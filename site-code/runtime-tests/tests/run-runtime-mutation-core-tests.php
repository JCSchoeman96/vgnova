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

function vg_runtime_mutation_core_fake_database_result(array $lockNames): array
{
    return [
        'locks' => [
            'a' => ['name' => $lockNames['a'], 'connection_id' => 42],
            'b' => ['name' => $lockNames['b'], 'connection_id' => 43],
        ],
        'identity' => [],
        'lifetime_handles' => [],
        'registration_handles' => [],
    ];
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

/** @return array{a:string,b:string} */
function vg_runtime_mutation_core_fixture_database_lock_paths(string $hostLockFile): array
{
    return ['a' => $hostLockFile . '.database-a', 'b' => $hostLockFile . '.database-b'];
}

function vg_runtime_mutation_core_fixture_has_both_keepers(string $markerFile): bool
{
    return is_file($markerFile . '.keeper.a') && is_file($markerFile . '.keeper.b');
}

/** @param array{a:string,b:string} $paths @return array<string,resource> */
function vg_runtime_mutation_core_try_database_lock_pair(array $paths): array
{
    $handles = [];
    foreach (['a', 'b'] as $slot) {
        $handle = VgRuntimeMutationCore::acquireFilesystemLock($paths[$slot]);
        if (! is_resource($handle)) {
            foreach ($handles as $held) {
                flock($held, LOCK_UN);
                fclose($held);
            }
            return [];
        }
        $handles[$slot] = $handle;
    }
    return $handles;
}

/** @param array<string,resource> $handles */
function vg_runtime_mutation_core_release_lock_handles(array $handles): void
{
    foreach (['b', 'a'] as $slot) {
        if (is_resource($handles[$slot] ?? null)) {
            flock($handles[$slot], LOCK_UN);
            fclose($handles[$slot]);
        }
    }
}

/** @return list<int> */
function vg_runtime_mutation_core_direct_children(int $processId): array
{
    $path = "/proc/{$processId}/task/{$processId}/children";
    $contents = @file_get_contents($path);
    if (! is_string($contents)) {
        return [];
    }
    preg_match_all('/[1-9][0-9]*/', $contents, $matches);
    return array_map('intval', $matches[0] ?? []);
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
    $digest = substr(hash('sha256', implode("\n", [
        $target['id'], $target['database_name'], $target['home'], $target['site'],
    ])), 0, 40);
    $expected = ['a' => 'vgnova.mutation.a.' . $digest, 'b' => 'vgnova.mutation.b.' . $digest];
    vg_runtime_mutation_core_test(VgRuntimeMutationCore::advisoryLockNames($target) === $expected, 'A/B lock names did not match the documented digest.');
    vg_runtime_mutation_core_test(VgRuntimeMutationCore::advisoryLockNames($target) === VgRuntimeMutationCore::advisoryLockNames($target), 'Lock names are not deterministic.');
    $other = $target;
    $other['database_name'] = 'another_database';
    $otherNames = VgRuntimeMutationCore::advisoryLockNames($other);
    vg_runtime_mutation_core_test($otherNames['a'] !== $expected['a'] && $otherNames['b'] !== $expected['b'], 'Different target identity reused a lock name.');
    vg_runtime_mutation_core_test(strlen($expected['a']) <= 64 && strlen($expected['b']) <= 64, 'MariaDB lock name exceeds 64 characters.');
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

$test('purpose rejects credential phrases even without an equals sign', static function (): void {
    vg_runtime_mutation_core_throws(
        71,
        static fn () => VgRuntimeMutationCore::validatePurpose('Review the password is hunter2'),
        'Credential phrase'
    );
});

$test('purpose rejects labeled credentials and short URL passwords', static function (): void {
    foreach ([
        'DB_PASSWORD: review-sentinel',
        'AUTH_KEY: review-sentinel',
        'https://alice:shortpass@example.org',
    ] as $purpose) {
        vg_runtime_mutation_core_throws(
            71,
            static fn () => VgRuntimeMutationCore::validatePurpose($purpose),
            'Credential-bearing purpose'
        );
    }
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

$test('filesystem lock refuses a group-writable state directory', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        chmod($directory, 0770);
        vg_runtime_mutation_core_throws(
            70,
            static fn () => VgRuntimeMutationCore::acquireFilesystemLock($directory . '/target.lock'),
            'Unsafe state directory'
        );
    });
});

$test('filesystem lock refuses an existing group-readable lock file', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $path = $directory . '/target.lock';
        file_put_contents($path, '');
        chmod($path, 0640);
        vg_runtime_mutation_core_throws(
            70,
            static fn () => VgRuntimeMutationCore::acquireFilesystemLock($path),
            'Unsafe existing lock file'
        );
    });
});

$test('stale metadata does not prevent real filesystem acquisition', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $path = $directory . '/target.lock';
        file_put_contents($path, '{"state":"active","session_id":"stale"}');
        chmod($path, 0600);
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
        $expectedLockNames = VgRuntimeMutationCore::advisoryLockNames(vg_runtime_mutation_core_fixture_target());
        $status = VgRuntimeMutationCore::withSession(
            $directory . '/target.lock',
            vg_runtime_mutation_core_fixture_target(),
            ['purpose' => 'unit test'],
            static function (array $lockNames) use (&$events): array {
                vg_runtime_mutation_core_test(array_keys($lockNames) === ['a', 'b'], 'Database locks were not requested in A/B order.');
                $events[] = 'database-a';
                $events[] = 'database-b';
                return vg_runtime_mutation_core_fake_database_result($lockNames);
            },
            static function (array $target) use (&$events): void { $events[] = 'identity'; },
            static function (array $proof, $lockHandle) use (&$events): int {
                $events[] = 'child';
                vg_runtime_mutation_core_test(is_resource($lockHandle), 'Child runner did not receive the held lock.');
                vg_runtime_mutation_core_test($proof['db_locks']['a']['connection_id'] === 42 && $proof['db_locks']['b']['connection_id'] === 43, 'Child proof missed a database connection ID.');
                return 0;
            },
            static function (array $lockNames) use (&$events): void { $events[] = 'release-b'; $events[] = 'release-a'; },
            static function (array $metadata) use (&$events, $expectedLockNames): void {
                $events[] = $metadata['state'];
                if ($metadata['state'] === 'active') {
                    vg_runtime_mutation_core_test(
                        $metadata['schema_version'] === 2
                            && $metadata['database_locks'] === [
                                'a' => ['name' => $expectedLockNames['a'], 'connection_id' => 42],
                                'b' => ['name' => $expectedLockNames['b'], 'connection_id' => 43],
                            ],
                        'Active metadata did not record both sanitized lock identities and connection IDs.'
                    );
                }
            }
        );
        vg_runtime_mutation_core_test($status === 0, 'Child status was not returned.');
        vg_runtime_mutation_core_test(
            $events === ['database-a', 'database-b', 'identity', 'active', 'child', 'release-b', 'release-a', 'released'],
            'Database acquisition, identity, child, or reverse release order is wrong: ' . implode(',', $events)
        );
    });
});

