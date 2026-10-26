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

/** @return list<array{start:int,end:int,id:int}> */
function vg_runtime_mutation_policy_branch_scopes(string $source): array
{
    $tokens = token_get_all($source);
    $scopes = [];
    $scope_id = 0;
    $count = count($tokens);
    // Exceptions execute catch/finally after a guard in try can throw. Keep
    // each exception path separate so only a guard outside try or inside the
    // specific cleanup path can dominate its writes.
    $control_tokens = [T_IF, T_ELSEIF, T_ELSE, T_FOR, T_FOREACH, T_WHILE, T_DO, T_SWITCH, T_CATCH, T_TRY, T_FINALLY];

    $matching_delimiter = static function (array $tokens, int $start, string $open, string $close): ?int {
        $depth = 0;
        for ($index = $start, $count = count($tokens); $index < $count; $index++) {
            $text = is_array($tokens[$index]) ? $tokens[$index][1] : $tokens[$index];
            if ($text === $open) {
                $depth++;
            } elseif ($text === $close && --$depth === 0) {
                return $index;
            }
        }
        return null;
    };

    for ($index = 0; $index < $count; $index++) {
        if (! is_array($tokens[$index]) || ! in_array($tokens[$index][0], $control_tokens, true)) {
            continue;
        }
        $next = vg_runtime_mutation_policy_next_significant_index($tokens, $index + 1);
        if ($next === null) {
            continue;
        }
        if ($tokens[$next] === '(') {
            $condition_end = $matching_delimiter($tokens, $next, '(', ')');
            if ($condition_end === null) {
                continue;
            }
            $next = vg_runtime_mutation_policy_next_significant_index($tokens, $condition_end + 1);
            if ($next === null) {
                continue;
            }
        }

        $body_text = is_array($tokens[$next]) ? $tokens[$next][1] : $tokens[$next];
        if ($body_text === '{') {
            $body_end = $matching_delimiter($tokens, $next, '{', '}');
            if ($body_end !== null) {
                $scopes[] = ['start' => $next + 1, 'end' => $body_end, 'id' => $scope_id++];
            }
            continue;
        }

        // For an unbraced control body, conservatively scope through its first top-level semicolon.
        $paren_depth = 0;
        $body_end = null;
        for ($body_index = $next; $body_index < $count; $body_index++) {
            $text = is_array($tokens[$body_index]) ? $tokens[$body_index][1] : $tokens[$body_index];
            if ($text === '(') {
                $paren_depth++;
            } elseif ($text === ')') {
                $paren_depth--;
            } elseif ($text === ';' && $paren_depth === 0) {
                $body_end = $body_index + 1;
                break;
            } elseif ($text === '}' && $paren_depth === 0) {
                $body_end = $body_index;
                break;
            }
        }
        if ($body_end !== null) {
            $scopes[] = ['start' => $next, 'end' => $body_end, 'id' => $scope_id++];
        }
    }

    // A switch body contains separate execution branches for each case/default label.
    foreach ($scopes as $scope) {
        $switch_index = null;
        for ($index = 0; $index < $scope['start']; $index++) {
            if (is_array($tokens[$index]) && $tokens[$index][0] === T_SWITCH) {
                $switch_index = $index;
            }
        }
        if ($switch_index === null) {
            continue;
        }
        $case_starts = [];
        for ($index = $scope['start']; $index < $scope['end']; $index++) {
            if (is_array($tokens[$index]) && in_array($tokens[$index][0], [T_CASE, T_DEFAULT], true)) {
                $case_starts[] = $index;
            }
        }
        foreach ($case_starts as $case_offset => $case_start) {
            $next_case = $case_starts[$case_offset + 1] ?? $scope['end'];
            $scopes[] = ['start' => $case_start, 'end' => $next_case, 'id' => $scope_id++];
        }
    }

    return $scopes;
}

/** @return list<int> */
function vg_runtime_mutation_policy_branch_path(string $source, int $position): array
{
    $path = [];
    foreach (vg_runtime_mutation_policy_branch_scopes($source) as $scope) {
        if ($scope['start'] <= $position && $position < $scope['end']) {
            $path[] = $scope['id'];
        }
    }
    return $path;
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
        if ($method_index === null) {
            continue;
        }
        if ($tokens[$method_index] === '{') {
            $dynamic_end = vg_runtime_mutation_policy_next_significant_index($tokens, $method_index + 1);
            if ($dynamic_end === null) {
                continue;
            }
            $dynamic_close = vg_runtime_mutation_policy_next_significant_index($tokens, $dynamic_end + 1);
            $dynamic_call = $dynamic_close !== null
                && $tokens[$dynamic_close] === '}'
                && vg_runtime_mutation_policy_next_significant_text($tokens, $dynamic_close + 1) === '(';
            if ($dynamic_call) {
                $mutations[] = ['index' => $method_index, 'name' => 'wpdb.dynamic-method'];
            }
            continue;
        }
        if (is_array($tokens[$method_index]) && $tokens[$method_index][0] === T_VARIABLE) {
            if (vg_runtime_mutation_policy_next_significant_text($tokens, $method_index + 1) === '(') {
                $mutations[] = ['index' => $method_index, 'name' => 'wpdb.dynamic-method'];
            }
            continue;
        }
        if (! is_array($tokens[$method_index]) || $tokens[$method_index][0] !== T_STRING) {
            continue;
        }
        $method = strtolower($tokens[$method_index][1]);
        if (! in_array($method, ['insert', 'update', 'delete', 'replace', 'query', 'get_results', 'get_var', 'get_row', 'get_col'], true)
            || vg_runtime_mutation_policy_next_significant_text($tokens, $method_index + 1) !== '(') {
            continue;
        }
        if (in_array($method, ['insert', 'update', 'delete', 'replace'], true)) {
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
                    'name' => 'wpdb.' . $method . ':' . strtoupper(strtok($verb, " \t\n\r")),
                ];
            }
        } elseif (! vg_runtime_mutation_policy_query_is_read_only_literal($tokens, $query_index, $source)) {
            $mutations[] = ['index' => $method_index, 'name' => 'wpdb.' . $method . ':DYNAMIC'];
        }
    }

    usort($mutations, static fn (array $left, array $right): int => $left['index'] <=> $right['index']);
    return $mutations;
}

