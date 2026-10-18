<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Event\EventParticipationRepository;
use Voelgoed\PlatformCore\Event\EventParticipationService;

if (! defined('ABSPATH')) {
    throw new RuntimeException('Run this integration check inside the loaded WordPress runtime.');
}

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

function vg_event_participation_qa_post_count(string $type): int
{
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", $type));
}

function vg_event_participation_qa_relationship_count(int $post_id): int
{
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE object_id = %d", $post_id));
}

function vg_event_participation_qa_meta_count(int $post_id): int
{
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d", $post_id));
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
    $event_state = [$event->post_status, $event->post_name, $event->post_modified, vg_event_participation_qa_ids(get_field('event_medewerkers', 763)), vg_event_participation_qa_terms(763, 'event_category')];
    $products = [];
    foreach ([767, 768] as $id) {
        $post = get_post($id);
        vg_event_participation_qa_check($post instanceof WP_Post && $post->post_type === 'product', 'Protected product ' . $id . ' is missing.');
        $products[$id] = [$post->post_status, $post->post_name, $post->post_modified, get_field('produk_bron_event', $id), vg_event_participation_qa_ids(get_field('produk_medewerkers', $id)), vg_event_participation_qa_terms($id, 'product_cat'), vg_event_participation_qa_terms($id, 'product_tag')];
    }
    return [
        'profiles' => $profiles, 'attachments' => $attachments, 'event' => $event_state, 'products' => $products,
        'topics' => vg_event_participation_qa_vocab('content_topic'),
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
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->term_relationships} r JOIN {$wpdb->term_taxonomy} t ON t.term_taxonomy_id=r.term_taxonomy_id JOIN {$wpdb->posts} p ON p.ID=r.object_id WHERE p.post_type=%s AND t.taxonomy=%s",
        'person', $taxonomy
    ));
}

function vg_event_participation_qa_taxonomy_relationship_count(string $taxonomy): int
{
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->term_relationships} r JOIN {$wpdb->term_taxonomy} t ON t.term_taxonomy_id=r.term_taxonomy_id WHERE t.taxonomy=%s", $taxonomy));
}

function vg_event_participation_qa_token_exists(string $token): bool
{
    global $wpdb;
    $like = '%' . $wpdb->esc_like($token) . '%';
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id=p.ID WHERE p.post_title LIKE %s OR p.post_name LIKE %s OR m.meta_value LIKE %s",
        $like, $like, $like
    )) > 0;
}

vg_event_participation_qa_check(class_exists(EventParticipationService::class), 'Platform Core EventParticipationService is not loaded.');
foreach ([EventParticipationRepository::POST_TYPE, 'person'] as $type) {
    vg_event_participation_qa_check(post_type_exists($type), 'Required post type ' . $type . ' is not registered.');
}
vg_event_participation_qa_check(taxonomy_exists('event_role'), 'Required event_role taxonomy is not registered.');
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
    if ($candidate_id !== 763 && vg_event_participation_qa_ids(get_field('event_medewerkers', $candidate_id)) === []) {
        $qa_event_id = $candidate_id;
        break;
    }
}
vg_event_participation_qa_check($qa_event_id > 0, 'No published QA Event with empty event_medewerkers was found.');
$qa_event = get_post($qa_event_id);
$before = vg_event_participation_qa_snapshot();
$qa_event_before = [$qa_event->post_status, $qa_event->post_name, $qa_event->post_modified, vg_event_participation_qa_ids(get_field('event_medewerkers', $qa_event_id)), vg_event_participation_qa_terms($qa_event_id, 'event_category')];
vg_event_participation_qa_check($before['event'][3] === [753, 757], 'Legacy Event 763 baseline does not match.');

