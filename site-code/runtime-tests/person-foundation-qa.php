<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Taxonomy\ContentTopicRoles;
use Voelgoed\PlatformCore\Taxonomy\ContentTopicTaxonomy;

if (! defined('ABSPATH')) {
    throw new RuntimeException('Run this integration check inside the loaded WordPress runtime.');
}

function vg_person_foundation_qa_check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function vg_person_foundation_qa_relationship_ids(mixed $value): array
{
    if ($value === null || $value === false || $value === '') {
        return [];
    }

    $values = is_array($value) ? $value : [$value];
    $ids = [];
    foreach ($values as $item) {
        if (is_object($item) && isset($item->ID)) {
            $ids[] = (int) $item->ID;
        } elseif (is_array($item) && isset($item['ID'])) {
            $ids[] = (int) $item['ID'];
        } elseif (is_numeric($item)) {
            $ids[] = (int) $item;
        }
    }

    $ids = array_values(array_unique(array_filter($ids)));
    sort($ids);

    return $ids;
}

function vg_person_foundation_qa_term_snapshot(int $post_id, string $taxonomy): array
{
    $terms = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'all']);
    if (is_wp_error($terms)) {
        throw new RuntimeException(sprintf('Could not read %s terms for post %d: %s', $taxonomy, $post_id, $terms->get_error_message()));
    }

    $snapshot = array_map(
        static fn (WP_Term $term): array => ['term_id' => (int) $term->term_id, 'slug' => $term->slug],
        $terms
    );
    usort($snapshot, static fn (array $a, array $b): int => $a['term_id'] <=> $b['term_id']);

    return $snapshot;
}

function vg_person_foundation_qa_legacy_snapshot(): array
{
    $profiles = [];
    foreach ([753, 754, 755, 756, 757] as $post_id) {
        $post = get_post($post_id);
        vg_person_foundation_qa_check($post instanceof WP_Post, sprintf('Legacy profile %d is missing.', $post_id));
        vg_person_foundation_qa_check($post->post_type === 'medewerker', sprintf('Legacy profile %d has an unexpected post type.', $post_id));
        $profiles[$post_id] = [
            'status' => $post->post_status,
            'slug' => $post->post_name,
            'modified' => $post->post_modified,
            'role_terms' => vg_person_foundation_qa_term_snapshot($post_id, 'medewerker_rol'),
            'group_terms' => vg_person_foundation_qa_term_snapshot($post_id, 'medewerker_groep'),
            'content_topic' => vg_person_foundation_qa_term_snapshot($post_id, 'content_topic'),
            'content_category' => vg_person_foundation_qa_term_snapshot($post_id, 'content_category'),
        ];
    }

    return $profiles;
}

function vg_person_foundation_qa_event_product_snapshot(): array
{
    $event = get_post(763);
    vg_person_foundation_qa_check($event instanceof WP_Post, 'Legacy event 763 is missing.');
    vg_person_foundation_qa_check($event->post_type === 'tc_events', 'Legacy event 763 has an unexpected post type.');

    $products = [];
    foreach ([767, 768] as $post_id) {
        $product = get_post($post_id);
        vg_person_foundation_qa_check($product instanceof WP_Post, sprintf('Legacy product %d is missing.', $post_id));
        vg_person_foundation_qa_check($product->post_type === 'product', sprintf('Legacy product %d has an unexpected post type.', $post_id));
        $products[$post_id] = [
            'status' => $product->post_status,
            'slug' => $product->post_name,
            'modified' => $product->post_modified,
            'produk_bron_event' => vg_person_foundation_qa_relationship_ids(get_field('produk_bron_event', $post_id)),
            'produk_medewerkers' => vg_person_foundation_qa_relationship_ids(get_field('produk_medewerkers', $post_id)),
        ];
    }

    return [
        'event' => [
            'status' => $event->post_status,
            'slug' => $event->post_name,
            'modified' => $event->post_modified,
            'event_medewerkers' => vg_person_foundation_qa_relationship_ids(get_field('event_medewerkers', 763)),
        ],
        'products' => $products,
    ];
}

