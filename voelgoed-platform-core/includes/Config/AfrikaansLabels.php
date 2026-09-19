<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Config;

/**
 * Central labels for the first dashboard release.
 */
final class AfrikaansLabels
{
    public const OVERVIEW = 'Oorsig';
    public const MEMBERSHIP = 'Lidmaatskap';
    public const SUBSCRIPTIONS = 'Subskripsies';
    public const ORDERS = 'Bestellings';
    public const TICKETS = 'Kaartjies';
    public const LEARNING = 'Kursusse';
    public const PROFILE = 'Profiel';
    public const PAYMENT_METHODS = 'Betaalmetodes';
    public const MEMBER_AREA = 'Lede-area';
    public const ATTENTION = 'Aandag nodig';

    /**
     * Unconditional top-level dashboard sections, in display order.
     *
     * `Lede-area` is conditional and `Betaalmetodes` is a profile action.
     *
     * @return array<string, string>
     */
    public static function sections(): array
    {
        return [
            'overview'      => self::OVERVIEW,
            'membership'    => self::MEMBERSHIP,
            'subscriptions' => self::SUBSCRIPTIONS,
            'orders'        => self::ORDERS,
            'tickets'       => self::TICKETS,
            'learning'      => self::LEARNING,
            'profile'       => self::PROFILE,
        ];
    }

    /**
     * Navigation labels that require a runtime eligibility check.
     *
     * @return array<string, string>
     */
    public static function conditional_navigation(): array
    {
        return [
            'member-area' => self::MEMBER_AREA,
        ];
    }

    /**
     * Labels owned by provider-backed dashboard services.
     *
     * `Lede-area` is navigation supplied by Member Perks, not a provider card.
     *
     * @return array<string, string>
     */
    public static function provider_services(): array
    {
        return [
            'membership'    => self::MEMBERSHIP,
            'subscriptions' => self::SUBSCRIPTIONS,
            'orders'        => self::ORDERS,
            'tickets'       => self::TICKETS,
            'learning'      => self::LEARNING,
        ];
    }

    /**
     * Profile actions that are not top-level navigation sections.
     *
     * @return array<string, string>
     */
    public static function profile_actions(): array
    {
        return [
            'payment-methods' => self::PAYMENT_METHODS,
        ];
    }
}
