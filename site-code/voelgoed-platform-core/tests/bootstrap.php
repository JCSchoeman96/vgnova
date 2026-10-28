<?php
declare(strict_types=1);

if (! function_exists('vg_runtime_mutation_assert_session')) {
    /** @return array{valid:bool,target:string} */
    function vg_runtime_mutation_assert_session(string $expected_target, ?array $environment = null, ?callable $database_lock_owner = null): array
    {
        $GLOBALS['vgpc_test_mutation_session_calls'][] = $expected_target;
        return ['valid' => true, 'target' => $expected_target];
    }
}

if (! function_exists('get_transient')) {
    function get_transient(string $key): mixed { return $GLOBALS['vgpc_test_transients'][$key] ?? false; }
    function set_transient(string $key, mixed $value, int $expiration = 0): bool { $GLOBALS['vgpc_test_transients'][$key] = $value; return true; }
    function delete_transient(string $key): bool { unset($GLOBALS['vgpc_test_transients'][$key]); return true; }
}
if (! function_exists('wp_next_scheduled')) {
    function wp_next_scheduled(string $hook, array $args = []): int|false { return false; }
    function wp_schedule_single_event(int $timestamp, string $hook, array $args = []): bool { $GLOBALS['vgpc_test_scheduled_events'][] = [$timestamp, $hook, $args]; return true; }
}

if (! class_exists('WP_Error')) {
    class WP_Error
    {
        public function __construct(
            private string $code = '',
            private string $message = ''
        ) {
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }
    }
}

final class VGPC_TestWpdb
{
    public string $prefix = 'wp_';
    public string $posts = 'wp_posts';

    public string $postmeta = 'wp_postmeta';

    public string $last_error = '';

    public array $inserted = [];

    public bool $fail_query = false;

    public bool $fail_insert = false;

    /** @var callable|null */
    public $query_handler = null;

    public function query(array|string $statement): int|false
    {
        $statement = is_array($statement) ? $statement : ['query' => $statement, 'args' => []];
        $this->queries[] = $statement;
        if (is_callable($this->query_handler)) ($this->query_handler)($statement['query'], $statement['args']);
        if ($this->fail_query && ! str_contains($statement['query'], 'ROLLBACK')) return false;
        if (str_contains($statement['query'], 'DELETE dirty FROM wp_postmeta')) {
            [$generation_key, $snapshot, $post_id, $dirty_key] = $statement['args'];
            $meta = &$GLOBALS['vgpc_test_meta'][(int) $post_id];
            if (($meta[$dirty_key] ?? null) === 1
                && (! array_key_exists($generation_key, $meta) || (string) $meta[$generation_key] === (string) $snapshot)) {
                unset($meta[$dirty_key]);
            }
        }
        return 1;
    }

    public function get_results(array|string $statement, mixed $format = null): array
    {
        $statement = is_array($statement) ? $statement : ['query' => $statement, 'args' => []];
        $this->queries[] = $statement;
        $is_schema_query = str_contains($statement['query'], 'SHOW FULL COLUMNS') || str_contains($statement['query'], 'SHOW INDEX');
        if ($is_schema_query && is_callable($this->get_schema_results_handler ?? null)) {
            $rows = ($this->get_schema_results_handler)($statement['query'], $statement['args']);
        } elseif (is_callable($this->get_results_handler ?? null) && ! $is_schema_query) {
            $rows = ($this->get_results_handler)($statement['query'], $statement['args']);
        } elseif (str_contains($statement['query'], 'SHOW FULL COLUMNS')) {
            $rows = [
                ['Field' => 'id', 'Type' => 'bigint(20) unsigned', 'Null' => 'NO', 'Default' => null, 'Extra' => 'auto_increment'],
                ['Field' => 'source_post_id', 'Type' => 'bigint(20) unsigned', 'Null' => 'NO', 'Default' => null, 'Extra' => ''],
                ['Field' => 'source_post_type', 'Type' => 'varchar(32)', 'Null' => 'NO', 'Default' => null, 'Extra' => ''],
                ['Field' => 'person_id', 'Type' => 'bigint(20) unsigned', 'Null' => 'NO', 'Default' => null, 'Extra' => ''],
                ['Field' => 'role_key', 'Type' => 'varchar(64)', 'Null' => 'NO', 'Default' => null, 'Extra' => ''],
                ['Field' => 'position', 'Type' => 'int(10) unsigned', 'Null' => 'NO', 'Default' => '0', 'Extra' => ''],
            ];
        } elseif (str_contains($statement['query'], 'SHOW INDEX')) {
            $rows = [
                ['Key_name' => 'PRIMARY', 'Column_name' => 'id', 'Non_unique' => 0, 'Seq_in_index' => 1],
                ['Key_name' => 'source_person_role', 'Column_name' => 'source_post_id', 'Non_unique' => 0, 'Seq_in_index' => 1],
                ['Key_name' => 'source_person_role', 'Column_name' => 'person_id', 'Non_unique' => 0, 'Seq_in_index' => 2],
                ['Key_name' => 'source_person_role', 'Column_name' => 'role_key', 'Non_unique' => 0, 'Seq_in_index' => 3],
                ['Key_name' => 'person_source_type', 'Column_name' => 'person_id', 'Non_unique' => 1, 'Seq_in_index' => 1],
                ['Key_name' => 'person_source_type', 'Column_name' => 'source_post_type', 'Non_unique' => 1, 'Seq_in_index' => 2],
                ['Key_name' => 'person_role', 'Column_name' => 'person_id', 'Non_unique' => 1, 'Seq_in_index' => 1],
                ['Key_name' => 'person_role', 'Column_name' => 'role_key', 'Non_unique' => 1, 'Seq_in_index' => 2],
                ['Key_name' => 'source_post', 'Column_name' => 'source_post_id', 'Non_unique' => 1, 'Seq_in_index' => 1],
            ];
        } else {
            $rows = [];
        }
        $this->last_error = '';
        return $rows;
    }

