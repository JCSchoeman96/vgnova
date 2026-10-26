<?php
declare(strict_types=1);

/**
 * Migrate only owner-authorized Person identities from the versioned CSV.
 *
 * Run through site-code/bin/vgnova-runtime-mutate.php with purpose=migration:
 * wp eval-file site-code/person-foundation/migrate-approved-person-profiles.php
 */

/** @return list<array<string,string>> */
function vg_person_migration_load_authority(string $path): array
{
    $headers = [
        'legacy_id',
        'decision',
        'target_title',
        'target_slug',
        'target_status',
        'person_types',
        'copy_featured_image',
        'copy_excerpt',
        'copy_content',
        'copy_person_fields',
        'relationship_policy',
        'authority',
    ];

    if (! is_file($path) || ! is_readable($path)) {
        throw new RuntimeException('The source-controlled Person migration authority file is missing or unreadable.');
    }

    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('The source-controlled Person migration authority file could not be opened.');
    }

    try {
        $actual_headers = fgetcsv($handle, 0, ',', '"', '');
        if (! is_array($actual_headers)) {
            throw new RuntimeException('The Person migration authority file has no CSV header.');
        }
        $actual_headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $actual_headers[0]) ?? (string) $actual_headers[0];
        if ($actual_headers !== $headers) {
            throw new RuntimeException('The Person migration authority file has an unexpected header.');
        }

        $rows = [];
        while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if ($values === [null] || $values === []) {
                continue;
            }
            if (count($values) !== count($headers)) {
                throw new RuntimeException('The Person migration authority file contains a row with the wrong number of columns.');
            }
            $row = array_combine($headers, array_map(static fn (mixed $value): string => (string) ($value ?? ''), $values));
            if (! is_array($row)) {
                throw new RuntimeException('The Person migration authority file contains an invalid row.');
            }
            $rows[] = $row;
        }
    } finally {
        fclose($handle);
    }

    $expected_ids = ['753', '754', '755', '756', '757'];
    if (array_column($rows, 'legacy_id') !== $expected_ids) {
        throw new RuntimeException('The Person migration authority must contain the five locked legacy profile IDs in order.');
    }

    return $rows;
}

/** @param list<array<string,string>> $rows @return array{eligible:list<array<string,string>>,skipped:list<int>} */
function vg_person_migration_select_authorized_rows(array $rows): array
{
    $eligible = [];
    $skipped = [];
    $seen_ids = [];
    $known_ids = [753, 754, 755, 756, 757];

    foreach ($rows as $row) {
        $raw_id = $row['legacy_id'] ?? '';
        if (! is_string($raw_id) || ! preg_match('/\A[1-9][0-9]*\z/', $raw_id)) {
            throw new RuntimeException('The Person migration authority contains an invalid legacy ID.');
        }
        $legacy_id = (int) $raw_id;
        if (! in_array($legacy_id, $known_ids, true)) {
            throw new RuntimeException(sprintf('Legacy profile %d is outside the Task 10A migration scope.', $legacy_id));
        }
        if (isset($seen_ids[$legacy_id])) {
            throw new RuntimeException(sprintf('Legacy profile %d appears more than once in the migration authority.', $legacy_id));
        }
        $seen_ids[$legacy_id] = true;

        $decision = $row['decision'] ?? '';
        if (! in_array($decision, ['MIGRATE', 'DO_NOT_MIGRATE', 'DEFER'], true)) {
            throw new RuntimeException(sprintf('Legacy profile %d has an unsupported migration decision.', $legacy_id));
        }

        if ($decision === 'MIGRATE') {
            if ($legacy_id !== 753) {
                throw new RuntimeException(sprintf('Task 10A authorizes only legacy profile 753; profile %d cannot migrate.', $legacy_id));
            }
            $eligible[] = $row;
            continue;
        }

        $skipped[] = $legacy_id;
    }

    if (count($eligible) > 1) {
        throw new RuntimeException('Task 10A authorizes exactly one Person profile migration.');
    }

    return ['eligible' => $eligible, 'skipped' => $skipped];
}

/** @param array<string,string> $row */
function vg_person_migration_validate_approved_policy(array $row): array
{
    $expected = [
        'legacy_id' => '753',
        'decision' => 'MIGRATE',
        'target_title' => 'Lynette Beer',
        'target_slug' => 'lynette-beer',
        'target_status' => 'publish',
        'person_types' => 'mentor|kenner',
        'copy_featured_image' => 'NO',
        'copy_excerpt' => 'NO',
        'copy_content' => 'NO',
        'copy_person_fields' => 'NO',
        'relationship_policy' => 'EXCLUDE_ALL_CURRENT',
        'authority' => 'OWNER_CONFIRMED_2026-10-25',
    ];

    foreach ($expected as $field => $value) {
        if (($row[$field] ?? null) !== $value) {
            throw new RuntimeException(sprintf('The approved policy for legacy profile 753 has an unexpected %s value.', $field));
        }
    }

    return ['mentor', 'kenner'];
}

