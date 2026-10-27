<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Person\PersonRelationshipContract;
use Voelgoed\PlatformCore\Person\PersonRelationshipReconciler;
use Voelgoed\PlatformCore\Person\PersonRelationshipRepository;
use Voelgoed\PlatformCore\Person\PersonRelationshipSchema;
use Voelgoed\PlatformCore\Person\PersonRelationshipService;
use Voelgoed\PlatformCore\Person\PersonRelationshipSourceReader;
use Voelgoed\PlatformCore\Person\PersonRelationshipValidator;

final class PersonRelationshipQueryTest
{
    public function test_forward_article_returns_validated_relationship_shape(): void
    {
        $this->register_fields();
        $this->post(10, 'post', 'publish');
        $this->post(20, 'person', 'publish');
        $GLOBALS['vgpc_test_meta'][10]['primary_author'] = 20;
        $GLOBALS['vgpc_test_meta'][10]['additional_authors'] = [20];
        $service = $this->service();
        vgpc_test_same(['primary_author' => null, 'additional_authors' => [], 'expert_contributors' => [], 'reviewed_by' => []], $service->people_for_article(10), 'Dirty/duplicate projections must fail closed.');
        unset($GLOBALS['vgpc_test_meta'][10]['_vg_person_relationship_index_dirty']);
        $GLOBALS['vgpc_test_meta'][10]['additional_authors'] = [21];
        $this->post(21, 'person', 'publish');
        vgpc_test_same(['primary_author' => 20, 'additional_authors' => [21], 'expert_contributors' => [], 'reviewed_by' => []], $service->people_for_article(10), 'Forward article reads must retain canonical IDs and field order.');
    }

    public function test_reverse_reads_filter_unpublished_people_dirty_and_nonpublic_sources(): void
    {
        $this->ready_schema();
        $this->post(20, 'person', 'publish');
        $this->post(30, 'post', 'publish');
        $this->post(31, 'post', 'draft');
        $this->post(32, 'post', 'publish');
        $this->register_fields();
        $GLOBALS['vgpc_test_meta'][30]['primary_author'] = 20;
        $GLOBALS['vgpc_test_meta'][31]['primary_author'] = 20;
        $GLOBALS['vgpc_test_meta'][32]['primary_author'] = 20;
        update_post_meta(32, PersonRelationshipContract::DIRTY_META_KEY, 1);
        $GLOBALS['wpdb']->get_col_handler = static fn (string $query, array $args): array => [32, 31, 30, 30];
        vgpc_test_same([30], $this->service()->articles_for_person(20), 'Reverse results must be unique, published, clean, and ascending.');
        $this->post(21, 'person', 'draft');
        vgpc_test_same([], $this->service()->articles_for_person(21), 'Unpublished Persons must have no public reverse results.');
    }

    public function test_reverse_read_fails_closed_when_dirty_marker_write_failed(): void
    {
        $this->ready_schema();
        $this->register_fields();
        $this->post(20, 'person', 'publish');
        $this->post(21, 'person', 'publish');
        $this->post(30, 'post', 'publish');
        $GLOBALS['vgpc_test_meta'][30]['primary_author'] = 21;
        $GLOBALS['vgpc_update_post_meta_handler'] = static fn (): bool => false;
        $GLOBALS['wpdb']->get_var_handler = static fn (string $query): string => str_contains($query, 'SHOW TABLES') ? 'wp_vg_person_relationship_index' : (str_contains($query, 'GET_LOCK') ? '0' : '1');
        $GLOBALS['wpdb']->get_col_handler = static fn (): array => [30];
        $indexer = new \Voelgoed\PlatformCore\Person\PersonRelationshipIndexer(
            new \Voelgoed\PlatformCore\Person\PersonRelationshipSourceLock(),
            new PersonRelationshipSourceReader(),
            new PersonRelationshipValidator(),
            new PersonRelationshipRepository(new PersonRelationshipSchema()),
        );
        vgpc_test_true(is_wp_error($indexer->rebuild_source(30)), 'Contended save should fail and attempt to persist dirty state.');
        vgpc_test_true(! $indexer->is_dirty(30), 'This case models failure to persist the marker itself.');
        vgpc_test_same([], $this->service()->articles_for_person(20), 'A stale index candidate must be hidden when the dirty marker could not persist.');
        vgpc_test_same([30], $this->service()->articles_for_person(21), 'A source-authoritative matching Person may still appear when the index candidate matches.');
        $GLOBALS['vgpc_update_post_meta_handler'] = null;
    }

