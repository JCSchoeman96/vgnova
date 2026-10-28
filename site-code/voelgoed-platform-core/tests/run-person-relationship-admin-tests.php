<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
foreach ([
    'PersonRelationshipContract', 'PersonRelationshipSchema', 'PersonRelationshipSourceLock',
    'PersonRelationshipRepository', 'PersonRelationshipSourceReader', 'PersonRelationshipValidator',
    'PersonRelationshipIndexer', 'PersonRelationshipService', 'PersonRelationshipAdmin',
] as $class) {
    require_once __DIR__ . '/../includes/Person/' . $class . '.php';
}
require_once __DIR__ . '/../includes/Product/ProductContributionRepository.php';
require_once __DIR__ . '/../includes/Event/EventParticipationRepository.php';
require_once __DIR__ . '/../includes/Product/ProductContributionPairLock.php';
require_once __DIR__ . '/../includes/Product/ProductContributionService.php';
require_once __DIR__ . '/../includes/Product/ProductContributionAdmin.php';
vgpc_test_reset();
require_once __DIR__ . '/PersonRelationshipAdminTest.php';
$test = new PersonRelationshipAdminTest();
foreach (get_class_methods($test) as $method) {
    if (str_starts_with($method, 'test_')) {
        vgpc_test_reset();
        $test->$method();
        echo "PASS {$method}\n";
    }
}
