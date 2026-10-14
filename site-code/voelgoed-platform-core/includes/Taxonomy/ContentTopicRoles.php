<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Taxonomy;

defined('ABSPATH') || exit;

/**
 * Provides the Primary/Secondary role layer over direct content_topic terms.
 *
 * The taxonomy remains authoritative for topic membership. Primary is one ACF
 * term reference, Secondary is derived from direct membership, and Effective
 * Topics are the direct terms plus their ancestors.
 */
final class ContentTopicRoles
{
    public const PRIMARY_META_KEY = '_vg_primary_content_topic';
    public const PRIMARY_FIELD_KEY = 'field_vg_primary_content_topic';
    public const PRIMARY_GROUP_KEY = 'group_vg_content_topic_roles';
    public const MIGRATION_RECORD_OPTION = 'vgpc_content_topic_migration_local_v1';

    public const STATE_UNCLASSIFIED = 'UNCLASSIFIED';
    public const STATE_MIGRATION_SEED = 'MIGRATION_SEED';
    public const STATE_NEEDS_REVIEW = 'NEEDS_REVIEW';
    public const STATE_REVIEWED = 'REVIEWED';
    public const STATE_INVALID = 'INVALID';
    public const STATE_NOT_APPLICABLE = 'NOT_APPLICABLE';

    /** @var array<int, int> Primary IDs removed during this request, keyed by object ID. */
    private array $removed_primary_ids = [];

    /**
     * Register the ACF field group and lifecycle hooks.
     *
     * ACF is the source-controlled field owner. Its taxonomy field uses
     * save_terms/load_terms=false, leaving the native taxonomy editor as the
     * only direct-membership control.
     */
    public function register(): void
    {
        add_action('acf/init', [$this, 'register_primary_field_group']);
        add_action('acf/save_post', [$this, 'handle_acf_save'], 20, 1);
        add_action('set_object_terms', [$this, 'handle_set_object_terms'], 10, 6);
        add_action('deleted_term_relationships', [$this, 'handle_deleted_term_relationships'], 10, 3);
        add_action('delete_term', [$this, 'handle_deleted_term'], 10, 5);
        add_action('admin_notices', [$this, 'render_admin_notice']);

        add_filter(
            'acf/validate_value/key=' . self::PRIMARY_FIELD_KEY,
            [$this, 'validate_primary_field'],
            10,
            4
        );
        add_filter(
            'acf/update_value/key=' . self::PRIMARY_FIELD_KEY,
            [$this, 'filter_primary_field_update'],
            10,
            3
        );
    }

    /** Register one Primary selector on supported object types only. */
    public function register_primary_field_group(): void
    {
        if (! function_exists('acf_add_local_field_group')) {
            return;
        }

        $locations = [];
        $policy = $this->get_entity_policy();
        foreach (array_keys($policy) as $post_type) {
            if (! $policy[$post_type]['primary_supported']) {
                continue;
            }

            $locations[] = [[
                'param' => 'post_type',
                'operator' => '==',
                'value' => $post_type,
            ]];
        }

        acf_add_local_field_group([
            'key' => self::PRIMARY_GROUP_KEY,
            'title' => 'Content Topic Roles',
            'fields' => [[
                'key' => self::PRIMARY_FIELD_KEY,
                'label' => 'Content Topic: Primary Topic',
                'name' => self::PRIMARY_META_KEY,
                'type' => 'taxonomy',
                'instructions' => 'Choose the most specific useful Primary Topic. Other directly assigned Topics are treated as Secondary Topics.',
                'required' => 0,
                'taxonomy' => ContentTopicTaxonomy::TAXONOMY,
                'field_type' => 'select',
                'allow_null' => 1,
                'add_term' => 0,
                'save_terms' => 0,
                'load_terms' => 0,
                'return_format' => 'id',
                'multiple' => 0,
                'bidirectional_target' => [],
            ]],
            'location' => $locations,
            'menu_order' => 0,
            'position' => 'side',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'active' => true,
            'show_in_rest' => 0,
        ]);
    }

