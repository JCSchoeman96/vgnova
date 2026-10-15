<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Taxonomy\ContentTopicRoles;
use Voelgoed\PlatformCore\Taxonomy\ContentTopicTaxonomy;

final class ContentTopicRolesTest
{
    private array $term_ids = [];

    private function fixture(): ContentTopicRoles
    {
        vgpc_test_reset();

        $taxonomy = new ContentTopicTaxonomy();
        $taxonomy->register_taxonomy();
        foreach (ContentTopicTaxonomy::definitions() as $definition) {
            $parent_id = $definition['parent'] === null ? 0 : $this->term_ids[$definition['parent']];
            $term = vgpc_test_add_term(
                sanitize_term_field('name', $definition['name'], 0, 'content_topic', 'db'),
                $definition['slug'],
                $parent_id
            );
            $this->term_ids[$definition['slug']] = (int) $term->term_id;
        }

        register_taxonomy('content_category', ['post', 'product'], ['hierarchical' => true]);
        $legacy_pillar_term = vgpc_test_add_term('Nuwe Jy', 'nuwe-jy', 0, 'content_category');
        $commercial_term = vgpc_test_add_term('Natuurlik Gesond', 'natuurlik-gesond', 0, 'content_category');

        $post_types = [
            514 => 'post',
            516 => 'post',
            518 => 'post',
            519 => 'post',
            521 => 'post',
            523 => 'product',
            641 => 'post',
            700 => 'mto-course',
            701 => 'tc_events',
            702 => 'uitsending',
            703 => 'kompetisie',
            704 => 'medewerker',
            705 => 'post',
        ];
        foreach ($post_types as $object_id => $post_type) {
            $GLOBALS['vgpc_test_posts'][$object_id] = (object) [
                'ID' => $object_id,
                'post_type' => $post_type,
                'post_title' => 'Fixture ' . $object_id,
            ];
        }

        $seed_slugs = [
            514 => 'nuwe-jy',
            516 => 'geloof',
            518 => 'ouerskap',
            519 => 'werk-geld',
            521 => 'mensstories',
            523 => 'nuwe-jy',
            641 => 'self',
        ];
        $created_relationships = [];
        foreach ($seed_slugs as $object_id => $slug) {
            $term_id = $this->term_ids[$slug];
            $GLOBALS['vgpc_test_relationships']['content_topic'][$object_id] = [$term_id];
            $created_relationships[] = [
                'object_id' => $object_id,
                'term_slug' => $slug,
                'term_id' => $term_id,
            ];
        }
        $GLOBALS['vgpc_test_relationships']['content_topic'][700] = [$this->term_ids['gesondheid']];
        $GLOBALS['vgpc_test_relationships']['content_category'][523] = [
            (int) $legacy_pillar_term->term_id,
            (int) $commercial_term->term_id,
        ];

        update_option('vgpc_content_topic_migration_local_v1', [
            'migration' => 'content-topic-local-v1',
            'source_fingerprint' => 'fixture-fingerprint',
            'created_relationships' => $created_relationships,
        ], false);

        $roles = new ContentTopicRoles();
        $roles->register();
        do_action('acf/init');

        return $roles;
    }

    private function term_id(string $slug): int
    {
        return (int) $this->term_ids[$slug];
    }

    private function topic_slugs(ContentTopicRoles $roles, int $object_id, string $method = 'get_direct_topics'): array
    {
        $terms = $roles->{$method}($object_id);
        vgpc_test_true(! is_wp_error($terms), $method . ' must return topic terms.');

        return array_map(static fn (object $term): string => $term->slug, $terms);
    }

    private function legacy_category_slugs(int $object_id): array
    {
        $terms = wp_get_object_terms($object_id, 'content_category', ['fields' => 'slugs']);
        vgpc_test_true(! is_wp_error($terms), 'Legacy category relationships must be readable.');

        return $terms;
    }

    private function acf_save(ContentTopicRoles $roles, int $object_id, int|string|null $primary_term_id): void
    {
        $_POST['post_ID'] = (string) $object_id;
        $_POST['acf'] = [ContentTopicRoles::PRIMARY_FIELD_KEY => $primary_term_id ?? ''];
        update_field(ContentTopicRoles::PRIMARY_FIELD_KEY, $primary_term_id ?? '', $object_id);
        do_action('acf/save_post', $object_id);
    }

