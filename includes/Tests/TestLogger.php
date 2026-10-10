<?php
/**
 * Unit test for ATA Logger.
 * Tests structured logging with redaction.
 */
class TestLogger extends WP_UnitTestCase
{
    public function test_redaction_strips_sensitive_data(): void
    {
        $context = [
            'user_id' => 123,
            'api_key' => 'sk-live-abc123secret',
            'action'  => 'test_action',
        ];

        $result = \ATA\Logging\Logger::redact($context);

        $this->assertNotEquals('sk-live-abc123secret', $result['api_key']);
        $this->assertEquals('***', $result['api_key']);
        $this->assertEquals(123, $result['user_id']);
        $this->assertEquals('test_action', $result['action']);
    }

    public function test_redaction_strips_bearer_token(): void
    {
        $context = [
            'authorization' => 'Bearer sk-test123456789',
            'message'       => 'API call',
        ];

        $result = \ATA\Logging\Logger::redact($context);

        $this->assertEquals('***', $result['authorization']);
    }

    public function test_redaction_preserves_non_secrets(): void
    {
        $context = [
            'normal_key' => 'normal_value',
            'count'      => 42,
            'nested'     => [
                'inner' => 'data',
            ],
        ];

        $result = \ATA\Logging\Logger::redact($context);

        $this->assertEquals('normal_value', $result['normal_key']);
        $this->assertEquals(42, $result['count']);
        $this->assertEquals(['inner' => 'data'], $result['nested']);
    }

    public function test_isSecretKey_patterns_via_redact(): void
    {
        // isSecretKey() is private; verify its behavior through the public redact().
        $result = \ATA\Logging\Logger::redact([
            'api_key'       => 'A',
            'apikey'        => 'B',
            'bot_token'     => 'C',
            'secret'        => 'D',
            'authorization' => 'E',
            'password'      => 'F',
            'username'      => 'G',
            'normal_field'  => 'H',
        ]);

        $this->assertEquals('***', $result['api_key']);
        $this->assertEquals('***', $result['apikey']);
        $this->assertEquals('***', $result['bot_token']);
        $this->assertEquals('***', $result['secret']);
        $this->assertEquals('***', $result['authorization']);
        $this->assertEquals('***', $result['password']);
        $this->assertEquals('G', $result['username']);
        $this->assertEquals('H', $result['normal_field']);
    }
}
