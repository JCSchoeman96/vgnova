<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Config\AfrikaansLabels;
use Voelgoed\PlatformCore\Contracts\DashboardCard;
use Voelgoed\PlatformCore\Dashboard\DashboardContext;
use Voelgoed\PlatformCore\Dashboard\DashboardRegistry;
use Voelgoed\PlatformCore\Dashboard\Navigation;
use Voelgoed\PlatformCore\Services\MemberAreaService;

final class VgpcNavigationServiceFixture
{
    /**
     * @param array<string, mixed> $card
     */
    public function __construct(private array $card)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function card(int $user_id): array
    {
        return $this->card;
    }
}

final class NavigationTest
{
    public function test_navigation_uses_the_approved_afrikaans_order_and_nested_payment_action(): void
    {
        assert_true(class_exists(DashboardContext::class));
        assert_true(class_exists(DashboardRegistry::class));
        assert_true(class_exists(Navigation::class));

        $navigation = new Navigation(new DashboardRegistry([
            'member-area' => new VgpcNavigationServiceFixture(
                DashboardCard::empty(
                    'member-area',
                    AfrikaansLabels::MEMBER_AREA,
                    'empty',
                    '',
                    false
                )
            ),
        ]));

        $items = $navigation->items(new DashboardContext(42, 'orders'));

        assert_same(
            ['Oorsig', 'Lidmaatskap', 'Subskripsies', 'Bestellings', 'Kaartjies', 'Kursusse', 'Profiel'],
            array_column($items, 'label')
        );
        assert_same('orders', $items[3]['key']);
        assert_true($items[3]['active']);
        assert_same('page', $items[3]['aria_current']);
        assert_same('false', $items[0]['aria_current']);
        assert_same('/my-voelgoed/orders/', $items[3]['url']);

        $profile = $items[6];
        assert_same('profile', $profile['key']);
        assert_same('payment-methods', $profile['children'][0]['key']);
        assert_same(AfrikaansLabels::PAYMENT_METHODS, $profile['children'][0]['label']);
        assert_same('/my-voelgoed/payment-methods/', $profile['children'][0]['url']);
        assert_same('false', $profile['children'][0]['aria_current']);

        foreach ($items as $item) {
            foreach (array_merge([$item], $item['children'] ?? []) as $nav_item) {
                assert_true(array_key_exists('key', $nav_item));
                assert_true(array_key_exists('label', $nav_item));
                assert_true(array_key_exists('url', $nav_item));
                assert_true(array_key_exists('active', $nav_item));
                assert_true(array_key_exists('visible', $nav_item));
                assert_true(array_key_exists('aria_current', $nav_item));
            }
        }

        assert_false(in_array('Biblioteek', array_column($items, 'label'), true));
    }

    public function test_member_area_is_appended_only_for_a_visible_service_card(): void
    {
        assert_true(class_exists(Navigation::class));

        $visible_card = DashboardCard::make(
            'member-area',
            AfrikaansLabels::MEMBER_AREA,
            'active',
            'Jou Lede-area is beskikbaar.',
            [['key' => 'open', 'label' => 'Maak oop', 'url' => '/lede-area/']]
        );
        $visible_navigation = (new Navigation(new DashboardRegistry([
            'member-area' => new VgpcNavigationServiceFixture($visible_card),
        ])))->items(new DashboardContext(42));

        assert_same('member-area', end($visible_navigation)['key']);
        assert_same(AfrikaansLabels::MEMBER_AREA, end($visible_navigation)['label']);
        assert_same('/lede-area/', end($visible_navigation)['url']);

        $hidden_navigation = (new Navigation(new DashboardRegistry([
            'member-area' => new VgpcNavigationServiceFixture(
                DashboardCard::empty('member-area', AfrikaansLabels::MEMBER_AREA, 'empty', '', false)
            ),
        ])))->items(new DashboardContext(42));

        assert_false(in_array('member-area', array_column($hidden_navigation, 'key'), true));
    }

    public function test_registry_isolates_a_failing_provider_from_other_views(): void
    {
        assert_true(class_exists(DashboardRegistry::class));

        $registry = new DashboardRegistry([
            'membership' => new VgpcNavigationServiceFixture(
                DashboardCard::make('membership', AfrikaansLabels::MEMBERSHIP, 'active', 'Aktief')
            ),
            'tickets' => new class {
                public function card(int $user_id): array
                {
                    throw new RuntimeException('Tickera is unavailable.');
                }
            },
        ]);

        $cards = $registry->cards(new DashboardContext(42));

        assert_same('active', $cards['membership']['state']);
        assert_same('unavailable', $cards['tickets']['state']);
        assert_same(AfrikaansLabels::TICKETS, $cards['tickets']['label']);
        assert_true(array_key_exists('orders', $cards));
        assert_true(array_key_exists('learning', $cards));
        assert_true(array_key_exists('member-area', $cards));
    }
}
