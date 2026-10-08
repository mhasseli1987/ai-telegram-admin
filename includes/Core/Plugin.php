<?php
namespace ATA\Core;

use ATA\Container;
use ATA\Admin\MenuProvider;
use ATA\Admin\RestApi;
use ATA\Security\SecretStore;
use ATA\Logging\Logger;
use ATA\Infrastructure\Http\WpHttpTransport;
use ATA\Infrastructure\WpDb\Installer;
use ATA\Infrastructure\WpDb\LogRepository;
use ATA\Settings\SettingsRegistry;
use ATA\Settings\SettingsService;
use ATA\Core\SettingsRegistrar;
use ATA\Services\ContentService;
use ATA\AI\AIProviderRegistry;
use ATA\AI\OpenAICompatibleProvider;
use ATA\Telegram\BotApiTelegramProvider;
use ATA\Contracts\Telegram\TelegramProviderInterface;
use ATA\Contracts\AI\AIProviderInterface;
use ATA\Contracts\Log\LoggerInterface;
use ATA\Contracts\SecretStoreInterface;
use ATA\Contracts\HttpClientInterface;
use ATA\Cron\Runner;

defined('ABSPATH') || exit;

/**
 * Main plugin bootstrap (Composition Root, D-13).
 * Wires all services into Container and registers WP hooks.
 */
class Plugin
{
    private static ?Plugin $instance = null;

    public static function instance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot(): void
    {
        $this->registerContainer();
        $this->registerHooks();
    }

    private function registerContainer(): void
    {
        $container = Container::instance();

        // Infrastructure singletons.
        $container->singleton(HttpClientInterface::class, fn() => new WpHttpTransport());
        $container->singleton(LoggerInterface::class, fn($c) => new Logger($c->make(LogRepository::class)));
        $container->singleton(SecretStoreInterface::class, fn() => new SecretStore());
        $container->singleton(LogRepository::class, fn() => new LogRepository());

        // Settings.
        $container->singleton(SettingsRegistry::class, fn() => {
            $reg = new SettingsRegistry();
            SettingsRegistrar::register($reg);
            return $reg;
        });
        $container->singleton(SettingsService::class, fn($c) => new SettingsService($c->make(SettingsRegistry::class)));

        // AI Registry + default provider.
        $container->singleton(AIProviderRegistry::class, fn($c) => {
            $reg = new AIProviderRegistry();
            $provider = new OpenAICompatibleProvider(
                $c->make(HttpClientInterface::class),
                $c->make(LoggerInterface::class)
            );
            $reg->register('openai_compatible', $provider, true);
            return $reg;
        });

        // Telegram provider.
        $container->singleton(TelegramProviderInterface::class, fn($c) => 
            new BotApiTelegramProvider(
                $c->make(HttpClientInterface::class),
                $c->make(LoggerInterface::class)
            )
        );

        // Services.
        $container->singleton(ContentService::class, fn($c) => 
            new ContentService(
                $c->make(TelegramProviderInterface::class),
                $c->make(AIProviderInterface::class)
            )
        );
    }

    private function registerHooks(): void
    {
        // Admin menu.
        add_action('admin_menu', [MenuProvider::class, 'register']);

        // REST API.
        RestApi::register();

        // DB install.
        register_activation_hook(ATA_FILE, [self::class, 'activate']);
        register_deactivation_hook(ATA_FILE, [self::class, 'deactivate']);
        register_uninstall_hook(ATA_FILE, [self::class, 'uninstall']);

        // Cron queue runner.
        add_action('ata_due_queue', [Runner::class, 'handle']);

        // Assets.
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);

        // Clear expired locks periodically.
        add_action('wp', [Runner::class, 'clearExpiredLocks']);

        // Text domain.
        load_plugin_textdomain('ata-telegram-ai-admin', false, dirname(plugin_basename(ATA_FILE)) . '/languages');
    }

    public function enqueueAssets(): void
    {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'ata') === false) {
            return;
        }

        wp_enqueue_style('ata-admin', ATA_URL . 'assets/css/admin.css', [], ATA_VERSION);
        wp_enqueue_script('ata-admin', ATA_URL . 'assets/js/admin.js', ['wp-element', 'wp-api-fetch', 'wp-components'], ATA_VERSION, true);

        // Localize REST root + nonce.
        wp_localize_script('ata-admin', 'ataRest', [
            'root' => rest_url('ata/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }

    public static function activate(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $installer = new Installer();
        $installer->install();

        // Create custom post type for posts (ata_post).
        self::registerPostType();

        // Flush rewrite rules.
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        // Clear scheduled cron events.
        wp_clear_scheduled_hook('ata_due_queue');
        flush_rewrite_rules();
    }

    public static function uninstall(): void
    {
        // Remove cron.
        wp_clear_scheduled_hook('ata_due_queue');

        // Delete tables and options.
        $installer = new Installer();
        $installer->uninstall();

        // Delete custom post type posts.
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'ata_post'");
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id IN (SELECT id FROM {$wpdb->posts} WHERE post_type = 'ata_post')");
    }

    private static function registerPostType(): void
    {
        $labels = [
            'name' => 'پست‌های ATA',
            'singular_name' => 'پست ATA',
            'add_new' => 'افزودن',
            'add_new_item' => 'پست جدید',
            'edit_item' => 'ویرایش',
            'new_item' => 'پست جدید',
            'view_item' => 'مشاهده',
            'search_items' => 'جستجو',
            'not_found' => 'یافت نشد',
            'not_found_in_trash' => 'در زباله‌دان یافت نشد',
        ];

        register_post_type('ata_post', [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false, // we use our own menu
            'capability_type' => 'post',
            'supports' => ['title', 'editor'],
            'has_archive' => false,
            'rewrite' => false,
            'menu_icon' => 'dashicons-admin-site',
        ]);
    }
}