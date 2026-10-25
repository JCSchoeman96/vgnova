<?php
declare(strict_types=1);

require_once __DIR__ . '/../../runtime-tools/runtime-mutation-core.php';
require_once __DIR__ . '/../../runtime-tools/runtime-mutation-database.php';

function vg_runtime_mutation_core_test(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function vg_runtime_mutation_core_throws(int $exitCode, callable $callback, string $message): void
{
    try {
        $callback();
    } catch (VgRuntimeMutationException $exception) {
        vg_runtime_mutation_core_test($exception->exitCode === $exitCode, $message . ': wrong exit code');
        return;
    }
    throw new RuntimeException($message . ': expected an exception');
}

function vg_runtime_mutation_core_fixture_target(array $overrides = []): array
{
    return array_replace([
        'id' => 'vgnova-local-10039',
        'home' => 'http://localhost:10039',
        'site' => 'http://localhost:10039',
        'database_name' => 'local',
        'database_host' => 'localhost',
        'platform_core_entrypoint' => 'wp-content/plugins/voelgoed-platform-core/voelgoed-platform-core.php',
    ], $overrides);
}

function vg_runtime_mutation_core_with_temp_dir(callable $callback): void
{
    $directory = sys_get_temp_dir() . '/vg-pe007-core-' . bin2hex(random_bytes(6));
    if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
        throw new RuntimeException('Could not create the test fixture directory.');
    }
    try {
        $callback($directory);
    } finally {
        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        ) as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($directory);
    }
}

$tests = 0;
$test = static function (string $name, callable $callback) use (&$tests): void {
    $callback();
    $tests++;
    echo "PASS {$name}\n";
};

$test('target registry parses and validates schema 1', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $path = $directory . '/targets.json';
        file_put_contents($path, json_encode([
            'schema_version' => 1,
            'targets' => [vg_runtime_mutation_core_fixture_target()],
        ], JSON_THROW_ON_ERROR));
        $registry = VgRuntimeMutationCore::loadTargetRegistry($path);
        vg_runtime_mutation_core_test($registry['schema_version'] === 1, 'Schema version was not retained.');
        vg_runtime_mutation_core_test(count($registry['targets']) === 1, 'Target was not loaded.');
    });
});

$test('unknown target is rejected', static function (): void {
    vg_runtime_mutation_core_throws(71, static fn () => VgRuntimeMutationCore::resolveTarget(
        ['schema_version' => 1, 'targets' => [vg_runtime_mutation_core_fixture_target()]],
        'missing-target'
    ), 'Unknown target');
});

$test('stable lock name uses target identity', static function (): void {
    $target = vg_runtime_mutation_core_fixture_target();
    $expected = 'vgnova.mutation.' . substr(hash('sha256', implode("\n", [
        $target['id'], $target['database_name'], $target['home'], $target['site'],
    ])), 0, 40);
    vg_runtime_mutation_core_test(VgRuntimeMutationCore::advisoryLockName($target) === $expected, 'Lock name did not match the documented digest.');
    vg_runtime_mutation_core_test(VgRuntimeMutationCore::advisoryLockName($target) === VgRuntimeMutationCore::advisoryLockName($target), 'Lock name is not deterministic.');
    $other = $target;
    $other['database_name'] = 'another_database';
    vg_runtime_mutation_core_test(VgRuntimeMutationCore::advisoryLockName($other) !== $expected, 'Different target identity reused the lock name.');
    vg_runtime_mutation_core_test(strlen($expected) <= 64, 'MariaDB lock name exceeds 64 characters.');
});

$test('state directory uses XDG_STATE_HOME', static function (): void {
    $path = VgRuntimeMutationCore::stateDirectory(['XDG_STATE_HOME' => '/state', 'HOME' => '/home/user']);
    vg_runtime_mutation_core_test($path === '/state/vgnova/runtime-mutation', 'XDG state path is incorrect.');
});

$test('state directory falls back to HOME', static function (): void {
    $path = VgRuntimeMutationCore::stateDirectory(['HOME' => '/home/user']);
    vg_runtime_mutation_core_test($path === '/home/user/.local/state/vgnova/runtime-mutation', 'HOME fallback path is incorrect.');
});

$test('relative state directory is rejected', static function (): void {
    vg_runtime_mutation_core_throws(71, static fn () => VgRuntimeMutationCore::stateDirectory(['XDG_STATE_HOME' => 'relative', 'HOME' => '/home/user']), 'Relative XDG path');
});

$test('temporary state directory is rejected', static function (): void {
    vg_runtime_mutation_core_throws(71, static fn () => VgRuntimeMutationCore::stateDirectory(['XDG_STATE_HOME' => '/tmp/vgnova']), 'Temporary state path');
});

