<?php
declare(strict_types=1);

$helper_path = dirname(__DIR__, 2) . '/bin/pe008-bootstrap-platform-core-runtime.php';
$GLOBALS['pe008_helper_path'] = $helper_path;
if (! is_file($helper_path)) {
    fwrite(STDERR, "FAIL PE-008 bootstrap helper is missing.\n");
    exit(1);
}
require_once $helper_path;

function pe008_test_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function pe008_test_remove(string $path): void
{
    $stat = @lstat($path);
    if (! is_array($stat)) {
        return;
    }
    if (($stat['mode'] & 0170000) !== 0040000) {
        unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $name) {
        if ($name !== '.' && $name !== '..') {
            pe008_test_remove($path . '/' . $name);
        }
    }
    rmdir($path);
}

function pe008_test_tree(string $path, string $version): void
{
    mkdir($path . '/includes/Runtime', 0700, true);
    file_put_contents($path . '/identity.txt', $version);
    file_put_contents($path . '/includes/Runtime/RuntimeMutationSession.php', 'guard');
}

function pe008_test_run(string $case, callable $callback): void
{
    try {
        $callback();
        fwrite(STDOUT, 'PASS ' . $case . ".\n");
    } catch (Throwable $error) {
        fwrite(STDERR, 'FAIL ' . $case . ': ' . $error->getMessage() . "\n");
        $GLOBALS['pe008_failures']++;
    }
}

function pe008_test_config(string $root): array
{
    $active = $root . '/public/wp-content/plugins/voelgoed-platform-core';
    $stage = $root . '/stage/voelgoed-platform-core';
    $backup = $root . '/public/wp-content/plugins/voelgoed-platform-core.pe008-backup';
    mkdir(dirname($active), 0700, true);
    mkdir(dirname($stage), 0700, true);
    return [
        'target' => 'vgnova-local-10039', 'wordpress_root' => $root . '/public',
        'active' => $active, 'stage' => $stage, 'backup' => $backup,
        'failed_parent' => $root . '/public/wp-content/plugins',
        'old_manifest' => 'old-fixture', 'new_manifest' => 'new-fixture',
        'verify' => static function (string $path, string $authority): array {
            $expected = $authority === 'old' ? 'old' : 'new';
            return ['exact' => is_file($path . '/identity.txt') && file_get_contents($path . '/identity.txt') === $expected] + vg_pe008_tree_safety($path);
        },
        'guard' => static function (string $target): bool { return $target === 'vgnova-local-10039'; },
        'rename' => static fn (string $from, string $to): bool => rename($from, $to),
        'smoke' => static fn (string $root): bool => true,
    ];
}

