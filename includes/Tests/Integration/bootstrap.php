<?php
/**
 * Integration Test Bootstrap
 * Sets up WordPress test environment for ATA plugin integration tests.
 * This file is loaded by PHPUnit before running integration tests.
 */

if (!defined('WP_TEST_DIR')) {
    // Default to standard WordPress test directory
    define('WP_TEST_DIR', sys_get_temp_dir() . '/wordpress-tests-lib');
}

if (!defined('WP_TESTS_DOMAIN')) {
    define('WP_TESTS_DOMAIN', 'ata-test.local');
}

if (!defined('WP_TESTS_EMAIL')) {
    define('WP_TESTS_EMAIL', 'test@ata-test.local');
}

if (!defined('WP_TESTS_TITLE')) {
    define('WP_TESTS_TITLE', 'ATA Test Site');
}

if (!defined('WP_TESTS_PHPUNIT')) {
    define('WP_TESTS_PHPUNIT', true);
}

// Load WordPress test utilities
require_once WP_TEST_DIR . '/includes/functions.php';

/**
 * Mock HTTP transport for testing external API calls.
 * Records requests and allows canned responses.
 */
class MockHttpTransport implements \ATA\Contracts\HttpClientInterface
{
    /** @var array<int, array{method:string, url:string, body:string, headers:array, timeout:int}> */
    private array $requests = [];

    /** @var array<string, mixed> */
    private array $responses = [];

    /** @var array<string, int> */
    private array $callCounts = [];

    public function __construct()
    {
        // Default responses
        $this->responses['default'] = [
            'code'    => 200,
            'body'    => json_encode(['success' => true, 'data' => []]),
            'headers' => ['Content-Type: application/json'],
        ];
    }

    public function get(string $url, array $args = []): string
    {
        $this->recordRequest('GET', $url, '', $args);
        return $this->getResponse('GET', $url);
    }

    public function post(string $url, array $args = []): string
    {
        $body = $args['body'] ?? '';
        $this->recordRequest('POST', $url, $body, $args);
        return $this->getResponse('POST', $url);
    }

    public function put(string $url, array $args = []): string
    {
        $body = $args['body'] ?? '';
        $this->recordRequest('PUT', $url, $body, $args);
        return $this->getResponse('PUT', $url);
    }

    public function delete(string $url, array $args = []): string
    {
        $this->recordRequest('DELETE', $url, '', $args);
        return $this->getResponse('DELETE', $url);
    }

    /**
     * Set a canned response for a specific URL pattern.
     */
    public function setResponse(string $method, string $urlPattern, array $response): void
    {
        $key = $method . ':' . $urlPattern;
        $this->responses[$key] = $response;
    }

    /**
     * Get recorded requests for verification.
     */
    public function getRequests(): array
    {
        return $this->requests;
    }

    /**
     * Get call count for a specific URL pattern.
     */
    public function getCallCount(string $method, string $urlPattern): int
    {
        $key = $method . ':' . $urlPattern;
        return $this->callCounts[$key] ?? 0;
    }

    /**
     * Reset all recorded data.
     */
    public function reset(): void
    {
        $this->requests = [];
        $this->callCounts = [];
    }

    private function recordRequest(string $method, string $url, string $body, array $args): void
    {
        $this->requests[] = [
            'method'   => $method,
            'url'      => $url,
            'body'     => $body,
            'headers'  => $args['headers'] ?? [],
            'timeout'  => $args['timeout'] ?? 30,
        ];
        $key = $method . ':' . $url;
        $this->callCounts[$key] = ($this->callCounts[$key] ?? 0) + 1;
    }

    private function getResponse(string $method, string $url): string
    {
        $key = $method . ':' . $url;
        if (isset($this->responses[$key])) {
            return $this->buildResponse($this->responses[$key]);
        }

        // Try pattern matching
        foreach ($this->responses as $pattern => $response) {
            if ($pattern === 'default') continue;
            $regexPattern = str_replace('*', '.*', preg_quote($pattern, '/'));
            if (preg_match('/^' . $regexPattern . '$/', $method . ':' . $url)) {
                return $this->buildResponse($response);
            }
        }

        return $this->buildResponse($this->responses['default']);
    }

    private function buildResponse(array $response): string
    {
        // WP_Http compatible response format
        $body = $response['body'] ?? '';
        return $body;
    }
}

/**
 * Test helper trait for integration tests.
 */
trait IntegrationTestHelper
{
    /** @var MockHttpTransport */
    protected MockHttpTransport $mockHttp;

    /** @var \ATA\Core\Container */
    protected \ATA\Core\Container $container;

    /** @var \wpdb */
    protected \wpdb $wpdb;

