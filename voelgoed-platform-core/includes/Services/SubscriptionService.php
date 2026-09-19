<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Services;

use Throwable;
use Voelgoed\PlatformCore\Config\AfrikaansLabels;
use Voelgoed\PlatformCore\Contracts\DashboardAction;
use Voelgoed\PlatformCore\Contracts\DashboardCard;
use Voelgoed\PlatformCore\Contracts\DashboardServiceInterface;

/**
 * Reads all Woo Subscriptions owned by the authenticated WordPress user.
 */
final class SubscriptionService implements DashboardServiceInterface
{
    private ?object $provider;

    public function __construct(?object $provider = null)
    {
        $this->provider = $provider;
    }

    /**
     * @return array<string, mixed>
     */
    public function card(int $user_id): array
    {
        if (! $this->is_authenticated_user($user_id)) {
            return DashboardCard::empty(
                'subscriptions',
                AfrikaansLabels::SUBSCRIPTIONS,
                'empty',
                'Meld aan om jou subskripsies te sien.'
            );
        }

        $subscriptions = $this->subscriptions($user_id);
        if ($subscriptions === null) {
            return DashboardCard::empty(
                'subscriptions',
                AfrikaansLabels::SUBSCRIPTIONS,
                'unavailable',
                'Subskripsies is tans nie beskikbaar nie.'
            );
        }

        $items = [];
        try {
            foreach ($subscriptions as $subscription) {
                $item = $this->item($subscription, $user_id);
                if ($item !== null) {
                    $items[] = $item;
                }
            }
        } catch (Throwable) {
            return DashboardCard::empty(
                'subscriptions',
                AfrikaansLabels::SUBSCRIPTIONS,
                'unavailable',
                'Subskripsies is tans nie beskikbaar nie.'
            );
        }

        if ($items === []) {
            return DashboardCard::empty(
                'subscriptions',
                AfrikaansLabels::SUBSCRIPTIONS,
                'empty',
                'Geen subskripsies nie.'
            );
        }

        $actions = [];
        $manage_url = '/my-voelgoed/subscriptions/';
        $manage_action = $this->action('manage', 'Bestuur subskripsies', $manage_url);
        if ($manage_action !== null) {
            $actions[] = $manage_action;
        }

        return DashboardCard::make(
            'subscriptions',
            AfrikaansLabels::SUBSCRIPTIONS,
            'active',
            sprintf('%d subskripsie%s', count($items), count($items) === 1 ? '' : 's'),
            $actions,
            $items
        );
    }

