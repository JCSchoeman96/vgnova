<?php
declare(strict_types=1);

if (! defined('WP_CLI') || WP_CLI !== true || ! class_exists('WP_CLI')) {
    return;
}

WP_CLI::add_hook('before_wp_load', static function (): void {
    if (! defined('DISABLE_WP_CRON')) {
        define('DISABLE_WP_CRON', true);
    }
    if (! defined('WPMU_PLUGIN_DIR')) {
        define('WPMU_PLUGIN_DIR', '/dev/null');
    } elseif (WPMU_PLUGIN_DIR !== '/dev/null') {
        WP_CLI::error('PE-008 guard bootstrap cannot suppress the configured MU-plugin directory.');
    }
});

WP_CLI::add_command('pe008 session-guard', static function (array $arguments): void {
    if (count($arguments) !== 2) {
        WP_CLI::error('PE-008 session guard expects a target and staged guard path.');
    }
    [$target, $guard_path] = $arguments;
    $guard_realpath = realpath($guard_path);
    $stage_root = is_string($guard_realpath) ? dirname(dirname(dirname($guard_realpath))) : '';
    if ($target !== 'vgnova-local-10039'
        || ! is_string($guard_realpath)
        || is_link($guard_path)
        || basename($guard_realpath) !== 'RuntimeMutationSession.php'
        || basename(dirname($guard_realpath)) !== 'Runtime'
        || basename(dirname(dirname($guard_realpath))) !== 'includes'
        || dirname($stage_root) !== '/home/jcschoeman96/Local Sites/voelgoed-novamira/app'
        || preg_match('/\Avoelgoed-platform-core\.pe008-stage-[A-Za-z0-9T_-]+\z/', basename($stage_root)) !== 1) {
        WP_CLI::error('PE-008 staged guard path is invalid.');
    }
    require_once $guard_realpath;
    if (! function_exists('vg_runtime_mutation_assert_session')) {
        WP_CLI::error('Staged mutation-session guard is unavailable.');
    }
    vg_runtime_mutation_assert_session($target);
}, [
    'when' => 'after_wp_load',
    'synopsis' => [
        ['type' => 'positional', 'name' => 'target', 'optional' => false],
        ['type' => 'positional', 'name' => 'guard_path', 'optional' => false],
    ],
]);
