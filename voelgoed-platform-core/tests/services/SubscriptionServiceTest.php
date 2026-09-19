<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Config\AfrikaansLabels;
use Voelgoed\PlatformCore\Services\SubscriptionService;

final class VgpcSubscriptionLineFixture
{
    public function __construct(private string $name)
    {
    }

    public function get_name(): string
    {
        return $this->name;
    }
}

final class VgpcSubscriptionFixture
{
    /**
     * @param array<int, VgpcSubscriptionLineFixture> $items
     */
    public function __construct(
        private int $id,
        private int $user_id,
        private string $status,
        private array $items,
        private string $next_payment,
        private string $payment_method,
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

    public function get_status(): string
    {
        return $this->status;
    }

    /**
     * @return array<int, VgpcSubscriptionLineFixture>
     */
    public function get_items(): array
    {
        return $this->items;
    }

    public function get_date_to_display(string $date_type = 'next_payment'): string
    {
        return $date_type === 'next_payment' ? $this->next_payment : '';
    }

    public function get_date(string $date_type): string
    {
        return $this->get_date_to_display($date_type);
    }

    public function get_payment_method_to_display(string $context = 'admin'): string
    {
        return $this->payment_method;
    }

    public function get_view_order_url(): string
    {
        return $this->view_url;
    }
}

final class VgpcSubscriptionProviderFixture
{
    /**
     * @param array<int, object> $subscriptions
     * @param array<int, array<string, array<string, string>>> $actions
     */
    public function __construct(
        private array $subscriptions,
        private array $actions,
    ) {
    }

    public int $requested_user_id = 0;

    /**
     * @return array<int, object>
     */
    public function subscriptions(int $user_id): array
    {
        $this->requested_user_id = $user_id;

        return $this->subscriptions;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function actions(object $subscription, int $user_id): array
    {
        return $this->actions[$subscription->get_id()] ?? [];
    }
}

final class SubscriptionServiceTest
{
    public function test_populated_user_returns_all_subscription_statuses_and_native_actions(): void
    {
        assert_true(class_exists(SubscriptionService::class));

        $active = new VgpcSubscriptionFixture(
            101,
            42,
            'active',
            [new VgpcSubscriptionLineFixture('Voelgoedvriendinne Silwer')],
            '2026-10-01 09:30:00',
            'Via PayFast',
            '/my-account/view-subscription/101/'
        );
        $standalone = new VgpcSubscriptionFixture(
            102,
            42,
            'cancelled',
            [new VgpcSubscriptionLineFixture('Onafhanklike nuusbrief')],
            '',
            'Manual Renewal',
            '/my-account/view-subscription/102/'
        );
        $provider = new VgpcSubscriptionProviderFixture(
            [$active, $standalone],
            [
                101 => [
                    'cancel' => [
                        'url'  => '/?subscription_id=101&change_subscription_to=cancelled',
                        'name' => 'Cancel',
                    ],
                ],
                102 => [
                    'resubscribe' => [
                        'url'  => '/?resubscribe=102',
                        'name' => 'Resubscribe',
                    ],
                ],
            ]
        );

        $card = (new SubscriptionService($provider))->card(42);

        assert_same('subscriptions', $card['key']);
        assert_same(AfrikaansLabels::SUBSCRIPTIONS, $card['label']);
        assert_same('active', $card['state']);
        assert_same(42, $provider->requested_user_id);
        assert_same(2, count($card['items']));
        assert_same('Voelgoedvriendinne Silwer', $card['items'][0]['product_name']);
        assert_same('active', $card['items'][0]['status']);
        assert_same('2026-10-01', $card['items'][0]['next_payment_date']);
        assert_same('Via PayFast', $card['items'][0]['payment_method']);
        assert_same('/my-account/view-subscription/101/', $card['items'][0]['actions']['manage']['url']);
        assert_same('/?subscription_id=101&change_subscription_to=cancelled', $card['items'][0]['actions']['cancel']['url']);
        assert_same('cancelled', $card['items'][1]['status']);
        assert_same('/?resubscribe=102', $card['items'][1]['actions']['renew']['url']);
    }

    public function test_empty_user_returns_a_valid_empty_card(): void
    {
        assert_true(class_exists(SubscriptionService::class));

        $card = (new SubscriptionService(new VgpcSubscriptionProviderFixture([], [])))->card(42);

        assert_same('subscriptions', $card['key']);
        assert_same(AfrikaansLabels::SUBSCRIPTIONS, $card['label']);
        assert_same('empty', $card['state']);
        assert_same([], $card['items']);
    }

    public function test_missing_woo_subscriptions_returns_an_unavailable_card_without_throwing(): void
    {
        assert_true(class_exists(SubscriptionService::class));

        $card = (new SubscriptionService())->card(42);

        assert_same('subscriptions', $card['key']);
        assert_same(AfrikaansLabels::SUBSCRIPTIONS, $card['label']);
        assert_same('unavailable', $card['state']);
        assert_same([], $card['items']);
        assert_same([], $card['actions']);
    }

    public function test_rejects_a_requested_user_that_differs_from_the_authenticated_user(): void
    {
        assert_true(class_exists(SubscriptionService::class));

        $provider = new VgpcSubscriptionProviderFixture(
            [new VgpcSubscriptionFixture(101, 99, 'active', [], '2026-10-01', 'PayFast', '/subscription/101/')],
            []
        );

        $card = (new SubscriptionService($provider))->card(99);

        assert_same('empty', $card['state']);
        assert_same([], $card['items']);
        assert_same([], $card['actions']);
        assert_same(0, $provider->requested_user_id);
    }

    public function test_returns_an_empty_card_when_the_authenticated_user_is_logged_out(): void
    {
        assert_true(class_exists(SubscriptionService::class));

        vgpc_set_current_user_id(0);
        $provider = new VgpcSubscriptionProviderFixture([], []);

        try {
            $card = (new SubscriptionService($provider))->card(42);
        } finally {
            vgpc_set_current_user_id(42);
        }

        assert_same('empty', $card['state']);
        assert_same([], $card['items']);
        assert_same([], $card['actions']);
        assert_same(0, $provider->requested_user_id);
    }

    public function test_rejects_a_zero_requested_user_without_querying_woo_subscriptions(): void
    {
        assert_true(class_exists(SubscriptionService::class));

        $provider = new VgpcSubscriptionProviderFixture(
            [new VgpcSubscriptionFixture(101, 42, 'active', [], '2026-10-01', 'PayFast', '/subscription/101/')],
            []
        );

        $card = (new SubscriptionService($provider))->card(0);

        assert_same('empty', $card['state']);
        assert_same([], $card['items']);
        assert_same(0, $provider->requested_user_id);
    }
}