/** @return list<int> */
function vg_person_migration_all_status_post_ids(string $post_type): array
{
    $statuses = get_post_stati([], 'names');
    if (! is_array($statuses) || $statuses === []) {
        throw new RuntimeException('WordPress could not enumerate post statuses for a fail-closed Person migration preflight.');
    }

    $posts = get_posts([
        'post_type' => $post_type,
        'post_status' => array_values($statuses),
        'numberposts' => -1,
        'fields' => 'ids',
        'suppress_filters' => true,
    ]);
    if (! is_array($posts)) {
        throw new RuntimeException(sprintf('WordPress could not enumerate %s posts.', $post_type));
    }

    $ids = array_map('intval', $posts);
    sort($ids);

    return $ids;
}

/** @return array<string,int> */
function vg_person_migration_validate_person_type_vocabulary(): array
{
    if (! taxonomy_exists('person_type')) {
        throw new RuntimeException('The ACF-owned person_type taxonomy is not registered.');
    }

    $terms = get_terms(['taxonomy' => 'person_type', 'hide_empty' => false]);
    if (is_wp_error($terms) || ! is_array($terms)) {
        throw new RuntimeException('The approved person_type vocabulary could not be read.');
    }

    $expected = ['mentor' => 'Mentor', 'kenner' => 'Kenner'];
    $actual = [];
    foreach ($terms as $term) {
        if (! isset($term->slug, $term->name, $term->term_id) || ! is_string($term->slug) || ! is_string($term->name)) {
            throw new RuntimeException('The person_type vocabulary contains an invalid term record.');
        }
        if (isset($actual[$term->slug])) {
            throw new RuntimeException(sprintf('The person_type slug "%s" is duplicated.', $term->slug));
        }
        $actual[$term->slug] = $term;
    }
    if (array_keys($actual) !== array_keys($expected)) {
        ksort($actual);
        $slugs = array_keys($actual);
        sort($slugs);
        if ($slugs !== ['kenner', 'mentor']) {
            throw new RuntimeException('The person_type vocabulary must contain only Mentor and Kenner.');
        }
    }

    $term_ids = [];
    foreach ($expected as $slug => $name) {
        $term = $actual[$slug] ?? null;
        if (! $term || $term->name !== $name || $term->taxonomy !== 'person_type') {
            throw new RuntimeException(sprintf('The person_type term %s does not match its approved definition.', $slug));
        }
        if ((int) ($term->count ?? 0) !== 0) {
            throw new RuntimeException('Task 10A requires zero pre-existing Person Type relationships.');
        }
        $term_ids[$slug] = (int) $term->term_id;
    }

    return $term_ids;
}

/** @return array<string,mixed> */
function vg_person_migration_capture_protected_legacy_state(): array
{
    $state = ['posts' => [], 'meta' => [], 'terms' => []];
    $protected_posts = [753, 754, 755, 756, 757, 763, 765, 767, 768];

    foreach ($protected_posts as $post_id) {
        $post = get_post($post_id);
        if (! $post) {
            throw new RuntimeException(sprintf('Protected legacy post %d is missing.', $post_id));
        }
        $post_values = get_object_vars($post);
        ksort($post_values);
        $state['posts'][$post_id] = $post_values;

        $meta = get_post_meta($post_id);
        if (! is_array($meta)) {
            throw new RuntimeException(sprintf('Protected legacy metadata for post %d could not be read.', $post_id));
        }
        unset($meta['_edit_lock'], $meta['_edit_last']);
        ksort($meta);
        $state['meta'][$post_id] = $meta;

        $taxonomies = get_object_taxonomies($post->post_type, 'names');
        if (! is_array($taxonomies)) {
            throw new RuntimeException(sprintf('Protected legacy taxonomies for post %d could not be read.', $post_id));
        }
        sort($taxonomies);
        $state['terms'][$post_id] = [];
        foreach ($taxonomies as $taxonomy) {
            $term_ids = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);
            if (is_wp_error($term_ids) || ! is_array($term_ids)) {
                throw new RuntimeException(sprintf('Protected legacy taxonomy relationships for post %d could not be read.', $post_id));
            }
            $term_ids = array_map('intval', $term_ids);
            sort($term_ids);
            $state['terms'][$post_id][$taxonomy] = $term_ids;
        }
        ksort($state['terms'][$post_id]);
    }

    ksort($state['posts']);
    ksort($state['meta']);
    ksort($state['terms']);

    return $state;
}

