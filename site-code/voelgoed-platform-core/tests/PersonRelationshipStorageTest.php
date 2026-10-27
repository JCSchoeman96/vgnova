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

    public function test_schema_install_emits_exact_expected_ddl_without_foreign_keys(): void
    {
        $GLOBALS['wpdb']->get_var_handler = static fn (string $query, array $args): string => str_contains($query, 'SHOW TABLES') ? 'wp_vg_person_relationship_index' : '1';
        $schema = new \Voelgoed\PlatformCore\Person\PersonRelationshipSchema();
        vgpc_test_same(true, $schema->install(), 'Schema install should succeed.');
        vgpc_test_same(1, count($GLOBALS['vgpc_test_dbdelta_sql']), 'Install should call dbDelta once.');
        $ddl = preg_replace('/\\s+/', ' ', trim($GLOBALS['vgpc_test_dbdelta_sql'][0]));
        $expected = 'CREATE TABLE wp_vg_person_relationship_index ( id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, source_post_id BIGINT UNSIGNED NOT NULL, source_post_type VARCHAR(32) NOT NULL, person_id BIGINT UNSIGNED NOT NULL, role_key VARCHAR(64) NOT NULL, position INT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY (id), UNIQUE KEY source_person_role (source_post_id,person_id,role_key), KEY person_source_type (person_id,source_post_type), KEY person_role (person_id,role_key), KEY source_post (source_post_id) ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4;';
        vgpc_test_same($expected, $ddl, 'Install must create the exact approved columns and keys.');
        vgpc_test_true(! preg_match('/\\bFOREIGN\\s+KEY\\b/i', $ddl), 'Schema must not create foreign keys.');
    }

    public function test_schema_readiness_fails_closed_when_database_check_throws(): void
    {
        $GLOBALS['vgpc_test_options']['vg_person_relationship_index_schema_version'] = 1;
        $GLOBALS['wpdb']->get_var_handler = static function (): never { throw new RuntimeException('database unavailable'); };
        $schema = new \Voelgoed\PlatformCore\Person\PersonRelationshipSchema();
        vgpc_test_true(! $schema->is_ready(), 'Readiness exceptions must report unavailable.');
        $GLOBALS['wpdb']->get_var_handler = null;
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

    public function test_source_lock_retains_tracking_after_release_failure_then_retries(): void
    {
        $release_attempts = 0;
        $GLOBALS['wpdb']->get_var_handler = static function (string $query) use (&$release_attempts): mixed {
            if (str_contains($query, 'GET_LOCK')) return '1';
            if (str_contains($query, 'RELEASE_LOCK')) return ++$release_attempts === 1 ? null : '1';
            return '1';
        };
        $lock = new \Voelgoed\PlatformCore\Person\PersonRelationshipSourceLock();
        vgpc_test_true($lock->acquire(57), 'Expected test lock acquisition.');
        $lock->release(57);
        $lock->release_all();
        vgpc_test_same(2, $release_attempts, 'Failed release must remain tracked for a later retry.');
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
        $GLOBALS['vgpc_test_options']['vg_person_relationship_index_schema_version'] = 1;
        $GLOBALS['wpdb']->get_var_handler = static fn (string $query): string => str_contains($query, 'SHOW TABLES') ? 'wp_vg_person_relationship_index' : '1';
        $schema = new \Voelgoed\PlatformCore\Person\PersonRelationshipSchema();
        $repository = new \Voelgoed\PlatformCore\Person\PersonRelationshipRepository($schema);
        $GLOBALS['wpdb']->fail_query = true;
        $result = $repository->replace_source_relationships(8, 'post', []);
        vgpc_test_true(is_wp_error($result), 'Database delete failure must be returned to the caller.');
    }

    public function test_repository_rolls_back_partial_replacement_and_can_recover(): void
    {
        $GLOBALS['vgpc_test_options']['vg_person_relationship_index_schema_version'] = 1;
        $GLOBALS['wpdb']->fail_query = false;
        $GLOBALS['wpdb']->get_var_handler = static fn (string $query): string => str_contains($query, 'SHOW TABLES') ? 'wp_vg_person_relationship_index' : '1';
        $schema = new \Voelgoed\PlatformCore\Person\PersonRelationshipSchema();
        $repository = new \Voelgoed\PlatformCore\Person\PersonRelationshipRepository($schema);
        $GLOBALS['wpdb']->fail_insert = true;
        $failed = $repository->replace_source_relationships(8, 'post', [
            ['person_id' => 3, 'role_key' => 'primary_author', 'position' => 0],
        ]);
        vgpc_test_true(is_wp_error($failed), 'Failed insert should report an error.');
        $queries = array_column($GLOBALS['wpdb']->queries, 'query');
        vgpc_test_true(in_array('START TRANSACTION', $queries, true), 'Replacement must start a transaction.');
        vgpc_test_true(in_array('ROLLBACK', $queries, true), 'Failed insert must roll back deletion and inserted rows.');
        $GLOBALS['wpdb']->fail_insert = false;
        $recovered = $repository->replace_source_relationships(8, 'post', []);
        vgpc_test_same(true, $recovered, 'A replacement after rollback should succeed.');
        $queries = array_column($GLOBALS['wpdb']->queries, 'query');
        vgpc_test_true(in_array('COMMIT', $queries, true), 'Successful replacement must commit.');
    }
}
