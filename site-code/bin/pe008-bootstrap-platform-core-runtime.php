<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/runtime-tools/runtime-mutation-core.php';

/** @param list<string> $command_arguments @return list<string> */
function vg_pe008_wp_cli_command(
    string $wp_cli,
    string $wordpress_root,
    string $command_library,
    array $command_arguments,
    ?string $socket_override,
    bool $skip_plugins_and_theme = false
): array
{
    if (! str_starts_with($wp_cli, '/')
        || ! str_starts_with($wordpress_root, '/')
        || ! str_starts_with($command_library, '/')
        || ! is_file($command_library)
        || $command_arguments === []
        || ! array_is_list($command_arguments)) {
        throw new InvalidArgumentException('WP-CLI command inputs are invalid.');
    }
    foreach ($command_arguments as $argument) {
        if (! is_string($argument) || str_contains($argument, "\0")) {
            throw new InvalidArgumentException('WP-CLI command arguments must be safe strings.');
        }
    }
    if ($socket_override !== null && ! VgRuntimeMutationCore::isAbsolutePathForDatabase($socket_override)) {
        throw new InvalidArgumentException('Database socket override must be an absolute path.');
    }
    $mysqli_socket = $socket_override ?? (string) ini_get('mysqli.default_socket');
    $pdo_socket = $socket_override ?? (string) ini_get('pdo_mysql.default_socket');
    $command = [
        PHP_BINARY,
        '-d', 'mysqli.default_socket=' . $mysqli_socket,
        '-d', 'pdo_mysql.default_socket=' . $pdo_socket,
        $wp_cli,
        '--path=' . $wordpress_root,
        '--skip-packages',
    ];
    if ($skip_plugins_and_theme) {
        $command[] = '--skip-plugins';
        $command[] = '--skip-themes';
    }
    return [...$command, ...$command_arguments];
}

/** @return list<string> */
function vg_pe008_guard_command(
    string $wp_cli,
    string $wordpress_root,
    string $command_library,
    string $stage,
    string $target,
    ?string $socket_override
): array
{
    $guard = $stage . '/includes/Runtime/RuntimeMutationSession.php';
    return vg_pe008_wp_cli_command(
        $wp_cli,
        $wordpress_root,
        $command_library,
        ['pe008', 'session-guard', $target, $guard],
        $socket_override,
        true
    );
}

/** @return list<string> */
function vg_pe008_smoke_command(
    string $wp_cli,
    string $wordpress_root,
    string $command_library,
    string $plugin_entrypoint,
    string $target,
    bool $require_guard,
    ?string $socket_override
): array {
    return vg_pe008_wp_cli_command(
        $wp_cli,
        $wordpress_root,
        $command_library,
        ['pe008', 'runtime-smoke', $plugin_entrypoint, $target, $require_guard ? 'required' : 'optional'],
        $socket_override
    );
}

