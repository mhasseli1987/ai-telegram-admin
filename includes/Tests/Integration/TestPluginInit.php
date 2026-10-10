<?php
/**
 * Integration Test: Plugin Initialization & DI Container
 * Tests that the plugin bootstraps correctly, registers all services,
 * and the DI container resolves dependencies properly.
 */
require_once __DIR__ . '/bootstrap.php';

class TestPluginInit extends IntegrationTestCase
{
    public function test_container_is_singleton(): void
    {
        $c1 = \ATA\Core\Container::instance();
        $c2 = \ATA\Core\Container::instance();
        $this->assertSame($c1, $c2);
    }

    public function test_container_resolves_core_services(): void
    {
        $container = $this->container;

        // Settings
        $settings = $container->make(\ATA\Settings\SettingsService::class);
        $this->assertInstanceOf(\ATA\Settings\SettingsService::class, $settings);

        $registry = $container->make(\ATA\Settings\SettingsRegistry::class);
        $this->assertInstanceOf(\ATA\Settings\SettingsRegistry::class, $registry);

        // Security
        $secrets = $container->make(\ATA\Contracts\SecretStoreInterface::class);
        $this->assertInstanceOf(\ATA\Security\SecretStore::class, $secrets);

        $ssrf = $container->make(\ATA\Security\SsrfGate::class);
        $this->assertInstanceOf(\ATA\Security\SsrfGate::class, $ssrf);

        // Logging
        $logger = $container->make(\ATA\Contracts\Log\LoggerInterface::class);
        $this->assertInstanceOf(\ATA\Logging\Logger::class, $logger);

        // HTTP
        $http = $container->make(\ATA\Contracts\HttpClientInterface::class);
        $this->assertInstanceOf(\ATA\Contracts\HttpClientInterface::class, $http);

        // AI
        $aiRegistry = $container->make(\ATA\AI\AIProviderRegistry::class);
        $this->assertInstanceOf(\ATA\AI\AIProviderRegistry::class, $aiRegistry);

        $aiProvider = $container->make(\ATA\Contracts\AI\AIProviderInterface::class);
        $this->assertInstanceOf(\ATA\AI\OpenAICompatibleProvider::class, $aiProvider);

        // Telegram
        $telegram = $container->make(\ATA\Contracts\Telegram\TelegramProviderInterface::class);
        $this->assertInstanceOf(\ATA\Telegram\BotApiTelegramProvider::class, $telegram);

        // Services
        $content = $container->make(\ATA\Services\ContentService::class);
        $this->assertInstanceOf(\ATA\Services\ContentService::class, $content);

        // DB
        $postRepo = $container->make(\ATA\Infrastructure\WpDb\PostRepository::class);
        $this->assertInstanceOf(\ATA\Infrastructure\WpDb\PostRepository::class, $postRepo);

        $logRepo = $container->make(\ATA\Infrastructure\WpDb\LogRepository::class);
        $this->assertInstanceOf(\ATA\Infrastructure\WpDb\LogRepository::class, $logRepo);

        // Cron
        $runner = $container->make(\ATA\Cron\Runner::class);
        $this->assertInstanceOf(\ATA\Cron\Runner::class, $runner);

        $scheduler = $container->make(\ATA\Cron\SchedulerAdapter::class);
        $this->assertInstanceOf(\ATA\Cron\SchedulerAdapter::class, $scheduler);

        // License
        $license = $container->make(\ATA\License\LicenseManager::class);
        $this->assertInstanceOf(\ATA\License\LicenseManager::class, $license);
    }

    public function test_container_returns_same_instance_for_singletons(): void
    {
        $logger1 = $this->container->make(\ATA\Contracts\Log\LoggerInterface::class);
        $logger2 = $this->container->make(\ATA\Contracts\Log\LoggerInterface::class);
        $this->assertSame($logger1, $logger2);

        $secrets1 = $this->container->make(\ATA\Contracts\SecretStoreInterface::class);
        $secrets2 = $this->container->make(\ATA\Contracts\SecretStoreInterface::class);
        $this->assertSame($secrets1, $secrets2);
    }

