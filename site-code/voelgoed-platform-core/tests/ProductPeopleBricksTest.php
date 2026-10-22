<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Product\ProductPeopleFields;
use Voelgoed\PlatformCore\Product\ProductPeopleResolver;
use Voelgoed\PlatformCore\Product\ProductPeopleBricks;

if (! class_exists('VGPC_TestBricksDatabase', false)) {
    class VGPC_TestBricksDatabase
    {
        public static array $page_data = [];
    }
}

if (! class_exists('Bricks\\Database')) {
    class_alias(VGPC_TestBricksDatabase::class, 'Bricks\\Database');
}

if (! function_exists('is_singular')) {
    function is_singular(): bool
    {
        return (bool) ($GLOBALS['vgpc_test_is_singular'] ?? false);
    }
}

if (! function_exists('get_queried_object')) {
    function get_queried_object(): mixed
    {
        return $GLOBALS['vgpc_test_queried_object'] ?? null;
    }
}

if (! function_exists('get_the_ID')) {
    function get_the_ID(): int
    {
        return (int) ($GLOBALS['vgpc_test_the_id'] ?? 0);
    }
}

if (! function_exists('bricks_is_builder')) {
    function bricks_is_builder(): bool
    {
        return (bool) ($GLOBALS['vgpc_test_bricks_builder'] ?? false);
    }
}

if (! function_exists('bricks_is_builder_call')) {
    function bricks_is_builder_call(): bool
    {
        return (bool) ($GLOBALS['vgpc_test_bricks_builder_call'] ?? false);
    }
}

final class ProductPeopleBricksTest
{
    private const PRODUCT = 401;
    private const PRODUCT_TWO = 402;
    private const NON_PRODUCT = 403;
    private const PERSON_A = 201;
    private const PERSON_B = 202;

    public function test_unmarked_query_is_returned_unchanged(): void
    {
        $this->fixture();
        $query_vars = ['post_type' => 'medewerker', 'meta_query' => [['key' => 'legacy']]];

        vgpc_test_same(
            $query_vars,
            $this->adapter()->filter_people_query_vars($query_vars, ['_cssClasses' => 'other vg-product-people-query-extra'], 'loop', 'block'),
            'A lookalike marker must not rewrite an unmarked query.'
        );
        vgpc_test_same(
            $query_vars,
            $this->adapter()->filter_people_query_vars($query_vars, ['_cssClasses' => 'prefix-vg-product-people-query'], 'loop', 'block'),
            'A marker prefix must not rewrite an unmarked query.'
        );
    }

    public function test_marked_query_without_product_context_fails_closed(): void
    {
        $this->fixture();
        $GLOBALS['vgpc_test_bricks_builder'] = false;
        $GLOBALS['vgpc_test_is_singular'] = false;
        $GLOBALS['vgpc_test_the_id'] = 0;
        $GLOBALS['post'] = null;

        vgpc_test_same(
            $this->canonical_query([0]),
            $this->adapter()->filter_people_query_vars([], ['_cssClasses' => 'vg-product-people-query'], 'loop', 'block'),
            'A marked query without a Product context must query no posts.'
        );
    }

    public function test_marked_query_with_non_product_context_fails_closed(): void
    {
        $this->fixture();
        $GLOBALS['vgpc_test_the_id'] = self::NON_PRODUCT;

        vgpc_test_same(
            $this->canonical_query([0]),
            $this->adapter()->filter_people_query_vars([], ['_cssClasses' => 'before vg-product-people-query after'], 'loop', 'block'),
            'A marked query in a non-Product context must query no posts.'
        );
    }

    public function test_unrelated_section_render_decision_is_unchanged(): void
    {
        $this->fixture();
        $element = (object) ['settings' => ['_cssClasses' => 'vg-product-people-section-extra']];

        vgpc_test_same(false, $this->adapter()->filter_people_section_render(false, $element), 'An unrelated section marker must preserve a false render decision.');
        vgpc_test_same(true, $this->adapter()->filter_people_section_render(true, $element), 'An unrelated section marker must preserve a true render decision.');
    }

    public function test_marked_query_delegates_membership_to_product_people_resolver(): void
    {
        $this->fixture();
        $GLOBALS['vgpc_test_the_id'] = self::PRODUCT;

        vgpc_test_same(
            $this->canonical_query([self::PERSON_A]),
            $this->adapter()->filter_people_query_vars([], ['_cssClasses' => 'vg-product-people-query'], 'loop', 'block'),
            'The marked query must use the canonical resolver membership for the current Product.'
        );
    }

