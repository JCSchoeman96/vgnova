<?php
declare(strict_types=1);

$repository_root = dirname(__DIR__, 3);
$parent_path = $repository_root . '/site-code/bin/pe008-diagnose-runtime-session.php';
$command_path = $repository_root . '/site-code/runtime-tools/pe008-wp-cli-session-diagnostic.php';
$deployment_path = $repository_root . '/site-code/bin/pe008-bootstrap-platform-core-runtime.php';
require_once $repository_root . '/site-code/runtime-tools/runtime-mutation-core.php';
require_once $deployment_path;
if (is_file($parent_path)) {
    require_once $parent_path;
}
if (is_file($command_path)) {
    require_once $command_path;
}

$failures = 0;
$tests = 0;
$test = static function (string $name, callable $callback) use (&$failures, &$tests): void {
    try {
        $callback();
        ++$tests;
        fwrite(STDOUT, 'PASS ' . $name . ".\n");
    } catch (Throwable $error) {
        ++$failures;
        fwrite(STDERR, 'FAIL ' . $name . ': ' . $error->getMessage() . "\n");
    }
};
$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

function pe008_diagnostic_test_remove(string $path): void
{
    $stat = @lstat($path);
    if (! is_array($stat)) {
        return;
    }
    if (($stat['mode'] & 0170000) !== 0040000) {
        unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $name) {
        if ($name !== '.' && $name !== '..') {
            pe008_diagnostic_test_remove($path . '/' . $name);
        }
    }
    rmdir($path);
}

function pe008_diagnostic_test_report(bool $valid = false): array
{
    return [
        'valid' => $valid,
        'target' => 'vgnova-local-10039',
        'session_id' => str_repeat('s', 32),
        'lock_file' => '/private/session.lock',
        'database_locks' => [
            'a' => ['name' => 'private-a', 'expected_connection_id' => '401', 'observed_connection_id' => 401],
            'b' => ['name' => 'private-b', 'expected_connection_id' => '402', 'observed_connection_id' => null],
        ],
        'metadata_session_id' => str_repeat('m', 32),
        'filesystem_lock_held' => true,
        'errors' => $valid ? [] : ['filesystem_lock_descriptor_unowned', 'database_owner_b_missing'],
        'unexpected' => 'must-not-be-copied',
    ];
}

$test('diagnostic tools are source-controlled and isolated from deployment and live QA', static function () use ($assert, $parent_path, $command_path): void {
    $assert(is_file($parent_path), 'read-only diagnostic parent has not been added');
    $assert(is_file($command_path), 'WP-CLI diagnostic command has not been added');
    $parent = file_get_contents($parent_path);
    $command = file_get_contents($command_path);
    $assert(is_string($parent) && is_string($command), 'diagnostic source could not be read');
    foreach ([$parent, $command] as $source) {
        $assert(! str_contains($source, 'product-contribution-qa.php'), 'diagnostic tooling refers to live Product Contribution QA');
        $assert(! str_contains($source, 'vg_product_contribution_qa_run'), 'diagnostic tooling invokes live Product Contribution QA');
        foreach (['vg_pe008_bootstrap_run', 'rename(', 'copy(', 'unlink(', 'mkdir(', 'rmdir('] as $forbidden) {
            $assert(! str_contains($source, $forbidden), 'diagnostic tooling contains deployment operation ' . $forbidden);
        }
    }
    $assert(! str_contains($command, 'vg_runtime_mutation_assert_session'), 'diagnostic command calls the assertion instead of reporting validity');
    $assert(str_contains($command, 'vg_runtime_mutation_session_report'), 'diagnostic command does not call the session report API');
});

