<?php
namespace ATA\Logging;

use ATA\Contracts\Log\LoggerInterface;
use ATA\Infrastructure\WpDb\LogRepository;

defined('ABSPATH') || exit;

/**
 * Structured logger (D-9, rule 13).
 * Redacts secret patterns from context before persisting — defense in depth.
 */
class Logger implements LoggerInterface
{
    private const LEVELS = ['debug' => 100, 'info' => 200, 'warning' => 300, 'error' => 400];

    private LogRepository $repo;

    public function __construct(LogRepository $repo)
    {
        $this->repo = $repo;
    }

    public function debug(string $message, array $context = []): void
    {
        $this->write('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $context = $this->redact($context);

        $this->repo->insert([
            'scope'    => $context['scope'] ?? 'system',
            'level'    => $level,
            'message'  => $message,
            'context'  => json_encode($context, JSON_UNESCAPED_UNICODE),
            'request_id' => $context['request_id'] ?? null,
        ]);
    }

    /**
     * Redact secret-like values from context (defense in depth).
     * Matches: key*, token*, secret*, authorization, api_key, bot_token.
     */
    public static function redact(array $context): array
    {
        $patterns = [
            '/(?i)(api[_-]?key|bot[_-]?token|secret|authorization|password|passwd|token)\b\s*[:=]\s*[^\s,}]+/m',
        ];

        $redacted = $context;
        foreach ($redacted as $k => $v) {
            if (!is_string($v) && !is_numeric($v)) {
                continue;
            }
            $str = (string)$v;
            foreach ($patterns as $pat) {
                $str = preg_replace($pat, '$1=***', $str);
            }
            $redacted[$k] = $str;
        }
        return $redacted;
    }
}