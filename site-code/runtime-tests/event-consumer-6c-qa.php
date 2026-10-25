<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Event\EventParticipationRepository;
use Voelgoed\PlatformCore\Event\EventParticipationService;

if (! defined('ABSPATH')) {
    echo json_encode([
        'result' => 'FAIL',
        'phase' => 'bootstrap',
        'mutation_started' => false,
        'failures' => [['message' => 'Run this QA file inside the loaded WordPress runtime.']],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
}

require_once __DIR__ . '/event-consumer-6c-qa-ownership.php';
require_once __DIR__ . '/event-consumer-6c-qa-rendering.php';

const VG_EVENT_CONSUMER_6C_QA_DIR = '/tmp/vgnova-task6c/qa';
const VG_EVENT_CONSUMER_6C_QA_EVENT_FILE = '/tmp/vgnova-task6c/qa-event.json';
const VG_EVENT_CONSUMER_6C_QA_OWNER_KEY = '_vg_task_6c_qa_owner';

function vg_event_consumer_6c_qa_emit(array $report, int $exit_code): never
{
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    exit($exit_code);
}

function vg_event_consumer_6c_qa_make_private_directory(string $directory): void
{
    if (is_link($directory)) {
        throw new RuntimeException('QA directory must not be a symbolic link.');
    }
    if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
        throw new RuntimeException('Could not create the private QA directory.');
    }
    if (! chmod($directory, 0700)) {
        throw new RuntimeException('Could not set private QA directory permissions.');
    }
}

function vg_event_consumer_6c_qa_write_json(string $path, array $data): void
{
    vg_event_consumer_6c_qa_make_private_directory(dirname($path));
    if (is_link($path)) {
        throw new RuntimeException('QA manifest path must not be a symbolic link.');
    }

    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    $temporary_path = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
    $written = file_put_contents($temporary_path, $encoded, LOCK_EX);
    if ($written === false || ! chmod($temporary_path, 0600) || ! rename($temporary_path, $path)) {
        if (is_file($temporary_path)) {
            unlink($temporary_path);
        }
        throw new RuntimeException('Could not persist the private QA manifest.');
    }
}

function vg_event_consumer_6c_qa_read_json(string $path): array
{
    if (! is_file($path) || is_link($path)) {
        throw new RuntimeException('Required Task 6C QA snapshot or manifest is missing.');
    }
    $contents = file_get_contents($path);
    if (! is_string($contents)) {
        throw new RuntimeException('Could not read the Task 6C QA snapshot or manifest.');
    }
    $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($data)) {
        throw new RuntimeException('Task 6C QA snapshot or manifest has an invalid shape.');
    }
    return $data;
}

function vg_event_consumer_6c_qa_count(string $sql, array $arguments, string $description): int
{
    global $wpdb;
    $statement = $wpdb->prepare($sql, ...$arguments);
    $result = $wpdb->get_var($statement);
    if ((string) ($wpdb->last_error ?? '') !== '' || $result === null) {
        throw new RuntimeException('Could not read ' . $description . '.');
    }
    return (int) $result;
}

function vg_event_consumer_6c_qa_post_count(string $post_type): int
{
    global $wpdb;
    return vg_event_consumer_6c_qa_count(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
        [$post_type],
        'post count for ' . $post_type
    );
}

function vg_event_consumer_6c_qa_relationship_count(string $post_type, string $taxonomy): int
{
    global $wpdb;
    return vg_event_consumer_6c_qa_count(
        "SELECT COUNT(*) FROM {$wpdb->term_relationships} r"
        . " INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = r.term_taxonomy_id"
        . " INNER JOIN {$wpdb->posts} p ON p.ID = r.object_id"
        . ' WHERE p.post_type = %s AND tt.taxonomy = %s',
        [$post_type, $taxonomy],
        'relationships for ' . $post_type . ' in ' . $taxonomy
    );
}

