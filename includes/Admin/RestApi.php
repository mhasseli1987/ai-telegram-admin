<?php
namespace ATA\Admin;

use ATA\Core\Plugin;
use ATA\Services\ContentService;
use ATA\Contracts\Log\LoggerInterface;

defined('ABSPATH') || exit;

class RestApi
{
    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function registerRoutes(): void
    {
        $base = 'ata/v1';
        $ns = $base . '/telegram';
        $aiNs = $base . '/ai';
        $postNs = $base . '/posts';
        $queueNs = $base . '/queue';
        $settingsNs = $base . '/settings';
        $licenseNs = $base . '/license';
        $logsNs = $base . '/logs';

        // Telegram
        register_rest_route($base, '/telegram/connect', [
            'methods' => 'POST',
            'callback' => [self::class, 'telegramConnect'],
            'permission_callback' => [self::class, 'adminPerm'],
        ]);
        register_rest_route($base, '/telegram/disconnect', [
            'methods' => 'POST',
            'callback' => [self::class, 'telegramDisconnect'],
            'permission_callback' => [self::class, 'adminPerm'],
        ]);
        register_rest_route($base, '/telegram/test', [
            'methods' => 'POST',
            'callback' => [self::class, 'telegramTest'],
            'permission_callback' => [self::class, 'adminPerm'],
        ]);

        // Channels
        register_rest_route($base, '/channels', [
            'methods' => 'GET',
            'callback' => [self::class, 'listChannels'],
            'permission_callback' => [self::class, 'adminPerm'],
        ]);

        // AI Providers
        register_rest_route($aiNs, '/providers', [
            'methods' => 'POST',
            'callback' => [self::class, 'aiAddProvider'],
            'permission_callback' => [self::class, 'adminPerm'],
        ]);
        register_rest_route($aiNs, '/providers/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [self::class, 'aiUpdateProvider'],
            'permission_callback' => [self::class, 'adminPerm'],
        ]);
        register_rest_route($aiNs, '/providers/(?P<id>\d+)/test', [
            'methods' => 'POST',
            'callback' => [self::class, 'aiTestProvider'],
            'permission_callback' => [self::class, 'adminPerm'],
        ]);
        register_rest_route($aiNs, '/providers/(?P<id>\d+)/models', [
            'methods' => 'GET',
            'callback' => [self::class, 'aiListModels'],
            'permission_callback' => [self::class, 'adminPerm'],
        ]);
        register_rest_route($aiNs, '/generate', [
            'methods' => 'POST',
            'callback' => [self::class, 'aiGenerate'],
            'permission_callback' => [self::class, 'adminPerm'],
        ]);

        // Posts
        register_rest_route($postNs, '', [
            'methods' => 'POST',
            'callback' => [self::class, 'createPost'],
            'permission_callback' => [self::class, 'adminPerm'],
        ]);
        register_rest_route($postNs, '/(?P<id>\d+)/preview', [
            'methods' => 'POST',
            'callback' => [self::class, 'previewPost'],
            'permission_callback' => [self::class, 'adminPerm'],
        ]);
        register_rest_route($postNs, '/(?P<id>\d+)/approve', [
            'methods' => 'POST',
            'callback' => [self::class, 'approvePost'],
            'permission_callback' => [self::class, 'adminPerm'],
        ]);
        register_rest_route($postNs, '/(?P<id>\d+)/schedule', [
            'methods' => 'POST',
            'callback' => [self::class, 'schedulePost'],
            'permission_callback' => [self::class, 'adminPerm'],
        ]);
        register_rest_route($postNs, '/(?P<id>\d+)/publish', [
            'methods' => 'POST',
            'callback' => [self::class, 'publishPost'],
            'permission_callback' => [self::class, 'adminPerm'],
        ]);
        register_rest_route($postNs, '/(?P<id>\d+)/cancel', [
            'methods' => 'POST',
            'callback' => [self::class, 'cancelPost'],
            'permission_callback' => [self::class, 'adminPerm'],
        ]);

        // Queue
        register_rest_route($queueNs, '', [
            'methods' => 'GET',
            'callback' => [self::class, 'listQueue'],
            'permission_callback' => [self::class, 'adminPerm'],
        ]);
        register_rest_route($queueNs, '/(?P<id>\d+)/run', [
            'methods' => 'POST',
            'callback' => [self::class, 'runQueueItem'],
            'permission_callback' => [self::class, 'adminPerm'],
        ]);
        register_rest_route($queueNs, '/(?P<id>\d+)/cancel', [
            'methods' => 'POST',
            'callback' => [self::class, 'cancelQueueItem'],
            'permission_callback' => [self::class, 'adminPerm'],
        ]);
        register_rest_route($queueNs, '/(?P<id>\d+)/retry', [
            'methods' => 'POST',
            'callback' => [self::class, 'retryQueueItem'],
            'permission_callback' => [self::class, 'adminPerm'],
        ]);

        // Logs
        register_rest_route($logsNs, '', [
            'methods' => 'GET',
            'callback' => [self::class, 'listLogs'],
            'permission_callback' => [self::class, 'adminPerm'],
        ]);
        register_rest_route($logsNs, '', [
            'methods' => 'DELETE',
            'callback' => [self::class, 'clearLogs'],
            'permission_callback' => [self::class, 'adminPerm'],
        ]);

