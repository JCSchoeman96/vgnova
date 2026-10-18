<?php
declare(strict_types=1);

final class WP_Post
{
    public function __construct(
        public int $ID,
        public string $post_type,
        public string $post_title = '',
        public string $post_name = ''
    ) {
    }
}

$GLOBALS['vg_qa_posts'] = [];
$GLOBALS['vg_qa_meta'] = [];
$GLOBALS['vg_qa_fields'] = [];
$GLOBALS['vg_qa_relationships'] = [];
$GLOBALS['vg_qa_failed_marker_ids'] = [];

function get_post(int $post_id): ?WP_Post
{
    return $GLOBALS['vg_qa_posts'][$post_id] ?? null;
}

function get_field(string $field, int $post_id): mixed
{
    return $GLOBALS['vg_qa_fields'][$post_id][$field] ?? null;
}

function get_post_meta(int $post_id, string $key, bool $single = false): mixed
{
    return $GLOBALS['vg_qa_meta'][$post_id][$key] ?? '';
}

function update_post_meta(int $post_id, string $key, mixed $value): bool
{
    if ($key === '_vg_task_6a_qa_owner' && in_array($post_id, $GLOBALS['vg_qa_failed_marker_ids'], true)) {
        return false;
    }

    $GLOBALS['vg_qa_meta'][$post_id][$key] = $value;
    return true;
}

function wp_delete_post(int $post_id, bool $force_delete = false): WP_Post|false
{
    $post = $GLOBALS['vg_qa_posts'][$post_id] ?? false;
    if (! $post) {
        return false;
    }

    unset(
        $GLOBALS['vg_qa_posts'][$post_id],
        $GLOBALS['vg_qa_meta'][$post_id],
        $GLOBALS['vg_qa_fields'][$post_id],
        $GLOBALS['vg_qa_relationships'][$post_id]
    );

    return $post;
}

function vg_event_participation_qa_meta_count(int $post_id): int
{
    return count($GLOBALS['vg_qa_meta'][$post_id] ?? []);
}

function vg_event_participation_qa_relationship_count(int $post_id): int
{
    return count($GLOBALS['vg_qa_relationships'][$post_id] ?? []);
}

function vg_event_participation_qa_post_count(string $post_type): int
{
    return count(array_filter(
        $GLOBALS['vg_qa_posts'],
        static fn (WP_Post $post): bool => $post->post_type === $post_type
    ));
}

function vg_qa_test_check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function vg_qa_test_reset(): void
{
    $GLOBALS['vg_qa_posts'] = [];
    $GLOBALS['vg_qa_meta'] = [];
    $GLOBALS['vg_qa_fields'] = [];
    $GLOBALS['vg_qa_relationships'] = [];
    $GLOBALS['vg_qa_failed_marker_ids'] = [];
}

function vg_qa_test_person(int $id, string $owner, string $slug): void
{
    $GLOBALS['vg_qa_posts'][$id] = new WP_Post($id, 'person', $owner, $slug);
    $GLOBALS['vg_qa_meta'][$id] = ['_vg_task_6a_qa_owner' => $owner];
    $GLOBALS['vg_qa_relationships'][$id] = ['person_type' => [31], 'content_topic' => [41]];
}

function vg_qa_test_create_participation(int $id, int $event_id, int $person_id): int
{
    $GLOBALS['vg_qa_posts'][$id] = new WP_Post($id, 'event_participation', 'QA participation');
    $GLOBALS['vg_qa_fields'][$id] = [
        'event_participation_event' => $event_id,
        'event_participation_person' => $person_id,
    ];
    $GLOBALS['vg_qa_relationships'][$id] = ['event_role' => [51, 52]];
    return $id;
}

require_once dirname(__DIR__) . '/event-participation-qa-ownership.php';

$passed = 0;
$total = 3;
$run_case = static function (string $name, callable $test) use (&$passed): void {
    try {
        $test();
        $passed++;
        fwrite(STDOUT, 'PASS ' . $name . ".\n");
    } catch (Throwable $error) {
        fwrite(STDERR, 'FAIL ' . $name . ': ' . $error->getMessage() . "\n");
        exit(1);
    }
};

