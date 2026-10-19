<?php
declare(strict_types=1);

final class WP_Post
{
    public function __construct(
        public int $ID,
        public string $post_type,
        public string $post_title = '',
        public string $post_name = '',
        public string $post_status = 'publish'
    ) {
    }
}

final class WP_Error
{
    public function __construct(private string $message = 'Test error')
    {
    }

    public function get_error_message(): string
    {
        return $this->message;
    }
}

$GLOBALS['vg6c_posts'] = [];
$GLOBALS['vg6c_meta'] = [];
$GLOBALS['vg6c_fields'] = [];
$GLOBALS['vg6c_relationships'] = [];
$GLOBALS['vg6c_delete_log'] = [];
$GLOBALS['vg6c_marker_write_hook'] = null;
$GLOBALS['vg6c_failed_marker_ids'] = [];
$GLOBALS['vg6c_failed_delete_ids'] = [];
$GLOBALS['vg6c_failed_term_ids'] = [];
$GLOBALS['vg6c_registered_post_types'] = ['person' => true, 'event_participation' => true];

function get_post(int $post_id): ?WP_Post
{
    return $GLOBALS['vg6c_posts'][$post_id] ?? null;
}

function post_type_exists(string $post_type): bool
{
    return (bool) ($GLOBALS['vg6c_registered_post_types'][$post_type] ?? false);
}

function get_field(string $field, int $post_id): mixed
{
    return $GLOBALS['vg6c_fields'][$post_id][$field] ?? null;
}

function get_post_meta(int $post_id, string $key = '', bool $single = false): mixed
{
    if ($key === '') {
        return array_map(static fn (mixed $value): array => [$value], $GLOBALS['vg6c_meta'][$post_id] ?? []);
    }

    $value = $GLOBALS['vg6c_meta'][$post_id][$key] ?? '';
    return $single ? $value : ($value === '' ? [] : [$value]);
}

function update_post_meta(int $post_id, string $key, mixed $value): bool
{
    if (is_callable($GLOBALS['vg6c_marker_write_hook'])) {
        ($GLOBALS['vg6c_marker_write_hook'])($post_id, $key);
    }
    if (in_array($post_id, $GLOBALS['vg6c_failed_marker_ids'], true)) {
        return false;
    }
    $GLOBALS['vg6c_meta'][$post_id][$key] = $value;
    return true;
}

function wp_delete_post(int $post_id, bool $force_delete = false): WP_Post|false
{
    $GLOBALS['vg6c_delete_log'][] = ['id' => $post_id, 'force' => $force_delete];
    if (in_array($post_id, $GLOBALS['vg6c_failed_delete_ids'], true)) {
        return false;
    }
    $post = $GLOBALS['vg6c_posts'][$post_id] ?? false;
    if (! $post) {
        return false;
    }
    if ($force_delete) {
        unset(
            $GLOBALS['vg6c_posts'][$post_id],
            $GLOBALS['vg6c_meta'][$post_id],
            $GLOBALS['vg6c_fields'][$post_id],
            $GLOBALS['vg6c_relationships'][$post_id]
        );
    } else {
        $post->post_status = 'trash';
    }
    return $post;
}

function get_posts(array $args = []): array
{
    $results = [];
    foreach ($GLOBALS['vg6c_posts'] as $post_id => $post) {
        if (($post->post_type ?? '') !== ($args['post_type'] ?? 'post')) {
            continue;
        }
        if (isset($args['post_status']) && ! in_array($post->post_status, (array) $args['post_status'], true)) {
            continue;
        }
        if (isset($args['meta_key'])) {
            $value = $GLOBALS['vg6c_fields'][$post_id][(string) $args['meta_key']] ?? null;
            if ((string) $value !== (string) ($args['meta_value'] ?? '')) {
                continue;
            }
        }
        $results[] = (int) $post_id;
    }
    return $results;
}