function vg_event_consumer_6c_qa_normalize_ids(mixed $value): array
{
    if ($value === null || $value === false || $value === '') {
        return [];
    }
    $items = is_array($value) ? $value : [$value];
    $ids = [];
    foreach ($items as $item) {
        if (is_object($item) && isset($item->ID)) {
            $id = (int) $item->ID;
        } elseif (is_array($item) && isset($item['ID'])) {
            $id = (int) $item['ID'];
        } elseif (is_numeric($item)) {
            $id = (int) $item;
        } else {
            continue;
        }
        if ($id > 0 && ! in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }
    return $ids;
}

function vg_event_consumer_6c_qa_taxonomy_snapshot(int $post_id, string $taxonomy): array
{
    $terms = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'all']);
    if (is_wp_error($terms) || ! is_array($terms)) {
        throw new RuntimeException('Could not read ' . $taxonomy . ' relationships for post ' . $post_id . '.');
    }
    $snapshot = [];
    foreach ($terms as $term) {
        $snapshot[] = [
            'id' => (int) ($term->term_id ?? 0),
            'name' => (string) ($term->name ?? ''),
            'slug' => (string) ($term->slug ?? ''),
            'parent' => (int) ($term->parent ?? 0),
        ];
    }
    usort($snapshot, static fn (array $left, array $right): int => $left['id'] <=> $right['id']);
    return $snapshot;
}

function vg_event_consumer_6c_qa_event_snapshot(int $event_id): array
{
    $event = get_post($event_id);
    if (! ($event instanceof WP_Post) || $event->post_type !== 'tc_events') {
        throw new RuntimeException('The Task 6C QA Event is missing or changed type.');
    }
    $repository = new EventParticipationRepository();
    return [
        'id' => (int) $event->ID,
        'title' => (string) $event->post_title,
        'slug' => (string) $event->post_name,
        'status' => (string) $event->post_status,
        'modified' => (string) $event->post_modified,
        'event_medewerkers' => vg_event_consumer_6c_qa_normalize_ids(get_field('event_medewerkers', $event_id)),
        'event_category' => vg_event_consumer_6c_qa_taxonomy_snapshot($event_id, 'event_category'),
        'content_topic' => vg_event_consumer_6c_qa_taxonomy_snapshot($event_id, 'content_topic'),
        'canonical_participation_ids' => $repository->participations_for_event($event_id),
    ];
}

function vg_event_consumer_6c_qa_http_page(string $url, string $label): array
{
    $response = wp_remote_get($url, ['timeout' => 20, 'redirection' => 3]);
    if (is_wp_error($response)) {
        throw new RuntimeException('Could not fetch ' . $label . ': ' . $response->get_error_message());
    }
    $status = (int) wp_remote_retrieve_response_code($response);
    $body = (string) wp_remote_retrieve_body($response);
    if ($status !== 200) {
        throw new RuntimeException($label . ' returned HTTP ' . $status . '.');
    }
    return ['status' => $status, 'body' => $body];
}

function vg_event_consumer_6c_qa_assert_legacy_participant_modal_absent(string $html): void
{
    if (! class_exists(DOMDocument::class) || ! class_exists(DOMXPath::class)) {
        throw new RuntimeException('PHP DOM support is required to inspect legacy participant modal output.');
    }
    $previous_errors = libxml_use_internal_errors(true);
    try {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($document);
        foreach ($xpath->query('//*') ?: [] as $element) {
            foreach ($element->attributes ?? [] as $attribute) {
                foreach (['2c59af', '30255d', 'afc060', 'fdca1f'] as $marker) {
                    if (str_contains((string) $attribute->value, $marker)) {
                        throw new RuntimeException('Legacy Event participant modal element remains: ' . $marker . '.');
                    }
                }
            }
        }
        if (str_contains((string) $document->textContent, '{mb_people_title}')) {
            throw new RuntimeException('Legacy Event participant title token remains in rendered text.');
        }
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous_errors);
    }
}

function vg_event_consumer_6c_qa_verify_event_763_isolation(): array
{
    $event = vg_event_consumer_6c_qa_event_snapshot(763);
    if ($event['event_medewerkers'] !== [753, 757] || $event['canonical_participation_ids'] !== []) {
        throw new RuntimeException('Event 763 does not retain only its excluded legacy source edges.');
    }
    $page = vg_event_consumer_6c_qa_http_page(get_permalink(763), 'Event 763 isolation check');
    $section = vg_event_consumer_6c_qa_section_details($page['body']);
    if ($section !== []) {
        throw new RuntimeException('Event 763 renders a canonical participant section with zero canonical records.');
    }
    vg_event_consumer_6c_qa_assert_legacy_participant_modal_absent($page['body']);
    return [
        'event_medewerkers' => $event['event_medewerkers'],
        'canonical_participation_ids' => $event['canonical_participation_ids'],
        'frontend_participant_section_absent' => true,
        'legacy_fallback_detected' => false,
        'http_status' => $page['status'],
    ];
}

