<?php
declare(strict_types=1);
namespace Voelgoed\PlatformCore\Person;
defined('ABSPATH') || exit;

final class PersonRelationshipSchema
{
    public const SCHEMA_VERSION = 1;
    public const VERSION_OPTION = 'vg_person_relationship_index_schema_version';

    public function table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'vg_person_relationship_index';
    }

    public function is_ready(): bool
    {
        global $wpdb;
        try {
            return (int) get_option(self::VERSION_OPTION, 0) === self::SCHEMA_VERSION
                && $this->schema_matches_database();
        } catch (\Throwable) {
            return false;
        }
    }

    public function install(): true|\WP_Error
    {
        global $wpdb;
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        $collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$this->table_name()} (\n"
            . "id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . "source_post_id BIGINT UNSIGNED NOT NULL,\n"
            . "source_post_type VARCHAR(32) NOT NULL,\n"
            . "person_id BIGINT UNSIGNED NOT NULL,\n"
            . "role_key VARCHAR(64) NOT NULL,\n"
            . "position INT UNSIGNED NOT NULL DEFAULT 0,\n"
            . "PRIMARY KEY  (id),\n"
            . "UNIQUE KEY source_person_role (source_post_id,person_id,role_key),\n"
            . "KEY person_source_type (person_id,source_post_type),\n"
            . "KEY person_role (person_id,role_key),\n"
            . "KEY source_post (source_post_id)\n"
            . ") ENGINE=InnoDB {$collate};";
        try {
            dbDelta($sql);
            if (! $this->schema_matches_database()) {
                return new \WP_Error('person_relationship_schema_failed', 'The relationship index table does not match the required columns, indexes, or InnoDB engine.');
            }
            update_option(self::VERSION_OPTION, self::SCHEMA_VERSION, false);
            if ((int) get_option(self::VERSION_OPTION, 0) !== self::SCHEMA_VERSION) {
                return new \WP_Error('person_relationship_schema_version_failed', 'The relationship index schema version did not persist.');
            }
        } catch (\Throwable $e) {
            return new \WP_Error('person_relationship_schema_failed', $e->getMessage());
        }
        return true;
    }

    private function table_exists(): bool
    {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->table_name())) === $this->table_name();
    }

    private function schema_matches_database(): bool
    {
        global $wpdb;
        if (! $this->table_exists()) return false;
        $table = '`' . str_replace('`', '``', $this->table_name()) . '`';
        $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $this->table_name()));
        if (! is_object($status) || strtolower((string) ($status->Engine ?? '')) !== 'innodb') return false;

        $columns = $wpdb->get_results("SHOW FULL COLUMNS FROM {$table}");
        if (! is_array($columns)) return false;
        $actual_columns = [];
        foreach ($columns as $column) {
            $column = (array) $column;
            if (! isset($column['Field'], $column['Type'], $column['Null'])) return false;
            $type = strtolower((string) $column['Type']);
            $type = preg_replace('/\\b(bigint|int)\\([0-9]+\\)/', '$1', $type) ?? $type;
            $actual_columns[(string) $column['Field']] = [
                'type' => $type,
                'null' => strtoupper((string) $column['Null']),
                'default' => ! array_key_exists('Default', $column) || $column['Default'] === null ? null : (string) $column['Default'],
                'extra' => strtolower((string) ($column['Extra'] ?? '')),
            ];
        }
        $expected_columns = [
            'id' => ['type' => 'bigint unsigned', 'null' => 'NO', 'default' => null, 'extra' => 'auto_increment'],
            'source_post_id' => ['type' => 'bigint unsigned', 'null' => 'NO', 'default' => null, 'extra' => ''],
            'source_post_type' => ['type' => 'varchar(32)', 'null' => 'NO', 'default' => null, 'extra' => ''],
            'person_id' => ['type' => 'bigint unsigned', 'null' => 'NO', 'default' => null, 'extra' => ''],
            'role_key' => ['type' => 'varchar(64)', 'null' => 'NO', 'default' => null, 'extra' => ''],
            'position' => ['type' => 'int unsigned', 'null' => 'NO', 'default' => '0', 'extra' => ''],
        ];
        if ($actual_columns !== $expected_columns) return false;

        $index_rows = $wpdb->get_results("SHOW INDEX FROM {$table}");
        if (! is_array($index_rows)) return false;
        $actual_indexes = [];
        foreach ($index_rows as $index) {
            $index = (array) $index;
            if (! isset($index['Key_name'], $index['Column_name'], $index['Non_unique'], $index['Seq_in_index'])) return false;
            $actual_indexes[(string) $index['Key_name']][(int) $index['Seq_in_index']] = (string) $index['Column_name'];
            $actual_indexes[(string) $index['Key_name']]['unique'] = (int) $index['Non_unique'] === 0;
        }
        foreach ($actual_indexes as &$index) {
            $unique = $index['unique'];
            unset($index['unique']);
            ksort($index, SORT_NUMERIC);
            $index = ['columns' => array_values($index), 'unique' => $unique];
        }
        unset($index);
        $expected_indexes = [
            'PRIMARY' => ['columns' => ['id'], 'unique' => true],
            'source_person_role' => ['columns' => ['source_post_id', 'person_id', 'role_key'], 'unique' => true],
            'person_source_type' => ['columns' => ['person_id', 'source_post_type'], 'unique' => false],
            'person_role' => ['columns' => ['person_id', 'role_key'], 'unique' => false],
            'source_post' => ['columns' => ['source_post_id'], 'unique' => false],
        ];
        ksort($actual_indexes);
        ksort($expected_indexes);
        return $actual_indexes === $expected_indexes;
    }
}
