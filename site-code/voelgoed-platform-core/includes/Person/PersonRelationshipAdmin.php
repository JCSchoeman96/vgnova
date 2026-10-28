<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Person;

defined('ABSPATH') || exit;

/** Enforces editor validation and keeps the derived Person index current. */
final class PersonRelationshipAdmin
{
    public const FUTURE_PUBLISH_FILTER = 'voelgoed/platform_core/future_post_can_publish';
    private PersonRelationshipSourceLock $writer_lock;
    /** @var array<int, true> */
    private array $locked_source_ids = [];
    private int $future_publish_lock_source_id = 0;
    /** @var array<int, string> Source type observed before a supported post save. */
    private array $pending_source_type_changes = [];
    /** @var array<int, true> Sources whose ACF save callback actually completed. */
    private array $completed_acf_saves = [];
    /** @var array<int, true> */
    private array $reported_publish_violations = [];
    /** @var array<int, string> */
    private array $pending_publish_checks = [];

    /** @var array<string, string> */
    private const FIELD_KEYS = [
        'primary_author' => 'field_vg_article_primary_author',
        'additional_authors' => 'field_vg_article_additional_authors',
        'expert_contributors' => 'field_vg_article_expert_contributors',
        'reviewed_by' => 'field_vg_article_reviewed_by',
        'lead_instructor' => 'field_vg_course_lead_instructor',
        'additional_instructors' => 'field_vg_course_additional_instructors',
        'experts' => 'field_vg_course_experts',
    ];

    public function __construct(
        private ?PersonRelationshipIndexer $indexer = null,
        private ?PersonRelationshipSourceReader $reader = null,
        private ?PersonRelationshipValidator $validator = null,
    ) {
        $this->writer_lock = new PersonRelationshipSourceLock();
        $this->reader ??= new PersonRelationshipSourceReader();
        $this->validator ??= new PersonRelationshipValidator();
        $this->indexer ??= new PersonRelationshipIndexer(
            new PersonRelationshipSourceLock(),
            $this->reader,
            $this->validator,
            new PersonRelationshipRepository(new PersonRelationshipSchema()),
        );
    }

    public function register(): void
    {
        foreach (self::FIELD_KEYS as $role => $field_key) {
            add_filter('acf/validate_value/key=' . $field_key, fn (mixed $valid, mixed $value, array $field, string $input_name): mixed => $this->validate_field($valid, $value, $field, $input_name, $role), 10, 4);
        }
        add_filter('wp_insert_post_data', [$this, 'validate_source_before_save'], 10, 2);
        add_action('save_post', [$this, 'handle_source_type_transition_on_save'], 1000, 3);
        add_action('save_post', [$this, 'release_source_lock_after_post_save'], 1001, 3);
        add_action('wp_after_insert_post', [$this, 'mark_source_type_transition_dirty'], 1000, 4);
        // WordPress fires this after the status write. It detects unsupported
        // low-level publication bypasses; supported writes remain prevalidated.
        add_action('transition_post_status', [$this, 'detect_invalid_published_source'], 1000, 3);
        // ACF persists editor fields after the WordPress status transition.
        // Check at shutdown so the detector reads the final authoritative values.
        add_action('shutdown', [$this, 'check_pending_published_sources'], 1000, 0);
        add_action('shutdown', [$this, 'recover_incomplete_source_type_transitions'], 900, 0);
        add_filter('rest_pre_insert_post', [$this, 'validate_rest_source'], 10, 2);
        add_filter('rest_pre_insert_mto-course', [$this, 'validate_rest_source'], 10, 2);
        add_action('rest_after_insert_post', [$this, 'project_after_rest_save'], 20, 3);
        add_action('rest_after_insert_mto-course', [$this, 'project_after_rest_save'], 20, 3);
        add_action('acf/save_post', [$this, 'project_after_acf_save'], 20, 1);
        add_action('acf/save_post', [$this, 'validate_actual_acf_save'], 1, 1);
        // Core may return from a scheduled publication without changing status
        // (for example, when the event is stale or fires too early).
        add_action('publish_future_post', [$this, 'release_future_publish_lock_after_core'], PHP_INT_MAX, 1);
        add_action('shutdown', [$this, 'release_source_write_lock'], PHP_INT_MAX, 0);
        add_action('before_delete_post', [$this, 'remove_after_permanent_delete'], 10, 2);
        add_action('deleted_post', [$this, 'retry_delete_cleanup_after_meta_removal'], 10, 2);
        add_action('admin_notices', [$this, 'render_projection_failure_notice']);
        add_filter(self::FUTURE_PUBLISH_FILTER, [$this, 'future_post_can_publish'], 10, 3);
    }

