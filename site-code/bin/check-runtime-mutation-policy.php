<?php
declare(strict_types=1);

const VG_RUNTIME_MUTATION_POLICY_CLASSIFICATIONS = [
    'mutating',
    'read_only',
    'library',
    'test_only',
    'explicit_exemption',
];

const VG_RUNTIME_MUTATION_POLICY_PURPOSES = [
    'runtime-qa',
    'seed',
    'cleanup',
    'migration',
    'maintenance',
    'other',
];

const VG_RUNTIME_MUTATION_POLICY_FUNCTIONS = [
    'wp_insert_post', 'wp_update_post', 'wp_delete_post', 'wp_trash_post', 'wp_untrash_post',
    'add_post_meta', 'update_post_meta', 'delete_post_meta', 'add_metadata', 'update_metadata', 'delete_metadata',
    'wp_set_object_terms', 'wp_remove_object_terms', 'wp_insert_term', 'wp_update_term', 'wp_delete_term',
    'add_option', 'update_option', 'delete_option', 'add_site_option', 'update_site_option', 'delete_site_option',
    'update_field', 'update_sub_field', 'acf_update_value', 'acf_save_post', 'delete_field',
    'set_template_settings',
    'wp_schedule_single_event', 'wp_schedule_event', 'wp_clear_scheduled_hook', 'wp_unschedule_event',
];

