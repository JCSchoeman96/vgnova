<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Person\PersonRelationshipCli;

final class PersonRelationshipCliTest
{
    public function test_register_exposes_the_three_requested_subcommands(): void
    {
        vgpc_test_reset();
        (new PersonRelationshipCli())->register();
        foreach (['setup', 'rebuild', 'reconcile'] as $subcommand) {
            vgpc_test_true(isset(WP_CLI::$commands['vg-person-relationship-index ' . $subcommand]), 'Each maintenance subcommand must be registered explicitly.');
        }
    }

    public function test_read_only_reconcile_does_not_require_mutation_session(): void
    {
        vgpc_test_reset();
        $calls = 0;
        $cli = $this->cli(static function () use (&$calls): void { $calls++; });
        $cli->reconcile_command([], ['format' => 'json']);
        vgpc_test_same(0, $calls, 'Read-only reconciliation must not require a mutation session.');
        vgpc_test_same('line', WP_CLI::$messages[0]['type'], 'Read-only reconciliation should report its result.');
    }

    public function test_table_reconcile_displays_inspection_error_code_and_message(): void
    {
        vgpc_test_reset();
        $this->register_fields();
        $GLOBALS['vgpc_test_posts'][81] = (object) ['ID' => 81, 'post_type' => 'post', 'post_status' => 'draft'];
        $GLOBALS['vgpc_test_meta'][81]['primary_author'] = [12];
        $cli = $this->cli(static function (): void {});
        $cli->reconcile_command([], ['source' => '81', 'format' => 'table']);
        $output = implode("\n", array_column(WP_CLI::$messages, 'message'));
        vgpc_test_true(str_contains($output, 'inspect failed: malformed_relationship: The primary_author field must contain a single Person value.'), 'Table output must expose inspection error code and message. Output: ' . $output);
        vgpc_test_true(str_contains($output, 'with issues: 1'), 'Inspection errors must count as unresolved issues.');
    }

    public function test_table_reconcile_reports_successful_reindex_and_repair_summary(): void
    {
        vgpc_test_reset();
        $this->register_fields();
        $GLOBALS['vgpc_test_posts'][82] = (object) ['ID' => 82, 'post_type' => 'post', 'post_status' => 'draft'];
        $GLOBALS['vgpc_test_meta'][82]['primary_author'] = 12;
        $GLOBALS['vgpc_test_posts'][12] = (object) ['ID' => 12, 'post_type' => 'person', 'post_status' => 'draft'];
        update_post_meta(82, \Voelgoed\PlatformCore\Person\PersonRelationshipContract::DIRTY_META_KEY, 1);
        $GLOBALS['vgpc_test_options'][\Voelgoed\PlatformCore\Person\PersonRelationshipSchema::VERSION_OPTION] = 1;
        $GLOBALS['wpdb']->get_var_handler = static fn (string $query, array $args): mixed => str_contains($query, 'SHOW TABLES') ? ($args[0] ?? '') : '1';
        $cli = $this->cli(static function (): void {});
        $cli->reconcile_command([], ['source' => '82', 'repair' => true, 'format' => 'table']);
        $output = implode("\n", array_column(WP_CLI::$messages, 'message'));
        vgpc_test_true(str_contains($output, '82 | 1 | reindexed, issues remain'), 'A repair with unresolved post-check issues must report both outcomes.');
        vgpc_test_true(str_contains($output, 'with issues: 1, repaired: 1'), 'Repair count and remaining issue count must both be reported.');
    }

    public function test_rebuild_requires_mutation_session_before_reindexing(): void
    {
        vgpc_test_reset();
        $calls = 0;
        $cli = $this->cli(static function () use (&$calls): void { $calls++; throw new RuntimeException('guard denied'); });
        try {
            $cli->rebuild_command([], []);
            throw new RuntimeException('Expected the mutation guard to stop rebuild.');
        } catch (RuntimeException $error) {
            vgpc_test_same('guard denied', $error->getMessage(), 'The guard must stop the write operation.');
        }
        vgpc_test_same(1, $calls, 'Rebuild must assert the mutation session once before writes.');
    }