    /** Fallback for environments that reach wp_after_insert_post without save_post handling. */
    public function mark_source_type_transition_dirty(mixed $post_id, mixed $post, mixed $update, mixed $post_before): void
    {
        $post_id = $this->normalize_id($post_id);
        if ($post_id > 0 && isset($this->completed_acf_saves[$post_id])) return;
        $this->process_source_type_transition($post_id, $post);
    }

    /** save_post still fires when callers suppress wp_after_insert_post. */
    public function handle_source_type_transition_on_save(mixed $post_id, mixed $post, mixed $update): void
    {
        $post_id = $this->normalize_id($post_id);
        if ($post_id > 0 && isset($this->completed_acf_saves[$post_id])) return;
        $this->process_source_type_transition($post_id, $post);
    }

    /** Release ordinary post-save locks when this request has no pending ACF relationship writes. */
    public function release_source_lock_after_post_save(mixed $post_id, mixed $post, mixed $update): void
    {
        $post_id = $this->normalize_id($post_id);
        if ($post_id < 1 || isset($this->completed_acf_saves[$post_id])) return;
        $this->release_source_write_lock($post_id);
    }

    private function process_source_type_transition(mixed $post_id, mixed $post): void
    {
        $post_id = $this->normalize_id($post_id);
        if ($post_id < 1 || ! isset($this->pending_source_type_changes[$post_id])) return;
        $old_type = $this->pending_source_type_changes[$post_id];
        $current_type = is_object($post) ? (string) ($post->post_type ?? '') : (string) get_post_type($post_id);
        if ($current_type === $old_type) return;
        // Mark before releasing the pre-write lock. The later wp_after_insert_post
        // callback sees this transition consumed and cannot stale a fresh projection.
        unset($this->pending_source_type_changes[$post_id]);
        $marker_saved = update_post_meta($post_id, PersonRelationshipContract::DIRTY_META_KEY, 1);
        if (! $marker_saved && (string) get_post_meta($post_id, PersonRelationshipContract::DIRTY_META_KEY, true) !== '1') {
            $recovery = new \WP_Error('dirty_marker_persistence_failed', 'Could not mark the transitioned source dirty.');
        } else {
            $this->release_source_write_lock($post_id);
            $recovery = PersonRelationshipContract::supported_source_type($current_type)
                ? $this->indexer->rebuild_source($post_id)
                : $this->indexer->remove_stale_source($post_id);
        }
        if (is_wp_error($recovery)) {
            error_log(sprintf('Person relationship index transition recovery failed for post %d (%s to %s, %s).', $post_id, $old_type, $current_type, $recovery->get_error_code()));
        }
    }

    /** Recover transitions whose ACF handler stopped before save_post completion. */
    public function recover_incomplete_source_type_transitions(): void
    {
        foreach (array_keys($this->pending_source_type_changes) as $source_id) {
            $post = get_post((int) $source_id);
            if (is_object($post)) $this->process_source_type_transition((int) $source_id, $post);
        }
    }

    /** Queue a post-transition check for a supported source. */
    public function detect_invalid_published_source(mixed $new_status, mixed $old_status, mixed $post): void
    {
        if (! is_object($post)) return;
        $source_id = $this->normalize_id($post->ID ?? 0);
        $source_type = (string) ($post->post_type ?? '');
        if ($source_id < 1 || ! PersonRelationshipContract::supported_source_type($source_type)) return;

        if ($new_status === 'publish' && $old_status !== 'publish') {
            $this->pending_publish_checks[$source_id] = $source_type;
        }

        // WordPress runs publish_future_post once per cron event. Release only
        // that path's lock at its status transition. Editor and REST writes
        // keep their lock through ACF persistence.
        if ($this->future_publish_lock_source_id === $source_id) {
            $this->release_source_write_lock($source_id);
        }
    }