function wp_get_object_terms(int $post_id, array|string $taxonomies, array $args = []): array|WP_Error
{
    if (in_array($post_id, $GLOBALS['vg6c_failed_term_ids'], true)) {
        return new WP_Error('Could not read terms.');
    }
    $terms = [];
    foreach ((array) $taxonomies as $taxonomy) {
        foreach ($GLOBALS['vg6c_relationships'][$post_id][$taxonomy] ?? [] as $term_id) {
            $terms[] = $term_id;
        }
    }
    return array_values(array_unique($terms));
}

function is_wp_error(mixed $value): bool
{
    return $value instanceof WP_Error;
}

function vg6c_test_check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function vg6c_test_reset(): void
{
    $GLOBALS['vg6c_posts'] = [];
    $GLOBALS['vg6c_meta'] = [];
    $GLOBALS['vg6c_fields'] = [];
    $GLOBALS['vg6c_relationships'] = [];
    $GLOBALS['vg6c_delete_log'] = [];
    $GLOBALS['vg6c_marker_write_hook'] = null;
    $GLOBALS['vg6c_failed_marker_ids'] = [];
    $GLOBALS['vg6c_failed_delete_ids'] = [];
    $GLOBALS['vg6c_failed_term_ids'] = [];
    $GLOBALS['vg6c_registered_post_types'] = ['person' => true, 'event_participation' => true];
}

function vg6c_test_person(int $id, string $title, string $slug, string $owner = ''): void
{
    $GLOBALS['vg6c_posts'][$id] = new WP_Post($id, 'person', $title, $slug);
    if ($owner !== '') {
        $GLOBALS['vg6c_meta'][$id]['_vg_task_6c_qa_owner'] = $owner;
    }
    $GLOBALS['vg6c_relationships'][$id] = ['person_type' => [31], 'content_topic' => [41]];
}

function vg6c_test_service_create_participation(int $id, int $event_id, int $person_id): int
{
    $GLOBALS['vg6c_posts'][$id] = new WP_Post($id, 'event_participation', 'QA participation');
    $GLOBALS['vg6c_fields'][$id] = [
        'event_participation_event' => $event_id,
        'event_participation_person' => $person_id,
    ];
    $GLOBALS['vg6c_relationships'][$id] = ['event_role' => [51]];
    return $id;
}

function vg6c_test_require_helper(): void
{
    foreach ([
        'vg_event_consumer_6c_qa_track_person_id',
        'vg_event_consumer_6c_qa_mark_person_owner',
        'vg_event_consumer_6c_qa_track_participation_id',
        'vg_event_consumer_6c_qa_mark_participation_owner',
        'vg_event_consumer_6c_qa_assert_post_types_registered',
        'vg_event_consumer_6c_qa_cleanup_owned_records',
    ] as $function) {
        if (! function_exists($function)) {
            throw new RuntimeException('Task 6C QA ownership helper is not implemented.');
        }
    }
}

$ownership_file = dirname(__DIR__) . '/event-consumer-6c-qa-ownership.php';
if (is_file($ownership_file)) {
    require_once $ownership_file;
}