function vg_event_consumer_6c_qa_verify_rendered_cards(array $manifest): array
{
    $page = vg_event_consumer_6c_qa_http_page(
        get_permalink((int) $manifest['qa_event']['id']),
        'QA Event with temporary canonical participants'
    );
    $section = vg_event_consumer_6c_qa_section_details($page['body']);
    if ($section === []) {
        throw new RuntimeException('The QA Event participant section is missing while canonical records exist.');
    }
    vg_event_consumer_6c_qa_assert_legacy_participant_modal_absent($page['body']);

    $person_a = $manifest['persons']['a'];
    $person_b = $manifest['persons']['b'];
    $position_b = strpos($section['text'], (string) $person_b['title']);
    $position_a = strpos($section['text'], (string) $person_a['title']);
    if ($position_b === false || $position_a === false || $position_b >= $position_a) {
        throw new RuntimeException('Rendered participant order is not Person B followed by Person A.');
    }

    $professional_title = (string) $person_a['professional_title_expected'];
    if ($professional_title === '' || substr_count($section['text'], $professional_title) !== 1) {
        throw new RuntimeException('Person A professional title did not render exactly once.');
    }
    if (! in_array(get_field('person_professional_title', (int) $person_b['id']), [null, false, ''], true)) {
        throw new RuntimeException('Person B must keep its professional title empty.');
    }
    if (substr_count($section['text'], 'Task 6C QA professional title') !== 1) {
        throw new RuntimeException('The empty Person B card rendered a professional title.');
    }

    $url_a = get_permalink((int) $person_a['id']);
    $url_b = get_permalink((int) $person_b['id']);
    if (! in_array($url_a, $section['links'], true) || ! in_array($url_b, $section['links'], true)) {
        throw new RuntimeException('A participant card does not link to its canonical Person permalink.');
    }
    $legacy_links = array_values(array_filter(
        $section['links'],
        static fn (string $url): bool => str_contains($url, '/medewerkers/')
    ));
    if ($legacy_links !== []) {
        throw new RuntimeException('A canonical participant card links to a legacy Medewerker URL.');
    }

    if (in_array('#', $section['links'], true)) {
        throw new RuntimeException('A canonical participant card contains a placeholder social link.');
    }

    $person_a_http = vg_event_consumer_6c_qa_http_page($url_a, 'Person A canonical permalink');
    $person_b_http = vg_event_consumer_6c_qa_http_page($url_b, 'Person B canonical permalink');
    return [
        'event_http_status' => $page['status'],
        'rendered_order' => ['person_b', 'person_a'],
        'person_a_professional_title' => 'rendered',
        'person_b_professional_title' => 'empty',
        'person_a_url' => $url_a,
        'person_a_http_status' => $person_a_http['status'],
        'person_b_url' => $url_b,
        'person_b_http_status' => $person_b_http['status'],
        'legacy_medewerker_link_count' => count($legacy_links),
        'legacy_modal_absent' => true,
        'placeholder_social_link_count' => 0,
        'featured_images' => 'not-assigned',
    ];
}

