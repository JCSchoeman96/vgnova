<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
$roles_file = dirname(__DIR__) . '/includes/Taxonomy/ContentTopicRoles.php';
if (is_file($roles_file)) {
    require_once $roles_file;
}
require_once __DIR__ . '/ContentTopicTaxonomyTest.php';
require_once __DIR__ . '/ContentTopicRolesTest.php';
require_once __DIR__ . '/ContentTopicMigrationTest.php';

$passed = 0;

foreach ([new ContentTopicTaxonomyTest(), new ContentTopicRolesTest(), new ContentTopicMigrationTest()] as $test) {
    foreach (get_class_methods($test) as $method) {
        if (! str_starts_with($method, 'test_')) {
            continue;
        }

        $test->$method();
        ++$passed;
        echo '.';
    }
}

echo PHP_EOL . $passed . ' content topic tests passed.' . PHP_EOL;