$test('human-readable purpose is retained and credential-like purpose is rejected', static function (): void {
    vg_runtime_mutation_core_test(
        VgRuntimeMutationCore::validatePurpose('Run the Product Contribution QA') === 'Run the Product Contribution QA',
        'Safe purpose text did not survive validation.'
    );
    vg_runtime_mutation_core_throws(71, static fn () => VgRuntimeMutationCore::validatePurpose('Run QA with api_key=private'), 'Credential-like purpose');
});

foreach ([
    'localhost' => ['host' => 'localhost', 'port' => null, 'socket' => null],
    '127.0.0.1' => ['host' => '127.0.0.1', 'port' => null, 'socket' => null],
    'localhost:3306' => ['host' => 'localhost', 'port' => 3306, 'socket' => null],
    '127.0.0.1:3306' => ['host' => '127.0.0.1', 'port' => 3306, 'socket' => null],
    'localhost:/path/to/mysql.sock' => ['host' => 'localhost', 'port' => null, 'socket' => '/path/to/mysql.sock'],
] as $value => $expected) {
    $test('DB_HOST parses ' . $value, static function () use ($value, $expected): void {
        vg_runtime_mutation_core_test(VgRuntimeMutationCore::parseDbHost($value) === $expected, 'DB_HOST parsed incorrectly.');
    });
}

foreach (['', ':3306', 'localhost:0', 'localhost:65536', 'localhost:abc', 'localhost:relative.sock', '[::1]:3306'] as $value) {
    $test('malformed DB_HOST is rejected: ' . ($value === '' ? 'empty' : $value), static function () use ($value): void {
        vg_runtime_mutation_core_throws(71, static fn () => VgRuntimeMutationCore::parseDbHost($value), 'Malformed DB_HOST');
    });
}

$test('explicit socket overrides transport without changing host identity', static function (): void {
    $parsed = VgRuntimeMutationCore::parseDbHost('localhost:/configured/mysql.sock', '/override/mysql.sock');
    vg_runtime_mutation_core_test($parsed === ['host' => 'localhost', 'port' => null, 'socket' => '/override/mysql.sock'], 'Explicit socket did not override only transport.');
});

$test('first filesystem lock succeeds and second fails fast', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $path = $directory . '/target.lock';
        $first = VgRuntimeMutationCore::acquireFilesystemLock($path);
        vg_runtime_mutation_core_test(is_resource($first), 'First lock did not return a handle.');
        vg_runtime_mutation_core_test(VgRuntimeMutationCore::acquireFilesystemLock($path) === false, 'Second lock unexpectedly acquired.');
        flock($first, LOCK_UN);
        fclose($first);
    });
});

$test('stale metadata does not prevent real filesystem acquisition', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $path = $directory . '/target.lock';
        file_put_contents($path, '{"state":"active","session_id":"stale"}');
        $handle = VgRuntimeMutationCore::acquireFilesystemLock($path);
        vg_runtime_mutation_core_test(is_resource($handle), 'Stale metadata blocked actual acquisition.');
        fclose($handle);
    });
});

$test('filesystem lock probe reports held and free states', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $path = $directory . '/target.lock';
        $handle = VgRuntimeMutationCore::acquireFilesystemLock($path);
        vg_runtime_mutation_core_test(VgRuntimeMutationCore::probeFilesystemLock($path)['held'] === true, 'Held lock was reported free.');
        flock($handle, LOCK_UN);
        fclose($handle);
        vg_runtime_mutation_core_test(VgRuntimeMutationCore::probeFilesystemLock($path)['held'] === false, 'Free lock was reported held.');
    });
});

$test('GET_LOCK result distinguishes acquired, contention, and errors', static function (): void {
    vg_runtime_mutation_core_test(VgRuntimeMutationCore::interpretGetLockResult(1) === true, 'GET_LOCK 1 was not accepted.');
    vg_runtime_mutation_core_throws(73, static fn () => VgRuntimeMutationCore::interpretGetLockResult(0), 'GET_LOCK contention');
    vg_runtime_mutation_core_throws(70, static fn () => VgRuntimeMutationCore::interpretGetLockResult(null), 'GET_LOCK NULL');
    vg_runtime_mutation_core_throws(70, static fn () => VgRuntimeMutationCore::interpretGetLockResult(false), 'GET_LOCK query error');
});