    public function test_registers_primary_field_for_supported_entities_and_excludes_people(): void
    {
        $roles = $this->fixture();
        $group = $GLOBALS['vgpc_test_field_groups']['group_vg_content_topic_roles'] ?? null;

        vgpc_test_true(is_array($group), 'The Platform Core ACF field group must register.');
        vgpc_test_same(1, count($group['fields'] ?? []), 'The editor must see one Primary selector and no separate Secondary field.');
        vgpc_test_same(1, count($GLOBALS['vgpc_test_actions']['admin_notices'] ?? []), 'The editor classification notice must register.');

        $locations = array_map(
            static fn (array $location): string => (string) ($location[0]['value'] ?? ''),
            $group['location'] ?? []
        );
        sort($locations);
        $expected = ['kompetisie', 'mto-course', 'post', 'product', 'tc_events', 'uitsending'];
        sort($expected);
        vgpc_test_same($expected, $locations, 'Primary selector must appear only on the six supported entity types.');
        vgpc_test_true(! in_array('medewerker', $locations, true), 'People must not receive a Primary selector.');
        vgpc_test_same('MIGRATION_SEED', $roles->get_classification_state(514), 'The Task 2 assignment must remain an unreviewed seed.');
    }

    public function test_primary_field_uses_only_content_topic_and_never_syncs_taxonomy_terms(): void
    {
        $this->fixture();
        $group = $GLOBALS['vgpc_test_field_groups']['group_vg_content_topic_roles'];
        $field = $group['fields'][0];

        vgpc_test_same(ContentTopicRoles::PRIMARY_META_KEY, $field['name'], 'The field must use the stable Primary metadata key.');
        vgpc_test_same('Content Topic: Primary Topic', $field['label'], 'The ACF label must include its content-topic context.');
        vgpc_test_same('taxonomy', $field['type'], 'The Primary selector must use ACF taxonomy choices.');
        vgpc_test_same('content_topic', $field['taxonomy'], 'The Primary selector must contain only canonical topics.');
        vgpc_test_same('select', $field['field_type'], 'The Primary selector must be single-choice.');
        vgpc_test_same(false, (bool) $field['save_terms'], 'ACF must not write taxonomy relationships from the Primary field.');
        vgpc_test_same(false, (bool) $field['load_terms'], 'ACF must not load the direct-topic list into the Primary field.');
        vgpc_test_same('id', $field['return_format'], 'The Primary field must return a term ID.');
        vgpc_test_same(false, (bool) $field['multiple'], 'The Primary field must accept only one term.');
        vgpc_test_true(str_contains($field['instructions'], 'most specific useful'), 'The field must tell editors to choose the most specific useful topic.');
        vgpc_test_true(str_contains($field['instructions'], 'Secondary Topics'), 'The field must explain that other direct topics are Secondary Topics.');
    }

    public function test_primary_is_stored_as_a_term_id_and_must_be_a_direct_content_topic(): void
    {
        $roles = $this->fixture();
        $term_id = $this->term_id('nuwe-jy');

        $result = $roles->set_primary_topic(514, $term_id);

        vgpc_test_same(true, $result, 'A directly assigned canonical topic must be accepted as Primary.');
        vgpc_test_same($term_id, (int) get_field(ContentTopicRoles::PRIMARY_FIELD_KEY, 514, false), 'Primary metadata must store the canonical term ID.');
        vgpc_test_same('nuwe-jy', $roles->get_primary_topic(514)->slug, 'The Primary getter must return the assigned content_topic term.');
        vgpc_test_same(true, $roles->validate_primary_topic(514), 'A direct content_topic term must validate.');
    }

    public function test_setting_primary_adds_only_the_selected_direct_topic_and_preserves_existing_topics(): void
    {
        $roles = $this->fixture();
        $before = $GLOBALS['vgpc_test_set_term_calls'];

        $result = $roles->set_primary_topic(700, $this->term_id('velsorg'));

        vgpc_test_same(true, $result, 'A valid Primary topic may be assigned during the same save.');
        vgpc_test_same(['gesondheid', 'velsorg'], $this->topic_slugs($roles, 700), 'Setting Primary must append the selected term and retain other direct topics.');
        vgpc_test_same($before + 1, $GLOBALS['vgpc_test_set_term_calls'], 'Setting an unassigned Primary must use the WordPress taxonomy API once.');
        vgpc_test_same(['gesondheid', 'velsorg', 'nuwe-jy', 'skoonheid-versorging'], $this->topic_slugs($roles, 700, 'get_effective_topics'), 'Effective Topics may derive ancestors but must not add them as direct relationships.');
        vgpc_test_same(['gesondheid', 'velsorg'], $this->topic_slugs($roles, 700), 'Ancestor derivation must leave direct relationships unchanged.');
    }

