<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Config\AfrikaansLabels;
use Voelgoed\PlatformCore\Contracts\DashboardAction;
use Voelgoed\PlatformCore\Contracts\DashboardCard;
use Voelgoed\PlatformCore\Contracts\DashboardServiceInterface;

final class DashboardCardTest
{
    public function test_contract_classes_are_loaded(): void
    {
        assert_true(interface_exists(DashboardServiceInterface::class));
        assert_true(class_exists(DashboardAction::class));
        assert_true(class_exists(DashboardCard::class));
        assert_true(class_exists(AfrikaansLabels::class));
    }

    public function test_card_has_a_stable_normalized_shape(): void
    {
        $card = DashboardCard::make(
            'subscriptions',
            AfrikaansLabels::SUBSCRIPTIONS,
            'active',
            '1 aktiewe subskripsie',
            [DashboardAction::link('manage', 'Bestuur subskripsies', '/my-voelgoed/subscriptions/')]
        );

        assert_same(
            ['key', 'label', 'visible', 'state', 'summary', 'items', 'actions'],
            array_keys($card)
        );
        assert_same('subscriptions', $card['key']);
        assert_same('Subskripsies', $card['label']);
        assert_true($card['visible']);
        assert_same('active', $card['state']);
        assert_same('1 aktiewe subskripsie', $card['summary']);
        assert_same([], $card['items']);
        assert_same('manage', $card['actions'][0]['key']);
        assert_same('Bestuur subskripsies', $card['actions'][0]['label']);
        assert_true(str_starts_with($card['actions'][0]['url'], '/'));
    }

    public function test_card_sanitizes_display_values_and_action_values(): void
    {
        $card = DashboardCard::make(
            ' subscriptions alert ',
            " <strong>Subskripsies</strong> ",
            ' active state ',
            " <em>Een subskripsie</em>\n",
            [DashboardAction::link(' manage now ', ' <span>Bestuur</span> ', '/my-voelgoed/subscriptions/')]
        );

        assert_same('subscriptions-alert', $card['key']);
        assert_same('Subskripsies', $card['label']);
        assert_same('active-state', $card['state']);
        assert_same('Een subskripsie', $card['summary']);
        assert_same('manage-now', $card['actions'][0]['key']);
        assert_same('Bestuur', $card['actions'][0]['label']);
    }

    public function test_card_rejects_empty_required_values(): void
    {
        assert_throws(
            InvalidArgumentException::class,
            static fn (): array => DashboardCard::make('', 'Label', 'empty', '')
        );
        assert_throws(
            InvalidArgumentException::class,
            static fn (): array => DashboardCard::make('key', '', 'empty', '')
        );
        assert_throws(
            InvalidArgumentException::class,
            static fn (): array => DashboardCard::make(
                'key',
                'Label',
                'empty',
                '',
                [DashboardAction::link('action', 'Action', '')]
            )
        );
    }

    public function test_actions_accept_same_site_paths_and_reject_external_urls(): void
    {
        $action = DashboardAction::link('manage', 'Bestuur', '/my-voelgoed/profile/');
        assert_same(
            ['key', 'label', 'url'],
            array_keys($action)
        );

        assert_throws(
            InvalidArgumentException::class,
            static fn (): array => DashboardAction::link('external', 'Ekstern', 'https://example.com/account/')
        );
        assert_throws(
            InvalidArgumentException::class,
            static fn (): array => DashboardAction::link('protocol', 'Protokol', '//example.com/account/')
        );
        assert_throws(
            InvalidArgumentException::class,
            static fn (): array => DashboardAction::link('script', 'Skrip', 'javascript:alert(1)')
        );
    }

    public function test_labels_keep_member_area_conditional_and_library_deferred(): void
    {
        $sections = AfrikaansLabels::sections();
        assert_false(in_array('Biblioteek', $sections, true));
        assert_false(in_array(AfrikaansLabels::MEMBER_AREA, $sections, true));

        $conditional_navigation = AfrikaansLabels::conditional_navigation();
        assert_same(AfrikaansLabels::MEMBER_AREA, $conditional_navigation['member-area']);

        $provider_services = AfrikaansLabels::provider_services();
        assert_false(in_array(AfrikaansLabels::MEMBER_AREA, $provider_services, true));
    }

    public function test_first_release_labels_are_exact(): void
    {
        assert_same('Oorsig', AfrikaansLabels::OVERVIEW);
        assert_same('Lidmaatskap', AfrikaansLabels::MEMBERSHIP);
        assert_same('Subskripsies', AfrikaansLabels::SUBSCRIPTIONS);
        assert_same('Bestellings', AfrikaansLabels::ORDERS);
        assert_same('Kaartjies', AfrikaansLabels::TICKETS);
        assert_same('Kursusse', AfrikaansLabels::LEARNING);
        assert_same('Profiel', AfrikaansLabels::PROFILE);
        assert_same('Betaalmetodes', AfrikaansLabels::PAYMENT_METHODS);
        assert_same('Lede-area', AfrikaansLabels::MEMBER_AREA);
        assert_same('Aandag nodig', AfrikaansLabels::ATTENTION);
    }
}
