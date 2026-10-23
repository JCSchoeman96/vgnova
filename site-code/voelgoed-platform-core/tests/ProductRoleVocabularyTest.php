<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Product\ProductRoleVocabulary;

final class ProductRoleVocabularyTest
{
    private const APPROVED_TERMS = [
        'aanbieder' => 'Aanbieder',
        'skrywer' => 'Skrywer',
        'instrukteur' => 'Instrukteur',
        'skepper' => 'Skepper',
    ];

    public function test_approved_terms_returns_the_exact_four_product_roles(): void
    {
        $this->require_vocabulary();

        vgpc_test_same(self::APPROVED_TERMS, ProductRoleVocabulary::approved_terms(), 'The vocabulary must expose exactly the approved Product Role labels and slugs.');
        vgpc_test_same('product_role', ProductRoleVocabulary::TAXONOMY, 'The vocabulary must own the canonical Product Role taxonomy name.');
    }

    public function test_is_approved_term_id_accepts_a_term_with_exact_taxonomy_slug_and_label(): void
    {
        vgpc_test_reset();
        $this->require_vocabulary();
        $term = vgpc_test_add_term('Aanbieder', 'aanbieder', 0, 'product_role');

        vgpc_test_same(true, ProductRoleVocabulary::is_approved_term_id($term->term_id), 'An exact approved Product Role term must be accepted.');
    }

    public function test_is_approved_term_id_rejects_a_same_taxonomy_unapproved_fifth_role(): void
    {
        vgpc_test_reset();
        $this->require_vocabulary();
        $fifth_role = vgpc_test_add_term('Mentor', 'mentor', 0, 'product_role');

        vgpc_test_same(false, ProductRoleVocabulary::is_approved_term_id($fifth_role->term_id), 'A fifth term in product_role must not count as an approved Product Role.');
    }

    public function test_is_approved_term_id_rejects_an_exact_label_and_slug_in_the_wrong_taxonomy(): void
    {
        vgpc_test_reset();
        $this->require_vocabulary();
        $term = vgpc_test_add_term('Aanbieder', 'aanbieder', 0, 'event_role');

        vgpc_test_same(false, ProductRoleVocabulary::is_approved_term_id($term->term_id), 'An Event Role must not be accepted as a Product Role.');
    }

    public function test_is_approved_term_id_rejects_an_approved_slug_with_the_wrong_label(): void
    {
        vgpc_test_reset();
        $this->require_vocabulary();
        $term = vgpc_test_add_term('Vendor', 'aanbieder', 0, 'product_role');

        vgpc_test_same(false, ProductRoleVocabulary::is_approved_term_id($term->term_id), 'A Product Role with an approved slug but the wrong label must be rejected.');
    }

    public function test_is_approved_term_id_rejects_an_approved_label_with_the_wrong_slug(): void
    {
        vgpc_test_reset();
        $this->require_vocabulary();
        $term = vgpc_test_add_term('Aanbieder', 'vendor', 0, 'product_role');

        vgpc_test_same(false, ProductRoleVocabulary::is_approved_term_id($term->term_id), 'A Product Role with an approved label but the wrong slug must be rejected.');
    }

    public function test_is_approved_term_id_rejects_missing_or_nonpositive_term_ids(): void
    {
        vgpc_test_reset();
        $this->require_vocabulary();

        vgpc_test_same(false, ProductRoleVocabulary::is_approved_term_id(0), 'Term ID zero must be rejected.');
        vgpc_test_same(false, ProductRoleVocabulary::is_approved_term_id(-1), 'Negative term IDs must be rejected.');
        vgpc_test_same(false, ProductRoleVocabulary::is_approved_term_id(9999), 'A missing term ID must be rejected.');
    }

    private function require_vocabulary(): void
    {
        vgpc_test_true(class_exists(ProductRoleVocabulary::class), 'ProductRoleVocabulary is not implemented yet.');
    }
}
