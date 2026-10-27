<?php
declare(strict_types=1);

require_once __DIR__ . '/runtime-mutation-core.php';

/** @return array{absolute:bool,exists:bool,is_socket:bool,basename:string,path_sha256:string} */
function vg_pe008_db_transport_path_facts(?string $path): array
{
    if (! is_string($path) || $path === '') {
        return ['absolute' => false, 'exists' => false, 'is_socket' => false, 'basename' => '', 'path_sha256' => ''];
    }
    $stat = @lstat($path);
    return [
        'absolute' => VgRuntimeMutationCore::isAbsolutePathForDatabase($path),
        'exists' => is_array($stat),
        'is_socket' => is_array($stat) && (($stat['mode'] & 0170000) === 0140000),
        'basename' => basename($path),
        'path_sha256' => hash('sha256', $path),
    ];
}

/** @return array<string,mixed> */
function vg_pe008_db_transport_host_facts(string $dbHost, ?string $socketOverride): array
{
    $transport = VgRuntimeMutationCore::parseDbHost($dbHost, $socketOverride);
    $kind = 'host_only';
    $socketInDbHost = false;
    $portPresent = false;
    $separator = strpos(trim($dbHost), ':');
    if ($separator !== false) {
        $suffix = substr(trim($dbHost), $separator + 1);
        if (str_starts_with($suffix, '/')) {
            $kind = 'host_plus_socket';
            $socketInDbHost = true;
        } else {
            $kind = 'host_plus_port';
            $portPresent = true;
        }
    }
    $selectedSocket = $transport['socket'];
    return [
        'db_host_kind' => $kind,
        'host' => $transport['host'],
        'port_present' => $portPresent,
        'socket_present_in_db_host' => $socketInDbHost,
        'override_present' => is_string($socketOverride) && $socketOverride !== '',
        'selected_socket' => vg_pe008_db_transport_path_facts($selectedSocket),
        'selected_transport' => $selectedSocket !== null ? 'socket' : ($transport['port'] !== null ? 'port' : 'default'),
    ];
}

/** @return array<string,mixed> */
function vg_pe008_db_transport_php_context(?array $environment = null): array
{
    $environment ??= getenv() ?: [];
    $binary = PHP_BINARY;
    $ini = php_ini_loaded_file();
    $mysqliSocket = (string) ini_get('mysqli.default_socket');
    $pdoSocket = (string) ini_get('pdo_mysql.default_socket');
    $override = isset($environment['VGNOVA_DB_SOCKET']) && $environment['VGNOVA_DB_SOCKET'] !== ''
        ? $environment['VGNOVA_DB_SOCKET']
        : null;
    return [
        'php_version' => PHP_VERSION,
        'php_binary' => [
            'basename' => basename($binary),
            'path_sha256' => hash('sha256', $binary),
        ],
        'php_ini' => [
            'basename' => is_string($ini) && $ini !== '' ? basename($ini) : '',
            'file_sha256' => is_string($ini) && is_file($ini) ? (string) hash_file('sha256', $ini) : '',
        ],
        'mysqli_available' => extension_loaded('mysqli'),
        'mysqli_default_socket' => vg_pe008_db_transport_path_facts($mysqliSocket),
        'pdo_mysql_default_socket' => vg_pe008_db_transport_path_facts($pdoSocket),
        'socket_override_present' => $override !== null,
        'mysqli_socket_matches_override' => $override !== null
            && $mysqliSocket !== ''
            && realpath($mysqliSocket) !== false
            && realpath($mysqliSocket) === realpath($override),
    ];
}

/** @param array<string,string> $environment @return array<string,bool> */
function vg_pe008_db_transport_environment_presence(array $environment): array
{
    $result = [];
    foreach (['PHPRC', 'PHP_INI_SCAN_DIR', 'WP_CLI_PHP', 'WP_CLI_PHP_ARGS', 'WP_CLI_CONFIG_PATH', 'WP_CLI_PACKAGES_DIR'] as $name) {
        $result[$name] = isset($environment[$name]) && $environment[$name] !== '';
    }
    return $result;
}