function vg_person_foundation_qa_topic_snapshot(): array
{
    $terms = get_terms([
        'taxonomy' => 'content_topic',
        'hide_empty' => false,
    ]);
    if (is_wp_error($terms)) {
        throw new RuntimeException('Could not read content_topic terms: ' . $terms->get_error_message());
    }

    $snapshot = array_map(
        static fn (WP_Term $term): array => [
            'term_id' => (int) $term->term_id,
            'slug' => $term->slug,
            'parent' => (int) $term->parent,
            'count' => (int) $term->count,
        ],
        $terms
    );
    usort($snapshot, static fn (array $a, array $b): int => $a['term_id'] <=> $b['term_id']);

    return $snapshot;
}

function vg_person_foundation_qa_post_count(): int
{
    global $wpdb;

    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
        'person'
    ));
}

function vg_person_foundation_qa_relationship_count_for_post(int $post_id): int
{
    global $wpdb;

    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE object_id = %d",
        $post_id
    ));
}

function vg_person_foundation_qa_postmeta_count_for_post(int $post_id): int
{
    global $wpdb;

    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d",
        $post_id
    ));
}

function vg_person_foundation_qa_person_relationship_count(string $term_taxonomy): int
{
    global $wpdb;

    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*)
        FROM {$wpdb->term_relationships} AS relationships
        INNER JOIN {$wpdb->term_taxonomy} AS taxonomy
            ON taxonomy.term_taxonomy_id = relationships.term_taxonomy_id
        INNER JOIN {$wpdb->posts} AS posts
            ON posts.ID = relationships.object_id
        WHERE posts.post_type = %s
          AND taxonomy.taxonomy = %s",
        'person',
        $term_taxonomy
    ));
}

function vg_person_foundation_qa_person_type_relationship_count(int $term_id): int
{
    $object_ids = get_objects_in_term($term_id, 'person_type');
    if (is_wp_error($object_ids)) {
        throw new RuntimeException('Could not read person_type relationships: ' . $object_ids->get_error_message());
    }

    return count($object_ids);
}

function vg_person_foundation_qa_person_topic_slugs(int $post_id, string $taxonomy): array
{
    $slugs = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'slugs']);
    if (is_wp_error($slugs)) {
        throw new RuntimeException(sprintf('Could not read %s relationships for Person %d: %s', $taxonomy, $post_id, $slugs->get_error_message()));
    }
    sort($slugs);

    return $slugs;
}

function vg_person_foundation_qa_group_has_person_location(array $group): bool
{
    foreach (($group['location'] ?? []) as $rule_group) {
        foreach ($rule_group as $rule) {
            if (($rule['param'] ?? null) === 'post_type' && ($rule['value'] ?? null) === 'person') {
                return true;
            }
        }
    }

    return false;
}

