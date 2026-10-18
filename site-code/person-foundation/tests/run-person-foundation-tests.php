<?php
declare(strict_types=1);

define('VG_PERSON_FOUNDATION_TESTING', true);

$GLOBALS['vg_person_foundation_test_taxonomies'] = ['person_type' => true];
$GLOBALS['vg_person_foundation_test_terms'] = [];
$GLOBALS['vg_person_foundation_test_next_term_id'] = 900;
$GLOBALS['vg_person_foundation_test_insert_calls'] = [];
$GLOBALS['vg_person_foundation_test_set_object_terms_calls'] = [];

final class PersonFoundationTestError
{
    public function __construct(public string $message)
    {
    }

    public function get_error_message(): string
    {
        return $this->message;
    }
}

function taxonomy_exists(string $taxonomy): bool
{
    return ! empty($GLOBALS['vg_person_foundation_test_taxonomies'][$taxonomy]);
}

function get_terms(array $args): array|PersonFoundationTestError
{
    $taxonomy = (string) ($args['taxonomy'] ?? '');
    if (! taxonomy_exists($taxonomy)) {
        return new PersonFoundationTestError('Taxonomy is not registered.');
    }

    return array_values(array_filter(
        $GLOBALS['vg_person_foundation_test_terms'],
        static fn (object $term): bool => $term->taxonomy === $taxonomy
    ));
}

function get_term_by(string $field, string $value, string $taxonomy): object|false
{
    foreach ($GLOBALS['vg_person_foundation_test_terms'] as $term) {
        if ($term->taxonomy === $taxonomy && ($term->{$field} ?? null) === $value) {
            return $term;
        }
    }

    return false;
}

function wp_insert_term(string $name, string $taxonomy, array $args = []): array|PersonFoundationTestError
{
    $slug = (string) ($args['slug'] ?? '');
    $term = (object) [
        'term_id' => (int) $GLOBALS['vg_person_foundation_test_next_term_id']++,
        'taxonomy' => $taxonomy,
        'name' => $name,
        'slug' => $slug,
    ];
    $GLOBALS['vg_person_foundation_test_terms'][] = $term;
    $GLOBALS['vg_person_foundation_test_insert_calls'][] = [$name, $taxonomy, $slug];

    return ['term_id' => $term->term_id, 'term_taxonomy_id' => $term->term_id];
}

function is_wp_error(mixed $value): bool
{
    return $value instanceof PersonFoundationTestError;
}

function wp_set_object_terms(int $object_id, mixed $terms, string $taxonomy, bool $append = false): array
{
    $GLOBALS['vg_person_foundation_test_set_object_terms_calls'][] = [$object_id, $terms, $taxonomy, $append];

    return [];
}

function person_foundation_test_assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . PHP_EOL
            . 'Expected: ' . var_export($expected, true) . PHP_EOL
            . 'Actual: ' . var_export($actual, true)
        );
    }
}