/** @param list<string> $wpArguments @return list<string> */
function vg_pe008_db_transport_wp_command(
    string $mode,
    string $wpCli,
    string $wordpressRoot,
    string $wpProbe,
    ?string $socketOverride,
    string $phpBinary,
    string $mysqliDefaultSocket,
    string $pdoDefaultSocket,
    array $wpArguments = []
): array {
    if (! in_array($mode, ['direct', 'forced', 'forced_default'], true)
        || ! str_starts_with($wpCli, '/')
        || ! str_starts_with($wordpressRoot, '/')
        || ! str_starts_with($wpProbe, '/')
        || ! is_file($wpProbe)
        || ! str_starts_with($phpBinary, '/')
        || ! array_is_list($wpArguments)) {
        throw new InvalidArgumentException('WP-CLI transport command inputs are invalid.');
    }
    $command = [];
    if ($mode === 'forced') {
        $command = [
            $phpBinary,
            '-d', 'mysqli.default_socket=' . ($socketOverride ?? $mysqliDefaultSocket),
            '-d', 'pdo_mysql.default_socket=' . ($socketOverride ?? $pdoDefaultSocket),
        ];
    } elseif ($mode === 'forced_default') {
        $command = [$phpBinary];
    }
    return [...$command, $wpCli, '--path=' . rtrim($wordpressRoot, '/'), '--skip-packages', '--skip-plugins', '--skip-themes', ...$wpArguments];
}

/** @return list<string> */
function vg_pe008_db_transport_mysqli_command(
    string $phpBinary,
    string $probe,
    string $wordpressRoot,
    string $wpCli,
    ?string $socketOverride,
    string $mysqliDefaultSocket,
    string $pdoDefaultSocket
): array {
    if (! str_starts_with($phpBinary, '/') || ! str_starts_with($probe, '/') || ! is_file($probe)
        || ! str_starts_with($wordpressRoot, '/') || ! str_starts_with($wpCli, '/')) {
        throw new InvalidArgumentException('Nested mysqli probe command inputs are invalid.');
    }
    return [
        $phpBinary,
        '-d', 'mysqli.default_socket=' . ($socketOverride ?? $mysqliDefaultSocket),
        '-d', 'pdo_mysql.default_socket=' . ($socketOverride ?? $pdoDefaultSocket),
        $probe,
        '--wp-root=' . $wordpressRoot,
        '--wp-cli=' . $wpCli,
    ];
}

/** @param array<string,string> $environment @return array<string,string> */
function vg_pe008_db_transport_child_environment(array $environment, ?string $wpProbe = null, bool $isolateWpCliConfig = true): array
{
    $locks = [];
    foreach (['a' => 'A', 'b' => 'B'] as $slot => $suffix) {
        $locks[$slot] = [
            'name' => (string) ($environment['VGNOVA_MUTATION_DB_LOCK_' . $suffix] ?? ''),
            'connection_id' => (string) ($environment['VGNOVA_MUTATION_DB_CONNECTION_ID_' . $suffix] ?? ''),
        ];
    }
    $proof = [
        'session_id' => (string) ($environment['VGNOVA_MUTATION_SESSION_ID'] ?? ''),
        'target' => (string) ($environment['VGNOVA_MUTATION_TARGET'] ?? ''),
        'db_locks' => $locks,
        'lock_file' => (string) ($environment['VGNOVA_MUTATION_LOCK_FILE'] ?? ''),
    ];
    $socket = isset($environment['VGNOVA_DB_SOCKET']) && $environment['VGNOVA_DB_SOCKET'] !== ''
        ? $environment['VGNOVA_DB_SOCKET']
        : null;
    $child = VgRuntimeMutationCore::childEnvironment($environment, $proof, $socket);
    if ($isolateWpCliConfig) {
        $child['WP_CLI_CONFIG_PATH'] = '/dev/null';
        $child['WP_CLI_SYSTEM_SETTINGS_PATH'] = '/dev/null';
    }
    unset($child['WP_CLI_REQUIRE']);
    if ($wpProbe !== null) {
        if (! str_starts_with($wpProbe, '/') || ! is_file($wpProbe) || is_link($wpProbe)) {
            throw new InvalidArgumentException('WP-CLI diagnostic source is unavailable.');
        }
        $child['WP_CLI_REQUIRE'] = $wpProbe;
    }
    return $child;
}

