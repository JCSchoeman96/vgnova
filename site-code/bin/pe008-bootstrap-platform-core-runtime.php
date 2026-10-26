<?php
declare(strict_types=1);

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
            return ['status' => 'BLOCKED_CRITICAL', 'reason' => 'could_not_preserve_failed_new_tree', 'active' => $active, 'backup' => $backup, 'failed' => $failed];
        }
    } elseif (file_exists($stage) || is_link($stage)) {
        if ($rename($stage, $failed)) {
            $failed_state = 'preserved';
        } else {
            return ['status' => 'BLOCKED_CRITICAL', 'reason' => 'could_not_preserve_failed_stage', 'active' => $active, 'stage' => $stage, 'backup' => $backup, 'failed' => $failed];
        }
    }
    if (! $rename($backup, $active)) {
        return ['status' => 'BLOCKED_CRITICAL', 'reason' => 'could_not_restore_backup', 'active' => $active, 'backup' => $backup, 'failed' => $failed, 'failed_state' => $failed_state];
    }
    try {
        $restored = $verify($active, 'old');
        $restored_smoke = ($restored['exact'] ?? false) === true && (bool) $smoke($config['wordpress_root'], false);
    } catch (Throwable) {
        $restored_smoke = false;
    }
    if (! $restored_smoke) {
        return ['status' => 'BLOCKED_CRITICAL', 'reason' => 'restored_old_tree_failed_verification', 'active' => $active, 'backup' => $backup, 'failed' => $failed, 'failed_state' => $failed_state];
    }
    @rmdir($failed_reservation);
    return ['status' => 'ROLLED_BACK', 'active' => $active, 'failed' => $failed_state === 'preserved' ? $failed : null];
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
    $new = vg_pe008_manifest_authority($new_path, ['source_commit' => 'ab7e7d853772fb920237e47d8fa83c72ed389fba', 'file_count' => 55, 'tree_sha256' => '5816b80ac4355782523aa4baa82bd9e55dd87326f15972f287ff1afa7567482a', 'archive_sha256' => '22d998019e3fe3d93d675250a26eba5be3dbc248badd9be4d7507fbdf9cffdb9', 'manifest_sha256' => 'ceba4caa9394ca3dc6dedca2794199c921fed85118b9d4a4d6606e711a8d12a8']);
    $guard = static function (string $expected) use ($active, $stage, $old, $old_path, $new, $new_path, $wordpress_root): void {
        if ($expected !== 'vgnova-local-10039') { throw new RuntimeException('Wrong target.'); }
        $active_report = vg_product_contribution_qa_platform_core_integrity_report($active, $old_path, $old);
        $stage_report = vg_product_contribution_qa_platform_core_integrity_report($stage, $new_path, $new);
        if (($active_report['exact'] ?? false) !== true || ($stage_report['exact'] ?? false) !== true) { throw new RuntimeException('Trees changed before session validation.'); }
        require_once $wordpress_root . '/wp-load.php';
        require_once $stage . '/includes/Runtime/RuntimeMutationSession.php';
        vg_runtime_mutation_assert_session($expected);
    };
    $verify = static function (string $path, string $which) use ($old, $new, $old_path, $new_path): array {
        $authority = $which === 'old' ? $old : $new;
        $manifest = $which === 'old' ? $old_path : $new_path;
        $report = vg_product_contribution_qa_platform_core_integrity_report($path, $manifest, $authority);
        return $report + vg_pe008_tree_safety($path);
    };
    $smoke = static function (string $root, bool $require_guard = true) use ($target): bool {
        $plugin = $root . '/wp-content/plugins/voelgoed-platform-core';
        $which = @proc_open(['which', 'wp'], [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $which_pipes);
        if (! is_resource($which)) { return false; }
        $wp_cli = trim((string) stream_get_contents($which_pipes[1]));
        stream_get_contents($which_pipes[2]);
        fclose($which_pipes[1]); fclose($which_pipes[2]);
        if (proc_close($which) !== 0 || $wp_cli === '' || ! str_starts_with($wp_cli, '/')) { return false; }
        $guard_check = $require_guard
            ? 'if (!function_exists("vg_runtime_mutation_assert_session")) { exit(22); } vg_runtime_mutation_assert_session("' . $target . '");'
            : '';
        $script = 'if (PHP_VERSION_ID < 80500 || PHP_VERSION_ID >= 80600) { exit(21); } require_once ' . var_export($root . '/wp-admin/includes/plugin.php', true) . '; if (!is_plugin_active("voelgoed-platform-core/voelgoed-platform-core.php")) { exit(24); } ' . $guard_check . ' $p=get_plugin_data(' . var_export($plugin . '/voelgoed-platform-core.php', true) . ', false, false); if (($p["Version"] ?? "") !== "0.1.0") { exit(23); }';
        $command = [PHP_BINARY, '-d', 'mysqli.default_socket=' . (string) ini_get('mysqli.default_socket'), '-d', 'pdo_mysql.default_socket=' . (string) ini_get('pdo_mysql.default_socket'), $wp_cli, '--path=' . $root, 'eval', $script];
        $process = @proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'], 3 => ['file', 'php://fd/3', 'r+']], $pipes, null, null);
        if (! is_resource($process)) { return false; }
        fclose($pipes[0]); $stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        return proc_close($process) === 0 && $stderr === '' && is_string($stdout)
            && preg_match('/\b(?:Warning|Notice|Deprecated|Fatal error|Uncaught)\b/i', $stdout) !== 1;
    };
    $config = ['target' => $target, 'wordpress_root' => $wordpress_root, 'active' => $active, 'stage' => $stage,
        'backup' => $backup, 'failed_parent' => dirname($wordpress_root), 'old_manifest' => $old_path, 'new_manifest' => $new_path,
        'verify' => $verify, 'guard' => $guard, 'rename' => static fn (string $a, string $b): bool => @rename($a, $b), 'smoke' => $smoke];
    $result = vg_pe008_bootstrap_run($config);
    fwrite(($result['status'] === 'SWAPPED' || $result['status'] === 'SKIPPED_ALREADY_EXACT' || $result['status'] === 'ROLLED_BACK') ? STDOUT : STDERR,
        json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    exit(in_array($result['status'], ['SWAPPED', 'SKIPPED_ALREADY_EXACT', 'ROLLED_BACK'], true) ? 0 : 1);
}

if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    vg_pe008_production();
}