    /**
     * Get the persisted Primary term.
     *
     * @return object|null A valid term or WP_Error object is returned when a
     *                     reference is stored; null means no Primary is stored.
     */
    public function get_primary_topic(int $object_id): object|null
    {
        $raw = $this->raw_primary_value($object_id);
        if ($raw === null || $raw === '') {
            return null;
        }

        $term_id = $this->normalize_term_id($raw);
        if ($term_id === null) {
            return new \WP_Error('vgpc_primary_topic_invalid_value', 'The Primary Topic reference is not a valid term ID.');
        }

        $term = get_term($term_id, ContentTopicTaxonomy::TAXONOMY);
        if (! $term || $term->taxonomy !== ContentTopicTaxonomy::TAXONOMY) {
            return new \WP_Error('vgpc_primary_topic_invalid_term', 'The Primary Topic does not exist in content_topic.');
        }

        if (! $this->is_direct_term($object_id, $term_id)) {
            return new \WP_Error('vgpc_primary_topic_not_direct', 'The Primary Topic is not directly assigned to this object.');
        }

        if (! $this->supports_primary($object_id)) {
            return new \WP_Error('vgpc_primary_topic_unsupported_entity', 'This object type does not support a Primary Topic.');
        }

        return $term;
    }

    /** Return direct content_topic terms, with no inferred ancestors. */
    public function get_direct_topics(int $object_id): array|\WP_Error
    {
        if (! get_post($object_id)) {
            return new \WP_Error('vgpc_topic_object_missing', 'The requested content object does not exist.');
        }

        $terms = wp_get_object_terms($object_id, ContentTopicTaxonomy::TAXONOMY, ['orderby' => 'term_id']);
        if (is_wp_error($terms)) {
            return $terms;
        }

        return array_values(array_filter(
            $terms,
            static fn (object $term): bool => ($term->taxonomy ?? null) === ContentTopicTaxonomy::TAXONOMY
        ));
    }

    /** Return direct topics minus the Primary term; no Secondary data is stored. */
    public function get_secondary_topics(int $object_id): array|\WP_Error
    {
        if (! $this->supports_primary($object_id)) {
            return new \WP_Error('vgpc_secondary_topics_not_applicable', 'This object type uses content topics as expertise associations, not Primary/Secondary roles.');
        }

        $direct = $this->get_direct_topics($object_id);
        if (is_wp_error($direct)) {
            return $direct;
        }

        $primary = $this->get_primary_topic($object_id);
        if (is_wp_error($primary)) {
            return $primary;
        }

        $primary_id = $primary ? (int) $primary->term_id : 0;

        return array_values(array_filter(
            $direct,
            static fn (object $term): bool => (int) $term->term_id !== $primary_id
        ));
    }

    /** Return direct topics and each required ancestor exactly once. */
    public function get_effective_topics(int $object_id): array|\WP_Error
    {
        $direct = $this->get_direct_topics($object_id);
        if (is_wp_error($direct)) {
            return $direct;
        }

        $effective = [];
        $seen = [];
        foreach ($direct as $term) {
            $term_id = (int) $term->term_id;
            if (! isset($seen[$term_id])) {
                $seen[$term_id] = true;
                $effective[] = $term;
            }
        }

        foreach ($direct as $term) {
            $parent_id = (int) ($term->parent ?? 0);
            while ($parent_id > 0) {
                if (isset($seen[$parent_id])) {
                    $ancestor = get_term($parent_id, ContentTopicTaxonomy::TAXONOMY);
                    $parent_id = $ancestor ? (int) ($ancestor->parent ?? 0) : 0;
                    continue;
                }

                $ancestor = get_term($parent_id, ContentTopicTaxonomy::TAXONOMY);
                if (! $ancestor || $ancestor->taxonomy !== ContentTopicTaxonomy::TAXONOMY) {
                    return new \WP_Error('vgpc_topic_ancestor_missing', 'An assigned content_topic has a missing or invalid ancestor.');
                }

                $seen[$parent_id] = true;
                $effective[] = $ancestor;
                $parent_id = (int) ($ancestor->parent ?? 0);
            }
        }

        return $effective;
    }

