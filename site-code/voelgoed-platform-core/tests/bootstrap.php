<?php
declare(strict_types=1);

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

$GLOBALS['vgpc_test_taxonomies'] = [];
$GLOBALS['vgpc_test_terms'] = [];
$GLOBALS['vgpc_test_options'] = [];
$GLOBALS['vgpc_test_actions'] = [];
$GLOBALS['vgpc_test_next_term_id'] = 1000;
$GLOBALS['vgpc_test_insert_count'] = 0;
$GLOBALS['vgpc_test_rewrite_flush_count'] = 0;
$GLOBALS['vgpc_test_force_suffix_next_insert'] = false;
$GLOBALS['vgpc_test_posts'] = [];
$GLOBALS['vgpc_test_relationships'] = [];
$GLOBALS['vgpc_test_set_term_calls'] = 0;
$GLOBALS['vgpc_test_fail_set_term_call'] = null;

function vgpc_test_reset(): void
{
    $GLOBALS['vgpc_test_taxonomies'] = [];
    $GLOBALS['vgpc_test_terms'] = [];
    $GLOBALS['vgpc_test_options'] = [];
    $GLOBALS['vgpc_test_actions'] = [];
    $GLOBALS['vgpc_test_next_term_id'] = 1000;
    $GLOBALS['vgpc_test_insert_count'] = 0;
    $GLOBALS['vgpc_test_rewrite_flush_count'] = 0;
    $GLOBALS['vgpc_test_force_suffix_next_insert'] = false;
    $GLOBALS['vgpc_test_posts'] = [];
    $GLOBALS['vgpc_test_relationships'] = [];
    $GLOBALS['vgpc_test_set_term_calls'] = 0;
    $GLOBALS['vgpc_test_fail_set_term_call'] = null;
    WP_CLI::$commands = [];
    WP_CLI::$messages = [];
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
    return $GLOBALS['vgpc_test_posts'][$post_id] ?? false;
}

function get_post_type(int|object|null $post = null): string|false
{
    $post = is_object($post) ? $post : get_post((int) $post);

    return $post->post_type ?? false;
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

    return ['term_id' => $term->term_id, 'term_taxonomy_id' => $term->term_id + 10000];
}

function wp_delete_term(int $term_id, string $taxonomy): bool
{
    $term = get_term($term_id, $taxonomy);
    if (! $term) {
        return false;
    }

    unset($GLOBALS['vgpc_test_terms'][$term_id]);

    return true;
}

function is_wp_error(mixed $thing): bool
{
    return $thing instanceof WP_Error;
}

function get_option(string $option, mixed $default = false): mixed
{
    return $GLOBALS['vgpc_test_options'][$option] ?? $default;
}

function update_option(string $option, mixed $value, mixed $autoload = null): bool
{
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