    public function test_rebuild_enumerates_only_approved_source_types_including_trash(): void
    {
        vgpc_test_reset();
        $GLOBALS['vgpc_test_options'][\Voelgoed\PlatformCore\Person\PersonRelationshipSchema::VERSION_OPTION] = 1;
        $GLOBALS['wpdb']->get_var_handler = static fn (string $query, array $args): mixed => str_contains($query, 'SHOW TABLES') ? ($args[0] ?? '') : '1';
        $queries = [];
        $GLOBALS['vgpc_test_get_posts_handler'] = static function (array $args) use (&$queries): array { $queries[] = $args; return []; };
        $this->cli(static function (): void {})->rebuild_command([], []);
        vgpc_test_same(['post', 'mto-course'], array_column($queries, 'post_type'), 'A full rebuild must query only approved source types.');
        vgpc_test_true(in_array('trash', $queries[0]['post_status'], true), 'Full rebuild must include trashed supported sources.');
        vgpc_test_same('success', WP_CLI::$messages[0]['type'], 'An empty supported-source set is a successful idempotent rebuild.');
    }

    public function test_full_rebuild_prunes_orphan_and_unsupported_source_rows(): void
    {
        vgpc_test_reset();
        $GLOBALS['vgpc_test_options'][\Voelgoed\PlatformCore\Person\PersonRelationshipSchema::VERSION_OPTION] = 1;
        $GLOBALS['wpdb']->get_var_handler = static fn (string $query, array $args): mixed => str_contains($query, 'SHOW TABLES') ? ($args[0] ?? '') : '1';
        $GLOBALS['wpdb']->get_results_handler = static fn (string $query): array => str_contains($query, 'LEFT JOIN wp_posts') ? [
            ['source_post_id' => 601, 'source_post_type' => 'post', 'current_post_type' => null],
            ['source_post_id' => 602, 'source_post_type' => 'post', 'current_post_type' => 'product'],
        ] : [];
        $this->cli(static function (): void {})->rebuild_command([], []);
        $deleted_ids = [];
        foreach ($GLOBALS['wpdb']->queries as $query) {
            if (str_contains($query['query'], 'DELETE FROM wp_vg_person_relationship_index WHERE source_post_id = %d')) $deleted_ids[] = $query['args'][0];
        }
        vgpc_test_same([601, 602], $deleted_ids, 'Full rebuild must prune orphan and unsupported derived rows after source enumeration.');
    }

    public function test_reconcile_repair_rebuilds_trashed_source(): void
    {
        vgpc_test_reset();
        $this->register_fields();
        $GLOBALS['vgpc_test_options'][\Voelgoed\PlatformCore\Person\PersonRelationshipSchema::VERSION_OPTION] = 1;
        $GLOBALS['wpdb']->get_var_handler = static fn (string $query, array $args): mixed => str_contains($query, 'SHOW TABLES') ? ($args[0] ?? '') : '1';
        $GLOBALS['wpdb']->get_results_handler = static fn (string $query): array => [];
        $GLOBALS['vgpc_test_posts'][83] = (object) ['ID' => 83, 'post_type' => 'post', 'post_status' => 'trash'];
        $GLOBALS['vgpc_test_posts'][84] = (object) ['ID' => 84, 'post_type' => 'person', 'post_status' => 'draft'];
        $GLOBALS['vgpc_test_meta'][83]['primary_author'] = 84;
        update_post_meta(83, \Voelgoed\PlatformCore\Person\PersonRelationshipContract::DIRTY_META_KEY, 1);
        $this->cli(static function (): void {})->reconcile_command([], ['source' => '83', 'repair' => true, 'format' => 'table']);
        vgpc_test_same(1, count($GLOBALS['wpdb']->inserted), 'The guarded repair must rebuild an indexed trashed source.');
        vgpc_test_same(84, $GLOBALS['wpdb']->inserted[0][1]['person_id'], 'Trash repair must retain the authoritative Person assignment.');
        $output = implode("\n", array_column(WP_CLI::$messages, 'message'));
        vgpc_test_true(str_contains($output, '83 | 1 | reindexed'), 'CLI reconciliation must report successful trash repair.');
    }

