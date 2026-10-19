<?php
declare(strict_types=1);

function vg_event_consumer_6c_qa_assert_post_types_registered(array $post_types): void
{
    foreach ($post_types as $post_type) {
        if (! is_string($post_type) || $post_type === '' || ! post_type_exists($post_type)) {
            throw new RuntimeException('Required canonical post type is not registered: ' . (string) $post_type . '.');
        }
    }
}

function vg_event_consumer_6c_qa_track_person_id(
    array &$owned_persons,
    int $person_id,
    string $expected_title,
    string $expected_slug
): void {
    if ($person_id <= 0 || $expected_title === '' || $expected_slug === '') {
        throw new RuntimeException('The created QA Person identity is incomplete.');
    }
    if (isset($owned_persons[$person_id])) {
        throw new RuntimeException('The QA Person ID is already tracked.');
    }

    // The caller persists this entry before writing the owner marker or more fields.
    $owned_persons[$person_id] = [
        'title' => $expected_title,
        'slug' => $expected_slug,
    ];
}

function vg_event_consumer_6c_qa_mark_person_owner(
    array $owned_persons,
    int $person_id,
    string $owner
): void {
    if ($owner === '' || ! isset($owned_persons[$person_id])) {
        throw new RuntimeException('The Person must be tracked before its owner marker is written.');
    }

    $post = get_post($person_id);
    if (
        ! ($post instanceof WP_Post)
        || $post->post_type !== 'person'
        || $post->post_title !== (string) ($owned_persons[$person_id]['title'] ?? '')
        || $post->post_name !== (string) ($owned_persons[$person_id]['slug'] ?? '')
    ) {
        throw new RuntimeException('The created QA Person does not match its tracked identity.');
    }

    vg_event_consumer_6c_qa_write_owner_marker($person_id, $owner, 'Person');
}

function vg_event_consumer_6c_qa_track_participation_id(
    array &$owned_participations,
    int $participation_id,
    int $expected_event_id,
    int $expected_person_id
): void {
    if ($participation_id <= 0 || $expected_event_id <= 0 || $expected_person_id <= 0) {
        throw new RuntimeException('The created QA participation identity is incomplete.');
    }
    if (isset($owned_participations[$participation_id])) {
        throw new RuntimeException('The QA participation ID is already tracked.');
    }

    // The caller persists this entry before checking fields or writing the owner marker.
    $owned_participations[$participation_id] = [
        'event_id' => $expected_event_id,
        'person_id' => $expected_person_id,
    ];
}

function vg_event_consumer_6c_qa_mark_participation_owner(
    array $owned_participations,
    int $participation_id,
    string $owner
): void {
    $endpoints = $owned_participations[$participation_id] ?? null;
    if ($owner === '' || ! is_array($endpoints)) {
        throw new RuntimeException('The participation must be tracked before its owner marker is written.');
    }

    $post = get_post($participation_id);
    if (! ($post instanceof WP_Post) || $post->post_type !== 'event_participation') {
        throw new RuntimeException('The created QA participation has an unexpected post type.');
    }
    if (
        (int) get_field('event_participation_event', $participation_id) !== (int) ($endpoints['event_id'] ?? 0)
        || (int) get_field('event_participation_person', $participation_id) !== (int) ($endpoints['person_id'] ?? 0)
    ) {
        throw new RuntimeException('The created QA participation has unexpected endpoints.');
    }

    vg_event_consumer_6c_qa_write_owner_marker($participation_id, $owner, 'participation');
}

function vg_event_consumer_6c_qa_cleanup_owned_records(
    array $owned_participations,
    array $owned_persons,
    string $owner
): array {
    $failures = [];
    $blocked_persons = [];
    $block_all_persons = false;

    foreach ($owned_participations as $participation_id => $endpoints) {
        $participation_id = (int) $participation_id;
        $person_id = is_array($endpoints) ? (int) ($endpoints['person_id'] ?? 0) : 0;
        try {
            vg_event_consumer_6c_qa_delete_participation($participation_id, $endpoints, $owner);
        } catch (Throwable $error) {
            $failures[] = ['id' => $participation_id, 'message' => $error->getMessage()];
            if ($person_id > 0) {
                $blocked_persons[$person_id] = true;
            } else {
                $block_all_persons = true;
            }
        }
    }

    foreach ($owned_persons as $person_id => $identity) {
        $person_id = (int) $person_id;
        if ($block_all_persons || isset($blocked_persons[$person_id])) {
            $failures[] = [
                'id' => $person_id,
                'message' => 'QA Person retained because its participation cleanup was not verified.',
            ];
            continue;
        }

        try {
            vg_event_consumer_6c_qa_delete_person($person_id, $identity, $owner);
        } catch (Throwable $error) {
            $failures[] = ['id' => $person_id, 'message' => $error->getMessage()];
        }
    }

    return $failures;
}

