<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Event\EventParticipationRepository;
use Voelgoed\PlatformCore\Event\EventParticipationService;

if (! defined('ABSPATH')) {
    echo json_encode([
        'result' => 'FAIL',
        'phase' => 'bootstrap',
        'mutation_started' => false,
        'failures' => [[
            'phase' => 'bootstrap',
            'message' => 'Run this integration check inside the loaded WordPress runtime.',
        ]],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(1);
}

require_once __DIR__ . '/event-participation-qa-setup.php';
require_once __DIR__ . '/event-participation-qa-ownership.php';

function vg_event_participation_qa_check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function vg_event_participation_qa_ids(mixed $value): array
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

function vg_event_participation_qa_terms(int $post_id, string $taxonomy): array
{
    $terms = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'all']);
    if (is_wp_error($terms)) {
        throw new RuntimeException($terms->get_error_message());
    }
    $result = array_map(static fn (WP_Term $term): array => [
        'id' => (int) $term->term_id, 'slug' => $term->slug,
    ], $terms);
    usort($result, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);
    return $result;
}

function vg_event_participation_qa_count_query(string $query, string $description): int
{
    global $wpdb;
    $value = $wpdb->get_var($query);
    if ($wpdb->last_error !== '') {
        throw new RuntimeException('Could not read ' . $description . ': ' . $wpdb->last_error);
    }
    if ($value === null) {
        throw new RuntimeException('The count query returned no result for ' . $description . '.');
    }
    return (int) $value;
}

function vg_event_participation_qa_post_count(string $type): int
{
    global $wpdb;
    return vg_event_participation_qa_count_query(
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", $type),
        'post type ' . $type
    );
}

function vg_event_participation_qa_relationship_count(int $post_id): int
{
    global $wpdb;
    return vg_event_participation_qa_count_query(
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE object_id = %d", $post_id),
        'term relationships for post ' . $post_id
    );
}

function vg_event_participation_qa_meta_count(int $post_id): int
{
    global $wpdb;
    return vg_event_participation_qa_count_query(
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d", $post_id),
        'metadata for post ' . $post_id
    );
}

function vg_event_participation_qa_vocab(string $taxonomy): array
{
    $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
    if (is_wp_error($terms)) {
        throw new RuntimeException('Could not read ' . $taxonomy . ': ' . $terms->get_error_message());
    }
    $result = array_map(static fn (WP_Term $term): array => [
        'id' => (int) $term->term_id, 'slug' => $term->slug, 'name' => $term->name,
        'parent' => (int) $term->parent, 'count' => (int) $term->count,
    ], $terms);
    usort($result, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);
    return $result;
}

function vg_event_participation_qa_snapshot(): array
{
    $profiles = [];
    foreach ([753, 754, 755, 756, 757] as $id) {
        $post = get_post($id);
        vg_event_participation_qa_check($post instanceof WP_Post && $post->post_type === 'medewerker', 'Legacy profile ' . $id . ' is missing or changed type.');
        $profiles[$id] = [
            'status' => $post->post_status, 'slug' => $post->post_name, 'modified' => $post->post_modified,
            'title' => $post->post_title, 'excerpt' => $post->post_excerpt, 'content' => $post->post_content,
            'fields' => [get_field('medewerker_titel', $id), get_field('medewerker_kort_bio', $id), get_field('medewerker_profiel_status', $id)],
            'roles' => vg_event_participation_qa_terms($id, 'medewerker_rol'),
            'groups' => vg_event_participation_qa_terms($id, 'medewerker_groep'),
            'topics' => vg_event_participation_qa_terms($id, 'content_topic'),
            'categories' => vg_event_participation_qa_terms($id, 'content_category'),
        ];
    }
    $attachments = [];
    foreach ([558, 560, 562, 564, 566] as $id) {
        $post = get_post($id);
        vg_event_participation_qa_check($post instanceof WP_Post && $post->post_type === 'attachment', 'Protected attachment ' . $id . ' is missing.');
        $attachments[$id] = [$post->post_status, $post->post_name, $post->post_modified, $post->post_title, $post->post_parent, wp_get_attachment_metadata($id)];
    }
    $event = get_post(763);
    vg_event_participation_qa_check($event instanceof WP_Post && $event->post_type === 'tc_events', 'Legacy event 763 is missing.');
    $event_state = [
        $event->post_status,
        $event->post_name,
        $event->post_modified,
        vg_event_participation_qa_ids(get_field('event_medewerkers', 763)),
        vg_event_participation_qa_terms(763, 'event_category'),
        vg_event_participation_qa_terms(763, 'content_topic'),
        $event->post_title,
    ];
    $products = [];
    foreach ([767, 768] as $id) {
        $post = get_post($id);
        vg_event_participation_qa_check($post instanceof WP_Post && $post->post_type === 'product', 'Protected product ' . $id . ' is missing.');
        $products[$id] = [
            $post->post_status,
            $post->post_name,
            $post->post_modified,
            get_field('produk_bron_event', $id),
            vg_event_participation_qa_ids(get_field('produk_medewerkers', $id)),
            vg_event_participation_qa_terms($id, 'product_cat'),
            vg_event_participation_qa_terms($id, 'product_tag'),
            vg_event_participation_qa_terms($id, 'content_topic'),
        ];
    }
    return [
        'profiles' => $profiles, 'attachments' => $attachments, 'event' => $event_state, 'products' => $products,
        'topics' => vg_event_participation_qa_vocab('content_topic'),
        'person_count' => vg_event_participation_qa_post_count('person'),
        'person_type_relationships' => vg_event_participation_qa_person_taxonomy_count('person_type'),
        'person_topic_relationships' => vg_event_participation_qa_person_taxonomy_count('content_topic'),
        'participation_count' => vg_event_participation_qa_post_count(EventParticipationRepository::POST_TYPE),
        'role_relationships' => vg_event_participation_qa_taxonomy_relationship_count('event_role'),
        'roles' => vg_event_participation_qa_vocab('event_role'),
    ];
}

