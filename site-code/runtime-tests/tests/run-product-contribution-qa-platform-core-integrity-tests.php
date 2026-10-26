<?php
declare(strict_types=1);

define('VG_PRODUCT_CONTRIBUTION_QA_LIBRARY_ONLY', true);
define('ABSPATH', __DIR__ . '/');

$integrity_path = dirname(__DIR__) . '/platform-core-runtime-integrity.php';
if (! is_file($integrity_path)) {
    fwrite(STDERR, "FAIL Platform Core integrity helper is missing.\n");
    exit(1);
}

require_once $integrity_path;
require_once dirname(__DIR__) . '/product-contribution-qa.php';

/** @param array<string, mixed> $report */
function vg_pc_integrity_test_assert(bool $condition, string $message, array $report = []): void
{
    if (! $condition) {
        throw new RuntimeException($message . ($report === [] ? '' : ': ' . json_encode($report, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)));
    }
}

function vg_pc_integrity_test_remove_tree(string $path): void
{
    $stat = @lstat($path);
    if (! is_array($stat)) {
        return;
    }
    if (($stat['mode'] & 0170000) !== 0040000) {
        if (! unlink($path)) {
            throw new RuntimeException('Could not remove temporary file: ' . $path);
        }
        return;
    }
    $entries = scandir($path);
    if (! is_array($entries)) {
        throw new RuntimeException('Could not enumerate temporary directory: ' . $path);
    }
    foreach ($entries as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            vg_pc_integrity_test_remove_tree($path . DIRECTORY_SEPARATOR . $entry);
        }
    }
    if (! rmdir($path)) {
        throw new RuntimeException('Could not remove temporary directory: ' . $path);
    }
}

/** @param array<string, string> $contents @param array<string, string> $modes */
function vg_pc_integrity_test_fixture(array $contents = [], array $modes = []): array
{
    $root = sys_get_temp_dir() . '/vg-pc-integrity-' . bin2hex(random_bytes(8));
    $plugin_root = $root . '/plugin';
    if (! mkdir($plugin_root, 0700, true)) {
        throw new RuntimeException('Could not create a temporary plugin root.');
    }
    if ($contents === []) {
        $contents = [
            'bin/check.sh' => "#!/bin/sh\nexit 0\n",
            'src/module.php' => "<?php\nreturn true;\n",
        ];
    }
    if ($modes === []) {
        $modes = ['bin/check.sh' => '100755', 'src/module.php' => '100644'];
    }

    $records = [];
    foreach ($contents as $relative_path => $bytes) {
        $absolute_path = $plugin_root . '/' . $relative_path;
        $directory = dirname($absolute_path);
        if (! is_dir($directory) && ! mkdir($directory, 0700, true)) {
            throw new RuntimeException('Could not create a temporary fixture subdirectory.');
        }
        if (file_put_contents($absolute_path, $bytes) !== strlen($bytes)) {
            throw new RuntimeException('Could not write a temporary fixture file.');
        }
        $mode = $modes[$relative_path] ?? '100644';
        if (! chmod($absolute_path, $mode === '100755' ? 0755 : 0644)) {
            throw new RuntimeException('Could not set a temporary fixture file mode.');
        }
        $records[] = [
            'path' => $relative_path,
            'mode' => $mode,
            'size' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
        ];
    }

    usort($records, static fn (array $left, array $right): int => strcmp($left['path'], $right['path']));
    $canonical = '';
    foreach ($records as $record) {
        $canonical .= $record['mode'] . ' ' . $record['sha256'] . ' ' . $record['path'] . "\n";
    }
    $source_commit = str_repeat('1', 40);
    $manifest = [
        'schema_version' => 3,
        'source_commit' => $source_commit,
        'file_count' => count($records),
        'tree_sha256' => hash('sha256', $canonical),
        'archive_sha256' => str_repeat('a', 64),
        'files' => $records,
    ];
    $manifest_bytes = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    $manifest_path = $root . '/manifest.json';
    if (file_put_contents($manifest_path, $manifest_bytes) !== strlen($manifest_bytes)) {
        throw new RuntimeException('Could not write a temporary manifest.');
    }

    return [
        'root' => $root,
        'plugin_root' => $plugin_root,
        'manifest_path' => $manifest_path,
        'manifest' => $manifest,
        'authority' => [
            'artifact_name' => 'voelgoed-platform-core-' . $source_commit,
            'source_commit' => $source_commit,
            'schema_version' => 3,
            'file_count' => count($records),
            'tree_sha256' => $manifest['tree_sha256'],
            'archive_sha256' => $manifest['archive_sha256'],
            'manifest_sha256' => hash('sha256', $manifest_bytes),
        ],
    ];
}

