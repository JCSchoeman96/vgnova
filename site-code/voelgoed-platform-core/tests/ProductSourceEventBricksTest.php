<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Product\ProductPeopleResolver;
use Voelgoed\PlatformCore\Product\ProductSourceEventBricks;

final class ProductSourceEventBricksTest
{
    private const PRODUCT = 401;
    private const NON_PRODUCT = 403;
    private const SOURCE_EVENT = 123;

    public function test_unmarked_source_event_query_is_returned_unchanged(): void
    {
        $this->fixture();
        $query_vars = ['post_type' => 'product', 'meta_query' => [['key' => '_event_name']]];

        vgpc_test_same(
            $query_vars,
            $this->adapter()->filter_source_event_query_vars($query_vars, ['_cssClasses' => 'vg-product-source-event-query-extra'], 'loop', 'block'),
            'A lookalike marker must not rewrite an unmarked source-Event query.'
        );
    }

    public function test_marked_source_event_query_without_product_context_fails_closed(): void
    {
        $this->fixture();
        $GLOBALS['vgpc_test_the_id'] = 0;
        $GLOBALS['post'] = null;

        vgpc_test_same(
            $this->source_event_query([0]),
            $this->adapter()->filter_source_event_query_vars([], ['_cssClasses' => 'vg-product-source-event-query'], 'loop', 'block'),
            'A marked source-Event query without Product context must query no Events.'
        );
    }

    public function test_marked_source_event_query_with_non_product_context_fails_closed(): void
    {
        $this->fixture();
        $GLOBALS['vgpc_test_the_id'] = self::NON_PRODUCT;

        vgpc_test_same(
            $this->source_event_query([0]),
            $this->adapter()->filter_source_event_query_vars([], ['_cssClasses' => 'vg-product-source-event-query'], 'loop', 'block'),
            'A marked source-Event query in a non-Product context must query no Events.'
        );
    }

    public function test_positive_integer_and_digit_string_source_event_values_resolve_identically(): void
    {
        $this->fixture();
        $adapter = $this->adapter();

        foreach ([123, '123'] as $source_event_id) {
            $this->set_source_event(self::PRODUCT, $source_event_id);
            vgpc_test_same(
                $this->source_event_query([self::SOURCE_EVENT]),
                $adapter->filter_source_event_query_vars([], ['_cssClasses' => 'vg-product-source-event-query'], 'loop', 'block'),
                'A positive integer or digit-only source Event value must resolve to its exact Event ID.'
            );
        }
    }

    public function test_malformed_source_event_values_fail_closed_without_broadening(): void
    {
        $this->fixture();
        $adapter = $this->adapter();
        $malformed = [
            [],
            [self::SOURCE_EVENT],
            [self::SOURCE_EVENT, 124],
            (object) ['ID' => self::SOURCE_EVENT],
            0,
            -1,
            0.5,
            true,
            false,
            '',
            ' 123',
            '123 ',
            '+123',
        ];

        foreach ($malformed as $value) {
            $this->set_source_event(self::PRODUCT, $value);
            vgpc_test_same(
                $this->source_event_query([0]),
                $adapter->filter_source_event_query_vars([], ['_cssClasses' => 'vg-product-source-event-query'], 'loop', 'block'),
                'A malformed or multi-valued source Event must fail closed.'
            );
        }
    }

    public function test_source_event_endpoint_validation_requires_tc_events_and_not_trash(): void
    {
        $this->fixture();
        $this->post(124, 'tc_events', 'draft');
        $this->post(125, 'tc_events', 'private');
        $this->post(126, 'tc_events', 'trash');
        $this->post(127, 'post');
        $adapter = $this->adapter();

        foreach ([999, 126, 127] as $invalid_id) {
            $this->set_source_event(self::PRODUCT, $invalid_id);
            vgpc_test_same(
                $this->source_event_query([0]),
                $adapter->filter_source_event_query_vars([], ['_cssClasses' => 'vg-product-source-event-query'], 'loop', 'block'),
                'Missing, trashed, or wrong-type source Events must fail closed.'
            );
        }

        foreach ([124, 125] as $non_public_id) {
            $this->set_source_event(self::PRODUCT, $non_public_id);
            vgpc_test_same(
                $this->source_event_query([$non_public_id]),
                $adapter->filter_source_event_query_vars([], ['_cssClasses' => 'vg-product-source-event-query'], 'loop', 'block'),
                'Draft and private tc_events remain valid because only trash is rejected.'
            );
        }
    }