/** @param array<string,string>|null $base_environment @return array<string,string> */
function vg_pe008_subprocess_environment(?array $base_environment, string $command_library): array
{
    if (! str_starts_with($command_library, '/') || ! is_file($command_library) || is_link($command_library)) {
        throw new InvalidArgumentException('WP-CLI command library must be a real source-controlled file.');
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

/**
 * Start an isolated child with the PE-007 host-lock descriptor only, capture its
 * output, and wait for proc_close() before returning to the filesystem parent.
 *
 * @param list<string> $command
 * @param array<string,string> $environment
 * @return array{exit_code:int,stdout:string,stderr:string}
 */
function vg_pe008_run_subprocess(array $command, array $environment, string $command_library): array
{
    if ($command === [] || ! array_is_list($command)) {
        return ['exit_code' => 70, 'stdout' => '', 'stderr' => 'invalid_child_command'];
    }
    try {
        $safe_command = VgRuntimeMutationCore::descriptorSafeCommand($command, [3]);
        $safe_environment = vg_pe008_subprocess_environment($environment, $command_library);
    } catch (Throwable) {
        return ['exit_code' => 70, 'stdout' => '', 'stderr' => 'invalid_child_configuration'];
    }
    $descriptors = [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
        3 => ['file', 'php://fd/3', 'r+'],
    ];
    $process = @proc_open($safe_command, $descriptors, $pipes, '/', $safe_environment);
    if (! is_resource($process)) {
        return ['exit_code' => 70, 'stdout' => '', 'stderr' => 'child_start_failed'];
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
    ];
}

function vg_pe008_cli_exit_code(array $result): int
{
    return match ($result['status'] ?? null) {
        'SWAPPED', 'SKIPPED_ALREADY_EXACT' => 0,
        'BLOCKED' => 1,
        'BLOCKED_CRITICAL' => 2,
        'ROLLED_BACK' => 3,
        default => 70,
    };
}

function vg_pe008_find_wp_cli(?array $environment = null): ?string
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

function vg_pe008_subprocess_output_is_clean(array $result): bool
{
    $stdout = $result['stdout'] ?? null;
    $stderr = $result['stderr'] ?? null;
    if (($result['exit_code'] ?? null) !== 0 || ! is_string($stdout) || ! is_string($stderr) || $stderr !== '') {
        return false;
    }
    return preg_match('/\b(?:Warning|Notice|Deprecated|Fatal error|Uncaught)\b/i', $stdout) !== 1;
}

/** PE-008 one-shot deployment state machine. Callbacks are exposed only to offline tests. */
function vg_pe008_bootstrap_run(array $config): array
{
    $blocked = static fn (string $reason): array => ['status' => 'BLOCKED', 'reason' => $reason];
    foreach (['target', 'wordpress_root', 'active', 'stage', 'backup', 'failed_parent', 'old_manifest', 'new_manifest', 'verify', 'guard', 'rename', 'smoke'] as $key) {
        if (! array_key_exists($key, $config)) {
            return $blocked('missing_' . $key);
        }
    }
    $active = $config['active'];
    $stage = $config['stage'];
    $backup = $config['backup'];
    $verify = $config['verify'];
    $guard = $config['guard'];
    $rename = $config['rename'];
    $smoke = $config['smoke'];
    if (! is_callable($verify) || ! is_callable($guard) || ! is_callable($rename) || ! is_callable($smoke)) {
        return $blocked('invalid_dependency');
    }
    $old = $verify($active, 'old');
    $new_active = $verify($active, 'new');
    if (($new_active['exact'] ?? false) === true) {
        return ['status' => 'SKIPPED_ALREADY_EXACT'];
    }
    if (($old['exact'] ?? false) !== true) {
        return $blocked('active_not_exact_old');
    }
    $new_stage = $verify($stage, 'new');
    if (($new_stage['exact'] ?? false) !== true
        || ($new_stage['symlinks'] ?? []) !== []
        || ($new_stage['unsupported'] ?? []) !== []
        || ($new_stage['world_writable'] ?? []) !== []) {
        return $blocked('stage_not_safe_exact_new');
    }
    if (file_exists($backup) || is_link($backup)) {
        return $blocked('backup_exists');
    }
    $failed_reservation = $config['failed_parent'] . '/.pe008-failed-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(8));
    $failed = $failed_reservation . '/voelgoed-platform-core';
    $active_stat = @stat($active);
    $stage_stat = @stat($stage);
    $parent_stat = @stat(dirname($backup));
    $failed_parent_stat = @stat($config['failed_parent']);
    if (! is_array($active_stat) || ! is_array($stage_stat) || ! is_array($parent_stat)
        || ! is_array($failed_parent_stat)
        || $active_stat['dev'] !== $stage_stat['dev'] || $active_stat['dev'] !== $parent_stat['dev']
        || $active_stat['dev'] !== $failed_parent_stat['dev']) {
        return $blocked('cross_device_or_missing_tree');
    }
    try {
        if ($guard($config['target']) === false) {
            return $blocked('mutation_session_invalid');
        }
    } catch (Throwable $error) {
        return $blocked('mutation_session_invalid: ' . $error->getMessage());
    }
    if (! @mkdir($failed_reservation, 0700)) {
        return $blocked('failed_path_reservation_failed');
    }
    if (! $rename($active, $backup)) {
        @rmdir($failed_reservation);
        return $blocked('first_rename_failed');
    }
    try {
        $second_ok = $rename($stage, $active);
        $verify_new = $second_ok ? $verify($active, 'new') : ['exact' => false];
        $backup_report = $second_ok && ($verify_new['exact'] ?? false) === true ? $verify($backup, 'old') : ['exact' => false];
        $smoke_ok = $second_ok && ($verify_new['exact'] ?? false) === true
            && ($backup_report['exact'] ?? false) === true && (bool) $smoke($config['wordpress_root'], true);
    } catch (Throwable) {
        $smoke_ok = false;
    }
    if ($smoke_ok) {
        @rmdir($failed_reservation);
        return ['status' => 'SWAPPED', 'backup' => $backup, 'active' => $active];
    }
    $failed_state = 'absent';
    if (file_exists($active) || is_link($active)) {
        if ($rename($active, $failed)) {
            $failed_state = 'preserved';
        } else {
            @rmdir($failed_reservation);
            return ['status' => 'BLOCKED_CRITICAL', 'reason' => 'could_not_preserve_failed_new_tree', 'active' => $active, 'backup' => $backup, 'failed' => $failed];
        }
    } elseif (file_exists($stage) || is_link($stage)) {
        if ($rename($stage, $failed)) {
            $failed_state = 'preserved';
        } else {
            $retained_stage = ['exact' => false];
            if (file_exists($stage) && ! is_link($stage)) {
                try {
                    $retained_stage = $verify($stage, 'new');
                } catch (Throwable) {
                    $retained_stage = ['exact' => false];
                }
            }
            if (($retained_stage['exact'] ?? false) === true) {
                $failed_state = 'retained_at_stage';
                $failed = $stage;
            } else {
                $failed_state = 'unverified_at_stage';
                $failed = $stage;
            }
        }
    }
    if (! $rename($backup, $active)) {
        @rmdir($failed_reservation);
        return ['status' => 'BLOCKED_CRITICAL', 'reason' => 'could_not_restore_backup', 'active' => $active, 'backup' => $backup, 'failed' => $failed, 'failed_state' => $failed_state];
    }
    try {
        $restored = $verify($active, 'old');
        $restored_smoke = ($restored['exact'] ?? false) === true && (bool) $smoke($config['wordpress_root'], false);
    } catch (Throwable) {
        $restored_smoke = false;
    }
    if (! $restored_smoke) {
        @rmdir($failed_reservation);
        return ['status' => 'BLOCKED_CRITICAL', 'reason' => 'restored_old_tree_failed_verification', 'active' => $active, 'backup' => $backup, 'failed' => $failed, 'failed_state' => $failed_state];
    }
    if ($failed_state === 'unverified_at_stage') {
        @rmdir($failed_reservation);
        return ['status' => 'BLOCKED_CRITICAL', 'reason' => 'could_not_verify_retained_failed_stage', 'active' => $active, 'failed' => $failed, 'failed_state' => $failed_state];
    }
    @rmdir($failed_reservation);
    return ['status' => 'ROLLED_BACK', 'active' => $active,
        'failed' => in_array($failed_state, ['preserved', 'retained_at_stage'], true) ? $failed : null,
        'failed_state' => $failed_state];
}

function vg_pe008_manifest_authority(string $path, array $expected): array
{
    $bytes = @file_get_contents($path);
    if (! is_string($bytes)) {
        throw new RuntimeException('Pinned manifest is unreadable: ' . $path);
    }
    $manifest = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
    $authority = [
        'artifact_name' => 'voelgoed-platform-core-' . $expected['source_commit'],
        'source_commit' => $expected['source_commit'],
        'schema_version' => 3,
        'file_count' => $expected['file_count'],
        'tree_sha256' => $expected['tree_sha256'],
        'archive_sha256' => $expected['archive_sha256'],
        'manifest_sha256' => $expected['manifest_sha256'],
    ];
    if (($manifest['file_count'] ?? null) !== $authority['file_count'] || hash('sha256', $bytes) !== $authority['manifest_sha256']) {
        throw new RuntimeException('Pinned manifest differs from the PE-008 authority.');
    }
    return $authority;
}

function vg_pe008_tree_safety(string $path): array
{
    $unsafe = ['symlinks' => [], 'unsupported' => [], 'world_writable' => []];
    $walk = static function (string $directory, string $relative = '') use (&$walk, &$unsafe): void {
        foreach (scandir($directory) ?: [] as $name) {
            if ($name === '.' || $name === '..') { continue; }
            $item = $directory . DIRECTORY_SEPARATOR . $name;
            $rel = $relative === '' ? $name : $relative . '/' . $name;
            $stat = @lstat($item);
            if (! is_array($stat)) { $unsafe['unsupported'][] = $rel; continue; }
            $type = $stat['mode'] & 0170000;
            if (($stat['mode'] & 0002) !== 0) { $unsafe['world_writable'][] = $rel; }
            if ($type === 0120000) { $unsafe['symlinks'][] = $rel; }
            elseif ($type === 0040000) { $walk($item, $rel); }
            elseif ($type !== 0100000) { $unsafe['unsupported'][] = $rel; }
        }
    };
    if (! is_dir($path) || is_link($path)) { return ['symlinks' => [], 'unsupported' => ['.'], 'world_writable' => []]; }
    $root_stat = @lstat($path);
    if (is_array($root_stat) && ($root_stat['mode'] & 0002) !== 0) {
        $unsafe['world_writable'][] = '.';
    }
    $walk($path);
    return $unsafe;
}

/** @param list<string> $arguments @return array{wordpress_root:string,stage:string,backup:string}|null */
function vg_pe008_parse_options(array $arguments): ?array
{
    $wordpress_root = '/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public';
    $app_root = dirname($wordpress_root);
    $values = [];
    foreach ($arguments as $argument) {
        if (! is_string($argument) || preg_match('/\A--(wp-root|stage|backup)=(.*)\z/D', $argument, $matches) !== 1) {
            return null;
        }
        if (array_key_exists($matches[1], $values)) {
            return null;
        }
        $values[$matches[1]] = $matches[2];
    }
    if (count($values) !== 3 || rtrim($values['wp-root'], '/') !== $wordpress_root) {
        return null;
    }
    foreach (['stage', 'backup'] as $key) {
        $path = $values[$key];
        if (dirname($path) !== $app_root || $path === $wordpress_root
            || str_starts_with($path, $wordpress_root . '/wp-content/plugins/')
            || ! str_starts_with(basename($path), 'voelgoed-platform-core.pe008-' . $key . '-')) {
            return null;
        }
    }
    if ($values['stage'] === $values['backup']) {
        return null;
    }
    return ['wordpress_root' => $wordpress_root, 'stage' => $values['stage'], 'backup' => $values['backup']];
}

function vg_pe008_production(): never
{
    $target = 'vgnova-local-10039';
    $options = vg_pe008_parse_options(array_slice($_SERVER['argv'] ?? [], 1));
    if ($options === null) {
        fwrite(STDERR, "Usage: php pe008-bootstrap-platform-core-runtime.php --wp-root='/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public' --stage='/home/jcschoeman96/Local Sites/voelgoed-novamira/app/voelgoed-platform-core.pe008-stage-NAME' --backup='/home/jcschoeman96/Local Sites/voelgoed-novamira/app/voelgoed-platform-core.pe008-backup-NAME'\n");
        exit(2);
    }
    $wordpress_root = $options['wordpress_root'];
    $plugin_name = 'voelgoed-platform-core';
    $active = $wordpress_root . '/wp-content/plugins/' . $plugin_name;
    $stage = $options['stage'];
    $backup = $options['backup'];
    require_once dirname(__DIR__) . '/runtime-tests/platform-core-runtime-integrity.php';
    $old_path = dirname(__DIR__) . '/runtime-tests/fixtures/voelgoed-platform-core-pe008-old.manifest.json';
    $new_path = dirname(__DIR__) . '/runtime-tests/fixtures/voelgoed-platform-core-pe005.manifest.json';
    $old = vg_pe008_manifest_authority($old_path, ['source_commit' => '7af938ac6f4422b1fa779b52444121e7a9293995', 'file_count' => 54, 'tree_sha256' => '856a3e5978559bfb2572b4b1ff4b34df916743f9e72d6b18220ee64e98af5f56', 'archive_sha256' => '9d5fd288025703fe4f71ed1f3ee264cd3fc9c0b61cf74d137b40e941bc2e2243', 'manifest_sha256' => 'c87d339ebe83203a82a3f02b27de9854be9e360835da9893077af9c9c318c1ee']);
    $new = vg_pe008_manifest_authority($new_path, ['source_commit' => 'de90ff061c20b164c127aa97e845e3f192429543', 'file_count' => 55, 'tree_sha256' => '5816b80ac4355782523aa4baa82bd9e55dd87326f15972f287ff1afa7567482a', 'archive_sha256' => '22d998019e3fe3d93d675250a26eba5be3dbc248badd9be4d7507fbdf9cffdb9', 'manifest_sha256' => '7cd38e758babddf495e484013d9345750af6f39689bc428f67d89a814949a932']);
    $base_environment = getenv() ?: [];
    $wp_cli = vg_pe008_find_wp_cli($base_environment);
    $command_library = dirname(__DIR__) . '/runtime-tools/pe008-wp-cli-session-guard.php';
    $smoke_library = dirname(__DIR__) . '/runtime-tools/pe008-wp-cli-runtime-smoke.php';
    $guard = static function (string $expected) use ($active, $stage, $old, $old_path, $new, $new_path, $wordpress_root, $wp_cli, $command_library): bool {
        if ($expected !== 'vgnova-local-10039') { throw new RuntimeException('Wrong target.'); }
        $active_report = vg_product_contribution_qa_platform_core_integrity_report($active, $old_path, $old);
        $stage_report = vg_product_contribution_qa_platform_core_integrity_report($stage, $new_path, $new);
        if (($active_report['exact'] ?? false) !== true || ($stage_report['exact'] ?? false) !== true) { throw new RuntimeException('Trees changed before session validation.'); }
        if (! is_string($wp_cli)) { return false; }
        $socket_override = isset($base_environment['VGNOVA_DB_SOCKET']) && $base_environment['VGNOVA_DB_SOCKET'] !== ''
            ? $base_environment['VGNOVA_DB_SOCKET']
            : null;
        $command = vg_pe008_guard_command($wp_cli, $wordpress_root, $command_library, $stage, $expected, $socket_override);
        return vg_pe008_subprocess_output_is_clean(vg_pe008_run_subprocess($command, $base_environment, $command_library));
    };
    $verify = static function (string $path, string $which) use ($old, $new, $old_path, $new_path): array {
        $authority = $which === 'old' ? $old : $new;
        $manifest = $which === 'old' ? $old_path : $new_path;
        $report = vg_product_contribution_qa_platform_core_integrity_report($path, $manifest, $authority);
        return $report + vg_pe008_tree_safety($path);
    };
    $smoke = static function (string $root, bool $require_guard = true) use ($target, $wp_cli, $base_environment, $smoke_library): bool {
        if (! is_string($wp_cli) || ! is_file($smoke_library)) { return false; }
        $plugin = $root . '/wp-content/plugins/voelgoed-platform-core';
        $socket_override = isset($base_environment['VGNOVA_DB_SOCKET']) && $base_environment['VGNOVA_DB_SOCKET'] !== ''
            ? $base_environment['VGNOVA_DB_SOCKET']
            : null;
        $command = vg_pe008_smoke_command(
            $wp_cli,
            $root,
            $smoke_library,
            $plugin . '/voelgoed-platform-core.php',
            $target,
            $require_guard,
            $socket_override
        );
        return vg_pe008_subprocess_output_is_clean(vg_pe008_run_subprocess($command, $base_environment, $smoke_library));
    };
    $config = ['target' => $target, 'wordpress_root' => $wordpress_root, 'active' => $active, 'stage' => $stage,
        'backup' => $backup, 'failed_parent' => dirname($wordpress_root), 'old_manifest' => $old_path, 'new_manifest' => $new_path,
        'verify' => $verify, 'guard' => $guard, 'rename' => static fn (string $a, string $b): bool => @rename($a, $b), 'smoke' => $smoke];
    $result = vg_pe008_bootstrap_run($config);
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    exit(vg_pe008_cli_exit_code($result));
}

if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    vg_pe008_production();
}