function vg_event_consumer_6c_qa_capture_empty_event(): array
{
    $candidates = get_posts([
        'post_type' => 'tc_events',
        'post_status' => 'publish',
        'numberposts' => -1,
        'posts_per_page' => -1,
        'fields' => 'ids',
        'orderby' => 'ID',
        'order' => 'ASC',
        'exclude' => [763],
    ]);
    if (! is_array($candidates)) {
        throw new RuntimeException('Could not list published Event candidates.');
    }

    $selected = null;
    $repository = new EventParticipationRepository();
    foreach ($candidates as $candidate_id) {
        $candidate_id = (int) $candidate_id;
        if ($candidate_id <= 0 || $candidate_id === 763) {
            continue;
        }
        $event = get_post($candidate_id);
        if (! ($event instanceof WP_Post) || $event->post_type !== 'tc_events' || $event->post_status !== 'publish') {
            continue;
        }
        if (vg_event_consumer_6c_qa_normalize_ids(get_field('event_medewerkers', $candidate_id)) !== []) {
            continue;
        }
        if ($repository->participations_for_event($candidate_id) !== []) {
            continue;
        }
        $selected = vg_event_consumer_6c_qa_event_snapshot($candidate_id);
        break;
    }
    if (! is_array($selected)) {
        throw new RuntimeException('No published non-763 Event has empty legacy and canonical participant relationships.');
    }

    if ($selected['event_medewerkers'] !== [] || $selected['canonical_participation_ids'] !== []) {
        throw new RuntimeException('The selected QA Event is not empty.');
    }
    $selected['frontend_before'] = ['status' => 200, 'participant_section_absent' => true];
    $qa_page = vg_event_consumer_6c_qa_http_page(get_permalink((int) $selected['id']), 'empty-state QA Event');
    $selected['frontend_before']['status'] = $qa_page['status'];
    $selected['frontend_before']['participant_section_absent'] = vg_event_consumer_6c_qa_section_details($qa_page['body']) === [];
    if (! $selected['frontend_before']['participant_section_absent']) {
        throw new RuntimeException('The empty-state QA Event still renders the participant section.');
    }
    vg_event_consumer_6c_qa_assert_legacy_participant_modal_absent($qa_page['body']);

    $selected['event_763_isolation_before'] = [
        ...vg_event_consumer_6c_qa_verify_event_763_isolation(),
    ];

    vg_event_consumer_6c_qa_write_json(VG_EVENT_CONSUMER_6C_QA_EVENT_FILE, $selected);
    return $selected;
}

function vg_event_consumer_6c_qa_load_manifest(string $run_token): array
{
    $path = VG_EVENT_CONSUMER_6C_QA_DIR . '/' . $run_token . '.json';
    $manifest = vg_event_consumer_6c_qa_read_json($path);
    if (($manifest['run_token'] ?? null) !== $run_token) {
        throw new RuntimeException('QA manifest run token does not match the requested token.');
    }
    return $manifest;
}

function vg_event_consumer_6c_qa_manifest_path(string $run_token): string
{
    if (! preg_match('/^[a-z0-9][a-z0-9-]{7,47}$/', $run_token)) {
        throw new RuntimeException('Run token must be 8 to 48 lowercase letters, digits, or hyphens.');
    }
    return VG_EVENT_CONSUMER_6C_QA_DIR . '/' . $run_token . '.json';
}

function vg_event_consumer_6c_qa_preflight(array $event_snapshot): array
{
    if (! class_exists(EventParticipationService::class) || ! class_exists(EventParticipationRepository::class)) {
        throw new RuntimeException('Platform Core Event Participation service or repository is not loaded.');
    }
    vg_event_consumer_6c_qa_assert_post_types_registered([
        'person',
        EventParticipationRepository::POST_TYPE,
    ]);
    $post_counts = [];
    foreach (['person', EventParticipationRepository::POST_TYPE] as $post_type) {
        $post_counts[$post_type] = vg_event_consumer_6c_qa_post_count($post_type);
        if ($post_counts[$post_type] !== 0) {
            throw new RuntimeException('Persistent ' . $post_type . ' count must be zero before QA.');
        }
    }

    $role_terms = get_terms(['taxonomy' => 'event_role', 'hide_empty' => false]);
    $topic_terms = get_terms(['taxonomy' => 'content_topic', 'hide_empty' => false]);
    if (is_wp_error($role_terms) || ! is_array($role_terms) || count($role_terms) !== 4) {
        throw new RuntimeException('The event_role baseline must contain exactly four terms before QA.');
    }
    if (is_wp_error($topic_terms) || ! is_array($topic_terms) || count($topic_terms) !== 55) {
        throw new RuntimeException('The content_topic baseline must contain exactly 55 terms before QA.');
    }
    $topic_roots = count(array_filter($topic_terms, static fn ($term): bool => (int) ($term->parent ?? 0) === 0));
    if ($topic_roots !== 7) {
        throw new RuntimeException('The content_topic baseline must contain exactly seven roots before QA.');
    }

    $relationship_counts = [
        'person_type' => vg_event_consumer_6c_qa_relationship_count('person', 'person_type'),
        'person_content_topic' => vg_event_consumer_6c_qa_relationship_count('person', 'content_topic'),
        'event_role' => vg_event_consumer_6c_qa_relationship_count(EventParticipationRepository::POST_TYPE, 'event_role'),
    ];
    if (array_filter($relationship_counts, static fn (int $count): bool => $count !== 0) !== []) {
        throw new RuntimeException('Canonical Person or Event Role relationships must be zero before QA.');
    }

    if ((int) ($event_snapshot['id'] ?? 0) === 763 || ($event_snapshot['status'] ?? '') !== 'publish') {
        throw new RuntimeException('The QA Event must be a different published Event.');
    }
    $current = vg_event_consumer_6c_qa_event_snapshot((int) $event_snapshot['id']);
    foreach (['id', 'title', 'slug', 'status', 'modified', 'event_medewerkers', 'event_category', 'content_topic', 'canonical_participation_ids'] as $key) {
        if (($current[$key] ?? null) !== ($event_snapshot[$key] ?? null)) {
            throw new RuntimeException('The QA Event changed after the Task 9 snapshot: ' . $key . '.');
        }
    }
    if ($current['event_medewerkers'] !== [] || $current['canonical_participation_ids'] !== []) {
        throw new RuntimeException('The QA Event must have no legacy or canonical participants.');
    }

    return [
        'post_counts' => $post_counts,
        'event_role_terms' => count($role_terms),
        'content_topic_terms' => count($topic_terms),
        'content_topic_roots' => $topic_roots,
        'relationships' => $relationship_counts,
    ];
}

