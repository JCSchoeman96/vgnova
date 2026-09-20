<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Bricks;

use Throwable;
use Voelgoed\PlatformCore\Contracts\DashboardAction;
use Voelgoed\PlatformCore\Dashboard\DashboardContext;
use Voelgoed\PlatformCore\Dashboard\DashboardRegistry;

/**
 * Exposes the small, user-scoped values used by dashboard Bricks templates.
 */
final class DashboardDynamicData
{
    /**
     * @var array<string, string>
     */
    private const FIELD_LABELS = [
        'vg_dashboard_user_name'                => 'Voelgoed Dashboard: Gebruikersnaam',
        'vg_dashboard_membership_summary'      => 'Voelgoed Dashboard: Lidmaatskap',
        'vg_dashboard_subscription_summary'    => 'Voelgoed Dashboard: Subskripsies',
        'vg_dashboard_order_summary'            => 'Voelgoed Dashboard: Bestellings',
        'vg_dashboard_ticket_summary'           => 'Voelgoed Dashboard: Kaartjies',
        'vg_dashboard_learning_summary'         => 'Voelgoed Dashboard: Kursusse',
        'vg_dashboard_member_area_url'          => 'Voelgoed Dashboard: Lede-area URL',
        'vg_dashboard_member_area_visible'     => 'Voelgoed Dashboard: Lede-area sigbaarheid',
    ];

    /**
     * @var array<string, string>
     */
    private const CARD_FIELDS = [
        'vg_dashboard_membership_summary'   => DashboardRegistry::MEMBERSHIP,
        'vg_dashboard_subscription_summary' => DashboardRegistry::SUBSCRIPTIONS,
        'vg_dashboard_order_summary'        => DashboardRegistry::ORDERS,
        'vg_dashboard_ticket_summary'       => DashboardRegistry::TICKETS,
        'vg_dashboard_learning_summary'    => DashboardRegistry::LEARNING,
        'vg_dashboard_member_area_url'      => DashboardRegistry::MEMBER_AREA,
        'vg_dashboard_member_area_visible'  => DashboardRegistry::MEMBER_AREA,
    ];

    private object $registry;

    private static bool $init_hook_registered = false;

    private static ?self $init_hook_owner = null;

    private static bool $hooks_registered = false;

    private static ?self $hooks_owner = null;

    public function __construct(?object $registry = null)
    {
        $this->registry = $registry ?? new DashboardRegistry();
    }

    public function registry(): object
    {
        return $this->registry;
    }

    public function register(): void
    {
        if (! function_exists('add_action')) {
            return;
        }

        if (self::$init_hook_registered) {
            if (
                ! function_exists('has_action')
                || self::$init_hook_owner === null
                || has_action('init', [self::$init_hook_owner, 'register_hooks']) !== false
            ) {
                return;
            }

            self::$init_hook_registered = false;
            self::$init_hook_owner = null;
        }

        add_action('init', [$this, 'register_hooks'], 20);
        self::$init_hook_registered = true;
        self::$init_hook_owner = $this;
    }

    public function register_hooks(): void
    {
        if (! $this->is_bricks_available()) {
            return;
        }

        if (! function_exists('add_filter')) {
            return;
        }

        if (self::$hooks_registered) {
            if (
                ! function_exists('has_filter')
                || self::$hooks_owner === null
                || $this->filters_are_present(self::$hooks_owner)
            ) {
                return;
            }

            self::$hooks_registered = false;
            self::$hooks_owner = null;
        }

        add_filter('bricks/dynamic_tags_list', [$this, 'register_tags'], 10, 1);
        add_filter('bricks/dynamic_data/render_content', [$this, 'render_content'], 11, 3);
        add_filter('bricks/dynamic_data/render_tag', [$this, 'render_tag'], 20, 3);
        self::$hooks_registered = true;
        self::$hooks_owner = $this;
    }

    /**
     * @param array<int, array<string, mixed>> $tags
     * @return array<int, array<string, mixed>>
     */
    public function register_tags(array $tags): array
    {
        $registered_names = [];
        foreach ($tags as $tag) {
            if (is_array($tag) && isset($tag['name']) && is_scalar($tag['name'])) {
                $registered_names[] = (string) $tag['name'];
            }
        }

        foreach (self::FIELD_LABELS as $field => $label) {
            $name = '{' . $field . '}';
            if (in_array($name, $registered_names, true)) {
                continue;
            }

            $tags[] = [
                'name'  => $name,
                'label' => $label,
                'group' => 'Voelgoed Dashboard',
            ];
        }

        return $tags;
    }

