<?php
namespace ATA\Admin;

use ATA\Settings\SettingsService;
use ATA\Security\SecretStore;
use ATA\Logging\Logger;
use ATA\Infrastructure\Http\WpHttpTransport;
use ATA\Infrastructure\WpDb\Installer;
use ATA\Infrastructure\WpDb\LogRepository;
use ATA\Services\ContentService;
use ATA\AI\AIProviderRegistry;

defined('ABSPATH') || exit;

class MenuProvider
{
    public static function register(): void
    {
        $slug = 'ata-telegram-ai-admin';

        // Add parent menu.
        add_menu_page(
            'ATA: AI Telegram Admin',
            'ATA: AI Admin',
            'manage_options',
            $slug,
            [self::class, 'adminPage'],
            'dashicons-admin-site',
            110  // position after Settings
        );

        // 14 submenu items (UX_SPEC.md:40-55).
        $subs = [
            ['Generated', 'generated', 'manage_options', 'ata-generated'],
            ['Content', 'content', 'manage_options', 'ata-content'],
            ['Scheduler', 'scheduler', 'manage_options', 'ata-scheduler'],
            ['Queue', 'queue', 'manage_options', 'ata-queue'],
            ['AI Providers', 'ai-providers', 'manage_options', 'ata-ai-providers'],
            ['AI Generate', 'ai-generate', 'manage_options', 'ata-ai-generate'],
            ['Channels', 'channels', 'manage_options', 'ata-channels'],
            ['Posts', 'posts', 'manage_options', 'ata-posts'],
            ['Logs', 'logs', 'manage_options', 'ata-logs'],
            // License submenu is registered by LicenseManager::registerMenu()
            // (classic form — works even without the SPA).
            ['Settings', 'settings', 'manage_options', 'ata-settings'],
            ['Dashboard', 'dashboard', 'manage_options', 'ata-dashboard'],
            ['Help', 'help', 'manage_options', 'ata-help'],
        ];

        foreach ($subs as [$label, $page, $cap, $menu]) {
            add_submenu_page(
                $slug,
                $label,
                $label,
                $cap,
                $menu,
                [self::class, 'subpage']
            );
        }

        // Enqueue React SPA assets.
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
    }

    public static function enqueueAssets(string $hook): void
    {
        // Hook suffixes: 'toplevel_page_{slug}' for the parent menu and
        // '{parent}_page_{sub}' for subpages — both must load the SPA.
        if (strpos($hook, 'ata-') !== 0 && strpos($hook, 'page_ata-') === false) {
            return;
        }

        wp_enqueue_script(
            'ata-admin-app',
            ATA_URL . 'includes/Admin/js/admin-app.js',
            ['wp-element', 'wp-components', 'wp-i18n'],
            ATA_VERSION,
            true
        );

        wp_localize_script('ata-admin-app', 'ATA_REST_URL', [
            'url'   => rest_url('ata/v1/'),
            // wp_rest nonce — without it every REST call from the SPA answers 403.
            'nonce' => wp_create_nonce('wp_rest'),
        ]);

        wp_enqueue_style(
            'ata-admin-app',
            ATA_URL . 'includes/Admin/css/admin-style.css',
            [],
            ATA_VERSION
        );
    }

    public static function adminPage(): void
    {
        echo '<div class="wrap"><div id="ata-admin-root"></div></div>';
    }

    public static function subpage(): void
    {
        echo '<div class="wrap"><div id="ata-admin-root"></div></div>';
    }
}