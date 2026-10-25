<?php
declare(strict_types=1);

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
    $database_lock_name = vg_runtime_mutation_session_environment_value($environment, 'VGNOVA_MUTATION_DB_LOCK');
    $expected_connection_id = vg_runtime_mutation_session_environment_value(
        $environment,
        'VGNOVA_MUTATION_DB_CONNECTION_ID'
    );

    $errors = [];
    $metadata_session_id = null;
    $observed_connection_id = null;
    $filesystem_lock_held = false;

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

    if ($database_lock_name === null || $database_lock_name === '') {
        $errors[] = 'database_lock_name_missing';
    } elseif (preg_match('/\Avgnova\.mutation\.[a-f0-9]{40}\z/', $database_lock_name) !== 1) {
        $errors[] = 'invalid_database_lock_name';
    }

    $expected_connection_id_normalized = vg_runtime_mutation_session_connection_id($expected_connection_id);
    if ($expected_connection_id_normalized === null) {
        $errors[] = 'database_connection_id_invalid';
    }
    $reported_expected_connection_id = null;
    if (
        $expected_connection_id !== null
        && preg_match('/\A[0-9]+\z/', $expected_connection_id) === 1
    ) {
        $reported_expected_connection_id = $expected_connection_id;
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

                    if (($metadata['schema_version'] ?? null) !== 1) {
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
                    if (($metadata['database_lock_name'] ?? null) !== $database_lock_name) {
                        $errors[] = 'metadata_database_lock_mismatch';
                    }
                    $metadata_connection_id = vg_runtime_mutation_session_connection_id(
                        $metadata['database_connection_id'] ?? null
                    );
                    if (
                        $expected_connection_id_normalized === null
                        || $metadata_connection_id !== $expected_connection_id_normalized
                    ) {
                        $errors[] = 'metadata_database_connection_mismatch';
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

    if ($database_lock_name === null || preg_match('/\Avgnova\.mutation\.[a-f0-9]{40}\z/', $database_lock_name) !== 1) {
        $errors[] = 'database_owner_unavailable';
    } else {
        try {
            if ($database_lock_owner !== null) {
                $observed_connection_id = $database_lock_owner($database_lock_name);
            } else {
                $wpdb = $GLOBALS['wpdb'] ?? null;
                if (
                    ! is_object($wpdb)
                    || ! is_callable([$wpdb, 'prepare'])
                    || ! is_callable([$wpdb, 'get_var'])
                ) {
                    $errors[] = 'database_owner_unavailable';
                } else {
                    $query = $wpdb->prepare('SELECT IS_USED_LOCK(%s)', $database_lock_name);
                    if (! is_string($query) || $query === '') {
                        $errors[] = 'database_owner_query_error';
                    } else {
                        $observed_connection_id = $wpdb->get_var($query);
                    }
                }
            }
        } catch (Throwable) {
            $errors[] = 'database_owner_error';
            $observed_connection_id = null;
        }

        $reported_observed_connection_id = null;
        if (is_int($observed_connection_id)) {
            $reported_observed_connection_id = $observed_connection_id;
        } elseif (
            is_string($observed_connection_id)
            && preg_match('/\A[0-9]+\z/', $observed_connection_id) === 1
        ) {
            $reported_observed_connection_id = $observed_connection_id;
        }
        $observed_connection_id_normalized = vg_runtime_mutation_session_connection_id($observed_connection_id);

        if (
            ! in_array('database_owner_unavailable', $errors, true)
            && ! in_array('database_owner_query_error', $errors, true)
            && ! in_array('database_owner_error', $errors, true)
        ) {
            if ($observed_connection_id_normalized === null) {
                $errors[] = 'database_owner_missing';
            } elseif (
                $expected_connection_id_normalized !== null
                && $observed_connection_id_normalized !== $expected_connection_id_normalized
            ) {
                $errors[] = 'database_owner_mismatch';
            }
        }
        $observed_connection_id = $reported_observed_connection_id;
    }

    $report = [
        'valid' => $errors === [],
        'target' => $target,
        'session_id' => $session_id,
        'lock_file' => $lock_file,
        'database_lock_name' => $database_lock_name,
        'expected_database_connection_id' => $reported_expected_connection_id,
        'observed_database_connection_id' => $observed_connection_id,
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
