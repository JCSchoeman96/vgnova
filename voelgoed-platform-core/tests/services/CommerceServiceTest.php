<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Config\AfrikaansLabels;
use Voelgoed\PlatformCore\Services\CommerceService;

final class VgpcOrderLineFixture
{
    public function __construct(
        private string $name,
        private int $quantity,
    ) {
    }

    public function get_name(): string
    {
        return $this->name;
    }

    public function get_quantity(): int
    {
        return $this->quantity;
    }
}

final class VgpcOrderFixture
{
    /**
     * @param array<int, VgpcOrderLineFixture> $items
     */
    public function __construct(
        private int $id,
        private mixed $user_id,
        private string $number,
        private string $status,
        private string $date,
        private string $total,
        private string $currency,
        private array $items,
        private string $view_url,
        private mixed $customer_id = null,
    ) {
    }

    public function get_id(): int
    {
        return $this->id;
    }

    public function get_user_id(): mixed
    {
        return $this->user_id;
    }

    public function get_customer_id(): mixed
    {
        return $this->customer_id;
    }

    public function get_order_number(): string
    {
        return $this->number;
    }

    public function get_status(): string
    {
        return $this->status;
    }

    public function get_date_created(): string
    {
        return $this->date;
    }

    public function get_total(): string
    {
        return $this->total;
    }

    public function get_currency(): string
    {
        return $this->currency;
    }

    /**
     * @return array<int, VgpcOrderLineFixture>
     */
    public function get_items(): array
    {
        return $this->items;
    }

    public function get_view_order_url(): string
    {
        return $this->view_url;
    }
}

final class VgpcCommerceProviderFixture
{
    /**
     * @param mixed $orders
     * @param array<string, string> $endpoints
     */
    public function __construct(
        private mixed $orders,
        private array $endpoints,
    ) {
    }

    /** @var array<string, mixed> */
    public array $requested_args = [];

    public function orders(array $args): mixed
    {
        $this->requested_args = $args;

        return $this->orders;
    }

    public function endpoint_url(string $endpoint, string $value = '', string $permalink = ''): string
    {
        return $this->endpoints[$endpoint] ?? '';
    }

    public function account_endpoint_url(string $endpoint): string
    {
        return $this->endpoints[$endpoint] ?? '';
    }
}

final class CommerceServiceTest
{
    public function test_populated_user_returns_only_owned_orders_and_native_endpoints(): void
    {
        assert_true(class_exists(CommerceService::class));

        $owned_order = new VgpcOrderFixture(
            501,
            42,
            '501',
            'completed',
            '2026-09-01 11:45:00',
            '249.00',
            'ZAR',
            [new VgpcOrderLineFixture('Voelgoed Joernaal', 2)],
            '/my-account/view-order/501/'
        );
        $other_order = new VgpcOrderFixture(
            502,
            99,
            '502',
            'processing',
            '2026-09-02',
            '99.00',
            'ZAR',
            [new VgpcOrderLineFixture('Ander klant se produk', 1)],
            '/my-account/view-order/502/'
        );
        $provider = new VgpcCommerceProviderFixture(
            [$owned_order, $other_order],
            [
                'orders'          => '/my-account/orders/',
                'downloads'       => '/my-account/downloads/',
                'edit-address'    => '/my-account/edit-address/',
                'payment-methods' => '/my-account/payment-methods/',
            ]
        );

        $card = (new CommerceService($provider))->card(42);

        assert_same('orders', $card['key']);
        assert_same(AfrikaansLabels::ORDERS, $card['label']);
        assert_same('active', $card['state']);
        assert_same(42, $provider->requested_args['customer_id']);
        assert_same(-1, $provider->requested_args['limit']);
        assert_same(1, count($card['items']));
        assert_same('501', $card['items'][0]['order_number']);
        assert_same('completed', $card['items'][0]['status']);
        assert_same('2026-09-01', $card['items'][0]['date']);
        assert_same('249.00', $card['items'][0]['total']);
        assert_same('ZAR', $card['items'][0]['currency']);
        assert_same('Voelgoed Joernaal', $card['items'][0]['line_items'][0]['name']);
        assert_same('/my-account/view-order/501/', $card['items'][0]['actions']['details']['url']);

        $action_urls = array_column($card['actions'], 'url');
        assert_true(in_array('/my-account/downloads/', $action_urls, true));
        assert_true(in_array('/my-account/orders/', $action_urls, true));
        assert_true(in_array('/my-account/edit-address/', $action_urls, true));
        assert_true(in_array('/my-account/payment-methods/', $action_urls, true));
        assert_false(in_array('library', array_column($card['actions'], 'key'), true));
    }