    public function test_setup_requires_mutation_session_before_schema_install(): void
    {
        vgpc_test_reset();
        $cli = $this->cli(static function (): void { throw new RuntimeException('guard denied'); });
        try {
            $cli->setup_command([], []);
            throw new RuntimeException('Expected the mutation guard to stop setup.');
        } catch (RuntimeException $error) {
            vgpc_test_same('guard denied', $error->getMessage(), 'The guard must run before schema installation.');
        }
        vgpc_test_same([], $GLOBALS['vgpc_test_dbdelta_sql'], 'Denied setup must not install the schema.');
    }

    public function test_setup_installs_schema_after_guard_passes(): void
    {
        vgpc_test_reset();
        $GLOBALS['wpdb']->get_var_handler = static fn (string $query, array $args): mixed => str_contains($query, 'SHOW TABLES') ? ($args[0] ?? '') : '1';
        $calls = 0;
        $this->cli(static function () use (&$calls): void { $calls++; })->setup_command([], []);
        vgpc_test_same(1, $calls, 'Setup must assert its mutation session before installation.');
        vgpc_test_same(1, count($GLOBALS['vgpc_test_dbdelta_sql']), 'Setup must install the index schema.');
    }

    public function test_single_source_rebuild_projects_only_the_requested_supported_source(): void
    {
        vgpc_test_reset();
        $GLOBALS['vgpc_test_options'][\Voelgoed\PlatformCore\Person\PersonRelationshipSchema::VERSION_OPTION] = 1;
        $GLOBALS['wpdb']->get_var_handler = static fn (string $query, array $args): mixed => str_contains($query, 'SHOW TABLES') ? ($args[0] ?? '') : '1';
        $GLOBALS['vgpc_test_posts'][71] = (object) ['ID' => 71, 'post_type' => 'post', 'post_status' => 'draft'];
        $GLOBALS['vgpc_test_posts'][72] = (object) ['ID' => 72, 'post_type' => 'product', 'post_status' => 'publish'];
        $query_calls = 0;
        $GLOBALS['vgpc_test_get_posts_handler'] = static function () use (&$query_calls): array { $query_calls++; return []; };
        $this->cli(static function (): void {})->rebuild_command([], ['source' => '71']);
        vgpc_test_same(0, $query_calls, 'A single-source rebuild must not enumerate the full source set.');
        vgpc_test_same('success', WP_CLI::$messages[0]['type'], 'A supported source must rebuild successfully.');
        vgpc_test_true(count($GLOBALS['wpdb']->queries) > 0, 'The requested source must be projected into the derived index.');
    }

    public function test_reconcile_repair_requires_mutation_session(): void
    {
        vgpc_test_reset();
        $cli = $this->cli(static function (): void { throw new RuntimeException('guard denied'); });
        try {
            $cli->reconcile_command([], ['repair' => true]);
            throw new RuntimeException('Expected the mutation guard to stop repair.');
        } catch (RuntimeException $error) {
            vgpc_test_same('guard denied', $error->getMessage(), 'Repair must assert its mutation session before writes.');
        }
    }

    private function cli(callable $guard): PersonRelationshipCli
    {
        $schema = new \Voelgoed\PlatformCore\Person\PersonRelationshipSchema();
        $repository = new \Voelgoed\PlatformCore\Person\PersonRelationshipRepository($schema);
        $reader = new \Voelgoed\PlatformCore\Person\PersonRelationshipSourceReader();
        $validator = new \Voelgoed\PlatformCore\Person\PersonRelationshipValidator();
        $indexer = new \Voelgoed\PlatformCore\Person\PersonRelationshipIndexer(
            new \Voelgoed\PlatformCore\Person\PersonRelationshipSourceLock(), $reader, $validator, $repository
        );
        $reconciler = new \Voelgoed\PlatformCore\Person\PersonRelationshipReconciler($reader, $repository, $validator);
        return new PersonRelationshipCli($schema, $indexer, $reconciler, $guard);
    }

    private function register_fields(): void
    {
        $fields = [];
        foreach (array_merge(\Voelgoed\PlatformCore\Person\PersonRelationshipContract::allowed_roles('post'), \Voelgoed\PlatformCore\Person\PersonRelationshipContract::allowed_roles('mto-course')) as $role) {
            $fields[] = ['key' => 'field_' . $role, 'name' => $role];
        }
        acf_add_local_field_group(['key' => 'group_person_cli_test', 'fields' => $fields]);
    }
}
