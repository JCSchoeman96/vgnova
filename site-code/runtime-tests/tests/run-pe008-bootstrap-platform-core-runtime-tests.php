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
    'production wiring binds root paths guard loader and inherited lock descriptor' => static function (): void {
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
        pe008_test_assert(str_contains($source, "require_once \$wordpress_root . '/wp-load.php'"), 'production does not load fixed WordPress context');
        pe008_test_assert(! str_contains($source, "require_once dirname(__DIR__) . '/runtime-tests/product-contribution-qa.php'"), 'live QA harness remains loaded');
        pe008_test_assert(str_contains($source, "3 => ['file', 'php://fd/3', 'r+']"), 'fresh smoke does not explicitly map lock FD 3');
        pe008_test_assert(str_contains($source, 'vg_runtime_mutation_assert_session($expected)'), 'production guard does not call the deployed session API');
        pe008_test_assert(strpos($source, '$guard($config[\'target\'])') < strpos($source, 'if (! $rename($active, $backup))'), 'state machine does not invoke its production guard before active rename');
        pe008_test_assert(str_contains($source, 'mysqli.default_socket') && str_contains($source, 'pdo_mysql.default_socket'), 'fresh smoke does not pass parent socket settings');
        pe008_test_assert(str_contains($source, 'wp-admin/includes/plugin.php'), 'smoke loads plugin helpers from wrong location');
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
