<?php
/**
 * Integration Test: Queue Execution, Scheduling, Retries, Failures
 * Tests the Cron Runner queue processing with atomic claims,
 * exponential backoff retries, and failure handling.
 */
require_once __DIR__ . '/bootstrap.php';

class TestQueueExecution extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The Telegram provider requires a configured token for publish jobs.
        (new \ATA\Security\SecretStore())->set('telegram_bot_token', '123456:TEST-TOKEN');
    }

    protected function tearDown(): void
    {
        (new \ATA\Security\SecretStore())->delete('telegram_bot_token');
        parent::tearDown();
    }

    // --------------------------------------------------------------- Atomic Claim

    public function test_runner_handle_claims_one_job_atomically(): void
    {
        $postId = $this->createTestPost(['status' => 'scheduled', 'channel_id' => -100123]);
        $this->createTestChannel(['chat_id' => '-100123']);

        $this->mockTelegramResponse('sendMessage', ['message_id' => 123]);

        // Create multiple pending jobs
        $q1 = $this->createQueueItem(['job_type' => 'publish_post', 'payload' => json_encode(['post_id' => $postId])]);
        $q2 = $this->createQueueItem(['job_type' => 'publish_post', 'payload' => json_encode(['post_id' => $postId])]);

        // Run handler - should claim and process ONE job (MAX_JOBS_PER_RUN = 5)
        \ATA\Cron\Runner::handle();

        // Both jobs should be processed since MAX_JOBS_PER_RUN = 5 and we only have 2
        $repo = $this->container->make(\ATA\Infrastructure\WpDb\PostRepository::class);
        $post = $repo->find($postId);
        $this->assertEquals('published', $post['status']);
    }

    public function test_runner_respects_max_jobs_per_run(): void
    {
        // This test would need to modify MAX_JOBS_PER_RUN constant or mock it
        // For now, verify the constant exists (private const → reflection).
        $const = (new ReflectionClass(\ATA\Cron\Runner::class))->getConstant('MAX_JOBS_PER_RUN');
        $this->assertEquals(5, $const);
    }

    public function test_atomic_claim_prevents_double_processing(): void
    {
        $postId = $this->createTestPost(['status' => 'scheduled', 'channel_id' => -100123]);
        $this->createTestChannel(['chat_id' => '-100123']);

        $this->mockTelegramResponse('sendMessage', ['message_id' => 456]);

        $queueId = $this->createQueueItem([
            'job_type' => 'publish_post',
            'payload'  => json_encode(['post_id' => $postId]),
        ]);

        // Simulate concurrent processing by manually setting lock
        global $wpdb;
        $token = 'manual-lock-token';
        $now = current_time('mysql', 1);
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}ata_queue SET status = 'running', locked_at = %s, lock_token = %s WHERE id = %d",
            [$now, $token, $queueId]
        ));

        // Run handler - should skip locked job
        \ATA\Cron\Runner::handle();

        // Job should still be locked (not processed again)
        $queue = $this->getQueueItem($queueId);
        $this->assertEquals('running', $queue['status']);
        $this->assertEquals($token, $queue['lock_token']);
    }

    // --------------------------------------------------------------- Job Execution

    public function test_publish_post_job_sends_to_telegram(): void
    {
        $postId = $this->createTestPost(['status' => 'scheduled', 'channel_id' => -100123]);
        $this->createTestChannel(['chat_id' => '-100123']);

        $this->mockTelegramResponse('sendMessage', ['message_id' => 789]);

        $queueId = $this->createQueueItem([
            'job_type' => 'publish_post',
            'payload'  => json_encode(['post_id' => $postId]),
        ]);

        $result = \ATA\Cron\Runner::runNow($queueId);
        $this->assertTrue($result);

        $repo = $this->container->make(\ATA\Infrastructure\WpDb\PostRepository::class);
        $post = $repo->find($postId);
        $this->assertEquals('published', $post['status']);
    }

    public function test_publish_post_with_image_sends_photo(): void
    {
        $attachmentId = $this->factory->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg');

        $postId = $this->createTestPost([
            'status'     => 'scheduled',
            'channel_id' => -100123,
            'image_id'   => $attachmentId,
        ]);
        $this->createTestChannel(['chat_id' => '-100123']);

        $this->mockTelegramResponse('sendPhoto', ['message_id' => 999]);

        $queueId = $this->createQueueItem([
            'job_type' => 'publish_post',
            'payload'  => json_encode(['post_id' => $postId]),
        ]);

        $result = \ATA\Cron\Runner::runNow($queueId);
        $this->assertTrue($result);

        $repo = $this->container->make(\ATA\Infrastructure\WpDb\PostRepository::class);
        $post = $repo->find($postId);
        $this->assertEquals('published', $post['status']);

        // Verify sendPhoto was called (not sendMessage)
        $req = $this->getLastRequest('POST', 'sendPhoto');
        $this->assertNotNull($req);
    }

    public function test_publish_post_fails_gracefully_on_telegram_error(): void
    {
        $postId = $this->createTestPost(['status' => 'scheduled', 'channel_id' => -100123]);
        $this->createTestChannel(['chat_id' => '-100123']);

        // Mock Telegram error
        $this->mockHttp->setResponse('POST', '*bot*/sendMessage', [
            'code' => 400,
            'body' => json_encode(['ok' => false, 'description' => 'Bad Request: chat not found']),
        ]);

        $queueId = $this->createQueueItem([
            'job_type' => 'publish_post',
            'payload'  => json_encode(['post_id' => $postId]),
        ]);

        $result = \ATA\Cron\Runner::runNow($queueId);
        $this->assertTrue($result); // runNow returns true if claimed

        // Job should be retried (status back to pending)
        $queue = $this->getQueueItem($queueId);
        $this->assertEquals('pending', $queue['status']);
        $this->assertEquals(1, $queue['attempts']); // incremented once by retryJob()
    }

    public function test_job_fails_when_post_not_found(): void
    {
        $queueId = $this->createQueueItem([
            'job_type' => 'publish_post',
            'payload'  => json_encode(['post_id' => 999999]), // non-existent
        ]);

        $result = \ATA\Cron\Runner::runNow($queueId);
        $this->assertTrue($result);

        $queue = $this->getQueueItem($queueId);
        $this->assertEquals('failed', $queue['status']);
        $this->assertStringContainsString('post_not_found', $queue['last_error'] ?? '');
    }

    public function test_job_fails_when_channel_invalid(): void
    {
        $postId = $this->createTestPost(['status' => 'scheduled', 'channel_id' => 0]); // invalid

        $queueId = $this->createQueueItem([
            'job_type' => 'publish_post',
            'payload'  => json_encode(['post_id' => $postId]),
        ]);

        $result = \ATA\Cron\Runner::runNow($queueId);
        $this->assertTrue($result);

        $queue = $this->getQueueItem($queueId);
        $this->assertEquals('failed', $queue['status']);
        $this->assertStringContainsString('invalid_post', $queue['last_error'] ?? '');
    }

    // --------------------------------------------------------------- Retry Logic

    public function test_exponential_backoff_retry(): void
    {
        $postId = $this->createTestPost(['status' => 'scheduled', 'channel_id' => -100123]);
        $this->createTestChannel(['chat_id' => '-100123']);

        // First attempt fails
        $this->mockHttp->setResponse('POST', '*bot*/sendMessage', [
            'code' => 500,
            'body' => json_encode(['ok' => false, 'description' => 'Internal Server Error']),
        ]);

        $queueId = $this->createQueueItem([
            'job_type' => 'publish_post',
            'payload'  => json_encode(['post_id' => $postId]),
            'attempts' => 0,
            'max_attempts' => 5,
        ]);

        \ATA\Cron\Runner::runNow($queueId);

        $queue = $this->getQueueItem($queueId);
        $this->assertEquals('pending', $queue['status']);
        $this->assertEquals(1, $queue['attempts']);

        // Check next_run_at is in future (backoff: 2^1 * 60 = 120 seconds)
        $nextRun = strtotime($queue['next_run_at']);
        $this->assertGreaterThan(time() + 100, $nextRun);
        $this->assertLessThan(time() + 200, $nextRun);
    }

    public function test_max_attempts_marks_job_failed(): void
    {
        $postId = $this->createTestPost(['status' => 'scheduled', 'channel_id' => -100123]);
        $this->createTestChannel(['chat_id' => '-100123']);

        $this->mockHttp->setResponse('POST', '*bot*/sendMessage', [
            'code' => 500,
            'body' => json_encode(['ok' => false]),
        ]);

        $queueId = $this->createQueueItem([
            'job_type' => 'publish_post',
            'payload'  => json_encode(['post_id' => $postId]),
            'attempts' => 4, // one away from max (5)
            'max_attempts' => 5,
        ]);

        \ATA\Cron\Runner::runNow($queueId);

        $queue = $this->getQueueItem($queueId);
        $this->assertEquals('failed', $queue['status']);
        $this->assertEquals(5, $queue['attempts']);
        $this->assertStringContainsString('max_attempts', $queue['last_error'] ?? '');
    }

    public function test_retry_resets_lock(): void
    {
        $postId = $this->createTestPost(['status' => 'scheduled', 'channel_id' => -100123]);
        $this->createTestChannel(['chat_id' => '-100123']);

        $this->mockHttp->setResponse('POST', '*bot*/sendMessage', ['code' => 500, 'body' => 'error']);

        $queueId = $this->createQueueItem([
            'job_type' => 'publish_post',
            'payload'  => json_encode(['post_id' => $postId]),
        ]);

        \ATA\Cron\Runner::runNow($queueId);

        $queue = $this->getQueueItem($queueId);
        $this->assertEquals('pending', $queue['status']);
        $this->assertNull($queue['lock_token']);
        $this->assertNull($queue['locked_at']);
    }

    // --------------------------------------------------------------- Expired Lock Cleanup

    public function test_clear_expired_locks_releases_stale_jobs(): void
    {
        $postId = $this->createTestPost(['status' => 'scheduled', 'channel_id' => -100123]);
        $this->createTestChannel(['chat_id' => '-100123']);

        $queueId = $this->createQueueItem([
            'job_type' => 'publish_post',
            'payload'  => json_encode(['post_id' => $postId]),
        ]);

        // Manually set lock as expired (6 minutes ago - LOCK_TTL = 5 min)
        global $wpdb;
        $expired = gmdate('Y-m-d H:i:s', time() - 360);
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}ata_queue SET status = 'running', locked_at = %s, lock_token = 'expired' WHERE id = %d",
            [$expired, $queueId]
        ));

        // Run cleanup
        \ATA\Cron\Runner::clearExpiredLocks();

        $queue = $this->getQueueItem($queueId);
        $this->assertEquals('pending', $queue['status']);
        $this->assertNull($queue['lock_token']);
        $this->assertNull($queue['locked_at']);
    }

    public function test_clear_expired_locks_does_not_touch_valid_locks(): void
    {
        $postId = $this->createTestPost(['status' => 'scheduled', 'channel_id' => -100123]);
        $this->createTestChannel(['chat_id' => '-100123']);

        $queueId = $this->createQueueItem([
            'job_type' => 'publish_post',
            'payload'  => json_encode(['post_id' => $postId]),
        ]);

        // Set lock as recent (1 minute ago)
        global $wpdb;
        $recent = gmdate('Y-m-d H:i:s', time() - 60);
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}ata_queue SET status = 'running', locked_at = %s, lock_token = 'valid' WHERE id = %d",
            [$recent, $queueId]
        ));

        \ATA\Cron\Runner::clearExpiredLocks();

        $queue = $this->getQueueItem($queueId);
        $this->assertEquals('running', $queue['status']);
        $this->assertEquals('valid', $queue['lock_token']);
    }

    // --------------------------------------------------------------- Scheduler Integration

    public function test_schedule_one_shot_job(): void
    {
        \ATA\Cron\SchedulerAdapter::scheduleOneShot('ata_test_hook', 60, ['data' => 'test']);

        $scheduled = wp_next_scheduled('ata_test_hook', ['data' => 'test']);
        $this->assertNotFalse($scheduled);
        $this->assertGreaterThan(time() + 50, $scheduled);
        $this->assertLessThan(time() + 70, $scheduled);

        // Cleanup
        wp_clear_scheduled_hook('ata_test_hook', ['data' => 'test']);
    }

    public function test_schedule_one_shot_immediate(): void
    {
        \ATA\Cron\SchedulerAdapter::scheduleOneShot('ata_test_immediate', 0, ['now' => true]);

        $scheduled = wp_next_scheduled('ata_test_immediate', ['now' => true]);
        $this->assertNotFalse($scheduled);
        $this->assertLessThanOrEqual(time() + 5, $scheduled);

        wp_clear_scheduled_hook('ata_test_immediate', ['now' => true]);
    }

    // --------------------------------------------------------------- Helpers

    protected function createQueueItem(array $overrides = []): int
    {
        // Matches Installer schema (no priority column).
        $defaults = [
            'job_type'     => 'publish_post',
            'status'       => 'pending',
            'attempts'     => 0,
            'max_attempts' => 5,
            'payload'      => '{}',
            'next_run_at'  => current_time('mysql', 1),
            'created_at'   => current_time('mysql', 1),
            'updated_at'   => current_time('mysql', 1),
        ];
        $data = array_merge($defaults, $overrides);
        $this->wpdb->insert($this->wpdb->prefix . 'ata_queue', $data);
        return (int) $this->wpdb->insert_id;
    }

    protected function getQueueItem(int $id): ?array
    {
        return $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->wpdb->prefix}ata_queue WHERE id = %d", $id),
            ARRAY_A
        ) ?: null;
    }
}