$test('target identity accepts exact runtime identity', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $entrypoint = 'wp-content/plugins/voelgoed-platform-core/voelgoed-platform-core.php';
        $path = $directory . '/' . $entrypoint;
        mkdir(dirname($path), 0700, true);
        file_put_contents($path, '<?php');
        VgRuntimeMutationCore::validateTargetIdentity(vg_runtime_mutation_core_fixture_target(), [
            'database_name' => 'local', 'database_host' => 'localhost',
            'home' => 'http://localhost:10039', 'site' => 'http://localhost:10039',
        ], $directory);
    });
});

foreach ([
    ['database_name' => 'other', 'database_host' => 'localhost', 'home' => 'http://localhost:10039', 'site' => 'http://localhost:10039'],
    ['database_name' => 'local', 'database_host' => '127.0.0.1', 'home' => 'http://localhost:10039', 'site' => 'http://localhost:10039'],
    ['database_name' => 'local', 'database_host' => 'localhost', 'home' => 'http://wrong', 'site' => 'http://localhost:10039'],
    ['database_name' => 'local', 'database_host' => 'localhost', 'home' => 'http://localhost:10039', 'site' => 'http://wrong'],
] as $observed) {
    $test('identity mismatch is rejected before mutation', static function () use ($observed): void {
        vg_runtime_mutation_core_with_temp_dir(static function (string $directory) use ($observed): void {
            $entrypoint = 'wp-content/plugins/voelgoed-platform-core/voelgoed-platform-core.php';
            mkdir(dirname($directory . '/' . $entrypoint), 0700, true);
            file_put_contents($directory . '/' . $entrypoint, '<?php');
            vg_runtime_mutation_core_throws(74, static fn () => VgRuntimeMutationCore::validateTargetIdentity(
                vg_runtime_mutation_core_fixture_target(),
                $observed,
                $directory
            ), 'Identity mismatch');
        });
    });
}

$test('missing Platform Core entrypoint is rejected', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        vg_runtime_mutation_core_throws(74, static fn () => VgRuntimeMutationCore::validateTargetIdentity(
            vg_runtime_mutation_core_fixture_target(),
            ['database_name' => 'local', 'database_host' => 'localhost', 'home' => 'http://localhost:10039', 'site' => 'http://localhost:10039'],
            $directory
        ), 'Missing entrypoint');
    });
});

$test('nested session is rejected with exit 75', static function (): void {
    vg_runtime_mutation_core_throws(75, static fn () => VgRuntimeMutationCore::assertNotNested(['VGNOVA_MUTATION_SESSION_ID' => 'active']), 'Nested session');
    VgRuntimeMutationCore::assertNotNested([]);
});

$test('child cannot start before filesystem, database, and identity gates', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $events = [];
        $status = VgRuntimeMutationCore::withSession(
            $directory . '/target.lock',
            vg_runtime_mutation_core_fixture_target(),
            ['purpose' => 'unit test'],
            static function (string $lockName) use (&$events): array { $events[] = 'db'; return ['result' => 1, 'connection_id' => 42]; },
            static function (array $target) use (&$events): void { $events[] = 'identity'; },
            static function (array $proof, $lockHandle) use (&$events): int {
                $events[] = 'child';
                vg_runtime_mutation_core_test(is_resource($lockHandle), 'Child runner did not receive the held lock.');
                vg_runtime_mutation_core_test($proof['db_connection_id'] === 42, 'Child proof missed the database connection ID.');
                return 0;
            },
            static function (string $lockName) use (&$events): void { $events[] = 'release-db'; },
            static function (array $metadata) use (&$events): void { $events[] = $metadata['state']; }
        );
        vg_runtime_mutation_core_test($status === 0, 'Child status was not returned.');
        vg_runtime_mutation_core_test($events === ['db', 'identity', 'active', 'child', 'release-db', 'released'], 'Lock and child order is wrong: ' . implode(',', $events));
    });
});

$test('database contention releases filesystem lock and does not start child', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $childStarted = false;
        vg_runtime_mutation_core_throws(73, static fn () => VgRuntimeMutationCore::withSession(
            $directory . '/target.lock', vg_runtime_mutation_core_fixture_target(), ['purpose' => 'contention'],
            static fn (string $lockName): array => ['result' => 0, 'connection_id' => null],
            static function (array $target): void {},
            static function (array $proof, $handle) use (&$childStarted): int { $childStarted = true; return 0; },
            static function (string $lockName): void {}
        ), 'Database contention');
        vg_runtime_mutation_core_test($childStarted === false, 'Child started after database contention.');
        $handle = VgRuntimeMutationCore::acquireFilesystemLock($directory . '/target.lock');
        vg_runtime_mutation_core_test(is_resource($handle), 'Database contention left the host lock held.');
        fclose($handle);
    });
});

