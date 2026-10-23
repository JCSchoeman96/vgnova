<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Product;

defined('ABSPATH') || exit;

/** Validates and writes canonical Product × Person contributions. */
final class ProductContributionService
{
    private const ALLOWED_STATUSES = ['publish', 'draft', 'pending', 'private'];

    private static int $service_insert_depth = 0;

    private ProductContributionRepository $repository;

    private ProductContributionPairLock $pair_lock;

    public function __construct(
        ?ProductContributionRepository $repository = null,
        ?ProductContributionPairLock $pair_lock = null
    ) {
        $this->repository = $repository ?? new ProductContributionRepository();
        $this->pair_lock = $pair_lock ?? new ProductContributionPairLock();
    }

    /** The admin post-data filter skips the already-validated transient service insert. */
    public static function is_service_insert_in_progress(): bool
    {
        return self::$service_insert_depth > 0;
    }

    public function validate_pair(
        int $product_id,
        int $person_id,
        ?int $exclude_contribution_id = null
    ): true|\WP_Error {
        return $this->validate_pair_using_repository($product_id, $person_id, $exclude_contribution_id, false);
    }

    public function validate_pair_fresh(
        int $product_id,
        int $person_id,
        ?int $exclude_contribution_id = null
    ): true|\WP_Error {
        return $this->validate_pair_using_repository($product_id, $person_id, $exclude_contribution_id, true);
    }

    /** @param list<int> $product_role_term_ids */
    public function validate_roles(array $product_role_term_ids, bool $require_nonempty = false): true|\WP_Error
    {
        $role_ids = $this->validated_role_ids($product_role_term_ids);
        if (is_wp_error($role_ids)) {
            return $role_ids;
        }
        if ($require_nonempty && $role_ids === []) {
            return new \WP_Error('product_roles_required', 'A published Product Contribution requires at least one approved Product Role.');
        }

        return true;
    }

    /**
     * @param list<int> $product_role_term_ids
     */
    public function validate_publishable(
        int $product_id,
        int $person_id,
        array $product_role_term_ids,
        ?int $exclude_contribution_id = null,
        bool $fresh = false
    ): true|\WP_Error {
        $role_validation = $this->validate_roles($product_role_term_ids, true);
        if (is_wp_error($role_validation)) {
            return $role_validation;
        }

        $pair_validation = $fresh
            ? $this->validate_pair_fresh($product_id, $person_id, $exclude_contribution_id)
            : $this->validate_pair($product_id, $person_id, $exclude_contribution_id);
        if (is_wp_error($pair_validation)) {
            return $pair_validation;
        }

        $mode = get_field(ProductPeopleFields::MODE_FIELD, $product_id);
        if ($mode === ProductPeopleFields::MODE_MANUAL) {
            return true;
        }
        if ($mode !== ProductPeopleFields::MODE_EVENT) {
            return new \WP_Error('invalid_product_mode', 'The Product must use the Event or Manual People mode before publication.');
        }

        if (! in_array($person_id, $this->repository->people_for_product($product_id), true)) {
            return new \WP_Error(
                'person_not_in_source_event',
                'The Person must have an active Event Participation in the Product\'s source Event before publication.'
            );
        }

        return true;
    }

    public function title_for_pair(int $product_id, int $person_id): string|\WP_Error
    {
        if (! $this->repository->is_valid_product($product_id)) {
            return new \WP_Error('invalid_product', 'The Product must be an existing, non-trashed WooCommerce Product.');
        }
        if (! $this->repository->is_valid_person($person_id)) {
            return new \WP_Error('invalid_person', 'The Person must be an existing, non-trashed Person.');
        }

        return get_the_title($product_id) . ' — ' . get_the_title($person_id);
    }

    /**
     * Create one canonical Product Contribution and its ACF and taxonomy values.
     *
     * @param list<int> $product_role_term_ids
     */
    public function create(
        int $product_id,
        int $person_id,
        array $product_role_term_ids = [],
        int $display_order = 0,
        string $status = 'publish'
    ): int|\WP_Error {
        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            return new \WP_Error(
                'invalid_product_contribution_status',
                'The requested Product Contribution status is not supported by create().'
            );
        }
        if ($display_order < 0) {
            return new \WP_Error('invalid_display_order', 'Display order must be zero or greater.');
        }

