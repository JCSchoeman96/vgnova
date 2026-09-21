<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Dashboard;

use Throwable;
use Voelgoed\PlatformCore\Config\AfrikaansLabels;
use Voelgoed\PlatformCore\Contracts\DashboardAction;
use Voelgoed\PlatformCore\Contracts\DashboardCard;
use Voelgoed\PlatformCore\Contracts\DashboardServiceInterface;
use Voelgoed\PlatformCore\Services\CommerceService;
use Voelgoed\PlatformCore\Services\LearningService;
use Voelgoed\PlatformCore\Services\MemberAreaService;
use Voelgoed\PlatformCore\Services\MembershipService;
use Voelgoed\PlatformCore\Services\SubscriptionService;
use Voelgoed\PlatformCore\Services\TicketService;

/**
 * Collects provider cards without allowing one provider failure to abort the page.
 */
final class DashboardRegistry
{
    public const MEMBERSHIP = 'membership';
    public const SUBSCRIPTIONS = 'subscriptions';
    public const ORDERS = 'orders';
    public const TICKETS = 'tickets';
    public const LEARNING = 'learning';
    public const MEMBER_AREA = 'member-area';

    /**
     * Provider order is also the order used by the overview card collection.
     *
     * @var array<int, string>
     */
    private const PROVIDER_KEYS = [
        self::MEMBERSHIP,
        self::SUBSCRIPTIONS,
        self::ORDERS,
        self::TICKETS,
        self::LEARNING,
        self::MEMBER_AREA,
    ];

    /**
     * @var array<string, DashboardServiceInterface>
     */
    private array $services = [];

    /**
     * Request-local card projections keyed by provider and user ID.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $card_cache = [];

    private ?DashboardContext $context;

    /**
     * @param array<string, DashboardServiceInterface|object> $services
     */
    public function __construct(array $services = [], ?DashboardContext $context = null)
    {
        $this->context = $context;

        $defaults = [
            self::MEMBERSHIP    => new MembershipService(),
            self::SUBSCRIPTIONS => new SubscriptionService(),
            self::ORDERS        => new CommerceService(),
            self::TICKETS       => new TicketService(),
            self::LEARNING      => new LearningService(),
            self::MEMBER_AREA   => new MemberAreaService(),
        ];

        foreach ($defaults as $key => $service) {
            $this->services[$key] = $service;
        }

        foreach ($services as $key => $service) {
            if (! $service instanceof DashboardServiceInterface) {
                continue;
            }

            $normalized_key = self::normalize_provider_key((string) $key);
            if ($normalized_key === '' || ! in_array($normalized_key, self::PROVIDER_KEYS, true)) {
                continue;
            }

            $this->services[$normalized_key] = $service;
        }
    }

    /**
     * Hook-compatible no-op. The registry has no WordPress hooks of its own.
     */
    public function register(): void
    {
    }

    /**
     * @return array<string, DashboardServiceInterface>
     */
    public function services(): array
    {
        return $this->services;
    }

    public function service(string $key): ?DashboardServiceInterface
    {
        $key = self::normalize_provider_key($key);

        return $key !== '' && isset($this->services[$key]) ? $this->services[$key] : null;
    }

    /**
     * @return array<int, string>
     */
    public static function provider_keys(): array
    {
        return self::PROVIDER_KEYS;
    }

    /**
     * @param DashboardContext|int|null $context
     * @return array<string, array<string, mixed>>
     */
    public function cards(DashboardContext|int|null $context = null): array
    {
        $resolved_context = $this->resolve_context($context);
        $cards = [];

        foreach (self::PROVIDER_KEYS as $key) {
            $cards[$key] = $this->card_for_provider($key, $resolved_context->user_id());
        }

        return $cards;
    }

    /**
     * Return the normalized view for a stable dashboard key.
     *
     * @param DashboardContext|int|null $context
     * @return array<string, mixed>|array<string, array<string, mixed>>|null
     */
    public function view(string $key, DashboardContext|int|null $context = null): ?array
    {
        $key = self::normalize_view_key($key);
        $resolved_context = $this->resolve_context($context);

        if ($key === DashboardContext::OVERVIEW) {
            return [
                'key'   => DashboardContext::OVERVIEW,
                'label' => AfrikaansLabels::OVERVIEW,
                'cards' => $this->cards($resolved_context),
            ];
        }

        if ($key === DashboardContext::PROFILE) {
            return $this->profile_view();
        }

        if ($key === DashboardContext::PAYMENT_METHODS) {
            return $this->payment_methods_view();
        }

        $provider_key = $key === DashboardContext::ORDERS ? self::ORDERS : $key;
        if (! in_array($provider_key, self::PROVIDER_KEYS, true)) {
            return null;
        }

        return $this->card_for_provider($provider_key, $resolved_context->user_id());
    }

    /**
     * @param DashboardContext|int|null $context
     * @return array<string, mixed>|null
     */
    public function card(string $key, DashboardContext|int|null $context = null): ?array
    {
        $view = $this->view($key, $context);

        return $view;
    }