/** @return array{person:int,event_participation:int,product_contribution:int} */
function vg_person_migration_persistent_counts(): array
{
    return [
        'person' => count(vg_person_migration_all_status_post_ids('person')),
        'event_participation' => count(vg_person_migration_all_status_post_ids('event_participation')),
        'product_contribution' => count(vg_person_migration_all_status_post_ids('product_contribution')),
    ];
}

/**
 * Delete only the exact new Person owned by this migration and verify its cleanup.
 *
 * @param list<int> $preexisting_person_ids
 */
function vg_person_migration_cleanup_owned_person(
    int $person_id,
    array $preexisting_person_ids
): void {
    if (in_array($person_id, $preexisting_person_ids, true)) {
        throw new RuntimeException(sprintf('Cleanup refused to delete pre-existing Person ID %d.', $person_id));
    }

    $post = get_post($person_id);
    if (! $post) {
        if (get_post_meta($person_id) !== []) {
            throw new RuntimeException(sprintf('Cleanup found metadata residue for missing Person ID %d.', $person_id));
        }
    } elseif ($post->post_type !== 'person') {
        throw new RuntimeException(sprintf('Cleanup refused to delete non-Person ID %d.', $person_id));
    } else {
        $deleted = wp_delete_post($person_id, true);
        if (! $deleted) {
            throw new RuntimeException(sprintf('Hard deletion failed for owned Person ID %d.', $person_id));
        }
    }

    if (get_post($person_id)) {
        throw new RuntimeException(sprintf('Owned Person ID %d remains after cleanup.', $person_id));
    }
    if (get_post_meta($person_id) !== []) {
        throw new RuntimeException(sprintf('Post metadata remains for owned Person ID %d after cleanup.', $person_id));
    }

    $taxonomies = array_unique(['person_type', 'content_topic', 'content_category', ...get_object_taxonomies('person', 'names')]);
    foreach ($taxonomies as $taxonomy) {
        $term_ids = wp_get_object_terms($person_id, $taxonomy, ['fields' => 'ids']);
        if (is_wp_error($term_ids) || ! is_array($term_ids) || $term_ids !== []) {
            throw new RuntimeException(sprintf('Taxonomy relationships remain for owned Person ID %d after cleanup.', $person_id));
        }
    }
}

