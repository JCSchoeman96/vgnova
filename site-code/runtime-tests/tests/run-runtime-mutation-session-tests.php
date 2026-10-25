<?php
declare(strict_types=1);

$library_file = dirname(__DIR__, 2) . '/runtime-tools/runtime-mutation-session.php';
if (is_file($library_file)) {
    require_once $library_file;
}

if (! function_exists('vg_runtime_mutation_session_report')
    || ! function_exists('vg_runtime_mutation_assert_session')
) {
    fwrite(STDERR, "FAIL Runtime mutation session APIs are available: the session guard implementation is missing.\n");
    fwrite(STDERR, "Runtime mutation session tests: 0/1 passed, 1 failed.\n");
    exit(1);
}

function vg_runtime_mutation_test_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function vg_runtime_mutation_test_fixture(array $metadata_overrides = []): array
{
    $target = 'vgnova-test-10039';
    $session_id = str_repeat('a', 32);
    $directory = sys_get_temp_dir() . '/vg-runtime-mutation-' . bin2hex(random_bytes(8));
    if (! mkdir($directory, 0700, true)) {
        throw new RuntimeException('Could not create a temporary mutation-session fixture.');
    }

    $state_home = $directory . '/state';
    $state_directory = $state_home . '/vgnova/runtime-mutation';
    if (! mkdir($state_directory, 0700, true)) {
        rmdir($directory);
        throw new RuntimeException('Could not create the mutation-session state directory.');
    }
    $lock_file = $state_directory . '/' . $target . '.lock';
    $metadata = array_merge([
        'schema_version' => 1,
        'state' => 'active',
        'target' => $target,
        'session_id' => $session_id,
        'database_lock_name' => 'vgnova.mutation.' . str_repeat('b', 40),
        'database_connection_id' => 12345,
    ], $metadata_overrides);
    $encoded_metadata = json_encode($metadata, JSON_THROW_ON_ERROR);
    if (file_put_contents($lock_file, $encoded_metadata) === false) {
        rmdir($directory);
        throw new RuntimeException('Could not write temporary mutation-session metadata.');
    }

    $environment = [
        'VGNOVA_MUTATION_SESSION_ID' => $session_id,
        'VGNOVA_MUTATION_TARGET' => $target,
        'VGNOVA_MUTATION_DB_LOCK' => 'vgnova.mutation.' . str_repeat('b', 40),
        'VGNOVA_MUTATION_DB_CONNECTION_ID' => '12345',
        'VGNOVA_MUTATION_LOCK_FILE' => $lock_file,
        'XDG_STATE_HOME' => $state_home,
    ];

    return [
        'directory' => $directory,
        'state_home' => $state_home,
        'state_directory' => $state_directory,
        'environment' => $environment,
        'lock_file' => $lock_file,
        'metadata' => $metadata,
        'session_id' => $session_id,
        'target' => $target,
    ];
}

function vg_runtime_mutation_test_cleanup_fixture(array $fixture): void
{
    if (isset($fixture['directory']) && is_dir($fixture['directory'])) {
        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fixture['directory'], FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        ) as $entry) {
            if ($entry->isDir()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($fixture['directory']);
    }
}

function vg_runtime_mutation_test_start_lock_holder(string $lock_file): array
{
    $script = <<<'PHP'
$handle = fopen($argv[1], 'r+');
if ($handle === false || ! flock($handle, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "lock acquisition failed\n");
    exit(2);
}
fwrite(STDOUT, "ready\n");
fflush(STDOUT);
fgets(STDIN);
flock($handle, LOCK_UN);
fclose($handle);
PHP;
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open([PHP_BINARY, '-r', $script, $lock_file], $descriptors, $pipes);
    if (! is_resource($process)) {
        throw new RuntimeException('Could not start the separate lock-holder process.');
    }

    $ready = fgets($pipes[1]);
    if ($ready !== "ready\n") {
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        throw new RuntimeException('The separate lock-holder process failed: ' . trim((string) $error));
    }

    return ['pipes' => $pipes, 'process' => $process];
}

function vg_runtime_mutation_test_stop_lock_holder(array $holder): void
{
    $pipes = $holder['pipes'];
    fwrite($pipes[0], "release\n");
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($holder['process']);
    if ($status !== 0) {
        throw new RuntimeException('The separate lock-holder process did not exit cleanly.');
    }
}

