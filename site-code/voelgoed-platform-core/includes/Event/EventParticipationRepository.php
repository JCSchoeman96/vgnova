<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Event;

defined('ABSPATH') || exit;

/** Reads canonical Event Participation relationships. */
final class EventParticipationRepository
{
    public const POST_TYPE = 'event_participation';
    public const EVENT_FIELD = 'event_participation_event';
    public const PERSON_FIELD = 'event_participation_person';
    public const DISPLAY_ORDER_FIELD = 'event_participation_display_order';
    public const ROLE_TAXONOMY = 'event_role';
    public const ACTIVE_STATUS = 'publish';
    public const UNIQUENESS_STATUSES = ['publish', 'draft', 'pending', 'private', 'future'];

    public function is_valid_event(int $event_id): bool
    {
        return $this->is_valid_endpoint($event_id, 'tc_events');
    }

    public function is_valid_person(int $person_id): bool
    {
        return $this->is_valid_endpoint($person_id, 'person');
    }

    public function is_valid_event_fresh(int $event_id): bool
    {
        return $this->is_valid_endpoint_fresh($event_id, 'tc_events');
    }

    public function is_valid_person_fresh(int $person_id): bool
    {
        return $this->is_valid_endpoint_fresh($person_id, 'person');
    }

    /**
     * Read an existing participation's endpoint values through ACF after clearing its caches.
     *
     * @return array{event_id:int,person_id:int}|null Null indicates the authoritative read could not be completed.
     */
    public function stored_pair_ids_fresh(int $participation_id): ?array
    {
        if (
            $participation_id <= 0
            || ! $this->flush_fresh_post_fields($participation_id, [self::EVENT_FIELD, self::PERSON_FIELD])
        ) {
            return null;
        }

        $post = get_post($participation_id);
        if (! is_object($post) || ($post->post_type ?? null) !== self::POST_TYPE) {
            return null;
        }

        $event_id = $this->field_id(self::EVENT_FIELD, $participation_id);
        $person_id = $this->field_id(self::PERSON_FIELD, $participation_id);
        if ($this->database_has_error()) {
            return null;
        }

        return ['event_id' => $event_id, 'person_id' => $person_id];
    }

    /** @return list<int> */
    public function find_pair_ids(
        int $event_id,
        int $person_id,
        ?int $exclude_participation_id = null
    ): array {
        if ($event_id <= 0 || $person_id <= 0) {
            return [];
        }

        $matches = [];
        foreach ($this->candidate_ids_for_statuses(self::UNIQUENESS_STATUSES) as $participation_id) {
            if ($exclude_participation_id !== null && $participation_id === $exclude_participation_id) {
                continue;
            }

            if (
                $this->field_id(self::EVENT_FIELD, $participation_id) === $event_id
                && $this->field_id(self::PERSON_FIELD, $participation_id) === $person_id
            ) {
                $matches[] = $participation_id;
            }
        }

        sort($matches, SORT_NUMERIC);

        return $matches;
    }

