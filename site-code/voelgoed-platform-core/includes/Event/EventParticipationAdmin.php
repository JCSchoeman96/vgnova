<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Event;

defined('ABSPATH') || exit;

/** Enforces editor validation and keeps administrative participation titles derived. */
final class EventParticipationAdmin
{
    public const EVENT_FIELD_KEY = 'field_vg_event_participation_event';
    public const PERSON_FIELD_KEY = 'field_vg_event_participation_person';

    private EventParticipationRepository $repository;

    private EventParticipationService $service;

    private EventParticipationPairLock $pair_lock;

    private bool $syncing_titles = false;

    public function __construct(
        ?EventParticipationRepository $repository = null,
        ?EventParticipationService $service = null,
        ?EventParticipationPairLock $pair_lock = null
    ) {
        $this->repository = $repository ?? new EventParticipationRepository();
        $this->pair_lock = $pair_lock ?? new EventParticipationPairLock();
        $this->service = $service ?? new EventParticipationService($this->repository, $this->pair_lock);
    }

    public function register(): void
    {
        add_filter(
            'acf/validate_value/key=' . self::EVENT_FIELD_KEY,
            [$this, 'validate_event_field'],
            10,
            4
        );
        add_filter(
            'acf/validate_value/key=' . self::PERSON_FIELD_KEY,
            [$this, 'validate_person_field'],
            10,
            4
        );
        add_filter('wp_insert_post_data', [$this, 'validate_pair_before_post_save'], 10, 2);
        add_action('acf/save_post', [$this, 'handle_acf_save'], 20, 1);
        add_action('post_updated', [$this, 'handle_post_updated'], 10, 3);
        add_action('shutdown', [$this, 'release_pair_locks'], 1);
    }

    /** Validate the submitted Event against the submitted or stored Person. */
    public function validate_event_field(mixed $valid, mixed $value, array $field, string $input_name): mixed
    {
        if ($valid !== true) {
            return $valid;
        }

        $post_id = $this->current_post_id();
        $event_id = $this->normalize_id($value);
        $person_id = $this->submitted_or_stored_id(self::PERSON_FIELD_KEY, $post_id);

        return $this->validation_result($event_id, $person_id, $post_id);
    }

    /** Validate the submitted Person against the submitted or stored Event. */
    public function validate_person_field(mixed $valid, mixed $value, array $field, string $input_name): mixed
    {
        if ($valid !== true) {
            return $valid;
        }

        $post_id = $this->current_post_id();
        $event_id = $this->submitted_or_stored_id(self::EVENT_FIELD_KEY, $post_id);
        $person_id = $this->normalize_id($value);

        return $this->validation_result($event_id, $person_id, $post_id);
    }

    /** Reserve and recheck the pair before WordPress writes the participation post row. */
    public function validate_pair_before_post_save(array $data, array $postarr): array
    {
        $submitted = $_POST['acf'] ?? null;
        if (($data['post_type'] ?? null) !== EventParticipationRepository::POST_TYPE) {
            return $data;
        }

        $post_id = $this->normalize_id($postarr['ID'] ?? 0);
        $has_submitted_event = is_array($submitted) && array_key_exists(self::EVENT_FIELD_KEY, $submitted);
        $has_submitted_person = is_array($submitted) && array_key_exists(self::PERSON_FIELD_KEY, $submitted);
        $has_submitted_pair = $has_submitted_event || $has_submitted_person;
        if (! $has_submitted_pair && $post_id <= 0) {
            return $data;
        }

        if ($post_id > 0 && ! $this->pair_lock->acquire_participation($post_id)) {
            wp_die(
                'Could not reserve this participation. Try again.',
                'Event Participation Save Failed',
                ['response' => 503]
            );
        }

        // Retiring a row must remain possible after an endpoint is deleted or trashed.
        // The participation lock still serializes this transition with edits of the same row.
        if ($post_id > 0 && ($data['post_status'] ?? null) === 'trash') {
            return $data;
        }

        $needs_stored_pair = $post_id > 0 && (! $has_submitted_event || ! $has_submitted_person);
        $stored_pair = $needs_stored_pair ? $this->repository->stored_pair_ids_fresh($post_id) : null;
        if ($needs_stored_pair && $stored_pair === null) {
            wp_die(
                'Could not read this participation. Try again.',
                'Event Participation Save Failed',
                ['response' => 503]
            );
        }

        $event_id = $has_submitted_event
            ? $this->normalize_id($submitted[self::EVENT_FIELD_KEY])
            : (int) ($stored_pair['event_id'] ?? 0);
        $person_id = $has_submitted_person
            ? $this->normalize_id($submitted[self::PERSON_FIELD_KEY])
            : (int) ($stored_pair['person_id'] ?? 0);
        if ($event_id <= 0 && $person_id <= 0 && ! $has_submitted_pair) {
            return $data;
        }

        if ($event_id <= 0 || $person_id <= 0) {
            wp_die(
                'Select an existing Event and Person before saving this participation.',
                'Invalid Event Participation',
                ['response' => 400]
            );
        }

        if (! $this->pair_lock->acquire($event_id, $person_id)) {
            wp_die(
                'Could not reserve this Event and Person pair. Try again.',
                'Event Participation Save Failed',
                ['response' => 503]
            );
        }

        $validation = $this->service->validate_pair_fresh(
            $event_id,
            $person_id,
            $post_id > 0 ? $post_id : null
        );
        if (is_wp_error($validation)) {
            if ($validation->get_error_code() === 'participation_fresh_check_failed') {
                wp_die(
                    $validation->get_error_message(),
                    'Event Participation Save Failed',
                    ['response' => 503]
                );
            }

            wp_die(
                $validation->get_error_message(),
                'Duplicate Event Participation',
                ['response' => 409]
            );
        }

        return $data;
    }