    public function test_reconciler_reports_missing_index_rows_and_dirty_source(): void
    {
        $this->ready_schema();
        $this->register_fields();
        $this->post(10, 'post', 'draft');
        $this->post(20, 'person', 'draft');
        $GLOBALS['vgpc_test_meta'][10]['primary_author'] = 20;
        $GLOBALS['wpdb']->get_results_handler = static fn (): array => [];
        update_post_meta(10, PersonRelationshipContract::DIRTY_META_KEY, 1);
        $issues = (new PersonRelationshipReconciler(new PersonRelationshipSourceReader(), new PersonRelationshipRepository(new PersonRelationshipSchema()), new PersonRelationshipValidator()))->inspect_source(10);
        $codes = array_column($issues, 'code');
        vgpc_test_true(in_array('missing_index_row', $codes, true), 'Reconciler must identify missing derived rows.');
        vgpc_test_true(in_array('dirty_source', $codes, true), 'Reconciler must identify dirty sources.');
    }

    public function test_reconciler_reports_stale_rows_wrong_role_position_and_invalid_people(): void
    {
        $this->ready_schema();
        $this->register_fields();
        $this->post(11, 'post', 'draft');
        $this->post(21, 'person', 'draft');
        $GLOBALS['vgpc_test_meta'][11]['additional_authors'] = [21];
        $GLOBALS['wpdb']->get_results_handler = static fn (): array => [
            ['person_id' => 21, 'role_key' => 'expert_contributors', 'position' => 4],
            ['person_id' => 22, 'role_key' => 'reviewed_by', 'position' => 0],
            ['person_id' => 23, 'role_key' => 'unapproved_role', 'position' => 0],
        ];
        $issues = (new PersonRelationshipReconciler(new PersonRelationshipSourceReader(), new PersonRelationshipRepository(new PersonRelationshipSchema()), new PersonRelationshipValidator()))->inspect_source(11);
        $codes = array_column($issues, 'code');
        vgpc_test_true(in_array('wrong_role', $codes, true), 'Role mismatches must be reported.');
        vgpc_test_true(in_array('wrong_position', $codes, true), 'Position mismatches must be reported.');
        vgpc_test_true(in_array('stale_index_row', $codes, true), 'Extra index rows must be reported.');
        vgpc_test_true(in_array('unsupported_role', $codes, true), 'Unapproved stored roles must be reported.');
        vgpc_test_true(in_array('invalid_person_reference', $codes, true), 'Missing or invalid Person references must be reported.');
    }

    public function test_reconciler_preserves_duplicate_and_unsupported_rows(): void
    {
        $this->ready_schema();
        $this->register_fields();
        $this->post(12, 'post', 'draft');
        $this->post(22, 'person', 'draft');
        $GLOBALS['vgpc_test_meta'][12]['additional_authors'] = [22];
        $duplicate = ['person_id' => 22, 'role_key' => 'additional_authors', 'position' => 0];
        $GLOBALS['wpdb']->get_results_handler = static fn (): array => [
            $duplicate,
            ['person_id' => 22, 'role_key' => 'additional_authors', 'position' => 1],
            ['person_id' => 22, 'role_key' => 'unapproved_role', 'position' => 0],
            ['person_id' => 23, 'role_key' => 'reviewed_by', 'position' => 0],
        ];

        $issues = (new PersonRelationshipReconciler(new PersonRelationshipSourceReader(), new PersonRelationshipRepository(new PersonRelationshipSchema()), new PersonRelationshipValidator()))->inspect_source(12);
        $duplicate_rows = array_filter($issues, static fn (array $issue): bool => ($issue['details']['reason'] ?? null) === 'duplicate_row');
        $stale_people = array_column(array_filter($issues, static fn (array $issue): bool => $issue['code'] === 'stale_index_row'), 'details');
        vgpc_test_same(1, count($duplicate_rows), 'Repeated database rows must be reported individually as duplicate stale rows.');
        vgpc_test_true(in_array('unsupported_role', array_column($issues, 'code'), true), 'Unsupported rows must remain visible when another row has the same Person ID.');
        vgpc_test_true(in_array(23, array_column($stale_people, 'person_id'), true), 'Additional rows must not be lost when several rows share one Person ID.');
    }

