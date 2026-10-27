<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/runtime-tools/runtime-mutation-core.php';

const VG_PE008_DIAGNOSTIC_TARGET = 'vgnova-local-10039';
const VG_PE008_DIAGNOSTIC_WORDPRESS_ROOT = '/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public';
const VG_PE008_DIAGNOSTIC_APP_ROOT = '/home/jcschoeman96/Local Sites/voelgoed-novamira/app';
const VG_PE008_DIAGNOSTIC_CAPTURE_ROOT = '/home/jcschoeman96/.cache/vgnova';

/** @return array<string,mixed>|null */
function vg_pe008_diagnostic_sanitize_report(mixed $report): ?array
{
    if (! is_array($report)
        || ! is_bool($report['valid'] ?? null)
        || ! is_bool($report['filesystem_lock_held'] ?? null)
        || ! is_array($report['errors'] ?? null)
        || ! array_is_list($report['errors'])) {
        return null;
    }
    foreach ($report['errors'] as $error) {
        if (! is_string($error) || preg_match('/\A[a-z][a-z0-9_]*\z/', $error) !== 1) {
            return null;
        }
    }
    $owner_matches = [];
    foreach (['a', 'b'] as $slot) {
        $lock = $report['database_locks'][$slot] ?? null;
        if (! is_array($lock)) {
            return null;
        }
        $expected = $lock['expected_connection_id'] ?? null;
        $observed = $lock['observed_connection_id'] ?? null;
        $expected_normalized = is_int($expected) || (is_string($expected) && preg_match('/\A[0-9]+\z/', $expected) === 1)
            ? (string) $expected
            : null;
        $observed_normalized = is_int($observed) || (is_string($observed) && preg_match('/\A[0-9]+\z/', $observed) === 1)
            ? (string) $observed
            : null;
        $owner_matches[$slot] = $expected_normalized !== null
            && $observed_normalized !== null
            && $expected_normalized === $observed_normalized;
    }

    return [
        'probe_executed' => true,
        'session_valid' => $report['valid'],
        'errors' => array_values($report['errors']),
        'filesystem_lock_held' => $report['filesystem_lock_held'],
        'database_lock_a' => ['owner_matches' => $owner_matches['a']],
        'database_lock_b' => ['owner_matches' => $owner_matches['b']],
    ];
}

/** @return array<string,mixed>|null */
function vg_pe008_diagnostic_sanitize_payload(mixed $payload): ?array
{
    if (! is_array($payload)
        || ($payload['probe_executed'] ?? null) !== true
        || ! is_bool($payload['session_valid'] ?? null)
        || ! is_bool($payload['filesystem_lock_held'] ?? null)
        || ! is_array($payload['errors'] ?? null)
        || ! array_is_list($payload['errors'])) {
        return null;
    }
    foreach ($payload['errors'] as $error) {
        if (! is_string($error) || preg_match('/\A[a-z][a-z0-9_]*\z/', $error) !== 1) {
            return null;
        }
    }
    $owners = [];
    foreach (['a', 'b'] as $slot) {
        $owner = $payload['database_lock_' . $slot]['owner_matches'] ?? null;
        if (! is_bool($owner)) {
            return null;
        }
        $owners[$slot] = $owner;
    }

    return [
        'probe_executed' => true,
        'session_valid' => $payload['session_valid'],
        'errors' => array_values($payload['errors']),
        'filesystem_lock_held' => $payload['filesystem_lock_held'],
        'database_lock_a' => ['owner_matches' => $owners['a']],
        'database_lock_b' => ['owner_matches' => $owners['b']],
    ];
}

/** @return array<string,mixed> */
function vg_pe008_diagnostic_build_result(
    bool $probe_started,
    ?int $exit_code,
    string $stdout,
    string $stderr,
    ?string $process_error_code = null
): array {
    $diagnostic = null;
    $parsed = false;
    if ($probe_started) {
        try {
            $decoded = json_decode(trim($stdout), true, 32, JSON_THROW_ON_ERROR);
            $diagnostic = vg_pe008_diagnostic_sanitize_payload($decoded);
            $parsed = $diagnostic !== null;
        } catch (JsonException) {
            $diagnostic = null;
        }
    }
    if ($process_error_code === null && $probe_started) {
        if ($exit_code !== 0) {
            $process_error_code = 'child_nonzero_exit';
        } elseif (! $parsed) {
            $process_error_code = 'diagnostic_output_invalid';
        }
    }

    return [
        'probe_started' => $probe_started,
        'child_exit_code' => $probe_started ? $exit_code : null,
        'child_stderr_empty' => $probe_started && $stderr === '',
        'diagnostic_json_parsed' => $parsed,
        'process_error_code' => $process_error_code,
        'diagnostic' => $diagnostic,
    ];
}

