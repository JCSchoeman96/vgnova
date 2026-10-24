<?php
declare(strict_types=1);

define('VG_PRODUCT_CONTRIBUTION_QA_LIBRARY_ONLY', true);

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
    public string $prefix = 'wp_';
    public string $posts = 'wp_posts';
    public string $postmeta = 'wp_postmeta';
    public string $term_relationships = 'wp_term_relationships';
    public string $wc_product_meta_lookup = 'wp_wc_product_meta_lookup';
    public string $last_error = '';

    public function prepare(string $query, mixed ...$args): string
    {
        foreach ($args as $arg) {
            $replacement = is_int($arg) ? (string) $arg : "'" . addslashes((string) $arg) . "'";
            $query = preg_replace('/%[ds]/', $replacement, $query, 1) ?? $query;
        }
        return $query;
    }

    public function get_var(string $query): int|null
    {
        if (str_contains($query, $this->wc_product_meta_lookup)) {
            if ($GLOBALS['vg_pc_qa_test_lookup_query_error']) {
                $this->last_error = 'simulated lookup-table query failure';
                return null;
            }
            if (preg_match('/product_id\s*=\s*(\d+)/', $query, $match) === 1) {
                return (int) ($GLOBALS['vg_pc_qa_test_lookup_rows'][(int) $match[1]] ?? 0);
            }
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
}

$GLOBALS['wpdb'] = new ProductContributionQaTestWpdb();
$GLOBALS['vg_pc_qa_test_posts'] = [];
$GLOBALS['vg_pc_qa_test_meta'] = [];
$GLOBALS['vg_pc_qa_test_fields'] = [];
$GLOBALS['vg_pc_qa_test_relationships'] = [];
$GLOBALS['vg_pc_qa_test_lookup_rows'] = [];
$GLOBALS['vg_pc_qa_test_lookup_query_error'] = false;

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
    if ($post->post_type === 'product') {
        unset($GLOBALS['vg_pc_qa_test_lookup_rows'][$post_id]);
    }
    return $post;
}

function wc_get_product(int $product_id): false|object
{
    return get_post($product_id) instanceof WP_Post ? (object) ['id' => $product_id] : false;
}