    /** Validate queued status transitions after WordPress and ACF finish the request. */
    public function check_pending_published_sources(): void
    {
        $pending = $this->pending_publish_checks;
        $this->pending_publish_checks = [];
        foreach ($pending as $source_id => $source_type) {
            $post = get_post($source_id);
            if (! is_object($post)
                || (string) ($post->post_status ?? '') !== 'publish'
                || (string) ($post->post_type ?? '') !== $source_type
            ) continue;

            // Drop cached post/meta values so validation sees the authoritative
            // stored ACF data after supported editor saves have finished.
            if (function_exists('clean_post_cache')) clean_post_cache($source_id);
            if (function_exists('wp_cache_delete')) wp_cache_delete($source_id, 'post_meta');
            if (function_exists('acf_flush_value_cache')) {
                foreach (PersonRelationshipContract::allowed_roles($source_type) as $role) {
                    acf_flush_value_cache($source_id, $role);
                }
            }
            $projection = $this->reader->read($source_id);
            if (is_array($projection) && function_exists('clean_post_cache')) {
                foreach ($projection['relationships'] as $relationship) {
                    clean_post_cache($relationship['person_id']);
                }
            }
            $result = is_wp_error($projection)
                ? $projection
                : $this->validate_projection($projection, 'publish');
            if (! is_wp_error($result) || isset($this->reported_publish_violations[$source_id])) continue;
            $this->reported_publish_violations[$source_id] = true;

            $dirty_key = PersonRelationshipContract::DIRTY_META_KEY;
            $marker_persisted = (string) get_post_meta($source_id, $dirty_key, true) === '1';
            if (! $marker_persisted) {
                update_post_meta($source_id, $dirty_key, 1);
                $marker_persisted = (string) get_post_meta($source_id, $dirty_key, true) === '1';
            }

            $event = [
                'severity' => 'high',
                'code' => 'invalid_person_relationships_on_publish',
                'source_id' => $source_id,
                'source_type' => $source_type,
                'dirty_marker_persisted' => $marker_persisted,
            ];
            error_log(sprintf(
                '[HIGH] Person relationship integrity violation on published source %d (%s). Dirty marker persistence: %s.',
                $source_id,
                $source_type,
                $marker_persisted ? 'confirmed' : 'failed'
            ));
            do_action('voelgoed/platform_core/person_relationship_integrity_violation', $event);
        }
    }

    public function validate_field(mixed $valid, mixed $value, array $field, string $input_name, string $role): mixed
    {
        if ($valid !== true) {
            return $valid;
        }

        $post_id = $this->request_post_id();
        $post = $post_id > 0 ? get_post($post_id) : false;
        $source_type = is_object($post) ? (string) ($post->post_type ?? '') : $this->request_source_type();
        if (! PersonRelationshipContract::supported_source_type($source_type)) {
            return true;
        }

        $projection = $this->projection_for_source($post_id, [$role => $value], $source_type);
        if (is_wp_error($projection)) {
            return $projection->get_error_message();
        }

        $status = $this->request_status($post_id, $post);
        if ($status === 'future') {
            $status = 'publish';
        }
        $result = $this->validate_projection($projection, $status);
        return is_wp_error($result) ? $result->get_error_message() : true;
    }

