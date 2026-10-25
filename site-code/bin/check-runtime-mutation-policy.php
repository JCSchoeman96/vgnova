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
    $mutations = [];
    foreach (vg_runtime_mutation_policy_mutation_calls($source) as $call) {
        $mutations[] = $call['name'];
    }
    $unique = array_values(array_unique($mutations));
    sort($unique);
    return $unique;
}

/**
 * Return statically recognized persistent writes and dynamic dispatch sites.
 * This is a conservative token scan, not a complete PHP call graph.
 *
 * @return list<array{index:int,name:string}>
 */
function vg_runtime_mutation_policy_mutation_calls(string $source): array
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
            $call_index = vg_runtime_mutation_policy_actual_call_index($tokens, $index, true);
            if ($call_index !== null && in_array($short_name, VG_RUNTIME_MUTATION_POLICY_FUNCTIONS, true)) {
                $mutations[] = ['index' => $index, 'name' => $short_name];
            }
            if ($call_index !== null && in_array($short_name, [
                'call_user_func', 'call_user_func_array', 'forward_static_call', 'forward_static_call_array',
            ], true)) {
                $mutations[] = ['index' => $index, 'name' => 'dynamic-dispatch'];
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
            $mutations[] = ['index' => $method_index, 'name' => 'wpdb.' . $method];
            continue;
        }

        $query_index = vg_runtime_mutation_policy_next_significant_index($tokens, $method_index + 1);
        if ($query_index === null || $tokens[$query_index] !== '(') {
            continue;
        }
        $query_source = vg_runtime_mutation_policy_call_source($tokens, $query_index);
        if (preg_match_all('/\b(INSERT\s+INTO|UPDATE|DELETE\s+FROM|REPLACE\s+INTO|ALTER\s+TABLE|CREATE\s+(?:TEMPORARY\s+)?TABLE|DROP\s+TABLE|TRUNCATE\s+TABLE|RENAME\s+TABLE|GRANT|REVOKE|CALL|LOAD\s+DATA)\b/i', $query_source, $matches)) {
            foreach ($matches[1] as $verb) {
                $mutations[] = [
                    'index' => $method_index,
                    'name' => 'wpdb.query:' . strtoupper(strtok($verb, " \t\n\r")),
                ];
            }
        } elseif (! vg_runtime_mutation_policy_query_is_read_only_literal($tokens, $query_index)) {
            $mutations[] = ['index' => $method_index, 'name' => 'wpdb.query:DYNAMIC'];
        }
    }

    usort($mutations, static fn (array $left, array $right): int => $left['index'] <=> $right['index']);
    return $mutations;
}

/** @param list<mixed> $tokens */
function vg_runtime_mutation_policy_query_is_read_only_literal(array $tokens, int $opening_parenthesis): bool
{
    $argument = vg_runtime_mutation_policy_call_source($tokens, $opening_parenthesis);
    if (preg_match('/\A\(\s*([\'"`])\s*(SELECT|SHOW|DESCRIBE|EXPLAIN|WITH)\b/i', $argument) === 1) {
        return true;
    }

    return false;
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

/** @param list<mixed> $tokens */
function vg_runtime_mutation_policy_actual_call_index(array $tokens, int $name_index, bool $allow_method_call = false): ?int
{
    $next_index = vg_runtime_mutation_policy_next_significant_index($tokens, $name_index + 1);
    if ($next_index === null || $tokens[$next_index] !== '(') {
        return null;
    }

    $previous_index = vg_runtime_mutation_policy_previous_significant_index($tokens, $name_index - 1);
    $previous_token = $previous_index === null ? null : $tokens[$previous_index];
    $previous_text = is_array($previous_token) ? $previous_token[1] : $previous_token;
    if (is_array($previous_token) && $previous_token[0] === T_FUNCTION) {
        return null;
    }
    if ($previous_text === '&') {
        $before_reference = $previous_index === null
            ? null
            : vg_runtime_mutation_policy_previous_significant_index($tokens, $previous_index - 1);
        if ($before_reference !== null && is_array($tokens[$before_reference]) && $tokens[$before_reference][0] === T_FUNCTION) {
            return null;
        }
    }
    if (! $allow_method_call && in_array($previous_text, ['->', '?->', '::'], true)) {
        return null;
    }

    $first_argument = vg_runtime_mutation_policy_next_significant_index($tokens, $next_index + 1);
    if ($first_argument !== null && is_array($tokens[$first_argument]) && $tokens[$first_argument][0] === T_ELLIPSIS) {
        $after_ellipsis = vg_runtime_mutation_policy_next_significant_index($tokens, $first_argument + 1);
        if ($after_ellipsis !== null && $tokens[$after_ellipsis] === ')') {
            return null;
        }
    }

    return $name_index;
}

/** @param list<mixed> $tokens */
function vg_runtime_mutation_policy_previous_significant_index(array $tokens, int $start): ?int
{
    for ($index = $start; $index >= 0; $index--) {
        $token = $tokens[$index];
        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        return $index;
    }
    return null;
}

function vg_runtime_mutation_policy_call_index(string $source, string $function_name, bool $allow_method_call = false): ?int
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
        if (vg_runtime_mutation_policy_actual_call_index($tokens, $index, $allow_method_call) !== null) {
            return $index;
        }
    }
    return null;
}

