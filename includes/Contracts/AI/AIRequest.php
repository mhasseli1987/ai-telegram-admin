<?php
namespace ATA\Contracts\AI;

defined('ABSPATH') || exit;

class AIRequest
{
    public function __construct(
        public readonly array $operations,
        public readonly array $messages = [],
        public readonly array $config = [],
        public readonly array $context = []
    ) {}
}