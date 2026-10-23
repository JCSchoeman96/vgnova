<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Product\ProductRoleSeeder;
use Voelgoed\PlatformCore\Product\ProductRoleVocabulary;

final class ProductRoleSeederTest
{
    public function test_empty_taxonomy_creates_exact_vocabulary_without_term_relationships(): void
    {
        $seeder = $this->seeder_with_taxonomy();

        $result = $seeder->seed_product_role_terms();

        $approved = ProductRoleVocabulary::approved_terms();
        vgpc_test_same(array_keys($approved), $result['created'], 'A clean seed must create approved roles in vocabulary order.');
        vgpc_test_same([], $result['existing'], 'A clean seed must report no existing Product Roles.');
        ksort($approved);
        vgpc_test_same($approved, $this->terms_by_slug(), 'A clean seed must create exactly the approved names and slugs.');
        vgpc_test_same([], $GLOBALS['vgpc_test_relationships']['product_role'] ?? [], 'Seeding must not create Product Role relationships.');
        vgpc_test_same(0, $GLOBALS['vgpc_test_set_term_calls'], 'Seeding must never call wp_set_object_terms().');
    }

    public function test_partial_vocabulary_creates_only_missing_approved_roles(): void
    {
        vgpc_test_reset();
        $this->register_taxonomy();
        $seeder = $this->seeder();
        $approved = ProductRoleVocabulary::approved_terms();
        $first_slug = array_key_first($approved);
        vgpc_test_add_term($approved[$first_slug], $first_slug, 0, 'product_role');

        $result = $seeder->seed_product_role_terms();

        $expected_created = array_values(array_diff(array_keys($approved), [$first_slug]));
        vgpc_test_same($expected_created, $result['created'], 'A partial seed must create only missing roles in approved order.');
        vgpc_test_same([$first_slug], $result['existing'], 'A partial seed must report the exact preexisting approved role.');
        $expected_terms = $approved;
        ksort($expected_terms);
        vgpc_test_same($expected_terms, $this->terms_by_slug(), 'The final vocabulary must exactly match the approved map.');
    }

    public function test_rerun_is_idempotent_and_preserves_existing_term_identity(): void
    {
        $seeder = $this->seeder_with_taxonomy();
        $seeder->seed_product_role_terms();
        $before = array_map('get_object_vars', $this->terms());

        $result = $seeder->seed_product_role_terms();

        vgpc_test_same([], $result['created'], 'An idempotent rerun must create no terms.');
        vgpc_test_same(array_keys(ProductRoleVocabulary::approved_terms()), $result['existing'], 'An idempotent rerun must report all approved roles as existing.');
        vgpc_test_same($before, array_map('get_object_vars', $this->terms()), 'An idempotent rerun must preserve term IDs and values.');
        vgpc_test_same(4, $GLOBALS['vgpc_test_insert_count'], 'An idempotent rerun must not insert duplicate terms.');
    }

    public function test_same_slug_with_wrong_name_stops_before_any_mutation(): void
    {
        vgpc_test_reset();
        $this->register_taxonomy();
        $existing = vgpc_test_add_term('Vendor', 'aanbieder', 0, 'product_role');
        $seeder = $this->seeder();

        $error = $this->assert_throws(
            static fn () => $seeder->seed_product_role_terms(),
            'A same-slug, wrong-name conflict must fail.'
        );

        vgpc_test_true(str_contains($error->getMessage(), 'conflict'), 'The same-slug conflict must be explained.');
        vgpc_test_same([$existing], $this->terms(), 'A same-slug conflict must preserve the current taxonomy.');
        vgpc_test_same(0, $GLOBALS['vgpc_test_insert_count'], 'The seeder must check conflicts before inserting any missing roles.');
    }

    public function test_same_name_with_wrong_slug_stops_before_any_mutation(): void
    {
        vgpc_test_reset();
        $this->register_taxonomy();
        $existing = vgpc_test_add_term('Aanbieder', 'vendor', 0, 'product_role');
        $seeder = $this->seeder();

        $error = $this->assert_throws(
            static fn () => $seeder->seed_product_role_terms(),
            'A same-name, wrong-slug conflict must fail.'
        );

        vgpc_test_true(str_contains($error->getMessage(), 'conflict'), 'The same-name conflict must be explained.');
        vgpc_test_same([$existing], $this->terms(), 'A same-name conflict must preserve the current taxonomy.');
        vgpc_test_same(0, $GLOBALS['vgpc_test_insert_count'], 'The seeder must check conflicts before inserting any missing roles.');
    }