$test('partial A acquisition is released when B is contended', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $paths = vg_runtime_mutation_core_fixture_database_lock_paths($directory . '/independent-host.lock');
        $blockedB = VgRuntimeMutationCore::acquireFilesystemLock($paths['b']);
        vg_runtime_mutation_core_test(is_resource($blockedB), 'Could not hold slot B for the partial-acquisition fixture.');
        $childMarker = $directory . '/second-child-started';
        $script = <<<'PHP'
require $argv[1];
$lockA = VgRuntimeMutationCore::acquireFilesystemLock($argv[2]);
if (! is_resource($lockA)) { exit(71); }
$lockB = VgRuntimeMutationCore::acquireFilesystemLock($argv[3]);
if (is_resource($lockB)) {
    file_put_contents($argv[4], 'started');
    flock($lockB, LOCK_UN);
    fclose($lockB);
    flock($lockA, LOCK_UN);
    fclose($lockA);
    exit(72);
}
flock($lockA, LOCK_UN);
fclose($lockA);
exit(73);
PHP;
        try {
            $process = proc_open(
                [PHP_BINARY, '-r', $script, __DIR__ . '/../../runtime-tools/runtime-mutation-core.php', $paths['a'], $paths['b'], $childMarker],
                [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes
            );
            vg_runtime_mutation_core_test(is_resource($process), 'Partial-acquisition contender did not start.');
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            vg_runtime_mutation_core_test(proc_close($process) === 73, 'Contender did not stop after B contention: ' . trim((string) $stderr));
            vg_runtime_mutation_core_test(! is_file($childMarker), 'A child marker was written after B contention.');

            $availableA = VgRuntimeMutationCore::acquireFilesystemLock($paths['a']);
            $availableB = VgRuntimeMutationCore::acquireFilesystemLock($paths['b']);
            vg_runtime_mutation_core_test(is_resource($availableA), 'Contender did not release its partial A lock.');
            vg_runtime_mutation_core_test($availableB === false, 'The original B holder lost its lock.');
            if (is_resource($availableA)) {
                flock($availableA, LOCK_UN);
                fclose($availableA);
            }
        } finally {
            flock($blockedB, LOCK_UN);
            fclose($blockedB);
        }
    });
});

$test('database contention releases filesystem lock and does not start child', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $childStarted = false;
        vg_runtime_mutation_core_throws(73, static fn () => VgRuntimeMutationCore::withSession(
            $directory . '/target.lock', vg_runtime_mutation_core_fixture_target(), ['purpose' => 'contention'],
            static function (array $lockNames): array { throw new VgRuntimeMutationException(73, 'DATABASE_LOCK_CONTENTION'); },
            static function (array $target): void {},
            static function (array $proof, $handle) use (&$childStarted): int { $childStarted = true; return 0; },
            static function (array $lockNames): void {}
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
                static fn (array $lockNames): array => vg_runtime_mutation_core_fake_database_result($lockNames),
                static function (array $target): void { throw new VgRuntimeMutationException(74, 'TARGET_IDENTITY_MISMATCH'); },
                static fn (array $proof, $handle): int => 0,
                static function (array $lockNames) use (&$events): void { $events[] = 'release-db'; },
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
            static fn (array $lockNames): array => vg_runtime_mutation_core_fake_database_result($lockNames),
            static function (array $target): void {},
            static fn (array $proof, $handle): int => 23,
            static function (array $lockNames) use (&$events): void { $events[] = 'release-db'; },
            static function (array $metadata) use (&$events): void { $events[] = $metadata['state']; }
        );
        vg_runtime_mutation_core_test($status === 23, 'Child failure was not propagated.');
        vg_runtime_mutation_core_test($events === ['active', 'release-db', 'released'], 'Child failure did not release session.');
        $handle = VgRuntimeMutationCore::acquireFilesystemLock($directory . '/target.lock');
        vg_runtime_mutation_core_test(is_resource($handle), 'Child failure left the host lock held.');
        fclose($handle);
    });
});

$test('cleanup failure is reported without replacing the child exit status', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $metadataStates = [];
        $status = VgRuntimeMutationCore::withSession(
            $directory . '/target.lock', vg_runtime_mutation_core_fixture_target(), ['purpose' => 'cleanup failure'],
            static fn (array $lockNames): array => vg_runtime_mutation_core_fake_database_result($lockNames),
            static function (array $target): void {},
            static fn (array $proof, $handle): int => 23,
            static function (array $lockNames): void {
                throw new VgRuntimeMutationException(70, 'DATABASE_LOCK_RELEASE_FAILED', 'test release failure');
            },
            static function (array $metadata) use (&$metadataStates): void {
                $metadataStates[] = $metadata;
            }
        );
        vg_runtime_mutation_core_test($status === 23, 'Cleanup failure replaced the child exit result.');
        $finalMetadata = $metadataStates[count($metadataStates) - 1] ?? [];
        vg_runtime_mutation_core_test(($finalMetadata['state'] ?? null) === 'cleanup_failed', 'Cleanup failure was not recorded in session metadata.');
        vg_runtime_mutation_core_test(($finalMetadata['cleanup_error_code'] ?? null) === 'DATABASE_LOCK_RELEASE_FAILED', 'Cleanup error code was not reported separately.');
    });
});