function vg_event_consumer_6c_qa_create_person(
    array &$manifest,
    string $key,
    string $professional_title
): int {
    $owner = (string) $manifest['owner'];
    $title = $owner . ' Person ' . strtoupper($key);
    $slug = sanitize_title('vg-task-6c-' . $manifest['run_token'] . '-person-' . $key);
    $person_id = wp_insert_post([
        'post_type' => 'person',
        'post_status' => 'publish',
        'post_title' => $title,
        'post_name' => $slug,
    ], true);
    if (is_wp_error($person_id)) {
        throw new RuntimeException('Could not create QA Person ' . strtoupper($key) . ': ' . $person_id->get_error_message());
    }
    $person_id = (int) $person_id;
    $manifest['mutation_started'] = true;
    vg_event_consumer_6c_qa_track_person_id($manifest['owned_persons'], $person_id, $title, $slug);
    $manifest['persons'][$key] = [
        'id' => $person_id,
        'title' => $title,
        'slug' => $slug,
        'professional_title_expected' => $professional_title,
        'featured_image_id' => 0,
    ];
    vg_event_consumer_6c_qa_write_json(vg_event_consumer_6c_qa_manifest_path($manifest['run_token']), $manifest);

    vg_event_consumer_6c_qa_mark_person_owner($manifest['owned_persons'], $person_id, $owner);
    if ($professional_title !== '') {
        if (update_field('person_professional_title', $professional_title, $person_id) === false) {
            throw new RuntimeException('Could not write the QA Person professional title.');
        }
    }

    $actual_title = get_field('person_professional_title', $person_id);
    if ($professional_title !== '' && (string) $actual_title !== $professional_title) {
        throw new RuntimeException('The QA Person professional title did not save as expected.');
    }
    if ($professional_title === '' && ! in_array($actual_title, [null, false, ''], true)) {
        throw new RuntimeException('The QA Person with an empty professional title received a value.');
    }

    return $person_id;
}

function vg_event_consumer_6c_qa_create_participation(
    array &$manifest,
    string $person_key,
    int $display_order
): int {
    $event_id = (int) $manifest['qa_event']['id'];
    $person_id = (int) $manifest['persons'][$person_key]['id'];
    $service = new EventParticipationService();
    $participation_id = $service->create($event_id, $person_id, [], $display_order, 'publish');
    if (is_wp_error($participation_id)) {
        throw new RuntimeException('Could not create canonical QA participation: ' . $participation_id->get_error_message());
    }
    $participation_id = (int) $participation_id;
    $manifest['mutation_started'] = true;
    vg_event_consumer_6c_qa_track_participation_id(
        $manifest['owned_participations'],
        $participation_id,
        $event_id,
        $person_id
    );
    $manifest['participations'][$person_key] = [
        'id' => $participation_id,
        'person_id' => $person_id,
        'display_order' => $display_order,
    ];
    vg_event_consumer_6c_qa_write_json(vg_event_consumer_6c_qa_manifest_path($manifest['run_token']), $manifest);
    vg_event_consumer_6c_qa_mark_participation_owner($manifest['owned_participations'], $participation_id, $manifest['owner']);
    return $participation_id;
}

