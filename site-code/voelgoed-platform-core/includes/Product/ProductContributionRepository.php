<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Product;

use Voelgoed\PlatformCore\Event\EventParticipationRepository;

defined('ABSPATH') || exit;

/** Reads canonical Product × Person contributions and derived Product membership. */
final class ProductContributionRepository
{
    public const POST_TYPE = 'product_contribution';
    public const PRODUCT_POST_TYPE = 'product';
    public const PERSON_POST_TYPE = 'person';
    public const PRODUCT_FIELD = 'product_contribution_product';
    public const PERSON_FIELD = 'product_contribution_person';
    public const ROLE_TAXONOMY = 'product_role';
    public const DISPLAY_ORDER_FIELD = 'product_contribution_display_order';
    public const SOURCE_EVENT_FIELD = 'produk_bron_event';
    public const ACTIVE_STATUS = 'publish';
    public const UNIQUENESS_STATUSES = ['publish', 'draft', 'pending', 'private', 'future'];
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_EVENT = 'event';

    private EventParticipationRepository $event_repository;

    public function __construct(?EventParticipationRepository $event_repository = null)
    {
        $this->event_repository = $event_repository ?? new EventParticipationRepository();
    }

    public function is_valid_product(int $product_id): bool
    {
        return $this->is_valid_endpoint($product_id, self::PRODUCT_POST_TYPE);
    }

    public function is_valid_person(int $person_id): bool
    {
        return $this->is_valid_endpoint($person_id, self::PERSON_POST_TYPE);
    }

    public function is_valid_product_fresh(int $product_id): bool
    {
        return $this->is_valid_endpoint_fresh($product_id, self::PRODUCT_POST_TYPE);
    }

    public function is_valid_person_fresh(int $person_id): bool
    {
        return $this->is_valid_endpoint_fresh($person_id, self::PERSON_POST_TYPE);
    }

    /**
     * Read an existing contribution's endpoints after clearing WordPress and ACF caches.
     *
     * @return array{product_id:int,person_id:int}|null Null means the authoritative read was uncertain.
     */
    public function stored_pair_ids_fresh(int $contribution_id): ?array
    {
        if (
            $contribution_id <= 0
            || ! $this->flush_fresh_post_fields($contribution_id, [self::PRODUCT_FIELD, self::PERSON_FIELD])
        ) {
            return null;
        }

        $post = get_post($contribution_id);
        if ($this->database_has_error()) {
            return null;
        }
        if (! is_object($post) || ($post->post_type ?? null) !== self::POST_TYPE) {
            return null;
        }

        $product_id = $this->field_id(self::PRODUCT_FIELD, $contribution_id);
        $person_id = $this->field_id(self::PERSON_FIELD, $contribution_id);
        if ($this->database_has_error()) {
            return null;
        }

        return ['product_id' => $product_id, 'person_id' => $person_id];
    }

    /** @return list<int> */
    public function find_pair_ids(
        int $product_id,
        int $person_id,
        ?int $exclude_contribution_id = null
    ): array {
        if ($product_id <= 0 || $person_id <= 0) {
            return [];
        }

        $matches = [];
        foreach ($this->candidate_ids_for_statuses(self::UNIQUENESS_STATUSES) as $contribution_id) {
            if ($exclude_contribution_id !== null && $contribution_id === $exclude_contribution_id) {
                continue;
            }

            if (
                $this->field_id(self::PRODUCT_FIELD, $contribution_id) === $product_id
                && $this->field_id(self::PERSON_FIELD, $contribution_id) === $person_id
            ) {
                $matches[] = $contribution_id;
            }
        }

        sort($matches, SORT_NUMERIC);
        return array_values(array_unique($matches));
    }

