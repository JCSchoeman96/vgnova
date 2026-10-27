<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Product;

defined('ABSPATH') || exit;

/** Enforces Product Contribution editor rules and maintains derived titles. */
final class ProductContributionAdmin
{
    public const PRODUCT_FIELD_KEY = 'field_vg_product_contribution_product';
    public const PERSON_FIELD_KEY = 'field_vg_product_contribution_person';
    public const ROLES_FIELD_KEY = 'field_vg_product_contribution_roles';
    public const DISPLAY_ORDER_FIELD_KEY = 'field_vg_product_contribution_display_order';

    private const EDITABLE_STATUSES = ['publish', 'draft', 'pending', 'private', 'future', 'trash', 'auto-draft'];

    private ProductContributionRepository $repository;

    private ProductContributionService $service;

    private ProductContributionPairLock $pair_lock;

    private bool $syncing_titles = false;

    private ?int $syncing_title_contribution_id = null;

    private ?string $syncing_title_original_status = null;

    private ?int $scheduled_publish_validated_id = null;

    private ?int $scheduled_publish_candidate_id = null;

    private bool $capture_save_validation_error = false;

    private ?\WP_Error $captured_save_validation_error = null;

    public function __construct(
        ?ProductContributionRepository $repository = null,
        ?ProductContributionService $service = null,
        ?ProductContributionPairLock $pair_lock = null
    ) {
        $this->repository = $repository ?? new ProductContributionRepository();
        $this->pair_lock = $pair_lock ?? new ProductContributionPairLock();
        $this->service = $service ?? new ProductContributionService($this->repository, $this->pair_lock);
    }

    public function register(): void
    {
        add_filter('acf/validate_value/key=' . self::PRODUCT_FIELD_KEY, [$this, 'validate_product_field'], 10, 4);
        add_filter('acf/validate_value/key=' . self::PERSON_FIELD_KEY, [$this, 'validate_person_field'], 10, 4);
        add_filter('acf/validate_value/key=' . self::ROLES_FIELD_KEY, [$this, 'validate_roles_field'], 10, 4);
        add_filter('acf/validate_value/key=' . self::DISPLAY_ORDER_FIELD_KEY, [$this, 'validate_display_order_field'], 10, 4);
        add_filter('wp_insert_post_data', [$this, 'validate_pair_before_post_save'], 10, 2);
        remove_action('publish_future_post', 'check_and_publish_future_post', 10);
        add_action('publish_future_post', [$this, 'handle_future_publication'], 10, 1);
        add_action('acf/save_post', [$this, 'handle_acf_save'], 20, 1);
        add_action('post_updated', [$this, 'handle_post_updated'], 10, 3);
        add_action('shutdown', [$this, 'release_pair_locks'], 1);
    }

    /** Route scheduled contribution publishing through the canonical save validation. */
    public function handle_future_publication(mixed $post_id): void
    {
        $post_id = $this->normalize_id($post_id);
        if ($post_id <= 0) {
            return;
        }

        if (function_exists('clean_post_cache')) {
            clean_post_cache($post_id);
        }
        $post = get_post($post_id);
        if (! is_object($post)) {
            global $wpdb;
            if (is_object($wpdb) && (string) ($wpdb->last_error ?? '') !== '') {
                $this->retry_future_publication_later($post_id);
            }
            return;
        }

        if (($post->post_type ?? null) !== ProductContributionRepository::POST_TYPE) {
            $can_publish = apply_filters('voelgoed/platform_core/future_post_can_publish', true, $post_id, $post);
            if ($can_publish !== true) {
                return;
            }
            check_and_publish_future_post($post_id);
            return;
        }
        if (($post->post_status ?? null) !== 'future') {
            return;
        }

        $this->captured_save_validation_error = null;
        $this->scheduled_publish_candidate_id = $post_id;
        $this->capture_save_validation_error = true;
        try {
            $validated_data = $this->validate_pair_before_post_save(
                [
                    'ID' => $post_id,
                    'post_type' => ProductContributionRepository::POST_TYPE,
                    'post_status' => 'publish',
                ],
                ['ID' => $post_id]
            );
        } catch (\RuntimeException $error) {
            $validation_error = $this->captured_save_validation_error;
            if ($validation_error === null) {
                throw $error;
            }
            $this->captured_save_validation_error = null;
            if ($validation_error->get_error_code() === 'scheduled_product_contribution_not_due') {
                $this->reschedule_if_future_date_changed($post_id);
            } elseif ($this->is_transient_scheduled_publish_error($validation_error)) {
                $this->retry_future_publication_later($post_id);
            }
            return;
        } finally {
            $this->capture_save_validation_error = false;
            $this->scheduled_publish_candidate_id = null;
        }

        $this->captured_save_validation_error = null;
        $this->scheduled_publish_validated_id = $post_id;
        try {
            $updated = wp_update_post([
                'ID' => $post_id,
                'post_status' => 'publish',
                'post_title' => $validated_data['post_title'],
            ], true);
            if (is_wp_error($updated) || (int) $updated !== $post_id) {
                $this->retry_future_publication_later($post_id);
            }
        } catch (\Throwable $error) {
            $this->retry_future_publication_later($post_id);
            throw $error;
        } finally {
            $this->scheduled_publish_validated_id = null;
        }
    }

