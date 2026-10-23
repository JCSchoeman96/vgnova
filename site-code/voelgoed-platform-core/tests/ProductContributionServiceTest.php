<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Product {
    /** Exercise wp_insert_post_data just as WordPress does during a service insert. */
    function wp_insert_post(array $postarr, bool $wp_error = false): int|\WP_Error
    {
        $postarr = \apply_filters('wp_insert_post_data', $postarr, $postarr);
        return \wp_insert_post($postarr, $wp_error);
    }

    function wp_delete_post(int $post_id, bool $force_delete = false): object|false
    {
        if (! empty($GLOBALS['vgpc_test_skip_delete_post'])) {
            $GLOBALS['vgpc_test_delete_post_calls'][] = [
                'post_id' => $post_id,
                'force_delete' => $force_delete,
            ];
            $GLOBALS['vgpc_test_skip_delete_post'] = false;
            return \get_post($post_id);
        }
        if (! empty($GLOBALS['vgpc_test_fail_delete_post'])) {
            $GLOBALS['vgpc_test_delete_post_calls'][] = [
                'post_id' => $post_id,
                'force_delete' => $force_delete,
            ];
            $GLOBALS['vgpc_test_fail_delete_post'] = false;
            return false;
        }
        $deleted = \wp_delete_post($post_id, $force_delete);
        if ($deleted && ! empty($GLOBALS['vgpc_test_fail_rollback_role_read_now'])) {
            $GLOBALS['vgpc_test_fail_rollback_role_read_now'] = false;
            $GLOBALS['vgpc_test_fail_rollback_role_read'] = true;
        }
        if ($deleted && ! empty($GLOBALS['vgpc_test_leave_rollback_metadata'])) {
            $GLOBALS['vgpc_test_meta'][$post_id]['orphaned_test_meta'] = 'partial';
            $GLOBALS['vgpc_test_leave_rollback_metadata'] = false;
        }
        if ($deleted && ! empty($GLOBALS['vgpc_test_leave_rollback_role'])) {
            $GLOBALS['vgpc_test_relationships'][ProductRoleVocabulary::TAXONOMY][$post_id] = [
                (int) $GLOBALS['vgpc_test_leave_rollback_role'],
            ];
            $GLOBALS['vgpc_test_leave_rollback_role'] = false;
        }
        return $deleted;
    }

    function wp_get_object_terms(int $object_id, string $taxonomy, array $args = []): array|\WP_Error
    {
        if (
            $taxonomy === ProductRoleVocabulary::TAXONOMY
            && ($args['fields'] ?? '') === 'ids'
            && (! empty($GLOBALS['vgpc_test_fail_empty_role_read']) || ! empty($GLOBALS['vgpc_test_fail_rollback_role_read']))
        ) {
            if (! empty($GLOBALS['vgpc_test_fail_empty_role_read'])) {
                $GLOBALS['vgpc_test_fail_empty_role_read'] = false;
            } else {
                $GLOBALS['vgpc_test_fail_rollback_role_read'] = false;
            }
            $GLOBALS['wpdb']->last_error = 'Injected Product Role read failure.';
            return [];
        }
        return \wp_get_object_terms($object_id, $taxonomy, $args);
    }

    function wp_set_object_terms(int $object_id, array|string|int $terms, string $taxonomy, bool $append = false): array|\WP_Error
    {
        if (! empty($GLOBALS['vgpc_test_succeed_without_term_relationships'])) {
            $GLOBALS['vgpc_test_succeed_without_term_relationships'] = false;
            ++$GLOBALS['vgpc_test_set_term_calls'];
            return array_map('intval', (array) $terms);
        }
        return \wp_set_object_terms($object_id, $terms, $taxonomy, $append);
    }
}

namespace {
    use Voelgoed\PlatformCore\Event\EventParticipationRepository;
    use Voelgoed\PlatformCore\Product\ProductContributionAdmin;
    use Voelgoed\PlatformCore\Product\ProductContributionPairLock;
    use Voelgoed\PlatformCore\Product\ProductContributionRepository;
    use Voelgoed\PlatformCore\Product\ProductContributionService;
    use Voelgoed\PlatformCore\Product\ProductPeopleFields;
    use Voelgoed\PlatformCore\Product\ProductRoleVocabulary;

final class ProductContributionServiceTest
{
    private const PRODUCT = 101;
    private const OTHER_PRODUCT = 102;
    private const PERSON = 201;
    private const OTHER_PERSON = 202;
    private const EVENT = 301;

