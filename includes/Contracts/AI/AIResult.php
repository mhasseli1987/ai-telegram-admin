<?php
namespace ATA\Contracts\AI;

defined('ABSPATH') || exit;

class AIResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $content,
        public readonly ?string $model = null,
        public readonly int $tokensUsed = 0,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly array $raw = [],
        /** Mutable: services (ContentService) attach post-generation info
         *  (e.g. stored_post_id) after construction; readonly would forbid it. */
        public array $context = []
    ) {}

    public static function ok(string $content, ?string $model = null, int $tokens = 0, array $raw = []): self
    {
        return new self(true, $content, $model, $tokens, null, null, $raw);
    }

    public static function fail(string $code, string $message): self
    {
        return new self(false, '', null, 0, $code, $message);
    }
}