    public function test_set_primary_rejects_missing_terms_and_terms_from_other_taxonomies(): void
    {
        $roles = $this->fixture();
        $commercial = vgpc_test_add_term('Natuurlik Gesond', 'natuurlik-gesond-extra', 0, 'content_category');
        $before = $this->topic_slugs($roles, 700);

        $missing = $roles->set_primary_topic(700, 999999);
        $other_taxonomy = $roles->set_primary_topic(700, (int) $commercial->term_id);

        vgpc_test_true(is_wp_error($missing), 'A missing term ID must be rejected.');
        vgpc_test_true(is_wp_error($other_taxonomy), 'A term from content_category must be rejected.');
        vgpc_test_same($before, $this->topic_slugs($roles, 700), 'Rejected Primary values must not change direct topics.');
        vgpc_test_same(false, metadata_exists('post', 700, ContentTopicRoles::PRIMARY_META_KEY), 'Rejected Primary values must not be persisted.');
    }

    public function test_acf_validation_checks_taxonomy_and_actual_submitted_primary_value(): void
    {
        $roles = $this->fixture();
        $field = $GLOBALS['vgpc_test_field_groups']['group_vg_content_topic_roles']['fields'][0];
        $commercial = vgpc_test_add_term('Commercial term', 'commercial-term', 0, 'content_category');
        $_POST['post_ID'] = '705';

        $validation_hook = 'acf/validate_value/key=' . ContentTopicRoles::PRIMARY_FIELD_KEY;
        $input = 'acf[' . ContentTopicRoles::PRIMARY_FIELD_KEY . ']';
        $unassigned_topic = apply_filters($validation_hook, true, $this->term_id('aftrede'), $field, $input);
        $other_taxonomy = apply_filters($validation_hook, true, (int) $commercial->term_id, $field, $input);
        $empty_value = apply_filters($validation_hook, true, '', $field, $input);

        $_POST['post_ID'] = '704';
        $unsupported_entity = apply_filters($validation_hook, true, $this->term_id('geloof'), $field, $input);

        vgpc_test_same(true, $unassigned_topic, 'A submitted content_topic may become direct membership during the same save.');
        vgpc_test_true(is_string($other_taxonomy), 'ACF validation must reject a term from another taxonomy.');
        vgpc_test_same(true, $empty_value, 'ACF validation must allow an editor to clear Primary.');
        vgpc_test_true(is_string($unsupported_entity), 'The registered ACF validation filter must reject Primary for unsupported entities.');
    }

    public function test_acf_update_filter_rejects_primary_for_unsupported_entities_without_form_context(): void
    {
        $roles = $this->fixture();
        $field = $GLOBALS['vgpc_test_field_groups']['group_vg_content_topic_roles']['fields'][0];

        $filtered = $roles->filter_primary_field_update($this->term_id('geloof'), 704, $field);
        update_field(ContentTopicRoles::PRIMARY_FIELD_KEY, $this->term_id('geloof'), 704);

        vgpc_test_same(null, $filtered, 'Programmatic ACF updates must not persist Primary metadata on a medewerker.');
        vgpc_test_same(false, metadata_exists('post', 704, ContentTopicRoles::PRIMARY_META_KEY), 'A rejected ACF update must leave people without a stored Primary role.');
    }

    public function test_acf_update_filter_validates_the_target_object_instead_of_a_stale_form_id(): void
    {
        $roles = $this->fixture();
        $direct_topic_id = $this->term_id('gesondheid');
        $_POST['post_ID'] = '704';

        update_field(ContentTopicRoles::PRIMARY_FIELD_KEY, $direct_topic_id, 700);

        vgpc_test_same($direct_topic_id, (int) get_field(ContentTopicRoles::PRIMARY_FIELD_KEY, 700, false), 'A valid update must use its target post ID even when the request has a stale post_ID.');
    }

