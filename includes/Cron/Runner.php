<?php
namespace ATA\Cron;

use WP_Cron;

defined('ABSPATH') || exit;

/**
 * Cron runner for queue processing.
 * Hook: ata_queue_runner (registered via at_action 'ata_due_queue').
 */
class Runner
{
    public static function schedule(): void
    {
        // Scheduled posts will trigger this action via wp_schedule_single_event.
    }

    public static function handle(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ata_queue';

        // Atomic claim: find earliest pending job with lock_token=0.
        $sql = "UPDATE $table 
                SET status = 'running', locked_at = %s, lock_token = %s 
                WHERE id = (
                    SELECT id FROM $table 
                    WHERE status = 'pending' AND locked_at IS NULL 
                    ORDER BY next_run_at ASC 
                    LIMIT 1
                )";
        $wpdb->query($wpdb->prepare($sql, current_time('mysql', 1), wp_generate_password(16, false)));

        // Get claimed job.
        $jobId = $wpdb->insert_id;
        if ($jobId === 0) {
            return; // No jobs.
        }

        $job = $wpdb->get_row("SELECT * FROM $table WHERE id = %d", $jobId, ARRAY_A);
        if (!$job) {
            return;
        }

        $job['payload'] = json_decode($job['payload'], true);

        // Execute job based on type.
        switch ($job['job_type']) {
            case 'publish_post':
                self::runPublish($job);
                break;
            default:
                self::failJob($jobId, 'unknown_job_type', 'نوع کار ناشناخته.');
        }
    }

    private static function runPublish(array $job): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ata_posts';
        $postId = $job['payload']['post_id'] ?? 0;
        $post = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $postId), ARRAY_A);

        if (!$post) {
            self::failJob($job['id'], 'post_not_found', 'پست یافت نشد.');
            return;
        }

        $channelId = $post['channel_id'];
        $text = $post['body'];
        $imageId = $post['image_id'];

        // Build payload and send via Telegram.
        $container = \ATA\Container::instance();
        try {
            $telegram = $container->make(\ATA\Contracts\Telegram\TelegramProviderInterface::class);
            $payload = ['chat_id' => $channelId, 'text' => $text];
            $sent = $telegram->sendMessage($payload);
            if (!empty($imageId)) {
                $image = wp_get_attachment_url($imageId);
                if ($image) {
                    $telegram->sendPhoto(['chat_id' => $channelId, 'photo' => $image, 'caption' => $text]);
                }
            }

            $wpdb->query($wpdb->prepare(
                "UPDATE $table SET status = 'published', published_at = %s, last_error_code = NULL WHERE id = %d",
                [current_time('mysql'), $postId]
            ));

            self::completeJob($job['id']);
        } catch (\Exception $e) {
            self::retryJob($job['id'], $e->getMessage());
        }
    }

    private static function completeJob(string $jobId): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ata_queue';
        $wpdb->query($wpdb->prepare("UPDATE $table SET status = 'done', lock_token = NULL WHERE id = %d", $jobId));
    }

    private static function failJob(string $jobId, string $code, string $reason): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ata_queue';
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET status = 'failed', last_error = %s, lock_token = NULL WHERE id = %d",
            [$code . ': ' . $reason, $jobId]
        ));
    }

    private static function retryJob(string $jobId, string $error): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ata_queue';
        $job = $wpdb->get_row("SELECT * FROM $table WHERE id = %d", $jobId, ARRAY_A);
        $attempts = (int) $job['attempts'] + 1;
        $max = (int) $job['max_attempts'];

        if ($attempts >= $max) {
            self::failJob($jobId, 'max_attempts', $error);
            return;
        }

        // Exponential backoff: 2^n * 60 seconds.
        $delay = min(pow(2, $attempts) * 60, 3600);
        $next = gmdate('Y-m-d H:i:s', strtotime("+$delay seconds"));
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET status = 'pending', attempts = %d, next_run_at = %s, lock_token = NULL, last_error = %s WHERE id = %d",
            [$attempts, $next, $error, $jobId]
        ));
    }

    public static function clearExpiredLocks(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ata_queue';
        $ttl = 300; // 5 minutes.
        $expired = gmdate('Y-m-d H:i:s', strtotime("-$ttl seconds"));
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET status = 'pending', lock_token = NULL WHERE status = 'running' AND locked_at < %s",
            [$expired]
        ));
    }
}