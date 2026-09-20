<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once dirname(__DIR__) . '/../includes/Bricks/DashboardDynamicData.php';
require_once dirname(__DIR__) . '/../includes/Bricks/DashboardQueryProvider.php';
require_once dirname(__DIR__) . '/../includes/Performance/CachePolicy.php';

use Voelgoed\PlatformCore\Bricks\DashboardDynamicData;
use Voelgoed\PlatformCore\Bricks\DashboardQueryProvider;
use Voelgoed\PlatformCore\Performance\CachePolicy;

if (! class_exists('Bricks\\Query', false)) {
    eval('namespace Bricks { class Query {} }');
}

if (! function_exists('add_filter')) {
    function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void
    {
        $GLOBALS['vgpc_filters'][$hook][$priority][] = [
            'callback'      => $callback,
            'accepted_args' => $accepted_args,
        ];
    }
}

if (! function_exists('apply_filters')) {
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
    {
        foreach ($GLOBALS['vgpc_filters'][$hook] ?? [] as $priority_callbacks) {
            foreach ($priority_callbacks as $registration) {
                $callback = $registration['callback'] ?? null;
                if (is_callable($callback)) {
                    $value = $callback($value, ...$args);
                }
            }
        }

        return $value;
    }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string
    {
        return trim(strip_tags($value));
    }
}

if (! function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return $url;
    }
}

