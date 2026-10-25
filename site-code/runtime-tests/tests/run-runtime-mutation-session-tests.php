<?php
declare(strict_types=1);

$library_file = dirname(__DIR__, 2) . '/runtime-tools/runtime-mutation-session.php';
$canonical_guard_file = dirname(__DIR__, 2) . '/voelgoed-platform-core/includes/Runtime/RuntimeMutationSession.php';
$core_file = dirname(__DIR__, 2) . '/runtime-tools/runtime-mutation-core.php';
if (is_file($core_file)) {
    require_once $core_file;
}
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

$run_guard_source = file_get_contents($library_file);
$canonical_guard_source = file_get_contents($canonical_guard_file);
if (! is_string($run_guard_source)
    || ! str_contains($run_guard_source, "require_once dirname(__DIR__) . '/voelgoed-platform-core/includes/Runtime/RuntimeMutationSession.php';")
    || ! is_string($canonical_guard_source)
    || ! str_contains($canonical_guard_source, 'function vg_runtime_mutation_session_report(')
    || ! str_contains($canonical_guard_source, 'function vg_runtime_mutation_assert_session(')) {
    fwrite(STDERR, "FAIL Runtime tools must load the canonical Platform Core session guard.");
    fwrite(STDERR, "\nRuntime mutation session tests: 0/1 passed, 1 failed.\n");
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
    if (file_put_contents($lock_file, $encoded_metadata) === false || ! chmod($lock_file, 0600)) {
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
    $handle = VgRuntimeMutationCore::acquireFilesystemLock($lock_file);
    if (! is_resource($handle)) {
        throw new RuntimeException('The session test could not acquire its fixture lock.');
    }

    return ['handle' => $handle];
}

function vg_runtime_mutation_test_stop_lock_holder(array $holder): void
{
    if (is_resource($holder['handle'] ?? null)) {
        flock($holder['handle'], LOCK_UN);
        fclose($holder['handle']);
    }
}

/** @return array<string,mixed> */
function vg_runtime_mutation_test_guard_result(
    ?array $holder,
    string $target,
    ?array $environment,
    ?callable $database_lock_owner,
    bool $assert_api = false
): array {
    if (! is_resource($holder['handle'] ?? null)) {
        if ($assert_api) {
            try {
                return ['threw' => false, 'report' => vg_runtime_mutation_assert_session($target, $environment, $database_lock_owner)];
            } catch (Throwable $error) {
                return ['threw' => true, 'exception' => $error->getMessage()];
            }
        }
        return ['threw' => false, 'report' => vg_runtime_mutation_session_report($target, $environment, $database_lock_owner)];
    }

    $owner = ['present' => $database_lock_owner !== null, 'throws' => false, 'value' => null];
    if ($database_lock_owner !== null) {
        try {
            $lock_name = is_array($environment)
                ? (string) ($environment['VGNOVA_MUTATION_DB_LOCK'] ?? '')
                : (string) (getenv('VGNOVA_MUTATION_DB_LOCK') ?: '');
            $owner['value'] = $database_lock_owner($lock_name);
        } catch (Throwable) {
            $owner['throws'] = true;
        }
    }

    $report_json = $environment === null ? 'null' : base64_encode(json_encode($environment, JSON_THROW_ON_ERROR));
    $owner_json = base64_encode(json_encode($owner, JSON_THROW_ON_ERROR));
    $script = <<<'PHP'
require_once $argv[1];
$environment = $argv[3] === 'null' ? null : json_decode(base64_decode($argv[3]), true, 32, JSON_THROW_ON_ERROR);
$owner = json_decode(base64_decode($argv[4]), true, 32, JSON_THROW_ON_ERROR);
$database_lock_owner = $owner['present'] ? static function (string $lock_name) use ($owner): mixed {
    if ($owner['throws']) {
        throw new RuntimeException('test callback failure');
    }
    return $owner['value'];
} : null;
try {
    $report = $argv[5] === 'assert'
        ? vg_runtime_mutation_assert_session($argv[2], $environment, $database_lock_owner)
        : vg_runtime_mutation_session_report($argv[2], $environment, $database_lock_owner);
    echo json_encode(['threw' => false, 'report' => $report], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    echo json_encode(['threw' => true, 'exception' => $error->getMessage()], JSON_THROW_ON_ERROR);
}
PHP;
    $base_environment = getenv();
    $child_environment = is_array($base_environment) ? $base_environment : [];
    if ($environment !== null) {
        $child_environment = array_replace($child_environment, $environment);
    }
    $mode = $assert_api ? 'assert' : 'report';
    $library = dirname(__DIR__, 2) . '/runtime-tools/runtime-mutation-session.php';
    $process = proc_open(
        [PHP_BINARY, '-r', $script, $library, $target, $report_json, $owner_json, $mode],
        [
            0 => ['file', '/dev/null', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
            3 => $holder['handle'],
        ],
        $pipes,
        null,
        $child_environment
    );
    if (! is_resource($process)) {
        throw new RuntimeException('The session guard child process did not start.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit_code = proc_close($process);
    if ($exit_code !== 0) {
        throw new RuntimeException('The session guard child process failed: ' . trim((string) $stderr));
    }
    $result = json_decode((string) $stdout, true, 32, JSON_THROW_ON_ERROR);
    if (! is_array($result)) {
        throw new RuntimeException('The session guard child returned an invalid result.');
    }
    return $result;
}

/** @return array<string,mixed> */
function vg_runtime_mutation_test_report(?array $holder, string $target, ?array $environment, ?callable $owner): array
{
    $result = vg_runtime_mutation_test_guard_result($holder, $target, $environment, $owner);
    return is_array($result['report'] ?? null) ? $result['report'] : [];
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
        $report = vg_runtime_mutation_test_report($holder,
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
        vg_runtime_mutation_test_assert($report['filesystem_lock_held'] === true, 'The held lock was not detected.');
        vg_runtime_mutation_test_assert($report['errors'] === [], 'A valid session returned errors.');
    } finally {
        if ($holder !== null) {
            vg_runtime_mutation_test_stop_lock_holder($holder);
        }
        vg_runtime_mutation_test_cleanup_fixture($fixture);
    }
});

$run_case('copied session values without inherited lock descriptor are rejected', static function (): void {
    $fixture = vg_runtime_mutation_test_fixture();
    $holder = null;
    try {
        $holder = vg_runtime_mutation_test_start_lock_holder($fixture['lock_file']);
        $script = <<<'PHP'
require_once $argv[1];
$environment = json_decode(base64_decode($argv[2]), true, 32, JSON_THROW_ON_ERROR);
$report = vg_runtime_mutation_session_report(
    $environment['VGNOVA_MUTATION_TARGET'],
    $environment,
    static fn (string $lock_name): string => $environment['VGNOVA_MUTATION_DB_CONNECTION_ID']
);
echo json_encode($report, JSON_THROW_ON_ERROR);
PHP;
        $environment = getenv();
        $environment = is_array($environment) ? $environment : [];
        $environment = array_replace($environment, $fixture['environment']);
        $process = proc_open(
            [PHP_BINARY, '-r', $script, dirname(__DIR__, 2) . '/runtime-tools/runtime-mutation-session.php', base64_encode(json_encode($fixture['environment'], JSON_THROW_ON_ERROR))],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'], 3 => ['file', '/dev/null', 'r+']],
            $pipes,
            null,
            $environment
        );
        vg_runtime_mutation_test_assert(is_resource($process), 'The copied-proof process did not start.');
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        $exit_code = proc_close($process);
        vg_runtime_mutation_test_assert($exit_code === 0, 'The copied-proof process failed: ' . trim((string) $stderr));
        $report = json_decode((string) $stdout, true, 32, JSON_THROW_ON_ERROR);
        vg_runtime_mutation_test_assert($report['valid'] === false, 'A process without the inherited lock descriptor reused copied session values.');
        vg_runtime_mutation_test_assert(
            in_array('filesystem_lock_descriptor_unowned', $report['errors'], true),
            'The missing inherited lock descriptor was not reported.'
        );
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
        $report = vg_runtime_mutation_test_report($holder,
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
        $report = vg_runtime_mutation_test_report($holder,
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
        $report = vg_runtime_mutation_test_report(null,
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
        $report = vg_runtime_mutation_test_report(null,
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
            $report = vg_runtime_mutation_test_report($holder,
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
        $report = vg_runtime_mutation_test_report(null,
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
            $report = vg_runtime_mutation_test_report($holder,
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
        $report = vg_runtime_mutation_test_report($holder,
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
        $report = vg_runtime_mutation_test_report($holder,
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
        $report = vg_runtime_mutation_test_report($holder, $fixture['target'], $fixture['environment'], null);
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
        $result = vg_runtime_mutation_test_guard_result($holder,
            $fixture['target'],
            null,
            vg_runtime_mutation_test_owner($fixture['environment'])
        );
        $report = $result['report'] ?? [];
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
        $result = vg_runtime_mutation_test_guard_result($holder,
            $fixture['target'],
            $fixture['environment'],
            vg_runtime_mutation_test_owner($fixture['environment']),
            true
        );
        $report = $result['report'] ?? [];
        vg_runtime_mutation_test_assert(($result['threw'] ?? true) === false, 'The assert API rejected a valid session.');
        vg_runtime_mutation_test_assert($report['valid'] === true, 'The assert API did not return a valid report.');

        $invalid = vg_runtime_mutation_test_guard_result(
            $holder,
            $fixture['target'],
            $fixture['environment'],
            static fn (string $lock_name): null => null,
            true
        );
        vg_runtime_mutation_test_assert(($invalid['threw'] ?? false) === true, 'The assert API did not throw RuntimeException for an invalid report.');
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
