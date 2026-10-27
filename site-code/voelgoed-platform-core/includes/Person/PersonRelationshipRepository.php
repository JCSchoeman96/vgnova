<?php
declare(strict_types=1);
namespace Voelgoed\PlatformCore\Person;
defined('ABSPATH') || exit;

final class PersonRelationshipRepository
{
    public function __construct(private PersonRelationshipSchema $schema) {}

    public function relationships_for_source(int $source_post_id): array
    {
        $rows = $this->relationships_for_source_checked($source_post_id);
        return is_wp_error($rows) ? [] : $rows;
    }

    /** @return list<array{person_id:int,role_key:string,position:int}>|\WP_Error */
    public function relationships_for_source_checked(int $source_post_id): array|\WP_Error
    {
        if ($source_post_id < 1 || ! $this->schema->is_ready()) return new \WP_Error('index_unavailable', 'The relationship index is unavailable.');
        global $wpdb;
        try { $rows = $wpdb->get_results($wpdb->prepare("SELECT person_id, role_key, position FROM {$this->schema->table_name()} WHERE source_post_id = %d ORDER BY role_key, position, person_id", $source_post_id), ARRAY_A); }
        catch (\Throwable) { return new \WP_Error('index_read_failed', 'Could not read source relationship index rows.'); }
        if (! is_array($rows) || (string) ($wpdb->last_error ?? '') !== '') return new \WP_Error('index_read_failed', 'Could not read source relationship index rows.');
        return array_map(static fn ($row) => ['person_id' => (int) $row['person_id'], 'role_key' => (string) $row['role_key'], 'position' => (int) $row['position']], $rows);
    }

    /** @return list<array{source_post_id:int,source_post_type:string,current_post_type:?string}>|\WP_Error */
    public function index_source_anomalies(): array|\WP_Error
    {
        if (! $this->schema->is_ready()) return new \WP_Error('index_unavailable', 'The relationship index is unavailable.');
        global $wpdb;
        $sql = "SELECT DISTINCT i.source_post_id, i.source_post_type, p.post_type AS current_post_type
            FROM {$this->schema->table_name()} AS i
            LEFT JOIN {$wpdb->posts} AS p ON p.ID = i.source_post_id
            ORDER BY i.source_post_id ASC";
        try { $rows = $wpdb->get_results($sql, ARRAY_A); }
        catch (\Throwable) { return new \WP_Error('index_read_failed', 'Could not inspect indexed source types.'); }
        if (! is_array($rows) || (string) ($wpdb->last_error ?? '') !== '') return new \WP_Error('index_read_failed', 'Could not inspect indexed source types.');
        $anomalies = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! array_key_exists('source_post_id', $row) || ! array_key_exists('source_post_type', $row)) continue;
            $stored_type = (string) ($row['source_post_type'] ?? '');
            $current_type = isset($row['current_post_type']) ? (string) $row['current_post_type'] : null;
            if ($current_type === null || ! PersonRelationshipContract::supported_source_type($current_type) || $stored_type !== $current_type) {
                $anomalies[] = [
                    'source_post_id' => (int) $row['source_post_id'],
                    'source_post_type' => $stored_type,
                    'current_post_type' => $current_type,
                ];
            }
        }
        return $anomalies;
    }

    /** @return array{source_post_id:int,source_post_type:string,current_post_type:?string}|null|\WP_Error */
    public function source_type_anomaly(int $source_post_id): array|null|\WP_Error
    {
        if ($source_post_id < 1 || ! $this->schema->is_ready()) return new \WP_Error('index_unavailable', 'The relationship index is unavailable.');
        global $wpdb;
        $sql = "SELECT DISTINCT i.source_post_id, i.source_post_type, p.post_type AS current_post_type
            FROM {$this->schema->table_name()} AS i
            LEFT JOIN {$wpdb->posts} AS p ON p.ID = i.source_post_id
            WHERE i.source_post_id = %d";
        try { $rows = $wpdb->get_results($wpdb->prepare($sql, $source_post_id), ARRAY_A); }
        catch (\Throwable) { return new \WP_Error('index_read_failed', 'Could not inspect indexed source type.'); }
        if (! is_array($rows) || (string) ($wpdb->last_error ?? '') !== '') return new \WP_Error('index_read_failed', 'Could not inspect indexed source type.');
        $row = $rows[0] ?? null;
        if ($row === null) return null;
        if (! is_array($row)) return new \WP_Error('index_read_failed', 'Could not inspect indexed source type.');
        $stored = (string) ($row['source_post_type'] ?? '');
        $current = isset($row['current_post_type']) ? (string) $row['current_post_type'] : null;
        if ($current !== null && $stored === $current) return null;
        return ['source_post_id' => $source_post_id, 'source_post_type' => $stored, 'current_post_type' => $current];
    }

    public function source_ids_for_person(int $person_id, string $source_post_type, array $role_keys = []): array
    {
        if ($person_id < 1 || ! PersonRelationshipContract::supported_source_type($source_post_type) || ! $this->schema->is_ready()) return [];
        $allowed = PersonRelationshipContract::allowed_roles($source_post_type);
        if ($role_keys && array_diff($role_keys, $allowed)) return [];
        global $wpdb;
        $sql = "SELECT DISTINCT source_post_id FROM {$this->schema->table_name()} WHERE person_id = %d AND source_post_type = %s";
        $args = [$person_id, $source_post_type];
        // Never let unsupported or legacy role rows enter public reverse reads.
        $role_keys = $role_keys ?: $allowed;
        $sql .= ' AND role_key IN (' . implode(',', array_fill(0, count($role_keys), '%s')) . ')'; array_push($args, ...$role_keys);
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
        $transaction_started = false;
        try {
            if ($wpdb->query('START TRANSACTION') === false) return new \WP_Error('index_write_failed', 'Could not start index replacement.');
            $transaction_started = true;
            if ($wpdb->query($wpdb->prepare("DELETE FROM {$this->schema->table_name()} WHERE source_post_id = %d", $source_post_id)) === false) {
                throw new \RuntimeException('Could not replace index rows.');
            }
            foreach ($rows as $row) {
                if ($wpdb->insert($this->schema->table_name(), ['source_post_id'=>$source_post_id,'source_post_type'=>$source_post_type,'person_id'=>$row['person_id'],'role_key'=>$row['role_key'],'position'=>$row['position']], ['%d','%s','%d','%s','%d']) === false) {
                    throw new \RuntimeException('Could not insert index rows.');
                }
            }
            if ($wpdb->query('COMMIT') === false) throw new \RuntimeException('Could not commit index replacement.');
        } catch (\Throwable) {
            if ($transaction_started) {
                try { $wpdb->query('ROLLBACK'); } catch (\Throwable) {}
            }
            return new \WP_Error('index_write_failed', 'Could not replace index rows.');
        }
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
