<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Taxonomy\ContentTopicMigration;
use Voelgoed\PlatformCore\Taxonomy\ContentTopicRoles;
use Voelgoed\PlatformCore\Taxonomy\ContentTopicTaxonomy;

require_once __DIR__ . '/../includes/Taxonomy/ContentTopicMigration.php';

final class ContentTopicMigrationTest
{
    private const LEGACY_TERMS = [
        ['nuwe-jy', 'Nuwe Jy', null],
        ['geloof', 'Geloof', null],
        ['self', 'Self', null],
        ['werk-geld', 'Werk + Geld', null],
        ['ouerskap', 'Ouerskap', null],
        ['huwelik', 'Huwelik', null],
        ['mensstories', 'Mensstories', null],
        ['leefstyl', 'Leefstyl', null],
        ['aanlyn-onderwys', 'Aanlyn Onderwys', 'leefstyl'],
        ['doen-goed', 'Doen Goed', 'leefstyl'],
        ['finansies', 'Finansies', 'leefstyl'],
        ['natuurlik-gesond', 'Natuurlik Gesond', 'leefstyl'],
        ['vakansie', 'Vakansie', 'leefstyl'],
    ];

    private const EXPECTED_OBJECTS = [
        514 => ['post', 'Jou oggend-routine vir meer rustigheid', ['nuwe-jy']],
        516 => ['post', 'Geloof wat jou werkweek dra', ['geloof']],
        518 => ['post', 'Ouerskap sonder perfeksie', ['ouerskap']],
        519 => ['post', "Geldwenke vir enkelma's", ['werk-geld']],
        521 => ['post', "Herstel na verlies: 'n mensstory", ['mensstories']],
        523 => ['product', 'Voelgoed Welstand Pakket', ['natuurlik-gesond', 'nuwe-jy']],
        641 => ['post', 'QA Self Featured Content Test', ['self']],
    ];

    private function fixture(): ContentTopicMigration
    {
        vgpc_test_reset();

        $topic = new ContentTopicTaxonomy();
        $topic->register_taxonomy();
        foreach (ContentTopicTaxonomy::definitions() as $definition) {
            $parent = $definition['parent'] === null
                ? 0
                : (int) get_term_by('slug', $definition['parent'], 'content_topic')->term_id;
            vgpc_test_add_term(
                sanitize_term_field('name', $definition['name'], 0, 'content_topic', 'db'),
                $definition['slug'],
                $parent
            );
        }

        register_taxonomy('content_category', ['post', 'product', 'tc_events', 'mto-course', 'medewerker', 'uitsending', 'kompetisie', 'kompetisie_entry', 'testimonial', 'team_member'], [
            'hierarchical' => true,
        ]);

        $legacy_ids = [];
        foreach (self::LEGACY_TERMS as [$slug, $name, $parent_slug]) {
            $parent_id = $parent_slug === null ? 0 : $legacy_ids[$parent_slug];
            $legacy_ids[$slug] = vgpc_test_add_term($name, $slug, $parent_id, 'content_category')->term_id;
        }

        foreach (self::EXPECTED_OBJECTS as $object_id => [$type, $title, $slugs]) {
            $GLOBALS['vgpc_test_posts'][$object_id] = (object) [
                'ID' => $object_id,
                'post_type' => $type,
                'post_title' => $title,
            ];
            foreach ($slugs as $slug) {
                $GLOBALS['vgpc_test_relationships']['content_category'][$object_id][] = $legacy_ids[$slug];
            }
        }

        return new ContentTopicMigration();
    }

    private function direct_relationship_slugs(): array
    {
        $rows = [];
        foreach ($GLOBALS['vgpc_test_relationships']['content_topic'] ?? [] as $object_id => $term_ids) {
            foreach ($term_ids as $term_id) {
                $rows[] = [(int) $object_id, get_term((int) $term_id, 'content_topic')->slug];
            }
        }
        sort($rows);

        return $rows;
    }