$token = gmdate('YmdHis') . '-' . bin2hex(random_bytes(8));
$owner = '[QA][AUTOMATED] ' . $token;
$person_id = 0;
$participation_id = 0;
$qa_failure = null;
$cleanup_failure = null;
$result = [];
$service = new EventParticipationService();
try {
    $inserted = wp_insert_post(['post_type' => 'person', 'post_status' => 'publish', 'post_title' => $owner, 'post_name' => 'ep-qa-' . strtolower(str_replace('-', '', $token)), 'meta_input' => ['_vg_task_6a_qa_owner' => $owner]], true);
    if (is_wp_error($inserted)) {
        throw new RuntimeException('Could not create QA Person: ' . $inserted->get_error_message());
    }
    $person_id = (int) $inserted;
    vg_event_participation_qa_check($person_id > 0 && get_post_meta($person_id, '_vg_task_6a_qa_owner', true) === $owner, 'QA Person ownership marker did not persist.');

    $created = $service->create($qa_event_id, $person_id, [(int) $role_by_slug['spreker']['id'], (int) $role_by_slug['paneellid']['id']], 17, 'private');
    if (is_wp_error($created)) {
        throw new RuntimeException('Could not create QA participation: ' . $created->get_error_message());
    }
    $participation_id = (int) $created;
    vg_event_participation_qa_check($participation_id > 0, 'Service returned an invalid participation ID.');
    vg_event_participation_qa_check(update_post_meta($participation_id, '_vg_task_6a_qa_owner', $owner) !== false, 'Could not write QA participation ownership marker.');

    $repo = new EventParticipationRepository();
    vg_event_participation_qa_check((int) get_field(EventParticipationRepository::EVENT_FIELD, $participation_id) === $qa_event_id, 'Event endpoint did not persist.');
    vg_event_participation_qa_check((int) get_field(EventParticipationRepository::PERSON_FIELD, $participation_id) === $person_id, 'Person endpoint did not persist.');
    vg_event_participation_qa_check((int) get_field(EventParticipationRepository::DISPLAY_ORDER_FIELD, $participation_id) === 17, 'Display order did not persist.');
    vg_event_participation_qa_check(get_the_title($participation_id) === get_the_title($qa_event_id) . ' — ' . get_the_title($person_id), 'Participation title is not derived from its endpoints.');
    vg_event_participation_qa_check($repo->participations_for_event($qa_event_id) === [$participation_id] && $repo->participations_for_person($person_id) === [$participation_id], 'Canonical Event/Person participation queries failed.');
    vg_event_participation_qa_check($repo->people_for_event($qa_event_id) === [$person_id] && $repo->events_for_person($person_id) === [$qa_event_id], 'Canonical reverse relationship queries failed.');
    vg_event_participation_qa_check(wp_get_post_terms($participation_id, 'event_role', ['fields' => 'slugs']) === ['paneellid', 'spreker'], 'Participation roles do not match Spreker and Paneellid.');
    vg_event_participation_qa_check(! is_post_publicly_viewable(get_post($participation_id)), 'Private participation must not be publicly viewable.');

    $duplicate = $service->create($qa_event_id, $person_id, [], 1, 'publish');
    vg_event_participation_qa_check(is_wp_error($duplicate), 'Duplicate Event/Person pair was accepted.');
    vg_event_participation_qa_check($repo->live_participations_for_event($qa_event_id) === [$participation_id], 'Duplicate attempt created a second valid participation.');
    vg_event_participation_qa_check(is_wp_error($service->validate_pair(999999999, $person_id)), 'Validator accepted a missing Event.');
    vg_event_participation_qa_check(is_wp_error($service->validate_pair($qa_event_id, 999999999)), 'Validator accepted a missing Person.');
    vg_event_participation_qa_check(wp_trash_post($person_id) instanceof WP_Post, 'Could not temporarily trash the QA Person.');
    vg_event_participation_qa_check(is_wp_error($service->validate_pair($qa_event_id, $person_id)), 'Validator accepted a trashed Person endpoint.');
    vg_event_participation_qa_check(wp_untrash_post($person_id) instanceof WP_Post, 'Could not restore the QA Person after validator check.');

    vg_event_participation_qa_check(wp_update_post(['ID' => $person_id, 'post_title' => $owner . ' changed'], true) === $person_id, 'Could not change temporary QA Person title.');
    vg_event_participation_qa_check(get_the_title($participation_id) === get_the_title($qa_event_id) . ' — ' . get_the_title($person_id), 'Participation title did not follow the changed Person title.');
    $result = ['event_id' => $qa_event_id, 'person_id' => $person_id, 'participation_id' => $participation_id, 'roles' => ['paneellid', 'spreker'], 'display_order' => 17, 'private' => true, 'duplicate_rejected' => true, 'invalid_endpoints_rejected' => true];
} catch (Throwable $error) {
    $qa_failure = $error;
} finally {
    foreach ([['id' => $participation_id, 'type' => EventParticipationRepository::POST_TYPE], ['id' => $person_id, 'type' => 'person']] as $owned) {
        $id = (int) $owned['id'];
        if ($id <= 0) {
            continue;
        }
        $post = get_post($id);
        if (! ($post instanceof WP_Post) || $post->post_type !== $owned['type'] || get_post_meta($id, '_vg_task_6a_qa_owner', true) !== $owner) {
            $cleanup_failure = new RuntimeException('Cleanup refused because a QA ownership marker did not match.');
            continue;
        }
        if (! wp_delete_post($id, true) || get_post($id) !== null || vg_event_participation_qa_postmeta_count($id) !== 0 || vg_event_participation_qa_relationship_count($id) !== 0) {
            $cleanup_failure = new RuntimeException('QA record cleanup did not remove the post, metadata, and term relationships.');
        }
    }
}
if ($cleanup_failure instanceof Throwable) {
    throw $cleanup_failure;
}
if ($qa_failure instanceof Throwable) {
    throw $qa_failure;
}
vg_event_participation_qa_check(! vg_event_participation_qa_token_exists($token), 'The QA run token remains in posts or post metadata.');
vg_event_participation_qa_check(vg_event_participation_qa_post_count('person') === 0 && vg_event_participation_qa_post_count(EventParticipationRepository::POST_TYPE) === 0, 'A Person or participation remains after cleanup.');
$after = vg_event_participation_qa_snapshot();
$qa_event_after = get_post($qa_event_id);
$qa_event_after_state = [$qa_event_after->post_status, $qa_event_after->post_name, $qa_event_after->post_modified, vg_event_participation_qa_ids(get_field('event_medewerkers', $qa_event_id)), vg_event_participation_qa_terms($qa_event_id, 'event_category')];
vg_event_participation_qa_check($before === $after, 'Protected runtime state changed during Event Participation QA.');
vg_event_participation_qa_check($qa_event_before === $qa_event_after_state, 'The selected QA Event changed during QA.');
$report = wp_json_encode(['result' => 'PASS', 'qa' => $result, 'cleanup' => ['persons' => 0, 'participations' => 0, 'run_token_found' => false], 'protected_state_unchanged' => true, 'qa_event_unchanged' => true], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::success($report);
} else {
    echo $report . PHP_EOL;
}