    /** Validate source state and coordinate supported-type changes before WordPress writes it. */
    public function validate_source_before_save(array $data, array $postarr): array
    {
        $source_type = (string) ($data['post_type'] ?? '');
        $status = (string) ($data['post_status'] ?? 'draft');
        $post_id = $this->normalize_id($postarr['ID'] ?? $data['ID'] ?? 0);
        if ($post_id > 0) unset($this->completed_acf_saves[$post_id]);
        $current_type = $post_id > 0 ? (string) get_post_type($post_id) : '';
        if (! PersonRelationshipContract::supported_source_type($source_type)
            && ! PersonRelationshipContract::supported_source_type($current_type)) {
            return $data;
        }

        $meta_input = $postarr['meta_input'] ?? null;
        if (is_array($meta_input)) {
            $governed_roles = array_unique(array_merge(
                PersonRelationshipContract::allowed_roles($source_type),
                PersonRelationshipContract::allowed_roles($current_type),
            ));
            foreach ($governed_roles as $role) {
                if (array_key_exists($role, $meta_input) || array_key_exists('_' . $role, $meta_input)) {
                    wp_die('Person relationship metadata must be saved through the supported ACF editor flow.', 'Person relationship validation failed', ['response' => 400]);
                }
            }
        }

        if ($post_id > 0 && ! $this->acquire_source_write_lock($post_id)) {
            wp_die('This source is being edited by another request. Retry the save.', 'Person relationship validation failed', ['response' => 409]);
        }
        if ($post_id > 0 && $current_type !== $source_type
            && (PersonRelationshipContract::supported_source_type($current_type) || PersonRelationshipContract::supported_source_type($source_type))
        ) {
            $this->pending_source_type_changes[$post_id] = $current_type;
        }
        $this->flush_source_field_caches($post_id, $source_type);
        if (! PersonRelationshipContract::supported_source_type($source_type) || ! in_array($status, ['publish', 'future'], true)) {
            return $data;
        }

        // A new post has no persisted source for the partial-write validator
        // to reread. Preserve the existing submitted-projection validation.
        if ($post_id < 1) {
            $new_post_fields = $this->submitted_relationship_fields($source_type, 0);
            $projection = $this->projection_for_source(0, $new_post_fields, $source_type, false);
            $result = is_wp_error($projection) ? $projection : $this->validate_projection($projection, 'publish');
            if (is_wp_error($result)) {
                wp_die($result->get_error_message(), 'Person relationship validation failed', ['response' => 400]);
            }
            return $data;
        }

        // Core writes post_status before ACF's independent field writes. Check
        // every old/new field combination while the source lock is held.
        // ACF accepts a missing/zero post_ID for every save_post callback.
        // Bind a positive post_ID exactly, and otherwise preflight the payload
        // for each supported source WordPress may save in this request.
        $submitted_fields = $this->submitted_relationship_fields($source_type, $post_id);
        if ($current_type !== $source_type && PersonRelationshipContract::supported_source_type($current_type)) {
            // Validate the old source projection before interpreting target-type fields.
            $old_type_result = $this->validator->validate_publish_partial_write_safety($post_id, [], $current_type);
            if (is_wp_error($old_type_result)) {
                wp_die($old_type_result->get_error_message(), 'Person relationship validation failed', ['response' => 400]);
            }
        }
        $result = $this->validator->validate_publish_partial_write_safety($post_id, $submitted_fields, $source_type);
        if (is_wp_error($result)) {
            wp_die($result->get_error_message(), 'Person relationship validation failed', ['response' => 400]);
        }
        return $data;
    }