$roles_class = ContentTopicRoles::class;
vg_person_foundation_qa_check(class_exists($roles_class), 'Platform Core ContentTopicRoles is not loaded.');
vg_person_foundation_qa_check(
    ContentTopicTaxonomy::OBJECT_TYPES === ['post', 'mto-course', 'product', 'tc_events', 'medewerker', 'uitsending', 'kompetisie', 'person'],
    'Platform Core content_topic object types do not match the approved contract.'
);
$content_topic_taxonomy = get_taxonomy('content_topic');
vg_person_foundation_qa_check($content_topic_taxonomy instanceof WP_Taxonomy, 'Platform Core content_topic taxonomy is not registered.');
vg_person_foundation_qa_check($content_topic_taxonomy->object_type === ContentTopicTaxonomy::OBJECT_TYPES, 'Runtime content_topic attachment differs from Platform Core source.');
$person_post_type = get_post_type_object('person');
vg_person_foundation_qa_check($person_post_type instanceof WP_Post_Type, 'The ACF Person post type is not registered.');
vg_person_foundation_qa_check($person_post_type->public && $person_post_type->publicly_queryable && $person_post_type->show_ui && $person_post_type->show_in_rest, 'The Person post type does not meet its public, admin, and REST contract.');
vg_person_foundation_qa_check($person_post_type->has_archive === 'mense', 'The Person archive must register at /mense/.');
vg_person_foundation_qa_check(($person_post_type->rewrite['slug'] ?? null) === 'mense' && ($person_post_type->rewrite['with_front'] ?? null) === false, 'Person singles must use /mense/{slug}/ without a front base.');
$person_type_taxonomy = get_taxonomy('person_type');
vg_person_foundation_qa_check($person_type_taxonomy instanceof WP_Taxonomy, 'The ACF person_type taxonomy is not registered.');
vg_person_foundation_qa_check($person_type_taxonomy->object_type === ['person'], 'person_type must attach only to Person.');
vg_person_foundation_qa_check(! $person_type_taxonomy->public && ! $person_type_taxonomy->publicly_queryable && $person_type_taxonomy->show_ui && $person_type_taxonomy->show_in_rest, 'person_type must be editor-managed without public archives.');
vg_person_foundation_qa_check($person_type_taxonomy->rewrite === false, 'person_type must not register public rewrite rules.');
$person_group = acf_get_field_group('group_vg_person_details');
vg_person_foundation_qa_check(is_array($person_group), 'The Person details field group is not loaded.');
vg_person_foundation_qa_check(($person_group['location'] ?? []) === [[['param' => 'post_type', 'operator' => '==', 'value' => 'person']]], 'The Person details group must target Person only.');
$person_group_fields = acf_get_fields($person_group);
$person_group_field_names = array_map(static fn (array $field): string => (string) ($field['name'] ?? ''), $person_group_fields ?: []);
vg_person_foundation_qa_check(
    $person_group_field_names === ['person_professional_title', 'person_credentials', 'person_company', 'person_website', 'person_type'],
    'The runtime Person details group has unexpected fields.'
);

$profiles_before = vg_person_foundation_qa_legacy_snapshot();
$legacy_relationships_before = vg_person_foundation_qa_event_product_snapshot();
$topic_terms_before = vg_person_foundation_qa_topic_snapshot();
vg_person_foundation_qa_check(count($topic_terms_before) === 55, 'Expected exactly 55 canonical content_topic terms before QA.');
vg_person_foundation_qa_check(count(array_filter($topic_terms_before, static fn (array $term): bool => $term['parent'] === 0)) === 7, 'Expected exactly seven canonical content_topic roots before QA.');
vg_person_foundation_qa_check(vg_person_foundation_qa_post_count() === 0, 'Persistent Person posts must be zero before QA begins.');
vg_person_foundation_qa_check(vg_person_foundation_qa_person_relationship_count('content_topic') === 0, 'Persistent Person topic relationships must be zero before QA begins.');

$person_type_terms = get_terms([
    'taxonomy' => 'person_type',
    'hide_empty' => false,
]);
if (is_wp_error($person_type_terms)) {
    throw new RuntimeException('Could not read person_type terms: ' . $person_type_terms->get_error_message());
}
vg_person_foundation_qa_check(count($person_type_terms) === 1, 'person_type must contain exactly one approved term before QA.');
$mentor = $person_type_terms[0];
vg_person_foundation_qa_check($mentor->name === 'Mentor' && $mentor->slug === 'mentor', 'The approved person_type term must be Mentor (mentor).');
vg_person_foundation_qa_check(vg_person_foundation_qa_person_type_relationship_count((int) $mentor->term_id) === 0, 'Mentor must have no Person relationships before QA.');

