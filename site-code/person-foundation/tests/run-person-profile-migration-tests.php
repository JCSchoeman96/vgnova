<?php
declare(strict_types=1);

define('VG_PERSON_PROFILE_MIGRATION_TESTING', true);

$GLOBALS['vg_person_migration_test_posts'] = [];
$GLOBALS['vg_person_migration_test_terms'] = [];
$GLOBALS['vg_person_migration_test_relationships'] = [];
$GLOBALS['vg_person_migration_test_postmeta'] = [];
$GLOBALS['vg_person_migration_test_insert_calls'] = [];
$GLOBALS['vg_person_migration_test_delete_calls'] = [];
$GLOBALS['vg_person_migration_test_term_calls'] = [];
$GLOBALS['vg_person_migration_test_next_post_id'] = 900;
$GLOBALS['vg_person_migration_test_fail_term_assignment'] = false;
$GLOBALS['vg_person_migration_test_add_unrelated_on_failure'] = false;
$GLOBALS['vg_person_migration_test_insert_return_id'] = null;

final class PersonProfileMigrationTestError
{
    public function __construct(public string $message)
    {
    }

    public function get_error_message(): string
    {
        return $this->message;
    }
}

function person_profile_migration_test_add_post(
    int $id,
    string $post_type,
    string $post_status,
    string $post_title,
    string $post_name,
    string $post_content = '',
    string $post_excerpt = ''
): object {
    $post = (object) [
        'ID' => $id,
        'post_type' => $post_type,
        'post_status' => $post_status,
        'post_title' => $post_title,
        'post_name' => $post_name,
        'post_content' => $post_content,
        'post_excerpt' => $post_excerpt,
        'post_date' => '2026-06-04 06:05:47',
        'post_date_gmt' => '2026-06-04 06:05:47',
        'post_modified' => '2026-06-04 06:48:03',
        'post_modified_gmt' => '2026-06-04 06:48:03',
        'post_author' => 1,
    ];
    $GLOBALS['vg_person_migration_test_posts'][$id] = $post;

    return $post;
}

function person_profile_migration_test_add_term(int $id, string $name, string $slug, string $taxonomy, int $count = 0): object
{
    $term = (object) [
        'term_id' => $id,
        'term_taxonomy_id' => $id + 1000,
        'name' => $name,
        'slug' => $slug,
        'taxonomy' => $taxonomy,
        'count' => $count,
    ];
    $GLOBALS['vg_person_migration_test_terms'][$id] = $term;

    return $term;
}

