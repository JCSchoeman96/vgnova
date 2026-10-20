<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$repository_file = dirname(__DIR__) . '/includes/Event/EventParticipationRepository.php';
if (is_file($repository_file)) {
    require_once $repository_file;
}
$product_files = [
    dirname(__DIR__) . '/includes/Product/ProductPeopleFields.php',
    dirname(__DIR__) . '/includes/Product/ProductPeopleResolver.php',
];
foreach ($product_files as $product_file) {
    if (is_file($product_file)) {
        require_once $product_file;
    }
}

require_once __DIR__ . '/ProductPeopleFieldsTest.php';
require_once __DIR__ . '/ProductPeopleResolverTest.php';

$test_suites = [
    new ProductPeopleFieldsTest(),
    new ProductPeopleResolverTest(),
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
    fwrite(STDERR, sprintf('%d of %d Product People tests failed.' . PHP_EOL, $failed, $passed + $failed));
    exit(1);
}

echo sprintf('%d Product People tests passed.' . PHP_EOL, $passed);