    public function validate_product_field(mixed $valid, mixed $value, array $field, string $input_name): mixed
    {
        if ($valid !== true || $this->is_retiring_or_placeholder()) {
            return $valid;
        }

        return $this->validate_field_pair($value, $this->submitted_or_stored_id(self::PERSON_FIELD_KEY), true);
    }

    public function validate_person_field(mixed $valid, mixed $value, array $field, string $input_name): mixed
    {
        if ($valid !== true || $this->is_retiring_or_placeholder()) {
            return $valid;
        }

        return $this->validate_field_pair($this->submitted_or_stored_id(self::PRODUCT_FIELD_KEY), $value, false);
    }

    public function validate_roles_field(mixed $valid, mixed $value, array $field, string $input_name): mixed
    {
        if ($valid !== true || $this->is_retiring_or_placeholder()) {
            return $valid;
        }

        $role_ids = $this->normalize_role_submission($value);
        if (is_wp_error($role_ids)) {
            return $role_ids->get_error_message();
        }

        $result = $this->service->validate_roles($role_ids, $this->request_status() === 'publish');
        return is_wp_error($result) ? $result->get_error_message() : true;
    }

    public function validate_display_order_field(mixed $valid, mixed $value, array $field, string $input_name): mixed
    {
        if ($valid !== true || $this->is_retiring_or_placeholder()) {
            return $valid;
        }

        return $this->normalize_display_order($value) === null
            ? 'Display order must be a non-negative whole number.'
            : true;
    }

