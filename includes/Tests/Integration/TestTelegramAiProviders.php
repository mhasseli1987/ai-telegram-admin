<?php
/**
 * Integration Test: Telegram & AI Provider Interactions
 * Tests Telegram Bot API adapter and AI provider with mocked HTTP responses.
 */
require_once __DIR__ . '/bootstrap.php';

class TestTelegramAiProviders extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // --------------------------------------------------------------- Telegram Provider

    public function test_telegram_get_me(): void
    {
        $this->mockTelegramResponse('getMe', [
            'ok' => true,
            'result' => [
                'id'         => 123456789,
                'is_bot'     => true,
                'first_name' => 'Test Bot',
                'username'   => 'testbot',
            ],
        ]);

        $telegram = $this->container->make(\ATA\Contracts\Telegram\TelegramProviderInterface::class);
        $result = $telegram->getMe();

        $this->assertEquals(true, $result['success']);
        $this->assertEquals('testbot', $result['username'] ?? '');
    }

    public function test_telegram_get_me_handles_error(): void
    {
        $this->mockHttp->setResponse('POST', '*bot*/getMe', [
            'code' => 401,
            'body' => json_encode(['ok' => false, 'description' => 'Unauthorized']),
        ]);

        $telegram = $this->container->make(\ATA\Contracts\Telegram\TelegramProviderInterface::class);
        $result = $telegram->getMe();

        $this->assertEquals(false, $result['success']);
    }

    public function test_telegram_get_chat(): void
    {
        $this->mockTelegramResponse('getChat', [
            'ok' => true,
            'result' => [
                'id'       => -1001234567890,
                'type'     => 'channel',
                'title'    => 'Test Channel',
                'username' => 'testchannel',
            ],
        ]);

        $telegram = $this->container->make(\ATA\Contracts\Telegram\TelegramProviderInterface::class);
        $result = $telegram->getChat(-1001234567890);

        $this->assertEquals(true, $result['success']);
        $this->assertEquals('Test Channel', $result['title'] ?? '');
    }

    public function test_telegram_send_message(): void
    {
        $this->mockTelegramResponse('sendMessage', [
            'ok' => true,
            'result' => [
                'message_id' => 123,
                'date'       => time(),
                'chat'       => ['id' => -100123],
            ],
        ]);

        $telegram = $this->container->make(\ATA\Contracts\Telegram\TelegramProviderInterface::class);
        $result = $telegram->sendMessage([
            'chat_id' => -100123,
            'text'    => 'Hello World',
        ]);

        $this->assertEquals(true, $result['success']);
        $this->assertEquals(123, $result['message_id'] ?? 0);
    }

    public function test_telegram_send_message_with_parse_mode(): void
    {
        $this->mockTelegramResponse('sendMessage', ['ok' => true, 'result' => ['message_id' => 1]]);

        $telegram = $this->container->make(\ATA\Contracts\Telegram\TelegramProviderInterface::class);
        $result = $telegram->sendMessage([
            'chat_id'    => -100123,
            'text'       => '<b>Bold</b>',
            'parse_mode' => 'HTML',
        ]);

        $this->assertEquals(true, $result['success']);

        // Verify parse_mode was sent
        $req = $this->getLastRequest('POST', 'sendMessage');
        $this->assertNotNull($req);
        $body = json_decode($req['body'], true);
        $this->assertEquals('HTML', $body['parse_mode'] ?? '');
    }

    public function test_telegram_send_photo(): void
    {
        $this->mockTelegramResponse('sendPhoto', [
            'ok' => true,
            'result' => ['message_id' => 456],
        ]);

        $telegram = $this->container->make(\ATA\Contracts\Telegram\TelegramProviderInterface::class);
        $result = $telegram->sendPhoto([
            'chat_id'  => -100123,
            'photo'    => 'https://example.com/image.jpg',
            'caption'  => 'Test caption',
        ]);

        $this->assertEquals(true, $result['success']);
    }

    public function test_telegram_edit_message_text(): void
    {
        $this->mockTelegramResponse('editMessageText', [
            'ok' => true,
            'result' => ['message_id' => 123],
        ]);

        $telegram = $this->container->make(\ATA\Contracts\Telegram\TelegramProviderInterface::class);
        $result = $telegram->editMessageText([
            'chat_id'    => -100123,
            'message_id' => 123,
            'text'       => 'Updated text',
        ]);

        $this->assertEquals(true, $result['success']);
    }

    public function test_telegram_test_connection(): void
    {
        $this->mockTelegramResponse('getMe', [
            'ok' => true,
            'result' => ['id' => 123, 'username' => 'testbot'],
        ]);

        $telegram = $this->container->make(\ATA\Contracts\Telegram\TelegramProviderInterface::class);
        $result = $telegram->testConnection();

        $this->assertEquals(true, $result['success']);
        $this->assertEquals('testbot', $result['bot_username'] ?? '');
    }

    public function test_telegram_ssrf_protection(): void
    {
        // The BotApiTelegramProvider uses hardcoded api.telegram.org
        // Verify it doesn't accept custom base URLs
        $telegram = $this->container->make(\ATA\Contracts\Telegram\TelegramProviderInterface::class);

        $reflection = new ReflectionClass($telegram);
        $property = $reflection->getProperty('baseUrl');
        $property->setAccessible(true);
        $baseUrl = $property->getValue($telegram);

        $this->assertStringContainsString('api.telegram.org', $baseUrl);
    }

    // --------------------------------------------------------------- AI Provider

    public function test_ai_generate(): void
    {
        $this->mockAiResponse('chat/completions', [
            'content' => 'Generated content from AI',
        ]);

        $provider = $this->container->make(\ATA\Contracts\AI\AIProviderInterface::class);
        $result = $provider->generate('Write a hello world', [
            'model'       => 'gpt-4',
            'temperature' => 0.7,
            'max_tokens'  => 100,
        ]);

        $this->assertEquals(true, $result['success']);
        $this->assertEquals('Generated content from AI', $result['content'] ?? '');
    }

    public function test_ai_rewrite(): void
    {
        $this->mockAiResponse('chat/completions', ['content' => 'Rewritten text']);

        $provider = $this->container->make(\ATA\Contracts\AI\AIProviderInterface::class);
        $result = $provider->rewrite('Original text', 'Make it shorter');

        $this->assertEquals(true, $result['success']);
        $this->assertEquals('Rewritten text', $result['content'] ?? '');
    }

    public function test_ai_summarize(): void
    {
        $this->mockAiResponse('chat/completions', ['content' => 'Summary of long text']);

        $provider = $this->container->make(\ATA\Contracts\AI\AIProviderInterface::class);
        $result = $provider->summarize('Very long text that needs to be summarized...');

        $this->assertEquals(true, $result['success']);
        $this->assertEquals('Summary of long text', $result['content'] ?? '');
    }

    public function test_ai_translate(): void
    {
        $this->mockAiResponse('chat/completions', ['content' => 'ترجمه شده']);

        $provider = $this->container->make(\ATA\Contracts\AI\AIProviderInterface::class);
        $result = $provider->translate('Translate this', 'fa');

        $this->assertEquals(true, $result['success']);
        $this->assertEquals('ترجمه شده', $result['content'] ?? '');
    }

    public function test_ai_generate_title(): void
    {
        $this->mockAiResponse('chat/completions', ['content' => 'Generated Title']);

        $provider = $this->container->make(\ATA\Contracts\AI\AIProviderInterface::class);
        $result = $provider->title('Content to title');

        $this->assertEquals(true, $result['success']);
        $this->assertEquals('Generated Title', $result['content'] ?? '');
    }

    public function test_ai_generate_caption(): void
    {
        $this->mockAiResponse('chat/completions', ['content' => 'Caption for image']);

        $provider = $this->container->make(\ATA\Contracts\AI\AIProviderInterface::class);
        $result = $provider->caption('Image description');

        $this->assertEquals(true, $result['success']);
        $this->assertEquals('Caption for image', $result['content'] ?? '');
    }

    public function test_ai_handles_http_error(): void
    {
        $this->mockHttp->setResponse('POST', '*chat/completions', [
            'code' => 500,
            'body' => json_encode(['error' => ['message' => 'Server error']]),
        ]);

        $provider = $this->container->make(\ATA\Contracts\AI\AIProviderInterface::class);
        $result = $provider->generate('Test prompt');

        $this->assertEquals(false, $result['success']);
        $this->assertStringContainsString('error', strtolower($result['error'] ?? ''));
    }

    public function test_ai_handles_timeout(): void
    {
        $this->mockHttp->setResponse('POST', '*chat/completions', [
            'code' => 408,
            'body' => 'Request timeout',
        ]);

        $provider = $this->container->make(\ATA\Contracts\AI\AIProviderInterface::class);
        $result = $provider->generate('Test');

        $this->assertEquals(false, $result['success']);
    }

    public function test_ai_provider_registry_resolves_default(): void
    {
        $registry = $this->container->make(\ATA\AI\AIProviderRegistry::class);

        // Default provider should be registered
        $default = $registry->getDefault();
        $this->assertNotNull($default);
        $this->assertInstanceOf(\ATA\Contracts\AI\AIProviderInterface::class, $default);
    }

    public function test_ai_provider_registry_can_register_multiple(): void
    {
        $registry = $this->container->make(\ATA\AI\AIProviderRegistry::class);

        // Add a second provider
        $provider2 = new \ATA\AI\OpenAICompatibleProvider(
            $this->container->make(\ATA\Contracts\HttpClientInterface::class),
            $this->container->make(\ATA\Contracts\SecretStoreInterface::class),
            [
                'baseUrl' => 'https://api.anthropic.com/v1',
                'model'   => 'claude-3',
            ]
        );

        $registry->register('anthropic', $provider2);

        $retrieved = $registry->get('anthropic');
        $this->assertSame($provider2, $retrieved);
    }

    // --------------------------------------------------------------- HTTP Client

    public function test_http_client_respects_timeout(): void
    {
        // This test verifies the mock transport receives timeout
        $this->mockHttp->setResponse('POST', 'https://api.test/timeout', [
            'code' => 200,
            'body' => 'ok',
        ]);

        $http = $this->container->make(\ATA\Contracts\HttpClientInterface::class);
        $http->post('https://api.test/timeout', ['timeout' => 10, 'body' => 'test']);

        $req = $this->getLastRequest('POST', 'timeout');
        $this->assertNotNull($req);
        $this->assertEquals(10, $req['timeout']);
    }

    public function test_http_client_sends_headers(): void
    {
        $this->mockHttp->setResponse('POST', 'https://api.test/headers', ['code' => 200, 'body' => 'ok']);

        $http = $this->container->make(\ATA\Contracts\HttpClientInterface::class);
        $http->post('https://api.test/headers', [
            'headers' => ['Authorization' => 'Bearer token123'],
            'body'    => 'test',
        ]);

        $req = $this->getLastRequest('POST', 'headers');
        $this->assertNotNull($req);
        $this->assertArrayHasKey('Authorization', $req['headers']);
        $this->assertEquals('Bearer token123', $req['headers']['Authorization']);
    }

    // --------------------------------------------------------------- SSRF Protection

    public function test_ssrf_gate_blocks_private_ips(): void
    {
        $ssrf = $this->container->make(\ATA\Security\SsrfGate::class);

        // These should be blocked
        $this->assertFalse($ssrf->isAllowed('http://127.0.0.1'));
        $this->assertFalse($ssrf->isAllowed('http://10.0.0.1'));
        $this->assertFalse($ssrf->isAllowed('http://192.168.1.1'));
        $this->assertFalse($ssrf->isAllowed('http://172.16.0.1'));
        $this->assertFalse($ssrf->isAllowed('http://169.254.169.254')); // AWS metadata
        $this->assertFalse($ssrf->isAllowed('http://localhost'));

        // These should be allowed
        $this->assertTrue($ssrf->isAllowed('https://api.telegram.org'));
        $this->assertTrue($ssrf->isAllowed('https://api.openai.com'));
        $this->assertTrue($ssrf->isAllowed('https://example.com'));
    }

    public function test_ssrf_gate_blocks_dns_rebinding(): void
    {
        $ssrf = $this->container->make(\ATA\Security\SsrfGate::class);

        // Hostnames resolving to private IPs should be blocked at connect time
        // The gate does DNS resolution check
        $this->assertFalse($ssrf->isAllowed('http://local.internal'));
    }
}