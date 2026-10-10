<?php
/**
 * Unit test for ATA Logger.
 * Tests structured logging with redaction.
 */
if (!defined('WP_TEST_DIR')) {
    define('WP_TEST_DIR', sys_get_temp_dir() . '/wordpress-tests-lib');
}

class TestLogger extends WP_Unit_Test_Case
{
    public function test_redaction_strips_sensitive_data(): void
    {
        $logger = new \ATA\Logging\Logger();

        $result = $logger->log([
            'user_id' => 123,
            'api_key' => 'sk-live-abc123secret',
            'action'  => 'test_action',
        ]);

        $this->assertStringNotContainsString('sk-live-abc123secret', $result);
        $this->assertStringNotContainsString('secret', $result);
        $this->assertStringContainsString('user_id', $result);
        $this->assertStringContainsString('action', $result);
    }

    public function test_log_returns_string(): void
    {
        $logger = new \ATA\Logging\Logger();

        $result = $logger->log(['test' => 'data']);

        $this->assertIsString($result);
    }
}