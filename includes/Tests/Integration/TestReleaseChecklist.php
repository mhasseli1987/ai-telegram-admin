<?php
/**
 * Integration Test: Phase 17 — Release Candidate Checklist
 * Executes the 14 SPEC items end-to-end against the real plugin APIs
 * (mocked outbound HTTP only): installation, activation, setup, telegram
 * connection, AI connection, generation, preview, scheduling, publishing,
 * logs, errors, security, performance, uninstall.
 *
 * NOTE: uninstall runs last and reinstalls the tables afterwards so the
 * rest of the suite is unaffected.
 */
require_once __DIR__ . '/bootstrap.php';

class TestReleaseChecklist extends IntegrationTestCase
{
    // Must satisfy RestApi's token format regex (30+ chars after the colon).
    private const BOT_TOKEN = '123456:AAHTESTTOKEN1234567890ABCDEFGHIJ';
    private const CHAT_ID   = -1001234567890;

    protected function setUp(): void
    {
        parent::setUp();
        // This file performs REAL install/uninstall DDL. WP's test case
        // rewrites CREATE/DROP TABLE into TEMPORARY variants (per-connection)
        // via start_transaction() — those shadow tables would hijack every
        // later test in the process. Run this whole file against real tables.
        remove_filter('query', [$this, '_create_temporary_tables']);
        remove_filter('query', [$this, '_drop_temporary_tables']);
        $this->storeBotToken();
    }

    protected function tearDown(): void
    {
        delete_transient('ata_update_check');
        parent::tearDown();
        // NOTE: do NOT re-add WP's _create/_drop_temporary_tables filters here.
        // They are per-test (added in start_transaction, removed in tear_down);
        // re-adding them with THIS instance leaves a stale registration that
        // WP's _restore_hooks() resurrects in the NEXT test, silently
        // rewriting real DDL back into TEMPORARY DDL.
    }

    private function storeBotToken(): void
    {
        (new \ATA\Security\SecretStore())->set('telegram_bot_token', self::BOT_TOKEN);
    }

    private function mockGetMe(): void
    {
        $this->mockTelegramResponse('getMe', ['id' => 777000, 'username' => 'ata_test_bot', 'is_bot' => true]);
    }

