<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Person\PersonRelationshipAdmin;
use Voelgoed\PlatformCore\Person\PersonRelationshipContract;
use Voelgoed\PlatformCore\Person\PersonRelationshipIndexer;
use Voelgoed\PlatformCore\Person\PersonRelationshipRepository;
use Voelgoed\PlatformCore\Person\PersonRelationshipSchema;
use Voelgoed\PlatformCore\Person\PersonRelationshipSourceLock;
use Voelgoed\PlatformCore\Person\PersonRelationshipSourceReader;
use Voelgoed\PlatformCore\Person\PersonRelationshipValidator;
use Voelgoed\PlatformCore\Product\ProductContributionAdmin;

if (! function_exists('check_and_publish_future_post')) {
    function check_and_publish_future_post(int $post_id): void
    {
        $GLOBALS['vgpc_test_core_future_publish_calls'][] = $post_id;
    }
}
if (! function_exists('remove_action')) {
    function remove_action(string $hook, callable|string $callback, int $priority = 10): bool
    {
        return true;
    }
}

final class PersonRelationshipAdminTest
{
    public function test_registers_all_field_and_lifecycle_hooks(): void
    {
        $admin = $this->admin();
        $admin->register();

        foreach ([
            'field_vg_article_primary_author', 'field_vg_article_additional_authors',
            'field_vg_article_expert_contributors', 'field_vg_article_reviewed_by',
            'field_vg_course_lead_instructor', 'field_vg_course_additional_instructors',
            'field_vg_course_experts',
        ] as $key) {
            vgpc_test_true(isset($GLOBALS['vgpc_test_filters']['acf/validate_value/key=' . $key]), 'Every approved field key must have validation.');
        }
        vgpc_test_true(isset($GLOBALS['vgpc_test_filters']['wp_insert_post_data']), 'Source publication must be checked before save.');
        vgpc_test_true(isset($GLOBALS['vgpc_test_actions']['acf/save_post']), 'ACF saves must project at priority 20.');
        vgpc_test_true(isset($GLOBALS['vgpc_test_actions']['before_delete_post']), 'Permanent deletion must clean derived rows.');
        vgpc_test_true(isset($GLOBALS['vgpc_test_filters']['voelgoed/platform_core/future_post_can_publish']), 'Scheduled publication must use the shared filter seam.');
    }

    public function test_rejects_malformed_and_duplicate_field_submissions(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(10, 'draft', [20, 21]);
        $_POST = ['post_ID' => 10, 'post_status' => 'draft', 'acf' => [
            'field_vg_article_primary_author' => 20,
            'field_vg_article_additional_authors' => ['not-an-id'],
        ]];

        $valid = apply_filters('acf/validate_value/key=field_vg_article_additional_authors', true, ['not-an-id'], ['key' => 'field_vg_article_additional_authors', 'name' => 'additional_authors'], 'acf[field_vg_article_additional_authors]');
        vgpc_test_true(is_string($valid), 'Malformed submitted values must be rejected with an ACF error.');

        $_POST['acf']['field_vg_article_additional_authors'] = [20];
        $valid = apply_filters('acf/validate_value/key=field_vg_article_additional_authors', true, [20], ['key' => 'field_vg_article_additional_authors', 'name' => 'additional_authors'], 'acf[field_vg_article_additional_authors]');
        vgpc_test_true(is_string($valid), 'A Person submitted in two roles must be rejected.');
    }

    public function test_allows_valid_draft_with_unpublished_person(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(11, 'draft', [22]);
        $_POST = ['post_ID' => 11, 'post_status' => 'draft', 'acf' => [
            'field_vg_article_primary_author' => 22,
        ]];

        $valid = apply_filters('acf/validate_value/key=field_vg_article_primary_author', true, 22, ['key' => 'field_vg_article_primary_author', 'name' => 'primary_author'], 'acf[field_vg_article_primary_author]');
        vgpc_test_same(true, $valid, 'A draft source may reference a draft Person.');
    }

    public function test_scheduled_publish_filter_blocks_invalid_and_allows_valid_sources(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(12, 'future', [23]);
        $GLOBALS['vgpc_test_meta'][12]['primary_author'] = 23;

        vgpc_test_same(false, apply_filters('voelgoed/platform_core/future_post_can_publish', true, 12, get_post(12)), 'A scheduled Article with a draft Person must stay unpublished.');
        $GLOBALS['vgpc_test_posts'][23]->post_status = 'publish';
        vgpc_test_same(true, apply_filters('voelgoed/platform_core/future_post_can_publish', true, 12, get_post(12)), 'A scheduled Article with a published Person may publish.');
    }

    public function test_scheduled_courses_use_the_same_publication_rules(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(13, 'future', [24], 'mto-course');
        $GLOBALS['vgpc_test_meta'][13]['lead_instructor'] = 24;

        vgpc_test_same(false, apply_filters('voelgoed/platform_core/future_post_can_publish', true, 13, get_post(13)), 'A scheduled Course with a draft Person must stay unpublished.');
        $GLOBALS['vgpc_test_posts'][24]->post_status = 'publish';
        vgpc_test_same(true, apply_filters('voelgoed/platform_core/future_post_can_publish', true, 13, get_post(13)), 'A scheduled Course with a published Person may publish.');
    }

    public function test_invalid_scheduled_source_is_not_sent_to_wordpress_publisher(): void
    {
        $person_admin = $this->admin();
        $person_admin->register();
        $product_admin = new ProductContributionAdmin();
        $product_admin->register();
        $this->source_and_people(16, 'future', [26]);
        $GLOBALS['vgpc_test_meta'][16]['primary_author'] = 26;
        $GLOBALS['vgpc_test_core_future_publish_calls'] = [];

        $product_admin->handle_future_publication(16);

        vgpc_test_same([], $GLOBALS['vgpc_test_core_future_publish_calls'], 'Invalid scheduled Article must not reach the WordPress publisher.');
    }