    /**
     * Resolve dashboard tokens in text and custom-attribute content.
     *
     * @param mixed $content
     * @param mixed $post
     */
    public function render_content($content, $post = null, string $context = 'text')
    {
        unset($post, $context);

        if (! is_string($content) || ! str_contains($content, '{vg_dashboard_')) {
            return $content;
        }

        foreach (array_keys(self::FIELD_LABELS) as $field) {
            $token = '{' . $field . '}';
            if (! str_contains($content, $token)) {
                continue;
            }

            $content = str_replace($token, $this->value_for_field($field), $content);
        }

        return $content;
    }

    /**
     * Resolve one Bricks dynamic token.
     *
     * @param mixed $value
     * @param mixed $post
     */
    public function render_tag($value, $post = null, string $context = 'text')
    {
        unset($post, $context);

        $field = $this->normalize_field_name($value);
        if ($field === null) {
            return $value;
        }

        return $this->value_for_field($field);
    }

    /**
     * Resolve an approved dashboard field for the current authenticated user.
     */
    public function value_for_field(string $field): string
    {
        $field = $this->normalize_field_name($field);
        if ($field === null) {
            return '';
        }

        $user_id = $this->current_user_id();
        if ($user_id < 1) {
            return $field === 'vg_dashboard_member_area_visible' ? '0' : '';
        }

        if ($field === 'vg_dashboard_user_name') {
            return $this->current_user_name();
        }

        $card_key = self::CARD_FIELDS[$field] ?? null;
        if (! is_string($card_key)) {
            return '';
        }

        $card = $this->card($card_key, $user_id);
        if ($card === null) {
            return $field === 'vg_dashboard_member_area_visible' ? '0' : '';
        }

        if ($field === 'vg_dashboard_member_area_visible') {
            return ($card['visible'] ?? false) === true ? '1' : '0';
        }

        if ($field === 'vg_dashboard_member_area_url') {
            return $this->member_area_url($card);
        }

        return $this->display_value($card['summary'] ?? '');
    }

    /**
     * @param mixed $value
     */
    private function normalize_field_name($value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $candidate = trim($value);
        if (str_starts_with($candidate, '{') && str_ends_with($candidate, '}')) {
            $candidate = substr($candidate, 1, -1);
        }

        $candidate = explode(':', $candidate, 2)[0];

        return array_key_exists($candidate, self::FIELD_LABELS) ? $candidate : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function card(string $key, int $user_id): ?array
    {
        if (! method_exists($this->registry, 'card')) {
            return null;
        }

        try {
            $card = $this->registry->card($key, DashboardContext::for_user($user_id, $key));
        } catch (Throwable) {
            return null;
        }

        return is_array($card) ? $card : null;
    }

    /**
     * @param array<string, mixed> $card
     */
    private function member_area_url(array $card): string
    {
        if (($card['visible'] ?? false) !== true || ! is_array($card['actions'] ?? null)) {
            return '';
        }

        foreach ($card['actions'] as $action) {
            if (! is_array($action)) {
                continue;
            }

            try {
                $normalized = DashboardAction::normalize($action);
            } catch (Throwable) {
                continue;
            }

            $url = $normalized['url'] ?? '';
            if (! is_string($url) || $url === '') {
                continue;
            }

            return function_exists('esc_url') ? (string) esc_url($url) : $url;
        }

        return '';
    }

    private function current_user_id(): int
    {
        if (function_exists('is_user_logged_in')) {
            try {
                if (! is_user_logged_in()) {
                    return 0;
                }
            } catch (Throwable) {
                return 0;
            }
        }

        if (! function_exists('get_current_user_id')) {
            return 0;
        }

        try {
            return max(0, (int) get_current_user_id());
        } catch (Throwable) {
            return 0;
        }
    }

    private function current_user_name(): string
    {
        if (! function_exists('wp_get_current_user')) {
            return '';
        }

        try {
            $user = wp_get_current_user();
        } catch (Throwable) {
            return '';
        }

        if (! is_object($user)) {
            return '';
        }

        $name = '';
        foreach (['display_name', 'user_login'] as $property) {
            if (isset($user->{$property}) && is_scalar($user->{$property})) {
                $name = trim((string) $user->{$property});
                if ($name !== '') {
                    break;
                }
            }
        }

        return $this->display_value($name);
    }

    /**
     * @param mixed $value
     */
    private function display_value($value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $value = (string) $value;
        if (function_exists('sanitize_text_field')) {
            return trim((string) sanitize_text_field($value));
        }

        return trim(strip_tags($value));
    }

    private function is_bricks_available(): bool
    {
        return class_exists('Bricks\\Query');
    }

    private function filters_are_present(self $instance): bool
    {
        return has_filter('bricks/dynamic_tags_list', [$instance, 'register_tags']) !== false
            && has_filter('bricks/dynamic_data/render_content', [$instance, 'render_content']) !== false
            && has_filter('bricks/dynamic_data/render_tag', [$instance, 'render_tag']) !== false;
    }
}