    private function createChannel(): int
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'ata_channels', [
            'chat_id'    => self::CHAT_ID,
            'title'      => 'RC Channel',
            'username'   => 'rc_channel',
            'chat_type'  => 'channel',
            'status'     => 'connected',
            'created_at' => current_time('mysql', 1),
        ]);
        // ata_posts.channel_id stores the TELEGRAM chat_id (RestApi preview
        // resolves channels by chat_id), not the row id.
        return self::CHAT_ID;
    }

    private function createPost(int $channelId): int
    {
        return $this->createTestPost(['channel_id' => $channelId, 'body' => 'متن آزمایشی انتشار']);
    }

    /** Add a default AI provider via the real REST API. @return int provider id */
    private function addDefaultAiProvider(): int
    {
        $resp = $this->restRequest('POST', '/ata/v1/ai/providers', [
            'name'       => 'OpenAI',
            'driver'     => 'openai_compatible',
            'config'     => ['baseUrl' => 'https://api.openai.com/v1/chat/completions', 'model' => 'gpt-4o-mini'],
            'secret'     => 'sk-test-key-12345678',
            'is_default' => 1,
        ], $this->adminUserId);
        return (int) ($resp['id'] ?? 0);
    }

    /** Make the single pending publish job for $queueId due immediately. */
    private function forceDue(int $queueId): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}ata_queue SET next_run_at = %s WHERE id = %d",
            [gmdate('Y-m-d H:i:s', time() - 10), $queueId]
        ));
    }

    // 1. Installation ------------------------------------------------------

    public function test_01_installation_creates_all_tables_and_version(): void
    {
        global $wpdb;
        (new \ATA\Infrastructure\WpDb\Installer())->install();

        foreach (['ata_posts', 'ata_channels', 'ata_providers', 'ata_queue', 'ata_logs'] as $t) {
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . $t));
            $this->assertSame($wpdb->prefix . $t, $found, "Table $t missing after install");
        }
        $this->assertSame(ATA_DB_VERSION, get_option('ata_db_version'));
    }

    // 2. Activation --------------------------------------------------------

    public function test_02_activation_schedules_cron_and_backfills(): void
    {
        wp_clear_scheduled_hook(\ATA\Cron\Runner::HOOK);
        $this->assertFalse(wp_next_scheduled(\ATA\Cron\Runner::HOOK));

        \ATA\Core\Plugin::activate();

        $this->assertNotFalse(wp_next_scheduled(\ATA\Cron\Runner::HOOK), 'Cron event not scheduled on activation');
        $this->assertSame(1, get_option('ata_cpt_migrated'), 'CPT migration guard not set');
    }

    // 3. Setup (settings) ---------------------------------------------------

    public function test_03_setup_persists_settings_via_rest(): void
    {
        $resp = $this->restRequest('POST', '/ata/v1/settings', [
            'ata_default_language'  => 'fa',
            'ata_default_tone'      => 'friendly',
            'ata_log_retention_days' => 30,
        ], $this->adminUserId);
        $this->assertTrue((bool) $resp['success'], 'Settings save failed: ' . wp_json_encode($resp));

        $resp = $this->restRequest('GET', '/ata/v1/settings', [], $this->adminUserId);
        $this->assertSame('fa', $resp['values']['ata_default_language'] ?? null);
        $this->assertSame('friendly', $resp['values']['ata_default_tone'] ?? null);
        $this->assertSame(30, (int) ($resp['values']['ata_log_retention_days'] ?? 0));

        // Unknown keys are rejected, not silently stored.
        $resp = $this->restRequest('POST', '/ata/v1/settings', ['ata_nonsense' => 'x'], $this->adminUserId);
        $this->assertFalse((bool) $resp['success']);
    }

    // 4. Telegram connection -------------------------------------------------

    public function test_04_telegram_connection_stores_bot_identity(): void
    {
        $this->mockGetMe();
        $resp = $this->restRequest('POST', '/ata/v1/telegram/connect', ['token' => self::BOT_TOKEN], $this->adminUserId);

        $this->assertTrue((bool) $resp['success']);
        $this->assertSame('ata_test_bot', $resp['bot_username']);
        $this->assertSame(self::BOT_TOKEN, (new \ATA\Security\SecretStore())->get('telegram_bot_token'));
        $this->assertSame('ata_test_bot', get_option('ata_bot_username'));
    }

    // 5. AI connection -------------------------------------------------------

    public function test_05_ai_connection_adds_and_tests_provider(): void
    {
        $providerId = $this->addDefaultAiProvider();
        $this->assertGreaterThan(0, $providerId, 'Provider add failed: ' . wp_json_encode($this->mockHttp->getRequests()));

        // The stored API key must never come back through the API.
        $resp = $this->restRequest('GET', '/ata/v1/ai/providers', [], $this->adminUserId);
        $this->assertStringNotContainsString('sk-test-key-12345678', wp_json_encode($resp));

        $this->mockAiResponse('ok');
        $resp = $this->restRequest('POST', "/ata/v1/ai/providers/{$providerId}/test", [], $this->adminUserId);
        $this->assertTrue((bool) $resp['success'], 'Provider test failed: ' . wp_json_encode($resp));
        $this->assertTrue((bool) ($resp['result']['success'] ?? false));
    }

    // 6. Generation ----------------------------------------------------------

    public function test_06_generation_creates_draft(): void
    {
        $this->assertGreaterThan(0, $this->addDefaultAiProvider(), 'Default AI provider required for generation');
        $channelId = $this->createChannel();
        $this->mockAiResponse('#عنوان' . PHP_EOL . 'محتوای تولید شده برای کانال.');

        $resp = $this->restRequest('POST', '/ata/v1/ai/generate', [
            'input'      => 'یک پست درباره فروش',
            'operation'  => 'generate',
            'channel_id' => $channelId,
            'title'      => 'پست فروش',
        ], $this->adminUserId);

        $this->assertTrue((bool) $resp['success'], 'Generate failed: ' . wp_json_encode($resp));
        $this->assertGreaterThan(0, (int) $resp['stored_post_id'], 'Draft was not stored');
        $this->assertStringContainsString('محتوای تولید شده', (string) $resp['content']);
    }

    // 7. Preview --------------------------------------------------------------

    public function test_07_preview_returns_post_with_channel(): void
    {
        $channelId = $this->createChannel();
        $postId = $this->createPost($channelId);

        $resp = $this->restRequest('POST', "/ata/v1/posts/{$postId}/preview", [], $this->adminUserId);
        $this->assertTrue((bool) $resp['success']);
        $this->assertSame('RC Channel', $resp['preview']['channel_title']);
        $this->assertSame('متن آزمایشی انتشار', $resp['preview']['text']);
    }

    // 8. Scheduling ------------------------------------------------------------

    public function test_08_scheduling_enqueues_future_job(): void
    {
        global $wpdb;
        $channelId = $this->createChannel();
        $postId = $this->createPost($channelId);

        $when = gmdate('Y-m-d H:i:s', time() + 3600);
        $resp = $this->restRequest('POST', "/ata/v1/posts/{$postId}/schedule", ['scheduled_at' => $when], $this->adminUserId);

        $this->assertTrue((bool) $resp['success'], 'Schedule failed: ' . wp_json_encode($resp));
        $this->assertGreaterThan(0, (int) $resp['queue_id']);
        $this->assertSame('scheduled', $resp['post']['status']);

        // Nothing publishes before its time: the runner must skip it.
        $this->mockTelegramResponse('sendMessage', ['message_id' => 1]);
        \ATA\Cron\Runner::handle();
        $post = (new \ATA\Infrastructure\WpDb\PostRepository())->find($postId);
        $this->assertSame('scheduled', $post['status'], 'Post published before scheduled time');
        $this->assertSame(0, $this->mockHttp->getCallCount('POST', 'sendMessage'));
    }

    // 9. Publishing -------------------------------------------------------------

    public function test_09_publishing_via_queue_sends_and_completes(): void
    {
        global $wpdb;
        $channelId = $this->createChannel();
        $postId = $this->createPost($channelId);

        $this->mockTelegramResponse('sendMessage', ['message_id' => 42, 'chat' => ['username' => 'rc_channel']]);
        // schedulePost only accepts future times; schedule then force due.
        $resp = $this->restRequest('POST', "/ata/v1/posts/{$postId}/schedule", ['scheduled_at' => gmdate('Y-m-d H:i:s', time() + 3600)], $this->adminUserId);
        $this->assertTrue((bool) $resp['success']);
        $this->forceDue((int) $resp['queue_id']);

        \ATA\Cron\Runner::handle();

        $post = (new \ATA\Infrastructure\WpDb\PostRepository())->find($postId);
        $this->assertSame('published', $post['status']);
        $this->assertSame(1, $this->mockHttp->getCallCount('POST', 'https://api.telegram.org/bot' . self::BOT_TOKEN . '/sendMessage'));

        $job = $this->getQueueItem((int) $resp['queue_id']);
        $this->assertSame('done', $job['status']);
    }

    // 10. Logs --------------------------------------------------------------------

    public function test_10_logs_capture_activity(): void
    {
        $channelId = $this->createChannel();
        $postId = $this->createPost($channelId);
        $this->mockTelegramResponse('sendMessage', ['message_id' => 7]);
        $resp = $this->restRequest('POST', "/ata/v1/posts/{$postId}/schedule", ['scheduled_at' => gmdate('Y-m-d H:i:s', time() + 3600)], $this->adminUserId);
        $this->assertTrue((bool) $resp['success']);
        $this->forceDue((int) $resp['queue_id']);
        \ATA\Cron\Runner::handle();

        $resp = $this->restRequest('GET', '/ata/v1/logs', [], $this->adminUserId);
        $this->assertGreaterThan(0, (int) $resp['total'], 'No logs recorded');

        $messages = wp_json_encode($resp['logs']);
        $this->assertStringContainsString('Post published', $messages);

        // Clear works too.
        $resp = $this->restRequest('DELETE', '/ata/v1/logs', [], $this->adminUserId);
        $this->assertTrue((bool) $resp['success']);
        $resp = $this->restRequest('GET', '/ata/v1/logs', [], $this->adminUserId);
        $this->assertSame(0, (int) $resp['total']);
    }

    // 11. Errors (retry / backoff / final failure) ---------------------------------

    public function test_11_errors_retry_then_record(): void
    {
        global $wpdb;
        $channelId = $this->createChannel();
        $postId = $this->createPost($channelId);

        // Telegram API answers 500 — send must fail.
        $this->mockTelegramResponse('sendMessage', ['description' => 'Internal Server Error'], 500, false);
        $resp = $this->restRequest('POST', "/ata/v1/posts/{$postId}/schedule", ['scheduled_at' => gmdate('Y-m-d H:i:s', time() + 3600)], $this->adminUserId);
        $this->assertTrue((bool) $resp['success']);
        $this->forceDue((int) $resp['queue_id']);
        \ATA\Cron\Runner::handle();

        $job = $this->getQueueItem((int) $resp['queue_id']);
        $this->assertSame('pending', $job['status'], 'Failed job must be re-queued with backoff');
        $this->assertSame(1, (int) $job['attempts']);
        $this->assertNotSame('0000-00-00 00:00:00', $job['next_run_at']);
        $this->assertGreaterThan(time(), strtotime($job['next_run_at'] . ' UTC') - 1, 'Backoff not applied');

        $post = (new \ATA\Infrastructure\WpDb\PostRepository())->find($postId);
        $this->assertSame('publish_failed', $post['last_error_code']);
        $this->assertSame('scheduled', $post['status'], 'Post must not be marked published on failure');

        // Exhaust retries -> final failure with readable reason.
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}ata_queue SET attempts = %d, next_run_at = %s WHERE id = %d",
            [4, gmdate('Y-m-d H:i:s', time() - 10), (int) $resp['queue_id']]
        ));
        \ATA\Cron\Runner::handle();
        $job = $this->getQueueItem((int) $resp['queue_id']);
        $this->assertSame('failed', $job['status']);
        $this->assertStringContainsString('max_attempts', (string) $job['last_error']);
    }

    // 12. Security -------------------------------------------------------------------

    public function test_12_security_ssrf_authz_and_secret_hygiene(): void
    {
        // SSRF: private targets blocked at the single HTTP choke point.
        $gate = new \ATA\Security\SsrfGate();
        $this->assertFalse($gate->validate('http://127.0.0.1:3306/'));
        $this->assertFalse($gate->validate('http://192.168.1.10/admin'));
        $this->assertFalse($gate->validate('file:///etc/passwd'));
        $this->assertFalse($gate->validate('http://169.254.169.254/latest/meta-data/'));

        // AuthZ: unauthenticated REST access denied on every route family.
        foreach (['GET /ata/v1/queue', 'GET /ata/v1/logs', 'GET /ata/v1/settings', 'GET /ata/v1/dashboard'] as $call) {
            [$method, $route] = explode(' ', $call);
            $resp = $this->restRequest($method, $route, [], 0);
            $status = $resp['data']['status'] ?? ($resp['code'] ?? 0);
            $this->assertSame(401, (int) $status, "$route must reject logged-out users, got: " . wp_json_encode($resp));
        }

        // Secrets at rest are ciphertext, not plaintext.
        $raw = get_option('ata_secret_telegram_bot_token');
        $this->assertIsString($raw);
        $this->assertStringNotContainsString(self::BOT_TOKEN, (string) $raw);
    }

    // 13. Performance ------------------------------------------------------------------

    public function test_13_performance_runner_bounded_work(): void
    {
        global $wpdb;
        $channelId = $this->createChannel();
        $this->mockTelegramResponse('sendMessage', ['message_id' => 1]);

        // Seed 5 due jobs (MAX_JOBS_PER_RUN) — runner must drain them in one pass.
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $postId = $this->createPost($channelId);
            $ids[] = $this->createQueueItem([
                'payload'     => wp_json_encode(['post_id' => $postId]),
                'next_run_at' => gmdate('Y-m-d H:i:s', time() - 5),
            ]);
        }

        $start = microtime(true);
        \ATA\Cron\Runner::handle();
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(5.0, $elapsed, "Runner too slow: {$elapsed}s for 5 jobs");
        foreach ($ids as $id) {
            $this->assertSame('done', $this->getQueueItem($id)['status'], "Job $id not completed");
        }
    }

    // 14. Uninstall (LAST — restores tables afterwards) ---------------------------------

    public function test_14_uninstall_removes_everything(): void
    {
        global $wpdb;
        $this->storeBotToken();
        $this->createChannel();
        update_option('ata_license', ['key' => 'X', 'activated' => true]);

        // register_uninstall_hook stores callables in one 'uninstall_plugins' option.
        $uninstallable = get_option('uninstall_plugins', []);
        $basename = plugin_basename(ATA_FILE);
        $this->assertIsArray($uninstallable);
        $this->assertArrayHasKey($basename, $uninstallable, 'Uninstall hook not registered');
        $this->assertSame('uninstall', $uninstallable[$basename][1] ?? null);

        // Any TEMPORARY twin of a plugin table left on this connection by an
        // earlier test would swallow the unqualified DROP TABLE below (MySQL
        // drops the temp twin first). Clear the temp twins so the real DDL runs.
        foreach (['ata_posts', 'ata_channels', 'ata_providers', 'ata_queue', 'ata_logs'] as $t) {
            $wpdb->query("DROP TEMPORARY TABLE IF EXISTS {$wpdb->prefix}{$t}");
        }

        \ATA\Core\Plugin::uninstall();

        foreach (['ata_posts', 'ata_channels', 'ata_providers', 'ata_queue', 'ata_logs'] as $t) {
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . $t));
            $this->assertNull($found, "Table $t survived uninstall");
        }
        $this->assertNull(get_option('ata_db_version', null));
        $this->assertNull(get_option('ata_license', null));
        $this->assertNull(get_option('ata_secret_telegram_bot_token', null), 'Bot token survived uninstall');

        // Restore for the rest of the suite.
        (new \ATA\Infrastructure\WpDb\Installer())->install();
    }
}