$topic = get_term_by('slug', 'gesondheid', 'content_topic');
vg_person_foundation_qa_check($topic instanceof WP_Term, 'The canonical gesondheid content_topic term is missing.');
$parent_topic = get_term_by('slug', 'nuwe-jy', 'content_topic');
vg_person_foundation_qa_check($parent_topic instanceof WP_Term, 'The canonical nuwe-jy parent topic is missing.');
vg_person_foundation_qa_check((int) $topic->parent === (int) $parent_topic->term_id, 'Gesondheid must remain a child of nuwe-jy.');

$primary_group = acf_get_field_group(ContentTopicRoles::PRIMARY_GROUP_KEY);
vg_person_foundation_qa_check(is_array($primary_group), 'The Platform Core Primary Topic field group is unavailable.');
vg_person_foundation_qa_check(! vg_person_foundation_qa_group_has_person_location($primary_group), 'The Primary Topic field group must not target Person.');

$token = gmdate('YmdHis') . '-' . bin2hex(random_bytes(6));
$marker = '[QA][AUTOMATED] Person Foundation Test ' . $token;
$slug = 'person-foundation-qa-' . strtolower(str_replace('-', '', $token));
$person_id = 0;
$qa_failure = null;
$cleanup_failure = null;
$qa_result = [];