function vg_runtime_mutation_test_owner(array $environment): Closure
{
    return static function (string $lock_name) use ($environment): string {
        if ($lock_name !== $environment['VGNOVA_MUTATION_DB_LOCK']) {
            throw new RuntimeException('The session guard queried an unexpected database lock.');
        }
        return $environment['VGNOVA_MUTATION_DB_CONNECTION_ID'];
    };
}

$passed = 0;
$failed = 0;
$run_case = static function (string $name, callable $test) use (&$passed, &$failed): void {
    try {
        $test();
        ++$passed;
        fwrite(STDOUT, 'PASS ' . $name . ".\n");
    } catch (Throwable $error) {
        ++$failed;
        fwrite(STDERR, 'FAIL ' . $name . ': ' . $error->getMessage() . "\n");
    }
};

$run_case('valid report requires a real lock holder and matching database owner', static function (): void {
    $fixture = vg_runtime_mutation_test_fixture();
    $holder = null;
    try {
        $holder = vg_runtime_mutation_test_start_lock_holder($fixture['lock_file']);
        $report = vg_runtime_mutation_session_report(
            $fixture['target'],
            $fixture['environment'],
            vg_runtime_mutation_test_owner($fixture['environment'])
        );
        $expected_keys = [
            'valid',
            'target',
            'session_id',
            'lock_file',
            'database_lock_name',
            'expected_database_connection_id',
            'observed_database_connection_id',
            'metadata_session_id',
            'filesystem_lock_held',
            'errors',
        ];
        vg_runtime_mutation_test_assert(array_keys($report) === $expected_keys, 'The report fields or their order changed.');
        vg_runtime_mutation_test_assert($report['valid'] === true, 'A held and matching session was rejected.');
        vg_runtime_mutation_test_assert($report['target'] === $fixture['target'], 'The report returned the wrong target.');
        vg_runtime_mutation_test_assert($report['session_id'] === $fixture['session_id'], 'The report returned the wrong session ID.');
        vg_runtime_mutation_test_assert($report['lock_file'] === $fixture['lock_file'], 'The report returned the wrong lock path.');
        vg_runtime_mutation_test_assert(
            $report['database_lock_name'] === $fixture['environment']['VGNOVA_MUTATION_DB_LOCK'],
            'The report returned the wrong database lock name.'
        );
        vg_runtime_mutation_test_assert(
            $report['expected_database_connection_id'] === '12345'
                && $report['observed_database_connection_id'] === '12345',
            'The report did not return both database connection IDs.'
        );
        vg_runtime_mutation_test_assert($report['metadata_session_id'] === $fixture['session_id'], 'Metadata session ID was not reported.');
        vg_runtime_mutation_test_assert($report['filesystem_lock_held'] === true, 'The separate process lock was not detected.');
        vg_runtime_mutation_test_assert($report['errors'] === [], 'A valid session returned errors.');
    } finally {
        if ($holder !== null) {
            vg_runtime_mutation_test_stop_lock_holder($holder);
        }
        vg_runtime_mutation_test_cleanup_fixture($fixture);
    }
});

$run_case('target must equal the expected target', static function (): void {
    $fixture = vg_runtime_mutation_test_fixture();
    $holder = null;
    try {
        $holder = vg_runtime_mutation_test_start_lock_holder($fixture['lock_file']);
        $report = vg_runtime_mutation_session_report(
            'vgnova-other-target',
            $fixture['environment'],
            vg_runtime_mutation_test_owner($fixture['environment'])
        );
        vg_runtime_mutation_test_assert($report['valid'] === false, 'A mismatched target was accepted.');
        vg_runtime_mutation_test_assert(in_array('target_mismatch', $report['errors'], true), 'Target mismatch was not reported.');
    } finally {
        if ($holder !== null) {
            vg_runtime_mutation_test_stop_lock_holder($holder);
        }
        vg_runtime_mutation_test_cleanup_fixture($fixture);
    }
});

