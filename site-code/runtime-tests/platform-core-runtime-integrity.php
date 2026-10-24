<?php
declare(strict_types=1);

/**
 * Compare a plugin directory with a published Platform Core manifest.
 *
 * @return array<string, mixed>
 */
function vg_product_contribution_qa_platform_core_integrity_report(
    string $plugin_root,
    string $manifest_path,
    int $expected_file_count,
    string $expected_tree_sha256,
    string $expected_manifest_sha256,
    ?callable $read_file = null
): array {
    $report = [
        'exact' => false,
        'expected_file_count' => $expected_file_count,
        'file_count' => 0,
        'missing' => [],
        'extra' => [],
        'mismatched' => [],
        'mode_mismatched' => [],
        'symlinks' => [],
        'unsupported' => [],
        'unreadable' => [],
        'unreadable_directories' => [],
        'directory_errors' => [],
        'manifest_errors' => [],
        'expected_tree_sha256' => $expected_tree_sha256,
        'runtime_tree_sha256' => null,
        'expected_manifest_sha256' => $expected_manifest_sha256,
        'observed_manifest_sha256' => null,
        'file_checks' => [],
    ];

    $manifest_bytes = @file_get_contents($manifest_path);
    if (! is_string($manifest_bytes)) {
        $report['manifest_errors'][] = 'Could not read the pinned Platform Core manifest.';
        return $report;
    }
    $report['observed_manifest_sha256'] = hash('sha256', $manifest_bytes);
    if (! hash_equals($expected_manifest_sha256, $report['observed_manifest_sha256'])) {
        $report['manifest_errors'][] = 'Pinned Platform Core manifest SHA-256 differs from the expected artifact manifest.';
        return $report;
    }

    try {
        $manifest = json_decode($manifest_bytes, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        $report['manifest_errors'][] = 'Pinned Platform Core manifest is invalid JSON: ' . $error->getMessage();
        return $report;
    }

    if (! is_array($manifest)
        || ($manifest['schema_version'] ?? null) !== 3
        || ($manifest['file_count'] ?? null) !== $expected_file_count
        || ($manifest['tree_sha256'] ?? null) !== $expected_tree_sha256
        || ! is_array($manifest['files'] ?? null)
        || ! array_is_list($manifest['files'])
        || count($manifest['files']) !== $expected_file_count
    ) {
        $report['manifest_errors'][] = 'Pinned Platform Core manifest metadata differs from the expected PE-005 content contract.';
        return $report;
    }

    $expected = [];
    foreach ($manifest['files'] as $record) {
        if (! is_array($record)) {
            $report['manifest_errors'][] = 'Pinned Platform Core manifest contains a non-object file record.';
            continue;
        }
        $relative_path = $record['path'] ?? null;
        $mode = $record['mode'] ?? null;
        $size = $record['size'] ?? null;
        $sha256 = $record['sha256'] ?? null;
        if (! is_string($relative_path)
            || $relative_path === ''
            || str_starts_with($relative_path, '/')
            || str_contains($relative_path, '\\')
            || str_contains($relative_path, "\0")
            || str_contains($relative_path, "\n")
            || str_contains($relative_path, "\r")
            || preg_match('//u', $relative_path) !== 1
            || preg_match('~(^|/)\.\.?(/|$)~', $relative_path) === 1
            || str_contains($relative_path, '//')
        ) {
            $report['manifest_errors'][] = 'Pinned Platform Core manifest contains an unsafe relative path.';
            continue;
        }
        if (isset($expected[$relative_path])) {
            $report['manifest_errors'][] = 'Pinned Platform Core manifest contains a duplicate path: ' . $relative_path;
            continue;
        }
        if (! in_array($mode, ['100644', '100755'], true)
            || ! is_int($size)
            || $size < 0
            || ! is_string($sha256)
            || preg_match('/\A[a-f0-9]{64}\z/D', $sha256) !== 1
        ) {
            $report['manifest_errors'][] = 'Pinned Platform Core manifest contains an invalid file record: ' . $relative_path;
            continue;
        }
        $expected[$relative_path] = [
            'mode' => $mode,
            'size' => $size,
            'sha256' => $sha256,
        ];
    }
    if ($report['manifest_errors'] !== [] || count($expected) !== $expected_file_count) {
        if ($report['manifest_errors'] === []) {
            $report['manifest_errors'][] = 'Pinned Platform Core manifest record count is invalid.';
        }
        return $report;
    }

    $root_stat = @lstat($plugin_root);
    if (! is_array($root_stat) || (($root_stat['mode'] & 0170000) !== 0040000)) {
        $report['directory_errors'][] = ['path' => $plugin_root, 'error' => 'Plugin root is missing, not a directory, or is a symlink.'];
        return $report;
    }
    if (($root_stat['mode'] & 0444) === 0 || ($root_stat['mode'] & 0111) === 0) {
        $report['unreadable_directories'][] = '.';
        return $report;
    }

    $actual = [];
    $read_file ??= static fn (string $path): string|false => @file_get_contents($path);
    $walk = static function (string $directory, string $relative_directory = '') use (&$walk, &$actual, &$report, $read_file): void {
        $entries = @scandir($directory);
        if (! is_array($entries)) {
            $report['directory_errors'][] = ['path' => $relative_directory === '' ? '.' : $relative_directory, 'error' => 'Could not enumerate directory.'];
            return;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $absolute_path = $directory . DIRECTORY_SEPARATOR . $entry;
            $relative_path = $relative_directory === '' ? $entry : $relative_directory . '/' . $entry;
            $stat = @lstat($absolute_path);
            if (! is_array($stat)) {
                $report['unreadable'][] = $relative_path;
                continue;
            }
            $type = $stat['mode'] & 0170000;
            if ($type === 0120000) {
                $report['symlinks'][] = $relative_path;
                continue;
            }
            if ($type === 0040000) {
                if (($stat['mode'] & 0444) === 0 || ($stat['mode'] & 0111) === 0) {
                    $report['unreadable_directories'][] = $relative_path;
                    continue;
                }
                $walk($absolute_path, $relative_path);
                continue;
            }
            if ($type !== 0100000) {
                $report['unsupported'][] = $relative_path;
                continue;
            }

            $mode = ($stat['mode'] & 0111) === 0 ? '100644' : '100755';
            $actual[$relative_path] = [
                'mode' => $mode,
                'size' => (int) $stat['size'],
                'sha256' => null,
            ];
            if (($stat['mode'] & 0444) === 0) {
                $report['unreadable'][] = $relative_path;
                continue;
            }
            try {
                $contents = $read_file($absolute_path);
            } catch (Throwable) {
                $contents = false;
            }
            if (! is_string($contents)) {
                $report['unreadable'][] = $relative_path;
                continue;
            }
            $actual[$relative_path]['size'] = strlen($contents);
            $actual[$relative_path]['sha256'] = hash('sha256', $contents);
        }
    };
    $walk(rtrim($plugin_root, DIRECTORY_SEPARATOR));

    $report['file_count'] = count($actual);
    $actual_paths = array_keys($actual);
    $expected_paths = array_keys($expected);
    usort($actual_paths, 'strcmp');
    usort($expected_paths, 'strcmp');
    $report['missing'] = array_values(array_diff($expected_paths, $actual_paths));
    $report['extra'] = array_values(array_diff($actual_paths, $expected_paths));

    foreach (array_intersect($actual_paths, $expected_paths) as $relative_path) {
        $expected_record = $expected[$relative_path];
        $actual_record = $actual[$relative_path];
        $content_matches = $actual_record['size'] === $expected_record['size']
            && $actual_record['sha256'] === $expected_record['sha256'];
        $mode_matches = $actual_record['mode'] === $expected_record['mode'];
        if (! $content_matches) {
            $report['mismatched'][] = $relative_path;
        }
        if (! $mode_matches) {
            $report['mode_mismatched'][] = $relative_path;
        }
        $report['file_checks'][] = [
            'path' => $relative_path,
            'expected_size' => $expected_record['size'],
            'runtime_size' => $actual_record['size'],
            'expected_sha256' => $expected_record['sha256'],
            'runtime_sha256' => $actual_record['sha256'],
            'expected_mode' => $expected_record['mode'],
            'runtime_mode' => $actual_record['mode'],
            'result' => $content_matches && $mode_matches ? 'PASS' : 'FAIL',
        ];
    }

    if ($report['unreadable'] === [] && $report['directory_errors'] === []) {
        $canonical_records = [];
        foreach ($actual as $relative_path => $record) {
            if (is_string($record['sha256'])) {
                $canonical_records[] = [$relative_path, $record['mode'], $record['sha256']];
            }
        }
        usort($canonical_records, static fn (array $left, array $right): int => strcmp($left[0], $right[0]));
        $canonical = '';
        foreach ($canonical_records as [$relative_path, $mode, $sha256]) {
            $canonical .= $mode . ' ' . $sha256 . ' ' . $relative_path . "\n";
        }
        $report['runtime_tree_sha256'] = hash('sha256', $canonical);
    }

    sort($report['symlinks'], SORT_STRING);
    sort($report['unsupported'], SORT_STRING);
    sort($report['unreadable'], SORT_STRING);
    sort($report['unreadable_directories'], SORT_STRING);
    usort($report['directory_errors'], static fn (array $left, array $right): int => strcmp($left['path'], $right['path']));

    $report['exact'] = $report['file_count'] === $expected_file_count
        && $report['missing'] === []
        && $report['extra'] === []
        && $report['mismatched'] === []
        && $report['mode_mismatched'] === []
        && $report['symlinks'] === []
        && $report['unsupported'] === []
        && $report['unreadable'] === []
        && $report['unreadable_directories'] === []
        && $report['directory_errors'] === []
        && $report['runtime_tree_sha256'] === $expected_tree_sha256;

    return $report;
}