$test('sanitizer keeps only allow-listed booleans and error codes', static function () use ($assert): void {
    $assert(function_exists('vg_pe008_diagnostic_sanitize_report'), 'diagnostic report sanitizer is missing');
    $safe = vg_pe008_diagnostic_sanitize_report(pe008_diagnostic_test_report());
    $assert($safe === [
        'probe_executed' => true,
        'session_valid' => false,
        'errors' => ['filesystem_lock_descriptor_unowned', 'database_owner_b_missing'],
        'filesystem_lock_held' => true,
        'database_lock_a' => ['owner_matches' => true],
        'database_lock_b' => ['owner_matches' => false],
    ], 'sanitized report differs from the diagnostic contract');
    $serialized = json_encode($safe, JSON_THROW_ON_ERROR);
    foreach (['private', str_repeat('s', 32), str_repeat('m', 32), '401', '402', 'must-not-be-copied'] as $secret) {
        $assert(! str_contains($serialized, $secret), 'sanitized report exposed a private value');
    }
    $command_payload = vg_pe008_wp_cli_session_diagnostic_payload(pe008_diagnostic_test_report());
    $assert($command_payload === $safe, 'WP-CLI command and parent do not share the same safe report semantics');
});

$test('diagnostic distinguishes startup, output, and invalid-session outcomes', static function () use ($assert): void {
    $assert(function_exists('vg_pe008_diagnostic_build_result'), 'diagnostic result classifier is missing');
    $not_started = vg_pe008_diagnostic_build_result(false, null, '', '', 'child_start_failed');
    $assert($not_started['probe_started'] === false && $not_started['child_exit_code'] === null, 'child-start failure was represented as a session result');
    $failed_output = vg_pe008_diagnostic_build_result(true, 9, '{"probe_executed":true,"session_valid":false,"errors":["target_missing"],"filesystem_lock_held":false,"database_lock_a":{"owner_matches":false},"database_lock_b":{"owner_matches":false}}', 'WP-CLI failed', null);
    $assert($failed_output['probe_started'] === true && $failed_output['child_exit_code'] === 9, 'nonzero child exit was lost');
    $assert($failed_output['child_stderr_empty'] === false && $failed_output['diagnostic_json_parsed'] === true, 'stderr and parse result were collapsed');
    $assert($failed_output['process_error_code'] === 'child_nonzero_exit', 'nonzero process failure was not classified');
    $assert(($failed_output['diagnostic']['session_valid'] ?? true) === false, 'parsed invalid-session report was discarded');
    $invalid_session = vg_pe008_diagnostic_build_result(true, 0, json_encode([
        'probe_executed' => true,
        'session_valid' => false,
        'errors' => ['filesystem_lock_not_held'],
        'filesystem_lock_held' => false,
        'database_lock_a' => ['owner_matches' => false],
        'database_lock_b' => ['owner_matches' => false],
    ], JSON_THROW_ON_ERROR), '', null);
    $assert($invalid_session['probe_started'] === true && $invalid_session['child_exit_code'] === 0, 'invalid session did not remain successful probe data');
    $assert($invalid_session['child_stderr_empty'] === true && $invalid_session['diagnostic_json_parsed'] === true, 'valid diagnostic execution was marked as a process failure');
    $assert($invalid_session['diagnostic']['session_valid'] === false, 'session validity was confused with command success');
    $invalid_session['raw_capture_saved'] = true;
    $assert(vg_pe008_diagnostic_exit_code($invalid_session) === 0, 'valid diagnostic command exited unsuccessfully because the session data is invalid');
    $legacy_nonzero = ['exit_code' => 1, 'stdout' => '', 'stderr' => 'session assertion failed'];
    $legacy_stderr = ['exit_code' => 0, 'stdout' => 'guard passed', 'stderr' => 'PHP Warning'];
    $assert(vg_pe008_subprocess_output_is_clean($legacy_nonzero) === false, 'legacy production guard accepted a nonzero child');
    $assert(vg_pe008_subprocess_output_is_clean($legacy_stderr) === false, 'legacy production guard accepted child stderr');
    $assert($failed_output['diagnostic']['session_valid'] === false && $failed_output['child_exit_code'] === 9 && $failed_output['child_stderr_empty'] === false, 'diagnostic still conflates a session report with subprocess failure details');
});

