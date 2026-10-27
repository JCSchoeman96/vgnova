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
}
