<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Bricks;

use Throwable;
use Voelgoed\PlatformCore\Config\AfrikaansLabels;
use Voelgoed\PlatformCore\Contracts\DashboardCard;
use Voelgoed\PlatformCore\Dashboard\DashboardContext;
use Voelgoed\PlatformCore\Dashboard\DashboardRegistry;

/**
 * Provides authenticated dashboard cards to Bricks query loops.
 */
final class DashboardQueryProvider
{
    public const QUERY_TYPE = 'vg_dashboard_card';

    public const META_QUERY_TYPE = '_vgpc_query_type';

    public const META_USER_ID = '_vgpc_user_id';

    public const META_VIEW_KEY = '_vgpc_view_key';

    /**
     * Compatibility alias for code that calls the view marker a context key.
     */
    public const META_CONTEXT_KEY = self::META_VIEW_KEY;

    /**
     * @var array<int, string>
     */
    private const CARD_KEYS = [
        DashboardRegistry::MEMBERSHIP,
        DashboardRegistry::SUBSCRIPTIONS,
        DashboardRegistry::ORDERS,
        DashboardRegistry::TICKETS,
        DashboardRegistry::LEARNING,
        DashboardRegistry::MEMBER_AREA,
    ];

    private object $registry;

    private bool $init_hook_registered = false;

    private bool $hooks_registered = false;

    public function __construct(?object $registry = null)
    {
        $this->registry = $registry ?? new DashboardRegistry();
    }

    public function register(): void
    {
        if ($this->init_hook_registered || ! function_exists('add_action')) {
            return;
        }

        add_action('init', [$this, 'register_hooks'], 20);
        $this->init_hook_registered = true;
    }

    public function register_hooks(): void
    {
        if ($this->hooks_registered || ! $this->is_bricks_available()) {
            return;
        }

        if (! function_exists('add_filter')) {
            return;
        }

        add_filter('bricks/posts/query_vars', [$this, 'filter_query_vars'], 10, 4);
        add_filter('bricks/query/cache_key', [$this, 'filter_cache_key'], 10, 2);
        add_filter('bricks/query/run', [$this, 'run_query'], 10, 2);
        $this->hooks_registered = true;
    }

    /**
     * @param array<string, mixed> $query_vars
     * @param mixed                 $settings
     * @return array<string, mixed>
     */
    public function filter_query_vars($query_vars, $settings, $element_id = null, $element_name = null): array
    {
        unset($element_id, $element_name);

        if (! is_array($query_vars) || ! is_array($settings)) {
            return is_array($query_vars) ? $query_vars : [];
        }

        $query = is_array($settings['query'] ?? null) ? $settings['query'] : $settings;
        if ($this->query_type($query) !== self::QUERY_TYPE) {
            return $query_vars;
        }

        $view_key = $this->view_key_from_query($query);
        $user_id = $this->current_user_id();

        return $this->query_for_view($view_key, $user_id);
    }

    /**
     * Return an isolated WP query shape for a card view.
     *
     * A zero post ID prevents a normal Posts loop from falling back to public
     * content. Authenticated custom query execution is handled by run_query().
     *
     * @return array<string, mixed>
     */
    public function query_for_view(string $view_key, ?int $user_id = null): array
    {
        $view_key = $this->normalize_card_key($view_key);
        $user_id = $user_id === null ? $this->current_user_id() : max(0, $user_id);

        $query = [
            'post__in'                 => [0],
            'posts_per_page'           => 1,
            self::META_QUERY_TYPE      => self::QUERY_TYPE,
            self::META_USER_ID         => $user_id,
            self::META_VIEW_KEY        => $view_key,
            'vg_query_type'            => self::QUERY_TYPE,
            'vg_dashboard_view'        => $view_key,
        ];

        if ($view_key === '' || $user_id < 1 || ! $this->is_authenticated()) {
            return $query;
        }

        $card = $this->card_for_view($view_key, $user_id);
        if ($card === []) {
            return $query;
        }

        return $query;
    }

    /**
     * Resolve one authenticated card. Missing providers fail closed with a
     * valid empty card rather than borrowing another provider's output.
     *
     * @return array<string, mixed>
     */
    public function card_for_view(string $view_key, ?int $user_id = null): array
    {
        $view_key = $this->normalize_card_key($view_key);
        $user_id = $user_id === null ? $this->current_user_id() : max(0, $user_id);

        if ($view_key === '' || $user_id < 1 || ! $this->is_authenticated($user_id)) {
            return [];
        }

        $card = null;
        if (method_exists($this->registry, 'card')) {
            try {
                $card = $this->registry->card(
                    $view_key,
                    DashboardContext::for_user($user_id, $view_key)
                );
            } catch (Throwable) {
                $card = null;
            }
        }

        if (! is_array($card)) {
            return $this->empty_card($view_key);
        }

        try {
            return DashboardCard::make(
                $view_key,
                $this->scalar($card['label'] ?? $this->label_for_card($view_key)),
                $this->scalar($card['state'] ?? 'empty'),
                $this->scalar($card['summary'] ?? ''),
                is_array($card['actions'] ?? null) ? $card['actions'] : [],
                is_array($card['items'] ?? null) ? $card['items'] : [],
                ($card['visible'] ?? true) === true
            );
        } catch (Throwable) {
            return $this->empty_card($view_key);
        }
    }

