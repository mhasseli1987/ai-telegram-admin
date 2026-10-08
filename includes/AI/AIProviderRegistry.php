<?php
namespace ATA\AI;

use ATA\Contracts\AI\AIProviderInterface;

defined('ABSPATH') || exit;

/**
 * Registry of AI providers (D-5).
 * Providers register themselves; lookup by driver identifier.
 */
class AIProviderRegistry
{
    private array $providers = [];
    private ?AIProviderInterface $default = null;

    public function register(string $driver, AIProviderInterface $provider, bool $default = false): void
    {
        $this->providers[$driver] = $provider;
        if ($default) {
            $this->default = $provider;
        }
    }

    public function get(string $driver): ?AIProviderInterface
    {
        return $this->providers[$driver] ?? null;
    }

    public function default(): ?AIProviderInterface
    {
        return $this->default ?? ($this->providers[array_key_first($this->providers)] ?? null);
    }

    public function all(): array
    {
        return $this->providers;
    }
}