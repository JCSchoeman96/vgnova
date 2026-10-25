<?php
declare(strict_types=1);

define('VG_PRODUCT_CONTRIBUTION_QA_LIBRARY_ONLY', true);
define('ABSPATH', __DIR__ . '/');

final class WP_Post
{
    public function __construct(public int $ID, public string $post_type, public string $post_title)
    {
    }
}

final class WP_Error
{
}

$GLOBALS['vg_pc_qa_test_posts'] = [];
$GLOBALS['vg_pc_qa_test_meta'] = [];

function get_post(int $post_id): ?WP_Post
{
    return $GLOBALS['vg_pc_qa_test_posts'][$post_id] ?? null;
}

function get_post_meta(int $post_id, string $key, bool $single = false): mixed
{
    return $GLOBALS['vg_pc_qa_test_meta'][$post_id][$key] ?? '';
}

function add_post_meta(int $post_id, string $key, mixed $value, bool $unique = false): bool
{
    if ($unique && array_key_exists($key, $GLOBALS['vg_pc_qa_test_meta'][$post_id] ?? [])) {
        return false;
    }
    $GLOBALS['vg_pc_qa_test_meta'][$post_id][$key] = $value;
    return true;
}

function is_wp_error(mixed $value): bool
{
    return $value instanceof WP_Error;
}

require_once dirname(__DIR__) . '/product-contribution-qa.php';
require_once dirname(__DIR__, 2) . '/voelgoed-platform-core/includes/Product/ProductContributionRepository.php';

$passed = 0;
$total = 5;
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

$run_case('preflight failure stays outside the mutation window and creates nothing', static function (): void {
    $created = 0;
    $cleanup_calls = 0;
    $result = vg_product_contribution_qa_guarded_run(
        static function (): void {
            throw new RuntimeException('preflight rejected runtime identity');
        },
        static function () use (&$created): void {
            ++$created;
        },
        static function () use (&$cleanup_calls): void {
            ++$cleanup_calls;
        }
    );
    if (
        $result['mutation_started'] !== false
        || $result['cleanup_attempted'] !== false
        || $created !== 0
        || $cleanup_calls !== 0
        || $result['error'] !== 'preflight rejected runtime identity'
    ) {
        throw new RuntimeException('A preflight failure entered mutation or created QA data.');
    }
});

$run_case('runtime identity rejects a foreign port before mutation', static function (): void {
    $identity = [
        'wordpress_path' => '/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/',
        'home' => 'http://localhost:10049',
        'site' => 'http://localhost:10049',
        'effective_port' => 10049,
        'database' => 'local',
        'database_host' => 'localhost',
        'php' => '8.5.4',
        'platform_core_path' => '/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/plugins/voelgoed-platform-core/',
    ];
    try {
        vg_product_contribution_qa_validate_identity($identity);
    } catch (RuntimeException) {
        return;
    }
    throw new RuntimeException('Runtime identity validation accepted localhost:10049.');
});

$run_case('query_var is disabled only when WordPress receives boolean false', static function (): void {
    if (! function_exists('vg_product_contribution_qa_query_var_is_disabled')) {
        throw new RuntimeException('The strict query_var contract predicate is missing.');
    }
    if (! vg_product_contribution_qa_query_var_is_disabled(false)) {
        throw new RuntimeException('The strict query_var contract predicate rejected boolean false.');
    }
    foreach ([true, 'product_contribution', 'none', null, 0, ''] as $value) {
        if (vg_product_contribution_qa_query_var_is_disabled($value)) {
            throw new RuntimeException('The strict query_var contract predicate accepted a non-false value.');
        }
    }
});

$run_case('unexpected successful service result is tracked and marked by exact ID', static function (): void {
    $GLOBALS['vg_pc_qa_test_posts'][901] = new WP_Post(
        901,
        'product_contribution',
        '[QA][AUTOMATED] Product Contribution product — person'
    );
    $GLOBALS['vg_pc_qa_test_meta'][901] = [];
    $owned = ['product_contribution' => []];
    $created = vg_product_contribution_qa_track_service_contribution_result(
        901,
        301,
        401,
        '[QA][AUTOMATED] Product Contribution',
        $owned
    );
    if (
        $created !== true
        || ($owned['product_contribution'][901]['endpoints'] ?? null) !== ['product_id' => 301, 'person_id' => 401]
        || get_post_meta(901, '_vg_task_8c_product_contribution_qa_owner', true) !== '[QA][AUTOMATED] Product Contribution'
    ) {
        throw new RuntimeException('An unexpected successful service result was not fully registered for guarded cleanup.');
    }
});

$run_case('rejected service result does not create an owned cleanup entry', static function (): void {
    $owned = ['product_contribution' => []];
    $created = vg_product_contribution_qa_track_service_contribution_result(
        new WP_Error(),
        301,
        401,
        '[QA][AUTOMATED] Product Contribution',
        $owned
    );
    if ($created !== false || $owned['product_contribution'] !== []) {
        throw new RuntimeException('A rejected service result created a cleanup entry.');
    }
});

if ($passed !== $total) {
    fwrite(STDERR, 'FAIL Product Contribution QA setup safety: ' . $passed . '/' . $total . " passed.\n");
    exit(1);
}
fwrite(STDOUT, 'Product Contribution QA setup safety: ' . $passed . '/' . $total . " passed.\n");
