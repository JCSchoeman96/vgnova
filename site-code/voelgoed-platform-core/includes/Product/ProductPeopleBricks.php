<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Product;

defined('ABSPATH') || exit;

/** Adapts canonical Product People results to the explicitly marked Bricks consumer. */
final class ProductPeopleBricks
{
    private const QUERY_MARKER = 'vg-product-people-query';

    private const SECTION_MARKER = 'vg-product-people-section';

    private ProductPeopleResolver $resolver;

    public function __construct(?ProductPeopleResolver $resolver = null)
    {
        $this->resolver = $resolver ?? new ProductPeopleResolver();
    }

    public function register(): void
    {
        add_filter('bricks/posts/query_vars', [$this, 'filter_people_query_vars'], 10, 4);
        add_filter('bricks/element/render', [$this, 'filter_people_section_render'], 10, 2);
    }

    /** Replace only the marked Bricks query with ordered canonical Person IDs. */
    public function filter_people_query_vars(
        array $query_vars,
        array $settings,
        string $element_id,
        string $element_name
    ): array {
        if (! $this->has_exact_css_class_token($settings, self::QUERY_MARKER)) {
            return $query_vars;
        }

        $person_ids = $this->people_for_current_product();

        return [
            'post_type' => ProductPeopleResolver::PERSON_POST_TYPE,
            'post_status' => ProductPeopleResolver::PERSON_ACTIVE_STATUS,
            'post__in' => $person_ids !== [] ? $person_ids : [0],
            'orderby' => 'post__in',
            'posts_per_page' => -1,
        ];
    }

    /** Hide the marked Product People section on the frontend when canonical results are empty. */
    public function filter_people_section_render(bool $render_element, object $element): bool
    {
        $settings = isset($element->settings) && is_array($element->settings)
            ? $element->settings
            : [];
        if (! $this->has_exact_css_class_token($settings, self::SECTION_MARKER)) {
            return $render_element;
        }

        if ($this->is_builder_context()) {
            return $render_element;
        }

        return $this->people_for_current_product() !== []
            ? $render_element
            : false;
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
            if ($preview_id > 0) {
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

    /** @return list<int> */
    private function people_for_current_product(): array
    {
        $product_id = $this->resolve_current_product_id();
        return $product_id > 0 ? $this->resolver->people_for_product($product_id) : [];
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

    private function is_builder_context(): bool
    {
        return (function_exists('bricks_is_builder') && bricks_is_builder())
            || (function_exists('bricks_is_builder_call') && bricks_is_builder_call());
    }
}