    public function test_pre_save_validation_blocks_invalid_publication_but_accepts_valid_schedule(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(17, 'draft', [27]);
        $_POST = ['post_ID' => 17, 'acf' => ['field_vg_article_primary_author' => 27]];

        try {
            apply_filters('wp_insert_post_data', ['ID' => 17, 'post_type' => 'post', 'post_status' => 'publish'], ['ID' => 17]);
            throw new RuntimeException('Invalid publication should have been blocked.');
        } catch (RuntimeException $error) {
            vgpc_test_true(str_contains($error->getMessage(), 'Person 27'), 'Pre-save validation must return an actionable Person error.');
        }

        $GLOBALS['vgpc_test_posts'][27]->post_status = 'publish';
        $data = ['ID' => 17, 'post_type' => 'post', 'post_status' => 'future'];
        vgpc_test_same($data, apply_filters('wp_insert_post_data', $data, ['ID' => 17]), 'A valid scheduled Article must pass source-level validation.');
    }

    public function test_new_source_can_pass_pre_save_validation_before_wordpress_assigns_an_id(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(28, 'publish', [29]);
        $GLOBALS['vgpc_test_posts'][29]->post_status = 'publish';
        $_POST = ['post_type' => 'mto-course', 'acf' => ['field_vg_course_lead_instructor' => 29]];
        $data = ['post_type' => 'mto-course', 'post_status' => 'future'];

        vgpc_test_same($data, apply_filters('wp_insert_post_data', $data, []), 'A valid new Course must pass pre-save validation before it has a source ID.');
    }

    public function test_unrelated_future_posts_still_delegate_to_wordpress(): void
    {
        $person_admin = $this->admin();
        $person_admin->register();
        $product_admin = new ProductContributionAdmin();
        $product_admin->register();
        $GLOBALS['vgpc_test_posts'][14] = (object) ['ID' => 14, 'post_type' => 'page', 'post_status' => 'future'];
        $GLOBALS['vgpc_test_core_future_publish_calls'] = [];

        $product_admin->handle_future_publication(14);

        vgpc_test_same([14], $GLOBALS['vgpc_test_core_future_publish_calls'], 'Unrelated future posts must continue through the WordPress publisher.');
    }

    public function test_indexer_failure_does_not_abort_source_save_and_delete_cleans_rows(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(15, 'draft', [25]);
        $GLOBALS['vgpc_test_meta'][15]['primary_author'] = 25;
        $GLOBALS['vgpc_test_options'][PersonRelationshipSchema::VERSION_OPTION] = PersonRelationshipSchema::SCHEMA_VERSION;
        $GLOBALS['wpdb']->get_var_handler = static fn (string $query): string => str_contains($query, 'SHOW TABLES') ? 'wp_vg_person_relationship_index' : '1';
        $GLOBALS['wpdb']->fail_insert = true;

        do_action('acf/save_post', 15);

        vgpc_test_same('draft', get_post(15)->post_status, 'Projection failure must not change the saved source status.');
        vgpc_test_true((new PersonRelationshipIndexer(new PersonRelationshipSourceLock(), new PersonRelationshipSourceReader(), new PersonRelationshipValidator(), new PersonRelationshipRepository(new PersonRelationshipSchema()), static function (): void {}))->is_dirty(15), 'Projection failure must retain the dirty marker.');

        $GLOBALS['wpdb']->fail_insert = false;
        $GLOBALS['wpdb']->fail_query = false;
        $GLOBALS['wpdb']->query_handler = null;
        do_action('before_delete_post', 15, get_post(15));
        vgpc_test_true((bool) array_filter($GLOBALS['wpdb']->queries, static fn (array $query): bool => str_contains($query['query'], 'DELETE FROM wp_vg_person_relationship_index')), 'Permanent deletion must remove derived source rows.');
    }

    private function admin(): PersonRelationshipAdmin
    {
        return new PersonRelationshipAdmin($this->indexer());
    }

    private function indexer(): PersonRelationshipIndexer
    {
        $schema = new PersonRelationshipSchema();
        return new PersonRelationshipIndexer(new PersonRelationshipSourceLock(), new PersonRelationshipSourceReader(), new PersonRelationshipValidator(), new PersonRelationshipRepository($schema), static function (): void {});
    }

    private function source_and_people(int $source_id, string $status, array $person_ids, string $source_type = 'post'): void
    {
        acf_add_local_field_group(['key' => 'test_person_relationship_admin_fields', 'fields' => [
            ['key' => 'field_vg_article_primary_author', 'name' => 'primary_author'],
            ['key' => 'field_vg_article_additional_authors', 'name' => 'additional_authors'],
            ['key' => 'field_vg_article_expert_contributors', 'name' => 'expert_contributors'],
            ['key' => 'field_vg_article_reviewed_by', 'name' => 'reviewed_by'],
            ['key' => 'field_vg_course_lead_instructor', 'name' => 'lead_instructor'],
            ['key' => 'field_vg_course_additional_instructors', 'name' => 'additional_instructors'],
            ['key' => 'field_vg_course_experts', 'name' => 'experts'],
        ]]);
        $GLOBALS['vgpc_test_posts'][$source_id] = (object) ['ID' => $source_id, 'post_type' => $source_type, 'post_status' => $status];
        foreach ($person_ids as $person_id) {
            $GLOBALS['vgpc_test_posts'][$person_id] = (object) ['ID' => $person_id, 'post_type' => 'person', 'post_status' => 'draft'];
        }
    }
}