    /** Validate a contribution save before WordPress writes its post row. */
    public function validate_pair_before_post_save(array $data, array $postarr): array
    {
        if (($data['post_type'] ?? null) !== ProductContributionRepository::POST_TYPE) {
            return $data;
        }
        if (
            $this->scheduled_publish_validated_id !== null
            && $this->normalize_id($postarr['ID'] ?? $data['ID'] ?? 0) === $this->scheduled_publish_validated_id
            && ($data['post_status'] ?? null) === 'publish'
        ) {
            return $data;
        }
        if (ProductContributionService::is_service_insert_in_progress()) {
            return $data;
        }
        if ($this->syncing_titles) {
            if (
                $this->syncing_title_contribution_id !== null
                && $this->normalize_id($postarr['ID'] ?? $data['ID'] ?? 0) === $this->syncing_title_contribution_id
                && $this->syncing_title_original_status === 'future'
                && ($data['post_status'] ?? null) === 'publish'
            ) {
                $data['post_status'] = 'future';
            }
            return $data;
        }

        $status = (string) ($data['post_status'] ?? 'draft');
        if (! in_array($status, self::EDITABLE_STATUSES, true)) {
            $this->stop_save(new \WP_Error(
                'invalid_product_contribution_status',
                'The requested Product Contribution status is not supported.'
            ));
        }
        if ($status === 'auto-draft') {
            return $data;
        }

        $contribution_id = $this->normalize_id($postarr['ID'] ?? $data['ID'] ?? 0);
        if ($contribution_id > 0 && ! $this->pair_lock->acquire_contribution($contribution_id)) {
            $this->stop_save(new \WP_Error(
                'product_contribution_row_lock_failed',
                'Could not reserve this Product Contribution. Try again.'
            ));
        }

        // Retirement is always possible after taking the row lock, even when history is broken.
        if ($status === 'trash') {
            return $data;
        }

        $submitted = $_POST['acf'] ?? null;
        $submitted = is_array($submitted) ? $submitted : [];
        $has_product = array_key_exists(self::PRODUCT_FIELD_KEY, $submitted);
        $has_person = array_key_exists(self::PERSON_FIELD_KEY, $submitted);
        $has_roles = array_key_exists(self::ROLES_FIELD_KEY, $submitted);

        $stored_pair = null;
        if ($contribution_id > 0) {
            // The row lock above must precede this fresh endpoint read.
            $stored_pair = $this->repository->stored_pair_ids_fresh($contribution_id);
            if ($stored_pair === null) {
                $this->stop_save(new \WP_Error(
                    'product_contribution_fresh_read_failed',
                    'Could not read this Product Contribution. Try again.'
                ));
            }
            if ($this->scheduled_publish_candidate_id === $contribution_id) {
                $fresh_post = get_post($contribution_id);
                if (
                    ! is_object($fresh_post)
                    || ($fresh_post->post_type ?? null) !== ProductContributionRepository::POST_TYPE
                ) {
                    $this->stop_save(new \WP_Error(
                        'scheduled_product_contribution_fresh_read_failed',
                        'Could not verify the scheduled Product Contribution. Try again.'
                    ));
                }
                if (($fresh_post->post_status ?? null) !== 'future') {
                    $this->stop_save(new \WP_Error(
                        'scheduled_product_contribution_status_changed',
                        'The Product Contribution is no longer scheduled for publication.'
                    ));
                }
                $scheduled_time = strtotime((string) ($fresh_post->post_date_gmt ?? '') . ' GMT');
                if ($scheduled_time === false) {
                    $this->stop_save(new \WP_Error(
                        'scheduled_product_contribution_schedule_read_failed',
                        'Could not verify the Product Contribution publication time. Try again.'
                    ));
                }
                if ($scheduled_time > time()) {
                    $this->stop_save(new \WP_Error(
                        'scheduled_product_contribution_not_due',
                        'The Product Contribution is not due for publication yet.'
                    ));
                }
            }
        }

        if ($has_product && $this->has_malformed_id($submitted[self::PRODUCT_FIELD_KEY])) {
            $this->stop_save(new \WP_Error('invalid_product', 'Select a valid Product.'));
        }
        if ($has_person && $this->has_malformed_id($submitted[self::PERSON_FIELD_KEY])) {
            $this->stop_save(new \WP_Error('invalid_person', 'Select a valid Person.'));
        }

        $product_id = $has_product
            ? $this->normalize_id($submitted[self::PRODUCT_FIELD_KEY])
            : (int) ($stored_pair['product_id'] ?? 0);
        $person_id = $has_person
            ? $this->normalize_id($submitted[self::PERSON_FIELD_KEY])
            : (int) ($stored_pair['person_id'] ?? 0);

        $roles = [];
        if ($has_roles) {
            $roles = $this->normalize_role_submission($submitted[self::ROLES_FIELD_KEY]);
            if (is_wp_error($roles)) {
                $this->stop_save($roles);
            }
        } elseif ($status === 'publish' && $contribution_id > 0) {
            $roles = $this->stored_role_ids($contribution_id);
            if (is_wp_error($roles)) {
                $this->stop_save($roles);
            }
        }

        $order_value = array_key_exists(self::DISPLAY_ORDER_FIELD_KEY, $submitted)
            ? $submitted[self::DISPLAY_ORDER_FIELD_KEY]
            : ($contribution_id > 0 ? get_field(ProductContributionRepository::DISPLAY_ORDER_FIELD, $contribution_id) : 0);
        $display_order = $this->normalize_display_order($order_value);
        if ($display_order === null) {
            $this->stop_save(new \WP_Error('invalid_display_order', 'Display order must be a non-negative whole number.'));
        }

        if ($status !== 'publish' && $has_roles) {
            $role_validation = $this->service->validate_roles($roles);
            if (is_wp_error($role_validation)) {
                $this->stop_save($role_validation);
            }
        }

        $has_complete_pair = $product_id > 0 && $person_id > 0;
        if (! $has_complete_pair && $status === 'draft') {
            // Drafts may remain incomplete and do not reserve a pair until both endpoints exist.
            $data['post_title'] = '';
            return $data;
        }
        if (! $has_complete_pair) {
            $this->stop_save(new \WP_Error(
                'product_contribution_endpoints_required',
                'Select an existing Product and Person before saving this status.'
            ));
        }

        if (! $this->pair_lock->acquire($product_id, $person_id)) {
            $this->stop_save(new \WP_Error(
                'product_contribution_pair_lock_failed',
                'Could not reserve this Product and Person pair. Try again.'
            ));
        }

        $validation = $status === 'publish'
            ? $this->service->validate_publishable(
                $product_id,
                $person_id,
                $roles,
                $contribution_id > 0 ? $contribution_id : null,
                true
            )
            : $this->service->validate_pair_fresh(
                $product_id,
                $person_id,
                $contribution_id > 0 ? $contribution_id : null
            );
        if (is_wp_error($validation)) {
            $this->stop_save($validation);
        }

        $title = $this->service->title_for_pair($product_id, $person_id);
        if (is_wp_error($title)) {
            $this->stop_save($title);
        }
        $data['post_title'] = $title;

        return $data;
    }

