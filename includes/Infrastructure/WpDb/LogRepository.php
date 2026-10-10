<?php
namespace ATA\Infrastructure\WpDb;

use wpdb;

defined('ABSPATH') || exit;

class LogRepository
{
    private wpdb $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    protected function table(): string
    {
        return $this->wpdb->prefix . 'ata_logs';
    }

    /** @return int inserted row ID. */
    public function insert(array $row): int
    {
        $context = $row['context'] ?? '';
        if (is_array($context)) {
            $context = wp_json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        $this->wpdb->insert($this->table(), [
            'scope'      => $row['scope'] ?? 'system',
            'level'      => $row['level'] ?? 'info',
            'message'    => $row['message'] ?? '',
            'context'    => $context,
            'request_id' => $row['request_id'] ?? null,
            'created_at' => current_time('mysql', 1),
        ]);
        return (int) $this->wpdb->insert_id;
    }

    public function all(int $limit = 100, int $offset = 0, array $filters = []): array
    {
        $table = $this->table();
        $where = '1=1';
        $params = [];

        if (!empty($filters['scope'])) {
            $where .= ' AND scope = %s';
            $params[] = $filters['scope'];
        }
        if (!empty($filters['level'])) {
            $where .= ' AND level = %s';
            $params[] = $filters['level'];
        }

        $sql = "SELECT * FROM $table WHERE $where ORDER BY id DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        return $params
            ? $this->wpdb->get_results($this->wpdb->prepare($sql, ...$params), ARRAY_A)
            : $this->wpdb->get_results($sql, ARRAY_A);
    }

    public function count(array $filters = []): int
    {
        $table = $this->table();
        $where = '1=1';
        $params = [];

        if (!empty($filters['scope'])) {
            $where .= ' AND scope = %s';
            $params[] = $filters['scope'];
        }
        if (!empty($filters['level'])) {
            $where .= ' AND level = %s';
            $params[] = $filters['level'];
        }

        $sql = "SELECT COUNT(*) FROM $table WHERE $where";
        return (int) ($params
            ? $this->wpdb->get_var($this->wpdb->prepare($sql, ...$params))
            : $this->wpdb->get_var($sql));
    }

    public function find(int $id): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare('SELECT * FROM ' . $this->table() . ' WHERE id = %d', $id),
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * Retention policy: delete entries older than $days days. Returns affected rows.
     */
    public function deleteOld(int $days = 30): int
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
        $this->wpdb->query($this->wpdb->prepare(
            'DELETE FROM ' . $this->table() . ' WHERE created_at < %s',
            $cutoff
        ));
        return (int) $this->wpdb->rows_affected;
    }

    public function deleteAll(): void
    {
        $this->wpdb->query("TRUNCATE TABLE " . $this->table());
    }
}