    private function legacy_snapshot(): array
    {
        $rows = [];
        foreach ($GLOBALS['vgpc_test_relationships']['content_category'] ?? [] as $object_id => $term_ids) {
            foreach ($term_ids as $term_id) {
                $rows[] = [(int) $object_id, get_term((int) $term_id, 'content_category')->slug];
            }
        }
        sort($rows);

        $terms = array_map(
            static fn (object $term): array => [$term->term_id, $term->slug, $term->parent],
            get_terms(['taxonomy' => 'content_category', 'hide_empty' => false])
        );

        return [$terms, $rows];
    }

    private function role_service(): ContentTopicRoles
    {
        $roles = new ContentTopicRoles();
        $roles->register();
        do_action('acf/init');

        return $roles;
    }

    public function test_mapping_classifies_all_thirteen_legacy_terms(): void
    {
        $mapping = ContentTopicMigration::mapping();
        vgpc_test_same(13, count($mapping), 'Every legacy term must have an explicit classification.');
        foreach (['nuwe-jy', 'geloof', 'self', 'werk-geld', 'ouerskap', 'huwelik', 'mensstories'] as $slug) {
            vgpc_test_same(['action' => 'DIRECT_MAP', 'target' => $slug], $mapping[$slug], 'Each semantic pillar must map to its same canonical slug.');
        }
        foreach (['leefstyl', 'aanlyn-onderwys', 'doen-goed', 'finansies', 'natuurlik-gesond', 'vakansie'] as $slug) {
            vgpc_test_same(['action' => 'SKIP_COMMERCIAL_CONTEXT', 'target' => null], $mapping[$slug], 'Commercial terms must never become semantic topics.');
        }
    }

    public function test_dry_run_reports_seven_objects_and_eight_legacy_relationships_without_writes(): void
    {
        $migration = $this->fixture();
        $legacy_before = $this->legacy_snapshot();
        $dry_run = $migration->dry_run();

        vgpc_test_true(! is_wp_error($dry_run), 'The approved fixture must pass dry run.');
        vgpc_test_same(7, $dry_run['summary']['objects_inspected'], 'Dry run must inspect seven objects.');
        vgpc_test_same(8, $dry_run['summary']['legacy_relationships'], 'Dry run must account for eight legacy relationships.');
        vgpc_test_same(7, $dry_run['summary']['canonical_relationships_to_add'], 'Dry run must plan seven semantic relationships.');
        vgpc_test_same(1, $dry_run['summary']['commercial_relationships_skipped'], 'Dry run must skip the commercial relationship.');
        vgpc_test_same(0, $dry_run['summary']['review_required'], 'The approved fixture must need no review.');
        vgpc_test_same(0, $dry_run['summary']['legacy_relationships_to_delete'], 'Dry run must not plan legacy deletions.');
        vgpc_test_same(0, $dry_run['summary']['canonical_relationships_to_delete'], 'Dry run must not plan topic deletions.');
        vgpc_test_same(0, $GLOBALS['vgpc_test_set_term_calls'], 'Dry run must not write relationships.');
        vgpc_test_same([], $GLOBALS['vgpc_test_options'], 'Dry run must not create migration state.');
        vgpc_test_same($legacy_before, $this->legacy_snapshot(), 'Dry run must leave legacy terms and relationships unchanged.');

        $product = array_values(array_filter($dry_run['objects'], static fn (array $row): bool => $row['object_id'] === 523))[0];
        vgpc_test_same('Voelgoed Welstand Pakket', $product['title'], 'The dry run must include the verified product title.');
        vgpc_test_same(['nuwe-jy'], $product['canonical_topics'], 'Product 523 must map only its semantic term.');
        vgpc_test_same(['natuurlik-gesond'], $product['skipped_commercial_terms'], 'Product 523 must identify its skipped commercial term.');
    }

    public function test_unknown_assigned_legacy_term_blocks_the_plan(): void
    {
        $migration = $this->fixture();
        $unknown = vgpc_test_add_term('Unmapped', 'unmapped', 0, 'content_category');
        $GLOBALS['vgpc_test_relationships']['content_category'][514][] = $unknown->term_id;

        $result = $migration->dry_run();

        vgpc_test_true(! is_wp_error($result), 'Dry run must return its report when it finds an unclassified term.');
        vgpc_test_true(str_contains(implode(' ', $result['blockers']), 'unmapped'), 'The dry-run report must name the unknown slug.');
        vgpc_test_same(0, $GLOBALS['vgpc_test_set_term_calls'], 'Unknown terms must block before writes.');
        $apply = $migration->apply($result['fingerprint']);
        vgpc_test_true(is_wp_error($apply), 'An assigned unclassified term must block apply.');
    }

