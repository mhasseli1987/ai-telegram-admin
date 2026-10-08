<?php
namespace ATA\Telegram;

use ATA\Contracts\Telegram\TelegramProviderInterface;
use ATA\Contracts\HttpClientInterface;
use ATA\Logging\Logger;

defined('ABSPATH') || exit;

/**
 * Telegram Bot API adapter (SPECIFICATION.md:292-325).
 * Verbs-only: getMe/getChat/sendMessage/sendPhoto/editMessageText/testConnection.
 */
class BotApiTelegramProvider implements TelegramProviderInterface
{
    private HttpClientInterface $http;
    private Logger $logger;
    private string $apiBase = 'https://api.telegram.org/bot';

    public function __construct(HttpClientInterface $http, Logger $logger)
    {
        $this->http = $http;
        $this->logger = $logger;
    }

    private function endpoint(string $method, ?string $token = null): string
    {
        if ($token === null) {
            $token = $this->getActiveToken();
        }
        return $this->apiBase . '/' . $token . '/' . $method;
    }

    private function getActiveToken(): string
    {
        // SecretStore lookup — never logged.
        $container = \ATA\Container::instance();
        $store = $container->make(\ATA\Contracts\SecretStoreInterface::class);
        $token = $store->get('telegram_bot_token');
        if ($token === null || $token === '') {
            throw new \RuntimeException('Telegram bot token not configured.');
        }
        return $token;
    }

    public function getMe(): array
    {
        $resp = $this->http->request($this->endpoint('getMe'), [
            'method' => 'GET',
            'timeout' => 15,
        ]);
        $data = json_decode($resp['body'], true);
        if (!is_array($data) || empty($data['ok'])) {
            throw new \RuntimeException('getMe failed: ' . ($data['description'] ?? 'unknown'));
        }
        return $data['result'];
    }

    public function getChat(int $chatId): array
    {
        $resp = $this->http->request($this->endpoint('getChat'), [
            'method' => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['chat_id' => $chatId]),
            'timeout' => 15,
        ]);
        $data = json_decode($resp['body'], true);
        if (!is_array($data) || empty($data['ok'])) {
            throw new \RuntimeException('getChat failed: ' . ($data['description'] ?? 'unknown'));
        }
        return $data['result'];
    }

    public function sendMessage(array $payload): array
    {
        $data = [
            'chat_id' => $payload['chat_id'],
            'text' => $payload['text'],
            'parse_mode' => $payload['parse_mode'] ?? 'HTML',
            'disable_web_page_preview' => $payload['disable_web_page_preview'] ?? true,
        ];
        if (!empty($payload['reply_markup'])) {
            $data['reply_markup'] = $payload['reply_markup'];
        }
        if (!empty($payload['reply_to_message_id'])) {
            $data['reply_to_message_id'] = $payload['reply_to_message_id'];
        }

        $resp = $this->http->request($this->endpoint('sendMessage'), [
            'method' => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'timeout' => 30,
        ]);
        $decoded = json_decode($resp['body'], true);
        if (!is_array($decoded) || empty($decoded['ok'])) {
            throw new \RuntimeException('sendMessage failed: ' . ($decoded['description'] ?? 'unknown'));
        }
        return $decoded['result'];
    }

    public function sendPhoto(array $payload): array
    {
        $data = [
            'chat_id' => $payload['chat_id'],
            'caption' => $payload['caption'] ?? '',
            'parse_mode' => $payload['parse_mode'] ?? 'HTML',
        ];
        if (!empty($payload['photo'])) {
            $data['photo'] = $payload['photo'];
        }

        $resp = $this->http->request($this->endpoint('sendPhoto'), [
            'method' => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'timeout' => 30,
        ]);
        $decoded = json_decode($resp['body'], true);
        if (!is_array($decoded) || empty($decoded['ok'])) {
            throw new \RuntimeException('sendPhoto failed: ' . ($decoded['description'] ?? 'unknown'));
        }
        return $decoded['result'];
    }

    public function editMessageText(array $payload): array
    {
        $data = [
            'chat_id' => $payload['chat_id'],
            'message_id' => $payload['message_id'],
            'text' => $payload['text'],
            'parse_mode' => $payload['parse_mode'] ?? 'HTML',
        ];

        $resp = $this->http->request($this->endpoint('editMessageText'), [
            'method' => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'timeout' => 30,
        ]);
        $decoded = json_decode($resp['body'], true);
        if (!is_array($decoded) || empty($decoded['ok'])) {
            throw new \RuntimeException('editMessageText failed: ' . ($decoded['description'] ?? 'unknown'));
        }
        return $decoded['result'];
    }

    public function testConnection(): array
    {
        try {
            $me = $this->getMe();
            return [
                'success' => true,
                'message' => 'اتصال برقرار شد.',
                'bot_username' => $me['username'] ?? null,
                'bot_id' => $me['id'] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}