/** @param list<string> $command @param array<string,string> $environment @return array{started:bool,exit_code:?int,stdout:string,stderr:string,error_code:?string} */
function vg_pe008_db_transport_run_process(array $command, array $environment, bool $inheritFd3 = true): array
{
    try {
        $safeCommand = VgRuntimeMutationCore::descriptorSafeCommand($command, $inheritFd3 ? [3] : []);
    } catch (Throwable) {
        return ['started' => false, 'exit_code' => null, 'stdout' => '', 'stderr' => '', 'error_code' => 'process_configuration_invalid'];
    }
    $descriptors = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    if ($inheritFd3) {
        $descriptors[3] = ['file', 'php://fd/3', 'r+'];
    }
    $process = @proc_open($safeCommand, $descriptors, $pipes, '/', $environment);
    if (! is_resource($process)) {
        return ['started' => false, 'exit_code' => null, 'stdout' => '', 'stderr' => '', 'error_code' => 'process_start_failed'];
    }
    stream_set_timeout($pipes[1], 30);
    stream_set_timeout($pipes[2], 30);
    $stdout = stream_get_contents($pipes[1], 65536);
    $stdoutTimedOut = (bool) (stream_get_meta_data($pipes[1])['timed_out'] ?? false);
    $stderr = stream_get_contents($pipes[2], 65536);
    $stderrTimedOut = (bool) (stream_get_meta_data($pipes[2])['timed_out'] ?? false);
    if ($stdoutTimedOut || $stderrTimedOut || ! is_string($stdout) || ! is_string($stderr)
        || strlen($stdout) >= 65536 || strlen($stderr) >= 65536) {
        @proc_terminate($process, SIGKILL);
    }
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($stdoutTimedOut || $stderrTimedOut || strlen((string) $stdout) >= 65536 || strlen((string) $stderr) >= 65536) {
        return ['started' => true, 'exit_code' => is_int($exitCode) ? $exitCode : 70, 'stdout' => '', 'stderr' => '', 'error_code' => 'process_output_limit'];
    }
    return [
        'started' => true,
        'exit_code' => is_int($exitCode) ? $exitCode : 70,
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
        'error_code' => null,
    ];
}