/** @param array<string, mixed> $fixture */
function vg_pc_integrity_test_write_manifest(array &$fixture, array $manifest): void
{
    $manifest_bytes = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    if (file_put_contents($fixture['manifest_path'], $manifest_bytes) !== strlen($manifest_bytes)) {
        throw new RuntimeException('Could not replace a temporary manifest.');
    }
    $fixture['authority']['manifest_sha256'] = hash('sha256', $manifest_bytes);
}

/** @param array<string, mixed> $fixture @return array<string, mixed> */
function vg_pc_integrity_test_report(array $fixture, ?callable $read_file = null): array
{
    return vg_product_contribution_qa_platform_core_integrity_report(
        $fixture['plugin_root'],
        $fixture['manifest_path'],
        $fixture['authority'],
        $read_file
    );
}

$passed = 0;
$total = 26;
$run_case = static function (string $name, callable $test) use (&$passed): void {
    try {
        $test();
        ++$passed;
        fwrite(STDOUT, 'PASS ' . $name . ".\n");
    } catch (Throwable $error) {
        fwrite(STDERR, 'FAIL ' . $name . ': ' . $error->getMessage() . "\n");
    }
};

$run_case('exact temporary tree is accepted with the canonical tree hash', static function (): void {
    $fixture = vg_pc_integrity_test_fixture();
    try {
        $report = vg_pc_integrity_test_report($fixture);
        vg_pc_integrity_test_assert($report['exact'] === true, 'Exact temporary plugin tree was rejected', $report);
        vg_pc_integrity_test_assert($report['runtime_tree_sha256'] === $fixture['authority']['tree_sha256'], 'Canonical runtime tree hash differs', $report);
        vg_pc_integrity_test_assert($report['file_count'] === 2, 'Expected two temporary fixture files', $report);
    } finally {
        vg_pc_integrity_test_remove_tree($fixture['root']);
    }
});

$run_case('published manifest fixture is pinned to the downloaded artifact bytes', static function (): void {
    $path = dirname(__DIR__) . '/fixtures/voelgoed-platform-core-pe005.manifest.json';
    $bytes = file_get_contents($path);
    vg_pc_integrity_test_assert(is_string($bytes), 'Published manifest fixture could not be read');
    vg_pc_integrity_test_assert(hash('sha256', $bytes) === 'ceba4caa9394ca3dc6dedca2794199c921fed85118b9d4a4d6606e711a8d12a8', 'Published manifest fixture digest differs');
    $manifest = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
    $runtime_guard_paths = array_values(array_filter(
        array_column($manifest['files'], 'path'),
        static fn (string $path): bool => $path === 'includes/Runtime/RuntimeMutationSession.php'
    ));
    vg_pc_integrity_test_assert(
        $manifest['schema_version'] === 3
            && $manifest['source_commit'] === 'ab7e7d853772fb920237e47d8fa83c72ed389fba'
            && $manifest['file_count'] === 55
            && $manifest['tree_sha256'] === '5816b80ac4355782523aa4baa82bd9e55dd87326f15972f287ff1afa7567482a'
            && $manifest['archive_sha256'] === '22d998019e3fe3d93d675250a26eba5be3dbc248badd9be4d7507fbdf9cffdb9'
            && $runtime_guard_paths === ['includes/Runtime/RuntimeMutationSession.php'],
        'Published manifest fixture does not match the PE-008 authority and required session guard'
    );
    vg_pc_integrity_test_assert(
        VG_PRODUCT_CONTRIBUTION_QA_PLATFORM_CORE_AUTHORITY === [
            'artifact_name' => 'voelgoed-platform-core-ab7e7d853772fb920237e47d8fa83c72ed389fba',
            'source_commit' => 'ab7e7d853772fb920237e47d8fa83c72ed389fba',
            'schema_version' => 3,
            'file_count' => 55,
            'tree_sha256' => '5816b80ac4355782523aa4baa82bd9e55dd87326f15972f287ff1afa7567482a',
            'archive_sha256' => '22d998019e3fe3d93d675250a26eba5be3dbc248badd9be4d7507fbdf9cffdb9',
            'manifest_sha256' => 'ceba4caa9394ca3dc6dedca2794199c921fed85118b9d4a4d6606e711a8d12a8',
        ],
        'Product Contribution QA authority constant differs from the published artifact'
    );
});

