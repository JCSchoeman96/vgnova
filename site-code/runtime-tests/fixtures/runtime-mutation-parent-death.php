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
umask(0077);
$handle = fopen($argv[1], 'c+');
if ($handle === false || ! flock($handle, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "database lock acquisition failed\n");
    exit(2);
}
fwrite(STDOUT, "ready\n");
fflush(STDOUT);
while (! feof(STDIN)) {
    $chunk = fread(STDIN, 8192);
    if ($chunk === false || ($chunk === '' && ! feof(STDIN))) {
        usleep(10000);
    }
}
flock($handle, LOCK_UN);
fclose($handle);
PHP;
$keeperCommand = VgRuntimeMutationCore::descriptorSafeCommand(
    [PHP_BINARY, '-r', $keeperCode, $databaseLockFile],
    []
);
$keeper = proc_open($keeperCommand, [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
    3 => ['file', '/dev/null', 'r'],
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
    $keeperPipes[0]
);
file_put_contents($markerFile . '.exit', (string) $exitCode);

if (is_resource($keeperPipes[0])) {
    fclose($keeperPipes[0]);
}
$keeperStatusCode = proc_close($keeper);
fclose($keeperPipes[1]);
fclose($keeperPipes[2]);
flock($lockHandle, LOCK_UN);
fclose($lockHandle);
exit($keeperStatusCode === 0 ? $exitCode : 70);