/** @param array<string,string> $environment */
function vg_pe008_db_transport_read_config(string $wpCli, string $wordpressRoot, string $name, string $type, array $environment): string
{
    if (! in_array($name, ['DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST'], true)
        || ! in_array($type, ['constant', 'variable'], true)) {
        throw new InvalidArgumentException('WordPress config key is not allow-listed.');
    }
    $command = [$wpCli, '--path=' . $wordpressRoot, '--quiet', 'config', 'get', $name, '--type=' . $type, '--format=json'];
    $result = vg_pe008_db_transport_run_process($command, $environment, true);
    if (! $result['started'] || $result['exit_code'] !== 0) {
        throw new RuntimeException('WordPress configuration read failed.');
    }
    try {
        $value = json_decode(trim($result['stdout']), true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new RuntimeException('WordPress configuration value was invalid.');
    }
    if (! is_string($value) && ! is_int($value)) {
        throw new RuntimeException('WordPress configuration value was invalid.');
    }
    return (string) $value;
}

/** @return array<string,mixed> */
function vg_pe008_db_transport_wp_info_facts(array $result): array
{
    $facts = [
        'started' => ($result['started'] ?? false) === true,
        'exit_code' => is_int($result['exit_code'] ?? null) ? $result['exit_code'] : null,
        'stderr_empty' => is_string($result['stderr'] ?? null) && $result['stderr'] === '',
        'wp_cli_version' => '',
        'php_version' => '',
        'php_binary' => null,
        'php_ini' => null,
    ];
    if (! $facts['started'] || $facts['exit_code'] !== 0 || ! is_string($result['stdout'] ?? null)) {
        return $facts;
    }
    foreach (preg_split('/\R/', $result['stdout']) ?: [] as $line) {
        if (preg_match('/\AWP-CLI version:\s*([0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9.-]+)?)/', $line, $matches) === 1) {
            $facts['wp_cli_version'] = $matches[1];
        } elseif (preg_match('/\APHP version:\s*([0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9.-]+)?)/', $line, $matches) === 1) {
            $facts['php_version'] = $matches[1];
        } elseif (preg_match('/\APHP binary:\s*(.+)\z/', $line, $matches) === 1) {
            $path = trim($matches[1]);
            $facts['php_binary'] = ['basename' => basename($path), 'path_sha256' => hash('sha256', $path)];
        } elseif (preg_match('/\Aphp.ini used:\s*(.+)\z/', $line, $matches) === 1) {
            $path = trim($matches[1]);
            $facts['php_ini'] = [
                'basename' => basename($path),
                'file_sha256' => is_file($path) ? (string) hash_file('sha256', $path) : '',
            ];
        }
    }
    return $facts;
}

/** @return array{resolved:string,file_type:string,shebang:string} */
function vg_pe008_db_transport_wp_executable_facts(string $wpCli): array
{
    $resolved = realpath($wpCli);
    if (! is_string($resolved) || ! is_file($resolved) || ! is_executable($resolved)) {
        throw new InvalidArgumentException('Discovered WP-CLI executable is unavailable.');
    }
    $firstLine = '';
    $handle = @fopen($resolved, 'rb');
    if (is_resource($handle)) {
        $line = fgets($handle, 4096);
        fclose($handle);
        $firstLine = is_string($line) ? trim($line) : '';
    }
    $shebang = 'none';
    if (str_starts_with($firstLine, '#!')) {
        $shebang = preg_match('~(?:^|/)env\s+php(?:[0-9.]*)?(?:\s|$)~', $firstLine) === 1
            ? 'env_php'
            : (preg_match('~(?:^|/)php(?:[0-9.]*)?(?:\s|$)~', $firstLine) === 1 ? 'php_path' : 'other');
    }
    $fileType = 'unknown';
    if (class_exists(finfo::class)) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = @$finfo->file($resolved);
        if (is_string($mime) && preg_match('/\A[a-z0-9.+-]+\/[a-z0-9.+-]+\z/i', $mime) === 1) {
            $fileType = $mime;
        }
    }
    return ['resolved' => $resolved, 'file_type' => $fileType, 'shebang' => $shebang];
}

/** @param array<string,mixed> $result */
function vg_pe008_db_transport_bootstrap_result(array $result): array
{
    $stdout = is_string($result['stdout'] ?? null) ? trim($result['stdout']) : '';
    $decoded = null;
    try {
        $candidate = json_decode($stdout, true, 8, JSON_THROW_ON_ERROR);
        $decoded = is_array($candidate) ? $candidate : null;
    } catch (JsonException) {
        $decoded = null;
    }
    $completed = ($decoded['probe'] ?? null) === 'wordpress_bootstrap' && ($decoded['result'] ?? null) === 'PASS';
    $stderrEmpty = is_string($result['stderr'] ?? null) && $result['stderr'] === '';
    $exitCode = is_int($result['exit_code'] ?? null) ? $result['exit_code'] : null;
    $clean = $completed && $exitCode === 0 && $stderrEmpty;
    return [
        'started' => ($result['started'] ?? false) === true,
        'result' => $clean ? 'PASS' : 'FAIL',
        'error_code' => $clean ? null : ($completed && $exitCode === 0 && ! $stderrEmpty
            ? 'wp_cli_stderr_nonempty'
            : (is_string($result['error_code'] ?? null) ? $result['error_code'] : 'wp_bootstrap_failed')),
        'exit_code' => $exitCode,
        'stderr_empty' => $stderrEmpty,
        'bootstrap_completed' => $completed,
    ];
}