/** Return delimiter text only for PHP syntax tokens, not string contents. */
function vg_runtime_mutation_policy_delimiter_text(mixed $token): ?string
{
    if (is_array($token)) {
        if ($token[0] === T_CURLY_OPEN) {
            return '{';
        }
        if (defined('T_CURLY_CLOSE') && $token[0] === T_CURLY_CLOSE) {
            return '}';
        }
        return null;
    }
    return is_string($token) && in_array($token, ['(', ')', '[', ']', '{', '}'], true) ? $token : null;
}

/** @param list<mixed> $tokens @return list<list<mixed>> */
function vg_runtime_mutation_policy_call_argument_tokens(array $tokens, int $opening_parenthesis): array
{
    $arguments = [];
    $argument = [];
    $depth = 0;
    for ($index = $opening_parenthesis + 1, $count = count($tokens); $index < $count; $index++) {
        $token = $tokens[$index];
        $text = is_array($token) ? $token[1] : $token;
        $delimiter = vg_runtime_mutation_policy_delimiter_text($token);
        if ($delimiter === ')' && $depth === 0) {
            if ($argument !== [] || $arguments !== []) {
                $arguments[] = $argument;
            }
            break;
        }
        if ($text === ',' && $depth === 0) {
            $arguments[] = $argument;
            $argument = [];
            continue;
        }
        if (in_array($delimiter, ['(', '[', '{'], true)) {
            $depth++;
        } elseif (in_array($delimiter, [')', ']', '}'], true)) {
            $depth--;
        }
        $argument[] = $token;
    }
    return $arguments;
}

/** @param list<mixed> $tokens @return list<mixed>|null */
function vg_runtime_mutation_policy_first_argument_tokens(array $tokens, int $opening_parenthesis): ?array
{
    return vg_runtime_mutation_policy_call_argument_tokens($tokens, $opening_parenthesis)[0] ?? null;
}

/** @param list<mixed> $tokens @return list<mixed> */
function vg_runtime_mutation_policy_trim_expression_tokens(array $tokens): array
{
    $tokens = array_values(array_filter($tokens, static fn (mixed $token): bool => ! is_array($token)
        || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_CLOSE_TAG], true)));
    while (count($tokens) >= 2 && $tokens[0] === '(' && end($tokens) === ')') {
        $depth = 0;
        $covers_expression = true;
        foreach ($tokens as $index => $token) {
            $delimiter = vg_runtime_mutation_policy_delimiter_text($token);
            if ($delimiter === '(') {
                $depth++;
            } elseif ($delimiter === ')') {
                $depth--;
                if ($depth === 0 && $index !== array_key_last($tokens)) {
                    $covers_expression = false;
                    break;
                }
            }
        }
        if (! $covers_expression) {
            break;
        }
        array_shift($tokens);
        array_pop($tokens);
    }
    return $tokens;
}

/** @param list<mixed> $tokens */
function vg_runtime_mutation_policy_query_is_read_only_literal(array $tokens, int $opening_parenthesis, string $source): bool
{
    $argument = vg_runtime_mutation_policy_first_argument_tokens($tokens, $opening_parenthesis);
    return is_array($argument)
        && vg_runtime_mutation_policy_query_expression_is_read_only($argument, $tokens, $source, $opening_parenthesis);
}

/**
 * Resolve only exact SQL string literals, $wpdb->prepare() calls, and a single local assignment to one of those.
 * Concatenation with unresolved values and arbitrary SQL variables remain mutation-capable.
 *
 * @param list<mixed> $expression
 * @param list<mixed> $tokens
 * @param array<string,true> $visited
 */