    public function test_marked_query_preserves_resolver_order_with_post_in_ordering(): void
    {
        $this->fixture();
        $this->set_people(self::PRODUCT, [self::PERSON_B, self::PERSON_A]);
        $GLOBALS['vgpc_test_the_id'] = self::PRODUCT;

        vgpc_test_same(
            $this->canonical_query([self::PERSON_B, self::PERSON_A]),
            $this->adapter()->filter_people_query_vars([], ['_cssClasses' => 'vg-product-people-query'], 'loop', 'block'),
            'The marked query must preserve the resolver order with post__in ordering.'
        );
    }

    public function test_marked_query_discards_legacy_bricks_constraints(): void
    {
        $this->fixture();
        $GLOBALS['vgpc_test_the_id'] = self::PRODUCT;
        $legacy_query = [
            'post_type' => 'medewerker',
            'post_status' => 'private',
            'post__in' => [999],
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [['key' => 'produk_medewerkers', 'value' => 999]],
            'tax_query' => [['taxonomy' => 'medewerker_rol', 'terms' => [1]]],
            's' => 'legacy',
            'posts_per_page' => 12,
        ];

        vgpc_test_same(
            $this->canonical_query([self::PERSON_A]),
            $this->adapter()->filter_people_query_vars($legacy_query, ['_cssClasses' => 'vg-product-people-query'], 'loop', 'block'),
            'The canonical adapter must replace every inherited legacy constraint with only its locked query keys.'
        );
    }

    public function test_empty_canonical_result_forces_post_in_zero(): void
    {
        $this->fixture();
        $this->set_people(self::PRODUCT, []);
        $GLOBALS['vgpc_test_the_id'] = self::PRODUCT;

        vgpc_test_same(
            $this->canonical_query([0]),
            $this->adapter()->filter_people_query_vars([], ['_cssClasses' => 'vg-product-people-query'], 'loop', 'block'),
            'An empty canonical resolver result must never broaden to all Persons.'
        );
    }

    public function test_frontend_people_section_renders_when_resolver_has_ids(): void
    {
        $this->fixture();
        $GLOBALS['vgpc_test_the_id'] = self::PRODUCT;

        vgpc_test_same(
            true,
            $this->adapter()->filter_people_section_render(true, $this->people_section()),
            'A frontend Product People section with canonical Person IDs must render.'
        );
    }

    public function test_frontend_people_section_is_hidden_for_unconfigured_product(): void
    {
        $this->fixture();
        delete_post_meta(self::PRODUCT, ProductPeopleFields::MODE_FIELD);
        $GLOBALS['vgpc_test_the_id'] = self::PRODUCT;

        vgpc_test_same(
            false,
            $this->adapter()->filter_people_section_render(true, $this->people_section()),
            'The frontend Product People section must hide when canonical mode is UNCONFIGURED.'
        );
    }

    public function test_frontend_people_section_is_hidden_for_empty_manual_product(): void
    {
        $this->fixture();
        $this->set_people(self::PRODUCT, []);
        $GLOBALS['vgpc_test_the_id'] = self::PRODUCT;

        vgpc_test_same(
            false,
            $this->adapter()->filter_people_section_render(true, $this->people_section()),
            'The frontend Product People section must hide when Manual mode resolves no Persons.'
        );
    }

    public function test_builder_keeps_empty_people_section_editable(): void
    {
        $this->fixture();
        $GLOBALS['vgpc_test_bricks_builder'] = true;

        vgpc_test_same(
            true,
            $this->adapter()->filter_people_section_render(true, $this->people_section()),
            'Bricks builder context must keep an empty Product People section editable.'
        );
    }

    public function test_builder_call_keeps_empty_people_section_editable(): void
    {
        $this->fixture();
        $GLOBALS['vgpc_test_bricks_builder_call'] = true;

        vgpc_test_same(
            true,
            $this->adapter()->filter_people_section_render(true, $this->people_section()),
            'Bricks builder-call context must keep an empty Product People section editable.'
        );
    }

    public function test_bricks_preview_product_context_wins_over_queried_object(): void
    {
        $this->fixture();
        VGPC_TestBricksDatabase::$page_data = ['preview_or_post_id' => self::PRODUCT_TWO];
        $GLOBALS['vgpc_test_is_singular'] = true;
        $GLOBALS['vgpc_test_queried_object'] = get_post(self::PRODUCT);
        $GLOBALS['vgpc_test_the_id'] = self::PRODUCT;

        vgpc_test_same(
            $this->canonical_query([self::PERSON_B]),
            $this->adapter()->filter_people_query_vars([], ['_cssClasses' => 'vg-product-people-query'], 'loop', 'block'),
            'Bricks preview_or_post_id must take priority over a different queried Product.'
        );
    }