$test('host lock remains held when cleanup guardian is still active', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $lockFile = $directory . '/target.lock';
        $releaseChild = $directory . '/release-child';
        $childPid = null;
        $status = VgRuntimeMutationCore::withSession(
            $lockFile,
            vg_runtime_mutation_core_fixture_target(), ['purpose' => 'guardian retention'],
            static function (array $lockNames, $hostLock) use (&$childPid, $releaseChild): array {
                $childPid = pcntl_fork();
                if ($childPid === -1) {
                    throw new RuntimeException('Could not start the host-lock guardian fixture.');
                }
                if ($childPid === 0) {
                    while (! is_file($releaseChild)) {
                        usleep(10000);
                    }
                    usleep(250000);
                    exit(0);
                }
                return vg_runtime_mutation_core_fake_database_result($lockNames);
            },
            static function (array $target): void {},
            static fn (array $proof, $hostLock, $lifetime): int => 23,
            static function (array $lockNames): void {
                throw new VgRuntimeMutationException(70, 'DATABASE_LOCK_KEEPER_RELEASE_TIMEOUT', 'guardian active', true);
            }
        );
        vg_runtime_mutation_core_test($status === 23, 'Guardian cleanup replaced the child exit status.');
        $contender = VgRuntimeMutationCore::acquireFilesystemLock($lockFile);
        if (is_resource($contender)) {
            fclose($contender);
        }
        vg_runtime_mutation_core_test($contender === false, 'The coordinator explicitly unlocked a host lock still held by its guardian.');
        file_put_contents($releaseChild, 'release');
        pcntl_waitpid((int) $childPid, $childStatus);
        $contender = VgRuntimeMutationCore::acquireFilesystemLock($lockFile);
        vg_runtime_mutation_core_test(is_resource($contender), 'The host lock did not release after the guardian exited.');
        fclose($contender);
    });
});

$test('child exception releases locks', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $released = false;
        try {
            VgRuntimeMutationCore::withSession(
                $directory . '/target.lock', vg_runtime_mutation_core_fixture_target(), ['purpose' => 'child exception'],
                static fn (array $lockNames): array => vg_runtime_mutation_core_fake_database_result($lockNames),
                static function (array $target): void {},
                static function (array $proof, $handle): int { throw new RuntimeException('child exception'); },
                static function (array $lockNames) use (&$released): void { $released = true; }
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
        'db_locks' => [
            'a' => ['name' => 'vgnova.mutation.a.' . str_repeat('a', 40), 'connection_id' => 42],
            'b' => ['name' => 'vgnova.mutation.b.' . str_repeat('a', 40), 'connection_id' => 43],
        ],
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
    vg_runtime_mutation_core_test(
        $environment['VGNOVA_MUTATION_DB_LOCK_A'] === 'vgnova.mutation.a.' . str_repeat('a', 40)
            && $environment['VGNOVA_MUTATION_DB_CONNECTION_ID_A'] === '42'
            && $environment['VGNOVA_MUTATION_DB_LOCK_B'] === 'vgnova.mutation.b.' . str_repeat('a', 40)
            && $environment['VGNOVA_MUTATION_DB_CONNECTION_ID_B'] === '43',
        'A/B database proof is missing.'
    );
    vg_runtime_mutation_core_test(
        ! isset($environment['VGNOVA_MUTATION_DB_LOCK'])
            && ! isset($environment['VGNOVA_MUTATION_DB_CONNECTION_ID']),
        'A singular legacy database proof alias reached the child.'
    );
});

$test('child environment passes only an explicit validated Local DB socket override', static function (): void {
    $proof = [
        'session_id' => str_repeat('a', 32), 'target' => 'vgnova-local-10039',
        'db_locks' => [
            'a' => ['name' => 'vgnova.mutation.a.' . str_repeat('a', 40), 'connection_id' => 42],
            'b' => ['name' => 'vgnova.mutation.b.' . str_repeat('a', 40), 'connection_id' => 43],
        ],
        'lock_file' => '/state/vgnova-local-10039.lock',
    ];
    $base = ['PATH' => '/usr/bin', 'VGNOVA_DB_SOCKET' => '/stale/socket', 'MYSQL_PWD' => 'hidden'];
    $default = VgRuntimeMutationCore::childEnvironment($base, $proof);
    vg_runtime_mutation_core_test(! isset($default['VGNOVA_DB_SOCKET']), 'An absent coordinator override inherited stale socket state.');
    vg_runtime_mutation_core_test(! isset($default['MYSQL_PWD']), 'A database credential reached the child.');

    $override = VgRuntimeMutationCore::childEnvironment($base, $proof, '/tmp/local-mysql.sock');
    vg_runtime_mutation_core_test(($override['VGNOVA_DB_SOCKET'] ?? null) === '/tmp/local-mysql.sock', 'Explicit socket override did not reach the child.');
    vg_runtime_mutation_core_test(! isset($override['MYSQL_PWD']), 'A database credential reached the overridden child.');
    $coordinator = file_get_contents(dirname(__DIR__, 2) . '/bin/vgnova-runtime-mutate.php');
    vg_runtime_mutation_core_test(is_string($coordinator)
        && str_contains($coordinator, 'use ($childArguments, $resolvedRoot, $socketOverride)')
        && str_contains($coordinator, 'childEnvironment($baseEnvironment, $proof, $socketOverride)'),
        'Coordinator did not forward its explicit socket override into the guarded child environment.');
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
    vg_runtime_mutation_core_test(
        array_keys($metadata['database_locks']) === ['a', 'b']
            && ! array_key_exists('database_lock_name', $metadata)
            && ! array_key_exists('database_connection_id', $metadata),
        'Metadata retained singular database-lock fields.'
    );
});

$test('status metadata redacts stale credential text from purpose and command summary', static function (): void {
    $safe = VgRuntimeMutationCore::safeStatusMetadata([
        'purpose' => 'DB_PASSWORD=review-sentinel',
        'command_summary' => 'AUTH_KEY=review-sentinel',
        'state' => 'active',
    ]);
    $encoded = json_encode($safe, JSON_THROW_ON_ERROR);
    vg_runtime_mutation_core_test(! str_contains($encoded, 'review-sentinel'), 'Unsafe stale metadata leaked through status formatting.');
    vg_runtime_mutation_core_test($safe['purpose'] === 'administrative mutation', 'Unsafe stale purpose was not replaced.');
    vg_runtime_mutation_core_test($safe['command_summary'] === 'command unavailable', 'Unsafe stale command summary was not omitted.');
    $lockSafe = VgRuntimeMutationCore::safeStatusMetadata([
        'database_locks' => [
            'a' => ['name' => 'vgnova.mutation.b.' . str_repeat('b', 40), 'connection_id' => 123],
            'b' => ['name' => 'vgnova.mutation.b.' . str_repeat('b', 40), 'connection_id' => 456],
        ],
    ]);
    vg_runtime_mutation_core_test(
        $lockSafe['database_locks']['a']['name'] === null
            && $lockSafe['database_locks']['b']['name'] === 'vgnova.mutation.b.' . str_repeat('b', 40),
        'Status metadata did not enforce slot-specific sanitized lock names.'
    );
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

$test('child command does not inherit coordinator pipes', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $lock = VgRuntimeMutationCore::acquireFilesystemLock($directory . '/target.lock');
        $capture = $directory . '/descriptors.json';
        $childCode = '$descriptors = []; foreach (range(3, 8) as $descriptor) { $target = @readlink("/proc/self/fd/" . $descriptor); if (is_string($target)) { $descriptors[$descriptor] = $target; } } file_put_contents($argv[1], json_encode($descriptors, JSON_THROW_ON_ERROR));';
        $status = VgRuntimeMutationCore::runChildCommand(
            [PHP_BINARY, '-r', $childCode, $capture],
            ['PATH' => (string) getenv('PATH')],
            $lock
        );
        $descriptors = json_decode((string) file_get_contents($capture), true, 8, JSON_THROW_ON_ERROR);
        $coordinatorPipes = is_array($descriptors)
            ? array_filter(
                array_intersect_key($descriptors, array_flip([4, 5, 6, 7, 8])),
                static fn (string $target): bool => preg_match('/\\Apipe:\\[[0-9]+\\]\\z/', $target) === 1
            )
            : null;
        vg_runtime_mutation_core_test($status === 0, 'Descriptor probe child did not complete.');
        vg_runtime_mutation_core_test(is_array($descriptors) && array_key_exists(3, $descriptors), 'Child did not inherit the host lock descriptor.');
        vg_runtime_mutation_core_test(
            $coordinatorPipes === [],
            'Child inherited coordinator pipe targets: ' . json_encode($coordinatorPipes, JSON_THROW_ON_ERROR)
        );
        flock($lock, LOCK_UN);
        fclose($lock);
    });
});

