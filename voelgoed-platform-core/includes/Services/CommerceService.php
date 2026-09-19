<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Services;

use Throwable;
use Voelgoed\PlatformCore\Config\AfrikaansLabels;
use Voelgoed\PlatformCore\Contracts\DashboardAction;
use Voelgoed\PlatformCore\Contracts\DashboardCard;
use Voelgoed\PlatformCore\Contracts\DashboardServiceInterface;

/**
 * Reads customer-owned WooCommerce orders and native account endpoints.
 */
final class CommerceService implements DashboardServiceInterface
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
        if ($user_id <= 0) {
            return DashboardCard::empty(
                'orders',
                AfrikaansLabels::ORDERS,
                'empty',
                'Meld aan om jou bestellings te sien.'
            );
        }

        $orders = $this->orders($user_id);
        if ($orders === null) {
            return DashboardCard::empty(
                'orders',
                AfrikaansLabels::ORDERS,
                'unavailable',
                'Bestellings is tans nie beskikbaar nie.'
            );
        }

        $items = [];
        foreach ($orders as $order) {
            $item = $this->item($order, $user_id);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        $actions = $this->account_actions();
        $state = $items === [] ? 'empty' : 'active';
        $summary = $items === []
            ? 'Geen bestellings nie.'
            : sprintf('%d bestelling%s', count($items), count($items) === 1 ? '' : 's');

        return DashboardCard::make(
            'orders',
            AfrikaansLabels::ORDERS,
            $state,
            $summary,
            $actions,
            $items
        );
    }

    /**
     * @return array<int, mixed>|null
     */
    private function orders(int $user_id): ?array
    {
        $args = [
            'customer_id' => $user_id,
            'status'      => 'any',
            'limit'       => -1,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ];

        try {
            if ($this->provider !== null) {
                if (! method_exists($this->provider, 'orders')) {
                    return null;
                }

                $orders = $this->provider->orders($args);
            } elseif (function_exists('wc_get_orders')) {
                $orders = \wc_get_orders($args);
            } else {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        if ($orders instanceof \Traversable) {
            $orders = iterator_to_array($orders, false);
        }

        return is_array($orders) ? array_values($orders) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function item(mixed $order, int $user_id): ?array
    {
        if (! is_object($order)) {
            return null;
        }

        $owner_id = $this->owner_id($order);
        if ($owner_id === null || $owner_id !== $user_id) {
            return null;
        }

        $order_id = $this->method_string($order, 'get_id');
        $order_number = $this->method_string($order, 'get_order_number');
        if ($order_number === '') {
            $order_number = $order_id;
        }

        $status = strtolower($this->method_string($order, 'get_status'));
        if ($status === '') {
            return null;
        }

        $actions = [];
        $details_url = $this->method_string($order, 'get_view_order_url');
        if ($details_url === '' && $order_id !== '') {
            $details_url = $this->endpoint('view-order', $order_id);
        }
        $details = $this->action('details', 'Sien bestelling', $details_url);
        if ($details !== null) {
            $actions['details'] = $details;
        }

        return [
            'order_id'     => $order_id,
            'order_number' => $order_number,
            'status'       => $status,
            'status_label' => $this->status_label($status),
            'date'         => $this->date_value($this->method_value($order, 'get_date_created')),
            'total'        => $this->method_string($order, 'get_total'),
            'currency'     => $this->method_string($order, 'get_currency'),
            'line_items'   => $this->line_items($order),
            'actions'      => $actions,
        ];
    }

    private function owner_id(object $order): ?int
    {
        $owner = $this->method_value($order, 'get_user_id');
        if ($owner === null && isset($order->user_id) && is_scalar($order->user_id)) {
            $owner = $order->user_id;
        }

        return is_numeric($owner) ? (int) $owner : null;
    }

    /**
     * @return array<int, array{name: string, quantity: string}>
     */
    private function line_items(object $order): array
    {
        $items = $this->method_value($order, 'get_items');
        if ($items instanceof \Traversable) {
            $items = iterator_to_array($items, false);
        }

        if (! is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            $name = '';
            $quantity = '';
            if (is_object($item)) {
                $name = $this->method_string($item, 'get_name');
                $quantity = $this->method_string($item, 'get_quantity');
            } elseif (is_array($item)) {
                $name = isset($item['name']) && is_scalar($item['name']) ? trim((string) $item['name']) : '';
                $quantity = isset($item['quantity']) && is_scalar($item['quantity']) ? trim((string) $item['quantity']) : '';
            }

            if ($name !== '') {
                $normalized[] = [
                    'name'     => $name,
                    'quantity' => $quantity,
                ];
            }
        }

        return $normalized;
    }

    /**
     * @return array<int, array{key: string, label: string, url: string}>
     */
    private function account_actions(): array
    {
        $definitions = [
            ['key' => 'orders', 'label' => 'Sien bestellings', 'endpoint' => 'orders'],
            ['key' => 'downloads', 'label' => 'Laai lêers af', 'endpoint' => 'downloads'],
            ['key' => 'addresses', 'label' => 'Wysig adresse', 'endpoint' => 'edit-address'],
            ['key' => 'payment-methods', 'label' => AfrikaansLabels::PAYMENT_METHODS, 'endpoint' => 'payment-methods'],
        ];

        $actions = [];
        foreach ($definitions as $definition) {
            $action = $this->action(
                $definition['key'],
                $definition['label'],
                $this->endpoint($definition['endpoint'])
            );
            if ($action !== null) {
                $actions[] = $action;
            }
        }

        return $actions;
    }

    private function endpoint(string $endpoint, string $value = ''): string
    {
        try {
            if ($this->provider !== null) {
                if ($value === '' && method_exists($this->provider, 'account_endpoint_url')) {
                    return $this->scalar_string($this->provider->account_endpoint_url($endpoint));
                }

                if (method_exists($this->provider, 'endpoint_url')) {
                    return $this->scalar_string($this->provider->endpoint_url($endpoint, $value, ''));
                }

                return '';
            }

            if ($value === '' && function_exists('wc_get_account_endpoint_url')) {
                return $this->scalar_string(\wc_get_account_endpoint_url($endpoint));
            }

            if (function_exists('wc_get_endpoint_url') && function_exists('wc_get_page_permalink')) {
                return $this->scalar_string(
                    \wc_get_endpoint_url(
                        $endpoint,
                        $value,
                        \wc_get_page_permalink('myaccount')
                    )
                );
            }
        } catch (Throwable) {
            return '';
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

    private function method_string(object $source, string $method): string
    {
        return $this->scalar_string($this->method_value($source, $method));
    }

    private function method_value(object $source, string $method): mixed
    {
        if (! method_exists($source, $method)) {
            return null;
        }

        try {
            return $source->{$method}();
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

        return $this->scalar_string($value);
    }

    private function status_label(string $status): string
    {
        return [
            'pending'    => 'Hangende',
            'processing' => 'Word verwerk',
            'on-hold'    => 'Op wag',
            'completed'  => 'Voltooi',
            'cancelled'  => 'Gekanselleer',
            'refunded'   => 'Terugbetaal',
            'failed'     => 'Misluk',
        ][$status] ?? ucfirst(str_replace('-', ' ', $status));
    }
}