function vg_event_consumer_6c_qa_phase_setup(string $run_token): never
{
    $manifest_path = vg_event_consumer_6c_qa_manifest_path($run_token);
    if (file_exists($manifest_path)) {
        vg_event_consumer_6c_qa_emit([
            'result' => 'FAIL', 'phase' => 'setup', 'mutation_started' => false,
            'failures' => [['message' => 'A manifest already exists for this run token; duplicate setup is refused.']],
        ], 1);
    }

    $manifest = null;
    $failure = null;
    $cleanup_failures = [];
    try {
        $event_snapshot = vg_event_consumer_6c_qa_read_json(VG_EVENT_CONSUMER_6C_QA_EVENT_FILE);
        $persistent_baseline = vg_event_consumer_6c_qa_preflight($event_snapshot);
        vg_runtime_mutation_assert_session('vgnova-local-10039');
        $manifest = [
            'task' => '6C',
            'run_token' => $run_token,
            'owner' => '[QA][AUTOMATED] Task 6C ' . $run_token,
            'state' => 'setup_started',
            'mutation_started' => false,
            'qa_event' => $event_snapshot,
            'persistent_baseline' => $persistent_baseline,
            'persons' => [],
            'participations' => [],
            'owned_persons' => [],
            'owned_participations' => [],
            'frontend_order_expected' => ['person_b', 'person_a'],
            'images_assigned' => false,
        ];
        vg_event_consumer_6c_qa_write_json($manifest_path, $manifest);

        vg_event_consumer_6c_qa_create_person($manifest, 'a', 'Task 6C QA professional title');
        vg_event_consumer_6c_qa_create_person($manifest, 'b', '');
        vg_event_consumer_6c_qa_create_participation($manifest, 'a', 20);
        vg_event_consumer_6c_qa_create_participation($manifest, 'b', 10);
        $repository = new EventParticipationRepository();
        $repository_order = $repository->participations_for_event((int) $manifest['qa_event']['id']);
        $expected_order = [
            (int) $manifest['participations']['b']['id'],
            (int) $manifest['participations']['a']['id'],
        ];
        if ($repository_order !== $expected_order) {
            throw new RuntimeException('Repository result does not match the requested 10/20 display order.');
        }
        if (
            vg_event_consumer_6c_qa_post_count('person') !== 2
            || vg_event_consumer_6c_qa_post_count(EventParticipationRepository::POST_TYPE) !== 2
        ) {
            throw new RuntimeException('Task 6C QA must create exactly two Persons and two participations.');
        }
        $manifest['repository_order'] = $repository_order;
        $manifest['frontend_qa'] = vg_event_consumer_6c_qa_verify_rendered_cards($manifest);
        $manifest['event_763_isolation_during'] = vg_event_consumer_6c_qa_verify_event_763_isolation();
        $manifest['state'] = 'setup_complete';
        vg_event_consumer_6c_qa_write_json($manifest_path, $manifest);
    } catch (Throwable $error) {
        $failure = $error->getMessage();
    } finally {
        if ($failure !== null && is_array($manifest)) {
            $cleanup_failures = vg_event_consumer_6c_qa_cleanup_owned_records(
                $manifest['owned_participations'] ?? [],
                $manifest['owned_persons'] ?? [],
                (string) ($manifest['owner'] ?? '')
            );
            $manifest['state'] = $cleanup_failures === [] ? 'setup_failed_cleaned' : 'setup_failed_cleanup_failed';
            $manifest['setup_failure'] = $failure;
            $manifest['cleanup_failures'] = $cleanup_failures;
            try {
                vg_event_consumer_6c_qa_write_json($manifest_path, $manifest);
            } catch (Throwable $write_error) {
                $cleanup_failures[] = ['message' => 'Could not persist failed setup cleanup status: ' . $write_error->getMessage()];
            }
        }
    }

    if ($failure !== null) {
        vg_event_consumer_6c_qa_emit([
            'result' => 'FAIL', 'phase' => 'setup', 'mutation_started' => (bool) ($manifest['mutation_started'] ?? false),
            'failures' => [['message' => $failure], ...$cleanup_failures],
            'manifest' => $manifest_path,
            'owned_person_ids' => array_keys($manifest['owned_persons'] ?? []),
            'owned_participation_ids' => array_keys($manifest['owned_participations'] ?? []),
        ], 1);
    }

    vg_event_consumer_6c_qa_emit([
        'result' => 'PASS', 'phase' => 'setup', 'mutation_started' => true,
        'qa_event_id' => $manifest['qa_event']['id'],
        'person_ids' => array_column($manifest['persons'], 'id'),
        'participation_ids' => array_column($manifest['participations'], 'id'),
        'frontend_order_expected' => $manifest['frontend_order_expected'],
        'manifest' => $manifest_path,
    ], 0);
}

