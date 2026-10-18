<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
$seeder_file = dirname(__DIR__) . '/includes/Event/EventRoleSeeder.php';
if (is_file($seeder_file)) {
    require_once $seeder_file;
}
require_once __DIR__ . '/EventRoleSeederTest.php';

$test = new EventRoleSeederTest();
$passed = 0;
$failed = 0;

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

if ($failed > 0) {
    fwrite(STDERR, sprintf('%d of %d event role seeder tests failed.' . PHP_EOL, $failed, $passed + $failed));
    exit(1);
}

echo sprintf('%d event role seeder tests passed.' . PHP_EOL, $passed);
