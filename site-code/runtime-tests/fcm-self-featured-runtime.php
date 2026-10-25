<?php
/**
 * Runtime actions for fcm-self-featured-integration.py.
 *
 * fcm-self-featured-cli.php loads this helper through local WP-CLI eval-file,
 * with `$task4f_action`, `$task4f_slug`, and `$task4f_run_id` set for each call.
 */

if (! defined('ABSPATH')) {
    throw new RuntimeException('This helper must run inside WordPress.');
}

function vg_task4f_assert_local_runtime(): void
{
    if (home_url('/') !== 'http://localhost:10039/') {
        throw new RuntimeException('Refusing to run outside http://localhost:10039/.');
    }
}

function vg_task4f_assert_mutation_session(): void
{
    $environment = $GLOBALS['task4f_mutation_environment'] ?? null;
    if (! is_array($environment)) {
        throw new RuntimeException('Create and cleanup actions require an active VGNova mutation session.');
    }

    vg_runtime_mutation_assert_session('vgnova-local-10039', $environment);
}

function vg_task4f_taxonomy_stats(string $taxonomy): array
{
    global $wpdb;

    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
        'fields' => 'ids',
    ]);
    if (is_wp_error($terms)) {
        throw new RuntimeException('Could not read term count for ' . $taxonomy . ': ' . $terms->get_error_message());
    }

    $relationship_stats = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT COUNT(*) AS relationships, COUNT(DISTINCT tr.object_id) AS objects
            FROM {$wpdb->term_relationships} tr
            INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
            WHERE tt.taxonomy = %s",
            $taxonomy
        ),
        ARRAY_A
    );

    return [
        'terms' => count($terms),
        'roots' => count(get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'parent' => 0,
            'fields' => 'ids',
        ])),
        'relationships' => (int) ($relationship_stats['relationships'] ?? 0),
        'objects' => (int) ($relationship_stats['objects'] ?? 0),
    ];
}

function vg_task4f_term_slugs(int $post_id, string $taxonomy): array
{
    $slugs = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'slugs']);
    if (is_wp_error($slugs)) {
        throw new RuntimeException('Could not read ' . $taxonomy . ' on post ' . $post_id . ': ' . $slugs->get_error_message());
    }

    sort($slugs);
    return $slugs;
}

function vg_task4f_post_reference_id(mixed $value): int
{
    if ($value instanceof WP_Post) {
        return (int) $value->ID;
    }
    if (is_array($value)) {
        if (isset($value['ID'])) {
            return (int) $value['ID'];
        }
        if (isset($value[0])) {
            return vg_task4f_post_reference_id($value[0]);
        }
    }
    return is_numeric($value) ? (int) $value : 0;
}

function vg_task4f_featured_options(): array
{
    $fields = [
        'featured_nuwe_jy',
        'featured_geloof',
        'featured_self',
        'featured_werk_geld',
        'featured_ouerskap',
        'featured_huwelik',
        'featured_mensstories',
        'featured_leefstyl',
    ];
    $values = [];

    foreach ($fields as $field) {
        $value = get_field($field, 'featured-content-manager');
        $values[$field] = [
            'value' => vg_task4f_post_reference_id($value),
            'serialized_sha256' => hash('sha256', serialize($value)),
        ];
    }

    return $values;
}

function vg_task4f_migration_hashes(): array
{
    $record = get_option('vgpc_content_topic_migration_local_v1', null);
    $marker = get_option('vgpc_content_topic_migration_local_v1_lifecycle', null);

    return [
        'record_sha256' => hash('sha256', serialize($record)),
        'marker_sha256' => hash('sha256', serialize($marker)),
    ];
}