    public function test_invalid_product_and_person_are_rejected_before_insert(): void
    {
        $service = $this->service();
        $this->add_product(self::PRODUCT, 'manual');
        $this->add_endpoint(self::PERSON, 'person', 'Mia Botha');
        $roles = $this->approved_roles();

        $invalid_product = $service->create(999, self::PERSON, [$roles['aanbieder']]);
        $invalid_person = $service->create(self::PRODUCT, 999, [$roles['aanbieder']]);

        vgpc_test_true(is_wp_error($invalid_product), 'A missing Product must be rejected.');
        vgpc_test_same('invalid_product', $invalid_product->get_error_code(), 'The error must identify the invalid Product.');
        vgpc_test_true(is_wp_error($invalid_person), 'A missing Person must be rejected.');
        vgpc_test_same('invalid_person', $invalid_person->get_error_code(), 'The error must identify the invalid Person.');
        vgpc_test_same([], $GLOBALS['vgpc_test_insert_post_calls'], 'Invalid endpoints must not create a contribution.');
    }

    public function test_duplicate_pair_is_rejected_before_insert(): void
    {
        $service = $this->service();
        $this->add_product(self::PRODUCT, 'manual');
        $this->add_endpoint(self::PERSON, 'person', 'Mia Botha');
        $this->add_contribution(401, 'draft', self::PRODUCT, self::PERSON);

        $result = $service->create(self::PRODUCT, self::PERSON, [], 0, 'draft');

        vgpc_test_true(is_wp_error($result), 'An existing non-retired Product × Person pair must be rejected.');
        vgpc_test_same('duplicate_product_contribution', $result->get_error_code(), 'The error must identify the duplicate pair.');
        vgpc_test_same([], $GLOBALS['vgpc_test_insert_post_calls'], 'A duplicate pair must not create another contribution.');
    }

    public function test_negative_display_order_is_rejected_before_insert(): void
    {
        $service = $this->service();
        $this->add_product(self::PRODUCT, 'manual');
        $this->add_endpoint(self::PERSON, 'person', 'Mia Botha');

        $result = $service->create(self::PRODUCT, self::PERSON, [], -1, 'draft');

        vgpc_test_true(is_wp_error($result), 'A negative display order must be rejected.');
        vgpc_test_same('invalid_display_order', $result->get_error_code(), 'The error must identify the invalid display order.');
        vgpc_test_same([], $GLOBALS['vgpc_test_insert_post_calls'], 'An invalid display order must not create a contribution.');
    }

    public function test_create_rejects_unsupported_and_placeholder_statuses_before_insert(): void
    {
        $service = $this->service();
        $this->add_product(self::PRODUCT, 'manual');
        $this->add_endpoint(self::PERSON, 'person', 'Mia Botha');
        $roles = $this->approved_roles();

        foreach (['future', 'trash', 'auto-draft', 'inherit', 'pending-review'] as $status) {
            $result = $service->create(self::PRODUCT, self::PERSON, [$roles['aanbieder']], 0, $status);
            vgpc_test_true(is_wp_error($result), sprintf('The %s status must be rejected by create().', $status));
            vgpc_test_same(
                'invalid_product_contribution_status',
                $result->get_error_code(),
                sprintf('The %s status must return the unsupported-status error.', $status)
            );
            vgpc_test_same([], $GLOBALS['vgpc_test_insert_post_calls'], 'Unsupported statuses must fail before any post is created.');
        }
    }

    public function test_create_future_fails_before_any_contribution_post_is_created(): void
    {
        $service = $this->service();
        $this->add_product(self::PRODUCT, 'manual');
        $this->add_endpoint(self::PERSON, 'person', 'Mia Botha');
        $roles = $this->approved_roles();

        $result = $service->create(self::PRODUCT, self::PERSON, [$roles['aanbieder']], 0, 'future');

        vgpc_test_true(is_wp_error($result), 'The API must reject future without a scheduled publication date.');
        vgpc_test_same('invalid_product_contribution_status', $result->get_error_code(), 'Future must return the unsupported-status error.');
        vgpc_test_same([], $GLOBALS['vgpc_test_insert_post_calls'], 'Future must fail before wp_insert_post is called.');
        vgpc_test_same([], array_values(array_filter(
            $GLOBALS['vgpc_test_posts'],
            static fn (object $post): bool => ($post->post_type ?? '') === ProductContributionRepository::POST_TYPE
        )), 'Future must leave no Product Contribution post behind.');
    }