    public function test_empty_user_returns_a_valid_empty_card_with_woo_account_actions(): void
    {
        assert_true(class_exists(CommerceService::class));

        $provider = new VgpcCommerceProviderFixture([], [
            'orders'          => '/my-account/orders/',
            'downloads'       => '/my-account/downloads/',
            'edit-address'    => '/my-account/edit-address/',
            'payment-methods' => '/my-account/payment-methods/',
        ]);

        $card = (new CommerceService($provider))->card(42);

        assert_same('orders', $card['key']);
        assert_same(AfrikaansLabels::ORDERS, $card['label']);
        assert_same('empty', $card['state']);
        assert_same([], $card['items']);
        assert_true(count($card['actions']) >= 3);
    }

    public function test_prefers_woocommerce_customer_id_before_wordpress_user_id(): void
    {
        assert_true(class_exists(CommerceService::class));

        $canonical = new VgpcOrderFixture(
            507,
            99,
            '507',
            'completed',
            '2026-09-01',
            '99.00',
            'ZAR',
            [],
            '/order/507/',
            '42'
        );
        $conflicting = new VgpcOrderFixture(
            508,
            42,
            '508',
            'completed',
            '2026-09-01',
            '99.00',
            'ZAR',
            [],
            '/order/508/',
            '99'
        );
        $fallback = new VgpcOrderFixture(
            509,
            42,
            '509',
            'completed',
            '2026-09-01',
            '99.00',
            'ZAR',
            [],
            '/order/509/'
        );
        $provider = new VgpcCommerceProviderFixture([$canonical, $conflicting, $fallback], []);

        $card = (new CommerceService($provider))->card(42);

        assert_same(2, count($card['items']));
        assert_same('507', $card['items'][0]['order_number']);
        assert_same('509', $card['items'][1]['order_number']);
    }

    public function test_unknown_and_empty_statuses_use_a_generic_afrikaans_label(): void
    {
        assert_true(class_exists(CommerceService::class));

        $unknown = new VgpcOrderFixture(
            503,
            42,
            '503',
            'mystery-status',
            '2026-09-01',
            '99.00',
            'ZAR',
            [],
            '/my-account/view-order/503/'
        );
        $empty = new VgpcOrderFixture(
            504,
            42,
            '504',
            '',
            '2026-09-02',
            '99.00',
            'ZAR',
            [],
            '/my-account/view-order/504/'
        );
        $provider = new VgpcCommerceProviderFixture([$unknown, $empty], []);

        $card = (new CommerceService($provider))->card(42);

        assert_same('unknown', $card['items'][0]['status']);
        assert_same('Status onbekend', $card['items'][0]['status_label']);
        assert_same('unknown', $card['items'][1]['status']);
        assert_same('Status onbekend', $card['items'][1]['status_label']);
    }

    public function test_missing_woocommerce_returns_an_unavailable_card_without_throwing(): void
    {
        assert_true(class_exists(CommerceService::class));

        $card = (new CommerceService())->card(42);

        assert_same('orders', $card['key']);
        assert_same(AfrikaansLabels::ORDERS, $card['label']);
        assert_same('unavailable', $card['state']);
        assert_same([], $card['items']);
        assert_same([], $card['actions']);
    }

    public function test_invalid_woocommerce_orders_shape_returns_an_unavailable_card(): void
    {
        assert_true(class_exists(CommerceService::class));

        $provider = new VgpcCommerceProviderFixture(new stdClass(), []);
        $card = (new CommerceService($provider))->card(42);

        assert_same('unavailable', $card['state']);
        assert_same([], $card['items']);
        assert_same([], $card['actions']);
    }