function person_profile_migration_test_reset(): void
{
    $GLOBALS['vg_person_migration_test_posts'] = [];
    $GLOBALS['vg_person_migration_test_terms'] = [];
    $GLOBALS['vg_person_migration_test_relationships'] = [];
    $GLOBALS['vg_person_migration_test_postmeta'] = [];
    $GLOBALS['vg_person_migration_test_insert_calls'] = [];
    $GLOBALS['vg_person_migration_test_delete_calls'] = [];
    $GLOBALS['vg_person_migration_test_term_calls'] = [];
    $GLOBALS['vg_person_migration_test_next_post_id'] = 900;
    $GLOBALS['vg_person_migration_test_fail_term_assignment'] = false;
    $GLOBALS['vg_person_migration_test_add_unrelated_on_failure'] = false;
    $GLOBALS['vg_person_migration_test_insert_return_id'] = null;

    person_profile_migration_test_add_term(410, 'Mentor', 'mentor', 'person_type');
    person_profile_migration_test_add_term(411, 'Kenner', 'kenner', 'person_type');
    person_profile_migration_test_add_term(298, 'Mentor', 'mentor', 'medewerker_rol');
    person_profile_migration_test_add_term(307, 'Voelgoed Mentor', 'voelgoed-mentor', 'medewerker_groep');
    person_profile_migration_test_add_term(510, 'Mensstories', 'mensstories', 'content_topic');

    foreach ([
        [753, 'Lynette Beer', 'lynette-beer', 'Lewensafrigter en spreker.'],
        [754, 'Annelize van der Merwe', 'annelize-van-der-merwe', 'Finansiële mentor.'],
        [755, 'Thandi Mokoena', 'thandi-mokoena', 'Huweliks- en gesinsterapeut.'],
        [756, 'Elna Pretorius', 'elna-pretorius', 'Wellness-afrigter.'],
        [757, 'Retha Coetzee', 'retha-coetzee', 'Skrywer en verhaalverteller.'],
    ] as [$id, $title, $slug, $bio]) {
        person_profile_migration_test_add_post(
            $id,
            'medewerker',
            'publish',
            $title,
            $slug,
            '<p>' . $bio . '</p>',
            $bio
        );
        $GLOBALS['vg_person_migration_test_postmeta'][$id] = [
            'medewerker_kort_bio' => $bio,
            'medewerker_profiel_status' => 'public',
            '_thumbnail_id' => (string) (558 + (($id - 753) * 2)),
        ];
        $GLOBALS['vg_person_migration_test_relationships'][$id] = [
            'medewerker_rol' => [298],
            'medewerker_groep' => [307],
        ];
    }

    person_profile_migration_test_add_post(763, 'tc_events', 'publish', 'Test Event', 'test-event-medewerkers-qa');
    person_profile_migration_test_add_post(765, 'product', 'trash', 'Test Event Recording', 'test-event-recording-qa__trashed');
    person_profile_migration_test_add_post(767, 'product', 'publish', 'Test Event Recording QA2', 'test-event-recording-qa2');
    person_profile_migration_test_add_post(768, 'product', 'trash', 'QA4', 'qa4__trashed');
    $GLOBALS['vg_person_migration_test_postmeta'][763] = ['event_medewerkers' => ['753', '757']];
    $GLOBALS['vg_person_migration_test_postmeta'][765] = ['produk_bron_event' => '763'];
    $GLOBALS['vg_person_migration_test_postmeta'][767] = ['produk_medewerkers' => ['753'], 'produk_bron_event' => '763'];
    $GLOBALS['vg_person_migration_test_postmeta'][768] = ['produk_medewerkers' => ['753', '757'], 'produk_bron_event' => '763'];
}

function taxonomy_exists(string $taxonomy): bool
{
    foreach ($GLOBALS['vg_person_migration_test_terms'] as $term) {
        if ($term->taxonomy === $taxonomy) {
            return true;
        }
    }

    return in_array($taxonomy, ['person_type', 'content_topic', 'content_category', 'medewerker_rol', 'medewerker_groep'], true);
}

function get_terms(array $args): array|PersonProfileMigrationTestError
{
    $taxonomy = (string) ($args['taxonomy'] ?? '');
    if (! taxonomy_exists($taxonomy)) {
        return new PersonProfileMigrationTestError('Taxonomy is not registered.');
    }

    return array_values(array_filter(
        $GLOBALS['vg_person_migration_test_terms'],
        static fn (object $term): bool => $term->taxonomy === $taxonomy
    ));
}

function get_term_by(string $field, string|int $value, string $taxonomy): object|false
{
    foreach ($GLOBALS['vg_person_migration_test_terms'] as $term) {
        if ($term->taxonomy === $taxonomy && ($term->{$field} ?? null) == $value) {
            return $term;
        }
    }

    return false;
}

function get_post(int $post_id): object|null
{
    return $GLOBALS['vg_person_migration_test_posts'][$post_id] ?? null;
}

function get_post_stati(array $args = [], string $output = 'names'): array
{
    return ['publish', 'future', 'draft', 'pending', 'private', 'trash', 'auto-draft', 'inherit'];
}

function get_posts(array $args = []): array
{
    $post_type = $args['post_type'] ?? 'post';
    $statuses = $args['post_status'] ?? 'publish';
    $posts = array_values(array_filter(
        $GLOBALS['vg_person_migration_test_posts'],
        static function (object $post) use ($post_type, $statuses): bool {
            $matches_type = $post_type === 'any' || $post->post_type === $post_type;
            $matches_status = $statuses === 'any' || in_array($post->post_status, (array) $statuses, true);

            return $matches_type && $matches_status;
        }
    ));
    usort($posts, static fn (object $a, object $b): int => $a->ID <=> $b->ID);

    if (($args['fields'] ?? '') === 'ids') {
        return array_map(static fn (object $post): int => $post->ID, $posts);
    }

    return $posts;
}

