<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$implementation_files = [
    dirname(__DIR__) . '/includes/Event/EventParticipationRepository.php',
    dirname(__DIR__) . '/includes/Product/ProductPeopleFields.php',
    dirname(__DIR__) . '/includes/Product/ProductRoleVocabulary.php',
    dirname(__DIR__) . '/includes/Product/ProductRoleSeeder.php',
    dirname(__DIR__) . '/includes/Product/ProductContributionRepository.php',
    dirname(__DIR__) . '/includes/Product/ProductContributionPairLock.php',
    dirname(__DIR__) . '/includes/Product/ProductContributionService.php',
    dirname(__DIR__) . '/includes/Product/ProductContributionAdmin.php',
    dirname(__DIR__) . '/includes/Product/PersonProductsBricks.php',
    dirname(__DIR__) . '/includes/Event/EventParticipationPairLock.php',
];
foreach ($implementation_files as $implementation_file) {
    if (is_file($implementation_file)) {
        require_once $implementation_file;
    }
}

require_once __DIR__ . '/ProductContributionSchemaTest.php';
require_once __DIR__ . '/ProductRoleVocabularyTest.php';
require_once __DIR__ . '/ProductRoleSeederTest.php';
require_once __DIR__ . '/ProductContributionRepositoryTest.php';
require_once __DIR__ . '/ProductContributionPairLockTest.php';
require_once __DIR__ . '/ProductContributionServiceTest.php';
require_once __DIR__ . '/ProductContributionAdminTest.php';
require_once __DIR__ . '/PersonProductsBricksTest.php';

$test_suites = [
    new ProductContributionSchemaTest(),
    new ProductRoleVocabularyTest(),
    new ProductRoleSeederTest(),
    new ProductContributionRepositoryTest(),
    new ProductContributionPairLockTest(),
    new ProductContributionServiceTest(),
    new ProductContributionAdminTest(),
    new PersonProductsBricksTest(),
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