function person_foundation_test_assert_true(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function person_foundation_test_assert_throws(callable $callback, string $message): Throwable
{
    try {
        $callback();
    } catch (Throwable $error) {
        return $error;
    }

    throw new RuntimeException($message);
}

function person_foundation_test_add_term(string $name, string $slug, string $taxonomy = 'person_type'): object
{
    $term = (object) [
        'term_id' => (int) $GLOBALS['vg_person_foundation_test_next_term_id']++,
        'taxonomy' => $taxonomy,
        'name' => $name,
        'slug' => $slug,
    ];
    $GLOBALS['vg_person_foundation_test_terms'][] = $term;

    return $term;
}

function person_foundation_test_reset(): void
{
    $GLOBALS['vg_person_foundation_test_taxonomies'] = ['person_type' => true];
    $GLOBALS['vg_person_foundation_test_terms'] = [];
    $GLOBALS['vg_person_foundation_test_next_term_id'] = 900;
    $GLOBALS['vg_person_foundation_test_insert_calls'] = [];
    $GLOBALS['vg_person_foundation_test_set_object_terms_calls'] = [];
}

function person_foundation_test_load_json(string $filename): array
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

$seeder_file = dirname(__DIR__) . '/seed-person-type-terms.php';
if (! is_file($seeder_file)) {
    throw new RuntimeException('The person_type term seeder does not exist yet.');
}
require_once $seeder_file;

$tests = [
    'Person CPT uses native profile fields and canonical routing' => static function (): void {
        $config = person_foundation_test_load_json('post_type_person.json');

        person_foundation_test_assert_same('post_type_person', $config['key'] ?? null, 'Person must use a stable ACF post type key.');
        person_foundation_test_assert_same('person', $config['post_type'] ?? null, 'Person must use the canonical post type key.');
        person_foundation_test_assert_same('Mense', $config['labels']['name'] ?? null, 'The plural Person label must use Afrikaans.');
        person_foundation_test_assert_same('Persoon', $config['labels']['singular_name'] ?? null, 'The singular Person label must use Afrikaans.');
        person_foundation_test_assert_same(true, $config['public'] ?? null, 'Person must be public.');
        person_foundation_test_assert_same(true, $config['publicly_queryable'] ?? null, 'Person must be publicly queryable.');
        person_foundation_test_assert_same(true, $config['show_ui'] ?? null, 'Person must expose its admin UI.');
        person_foundation_test_assert_same(true, $config['show_in_rest'] ?? null, 'Person must expose the REST API.');
        person_foundation_test_assert_same(true, $config['has_archive'] ?? null, 'Person must register an archive.');
        person_foundation_test_assert_same('mense', $config['has_archive_slug'] ?? null, 'The Person archive must use /mense/.');
        person_foundation_test_assert_same('mense', $config['rewrite']['slug'] ?? null, 'Person singles must use /mense/{slug}/.');
        person_foundation_test_assert_same(false, $config['rewrite']['with_front'] ?? null, 'Person routes must not inherit a front base.');
        person_foundation_test_assert_same(
            ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
            $config['supports'] ?? null,
            'Person must use only the approved native profile features.'
        );
    },
    'person_type is a flat editor taxonomy without public archive routes' => static function (): void {
        $config = person_foundation_test_load_json('taxonomy_person_type.json');

        person_foundation_test_assert_same('taxonomy_person_type', $config['key'] ?? null, 'person_type must use a stable ACF taxonomy key.');
        person_foundation_test_assert_same('person_type', $config['taxonomy'] ?? null, 'The taxonomy slug must be person_type.');
        person_foundation_test_assert_same('Persoonstipes', $config['labels']['name'] ?? null, 'The taxonomy plural label must use Afrikaans.');
        person_foundation_test_assert_same('Persoonstipe', $config['labels']['singular_name'] ?? null, 'The taxonomy singular label must use Afrikaans.');
        person_foundation_test_assert_same(['person'], $config['object_type'] ?? null, 'person_type must attach only to Person.');
        person_foundation_test_assert_same(false, $config['hierarchical'] ?? null, 'person_type must remain flat.');
        person_foundation_test_assert_same(true, $config['show_ui'] ?? null, 'Editors must manage approved person_type terms.');
        person_foundation_test_assert_same(true, $config['show_in_rest'] ?? null, 'person_type must be available through the REST API.');
        person_foundation_test_assert_same(false, $config['public'] ?? null, 'person_type must not be public.');
        person_foundation_test_assert_same(false, $config['publicly_queryable'] ?? null, 'person_type must not have public archive queries.');
        person_foundation_test_assert_same('no_permalink', $config['rewrite']['permalink_rewrite'] ?? null, 'person_type must not register rewrite rules.');
        person_foundation_test_assert_same('none', $config['query_var'] ?? null, 'person_type must not add a public query variable.');
    },
    'Person details fields are optional and taxonomy assignment is authoritative' => static function (): void {
        $group = person_foundation_test_load_json('group_vg_person_details.json');
        person_foundation_test_assert_same('group_vg_person_details', $group['key'] ?? null, 'The Person details group key must be stable.');
        person_foundation_test_assert_same([[
            ['param' => 'post_type', 'operator' => '==', 'value' => 'person'],
        ]], $group['location'] ?? null, 'The Person details group must target Person only.');

        $fields = [];
        foreach (($group['fields'] ?? []) as $field) {
            $fields[$field['name'] ?? ''] = $field;
        }
        person_foundation_test_assert_same(
            ['person_professional_title', 'person_credentials', 'person_company', 'person_website', 'person_type'],
            array_keys($fields),
            'The group must contain only approved custom Person facts and the person_type control.'
        );
        person_foundation_test_assert_same('text', $fields['person_professional_title']['type'] ?? null, 'Professional title must use a simple text field.');
        person_foundation_test_assert_true(
            in_array($fields['person_credentials']['type'] ?? null, ['text', 'textarea'], true),
            'Credentials must use a simple text or textarea field.'
        );
        person_foundation_test_assert_same('text', $fields['person_company']['type'] ?? null, 'Company must use a simple text field.');
        person_foundation_test_assert_same('url', $fields['person_website']['type'] ?? null, 'Website must use a URL field.');
        foreach (['person_professional_title', 'person_credentials', 'person_company', 'person_website', 'person_type'] as $name) {
            person_foundation_test_assert_same(0, (int) ($fields[$name]['required'] ?? 1), $name . ' must remain optional.');
            person_foundation_test_assert_true(
                str_starts_with((string) ($fields[$name]['label'] ?? ''), 'Persoon:'),
                $name . ' must use a context-prefixed admin label.'
            );
        }

        $person_type = $fields['person_type'];
        person_foundation_test_assert_same('taxonomy', $person_type['type'] ?? null, 'Person type must be an ACF taxonomy control.');
        person_foundation_test_assert_same('person_type', $person_type['taxonomy'] ?? null, 'Person type must save to the WordPress taxonomy.');
        person_foundation_test_assert_same('multi_select', $person_type['field_type'] ?? null, 'Person type must allow multiple future classifications.');
        person_foundation_test_assert_same(1, (int) ($person_type['multiple'] ?? 0), 'Person type must allow multiple selections.');
        person_foundation_test_assert_same(0, (int) ($person_type['add_term'] ?? 1), 'Editors must not create new terms from the field.');
        person_foundation_test_assert_same(1, (int) ($person_type['save_terms'] ?? 0), 'Person type must save WordPress term relationships.');
        person_foundation_test_assert_same(1, (int) ($person_type['load_terms'] ?? 0), 'Person type must load WordPress term relationships.');
        person_foundation_test_assert_same('id', $person_type['return_format'] ?? null, 'The taxonomy relationship ID is the control value.');
        person_foundation_test_assert_true(
            ! array_intersect(array_keys($fields), ['person_name', 'person_photo', 'person_short_bio', 'person_bio']),
            'The group must not duplicate title, featured image, excerpt, or content.'
        );
    },
    'seeds only the approved Mentor term without assigning any Person' => static function (): void {
        person_foundation_test_reset();

        $result = vg_seed_person_type_terms();

        person_foundation_test_assert_same(['mentor'], $result['created'], 'A clean run must create only mentor.');
        person_foundation_test_assert_same([], $result['existing'], 'A clean run must have no existing approved term.');
        person_foundation_test_assert_same(1, count($GLOBALS['vg_person_foundation_test_terms']), 'The initial vocabulary must contain exactly one term.');
        person_foundation_test_assert_same('Mentor', $GLOBALS['vg_person_foundation_test_terms'][0]->name, 'The approved name must be Mentor.');
        person_foundation_test_assert_same('mentor', $GLOBALS['vg_person_foundation_test_terms'][0]->slug, 'The approved slug must be mentor.');
        person_foundation_test_assert_same(0, count($GLOBALS['vg_person_foundation_test_set_object_terms_calls']), 'The vocabulary seeder must never create Person assignments.');
    },
    'rerunning the seeder leaves Mentor unchanged' => static function (): void {
        person_foundation_test_reset();
        vg_seed_person_type_terms();
        $term_before = get_object_vars($GLOBALS['vg_person_foundation_test_terms'][0]);

        $result = vg_seed_person_type_terms();

        person_foundation_test_assert_same([], $result['created'], 'A rerun must create no duplicate terms.');
        person_foundation_test_assert_same(['mentor'], $result['existing'], 'A rerun must recognize Mentor.');
        person_foundation_test_assert_same($term_before, get_object_vars($GLOBALS['vg_person_foundation_test_terms'][0]), 'A rerun must leave the existing term unchanged.');
    },
    'an exact existing Mentor term is unchanged' => static function (): void {
        person_foundation_test_reset();
        $term = person_foundation_test_add_term('Mentor', 'mentor');

        $result = vg_seed_person_type_terms();

        person_foundation_test_assert_same([], $result['created'], 'An exact existing term must not be recreated.');
        person_foundation_test_assert_same(['mentor'], $result['existing'], 'An exact existing term must be reported as existing.');
        person_foundation_test_assert_same($term, $GLOBALS['vg_person_foundation_test_terms'][0], 'An exact existing term must retain its identity and values.');
    },
    'a same-slug conflict stops before changing terms' => static function (): void {
        person_foundation_test_reset();
        $existing = person_foundation_test_add_term('Mentors', 'mentor');

        $error = person_foundation_test_assert_throws(static fn () => vg_seed_person_type_terms(), 'A same-slug conflict must fail.');

        person_foundation_test_assert_true(str_contains($error->getMessage(), 'conflict'), 'The conflict must be explained.');
        person_foundation_test_assert_same([$existing], $GLOBALS['vg_person_foundation_test_terms'], 'A slug conflict must not rewrite terms.');
        person_foundation_test_assert_same([], $GLOBALS['vg_person_foundation_test_insert_calls'], 'A slug conflict must stop before inserts.');
    },
    'a same-name conflict stops before changing terms' => static function (): void {
        person_foundation_test_reset();
        $existing = person_foundation_test_add_term('Mentor', 'mentor-legacy');

        $error = person_foundation_test_assert_throws(static fn () => vg_seed_person_type_terms(), 'A same-name conflict must fail.');

        person_foundation_test_assert_true(str_contains($error->getMessage(), 'conflict'), 'The conflict must be explained.');
        person_foundation_test_assert_same([$existing], $GLOBALS['vg_person_foundation_test_terms'], 'A name conflict must not rewrite terms.');
        person_foundation_test_assert_same([], $GLOBALS['vg_person_foundation_test_insert_calls'], 'A name conflict must stop before inserts.');
    },
    'an unexpected term stops before changing the vocabulary' => static function (): void {
        person_foundation_test_reset();
        $existing = person_foundation_test_add_term('Kenner', 'kenner');

        $error = person_foundation_test_assert_throws(static fn () => vg_seed_person_type_terms(), 'An unapproved term must fail.');

        person_foundation_test_assert_true(str_contains($error->getMessage(), 'Unexpected'), 'The unapproved term must be identified.');
        person_foundation_test_assert_same([$existing], $GLOBALS['vg_person_foundation_test_terms'], 'An unexpected term must not be deleted or changed.');
        person_foundation_test_assert_same([], $GLOBALS['vg_person_foundation_test_insert_calls'], 'An unexpected term must stop before inserts.');
    },
    'a missing taxonomy stops safely without creating terms' => static function (): void {
        person_foundation_test_reset();
        $GLOBALS['vg_person_foundation_test_taxonomies']['person_type'] = false;

        person_foundation_test_assert_throws(static fn () => vg_seed_person_type_terms(), 'The seeder must require the ACF-owned taxonomy.');
        person_foundation_test_assert_same([], $GLOBALS['vg_person_foundation_test_insert_calls'], 'A missing taxonomy must not create any term.');
    },
];

$failed = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        fwrite(STDOUT, "PASS {$name}" . PHP_EOL);
    } catch (Throwable $error) {
        ++$failed;
        fwrite(STDERR, "FAIL {$name}: {$error->getMessage()}" . PHP_EOL);
    }
}

if ($failed > 0) {
    fwrite(STDERR, sprintf('%d of %d Person foundation tests failed.' . PHP_EOL, $failed, count($tests)));
    exit(1);
}

fwrite(STDOUT, sprintf('%d Person foundation tests passed.' . PHP_EOL, count($tests)));
