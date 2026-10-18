<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Event\EventRoleSeeder;

final class EventRoleSeederTest
{
    private const APPROVED_TERMS = [
        'spreker' => 'Spreker',
        'paneellid' => 'Paneellid',
        'fasiliteerder' => 'Fasiliteerder',
        'gas' => 'Gas',
    ];

    public function test_clean_taxonomy_creates_exact_roles_without_relationships(): void
    {
        $seeder = $this->seeder_with_taxonomy();

        $result = $seeder->seed_event_role_terms();

        vgpc_test_same(array_keys(self::APPROVED_TERMS), $result['created'], 'A clean run must create the four approved role slugs in order.');
        vgpc_test_same([], $result['existing'], 'A clean run must report no existing roles.');
        vgpc_test_same(self::APPROVED_TERMS, $this->terms_by_slug(), 'A clean run must create only the approved names and slugs.');
        vgpc_test_same([], $GLOBALS['vgpc_test_relationships']['event_role'] ?? [], 'The seeder must not assign any objects to roles.');
        vgpc_test_same(0, $GLOBALS['vgpc_test_set_term_calls'], 'The seeder must never call wp_set_object_terms().');
    }

    public function test_rerun_creates_nothing_and_reports_all_roles_as_existing(): void
    {
        $seeder = $this->seeder_with_taxonomy();
        $seeder->seed_event_role_terms();
        $terms_before = array_map('get_object_vars', $this->terms());

        $result = $seeder->seed_event_role_terms();

        vgpc_test_same([], $result['created'], 'A rerun must create no terms.');
        vgpc_test_same(array_keys(self::APPROVED_TERMS), $result['existing'], 'A rerun must report all approved role slugs as existing.');
        vgpc_test_same($terms_before, array_map('get_object_vars', $this->terms()), 'A rerun must leave the existing terms unchanged.');
        vgpc_test_same(4, $GLOBALS['vgpc_test_insert_count'], 'A rerun must not insert duplicate terms.');
    }

    public function test_exact_existing_vocabulary_is_unchanged(): void
    {
        vgpc_test_reset();
        $this->register_taxonomy();
        foreach (self::APPROVED_TERMS as $slug => $name) {
            vgpc_test_add_term($name, $slug, 0, 'event_role');
        }
        $terms_before = array_map('get_object_vars', $this->terms());
        $seeder = new EventRoleSeeder();

        $result = $seeder->seed_event_role_terms();

        vgpc_test_same([], $result['created'], 'An exact existing vocabulary must not create terms.');
        vgpc_test_same(array_keys(self::APPROVED_TERMS), $result['existing'], 'Every exact role must be reported as existing.');
        vgpc_test_same($terms_before, array_map('get_object_vars', $this->terms()), 'An exact vocabulary must keep its term identities and values.');
        vgpc_test_same(0, $GLOBALS['vgpc_test_insert_count'], 'An exact vocabulary must not trigger inserts.');
    }

    public function test_partial_vocabulary_creates_only_missing_roles(): void
    {
        vgpc_test_reset();
        $this->register_taxonomy();
        $existing_term = vgpc_test_add_term('Paneellid', 'paneellid', 0, 'event_role');
        $seeder = new EventRoleSeeder();

        $result = $seeder->seed_event_role_terms();

        vgpc_test_same(['spreker', 'fasiliteerder', 'gas'], $result['created'], 'Only missing role slugs must be created.');
        vgpc_test_same(['paneellid'], $result['existing'], 'The existing role must be reported by slug.');
        $actual_terms = $this->terms_by_slug();
        $expected_terms = self::APPROVED_TERMS;
        ksort($actual_terms);
        ksort($expected_terms);
        vgpc_test_same($expected_terms, $actual_terms, 'The final vocabulary must match all four approved definitions.');
        vgpc_test_same($existing_term->term_id, get_term_by('slug', 'paneellid', 'event_role')->term_id, 'The existing role identity must remain unchanged.');
        vgpc_test_same(3, $GLOBALS['vgpc_test_insert_count'], 'The seeder must insert only the three missing terms.');
    }

