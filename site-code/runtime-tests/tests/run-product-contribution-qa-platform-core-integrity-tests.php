<?php
declare(strict_types=1);

define('VG_PRODUCT_CONTRIBUTION_QA_LIBRARY_ONLY', true);
require_once dirname(__DIR__) . '/product-contribution-qa.php';

const VG_PC_QA_INTEGRITY_FIXTURE_TREE_SHA256 = '7f9e89dfe0eb4dfa783726f65ea727c0b52c2504ef7c1968dc88171f1b13ea81';

$passed = 0;
$total = 12;
$run_case = static function (string $name, callable $test) use (&$passed): void {
    try {
        $test();
        $passed++;
        fwrite(STDOUT, 'PASS ' . $name . ".\n");
    } catch (Throwable $error) {
        fwrite(STDERR, 'FAIL ' . $name . ': ' . $error->getMessage() . "\n");
        exit(1);
    }
};

$make_fixture = static function (): array {
    $base = sys_get_temp_dir() . '/vg-pc-qa-integrity-' . bin2hex(random_bytes(6));
    $root = $base . '/plugin';
    if (! mkdir($root . '/bin', 0777, true) && ! is_dir($root . '/bin')) {
        throw new RuntimeException('Could not create integrity fixture directories.');
    }
    $files = [
        'alpha.txt' => ['contents' => "alpha\n", 'mode' => '100644', 'permissions' => 0644],
        'bin/check.sh' => ['contents' => "#!/bin/sh\nexit 0\n", 'mode' => '100755', 'permissions' => 0755],
    ];
    $records = [];
    foreach ($files as $path => $file) {
        $absolute = $root . '/' . $path;
        if (file_put_contents($absolute, $file['contents']) === false || ! chmod($absolute, $file['permissions'])) {
            throw new RuntimeException('Could not write integrity fixture file: ' . $path);
        }
        $records[] = [
            'mode' => $file['mode'],
            'path' => $path,
            'sha256' => hash('sha256', $file['contents']),
            'size' => strlen($file['contents']),
        ];
    }
    $manifest = [
        'schema_version' => 3,
        'file_count' => count($records),
        'tree_sha256' => VG_PC_QA_INTEGRITY_FIXTURE_TREE_SHA256,
        'files' => $records,
    ];
    $bytes = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $manifest_path = $base . '/manifest.json';
    if (file_put_contents($manifest_path, $bytes) === false) {
        throw new RuntimeException('Could not write integrity fixture manifest.');
    }
    return [
        'base' => $base,
        'root' => $root,
        'manifest_path' => $manifest_path,
        'expected_file_count' => count($records),
        'expected_tree_sha256' => VG_PC_QA_INTEGRITY_FIXTURE_TREE_SHA256,
        'expected_manifest_sha256' => hash('sha256', $bytes),
    ];
};

$remove_fixture = static function (string $path) use (&$remove_fixture): void {
    $stat = @lstat($path);
    if (! is_array($stat)) {
        return;
    }
    if (($stat['mode'] & 0170000) !== 0040000) {
        unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            $remove_fixture($path . '/' . $entry);
        }
    }
    rmdir($path);
};

$run_fixture_case = static function (string $name, callable $test) use ($make_fixture, $remove_fixture, $run_case): void {
    $run_case($name, static function () use ($make_fixture, $remove_fixture, $test): void {
        $fixture = $make_fixture();
        try {
            $test($fixture);
        } finally {
            $remove_fixture($fixture['base']);
        }
    });
};

$inspect = static function (array $fixture, ?callable $reader = null): array {
    if (! function_exists('vg_product_contribution_qa_platform_core_integrity_report')) {
        throw new RuntimeException('The Platform Core runtime integrity helper is not implemented.');
    }
    return vg_product_contribution_qa_platform_core_integrity_report(
        $fixture['root'],
        $fixture['manifest_path'],
        $fixture['expected_file_count'],
        $fixture['expected_tree_sha256'],
        $fixture['expected_manifest_sha256'],
        $reader
    );
};

$run_fixture_case('accepts the exact canonical fixture tree', static function (array $fixture) use ($inspect): void {
    $report = $inspect($fixture);
    if ($report['exact'] !== true || $report['file_count'] !== 2 || $report['runtime_tree_sha256'] !== VG_PC_QA_INTEGRITY_FIXTURE_TREE_SHA256) {
        throw new RuntimeException('The exact temporary Platform Core fixture was not accepted.');
    }
});

$run_fixture_case('rejects changed file content', static function (array $fixture) use ($inspect): void {
    file_put_contents($fixture['root'] . '/alpha.txt', "changed\n");
    $report = $inspect($fixture);
    if ($report['exact'] || $report['mismatched'] !== ['alpha.txt']) {
        throw new RuntimeException('Changed Platform Core content was not reported.');
    }
});