$test('identity failure releases database then filesystem lock', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $events = [];
        try {
            VgRuntimeMutationCore::withSession(
                $directory . '/target.lock', vg_runtime_mutation_core_fixture_target(), ['purpose' => 'identity failure'],
                static fn (string $lockName): array => ['result' => 1, 'connection_id' => 42],
                static function (array $target): void { throw new VgRuntimeMutationException(74, 'TARGET_IDENTITY_MISMATCH'); },
                static fn (array $proof, $handle): int => 0,
                static function (string $lockName) use (&$events): void { $events[] = 'release-db'; },
                static function (array $metadata) use (&$events): void { $events[] = $metadata['state']; }
            );
            throw new RuntimeException('Identity gate did not fail.');
        } catch (VgRuntimeMutationException $exception) {
            vg_runtime_mutation_core_test($exception->exitCode === 74, 'Identity gate returned the wrong exit code.');
        }
        vg_runtime_mutation_core_test($events === ['release-db', 'released'], 'Identity failure did not release in reverse order: ' . implode(',', $events));
    });
});

$test('child failure is propagated and releases locks', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $events = [];
        $status = VgRuntimeMutationCore::withSession(
            $directory . '/target.lock', vg_runtime_mutation_core_fixture_target(), ['purpose' => 'child failure'],
            static fn (string $lockName): array => ['result' => 1, 'connection_id' => 42],
            static function (array $target): void {},
            static fn (array $proof, $handle): int => 23,
            static function (string $lockName) use (&$events): void { $events[] = 'release-db'; },
            static function (array $metadata) use (&$events): void { $events[] = $metadata['state']; }
        );
        vg_runtime_mutation_core_test($status === 23, 'Child failure was not propagated.');
        vg_runtime_mutation_core_test($events === ['active', 'release-db', 'released'], 'Child failure did not release session.');
        $handle = VgRuntimeMutationCore::acquireFilesystemLock($directory . '/target.lock');
        vg_runtime_mutation_core_test(is_resource($handle), 'Child failure left the host lock held.');
        fclose($handle);
    });
});

$test('child exception releases locks', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $released = false;
        try {
            VgRuntimeMutationCore::withSession(
                $directory . '/target.lock', vg_runtime_mutation_core_fixture_target(), ['purpose' => 'child exception'],
                static fn (string $lockName): array => ['result' => 1, 'connection_id' => 42],
                static function (array $target): void {},
                static function (array $proof, $handle): int { throw new RuntimeException('child exception'); },
                static function (string $lockName) use (&$released): void { $released = true; }
            );
        } catch (RuntimeException $exception) {
            vg_runtime_mutation_core_test($exception->getMessage() === 'child exception', 'Wrong exception was thrown.');
        }
        vg_runtime_mutation_core_test($released, 'Database release did not run after exception.');
        $handle = VgRuntimeMutationCore::acquireFilesystemLock($directory . '/target.lock');
        vg_runtime_mutation_core_test(is_resource($handle), 'Exception left the host lock held.');
        fclose($handle);
    });
});

$test('status probes an existing lock without releasing it', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $path = $directory . '/target.lock';
        $owner = VgRuntimeMutationCore::acquireFilesystemLock($path);
        $status = VgRuntimeMutationCore::probeFilesystemLock($path);
        vg_runtime_mutation_core_test($status['held'] === true, 'Status did not report the active lock.');
        vg_runtime_mutation_core_test(VgRuntimeMutationCore::acquireFilesystemLock($path) === false, 'Status released an existing lock.');
        flock($owner, LOCK_UN);
        fclose($owner);
    });
});

$test('child environment excludes database credentials and keeps only session proof', static function (): void {
    $environment = VgRuntimeMutationCore::childEnvironment([
        'PATH' => '/usr/bin', 'HOME' => '/home/test', 'LANG' => 'en_US.UTF-8',
        'DB_PASSWORD' => 'hidden', 'MYSQL_PWD' => 'also-hidden',
        'DATABASE_URL' => 'mysql://user:password@localhost/db', 'UNREVIEWED_VALUE' => 'hidden',
        'VGNOVA_MUTATION_SESSION_ID' => 'stale',
    ], [
        'session_id' => 'session-1234', 'target' => 'vgnova-local-10039',
        'db_lock_name' => 'vgnova.mutation.digest', 'db_connection_id' => 42,
        'lock_file' => '/state/target.lock',
    ]);
    vg_runtime_mutation_core_test(
        ! isset($environment['DB_PASSWORD'])
            && ! isset($environment['MYSQL_PWD'])
            && ! isset($environment['DATABASE_URL'])
            && ! isset($environment['UNREVIEWED_VALUE']),
        'A secret or unreviewed environment value reached the child.'
    );
    vg_runtime_mutation_core_test($environment['PATH'] === '/usr/bin' && $environment['HOME'] === '/home/test', 'Allow-listed environment values were dropped.');
    vg_runtime_mutation_core_test($environment['VGNOVA_MUTATION_SESSION_ID'] === 'session-1234', 'Session proof was not refreshed.');
    vg_runtime_mutation_core_test($environment['VGNOVA_MUTATION_DB_CONNECTION_ID'] === '42', 'Connection proof is missing.');
});