    /**
     * @param DashboardContext|int|null $context
     * @return array<string, array<string, mixed>>
     */
    public function summary_cards(DashboardContext|int|null $context = null): array
    {
        return $this->cards($context);
    }

    private function card_for_provider(string $key, int $user_id): array
    {
        $cache_key = $key . ':' . $user_id;
        if (array_key_exists($cache_key, $this->card_cache)) {
            return $this->card_cache[$cache_key];
        }

        $service = $this->services[$key] ?? null;
        if (! $service instanceof DashboardServiceInterface) {
            return $this->card_cache[$cache_key] = $this->fallback_card($key);
        }

        try {
            $card = $service->card($user_id);
        } catch (Throwable) {
            return $this->card_cache[$cache_key] = $this->fallback_card($key);
        }

        if (! is_array($card) || ! $this->is_valid_card($card, $key)) {
            return $this->card_cache[$cache_key] = $this->fallback_card($key);
        }

        try {
            return $this->card_cache[$cache_key] = DashboardCard::make(
                $key,
                $card['label'],
                $card['availability'],
                $card['summary'],
                $card['actions'],
                $card['items'],
                $card['visible'],
                $card['content_state']
            );
        } catch (Throwable) {
            return $this->card_cache[$cache_key] = $this->fallback_card($key);
        }
    }

    /**
     * @param array<string, mixed> $card
     */
    private function is_valid_card(array $card, string $key): bool
    {
        if (($card['key'] ?? null) !== $key) {
            return false;
        }

        foreach (['label', 'visible', 'availability', 'content_state', 'summary', 'items', 'actions'] as $required) {
            if (! array_key_exists($required, $card)) {
                return false;
            }
        }

        return is_string($card['label'])
            && is_bool($card['visible'])
            && is_string($card['availability'])
            && is_string($card['content_state'])
            && is_string($card['summary'])
            && is_array($card['items'])
            && is_array($card['actions']);
    }

    /**
     * @return array<string, mixed>
     */
    private function fallback_card(string $key): array
    {
        $label = $this->label_for_provider($key);
        $visible = $key !== self::MEMBER_AREA;

        return DashboardCard::empty(
            $key,
            $label,
            DashboardCard::AVAILABILITY_UNAVAILABLE,
            $visible ? $label . ' is tans nie beskikbaar nie.' : '',
            $visible
        );
    }

    private function label_for_provider(string $key): string
    {
        return match ($key) {
            self::MEMBERSHIP => AfrikaansLabels::MEMBERSHIP,
            self::SUBSCRIPTIONS => AfrikaansLabels::SUBSCRIPTIONS,
            self::ORDERS => AfrikaansLabels::ORDERS,
            self::TICKETS => AfrikaansLabels::TICKETS,
            self::LEARNING => AfrikaansLabels::LEARNING,
            self::MEMBER_AREA => AfrikaansLabels::MEMBER_AREA,
            default => $key,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function profile_view(): array
    {
        $action = DashboardAction::link(
            DashboardContext::PAYMENT_METHODS,
            AfrikaansLabels::PAYMENT_METHODS,
            '/my-voelgoed/payment-methods/'
        );

        return DashboardCard::make(
            DashboardContext::PROFILE,
            AfrikaansLabels::PROFILE,
            DashboardCard::AVAILABILITY_AVAILABLE,
            '',
            [$action]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payment_methods_view(): array
    {
        return DashboardCard::make(
            DashboardContext::PAYMENT_METHODS,
            AfrikaansLabels::PAYMENT_METHODS,
            DashboardCard::AVAILABILITY_AVAILABLE,
            '',
            [DashboardAction::link(
                'profile',
                AfrikaansLabels::PROFILE,
                '/my-voelgoed/profile/'
            )]
        );
    }

    /**
     * @param DashboardContext|int|null $context
     */
    private function resolve_context(DashboardContext|int|null $context): DashboardContext
    {
        if ($context instanceof DashboardContext) {
            return $context;
        }

        if (is_int($context)) {
            return DashboardContext::for_user($context);
        }

        return $this->context ?? DashboardContext::current();
    }

    private static function normalize_provider_key(string $key): string
    {
        $key = strtolower(trim($key));
        $key = str_replace('_', '-', $key);

        return match ($key) {
            'commerce', 'orders' => self::ORDERS,
            'memberarea', 'member-area' => self::MEMBER_AREA,
            self::MEMBERSHIP, self::SUBSCRIPTIONS, self::TICKETS, self::LEARNING => $key,
            default => '',
        };
    }

    private static function normalize_view_key(string $key): string
    {
        $key = strtolower(trim($key));
        $key = str_replace('_', '-', $key);

        if ($key === self::MEMBER_AREA || $key === 'memberarea') {
            return self::MEMBER_AREA;
        }

        return in_array($key, DashboardContext::view_keys(), true) ? $key : '';
    }
}
