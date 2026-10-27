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
        update_post_meta(32, PersonRelationshipContract::DIRTY_META_KEY, 1);
        $GLOBALS['wpdb']->get_col_handler = static fn (string $query, array $args): array => [32, 31, 30, 30];
        vgpc_test_same([30], $this->service()->articles_for_person(20), 'Reverse results must be unique, published, clean, and ascending.');
        $this->post(21, 'person', 'draft');
        vgpc_test_same([], $this->service()->articles_for_person(21), 'Unpublished Persons must have no public reverse results.');
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
