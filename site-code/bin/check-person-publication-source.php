<?php
declare(strict_types=1);

/** Test-only code is classified by repository-relative path. */
function vg_person_publication_source_is_test_only(string $path): bool
{
    $normalized = '/' . trim(str_replace('\\', '/', $path), '/') . '/';
    return str_contains($normalized, '/tests/')
        || str_contains($normalized, '/test/')
        || str_contains($normalized, '/fixtures/');
}

/** Third-party dependencies are outside repository ownership. */
function vg_person_publication_source_is_excluded(string $path): bool
{
    $normalized = '/' . trim(str_replace('\\', '/', $path), '/') . '/';
    return str_contains($normalized, '/vendor/')
        || str_contains($normalized, '/.git/')
        || str_contains($normalized, '/node_modules/');
}

/** @return list<string> */
function vg_person_publication_source_repository_php_files(string $repositoryRoot): array
{
    $root = realpath($repositoryRoot);
    if ($root === false) {
        return [];
    }
    $command = ['git', '-C', $root, 'ls-files', '-s', '-z'];
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
    $submodules = [];
    foreach (explode("\0", $stdout) as $entry) {
        if (preg_match('/^160000 [^ ]+ 0\t(.+)$/s', $entry, $matches) === 1) {
            $submodules[] = $matches[1];
        }
    }
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            static function (SplFileInfo $item) use ($root, $submodules): bool {
                $relative = ltrim(str_replace('\\', '/', substr($item->getPathname(), strlen($root))), '/');
                if ($item->isLink() || vg_person_publication_source_is_excluded($relative)) {
                    return false;
                }
                foreach ($submodules as $submodule) {
                    if ($relative === $submodule || str_starts_with($relative, $submodule . '/')) {
                        return false;
                    }
                }
                return true;
            }
        ),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iterator as $file) {
        if (! $file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    }
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
        if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
            $literal = substr($token[1], 1, -1);
            if (strtolower(stripslashes($literal)) === ('wp_publish_' . 'post')) {
                $lines[] = $token[2];
            }
            continue;
        }
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
        // Any executable identifier reference is forbidden, including imported aliases
        // and names passed to dynamic dispatch. Comments never appear in this token stream.
        $lines[] = $token[2];
    }
    return array_values(array_unique($lines));
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
        if (vg_person_publication_source_is_excluded($normalized)) {
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
            $errors[] = $normalized . ':' . $line . ': unauthorized wp_publish_post reference lacks an approved pre-publication validation boundary.';
        }
    }
    sort($errors);
    return ['valid' => $errors === [], 'files_scanned' => $scanned, 'errors' => $errors];
}

/** @return array{valid:bool,files_scanned:int,errors:list<string>} */
function vg_person_publication_source_check_repository(string $repositoryRoot): array
{
    $files = vg_person_publication_source_repository_php_files($repositoryRoot);
    if ($files === []) {
        return ['valid' => false, 'files_scanned' => 0, 'errors' => ['No repository-owned PHP files found.']];
    }
    $result = vg_person_publication_source_check_files($repositoryRoot, $files);
    $root = realpath($repositoryRoot);
    $patchPath = 'site-code/voelgoed-platform-core/integration.patch';
    $patchFile = $root === false ? false : $root . '/' . $patchPath;
    if (! is_string($patchFile) || ! is_file($patchFile)) {
        $result['valid'] = false;
        $result['errors'][] = 'Repository-owned executable integration.patch is missing.';
        sort($result['errors']);
        return $result;
    }
    $patch = file($patchFile, FILE_IGNORE_NEW_LINES);
    if (! is_array($patch)) {
        $result['valid'] = false;
        $result['errors'][] = 'Could not read repository-owned executable integration.patch.';
        sort($result['errors']);
        return $result;
    }
    foreach ($patch as $lineNumber => $line) {
        if (str_starts_with($line, '+++') || (! str_starts_with($line, '+') && ! str_starts_with($line, ' '))) continue;
        foreach (vg_person_publication_source_call_lines('<?php ' . substr($line, 1)) as $ignoredLine) {
            $result['errors'][] = sprintf(
                '%s:%d: unauthorized wp_publish_post reference lacks an approved pre-publication validation boundary.',
                $patchPath,
                $lineNumber + 1
            );
        }
    }
    sort($result['errors']);
    $result['valid'] = $result['errors'] === [];
    return $result;
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