        // Dashboard
        register_rest_route($base, '/dashboard', [
            'methods' => 'GET',
            'callback' => [self::class, 'getDashboard'],
            'permission_callback' => [self::class, 'adminPerm'],
        ]);

        // Settings
        register_rest_route($settingsNs, '', [
            'methods' => ['GET', 'POST'],
            'callback' => [self::class, 'settings'],
            'permission_callback' => [self::class, 'adminPerm'],
        ]);

        // License
        register_rest_route($licenseNs, '/activate', [
            'methods' => 'POST',
            'callback' => [self::class, 'licenseActivate'],
            'permission_callback' => [self::class, 'adminPerm'],
        ]);
        register_rest_route($licenseNs, '/deactivate', [
            'methods' => 'POST',
            'callback' => [self::class, 'licenseDeactivate'],
            'permission_callback' => [self::class, 'adminPerm'],
        ]);
    }

    public static function adminPerm(): bool
    {
        return current_user_can('manage_options');
    }

    // Handler stubs — implementation in later files.
    public static function telegramConnect(\WP_REST_Request $r): \WP_REST_Response { return rest_ensure_response(['stub' => true, 'action' => 'telegram_connect']); }
    public static function telegramDisconnect(\WP_REST_Request $r): \WP_REST_Response { return rest_ensure_response(['stub' => true, 'action' => 'telegram_disconnect']); }
    public static function telegramTest(\WP_REST_Request $r): \WP_REST_Response { return rest_ensure_response(['stub' => true, 'action' => 'telegram_test']); }
    public static function listChannels(\WP_REST_Request $r): \WP_REST_Response { return rest_ensure_response(['stub' => true, 'action' => 'list_channels']); }
    public static function aiAddProvider(\WP_REST_Request $r): \WP_REST_Response { return rest_ensure_response(['stub' => true, 'action' => 'ai_add_provider']); }
    public static function aiUpdateProvider(\WP_REST_Request $r): \WP_REST_Response { return rest_ensure_response(['stub' => true, 'action' => 'ai_update_provider']); }
    public static function aiTestProvider(\WP_REST_Request $r): \WP_REST_Response { return rest_ensure_response(['stub' => true, 'action' => 'ai_test_provider']); }
    public static function aiListModels(\WP_REST_Request $r): \WP_REST_Response { return rest_ensure_response(['stub' => true, 'action' => 'ai_list_models']); }
    public static function aiGenerate(\WP_REST_Request $r): \WP_REST_Response { return rest_ensure_response(['stub' => true, 'action' => 'ai_generate']); }
    public static function createPost(\WP_REST_Request $r): \WP_REST_Response { return rest_ensure_response(['stub' => true, 'action' => 'create_post']); }
    public static function previewPost(\WP_REST_Request $r): \WP_REST_Response { return rest_ensure_response(['stub' => true, 'action' => 'preview_post']); }
    public static function approvePost(\WP_REST_Request $r): \WP_REST_Response { return rest_ensure_response(['stub' => true, 'action' => 'approve_post']); }
    public static function schedulePost(\WP_REST_Request $r): \WP_REST_Response { return rest_ensure_response(['stub' => true, 'action' => 'schedule_post']); }
    public static function publishPost(\WP_REST_Request $r): \WP_REST_Response { return rest_ensure_response(['stub' => true, 'action' => 'publish_post']); }
    public static function cancelPost(\WP_REST_Request $r): \WP_REST_Response { return rest_ensure_response(['stub' => true, 'action' => 'cancel_post']); }
    public static function listQueue(\WP_REST_Request $r): \WP_REST_Response { return rest_ensure_response(['stub' => true, 'action' => 'list_queue']); }
    public static function runQueueItem(\WP_REST_Request $r): \WP_REST_Response { return rest_ensure_response(['stub' => true, 'action' => 'run_queue']); }
    public static function cancelQueueItem(\WP_REST_Request $r): \WP_REST_Response { return rest_ensure_response(['stub' => true, 'action' => 'cancel_queue']); }
    public static function retryQueueItem(\WP_REST_Request $r): \WP_REST_Response { return rest_ensure_response(['stub' => true, 'action' => 'retry_queue']); }
    public static function listLogs(\WP_REST_Request $r): \WP_REST_Response { return rest_ensure_response(['stub' => true, 'action' => 'list_logs']); }
    public static function clearLogs(\WP_REST_Request $r): \WP_REST_Response { return rest_ensure_response(['stub' => true, 'action' => 'clear_logs']); }
    public static function getDashboard(\WP_REST_Request $r): \WP_REST_Response { return rest_ensure_response(['stub' => true, 'action' => 'dashboard']); }
    public static function settings(\WP_REST_Request $r): \WP_REST_Response { return rest_ensure_response(['stub' => true, 'action' => 'settings']); }
    public static function licenseActivate(\WP_REST_Request $r): \WP_REST_Response { return rest_ensure_response(['stub' => true, 'action' => 'license_activate']); }
    public static function licenseDeactivate(\WP_REST_Request $r): \WP_REST_Response { return rest_ensure_response(['stub' => true, 'action' => 'license_deactivate']); }
}