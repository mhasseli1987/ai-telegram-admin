<?php
namespace ATA\License;

defined('ABSPATH') || exit;

/**
 * License activation / verification (Phase 11).
 * D-10: Fail-Open with grace period.
 */
class LicenseManager
{
    private const OPTION_KEY = 'ata_license';
    private const GRACE_DAYS = 30;
    private const VERIFY_URL = 'https://rtl-theme.com/verify-license.php';

    /** @return array<string,mixed> */
    public static function status(): array
    {
        $lic = get_option(self::OPTION_KEY, []);
        if (!is_array($lic)) {
            $lic = [];
        }

        $valid = !empty($lic['key']) && !empty($lic['expires']);
        $expired = $valid && (time() > strtotime($lic['expires']));
        $grace = $valid && !$expired && (time() > strtotime('-' . self::GRACE_DAYS . ' days', strtotime($lic['expires'])));

        return [
            'activated' => (bool) ($lic['activated'] ?? false),
            'valid'      => $valid && !$expired,
            'expired'    => $expired,
            'grace'      => $grace,
            'key'        => $lic['key'] ?? '',
            'expires'    => $lic['expires'] ?? '',
            'product'    => $lic['product'] ?? 'ai-telegram-admin',
        ];
    }

    /**
     * D-10 fail-open gate: features stay available unless a license was
     * activated and has fully expired. Not-activated installs fail open;
     * expired installs are cut off.
     */
    public static function failOpen(): bool
    {
        $s = self::status();
        return !$s['activated'] || $s['valid'];
    }

    /**
     * Verify $key against the license server and store the result.
     * @return array{success:bool, message:string}
     */
    public static function activate(string $key): array
    {
        $key = trim($key);
        if ($key === '') {
            return ['success' => false, 'message' => 'کلید لایسنس نمی‌تواند خالی باشد.'];
        }

        try {
            $http = \ATA\Core\Container::instance()->make(\ATA\Contracts\HttpClientInterface::class);
            $resp = $http->request(self::VERIFY_URL, [
                'method'  => 'POST',
                'body'    => ['license_key' => $key, 'product' => 'ai-telegram-admin'],
                'timeout' => 15,
            ]);
        } catch (\Throwable) {
            return ['success' => false, 'message' => 'خطا در اتصال به سرور لایسنس.'];
        }

        if (($resp['status'] ?? 0) !== 200) {
            return ['success' => false, 'message' => 'خطا در اتصال به سرور لایسنس.'];
        }

        $data = json_decode((string) ($resp['body'] ?? ''), true);
        if (!is_array($data) || empty($data['valid'])) {
            $extra = isset($data['message']) ? ' (' . $data['message'] . ')' : '';
            return ['success' => false, 'message' => 'کلید لایسنس نامعتبر است.' . $extra];
        }

        update_option(self::OPTION_KEY, [
            'key'       => $key,
            'activated' => true,
            'expires'   => (string) ($data['expires'] ?? ''),
            'product'   => (string) ($data['product'] ?? 'ai-telegram-admin'),
        ], false);

        return ['success' => true, 'message' => 'لایسنس فعال شد.'];
    }

    /** Remove the stored license entirely. @return array{success:bool, message:string} */
    public static function deactivate(): array
    {
        delete_option(self::OPTION_KEY);
        return ['success' => true, 'message' => 'لایسنس غیرعالی شد.'];
    }

    /** Admin page for license management. */
    public static function adminPage(): void
    {
        $status = self::status();
        $message = '';
        if (isset($_POST['ata_license_activate'])) {
            $key = isset($_POST['ata_license_key']) ? trim($_POST['ata_license_key']) : '';
            $result = self::activate($key);
            $message = $result['message'];
        }
        if (isset($_POST['ata_license_deactivate'])) {
            self::deactivate();
            $message = 'لایسنس غیرعالی شد.';
        }
        ?>
        <div class="wrap">
            <h2>لایسنس ATA: AI Telegram Admin</h2>
            <?php if ($message): ?>
                <div class="notice notice-<?php echo $status['valid'] ? 'success' : ($status['grace'] ? 'notice' : 'error'); ?>">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>
            <form method="post" action="">
                <?php wp_nonce_field('ata_license_action', 'ata_license_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="ata_license_key">کلید لایسنس</label></th>
                        <td><input type="text" name="ata_license_key" id="ata_license_key" class="large-text" value="<?php echo esc_attr($status['key']); ?>" required></td>
                    </tr>
                </table>
                <?php if (!$status['activated']): ?>
                    <p class="submit"><input type="submit" name="ata_license_activate" id="ata_license_activate" class="button button-primary" value="فعال کردن"></p>
                <?php else: ?>
                    <p class="submit"><input type="submit" name="ata_license_deactivate" id="ata_license_deactivate" class="button" value="حذف لایسنس"></p>
                <?php endif; ?>
            </form>
            <p><strong>وضعیت:</strong> <?php echo $status['activated'] ? 'فعال' : ($status['grace'] ? 'بازگشت به حالتfail-open ('.$status['grace'].' روز باقی مانده)' : 'نا فعال'); ?></p>
            <p><strong>انقضا:</strong> <?php echo $status['expires'] ?? 'بدون تاریخ'; ?></p>
        </div>
        <?php
    }

    /** Check status and show admin notice if needed. */
    public static function maybeCheckStatus(): void
    {
        $s = self::status();
        if (!$s['activated'] && !$s['valid']) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-warning is-dismissible"><p>لایسنس فعال نشده است. از بخش "لایسنس" فعال کنید.</p></div>';
            });
        }
    }
}