    public function test_zero_roles_are_rejected_when_publishing(): void
    {
        $service = $this->service();
        $this->add_product(self::PRODUCT, 'manual');
        $this->add_endpoint(self::PERSON, 'person', 'Mia Botha');

        $result = $service->create(self::PRODUCT, self::PERSON, [], 0, 'publish');

        vgpc_test_true(is_wp_error($result), 'A published contribution must have at least one Product Role.');
        vgpc_test_same('product_roles_required', $result->get_error_code(), 'The error must identify the missing publication roles.');
        vgpc_test_same([], $GLOBALS['vgpc_test_insert_post_calls'], 'Role-less publication must not create a contribution.');
    }

    public function test_malformed_role_ids_are_rejected_before_insert(): void
    {
        $service = $this->service();
        $this->add_product(self::PRODUCT, 'manual');
        $this->add_endpoint(self::PERSON, 'person', 'Mia Botha');

        foreach ([['not-an-id'], [0], [-3], [new stdClass()]] as $role_ids) {
            $result = $service->create(self::PRODUCT, self::PERSON, $role_ids, 0, 'draft');
            vgpc_test_true(is_wp_error($result), 'Malformed Product Role IDs must be rejected even on inactive rows.');
            vgpc_test_same('invalid_product_role', $result->get_error_code(), 'Malformed role IDs must report invalid_product_role.');
        }
        vgpc_test_same([], $GLOBALS['vgpc_test_insert_post_calls'], 'Malformed roles must not create contributions.');
    }

    public function test_wrong_taxonomy_and_unapproved_fifth_roles_are_rejected(): void
    {
        $service = $this->service();
        $this->add_product(self::PRODUCT, 'manual');
        $this->add_endpoint(self::PERSON, 'person', 'Mia Botha');
        $wrong_taxonomy = vgpc_test_add_term('Aanbieder', 'aanbieder', 0, 'event_role');
        $fifth_role = vgpc_test_add_term('Mentor', 'mentor', 0, ProductRoleVocabulary::TAXONOMY);

        foreach ([$wrong_taxonomy->term_id, $fifth_role->term_id] as $role_id) {
            $result = $service->create(self::PRODUCT, self::PERSON, [$role_id], 0, 'draft');
            vgpc_test_true(is_wp_error($result), 'A role outside the exact approved taxonomy vocabulary must be rejected.');
            vgpc_test_same('invalid_product_role', $result->get_error_code(), 'Unapproved terms must report invalid_product_role.');
        }
        vgpc_test_same([], $GLOBALS['vgpc_test_insert_post_calls'], 'Unapproved roles must not create contributions.');
    }

    public function test_manual_mode_allows_a_valid_published_contribution(): void
    {
        $service = $this->service();
        $this->add_product(self::PRODUCT, 'manual');
        $this->add_endpoint(self::PERSON, 'person', 'Mia Botha');
        $roles = $this->approved_roles();

        $result = $service->create(self::PRODUCT, self::PERSON, [$roles['aanbieder']]);

        vgpc_test_true(is_int($result) && $result > 0, 'A valid manual contribution must publish.');
        vgpc_test_same('publish', get_post_status($result), 'The manual contribution must be stored as published.');
    }

    public function test_blank_or_invalid_product_mode_cannot_be_published(): void
    {
        $service = $this->service();
        $this->add_endpoint(self::PERSON, 'person', 'Mia Botha');
        $roles = $this->approved_roles();

        foreach (['', 'invalid', 'Manual', 'manual '] as $mode) {
            $this->add_product(self::PRODUCT, $mode);
            $result = $service->create(self::PRODUCT, self::PERSON, [$roles['aanbieder']]);
            vgpc_test_true(is_wp_error($result), 'A blank or unrecognized Product mode must not publish.');
            vgpc_test_same('invalid_product_mode', $result->get_error_code(), 'The mode error must be explicit.');
        }
        vgpc_test_same([], $GLOBALS['vgpc_test_insert_post_calls'], 'Invalid Product modes must not create published contributions.');
    }

