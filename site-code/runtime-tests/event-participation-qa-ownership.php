<?php
declare(strict_types=1);

function vg_event_participation_qa_track_participation(
    array &$owned_participations,
    int $participation_id,
    int $expected_event_id,
    int $expected_person_id,
    string $owner
): void {
    if ($participation_id <= 0) {
        throw new RuntimeException('Service returned an invalid participation ID.');
    }

    // Record the exact returned ID before any reads or marker writes can fail.
    $owned_participations[$participation_id] = [
        'event_id' => $expected_event_id,
        'person_id' => $expected_person_id,
    ];

    $post = get_post($participation_id);
    if (! ($post instanceof WP_Post) || $post->post_type !== 'event_participation') {
        throw new RuntimeException('Created QA participation has an unexpected post type.');
    }
    if ((int) get_field('event_participation_event', $participation_id) !== $expected_event_id) {
        throw new RuntimeException('Created QA participation has an unexpected Event endpoint.');
    }
    if ((int) get_field('event_participation_person', $participation_id) !== $expected_person_id) {
        throw new RuntimeException('Created QA participation has an unexpected Person endpoint.');
    }

    $marker = get_post_meta($participation_id, '_vg_task_6a_qa_owner', true);
    if ($marker !== '' && $marker !== false && $marker !== null && $marker !== $owner) {
        throw new RuntimeException('Created QA participation has a conflicting ownership marker.');
    }
    if ($marker !== $owner) {
        update_post_meta($participation_id, '_vg_task_6a_qa_owner', $owner);
    }
    if (get_post_meta($participation_id, '_vg_task_6a_qa_owner', true) !== $owner) {
        throw new RuntimeException('Could not write QA participation ownership marker.');
    }
}

function vg_event_participation_qa_cleanup_owned_records(
    array $owned_participations,
    int $person_id,
    string $owner,
    string $person_slug
): array {
    $failures = [];
    $participations_removed = true;

    foreach ($owned_participations as $participation_id => $endpoints) {
        $participation_id = (int) $participation_id;
        try {
            if ($participation_id <= 0) {
                throw new RuntimeException('Tracked participation ID is invalid.');
            }

            $post = get_post($participation_id);
            if ($post !== null) {
                if (! ($post instanceof WP_Post)) {
                    throw new RuntimeException('Cleanup could not verify the tracked participation post object.');
                }
                if ($post->post_type !== 'event_participation') {
                    throw new RuntimeException('Cleanup refused because the tracked ID is no longer an event_participation post.');
                }
                if ((int) get_field('event_participation_event', $participation_id) !== (int) ($endpoints['event_id'] ?? 0)
                    || (int) get_field('event_participation_person', $participation_id) !== (int) ($endpoints['person_id'] ?? 0)
                ) {
                    throw new RuntimeException('Cleanup refused because a tracked participation endpoint changed.');
                }

                $marker = get_post_meta($participation_id, '_vg_task_6a_qa_owner', true);
                if ($marker !== $owner && $marker !== '' && $marker !== false && $marker !== null) {
                    throw new RuntimeException('Cleanup refused because the participation ownership marker conflicts.');
                }

                wp_delete_post($participation_id, true);
            }

            if (get_post($participation_id) !== null
                || vg_event_participation_qa_meta_count($participation_id) !== 0
                || vg_event_participation_qa_relationship_count($participation_id) !== 0
            ) {
                throw new RuntimeException('Participation cleanup did not remove the post, metadata, and term relationships.');
            }
        } catch (Throwable $error) {
            $participations_removed = false;
            $failures[] = ['id' => $participation_id, 'message' => $error->getMessage()];
        }
    }

    if ($person_id <= 0) {
        return $failures;
    }

    if (! $participations_removed) {
        $failures[] = [
            'id' => $person_id,
            'message' => 'QA Person retained because not all tracked participations were verified as removed.',
        ];
        return $failures;
    }

    try {
        $post = get_post($person_id);
        if ($post !== null) {
            if (! ($post instanceof WP_Post)) {
                throw new RuntimeException('Cleanup could not verify the tracked Person post object.');
            }
            if ($post->post_type !== 'person') {
                throw new RuntimeException('Cleanup refused because the tracked ID is no longer a Person post.');
            }

            $marker = get_post_meta($person_id, '_vg_task_6a_qa_owner', true);
            $marker_matches = $marker === $owner;
            $exact_fallback_matches = ($marker === '' || $marker === false || $marker === null)
                && $post->post_name === $person_slug
                && $post->post_title === $owner;
            if (! $marker_matches && ! $exact_fallback_matches) {
                throw new RuntimeException('Cleanup refused because Person ownership could not be proven.');
            }

            wp_delete_post($person_id, true);
        }

        if (get_post($person_id) !== null
            || vg_event_participation_qa_meta_count($person_id) !== 0
            || vg_event_participation_qa_relationship_count($person_id) !== 0
        ) {
            throw new RuntimeException('Person cleanup did not remove the post, metadata, and term relationships.');
        }
    } catch (Throwable $error) {
        $failures[] = ['id' => $person_id, 'message' => $error->getMessage()];
    }

    return $failures;
}