    /** Validate the submitted ACF payload in the actual save request before ACF writes any field. */
    public function validate_actual_acf_save(mixed $post_id): void
    {
        $post_id = $this->normalize_id($post_id);
        $post = $post_id > 0 ? get_post($post_id) : false;
        $source_type = is_object($post) ? (string) ($post->post_type ?? '') : $this->request_source_type();
        if (! PersonRelationshipContract::supported_source_type($source_type)) return;
        if (! $this->acquire_source_write_lock($post_id)) {
            wp_die('Person relationship fields could not be saved because this source is being edited by another request. Retry the save.', 'Person relationship validation failed', ['response' => 409]);
        }

        $status = is_object($post) ? (string) ($post->post_status ?? 'draft') : 'draft';
        // A status-only demotion is the first step in repairing malformed
        // published data. Do not validate the old relationships as a publish.
        $submitted_fields = $this->submitted_relationship_fields($source_type, $post_id);
        if ($status !== 'publish' && $submitted_fields !== []) {
            $unchanged = $this->validator->submitted_fields_match_persisted($post_id, $submitted_fields, $source_type);
            if ($unchanged === true) {
                update_post_meta($post_id, PersonRelationshipContract::DIRTY_META_KEY, 1);
                if ((string) get_post_meta($post_id, PersonRelationshipContract::DIRTY_META_KEY, true) !== '1') {
                    $this->release_source_write_lock($post_id);
                    wp_die('Person relationship fields could not be saved because the reverse index could not be marked for repair.', 'Person relationship validation failed', ['response' => 500]);
                }
                return;
            }
        }
        if ($status !== 'publish' && $submitted_fields === []) {
            update_post_meta($post_id, PersonRelationshipContract::DIRTY_META_KEY, 1);
            if ((string) get_post_meta($post_id, PersonRelationshipContract::DIRTY_META_KEY, true) !== '1') {
                $this->release_source_write_lock($post_id);
                wp_die('Person relationship fields could not be saved because the reverse index could not be marked for repair.', 'Person relationship validation failed', ['response' => 500]);
            }
            return;
        }

        // wp_insert_post_data runs before a new post has an ID and therefore
        // cannot lock or reread it. At this callback the row exists. Acquire
        // the source lock, reread authority, and prove every partial field
        // write safe before ACF persists any relationship field.
        if (in_array($status, ['publish', 'future'], true)) {
            $result = $this->validator->validate_publish_partial_write_safety($post_id, $submitted_fields, $source_type);
            if (is_wp_error($result)) {
                $this->release_source_write_lock($post_id);
                wp_die($result->get_error_message(), 'Person relationship validation failed', ['response' => 400]);
            }
        }

        $projection = $this->projection_for_source($post_id, $submitted_fields, $source_type, false);
        if (is_wp_error($projection)) {
            wp_die($projection->get_error_message(), 'Person relationship validation failed', ['response' => 400]);
        }
        $result = $this->validate_projection($projection, $status);
        if (is_wp_error($result)) {
            wp_die($result->get_error_message(), 'Person relationship validation failed', ['response' => 400]);
        }
        update_post_meta($post_id, PersonRelationshipContract::DIRTY_META_KEY, 1);
        if ((string) get_post_meta($post_id, PersonRelationshipContract::DIRTY_META_KEY, true) !== '1') {
            $this->release_source_write_lock($post_id);
            wp_die('Person relationship fields could not be saved because the reverse index could not be marked for repair.', 'Person relationship validation failed', ['response' => 500]);
        }
    }

    /** Release a scheduled source lock after the core publisher returns, even without a transition. */
    public function release_future_publish_lock_after_core(mixed $post_id): void
    {
        $post_id = $this->normalize_id($post_id);
        if ($post_id > 0 && $this->future_publish_lock_source_id === $post_id) {
            $this->release_source_write_lock($post_id);
        }
    }

    /** @return array<string, mixed> */
    private function submitted_relationship_fields(string $source_type, int $source_id): array
    {
        $submitted = [];
        // ACF_Form_Post::allow_save_post() checks only post_ID. It accepts a
        // missing/zero value and then saves against each actual save_post ID.
        $request_post_id = $this->request_post_id();
        if ($request_post_id > 0 && $request_post_id !== $source_id) return $submitted;
        if (! isset($_POST['acf']) || ! is_array($_POST['acf'])) return $submitted;
        foreach (PersonRelationshipContract::allowed_roles($source_type) as $role) {
            $field_key = self::FIELD_KEYS[$role];
            if (array_key_exists($field_key, $_POST['acf'])) $submitted[$role] = $_POST['acf'][$field_key];
        }
        return $submitted;
    }