    public function test_commercial_only_objects_require_semantic_review(): void
    {
        $migration = $this->fixture();
        $commercial_term = get_term_by('slug', 'finansies', 'content_category');
        $GLOBALS['vgpc_test_posts'][700] = (object) ['ID' => 700, 'post_type' => 'post', 'post_title' => 'Commercial only'];
        $GLOBALS['vgpc_test_relationships']['content_category'][700] = [(int) $commercial_term->term_id];

        $dry_run = $migration->dry_run();
        $object = array_values(array_filter($dry_run['objects'], static fn (array $row): bool => $row['object_id'] === 700))[0];

        vgpc_test_same('REVIEW_REQUIRED', $object['action'], 'A commercial-only object must not receive a guessed topic.');
        vgpc_test_same(true, $object['review_required'], 'A commercial-only object must be marked for review.');
        vgpc_test_same([], $object['canonical_topics'], 'A commercial-only object must receive no canonical topic.');
        vgpc_test_true($dry_run['blockers'] !== [], 'A non-baseline commercial-only object must block apply.');
    }

    public function test_apply_requires_the_reviewed_fingerprint_and_creates_seven_relationships(): void
    {
        $migration = $this->fixture();
        $dry_run = $migration->dry_run();
        $result = $migration->apply($dry_run['fingerprint']);

        vgpc_test_true(! is_wp_error($result), 'Apply with the reviewed fingerprint must succeed.');
        vgpc_test_same(7, $result['created_relationships'], 'Apply must create seven relationships.');
        vgpc_test_same(7, count($this->direct_relationship_slugs()), 'The canonical taxonomy must have seven relationships.');
        vgpc_test_same(1, count($GLOBALS['vgpc_test_options']), 'Apply must record a single versioned migration record.');
    }

    public function test_apply_is_idempotent_after_a_successful_run(): void
    {
        $migration = $this->fixture();
        $fingerprint = $migration->dry_run()['fingerprint'];
        $migration->apply($fingerprint);

        $second = $migration->apply($fingerprint);

        vgpc_test_true(! is_wp_error($second), 'A repeated apply must be safe.');
        vgpc_test_same(7, $second['relationships_already_present'], 'A repeated apply must report seven existing relationships.');
        vgpc_test_same(0, $second['created_relationships'], 'A repeated apply must create no duplicates.');
        vgpc_test_same(7, count($this->direct_relationship_slugs()), 'A repeated apply must retain exactly seven canonical relationships.');
    }

    public function test_apply_rolls_back_relationships_after_a_partial_failure(): void
    {
        $migration = $this->fixture();
        $fingerprint = $migration->dry_run()['fingerprint'];
        $GLOBALS['vgpc_test_fail_set_term_call'] = 3;

        $result = $migration->apply($fingerprint);

        vgpc_test_true(is_wp_error($result), 'A failed relationship write must abort apply.');
        vgpc_test_same([], $this->direct_relationship_slugs(), 'Apply must remove relationships created before the failure.');
        vgpc_test_same([], $GLOBALS['vgpc_test_options'], 'A failed migration must not leave a completed run record.');
        vgpc_test_same(8, count($this->legacy_snapshot()[1]), 'A failed migration must preserve all legacy relationships.');
    }

    public function test_rollback_removes_only_recorded_relationships(): void
    {
        $migration = $this->fixture();
        $fingerprint = $migration->dry_run()['fingerprint'];
        $migration->apply($fingerprint);
        $manual_term = get_term_by('slug', 'gesondheid', 'content_topic');
        wp_set_object_terms(514, [(int) $manual_term->term_id], 'content_topic', true);

        $rollback = $migration->rollback();

        vgpc_test_true(! is_wp_error($rollback), 'Rollback must succeed when migration-owned rows remain intact.');
        vgpc_test_same([[514, 'gesondheid']], $this->direct_relationship_slugs(), 'Rollback must preserve the unrelated manual topic.');
        vgpc_test_same(8, count($this->legacy_snapshot()[1]), 'Rollback must preserve all legacy assignments.');
    }