    public function test_programmatic_acf_update_cannot_store_an_unassigned_primary(): void
    {
        $roles = $this->fixture();
        $existing_primary_id = $this->term_id('gesondheid');
        $roles->set_primary_topic(700, $existing_primary_id);

        update_field(ContentTopicRoles::PRIMARY_FIELD_KEY, $this->term_id('geloof'), 700);

        vgpc_test_same($existing_primary_id, (int) get_field(ContentTopicRoles::PRIMARY_FIELD_KEY, 700, false), 'An unassigned Primary update must preserve the existing direct Primary.');
        vgpc_test_same(['gesondheid'], $this->topic_slugs($roles, 700), 'A programmatic Primary update must not persist a role without direct membership.');
        vgpc_test_same('REVIEWED', $roles->get_classification_state(700), 'A rejected programmatic update must leave the valid reviewed state intact.');
    }

    public function test_secondary_topics_are_derived_without_a_second_stored_list(): void
    {
        $roles = $this->fixture();
        wp_set_object_terms(700, [$this->term_id('geloof')], 'content_topic', true);
        $roles->set_primary_topic(700, $this->term_id('gesondheid'));

        vgpc_test_same(['geloof'], $this->topic_slugs($roles, 700, 'get_secondary_topics'), 'Secondary Topics must equal direct topics minus Primary.');
        vgpc_test_same(false, metadata_exists('post', 700, '_vg_secondary_topics'), 'No Secondary topic list may be stored.');
        vgpc_test_same(false, metadata_exists('post', 700, 'secondary_topics'), 'No alternative Secondary topic list may be stored.');
    }

    public function test_effective_topics_adds_ancestors_once_without_changing_direct_relationships(): void
    {
        $roles = $this->fixture();
        wp_set_object_terms(705, [$this->term_id('aftrede')], 'content_topic', false);
        $set_calls = $GLOBALS['vgpc_test_set_term_calls'];

        vgpc_test_same(['aftrede'], $this->topic_slugs($roles, 705), 'Direct Topics must contain the assigned leaf only.');
        vgpc_test_same(['aftrede', 'geld', 'werk-geld'], $this->topic_slugs($roles, 705, 'get_effective_topics'), 'Effective Topics must include each ancestor exactly once.');
        vgpc_test_same(['aftrede'], $this->topic_slugs($roles, 705), 'Effective Topics must not add ancestors to WordPress relationships.');
        vgpc_test_same($set_calls, $GLOBALS['vgpc_test_set_term_calls'], 'Effective topic reads must not write taxonomy relationships.');
    }

    public function test_classification_states_follow_primary_topics_and_task2_provenance(): void
    {
        $roles = $this->fixture();

        vgpc_test_same('MIGRATION_SEED', $roles->get_classification_state(514), 'A Task 2 relationship without Primary must remain a migration seed.');
        vgpc_test_same('NEEDS_REVIEW', $roles->get_classification_state(700), 'A non-seed direct topic without Primary must need review.');
        vgpc_test_same('UNCLASSIFIED', $roles->get_classification_state(705), 'An object with no direct Topics or Primary must be unclassified.');

        $roles->set_primary_topic(700, $this->term_id('gesondheid'));

        vgpc_test_same('REVIEWED', $roles->get_classification_state(700), 'A valid direct Primary must mark the classification reviewed.');
        vgpc_test_same('NOT_APPLICABLE', $roles->get_classification_state(704), 'People must not be sent through the Primary review workflow.');
    }

    public function test_unsupported_entity_with_a_persisted_primary_is_invalid(): void
    {
        $roles = $this->fixture();
        $term_id = $this->term_id('geloof');
        wp_set_object_terms(704, [$term_id], 'content_topic');
        update_post_meta(704, ContentTopicRoles::PRIMARY_META_KEY, $term_id);

        vgpc_test_same('INVALID', $roles->get_classification_state(704), 'A persisted Primary on a person must be surfaced as invalid.');
        vgpc_test_true(is_wp_error($roles->get_primary_topic(704)), 'A person must not expose a valid Primary role.');
    }

    public function test_invalid_primary_references_fail_closed(): void
    {
        $roles = $this->fixture();
        update_post_meta(700, ContentTopicRoles::PRIMARY_META_KEY, 999999);

        vgpc_test_same('INVALID', $roles->get_classification_state(700), 'A deleted Primary term reference must be invalid.');
        vgpc_test_true(is_wp_error($roles->get_primary_topic(700)), 'The Primary getter must not return a stale term.');
        vgpc_test_true(is_wp_error($roles->validate_primary_topic(700)), 'Primary validation must report a stale term.');

        $commercial = vgpc_test_add_term('Legacy commercial', 'legacy-commercial', 0, 'content_category');
        update_post_meta(700, ContentTopicRoles::PRIMARY_META_KEY, (int) $commercial->term_id);
        vgpc_test_same('INVALID', $roles->get_classification_state(700), 'A Primary reference from another taxonomy must be invalid.');

        update_post_meta(700, ContentTopicRoles::PRIMARY_META_KEY, $this->term_id('geloof'));
        vgpc_test_same('INVALID', $roles->get_classification_state(700), 'A Primary term that is not directly assigned must be invalid.');
    }

