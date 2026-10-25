<?php
declare(strict_types=1);

define('VG_PRODUCT_CONTRIBUTION_QA_LIBRARY_ONLY', true);
define('ABSPATH', __DIR__ . '/');

final class WP_Post
{
    public function __construct(
        public int $ID,
        public string $post_type,
        public string $post_title
    ) {
    }
}

final class ProductContributionQaTestWpdb
{
    public string $posts = 'wp_posts';
    public string $postmeta = 'wp_postmeta';
    public string $term_relationships = 'wp_term_relationships';
    public string $prefix = 'wp_';
    public string $last_error = '';

    public function prepare(string $query, mixed ...$args): string
    {
        foreach ($args as $arg) {
            $replacement = is_int($arg) ? (string) $arg : "'" . addslashes((string) $arg) . "'";
            $query = preg_replace('/%[ds]/', $replacement, $query, 1) ?? $query;
        }
        return $query;
    }

    public function get_var(string $query): mixed
    {
        if (preg_match("/SHOW TABLES LIKE '([^']+)'/", $query, $match) === 1) {
            $table = str_replace(['\\_', '\\%'], ['_', '%'], stripslashes($match[1]));
            return in_array($table, $GLOBALS['vg_pc_qa_test_lookup_tables'], true) ? $table : null;
        }
        if (preg_match('/FROM (wp_wc_product_meta_lookup|wp_wc_product_attributes_lookup) WHERE (?:product_id|product_or_parent_id) = (\d+)/', $query, $match) === 1) {
            return count($GLOBALS['vg_pc_qa_test_lookup_rows'][$match[1]][(int) $match[2]] ?? []);
        }
        if (preg_match('/post_id\s*=\s*(\d+)/', $query, $match) === 1) {
            return count($GLOBALS['vg_pc_qa_test_meta'][(int) $match[1]] ?? []);
        }
        if (preg_match('/object_id\s*=\s*(\d+)/', $query, $match) === 1) {
            return count($GLOBALS['vg_pc_qa_test_relationships'][(int) $match[1]] ?? []);
        }
        $this->last_error = 'unexpected test query';
        return 0;
    }

    public function esc_like(string $text): string
    {
        return addcslashes($text, '_%\\');
    }
}

$GLOBALS['wpdb'] = new ProductContributionQaTestWpdb();
$GLOBALS['vg_pc_qa_test_posts'] = [];
$GLOBALS['vg_pc_qa_test_meta'] = [];
$GLOBALS['vg_pc_qa_test_fields'] = [];
$GLOBALS['vg_pc_qa_test_relationships'] = [];
$GLOBALS['vg_pc_qa_test_lookup_tables'] = [];
$GLOBALS['vg_pc_qa_test_lookup_rows'] = [];
$GLOBALS['vg_pc_qa_test_preserve_lookup_rows_on_delete'] = false;

function get_post(int $post_id): ?WP_Post
{
    return $GLOBALS['vg_pc_qa_test_posts'][$post_id] ?? null;
}

function get_post_meta(int $post_id, string $key, bool $single = false): mixed
{
    return $GLOBALS['vg_pc_qa_test_meta'][$post_id][$key] ?? '';
}

function get_field(string $field, int $post_id, bool $format_value = true): mixed
{
    return $GLOBALS['vg_pc_qa_test_fields'][$post_id][$field] ?? null;
}

function update_field(string $field, mixed $value, int $post_id): bool
{
    $GLOBALS['vg_pc_qa_test_fields'][$post_id][$field] = $value;
    return true;
}

function clean_post_cache(int $post_id): void
{
}

function wp_delete_post(int $post_id, bool $force_delete = false): WP_Post|false
{
    $post = $GLOBALS['vg_pc_qa_test_posts'][$post_id] ?? false;
    if (! $post) {
        return false;
    }
    unset(
        $GLOBALS['vg_pc_qa_test_posts'][$post_id],
        $GLOBALS['vg_pc_qa_test_meta'][$post_id],
        $GLOBALS['vg_pc_qa_test_fields'][$post_id],
        $GLOBALS['vg_pc_qa_test_relationships'][$post_id]
    );
    if (! $GLOBALS['vg_pc_qa_test_preserve_lookup_rows_on_delete']) {
        foreach ($GLOBALS['vg_pc_qa_test_lookup_rows'] as &$rows_by_product) {
            unset($rows_by_product[$post_id]);
        }
        unset($rows_by_product);
    }
    return $post;
}