function vg_event_consumer_6c_qa_phase_cleanup(string $run_token): never
{
    $path = vg_event_consumer_6c_qa_manifest_path($run_token);
    try {
        $manifest = vg_event_consumer_6c_qa_load_manifest($run_token);
        vg_runtime_mutation_assert_session('vgnova-local-10039');
        $failures = vg_event_consumer_6c_qa_cleanup_owned_records(
            $manifest['owned_participations'] ?? [],
            $manifest['owned_persons'] ?? [],
            (string) ($manifest['owner'] ?? '')
        );
        $manifest['cleanup_failures'] = $failures;
        $manifest['state'] = $failures === [] ? 'cleanup_complete' : 'cleanup_failed';
        vg_event_consumer_6c_qa_write_json($path, $manifest);
        vg_event_consumer_6c_qa_emit([
            'result' => $failures === [] ? 'PASS' : 'FAIL',
            'phase' => 'cleanup',
            'manifest' => $path,
            'owned_person_ids' => array_keys($manifest['owned_persons'] ?? []),
            'owned_participation_ids' => array_keys($manifest['owned_participations'] ?? []),
            'failures' => $failures,
        ], $failures === [] ? 0 : 1);
    } catch (Throwable $error) {
        vg_event_consumer_6c_qa_emit(['result' => 'FAIL', 'phase' => 'cleanup', 'failures' => [['message' => $error->getMessage()]]], 1);
    }
}

