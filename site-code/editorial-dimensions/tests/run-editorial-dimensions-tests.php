<?php
declare(strict_types=1);

define('VG_EDITORIAL_DIMENSIONS_TESTING', true);

$GLOBALS['vg_editorial_dimensions_test_taxonomies'] = ['editorial_format' => true];
$GLOBALS['vg_editorial_dimensions_test_terms'] = [];
$GLOBALS['vg_editorial_dimensions_test_next_term_id'] = 100;
$GLOBALS['vg_editorial_dimensions_test_insert_calls'] = [];
$GLOBALS['vg_editorial_dimensions_test_set_object_terms_calls'] = 0;

final class EditorialDimensionsTestError
{
    public function __construct(public string $message)
    {
    }
}

function taxonomy_exists(string $taxonomy): bool
{
    return ! empty($GLOBALS['vg_editorial_dimensions_test_taxonomies'][$taxonomy]);
}

function get_terms(array $args): array|EditorialDimensionsTestError
{
    $taxonomy = (string) ($args['taxonomy'] ?? '');
    if (! taxonomy_exists($taxonomy)) {
        return new EditorialDimensionsTestError('Taxonomy is not registered.');
    }

    return array_values(array_filter(
        $GLOBALS['vg_editorial_dimensions_test_terms'],
        static fn (object $term): bool => $term->taxonomy === $taxonomy
    ));
}

function get_term_by(string $field, string $value, string $taxonomy): object|false
{
    foreach ($GLOBALS['vg_editorial_dimensions_test_terms'] as $term) {
        if ($term->taxonomy === $taxonomy && ($term->{$field} ?? null) === $value) {
            return $term;
        }
    }

    return false;
}

function wp_insert_term(string $name, string $taxonomy, array $args = []): array|EditorialDimensionsTestError
{
    $slug = (string) ($args['slug'] ?? '');
    $term = (object) [
        'term_id' => (int) $GLOBALS['vg_editorial_dimensions_test_next_term_id']++,
        'taxonomy' => $taxonomy,
        'name' => $name,
        'slug' => $slug,
    ];
    $GLOBALS['vg_editorial_dimensions_test_terms'][] = $term;
    $GLOBALS['vg_editorial_dimensions_test_insert_calls'][] = [$name, $taxonomy, $slug];

    return ['term_id' => $term->term_id, 'term_taxonomy_id' => $term->term_id];
}

function is_wp_error(mixed $value): bool
{
    return $value instanceof EditorialDimensionsTestError;
}

function wp_set_object_terms(int $object_id, mixed $terms, string $taxonomy, bool $append = false): array
{
    ++$GLOBALS['vg_editorial_dimensions_test_set_object_terms_calls'];

    return [];
}

function editorial_dimensions_test_assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . PHP_EOL
            . 'Expected: ' . var_export($expected, true) . PHP_EOL
            . 'Actual: ' . var_export($actual, true)
        );
    }
}

function editorial_dimensions_test_assert_true(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function editorial_dimensions_test_add_term(string $name, string $slug, string $taxonomy = 'editorial_format'): object
{
    $term = (object) [
        'term_id' => (int) $GLOBALS['vg_editorial_dimensions_test_next_term_id']++,
        'taxonomy' => $taxonomy,
        'name' => $name,
        'slug' => $slug,
    ];
    $GLOBALS['vg_editorial_dimensions_test_terms'][] = $term;

    return $term;
}

function editorial_dimensions_test_reset(): void
{
    $GLOBALS['vg_editorial_dimensions_test_taxonomies'] = [
        'editorial_format' => true,
        'editorial_collection' => true,
    ];
    $GLOBALS['vg_editorial_dimensions_test_terms'] = [];
    $GLOBALS['vg_editorial_dimensions_test_next_term_id'] = 100;
    $GLOBALS['vg_editorial_dimensions_test_insert_calls'] = [];
    $GLOBALS['vg_editorial_dimensions_test_set_object_terms_calls'] = 0;
}

function editorial_dimensions_test_load_json(string $filename): array
{
    $path = dirname(__DIR__, 2) . '/acf-json/' . $filename;
    if (! is_file($path)) {
        throw new RuntimeException('Missing source-controlled ACF Local JSON file: ' . $path);
    }

    $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($data)) {
        throw new RuntimeException('ACF Local JSON file must contain an object: ' . $path);
    }

    return $data;
}