$run_case('session ID must use the safe 32-character lowercase hexadecimal format', static function (): void {
    $fixture = vg_runtime_mutation_test_fixture();
    $holder = null;
    try {
        $holder = vg_runtime_mutation_test_start_lock_holder($fixture['lock_file']);
        $environment = $fixture['environment'];
        $environment['VGNOVA_MUTATION_SESSION_ID'] = '../unsafe';
        $report = vg_runtime_mutation_session_report(
            $fixture['target'],
            $environment,
            vg_runtime_mutation_test_owner($environment)
        );
        vg_runtime_mutation_test_assert($report['valid'] === false, 'An unsafe session ID was accepted.');
        vg_runtime_mutation_test_assert(in_array('invalid_session_id', $report['errors'], true), 'Unsafe session ID was not reported.');
    } finally {
        if ($holder !== null) {
            vg_runtime_mutation_test_stop_lock_holder($holder);
        }
        vg_runtime_mutation_test_cleanup_fixture($fixture);
    }
});

$run_case('lock path must be named for the expected target', static function (): void {
    $fixture = vg_runtime_mutation_test_fixture();
    try {
        $wrong_lock_file = $fixture['directory'] . '/wrong-target.lock';
        file_put_contents($wrong_lock_file, json_encode($fixture['metadata'], JSON_THROW_ON_ERROR));
        $environment = $fixture['environment'];
        $environment['VGNOVA_MUTATION_LOCK_FILE'] = $wrong_lock_file;
        $report = vg_runtime_mutation_session_report(
            $fixture['target'],
            $environment,
            vg_runtime_mutation_test_owner($environment)
        );
        vg_runtime_mutation_test_assert($report['valid'] === false, 'A lock path with another target name was accepted.');
        vg_runtime_mutation_test_assert(in_array('invalid_lock_file', $report['errors'], true), 'Invalid lock path was not reported.');
    } finally {
        vg_runtime_mutation_test_cleanup_fixture($fixture);
    }
});

$run_case('lock path must resolve under the configured VGNova state directory', static function (): void {
    $fixture = vg_runtime_mutation_test_fixture();
    try {
        $wrong_directory = $fixture['directory'] . '/other/vgnova/runtime-mutation';
        mkdir($wrong_directory, 0700, true);
        $wrong_lock_file = $wrong_directory . '/' . $fixture['target'] . '.lock';
        file_put_contents($wrong_lock_file, json_encode($fixture['metadata'], JSON_THROW_ON_ERROR));
        $environment = $fixture['environment'];
        $environment['VGNOVA_MUTATION_LOCK_FILE'] = $wrong_lock_file;
        $report = vg_runtime_mutation_session_report(
            $fixture['target'],
            $environment,
            vg_runtime_mutation_test_owner($environment)
        );
        vg_runtime_mutation_test_assert($report['valid'] === false, 'A same-name lock outside state directory was accepted.');
        vg_runtime_mutation_test_assert(in_array('invalid_lock_file_location', $report['errors'], true), 'Out-of-state lock was not reported.');
    } finally {
        vg_runtime_mutation_test_cleanup_fixture($fixture);
    }
});

$run_case('metadata requires schema version one, active state, matching target, and matching session', static function (): void {
    $invalid_metadata = [
        [['schema_version' => 2], 'metadata_schema_invalid'],
        [['state' => 'released'], 'metadata_state_invalid'],
        [['target' => 'another-target'], 'metadata_target_mismatch'],
        [['session_id' => str_repeat('c', 32)], 'metadata_session_mismatch'],
        [['database_lock_name' => 'vgnova.mutation.' . str_repeat('c', 40)], 'metadata_database_lock_mismatch'],
        [['database_connection_id' => 54321], 'metadata_database_connection_mismatch'],
    ];
    foreach ($invalid_metadata as [$overrides, $expected_error]) {
        $fixture = vg_runtime_mutation_test_fixture($overrides);
        $holder = null;
        try {
            $holder = vg_runtime_mutation_test_start_lock_holder($fixture['lock_file']);
            $report = vg_runtime_mutation_session_report(
                $fixture['target'],
                $fixture['environment'],
                vg_runtime_mutation_test_owner($fixture['environment'])
            );
            vg_runtime_mutation_test_assert($report['valid'] === false, 'Invalid lock metadata was accepted.');
            vg_runtime_mutation_test_assert(
                in_array($expected_error, $report['errors'], true),
                'The metadata failure was not reported as ' . $expected_error . '.'
            );
        } finally {
            if ($holder !== null) {
                vg_runtime_mutation_test_stop_lock_holder($holder);
            }
            vg_runtime_mutation_test_cleanup_fixture($fixture);
        }
    }
});