    /** Resync a contribution title after ACF has stored its endpoints. */
    public function handle_acf_save(mixed $post_id): void
    {
        if ($this->syncing_titles) {
            return;
        }

        try {
            $post_id = $this->normalize_id($post_id);
            if ($post_id > 0 && get_post_type($post_id) === ProductContributionRepository::POST_TYPE) {
                $this->sync_contribution_title($post_id);
            }
        } finally {
            $this->release_pair_locks();
        }
    }

    public function release_pair_locks(): void
    {
        $this->pair_lock->release_all();
    }

    /** Resync related contribution titles after a Product or Person title changes. */
    public function handle_post_updated(int $post_id, object $post_after, object $post_before): void
    {
        if (
            $this->syncing_titles
            || ($post_after->post_title ?? null) === ($post_before->post_title ?? null)
        ) {
            return;
        }

        $post_type = (string) ($post_after->post_type ?? '');
        if ($post_type === ProductContributionRepository::PRODUCT_POST_TYPE) {
            $contribution_ids = $this->repository->non_retired_contributions_for_product($post_id);
        } elseif ($post_type === ProductContributionRepository::PERSON_POST_TYPE) {
            $contribution_ids = $this->repository->non_retired_contributions_for_person($post_id);
        } else {
            return;
        }

        foreach ($contribution_ids as $contribution_id) {
            $this->sync_contribution_title((int) $contribution_id);
        }
    }

    private function validate_field_pair(mixed $product_value, mixed $person_value, bool $product_field): mixed
    {
        if ($this->request_status() === 'draft') {
            $product_id = $this->normalize_id($product_value);
            $person_id = $this->normalize_id($person_value);
            if ($product_id <= 0 || $person_id <= 0) {
                $value = $product_field ? $product_value : $person_value;
                return $this->has_malformed_id($value)
                    ? ($product_field ? 'Select a valid Product.' : 'Select a valid Person.')
                    : true;
            }
        }

        $product_id = $this->normalize_id($product_value);
        $person_id = $this->normalize_id($person_value);
        if ($product_id <= 0 || $person_id <= 0) {
            return 'Select an existing Product and Person.';
        }

        $contribution_id = $this->current_post_id();
        $status = $this->request_status();
        if ($status === 'publish') {
            $roles = $this->request_role_ids($contribution_id);
            if (is_wp_error($roles)) {
                return $roles->get_error_message();
            }
            $result = $this->service->validate_publishable(
                $product_id,
                $person_id,
                $roles,
                $contribution_id > 0 ? $contribution_id : null
            );
        } else {
            $result = $this->service->validate_pair(
                $product_id,
                $person_id,
                $contribution_id > 0 ? $contribution_id : null
            );
        }

        return is_wp_error($result) ? $result->get_error_message() : true;
    }

