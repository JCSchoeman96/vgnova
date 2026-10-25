<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || ! function_exists('posix_setsid') || ! function_exists('pcntl_exec')) {
    fwrite(STDERR, "Runtime mutation process support is unavailable.\n");
    exit(70);
}

$arguments = array_slice($argv, 1);
if ($arguments === [] || ! is_string($arguments[0]) || $arguments[0] === '') {
    fwrite(STDERR, "Runtime mutation command is invalid.\n");
    exit(70);
}

if (@posix_setsid() === -1) {
    fwrite(STDERR, "Runtime mutation process group could not be created.\n");
    exit(70);
}

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

$ready = @fopen('php://fd/5', 'w');
if (! is_resource($ready) || fwrite($ready, "ready\n") !== 6 || ! fflush($ready)) {
    if (is_resource($ready)) {
        fclose($ready);
    }
    fwrite(STDERR, "Runtime mutation process could not register its process group.\n");
    exit(70);
}
fclose($ready);

@pcntl_exec($executable, array_slice($arguments, 1), $environment);
fwrite(STDERR, "Runtime mutation command could not be started.\n");
exit(127);
