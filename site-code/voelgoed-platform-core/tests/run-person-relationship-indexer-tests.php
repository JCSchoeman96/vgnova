<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
foreach (['PersonRelationshipContract', 'PersonRelationshipSchema', 'PersonRelationshipSourceLock', 'PersonRelationshipRepository', 'PersonRelationshipSourceReader', 'PersonRelationshipValidator', 'PersonRelationshipIndexer'] as $class) {
    $path = __DIR__ . '/../includes/Person/' . $class . '.php';
    if (is_file($path)) require_once $path;
}
vgpc_test_reset();
require_once __DIR__ . '/PersonRelationshipIndexerTest.php';
$test = new PersonRelationshipIndexerTest();
foreach (get_class_methods($test) as $method) {
    if (str_starts_with($method, 'test_')) {
        $test->$method();
        echo "PASS {$method}\n";
    }
}
