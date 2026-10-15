<?php
declare(strict_types=1);

/**
 * Seed the closed, approved editorial format vocabulary.
 *
 * Run with:
 * wp eval-file /path/to/seed-editorial-format-terms.php
 */
function vg_seed_editorial_format_terms(): array
{
    $taxonomy = 'editorial_format';
    $approved_terms = [
        'article' => 'Artikel',
        'video' => 'Video',
        'audio' => 'Oudio',
    ];

    if (! taxonomy_exists($taxonomy)) {
        throw new RuntimeException('The ACF-owned editorial_format taxonomy is not registered.');
    }

    $current_terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
    ]);
    if (is_wp_error($current_terms)) {
        throw new RuntimeException('Could not read editorial_format terms: ' . $current_terms->get_error_message());
    }

    foreach ($current_terms as $current_term) {
        if (! isset($approved_terms[$current_term->slug])) {
            throw new RuntimeException(sprintf(
                'Unexpected editorial_format term "%s" (%s) exists. No terms were changed.',
                $current_term->name,
                $current_term->slug
            ));
        }

        if ($current_term->name !== $approved_terms[$current_term->slug]) {
            throw new RuntimeException(sprintf(
                'The editorial_format slug "%s" already has the unexpected name "%s". No terms were changed.',
                $current_term->slug,
                $current_term->name
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
                    'The editorial_format term "%s" conflicts with the approved definition. No terms were changed.',
                    $slug
                ));
            }

            continue;
        }

        if ($term_by_name) {
            throw new RuntimeException(sprintf(
                'The editorial_format name "%s" already exists with slug "%s". No terms were changed.',
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
                'Could not create editorial_format term "%s": %s',
                $slug,
                $inserted->get_error_message()
            ));
        }

        $created_term = get_term_by('slug', $slug, $taxonomy);
        if (! $created_term || $created_term->name !== $name || $created_term->slug !== $slug) {
            throw new RuntimeException(sprintf(
                'The created editorial_format term "%s" did not retain its approved name and slug.',
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

if (defined('WP_CLI') && WP_CLI && ! defined('VG_EDITORIAL_DIMENSIONS_TESTING')) {
    try {
        $result = vg_seed_editorial_format_terms();
        \WP_CLI::success(sprintf(
            'Editorial format terms created: %s. Already present: %s.',
            $result['created'] ? implode(', ', $result['created']) : 'none',
            $result['existing'] ? implode(', ', $result['existing']) : 'none'
        ));
    } catch (Throwable $error) {
        \WP_CLI::error($error->getMessage());
    }
}