$test('metadata and command summary omit secret values', static function (): void {
    $metadata = VgRuntimeMutationCore::metadata([
        'target' => 'vgnova-local-10039', 'purpose' => 'password=do-not-store',
        'command' => ['/usr/local/bin/wp', 'eval-file', 'qa.php', '--token=private'],
        'environment' => ['DB_PASSWORD' => 'do-not-store'],
    ]);
    $encoded = json_encode($metadata, JSON_THROW_ON_ERROR);
    vg_runtime_mutation_core_test(! str_contains($encoded, 'do-not-store') && ! str_contains($encoded, 'private'), 'Secret value leaked into metadata.');
    vg_runtime_mutation_core_test($metadata['purpose'] === 'administrative mutation', 'Unsafe purpose was not replaced with a safe diagnostic label.');
    vg_runtime_mutation_core_test($metadata['command_summary'] === 'wp [4 arguments]', 'Command summary is not sanitized.');
});

$test('forbidden bypass options are rejected', static function (): void {
    foreach (['--force', '--force-unlock', '--steal', '--kill-owner', '--ignore-lock', '--ignore-db-lock', '--ignore-filesystem-lock', '--skip-session-check'] as $option) {
        vg_runtime_mutation_core_throws(71, static fn () => VgRuntimeMutationCore::validateArguments([$option]), 'Forbidden option ' . $option);
    }
});

$test('child command preserves argv boundaries and exit status', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $lock = VgRuntimeMutationCore::acquireFilesystemLock($directory . '/target.lock');
        $capture = $directory . '/arguments.json';
        $childCode = 'file_put_contents($argv[1], json_encode(array_slice($argv, 2), JSON_THROW_ON_ERROR)); exit(23);';
        $arguments = [PHP_BINARY, '-r', $childCode, $capture, 'space argument', 'quote " value', 'UTF-8 ä', ''];
        $status = VgRuntimeMutationCore::runChildCommand($arguments, ['PATH' => (string) getenv('PATH')], $lock);
        vg_runtime_mutation_core_test($status === 23, 'Child exit status was not propagated.');
        $actual = json_decode((string) file_get_contents($capture), true, 8, JSON_THROW_ON_ERROR);
        vg_runtime_mutation_core_test($actual === ['space argument', 'quote " value', 'UTF-8 ä', ''], 'Child argv boundaries changed.');
        flock($lock, LOCK_UN);
        fclose($lock);
    });
});

$test('child process group terminates a mutating descendant before returning', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $lock = VgRuntimeMutationCore::acquireFilesystemLock($directory . '/target.lock');
        $pidFile = $directory . '/descendant.pid';
        $lateFile = $directory . '/late-write';
        $childCode = '$pid = pcntl_fork(); if ($pid === 0) { usleep(1200000); file_put_contents($argv[1], "late"); exit(0); } file_put_contents($argv[2], (string) $pid); exit(19);';
        $status = VgRuntimeMutationCore::runChildCommand(
            [PHP_BINARY, '-r', $childCode, $lateFile, $pidFile],
            ['PATH' => (string) getenv('PATH')],
            $lock
        );
        vg_runtime_mutation_core_test($status === 19, 'The direct child exit status was not propagated.');
        $descendantPid = (int) trim((string) file_get_contents($pidFile));
        vg_runtime_mutation_core_test($descendantPid > 1 && ! @posix_kill($descendantPid, 0), 'A mutating descendant remained after the runner returned.');
        vg_runtime_mutation_core_test(! file_exists($lateFile), 'A mutating descendant wrote after the lock runner returned.');
        flock($lock, LOCK_UN);
        fclose($lock);
    });
});

$test('child signal exit is reported as 128 plus the signal', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $lock = VgRuntimeMutationCore::acquireFilesystemLock($directory . '/target.lock');
        $status = VgRuntimeMutationCore::runChildCommand(
            [PHP_BINARY, '-r', 'posix_kill(getmypid(), SIGKILL);'],
            ['PATH' => (string) getenv('PATH')],
            $lock
        );
        vg_runtime_mutation_core_test($status === 137, 'SIGKILL exit did not map to 137.');
        flock($lock, LOCK_UN);
        fclose($lock);
    });
});