    /**
     * Handle Bricks custom query execution for `vg_dashboard_card` loops.
     *
     * @param mixed $results
     * @param mixed $query
     * @return mixed
     */
    public function run_query($results, $query)
    {
        if (! is_object($query)) {
            return $results;
        }

        $query_vars = is_array($query->query_vars ?? null) ? $query->query_vars : [];
        $settings = is_array($query->settings ?? null) ? $query->settings : [];
        $settings_query = is_array($settings['query'] ?? null) ? $settings['query'] : $settings;
        $type = (string) ($query->object_type ?? $this->query_type($query_vars));

        if ($type !== self::QUERY_TYPE && $this->query_type($settings_query) !== self::QUERY_TYPE) {
            return $results;
        }

        $view_key = (string) (
            $query_vars[self::META_VIEW_KEY]
            ?? $query_vars['vg_dashboard_view']
            ?? $this->view_key_from_query($settings_query)
        );
        $user_id = isset($query_vars[self::META_USER_ID])
            ? max(0, (int) $query_vars[self::META_USER_ID])
            : $this->current_user_id();
        $card = $this->card_for_view($view_key, $user_id);

        return $card === [] ? [] : [$card];
    }

    /**
     * @param mixed $cache_key
     * @param mixed $query
     */
    public function filter_cache_key($cache_key, $query): string
    {
        $base_key = is_scalar($cache_key) ? (string) $cache_key : '';
        if (! is_object($query)) {
            return $base_key;
        }

        $query_vars = is_array($query->query_vars ?? null) ? $query->query_vars : [];
        $settings = is_array($query->settings ?? null) ? $query->settings : [];
        $settings_query = is_array($settings['query'] ?? null) ? $settings['query'] : $settings;
        $type = (string) ($query->object_type ?? $this->query_type($query_vars));

        if (
            $type !== self::QUERY_TYPE
            && $this->query_type($query_vars) !== self::QUERY_TYPE
            && $this->query_type($settings_query) !== self::QUERY_TYPE
        ) {
            return $base_key;
        }

        $user_id = isset($query_vars[self::META_USER_ID])
            ? max(0, (int) $query_vars[self::META_USER_ID])
            : $this->current_user_id();
        $view_key = (string) (
            $query_vars[self::META_VIEW_KEY]
            ?? $query_vars['vg_dashboard_view']
            ?? $this->view_key_from_query($settings_query)
        );
        $view_key = $this->normalize_card_key($view_key);
        $view_marker = $view_key === '' ? 'empty' : $view_key;

        return $base_key
            . '_vgpc_u' . $user_id
            . '_v' . $view_marker
            . '_' . md5($view_marker);
    }

    public function cache_key($cache_key, $query): string
    {
        return $this->filter_cache_key($cache_key, $query);
    }

    private function query_type(array $query): string
    {
        $type = $query['vg_query_type'] ?? $query[self::META_QUERY_TYPE] ?? $query['query_type'] ?? '';

        return is_scalar($type) ? trim((string) $type) : '';
    }

    private function view_key_from_query(array $query): string
    {
        foreach (['vg_dashboard_view', 'vg_dashboard_view_key', 'dashboard_view', 'view_key', 'view'] as $key) {
            if (isset($query[$key]) && is_scalar($query[$key])) {
                return $this->normalize_card_key((string) $query[$key]);
            }
        }

        return $this->normalize_card_key(DashboardContext::current()->view_key());
    }

    private function normalize_card_key(string $view_key): string
    {
        $view_key = strtolower(trim($view_key));
        $view_key = str_replace('_', '-', $view_key);

        return in_array($view_key, self::CARD_KEYS, true) ? $view_key : '';
    }

    private function empty_card(string $key): array
    {
        return DashboardCard::empty($key, $this->label_for_card($key), 'empty', '');
    }

    private function label_for_card(string $key): string
    {
        return match ($key) {
            DashboardRegistry::MEMBERSHIP => AfrikaansLabels::MEMBERSHIP,
            DashboardRegistry::SUBSCRIPTIONS => AfrikaansLabels::SUBSCRIPTIONS,
            DashboardRegistry::ORDERS => AfrikaansLabels::ORDERS,
            DashboardRegistry::TICKETS => AfrikaansLabels::TICKETS,
            DashboardRegistry::LEARNING => AfrikaansLabels::LEARNING,
            DashboardRegistry::MEMBER_AREA => AfrikaansLabels::MEMBER_AREA,
            default => 'Voelgoed Dashboard',
        };
    }

    private function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function current_user_id(): int
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

    private function is_authenticated(?int $user_id = null): bool
    {
        $user_id = $user_id ?? $this->current_user_id();
        if ($user_id < 1 || $this->current_user_id() !== $user_id) {
            return false;
        }

        if (! function_exists('is_user_logged_in')) {
            return true;
        }

        try {
            return (bool) is_user_logged_in();
        } catch (Throwable) {
            return false;
        }
    }

    private function is_bricks_available(): bool
    {
        return class_exists('Bricks\\Query');
    }
}
