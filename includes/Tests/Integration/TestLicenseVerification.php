<?php
/**
 * Integration Test: License Verification & Fail-Open Behavior
 * Tests license activation, verification, deactivation, and fail-open grace period.
 */
require_once __DIR__ . '/bootstrap.php';

class TestLicenseVerification extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        delete_option('ata_license');
        parent::tearDown();
    }

    // --------------------------------------------------------------- License Status

    public function test_license_status_default_no_license(): void
    {
        $license = $this->container->make(\ATA\License\LicenseManager::class);
        $status = $license->status();

        $this->assertFalse($status['activated']);
        $this->assertFalse($status['valid']);
        $this->assertFalse($status['expired']);
        $this->assertFalse($status['grace']);
        $this->assertEquals('', $status['key']);
        $this->assertEquals('ai-telegram-admin', $status['product']);
    }

    public function test_license_fail_open_when_not_activated(): void
    {
        $license = $this->container->make(\ATA\License\LicenseManager::class);
        $this->assertTrue($license->failOpen());
    }

    // --------------------------------------------------------------- Activation

    public function test_activate_rejects_empty_key(): void
    {
        $license = $this->container->make(\ATA\License\LicenseManager::class);
        $result = $license->activate('');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('خالی', $result['message']);
    }

    public function test_activate_with_valid_key(): void
    {
        // Mock license server response
        $this->mockHttp->setResponse('POST', '*/verify-license.php', [
            'code'    => 200,
            'body'    => json_encode([
                'valid'   => true,
                'expires' => date('Y-m-d H:i:s', time() + 365 * DAY_IN_SECONDS),
                'product' => 'ai-telegram-admin',
            ]),
            'headers' => ['Content-Type: application/json'],
        ]);

        $license = $this->container->make(\ATA\License\LicenseManager::class);
        $result = $license->activate('VALID-LICENSE-KEY-123');

        $this->assertTrue($result['success']);
        $this->assertEquals('لایسنس فعال شد.', $result['message']);

        // Verify status
        $status = $license->status();
        $this->assertTrue($status['activated']);
        $this->assertTrue($status['valid']);
        $this->assertFalse($status['expired']);
        $this->assertFalse($status['grace']);
        $this->assertEquals('VALID-LICENSE-KEY-123', $status['key']);
    }

    public function test_activate_stores_key_encrypted(): void
    {
        $this->mockHttp->setResponse('POST', '*/verify-license.php', [
            'code'    => 200,
            'body'    => json_encode([
                'valid'   => true,
                'expires' => date('Y-m-d H:i:s', time() + 86400),
            ]),
        ]);

        $license = $this->container->make(\ATA\License\LicenseManager::class);
        $license->activate('SECRET-KEY-123');

        // Verify option stored
        $opt = get_option('ata_license');
        $this->assertNotFalse($opt);
        $this->assertTrue($opt['activated']);
        $this->assertEquals('SECRET-KEY-123', $opt['key']);
    }

    public function test_activate_invalid_key_from_server(): void
    {
        $this->mockHttp->setResponse('POST', '*/verify-license.php', [
            'code' => 200,
            'body' => json_encode([
                'valid'   => false,
                'message' => 'Invalid license key',
            ]),
        ]);

        $license = $this->container->make(\ATA\License\LicenseManager::class);
        $result = $license->activate('INVALID-KEY');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('نامعتبر', $result['message']);
    }

    public function test_activate_server_unreachable(): void
    {
        $this->mockHttp->setResponse('POST', '*/verify-license.php', [
            'code' => 500,
            'body' => 'Internal Server Error',
        ]);

        $license = $this->container->make(\ATA\License\LicenseManager::class);
        $result = $license->activate('TEST-KEY');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('خطا در اتصال', $result['message']);
    }

    // --------------------------------------------------------------- Deactivation

    public function test_deactivate_removes_license(): void
    {
        // First activate
        $this->mockHttp->setResponse('POST', '*/verify-license.php', [
            'code'    => 200,
            'body'    => json_encode(['valid' => true, 'expires' => date('Y-m-d H:i:s', time() + 86400)]),
        ]);

        $license = $this->container->make(\ATA\License\LicenseManager::class);
        $license->activate('TEST-KEY-123');

        // Then deactivate
        $result = $license->deactivate();

        $this->assertTrue($result['success']);
        $this->assertEquals('لایسنس غیرعالی شد.', $result['message']);

        // Verify option removed
        $this->assertFalse(get_option('ata_license', false));
    }

    // --------------------------------------------------------------- Expiration & Grace Period

    public function test_license_expired_status(): void
    {
        // Set expired license directly
        update_option('ata_license', [
            'key'       => 'EXPIRED-KEY',
            'activated' => true,
            'expires'   => date('Y-m-d H:i:s', time() - 86400), // 1 day ago
            'product'   => 'ai-telegram-admin',
        ]);

        $license = $this->container->make(\ATA\License\LicenseManager::class);
        $status = $license->status();

        $this->assertTrue($status['activated']);
        $this->assertFalse($status['valid']);
        $this->assertTrue($status['expired']);
        $this->assertFalse($status['grace']);
    }

    public function test_license_grace_period(): void
    {
        // Set license expiring in 15 days (within 30-day grace)
        update_option('ata_license', [
            'key'       => 'GRACE-KEY',
            'activated' => true,
            'expires'   => date('Y-m-d H:i:s', time() + 15 * DAY_IN_SECONDS),
            'product'   => 'ai-telegram-admin',
        ]);

        $license = $this->container->make(\ATA\License\LicenseManager::class);
        $status = $license->status();

        $this->assertTrue($status['activated']);
        $this->assertTrue($status['valid']); // still valid during grace
        $this->assertFalse($status['expired']);
        $this->assertTrue($status['grace']);
    }

    public function test_fail_open_during_grace(): void
    {
        update_option('ata_license', [
            'key'       => 'GRACE-KEY',
            'activated' => true,
            'expires'   => date('Y-m-d H:i:s', time() + 15 * DAY_IN_SECONDS),
        ]);

        $license = $this->container->make(\ATA\License\LicenseManager::class);
        $this->assertTrue($license->failOpen());
    }

    public function test_fail_open_after_expiration(): void
    {
        update_option('ata_license', [
            'key'       => 'EXPIRED-KEY',
            'activated' => true,
            'expires'   => date('Y-m-d H:i:s', time() - 86400),
        ]);

        $license = $this->container->make(\ATA\License\LicenseManager::class);
        // After expiration, failOpen returns false (plugin should restrict features)
        $this->assertFalse($license->failOpen());
    }

    public function test_grace_period_is_30_days(): void
    {
        $reflection = new ReflectionClass(\ATA\License\LicenseManager::class);
        $constant = $reflection->getConstant('GRACE_DAYS');
        $this->assertEquals(30, $constant);
    }

    // --------------------------------------------------------------- REST API Integration

    public function test_rest_license_activate_endpoint(): void
    {
        $this->mockHttp->setResponse('POST', '*/verify-license.php', [
            'code'    => 200,
            'body'    => json_encode(['valid' => true, 'expires' => date('Y-m-d H:i:s', time() + 86400)]),
        ]);

        $response = $this->restRequest('POST', '/ata/v1/license/activate', [
            'key' => 'REST-API-KEY-123',
        ], $this->adminUserId);

        $this->assertEquals(true, $response['success']);
    }

    public function test_rest_license_deactivate_endpoint(): void
    {
        // Set license first
        update_option('ata_license', [
            'key'       => 'TEST',
            'activated' => true,
            'expires'   => date('Y-m-d H:i:s', time() + 86400),
        ]);

        $response = $this->restRequest('POST', '/ata/v1/license/deactivate', [], $this->adminUserId);

        $this->assertEquals(true, $response['success']);
        $this->assertFalse(get_option('ata_license', false));
    }

    public function test_rest_license_activate_validates_input(): void
    {
        $response = $this->restRequest('POST', '/ata/v1/license/activate', ['key' => ''], $this->adminUserId);

        $this->assertEquals(false, $response['success']);
    }

    // --------------------------------------------------------------- Admin Page

    public function test_license_admin_page_renders(): void
    {
        $license = $this->container->make(\ATA\License\LicenseManager::class);

        // Capture output
        ob_start();
        $license->adminPage();
        $output = ob_get_clean();

        $this->assertStringContainsString('لایسنس', $output);
        $this->assertStringContainsString('کلید لایسنس', $output);
    }

    public function test_license_admin_page_shows_status(): void
    {
        $this->mockHttp->setResponse('POST', '*/verify-license.php', [
            'code'    => 200,
            'body'    => json_encode(['valid' => true, 'expires' => date('Y-m-d H:i:s', time() + 86400)]),
        ]);

        $license = $this->container->make(\ATA\License\LicenseManager::class);
        $license->activate('ADMIN-PAGE-KEY');

        ob_start();
        $license->adminPage();
        $output = ob_get_clean();

        $this->assertStringContainsString('فعال', $output);
        $this->assertStringContainsString('ADMIN-PAGE-KEY', $output);
    }

    // --------------------------------------------------------------- Admin Notice

    public function test_admin_notice_when_no_license(): void
    {
        $license = $this->container->make(\ATA\License\LicenseManager::class);

        // Trigger the maybeCheckStatus hook
        $license->maybeCheckStatus();

        // Capture admin notices
        $notices = $GLOBALS['wp_filter']['admin_notices']->callbacks ?? [];
        $found = false;
        foreach ($notices as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (is_callable($callback['function'])) {
                    ob_start();
                    call_user_func($callback['function']);
                    $output = ob_get_clean();
                    if (str_contains($output, 'لایسنس فعال نشده')) {
                        $found = true;
                    }
                }
            }
        }
        $this->assertTrue($found, 'Admin notice for missing license not found');
    }

    // --------------------------------------------------------------- Edge Cases

    public function test_multiple_activations_overwrite(): void
    {
        $this->mockHttp->setResponse('POST', '*/verify-license.php', [
            'code'    => 200,
            'body'    => json_encode(['valid' => true, 'expires' => date('Y-m-d H:i:s', time() + 86400)]),
        ]);

        $license = $this->container->make(\ATA\License\LicenseManager::class);

        $license->activate('FIRST-KEY');
        $status1 = $license->status();
        $this->assertEquals('FIRST-KEY', $status1['key']);

        $license->activate('SECOND-KEY');
        $status2 = $license->status();
        $this->assertEquals('SECOND-KEY', $status2['key']);
    }

    public function test_verify_url_is_configurable(): void
    {
        $reflection = new ReflectionClass(\ATA\License\LicenseManager::class);
        $constant = $reflection->getConstant('VERIFY_URL');
        $this->assertStringContainsString('rtl-theme.com', $constant);
    }

    public function test_license_product_name(): void
    {
        $this->mockHttp->setResponse('POST', '*/verify-license.php', [
            'code'    => 200,
            'body'    => json_encode(['valid' => true, 'expires' => date('Y-m-d H:i:s', time() + 86400), 'product' => 'custom-product']),
        ]);

        $license = $this->container->make(\ATA\License\LicenseManager::class);
        $license->activate('PRODUCT-TEST-KEY');

        $status = $license->status();
        $this->assertEquals('custom-product', $status['product']);
    }
}