<?php
namespace ATA\AI;

use ATA\Contracts\AI\AIProviderInterface;
use ATA\Contracts\AI\AIRequest;
use ATA\Contracts\AI\AIResult;
use ATA\Contracts\HttpClientInterface;
use ATA\Logging\Logger;

defined('ABSPATH') || exit;

/**
 * Adapter for any OpenAI-compatible HTTP endpoint (SPECIFICATION.md:326-352).
 * Configurable: baseUrl, apiKey, model, temperature, maxTokens, timeout, custom headers.
 */
class OpenAICompatibleProvider implements AIProviderInterface
{
    public const MODEL_OPENAI = 'openai';
    public const MODEL_GEMINI = 'gemini';

    private HttpClientInterface $http;
    private Logger $logger;

    /** @var array<string, string> */
    private static $MODEL_DEFAULTS = [
        self::MODEL_OPENAI => ['temperature' => 0.5, 'maxTokens' => 2000],
        self::MODEL_GEMINI => ['temperature' => 0.7, 'maxTokens' => 2000],
    ];

    public function __construct(HttpClientInterface $http, Logger $logger)
    {
        $this->http = $http;
        $this->logger = $logger;
    }

    public function generate(AIRequest $request): AIResult
    {
        $config = $this->parseConfig($request->config);
        $operation = $request->operations[0] ?? 'generate';
        $userMsg = $request->messages[0]['content'] ?? '';
        if ($userMsg === '') {
            return AIResult::fail('empty_input', 'محصول: ورودی خالی است.');
        }

        $prompt = $this->buildPrompt($operation, $userMsg, $config);

        $payload = [
            'model' => $config['model'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->buildSystemPrompt($config),
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => $config['temperature'],
            'max_tokens' => $config['maxTokens'],
            'stream' => false,
        ];

        try {
            $opts = [
                'method'    => 'POST',
                'headers'   => $this->buildHeaders($config),
                'body'      => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'timeout'   => $config['timeout'],
            ];

            $this->logger->debug('AI request sent', [
                'operation' => $operation,
                'model' => $config['model'],
                'url' => $config['baseUrl'],
                'request_id' => $config['request_id'] ?? 'n/a',
            ]);

            $t0 = microtime(true);
            $response = $this->http->request($config['baseUrl'], $opts);
            $latency = (int) round((microtime(true) - $t0) * 1000);

            $body = json_decode($response['body'], true);
            if (!is_array($body)) {
                return AIResult::fail('invalid_response', 'پاسخ نامعتبر از سرویس AI.');
            }

            $choices = $body['choices'] ?? [];
            $content = $choices[0]['message']['content'] ?? '';

            $usage = $body['usage'] ?? [];
            $tokens = (int) ($usage['total_tokens'] ?? 0);

            $result = AIResult::ok($content, $config['model'], $tokens, $response);

            $this->logger->debug('AI response received', [
                'operation' => $operation,
                'model' => $config['model'],
                'latency_ms' => $latency,
                'tokens' => $tokens,
                'request_id' => $config['request_id'] ?? 'n/a',
            ]);

            return $result;

        } catch (\Exception $e) {
            return AIResult::fail('ai_request_failed', $e->getMessage());
        }
    }

    public function listModels(array $config = []): array
    {
        // For MVP, return a curated default list when the provider refuses/returns empty.
        if (empty($config['baseUrl'])) {
            return ['openai/gpt-4o', 'openai/gpt-4o-mini', 'openai/o1-mini', 'custom/model'];
        }
        try {
            $base = rtrim($config['baseUrl'], '/');
            // OpenAI-compatible pattern.
            if (!str_contains($base, 'v1/') && !str_contains($base, 'openai/')) {
                $base = $base . '/v1';
            }
            $resp = $this->http->request($base . '/models', [
                'method' => 'GET',
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => 10,
            ]);
            if ((int) ($resp['status'] ?? 0) !== 200) {
                return [];
            }
            $data = json_decode($resp['body'], true);
            $models = $data['data'] ?? [];
            return wp_list_pluck($models, 'id');
        } catch (\Exception $e) {
            $this->logger->warning('listModels failed', ['exception' => $e->getMessage()]);
            return [];
        }
    }

    public function testConnection(array $config = []): array
    {
        if (empty($config['baseUrl'])) {
            return ['success' => false, 'message' => 'base_url الزامی است.'];
        }
        try {
            $opts = [
                'method'    => 'POST',
                'headers'   => $this->buildHeaders($config),
                'body'      => json_encode([
                    'model' => ($config['model'] ?? 'gpt-4o-mini'),
                    'messages' => [['role' => 'user', 'content' => 'p']],
                    'max_tokens' => 1,
                    'stream' => false,
                ], JSON_UNESCAPED_UNICODE),
                'timeout' => 10,
            ];
            $t0 = microtime(true);
            $response = $this->http->request($config['baseUrl'], $opts);
            $latency = (int) round((microtime(true) - $t0) * 1000);
            $body = json_decode($response['body'], true);
            $success = is_array($body) &&
                (!empty($body['choices']) || !empty($body['data']));
            return [
                'success' => $success,
                'message' => $success ? 'اتصال برقرار شد.' : 'سرویس پاسخ نامعتبر داد.',
                'latency_ms' => $latency,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'latency_ms' => null,
            ];
        }
    }

    private function parseConfig(array $config): array
    {
        $defaults = self::$MODEL_DEFAULTS[$config['model'] ?? ''] ?? self::$MODEL_DEFAULTS[self::MODEL_OPENAI];
        return wp_parse_args($config, array_merge($defaults, [
            'baseUrl'    => 'https://api.openai.com/v1/chat/completions',
            'apiKey'     => '',
            'model'      => 'gpt-4o-mini',
            'temperature'=> 0.5,
            'maxTokens'  => 2000,
            'timeout'    => 30,
            'headers'    => [],
        ]));
    }

    private function buildSystemPrompt(array $config): string
    {
        return 'شما دستیار تولید محتوای تلگرام هستید. متن خروجی باید آماده‌ی انتشار در یک کانال تلگرام فارسی باشد.';
    }

    private function buildPrompt(string $operation, string $input, array $config): string
    {
        $desc = match ($operation) {
            'rewrite' => 'متن زیر را بازنویسی کنید (همان معنا، بیان بهتر):',
            'summarize' => 'متن زیر را به‌صورت خلاصه و مفید بنویسید:',
            'title' => 'برای متن زیر یک تیتر جذاب بنویسید (فقط تیتر):',
            'caption' => 'برای تصویر/محتوای زیر یک کپشن (Caption) جذاب برای تلگرام بنویسید:',
            'translate' => 'متن زیر را به زبان فارسی ترجمه کنید:',
            default => 'متن زیر را تولید یا پردازش کنید:',
        };
        return $desc . "\n\n" . $input;
    }

    private function buildHeaders(array $config): array
    {
        $headers = ['Content-Type' => 'application/json'];
        if (!empty($config['apiKey'])) {
            $headers['Authorization'] = 'Bearer ' . $config['apiKey'];
        }
        foreach ($config['headers'] as $k => $v) {
            if (stripos($k, 'authorization') === false) {
                $headers[$k] = $v;
            }
        }
        return $headers;
    }
}