    public function test_apply_aborts_when_source_fingerprint_has_drifted(): void
    {
        $migration = $this->fixture();
        $fingerprint = $migration->dry_run()['fingerprint'];
        $GLOBALS['vgpc_test_relationships']['content_category'][514] = [];

        $result = $migration->apply($fingerprint);

        vgpc_test_true(is_wp_error($result), 'Apply must reject a source fingerprint that changed after dry run.');
        vgpc_test_same([], $this->direct_relationship_slugs(), 'Source drift must stop before writing.');
    }

    public function test_registered_cli_command_defaults_to_dry_run(): void
    {
        $migration = $this->fixture();
        $migration->register();

        vgpc_test_true(isset(WP_CLI::$commands['vg-content-topic-migrate']), 'The migration command must be registered.');
        $command = WP_CLI::$commands['vg-content-topic-migrate'];
        $command([], []);

        vgpc_test_same(0, $GLOBALS['vgpc_test_set_term_calls'], 'The default command mode must not write relationships.');
        vgpc_test_same([], $GLOBALS['vgpc_test_options'], 'The default command mode must not save migration state.');
    }

    public function test_rollback_refuses_when_a_migration_owned_relationship_was_removed(): void
    {
        $migration = $this->fixture();
        $fingerprint = $migration->dry_run()['fingerprint'];
        $migration->apply($fingerprint);
        wp_remove_object_terms(514, get_term_by('slug', 'nuwe-jy', 'content_topic')->term_id, 'content_topic');

        $result = $migration->rollback();

        vgpc_test_true(is_wp_error($result), 'Rollback must stop when its recorded state has drifted.');
        vgpc_test_same(6, count($this->direct_relationship_slugs()), 'Unsafe rollback must leave all remaining relationships unchanged.');
    }

    public function test_task2_rollback_preserves_a_primary_relationship_created_by_task3(): void
    {
        $migration = $this->fixture();
        $fingerprint = $migration->dry_run()['fingerprint'];
        $migration->apply($fingerprint);
        $roles = $this->role_service();
        $manual_topic = get_term_by('slug', 'gesondheid', 'content_topic');
        $roles->set_primary_topic(514, (int) $manual_topic->term_id);

        $rollback = $migration->rollback();

        vgpc_test_true(! is_wp_error($rollback), 'Task 2 rollback must still remove only its recorded relationships.');
        vgpc_test_same([[514, 'gesondheid']], $this->direct_relationship_slugs(), 'The Task 3 relationship must remain after Task 2 rollback.');
        vgpc_test_same('gesondheid', $roles->get_primary_topic(514)->slug, 'The retained direct topic must remain Primary.');
        vgpc_test_same('REVIEWED', $roles->get_classification_state(514), 'The retained Primary classification must stay reviewed.');
        vgpc_test_same(false, get_option('vgpc_content_topic_migration_local_v1', false), 'Task 2 rollback must retain its existing record cleanup behavior.');
    }

    public function test_task2_rollback_clears_primary_when_it_removes_that_owned_topic(): void
    {
        $migration = $this->fixture();
        $fingerprint = $migration->dry_run()['fingerprint'];
        $migration->apply($fingerprint);
        $roles = $this->role_service();
        $migration_topic = get_term_by('slug', 'nuwe-jy', 'content_topic');
        $roles->set_primary_topic(514, (int) $migration_topic->term_id);

        $rollback = $migration->rollback();

        vgpc_test_true(! is_wp_error($rollback), 'Task 2 rollback must still succeed for intact migration rows.');
        vgpc_test_same(false, metadata_exists('post', 514, ContentTopicRoles::PRIMARY_META_KEY), 'No Primary reference may survive removal of its direct term.');
        vgpc_test_same([], $this->direct_relationship_slugs(), 'The controlled rollback must remove all seven Task 2-owned relationships.');
        vgpc_test_same('UNCLASSIFIED', $roles->get_classification_state(514), 'The seed must become unclassified after rollback removes its only topic.');
    }
}
