<?php
declare(strict_types=1);

/**
 * Test-only code is classified by repository-relative path. Production files have
 * no exemptions, and this guard scans only paths returned by git ls-files.
 */
function vg_person_publication_source_is_test_only(string $path): bool
{
    $normalized = '/' . trim(str_replace('\\', '/', $path), '/') . '/';
    return str_contains($normalized, '/tests/')
        || str_contains($normalized, '/test/')
        || str_contains($normalized, '/fixtures/');
}

/** @return list<string> */
function vg_person_publication_source_tracked_php_files(string $repositoryRoot): array
{
    $command = ['git', '-C', $repositoryRoot, 'ls-files', '-z', '--', '*.php'];
    $process = @proc_open($command, [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);
    if (! is_resource($process)) {
        return [];
    }
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0 || ! is_string($stdout)) {
        return [];
    }
    $files = array_values(array_filter(explode("\0", $stdout), static fn (string $path): bool => $path !== ''));
    sort($files);
    return $files;
}

/** @return list<int> */
function vg_person_publication_source_call_lines(string $source): array
{
    $tokens = token_get_all($source);
    $lines = [];
    for ($index = 0, $count = count($tokens); $index < $count; $index++) {
        $token = $tokens[$index];
        if (! is_array($token) || ! in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            continue;
        }
        $name = strtolower(ltrim($token[1], '\\'));
        if (strrpos($name, '\\') !== false) {
            $name = substr($name, strrpos($name, '\\') + 1);
        }
        if ($name !== ('wp_publish_' . 'post')) {
            continue;
        }
        for ($next = $index + 1; $next < $count; $next++) {
            $nextToken = $tokens[$next];
            if (is_array($nextToken) && in_array($nextToken[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if ($nextToken === '(') {
                $lines[] = $token[2];
            }
            break;
        }
    }
    return $lines;
}

/** @return array{valid:bool,files_scanned:int,errors:list<string>} */
function vg_person_publication_source_check_files(string $repositoryRoot, array $paths, bool $includeFixtureForTest = false): array
{
    $root = realpath($repositoryRoot);
    if ($root === false) {
        return ['valid' => false, 'files_scanned' => 0, 'errors' => ['Repository root does not exist.']];
    }
    $errors = [];
    $scanned = 0;
    foreach ($paths as $path) {
        if (! is_string($path) || $path === '' || str_starts_with($path, '/') || str_contains($path, "\0")) {
            $errors[] = 'Invalid repository-relative PHP path.';
            continue;
        }
        $normalized = str_replace('\\', '/', $path);
        if (preg_match('~(^|/)\.\.(/|$)~', $normalized) === 1) {
            $errors[] = 'Path escapes repository ownership: ' . $path . '.';
            continue;
        }
        if (vg_person_publication_source_is_test_only($normalized) && ! $includeFixtureForTest) {
            continue;
        }
        $absolute = realpath($root . '/' . $normalized);
        if ($absolute === false || ! str_starts_with($absolute, $root . DIRECTORY_SEPARATOR) || ! is_file($absolute)) {
            $errors[] = 'Repository PHP path is missing or outside repository ownership: ' . $path . '.';
            continue;
        }
        $source = file_get_contents($absolute);
        if (! is_string($source)) {
            $errors[] = 'Could not read repository PHP path: ' . $path . '.';
            continue;
        }
        $scanned++;
        foreach (vg_person_publication_source_call_lines($source) as $line) {
            $errors[] = $normalized . ':' . $line . ': direct publication call lacks an approved pre-publication validation boundary.';
        }
    }
    sort($errors);
    return ['valid' => $errors === [], 'files_scanned' => $scanned, 'errors' => $errors];
}

/** @return array{valid:bool,files_scanned:int,errors:list<string>} */
function vg_person_publication_source_check_repository(string $repositoryRoot): array
{
    $files = vg_person_publication_source_tracked_php_files($repositoryRoot);
    if ($files === []) {
        return ['valid' => false, 'files_scanned' => 0, 'errors' => ['No tracked repository PHP files found.']];
    }
    return vg_person_publication_source_check_files($repositoryRoot, $files);
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    $repositoryRoot = dirname(__DIR__, 2);
    $result = vg_person_publication_source_check_repository($repositoryRoot);
    if (! $result['valid']) {
        fwrite(STDERR, implode("\n", $result['errors']) . "\n");
        exit(1);
    }
    fwrite(STDOUT, sprintf("Person publication source policy passed: %d repository-owned production PHP files scanned.\n", $result['files_scanned']));
}