$run_case('an unlocked file fails the filesystem ownership probe', static function (): void {
    $fixture = vg_runtime_mutation_test_fixture();
    try {
        $report = vg_runtime_mutation_session_report(
            $fixture['target'],
            $fixture['environment'],
            vg_runtime_mutation_test_owner($fixture['environment'])
        );
        vg_runtime_mutation_test_assert($report['valid'] === false, 'An unlocked file was accepted as an active session.');
        vg_runtime_mutation_test_assert($report['filesystem_lock_held'] === false, 'The free lock file was reported as held.');
        vg_runtime_mutation_test_assert(in_array('filesystem_lock_not_held', $report['errors'], true), 'Free lock was not reported.');
    } finally {
        vg_runtime_mutation_test_cleanup_fixture($fixture);
    }
});

$run_case('database proof rejects missing, zero, and mismatched owners', static function (): void {
    $fixture = vg_runtime_mutation_test_fixture();
    $holder = null;
    try {
        $holder = vg_runtime_mutation_test_start_lock_holder($fixture['lock_file']);
        $owner_results = [
            [null, 'database_owner_missing'],
            [0, 'database_owner_missing'],
            ['54321', 'database_owner_mismatch'],
        ];
        foreach ($owner_results as [$owner_result, $expected_error]) {
            $report = vg_runtime_mutation_session_report(
                $fixture['target'],
                $fixture['environment'],
                static fn (string $lock_name): mixed => $owner_result
            );
            vg_runtime_mutation_test_assert($report['valid'] === false, 'An invalid database lock owner was accepted.');
            vg_runtime_mutation_test_assert(
                in_array($expected_error, $report['errors'], true),
                'Database owner failure was not reported as ' . $expected_error . '.'
            );
        }
    } finally {
        if ($holder !== null) {
            vg_runtime_mutation_test_stop_lock_holder($holder);
        }
        vg_runtime_mutation_test_cleanup_fixture($fixture);
    }
});

$run_case('database callback errors fail closed without exposing exception text or credentials', static function (): void {
    $fixture = vg_runtime_mutation_test_fixture();
    $holder = null;
    try {
        $holder = vg_runtime_mutation_test_start_lock_holder($fixture['lock_file']);
        $environment = $fixture['environment'];
        $environment['DB_PASSWORD'] = 'fixture-secret-must-not-appear';
        $report = vg_runtime_mutation_session_report(
            $fixture['target'],
            $environment,
            static function (string $lock_name): never {
                throw new RuntimeException('fixture-secret-must-not-appear');
            }
        );
        $encoded_report = json_encode($report, JSON_THROW_ON_ERROR);
        vg_runtime_mutation_test_assert($report['valid'] === false, 'A database callback error was accepted.');
        vg_runtime_mutation_test_assert(in_array('database_owner_error', $report['errors'], true), 'Database callback error was not reported.');
        vg_runtime_mutation_test_assert(
            ! str_contains($encoded_report, 'fixture-secret-must-not-appear'),
            'The report exposed a credential or callback exception message.'
        );
    } finally {
        if ($holder !== null) {
            vg_runtime_mutation_test_stop_lock_holder($holder);
        }
        vg_runtime_mutation_test_cleanup_fixture($fixture);
    }
});

$run_case('malformed database connection IDs are not echoed', static function (): void {
    $fixture = vg_runtime_mutation_test_fixture();
    $holder = null;
    try {
        $holder = vg_runtime_mutation_test_start_lock_holder($fixture['lock_file']);
        $environment = $fixture['environment'];
        $environment['VGNOVA_MUTATION_DB_CONNECTION_ID'] = 'fixture-secret-must-not-appear';
        $report = vg_runtime_mutation_session_report(
            $fixture['target'],
            $environment,
            static fn (string $lock_name): string => 'fixture-secret-must-not-appear'
        );
        $encoded_report = json_encode($report, JSON_THROW_ON_ERROR);
        vg_runtime_mutation_test_assert($report['valid'] === false, 'Malformed database connection IDs were accepted.');
        vg_runtime_mutation_test_assert(
            $report['expected_database_connection_id'] === null
                && $report['observed_database_connection_id'] === null,
            'Malformed database connection ID values were returned.'
        );
        vg_runtime_mutation_test_assert(
            ! str_contains($encoded_report, 'fixture-secret-must-not-appear'),
            'The report echoed a malformed database connection ID.'
        );
    } finally {
        if ($holder !== null) {
            vg_runtime_mutation_test_stop_lock_holder($holder);
        }
        vg_runtime_mutation_test_cleanup_fixture($fixture);
    }
});