/** @param array<string,mixed> $result */
function vg_pe008_db_transport_mysqli_result(array $result): array
{
    $decoded = null;
    try {
        $candidate = json_decode(trim((string) ($result['stdout'] ?? '')), true, 8, JSON_THROW_ON_ERROR);
        $decoded = is_array($candidate) ? $candidate : null;
    } catch (JsonException) {
        $decoded = null;
    }
    if (is_array($decoded)
        && in_array($decoded['result'] ?? null, ['PASS', 'FAIL'], true)
        && (($result['exit_code'] ?? null) === 0)) {
        $phpContext = is_array($decoded['php_context'] ?? null) ? $decoded['php_context'] : [];
        return [
            'started' => ($result['started'] ?? false) === true,
            'result' => $decoded['result'],
            'error_code' => is_string($decoded['error_code'] ?? null) && preg_match('/\A[a-z][a-z0-9_]*\z/', $decoded['error_code']) === 1 ? $decoded['error_code'] : null,
            'exit_code' => is_int($result['exit_code'] ?? null) ? $result['exit_code'] : null,
            'stderr_empty' => is_string($result['stderr'] ?? null) && $result['stderr'] === '',
            'php_context' => $phpContext,
        ];
    }
    return [
        'started' => ($result['started'] ?? false) === true,
        'result' => 'FAIL',
        'error_code' => is_string($result['error_code'] ?? null) ? $result['error_code'] : 'nested_mysqli_probe_failed',
        'exit_code' => is_int($result['exit_code'] ?? null) ? $result['exit_code'] : null,
        'stderr_empty' => is_string($result['stderr'] ?? null) && $result['stderr'] === '',
        'php_context' => [],
    ];
}

/** @return array{absolute:bool,exists:bool,is_socket:bool,basename:string,path_sha256:string}|null */
function vg_pe008_db_transport_sanitize_path_facts(mixed $facts): ?array
{
    if (! is_array($facts)
        || ! is_bool($facts['absolute'] ?? null)
        || ! is_bool($facts['exists'] ?? null)
        || ! is_bool($facts['is_socket'] ?? null)
        || ! is_string($facts['basename'] ?? null)
        || preg_match('/\A[A-Za-z0-9._-]*\z/', $facts['basename']) !== 1
        || ! is_string($facts['path_sha256'] ?? null)
        || ($facts['path_sha256'] !== '' && preg_match('/\A[a-f0-9]{64}\z/', $facts['path_sha256']) !== 1)) {
        return null;
    }
    return [
        'absolute' => $facts['absolute'],
        'exists' => $facts['exists'],
        'is_socket' => $facts['is_socket'],
        'basename' => $facts['basename'],
        'path_sha256' => $facts['path_sha256'],
    ];
}

