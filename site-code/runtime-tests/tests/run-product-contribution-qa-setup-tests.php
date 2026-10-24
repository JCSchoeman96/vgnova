<?php
declare(strict_types=1);

define('VG_PRODUCT_CONTRIBUTION_QA_LIBRARY_ONLY', true);
require_once dirname(__DIR__) . '/product-contribution-qa.php';

$passed = 0;
$total = 2;
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

if ($passed !== $total) {
    fwrite(STDERR, 'FAIL Product Contribution QA setup safety: ' . $passed . '/' . $total . " passed.\n");
    exit(1);
}
fwrite(STDOUT, 'Product Contribution QA setup safety: ' . $passed . '/' . $total . " passed.\n");
