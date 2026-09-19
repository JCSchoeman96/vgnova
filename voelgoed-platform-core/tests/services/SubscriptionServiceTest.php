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
        private mixed $user_id,
        private string $status,
        private array $items,
        private string $next_payment,
        private string $payment_method,
        private string $view_url,
        private ?string $raw_next_payment = null,
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

    public function get_date(string $date_type): ?string
    {
        return $this->raw_next_payment;
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
     * @param mixed $subscriptions
     * @param array<int, array<string, array<string, string>>> $actions
     */
    public function __construct(
        private mixed $subscriptions,
        private array $actions,
    ) {
    }

    public int $requested_user_id = 0;

    /**
     * @return mixed
     */
    public function subscriptions(int $user_id): mixed
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

    public function test_unknown_and_empty_statuses_use_a_generic_afrikaans_label(): void
    {
        assert_true(class_exists(SubscriptionService::class));

        $unknown = new VgpcSubscriptionFixture(
            107,
            42,
            'mystery-status',
            [new VgpcSubscriptionLineFixture('Onbekende subskripsie')],
            '',
            'Via PayFast',
            '/my-account/view-subscription/107/'
        );
        $empty = new VgpcSubscriptionFixture(
            108,
            42,
            '',
            [new VgpcSubscriptionLineFixture('Leë subskripsie')],
            '',
            'Via PayFast',
            '/my-account/view-subscription/108/'
        );
        $provider = new VgpcSubscriptionProviderFixture([$unknown, $empty], []);

        $card = (new SubscriptionService($provider))->card(42);

        assert_same('unknown', $card['items'][0]['status']);
        assert_same('Status onbekend', $card['items'][0]['status_label']);
        assert_same('unknown', $card['items'][1]['status']);
        assert_same('Status onbekend', $card['items'][1]['status_label']);
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

    public function test_invalid_subscription_shape_returns_an_unavailable_card(): void
    {
        assert_true(class_exists(SubscriptionService::class));

        $provider = new VgpcSubscriptionProviderFixture(new stdClass(), []);
        $card = (new SubscriptionService($provider))->card(42);

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

    public function test_accepts_canonical_owner_ids_and_rejects_fractional_or_noncanonical_values(): void
    {
        assert_true(class_exists(SubscriptionService::class));

        $valid = new VgpcSubscriptionFixture(
            110,
            '42',
            'active',
            [new VgpcSubscriptionLineFixture('Geldige eienaar')],
            '',
            'Via PayFast',
            '/subscription/110/'
        );
        $invalid_owners = [42.5, '42.0', '4e1', ' 42', '042'];
        $subscriptions = [$valid];
        foreach ($invalid_owners as $offset => $owner_id) {
            $subscriptions[] = new VgpcSubscriptionFixture(
                111 + $offset,
                $owner_id,
                'active',
                [new VgpcSubscriptionLineFixture('Ongeldige eienaar')],
                '',
                'Via PayFast',
                '/subscription/' . (111 + $offset) . '/'
            );
        }

        $provider = new VgpcSubscriptionProviderFixture($subscriptions, []);
        $card = (new SubscriptionService($provider))->card(42);

        assert_same(1, count($card['items']));
        assert_same('110', (string) $card['items'][0]['subscription_id']);
    }

    public function test_prefers_the_raw_next_payment_date_over_localized_display_text(): void
    {
        assert_true(class_exists(SubscriptionService::class));

        $subscription = new VgpcSubscriptionFixture(
            103,
            42,
            'active',
            [new VgpcSubscriptionLineFixture('Voelgoedvriendinne Goud')],
            '01/10/2026',
            'Via PayFast',
            '/my-account/view-subscription/103/',
            '2026-10-01 09:30:00'
        );
        $provider = new VgpcSubscriptionProviderFixture([$subscription], []);

        $card = (new SubscriptionService($provider))->card(42);

        assert_same('2026-10-01', $card['items'][0]['next_payment_date']);
    }

    public function test_preserves_localized_and_relative_display_text_when_raw_date_is_absent(): void
    {
        assert_true(class_exists(SubscriptionService::class));

        $localized = new VgpcSubscriptionFixture(
            104,
            42,
            'active',
            [new VgpcSubscriptionLineFixture('Voelgoedvriendinne Silwer')],
            '01/10/2026',
            'Via PayFast',
            '/my-account/view-subscription/104/'
        );
        $relative = new VgpcSubscriptionFixture(
            105,
            42,
            'active',
            [new VgpcSubscriptionLineFixture('Voelgoedvriendinne Brons')],
            'in 5 days',
            'Via PayFast',
            '/my-account/view-subscription/105/'
        );
        $provider = new VgpcSubscriptionProviderFixture([$localized, $relative], []);

        $card = (new SubscriptionService($provider))->card(42);

        assert_same('01/10/2026', $card['items'][0]['next_payment_date']);
        assert_same('in 5 days', $card['items'][1]['next_payment_date']);
    }

    public function test_falls_back_to_display_text_when_raw_next_payment_is_malformed(): void
    {
        assert_true(class_exists(SubscriptionService::class));

        $subscription = new VgpcSubscriptionFixture(
            106,
            42,
            'active',
            [new VgpcSubscriptionLineFixture('Voelgoedvriendinne Brons')],
            '01/10/2026',
            'Via PayFast',
            '/my-account/view-subscription/106/',
            'not-a-date'
        );
        $provider = new VgpcSubscriptionProviderFixture([$subscription], []);

        $card = (new SubscriptionService($provider))->card(42);

        assert_same('01/10/2026', $card['items'][0]['next_payment_date']);
    }

    public function test_impossible_raw_iso_dates_use_display_text(): void
    {
        assert_true(class_exists(SubscriptionService::class));

        $subscriptions = [];
        foreach (['2026-02-30', '2026-02-30abc', '2026-01-01T25:00'] as $offset => $raw_date) {
            $id = 109 + $offset;
            $subscriptions[] = new VgpcSubscriptionFixture(
                $id,
                42,
                'active',
                [new VgpcSubscriptionLineFixture('Voelgoedvriendinne Brons')],
                '01/10/2026',
                'Via PayFast',
                '/my-account/view-subscription/' . $id . '/',
                $raw_date
            );
        }
        $provider = new VgpcSubscriptionProviderFixture($subscriptions, []);

        $card = (new SubscriptionService($provider))->card(42);

        assert_same('01/10/2026', $card['items'][0]['next_payment_date']);
        assert_same('01/10/2026', $card['items'][1]['next_payment_date']);
        assert_same('01/10/2026', $card['items'][2]['next_payment_date']);
    }
}
