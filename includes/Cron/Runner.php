<?php
namespace ATA\Cron;

defined('ABSPATH') || exit;

/**
 * WP-Cron runner for the publish queue.
 * Atomic claim via lock_token, bounded jobs per run, retry with backoff.
 */
class Runner
{
    public const HOOK = 'ata_due_queue';

    private const MAX_JOBS_PER_RUN = 5;
    private const LOCK_TTL = 300; // 5 minutes.

    /** Make sure the recurring queue event exists (idempotent). */
    public static function ensureScheduled(): void
    {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, 'ata_minute', self::HOOK);
        }
    }

    public static function handle(): void
    {
        self::clearExpiredLocks();
        for ($i = 0; $i < self::MAX_JOBS_PER_RUN; $i++) {
            if (!self::runNext()) {
                break;
            }
        }
    }

    /**
     * Claim at most one due job atomically and run it.
     * Returns true when a job was claimed (so callers can keep draining).
     */
    private static function runNext(): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ata_queue';

        // Generate the token first, then claim by conditional UPDATE.
        // (The old code relied on $wpdb->insert_id after an UPDATE, which is not reliable.)
        $token = wp_generate_password(24, false, false);
        $now = current_time('mysql', 1); // UTC.

        $claimed = $wpdb->query($wpdb->prepare(
            "UPDATE $table
             SET status = 'running', locked_at = %s, lock_token = %s
             WHERE status = 'pending' AND locked_at IS NULL AND next_run_at <= %s
             ORDER BY next_run_at ASC
             LIMIT 1",
            [$now, $token, $now]
        ));

        if (!$claimed) {
            return false;
        }

        $job = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE lock_token = %s", $token),
            ARRAY_A
        );
        if (!$job) {
            return false;
        }

        $job['payload'] = json_decode((string) ($job['payload'] ?? ''), true) ?: [];

        switch ($job['job_type']) {
            case 'publish_post':
                self::runPublish($job);
                break;
            default:
                self::failJob((int) $job['id'], 'unknown_job_type', 'نوع کار ناشناخته است.');
        }

        return true;
    }

    private static function runPublish(array $job): void
    {
        global $wpdb;
        $posts = $wpdb->prefix . 'ata_posts';
        $postId = (int) ($job['payload']['post_id'] ?? 0);

        // Old code passed $jobId as get_row() output-format arg — the query never bound.
        $post = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $posts WHERE id = %d", $postId),
            ARRAY_A
        );

        if (!$post) {
            self::failJob((int) $job['id'], 'post_not_found', 'پست یافت نشد.');
            return;
        }

        $channelId = (int) $post['channel_id'];
        $text = trim((string) $post['body']);

        // Telegram channel IDs are negative (e.g. -1001234567890); only 0 means unset.
        if ($channelId === 0 || $text === '') {
            self::failJob((int) $job['id'], 'invalid_post', 'متن یا کانال پست تنظیم نشده است.');
            return;
        }

        try {
            $container = \ATA\Core\Container::instance();
            $telegram = $container->make(\ATA\Contracts\Telegram\TelegramProviderInterface::class);

            $imageId = (int) ($post['image_id'] ?? 0);
            $sent = null;

            if ($imageId > 0) {
                $image = wp_get_attachment_url($imageId);
                if ($image) {
                    // Single message: photo with caption (old code sent text AND photo = duplicate).
                    $sent = $telegram->sendPhoto([
                        'chat_id' => $channelId,
                        'photo'   => $image,
                        'caption' => $text,
                    ]);
                }
            }

            if ($sent === null) {
                $sent = $telegram->sendMessage(['chat_id' => $channelId, 'text' => $text]);
            }

            $now = current_time('mysql', 1); // UTC.
            $wpdb->query($wpdb->prepare(
                "UPDATE $posts
                 SET status = 'published', published_at = %s, updated_at = %s, last_error_code = NULL
                 WHERE id = %d",
                [$now, $now, $postId]
            ));

            self::completeJob((int) $job['id']);
        } catch (\Throwable $e) {
            $wpdb->query($wpdb->prepare(
                "UPDATE $posts SET attempts = attempts + 1, last_error_code = %s WHERE id = %d",
                ['publish_failed', $postId]
            ));
            self::retryJob((int) $job['id'], $e->getMessage());
        }
    }

    private static function completeJob(int $jobId): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ata_queue';
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET status = 'done', locked_at = NULL, lock_token = NULL WHERE id = %d",
            $jobId
        ));
    }

    private static function failJob(int $jobId, string $code, string $reason): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ata_queue';
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET status = 'failed', last_error = %s, locked_at = NULL, lock_token = NULL WHERE id = %d",
            [$code . ': ' . $reason, $jobId]
        ));
    }

    private static function retryJob(int $jobId, string $error): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ata_queue';

        // Old code called get_row() with a raw "%d" query (never prepared).
        $job = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $jobId),
            ARRAY_A
        );
        if (!$job) {
            return;
        }

        $attempts = (int) $job['attempts'] + 1;
        $max = (int) $job['max_attempts'];

        // Record the attempt before the cap check, so a job that exhausts
        // its retries shows attempts == max_attempts.
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET attempts = %d, updated_at = %s WHERE id = %d",
            [$attempts, current_time('mysql', 1), $jobId]
        ));

        if ($attempts >= $max) {
            self::failJob($jobId, 'max_attempts', $error);
            return;
        }

        // Exponential backoff: 2^n * 60 seconds, capped at 1 hour. UTC.
        $delay = (int) min(pow(2, $attempts) * 60, 3600);
        $next = gmdate('Y-m-d H:i:s', time() + $delay);
        $wpdb->query($wpdb->prepare(
            "UPDATE $table
             SET status = 'pending', attempts = %d, next_run_at = %s,
                 locked_at = NULL, lock_token = NULL, last_error = %s
             WHERE id = %d",
            [$attempts, $next, $error, $jobId]
        ));
    }

    /**
     * Claim one specific job (manual "run now" from admin) and execute it.
     * Unlike handle(), this ignores next_run_at and allows failed jobs.
     */
    public static function runNow(int $jobId): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ata_queue';

        $token = wp_generate_password(24, false, false);
        $now = current_time('mysql', 1);
        $claimed = $wpdb->query($wpdb->prepare(
            "UPDATE $table
             SET status = 'running', locked_at = %s, lock_token = %s
             WHERE id = %d AND status IN ('pending', 'failed')",
            [$now, $token, $jobId]
        ));
        if (!$claimed) {
            return false;
        }

        $job = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE lock_token = %s", $token),
            ARRAY_A
        );
        if (!$job) {
            return false;
        }
        $job['payload'] = json_decode((string) ($job['payload'] ?? ''), true) ?: [];

        if ($job['job_type'] === 'publish_post') {
            self::runPublish($job);
        } else {
            self::failJob((int) $job['id'], 'unknown_job_type', 'نوع کار ناشناخته است.');
        }
        return true;
    }

    public static function clearExpiredLocks(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ata_queue';
        $expired = gmdate('Y-m-d H:i:s', time() - self::LOCK_TTL);
        $wpdb->query($wpdb->prepare(
            "UPDATE $table
             SET status = 'pending', locked_at = NULL, lock_token = NULL
             WHERE status = 'running' AND locked_at < %s",
            [$expired]
        ));
    }
}