function vg_runtime_mutation_policy_query_expression_is_read_only(
    array $expression,
    array $tokens,
    string $source,
    int $position,
    array $visited = []
): bool {
    $expression = vg_runtime_mutation_policy_trim_expression_tokens($expression);
    if ($expression === []) {
        return false;
    }
    if (count($expression) === 1 && is_array($expression[0]) && $expression[0][0] === T_CONSTANT_ENCAPSED_STRING) {
        $literal = substr($expression[0][1], 1, -1);
        return vg_runtime_mutation_policy_sql_text_is_read_only($literal);
    }

    if (count($expression) === 1 && is_array($expression[0]) && $expression[0][0] === T_VARIABLE) {
        $variable = $expression[0][1];
        $variable_context = vg_runtime_mutation_policy_enclosing_function($source, $position);
        $visit_key = 'variable:' . (string) ($variable_context['index'] ?? 'top-level') . ':' . $variable;
        if (isset($visited[$visit_key])) {
            return false;
        }
        $visited[$visit_key] = true;
        $assignment = vg_runtime_mutation_policy_local_variable_assignment($source, $tokens, $variable, $position);
        if (is_array($assignment)) {
            return vg_runtime_mutation_policy_query_expression_is_read_only($assignment, $tokens, $source, $position, $visited);
        }
        $foreach_values = vg_runtime_mutation_policy_foreach_array_values($source, $tokens, $variable, $position);
        if (is_array($foreach_values)) {
            foreach ($foreach_values as $value) {
                if (! vg_runtime_mutation_policy_query_expression_is_read_only($value, $tokens, $source, $position, $visited)) {
                    return false;
                }
            }
            return $foreach_values !== [];
        }
        $parameter_position = vg_runtime_mutation_policy_function_parameter_position($source, $position, $variable);
        if ($parameter_position === null) {
            return false;
        }
        $context = $variable_context;
        if ($context === null || ! is_string($context['name']) || isset($visited['function:' . $context['name']])) {
            return false;
        }
        $visited['function:' . $context['name']] = true;
        $calls = vg_runtime_mutation_policy_call_indexes($source, $context['name'], true);
        if ($calls === []) {
            return false;
        }
        foreach ($calls as $call) {
            $opening = vg_runtime_mutation_policy_next_significant_index($tokens, $call + 1);
            if ($opening === null) {
                return false;
            }
            $arguments = vg_runtime_mutation_policy_call_argument_tokens($tokens, $opening);
            if (! isset($arguments[$parameter_position])
                || ! vg_runtime_mutation_policy_query_expression_is_read_only(
                    $arguments[$parameter_position],
                    $tokens,
                    $source,
                    $call,
                    $visited
                )) {
                return false;
            }
        }
        return true;
    }

    $prepare_index = null;
    foreach ($expression as $index => $token) {
        if (! is_array($token) || $token[0] !== T_STRING || strtolower($token[1]) !== 'prepare') {
            continue;
        }
        $previous = vg_runtime_mutation_policy_previous_significant_index($expression, $index - 1);
        $wpdb_index = $previous === null ? null : vg_runtime_mutation_policy_previous_significant_index($expression, $previous - 1);
        if ($previous !== null && vg_runtime_mutation_policy_next_significant_text($expression, $previous) === '->'
            && $wpdb_index !== null && is_array($expression[$wpdb_index])
            && $expression[$wpdb_index][0] === T_VARIABLE && $expression[$wpdb_index][1] === '$wpdb'
            && vg_runtime_mutation_policy_next_significant_text($expression, $index + 1) === '(') {
            $prepare_index = vg_runtime_mutation_policy_next_significant_index($expression, $index + 1);
            break;
        }
    }
    if ($prepare_index !== null) {
        $prepare_arguments = vg_runtime_mutation_policy_first_argument_tokens($expression, $prepare_index);
        return is_array($prepare_arguments)
            && vg_runtime_mutation_policy_query_expression_is_read_only($prepare_arguments, $tokens, $source, $position, $visited);
    }

    return vg_runtime_mutation_policy_composed_sql_expression_is_read_only($expression, $tokens, $source, $position, $visited);
}

/**
 * Prove a composed query is a read by allowing only literal SQL, trusted wpdb table names, and narrowly safe local fragments.
 *
 * @param list<mixed> $expression
 * @param list<mixed> $tokens
 * @param array<string,true> $visited
 */
function vg_runtime_mutation_policy_composed_sql_expression_is_read_only(
    array $expression,
    array $tokens,
    string $source,
    int $position,
    array $visited
): bool {
    $sql = '';
    $count = count($expression);
    for ($index = 0; $index < $count; $index++) {
        $token = $expression[$index];
        if (! is_array($token)) {
            if (in_array($token, ['.', '(', ')'], true)) {
                continue;
            }
            if (in_array($token, ['"', "'"], true)) {
                $quote = $token;
                $closed = false;
                for ($index++; $index < $count; $index++) {
                    $inner = $expression[$index];
                    $inner_text = is_array($inner) ? $inner[1] : $inner;
                    if ($inner_text === $quote) {
                        $closed = true;
                        break;
                    }
                    if (is_array($inner) && $inner[0] === T_ENCAPSED_AND_WHITESPACE) {
                        $sql .= $inner[1];
                        continue;
                    }
                    if (is_array($inner) && $inner[0] === T_CURLY_OPEN) {
                        $fragment = [];
                        for ($index++; $index < $count; $index++) {
                            $fragment_text = is_array($expression[$index]) ? $expression[$index][1] : $expression[$index];
                            if ($fragment_text === '}') {
                                break;
                            }
                            $fragment[] = $expression[$index];
                        }
                        if (! vg_runtime_mutation_policy_sql_fragment_is_safe($fragment, $tokens, $source, $position, $visited)) {
                            return false;
                        }
                        $sql .= ' SAFE_FRAGMENT ';
                        continue;
                    }
                    if (is_array($inner) && $inner[0] === T_VARIABLE) {
                        $fragment = [$inner];
                        if (vg_runtime_mutation_policy_next_significant_text($expression, $index + 1) === '->') {
                            $operator = vg_runtime_mutation_policy_next_significant_index($expression, $index + 1);
                            $property = $operator === null ? null : vg_runtime_mutation_policy_next_significant_index($expression, $operator + 1);
                            if ($operator !== null && $property !== null) {
                                $fragment[] = $expression[$operator];
                                $fragment[] = $expression[$property];
                                $index = $property;
                            }
                        }
                        if (! vg_runtime_mutation_policy_sql_fragment_is_safe($fragment, $tokens, $source, $position, $visited)) {
                            return false;
                        }
                        $sql .= ' SAFE_FRAGMENT ';
                        continue;
                    }
                    return false;
                }
                if (! $closed) {
                    return false;
                }
                continue;
            }
            return false;
        }

        if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
            $sql .= substr($token[1], 1, -1);
            continue;
        }
        if ($token[0] === T_VARIABLE) {
            $fragment = [$token];
            $operator = vg_runtime_mutation_policy_next_significant_index($expression, $index + 1);
            $property = $operator === null ? null : vg_runtime_mutation_policy_next_significant_index($expression, $operator + 1);
            if ($operator !== null && $property !== null
                && vg_runtime_mutation_policy_next_significant_text($expression, $operator) === '->') {
                $fragment[] = $expression[$operator];
                $fragment[] = $expression[$property];
                $index = $property;
            }
            if (! vg_runtime_mutation_policy_sql_fragment_is_safe($fragment, $tokens, $source, $position, $visited)) {
                return false;
            }
            $sql .= ' SAFE_FRAGMENT ';
            continue;
        }
        return false;
    }

    return vg_runtime_mutation_policy_sql_text_is_read_only($sql);
}