    protected function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $this->wpdb = $wpdb;

        // Reset mock HTTP transport
        $this->mockHttp = new MockHttpTransport();

        // Get container instance
        $this->container = \ATA\Core\Container::instance();

        // Replace HTTP transport with mock
        $this->container->bind(
            \ATA\Contracts\HttpClientInterface::class,
            fn() => $this->mockHttp
        );

        // Clean up database
        $this->cleanDatabase();
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
        $this->mockHttp->reset();
        parent::tearDown();
    }

    /**
     * Clean up test tables.
     */
    private function cleanDatabase(): void
    {
        $tables = [
            'ata_posts',
            'ata_channels',
            'ata_queue',
            'ata_logs',
            'ata_ai_providers',
            'ata_license',
        ];

        foreach ($tables as $table) {
            $this->wpdb->query("DELETE FROM {$this->wpdb->prefix}{$table}");
        }

        // Clean options
        $options = [
            'ata_bot_username',
            'ata_bot_id',
            'ata_license',
            'ata_cpt_migrated',
        ];
        foreach ($options as $opt) {
            delete_option($opt);
        }
    }

    /**
     * Assert that a REST request was made with expected parameters.
     */
    protected function assertRequestMade(string $method, string $urlPattern, int $expectedCount = 1): void
    {
        $count = $this->mockHttp->getCallCount($method, $urlPattern);
        $this->assertEquals($expectedCount, $count, "Expected $expectedCount requests to $method $urlPattern, got $count");
    }

    /**
     * Get the last request matching a pattern.
     */
    protected function getLastRequest(string $method, string $urlPattern): ?array
    {
        $requests = array_filter($this->mockHttp->getRequests(), function ($r) use ($method, $urlPattern) {
            return $r['method'] === $method && str_contains($r['url'], $urlPattern);
        });
        return end($requests) ?: null;
    }

    /**
     * Create a test post in ata_posts table.
     */
    protected function createTestPost(array $overrides = []): int
    {
        $defaults = [
            'title'       => 'Test Post',
            'body'        => 'Test content',
            'channel_id'  => 1,
            'status'      => 'draft',
            'scheduled_at' => null,
            'published_at' => null,
            'attempts'    => 0,
            'last_error_code' => null,
        ];
        $repo = new \ATA\Infrastructure\WpDb\PostRepository();
        return $repo->insert(array_merge($defaults, $overrides));
    }

    /**
     * Create a test channel.
     */
    protected function createTestChannel(array $overrides = []): int
    {
        $defaults = [
            'chat_id'       => '-1001234567890',
            'title'         => 'Test Channel',
            'username'      => 'testchannel',
            'is_admin'      => 1,
            'last_synced'   => current_time('mysql', 1),
        ];
        $data = array_merge($defaults, $overrides);
        $this->wpdb->insert(
            $this->wpdb->prefix . 'ata_channels',
            $data
        );
        return (int) $this->wpdb->insert_id;
    }

    /**
     * Set up a fake AI provider response.
     */
    protected function mockAiResponse(string $operation, array $response): void
    {
        $this->mockHttp->setResponse('POST', '*/chat/completions', [
            'code' => 200,
            'body' => json_encode(['choices' => [['message' => ['content' => json_encode($response)]]]]),
            'headers' => ['Content-Type: application/json'],
        ]);
    }

    /**
     * Set up a fake Telegram API response.
     */
    protected function mockTelegramResponse(string $method, array $response): void
    {
        $this->mockHttp->setResponse('POST', "*bot*/$method", [
            'code' => 200,
            'body' => json_encode(['ok' => true, 'result' => $response]),
            'headers' => ['Content-Type: application/json'],
        ]);
    }

    /**
     * Run a REST request and return decoded response.
     */
    protected function restRequest(string $method, string $endpoint, array $body = [], int $userId = 1): array
    {
        $request = new \WP_REST_Request($method, $endpoint);
        $request->set_body_params($body);
        $request->set_header('Content-Type', 'application/json');

        // Set current user for permission checks
        wp_set_current_user($userId);

        $response = rest_do_request($request);
        $data = $response->get_data();

        wp_set_current_user(0);

        return is_array($data) ? $data : ['raw' => $data];
    }
}

/**
 * Base class for integration tests.
 */
abstract class IntegrationTestCase extends \WP_Unit_Test_Case
{
    use IntegrationTestHelper;

    /**
     * Get a fresh container instance for each test.
     */
    protected function getFreshContainer(): \ATA\Core\Container
    {
        // Clear singleton instance
        $reflection = new ReflectionClass(\ATA\Core\Container::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null, null);

        return \ATA\Core\Container::instance();
    }
}