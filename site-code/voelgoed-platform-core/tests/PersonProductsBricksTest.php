<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Product\ProductContributionRepository;
use Voelgoed\PlatformCore\Product\ProductRoleVocabulary;
use Voelgoed\PlatformCore\Product\PersonProductsBricks;

if (! class_exists('VGPC_TestPersonProductsBricksDatabase', false)) {
    class VGPC_TestPersonProductsBricksDatabase
    {
        public static array $page_data = [];
    }
}

if (! class_exists('Bricks\\Database')) {
    class_alias(VGPC_TestPersonProductsBricksDatabase::class, 'Bricks\\Database');
}

if (! function_exists('is_singular')) {
    function is_singular(): bool
    {
        return (bool) ($GLOBALS['vgpc_person_products_test_is_singular'] ?? false);
    }
}

if (! function_exists('get_queried_object')) {
    function get_queried_object(): mixed
    {
        return $GLOBALS['vgpc_person_products_test_queried_object'] ?? null;
    }
}

if (! function_exists('get_the_ID')) {
    function get_the_ID(): int|false
    {
        $post_id = $GLOBALS['vgpc_person_products_test_the_id'] ?? 0;
        return is_int($post_id) && $post_id > 0 ? $post_id : false;
    }
}

if (! function_exists('bricks_is_builder')) {
    function bricks_is_builder(): bool
    {
        return (bool) ($GLOBALS['vgpc_person_products_test_bricks_builder'] ?? false);
    }
}

if (! function_exists('bricks_is_builder_call')) {
    function bricks_is_builder_call(): bool
    {
        return (bool) ($GLOBALS['vgpc_person_products_test_bricks_builder_call'] ?? false);
    }
}

final class PersonProductsBricksTest
{
    private const PERSON_ONE = 201;
    private const PERSON_TWO = 202;
    private const PRODUCT_ONE = 501;
    private const PRODUCT_TWO = 502;
    private const PRODUCT_THREE = 503;
    private const EVENT = 301;
    private const NON_PERSON_POST = 401;
    private const CONTRIBUTION_BASE = 701;

    public function test_bricks_preview_person_is_accepted(): void
    {
        $this->fixture();
        $this->add_manual_products(self::PERSON_ONE, [self::PRODUCT_TWO, self::PRODUCT_ONE]);
        VGPC_TestPersonProductsBricksDatabase::$page_data = ['preview_or_post_id' => self::PERSON_ONE];
        $GLOBALS['vgpc_person_products_test_is_singular'] = true;
        $GLOBALS['vgpc_person_products_test_queried_object'] = get_post(self::PERSON_TWO);

        vgpc_test_same(
            $this->canonical_query([self::PRODUCT_ONE, self::PRODUCT_TWO]),
            $this->adapter()->filter_product_query_vars([], $this->query_settings(), 'loop', 'block'),
            'Bricks preview Person must be accepted before lower-priority contexts.'
        );
    }

    public function test_bricks_preview_non_person_fails_closed(): void
    {
        $this->fixture();
        $this->add_manual_products(self::PERSON_ONE, [self::PRODUCT_ONE]);
        VGPC_TestPersonProductsBricksDatabase::$page_data = ['preview_or_post_id' => self::PRODUCT_ONE];
        $GLOBALS['vgpc_person_products_test_is_singular'] = true;
        $GLOBALS['vgpc_person_products_test_queried_object'] = get_post(self::PERSON_ONE);

        vgpc_test_same(
            $this->canonical_query([0]),
            $this->adapter()->filter_product_query_vars([], $this->query_settings(), 'loop', 'block'),
            'A non-Person Bricks preview must fail closed.'
        );
    }