function vg_event_consumer_6c_qa_delete_participation(
    int $participation_id,
    mixed $endpoints,
    string $owner
): void {
    if ($participation_id <= 0 || ! is_array($endpoints)) {
        throw new RuntimeException('Tracked participation identity is invalid.');
    }

    $post = get_post($participation_id);
    if ($post !== null && $post !== false) {
        if (! ($post instanceof WP_Post) || $post->post_type !== 'event_participation') {
            throw new RuntimeException('Cleanup refused because the tracked ID is no longer an event_participation post.');
        }
        if (
            (int) get_field('event_participation_event', $participation_id) !== (int) ($endpoints['event_id'] ?? 0)
            || (int) get_field('event_participation_person', $participation_id) !== (int) ($endpoints['person_id'] ?? 0)
        ) {
            throw new RuntimeException('Cleanup refused because a tracked participation endpoint changed.');
        }
        vg_event_consumer_6c_qa_assert_owner_marker($participation_id, $owner, 'participation');

        $deleted = wp_delete_post($participation_id, true);
        if (! $deleted) {
            throw new RuntimeException('WordPress did not confirm hard deletion of the QA participation.');
        }
    }

    vg_event_consumer_6c_qa_verify_deleted_post($participation_id, ['event_role']);
}

function vg_event_consumer_6c_qa_delete_person(int $person_id, mixed $identity, string $owner): void
{
    if ($person_id <= 0 || ! is_array($identity)) {
        throw new RuntimeException('Tracked Person identity is invalid.');
    }

    $post = get_post($person_id);
    if ($post !== null && $post !== false) {
        if (! ($post instanceof WP_Post) || $post->post_type !== 'person') {
            throw new RuntimeException('Cleanup refused because the tracked ID is no longer a Person post.');
        }
        if (
            $post->post_title !== (string) ($identity['title'] ?? '')
            || $post->post_name !== (string) ($identity['slug'] ?? '')
        ) {
            throw new RuntimeException('Cleanup refused because the tracked Person identity changed.');
        }
        vg_event_consumer_6c_qa_assert_owner_marker($person_id, $owner, 'Person');
        if (vg_event_consumer_6c_qa_has_participations($person_id)) {
            throw new RuntimeException('QA Person retained because Event Participation records still reference it.');
        }

        $deleted = wp_delete_post($person_id, true);
        if (! $deleted) {
            throw new RuntimeException('WordPress did not confirm hard deletion of the QA Person.');
        }
    }

    vg_event_consumer_6c_qa_verify_deleted_post($person_id, ['person_type', 'content_topic']);
}

function vg_event_consumer_6c_qa_write_owner_marker(int $post_id, string $owner, string $label): void
{
    $marker_key = '_vg_task_6c_qa_owner';
    vg_event_consumer_6c_qa_assert_owner_marker($post_id, $owner, $label);
    if (get_post_meta($post_id, $marker_key, true) !== $owner) {
        if (update_post_meta($post_id, $marker_key, $owner) === false) {
            throw new RuntimeException('Could not write QA ' . $label . ' ownership marker.');
        }
    }
    if (get_post_meta($post_id, $marker_key, true) !== $owner) {
        throw new RuntimeException('Could not verify QA ' . $label . ' ownership marker.');
    }
}

function vg_event_consumer_6c_qa_assert_owner_marker(int $post_id, string $owner, string $label): void
{
    if ($owner === '') {
        throw new RuntimeException('The QA ownership marker is empty.');
    }
    $marker = get_post_meta($post_id, '_vg_task_6c_qa_owner', true);
    if ($marker !== $owner && $marker !== '' && $marker !== false && $marker !== null) {
        throw new RuntimeException('Cleanup refused because the ' . $label . ' ownership marker conflicts.');
    }
}

function vg_event_consumer_6c_qa_verify_deleted_post(int $post_id, array $taxonomies): void
{
    if ($post_id <= 0) {
        throw new RuntimeException('Deleted post ID is invalid.');
    }
    if (get_post($post_id) !== null && get_post($post_id) !== false) {
        throw new RuntimeException('QA cleanup did not remove the post.');
    }

    $metadata = get_post_meta($post_id);
    if (! is_array($metadata) || $metadata !== []) {
        throw new RuntimeException('QA cleanup did not remove the post metadata.');
    }

    $relationships = wp_get_object_terms($post_id, $taxonomies, ['fields' => 'ids']);
    if (is_wp_error($relationships) || ! is_array($relationships) || $relationships !== []) {
        throw new RuntimeException('QA cleanup did not remove the taxonomy relationships.');
    }
}

function vg_event_consumer_6c_qa_has_participations(int $person_id): bool
{
    $participation_ids = get_posts([
        'post_type' => 'event_participation',
        'post_status' => ['publish', 'draft', 'pending', 'private', 'future', 'trash', 'auto-draft'],
        'numberposts' => -1,
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_key' => 'event_participation_person',
        'meta_value' => $person_id,
    ]);
    if (! is_array($participation_ids)) {
        throw new RuntimeException('Could not verify remaining Person participations.');
    }

    return $participation_ids !== [];
}