$test('child supervisor retains the session until a mutating descendant exits', static function (): void {
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
        vg_runtime_mutation_core_test($descendantPid > 1, 'The descendant PID was invalid.');
        vg_runtime_mutation_core_test(file_exists($lateFile), 'The runner returned before its descendant finished.');
        flock($lock, LOCK_UN);
        fclose($lock);
    });
});

$test('descriptor launcher closes inherited descriptors above nine', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $resultFile = $directory . '/descriptor-launcher.txt';
        $extraDescriptor = fopen('/dev/null', 'r');
        vg_runtime_mutation_core_test(is_resource($extraDescriptor), 'Could not open the high-descriptor fixture.');
        $command = VgRuntimeMutationCore::descriptorSafeCommand([
            PHP_BINARY,
            '-r',
            'file_put_contents($argv[1], "started");',
            $resultFile,
        ], []);
        $process = proc_open($command, [
            0 => ['file', '/dev/null', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
            12 => $extraDescriptor,
        ], $pipes);
        fclose($extraDescriptor);
        vg_runtime_mutation_core_test(is_resource($process), 'High-descriptor child did not start.');
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        vg_runtime_mutation_core_test($exitCode === 0, 'High-descriptor child exited unsuccessfully: ' . trim((string) $stderr));
        vg_runtime_mutation_core_test(file_get_contents($resultFile) === 'started', 'High-descriptor child did not execute.');
    });
});

$test('process-group exit wait uses its timeout', static function (): void {
    $pid = pcntl_fork();
    vg_runtime_mutation_core_test($pid !== -1, 'Could not start the process-group timeout fixture.');
    if ($pid === 0) {
        if (posix_setsid() === -1) {
            exit(91);
        }
        while (true) {
            usleep(50000);
        }
    }
    usleep(50000);
    $startedAt = microtime(true);
    $exited = VgRuntimeMutationCore::waitForProcessGroupExit($pid, 0.1);
    $elapsed = microtime(true) - $startedAt;
    posix_kill(-$pid, SIGKILL);
    pcntl_waitpid($pid, $status);
    vg_runtime_mutation_core_test(! $exited, 'A live process group was reported as exited.');
    vg_runtime_mutation_core_test($elapsed < 0.5, 'The process-group exit wait exceeded its bounded timeout.');
});

$test('child readiness polling returns promptly to observe pending signals', static function (): void {
    $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    vg_runtime_mutation_core_test(is_array($pair), 'Could not create readiness-poll fixture streams.');
    stream_set_blocking($pair[0], false);
    $startedAt = microtime(true);
    $poll = new ReflectionMethod(VgRuntimeMutationCore::class, 'pollChildReadiness');
    $poll->setAccessible(true);
    do {
        $result = $poll->invoke(null, $pair[0], static fn (): bool => microtime(true) - $startedAt >= 0.075);
    } while (($result['state'] ?? null) === 'waiting' && microtime(true) - $startedAt < 0.3);
    $elapsed = microtime(true) - $startedAt;
    fclose($pair[0]);
    fclose($pair[1]);
    vg_runtime_mutation_core_test(($result['state'] ?? null) === 'interrupted', 'Readiness polling did not observe the pending signal callback.');
    vg_runtime_mutation_core_test($elapsed < 0.3, 'Readiness polling blocked beyond its interruptible interval.');
});