$test('diagnostic command uses strict staged path checks and controlled WP-CLI bootstrap', static function () use ($assert, $command_path): void {
    $source = file_get_contents($command_path);
    $assert(is_string($source), 'diagnostic command source is unreadable');
    foreach (["WP_CLI::add_hook('before_wp_load'", 'DISABLE_WP_CRON', "define('WPMU_PLUGIN_DIR', '/dev/null')", "WP_CLI::add_command('pe008 session-diagnostic'", "'when' => 'after_wp_load'", 'WP_CLI::line', 'RuntimeMutationSession.php'] as $required) {
        $assert(str_contains($source, $required), 'diagnostic command is missing controlled behavior: ' . $required);
    }
    $assert(! str_contains($source, 'WP_CLI::success'), 'diagnostic command may emit additional success text');
    $assert(str_contains($source, '--skip-plugins') === false, 'WP-CLI bootstrap flags belong in the WordPress-free parent command');
    $assert(! str_contains($source, 'wp-load.php') && ! str_contains($source, 'wp-settings.php'), 'diagnostic command bootstraps WordPress itself');
    $root = sys_get_temp_dir() . '/pe008-diagnostic-path-' . bin2hex(random_bytes(5));
    $stage = $root . '/app/voelgoed-platform-core.pe008-stage-fixture';
    mkdir($stage . '/includes/Runtime', 0700, true);
    $guard = $stage . '/includes/Runtime/RuntimeMutationSession.php';
    file_put_contents($guard, '<?php');
    try {
        $realpath = realpath($guard);
        $assert(is_string($realpath) && vg_pe008_wp_cli_session_diagnostic_guard_path_is_valid('vgnova-local-10039', $guard, $realpath, $root . '/app'), 'exact fixture stage guard was rejected');
        $assert(! vg_pe008_wp_cli_session_diagnostic_guard_path_is_valid('other-target', $guard, $realpath, $root . '/app'), 'wrong target was accepted');
        $assert(! vg_pe008_wp_cli_session_diagnostic_guard_path_is_valid('vgnova-local-10039', $root . '/other.php', $realpath, $root . '/app'), 'guard path differing from its real path was accepted');
    } finally {
        pe008_diagnostic_test_remove($root);
    }
});