    public function test_non_person_preview_blocks_stale_global_person(): void
    {
        $this->fixture();
        $this->add_manual_products(self::PERSON_ONE, [self::PRODUCT_ONE]);
        VGPC_TestPersonProductsBricksDatabase::$page_data = ['preview_or_post_id' => self::PRODUCT_ONE];
        $GLOBALS['post'] = get_post(self::PERSON_ONE);

        vgpc_test_same(
            $this->canonical_query([0]),
            $this->adapter()->filter_product_query_vars([], $this->query_settings(), 'loop', 'block'),
            'A Product preview must block a stale lower-priority global Person.'
        );
    }

    public function test_singular_queried_person_is_accepted_without_preview(): void
    {
        $this->fixture();
        $this->add_manual_products(self::PERSON_ONE, [self::PRODUCT_ONE]);
        $GLOBALS['vgpc_person_products_test_is_singular'] = true;
        $GLOBALS['vgpc_person_products_test_queried_object'] = get_post(self::PERSON_ONE);
        $GLOBALS['vgpc_person_products_test_the_id'] = self::PERSON_TWO;

        vgpc_test_same(
            $this->canonical_query([self::PRODUCT_ONE]),
            $this->adapter()->filter_product_query_vars([], $this->query_settings(), 'loop', 'block'),
            'The queried singular Person must be accepted when Bricks has no preview ID.'
        );
    }

    public function test_singular_queried_non_person_fails_closed(): void
    {
        $this->fixture();
        $this->add_manual_products(self::PERSON_ONE, [self::PRODUCT_ONE]);
        $GLOBALS['vgpc_person_products_test_is_singular'] = true;
        $GLOBALS['vgpc_person_products_test_queried_object'] = get_post(self::PRODUCT_ONE);
        $GLOBALS['vgpc_person_products_test_the_id'] = self::PERSON_ONE;

        vgpc_test_same(
            $this->canonical_query([0]),
            $this->adapter()->filter_product_query_vars([], $this->query_settings(), 'loop', 'block'),
            'A singular Product must fail closed instead of using a lower-priority Person.'
        );
    }

    public function test_singular_non_person_blocks_get_the_id_and_global_person_fallbacks(): void
    {
        $this->fixture();
        $this->add_manual_products(self::PERSON_ONE, [self::PRODUCT_ONE]);
        $this->add_post(self::NON_PERSON_POST, 'post');
        $GLOBALS['vgpc_person_products_test_is_singular'] = true;
        $GLOBALS['vgpc_person_products_test_queried_object'] = get_post(self::NON_PERSON_POST);
        $GLOBALS['vgpc_person_products_test_the_id'] = self::PERSON_ONE;
        $GLOBALS['post'] = get_post(self::PERSON_ONE);

        vgpc_test_same(
            $this->canonical_query([0]),
            $this->adapter()->filter_product_query_vars([], $this->query_settings(), 'loop', 'block'),
            'A singular non-Person must stop resolution before get_the_ID or global $post.'
        );
    }

    public function test_get_the_id_person_fallback_is_accepted(): void
    {
        $this->fixture();
        $this->add_manual_products(self::PERSON_ONE, [self::PRODUCT_ONE]);
        $GLOBALS['vgpc_person_products_test_the_id'] = self::PERSON_ONE;

        vgpc_test_same(
            $this->canonical_query([self::PRODUCT_ONE]),
            $this->adapter()->filter_product_query_vars([], $this->query_settings(), 'loop', 'block'),
            'get_the_ID Person fallback must work when preview and singular query contexts are absent.'
        );
    }

    public function test_get_the_id_non_person_fails_closed_before_global_person(): void
    {
        $this->fixture();
        $this->add_manual_products(self::PERSON_ONE, [self::PRODUCT_ONE]);
        $GLOBALS['vgpc_person_products_test_the_id'] = self::PRODUCT_ONE;
        $GLOBALS['post'] = get_post(self::PERSON_ONE);

        vgpc_test_same(
            $this->canonical_query([0]),
            $this->adapter()->filter_product_query_vars([], $this->query_settings(), 'loop', 'block'),
            'A get_the_ID Product must block a lower-priority global Person.'
        );
    }

