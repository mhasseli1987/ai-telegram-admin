<?php
/**
 * Integration Test Bootstrap
 * Sets up WordPress test environment for ATA plugin integration tests.
 *
 * Order matters:
 *  1. Load project composer autoloader (so PHPUnit\Runner\Version resolves
 *     and the WP bootstrap version check passes; also autoloads ATA\* classes).
 *  2. Define WP test constants.
 *  3. Load the WordPress test-suite bootstrap (installs WP, defines
 *     WP_UnitTestCase and all test helpers).
 *  4. Define test doubles (MockHttpTransport) and helpers.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

// 1. Project autoloader first. (Integration -> Tests -> includes -> project root)
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

// 2. WP test constants.
if (!defined('WP_TEST_DIR')) {
    define('WP_TEST_DIR', 'C:/Users/win11/AppData/Local/Temp/wp-tests/tests/phpunit');
}
if (!defined('WP_TESTS_PHPUNIT_POLYFILLS_PATH')) {
    define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', 'C:/Users/win11/AppData/Local/Temp/wp-tests/vendor/yoast/phpunit-polyfills');
}
if (!defined('WP_TESTS_CONFIG_FILE_PATH')) {
    define('WP_TESTS_CONFIG_FILE_PATH', 'C:/Users/win11/AppData/Local/Temp/wp-tests/wp-tests-config.php');
}
// WP_TESTS_DOMAIN / EMAIL / TITLE come from wp-tests-config.php — do not redefine.

// 3. WordPress test bootstrap (installs WP into wordpress_test DB, defines WP_UnitTestCase).
require_once WP_TEST_DIR . '/includes/bootstrap.php';

// Load the plugin (defines ABSPATH-based plugin constants and its autoloader).
require_once dirname(__DIR__, 3) . '/ata-telegram-ai-admin.php';

// Create the plugin's custom tables in the test database.
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
(new \ATA\Infrastructure\WpDb\Installer())->install();

/**
 * Mock HTTP transport for testing external API calls.
 * Records requests and serves canned responses.
 */
class MockHttpTransport implements \ATA\Contracts\HttpClientInterface
{
    /** @var array<int, array{method:string,url:string,body:string,headers:array,timeout:int}> */
    private array $requests = [];

    /** @var array<string, array{status:int, body:string}> */
    private array $responses = [];

    /** @var array<string, string> */
    private array $errors = [];

    /** @var array<string, int> */
    private array $callCounts = [];

    public function __construct()
    {
        $this->responses['default'] = ['status' => 200, 'body' => '{"success":true}'];
    }

    public function request(string $url, array $opts = []): array
    {
        $method = strtoupper($opts['method'] ?? 'GET');
        $body   = $opts['body'] ?? '';
        if (is_array($body)) {
            // Callers may pass structured payloads (LicenseManager does).
            $body = (string) wp_json_encode($body, JSON_UNESCAPED_UNICODE);
        }
        $this->recordRequest($method, $url, (string) $body, $opts);

        $response = $this->getResponse($method, $url);
        return [
            'status'  => $response['status'],
            'headers' => ['Content-Type: application/json'],
            'body'    => $response['body'],
        ];
    }

    public function setResponse(string $method, string $urlPattern, array $response): void
    {
        $this->responses[$method . ':' . $urlPattern] = [
            'status' => (int) ($response['status'] ?? $response['code'] ?? 200),
            'body'   => is_string($response['body'] ?? null) ? $response['body'] : json_encode($response['body'] ?? ''),
        ];
    }

    public function getError(string $method, string $urlPattern): ?array
    {
        return $this->errors[$method . ':' . $urlPattern] ?? null;
    }

    /** Convenience POST helper (delegates to request()). */
    public function post(string $url, array $opts = []): array
    {
        return $this->request($url, ['method' => 'POST'] + $opts);
    }

    /** Convenience GET helper (delegates to request()). */
    public function get(string $url, array $opts = []): array
    {
        return $this->request($url, ['method' => 'GET'] + $opts);
    }

    public function setError(string $method, string $urlPattern, string $message): void
    {
        $this->errors[$method . ':' . $urlPattern] = $message;
    }

    /** @return array<int, array{method:string,url:string,body:string}> */
    public function getRequests(): array
    {
        return $this->requests;
    }

    public function getCallCount(string $method, string $urlPattern): int
    {
        return $this->callCounts[$method . ':' . $urlPattern] ?? 0;
    }

    public function reset(): void
    {
        $this->requests   = [];
        $this->callCounts = [];
    }

    private function recordRequest(string $method, string $url, string $body, array $opts): void
    {
        $this->requests[] = [
            'method'  => $method,
            'url'     => $url,
            'body'    => $body,
            'headers' => $opts['headers'] ?? [],
            'timeout' => $opts['timeout'] ?? 30,
        ];
        $key = $method . ':' . $url;
        $this->callCounts[$key] = ($this->callCounts[$key] ?? 0) + 1;
    }