    public function test_unexpected_fifth_term_stops_before_any_mutation(): void
    {
        vgpc_test_reset();
        $this->register_taxonomy();
        $existing = vgpc_test_add_term('Mentor', 'mentor', 0, 'product_role');
        $seeder = $this->seeder();

        $error = $this->assert_throws(
            static fn () => $seeder->seed_product_role_terms(),
            'An unexpected fifth Product Role must fail.'
        );

        vgpc_test_true(str_contains($error->getMessage(), 'Unexpected'), 'The fifth term must be identified as unexpected.');
        vgpc_test_same([$existing], $this->terms(), 'An unexpected Product Role must not be renamed or deleted.');
        vgpc_test_same(0, $GLOBALS['vgpc_test_insert_count'], 'An unexpected Product Role must stop all writes.');
    }

    public function test_missing_taxonomy_fails_without_writes(): void
    {
        vgpc_test_reset();
        $seeder = $this->seeder();

        $error = $this->assert_throws(
            static fn () => $seeder->seed_product_role_terms(),
            'The seeder must require the ACF-owned Product Role taxonomy.'
        );

        vgpc_test_true(str_contains($error->getMessage(), 'not registered'), 'The missing taxonomy must be reported.');
        vgpc_test_same(0, $GLOBALS['vgpc_test_insert_count'], 'A missing taxonomy must not create terms.');
    }

    public function test_seeder_reads_the_shared_vocabulary_map(): void
    {
        $seeder = $this->seeder_with_taxonomy();
        $source_file = (new ReflectionClass($seeder))->getFileName();
        vgpc_test_true(is_string($source_file), 'The Product Role seeder source must be available for contract verification.');
        $source = (string) file_get_contents($source_file);

        vgpc_test_true(
            str_contains($source, 'ProductRoleVocabulary::approved_terms()'),
            'The seeder must read ProductRoleVocabulary::approved_terms() instead of keeping its own role map.'
        );
    }

    public function test_inserted_term_is_verified_and_rolled_back_when_wordpress_changes_its_slug(): void
    {
        vgpc_test_reset();
        $this->register_taxonomy();
        $existing_term = vgpc_test_add_term('Skrywer', 'skrywer', 0, 'product_role');
        $seeder = $this->seeder();
        $GLOBALS['vgpc_test_force_suffix_next_insert'] = true;

        $error = $this->assert_throws(
            static fn () => $seeder->seed_product_role_terms(),
            'A Product Role inserted with a suffixed slug must fail verification.'
        );

        vgpc_test_true(str_contains($error->getMessage(), 'did not retain'), 'The altered inserted slug must be reported.');
        vgpc_test_same(1, $GLOBALS['vgpc_test_insert_count'], 'Inserted-term verification must fail after the first insert.');
        vgpc_test_same([$existing_term], $this->terms(), 'Rollback must remove the altered term and preserve the pre-existing approved term.');
        vgpc_test_same(false, get_term_by('slug', 'aanbieder-2', 'product_role'), 'The altered Product Role slug must not remain after rollback.');

        $retry_result = $seeder->seed_product_role_terms();
        $approved = ProductRoleVocabulary::approved_terms();
        $expected_created = array_values(array_diff(array_keys($approved), ['skrywer']));
        vgpc_test_same($expected_created, $retry_result['created'], 'A retry after rollback must create only the remaining approved roles.');
        vgpc_test_same(['skrywer'], $retry_result['existing'], 'A retry after rollback must preserve the pre-existing approved role.');
        ksort($approved);
        vgpc_test_same($approved, $this->terms_by_slug(), 'A retry after rollback must restore the exact approved vocabulary.');
    }