    /**
     * Authoritative duplicate lookup for callers holding the matching named lock.
     *
     * @return list<int>|null Null means the authoritative database read was uncertain.
     */
    public function find_pair_ids_fresh(
        int $product_id,
        int $person_id,
        ?int $exclude_contribution_id = null
    ): ?array {
        if ($product_id <= 0 || $person_id <= 0) {
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
        $sql = "SELECT DISTINCT contribution.ID\n"
            . "FROM {$wpdb->posts} AS contribution\n"
            . "INNER JOIN {$wpdb->postmeta} AS product_meta\n"
            . "  ON product_meta.post_id = contribution.ID\n"
            . "  AND product_meta.meta_key = %s AND product_meta.meta_value = %s\n"
            . "INNER JOIN {$wpdb->postmeta} AS person_meta\n"
            . "  ON person_meta.post_id = contribution.ID\n"
            . "  AND person_meta.meta_key = %s AND person_meta.meta_value = %s\n"
            . "WHERE contribution.post_type = %s\n"
            . "  AND contribution.post_status IN ({$status_placeholders})";
        $args = [
            self::PRODUCT_FIELD,
            (string) $product_id,
            self::PERSON_FIELD,
            (string) $person_id,
            self::POST_TYPE,
            ...self::UNIQUENESS_STATUSES,
        ];
        if ($exclude_contribution_id !== null && $exclude_contribution_id > 0) {
            $sql .= "\n  AND contribution.ID <> %d";
            $args[] = $exclude_contribution_id;
        }
        $sql .= "\nORDER BY contribution.ID ASC";

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
            if (! is_int($candidate_id) && ! (is_string($candidate_id) && ctype_digit($candidate_id))) {
                return null;
            }
            $id = (int) $candidate_id;
            if ($id <= 0) {
                return null;
            }
            if ($exclude_contribution_id !== null && $id === $exclude_contribution_id) {
                continue;
            }

            if (! $this->flush_fresh_post_fields($id, [self::PRODUCT_FIELD, self::PERSON_FIELD])) {
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

            $stored_product_id = $this->field_id(self::PRODUCT_FIELD, $id);
            $stored_person_id = $this->field_id(self::PERSON_FIELD, $id);
            if ($this->database_has_error()) {
                return null;
            }
            if ($stored_product_id === $product_id && $stored_person_id === $person_id) {
                $ids[] = $id;
            }
        }

        sort($ids, SORT_NUMERIC);
        return array_values(array_unique($ids));
    }

    /** @return list<int> Published, valid contribution rows for this Product. */
    public function contributions_for_product(int $product_id): array
    {
        if (! $this->is_valid_product($product_id) || ! $this->valid_mode($product_id)) {
            return [];
        }

        $ids = [];
        foreach ($this->candidate_ids_for_endpoint(self::PRODUCT_FIELD, $product_id, [self::ACTIVE_STATUS]) as $id) {
            $row = $this->active_contribution($id, $product_id, null);
            if ($row !== null) {
                $ids[] = $id;
            }
        }

        return $this->sort_contribution_ids_by_display_order($ids);
    }

    /** @return list<int> Published, valid contribution rows for this Person. */
    public function contributions_for_person(int $person_id): array
    {
        if (! $this->is_published_person($person_id)) {
            return [];
        }

        $ids = [];
        foreach ($this->candidate_ids_for_endpoint(self::PERSON_FIELD, $person_id, [self::ACTIVE_STATUS]) as $id) {
            $row = $this->active_contribution($id, null, $person_id);
            if ($row !== null) {
                $ids[] = $id;
            }
        }

        return $this->sort_contribution_ids_by_display_order($ids);
    }

    /** @return list<int> */
    public function people_for_product(int $product_id): array
    {
        if (! $this->is_valid_product($product_id)) {
            return [];
        }

        $mode = $this->product_mode($product_id);
        if ($mode === ProductPeopleFields::MODE_MANUAL) {
            $people = [];
            foreach ($this->contributions_for_product($product_id) as $contribution_id) {
                $row = $this->active_contribution($contribution_id, $product_id, null);
                if (
                    $row !== null
                    && $row['membership_source'] === self::SOURCE_MANUAL
                    && ! in_array($row['person_id'], $people, true)
                ) {
                    $people[] = $row['person_id'];
                }
            }

            return $people;
        }

        if ($mode !== ProductPeopleFields::MODE_EVENT) {
            return [];
        }

        $event_id = $this->field_id(self::SOURCE_EVENT_FIELD, $product_id);
        if ($event_id <= 0) {
            return [];
        }

        $people = [];
        foreach ($this->event_repository->people_for_event($event_id) as $person_id) {
            if ($this->is_published_person($person_id) && ! in_array($person_id, $people, true)) {
                $people[] = $person_id;
            }
        }

        return $people;
    }

    /** @return list<int> Published Products derived from manual or Event membership. */
    public function products_for_person(int $person_id): array
    {
        if (! $this->is_published_person($person_id)) {
            return [];
        }

        $participating_event_ids = $this->event_repository->events_for_person($person_id);
        $products = [];
        foreach ($this->candidate_ids_for_statuses_for_type(self::PRODUCT_POST_TYPE, [self::ACTIVE_STATUS]) as $product_id) {
            if (! $this->is_published_endpoint($product_id, self::PRODUCT_POST_TYPE)) {
                continue;
            }

            $mode = $this->product_mode($product_id);
            if ($mode === ProductPeopleFields::MODE_MANUAL) {
                foreach ($this->contributions_for_product($product_id) as $contribution_id) {
                    $row = $this->active_contribution($contribution_id, $product_id, null);
                    if (
                        $row !== null
                        && $row['membership_source'] === self::SOURCE_MANUAL
                        && $row['person_id'] === $person_id
                    ) {
                        $products[] = $product_id;
                        break;
                    }
                }
                continue;
            }

            if ($mode !== ProductPeopleFields::MODE_EVENT) {
                continue;
            }
            $event_id = $this->field_id(self::SOURCE_EVENT_FIELD, $product_id);
            if ($event_id > 0 && in_array($event_id, $participating_event_ids, true)) {
                $products[] = $product_id;
            }
        }

        sort($products, SORT_NUMERIC);
        return array_values(array_unique($products));
    }

    /** @return list<int> Non-retired rows used by title synchronization. */
    public function non_retired_contributions_for_product(int $product_id): array
    {
        return $this->contribution_ids_for_endpoint(self::PRODUCT_FIELD, $product_id, self::UNIQUENESS_STATUSES);
    }

    /** @return list<int> Non-retired rows used by title synchronization. */
    public function non_retired_contributions_for_person(int $person_id): array
    {
        return $this->contribution_ids_for_endpoint(self::PERSON_FIELD, $person_id, self::UNIQUENESS_STATUSES);
    }

    /** @return list<array{product_id:int,person_id:int,product_role_ids:list<int>,display_order:int,membership_source:string}> */
    public function associations_for_product(int $product_id): array
    {
        if (! $this->is_valid_product($product_id)) {
            return [];
        }

        $mode = $this->product_mode($product_id);
        if ($mode === ProductPeopleFields::MODE_MANUAL) {
            $associations = [];
            $seen_people = [];
            foreach ($this->contributions_for_product($product_id) as $contribution_id) {
                $row = $this->active_contribution($contribution_id, $product_id, null);
                if ($row === null || $row['membership_source'] !== self::SOURCE_MANUAL) {
                    continue;
                }
                if (in_array($row['person_id'], $seen_people, true)) {
                    continue;
                }
                $seen_people[] = $row['person_id'];
                $associations[] = $this->association($product_id, $row['person_id'], $row['product_role_ids'], $row['display_order'], self::SOURCE_MANUAL);
            }

            return $associations;
        }

        if ($mode !== ProductPeopleFields::MODE_EVENT) {
            return [];
        }

        $event_id = $this->field_id(self::SOURCE_EVENT_FIELD, $product_id);
        if ($event_id <= 0) {
            return [];
        }

        $annotations = [];
        foreach ($this->contributions_for_product($product_id) as $contribution_id) {
            $row = $this->active_contribution($contribution_id, $product_id, null);
            if ($row !== null && ! isset($annotations[$row['person_id']])) {
                $annotations[$row['person_id']] = $row['product_role_ids'];
            }
        }

        $associations = [];
        $seen_people = [];
        foreach ($this->event_repository->people_for_event($event_id) as $person_id) {
            if (! $this->is_published_person($person_id) || in_array($person_id, $seen_people, true)) {
                continue;
            }
            $display_order = $this->event_repository->display_order_for_pair($event_id, $person_id);
            if ($display_order === null) {
                continue;
            }
            $seen_people[] = $person_id;
            $associations[] = $this->association(
                $product_id,
                $person_id,
                $annotations[$person_id] ?? [],
                $display_order,
                self::SOURCE_EVENT
            );
        }

        return $associations;
    }

    /** @return list<array{product_id:int,person_id:int,product_role_ids:list<int>,display_order:int,membership_source:string}> */
    public function associations_for_person(int $person_id): array
    {
        if (! $this->is_published_person($person_id)) {
            return [];
        }

        $associations = [];
        foreach ($this->products_for_person($person_id) as $product_id) {
            foreach ($this->associations_for_product($product_id) as $association) {
                if ($association['person_id'] === $person_id) {
                    $associations[] = $association;
                    break;
                }
            }
        }

        usort($associations, static fn (array $left, array $right): int => $left['product_id'] <=> $right['product_id']);
        return $associations;
    }

    private function active_contribution(int $contribution_id, ?int $expected_product_id, ?int $expected_person_id): ?array
    {
        $post = get_post($contribution_id);
        if (
            ! is_object($post)
            || ($post->post_type ?? null) !== self::POST_TYPE
            || ($post->post_status ?? null) !== self::ACTIVE_STATUS
        ) {
            return null;
        }

        $product_id = $this->field_id(self::PRODUCT_FIELD, $contribution_id);
        $person_id = $this->field_id(self::PERSON_FIELD, $contribution_id);
        if (
            $product_id <= 0
            || $person_id <= 0
            || ($expected_product_id !== null && $product_id !== $expected_product_id)
            || ($expected_person_id !== null && $person_id !== $expected_person_id)
            || ! $this->is_valid_product($product_id)
            || ! $this->is_published_person($person_id)
        ) {
            return null;
        }

        $mode = $this->product_mode($product_id);
        if ($mode !== ProductPeopleFields::MODE_MANUAL && $mode !== ProductPeopleFields::MODE_EVENT) {
            return null;
        }

        $role_ids = $this->approved_role_ids($contribution_id);
        if ($role_ids === []) {
            return null;
        }

        if ($mode === ProductPeopleFields::MODE_EVENT) {
            $event_id = $this->field_id(self::SOURCE_EVENT_FIELD, $product_id);
            if (
                $event_id <= 0
                || $this->event_repository->display_order_for_pair($event_id, $person_id) === null
            ) {
                return null;
            }
        }

        return [
            'contribution_id' => $contribution_id,
            'product_id' => $product_id,
            'person_id' => $person_id,
            'product_role_ids' => $role_ids,
            'display_order' => $this->display_order($contribution_id),
            'membership_source' => $mode,
        ];
    }

    /** @return list<int> */
    private function approved_role_ids(int $contribution_id): array
    {
        $term_ids = wp_get_object_terms($contribution_id, self::ROLE_TAXONOMY, ['fields' => 'ids']);
        if (is_wp_error($term_ids) || ! is_array($term_ids)) {
            return [];
        }

        $approved = [];
        foreach ($term_ids as $term_id) {
            $id = $this->positive_id($term_id);
            if ($id > 0 && ProductRoleVocabulary::is_approved_term_id($id)) {
                $approved[] = $id;
            }
        }
        sort($approved, SORT_NUMERIC);
        return array_values(array_unique($approved));
    }

    private function valid_mode(int $product_id): bool
    {
        return in_array($this->product_mode($product_id), [ProductPeopleFields::MODE_MANUAL, ProductPeopleFields::MODE_EVENT], true);
    }

    private function product_mode(int $product_id): string
    {
        $mode = get_field(ProductPeopleFields::MODE_FIELD, $product_id);
        return is_string($mode) ? $mode : '';
    }

    private function display_order(int $contribution_id): int
    {
        $value = get_field(self::DISPLAY_ORDER_FIELD, $contribution_id);
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            return 0;
        }

        $order = (int) $value;
        return $order >= 0 ? $order : 0;
    }

