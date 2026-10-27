<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bin/check-runtime-mutation-policy.php';

function vg_runtime_mutation_policy_test(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function vg_runtime_mutation_policy_with_temp_root(callable $callback): void
{
    $root = sys_get_temp_dir() . '/vg-pe007-policy-' . bin2hex(random_bytes(6));
    if (! mkdir($root, 0700, true) && ! is_dir($root)) {
        throw new RuntimeException('Could not create the policy fixture directory.');
    }
    try {
        $callback($root);
    } finally {
        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        ) as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($root);
    }
}

function vg_runtime_mutation_policy_inventory(array $entries): string
{
    return json_encode(['schema_version' => 1, 'entries' => $entries], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
}

function vg_runtime_mutation_policy_entry(string $path, string $classification, array $extra = []): array
{
    return array_replace(['path' => $path, 'classification' => $classification], $extra);
}

$tests = 0;
$test = static function (string $name, callable $callback) use (&$tests): void {
    $callback();
    $tests++;
    echo "PASS {$name}\n";
};

$test('unguarded mutator is rejected', static function (): void {
    vg_runtime_mutation_policy_with_temp_root(static function (string $root): void {
        file_put_contents($root . '/mutator.php', '<?php wp_insert_post([]);');
        $inventoryPath = $root . '/inventory.json';
        file_put_contents($inventoryPath, vg_runtime_mutation_policy_inventory([
            vg_runtime_mutation_policy_entry('mutator.php', 'library'),
        ]));
        $result = vg_runtime_mutation_policy_check($root, $inventoryPath, ['mutator.php']);
        vg_runtime_mutation_policy_test(! $result['valid'], 'An unguarded mutator passed policy.');
        vg_runtime_mutation_policy_test(str_contains(implode("\n", $result['errors']), 'mutator.php'), 'Policy omitted the offending path.');
    });
});

$test('guarded mutator is accepted', static function (): void {
    vg_runtime_mutation_policy_with_temp_root(static function (string $root): void {
        file_put_contents($root . '/mutator.php', '<?php vg_runtime_mutation_assert_session("vgnova-local-10039"); wp_insert_post([]);');
        $inventoryPath = $root . '/inventory.json';
        file_put_contents($inventoryPath, vg_runtime_mutation_policy_inventory([
            vg_runtime_mutation_policy_entry('mutator.php', 'mutating', [
                'session_guard_required' => true,
                'guard_function' => 'vg_runtime_mutation_assert_session',
                'purpose' => 'runtime-qa',
            ]),
        ]));
        $result = vg_runtime_mutation_policy_check($root, $inventoryPath, ['mutator.php']);
        vg_runtime_mutation_policy_test($result['valid'], implode("\n", $result['errors']));
    });
});

$test('read-only and test-only files may contain inspected or stubbed APIs', static function (): void {
    vg_runtime_mutation_policy_with_temp_root(static function (string $root): void {
        file_put_contents($root . '/reader.php', '<?php $posts = get_posts([]);');
        file_put_contents($root . '/stub.php', '<?php function wp_insert_post(array $post): int { return 1; }');
        $inventoryPath = $root . '/inventory.json';
        file_put_contents($inventoryPath, vg_runtime_mutation_policy_inventory([
            vg_runtime_mutation_policy_entry('reader.php', 'read_only'),
            vg_runtime_mutation_policy_entry('stub.php', 'test_only'),
        ]));
        $result = vg_runtime_mutation_policy_check($root, $inventoryPath, ['reader.php', 'stub.php']);
        vg_runtime_mutation_policy_test($result['valid'], implode("\n", $result['errors']));
    });
});

$test('explicit exemption requires reason and reviewed scope', static function (): void {
    vg_runtime_mutation_policy_with_temp_root(static function (string $root): void {
        file_put_contents($root . '/domain.php', '<?php wp_insert_post([]);');
        $inventoryPath = $root . '/inventory.json';
        file_put_contents($inventoryPath, vg_runtime_mutation_policy_inventory([
            vg_runtime_mutation_policy_entry('domain.php', 'explicit_exemption', [
                'reason' => 'Normal application writes stay outside administrative coordination.',
                'reviewed_scope' => 'WordPress request lifecycle for the domain service.',
            ]),
        ]));
        $result = vg_runtime_mutation_policy_check($root, $inventoryPath, ['domain.php']);
        vg_runtime_mutation_policy_test($result['valid'], implode("\n", $result['errors']));
    });
});

$test('explicit exemption without a reviewed scope is rejected', static function (): void {
    vg_runtime_mutation_policy_with_temp_root(static function (string $root): void {
        file_put_contents($root . '/domain.php', '<?php wp_insert_post([]);');
        $inventoryPath = $root . '/inventory.json';
        file_put_contents($inventoryPath, vg_runtime_mutation_policy_inventory([
            vg_runtime_mutation_policy_entry('domain.php', 'explicit_exemption', [
                'reason' => 'Normal application writes stay outside administrative coordination.',
            ]),
        ]));
        $result = vg_runtime_mutation_policy_check($root, $inventoryPath, ['domain.php']);
        vg_runtime_mutation_policy_test(! $result['valid'], 'An incomplete exemption passed policy.');
    });
});

$test('missing inventory file is rejected', static function (): void {
    vg_runtime_mutation_policy_with_temp_root(static function (string $root): void {
        $inventoryPath = $root . '/inventory.json';
        file_put_contents($inventoryPath, vg_runtime_mutation_policy_inventory([
            vg_runtime_mutation_policy_entry('missing.php', 'library'),
        ]));
        $result = vg_runtime_mutation_policy_check($root, $inventoryPath, ['missing.php']);
        vg_runtime_mutation_policy_test(! $result['valid'], 'Missing inventory file passed policy.');
    });
});

$test('duplicate inventory path is rejected', static function (): void {
    vg_runtime_mutation_policy_with_temp_root(static function (string $root): void {
        file_put_contents($root . '/reader.php', '<?php');
        $inventoryPath = $root . '/inventory.json';
        file_put_contents($inventoryPath, vg_runtime_mutation_policy_inventory([
            vg_runtime_mutation_policy_entry('reader.php', 'library'),
            vg_runtime_mutation_policy_entry('reader.php', 'read_only'),
        ]));
        $result = vg_runtime_mutation_policy_check($root, $inventoryPath, ['reader.php']);
        vg_runtime_mutation_policy_test(! $result['valid'], 'Duplicate path passed policy.');
    });
});

$test('unknown classification is rejected', static function (): void {
    vg_runtime_mutation_policy_with_temp_root(static function (string $root): void {
        file_put_contents($root . '/reader.php', '<?php');
        $inventoryPath = $root . '/inventory.json';
        file_put_contents($inventoryPath, vg_runtime_mutation_policy_inventory([
            vg_runtime_mutation_policy_entry('reader.php', 'ignored'),
        ]));
        $result = vg_runtime_mutation_policy_check($root, $inventoryPath, ['reader.php']);
        vg_runtime_mutation_policy_test(! $result['valid'], 'Unknown classification passed policy.');
    });
});

$test('declared guard absent is rejected', static function (): void {
    vg_runtime_mutation_policy_with_temp_root(static function (string $root): void {
        file_put_contents($root . '/mutator.php', "<?php // vg_runtime_mutation_assert_session('vgnova-local-10039');\n\$fake = 'vg_runtime_mutation_assert_session(\\\"vgnova-local-10039\\\")';\nfunction vg_runtime_mutation_assert_session(string \$target): void {}\nwp_update_post([]);");
        $inventoryPath = $root . '/inventory.json';
        file_put_contents($inventoryPath, vg_runtime_mutation_policy_inventory([
            vg_runtime_mutation_policy_entry('mutator.php', 'mutating', [
                'session_guard_required' => true,
                'guard_function' => 'vg_runtime_mutation_assert_session',
                'purpose' => 'runtime-qa',
            ]),
        ]));
        $result = vg_runtime_mutation_policy_check($root, $inventoryPath, ['mutator.php']);
        vg_runtime_mutation_policy_test(! $result['valid'], 'Missing declared guard passed policy.');
    });
});

$test('first-class guard callable is not accepted as an executed session assertion', static function (): void {
    vg_runtime_mutation_policy_with_temp_root(static function (string $root): void {
        file_put_contents($root . '/mutator.php', '<?php $guard = vg_runtime_mutation_assert_session(...); wp_insert_post([]);');
        $inventoryPath = $root . '/inventory.json';
        file_put_contents($inventoryPath, vg_runtime_mutation_policy_inventory([
            vg_runtime_mutation_policy_entry('mutator.php', 'mutating', [
                'session_guard_required' => true,
                'guard_function' => 'vg_runtime_mutation_assert_session',
                'purpose' => 'runtime-qa',
            ]),
        ]));
        $result = vg_runtime_mutation_policy_check($root, $inventoryPath, ['mutator.php']);
        vg_runtime_mutation_policy_test(! $result['valid'], 'A first-class callable passed as an executed session assertion.');
    });
});

$test('by-reference guard declaration is not accepted as an executed session assertion', static function (): void {
    vg_runtime_mutation_policy_with_temp_root(static function (string $root): void {
        file_put_contents($root . '/mutator.php', '<?php function &vg_runtime_mutation_assert_session() {} wp_insert_post([]);');
        $inventoryPath = $root . '/inventory.json';
        file_put_contents($inventoryPath, vg_runtime_mutation_policy_inventory([
            vg_runtime_mutation_policy_entry('mutator.php', 'mutating', [
                'session_guard_required' => true,
                'guard_function' => 'vg_runtime_mutation_assert_session',
                'purpose' => 'runtime-qa',
            ]),
        ]));
        $result = vg_runtime_mutation_policy_check($root, $inventoryPath, ['mutator.php']);
        vg_runtime_mutation_policy_test(! $result['valid'], 'A by-reference declaration passed as an executed session assertion.');
    });
});

$test('session assertion must precede the first detected persistent write', static function (): void {
    vg_runtime_mutation_policy_with_temp_root(static function (string $root): void {
        file_put_contents($root . '/mutator.php', '<?php wp_update_post([]); vg_runtime_mutation_assert_session("vgnova-local-10039");');
        $inventoryPath = $root . '/inventory.json';
        file_put_contents($inventoryPath, vg_runtime_mutation_policy_inventory([
            vg_runtime_mutation_policy_entry('mutator.php', 'mutating', [
                'session_guard_required' => true,
                'guard_function' => 'vg_runtime_mutation_assert_session',
                'purpose' => 'runtime-qa',
            ]),
        ]));
        $result = vg_runtime_mutation_policy_check($root, $inventoryPath, ['mutator.php']);
        vg_runtime_mutation_policy_test(! $result['valid'], 'A session assertion after the first write passed policy.');
    });
});

$test('guard in an uncalled helper does not protect a top-level mutation', static function (): void {
    vg_runtime_mutation_policy_with_temp_root(static function (string $root): void {
        file_put_contents($root . '/mutator.php', '<?php function unused_guard() { vg_runtime_mutation_assert_session("vgnova-local-10039"); } wp_insert_post([]);');
        $inventoryPath = $root . '/inventory.json';
        file_put_contents($inventoryPath, vg_runtime_mutation_policy_inventory([
            vg_runtime_mutation_policy_entry('mutator.php', 'mutating', [
                'session_guard_required' => true,
                'guard_function' => 'vg_runtime_mutation_assert_session',
                'purpose' => 'runtime-qa',
            ]),
        ]));
        $result = vg_runtime_mutation_policy_check($root, $inventoryPath, ['mutator.php']);
        vg_runtime_mutation_policy_test(! $result['valid'], 'An uncalled guard helper protected a top-level write.');
        vg_runtime_mutation_policy_test(
            str_contains(implode("\n", $result['errors']), 'session guard is absent before a detected persistent write'),
            'Policy did not identify the uncalled guard wrapper.'
        );
    });
});

$test('a conditional guard does not protect a write outside its branch', static function (): void {
    vg_runtime_mutation_policy_with_temp_root(static function (string $root): void {
        file_put_contents(
            $root . '/mutator.php',
            '<?php if ($dryRun) { vg_runtime_mutation_assert_session("vgnova-local-10039"); } wp_insert_post([]);'
        );
        $inventoryPath = $root . '/inventory.json';
        file_put_contents($inventoryPath, vg_runtime_mutation_policy_inventory([
            vg_runtime_mutation_policy_entry('mutator.php', 'mutating', [
                'session_guard_required' => true,
                'guard_function' => 'vg_runtime_mutation_assert_session',
                'purpose' => 'runtime-qa',
            ]),
        ]));
        $result = vg_runtime_mutation_policy_check($root, $inventoryPath, ['mutator.php']);
        vg_runtime_mutation_policy_test(! $result['valid'], 'A guard confined to a conditional branch protected an unconditional write.');
    });
});

$test('a short-circuit guard does not protect a later write', static function (): void {
    vg_runtime_mutation_policy_with_temp_root(static function (string $root): void {
        file_put_contents(
            $root . '/mutator.php',
            '<?php $dryRun && vg_runtime_mutation_assert_session("vgnova-local-10039"); wp_insert_post([]);'
        );
        $inventoryPath = $root . '/inventory.json';
        file_put_contents($inventoryPath, vg_runtime_mutation_policy_inventory([
            vg_runtime_mutation_policy_entry('mutator.php', 'mutating', [
                'session_guard_required' => true,
                'guard_function' => 'vg_runtime_mutation_assert_session',
                'purpose' => 'runtime-qa',
            ]),
        ]));
        $result = vg_runtime_mutation_policy_check($root, $inventoryPath, ['mutator.php']);
        vg_runtime_mutation_policy_test(! $result['valid'], 'A conditionally executed short-circuit guard protected a later write.');
    });
});

$test('session guard in try does not protect writes in finally', static function (): void {
    vg_runtime_mutation_policy_with_temp_root(static function (string $root): void {
        file_put_contents(
            $root . '/mutator.php',
            '<?php try { vg_runtime_mutation_assert_session("vgnova-local-10039"); } finally { wp_delete_post(1, true); }'
        );
        $inventoryPath = $root . '/inventory.json';
        file_put_contents($inventoryPath, vg_runtime_mutation_policy_inventory([
            vg_runtime_mutation_policy_entry('mutator.php', 'mutating', [
                'session_guard_required' => true,
                'guard_function' => 'vg_runtime_mutation_assert_session',
                'purpose' => 'runtime-qa',
            ]),
        ]));
        $result = vg_runtime_mutation_policy_check($root, $inventoryPath, ['mutator.php']);
        vg_runtime_mutation_policy_test(! $result['valid'], 'A guard inside try protected a cleanup write that runs when the guard throws.');
    });
});

$test('session guard in try does not protect writes in catch', static function (): void {
    vg_runtime_mutation_policy_with_temp_root(static function (string $root): void {
        file_put_contents(
            $root . '/mutator.php',
            '<?php try { vg_runtime_mutation_assert_session("vgnova-local-10039"); } catch (Throwable $error) { wp_delete_post(1, true); }'
        );
        $inventoryPath = $root . '/inventory.json';
        file_put_contents($inventoryPath, vg_runtime_mutation_policy_inventory([
            vg_runtime_mutation_policy_entry('mutator.php', 'mutating', [
                'session_guard_required' => true,
                'guard_function' => 'vg_runtime_mutation_assert_session',
                'purpose' => 'runtime-qa',
            ]),
        ]));
        $result = vg_runtime_mutation_policy_check($root, $inventoryPath, ['mutator.php']);
        vg_runtime_mutation_policy_test(! $result['valid'], 'A guard inside try protected a mutation in the exception catch path.');
    });
});

$test('session guard before try protects writes in finally', static function (): void {
    vg_runtime_mutation_policy_with_temp_root(static function (string $root): void {
        file_put_contents(
            $root . '/mutator.php',
            '<?php vg_runtime_mutation_assert_session("vgnova-local-10039"); try { do_work(); } finally { wp_delete_post(1, true); }'
        );
        $inventoryPath = $root . '/inventory.json';
        file_put_contents($inventoryPath, vg_runtime_mutation_policy_inventory([
            vg_runtime_mutation_policy_entry('mutator.php', 'mutating', [
                'session_guard_required' => true,
                'guard_function' => 'vg_runtime_mutation_assert_session',
                'purpose' => 'runtime-qa',
            ]),
        ]));
        $result = vg_runtime_mutation_policy_check($root, $inventoryPath, ['mutator.php']);
        vg_runtime_mutation_policy_test($result['valid'], implode("\n", $result['errors']));
    });
});

$test('wpdb INSERT UPDATE and DELETE statements are detected', static function (): void {
    $source = <<<'PHP'
<?php
$wpdb->query('INSERT INTO wp_posts (post_title) VALUES ("x")');
$wpdb->query("UPDATE wp_posts SET post_status = 'draft'");
$wpdb->query('DELETE FROM wp_posts WHERE ID = 1');
PHP;
    $calls = vg_runtime_mutation_policy_detect_mutations($source);
    foreach (['wpdb.query:INSERT', 'wpdb.query:UPDATE', 'wpdb.query:DELETE'] as $expected) {
        vg_runtime_mutation_policy_test(in_array($expected, $calls, true), 'Missing detection for ' . $expected . '.');
    }
});

$test('dynamic SQL calls and additional DDL/DCL mutation forms are detected', static function (): void {
    $source = <<<'PHP'
<?php
$wpdb->query($sql);
$wpdb->query('RENAME TABLE wp_old TO wp_new');
$wpdb->query('GRANT SELECT ON wp_posts TO qa_user');
$wpdb->query('CALL mutate_content()');
$wpdb->query('LOAD DATA INFILE "/tmp/content.csv" INTO TABLE wp_posts');
PHP;
    $calls = vg_runtime_mutation_policy_detect_mutations($source);
    foreach ([
        'wpdb.query:DYNAMIC', 'wpdb.query:RENAME', 'wpdb.query:GRANT',
        'wpdb.query:CALL', 'wpdb.query:LOAD',
    ] as $expected) {
        vg_runtime_mutation_policy_test(in_array($expected, $calls, true), 'Missing detection for ' . $expected . '.');
    }
});

$test('wpdb query methods, outfile selects and dynamic method dispatch are detected', static function (): void {
    $source = <<<'PHP'
<?php
$wpdb->get_results('UPDATE wp_posts SET post_status = "draft"');
$wpdb->get_var($sql);
$wpdb->query('SELECT * FROM wp_posts INTO OUTFILE "/tmp/posts.csv"');
$wpdb->{$operation}('wp_posts', $data);
$wpdb->$operation('wp_posts', $data);
PHP;
    $calls = vg_runtime_mutation_policy_detect_mutations($source);
    foreach ([
        'wpdb.get_results:UPDATE',
        'wpdb.get_var:DYNAMIC',
        'wpdb.query:DYNAMIC',
        'wpdb.dynamic-method',
    ] as $expected) {
        vg_runtime_mutation_policy_test(in_array($expected, $calls, true), 'Missing detection for ' . $expected . '.');
    }
});

$test('a SELECT prefix concatenated with unknown SQL remains mutation-capable', static function (): void {
    $source = <<<'PHP'
<?php
$wpdb->query('SELECT * FROM wp_posts' . $suffix);
PHP;
    $calls = vg_runtime_mutation_policy_detect_mutations($source);
    vg_runtime_mutation_policy_test(in_array('wpdb.query:DYNAMIC', $calls, true), 'A SELECT prefix with an unknown SQL suffix was incorrectly treated as read-only.');
});

$test('a local prepared read query is resolved as read-only', static function (): void {
    $source = <<<'PHP'
<?php
$query = $wpdb->prepare('SELECT IS_USED_LOCK(%s)', $lock_name);
$owner = $wpdb->get_var($query);
PHP;
    $calls = vg_runtime_mutation_policy_detect_mutations($source);
    vg_runtime_mutation_policy_test($calls === [], 'A prepared SELECT assigned to a local query variable was reported as a persistent mutation.');
});

$test('prepared reads in a local foreach query list are resolved as read-only', static function (): void {
    $source = <<<'PHP'
<?php
function vg_policy_test_read_count(string $sql): int {
    global $wpdb;
    return (int) $wpdb->get_var($sql);
}
$queries = [
    $wpdb->prepare('SELECT COUNT(*) FROM wp_posts WHERE ID = %d', 1),
    $wpdb->prepare('SELECT COUNT(*) FROM wp_options WHERE option_name = %s', 'example'),
];
foreach ($queries as $query) {
    vg_policy_test_read_count($query);
}
PHP;
    $calls = vg_runtime_mutation_policy_detect_mutations($source);
    vg_runtime_mutation_policy_test($calls === [], 'A prepared read query from a local list was reported as a persistent mutation.');
});

$test('unknown SQL in a local foreach query list remains mutation-capable', static function (): void {
    $source = <<<'PHP'
<?php
$queries = ['SELECT * FROM wp_posts' . $suffix];
foreach ($queries as $query) {
    $wpdb->query($query);
}
PHP;
    $calls = vg_runtime_mutation_policy_detect_mutations($source);
    vg_runtime_mutation_policy_test(in_array('wpdb.query:DYNAMIC', $calls, true), 'Unknown SQL in a foreach list was incorrectly treated as read-only.');
});

$test('dynamic function dispatch is treated as a possible mutation', static function (): void {
    $calls = vg_runtime_mutation_policy_detect_mutations('<?php call_user_func($callback, $value);');
    vg_runtime_mutation_policy_test(in_array('dynamic-dispatch', $calls, true), 'Dynamic function dispatch was not classified as mutation-capable.');
});

$test('mutation library caller must include and invoke the library', static function (): void {
    vg_runtime_mutation_policy_with_temp_root(static function (string $root): void {
        file_put_contents($root . '/library.php', '<?php function vg_test_library_write() { wp_insert_post([]); }');
        file_put_contents($root . '/caller.php', '<?php require_once __DIR__ . "/library.php"; vg_runtime_mutation_assert_session("vgnova-local-10039");');
        $inventoryPath = $root . '/inventory.json';
        file_put_contents($inventoryPath, vg_runtime_mutation_policy_inventory([
            vg_runtime_mutation_policy_entry('library.php', 'library', ['mutation_callers' => ['caller.php']]),
            vg_runtime_mutation_policy_entry('caller.php', 'mutating', [
                'session_guard_required' => true,
                'guard_function' => 'vg_runtime_mutation_assert_session',
                'purpose' => 'runtime-qa',
            ]),
        ]));
        $result = vg_runtime_mutation_policy_check($root, $inventoryPath, ['library.php', 'caller.php']);
        vg_runtime_mutation_policy_test(! $result['valid'], 'A caller that omitted the library invocation passed policy.');
        vg_runtime_mutation_policy_test(
            str_contains(implode("\n", $result['errors']), 'does not include and invoke a function that reaches this library'),
            'Policy did not identify the stale mutation caller edge.'
        );
        file_put_contents(
            $root . '/caller.php',
            '<?php require_once __DIR__ . "/library.php"; vg_runtime_mutation_assert_session("vgnova-local-10039"); vg_test_library_write();'
        );
        $result = vg_runtime_mutation_policy_check($root, $inventoryPath, ['library.php', 'caller.php']);
        vg_runtime_mutation_policy_test($result['valid'], implode("\n", $result['errors']));

        file_put_contents(
            $root . '/caller.php',
            '<?php require_once __DIR__ . "/library.php"; vg_test_library_write(); vg_runtime_mutation_assert_session("vgnova-local-10039");'
        );
        $result = vg_runtime_mutation_policy_check($root, $inventoryPath, ['library.php', 'caller.php']);
        vg_runtime_mutation_policy_test(! $result['valid'], 'A library caller with a guard after the write passed policy.');
    });
});

$test('a library caller must invoke a function that can reach its write', static function (): void {
    vg_runtime_mutation_policy_with_temp_root(static function (string $root): void {
        file_put_contents($root . '/library.php', '<?php function vg_test_library_read() { return get_option("x"); } function vg_test_library_write() { wp_insert_post([]); }');
        file_put_contents(
            $root . '/caller.php',
            '<?php require_once __DIR__ . "/library.php"; vg_runtime_mutation_assert_session("vgnova-local-10039"); vg_test_library_read();'
        );
        $inventoryPath = $root . '/inventory.json';
        file_put_contents($inventoryPath, vg_runtime_mutation_policy_inventory([
            vg_runtime_mutation_policy_entry('library.php', 'library', ['mutation_callers' => ['caller.php']]),
            vg_runtime_mutation_policy_entry('caller.php', 'mutating', [
                'session_guard_required' => true,
                'guard_function' => 'vg_runtime_mutation_assert_session',
                'purpose' => 'runtime-qa',
            ]),
        ]));
        $result = vg_runtime_mutation_policy_check($root, $inventoryPath, ['library.php', 'caller.php']);
        vg_runtime_mutation_policy_test(! $result['valid'], 'A guarded call to a read-only library function satisfied the mutation caller requirement.');
    });
});

$test('the reviewed mutation inventory passes against every tracked PHP file', static function (): void {
    $repositoryRoot = dirname(__DIR__, 3);
    $inventoryPath = $repositoryRoot . '/site-code/runtime-tools/mutating-entrypoints.json';
    $result = vg_runtime_mutation_policy_check($repositoryRoot, $inventoryPath);
    vg_runtime_mutation_policy_test($result['valid'], implode("\n", $result['errors']));
    vg_runtime_mutation_policy_test($result['files_scanned'] > 100, 'The inventory regression did not scan the repository PHP sources.');
});

$test('protected external mutators require a bounded target and coordinator declaration', static function (): void {
    vg_runtime_mutation_policy_with_temp_root(static function (string $root): void {
        mkdir($root . '/site-code/bin', 0700, true);
        mkdir($root . '/site-code/runtime-tools', 0700, true);
        file_put_contents($root . '/site-code/bin/deploy-platform-core.py', '#!/usr/bin/env python3\n');
        file_put_contents($root . '/site-code/bin/vgnova-runtime-mutate.php', '<?php');
        file_put_contents($root . '/site-code/runtime-tools/platform-core-deployment-health.php', '<?php');
        $git = static function (array $arguments) use ($root): void {
            $process = proc_open(
                ['git', '-C', $root, ...$arguments],
                [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes
            );
            if (! is_resource($process)) {
                throw new RuntimeException('Could not start the policy test Git process.');
            }
            stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            if (proc_close($process) !== 0) {
                throw new RuntimeException('The policy test Git process failed.');
            }
        };
        $git(['init', '--quiet']);
        $git(['add', 'site-code/bin/deploy-platform-core.py', 'site-code/bin/vgnova-runtime-mutate.php', 'site-code/runtime-tools/platform-core-deployment-health.php']);
        $valid = [[
            'path' => 'site-code/bin/deploy-platform-core.py',
            'boundary' => 'Platform Core runtime filesystem replacement',
            'actions' => [
                'create_owned_stage_and_state_files',
                'atomic_platform_core_directory_exchange',
                'rename_owned_platform_core_directories',
            ],
            'guard' => 'The PHP helper validates the inherited PE-007 session before filesystem mutation.',
            'reviewed_scope' => 'One registered local target and one Platform Core directory.',
            'protected' => true,
            'target' => 'vgnova-local-10039',
            'coordinator' => 'site-code/bin/vgnova-runtime-mutate.php',
            'session_helper' => 'site-code/runtime-tools/platform-core-deployment-health.php',
        ]];
        $errors = vg_runtime_mutation_policy_validate_external_mutators($root, $valid);
        vg_runtime_mutation_policy_test($errors === [], implode("\n", $errors));

        $invalid = $valid;
        $invalid[0]['actions'][] = 'delete_runtime_tree';
        $errors = vg_runtime_mutation_policy_validate_external_mutators($root, $invalid);
        vg_runtime_mutation_policy_test($errors !== [], 'An unreviewed destructive action passed the external mutator policy.');

        $invalid = $valid;
        $invalid[0]['target'] = 'another-target';
        $errors = vg_runtime_mutation_policy_validate_external_mutators($root, $invalid);
        vg_runtime_mutation_policy_test($errors !== [], 'A protected external mutator accepted a different target.');

        $invalid = $valid;
        $invalid[0]['path'] = 'site-code/bin/missing.py';
        $errors = vg_runtime_mutation_policy_validate_external_mutators($root, $invalid);
        vg_runtime_mutation_policy_test($errors !== [], 'A missing external mutator path passed policy.');

        file_put_contents($root . '/site-code/bin/untracked.py', '#!/usr/bin/env python3\n');
        $untracked = [[
            'path' => 'site-code/bin/untracked.py',
            'boundary' => 'Fixture boundary.',
            'actions' => ['create'],
            'guard' => 'Fixture guard.',
            'reviewed_scope' => 'Fixture scope.',
        ]];
        $errors = vg_runtime_mutation_policy_validate_external_mutators($root, $untracked);
        vg_runtime_mutation_policy_test($errors !== [], 'An untracked external mutator passed policy.');
    });
});

$test('the PE-008 mutator and read-only health helper cannot disappear from policy', static function (): void {
    $inventory = [
        'entries' => [[
            'path' => 'site-code/runtime-tools/platform-core-deployment-health.php',
            'classification' => 'read_only',
        ]],
        'external_mutators' => [[
            'path' => 'site-code/bin/deploy-platform-core.py',
            'protected' => true,
        ]],
    ];
    vg_runtime_mutation_policy_test(
        vg_runtime_mutation_policy_validate_pe008_contract($inventory) === [],
        'The valid PE-008 policy contract was rejected.'
    );
    $inventory['external_mutators'] = [];
    vg_runtime_mutation_policy_test(
        vg_runtime_mutation_policy_validate_pe008_contract($inventory) !== [],
        'Policy allowed the PE-008 mutator to disappear.'
    );
    $inventory['external_mutators'][] = ['path' => 'site-code/bin/deploy-platform-core.py', 'protected' => true];
    $inventory['entries'][0]['classification'] = 'library';
    vg_runtime_mutation_policy_test(
        vg_runtime_mutation_policy_validate_pe008_contract($inventory) !== [],
        'Policy allowed the PE-008 helper to lose its read-only classification.'
    );
});

$test('wpdb CRUD methods and Bricks and ACF write calls are detected', static function (): void {
    $source = <<<'PHP'
<?php
$wpdb->insert('wp_posts', []);
$wpdb->update('wp_posts', [], []);
$wpdb->delete('wp_posts', []);
$wpdb->replace('wp_posts', []);
\Bricks\Helpers::set_template_settings(1, []);
update_field('field', 'value', 1);
PHP;
    $calls = vg_runtime_mutation_policy_detect_mutations($source);
    foreach ([
        'wpdb.insert', 'wpdb.update', 'wpdb.delete', 'wpdb.replace',
        'set_template_settings', 'update_field',
    ] as $expected) {
        vg_runtime_mutation_policy_test(in_array($expected, $calls, true), 'Missing detection for ' . $expected . '.');
    }
});

$test('WordPress post taxonomy and option writes are detected', static function (): void {
    $source = '<?php wp_delete_post(1); wp_set_object_terms(1, [], "topic"); update_option("x", 1);';
    $calls = vg_runtime_mutation_policy_detect_mutations($source);
    foreach (['wp_delete_post', 'wp_set_object_terms', 'update_option'] as $expected) {
        vg_runtime_mutation_policy_test(in_array($expected, $calls, true), 'Missing detection for ' . $expected . '.');
    }
});

echo "{$tests} runtime mutation policy tests passed.\n";
