<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Taxonomy;

defined('ABSPATH') || exit;

/**
 * Plans and applies the small, reviewed content_category to content_topic proof.
 *
 * The default WP-CLI command is read-only. Apply requires the fingerprint printed
 * by the reviewed dry run, and rollback removes only rows recorded by this run.
 */
final class ContentTopicMigration
{
    public const COMMAND = 'vg-content-topic-migrate';

    private const SOURCE_TAXONOMY = 'content_category';
    private const TARGET_TAXONOMY = ContentTopicTaxonomy::TAXONOMY;
    private const MIGRATION_ID = 'content-topic-local-v1';
    private const RECORD_OPTION = 'vgpc_content_topic_migration_local_v1';

    private const LEGACY_OBJECT_TYPES = [
        'post',
        'product',
        'tc_events',
        'mto-course',
        'medewerker',
        'uitsending',
        'kompetisie',
        'kompetisie_entry',
        'testimonial',
        'team_member',
    ];

    private const EXPECTED_ASSIGNMENTS = [
        [514, 'post', 'nuwe-jy'],
        [516, 'post', 'geloof'],
        [518, 'post', 'ouerskap'],
        [519, 'post', 'werk-geld'],
        [521, 'post', 'mensstories'],
        [523, 'product', 'natuurlik-gesond'],
        [523, 'product', 'nuwe-jy'],
        [641, 'post', 'self'],
    ];

    private const EXPECTED_LEGACY_TERMS = [
        'aanlyn-onderwys',
        'doen-goed',
        'finansies',
        'geloof',
        'huwelik',
        'leefstyl',
        'mensstories',
        'natuurlik-gesond',
        'nuwe-jy',
        'ouerskap',
        'self',
        'vakansie',
        'werk-geld',
    ];

    private const LEGACY_PARENTS = [
        'aanlyn-onderwys' => 'leefstyl',
        'doen-goed' => 'leefstyl',
        'finansies' => 'leefstyl',
        'natuurlik-gesond' => 'leefstyl',
        'vakansie' => 'leefstyl',
    ];

    public static function mapping(): array
    {
        $mapping = [];
        foreach (['nuwe-jy', 'geloof', 'self', 'werk-geld', 'ouerskap', 'huwelik', 'mensstories'] as $slug) {
            $mapping[$slug] = ['action' => 'DIRECT_MAP', 'target' => $slug];
        }
        foreach (['leefstyl', 'aanlyn-onderwys', 'doen-goed', 'finansies', 'natuurlik-gesond', 'vakansie'] as $slug) {
            $mapping[$slug] = ['action' => 'SKIP_COMMERCIAL_CONTEXT', 'target' => null];
        }

        return $mapping;
    }

