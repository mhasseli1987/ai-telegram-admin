<?php
/**
 * Unit test for ATA SecretStore.
 * Tests AES-256-GCM encryption/decryption via public API.
 */
if (!defined('WP_TEST_DIR')) {
    define('WP_TEST_DIR', sys_get_temp_dir() . '/wordpress-tests-lib');
}

class TestSecretStore extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        delete_option('ata_secret_test');
    }

    protected function tearDown(): void
    {
        delete_option('ata_secret_test');
        parent::tearDown();
    }

    public function test_encrypt_decrypt_roundtrip_public_api(): void
    {
        $store = new \ATA\Security\SecretStore();

        $plain = 'super-secret-bot-token-12345';
        $store->set('test', $plain);

        $retrieved = $store->get('test');
        $this->assertEquals($plain, $retrieved);
    }

    public function test_set_get_different_values(): void
    {
        $store = new \ATA\Security\SecretStore();

        $store->set('key1', 'value-1');
        $store->set('key2', 'value-2');

        $this->assertEquals('value-1', $store->get('key1'));
        $this->assertEquals('value-2', $store->get('key2'));
    }

    public function test_get_returns_null_for_missing_key(): void
    {
        $store = new \ATA\Security\SecretStore();

        $this->assertNull($store->get('nonexistent'));
    }
}