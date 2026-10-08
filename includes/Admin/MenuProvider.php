<?php
namespace ATA\Admin;

use ATA\Core\Container;
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
            ['License', 'license', 'manage_options', 'ata-license'],
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
    }

    public static function adminPage(): void
    {
        echo '<div class="wrap"><h1>ATA: AI Telegram Admin</h1>';
        echo '<p>پلاگین مدیریت هوشمند کانال‌ها و محتوای تلگرام با AI</p>';
        echo '</div>';
    }

    public static function subpage(): void
    {
        echo '<div class="wrap"><h2>ATA: Admin Subpage</h2>';
        echo '<p>وارد بخش مورد نظر شوید.</p>';
        echo '</div>';
    }
}