function add_post_meta(int $post_id, string $key, mixed $value, bool $unique = false): bool
{
    if ($unique && array_key_exists($key, $GLOBALS['vg_pc_qa_test_meta'][$post_id] ?? [])) {
        return false;
    }
    $GLOBALS['vg_pc_qa_test_meta'][$post_id][$key] = $value;
    return true;
}

function wc_get_product(int $product_id): false
{
    return false;
}

function vg_pc_qa_test_reset(): void
{
    $GLOBALS['vg_pc_qa_test_posts'] = [];
    $GLOBALS['vg_pc_qa_test_meta'] = [];
    $GLOBALS['vg_pc_qa_test_fields'] = [];
    $GLOBALS['vg_pc_qa_test_relationships'] = [];
    $GLOBALS['vg_pc_qa_test_lookup_tables'] = [];
    $GLOBALS['vg_pc_qa_test_lookup_rows'] = [];
    $GLOBALS['vg_pc_qa_test_preserve_lookup_rows_on_delete'] = false;
    $GLOBALS['wpdb']->last_error = '';
}

function vg_pc_qa_test_seed(int $id, string $type, string $title, string $token, array $fields = []): void
{
    $GLOBALS['vg_pc_qa_test_posts'][$id] = new WP_Post($id, $type, $title);
    $GLOBALS['vg_pc_qa_test_meta'][$id] = [VG_PRODUCT_CONTRIBUTION_QA_OWNER_META => $token];
    $GLOBALS['vg_pc_qa_test_fields'][$id] = $fields;
    $GLOBALS['vg_pc_qa_test_relationships'][$id] = ['role' => [7]];
}

function vg_pc_qa_test_throws(callable $callback): bool
{
    try {
        $callback();
    } catch (Throwable) {
        return true;
    }
    return false;
}

require_once dirname(__DIR__) . '/product-contribution-qa.php';
require_once dirname(__DIR__, 2) . '/voelgoed-platform-core/includes/Product/ProductContributionRepository.php';

$passed = 0;
$total = 9;
$run_case = static function (string $name, callable $test) use (&$passed): void {
    try {
        $test();
        $passed++;
        fwrite(STDOUT, 'PASS ' . $name . ".\n");
    } catch (Throwable $error) {
        fwrite(STDERR, 'FAIL ' . $name . ': ' . $error->getMessage() . "\n");
        exit(1);
    }
};

$run_case('cleanup deletes only the exact tracked ID and verifies complete removal', static function (): void {
    vg_pc_qa_test_reset();
    $token = '[QA][AUTOMATED] Product Contribution cleanup-a';
    vg_pc_qa_test_seed(801, 'person', $token . ' owned', $token);
    $GLOBALS['vg_pc_qa_test_posts'][802] = new WP_Post(802, 'person', $token . ' similar title');
    $GLOBALS['vg_pc_qa_test_meta'][802] = [];
    $record = ['id' => 801, 'type' => 'person', 'endpoints' => []];
    vg_product_contribution_qa_delete_owned_record($record, $token);
    if (get_post(801) !== null || vg_product_contribution_qa_post_meta_count(801) !== 0 || vg_product_contribution_qa_relationship_count(801) !== 0) {
        throw new RuntimeException('Exact tracked QA record left post, metadata, or relationship residue.');
    }
    if (! get_post(802) instanceof WP_Post) {
        throw new RuntimeException('Cleanup deleted a similar-title record that was not tracked or marked as owned.');
    }
});

$run_case('cleanup refuses a tracked Product Contribution with changed endpoints', static function (): void {
    vg_pc_qa_test_reset();
    $token = '[QA][AUTOMATED] Product Contribution cleanup-b';
    vg_pc_qa_test_seed(803, 'product_contribution', $token . ' — endpoint', $token, [
        'product_contribution_product' => 51,
        'product_contribution_person' => 62,
    ]);
    $record = ['id' => 803, 'type' => 'product_contribution', 'endpoints' => ['product_id' => 51, 'person_id' => 61]];
    try {
        vg_product_contribution_qa_delete_owned_record($record, $token);
    } catch (RuntimeException $error) {
        if ($error->getMessage() !== 'Cleanup refused a Product Contribution with changed endpoints.') {
            throw new RuntimeException('Cleanup failed for an unexpected reason: ' . $error->getMessage());
        }
    }
    if (get_post(803) === null) {
        throw new RuntimeException('Cleanup deleted a record after its endpoint ownership check failed.');
    }
});