$test('a stopped pre-ready process is killed within the bounded startup cleanup window', static function (): void {
    $process = proc_open([PHP_BINARY, '-r', 'usleep(10000000);'], [
        0 => ['file', '/dev/null', 'r'],
        1 => ['file', '/dev/null', 'w'],
        2 => ['file', '/dev/null', 'w'],
    ], $pipes);
    vg_runtime_mutation_core_test(is_resource($process), 'Could not start the stopped-process fixture.');
    $status = proc_get_status($process);
    $pid = is_int($status['pid'] ?? null) ? $status['pid'] : 0;
    vg_runtime_mutation_core_test($pid > 1 && posix_kill($pid, SIGSTOP), 'Could not stop the pre-ready process fixture.');
    $terminate = new ReflectionMethod(VgRuntimeMutationCore::class, 'terminateDirectProcess');
    $terminate->setAccessible(true);
    $startedAt = microtime(true);
    $terminated = $terminate->invoke(null, $process, null, false);
    $elapsed = microtime(true) - $startedAt;
    proc_close($process);
    vg_runtime_mutation_core_test($terminated === true, 'Stopped startup process survived TERM/KILL cleanup.');
    vg_runtime_mutation_core_test($elapsed < 2.5, 'Stopped startup process exceeded bounded TERM/KILL cleanup.');
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

$test('descendant scan classification keeps unknown distinct from empty', static function (): void {
    vg_runtime_mutation_core_test(
        VgRuntimeMutationCore::classifyDescendantScan([]) === 'empty',
        'An empty descendant scan was not classified as complete.'
    );
    vg_runtime_mutation_core_test(
        VgRuntimeMutationCore::classifyDescendantScan([123]) === 'present',
        'A non-empty descendant scan was not classified as active.'
    );
    vg_runtime_mutation_core_test(
        VgRuntimeMutationCore::classifyDescendantScan(null) === 'unknown',
        'An unavailable descendant scan was incorrectly treated as empty or active.'
    );
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
        $databaseLockFiles = vg_runtime_mutation_core_fixture_database_lock_paths($lockFile);
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
        while (! vg_runtime_mutation_core_fixture_has_both_keepers($marker) && microtime(true) < $deadline) {
            usleep(20000);
        }
        vg_runtime_mutation_core_test(vg_runtime_mutation_core_fixture_has_both_keepers($marker), 'Both database keepers were not registered.');
        posix_kill($coordinatorPid, SIGKILL);
        proc_close($process);

        $hostLock = VgRuntimeMutationCore::acquireFilesystemLock($lockFile);
        vg_runtime_mutation_core_test($hostLock === false, 'The database keeper did not retain the host lock after coordinator death.');
        $databaseLocks = vg_runtime_mutation_core_try_database_lock_pair($databaseLockFiles);
        vg_runtime_mutation_core_test($databaseLocks === [], 'A contender bypassed the database keepers while the mutator continued.');

        $deadline = microtime(true) + 4.0;
        while (trim((string) @file_get_contents($marker)) !== 'finished' && microtime(true) < $deadline) {
            usleep(50000);
        }
        vg_runtime_mutation_core_test(trim((string) @file_get_contents($marker)) === 'finished', 'Mutator did not finish after coordinator death.');

        $databaseAcquired = false;
        $deadline = microtime(true) + 3.0;
        while (microtime(true) < $deadline) {
            $databaseLocks = vg_runtime_mutation_core_try_database_lock_pair($databaseLockFiles);
            if ($databaseLocks !== []) {
                $databaseAcquired = true;
                vg_runtime_mutation_core_release_lock_handles($databaseLocks);
                break;
            }
            usleep(20000);
        }
        vg_runtime_mutation_core_test($databaseAcquired, 'The database keeper did not release after the mutator ended.');
        $hostLock = false;
        $deadline = microtime(true) + 3.0;
        while (! is_resource($hostLock) && microtime(true) < $deadline) {
            $hostLock = VgRuntimeMutationCore::acquireFilesystemLock($lockFile);
            if (! is_resource($hostLock)) {
                usleep(20000);
            }
        }
        vg_runtime_mutation_core_test(is_resource($hostLock), 'The host lock did not become available after mutation ended.');
        fclose($hostLock);
    });
});

$test('database keeper retains both locks for a proofless descendant after coordinator death', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $lockFile = $directory . '/target.lock';
        $databaseLockFiles = vg_runtime_mutation_core_fixture_database_lock_paths($lockFile);
        $marker = $directory . '/child.marker';
        $fixture = __DIR__ . '/../fixtures/runtime-mutation-parent-death.php';
        $process = proc_open(
            [PHP_BINARY, $fixture, $lockFile, $marker, 'spawn-proofless-descendant'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes
        );
        vg_runtime_mutation_core_test(is_resource($process), 'Descendant lifetime fixture did not start.');
        $coordinatorPid = proc_get_status($process)['pid'];
        $deadline = microtime(true) + 3.0;
        while ((! is_file($marker . '.descendant-started') || ! vg_runtime_mutation_core_fixture_has_both_keepers($marker)) && microtime(true) < $deadline) {
            usleep(20000);
        }
        vg_runtime_mutation_core_test(is_file($marker . '.descendant-started'), 'Proofless descendant did not start.');
        vg_runtime_mutation_core_test(vg_runtime_mutation_core_fixture_has_both_keepers($marker), 'Both database keepers were not registered.');
        posix_kill($coordinatorPid, SIGKILL);
        proc_close($process);
        file_put_contents($marker . '.parent-exit', 'exit');
        $childPid = (int) trim((string) file_get_contents($marker . '.parent-pid'));
        $deadline = microtime(true) + 3.0;
        do {
            $stat = @file_get_contents('/proc/' . $childPid . '/stat');
            $closingParenthesis = is_string($stat) ? strrpos($stat, ')') : false;
            $fields = is_int($closingParenthesis) ? preg_split('/\s+/', trim(substr($stat, $closingParenthesis + 1))) : [];
            $state = is_array($fields) ? ($fields[0] ?? null) : null;
            if (! is_string($stat) || in_array($state, ['Z', 'X'], true)) {
                break;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        vg_runtime_mutation_core_test(! is_string($stat) || in_array($state, ['Z', 'X'], true), 'Direct mutator did not exit after coordinator death.');

        $hostLock = VgRuntimeMutationCore::acquireFilesystemLock($lockFile);
        $databaseLocks = vg_runtime_mutation_core_try_database_lock_pair($databaseLockFiles);
        if (is_resource($hostLock)) {
            fclose($hostLock);
        }
        vg_runtime_mutation_core_release_lock_handles($databaseLocks);
        vg_runtime_mutation_core_test($hostLock === false, 'Host lock was released while its descendant could still write.');
        vg_runtime_mutation_core_test($databaseLocks === [], 'A database lock pair was acquired while its descendant could still write.');
        vg_runtime_mutation_core_test(! is_file($marker . '.late-write'), 'Descendant completed before lock retention was checked.');

        $deadline = microtime(true) + 4.0;
        while (! is_file($marker . '.late-write') && microtime(true) < $deadline) {
            usleep(20000);
        }
        vg_runtime_mutation_core_test(is_file($marker . '.late-write'), 'Proofless descendant did not finish its write.');
        $hostAcquired = false;
        $databaseAcquired = false;
        $deadline = microtime(true) + 3.0;
        while (microtime(true) < $deadline && (! $hostAcquired || ! $databaseAcquired)) {
            if (! $hostAcquired) {
                $handle = VgRuntimeMutationCore::acquireFilesystemLock($lockFile);
                if (is_resource($handle)) {
                    $hostAcquired = true;
                    fclose($handle);
                }
            }
            if (! $databaseAcquired) {
                $handles = vg_runtime_mutation_core_try_database_lock_pair($databaseLockFiles);
                if ($handles !== []) {
                    $databaseAcquired = true;
                    vg_runtime_mutation_core_release_lock_handles($handles);
                }
            }
            usleep(20000);
        }
        vg_runtime_mutation_core_test($hostAcquired && $databaseAcquired, 'Both locks did not release after the descendant exited.');
    });
});