    public function test_marked_query_discards_inherited_constraints_and_ignores_people_and_commerce_modes(): void
    {
        $this->fixture();
        $this->set_source_event(self::PRODUCT, self::SOURCE_EVENT);
        update_post_meta(self::PRODUCT, 'product_people_mode', 'manual');
        update_post_meta(self::PRODUCT, 'product_people_override', [201]);
        update_post_meta(self::PRODUCT, 'produk_medewerkers', [301]);
        update_post_meta(self::PRODUCT, '_event_name', '999');
        $stale_query = [
            'post_type' => 'product',
            'post__in' => [999],
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => [['key' => '_event_name', 'value' => 999]],
            'tax_query' => [['taxonomy' => 'event_category', 'terms' => [1]]],
            's' => 'unrelated',
            'posts_per_page' => -1,
        ];

        vgpc_test_same(
            $this->source_event_query([self::SOURCE_EVENT]),
            $this->adapter()->filter_source_event_query_vars($stale_query, ['_cssClasses' => 'vg-product-source-event-query'], 'loop', 'block'),
            'Source Event presentation must be independent of Product People mode, legacy data, commerce state, and inherited Bricks constraints.'
        );
    }

    public function test_source_event_resolves_when_product_people_are_unconfigured(): void
    {
        $this->fixture();
        $this->set_source_event(self::PRODUCT, self::SOURCE_EVENT);
        update_post_meta(self::PRODUCT, '_event_name', '999');

        vgpc_test_same(
            $this->source_event_query([self::SOURCE_EVENT]),
            $this->adapter()->filter_source_event_query_vars([], ['_cssClasses' => 'vg-product-source-event-query'], 'loop', 'block'),
            'A valid source Event remains available when Product People are unconfigured.'
        );
    }

    public function test_no_acf_runtime_fails_closed_in_a_separate_process(): void
    {
        $product_file = dirname(__DIR__) . '/includes/Product/ProductSourceEventBricks.php';
        $code = "define('ABSPATH', '/');"
            . "function get_the_ID() { return 401; }"
            . "function get_post_type(\$id = null) { return (int) \$id === 123 ? 'tc_events' : 'product'; }"
            . "function get_post(\$id) { return (int) \$id === 123 ? (object) ['ID' => 123, 'post_type' => 'tc_events', 'post_status' => 'publish'] : false; }"
            . "require " . var_export($product_file, true) . ";"
            . "try { echo json_encode((new Voelgoed\\PlatformCore\\Product\\ProductSourceEventBricks())->filter_source_event_query_vars([], ['_cssClasses' => 'vg-product-source-event-query'], 'loop', 'block')); }"
            . "catch (Throwable \$error) { echo 'THREW: ' . \$error->getMessage(); }";
        $output = [];
        $exit_code = 0;
        exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' 2>&1', $output, $exit_code);

        vgpc_test_same(0, $exit_code, 'The no-ACF source Event process must not crash. Output: ' . implode("\n", $output));
        vgpc_test_same(
            '{"post_type":"tc_events","post__in":[0],"orderby":"post__in","posts_per_page":1}',
            trim(implode("\n", $output)),
            'The no-ACF source Event path must return the exact empty query contract.'
        );
    }

    private function adapter(): ProductSourceEventBricks
    {
        return new ProductSourceEventBricks();
    }

    private function fixture(): void
    {
        vgpc_test_reset();
        VGPC_TestBricksDatabase::$page_data = [];
        $GLOBALS['vgpc_test_is_singular'] = false;
        $GLOBALS['vgpc_test_queried_object'] = null;
        $GLOBALS['vgpc_test_the_id'] = self::PRODUCT;
        $GLOBALS['vgpc_test_bricks_builder'] = false;
        $GLOBALS['vgpc_test_bricks_builder_call'] = false;
        $GLOBALS['post'] = null;

        acf_add_local_field_group([
            'key' => 'group_product_source_event_bricks_test',
            'fields' => [
                ['key' => 'field_produk_bron_event_test', 'name' => ProductPeopleResolver::SOURCE_EVENT_FIELD],
                ['key' => 'field_produk_medewerkers_test', 'name' => 'produk_medewerkers'],
                ['key' => 'field_product_people_override_test', 'name' => 'product_people_override'],
            ],
        ]);

        $this->post(self::PRODUCT, 'product');
        $this->post(self::NON_PRODUCT, 'post');
        $this->post(self::SOURCE_EVENT, 'tc_events');
    }

    private function set_source_event(int $product_id, mixed $source_event_id): void
    {
        update_post_meta($product_id, ProductPeopleResolver::SOURCE_EVENT_FIELD, $source_event_id);
    }

    private function post(int $id, string $post_type, string $post_status = 'publish'): void
    {
        $GLOBALS['vgpc_test_posts'][$id] = (object) [
            'ID' => $id,
            'post_type' => $post_type,
            'post_status' => $post_status,
        ];
    }

    private function source_event_query(array $ids): array
    {
        return [
            'post_type' => 'tc_events',
            'post__in' => $ids,
            'orderby' => 'post__in',
            'posts_per_page' => 1,
        ];
    }
}
