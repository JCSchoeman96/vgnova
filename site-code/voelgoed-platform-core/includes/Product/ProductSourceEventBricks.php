<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Product;

defined('ABSPATH') || exit;

/** Adapts the independent Product source Event field to its marked Bricks query. */
final class ProductSourceEventBricks
{
    private const QUERY_MARKER = 'vg-product-source-event-query';

    public function register(): void
    {
        add_filter('bricks/posts/query_vars', [$this, 'filter_source_event_query_vars'], 10, 4);
    }

    /** Replace only the marked query with the validated source Event. */
    public function filter_source_event_query_vars(
        array $query_vars,
        array $settings,
        string $element_id,
        string $element_name
    ): array {
        if (! $this->has_exact_css_class_token($settings, self::QUERY_MARKER)) {
            return $query_vars;
        }

        $event_id = $this->source_event_for_current_product();

        return [
            'post_type' => 'tc_events',
            'post__in' => $event_id !== null ? [$event_id] : [0],
            'orderby' => 'post__in',
            'posts_per_page' => 1,
        ];
    }

    private function source_event_for_current_product(): ?int
    {
        $product_id = $this->resolve_current_product_id();
        if ($product_id <= 0 || ! function_exists('get_post_type') || get_post_type($product_id) !== 'product') {
            return null;
        }

        if (! function_exists('get_field')) {
            return null;
        }

        $event_id = $this->positive_id(
            get_field(ProductPeopleResolver::SOURCE_EVENT_FIELD, $product_id, false)
        );
        if ($event_id === null || ! function_exists('get_post')) {
            return null;
        }

        $event = get_post($event_id);
        if (
            ! is_object($event)
            || ($event->post_type ?? null) !== 'tc_events'
            || ($event->post_status ?? null) === 'trash'
        ) {
            return null;
        }

        return $event_id;
    }

    /** Resolve the highest-priority Bricks or WordPress post context. */
    private function resolve_current_product_id(): int
    {
        if (
            class_exists('Bricks\\Database')
            && isset(\Bricks\Database::$page_data)
            && is_array(\Bricks\Database::$page_data)
        ) {
            $preview_id = (int) (\Bricks\Database::$page_data['preview_or_post_id'] ?? 0);
            if ($preview_id > 0 && function_exists('get_post_type') && get_post_type($preview_id) === 'product') {
                return $preview_id;
            }
        }

        if (function_exists('is_singular') && is_singular() && function_exists('get_queried_object')) {
            $queried_object = get_queried_object();
            $queried_id = is_object($queried_object) ? (int) ($queried_object->ID ?? 0) : 0;
            if ($queried_id > 0) {
                return $queried_id;
            }
        }

        if (function_exists('get_the_ID')) {
            $current_post_id = (int) get_the_ID();
            if ($current_post_id > 0) {
                return $current_post_id;
            }
        }

        global $post;
        return is_object($post) ? (int) ($post->ID ?? 0) : 0;
    }

    /** Accept one positive integer or digit-only numeric string; everything else is invalid. */
    private function positive_id(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && ctype_digit($value)) {
            $id = (int) $value;
            return $id > 0 ? $id : null;
        }

        return null;
    }

    private function has_exact_css_class_token(array $settings, string $marker): bool
    {
        $classes = $settings['_cssClasses'] ?? '';
        if (is_string($classes)) {
            $tokens = preg_split('/\s+/', trim($classes), -1, PREG_SPLIT_NO_EMPTY);
            return is_array($tokens) && in_array($marker, $tokens, true);
        }

        if (is_array($classes)) {
            foreach ($classes as $class_name) {
                if (is_string($class_name) && $class_name === $marker) {
                    return true;
                }
            }
        }

        return false;
    }
}