$test('coordinator death cannot release host lock while child continues', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $lockFile = $directory . '/target.lock';
        $marker = $directory . '/child.marker';
        $fixture = __DIR__ . '/../fixtures/runtime-mutation-parent-death.php';
        $process = proc_open(
            [PHP_BINARY, $fixture, $lockFile, $marker, 'hold'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes
        );
        vg_runtime_mutation_core_test(is_resource($process), 'Coordinator fixture did not start.');
        $coordinatorPid = proc_get_status($process)['pid'];
        $deadline = microtime(true) + 3.0;
        while ((! is_file($marker) || trim((string) file_get_contents($marker)) !== 'started') && microtime(true) < $deadline) {
            usleep(20000);
        }
        vg_runtime_mutation_core_test(is_file($marker) && trim((string) file_get_contents($marker)) === 'started', 'Mutation child did not start.');
        usleep(100000);
        posix_kill($coordinatorPid, SIGKILL);
        proc_close($process);

        vg_runtime_mutation_core_test(VgRuntimeMutationCore::acquireFilesystemLock($lockFile) === false, 'A contender acquired the lock while the child remained alive.');
        $deadline = microtime(true) + 4.0;
        while (trim((string) @file_get_contents($marker)) !== 'finished' && microtime(true) < $deadline) {
            usleep(50000);
        }
        vg_runtime_mutation_core_test(trim((string) file_get_contents($marker)) === 'finished', 'Mutation child did not finish after coordinator death.');

        $acquired = false;
        $deadline = microtime(true) + 2.0;
        while (microtime(true) < $deadline) {
            $handle = VgRuntimeMutationCore::acquireFilesystemLock($lockFile);
            if (is_resource($handle)) {
                $acquired = true;
                fclose($handle);
                break;
            }
            usleep(20000);
        }
        vg_runtime_mutation_core_test($acquired, 'Lock did not become available after child exit.');
    });
});

$test('database keeper protects a mutator after coordinator death even when it closes the host descriptor', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $lockFile = $directory . '/target.lock';
        $databaseLockFile = $lockFile . '.database';
        $marker = $directory . '/child.marker';
        $fixture = __DIR__ . '/../fixtures/runtime-mutation-parent-death.php';
        $process = proc_open(
            [PHP_BINARY, $fixture, $lockFile, $marker, 'close-host-lock'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes
        );
        vg_runtime_mutation_core_test(is_resource($process), 'Layered coordinator fixture did not start.');
        $coordinatorPid = proc_get_status($process)['pid'];
        $deadline = microtime(true) + 3.0;
        while ((! is_file($marker) || trim((string) file_get_contents($marker)) !== 'started') && microtime(true) < $deadline) {
            usleep(20000);
        }
        vg_runtime_mutation_core_test(is_file($marker) && trim((string) file_get_contents($marker)) === 'started', 'Mutating child did not start.');
        $deadline = microtime(true) + 3.0;
        while (! is_file($marker . '.keeper') && microtime(true) < $deadline) {
            usleep(20000);
        }
        vg_runtime_mutation_core_test(is_file($marker . '.keeper'), 'Database keeper process was not registered.');
        posix_kill($coordinatorPid, SIGKILL);
        proc_close($process);

        $hostLock = VgRuntimeMutationCore::acquireFilesystemLock($lockFile);
        vg_runtime_mutation_core_test(is_resource($hostLock), 'The child unexpectedly retained the closed host lock descriptor.');
        $databaseLock = VgRuntimeMutationCore::acquireFilesystemLock($databaseLockFile);
        vg_runtime_mutation_core_test($databaseLock === false, 'A contender bypassed the database keeper while the mutator continued.');
        fclose($hostLock);

        $deadline = microtime(true) + 4.0;
        while (trim((string) @file_get_contents($marker)) !== 'finished' && microtime(true) < $deadline) {
            usleep(50000);
        }
        vg_runtime_mutation_core_test(trim((string) @file_get_contents($marker)) === 'finished', 'Mutator did not finish after coordinator death.');

        $databaseAcquired = false;
        $deadline = microtime(true) + 3.0;
        while (microtime(true) < $deadline) {
            $databaseLock = VgRuntimeMutationCore::acquireFilesystemLock($databaseLockFile);
            if (is_resource($databaseLock)) {
                $databaseAcquired = true;
                fclose($databaseLock);
                break;
            }
            usleep(20000);
        }
        vg_runtime_mutation_core_test($databaseAcquired, 'The database keeper did not release after the mutator ended.');
        $hostLock = VgRuntimeMutationCore::acquireFilesystemLock($lockFile);
        vg_runtime_mutation_core_test(is_resource($hostLock), 'The host lock did not become available after mutation ended.');
        fclose($hostLock);
    });
});

