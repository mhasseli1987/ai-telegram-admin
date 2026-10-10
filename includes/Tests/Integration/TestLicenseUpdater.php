<?php
/**
 * Integration Test: Phase 15 — Domain Binding + Update System
 * Tests domain binding, update check, version check, and the secure
 * download gate (all fail-safe: errors must never break the site).
 */
require_once __DIR__ . '/bootstrap.php';

class TestLicenseUpdater extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        delete_transient('ata_update_check');
    }

    protected function tearDown(): void
    {
        delete_transient('ata_update_check');
        delete_option('ata_license');
        parent::tearDown();
    }

    private function mockLicenseServer(array $data = [], int $status = 200): void
    {
        $this->mockHttp->setResponse('POST', '*/verify-license.php', [
            'code' => $status,
            'body' => json_encode($data ?: [
                'valid'   => true,
                'expires' => date('Y-m-d H:i:s', time() + 365 * DAY_IN_SECONDS),
                'product' => 'ai-telegram-admin',
            ]),
        ]);
    }

    private function mockUpdateServer(array $data = [], int $status = 200): void
    {
        $this->mockHttp->setResponse('POST', '*/update-check.php', [
            'code' => $status,
            'body' => json_encode($data),
        ]);
    }

    private function activateValid(): void
    {
        $this->mockLicenseServer(['valid' => true, 'expires' => date('Y-m-d H:i:s', time() + 365 * DAY_IN_SECONDS)]);
        (new \ATA\License\LicenseManager())->activate('VALID-KEY');
    }

    /** Host of the WP test site (WP_TESTS_DOMAIN, lowercased). */
    private function siteDomain(): string
    {
        return strtolower((string) parse_url(home_url(), PHP_URL_HOST));
    }

    // ------------------------------------------------------- Domain binding

    public function test_activate_sends_domain_and_version_to_server(): void
    {
        $this->activateValid();

        $req = $this->getLastRequest('POST', 'verify-license.php');
        $this->assertNotNull($req);
        $body = json_decode($req['body'], true);
        $this->assertSame($this->siteDomain(), $body['domain']); // WP test site host
        $this->assertSame(ATA_VERSION, $body['version']);
    }

    public function test_activate_stores_server_confirmed_domain(): void
    {
        $this->mockLicenseServer(['valid' => true, 'expires' => date('Y-m-d H:i:s', time() + 86400), 'domain' => $this->siteDomain()]);
        (new \ATA\License\LicenseManager())->activate('KEY-1');

        $status = (new \ATA\License\LicenseManager())->status();
        $this->assertSame($this->siteDomain(), $status['domain']);
        $this->assertTrue($status['domain_match']);
    }

    public function test_domain_mismatch_is_flagged_but_never_enforced(): void
    {
        $this->mockLicenseServer(['valid' => true, 'expires' => date('Y-m-d H:i:s', time() + 86400), 'domain' => 'some-other-site.com']);
        (new \ATA\License\LicenseManager())->activate('KEY-2');

        $lm = new \ATA\License\LicenseManager();
        $status = $lm->status();
        $this->assertFalse($status['domain_match']);
        // Fail-safe: mismatch must not cut features off.
        $this->assertTrue($lm->failOpen());
    }

    public function test_unbound_license_always_matches(): void
    {
        update_option('ata_license', [
            'key'       => 'LEGACY',
            'activated' => true,
            'expires'   => date('Y-m-d H:i:s', time() + 86400),
        ]);
        $status = (new \ATA\License\LicenseManager())->status();
        $this->assertTrue($status['domain_match']);
    }

    public function test_status_default_has_empty_domain(): void
    {
        $status = (new \ATA\License\LicenseManager())->status();
        $this->assertSame('', $status['domain']);
        $this->assertTrue($status['domain_match']);
    }

    // ------------------------------------------------------- Update check

    public function test_update_check_parses_server_response(): void
    {
        $this->mockUpdateServer([
            'version'      => '1.1.0',
            'url'          => 'https://rtl-theme.com/plugins/ai-telegram-admin',
            'package'      => 'https://rtl-theme.com/downloads/ata-1.1.0.zip',
            'requires'     => '6.5',
            'requires_php' => '8.1',
            'changelog'    => 'Fixes and improvements',
        ]);

        $info = \ATA\License\Updater::check();
        $this->assertNotNull($info);
        $this->assertSame('1.1.0', $info['version']);
        $this->assertSame('https://rtl-theme.com/downloads/ata-1.1.0.zip', $info['package']);
    }

    public function test_update_check_failsafe_on_server_error(): void
    {
        $this->mockUpdateServer(['error' => 'boom'], 500);
        $this->assertNull(\ATA\License\Updater::check());
    }

    public function test_update_check_failsafe_on_malformed_response(): void
    {
        $this->mockUpdateServer(['foo' => 'bar']);
        $this->assertNull(\ATA\License\Updater::check());
    }

    public function test_update_check_rejects_insecure_package_url(): void
    {
        $this->mockUpdateServer(['version' => '1.1.0', 'package' => 'http://rtl-theme.com/downloads/ata-1.1.0.zip']);
        $this->assertNull(\ATA\License\Updater::fetch());
    }

    public function test_update_check_sends_license_key_and_domain(): void
    {
        $this->activateValid();
        $this->mockUpdateServer(['version' => '1.1.0', 'package' => 'https://rtl-theme.com/downloads/ata-1.1.0.zip']);
        \ATA\License\Updater::check();

        $req = $this->getLastRequest('POST', 'update-check.php');
        $this->assertNotNull($req);
        $body = json_decode($req['body'], true);
        $this->assertSame('VALID-KEY', $body['license_key']);
        $this->assertSame($this->siteDomain(), $body['domain']);
        $this->assertSame(ATA_VERSION, $body['version']);
    }

    // ------------------------------------------------------- Version check / injection

    private function transient(): \stdClass
    {
        $t = new \stdClass();
        $t->response = [];
        return $t;
    }

    public function test_inject_update_when_newer_version_exists(): void
    {
        $this->mockUpdateServer(['version' => '9.9.9', 'package' => 'https://rtl-theme.com/downloads/ata-9.9.9.zip']);
        $t = \ATA\License\Updater::injectUpdate($this->transient());

        $plugin = plugin_basename(ATA_FILE);
        $this->assertArrayHasKey($plugin, $t->response);
        $this->assertSame('9.9.9', $t->response[$plugin]->new_version);
    }

    public function test_no_injection_when_already_latest(): void
    {
        $this->mockUpdateServer(['version' => ATA_VERSION, 'package' => 'https://rtl-theme.com/downloads/ata.zip']);
        $t = \ATA\License\Updater::injectUpdate($this->transient());
        $this->assertSame([], $t->response);
    }

    public function test_no_injection_when_older_version_reported(): void
    {
        $this->mockUpdateServer(['version' => '0.0.1', 'package' => 'https://rtl-theme.com/downloads/ata-0.0.1.zip']);
        $t = \ATA\License\Updater::injectUpdate($this->transient());
        $this->assertSame([], $t->response);
    }

    public function test_injection_failsafe_on_bad_transient(): void
    {
        $this->mockUpdateServer(['version' => '9.9.9', 'package' => 'https://rtl-theme.com/downloads/ata.zip']);
        // A non-object transient is returned untouched.
        $this->assertNull(\ATA\License\Updater::injectUpdate(null));
        // A transient without a response array is returned untouched.
        $bare = new \stdClass();
        $this->assertSame($bare, \ATA\License\Updater::injectUpdate($bare));
    }

    // ------------------------------------------------------- Secure update gate

    public function test_gate_passes_third_party_packages_through(): void
    {
        // Even with a fully expired license, non-ATA packages are untouched.
        update_option('ata_license', [
            'key'       => 'X',
            'activated' => true,
            'expires'   => date('Y-m-d H:i:s', time() - 86400),
        ]);
        $reply = 'passthrough';
        $this->assertSame($reply, \ATA\License\Updater::gateDownload($reply, 'https://downloads.wordpress.org/plugin/akismet.5.3.zip'));
    }

    public function test_gate_blocks_insecure_ata_package(): void
    {
        $this->activateValid();
        $err = \ATA\License\Updater::gateDownload(false, 'http://rtl-theme.com/downloads/ata.zip');
        $this->assertInstanceOf(\WP_Error::class, $err);
    }

    public function test_gate_requires_valid_license_for_ata_package(): void
    {
        update_option('ata_license', [
            'key'       => 'X',
            'activated' => true,
            'expires'   => date('Y-m-d H:i:s', time() - 86400), // expired
        ]);
        $err = \ATA\License\Updater::gateDownload(false, 'https://rtl-theme.com/downloads/ata.zip');
        $this->assertInstanceOf(\WP_Error::class, $err);
        $this->assertSame('ata_license_required', $err->get_error_code());
    }

    public function test_gate_allows_ata_package_with_valid_license(): void
    {
        $this->activateValid();
        $reply = false;
        $this->assertSame($reply, \ATA\License\Updater::gateDownload($reply, 'https://rtl-theme.com/downloads/ata.zip'));
        $reply = 'cached-file-path';
        $this->assertSame($reply, \ATA\License\Updater::gateDownload($reply, 'https://www.rtl-theme.com/downloads/ata.zip'));
    }

    // ------------------------------------------------------- Wiring

    public function test_updater_registers_wp_filters(): void
    {
        \ATA\License\Updater::register();
        $this->assertNotFalse(has_filter('pre_set_site_transient_update_plugins', [\ATA\License\Updater::class, 'injectUpdate']));
        $this->assertNotFalse(has_filter('plugins_api', [\ATA\License\Updater::class, 'pluginInfo']));
        $this->assertNotFalse(has_filter('upgrader_pre_download', [\ATA\License\Updater::class, 'gateDownload']));
    }

    public function test_plugin_info_modal_for_ata_slug(): void
    {
        $this->mockUpdateServer(['version' => '1.1.0', 'package' => 'https://rtl-theme.com/downloads/ata-1.1.0.zip']);
        $args = new \stdClass();
        $args->slug = 'ata-telegram-ai-admin';

        $info = \ATA\License\Updater::pluginInfo(false, 'plugin_information', $args);
        $this->assertInstanceOf(\stdClass::class, $info);
        $this->assertSame('1.1.0', $info->version);

        // Other slugs pass through untouched.
        $args->slug = 'akismet';
        $this->assertFalse(\ATA\License\Updater::pluginInfo(false, 'plugin_information', $args));
    }
}
