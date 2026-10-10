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
     * Redact secrets from context before persistence (defense in depth).
     * Two layers:
     *  1. Key-based: any value under a secret-looking key ('bot_token',
     *     'api_key', 'authorization', ...) is masked — this closed the biggest
     *     hole: context like ['bot_token' => '123:AAH...'] used to be written
     *     verbatim to ata_logs.
     *  2. Pattern-based: secret-looking fragments inside free text
     *     ('key=...', 'Bearer sk-...', 40+ hex/entropy strings).
     */
    public static function redact(array $context): array
    {
        $out = [];
        foreach ($context as $k => $v) {
            $key = strtolower((string)$k);
            if (self::isSecretKey($key)) {
                $out[$k] = '***';
                continue;
            }
            if (is_array($v)) {
                $out[$k] = self::redact($v); // recurse into nested context
                continue;
            }
            if (is_string($v) || is_numeric($v)) {
                $out[$k] = self::redactString((string)$v);
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    private static function isSecretKey(string $key): bool
    {
        return (bool) preg_match(
            '/(api[_-]?key|apikey|bot[_-]?token|secret|authorization|password|passwd|pwd|token|bearer|credential)/i',
            $key
        );
    }

    private static function redactString(string $str): string
    {
        // key=value / key: value inside free-form strings.
        $str = preg_replace(
            '/(api[_-]?key|apikey|bot[_-]?token|secret|authorization|password|passwd|token)\b\s*[:=]\s*\S+/i',
            '$1=***',
            $str
        ) ?: $str;
        // Bearer / raw key fragments.
        $str = preg_replace('/(sk-[A-Za-z0-9_-]{8,})/', '***', $str) ?: $str;
        return $str;
    }
}