function vg_pc_qa_test_reset(): void
{
    $GLOBALS['vg_pc_qa_test_posts'] = [];
    $GLOBALS['vg_pc_qa_test_meta'] = [];
    $GLOBALS['vg_pc_qa_test_fields'] = [];
    $GLOBALS['vg_pc_qa_test_relationships'] = [];
    $GLOBALS['vg_pc_qa_test_lookup_rows'] = [];
    $GLOBALS['vg_pc_qa_test_lookup_query_error'] = false;
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
    if (! vg_pc_qa_test_throws(static fn () => vg_product_contribution_qa_delete_owned_record($record, $token))) {
        throw new RuntimeException('Cleanup accepted a changed endpoint pair.');
    }
    if (! get_post(803) instanceof WP_Post) {
        throw new RuntimeException('Cleanup deleted a record after its endpoint ownership check failed.');
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

$run_case('cleanup verifies lookup-row deletion and preserves unrelated Product rows', static function (): void {
    vg_pc_qa_test_reset();
    $token = '[QA][AUTOMATED] Product Contribution cleanup-product';
    vg_pc_qa_test_seed(810, 'product', $token . ' owned', $token);
    $GLOBALS['vg_pc_qa_test_lookup_rows'][810] = 1;
    $GLOBALS['vg_pc_qa_test_posts'][811] = new WP_Post(811, 'product', 'unrelated Product');
    $GLOBALS['vg_pc_qa_test_lookup_rows'][811] = 1;
    vg_product_contribution_qa_delete_owned_record(['id' => 810, 'type' => 'product', 'endpoints' => []], $token);
    if (isset($GLOBALS['vg_pc_qa_test_lookup_rows'][810])) {
        throw new RuntimeException('The owned Product lookup row remained after normal deletion.');
    }
    if (($GLOBALS['vg_pc_qa_test_lookup_rows'][811] ?? null) !== 1 || ! get_post(811) instanceof WP_Post) {
        throw new RuntimeException('Cleanup changed an unrelated Product lookup row or post.');
    }
});

$run_case('cleanup fails when an owned Product lookup row remains', static function (): void {
    vg_pc_qa_test_reset();
    $token = '[QA][AUTOMATED] Product Contribution cleanup-residue';
    vg_pc_qa_test_seed(812, 'product', $token . ' owned', $token);
    $GLOBALS['vg_pc_qa_test_lookup_rows'][812] = 1;
    $delete_post_only = static function (int $post_id): void {
        unset(
            $GLOBALS['vg_pc_qa_test_posts'][$post_id],
            $GLOBALS['vg_pc_qa_test_meta'][$post_id],
            $GLOBALS['vg_pc_qa_test_fields'][$post_id],
            $GLOBALS['vg_pc_qa_test_relationships'][$post_id]
        );
    };
    if (! vg_pc_qa_test_throws(static fn () => vg_product_contribution_qa_delete_owned_record(
        ['id' => 812, 'type' => 'product', 'endpoints' => []],
        $token,
        $delete_post_only
    ))) {
        throw new RuntimeException('Cleanup accepted an owned Product with a remaining lookup row.');
    }
    if (($GLOBALS['vg_pc_qa_test_lookup_rows'][812] ?? null) !== 1) {
        throw new RuntimeException('The regression fixture did not retain the lookup row.');
    }
});

$run_case('cleanup treats lookup-table query errors as failures', static function (): void {
    vg_pc_qa_test_reset();
    $token = '[QA][AUTOMATED] Product Contribution cleanup-query-error';
    vg_pc_qa_test_seed(813, 'product', $token . ' owned', $token);
    $GLOBALS['vg_pc_qa_test_lookup_query_error'] = true;
    if (! vg_pc_qa_test_throws(static fn () => vg_product_contribution_qa_delete_owned_record(
        ['id' => 813, 'type' => 'product', 'endpoints' => []],
        $token
    ))) {
        throw new RuntimeException('Cleanup treated a lookup-table query error as an empty table.');
    }
    if (get_post(813) !== null) {
        throw new RuntimeException('The query-error test did not exercise post cleanup.');
    }
});

$run_case('final residue audit detects an owned lookup row after its Product post is absent', static function (): void {
    vg_pc_qa_test_reset();
    $GLOBALS['vg_pc_qa_test_lookup_rows'][814] = 1;
    $residue = vg_product_contribution_qa_product_lookup_residue([
        814 => ['id' => 814, 'type' => 'product', 'endpoints' => []],
    ]);
    if (count($residue) !== 1
        || $residue[0]['id'] !== 814
        || $residue[0]['table'] !== 'wp_wc_product_meta_lookup'
        || $residue[0]['rows'] !== 1
    ) {
        throw new RuntimeException('Final residue audit missed the owned Product lookup row after its post was absent.');
    }
});

$run_case('final residue audit fails closed when the lookup query errors', static function (): void {
    vg_pc_qa_test_reset();
    $GLOBALS['vg_pc_qa_test_lookup_query_error'] = true;
    $residue = vg_product_contribution_qa_product_lookup_residue([
        815 => ['id' => 815, 'type' => 'product', 'endpoints' => []],
    ]);
    if (count($residue) !== 1
        || $residue[0]['id'] !== 815
        || $residue[0]['table'] !== 'wp_wc_product_meta_lookup'
        || ! isset($residue[0]['error'])
    ) {
        throw new RuntimeException('Final residue audit treated a lookup query error as an empty table.');
    }
});

if ($passed !== $total) {
    fwrite(STDERR, 'FAIL Product Contribution QA cleanup safety: ' . $passed . '/' . $total . " passed.\n");
    exit(1);
}
fwrite(STDOUT, 'Product Contribution QA cleanup safety: ' . $passed . '/' . $total . " passed.\n");