$seeder_file = dirname(__DIR__) . '/seed-editorial-format-terms.php';
if (! is_file($seeder_file)) {
    throw new RuntimeException('The editorial format term seeder does not exist yet.');
}
require_once $seeder_file;

$tests = [
    'both taxonomies are post-only, flat, and non-public' => static function (): void {
        $expected = [
            'taxonomy_editorial_format.json' => 'editorial_format',
            'taxonomy_editorial_collection.json' => 'editorial_collection',
        ];

        foreach ($expected as $filename => $slug) {
            $config = editorial_dimensions_test_load_json($filename);
            editorial_dimensions_test_assert_same($slug, $config['taxonomy'] ?? null, $slug . ' must use its canonical taxonomy slug.');
            editorial_dimensions_test_assert_same(['post'], $config['object_type'] ?? null, $slug . ' must attach to standard posts only.');
            editorial_dimensions_test_assert_same(false, $config['hierarchical'] ?? null, $slug . ' must be flat.');
            editorial_dimensions_test_assert_same(false, $config['public'] ?? null, $slug . ' must not be public.');
            editorial_dimensions_test_assert_same(false, $config['publicly_queryable'] ?? null, $slug . ' must not expose public term pages.');
            editorial_dimensions_test_assert_same(false, $config['show_in_rest'] ?? null, $slug . ' must not expose a public REST taxonomy panel.');
            editorial_dimensions_test_assert_same(false, $config['show_in_nav_menus'] ?? null, $slug . ' must not appear in navigation menus.');
            editorial_dimensions_test_assert_same(false, $config['show_tagcloud'] ?? null, $slug . ' must not appear in tag clouds.');
            editorial_dimensions_test_assert_same(true, $config['show_ui'] ?? null, $slug . ' must retain admin term management.');
            editorial_dimensions_test_assert_same('disabled', $config['meta_box'] ?? null, $slug . ' must not add a duplicate native taxonomy meta box.');
            editorial_dimensions_test_assert_same('none', $config['query_var'] ?? null, $slug . ' must not register a public query variable.');
            editorial_dimensions_test_assert_same('no_permalink', $config['rewrite']['permalink_rewrite'] ?? null, $slug . ' must not register rewrite rules.');
            editorial_dimensions_test_assert_same(false, $config['default_term']['default_term_enabled'] ?? null, $slug . ' must not have a default term.');
        }
    },
    'one post-only ACF field group owns both migration-safe taxonomy controls' => static function (): void {
        $group = editorial_dimensions_test_load_json('group_vg_editorial_classification.json');
        editorial_dimensions_test_assert_same('group_vg_editorial_classification', $group['key'] ?? null, 'The classification field group key must be stable.');
        editorial_dimensions_test_assert_same('Editorial Classification', $group['title'] ?? null, 'The editor area must have one clear title.');
        editorial_dimensions_test_assert_same(true, $group['active'] ?? null, 'The field group must be active.');
        editorial_dimensions_test_assert_same([[
            ['param' => 'post_type', 'operator' => '==', 'value' => 'post'],
        ]], $group['location'] ?? null, 'The field group must target standard posts only.');

        $fields = [];
        foreach (($group['fields'] ?? []) as $field) {
            $fields[$field['name'] ?? ''] = $field;
        }
        editorial_dimensions_test_assert_same(['editorial_format', 'editorial_collection'], array_keys($fields), 'The group must contain only the two approved dimensions.');

        $format = $fields['editorial_format'];
        editorial_dimensions_test_assert_same('taxonomy', $format['type'] ?? null, 'Format must be an ACF taxonomy field.');
        editorial_dimensions_test_assert_same('editorial_format', $format['taxonomy'] ?? null, 'Format must write to editorial_format terms.');
        editorial_dimensions_test_assert_same('radio', $format['field_type'] ?? null, 'Format must be single-select.');
        editorial_dimensions_test_assert_same(0, (int) ($format['required'] ?? 0), 'Format must remain optional during migration.');
        editorial_dimensions_test_assert_same(0, (int) ($format['add_term'] ?? 1), 'Editors must not create format terms inline.');
        editorial_dimensions_test_assert_same(1, (int) ($format['save_terms'] ?? 0), 'Format selections must save taxonomy relationships.');
        editorial_dimensions_test_assert_same(1, (int) ($format['load_terms'] ?? 0), 'Format selections must load taxonomy relationships.');
        editorial_dimensions_test_assert_true(empty($format['default_value']), 'Format must not default to article or another term.');

        $collection = $fields['editorial_collection'];
        editorial_dimensions_test_assert_same('taxonomy', $collection['type'] ?? null, 'Collection must be an ACF taxonomy field.');
        editorial_dimensions_test_assert_same('editorial_collection', $collection['taxonomy'] ?? null, 'Collection must write to editorial_collection terms.');
        editorial_dimensions_test_assert_same('multi_select', $collection['field_type'] ?? null, 'Collection must allow multiple selections.');
        editorial_dimensions_test_assert_same(1, (int) ($collection['multiple'] ?? 0), 'Collection must allow more than one term.');
        editorial_dimensions_test_assert_same(0, (int) ($collection['required'] ?? 0), 'Collection must be optional.');
        editorial_dimensions_test_assert_same(0, (int) ($collection['add_term'] ?? 1), 'Editors must not create collection terms inline.');
        editorial_dimensions_test_assert_same(1, (int) ($collection['save_terms'] ?? 0), 'Collection selections must save taxonomy relationships.');
        editorial_dimensions_test_assert_same(1, (int) ($collection['load_terms'] ?? 0), 'Collection selections must load taxonomy relationships.');
    },
    'the shared source contains exactly the Editorial-owned ACF definitions' => static function (): void {
        $files = glob(dirname(__DIR__, 2) . '/acf-json/*.json') ?: [];
        $actual = [];

        foreach ($files as $file) {
            $filename = basename($file);
            $schema = json_decode((string) file_get_contents($file), true);
            $schema = is_array($schema) ? $schema : [];
            $schema_key = (string) ($schema['key'] ?? '');
            $taxonomy = (string) ($schema['taxonomy'] ?? '');
            $editorial_field_names = ['editorial_format', 'editorial_collection'];
            $has_editorial_field = false;
            $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];

            foreach ($fields as $field) {
                if (! is_array($field)) {
                    continue;
                }

                $field_name = (string) ($field['name'] ?? '');
                $field_taxonomy = (string) ($field['taxonomy'] ?? '');

                if (in_array($field_name, $editorial_field_names, true) || str_starts_with($field_taxonomy, 'editorial_')) {
                    $has_editorial_field = true;
                    break;
                }
            }

            $is_editorial_definition = str_starts_with($filename, 'group_vg_editorial_')
                || str_starts_with($filename, 'post_type_editorial_')
                || str_starts_with($filename, 'taxonomy_editorial_')
                || str_starts_with($schema_key, 'group_vg_editorial_')
                || str_starts_with($schema_key, 'post_type_editorial_')
                || str_starts_with($schema_key, 'taxonomy_editorial_')
                || str_starts_with($taxonomy, 'editorial_')
                || $has_editorial_field;

            if ($is_editorial_definition) {
                $actual[] = $filename;
            }
        }

        sort($actual);
        $expected = [
            'group_vg_editorial_classification.json',
            'taxonomy_editorial_collection.json',
            'taxonomy_editorial_format.json',
        ];
        sort($expected);

        editorial_dimensions_test_assert_same($expected, $actual, 'Editorial Dimensions must own exactly the approved Editorial ACF definitions.');
    },
    'seeds only the three approved format terms and assigns no posts' => static function (): void {
        editorial_dimensions_test_reset();

        $result = vg_seed_editorial_format_terms();

        editorial_dimensions_test_assert_same(['article', 'video', 'audio'], $result['created'], 'Created format slugs must match the approved set.');
        editorial_dimensions_test_assert_same([], $result['existing'], 'A clean seed must have no pre-existing terms.');
        editorial_dimensions_test_assert_same(['article', 'video', 'audio'], array_map(
            static fn (object $term): string => $term->slug,
            $GLOBALS['vg_editorial_dimensions_test_terms']
        ), 'The seeder must create the approved slugs only.');
        editorial_dimensions_test_assert_same(['Artikel', 'Video', 'Oudio'], array_map(
            static fn (object $term): string => $term->name,
            $GLOBALS['vg_editorial_dimensions_test_terms']
        ), 'Format labels must follow the configured Afrikaans editor vocabulary.');
        editorial_dimensions_test_assert_same(['editorial_format', 'editorial_format', 'editorial_format'], array_column($GLOBALS['vg_editorial_dimensions_test_insert_calls'], 1), 'The seeder must not create terms in another taxonomy.');
        editorial_dimensions_test_assert_same(0, $GLOBALS['vg_editorial_dimensions_test_set_object_terms_calls'], 'Term seeding must not assign any post relationships.');
    },
    'rerunning the seeder leaves the approved terms unchanged' => static function (): void {
        editorial_dimensions_test_reset();
        vg_seed_editorial_format_terms();

        $result = vg_seed_editorial_format_terms();

        editorial_dimensions_test_assert_same([], $result['created'], 'A repeat run must not create terms.');
        editorial_dimensions_test_assert_same(['article', 'video', 'audio'], $result['existing'], 'A repeat run must recognize each approved term.');
        editorial_dimensions_test_assert_same(3, count($GLOBALS['vg_editorial_dimensions_test_terms']), 'A repeat run must not duplicate terms.');
    },
    'rerunning after a partial seed creates only the missing approved terms' => static function (): void {
        editorial_dimensions_test_reset();
        editorial_dimensions_test_add_term('Artikel', 'article');

        $result = vg_seed_editorial_format_terms();

        editorial_dimensions_test_assert_same(['video', 'audio'], $result['created'], 'A partial seed must create only the remaining approved slugs.');
        editorial_dimensions_test_assert_same(['article'], $result['existing'], 'A partial seed must preserve the approved existing term.');
        editorial_dimensions_test_assert_same(3, count($GLOBALS['vg_editorial_dimensions_test_terms']), 'A partial rerun must finish with exactly three format terms.');
    },
    'the seeder does not create editorial collection terms' => static function (): void {
        editorial_dimensions_test_reset();

        vg_seed_editorial_format_terms();

        editorial_dimensions_test_assert_same([], get_terms([
            'taxonomy' => 'editorial_collection',
            'hide_empty' => false,
        ]), 'The format seeder must leave editorial_collection empty.');
    },
    'a same-slug term with a different name stops without rewriting it' => static function (): void {
        editorial_dimensions_test_reset();
        editorial_dimensions_test_add_term('Unexpected label', 'article');

        try {
            vg_seed_editorial_format_terms();
        } catch (RuntimeException $error) {
            editorial_dimensions_test_assert_true(str_contains($error->getMessage(), 'article'), 'The conflict error must identify the conflicting slug.');
            editorial_dimensions_test_assert_same(1, count($GLOBALS['vg_editorial_dimensions_test_terms']), 'A conflict must not alter existing terms.');

            return;
        }

        throw new RuntimeException('A same-slug conflict should have stopped seeding.');
    },
    'a same-name term with a different slug stops without rewriting it' => static function (): void {
        editorial_dimensions_test_reset();
        editorial_dimensions_test_add_term('Artikel', 'artikel');

        try {
            vg_seed_editorial_format_terms();
        } catch (RuntimeException $error) {
            editorial_dimensions_test_assert_true(str_contains($error->getMessage(), 'Artikel'), 'The conflict error must identify the conflicting name.');
            editorial_dimensions_test_assert_same(1, count($GLOBALS['vg_editorial_dimensions_test_terms']), 'A conflict must not alter existing terms.');

            return;
        }

        throw new RuntimeException('A same-name conflict should have stopped seeding.');
    },
    'an unapproved existing format term stops without deleting it' => static function (): void {
        editorial_dimensions_test_reset();
        editorial_dimensions_test_add_term('Podcast', 'podcast');

        try {
            vg_seed_editorial_format_terms();
        } catch (RuntimeException $error) {
            editorial_dimensions_test_assert_true(str_contains($error->getMessage(), 'podcast'), 'The conflict error must identify the extra slug.');
            editorial_dimensions_test_assert_same(1, count($GLOBALS['vg_editorial_dimensions_test_terms']), 'The seeder must not remove an unexpected term.');

            return;
        }

        throw new RuntimeException('An unapproved format term should have stopped seeding.');
    },
    'missing taxonomy registration stops seeding' => static function (): void {
        editorial_dimensions_test_reset();
        $GLOBALS['vg_editorial_dimensions_test_taxonomies'] = [];

        try {
            vg_seed_editorial_format_terms();
        } catch (RuntimeException $error) {
            editorial_dimensions_test_assert_true(str_contains($error->getMessage(), 'editorial_format'), 'The error must identify the missing taxonomy.');
            editorial_dimensions_test_assert_same([], $GLOBALS['vg_editorial_dimensions_test_terms'], 'A missing taxonomy must not create terms.');

            return;
        }

        throw new RuntimeException('A missing taxonomy should have stopped seeding.');
    },
];

$passed = 0;
foreach ($tests as $name => $test) {
    $test();
    ++$passed;
    echo '.';
}

echo PHP_EOL . $passed . ' editorial dimension seeder tests passed.' . PHP_EOL;