$test('diagnostic parent preserves session, socket, config isolation, and descriptor boundaries', static function () use ($assert): void {
    $assert(function_exists('vg_pe008_diagnostic_command'), 'diagnostic WP-CLI command builder is missing');
    $assert(function_exists('vg_pe008_diagnostic_subprocess_environment'), 'diagnostic child environment builder is missing');
    $library = dirname(__DIR__, 2) . '/runtime-tools/pe008-wp-cli-session-diagnostic.php';
    $guard = dirname(__DIR__, 2) . '/voelgoed-platform-core/includes/Runtime/RuntimeMutationSession.php';
    $default_command = vg_pe008_diagnostic_command('/usr/bin/wp', '/tmp/wp', $library, $guard, null);
    $assert(in_array('--skip-packages', $default_command, true) && in_array('--skip-plugins', $default_command, true) && in_array('--skip-themes', $default_command, true), 'controlled WP-CLI skip flags are missing');
    $assert(in_array('session-diagnostic', $default_command, true), 'diagnostic command name is missing');
    $assert(in_array('vgnova-local-10039', $default_command, true) && in_array($guard, $default_command, true), 'target or staged guard path was dropped');
    $assert(! in_array('eval', $default_command, true) && ! in_array('eval-file', $default_command, true), 'diagnostic uses an early WP-CLI eval command');
    $base = [
        'PATH' => '/usr/bin',
        'HOME' => '/tmp/pe008-diagnostic-home',
        'VGNOVA_MUTATION_SESSION_ID' => str_repeat('a', 32),
        'VGNOVA_MUTATION_TARGET' => 'vgnova-local-10039',
        'VGNOVA_MUTATION_DB_LOCK_A' => 'vgnova.mutation.a.' . str_repeat('b', 40),
        'VGNOVA_MUTATION_DB_CONNECTION_ID_A' => '41',
        'VGNOVA_MUTATION_DB_LOCK_B' => 'vgnova.mutation.b.' . str_repeat('c', 40),
        'VGNOVA_MUTATION_DB_CONNECTION_ID_B' => '42',
        'VGNOVA_MUTATION_LOCK_FILE' => '/tmp/pe008-diagnostic-home/.local/state/vgnova/runtime-mutation/vgnova-local-10039.lock',
        'MYSQL_PWD' => 'must-not-propagate',
        'DB_PASSWORD' => 'must-not-propagate',
    ];
    $normal = vg_pe008_diagnostic_subprocess_environment($base, $library);
    $assert(! isset($normal['VGNOVA_DB_SOCKET']), 'normal DB transport retained an absent socket override');
    $base['VGNOVA_DB_SOCKET'] = '/tmp/local-mysql.sock';
    $override = vg_pe008_diagnostic_subprocess_environment($base, $library);
    $assert(($override['VGNOVA_DB_SOCKET'] ?? null) === '/tmp/local-mysql.sock', 'socket override was dropped');
    $assert(($override['VGNOVA_MUTATION_SESSION_ID'] ?? null) === str_repeat('a', 32), 'PE-007 session proof was dropped');
    $assert(($override['VGNOVA_MUTATION_DB_CONNECTION_ID_B'] ?? null) === '42', 'second DB-owner identity was dropped');
    $assert(($override['WP_CLI_CONFIG_PATH'] ?? null) === '/dev/null' && ($override['WP_CLI_SYSTEM_SETTINGS_PATH'] ?? null) === '/dev/null', 'WP-CLI config isolation was dropped');
    $assert(($override['WP_CLI_REQUIRE'] ?? null) === $library, 'diagnostic command library was not required');
    $assert(! isset($override['MYSQL_PWD']) && ! isset($override['DB_PASSWORD']), 'database credentials entered child environment');
    $parsed = vg_pe008_diagnostic_parse_options([
        '--wp-root=/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public',
        '--stage=/home/jcschoeman96/Local Sites/voelgoed-novamira/app/voelgoed-platform-core.pe008-stage-test',
        '--capture-dir=/home/jcschoeman96/.cache/vgnova/pe008-test',
    ]);
    $assert(($parsed['wordpress_root'] ?? null) === '/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public', 'parent CLI parser lost WordPress root');
    $assert(vg_pe008_diagnostic_parse_options(['--wp-root=/tmp/a', '--wp-root=/tmp/b', '--stage=/tmp/c', '--capture-dir=/tmp/d']) === null, 'duplicate parent CLI arguments were accepted');
});

