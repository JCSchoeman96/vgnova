<?php
declare(strict_types=1);

/** @return array<string,mixed>|null */
function vg_pe008_wp_cli_session_diagnostic_payload(array $report): ?array
{
    if (! is_bool($report['valid'] ?? null)
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
    $owners = [];
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
        $owners[$slot] = $expected_normalized !== null
            && $observed_normalized !== null
            && $expected_normalized === $observed_normalized;
    }

    return [
        'probe_executed' => true,
        'session_valid' => $report['valid'],
        'errors' => array_values($report['errors']),
        'filesystem_lock_held' => $report['filesystem_lock_held'],
        'database_lock_a' => ['owner_matches' => $owners['a']],
        'database_lock_b' => ['owner_matches' => $owners['b']],
    ];
}

/** @param mixed $guard_realpath */
function vg_pe008_wp_cli_session_diagnostic_guard_path_is_valid(
    string $target,
    string $guard_path,
    mixed $guard_realpath,
    string $allowed_app = '/home/jcschoeman96/Local Sites/voelgoed-novamira/app'
): bool {
    if ($target !== 'vgnova-local-10039'
        || ! is_string($guard_realpath)
        || ! is_file($guard_realpath)
        || is_link($guard_path)
        || $guard_path !== $guard_realpath
        || basename($guard_realpath) !== 'RuntimeMutationSession.php'
        || basename(dirname($guard_realpath)) !== 'Runtime'
        || basename(dirname(dirname($guard_realpath))) !== 'includes') {
        return false;
    }
    $stage_root = dirname(dirname(dirname($guard_realpath)));
    return dirname($stage_root) === $allowed_app
        && preg_match('/\Avoelgoed-platform-core\.pe008-stage-[A-Za-z0-9T_-]+\z/', basename($stage_root)) === 1;
}

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
        WP_CLI::error('PE-008 diagnostic bootstrap cannot suppress the configured MU-plugin directory.');
    }
});

WP_CLI::add_command('pe008 session-diagnostic', static function (array $arguments): void {
    if (count($arguments) !== 2) {
        WP_CLI::error('PE-008 session diagnostic expects a target and staged guard path.');
    }
    [$target, $guard_path] = $arguments;
    $guard_realpath = realpath($guard_path);
    if (! vg_pe008_wp_cli_session_diagnostic_guard_path_is_valid($target, $guard_path, $guard_realpath)) {
        WP_CLI::error('PE-008 staged guard path is invalid.');
    }
    require_once $guard_realpath;
    if (! function_exists('vg_runtime_mutation_session_report')) {
        WP_CLI::error('Staged mutation-session report is unavailable.');
    }
    try {
        $report = vg_runtime_mutation_session_report($target);
        $payload = vg_pe008_wp_cli_session_diagnostic_payload($report);
    } catch (Throwable) {
        WP_CLI::error('PE-008 session diagnostic report failed.');
    }
    if (! is_array($payload)) {
        WP_CLI::error('PE-008 session diagnostic report is malformed.');
    }
    WP_CLI::line(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}, [
    'when' => 'after_wp_load',
    'synopsis' => [
        ['type' => 'positional', 'name' => 'target', 'optional' => false],
        ['type' => 'positional', 'name' => 'guard_path', 'optional' => false],
    ],
]);