function vg_pe008_diagnostic_exit_code(array $result): int
{
    return ($result['probe_started'] ?? false) === true
        && ($result['child_exit_code'] ?? null) === 0
        && ($result['diagnostic_json_parsed'] ?? false) === true
        && ($result['diagnostic']['probe_executed'] ?? false) === true
        && ($result['raw_capture_saved'] ?? false) === true
        ? 0
        : 1;
}

function vg_pe008_diagnostic_find_wp_cli(?array $environment = null): ?string
{
    $environment ??= getenv() ?: [];
    $path = (string) ($environment['PATH'] ?? '');
    foreach (explode(PATH_SEPARATOR, $path) as $directory) {
        if ($directory === '' || ! str_starts_with($directory, '/')) {
            continue;
        }
        $candidate = rtrim($directory, '/') . '/wp';
        if (is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }
    }
    return null;
}

/** @return list<string> */
function vg_pe008_diagnostic_command(
    string $wp_cli,
    string $wordpress_root,
    string $command_library,
    string $guard_path,
    ?string $socket_override
): array {
    if (! str_starts_with($wp_cli, '/')
        || ! str_starts_with($wordpress_root, '/')
        || ! str_starts_with($command_library, '/')
        || ! is_file($command_library)
        || ! is_file($guard_path)) {
        throw new InvalidArgumentException('Diagnostic WP-CLI inputs are invalid.');
    }
    foreach ([$wp_cli, $wordpress_root, $command_library, $guard_path] as $value) {
        if (str_contains($value, "\0")) {
            throw new InvalidArgumentException('Diagnostic WP-CLI inputs contain an invalid path.');
        }
    }
    if ($socket_override !== null && ! VgRuntimeMutationCore::isAbsolutePathForDatabase($socket_override)) {
        throw new InvalidArgumentException('Database socket override must be an absolute path.');
    }
    $mysqli_socket = $socket_override ?? (string) ini_get('mysqli.default_socket');
    $pdo_socket = $socket_override ?? (string) ini_get('pdo_mysql.default_socket');
    return [
        PHP_BINARY,
        '-d', 'mysqli.default_socket=' . $mysqli_socket,
        '-d', 'pdo_mysql.default_socket=' . $pdo_socket,
        $wp_cli,
        '--path=' . rtrim($wordpress_root, '/'),
        '--skip-packages',
        '--skip-plugins',
        '--skip-themes',
        'pe008',
        'session-diagnostic',
        VG_PE008_DIAGNOSTIC_TARGET,
        $guard_path,
    ];
}

/** @param array<string,string>|null $base_environment @return array<string,string> */
function vg_pe008_diagnostic_subprocess_environment(?array $base_environment, string $command_library): array
{
    if (! str_starts_with($command_library, '/') || ! is_file($command_library) || is_link($command_library)) {
        throw new InvalidArgumentException('Diagnostic command library must be a real source-controlled file.');
    }
    $base_environment ??= getenv() ?: [];
    $db_locks = [];
    foreach (['a' => 'A', 'b' => 'B'] as $slot => $suffix) {
        $db_locks[$slot] = [
            'name' => (string) ($base_environment['VGNOVA_MUTATION_DB_LOCK_' . $suffix] ?? ''),
            'connection_id' => (string) ($base_environment['VGNOVA_MUTATION_DB_CONNECTION_ID_' . $suffix] ?? ''),
        ];
    }
    $proof = [
        'session_id' => (string) ($base_environment['VGNOVA_MUTATION_SESSION_ID'] ?? ''),
        'target' => (string) ($base_environment['VGNOVA_MUTATION_TARGET'] ?? ''),
        'db_locks' => $db_locks,
        'lock_file' => (string) ($base_environment['VGNOVA_MUTATION_LOCK_FILE'] ?? ''),
    ];
    $socket_override = isset($base_environment['VGNOVA_DB_SOCKET']) && $base_environment['VGNOVA_DB_SOCKET'] !== ''
        ? $base_environment['VGNOVA_DB_SOCKET']
        : null;
    $environment = VgRuntimeMutationCore::childEnvironment($base_environment, $proof, $socket_override);
    $environment['WP_CLI_CONFIG_PATH'] = '/dev/null';
    $environment['WP_CLI_SYSTEM_SETTINGS_PATH'] = '/dev/null';
    $environment['WP_CLI_REQUIRE'] = $command_library;
    return $environment;
}