    /**
     * @return array<int, mixed>|null
     */
    private function subscriptions(int $user_id): ?array
    {
        try {
            if ($this->provider !== null) {
                if (! method_exists($this->provider, 'subscriptions')) {
                    return null;
                }

                $subscriptions = $this->provider->subscriptions($user_id);
            } elseif (function_exists('wcs_get_users_subscriptions')) {
                $subscriptions = \wcs_get_users_subscriptions($user_id);
            } else {
                return null;
            }
            if ($subscriptions instanceof \Traversable) {
                $subscriptions = iterator_to_array($subscriptions, false);
            }

            return is_array($subscriptions) ? array_values($subscriptions) : [];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function item(mixed $subscription, int $user_id): ?array
    {
        if (! is_object($subscription)) {
            return null;
        }

        $owner_id = $this->owner_id($subscription);
        if ($owner_id === null || $owner_id !== $user_id) {
            return null;
        }

        $status = $this->method_string($subscription, 'get_status');
        if ($status === '') {
            return null;
        }
        $status = strtolower($status);

        $subscription_id = $this->method_string($subscription, 'get_id');
        $product_name = $this->product_name($subscription);
        $next_payment = $this->next_payment($subscription);
        $payment_method = $this->payment_method($subscription);
        $actions = $this->actions($subscription, $user_id, $subscription_id, $status);

        return [
            'subscription_id'    => $subscription_id,
            'product_name'       => $product_name,
            'status'             => $status,
            'status_label'       => $this->status_label($status),
            'next_payment_date'  => $next_payment,
            'next_payment'       => $next_payment,
            'payment_method'     => $payment_method,
            'actions'            => $actions,
            'manage_url'         => $actions['manage']['url'] ?? '',
            'renew_url'          => $actions['renew']['url'] ?? '',
            'cancel_url'         => $actions['cancel']['url'] ?? '',
        ];
    }

    private function owner_id(object $subscription): ?int
    {
        $owner = $this->method_value($subscription, 'get_user_id');
        if ($owner === null && isset($subscription->user_id) && is_scalar($subscription->user_id)) {
            $owner = $subscription->user_id;
        }

        return is_numeric($owner) ? (int) $owner : null;
    }

    private function product_name(object $subscription): string
    {
        try {
            $items = $this->method_value($subscription, 'get_items');
            if ($items instanceof \Traversable) {
                $items = iterator_to_array($items, false);
            }
        } catch (Throwable) {
            throw new \RuntimeException('Subscription items could not be read.');
        }

        $names = [];
        if (is_array($items)) {
            foreach ($items as $item) {
                $name = '';
                if (is_object($item) && method_exists($item, 'get_name')) {
                    $name = $this->method_string($item, 'get_name');
                } elseif (is_array($item) && isset($item['name']) && is_scalar($item['name'])) {
                    $name = trim((string) $item['name']);
                }

                if ($name !== '') {
                    $names[] = $name;
                }
            }
        }

        if ($names !== []) {
            return implode(', ', array_values(array_unique($names)));
        }

        return $this->method_string($subscription, 'get_name');
    }

    private function next_payment(object $subscription): string
    {
        $raw_date = $this->method_value($subscription, 'get_date', ['next_payment']);
        if ($raw_date !== null && $raw_date !== false) {
            $normalized = $this->date_value($raw_date);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        $formatted = '';
        if (method_exists($subscription, 'get_date_to_display')) {
            try {
                $formatted = $this->scalar_string($subscription->get_date_to_display('next_payment'));
            } catch (Throwable) {
                try {
                    $formatted = $this->scalar_string($subscription->get_date_to_display());
                } catch (Throwable) {
                    $formatted = '';
                }
            }
        }

        if ($formatted !== '') {
            return $this->display_date_value($formatted);
        }

        return '';
    }

    private function payment_method(object $subscription): string
    {
        if (method_exists($subscription, 'get_payment_method_to_display')) {
            try {
                return $this->scalar_string($subscription->get_payment_method_to_display('customer'));
            } catch (Throwable) {
                try {
                    return $this->scalar_string($subscription->get_payment_method_to_display());
                } catch (Throwable) {
                    return '';
                }
            }
        }

        return $this->method_string($subscription, 'get_payment_method_title');
    }

    /**
     * @return array<string, array{key: string, label: string, url: string}>
     */
    private function actions(object $subscription, int $user_id, string $subscription_id, string $status): array
    {
        $actions = [];

        $manage_url = $this->method_string($subscription, 'get_view_order_url');
        if ($manage_url === '' && $subscription_id !== '') {
            $manage_url = $this->subscription_url($subscription_id);
        }

        $manage = $this->action('manage', 'Bestuur subskripsie', $manage_url);
        if ($manage !== null) {
            $actions['manage'] = $manage;
        }

        $native_actions = $this->native_actions($subscription, $user_id);
        foreach ($native_actions as $key => $native_action) {
            if (! is_array($native_action)) {
                continue;
            }

            $url = isset($native_action['url']) && is_scalar($native_action['url'])
                ? trim((string) $native_action['url'])
                : '';
            if ($url === '') {
                continue;
            }

            if ($key === 'cancel') {
                $action = $this->action('cancel', 'Kanselleer subskripsie', $url);
                if ($action !== null) {
                    $actions['cancel'] = $action;
                }
            } elseif (in_array($key, ['resubscribe', 'renew', 'reactivate'], true)) {
                $action = $this->action('renew', 'Hernu subskripsie', $url);
                if ($action !== null) {
                    $actions['renew'] = $action;
                }
            }
        }

        return $actions;
    }

    /**
     * @return array<string, mixed>
     */
    private function native_actions(object $subscription, int $user_id): array
    {
        try {
            if ($this->provider !== null && method_exists($this->provider, 'actions')) {
                $actions = $this->provider->actions($subscription, $user_id);

                return is_array($actions) ? $actions : [];
            }

            if (function_exists('wcs_get_all_user_actions_for_subscription')) {
                $actions = \wcs_get_all_user_actions_for_subscription($subscription, $user_id);

                return is_array($actions) ? $actions : [];
            }
        } catch (Throwable) {
            return [];
        }

        return [];
    }

    private function subscription_url(string $subscription_id): string
    {
        if (
            function_exists('wc_get_endpoint_url')
            && function_exists('wc_get_page_permalink')
        ) {
            try {
                return $this->scalar_string(
                    \wc_get_endpoint_url(
                        'view-subscription',
                        $subscription_id,
                        \wc_get_page_permalink('myaccount')
                    )
                );
            } catch (Throwable) {
                return '';
            }
        }

        return '';
    }

    /**
     * @return array{key: string, label: string, url: string}|null
     */
    private function action(string $key, string $label, string $url): ?array
    {
        if ($url === '') {
            return null;
        }

        try {
            return DashboardAction::link($key, $label, $url);
        } catch (Throwable) {
            return null;
        }
    }

    private function method_string(object $source, string $method, array $arguments = []): string
    {
        return $this->scalar_string($this->method_value($source, $method, $arguments));
    }

    private function method_value(object $source, string $method, array $arguments = []): mixed
    {
        if (! method_exists($source, $method)) {
            return null;
        }

        try {
            return $source->{$method}(...$arguments);
        } catch (Throwable) {
            return null;
        }
    }

    private function scalar_string(mixed $value): string
    {
        if ($value instanceof \Stringable) {
            return trim((string) $value);
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function date_value(mixed $value): string
    {
        if ($value === null || $value === false) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_object($value) && method_exists($value, 'date')) {
            try {
                return $this->scalar_string($value->date('Y-m-d'));
            } catch (Throwable) {
                return '';
            }
        }

        if (is_numeric($value)) {
            if ((float) $value <= 0) {
                return '';
            }

            return function_exists('wp_date')
                ? (string) \wp_date('Y-m-d', (int) $value)
                : date('Y-m-d', (int) $value);
        }

        $text = $this->scalar_string($value);
        if ($text === '' || in_array($text, ['0000-00-00', '0000-00-00 00:00:00'], true)) {
            return '';
        }

        $timestamp = strtotime($text);
        if ($timestamp !== false) {
            return function_exists('wp_date')
                ? (string) \wp_date('Y-m-d', $timestamp)
                : date('Y-m-d', $timestamp);
        }

        return $text;
    }

    private function display_date_value(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match(
            '/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})?)?$/',
            $value
        ) === 1) {
            return $this->date_value($value);
        }

        return $value;
    }

    private function is_authenticated_user(int $user_id): bool
    {
        if ($user_id <= 0 || ! function_exists('get_current_user_id')) {
            return false;
        }

        try {
            $current_user_id = (int) \get_current_user_id();
        } catch (Throwable) {
            return false;
        }

        return $current_user_id > 0 && $current_user_id === $user_id;
    }

    private function status_label(string $status): string
    {
        return [
            'active'         => 'Aktief',
            'pending'        => 'Hangende',
            'on-hold'        => 'Op wag',
            'failed'         => 'Misluk',
            'paused'         => 'Onderbreek',
            'cancelled'      => 'Gekanselleer',
            'pending-cancel' => 'Kansellasie hangende',
            'expired'        => 'Verstreke',
            'completed'      => 'Voltooi',
        ][$status] ?? ucfirst(str_replace('-', ' ', $status));
    }
}