$test('host lock protects a mutator if the database keeper exits unexpectedly', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $lockFile = $directory . '/target.lock';
        $marker = $directory . '/child.marker';
        $fixture = __DIR__ . '/../fixtures/runtime-mutation-parent-death.php';
        $process = proc_open(
            [PHP_BINARY, $fixture, $lockFile, $marker, 'close-host-lock'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes
        );
        vg_runtime_mutation_core_test(is_resource($process), 'Layered coordinator fixture did not start.');
        $deadline = microtime(true) + 3.0;
        while ((! is_file($marker) || trim((string) file_get_contents($marker)) !== 'started') && microtime(true) < $deadline) {
            usleep(20000);
        }
        vg_runtime_mutation_core_test(is_file($marker) && trim((string) file_get_contents($marker)) === 'started', 'Mutating child did not start.');
        $deadline = microtime(true) + 3.0;
        while (! is_file($marker . '.keeper') && microtime(true) < $deadline) {
            usleep(20000);
        }
        vg_runtime_mutation_core_test(is_file($marker . '.keeper'), 'Database keeper process was not registered.');
        $keeperPid = (int) trim((string) file_get_contents($marker . '.keeper'));
        vg_runtime_mutation_core_test($keeperPid > 1, 'Database keeper PID is invalid.');
        posix_kill($keeperPid, SIGKILL);

        vg_runtime_mutation_core_test(VgRuntimeMutationCore::acquireFilesystemLock($lockFile) === false, 'A contender acquired the host lock after keeper failure.');
        $deadline = microtime(true) + 4.0;
        while (trim((string) @file_get_contents($marker)) !== 'finished' && microtime(true) < $deadline) {
            usleep(50000);
        }
        vg_runtime_mutation_core_test(trim((string) @file_get_contents($marker)) === 'finished', 'Mutator did not finish after keeper failure.');
        proc_close($process);

        $lock = VgRuntimeMutationCore::acquireFilesystemLock($lockFile);
        vg_runtime_mutation_core_test(is_resource($lock), 'Host lock did not release after mutation ended.');
        fclose($lock);
    });
});

$test('SIGTERM reaches child and host lock releases after child exit', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $lockFile = $directory . '/target.lock';
        $marker = $directory . '/child.marker';
        $fixture = __DIR__ . '/../fixtures/runtime-mutation-parent-death.php';
        $process = proc_open(
            [PHP_BINARY, $fixture, $lockFile, $marker, 'signal'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes
        );
        vg_runtime_mutation_core_test(is_resource($process), 'Signal fixture did not start.');
        $coordinatorPid = proc_get_status($process)['pid'];
        $deadline = microtime(true) + 3.0;
        while ((! is_file($marker) || trim((string) file_get_contents($marker)) !== 'started') && microtime(true) < $deadline) {
            usleep(20000);
        }
        vg_runtime_mutation_core_test(is_file($marker) && trim((string) file_get_contents($marker)) === 'started', 'Signal child did not start.');
        posix_kill($coordinatorPid, SIGTERM);
        $deadline = microtime(true) + 4.0;
        while (trim((string) @file_get_contents($marker)) !== 'terminated' && microtime(true) < $deadline) {
            usleep(20000);
        }
        vg_runtime_mutation_core_test(trim((string) file_get_contents($marker)) === 'terminated', 'Child did not receive SIGTERM.');
        proc_close($process);

        $handle = VgRuntimeMutationCore::acquireFilesystemLock($lockFile);
        vg_runtime_mutation_core_test(is_resource($handle), 'SIGTERM cleanup left the host lock held.');
        fclose($handle);
    });
});

$test('malformed WP database host is rejected before a database connection', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $binDirectory = $directory . '/bin';
        mkdir($binDirectory, 0700, true);
        $callLog = $directory . '/wp-config-reads.log';
        $wpPath = $binDirectory . '/wp';
        $wpScript = <<<'SH'
#!/bin/sh
printf '%s:%s\n' "$5" "$6" >> __CALL_LOG__
case "$5" in
  DB_NAME) printf '"local"\n' ;;
  DB_USER) printf '"test-user"\n' ;;
  DB_PASSWORD) printf '"secret-sentinel"\n' ;;
  DB_HOST) printf '"localhost:relative.sock"\n' ;;
  table_prefix) printf '"wp_"\n' ;;
  *) exit 2 ;;