$GLOBALS['pe008_failures'] = 0;
$cases = [
    'failed backup restore removes empty reservation' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        try {
            $c = pe008_test_config($root);
            pe008_test_tree($c['active'], 'old');
            pe008_test_tree($c['stage'], 'new');
            $n = 0;
            $real = $c['rename'];
            $c['rename'] = static function (string $from, string $to) use (&$n, $real): bool {
                ++$n;
                return in_array($n, [2, 3, 4], true) ? false : $real($from, $to);
            };
            $r = vg_pe008_bootstrap_run($c);
            pe008_test_assert($r['status'] === 'BLOCKED_CRITICAL' && ! file_exists($c['active']) && is_dir($c['backup']), 'backup restore failure did not retain the blocked filesystem state');
            pe008_test_assert((glob($c['failed_parent'] . '/.pe008-failed-*') ?: []) === [], 'empty reservation remained after backup restore failure');
        } finally { pe008_test_remove($root); }
    },
    'restored old smoke failure removes empty reservation' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        try {
            $c = pe008_test_config($root);
            pe008_test_tree($c['active'], 'old');
            pe008_test_tree($c['stage'], 'new');
            $n = 0;
            $real = $c['rename'];
            $c['rename'] = static function (string $from, string $to) use (&$n, $real): bool {
                ++$n;
                return in_array($n, [2, 3], true) ? false : $real($from, $to);
            };
            $c['smoke'] = static fn (): bool => false;
            $r = vg_pe008_bootstrap_run($c);
            pe008_test_assert($r['status'] === 'BLOCKED_CRITICAL' && file_get_contents($c['active'] . '/identity.txt') === 'old', 'old tree was not restored before smoke failure was reported');
            pe008_test_assert((glob($c['failed_parent'] . '/.pe008-failed-*') ?: []) === [], 'empty reservation remained after restored-old smoke failure');
        } finally { pe008_test_remove($root); }
    },
    'failed active-new preservation removes empty reservation' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        try {
            $c = pe008_test_config($root);
            pe008_test_tree($c['active'], 'old');
            pe008_test_tree($c['stage'], 'new');
            $n = 0;
            $real = $c['rename'];
            $c['rename'] = static function (string $from, string $to) use (&$n, $real): bool {
                ++$n;
                return $n === 3 ? false : $real($from, $to);
            };
            $c['smoke'] = static fn (): bool => false;
            $r = vg_pe008_bootstrap_run($c);
            pe008_test_assert($r['status'] === 'BLOCKED_CRITICAL' && file_get_contents($c['active'] . '/identity.txt') === 'new', 'failed new tree was not left active and blocked');
            pe008_test_assert((glob($c['failed_parent'] . '/.pe008-failed-*') ?: []) === [], 'empty reservation remained after active-new preservation failure');
        } finally { pe008_test_remove($root); }
    },
    'guard rejection creates no failed reservation directory' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        try { $c = pe008_test_config($root); pe008_test_tree($c['active'], 'old'); pe008_test_tree($c['stage'], 'new'); $observed = null; $c['guard'] = static function () use (&$observed, $c): bool { $observed = glob($c['failed_parent'] . '/.pe008-failed-*') ?: []; return false; }; $r = vg_pe008_bootstrap_run($c); pe008_test_assert($r['status'] === 'BLOCKED' && $observed === [], 'guard rejection created a failed reservation before validation'); }
        finally { pe008_test_remove($root); }
    },
    'failed new-tree preservation rename failure is critical' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        try { $c = pe008_test_config($root); pe008_test_tree($c['active'], 'old'); pe008_test_tree($c['stage'], 'new'); $n = 0; $real = $c['rename']; $c['rename'] = static function (string $a, string $b) use (&$n, $real): bool { ++$n; return $n === 3 ? false : $real($a, $b); }; $c['smoke'] = static fn (): bool => false; $r = vg_pe008_bootstrap_run($c); pe008_test_assert($r['status'] === 'BLOCKED_CRITICAL' && is_dir($c['active']), 'failed-tree preservation failure was not critical'); }
        finally { pe008_test_remove($root); }
    },
    'failed stage preservation still restores old backup' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        try {
            $c = pe008_test_config($root);
            pe008_test_tree($c['active'], 'old');
            pe008_test_tree($c['stage'], 'new');
            $n = 0;
            $real = $c['rename'];
            $c['rename'] = static function (string $from, string $to) use (&$n, $real): bool {
                ++$n;
                return in_array($n, [2, 3], true) ? false : $real($from, $to);
            };
            $r = vg_pe008_bootstrap_run($c);
            pe008_test_assert($r['status'] === 'ROLLED_BACK', 'old backup was not restored after failed stage preservation');
            pe008_test_assert($n === 4, 'backup restoration was not attempted after failed stage preservation');
            pe008_test_assert(file_get_contents($c['active'] . '/identity.txt') === 'old', 'restored active tree is not old');
            pe008_test_assert(file_get_contents($c['stage'] . '/identity.txt') === 'new', 'unmoved new tree was not retained at stage');
            pe008_test_assert(! file_exists($c['backup']), 'backup remained outside active path after restoration');
            pe008_test_assert(($r['failed'] ?? null) === $c['stage'], 'rollback did not report the retained failed stage path');
        } finally { pe008_test_remove($root); }
    },
    'unverified retained stage restores old backup and clears reservation' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        try {
            $c = pe008_test_config($root);
            pe008_test_tree($c['active'], 'old');
            pe008_test_tree($c['stage'], 'new');
            $n = 0;
            $real_rename = $c['rename'];
            $c['rename'] = static function (string $from, string $to) use (&$n, $real_rename): bool {
                ++$n;
                return in_array($n, [2, 3], true) ? false : $real_rename($from, $to);
            };
            $stage_reads = 0;
            $real_verify = $c['verify'];
            $c['verify'] = static function (string $path, string $authority) use (&$stage_reads, $c, $real_verify): array {
                $report = $real_verify($path, $authority);
                if ($path === $c['stage'] && ++$stage_reads > 1) {
                    $report['exact'] = false;
                }
                return $report;
            };
            $r = vg_pe008_bootstrap_run($c);
            pe008_test_assert($r['status'] === 'BLOCKED_CRITICAL', 'unverified retained stage was reported as a clean rollback');
            pe008_test_assert(file_get_contents($c['active'] . '/identity.txt') === 'old', 'old backup was not restored before blocking');
            pe008_test_assert(! file_exists($c['backup']), 'restored backup remained outside active path');
            pe008_test_assert((glob($c['failed_parent'] . '/.pe008-failed-*') ?: []) === [], 'empty failed-tree reservation was left behind');
        } finally { pe008_test_remove($root); }
    },
    'backup restore rename failure is critical' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        try { $c = pe008_test_config($root); pe008_test_tree($c['active'], 'old'); pe008_test_tree($c['stage'], 'new'); $n = 0; $real = $c['rename']; $c['rename'] = static function (string $a, string $b) use (&$n, $real): bool { ++$n; return $n === 4 ? false : $real($a, $b); }; $c['smoke'] = static fn (): bool => false; $r = vg_pe008_bootstrap_run($c); pe008_test_assert($r['status'] === 'BLOCKED_CRITICAL' && is_dir($c['backup']), 'backup restore failure was not critical'); }
        finally { pe008_test_remove($root); }
    },
    'restored old verification failure is critical' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        try { $c = pe008_test_config($root); pe008_test_tree($c['active'], 'old'); pe008_test_tree($c['stage'], 'new'); $c['smoke'] = static fn (): bool => false; $r = vg_pe008_bootstrap_run($c); pe008_test_assert($r['status'] === 'BLOCKED_CRITICAL' && is_dir($c['active']), 'restored-old smoke failure was not critical'); }
        finally { pe008_test_remove($root); }
    },
    'post-swap exact-new verification failure rolls back' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        try { $c = pe008_test_config($root); pe008_test_tree($c['active'], 'old'); pe008_test_tree($c['stage'], 'new'); $verify = $c['verify']; $c['verify'] = static function (string $path, string $authority) use ($c, $verify): array { $report = $verify($path, $authority); if ($path === $c['active'] && file_get_contents($path . '/identity.txt') === 'new') { $report['exact'] = false; } return $report; }; $r = vg_pe008_bootstrap_run($c); pe008_test_assert($r['status'] === 'ROLLED_BACK' && file_get_contents($c['active'] . '/identity.txt') === 'old', 'post-swap exact-new failure did not roll back'); }
        finally { pe008_test_remove($root); }
    },
    'root-symlinked stage blocks before rename' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        try { $c = pe008_test_config($root); pe008_test_tree($c['active'], 'old'); $real_stage = $root . '/stage-real'; pe008_test_tree($real_stage, 'new'); symlink($real_stage, $c['stage']); $r = vg_pe008_bootstrap_run($c); pe008_test_assert($r['status'] === 'BLOCKED' && is_dir($c['active']), 'root symlinked stage proceeded'); }
        finally { pe008_test_remove($root); }
    },
    'second rename failure preserves staged new tree at failed path' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        try { $c = pe008_test_config($root); pe008_test_tree($c['active'], 'old'); pe008_test_tree($c['stage'], 'new'); $n = 0; $real = $c['rename']; $c['rename'] = static function (string $a, string $b) use (&$n, $real): bool { ++$n; return $n === 2 ? false : $real($a, $b); }; $r = vg_pe008_bootstrap_run($c); pe008_test_assert($r['status'] === 'ROLLED_BACK' && is_string($r['failed']) && file_get_contents($r['failed'] . '/identity.txt') === 'new', 'failed new tree was not preserved'); }
        finally { pe008_test_remove($root); }
    },
    'retained backup is checked against old authority before success' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        try { $c = pe008_test_config($root); pe008_test_tree($c['active'], 'old'); pe008_test_tree($c['stage'], 'new'); $verify = $c['verify']; $c['verify'] = static function (string $path, string $authority) use ($c, $verify): array { $result = $verify($path, $authority); if ($path === $c['backup'] && $authority === 'old') { $result['exact'] = false; } return $result; }; $r = vg_pe008_bootstrap_run($c); pe008_test_assert($r['status'] === 'ROLLED_BACK' && file_get_contents($c['active'] . '/identity.txt') === 'old', 'bad backup passed success verification'); }
        finally { pe008_test_remove($root); }
    },
    'production wiring keeps WordPress out of the filesystem-mutating parent' => static function (): void {
        $source = file_get_contents($GLOBALS['pe008_helper_path']);
        pe008_test_assert(is_string($source), 'helper source unreadable');
        pe008_test_assert(function_exists('vg_pe008_parse_options'), 'fixed-root CLI parser is missing');
        $options = vg_pe008_parse_options(['--wp-root=/tmp/fake/public', '--stage=/tmp/fake/app/stage', '--backup=/tmp/fake/app/backup']);
        pe008_test_assert($options === null, 'noncanonical root was accepted');
        $bad_stage = vg_pe008_parse_options(['--wp-root=/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public', '--stage=/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/plugins/stage', '--backup=/home/jcschoeman96/Local Sites/voelgoed-novamira/app/backup']);
        pe008_test_assert($bad_stage === null, 'plugin-local stage was accepted');
        $bad_backup = vg_pe008_parse_options(['--wp-root=/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public', '--stage=/home/jcschoeman96/Local Sites/voelgoed-novamira/app/voelgoed-platform-core.pe008-stage-a1', '--backup=/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/plugins/voelgoed-platform-core.pe008-backup-b2']);
        pe008_test_assert($bad_backup === null, 'plugin-local backup was accepted');
        $valid = vg_pe008_parse_options(['--wp-root=/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public', '--stage=/home/jcschoeman96/Local Sites/voelgoed-novamira/app/voelgoed-platform-core.pe008-stage-a1', '--backup=/home/jcschoeman96/Local Sites/voelgoed-novamira/app/voelgoed-platform-core.pe008-backup-b2']);
        pe008_test_assert(is_array($valid), 'canonical direct-child paths were rejected');
        pe008_test_assert(! str_contains($source, 'wp-load.php') && ! str_contains($source, 'wp-settings.php'), 'filesystem-mutating parent still contains a direct WordPress bootstrap');
        pe008_test_assert(! str_contains($source, "require_once dirname(__DIR__) . '/runtime-tests/product-contribution-qa.php'"), 'live QA harness remains loaded');
        pe008_test_assert(str_contains($source, "3 => ['file', 'php://fd/3', 'r+']"), 'fresh smoke does not explicitly map lock FD 3');
        pe008_test_assert(str_contains($source, 'vg_pe008_guard_command') && str_contains($source, 'vg_pe008_smoke_command'), 'guard and smoke do not use separate child commands');
        pe008_test_assert(! preg_match('/(?:^|\s)(?:eval|eval-file)(?:\s|\'|"|$)/', $source), 'deployment parent still invokes WP-CLI eval before WordPress bootstrap');
        pe008_test_assert(strpos($source, '$guard($config[\'target\'])') < strpos($source, 'if (! $rename($active, $backup))'), 'state machine does not invoke its production guard before active rename');
        pe008_test_assert(str_contains($source, 'mysqli.default_socket') && str_contains($source, 'pdo_mysql.default_socket'), 'fresh smoke does not pass parent socket settings');
        pe008_test_assert(str_contains($source, 'descriptorSafeCommand') && str_contains($source, '[3]'), 'guard child does not close internal descriptors while passing FD3');
        pe008_test_assert(str_contains($source, 'proc_close('), 'guard child termination is not awaited before returning');
    },
    'WP-CLI command builder carries default and explicit DB transport without credentials' => static function (): void {
        pe008_test_assert(function_exists('vg_pe008_wp_cli_command') && function_exists('vg_pe008_guard_command') && function_exists('vg_pe008_smoke_command'), 'isolated WP-CLI command builders are missing');
        $tool = dirname(__DIR__, 2) . '/runtime-tools/pe008-wp-cli-session-guard.php';
        $smokeTool = dirname(__DIR__, 2) . '/runtime-tools/pe008-wp-cli-runtime-smoke.php';
        $default = vg_pe008_guard_command('/usr/bin/wp', '/tmp/wordpress', $tool, '/tmp/stage', 'vgnova-local-10039', null);
        pe008_test_assert($default[0] === PHP_BINARY && in_array('/usr/bin/wp', $default, true), 'default transport does not use the current PHP binary and WP-CLI');
        pe008_test_assert(in_array('--path=/tmp/wordpress', $default, true), 'default transport lost the fixed WordPress root');
        pe008_test_assert(in_array('--skip-plugins', $default, true) && in_array('--skip-themes', $default, true), 'guard command does not suppress active plugins and theme');
        pe008_test_assert(in_array('--skip-packages', $default, true), 'guard command does not suppress WP-CLI packages');
        pe008_test_assert(! in_array('--require=' . $tool, $default, true), 'guard command uses normal WP-CLI --require loading');
        pe008_test_assert(in_array('pe008', $default, true) && in_array('session-guard', $default, true), 'guard command is not registered for post-bootstrap execution');
        pe008_test_assert(in_array('vgnova-local-10039', $default, true) && in_array('/tmp/stage/includes/Runtime/RuntimeMutationSession.php', $default, true), 'guard command lost target or staged session guard path');
        pe008_test_assert(! in_array('eval', $default, true) && ! in_array('eval-file', $default, true), 'guard command uses an early WP-CLI eval command');
        pe008_test_assert(count(array_filter($default, static fn (string $arg): bool => str_contains($arg, 'MYSQL_PWD') || str_contains($arg, 'DB_PASSWORD'))) === 0, 'database credentials entered command arguments');
        $override = vg_pe008_guard_command('/usr/bin/wp', '/tmp/wordpress', $tool, '/tmp/stage', 'vgnova-local-10039', '/tmp/local-mysql.sock');
        pe008_test_assert(in_array('mysqli.default_socket=/tmp/local-mysql.sock', $override, true), 'mysqli socket override was dropped');
        pe008_test_assert(in_array('pdo_mysql.default_socket=/tmp/local-mysql.sock', $override, true), 'PDO socket override was dropped');
        pe008_test_assert(count(array_filter($override, static fn (string $arg): bool => str_contains($arg, 'MYSQL_PWD') || str_contains($arg, 'DB_PASSWORD'))) === 0, 'database credentials entered override arguments');

        $smoke = vg_pe008_smoke_command('/usr/bin/wp', '/tmp/wordpress', $smokeTool, '/tmp/wordpress/wp-content/plugins/voelgoed-platform-core/voelgoed-platform-core.php', 'vgnova-local-10039', true, null);
        pe008_test_assert(! in_array('--require=' . $smokeTool, $smoke, true) && in_array('runtime-smoke', $smoke, true), 'smoke uses normal WP-CLI --require loading or lost its separate command');
        pe008_test_assert(! in_array('--skip-plugins', $smoke, true) && ! in_array('--skip-themes', $smoke, true), 'runtime smoke does not load the active plugin/theme stack');
        pe008_test_assert(! in_array('eval', $smoke, true) && ! in_array('eval-file', $smoke, true), 'smoke uses an early WP-CLI eval command');
    },
    'isolated WP-CLI environment preserves optional socket transport and excludes secrets' => static function (): void {
        $base = [
            'PATH' => '/usr/bin',
            'HOME' => '/tmp/pe008-home',
            'VGNOVA_MUTATION_SESSION_ID' => str_repeat('a', 32),
            'VGNOVA_MUTATION_TARGET' => 'vgnova-local-10039',
            'VGNOVA_MUTATION_DB_LOCK_A' => 'vgnova.mutation.a.' . str_repeat('b', 40),
            'VGNOVA_MUTATION_DB_CONNECTION_ID_A' => '42',
            'VGNOVA_MUTATION_DB_LOCK_B' => 'vgnova.mutation.b.' . str_repeat('b', 40),
            'VGNOVA_MUTATION_DB_CONNECTION_ID_B' => '43',
            'VGNOVA_MUTATION_LOCK_FILE' => '/tmp/pe008-home/.local/state/vgnova/runtime-mutation/vgnova-local-10039.lock',
            'DB_PASSWORD' => 'must-not-propagate',
            'MYSQL_PWD' => 'must-not-propagate',
        ];
        $guardLibrary = dirname(__DIR__, 2) . '/runtime-tools/pe008-wp-cli-session-guard.php';
        $normal = vg_pe008_subprocess_environment($base, $guardLibrary);
        pe008_test_assert(! array_key_exists('VGNOVA_DB_SOCKET', $normal), 'normal DB_HOST environment retained a stale socket override');
        pe008_test_assert(($normal['WP_CLI_CONFIG_PATH'] ?? null) === '/dev/null', 'WP-CLI user configuration was not disabled for child execution');
        pe008_test_assert(($normal['WP_CLI_SYSTEM_SETTINGS_PATH'] ?? null) === '/dev/null', 'WP-CLI system configuration was not disabled for child execution');
        pe008_test_assert(($normal['WP_CLI_REQUIRE'] ?? null) === $guardLibrary, 'WP-CLI command library was not loaded before WordPress bootstrap');
        pe008_test_assert(! isset($normal['WP_CLI_EARLY_REQUIRE']), 'command registration was sent through WP_CLI_EARLY_REQUIRE before WP_CLI class initialization');
        pe008_test_assert(($normal['VGNOVA_MUTATION_SESSION_ID'] ?? null) === str_repeat('a', 32), 'normal DB transport lost PE-007 session proof');
        pe008_test_assert(! isset($normal['DB_PASSWORD']) && ! isset($normal['MYSQL_PWD']), 'database credential entered the WP-CLI child environment');
        $base['VGNOVA_DB_SOCKET'] = '/tmp/local-mysql.sock';
        $override = vg_pe008_subprocess_environment($base, $guardLibrary);
        pe008_test_assert(($override['VGNOVA_DB_SOCKET'] ?? null) === '/tmp/local-mysql.sock', 'explicit Local DB socket override was lost before WP-CLI process startup');
        pe008_test_assert(! isset($override['DB_PASSWORD']) && ! isset($override['MYSQL_PWD']), 'socket override caused a database credential to enter the WP-CLI child environment');
    },
    'WP-CLI command library runs session guard after load and suppresses pre-rename lifecycle writers' => static function (): void {
        $source = file_get_contents(dirname(__DIR__, 2) . '/runtime-tools/pe008-wp-cli-session-guard.php');
        pe008_test_assert(is_string($source), 'WP-CLI command library is unreadable');
        pe008_test_assert(str_contains($source, "WP_CLI::add_hook('before_wp_load'") && str_contains($source, "'when' => 'after_wp_load'"), 'session guard is not registered to execute after controlled WordPress bootstrap');
        pe008_test_assert(str_contains($source, 'DISABLE_WP_CRON') && str_contains($source, "define('WPMU_PLUGIN_DIR', '/dev/null')"), 'pre-rename bootstrap does not disable cron and MU-plugin loading');
        pe008_test_assert(str_contains($source, 'RuntimeMutationSession.php') && str_contains($source, 'vg_runtime_mutation_assert_session'), 'guard command does not explicitly load and invoke the staged session guard');
        $smoke_source = file_get_contents(dirname(__DIR__, 2) . '/runtime-tools/pe008-wp-cli-runtime-smoke.php');
        pe008_test_assert(is_string($smoke_source) && str_contains($smoke_source, "WP_CLI::add_command('pe008 runtime-smoke'") && str_contains($smoke_source, "'when' => 'after_wp_load'"), 'post-swap smoke is not a separate post-bootstrap WP-CLI command');
    },
    'guard subprocess inherits only FD3, receives session transport, and exits before parent continues' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-subprocess-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        $lockPath = $root . '/vgnova-local-10039.lock'; file_put_contents($lockPath, 'lock'); chmod($lockPath, 0600);
        $lock = fopen($lockPath, 'r+'); pe008_test_assert(is_resource($lock) && flock($lock, LOCK_EX | LOCK_NB), 'test parent could not acquire FD3 lock fixture');
        $sentinelPaths = [];
        $sentinels = [];
        for ($fd = 4; $fd <= 8; ++$fd) {
            $path = $root . '/internal-' . $fd . '.sentinel'; file_put_contents($path, 'internal');
            $sentinelPaths[] = $path;
            $sentinels[] = fopen($path, 'r');
        }
        $record = $root . '/child.json'; $childDone = $root . '/child.done'; $parentDone = $root . '/parent.done';
        $childScript = $root . '/guard-child.php';
        $workerScript = $root . '/deployment-parent.php';
        file_put_contents($childScript, <<<'PHP'
<?php
declare(strict_types=1);
$args = array_slice($argv, 1);
        $commandIndex = array_search('pe008', $args, true);
        $command = is_int($commandIndex) ? array_slice($args, $commandIndex, 4) : [];
$root = (string) getenv('TMPDIR');
$guardPath = $root . '/stage/includes/Runtime/RuntimeMutationSession.php';
$validCommand = in_array('--skip-plugins', $args, true)
    && in_array('--skip-themes', $args, true)
    && in_array('--skip-packages', $args, true)
            && in_array('session-guard', $command, true)
            && in_array('vgnova-local-10039', $command, true)
            && in_array($guardPath, $command, true)
            && str_ends_with((string) getenv('WP_CLI_REQUIRE'), '/pe008-wp-cli-session-guard.php');
$fd = @fopen('php://fd/3', 'r+');
$fdStat = is_resource($fd) ? @fstat($fd) : false;
$lockStat = @stat($root . '/vgnova-local-10039.lock');
$lockHeld = is_resource($fd) && @flock($fd, LOCK_EX | LOCK_NB);
$leaked = [];
for ($sentinelFd = 4; $sentinelFd <= 8; ++$sentinelFd) {
    $sentinelPath = $root . '/internal-' . $sentinelFd . '.sentinel';
    $sentinel = @stat($sentinelPath);
    $observed = @stat('/proc/self/fd/' . $sentinelFd);
    if (is_array($sentinel) && is_array($observed)
        && ($sentinel['dev'] ?? null) === ($observed['dev'] ?? null)
        && ($sentinel['ino'] ?? null) === ($observed['ino'] ?? null)) {
        $leaked[] = $sentinelFd;
    }
}
file_put_contents($root . '/child.json', json_encode([
    'valid_command' => $validCommand,
    'pid' => getmypid(),
    'same_fd3_inode' => is_array($fdStat) && is_array($lockStat) && $fdStat['dev'] === $lockStat['dev'] && $fdStat['ino'] === $lockStat['ino'],
    'lock_held' => $lockHeld,
    'leaked_internal_descriptors' => $leaked,
    'session_id' => getenv('VGNOVA_MUTATION_SESSION_ID') ?: null,
    'target' => getenv('VGNOVA_MUTATION_TARGET') ?: null,
    'db_lock_a' => getenv('VGNOVA_MUTATION_DB_LOCK_A') ?: null,
    'connection_a' => getenv('VGNOVA_MUTATION_DB_CONNECTION_ID_A') ?: null,
    'db_lock_b' => getenv('VGNOVA_MUTATION_DB_LOCK_B') ?: null,
    'connection_b' => getenv('VGNOVA_MUTATION_DB_CONNECTION_ID_B') ?: null,
    'lock_file' => getenv('VGNOVA_MUTATION_LOCK_FILE') ?: null,
    'socket' => getenv('VGNOVA_DB_SOCKET') ?: null,
    'wp_cli_config_path' => getenv('WP_CLI_CONFIG_PATH') ?: null,
    'wp_cli_require' => getenv('WP_CLI_REQUIRE') ?: null,
    'wp_cli_system_settings_path' => getenv('WP_CLI_SYSTEM_SETTINGS_PATH') ?: null,
    'working_directory' => getcwd(),
    'db_password' => getenv('DB_PASSWORD') ?: null,
    'mysql_pwd' => getenv('MYSQL_PWD') ?: null,
], JSON_THROW_ON_ERROR));
usleep(100000);
file_put_contents($root . '/child.done', 'child exited');
fwrite(STDOUT, "guard-child-ok\n");
PHP);
        file_put_contents($workerScript, <<<'PHP'
<?php
declare(strict_types=1);
require $argv[1];
$root = $argv[3];
$active = $root . '/active';
$stage = $root . '/stage';
$backup = $root . '/backup';
$failedParent = $root . '/failed';
mkdir($active, 0700); file_put_contents($active . '/identity.txt', 'old');
mkdir($stage . '/includes/Runtime', 0700, true); file_put_contents($stage . '/identity.txt', 'new');
file_put_contents($stage . '/includes/Runtime/RuntimeMutationSession.php', '<?php');
mkdir($failedParent, 0700);
$environment = [
    'PATH' => (string) (getenv('PATH') ?: '/usr/bin'),
    'TMPDIR' => $root,
    'VGNOVA_MUTATION_SESSION_ID' => str_repeat('a', 32),
    'VGNOVA_MUTATION_TARGET' => 'vgnova-local-10039',
    'VGNOVA_MUTATION_DB_LOCK_A' => 'vgnova.mutation.a.' . str_repeat('b', 40),
    'VGNOVA_MUTATION_DB_CONNECTION_ID_A' => '42',
    'VGNOVA_MUTATION_DB_LOCK_B' => 'vgnova.mutation.b.' . str_repeat('b', 40),
    'VGNOVA_MUTATION_DB_CONNECTION_ID_B' => '43',
    'VGNOVA_MUTATION_LOCK_FILE' => $root . '/vgnova-local-10039.lock',
    'VGNOVA_DB_SOCKET' => '/tmp/local-mysql.sock',
    'DB_PASSWORD' => 'must-not-leak',
    'MYSQL_PWD' => 'must-not-leak',
];
$guard = static function (string $target) use ($argv, $root, $stage, $environment): bool {
    $tool = dirname(dirname($argv[1])) . '/runtime-tools/pe008-wp-cli-session-guard.php';
    $command = vg_pe008_guard_command($argv[2], $root . '/public', $tool, $stage, $target, '/tmp/local-mysql.sock');
    $result = vg_pe008_run_subprocess($command, $environment, $tool);
    $child = json_decode((string) file_get_contents($root . '/child.json'), true);
    return vg_pe008_subprocess_output_is_clean($result)
        && ($result['stdout'] ?? '') === "guard-child-ok\n"
        && is_array($child) && is_int($child['pid'] ?? null)
        && !@posix_kill($child['pid'], 0);
};
$verify = static function (string $path, string $authority): array {
    $expected = $authority === 'old' ? 'old' : 'new';
    return ['exact' => is_file($path . '/identity.txt') && file_get_contents($path . '/identity.txt') === $expected,
        'symlinks' => [], 'unsupported' => [], 'world_writable' => []];
};
$renames = 0;
$rename = static function (string $from, string $to) use (&$renames, $root): bool {
    ++$renames;
    if ($renames === 1 && !is_file($root . '/child.done')) { throw new RuntimeException('first rename started before guard child exit'); }
    return rename($from, $to);
};
$result = vg_pe008_bootstrap_run([
    'target' => 'vgnova-local-10039', 'wordpress_root' => $root . '/public', 'active' => $active,
    'stage' => $stage, 'backup' => $backup, 'failed_parent' => $failedParent,
    'old_manifest' => 'fixture-old', 'new_manifest' => 'fixture-new', 'verify' => $verify,
    'guard' => $guard, 'rename' => $rename, 'smoke' => static fn (): bool => true,
]);
if (($result['status'] ?? null) !== 'SWAPPED' || $renames !== 2) { fwrite(STDERR, 'state machine did not complete fixture swap'); exit(31); }
if (!is_file($argv[3] . '/child.done')) { fwrite(STDERR, 'parent continued before child termination'); exit(32); }
file_put_contents($argv[3] . '/parent.done', 'parent continued');
PHP);
        try {
            $command = [PHP_BINARY, $workerScript, $GLOBALS['pe008_helper_path'], $childScript, $root];
            $descriptors = [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'], 3 => $lock];
            foreach ($sentinels as $index => $handle) { $descriptors[4 + $index] = $handle; }
            $process = proc_open($command, $descriptors, $pipes, null, ['PATH' => (string) (getenv('PATH') ?: '/usr/bin')]);
            pe008_test_assert(is_resource($process), 'temporary deployment-parent subprocess did not start');
            foreach ($sentinels as $handle) { fclose($handle); }
            fclose($lock);
            $stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
            $exit = proc_close($process);
            pe008_test_assert($exit === 0, 'temporary deployment parent failed: ' . trim((string) $stderr));
            pe008_test_assert(is_file($childDone) && is_file($parentDone), 'child and parent completion markers are missing');
            $observed = json_decode((string) file_get_contents($record), true, 512, JSON_THROW_ON_ERROR);
            pe008_test_assert(($observed['same_fd3_inode'] ?? false) === true && ($observed['lock_held'] ?? false) === true, 'guard child did not inherit the locked FD3');
            pe008_test_assert(($observed['valid_command'] ?? false) === true, 'guard child did not receive the exact guarded WP-CLI arguments');
            pe008_test_assert(($observed['leaked_internal_descriptors'] ?? null) === [], 'coordinator control descriptor leaked to guard child');
            pe008_test_assert(($observed['session_id'] ?? null) === str_repeat('a', 32), 'session environment did not reach the child');
            pe008_test_assert(($observed['target'] ?? null) === 'vgnova-local-10039'
                && ($observed['db_lock_a'] ?? null) === 'vgnova.mutation.a.' . str_repeat('b', 40)
                && ($observed['connection_a'] ?? null) === '42'
                && ($observed['db_lock_b'] ?? null) === 'vgnova.mutation.b.' . str_repeat('b', 40)
                && ($observed['connection_b'] ?? null) === '43'
                && ($observed['lock_file'] ?? null) === $lockPath,
                'full PE-007 session proof did not reach the guard child');
            pe008_test_assert(($observed['socket'] ?? null) === '/tmp/local-mysql.sock', 'DB socket override did not reach the child');
            pe008_test_assert(($observed['wp_cli_config_path'] ?? null) === '/dev/null', 'unrelated WP-CLI user configuration was not isolated from the child');
            $expectedTool = dirname(__DIR__, 2) . '/runtime-tools/pe008-wp-cli-session-guard.php';
            pe008_test_assert(($observed['wp_cli_require'] ?? null) === $expectedTool
                && ($observed['wp_cli_system_settings_path'] ?? null) === '/dev/null',
                'WP-CLI early command library or system configuration isolation was lost');
            pe008_test_assert(($observed['working_directory'] ?? null) === '/', 'WP-CLI child inherited repository-local configuration discovery through its working directory');
            pe008_test_assert(($observed['db_password'] ?? null) === null && ($observed['mysql_pwd'] ?? null) === null, 'database credentials reached the guard child');
            pe008_test_assert(strpos(file_get_contents($root . '/child.done'), 'child exited') === 0, 'child completion marker is invalid');
            pe008_test_assert(strpos(file_get_contents($parentDone), 'parent continued') === 0, 'parent completion marker is invalid');
        } finally { pe008_test_remove($root); }
    },
    'production exit mapping is exercised in subprocesses for every terminal status' => static function (): void {
        pe008_test_assert(function_exists('vg_pe008_cli_exit_code'), 'production CLI exit mapper is missing');
        $expected = ['SWAPPED' => 0, 'SKIPPED_ALREADY_EXACT' => 0, 'ROLLED_BACK' => 3, 'BLOCKED' => 1, 'BLOCKED_CRITICAL' => 2];
        foreach ($expected as $status => $expectedExit) {
            $result = ['status' => $status];
            $inline = 'require ' . var_export($GLOBALS['pe008_helper_path'], true) . '; $result=' . var_export($result, true)
                . '; fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR) . "\\n"); exit(vg_pe008_cli_exit_code($result));';
            $process = proc_open([PHP_BINARY, '-r', $inline], [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            pe008_test_assert(is_resource($process), 'exit-mapping child did not start for ' . $status);
            $stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
            $exit = proc_close($process);
            $decoded = json_decode(trim((string) $stdout), true, 512, JSON_THROW_ON_ERROR);
            pe008_test_assert(($decoded['status'] ?? null) === $status, 'subprocess lost terminal JSON status ' . $status);
            pe008_test_assert($exit === $expectedExit && $stderr === '', 'wrong production process exit for ' . $status . ': ' . $exit);
        }
    },
    'direct run rejects missing mutation session before rename' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        try { $c = pe008_test_config($root); pe008_test_tree($c['active'], 'old'); pe008_test_tree($c['stage'], 'new'); $c['guard'] = static fn (): bool => false;
            $r = vg_pe008_bootstrap_run($c); pe008_test_assert($r['status'] === 'BLOCKED', 'uncoordinated run was not blocked'); pe008_test_assert(is_dir($c['active']), 'active tree moved');
        } finally { pe008_test_remove($root); }
    },
    'old active and exact new stage swap successfully and retain backup' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        try { $c = pe008_test_config($root); pe008_test_tree($c['active'], 'old'); pe008_test_tree($c['stage'], 'new'); $r = vg_pe008_bootstrap_run($c);
            pe008_test_assert($r['status'] === 'SWAPPED', 'swap failed'); pe008_test_assert(is_dir($c['backup']), 'backup missing'); pe008_test_assert(file_get_contents($c['active'] . '/identity.txt') === 'new', 'new tree absent');
        } finally { pe008_test_remove($root); }
    },
    'successful deployment verifies retained backup against old authority' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        try { $c = pe008_test_config($root); pe008_test_tree($c['active'], 'old'); pe008_test_tree($c['stage'], 'new'); $calls = []; $verify = $c['verify']; $c['verify'] = static function (string $path, string $authority) use (&$calls, $c, $verify): array { if ($path === $c['backup']) { $calls[] = $authority; } return $verify($path, $authority); }; $r = vg_pe008_bootstrap_run($c); pe008_test_assert($r['status'] === 'SWAPPED' && $calls === ['old'], 'successful deployment did not verify the old backup'); }
        finally { pe008_test_remove($root); }
    },
    'already exact new active tree is skipped' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        try { $c = pe008_test_config($root); pe008_test_tree($c['active'], 'new'); pe008_test_tree($c['stage'], 'new'); $r = vg_pe008_bootstrap_run($c); pe008_test_assert($r['status'] === 'SKIPPED_ALREADY_EXACT', 'already exact tree was not skipped'); }
        finally { pe008_test_remove($root); }
    },
    'unexpected active tree blocks' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        try { $c = pe008_test_config($root); pe008_test_tree($c['active'], 'odd'); pe008_test_tree($c['stage'], 'new'); $r = vg_pe008_bootstrap_run($c); pe008_test_assert($r['status'] === 'BLOCKED', 'unexpected active proceeded'); }
        finally { pe008_test_remove($root); }
    },
    'mismatched stage blocks before rename' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        try { $c = pe008_test_config($root); pe008_test_tree($c['active'], 'old'); pe008_test_tree($c['stage'], 'bad'); $r = vg_pe008_bootstrap_run($c); pe008_test_assert($r['status'] === 'BLOCKED' && is_dir($c['active']), 'bad stage moved'); }
        finally { pe008_test_remove($root); }
    },
    'symlink stage blocks' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        try { $c = pe008_test_config($root); pe008_test_tree($c['active'], 'old'); pe008_test_tree($c['stage'], 'new'); symlink('identity.txt', $c['stage'] . '/link'); $r = vg_pe008_bootstrap_run($c); pe008_test_assert($r['status'] === 'BLOCKED', 'symlink stage proceeded'); }
        finally { pe008_test_remove($root); }
    },
    'unsupported stage object blocks' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        try { $c = pe008_test_config($root); pe008_test_tree($c['active'], 'old'); pe008_test_tree($c['stage'], 'new'); $fifo = $c['stage'] . '/fifo'; if (! posix_mkfifo($fifo, 0600)) { throw new RuntimeException('could not create FIFO'); } $r = vg_pe008_bootstrap_run($c); pe008_test_assert($r['status'] === 'BLOCKED', 'unsupported object stage proceeded'); }
        finally { pe008_test_remove($root); }
    },
    'world writable stage blocks' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        try { $c = pe008_test_config($root); pe008_test_tree($c['active'], 'old'); pe008_test_tree($c['stage'], 'new'); chmod($c['stage'] . '/identity.txt', 0666); $r = vg_pe008_bootstrap_run($c); pe008_test_assert($r['status'] === 'BLOCKED', 'world writable stage proceeded'); }
        finally { pe008_test_remove($root); }
    },
    'existing backup blocks' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        try { $c = pe008_test_config($root); pe008_test_tree($c['active'], 'old'); pe008_test_tree($c['stage'], 'new'); mkdir($c['backup']); $r = vg_pe008_bootstrap_run($c); pe008_test_assert($r['status'] === 'BLOCKED', 'existing backup was overwritten'); }
        finally { pe008_test_remove($root); }
    },
    'first rename failure leaves old tree active' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        try { $c = pe008_test_config($root); pe008_test_tree($c['active'], 'old'); pe008_test_tree($c['stage'], 'new'); $c['rename'] = static fn (): bool => false; $r = vg_pe008_bootstrap_run($c); pe008_test_assert($r['status'] === 'BLOCKED' && file_get_contents($c['active'] . '/identity.txt') === 'old', 'first rename failure changed active'); }
        finally { pe008_test_remove($root); }
    },
    'second rename failure restores old tree' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        try { $c = pe008_test_config($root); pe008_test_tree($c['active'], 'old'); pe008_test_tree($c['stage'], 'new'); $n = 0; $real = $c['rename']; $c['rename'] = static function (string $a, string $b) use (&$n, $real): bool { ++$n; return $n === 2 ? false : $real($a, $b); }; $r = vg_pe008_bootstrap_run($c); pe008_test_assert($r['status'] === 'ROLLED_BACK' && file_get_contents($c['active'] . '/identity.txt') === 'old', 'old tree not restored'); }
        finally { pe008_test_remove($root); }
    },
    'post-swap verification failure restores and verifies old tree' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        try { $c = pe008_test_config($root); pe008_test_tree($c['active'], 'old'); pe008_test_tree($c['stage'], 'new'); $guards = []; $c['smoke'] = static function (string $wordpress_root, bool $require_guard) use (&$guards): bool { $guards[] = $require_guard; return ! $require_guard; }; $r = vg_pe008_bootstrap_run($c); pe008_test_assert($r['status'] === 'ROLLED_BACK' && file_get_contents($c['active'] . '/identity.txt') === 'old' && $guards === [true, false], 'verification failure did not restore old or use the restored smoke mode'); }
        finally { pe008_test_remove($root); }
    },
    'activation performs no individual file edits' => static function (): void {
        $root = sys_get_temp_dir() . '/pe008-' . bin2hex(random_bytes(6)); mkdir($root, 0700);
        try { $c = pe008_test_config($root); pe008_test_tree($c['active'], 'old'); pe008_test_tree($c['stage'], 'new'); $renames = 0; $c['rename'] = static function (string $a, string $b) use (&$renames): bool { ++$renames; return rename($a, $b); }; $r = vg_pe008_bootstrap_run($c); pe008_test_assert($r['status'] === 'SWAPPED' && $renames === 2, 'activation was not two directory renames'); }
        finally { pe008_test_remove($root); }
    },
];
foreach ($cases as $name => $case) { pe008_test_run($name, $case); }
if ($GLOBALS['pe008_failures'] > 0) { exit(1); }
fwrite(STDOUT, 'All PE-008 bootstrap cases passed. (' . count($cases) . " cases)\n");