    public function test_removing_a_primary_relationship_clears_the_role_without_promoting_a_secondary(): void
    {
        $roles = $this->fixture();
        $primary_id = $this->term_id('nuwe-jy');
        $roles->set_primary_topic(514, $primary_id);
        $record_before = get_option('vgpc_content_topic_migration_local_v1');

        wp_remove_object_terms(514, $primary_id, 'content_topic');

        vgpc_test_same(false, metadata_exists('post', 514, ContentTopicRoles::PRIMARY_META_KEY), 'Removing the Primary relationship must clear its role.');
        vgpc_test_same([], $this->topic_slugs($roles, 514), 'Removing the Primary must not restore or replace its relationship.');
        vgpc_test_same('UNCLASSIFIED', $roles->get_classification_state(514), 'Removing the only migration seed topic must leave the object unclassified.');
        vgpc_test_same($record_before, get_option('vgpc_content_topic_migration_local_v1'), 'Relationship cleanup must leave Task 2 provenance untouched.');
    }

    public function test_removing_primary_with_other_direct_topics_leaves_needs_review_without_promotion(): void
    {
        $roles = $this->fixture();
        $remaining_secondary = $this->term_id('gesondheid');
        $removed_primary = $this->term_id('nuwe-jy');
        wp_set_object_terms(700, [$remaining_secondary, $removed_primary], 'content_topic', false);
        $roles->set_primary_topic(700, $removed_primary);

        wp_remove_object_terms(700, $removed_primary, 'content_topic');

        vgpc_test_same(false, metadata_exists('post', 700, ContentTopicRoles::PRIMARY_META_KEY), 'Removing Primary membership must clear the role reference.');
        vgpc_test_same(['gesondheid'], $this->topic_slugs($roles, 700), 'The other direct topic must remain assigned.');
        vgpc_test_same('NEEDS_REVIEW', $roles->get_classification_state(700), 'The object must need review rather than auto-promoting a former Secondary.');
        vgpc_test_same('gesondheid', $this->topic_slugs($roles, 700, 'get_secondary_topics')[0], 'The remaining topic must still be directly discoverable.');
    }

    public function test_acf_save_sets_topic_and_primary_in_one_save(): void
    {
        $roles = $this->fixture();
        $term_id = $this->term_id('aftrede');
        $this->acf_save($roles, 705, $term_id);

        vgpc_test_same('aftrede', $roles->get_primary_topic(705)->slug, 'The Primary value must be valid after one editor save.');
        vgpc_test_same(['aftrede'], $this->topic_slugs($roles, 705), 'The same save must retain direct topic membership.');
        vgpc_test_same('REVIEWED', $roles->get_classification_state(705), 'The same save must not require a second update.');
    }

    public function test_acf_save_clears_primary_when_direct_topic_assignment_fails(): void
    {
        $roles = $this->fixture();
        $term_id = $this->term_id('aftrede');
        $GLOBALS['vgpc_test_fail_set_term_call'] = $GLOBALS['vgpc_test_set_term_calls'] + 1;

        $_POST['post_ID'] = '705';
        $_POST['acf'] = [ContentTopicRoles::PRIMARY_FIELD_KEY => $term_id];
        update_post_meta(705, ContentTopicRoles::PRIMARY_META_KEY, $term_id);
        do_action('acf/save_post', 705);

        vgpc_test_same(false, metadata_exists('post', 705, ContentTopicRoles::PRIMARY_META_KEY), 'A failed direct-topic write must not leave an invalid Primary reference.');
        vgpc_test_same([], $this->topic_slugs($roles, 705), 'A failed direct-topic write must not create a taxonomy relationship.');
        vgpc_test_same('UNCLASSIFIED', $roles->get_classification_state(705), 'A failed save must leave the object unclassified.');
    }