    public function test_event_mode_publish_requires_person_in_current_source_event(): void
    {
        $service = $this->service();
        $this->add_product(self::PRODUCT, 'event', self::EVENT);
        $this->add_endpoint(self::EVENT, 'tc_events', 'Vroue-aand');
        $this->add_endpoint(self::PERSON, 'person', 'Mia Botha');
        $roles = $this->approved_roles();

        $result = $service->create(self::PRODUCT, self::PERSON, [$roles['skrywer']]);

        vgpc_test_true(is_wp_error($result), 'Event-mode publication must not create Event membership.');
        vgpc_test_same('person_not_in_source_event', $result->get_error_code(), 'The error must identify stale or missing source-Event membership.');
        vgpc_test_same([], $GLOBALS['vgpc_test_insert_post_calls'], 'An event annotation without current Event Participation must not publish.');
    }

    public function test_create_rechecks_a_stale_pre_lock_view_against_the_fresh_pair_after_lock(): void
    {
        $service = $this->service();
        $this->add_product(self::PRODUCT, 'manual');
        $this->add_endpoint(self::PERSON, 'person', 'Mia Botha');
        $this->install_fresh_pair_lookup();
        $pair_was_committed = false;
        $GLOBALS['wpdb']->get_var_handler = function (string $query) use (&$pair_was_committed): string {
            if ($query === 'SELECT GET_LOCK(%s, %d)' && ! $pair_was_committed) {
                // This request saw no duplicate before waiting. Another writer commits before lock acquisition.
                $this->add_contribution(401, 'publish', self::PRODUCT, self::PERSON);
                $pair_was_committed = true;
            }
            return '1';
        };

        $result = $service->create(self::PRODUCT, self::PERSON, [], 0, 'draft');

        vgpc_test_true(is_wp_error($result), 'A duplicate that appears while waiting for the lock must stop creation.');
        vgpc_test_same('duplicate_product_contribution', $result->get_error_code(), 'The locked fresh check must report the duplicate.');
        vgpc_test_same([], $GLOBALS['vgpc_test_insert_post_calls'], 'A stale pre-lock view must not permit a second insert.');
        vgpc_test_same(
            ['SELECT GET_LOCK(%s, %d)', 'SELECT RELEASE_LOCK(%s)'],
            array_column($GLOBALS['wpdb']->queries, 'query'),
            'The service must hold the Product × Person lock through its fresh check and release it afterward.'
        );
    }

    public function test_create_writes_derived_title_fields_and_multiple_native_roles_to_one_row(): void
    {
        $service = $this->service();
        $this->add_product(self::PRODUCT, 'manual');
        $GLOBALS['vgpc_test_posts'][self::PRODUCT]->post_title = 'Warm Water Bottle';
        $this->add_endpoint(self::PERSON, 'person', 'Mia Botha');
        $roles = $this->approved_roles();
        $selected_roles = [$roles['skrywer'], $roles['aanbieder']];

        $contribution_id = $service->create(self::PRODUCT, self::PERSON, $selected_roles, 7, 'publish');

        vgpc_test_true(is_int($contribution_id), 'A valid contribution must return its new post ID.');
        vgpc_test_same(1, count($GLOBALS['vgpc_test_insert_post_calls']), 'Multiple roles must remain on one contribution row.');
        vgpc_test_same(
            'Warm Water Bottle — Mia Botha',
            get_the_title($contribution_id),
            'The contribution title must be Product title, em dash, then Person title.'
        );
        vgpc_test_same(self::PRODUCT, (int) get_field(ProductContributionRepository::PRODUCT_FIELD, $contribution_id), 'The Product ACF field must store the Product ID.');
        vgpc_test_same(self::PERSON, (int) get_field(ProductContributionRepository::PERSON_FIELD, $contribution_id), 'The Person ACF field must store the Person ID.');
        vgpc_test_same(7, (int) get_field(ProductContributionRepository::DISPLAY_ORDER_FIELD, $contribution_id), 'The display-order ACF field must store the requested order.');
        vgpc_test_same($selected_roles, $GLOBALS['vgpc_test_relationships'][ProductRoleVocabulary::TAXONOMY][$contribution_id], 'Product Roles must be assigned as native taxonomy terms on the contribution.');
    }