function vg_event_participation_qa_person_taxonomy_count(string $taxonomy): int
{
    global $wpdb;
    return vg_event_participation_qa_count_query(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->term_relationships} r JOIN {$wpdb->term_taxonomy} t ON t.term_taxonomy_id=r.term_taxonomy_id JOIN {$wpdb->posts} p ON p.ID=r.object_id WHERE p.post_type=%s AND t.taxonomy=%s",
            'person',
            $taxonomy
        ),
        'Person relationships for taxonomy ' . $taxonomy
    );
}

function vg_event_participation_qa_taxonomy_relationship_count(string $taxonomy): int
{
    global $wpdb;
    return vg_event_participation_qa_count_query(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->term_relationships} r JOIN {$wpdb->term_taxonomy} t ON t.term_taxonomy_id=r.term_taxonomy_id WHERE t.taxonomy=%s",
            $taxonomy
        ),
        'relationships for taxonomy ' . $taxonomy
    );
}

function vg_event_participation_qa_token_exists(string $token): bool
{
    global $wpdb;
    $like = '%' . $wpdb->esc_like($token) . '%';
    $post_matches = vg_event_participation_qa_count_query(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title LIKE %s OR post_name LIKE %s OR post_content LIKE %s OR post_excerpt LIKE %s",
            $like,
            $like,
            $like,
            $like
        ),
        'run token in posts'
    );
    $meta_matches = vg_event_participation_qa_count_query(
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_value LIKE %s", $like),
        'run token in post metadata'
    );
    return $post_matches > 0 || $meta_matches > 0;
}