$run_case('cleanup accepts matching endpoints and deletes the exact owned contribution', static function (): void {
    vg_pc_qa_test_reset();
    $token = '[QA][AUTOMATED] Product Contribution cleanup-matching';
    vg_pc_qa_test_seed(806, 'product_contribution', $token . ' — matching', $token, [
        'product_contribution_product' => 52,
        'product_contribution_person' => 63,
    ]);
    $record = ['id' => 806, 'type' => 'product_contribution', 'endpoints' => ['product_id' => 52, 'person_id' => 63]];
    vg_product_contribution_qa_delete_owned_record($record, $token);
    if (get_post(806) !== null || vg_product_contribution_qa_post_meta_count(806) !== 0 || vg_product_contribution_qa_relationship_count(806) !== 0) {
        throw new RuntimeException('Cleanup did not remove and verify the matching owned contribution.');
    }
});

$run_case('cleanup refuses a tracked ID whose post type changed', static function (): void {
    vg_pc_qa_test_reset();
    $token = '[QA][AUTOMATED] Product Contribution cleanup-c';
    vg_pc_qa_test_seed(804, 'person', $token . ' wrong-type', $token);
    $record = ['id' => 804, 'type' => 'product', 'endpoints' => []];
    if (! vg_pc_qa_test_throws(static fn () => vg_product_contribution_qa_delete_owned_record($record, $token))) {
        throw new RuntimeException('Cleanup accepted an unexpected post type.');
    }
    if (! get_post(804) instanceof WP_Post) {
        throw new RuntimeException('Cleanup deleted an ID after its post-type ownership check failed.');
    }
});

$run_case('Product cleanup verifies WooCommerce lookup rows by exact ID', static function (): void {
    vg_pc_qa_test_reset();
    $token = '[QA][AUTOMATED] Product Contribution cleanup-lookup';
    vg_pc_qa_test_seed(807, 'product', $token . ' product', $token);
    $GLOBALS['vg_pc_qa_test_lookup_tables'] = ['wp_wc_product_meta_lookup', 'wp_wc_product_attributes_lookup'];
    $GLOBALS['vg_pc_qa_test_lookup_rows'] = [
        'wp_wc_product_meta_lookup' => [807 => ['row']],
        'wp_wc_product_attributes_lookup' => [807 => ['row']],
    ];
    $record = ['id' => 807, 'type' => 'product', 'endpoints' => []];
    $evidence = null;
    vg_product_contribution_qa_delete_owned_record($record, $token, null, $evidence);
    if (
        get_post(807) !== null
        || ($evidence['tables']['wc_product_meta_lookup']['rows'] ?? null) !== 0
        || ($evidence['tables']['wc_product_attributes_lookup']['rows'] ?? null) !== 0
    ) {
        throw new RuntimeException('Product cleanup did not prove zero WooCommerce lookup rows for the owned ID.');
    }
});

$run_case('Product cleanup fails when a WooCommerce lookup row remains', static function (): void {
    vg_pc_qa_test_reset();
    $token = '[QA][AUTOMATED] Product Contribution cleanup-residue';
    vg_pc_qa_test_seed(808, 'product', $token . ' product', $token);
    $GLOBALS['vg_pc_qa_test_lookup_tables'] = ['wp_wc_product_meta_lookup'];
    $GLOBALS['vg_pc_qa_test_lookup_rows'] = ['wp_wc_product_meta_lookup' => [808 => ['row']]];
    $GLOBALS['vg_pc_qa_test_preserve_lookup_rows_on_delete'] = true;
    $record = ['id' => 808, 'type' => 'product', 'endpoints' => []];
    try {
        vg_product_contribution_qa_delete_owned_record($record, $token);
    } catch (RuntimeException $error) {
        if ($error->getMessage() !== 'WooCommerce lookup residue remains for deleted Product 808 in wc_product_meta_lookup.') {
            throw new RuntimeException('Lookup cleanup failed for an unexpected reason: ' . $error->getMessage());
        }
        return;
    }
    throw new RuntimeException('Cleanup accepted a remaining WooCommerce lookup row.');
});