/** @return list<int> */
function vg_runtime_mutation_policy_call_indexes(string $source, string $function_name, bool $allow_method_call = false): array
{
    $expected = strtolower(ltrim($function_name, '\\'));
    $tokens = token_get_all($source);
    $indexes = [];
    foreach ($tokens as $index => $token) {
        if (! is_array($token) || ! in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            continue;
        }
        $name = strtolower(ltrim($token[1], '\\'));
        $short_name = substr($name, strrpos($name, '\\') === false ? 0 : strrpos($name, '\\') + 1);
        if (($name === $expected || $short_name === $expected)
            && vg_runtime_mutation_policy_actual_call_index($tokens, $index, $allow_method_call) !== null) {
            $indexes[] = $index;
        }
    }
    return $indexes;
}

/** @return array{index:int,name:?string,parent_index:?int,parent_name:?string}|null */
function vg_runtime_mutation_policy_enclosing_function(string $source, int $target_index): ?array
{
    $tokens = token_get_all($source);
    $frames = [];
    $pending_function = null;
    $pending_function_declared = false;
    for ($index = 0, $count = min(count($tokens), $target_index + 1); $index < $count; $index++) {
        $token = $tokens[$index];
        if (is_array($token) && $token[0] === T_FUNCTION) {
            $pending_function_declared = true;
            $name_index = vg_runtime_mutation_policy_next_significant_index($tokens, $index + 1);
            if ($name_index !== null && is_array($tokens[$name_index]) && $tokens[$name_index][0] === T_STRING) {
                $pending_function = ['index' => $index, 'name' => strtolower($tokens[$name_index][1])];
            } else {
                $pending_function = ['index' => $index, 'name' => null];
            }
        }

        $text = is_array($token) ? $token[1] : $token;
        if ($text === '{') {
            $frames[] = $pending_function_declared ? $pending_function : null;
            $pending_function = null;
            $pending_function_declared = false;
        } elseif ($text === '}') {
            array_pop($frames);
        }
    }

    $function_frames = array_values(array_filter($frames, 'is_array'));
    if ($function_frames === []) {
        return null;
    }
    $current = $function_frames[array_key_last($function_frames)];
    $parent = count($function_frames) > 1 ? $function_frames[count($function_frames) - 2] : null;
    return [
        'index' => $current['index'],
        'name' => $current['name'],
        'parent_index' => is_array($parent) ? $parent['index'] : null,
        'parent_name' => is_array($parent) && is_string($parent['name']) ? $parent['name'] : null,
    ];
}

/** @return array{index:int,name:?string,parent_index:?int,parent_name:?string}|null */
function vg_runtime_mutation_policy_function_context(string $source, int $function_index): ?array
{
    $tokens = token_get_all($source);
    for ($index = $function_index + 1, $count = count($tokens); $index < $count; $index++) {
        $token = $tokens[$index];
        $text = is_array($token) ? $token[1] : $token;
        if ($text === '{') {
            return vg_runtime_mutation_policy_enclosing_function($source, $index);
        }
    }
    return null;
}

/** @param array{index:int,name:?string,parent_index:?int,parent_name:?string}|null $context */
function vg_runtime_mutation_policy_execution_index(string $source, int $token_index, ?array $context): ?int
{
    if ($context === null) {
        return $token_index;
    }
    if (! is_string($context['name'])) {
        return null;
    }
    return vg_runtime_mutation_policy_call_index($source, $context['name'], true);
}

/** @param list<int> $guard_indexes @param array<string,true> $visited */
function vg_runtime_mutation_policy_position_is_guarded(
    string $source,
    array $guard_indexes,
    int $position,
    ?array $context,
    array $visited = []
): bool {
    foreach ($guard_indexes as $guard_index) {
        $guard_context = vg_runtime_mutation_policy_enclosing_function($source, $guard_index);
        if ($guard_context === $context && $guard_index < $position) {
            return true;
        }
        if ($context !== null
            && $context['name'] === null
            && $guard_index < $position
            && (($context['parent_index'] === null && $guard_context === null)
                || ($context['parent_index'] !== null
                    && is_array($guard_context)
                    && $guard_context['index'] === $context['parent_index']))) {
            return true;
        }
        if ($context !== null && $context['name'] === null) {
            if ($context['parent_index'] === null
                && $guard_context === null
                && $guard_index < $context['index']) {
                return true;
            }
            if ($context['parent_index'] !== null) {
                $parent_context = vg_runtime_mutation_policy_function_context($source, $context['parent_index']);
                if ($parent_context !== null
                    && $guard_context === $parent_context
                    && $guard_index < $context['index']) {
                    return true;
                }
            }
        }
        if ($context === null && is_array($guard_context) && is_string($guard_context['name'])) {
            foreach (vg_runtime_mutation_policy_call_indexes($source, $guard_context['name'], true) as $guard_call) {
                if (vg_runtime_mutation_policy_enclosing_function($source, $guard_call) === null && $guard_call < $position) {
                    return true;
                }
            }
        }
    }

    if ($context === null || ! is_string($context['name']) || isset($visited[$context['name']])) {
        if ($context !== null && $context['name'] === null && $context['parent_index'] !== null) {
            $parent_context = vg_runtime_mutation_policy_function_context($source, $context['parent_index']);
            if ($parent_context !== null) {
                return vg_runtime_mutation_policy_position_is_guarded(
                    $source,
                    $guard_indexes,
                    $context['index'],
                    $parent_context,
                    $visited
                );
            }
        }
        return false;
    }

    $visited[$context['name']] = true;
    $calls = vg_runtime_mutation_policy_call_indexes($source, $context['name'], true);
    $callers = [];
    foreach ($calls as $call_index) {
        $caller_context = vg_runtime_mutation_policy_enclosing_function($source, $call_index);
        if ($caller_context !== $context) {
            $callers[] = [$call_index, $caller_context];
        }
    }
    if ($callers === []) {
        return false;
    }

    foreach ($callers as [$call_index, $caller_context]) {
        if (! vg_runtime_mutation_policy_position_is_guarded(
            $source,
            $guard_indexes,
            $call_index,
            $caller_context,
            $visited
        )) {
            return false;
        }
    }

    return true;
}