    /** Recalculate the title after ACF has saved a participation's endpoints. */
    public function handle_acf_save(mixed $post_id): void
    {
        if ($this->syncing_titles) {
            return;
        }

        try {
            $post_id = $this->normalize_id($post_id);
            if ($post_id <= 0 || get_post_type($post_id) !== EventParticipationRepository::POST_TYPE) {
                return;
            }

            $this->sync_participation_title($post_id);
        } finally {
            $this->release_pair_locks();
        }
    }

    public function release_pair_locks(): void
    {
        $this->pair_lock->release_all();
    }

    /** Resync related participation titles when an Event or Person title changes. */
    public function handle_post_updated(int $post_id, object $post_after, object $post_before): void
    {
        if (
            $this->syncing_titles
            || ($post_after->post_title ?? null) === ($post_before->post_title ?? null)
        ) {
            return;
        }

        $post_type = (string) ($post_after->post_type ?? '');
        if ($post_type === 'tc_events') {
            $participation_ids = $this->repository->live_participations_for_event($post_id);
        } elseif ($post_type === 'person') {
            $participation_ids = $this->repository->live_participations_for_person($post_id);
        } else {
            return;
        }

        foreach ($participation_ids as $participation_id) {
            $this->sync_participation_title($participation_id);
        }
    }

    private function validation_result(int $event_id, int $person_id, int $post_id): true|string
    {
        if ($event_id <= 0 || $person_id <= 0) {
            return 'Select an existing Event and Person for this participation.';
        }

        $result = $this->service->validate_pair(
            $event_id,
            $person_id,
            $post_id > 0 ? $post_id : null
        );
        if (is_wp_error($result)) {
            return $result->get_error_message();
        }

        return true;
    }

    private function submitted_or_stored_id(string $field_key, int $post_id): int
    {
        $submitted = $_POST['acf'][$field_key] ?? null;
        if ($submitted !== null) {
            return $this->normalize_id($submitted);
        }

        return $post_id > 0 ? $this->normalize_id(get_field($field_key, $post_id)) : 0;
    }

    private function current_post_id(): int
    {
        return $this->normalize_id($_POST['post_ID'] ?? 0);
    }

    private function normalize_id(mixed $value): int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : 0;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return 0;
    }

    private function sync_participation_title(int $participation_id): void
    {
        if ($this->syncing_titles || $participation_id <= 0) {
            return;
        }

        $this->pair_lock->with_scoped_locks(function () use ($participation_id): void {
            if (! $this->pair_lock->acquire_participation($participation_id)) {
                return;
            }

            $stored_pair = $this->repository->stored_pair_ids_fresh($participation_id);
            if ($stored_pair === null) {
                return;
            }

            $post = get_post($participation_id);
            if (
                ! is_object($post)
                || ($post->post_type ?? null) !== EventParticipationRepository::POST_TYPE
                || ($post->post_status ?? null) === 'trash'
            ) {
                return;
            }

            $event_is_valid = $this->repository->is_valid_event_fresh($stored_pair['event_id']);
            $person_is_valid = $this->repository->is_valid_person_fresh($stored_pair['person_id']);
            if (! $event_is_valid || ! $person_is_valid) {
                return;
            }

            $title = $this->service->title_for_pair($stored_pair['event_id'], $stored_pair['person_id']);
            if (is_wp_error($title) || get_the_title($participation_id) === $title) {
                return;
            }

            $this->syncing_titles = true;
            try {
                wp_update_post([
                    'ID' => $participation_id,
                    'post_title' => $title,
                ], true);
            } finally {
                $this->syncing_titles = false;
            }
        });
    }
}
