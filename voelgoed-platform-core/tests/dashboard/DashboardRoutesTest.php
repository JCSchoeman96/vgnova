<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Dashboard\DashboardRoutes;

if (! defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

if (! function_exists('get_page_by_path')) {
    function get_page_by_path(string $path, mixed $output = null, mixed $post_type = null): mixed
    {
        return ($GLOBALS['vgpc_pages_by_path'] ?? [])[$path] ?? null;
    }
}

final class DashboardRoutesTest
{
    public function test_exact_dashboard_child_resolves(): void
    {
        $this->set_pages(1155, 1157);

        assert_same(
            ['page_id' => 1157],
            $this->filter('/my-voelgoed/subscriptions/')
        );
    }

    public function test_exact_dashboard_child_with_query_string_resolves(): void
    {
        $this->set_pages(1155, 1157);

        assert_same(
            ['page_id' => 1157],
            $this->filter('/my-voelgoed/subscriptions/?view=history')
        );
    }

    public function test_native_account_route_remains_untouched(): void
    {
        $this->set_pages(1155, 1157);
        $query_vars = ['pagename' => 'my-account/subscriptions'];

        assert_same($query_vars, $this->filter('/my-account/subscriptions/', $query_vars));
    }

    public function test_unknown_dashboard_child_remains_untouched(): void
    {
        $this->set_pages(1155, null);
        $query_vars = ['pagename' => 'my-voelgoed/unknown'];

        assert_same($query_vars, $this->filter('/my-voelgoed/unknown/', $query_vars));
    }

    public function test_nested_dashboard_child_remains_untouched(): void
    {
        $this->set_pages(1155, 1157);
        $query_vars = ['pagename' => 'my-voelgoed/subscriptions/123'];

        assert_same($query_vars, $this->filter('/my-voelgoed/subscriptions/123/', $query_vars));
    }

    public function test_child_with_wrong_parent_remains_untouched(): void
    {
        $this->set_pages(9999, 1157, 1155);
        $query_vars = ['pagename' => 'my-voelgoed/subscriptions'];

        assert_same($query_vars, $this->filter('/my-voelgoed/subscriptions/', $query_vars));
    }

    public function test_dashboard_root_remains_untouched(): void
    {
        $this->set_pages(1155, null);
        $query_vars = ['page_id' => 1155];

        assert_same($query_vars, $this->filter('/my-voelgoed/', $query_vars));
    }

    /**
     * @param array<string, mixed> $query_vars
     * @return array<string, mixed>
     */
    private function filter(string $request_uri, array $query_vars = []): array
    {
        $_SERVER['REQUEST_URI'] = $request_uri;

        return (new DashboardRoutes())->filter_request($query_vars);
    }

    private function set_pages(int $parent_id, ?int $child_id, ?int $child_parent_id = null): void
    {
        $pages = [
            'my-voelgoed' => (object) ['ID' => $parent_id],
        ];

        if ($child_id !== null) {
            $pages['my-voelgoed/subscriptions'] = (object) [
                'ID'         => $child_id,
                'post_parent' => $child_parent_id ?? $parent_id,
            ];
        }

        $GLOBALS['vgpc_pages_by_path'] = $pages;
    }
}
