<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$implementation_files = [
    dirname(__DIR__) . '/includes/Product/ProductRoleVocabulary.php',
    dirname(__DIR__) . '/includes/Product/ProductRoleSeeder.php',
];
foreach ($implementation_files as $implementation_file) {
    if (is_file($implementation_file)) {
        require_once $implementation_file;
    }
}

require_once __DIR__ . '/ProductContributionSchemaTest.php';
require_once __DIR__ . '/ProductRoleVocabularyTest.php';
require_once __DIR__ . '/ProductRoleSeederTest.php';

$test_suites = [
    new ProductContributionSchemaTest(),
    new ProductRoleVocabularyTest(),
    new ProductRoleSeederTest(),
];
$passed = 0;
$failed = 0;

foreach ($test_suites as $test) {
    foreach (get_class_methods($test) as $method) {
        if (! str_starts_with($method, 'test_')) {
            continue;
        }

        try {
            $test->$method();
            ++$passed;
            echo 'PASS ' . $method . PHP_EOL;
        } catch (Throwable $error) {
            ++$failed;
            fwrite(STDERR, 'FAIL ' . $method . ': ' . $error->getMessage() . PHP_EOL);
        }
    }
}

if ($failed > 0) {
    fwrite(STDERR, sprintf('%d of %d Product Contribution tests failed.' . PHP_EOL, $failed, $passed + $failed));
    exit(1);
}

echo sprintf('%d Product Contribution tests passed.' . PHP_EOL, $passed);