/** @param list<mixed> $fragment @param list<mixed> $tokens @param array<string,true> $visited */
function vg_runtime_mutation_policy_sql_fragment_is_safe(
    array $fragment,
    array $tokens,
    string $source,
    int $position,
    array $visited
): bool {
    $fragment = vg_runtime_mutation_policy_trim_expression_tokens($fragment);
    if (count($fragment) === 3 && is_array($fragment[0]) && $fragment[0][0] === T_VARIABLE
        && $fragment[0][1] === '$wpdb' && vg_runtime_mutation_policy_next_significant_text($fragment, 1) === '->'
        && is_array($fragment[2]) && $fragment[2][0] === T_STRING) {
        return in_array(strtolower($fragment[2][1]), [
            'posts', 'postmeta', 'comments', 'commentmeta', 'terms', 'term_taxonomy',
            'term_relationships', 'options', 'users', 'usermeta', 'links', 'prefix',
            'base_prefix', 'termmeta', 'blogs', 'blog_versions', 'registration_log',
            'signups', 'site', 'sitemeta',
        ], true);
    }
    if (count($fragment) === 4 && is_array($fragment[0]) && $fragment[0][0] === T_VARIABLE
        && $fragment[1] === '[' && is_array($fragment[2]) && $fragment[2][0] === T_CONSTANT_ENCAPSED_STRING
        && $fragment[3] === ']') {
        $variable = $fragment[0][1];
        if (isset($visited['fragment:' . $variable])) {
            return false;
        }
        $array_values = vg_runtime_mutation_policy_foreach_array_values($source, $tokens, $variable, $position);
        if (! is_array($array_values) || $array_values === []) {
            return false;
        }
        $field = substr($fragment[2][1], 1, -1);
        foreach ($array_values as $array_value) {
            $field_values = vg_runtime_mutation_policy_array_literal_string_values($array_value, $field);
            if (! is_array($field_values) || $field_values === []) {
                return false;
            }
            foreach ($field_values as $field_value) {
                if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $field_value) !== 1) {
                    return false;
                }
            }
        }
        return true;
    }
    if (count($fragment) !== 1 || ! is_array($fragment[0]) || $fragment[0][0] !== T_VARIABLE) {
        return false;
    }

    $variable = $fragment[0][1];
    if (isset($visited['fragment:' . $variable])) {
        return false;
    }
    $visited['fragment:' . $variable] = true;
    $assignment = vg_runtime_mutation_policy_local_variable_assignment($source, $tokens, $variable, $position);
    if (! is_array($assignment)) {
        return false;
    }
    $assignment_source = '';
    foreach ($assignment as $token) {
        $assignment_source .= is_array($token) ? $token[1] : $token;
    }
    $assignment_source = preg_replace('/\s+/', '', $assignment_source) ?? '';
    if (preg_match('/\A\$wpdb->prefix\.\$([A-Za-z_][A-Za-z0-9_]*)\z/', $assignment_source, $matches) === 1) {
        $suffixes = vg_runtime_mutation_policy_foreach_array_values($source, $tokens, '$' . $matches[1], $position);
        if (! is_array($suffixes) || $suffixes === []) {
            return false;
        }
        foreach ($suffixes as $suffix) {
            $suffix = vg_runtime_mutation_policy_trim_expression_tokens($suffix);
            if (count($suffix) !== 1 || ! is_array($suffix[0]) || $suffix[0][0] !== T_CONSTANT_ENCAPSED_STRING
                || preg_match('/\A[A-Za-z0-9_]+\z/', substr($suffix[0][1], 1, -1)) !== 1) {
                return false;
            }
        }
        return true;
    }
    if (str_starts_with($assignment_source, 'implode(')
        && str_contains($assignment_source, 'array_fill(')
        && preg_match('/[\'\"]%s[\'\"]/', $assignment_source) === 1) {
        return true;
    }
    if (preg_match('/\Aimplode\(\s*[\'\"]\s*,\s*[\'\"]\s*,\s*(\$[A-Za-z_][A-Za-z0-9_]*)\s*\)\z/', $assignment_source, $matches) === 1) {
        $list_assignment = vg_runtime_mutation_policy_local_variable_assignment($source, $tokens, $matches[1], $position);
        if (is_array($list_assignment)) {
            $list_source = '';
            foreach ($list_assignment as $token) {
                $list_source .= is_array($token) ? $token[1] : $token;
            }
            $list_source = preg_replace('/\s+/', '', $list_source) ?? '';
            return str_contains($list_source, "array_map('intval',")
                || str_contains($list_source, 'array_map("intval",')
                || preg_match('/array_map\(staticfn\(array\$[A-Za-z_][A-Za-z0-9_]*\):int=>\(int\)\(\$[A-Za-z_][A-Za-z0-9_]*\[[\'"]ID[\'"]\]\?\?0\),\$[A-Za-z_][A-Za-z0-9_]*\)/', $list_source) === 1
                || vg_runtime_mutation_policy_maps_to_integer_cast($list_assignment);
        }
    }
    return false;
}