    public function test_partial_acf_field_write_failure_hard_rolls_back_the_new_row(): void
    {
        foreach ([
            ProductContributionRepository::PRODUCT_FIELD,
            ProductContributionRepository::PERSON_FIELD,
            ProductContributionRepository::DISPLAY_ORDER_FIELD,
        ] as $failing_field) {
            $service = $this->service();
            $this->add_product(self::PRODUCT, 'manual');
            $this->add_endpoint(self::PERSON, 'person', 'Mia Botha');
            $roles = $this->approved_roles();
            $GLOBALS['vgpc_test_fail_update_field_name'] = $failing_field;

            $result = $service->create(self::PRODUCT, self::PERSON, [$roles['aanbieder']], 4, 'draft');

            vgpc_test_true(is_wp_error($result), sprintf('A failure writing %s must fail create().', $failing_field));
            vgpc_test_same([], $GLOBALS['vgpc_test_posts'][5000] ?? [], 'A field-write failure must remove the newly inserted row, not leave it in trash.');
            vgpc_test_same([], $GLOBALS['vgpc_test_meta'][5000] ?? [], 'A field-write failure must remove partial ACF metadata.');
            vgpc_test_same([['post_id' => 5000, 'force_delete' => true]], $GLOBALS['vgpc_test_delete_post_calls'], 'A partial field failure must hard-delete only the new contribution.');
        }
    }

    public function test_native_role_assignment_failure_hard_rolls_back_the_new_row(): void
    {
        $service = $this->service();
        $this->add_product(self::PRODUCT, 'manual');
        $this->add_endpoint(self::PERSON, 'person', 'Mia Botha');
        $roles = $this->approved_roles();
        $GLOBALS['vgpc_test_fail_set_term_call'] = 1;

        $result = $service->create(self::PRODUCT, self::PERSON, [$roles['aanbieder']], 0, 'draft');

        vgpc_test_true(is_wp_error($result), 'A failed native role assignment must fail create().');
        vgpc_test_same([], $GLOBALS['vgpc_test_posts'][5000] ?? [], 'A role-write failure must hard-delete the new contribution.');
        vgpc_test_same([], $GLOBALS['vgpc_test_meta'][5000] ?? [], 'A role-write failure must remove partial ACF metadata.');
        vgpc_test_same([['post_id' => 5000, 'force_delete' => true]], $GLOBALS['vgpc_test_delete_post_calls'], 'A role-write failure must invoke hard rollback.');
    }

    public function test_native_role_assignment_array_result_must_be_verified_before_success(): void
    {
        $service = $this->service();
        $this->add_product(self::PRODUCT, 'manual');
        $this->add_endpoint(self::PERSON, 'person', 'Mia Botha');
        $roles = $this->approved_roles();
        $GLOBALS['vgpc_test_succeed_without_term_relationships'] = true;

        $result = $service->create(self::PRODUCT, self::PERSON, [$roles['aanbieder']], 4, 'publish');

        vgpc_test_true(is_wp_error($result), 'A successful return from wp_set_object_terms must not hide missing persisted relationships.');
        vgpc_test_same('product_contribution_role_persistence_failed', $result->get_error_code(), 'A role persistence mismatch must have a dedicated write error.');
        vgpc_test_same([], $GLOBALS['vgpc_test_posts'][5000] ?? [], 'A published contribution without its requested native role must be hard-rolled back.');
        vgpc_test_same([], $GLOBALS['vgpc_test_relationships'][ProductRoleVocabulary::TAXONOMY][5000] ?? [], 'No native role relationship may survive the rollback.');
    }

    public function test_empty_role_read_with_database_error_fails_closed(): void
    {
        $service = $this->service();
        $this->add_product(self::PRODUCT, 'manual');
        $this->add_endpoint(self::PERSON, 'person', 'Mia Botha');
        $GLOBALS['vgpc_test_fail_empty_role_read'] = true;

        $result = $service->create(self::PRODUCT, self::PERSON, [], 4, 'draft');

        vgpc_test_true(is_wp_error($result), 'An empty role read with a database error must not be treated as a verified empty set.');
        vgpc_test_true(($GLOBALS['vgpc_test_posts'][5000] ?? false) === false, 'An uncertain native role read must hard-roll back the new contribution.');
    }