$test('locks remain held for a detached descendant after its mutator exits', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $lockFile = $directory . '/target.lock';
        $databaseLockFiles = vg_runtime_mutation_core_fixture_database_lock_paths($lockFile);
        $marker = $directory . '/child.marker';
        $fixture = __DIR__ . '/../fixtures/runtime-mutation-parent-death.php';
        $process = proc_open(
            [PHP_BINARY, $fixture, $lockFile, $marker, 'spawn-detached-descendant'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes
        );
        vg_runtime_mutation_core_test(is_resource($process), 'Detached-descendant fixture did not start.');
        $coordinatorPid = proc_get_status($process)['pid'];
        $deadline = microtime(true) + 3.0;
        while ((! is_file($marker . '.descendant-started') || ! vg_runtime_mutation_core_fixture_has_both_keepers($marker)) && microtime(true) < $deadline) {
            usleep(10000);
        }
        vg_runtime_mutation_core_test(is_file($marker . '.descendant-started'), 'Detached descendant did not start.');
        vg_runtime_mutation_core_test(vg_runtime_mutation_core_fixture_has_both_keepers($marker), 'Both database keepers were not registered.');
        posix_kill($coordinatorPid, SIGKILL);
        proc_close($process);

        $deadline = microtime(true) + 2.0;
        while (! is_file($marker . '.late-write') && microtime(true) < $deadline) {
            $hostLock = VgRuntimeMutationCore::acquireFilesystemLock($lockFile);
            if (is_resource($hostLock)) {
                fclose($hostLock);
                break;
            }
            usleep(20000);
        }
        $hostLock = VgRuntimeMutationCore::acquireFilesystemLock($lockFile);
        $databaseLocks = vg_runtime_mutation_core_try_database_lock_pair($databaseLockFiles);
        if (is_resource($hostLock)) {
            fclose($hostLock);
        }
        vg_runtime_mutation_core_release_lock_handles($databaseLocks);
        $lateWritePerformed = is_file($marker . '.late-write');
        $hostLockHeld = $hostLock === false;
        $databaseLockHeld = $databaseLocks === [];
        file_put_contents($marker . '.descendant-release', 'release');
        vg_runtime_mutation_core_test($lateWritePerformed, 'Detached descendant did not perform its delayed write.');
        vg_runtime_mutation_core_test($hostLockHeld, 'Host lock was released before the detached descendant finished.');
        vg_runtime_mutation_core_test($databaseLockHeld, 'Database lock was released before the detached descendant finished.');

        $hostAcquired = false;
        $databaseAcquired = false;
        $deadline = microtime(true) + 3.0;
        while (microtime(true) < $deadline && (! $hostAcquired || ! $databaseAcquired)) {
            if (! $hostAcquired) {
                $handle = VgRuntimeMutationCore::acquireFilesystemLock($lockFile);
                if (is_resource($handle)) {
                    $hostAcquired = true;
                    fclose($handle);
                }
            }
            if (! $databaseAcquired) {
                $handles = vg_runtime_mutation_core_try_database_lock_pair($databaseLockFiles);
                if ($handles !== []) {
                    $databaseAcquired = true;
                    vg_runtime_mutation_core_release_lock_handles($handles);
                }
            }
            usleep(20000);
        }
        vg_runtime_mutation_core_test($hostAcquired && $databaseAcquired, 'Both locks did not release after the detached descendant exited.');
    });
});

$test('unexpected supervisor death retains both locks until the detached writer exits', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $lockFile = $directory . '/target.lock';
        $databaseLockFiles = vg_runtime_mutation_core_fixture_database_lock_paths($lockFile);
        $marker = $directory . '/child.marker';
        $fixture = __DIR__ . '/../fixtures/runtime-mutation-parent-death.php';
        $process = proc_open(
            [PHP_BINARY, $fixture, $lockFile, $marker, 'spawn-detached-descendant'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes
        );
        vg_runtime_mutation_core_test(is_resource($process), 'Detached-descendant failure fixture did not start.');
        $coordinatorPid = (int) (proc_get_status($process)['pid'] ?? 0);
        $keeperPids = ['a' => 0, 'b' => 0];
        $supervisorPid = 0;
        try {
            $deadline = microtime(true) + 3.0;
            while ((! is_file($marker . '.descendant-started') || ! vg_runtime_mutation_core_fixture_has_both_keepers($marker)) && microtime(true) < $deadline) {
                usleep(10000);
            }
            vg_runtime_mutation_core_test(is_file($marker . '.descendant-started'), 'Detached descendant did not start.');
            vg_runtime_mutation_core_test(vg_runtime_mutation_core_fixture_has_both_keepers($marker), 'Both database keepers were not registered.');
            foreach (['a', 'b'] as $slot) {
                $keeperPids[$slot] = (int) trim((string) file_get_contents($marker . '.keeper.' . $slot));
            }
            $children = vg_runtime_mutation_core_direct_children($coordinatorPid);
            foreach ($children as $childPid) {
                $commandLine = @file_get_contents("/proc/{$childPid}/cmdline");
                if (! in_array($childPid, $keeperPids, true) && is_string($commandLine) && str_contains($commandLine, 'runtime-mutation-exec.php')) {
                    $supervisorPid = $childPid;
                    break;
                }
            }
            vg_runtime_mutation_core_test($keeperPids['a'] > 1 && $keeperPids['b'] > 1 && $supervisorPid > 1, 'Could not identify both keepers and the child supervisor.');

            posix_kill($supervisorPid, SIGKILL);
            usleep(100000);
            posix_kill($coordinatorPid, SIGKILL);
            proc_close($process);
            $process = null;

            $deadline = microtime(true) + 3.0;
            while (! is_file($marker . '.late-write') && microtime(true) < $deadline) {
                usleep(20000);
            }
            vg_runtime_mutation_core_test(is_file($marker . '.late-write'), 'Detached writer did not reach its delayed write.');
            $hostLock = VgRuntimeMutationCore::acquireFilesystemLock($lockFile);
            $databaseLocks = vg_runtime_mutation_core_try_database_lock_pair($databaseLockFiles);
            if (is_resource($hostLock)) {
                fclose($hostLock);
            }
            vg_runtime_mutation_core_release_lock_handles($databaseLocks);
            $hostLockHeld = $hostLock === false;
            $databaseLockHeld = $databaseLocks === [];
            file_put_contents($marker . '.descendant-release', 'release');
            vg_runtime_mutation_core_test($hostLockHeld, 'Host lock was released after supervisor death while the detached writer remained active.');
            vg_runtime_mutation_core_test($databaseLockHeld, 'Database lock was released after supervisor death while the detached writer remained active.');
        } finally {
            file_put_contents($marker . '.descendant-release', 'release');
            if (is_resource($process)) {
                @posix_kill($coordinatorPid, SIGKILL);
                proc_close($process);
            }
            if ($supervisorPid > 1) {
                @posix_kill($supervisorPid, SIGKILL);
            }
            foreach ($keeperPids as $keeperPid) {
                if ($keeperPid > 1) {
                    @posix_kill($keeperPid, SIGKILL);
                }
            }
            $deadline = microtime(true) + 2.0;
            while (! is_file($marker . '.late-write') && microtime(true) < $deadline) {
                usleep(20000);
            }
        }
    });
});