    public function test_impossible_iso_order_dates_are_empty(): void
    {
        assert_true(class_exists(CommerceService::class));

        $orders = [];
        foreach (['2026-02-30', '2026-02-30abc', '2026-01-01T25:00', 'not-a-date'] as $offset => $date) {
            $id = 505 + $offset;
            $orders[] = new VgpcOrderFixture(
                $id,
                42,
                (string) $id,
                'completed',
                $date,
                '99.00',
                'ZAR',
                [],
                '/my-account/view-order/' . $id . '/'
            );
        }
        $provider = new VgpcCommerceProviderFixture($orders, []);

        $card = (new CommerceService($provider))->card(42);

        assert_same('', $card['items'][0]['date']);
        assert_same('', $card['items'][1]['date']);
        assert_same('', $card['items'][2]['date']);
        assert_same('', $card['items'][3]['date']);
    }

    public function test_rejects_a_requested_user_that_differs_from_the_authenticated_user(): void
    {
        assert_true(class_exists(CommerceService::class));

        $provider = new VgpcCommerceProviderFixture(
            [new VgpcOrderFixture(501, 99, '501', 'completed', '2026-09-01', '249.00', 'ZAR', [], '/order/501/')],
            ['orders' => '/my-account/orders/']
        );

        $card = (new CommerceService($provider))->card(99);

        assert_same('empty', $card['state']);
        assert_same([], $card['items']);
        assert_same([], $card['actions']);
        assert_same([], $provider->requested_args);
    }

    public function test_returns_an_empty_card_when_the_authenticated_user_is_logged_out(): void
    {
        assert_true(class_exists(CommerceService::class));

        vgpc_set_current_user_id(0);
        $provider = new VgpcCommerceProviderFixture([], []);

        try {
            $card = (new CommerceService($provider))->card(42);
        } finally {
            vgpc_set_current_user_id(42);
        }

        assert_same('empty', $card['state']);
        assert_same([], $card['items']);
        assert_same([], $card['actions']);
        assert_same([], $provider->requested_args);
    }

    public function test_throwing_order_iterator_returns_an_unavailable_card(): void
    {
        assert_true(class_exists(CommerceService::class));

        $provider = new VgpcCommerceProviderFixture(new class implements IteratorAggregate {
            public function getIterator(): Traversable
            {
                throw new RuntimeException('Provider iterator failed.');
            }
        }, []);

        $card = (new CommerceService($provider))->card(42);

        assert_same('unavailable', $card['state']);
        assert_same([], $card['items']);
        assert_same([], $card['actions']);
    }

    public function test_rejects_a_zero_requested_user_without_querying_woocommerce(): void
    {
        assert_true(class_exists(CommerceService::class));

        $provider = new VgpcCommerceProviderFixture(
            [new VgpcOrderFixture(501, 42, '501', 'completed', '2026-09-01', '249.00', 'ZAR', [], '/order/501/')],
            ['orders' => '/my-account/orders/']
        );

        $card = (new CommerceService($provider))->card(0);

        assert_same('empty', $card['state']);
        assert_same([], $card['items']);
        assert_same([], $provider->requested_args);
    }

    public function test_accepts_canonical_owner_ids_and_rejects_fractional_or_noncanonical_values(): void
    {
        assert_true(class_exists(CommerceService::class));

        $valid = new VgpcOrderFixture(
            506,
            '42',
            '506',
            'completed',
            '2026-09-01',
            '99.00',
            'ZAR',
            [],
            '/order/506/'
        );
        $invalid_owners = [42.5, '42.0', '4e1', ' 42', '042'];
        $orders = [$valid];
        foreach ($invalid_owners as $offset => $owner_id) {
            $orders[] = new VgpcOrderFixture(
                507 + $offset,
                $owner_id,
                (string) (507 + $offset),
                'completed',
                '2026-09-01',
                '99.00',
                'ZAR',
                [],
                '/order/' . (507 + $offset) . '/'
            );
        }

        $provider = new VgpcCommerceProviderFixture($orders, []);
        $card = (new CommerceService($provider))->card(42);

        assert_same(1, count($card['items']));
        assert_same('506', $card['items'][0]['order_number']);
    }
}
