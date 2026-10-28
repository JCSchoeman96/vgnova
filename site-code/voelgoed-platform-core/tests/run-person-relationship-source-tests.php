<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/Person/PersonRelationshipContract.php';
require_once __DIR__ . '/../includes/Person/PersonRelationshipSourceReader.php';
require_once __DIR__ . '/../includes/Person/PersonRelationshipValidator.php';
vgpc_test_reset();
require_once __DIR__ . '/PersonRelationshipSourceTest.php';
$test = new PersonRelationshipSourceTest();
foreach (get_class_methods($test) as $method) {
    if (str_starts_with($method, 'test_')) {
        $test->$method();
        echo "PASS {$method}\n";
    }
}