    public function test_acf_save_preserves_existing_primary_when_submitted_term_is_invalid(): void
    {
        $roles = $this->fixture();
        $existing_primary_id = $this->term_id('gesondheid');
        $roles->set_primary_topic(700, $existing_primary_id);

        $this->acf_save($roles, 700, 999999);

        vgpc_test_same($existing_primary_id, (int) get_field(ContentTopicRoles::PRIMARY_FIELD_KEY, 700, false), 'An invalid submitted term must not erase the previously valid Primary role.');
        vgpc_test_same('REVIEWED', $roles->get_classification_state(700), 'Rejecting an invalid replacement must preserve the reviewed state.');
    }

    public function test_acf_save_changes_or_clears_primary_without_removing_direct_topics(): void
    {
        $roles = $this->fixture();
        $roles->set_primary_topic(700, $this->term_id('gesondheid'));
        $this->acf_save($roles, 700, $this->term_id('geloof'));

        vgpc_test_same('geloof', $roles->get_primary_topic(700)->slug, 'The editor may change Primary in one save.');
        vgpc_test_same(['gesondheid', 'geloof'], $this->topic_slugs($roles, 700), 'Changing Primary must preserve all direct topics.');
        vgpc_test_same(['gesondheid'], $this->topic_slugs($roles, 700, 'get_secondary_topics'), 'The old direct topic must become derived Secondary data.');

        $direct_before_clear = $this->topic_slugs($roles, 700);
        $this->acf_save($roles, 700, null);

        vgpc_test_same(false, metadata_exists('post', 700, ContentTopicRoles::PRIMARY_META_KEY), 'Clearing Primary must remove the role metadata.');
        vgpc_test_same($direct_before_clear, $this->topic_slugs($roles, 700), 'Clearing Primary must not remove direct topics.');
        vgpc_test_same('NEEDS_REVIEW', $roles->get_classification_state(700), 'Direct topics without Primary must need review.');
    }

    public function test_same_save_removal_of_primary_does_not_restore_stale_acf_value(): void
    {
        $roles = $this->fixture();
        $primary_id = $this->term_id('nuwe-jy');
        $roles->set_primary_topic(514, $primary_id);
        $_POST['post_ID'] = '514';
        $_POST['acf'] = [ContentTopicRoles::PRIMARY_FIELD_KEY => $primary_id];

        wp_set_object_terms(514, [], 'content_topic', false);
        update_field(ContentTopicRoles::PRIMARY_FIELD_KEY, $primary_id, 514);
        do_action('acf/save_post', 514);

        vgpc_test_same([], $this->topic_slugs($roles, 514), 'The removed Primary relationship must remain removed after ACF saves.');
        vgpc_test_same(false, metadata_exists('post', 514, ContentTopicRoles::PRIMARY_META_KEY), 'The stale ACF form value must not restore a Primary role.');
        vgpc_test_same('UNCLASSIFIED', $roles->get_classification_state(514), 'Removing all topics must leave the object unclassified.');
    }

    public function test_deleting_a_primary_term_clears_its_role_reference(): void
    {
        $roles = $this->fixture();
        $term_id = $this->term_id('gesondheid');
        $roles->set_primary_topic(700, $term_id);

        vgpc_test_true(wp_delete_term($term_id, 'content_topic'), 'The controlled test term must be deleted.');

        vgpc_test_same(false, metadata_exists('post', 700, ContentTopicRoles::PRIMARY_META_KEY), 'Deleting a Primary term must clear its stored reference.');
        vgpc_test_same('UNCLASSIFIED', $roles->get_classification_state(700), 'A deleted direct topic must not leave a stale reviewed state.');
    }

    public function test_redundant_parent_child_assignments_warn_without_changing_editor_data(): void
    {
        $roles = $this->fixture();
        wp_set_object_terms(700, [$this->term_id('nuwe-jy')], 'content_topic', true);
        $direct_before = $this->topic_slugs($roles, 700);

        $warnings = $roles->get_hierarchy_warnings(700);

        vgpc_test_same(1, count($warnings), 'A directly assigned parent and child must produce one warning.');
        vgpc_test_same('redundant_parent_child', $warnings[0]['code'], 'The finding must identify the hierarchy redundancy.');
        vgpc_test_same($direct_before, $this->topic_slugs($roles, 700), 'Hierarchy warnings must not delete editor assignments.');
        vgpc_test_same('NEEDS_REVIEW', $roles->get_classification_state(700), 'A hierarchy warning must not become an invalid Primary error.');
    }