        $role_ids = $this->validated_role_ids($product_role_term_ids);
        if (is_wp_error($role_ids)) {
            return $role_ids;
        }

        $validation = $status === 'publish'
            ? $this->validate_publishable($product_id, $person_id, $role_ids)
            : $this->validate_pair($product_id, $person_id);
        if (is_wp_error($validation)) {
            return $validation;
        }

        if (! $this->pair_lock->acquire($product_id, $person_id)) {
            return new \WP_Error(
                'product_contribution_pair_lock_failed',
                'Could not reserve this Product and Person pair. Try again.'
            );
        }

        try {
            $validation = $status === 'publish'
                ? $this->validate_publishable($product_id, $person_id, $role_ids, null, true)
                : $this->validate_pair_fresh($product_id, $person_id);
            if (is_wp_error($validation)) {
                return $validation;
            }

            $title = $this->title_for_pair($product_id, $person_id);
            if (is_wp_error($title)) {
                return $title;
            }

            ++self::$service_insert_depth;
            try {
                $contribution_id = wp_insert_post([
                    'post_type' => ProductContributionRepository::POST_TYPE,
                    'post_status' => $status,
                    'post_title' => $title,
                ], true);
            } finally {
                --self::$service_insert_depth;
            }
            if (is_wp_error($contribution_id)) {
                return $contribution_id;
            }

            $contribution_id = (int) $contribution_id;
            if ($contribution_id <= 0) {
                return new \WP_Error('product_contribution_insert_failed', 'WordPress did not return a valid Product Contribution ID.');
            }

            try {
                $field_values = [
                    ProductContributionRepository::PRODUCT_FIELD => $product_id,
                    ProductContributionRepository::PERSON_FIELD => $person_id,
                    ProductContributionRepository::DISPLAY_ORDER_FIELD => $display_order,
                ];
                foreach ($field_values as $field_name => $value) {
                    if (update_field($field_name, $value, $contribution_id) === false) {
                        return $this->rollback_new_contribution(
                            $contribution_id,
                            'product_contribution_field_write_failed',
                            sprintf('Could not write the Product Contribution field "%s".', $field_name)
                        );
                    }
                }

                $role_result = wp_set_object_terms(
                    $contribution_id,
                    $role_ids,
                    ProductContributionRepository::ROLE_TAXONOMY,
                    false
                );
                if (is_wp_error($role_result)) {
                    return $this->rollback_new_contribution(
                        $contribution_id,
                        'product_contribution_role_write_failed',
                        'Could not assign Product Contribution roles: ' . $role_result->get_error_message()
                    );
                }
                if (! is_array($role_result)) {
                    return $this->rollback_new_contribution(
                        $contribution_id,
                        'product_contribution_role_write_failed',
                        'WordPress returned an invalid result while assigning Product Contribution roles.'
                    );
                }

                $persisted_role_ids = wp_get_object_terms(
                    $contribution_id,
                    ProductContributionRepository::ROLE_TAXONOMY,
                    ['fields' => 'ids']
                );
                if (is_wp_error($persisted_role_ids) || ! is_array($persisted_role_ids)) {
                    return $this->rollback_new_contribution(
                        $contribution_id,
                        'product_contribution_role_persistence_failed',
                        'Could not verify the Product Contribution role relationships.'
                    );
                }

                $expected_role_ids = $role_ids;
                $persisted_role_ids = array_values(array_unique(array_map('intval', $persisted_role_ids)));
                sort($expected_role_ids, SORT_NUMERIC);
                sort($persisted_role_ids, SORT_NUMERIC);
                if ($persisted_role_ids !== $expected_role_ids) {
                    return $this->rollback_new_contribution(
                        $contribution_id,
                        'product_contribution_role_persistence_failed',
                        'The requested Product Roles were not persisted as native taxonomy relationships.'
                    );
                }
            } catch (\Throwable $error) {
                return $this->rollback_new_contribution(
                    $contribution_id,
                    'product_contribution_write_failed',
                    'Could not finish writing the Product Contribution: ' . $error->getMessage()
                );
            }

            return $contribution_id;
        } finally {
            $this->pair_lock->release($product_id, $person_id);
        }
    }

    private function validate_pair_using_repository(
        int $product_id,
        int $person_id,
        ?int $exclude_contribution_id,
        bool $fresh
    ): true|\WP_Error {
        $valid_product = $fresh
            ? $this->repository->is_valid_product_fresh($product_id)
            : $this->repository->is_valid_product($product_id);
        if (! $valid_product) {
            if ($fresh && $this->fresh_endpoint_read_failed()) {
                return new \WP_Error(
                    'product_contribution_fresh_endpoint_read_failed',
                    'Could not verify the Product. Try again.'
                );
            }
            return new \WP_Error('invalid_product', 'The Product must be an existing, non-trashed WooCommerce Product.');
        }

        $valid_person = $fresh
            ? $this->repository->is_valid_person_fresh($person_id)
            : $this->repository->is_valid_person($person_id);
        if (! $valid_person) {
            if ($fresh && $this->fresh_endpoint_read_failed()) {
                return new \WP_Error(
                    'product_contribution_fresh_endpoint_read_failed',
                    'Could not verify the Person. Try again.'
                );
            }
            return new \WP_Error('invalid_person', 'The Person must be an existing, non-trashed Person.');
        }

        if ($fresh) {
            $matches = $this->repository->find_pair_ids_fresh($product_id, $person_id, $exclude_contribution_id);
            if ($matches === null) {
                return new \WP_Error(
                    'product_contribution_fresh_check_failed',
                    'Could not verify Product Contribution relationships. Try again.'
                );
            }
        } else {
            $matches = $this->repository->find_pair_ids($product_id, $person_id, $exclude_contribution_id);
        }

        if ($matches) {
            return new \WP_Error(
                'duplicate_product_contribution',
                'This Product and Person pair already has a Product Contribution.'
            );
        }

        return true;
    }

    private function fresh_endpoint_read_failed(): bool
    {
        global $wpdb;
        return is_object($wpdb) && (string) ($wpdb->last_error ?? '') !== '';
    }

    /**
     * @param list<int> $product_role_term_ids
     * @return list<int>|\WP_Error
     */
    private function validated_role_ids(array $product_role_term_ids): array|\WP_Error
    {
        $validated_ids = [];
        foreach ($product_role_term_ids as $term_id) {
            if (! is_int($term_id) && (! is_string($term_id) || ! ctype_digit($term_id))) {
                return new \WP_Error('invalid_product_role', 'Each Product Role must be a valid product_role term ID.');
            }

            $term_id = (int) $term_id;
            if ($term_id <= 0 || ! ProductRoleVocabulary::is_approved_term_id($term_id)) {
                return new \WP_Error('invalid_product_role', 'Each Product Role must use the approved product_role vocabulary.');
            }
            if (! in_array($term_id, $validated_ids, true)) {
                $validated_ids[] = $term_id;
            }
        }

        return $validated_ids;
    }

    private function rollback_new_contribution(int $contribution_id, string $code, string $message): \WP_Error
    {
        $rollback_error = null;
        try {
            $deleted = wp_delete_post($contribution_id, true);
            if ($deleted === false || $deleted === null || is_wp_error($deleted)) {
                $rollback_error = is_wp_error($deleted)
                    ? $deleted->get_error_message()
                    : 'WordPress did not confirm hard deletion of the new contribution.';
            } else {
                if (! function_exists('clean_post_cache')) {
                    $rollback_error = 'WordPress could not clear the new contribution cache for rollback verification.';
                } else {
                    clean_post_cache($contribution_id);
                    $remaining_post = get_post($contribution_id);
                    global $wpdb;
                    if (is_object($wpdb) && (string) ($wpdb->last_error ?? '') !== '') {
                        $rollback_error = 'WordPress could not verify that the new contribution was deleted.';
                    } elseif (is_object($remaining_post)) {
                        $rollback_error = 'The new contribution still exists after the hard-delete attempt.';
                    }
                }
            }
        } catch (\Throwable $error) {
            $rollback_error = $error->getMessage();
        }

        if ($rollback_error !== null) {
            return new \WP_Error(
                'product_contribution_rollback_failed',
                $message . ' Rollback failed: ' . $rollback_error,
                ['original_error_code' => $code, 'contribution_id' => $contribution_id]
            );
        }

        return new \WP_Error($code, $message);
    }
}
