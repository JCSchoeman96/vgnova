<?php
declare(strict_types=1);

$repository_root = dirname(__DIR__, 3);
$library_path = $repository_root . '/site-code/runtime-tools/pe008-db-transport-diagnostic.php';
$parent_path = $repository_root . '/site-code/bin/pe008-diagnose-db-transport.php';
$mysqli_probe_path = $repository_root . '/site-code/runtime-tools/pe008-db-transport-probe.php';
$wp_probe_path = $repository_root . '/site-code/runtime-tools/pe008-wp-cli-transport-diagnostic.php';

require_once $repository_root . '/site-code/runtime-tools/runtime-mutation-core.php';
if (is_file($library_path)) {
    require_once $library_path;
}
if (is_file($parent_path)) {
    require_once $parent_path;
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

$test('diagnostic files are present and isolated from deployment and live QA', static function () use ($assert, $library_path, $parent_path, $mysqli_probe_path, $wp_probe_path): void {
    foreach ([$library_path, $parent_path, $mysqli_probe_path, $wp_probe_path] as $path) {
        $assert(is_file($path), 'missing diagnostic source ' . basename($path));
        $source = file_get_contents($path);
        $assert(is_string($source), 'could not read diagnostic source ' . basename($path));
        foreach (['product-contribution-qa.php', 'vg_product_contribution_qa_run', 'vg_pe008_bootstrap_run', 'rename(', 'copy(', 'unlink(', 'mkdir(', 'rmdir('] as $forbidden) {
            $assert(! str_contains($source, $forbidden), basename($path) . ' contains prohibited operation/reference ' . $forbidden);
        }
    }
    foreach ([$parent_path, $library_path, $mysqli_probe_path] as $path) {
        $source = (string) file_get_contents($path);
        $assert(preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP|TRUNCATE|GET_LOCK|RELEASE_LOCK)\b/i', $source) !== 1, basename($path) . ' contains a database write or lock operation');
    }
});

$test('WP command builder distinguishes direct and forced execution', static function () use ($assert): void {
    $assert(function_exists('vg_pe008_db_transport_wp_command'), 'WP command builder is missing');
    $probe = dirname(__DIR__, 2) . '/runtime-tools/pe008-wp-cli-transport-diagnostic.php';
    $direct = vg_pe008_db_transport_wp_command('direct', '/usr/local/bin/wp', '/tmp/wp', $probe, null, '/usr/bin/php8.5', '/run/mysql.sock', '/run/mysql.sock');
    $forced = vg_pe008_db_transport_wp_command('forced', '/usr/local/bin/wp', '/tmp/wp', $probe, '/run/mysql.sock', '/usr/bin/php8.5', '/other/default.sock', '/other/default.sock');
    $assert(($direct[0] ?? null) === '/usr/local/bin/wp', 'direct mode does not execute the discovered WP-CLI executable');
    $assert(($forced[0] ?? null) === '/usr/bin/php8.5' && in_array('/usr/local/bin/wp', $forced, true), 'forced mode does not execute PHP_BINARY followed by WP-CLI');
    $assert(in_array('-d', $forced, true) && in_array('mysqli.default_socket=/run/mysql.sock', $forced, true), 'forced mode did not use the socket override');
    $assert(! in_array('-d', $direct, true), 'direct mode unexpectedly forced parent PHP settings');
    $forcedDefault = vg_pe008_db_transport_wp_command('forced_default', '/usr/local/bin/wp', '/tmp/wp', $probe, null, '/usr/bin/php8.5', '/run/mysql.sock', '/run/pdo.sock');
    $assert(($forcedDefault[0] ?? null) === '/usr/bin/php8.5' && ($forcedDefault[1] ?? null) === '/usr/local/bin/wp', 'forced default-ini mode changed the PHP binary selection');
    $assert(! in_array('-d', $forcedDefault, true), 'forced default-ini mode changed socket settings along with PHP selection');
});

$test('DB_HOST and socket inputs retain their separate structural meanings', static function () use ($assert): void {
    $assert(function_exists('vg_pe008_db_transport_host_facts'), 'DB_HOST classifier is missing');
    $host = vg_pe008_db_transport_host_facts('localhost', null);
    $port = vg_pe008_db_transport_host_facts('localhost:10039', null);
    $socket = vg_pe008_db_transport_host_facts('localhost:/tmp/mysql.sock', null);
    $override = vg_pe008_db_transport_host_facts('localhost', '/run/local.sock');
    $assert($host['db_host_kind'] === 'host_only' && $host['host'] === 'localhost', 'host-only DB_HOST was misclassified');
    $assert($port['db_host_kind'] === 'host_plus_port' && $port['port_present'] === true, 'DB_HOST port was misclassified');
    $assert($socket['db_host_kind'] === 'host_plus_socket' && $socket['socket_present_in_db_host'] === true, 'DB_HOST socket was misclassified');
    $assert($override['override_present'] === true && $override['selected_socket']['basename'] === 'local.sock', 'socket override was not represented safely');
    $serialized = json_encode([$host, $port, $socket, $override], JSON_THROW_ON_ERROR);
    $assert(! str_contains($serialized, '/tmp/mysql.sock') && ! str_contains($serialized, '/run/local.sock'), 'full socket path escaped safe facts');
});

$test('safe path facts expose shape and hash without the absolute path', static function () use ($assert): void {
    $assert(function_exists('vg_pe008_db_transport_path_facts'), 'socket path fact helper is missing');
    $facts = vg_pe008_db_transport_path_facts('/run/private/mysql.sock');
    $assert($facts['absolute'] === true && $facts['basename'] === 'mysql.sock', 'socket path facts are incomplete');
    $assert(is_string($facts['path_sha256']) && strlen($facts['path_sha256']) === 64, 'socket path hash is missing');
    $assert(! str_contains(json_encode($facts, JSON_THROW_ON_ERROR), '/run/private/mysql.sock'), 'full socket path was exposed');
});

$test('sanitized report excludes database credentials and raw environment', static function () use ($assert): void {
    $assert(function_exists('vg_pe008_db_transport_sanitize_report'), 'diagnostic report sanitizer is missing');
    $report = vg_pe008_db_transport_sanitize_report([
        'db_user' => 'secret-user-value',
        'db_password' => 'secret-password-value',
        'environment' => ['MYSQL_PWD' => 'secret-env-value', 'PHPRC' => '/private/php.ini'],
        'environment_presence' => ['PHPRC' => '/private/php.ini', 'WP_CLI_PHP_ARGS' => '-d secret=hidden'],
        'argv' => ['--password=secret-argument-value'],
        'matrix' => ['DIRECT_WP_EXEC' => ['result' => 'PASS']],
        'socket_override' => ['present' => true, 'facts' => ['absolute' => true, 'exists' => true, 'is_socket' => true, 'basename' => 'mysql.sock', 'path_sha256' => hash('sha256', '/secret/mysql.sock'), 'path' => '/secret/mysql.sock']],
        'php' => ['coordinator' => ['php_ini' => ['basename' => 'private-secret.ini', 'file_sha256' => str_repeat('a', 64), 'path' => '/private/secret.ini']]],
        'db_host' => ['selected_socket' => ['absolute' => true, 'exists' => true, 'is_socket' => true, 'basename' => 'db.sock', 'path_sha256' => hash('sha256', '/private/db.sock'), 'path' => '/private/db.sock']],
        'wp_cli' => ['executable' => ['resolved' => '/usr/local/bin/wp', 'file_type' => 'application/x-executable', 'shebang' => 'env_php']],
    ]);
    $json = json_encode($report, JSON_THROW_ON_ERROR);
    foreach (['secret-user-value', 'secret-password-value', 'secret-env-value', 'private/php.ini', 'secret-argument-value', 'MYSQL_PWD', 'hidden', '/secret/mysql.sock', '/private/secret.ini', '/private/db.sock'] as $secret) {
        $assert(! str_contains($json, $secret), 'sanitized report exposed ' . $secret);
    }
    $assert(($report['matrix']['DIRECT_WP_EXEC']['result'] ?? null) === 'PASS', 'safe matrix result was dropped');
});

$test('environment observation returns presence booleans only', static function () use ($assert): void {
    $assert(function_exists('vg_pe008_db_transport_environment_presence'), 'environment observer is missing');
    $presence = vg_pe008_db_transport_environment_presence([
        'PHPRC' => '/secret/path/php.ini',
        'PHP_INI_SCAN_DIR' => '/private/conf.d',
        'WP_CLI_PHP_ARGS' => '-d mysqlnd.net_read_timeout=9 --define=pass=hidden',
    ]);
    $assert($presence['PHPRC'] === true && $presence['PHP_INI_SCAN_DIR'] === true && $presence['WP_CLI_PHP_ARGS'] === true, 'present config variables were missed');
    $assert($presence['WP_CLI_PHP'] === false && $presence['WP_CLI_CONFIG_PATH'] === false && $presence['WP_CLI_PACKAGES_DIR'] === false, 'absent config variables were misreported');
    $assert(! str_contains(json_encode($presence, JSON_THROW_ON_ERROR), 'secret') && ! str_contains(json_encode($presence, JSON_THROW_ON_ERROR), 'hidden'), 'environment contents were exposed');
});

$test('WP-CLI info parser returns sanitized PHP and WP-CLI context', static function () use ($assert): void {
    $facts = vg_pe008_db_transport_wp_info_facts([
        'started' => true,
        'exit_code' => 0,
        'stdout' => "WP-CLI version: 2.11.0\nPHP binary: /usr/bin/php8.5\nPHP version: 8.5.4\nphp.ini used: /etc/php/8.5/cli/php.ini\n",
    ]);
    $assert($facts['wp_cli_version'] === '2.11.0' && $facts['php_version'] === '8.5.4', 'WP-CLI or PHP version was not parsed');
    $assert(($facts['php_binary']['basename'] ?? null) === 'php8.5' && ($facts['php_ini']['basename'] ?? null) === 'php.ini', 'PHP executable or ini facts are missing');
    $assert(! str_contains(json_encode($facts, JSON_THROW_ON_ERROR), '/usr/bin/php8.5'), 'full PHP binary path was emitted');
});

$test('primary classification requires matching transport evidence', static function () use ($assert): void {
    $report = [
        'matrix' => [
            'PE007_DIRECT_MYSQLI' => ['result' => 'PASS'],
            'NESTED_DIRECT_MYSQLI' => ['result' => 'PASS'],
            'DIRECT_WP_EXEC' => ['result' => 'FAIL'],
            'FORCED_PARENT_PHP_DEFAULT_INI' => ['result' => 'FAIL'],
            'FORCED_PARENT_PHP_WP_EXEC' => ['result' => 'FAIL'],
        ],
        'php' => [
            'coordinator' => ['php_version' => '8.5.4', 'php_binary' => ['path_sha256' => str_repeat('a', 64)], 'php_ini' => ['file_sha256' => str_repeat('b', 64)]],
            'nested_forced' => ['socket_override_present' => true, 'php_ini' => ['file_sha256' => str_repeat('b', 64)]],
            'wp_cli_native' => ['php_version' => '8.5.4', 'php_binary' => ['path_sha256' => str_repeat('a', 64)], 'php_ini' => ['file_sha256' => str_repeat('b', 64)]],
        ],
        'db_host' => ['db_host_kind' => 'host_only'],
        'socket_override' => ['present' => true],
    ];
    $assert(vg_pe008_db_transport_primary_classification($report) === 'F_DIRECT_MYSQLI_WORKS_BUT_WPDB_BOOTSTRAP_FAILS', 'mysqli/WPDB divergence was misclassified');
    $report['matrix']['FORCED_PARENT_PHP_DEFAULT_INI']['result'] = 'PASS';
    $report['matrix']['FORCED_PARENT_PHP_WP_EXEC']['result'] = 'FAIL';
    $assert(vg_pe008_db_transport_primary_classification($report) === 'B_DB_HOST_SOCKET_OR_PORT_INTERPRETATION_DIVERGENCE', 'isolated socket-setting failure was not classified');
    $report['php']['nested_forced']['socket_override_present'] = false;
    $assert(vg_pe008_db_transport_primary_classification($report) === 'A_SOCKET_OVERRIDE_NOT_PROPAGATED', 'missing nested socket override was not classified');
});

$test('mysqli probe command keeps credentials out of argv and preserves socket choices', static function () use ($assert): void {
    $assert(function_exists('vg_pe008_db_transport_mysqli_command'), 'nested mysqli command builder is missing');
    $probe = dirname(__DIR__, 2) . '/runtime-tools/pe008-db-transport-probe.php';
    $command = vg_pe008_db_transport_mysqli_command('/usr/bin/php8.5', $probe, '/wp/root', '/usr/local/bin/wp', '/run/local.sock', '/etc/php.sock', '/etc/pdo.sock');
    $joined = implode("\n", $command);
    $assert(in_array('mysqli.default_socket=/run/local.sock', $command, true), 'socket override did not reach the nested PHP process');
    $assert(in_array('mysqli.default_socket=/run/local.sock', $command, true), 'nested PHP did not receive the PE-008 mysqli socket setting');
    foreach (['secret-user-value', 'secret-password-value', 'MYSQL_PWD'] as $secret) {
        $assert(! str_contains($joined, $secret), 'database secret entered nested probe argv');
    }
});

$test('diagnostic process and WP-CLI commands allow only FD3 and do not dump environment', static function () use ($assert, $parent_path, $mysqli_probe_path, $wp_probe_path, $library_path): void {
    foreach ([$parent_path, $mysqli_probe_path, $wp_probe_path, $library_path] as $path) {
        $source = file_get_contents($path);
        $assert(is_string($source), 'diagnostic source could not be read');
        $assert(! preg_match('/(?:var_export|print_r)\s*\(\s*(?:getenv|\$_ENV|\$_SERVER)/', $source), basename($path) . ' may dump raw environment');
    }
    $library = file_get_contents($library_path);
    $assert(is_string($library) && str_contains($library, 'descriptorSafeCommand($command, $inheritFd3 ? [3] : [])'), 'subprocess builder does not restrict inherited descriptors to FD3');
});

$test('diagnostic run waits for children and uses isolated WP-CLI bootstrap settings', static function () use ($assert, $parent_path, $wp_probe_path, $library_path): void {
    $parent = file_get_contents($parent_path);
    $wp = file_get_contents($wp_probe_path);
    $library = file_get_contents($library_path);
    $assert(is_string($parent) && is_string($library) && preg_match('/proc_close\s*\(/', $library) === 1, 'parent does not wait for nested child exit');
    $assert(str_contains((string) $library, 'WP_CLI_CONFIG_PATH') && str_contains((string) $library, 'WP_CLI_SYSTEM_SETTINGS_PATH'), 'parent omits WP-CLI configuration isolation');
    $assert(str_contains($wp, 'DISABLE_WP_CRON') && str_contains($wp, 'WPMU_PLUGIN_DIR'), 'WP bootstrap diagnostic lacks cron/MU-plugin suppression');
});

$test('actual nested subprocess inherits only FD3 and the full sanitized PE-007 proof', static function () use ($assert, $library_path): void {
    $root = sys_get_temp_dir() . '/pe008-db-transport-' . bin2hex(random_bytes(5));
    if (! mkdir($root, 0700) && ! is_dir($root)) {
        throw new RuntimeException('Could not create subprocess fixture directory');
    }
    $lockPath = $root . '/host.lock';
    file_put_contents($lockPath, 'fixture');
    chmod($lockPath, 0600);
    for ($descriptor = 4; $descriptor <= 8; ++$descriptor) {
        file_put_contents($root . '/internal-' . $descriptor, 'fixture');
    }
    $childPath = $root . '/child.php';
    file_put_contents($childPath, <<<'PHP'
<?php
declare(strict_types=1);
$root = (string) getenv('TMPDIR');
$fd = @fopen('php://fd/3', 'r+');
$fdStat = is_resource($fd) ? @fstat($fd) : false;
$lockStat = @stat((string) getenv('VGNOVA_MUTATION_LOCK_FILE'));
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
$record = [
    'fd3_same_lock' => is_array($fdStat) && is_array($lockStat) && $fdStat['dev'] === $lockStat['dev'] && $fdStat['ino'] === $lockStat['ino'],
    'fd3_locked' => is_resource($fd) && @flock($fd, LOCK_EX | LOCK_NB),
    'leaked_descriptors' => $leaks,
    'session_id' => getenv('VGNOVA_MUTATION_SESSION_ID') ?: null,
    'target' => getenv('VGNOVA_MUTATION_TARGET') ?: null,
    'lock_a' => getenv('VGNOVA_MUTATION_DB_LOCK_A') ?: null,
    'connection_a' => getenv('VGNOVA_MUTATION_DB_CONNECTION_ID_A') ?: null,
    'lock_b' => getenv('VGNOVA_MUTATION_DB_LOCK_B') ?: null,
    'connection_b' => getenv('VGNOVA_MUTATION_DB_CONNECTION_ID_B') ?: null,
    'lock_file' => getenv('VGNOVA_MUTATION_LOCK_FILE') ?: null,
    'socket' => getenv('VGNOVA_DB_SOCKET') ?: null,
    'mysql_password' => getenv('MYSQL_PWD') ?: null,
    'db_password' => getenv('DB_PASSWORD') ?: null,
    'phprc' => getenv('PHPRC') ?: null,
    'wp_cli_args' => getenv('WP_CLI_PHP_ARGS') ?: null,
];
file_put_contents($root . '/child.done', 'done');
file_put_contents($root . '/child.pid', (string) getmypid());
fwrite(STDOUT, json_encode($record, JSON_THROW_ON_ERROR) . "\n");
usleep(100000);
PHP);
    $workerPath = $root . '/worker.php';
    file_put_contents($workerPath, <<<'PHP'
<?php
declare(strict_types=1);
require $argv[1];
$root = $argv[2];
$environment = [
    'PATH' => (string) (getenv('PATH') ?: '/usr/bin'),
    'HOME' => $root,
    'TMPDIR' => $root,
    'VGNOVA_MUTATION_SESSION_ID' => str_repeat('a', 32),
    'VGNOVA_MUTATION_TARGET' => 'vgnova-local-10039',
    'VGNOVA_MUTATION_DB_LOCK_A' => 'vgnova.mutation.a.' . str_repeat('b', 40),
    'VGNOVA_MUTATION_DB_CONNECTION_ID_A' => '41',
    'VGNOVA_MUTATION_DB_LOCK_B' => 'vgnova.mutation.b.' . str_repeat('c', 40),
    'VGNOVA_MUTATION_DB_CONNECTION_ID_B' => '42',
    'VGNOVA_MUTATION_LOCK_FILE' => $root . '/host.lock',
    'VGNOVA_DB_SOCKET' => '/tmp/local-mysql.sock',
    'MYSQL_PWD' => 'secret-password-value',
    'DB_PASSWORD' => 'secret-db-value',
    'PHPRC' => '/private/php.ini',
    'WP_CLI_PHP_ARGS' => '-d secret=hidden',
];
$safe = vg_pe008_db_transport_child_environment($environment);
$result = vg_pe008_db_transport_run_process([PHP_BINARY, $root . '/child.php'], $safe, true);
$pid = (int) file_get_contents($root . '/child.pid');
$terminated = function_exists('posix_kill') && ! @posix_kill($pid, 0);
$done = is_file($root . '/child.done');
fwrite(STDOUT, json_encode(['result' => $result, 'safe_environment' => $safe, 'terminated' => $terminated, 'done' => $done], JSON_THROW_ON_ERROR) . "\n");
PHP);
    try {
        $lock = fopen($lockPath, 'r+');
        $assert(is_resource($lock) && flock($lock, LOCK_EX | LOCK_NB), 'fixture parent could not lock the host descriptor');
        $sentinels = [];
        for ($descriptor = 4; $descriptor <= 8; ++$descriptor) {
            $sentinels[] = fopen($root . '/internal-' . $descriptor, 'r');
        }
        $descriptors = [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'], 3 => $lock];
        foreach ($sentinels as $index => $sentinel) {
            $descriptors[4 + $index] = $sentinel;
        }
        $process = proc_open([PHP_BINARY, $workerPath, $library_path, $root], $descriptors, $pipes, '/', ['PATH' => (string) (getenv('PATH') ?: '/usr/bin')]);
        $assert(is_resource($process), 'fixture worker did not start');
        foreach ($sentinels as $sentinel) {
            fclose($sentinel);
        }
        fclose($lock);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        $assert($exit === 0 && $stderr === '', 'fixture worker failed');
        $result = json_decode(trim((string) $stdout), true, 16, JSON_THROW_ON_ERROR);
        $childOutput = json_decode(trim((string) ($result['result']['stdout'] ?? '')), true, 16, JSON_THROW_ON_ERROR);
        $assert(($result['result']['started'] ?? false) === true && ($result['result']['exit_code'] ?? null) === 0, 'nested diagnostic subprocess did not exit cleanly');
        $assert(($result['terminated'] ?? false) === true && ($result['done'] ?? false) === true, 'parent continued before the nested subprocess terminated');
        $assert(($childOutput['fd3_same_lock'] ?? false) === true && ($childOutput['fd3_locked'] ?? false) === true, 'nested child did not receive the locked FD3');
        $assert(($childOutput['leaked_descriptors'] ?? null) === [], 'FD4 through FD8 leaked into nested child');
        $assert(($childOutput['session_id'] ?? null) === str_repeat('a', 32)
            && ($childOutput['target'] ?? null) === 'vgnova-local-10039'
            && ($childOutput['lock_a'] ?? null) === 'vgnova.mutation.a.' . str_repeat('b', 40)
            && ($childOutput['connection_a'] ?? null) === '41'
            && ($childOutput['lock_b'] ?? null) === 'vgnova.mutation.b.' . str_repeat('c', 40)
            && ($childOutput['connection_b'] ?? null) === '42'
            && ($childOutput['lock_file'] ?? null) === $lockPath, 'full PE-007 session proof did not reach the child');
        $assert(($childOutput['socket'] ?? null) === '/tmp/local-mysql.sock', 'database socket override did not reach the child');
        $assert(($childOutput['mysql_password'] ?? null) === null && ($childOutput['db_password'] ?? null) === null
            && ($childOutput['phprc'] ?? null) === null && ($childOutput['wp_cli_args'] ?? null) === null, 'secret or unapproved PHP configuration entered nested child environment');
        $assert(($result['result']['stderr'] ?? null) === '' && ! str_contains((string) $stdout, 'secret-password-value'), 'secret values reached subprocess output');
    } finally {
        foreach (glob($root . '/*') ?: [] as $path) {
            if (is_dir($path) && ! is_link($path)) {
                foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), FilesystemIterator::CHILD_FIRST) as $entry) {
                    $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
                }
                rmdir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($root);
    }
});

$test('source inventory classifies diagnostic entrypoints as read-only', static function () use ($assert, $repository_root, $parent_path, $mysqli_probe_path, $wp_probe_path, $library_path): void {
    $inventory = json_decode((string) file_get_contents($repository_root . '/site-code/runtime-tools/mutating-entrypoints.json'), true, 32, JSON_THROW_ON_ERROR);
    $entries = [];
    foreach ($inventory['entries'] as $entry) {
        $entries[$entry['path']] = $entry['classification'];
    }
    foreach ([$parent_path, $mysqli_probe_path] as $path) {
        $relative = substr($path, strlen($repository_root) + 1);
        $assert(($entries[$relative] ?? null) === 'read_only', 'diagnostic entrypoint is not inventoried as read_only: ' . $relative);
    }
    foreach ([$library_path, $wp_probe_path] as $path) {
        $relative = substr($path, strlen($repository_root) + 1);
        $assert(($entries[$relative] ?? null) === 'library', 'diagnostic support file is not inventoried as library: ' . $relative);
    }
});

fwrite(STDOUT, sprintf("%d/%d PE-008 DB transport diagnostic tests passed.\n", $tests, $tests + $failures));
exit($failures === 0 ? 0 : 1);