    /** Reject unpersisted REST overrides and validate stored relationships before core writes the source. */
    public function validate_rest_source(mixed $prepared_post, mixed $request): mixed
    {
        if (! is_object($prepared_post)) {
            return new \WP_Error('invalid_person_relationship_request', 'Could not validate Person relationships for this request.', ['status' => 400]);
        }
        $source_type = (string) ($prepared_post->post_type ?? '');
        if (! PersonRelationshipContract::supported_source_type($source_type)) {
            return $prepared_post;
        }
        $post_id = $this->normalize_id($prepared_post->ID ?? 0);
        $params = is_object($request) && method_exists($request, 'get_params') ? $request->get_params() : null;
        if (! is_array($params)) {
            return new \WP_Error('invalid_person_relationship_request', 'Could not read Person relationship values from this request.', ['status' => 400]);
        }
        // These ACF field groups are intentionally not REST-enabled. WordPress
        // will not persist relationship values from acf/meta or top-level keys,
        // so reject attempted overrides and validate only stored authority.
        foreach (['acf', 'meta'] as $container) {
            if (isset($params[$container]) && ! is_array($params[$container])) {
                return new \WP_Error('malformed_person_relationship_payload', 'Person relationship data must be an object of approved fields.', ['status' => 400]);
            }
            if (is_array($params[$container] ?? null)) {
                foreach (PersonRelationshipContract::allowed_roles($source_type) as $role) {
                    if (array_key_exists($role, $params[$container]) || array_key_exists(self::FIELD_KEYS[$role], $params[$container])) {
                        return new \WP_Error('unsupported_person_relationship_rest_write', 'Person relationships cannot be written through REST.', ['status' => 400]);
                    }
                }
            }
        }
        foreach (PersonRelationshipContract::allowed_roles($source_type) as $role) {
            if (array_key_exists($role, $params)) {
                return new \WP_Error('unsupported_person_relationship_rest_write', 'Person relationships cannot be written through REST.', ['status' => 400]);
            }
        }
        if ($post_id > 0 && ! $this->acquire_source_write_lock($post_id)) {
            return new \WP_Error('source_lock_failed', 'This source is being edited by another request. Retry the save.', ['status' => 409]);
        }
        $this->flush_source_field_caches($post_id, $source_type);
        $projection = $this->projection_for_source($post_id, [], $source_type, false);
        $status = (string) ($prepared_post->post_status ?? ($post_id > 0 ? get_post_status($post_id) : 'draft'));
        // REST has no relationship writes for these non-REST-enabled fields.
        // Permit a status-only demotion so an invalid published source can be
        // repaired while non-public.
        if ($post_id > 0 && in_array($status, ['draft', 'pending', 'private'], true)
            && (string) get_post_status($post_id) === 'publish'
        ) {
            return $prepared_post;
        }
        if (is_wp_error($projection)) {
            $this->release_source_write_lock($post_id);
            return new \WP_Error($projection->get_error_code(), $projection->get_error_message(), ['status' => 400]);
        }
        if (in_array($status, ['publish', 'future'], true)) {
            $status = 'publish';
        }
        $result = $this->validate_projection($projection, $status);
        if (is_wp_error($result)) {
            $this->release_source_write_lock($post_id);
            return new \WP_Error($result->get_error_code(), $result->get_error_message(), ['status' => 400]);
        }
        return $prepared_post;
    }

    /** The ACF source remains saved even when its derived projection fails. */
    public function project_after_acf_save(mixed $post_id): void
    {
        $post_id = $this->normalize_id($post_id);
        if ($post_id > 0) $this->completed_acf_saves[$post_id] = true;
        $post = $post_id > 0 ? get_post($post_id) : false;
        if (is_object($post)) $this->process_source_type_transition($post_id, $post);
        $this->release_source_write_lock($post_id);
        if ($post_id < 1 || ! PersonRelationshipContract::supported_source_type((string) get_post_type($post_id))) {
            return;
        }

        try {
            $result = $this->indexer->rebuild_source($post_id);
            if (is_wp_error($result)) {
                $this->report_operator_failure($post_id, $result->get_error_code());
                return;
            }
        } catch (\Throwable $error) {
            $this->report_operator_failure($post_id, 'unexpected_' . strtolower((new \ReflectionClass($error))->getShortName()));
        }
    }

    /** Acquire before ACF persists any validated fields and mark the index unsafe first. */
    private function acquire_source_write_lock(int $post_id): bool
    {
        if ($post_id < 1) return true;
        if (isset($this->locked_source_ids[$post_id])) return true;
        if (! $this->writer_lock->acquire($post_id)) return false;
        $this->locked_source_ids[$post_id] = true;
        return true;
    }

    public function release_source_write_lock(mixed $post_id = null): void
    {
        $post_id = $this->normalize_id($post_id);
        if ($post_id > 0) {
            if (! isset($this->locked_source_ids[$post_id])) return;
            $this->writer_lock->release($post_id);
            if ($this->writer_lock->release_pending($post_id)) return;
            unset($this->locked_source_ids[$post_id]);
            if ($this->future_publish_lock_source_id === $post_id) $this->future_publish_lock_source_id = 0;
            return;
        }
        foreach (array_keys($this->locked_source_ids) as $source_id) {
            $this->release_source_write_lock($source_id);
        }
    }

    /** REST meta fields, including ACF REST fields, are persisted before this action. */
    public function project_after_rest_save(mixed $post, mixed $request = null, mixed $creating = null): void
    {
        $post_id = $this->normalize_id(is_object($post) ? ($post->ID ?? 0) : 0);
        if ($post_id > 0) {
            $this->release_source_write_lock($post_id);
            $this->project_after_acf_save($post_id);
        }
    }