/** @return array<string,mixed> */
function vg_pe008_db_transport_sanitize_php_context(mixed $context): array
{
    if (! is_array($context)) {
        return [];
    }
    $binary = is_array($context['php_binary'] ?? null) ? $context['php_binary'] : [];
    $ini = is_array($context['php_ini'] ?? null) ? $context['php_ini'] : [];
    return [
        'php_version' => is_string($context['php_version'] ?? null) && preg_match('/\A[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9.-]+)?\z/', $context['php_version']) === 1 ? $context['php_version'] : '',
        'php_binary' => [
            'basename' => is_string($binary['basename'] ?? null) && preg_match('/\A[A-Za-z0-9._-]+\z/', basename($binary['basename'])) === 1 ? basename($binary['basename']) : '',
            'path_sha256' => is_string($binary['path_sha256'] ?? null) && preg_match('/\A[a-f0-9]{64}\z/', $binary['path_sha256']) === 1 ? $binary['path_sha256'] : '',
        ],
        'php_ini' => [
            'basename' => is_string($ini['basename'] ?? null) && preg_match('/\A[A-Za-z0-9._-]+\z/', basename($ini['basename'])) === 1 ? basename($ini['basename']) : '',
            'file_sha256' => is_string($ini['file_sha256'] ?? null) && ($ini['file_sha256'] === '' || preg_match('/\A[a-f0-9]{64}\z/', $ini['file_sha256']) === 1) ? $ini['file_sha256'] : '',
        ],
        'mysqli_available' => is_bool($context['mysqli_available'] ?? null) ? $context['mysqli_available'] : false,
        'mysqli_default_socket' => vg_pe008_db_transport_sanitize_path_facts($context['mysqli_default_socket'] ?? null),
        'pdo_mysql_default_socket' => vg_pe008_db_transport_sanitize_path_facts($context['pdo_mysql_default_socket'] ?? null),
        'socket_override_present' => is_bool($context['socket_override_present'] ?? null) ? $context['socket_override_present'] : false,
        'mysqli_socket_matches_override' => is_bool($context['mysqli_socket_matches_override'] ?? null) ? $context['mysqli_socket_matches_override'] : false,
    ];
}

/** @param array<string,string> $configuration @param array<string,mixed> $transport @return array{result:string,error_code:?string} */
function vg_pe008_db_transport_mysqli_connect(array $configuration, array $transport): array
{
    if (! extension_loaded('mysqli')) {
        return ['result' => 'FAIL', 'error_code' => 'mysqli_unavailable'];
    }
    mysqli_report(MYSQLI_REPORT_OFF);
    $database = mysqli_init();
    if (! $database instanceof mysqli) {
        return ['result' => 'FAIL', 'error_code' => 'mysqli_init_failed'];
    }
    $database->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
    $connected = @$database->real_connect(
        $transport['host'],
        $configuration['DB_USER'],
        $configuration['DB_PASSWORD'],
        $configuration['DB_NAME'],
        $transport['port'],
        $transport['socket']
    );
    if (! $connected) {
        $database->close();
        return ['result' => 'FAIL', 'error_code' => 'mysqli_connect_failed'];
    }
    try {
        $databaseName = $database->query('SELECT DATABASE()');
        $databaseRow = $databaseName instanceof mysqli_result ? $databaseName->fetch_row() : null;
        if ($databaseName instanceof mysqli_result) {
            $databaseName->free();
        }
        $one = $database->query('SELECT 1');
        $oneRow = $one instanceof mysqli_result ? $one->fetch_row() : null;
        if ($one instanceof mysqli_result) {
            $one->free();
        }
        $valid = is_array($databaseRow) && ($databaseRow[0] ?? null) === $configuration['DB_NAME']
            && is_array($oneRow) && (string) ($oneRow[0] ?? '') === '1';
        return ['result' => $valid ? 'PASS' : 'FAIL', 'error_code' => $valid ? null : 'mysqli_read_query_failed'];
    } catch (Throwable) {
        return ['result' => 'FAIL', 'error_code' => 'mysqli_read_query_failed'];
    } finally {
        $database->close();
    }
}