    public function test_failed_hard_rollback_is_reported_explicitly(): void
    {
        $service = $this->service();
        $this->add_product(self::PRODUCT, 'manual');
        $this->add_endpoint(self::PERSON, 'person', 'Mia Botha');
        $roles = $this->approved_roles();
        $GLOBALS['vgpc_test_fail_update_field_name'] = ProductContributionRepository::PRODUCT_FIELD;
        $GLOBALS['vgpc_test_fail_delete_post'] = true;

        $result = $service->create(self::PRODUCT, self::PERSON, [$roles['aanbieder']], 4, 'draft');

        vgpc_test_true(is_wp_error($result), 'A field-write failure must still fail create when rollback also fails.');
        vgpc_test_same('product_contribution_rollback_failed', $result->get_error_code(), 'A failed hard delete must have a distinct rollback error code.');
        vgpc_test_true(str_contains($result->get_error_message(), 'Rollback failed'), 'The error must state that the newly inserted row could not be removed.');
        vgpc_test_true(isset($GLOBALS['vgpc_test_posts'][5000]), 'The test must prove the failed deletion left the partial row behind.');
        vgpc_test_same([['post_id' => 5000, 'force_delete' => true]], $GLOBALS['vgpc_test_delete_post_calls'], 'Rollback must attempt a force delete of only the new contribution.');
    }

    public function test_pre_delete_short_circuit_cannot_be_mistaken_for_a_hard_rollback(): void
    {
        $service = $this->service();
        $this->add_product(self::PRODUCT, 'manual');
        $this->add_endpoint(self::PERSON, 'person', 'Mia Botha');
        $roles = $this->approved_roles();
        $GLOBALS['vgpc_test_fail_update_field_name'] = ProductContributionRepository::PRODUCT_FIELD;
        $GLOBALS['vgpc_test_skip_delete_post'] = true;

        $result = $service->create(self::PRODUCT, self::PERSON, [$roles['aanbieder']], 4, 'draft');

        vgpc_test_true(is_wp_error($result), 'A deletion filter that leaves the post must fail the create operation.');
        vgpc_test_same('product_contribution_rollback_failed', $result->get_error_code(), 'A surviving partial row must be reported as rollback failure.');
        vgpc_test_true(isset($GLOBALS['vgpc_test_posts'][5000]), 'The regression must prove the pre-delete short-circuit left the row behind.');
    }

    public function test_hard_rollback_reports_orphaned_metadata_after_post_deletion(): void
    {
        $service = $this->service();
        $this->add_product(self::PRODUCT, 'manual');
        $this->add_endpoint(self::PERSON, 'person', 'Mia Botha');
        $roles = $this->approved_roles();
        $GLOBALS['vgpc_test_fail_update_field_name'] = ProductContributionRepository::PRODUCT_FIELD;
        $GLOBALS['vgpc_test_leave_rollback_metadata'] = true;

        $result = $service->create(self::PRODUCT, self::PERSON, [$roles['aanbieder']], 4, 'draft');

        vgpc_test_true(is_wp_error($result), 'Rollback must fail when contribution metadata remains after post deletion.');
        vgpc_test_same('product_contribution_rollback_failed', $result->get_error_code(), 'Orphaned metadata must be reported as rollback failure.');
        vgpc_test_true(get_post(5000) === false, 'The post row may be deleted while its orphaned metadata remains.');
        vgpc_test_true(get_post_meta(5000) !== [], 'The test must prove post metadata survived the delete.');
    }

    public function test_hard_rollback_reports_orphaned_native_role_relationships(): void
    {
        $service = $this->service();
        $this->add_product(self::PRODUCT, 'manual');
        $this->add_endpoint(self::PERSON, 'person', 'Mia Botha');
        $roles = $this->approved_roles();
        $GLOBALS['vgpc_test_fail_update_field_name'] = ProductContributionRepository::PRODUCT_FIELD;
        $GLOBALS['vgpc_test_leave_rollback_role'] = $roles['aanbieder'];

        $result = $service->create(self::PRODUCT, self::PERSON, [$roles['aanbieder']], 4, 'draft');

        vgpc_test_true(is_wp_error($result), 'Rollback must fail when a native Product Role relationship remains.');
        vgpc_test_same('product_contribution_rollback_failed', $result->get_error_code(), 'An orphaned role relationship must be reported as rollback failure.');
        vgpc_test_true(get_post(5000) === false, 'The post row may be deleted while its taxonomy relationship remains.');
        vgpc_test_same([$roles['aanbieder']], $GLOBALS['vgpc_test_relationships'][ProductRoleVocabulary::TAXONOMY][5000] ?? [], 'The test must prove the native role relationship survived the delete.');
    }

