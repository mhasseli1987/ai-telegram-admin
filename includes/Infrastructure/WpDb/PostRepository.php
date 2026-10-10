<?php
namespace ATA\Infrastructure\WpDb;

use wpdb;

defined('ABSPATH') || exit;

/**
 * Repository for the ata_posts table (single source of truth for posts,
 * aligned with Installer + Cron\Runner; replaces the conflicting ata_post CPT path).
 */
class PostRepository
{
    private wpdb $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    private function table(): string
    {
        return $this->wpdb->prefix . 'ata_posts';
    }

    public function insert(array $row): int
    {
        $row = wp_parse_args($row, [
            'title'           => '',
            'body'            => '',
            'image_id'        => null,
            'channel_id'      => 0,
            'status'          => 'draft',
            'scheduled_at'    => null,
            'published_at'    => null,
            'attempts'        => 0,
            'last_error_code' => null,
            'request_id'      => null,
        ]);
        $now = current_time('mysql', 1); // UTC.
        $row['created_at'] = $now;
        $row['updated_at'] = $now;

        // wpdb->insert with default formatting casts null to '' which breaks
        // datetime/nullable columns; drop nulls so the column default (NULL)
        // applies instead.
        $row = array_filter($row, static fn($v) => $v !== null);

        $this->wpdb->insert($this->table(), $row);
        return (int) $this->wpdb->insert_id;
    }

    public function find(int $id): ?array
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function update(int $id, array $fields): bool
    {
        $allowed = [
            'title', 'body', 'image_id', 'channel_id', 'status',
            'scheduled_at', 'published_at', 'attempts', 'last_error_code', 'request_id',
        ];
        $fields = array_intersect_key($fields, array_flip($allowed));
        if (!$fields) {
            return false;
        }
        $fields['updated_at'] = current_time('mysql', 1); // UTC.
        return false !== $this->wpdb->update($this->table(), $fields, ['id' => $id]);
    }

    /** @return array<string,int> status => count */
    public function countByStatus(): array
    {
        $rows = $this->wpdb->get_results(
            "SELECT status, COUNT(*) AS c FROM {$this->table()} GROUP BY status",
            ARRAY_A
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['status']] = (int) $r['c'];
        }
        return $out;
    }
}
