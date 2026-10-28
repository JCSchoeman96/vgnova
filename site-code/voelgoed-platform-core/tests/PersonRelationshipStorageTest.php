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
        $this->install_valid_schema_stub();
        $schema = new \Voelgoed\PlatformCore\Person\PersonRelationshipSchema();
        vgpc_test_same(true, $schema->install(), 'Schema install should succeed.');
        vgpc_test_same(1, count($GLOBALS['vgpc_test_dbdelta_sql']), 'Install should call dbDelta once.');
        $ddl = preg_replace('/\\s+/', ' ', trim($GLOBALS['vgpc_test_dbdelta_sql'][0]));
        $expected = 'CREATE TABLE wp_vg_person_relationship_index ( id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, source_post_id BIGINT UNSIGNED NOT NULL, source_post_type VARCHAR(32) NOT NULL, person_id BIGINT UNSIGNED NOT NULL, role_key VARCHAR(64) NOT NULL, position INT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY (id), UNIQUE KEY source_person_role (source_post_id,person_id,role_key), KEY person_source_type (person_id,source_post_type), KEY person_role (person_id,role_key), KEY source_post (source_post_id) ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4;';
        vgpc_test_same($expected, $ddl, 'Install must create the exact approved columns and keys.');
        vgpc_test_true(! preg_match('/\\bFOREIGN\\s+KEY\\b/i', $ddl), 'Schema must not create foreign keys.');
        vgpc_test_same(1, get_option($schema::VERSION_OPTION), 'Schema version must persist only after exact schema verification.');
    }

    public function test_schema_readiness_rejects_wrong_columns(): void
    {
        $GLOBALS['vgpc_test_options'][\Voelgoed\PlatformCore\Person\PersonRelationshipSchema::VERSION_OPTION] = 1;
        $this->install_valid_schema_stub();
        $GLOBALS['wpdb']->get_schema_results_handler = static fn (string $query): array => str_contains($query, 'SHOW FULL COLUMNS')
            ? [(object) ['Field' => 'wrong_column', 'Type' => 'bigint unsigned', 'Null' => 'NO', 'Default' => null, 'Extra' => '']]
            : [];
        $schema = new \Voelgoed\PlatformCore\Person\PersonRelationshipSchema();
        vgpc_test_true(! $schema->is_ready(), 'A table with the wrong columns and indexes must not be ready.');
    }

    public function test_schema_readiness_rejects_missing_required_index(): void
    {
        $GLOBALS['vgpc_test_options'][\Voelgoed\PlatformCore\Person\PersonRelationshipSchema::VERSION_OPTION] = 1;
        $this->install_valid_schema_stub();
        $GLOBALS['wpdb']->get_schema_results_handler = static function (string $query): array {
            if (str_contains($query, 'SHOW FULL COLUMNS')) {
                return [
                    ['Field' => 'id', 'Type' => 'bigint unsigned', 'Null' => 'NO', 'Default' => null, 'Extra' => 'auto_increment'],
                    ['Field' => 'source_post_id', 'Type' => 'bigint unsigned', 'Null' => 'NO', 'Default' => null, 'Extra' => ''],
                    ['Field' => 'source_post_type', 'Type' => 'varchar(32)', 'Null' => 'NO', 'Default' => null, 'Extra' => ''],
                    ['Field' => 'person_id', 'Type' => 'bigint unsigned', 'Null' => 'NO', 'Default' => null, 'Extra' => ''],
                    ['Field' => 'role_key', 'Type' => 'varchar(64)', 'Null' => 'NO', 'Default' => null, 'Extra' => ''],
                    ['Field' => 'position', 'Type' => 'int unsigned', 'Null' => 'NO', 'Default' => '0', 'Extra' => ''],
                ];
            }
            return [];
        };
        $schema = new \Voelgoed\PlatformCore\Person\PersonRelationshipSchema();
        vgpc_test_true(! $schema->is_ready(), 'A table missing all required index definitions must not be ready.');
    }

    public function test_schema_install_does_not_persist_version_when_database_shape_or_engine_is_wrong(): void
    {
        unset($GLOBALS['vgpc_test_options'][\Voelgoed\PlatformCore\Person\PersonRelationshipSchema::VERSION_OPTION]);
        $this->install_valid_schema_stub();
        $GLOBALS['wpdb']->get_schema_status_handler = static fn (): object => (object) ['Engine' => 'MyISAM'];
        $schema = new \Voelgoed\PlatformCore\Person\PersonRelationshipSchema();
        $result = $schema->install();
        vgpc_test_true(is_wp_error($result), 'Install must fail when the table engine is not transactional InnoDB.');
        vgpc_test_same(0, (int) get_option($schema::VERSION_OPTION, 0), 'An unverified database schema must not receive the current version.');
    }

    public function test_schema_install_reports_version_persistence_failure(): void
    {
        $this->install_valid_schema_stub();
        $GLOBALS['vgpc_update_option_handler'] = static fn (): bool => false;
        $schema = new \Voelgoed\PlatformCore\Person\PersonRelationshipSchema();
        $result = $schema->install();
        vgpc_test_true(is_wp_error($result), 'Install must report a schema version option that did not persist.');
        vgpc_test_same(0, (int) get_option($schema::VERSION_OPTION, 0), 'Failed version persistence must remain visible.');
        $GLOBALS['vgpc_update_option_handler'] = null;
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
        vgpc_test_true(! $lock->acquire(55), 'A second operation cannot borrow a lock already owned by this object.');
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

    public function test_source_lock_forgets_unconfirmed_release_and_requires_fresh_acquisition(): void
    {
        $release_attempts = 0;
        $GLOBALS['wpdb']->get_var_handler = static function (string $query) use (&$release_attempts): mixed {
            if (str_contains($query, 'GET_LOCK')) return '1';
            if (str_contains($query, 'RELEASE_LOCK')) return ++$release_attempts === 1 ? '0' : '1';
            return '1';
        };
        $lock = new \Voelgoed\PlatformCore\Person\PersonRelationshipSourceLock();
        vgpc_test_true($lock->acquire(57), 'Expected test lock acquisition.');
        $lock->release(57);
        $lock->release_all();
        vgpc_test_same(1, $release_attempts, 'An unconfirmed release must not be retried against a potentially different owner.');
        vgpc_test_true(! $lock->release_succeeded(57), 'A failed release result must remain visible until another acquisition attempt.');
        vgpc_test_true($lock->acquire(57), 'A later operation must make a fresh database acquisition.');
        vgpc_test_true($lock->release_succeeded(57), 'A fresh acquisition must clear stale release status.');
        $lock->release(57);
        vgpc_test_same(2, $release_attempts, 'Only the newly acquired lock may be released.');
    }

    public function test_source_lock_clears_held_tracking_after_null_release_result(): void
    {
        $queries_before = count($GLOBALS['wpdb']->queries);
        $GLOBALS['wpdb']->get_var_handler = static function (string $query): mixed {
            if (str_contains($query, 'GET_LOCK')) return '1';
            if (str_contains($query, 'RELEASE_LOCK')) return null;
            return '1';
        };
        $lock = new \Voelgoed\PlatformCore\Person\PersonRelationshipSourceLock();
        vgpc_test_true($lock->acquire(58), 'Expected test lock acquisition.');
        $lock->release(58);
        $lock->release_all();
        vgpc_test_same(1, count(array_filter(
            array_slice($GLOBALS['wpdb']->queries, $queries_before),
            static fn (array $query): bool => $query['query'] === 'SELECT RELEASE_LOCK(%s)',
        )), 'A NULL release result must clear local ownership and prevent another release attempt.');
    }

    public function test_source_lock_retries_release_after_exception(): void
    {
        $attempts = 0;
        $GLOBALS['wpdb']->get_var_handler = static function (string $query) use (&$attempts): string {
            if (str_contains($query, 'RELEASE_LOCK') && ++$attempts <= 2) throw new RuntimeException('release failed');
            return '1';
        };
        $lock = new \Voelgoed\PlatformCore\Person\PersonRelationshipSourceLock();
        vgpc_test_true($lock->acquire(59), 'Expected test lock acquisition.');
        $lock->release(59);
        vgpc_test_true(! $lock->acquire(59), 'A failed release must block a new operation.');
        $lock->release_all();
        vgpc_test_same(3, $attempts, 'The active release gets one bounded retry, and later cleanup retries the still-tracked lock.');
        vgpc_test_true($lock->acquire(59), 'Acquisition may resume after confirmed cleanup.');
        $lock->release(59);
        vgpc_test_same(4, $attempts, 'The newly acquired lock must receive its own confirmed release.');
        $GLOBALS['wpdb']->get_var_handler = null;
    }

    public function test_indexer_lock_cleanup_runs_after_wp_shutdown_reaches_max_priority(): void
    {
        $attempts = 0;
        $held = false;
        $GLOBALS['wpdb']->get_var_handler = static function (string $query) use (&$attempts, &$held): string {
            if (str_contains($query, 'GET_LOCK')) { $held = true; return '1'; }
            if (str_contains($query, 'RELEASE_LOCK')) {
                if (++$attempts <= 4) throw new RuntimeException('temporary release failure');
                $held = false;
                return '1';
            }
            return '1';
        };
        $lock = new \Voelgoed\PlatformCore\Person\PersonRelationshipSourceLock();
        vgpc_test_true($lock->acquire(590), 'Expected indexer source lock acquisition.');
        $lock->release(590);
        vgpc_test_true($lock->release_pending(590), 'Two active release failures must retain the lock for cleanup.');

        $shutdown = new class {
            private array $callbacks = [];
            public function add(callable $callback, int $priority): void { $this->callbacks[$priority][] = $callback; ksort($this->callbacks, SORT_NUMERIC); }
            public function run(): void { foreach ($this->callbacks as $callbacks) foreach ($callbacks as $callback) $callback(); }
        };
        $late_priority_retry = false;
        $shutdown->add(static function () use ($lock, $shutdown, &$late_priority_retry): void {
            $lock->release_all();
            $shutdown->add(static function () use (&$late_priority_retry): void { $late_priority_retry = true; }, PHP_INT_MAX - 1);
        }, PHP_INT_MAX);
        $shutdown->run();
        vgpc_test_true(! $late_priority_retry, 'WP_Hook must skip a callback added at an already-passed priority.');
        vgpc_test_true($lock->release_pending(590), 'The max-priority hook failure must leave the lock tracked.');

        // The registered PHP shutdown function runs after WordPress's shutdown hook.
        $lock->release_all();
        vgpc_test_same(5, $attempts, 'Post-hook cleanup must retry the tracked release once.');
        vgpc_test_true(! $held, 'Post-hook cleanup must release the held indexer lock.');
        $GLOBALS['wpdb']->get_var_handler = null;
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

    private function install_valid_schema_stub(): void
    {
        $GLOBALS['wpdb']->get_var_handler = static fn (string $query): string => str_contains($query, 'SHOW TABLES') ? 'wp_vg_person_relationship_index' : '1';
        $GLOBALS['wpdb']->get_schema_status_handler = static fn (): object => (object) ['Engine' => 'InnoDB'];
        $GLOBALS['wpdb']->get_schema_results_handler = static function (string $query): array {
            if (str_contains($query, 'SHOW FULL COLUMNS')) {
                return [
                    ['Field' => 'id', 'Type' => 'bigint unsigned', 'Null' => 'NO', 'Default' => null, 'Extra' => 'auto_increment'],
                    ['Field' => 'source_post_id', 'Type' => 'bigint unsigned', 'Null' => 'NO', 'Default' => null, 'Extra' => ''],
                    ['Field' => 'source_post_type', 'Type' => 'varchar(32)', 'Null' => 'NO', 'Default' => null, 'Extra' => ''],
                    ['Field' => 'person_id', 'Type' => 'bigint unsigned', 'Null' => 'NO', 'Default' => null, 'Extra' => ''],
                    ['Field' => 'role_key', 'Type' => 'varchar(64)', 'Null' => 'NO', 'Default' => null, 'Extra' => ''],
                    ['Field' => 'position', 'Type' => 'int unsigned', 'Null' => 'NO', 'Default' => '0', 'Extra' => ''],
                ];
            }
            return [
                ['Key_name' => 'PRIMARY', 'Column_name' => 'id', 'Non_unique' => 0, 'Seq_in_index' => 1],
                ['Key_name' => 'source_person_role', 'Column_name' => 'source_post_id', 'Non_unique' => 0, 'Seq_in_index' => 1],
                ['Key_name' => 'source_person_role', 'Column_name' => 'person_id', 'Non_unique' => 0, 'Seq_in_index' => 2],
                ['Key_name' => 'source_person_role', 'Column_name' => 'role_key', 'Non_unique' => 0, 'Seq_in_index' => 3],
                ['Key_name' => 'person_source_type', 'Column_name' => 'person_id', 'Non_unique' => 1, 'Seq_in_index' => 1],
                ['Key_name' => 'person_source_type', 'Column_name' => 'source_post_type', 'Non_unique' => 1, 'Seq_in_index' => 2],
                ['Key_name' => 'person_role', 'Column_name' => 'person_id', 'Non_unique' => 1, 'Seq_in_index' => 1],
                ['Key_name' => 'person_role', 'Column_name' => 'role_key', 'Non_unique' => 1, 'Seq_in_index' => 2],
                ['Key_name' => 'source_post', 'Column_name' => 'source_post_id', 'Non_unique' => 1, 'Seq_in_index' => 1],
            ];
        };
    }
}
