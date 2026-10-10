<?php
namespace ATA\Cron;

defined('ABSPATH') || exit;

/**
 * Scheduler adapter: prefers Action Scheduler when available, falls back to WP-Cron.
 * D-6/D-7: Scheduler = "when", Queue = "execution with retry".
 */
class SchedulerAdapter
{
    private const HOOK = Runner::HOOK;

    public static function register(): void
    {
        add_action(self::HOOK, [Runner::class, 'handle']);
        add_action('init', [self::class, 'maybeSwitchToActionScheduler']);
    }

    /** Switch the recurring interval from WP-Cron to AS when AS is present. */
    public static function maybeSwitchToActionScheduler(): void
    {
        if (!function_exists('as_schedule_recurring_event')) {
            return;
        }

        $next = wp_next_scheduled(self::HOOK);
        if ($next) {
            wp_unschedule_event($next, self::HOOK);
        }

        as_schedule_recurring_event(
            time() + MINUTE_IN_SECONDS,
            'ata_minute',
            self::HOOK
        );
    }

    /** Queue a one-shot job via AS when available, else WP-Cron. */
    public static function scheduleOneShot(string $hook, int $delaySeconds = 0, array $args = []): void
    {
        if (function_exists('as_schedule_single_action') && $delaySeconds > 0) {
            as_schedule_single_action(time() + $delaySeconds, $hook, $args);
            return;
        }

        if ($delaySeconds <= 0) {
            wp_schedule_single_event(time(), $hook, $args);
        } else {
            wp_schedule_single_event(time() + $delaySeconds, $hook, $args);
        }
    }
}