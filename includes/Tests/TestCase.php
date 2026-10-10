<?php
/**
 * Test case base for ATA unit tests.
 * PHPUnit compatible.
 */
if (!defined('WP_TEST_DIR')) {
    define('WP_TEST_DIR', sys_get_temp_dir() . '/wordpress-tests-lib');
}
define('TEST_EMAIL', 'test@example.com');

class TestCase extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->factory->post->create([
            'post_status' => 'publish',
            'post_title'  => 'Test Post',
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}