/** @return list<string> */
function vg_runtime_mutation_policy_declared_functions(string $source): array
{
    $tokens = token_get_all($source);
    $functions = [];
    for ($index = 0, $count = count($tokens); $index < $count; $index++) {
        if (! is_array($tokens[$index]) || $tokens[$index][0] !== T_FUNCTION) {
            continue;
        }
        $name_index = vg_runtime_mutation_policy_next_significant_index($tokens, $index + 1);
        if ($name_index !== null && is_array($tokens[$name_index]) && $tokens[$name_index][0] === T_STRING) {
            $functions[] = strtolower($tokens[$name_index][1]);
        }
    }
    return array_values(array_unique($functions));
}

/** @param list<mixed> $tokens */
function vg_runtime_mutation_policy_includes_path(array $tokens, string $relative_path): bool
{
    $expected_base = basename($relative_path);
    $include_tokens = [T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE];
    for ($index = 0, $count = count($tokens); $index < $count; $index++) {
        if (! is_array($tokens[$index]) || ! in_array($tokens[$index][0], $include_tokens, true)) {
            continue;
        }
        $expression = '';
        for ($cursor = $index + 1; $cursor < $count; $cursor++) {
            $token = $tokens[$cursor];
            $text = is_array($token) ? $token[1] : $token;
            if ($text === ';') {
                break;
            }
            $expression .= $text;
        }
        if (str_contains($expression, $expected_base)) {
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
        $mutation_calls = vg_runtime_mutation_policy_mutation_calls($source);
        if ($mutation_calls === []) {
            if (($entries_by_path[$path]['classification'] ?? null) === 'mutating') {
                $entry = $entries_by_path[$path];
                $guard = (string) ($entry['guard_function'] ?? '');
                if (vg_runtime_mutation_policy_call_index($source, $guard) === null) {
                    $errors[] = "{$path}: declared session guard is absent from source.";
                }
            }
            continue;
        }
        $mutations = array_values(array_unique(array_column($mutation_calls, 'name')));
        $mutation_files++;
        $entry = $entries_by_path[$path] ?? null;
        $classification = is_array($entry) ? ($entry['classification'] ?? null) : null;
        if ($classification === 'test_only' || $classification === 'explicit_exemption') {
            continue;
        }
        if ($classification === 'mutating') {
            $guard = (string) ($entry['guard_function'] ?? '');
            $guard_indexes = vg_runtime_mutation_policy_call_indexes($source, $guard);
            if ($guard_indexes === []) {
                $errors[] = "{$path}: declared session guard is absent from source.";
            } else {
                foreach ($mutation_calls as $mutation_call) {
                    $mutation_context = vg_runtime_mutation_policy_enclosing_function($source, $mutation_call['index']);
                    if (! vg_runtime_mutation_policy_position_is_guarded(
                        $source,
                        $guard_indexes,
                        $mutation_call['index'],
                        $mutation_context
                    )) {
                        $errors[] = "{$path}: session guard is absent before a detected persistent write.";
                        break;
                    }
                }
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
                if (! is_string($caller_source) || vg_runtime_mutation_policy_call_index($caller_source, $guard) === null) {
                    $errors[] = "{$path}: mutation caller lacks its declared session guard: {$caller}.";
                    continue;
                }
                $library_functions = vg_runtime_mutation_policy_declared_functions($source);
                $caller_tokens = token_get_all($caller_source);
                $includes_library = vg_runtime_mutation_policy_includes_path($caller_tokens, $path);
                $calls_library = false;
                foreach ($library_functions as $library_function) {
                    if (vg_runtime_mutation_policy_call_index($caller_source, $library_function) !== null) {
                        $calls_library = true;
                        break;
                    }
                }
                if (! $includes_library || ! $calls_library) {
                    $errors[] = "{$path}: mutation caller does not include and invoke this library: {$caller}.";
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