    public function insert(string $table, array $data, array $format = []): int|false { $this->inserted[] = [$table, $data]; return $this->fail_insert ? false : 1; }

    public function get_charset_collate(): string { return 'DEFAULT CHARACTER SET utf8mb4'; }

    /** @var list<array{query:string,args:list<mixed>}> */
    public array $queries = [];

    /** @var callable|null */
    public $get_var_handler = null;

    /** @var callable|null */
    public $get_col_handler = null;

    /** @var callable|null */
    public $get_results_handler = null;

    /** @var callable|null */
    public $get_schema_results_handler = null;

    /** @var callable|null */
    public $get_schema_status_handler = null;

    /** @var callable|null */
    public $get_row_handler = null;

    /** @param list<mixed> $args */
    public function prepare(string $query, mixed ...$args): array
    {
        return ['query' => $query, 'args' => $args];
    }

    /** @param array{query:string,args:list<mixed>} $statement */
    public function get_var(array $statement): mixed
    {
        $this->queries[] = $statement;
        if (str_contains($statement['query'], 'GET_LOCK')) $GLOBALS['vgpc_test_database_sequence'][] = 'get_lock';
        if (is_callable($this->get_var_handler)) {
            return ($this->get_var_handler)($statement['query'], $statement['args']);
        }

        return '1';
    }

    /** @param array{query:string,args:list<mixed>} $statement */
    public function get_col(array $statement): array
    {
        if (is_callable($this->get_col_handler)) {
            return ($this->get_col_handler)($statement['query'], $statement['args']);
        }

        if (! str_contains($statement['query'], 'SELECT DISTINCT participation.ID')) {
            return [];
        }

        $args = $statement['args'];
        $event_field_index = array_search('event_participation_event', $args, true);
        $person_field_index = array_search('event_participation_person', $args, true);
        if (! is_int($event_field_index) || ! is_int($person_field_index)) {
            return [];
        }
        $event_id = (string) ($args[$event_field_index + 1] ?? '');
        $person_id = (string) ($args[$person_field_index + 1] ?? '');
        $post_type_index = array_search('event_participation', $args, true);
        $post_type = is_int($post_type_index) ? (string) $args[$post_type_index] : '';
        $statuses = ['publish', 'draft', 'pending', 'private', 'future'];
        $exclude_id = str_contains($statement['query'], 'participation.ID <> %d')
            ? (int) end($args)
            : 0;

        $matches = [];
        foreach ($GLOBALS['vgpc_test_posts'] as $post_id => $post) {
            if (
                ($post->post_type ?? null) !== $post_type
                || ! in_array((string) ($post->post_status ?? ''), $statuses, true)
                || (int) $post_id === $exclude_id
            ) {
                continue;
            }
            if (
                (string) ($GLOBALS['vgpc_test_meta'][$post_id]['event_participation_event'] ?? '') === $event_id
                && (string) ($GLOBALS['vgpc_test_meta'][$post_id]['event_participation_person'] ?? '') === $person_id
            ) {
                $matches[] = (int) $post_id;
            }
        }
        sort($matches, SORT_NUMERIC);
        return $matches;
    }

    /** @param array{query:string,args:list<mixed>} $statement */
    public function get_row(array $statement): object|null
    {
        if (str_contains($statement['query'], 'SHOW TABLE STATUS')) {
            return is_callable($this->get_schema_status_handler ?? null)
                ? ($this->get_schema_status_handler)($statement['query'], $statement['args'])
                : (object) ['Engine' => 'InnoDB'];
        }
        if (is_callable($this->get_row_handler)) {
            return ($this->get_row_handler)($statement['query'], $statement['args']);
        }

        $args = $statement['args'];
        if (str_contains($statement['query'], 'FROM wp_posts')) {
            $post = $GLOBALS['vgpc_test_posts'][(int) ($args[0] ?? 0)] ?? null;
            return is_object($post)
                ? (object) ['post_type' => $post->post_type ?? '', 'post_status' => $post->post_status ?? '']
                : null;
        }
        if (str_contains($statement['query'], 'FROM wp_postmeta')) {
            $value = $GLOBALS['vgpc_test_meta'][(int) ($args[0] ?? 0)][(string) ($args[1] ?? '')] ?? null;
            return $value === null ? null : (object) ['meta_value' => $value];
        }

        return null;
    }
}

if (! class_exists('WP_CLI')) {
    class WP_CLI
    {
        public static array $commands = [];
        public static array $messages = [];

        public static function add_command(string $name, callable $callable): void
        {
            self::$commands[$name] = $callable;
        }

        public static function success(string $message): void
        {
            self::$messages[] = ['type' => 'success', 'message' => $message];
        }

        public static function line(string $message): void
        {
            self::$messages[] = ['type' => 'line', 'message' => $message];
        }

        public static function error(string $message): never
        {
            throw new RuntimeException($message);
        }
    }
}

