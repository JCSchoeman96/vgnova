<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Services;

use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use Throwable;
use Traversable;
use Voelgoed\PlatformCore\Config\AfrikaansLabels;
use Voelgoed\PlatformCore\Contracts\DashboardAction;
use Voelgoed\PlatformCore\Contracts\DashboardCard;
use Voelgoed\PlatformCore\Contracts\DashboardServiceInterface;

/**
 * Reads authenticated-user tickets through Tickera's supported APIs.
 */
final class TicketService implements DashboardServiceInterface
{
    private mixed $provider;

    public function __construct(mixed $provider = null)
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
                'tickets',
                AfrikaansLabels::TICKETS,
                'empty',
                'Meld aan om jou kaartjies te sien.'
            );
        }

        try {
            $orders_result = $this->orders($user_id);
            if (! $orders_result['ok']) {
                return $this->unavailable();
            }

            $orders = $this->collection($orders_result['value']);
            if ($orders === null) {
                return $this->unavailable();
            }

            $upcoming = [];
            $past = [];
            foreach ($orders as $order) {
                $ownership = $this->order_ownership($order, $user_id);
                if ($ownership['error']) {
                    return $this->unavailable();
                }

                if (! $ownership['owned']) {
                    continue;
                }

                $order_id = $ownership['order_id'];
                $ticket_ids_result = $this->ticket_ids($order_id, $order);
                if (! $ticket_ids_result['ok']) {
                    return $this->unavailable();
                }

                $ticket_ids = $this->collection($ticket_ids_result['value']);
                if ($ticket_ids === null) {
                    return $this->unavailable();
                }

                foreach ($ticket_ids as $ticket_id_value) {
                    $ticket_id_value = is_object($ticket_id_value) || is_array($ticket_id_value)
                        ? $this->record_value($ticket_id_value, ['get_ticket_id', 'get_id'], ['ticket_id', 'id'])
                        : $ticket_id_value;
                    $ticket_id = $this->canonical_id($ticket_id_value);
                    if ($ticket_id === null) {
                        // A malformed ticket cannot be tied to the current user.
                        continue;
                    }

                    $ticket_result = $this->ticket($ticket_id);
                    if (! $ticket_result['ok'] || $ticket_result['value'] === null) {
                        continue;
                    }

                    $item = $this->item(
                        $ticket_result['value'],
                        $ticket_id,
                        $order,
                        $order_id,
                        $user_id,
                        $ownership['email']
                    );
                    if ($item === null) {
                        continue;
                    }

                    if ($item['event_timestamp'] >= time()) {
                        $upcoming[] = $item;
                    } else {
                        $past[] = $item;
                    }
                }
            }

            $items = array_merge(
                array_map(
                    static fn (array $item): array => $item + ['group' => 'upcoming'],
                    $upcoming
                ),
                array_map(
                    static fn (array $item): array => $item + ['group' => 'past'],
                    $past
                )
            );
            $total = count($upcoming) + count($past);
            if ($total === 0) {
                return DashboardCard::make(
                    'tickets',
                    AfrikaansLabels::TICKETS,
                    'empty',
                    'Geen komende kaartjies nie.',
                    [],
                    $items
                );
            }

            $summary = sprintf(
                '%d komende kaartjie%s, %d vorige kaartjie%s',
                count($upcoming),
                count($upcoming) === 1 ? '' : 's',
                count($past),
                count($past) === 1 ? '' : 's'
            );

            return DashboardCard::make(
                'tickets',
                AfrikaansLabels::TICKETS,
                'active',
                $summary,
                [],
                $items
            );
        } catch (Throwable) {
            return $this->unavailable();
        }
    }

    /**
     * @return array{ok: bool, value: mixed}
     */
    private function orders(int $user_id): array
    {
        $user = $this->current_user($user_id);
        if ($user === null) {
            return ['ok' => false, 'value' => null];
        }

        if ($this->provider !== null) {
            if (! is_object($this->provider)) {
                return ['ok' => false, 'value' => null];
            }

            $result = $this->provider_call(
                ['orders', 'user_orders', 'get_user_orders'],
                [[$user], [$user_id], [$user, $user_id]]
            );

            return $result['found'] && $result['ok']
                ? ['ok' => true, 'value' => $result['value']]
                : ['ok' => false, 'value' => null];
        }

        $class = '\\Tickera\\TC_Orders';
        if (! class_exists($class) || ! is_callable([$class, 'get_user_orders'])) {
            return ['ok' => false, 'value' => null];
        }

        try {
            return ['ok' => true, 'value' => $class::get_user_orders($user)];
        } catch (Throwable) {
            return ['ok' => false, 'value' => null];
        }
    }

    /**
     * @return array{error: bool, owned: bool, order_id: int, email: string}
     */
    private function order_ownership(mixed $order, int $user_id): array
    {
        if (! is_object($order) && ! is_array($order)) {
            return ['error' => true, 'owned' => false, 'order_id' => 0, 'email' => ''];
        }

        $order_id = $this->canonical_id($this->record_value($order, ['get_id', 'get_order_id'], ['ID', 'id', 'order_id']));
        if ($order_id === null) {
            return ['error' => true, 'owned' => false, 'order_id' => 0, 'email' => ''];
        }

        $owner = $this->record_value(
            $order,
            ['get_user_id', 'get_customer_id', 'get_author_id'],
            ['post_author', 'author_id', 'user_id', 'customer_id']
        );
        $owner_id = $owner === null ? null : $this->canonical_id($owner);
        $emails = [];
        $sources = [$order];
        $native_order = $this->native_order($order, $order_id);
        if ($native_order !== null && $native_order !== $order) {
            $sources[] = $native_order;
        }

        foreach ($sources as $source) {
            $source_email = strtolower(trim($this->scalar_string(
                $this->nested_record_value(
                    $source,
                    ['get_buyer_email', 'get_billing_email', 'get_email'],
                    ['buyer_email', 'billing_email', 'email'],
                    [
                        ['details', 'tc_cart_info', 'buyer_data', 'email'],
                        ['details', 'tc_cart_info', 'buyer_data', 'email_post_meta'],
                        ['details', 'tc_cart_info', 'buyer_data', 'user_email'],
                        ['details', 'buyer_data', 'email'],
                        ['details', 'buyer_email'],
                        ['details', 'email'],
                        ['tc_cart_info', 'buyer_data', 'email'],
                        ['tc_cart_info', 'buyer_data', 'email_post_meta'],
                    ]
                )
            )));
            if ($source_email !== '') {
                $emails[] = $source_email;
            }
        }
        $current_email = $this->current_user_email();
        $email_owned = false;
        foreach ($emails as $email) {
            if ($current_email !== '' && hash_equals($current_email, $email)) {
                $email_owned = true;
                break;
            }
        }

        return [
            'error'    => false,
            'owned'    => ($owner_id !== null && $owner_id === $user_id) || $email_owned,
            'order_id' => $order_id,
            'email'    => $current_email,
        ];
    }

    /**
     * @return array{ok: bool, value: mixed}
     */
    private function ticket_ids(int $order_id, mixed $order): array
    {
        if ($this->provider !== null && is_object($this->provider)) {
            $result = $this->provider_call(
                ['ticket_ids', 'get_tickets_ids', 'tickets_ids'],
                [[$order_id, 'publish'], [$order_id], [$order, 'publish']]
            );

            return $result['found'] && $result['ok']
                ? ['ok' => true, 'value' => $result['value']]
                : ['ok' => false, 'value' => null];
        }

        $class = '\\Tickera\\TC_Orders';
        if (! class_exists($class) || ! is_callable([$class, 'get_tickets_ids'])) {
            return ['ok' => false, 'value' => null];
        }

        try {
            return ['ok' => true, 'value' => $class::get_tickets_ids($order_id, 'publish')];
        } catch (Throwable) {
            return ['ok' => false, 'value' => null];
        }
    }

    /**
     * @return array{ok: bool, value: mixed}
     */
    private function ticket(int $ticket_id): array
    {
        if ($this->provider !== null && is_object($this->provider)) {
            $result = $this->provider_call(['ticket', 'ticket_instance', 'get_ticket'], [[$ticket_id]]);

            return $result['found'] && $result['ok']
                ? ['ok' => true, 'value' => $result['value']]
                : ['ok' => false, 'value' => null];
        }

        $class = '\\Tickera\\TC_Ticket_Instance';
        if (! class_exists($class)) {
            return ['ok' => false, 'value' => null];
        }

        try {
            return ['ok' => true, 'value' => new $class($ticket_id)];
        } catch (Throwable) {
            return ['ok' => false, 'value' => null];
        }
    }

    /**
     * @param string $ownership_email
     * @return array<string, mixed>|null
     */
    private function item(
        mixed $ticket,
        int $ticket_id,
        mixed $order,
        int $order_id,
        int $user_id,
        string $ownership_email
    ): ?array {
        if (! is_object($ticket) && ! is_array($ticket)) {
            return null;
        }

        $ticket_order_id = $this->nested_record_value(
            $ticket,
            ['get_order_id', 'get_tc_order_id'],
            ['order_id', 'tc_order_id'],
            [
                ['details', 'order_id'],
                ['details', 'tc_order_id'],
                ['details', 'post_parent'],
            ]
        );
        if ($ticket_order_id !== null) {
            $ticket_order = $this->canonical_id($ticket_order_id);
            if ($ticket_order === null || $ticket_order !== $order_id) {
                return null;
            }
        }

        $ticket_email = strtolower(trim($this->scalar_string(
            $this->nested_record_value(
                $ticket,
                ['get_owner_email', 'get_email'],
                ['owner_email', 'email'],
                [
                    ['details', 'owner_email'],
                    ['details', 'email'],
                ]
            )
        )));
        if ($ticket_email !== '' && $ownership_email !== '' && ! hash_equals($ownership_email, $ticket_email)) {
            return null;
        }

        $event_id = $this->canonical_id(
            $this->nested_record_value(
                $ticket,
                ['get_event_id', 'get_tc_event_id'],
                ['event_id', 'tc_event_id'],
                [
                    ['details', 'event_id'],
                    ['details', 'tc_event_id'],
                ]
            )
        );
        if ($event_id === null) {
            return null;
        }

        $event_result = $this->event($event_id);
        if (! $event_result['ok'] || $event_result['value'] === null) {
            return null;
        }

        $event = $event_result['value'];
        if (! is_object($event) && ! is_array($event)) {
            return null;
        }

        $event_title = $this->scalar_string(
            $this->nested_record_value(
                $event,
                ['get_name', 'get_title'],
                ['post_title', 'title', 'name'],
                [
                    ['details', 'post_title'],
                    ['details', 'title'],
                    ['details', 'name'],
                ]
            )
        );
        $event_date_raw = $this->nested_record_value(
            $event,
            [],
            ['event_date_time', 'start_datetime', 'event_date', 'start_date', 'date'],
            [
                ['details', 'event_date_time'],
                ['details', 'start_datetime'],
                ['details', 'event_date'],
                ['details', 'start_date'],
            ]
        );
        if ($event_date_raw === null || $this->scalar_string($event_date_raw) === '') {
            $event_date_raw = $this->record_value(
                $event,
                ['get_start_date', 'get_event_date', 'get_start_datetime', 'get_event_date_time', 'get_date'],
                ['start_date', 'event_date', 'start_datetime', 'event_date_time', 'date']
            );
        }
        $event_timestamp = $this->timestamp($event_date_raw);
        if ($event_title === '' || $event_timestamp === null) {
            return null;
        }

        $event_url = $this->safe_url($this->scalar_string(
            $this->nested_record_value(
                $event,
                ['get_permalink', 'get_url'],
                ['permalink', 'url'],
                [
                    ['details', 'permalink'],
                    ['details', 'url'],
                ]
            )
        ));
        if ($event_url === '' && function_exists('get_permalink')) {
            try {
                $event_url = $this->safe_url((string) get_permalink($event_id));
            } catch (Throwable) {
                $event_url = '';
            }
        }

        $status = $this->order_status($order, $order_id);
        if ($status === '') {
            $status = $this->status($this->scalar_string(
                $this->nested_record_value(
                    $ticket,
                    ['get_status', 'get_ticket_status'],
                    ['status', 'ticket_status'],
                    [
                        ['details', 'status'],
                        ['details', 'ticket_status'],
                    ]
                )
            ));
        }
        $actions = [];

        $download_url = $this->download_url($ticket_id);
        $download = $this->action('download', 'Laai kaartjie af', $download_url);
        if ($download !== null) {
            $actions['download'] = $download;

            // Tickera renders tc_ticket_qr_code_element inside this signed
            // ticket PDF. The QR action must use the same ownership-checked
            // URL rather than constructing a second ticket endpoint.
            $qr = $this->action('qr', 'Wys QR-kaartjie', $download_url);
            if ($qr !== null) {
                $actions['qr'] = $qr;
            }
        }

        $order_url = $this->order_url($order, $order_id);
        $order_action = $this->action('order', 'Sien bestelling', $order_url);
        if ($order_action !== null) {
            $actions['order'] = $order_action;
        }

        return [
            'ticket_id'       => (string) $ticket_id,
            'order_id'        => (string) $order_id,
            'event_id'        => (string) $event_id,
            'event_title'     => $event_title,
            'title'           => $event_title,
            'event_url'       => $event_url,
            'event_date'      => date('Y-m-d', $event_timestamp),
            'date'            => date('Y-m-d', $event_timestamp),
            'event_timestamp' => $event_timestamp,
            'status'          => $status,
            'status_label'    => $this->status_label($status),
            'download_url'    => $download_url,
            'order_url'       => $order_url,
            'actions'         => $actions,
        ];
    }

    /**
     * @return array{ok: bool, value: mixed}
     */
    private function event(int $event_id): array
    {
        if ($this->provider !== null && is_object($this->provider)) {
            $result = $this->provider_call(['event', 'get_event'], [[$event_id]]);

            return $result['found'] && $result['ok']
                ? ['ok' => true, 'value' => $result['value']]
                : ['ok' => false, 'value' => null];
        }

        $class = '\\Tickera\\TC_Event';
        if (! class_exists($class)) {
            return ['ok' => false, 'value' => null];
        }

        try {
            return ['ok' => true, 'value' => new $class($event_id)];
        } catch (Throwable) {
            return ['ok' => false, 'value' => null];
        }
    }

    private function download_url(int $ticket_id): string
    {
        if ($this->provider !== null && is_object($this->provider)) {
            $result = $this->provider_call(
                ['download_url', 'ticket_download_url', 'raw_ticket_download_link'],
                [[$ticket_id]]
            );
            if ($result['found'] && $result['ok']) {
                return $this->safe_url($this->scalar_string($result['value']));
            }

            return '';
        }

        if (! function_exists('tickera_get_raw_ticket_download_link')) {
            return '';
        }

        try {
            return $this->safe_url((string) \tickera_get_raw_ticket_download_link('', '', $ticket_id, true));
        } catch (Throwable) {
            return '';
        }
    }

    private function order_url(mixed $order, int $order_id): string
    {
        if ($this->provider !== null && is_object($this->provider)) {
            $result = $this->provider_call(['order_url', 'order_status_url'], [[$order], [$order_id, $order]]);
            if ($result['found'] && $result['ok']) {
                return $this->safe_url($this->scalar_string($result['value']));
            }

            return '';
        }

        $controller = $GLOBALS['tc'] ?? null;
        if (! is_object($controller) || ! method_exists($controller, 'tc_order_status_url')) {
            return '';
        }

        $native_order = $this->native_order($order, $order_id);
        $order_for_url = $native_order ?? $order;
        $order_key = $this->scalar_string(
            $this->nested_record_value(
                $order_for_url,
                ['get_order_key'],
                ['order_key', 'tc_order_date', 'post_name'],
                [
                    ['details', 'tc_order_date'],
                    ['details', 'order_key'],
                    ['details', 'tc_order_key'],
                ]
            )
        );
        $result = $this->invoke_method(
            $controller,
            'tc_order_status_url',
            [[$order_for_url, $order_key, '', false], [$order_id, $order_key, '', false], [$order_for_url, $order_key]]
        );

        if (! $result['ok'] && $native_order === null) {
            $native_order = $this->native_order($order, $order_id);
            if ($native_order !== null) {
                $result = $this->invoke_method(
                    $controller,
                    'tc_order_status_url',
                    [[$native_order, $order_key, '', false], [$native_order, $order_key]]
                );
            }
        }

        return $result['ok'] ? $this->safe_url($this->scalar_string($result['value'])) : '';
    }

    private function current_user(int $user_id): ?object
    {
        if (function_exists('wp_get_current_user')) {
            try {
                $user = wp_get_current_user();
                if (is_object($user) && (int) ($user->ID ?? 0) === $user_id) {
                    return $user;
                }
            } catch (Throwable) {
                return null;
            }
        }

        return (object) ['ID' => $user_id, 'user_email' => $this->current_user_email()];
    }

    private function current_user_email(): string
    {
        if (! function_exists('wp_get_current_user')) {
            return '';
        }

        try {
            $user = wp_get_current_user();
            return strtolower(trim($this->scalar_string(
                $this->record_value($user, [], ['user_email', 'email'])
            )));
        } catch (Throwable) {
            return '';
        }
    }

    private function native_order(mixed $order, int $order_id): ?object
    {
        if ($this->provider !== null) {
            return null;
        }

        $class = '\\Tickera\\TC_Order';
        if (! class_exists($class)) {
            return null;
        }

        if (is_object($order) && $order instanceof $class) {
            return $order;
        }

        try {
            return new $class($order_id);
        } catch (Throwable) {
            return null;
        }
    }

    private function timestamp(mixed $value): ?int
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_numeric($value)) {
            $timestamp = (int) $value;
            return $timestamp > 0 ? $timestamp : null;
        }

        $text = trim($this->scalar_string($value));
        if ($text === '' || preg_match('/^0{4}-0{2}-0{2}/', $text) === 1) {
            return null;
        }

        $timestamp = strtotime($text);

        return $timestamp === false ? null : $timestamp;
    }

    /**
     * @return array<int, mixed>|null
     */
    private function collection(mixed $value): ?array
    {
        if ($value instanceof Traversable) {
            try {
                $value = iterator_to_array($value, false);
            } catch (Throwable) {
                return null;
            }
        }

        if (is_array($value)) {
            return array_values($value);
        }

        if (is_object($value)) {
            foreach (['get_items', 'get_results', 'get_posts'] as $method) {
                if (! method_exists($value, $method)) {
                    continue;
                }

                try {
                    return $this->collection($value->{$method}());
                } catch (Throwable) {
                    return null;
                }
            }
        }

        return null;
    }

    private function record_value(mixed $source, array $methods, array $keys): mixed
    {
        if (is_object($source)) {
            foreach ($methods as $method) {
                if (! method_exists($source, $method)) {
                    continue;
                }

                try {
                    return $source->{$method}();
                } catch (Throwable) {
                    throw new \RuntimeException('Tickera value could not be read.');
                }
            }
        }

        foreach ($keys as $key) {
            if (is_array($source) && array_key_exists($key, $source)) {
                return $source[$key];
            }

            if (is_object($source) && (property_exists($source, $key) || isset($source->{$key}))) {
                try {
                    return $source->{$key};
                } catch (Throwable) {
                    throw new \RuntimeException('Tickera value could not be read.');
                }
            }
        }

        return null;
    }

    /**
     * Read a direct value first, then walk supported nested detail paths.
     * Tickera's native wrappers put event and ticket fields in `details`.
     *
     * @param array<int, string>              $methods
     * @param array<int, string>              $keys
     * @param array<int, array<int, string>>  $paths
     */
    private function nested_record_value(mixed $source, array $methods, array $keys, array $paths): mixed
    {
        $value = $this->record_value($source, $methods, $keys);
        if ($this->meaningful($value)) {
            return $value;
        }

        foreach ($paths as $path) {
            $current = $source;
            foreach ($path as $segment) {
                $current = $this->record_value($current, [], [$segment]);
                if ($current === null) {
                    continue 2;
                }
            }

            if ($this->meaningful($current)) {
                return $current;
            }
        }

        return $value;
    }

    private function meaningful(mixed $value): bool
    {
        return $value !== null && (! is_string($value) || trim($value) !== '');
    }

    private function canonical_id(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (! is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 && (string) $id === $value ? $id : null;
    }

    private function scalar_string(mixed $value): string
    {
        if ($value instanceof \Stringable) {
            return trim((string) $value);
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function safe_url(string $url): string
    {
        if ($url === '') {
            return '';
        }

        try {
            return DashboardAction::link('url', 'Skakel', $url)['url'];
        } catch (Throwable) {
            return '';
        }
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

    private function status(string $status): string
    {
        $status = strtolower(trim($status));

        return $status !== '' ? $status : 'unknown';
    }

    private function order_status(mixed $order, int $order_id): string
    {
        $sources = [$order];
        $native_order = $this->native_order($order, $order_id);
        if ($native_order !== null && $native_order !== $order) {
            $sources[] = $native_order;
        }

        foreach ($sources as $source) {
            $status = strtolower(trim($this->scalar_string(
                $this->nested_record_value(
                    $source,
                    ['get_order_status', 'get_status'],
                    ['order_status', 'status', 'post_status'],
                    [
                        ['details', 'order_status'],
                        ['details', 'post_status'],
                        ['details', 'status'],
                    ]
                )
            )));
            if ($this->is_order_status($status)) {
                return $status;
            }
        }

        return '';
    }

    private function is_order_status(string $status): bool
    {
        return in_array($status, [
            'order_received',
            'order_paid',
            'order_cancelled',
            'order_fraud',
            'order_refunded',
            'received',
            'paid',
            'cancelled',
            'fraud',
            'refunded',
        ], true);
    }

    private function status_label(string $status): string
    {
        return [
            'valid'           => 'Geldig',
            'used'            => 'Gebruik',
            'redeemed'        => 'Gebruik',
            'cancelled'       => 'Gekanselleer',
            'void'            => 'Ongeldig',
            'expired'         => 'Verstreke',
            'order_received'  => 'Ontvang',
            'received'        => 'Ontvang',
            'order_paid'      => 'Betaal',
            'paid'            => 'Betaal',
            'order_cancelled' => 'Gekanselleer',
            'order_fraud'     => 'Bedrog',
            'fraud'           => 'Bedrog',
            'order_refunded'  => 'Terugbetaal',
            'refunded'        => 'Terugbetaal',
            'unknown'         => 'Status onbekend',
        ][$status] ?? 'Status onbekend';
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailable(): array
    {
        return DashboardCard::empty(
            'tickets',
            AfrikaansLabels::TICKETS,
            'unavailable',
            'Kaartjies is tans nie beskikbaar nie.'
        );
    }

    private function is_authenticated_user(int $user_id): bool
    {
        if ($user_id <= 0 || ! function_exists('get_current_user_id')) {
            return false;
        }

        try {
            return (int) get_current_user_id() === $user_id;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param array<int, string> $methods
     * @param array<int, array<int, mixed>> $variants
     * @return array{found: bool, ok: bool, value: mixed}
     */
    private function provider_call(array $methods, array $variants): array
    {
        foreach ($methods as $method) {
            if (! is_object($this->provider) || ! method_exists($this->provider, $method)) {
                continue;
            }

            return $this->invoke_method($this->provider, $method, $variants);
        }

        return ['found' => false, 'ok' => false, 'value' => null];
    }

    /**
     * @param array<int, array<int, mixed>> $variants
     * @return array{found: bool, ok: bool, value: mixed}
     */
    private function invoke_method(object $object, string $method, array $variants): array
    {
        try {
            $reflection = new ReflectionMethod($object, $method);
        } catch (Throwable) {
            return ['found' => true, 'ok' => false, 'value' => null];
        }

        foreach ($variants as $arguments) {
            if (! $this->accepts_arguments($reflection, count($arguments)) || ! $this->arguments_match($reflection, $arguments)) {
                continue;
            }

            try {
                if ($reflection->isStatic()) {
                    $class = $reflection->getDeclaringClass()->getName();

                    return ['found' => true, 'ok' => true, 'value' => $class::{$method}(...$arguments)];
                }

                return ['found' => true, 'ok' => true, 'value' => $object->{$method}(...$arguments)];
            } catch (Throwable) {
                return ['found' => true, 'ok' => false, 'value' => null];
            }
        }

        return ['found' => true, 'ok' => false, 'value' => null];
    }

    private function accepts_arguments(ReflectionFunctionAbstract $reflection, int $count): bool
    {
        $required = $reflection->getNumberOfRequiredParameters();
        $maximum = $reflection->isVariadic() ? PHP_INT_MAX : $reflection->getNumberOfParameters();

        return $count >= $required && $count <= $maximum;
    }

    /**
     * @param array<int, mixed> $arguments
     */
    private function arguments_match(ReflectionFunctionAbstract $reflection, array $arguments): bool
    {
        foreach ($reflection->getParameters() as $index => $parameter) {
            if (! array_key_exists($index, $arguments)) {
                continue;
            }

            $type = $parameter->getType();
            if ($type === null || $type->allowsNull() && $arguments[$index] === null) {
                continue;
            }

            $types = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];
            $matches = false;
            foreach ($types as $candidate) {
                if (! $candidate instanceof ReflectionNamedType) {
                    continue;
                }

                $name = $candidate->getName();
                $value = $arguments[$index];
                $matches = match ($name) {
                    'mixed'    => true,
                    'array'    => is_array($value),
                    'bool'     => is_bool($value),
                    'float'    => is_float($value),
                    'int'      => is_int($value),
                    'string'   => is_string($value),
                    'object'   => is_object($value),
                    'callable' => is_callable($value),
                    'iterable' => is_iterable($value),
                    default    => is_object($value) && $value instanceof $name,
                };
                if ($matches) {
                    break;
                }
            }

            if (! $matches) {
                return false;
            }
        }

        return true;
    }
}