function wp_insert_post(array $postarr, bool $wp_error = false): int|PersonProfileMigrationTestError
{
    $GLOBALS['vg_person_migration_test_insert_calls'][] = $postarr;
    if ($GLOBALS['vg_person_migration_test_insert_return_id'] !== null) {
        return (int) $GLOBALS['vg_person_migration_test_insert_return_id'];
    }

    $id = (int) $GLOBALS['vg_person_migration_test_next_post_id']++;
    person_profile_migration_test_add_post(
        $id,
        (string) ($postarr['post_type'] ?? ''),
        (string) ($postarr['post_status'] ?? 'draft'),
        (string) ($postarr['post_title'] ?? ''),
        (string) ($postarr['post_name'] ?? ''),
        (string) ($postarr['post_content'] ?? ''),
        (string) ($postarr['post_excerpt'] ?? '')
    );
    $GLOBALS['vg_person_migration_test_postmeta'][$id] = ['_inserted_fixture_meta' => 'owned-by-this-insert'];

    return $id;
}

function wp_set_object_terms(int $object_id, mixed $terms, string $taxonomy, bool $append = false): array|PersonProfileMigrationTestError
{
    $term_ids = array_map('intval', (array) $terms);
    $GLOBALS['vg_person_migration_test_term_calls'][] = [$object_id, $term_ids, $taxonomy, $append];
    if (! $append) {
        $GLOBALS['vg_person_migration_test_relationships'][$object_id][$taxonomy] = $term_ids;
    } else {
        $GLOBALS['vg_person_migration_test_relationships'][$object_id][$taxonomy] = array_values(array_unique([
            ...($GLOBALS['vg_person_migration_test_relationships'][$object_id][$taxonomy] ?? []),
            ...$term_ids,
        ]));
    }

    if ($GLOBALS['vg_person_migration_test_fail_term_assignment']) {
        if ($GLOBALS['vg_person_migration_test_add_unrelated_on_failure']) {
            person_profile_migration_test_add_post(800, 'person', 'draft', 'Another Person', 'another-person');
            $GLOBALS['vg_person_migration_test_add_unrelated_on_failure'] = false;
        }

        return new PersonProfileMigrationTestError('Injected term assignment failure.');
    }

    return array_map(
        static fn (int $term_id): int => (int) $GLOBALS['vg_person_migration_test_terms'][$term_id]->term_taxonomy_id,
        $term_ids
    );
}

function wp_get_object_terms(int $object_ids, string $taxonomy, array $args = []): array|PersonProfileMigrationTestError
{
    $ids = $GLOBALS['vg_person_migration_test_relationships'][$object_ids][$taxonomy] ?? [];
    if (($args['fields'] ?? '') === 'ids') {
        return array_map('intval', $ids);
    }

    return array_values(array_filter(array_map(
        static fn (int $id): ?object => $GLOBALS['vg_person_migration_test_terms'][$id] ?? null,
        array_map('intval', $ids)
    )));
}

function get_object_taxonomies(string $object_type, string $output = 'names'): array
{
    return match ($object_type) {
        'person' => ['person_type', 'content_topic', 'content_category'],
        'medewerker' => ['medewerker_rol', 'medewerker_groep', 'content_topic', 'content_category'],
        default => [],
    };
}

function get_post_meta(int $post_id, string $key = '', bool $single = false): mixed
{
    $meta = $GLOBALS['vg_person_migration_test_postmeta'][$post_id] ?? [];
    if ($key !== '') {
        $value = $meta[$key] ?? '';
        return $single ? $value : ($value === '' ? [] : [$value]);
    }

    return array_map(static fn (mixed $value): array => [$value], $meta);
}

function metadata_exists(string $meta_type, int $object_id, string $meta_key): bool
{
    return array_key_exists($meta_key, $GLOBALS['vg_person_migration_test_postmeta'][$object_id] ?? []);
}