$run_case('previous 54-file authority is preserved byte-for-byte for rollback classification', static function (): void {
    $path = dirname(__DIR__) . '/fixtures/voelgoed-platform-core-pe008-old.manifest.json';
    $bytes = file_get_contents($path);
    vg_pc_integrity_test_assert(is_string($bytes), 'Previous published manifest could not be read');
    vg_pc_integrity_test_assert(hash('sha256', $bytes) === 'c87d339ebe83203a82a3f02b27de9854be9e360835da9893077af9c9c318c1ee', 'Previous manifest digest differs');
    $manifest = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
    vg_pc_integrity_test_assert(
        $manifest['source_commit'] === '7af938ac6f4422b1fa779b52444121e7a9293995'
            && $manifest['file_count'] === 54
            && $manifest['tree_sha256'] === '856a3e5978559bfb2572b4b1ff4b34df916743f9e72d6b18220ee64e98af5f56',
        'Previous manifest no longer identifies the exact predeployment authority'
    );
});

$run_case('previous 54-file authority rejects the exact published 55-file manifest', static function (): void {
    $fixture = vg_pc_integrity_test_fixture();
    try {
        $old_authority = [
            'artifact_name' => 'voelgoed-platform-core-7af938ac6f4422b1fa779b52444121e7a9293995',
            'source_commit' => '7af938ac6f4422b1fa779b52444121e7a9293995',
            'schema_version' => 3,
            'file_count' => 54,
            'tree_sha256' => '856a3e5978559bfb2572b4b1ff4b34df916743f9e72d6b18220ee64e98af5f56',
            'archive_sha256' => '9d5fd288025703fe4f71ed1f3ee264cd3fc9c0b61cf74d137b40e941bc2e2243',
            'manifest_sha256' => 'c87d339ebe83203a82a3f02b27de9854be9e360835da9893077af9c9c318c1ee',
        ];
        $report = vg_product_contribution_qa_platform_core_integrity_report(
            $fixture['plugin_root'],
            dirname(__DIR__) . '/fixtures/voelgoed-platform-core-pe005.manifest.json',
            $old_authority
        );
        vg_pc_integrity_test_assert(
            $report['exact'] === false
                && $report['expected_file_count'] === 54
                && $report['observed_manifest_sha256'] === 'ceba4caa9394ca3dc6dedca2794199c921fed85118b9d4a4d6606e711a8d12a8'
                && in_array('Pinned Platform Core manifest SHA-256 differs from the expected artifact manifest.', $report['manifest_errors'], true),
            'Previous 54-file authority accepted the exact published 55-file manifest',
            $report
        );
    } finally {
        vg_pc_integrity_test_remove_tree($fixture['root']);
    }
});

$run_case('modified file content is rejected', static function (): void {
    $fixture = vg_pc_integrity_test_fixture();
    try {
        file_put_contents($fixture['plugin_root'] . '/src/module.php', "<?php\nreturn false;\n");
        $report = vg_pc_integrity_test_report($fixture);
        vg_pc_integrity_test_assert($report['exact'] === false && $report['mismatched'] === ['src/module.php'], 'Modified file was not rejected', $report);
    } finally {
        vg_pc_integrity_test_remove_tree($fixture['root']);
    }
});

$run_case('missing file is rejected', static function (): void {
    $fixture = vg_pc_integrity_test_fixture();
    try {
        unlink($fixture['plugin_root'] . '/src/module.php');
        $report = vg_pc_integrity_test_report($fixture);
        vg_pc_integrity_test_assert($report['exact'] === false && $report['missing'] === ['src/module.php'], 'Missing file was not reported', $report);
    } finally {
        vg_pc_integrity_test_remove_tree($fixture['root']);
    }
});

