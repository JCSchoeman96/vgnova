<?php
declare(strict_types=1);

/** Read-only PE-007 session and Platform Core runtime health check. */

$target_id = $argv[2] ?? '';
$requested_root = $argv[3] ?? '';
$mode = $argv[1] ?? '';
$registered_root = '/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public';
$expected_target = [
    'id' => 'vgnova-local-10039',
    'home' => 'http://localhost:10039',
    'site' => 'http://localhost:10039',
    'database_name' => 'local',
    'database_host' => 'localhost',
    'platform_core_entrypoint' => 'wp-content/plugins/voelgoed-platform-core/voelgoed-platform-core.php',
];
$result = [
    'ok' => false,
    'target' => $target_id,
    'wp_root' => $requested_root,
    'session_valid' => false,
];
$captured_errors = [];
$captured_output = '';
$bootstrapped = false;

$emit = static function (array $value): never {
    if (is_array($value['checks'] ?? null)) {
        ksort($value['checks'], SORT_STRING);
    }
    ksort($value, SORT_STRING);
    fwrite(STDOUT, json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
    exit(($value['ok'] ?? false) === true ? 0 : 1);
};

if (! in_array($mode, ['session', 'health'], true)
    || $target_id !== 'vgnova-local-10039'
    || rtrim($requested_root, '/') !== $registered_root
    || rtrim((string) (getenv('VGNOVA_MUTATION_WP_ROOT') ?: ''), '/') !== $registered_root) {
    $emit($result);
}

$root = realpath($registered_root);
$wp_content_path = $registered_root . '/wp-content';
$plugins_path = $wp_content_path . '/plugins';
$plugin_root_path = $registered_root . '/wp-content/plugins/voelgoed-platform-core';
$entrypoint = $registered_root . '/' . $expected_target['platform_core_entrypoint'];
$entrypoint_real = realpath($entrypoint);
$wp_load = $registered_root . '/wp-load.php';
if ($root === false
    || $root !== $registered_root
    || is_link($registered_root)
    || ! is_dir($wp_content_path)
    || is_link($wp_content_path)
    || realpath($wp_content_path) !== $wp_content_path
    || ! is_dir($plugins_path)
    || is_link($plugins_path)
    || realpath($plugins_path) !== $plugins_path
    || ! is_file($wp_load)
    || is_link($wp_load)
    || ($mode === 'health' && (
        is_link($plugin_root_path)
        || ! is_file($entrypoint)
        || $entrypoint_real === false
        || is_link($entrypoint)
        || ! str_starts_with($entrypoint_real, $registered_root . '/wp-content/plugins/voelgoed-platform-core/')
    ))) {
    $emit($result);
}

$registry_path = dirname(__DIR__) . '/runtime-tools/runtime-mutation-targets.json';
$registry_json = @file_get_contents($registry_path);
try {
    $registry = is_string($registry_json) ? json_decode($registry_json, true, 32, JSON_THROW_ON_ERROR) : null;
} catch (JsonException) {
    $registry = null;
}
$registered = null;
if (is_array($registry['targets'] ?? null)) {
    foreach ($registry['targets'] as $candidate) {
        if (is_array($candidate) && ($candidate['id'] ?? null) === $target_id) {
            $registered = $candidate;
            break;
        }
    }
}
if ($registered !== $expected_target) {
    $emit($result);
}

require_once __DIR__ . '/runtime-mutation-session.php';

$initial_buffer_level = ob_get_level();
ob_start();
set_error_handler(static function (int $severity, string $message) use (&$captured_errors): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }
    $captured_errors[] = $severity;
    return true;
});

try {
    require $wp_load;
    $bootstrapped = defined('ABSPATH') && rtrim((string) ABSPATH, '/') === $registered_root;
} catch (Throwable) {
    $captured_errors[] = E_ERROR;
}
try {
    vg_runtime_mutation_assert_session($target_id);
    $result['session_valid'] = true;
} catch (Throwable) {
    $result['session_valid'] = false;
}

while (ob_get_level() > $initial_buffer_level) {
    $buffer = ob_get_clean();
    if (is_string($buffer)) {
        $captured_output = $buffer . $captured_output;
    }
}
restore_error_handler();

$checks = $mode === 'session'
    ? ['session_valid' => $result['session_valid']]
    : [
        'wordpress_bootstrapped' => $bootstrapped,
        'no_php_diagnostics' => $captured_errors === [],
        'no_unexpected_output' => $captured_output === '',
        'session_valid' => $result['session_valid'],
    ];

if ($mode === 'health' && $bootstrapped) {
    global $wpdb, $wp_filter;
    $home = function_exists('home_url') ? (string) home_url('/') : '';
    $site = function_exists('site_url') ? (string) site_url('/') : '';
    $database_name = defined('DB_NAME') ? (string) DB_NAME : (is_object($wpdb) ? (string) ($wpdb->dbname ?? '') : '');
    $active_plugins = function_exists('get_option') ? get_option('active_plugins', []) : [];
    $entrypoint_relative = 'voelgoed-platform-core/voelgoed-platform-core.php';
    $plugin_active = is_array($active_plugins) && in_array($entrypoint_relative, $active_plugins, true);
    $plugin_class = 'Voelgoed\\PlatformCore\\Plugin';
    $plugin_loaded = class_exists($plugin_class, false);
    $plugin_file_matches = false;
    if ($plugin_loaded) {
        $reflection = new ReflectionClass($plugin_class);
        $class_file = $reflection->getFileName();
        $plugin_root = realpath($registered_root . '/wp-content/plugins/voelgoed-platform-core');
        $resolved_class_file = is_string($class_file) ? realpath($class_file) : false;
        $plugin_file_matches = is_string($plugin_root)
            && is_string($resolved_class_file)
            && str_starts_with($resolved_class_file, $plugin_root . '/');
    }

    $lifecycle_registered = false;
    $init_hook = is_array($wp_filter ?? null) ? ($wp_filter['init'] ?? null) : null;
    if (is_object($init_hook) && is_array($init_hook->callbacks ?? null)) {
        foreach ($init_hook->callbacks as $callbacks) {
            if (! is_array($callbacks)) {
                continue;
            }
            foreach ($callbacks as $registration) {
                $callback = is_array($registration) ? ($registration['function'] ?? null) : null;
                if (is_array($callback)
                    && is_object($callback[0] ?? null)
                    && get_class($callback[0]) === 'Voelgoed\\PlatformCore\\Taxonomy\\ContentTopicTaxonomy'
                    && ($callback[1] ?? null) === 'register_taxonomy') {
                    $lifecycle_registered = true;
                    break 2;
                }
            }
        }
    }

    $site_name = function_exists('get_bloginfo') ? (string) get_bloginfo('name') : '';
    $checks['runtime_identity'] = $home === $expected_target['home'] . '/'
        && $site === $expected_target['site'] . '/'
        && $database_name === $expected_target['database_name'];
    $checks['plugin_entrypoint_active'] = $plugin_active;
    $checks['plugin_class_loaded'] = $plugin_loaded && $plugin_file_matches;
    $checks['lifecycle_registered'] = $lifecycle_registered;
    $checks['site_name_available'] = $site_name !== '';
    $result['home'] = $home;
    $result['site'] = $site;
    $result['database'] = $database_name;
    $result['site_name'] = $site_name;
}

$result['checks'] = $checks;
$result['ok'] = ! in_array(false, $checks, true) && ($mode !== 'health' || isset($result['site_name']));
$emit($result);