    private function sync_contribution_title(int $contribution_id): void
    {
        if ($this->syncing_titles || $contribution_id <= 0) {
            return;
        }

        $this->pair_lock->with_scoped_locks(function () use ($contribution_id): void {
            if (! $this->pair_lock->acquire_contribution($contribution_id)) {
                return;
            }

            $stored_pair = $this->repository->stored_pair_ids_fresh($contribution_id);
            if ($stored_pair === null) {
                return;
            }

            $post = get_post($contribution_id);
            if (
                ! is_object($post)
                || ($post->post_type ?? null) !== ProductContributionRepository::POST_TYPE
                || in_array((string) ($post->post_status ?? ''), ['trash', 'auto-draft'], true)
            ) {
                return;
            }

            if (
                ! $this->repository->is_valid_product_fresh($stored_pair['product_id'])
                || ! $this->repository->is_valid_person_fresh($stored_pair['person_id'])
            ) {
                return;
            }

            $title = $this->service->title_for_pair($stored_pair['product_id'], $stored_pair['person_id']);
            if (is_wp_error($title) || get_the_title($contribution_id) === $title) {
                return;
            }

            $this->syncing_title_contribution_id = $contribution_id;
            $this->syncing_title_original_status = (string) $post->post_status;
            $this->syncing_titles = true;
            try {
                wp_update_post([
                    'ID' => $contribution_id,
                    'post_title' => $title,
                ], true);
            } finally {
                $this->syncing_titles = false;
                $this->syncing_title_contribution_id = null;
                $this->syncing_title_original_status = null;
            }
        });
    }

    private function reschedule_if_future_date_changed(int $post_id): void
    {
        $post = get_post($post_id);
        if (! is_object($post) || ($post->post_status ?? null) !== 'future') {
            return;
        }

        $time = strtotime((string) ($post->post_date_gmt ?? '') . ' GMT');
        if ($time === false || $time <= time()) {
            return;
        }

        $this->replace_future_publication_event($post_id, $time);
    }

    private function retry_future_publication_later(int $post_id): void
    {
        $this->replace_future_publication_event($post_id, time() + 60);
    }

    private function replace_future_publication_event(int $post_id, int $timestamp): void
    {
        $clear_result = wp_clear_scheduled_hook('publish_future_post', [$post_id]);
        if (is_wp_error($clear_result) || $clear_result === false) {
            $this->log_future_schedule_failure('clear', $post_id, $clear_result);
        }

        $schedule_result = wp_schedule_single_event($timestamp, 'publish_future_post', [$post_id]);
        if ($schedule_result === true) {
            return;
        }

        $scheduled_time = wp_next_scheduled('publish_future_post', [$post_id]);
        if ($scheduled_time !== false && (int) $scheduled_time === $timestamp) {
            return;
        }

        $this->log_future_schedule_failure('schedule', $post_id, $schedule_result);
        $retry_time = time() + 60;
        $retry_result = wp_schedule_single_event($retry_time, 'publish_future_post', [$post_id]);
        if ($retry_result === true) {
            return;
        }

        $next_scheduled_time = wp_next_scheduled('publish_future_post', [$post_id]);
        if ($next_scheduled_time === false || (int) $next_scheduled_time > $retry_time) {
            $this->log_future_schedule_failure('retry schedule', $post_id, $retry_result);
        }
    }

    private function log_future_schedule_failure(string $operation, int $post_id, mixed $result): void
    {
        $detail = is_wp_error($result)
            ? $result->get_error_message()
            : 'WordPress returned false.';
        error_log(sprintf(
            'Product Contribution scheduled publication %s failed for post %d: %s',
            $operation,
            $post_id,
            $detail
        ));
    }

