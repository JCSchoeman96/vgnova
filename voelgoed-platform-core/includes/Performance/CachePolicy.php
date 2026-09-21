<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Performance;

use Throwable;

/**
 * Prevents shared caches from storing authenticated dashboard responses.
 */
final class CachePolicy
{
    private const CACHE_CONTROL = 'private, no-store, no-cache, must-revalidate, max-age=0';

    private const LITESPEED_REASON = 'Voelgoed dashboard is user-specific.';

    private static bool $registered = false;

    private static ?self $registered_instance = null;

    /**
     * @var callable|null
     */
    private $header_sender;

    /**
     * @param callable|null $header_sender Optional response-header sink for tests.
     */
    public function __construct(?callable $header_sender = null)
    {
        $this->header_sender = $header_sender;
    }

    public function register(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        if (self::$registered) {
            if (
                ! function_exists('has_action')
                || self::$registered_instance === null
                || $this->registered_hooks_are_present(self::$registered_instance)
            ) {
                return;
            }

            self::$registered = false;
            self::$registered_instance = null;
        }

        add_action('template_redirect', [$this, 'protect_request'], 0);
        add_action('send_headers', [$this, 'send_no_cache_headers'], 0);

        if (function_exists('add_filter')) {
            add_filter('wp_robots', [$this, 'robots'], 10, 1);
        }

        if ($this->litespeed_is_available()) {
            add_action('litespeed_control_finalize', [$this, 'litespeed_finalize'], 1, 1);
        }

        self::$registered = true;
        self::$registered_instance = $this;
    }

    /**
     * Apply the application-level no-cache signal to a dashboard request.
     */
    public function apply(?string $request_uri = null): bool
    {
        if (! $this->is_dashboard_request($request_uri)) {
            return false;
        }

        if (! defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }

        $this->notify_litespeed();

        return true;
    }

    public function protect_request(): bool
    {
        return $this->apply();
    }

    /**
     * Send private response headers. WordPress passes its WP object here.
     *
     * @param mixed $request_uri
     */
    public function send_no_cache_headers(mixed $request_uri = null): bool
    {
        $request_uri = is_string($request_uri) ? $request_uri : null;
        if (! $this->apply($request_uri)) {
            return false;
        }

        foreach ($this->headers() as $name => $value) {
            if ($this->header_sender !== null) {
                ($this->header_sender)($name, $value);
                continue;
            }

            if (function_exists('headers_sent') && headers_sent()) {
                break;
            }

            header($name . ': ' . $value, true);
        }

        return true;
    }

    /**
     * LiteSpeed calls this after the response has rendered and before headers.
     * The hook name is the installed plugin's documented cache-control API.
     *
     * @param mixed $esi_id
     */
    public function litespeed_finalize($esi_id = false): void
    {
        unset($esi_id);

        if ($this->is_dashboard_request()) {
            $this->apply();
        }
    }

    /**
     * Keep authenticated dashboard routes out of search indexes.
     *
     * @param array<string, mixed> $robots
     * @return array<string, mixed>
     */
    public function robots(array $robots): array
    {
        if (! $this->is_dashboard_request()) {
            return $robots;
        }

        if (function_exists('wp_robots_no_robots')) {
            return \wp_robots_no_robots($robots);
        }

        $robots['noindex'] = true;
        unset($robots['index']);

        return $robots;
    }

    public function is_dashboard_request(?string $request_uri = null): bool
    {
        $request_uri = $request_uri ?? $this->request_uri();
        if (! is_string($request_uri) || $request_uri === '') {
            return false;
        }

        $path = parse_url($request_uri, PHP_URL_PATH);
        if (! is_string($path)) {
            return false;
        }

        $path = rtrim($path, '/');

        return $path === '/my-voelgoed' || str_starts_with($path, '/my-voelgoed/');
    }

    public function cache_control_header(): string
    {
        return self::CACHE_CONTROL;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return [
            'Cache-Control' => self::CACHE_CONTROL,
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
            'Vary'          => 'Cookie',
        ];
    }

    /**
     * Return the page-cache hooks confirmed for the installed LiteSpeed layer.
     *
     * The local staging plugin directory does not contain LiteSpeed Cache. The
     * list stays empty until the runtime detects the layer through its known
     * constant or class. No cache hook is registered without that signal.
     *
     * @return array<int, string>
     */
    public function installed_page_cache_hooks(): array
    {
        if (! $this->litespeed_is_available()) {
            return [];
        }

        return [
            'litespeed_control_finalize',
            'litespeed_control_set_nocache',
            'litespeed_control_set_private',
        ];
    }

    private function notify_litespeed(): void
    {
        if (! $this->litespeed_is_available() || ! function_exists('do_action')) {
            return;
        }

        do_action('litespeed_control_set_nocache', self::LITESPEED_REASON);
        do_action('litespeed_control_set_private', self::LITESPEED_REASON);
    }

    private function litespeed_is_available(): bool
    {
        return defined('LSCWP_V')
            || defined('LSCACHE_V')
            || class_exists('LiteSpeed_Cache_API')
            || class_exists('LiteSpeed\\Core');
    }

    private function request_uri(): string
    {
        if (! isset($_SERVER['REQUEST_URI']) || ! is_scalar($_SERVER['REQUEST_URI'])) {
            return '';
        }

        try {
            return (string) $_SERVER['REQUEST_URI'];
        } catch (Throwable) {
            return '';
        }
    }

    private function registered_hooks_are_present(self $instance): bool
    {
        $hooks = [
            ['template_redirect', [$instance, 'protect_request']],
            ['send_headers', [$instance, 'send_no_cache_headers']],
        ];

        if (function_exists('has_filter')) {
            if (has_filter('wp_robots', [$instance, 'robots']) === false) {
                return false;
            }
        }

        if ($this->litespeed_is_available()) {
            $hooks[] = ['litespeed_control_finalize', [$instance, 'litespeed_finalize']];
        }

        foreach ($hooks as [$hook, $callback]) {
            if (has_action($hook, $callback) === false) {
                return false;
            }
        }

        return true;
    }
}