    public function test_same_slug_with_wrong_name_stops_before_any_insert(): void
    {
        vgpc_test_reset();
        $this->register_taxonomy();
        $existing = vgpc_test_add_term('Guest', 'gas', 0, 'event_role');
        $seeder = new EventRoleSeeder();

        $error = $this->assert_throws(
            static fn () => $seeder->seed_event_role_terms(),
            'A same-slug conflict must fail.'
        );

        vgpc_test_true(str_contains($error->getMessage(), 'conflict'), 'The same-slug conflict must be explained.');
        vgpc_test_same([$existing], $this->terms(), 'A same-slug conflict must leave the current vocabulary unchanged.');
        vgpc_test_same(0, $GLOBALS['vgpc_test_insert_count'], 'The full vocabulary must be checked before any missing roles are inserted.');
    }

    public function test_same_name_with_wrong_slug_stops_before_any_insert(): void
    {
        vgpc_test_reset();
        $this->register_taxonomy();
        $existing = vgpc_test_add_term('Gas', 'gas-legacy', 0, 'event_role');
        $seeder = new EventRoleSeeder();

        $error = $this->assert_throws(
            static fn () => $seeder->seed_event_role_terms(),
            'A same-name conflict must fail.'
        );

        vgpc_test_true(str_contains($error->getMessage(), 'conflict'), 'The same-name conflict must be explained.');
        vgpc_test_same([$existing], $this->terms(), 'A same-name conflict must leave the current vocabulary unchanged.');
        vgpc_test_same(0, $GLOBALS['vgpc_test_insert_count'], 'The full vocabulary must be checked before any missing roles are inserted.');
    }

    public function test_unexpected_term_stops_before_any_insert(): void
    {
        vgpc_test_reset();
        $this->register_taxonomy();
        $existing = vgpc_test_add_term('Aanbieder', 'aanbieder', 0, 'event_role');
        $seeder = new EventRoleSeeder();

        $error = $this->assert_throws(
            static fn () => $seeder->seed_event_role_terms(),
            'An unexpected role must fail.'
        );

        vgpc_test_true(str_contains($error->getMessage(), 'Unexpected'), 'The unexpected term must be identified.');
        vgpc_test_same([$existing], $this->terms(), 'An unexpected term must not be changed or deleted.');
        vgpc_test_same(0, $GLOBALS['vgpc_test_insert_count'], 'An unexpected term must stop all seeding before writes.');
    }

    public function test_missing_taxonomy_fails_without_writes(): void
    {
        vgpc_test_reset();
        $seeder = new EventRoleSeeder();

        $error = $this->assert_throws(
            static fn () => $seeder->seed_event_role_terms(),
            'The seeder must require the registered event_role taxonomy.'
        );

        vgpc_test_true(str_contains($error->getMessage(), 'not registered'), 'The missing taxonomy must be reported.');
        vgpc_test_same(0, $GLOBALS['vgpc_test_insert_count'], 'A missing taxonomy must not create terms.');
    }

    public function test_inserted_term_is_verified_against_its_requested_slug(): void
    {
        vgpc_test_reset();
        $this->register_taxonomy();
        $existing_term = vgpc_test_add_term('Paneellid', 'paneellid', 0, 'event_role');
        $seeder = new EventRoleSeeder();
        $GLOBALS['vgpc_test_force_suffix_next_insert'] = true;

        $error = $this->assert_throws(
            static fn () => $seeder->seed_event_role_terms(),
            'An inserted term with a suffixed slug must fail verification.'
        );

        vgpc_test_true(str_contains($error->getMessage(), 'did not retain'), 'The failed insert verification must be reported.');
        vgpc_test_same(1, $GLOBALS['vgpc_test_insert_count'], 'Verification must happen after the first insert.');
        vgpc_test_same(
            [$existing_term],
            $this->terms(),
            'A failed insert verification must delete only the term created by that attempt.'
        );
        vgpc_test_same(false, get_term_by('slug', 'spreker-2', 'event_role'), 'The altered term slug must not remain after a failed verification.');

        $retry_result = $seeder->seed_event_role_terms();
        vgpc_test_same(['spreker', 'fasiliteerder', 'gas'], $retry_result['created'], 'A retry after cleanup must create the remaining approved roles.');
        vgpc_test_same(['paneellid'], $retry_result['existing'], 'A retry after cleanup must preserve the original approved role.');
        $retry_terms = $this->terms_by_slug();
        $expected_terms = self::APPROVED_TERMS;
        ksort($retry_terms);
        ksort($expected_terms);
        vgpc_test_same($expected_terms, $retry_terms, 'A retry after cleanup must restore the exact approved vocabulary.');
    }