function vg_task4f_snapshot(): array
{
    global $wpdb;

    $post_counts = wp_count_posts('post');
    $featured_self = get_field('featured_self', 'featured-content-manager');
    $featured_id = vg_task4f_post_reference_id($featured_self);
    $featured_post = $featured_id ? get_post($featured_id) : null;
    $self_term = get_term_by('slug', 'self', 'content_category');
    $post_status_counts = [];
    foreach (['publish', 'draft', 'private', 'pending', 'future', 'trash'] as $status) {
        $post_status_counts[$status] = (int) ($post_counts->{$status} ?? 0);
    }

    $qa_residuals = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT ID, post_name, post_status FROM {$wpdb->posts}
            WHERE post_type = 'post'
              AND (post_title = %s OR post_name LIKE %s)
            ORDER BY ID",
            '[QA][AUTOMATED] Self Featured Content Integration',
            $wpdb->esc_like('qa-automated-self-featured-integration-') . '%'
        ),
        ARRAY_A
    );

    return [
        'posts' => [
            'status_counts' => $post_status_counts,
        ],
        'fixture_presence' => [
            '521' => (bool) get_post(521),
            '523' => (bool) get_post(523),
            '641' => (bool) get_post(641),
        ],
        'content_category_self_exists' => $self_term instanceof WP_Term,
        'taxonomies' => [
            'content_category' => vg_task4f_taxonomy_stats('content_category'),
            'content_topic' => vg_task4f_taxonomy_stats('content_topic'),
            'hf_cat_post' => vg_task4f_taxonomy_stats('hf_cat_post'),
            'editorial_format' => vg_task4f_taxonomy_stats('editorial_format'),
            'editorial_collection' => vg_task4f_taxonomy_stats('editorial_collection'),
        ],
        'featured_options' => vg_task4f_featured_options(),
        'qa_residuals' => $qa_residuals,
        'featured_self_target' => $featured_post ? [
            'id' => (int) $featured_post->ID,
            'post_type' => $featured_post->post_type,
            'status' => $featured_post->post_status,
            'title' => $featured_post->post_title,
            'permalink' => get_permalink($featured_post),
        ] : null,
        'migration' => vg_task4f_migration_hashes(),
    ];
}

function vg_task4f_find_slug(string $slug): array
{
    return get_posts([
        'name' => $slug,
        'post_type' => 'post',
        'post_status' => ['publish', 'draft', 'pending', 'private', 'future', 'trash'],
        'numberposts' => -1,
        'suppress_filters' => true,
    ]);
}

$task4f_result = ['ok' => false];
$task4f_action = $task4f_action ?? '';
$task4f_created_post_id = 0;