    public function test_plugin_hooks_registered(): void
    {
        // Check that admin_menu hook has our callback
        $hooks = $GLOBALS['wp_filter']['admin_menu']->callbacks ?? [];
        $found = false;
        foreach ($hooks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (isset($callback['function']) &&
                    is_array($callback['function']) &&
                    $callback['function'][0] === \ATA\Admin\MenuProvider::class) {
                    $found = true;
                    break;
                }
            }
        }
        $this->assertTrue($found, 'MenuProvider::register not hooked to admin_menu');
    }

    public function test_rest_api_routes_registered(): void
    {
        // Ensure REST routes are registered
        $routes = rest_get_server()->get_routes();
        $this->assertArrayHasKey('/ata/v1/telegram/connect', $routes);
        $this->assertArrayHasKey('/ata/v1/channels', $routes);
        $this->assertArrayHasKey('/ata/v1/ai/providers', $routes);
        $this->assertArrayHasKey('/ata/v1/ai/generate', $routes);
        $this->assertArrayHasKey('/ata/v1/posts', $routes);
        $this->assertArrayHasKey('/ata/v1/queue', $routes);
        $this->assertArrayHasKey('/ata/v1/logs', $routes);
        $this->assertArrayHasKey('/ata/v1/dashboard', $routes);
        $this->assertArrayHasKey('/ata/v1/settings', $routes);
        $this->assertArrayHasKey('/ata/v1/license/activate', $routes);
    }

    public function test_cron_schedule_ata_minute_exists(): void
    {
        $schedules = wp_get_schedules();
        $this->assertArrayHasKey('ata_minute', $schedules);
        $this->assertEquals(MINUTE_IN_SECONDS, $schedules['ata_minute']['interval']);
    }

    public function test_license_manager_registered(): void
    {
        $license = $this->container->make(\ATA\License\LicenseManager::class);
        $this->assertInstanceOf(\ATA\License\LicenseManager::class, $license);

        // Check admin page hook
        $hooks = $GLOBALS['wp_filter']['admin_menu']->callbacks ?? [];
        $found = false;
        foreach ($hooks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (isset($callback['function']) &&
                    is_array($callback['function']) &&
                    $callback['function'][0] === \ATA\License\LicenseManager::class) {
                    $found = true;
                    break;
                }
            }
        }
        $this->assertTrue($found, 'LicenseManager::adminPage not hooked to admin_menu');
    }

    public function test_scheduler_adapter_registered(): void
    {
        $scheduler = $this->container->make(\ATA\Cron\SchedulerAdapter::class);
        $this->assertInstanceOf(\ATA\Cron\SchedulerAdapter::class, $scheduler);

        // Check init hook
        $hooks = $GLOBALS['wp_filter']['init']->callbacks ?? [];
        $found = false;
        foreach ($hooks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (isset($callback['function']) &&
                    is_array($callback['function']) &&
                    $callback['function'][0] === \ATA\Cron\SchedulerAdapter::class) {
                    $found = true;
                    break;
                }
            }
        }
        $this->assertTrue($found, 'SchedulerAdapter::maybeSwitchToActionScheduler not hooked to init');
    }

    public function test_settings_loaded(): void
    {
        $registry = $this->container->make(\ATA\Settings\SettingsRegistry::class);
        $definitions = $registry->definitions();

        // Should have at least these core settings
        $this->assertArrayHasKey('ata_default_tone', $definitions);
        $this->assertArrayHasKey('ata_default_language', $definitions);
        $this->assertArrayHasKey('ata_license_key', $definitions);
        $this->assertArrayHasKey('ata_log_retention_days', $definitions);
    }

    public function test_secret_store_encryption_key_derivation(): void
    {
        $store = $this->container->make(\ATA\Contracts\SecretStoreInterface::class);

        // Test that we can store and retrieve a secret
        $store->set('test_key', 'test_value_123');
        $value = $store->get('test_key');
        $this->assertEquals('test_value_123', $value);

        // Test has()
        $this->assertTrue($store->has('test_key'));
        $this->assertFalse($store->has('nonexistent'));

        // Test delete()
        $store->delete('test_key');
        $this->assertNull($store->get('test_key'));
    }
}