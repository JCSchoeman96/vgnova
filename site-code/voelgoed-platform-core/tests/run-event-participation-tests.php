<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$repository_file = dirname(__DIR__) . '/includes/Event/EventParticipationRepository.php';
if (is_file($repository_file)) {
    require_once $repository_file;
}
$pair_lock_file = dirname(__DIR__) . '/includes/Event/EventParticipationPairLock.php';
if (is_file($pair_lock_file)) {
    require_once $pair_lock_file;
}
$role_seeder_file = dirname(__DIR__) . '/includes/Event/EventRoleSeeder.php';
if (is_file($role_seeder_file)) {
    require_once $role_seeder_file;
}
$service_file = dirname(__DIR__) . '/includes/Event/EventParticipationService.php';
if (is_file($service_file)) {
    require_once $service_file;
}
$admin_file = dirname(__DIR__) . '/includes/Event/EventParticipationAdmin.php';
if (is_file($admin_file)) {
    require_once $admin_file;
}

require_once __DIR__ . '/EventParticipationRepositoryTest.php';
require_once __DIR__ . '/EventParticipationSchemaTest.php';
require_once __DIR__ . '/EventRoleSeederTest.php';
require_once __DIR__ . '/EventParticipationServiceTest.php';
require_once __DIR__ . '/EventParticipationAdminTest.php';

$test_suites = [
    new EventParticipationRepositoryTest(),
    new EventParticipationSchemaTest(),
    new EventRoleSeederTest(),
    new EventParticipationServiceTest(),
    new EventParticipationAdminTest(),
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
    fwrite(STDERR, sprintf('%d of %d Event Participation tests failed.' . PHP_EOL, $failed, $passed + $failed));
    exit(1);
}

echo sprintf('%d Event Participation tests passed.' . PHP_EOL, $passed);
