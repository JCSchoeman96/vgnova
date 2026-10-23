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
