<?php
declare(strict_types=1);

require_once __DIR__ . '/runtime-mutation-core.php';

if (PHP_SAPI !== 'cli'
    || ! function_exists('posix_setsid')
    || ! function_exists('posix_setpgid')
    || ! function_exists('pcntl_fork')
    || ! function_exists('pcntl_exec')
    || ! function_exists('pcntl_waitpid')
    || ! function_exists('pcntl_signal')
    || ! function_exists('pcntl_async_signals')
    || ! function_exists('posix_kill')
    || ! class_exists(FFI::class)) {
    fwrite(STDERR, "Runtime mutation process support is unavailable.\n");
    exit(70);
}

try {
    $libc = FFI::cdef(
        'int prctl(int option, unsigned long arg2, unsigned long arg3, unsigned long arg4, unsigned long arg5); int close(int fd);',
        'libc.so.6'
    );
    if ($libc->prctl(36, 1, 0, 0, 0) !== 0) { // PR_SET_CHILD_SUBREAPER
        throw new RuntimeException('subreaper unavailable');
    }
} catch (Throwable) {
    fwrite(STDERR, "Runtime mutation descendant supervision is unavailable.\n");
    exit(70);
}

$completionChannel = ($argv[1] ?? null) === '--completion-channel';
$arguments = array_slice($argv, $completionChannel ? 2 : 1);
if ($arguments === [] || ! is_string($arguments[0]) || $arguments[0] === '') {
    fwrite(STDERR, "Runtime mutation command is invalid.\n");
    exit(70);
}

if (@posix_setsid() === -1) {
    fwrite(STDERR, "Runtime mutation process session could not be created.\n");
    exit(70);
}

$termination = ['signal' => null, 'started_at' => null];
pcntl_async_signals(true);
$requestTermination = static function (int $signal) use (&$termination): void {
    if ($termination['signal'] === null) {
        $termination['signal'] = $signal;
        $termination['started_at'] = microtime(true);
    }
};
pcntl_signal(SIGTERM, static fn (int $signal) => $requestTermination($signal));
pcntl_signal(SIGINT, static fn (int $signal) => $requestTermination($signal));

$environment = getenv();
if (! is_array($environment)) {
    $environment = [];
}

$executable = $arguments[0];
if (! str_contains($executable, DIRECTORY_SEPARATOR)) {
    $path = (string) ($environment['PATH'] ?? '');
    foreach (explode(PATH_SEPARATOR, $path) as $directory) {
        if ($directory === '') {
            continue;
        }
        $candidate = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $executable;
        if (is_file($candidate) && is_executable($candidate)) {
            $executable = $candidate;
            break;
        }
    }
}

$startGate = @fopen('php://fd/7', 'r');
if (! is_resource($startGate)) {
    fwrite(STDERR, "Runtime mutation start gate is unavailable.\n");
    exit(70);
}
$statusOutput = @fopen('php://fd/8', 'w');
if (! is_resource($statusOutput)) {
    fclose($startGate);
    fwrite(STDERR, "Runtime mutation child status channel is unavailable.\n");
    exit(70);
}

$childGate = @stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
if (! is_array($childGate) || count($childGate) !== 2) {
    fclose($startGate);
    fclose($statusOutput);
    fwrite(STDERR, "Runtime mutation child gate could not be created.\n");
    exit(70);
}

$targetPid = @pcntl_fork();
if ($targetPid === -1) {
    fclose($startGate);
    fclose($statusOutput);
    fclose($childGate[0]);
    fclose($childGate[1]);
    fwrite(STDERR, "Runtime mutation command could not be forked.\n");
    exit(70);
}

if ($targetPid === 0) {
    fclose($startGate);
    fclose($statusOutput);
    fclose($childGate[0]);
    if (@posix_setpgid(0, 0) === false) {
        exit(70);
    }

    $gate = fgets($childGate[1], 8);
    fclose($childGate[1]);
    if ($gate !== "go\n") {
        exit(70);
    }

    // The command keeps fd 3 to prove host-lock ownership. Close both keeper
    // channels and every supervisor control descriptor before exec.
    foreach ([4, 5, 6, 7, 8] as $descriptor) {
        $libc->close($descriptor);
    }

    $commandArguments = [
        '-c',
        'exec 4>&- 5>&- 6>&- 7>&- 8>&-; exec "$@"',
        'vgnova-runtime-command',
        $executable,
        ...array_slice($arguments, 1),
    ];
    @pcntl_exec('/bin/bash', $commandArguments, $environment);
    fwrite(STDERR, "Runtime mutation command could not be started.\n");
    exit(127);
}

fclose($childGate[1]);
$ready = @fopen('php://fd/6', 'w');
$readyLine = 'ready:' . $targetPid . "\n";
if (! is_resource($ready)
    || fwrite($ready, $readyLine) !== strlen($readyLine)
    || ! fflush($ready)) {
    if (is_resource($ready)) {
        fclose($ready);
    }
    fclose($startGate);
    fclose($childGate[0]);
    @posix_kill(-$targetPid, SIGKILL);
    exit(70);
}
fclose($ready);