$run_case('unexpected duplicate success removes both participations and the QA Person', static function (): void {
    vg_qa_test_reset();
    $owner = '[QA][AUTOMATED] duplicate-case';
    $slug = 'ep-qa-duplicatecase';
    $event_id = 101;
    $person_id = 501;
    vg_qa_test_person($person_id, $owner, $slug);
    $owned = [];
    $qa_failure = null;
    $cleanup_failures = [];

    try {
        $primary_id = vg_qa_test_create_participation(801, $event_id, $person_id);
        vg_event_participation_qa_track_participation($owned, $primary_id, $event_id, $person_id, $owner);

        $unexpected_duplicate_id = vg_qa_test_create_participation(802, $event_id, $person_id);
        vg_event_participation_qa_track_participation($owned, $unexpected_duplicate_id, $event_id, $person_id, $owner);

        throw new RuntimeException('Duplicate Event/Person pair was not rejected.');
    } catch (Throwable $error) {
        $qa_failure = $error;
    } finally {
        $cleanup_failures = vg_event_participation_qa_cleanup_owned_records($owned, $person_id, $owner, $slug);
    }

    vg_qa_test_check($qa_failure?->getMessage() === 'Duplicate Event/Person pair was not rejected.', 'The simulated duplicate assertion did not fail.');
    vg_qa_test_check($cleanup_failures === [], 'Cleanup reported a failure for the two owned participations.');
    vg_qa_test_check(vg_event_participation_qa_post_count('person') === 0, 'A QA Person remains.');
    vg_qa_test_check(vg_event_participation_qa_post_count('event_participation') === 0, 'A QA participation remains.');
    vg_qa_test_check(vg_event_participation_qa_relationship_count(801) === 0 && vg_event_participation_qa_relationship_count(802) === 0, 'An event_role relationship remains.');
    vg_qa_test_check(vg_event_participation_qa_relationship_count($person_id) === 0, 'A Person taxonomy relationship remains.');
});

$run_case('failed participation marker write still cleans the exact owned ID', static function (): void {
    vg_qa_test_reset();
    $owner = '[QA][AUTOMATED] marker-case';
    $slug = 'ep-qa-markercase';
    $event_id = 102;
    $person_id = 502;
    vg_qa_test_person($person_id, $owner, $slug);
    $GLOBALS['vg_qa_failed_marker_ids'] = [803];
    $owned = [];
    $qa_failure = null;
    $cleanup_failures = [];

    try {
        $created_id = vg_qa_test_create_participation(803, $event_id, $person_id);
        vg_event_participation_qa_track_participation($owned, $created_id, $event_id, $person_id, $owner);
    } catch (Throwable $error) {
        $qa_failure = $error;
    } finally {
        $cleanup_failures = vg_event_participation_qa_cleanup_owned_records($owned, $person_id, $owner, $slug);
    }

    vg_qa_test_check(str_contains((string) $qa_failure?->getMessage(), 'ownership marker'), 'The marker failure did not fail QA.');
    vg_qa_test_check(isset($owned[803]), 'The exact participation ID was not tracked before marker verification.');
    vg_qa_test_check($cleanup_failures === [], 'Cleanup did not accept the exact ID and verified endpoints as ownership proof.');
    vg_qa_test_check(vg_event_participation_qa_post_count('person') === 0, 'A QA Person remains.');
    vg_qa_test_check(vg_event_participation_qa_post_count('event_participation') === 0, 'A QA participation remains.');
    vg_qa_test_check(vg_event_participation_qa_relationship_count(803) === 0, 'An event_role relationship remains.');
});

$run_case('cleanup continues across participation errors and preserves the Person until all are gone', static function (): void {
    vg_qa_test_reset();
    $owner = '[QA][AUTOMATED] continue-case';
    $slug = 'ep-qa-continuecase';
    $event_id = 103;
    $person_id = 503;
    vg_qa_test_person($person_id, $owner, $slug);
    $owned = [];
    foreach ([804, 805] as $id) {
        vg_qa_test_create_participation($id, $event_id, $person_id);
        vg_event_participation_qa_track_participation($owned, $id, $event_id, $person_id, $owner);
    }
    $GLOBALS['vg_qa_fields'][804]['event_participation_event'] = 999;

    $cleanup_failures = vg_event_participation_qa_cleanup_owned_records($owned, $person_id, $owner, $slug);

    vg_qa_test_check(
        count($cleanup_failures) === 2
            && (int) $cleanup_failures[0]['id'] === 804
            && (int) $cleanup_failures[1]['id'] === $person_id,
        'Cleanup did not report both the mismatched endpoint and the dependent Person retention.'
    );
    vg_qa_test_check(get_post(804) instanceof WP_Post, 'Cleanup deleted a participation with a mismatched endpoint.');
    vg_qa_test_check(get_post(805) === null, 'Cleanup stopped before deleting the next proven-owned participation.');
    vg_qa_test_check(get_post($person_id) instanceof WP_Post, 'Cleanup deleted the Person while an owned participation remained.');
});

if ($passed !== $total) {
    fwrite(STDERR, 'FAIL QA cleanup ownership regressions: ' . $passed . '/' . $total . " passed.\n");
    exit(1);
}

fwrite(STDOUT, 'QA cleanup ownership regressions: ' . $passed . '/' . $total . " passed.\n");