esac
SH;
        $wpScript = str_replace('__CALL_LOG__', escapeshellarg($callLog), $wpScript);
        file_put_contents($wpPath, $wpScript);
        chmod($wpPath, 0700);
        $target = vg_runtime_mutation_core_fixture_target();
        try {
            VgRuntimeMutationDatabase::startKeeper(
                $target,
                $directory,
                VgRuntimeMutationCore::advisoryLockName($target),
                null,
                [
                    'PATH' => $binDirectory,
                    'HOME' => $directory,
                ]
            );
            throw new RuntimeException('Malformed database host was accepted.');
        } catch (VgRuntimeMutationException $exception) {
            vg_runtime_mutation_core_test($exception->exitCode === 71, 'Malformed host returned the wrong exit code.');
            vg_runtime_mutation_core_test($exception->errorCode === 'INVALID_DB_HOST', 'Malformed host returned the wrong diagnostic.');
            vg_runtime_mutation_core_test(! str_contains($exception->getMessage(), 'secret-sentinel'), 'Database password reached the diagnostic.');
        }
        $reads = file($callLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        vg_runtime_mutation_core_test($reads === [
            'DB_NAME:--type=constant',
            'DB_USER:--type=constant',
            'DB_PASSWORD:--type=constant',
            'DB_HOST:--type=constant',
            'table_prefix:--type=variable',
        ], 'The WP-CLI configuration reads were not isolated or validation did not stop at DB_HOST: ' . var_export($reads, true));
    });
});

$test('database keeper release waits for a positive release acknowledgement', static function (): void {
    $script = '$buffer = ""; while (!feof(STDIN)) { $chunk = fread(STDIN, 8192); if ($chunk !== false) { $buffer .= $chunk; } } fwrite(STDOUT, "{\\"released\\":true}\\n");';
    $process = proc_open(
        [PHP_BINARY, '-r', $script],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']],
        $pipes
    );
    vg_runtime_mutation_core_test(is_resource($process), 'Fake keeper process did not start.');
    VgRuntimeMutationDatabase::releaseKeeper([
        'lifetime_handle' => $pipes[0],
        'keeper_stdout' => $pipes[1],
        'keeper_process' => $process,
    ]);
});

$test('database status reports an invalid root as an error state', static function (): void {
    $target = vg_runtime_mutation_core_fixture_target();
    $status = VgRuntimeMutationDatabase::statusLockOwner(
        $target,
        '/path/that/does/not/exist',
        VgRuntimeMutationCore::advisoryLockName($target)
    );
    vg_runtime_mutation_core_test(
        $status === ['state' => 'error', 'owner_connection_id' => null, 'error_code' => 'INVALID_WORDPRESS_ROOT'],
        'Database status did not report an explicit error state.'
    );
});

$test('SIGTERM escalates against a child that ignores it and releases locks after group exit', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $lockFile = $directory . '/target.lock';
        $marker = $directory . '/child.marker';
        $fixture = __DIR__ . '/../fixtures/runtime-mutation-parent-death.php';
        $process = proc_open(
            [PHP_BINARY, $fixture, $lockFile, $marker, 'ignore-signal'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes
        );
        vg_runtime_mutation_core_test(is_resource($process), 'Ignored-signal fixture did not start.');
        $coordinatorPid = proc_get_status($process)['pid'];
        $deadline = microtime(true) + 3.0;
        while ((! is_file($marker) || trim((string) file_get_contents($marker)) !== 'started') && microtime(true) < $deadline) {
            usleep(20000);
        }
        vg_runtime_mutation_core_test(is_file($marker) && trim((string) file_get_contents($marker)) === 'started', 'Ignored-signal child did not start.');
        $childPid = (int) trim((string) file_get_contents($marker . '.pid'));
        posix_kill($coordinatorPid, SIGTERM);
        proc_close($process);
        vg_runtime_mutation_core_test(trim((string) @file_get_contents($marker . '.exit')) === '143', 'Coordinator did not report the original SIGTERM result.');
        vg_runtime_mutation_core_test(! @posix_kill($childPid, 0), 'A child that ignored SIGTERM remained alive after escalation.');
        $lock = VgRuntimeMutationCore::acquireFilesystemLock($lockFile);
        vg_runtime_mutation_core_test(is_resource($lock), 'Filesystem lock remained held after the child group exited.');
        fclose($lock);
        $databaseLock = VgRuntimeMutationCore::acquireFilesystemLock($lockFile . '.database');
        vg_runtime_mutation_core_test(is_resource($databaseLock), 'Database keeper did not release after the child group exited.');
        fclose($databaseLock);
    });
});

echo "{$tests} runtime mutation core tests passed.\n";
