<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$test_files = [];
$test_iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS)
);

foreach ($test_iterator as $test_file) {
    if (! $test_file->isFile() || ! str_ends_with($test_file->getFilename(), 'Test.php')) {
        continue;
    }

    $path = $test_file->getPathname();
    if ($path === __FILE__) {
        continue;
    }

    $test_files[] = $path;
}

sort($test_files);

$passed = 0;

foreach ($test_files as $test_file) {
    $classes_before = get_declared_classes();
    $assertions_before = (int) ($GLOBALS['vgpc_assertion_count'] ?? 0);
    require_once $test_file;
    $classes_after = get_declared_classes();
    $new_classes = array_diff($classes_after, $classes_before);
    $file_test_count = 0;

    foreach ($new_classes as $class) {
        if (! str_ends_with($class, 'Test')) {
            continue;
        }

        $reflection = new ReflectionClass($class);
        if ($reflection->isAbstract()) {
            continue;
        }

        $test = $reflection->newInstance();
        foreach (get_class_methods($test) as $method) {
            if (! str_starts_with($method, 'test_')) {
                continue;
            }

            $test->$method();
            ++$passed;
            ++$file_test_count;
            echo '.';
        }
    }

    $assertions_after = (int) ($GLOBALS['vgpc_assertion_count'] ?? 0);
    if ($file_test_count === 0 && $assertions_after > $assertions_before) {
        ++$passed;
        echo '.';
    }
}

if ($passed > 0) {
    echo PHP_EOL;
}

echo $passed . ' dashboard tests passed.' . PHP_EOL;