$run_case('missing database owner callback outside WordPress fails closed', static function (): void {
    $fixture = vg_runtime_mutation_test_fixture();
    $holder = null;
    try {
        $holder = vg_runtime_mutation_test_start_lock_holder($fixture['lock_file']);
        $report = vg_runtime_mutation_session_report($fixture['target'], $fixture['environment']);
        vg_runtime_mutation_test_assert($report['valid'] === false, 'A session without database proof was accepted.');
        vg_runtime_mutation_test_assert(in_array('database_owner_unavailable', $report['errors'], true), 'Missing database proof was not reported.');
    } finally {
        if ($holder !== null) {
            vg_runtime_mutation_test_stop_lock_holder($holder);
        }
        vg_runtime_mutation_test_cleanup_fixture($fixture);
    }
});

$run_case('null environment reads the five session values and returns no credentials', static function (): void {
    $fixture = vg_runtime_mutation_test_fixture();
    $holder = null;
    $names = array_keys($fixture['environment']);
    $previous_env = [];
    $previous_process_values = [];
    try {
        $holder = vg_runtime_mutation_test_start_lock_holder($fixture['lock_file']);
        foreach ($names as $name) {
            $previous_env[$name] = [array_key_exists($name, $_ENV), $_ENV[$name] ?? null];
            $previous_process_values[$name] = getenv($name);
            $_ENV[$name] = $fixture['environment'][$name];
            putenv($name . '=' . $fixture['environment'][$name]);
        }
        $report = vg_runtime_mutation_session_report(
            $fixture['target'],
            null,
            vg_runtime_mutation_test_owner($fixture['environment'])
        );
        vg_runtime_mutation_test_assert($report['valid'] === true, 'The environment-backed active session was rejected.');
        vg_runtime_mutation_test_assert(! array_key_exists('DB_PASSWORD', $report), 'The report exposed a credential field.');
        vg_runtime_mutation_test_assert(
            ! str_contains(json_encode($report, JSON_THROW_ON_ERROR), 'DB_PASSWORD'),
            'The report exposed an unrelated environment variable name.'
        );
    } finally {
        foreach ($names as $name) {
            if ($previous_env[$name][0] ?? false) {
                $_ENV[$name] = $previous_env[$name][1];
            } else {
                unset($_ENV[$name]);
            }
            if (($previous_process_values[$name] ?? false) === false) {
                putenv($name);
            } else {
                putenv($name . '=' . $previous_process_values[$name]);
            }
        }
        if ($holder !== null) {
            vg_runtime_mutation_test_stop_lock_holder($holder);
        }
        vg_runtime_mutation_test_cleanup_fixture($fixture);
    }
});

$run_case('assert API returns valid reports and throws RuntimeException for invalid reports', static function (): void {
    $fixture = vg_runtime_mutation_test_fixture();
    $holder = null;
    try {
        $holder = vg_runtime_mutation_test_start_lock_holder($fixture['lock_file']);
        $report = vg_runtime_mutation_assert_session(
            $fixture['target'],
            $fixture['environment'],
            vg_runtime_mutation_test_owner($fixture['environment'])
        );
        vg_runtime_mutation_test_assert($report['valid'] === true, 'The assert API did not return a valid report.');

        $threw = false;
        try {
            vg_runtime_mutation_assert_session(
                $fixture['target'],
                $fixture['environment'],
                static fn (string $lock_name): null => null
            );
        } catch (RuntimeException) {
            $threw = true;
        }
        vg_runtime_mutation_test_assert($threw, 'The assert API did not throw RuntimeException for an invalid report.');
    } finally {
        if ($holder !== null) {
            vg_runtime_mutation_test_stop_lock_holder($holder);
        }
        vg_runtime_mutation_test_cleanup_fixture($fixture);
    }
});

if ($failed > 0) {
    fwrite(STDERR, 'Runtime mutation session tests: ' . $passed . '/' . ($passed + $failed) . ' passed, ' . $failed . " failed.\n");
    exit(1);
}

fwrite(STDOUT, 'Runtime mutation session tests: ' . $passed . '/' . ($passed + $failed) . " passed.\n");