$test('SIGTERM cancels a detached descendant and waits for its exit before releasing locks', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $lockFile = $directory . '/target.lock';
        $databaseLockFiles = vg_runtime_mutation_core_fixture_database_lock_paths($lockFile);
        $marker = $directory . '/child.marker';
        $fixture = __DIR__ . '/../fixtures/runtime-mutation-parent-death.php';
        $process = proc_open(
            [PHP_BINARY, $fixture, $lockFile, $marker, 'spawn-detached-descendant'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes
        );
        vg_runtime_mutation_core_test(is_resource($process), 'Detached-descendant cancellation fixture did not start.');
        $coordinatorPid = (int) (proc_get_status($process)['pid'] ?? 0);
        $startedAt = microtime(true);
        $deadline = $startedAt + 3.0;
        while ((! is_file($marker . '.descendant-started') || ! vg_runtime_mutation_core_fixture_has_both_keepers($marker)) && microtime(true) < $deadline) {
            usleep(10000);
        }
        vg_runtime_mutation_core_test(is_file($marker . '.descendant-started'), 'Detached descendant did not start.');
        posix_kill($coordinatorPid, SIGTERM);
        $status = proc_get_status($process);
        $deadline = microtime(true) + 3.0;
        while (($status['running'] ?? false) && microtime(true) < $deadline) {
            usleep(20000);
            $status = proc_get_status($process);
        }
        $elapsed = microtime(true) - $startedAt;
        proc_close($process);
        $lateWriteOccurred = is_file($marker . '.late-write');
        file_put_contents($marker . '.descendant-release', 'release');

        vg_runtime_mutation_core_test(! ($status['running'] ?? false), 'Coordinator did not finish bounded cancellation of the detached descendant.');
        vg_runtime_mutation_core_test($elapsed < 2.8, 'Detached descendant outlived the cancellation deadline.');
        vg_runtime_mutation_core_test(! $lateWriteOccurred, 'Detached descendant wrote after cancellation.');
        $hostLock = VgRuntimeMutationCore::acquireFilesystemLock($lockFile);
        $databaseLocks = vg_runtime_mutation_core_try_database_lock_pair($databaseLockFiles);
        vg_runtime_mutation_core_test(is_resource($hostLock) && $databaseLocks !== [], 'Locks did not release after every detached descendant exited.');
        fclose($hostLock);
        vg_runtime_mutation_core_release_lock_handles($databaseLocks);
    });
});