/** @param array<string,mixed> $report @return array<string,mixed> */
function vg_pe008_db_transport_sanitize_report(array $report): array
{
    $matrix = [];
    foreach (['PE007_DIRECT_MYSQLI', 'NESTED_DIRECT_MYSQLI', 'DIRECT_WP_EXEC', 'FORCED_PARENT_PHP_DEFAULT_INI', 'FORCED_PARENT_PHP_WP_EXEC'] as $probe) {
        $item = is_array($report['matrix'][$probe] ?? null) ? $report['matrix'][$probe] : [];
        $matrix[$probe] = [
            'started' => is_bool($item['started'] ?? null) ? $item['started'] : null,
            'result' => in_array($item['result'] ?? null, ['PASS', 'FAIL', 'NOT_RUN'], true) ? $item['result'] : 'NOT_RUN',
            'error_code' => is_string($item['error_code'] ?? null) && preg_match('/\A[a-z][a-z0-9_]*\z/', $item['error_code']) === 1 ? $item['error_code'] : null,
            'exit_code' => is_int($item['exit_code'] ?? null) ? $item['exit_code'] : null,
            'stderr_empty' => is_bool($item['stderr_empty'] ?? null) ? $item['stderr_empty'] : null,
            'bootstrap_completed' => is_bool($item['bootstrap_completed'] ?? null) ? $item['bootstrap_completed'] : null,
        ];
    }
    $facts = is_array($report['db_host'] ?? null) ? $report['db_host'] : [];
    $php = is_array($report['php'] ?? null) ? $report['php'] : [];
    $wp = is_array($report['wp_cli'] ?? null) ? $report['wp_cli'] : [];
    $phpContext = [];
    foreach (['coordinator', 'nested_forced', 'wp_cli_native'] as $contextName) {
        $phpContext[$contextName] = vg_pe008_db_transport_sanitize_php_context($php[$contextName] ?? null);
    }
    $wpExecutable = is_array($wp['executable'] ?? null) ? $wp['executable'] : [];
    $wpInfo = [];
    foreach (['direct', 'forced'] as $mode) {
        $info = is_array($wp['info'][$mode] ?? null) ? $wp['info'][$mode] : [];
        $binary = is_array($info['php_binary'] ?? null) ? $info['php_binary'] : [];
        $ini = is_array($info['php_ini'] ?? null) ? $info['php_ini'] : [];
        $wpInfo[$mode] = [
            'started' => is_bool($info['started'] ?? null) ? $info['started'] : false,
            'exit_code' => is_int($info['exit_code'] ?? null) ? $info['exit_code'] : null,
            'stderr_empty' => is_bool($info['stderr_empty'] ?? null) ? $info['stderr_empty'] : false,
            'wp_cli_version' => is_string($info['wp_cli_version'] ?? null) && preg_match('/\A[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9.-]+)?\z/', $info['wp_cli_version']) === 1 ? $info['wp_cli_version'] : '',
            'php_version' => is_string($info['php_version'] ?? null) && preg_match('/\A[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9.-]+)?\z/', $info['php_version']) === 1 ? $info['php_version'] : '',
            'php_binary' => [
                'basename' => is_string($binary['basename'] ?? null) && preg_match('/\A[A-Za-z0-9._-]+\z/', basename($binary['basename'])) === 1 ? basename($binary['basename']) : '',
                'path_sha256' => is_string($binary['path_sha256'] ?? null) && preg_match('/\A[a-f0-9]{64}\z/', $binary['path_sha256']) === 1 ? $binary['path_sha256'] : '',
            ],
            'php_ini' => [
                'basename' => is_string($ini['basename'] ?? null) && preg_match('/\A[A-Za-z0-9._-]+\z/', basename($ini['basename'])) === 1 ? basename($ini['basename']) : '',
                'file_sha256' => is_string($ini['file_sha256'] ?? null) && ($ini['file_sha256'] === '' || preg_match('/\A[a-f0-9]{64}\z/', $ini['file_sha256']) === 1) ? $ini['file_sha256'] : '',
            ],
        ];
    }
    $environmentPresence = [];
    if (is_array($report['environment_presence'] ?? null)) {
        foreach (['PHPRC', 'PHP_INI_SCAN_DIR', 'WP_CLI_PHP', 'WP_CLI_PHP_ARGS', 'WP_CLI_CONFIG_PATH', 'WP_CLI_PACKAGES_DIR'] as $name) {
            if (is_bool($report['environment_presence'][$name] ?? null)) {
                $environmentPresence[$name] = $report['environment_presence'][$name];
            }
        }
    }
    $executableResolved = is_string($wpExecutable['resolved'] ?? null) && str_starts_with($wpExecutable['resolved'], '/')
        && preg_match('/[\r\n\0]/', $wpExecutable['resolved']) !== 1 ? $wpExecutable['resolved'] : '';
    $fileType = is_string($wpExecutable['file_type'] ?? null) && preg_match('/\A[a-z0-9.+-]+\/[a-z0-9.+-]+\z/i', $wpExecutable['file_type']) === 1 ? $wpExecutable['file_type'] : '';
    $shebang = in_array($wpExecutable['shebang'] ?? null, ['none', 'env_php', 'php_path', 'other'], true) ? $wpExecutable['shebang'] : 'unknown';
    return [
        'probe_started' => ($report['probe_started'] ?? false) === true,
        'process_error_code' => is_string($report['process_error_code'] ?? null) && preg_match('/\A[a-z][a-z0-9_]*\z/', $report['process_error_code']) === 1 ? $report['process_error_code'] : null,
        'primary_classification' => in_array($report['primary_classification'] ?? null, [
            'A_SOCKET_OVERRIDE_NOT_PROPAGATED',
            'B_DB_HOST_SOCKET_OR_PORT_INTERPRETATION_DIVERGENCE',
            'C_WRONG_PHP_BINARY_FOR_LOCAL_WP_CLI',
            'D_PHP_INI_CONTEXT_DIVERGENCE',
            'E_REQUIRED_LOCAL_ENVIRONMENT_REMOVED_BY_SANITIZATION',
            'F_DIRECT_MYSQLI_WORKS_BUT_WPDB_BOOTSTRAP_FAILS',
            'G_OTHER_PROVEN',
            'H_ROOT_CAUSE_STILL_UNRESOLVED',
        ], true) ? $report['primary_classification'] : 'H_ROOT_CAUSE_STILL_UNRESOLVED',
        'matrix' => $matrix,
        'db_host' => [
            'db_host_kind' => in_array($facts['db_host_kind'] ?? null, ['host_only', 'host_plus_port', 'host_plus_socket'], true) ? $facts['db_host_kind'] : 'unknown',
            'host' => is_string($facts['host'] ?? null) && preg_match('/\A[A-Za-z0-9._-]+\z/', $facts['host']) === 1 ? $facts['host'] : '',
            'port_present' => is_bool($facts['port_present'] ?? null) ? $facts['port_present'] : false,
            'socket_present_in_db_host' => is_bool($facts['socket_present_in_db_host'] ?? null) ? $facts['socket_present_in_db_host'] : false,
            'override_present' => is_bool($facts['override_present'] ?? null) ? $facts['override_present'] : false,
            'selected_transport' => in_array($facts['selected_transport'] ?? null, ['socket', 'port', 'default'], true) ? $facts['selected_transport'] : 'unknown',
            'selected_socket' => vg_pe008_db_transport_sanitize_path_facts($facts['selected_socket'] ?? null),
        ],
        'php' => $phpContext,
        'wp_cli' => [
            'executable' => [
                'resolved' => $executableResolved,
                'file_type' => $fileType,
                'shebang' => $shebang,
            ],
            'info' => $wpInfo,
        ],
        'environment_presence' => $environmentPresence,
        'socket_override' => is_array($report['socket_override'] ?? null) ? [
            'present' => is_bool($report['socket_override']['present'] ?? null) ? $report['socket_override']['present'] : false,
            'facts' => vg_pe008_db_transport_sanitize_path_facts($report['socket_override']['facts'] ?? null),
        ] : null,
    ];
}