    /** Derive classification state from direct terms, Primary validity, and Task 2 provenance. */
    public function get_classification_state(int $object_id): string
    {
        $raw_primary = $this->raw_primary_value($object_id);
        if ($raw_primary !== null && $raw_primary !== '') {
            $primary = $this->get_primary_topic($object_id);
            if (is_wp_error($primary) || ! $primary) {
                return self::STATE_INVALID;
            }

            return self::STATE_REVIEWED;
        }

        if (! $this->supports_primary($object_id)) {
            return self::STATE_NOT_APPLICABLE;
        }

        $direct = $this->get_direct_topics($object_id);
        if (is_wp_error($direct) || $direct === []) {
            return self::STATE_UNCLASSIFIED;
        }

        return $this->has_migration_owned_direct_topic($object_id, $direct)
            ? self::STATE_MIGRATION_SEED
            : self::STATE_NEEDS_REVIEW;
    }

    /**
     * Set Primary and ensure it is also a direct canonical topic.
     *
     * @return true|\WP_Error
     */
    public function set_primary_topic(int $object_id, int $term_id): true|\WP_Error
    {
        if (! $this->supports_primary($object_id)) {
            return new \WP_Error('vgpc_primary_topic_unsupported_entity', 'This object type does not support a Primary Topic.');
        }

        $term = get_term($term_id, ContentTopicTaxonomy::TAXONOMY);
        if (! $term || $term->taxonomy !== ContentTopicTaxonomy::TAXONOMY) {
            return new \WP_Error('vgpc_primary_topic_invalid_term', 'Primary must reference an existing content_topic term.');
        }

        $added_relationship = ! $this->is_direct_term($object_id, $term_id);
        if ($added_relationship) {
            $result = wp_set_object_terms($object_id, [$term_id], ContentTopicTaxonomy::TAXONOMY, true);
            if (is_wp_error($result)) {
                return new \WP_Error('vgpc_primary_topic_relationship_failed', 'The Primary topic could not be added as a direct content_topic relationship: ' . $result->get_error_message());
            }

            if (! $this->is_direct_term($object_id, $term_id)) {
                return new \WP_Error('vgpc_primary_topic_relationship_unverified', 'WordPress did not retain the Primary topic as a direct content_topic relationship.');
            }
        }

        $current = $this->raw_primary_value($object_id);
        if ($this->normalize_term_id($current) !== $term_id) {
            $saved = function_exists('update_field')
                ? update_field(self::PRIMARY_FIELD_KEY, $term_id, $object_id)
                : false;
            if (! $saved && $this->normalize_term_id($this->raw_primary_value($object_id)) !== $term_id) {
                if ($added_relationship) {
                    wp_remove_object_terms($object_id, $term_id, ContentTopicTaxonomy::TAXONOMY);
                }

                return new \WP_Error('vgpc_primary_topic_save_failed', 'The Primary Topic reference could not be saved.');
            }
        }

        return true;
    }

    /** Clear the Primary role without changing direct topic membership. */
    public function clear_primary_topic(int $object_id): true|\WP_Error
    {
        if (! metadata_exists('post', $object_id, self::PRIMARY_META_KEY)) {
            return true;
        }

        if (function_exists('delete_field')) {
            delete_field(self::PRIMARY_FIELD_KEY, $object_id);
        } else {
            delete_post_meta($object_id, self::PRIMARY_META_KEY);
            delete_post_meta($object_id, '_' . self::PRIMARY_META_KEY);
        }

        if (metadata_exists('post', $object_id, self::PRIMARY_META_KEY)) {
            delete_post_meta($object_id, self::PRIMARY_META_KEY);
            delete_post_meta($object_id, '_' . self::PRIMARY_META_KEY);
        }

        return metadata_exists('post', $object_id, self::PRIMARY_META_KEY)
            ? new \WP_Error('vgpc_primary_topic_clear_failed', 'The Primary Topic reference could not be cleared.')
            : true;
    }