/** @param array<string,string> $environment @return array{exit_code:int,stdout:string,stderr:string,started:bool,error_code:?string} */
function vg_pe008_diagnostic_subprocess(array $command, array $environment, string $command_library): array
{
    try {
        $safe_command = VgRuntimeMutationCore::descriptorSafeCommand($command, [3]);
        $safe_environment = vg_pe008_diagnostic_subprocess_environment($environment, $command_library);
    } catch (Throwable) {
        return ['exit_code' => 70, 'stdout' => '', 'stderr' => '', 'started' => false, 'error_code' => 'child_configuration_invalid'];
    }
    $descriptors = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
        3 => ['file', 'php://fd/3', 'r+'],
    ];
    $process = @proc_open($safe_command, $descriptors, $pipes, '/', $safe_environment);
    if (! is_resource($process)) {
        return ['exit_code' => 70, 'stdout' => '', 'stderr' => '', 'started' => false, 'error_code' => 'child_start_failed'];
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit_code = proc_close($process);
    return [
        'exit_code' => is_int($exit_code) ? $exit_code : 70,
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
        'started' => true,
        'error_code' => null,
    ];
}

function vg_pe008_diagnostic_capture_write(string $path, string $contents): bool
{
    $handle = @fopen($path, 'x');
    if (! is_resource($handle)) {
        return false;
    }
    $written = 0;
    $length = strlen($contents);
    while ($written < $length) {
        $count = @fwrite($handle, substr($contents, $written));
        if (! is_int($count) || $count < 1) {
            fclose($handle);
            return false;
        }
        $written += $count;
    }
    $flushed = @fflush($handle);
    fclose($handle);
    @chmod($path, 0600);
    return $flushed;
}

