<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/runtime-tools/pe008-db-transport-diagnostic.php';

const VG_PE008_DB_DIAGNOSTIC_TARGET = 'vgnova-local-10039';
const VG_PE008_DB_DIAGNOSTIC_WORDPRESS_ROOT = '/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public';

/** @param array<string,string> $environment */
function vg_pe008_db_transport_find_wp_cli(array $environment): ?string
{
    foreach (explode(PATH_SEPARATOR, (string) ($environment['PATH'] ?? '')) as $directory) {
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

/** @param array<string,string> $environment @return array{ok:bool,configuration:array<string,string>,error_code:?string} */
function vg_pe008_db_transport_read_all_config(string $wpCli, string $wordpressRoot, array $environment): array
{
    $configuration = [];
    foreach (['DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST'] as $name) {
        try {
            $configuration[$name] = vg_pe008_db_transport_read_config(
                $wpCli,
                $wordpressRoot,
                $name,
                'constant',
                $environment
            );
        } catch (Throwable) {
            return ['ok' => false, 'configuration' => [], 'error_code' => 'wp_config_read_failed'];
        }
    }
    return ['ok' => true, 'configuration' => $configuration, 'error_code' => null];
}

/** @param array<string,string> $environment @return array<string,mixed> */
function vg_pe008_db_transport_info(string $mode, string $wpCli, ?string $socketOverride, array $environment): array
{
    $mysqliSocket = $socketOverride ?? (string) ini_get('mysqli.default_socket');
    $pdoSocket = $socketOverride ?? (string) ini_get('pdo_mysql.default_socket');
    $command = $mode === 'direct'
        ? [$wpCli, '--info']
        : [PHP_BINARY, '-d', 'mysqli.default_socket=' . $mysqliSocket, '-d', 'pdo_mysql.default_socket=' . $pdoSocket, $wpCli, '--info'];
    $result = vg_pe008_db_transport_run_process($command, $environment, true);
    return vg_pe008_db_transport_wp_info_facts($result);
}

/** @param array<string,string> $environment @return array<string,mixed> */
function vg_pe008_db_transport_bootstrap(string $mode, string $wpCli, string $wordpressRoot, ?string $socketOverride, array $environment, string $wpProbe): array
{
    $mysqliSocket = (string) ini_get('mysqli.default_socket');
    $pdoSocket = (string) ini_get('pdo_mysql.default_socket');
    $arguments = [
        '--path=' . rtrim($wordpressRoot, '/'),
        '--skip-packages',
        '--skip-plugins',
        '--skip-themes',
        'pe008',
        'db-transport-boot',
    ];
    $command = vg_pe008_db_transport_wp_command(
        $mode,
        $wpCli,
        $wordpressRoot,
        $wpProbe,
        $socketOverride,
        PHP_BINARY,
        $mysqliSocket,
        $pdoSocket,
        $arguments
    );
    $childEnvironment = vg_pe008_db_transport_child_environment($environment, $wpProbe);
    $result = vg_pe008_db_transport_run_process($command, $childEnvironment, true);
    return vg_pe008_db_transport_bootstrap_result($result);
}

/** @param array<string,string> $environment @return array<string,mixed> */
function vg_pe008_db_transport_run(array $options, ?array $environment = null, ?string $wpCliOverride = null): array
{
    $environment ??= getenv() ?: [];
    $target = $options['target'] ?? null;
    $wordpressRoot = $options['wordpress_root'] ?? null;
    if ($target !== VG_PE008_DB_DIAGNOSTIC_TARGET || $wordpressRoot !== VG_PE008_DB_DIAGNOSTIC_WORDPRESS_ROOT
        || realpath(VG_PE008_DB_DIAGNOSTIC_WORDPRESS_ROOT) !== VG_PE008_DB_DIAGNOSTIC_WORDPRESS_ROOT
        || is_link(VG_PE008_DB_DIAGNOSTIC_WORDPRESS_ROOT)) {
        return ['probe_started' => false, 'process_error_code' => 'invalid_runtime_identity'];
    }
    foreach ([
        'VGNOVA_MUTATION_SESSION_ID',
        'VGNOVA_MUTATION_TARGET',
        'VGNOVA_MUTATION_DB_LOCK_A',
        'VGNOVA_MUTATION_DB_CONNECTION_ID_A',
        'VGNOVA_MUTATION_DB_LOCK_B',
        'VGNOVA_MUTATION_DB_CONNECTION_ID_B',
        'VGNOVA_MUTATION_LOCK_FILE',
    ] as $proofKey) {
        if (! isset($environment[$proofKey]) || $environment[$proofKey] === '') {
            return ['probe_started' => false, 'process_error_code' => 'coordinator_session_environment_missing'];
        }
    }
    if (($environment['VGNOVA_MUTATION_TARGET'] ?? null) !== VG_PE008_DB_DIAGNOSTIC_TARGET) {
        return ['probe_started' => false, 'process_error_code' => 'coordinator_target_mismatch'];
    }
    $wpCli = $wpCliOverride ?? vg_pe008_db_transport_find_wp_cli($environment);
    if (! is_string($wpCli) || ! is_file($wpCli) || ! is_executable($wpCli)) {
        return ['probe_started' => false, 'process_error_code' => 'wp_cli_not_found'];
    }
    try {
        $wpFacts = vg_pe008_db_transport_wp_executable_facts($wpCli);
    } catch (Throwable) {
        return ['probe_started' => false, 'process_error_code' => 'wp_cli_invalid'];
    }
    $wpProbe = dirname(__DIR__) . '/runtime-tools/pe008-wp-cli-transport-diagnostic.php';
    $mysqliProbe = dirname(__DIR__) . '/runtime-tools/pe008-db-transport-probe.php';
    if (! is_file($wpProbe) || is_link($wpProbe) || ! is_file($mysqliProbe) || is_link($mysqliProbe)) {
        return ['probe_started' => false, 'process_error_code' => 'diagnostic_source_missing'];
    }
    try {
        $configEnvironment = vg_pe008_db_transport_child_environment($environment, null, false);
        $childEnvironment = vg_pe008_db_transport_child_environment($environment);
    } catch (Throwable) {
        return ['probe_started' => false, 'process_error_code' => 'child_environment_invalid'];
    }
    $socketOverride = isset($environment['VGNOVA_DB_SOCKET']) && $environment['VGNOVA_DB_SOCKET'] !== ''
        ? $environment['VGNOVA_DB_SOCKET']
        : null;
    $config = vg_pe008_db_transport_read_all_config($wpCli, $wordpressRoot, $configEnvironment);
    $transportFacts = null;
    $coordinatorMysql = ['result' => 'FAIL', 'error_code' => $config['error_code'] ?? 'wp_config_read_failed'];
    if ($config['ok']) {
        try {
            $transport = VgRuntimeMutationCore::parseDbHost($config['configuration']['DB_HOST'], $socketOverride);
            $transportFacts = vg_pe008_db_transport_host_facts($config['configuration']['DB_HOST'], $socketOverride);
            $coordinatorMysql = vg_pe008_db_transport_mysqli_connect($config['configuration'], $transport);
        } catch (InvalidArgumentException) {
            $coordinatorMysql = ['result' => 'FAIL', 'error_code' => 'db_host_invalid'];
        }
    }
    $nestedCommand = vg_pe008_db_transport_mysqli_command(
        PHP_BINARY,
        $mysqliProbe,
        $wordpressRoot,
        $wpCli,
        $socketOverride,
        (string) ini_get('mysqli.default_socket'),
        (string) ini_get('pdo_mysql.default_socket')
    );
    $nestedProcess = vg_pe008_db_transport_run_process($nestedCommand, $configEnvironment, true);
    $nestedMysql = vg_pe008_db_transport_mysqli_result($nestedProcess);
    if (! $nestedProcess['started']) {
        $nestedMysql['error_code'] = $nestedProcess['error_code'];
    }
    $infoEnvironment = vg_pe008_db_transport_child_environment($environment);
    $infoDirect = vg_pe008_db_transport_info('direct', $wpCli, $socketOverride, $infoEnvironment);
    $infoForced = vg_pe008_db_transport_info('forced', $wpCli, $socketOverride, $infoEnvironment);
    $directBoot = vg_pe008_db_transport_bootstrap('direct', $wpCli, $wordpressRoot, $socketOverride, $environment, $wpProbe);
    $forcedDefaultBoot = vg_pe008_db_transport_bootstrap('forced_default', $wpCli, $wordpressRoot, $socketOverride, $environment, $wpProbe);
    $forcedBoot = vg_pe008_db_transport_bootstrap('forced', $wpCli, $wordpressRoot, $socketOverride, $environment, $wpProbe);
    $report = [
        'probe_started' => true,
        'process_error_code' => null,
        'matrix' => [
            'PE007_DIRECT_MYSQLI' => $coordinatorMysql,
            'NESTED_DIRECT_MYSQLI' => $nestedMysql,
            'DIRECT_WP_EXEC' => $directBoot,
            'FORCED_PARENT_PHP_DEFAULT_INI' => $forcedDefaultBoot,
            'FORCED_PARENT_PHP_WP_EXEC' => $forcedBoot,
        ],
        'db_host' => $transportFacts ?? [],
        'php' => [
            'coordinator' => vg_pe008_db_transport_php_context($environment),
            'nested_forced' => is_array($nestedMysql['php_context'] ?? null) ? $nestedMysql['php_context'] : [],
            'wp_cli_native' => [
                'php_version' => $infoDirect['php_version'],
                'php_binary' => $infoDirect['php_binary'],
                'php_ini' => $infoDirect['php_ini'],
                'mysqli_available' => null,
                'mysqli_default_socket' => null,
                'pdo_mysql_default_socket' => null,
                'mysqli_socket_matches_override' => false,
            ],
        ],
        'wp_cli' => ['executable' => $wpFacts, 'info' => ['direct' => $infoDirect, 'forced' => $infoForced]],
        'environment_presence' => vg_pe008_db_transport_environment_presence($environment),
        'socket_override' => [
            'present' => $socketOverride !== null,
            'facts' => vg_pe008_db_transport_path_facts($socketOverride),
        ],
    ];
    $report['primary_classification'] = vg_pe008_db_transport_primary_classification($report);
    return vg_pe008_db_transport_sanitize_report($report);
}

/** @param array<string,mixed> $report */
function vg_pe008_db_transport_primary_classification(array $report): string
{
    $matrix = is_array($report['matrix'] ?? null) ? $report['matrix'] : [];
    $coordinator = $matrix['PE007_DIRECT_MYSQLI']['result'] ?? null;
    $nested = $matrix['NESTED_DIRECT_MYSQLI']['result'] ?? null;
    $directWp = $matrix['DIRECT_WP_EXEC']['result'] ?? null;
    $forcedDefault = $matrix['FORCED_PARENT_PHP_DEFAULT_INI']['result'] ?? null;
    $forced = $matrix['FORCED_PARENT_PHP_WP_EXEC']['result'] ?? null;
    $php = is_array($report['php'] ?? null) ? $report['php'] : [];
    $parent = is_array($php['coordinator'] ?? null) ? $php['coordinator'] : [];
    $native = is_array($php['wp_cli_native'] ?? null) ? $php['wp_cli_native'] : [];
    $nestedPhp = is_array($php['nested_forced'] ?? null) ? $php['nested_forced'] : [];
    $sameBinary = ($parent['php_binary']['path_sha256'] ?? null) !== ''
        && ($parent['php_binary']['path_sha256'] ?? null) === ($native['php_binary']['path_sha256'] ?? null);
    $sameVersion = ($parent['php_version'] ?? null) !== '' && ($parent['php_version'] ?? null) === ($native['php_version'] ?? null);
    $sameIni = ($parent['php_ini']['file_sha256'] ?? null) !== ''
        && ($parent['php_ini']['file_sha256'] ?? null) === ($native['php_ini']['file_sha256'] ?? null);
    $nestedSameIni = ($parent['php_ini']['file_sha256'] ?? null) !== ''
        && ($parent['php_ini']['file_sha256'] ?? null) === ($nestedPhp['php_ini']['file_sha256'] ?? null);
    $host = is_array($report['db_host'] ?? null) ? $report['db_host'] : [];
    if (($report['socket_override']['present'] ?? false) === true
        && ($nestedPhp['socket_override_present'] ?? true) === false) {
        return 'A_SOCKET_OVERRIDE_NOT_PROPAGATED';
    }
    if ($coordinator === 'PASS' && $nested === 'FAIL') {
        if (! $nestedSameIni) {
            return 'D_PHP_INI_CONTEXT_DIVERGENCE';
        }
        return 'H_ROOT_CAUSE_STILL_UNRESOLVED';
    }
    if ($forcedDefault === 'PASS' && $forced === 'FAIL'
        && ($host['db_host_kind'] ?? null) === 'host_only'
        && ($report['socket_override']['present'] ?? false) === true) {
        return 'B_DB_HOST_SOCKET_OR_PORT_INTERPRETATION_DIVERGENCE';
    }
    if ($directWp === 'PASS' && $forcedDefault === 'FAIL') {
        if (! $sameBinary || ! $sameVersion) {
            return 'C_WRONG_PHP_BINARY_FOR_LOCAL_WP_CLI';
        }
        if (! $sameIni) {
            return 'D_PHP_INI_CONTEXT_DIVERGENCE';
        }
    }
    if ($directWp === 'FAIL' && $forcedDefault === 'PASS' && (! $sameBinary || ! $sameVersion)) {
        return 'C_WRONG_PHP_BINARY_FOR_LOCAL_WP_CLI';
    }
    if ($coordinator === 'PASS' && $nested === 'PASS' && $directWp === 'FAIL' && $forcedDefault === 'FAIL' && $forced === 'FAIL') {
        return 'F_DIRECT_MYSQLI_WORKS_BUT_WPDB_BOOTSTRAP_FAILS';
    }
    return 'H_ROOT_CAUSE_STILL_UNRESOLVED';
}

/** @return array<string,string>|null */
function vg_pe008_db_transport_parse_options(array $arguments): ?array
{
    $options = [];
    foreach ($arguments as $argument) {
        if (! is_string($argument) || ! preg_match('/\A--(target|wp-root)=(.*)\z/s', $argument, $matches)
            || array_key_exists($matches[1], $options)) {
            return null;
        }
        $options[$matches[1]] = $matches[2];
    }
    if (count($options) !== 2 || ! isset($options['target'], $options['wp-root'])) {
        return null;
    }
    return ['target' => $options['target'], 'wordpress_root' => $options['wp-root']];
}

function vg_pe008_db_transport_main(): never
{
    $options = vg_pe008_db_transport_parse_options(array_slice($_SERVER['argv'] ?? [], 1));
    if ($options === null) {
        fwrite(STDOUT, json_encode(['probe_started' => false, 'process_error_code' => 'invalid_arguments'], JSON_THROW_ON_ERROR) . "\n");
        exit(1);
    }
    try {
        $result = vg_pe008_db_transport_run($options);
    } catch (Throwable) {
        $result = ['probe_started' => false, 'process_error_code' => 'diagnostic_failed'];
    }
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    exit(($result['probe_started'] ?? false) === true ? 0 : 1);
}

if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    vg_pe008_db_transport_main();
}