/** @param list<array<string,string>> $authority_rows @return array<string,mixed> */
function vg_migrate_approved_person_profiles(array $authority_rows): array
{
    $selection = vg_person_migration_select_authorized_rows($authority_rows);
    if ($selection['eligible'] === []) {
        return [
            'created_person_ids' => [],
            'migrated_legacy_ids' => [],
            'skipped_legacy_ids' => $selection['skipped'],
            'person_type_term_ids' => [],
            'counts_before' => null,
            'counts_after' => null,
        ];
    }

    $row = $selection['eligible'][0];
    $person_types = vg_person_migration_validate_approved_policy($row);

    $legacy_post = get_post(753);
    if (
        ! $legacy_post
        || (int) $legacy_post->ID !== 753
        || $legacy_post->post_type !== 'medewerker'
        || $legacy_post->post_status !== 'publish'
        || $legacy_post->post_title !== 'Lynette Beer'
        || $legacy_post->post_name !== 'lynette-beer'
    ) {
        throw new RuntimeException('Legacy profile 753 no longer matches its approved source identity.');
    }

    $person_ids_before = vg_person_migration_all_status_post_ids('person');
    if ($person_ids_before !== []) {
        throw new RuntimeException('Canonical Person records already exist; migration requires review before any write.');
    }
    $counts_before = vg_person_migration_persistent_counts();
    if ($counts_before !== ['person' => 0, 'event_participation' => 0, 'product_contribution' => 0]) {
        throw new RuntimeException('Canonical Person relationship records do not match the approved zero-count pre-state.');
    }

    $term_ids = vg_person_migration_validate_person_type_vocabulary();
    if (array_keys($term_ids) !== $person_types) {
        throw new RuntimeException('The approved Person Type assignments do not match the migration policy.');
    }

    $protected_legacy_before = vg_person_migration_capture_protected_legacy_state();
    $owned_person_id = null;

    try {
        $inserted = wp_insert_post([
            'post_type' => 'person',
            'post_status' => 'publish',
            'post_title' => 'Lynette Beer',
            'post_name' => 'lynette-beer',
            'post_content' => '',
            'post_excerpt' => '',
        ], true);

        if (is_wp_error($inserted)) {
            throw new RuntimeException('The canonical Person insert failed: ' . $inserted->get_error_message());
        }
        if (! is_int($inserted) || $inserted < 1) {
            throw new RuntimeException('The canonical Person insert did not return a valid new ID.');
        }

        // Record ownership immediately; cleanup still checks the pre-state and exact target identity.
        $owned_person_id = $inserted;
        if (in_array($owned_person_id, $person_ids_before, true)) {
            throw new RuntimeException('The canonical Person insert returned a pre-existing ID.');
        }

        $inserted_terms = wp_set_object_terms($owned_person_id, array_values($term_ids), 'person_type', false);
        if (is_wp_error($inserted_terms)) {
            throw new RuntimeException('The canonical Person Type assignment failed: ' . $inserted_terms->get_error_message());
        }

        $person = get_post($owned_person_id);
        if (
            ! $person
            || $person->post_type !== 'person'
            || $person->post_status !== 'publish'
            || $person->post_title !== 'Lynette Beer'
            || $person->post_name !== 'lynette-beer'
            || $person->post_content !== ''
            || $person->post_excerpt !== ''
            || get_post_thumbnail_id($owned_person_id) !== 0
        ) {
            throw new RuntimeException('The canonical Person content does not match the approved empty identity target.');
        }

        foreach (['person_professional_title', 'person_credentials', 'person_company', 'person_website'] as $field) {
            $field_value = get_field($field, $owned_person_id);
            if (
                metadata_exists('post', $owned_person_id, $field)
                || metadata_exists('post', $owned_person_id, '_' . $field)
                || get_post_meta($owned_person_id, $field, true) !== ''
                || ! in_array($field_value, [false, null, ''], true)
            ) {
                throw new RuntimeException(sprintf('Optional Person field %s is unexpectedly populated.', $field));
            }
        }

        foreach (get_object_taxonomies('person', 'names') as $taxonomy) {
            $actual_term_ids = wp_get_object_terms($owned_person_id, $taxonomy, ['fields' => 'ids']);
            if (is_wp_error($actual_term_ids) || ! is_array($actual_term_ids)) {
                throw new RuntimeException(sprintf('The canonical Person %s relationships could not be verified.', $taxonomy));
            }
            $actual_term_ids = array_map('intval', $actual_term_ids);
            sort($actual_term_ids);
            $expected_term_ids = $taxonomy === 'person_type' ? array_values($term_ids) : [];
            sort($expected_term_ids);
            if ($actual_term_ids !== $expected_term_ids) {
                throw new RuntimeException(sprintf('The canonical Person has an unexpected %s assignment.', $taxonomy));
            }
        }

        $actual_person_type_slugs = [];
        foreach (wp_get_object_terms($owned_person_id, 'person_type', ['fields' => 'all']) as $term) {
            $actual_person_type_slugs[] = (string) $term->slug;
        }
        sort($actual_person_type_slugs);
        if ($actual_person_type_slugs !== ['kenner', 'mentor']) {
            throw new RuntimeException('The canonical Person must have exactly Mentor and Kenner assignments.');
        }

        $counts_after = vg_person_migration_persistent_counts();
        if ($counts_after !== ['person' => 1, 'event_participation' => 0, 'product_contribution' => 0]) {
            throw new RuntimeException('Canonical post counts differ from the approved migration result.');
        }
        if (vg_person_migration_capture_protected_legacy_state() !== $protected_legacy_before) {
            throw new RuntimeException('Protected legacy profiles, Events, Products, metadata, or relationships changed during migration.');
        }

        return [
            'created_person_ids' => [$owned_person_id],
            'migrated_legacy_ids' => [753],
            'skipped_legacy_ids' => $selection['skipped'],
            'person_type_term_ids' => $term_ids,
            'counts_before' => $counts_before,
            'counts_after' => $counts_after,
        ];
    } catch (Throwable $error) {
        if ($owned_person_id !== null) {
            try {
                vg_person_migration_cleanup_owned_person(
                    $owned_person_id,
                    $person_ids_before
                );
            } catch (Throwable $cleanup_error) {
                throw new RuntimeException(
                    $error->getMessage() . ' Owned Person cleanup also failed: ' . $cleanup_error->getMessage(),
                    0,
                    $error
                );
            }
        }

        throw $error;
    }
}

if (defined('WP_CLI') && WP_CLI && ! defined('VG_PERSON_PROFILE_MIGRATION_TESTING')) {
    try {
        require_once dirname(__DIR__) . '/runtime-tools/runtime-mutation-session.php';
        vg_runtime_mutation_assert_session('vgnova-local-10039');
        $authority_path = dirname(__DIR__, 2) . '/docs/architecture/PERSON_PROFILE_MIGRATION_AUTHORITY_v1.0.0.csv';
        $authority_rows = vg_person_migration_load_authority($authority_path);
        $result = vg_migrate_approved_person_profiles($authority_rows);
        \WP_CLI::success(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    } catch (Throwable $error) {
        \WP_CLI::error($error->getMessage());
    }
}