    public function test_global_person_fallback_is_used_only_after_higher_contexts_are_absent(): void
    {
        $this->fixture();
        $this->add_manual_products(self::PERSON_TWO, [self::PRODUCT_TWO]);
        $GLOBALS['post'] = get_post(self::PERSON_TWO);

        vgpc_test_same(
            $this->canonical_query([self::PRODUCT_TWO]),
            $this->adapter()->filter_product_query_vars([], $this->query_settings(), 'loop', 'block'),
            'Global $post Person is the final fallback when higher-priority IDs are absent.'
        );
    }

    public function test_global_non_person_fails_closed(): void
    {
        $this->fixture();
        $GLOBALS['post'] = get_post(self::PRODUCT_ONE);

        vgpc_test_same(
            $this->canonical_query([0]),
            $this->adapter()->filter_product_query_vars([], $this->query_settings(), 'loop', 'block'),
            'A global non-Person post must fail closed.'
        );
    }

    public function test_unresolved_explicit_preview_blocks_lower_person_context(): void
    {
        $this->fixture();
        $this->add_manual_products(self::PERSON_ONE, [self::PRODUCT_ONE]);
        VGPC_TestPersonProductsBricksDatabase::$page_data = ['preview_or_post_id' => 9999];
        $GLOBALS['vgpc_person_products_test_the_id'] = self::PERSON_ONE;

        vgpc_test_same(
            $this->canonical_query([0]),
            $this->adapter()->filter_product_query_vars([], $this->query_settings(), 'loop', 'block'),
            'A positive preview ID that does not resolve to a Person must block lower-priority fallback.'
        );
    }

    public function test_invalid_person_context_does_not_start_repository_product_queries(): void
    {
        $this->fixture();
        $GLOBALS['vgpc_person_products_test_the_id'] = self::PRODUCT_ONE;
        $query_calls = [];
        $GLOBALS['vgpc_test_get_posts_handler'] = static function (array $args) use (&$query_calls): array {
            $query_calls[] = $args;
            return [];
        };

        vgpc_test_same(
            $this->canonical_query([0]),
            $this->adapter()->filter_product_query_vars([], $this->query_settings(), 'loop', 'block'),
            'A non-Person context must produce the guaranteed-empty query.'
        );
        vgpc_test_same([], $query_calls, 'Invalid context must be rejected before the repository performs relationship queries.');
    }

    public function test_exact_query_marker_is_recognized_as_a_css_token(): void
    {
        $this->fixture();
        $this->add_manual_products(self::PERSON_ONE, [self::PRODUCT_ONE]);
        $GLOBALS['vgpc_person_products_test_the_id'] = self::PERSON_ONE;

        vgpc_test_same(
            $this->canonical_query([self::PRODUCT_ONE]),
            $this->adapter()->filter_product_query_vars([], ['_cssClasses' => 'grid vg-person-products-query card'], 'loop', 'block'),
            'The exact query marker must trigger the canonical Product query.'
        );
    }

    public function test_similar_query_tokens_do_not_trigger_behavior(): void
    {
        $this->fixture();
        $query_vars = ['post_type' => 'post', 'meta_query' => [['key' => 'preserve']]];
        foreach ([
            'vg-person-products-query-extra',
            'prefix-vg-person-products-query',
            'vg-person-products-query-suffix',
            ['vg-person-products-query-extra'],
        ] as $classes) {
            vgpc_test_same(
                $query_vars,
                $this->adapter()->filter_product_query_vars($query_vars, ['_cssClasses' => $classes], 'loop', 'block'),
                'A partial or similar class name must not trigger the Product query adapter.'
            );
        }
    }