$test('probe waits for its child and passes only FD3 with sanitized session transport', static function () use ($assert, $repository_root, $parent_path, $command_path): void {
    $assert(function_exists('vg_pe008_diagnostic_run'), 'diagnostic subprocess runner is missing');
    $root = sys_get_temp_dir() . '/pe008-diagnostic-' . bin2hex(random_bytes(6));
    mkdir($root, 0700);
    mkdir($root . '/app/public', 0700, true);
    $stage = $root . '/app/voelgoed-platform-core.pe008-stage-fixture';
    mkdir($stage . '/includes/Runtime', 0700, true);
    file_put_contents($stage . '/includes/Runtime/RuntimeMutationSession.php', '<?php');
    mkdir($root . '/capture', 0700);
    $lock_path = $root . '/vgnova-local-10039.lock';
    file_put_contents($lock_path, 'fixture');
    chmod($lock_path, 0600);
    for ($descriptor = 4; $descriptor <= 8; ++$descriptor) {
        file_put_contents($root . '/internal-' . $descriptor, 'fixture');
    }
    $record_path = $root . '/child-observed.json';
    $done_path = $root . '/child.done';
    $fake_wp = $root . '/wp';
    file_put_contents($fake_wp, "#!/usr/bin/env php\n" . <<<'PHP'
<?php
declare(strict_types=1);
$root = (string) getenv('TMPDIR');
$fd = @fopen('php://fd/3', 'r+');
$fd_stat = is_resource($fd) ? @fstat($fd) : false;
$lock_stat = @stat((string) getenv('VGNOVA_MUTATION_LOCK_FILE'));
$leaks = [];
for ($number = 4; $number <= 8; ++$number) {
    $expected = @stat($root . '/internal-' . $number);
    $actual = @stat('/proc/self/fd/' . $number);
    if (is_array($expected) && is_array($actual)
        && ($expected['dev'] ?? null) === ($actual['dev'] ?? null)
        && ($expected['ino'] ?? null) === ($actual['ino'] ?? null)) {
        $leaks[] = $number;
    }
}
$arguments = array_slice($argv, 1);
$record = [
    'same_fd3_inode' => is_array($fd_stat) && is_array($lock_stat) && $fd_stat['dev'] === $lock_stat['dev'] && $fd_stat['ino'] === $lock_stat['ino'],
    'fd3_lock_held' => is_resource($fd) && @flock($fd, LOCK_EX | LOCK_NB),
    'leaked_descriptors' => $leaks,
    'session_id' => getenv('VGNOVA_MUTATION_SESSION_ID') ?: null,
    'target' => getenv('VGNOVA_MUTATION_TARGET') ?: null,
    'lock_a' => getenv('VGNOVA_MUTATION_DB_LOCK_A') ?: null,
    'connection_a' => getenv('VGNOVA_MUTATION_DB_CONNECTION_ID_A') ?: null,
    'lock_b' => getenv('VGNOVA_MUTATION_DB_LOCK_B') ?: null,
    'connection_b' => getenv('VGNOVA_MUTATION_DB_CONNECTION_ID_B') ?: null,
    'lock_file' => getenv('VGNOVA_MUTATION_LOCK_FILE') ?: null,
    'socket' => getenv('VGNOVA_DB_SOCKET') ?: null,
    'config_path' => getenv('WP_CLI_CONFIG_PATH') ?: null,
    'system_path' => getenv('WP_CLI_SYSTEM_SETTINGS_PATH') ?: null,
    'require_path' => getenv('WP_CLI_REQUIRE') ?: null,
    'mysql_pwd' => getenv('MYSQL_PWD') ?: null,
    'db_password' => getenv('DB_PASSWORD') ?: null,
    'arguments_have_secret' => count(array_filter($arguments, static fn (string $value): bool => str_contains($value, 'must-not-leak'))) > 0,
    'command_present' => in_array('session-diagnostic', $arguments, true),
    'arguments' => $arguments,
    'pid' => getmypid(),
];
file_put_contents($root . '/child-observed.json', json_encode($record, JSON_THROW_ON_ERROR));
file_put_contents($root . '/child.done', 'done');
usleep(100000);
fwrite(STDOUT, json_encode([
    'probe_executed' => true,
    'session_valid' => false,
    'errors' => ['filesystem_lock_descriptor_unowned'],
    'filesystem_lock_held' => false,
    'database_lock_a' => ['owner_matches' => false],
    'database_lock_b' => ['owner_matches' => false],
], JSON_THROW_ON_ERROR) . "\n");
PHP);
    chmod($fake_wp, 0700);

    $worker = $root . '/worker.php';
    file_put_contents($worker, <<<'PHP'
<?php
declare(strict_types=1);
require $argv[1];
$root = $argv[2];
$environment = [
    'PATH' => (string) getenv('PATH'),
    'HOME' => $root,
    'TMPDIR' => $root,
    'VGNOVA_MUTATION_SESSION_ID' => str_repeat('a', 32),
    'VGNOVA_MUTATION_TARGET' => 'vgnova-local-10039',
    'VGNOVA_MUTATION_DB_LOCK_A' => 'vgnova.mutation.a.' . str_repeat('b', 40),
    'VGNOVA_MUTATION_DB_CONNECTION_ID_A' => '41',
    'VGNOVA_MUTATION_DB_LOCK_B' => 'vgnova.mutation.b.' . str_repeat('c', 40),
    'VGNOVA_MUTATION_DB_CONNECTION_ID_B' => '42',
    'VGNOVA_MUTATION_LOCK_FILE' => $root . '/vgnova-local-10039.lock',
    'VGNOVA_DB_SOCKET' => '/tmp/local-mysql.sock',
    'MYSQL_PWD' => 'must-not-leak',
    'DB_PASSWORD' => 'must-not-leak',
];
$options = [
    'wordpress_root' => $root . '/app/public',
    'stage' => $root . '/app/voelgoed-platform-core.pe008-stage-fixture',
    'capture_directory' => $root . '/capture',
];
$result = vg_pe008_diagnostic_run($options, $environment, $root . '/wp', $root . '/app', $root . '/capture');
$observed = json_decode((string) file_get_contents($root . '/child-observed.json'), true, 16, JSON_THROW_ON_ERROR);
$pid = $observed['pid'] ?? null;
$result['test_child_reaped'] = is_int($pid) && function_exists('posix_kill') && ! @posix_kill($pid, 0);
$result['test_child_done_marker'] = is_file($root . '/child.done');
fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR) . "\n");
PHP);

    try {
        $lock = fopen($lock_path, 'r+');
        $assert(is_resource($lock) && flock($lock, LOCK_EX | LOCK_NB), 'test coordinator could not lock FD3 fixture');
        $sentinels = [];
        for ($descriptor = 4; $descriptor <= 8; ++$descriptor) {
            $sentinels[] = fopen($root . '/internal-' . $descriptor, 'r');
        }
        $environment = [
            'PATH' => (string) (getenv('PATH') ?: '/usr/bin'),
            'TMPDIR' => $root,
            'VGNOVA_MUTATION_SESSION_ID' => str_repeat('a', 32),
            'VGNOVA_MUTATION_TARGET' => 'vgnova-local-10039',
            'VGNOVA_MUTATION_DB_LOCK_A' => 'vgnova.mutation.a.' . str_repeat('b', 40),
            'VGNOVA_MUTATION_DB_CONNECTION_ID_A' => '41',
            'VGNOVA_MUTATION_DB_LOCK_B' => 'vgnova.mutation.b.' . str_repeat('c', 40),
            'VGNOVA_MUTATION_DB_CONNECTION_ID_B' => '42',
            'VGNOVA_MUTATION_LOCK_FILE' => $lock_path,
            'VGNOVA_DB_SOCKET' => '/tmp/local-mysql.sock',
            'MYSQL_PWD' => 'must-not-leak',
            'DB_PASSWORD' => 'must-not-leak',
        ];
        $command = [PHP_BINARY, $worker, $parent_path, $root];
        $descriptors = [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'], 3 => $lock];
        foreach ($sentinels as $index => $sentinel) {
            $descriptors[4 + $index] = $sentinel;
        }
        $process = proc_open($command, $descriptors, $pipes, '/', $environment);
        $assert(is_resource($process), 'fixture parent subprocess did not start');
        foreach ($sentinels as $sentinel) {
            fclose($sentinel);
        }
        fclose($lock);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        $assert($exit === 0 && $stderr === '', 'fixture diagnostic parent failed: ' . trim((string) $stderr));
        $result = json_decode(trim((string) $stdout), true, 32, JSON_THROW_ON_ERROR);
        $observed = json_decode((string) file_get_contents($record_path), true, 32, JSON_THROW_ON_ERROR);
        $assert(($result['probe_started'] ?? false) === true && ($result['child_exit_code'] ?? null) === 0, 'diagnostic child did not execute successfully');
        $assert(($result['diagnostic']['session_valid'] ?? true) === false, 'invalid session result was not returned as probe data');
        $assert(($result['test_child_reaped'] ?? false) === true && ($result['test_child_done_marker'] ?? false) === true, 'parent continued before the child was reaped');
        $assert(($observed['same_fd3_inode'] ?? false) === true && ($observed['fd3_lock_held'] ?? false) === true && ($observed['leaked_descriptors'] ?? null) === [], 'FD3/session descriptor boundary was not preserved');
        $assert(($observed['session_id'] ?? null) === str_repeat('a', 32)
            && ($observed['target'] ?? null) === 'vgnova-local-10039'
            && ($observed['lock_a'] ?? null) === 'vgnova.mutation.a.' . str_repeat('b', 40)
            && ($observed['connection_a'] ?? null) === '41'
            && ($observed['lock_b'] ?? null) === 'vgnova.mutation.b.' . str_repeat('c', 40)
            && ($observed['connection_b'] ?? null) === '42'
            && ($observed['lock_file'] ?? null) === $lock_path,
            'full PE-007 proof did not reach the child');
        $assert(($observed['socket'] ?? null) === '/tmp/local-mysql.sock', 'socket override did not reach the diagnostic child');
        $assert(($observed['config_path'] ?? null) === '/dev/null' && ($observed['system_path'] ?? null) === '/dev/null', 'WP-CLI config isolation did not reach child');
        $assert(($observed['command_present'] ?? false) === true && ($observed['require_path'] ?? null) === $command_path, 'diagnostic WP-CLI command was not selected');
        $assert(in_array($stage . '/includes/Runtime/RuntimeMutationSession.php', $observed['arguments'] ?? [], true), 'staged session-guard path did not reach child');
        $assert(in_array('vgnova-local-10039', $observed['arguments'] ?? [], true) && in_array('--path=' . $root . '/app/public', $observed['arguments'] ?? [], true), 'fixed target or WordPress root did not reach child');
        $assert(($observed['mysql_pwd'] ?? null) === null && ($observed['db_password'] ?? null) === null && ($observed['arguments_have_secret'] ?? true) === false, 'credentials reached child environment or argv');
        $serialized = json_encode($result, JSON_THROW_ON_ERROR);
        $assert(! str_contains($serialized, 'must-not-leak') && ! str_contains($serialized, str_repeat('a', 32)), 'parent output exposed credentials or session ID');
        $assert(is_array($result['raw_capture'] ?? null) && is_file($result['raw_capture']['stdout'] ?? '') && is_file($result['raw_capture']['stderr'] ?? ''), 'raw subprocess output was not captured locally');
        $assert((fileperms($result['raw_capture']['stdout']) & 0077) === 0 && (fileperms($result['raw_capture']['stderr']) & 0077) === 0, 'raw subprocess capture files are readable by other users');
        $captured_json = json_decode(trim((string) file_get_contents($result['raw_capture']['stdout'])), true, 16, JSON_THROW_ON_ERROR);
        $assert(($captured_json['probe_executed'] ?? false) === true && file_get_contents($result['raw_capture']['stderr']) === '', 'raw output capture does not preserve the exact child streams');
    } finally {
        pe008_diagnostic_test_remove($root);
    }
});

$test('diagnostic command emits one allow-listed JSON payload without asserting session validity', static function () use ($assert, $command_path): void {
    $source = file_get_contents($command_path);
    $assert(is_string($source), 'diagnostic command source is unreadable');
    $assert(str_contains($source, 'JSON_THROW_ON_ERROR') && str_contains($source, 'probe_executed'), 'diagnostic command does not emit a strict machine-readable result');
    $assert(substr_count($source, 'WP_CLI::line(') === 1, 'diagnostic command may emit more than one result line');
    $assert(! str_contains($source, 'vg_runtime_mutation_assert_session'), 'diagnostic command still treats an invalid report as command failure');
});

fwrite(STDOUT, sprintf("PE-008 session diagnostic: %d/%d passed.\n", $tests, $tests + $failures));
exit($failures === 0 ? 0 : 1);
