<?php
/**
 * Plugin Name:       AI Telegram Admin
 * Plugin URI:        https://rtl-theme.com/plugins/ai-telegram-admin
 * Description:       مدیریت هوشمند کانال‌ها و محتوای تلگرام با AI — تولید، بازنویسی، ترجمه، خلاصه، زمان‌بندی و صف انتشار.
 * Version:           1.0.0-MVP
 * Author:            RTL-Theme
 * Author URI:        https://rtl-theme.com
 * License:           GPL-2.0+
 * Text Domain:       ata-telegram-ai-admin
 * Domain Path:       /languages
 * Requires PHP:      8.1
 * Requires WP:       6.5
 *
 * @package ATA
 */

defined('ABSPATH') || exit;

define('ATA_VERSION', '1.0.0-MVP');
define('ATA_FILE', __FILE__);
define('ATA_DIR', plugin_dir_path(__FILE__));
define('ATA_URL', plugin_dir_url(__FILE__));
define('ATA_INCLUDES', ATA_DIR . 'includes');
define('ATA_NS', 'ATA');

// Version stamp for DB migrations.
define('ATA_DB_VERSION', '1.0.0');

require_once ATA_INCLUDES . '/Core/Plugin.php';

register_activation_hook(__FILE__, ['ATA\Core_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['ATA\Core_Plugin', 'deactivate']);
register_uninstall_hook(__FILE__, ['ATA\Core_Plugin', 'uninstall']);

ATA\Core_Plugin::instance()->boot();