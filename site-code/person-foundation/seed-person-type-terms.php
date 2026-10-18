<?php
declare(strict_types=1);

/**
 * Seed the approved initial person_type vocabulary.
 *
 * Run with:
 * wp eval-file /path/to/seed-person-type-terms.php
 */
function vg_seed_person_type_terms(): array
{
    $taxonomy = 'person_type';
    $approved_terms = [
        'mentor' => 'Mentor',
    ];

    if (! taxonomy_exists($taxonomy)) {
        throw new RuntimeException('The ACF-owned person_type taxonomy is not registered.');
    }

    $current_terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
    ]);
    if (is_wp_error($current_terms)) {
        throw new RuntimeException('Could not read person_type terms: ' . $current_terms->get_error_message());
    }

    foreach ($current_terms as $current_term) {
        if (
            $current_term->slug === 'mentor'
            && $current_term->name !== 'Mentor'
        ) {
            throw new RuntimeException(sprintf(
                'The person_type slug "%s" conflicts with the approved name "%s". No terms were changed.',
                $current_term->slug,
                'Mentor'
            ));
        }

        if (
            $current_term->name === 'Mentor'
            && $current_term->slug !== 'mentor'
        ) {
            throw new RuntimeException(sprintf(
                'The person_type name "%s" conflicts with the approved slug "%s". No terms were changed.',
                $current_term->name,
                'mentor'
            ));
        }

        if ($current_term->slug !== 'mentor' || $current_term->name !== 'Mentor') {
            throw new RuntimeException(sprintf(
                'Unexpected person_type term "%s" (%s) exists. No terms were changed.',
                $current_term->name,
                $current_term->slug
            ));
        }
    }

    foreach ($approved_terms as $slug => $name) {
        $term_by_slug = get_term_by('slug', $slug, $taxonomy);
        $term_by_name = get_term_by('name', $name, $taxonomy);

        if ($term_by_slug) {
            if (
                $term_by_slug->taxonomy !== $taxonomy
                || $term_by_slug->slug !== $slug
                || $term_by_slug->name !== $name
                || ($term_by_name && $term_by_name->term_id !== $term_by_slug->term_id)
            ) {
                throw new RuntimeException(sprintf(
                    'The person_type term "%s" conflicts with the approved definition. No terms were changed.',
                    $slug
                ));
            }

            continue;
        }

        if ($term_by_name) {
            throw new RuntimeException(sprintf(
                'The person_type name "%s" already exists with slug "%s". No terms were changed.',
                $name,
                $term_by_name->slug
            ));
        }
    }

    $created = [];
    $existing = [];

    foreach ($approved_terms as $slug => $name) {
        $term = get_term_by('slug', $slug, $taxonomy);
        if ($term) {
            $existing[] = $slug;
            continue;
        }

        $inserted = wp_insert_term($name, $taxonomy, ['slug' => $slug]);
        if (is_wp_error($inserted)) {
            throw new RuntimeException(sprintf(
                'Could not create person_type term "%s": %s',
                $slug,
                $inserted->get_error_message()
            ));
        }

        $created_term = get_term_by('slug', $slug, $taxonomy);
        if (! $created_term || $created_term->name !== $name || $created_term->slug !== $slug) {
            throw new RuntimeException(sprintf(
                'The created person_type term "%s" did not retain its approved name and slug.',
                $slug
            ));
        }

        $created[] = $slug;
    }

    return [
        'created' => $created,
        'existing' => $existing,
    ];
}

if (defined('WP_CLI') && WP_CLI && ! defined('VG_PERSON_FOUNDATION_TESTING')) {
    try {
        $result = vg_seed_person_type_terms();
        \WP_CLI::success(sprintf(
            'Person type terms created: %s. Already present: %s.',
            $result['created'] ? implode(', ', $result['created']) : 'none',
            $result['existing'] ? implode(', ', $result['existing']) : 'none'
        ));
    } catch (Throwable $error) {
        \WP_CLI::error($error->getMessage());
    }
}
