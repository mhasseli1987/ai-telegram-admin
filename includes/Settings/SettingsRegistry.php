<?php
namespace ATA\Settings;

defined('ABSPATH') || exit;

/**
 * Central registry for all plugin settings (D-11).
 * Each module registers its own settings with defaults + validation.
 */
class SettingsRegistry
{
    private array $settings = [];

    public function register(string $key, array $definition): void
    {
        $definition = wp_parse_args($definition, [
            'default'     => null,
            'type'        => 'string',
            'sanitize'    => 'sanitize_text_field',
            'group'       => 'general',
            'description' => '',
        ]);
        $this->settings[$key] = $definition;
    }

    public function get(string $key)
    {
        if (!isset($this->settings[$key])) {
            return null;
        }
        $def = $this->settings[$key];
        $val = get_option($key, $def['default']);
        return $val;
    }

    public function set(string $key, $value): void
    {
        if (!isset($this->settings[$key])) {
            return;
        }
        $def = $this->settings[$key];
        $sanitized = call_user_func($def['sanitize'], $value);
        update_option($key, $sanitized, false);
    }

    public function all(): array
    {
        $out = [];
        foreach ($this->settings as $key => $def) {
            $out[$key] = $this->get($key);
        }
        return $out;
    }

    public function definitions(): array
    {
        return $this->settings;
    }

    public function defaults(): array
    {
        $out = [];
        foreach ($this->settings as $key => $def) {
            $out[$key] = $def['default'];
        }
        return $out;
    }
}