foreach (['a', 'b'] as $failedSlot) {
    $survivingSlot = $failedSlot === 'a' ? 'b' : 'a';
    $test('keeper ' . strtoupper($failedSlot) . ' death leaves the other database slot locked from an independent host', static function () use ($failedSlot, $survivingSlot): void {
        vg_runtime_mutation_core_with_temp_dir(static function (string $directory) use ($failedSlot, $survivingSlot): void {
            $lockFile = $directory . '/target.lock';
            $databaseLockFiles = vg_runtime_mutation_core_fixture_database_lock_paths($lockFile);
            $marker = $directory . '/child.marker';
            $fixture = __DIR__ . '/../fixtures/runtime-mutation-parent-death.php';
            $process = proc_open(
                [PHP_BINARY, $fixture, $lockFile, $marker, 'hold'],
                [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
                $pipes
            );
            vg_runtime_mutation_core_test(is_resource($process), 'Dual-keeper fixture did not start.');
            $remoteHostLock = null;
            $remoteLocks = [];
            try {
                $deadline = microtime(true) + 3.0;
                while ((! is_file($marker) || ! vg_runtime_mutation_core_fixture_has_both_keepers($marker)) && microtime(true) < $deadline) {
                    usleep(10000);
                }
                vg_runtime_mutation_core_test(trim((string) @file_get_contents($marker)) === 'started', 'Mutation child did not start.');
                vg_runtime_mutation_core_test(vg_runtime_mutation_core_fixture_has_both_keepers($marker), 'Both independent database keepers did not start.');

                $keeperPid = (int) trim((string) file_get_contents($marker . '.keeper.' . $failedSlot));
                vg_runtime_mutation_core_test($keeperPid > 1, 'Database keeper PID is invalid.');
                posix_kill($keeperPid, SIGKILL);
                file_put_contents($marker . '.keeper-' . $failedSlot . '-killed', 'yes');

                $failedSlotLock = null;
                $deadline = microtime(true) + 2.0;
                while (! is_resource($failedSlotLock) && microtime(true) < $deadline) {
                    $failedSlotLock = VgRuntimeMutationCore::acquireFilesystemLock($databaseLockFiles[$failedSlot]);
                    if (! is_resource($failedSlotLock)) {
                        usleep(10000);
                    }
                }
                vg_runtime_mutation_core_test(is_resource($failedSlotLock), 'Killed keeper did not release its own database slot.');
                flock($failedSlotLock, LOCK_UN);
                fclose($failedSlotLock);

                $remoteHostLock = VgRuntimeMutationCore::acquireFilesystemLock($lockFile . '.independent-host');
                vg_runtime_mutation_core_test(is_resource($remoteHostLock), 'Independent host fixture could not acquire its own host lock.');
                $deadline = microtime(true) + 2.0;
                while (microtime(true) < $deadline) {
                    $remoteLocks = [];
                    foreach (['a', 'b'] as $slot) {
                        $handle = VgRuntimeMutationCore::acquireFilesystemLock($databaseLockFiles[$slot]);
                        if (! is_resource($handle)) {
                            break;
                        }
                        $remoteLocks[$slot] = $handle;
                    }
                    if (($failedSlot === 'a' && isset($remoteLocks['a']))
                        || ($failedSlot === 'b' && $remoteLocks === [])) {
                        break;
                    }
                    vg_runtime_mutation_core_release_lock_handles($remoteLocks);
                    $remoteLocks = [];
                    usleep(10000);
                }

                $secondChildStarted = array_keys($remoteLocks) === ['a', 'b'];
                if ($secondChildStarted) {
                    file_put_contents($marker . '.second-child-started', 'started');
                }
                vg_runtime_mutation_core_test(! $secondChildStarted, 'A remote contender started while the original mutation child was alive.');
                vg_runtime_mutation_core_test(
                    ! isset($remoteLocks[$survivingSlot]),
                    'The surviving keeper did not retain its database lock after its peer died.'
                );
                vg_runtime_mutation_core_test(! is_file($marker . '.second-child-started'), 'A second mutation child started.');

                $deadline = microtime(true) + 4.0;
                while (trim((string) @file_get_contents($marker)) !== 'finished' && microtime(true) < $deadline) {
                    usleep(20000);
                }
                vg_runtime_mutation_core_test(trim((string) @file_get_contents($marker)) === 'finished', 'Original mutation child did not finish.');
            } finally {
                vg_runtime_mutation_core_release_lock_handles($remoteLocks);
                if (is_resource($remoteHostLock)) {
                    flock($remoteHostLock, LOCK_UN);
                    fclose($remoteHostLock);
                }
                if (is_resource($process)) {
                    proc_close($process);
                }
            }
        });
    });
}

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

$test('coordinator signal preserves the child normal exit status', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $lockFile = $directory . '/target.lock';
        $marker = $directory . '/child.marker';
        $fixture = __DIR__ . '/../fixtures/runtime-mutation-parent-death.php';
        $process = proc_open(
            [PHP_BINARY, $fixture, $lockFile, $marker, 'signal-exit-code'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes
        );
        vg_runtime_mutation_core_test(is_resource($process), 'Signal exit-code fixture did not start.');
        $coordinatorPid = proc_get_status($process)['pid'];
        $deadline = microtime(true) + 3.0;
        while ((! is_file($marker) || trim((string) file_get_contents($marker)) !== 'started') && microtime(true) < $deadline) {
            usleep(20000);
        }
        vg_runtime_mutation_core_test(is_file($marker) && trim((string) file_get_contents($marker)) === 'started', 'Signal exit-code child did not start.');
        posix_kill($coordinatorPid, SIGTERM);
        $deadline = microtime(true) + 4.0;
        do {
            $status = proc_get_status($process);
            if (! ($status['running'] ?? false)) {
                break;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        vg_runtime_mutation_core_test(! ($status['running'] ?? false), 'Coordinator did not finish after SIGTERM.');
        proc_close($process);
        vg_runtime_mutation_core_test(trim((string) @file_get_contents($marker . '.exit')) === '23', 'Coordinator replaced the child normal exit code with a signal-derived code.');
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
        $hostLock = VgRuntimeMutationCore::acquireFilesystemLock($directory . '/host.lock');
        try {
            VgRuntimeMutationDatabase::startKeepers(
                $target,
                $directory,
                VgRuntimeMutationCore::advisoryLockNames($target),
                $hostLock,
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
        } finally {
            flock($hostLock, LOCK_UN);
            fclose($hostLock);
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

$test('database keepers release in B then A order after positive acknowledgements', static function (): void {
    vg_runtime_mutation_core_with_temp_dir(static function (string $directory): void {
        $target = vg_runtime_mutation_core_fixture_target();
        $names = VgRuntimeMutationCore::advisoryLockNames($target);
        $keepers = [];
        foreach (['a', 'b'] as $slot) {
            $script = '$buffer = ""; while (!feof(STDIN)) { $chunk = fread(STDIN, 8192); if ($chunk !== false) { $buffer .= $chunk; } } file_put_contents($argv[1], $argv[2], FILE_APPEND); fwrite(STDOUT, "{\\"released\\":true}\\n");';
            $process = proc_open(
                [PHP_BINARY, '-r', $script, $directory . '/release-order', $slot],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']],
                $pipes
            );
            vg_runtime_mutation_core_test(is_resource($process), 'Fake database keeper did not start.');
            $keepers[$slot] = [
                'lifetime_handle' => $pipes[0],
                'keeper_stdout' => $pipes[1],
                'keeper_process' => $process,
            ];
        }
        VgRuntimeMutationDatabase::releaseKeepers([
            'locks' => ['a' => ['name' => $names['a']], 'b' => ['name' => $names['b']]],
            'keepers' => $keepers,
        ], $names);
        vg_runtime_mutation_core_test(file_get_contents($directory . '/release-order') === 'ba', 'Database keepers did not release in reverse acquisition order.');
    });
});

$test('database status reports an invalid root as an error state', static function (): void {
    $target = vg_runtime_mutation_core_fixture_target();
    $status = VgRuntimeMutationDatabase::statusLockOwners(
        $target,
        '/path/that/does/not/exist',
        VgRuntimeMutationCore::advisoryLockNames($target)
    );
    vg_runtime_mutation_core_test(
        $status === [
            'a' => ['state' => 'error', 'owner_connection_id' => null, 'error_code' => 'INVALID_WORDPRESS_ROOT'],
            'b' => ['state' => 'error', 'owner_connection_id' => null, 'error_code' => 'INVALID_WORDPRESS_ROOT'],
        ],
        'Database status did not report an explicit error state.'
    );
});

$test('database status passes only configuration and transport to the identity reader', static function (): void {
    $source = file_get_contents(__DIR__ . '/../../runtime-tools/runtime-mutation-database.php');
    vg_runtime_mutation_core_test(is_string($source), 'Database runtime source could not be read.');
    $status_start = strpos($source, 'public static function statusLockOwners(');
    $keeper_start = strpos($source, 'public static function keeperMain(');
    vg_runtime_mutation_core_test(
        is_int($status_start) && is_int($keeper_start) && $status_start < $keeper_start,
        'Database status implementation could not be isolated.'
    );
    $status_source = substr($source, $status_start, $keeper_start - $status_start);
    vg_runtime_mutation_core_test(
        preg_match('/readTargetIdentity\(\s*\$database\s*,\s*\$configuration\s*,\s*\$transport\s*\)/', $status_source) === 1,
        'The status path does not call the identity reader with its declared configuration and transport arguments.'
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
        $databaseLocks = vg_runtime_mutation_core_try_database_lock_pair(
            vg_runtime_mutation_core_fixture_database_lock_paths($lockFile)
        );
        vg_runtime_mutation_core_test($databaseLocks !== [], 'Both database keepers did not release after the child group exited.');
        vg_runtime_mutation_core_release_lock_handles($databaseLocks);
    });
});

echo "{$tests} runtime mutation core tests passed.\n";