    public function test_final_vocabulary_mismatch_rolls_back_only_terms_created_by_this_attempt(): void
    {
        vgpc_test_reset();
        $this->register_taxonomy();
        $existing_term = vgpc_test_add_term('Skrywer', 'skrywer', 0, 'product_role');
        $injected = false;
        add_action(
            'created_term',
            static function (int $term_id, int $term_taxonomy_id, string $taxonomy) use (&$injected): void {
                if ($taxonomy !== 'product_role' || $injected) {
                    return;
                }

                $injected = true;
                vgpc_test_add_term('Mentor', 'mentor', 0, 'product_role');
            },
            10,
            3
        );
        $seeder = $this->seeder();

        $error = $this->assert_throws(
            static fn () => $seeder->seed_product_role_terms(),
            'An unexpected Product Role added during seeding must fail final verification.'
        );

        vgpc_test_true(str_contains($error->getMessage(), 'exact approved vocabulary'), 'The final vocabulary mismatch must be reported.');
        vgpc_test_same(
            ['mentor' => 'Mentor', 'skrywer' => 'Skrywer'],
            $this->terms_by_slug(),
            'Rollback must remove only terms created in this attempt and preserve the pre-existing and concurrently added terms.'
        );
        vgpc_test_same(3, $GLOBALS['vgpc_test_insert_count'], 'The final vocabulary check must occur after the missing approved terms were created.');
        vgpc_test_true(in_array($existing_term, $this->terms(), true), 'Rollback must preserve the pre-existing approved term identity.');
    }

    public function test_cleanup_failure_is_reported_without_deleting_preexisting_roles(): void
    {
        vgpc_test_reset();
        $this->register_taxonomy();
        $existing_term = vgpc_test_add_term('Skrywer', 'skrywer', 0, 'product_role');
        $GLOBALS['vgpc_test_force_suffix_next_insert'] = true;
        $GLOBALS['vgpc_test_fail_next_term_delete'] = true;
        $seeder = $this->seeder();

        $error = $this->assert_throws(
            static fn () => $seeder->seed_product_role_terms(),
            'A failed cleanup of an attempt-owned Product Role must be reported.'
        );

        vgpc_test_true(str_contains($error->getMessage(), 'Cleanup failed'), 'A failed cleanup must appear in the seeder error.');
        vgpc_test_same(
            [$existing_term, get_term_by('slug', 'aanbieder-2', 'product_role')],
            $this->terms(),
            'A failed cleanup must preserve the pre-existing role and leave the failed attempt-owned deletion visible.'
        );
    }

    public function test_register_adds_and_runs_the_wp_cli_command(): void
    {
        $seeder = $this->seeder_with_taxonomy();
        $seeder->register();

        vgpc_test_true(isset(WP_CLI::$commands['vg-product-role-setup']), 'The Product Role setup command must be registered when WP-CLI is available.');
        vgpc_test_true(is_callable(WP_CLI::$commands['vg-product-role-setup']), 'The registered Product Role command must be callable.');
        (WP_CLI::$commands['vg-product-role-setup'])([], []);

        vgpc_test_same(
            [['type' => 'success', 'message' => 'Product role terms created: aanbieder, skrywer, instrukteur, skepper. Already present: none.']],
            WP_CLI::$messages,
            'The WP-CLI command must report created and existing role slugs.'
        );
    }

    public function test_wp_cli_command_exits_with_an_error_on_conflict(): void
    {
        vgpc_test_reset();
        $this->register_taxonomy();
        vgpc_test_add_term('Vendor', 'aanbieder', 0, 'product_role');
        $seeder = $this->seeder();
        $seeder->register();

        $error = $this->assert_throws(
            static fn () => (WP_CLI::$commands['vg-product-role-setup'])([], []),
            'A Product Role conflict must be reported through WP_CLI::error().'
        );

        vgpc_test_true(str_contains($error->getMessage(), 'conflict'), 'The WP-CLI command must pass the seeding conflict to WP-CLI.');
        vgpc_test_same([], WP_CLI::$messages, 'A failed WP-CLI command must not report success.');
        vgpc_test_same(0, $GLOBALS['vgpc_test_insert_count'], 'A failed WP-CLI command must not seed any roles.');
    }

    private function seeder_with_taxonomy(): ProductRoleSeeder
    {
        vgpc_test_reset();
        $this->register_taxonomy();

        return $this->seeder();
    }

    private function seeder(): ProductRoleSeeder
    {
        vgpc_test_true(class_exists(ProductRoleSeeder::class), 'ProductRoleSeeder is not implemented yet.');
        vgpc_test_true(class_exists(ProductRoleVocabulary::class), 'ProductRoleVocabulary is not implemented yet.');

        return new ProductRoleSeeder();
    }

    private function register_taxonomy(): void
    {
        register_taxonomy('product_role', ['product_contribution'], []);
    }

    private function terms(): array
    {
        return get_terms(['taxonomy' => 'product_role', 'hide_empty' => false]);
    }

    /** @return array<string, string> */
    private function terms_by_slug(): array
    {
        $terms = [];
        foreach ($this->terms() as $term) {
            $terms[(string) $term->slug] = (string) $term->name;
        }
        ksort($terms);

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
