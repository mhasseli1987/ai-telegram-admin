<?php
namespace ATA\Infrastructure\WpDb;

use wpdb;

defined('ABSPATH') || exit;

/**
 * Versioned DB schema (D-2, SPECIFICATION.md:512-521).
 * Uses dbDelta for install/upgrade. Never drops columns.
 */
class Installer
{
    private wpdb $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function install(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $this->wpdb->get_charset_collate();
        $table = $this->wpdb->prefix . 'ata_posts';
        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            body longtext,
            image_id bigint(20) DEFAULT NULL,
            channel_id bigint(20) NOT NULL,
            status varchar(32) NOT NULL DEFAULT 'draft',
            scheduled_at datetime DEFAULT NULL,
            published_at datetime DEFAULT NULL,
            attempts int(11) NOT NULL DEFAULT 0,
            last_error_code varchar(64) DEFAULT NULL,
            request_id varchar(64) DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY status_idx (status),
            KEY status_scheduled_idx (status, scheduled_at),
            KEY channel_id_idx (channel_id)
        ) $charset;";

        dbDelta($sql);

        $table = $this->wpdb->prefix . 'ata_channels';
        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            chat_id bigint(20) NOT NULL,
            username varchar(255) DEFAULT NULL,
            title varchar(255) NOT NULL,
            chat_type varchar(64) NOT NULL,
            status varchar(32) NOT NULL DEFAULT 'connected',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY chat_id_idx (chat_id)
        ) $charset;";

        dbDelta($sql);

        $table = $this->wpdb->prefix . 'ata_providers';
        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            type varchar(32) NOT NULL,
            driver varchar(64) NOT NULL,
            name varchar(255) NOT NULL,
            is_default tinyint(1) NOT NULL DEFAULT 0,
            config_json longtext,
            secret_ref varchar(128) DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY type_idx (type)
        ) $charset;";

        dbDelta($sql);

        $table = $this->wpdb->prefix . 'ata_queue';
        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_type varchar(128) NOT NULL,
            payload longtext,
            status varchar(32) NOT NULL DEFAULT 'pending',
            attempts int(11) NOT NULL DEFAULT 0,
            max_attempts int(11) NOT NULL DEFAULT 5,
            next_run_at datetime NOT NULL,
            locked_at datetime DEFAULT NULL,
            lock_token varchar(64) DEFAULT NULL,
            last_error text,
            request_id varchar(64) DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY status_next_idx (status, next_run_at),
            KEY job_type_idx (job_type)
        ) $charset;";

        dbDelta($sql);

        $table = $this->wpdb->prefix . 'ata_logs';
        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            scope varchar(64) NOT NULL DEFAULT 'system',
            level varchar(16) NOT NULL DEFAULT 'info',
            message text,
            context longtext,
            request_id varchar(64) DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY scope_created_idx (scope, created_at),
            KEY level_idx (level)
        ) $charset;";

        dbDelta($sql);

        update_option('ata_db_version', ATA_DB_VERSION);
    }

    public function uninstall(): void
    {
        $tables = [
            $this->wpdb->prefix . 'ata_posts',
            $this->wpdb->prefix . 'ata_channels',
            $this->wpdb->prefix . 'ata_providers',
            $this->wpdb->prefix . 'ata_queue',
            $this->wpdb->prefix . 'ata_logs',
        ];
        foreach ($tables as $t) {
            $this->wpdb->query("DROP TABLE IF EXISTS $t");
        }

        // Remove options.
        $this->wpdb->query(
            "SELECT option_name FROM {$this->wpdb->options} WHERE option_name LIKE 'ata\\_%'"
        );
        $like = $this->wpdb->prefix . 'ata\_%';
        $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->wpdb->options} WHERE option_name LIKE %s",
                $like
            )
        );
        // Also remove secret options.
        $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->wpdb->options} WHERE option_name LIKE %s",
                $this->wpdb->prefix . 'ata_secret\_%'
            )
        );

        delete_option('ata_db_version');
    }
}