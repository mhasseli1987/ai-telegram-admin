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

    /** @var array<string, array{temperature:float, maxTokens:int}> */
    private const MODEL_DEFAULTS = [
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

            if (isset($body['error'])) {
                // Surface the API error, but mask any key-like fragments (rule 13).
                $errMsg = is_string($body['error']['message'] ?? null)
                    ? $body['error']['message']
                    : 'خطای سرویس AI.';
                $errMsg = (string) preg_replace('/(sk-[A-Za-z0-9_-]{8,}|[A-Za-z0-9_-]{32,})/', '***', $errMsg);
                return AIResult::fail('ai_error', $errMsg);
            }

            $choices = $body['choices'] ?? [];
            $content = is_string($choices[0]['message']['content'] ?? null)
                ? trim($choices[0]['message']['content'])
                : '';
            if ($content === '') {
                return AIResult::fail('empty_response', 'سرویس AI پاسخ خالی برگرداند.');
            }

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
        if (empty($config['baseUrl'])) {
            return ['gpt-4o', 'gpt-4o-mini', 'o1-mini'];
        }
        try {
            $modelsUrl = $this->toModelsUrl((string) $config['baseUrl']);
            if ($modelsUrl === '') {
                return ['gpt-4o', 'gpt-4o-mini', 'o1-mini'];
            }
            $headers = ['Content-Type' => 'application/json'];
            if (!empty($config['apiKey'])) {
                $headers['Authorization'] = 'Bearer ' . $config['apiKey'];
            }
            $resp = $this->http->request($modelsUrl, [
                'method'  => 'GET',
                'headers' => $headers, // was unauthenticated: 401 for keyed providers
                'timeout' => 10,
            ]);
            if ((int) ($resp['status'] ?? 0) !== 200) {
                return [];
            }
            $data = json_decode($resp['body'], true);
            $models = $data['data'] ?? [];
            if (!is_array($models)) {
                return [];
            }
            $ids = [];
            foreach ($models as $m) {
                if (is_array($m) && isset($m['id'])) {
                    $ids[] = (string) $m['id'];
                }
            }
            return $ids; // wp_list_pluck() is admin-only; avoid fatal on frontend/cron.
        } catch (\Exception $e) {
            $this->logger->warning('listModels failed', ['exception' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Derive the /models URL from any chat-completions or API-root baseUrl.
     * Handles: .../v1/chat/completions, .../v1/, .../v1, .../ (root).
     */
    private function toModelsUrl(string $baseUrl): string
    {
        $base = rtrim($baseUrl, '/');
        $base = preg_replace('~/chat/completions$~', '', $base);
        $base = rtrim((string) $base, '/');

        $prefix = '';
        $qPos = strpos($base, '?');
        if ($qPos !== false) {
            $prefix = (string) substr($base, $qPos);
            $base = (string) substr($base, 0, $qPos);
        }

        if (preg_match('~/v\d+$~', $base)) {
            return $base . '/models' . $prefix;
        }
        // Bare domain (e.g. https://api.provider.com): append /v1/models.
        if (preg_match('~^https?://[^/]+$~i', $base)) {
            return $base . '/v1/models' . $prefix;
        }
        return $base . '/models' . $prefix;
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
        $key = $config['model'] ?? '';
        $defaults = self::MODEL_DEFAULTS[$key] ?? self::MODEL_DEFAULTS[self::MODEL_OPENAI];
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
        // Case-insensitive filter: HTTP header names are case-insensitive, and a
        // custom config must never override the Authorization set above.
        foreach ((array) ($config['headers'] ?? []) as $k => $v) {
            $k = (string) $k;
            if ($k === '' || !is_scalar($v)) {
                continue;
            }
            $lower = strtolower($k);
            if ($lower === 'authorization' || $lower === 'content-type' || str_starts_with($lower, 'host')) {
                continue;
            }
            if (!preg_match('/^[A-Za-z0-9!#$%&\'*+.^_`|~-]+$/', $k)) {
                continue; // invalid / possibly injected header name
            }
            $headers[$k] = (string) $v;
        }
        return $headers;
    }
}