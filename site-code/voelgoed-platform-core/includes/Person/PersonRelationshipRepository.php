<?php
declare(strict_types=1);
namespace Voelgoed\PlatformCore\Person;
defined('ABSPATH') || exit;

final class PersonRelationshipRepository
{
    public function __construct(private PersonRelationshipSchema $schema) {}

    public function relationships_for_source(int $source_post_id): array
    {
        if ($source_post_id < 1 || ! $this->schema->is_ready()) return [];
        global $wpdb;
        try { $rows = $wpdb->get_results($wpdb->prepare("SELECT person_id, role_key, position FROM {$this->schema->table_name()} WHERE source_post_id = %d ORDER BY role_key, position, person_id", $source_post_id), ARRAY_A); }
        catch (\Throwable) { return []; }
        if (! is_array($rows) || (string) ($wpdb->last_error ?? '') !== '') return [];
        return array_map(static fn ($row) => ['person_id' => (int) $row['person_id'], 'role_key' => (string) $row['role_key'], 'position' => (int) $row['position']], $rows);
    }

    public function source_ids_for_person(int $person_id, string $source_post_type, array $role_keys = []): array
    {
        if ($person_id < 1 || ! PersonRelationshipContract::supported_source_type($source_post_type) || ! $this->schema->is_ready()) return [];
        $allowed = PersonRelationshipContract::allowed_roles($source_post_type);
        if ($role_keys && array_diff($role_keys, $allowed)) return [];
        global $wpdb;
        $sql = "SELECT DISTINCT source_post_id FROM {$this->schema->table_name()} WHERE person_id = %d AND source_post_type = %s";
        $args = [$person_id, $source_post_type];
        if ($role_keys) { $sql .= ' AND role_key IN (' . implode(',', array_fill(0, count($role_keys), '%s')) . ')'; array_push($args, ...$role_keys); }
        $sql .= ' ORDER BY source_post_id ASC';
        try { $ids = $wpdb->get_col($wpdb->prepare($sql, ...$args)); } catch (\Throwable) { return []; }
        if (! is_array($ids) || (string) ($wpdb->last_error ?? '') !== '') return [];
        return array_values(array_map('intval', $ids));
    }

    public function replace_source_relationships(int $source_post_id, string $source_post_type, array $rows): true|\WP_Error
    {
        $roles = PersonRelationshipContract::allowed_roles($source_post_type);
        if ($source_post_id < 1 || ! $roles) return new \WP_Error('invalid_source', 'Unsupported source object.');
        $seen = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['person_id'], $row['role_key'], $row['position']) || ! is_int($row['person_id']) || $row['person_id'] < 1 || ! is_string($row['role_key']) || ! in_array($row['role_key'], $roles, true) || ! is_int($row['position']) || $row['position'] < 0 || isset($seen[$row['person_id']])) return new \WP_Error('invalid_relationship_rows', 'Relationship rows are malformed or assign a Person more than once.');
            $seen[$row['person_id']] = true;
        }
        if (! $this->schema->is_ready()) return new \WP_Error('index_unavailable', 'The relationship index is unavailable.');
        global $wpdb;
        try {
            if ($wpdb->query($wpdb->prepare("DELETE FROM {$this->schema->table_name()} WHERE source_post_id = %d", $source_post_id)) === false) return new \WP_Error('index_write_failed', 'Could not replace index rows.');
            foreach ($rows as $row) if ($wpdb->insert($this->schema->table_name(), ['source_post_id'=>$source_post_id,'source_post_type'=>$source_post_type,'person_id'=>$row['person_id'],'role_key'=>$row['role_key'],'position'=>$row['position']], ['%d','%s','%d','%s','%d']) === false) return new \WP_Error('index_write_failed', 'Could not insert index rows.');
        } catch (\Throwable) { return new \WP_Error('index_write_failed', 'Could not replace index rows.'); }
        return true;
    }

    public function delete_source(int $source_post_id): true|\WP_Error
    {
        if ($source_post_id < 1) return new \WP_Error('invalid_source', 'Source ID must be positive.');
        if (! $this->schema->is_ready()) return new \WP_Error('index_unavailable', 'The relationship index is unavailable.');
        global $wpdb;
        try { $result = $wpdb->query($wpdb->prepare("DELETE FROM {$this->schema->table_name()} WHERE source_post_id = %d", $source_post_id)); }
        catch (\Throwable) { return new \WP_Error('index_write_failed', 'Could not delete source index rows.'); }
        return $result === false ? new \WP_Error('index_write_failed', 'Could not delete source index rows.') : true;
    }
}
