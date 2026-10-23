<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Product;

defined('ABSPATH') || exit;

/** Owns the exact Product Role vocabulary approved for publication. */
final class ProductRoleVocabulary
{
    public const TAXONOMY = 'product_role';

    /** @var array<string, string> slug => approved label */
    private const APPROVED_TERMS = [
        'aanbieder' => 'Aanbieder',
        'skrywer' => 'Skrywer',
        'instrukteur' => 'Instrukteur',
        'skepper' => 'Skepper',
    ];

    /** @return array<string, string> slug => approved label */
    public static function approved_terms(): array
    {
        return self::APPROVED_TERMS;
    }

    public static function is_approved_term_id(int $term_id): bool
    {
        if ($term_id <= 0) {
            return false;
        }

        $term = get_term($term_id);
        if (! is_object($term) || is_wp_error($term)) {
            return false;
        }

        $slug = (string) ($term->slug ?? '');
        return ($term->taxonomy ?? null) === self::TAXONOMY
            && array_key_exists($slug, self::APPROVED_TERMS)
            && ($term->name ?? null) === self::APPROVED_TERMS[$slug];
    }
}