    public function test_non_product_bricks_template_preview_falls_back_to_singular_product(): void
    {
        $this->fixture();
        $this->post(404, 'bricks_template');
        VGPC_TestBricksDatabase::$page_data = ['preview_or_post_id' => 404];
        $GLOBALS['vgpc_test_is_singular'] = true;
        $GLOBALS['vgpc_test_queried_object'] = get_post(self::PRODUCT);
        $GLOBALS['vgpc_test_the_id'] = self::NON_PRODUCT;

        vgpc_test_same(
            $this->canonical_query([self::PERSON_A]),
            $this->adapter()->filter_people_query_vars([], ['_cssClasses' => 'vg-product-people-query'], 'loop', 'block'),
            'A Bricks template ID must not override the singular Product context.'
        );
    }

    public function test_singular_queried_product_context_is_used_without_preview(): void
    {
        $this->fixture();
        $GLOBALS['vgpc_test_is_singular'] = true;
        $GLOBALS['vgpc_test_queried_object'] = get_post(self::PRODUCT);
        $GLOBALS['vgpc_test_the_id'] = self::NON_PRODUCT;
        $GLOBALS['post'] = get_post(self::PRODUCT_TWO);

        vgpc_test_same(
            $this->canonical_query([self::PERSON_A]),
            $this->adapter()->filter_people_query_vars([], ['_cssClasses' => 'vg-product-people-query'], 'loop', 'block'),
            'A singular queried Product must beat a lower-priority non-Product get_the_ID and global post.'
        );
    }

    public function test_get_the_id_fallback_is_used_when_preview_and_singular_context_are_absent(): void
    {
        $this->fixture();
        $GLOBALS['vgpc_test_the_id'] = self::PRODUCT;
        $GLOBALS['post'] = get_post(self::PRODUCT_TWO);

        vgpc_test_same(
            $this->canonical_query([self::PERSON_A]),
            $this->adapter()->filter_people_query_vars([], ['_cssClasses' => 'vg-product-people-query'], 'loop', 'block'),
            'get_the_ID must be used when Bricks preview and singular query contexts are absent.'
        );
    }

    public function test_global_post_fallback_is_used_last(): void
    {
        $this->fixture();
        $GLOBALS['post'] = get_post(self::PRODUCT_TWO);

        vgpc_test_same(
            $this->canonical_query([self::PERSON_B]),
            $this->adapter()->filter_people_query_vars([], ['_cssClasses' => 'vg-product-people-query'], 'loop', 'block'),
            'The global post must be the last Product context fallback.'
        );
    }

    private function adapter(): ProductPeopleBricks
    {
        return new ProductPeopleBricks(new ProductPeopleResolver());
    }

    private function people_section(): object
    {
        return (object) ['settings' => ['_cssClasses' => 'layout vg-product-people-section']];
    }

    private function fixture(): void
    {
        vgpc_test_reset();
        VGPC_TestBricksDatabase::$page_data = [];
        $GLOBALS['vgpc_test_is_singular'] = false;
        $GLOBALS['vgpc_test_queried_object'] = null;
        $GLOBALS['vgpc_test_the_id'] = 0;
        $GLOBALS['vgpc_test_bricks_builder'] = false;
        $GLOBALS['vgpc_test_bricks_builder_call'] = false;
        $GLOBALS['post'] = null;

        acf_add_local_field_group([
            'key' => 'group_product_people_bricks_test',
            'fields' => [
                ['key' => ProductPeopleFields::MODE_FIELD_KEY, 'name' => ProductPeopleFields::MODE_FIELD],
                ['key' => ProductPeopleFields::OVERRIDE_FIELD_KEY, 'name' => ProductPeopleFields::OVERRIDE_FIELD],
            ],
        ]);

        $this->post(self::PRODUCT, 'product');
        $this->post(self::PRODUCT_TWO, 'product');
        $this->post(self::NON_PRODUCT, 'post');
        $this->post(self::PERSON_A, 'person');
        $this->post(self::PERSON_B, 'person');
        $this->set_people(self::PRODUCT, [self::PERSON_A]);
        $this->set_people(self::PRODUCT_TWO, [self::PERSON_B]);
    }

    /** @param list<int> $people */
    private function set_people(int $product_id, array $people): void
    {
        update_post_meta($product_id, ProductPeopleFields::MODE_FIELD, ProductPeopleFields::MODE_MANUAL);
        update_post_meta($product_id, ProductPeopleFields::OVERRIDE_FIELD, $people);
    }

    private function post(int $id, string $post_type, string $post_status = 'publish'): void
    {
        $GLOBALS['vgpc_test_posts'][$id] = (object) [
            'ID' => $id,
            'post_type' => $post_type,
            'post_status' => $post_status,
        ];
    }

    /** @param list<int> $ids */
    private function canonical_query(array $ids): array
    {
        return [
            'post_type' => ProductPeopleResolver::PERSON_POST_TYPE,
            'post_status' => ProductPeopleResolver::PERSON_ACTIVE_STATUS,
            'post__in' => $ids,
            'orderby' => 'post__in',
            'posts_per_page' => -1,
        ];
    }
}
