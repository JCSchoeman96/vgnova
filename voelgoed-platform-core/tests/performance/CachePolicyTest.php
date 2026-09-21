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

if (! function_exists('has_filter')) {
    function has_filter(string $hook, mixed $callback = null): int|false
    {
        $filters = $GLOBALS['vgpc_filters'][$hook] ?? [];
        if (! is_array($filters) || $filters === []) {
            return false;
        }

        if ($callback === null) {
            return 10;
        }

        foreach ($filters as $priority => $registrations) {
            foreach ($registrations as $registration) {
                if (($registration['callback'] ?? null) === $callback) {
                    return (int) $priority;
                }
            }
        }

        return false;
    }
}

if (! function_exists('do_action')) {
    function do_action(string $hook, mixed ...$args): void
    {
        $GLOBALS['vgpc_do_actions'][] = [
            'hook' => $hook,
            'args' => $args,
        ];
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
        return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (! function_exists('esc_attr')) {
    function esc_attr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
    public int $calls = 0;

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
        ++$this->calls;

        return $this->cards[$key] ?? null;
    }
}

final class VgpcDynamicCachePolicyFixture
{
    public int $apply_calls = 0;

    public function is_dashboard_request(?string $request_uri = null): bool
    {
        $path = parse_url($request_uri ?? (string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

        if (! is_string($path)) {
            return false;
        }

        $path = rtrim($path, '/');

        return $path === '/my-voelgoed' || str_starts_with($path, '/my-voelgoed/');
    }

    public function apply(?string $request_uri = null): bool
    {
        ++$this->apply_calls;

        return $this->is_dashboard_request($request_uri);
    }
}

final class CachePolicyTest
{
    public function test_cache_policy_accepts_a_wordpress_hook_object_and_uses_the_current_request_uri(): void
    {
        assert_true(class_exists(CachePolicy::class));

        $headers = [];
        $request_uri = $_SERVER['REQUEST_URI'] ?? null;
        $_SERVER['REQUEST_URI'] = '/my-voelgoed/orders/';

        try {
            $policy = new CachePolicy(
                static function (string $name, string $value) use (&$headers): void {
                    $headers[$name] = $value;
                }
            );

            assert_true($policy->send_no_cache_headers((object) ['request_uri' => '/my-voelgoed/orders/']));
            assert_same($policy->headers(), $headers);
        } finally {
            if ($request_uri === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $request_uri;
            }
        }
    }

    public function test_dashboard_query_provider_exposes_a_bricks_type_and_marks_custom_query_vars(): void
    {
        assert_true(class_exists(DashboardQueryProvider::class));

        $GLOBALS['vgpc_filters'] = [];
        vgpc_set_current_user_id(42);
        $provider = new DashboardQueryProvider(new VgpcDashboardRegistryFixture([
            'orders' => [
                'key'      => 'orders',
                'label'    => 'Bestellings',
                'visible'  => true,
                'availability' => 'available', 'content_state' => 'populated',
                'summary'  => 'Een bestelling',
                'items'    => [],
                'actions'  => [],
            ],
        ]));
        $provider->register_hooks();

        $control_options = apply_filters('bricks/setup/control_options', [
            'queryTypes' => ['post' => 'Posts'],
        ]);
        assert_same(
            'Voelgoed Dashboard: Kaart',
            $control_options['queryTypes'][DashboardQueryProvider::QUERY_TYPE] ?? null
        );

        $query_vars = apply_filters(
            'bricks/vg_dashboard_cards/query_vars',
            [],
            [
                'query' => [
                    'vg_dashboard_view' => 'orders',
                ],
            ],
            'loop-1',
            'block'
        );

        assert_same(DashboardQueryProvider::QUERY_TYPE, $query_vars[DashboardQueryProvider::META_QUERY_TYPE] ?? null);
        assert_same(42, $query_vars[DashboardQueryProvider::META_USER_ID] ?? null);
        assert_same('orders', $query_vars[DashboardQueryProvider::META_VIEW_KEY] ?? null);

        $result = apply_filters('bricks/query/run', [], (object) [
            'object_type' => DashboardQueryProvider::QUERY_TYPE,
            'query_vars'  => $query_vars,
        ]);
        assert_same('orders', $result[0]['key'] ?? null);
    }

    public function test_dashboard_dynamic_data_registers_hooks_once_across_instances(): void
    {
        assert_true(class_exists(DashboardDynamicData::class));

        $GLOBALS['vgpc_filters'] = [];
        $first = new DashboardDynamicData(new VgpcDashboardRegistryFixture([]));
        $second = new DashboardDynamicData(new VgpcDashboardRegistryFixture([]));
        $first->register_hooks();
        $second->register_hooks();

        assert_same(1, count($GLOBALS['vgpc_filters']['bricks/dynamic_tags_list'][10] ?? []));
        assert_same(1, count($GLOBALS['vgpc_filters']['bricks/dynamic_data/render_content'][11] ?? []));
        assert_same(1, count($GLOBALS['vgpc_filters']['bricks/dynamic_data/render_tag'][20] ?? []));
    }

    public function test_dashboard_query_provider_registers_hooks_once_across_instances(): void
    {
        assert_true(class_exists(DashboardQueryProvider::class));

        $GLOBALS['vgpc_filters'] = [];
        $first = new DashboardQueryProvider(new VgpcDashboardRegistryFixture([]));
        $second = new DashboardQueryProvider(new VgpcDashboardRegistryFixture([]));
        $first->register_hooks();
        $second->register_hooks();

        assert_same(1, count($GLOBALS['vgpc_filters']['bricks/setup/control_options'][10] ?? []));
        assert_same(1, count($GLOBALS['vgpc_filters']['bricks/vg_dashboard_cards/query_vars'][10] ?? []));
        assert_same(1, count($GLOBALS['vgpc_filters']['bricks/query/cache_key'][10] ?? []));
        assert_same(1, count($GLOBALS['vgpc_filters']['bricks/query/run'][10] ?? []));
    }

    public function test_bricks_init_hooks_register_once_across_separate_instances(): void
    {
        assert_true(class_exists(DashboardDynamicData::class));
        assert_true(class_exists(DashboardQueryProvider::class));

        $GLOBALS['vgpc_actions'] = [];
        $dynamic_first = new DashboardDynamicData(new VgpcDashboardRegistryFixture([]));
        $dynamic_second = new DashboardDynamicData(new VgpcDashboardRegistryFixture([]));
        $query_first = new DashboardQueryProvider(new VgpcDashboardRegistryFixture([]));
        $query_second = new DashboardQueryProvider(new VgpcDashboardRegistryFixture([]));
        $dynamic_first->register();
        $dynamic_second->register();
        $query_first->register();
        $query_second->register();

        assert_same(2, count($GLOBALS['vgpc_actions']['init'] ?? []));
    }

    public function test_cache_policy_registers_hooks_once_across_instances(): void
    {
        assert_true(class_exists(CachePolicy::class));

        $GLOBALS['vgpc_actions'] = [];
        $first = new CachePolicy();
        $second = new CachePolicy();
        $first->register();
        $second->register();

        assert_same(1, count($GLOBALS['vgpc_actions']['template_redirect'] ?? []));
        assert_same(1, count($GLOBALS['vgpc_actions']['send_headers'] ?? []));
        assert_same(1, count($GLOBALS['vgpc_filters']['wp_robots'][10] ?? []));
    }

    public function test_plugin_injects_one_registry_into_dynamic_data_and_query_provider(): void
    {
        assert_true(class_exists(\Voelgoed\PlatformCore\Plugin::class));

        $GLOBALS['vgpc_actions'] = [];
        $GLOBALS['vgpc_filters'] = [];
        $plugin = new \Voelgoed\PlatformCore\Plugin();
        $plugin->register();
        $components = $plugin->components();

        assert_true(($components['dynamic_data']->registry() ?? null) === ($components['registry'] ?? null));
        assert_true(($components['query_provider']->registry() ?? null) === ($components['registry'] ?? null));
    }

    public function test_dashboard_query_cache_keys_include_user_and_view(): void
    {
        assert_true(class_exists(DashboardQueryProvider::class));

        $provider = new DashboardQueryProvider(new VgpcDashboardRegistryFixture([
            'orders' => [
                'key'      => 'orders',
                'label'    => 'Bestellings',
                'visible'  => true,
                'availability' => 'available', 'content_state' => 'empty',
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
                'availability' => 'available', 'content_state' => 'populated',
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
        $registry = new VgpcDashboardRegistryFixture([]);
        $provider = new DashboardQueryProvider($registry);
        $card = $provider->card_for_view('orders', 31);

        assert_same('orders', $card['key'] ?? null);
        assert_same('available', $card['availability'] ?? null);
        assert_same([], $card['items'] ?? null);
        assert_same([], $card['actions'] ?? null);

        $registry->calls = 0;
        $query_one = $provider->query_for_view('orders', 31);
        $query_two = $provider->query_for_view('orders', 32);
        assert_same(0, $registry->calls);

        $key_one = $provider->filter_cache_key('bricks_loop', (object) ['query_vars' => $query_one]);
        $key_two = $provider->filter_cache_key('bricks_loop', (object) ['query_vars' => $query_two]);

        assert_not_same($key_one, $key_two);
        assert_same([0], $query_one['post__in'] ?? null);
        assert_same([0], $query_two['post__in'] ?? null);

        $result = $provider->run_query([], (object) [
            'object_type' => DashboardQueryProvider::QUERY_TYPE,
            'query_vars' => $query_one,
        ]);
        assert_same(1, $registry->calls);
        assert_same('orders', $result[0]['key'] ?? null);
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
                'availability' => 'available', 'content_state' => 'populated',
                'summary'  => 'Aktief',
                'items'    => [],
                'actions'  => [],
            ],
            'member-area' => [
                'key'      => 'member-area',
                'label'    => 'Lede-area',
                'visible'  => true,
                'availability' => 'available', 'content_state' => 'populated',
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

    public function test_dashboard_dynamic_data_refuses_private_tokens_on_public_requests(): void
    {
        $request_uri = $_SERVER['REQUEST_URI'] ?? null;
        $_SERVER['REQUEST_URI'] = '/content/open-post/';

        try {
            $registry = new VgpcDashboardRegistryFixture([
                'membership' => [
                    'key'           => 'membership',
                    'label'         => 'Lidmaatskap',
                    'visible'       => true,
                    'availability'  => 'available',
                    'content_state' => 'populated',
                    'summary'       => 'Privaat waarde',
                    'items'         => [],
                    'actions'       => [],
                ],
            ]);
            $dynamic = new DashboardDynamicData($registry, new VgpcDynamicCachePolicyFixture());

            assert_same(
                '',
                $dynamic->render_tag('{vg_dashboard_membership_summary}', null, 'text')
            );
            assert_same(
                'Welkom ',
                $dynamic->render_content('Welkom {vg_dashboard_membership_summary}', null, 'text')
            );
        } finally {
            if ($request_uri === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $request_uri;
            }
        }
    }

    public function test_dashboard_dynamic_data_escapes_by_bricks_context_and_marks_the_request_private(): void
    {
        $request_uri = $_SERVER['REQUEST_URI'] ?? null;
        $_SERVER['REQUEST_URI'] = '/my-voelgoed/';
        $policy = new VgpcDynamicCachePolicyFixture();

        try {
            $dynamic = new DashboardDynamicData(
                new VgpcDashboardRegistryFixture([
                    'membership' => [
                        'key'           => 'membership',
                        'label'         => 'Lidmaatskap',
                        'visible'       => true,
                        'availability'  => 'available',
                        'content_state' => 'populated',
                        'summary'       => 'A & "B"',
                    'items'         => [],
                    'actions'       => [],
                ],
                    'member-area' => [
                        'key'           => 'member-area',
                        'label'         => 'Lede-area',
                        'visible'       => true,
                        'availability'  => 'available',
                        'content_state' => 'populated',
                        'summary'       => 'Beskikbaar',
                        'items'         => [],
                        'actions'       => [[
                            'key'   => 'open',
                            'label' => 'Maak oop',
                            'url'   => '/my-voordele/?q=a&b=1',
                        ]],
                    ],
                ]),
                $policy
            );

            assert_same(
                'A &amp; &quot;B&quot;',
                $dynamic->render_tag('{vg_dashboard_membership_summary}', null, 'text')
            );
            assert_same(
                'A &amp; &quot;B&quot;',
                $dynamic->render_tag('{vg_dashboard_membership_summary}', null, 'attribute')
            );
            assert_same(
                'Welkom A &amp; &quot;B&quot;',
                $dynamic->render_content('Welkom {vg_dashboard_membership_summary}', null, 'text')
            );
            assert_same(
                '/my-voordele/?q=a&amp;b=1',
                $dynamic->render_tag('{vg_dashboard_member_area_url}', null, 'link')
            );
            assert_same(4, $policy->apply_calls);
        } finally {
            if ($request_uri === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $request_uri;
            }
        }
    }

    public function test_dashboard_dynamic_data_fails_closed_for_logged_out_dashboard_requests(): void
    {
        $request_uri = $_SERVER['REQUEST_URI'] ?? null;
        $_SERVER['REQUEST_URI'] = '/my-voelgoed/';
        vgpc_set_current_user_id(0);

        try {
            $dynamic = new DashboardDynamicData(new VgpcDashboardRegistryFixture([]));

            assert_same('', $dynamic->render_tag('{vg_dashboard_membership_summary}', null, 'text'));
            assert_same('Welkom ', $dynamic->render_content('Welkom {vg_dashboard_membership_summary}', null, 'text'));
        } finally {
            vgpc_set_current_user_id(42);
            if ($request_uri === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $request_uri;
            }
        }
    }

    public function test_dashboard_routes_are_noindex(): void
    {
        $request_uri = $_SERVER['REQUEST_URI'] ?? null;
        $_SERVER['REQUEST_URI'] = '/my-voelgoed/orders/';

        try {
            $policy = new CachePolicy();
            $robots = $policy->robots(['index' => true, 'follow' => true]);

            assert_true(isset($robots['noindex']));
            assert_false(array_key_exists('index', $robots));
        } finally {
            if ($request_uri === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $request_uri;
            }
        }
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

    public function test_cache_policy_does_not_register_unconfirmed_layer_hooks(): void
    {
        assert_true(class_exists(CachePolicy::class));

        $GLOBALS['vgpc_actions'] = [];
        $policy = new CachePolicy();
        assert_same([], $policy->installed_page_cache_hooks());
        $policy->register();
        assert_same([], $GLOBALS['vgpc_actions']['litespeed_control_finalize'] ?? []);
    }

    public function test_cache_policy_sends_headers_and_notifies_a_detected_litespeed_layer(): void
    {
        assert_true(class_exists(CachePolicy::class));

        if (! defined('LSCWP_V')) {
            define('LSCWP_V', 'test');
        }

        $headers = [];
        $GLOBALS['vgpc_do_actions'] = [];
        $policy = new CachePolicy(
            static function (string $name, string $value) use (&$headers): void {
                $headers[$name] = $value;
            }
        );

        assert_same([
            'litespeed_control_finalize',
            'litespeed_control_set_nocache',
            'litespeed_control_set_private',
        ], $policy->installed_page_cache_hooks());
        assert_same(4, count($policy->headers()));
        assert_true($policy->send_no_cache_headers('/my-voelgoed/orders/'));
        assert_same($policy->headers(), $headers);
        assert_same([
            'litespeed_control_set_nocache',
            'litespeed_control_set_private',
        ], array_column($GLOBALS['vgpc_do_actions'], 'hook'));

        $GLOBALS['vgpc_actions'] = [];
        $policy->register();
        assert_true(count($GLOBALS['vgpc_actions']['litespeed_control_finalize'] ?? []) === 1);
        assert_false($policy->send_no_cache_headers('/content/example/'));
    }
}

vgpc_set_current_user_id(42);
