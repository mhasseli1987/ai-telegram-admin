<?php
/**
 * Integration Test: Telegram & AI Provider Interactions
 * Tests Telegram Bot API adapter and AI provider with mocked HTTP responses.
 *
 * Written against the REAL adapter APIs:
 *  - BotApiTelegramProvider returns the raw Telegram `result` object and
 *    throws RuntimeException on API failure; only testConnection() is soft.
 *  - OpenAICompatibleProvider::generate(AIRequest): AIResult.
 */
require_once __DIR__ . '/bootstrap.php';

class TestTelegramAiProviders extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The Telegram provider requires a configured token.
        (new \ATA\Security\SecretStore())->set('telegram_bot_token', '123456:TEST-TOKEN');
    }

    protected function tearDown(): void
    {
        (new \ATA\Security\SecretStore())->delete('telegram_bot_token');
        parent::tearDown();
    }

    // --------------------------------------------------------------- Telegram Provider

    public function test_telegram_get_me(): void
    {
        $this->mockTelegramResponse('getMe', [
            'id'         => 123456789,
            'is_bot'     => true,
            'first_name' => 'Test Bot',
            'username'   => 'testbot',
        ]);

        $telegram = $this->container->make(\ATA\Contracts\Telegram\TelegramProviderInterface::class);
        $me = $telegram->getMe();

        $this->assertEquals(123456789, $me['id']);
        $this->assertEquals('testbot', $me['username']);
    }

    public function test_telegram_get_me_handles_error(): void
    {
        $this->mockHttp->setResponse('POST', '*bot*/getMe', [
            'code' => 401,
            'body' => json_encode(['ok' => false, 'description' => 'Unauthorized']),
        ]);

        $telegram = $this->container->make(\ATA\Contracts\Telegram\TelegramProviderInterface::class);

        $this->expectException(\RuntimeException::class);
        $telegram->getMe();
    }

    public function test_telegram_get_chat(): void
    {
        $this->mockTelegramResponse('getChat', [
            'id'       => -1001234567890,
            'type'     => 'channel',
            'title'    => 'Test Channel',
            'username' => 'testchannel',
        ]);

        $telegram = $this->container->make(\ATA\Contracts\Telegram\TelegramProviderInterface::class);
        $chat = $telegram->getChat(-1001234567890);

        $this->assertEquals('Test Channel', $chat['title']);
        $this->assertEquals('testchannel', $chat['username']);
    }

    public function test_telegram_send_message(): void
    {
        $this->mockTelegramResponse('sendMessage', [
            'message_id' => 123,
            'date'       => time(),
            'chat'       => ['id' => -100123],
        ]);

        $telegram = $this->container->make(\ATA\Contracts\Telegram\TelegramProviderInterface::class);
        $result = $telegram->sendMessage([
            'chat_id' => -100123,
            'text'    => 'Hello World',
        ]);

        $this->assertEquals(123, $result['message_id']);
    }

    public function test_telegram_send_message_with_parse_mode(): void
    {
        $this->mockTelegramResponse('sendMessage', ['message_id' => 1]);

        $telegram = $this->container->make(\ATA\Contracts\Telegram\TelegramProviderInterface::class);
        $telegram->sendMessage([
            'chat_id'    => -100123,
            'text'       => '<b>Bold</b>',
            'parse_mode' => 'HTML',
        ]);

        // Verify parse_mode was sent in the request body
        $req = $this->getLastRequest('POST', 'sendMessage');
        $this->assertNotNull($req);
        $body = json_decode($req['body'], true);
        $this->assertEquals('HTML', $body['parse_mode'] ?? '');
    }

    public function test_telegram_send_photo(): void
    {
        $this->mockTelegramResponse('sendPhoto', ['message_id' => 456]);

        $telegram = $this->container->make(\ATA\Contracts\Telegram\TelegramProviderInterface::class);
        $result = $telegram->sendPhoto([
            'chat_id'  => -100123,
            'photo'    => 'https://example.com/image.jpg',
            'caption'  => 'Test caption',
        ]);

        $this->assertEquals(456, $result['message_id']);
    }

    public function test_telegram_edit_message_text(): void
    {
        $this->mockTelegramResponse('editMessageText', ['message_id' => 123]);

        $telegram = $this->container->make(\ATA\Contracts\Telegram\TelegramProviderInterface::class);
        $result = $telegram->editMessageText([
            'chat_id'    => -100123,
            'message_id' => 123,
            'text'       => 'Updated text',
        ]);

        $this->assertEquals(123, $result['message_id']);
    }

    public function test_telegram_test_connection(): void
    {
        $this->mockTelegramResponse('getMe', ['id' => 123, 'username' => 'testbot']);

        $telegram = $this->container->make(\ATA\Contracts\Telegram\TelegramProviderInterface::class);
        $result = $telegram->testConnection();

        $this->assertTrue($result['success']);
        $this->assertEquals('testbot', $result['bot_username'] ?? '');
    }

    public function test_telegram_test_connection_soft_failure(): void
    {
        // testConnection() never throws — it reports failure instead.
        $this->mockHttp->setResponse('POST', '*bot*/getMe', [
            'code' => 401,
            'body' => json_encode(['ok' => false, 'description' => 'Unauthorized']),
        ]);

        $telegram = $this->container->make(\ATA\Contracts\Telegram\TelegramProviderInterface::class);
        $result = $telegram->testConnection();

        $this->assertFalse($result['success']);
    }

    public function test_telegram_ssrf_protection(): void
    {
        // The BotApiTelegramProvider hardcodes api.telegram.org — the apiBase
        // property must never be caller-controllable.
        $telegram = $this->container->make(\ATA\Contracts\Telegram\TelegramProviderInterface::class);

        $reflection = new ReflectionClass($telegram);
        $property = $reflection->getProperty('apiBase');
        $baseUrl = $property->getValue($telegram);

        $this->assertStringContainsString('api.telegram.org', $baseUrl);
    }

    public function test_telegram_missing_token_throws(): void
    {
        (new \ATA\Security\SecretStore())->delete('telegram_bot_token');

        $telegram = $this->container->make(\ATA\Contracts\Telegram\TelegramProviderInterface::class);

        $this->expectException(\RuntimeException::class);
        $telegram->getMe();
    }

    // --------------------------------------------------------------- AI Provider

    private function aiRequest(string $operation, string $input, array $config = []): \ATA\Contracts\AI\AIRequest
    {
        return new \ATA\Contracts\AI\AIRequest(
            operations: [$operation],
            messages: [['role' => 'user', 'content' => $input]],
            config: $config + ['apiKey' => 'sk-test-key-12345678', 'model' => 'gpt-4o-mini'],
        );
    }

    public function test_ai_generate(): void
    {
        $this->mockAiResponse('Generated content from AI');

        $provider = $this->container->make(\ATA\Contracts\AI\AIProviderInterface::class);
        $result = $provider->generate($this->aiRequest('generate', 'Write a hello world'));

        $this->assertTrue($result->success);
        $this->assertEquals('Generated content from AI', $result->content);
    }

    public function test_ai_rewrite(): void
    {
        $this->mockAiResponse('Rewritten text');

        $provider = $this->container->make(\ATA\Contracts\AI\AIProviderInterface::class);
        $result = $provider->generate($this->aiRequest('rewrite', 'Original text'));

        $this->assertTrue($result->success);
        $this->assertEquals('Rewritten text', $result->content);
    }

    public function test_ai_summarize(): void
    {
        $this->mockAiResponse('Summary of long text');

        $provider = $this->container->make(\ATA\Contracts\AI\AIProviderInterface::class);
        $result = $provider->generate($this->aiRequest('summarize', 'Very long text...'));

        $this->assertTrue($result->success);
        $this->assertEquals('Summary of long text', $result->content);
    }

    public function test_ai_translate(): void
    {
        $this->mockAiResponse('ترجمه شده');

        $provider = $this->container->make(\ATA\Contracts\AI\AIProviderInterface::class);
        $result = $provider->generate($this->aiRequest('translate', 'Translate this'));

        $this->assertTrue($result->success);
        $this->assertEquals('ترجمه شده', $result->content);
    }

    public function test_ai_generate_title(): void
    {
        $this->mockAiResponse('Generated Title');

        $provider = $this->container->make(\ATA\Contracts\AI\AIProviderInterface::class);
        $result = $provider->generate($this->aiRequest('title', 'Content to title'));

        $this->assertTrue($result->success);
        $this->assertEquals('Generated Title', $result->content);
    }

    public function test_ai_generate_caption(): void
    {
        $this->mockAiResponse('Caption for image');

        $provider = $this->container->make(\ATA\Contracts\AI\AIProviderInterface::class);
        $result = $provider->generate($this->aiRequest('caption', 'Image description'));

        $this->assertTrue($result->success);
        $this->assertEquals('Caption for image', $result->content);
    }

    public function test_ai_empty_input_fails(): void
    {
        $provider = $this->container->make(\ATA\Contracts\AI\AIProviderInterface::class);
        $result = $provider->generate($this->aiRequest('generate', ''));

        $this->assertFalse($result->success);
        $this->assertEquals('empty_input', $result->errorCode);
    }

    public function test_ai_handles_http_error(): void
    {
        $this->mockHttp->setResponse('POST', '*chat/completions*', [
            'code' => 500,
            'body' => json_encode(['error' => ['message' => 'Server error']]),
        ]);

        $provider = $this->container->make(\ATA\Contracts\AI\AIProviderInterface::class);
        $result = $provider->generate($this->aiRequest('generate', 'Test prompt'));

        $this->assertFalse($result->success);
        $this->assertNotNull($result->errorCode);
        $this->assertStringContainsString('server error', strtolower($result->errorMessage ?? ''));
    }

    public function test_ai_handles_timeout(): void
    {
        $this->mockHttp->setResponse('POST', '*chat/completions*', [
            'code' => 408,
            'body' => 'Request timeout',
        ]);

        $provider = $this->container->make(\ATA\Contracts\AI\AIProviderInterface::class);
        $result = $provider->generate($this->aiRequest('generate', 'Test'));

        $this->assertFalse($result->success);
    }

    public function test_ai_request_body_has_model_and_auth(): void
    {
        $this->mockAiResponse('ok');

        $provider = $this->container->make(\ATA\Contracts\AI\AIProviderInterface::class);
        $provider->generate($this->aiRequest('generate', 'hello'));

        $req = $this->getLastRequest('POST', 'chat/completions');
        $this->assertNotNull($req);

        $body = json_decode($req['body'], true);
        $this->assertEquals('gpt-4o-mini', $body['model'] ?? '');
        $this->assertEquals('Bearer sk-test-key-12345678', $req['headers']['Authorization'] ?? '');
    }

    public function test_ai_provider_registry_resolves_default(): void
    {
        $registry = $this->container->make(\ATA\AI\AIProviderRegistry::class);

        $default = $registry->default();
        $this->assertNotNull($default);
        $this->assertInstanceOf(\ATA\Contracts\AI\AIProviderInterface::class, $default);
    }

    public function test_ai_provider_registry_can_register_multiple(): void
    {
        $registry = $this->container->make(\ATA\AI\AIProviderRegistry::class);

        // Second provider — same constructor signature (HttpClientInterface + Logger).
        $provider2 = new \ATA\AI\OpenAICompatibleProvider(
            $this->container->make(\ATA\Contracts\HttpClientInterface::class),
            $this->container->make(\ATA\Contracts\Log\LoggerInterface::class)
        );

        $registry->register('anthropic', $provider2);

        $retrieved = $registry->get('anthropic');
        $this->assertSame($provider2, $retrieved);
    }

    // --------------------------------------------------------------- HTTP Client

    public function test_http_client_respects_timeout(): void
    {
        $this->mockHttp->setResponse('POST', 'https://api.test/timeout', [
            'code' => 200,
            'body' => 'ok',
        ]);

        $http = $this->container->make(\ATA\Contracts\HttpClientInterface::class);
        $http->request('https://api.test/timeout', ['method' => 'POST', 'timeout' => 10, 'body' => 'test']);

        $req = $this->getLastRequest('POST', 'timeout');
        $this->assertNotNull($req);
        $this->assertEquals(10, $req['timeout']);
    }

    public function test_http_client_sends_headers(): void
    {
        $this->mockHttp->setResponse('POST', 'https://api.test/headers', ['code' => 200, 'body' => 'ok']);

        $http = $this->container->make(\ATA\Contracts\HttpClientInterface::class);
        $http->request('https://api.test/headers', [
            'method'  => 'POST',
            'headers' => ['Authorization' => 'Bearer token123'],
            'body'    => 'test',
        ]);

        $req = $this->getLastRequest('POST', 'headers');
        $this->assertNotNull($req);
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

    public function test_ssrf_gate_blocks_non_http_schemes(): void
    {
        $ssrf = $this->container->make(\ATA\Security\SsrfGate::class);

        $this->assertFalse($ssrf->isAllowed('file:///etc/passwd'));
        $this->assertFalse($ssrf->isAllowed('ftp://example.com/file'));
    }
}