$run_case('final cleanup audit reports lookup residue when the Product post is already absent', static function (): void {
    vg_pc_qa_test_reset();
    $token = '[QA][AUTOMATED] Product Contribution final-lookup-audit';
    $GLOBALS['vg_pc_qa_test_lookup_tables'] = ['wp_wc_product_meta_lookup'];
    $GLOBALS['vg_pc_qa_test_lookup_rows'] = ['wp_wc_product_meta_lookup' => [810 => ['row']]];
    $context = [
        'owned' => [
            'product_contribution' => [],
            'event_participation' => [],
            'product' => [810 => ['id' => 810, 'type' => 'product', 'endpoints' => []]],
            'person' => [],
            'tc_events' => [],
        ],
        'preexisting_api_ids' => [],
        'cleanup' => [
            'errors' => [],
            'woocommerce_lookup' => [],
            'tickera' => ['deleted' => [], 'unexplained' => [], 'errors' => []],
        ],
    ];

    vg_product_contribution_qa_cleanup($context, $token);
    $final_audit = $context['cleanup']['woocommerce_lookup_final'] ?? [];
    $has_lookup_residue = false;
    foreach ($final_audit as $record) {
        if (
            ($record['product_id'] ?? null) === 810
            && ($record['tables']['wc_product_meta_lookup']['rows'] ?? null) === 1
        ) {
            $has_lookup_residue = true;
        }
    }
    $has_cleanup_error = false;
    foreach ($context['cleanup']['errors'] as $error) {
        if (str_contains($error['message'] ?? '', 'Final WooCommerce lookup residue remains for Product 810')) {
            $has_cleanup_error = true;
        }
    }
    if (! $has_lookup_residue || ! $has_cleanup_error) {
        throw new RuntimeException('Final cleanup audit did not report exact lookup residue for the already-absent Product post.');
    }
});

$run_case('finally cleanup runs after a runtime assertion throws', static function (): void {
    vg_pc_qa_test_reset();
    $token = '[QA][AUTOMATED] Product Contribution cleanup-finally';
    vg_pc_qa_test_seed(805, 'person', $token . ' person', $token);
    $record = ['id' => 805, 'type' => 'person', 'endpoints' => []];
    $result = vg_product_contribution_qa_guarded_run(
        static function (): void {},
        static function (): void {
            throw new RuntimeException('simulated assertion failure');
        },
        static function () use ($record, $token): void {
            vg_product_contribution_qa_delete_owned_record($record, $token);
        }
    );
    if (
        $result['mutation_started'] !== true
        || $result['cleanup_attempted'] !== true
        || $result['error'] !== 'simulated assertion failure'
        || get_post(805) !== null
        || vg_product_contribution_qa_post_meta_count(805) !== 0
        || vg_product_contribution_qa_relationship_count(805) !== 0
    ) {
        throw new RuntimeException('The finally boundary did not remove and verify the exact owned record after the assertion failed.');
    }
});

$run_case('temporary endpoint is restored before cleanup after its assertion throws', static function (): void {
    vg_pc_qa_test_reset();
    $token = '[QA][AUTOMATED] Product Contribution cleanup-endpoint-finally';
    vg_pc_qa_test_seed(809, 'product_contribution', $token . ' contribution', $token, [
        \Voelgoed\PlatformCore\Product\ProductContributionRepository::PRODUCT_FIELD => 52,
        \Voelgoed\PlatformCore\Product\ProductContributionRepository::PERSON_FIELD => 63,
    ]);
    $record = [
        'id' => 809,
        'type' => 'product_contribution',
        'endpoints' => ['product_id' => 52, 'person_id' => 63],
    ];
    $assertions = [];
    $expected_error = 'Runtime assertion failed: malformed endpoint is excluded';
    $result = vg_product_contribution_qa_guarded_run(
        static function (): void {},
        static function () use (&$assertions, $expected_error): void {
            vg_product_contribution_qa_with_temporary_contribution_person_endpoint(
                809,
                64,
                63,
                static function () use (&$assertions): void {
                    if ((int) get_field(\Voelgoed\PlatformCore\Product\ProductContributionRepository::PERSON_FIELD, 809) !== 64) {
                        throw new RuntimeException('The malformed endpoint was not installed for the assertion.');
                    }
                    vg_product_contribution_qa_assertion($assertions, 'malformed endpoint is excluded', false);
                }
            );
        },
        static function () use ($record, $token): void {
            vg_product_contribution_qa_delete_owned_record($record, $token);
        }
    );
    if (
        $result['mutation_started'] !== true
        || $result['cleanup_attempted'] !== true
        || $result['error'] !== $expected_error
        || $result['cleanup_errors'] !== []
        || get_post(809) !== null
        || vg_product_contribution_qa_post_meta_count(809) !== 0
        || vg_product_contribution_qa_relationship_count(809) !== 0
    ) {
        throw new RuntimeException('A failed malformed-endpoint assertion did not restore the endpoint before exact-owned cleanup.');
    }
});

if ($passed !== $total) {
    fwrite(STDERR, 'FAIL Product Contribution QA cleanup safety: ' . $passed . '/' . $total . " passed.\n");
    exit(1);
}
fwrite(STDOUT, 'Product Contribution QA cleanup safety: ' . $passed . '/' . $total . " passed.\n");
