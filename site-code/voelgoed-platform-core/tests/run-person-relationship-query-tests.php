<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
foreach (['PersonRelationshipContract', 'PersonRelationshipSchema', 'PersonRelationshipSourceLock', 'PersonRelationshipRepository', 'PersonRelationshipSourceReader', 'PersonRelationshipValidator', 'PersonRelationshipIndexer', 'PersonRelationshipService', 'PersonRelationshipReconciler'] as $class) {
    require_once __DIR__ . '/../includes/Person/' . $class . '.php';
}
vgpc_test_reset();
require_once __DIR__ . '/PersonRelationshipQueryTest.php';
$test = new PersonRelationshipQueryTest();
foreach (get_class_methods($test) as $method) {
    if (str_starts_with($method, 'test_')) {
        $test->$method();
        echo "PASS {$method}\n";
    }
}
