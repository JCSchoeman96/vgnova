<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Event;

defined('ABSPATH') || exit;

/** Validates and writes canonical Event Participation relationships. */
final class EventParticipationService
{
    private const ALLOWED_STATUSES = ['publish', 'draft', 'pending', 'private', 'future'];

    private EventParticipationRepository $repository;

    private EventParticipationPairLock $pair_lock;

    public function __construct(
        ?EventParticipationRepository $repository = null,
        ?EventParticipationPairLock $pair_lock = null
    )
    {
        $this->repository = $repository ?? new EventParticipationRepository();
        $this->pair_lock = $pair_lock ?? new EventParticipationPairLock();
    }

    public function validate_pair(
        int $event_id,
        int $person_id,
        ?int $exclude_participation_id = null
    ): true|\WP_Error {
        return $this->validate_pair_using_repository($event_id, $person_id, $exclude_participation_id, false);
    }

    public function validate_pair_fresh(
        int $event_id,
        int $person_id,
        ?int $exclude_participation_id = null
    ): true|\WP_Error {
        return $this->validate_pair_using_repository($event_id, $person_id, $exclude_participation_id, true);
    }

    private function validate_pair_using_repository(
        int $event_id,
        int $person_id,
        ?int $exclude_participation_id,
        bool $fresh
    ): true|\WP_Error {
        $valid_event = $fresh
            ? $this->repository->is_valid_event_fresh($event_id)
            : $this->repository->is_valid_event($event_id);
        if (! $valid_event) {
            return new \WP_Error('invalid_event', 'The Event must be an existing, non-trashed Tickera Event.');
        }

        $valid_person = $fresh
            ? $this->repository->is_valid_person_fresh($person_id)
            : $this->repository->is_valid_person($person_id);
        if (! $valid_person) {
            return new \WP_Error('invalid_person', 'The Person must be an existing, non-trashed Person.');
        }

        if ($fresh) {
            $matches = $this->repository->find_pair_ids_fresh($event_id, $person_id, $exclude_participation_id);
            if ($matches === null) {
                return new \WP_Error(
                    'participation_fresh_check_failed',
                    'Could not verify Event Participation relationships. Try again.'
                );
            }
        } else {
            $matches = $this->repository->find_pair_ids($event_id, $person_id, $exclude_participation_id);
        }

        if ($matches) {
            return new \WP_Error('duplicate_event_participation', 'This Event and Person pair already has a participation.');
        }

        return true;
    }

    public function title_for_pair(int $event_id, int $person_id): string|\WP_Error
    {
        if (! $this->repository->is_valid_event($event_id)) {
            return new \WP_Error('invalid_event', 'The Event must be an existing, non-trashed Tickera Event.');
        }

        if (! $this->repository->is_valid_person($person_id)) {
            return new \WP_Error('invalid_person', 'The Person must be an existing, non-trashed Person.');
        }

        return get_the_title($event_id) . ' — ' . get_the_title($person_id);
    }

    /**
     * Create a canonical Event Participation post and its ACF and taxonomy relationships.
     *
     * @param list<int> $event_role_term_ids
     */
    public function create(
        int $event_id,
        int $person_id,
        array $event_role_term_ids = [],
        int $display_order = 0,
        string $status = 'publish'
    ): int|\WP_Error {
        if ($event_id <= 0) {
            return new \WP_Error('invalid_event', 'The Event must be an existing, non-trashed Tickera Event.');
        }

        if ($person_id <= 0) {
            return new \WP_Error('invalid_person', 'The Person must be an existing, non-trashed Person.');
        }

        if ($display_order < 0) {
            return new \WP_Error('invalid_display_order', 'Display order must be zero or greater.');
        }

        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            return new \WP_Error('invalid_participation_status', 'The requested Event Participation status is not allowed.');
        }

        $validated_role_ids = $this->validated_event_role_ids($event_role_term_ids);
        if (is_wp_error($validated_role_ids)) {
            return $validated_role_ids;
        }

        if (! $this->pair_lock->acquire($event_id, $person_id)) {
            return new \WP_Error(
                'participation_pair_lock_failed',
                'Could not reserve this Event and Person pair. Try again.'
            );
        }