try {
    $person_id = wp_insert_post([
        'post_type' => 'person',
        'post_status' => 'publish',
        'post_title' => $marker,
        'post_name' => $slug,
        'post_excerpt' => 'Temporary QA short biography ' . $token,
        'post_content' => 'Temporary QA full biography ' . $token,
        'comment_status' => 'closed',
        'ping_status' => 'closed',
        'meta_input' => [
            '_vg_person_foundation_qa_token' => $token,
            '_vg_person_foundation_qa_marker' => $marker,
        ],
    ], true);
    if (is_wp_error($person_id)) {
        throw new RuntimeException('Could not create the temporary Person: ' . $person_id->get_error_message());
    }
    $person_id = (int) $person_id;
    vg_person_foundation_qa_check($person_id > 0, 'WordPress did not return a valid temporary Person ID.');

    vg_person_foundation_qa_check(
        get_post_meta($person_id, '_vg_person_foundation_qa_token', true) === $token
            && get_post_meta($person_id, '_vg_person_foundation_qa_marker', true) === $marker,
        'The QA ownership token was not retained on the temporary Person.'
    );

    $field_values = [
        'field_vg_person_professional_title' => 'QA Professional Title ' . $token,
        'field_vg_person_credentials' => 'QA Credentials ' . $token,
        'field_vg_person_company' => 'QA Company ' . $token,
        'field_vg_person_website' => 'https://example.com/person-foundation-qa/' . strtolower(str_replace('-', '', $token)),
    ];
    foreach ($field_values as $field_key => $value) {
        vg_person_foundation_qa_check((bool) update_field($field_key, $value, $person_id), sprintf('Could not save ACF field %s.', $field_key));
    }

    $mentor_assignment = wp_set_object_terms($person_id, [(int) $mentor->term_id], 'person_type', false);
    if (is_wp_error($mentor_assignment)) {
        throw new RuntimeException('Could not assign Mentor to the QA Person: ' . $mentor_assignment->get_error_message());
    }
    $topic_assignment = wp_set_object_terms($person_id, [(int) $topic->term_id], 'content_topic', false);
    if (is_wp_error($topic_assignment)) {
        throw new RuntimeException('Could not assign the canonical expertise topic: ' . $topic_assignment->get_error_message());
    }

    $person = get_post($person_id);
    vg_person_foundation_qa_check($person instanceof WP_Post && $person->post_type === 'person', 'The QA record must use post_type person.');
    vg_person_foundation_qa_check($person->post_status === 'publish', 'The QA Person must be published for route testing.');
    vg_person_foundation_qa_check($person->post_name === $slug, 'The QA Person must retain its unique generated slug.');
    vg_person_foundation_qa_check($person->post_excerpt === 'Temporary QA short biography ' . $token, 'The native excerpt did not persist.');
    vg_person_foundation_qa_check($person->post_content === 'Temporary QA full biography ' . $token, 'The native content did not persist.');
    foreach ($field_values as $field_key => $value) {
        vg_person_foundation_qa_check(get_field($field_key, $person_id) === $value, sprintf('ACF field %s did not round-trip.', $field_key));
    }
    vg_person_foundation_qa_check(vg_person_foundation_qa_person_topic_slugs($person_id, 'person_type') === ['mentor'], 'The WordPress person_type relationship is missing.');
    vg_person_foundation_qa_check(vg_person_foundation_qa_person_topic_slugs($person_id, 'content_topic') === ['gesondheid'], 'The direct expertise topic is missing.');

    $roles = new ContentTopicRoles();
    $direct = $roles->get_direct_topics($person_id);
    if (is_wp_error($direct)) {
        throw new RuntimeException('Could not read Person direct topics: ' . $direct->get_error_message());
    }
    $direct_slugs = array_map(static fn (WP_Term $term): string => $term->slug, $direct);
    sort($direct_slugs);
    vg_person_foundation_qa_check($direct_slugs === ['gesondheid'], 'Core direct topics must contain only gesondheid.');

    $effective = $roles->get_effective_topics($person_id);
    if (is_wp_error($effective)) {
        throw new RuntimeException('Could not read Person effective topics: ' . $effective->get_error_message());
    }
    $effective_slugs = array_map(static fn (WP_Term $term): string => $term->slug, $effective);
    sort($effective_slugs);
    vg_person_foundation_qa_check($effective_slugs === ['gesondheid', 'nuwe-jy'], 'Core effective topics must include gesondheid and its nuwe-jy ancestor.');
    vg_person_foundation_qa_check($roles->get_classification_state($person_id) === ContentTopicRoles::STATE_NOT_APPLICABLE, 'Person classification state must be NOT_APPLICABLE.');

    $primary_attempt = $roles->set_primary_topic($person_id, (int) $topic->term_id);
    vg_person_foundation_qa_check(is_wp_error($primary_attempt), 'The Primary Topic setter must reject Person.');
    vg_person_foundation_qa_check(! metadata_exists('post', $person_id, ContentTopicRoles::PRIMARY_META_KEY), 'A rejected Person Primary must not persist.');
    vg_person_foundation_qa_check(! metadata_exists('post', $person_id, '_' . ContentTopicRoles::PRIMARY_META_KEY), 'A rejected Person Primary reference key must not persist.');

    $archive_url = get_post_type_archive_link('person');
    vg_person_foundation_qa_check(is_string($archive_url) && $archive_url !== '', 'WordPress did not produce the Person archive URL.');
    vg_person_foundation_qa_check((string) wp_parse_url($archive_url, PHP_URL_PATH) === '/mense/', 'The Person archive URL must resolve to /mense/.');
    $single_url = get_permalink($person_id);
    vg_person_foundation_qa_check((string) wp_parse_url($single_url, PHP_URL_PATH) === '/mense/' . $slug . '/', 'The Person single URL must resolve under /mense/.');
    vg_person_foundation_qa_check((int) url_to_postid($single_url) === $person_id, 'The Person single rewrite must resolve to the QA Person.');

    $archive_response = wp_remote_get($archive_url, ['timeout' => 20, 'redirection' => 3]);
    if (is_wp_error($archive_response)) {
        throw new RuntimeException('The /mense/ route request failed: ' . $archive_response->get_error_message());
    }
    $archive_status = (int) wp_remote_retrieve_response_code($archive_response);
    vg_person_foundation_qa_check($archive_status === 200, sprintf('The /mense/ route returned HTTP %d.', $archive_status));

    $single_response = wp_remote_get($single_url, ['timeout' => 20, 'redirection' => 3]);
    if (is_wp_error($single_response)) {
        throw new RuntimeException('The Person single route request failed: ' . $single_response->get_error_message());
    }
    $single_status = (int) wp_remote_retrieve_response_code($single_response);
    vg_person_foundation_qa_check($single_status === 200, sprintf('The Person single route returned HTTP %d.', $single_status));

    $qa_result = [
        'person_id' => $person_id,
        'status' => $person->post_status,
        'slug' => $person->post_name,
        'archive_url' => $archive_url,
        'archive_http_status' => $archive_status,
        'single_url' => $single_url,
        'single_http_status' => $single_status,
        'direct_topics' => $direct_slugs,
        'effective_topics' => $effective_slugs,
        'classification_state' => $roles->get_classification_state($person_id),
        'primary_rejected' => true,
    ];
} catch (Throwable $error) {
    $qa_failure = $error;
} finally {
    if ($person_id > 0) {
        $person_to_delete = get_post($person_id);
        $owns_person = $person_to_delete instanceof WP_Post
            && $person_to_delete->post_type === 'person'
            && $person_to_delete->post_title === $marker
            && $person_to_delete->post_name === $slug
            && get_post_meta($person_id, '_vg_person_foundation_qa_token', true) === $token
            && get_post_meta($person_id, '_vg_person_foundation_qa_marker', true) === $marker;

        if (! $owns_person) {
            $cleanup_failure = new RuntimeException('Cleanup refused because the QA ownership marker did not match exactly.');
        } else {
            $deleted = wp_delete_post($person_id, true);
            if (! $deleted || get_post($person_id) !== null) {
                $cleanup_failure = new RuntimeException('Could not permanently delete the owned QA Person.');
            } elseif (vg_person_foundation_qa_postmeta_count_for_post($person_id) !== 0) {
                $cleanup_failure = new RuntimeException('QA Person metadata remains after permanent deletion.');
            } elseif (vg_person_foundation_qa_relationship_count_for_post($person_id) !== 0) {
                $cleanup_failure = new RuntimeException('QA Person taxonomy relationships remain after permanent deletion.');
            }
        }
    }
}