$run_fixture_case('rejects a missing file', static function (array $fixture) use ($inspect): void {
    unlink($fixture['root'] . '/alpha.txt');
    $report = $inspect($fixture);
    if ($report['exact'] || $report['missing'] !== ['alpha.txt']) {
        throw new RuntimeException('A missing Platform Core file was not reported.');
    }
});

$run_fixture_case('rejects an extra file', static function (array $fixture) use ($inspect): void {
    file_put_contents($fixture['root'] . '/extra.txt', "extra\n");
    $report = $inspect($fixture);
    if ($report['exact'] || $report['extra'] !== ['extra.txt']) {
        throw new RuntimeException('An extra Platform Core file was not reported.');
    }
});

$run_fixture_case('rejects symlinks', static function (array $fixture) use ($inspect): void {
    if (! symlink('alpha.txt', $fixture['root'] . '/linked.txt')) {
        throw new RuntimeException('Could not create the symlink fixture.');
    }
    $report = $inspect($fixture);
    if ($report['exact'] || $report['symlinks'] !== ['linked.txt']) {
        throw new RuntimeException('A Platform Core symlink was not reported.');
    }
});

$run_fixture_case('rejects an unexpected executable mode', static function (array $fixture) use ($inspect): void {
    chmod($fixture['root'] . '/alpha.txt', 0755);
    $report = $inspect($fixture);
    if ($report['exact'] || $report['mode_mismatched'] !== ['alpha.txt']) {
        throw new RuntimeException('An unexpected executable bit was not reported.');
    }
});

$run_fixture_case('rejects a missing expected executable mode', static function (array $fixture) use ($inspect): void {
    chmod($fixture['root'] . '/bin/check.sh', 0644);
    $report = $inspect($fixture);
    if ($report['exact'] || $report['mode_mismatched'] !== ['bin/check.sh']) {
        throw new RuntimeException('A missing expected executable bit was not reported.');
    }
});

$run_fixture_case('rejects unreadable files', static function (array $fixture) use ($inspect): void {
    chmod($fixture['root'] . '/alpha.txt', 0000);
    $report = $inspect($fixture);
    if ($report['exact'] || $report['unreadable'] !== ['alpha.txt']) {
        throw new RuntimeException('A file without read permission was not reported.');
    }
});

$run_fixture_case('rejects file read failures', static function (array $fixture) use ($inspect): void {
    $report = $inspect($fixture, static fn (string $path): false => false);
    if ($report['exact'] || $report['unreadable'] !== ['alpha.txt', 'bin/check.sh']) {
        throw new RuntimeException('A failed file read was not reported.');
    }
});

$run_fixture_case('rejects unsupported filesystem objects', static function (array $fixture) use ($inspect): void {
    if (! function_exists('posix_mkfifo') || ! posix_mkfifo($fixture['root'] . '/pipe', 0600)) {
        throw new RuntimeException('Could not create the unsupported object fixture.');
    }
    $report = $inspect($fixture);
    if ($report['exact'] || $report['unsupported'] !== ['pipe']) {
        throw new RuntimeException('An unsupported Platform Core filesystem object was not reported.');
    }
});

$run_fixture_case('rejects unsafe manifest paths', static function (array $fixture) use ($inspect): void {
    $manifest = json_decode(file_get_contents($fixture['manifest_path']), true, 512, JSON_THROW_ON_ERROR);
    $manifest['files'][0]['path'] = '../outside.txt';
    $bytes = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    file_put_contents($fixture['manifest_path'], $bytes);
    $fixture['expected_manifest_sha256'] = hash('sha256', $bytes);
    $report = $inspect($fixture);
    if ($report['exact'] || $report['manifest_errors'] === []) {
        throw new RuntimeException('An unsafe manifest path was accepted.');
    }
});

$run_fixture_case('rejects a tree mismatch before mutation or cleanup', static function (array $fixture) use ($inspect): void {
    file_put_contents($fixture['root'] . '/alpha.txt', "changed\n");
    $body_calls = 0;
    $cleanup_calls = 0;
    $result = vg_product_contribution_qa_guarded_run(
        static function () use ($fixture, $inspect): void {
            $report = $inspect($fixture);
            if (! $report['exact']) {
                throw new RuntimeException('Platform Core integrity check failed.');
            }
        },
        static function () use (&$body_calls): void {
            $body_calls++;
        },
        static function () use (&$cleanup_calls): void {
            $cleanup_calls++;
        }
    );
    if ($result['mutation_started'] || $result['cleanup_attempted'] || $body_calls !== 0 || $cleanup_calls !== 0) {
        throw new RuntimeException('Platform Core integrity rejection entered the mutation or cleanup window.');
    }
});

if ($passed !== $total) {
    fwrite(STDERR, 'FAIL Product Contribution QA Platform Core integrity safety: ' . $passed . '/' . $total . " passed.\n");
    exit(1);
}
fwrite(STDOUT, 'Product Contribution QA Platform Core integrity safety: ' . $passed . '/' . $total . " passed.\n");