/** @return list<string> */
function vg_runtime_mutation_policy_tracked_php_files(string $repository_root): array
{
    $command = ['git', '-C', $repository_root, 'ls-files', '-z', '--', '*.php'];
    $process = @proc_open($command, [
        0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);
    if (! is_resource($process)) {
        return [];
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit_code = proc_close($process);
    unset($stderr);
    if ($exit_code !== 0 || ! is_string($stdout)) {
        return [];
    }

    $files = array_values(array_filter(explode("\0", $stdout), static fn (string $path): bool => $path !== ''));
    sort($files);
    return $files;
}

/** @return list<string> */
function vg_runtime_mutation_policy_detect_mutations(string $source): array
{
    $tokens = token_get_all($source);
    $mutations = [];
    $token_count = count($tokens);
    for ($index = 0; $index < $token_count; $index++) {
        $token = $tokens[$index];
        $token_text = is_array($token) ? $token[1] : $token;

        if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            $normalized_name = strtolower(ltrim($token_text, '\\'));
            $short_name = strtolower(substr($normalized_name, strrpos($normalized_name, '\\') === false
                ? 0
                : strrpos($normalized_name, '\\') + 1));
            if (in_array($short_name, VG_RUNTIME_MUTATION_POLICY_FUNCTIONS, true)
                && vg_runtime_mutation_policy_next_significant_text($tokens, $index + 1) === '(') {
                $mutations[] = $short_name;
            }
        }

        if ($token_text !== '$wpdb' || ! is_array($token) || $token[0] !== T_VARIABLE) {
            continue;
        }
        $operator_index = vg_runtime_mutation_policy_next_significant_index($tokens, $index + 1);
        if ($operator_index === null || vg_runtime_mutation_policy_next_significant_text($tokens, $index + 1) !== '->') {
            continue;
        }
        $method_index = vg_runtime_mutation_policy_next_significant_index($tokens, $operator_index + 1);
        if ($method_index === null || ! is_array($tokens[$method_index]) || $tokens[$method_index][0] !== T_STRING) {
            continue;
        }
        $method = strtolower($tokens[$method_index][1]);
        if (! in_array($method, ['insert', 'update', 'delete', 'replace', 'query'], true)
            || vg_runtime_mutation_policy_next_significant_text($tokens, $method_index + 1) !== '(') {
            continue;
        }
        if ($method !== 'query') {
            $mutations[] = 'wpdb.' . $method;
            continue;
        }

        $query_index = vg_runtime_mutation_policy_next_significant_index($tokens, $method_index + 1);
        if ($query_index === null || $tokens[$query_index] !== '(') {
            continue;
        }
        $query_source = vg_runtime_mutation_policy_call_source($tokens, $query_index);
        if (preg_match_all('/\b(INSERT\s+INTO|UPDATE|DELETE\s+FROM|REPLACE\s+INTO|ALTER\s+TABLE|CREATE\s+TABLE|DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i', $query_source, $matches)) {
            foreach ($matches[1] as $verb) {
                $mutations[] = 'wpdb.query:' . strtoupper(strtok($verb, " \t\n\r"));
            }
        }
    }

    $unique = array_values(array_unique($mutations));
    sort($unique);
    return $unique;
}

/** @param list<mixed> $tokens */
function vg_runtime_mutation_policy_next_significant_index(array $tokens, int $start): ?int
{
    for ($index = $start, $count = count($tokens); $index < $count; $index++) {
        $token = $tokens[$index];
        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        return $index;
    }
    return null;
}

/** @param list<mixed> $tokens */
function vg_runtime_mutation_policy_next_significant_text(array $tokens, int $start): ?string
{
    $index = vg_runtime_mutation_policy_next_significant_index($tokens, $start);
    if ($index === null) {
        return null;
    }
    return is_array($tokens[$index]) ? $tokens[$index][1] : $tokens[$index];
}

/** @param list<mixed> $tokens */
function vg_runtime_mutation_policy_call_source(array $tokens, int $opening_parenthesis): string
{
    $depth = 0;
    $parts = [];
    for ($index = $opening_parenthesis, $count = count($tokens); $index < $count; $index++) {
        $token = $tokens[$index];
        $token_text = is_array($token) ? $token[1] : $token;
        if ($token_text === '(') {
            $depth++;
        } elseif ($token_text === ')') {
            $depth--;
            if ($depth === 0) {
                break;
            }
        }
        $parts[] = $token_text;
    }
    return implode('', $parts);
}

function vg_runtime_mutation_policy_has_call(string $source, string $function_name): bool
{
    $expected = strtolower(ltrim($function_name, '\\'));
    $tokens = token_get_all($source);
    for ($index = 0, $count = count($tokens); $index < $count; $index++) {
        $token = $tokens[$index];
        if (! is_array($token) || ! in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            continue;
        }
        $name = strtolower(ltrim($token[1], '\\'));
        $shortName = substr($name, strrpos($name, '\\') === false ? 0 : strrpos($name, '\\') + 1);
        if ($name !== $expected && $shortName !== $expected) {
            continue;
        }
        $previousIndex = null;
        for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
            $previous = $tokens[$cursor];
            if (is_array($previous) && in_array($previous[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $previousIndex = $cursor;
            break;
        }
        $previousToken = $previousIndex === null ? null : $tokens[$previousIndex];
        $previousText = is_array($previousToken) ? $previousToken[1] : $previousToken;
        $isDeclaration = is_array($previousToken) && $previousToken[0] === T_FUNCTION;
        $isMethod = in_array($previousText, ['->', '?->', '::'], true);
        if (! $isDeclaration && ! $isMethod
            && vg_runtime_mutation_policy_next_significant_text($tokens, $index + 1) === '(') {
            return true;
        }
    }
    return false;
}

/** @param array<string,mixed> $entry */
function vg_runtime_mutation_policy_validate_entry(array $entry, string $path, array &$errors): void
{
    $classification = $entry['classification'] ?? null;
    if (! is_string($classification) || ! in_array($classification, VG_RUNTIME_MUTATION_POLICY_CLASSIFICATIONS, true)) {
        $errors[] = "{$path}: unknown classification.";
        return;
    }

    if ($classification === 'mutating') {
        if (($entry['session_guard_required'] ?? null) !== true
            || ($entry['guard_function'] ?? null) !== 'vg_runtime_mutation_assert_session') {
            $errors[] = "{$path}: mutating entry must require vg_runtime_mutation_assert_session.";
        }
        if (! is_string($entry['purpose'] ?? null)
            || ! in_array($entry['purpose'], VG_RUNTIME_MUTATION_POLICY_PURPOSES, true)) {
            $errors[] = "{$path}: mutating entry has no registered purpose.";
        }
    }

    if ($classification === 'explicit_exemption') {
        foreach (['reason', 'reviewed_scope'] as $required) {
            if (! is_string($entry[$required] ?? null) || trim($entry[$required]) === '') {
                $errors[] = "{$path}: explicit exemption requires {$required}.";
            }
        }
    }

    if ($classification === 'library' && isset($entry['mutation_callers'])) {
        if (! is_array($entry['mutation_callers']) || $entry['mutation_callers'] === []) {
            $errors[] = "{$path}: mutation_callers must be a non-empty path list.";
            return;
        }
        foreach ($entry['mutation_callers'] as $caller) {
            if (! is_string($caller) || $caller === '') {
                $errors[] = "{$path}: mutation_callers contains an invalid path.";
            }
        }
    }
}

/**
 * Validate the reviewed inventory and scan every tracked PHP file supplied by Git.
 *
 * @param list<string>|null $tracked_files Optional tracked-file list for source-safe tests.
 * @return array{valid:bool,files_scanned:int,mutation_files:int,errors:list<string>}
 */
function vg_runtime_mutation_policy_check(
    string $repository_root,
    string $inventory_path,
    ?array $tracked_files = null
): array {
    $errors = [];
    $inventory_json = @file_get_contents($inventory_path);
    if (! is_string($inventory_json)) {
        return ['valid' => false, 'files_scanned' => 0, 'mutation_files' => 0, 'errors' => ['Inventory file could not be read.']];
    }

    try {
        $inventory = json_decode($inventory_json, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return ['valid' => false, 'files_scanned' => 0, 'mutation_files' => 0, 'errors' => ['Inventory JSON is invalid.']];
    }

    if (! is_array($inventory)
        || ($inventory['schema_version'] ?? null) !== 1
        || ! is_array($inventory['entries'] ?? null)
        || ! array_is_list($inventory['entries'])) {
        return ['valid' => false, 'files_scanned' => 0, 'mutation_files' => 0, 'errors' => ['Inventory schema is unsupported.']];
    }

    $tracked_files ??= vg_runtime_mutation_policy_tracked_php_files($repository_root);
    if ($tracked_files === []) {
        $errors[] = 'Git returned no tracked PHP files.';
    }
    $tracked_set = [];
    foreach ($tracked_files as $path) {
        if (! is_string($path) || ! str_ends_with($path, '.php')) {
            continue;
        }
        $tracked_set[$path] = true;
    }

    $entries_by_path = [];
    foreach ($inventory['entries'] as $entry) {
        if (! is_array($entry) || ! is_string($entry['path'] ?? null) || trim($entry['path']) === '') {
            $errors[] = 'Inventory contains an entry without a path.';
            continue;
        }
        $path = $entry['path'];
        if (str_starts_with($path, '/') || str_contains($path, "\\") || str_contains($path, "\0")
            || in_array('..', explode('/', $path), true)) {
            $errors[] = "{$path}: inventory path is unsafe.";
            continue;
        }
        if (isset($entries_by_path[$path])) {
            $errors[] = "{$path}: duplicate inventory path.";
            continue;
        }
        $entries_by_path[$path] = $entry;
        vg_runtime_mutation_policy_validate_entry($entry, $path, $errors);
        if (! is_file($repository_root . '/' . $path)) {
            $errors[] = "{$path}: inventory path is missing on disk.";
        }
        if (! isset($tracked_set[$path])) {
            $errors[] = "{$path}: inventory path is not tracked by Git.";
        }
    }

    foreach (array_keys($tracked_set) as $path) {
        if (! isset($entries_by_path[$path])) {
            $errors[] = "{$path}: tracked PHP file is missing from the inventory.";
        }
    }

    $mutation_files = 0;
    foreach (array_keys($tracked_set) as $path) {
        $source = @file_get_contents($repository_root . '/' . $path);
        if (! is_string($source)) {
            $errors[] = "{$path}: tracked PHP file could not be read.";
            continue;
        }
        $mutations = vg_runtime_mutation_policy_detect_mutations($source);
        if ($mutations === []) {
            continue;
        }
        $mutation_files++;
        $entry = $entries_by_path[$path] ?? null;
        $classification = is_array($entry) ? ($entry['classification'] ?? null) : null;
        if ($classification === 'test_only' || $classification === 'explicit_exemption') {
            continue;
        }
        if ($classification === 'mutating') {
            $guard = (string) ($entry['guard_function'] ?? '');
            if (! vg_runtime_mutation_policy_has_call($source, $guard)) {
                $errors[] = "{$path}: declared session guard is absent from source.";
            }
            continue;
        }
        if ($classification === 'library') {
            $callers = $entry['mutation_callers'] ?? null;
            if (! is_array($callers) || $callers === []) {
                $errors[] = "{$path}: mutation-bearing library requires reviewed mutation_callers.";
                continue;
            }
            foreach ($callers as $caller) {
                $caller_entry = is_string($caller) ? ($entries_by_path[$caller] ?? null) : null;
                if (! is_array($caller_entry) || ($caller_entry['classification'] ?? null) !== 'mutating') {
                    $errors[] = "{$path}: mutation caller is not a guarded mutating entry: " . (is_string($caller) ? $caller : 'invalid path') . '.';
                    continue;
                }
                $caller_source = @file_get_contents($repository_root . '/' . $caller);
                $guard = (string) ($caller_entry['guard_function'] ?? '');
                if (! is_string($caller_source) || ! vg_runtime_mutation_policy_has_call($caller_source, $guard)) {
                    $errors[] = "{$path}: mutation caller lacks its declared session guard: {$caller}.";
                }
            }
            continue;
        }
        $errors[] = "{$path}: detected mutation is not classified as mutating or explicitly exempt.";
    }

    sort($errors);
    return [
        'valid' => $errors === [],
        'files_scanned' => count($tracked_set),
        'mutation_files' => $mutation_files,
        'errors' => $errors,
    ];
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    $repository_root = dirname(__DIR__, 2);
    $inventory_path = dirname(__DIR__) . '/runtime-tools/mutating-entrypoints.json';
    $result = vg_runtime_mutation_policy_check($repository_root, $inventory_path);
    if (! $result['valid']) {
        fwrite(STDERR, implode("\n", $result['errors']) . "\n");
        exit(1);
    }
    fwrite(STDOUT, sprintf(
        "Runtime mutation policy passed: %d tracked PHP files, %d files with detected mutation APIs.\n",
        $result['files_scanned'],
        $result['mutation_files']
    ));
}