if ($cleanup_failure instanceof Throwable) {
    throw $cleanup_failure;
}
if ($qa_failure instanceof Throwable) {
    throw $qa_failure;
}

$profiles_after = vg_person_foundation_qa_legacy_snapshot();
$legacy_relationships_after = vg_person_foundation_qa_event_product_snapshot();
$topic_terms_after = vg_person_foundation_qa_topic_snapshot();

vg_person_foundation_qa_check($profiles_before === $profiles_after, 'Legacy profiles 753–757 changed during Person QA.');
vg_person_foundation_qa_check($legacy_relationships_before === $legacy_relationships_after, 'Legacy event/product relationships changed during Person QA.');
vg_person_foundation_qa_check($topic_terms_before === $topic_terms_after, 'Canonical content_topic terms or relationship counts changed after cleanup.');
vg_person_foundation_qa_check(vg_person_foundation_qa_post_count() === 0, 'A persistent or trashed Person remains after cleanup.');
vg_person_foundation_qa_check(vg_person_foundation_qa_person_relationship_count('person_type') === 0, 'A Mentor-to-Person relationship remains after cleanup.');
vg_person_foundation_qa_check(vg_person_foundation_qa_person_relationship_count('content_topic') === 0, 'A content_topic-to-Person relationship remains after cleanup.');
vg_person_foundation_qa_check(vg_person_foundation_qa_person_type_relationship_count((int) $mentor->term_id) === 0, 'Mentor must have no persistent Person assignments.');

$report = wp_json_encode([
    'result' => 'PASS',
    'qa_person' => $qa_result,
    'cleanup' => [
        'persistent_person_posts' => vg_person_foundation_qa_post_count(),
        'person_type_relationships' => vg_person_foundation_qa_person_type_relationship_count((int) $mentor->term_id),
        'content_topic_person_relationships' => vg_person_foundation_qa_person_relationship_count('content_topic'),
    ],
    'legacy_profiles_unchanged' => true,
    'event_product_relationships_unchanged' => true,
    'content_topic_terms_and_counts_restored' => true,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::success($report);
} else {
    echo $report . PHP_EOL;
}
