<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Product;

defined('ABSPATH') || exit;

/**
 * Read-only canonical Product → Person authority.
 *
 * Delegates resolution to ProductContributionRepository::people_for_product().
 * Never reads produk_medewerkers, event_medewerkers, or medewerker, and never writes.
 */
final class ProductPeopleResolver
{
    public const PRODUCT_POST_TYPE = 'product';
    public const PERSON_POST_TYPE = 'person';
    public const PERSON_ACTIVE_STATUS = 'publish';
    public const SOURCE_EVENT_FIELD = 'produk_bron_event';

    private ProductContributionRepository $repository;

    public function __construct(?ProductContributionRepository $repository = null)
    {
        $this->repository = $repository ?? new ProductContributionRepository();
    }

    /** @return list<int> */
    public function people_for_product(int $product_id): array
    {
        if ($product_id <= 0 || get_post_type($product_id) !== self::PRODUCT_POST_TYPE) {
            return [];
        }

        // Fail closed when ACF field reads are unavailable: configured Products resolve to [] rather than fataling.
        if (! function_exists('get_field')) {
            return [];
        }

        return $this->repository->people_for_product($product_id);
    }
}