    /**
     * Authoritative duplicate lookup for callers holding the matching named lock.
     * This bypasses WordPress and ACF request-local caches.
     *
     * @return list<int>|null Null indicates the authoritative lookup could not be completed.
     */
    public function find_pair_ids_fresh(
        int $event_id,
        int $person_id,
        ?int $exclude_participation_id = null
    ): ?array {
        if ($event_id <= 0 || $person_id <= 0) {
            return [];
        }

        global $wpdb;
        if (
            ! is_object($wpdb)
            || ! isset($wpdb->posts, $wpdb->postmeta)
            || ! method_exists($wpdb, 'prepare')
            || ! method_exists($wpdb, 'get_col')
        ) {
            return null;
        }

        $status_placeholders = implode(', ', array_fill(0, count(self::UNIQUENESS_STATUSES), '%s'));
        $sql = "SELECT DISTINCT participation.ID\n"
            . "FROM {$wpdb->posts} AS participation\n"
            . "INNER JOIN {$wpdb->postmeta} AS event_meta\n"
            . "  ON event_meta.post_id = participation.ID\n"
            . "  AND event_meta.meta_key = %s AND event_meta.meta_value = %s\n"
            . "INNER JOIN {$wpdb->postmeta} AS person_meta\n"
            . "  ON person_meta.post_id = participation.ID\n"
            . "  AND person_meta.meta_key = %s AND person_meta.meta_value = %s\n"
            . "WHERE participation.post_type = %s\n"
            . "  AND participation.post_status IN ({$status_placeholders})";
        $args = [
            self::EVENT_FIELD,
            (string) $event_id,
            self::PERSON_FIELD,
            (string) $person_id,
            self::POST_TYPE,
            ...self::UNIQUENESS_STATUSES,
        ];
        if ($exclude_participation_id !== null && $exclude_participation_id > 0) {
            $sql .= "\n  AND participation.ID <> %d";
            $args[] = $exclude_participation_id;
        }
        $sql .= "\nORDER BY participation.ID ASC";

        try {
            $candidate_ids = $wpdb->get_col($wpdb->prepare($sql, ...$args));
        } catch (\Throwable) {
            return null;
        }
        if (! is_array($candidate_ids) || (string) ($wpdb->last_error ?? '') !== '') {
            return null;
        }

        $ids = [];
        foreach ($candidate_ids as $candidate_id) {
            $id = (int) $candidate_id;
            if ($id <= 0 || ($exclude_participation_id !== null && $id === $exclude_participation_id)) {
                continue;
            }

            // Candidate lookup is fresh, then post and ACF caches are cleared before
            // the canonical field API reads the Event and Person endpoint values.
            if (! $this->flush_fresh_post_fields($id, [self::EVENT_FIELD, self::PERSON_FIELD])) {
                return null;
            }
            $post = get_post($id);
            if ($this->database_has_error()) {
                return null;
            }
            if (
                ! is_object($post)
                || ($post->post_type ?? null) !== self::POST_TYPE
                || ! in_array((string) ($post->post_status ?? ''), self::UNIQUENESS_STATUSES, true)
            ) {
                continue;
            }
            $candidate_event_id = $this->field_id(self::EVENT_FIELD, $id);
            $candidate_person_id = $this->field_id(self::PERSON_FIELD, $id);
            if ($this->database_has_error()) {
                return null;
            }
            if ($candidate_event_id === $event_id && $candidate_person_id === $person_id) {
                $ids[] = $id;
            }
        }

        sort($ids, SORT_NUMERIC);
        return array_values(array_unique($ids));
    }

    /** @return list<int> */
    public function participations_for_event(int $event_id): array
    {
        if (! $this->is_valid_event($event_id)) {
            return [];
        }

        return $this->participations_for_endpoint(
            self::EVENT_FIELD,
            $event_id,
            [self::ACTIVE_STATUS]
        );
    }

    /** @return list<int> */
    public function participations_for_person(int $person_id): array
    {
        if (! $this->is_valid_person($person_id)) {
            return [];
        }

        return $this->participations_for_endpoint(
            self::PERSON_FIELD,
            $person_id,
            [self::ACTIVE_STATUS]
        );
    }

    /** @return list<int> */
    public function people_for_event(int $event_id): array
    {
        $person_ids = [];
        foreach ($this->participations_for_event($event_id) as $participation_id) {
            $person_id = $this->field_id(self::PERSON_FIELD, $participation_id);
            if ($person_id > 0 && ! in_array($person_id, $person_ids, true)) {
                $person_ids[] = $person_id;
            }
        }

        return $person_ids;
    }

    /** Return the canonical active Event Participation order for one Event × Person pair. */
    public function display_order_for_pair(int $event_id, int $person_id): ?int
    {
        if (! $this->is_valid_event($event_id) || ! $this->is_valid_person($person_id)) {
            return null;
        }

        foreach ($this->participations_for_event($event_id) as $participation_id) {
            if ($this->field_id(self::PERSON_FIELD, $participation_id) === $person_id) {
                return $this->display_order($participation_id);
            }
        }

        return null;
    }

    /** @return list<int> */
    public function events_for_person(int $person_id): array
    {
        $event_ids = [];
        foreach ($this->participations_for_person($person_id) as $participation_id) {
            $event_id = $this->field_id(self::EVENT_FIELD, $participation_id);
            if ($event_id > 0 && ! in_array($event_id, $event_ids, true)) {
                $event_ids[] = $event_id;
            }
        }

        return $event_ids;
    }