    public function test_unmarked_query_remains_unchanged(): void
    {
        $this->fixture();
        $query_vars = ['post_type' => 'medewerker', 'post_status' => 'private', 'posts_per_page' => 4];

        vgpc_test_same(
            $query_vars,
            $this->adapter()->filter_product_query_vars($query_vars, ['_cssClasses' => 'ordinary-card'], 'loop', 'block'),
            'An unmarked Bricks query must remain unchanged.'
        );
    }

    public function test_query_uses_repository_product_order_deterministically(): void
    {
        $this->fixture();
        $this->add_manual_products(self::PERSON_ONE, [self::PRODUCT_THREE, self::PRODUCT_ONE, self::PRODUCT_TWO]);
        $GLOBALS['vgpc_person_products_test_the_id'] = self::PERSON_ONE;

        vgpc_test_same(
            [self::PRODUCT_ONE, self::PRODUCT_TWO, self::PRODUCT_THREE],
            $this->adapter()->filter_product_query_vars([], $this->query_settings(), 'loop', 'block')['post__in'] ?? null,
            'The adapter must preserve the repository Product ID order.'
        );
    }

    public function test_marked_query_is_constrained_to_published_products(): void
    {
        $this->fixture();
        $this->add_manual_products(self::PERSON_ONE, [self::PRODUCT_ONE]);
        $GLOBALS['vgpc_person_products_test_the_id'] = self::PERSON_ONE;
        $query = $this->adapter()->filter_product_query_vars([], $this->query_settings(), 'loop', 'block');

        vgpc_test_same('product', $query['post_type'] ?? null, 'The marked query must target WooCommerce Products.');
        vgpc_test_same('publish', $query['post_status'] ?? null, 'The marked query must return published Products only.');
        vgpc_test_same('post__in', $query['orderby'] ?? null, 'The marked query must preserve repository ordering.');
    }

    public function test_empty_repository_result_uses_post_in_zero(): void
    {
        $this->fixture();
        $GLOBALS['vgpc_person_products_test_the_id'] = self::PERSON_ONE;

        vgpc_test_same(
            [0],
            $this->adapter()->filter_product_query_vars([], $this->query_settings(), 'loop', 'block')['post__in'] ?? null,
            'An empty reverse repository result must never broaden to all Products.'
        );
    }

    public function test_exact_section_marker_hides_empty_frontend_section(): void
    {
        $this->fixture();
        $GLOBALS['vgpc_person_products_test_the_id'] = self::PERSON_ONE;

        vgpc_test_same(
            false,
            $this->adapter()->filter_products_section_render(true, $this->section('layout vg-person-products-section card')),
            'The exact marked frontend section must hide when the repository returns no Products.'
        );
    }

    public function test_section_marker_array_token_is_recognized(): void
    {
        $this->fixture();
        $GLOBALS['vgpc_person_products_test_the_id'] = self::PERSON_ONE;

        vgpc_test_same(
            false,
            $this->adapter()->filter_products_section_render(true, $this->section(['vg-person-products-section'])),
            'An exact section class token in Bricks array settings must be recognized.'
        );
    }

    public function test_similar_section_tokens_do_not_trigger_behavior(): void
    {
        $this->fixture();
        $GLOBALS['vgpc_person_products_test_the_id'] = self::PERSON_ONE;

        foreach ([
            'vg-person-products-section-extra',
            'prefix-vg-person-products-section',
            'vg-person-products-section-suffix',
            ['vg-person-products-section-extra'],
        ] as $classes) {
            vgpc_test_same(
                true,
                $this->adapter()->filter_products_section_render(true, $this->section($classes)),
                'A similar or partial section token must not hide an unmarked section.'
            );
        }
    }

    public function test_unmarked_section_preserves_the_incoming_render_decision(): void
    {
        $this->fixture();

        vgpc_test_same(
            false,
            $this->adapter()->filter_products_section_render(false, $this->section('ordinary-section')),
            'An unmarked section must preserve an incoming false render decision.'
        );
        vgpc_test_same(
            true,
            $this->adapter()->filter_products_section_render(true, $this->section('ordinary-section')),
            'An unmarked section must preserve an incoming true render decision.'
        );
    }

