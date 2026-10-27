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
                && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->table_name())) === $this->table_name();
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
            if (! $this->table_exists()) return new \WP_Error('person_relationship_schema_failed', 'The relationship index table was not created.');
            update_option(self::VERSION_OPTION, self::SCHEMA_VERSION, false);
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
}