    private function is_transient_scheduled_publish_error(\WP_Error $error): bool
    {
        return in_array($error->get_error_code(), [
            'product_contribution_row_lock_failed',
            'product_contribution_pair_lock_failed',
            'product_contribution_fresh_read_failed',
            'product_contribution_fresh_endpoint_read_failed',
            'product_contribution_fresh_check_failed',
            'product_contribution_roles_read_failed',
            'scheduled_product_contribution_fresh_read_failed',
            'scheduled_product_contribution_schedule_read_failed',
        ], true);
    }

    private function request_role_ids(int $contribution_id): array|\WP_Error
    {
        $submitted = $_POST['acf'] ?? null;
        if (is_array($submitted) && array_key_exists(self::ROLES_FIELD_KEY, $submitted)) {
            return $this->normalize_role_submission($submitted[self::ROLES_FIELD_KEY]);
        }
        return $contribution_id > 0 ? $this->stored_role_ids($contribution_id) : [];
    }

    private function stored_role_ids(int $contribution_id): array|\WP_Error
    {
        $term_ids = wp_get_object_terms($contribution_id, ProductContributionRepository::ROLE_TAXONOMY, ['fields' => 'ids']);
        global $wpdb;
        if (
            is_wp_error($term_ids)
            || ! is_array($term_ids)
            || (is_object($wpdb) && (string) ($wpdb->last_error ?? '') !== '')
        ) {
            return new \WP_Error('product_contribution_roles_read_failed', 'Could not read Product Contribution roles. Try again.');
        }
        return $this->normalize_role_submission($term_ids);
    }

    private function normalize_role_submission(mixed $value): array|\WP_Error
    {
        if ($value === '') {
            return [];
        }
        if (! is_array($value)) {
            return new \WP_Error('invalid_product_role', 'Product Roles must be submitted as a list of approved term IDs.');
        }
        foreach ($value as $role_id) {
            if (! is_int($role_id) && (! is_string($role_id) || ! ctype_digit($role_id))) {
                return new \WP_Error('invalid_product_role', 'Each Product Role must be a valid term ID.');
            }
        }
        return array_values($value);
    }

    private function submitted_or_stored_id(string $field_key): int
    {
        $submitted = $_POST['acf'] ?? null;
        if (is_array($submitted) && array_key_exists($field_key, $submitted)) {
            return $this->normalize_id($submitted[$field_key]);
        }

        $post_id = $this->current_post_id();
        $field_name = $field_key === self::PRODUCT_FIELD_KEY
            ? ProductContributionRepository::PRODUCT_FIELD
            : ProductContributionRepository::PERSON_FIELD;
        return $post_id > 0 ? $this->normalize_id(get_field($field_name, $post_id)) : 0;
    }

    private function current_post_id(): int
    {
        return $this->normalize_id($_POST['post_ID'] ?? 0);
    }

    private function request_status(): string
    {
        $status = $_POST['post_status'] ?? $_POST['_status'] ?? null;
        return is_string($status) ? $status : 'draft';
    }

    private function is_retiring_or_placeholder(): bool
    {
        return in_array($this->request_status(), ['trash', 'auto-draft'], true);
    }

    private function normalize_id(mixed $value): int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : 0;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }
        return 0;
    }

    private function has_malformed_id(mixed $value): bool
    {
        return $value !== null && $value !== '' && $this->normalize_id($value) <= 0;
    }

    private function normalize_display_order(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }
        if (! is_string($value) || ! ctype_digit($value)) {
            return null;
        }
        return (int) $value;
    }

    private function stop_save(\WP_Error $error): never
    {
        if ($this->capture_save_validation_error) {
            $this->captured_save_validation_error = $error;
            throw new \RuntimeException($error->get_error_message());
        }

        $code = $error->get_error_code();
        $response = in_array($code, [
            'product_contribution_row_lock_failed',
            'product_contribution_pair_lock_failed',
            'product_contribution_fresh_read_failed',
            'product_contribution_fresh_check_failed',
            'product_contribution_roles_read_failed',
        ], true) ? 503 : ($code === 'duplicate_product_contribution' ? 409 : 400);
        $title = $code === 'duplicate_product_contribution'
            ? 'Duplicate Product Contribution'
            : 'Product Contribution Save Failed';
        wp_die($error->get_error_message(), $title, ['response' => $response]);
    }
}