$preflight_failure = null;
try {
    vg_event_participation_qa_check(class_exists(EventParticipationService::class), 'Platform Core EventParticipationService is not loaded.');
    foreach ([EventParticipationRepository::POST_TYPE, 'person'] as $type) {
        vg_event_participation_qa_check(post_type_exists($type), 'Required post type ' . $type . ' is not registered.');
    }
    vg_event_participation_qa_check(taxonomy_exists('event_role'), 'Required event_role taxonomy is not registered.');
    $participation_type = get_post_type_object(EventParticipationRepository::POST_TYPE);
    vg_event_participation_qa_check($participation_type instanceof WP_Post_Type, 'Event Participation post type object is missing.');
    vg_event_participation_qa_check(
        ! $participation_type->public
            && ! $participation_type->publicly_queryable
            && $participation_type->exclude_from_search
            && $participation_type->show_in_rest
            && ! $participation_type->has_archive
            && $participation_type->rewrite === false,
        'Event Participation must remain private, non-queryable, excluded from search, REST-enabled, without an archive or rewrite.'
    );
    $role_taxonomy = get_taxonomy('event_role');
    $role_object_types = is_object($role_taxonomy) ? (array) $role_taxonomy->object_type : [];
    sort($role_object_types);
    vg_event_participation_qa_check($role_object_types === [EventParticipationRepository::POST_TYPE], 'event_role must be attached only to event_participation.');
    vg_event_participation_qa_check(vg_event_participation_qa_post_count('person') === 0, 'Persistent Person count must be zero before QA.');
    vg_event_participation_qa_check(vg_event_participation_qa_post_count(EventParticipationRepository::POST_TYPE) === 0, 'Persistent participation count must be zero before QA.');
    $role_vocab = vg_event_participation_qa_vocab('event_role');
    $role_by_slug = [];
    foreach ($role_vocab as $term) {
        $role_by_slug[$term['slug']] = $term;
    }
    $expected_roles = ['fasiliteerder' => 'Fasiliteerder', 'gas' => 'Gas', 'paneellid' => 'Paneellid', 'spreker' => 'Spreker'];
    $actual_roles = array_column($role_vocab, 'name', 'slug');
    ksort($actual_roles);
    vg_event_participation_qa_check($actual_roles === $expected_roles, 'event_role must contain exactly the four approved terms.');
    vg_event_participation_qa_check(vg_event_participation_qa_ids(get_field('event_medewerkers', 763)) === [753, 757], 'Event 763 must retain its exact legacy relationships 753 and 757.');
    vg_event_participation_qa_check(vg_event_participation_qa_ids(get_field('produk_bron_event', 767)) === [763] && vg_event_participation_qa_ids(get_field('produk_medewerkers', 767)) === [753], 'Product 767 relationships differ from the approved baseline.');
    vg_event_participation_qa_check(vg_event_participation_qa_ids(get_field('produk_bron_event', 768)) === [763] && vg_event_participation_qa_ids(get_field('produk_medewerkers', 768)) === [753, 757], 'Product 768 relationships differ from the approved baseline.');
    $candidates = get_posts(['post_type' => 'tc_events', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC']);
    $qa_event_id = 0;
    foreach ($candidates as $candidate_id) {
        $candidate_id = (int) $candidate_id;
        $candidate = get_post($candidate_id);
        if (
            $candidate_id !== 763
            && $candidate instanceof WP_Post
            && $candidate->post_type === 'tc_events'
            && $candidate->post_status === 'publish'
            && vg_event_participation_qa_ids(get_field('event_medewerkers', $candidate_id)) === []
        ) {
            $qa_event_id = $candidate_id;
            break;
        }
    }
    vg_event_participation_qa_check($qa_event_id > 0, 'No published QA Event with empty event_medewerkers was found.');
    $qa_event = get_post($qa_event_id);
    vg_event_participation_qa_check($qa_event instanceof WP_Post && $qa_event->ID !== 763, 'The QA Event must be a published Event other than Event 763.');
    $before = vg_event_participation_qa_snapshot();
    $qa_event_before = [
        $qa_event->post_type,
        $qa_event->post_status,
        $qa_event->post_name,
        $qa_event->post_modified,
        vg_event_participation_qa_ids(get_field('event_medewerkers', $qa_event_id)),
        vg_event_participation_qa_terms($qa_event_id, 'event_category'),
        vg_event_participation_qa_terms($qa_event_id, 'content_topic'),
        $qa_event->post_title,
    ];
    vg_event_participation_qa_check($before['event'][3] === [753, 757], 'Legacy Event 763 baseline does not match.');
} catch (Throwable $error) {
    $preflight_failure = $error;
}
if ($preflight_failure instanceof Throwable) {
    $preflight_report = wp_json_encode([
        'result' => 'FAIL',
        'phase' => 'preflight',
        'mutation_started' => false,
        'failures' => [['phase' => 'preflight', 'message' => $preflight_failure->getMessage()]],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (defined('WP_CLI') && WP_CLI) {
        \WP_CLI::line($preflight_report);
        \WP_CLI::error('Event Participation runtime QA preflight failed.', 1);
    }
    echo $preflight_report . PHP_EOL;
    throw new RuntimeException('Event Participation runtime QA preflight failed.');
}

$setup = vg_event_participation_qa_initialize(
    static fn (): string => gmdate('YmdHis') . '-' . bin2hex(random_bytes(8)),
    static fn (): EventParticipationService => new EventParticipationService()
);
if ($setup['failure'] !== null) {
    $setup_report = wp_json_encode(
        vg_event_participation_qa_setup_failure_report($setup['failure']),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
    );
    if (defined('WP_CLI') && WP_CLI) {
        \WP_CLI::line($setup_report);
        \WP_CLI::error('Event Participation runtime QA setup failed.', 1);
    }
    echo $setup_report . PHP_EOL;
    throw new RuntimeException('Event Participation runtime QA setup failed.');
}

$token = $setup['token'];
$owner = $setup['owner'];
$person_slug = 'ep-qa-' . strtolower(str_replace('-', '', $token));
$person_id = 0;
$participation_id = 0;
$owned_participations = [];
$qa_failure = null;
$cleanup_failures = [];
$result = [];
$service = $setup['service'];
try {
    vg_runtime_mutation_assert_session('vgnova-local-10039');
    $inserted = wp_insert_post(['post_type' => 'person', 'post_status' => 'publish', 'post_title' => $owner, 'post_name' => $person_slug, 'meta_input' => ['_vg_task_6a_qa_owner' => $owner]], true);
    if (is_wp_error($inserted)) {
        throw new RuntimeException('Could not create QA Person: ' . $inserted->get_error_message());
    }
    $person_id = (int) $inserted;
    vg_event_participation_qa_check($person_id > 0 && get_post_meta($person_id, '_vg_task_6a_qa_owner', true) === $owner, 'QA Person ownership marker did not persist.');

    $created = $service->create($qa_event_id, $person_id, [(int) $role_by_slug['spreker']['id'], (int) $role_by_slug['paneellid']['id']], 17, 'publish');
    if (is_wp_error($created)) {
        throw new RuntimeException('Could not create QA participation: ' . $created->get_error_message());
    }
    $participation_id = (int) $created;
    vg_event_participation_qa_track_participation($owned_participations, $participation_id, $qa_event_id, $person_id, $owner);

    $repo = new EventParticipationRepository();
    vg_event_participation_qa_check((int) get_field(EventParticipationRepository::EVENT_FIELD, $participation_id) === $qa_event_id, 'Event endpoint did not persist.');
    vg_event_participation_qa_check((int) get_field(EventParticipationRepository::PERSON_FIELD, $participation_id) === $person_id, 'Person endpoint did not persist.');
    vg_event_participation_qa_check((int) get_field(EventParticipationRepository::DISPLAY_ORDER_FIELD, $participation_id) === 17, 'Display order did not persist.');
    vg_event_participation_qa_check(get_the_title($participation_id) === get_the_title($qa_event_id) . ' — ' . get_the_title($person_id), 'Participation title is not derived from its endpoints.');
    vg_event_participation_qa_check($repo->participations_for_event($qa_event_id) === [$participation_id] && $repo->participations_for_person($person_id) === [$participation_id], 'Canonical Event/Person participation queries failed.');
    vg_event_participation_qa_check($repo->people_for_event($qa_event_id) === [$person_id] && $repo->events_for_person($person_id) === [$qa_event_id], 'Canonical reverse relationship queries failed.');
    $participation_roles = wp_get_post_terms($participation_id, 'event_role', ['fields' => 'slugs']);
    sort($participation_roles);
    vg_event_participation_qa_check($participation_roles === ['paneellid', 'spreker'], 'Participation roles do not match Spreker and Paneellid.');
    vg_event_participation_qa_check(! is_post_publicly_viewable(get_post($participation_id)), 'Published Event Participation must not be publicly viewable.');

    $duplicate = $service->create($qa_event_id, $person_id, [], 1, 'publish');
    if (! is_wp_error($duplicate)) {
        vg_event_participation_qa_track_participation($owned_participations, (int) $duplicate, $qa_event_id, $person_id, $owner);
    }
    vg_event_participation_qa_check(
        is_wp_error($duplicate) && $duplicate->get_error_code() === 'duplicate_event_participation',
        'Duplicate Event/Person pair was not rejected with duplicate_event_participation.'
    );
    vg_event_participation_qa_check($repo->live_participations_for_event($qa_event_id) === [$participation_id], 'Duplicate attempt created a second valid participation.');
    $private_update = wp_update_post(['ID' => $participation_id, 'post_status' => 'private'], true);
    vg_event_participation_qa_check($private_update === $participation_id, 'Could not temporarily change the QA participation to private.');
    vg_event_participation_qa_check(
        $repo->participations_for_event($qa_event_id) === []
            && $repo->participations_for_person($person_id) === []
            && $repo->people_for_event($qa_event_id) === []
            && $repo->events_for_person($person_id) === [],
        'Private participation leaked into active query results.'
    );
    vg_event_participation_qa_check(! is_post_publicly_viewable(get_post($participation_id)), 'Private participation must not be publicly viewable.');
    $published_update = wp_update_post(['ID' => $participation_id, 'post_status' => 'publish'], true);
    vg_event_participation_qa_check($published_update === $participation_id, 'Could not restore the QA participation to publish.');
    vg_event_participation_qa_check(
        $repo->participations_for_event($qa_event_id) === [$participation_id]
            && $repo->participations_for_person($person_id) === [$participation_id]
            && $repo->people_for_event($qa_event_id) === [$person_id]
            && $repo->events_for_person($person_id) === [$qa_event_id],
        'Published participation did not return to active query results.'
    );
    $missing_event = $service->validate_pair(999999999, $person_id, $participation_id);
    vg_event_participation_qa_check(is_wp_error($missing_event) && $missing_event->get_error_code() === 'invalid_event', 'Validator did not reject the missing Event with invalid_event.');
    $missing_person = $service->validate_pair($qa_event_id, 999999999, $participation_id);
    vg_event_participation_qa_check(is_wp_error($missing_person) && $missing_person->get_error_code() === 'invalid_person', 'Validator did not reject the missing Person with invalid_person.');
    $wrong_event_type = $service->validate_pair($person_id, $person_id, $participation_id);
    vg_event_participation_qa_check(is_wp_error($wrong_event_type) && $wrong_event_type->get_error_code() === 'invalid_event', 'Validator accepted a Person ID as an Event.');
    $wrong_person_type = $service->validate_pair($qa_event_id, $qa_event_id, $participation_id);
    vg_event_participation_qa_check(is_wp_error($wrong_person_type) && $wrong_person_type->get_error_code() === 'invalid_person', 'Validator accepted an Event ID as a Person.');
    vg_event_participation_qa_check(wp_trash_post($person_id) instanceof WP_Post, 'Could not temporarily trash the QA Person.');
    $trashed_person = $service->validate_pair($qa_event_id, $person_id, $participation_id);
    vg_event_participation_qa_check(is_wp_error($trashed_person) && $trashed_person->get_error_code() === 'invalid_person', 'Validator did not reject the trashed Person with invalid_person.');
    vg_event_participation_qa_check(wp_trash_post($participation_id) instanceof WP_Post, 'Could not retire the QA participation while its Person endpoint is trashed.');
    vg_event_participation_qa_check(get_post_status($participation_id) === 'trash', 'The QA participation was not retired after its Person endpoint was trashed.');
    vg_event_participation_qa_check(wp_untrash_post($person_id) instanceof WP_Post, 'Could not restore the QA Person after validator check.');
    vg_event_participation_qa_check(wp_untrash_post($participation_id) instanceof WP_Post, 'Could not reactivate the QA participation after restoring its Person endpoint.');
    vg_event_participation_qa_check(get_post_status($participation_id) === 'draft', 'WordPress did not restore the retired QA participation as a draft.');
    vg_event_participation_qa_check(wp_update_post(['ID' => $participation_id, 'post_status' => 'publish'], true) === $participation_id, 'Could not publish the restored QA participation.');
    vg_event_participation_qa_check(get_post_status($participation_id) === 'publish', 'The QA participation did not publish after its Person endpoint was restored.');

    vg_event_participation_qa_check(wp_update_post(['ID' => $person_id, 'post_title' => $owner . ' changed'], true) === $person_id, 'Could not change temporary QA Person title.');
    vg_event_participation_qa_check(get_the_title($participation_id) === get_the_title($qa_event_id) . ' — ' . get_the_title($person_id), 'Participation title did not follow the changed Person title.');
    $result = ['event_id' => $qa_event_id, 'person_id' => $person_id, 'participation_id' => $participation_id, 'roles' => ['paneellid', 'spreker'], 'display_order' => 17, 'published_queries_pass' => true, 'private_query_exclusion_pass' => true, 'non_public_routing_pass' => true, 'duplicate_rejected' => true, 'invalid_endpoints_rejected' => true, 'invalid_endpoint_retirement_pass' => true];
} catch (Throwable $error) {
    $qa_failure = $error;
} finally {
    $cleanup_failures = vg_event_participation_qa_cleanup_owned_records($owned_participations, $person_id, $owner, $person_slug);
}
$audit_failures = [];
$audit = static function (string $name, callable $assertion) use (&$audit_failures): void {
    try {
        $assertion();
    } catch (Throwable $error) {
        $audit_failures[] = ['check' => $name, 'message' => $error->getMessage()];
    }
};
$token_found = null;
$remaining_persons = null;
$remaining_participations = null;
$remaining_person_type_relationships = null;
$remaining_person_topic_relationships = null;
$remaining_role_relationships = null;
$protected_state_unchanged = false;
$qa_event_unchanged = false;
$audit('run_token_absent', static function () use ($token, &$token_found): void {
    $token_found = vg_event_participation_qa_token_exists($token);
    vg_event_participation_qa_check(! $token_found, 'The QA run token remains in posts or post metadata.');
});
$audit('persistent_records_removed', static function () use (&$remaining_persons, &$remaining_participations): void {
    $remaining_persons = vg_event_participation_qa_post_count('person');
    $remaining_participations = vg_event_participation_qa_post_count(EventParticipationRepository::POST_TYPE);
    vg_event_participation_qa_check($remaining_persons === 0 && $remaining_participations === 0, 'A Person or participation remains after cleanup.');
});
$audit('person_relationships_removed', static function () use (&$remaining_person_type_relationships, &$remaining_person_topic_relationships): void {
    $remaining_person_type_relationships = vg_event_participation_qa_person_taxonomy_count('person_type');
    $remaining_person_topic_relationships = vg_event_participation_qa_person_taxonomy_count('content_topic');
    vg_event_participation_qa_check($remaining_person_type_relationships === 0 && $remaining_person_topic_relationships === 0, 'Person taxonomy relationships remain after cleanup.');
});
$audit('role_relationships_removed', static function () use (&$remaining_role_relationships): void {
    $remaining_role_relationships = vg_event_participation_qa_taxonomy_relationship_count('event_role');
    vg_event_participation_qa_check($remaining_role_relationships === 0, 'Event Participation event_role relationships remain after cleanup.');
});
$after = null;
$audit('protected_state_unchanged', static function () use ($before, &$after, &$protected_state_unchanged): void {
    $after = vg_event_participation_qa_snapshot();
    vg_event_participation_qa_check($before === $after, 'Protected runtime state changed during Event Participation QA.');
    $protected_state_unchanged = true;
});
$audit('qa_event_unchanged', static function () use ($qa_event_id, $qa_event_before, &$qa_event_unchanged): void {
    $qa_event_after = get_post($qa_event_id);
    vg_event_participation_qa_check($qa_event_after instanceof WP_Post, 'The selected QA Event disappeared.');
    $qa_event_after_state = [
        $qa_event_after->post_type,
        $qa_event_after->post_status,
        $qa_event_after->post_name,
        $qa_event_after->post_modified,
        vg_event_participation_qa_ids(get_field('event_medewerkers', $qa_event_id)),
        vg_event_participation_qa_terms($qa_event_id, 'event_category'),
        vg_event_participation_qa_terms($qa_event_id, 'content_topic'),
        $qa_event_after->post_title,
    ];
    vg_event_participation_qa_check($qa_event_before === $qa_event_after_state, 'The selected QA Event changed during QA.');
    $qa_event_unchanged = true;
});
$failures = [];
if ($qa_failure instanceof Throwable) {
    $failures[] = ['phase' => 'qa', 'message' => $qa_failure->getMessage()];
}
foreach ($cleanup_failures as $failure) {
    $failures[] = ['phase' => 'cleanup'] + $failure;
}
foreach ($audit_failures as $failure) {
    $failures[] = ['phase' => 'audit'] + $failure;
}
$report = wp_json_encode([
    'result' => $failures === [] ? 'PASS' : 'FAIL',
    'qa' => $result,
    'cleanup' => [
        'tracked_participation_ids' => array_map('intval', array_keys($owned_participations)),
        'persons_remaining' => $remaining_persons,
        'participations_remaining' => $remaining_participations,
        'run_token_found' => $token_found,
        'person_type_relationships_remaining' => $remaining_person_type_relationships,
        'person_content_topic_relationships_remaining' => $remaining_person_topic_relationships,
        'event_role_relationships_remaining' => $remaining_role_relationships,
        'ownership_cleanup_failures' => $cleanup_failures,
    ],
    'protected_state_unchanged' => $protected_state_unchanged,
    'qa_event_unchanged' => $qa_event_unchanged,
    'failures' => $failures,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::line($report);
    if ($failures !== []) {
        \WP_CLI::error('Event Participation runtime QA failed.', 1);
    }
} else {
    echo $report . PHP_EOL;
    if ($failures !== []) {
        throw new RuntimeException('Event Participation runtime QA failed.');
    }
}
