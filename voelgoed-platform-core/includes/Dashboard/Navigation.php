<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Dashboard;

use Throwable;
use Voelgoed\PlatformCore\Config\AfrikaansLabels;
use Voelgoed\PlatformCore\Contracts\DashboardAction;

/**
 * Builds the stable dashboard tab and profile-action navigation model.
 */
final class Navigation
{
    private DashboardRegistry $registry;

    public function __construct(DashboardRegistry|array|null $registry = null)
    {
        $this->registry = $registry instanceof DashboardRegistry
            ? $registry
            : new DashboardRegistry(is_array($registry) ? $registry : []);
    }

    public function register(): void
    {
    }

    public function registry(): DashboardRegistry
    {
        return $this->registry;
    }

    /**
     * @param DashboardContext|int|string|null $context
     * @return array<int, array<string, mixed>>
     */
    public function items(DashboardContext|int|string|null $context = null, ?string $active_key = null): array
    {
        [$resolved_context, $resolved_active_key] = $this->resolve_context_and_active($context, $active_key);

        $items = [];
        foreach (AfrikaansLabels::sections() as $key => $label) {
            $item = $this->item(
                $key,
                $label,
                $this->route($key),
                $this->is_active($key, $resolved_active_key)
            );

            if ($key === DashboardContext::PROFILE) {
                $item['children'] = $this->profile_actions($resolved_active_key);
            }

            $items[] = $item;
        }

        $member_area = $this->member_area_item($resolved_context, $resolved_active_key);
        if ($member_area !== null) {
            $items[] = $member_area;
        }

        return $items;
    }

    /**
     * @param DashboardContext|int|string|null $context
     * @return array<int, array<string, mixed>>
     */
    public function build(DashboardContext|int|string|null $context = null, ?string $active_key = null): array
    {
        return $this->items($context, $active_key);
    }

    /**
     * @param DashboardContext|int|string|null $context
     * @return array<int, array<string, mixed>>
     */
    public function navigation(DashboardContext|int|string|null $context = null, ?string $active_key = null): array
    {
        return $this->items($context, $active_key);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function profile_actions(?string $active_key = null): array
    {
        $key = DashboardContext::PAYMENT_METHODS;

        return [$this->item(
            $key,
            AfrikaansLabels::PAYMENT_METHODS,
            '/my-voelgoed/payment-methods/',
            $this->is_active($key, $this->normalize_active_key($active_key ?? DashboardContext::OVERVIEW))
        )];
    }

    /**
     * @param DashboardContext|int|string|null $context
     * @return array{0: DashboardContext, 1: string}
     */
    private function resolve_context_and_active(DashboardContext|int|string|null $context, ?string $active_key): array
    {
        if ($context instanceof DashboardContext) {
            $resolved_context = $context;
        } elseif (is_int($context)) {
            $resolved_context = DashboardContext::for_user($context, $active_key ?? DashboardContext::OVERVIEW);
        } elseif (is_string($context)) {
            $resolved_context = DashboardContext::current($context);
            $active_key = $active_key ?? $context;
        } else {
            $resolved_context = DashboardContext::current($active_key);
        }

        $resolved_active_key = $this->normalize_active_key(
            $active_key ?? $resolved_context->view_key()
        );

        return [$resolved_context, $resolved_active_key];
    }

    /**
     * @return array<string, mixed>
     */
    private function item(string $key, string $label, string $url, bool $active): array
    {
        return [
            'key'          => $key,
            'label'        => $label,
            'url'          => $url,
            'active'       => $active,
            'visible'      => true,
            'aria_current' => $active ? 'page' : 'false',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function member_area_item(DashboardContext $context, string $active_key): ?array
    {
        $card = $this->registry->card(DashboardRegistry::MEMBER_AREA, $context);
        if (! is_array($card) || ($card['visible'] ?? false) !== true) {
            return null;
        }

        $url = '';
        foreach (($card['actions'] ?? []) as $action) {
            if (! is_array($action)) {
                continue;
            }

            try {
                $normalized = DashboardAction::normalize($action);
            } catch (Throwable) {
                continue;
            }

            if ($normalized['url'] !== '') {
                $url = $normalized['url'];
                break;
            }
        }

        if ($url === '') {
            return null;
        }

        return $this->item(
            DashboardRegistry::MEMBER_AREA,
            AfrikaansLabels::MEMBER_AREA,
            $url,
            $this->is_active(DashboardRegistry::MEMBER_AREA, $active_key)
        );
    }

    private function route(string $key): string
    {
        return match ($key) {
            DashboardContext::OVERVIEW => '/my-voelgoed/',
            DashboardContext::MEMBERSHIP => '/my-voelgoed/membership/',
            DashboardContext::SUBSCRIPTIONS => '/my-voelgoed/subscriptions/',
            DashboardContext::ORDERS => '/my-voelgoed/orders/',
            DashboardContext::TICKETS => '/my-voelgoed/tickets/',
            DashboardContext::LEARNING => '/my-voelgoed/learning/',
            DashboardContext::PROFILE => '/my-voelgoed/profile/',
            default => '/my-voelgoed/',
        };
    }

    private function normalize_active_key(string $key): string
    {
        $key = strtolower(trim($key));
        $key = str_replace('_', '-', $key);

        return match ($key) {
            'memberarea', DashboardRegistry::MEMBER_AREA => DashboardRegistry::MEMBER_AREA,
            default => DashboardContext::normalize_view_key($key),
        };
    }

    private function is_active(string $key, string $active_key): bool
    {
        if ($key === DashboardContext::PROFILE && $active_key === DashboardContext::PAYMENT_METHODS) {
            return true;
        }

        return $key === $active_key;
    }
}
