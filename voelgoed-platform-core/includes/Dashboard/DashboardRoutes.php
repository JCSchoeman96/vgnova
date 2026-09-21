<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Dashboard;

/**
 * Keeps the dashboard child pages addressable when a provider endpoint uses
 * the same slug as a child page (Woo Subscriptions uses `subscriptions`).
 */
final class DashboardRoutes
{
    private bool $registered = false;

    public function register(): void
    {
        if ($this->registered || ! function_exists('add_filter')) {
            return;
        }

        add_filter('request', [$this, 'filter_request'], 20, 1);
        $this->registered = true;
    }

    /**
     * @param array<string, mixed> $query_vars
     * @return array<string, mixed>
     */
    public function filter_request(array $query_vars): array
    {
        $view_key = $this->view_key_from_request();
        if ($view_key === DashboardContext::OVERVIEW || ! function_exists('get_page_by_path')) {
            return $query_vars;
        }

        $parent = get_page_by_path('my-voelgoed', OBJECT, 'page');
        $page = get_page_by_path('my-voelgoed/' . $view_key, OBJECT, 'page');
        if (! is_object($parent) || ! is_object($page)) {
            return $query_vars;
        }

        if ((int) ($page->post_parent ?? 0) !== (int) ($parent->ID ?? 0)) {
            return $query_vars;
        }

        // Replace endpoint-derived vars with the resolved child page. This
        // keeps the native Woo endpoint under /my-account/ authoritative.
        return ['page_id' => (int) $page->ID];
    }

    private function view_key_from_request(): string
    {
        if (! isset($_SERVER['REQUEST_URI']) || ! is_scalar($_SERVER['REQUEST_URI'])) {
            return DashboardContext::OVERVIEW;
        }

        $path = parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (! is_string($path)) {
            return DashboardContext::OVERVIEW;
        }

        $segments = array_values(array_filter(
            explode('/', trim($path, '/')),
            static fn (string $segment): bool => $segment !== ''
        ));
        if (count($segments) !== 2 || ($segments[0] ?? '') !== 'my-voelgoed') {
            return DashboardContext::OVERVIEW;
        }

        return DashboardContext::normalize_view_key((string) ($segments[1] ?? DashboardContext::OVERVIEW));
    }
}