    /** @param list<int> $contribution_ids @return list<int> */
    private function sort_contribution_ids_by_display_order(array $contribution_ids): array
    {
        usort($contribution_ids, function (int $left, int $right): int {
            return ($this->display_order($left) <=> $this->display_order($right))
                ?: ($left <=> $right);
        });

        return $contribution_ids;
    }

    private function field_id(string $field_name, int $post_id): int
    {
        return $this->positive_id(get_field($field_name, $post_id));
    }

    private function positive_id(mixed $value): int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : 0;
        }
        if (is_string($value) && ctype_digit($value)) {
            $id = (int) $value;
            return $id > 0 ? $id : 0;
        }

        return 0;
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

    private function is_published_person(int $person_id): bool
    {
        return $this->is_published_endpoint($person_id, self::PERSON_POST_TYPE);
    }

    private function is_published_endpoint(int $post_id, string $expected_post_type): bool
    {
        if ($post_id <= 0) {
            return false;
        }
        $post = get_post($post_id);
        return is_object($post)
            && ($post->post_type ?? null) === $expected_post_type
            && ($post->post_status ?? null) === self::ACTIVE_STATUS;
    }

    private function is_valid_endpoint_fresh(int $post_id, string $expected_post_type): bool
    {
        if ($post_id <= 0 || ! $this->flush_fresh_post_fields($post_id, [])) {
            return false;
        }

        $post = get_post($post_id);
        return ! $this->database_has_error()
            && is_object($post)
            && ($post->post_type ?? null) === $expected_post_type
            && ($post->post_status ?? null) !== 'trash';
    }

    /** @param list<string> $field_names */
    private function flush_fresh_post_fields(int $post_id, array $field_names): bool
    {
        if (! function_exists('clean_post_cache') || ! function_exists('acf_flush_value_cache')) {
            return false;
        }

        try {
            clean_post_cache($post_id);
            foreach ($field_names as $field_name) {
                acf_flush_value_cache($post_id, $field_name);
            }
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    private function database_has_error(): bool
    {
        global $wpdb;
        return is_object($wpdb) && (string) ($wpdb->last_error ?? '') !== '';
    }

    /** @return list<int> */
    private function candidate_ids_for_endpoint(string $field_name, int $endpoint_id, array $statuses): array
    {
        if ($endpoint_id <= 0) {
            return [];
        }

        return $this->candidate_ids_for_query([
            'post_type' => self::POST_TYPE,
            'post_status' => $statuses,
            'numberposts' => -1,
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
            'fields' => 'ids',
            'meta_key' => $field_name,
            'meta_value' => (string) $endpoint_id,
        ], self::POST_TYPE, $statuses);
    }

    /** @param list<string> $statuses @return list<int> */
    private function candidate_ids_for_statuses(array $statuses): array
    {
        return $this->candidate_ids_for_query([
            'post_type' => self::POST_TYPE,
            'post_status' => $statuses,
            'numberposts' => -1,
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
            'fields' => 'ids',
        ], self::POST_TYPE, $statuses);
    }

    /** @param list<string> $statuses @return list<int> */
    private function candidate_ids_for_statuses_for_type(string $post_type, array $statuses): array
    {
        return $this->candidate_ids_for_query([
            'post_type' => $post_type,
            'post_status' => $statuses,
            'numberposts' => -1,
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
            'fields' => 'ids',
        ], $post_type, $statuses);
    }

    /** @param array<string, mixed> $query @param list<string> $statuses @return list<int> */
    private function candidate_ids_for_query(array $query, string $post_type, array $statuses): array
    {
        $candidate_ids = get_posts($query);
        if (! is_array($candidate_ids)) {
            return [];
        }

        $ids = [];
        foreach ($candidate_ids as $candidate_id) {
            $id = $this->positive_id($candidate_id);
            if ($id <= 0) {
                continue;
            }
            $post = get_post($id);
            if (
                ! is_object($post)
                || ($post->post_type ?? null) !== $post_type
                || ! in_array((string) ($post->post_status ?? ''), $statuses, true)
            ) {
                continue;
            }
            $ids[] = $id;
        }

        sort($ids, SORT_NUMERIC);
        return array_values(array_unique($ids));
    }

    /** @param list<string> $statuses @return list<int> */
    private function contribution_ids_for_endpoint(string $field_name, int $endpoint_id, array $statuses): array
    {
        return $this->candidate_ids_for_endpoint($field_name, $endpoint_id, $statuses);
    }

    /** @return array{product_id:int,person_id:int,product_role_ids:list<int>,display_order:int,membership_source:string} */
    private function association(int $product_id, int $person_id, array $role_ids, int $display_order, string $source): array
    {
        sort($role_ids, SORT_NUMERIC);
        return [
            'product_id' => $product_id,
            'person_id' => $person_id,
            'product_role_ids' => array_values(array_unique($role_ids)),
            'display_order' => $display_order,
            'membership_source' => $source,
        ];
    }
}