try {
    vg_task4f_assert_local_runtime();

    if (! function_exists('get_field')) {
        throw new RuntimeException('ACF is unavailable; cannot read Featured Content Manager options.');
    }
    if (! function_exists('vg_article_get_featured_content_post_id')) {
        throw new RuntimeException('The active Featured Content Manager article helper is not loaded.');
    }

    $action = $task4f_action;

    if ($action === 'snapshot') {
        $task4f_result = ['ok' => true, 'snapshot' => vg_task4f_snapshot()];
    } elseif ($action === 'create') {
        $slug = (string) ($task4f_slug ?? '');
        $run_id = (string) ($task4f_run_id ?? '');
        if (! preg_match('/^qa-automated-self-featured-integration-[a-f0-9-]{20,64}$/', $slug) || $run_id === '') {
            throw new RuntimeException('The temporary post needs a unique QA slug and run marker.');
        }
        if (vg_task4f_find_slug($slug)) {
            throw new RuntimeException('A post already uses this run slug; refusing to modify it.');
        }

        $target_id = vg_task4f_post_reference_id(get_field('featured_self', 'featured-content-manager'));
        $target = $target_id ? get_post($target_id) : null;
        if (! $target || $target->post_status !== 'publish' || ! in_array($target->post_type, ['product', 'mto-course', 'tc_events'], true)) {
            throw new RuntimeException('The current featured_self option does not resolve to a published supported object.');
        }

        vg_task4f_assert_mutation_session();

        $post_id = wp_insert_post([
            'post_type' => 'post',
            'post_status' => 'publish',
            'post_title' => '[QA][AUTOMATED] Self Featured Content Integration',
            'post_name' => $slug,
            'post_content' => '<p>Automated local integration test content.</p>',
            'post_excerpt' => 'Automated local integration test content.',
            'comment_status' => 'closed',
            'ping_status' => 'closed',
        ], true);
        if (is_wp_error($post_id)) {
            throw new RuntimeException('Could not create the temporary QA article: ' . $post_id->get_error_message());
        }
        $task4f_created_post_id = (int) $post_id;

        update_post_meta((int) $post_id, '_task4f_fcm_run_id', $run_id);
        if (get_post_meta((int) $post_id, '_task4f_fcm_run_id', true) !== $run_id) {
            throw new RuntimeException('Could not write the temporary post ownership marker.');
        }
        $category_result = wp_set_object_terms((int) $post_id, ['self'], 'content_category', false);
        if (is_wp_error($category_result)) {
            throw new RuntimeException('Could not assign the existing content_category=self term: ' . $category_result->get_error_message());
        }

        $post = get_post((int) $post_id);
        $category_slugs = vg_task4f_term_slugs((int) $post_id, 'content_category');
        $happyfiles_slugs = vg_task4f_term_slugs((int) $post_id, 'hf_cat_post');
        $topic_slugs = vg_task4f_term_slugs((int) $post_id, 'content_topic');
        $format_slugs = vg_task4f_term_slugs((int) $post_id, 'editorial_format');
        $collection_slugs = vg_task4f_term_slugs((int) $post_id, 'editorial_collection');
        $primary = get_post_meta((int) $post_id, '_vg_primary_content_topic', true);
        $selected_id = (int) vg_article_get_featured_content_post_id($post);

        if ($post->post_type !== 'post' || $post->post_status !== 'publish') {
            throw new RuntimeException('The temporary object is not a published standard post.');
        }
        if ($category_slugs !== ['self']) {
            throw new RuntimeException('The temporary post does not have exactly content_category=self.');
        }
        if ($happyfiles_slugs !== ['self']) {
            throw new RuntimeException('The active HappyFiles integration did not mirror the Self assignment.');
        }
        if ($topic_slugs || $format_slugs || $collection_slugs || $primary !== '') {
            throw new RuntimeException('The temporary post received an unexpected topic, Primary Topic, editorial format, or collection.');
        }
        if ($selected_id !== $target_id) {
            throw new RuntimeException('The active article helper did not route the Self article to the current featured_self object.');
        }

        $task4f_result = [
            'ok' => true,
            'post' => [
                'id' => (int) $post_id,
                'title' => $post->post_title,
                'slug' => $post->post_name,
                'status' => $post->post_status,
                'post_type' => $post->post_type,
                'permalink' => get_permalink($post),
                'content_category' => $category_slugs,
                'happyfiles' => $happyfiles_slugs,
                'content_topic' => $topic_slugs,
                'editorial_format' => $format_slugs,
                'editorial_collection' => $collection_slugs,
                'primary_topic' => $primary,
            ],
            'featured' => [
                'id' => (int) $target->ID,
                'post_type' => $target->post_type,
                'title' => $target->post_title,
                'permalink' => get_permalink($target),
                'helper_selected_id' => $selected_id,
            ],
        ];
    } elseif ($action === 'cleanup') {
        global $wpdb;

        $slug = (string) ($task4f_slug ?? '');
        $run_id = (string) ($task4f_run_id ?? '');
        if (! preg_match('/^qa-automated-self-featured-integration-[a-f0-9-]{20,64}$/', $slug) || $run_id === '') {
            throw new RuntimeException('Cleanup requires the exact run slug and marker.');
        }

        $matches = vg_task4f_find_slug($slug);
        if (count($matches) > 1) {
            throw new RuntimeException('More than one post has the temporary run slug; cleanup refused.');
        }
        $deleted_id = null;
        if ($matches) {
            $post = $matches[0];
            $owner_marker = get_post_meta((int) $post->ID, '_task4f_fcm_run_id', true);
            if ($post->post_title !== '[QA][AUTOMATED] Self Featured Content Integration' || $owner_marker !== $run_id) {
                throw new RuntimeException('The matching post does not carry this run token; cleanup refused.');
            }
            vg_task4f_assert_mutation_session();
            $deleted_id = (int) $post->ID;
            if (! wp_delete_post($deleted_id, true)) {
                throw new RuntimeException('WordPress did not delete the temporary QA post.');
            }
        }

        $by_slug_after = vg_task4f_find_slug($slug);
        $database_row = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_name = %s LIMIT 1", $slug));
        $relationship_count = $deleted_id ? (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE object_id = %d", $deleted_id)) : 0;
        $remaining_meta = $deleted_id ? get_post_meta($deleted_id) : [];
        if ($by_slug_after || $database_row || $relationship_count !== 0 || $remaining_meta) {
            throw new RuntimeException('Temporary post cleanup left a post row, taxonomy relationship, or post meta.');
        }

        $task4f_result = [
            'ok' => true,
            'deleted_id' => $deleted_id,
            'post_absent' => true,
            'taxonomy_relationships_absent' => true,
            'post_meta_absent' => true,
        ];
    } else {
        throw new RuntimeException('Unknown runtime action.');
    }
} catch (Throwable $error) {
    $cleanup_error = null;
    if ($task4f_action === 'create' && $task4f_created_post_id && get_post($task4f_created_post_id)) {
        try {
            vg_task4f_assert_mutation_session();
            if (! wp_delete_post($task4f_created_post_id, true) || get_post($task4f_created_post_id)) {
                $cleanup_error = ' Emergency cleanup of the temporary post failed.';
            }
        } catch (Throwable) {
            $cleanup_error = ' Emergency cleanup of the temporary post was refused because the mutation session was no longer valid.';
        }
    }
    $task4f_result = [
        'ok' => false,
        'error' => $error->getMessage() . ($cleanup_error ?? ''),
    ];
}

echo wp_json_encode($task4f_result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
