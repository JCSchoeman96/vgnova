<?php
declare(strict_types=1);

if (! function_exists('vg_runtime_mutation_session_report')) {

/**
 * Read one allow-listed mutation-session value without inspecting the full environment.
 */
function vg_runtime_mutation_session_environment_value(?array $environment, string $name): ?string
{
    if ($environment !== null) {
        if (array_key_exists($name, $environment)) {
            $value = $environment[$name];
        } else {
            $value = $_ENV[$name] ?? getenv($name);
        }
    } else {
        $value = $_ENV[$name] ?? getenv($name);
    }

    if (is_string($value)) {
        return $value;
    }
    if (is_int($value)) {
        return (string) $value;
    }

    return null;
}

/**
 * Normalize positive decimal connection IDs for safe, numeric equality checks.
 */
function vg_runtime_mutation_session_connection_id(mixed $value): ?string
{
    if (is_int($value)) {
        if ($value <= 0) {
            return null;
        }
        $digits = (string) $value;
    } elseif (is_string($value) && preg_match('/\A[0-9]+\z/', $value) === 1) {
        $digits = $value;
    } else {
        return null;
    }

    $normalized = ltrim($digits, '0');
    return $normalized === '' ? null : $normalized;
}

function vg_runtime_mutation_session_canonical_lock_names(string $target): ?array
{
    global $wpdb;
    $database_name = defined('DB_NAME') ? constant('DB_NAME') : (is_object($wpdb) ? ($wpdb->dbname ?? null) : null);
    $identity = [
        'database_name' => $database_name,
        'home' => function_exists('get_option') ? get_option('home') : null,
        'site' => function_exists('get_option') ? get_option('siteurl') : null,
    ];

    foreach (['database_name', 'home', 'site'] as $field) {
        if (! is_string($identity[$field] ?? null) || $identity[$field] === '') {
            return null;
        }
    }

    $digest = substr(hash('sha256', implode("\n", [
        $target,
        $identity['database_name'],
        $identity['home'],
        $identity['site'],
    ])), 0, 40);
    return [
        'a' => 'vgnova.mutation.a.' . $digest,
        'b' => 'vgnova.mutation.b.' . $digest,
    ];
}

/**
 * Confirm descriptor 3 refers to the validated lock file and shares its active flock.
 */
function vg_runtime_mutation_session_inherited_lock(string $lock_file): bool
{
    static $descriptor = null;

    if (! is_resource($descriptor)) {
        $descriptor = @fopen('php://fd/3', 'r+');
    }
    if (! is_resource($descriptor) || is_link($lock_file)) {
        return false;
    }

    $descriptor_stat = @fstat($descriptor);
    $path_stat = @lstat($lock_file);
    if (! is_array($descriptor_stat)
        || ! is_array($path_stat)
        || ($path_stat['mode'] & 0170000) !== 0100000
        || ($descriptor_stat['dev'] ?? null) !== ($path_stat['dev'] ?? null)
        || ($descriptor_stat['ino'] ?? null) !== ($path_stat['ino'] ?? null)) {
        return false;
    }

    return @flock($descriptor, LOCK_EX | LOCK_NB);
}

/**
 * Validate lock path ownership, mode, and inode identity after opening it.
 *
 * @param resource $handle
 */
function vg_runtime_mutation_session_safe_lock_file(string $lock_file, $handle): bool
{
    if (is_link($lock_file)) {
        return false;
    }

    $path_stat = @lstat($lock_file);
    $handle_stat = @fstat($handle);
    $directory = dirname($lock_file);
    $directory_stat = @lstat($directory);
    if (! is_array($path_stat) || ! is_array($handle_stat) || ! is_array($directory_stat)) {
        return false;
    }

    if (! function_exists('posix_geteuid') || realpath($directory) !== rtrim($directory, '/')) {
        return false;
    }

    $effective_uid = posix_geteuid();
    return ($path_stat['mode'] & 0170000) === 0100000
        && ($directory_stat['mode'] & 0170000) === 0040000
        && ($path_stat['dev'] ?? null) === ($handle_stat['dev'] ?? null)
        && ($path_stat['ino'] ?? null) === ($handle_stat['ino'] ?? null)
        && ($path_stat['uid'] ?? null) === $effective_uid
        && ($directory_stat['uid'] ?? null) === $effective_uid
        && (($path_stat['mode'] & 0077) === 0)
        && (($directory_stat['mode'] & 0077) === 0);
}

/**
 * Return a non-secret diagnostic report for the current administrative mutation session.
 *
 * The database lock owner callback receives the validated advisory-lock name and must
 * return its owning connection ID, or null when no connection owns the lock.
 */
function vg_runtime_mutation_session_report(
    string $expected_target,
    ?array $environment = null,
    ?callable $database_lock_owner = null
): array {
    $target = vg_runtime_mutation_session_environment_value($environment, 'VGNOVA_MUTATION_TARGET');
    $session_id = vg_runtime_mutation_session_environment_value($environment, 'VGNOVA_MUTATION_SESSION_ID');
    $lock_file = vg_runtime_mutation_session_environment_value($environment, 'VGNOVA_MUTATION_LOCK_FILE');
    $database_lock_names = [];
    $expected_connection_ids = [];
    foreach (['a', 'b'] as $slot) {
        $suffix = strtoupper($slot);
        $database_lock_names[$slot] = vg_runtime_mutation_session_environment_value(
            $environment,
            'VGNOVA_MUTATION_DB_LOCK_' . $suffix
        );
        $expected_connection_ids[$slot] = vg_runtime_mutation_session_environment_value(
            $environment,
            'VGNOVA_MUTATION_DB_CONNECTION_ID_' . $suffix
        );
    }

    $errors = [];
    $metadata_session_id = null;
    $observed_connection_ids = ['a' => null, 'b' => null];
    $filesystem_lock_held = false;
    $canonical_lock_names = vg_runtime_mutation_session_canonical_lock_names($expected_target);
    if ($canonical_lock_names === null) {
        $errors[] = 'database_identity_unavailable';
    }

    $target_pattern = '/\A[a-z0-9][a-z0-9-]{0,63}\z/';
    if (preg_match($target_pattern, $expected_target) !== 1) {
        $errors[] = 'invalid_expected_target';
    }
    if ($target === null || $target === '') {
        $errors[] = 'target_missing';
    } elseif ($target !== $expected_target) {
        $errors[] = 'target_mismatch';
    }
    if ($target !== null && preg_match($target_pattern, $target) !== 1) {
        $errors[] = 'invalid_target';
    }

    if ($session_id === null || $session_id === '') {
        $errors[] = 'session_id_missing';
    } elseif (preg_match('/\A[a-f0-9]{32}\z/', $session_id) !== 1) {
        $errors[] = 'invalid_session_id';
    }

    $valid_lock_path = false;
    if ($lock_file === null || $lock_file === '') {
        $errors[] = 'lock_file_missing';
    } elseif (
        str_contains($lock_file, "\0")
        || ! str_starts_with($lock_file, DIRECTORY_SEPARATOR)
        || basename($lock_file) !== $expected_target . '.lock'
        || is_link($lock_file)
    ) {
        $errors[] = 'invalid_lock_file';
    } else {
        $state_home = vg_runtime_mutation_session_environment_value($environment, 'XDG_STATE_HOME');
        if ($state_home === null || trim($state_home) === '') {
            $state_home = vg_runtime_mutation_session_environment_value($environment, 'HOME');
            $state_directory = is_string($state_home) && str_starts_with($state_home, '/')
                ? rtrim($state_home, '/') . '/.local/state/vgnova/runtime-mutation'
                : null;
        } else {
            $state_directory = str_starts_with($state_home, '/')
                ? rtrim($state_home, '/') . '/vgnova/runtime-mutation'
                : null;
        }
        $expected_lock_file = is_string($state_directory)
            ? $state_directory . '/' . $expected_target . '.lock'
            : null;
        if ($expected_lock_file === null || $lock_file !== $expected_lock_file) {
            $errors[] = 'invalid_lock_file_location';
        } else {
            $valid_lock_path = true;
        }
    }

    $expected_connection_ids_normalized = [];
    $reported_expected_connection_ids = [];
    foreach (['a', 'b'] as $slot) {
        $lockName = $database_lock_names[$slot];
        $connectionId = $expected_connection_ids[$slot];
        if ($lockName === null || $lockName === '') {
            $errors[] = 'database_lock_' . $slot . '_name_missing';
        } elseif (preg_match('/\Avgnova\.mutation\.' . $slot . '\.[a-f0-9]{40}\z/', $lockName) !== 1) {
            $errors[] = 'invalid_database_lock_' . $slot . '_name';
        }
        $expected_connection_ids_normalized[$slot] = vg_runtime_mutation_session_connection_id($connectionId);
        if ($expected_connection_ids_normalized[$slot] === null) {
            $errors[] = 'database_connection_id_' . $slot . '_invalid';
        }
        $reported_expected_connection_ids[$slot] = $connectionId !== null && preg_match('/\A[0-9]+\z/', $connectionId) === 1
            ? $connectionId
            : null;
    }

    if ($valid_lock_path) {
        $metadata_handle = @fopen($lock_file, 'r');
        if (! is_resource($metadata_handle) || ! vg_runtime_mutation_session_safe_lock_file($lock_file, $metadata_handle)) {
            if (is_resource($metadata_handle)) {
                fclose($metadata_handle);
            }
            $errors[] = 'lock_file_unsafe';
        } else {
            $metadata_json = stream_get_contents($metadata_handle);
            fclose($metadata_handle);
            if (! is_string($metadata_json)) {
                $errors[] = 'metadata_unreadable';
            } else {
                try {
                    $metadata = json_decode($metadata_json, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    $metadata = null;
                }

                if (! is_array($metadata)) {
                    $errors[] = 'metadata_invalid';
                } else {
                    $metadata_session_value = $metadata['session_id'] ?? null;
                    if (is_string($metadata_session_value)) {
                        $metadata_session_id = $metadata_session_value;
                    }

                    if (($metadata['schema_version'] ?? null) !== 2) {
                        $errors[] = 'metadata_schema_invalid';
                    }
                    if (($metadata['state'] ?? null) !== 'active') {
                        $errors[] = 'metadata_state_invalid';
                    }
                    if (($metadata['target'] ?? null) !== $expected_target) {
                        $errors[] = 'metadata_target_mismatch';
                    }
                    if ($session_id === null || $metadata_session_id !== $session_id) {
                        $errors[] = 'metadata_session_mismatch';
                    }
                    $metadataLocks = $metadata['database_locks'] ?? null;
                    foreach (['a', 'b'] as $slot) {
                        $metadataLock = is_array($metadataLocks) && is_array($metadataLocks[$slot] ?? null)
                            ? $metadataLocks[$slot]
                            : [];
                        if (($metadataLock['name'] ?? null) !== $database_lock_names[$slot]) {
                            $errors[] = 'metadata_database_lock_' . $slot . '_mismatch';
                        }
                        $metadataConnectionId = vg_runtime_mutation_session_connection_id(
                            $metadataLock['connection_id'] ?? null
                        );
                        if (
                            $expected_connection_ids_normalized[$slot] === null
                            || $metadataConnectionId !== $expected_connection_ids_normalized[$slot]
                        ) {
                            $errors[] = 'metadata_database_connection_' . $slot . '_mismatch';
                        }
                    }
                }
            }
        }

        $probe_handle = @fopen($lock_file, 'r+');
        if (! is_resource($probe_handle) || ! vg_runtime_mutation_session_safe_lock_file($lock_file, $probe_handle)) {
            if (is_resource($probe_handle)) {
                fclose($probe_handle);
            }
            $errors[] = 'filesystem_lock_unavailable';
        } else {
            $probe_acquired = @flock($probe_handle, LOCK_EX | LOCK_NB);
            if ($probe_acquired) {
                $filesystem_lock_held = false;
                $errors[] = 'filesystem_lock_not_held';
                if (! @flock($probe_handle, LOCK_UN)) {
                    $errors[] = 'filesystem_probe_release_failed';
                }
            } else {
                $filesystem_lock_held = true;
                if (! vg_runtime_mutation_session_inherited_lock($lock_file)) {
                    $errors[] = 'filesystem_lock_descriptor_unowned';
                }
            }
            fclose($probe_handle);
        }
    } else {
        $errors[] = 'filesystem_lock_unavailable';
    }

    foreach (['a', 'b'] as $slot) {
        $lockName = $database_lock_names[$slot];
        $namePattern = '/\Avgnova\.mutation\.' . $slot . '\.[a-f0-9]{40}\z/';
        if ($lockName === null || preg_match($namePattern, $lockName) !== 1) {
            $errors[] = 'database_owner_' . $slot . '_unavailable';
            continue;
        }
        if ($canonical_lock_names === null || ! hash_equals($canonical_lock_names[$slot], $lockName)) {
            $errors[] = 'database_lock_' . $slot . '_name_not_canonical';
            continue;
        }

        $observed = null;
        $queryFailed = false;
        try {
            if ($database_lock_owner !== null) {
                $observed = $database_lock_owner($lockName);
            } else {
                $wpdb = $GLOBALS['wpdb'] ?? null;
                if (
                    ! is_object($wpdb)
                    || ! is_callable([$wpdb, 'prepare'])
                    || ! is_callable([$wpdb, 'get_var'])
                ) {
                    $errors[] = 'database_owner_' . $slot . '_unavailable';
                    $queryFailed = true;
                } else {
                    $query = $wpdb->prepare('SELECT IS_USED_LOCK(%s)', $lockName);
                    if (! is_string($query) || $query === '') {
                        $errors[] = 'database_owner_' . $slot . '_query_error';
                        $queryFailed = true;
                    } else {
                        $observed = $wpdb->get_var($query);
                    }
                }
            }
        } catch (Throwable) {
            $errors[] = 'database_owner_' . $slot . '_error';
            $queryFailed = true;
        }

        if ($queryFailed) {
            continue;
        }
        $reportedObserved = null;
        if (is_int($observed)) {
            $reportedObserved = $observed;
        } elseif (is_string($observed) && preg_match('/\A[0-9]+\z/', $observed) === 1) {
            $reportedObserved = $observed;
        }
        $observedNormalized = vg_runtime_mutation_session_connection_id($observed);
        if ($observedNormalized === null) {
            $errors[] = 'database_owner_' . $slot . '_missing';
        } elseif (
            $expected_connection_ids_normalized[$slot] !== null
            && $observedNormalized !== $expected_connection_ids_normalized[$slot]
        ) {
            $errors[] = 'database_owner_' . $slot . '_mismatch';
        }
        $observed_connection_ids[$slot] = $reportedObserved;
    }

    $report = [
        'valid' => $errors === [],
        'target' => $target,
        'session_id' => $session_id,
        'lock_file' => $lock_file,
        'database_locks' => [
            'a' => [
                'name' => $database_lock_names['a'],
                'expected_connection_id' => $reported_expected_connection_ids['a'],
                'observed_connection_id' => $observed_connection_ids['a'],
            ],
            'b' => [
                'name' => $database_lock_names['b'],
                'expected_connection_id' => $reported_expected_connection_ids['b'],
                'observed_connection_id' => $observed_connection_ids['b'],
            ],
        ],
        'metadata_session_id' => $metadata_session_id,
        'filesystem_lock_held' => $filesystem_lock_held,
        'errors' => $errors,
    ];

    return $report;
}

/**
 * Require an active mutation session and return its diagnostic report.
 */
function vg_runtime_mutation_assert_session(
    string $expected_target,
    ?array $environment = null,
    ?callable $database_lock_owner = null
): array {
    $report = vg_runtime_mutation_session_report($expected_target, $environment, $database_lock_owner);
    if (! $report['valid']) {
        throw new RuntimeException('Invalid runtime mutation session: ' . implode(', ', $report['errors']) . '.');
    }

    return $report;
}

}