function vg_event_consumer_6c_qa_phase_verify_clean(string $run_token): never
{
    try {
        $manifest = vg_event_consumer_6c_qa_load_manifest($run_token);
        if (($manifest['state'] ?? '') !== 'cleanup_complete') {
            throw new RuntimeException('QA cleanup has not completed successfully.');
        }

        $counts = [
            'person' => vg_event_consumer_6c_qa_post_count('person'),
            'event_participation' => vg_event_consumer_6c_qa_post_count(EventParticipationRepository::POST_TYPE),
            'person_type_relationships' => vg_event_consumer_6c_qa_relationship_count('person', 'person_type'),
            'person_content_topic_relationships' => vg_event_consumer_6c_qa_relationship_count('person', 'content_topic'),
            'event_role_relationships' => vg_event_consumer_6c_qa_relationship_count(EventParticipationRepository::POST_TYPE, 'event_role'),
        ];
        if (in_array(false, $counts, true) || array_filter($counts, static fn ($count): bool => $count !== 0) !== []) {
            throw new RuntimeException('Persistent canonical records or relationships remain after cleanup.');
        }

        $roles = get_terms(['taxonomy' => 'event_role', 'hide_empty' => false]);
        $topics = get_terms(['taxonomy' => 'content_topic', 'hide_empty' => false]);
        if (is_wp_error($roles) || ! is_array($roles) || count($roles) !== 4) {
            throw new RuntimeException('The event_role vocabulary changed during QA.');
        }
        if (is_wp_error($topics) || ! is_array($topics) || count($topics) !== 55) {
            throw new RuntimeException('The content_topic vocabulary changed during QA.');
        }
        $topic_roots = count(array_filter($topics, static fn ($term): bool => (int) ($term->parent ?? 0) === 0));
        if ($topic_roots !== 7) {
            throw new RuntimeException('The content_topic root count changed during QA.');
        }

        $event_before = $manifest['qa_event'];
        $event_after = vg_event_consumer_6c_qa_event_snapshot((int) $event_before['id']);
        foreach (['id', 'title', 'slug', 'status', 'modified', 'event_medewerkers', 'event_category', 'content_topic', 'canonical_participation_ids'] as $key) {
            if (($event_after[$key] ?? null) !== ($event_before[$key] ?? null)) {
                throw new RuntimeException('QA Event changed during QA: ' . $key . '.');
            }
        }
        if ($event_after['canonical_participation_ids'] !== []) {
            throw new RuntimeException('Canonical participation remains on the QA Event.');
        }

        $frontend = vg_event_consumer_6c_qa_http_page(get_permalink((int) $event_before['id']), 'QA Event after cleanup');
        if (vg_event_consumer_6c_qa_section_details($frontend['body']) !== []) {
            throw new RuntimeException('The QA Event participant section remains visible after cleanup.');
        }
        $event_763_isolation = vg_event_consumer_6c_qa_verify_event_763_isolation();
        $counts['event_role_terms'] = count($roles);
        $counts['content_topic_terms'] = count($topics);
        $counts['content_topic_roots'] = $topic_roots;
        vg_event_consumer_6c_qa_emit([
            'result' => 'PASS',
            'phase' => 'verify-clean',
            'counts' => $counts,
            'qa_event_unchanged' => true,
            'participant_section_hidden' => true,
            'event_763_isolation' => $event_763_isolation,
            'cleanup_failures' => [],
        ], 0);
    } catch (Throwable $error) {
        vg_event_consumer_6c_qa_emit([
            'result' => 'FAIL', 'phase' => 'verify-clean', 'failures' => [['message' => $error->getMessage()]],
        ], 1);
    }
}

$raw_arguments = isset($args) && is_array($args) ? $args : [];
$raw_arguments = array_merge($raw_arguments, is_array($_SERVER['argv'] ?? null) ? $_SERVER['argv'] : []);
$options = [];
foreach ($raw_arguments as $argument) {
    if (is_string($argument) && preg_match('/^--([a-z-]+)=(.*)$/', $argument, $matches)) {
        $options[$matches[1]] = $matches[2];
    } elseif (is_string($argument) && preg_match('/^(phase|run-token)=(.*)$/', $argument, $matches)) {
        // WP-CLI eval-file accepts custom script arguments as positionals, not unknown flags.
        $options[$matches[1]] = $matches[2];
    }
}

$phase = (string) ($options['phase'] ?? '');
if ($phase === 'snapshot-event') {
    try {
        $snapshot = vg_event_consumer_6c_qa_capture_empty_event();
        vg_event_consumer_6c_qa_emit([
            'result' => 'PASS', 'phase' => $phase, 'qa_event' => $snapshot,
            'snapshot_file' => VG_EVENT_CONSUMER_6C_QA_EVENT_FILE,
            'event_763_isolation' => $snapshot['event_763_isolation_before'],
        ], 0);
    } catch (Throwable $error) {
        vg_event_consumer_6c_qa_emit(['result' => 'FAIL', 'phase' => $phase, 'failures' => [['message' => $error->getMessage()]]], 1);
    }
}

if (! defined('WP_CLI') || ! WP_CLI) {
    vg_event_consumer_6c_qa_emit(['result' => 'FAIL', 'phase' => $phase, 'failures' => [['message' => 'Run Task 6C QA with WP-CLI.']]], 1);
}
$run_token = (string) ($options['run-token'] ?? '');
if ($phase === 'setup') {
    vg_event_consumer_6c_qa_phase_setup($run_token);
}
if ($phase === 'cleanup') {
    vg_event_consumer_6c_qa_phase_cleanup($run_token);
}
if ($phase === 'verify-clean') {
    vg_event_consumer_6c_qa_phase_verify_clean($run_token);
}
vg_event_consumer_6c_qa_emit(['result' => 'FAIL', 'phase' => $phase, 'failures' => [['message' => 'Unknown Task 6C QA phase.']]], 1);