/** @param list<mixed> $expression @return list<string>|null */
function vg_runtime_mutation_policy_array_literal_string_values(array $expression, string $key): ?array
{
    $expression = vg_runtime_mutation_policy_trim_expression_tokens($expression);
    if (count($expression) < 2 || $expression[0] !== '[' || end($expression) !== ']') {
        return null;
    }
    $values = [];
    $entry = [];
    $depth = 0;
    for ($index = 1, $last = count($expression) - 1; $index < $last; $index++) {
        $token = $expression[$index];
        $delimiter = vg_runtime_mutation_policy_delimiter_text($token);
        if (! is_array($token) && $token === ',' && $depth === 0) {
            $value = vg_runtime_mutation_policy_array_literal_entry_value($entry, $key);
            if ($value !== null) {
                $values[] = $value;
            }
            $entry = [];
            continue;
        }
        if (in_array($delimiter, ['(', '[', '{'], true)) {
            $depth++;
        } elseif (in_array($delimiter, [')', ']', '}'], true)) {
            $depth--;
        }
        $entry[] = $token;
    }
    $value = vg_runtime_mutation_policy_array_literal_entry_value($entry, $key);
    if ($value !== null) {
        $values[] = $value;
    }
    return $values === [] ? null : $values;
}

/** @param list<mixed> $entry */
function vg_runtime_mutation_policy_array_literal_entry_value(array $entry, string $key): ?string
{
    $depth = 0;
    $arrow = null;
    foreach ($entry as $index => $token) {
        $delimiter = vg_runtime_mutation_policy_delimiter_text($token);
        if ($delimiter === '(' || $delimiter === '[' || $delimiter === '{') {
            $depth++;
        } elseif ($delimiter === ')' || $delimiter === ']' || $delimiter === '}') {
            $depth--;
        } elseif ($depth === 0 && is_array($token) && $token[0] === T_DOUBLE_ARROW) {
            $arrow = $index;
            break;
        }
    }
    if ($arrow === null) {
        return null;
    }
    $key_tokens = vg_runtime_mutation_policy_trim_expression_tokens(array_slice($entry, 0, $arrow));
    $value_tokens = vg_runtime_mutation_policy_trim_expression_tokens(array_slice($entry, $arrow + 1));
    if (count($key_tokens) !== 1 || ! is_array($key_tokens[0]) || $key_tokens[0][0] !== T_CONSTANT_ENCAPSED_STRING
        || substr($key_tokens[0][1], 1, -1) !== $key || count($value_tokens) !== 1
        || ! is_array($value_tokens[0]) || $value_tokens[0][0] !== T_CONSTANT_ENCAPSED_STRING) {
        return null;
    }
    return substr($value_tokens[0][1], 1, -1);
}

/** @param list<mixed> $expression */
function vg_runtime_mutation_policy_maps_to_integer_cast(array $expression): bool
{
    foreach ($expression as $index => $token) {
        if (! is_array($token) || $token[0] !== T_STRING || strtolower($token[1]) !== 'array_map'
            || vg_runtime_mutation_policy_next_significant_text($expression, $index + 1) !== '(') {
            continue;
        }
        $opening = vg_runtime_mutation_policy_next_significant_index($expression, $index + 1);
        $arguments = $opening === null ? [] : vg_runtime_mutation_policy_call_argument_tokens($expression, $opening);
        $mapper = vg_runtime_mutation_policy_trim_expression_tokens($arguments[0] ?? []);
        $arrow = null;
        $depth = 0;
        foreach ($mapper as $mapper_index => $mapper_token) {
            $delimiter = vg_runtime_mutation_policy_delimiter_text($mapper_token);
            if ($delimiter === '(' || $delimiter === '[' || $delimiter === '{') {
                $depth++;
            } elseif ($delimiter === ')' || $delimiter === ']' || $delimiter === '}') {
                $depth--;
            } elseif ($depth === 0 && is_array($mapper_token) && $mapper_token[0] === T_DOUBLE_ARROW) {
                $arrow = $mapper_index;
                break;
            }
        }
        if ($arrow === null) {
            continue;
        }
        $body = vg_runtime_mutation_policy_trim_expression_tokens(array_slice($mapper, $arrow + 1));
        if (count($body) < 5 || $body[0] !== '(' || ! is_array($body[1]) || $body[1][0] !== T_INT_CAST
            || $body[2] !== ')' || $body[3] !== '(' || end($body) !== ')') {
            continue;
        }
        $body_depth = 0;
        $body_is_single_cast = true;
        foreach (array_slice($body, 3) as $body_index => $body_token) {
            $delimiter = vg_runtime_mutation_policy_delimiter_text($body_token);
            if ($delimiter === '(') {
                $body_depth++;
            } elseif ($delimiter === ')') {
                $body_depth--;
                if ($body_depth === 0 && $body_index !== array_key_last(array_slice($body, 3))) {
                    $body_is_single_cast = false;
                    break;
                }
            }
        }
        if ($body_is_single_cast) {
            return true;
        }
    }
    return false;
}