if (! defined('WP_CLI')) {
    define('WP_CLI', true);
}

if (! defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (! defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

$GLOBALS['vgpc_test_taxonomies'] = [];
$GLOBALS['vgpc_test_terms'] = [];
$GLOBALS['vgpc_test_options'] = [];
$GLOBALS['vgpc_test_actions'] = [];
$GLOBALS['vgpc_test_next_term_id'] = 1000;
$GLOBALS['vgpc_test_insert_count'] = 0;
$GLOBALS['vgpc_test_rewrite_flush_count'] = 0;
$GLOBALS['vgpc_test_force_suffix_next_insert'] = false;
$GLOBALS['vgpc_test_fail_next_term_delete'] = false;
$GLOBALS['vgpc_test_posts'] = [];
$GLOBALS['vgpc_test_next_post_id'] = 5000;
$GLOBALS['vgpc_test_insert_post_calls'] = [];
$GLOBALS['vgpc_test_update_post_calls'] = [];
$GLOBALS['vgpc_test_delete_post_calls'] = [];
$GLOBALS['vgpc_test_relationships'] = [];
$GLOBALS['vgpc_test_meta'] = [];
$GLOBALS['vgpc_test_field_groups'] = [];
$GLOBALS['vgpc_test_update_field_calls'] = [];
$GLOBALS['vgpc_test_fail_update_field_name'] = null;
$GLOBALS['vgpc_test_filters'] = [];
$GLOBALS['vgpc_test_screen'] = null;
$GLOBALS['vgpc_test_set_term_calls'] = 0;
$GLOBALS['vgpc_test_fail_set_term_call'] = null;
$GLOBALS['vgpc_test_fail_get_post_id'] = null;
$GLOBALS['vgpc_test_acf_save_during_update'] = false;
$GLOBALS['vgpc_test_acf_save_release_counts'] = [];
$GLOBALS['vgpc_test_apply_wp_update_filter'] = false;
$GLOBALS['vgpc_test_enable_query_cache'] = false;
$GLOBALS['vgpc_test_get_posts_cache'] = [];
$GLOBALS['vgpc_test_get_posts_handler'] = null;
$GLOBALS['vgpc_test_get_post_cache'] = [];
$GLOBALS['vgpc_test_get_field_cache'] = [];
$GLOBALS['vgpc_test_database_sequence'] = [];

function vgpc_test_reset(): void
{
    $GLOBALS['vgpc_test_transients'] = [];
    $GLOBALS['vgpc_test_scheduled_events'] = [];
    $GLOBALS['vgpc_test_taxonomies'] = [];
    $GLOBALS['vgpc_test_terms'] = [];
    $GLOBALS['vgpc_test_options'] = [];
    $GLOBALS['vgpc_test_actions'] = [];
    $GLOBALS['vgpc_test_next_term_id'] = 1000;
    $GLOBALS['vgpc_test_insert_count'] = 0;
    $GLOBALS['vgpc_test_rewrite_flush_count'] = 0;
    $GLOBALS['vgpc_test_force_suffix_next_insert'] = false;
    $GLOBALS['vgpc_test_fail_next_term_delete'] = false;
    $GLOBALS['vgpc_test_posts'] = [];
    $GLOBALS['vgpc_test_next_post_id'] = 5000;
    $GLOBALS['vgpc_test_insert_post_calls'] = [];
    $GLOBALS['vgpc_test_update_post_calls'] = [];
    $GLOBALS['vgpc_test_delete_post_calls'] = [];
    $GLOBALS['vgpc_test_relationships'] = [];
    $GLOBALS['vgpc_test_meta'] = [];
    $GLOBALS['vgpc_update_post_meta_handler'] = null;
    $GLOBALS['vgpc_delete_post_meta_handler'] = null;
    $GLOBALS['vgpc_test_field_groups'] = [];
    $GLOBALS['vgpc_test_update_field_calls'] = [];
    $GLOBALS['vgpc_test_fail_update_field_name'] = null;
    $GLOBALS['vgpc_test_filters'] = [];
    $GLOBALS['vgpc_test_screen'] = null;
    $GLOBALS['vgpc_test_set_term_calls'] = 0;
    $GLOBALS['vgpc_test_fail_set_term_call'] = null;
$GLOBALS['vgpc_test_fail_get_post_id'] = null;
$GLOBALS['vgpc_test_acf_save_during_update'] = false;
$GLOBALS['vgpc_test_acf_save_release_counts'] = [];
$GLOBALS['vgpc_test_apply_wp_update_filter'] = false;
    $GLOBALS['vgpc_test_enable_query_cache'] = false;
    $GLOBALS['vgpc_test_get_posts_cache'] = [];
$GLOBALS['vgpc_test_get_posts_handler'] = null;
    $GLOBALS['vgpc_test_get_post_cache'] = [];
    $GLOBALS['vgpc_test_get_field_cache'] = [];
    $GLOBALS['vgpc_test_database_sequence'] = [];
    $GLOBALS['wpdb'] = new VGPC_TestWpdb();
    $GLOBALS['vgpc_test_dbdelta_sql'] = [];
    WP_CLI::$commands = [];
    WP_CLI::$messages = [];
    $_POST = [];
    $_GET = [];
    $GLOBALS['vgpc_test_transients'] = [];
    $GLOBALS['vgpc_test_current_user_id'] = 1;
}

if (! defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

function get_current_user_id(): int { return (int) ($GLOBALS['vgpc_test_current_user_id'] ?? 1); }
function current_user_can(string $capability, int $object_id = 0): bool { return true; }
function set_transient(string $key, mixed $value, int $expiration = 0): bool { $GLOBALS['vgpc_test_transients'][$key] = $value; return true; }
function get_transient(string $key): mixed { return $GLOBALS['vgpc_test_transients'][$key] ?? false; }
function delete_transient(string $key): bool { unset($GLOBALS['vgpc_test_transients'][$key]); return true; }

function dbDelta(string $sql): array
{
    $GLOBALS['vgpc_test_dbdelta_sql'][] = $sql;
    return ['wp_vg_person_relationship_index' => 'Created'];
}

function vgpc_test_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . PHP_EOL
            . 'Expected: ' . var_export($expected, true) . PHP_EOL
            . 'Actual: ' . var_export($actual, true)
        );
    }
}

