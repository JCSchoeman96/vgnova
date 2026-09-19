<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Config\AfrikaansLabels;
use Voelgoed\PlatformCore\Services\MembershipService;

final class VgpcMembershipProviderFixture
{
    /**
     * @param array<int, object> $levels
     * @param array<string, string> $urls
     */
    public function __construct(
        private array $levels,
        private array $urls,
    ) {
    }

    public int $requested_user_id = 0;

    /**
     * @return array<int, object>
     */
    public function levels(int $user_id): array
    {
        $this->requested_user_id = $user_id;

        return $this->levels;
    }

    public function url(string $page, string $query = ''): string
    {
        if ($query !== '') {
            return ($this->urls[$page] ?? '') . '?' . ltrim($query, '?');
        }

        return $this->urls[$page] ?? '';
    }
}

final class MembershipServiceTest
{
    public function test_populated_user_returns_pmpro_membership_and_native_actions(): void
    {
        if (! class_exists(MembershipService::class)) {
            assert_true(false, 'MembershipService should be available.');

            return;
        }

        $provider = new VgpcMembershipProviderFixture(
            [
                (object) [
                    'name'        => 'Voelgoedvriendinne Silwer',
                    'status'      => 'active',
                    'startdate'   => '2026-01-01',
                    'enddate'     => '2026-12-31',
                    'renewal_date'=> '2026-12-31',
                    'ID'          => 987,
                ],
            ],
            [
                'account' => '/pmpro-account/',
                'invoice' => '/pmpro-invoice/',
                'cancel'  => '/pmpro-cancel/',
            ]
        );

        $card = (new MembershipService($provider))->card(42);

        assert_same('membership', $card['key']);
        assert_same(AfrikaansLabels::MEMBERSHIP, $card['label']);
        assert_same('active', $card['state']);
        assert_same(42, $provider->requested_user_id);
        assert_same(1, count($card['items']));
        assert_same('Voelgoedvriendinne Silwer', $card['items'][0]['level_name']);
        assert_same('active', $card['items'][0]['status']);
        assert_same('2026-12-31', $card['items'][0]['expiry_date']);
        assert_same('2026-12-31', $card['items'][0]['renewal_date']);
        assert_same('Lidmaatskap', $card['label']);

        $action_keys = array_column($card['actions'], 'key');
        assert_true(in_array('manage', $action_keys, true));
        assert_true(in_array('invoice', $action_keys, true));
        assert_true(in_array('cancel', $action_keys, true));
        assert_false(in_array('payment-method', $action_keys, true));
        assert_same('/pmpro-account/', $card['actions'][0]['url']);
    }

    public function test_empty_user_returns_a_valid_empty_card(): void
    {
        if (! class_exists(MembershipService::class)) {
            return;
        }

        $provider = new VgpcMembershipProviderFixture([], [
            'account' => '/pmpro-account/',
            'invoice' => '/pmpro-invoice/',
            'cancel'  => '/pmpro-cancel/',
        ]);

        $card = (new MembershipService($provider))->card(42);

        assert_same('membership', $card['key']);
        assert_same(AfrikaansLabels::MEMBERSHIP, $card['label']);
        assert_same('empty', $card['state']);
        assert_same([], $card['items']);
        assert_true($card['visible']);
    }

    public function test_missing_pmpro_returns_an_unavailable_card_without_throwing(): void
    {
        if (! class_exists(MembershipService::class)) {
            return;
        }

        $card = (new MembershipService())->card(42);

        assert_same('membership', $card['key']);
        assert_same(AfrikaansLabels::MEMBERSHIP, $card['label']);
        assert_same('unavailable', $card['state']);
        assert_same([], $card['items']);
        assert_same([], $card['actions']);
    }
}
