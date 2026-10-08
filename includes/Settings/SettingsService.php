<?php
namespace ATA\Settings;

defined('ABSPATH') || exit;

class SettingsService
{
    private SettingsRegistry $registry;

    public function __construct(SettingsRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function get(string $key)
    {
        return $this->registry->get($key);
    }

    public function set(string $key, $value): void
    {
        $this->registry->set($key, $value);
    }

    public function all(): array
    {
        return $this->registry->all();
    }

    public function definitions(): array
    {
        return $this->registry->definitions();
    }

    public function defaults(): array
    {
        return $this->registry->defaults();
    }
}