/** @param array<string,mixed> $options @param array<string,string>|null $base_environment @return array<string,mixed> */
function vg_pe008_diagnostic_run(
    array $options,
    ?array $base_environment = null,
    ?string $wp_cli_override = null,
    ?string $app_root_override = null,
    ?string $capture_root_override = null
): array {
    $base_environment ??= getenv() ?: [];
    $app_root = $app_root_override ?? VG_PE008_DIAGNOSTIC_APP_ROOT;
    $capture_root = $capture_root_override ?? VG_PE008_DIAGNOSTIC_CAPTURE_ROOT;
    $wp_root = $options['wordpress_root'] ?? null;
    $stage = $options['stage'] ?? null;
    $capture_directory = $options['capture_directory'] ?? null;
    if (! is_string($wp_root) || ! is_string($stage) || ! is_string($capture_directory)) {
        return vg_pe008_diagnostic_build_result(false, null, '', '', 'invalid_arguments');
    }
    $resolved_app = realpath($app_root);
    $resolved_root = realpath($wp_root);
    $resolved_stage = realpath($stage);
    $stage_guard = $stage . '/includes/Runtime/RuntimeMutationSession.php';
    $resolved_guard = realpath($stage_guard);
    if (! is_string($resolved_app)
        || ! is_string($resolved_root)
        || $resolved_root !== $resolved_app . '/public'
        || is_link($wp_root)
        || ! is_string($resolved_stage)
        || $resolved_stage !== $stage
        || is_link($stage)
        || dirname($resolved_stage) !== $resolved_app
        || preg_match('/\Avoelgoed-platform-core\.pe008-stage-[A-Za-z0-9T_-]+\z/', basename($resolved_stage)) !== 1
        || ! is_string($resolved_guard)
        || $resolved_guard !== $stage_guard
        || is_link($stage_guard)) {
        return vg_pe008_diagnostic_build_result(false, null, '', '', 'invalid_runtime_paths');
    }
    $resolved_capture_root = realpath($capture_root);
    $resolved_capture = realpath($capture_directory);
    $capture_stat = is_string($resolved_capture) ? @lstat($resolved_capture) : false;
    if (! is_string($resolved_capture_root)
        || ! is_string($resolved_capture)
        || ($resolved_capture !== $resolved_capture_root && ! str_starts_with($resolved_capture, rtrim($resolved_capture_root, '/') . '/'))
        || is_link($capture_directory)
        || ! is_array($capture_stat)
        || ($capture_stat['mode'] & 0170000) !== 0040000
        || ($capture_stat['uid'] ?? null) !== (function_exists('posix_geteuid') ? posix_geteuid() : ($capture_stat['uid'] ?? null))
        || ($capture_stat['mode'] & 0077) !== 0) {
        return vg_pe008_diagnostic_build_result(false, null, '', '', 'invalid_capture_directory');
    }
    $wp_cli = $wp_cli_override ?? vg_pe008_diagnostic_find_wp_cli($base_environment);
    if (! is_string($wp_cli) || ! is_file($wp_cli) || ! is_executable($wp_cli)) {
        return vg_pe008_diagnostic_build_result(false, null, '', '', 'wp_cli_not_found');
    }
    $command_library = dirname(__DIR__) . '/runtime-tools/pe008-wp-cli-session-diagnostic.php';
    if (! is_file($command_library) || is_link($command_library)) {
        return vg_pe008_diagnostic_build_result(false, null, '', '', 'diagnostic_command_unavailable');
    }
    try {
        $socket_override = isset($base_environment['VGNOVA_DB_SOCKET']) && $base_environment['VGNOVA_DB_SOCKET'] !== ''
            ? $base_environment['VGNOVA_DB_SOCKET']
            : null;
        $command = vg_pe008_diagnostic_command($wp_cli, $resolved_root, $command_library, $resolved_guard, $socket_override);
    } catch (Throwable) {
        return vg_pe008_diagnostic_build_result(false, null, '', '', 'child_configuration_invalid');
    }
    $child = vg_pe008_diagnostic_subprocess($command, $base_environment, $command_library);
    $result = vg_pe008_diagnostic_build_result(
        $child['started'],
        $child['exit_code'],
        $child['stdout'],
        $child['stderr'],
        $child['error_code']
    );
    $capture_id = gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(4));
    $stdout_path = $resolved_capture . '/pe008-session-diagnostic-' . $capture_id . '.stdout.txt';
    $stderr_path = $resolved_capture . '/pe008-session-diagnostic-' . $capture_id . '.stderr.txt';
    $stdout_saved = vg_pe008_diagnostic_capture_write($stdout_path, $child['stdout']);
    $stderr_saved = vg_pe008_diagnostic_capture_write($stderr_path, $child['stderr']);
    $result['raw_capture_saved'] = $stdout_saved && $stderr_saved;
    $result['raw_capture'] = $result['raw_capture_saved']
        ? ['stdout' => $stdout_path, 'stderr' => $stderr_path]
        : null;
    if (! $result['raw_capture_saved']) {
        $result['process_error_code'] = 'capture_write_failed';
    }
    return $result;
}

/** @return array<string,string>|null */
function vg_pe008_diagnostic_parse_options(array $arguments): ?array
{
    $options = [];
    foreach ($arguments as $argument) {
        if (! is_string($argument) || ! preg_match('/\A--(wp-root|stage|capture-dir)=(.*)\z/s', $argument, $matches)) {
            return null;
        }
        if (array_key_exists($matches[1], $options)) {
            return null;
        }
        $options[$matches[1]] = $matches[2];
    }
    if (count($options) !== 3 || ! isset($options['wp-root'], $options['stage'], $options['capture-dir'])) {
        return null;
    }
    return [
        'wordpress_root' => $options['wp-root'],
        'stage' => $options['stage'],
        'capture_directory' => $options['capture-dir'],
    ];
}

function vg_pe008_diagnostic_main(): never
{
    $options = vg_pe008_diagnostic_parse_options(array_slice($_SERVER['argv'] ?? [], 1));
    if ($options === null) {
        $result = vg_pe008_diagnostic_build_result(false, null, '', '', 'invalid_arguments');
        fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        exit(1);
    }
    try {
        $result = vg_pe008_diagnostic_run($options);
    } catch (Throwable) {
        $result = vg_pe008_diagnostic_build_result(false, null, '', '', 'diagnostic_parent_exception');
    }
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    exit(vg_pe008_diagnostic_exit_code($result));
}

if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    vg_pe008_diagnostic_main();
}
