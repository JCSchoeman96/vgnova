<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Product;

use Voelgoed\PlatformCore\Event\EventParticipationRepository;

defined('ABSPATH') || exit;

/**
 * Read-only canonical Product → Person authority.
 *
 * Resolves People from the explicit product_people_mode only:
 * - event: produk_bron_event → EventParticipationRepository → published Persons
 * - manual: product_people_override → published Persons in editor order
 * - anything else (absent, empty, invalid): []
 *
 * Never reads produk_medewerkers, event_medewerkers, or medewerker, and never writes.
 */
final class ProductPeopleResolver
{
    public const PRODUCT_POST_TYPE = 'product';
    public const PERSON_POST_TYPE = 'person';
    public const PERSON_ACTIVE_STATUS = 'publish';
    public const SOURCE_EVENT_FIELD = 'produk_bron_event';

    private EventParticipationRepository $repository;

    public function __construct(?EventParticipationRepository $repository = null)
    {
        $this->repository = $repository ?? new EventParticipationRepository();
    }

    /** @return list<int> */
    public function people_for_product(int $product_id): array
    {
        if ($product_id <= 0 || get_post_type($product_id) !== self::PRODUCT_POST_TYPE) {
            return [];
        }

        // Exact comparison: whitespace and case variants are invalid, not normalized.
        $mode = get_post_meta($product_id, ProductPeopleFields::MODE_FIELD, true);

        if ($mode === ProductPeopleFields::MODE_EVENT) {
            return $this->event_people($product_id);
        }

        if ($mode === ProductPeopleFields::MODE_MANUAL) {
            return $this->manual_people($product_id);
        }

        return [];
    }

    /** @return list<int> */
    private function event_people(int $product_id): array
    {
        $event_id = $this->positive_id(get_field(self::SOURCE_EVENT_FIELD, $product_id, false));
        if ($event_id === null || ! $this->repository->is_valid_event($event_id)) {
            return [];
        }

        return $this->eligible_people($this->repository->people_for_event($event_id));
    }

    /** @return list<int> */
    private function manual_people(int $product_id): array
    {
        $raw = get_field(ProductPeopleFields::OVERRIDE_FIELD, $product_id, false);
        if (! is_array($raw)) {
            return [];
        }

        $candidate_ids = [];
        foreach ($raw as $value) {
            $person_id = $this->positive_id($value);
            if ($person_id !== null) {
                $candidate_ids[] = $person_id;
            }
        }

        return $this->eligible_people($candidate_ids);
    }

    /**
     * @param list<int> $candidate_ids
     * @return list<int>
     */
    private function eligible_people(array $candidate_ids): array
    {
        $people = [];
        foreach ($candidate_ids as $person_id) {
            if (in_array($person_id, $people, true)) {
                continue;
            }

            $post = get_post($person_id);
            if (
                ! is_object($post)
                || ($post->post_type ?? null) !== self::PERSON_POST_TYPE
                || ($post->post_status ?? null) !== self::PERSON_ACTIVE_STATUS
            ) {
                continue;
            }

            $people[] = $person_id;
        }

        return $people;
    }

    /** Accept one positive integer or digit-only numeric string; everything else is invalid. */
    private function positive_id(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && ctype_digit($value)) {
            $id = (int) $value;
            return $id > 0 ? $id : null;
        }

        return null;
    }
}