function get_post_thumbnail_id(int $post_id): int
{
    return (int) get_post_meta($post_id, '_thumbnail_id', true);
}

function get_field(string $field_name, int $post_id = 0, bool $format_value = true): mixed
{
    $value = get_post_meta($post_id, $field_name, true);

    return $value === '' ? false : $value;
}

function wp_delete_post(int $post_id, bool $force_delete = false): object|false
{
    $GLOBALS['vg_person_migration_test_delete_calls'][] = [$post_id, $force_delete];
    $post = get_post($post_id);
    if (! $post) {
        return false;
    }

    unset($GLOBALS['vg_person_migration_test_posts'][$post_id]);
    unset($GLOBALS['vg_person_migration_test_postmeta'][$post_id]);
    unset($GLOBALS['vg_person_migration_test_relationships'][$post_id]);

    return $post;
}

function is_wp_error(mixed $value): bool
{
    return $value instanceof PersonProfileMigrationTestError;
}

function person_profile_migration_test_assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . PHP_EOL
            . 'Expected: ' . var_export($expected, true) . PHP_EOL
            . 'Actual: ' . var_export($actual, true)
        );
    }
}

function person_profile_migration_test_assert_true(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function person_profile_migration_test_assert_throws(callable $callback, string $message): Throwable
{
    try {
        $callback();
    } catch (Throwable $error) {
        return $error;
    }

    throw new RuntimeException($message);
}

function person_profile_migration_test_authority_rows(array $overrides = []): array
{
    $rows = [
        [
            'legacy_id' => '753', 'decision' => 'MIGRATE', 'target_title' => 'Lynette Beer',
            'target_slug' => 'lynette-beer', 'target_status' => 'publish', 'person_types' => 'mentor|kenner',
            'copy_featured_image' => 'NO', 'copy_excerpt' => 'NO', 'copy_content' => 'NO',
            'copy_person_fields' => 'NO', 'relationship_policy' => 'EXCLUDE_ALL_CURRENT',
            'authority' => 'OWNER_CONFIRMED_2026-10-25',
        ],
        [
            'legacy_id' => '754', 'decision' => 'DO_NOT_MIGRATE', 'target_title' => '',
            'target_slug' => '', 'target_status' => '', 'person_types' => '',
            'copy_featured_image' => 'NO', 'copy_excerpt' => 'NO', 'copy_content' => 'NO',
            'copy_person_fields' => 'NO', 'relationship_policy' => 'EXCLUDE_ALL_CURRENT',
            'authority' => 'OWNER_CONFIRMED_2026-10-25',
        ],
        [
            'legacy_id' => '755', 'decision' => 'DO_NOT_MIGRATE', 'target_title' => '',
            'target_slug' => '', 'target_status' => '', 'person_types' => '',
            'copy_featured_image' => 'NO', 'copy_excerpt' => 'NO', 'copy_content' => 'NO',
            'copy_person_fields' => 'NO', 'relationship_policy' => 'EXCLUDE_ALL_CURRENT',
            'authority' => 'OWNER_CONFIRMED_2026-10-25',
        ],
        [
            'legacy_id' => '756', 'decision' => 'DO_NOT_MIGRATE', 'target_title' => '',
            'target_slug' => '', 'target_status' => '', 'person_types' => '',
            'copy_featured_image' => 'NO', 'copy_excerpt' => 'NO', 'copy_content' => 'NO',
            'copy_person_fields' => 'NO', 'relationship_policy' => 'EXCLUDE_ALL_CURRENT',
            'authority' => 'OWNER_CONFIRMED_2026-10-25',
        ],
        [
            'legacy_id' => '757', 'decision' => 'DO_NOT_MIGRATE', 'target_title' => '',
            'target_slug' => '', 'target_status' => '', 'person_types' => '',
            'copy_featured_image' => 'NO', 'copy_excerpt' => 'NO', 'copy_content' => 'NO',
            'copy_person_fields' => 'NO', 'relationship_policy' => 'EXCLUDE_ALL_CURRENT',
            'authority' => 'OWNER_CONFIRMED_2026-10-25',
        ],
    ];

    foreach ($rows as &$row) {
        if (isset($overrides[(int) $row['legacy_id']])) {
            $row = array_replace($row, $overrides[(int) $row['legacy_id']]);
        }
    }
    unset($row);

    return $rows;
}

$migration_file = dirname(__DIR__) . '/migrate-approved-person-profiles.php';
if (! is_file($migration_file)) {
    throw new RuntimeException('The approved Person profile migration does not exist yet.');
}
require_once $migration_file;

$tests = [
    'CLI writers load the source mutation guard before asserting the session' => static function (): void {
        $entrypoints = [
            dirname(__DIR__) . '/seed-person-type-terms.php',
            dirname(__DIR__) . '/migrate-approved-person-profiles.php',
        ];
        foreach ($entrypoints as $entrypoint) {
            $source = file_get_contents($entrypoint);
            person_profile_migration_test_assert_true(is_string($source), 'A guarded Person CLI entrypoint must be readable.');

            $bootstrap_position = strpos($source, "require_once dirname(__DIR__) . '/runtime-tools/runtime-mutation-session.php';");
            $guard_position = strpos($source, "vg_runtime_mutation_assert_session('vgnova-local-10039');");
            person_profile_migration_test_assert_true(
                $bootstrap_position !== false && $guard_position !== false && $bootstrap_position < $guard_position,
                basename($entrypoint) . ' must load the source mutation-session guard before checking the active session.'
            );
        }
    },
    'source authority CSV contains only the five locked owner decisions' => static function (): void {
        $path = dirname(__DIR__, 3) . '/docs/architecture/PERSON_PROFILE_MIGRATION_AUTHORITY_v1.0.0.csv';
        $rows = vg_person_migration_load_authority($path);
        person_profile_migration_test_assert_same(
            ['753' => 'MIGRATE', '754' => 'DO_NOT_MIGRATE', '755' => 'DO_NOT_MIGRATE', '756' => 'DO_NOT_MIGRATE', '757' => 'DO_NOT_MIGRATE'],
            array_combine(array_column($rows, 'legacy_id'), array_column($rows, 'decision')),
            'The source authority artifact must retain the owner-approved decisions.'
        );
    },
    'only MIGRATE rows are eligible and DO_NOT_MIGRATE rows are never created' => static function (): void {
        person_profile_migration_test_reset();
        $result = vg_migrate_approved_person_profiles(person_profile_migration_test_authority_rows());

        person_profile_migration_test_assert_same([753], $result['migrated_legacy_ids'], 'Only the authorized legacy row may migrate.');
        person_profile_migration_test_assert_same([754, 755, 756, 757], $result['skipped_legacy_ids'], 'Every owner-excluded row must be reported as skipped.');
        person_profile_migration_test_assert_same(1, count($GLOBALS['vg_person_migration_test_insert_calls']), 'Exactly one approved row must be inserted.');
        person_profile_migration_test_assert_same(1, count(array_filter(get_posts(['post_type' => 'person', 'post_status' => 'any']))), 'No excluded row may create a Person.');
    },
    'DEFER and DO_NOT_MIGRATE decisions never create a Person' => static function (): void {
        person_profile_migration_test_reset();
        $rows = person_profile_migration_test_authority_rows([
            753 => ['decision' => 'DEFER'],
            754 => ['decision' => 'DO_NOT_MIGRATE'],
            755 => ['decision' => 'DEFER'],
        ]);
        $result = vg_migrate_approved_person_profiles($rows);

        person_profile_migration_test_assert_same([], $result['migrated_legacy_ids'], 'No non-MIGRATE row may be migrated.');
        person_profile_migration_test_assert_same([], $GLOBALS['vg_person_migration_test_insert_calls'], 'No post insert may occur for excluded or deferred rows.');
        person_profile_migration_test_assert_same([], get_posts(['post_type' => 'person', 'post_status' => 'any']), 'No Person may be created for excluded or deferred rows.');
    },
    'an unapproved legacy ID cannot be migrated' => static function (): void {
        person_profile_migration_test_reset();
        $rows = person_profile_migration_test_authority_rows([
            753 => ['decision' => 'DO_NOT_MIGRATE'],
            754 => [
                'decision' => 'MIGRATE', 'target_title' => 'Annelize van der Merwe',
                'target_slug' => 'annelize-van-der-merwe', 'target_status' => 'publish',
                'person_types' => 'mentor|kenner', 'authority' => 'OWNER_CONFIRMED_2026-10-25',
            ],
        ]);

        person_profile_migration_test_assert_throws(
            static fn () => vg_migrate_approved_person_profiles($rows),
            'A migration row for any legacy ID other than 753 must fail closed.'
        );
        person_profile_migration_test_assert_same([], $GLOBALS['vg_person_migration_test_insert_calls'], 'The unsupported legacy ID must fail before insertion.');
    },
    'wrong source post type is rejected before insertion' => static function (): void {
        person_profile_migration_test_reset();
        $source = get_post(753);
        $source->post_type = 'person';

        person_profile_migration_test_assert_throws(
            static fn () => vg_migrate_approved_person_profiles(person_profile_migration_test_authority_rows()),
            'A source post with the wrong post type must be rejected.'
        );
        person_profile_migration_test_assert_same([], $GLOBALS['vg_person_migration_test_insert_calls'], 'The wrong source type must fail before insertion.');
    },
    'changed source title or slug is rejected before insertion' => static function (): void {
        foreach ([
            'post_title' => 'Lynette B.',
            'post_name' => 'lynette-beer-old',
            'post_status' => 'draft',
        ] as $field => $value) {
            person_profile_migration_test_reset();
            $source = get_post(753);
            $source->{$field} = $value;

            person_profile_migration_test_assert_throws(
                static fn () => vg_migrate_approved_person_profiles(person_profile_migration_test_authority_rows()),
                'Changed source identity facts must be rejected.'
            );
            person_profile_migration_test_assert_same([], $GLOBALS['vg_person_migration_test_insert_calls'], 'Changed source identity must fail before insertion.');
        }
    },
    'an existing conflicting canonical Person stops before insertion' => static function (): void {
        person_profile_migration_test_reset();
        person_profile_migration_test_add_post(800, 'person', 'draft', 'Lynette Beer', 'lynette-beer');

        person_profile_migration_test_assert_throws(
            static fn () => vg_migrate_approved_person_profiles(person_profile_migration_test_authority_rows()),
            'An existing canonical Person collision must fail closed.'
        );
        person_profile_migration_test_assert_same([], $GLOBALS['vg_person_migration_test_insert_calls'], 'A target collision must fail before insertion.');
    },
    'nonzero Person Type relationships fail before insertion for either approved term' => static function (): void {
        foreach ([410, 411] as $term_id) {
            person_profile_migration_test_reset();
            $GLOBALS['vg_person_migration_test_terms'][$term_id]->count = 1;

            person_profile_migration_test_assert_throws(
                static fn () => vg_migrate_approved_person_profiles(person_profile_migration_test_authority_rows()),
                'A pre-existing Person Type relationship must fail closed.'
            );
            person_profile_migration_test_assert_same([], $GLOBALS['vg_person_migration_test_insert_calls'], 'A nonzero Person Type count must fail before insertion.');
            person_profile_migration_test_assert_same([], get_posts(['post_type' => 'person', 'post_status' => 'any']), 'A nonzero Person Type count must not create a Person.');
        }
    },
    'invalid Person Type vocabulary fails before insertion' => static function (): void {
        $invalid_vocabularies = [
            'missing Kenner' => static function (): void {
                unset($GLOBALS['vg_person_migration_test_terms'][411]);
            },
            'wrong Mentor name' => static function (): void {
                $GLOBALS['vg_person_migration_test_terms'][410]->name = 'Guide';
            },
            'wrong Kenner name' => static function (): void {
                $GLOBALS['vg_person_migration_test_terms'][411]->name = 'Expert';
            },
            'wrong Mentor slug' => static function (): void {
                $GLOBALS['vg_person_migration_test_terms'][410]->slug = 'guide';
            },
            'wrong Kenner slug' => static function (): void {
                $GLOBALS['vg_person_migration_test_terms'][411]->slug = 'expert';
            },
            'unexpected third term' => static function (): void {
                person_profile_migration_test_add_term(412, 'Spreker', 'spreker', 'person_type');
            },
        ];

        foreach ($invalid_vocabularies as $case => $mutate_vocabulary) {
            person_profile_migration_test_reset();
            $mutate_vocabulary();

            person_profile_migration_test_assert_throws(
                static fn () => vg_migrate_approved_person_profiles(person_profile_migration_test_authority_rows()),
                sprintf('An invalid Person Type vocabulary (%s) must fail closed.', $case)
            );
            person_profile_migration_test_assert_same(
                [],
                $GLOBALS['vg_person_migration_test_insert_calls'],
                sprintf('An invalid Person Type vocabulary (%s) must fail before insertion.', $case)
            );
        }
    },
    'one approved row creates exactly one empty published Lynette Person with both types' => static function (): void {
        person_profile_migration_test_reset();
        $protected_before = [
            'posts' => $GLOBALS['vg_person_migration_test_posts'],
            'postmeta' => $GLOBALS['vg_person_migration_test_postmeta'],
            'relationships' => $GLOBALS['vg_person_migration_test_relationships'],
        ];

        $result = vg_migrate_approved_person_profiles(person_profile_migration_test_authority_rows());

        person_profile_migration_test_assert_same(1, count($result['created_person_ids']), 'Exactly one Person ID must be returned.');
        person_profile_migration_test_assert_same(1, count($GLOBALS['vg_person_migration_test_insert_calls']), 'Exactly one Person post must be inserted.');
        $person_id = $result['created_person_ids'][0];
        $person = get_post($person_id);
        person_profile_migration_test_assert_true(is_object($person), 'The returned Person ID must exist.');
        person_profile_migration_test_assert_same('person', $person->post_type, 'The target must use the canonical Person post type.');
        person_profile_migration_test_assert_same('publish', $person->post_status, 'The target must be published.');
        person_profile_migration_test_assert_same('Lynette Beer', $person->post_title, 'The target title must be exact.');
        person_profile_migration_test_assert_same('lynette-beer', $person->post_name, 'The target slug must be exact.');
        person_profile_migration_test_assert_same('', $person->post_excerpt, 'The target excerpt must remain empty.');
        person_profile_migration_test_assert_same('', $person->post_content, 'The target content must remain empty.');
        person_profile_migration_test_assert_same(0, get_post_thumbnail_id($person_id), 'No featured image may be copied.');
        foreach (['person_professional_title', 'person_credentials', 'person_company', 'person_website'] as $field) {
            person_profile_migration_test_assert_true(! metadata_exists('post', $person_id, $field), $field . ' must remain unset.');
            person_profile_migration_test_assert_true(
                in_array(get_field($field, $person_id), [false, null, ''], true),
                $field . ' must remain empty.'
            );
        }
        person_profile_migration_test_assert_same([410, 411], wp_get_object_terms($person_id, 'person_type', ['fields' => 'ids']), 'Exactly Mentor and Kenner must be assigned.');
        foreach (['medewerker_rol', 'medewerker_groep', 'content_topic'] as $taxonomy) {
            person_profile_migration_test_assert_same([], wp_get_object_terms($person_id, $taxonomy, ['fields' => 'ids']), $taxonomy . ' must not be copied or created.');
        }
        person_profile_migration_test_assert_same(
            [$protected_before['posts'][763], $protected_before['posts'][765], $protected_before['posts'][767], $protected_before['posts'][768]],
            [get_post(763), get_post(765), get_post(767), get_post(768)],
            'Known Event and Product posts must remain unchanged.'
        );
        person_profile_migration_test_assert_same(
            $protected_before['postmeta'],
            array_intersect_key($GLOBALS['vg_person_migration_test_postmeta'], $protected_before['postmeta']),
            'Legacy source content and all relationship values must remain unchanged.'
        );
        person_profile_migration_test_assert_same(
            $protected_before['relationships'],
            array_diff_key($GLOBALS['vg_person_migration_test_relationships'], [$person_id => true]),
            'Legacy classifications and relationships must remain unchanged.'
        );
        person_profile_migration_test_assert_same([], get_posts(['post_type' => 'event_participation', 'post_status' => 'any']), 'No Event Participation may be created.');
        person_profile_migration_test_assert_same([], get_posts(['post_type' => 'product_contribution', 'post_status' => 'any']), 'No Product Contribution may be created.');
    },
    'a failure after insertion cleans only the exact owned Person and its data' => static function (): void {
        person_profile_migration_test_reset();
        $GLOBALS['vg_person_migration_test_fail_term_assignment'] = true;
        $GLOBALS['vg_person_migration_test_add_unrelated_on_failure'] = true;

        person_profile_migration_test_assert_throws(
            static fn () => vg_migrate_approved_person_profiles(person_profile_migration_test_authority_rows()),
            'A post-insert taxonomy failure must be reported.'
        );

        person_profile_migration_test_assert_same([[900, true]], $GLOBALS['vg_person_migration_test_delete_calls'], 'Cleanup must hard-delete only the exact newly inserted ID.');
        person_profile_migration_test_assert_same(null, get_post(900), 'The owned Person row must be gone.');
        person_profile_migration_test_assert_same([], get_post_meta(900), 'The owned Person metadata must be gone.');
        person_profile_migration_test_assert_same([], wp_get_object_terms(900, 'person_type', ['fields' => 'ids']), 'The owned Person taxonomy relationships must be gone.');
        person_profile_migration_test_assert_true(is_object(get_post(800)), 'An unrelated Person created during failure cleanup must remain.');
    },
    'cleanup refuses to delete a pre-existing unrelated Person ID' => static function (): void {
        person_profile_migration_test_reset();
        $unrelated = person_profile_migration_test_add_post(800, 'person', 'draft', 'Another Person', 'another-person');

        person_profile_migration_test_assert_throws(
            static fn () => vg_person_migration_cleanup_owned_person(800, [800]),
            'Cleanup must reject an ID that existed before this migration.'
        );

        person_profile_migration_test_assert_same([], $GLOBALS['vg_person_migration_test_delete_calls'], 'An unrelated pre-existing Person must never be deleted.');
        person_profile_migration_test_assert_same($unrelated, get_post(800), 'The unrelated Person row must remain.');
    },
    'cleanup removes the exact owned ID even if a later hook changed its identity fields' => static function (): void {
        person_profile_migration_test_reset();
        person_profile_migration_test_add_post(900, 'person', 'publish', 'Lynette Beer', 'lynette-beer-2');
        $GLOBALS['vg_person_migration_test_postmeta'][900] = ['person_credentials' => 'unexpected hook value'];
        $GLOBALS['vg_person_migration_test_relationships'][900] = ['person_type' => [410]];

        vg_person_migration_cleanup_owned_person(900, []);

        person_profile_migration_test_assert_same([[900, true]], $GLOBALS['vg_person_migration_test_delete_calls'], 'Cleanup must use the tracked new ID.');
        person_profile_migration_test_assert_same(null, get_post(900), 'The exact owned Person must be gone.');
        person_profile_migration_test_assert_same([], get_post_meta(900), 'The owned metadata must be gone.');
        person_profile_migration_test_assert_same([], wp_get_object_terms(900, 'person_type', ['fields' => 'ids']), 'The owned term relationships must be gone.');
    },
];

$failed = 0;
foreach ($tests as $name => $test) {
    try {
        person_profile_migration_test_reset();
        $test();
        fwrite(STDOUT, "PASS {$name}" . PHP_EOL);
    } catch (Throwable $error) {
        ++$failed;
        fwrite(STDERR, "FAIL {$name}: {$error->getMessage()}" . PHP_EOL);
    }
}

if ($failed > 0) {
    fwrite(STDERR, sprintf('%d of %d Person migration tests failed.' . PHP_EOL, $failed, count($tests)));
    exit(1);
}

fwrite(STDOUT, sprintf('%d Person migration tests passed.' . PHP_EOL, count($tests)));
