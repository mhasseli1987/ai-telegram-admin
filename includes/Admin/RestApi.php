<?php
namespace ATA\Admin;

use ATA\Core\Container; // real namespace: Container lives in Core (not ATA\\)
use ATA\AI\AIProviderRegistry;
use ATA\Contracts\AI\AIProviderInterface;
use ATA\Contracts\Log\LoggerInterface;
use ATA\Contracts\SecretStoreInterface;
use ATA\Contracts\Telegram\TelegramProviderInterface;
use ATA\Cron\Runner;
use ATA\License\LicenseManager;
use ATA\Infrastructure\WpDb\LogRepository;
use ATA\Infrastructure\WpDb\PostRepository;
use ATA\Security\SecretStore;
use ATA\Services\ContentService;
use ATA\Settings\SettingsService;

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
        $aiNs = $base . '/ai';

        $routes = [
            [$base, '/telegram/connect', 'POST', 'telegramConnect'],
            [$base, '/telegram/disconnect', 'POST', 'telegramDisconnect'],
            [$base, '/telegram/test', 'POST', 'telegramTest'],
            [$base, '/channels', 'GET', 'listChannels'],
            // GET list + POST add share one path — WP merges same-route
            // registrations, so a method dispatcher keeps both alive.
            [$aiNs, '/providers', ['GET', 'POST'], 'providers'],
            [$aiNs, '/providers/(?P<id>\d+)', 'POST', 'aiUpdateProvider'],
            [$aiNs, '/providers/(?P<id>\d+)/test', 'POST', 'aiTestProvider'],
            [$aiNs, '/providers/(?P<id>\d+)/models', 'GET', 'aiListModels'],
            [$aiNs, '/generate', 'POST', 'aiGenerate'],
            // NOTE: register_rest_route() silently rejects an empty route
            // string, so collection endpoints must use '/name' under $base
            // (registering them under "$base/name" with route '' registered nothing).
            [$base, '/posts', 'POST', 'createPost'],
            [$base, '/posts/(?P<id>\d+)/preview', 'POST', 'previewPost'],
            [$base, '/posts/(?P<id>\d+)/approve', 'POST', 'approvePost'],
            [$base, '/posts/(?P<id>\d+)/schedule', 'POST', 'schedulePost'],
            [$base, '/posts/(?P<id>\d+)/publish', 'POST', 'publishPost'],
            [$base, '/posts/(?P<id>\d+)/cancel', 'POST', 'cancelPost'],
            [$base, '/queue', 'GET', 'listQueue'],
            [$base, '/queue/(?P<id>\d+)/run', 'POST', 'runQueueItem'],
            [$base, '/queue/(?P<id>\d+)/cancel', 'POST', 'cancelQueueItem'],
            [$base, '/queue/(?P<id>\d+)/retry', 'POST', 'retryQueueItem'],
            [$base, '/logs', ['GET', 'DELETE'], 'logs'],
            [$base, '/dashboard', 'GET', 'getDashboard'],
            [$base, '/settings', ['GET', 'POST'], 'settings'],
            [$base, '/license/activate', 'POST', 'licenseActivate'],
            [$base, '/license/deactivate', 'POST', 'licenseDeactivate'],
        ];

        foreach ($routes as [$ns, $route, $methods, $cb]) {
            register_rest_route($ns, $route, [
                'methods' => $methods,
                'callback' => [self::class, $cb],
                'permission_callback' => [self::class, 'adminPerm'],
            ]);
        }
    }

    public static function adminPerm(): bool
    {
        return current_user_can('manage_options');
    }

    /** @return array<string,mixed> */
    private static function body(\WP_REST_Request $r): array
    {
        $json = $r->get_json_params();
        return is_array($json) ? $json : (array) $r->get_params();
    }

    private static function ok(array $data = [], int $status = 200): \WP_REST_Response
    {
        $resp = rest_ensure_response(['success' => true] + $data);
        $resp->set_status($status);
        return $resp;
    }

    private static function fail(string $message, int $status = 400, string $code = 'bad_request'): \WP_REST_Response
    {
        $resp = rest_ensure_response(['success' => false, 'code' => $code, 'message' => $message]);
        $resp->set_status($status);
        return $resp;
    }

    private static function container(): Container
    {
        return Container::instance();
    }

    // ------------------------------------------------------------- Telegram

    public static function telegramConnect(\WP_REST_Request $r): \WP_REST_Response
    {
        $token = trim((string) (self::body($r)['token'] ?? ''));
        // Bot tokens look like 123456789:AAH... (digits, colon, 30+ chars).
        if (!preg_match('/^\d{5,}:[A-Za-z0-9_-]{30,}$/', $token)) {
            return self::fail('فرمت Bot Token نامعتبر است.');
        }

        /** @var SecretStore $secrets */
        $secrets = self::container()->make(SecretStoreInterface::class);
        $secrets->set('telegram_bot_token', $token);

        /** @var TelegramProviderInterface $telegram */
        $telegram = self::container()->make(TelegramProviderInterface::class);
        $result = $telegram->testConnection();

        if (empty($result['success'])) {
            $secrets->delete('telegram_bot_token'); // never keep a bad token
            return self::fail((string) ($result['message'] ?? 'اتصال ناموفق بود.'), 400, 'telegram_error');
        }

        update_option('ata_bot_username', (string) ($result['bot_username'] ?? ''), false);
        update_option('ata_bot_id', (int) ($result['bot_id'] ?? 0), false);

        self::container()->make(LoggerInterface::class)->info('Telegram bot connected', [
            'scope'         => 'telegram',
            'bot_username'  => $result['bot_username'] ?? null,
        ]);

        return self::ok(['bot_username' => $result['bot_username'] ?? null]);
    }

    public static function telegramDisconnect(\WP_REST_Request $r): \WP_REST_Response
    {
        /** @var SecretStore $secrets */
        $secrets = self::container()->make(SecretStoreInterface::class);
        $secrets->delete('telegram_bot_token');
        delete_option('ata_bot_username');
        delete_option('ata_bot_id');
        return self::ok();
    }

    public static function telegramTest(\WP_REST_Request $r): \WP_REST_Response
    {
        /** @var SecretStore $secrets */
        $secrets = self::container()->make(SecretStoreInterface::class);
        if ($secrets->get('telegram_bot_token') === null) {
            return self::fail('ابتدا Bot Token را تنظیم کنید.', 400, 'not_connected');
        }

        /** @var TelegramProviderInterface $telegram */
        $telegram = self::container()->make(TelegramProviderInterface::class);
        $result = $telegram->testConnection();
        $resp = self::ok(['result' => $result]);
        if (empty($result['success'])) {
            $resp->set_status(400);
        }
        return $resp;
    }

    public static function listChannels(\WP_REST_Request $r): \WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ata_channels';
        $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC LIMIT 200", ARRAY_A) ?: [];
        return self::ok(['channels' => $rows]);
    }

    // ------------------------------------------------------------ AI providers

    /** Decode provider row config and attach the stored API key (never returned to client). */
    private static function providerConfig(array $row): array
    {
        $config = json_decode((string) ($row['config_json'] ?? ''), true);
        $config = is_array($config) ? $config : [];
        if (!empty($row['secret_ref'])) {
            $secrets = self::container()->make(SecretStoreInterface::class);
            $apiKey = $secrets->get((string) $row['secret_ref']);
            if ($apiKey !== null && $apiKey !== '') {
                $config['apiKey'] = $apiKey;
            }
        }
        return $config;
    }

    /** Strip secrets from config before storing / returning. */
    private static function stripSecrets(array $config): array
    {
        foreach (['apiKey', 'api_key', 'token'] as $k) {
            unset($config[$k]);
        }
        return $config;
    }

    private static function validateBaseUrl(string $baseUrl): ?string
    {
        $baseUrl = trim($baseUrl);
        if ($baseUrl === '') {
            return 'base_url الزامی است.';
        }
        $scheme = strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return 'base_url باید با http یا https باشد.';
        }
        return null;
    }

    /** Method dispatcher for /ai/providers (GET list, POST add). */
    public static function providers(\WP_REST_Request $r): \WP_REST_Response
    {
        return strtoupper($r->get_method()) === 'GET'
            ? self::aiListProviders($r)
            : self::aiAddProvider($r);
    }

    /** List configured AI providers (secrets stripped). */
    public static function aiListProviders(\WP_REST_Request $r): \WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ata_providers';
        $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC LIMIT 200", ARRAY_A) ?: [];
        foreach ($rows as &$row) {
            $config = json_decode((string) ($row['config_json'] ?? ''), true);
            $row['config'] = self::stripSecrets(is_array($config) ? $config : []);
            unset($row['config_json']);
        }
        unset($row);
        return self::ok(['providers' => $rows]);
    }

    public static function aiAddProvider(\WP_REST_Request $r): \WP_REST_Response
    {
        $b = self::body($r);
        $name = sanitize_text_field((string) ($b['name'] ?? ''));
        $type = sanitize_text_field((string) ($b['type'] ?? 'ai'));
        $driver = sanitize_text_field((string) ($b['driver'] ?? 'openai_compatible'));
        $config = is_array($b['config'] ?? null) ? (array) $b['config'] : [];

        if ($name === '') {
            return self::fail('نام Provider الزامی است.');
        }
        $err = self::validateBaseUrl((string) ($config['baseUrl'] ?? ''));
        if ($err !== null) {
            return self::fail($err);
        }

        $apiKey = trim((string) ($b['secret'] ?? $config['apiKey'] ?? ''));
        $config = self::stripSecrets($config);
        $isDefault = !empty($b['is_default']) ? 1 : 0;

        global $wpdb;
        $table = $wpdb->prefix . 'ata_providers';
        if ($isDefault) {
            $wpdb->query($wpdb->prepare("UPDATE $table SET is_default = 0 WHERE type = %s", $type));
        }
        $wpdb->insert($table, [
            'type'        => $type,
            'driver'      => $driver,
            'name'        => $name,
            'is_default'  => $isDefault,
            'config_json' => wp_json_encode($config),
            'created_at'  => current_time('mysql', 1),
        ]);
        $id = (int) $wpdb->insert_id;

        if ($apiKey !== '') {
            $ref = 'ai_api_key_' . $id;
            self::container()->make(SecretStoreInterface::class)->set($ref, $apiKey);
            $wpdb->update($table, ['secret_ref' => $ref], ['id' => $id]);
        }

        self::container()->make(LoggerInterface::class)->info('AI provider added', [
            'scope' => 'ai', 'provider_id' => $id, 'driver' => $driver,
        ]);

        return self::ok(['id' => $id], 201);
    }

    public static function aiUpdateProvider(\WP_REST_Request $r): \WP_REST_Response
    {
        $id = (int) $r['id'];
        $b = self::body($r);

        global $wpdb;
        $table = $wpdb->prefix . 'ata_providers';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
        if (!$row) {
            return self::fail('Provider یافت نشد.', 404, 'not_found');
        }

        $update = [];
        if (isset($b['name'])) {
            $update['name'] = sanitize_text_field((string) $b['name']);
        }
        if (isset($b['config']) && is_array($b['config'])) {
            $config = $b['config'];
            if (isset($config['baseUrl'])) {
                $err = self::validateBaseUrl((string) $config['baseUrl']);
                if ($err !== null) {
                    return self::fail($err);
                }
            }
            $update['config_json'] = wp_json_encode(self::stripSecrets($config));
        }
        if (!empty($b['is_default'])) {
            $type = (string) $row['type'];
            $wpdb->query($wpdb->prepare("UPDATE $table SET is_default = 0 WHERE type = %s", $type));
            $update['is_default'] = 1;
        }
        if ($update) {
            $wpdb->update($table, $update, ['id' => $id]);
        }

        $secret = trim((string) ($b['secret'] ?? ''));
        if ($secret !== '') {
            $ref = (string) ($row['secret_ref'] ?: ('ai_api_key_' . $id));
            self::container()->make(SecretStoreInterface::class)->set($ref, $secret);
            if ($ref !== $row['secret_ref']) {
                $wpdb->update($table, ['secret_ref' => $ref], ['id' => $id]);
            }
        }

        return self::ok(['id' => $id]);
    }

    public static function aiTestProvider(\WP_REST_Request $r): \WP_REST_Response
    {
        $id = (int) $r['id'];
        global $wpdb;
        $table = $wpdb->prefix . 'ata_providers';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
        if (!$row) {
            return self::fail('Provider یافت نشد.', 404, 'not_found');
        }

        $provider = self::aiProviderInstance();
        $result = $provider->testConnection(self::providerConfig($row));
        $resp = self::ok(['result' => $result]);
        if (empty($result['success'])) {
            $resp->set_status(400);
        }
        return $resp;
    }

    public static function aiListModels(\WP_REST_Request $r): \WP_REST_Response
    {
        $id = (int) $r['id'];
        global $wpdb;
        $table = $wpdb->prefix . 'ata_providers';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
        if (!$row) {
            return self::fail('Provider یافت نشد.', 404, 'not_found');
        }
        $models = self::aiProviderInstance()->listModels(self::providerConfig($row));
        return self::ok(['models' => $models]);
    }

    private static function aiProviderInstance(): AIProviderInterface
    {
        $registry = self::container()->make(AIProviderRegistry::class);
        $provider = $registry->default();
        if (!$provider instanceof AIProviderInterface) {
            throw new \RuntimeException('هیچ Provider AI تنظیم نشده است.');
        }
        return $provider;
    }

    public static function aiGenerate(\WP_REST_Request $r): \WP_REST_Response
    {
        $b = self::body($r);
        $input = trim((string) ($b['input'] ?? ''));
        if ($input === '') {
            return self::fail('متن ورودی خالی است.');
        }

        $operation = sanitize_key((string) ($b['operation'] ?? 'generate'));
        $allowed = ['generate', 'rewrite', 'summarize', 'title', 'caption', 'translate'];
        if (!in_array($operation, $allowed, true)) {
            $operation = 'generate';
        }

        // Config from default provider row (falls back to provider defaults).
        $config = [];
        global $wpdb;
        $table = $wpdb->prefix . 'ata_providers';
        $def = $wpdb->get_row(
            "SELECT * FROM $table WHERE type = 'ai' AND is_default = 1 LIMIT 1",
            ARRAY_A
        );
        if ($def) {
            $config = self::providerConfig($def);
        }

        $context = [
            'operation'  => $operation,
            'input'      => $input,
            'title'      => sanitize_text_field((string) ($b['title'] ?? '')),
            'channel_id' => (int) ($b['channel_id'] ?? 0),
            'config'     => $config,
        ];

        /** @var ContentService $service */
        $service = self::container()->make(ContentService::class);
        $result = $service->generate($context);

        if (!$result->success) {
            return self::fail(
                (string) ($result->errorMessage ?? 'تولید محتوا ناموفق بود.'),
                400,
                (string) ($result->errorCode ?? 'ai_failed')
            );
        }

        // ContentService attaches metadata onto $result->context after
        // generation — read it from there (the old code read its own local
        // $context array, so stored_post_id was always 0).
        return self::ok([
            'content'         => $result->content,
            'model'           => $result->model,
            'tokens_used'     => $result->tokensUsed,
            'stored_post_id'  => (int) ($result->context['stored_post_id'] ?? 0),
        ]);
    }

    // ------------------------------------------------------------------ Posts

    private static function findPost(int $id): ?array
    {
        return self::container()->make(PostRepository::class)->find($id);
    }

    public static function createPost(\WP_REST_Request $r): \WP_REST_Response
    {
        $b = self::body($r);
        $text = trim((string) ($b['body'] ?? ''));
        if ($text === '') {
            return self::fail('متن پست الزامی است.');
        }

        /** @var PostRepository $repo */
        $repo = self::container()->make(PostRepository::class);
        $id = $repo->insert([
            'title'      => sanitize_text_field((string) ($b['title'] ?? '')),
            'body'       => $text,
            'channel_id' => (int) ($b['channel_id'] ?? 0),
            'image_id'   => !empty($b['image_id']) ? (int) $b['image_id'] : null,
            'status'     => 'draft',
        ]);

        if ($id <= 0) {
            return self::fail('ذخیره پست ناموفق بود.', 500, 'db_error');
        }
        return self::ok(['id' => $id, 'post' => $repo->find($id)], 201);
    }

    public static function previewPost(\WP_REST_Request $r): \WP_REST_Response
    {
        $post = self::findPost((int) $r['id']);
        if (!$post) {
            return self::fail('پست یافت نشد.', 404, 'not_found');
        }
        global $wpdb;
        $channel = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . $wpdb->prefix . 'ata_channels WHERE chat_id = %d',
            (int) $post['channel_id']
        ), ARRAY_A);

        return self::ok([
            'preview' => [
                'title'         => $post['title'],
                'text'          => $post['body'],
                'channel_title' => $channel['title'] ?? null,
                'channel_username' => $channel['username'] ?? null,
                'char_count'    => mb_strlen((string) $post['body']),
                'status'        => $post['status'],
            ],
        ]);
    }

    public static function approvePost(\WP_REST_Request $r): \WP_REST_Response
    {
        $id = (int) $r['id'];
        if (!self::findPost($id)) {
            return self::fail('پست یافت نشد.', 404, 'not_found');
        }
        self::container()->make(ContentService::class)->approve($id);
        return self::ok(['post' => self::findPost($id)]);
    }

    public static function schedulePost(\WP_REST_Request $r): \WP_REST_Response
    {
        $id = (int) $r['id'];
        $post = self::findPost($id);
        if (!$post) {
            return self::fail('پست یافت نشد.', 404, 'not_found');
        }
        // Telegram channel IDs are negative; only 0 means unset.
        if ((int) $post['channel_id'] === 0 || trim((string) $post['body']) === '') {
            return self::fail('متن یا کانال پست تنظیم نشده است.');
        }

        $when = (string) (self::body($r)['scheduled_at'] ?? '');
        $ts = strtotime($when);
        if ($ts === false) {
            return self::fail('زمان نامعتبر است. قالب: YYYY-MM-DD HH:MM');
        }
        if ($ts <= time()) {
            return self::fail('زمان انتشار باید در آینده باشد.');
        }
        $utc = gmdate('Y-m-d H:i:s', $ts); // queue compares in UTC.

        /** @var PostRepository $repo */
        $repo = self::container()->make(PostRepository::class);
        $repo->update($id, ['status' => 'scheduled', 'scheduled_at' => $utc]);

        global $wpdb;
        $queue = $wpdb->prefix . 'ata_queue';
        // Replace any pending publish job for this post.
        $wpdb->query($wpdb->prepare(
            "UPDATE $queue SET status = 'cancelled'
             WHERE job_type = 'publish_post' AND status = 'pending' AND payload LIKE %s",
            '%"post_id":' . $id . '}%'
        ));
        $wpdb->insert($queue, [
            'job_type'    => 'publish_post',
            'payload'     => wp_json_encode(['post_id' => $id]),
            'status'      => 'pending',
            'attempts'    => 0,
            'max_attempts'=> 5,
            'next_run_at' => $utc,
            'created_at'  => current_time('mysql', 1),
            'updated_at'  => current_time('mysql', 1),
        ]);

        Runner::ensureScheduled();

        return self::ok(['post' => $repo->find($id), 'queue_id' => (int) $wpdb->insert_id]);
    }

    public static function publishPost(\WP_REST_Request $r): \WP_REST_Response
    {
        $id = (int) $r['id'];
        if (!self::findPost($id)) {
            return self::fail('پست یافت نشد.', 404, 'not_found');
        }
        $result = self::container()->make(ContentService::class)->publishNow($id);
        if (empty($result['success'])) {
            return self::fail((string) ($result['error'] ?? 'انتشار ناموفق بود.'), 400, 'publish_failed');
        }
        return self::ok(['result' => $result, 'post' => self::findPost($id)]);
    }

    public static function cancelPost(\WP_REST_Request $r): \WP_REST_Response
    {
        $id = (int) $r['id'];
        $post = self::findPost($id);
        if (!$post) {
            return self::fail('پست یافت نشد.', 404, 'not_found');
        }
        if (in_array($post['status'], ['published', 'cancelled'], true)) {
            return self::fail('این پست در وضعیت قابل لغو نیست.');
        }

        self::container()->make(PostRepository::class)->update($id, ['status' => 'cancelled']);

        global $wpdb;
        $queue = $wpdb->prefix . 'ata_queue';
        $wpdb->query($wpdb->prepare(
            "UPDATE $queue SET status = 'cancelled', locked_at = NULL, lock_token = NULL
             WHERE job_type = 'publish_post' AND status IN ('pending', 'running') AND payload LIKE %s",
            '%"post_id":' . $id . '}%'
        ));

        return self::ok(['post' => self::findPost($id)]);
    }

    // ----------------------------------------------------------------- Queue

    public static function listQueue(\WP_REST_Request $r): \WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ata_queue';
        $status = sanitize_key((string) $r->get_param('status'));
        if ($status !== '' && $status !== 'all') {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE status = %s ORDER BY next_run_at ASC LIMIT 100",
                $status
            ), ARRAY_A) ?: [];
        } else {
            $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC LIMIT 100", ARRAY_A) ?: [];
        }
        foreach ($rows as &$row) {
            $row['payload'] = json_decode((string) $row['payload'], true) ?: [];
        }
        unset($row);
        return self::ok(['jobs' => $rows]);
    }

    public static function runQueueItem(\WP_REST_Request $r): \WP_REST_Response
    {
        $id = (int) $r['id'];
        $ran = Runner::runNow($id);
        if (!$ran) {
            return self::fail('اجرای کار ممکن نشد (در حال اجرا، لغو شده یا انجام‌شده).', 409, 'cannot_run');
        }
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . $wpdb->prefix . 'ata_queue WHERE id = %d',
            $id
        ), ARRAY_A);
        if ($row) {
            $row['payload'] = json_decode((string) $row['payload'], true) ?: [];
        }
        return self::ok(['job' => $row]);
    }

    public static function cancelQueueItem(\WP_REST_Request $r): \WP_REST_Response
    {
        $id = (int) $r['id'];
        global $wpdb;
        $table = $wpdb->prefix . 'ata_queue';
        $changed = $wpdb->query($wpdb->prepare(
            "UPDATE $table SET status = 'cancelled', locked_at = NULL, lock_token = NULL
             WHERE id = %d AND status IN ('pending', 'failed')",
            $id
        ));
        if (!$changed) {
            return self::fail('این کار در وضعیت قابل لغو نیست.', 409, 'cannot_cancel');
        }
        return self::ok(['id' => $id]);
    }

    public static function retryQueueItem(\WP_REST_Request $r): \WP_REST_Response
    {
        $id = (int) $r['id'];
        global $wpdb;
        $table = $wpdb->prefix . 'ata_queue';
        $now = current_time('mysql', 1);
        $changed = $wpdb->query($wpdb->prepare(
            "UPDATE $table SET status = 'pending', next_run_at = %s, locked_at = NULL, lock_token = NULL
             WHERE id = %d AND status IN ('failed', 'cancelled')",
            [$now, $id]
        ));
        if (!$changed) {
            return self::fail('فقط کارهای ناموفق یا لغوشده قابل تلاش مجدد هستند.', 409, 'cannot_retry');
        }
        Runner::ensureScheduled();
        return self::ok(['id' => $id]);
    }

    // ----------------------------------------------------------------- Logs

    public static function listLogs(\WP_REST_Request $r): \WP_REST_Response
    {
        /** @var LogRepository $repo */
        $repo = self::container()->make(LogRepository::class);
        $filters = [];
        if ($r->get_param('scope')) {
            $filters['scope'] = sanitize_key((string) $r->get_param('scope'));
        }
        if ($r->get_param('level')) {
            $filters['level'] = sanitize_key((string) $r->get_param('level'));
        }
        $page = max(1, (int) $r->get_param('page'));
        $perPage = min(200, max(1, (int) ($r->get_param('per_page') ?: 50)));

        $rows = $repo->all($perPage, ($page - 1) * $perPage, $filters);
        $total = $repo->count($filters);
        foreach ($rows as &$row) {
            $row['context'] = json_decode((string) $row['context'], true) ?: [];
        }
        unset($row);

        return self::ok(['logs' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
    }

    /** Method dispatcher for /logs (GET list, DELETE clear). */
    public static function logs(\WP_REST_Request $r): \WP_REST_Response
    {
        return strtoupper($r->get_method()) === 'DELETE'
            ? self::clearLogs($r)
            : self::listLogs($r);
    }

    public static function clearLogs(\WP_REST_Request $r): \WP_REST_Response
    {
        self::container()->make(LogRepository::class)->deleteAll();
        return self::ok();
    }

    // ------------------------------------------------------------- Dashboard

    public static function getDashboard(\WP_REST_Request $r): \WP_REST_Response
    {
        global $wpdb;
        $counts = [
            'posts'   => self::container()->make(PostRepository::class)->countByStatus(),
            'queue'   => [],
            'channels'=> (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $wpdb->prefix . 'ata_channels'),
            'logs'    => self::container()->make(LogRepository::class)->count(),
        ];

        $qRows = $wpdb->get_results(
            'SELECT status, COUNT(*) AS c FROM ' . $wpdb->prefix . 'ata_queue GROUP BY status',
            ARRAY_A
        ) ?: [];
        foreach ($qRows as $qr) {
            $counts['queue'][(string) $qr['status']] = (int) $qr['c'];
        }

        $recent = self::container()->make(LogRepository::class)->all(5);
        foreach ($recent as &$row) {
            $row['context'] = json_decode((string) $row['context'], true) ?: [];
        }
        unset($row);

        return self::ok(['counts' => $counts, 'recent_logs' => $recent]);
    }

    // -------------------------------------------------------------- Settings

    private static function isSecretSetting(string $key): bool
    {
        return in_array($key, ['ata_license_key'], true);
    }

    public static function settings(\WP_REST_Request $r): \WP_REST_Response
    {
        /** @var SettingsService $service */
        $service = self::container()->make(SettingsService::class);

        if (strtoupper($r->get_method()) === 'POST') {
            $b = self::body($r);
            $defs = $service->definitions();
            $saved = [];
            foreach ($b as $key => $value) {
                $key = (string) $key;
                if (!isset($defs[$key])) {
                    return self::fail("تنظیم ناشناخته: {$key}", 400, 'unknown_setting');
                }
                if (self::isSecretSetting($key)) {
                    self::container()->make(SecretStoreInterface::class)
                        ->set('setting_' . $key, (string) $value);
                    $saved[] = $key;
                    continue;
                }
                $service->set($key, $value);
                $saved[] = $key;
            }
            return self::ok(['saved' => $saved]);
        }

        $values = $service->all();
        foreach (array_keys($values) as $key) {
            if (self::isSecretSetting((string) $key)) {
                $stored = self::container()->make(SecretStoreInterface::class)
                    ->get('setting_' . $key);
                $values[$key] = $stored !== null && $stored !== ''
                    ? '••••' . substr($stored, -4)
                    : '';
            }
        }
        return self::ok(['values' => $values]);
    }

    // ---------------------------------------------------------------- License

    public static function licenseActivate(\WP_REST_Request $r): \WP_REST_Response
    {
        $key = trim((string) (self::body($r)['key'] ?? ''));
        $result = LicenseManager::activate($key);

        if (!$result['success']) {
            return self::fail($result['message'], 400, 'license_invalid');
        }
        return self::ok(LicenseManager::status());
    }

    public static function licenseDeactivate(\WP_REST_Request $r): \WP_REST_Response
    {
        $result = LicenseManager::deactivate();

        if (!$result['success']) {
            return self::fail($result['message'], 400, 'license_error');
        }
        return self::ok(['status' => LicenseManager::status()]);
    }
}