    public function test_seed_rechecks_exact_vocabulary_after_inserts(): void
    {
        $seeder = $this->seeder_with_taxonomy();
        $injected = false;
        add_action(
            'created_term',
            static function (int $term_id, int $term_taxonomy_id, string $taxonomy) use (&$injected): void {
                if ($taxonomy !== 'event_role' || $injected) {
                    return;
                }

                $injected = true;
                vgpc_test_add_term('Aanbieder', 'aanbieder', 0, 'event_role');
            },
            10,
            3
        );

        $error = $this->assert_throws(
            static fn () => $seeder->seed_event_role_terms(),
            'The seeder must reject an unexpected term added during seeding.'
        );

        vgpc_test_true(str_contains($error->getMessage(), 'exact approved vocabulary'), 'The final vocabulary mismatch must be reported.');
        vgpc_test_same(
            ['aanbieder' => 'Aanbieder'],
            $this->terms_by_slug(),
            'A final vocabulary mismatch must remove only terms created by the seeder call.'
        );
    }

    public function test_cleanup_failure_is_reported_without_deleting_preexisting_roles(): void
    {
        vgpc_test_reset();
        $this->register_taxonomy();
        $existing_term = vgpc_test_add_term('Paneellid', 'paneellid', 0, 'event_role');
        $GLOBALS['vgpc_test_force_suffix_next_insert'] = true;
        $GLOBALS['vgpc_test_fail_next_term_delete'] = true;
        $seeder = new EventRoleSeeder();

        $error = $this->assert_throws(
            static fn () => $seeder->seed_event_role_terms(),
            'A failed owned-term cleanup must be reported.'
        );

        vgpc_test_true(str_contains($error->getMessage(), 'Cleanup failed'), 'A failed cleanup must appear in the seeder error.');
        vgpc_test_same(
            [$existing_term, get_term_by('slug', 'spreker-2', 'event_role')],
            $this->terms(),
            'A cleanup failure must leave the existing role and report the failed attempt-owned term.'
        );
    }

    public function test_register_adds_and_runs_the_wp_cli_command(): void
    {
        $seeder = $this->seeder_with_taxonomy();
        $seeder->register();

        vgpc_test_true(isset(WP_CLI::$commands['vg-event-role-setup']), 'The event role setup command must be registered when WP-CLI is available.');
        vgpc_test_true(is_callable(WP_CLI::$commands['vg-event-role-setup']), 'The registered event role command must be callable.');
        (WP_CLI::$commands['vg-event-role-setup'])([], []);

        vgpc_test_same(
            [['type' => 'success', 'message' => 'Event role terms created: spreker, paneellid, fasiliteerder, gas. Already present: none.'],],
            WP_CLI::$messages,
            'The command must report the created and existing slug lists.'
        );
    }

    public function test_wp_cli_command_exits_with_an_error_on_conflict(): void
    {
        vgpc_test_reset();
        $this->register_taxonomy();
        vgpc_test_add_term('Guest', 'gas', 0, 'event_role');
        $seeder = new EventRoleSeeder();
        $seeder->register();

        $error = $this->assert_throws(
            static fn () => (WP_CLI::$commands['vg-event-role-setup'])([], []),
            'A seeding conflict must be reported through WP_CLI::error().'
        );

        vgpc_test_true(str_contains($error->getMessage(), 'conflict'), 'The command must pass the seeding conflict to WP-CLI.');
        vgpc_test_same([], WP_CLI::$messages, 'A failed command must not report success.');
        vgpc_test_same(0, $GLOBALS['vgpc_test_insert_count'], 'A failed command must not partially seed the roles.');
    }

    private function seeder_with_taxonomy(): EventRoleSeeder
    {
        vgpc_test_reset();
        $this->register_taxonomy();

        return new EventRoleSeeder();
    }

    private function register_taxonomy(): void
    {
        register_taxonomy('event_role', [], []);
    }

    private function terms(): array
    {
        return get_terms(['taxonomy' => 'event_role', 'hide_empty' => false]);
    }

    private function terms_by_slug(): array
    {
        $terms = [];
        foreach ($this->terms() as $term) {
            $terms[$term->slug] = $term->name;
        }

        return $terms;
    }

    private function assert_throws(callable $callback, string $message): Throwable
    {
        try {
            $callback();
        } catch (Throwable $error) {
            return $error;
        }

        throw new RuntimeException($message);
    }
}