    public function test_empty_section_remains_visible_in_bricks_builder(): void
    {
        $this->fixture();
        $GLOBALS['vgpc_person_products_test_bricks_builder'] = true;

        vgpc_test_same(
            true,
            $this->adapter()->filter_products_section_render(true, $this->section('vg-person-products-section')),
            'The builder context must keep an empty Product section editable.'
        );
    }

    public function test_empty_section_remains_visible_during_bricks_builder_calls(): void
    {
        $this->fixture();
        $GLOBALS['vgpc_person_products_test_bricks_builder_call'] = true;

        vgpc_test_same(
            true,
            $this->adapter()->filter_products_section_render(true, $this->section('vg-person-products-section')),
            'AJAX/REST builder calls must keep an empty Product section editable.'
        );
    }

    public function test_nonempty_section_is_not_hidden_on_frontend(): void
    {
        $this->fixture();
        $this->add_manual_products(self::PERSON_ONE, [self::PRODUCT_ONE]);
        $GLOBALS['vgpc_person_products_test_the_id'] = self::PERSON_ONE;

        vgpc_test_same(
            true,
            $this->adapter()->filter_products_section_render(true, $this->section('vg-person-products-section')),
            'A marked section with canonical Product results must remain visible.'
        );
    }

    public function test_legacy_product_and_event_relationships_do_not_create_reverse_membership(): void
    {
        $this->fixture();
        $this->add_product(self::PRODUCT_ONE, 'manual');
        $this->add_product(self::PRODUCT_TWO, 'event', self::EVENT);
        $this->add_post(self::EVENT, 'tc_events');
        $GLOBALS['vgpc_test_meta'][self::PRODUCT_ONE]['produk_medewerkers'] = [self::PERSON_ONE];
        $GLOBALS['vgpc_test_meta'][self::PRODUCT_ONE]['product_people_override'] = [self::PERSON_ONE];
        $GLOBALS['vgpc_test_meta'][self::PRODUCT_ONE]['_event_name'] = self::EVENT;
        $GLOBALS['vgpc_test_meta'][self::PRODUCT_TWO]['produk_medewerkers'] = [self::PERSON_ONE];
        $GLOBALS['vgpc_test_meta'][self::EVENT]['event_medewerkers'] = [self::PERSON_ONE];
        $GLOBALS['vgpc_test_meta'][self::EVENT]['medewerker'] = [self::PERSON_ONE];
        $GLOBALS['vgpc_person_products_test_the_id'] = self::PERSON_ONE;

        vgpc_test_same(
            [0],
            $this->adapter()->filter_product_query_vars([], $this->query_settings(), 'loop', 'block')['post__in'] ?? null,
            'Legacy Product People, Event, override, and Medewerker metadata must not create reverse membership.'
        );
    }

    public function test_register_adds_only_the_expected_bricks_filters(): void
    {
        $this->fixture();
        $this->adapter()->register();

        vgpc_test_same(4, $GLOBALS['vgpc_test_filters']['bricks/posts/query_vars'][0]['accepted_args'] ?? null, 'The query adapter must accept four Bricks arguments.');
        vgpc_test_same(2, $GLOBALS['vgpc_test_filters']['bricks/element/render'][0]['accepted_args'] ?? null, 'The section adapter must accept two Bricks arguments.');
    }

    private function adapter(): PersonProductsBricks
    {
        if (! class_exists(PersonProductsBricks::class)) {
            throw new RuntimeException('PersonProductsBricks has not been implemented.');
        }

        return new PersonProductsBricks(new ProductContributionRepository());
    }