        try {
            return $this->create_locked($event_id, $person_id, $validated_role_ids, $display_order, $status);
        } finally {
            $this->pair_lock->release($event_id, $person_id);
        }
    }

    /**
     * @param list<int> $validated_role_ids
     */
    private function create_locked(
        int $event_id,
        int $person_id,
        array $validated_role_ids,
        int $display_order,
        string $status
    ): int|\WP_Error {
        // Recheck after acquiring the lock so a competing writer cannot pass the check concurrently.
        $pair_validation = $this->validate_pair_fresh($event_id, $person_id);
        if (is_wp_error($pair_validation)) {
            return $pair_validation;
        }

        $title = $this->title_for_pair($event_id, $person_id);
        if (is_wp_error($title)) {
            return $title;
        }

        $participation_id = wp_insert_post([
            'post_type' => EventParticipationRepository::POST_TYPE,
            'post_status' => $status,
            'post_title' => $title,
        ], true);
        if (is_wp_error($participation_id)) {
            return $participation_id;
        }

        $participation_id = (int) $participation_id;
        if ($participation_id <= 0) {
            return new \WP_Error('participation_insert_failed', 'WordPress did not return a valid participation ID.');
        }

        try {
            $field_values = [
                EventParticipationRepository::EVENT_FIELD => $event_id,
                EventParticipationRepository::PERSON_FIELD => $person_id,
                EventParticipationRepository::DISPLAY_ORDER_FIELD => $display_order,
            ];
            foreach ($field_values as $field_name => $value) {
                if (update_field($field_name, $value, $participation_id) === false) {
                    return $this->rollback_new_participation(
                        $participation_id,
                        'participation_field_write_failed',
                        sprintf('Could not write the Event Participation field "%s".', $field_name)
                    );
                }
            }

            $role_result = wp_set_object_terms(
                $participation_id,
                $validated_role_ids,
                EventParticipationRepository::ROLE_TAXONOMY,
                false
            );
            if (is_wp_error($role_result)) {
                return $this->rollback_new_participation(
                    $participation_id,
                    'participation_role_write_failed',
                    'Could not assign Event Participation roles: ' . $role_result->get_error_message()
                );
            }
            if (! is_array($role_result)) {
                return $this->rollback_new_participation(
                    $participation_id,
                    'participation_role_write_failed',
                    'WordPress returned an invalid result while assigning Event Participation roles.'
                );
            }
        } catch (\Throwable $error) {
            return $this->rollback_new_participation(
                $participation_id,
                'participation_write_failed',
                'Could not finish writing the Event Participation: ' . $error->getMessage()
            );
        }

        return $participation_id;
    }

    /**
     * @param list<int> $event_role_term_ids
     * @return list<int>|\WP_Error
     */
    private function validated_event_role_ids(array $event_role_term_ids): array|\WP_Error
    {
        $validated_ids = [];
        foreach ($event_role_term_ids as $term_id) {
            if (! is_int($term_id) && (! is_string($term_id) || ! ctype_digit($term_id))) {
                return new \WP_Error('invalid_event_role', 'Each Event Participation role must be a valid event_role term ID.');
            }

            $term_id = (int) $term_id;
            if ($term_id <= 0) {
                return new \WP_Error('invalid_event_role', 'Each Event Participation role must be a positive event_role term ID.');
            }

            $term = get_term($term_id, EventParticipationRepository::ROLE_TAXONOMY);
            if (is_wp_error($term) || ! is_object($term) || ($term->taxonomy ?? null) !== EventParticipationRepository::ROLE_TAXONOMY) {
                return new \WP_Error('invalid_event_role', 'Each role must belong to the event_role taxonomy.');
            }

            $validated_ids[] = $term_id;
        }

        return array_values(array_unique($validated_ids));
    }

    private function rollback_new_participation(int $participation_id, string $code, string $message): \WP_Error
    {
        $deleted_post = wp_delete_post($participation_id, true);
        if (! $deleted_post) {
            $message .= ' WordPress could not hard-delete the new participation during rollback.';
        }

        return new \WP_Error($code, $message);
    }
}
