<?php
/**
 * Unit test for ATA LicenseManager.
 * Tests license activation/deactivation logic.
 */
if (!defined('WP_TEST_DIR')) {
    define('WP_TEST_DIR', sys_get_temp_dir() . '/wordpress-tests-lib');
}

class TestLicenseManager extends WP_Unit_Test_Case
{
    protected function setUp(): void
    {
        parent::setUp();
        delete_option('ata_license');
    }

    protected function tearDown(): void
    {
        delete_option('ata_license');
        parent::tearDown();
    }

    public function test_status_returns_default_when_no_license(): void
    {
        $status = \ATA\License\LicenseManager::status();

        $this->assertFalse($status['activated']);
        $this->assertFalse($status['valid']);
        $this->assertFalse($status['expired']);
        $this->assertFalse($status['grace']);
    }

    public function test_activate_fails_on_empty_key(): void
    {
        $result = \ATA\License\LicenseManager::activate('');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('خالی', $result['message']);
    }

    public function test_deactivate_removes_option(): void
    {
        update_option('ata_license', ['key' => 'test', 'activated' => true]);

        $result = \ATA\License\LicenseManager::deactivate();

        $this->assertTrue($result['success']);
        $this->assertFalse(get_option('ata_license', false));
    }

    public function test_fail_open_returns_true_when_not_activated(): void
    {
        $this->assertTrue(\ATA\License\LicenseManager::failOpen());
    }
}