/** Return the zero-based parameter position for a variable in the current function, if any. */
function vg_runtime_mutation_policy_function_parameter_position(string $source, int $position, string $variable): ?int
{
    $context = vg_runtime_mutation_policy_enclosing_function($source, $position);
    if ($context === null || ! is_string($context['name'])) {
        return null;
    }
    $tokens = token_get_all($source);
    $opening = null;
    for ($index = $context['index'] + 1, $count = count($tokens); $index < $count; $index++) {
        $token_text = is_array($tokens[$index]) ? $tokens[$index][1] : $tokens[$index];
        if ($token_text === '(') {
            $opening = $index;
            break;
        }
    }
    if ($opening === null) {
        return null;
    }
    foreach (vg_runtime_mutation_policy_call_argument_tokens($tokens, $opening) as $parameter_index => $parameter) {
        foreach ($parameter as $token) {
            if (is_array($token) && $token[0] === T_VARIABLE && $token[1] === $variable) {
                return $parameter_index;
            }
        }
    }
    return null;
}

/** @param list<mixed> $tokens */
function vg_runtime_mutation_policy_sql_text_is_read_only(string $query): bool
{
    if (preg_match('/\A\s*(?:SELECT|SHOW|DESCRIBE|EXPLAIN|WITH)\b/i', $query) !== 1
        || preg_match('/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM|REPLACE\s+INTO|ALTER\s+TABLE|CREATE\s+(?:TEMPORARY\s+)?TABLE|DROP\s+TABLE|TRUNCATE\s+TABLE|RENAME\s+TABLE|GRANT|REVOKE|CALL|LOAD\s+DATA)\b/i', $query) === 1
        || preg_match('/\bINTO\s+(?:OUTFILE|DUMPFILE)\b/i', $query) === 1) {
        return false;
    }
    return true;
}

/** @param list<mixed> $tokens @return list<mixed>|null */
function vg_runtime_mutation_policy_local_variable_assignment(string $source, array $tokens, string $variable, int $position): ?array
{
    $context = vg_runtime_mutation_policy_enclosing_function($source, $position);
    $assignments = [];
    foreach ($tokens as $index => $token) {
        if ($index >= $position || ! is_array($token) || $token[0] !== T_VARIABLE || $token[1] !== $variable
            || vg_runtime_mutation_policy_enclosing_function($source, $index) !== $context) {
            continue;
        }
        $operator = vg_runtime_mutation_policy_next_significant_index($tokens, $index + 1);
        if ($operator !== null && ($tokens[$operator] === '=' || (is_array($tokens[$operator]) && $tokens[$operator][0] === T_CONCAT_EQUAL))) {
            $assignments[] = [$index, $operator];
        }
    }
    if ($assignments === []) {
        return null;
    }
    $base_assignments = array_values(array_filter(
        $assignments,
        static fn (array $assignment): bool => $tokens[$assignment[1]] === '='
    ));
    if (count($base_assignments) !== 1 || $assignments[0] !== $base_assignments[0]) {
        return null;
    }
    $assignment_branches = vg_runtime_mutation_policy_branch_path($source, $assignments[0][0]);
    $position_branches = vg_runtime_mutation_policy_branch_path($source, $position);
    if (array_diff($assignment_branches, $position_branches) !== []) {
        return null;
    }

    $expression = [];
    foreach ($assignments as $assignment_number => [$variable_index, $operator_index]) {
        $right_hand_side = [];
        $depth = 0;
        for ($index = $operator_index + 1, $count = count($tokens); $index < $count; $index++) {
            $token = $tokens[$index];
            $text = is_array($token) ? $token[1] : $token;
            $delimiter = vg_runtime_mutation_policy_delimiter_text($token);
            if ($text === ';' && ! is_array($token) && $depth === 0) {
                break;
            }
            if (in_array($delimiter, ['(', '[', '{'], true)) {
                $depth++;
            } elseif (in_array($delimiter, [')', ']', '}'], true)) {
                $depth--;
            }
            $right_hand_side[] = $token;
        }
        if ($assignment_number > 0) {
            $expression[] = '.';
        }
        array_push($expression, ...$right_hand_side);
    }
    return $expression;
}

/**
 * Resolve a foreach value only when its iterable is a local literal array and
 * every array entry can be inspected independently.
 *
 * @param list<mixed> $tokens
 * @return list<list<mixed>>|null
 */