$startSignal = fgets($startGate, 8);
fclose($startGate);
if ($startSignal === "go\n") {
    fwrite($childGate[0], "go\n");
    fflush($childGate[0]);
}
fclose($childGate[0]);

$targetExitCode = null;
$targetSignal = null;
$completionVerified = true;
$supervisorPid = getmypid();
$descendantPids = static function () use ($supervisorPid): ?array {
    if (! is_file('/proc/self/stat')) {
        return null;
    }
    $statFiles = @glob('/proc/[0-9]*/stat');
    if (! is_array($statFiles) || $statFiles === []) {
        return null;
    }
    $parentByPid = [];
    foreach ($statFiles as $statFile) {
        $stat = @file_get_contents($statFile);
        if (! is_string($stat)) {
            continue;
        }
        $closingParenthesis = strrpos($stat, ')');
        if (! is_int($closingParenthesis)) {
            continue;
        }
        $fields = preg_split('/\\s+/', trim(substr($stat, $closingParenthesis + 1)));
        $pid = (int) basename(dirname($statFile));
        if ($pid > 1 && is_array($fields) && isset($fields[1]) && ctype_digit($fields[1])) {
            $parentByPid[$pid] = (int) $fields[1];
        }
    }
    if (! isset($parentByPid[$supervisorPid])) {
        return null;
    }

    $descendants = [$supervisorPid => true];
    do {
        $changed = false;
        foreach ($parentByPid as $pid => $parentPid) {
            if (! isset($descendants[$pid]) && isset($descendants[$parentPid])) {
                $descendants[$pid] = true;
                $changed = true;
            }
        }
    } while ($changed);

    unset($descendants[$supervisorPid]);
    return array_map('intval', array_keys($descendants));
};
$signalDescendants = static function (int $signal) use ($descendantPids): bool {
    $pids = $descendantPids();
    if (! is_array($pids)) {
        return false;
    }
    foreach ($pids as $pid) {
        @posix_kill($pid, $signal);
    }
    return true;
};
while (true) {
    if (is_int($termination['signal']) && is_float($termination['started_at'])) {
        $signal = microtime(true) - $termination['started_at'] >= 1.0 ? SIGKILL : SIGTERM;
        $signalDescendants($signal);
    }
    $waitedPid = @pcntl_waitpid(-1, $waitStatus, WNOHANG);
    if ($waitedPid > 0) {
        if ($waitedPid === $targetPid) {
            if (pcntl_wifexited($waitStatus)) {
                $targetExitCode = pcntl_wexitstatus($waitStatus);
            } elseif (pcntl_wifsignaled($waitStatus)) {
                $targetSignal = pcntl_wtermsig($waitStatus);
            }
        }
        continue;
    }
    if ($waitedPid === -1) {
        if (is_int($termination['signal'])) {
            $remainingDescendants = $descendantPids();
            $descendantState = VgRuntimeMutationCore::classifyDescendantScan($remainingDescendants);
            if ($descendantState === 'unknown') {
                // The keeper must retain both locks unless the supervisor can prove
                // that every descendant has exited. EOF without an acknowledgement
                // makes the keeper fail closed instead of risking a late writer.
                $completionVerified = false;
                break;
            }
            if ($descendantState === 'present') {
                $signalDescendants(SIGKILL);
                usleep(20000);
                continue;
            }
        }
        break;
    }
    usleep(20000);
}

if (! $completionVerified) {
    $status = ['kind' => 'exit', 'exit_code' => 70];
    $supervisorExitCode = 70;
} elseif (is_int($targetExitCode)) {
    $status = ['kind' => 'exit', 'exit_code' => max(0, min(255, $targetExitCode))];
    $supervisorExitCode = $status['exit_code'];
} elseif (is_int($targetSignal)) {
    $status = ['kind' => 'signal', 'signal' => $targetSignal];
    $supervisorExitCode = max(0, min(255, 128 + $targetSignal));
} else {
    $status = ['kind' => 'exit', 'exit_code' => 70];
    $supervisorExitCode = 70;
}
fwrite($statusOutput, json_encode($status, JSON_THROW_ON_ERROR) . "\n");
fflush($statusOutput);
fclose($statusOutput);
$completion = $completionChannel && $completionVerified ? [
    @fopen('php://fd/4', 'w'),
    @fopen('php://fd/5', 'w'),
] : [];
foreach ($completion as $completionHandle) {
    if (! is_resource($completionHandle)) {
        continue;
    }
    $completionLine = 'VGNOVA_RUNTIME_MUTATION_SUPERVISOR_COMPLETE_V1:' . $supervisorPid . "\n";
    @fwrite($completionHandle, $completionLine);
    @fflush($completionHandle);
    fclose($completionHandle);
}
exit($supervisorExitCode);
