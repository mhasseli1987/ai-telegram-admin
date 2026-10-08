<?php
namespace ATA\Core;

use ATA\Settings\SettingsRegistry;

defined('ABSPATH') || exit;

/**
 * Registers all plugin settings into the central registry.
 * Called once during boot.
 */
class SettingsRegistrar
{
    public static function register(SettingsRegistry $registry): void
    {
        $registry->register('ata_default_tone', [
            'default'   => 'friendly',
            'type'      => 'string',
            'sanitize'  => 'sanitize_text_field',
            'group'     => 'ai',
            'description' => 'Default tone for AI-generated content.',
        ]);

        $registry->register('ata_default_language', [
            'default'   => 'fa',
            'type'      => 'string',
            'sanitize'  => 'sanitize_text_field',
            'group'     => 'ai',
            'description' => 'Default output language (fa=Persian, en=English).',
        ]);

        $registry->register('ata_post_template', [
            'default'   => '',
            'type'      => 'textarea',
            'sanitize'  => 'sanitize_textarea_field',
            'group'     => 'ai',
            'description' => 'Optional custom template for AI content generation.',
        ]);

        $registry->register('ata_auto_preview', [
            'default'   => '1',
            'type'      => 'boolean',
            'sanitize'  => 'rest_sanitize_boolean',
            'group'     => 'workflow',
            'description' => 'Show Telegram preview after generation.',
        ]);

        $registry->register('ata_log_retention_days', [
            'default'   => 30,
            'type'      => 'integer',
            'sanitize'  => 'absint',
            'group'     => 'system',
            'description' => 'Log retention in days.',
        ]);

        $registry->register('ata_license_key', [
            'default'   => '',
            'type'      => 'string',
            'sanitize'  => 'sanitize_text_field',
            'group'     => 'license',
            'description' => 'License key (stored encrypted).',
        ]);

        $registry->register('ata_license_site', [
            'default'   => '',
            'type'      => 'string',
            'sanitize'  => 'sanitize_text_field',
            'group'     => 'license',
            'description' => 'Site URL registered with license.',
        ]);

        $registry->register('ata_license_status', [
            'default'   => 'inactive',
            'type'      => 'string',
            'sanitize'  => 'sanitize_text_field',
            'group'     => 'license',
            'description' => 'Current license status.',
        ]);

        $registry->register('ata_license_expiry', [
            'default'   => '',
            'type'      => 'string',
            'sanitize'  => 'sanitize_text_field',
            'group'     => 'license',
            'description' => 'License expiry date (Y-m-d).',
        ]);

        $registry->register('ata_grace_period_days', [
            'default'   => 7,
            'type'      => 'integer',
            'sanitize'  => 'absint',
            'group'     => 'license',
            'description' => 'Grace period after license failure (D-10 fail-open).',
        ]);
    }
}