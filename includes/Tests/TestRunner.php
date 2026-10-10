<?php
/**
 * Unit test for ATA Cron Runner.
 * Tests atomic claim, job execution, retry logic via the public handle().
 */
class TestRunner extends WP_UnitTestCase
{
    private MockHttpTransport $mockHttp;

    protected function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}ata_queue");
        $wpdb->query("DELETE FROM {$wpdb->prefix}ata_posts");

        $this->mockHttp = new MockHttpTransport();

        $container = \ATA\Core\Container::instance();
        $container->reset();
        \ATA\Core\Plugin::instance()->registerContainer();
        $container->bind(\ATA\Contracts\HttpClientInterface::class, fn() => $this->mockHttp);

        // The provider requires a configured bot token.
        (new \ATA\Security\SecretStore())->set('telegram_bot_token', '123456:TEST-TOKEN');
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}ata_queue");
        $wpdb->query("DELETE FROM {$wpdb->prefix}ata_posts");
        (new \ATA\Security\SecretStore())->delete('telegram_bot_token');
        parent::tearDown();
    }

    private function insertJob(array $overrides = []): int
    {
        global $wpdb;
        $row = array_merge([
            'job_type'     => 'publish_post',
            'status'       => 'pending',
            'locked_at'    => null,
            'lock_token'   => null,
            'attempts'     => 0,
            'max_attempts' => 5,
            'next_run_at'  => current_time('mysql', 1),
            'payload'      => json_encode(['post_id' => 999]),
        ], $overrides);
        $row = array_filter($row, static fn($v) => $v !== null);
        $wpdb->insert($wpdb->prefix . 'ata_queue', $row);
        return (int) $wpdb->insert_id;
    }

    public function test_atomic_claim_publishes_post_and_completes_job(): void
    {
        global $wpdb;

        $postId = (new \ATA\Infrastructure\WpDb\PostRepository())->insert([
            'title'      => 'Runner Post',
            'body'       => 'Hello channel',
            'channel_id' => -1001234567890,
            'status'     => 'approved',
        ]);

        $this->mockHttp->setResponse('POST', '*api.telegram.org*', [
            'code' => 200,
            'body' => json_encode(['ok' => true, 'result' => ['message_id' => 42]]),
        ]);

        $this->insertJob(['payload' => json_encode(['post_id' => $postId])]);

        \ATA\Cron\Runner::handle();

        $job = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}ata_queue", ARRAY_A);
        $this->assertEquals('done', $job['status']);
        $this->assertEmpty($job['lock_token']);

        $post = (new \ATA\Infrastructure\WpDb\PostRepository())->find($postId);
        $this->assertEquals('published', $post['status']);

        // Telegram must have received exactly one sendMessage (no photo → no sendPhoto).
        $this->assertSame(1, $this->mockHttp->getCallCount('POST', 'https://api.telegram.org/bot123456:TEST-TOKEN/sendMessage'));
    }

    public function test_no_pending_jobs_means_no_claim(): void
    {
        \ATA\Cron\Runner::handle();
        $this->assertSame(0, count($this->mockHttp->getRequests()));
    }
}
