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

    public function insert(array $row): void
    {
        $this->wpdb->insert($this->table(), [
            'scope'      => $row['scope'] ?? 'system',
            'level'      => $row['level'] ?? 'info',
            'message'    => $row['message'] ?? '',
            'context'    => $row['context'] ?? '',
            'request_id' => $row['request_id'] ?? null,
            'created_at' => current_time('mysql', 1),
        ]);
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

    public function deleteAll(): void
    {
        $this->wpdb->query("TRUNCATE TABLE " . $this->table());
    }
}