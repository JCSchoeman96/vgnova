<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Person\PersonRelationshipContract;

final class PersonRelationshipContractTest
{
    private const CONTRACTS = [
        'post' => ['primary_author', 'additional_authors', 'expert_contributors', 'reviewed_by'],
        'mto-course' => ['lead_instructor', 'additional_instructors', 'experts'],
    ];

    public function test_source_contracts_expose_only_the_approved_types_and_roles(): void
    {
        vgpc_test_same('person', PersonRelationshipContract::PERSON_POST_TYPE, 'Person relationships must target the canonical Person post type.');
        vgpc_test_same('post', PersonRelationshipContract::ARTICLE_POST_TYPE, 'Articles must use the WordPress post type.');
        vgpc_test_same('mto-course', PersonRelationshipContract::COURSE_POST_TYPE, 'Courses must use the Masteriyo post type.');
        vgpc_test_same('_vg_person_relationship_index_dirty', PersonRelationshipContract::DIRTY_META_KEY, 'The source dirty marker must use its canonical key.');

        foreach (self::CONTRACTS as $post_type => $roles) {
            vgpc_test_same(true, PersonRelationshipContract::supported_source_type($post_type), 'Approved source types must be supported.');
            vgpc_test_same($roles, PersonRelationshipContract::allowed_roles($post_type), 'The source role vocabulary must match the approved roles and order.');
            vgpc_test_same(['roles' => $roles], PersonRelationshipContract::source_contract($post_type), 'Each source contract must expose only its approved role list.');
        }

        vgpc_test_same(false, PersonRelationshipContract::supported_source_type('product'), 'Unapproved source types must not be supported.');
        vgpc_test_same([], PersonRelationshipContract::allowed_roles('product'), 'Unapproved source types must have no roles.');
        vgpc_test_same(null, PersonRelationshipContract::source_contract('product'), 'Unapproved source types must have no source contract.');
    }

    public function test_article_and_course_acf_groups_define_exact_optional_person_fields(): void
    {
        $cases = [
            ['group_vg_article_people.json', 'group_vg_article_people', 'post', [
                'field_vg_article_primary_author' => ['primary_author', 0],
                'field_vg_article_additional_authors' => ['additional_authors', 1],
                'field_vg_article_expert_contributors' => ['expert_contributors', 1],
                'field_vg_article_reviewed_by' => ['reviewed_by', 1],
            ]],
            ['group_vg_course_people.json', 'group_vg_course_people', 'mto-course', [
                'field_vg_course_lead_instructor' => ['lead_instructor', 0],
                'field_vg_course_additional_instructors' => ['additional_instructors', 1],
                'field_vg_course_experts' => ['experts', 1],
            ]],
        ];

        foreach ($cases as [$filename, $key, $post_type, $expected_fields]) {
            $group = $this->json($filename);
            vgpc_test_same($key, $group['key'] ?? null, 'The ACF group key must be canonical.');
            vgpc_test_same([[['param' => 'post_type', 'operator' => '==', 'value' => $post_type]]], $group['location'] ?? null, 'The ACF group must target only its approved source type.');
            vgpc_test_same(array_values(array_map(static fn (array $field): string => $field[0], $expected_fields)), array_column($group['fields'] ?? [], 'name'), 'The group must contain exactly the canonical fields in order.');

            foreach ($expected_fields as $field_key => [$name, $multiple]) {
                $field = $this->field_by_key($group, $field_key);
                vgpc_test_same($name, $field['name'] ?? null, 'Each ACF field must use its canonical name.');
                vgpc_test_same('post_object', $field['type'] ?? null, 'Person relationships must use ACF Post Object fields.');
                vgpc_test_same(['person'], $field['post_type'] ?? null, 'Each relationship must target only canonical Person records.');
                vgpc_test_same('id', $field['return_format'] ?? null, 'Relationship values must return Person IDs.');
                vgpc_test_same($multiple, $field['multiple'] ?? null, 'The field cardinality must match its approved role.');
                vgpc_test_same(0, $field['required'] ?? null, 'Relationship fields must remain optional.');
                vgpc_test_same(1, $field['allow_null'] ?? null, 'Relationship fields must allow no selection.');
                vgpc_test_same([], $field['bidirectional_target'] ?? null, 'Relationship fields must not create a second writable authority.');
            }
        }
    }

    /** @return array<string, mixed> */
    private function json(string $filename): array
    {
        $path = dirname(__DIR__, 2) . '/acf-json/' . $filename;
        vgpc_test_true(is_file($path), 'Missing required ACF Local JSON definition: ' . $filename);
        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $group
     *  @return array<string, mixed>
     */
    private function field_by_key(array $group, string $key): array
    {
        foreach ($group['fields'] ?? [] as $field) {
            if (($field['key'] ?? null) === $key) {
                return $field;
            }
        }
        throw new RuntimeException('Missing Person relationship field: ' . $key);
    }
}