    public function test_entity_policy_keeps_medewerker_on_expertise_topics_without_primary(): void
    {
        $roles = $this->fixture();
        $policy = $roles->get_entity_policy();

        vgpc_test_same(['post', 'mto-course', 'product', 'tc_events', 'uitsending', 'kompetisie'], array_values(array_filter(
            array_keys($policy),
            static fn (string $post_type): bool => ! empty($policy[$post_type]['primary_supported'])
        )), 'All non-person entities attached to content_topic must support Primary.');
        vgpc_test_same('required_soft', $policy['post']['primary_requirement'], 'Posts require Primary by policy with soft save enforcement.');
        foreach (['mto-course', 'product', 'tc_events', 'uitsending', 'kompetisie'] as $post_type) {
            vgpc_test_same('optional', $policy[$post_type]['primary_requirement'], $post_type . ' Primary must remain optional.');
        }
        vgpc_test_same(false, $policy['medewerker']['primary_supported'], 'People must not support a Primary role.');
        vgpc_test_same(true, $roles->set_primary_topic(704, $this->term_id('geloof')) instanceof WP_Error, 'The setter must reject Primary for people.');
        vgpc_test_same('NOT_APPLICABLE', $roles->get_classification_state(704), 'People must not receive a Primary review state.');
        vgpc_test_true(is_wp_error($roles->get_secondary_topics(704)), 'Person expertise topics must not be reported as Secondary Topics.');
        vgpc_test_same(['post', 'mto-course', 'product', 'tc_events', 'medewerker', 'uitsending', 'kompetisie'], ContentTopicTaxonomy::OBJECT_TYPES, 'The Task 2 taxonomy attachment policy must remain unchanged.');
    }

    public function test_primary_changes_never_rewrite_the_task2_migration_record(): void
    {
        $roles = $this->fixture();
        $record_before = get_option('vgpc_content_topic_migration_local_v1');

        $roles->set_primary_topic(514, $this->term_id('nuwe-jy'));
        $roles->clear_primary_topic(514);

        vgpc_test_same($record_before, get_option('vgpc_content_topic_migration_local_v1'), 'Primary changes must leave Task 2 provenance byte-for-byte equivalent.');
        vgpc_test_same(['nuwe-jy'], $this->topic_slugs($roles, 514), 'Clearing Primary must leave the migration-owned direct relationship intact.');
        vgpc_test_same('MIGRATION_SEED', $roles->get_classification_state(514), 'Clearing Primary must restore the seed state.');
    }

    public function test_product_commercial_category_never_enters_topic_queries(): void
    {
        $roles = $this->fixture();

        vgpc_test_same(['nuwe-jy'], $this->topic_slugs($roles, 523), 'Product 523 must retain only its canonical topic in the topic API.');
        vgpc_test_same(['nuwe-jy'], $this->topic_slugs($roles, 523, 'get_effective_topics'), 'Effective Topics must not include a commercial category.');
        vgpc_test_true(! in_array('natuurlik-gesond', $this->topic_slugs($roles, 523, 'get_effective_topics'), true), 'content_category must never become an Effective Topic.');
    }

    public function test_unreviewed_task2_seeds_remain_seeds_after_lifecycle_retirement(): void
    {
        $roles = $this->fixture();
        update_option('vgpc_content_topic_migration_local_v1_lifecycle', [
            'status' => 'retired',
            'reason' => 'editorial classification supersedes local migration seeds',
            'retired_at' => '2026-10-15T10:00:00+00:00',
            'migration' => 'content-topic-local-v1',
        ], false);

        foreach ([514, 516, 518, 521, 641] as $object_id) {
            vgpc_test_same('MIGRATION_SEED', $roles->get_classification_state($object_id), 'Retiring Task 2 rollback must not mark an unreviewed seed as reviewed.');
        }
    }

    public function test_reviewed_classification_is_unaffected_by_lifecycle_retirement(): void
    {
        $roles = $this->fixture();
        update_option('vgpc_content_topic_migration_local_v1_lifecycle', [
            'status' => 'retired',
            'reason' => 'editorial classification supersedes local migration seeds',
            'retired_at' => '2026-10-15T10:00:00+00:00',
            'migration' => 'content-topic-local-v1',
        ], false);

        $result = $roles->set_primary_topic(519, $this->term_id('begroting-geldbestuur'));
        vgpc_test_true($result === true, 'The role API must accept a valid Primary after retirement.');
        wp_remove_object_terms(519, $this->term_id('werk-geld'), 'content_topic');

        vgpc_test_same('begroting-geldbestuur', $roles->get_primary_topic(519)->slug, 'The approved specific term must remain Primary after retirement.');
        vgpc_test_same('REVIEWED', $roles->get_classification_state(519), 'A valid Primary must remain reviewed after lifecycle retirement.');
    }

