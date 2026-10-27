<?php
declare(strict_types=1);

require_once __DIR__ . '/pe008-db-transport-diagnostic.php';

/** @return array{wp_root:string,wp_cli:string}|null */
function vg_pe008_db_transport_probe_arguments(array $arguments): ?array
{
    $options = [];
    foreach ($arguments as $argument) {
        if (! is_string($argument) || ! preg_match('/\A--(wp-root|wp-cli)=(.*)\z/s', $argument, $matches)
            || array_key_exists($matches[1], $options)) {
            return null;
        }
        $options[$matches[1]] = $matches[2];
    }
    if (count($options) !== 2 || ! isset($options['wp-root'], $options['wp-cli'])
        || ! str_starts_with($options['wp-root'], '/') || ! str_starts_with($options['wp-cli'], '/')) {
        return null;
    }
    return ['wp_root' => $options['wp-root'], 'wp_cli' => $options['wp-cli']];
}

/** @param array<string,string> $environment @return array<string,string> */
function vg_pe008_db_transport_probe_read_configuration(string $wpCli, string $wordpressRoot, array $environment): array
{
    return [
        'DB_NAME' => vg_pe008_db_transport_read_config($wpCli, $wordpressRoot, 'DB_NAME', 'constant', $environment),
        'DB_USER' => vg_pe008_db_transport_read_config($wpCli, $wordpressRoot, 'DB_USER', 'constant', $environment),
        'DB_PASSWORD' => vg_pe008_db_transport_read_config($wpCli, $wordpressRoot, 'DB_PASSWORD', 'constant', $environment),
        'DB_HOST' => vg_pe008_db_transport_read_config($wpCli, $wordpressRoot, 'DB_HOST', 'constant', $environment),
    ];
}

/** @param list<string> $arguments */
function vg_pe008_db_transport_probe_main(array $arguments): never
{
    $options = vg_pe008_db_transport_probe_arguments($arguments);
    if ($options === null || ! is_dir($options['wp_root']) || ! is_file($options['wp_cli']) || ! is_executable($options['wp_cli'])) {
        fwrite(STDOUT, json_encode(['result' => 'FAIL', 'error_code' => 'invalid_probe_arguments'], JSON_THROW_ON_ERROR) . "\n");
        exit(0);
    }
    $environment = getenv() ?: [];
    $report = ['result' => 'FAIL', 'error_code' => null, 'php_context' => vg_pe008_db_transport_php_context($environment)];
    try {
        $configuration = vg_pe008_db_transport_probe_read_configuration($options['wp_cli'], $options['wp_root'], $environment);
        $override = isset($environment['VGNOVA_DB_SOCKET']) && $environment['VGNOVA_DB_SOCKET'] !== ''
            ? $environment['VGNOVA_DB_SOCKET']
            : null;
        $transport = VgRuntimeMutationCore::parseDbHost($configuration['DB_HOST'], $override);
        $connection = vg_pe008_db_transport_mysqli_connect($configuration, $transport);
        $report['result'] = $connection['result'];
        $report['error_code'] = $connection['error_code'];
    } catch (InvalidArgumentException) {
        $report['error_code'] = 'database_transport_configuration_invalid';
    } catch (Throwable) {
        $report['error_code'] = 'wp_config_read_failed';
    }
    fwrite(STDOUT, json_encode($report, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    exit(0);
}

if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    vg_pe008_db_transport_probe_main(array_slice($_SERVER['argv'] ?? [], 1));
}
