<?php
declare(strict_types=1);

if (! defined('WP_CLI') || WP_CLI !== true || ! class_exists('WP_CLI')) {
    return;
}

WP_CLI::add_hook('before_wp_load', static function (): void {
    if (! defined('DISABLE_WP_CRON')) {
        define('DISABLE_WP_CRON', true);
    }
});

WP_CLI::add_command('pe008 runtime-smoke', static function (array $arguments): void {
    if (count($arguments) !== 3) {
        WP_CLI::error('PE-008 runtime smoke expects plugin path, target, and guard mode.');
    }
    [$plugin_entrypoint, $target, $guard_mode] = $arguments;
    if (! in_array($guard_mode, ['required', 'optional'], true) || $target !== 'vgnova-local-10039') {
        WP_CLI::error('PE-008 runtime smoke arguments are invalid.');
    }
    if (PHP_VERSION_ID < 80500 || PHP_VERSION_ID >= 80600) {
        WP_CLI::error('Runtime smoke requires PHP 8.5.x.');
    }
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $expected_entrypoint = WP_PLUGIN_DIR . '/voelgoed-platform-core/voelgoed-platform-core.php';
    $expected_realpath = realpath($expected_entrypoint);
    if (! is_string($expected_realpath)
        || realpath($plugin_entrypoint) !== $expected_realpath
        || ! is_plugin_active('voelgoed-platform-core/voelgoed-platform-core.php')) {
        WP_CLI::error('Platform Core is not active at the expected path.');
    }
    if ($guard_mode === 'required') {
        if (! function_exists('vg_runtime_mutation_assert_session')) {
            WP_CLI::error('Active Platform Core mutation-session guard is unavailable.');
        }
        vg_runtime_mutation_assert_session($target);
    }
    $plugin_data = get_plugin_data($expected_realpath, false, false);
    if (($plugin_data['Version'] ?? '') !== '0.1.0') {
        WP_CLI::error('Platform Core version is not 0.1.0.');
    }
}, [
    'when' => 'after_wp_load',
    'synopsis' => [
        ['type' => 'positional', 'name' => 'plugin_entrypoint', 'optional' => false],
        ['type' => 'positional', 'name' => 'target', 'optional' => false],
        ['type' => 'positional', 'name' => 'guard_mode', 'optional' => false, 'options' => ['required', 'optional']],
    ],
]);
