<?php
declare(strict_types=1);

if (! defined('ABSPATH')) {
    throw new RuntimeException('This entrypoint must run through WP-CLI in WordPress.');
}

$requested_root = getenv('VGNOVA_MUTATION_WP_ROOT');
$resolved_root = is_string($requested_root) ? realpath($requested_root) : false;
$loaded_root = realpath(ABSPATH);
if (! is_string($resolved_root) || ! is_string($loaded_root) || $resolved_root !== $loaded_root) {
    throw new RuntimeException('WP-CLI loaded a different WordPress root than the mutation coordinator validated.');
}

$task4f_action = getenv('VGNOVA_FCM_ACTION');
$task4f_slug = getenv('VGNOVA_FCM_SLUG');
$task4f_run_id = getenv('VGNOVA_FCM_RUN_ID');
if (! is_string($task4f_action) || ! in_array($task4f_action, ['snapshot', 'create', 'cleanup'], true)) {
    throw new RuntimeException('The requested WordPress runtime action is invalid.');
}

if (in_array($task4f_action, ['create', 'cleanup'], true)) {
    if (! is_string($task4f_slug)
        || ! preg_match('/\Aqa-automated-self-featured-integration-[a-f0-9-]{20,64}\z/', $task4f_slug)
        || ! is_string($task4f_run_id)
        || ! preg_match('/\A[a-f0-9]{32}\z/', $task4f_run_id)) {
        throw new RuntimeException('The temporary WordPress runtime action identity is invalid.');
    }

    require_once dirname(__DIR__) . '/runtime-tools/runtime-mutation-session.php';
    $mutation_environment = [];
    foreach ([
        'VGNOVA_MUTATION_SESSION_ID',
        'VGNOVA_MUTATION_TARGET',
        'VGNOVA_MUTATION_DB_LOCK',
        'VGNOVA_MUTATION_DB_CONNECTION_ID',
        'VGNOVA_MUTATION_LOCK_FILE',
    ] as $name) {
        $value = getenv($name);
        if (! is_string($value) || $value === '') {
            throw new RuntimeException('The active VGNova mutation proof is incomplete.');
        }
        $mutation_environment[$name] = $value;
    }
    $GLOBALS['task4f_mutation_environment'] = $mutation_environment;

    // This local WP-CLI process inherits descriptors 3 and 4 from the coordinator.
    vg_runtime_mutation_assert_session('vgnova-local-10039', $mutation_environment);
}

require __DIR__ . '/fcm-self-featured-runtime.php';