    public function test_hard_rollback_reports_uncertain_native_role_cleanup_read(): void
    {
        $service = $this->service();
        $this->add_product(self::PRODUCT, 'manual');
        $this->add_endpoint(self::PERSON, 'person', 'Mia Botha');
        $roles = $this->approved_roles();
        $GLOBALS['vgpc_test_fail_update_field_name'] = ProductContributionRepository::PRODUCT_FIELD;
        $GLOBALS['vgpc_test_fail_rollback_role_read_now'] = true;

        $result = $service->create(self::PRODUCT, self::PERSON, [$roles['aanbieder']], 4, 'draft');

        vgpc_test_true(is_wp_error($result), 'An uncertain taxonomy cleanup read must fail rollback verification.');
        vgpc_test_same('product_contribution_rollback_failed', $result->get_error_code(), 'An uncertain cleanup read must be a rollback failure.');
    }

    public function test_incomplete_draft_can_be_created_without_roles_or_publish_mode(): void
    {
        $service = $this->service();
        $this->add_product(self::PRODUCT, '');
        $this->add_endpoint(self::PERSON, 'person', 'Mia Botha');

        $result = $service->create(self::PRODUCT, self::PERSON, [], 0, 'draft');

        vgpc_test_true(is_int($result), 'A draft with valid endpoints may remain incomplete.');
        vgpc_test_same('draft', get_post_status($result), 'The incomplete contribution must remain inactive as a draft.');
        vgpc_test_same([], $GLOBALS['vgpc_test_relationships'][ProductRoleVocabulary::TAXONOMY][$result] ?? [], 'An incomplete draft need not have roles.');
    }

    public function test_shared_role_validation_allows_optional_empty_roles_and_rejects_unapproved_terms(): void
    {
        $service = $this->service();
        $wrong_taxonomy = vgpc_test_add_term('Aanbieder', 'aanbieder', 0, 'event_role');

        vgpc_test_same(true, $service->validate_roles([], false), 'Inactive lifecycle states may omit Product Roles.');
        $invalid = $service->validate_roles([$wrong_taxonomy->term_id], false);
        vgpc_test_true(is_wp_error($invalid), 'A supplied role must use the approved Product Role vocabulary.');
        vgpc_test_same('invalid_product_role', $invalid->get_error_code(), 'The shared validator must identify an invalid role.');
    }

    public function test_programmatic_create_passes_the_admin_save_hook_with_its_validated_payload(): void
    {
        foreach (['publish', 'draft'] as $status) {
            $service = $this->service();
            $this->add_product(self::PRODUCT, $status === 'publish' ? 'manual' : '');
            $this->add_endpoint(self::PERSON, 'person', 'Mia Botha');
            $roles = $this->approved_roles();
            $repository = new ProductContributionRepository();
            $admin = new ProductContributionAdmin($repository, $service, new ProductContributionPairLock());
            $admin->register();

            $result = $service->create(self::PRODUCT, self::PERSON, $status === 'publish' ? [$roles['aanbieder']] : [], 3, $status);

            vgpc_test_true(is_int($result), sprintf('A valid %s service create must pass the admin pre-save hook.', $status));
            vgpc_test_same('Product 101 — Mia Botha', get_the_title($result), sprintf('The admin hook must preserve the derived %s title.', $status));
            vgpc_test_same($status, get_post_status($result), sprintf('The service must preserve the requested %s status.', $status));
            vgpc_test_same(self::PRODUCT, (int) get_field(ProductContributionRepository::PRODUCT_FIELD, $result), 'The Product endpoint must be written after insert.');
            vgpc_test_same(self::PERSON, (int) get_field(ProductContributionRepository::PERSON_FIELD, $result), 'The Person endpoint must be written after insert.');
            vgpc_test_true(! ProductContributionService::is_service_insert_in_progress(), 'The scoped insert context must clear after the WordPress insert.');
        }
    }

    private function service(): ProductContributionService
    {
        vgpc_test_reset();
        $this->register_fields();
        register_taxonomy(ProductRoleVocabulary::TAXONOMY, [ProductContributionRepository::POST_TYPE]);
        register_taxonomy(EventParticipationRepository::ROLE_TAXONOMY, [EventParticipationRepository::POST_TYPE]);
        $this->install_fresh_pair_lookup();

        return new ProductContributionService(new ProductContributionRepository(), new ProductContributionPairLock());
    }