    /** Validate the persisted Primary invariant; no Primary is valid for optional/soft policy. */
    public function validate_primary_topic(int $object_id): true|\WP_Error
    {
        if (! $this->supports_primary($object_id)) {
            return $this->raw_primary_value($object_id) === null || $this->raw_primary_value($object_id) === ''
                ? true
                : new \WP_Error('vgpc_primary_topic_unsupported_entity', 'This object type does not support a Primary Topic.');
        }

        $raw = $this->raw_primary_value($object_id);
        if ($raw === null || $raw === '') {
            return true;
        }

        $primary = $this->get_primary_topic($object_id);

        return is_wp_error($primary)
            ? $primary
            : true;
    }

    /** Validate the submitted ACF value before it is saved. */
    public function validate_primary_field(mixed $valid, mixed $value, array $field, string $input): mixed
    {
        if ($valid !== true) {
            return $valid;
        }

        return $this->validate_primary_value($value, $this->submitted_object_id());
    }

    /** Validate a Primary value against an explicit target object ID. */
    private function validate_primary_value(mixed $value, int $object_id): true|string
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return true;
        }

        $term_id = $this->normalize_term_id($value);
        if ($term_id === null) {
            return 'Choose one valid Primary Topic.';
        }

        $term = get_term($term_id, ContentTopicTaxonomy::TAXONOMY);
        if (! $term || $term->taxonomy !== ContentTopicTaxonomy::TAXONOMY) {
            return 'Primary Topic must be a term from content_topic.';
        }

        if ($object_id > 0 && ! $this->supports_primary($object_id)) {
            return 'This object type does not support a Primary Topic.';
        }

        return true;
    }

    /** Prevent invalid values sent outside the normal ACF form from replacing a valid stored role. */
    public function filter_primary_field_update(mixed $value, mixed $post_id, array $field): mixed
    {
        $object_id = is_numeric($post_id) ? (int) $post_id : 0;
        if ($object_id < 1 || ! $this->supports_primary($object_id)) {
            return $object_id > 0 ? $this->raw_primary_value($object_id) : null;
        }

        $valid = $this->validate_primary_value($value, $object_id);
        if ($valid !== true) {
            return $this->raw_primary_value($object_id);
        }

        $term_id = $this->normalize_term_id($value);
        if ($term_id !== null && ! $this->is_direct_term($object_id, $term_id)) {
            return $this->raw_primary_value($object_id);
        }

        return $value;
    }

    /** Apply or clear the actual ACF form value after ACF and WordPress taxonomy saves. */
    public function handle_acf_save(mixed $post_id): void
    {
        $object_id = is_numeric($post_id) ? (int) $post_id : 0;
        if ($object_id < 1 || ! isset($_POST['acf']) || ! is_array($_POST['acf']) || ! array_key_exists(self::PRIMARY_FIELD_KEY, $_POST['acf'])) {
            return;
        }

        if (! $this->supports_primary($object_id)) {
            return;
        }

        $submitted = $_POST['acf'][self::PRIMARY_FIELD_KEY];
        if ($submitted === null || $submitted === '' || $submitted === 0 || $submitted === '0') {
            $this->clear_primary_topic($object_id);
            return;
        }

        $term_id = $this->normalize_term_id($submitted);
        if ($term_id === null) {
            return;
        }

        if ($this->validate_primary_value($submitted, $object_id) !== true) {
            return;
        }

        // If an editor removed the current Primary from the direct checklist
        // but the form still carried its old select value, honor the removal.
        if (($this->removed_primary_ids[$object_id] ?? null) === $term_id) {
            $this->clear_primary_topic($object_id);
            return;
        }

        $result = $this->set_primary_topic($object_id, $term_id);
        if (is_wp_error($result)) {
            // ACF saved the submitted field before this hook ran. Remove that
            // value if the matching direct taxonomy relationship could not be
            // established, so persisted Primary never points at a non-member.
            $this->clear_primary_topic($object_id);
        }
    }

    /** Keep Primary from surviving a direct relationship removal. */
    public function handle_set_object_terms(int $object_id, mixed $terms, array $term_taxonomy_ids, string $taxonomy, bool $append, array $old_term_taxonomy_ids): void
    {
        $this->clear_role_if_relationship_missing($object_id, $taxonomy);
    }

    /** Keep Primary from surviving a direct relationship removal. */
    public function handle_deleted_term_relationships(int $object_id, array $term_taxonomy_ids, string $taxonomy): void
    {
        $this->clear_role_if_relationship_missing($object_id, $taxonomy);
    }

    /** Clear Primary references when their canonical term is deleted. */
    public function handle_deleted_term(int $term_id, int $term_taxonomy_id, string $taxonomy, mixed $deleted_term = null, array $object_ids = []): void
    {
        if ($taxonomy !== ContentTopicTaxonomy::TAXONOMY) {
            return;
        }

        foreach (get_posts([
            'post_type' => array_keys($this->get_entity_policy()),
            'post_status' => 'any',
            'meta_key' => self::PRIMARY_META_KEY,
            'meta_value' => $term_id,
            'fields' => 'ids',
            'numberposts' => -1,
        ]) as $object_id) {
            $this->clear_primary_topic((int) $object_id);
        }
    }

    /** Return direct parent/child duplication findings without changing assignments. */
    public function get_hierarchy_warnings(int $object_id): array|\WP_Error
    {
        $direct = $this->get_direct_topics($object_id);
        if (is_wp_error($direct)) {
            return $direct;
        }

        $assigned = [];
        foreach ($direct as $term) {
            $assigned[(int) $term->term_id] = $term;
        }

        $warnings = [];
        foreach ($direct as $child) {
            $parent_id = (int) ($child->parent ?? 0);
            while ($parent_id > 0) {
                $parent = get_term($parent_id, ContentTopicTaxonomy::TAXONOMY);
                if (! $parent || $parent->taxonomy !== ContentTopicTaxonomy::TAXONOMY) {
                    break;
                }
                if (isset($assigned[$parent_id])) {
                    $warnings[] = [
                        'code' => 'redundant_parent_child',
                        'parent_term_id' => $parent_id,
                        'parent_slug' => (string) $parent->slug,
                        'child_term_id' => (int) $child->term_id,
                        'child_slug' => (string) $child->slug,
                        'severity' => 'warning',
                    ];
                }
                $parent_id = (int) ($parent->parent ?? 0);
            }
        }

        return $warnings;
    }

    /** Return editorial policy for all currently attached content_topic entity types. */
    public function get_entity_policy(): array
    {
        return [
            'post' => ['primary_supported' => true, 'primary_requirement' => 'required_soft', 'editorial_policy' => 'Required for fully reviewed normal editorial content; no save lock during migration.'],
            'mto-course' => ['primary_supported' => true, 'primary_requirement' => 'optional', 'editorial_policy' => 'Optional.'],
            'product' => ['primary_supported' => true, 'primary_requirement' => 'optional', 'editorial_policy' => 'Optional.'],
            'tc_events' => ['primary_supported' => true, 'primary_requirement' => 'optional', 'editorial_policy' => 'Optional.'],
            'medewerker' => ['primary_supported' => false, 'primary_requirement' => 'not_applicable', 'editorial_policy' => 'content_topic terms represent expertise or subject associations.'],
            'uitsending' => ['primary_supported' => true, 'primary_requirement' => 'optional', 'editorial_policy' => 'Optional.'],
            'kompetisie' => ['primary_supported' => true, 'primary_requirement' => 'optional', 'editorial_policy' => 'Optional.'],
            'person' => ['primary_supported' => false, 'primary_requirement' => 'not_applicable', 'editorial_policy' => 'If introduced, content_topic terms represent expertise or subject associations.'],
        ];
    }

    /** Build the editor message and expose warnings through a stable service contract. */
    public function get_editor_status(int $object_id): array
    {
        $state = $this->get_classification_state($object_id);
        $messages = [
            self::STATE_UNCLASSIFIED => 'No Topics are assigned yet.',
            self::STATE_MIGRATION_SEED => 'Topic classification awaiting editorial review.',
            self::STATE_NEEDS_REVIEW => 'Choose a Primary Topic to complete editorial classification.',
            self::STATE_REVIEWED => 'Topic classification reviewed.',
            self::STATE_INVALID => 'The saved Primary Topic is invalid. Choose a valid Primary Topic.',
            self::STATE_NOT_APPLICABLE => 'Topics on this profile describe expertise or subject associations.',
        ];
        $warnings = $this->get_hierarchy_warnings($object_id);

        return [
            'state' => $state,
            'message' => $messages[$state] ?? 'Topic classification status is unavailable.',
            'hierarchy_warnings' => is_wp_error($warnings) ? [] : $warnings,
        ];
    }

    /** Display a soft review notice on a supported object's edit screen. */
    public function render_admin_notice(): void
    {
        if (! is_admin()) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (! $screen || ($screen->base ?? '') !== 'post') {
            return;
        }

        $object_id = isset($_GET['post']) && is_numeric($_GET['post'])
            ? (int) $_GET['post']
            : (isset($_POST['post_ID']) && is_numeric($_POST['post_ID']) ? (int) $_POST['post_ID'] : 0);
        if ($object_id < 1 || ! $this->supports_primary($object_id)) {
            return;
        }

        $status = $this->get_editor_status($object_id);
        $notice_type = match ($status['state']) {
            self::STATE_REVIEWED => 'success',
            self::STATE_UNCLASSIFIED => 'info',
            default => 'warning',
        };

        printf(
            '<div class="notice notice-%s"><p>%s</p></div>',
            esc_attr($notice_type),
            esc_html($status['message'])
        );
        foreach ($status['hierarchy_warnings'] as $warning) {
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                esc_html(sprintf('Redundant topic selection: both %s and its child %s are assigned.', $warning['parent_slug'], $warning['child_slug']))
            );
        }
    }

    private function supports_primary(int $object_id): bool
    {
        $post_type = get_post_type($object_id);
        $policy = $this->get_entity_policy();

        return is_string($post_type)
            && isset($policy[$post_type])
            && $policy[$post_type]['primary_supported'] === true;
    }

    private function raw_primary_value(int $object_id): mixed
    {
        if (! metadata_exists('post', $object_id, self::PRIMARY_META_KEY)) {
            return null;
        }

        if (function_exists('get_field')) {
            return get_field(self::PRIMARY_FIELD_KEY, $object_id, false);
        }

        return get_post_meta($object_id, self::PRIMARY_META_KEY, true);
    }

    private function normalize_term_id(mixed $value): ?int
    {
        if (! is_int($value) && !(is_string($value) && ctype_digit($value))) {
            return null;
        }

        $term_id = (int) $value;
        return $term_id > 0 ? $term_id : null;
    }

    private function is_direct_term(int $object_id, int $term_id): bool
    {
        $ids = wp_get_object_terms($object_id, ContentTopicTaxonomy::TAXONOMY, ['fields' => 'ids']);
        return ! is_wp_error($ids) && in_array($term_id, array_map('intval', $ids), true);
    }

    private function submitted_object_id(): int
    {
        if (isset($_POST['post_ID']) && is_numeric($_POST['post_ID'])) {
            return (int) $_POST['post_ID'];
        }

        $acf_post_id = $_POST['_acf_post_id'] ?? null;
        return is_numeric($acf_post_id) ? (int) $acf_post_id : 0;
    }

    private function clear_role_if_relationship_missing(int $object_id, string $taxonomy): void
    {
        if ($taxonomy !== ContentTopicTaxonomy::TAXONOMY) {
            return;
        }

        $raw = $this->raw_primary_value($object_id);
        $term_id = $this->normalize_term_id($raw);
        if ($term_id === null || $this->is_direct_term($object_id, $term_id)) {
            return;
        }

        $this->removed_primary_ids[$object_id] = $term_id;
        $this->clear_primary_topic($object_id);
    }

    private function has_migration_owned_direct_topic(int $object_id, array $direct_terms): bool
    {
        $record = get_option(self::MIGRATION_RECORD_OPTION, false);
        $created = is_array($record) && is_array($record['created_relationships'] ?? null)
            ? $record['created_relationships']
            : [];
        if ($created === []) {
            return false;
        }

        $direct_ids = array_map(static fn (object $term): int => (int) $term->term_id, $direct_terms);
        foreach ($created as $row) {
            if ((int) ($row['object_id'] ?? 0) === $object_id && in_array((int) ($row['term_id'] ?? 0), $direct_ids, true)) {
                return true;
            }
        }

        return false;
    }
}