$run_case('extra file is rejected', static function (): void {
    $fixture = vg_pc_integrity_test_fixture();
    try {
        file_put_contents($fixture['plugin_root'] . '/extra.txt', 'extra');
        $report = vg_pc_integrity_test_report($fixture);
        vg_pc_integrity_test_assert($report['exact'] === false && $report['extra'] === ['extra.txt'], 'Extra file was not reported', $report);
    } finally {
        vg_pc_integrity_test_remove_tree($fixture['root']);
    }
});

$run_case('unexpected executable bit is rejected', static function (): void {
    $fixture = vg_pc_integrity_test_fixture();
    try {
        chmod($fixture['plugin_root'] . '/src/module.php', 0755);
        $report = vg_pc_integrity_test_report($fixture);
        vg_pc_integrity_test_assert($report['exact'] === false && $report['mode_mismatched'] === ['src/module.php'], 'Unexpected executable bit was not reported', $report);
    } finally {
        vg_pc_integrity_test_remove_tree($fixture['root']);
    }
});

$run_case('missing executable bit is rejected', static function (): void {
    $fixture = vg_pc_integrity_test_fixture();
    try {
        chmod($fixture['plugin_root'] . '/bin/check.sh', 0644);
        $report = vg_pc_integrity_test_report($fixture);
        vg_pc_integrity_test_assert($report['exact'] === false && $report['mode_mismatched'] === ['bin/check.sh'], 'Missing executable bit was not reported', $report);
    } finally {
        vg_pc_integrity_test_remove_tree($fixture['root']);
    }
});

$run_case('symlink is rejected without following it', static function (): void {
    $fixture = vg_pc_integrity_test_fixture();
    try {
        symlink('src/module.php', $fixture['plugin_root'] . '/linked.php');
        $report = vg_pc_integrity_test_report($fixture);
        vg_pc_integrity_test_assert($report['exact'] === false && $report['symlinks'] === ['linked.php'], 'Symlink was not rejected', $report);
    } finally {
        vg_pc_integrity_test_remove_tree($fixture['root']);
    }
});

$run_case('symlinked plugin root with trailing slash is rejected', static function (): void {
    $fixture = vg_pc_integrity_test_fixture();
    try {
        $linked_root = $fixture['root'] . '/linked-plugin';
        symlink($fixture['plugin_root'], $linked_root);
        $report = vg_product_contribution_qa_platform_core_integrity_report(
            $linked_root . '/',
            $fixture['manifest_path'],
            $fixture['authority']
        );
        vg_pc_integrity_test_assert(
            $report['exact'] === false && $report['directory_errors'] !== [],
            'Trailing slash allowed a symlinked plugin root',
            $report
        );
    } finally {
        vg_pc_integrity_test_remove_tree($fixture['root']);
    }
});

$run_case('empty and filesystem-root plugin paths are rejected before traversal', static function (): void {
    $fixture = vg_pc_integrity_test_fixture();
    try {
        foreach (['', DIRECTORY_SEPARATOR] as $invalid_root) {
            $report = vg_product_contribution_qa_platform_core_integrity_report(
                $invalid_root,
                $fixture['manifest_path'],
                $fixture['authority']
            );
            vg_pc_integrity_test_assert(
                $report['exact'] === false && $report['directory_errors'] !== [],
                'Invalid plugin root path did not fail before traversal',
                $report
            );
        }
    } finally {
        vg_pc_integrity_test_remove_tree($fixture['root']);
    }
});

$run_case('unsupported filesystem object is rejected', static function (): void {
    $fixture = vg_pc_integrity_test_fixture();
    try {
        if (! function_exists('posix_mkfifo') || ! posix_mkfifo($fixture['plugin_root'] . '/pipe', 0600)) {
            throw new RuntimeException('This PHP runtime cannot create the FIFO fixture required by this test.');
        }
        $report = vg_pc_integrity_test_report($fixture);
        vg_pc_integrity_test_assert($report['exact'] === false && $report['unsupported'] === ['pipe'], 'Unsupported filesystem object was not rejected', $report);
    } finally {
        vg_pc_integrity_test_remove_tree($fixture['root']);
    }
});

$run_case('unreadable file read failure is rejected', static function (): void {
    $fixture = vg_pc_integrity_test_fixture();
    try {
        $report = vg_pc_integrity_test_report($fixture, static function (string $path): string|false {
            return str_ends_with($path, '/src/module.php') ? false : file_get_contents($path);
        });
        vg_pc_integrity_test_assert($report['exact'] === false && $report['unreadable'] === ['src/module.php'], 'Injected read failure was not rejected', $report);
    } finally {
        vg_pc_integrity_test_remove_tree($fixture['root']);
    }
});

