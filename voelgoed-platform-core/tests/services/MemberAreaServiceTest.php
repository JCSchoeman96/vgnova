<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Config\AfrikaansLabels;
use Voelgoed\PlatformCore\Services\MemberAreaService;

final class VgpcMemberHubAccessFixture
{
    public int $resolve_calls = 0;

    public function __construct(private array $resolution)
    {
    }

    public function resolve(string $context): array
    {
        ++$this->resolve_calls;

        return $this->resolution;
    }
}

final class VgpcMemberAreaProviderFixture
{
    public function __construct(
        private mixed $flag,
        private VgpcMemberHubAccessFixture $hub_access,
    ) {
    }

    public int $flag_calls = 0;

    public function feature_flags(): mixed
    {
        ++$this->flag_calls;

        return $this->flag;
    }

    public function hub_access(): VgpcMemberHubAccessFixture
    {
        return $this->hub_access;
    }
}

final class MemberAreaServiceBoundaryTest
{
    public function test_flag_enabled_but_missing_hub_access_class_hides_member_area(): void
    {
        $hub = new VgpcMemberHubAccessFixture(['can_view_hub' => true, 'hub_page_id' => 990]);
        $provider = new VgpcMemberAreaProviderFixture(['hub_display' => true], $hub);

        $card = (new MemberAreaService($provider))->card(42);

        assert_false($card['visible']);
        assert_same([], $card['actions']);
        assert_same(0, $hub->resolve_calls);
    }
}

final class MemberAreaServiceTest
{
    private function ensure_hub_access_stub(): void
    {
        $class = '\\Voelgoed\\MemberPerks\\Display\\HubAccess';
        if (class_exists($class)) {
            return;
        }

        eval('namespace Voelgoed\\MemberPerks\\Display; final class HubAccess { public const CONTEXT_MAIN = "main"; }');
    }

    public function test_active_member_area_uses_the_resolved_main_hub_and_same_site_url(): void
    {
        assert_true(class_exists(MemberAreaService::class));
        $this->ensure_hub_access_stub();

        vgpc_set_permalink(990, '/my-voordele/');
        $hub = new VgpcMemberHubAccessFixture([
            'can_view_hub' => true,
            'hub_page_id'  => 990,
        ]);
        $provider = new VgpcMemberAreaProviderFixture(
            ['vgmp_enable_hub_display' => true],
            $hub
        );

        $card = (new MemberAreaService($provider))->card(42);

        assert_same('member-area', $card['key']);
        assert_same(AfrikaansLabels::MEMBER_AREA, $card['label']);
        assert_same('active', $card['state']);
        assert_true($card['visible']);
        assert_same(1, $hub->resolve_calls);
        assert_same(1, $provider->flag_calls);
        assert_same('/my-voordele/', $card['actions'][0]['url']);
        assert_same('open', $card['actions'][0]['key']);
        assert_same(990, $card['items'][0]['hub_page_id']);
        assert_same('/my-voordele/', $card['items'][0]['url']);
    }

    public function test_disabled_feature_hides_member_area_without_resolving_hub_access(): void
    {
        $this->ensure_hub_access_stub();
        $hub = new VgpcMemberHubAccessFixture(['can_view_hub' => true, 'hub_page_id' => 990]);
        $provider = new VgpcMemberAreaProviderFixture(
            ['vgmp_enable_hub_display' => false],
            $hub
        );

        $card = (new MemberAreaService($provider))->card(42);

        assert_false($card['visible']);
        assert_same('empty', $card['state']);
        assert_same([], $card['actions']);
        assert_same(0, $hub->resolve_calls);
    }

