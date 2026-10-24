<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Product;

defined('ABSPATH') || exit;

/** Adapts canonical Person Product results to the marked Bricks consumer. */
final class PersonProductsBricks
{
    private const QUERY_MARKER = 'vg-person-products-query';

    private const SECTION_MARKER = 'vg-person-products-section';

    private ProductContributionRepository $repository;

    public function __construct(?ProductContributionRepository $repository = null)
    {
        $this->repository = $repository ?? new ProductContributionRepository();
    }

    public function register(): void
    {
        add_filter('bricks/posts/query_vars', [$this, 'filter_product_query_vars'], 10, 4);
        add_filter('bricks/element/render', [$this, 'filter_products_section_render'], 10, 2);
    }

    /** Replace only the marked Bricks query with ordered canonical Product IDs. */
    public function filter_product_query_vars(
        array $query_vars,
        array $settings,
        string $element_id,
        string $element_name
    ): array {
        if (! $this->has_exact_css_class_token($settings, self::QUERY_MARKER)) {
            return $query_vars;
        }

        $product_ids = $this->products_for_current_person();

        return [
            'post_type' => ProductContributionRepository::PRODUCT_POST_TYPE,
            'post_status' => ProductContributionRepository::ACTIVE_STATUS,
            'post__in' => $product_ids !== [] ? $product_ids : [0],
            'orderby' => 'post__in',
            'posts_per_page' => -1,
        ];
    }

    /** Hide the marked Person Products section on the frontend when no Products exist. */
    public function filter_products_section_render(bool $render_element, object $element): bool
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

        return $this->products_for_current_person() !== []
            ? $render_element
            : false;
    }

    /** Resolve the highest-priority Bricks or WordPress Person context. */
    private function resolve_current_person_id(): int
    {
        if (
            class_exists('Bricks\\Database')
            && isset(\Bricks\Database::$page_data)
            && is_array(\Bricks\Database::$page_data)
            && array_key_exists('preview_or_post_id', \Bricks\Database::$page_data)
        ) {
            $preview_value = \Bricks\Database::$page_data['preview_or_post_id'];
            if ($this->has_explicit_context_value($preview_value)) {
                $preview_id = $this->positive_id($preview_value);
                return $preview_id > 0 && $this->is_person_id($preview_id) ? $preview_id : 0;
            }
        }

        if (function_exists('is_singular') && is_singular() && function_exists('get_queried_object')) {
            $queried_object = get_queried_object();
            if (is_object($queried_object)) {
                $queried_id = $this->positive_id($queried_object->ID ?? 0);
                return $queried_id > 0 && $this->is_person_id($queried_id) ? $queried_id : 0;
            }
        }

        if (function_exists('get_the_ID')) {
            $current_post_id = $this->positive_id(get_the_ID());
            if ($current_post_id > 0) {
                return $this->is_person_id($current_post_id) ? $current_post_id : 0;
            }
        }

        global $post;
        if (is_object($post)) {
            $global_post_id = $this->positive_id($post->ID ?? 0);
            return $global_post_id > 0 && $this->is_person_id($global_post_id) ? $global_post_id : 0;
        }

        return 0;
    }

    /** @return list<int> */
    private function products_for_current_person(): array
    {
        $person_id = $this->resolve_current_person_id();
        return $person_id > 0 ? $this->repository->products_for_person($person_id) : [];
    }

    private function is_person_id(int $post_id): bool
    {
        return $post_id > 0
            && function_exists('get_post_type')
            && get_post_type($post_id) === ProductContributionRepository::PERSON_POST_TYPE;
    }

    private function has_explicit_context_value(mixed $value): bool
    {
        return ! ($value === null || $value === false || $value === '' || $value === 0 || $value === '0');
    }

    private function positive_id(mixed $value): int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : 0;
        }
        if (is_string($value) && ctype_digit($value)) {
            $post_id = (int) $value;
            return $post_id > 0 ? $post_id : 0;
        }

        return 0;
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