function vgpc_test_true(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

if (! function_exists('wp_die')) {
    function wp_die(string $message = '', string $title = '', array $args = []): never
    {
        throw new RuntimeException('wp_die: ' . $message);
    }
}

function vgpc_test_add_term(string $name, string $slug, int $parent = 0, string $taxonomy = 'content_topic'): object
{
    $term = (object) [
        'term_id' => (int) $GLOBALS['vgpc_test_next_term_id']++,
        'taxonomy' => $taxonomy,
        'name' => $name,
        'slug' => $slug,
        'parent' => $parent,
        'description' => '',
    ];
    $GLOBALS['vgpc_test_terms'][$term->term_id] = $term;

    return $term;
}

function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void
{
    $GLOBALS['vgpc_test_actions'][$hook][] = [
        'callback' => $callback,
        'priority' => $priority,
        'accepted_args' => $accepted_args,
    ];
}

function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void
{
    $GLOBALS['vgpc_test_filters'][$hook][] = [
        'callback' => $callback,
        'priority' => $priority,
        'accepted_args' => $accepted_args,
    ];
}

function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
{
    $callbacks = $GLOBALS['vgpc_test_filters'][$hook] ?? [];
    usort($callbacks, static fn (array $left, array $right): int => $left['priority'] <=> $right['priority']);

    foreach ($callbacks as $filter) {
        $accepted_args = max(1, (int) ($filter['accepted_args'] ?? 1));
        $filter_args = array_slice(array_merge([$value], $args), 0, $accepted_args);
        $value = ($filter['callback'])(...$filter_args);
    }

    return $value;
}

function do_action(string $hook, mixed ...$args): void
{
    $callbacks = $GLOBALS['vgpc_test_actions'][$hook] ?? [];
    usort($callbacks, static fn (array $left, array $right): int => $left['priority'] <=> $right['priority']);

    foreach ($callbacks as $action) {
        $accepted_args = (int) ($action['accepted_args'] ?? 1);
        ($action['callback'])(...array_slice($args, 0, $accepted_args));
    }
}

function taxonomy_exists(string $taxonomy): bool
{
    return isset($GLOBALS['vgpc_test_taxonomies'][$taxonomy]);
}

function get_taxonomy(string $taxonomy): object|false
{
    return $GLOBALS['vgpc_test_taxonomies'][$taxonomy] ?? false;
}

function register_taxonomy(string $taxonomy, array $object_type, array $args = []): object
{
    $registered = (object) array_merge($args, [
        'name' => $taxonomy,
        'object_type' => $object_type,
    ]);
    $GLOBALS['vgpc_test_taxonomies'][$taxonomy] = $registered;

    return $registered;
}

function get_terms(array $args = []): array|WP_Error
{
    $taxonomy = $args['taxonomy'] ?? '';
    $terms = array_values(array_filter(
        $GLOBALS['vgpc_test_terms'],
        static fn (object $term): bool => $term->taxonomy === $taxonomy
    ));
    usort($terms, static fn (object $a, object $b): int => $a->term_id <=> $b->term_id);

    return $terms;
}

function get_term_by(string $field, string|int $value, string $taxonomy): object|false
{
    foreach ($GLOBALS['vgpc_test_terms'] as $term) {
        if ($term->taxonomy === $taxonomy && isset($term->{$field}) && (string) $term->{$field} === (string) $value) {
            return $term;
        }
    }

    return false;
}

function get_term(int $term_id, string $taxonomy = ''): object|false
{
    $term = $GLOBALS['vgpc_test_terms'][$term_id] ?? false;
    if (! $term || ($taxonomy !== '' && $term->taxonomy !== $taxonomy)) {
        return false;
    }

    return $term;
}

function get_post(int $post_id): object|false
{
    if ((int) ($GLOBALS['vgpc_test_fail_get_post_id'] ?? 0) === $post_id) {
        $GLOBALS['wpdb']->last_error = 'Injected candidate post read failure.';
        return false;
    }

    if (
        ! empty($GLOBALS['vgpc_test_enable_query_cache'])
        && array_key_exists($post_id, $GLOBALS['vgpc_test_get_post_cache'])
    ) {
        return $GLOBALS['vgpc_test_get_post_cache'][$post_id];
    }

    $post = $GLOBALS['vgpc_test_posts'][$post_id] ?? false;
    if (! empty($GLOBALS['vgpc_test_enable_query_cache'])) {
        $GLOBALS['vgpc_test_get_post_cache'][$post_id] = $post;
    }

    return $post;
}

function clean_post_cache(int $post_id): void
{
    unset($GLOBALS['vgpc_test_get_post_cache'][$post_id]);
}

function wp_cache_delete(int|string $key, string $group = ''): bool
{
    $GLOBALS['vgpc_test_cache_deletes'][] = [$key, $group];
    return true;
}

function acf_flush_value_cache(int|string $post_id, string $field_name): void
{
    $GLOBALS['vgpc_test_database_sequence'][] = 'acf_flush:' . $field_name;
    if ($field_name === '') {
        return;
    }
    unset($GLOBALS['vgpc_test_get_field_cache'][$field_name . ':' . (string) $post_id]);
}

function wp_insert_post(array $postarr, bool $wp_error = false): int|WP_Error
{
    $GLOBALS['vgpc_test_insert_post_calls'][] = $postarr;
    $post_id = (int) $GLOBALS['vgpc_test_next_post_id']++;
    while (isset($GLOBALS['vgpc_test_posts'][$post_id])) {
        $post_id = (int) $GLOBALS['vgpc_test_next_post_id']++;
    }

    $GLOBALS['vgpc_test_posts'][$post_id] = (object) array_merge($postarr, [
        'ID' => $post_id,
        'post_type' => (string) ($postarr['post_type'] ?? 'post'),
        'post_status' => (string) ($postarr['post_status'] ?? 'draft'),
        'post_title' => (string) ($postarr['post_title'] ?? ''),
    ]);

    return $post_id;
}

function wp_update_post(array $postarr, bool $wp_error = false, bool $fire_after_hooks = true): int|WP_Error
{
    $post_id = (int) ($postarr['ID'] ?? 0);
    $post = get_post($post_id);
    if (! $post) {
        return $wp_error ? new WP_Error('invalid_post', 'The requested post does not exist.') : 0;
    }

    $before = clone $post;
    $update_data = array_merge(get_object_vars($post), $postarr);
    if (! empty($GLOBALS['vgpc_test_apply_wp_update_filter'])) {
        $update_data = apply_filters('wp_insert_post_data', $update_data, $postarr);
    }
    foreach ($update_data as $key => $value) {
        if ($key !== 'ID') {
            $post->{$key} = $value;
        }
    }
    $GLOBALS['vgpc_test_update_post_calls'][] = $postarr;
    if (! empty($GLOBALS['vgpc_test_wp_update_post_hooks'])) {
        if ((string) ($before->post_status ?? '') !== (string) ($post->post_status ?? '')) {
            do_action('transition_post_status', (string) $post->post_status, (string) $before->post_status, $post);
        }
        $GLOBALS['vgpc_test_wp_hook_order'][] = 'save_post';
        $saved_save_post_actions = $GLOBALS['vgpc_test_actions']['save_post'] ?? [];
        if (! empty($GLOBALS['vgpc_test_acf_save_during_core_save'])) {
            add_action('save_post', static function (int $acf_post_id): void {
                $expected_id = (int) ($_POST['post_ID'] ?? $acf_post_id);
                if ($expected_id !== $acf_post_id
                    || ! empty($GLOBALS['vgpc_test_acf_nonce_invalid'])
                    || ! empty($GLOBALS['vgpc_test_acf_validation_failed'])
                    || empty($_POST['acf']) || ! is_array($_POST['acf'])) return;
                $previous_acf_actions = $GLOBALS['vgpc_test_actions']['acf/save_post'] ?? [];
                add_action('acf/save_post', static function (int $actual_post_id): void {
                    foreach (($_POST['acf'] ?? []) as $selector => $value) {
                        update_field((string) $selector, $value, $actual_post_id);
                    }
                }, 10, 1);
                $GLOBALS['vgpc_test_wp_hook_order'][] = 'acf/save_post';
                try {
                    do_action('acf/save_post', $acf_post_id);
                } finally {
                    $GLOBALS['vgpc_test_actions']['acf/save_post'] = $previous_acf_actions;
                }
            }, 10, 1);
        }
        do_action('save_post', $post_id, $post, true);
        $GLOBALS['vgpc_test_actions']['save_post'] = $saved_save_post_actions;
        if ($fire_after_hooks && empty($GLOBALS['vgpc_test_suppress_after_insert_post'])) {
            $GLOBALS['vgpc_test_wp_hook_order'][] = 'wp_after_insert_post';
            do_action('wp_after_insert_post', $post_id, $post, true, $before);
        }
    }
    if (! empty($GLOBALS['vgpc_test_acf_save_during_update'])) {
        $release_count_before = count(array_filter(
            $GLOBALS['wpdb']->queries,
            static fn (array $query): bool => $query['query'] === 'SELECT RELEASE_LOCK(%s)'
        ));
        do_action('acf/save_post', $post_id);
        $release_count_after = count(array_filter(
            $GLOBALS['wpdb']->queries,
            static fn (array $query): bool => $query['query'] === 'SELECT RELEASE_LOCK(%s)'
        ));
        $GLOBALS['vgpc_test_acf_save_release_counts'][] = $release_count_after - $release_count_before;
    }
    do_action('post_updated', $post_id, $post, $before);

    return $post_id;
}

function wp_delete_post(int $post_id, bool $force_delete = false): object|false
{
    $GLOBALS['vgpc_test_delete_post_calls'][] = [
        'post_id' => $post_id,
        'force_delete' => $force_delete,
    ];
    $post = get_post($post_id);
    if (! $post) {
        return false;
    }

    if (! $force_delete) {
        $post->post_status = 'trash';
        return $post;
    }

    do_action('before_delete_post', $post_id, $post);
    unset($GLOBALS['vgpc_test_posts'][$post_id], $GLOBALS['vgpc_test_meta'][$post_id]);
    foreach ($GLOBALS['vgpc_test_relationships'] as $taxonomy => $relationships) {
        unset($GLOBALS['vgpc_test_relationships'][$taxonomy][$post_id]);
    }
    do_action('deleted_post', $post_id, $post);

    return $post;
}

function get_post_type(int|object|null $post = null): string|false
{
    $post = is_object($post) ? $post : get_post((int) $post);

    return $post->post_type ?? false;
}

function get_post_status(int|object|null $post = null): string|false
{
    $post = is_object($post) ? $post : get_post((int) $post);

    return $post->post_status ?? false;
}

function get_the_title(int|object $post = 0): string
{
    $post = is_object($post) ? $post : get_post((int) $post);

    return (string) ($post->post_title ?? '');
}

function get_objects_in_term(array|int $term_ids, string $taxonomy, array $args = []): array|WP_Error
{
    $term_ids = array_map('intval', (array) $term_ids);
    $objects = [];
    foreach ($GLOBALS['vgpc_test_relationships'][$taxonomy] ?? [] as $object_id => $assigned_ids) {
        if (array_intersect($term_ids, $assigned_ids)) {
            $objects[] = (int) $object_id;
        }
    }

    return array_values(array_unique($objects));
}

function wp_get_object_terms(int $object_id, string $taxonomy, array $args = []): array|WP_Error
{
    $terms = [];
    foreach ($GLOBALS['vgpc_test_relationships'][$taxonomy][$object_id] ?? [] as $term_id) {
        $term = get_term((int) $term_id, $taxonomy);
        if ($term) {
            $terms[] = $term;
        }
    }

    if (($args['fields'] ?? '') === 'slugs') {
        return array_map(static fn (object $term): string => $term->slug, $terms);
    }

    if (($args['fields'] ?? '') === 'ids') {
        return array_map(static fn (object $term): int => (int) $term->term_id, $terms);
    }

    if (($args['orderby'] ?? '') === 'term_id') {
        usort($terms, static fn (object $left, object $right): int => $left->term_id <=> $right->term_id);
    }

    return $terms;
}

function wp_set_object_terms(int $object_id, array|string|int $terms, string $taxonomy, bool $append = false): array|WP_Error
{
    ++$GLOBALS['vgpc_test_set_term_calls'];
    if ($GLOBALS['vgpc_test_fail_set_term_call'] === $GLOBALS['vgpc_test_set_term_calls']) {
        return new WP_Error('test_assignment_failed', 'Injected relationship failure.');
    }

    $term_ids = [];
    foreach ((array) $terms as $term) {
        $resolved = is_numeric($term)
            ? get_term((int) $term, $taxonomy)
            : get_term_by('slug', (string) $term, $taxonomy);
        if (! $resolved) {
            return new WP_Error('invalid_term', 'The requested term does not exist.');
        }
        $term_ids[] = (int) $resolved->term_id;
    }

    $existing = $GLOBALS['vgpc_test_relationships'][$taxonomy][$object_id] ?? [];
    $GLOBALS['vgpc_test_relationships'][$taxonomy][$object_id] = $append
        ? array_values(array_unique(array_merge($existing, $term_ids)))
        : $term_ids;

    do_action('set_object_terms', $object_id, (array) $terms, $term_ids, $taxonomy, $append, $existing);

    return $GLOBALS['vgpc_test_relationships'][$taxonomy][$object_id];
}

function wp_remove_object_terms(int $object_id, array|string|int $terms, string $taxonomy): bool|WP_Error
{
    $term_ids = [];
    foreach ((array) $terms as $term) {
        $resolved = is_numeric($term)
            ? get_term((int) $term, $taxonomy)
            : get_term_by('slug', (string) $term, $taxonomy);
        if ($resolved) {
            $term_ids[] = (int) $resolved->term_id;
        }
    }

    $existing = $GLOBALS['vgpc_test_relationships'][$taxonomy][$object_id] ?? [];
    $GLOBALS['vgpc_test_relationships'][$taxonomy][$object_id] = array_values(array_diff($existing, $term_ids));

    do_action('deleted_term_relationships', $object_id, array_values(array_intersect($existing, $term_ids)), $taxonomy);

    return true;
}

function sanitize_term_field(string $field, string $value, int $term_id, string $taxonomy, string $context): string
{
    if ($field === 'name' && $context === 'db') {
        return preg_replace('/&(?!#?[a-z0-9]+;)/i', '&amp;', $value) ?? $value;
    }

    return $value;
}

function wp_insert_term(string $name, string $taxonomy, array $args = []): array|WP_Error
{
    if (get_term_by('slug', (string) ($args['slug'] ?? ''), $taxonomy)) {
        return new WP_Error('term_exists', 'A term with this slug already exists.');
    }

    $slug = (string) ($args['slug'] ?? '');
    if ($GLOBALS['vgpc_test_force_suffix_next_insert']) {
        $slug .= '-2';
        $GLOBALS['vgpc_test_force_suffix_next_insert'] = false;
    }

    $term = vgpc_test_add_term(
        sanitize_term_field('name', $name, 0, $taxonomy, 'db'),
        $slug,
        (int) ($args['parent'] ?? 0),
        $taxonomy
    );
    $term->description = (string) ($args['description'] ?? '');
    ++$GLOBALS['vgpc_test_insert_count'];
    do_action('created_term', $term->term_id, $term->term_id + 10000, $taxonomy, $args);

    return ['term_id' => $term->term_id, 'term_taxonomy_id' => $term->term_id + 10000];
}

function wp_delete_term(int $term_id, string $taxonomy): bool
{
    if ($GLOBALS['vgpc_test_fail_next_term_delete']) {
        $GLOBALS['vgpc_test_fail_next_term_delete'] = false;
        return false;
    }

    $term = get_term($term_id, $taxonomy);
    if (! $term) {
        return false;
    }

    $object_ids = [];
    foreach ($GLOBALS['vgpc_test_relationships'][$taxonomy] ?? [] as $object_id => $term_ids) {
        if (in_array($term_id, $term_ids, true)) {
            wp_remove_object_terms((int) $object_id, $term_id, $taxonomy);
            $object_ids[] = (int) $object_id;
        }
    }

    unset($GLOBALS['vgpc_test_terms'][$term_id]);
    do_action('delete_term', $term_id, $term_id + 10000, $taxonomy, $term, $object_ids);

    return true;
}

function metadata_exists(string $meta_type, int $object_id, string $meta_key): bool
{
    return array_key_exists($meta_key, $GLOBALS['vgpc_test_meta'][$object_id] ?? []);
}

function get_post_meta(int $post_id, string $meta_key = '', bool $single = false): mixed
{
    $meta = $GLOBALS['vgpc_test_meta'][$post_id] ?? [];
    if ($meta_key === '') {
        return $meta;
    }

    $value = $meta[$meta_key] ?? '';
    return $single ? $value : ($value === '' ? [] : [$value]);
}

function update_post_meta(int $post_id, string $meta_key, mixed $value): bool
{
    if (! get_post($post_id)) {
        return false;
    }
    if (isset($GLOBALS['vgpc_update_post_meta_handler'])) {
        $result = ($GLOBALS['vgpc_update_post_meta_handler'])($post_id, $meta_key, $value);
        if ($result === false) return false;
    }
    if (($GLOBALS['vgpc_test_meta'][$post_id][$meta_key] ?? null) === $value) return false;
    $GLOBALS['vgpc_test_meta'][$post_id][$meta_key] = $value;
    return true;
}

function delete_post_meta(int $post_id, string $meta_key, mixed $value = ''): bool
{
    if (isset($GLOBALS['vgpc_delete_post_meta_handler']) && ($GLOBALS['vgpc_delete_post_meta_handler'])($post_id, $meta_key, $value) === false) return false;
    if (func_num_args() >= 3 && ($GLOBALS['vgpc_test_meta'][$post_id][$meta_key] ?? null) !== $value) {
        return false;
    }
    unset($GLOBALS['vgpc_test_meta'][$post_id][$meta_key]);
    return true;
}

function acf_add_local_field_group(array $field_group): void
{
    $fields = $field_group['fields'] ?? [];
    foreach ($fields as &$field) {
        $field['ID'] ??= (int) sprintf('%u', crc32((string) ($field['key'] ?? $field['name'] ?? '')));
    }
    unset($field);
    $field_group['fields'] = $fields;
    $GLOBALS['vgpc_test_field_groups'][$field_group['key']] = $field_group;
}

function acf_get_field(string|int $selector): array|false
{
    foreach ($GLOBALS['vgpc_test_field_groups'] as $field_group) {
        foreach ($field_group['fields'] ?? [] as $field) {
            if ((string) ($field['key'] ?? '') === (string) $selector
                || (string) ($field['name'] ?? '') === (string) $selector
                || (string) ($field['ID'] ?? '') === (string) $selector
            ) return $field;
        }
    }
    return false;
}

/** Faithful test double for ACF's acf_update_values() selector resolution loop. */
function acf_update_values(array $values, int|string $post_id): void
{
    foreach ($values as $selector => $value) {
        $field = acf_get_field($selector);
        if ($field) update_field((string) $field['name'], $value, $post_id);
    }
}

function vgpc_test_acf_field(string $selector): ?array
{
    foreach ($GLOBALS['vgpc_test_field_groups'] as $field_group) {
        foreach ($field_group['fields'] ?? [] as $field) {
            if (($field['key'] ?? null) === $selector || ($field['name'] ?? null) === $selector || (string) ($field['ID'] ?? '') === $selector) {
                return $field;
            }
        }
    }

    return null;
}

function vgpc_test_acf_field_name(string $selector): ?string
{
    $field = vgpc_test_acf_field($selector);

    return $field === null ? null : (string) $field['name'];
}

function get_field(string|int $selector, int|string|false $post_id = false, bool $format_value = true): mixed
{
    $cache_key = (string) $selector . ':' . (string) $post_id;
    if (
        ! empty($GLOBALS['vgpc_test_enable_query_cache'])
        && array_key_exists($cache_key, $GLOBALS['vgpc_test_get_field_cache'])
    ) {
        return $GLOBALS['vgpc_test_get_field_cache'][$cache_key];
    }

    $field_name = vgpc_test_acf_field_name((string) $selector);
    if ($field_name === null) {
        return null;
    }

    $value = $GLOBALS['vgpc_test_meta'][(int) $post_id][$field_name] ?? null;
    if (! empty($GLOBALS['vgpc_test_enable_query_cache'])) {
        $GLOBALS['vgpc_test_get_field_cache'][$cache_key] = $value;
    }

    return $value;
}

function update_field(string|int $selector, mixed $value, int|string|false $post_id = false): bool
{
    $field = vgpc_test_acf_field((string) $selector);
    if ($field === null) {
        return false;
    }

    $post_id = (int) $post_id;
    $field_name = (string) $field['name'];
    $GLOBALS['vgpc_test_update_field_calls'][] = [
        'selector' => (string) $selector,
        'field_name' => $field_name,
        'value' => $value,
        'post_id' => $post_id,
    ];
    if ($GLOBALS['vgpc_test_fail_update_field_name'] === $field_name) {
        return false;
    }

    $value = apply_filters('acf/update_value/key=' . $field['key'], $value, $post_id, $field);
    if ($value === null) {
        return true;
    }

    $GLOBALS['vgpc_test_meta'][$post_id][$field_name] = $value;
    $GLOBALS['vgpc_test_meta'][$post_id]['_' . $field_name] = (string) $selector;

    return true;
}

function delete_field(string|int $selector, int|string|false $post_id = false): bool
{
    $field_name = vgpc_test_acf_field_name((string) $selector);
    if ($field_name === null) {
        return false;
    }

    $post_id = (int) $post_id;
    unset(
        $GLOBALS['vgpc_test_meta'][$post_id][$field_name],
        $GLOBALS['vgpc_test_meta'][$post_id]['_' . $field_name]
    );

    return true;
}

function get_posts(array $args = []): array
{
    if (is_callable($GLOBALS['vgpc_test_get_posts_handler'] ?? null)) {
        $handled_posts = ($GLOBALS['vgpc_test_get_posts_handler'])($args);
        if (is_array($handled_posts)) {
            return $handled_posts;
        }
    }

    $cache_key = serialize($args);
    if (
        ! empty($GLOBALS['vgpc_test_enable_query_cache'])
        && array_key_exists($cache_key, $GLOBALS['vgpc_test_get_posts_cache'])
    ) {
        return $GLOBALS['vgpc_test_get_posts_cache'][$cache_key];
    }

    $post_types = (array) ($args['post_type'] ?? 'post');
    $post_types = $post_types === ['any'] ? array_column($GLOBALS['vgpc_test_posts'], 'post_type') : $post_types;
    $post_ids = [];
    foreach ($GLOBALS['vgpc_test_posts'] as $post_id => $post) {
        if (! in_array($post->post_type ?? '', $post_types, true)) {
            continue;
        }
        if (isset($args['post_status']) && $args['post_status'] !== 'any') {
            $post_statuses = (array) $args['post_status'];
            if (! in_array($post->post_status ?? 'publish', $post_statuses, true)) {
                continue;
            }
        }
        if (isset($args['meta_key'])) {
            $value = $GLOBALS['vgpc_test_meta'][$post_id][$args['meta_key']] ?? null;
            if ((string) $value !== (string) ($args['meta_value'] ?? '')) {
                continue;
            }
        }
        $post_ids[] = (int) $post_id;
    }

    $results = ($args['fields'] ?? '') === 'ids'
        ? $post_ids
        : array_map(static fn (int $post_id): object => get_post($post_id), $post_ids);

    if (! empty($GLOBALS['vgpc_test_enable_query_cache'])) {
        $GLOBALS['vgpc_test_get_posts_cache'][$cache_key] = $results;
    }

    return $results;
}

function get_current_screen(): ?object
{
    return $GLOBALS['vgpc_test_screen'];
}

function is_admin(): bool
{
    return true;
}

function esc_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function esc_attr(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function is_wp_error(mixed $thing): bool
{
    return $thing instanceof WP_Error;
}

function get_option(string $option, mixed $default = false): mixed
{
    return array_key_exists($option, $GLOBALS['vgpc_test_options'])
        ? $GLOBALS['vgpc_test_options'][$option]
        : $default;
}

function update_option(string $option, mixed $value, mixed $autoload = null): bool
{
    if (is_callable($GLOBALS['vgpc_update_option_handler'] ?? null) && ! ($GLOBALS['vgpc_update_option_handler'])($option, $value, $autoload)) {
        return false;
    }
    $GLOBALS['vgpc_test_options'][$option] = $value;

    return true;
}

function delete_option(string $option): bool
{
    unset($GLOBALS['vgpc_test_options'][$option]);

    return true;
}

function wp_json_encode(mixed $value, int $flags = 0): string|false
{
    return json_encode($value, $flags);
}

function flush_rewrite_rules(bool $hard = true): void
{
    ++$GLOBALS['vgpc_test_rewrite_flush_count'];
}

require_once __DIR__ . '/../includes/Taxonomy/ContentTopicTaxonomy.php';