    public function test_reconciler_returns_read_error_instead_of_reporting_missing_rows(): void
    {
        $this->ready_schema();
        $this->register_fields();
        $this->post(13, 'post', 'draft');
        $this->post(23, 'person', 'draft');
        $GLOBALS['vgpc_test_meta'][13]['primary_author'] = 23;
        $GLOBALS['wpdb']->get_results_handler = static function (): never { throw new RuntimeException('database unavailable'); };

        $issues = (new PersonRelationshipReconciler(new PersonRelationshipSourceReader(), new PersonRelationshipRepository(new PersonRelationshipSchema()), new PersonRelationshipValidator()))->inspect_source(13);
        vgpc_test_true(is_wp_error($issues), 'A database read failure must be returned to the caller.');
        vgpc_test_same('index_read_failed', $issues->get_error_code(), 'A failed read must identify the repository read failure.');
        vgpc_test_same([], (new PersonRelationshipRepository(new PersonRelationshipSchema()))->relationships_for_source(13), 'The existing repository method must retain its fail-closed array interface.');
    }

    public function test_global_reconciliation_reports_orphan_unsupported_and_wrong_type_sources(): void
    {
        $this->ready_schema();
        $GLOBALS['wpdb']->get_results_handler = static fn (string $query): array => str_contains($query, 'LEFT JOIN wp_posts') ? [
            ['source_post_id' => 501, 'source_post_type' => 'post', 'current_post_type' => null],
            ['source_post_id' => 502, 'source_post_type' => 'post', 'current_post_type' => 'product'],
            ['source_post_id' => 503, 'source_post_type' => 'post', 'current_post_type' => 'mto-course'],
        ] : [];
        $issues = (new PersonRelationshipReconciler(new PersonRelationshipSourceReader(), new PersonRelationshipRepository(new PersonRelationshipSchema()), new PersonRelationshipValidator()))->inspect_index();
        vgpc_test_same(['orphan_index_source', 'unsupported_index_source', 'wrong_source_post_type'], array_column($issues, 'code'), 'Global inspection must surface deleted, unsupported and retyped sources.');
        vgpc_test_same([501, 502, 503], array_column($issues, 'source_post_id'), 'Global anomalies must retain the affected source IDs.');
    }

    public function test_reconciler_returns_malformed_reader_errors_without_mislabeling_them_as_person_issues(): void
    {
        $this->register_fields();
        $this->post(14, 'post', 'draft');
        $GLOBALS['vgpc_test_meta'][14]['primary_author'] = [24];

        $issues = (new PersonRelationshipReconciler(new PersonRelationshipSourceReader(), new PersonRelationshipRepository(new PersonRelationshipSchema()), new PersonRelationshipValidator()))->inspect_source(14);
        vgpc_test_true(is_wp_error($issues), 'Reader failures must be returned to the caller.');
        vgpc_test_same('malformed_relationship', $issues->get_error_code(), 'Reader failures must retain their own code.');
    }

    private function service(): PersonRelationshipService
    {
        return new PersonRelationshipService(new PersonRelationshipSourceReader(), new PersonRelationshipValidator(), new PersonRelationshipRepository(new PersonRelationshipSchema()));
    }

    private function register_fields(): void
    {
        $fields = [];
        foreach (array_merge(PersonRelationshipContract::allowed_roles('post'), PersonRelationshipContract::allowed_roles('mto-course')) as $role) $fields[] = ['key' => 'field_' . $role, 'name' => $role];
        acf_add_local_field_group(['key' => 'group_person_test', 'fields' => $fields]);
    }

    private function ready_schema(): void
    {
        $GLOBALS['vgpc_test_options'][PersonRelationshipSchema::VERSION_OPTION] = PersonRelationshipSchema::SCHEMA_VERSION;
        $GLOBALS['wpdb']->get_var_handler = static fn (string $query): string => str_contains($query, 'SHOW TABLES') ? 'wp_vg_person_relationship_index' : '1';
    }

    private function post(int $id, string $type, string $status): void
    {
        $GLOBALS['vgpc_test_posts'][$id] = (object) ['ID' => $id, 'post_type' => $type, 'post_status' => $status];
    }
}