$cases = [
    'participant link extraction ignores Bricks query-trail anchors but keeps placeholder links' => static function (): void {
        $rendering_file = dirname(__DIR__) . '/event-consumer-6c-qa-rendering.php';
        if (is_file($rendering_file)) {
            require_once $rendering_file;
        }
        vg6c_test_check(
            function_exists('vg_event_consumer_6c_qa_section_details'),
            'The Task 6C rendered-section parser is not implemented.'
        );
        $details = vg_event_consumer_6c_qa_section_details(
            '<section class="vg-event-participants-section">'
            . '<a href="/mense/canonical-person/">Person</a>'
            . '<a class="brx-query-trail" href="#" onclick="return false;">Loop trail</a>'
            . '<a class="person-card__social" href="#">Social placeholder</a>'
            . '</section>'
        );
        vg6c_test_check(
            $details['links'] === ['/mense/canonical-person/', '#'],
            'The rendered-section parser counted a Bricks loop trail or hid a participant placeholder link.'
        );
    },
    'preflight fails when a canonical post type is not registered' => static function (): void {
        vg6c_test_require_helper();
        vg6c_test_reset();
        $GLOBALS['vg6c_registered_post_types']['event_participation'] = false;
        $failed = false;
        try {
            vg_event_consumer_6c_qa_assert_post_types_registered(['person', 'event_participation']);
        } catch (RuntimeException $error) {
            $failed = str_contains($error->getMessage(), 'event_participation');
        }
        vg6c_test_check($failed, 'QA preflight did not stop for an unregistered canonical post type.');
    },
    'preflight accepts both registered canonical post types' => static function (): void {
        vg6c_test_require_helper();
        vg6c_test_reset();
        vg_event_consumer_6c_qa_assert_post_types_registered(['person', 'event_participation']);
    },
    'Person ID is tracked before its ownership marker is written' => static function (): void {
        vg6c_test_require_helper();
        vg6c_test_reset();
        $owner = '[QA][AUTOMATED] Task 6C run-person-before-marker';
        $title = $owner . ' Person A';
        $slug = 'vg-task-6c-person-before-marker-a';
        vg6c_test_person(501, $title, $slug);
        $owned = [];
        vg_event_consumer_6c_qa_track_person_id($owned, 501, $title, $slug);
        $GLOBALS['vg6c_marker_write_hook'] = static function (int $id) use (&$owned): void {
            vg6c_test_check(isset($owned[$id]), 'The Person ID was not tracked before the marker write.');
        };
        vg_event_consumer_6c_qa_mark_person_owner($owned, 501, $owner);
        vg6c_test_check(get_post_meta(501, '_vg_task_6c_qa_owner', true) === $owner, 'The Person marker was not written.');
    },
    'participation ID is tracked immediately after service creation' => static function (): void {
        vg6c_test_require_helper();
        vg6c_test_reset();
        $owner = '[QA][AUTOMATED] Task 6C run-participation-before-marker';
        vg6c_test_person(502, $owner . ' Person A', 'vg-task-6c-participation-before-marker-a', $owner);
        $person_ids = [502 => ['title' => $owner . ' Person A', 'slug' => 'vg-task-6c-participation-before-marker-a']];
        $id = vg6c_test_service_create_participation(802, 103, 502);
        $owned = [];
        vg_event_consumer_6c_qa_track_participation_id($owned, $id, 103, 502);
        $GLOBALS['vg6c_marker_write_hook'] = static function (int $write_id) use (&$owned): void {
            vg6c_test_check(isset($owned[$write_id]), 'The service-created ID was not tracked before the marker write.');
        };
        vg_event_consumer_6c_qa_mark_participation_owner($owned, $id, $owner);
        vg6c_test_check(isset($person_ids[502]) && isset($owned[802]), 'The test did not retain the returned service ID.');
    },
    'wrong post type refuses hard deletion' => static function (): void {
        vg6c_test_require_helper();
        vg6c_test_reset();
        $GLOBALS['vg6c_posts'][803] = new WP_Post(803, 'page', 'Unowned page', 'unowned-page');
        $failures = vg_event_consumer_6c_qa_cleanup_owned_records(
            [803 => ['event_id' => 104, 'person_id' => 503]],
            [],
            '[QA][AUTOMATED] Task 6C wrong-type'
        );
        vg6c_test_check($failures !== [], 'Cleanup did not report the changed post type.');
        vg6c_test_check($GLOBALS['vg6c_delete_log'] === [], 'Cleanup hard-deleted a post with the wrong type.');
    },
    'changed participation endpoints refuse deletion' => static function (): void {
        vg6c_test_require_helper();
        vg6c_test_reset();
        vg6c_test_person(504, 'Owned Person', 'vg-task-6c-endpoint-person', '[QA][AUTOMATED] Task 6C endpoint-change');
        vg6c_test_service_create_participation(804, 105, 504);
        $GLOBALS['vg6c_fields'][804]['event_participation_person'] = 505;
        $failures = vg_event_consumer_6c_qa_cleanup_owned_records(
            [804 => ['event_id' => 105, 'person_id' => 504]],
            [504 => ['title' => 'Owned Person', 'slug' => 'vg-task-6c-endpoint-person']],
            '[QA][AUTOMATED] Task 6C endpoint-change'
        );
        vg6c_test_check(count($failures) >= 2, 'Cleanup did not report the endpoint change and retained Person.');
        vg6c_test_check(get_post(804) instanceof WP_Post, 'Cleanup deleted a participation with changed endpoints.');
        vg6c_test_check(get_post(504) instanceof WP_Post, 'Cleanup deleted the Person whose participation endpoint changed.');
    },
    'conflicting ownership marker refuses deletion' => static function (): void {
        vg6c_test_require_helper();
        vg6c_test_reset();
        $owner = '[QA][AUTOMATED] Task 6C expected-owner';
        vg6c_test_person(505, 'Owned Person', 'vg-task-6c-conflict-person', $owner);
        vg6c_test_service_create_participation(805, 106, 505);
        $GLOBALS['vg6c_meta'][805]['_vg_task_6c_qa_owner'] = '[QA][AUTOMATED] Other run';
        $failures = vg_event_consumer_6c_qa_cleanup_owned_records(
            [805 => ['event_id' => 106, 'person_id' => 505]],
            [505 => ['title' => 'Owned Person', 'slug' => 'vg-task-6c-conflict-person']],
            $owner
        );
        vg6c_test_check($failures !== [], 'Cleanup did not reject a conflicting ownership marker.');
        vg6c_test_check(get_post(805) instanceof WP_Post, 'Cleanup deleted a participation with a conflicting owner marker.');
    },
    'participations are deleted before owned Persons' => static function (): void {
        vg6c_test_require_helper();
        vg6c_test_reset();
        $owner = '[QA][AUTOMATED] Task 6C delete-order';
        $title = $owner . ' Person A';
        $slug = 'vg-task-6c-delete-order-a';
        vg6c_test_person(506, $title, $slug);
        $persons = [];
        vg_event_consumer_6c_qa_track_person_id($persons, 506, $title, $slug);
        vg_event_consumer_6c_qa_mark_person_owner($persons, 506, $owner);
        vg6c_test_service_create_participation(806, 107, 506);
        $participations = [];
        vg_event_consumer_6c_qa_track_participation_id($participations, 806, 107, 506);
        vg_event_consumer_6c_qa_mark_participation_owner($participations, 806, $owner);
        $failures = vg_event_consumer_6c_qa_cleanup_owned_records($participations, $persons, $owner);
        vg6c_test_check($failures === [], 'Cleanup reported a failure for owned records.');
        vg6c_test_check(
            array_column($GLOBALS['vg6c_delete_log'], 'id') === [806, 506],
            'Cleanup did not delete the participation before the Person.'
        );
        vg6c_test_check(array_column($GLOBALS['vg6c_delete_log'], 'force') === [true, true], 'Cleanup did not hard-delete both owned records.');
    },
    'cleanup continues across independent records after one failure' => static function (): void {
        vg6c_test_require_helper();
        vg6c_test_reset();
        $owner = '[QA][AUTOMATED] Task 6C continue';
        $title_a = $owner . ' Person A';
        $slug_a = 'vg-task-6c-continue-a';
        $title_b = $owner . ' Person B';
        $slug_b = 'vg-task-6c-continue-b';
        vg6c_test_person(507, $title_a, $slug_a, $owner);
        vg6c_test_person(508, $title_b, $slug_b, $owner);
        vg6c_test_service_create_participation(807, 108, 507);
        vg6c_test_service_create_participation(808, 108, 508);
        $GLOBALS['vg6c_fields'][807]['event_participation_event'] = 999;
        $failures = vg_event_consumer_6c_qa_cleanup_owned_records(
            [807 => ['event_id' => 108, 'person_id' => 507], 808 => ['event_id' => 108, 'person_id' => 508]],
            [507 => ['title' => $title_a, 'slug' => $slug_a], 508 => ['title' => $title_b, 'slug' => $slug_b]],
            $owner
        );
        vg6c_test_check($failures !== [], 'Cleanup did not report the changed independent participation.');
        vg6c_test_check(get_post(807) instanceof WP_Post && get_post(507) instanceof WP_Post, 'Cleanup removed the blocked Person or its changed participation.');
        vg6c_test_check(get_post(808) === null && get_post(508) === null, 'Cleanup stopped before cleaning independent owned records.');
    },
    'Person with a remaining participation is retained' => static function (): void {
        vg6c_test_require_helper();
        vg6c_test_reset();
        $owner = '[QA][AUTOMATED] Task 6C remaining';
        $title = $owner . ' Person A';
        $slug = 'vg-task-6c-remaining-a';
        vg6c_test_person(509, $title, $slug, $owner);
        vg6c_test_service_create_participation(809, 109, 509);
        $failures = vg_event_consumer_6c_qa_cleanup_owned_records(
            [],
            [509 => ['title' => $title, 'slug' => $slug]],
            $owner
        );
        vg6c_test_check($failures !== [], 'Cleanup did not retain a Person referenced by a participation.');
        vg6c_test_check(get_post(509) instanceof WP_Post, 'Cleanup deleted a Person that still had an Event Participation.');
        vg6c_test_check(get_post(809) instanceof WP_Post, 'Cleanup deleted an untracked participation.');
    },
    'successful cleanup verifies posts, metadata, and taxonomy relationships are gone' => static function (): void {
        vg6c_test_require_helper();
        vg6c_test_reset();
        $owner = '[QA][AUTOMATED] Task 6C complete-cleanup';
        $title = $owner . ' Person A';
        $slug = 'vg-task-6c-complete-cleanup-a';
        vg6c_test_person(510, $title, $slug);
        $persons = [];
        vg_event_consumer_6c_qa_track_person_id($persons, 510, $title, $slug);
        vg_event_consumer_6c_qa_mark_person_owner($persons, 510, $owner);
        vg6c_test_service_create_participation(810, 110, 510);
        $participations = [];
        vg_event_consumer_6c_qa_track_participation_id($participations, 810, 110, 510);
        vg_event_consumer_6c_qa_mark_participation_owner($participations, 810, $owner);
        $failures = vg_event_consumer_6c_qa_cleanup_owned_records($participations, $persons, $owner);
        vg6c_test_check($failures === [], 'Cleanup reported a failure for fully owned records.');
        foreach ([510, 810] as $id) {
            vg6c_test_check(get_post($id) === null, 'An owned QA post remains.');
            vg6c_test_check(get_post_meta($id) === [], 'Metadata remains for an owned QA post.');
            vg6c_test_check(wp_get_object_terms($id, ['person_type', 'content_topic', 'event_role'], ['fields' => 'ids']) === [], 'Taxonomy relationships remain for an owned QA post.');
        }
    },
];

$passed = 0;
$failed = 0;
foreach ($cases as $name => $test) {
    try {
        $test();
        ++$passed;
        fwrite(STDOUT, 'PASS ' . $name . ".\n");
    } catch (Throwable $error) {
        ++$failed;
        fwrite(STDERR, 'FAIL ' . $name . ': ' . $error->getMessage() . "\n");
    }
}

if ($failed > 0) {
    fwrite(STDERR, sprintf('Task 6C QA ownership: %d/%d passed.' . PHP_EOL, $passed, count($cases)));
    exit(1);
}

fwrite(STDOUT, sprintf('Task 6C QA ownership: %d/%d passed.' . PHP_EOL, $passed, count($cases)));