    private function fixture(): void
    {
        vgpc_test_reset();
        VGPC_TestPersonProductsBricksDatabase::$page_data = ['preview_or_post_id' => 0];
        $GLOBALS['vgpc_person_products_test_is_singular'] = false;
        $GLOBALS['vgpc_person_products_test_queried_object'] = null;
        $GLOBALS['vgpc_person_products_test_the_id'] = 0;
        $GLOBALS['vgpc_person_products_test_bricks_builder'] = false;
        $GLOBALS['vgpc_person_products_test_bricks_builder_call'] = false;
        $GLOBALS['post'] = null;

        acf_add_local_field_group([
            'key' => 'group_person_products_bricks_test',
            'fields' => [
                ['key' => 'field_person_products_mode', 'name' => 'product_people_mode'],
                ['key' => 'field_person_products_source_event', 'name' => 'produk_bron_event'],
                ['key' => 'field_person_products_product', 'name' => 'product_contribution_product'],
                ['key' => 'field_person_products_person', 'name' => 'product_contribution_person'],
                ['key' => 'field_person_products_order', 'name' => 'product_contribution_display_order'],
            ],
        ]);

        $this->add_post(self::PERSON_ONE, 'person');
        $this->add_post(self::PERSON_TWO, 'person');
        $this->add_post(self::PRODUCT_ONE, 'product');
        $this->add_post(self::PRODUCT_TWO, 'product');
        $this->add_post(self::PRODUCT_THREE, 'product');
        $this->add_post(self::NON_PERSON_POST, 'post');
    }

    /** @param list<int> $product_ids */
    private function add_manual_products(int $person_id, array $product_ids): void
    {
        $offset = 0;
        foreach ($product_ids as $product_id) {
            $this->add_product($product_id, 'manual');
            $this->add_contribution(
                self::CONTRIBUTION_BASE + $offset,
                $product_id,
                $person_id,
                $offset
            );
            ++$offset;
        }
    }

    private function add_product(int $product_id, string $mode, int $source_event_id = 0): void
    {
        $this->add_post($product_id, 'product');
        $GLOBALS['vgpc_test_meta'][$product_id]['product_people_mode'] = $mode;
        $GLOBALS['vgpc_test_meta'][$product_id]['produk_bron_event'] = $source_event_id;
    }

    private function add_contribution(int $id, int $product_id, int $person_id, int $display_order): void
    {
        $this->add_post($id, 'product_contribution');
        $GLOBALS['vgpc_test_meta'][$id]['product_contribution_product'] = $product_id;
        $GLOBALS['vgpc_test_meta'][$id]['product_contribution_person'] = $person_id;
        $GLOBALS['vgpc_test_meta'][$id]['product_contribution_display_order'] = $display_order;
        $role = $this->approved_role_id();
        wp_set_object_terms($id, [$role], ProductRoleVocabulary::TAXONOMY, false);
    }

    private function approved_role_id(): int
    {
        $terms = ProductRoleVocabulary::approved_terms();
        $slug = array_key_first($terms);
        $term = get_term_by('slug', $slug, ProductRoleVocabulary::TAXONOMY);
        if (! $term) {
            $term = vgpc_test_add_term($terms[$slug], $slug, 0, ProductRoleVocabulary::TAXONOMY);
        }

        return (int) $term->term_id;
    }

    private function add_post(int $id, string $post_type, string $post_status = 'publish'): void
    {
        $GLOBALS['vgpc_test_posts'][$id] = (object) [
            'ID' => $id,
            'post_type' => $post_type,
            'post_status' => $post_status,
        ];
    }

    private function query_settings(): array
    {
        return ['_cssClasses' => 'vg-person-products-query'];
    }

    private function section(string|array $classes): object
    {
        return (object) ['settings' => ['_cssClasses' => $classes]];
    }

    /** @param list<int> $product_ids */
    private function canonical_query(array $product_ids): array
    {
        return [
            'post_type' => 'product',
            'post_status' => 'publish',
            'post__in' => $product_ids !== [] ? $product_ids : [0],
            'orderby' => 'post__in',
            'posts_per_page' => -1,
        ];
    }
}
