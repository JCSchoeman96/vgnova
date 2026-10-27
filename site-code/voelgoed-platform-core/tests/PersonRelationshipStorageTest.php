<?php
declare(strict_types=1);

final class PersonRelationshipStorageTest
{
    public function test_schema_defines_expected_index_without_foreign_keys_or_auto_install(): void
    {
        $schema = new \Voelgoed\PlatformCore\Person\PersonRelationshipSchema();
        vgpc_test_same(1, $schema::SCHEMA_VERSION, 'Schema version must be 1.');
        vgpc_test_same('vg_person_relationship_index_schema_version', $schema::VERSION_OPTION, 'Version option must be stable.');
        vgpc_test_same('wp_vg_person_relationship_index', $schema->table_name(), 'Table must use the active prefix.');
        vgpc_test_true(! $schema->is_ready(), 'Schema must not install itself on construction or readiness checks.');
    }

    public function test_source_lock_tracks_acquired_locks_and_releases_them(): void
    {
        $lock = new \Voelgoed\PlatformCore\Person\PersonRelationshipSourceLock();
        vgpc_test_true($lock->acquire(55), 'Expected test lock acquisition.');
        $lock->release(55);
        $lock->release_all();
        $queries = array_column($GLOBALS['wpdb']->queries, 'query');
        vgpc_test_true(in_array('SELECT RELEASE_LOCK(%s)', $queries, true), 'Expected named lock release.');
    }

    public function test_source_lock_rejects_contention(): void
    {
        $GLOBALS['wpdb']->get_var_handler = static fn (string $query): string => str_contains($query, 'GET_LOCK') ? '0' : '1';
        $lock = new \Voelgoed\PlatformCore\Person\PersonRelationshipSourceLock();
        vgpc_test_true(! $lock->acquire(56), 'Contended source lock must fail.');
    }

    public function test_repository_rejects_malformed_rows_and_fails_closed_when_table_unavailable(): void
    {
        $schema = new \Voelgoed\PlatformCore\Person\PersonRelationshipSchema();
        $repository = new \Voelgoed\PlatformCore\Person\PersonRelationshipRepository($schema);
        $error = $repository->replace_source_relationships(8, 'post', [['person_id' => 3, 'role_key' => 'bogus', 'position' => 0]]);
        vgpc_test_true(is_wp_error($error), 'Unsupported role must fail validation.');
        vgpc_test_same([], $repository->relationships_for_source(8), 'Unavailable index reads must return no relationships.');
        vgpc_test_same([], $repository->source_ids_for_person(3, 'post'), 'Unavailable reverse reads must return no sources.');
    }

    public function test_repository_reports_database_write_failure(): void
    {
        $GLOBALS['wpdb']->get_var_handler = static fn (string $query): string => str_contains($query, 'SHOW TABLES') ? 'wp_vg_person_relationship_index' : '1';
        $schema = new \Voelgoed\PlatformCore\Person\PersonRelationshipSchema();
        $repository = new \Voelgoed\PlatformCore\Person\PersonRelationshipRepository($schema);
        $GLOBALS['wpdb']->fail_query = true;
        $result = $repository->replace_source_relationships(8, 'post', []);
        vgpc_test_true(is_wp_error($result), 'Database delete failure must be returned to the caller.');
    }
}