    private function register_fields(): void
    {
        acf_add_local_field_group([
            'key' => 'group_vgpc_test_fields',
            'fields' => [
                ['key' => 'field_vgpc_test_mode', 'name' => ProductPeopleFields::MODE_FIELD],
                ['key' => 'field_vgpc_test_source_event', 'name' => ProductContributionRepository::SOURCE_EVENT_FIELD],
                ['key' => 'field_vgpc_test_product', 'name' => ProductContributionRepository::PRODUCT_FIELD],
                ['key' => 'field_vgpc_test_person', 'name' => ProductContributionRepository::PERSON_FIELD],
                ['key' => 'field_vgpc_test_order', 'name' => ProductContributionRepository::DISPLAY_ORDER_FIELD],
                ['key' => 'field_vgpc_test_roles', 'name' => 'product_contribution_roles'],
                ['key' => 'field_vgpc_test_event', 'name' => EventParticipationRepository::EVENT_FIELD],
                ['key' => 'field_vgpc_test_event_person', 'name' => EventParticipationRepository::PERSON_FIELD],
                ['key' => 'field_vgpc_test_event_order', 'name' => EventParticipationRepository::DISPLAY_ORDER_FIELD],
            ],
        ]);
    }

    private function approved_roles(): array
    {
        $ids = [];
        foreach (ProductRoleVocabulary::approved_terms() as $slug => $name) {
            $term = vgpc_test_add_term($name, $slug, 0, ProductRoleVocabulary::TAXONOMY);
            $ids[$slug] = $term->term_id;
        }
        return $ids;
    }

    private function add_product(int $id, string $mode, int $event_id = 0): void
    {
        $this->add_endpoint($id, 'product', 'Product ' . $id);
        $GLOBALS['vgpc_test_meta'][$id][ProductPeopleFields::MODE_FIELD] = $mode;
        $GLOBALS['vgpc_test_meta'][$id][ProductContributionRepository::SOURCE_EVENT_FIELD] = $event_id;
    }

    private function add_endpoint(int $id, string $type, string $title, string $status = 'publish'): void
    {
        $GLOBALS['vgpc_test_posts'][$id] = (object) [
            'ID' => $id,
            'post_type' => $type,
            'post_status' => $status,
            'post_title' => $title,
        ];
    }

    private function add_contribution(int $id, string $status, int $product_id, int $person_id): void
    {
        $GLOBALS['vgpc_test_posts'][$id] = (object) [
            'ID' => $id,
            'post_type' => ProductContributionRepository::POST_TYPE,
            'post_status' => $status,
            'post_title' => 'Existing contribution',
        ];
        $GLOBALS['vgpc_test_meta'][$id][ProductContributionRepository::PRODUCT_FIELD] = $product_id;
        $GLOBALS['vgpc_test_meta'][$id][ProductContributionRepository::PERSON_FIELD] = $person_id;
    }

    private function install_fresh_pair_lookup(): void
    {
        $GLOBALS['vgpc_test_fresh_pair_lookups'] = [];
        $GLOBALS['wpdb']->get_col_handler = static function (string $query, array $args): array {
            if (! str_contains($query, 'SELECT DISTINCT contribution.ID')) {
                return [];
            }

            $product_field_index = array_search(ProductContributionRepository::PRODUCT_FIELD, $args, true);
            $person_field_index = array_search(ProductContributionRepository::PERSON_FIELD, $args, true);
            if (! is_int($product_field_index) || ! is_int($person_field_index)) {
                return [];
            }
            $product_id = (int) ($args[$product_field_index + 1] ?? 0);
            $person_id = (int) ($args[$person_field_index + 1] ?? 0);
            $exclude_id = str_contains($query, 'contribution.ID <> %d') ? (int) end($args) : 0;
            $matches = [];
            foreach ($GLOBALS['vgpc_test_posts'] as $id => $post) {
                if (
                    ($post->post_type ?? '') === ProductContributionRepository::POST_TYPE
                    && in_array((string) ($post->post_status ?? ''), ProductContributionRepository::UNIQUENESS_STATUSES, true)
                    && (int) $id !== $exclude_id
                    && (int) ($GLOBALS['vgpc_test_meta'][$id][ProductContributionRepository::PRODUCT_FIELD] ?? 0) === $product_id
                    && (int) ($GLOBALS['vgpc_test_meta'][$id][ProductContributionRepository::PERSON_FIELD] ?? 0) === $person_id
                ) {
                    $matches[] = (int) $id;
                }
            }
            sort($matches, SORT_NUMERIC);
            $GLOBALS['vgpc_test_fresh_pair_lookups'][] = [
                'product_id' => $product_id,
                'person_id' => $person_id,
                'matches' => $matches,
            ];
            return $matches;
        };
    }
}
}