    /** Remove only disposable index rows when WordPress permanently deletes a source. */
    public function remove_after_permanent_delete(int $post_id, mixed $post = null): void
    {
        $post_type = is_object($post) ? (string) ($post->post_type ?? '') : (string) get_post_type($post_id);
        if (! PersonRelationshipContract::supported_source_type($post_type)) {
            return;
        }

        $result = $this->indexer->remove_source($post_id);
        if (is_wp_error($result)) {
            error_log(sprintf('Person relationship delete cleanup failed for source %d (%s).', $post_id, $result->get_error_code()));
        }
    }

    /** Retry after WordPress removes post meta, which also removes dirty markers. */
    public function retry_delete_cleanup_after_meta_removal(int $post_id, mixed $post = null): void
    {
        $post_type = is_object($post) ? (string) ($post->post_type ?? '') : '';
        if (! PersonRelationshipContract::supported_source_type($post_type)) {
            return;
        }
        $result = $this->indexer->remove_source($post_id);
        if (is_wp_error($result)) {
            error_log(sprintf('Person relationship post-delete cleanup retry failed for source %d (%s).', $post_id, $result->get_error_code()));
        }
    }

    public function render_projection_failure_notice(): void
    {
        $post_id = $this->normalize_id($_GET['post'] ?? 0);
        if ($post_id < 1 || ! current_user_can('edit_post', $post_id)) {
            return;
        }
        $code = get_transient($this->operator_notice_key($post_id));
        if (! is_string($code) || $code === '') {
            return;
        }
        delete_transient($this->operator_notice_key($post_id));
        echo '<div class="notice notice-error"><p>' . esc_html(sprintf(
            'Person relationships were saved, but their reverse index could not be updated. The source is marked for repair (%s).',
            $code
        )) . '</p></div>';
    }

    private function report_operator_failure(int $post_id, string $code): void
    {
        $code = preg_replace('/[^a-zA-Z0-9_-]/', '', $code) ?: 'projection_failed';
        error_log(sprintf('Person relationship projection failed for source %d (%s).', $post_id, $code));
        if (get_current_user_id() > 0) {
            set_transient($this->operator_notice_key($post_id), $code, HOUR_IN_SECONDS);
        }
    }

    private function operator_notice_key(int $post_id): string
    {
        return 'vg_person_relationship_projection_error_' . get_current_user_id() . '_' . $post_id;
    }

    public function future_post_can_publish(mixed $can_publish, mixed $post_id, mixed $post): bool
    {
        if ($can_publish !== true) {
            return false;
        }
        $post_id = $this->normalize_id($post_id);
        $source_type = is_object($post) ? (string) ($post->post_type ?? '') : (string) get_post_type($post_id);
        if (! PersonRelationshipContract::supported_source_type($source_type)) {
            return true;
        }

        if (! $this->acquire_source_write_lock($post_id)) {
            $this->report_operator_failure($post_id, 'source_lock_failed');
            $this->schedule_one_future_retry($post_id);
            return false;
        }
        $this->flush_source_field_caches($post_id, $source_type);

        $projection = $this->projection_for_source($post_id, [], $source_type, false);
        if (is_wp_error($projection)) {
            $this->release_source_write_lock($post_id);
            $this->report_operator_failure($post_id, $projection->get_error_code());
            $this->schedule_one_future_retry($post_id);
            return false;
        }
        $result = $this->validate_projection($projection, 'publish');
        if (is_wp_error($result)) {
            $this->release_source_write_lock($post_id);
            $this->report_operator_failure($post_id, $result->get_error_code());
            $this->schedule_one_future_retry($post_id);
            return false;
        }
        delete_transient($this->future_retry_key($post_id));
        $this->future_publish_lock_source_id = $post_id;
        return true;
    }