$run_case('malformed manifest is rejected', static function (): void {
    $fixture = vg_pc_integrity_test_fixture();
    try {
        file_put_contents($fixture['manifest_path'], '{invalid');
        $fixture['authority']['manifest_sha256'] = hash('sha256', '{invalid');
        $report = vg_pc_integrity_test_report($fixture);
        vg_pc_integrity_test_assert($report['exact'] === false && $report['manifest_errors'] !== [], 'Malformed manifest was not rejected', $report);
    } finally {
        vg_pc_integrity_test_remove_tree($fixture['root']);
    }
});

$run_case('duplicate manifest path is rejected', static function (): void {
    $fixture = vg_pc_integrity_test_fixture();
    try {
        $manifest = $fixture['manifest'];
        $manifest['files'][1]['path'] = $manifest['files'][0]['path'];
        vg_pc_integrity_test_write_manifest($fixture, $manifest);
        $report = vg_pc_integrity_test_report($fixture);
        vg_pc_integrity_test_assert($report['exact'] === false && $report['manifest_errors'] !== [], 'Duplicate manifest path was not rejected', $report);
    } finally {
        vg_pc_integrity_test_remove_tree($fixture['root']);
    }
});

$run_case('unsafe manifest path is rejected', static function (): void {
    $fixture = vg_pc_integrity_test_fixture();
    try {
        $manifest = $fixture['manifest'];
        $manifest['files'][0]['path'] = '../escape';
        vg_pc_integrity_test_write_manifest($fixture, $manifest);
        $report = vg_pc_integrity_test_report($fixture);
        vg_pc_integrity_test_assert($report['exact'] === false && $report['manifest_errors'] !== [], 'Unsafe manifest path was not rejected', $report);
    } finally {
        vg_pc_integrity_test_remove_tree($fixture['root']);
    }
});

$run_case('wrong source commit is rejected', static function (): void {
    $fixture = vg_pc_integrity_test_fixture();
    try {
        $manifest = $fixture['manifest'];
        $manifest['source_commit'] = str_repeat('2', 40);
        vg_pc_integrity_test_write_manifest($fixture, $manifest);
        $report = vg_pc_integrity_test_report($fixture);
        vg_pc_integrity_test_assert($report['exact'] === false && $report['manifest_errors'] !== [], 'Wrong source commit was not rejected', $report);
    } finally {
        vg_pc_integrity_test_remove_tree($fixture['root']);
    }
});

$run_case('wrong artifact name is rejected', static function (): void {
    $fixture = vg_pc_integrity_test_fixture();
    try {
        $fixture['authority']['artifact_name'] = 'voelgoed-platform-core-wrong';
        $report = vg_pc_integrity_test_report($fixture);
        vg_pc_integrity_test_assert($report['exact'] === false && $report['manifest_errors'] !== [], 'Wrong artifact name was not rejected', $report);
    } finally {
        vg_pc_integrity_test_remove_tree($fixture['root']);
    }
});

$run_case('wrong manifest digest is rejected', static function (): void {
    $fixture = vg_pc_integrity_test_fixture();
    try {
        $fixture['authority']['manifest_sha256'] = str_repeat('f', 64);
        $report = vg_pc_integrity_test_report($fixture);
        vg_pc_integrity_test_assert($report['exact'] === false && $report['manifest_errors'] !== [], 'Wrong manifest digest was not rejected', $report);
    } finally {
        vg_pc_integrity_test_remove_tree($fixture['root']);
    }
});

$run_case('wrong schema is rejected', static function (): void {
    $fixture = vg_pc_integrity_test_fixture();
    try {
        $manifest = $fixture['manifest'];
        $manifest['schema_version'] = 2;
        vg_pc_integrity_test_write_manifest($fixture, $manifest);
        $report = vg_pc_integrity_test_report($fixture);
        vg_pc_integrity_test_assert($report['exact'] === false && $report['manifest_errors'] !== [], 'Wrong schema was not rejected', $report);
    } finally {
        vg_pc_integrity_test_remove_tree($fixture['root']);
    }
});