    /** @return list<int> */
    public function live_participations_for_event(int $event_id): array
    {
        if (! $this->is_valid_event($event_id)) {
            return [];
        }

        return $this->participations_for_endpoint(
            self::EVENT_FIELD,
            $event_id,
            self::UNIQUENESS_STATUSES
        );
    }

    /** @return list<int> */
    public function live_participations_for_person(int $person_id): array
    {
        if (! $this->is_valid_person($person_id)) {
            return [];
        }

        return $this->participations_for_endpoint(
            self::PERSON_FIELD,
            $person_id,
            self::UNIQUENESS_STATUSES
        );
    }

    private function is_valid_endpoint(int $post_id, string $expected_post_type): bool
    {
        if ($post_id <= 0) {
            return false;
        }

        $post = get_post($post_id);
        return is_object($post)
            && ($post->post_type ?? null) === $expected_post_type
            && ($post->post_status ?? null) !== 'trash';
    }

    private function is_valid_endpoint_fresh(int $post_id, string $expected_post_type): bool
    {
        if ($post_id <= 0 || ! $this->flush_fresh_post_fields($post_id, [])) {
            return false;
        }

        $post = get_post($post_id);

        return is_object($post)
            && ($post->post_type ?? null) === $expected_post_type
            && ($post->post_status ?? null) !== 'trash';
    }

    /**
     * Clear WordPress post/meta and ACF value caches before an authoritative read.
     *
     * @param list<string> $field_names
     */
    private function flush_fresh_post_fields(int $post_id, array $field_names): bool
    {
        if (! function_exists('clean_post_cache') || ! function_exists('acf_flush_value_cache')) {
            return false;
        }

        clean_post_cache($post_id);
        foreach ($field_names as $field_name) {
            acf_flush_value_cache($post_id, $field_name);
        }

        return true;
    }

    private function database_has_error(): bool
    {
        global $wpdb;
        return is_object($wpdb) && (string) ($wpdb->last_error ?? '') !== '';
    }

    /**
     * @param list<string> $statuses
     * @return list<int>
     */
    private function candidate_ids_for_statuses(array $statuses): array
    {
        $candidate_ids = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => $statuses,
            'numberposts' => -1,
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
            'fields' => 'ids',
        ]);
        if (! is_array($candidate_ids)) {
            return [];
        }

        $ids = [];
        foreach ($candidate_ids as $candidate_id) {
            $participation_id = (int) $candidate_id;
            if ($participation_id <= 0) {
                continue;
            }

            $post = get_post($participation_id);
            if (
                ! is_object($post)
                || ($post->post_type ?? null) !== self::POST_TYPE
                || ! in_array((string) ($post->post_status ?? ''), $statuses, true)
            ) {
                continue;
            }

            $ids[] = $participation_id;
        }

        sort($ids, SORT_NUMERIC);

        return array_values(array_unique($ids));
    }

    /**
     * @param list<string> $statuses
     * @return list<int>
     */
    private function participations_for_endpoint(
        string $endpoint_field,
        int $endpoint_id,
        array $statuses
    ): array {
        $matches = [];
        foreach ($this->candidate_ids_for_statuses($statuses) as $participation_id) {
            if ($this->field_id($endpoint_field, $participation_id) !== $endpoint_id) {
                continue;
            }

            $event_id = $this->field_id(self::EVENT_FIELD, $participation_id);
            $person_id = $this->field_id(self::PERSON_FIELD, $participation_id);
            if (! $this->is_valid_event($event_id) || ! $this->is_valid_person($person_id)) {
                continue;
            }

            $matches[] = [
                'id' => $participation_id,
                'display_order' => $this->display_order($participation_id),
            ];
        }

        usort($matches, static function (array $left, array $right): int {
            return ($left['display_order'] <=> $right['display_order'])
                ?: ($left['id'] <=> $right['id']);
        });

        return array_map(static fn (array $match): int => (int) $match['id'], $matches);
    }

    private function field_id(string $field_name, int $participation_id): int
    {
        $value = get_field($field_name, $participation_id);
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return 0;
    }

    private function display_order(int $participation_id): int
    {
        $value = get_field(self::DISPLAY_ORDER_FIELD, $participation_id);
        return (is_int($value) || (is_string($value) && is_numeric($value)))
            ? (int) $value
            : 0;
    }
}
