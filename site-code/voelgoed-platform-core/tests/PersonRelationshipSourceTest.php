<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Person\PersonRelationshipSourceReader;
use Voelgoed\PlatformCore\Person\PersonRelationshipValidator;

final class PersonRelationshipSourceTest
{
    public function test_reader_normalizes_supported_article_fields_in_field_order(): void
    {
        $this->register_fields();
        $GLOBALS['vgpc_test_posts'][10] = (object) ['ID' => 10, 'post_type' => 'post', 'post_status' => 'draft'];
        $GLOBALS['vgpc_test_meta'][10] = [
            'primary_author' => 20,
            'additional_authors' => [21, (object) ['ID' => 22]],
            'expert_contributors' => null,
        ];

        $projection = (new PersonRelationshipSourceReader())->read(10);
        vgpc_test_same([
            'source_post_id' => 10,
            'source_post_type' => 'post',
            'relationships' => [
                ['person_id' => 20, 'role_key' => 'primary_author', 'position' => 0],
                ['person_id' => 21, 'role_key' => 'additional_authors', 'position' => 0],
                ['person_id' => 22, 'role_key' => 'additional_authors', 'position' => 1],
            ],
        ], $projection, 'Reader must normalize role fields and preserve each field order.');
    }

    public function test_reader_rejects_unsupported_sources_and_malformed_field_values(): void
    {
        $this->register_fields();
        $GLOBALS['vgpc_test_posts'][11] = (object) ['ID' => 11, 'post_type' => 'product', 'post_status' => 'draft'];
        $GLOBALS['vgpc_test_posts'][12] = (object) ['ID' => 12, 'post_type' => 'post', 'post_status' => 'draft'];
        $GLOBALS['vgpc_test_meta'][12]['additional_authors'] = 'not-an-id';
        $reader = new PersonRelationshipSourceReader();
        vgpc_test_true(is_wp_error($reader->read(11)), 'Unsupported source type must fail.');
        vgpc_test_true(is_wp_error($reader->read(12)), 'Malformed ACF value must fail without dropping it.');
        vgpc_test_true(is_wp_error($reader->read(999)), 'Missing source must fail.');
    }

    public function test_reader_supports_course_contract_and_preserves_course_role_positions(): void
    {
        $this->register_fields();
        $GLOBALS['vgpc_test_posts'][13] = (object) ['ID' => 13, 'post_type' => 'mto-course', 'post_status' => 'private'];
        $GLOBALS['vgpc_test_meta'][13] = ['additional_instructors' => [31, 32], 'experts' => 33];
        $projection = (new PersonRelationshipSourceReader())->read(13);
        vgpc_test_same([
            'source_post_id' => 13,
            'source_post_type' => 'mto-course',
            'relationships' => [
                ['person_id' => 31, 'role_key' => 'additional_instructors', 'position' => 0],
                ['person_id' => 32, 'role_key' => 'additional_instructors', 'position' => 1],
                ['person_id' => 33, 'role_key' => 'experts', 'position' => 0],
            ],
        ], $projection, 'Course relationship fields must use only their approved role contract.');
    }

    public function test_validator_enforces_allowed_roles_unique_people_and_publication_rules(): void
    {
        $validator = new PersonRelationshipValidator();
        $GLOBALS['vgpc_test_posts'][20] = (object) ['ID' => 20, 'post_type' => 'person', 'post_status' => 'publish'];
        $GLOBALS['vgpc_test_posts'][21] = (object) ['ID' => 21, 'post_type' => 'person', 'post_status' => 'draft'];
        $GLOBALS['vgpc_test_posts'][22] = (object) ['ID' => 22, 'post_type' => 'post', 'post_status' => 'publish'];
        $base = ['source_post_id' => 10, 'source_post_type' => 'post', 'relationships' => [
            ['person_id' => 20, 'role_key' => 'primary_author', 'position' => 0],
        ]];
        vgpc_test_same(true, $validator->validate_projection($base, 'publish'), 'Published sources may use published Persons.');
        vgpc_test_same(true, $validator->validate_projection(['source_post_id'=>10,'source_post_type'=>'post','relationships'=>[]], 'draft'), 'Primary author remains optional.');
        vgpc_test_same(true, $validator->validate_projection($base, 'private'), 'Private sources may reference published Persons.');
        $duplicate = $base;
        $duplicate['relationships'][] = ['person_id' => 20, 'role_key' => 'reviewed_by', 'position' => 0];
        vgpc_test_true(is_wp_error($validator->validate_projection($duplicate, 'publish')), 'A Person cannot occupy two roles on one source.');
        $duplicate['relationships'] = [
            ['person_id' => 20, 'role_key' => 'primary_author', 'position' => 0],
            ['person_id' => 20, 'role_key' => 'primary_author', 'position' => 1],
        ];
        vgpc_test_true(is_wp_error($validator->validate_projection($duplicate, 'publish')), 'Duplicate Person in one role must fail.');
        $wrong_type = $base;
        $wrong_type['relationships'][0]['person_id'] = 22;
        vgpc_test_true(is_wp_error($validator->validate_projection($wrong_type, 'publish')), 'Relationships must target canonical Persons.');
        vgpc_test_same(true, $validator->validate_person(21, false), 'Draft Person is allowed for non-public sources.');
        vgpc_test_true(is_wp_error($validator->validate_person(21, true)), 'Published sources require published Persons.');
        $GLOBALS['vgpc_test_posts'][23] = (object) ['ID' => 23, 'post_type' => 'person', 'post_status' => 'trash'];
        vgpc_test_true(is_wp_error($validator->validate_person(23, false)), 'Trashed Persons must fail even for non-public sources.');
        vgpc_test_true(is_wp_error($validator->validate_person(404, false)), 'Missing Persons must fail.');
    }

    public function test_validator_rejects_unapproved_source_roles_and_invalid_row_shapes(): void
    {
        $validator = new PersonRelationshipValidator();
        $bad = ['source_post_id'=>1, 'source_post_type'=>'product', 'relationships'=>[]];
        vgpc_test_true(is_wp_error($validator->validate_projection($bad, 'draft')), 'Unsupported source must fail.');
        $bad = ['source_post_id'=>1, 'source_post_type'=>'post', 'relationships'=>[
            ['person_id'=>1, 'role_key'=>'lead_instructor', 'position'=>0],
        ]];
        vgpc_test_true(is_wp_error($validator->validate_projection($bad, 'draft')), 'Role from another source contract must fail.');
        $bad['relationships'] = [['person_id'=>'1', 'role_key'=>'primary_author', 'position'=>0]];
        vgpc_test_true(is_wp_error($validator->validate_projection($bad, 'draft')), 'Malformed row types must fail.');
    }

    private function register_fields(): void
    {
        acf_add_local_field_group(['key'=>'test_person_relationships', 'fields'=>array_map(static fn (string $field): array => ['key'=>'field_' . $field, 'name'=>$field], ['primary_author', 'additional_authors', 'expert_contributors', 'reviewed_by', 'lead_instructor', 'additional_instructors', 'experts'])]);
    }
}