    public function test_519_uses_the_specific_topic_and_infers_its_ancestors(): void
    {
        $roles = $this->fixture();

        $result = $roles->set_primary_topic(519, $this->term_id('begroting-geldbestuur'));
        vgpc_test_true($result === true, 'The approved budget topic must be accepted as Primary.');
        wp_remove_object_terms(519, $this->term_id('werk-geld'), 'content_topic');

        vgpc_test_same(['begroting-geldbestuur'], $this->topic_slugs($roles, 519), 'Object 519 must store only the specific Direct Topic.');
        vgpc_test_same('begroting-geldbestuur', $roles->get_primary_topic(519)->slug, 'Object 519 must use the specific topic as Primary.');
        vgpc_test_same([], $this->topic_slugs($roles, 519, 'get_secondary_topics'), 'Object 519 must have no Secondary Topics.');
        vgpc_test_same(['begroting-geldbestuur', 'geld', 'werk-geld'], $this->topic_slugs($roles, 519, 'get_effective_topics'), 'Object 519 must infer both ancestors as Effective Topics.');
        vgpc_test_same([], $roles->get_hierarchy_warnings(519), 'Object 519 must not keep a redundant parent Direct Topic.');
        vgpc_test_same('REVIEWED', $roles->get_classification_state(519), 'Object 519 must be reviewed after the approved classification.');
    }

    public function test_523_keeps_legacy_commercial_categories_out_of_semantic_topics(): void
    {
        $roles = $this->fixture();
        $legacy_before = $this->legacy_category_slugs(523);

        $result = $roles->set_primary_topic(523, $this->term_id('oefening-fiksheid'));
        vgpc_test_true($result === true, 'The approved exercise topic must be accepted as Primary.');
        wp_remove_object_terms(523, $this->term_id('nuwe-jy'), 'content_topic');

        vgpc_test_same(['oefening-fiksheid'], $this->topic_slugs($roles, 523), 'Object 523 must store only the specific Direct Topic.');
        vgpc_test_same('oefening-fiksheid', $roles->get_primary_topic(523)->slug, 'Object 523 must use the specific topic as Primary.');
        vgpc_test_same([], $this->topic_slugs($roles, 523, 'get_secondary_topics'), 'Object 523 must have no Secondary Topics.');
        vgpc_test_same(['oefening-fiksheid', 'nuwe-jy'], $this->topic_slugs($roles, 523, 'get_effective_topics'), 'Object 523 must infer only its canonical pillar ancestor.');
        vgpc_test_true(! in_array('natuurlik-gesond', $this->topic_slugs($roles, 523), true), 'The legacy commercial term must not become a Direct Topic.');
        vgpc_test_true(! in_array('natuurlik-gesond', $this->topic_slugs($roles, 523, 'get_effective_topics'), true), 'The legacy commercial term must not become an Effective Topic.');
        vgpc_test_same($legacy_before, $this->legacy_category_slugs(523), 'Object 523 content_category relationships must remain unchanged.');
        vgpc_test_same('REVIEWED', $roles->get_classification_state(523), 'Object 523 must be reviewed after the approved classification.');
    }

    public function test_editor_status_and_admin_notice_identify_unreviewed_seed_content(): void
    {
        $roles = $this->fixture();
        $status = $roles->get_editor_status(514);

        vgpc_test_same('MIGRATION_SEED', $status['state'], 'The editor status must expose the seed state.');
        vgpc_test_same('Topic classification awaiting editorial review.', $status['message'], 'Migration seeds must have a clear review notice.');

        $GLOBALS['vgpc_test_screen'] = (object) ['base' => 'post'];
        $_GET['post'] = '514';
        ob_start();
        $roles->render_admin_notice();
        $notice = (string) ob_get_clean();

        vgpc_test_true(str_contains($notice, 'Topic classification awaiting editorial review.'), 'The WordPress editor must display the seed review notice.');

        $_GET['post'] = '705';
        ob_start();
        $roles->render_admin_notice();
        $unclassified_notice = (string) ob_get_clean();
        vgpc_test_true(str_contains($unclassified_notice, 'No Topics are assigned yet.'), 'The editor must see the UNCLASSIFIED state without a save blocker.');
    }
}