$run_case('invalid Git file mode is rejected', static function (): void {
    $fixture = vg_pc_integrity_test_fixture();
    try {
        $manifest = $fixture['manifest'];
        $manifest['files'][1]['mode'] = '100600';
        vg_pc_integrity_test_write_manifest($fixture, $manifest);
        $report = vg_pc_integrity_test_report($fixture);
        vg_pc_integrity_test_assert($report['exact'] === false && $report['manifest_errors'] !== [], 'Invalid file mode was not rejected', $report);
    } finally {
        vg_pc_integrity_test_remove_tree($fixture['root']);
    }
});

$run_case('wrong archive digest is rejected', static function (): void {
    $fixture = vg_pc_integrity_test_fixture();
    try {
        $fixture['authority']['archive_sha256'] = str_repeat('b', 64);
        $report = vg_pc_integrity_test_report($fixture);
        vg_pc_integrity_test_assert($report['exact'] === false && $report['manifest_errors'] !== [], 'Wrong archive digest was not rejected', $report);
    } finally {
        vg_pc_integrity_test_remove_tree($fixture['root']);
    }
});

$run_case('wrong canonical tree hash is rejected', static function (): void {
    $fixture = vg_pc_integrity_test_fixture();
    try {
        $fixture['authority']['tree_sha256'] = str_repeat('c', 64);
        $report = vg_pc_integrity_test_report($fixture);
        vg_pc_integrity_test_assert($report['exact'] === false && $report['manifest_errors'] !== [], 'Wrong canonical tree hash was not rejected', $report);
    } finally {
        vg_pc_integrity_test_remove_tree($fixture['root']);
    }
});

$run_case('failed integrity preflight cannot open mutation or cleanup window', static function (): void {
    $fixture = vg_pc_integrity_test_fixture();
    try {
        if (! function_exists('vg_product_contribution_qa_assert_platform_core_integrity')) {
            throw new RuntimeException('The guarded preflight integrity assertion is missing.');
        }
        file_put_contents($fixture['plugin_root'] . '/src/module.php', "<?php\nreturn false;\n");
        $body_calls = 0;
        $cleanup_calls = 0;
        $result = vg_product_contribution_qa_guarded_run(
            static function () use ($fixture): void {
                $report = vg_pc_integrity_test_report($fixture);
                vg_product_contribution_qa_assert_platform_core_integrity($report);
            },
            static function () use (&$body_calls): void {
                ++$body_calls;
            },
            static function () use (&$cleanup_calls): void {
                ++$cleanup_calls;
            }
        );
        vg_pc_integrity_test_assert(
            $result['mutation_started'] === false
                && $result['cleanup_attempted'] === false
                && $body_calls === 0
                && $cleanup_calls === 0,
            'Failed integrity preflight entered the mutation or cleanup window',
            $result
        );
    } finally {
        vg_pc_integrity_test_remove_tree($fixture['root']);
    }
});

$run_case('post-cleanup integrity recheck records Platform Core drift', static function (): void {
    $fixture = vg_pc_integrity_test_fixture();
    try {
        if (! function_exists('vg_product_contribution_qa_record_platform_core_integrity')) {
            throw new RuntimeException('The post-cleanup Platform Core integrity recheck is missing.');
        }
        file_put_contents($fixture['plugin_root'] . '/src/module.php', "<?php\nreturn false;\n");
        $context = [];
        try {
            vg_product_contribution_qa_record_platform_core_integrity(
                $context,
                'post_cleanup',
                $fixture['plugin_root'],
                $fixture['manifest_path'],
                $fixture['authority']
            );
        } catch (RuntimeException) {
        }
        vg_pc_integrity_test_assert(
            ($context['platform_core_integrity_post_cleanup']['exact'] ?? true) === false
                && ($context['platform_core_integrity_post_cleanup']['mismatched'] ?? []) === ['src/module.php'],
            'Post-cleanup integrity recheck did not capture runtime drift',
            $context['platform_core_integrity_post_cleanup'] ?? []
        );
    } finally {
        vg_pc_integrity_test_remove_tree($fixture['root']);
    }
});

if ($passed !== $total) {
    fwrite(STDERR, 'Product Contribution Platform Core integrity safety: ' . $passed . '/' . $total . " passed.\n");
    exit(1);
}
fwrite(STDOUT, 'Product Contribution Platform Core integrity safety: ' . $passed . '/' . $total . " passed.\n");
