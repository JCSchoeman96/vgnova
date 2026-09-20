<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Dashboard;

use Throwable;

/**
 * Protects dashboard routes and creates a validated login return URL.
 */
final class AccessGuard
{
    /**
     * @var callable|null
     */
    private $authentication_checker;

    /**
     * @var callable|null
     */
    private $redirector;

    private bool $terminate_on_redirect;

    private bool $registered = false;

    /**
     * @param callable|null $authentication_checker Optional testable login check.
     * @param callable|null $redirector Optional redirect sink, mainly for tests.
     */
    public function __construct(
        ?callable $authentication_checker = null,
        ?callable $redirector = null,
        bool $terminate_on_redirect = true
    ) {
        $this->authentication_checker = $authentication_checker;
        $this->redirector = $redirector;
        $this->terminate_on_redirect = $terminate_on_redirect;
    }

    public function register(): void
    {
        if ($this->registered || ! function_exists('add_action')) {
            return;
        }

        add_action('template_redirect', [$this, 'guard_dashboard'], 1);
        $this->registered = true;
    }

    public function is_authenticated(): bool
    {
        if ($this->authentication_checker !== null) {
            try {
                return (bool) ($this->authentication_checker)();
            } catch (Throwable) {
                return false;
            }
        }

        if (function_exists('is_user_logged_in')) {
            try {
                return (bool) is_user_logged_in();
            } catch (Throwable) {
                return false;
            }
        }

        if (function_exists('get_current_user_id')) {
            try {
                return (int) get_current_user_id() > 0;
            } catch (Throwable) {
                return false;
            }
        }

        return false;
    }

    /**
     * Hook callback that leaves non-dashboard requests alone.
     */
    public function guard_dashboard(): bool
    {
        $request_uri = $this->request_uri();
        if (! $this->is_dashboard_request($request_uri)) {
            return true;
        }

        return $this->enforce($request_uri);
    }

    /**
     * Guard a dashboard request. Returns false after a test redirect sink or
     * when termination is disabled; production redirects terminate execution.
     */
    public function enforce(?string $request_uri = null): bool
    {
        if ($this->is_authenticated()) {
            return true;
        }

        $login_url = $this->login_url($request_uri);
        $this->redirect($login_url);

        if ($this->terminate_on_redirect) {
            exit;
        }

        return false;
    }

    public function guard(?string $request_uri = null): bool
    {
        return $this->enforce($request_uri);
    }

    public function login_url(?string $request_uri = null): string
    {
        $return_url = $this->return_url($request_uri);

        if (function_exists('wp_login_url')) {
            try {
                $login_url = wp_login_url($return_url);
                if (is_scalar($login_url) && (string) $login_url !== '') {
                    return (string) $login_url;
                }
            } catch (Throwable) {
            }
        }

        return $return_url;
    }

    public function return_url(?string $request_uri = null): string
    {
        $fallback = $this->dashboard_root();
        $candidate = $request_uri ?? $this->request_uri();
        if (! is_string($candidate) || $candidate === '') {
            return $fallback;
        }

        if (function_exists('wp_unslash')) {
            try {
                $candidate = (string) wp_unslash($candidate);
            } catch (Throwable) {
                return $fallback;
            }
        }

        if (! $this->is_safe_return_target($candidate)) {
            return $fallback;
        }

        $validated = $candidate;
        if (function_exists('wp_validate_redirect')) {
            try {
                $validated = wp_validate_redirect($candidate, $fallback);
            } catch (Throwable) {
                return $fallback;
            }
        }

        if (! is_scalar($validated) || ! $this->is_safe_return_target((string) $validated)) {
            return $fallback;
        }

        return (string) $validated;
    }

    public function validate_return_url(?string $request_uri = null): string
    {
        return $this->return_url($request_uri);
    }

    public function is_dashboard_request(?string $request_uri = null): bool
    {
        $request_uri = $request_uri ?? $this->request_uri();
        $path = parse_url($request_uri, PHP_URL_PATH);
        if (! is_string($path)) {
            return false;
        }

        $path = rtrim($path, '/');

        return $path === '/my-voelgoed' || str_starts_with($path, '/my-voelgoed/');
    }

    private function redirect(string $url): void
    {
        if ($this->redirector !== null) {
            ($this->redirector)($url);

            return;
        }

        if (function_exists('wp_safe_redirect')) {
            wp_safe_redirect($url);
        }
    }

    private function dashboard_root(): string
    {
        if (function_exists('home_url')) {
            try {
                $url = home_url('/my-voelgoed/');
                if (is_scalar($url) && (string) $url !== '') {
                    return (string) $url;
                }
            } catch (Throwable) {
            }
        }

        return '/my-voelgoed/';
    }

    private function request_uri(): string
    {
        return isset($_SERVER['REQUEST_URI']) && is_scalar($_SERVER['REQUEST_URI'])
            ? (string) $_SERVER['REQUEST_URI']
            : '/my-voelgoed/';
    }

    private function is_safe_return_target(string $candidate): bool
    {
        if ($candidate === '' || preg_match('/[\x00-\x20\x7f]/', $candidate) === 1 || str_contains($candidate, '\\')) {
            return false;
        }

        $decoded_candidate = rawurldecode($candidate);
        if (str_starts_with($decoded_candidate, '//') || str_contains($decoded_candidate, '\\')) {
            return false;
        }

        $parts = parse_url($candidate);
        if (! is_array($parts)) {
            return false;
        }

        if (str_starts_with($candidate, '/')) {
            return ! str_starts_with($candidate, '//')
                && ($parts['path'] ?? '') !== ''
                && ! isset($parts['scheme'], $parts['host'], $parts['user'], $parts['pass']);
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        if (! function_exists('home_url')) {
            return false;
        }

        try {
            $site = parse_url((string) home_url('/'));
        } catch (Throwable) {
            return false;
        }

        if (! is_array($site)) {
            return false;
        }

        $site_scheme = strtolower((string) ($site['scheme'] ?? ''));
        $site_host = strtolower((string) ($site['host'] ?? ''));
        if ($scheme !== $site_scheme || $host !== $site_host) {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $candidate_port = isset($parts['port']) ? (int) $parts['port'] : null;
        $site_port = isset($site['port']) ? (int) $site['port'] : null;

        return $candidate_port === $site_port || ($candidate_port === null && $site_port === null);
    }
}