    private function getResponse(string $method, string $url): array
    {
        if (isset($this->responses[$method . ':' . $url])) {
            return $this->responses[$method . ':' . $url];
        }
        // Wildcard pattern matching ("POST:*bot*/sendMessage").
        $needle = $method . ':';
        foreach ($this->responses as $pattern => $response) {
            if ($pattern === 'default' || !str_starts_with($pattern, $needle)) {
                continue;
            }
            $glob = substr($pattern, strlen($needle));
            $regex = '/^' . str_replace('\*', '.*', preg_quote($glob, '/')) . '$/';
            if (preg_match($regex, $url)) {
                return $response;
            }
        }
        return $this->responses['default'];
    }
}

/**
 * Shared helpers for integration tests.
 */
trait IntegrationTestHelper
{
    protected MockHttpTransport $mockHttp;

    protected \ATA\Core\Container $container;

    protected \wpdb $wpdb;

    /** @var int ID of a user with manage_options. */
    protected int $adminUserId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $this->wpdb = $wpdb;

        $this->adminUserId = self::factory()->user->create(['role' => 'administrator']);

        $this->mockHttp = new MockHttpTransport();

        // Rebuild the container fresh each test, then override the HTTP
        // transport with the mock. Factories are lazy, so every service
        // resolved from here on receives the mock transport.
        $this->container = \ATA\Core\Container::instance();
        $this->container->reset();
        \ATA\Core\Plugin::instance()->registerContainer();
        $this->container->bind(\ATA\Contracts\HttpClientInterface::class, fn() => $this->mockHttp);

        $this->cleanDatabase();
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
        $this->mockHttp->reset();
        parent::tearDown();
    }

    private function cleanDatabase(): void
    {
        global $wpdb;
        // ata_providers (NOT ata_ai_providers — matches Installer schema).
        foreach (['ata_posts', 'ata_channels', 'ata_providers', 'ata_queue', 'ata_logs'] as $table) {
            $wpdb->query("DELETE FROM {$wpdb->prefix}{$table}");
        }
        foreach (['ata_bot_username', 'ata_bot_id', 'ata_license', 'ata_cpt_migrated'] as $opt) {
            delete_option($opt);
        }
        foreach (['telegram_bot_token', 'ai_api_key'] as $key) {
            (new \ATA\Security\SecretStore())->delete($key);
        }
    }

    protected function createTestPost(array $overrides = []): int
    {
        $defaults = [
            'title'      => 'Test Post',
            'body'       => 'Test content',
            'channel_id' => 1,
            'status'     => 'draft',
        ];
        return (new \ATA\Infrastructure\WpDb\PostRepository())->insert(array_merge($defaults, $overrides));
    }

    protected function createTestChannel(array $overrides = []): int
    {
        global $wpdb;
        // Matches Installer schema: chat_id, username, title, chat_type, status, created_at.
        $defaults = [
            'chat_id'    => -1001234567890,
            'title'      => 'Test Channel',
            'username'   => 'testchannel',
            'chat_type'  => 'channel',
            'status'     => 'connected',
            'created_at' => current_time('mysql', 1),
        ];
        $wpdb->insert($wpdb->prefix . 'ata_channels', array_merge($defaults, $overrides));
        return (int) $wpdb->insert_id;
    }

    /** Last recorded request whose URL contains $urlFragment, or null. */
    protected function getLastRequest(string $method, string $urlFragment): ?array
    {
        $matches = array_filter(
            $this->mockHttp->getRequests(),
            static fn(array $r): bool => $r['method'] === $method && str_contains($r['url'], $urlFragment)
        );
        return $matches === [] ? null : array_values($matches)[count($matches) - 1];
    }

    /** Stub a Telegram Bot API response for $method (registered for GET and POST). */
    protected function mockTelegramResponse(string $method, array $result, int $status = 200, bool $ok = true): void
    {
        $body = json_encode(['ok' => $ok, 'result' => $result]);
        $this->mockHttp->setResponse('POST', "*api.telegram.org*/{$method}", ['code' => $status, 'body' => $body]);
        $this->mockHttp->setResponse('GET', "*api.telegram.org*/{$method}", ['code' => $status, 'body' => $body]);
    }

    /** Stub an OpenAI-compatible chat/completions response. */
    protected function mockAiResponse(string $content): void
    {
        $this->mockHttp->setResponse('POST', '*chat/completions*', [
            'code' => 200,
            'body' => json_encode([
                'choices' => [['message' => ['content' => $content]]],
            ]),
        ]);
    }

    /** Insert a row into ata_queue. @return int job ID */
    protected function createQueueItem(array $overrides = []): int
    {
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

    protected function restRequest(string $method, string $route, array $body = [], int $userId = 1): array
    {
        wp_set_current_user($userId);

        $request = new WP_REST_Request($method, $route);
        if ($body) {
            $request->set_body_params($body);
        }
        $response = rest_do_request($request);
        $data     = $response->get_data();

        wp_set_current_user(0);

        return is_array($data) ? $data : ['raw' => $data];
    }
}

/**
 * Base class for integration tests.
 */
abstract class IntegrationTestCase extends WP_UnitTestCase
{
    use IntegrationTestHelper;
}