    private function schedule_one_future_retry(int $post_id): void
    {
        $key = $this->future_retry_key($post_id);
        if (get_transient($key)) {
            delete_transient($key);
            return;
        }
        set_transient($key, 1, HOUR_IN_SECONDS);
        $scheduled = wp_next_scheduled('publish_future_post', [$post_id]);
        if ($scheduled !== false) return;
        $result = wp_schedule_single_event(time() + 60, 'publish_future_post', [$post_id]);
        if ($result !== true && wp_next_scheduled('publish_future_post', [$post_id]) === false) {
            error_log(sprintf('Person relationship scheduled publication retry could not be queued for source %d.', $post_id));
        }
    }

    private function future_retry_key(int $post_id): string
    {
        return 'vg_person_relationship_future_retry_' . $post_id;
    }

    /** @return array{source_post_id:int,source_post_type:string,relationships:list<array{person_id:int,role_key:string,position:int}>}|\WP_Error */
    private function projection_for_source(int $post_id, array $overrides, string $source_type, bool $allow_submitted = true): array|\WP_Error
    {
        $roles = PersonRelationshipContract::allowed_roles($source_type);
        if ($roles === []) {
            return new \WP_Error('unsupported_source', 'This post type has no approved Person relationship contract.');
        }

        $relationships = [];
        foreach ($roles as $role) {
            $field_key = self::FIELD_KEYS[$role];
            if (array_key_exists($role, $overrides)) {
                $value = $overrides[$role];
            } elseif ($allow_submitted && isset($_POST['acf']) && is_array($_POST['acf']) && array_key_exists($field_key, $_POST['acf'])) {
                $value = $_POST['acf'][$field_key];
            } elseif ($post_id > 0) {
                $value = get_field($role, $post_id, false);
            } else {
                $value = null;
            }

            if ($value === null || $value === false || $value === '') {
                continue;
            }
            $single = in_array($role, ['primary_author', 'lead_instructor'], true);
            if ($single) {
                if (is_array($value)) {
                    return new \WP_Error('malformed_relationship', sprintf('The %s field must contain one Person ID.', $role));
                }
                $values = [$value];
            } else {
                if (! is_array($value) || ! array_is_list($value)) {
                    return new \WP_Error('malformed_relationship', sprintf('The %s field must contain an ordered list of Person IDs.', $role));
                }
                $values = $value;
            }
            foreach ($values as $position => $person_id) {
                $person_id = $this->normalize_id($person_id);
                if ($person_id < 1) {
                    return new \WP_Error('malformed_relationship', sprintf('The %s field contains an invalid Person ID.', $role));
                }
                $relationships[] = ['person_id' => $person_id, 'role_key' => $role, 'position' => $position];
            }
        }

        return ['source_post_id' => $post_id, 'source_post_type' => $source_type, 'relationships' => $relationships];
    }

    private function flush_source_field_caches(int $post_id, string $source_type): void
    {
        if ($post_id < 1) return;
        if (function_exists('clean_post_cache')) clean_post_cache($post_id);
        if (function_exists('wp_cache_delete')) wp_cache_delete($post_id, 'post_meta');
        if (function_exists('acf_flush_value_cache')) {
            foreach (PersonRelationshipContract::allowed_roles($source_type) as $role) {
                acf_flush_value_cache($post_id, $role);
            }
        }
    }

    private function request_post_id(): int
    {
        return $this->normalize_id($_POST['post_ID'] ?? 0);
    }

    private function validate_projection(array $projection, string $status): true|\WP_Error
    {
        // WordPress may validate a new post before it has assigned the real ID.
        if (($projection['source_post_id'] ?? 0) < 1) {
            $projection['source_post_id'] = 1;
        }
        return $this->validator->validate_projection($projection, $status);
    }

    private function request_source_type(): string
    {
        $type = $_POST['post_type'] ?? '';
        return is_string($type) ? $type : '';
    }

    private function request_status(int $post_id, mixed $post): string
    {
        $status = $_POST['post_status'] ?? (is_object($post) ? ($post->post_status ?? null) : null);
        if (is_string($status) && $status !== '') {
            return $status;
        }
        return $post_id > 0 ? (string) get_post_status($post_id) : 'draft';
    }

    private function normalize_id(mixed $value): int
    {
        if (is_object($value)) {
            $value = $value->ID ?? null;
        } elseif (is_array($value)) {
            $value = $value['ID'] ?? $value['id'] ?? null;
        }
        if (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1) {
            $value = (int) $value;
        }
        return is_int($value) && $value > 0 ? $value : 0;
    }
}