    public function test_missing_feature_flag_boundary_hides_member_area_in_production(): void
    {
        $this->ensure_hub_access_stub();
        $hub = new VgpcMemberHubAccessFixture(['can_view_hub' => true, 'hub_page_id' => 990]);
        $provider = new class($hub) {
            public function __construct(private VgpcMemberHubAccessFixture $hub)
            {
            }

            public function hub_access(): VgpcMemberHubAccessFixture
            {
                return $this->hub;
            }
        };

        $card = (new MemberAreaService($provider))->card(42);

        assert_false($card['visible']);
        assert_same(0, $hub->resolve_calls);
    }

    public function test_inactive_hub_resolution_hides_member_area(): void
    {
        $this->ensure_hub_access_stub();
        $hub = new VgpcMemberHubAccessFixture([
            'can_view_hub' => false,
            'hub_page_id'  => 990,
        ]);
        $provider = new VgpcMemberAreaProviderFixture(['vgmp_enable_hub_display' => true], $hub);

        $card = (new MemberAreaService($provider))->card(42);

        assert_false($card['visible']);
        assert_same([], $card['actions']);
        assert_same(1, $hub->resolve_calls);
    }

    public function test_missing_page_or_external_permalink_fails_closed(): void
    {
        $this->ensure_hub_access_stub();
        $missing_page = new VgpcMemberHubAccessFixture(['can_view_hub' => true, 'hub_page_id' => 0]);
        $provider = new VgpcMemberAreaProviderFixture(['vgmp_enable_hub_display' => true], $missing_page);
        $card = (new MemberAreaService($provider))->card(42);
        assert_false($card['visible']);

        $external_page = new VgpcMemberHubAccessFixture(['can_view_hub' => true, 'hub_page_id' => 991]);
        vgpc_set_permalink(991, 'https://ander.example/voordele/');
        $provider = new VgpcMemberAreaProviderFixture(['vgmp_enable_hub_display' => true], $external_page);
        $card = (new MemberAreaService($provider))->card(42);
        assert_false($card['visible']);
        assert_same([], $card['actions']);
    }

    public function test_dual_programme_resolution_still_exposes_one_main_hub_action(): void
    {
        $this->ensure_hub_access_stub();
        vgpc_set_permalink(990, '/my-voordele/');
        $hub = new VgpcMemberHubAccessFixture([
            'can_view_hub' => true,
            'hub_page_id'  => 990,
            'programmes'   => ['vriendinneklub', 'voelgoednetwerk'],
        ]);
        $provider = new VgpcMemberAreaProviderFixture(['vgmp_enable_hub_display' => true], $hub);

        $card = (new MemberAreaService($provider))->card(42);

        assert_same(1, count($card['actions']));
        assert_same('/my-voordele/', $card['actions'][0]['url']);
    }

    public function test_requested_or_authenticated_user_mismatch_hides_member_area(): void
    {
        $this->ensure_hub_access_stub();
        $hub = new VgpcMemberHubAccessFixture(['can_view_hub' => true, 'hub_page_id' => 990]);
        $provider = new VgpcMemberAreaProviderFixture(['vgmp_enable_hub_display' => true], $hub);

        $card = (new MemberAreaService($provider))->card(99);
        assert_false($card['visible']);
        assert_same(0, $hub->resolve_calls);

        vgpc_set_current_user_id(0);
        try {
            $card = (new MemberAreaService($provider))->card(42);
        } finally {
            vgpc_set_current_user_id(42);
        }

        assert_false($card['visible']);
        assert_same(0, $hub->resolve_calls);
    }

    public function test_hub_display_feature_flag_map_is_supported(): void
    {
        $this->ensure_hub_access_stub();
        vgpc_set_permalink(990, '/my-voordele/');
        $hub = new VgpcMemberHubAccessFixture([
            'can_view_hub' => true,
            'hub_page_id'  => 990,
        ]);
        $provider = new VgpcMemberAreaProviderFixture(['hub_display' => true], $hub);

        $card = (new MemberAreaService($provider))->card(42);

        assert_true($card['visible']);
        assert_same('/my-voordele/', $card['actions'][0]['url']);
    }
}
