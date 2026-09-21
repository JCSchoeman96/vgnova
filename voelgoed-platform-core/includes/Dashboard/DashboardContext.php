<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Dashboard;

use Throwable;

/**
 * Carries the authenticated user and the dashboard view being rendered.
 */
final class DashboardContext
{
    public const OVERVIEW = 'overview';
    public const MEMBERSHIP = 'membership';
    public const SUBSCRIPTIONS = 'subscriptions';
    public const ORDERS = 'orders';
    public const TICKETS = 'tickets';
    public const LEARNING = 'learning';
    public const PROFILE = 'profile';
    public const PAYMENT_METHODS = 'payment-methods';

    /**
     * @var array<int, string>
     */
    private const VIEW_KEYS = [
        self::OVERVIEW,
        self::MEMBERSHIP,
        self::SUBSCRIPTIONS,
        self::ORDERS,
        self::TICKETS,
        self::LEARNING,
        self::PROFILE,
        self::PAYMENT_METHODS,
    ];

    private int $user_id;

    private string $view_key;

    public function __construct(?int $user_id = null, string $view_key = self::OVERVIEW)
    {
        $this->user_id = $user_id === null ? self::current_user_id() : max(0, $user_id);
        $this->view_key = self::normalize_view_key($view_key);
    }

    public static function current(?string $view_key = null): self
    {
        return new self(null, $view_key ?? self::view_key_from_request());
    }

    public static function for_user(int $user_id, string $view_key = self::OVERVIEW): self
    {
        return new self($user_id, $view_key);
    }

    public function user_id(): int
    {
        return $this->user_id;
    }

    public function get_user_id(): int
    {
        return $this->user_id();
    }

    public function view_key(): string
    {
        return $this->view_key;
    }

    public function key(): string
    {
        return $this->view_key();
    }

    public function is_authenticated(): bool
    {
        return $this->user_id > 0;
    }

    public function is_view(string $view_key): bool
    {
        return $this->view_key === self::normalize_view_key($view_key);
    }

    public function with_view(string $view_key): self
    {
        return new self($this->user_id, $view_key);
    }

    /**
     * @return array<int, string>
     */
    public static function view_keys(): array
    {
        return self::VIEW_KEYS;
    }

    public static function normalize_view_key(string $view_key): string
    {
        $view_key = strtolower(trim($view_key));
        $view_key = trim($view_key, " \t\n\r\0\x0B/");
        $view_key = str_replace('_', '-', $view_key);

        if (str_starts_with($view_key, 'my-voelgoed/')) {
            $view_key = (string) substr($view_key, strlen('my-voelgoed/'));
            $view_key = trim($view_key, '/');
        }

        return in_array($view_key, self::VIEW_KEYS, true) ? $view_key : self::OVERVIEW;
    }

    private static function current_user_id(): int
    {
        if (! function_exists('get_current_user_id')) {
            return 0;
        }

        try {
            return max(0, (int) get_current_user_id());
        } catch (Throwable) {
            return 0;
        }
    }

    private static function view_key_from_request(): string
    {
        if (! isset($_SERVER['REQUEST_URI']) || ! is_scalar($_SERVER['REQUEST_URI'])) {
            return self::OVERVIEW;
        }

        $request_uri = (string) $_SERVER['REQUEST_URI'];
        $path = parse_url($request_uri, PHP_URL_PATH);
        if (! is_string($path)) {
            return self::OVERVIEW;
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn (string $segment): bool => $segment !== ''));
        if (($segments[0] ?? '') !== 'my-voelgoed') {
            return self::OVERVIEW;
        }

        return self::normalize_view_key((string) ($segments[1] ?? self::OVERVIEW));
    }
}
