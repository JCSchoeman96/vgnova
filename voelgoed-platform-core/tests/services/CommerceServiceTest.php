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
        private int $user_id,
        private string $number,
        private string $status,
        private string $date,
        private string $total,
        private string $currency,
        private array $items,
        private string $view_url,
    ) {
    }

    public function get_id(): int
    {
        return $this->id;
    }

    public function get_user_id(): int
    {
        return $this->user_id;
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
     * @param array<int, object> $orders
     * @param array<string, string> $endpoints
     */
    public function __construct(
        private array $orders,
        private array $endpoints,
    ) {
    }

    /** @var array<string, mixed> */
    public array $requested_args = [];

    /**
     * @return array<int, object>
     */
    public function orders(array $args): array
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
        if (! class_exists(CommerceService::class)) {
            assert_true(false, 'CommerceService should be available.');

            return;
        }

        $owned_order = new VgpcOrderFixture(
            501,
            42,
            '501',
            'completed',
            '2026-09-01',
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
        assert_true(in_array('/my-account/edit-address/', $action_urls, true));
        assert_true(in_array('/my-account/payment-methods/', $action_urls, true));
        assert_false(in_array('library', array_column($card['actions'], 'key'), true));
    }

    public function test_empty_user_returns_a_valid_empty_card_with_woo_account_actions(): void
    {
        if (! class_exists(CommerceService::class)) {
            return;
        }

        $provider = new VgpcCommerceProviderFixture([], [
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

    public function test_missing_woocommerce_returns_an_unavailable_card_without_throwing(): void
    {
        if (! class_exists(CommerceService::class)) {
            return;
        }

        $card = (new CommerceService())->card(42);

        assert_same('orders', $card['key']);
        assert_same(AfrikaansLabels::ORDERS, $card['label']);
        assert_same('unavailable', $card['state']);
        assert_same([], $card['items']);
        assert_same([], $card['actions']);
    }
}