    public function register(): void
    {
        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            \WP_CLI::add_command(self::COMMAND, [$this, 'command']);
        }
    }

    public function dry_run(): array|\WP_Error
    {
        $plan = $this->build_plan();
        if (is_wp_error($plan)) {
            return $plan;
        }

        return $plan;
    }

    public function apply(string $expected_fingerprint): array|\WP_Error
    {
        if ($expected_fingerprint === '') {
            return new \WP_Error('vgpc_topic_migration_fingerprint_required', 'Apply requires the fingerprint printed by the reviewed dry run.');
        }

        $plan = $this->build_plan();
        if (is_wp_error($plan)) {
            return $plan;
        }

        if (! hash_equals($plan['fingerprint'], $expected_fingerprint)) {
            return new \WP_Error('vgpc_topic_migration_source_drift', sprintf(
                'Source state changed after dry run. Expected fingerprint %s, current fingerprint %s. No relationships were written.',
                $expected_fingerprint,
                $plan['fingerprint']
            ));
        }

        if ($plan['blockers'] !== []) {
            return new \WP_Error('vgpc_topic_migration_preflight_failed', implode(' ', $plan['blockers']));
        }

        $record = get_option(self::RECORD_OPTION, false);
        if (is_array($record)) {
            if (($record['migration'] ?? null) !== self::MIGRATION_ID || ($record['source_fingerprint'] ?? null) !== $plan['fingerprint']) {
                return new \WP_Error('vgpc_topic_migration_record_conflict', 'The saved migration record does not match this source snapshot. No relationships were written.');
            }

            $verification = $this->verify_record_relationships($record, $plan);
            if (is_wp_error($verification)) {
                return $verification;
            }

            return [
                'mode' => 'apply',
                'relationships_already_present' => count($record['created_relationships']),
                'created_relationships' => 0,
                'legacy_relationships_changed' => 0,
                'record' => $record,
            ];
        }

        if ($plan['canonical_relationships'] !== []) {
            return new \WP_Error('vgpc_topic_migration_unowned_relationships', 'Canonical topic relationships already exist without a matching migration record. No relationships were written.');
        }

        $created = [];
        $started_at = gmdate('c');
        foreach ($plan['expected_topics'] as $row) {
            $term = get_term_by('slug', $row['term_slug'], self::TARGET_TAXONOMY);
            if (! $term || $term->taxonomy !== self::TARGET_TAXONOMY || $term->slug !== $row['term_slug']) {
                $cleanup = $this->remove_created_relationships($created);
                return new \WP_Error('vgpc_topic_migration_target_missing', sprintf(
                    'Canonical term "%s" could not be resolved. %s',
                    $row['term_slug'],
                    $cleanup
                ));
            }

            $result = wp_set_object_terms((int) $row['object_id'], [(int) $term->term_id], self::TARGET_TAXONOMY, true);
            if (is_wp_error($result)) {
                $cleanup = $this->remove_created_relationships($created);
                return new \WP_Error('vgpc_topic_migration_write_failed', sprintf(
                    'Could not assign "%s" to object %d: %s. %s',
                    $row['term_slug'],
                    $row['object_id'],
                    $result->get_error_message(),
                    $cleanup
                ));
            }

            $assigned = wp_get_object_terms((int) $row['object_id'], self::TARGET_TAXONOMY, ['fields' => 'slugs']);
            if (is_wp_error($assigned) || ! in_array($row['term_slug'], $assigned, true)) {
                $created[] = ['object_id' => (int) $row['object_id'], 'term_slug' => $row['term_slug'], 'term_id' => (int) $term->term_id];
                $cleanup = $this->remove_created_relationships($created);
                return new \WP_Error('vgpc_topic_migration_verify_failed', sprintf(
                    'WordPress did not retain "%s" on object %d. %s',
                    $row['term_slug'],
                    $row['object_id'],
                    $cleanup
                ));
            }

            $created[] = ['object_id' => (int) $row['object_id'], 'term_slug' => $row['term_slug'], 'term_id' => (int) $term->term_id];
        }

        $record = [
            'migration' => self::MIGRATION_ID,
            'source_fingerprint' => $plan['fingerprint'],
            'created_relationships' => $created,
            'started_at' => $started_at,
            'completed_at' => gmdate('c'),
            'source_snapshot' => $plan['source_snapshot'],
        ];
        if (! update_option(self::RECORD_OPTION, $record, false) || get_option(self::RECORD_OPTION, false) !== $record) {
            $cleanup = $this->remove_created_relationships($created);
            delete_option(self::RECORD_OPTION);
            return new \WP_Error('vgpc_topic_migration_record_failed', 'The migration record could not be saved and verified. ' . $cleanup);
        }

        return [
            'mode' => 'apply',
            'relationships_already_present' => 0,
            'created_relationships' => count($created),
            'legacy_relationships_changed' => 0,
            'record' => $record,
        ];
    }

    public function rollback(): array|\WP_Error
    {
        $record = get_option(self::RECORD_OPTION, false);
        if (! is_array($record) || ($record['migration'] ?? null) !== self::MIGRATION_ID) {
            return new \WP_Error('vgpc_topic_migration_record_missing', 'No valid local content topic migration record exists. Nothing was changed.');
        }

        $plan = $this->build_plan();
        if (is_wp_error($plan)) {
            return $plan;
        }
        if (($record['source_fingerprint'] ?? null) !== $plan['fingerprint']) {
            return new \WP_Error('vgpc_topic_migration_source_drift', 'Legacy source state no longer matches the migration record. Rollback stopped without changes.');
        }

        foreach ($record['created_relationships'] ?? [] as $row) {
            $term = get_term((int) ($row['term_id'] ?? 0), self::TARGET_TAXONOMY);
            $assigned = wp_get_object_terms((int) ($row['object_id'] ?? 0), self::TARGET_TAXONOMY, ['fields' => 'slugs']);
            if (
                ! $term
                || $term->slug !== ($row['term_slug'] ?? null)
                || is_wp_error($assigned)
                || ! in_array((string) $row['term_slug'], $assigned, true)
            ) {
                return new \WP_Error('vgpc_topic_migration_rollback_drift', 'A recorded migration relationship or term changed. Rollback stopped without changes.');
            }
        }

        $removed = [];
        foreach ($record['created_relationships'] as $row) {
            $result = wp_remove_object_terms((int) $row['object_id'], (int) $row['term_id'], self::TARGET_TAXONOMY);
            if (is_wp_error($result) || $result === false) {
                foreach ($removed as $removed_row) {
                    wp_set_object_terms((int) $removed_row['object_id'], [(int) $removed_row['term_id']], self::TARGET_TAXONOMY, true);
                }
                return new \WP_Error('vgpc_topic_migration_rollback_failed', 'A recorded relationship could not be removed. Relationships removed earlier in this rollback were restored.');
            }
            $removed[] = $row;
        }

        delete_option(self::RECORD_OPTION);

        return [
            'mode' => 'rollback',
            'relationships_removed' => count($removed),
            'legacy_relationships_changed' => 0,
        ];
    }

    public function command(array $args, array $assoc_args): void
    {
        $apply = isset($assoc_args['apply']);
        $rollback = isset($assoc_args['rollback']);
        if ($apply && $rollback) {
            \WP_CLI::error('Choose either --apply or --rollback.');
        }

        $format = (string) ($assoc_args['format'] ?? 'table');
        if (! in_array($format, ['table', 'json'], true)) {
            \WP_CLI::error('Format must be table or json.');
        }

        $result = $rollback
            ? $this->rollback()
            : ($apply
                ? $this->apply((string) ($assoc_args['fingerprint'] ?? ''))
                : $this->dry_run());

        if (is_wp_error($result)) {
            \WP_CLI::error($result->get_error_message());
        }

        if ($format === 'json') {
            \WP_CLI::line((string) wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return;
        }

        $this->print_table($result);
    }

    private function build_plan(): array|\WP_Error
    {
        $legacy_terms = get_terms(['taxonomy' => self::SOURCE_TAXONOMY, 'hide_empty' => false]);
        $canonical_terms = get_terms(['taxonomy' => self::TARGET_TAXONOMY, 'hide_empty' => false]);
        if (is_wp_error($legacy_terms) || is_wp_error($canonical_terms)) {
            return new \WP_Error('vgpc_topic_migration_terms_unavailable', 'The source or canonical taxonomy terms could not be read.');
        }

        $canonical_validation = $this->validate_canonical_tree($canonical_terms);
        $blockers = is_wp_error($canonical_validation) ? [$canonical_validation->get_error_message()] : [];

        $legacy_by_slug = [];
        foreach ($legacy_terms as $term) {
            $legacy_by_slug[(string) $term->slug] = $term;
        }
        $legacy_slugs = array_keys($legacy_by_slug);
        sort($legacy_slugs);
        $expected_legacy_slugs = self::EXPECTED_LEGACY_TERMS;
        sort($expected_legacy_slugs);
        if ($legacy_slugs !== $expected_legacy_slugs) {
            $blockers[] = sprintf('Expected 13 legacy slugs, found %d. Actual: %s.', count($legacy_slugs), implode(', ', $legacy_slugs));
        }
        $this->validate_legacy_tree($legacy_by_slug, $blockers);

        $canonical_taxonomy = get_taxonomy(self::TARGET_TAXONOMY);
        $canonical_types = is_object($canonical_taxonomy) && is_array($canonical_taxonomy->object_type ?? null)
            ? $canonical_taxonomy->object_type
            : [];
        sort($canonical_types);
        $expected_canonical_types = ContentTopicTaxonomy::OBJECT_TYPES;
        sort($expected_canonical_types);
        if (! $canonical_taxonomy || $canonical_types !== $expected_canonical_types) {
            $blockers[] = 'The content_topic object-type attachments differ from the approved baseline.';
        }

        $taxonomy = get_taxonomy(self::SOURCE_TAXONOMY);
        if (! $taxonomy) {
            $blockers[] = 'The content_category taxonomy is not registered.';
            $legacy_types = [];
        } else {
            $legacy_types = is_array($taxonomy->object_type ?? null) ? $taxonomy->object_type : [];
            sort($legacy_types);
            $expected_types = self::LEGACY_OBJECT_TYPES;
            sort($expected_types);
            if ($legacy_types !== $expected_types) {
                $blockers[] = 'The content_category object-type attachments differ from the approved baseline.';
            }
        }

        $mapping = self::mapping();
        $objects = [];
        $legacy_relationships = [];
        foreach ($legacy_terms as $term) {
            $object_ids = get_objects_in_term((int) $term->term_id, self::SOURCE_TAXONOMY);
            if (is_wp_error($object_ids)) {
                return $object_ids;
            }
            foreach ($object_ids as $object_id) {
                $object_id = (int) $object_id;
                $post = get_post($object_id);
                if (! $post) {
                    $blockers[] = sprintf('Legacy relationship points to missing object %d.', $object_id);
                    continue;
                }
                $type = (string) get_post_type($post);
                $legacy_relationships[] = ['object_id' => $object_id, 'object_type' => $type, 'term_slug' => (string) $term->slug];
                $objects[$object_id] ??= [
                    'object_id' => $object_id,
                    'object_type' => $type,
                    'title' => (string) get_the_title($post),
                    'legacy_terms' => [],
                ];
                $objects[$object_id]['legacy_terms'][] = (string) $term->slug;
            }
        }
        ksort($objects, SORT_NUMERIC);

        usort($legacy_relationships, [$this, 'compare_relationship_rows']);
        foreach ($objects as &$object) {
            sort($object['legacy_terms']);
            if (! isset(self::EXPECTED_OBJECT_TYPES()[$object['object_id']])) {
                $blockers[] = sprintf('Object %d is outside the approved seven-object manifest.', $object['object_id']);
            } elseif ($object['object_type'] !== self::EXPECTED_OBJECT_TYPES()[$object['object_id']]) {
                $blockers[] = sprintf('Object %d has type %s, expected %s.', $object['object_id'], $object['object_type'], self::EXPECTED_OBJECT_TYPES()[$object['object_id']]);
            }
        }
        unset($object);

        $expected_relationships = array_map(
            static fn (array $row): array => ['object_id' => $row[0], 'object_type' => $row[1], 'term_slug' => $row[2]],
            self::EXPECTED_ASSIGNMENTS
        );
        usort($expected_relationships, [$this, 'compare_relationship_rows']);
        if ($legacy_relationships !== $expected_relationships) {
            $blockers[] = sprintf('Expected eight exact legacy relationships across seven objects; found %d relationships across %d objects.', count($legacy_relationships), count($objects));
        }
        if (count($legacy_terms) !== 13 || count($legacy_relationships) !== 8 || count($objects) !== 7) {
            $blockers[] = sprintf('Baseline count mismatch: %d legacy terms, %d relationships, %d objects.', count($legacy_terms), count($legacy_relationships), count($objects));
        }

        $unknown_assigned_terms = [];
        $expected_topics = [];
        foreach ($objects as &$object) {
            $direct = [];
            $skipped = [];
            $unknown = [];
            foreach ($object['legacy_terms'] as $slug) {
                if (! array_key_exists($slug, $mapping)) {
                    $unknown[] = $slug;
                    $unknown_assigned_terms[$slug] = true;
                    continue;
                }
                if ($mapping[$slug]['action'] === 'DIRECT_MAP') {
                    $direct[] = $mapping[$slug]['target'];
                    $expected_topics[] = ['object_id' => $object['object_id'], 'term_slug' => $mapping[$slug]['target']];
                } else {
                    $skipped[] = $slug;
                }
            }
            $object['direct_semantic_terms'] = array_values(array_unique($direct));
            $object['skipped_commercial_terms'] = array_values(array_unique($skipped));
            $object['canonical_topics'] = array_values(array_unique($direct));
            $object['review_required'] = $unknown !== [] || ($direct === [] && $skipped !== []);
            if ($unknown !== []) {
                $object['action'] = 'BLOCK_UNKNOWN_TERM';
                $object['reason'] = 'Unmapped assigned term(s): ' . implode(', ', $unknown) . '.';
            } elseif ($direct === [] && $skipped !== []) {
                $object['action'] = 'REVIEW_REQUIRED';
                $object['reason'] = 'Commercial context alone does not establish a semantic topic.';
            } elseif ($skipped !== []) {
                $object['action'] = 'MIGRATE_WITH_COMMERCIAL_SKIP';
                $object['reason'] = implode(', ', $skipped) . ' is commercial context, not a semantic topic.';
            } else {
                $object['action'] = 'DIRECT_MAP';
                $object['reason'] = 'Legacy pillar maps directly to the same canonical topic.';
            }
        }
        unset($object);
        usort($expected_topics, [$this, 'compare_relationship_rows']);
        foreach (array_keys($unknown_assigned_terms) as $slug) {
            $blockers[] = sprintf('Assigned legacy term "%s" has no migration classification.', $slug);
        }
        foreach ($objects as $object) {
            if ($object['review_required']) {
                $blockers[] = sprintf('Object %d requires manual semantic topic review.', $object['object_id']);
            }
        }

        $canonical_slugs = array_map(static fn (object $term): string => (string) $term->slug, $canonical_terms);
        sort($canonical_slugs);
        $canonical_relationships = $this->relationship_rows($canonical_terms, self::TARGET_TAXONOMY);
        if (is_wp_error($canonical_relationships)) {
            return $canonical_relationships;
        }

        $record = get_option(self::RECORD_OPTION, false);
        if (! is_array($record)) {
            if ($canonical_relationships !== []) {
                $blockers[] = 'Canonical topic relationships exist before migration and no matching run record is present.';
            }
        } elseif (($record['migration'] ?? null) !== self::MIGRATION_ID) {
            $blockers[] = 'A conflicting migration record exists for this option.';
        } elseif (($record['source_fingerprint'] ?? null) !== $this->make_fingerprint($legacy_slugs, $legacy_relationships, $canonical_slugs, $objects, $legacy_types, $canonical_types)) {
            $blockers[] = 'The saved migration record does not match current legacy source data.';
        }

        $already_present = [];
        foreach ($expected_topics as $row) {
            $assigned = wp_get_object_terms((int) $row['object_id'], self::TARGET_TAXONOMY, ['fields' => 'slugs']);
            if (is_wp_error($assigned)) {
                return $assigned;
            }
            if (in_array($row['term_slug'], $assigned, true)) {
                $already_present[] = $row;
            }
        }

        $source_snapshot = [
            'legacy_term_slugs' => $legacy_slugs,
            'legacy_relationships' => $legacy_relationships,
            'canonical_term_slugs' => $canonical_slugs,
            'taxonomy_object_types' => [
                self::SOURCE_TAXONOMY => $legacy_types,
                self::TARGET_TAXONOMY => $canonical_types,
            ],
            'objects' => array_map(static fn (array $object): array => [
                'object_id' => $object['object_id'],
                'object_type' => $object['object_type'],
            ], array_values($objects)),
        ];
        $fingerprint = $this->make_fingerprint($legacy_slugs, $legacy_relationships, $canonical_slugs, $objects, $legacy_types, $canonical_types);

        return [
            'migration' => self::MIGRATION_ID,
            'fingerprint' => $fingerprint,
            'source_snapshot' => $source_snapshot,
            'baseline_matches' => $blockers === [],
            'blockers' => array_values(array_unique($blockers)),
            'objects' => array_values($objects),
            'expected_topics' => $expected_topics,
            'canonical_relationships' => $canonical_relationships,
            'summary' => [
                'objects_inspected' => count($objects),
                'legacy_relationships' => count($legacy_relationships),
                'canonical_relationships_to_add' => count($expected_topics) - count($already_present),
                'commercial_relationships_skipped' => count(array_filter(
                    $legacy_relationships,
                    static fn (array $row): bool => (self::mapping()[$row['term_slug']]['action'] ?? null) === 'SKIP_COMMERCIAL_CONTEXT'
                )),
                'review_required' => count(array_filter($objects, static fn (array $object): bool => $object['review_required'])),
                'canonical_relationships_already_present' => count($already_present),
                'legacy_relationships_to_delete' => 0,
                'canonical_relationships_to_delete' => 0,
            ],
        ];
    }

    private function validate_canonical_tree(array $terms): true|\WP_Error
    {
        if (count($terms) !== 55) {
            return new \WP_Error('vgpc_topic_migration_canonical_count', sprintf('Expected 55 canonical terms; found %d.', count($terms)));
        }

        $by_slug = [];
        foreach ($terms as $term) {
            $by_slug[(string) $term->slug] = $term;
        }
        foreach (ContentTopicTaxonomy::definitions() as $definition) {
            $term = $by_slug[$definition['slug']] ?? null;
            if (! $term || $term->taxonomy !== self::TARGET_TAXONOMY) {
                return new \WP_Error('vgpc_topic_migration_canonical_term_missing', sprintf('Canonical term "%s" is missing or belongs to another taxonomy.', $definition['slug']));
            }
            if ($term->name !== sanitize_term_field('name', $definition['name'], (int) $term->term_id, self::TARGET_TAXONOMY, 'db')) {
                return new \WP_Error('vgpc_topic_migration_canonical_name_conflict', sprintf('Canonical term "%s" has an unexpected name.', $definition['slug']));
            }
            $parent_slug = null;
            if ((int) $term->parent !== 0) {
                $parent = get_term((int) $term->parent, self::TARGET_TAXONOMY);
                $parent_slug = $parent ? (string) $parent->slug : '(missing)';
            }
            if ($parent_slug !== $definition['parent']) {
                return new \WP_Error('vgpc_topic_migration_canonical_parent_conflict', sprintf('Canonical term "%s" has parent "%s", expected "%s".', $definition['slug'], $parent_slug ?? '(root)', $definition['parent'] ?? '(root)'));
            }
        }
        if (isset($by_slug['leefstyl']) || isset($by_slug['vrouegesondheid'])) {
            return new \WP_Error('vgpc_topic_migration_canonical_exclusion', 'Leefstyl and Vrouegesondheid must be absent from content_topic.');
        }
        $roots = count(array_filter($terms, static fn (object $term): bool => (int) $term->parent === 0));
        if ($roots !== 7) {
            return new \WP_Error('vgpc_topic_migration_canonical_roots', sprintf('Expected seven canonical roots; found %d.', $roots));
        }

        return true;
    }

    private function validate_legacy_tree(array $terms_by_slug, array &$blockers): void
    {
        foreach (self::LEGACY_PARENTS as $slug => $parent_slug) {
            $term = $terms_by_slug[$slug] ?? null;
            $parent = $terms_by_slug[$parent_slug] ?? null;
            if (! $term || ! $parent || (int) $term->parent !== (int) $parent->term_id) {
                $blockers[] = sprintf('Legacy term "%s" is not a child of "%s".', $slug, $parent_slug);
            }
        }
        foreach (array_diff(self::EXPECTED_LEGACY_TERMS, array_keys(self::LEGACY_PARENTS), ['leefstyl']) as $slug) {
            $term = $terms_by_slug[$slug] ?? null;
            if ($term && (int) $term->parent !== 0) {
                $blockers[] = sprintf('Legacy pillar "%s" is not a root term.', $slug);
            }
        }
    }

    private function relationship_rows(array $terms, string $taxonomy): array|\WP_Error
    {
        $rows = [];
        foreach ($terms as $term) {
            $object_ids = get_objects_in_term((int) $term->term_id, $taxonomy);
            if (is_wp_error($object_ids)) {
                return $object_ids;
            }
            foreach ($object_ids as $object_id) {
                $post = get_post((int) $object_id);
                $rows[] = [
                    'object_id' => (int) $object_id,
                    'object_type' => $post ? (string) get_post_type($post) : '(missing)',
                    'term_slug' => (string) $term->slug,
                ];
            }
        }
        usort($rows, [$this, 'compare_relationship_rows']);

        return $rows;
    }

    private function make_fingerprint(
        array $legacy_slugs,
        array $legacy_relationships,
        array $canonical_slugs,
        array $objects,
        array $legacy_types,
        array $canonical_types
    ): string
    {
        $object_rows = array_map(static fn (array $object): array => [
            'object_id' => $object['object_id'],
            'object_type' => $object['object_type'],
        ], array_values($objects));
        usort($object_rows, static fn (array $a, array $b): int => $a['object_id'] <=> $b['object_id']);
        $data = [
            'legacy_term_slugs' => $legacy_slugs,
            'legacy_relationships' => $legacy_relationships,
            'canonical_term_slugs' => $canonical_slugs,
            'taxonomy_object_types' => [
                self::SOURCE_TAXONOMY => $legacy_types,
                self::TARGET_TAXONOMY => $canonical_types,
            ],
            'objects' => $object_rows,
        ];

        return hash('sha256', (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function verify_record_relationships(array $record, array $plan): true|\WP_Error
    {
        $recorded = $record['created_relationships'] ?? [];
        $expected = array_map(static fn (array $row): array => [
            'object_id' => (int) $row['object_id'],
            'term_slug' => $row['term_slug'],
        ], $recorded);
        $actual = array_map(static fn (array $row): array => [
            'object_id' => (int) $row['object_id'],
            'term_slug' => $row['term_slug'],
        ], $plan['canonical_relationships']);
        usort($expected, [$this, 'compare_relationship_rows']);
        usort($actual, [$this, 'compare_relationship_rows']);
        if ($expected !== $actual) {
            return new \WP_Error('vgpc_topic_migration_record_drift', 'Current canonical relationships differ from the migration record. Apply stopped without changes.');
        }

        return true;
    }

    private function remove_created_relationships(array $created): string
    {
        $failures = [];
        foreach (array_reverse($created) as $row) {
            $result = wp_remove_object_terms((int) $row['object_id'], (int) $row['term_id'], self::TARGET_TAXONOMY);
            if (is_wp_error($result) || $result === false) {
                $failures[] = sprintf('%s on object %d', $row['term_slug'], $row['object_id']);
            }
        }

        return $failures === []
            ? 'All canonical relationships created by this failed run were removed.'
            : 'Cleanup failed for: ' . implode(', ', $failures) . '.';
    }

    private function print_table(array $result): void
    {
        if (isset($result['objects'])) {
            foreach ($result['objects'] as $object) {
                \WP_CLI::line(sprintf(
                    '%d\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s',
                    $object['object_id'],
                    $object['object_type'],
                    $object['title'],
                    implode(', ', $object['legacy_terms']),
                    implode(', ', $object['canonical_topics']),
                    implode(', ', $object['skipped_commercial_terms']),
                    $object['action'],
                    $object['review_required'] ? 'yes' : 'no',
                    $object['reason']
                ));
            }
        }
        \WP_CLI::line('Fingerprint: ' . ($result['fingerprint'] ?? 'n/a'));
        \WP_CLI::line('Summary: ' . (string) json_encode($result['summary'] ?? $result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        if (($result['blockers'] ?? []) !== []) {
            \WP_CLI::line('Blockers: ' . implode(' ', $result['blockers']));
        }
    }

    private function compare_relationship_rows(array $left, array $right): int
    {
        $left_id = (int) ($left['object_id'] ?? $left[0] ?? 0);
        $right_id = (int) ($right['object_id'] ?? $right[0] ?? 0);
        $id_order = $left_id <=> $right_id;
        if ($id_order !== 0) {
            return $id_order;
        }

        return strcmp((string) ($left['term_slug'] ?? $left[2] ?? ''), (string) ($right['term_slug'] ?? $right[2] ?? ''));
    }

    private static function EXPECTED_OBJECT_TYPES(): array
    {
        return [514 => 'post', 516 => 'post', 518 => 'post', 519 => 'post', 521 => 'post', 523 => 'product', 641 => 'post'];
    }
}
