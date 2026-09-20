<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Config\AfrikaansLabels;
use Voelgoed\PlatformCore\Services\MembershipService;

final class VgpcMembershipProviderFixture
{
    /**
     * @param mixed $levels
     * @param array<string, string> $urls
     */
    public function __construct(
        private mixed $levels,
        private array $urls,
    ) {
    }

    public int $requested_user_id = 0;

    /**
     * @return mixed
     */
    public function levels(int $user_id): mixed
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

final class VgpcThrowingMembershipLevelFixture
{
    public function __isset(string $name): bool
    {
        throw new RuntimeException('Membership level property access failed.');
    }

    public function __get(string $name): mixed
    {
        throw new RuntimeException('Membership level property access failed.');
    }
}

final class MembershipServiceTest
{
    public function test_populated_user_returns_pmpro_membership_and_native_actions(): void
    {
        assert_true(class_exists(MembershipService::class));

        $provider = new VgpcMembershipProviderFixture(
            [
                (object) [
                    'name'        => 'Voelgoedvriendinne Silwer',
                    'status'      => 'active',
                    'startdate'   => '2026-01-01',
                    'enddate'     => '2026-12-31 18:00:00',
                    'renewal_date'=> '2026-12-31T12:00:00+02:00',
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
        assert_same('1 aktiewe lidmaatskap', $card['summary']);
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
        assert_true(class_exists(MembershipService::class));

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

    public function test_missing_pmpro_status_defaults_to_active_for_levels_api_records(): void
    {
        assert_true(class_exists(MembershipService::class));

        $provider = new VgpcMembershipProviderFixture(
            [(object) ['name' => 'PMPro vlak', 'ID' => 988]],
            ['cancel' => '/pmpro-cancel/']
        );

        $card = (new MembershipService($provider))->card(42);

        assert_same('active', $card['state']);
        assert_same('active', $card['items'][0]['status']);
        assert_true(in_array('cancel', array_column($card['actions'], 'key'), true));
    }

    public function test_unknown_and_empty_statuses_use_a_generic_afrikaans_label(): void
    {
        assert_true(class_exists(MembershipService::class));

        $provider = new VgpcMembershipProviderFixture(
            [
                (object) ['name' => 'Onbekende status', 'status' => 'paused'],
                (object) ['name' => 'Leë status', 'status' => ''],
            ],
            []
        );

        $card = (new MembershipService($provider))->card(42);

        assert_same('unknown', $card['items'][0]['status']);
        assert_same('Status onbekend', $card['items'][0]['status_label']);
        assert_same('unknown', $card['items'][1]['status']);
        assert_same('Status onbekend', $card['items'][1]['status_label']);
        assert_same('unknown', $card['state']);
        assert_same('Lidmaatskapstatus onbekend.', $card['summary']);
        assert_same([], $card['actions']);
        assert_same([], $card['items'][0]['actions']);
        assert_same([], $card['items'][1]['actions']);
    }

    public function test_missing_pmpro_returns_an_unavailable_card_without_throwing(): void
    {
        assert_true(class_exists(MembershipService::class));

        $card = (new MembershipService(new stdClass()))->card(42);

        assert_same('membership', $card['key']);
        assert_same(AfrikaansLabels::MEMBERSHIP, $card['label']);
        assert_same('unavailable', $card['state']);
        assert_same([], $card['items']);
        assert_same([], $card['actions']);
    }

    public function test_invalid_pmpro_levels_shape_returns_an_unavailable_card(): void
    {
        assert_true(class_exists(MembershipService::class));

        $card = (new MembershipService(new VgpcMembershipProviderFixture(new stdClass(), [])))->card(42);

        assert_same('unavailable', $card['state']);
        assert_same([], $card['items']);
        assert_same([], $card['actions']);
    }

    public function test_impossible_iso_dates_are_empty(): void
    {
        assert_true(class_exists(MembershipService::class));

        $provider = new VgpcMembershipProviderFixture(
            [
                (object) ['name' => 'Onmoontlike dag', 'enddate' => '2026-02-30'],
                (object) ['name' => 'Ongeldige agtervoegsel', 'enddate' => '2026-02-30abc'],
                (object) ['name' => 'Ongeldige tyd', 'enddate' => '2026-01-01T25:00'],
                (object) ['name' => 'Onleesbare datum', 'enddate' => 'not-a-date'],
            ],
            []
        );

        $card = (new MembershipService($provider))->card(42);

        assert_same('', $card['items'][0]['expiry_date']);
        assert_same('', $card['items'][1]['expiry_date']);
        assert_same('', $card['items'][2]['expiry_date']);
        assert_same('', $card['items'][3]['expiry_date']);
    }

    public function test_expired_only_memberships_are_inactive_without_cancellation(): void
    {
        assert_true(class_exists(MembershipService::class));

        $provider = new VgpcMembershipProviderFixture(
            [(object) ['name' => 'Verstreke lidmaatskap', 'status' => 'expired', 'ID' => 991]],
            [
                'account' => '/pmpro-account/',
                'invoice' => '/pmpro-invoice/',
                'cancel'  => '/pmpro-cancel/',
            ]
        );

        $card = (new MembershipService($provider))->card(42);

        assert_same('inactive', $card['state']);
        assert_same('Geen aktiewe lidmaatskap nie.', $card['summary']);
        assert_same([], $card['items'][0]['actions']);
        assert_false(in_array('cancel', array_column($card['actions'], 'key'), true));
    }

    public function test_rejects_a_requested_user_that_differs_from_the_authenticated_user(): void
    {
        assert_true(class_exists(MembershipService::class));

        $provider = new VgpcMembershipProviderFixture(
            [(object) ['name' => 'Moet nie sigbaar wees nie', 'ID' => 987]],
            ['account' => '/pmpro-account/']
        );

        $card = (new MembershipService($provider))->card(99);

        assert_same('empty', $card['state']);
        assert_same([], $card['items']);
        assert_same([], $card['actions']);
        assert_same(0, $provider->requested_user_id);
    }

    public function test_returns_an_empty_card_when_the_authenticated_user_is_logged_out(): void
    {
        assert_true(class_exists(MembershipService::class));

        vgpc_set_current_user_id(0);
        $provider = new VgpcMembershipProviderFixture(
            [(object) ['name' => 'Moet nie sigbaar wees nie', 'ID' => 987]],
            ['account' => '/pmpro-account/']
        );

        try {
            $card = (new MembershipService($provider))->card(42);
        } finally {
            vgpc_set_current_user_id(42);
        }

        assert_same('empty', $card['state']);
        assert_same([], $card['items']);
        assert_same([], $card['actions']);
        assert_same(0, $provider->requested_user_id);
    }

    public function test_rejects_a_zero_requested_user_without_querying_pmpro(): void
    {
        assert_true(class_exists(MembershipService::class));

        $provider = new VgpcMembershipProviderFixture(
            [(object) ['name' => 'Moet nie sigbaar wees nie', 'ID' => 987]],
            ['account' => '/pmpro-account/']
        );

        $card = (new MembershipService($provider))->card(0);

        assert_same('empty', $card['state']);
        assert_same([], $card['items']);
        assert_same(0, $provider->requested_user_id);
    }

    public function test_http_pmpro_urls_remain_http(): void
    {
        assert_true(class_exists(MembershipService::class));

        if (! function_exists('pmpro_getMembershipLevelsForUser')) {
            function pmpro_getMembershipLevelsForUser(int $user_id): array
            {
                return [(object) ['name' => 'Voelgoedvriendinne Silwer', 'ID' => 987]];
            }
        }

        if (! function_exists('pmpro_url')) {
            function pmpro_url(string $page = '', string $query = '', ?string $scheme = null): string
            {
                $path = '/pmpro-' . trim($page, '/') . '/';
                if ($query !== '') {
                    $path .= '?' . ltrim($query, '?');
                }

                $base = $scheme !== null ? $scheme . '://voelgoed.test' : home_url('/');

                return rtrim($base, '/') . $path;
            }
        }

        vgpc_set_home_url('http://voelgoed.test');

        try {
            $card = (new MembershipService())->card(42);
        } finally {
            vgpc_set_home_url('https://voelgoed.test');
        }

        assert_same('http://voelgoed.test/pmpro-account/', $card['actions'][0]['url']);
    }

    public function test_throwing_level_properties_return_an_unavailable_card(): void
    {
        assert_true(class_exists(MembershipService::class));

        $provider = new VgpcMembershipProviderFixture(
            [new VgpcThrowingMembershipLevelFixture()],
            ['account' => '/pmpro-account/']
        );

        $card = (new MembershipService($provider))->card(42);

        assert_same('unavailable', $card['state']);
        assert_same([], $card['items']);
        assert_same([], $card['actions']);
    }
}