if (! function_exists('esc_html')) {
    function esc_html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (! function_exists('wp_get_current_user')) {
    function wp_get_current_user(): object
    {
        return (object) [
            'ID'           => (int) ($GLOBALS['vgpc_current_user_id'] ?? 0),
            'display_name' => (string) ($GLOBALS['vgpc_current_user_name'] ?? ''),
            'user_login'   => (string) ($GLOBALS['vgpc_current_user_login'] ?? ''),
        ];
    }
}

final class VgpcDashboardRegistryFixture
{
    /**
     * @param array<string, array<string, mixed>|null> $cards
     */
    public function __construct(private array $cards)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function card(string $key, mixed $context = null): ?array
    {
        unset($context);

        return $this->cards[$key] ?? null;
    }
}

final class CachePolicyTest
{
    public function test_dashboard_query_cache_keys_include_user_and_view(): void
    {
        assert_true(class_exists(DashboardQueryProvider::class));

        $provider = new DashboardQueryProvider(new VgpcDashboardRegistryFixture([
            'orders' => [
                'key'      => 'orders',
                'label'    => 'Bestellings',
                'visible'  => true,
                'state'    => 'empty',
                'summary'  => '',
                'items'    => [],
                'actions'  => [],
            ],
        ]));

        vgpc_set_current_user_id(11);
        $first = $provider->filter_query_vars([], [
            'query' => [
                'vg_query_type'      => DashboardQueryProvider::QUERY_TYPE,
                'vg_dashboard_view'  => 'orders',
            ],
        ], 'loop-1', 'block');

        vgpc_set_current_user_id(22);
        $second = $provider->filter_query_vars([], [
            'query' => [
                'vg_query_type'      => DashboardQueryProvider::QUERY_TYPE,
                'vg_dashboard_view'  => 'orders',
            ],
        ], 'loop-1', 'block');

        $first_key = $provider->filter_cache_key('bricks_loop', (object) ['query_vars' => $first]);
        $second_key = $provider->filter_cache_key('bricks_loop', (object) ['query_vars' => $second]);

        assert_not_same($first_key, $second_key);
        assert_contains('_vgpc_u11', $first_key);
        assert_contains('_vgpc_u22', $second_key);
        assert_contains('orders', $first_key);
        vgpc_set_current_user_id(42);
    }

    public function test_logged_out_dashboard_query_is_empty(): void
    {
        assert_true(class_exists(DashboardQueryProvider::class));

        vgpc_set_current_user_id(0);
        $provider = new DashboardQueryProvider(new VgpcDashboardRegistryFixture([
            'orders' => [
                'key'      => 'orders',
                'label'    => 'Bestellings',
                'visible'  => true,
                'state'    => 'active',
                'summary'  => 'Een bestelling',
                'items'    => [['order_id' => '100']],
                'actions'  => [],
            ],
        ]));

        $query = $provider->filter_query_vars([], [
            'query' => [
                'vg_query_type'     => DashboardQueryProvider::QUERY_TYPE,
                'vg_dashboard_view' => 'orders',
            ],
        ], 'loop-1', 'block');

        assert_same([0], $query['post__in'] ?? null);
        assert_same(0, (int) ($query['vgpc_user_id'] ?? 0));
        assert_same([], $provider->card_for_view('orders'));
        vgpc_set_current_user_id(42);
    }

    public function test_missing_provider_returns_empty_card_and_keeps_user_cache_isolation(): void
    {
        assert_true(class_exists(DashboardQueryProvider::class));

        vgpc_set_current_user_id(31);
        $provider = new DashboardQueryProvider(new VgpcDashboardRegistryFixture([]));
        $card = $provider->card_for_view('orders', 31);

        assert_same('orders', $card['key'] ?? null);
        assert_same('empty', $card['state'] ?? null);
        assert_same([], $card['items'] ?? null);
        assert_same([], $card['actions'] ?? null);

        $query_one = $provider->query_for_view('orders', 31);
        $query_two = $provider->query_for_view('orders', 32);
        $key_one = $provider->filter_cache_key('bricks_loop', (object) ['query_vars' => $query_one]);
        $key_two = $provider->filter_cache_key('bricks_loop', (object) ['query_vars' => $query_two]);

        assert_not_same($key_one, $key_two);
        assert_same([0], $query_one['post__in'] ?? null);
        assert_same([0], $query_two['post__in'] ?? null);
        vgpc_set_current_user_id(42);
    }

    public function test_dashboard_dynamic_tags_are_limited_to_approved_fields(): void
    {
        assert_true(class_exists(DashboardDynamicData::class));

        $registry = new VgpcDashboardRegistryFixture([
            'membership' => [
                'key'      => 'membership',
                'label'    => 'Lidmaatskap',
                'visible'  => true,
                'state'    => 'active',
                'summary'  => 'Aktief',
                'items'    => [],
                'actions'  => [],
            ],
            'member-area' => [
                'key'      => 'member-area',
                'label'    => 'Lede-area',
                'visible'  => true,
                'state'    => 'active',
                'summary'  => '',
                'items'    => [],
                'actions'  => [[
                    'key'   => 'open',
                    'label' => 'Maak oop',
                    'url'   => '/my-voordele/',
                ]],
            ],
        ]);
        $dynamic = new DashboardDynamicData($registry);
        $dynamic->register_hooks();

        $tags = apply_filters('bricks/dynamic_tags_list', []);
        $names = array_column($tags, 'name');

        assert_same([
            '{vg_dashboard_user_name}',
            '{vg_dashboard_membership_summary}',
            '{vg_dashboard_subscription_summary}',
            '{vg_dashboard_order_summary}',
            '{vg_dashboard_ticket_summary}',
            '{vg_dashboard_learning_summary}',
            '{vg_dashboard_member_area_url}',
            '{vg_dashboard_member_area_visible}',
        ], array_values(array_filter(
            $names,
            static fn (mixed $name): bool => is_string($name) && str_starts_with($name, '{vg_dashboard_')
        )));
        assert_false(in_array('{vg_dashboard_biblioteek}', $names, true));
    }

    public function test_dashboard_cache_policy_disables_shared_caching(): void
    {
        assert_true(class_exists(CachePolicy::class));

        $policy = new CachePolicy();
        assert_true($policy->apply('/my-voelgoed/orders/'));
        assert_true(defined('DONOTCACHEPAGE'));
        assert_true((bool) DONOTCACHEPAGE);
        assert_same(
            'private, no-store, no-cache, must-revalidate, max-age=0',
            $policy->cache_control_header()
        );
        assert_false($policy->apply('/content/example/'));
    }
}

vgpc_set_current_user_id(42);