function vg_runtime_mutation_policy_foreach_array_values(string $source, array $tokens, string $value_variable, int $position): ?array
{
    $context = vg_runtime_mutation_policy_enclosing_function($source, $position);
    foreach ($tokens as $index => $token) {
        if ($index >= $position || ! is_array($token) || $token[0] !== T_FOREACH
            || vg_runtime_mutation_policy_enclosing_function($source, $index) !== $context) {
            continue;
        }
        $opening = vg_runtime_mutation_policy_next_significant_index($tokens, $index + 1);
        if ($opening === null || $tokens[$opening] !== '(') {
            continue;
        }
        $depth = 0;
        $closing = null;
        for ($cursor = $opening; $cursor < count($tokens); $cursor++) {
            $delimiter = vg_runtime_mutation_policy_delimiter_text($tokens[$cursor]);
            if ($delimiter === '(') {
                $depth++;
            } elseif ($delimiter === ')' && --$depth === 0) {
                $closing = $cursor;
                break;
            }
        }
        if ($closing === null) {
            continue;
        }

        $as_index = null;
        for ($cursor = $opening + 1; $cursor < $closing; $cursor++) {
            if (is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_AS) {
                $as_index = $cursor;
                break;
            }
        }
        if ($as_index === null) {
            continue;
        }
        $foreach_variables = [];
        for ($cursor = $as_index + 1; $cursor < $closing; $cursor++) {
            if (is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_VARIABLE) {
                $foreach_variables[] = $cursor;
            }
        }
        if ($foreach_variables === []) {
            continue;
        }
        $has_key_value_pair = false;
        for ($cursor = $as_index + 1; $cursor < $closing; $cursor++) {
            if (is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_DOUBLE_ARROW) {
                $has_key_value_pair = true;
                break;
            }
        }
        $key_variable_index = $has_key_value_pair ? $foreach_variables[0] : null;
        $value_variable_index = $foreach_variables[array_key_last($foreach_variables)];
        $use_keys = $key_variable_index !== null && $tokens[$key_variable_index][1] === $value_variable;
        if (! $use_keys && $tokens[$value_variable_index][1] !== $value_variable) {
            continue;
        }

        $body_start = vg_runtime_mutation_policy_next_significant_index($tokens, $closing + 1);
        if ($body_start === null || $tokens[$body_start] !== '{') {
            continue;
        }
        $body_depth = 0;
        $body_end = null;
        for ($cursor = $body_start; $cursor < count($tokens); $cursor++) {
            $delimiter = vg_runtime_mutation_policy_delimiter_text($tokens[$cursor]);
            if ($delimiter === '{') {
                $body_depth++;
            } elseif ($delimiter === '}' && --$body_depth === 0) {
                $body_end = $cursor;
                break;
            }
        }
        if ($body_end === null || ! ($body_start < $position && $position < $body_end)) {
            continue;
        }

        $iterable = vg_runtime_mutation_policy_trim_expression_tokens(array_slice($tokens, $opening + 1, $as_index - $opening - 1));
        if (count($iterable) !== 1 || ! is_array($iterable[0]) || $iterable[0][0] !== T_VARIABLE) {
            return null;
        }
        $iterable_assignment = vg_runtime_mutation_policy_local_variable_assignment(
            $source,
            $tokens,
            $iterable[0][1],
            $index
        );
        if (! is_array($iterable_assignment)) {
            return null;
        }
        $iterable_assignment = vg_runtime_mutation_policy_trim_expression_tokens($iterable_assignment);
        if (count($iterable_assignment) < 2 || $iterable_assignment[0] !== '[' || end($iterable_assignment) !== ']') {
            return null;
        }

        $entries = [];
        $entry = [];
        $nested_depth = 0;
        for ($cursor = 1, $last = count($iterable_assignment) - 1; $cursor < $last; $cursor++) {
            $item = $iterable_assignment[$cursor];
            $delimiter = vg_runtime_mutation_policy_delimiter_text($item);
            if (! is_array($item) && $item === ',' && $nested_depth === 0) {
                if ($entry !== []) {
                    $entries[] = $entry;
                }
                $entry = [];
                continue;
            }
            if (in_array($delimiter, ['(', '[', '{'], true)) {
                $nested_depth++;
            } elseif (in_array($delimiter, [')', ']', '}'], true)) {
                $nested_depth--;
            }
            $entry[] = $item;
        }
        if ($entry !== []) {
            $entries[] = $entry;
        }
        $values = [];
        foreach ($entries as $entry) {
            $entry_depth = 0;
            $arrow = null;
            foreach ($entry as $entry_index => $entry_token) {
                $delimiter = vg_runtime_mutation_policy_delimiter_text($entry_token);
                if (! is_array($entry_token) && $entry_token === ',' && $entry_depth === 0) {
                    break;
                }
                if ($delimiter === '(' || $delimiter === '[' || $delimiter === '{') {
                    $entry_depth++;
                } elseif ($delimiter === ')' || $delimiter === ']' || $delimiter === '}') {
                    $entry_depth--;
                } elseif ($entry_depth === 0 && is_array($entry_token) && $entry_token[0] === T_DOUBLE_ARROW) {
                    $arrow = $entry_index;
                    break;
                }
            }
            if ($use_keys) {
                if ($arrow === null) {
                    return null;
                }
                $values[] = array_slice($entry, 0, $arrow);
            } elseif ($arrow !== null) {
                $values[] = array_slice($entry, $arrow + 1);
            } else {
                $values[] = $entry;
            }
        }
        return $values;
    }
    return null;
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

/** Accept a guard only as a complete, unconditional expression statement. */
function vg_runtime_mutation_policy_is_unconditional_guard_statement(string $source, int $position): bool
{
    $tokens = token_get_all($source);
    $opening = vg_runtime_mutation_policy_next_significant_index($tokens, $position + 1);
    if ($opening === null || $tokens[$opening] !== '(') {
        return false;
    }
    $depth = 0;
    $closing = null;
    for ($index = $opening, $count = count($tokens); $index < $count; $index++) {
        $text = is_array($tokens[$index]) ? $tokens[$index][1] : $tokens[$index];
        if ($text === '(') {
            $depth++;
        } elseif ($text === ')' && --$depth === 0) {
            $closing = $index;
            break;
        }
    }
    if ($closing === null || vg_runtime_mutation_policy_next_significant_text($tokens, $closing + 1) !== ';') {
        return false;
    }
    $previous = vg_runtime_mutation_policy_previous_significant_index($tokens, $position - 1);
    if ($previous === null) {
        return true;
    }
    $previous_token = $tokens[$previous];
    $previous_text = is_array($previous_token) ? $previous_token[1] : $previous_token;
    if (in_array($previous_text, [';', '{', '}', ':'], true)) {
        return true;
    }
    return is_array($previous_token) && in_array($previous_token[0], [T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO], true);
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
        if (! vg_runtime_mutation_policy_is_unconditional_guard_statement($source, $guard_index)) {
            continue;
        }
        $guard_context = vg_runtime_mutation_policy_enclosing_function($source, $guard_index);
        $guard_branches = vg_runtime_mutation_policy_branch_path($source, $guard_index);
        $position_branches = vg_runtime_mutation_policy_branch_path($source, $position);
        $guard_is_on_every_path_to_position = array_diff($guard_branches, $position_branches) === [];
        if ($guard_context === $context && $guard_index < $position && $guard_is_on_every_path_to_position) {
            return true;
        }
        if ($context !== null
            && $context['name'] === null
            && $guard_index < $position
            && $guard_is_on_every_path_to_position
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
                $guard_call_branches = vg_runtime_mutation_policy_branch_path($source, $guard_call);
                $position_branches = vg_runtime_mutation_policy_branch_path($source, $position);
                if (vg_runtime_mutation_policy_enclosing_function($source, $guard_call) === null
                    && $guard_call < $position
                    && array_diff($guard_call_branches, $position_branches) === []
                    && vg_runtime_mutation_policy_is_unconditional_guard_statement($source, $guard_call)) {
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

/** @param list<array{index:int,name:string}> $mutation_calls @return array{functions:list<string>,top_level:bool} */
function vg_runtime_mutation_policy_mutation_reaching_functions(string $source, array $mutation_calls): array
{
    $reachable = [];
    $top_level = false;
    foreach ($mutation_calls as $mutation_call) {
        $context = vg_runtime_mutation_policy_enclosing_function($source, $mutation_call['index']);
        while ($context !== null && ! is_string($context['name']) && $context['parent_index'] !== null) {
            $context = vg_runtime_mutation_policy_function_context($source, $context['parent_index']);
        }
        if ($context === null || ! is_string($context['name'])) {
            $top_level = true;
        } else {
            $reachable[$context['name']] = true;
        }
    }

    $declared = vg_runtime_mutation_policy_declared_functions($source);
    do {
        $changed = false;
        foreach ($declared as $candidate) {
            if (isset($reachable[$candidate])) {
                continue;
            }
            foreach (array_keys($reachable) as $callee) {
                foreach (vg_runtime_mutation_policy_call_indexes($source, $callee, true) as $call_index) {
                    $context = vg_runtime_mutation_policy_enclosing_function($source, $call_index);
                    if (($context['name'] ?? null) === $candidate || ($context['parent_name'] ?? null) === $candidate) {
                        $reachable[$candidate] = true;
                        $changed = true;
                        break 2;
                    }
                }
            }
        }
    } while ($changed);

    $functions = array_keys($reachable);
    sort($functions);
    return ['functions' => $functions, 'top_level' => $top_level];
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
                if (! is_string($caller_source)) {
                    $errors[] = "{$path}: mutation caller lacks its declared session guard: {$caller}.";
                    continue;
                }
                $guard_indexes = vg_runtime_mutation_policy_call_indexes($caller_source, $guard);
                $caller_tokens = token_get_all($caller_source);
                $includes_library = vg_runtime_mutation_policy_includes_path($caller_tokens, $path);
                $calls_library = false;
                $mutation_reaching_functions = vg_runtime_mutation_policy_mutation_reaching_functions($source, $mutation_calls);
                if ($mutation_reaching_functions['top_level']) {
                    $calls_library = $includes_library;
                }
                foreach ($mutation_reaching_functions['functions'] as $library_function) {
                    foreach (vg_runtime_mutation_policy_call_indexes($caller_source, $library_function) as $library_call) {
                        $calls_library = true;
                        $call_context = vg_runtime_mutation_policy_enclosing_function($caller_source, $library_call);
                        if (! vg_runtime_mutation_policy_position_is_guarded(
                            $caller_source,
                            $guard_indexes,
                            $library_call,
                            $call_context
                        )) {
                            $errors[] = "{$path}: mutation caller does not guard its library invocation: {$caller}.";
                            break 2;
                        }
                    }
                }
                if ($guard_indexes === []) {
                    $errors[] = "{$path}: mutation caller lacks its declared session guard: {$caller}.";
                }
                if (! $includes_library || ! $calls_library) {
                    $errors[] = "{$path}: mutation caller does not include and invoke a function that reaches this library's write: {$caller}.";
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
