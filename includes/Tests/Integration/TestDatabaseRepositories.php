<?php
/**
 * Integration Test: Database Repositories
 * Tests PostRepository, LogRepository, and post persistence.
 */
require_once __DIR__ . '/bootstrap.php';

class TestDatabaseRepositories extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Publish flows through the queue need a configured bot token.
        (new \ATA\Security\SecretStore())->set('telegram_bot_token', '123456:TEST-TOKEN');
    }

    protected function tearDown(): void
    {
        (new \ATA\Security\SecretStore())->delete('telegram_bot_token');
        parent::tearDown();
    }

    // --------------------------------------------------------------- PostRepository

    public function test_post_repository_insert_returns_id(): void
    {
        $repo = $this->container->make(\ATA\Infrastructure\WpDb\PostRepository::class);

        $id = $repo->insert([
            'title'      => 'Test Post',
            'body'       => 'Test content',
            'channel_id' => 1,
            'status'     => 'draft',
        ]);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function test_post_repository_insert_sets_timestamps(): void
    {
        $repo = $this->container->make(\ATA\Infrastructure\WpDb\PostRepository::class);

        $id = $repo->insert([
            'title'      => 'Test',
            'body'       => 'Content',
            'channel_id' => 1,
        ]);

        $post = $repo->find($id);
        $this->assertNotNull($post['created_at']);
        $this->assertNotNull($post['updated_at']);
        $this->assertEquals('draft', $post['status']);
    }

    public function test_post_repository_insert_handles_nulls(): void
    {
        $repo = $this->container->make(\ATA\Infrastructure\WpDb\PostRepository::class);

        // scheduled_at and published_at are null - should use DB defaults
        $id = $repo->insert([
            'title'           => 'Test',
            'body'            => 'Content',
            'channel_id'      => 1,
            'scheduled_at'    => null,
            'published_at'    => null,
            'image_id'        => null,
        ]);

        $post = $repo->find($id);
        // Nulls should not be stored as empty strings
        $this->assertNull($post['scheduled_at']);
        $this->assertNull($post['published_at']);
        $this->assertNull($post['image_id']);
    }

    public function test_post_repository_find_returns_null_for_missing(): void
    {
        $repo = $this->container->make(\ATA\Infrastructure\WpDb\PostRepository::class);

        $post = $repo->find(999999);
        $this->assertNull($post);
    }

    public function test_post_repository_update_changes_fields(): void
    {
        $repo = $this->container->make(\ATA\Infrastructure\WpDb\PostRepository::class);

        $id = $repo->insert([
            'title'      => 'Original',
            'body'       => 'Original content',
            'channel_id' => 1,
            'status'     => 'draft',
        ]);

        $result = $repo->update($id, [
            'title'  => 'Updated',
            'status' => 'approved',
        ]);

        $this->assertTrue($result);

        $post = $repo->find($id);
        $this->assertEquals('Updated', $post['title']);
        $this->assertEquals('approved', $post['status']);
        $this->assertEquals('Original content', $post['body']); // unchanged
    }

    public function test_post_repository_update_ignores_invalid_fields(): void
    {
        $repo = $this->container->make(\ATA\Infrastructure\WpDb\PostRepository::class);

        $id = $repo->insert([
            'title'      => 'Test',
            'body'       => 'Content',
            'channel_id' => 1,
        ]);

        // Try to update non-existent field
        $result = $repo->update($id, ['nonexistent_field' => 'value']);
        $this->assertFalse($result);

        // Should not affect the row
        $post = $repo->find($id);
        $this->assertEquals('Test', $post['title']);
    }

    public function test_post_repository_count_by_status(): void
    {
        $repo = $this->container->make(\ATA\Infrastructure\WpDb\PostRepository::class);

        $repo->insert(['title' => 'Draft 1', 'body' => 'C', 'channel_id' => 1, 'status' => 'draft']);
        $repo->insert(['title' => 'Draft 2', 'body' => 'C', 'channel_id' => 1, 'status' => 'draft']);
        $repo->insert(['title' => 'Approved', 'body' => 'C', 'channel_id' => 1, 'status' => 'approved']);
        $repo->insert(['title' => 'Published', 'body' => 'C', 'channel_id' => 1, 'status' => 'published']);

        $counts = $repo->countByStatus();

        $this->assertEquals(2, $counts['draft'] ?? 0);
        $this->assertEquals(1, $counts['approved'] ?? 0);
        $this->assertEquals(1, $counts['published'] ?? 0);
    }

    // --------------------------------------------------------------- LogRepository

    public function test_log_repository_insert(): void
    {
        $repo = $this->container->make(\ATA\Infrastructure\WpDb\LogRepository::class);

        $id = $repo->insert([
            'scope'    => 'test',
            'level'    => 'info',
            'message'  => 'Test message',
            'context'  => ['key' => 'value'],
        ]);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function test_log_repository_find(): void
    {
        $repo = $this->container->make(\ATA\Infrastructure\WpDb\LogRepository::class);

        $id = $repo->insert([
            'scope'   => 'test',
            'level'   => 'error',
            'message' => 'Error message',
        ]);

        $log = $repo->find($id);
        $this->assertNotNull($log);
        $this->assertEquals('error', $log['level']);
        $this->assertEquals('Error message', $log['message']);
    }

    public function test_log_repository_all_with_limit(): void
    {
        $repo = $this->container->make(\ATA\Infrastructure\WpDb\LogRepository::class);

        for ($i = 0; $i < 5; $i++) {
            $repo->insert([
                'scope'   => 'test',
                'level'   => 'info',
                'message' => "Message $i",
            ]);
        }

        $logs = $repo->all(3);
        $this->assertCount(3, $logs);
    }

    public function test_log_repository_count(): void
    {
        $repo = $this->container->make(\ATA\Infrastructure\WpDb\LogRepository::class);

        $repo->insert(['scope' => 'a', 'level' => 'info', 'message' => '1']);
        $repo->insert(['scope' => 'b', 'level' => 'error', 'message' => '2']);
        $repo->insert(['scope' => 'c', 'level' => 'warning', 'message' => '3']);

        $count = $repo->count();
        $this->assertEquals(3, $count);
    }

    public function test_log_repository_deletes_old(): void
    {
        $repo = $this->container->make(\ATA\Infrastructure\WpDb\LogRepository::class);

        // Insert old log (manually set old date)
        $this->wpdb->insert($this->wpdb->prefix . 'ata_logs', [
            'scope'      => 'old',
            'level'      => 'info',
            'message'    => 'Old log',
            'context'    => '{}',
            'created_at' => date('Y-m-d H:i:s', time() - 86400 * 40), // 40 days ago
        ]);

        $repo->insert(['scope' => 'new', 'level' => 'info', 'message' => 'New log']);

        // Delete logs older than 30 days
        $deleted = $repo->deleteOld(30);
        $this->assertEquals(1, $deleted);

        $count = $repo->count();
        $this->assertEquals(1, $count);
    }

    // --------------------------------------------------------------- Post Persistence via REST API

    public function test_full_post_lifecycle_via_rest(): void
    {
        // 1. Create post
        $response = $this->restRequest('POST', '/ata/v1/posts', [
            'title'      => 'Lifecycle Test',
            'body'       => 'Full lifecycle content',
            'channel_id' => 1,
        ], $this->adminUserId);

        $this->assertEquals(true, $response['success']);
        $postId = $response['id'];

        // 2. Verify in DB
        $repo = $this->container->make(\ATA\Infrastructure\WpDb\PostRepository::class);
        $post = $repo->find($postId);
        $this->assertEquals('draft', $post['status']);
        $this->assertEquals('Lifecycle Test', $post['title']);

        // 3. Approve
        $this->restRequest('POST', "/ata/v1/posts/$postId/approve", [], $this->adminUserId);
        $post = $repo->find($postId);
        $this->assertEquals('approved', $post['status']);

        // 4. Schedule
        $future = gmdate('Y-m-d H:i:s', time() + 7200);
        $this->restRequest('POST', "/ata/v1/posts/$postId/schedule", ['scheduled_at' => $future], $this->adminUserId);
        $post = $repo->find($postId);
        $this->assertEquals('scheduled', $post['status']);
        $this->assertEquals($future, $post['scheduled_at']);

        // 5. Publish (via queue)
        $this->mockTelegramResponse('sendMessage', ['message_id' => 999]);

        $queueId = $this->createQueueItem([
            'job_type' => 'publish_post',
            'payload'  => json_encode(['post_id' => $postId]),
        ]);

        $this->restRequest('POST', "/ata/v1/queue/$queueId/run", [], $this->adminUserId);

        $post = $repo->find($postId);
        $this->assertEquals('published', $post['status']);
        $this->assertNotNull($post['published_at']);
    }

    public function test_post_with_image(): void
    {
        // Create a test attachment
        $attachmentId = $this->factory->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg');

        $repo = $this->container->make(\ATA\Infrastructure\WpDb\PostRepository::class);
        $id = $repo->insert([
            'title'      => 'With Image',
            'body'       => 'Content with image',
            'channel_id' => 1,
            'image_id'   => $attachmentId,
        ]);

        $post = $repo->find($id);
        $this->assertEquals($attachmentId, $post['image_id']);
    }
}