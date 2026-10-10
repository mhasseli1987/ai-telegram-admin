<?php
namespace ATA\License;

use ATA\Contracts\HttpClientInterface;
use ATA\Core\Container;

defined('ABSPATH') || exit;

/**
 * Phase 15 — Update Check / Version Check / Secure Update.
 *
 * Fail-safe by design (the SPEC's hard rule: implementation must never harm
 * the customer site):
 *  - Any server/API error silently leaves WP's native update flow untouched.
 *  - Updates are only OFFERED in the admin; they are never auto-installed.
 *  - The download gate only ever applies to packages served from our own
 *    hosts; third-party packages (including wordpress.org) pass untouched.
 *  - Packages from our hosts must be HTTPS and require a valid license.
 */
class Updater
{
    private const CHECK_TRANSIENT = 'ata_update_check';
    private const CACHE_TTL = 12 * HOUR_IN_SECONDS;
    private const SLUG = 'ata-telegram-ai-admin';
    /** Hosts whose package URLs are gated (HTTPS + valid license). */
    private const PACKAGE_HOSTS = ['rtl-theme.com', 'www.rtl-theme.com'];

    public static function register(): void
    {
        add_filter('pre_set_site_transient_update_plugins', [self::class, 'injectUpdate']);
        add_filter('plugins_api', [self::class, 'pluginInfo'], 20, 3);
        add_filter('upgrader_pre_download', [self::class, 'gateDownload'], 10, 3);
    }

    /**
     * Latest release from the update server, cached 12h.
     * @return array{version:string,url:string,package:string,requires:string,requires_php:string,changelog:string}|null
     */
    public static function check(): ?array
    {
        $cached = get_transient(self::CHECK_TRANSIENT);
        if (is_array($cached)) {
            return $cached;
        }

        $info = self::fetch();
        if ($info !== null) {
            set_transient(self::CHECK_TRANSIENT, $info, self::CACHE_TTL);
        }
        return $info;
    }

    /** Live (uncached) fetch — null on ANY failure. */
    public static function fetch(): ?array
    {
        try {
            $http = Container::instance()->make(HttpClientInterface::class);
            $resp = $http->request(LicenseManager::UPDATE_CHECK_URL, [
                'method'  => 'POST',
                'body'    => [
                    'license_key' => (string) (LicenseManager::status()['key'] ?? ''),
                    'product'     => LicenseManager::PRODUCT,
                    'domain'      => LicenseManager::currentDomain(),
                    'version'     => defined('ATA_VERSION') ? ATA_VERSION : '',
                ],
                'timeout' => 10,
            ]);
        } catch (\Throwable) {
            return null;
        }

        if (($resp['status'] ?? 0) !== 200) {
            return null;
        }

        $data = json_decode((string) ($resp['body'] ?? ''), true);
        if (!is_array($data) || !isset($data['version']) || !is_string($data['version']) || $data['version'] === '') {
            return null;
        }

        $scheme = strtolower((string) parse_url((string) ($data['package'] ?? ''), PHP_URL_SCHEME));
        if ($data['package'] !== '' && $scheme !== 'https') {
            return null; // never point WP at an insecure package
        }

        return [
            'version'      => $data['version'],
            'url'          => (string) ($data['url'] ?? 'https://rtl-theme.com/plugins/ai-telegram-admin'),
            'package'      => (string) ($data['package'] ?? ''),
            'requires'     => (string) ($data['requires'] ?? '6.5'),
            'requires_php' => (string) ($data['requires_php'] ?? '8.1'),
            'changelog'    => (string) ($data['changelog'] ?? ''),
        ];
    }

    /**
     * Version check: inject the update into WP's transient when a newer
     * release exists. @param mixed $transient
     * @return mixed
     */
    public static function injectUpdate($transient)
    {
        if (!is_object($transient) || !isset($transient->response) || !is_array($transient->response)) {
            return $transient;
        }

        $info = self::check();
        if ($info === null || $info['package'] === '') {
            return $transient;
        }
        if (!version_compare(defined('ATA_VERSION') ? ATA_VERSION : '0', $info['version'], '<')) {
            return $transient; // already on the latest version
        }

        $transient->response[plugin_basename(ATA_FILE)] = (object) [
            'slug'         => self::SLUG,
            'plugin'       => plugin_basename(ATA_FILE),
            'new_version'  => $info['version'],
            'url'          => $info['url'],
            'package'      => $info['package'],
            'requires'     => $info['requires'],
            'requires_php' => $info['requires_php'],
        ];

        return $transient;
    }

    /**
     * "View details" modal on the Plugins screen. @return mixed
     */
    public static function pluginInfo($result, $action = 'plugin_information', $args = null)
    {
        if ($action !== 'plugin_information' || ($args->slug ?? '') !== self::SLUG) {
            return $result;
        }

        $info = self::check();
        if ($info === null) {
            return $result;
        }

        return (object) [
            'name'            => 'AI Telegram Admin (آتا)',
            'slug'            => self::SLUG,
            'version'         => $info['version'],
            'requires'        => $info['requires'],
            'requires_php'    => $info['requires_php'],
            'download_link'   => $info['package'],
            'homepage'        => $info['url'],
            'sections'        => [
                'description' => 'مدیریت هوشمند کانال‌ها و محتوای تلگرام با AI.',
                'changelog'   => $info['changelog'] !== '' ? $info['changelog'] : 'برای تغییرات، صفحه محصول را ببینید.',
            ],
        ];
    }

    /**
     * Secure Update gate. Only intercepts OUR packages; everything else
     * passes straight through. @return mixed
     */
    public static function gateDownload($reply, $package = '', $upgrader = null)
    {
        $url = (string) $package;
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || !in_array($host, self::PACKAGE_HOSTS, true)) {
            return $reply; // not our package — never interfere
        }

        if (strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            return new \WP_Error('ata_insecure_package', 'دانلود بسته ATA فقط از طریق HTTPS مجاز است.');
        }

        if (!LicenseManager::failOpen()) {
            return new \WP_Error('ata_license_required', 'برای دریافت به‌روزرسانی، لایسنس فعال و معتبر لازم است.');
        }

        return $reply;
    }
}
