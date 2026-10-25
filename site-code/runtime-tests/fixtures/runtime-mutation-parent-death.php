<?php
declare(strict_types=1);

require_once __DIR__ . '/../../runtime-tools/runtime-mutation-core.php';

$lockFile = $argv[1] ?? '';
$markerFile = $argv[2] ?? '';
$mode = $argv[3] ?? 'hold';
$lockHandle = VgRuntimeMutationCore::acquireFilesystemLock($lockFile);
if (! is_resource($lockHandle)) {
    exit(72);
}

$databaseLockFile = $lockFile . '.database';
$keeperCode = <<<'PHP'
require $argv[2];
umask(0077);
$handle = fopen($argv[1], 'c+');
if ($handle === false || ! flock($handle, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "database lock acquisition failed\n");
    exit(2);
}
fwrite(STDOUT, "ready\n");
fflush(STDOUT);
$registration = fopen('php://fd/4', 'r');
$registrationLine = is_resource($registration) ? fgets($registration, 32) : false;
$registeredPid = is_string($registrationLine)
    && preg_match('/\A([1-9][0-9]{0,9})\n\z/', $registrationLine, $registrationMatches) === 1
    ? (int) $registrationMatches[1]
    : 0;
$registered = $registeredPid > 1;
if (is_resource($registration)) {
    fclose($registration);
}
if (! $registered) {
    exit(3);
}
$expectedAcknowledgement = 'VGNOVA_RUNTIME_MUTATION_SUPERVISOR_COMPLETE_V1:' . $registeredPid . "\n";
$acknowledgement = fgets(STDIN, 128);
if ($acknowledgement !== $expectedAcknowledgement) {
    while (true) {
        sleep(60);
    }
}
while (! feof(STDIN)) {
    $chunk = fread(STDIN, 8192);
    if ($chunk === false || $chunk !== '') {
        while (true) {
            sleep(60);
        }
    }
    if ($chunk === '' && ! feof(STDIN)) {
        usleep(10000);
    }
}
flock($handle, LOCK_UN);
fclose($handle);
PHP;
$keeperCommand = VgRuntimeMutationCore::descriptorSafeCommand(
    [PHP_BINARY, '-r', $keeperCode, $databaseLockFile, __DIR__ . '/../../runtime-tools/runtime-mutation-core.php'],
    [3, 4]
);
$keeper = proc_open($keeperCommand, [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
    3 => $lockHandle,
    4 => ['pipe', 'r'],
], $keeperPipes);
if (! is_resource($keeper)) {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(70);
}

$ready = fgets($keeperPipes[1]);
if ($ready !== "ready\n") {
    fclose($keeperPipes[0]);
    fclose($keeperPipes[1]);
    fclose($keeperPipes[2]);
    fclose($keeperPipes[4]);
    proc_close($keeper);
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(70);
}
$keeperStatus = proc_get_status($keeper);
file_put_contents($markerFile . '.keeper', (string) ($keeperStatus['pid'] ?? 0));

if ($mode === 'signal') {
    $childCode = <<<'PHP'
pcntl_async_signals(true);
pcntl_signal(SIGTERM, static function () use ($argv): void {
    file_put_contents($argv[1], 'terminated');
    exit(0);
});
file_put_contents($argv[1], 'started');
while (true) {
    usleep(50000);
}
PHP;
    $childArguments = [PHP_BINARY, '-r', $childCode, $markerFile];
} elseif ($mode === 'signal-exit-code') {
    $childCode = <<<'PHP'
pcntl_async_signals(true);
pcntl_signal(SIGTERM, static function (): void {
    exit(23);
});
file_put_contents($argv[1], 'started');
while (true) {
    usleep(50000);
}
PHP;
    $childArguments = [PHP_BINARY, '-r', $childCode, $markerFile];
} elseif ($mode === 'ignore-signal') {
    $childCode = <<<'PHP'
pcntl_async_signals(true);
pcntl_signal(SIGTERM, SIG_IGN);
file_put_contents($argv[1], 'started');
file_put_contents($argv[1] . '.pid', (string) getmypid());
while (true) {
    usleep(50000);
}
PHP;
    $childArguments = [PHP_BINARY, '-r', $childCode, $markerFile];
} elseif ($mode === 'close-host-lock') {
    $childArguments = [
        '/bin/sh', '-c', 'exec 3>&-; printf started > "$1"; sleep 2; printf finished > "$1"',
        'sh', $markerFile,
    ];
} elseif ($mode === 'spawn-proofless-descendant') {
    $childCode = <<<'PHP'
$descendantCode = 'file_put_contents($argv[1] . ".descendant-pid", (string) getmypid()); file_put_contents($argv[1] . ".descendant-started", "started"); usleep(1800000); file_put_contents($argv[1] . ".late-write", "late");';
$process = proc_open([PHP_BINARY, '-r', $descendantCode, $argv[1]], [
    0 => ['file', '/dev/null', 'r'],
    1 => ['file', '/dev/null', 'w'],
    2 => ['file', '/dev/null', 'w'],
], $pipes);
if (! is_resource($process)) {
    exit(91);
}
file_put_contents($argv[1], 'started');
file_put_contents($argv[1] . '.parent-pid', (string) getmypid());
while (! is_file($argv[1] . '.parent-exit')) {
    usleep(20000);
}
PHP;
    $childArguments = [
        '/bin/sh', '-c', 'exec 3>&- 4>&-; exec "$1" -r "$2" "$3"',
        'sh', PHP_BINARY, $childCode, $markerFile,
    ];
} elseif ($mode === 'spawn-detached-descendant') {
    $childCode = <<<'PHP'
$descendantPid = pcntl_fork();
if ($descendantPid === -1) {
    exit(92);
}
if ($descendantPid === 0) {
    if (posix_setsid() === -1) {
        exit(93);
    }
    file_put_contents($argv[1] . '.descendant-started', 'started');
    pcntl_exec('/bin/bash', [
        '-c',
        'exec 3>&- 4>&-; sleep 1.5; printf late > "$1"; for attempt in {1..500}; do if [ -e "$2" ]; then exit 0; fi; sleep 0.01; done; exit 95',
        'bash',
        $argv[1] . '.late-write',
        $argv[1] . '.descendant-release',
    ], ['PATH' => (string) getenv('PATH')]);
    exit(94);
}
file_put_contents($argv[1], 'started');
file_put_contents($argv[1] . '.parent-pid', (string) getmypid());
PHP;
    $childArguments = [PHP_BINARY, '-r', $childCode, $markerFile];
} else {
    $childCode = <<<'PHP'
file_put_contents($argv[1], 'started');
usleep(2500000);
file_put_contents($argv[1], 'finished');
PHP;
    $childArguments = [PHP_BINARY, '-r', $childCode, $markerFile];
}

$exitCode = VgRuntimeMutationCore::runChildCommand(
    $childArguments,
    ['PATH' => (string) getenv('PATH')],
    $lockHandle,
    $keeperPipes[0],
    $keeperPipes[4]
);
file_put_contents($markerFile . '.exit', (string) $exitCode);

if (is_resource($keeperPipes[0])) {
    fclose($keeperPipes[0]);
}
$keeperStatusCode = proc_close($keeper);
if (is_resource($keeperPipes[1])) {
    fclose($keeperPipes[1]);
}
if (is_resource($keeperPipes[2])) {
    fclose($keeperPipes[2]);
}
if (is_resource($keeperPipes[4])) {
    fclose($keeperPipes[4]);
}
flock($lockHandle, LOCK_UN);
fclose($lockHandle);
exit($keeperStatusCode === 0 ? $exitCode : 70);
