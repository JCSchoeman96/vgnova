<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Event\EventParticipationRepository;
use Voelgoed\PlatformCore\Event\EventParticipationService;

final class EventParticipationServiceTest
{
    public function test_valid_pair_passes(): void
    {
        $service = $this->service();
        $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
        $this->add_endpoint(201, 'person', 'Mia Botha');

        vgpc_test_same(true, $service->validate_pair(101, 201), 'A valid Event and Person pair must pass validation.');
    }

    public function test_wrong_event_type_is_rejected(): void
    {
        $service = $this->service();
        $this->add_endpoint(101, 'post', 'Article');
        $this->add_endpoint(201, 'person', 'Mia Botha');

        $result = $service->validate_pair(101, 201);

        vgpc_test_true(is_wp_error($result), 'A post of the wrong type must be rejected as an Event.');
        vgpc_test_same('invalid_event', $result->get_error_code(), 'The validation error must identify the invalid Event.');
    }

    public function test_trashed_and_missing_events_are_rejected(): void
    {
        $service = $this->service();
        $this->add_endpoint(101, 'tc_events', 'Trashed Event', 'trash');
        $this->add_endpoint(201, 'person', 'Mia Botha');

        vgpc_test_true(is_wp_error($service->validate_pair(101, 201)), 'A trashed Event must be rejected.');
        vgpc_test_true(is_wp_error($service->validate_pair(102, 201)), 'A missing Event must be rejected.');
    }

    public function test_wrong_person_type_is_rejected(): void
    {
        $service = $this->service();
        $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
        $this->add_endpoint(201, 'medewerker', 'Legacy Profile');

        $result = $service->validate_pair(101, 201);

        vgpc_test_true(is_wp_error($result), 'A post of the wrong type must be rejected as a Person.');
        vgpc_test_same('invalid_person', $result->get_error_code(), 'The validation error must identify the invalid Person.');
    }

    public function test_trashed_and_missing_people_are_rejected(): void
    {
        $service = $this->service();
        $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
        $this->add_endpoint(201, 'person', 'Trashed Person', 'trash');

        vgpc_test_true(is_wp_error($service->validate_pair(101, 201)), 'A trashed Person must be rejected.');
        vgpc_test_true(is_wp_error($service->validate_pair(101, 202)), 'A missing Person must be rejected.');
    }

    public function test_duplicate_pair_in_every_non_retired_status_is_rejected(): void
    {
        foreach (EventParticipationRepository::UNIQUENESS_STATUSES as $status) {
            $service = $this->service();
            $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
            $this->add_endpoint(201, 'person', 'Mia Botha');
            $this->add_participation(301, $status, 101, 201);

            vgpc_test_true(
                is_wp_error($service->validate_pair(101, 201)),
                sprintf('A duplicate pair in %s status must be rejected.', $status)
            );
        }
    }

    public function test_duplicate_trash_and_auto_draft_pairs_do_not_block(): void
    {
        $service = $this->service();
        $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $this->add_participation(301, 'trash', 101, 201);
        $this->add_participation(302, 'auto-draft', 101, 201);

        vgpc_test_same(true, $service->validate_pair(101, 201), 'Retired and auto-draft duplicates must not block the pair.');
    }

    public function test_create_rechecks_pair_after_waiting_for_another_writer(): void
    {
        $service = $this->service();
        $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $simulated_concurrent_commit = false;
        $GLOBALS['wpdb']->get_var_handler = function (string $query) use (&$simulated_concurrent_commit): string {
            if ($query === 'SELECT GET_LOCK(%s, %d)' && ! $simulated_concurrent_commit) {
                // The competing request commits while this request waits for the same named lock.
                $this->add_participation(401, 'publish', 101, 201);
                $simulated_concurrent_commit = true;
            }

            return '1';
        };

        $result = $service->create(101, 201);

        vgpc_test_true(is_wp_error($result), 'A request that acquires the pair lock after a competing commit must reject the duplicate.');
        vgpc_test_same('duplicate_event_participation', $result->get_error_code(), 'The locked recheck must report the duplicate pair.');
        vgpc_test_same([], $GLOBALS['vgpc_test_insert_post_calls'], 'A competing committed pair must prevent a second insert.');
        vgpc_test_same(
            ['SELECT GET_LOCK(%s, %d)', 'SELECT RELEASE_LOCK(%s)'],
            array_column($GLOBALS['wpdb']->queries, 'query'),
            'The service must hold the same pair lock across its duplicate recheck and release it afterward.'
        );
        vgpc_test_same(
            $GLOBALS['wpdb']->queries[0]['args'][0],
            $GLOBALS['wpdb']->queries[1]['args'][0],
            'The service must release the lock it acquired for this Event and Person pair.'
        );
    }

    public function test_create_uses_fresh_database_values_after_cached_precheck(): void
    {
        $service = $this->service();
        $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $GLOBALS['vgpc_test_enable_query_cache'] = true;

        // Warm this request's candidate query before the competing transaction commits.
        vgpc_test_same(true, $service->validate_pair(101, 201), 'The initial request-local check must see no duplicate.');
        $simulated_concurrent_commit = false;
        $GLOBALS['wpdb']->get_var_handler = function (string $query) use (&$simulated_concurrent_commit): string {
            if ($query === 'SELECT GET_LOCK(%s, %d)' && ! $simulated_concurrent_commit) {
                $this->add_participation(401, 'publish', 101, 201);
                $simulated_concurrent_commit = true;
            }

            return '1';
        };

        $result = $service->create(101, 201);

        vgpc_test_true(is_wp_error($result), 'The lock holder must observe a committed duplicate despite warmed request caches.');
        vgpc_test_same('duplicate_event_participation', $result->get_error_code(), 'The fresh duplicate check must reject the pair.');
        vgpc_test_same([], $GLOBALS['vgpc_test_insert_post_calls'], 'A stale cached precheck must not allow a duplicate insert.');
    }

    public function test_create_rechecks_endpoint_status_from_database_after_cached_precheck(): void
    {
        $service = $this->service();
        $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $GLOBALS['vgpc_test_enable_query_cache'] = true;

        vgpc_test_same(true, $service->validate_pair(101, 201), 'The initial request-local endpoint check must pass.');
        $GLOBALS['wpdb']->get_var_handler = function (string $query): string {
            if ($query === 'SELECT GET_LOCK(%s, %d)') {
                // The endpoint changes after this request cached its WordPress post object.
                $this->add_endpoint(201, 'person', 'Mia Botha', 'trash');
            }

            return '1';
        };

        $result = $service->create(101, 201);

        vgpc_test_true(is_wp_error($result), 'The locked check must use the current Person status from the database.');
        vgpc_test_same('invalid_person', $result->get_error_code(), 'A Person trashed after the cached precheck must be rejected.');
        vgpc_test_same([], $GLOBALS['vgpc_test_insert_post_calls'], 'A stale endpoint cache must not permit the insert.');
    }

    public function test_create_fails_closed_when_fresh_duplicate_query_fails(): void
    {
        $service = $this->service();
        $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $GLOBALS['wpdb']->get_col_handler = static function (): array {
            $GLOBALS['wpdb']->last_error = 'Injected fresh-query failure.';
            return [];
        };

        $result = $service->create(101, 201);

        vgpc_test_true(is_wp_error($result), 'A failed authoritative duplicate query must stop creation.');
        vgpc_test_same('participation_fresh_check_failed', $result->get_error_code(), 'The error must identify the fresh-query failure.');
        vgpc_test_same([], $GLOBALS['vgpc_test_insert_post_calls'], 'A database read error must not be treated as an empty result.');
    }

    public function test_create_fails_closed_when_pair_lock_cannot_be_acquired(): void
    {
        $service = $this->service();
        $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $GLOBALS['wpdb']->get_var_handler = static fn (string $query): string => $query === 'SELECT GET_LOCK(%s, %d)' ? '0' : '1';

        $result = $service->create(101, 201);

        vgpc_test_true(is_wp_error($result), 'A create must fail when it cannot establish the pair lock.');
        vgpc_test_same('participation_pair_lock_failed', $result->get_error_code(), 'The error must identify failure to protect pair uniqueness.');
        vgpc_test_same([], $GLOBALS['vgpc_test_insert_post_calls'], 'The service must not create a participation without the pair lock.');
        vgpc_test_same(['SELECT GET_LOCK(%s, %d)'], array_column($GLOBALS['wpdb']->queries, 'query'), 'An unacquired lock must not be released.');
    }

    public function test_negative_display_order_is_rejected_before_insert(): void
    {
        $service = $this->service();
        $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
        $this->add_endpoint(201, 'person', 'Mia Botha');

        $result = $service->create(101, 201, [], -1);

        vgpc_test_true(is_wp_error($result), 'A negative display order must be rejected.');
        vgpc_test_same([], $GLOBALS['vgpc_test_insert_post_calls'], 'Invalid display order must not insert a participation.');
    }

    public function test_unknown_event_role_term_is_rejected_before_insert(): void
    {
        $service = $this->service();
        $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
        $this->add_endpoint(201, 'person', 'Mia Botha');

        $result = $service->create(101, 201, [9999]);

        vgpc_test_true(is_wp_error($result), 'An unknown event_role term must be rejected.');
        vgpc_test_same([], $GLOBALS['vgpc_test_insert_post_calls'], 'An unknown role must not insert a participation.');
    }

    public function test_term_from_another_taxonomy_is_rejected_before_insert(): void
    {
        $service = $this->service();
        $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $other_taxonomy_term = vgpc_test_add_term('Geloof', 'geloof', 0, 'content_topic');

        $result = $service->create(101, 201, [(int) $other_taxonomy_term->term_id]);

        vgpc_test_true(is_wp_error($result), 'A term from another taxonomy must not be accepted as an event role.');
        vgpc_test_same([], $GLOBALS['vgpc_test_insert_post_calls'], 'A term from another taxonomy must not insert a participation.');
    }

    public function test_multiple_valid_event_role_terms_are_accepted(): void
    {
        $service = $this->service();
        $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $speaker = vgpc_test_add_term('Spreker', 'spreker', 0, 'event_role');
        $facilitator = vgpc_test_add_term('Fasiliteerder', 'fasiliteerder', 0, 'event_role');

        $participation_id = $service->create(101, 201, [
            (int) $speaker->term_id,
            (int) $facilitator->term_id,
        ]);

        vgpc_test_true(is_int($participation_id), 'Valid multiple event_role terms must allow a participation to be created.');
        vgpc_test_same(
            [(int) $speaker->term_id, (int) $facilitator->term_id],
            $GLOBALS['vgpc_test_relationships']['event_role'][$participation_id],
            'The complete set of valid roles must be assigned to the participation.'
        );
    }

    public function test_title_for_pair_uses_event_and_person_titles(): void
    {
        $service = $this->service();
        $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
        $this->add_endpoint(201, 'person', 'Mia Botha');

        vgpc_test_same('Vroue-aand — Mia Botha', $service->title_for_pair(101, 201), 'The title must combine the Event and Person titles.');
    }

    public function test_successful_create_uses_acf_fields_native_roles_and_derived_title(): void
    {
        $service = $this->service();
        $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $speaker = vgpc_test_add_term('Spreker', 'spreker', 0, 'event_role');
        $host = vgpc_test_add_term('Gas', 'gas', 0, 'event_role');

        $participation_id = $service->create(101, 201, [
            (int) $speaker->term_id,
            (int) $host->term_id,
        ], 4, 'publish');

        vgpc_test_true(is_int($participation_id), 'A valid participation must be created.');
        $participation = get_post($participation_id);
        vgpc_test_same('event_participation', $participation->post_type, 'The service must create the canonical participation type.');
        vgpc_test_same('publish', $participation->post_status, 'The requested status must be used.');
        vgpc_test_same('Vroue-aand — Mia Botha', $participation->post_title, 'The created post must use the derived title.');
        vgpc_test_same(
            [
                EventParticipationRepository::EVENT_FIELD,
                EventParticipationRepository::PERSON_FIELD,
                EventParticipationRepository::DISPLAY_ORDER_FIELD,
            ],
            array_column($GLOBALS['vgpc_test_update_field_calls'], 'field_name'),
            'The service must write all canonical fields through update_field().'
        );
        vgpc_test_same(
            [101, 201, 4],
            array_column($GLOBALS['vgpc_test_update_field_calls'], 'value'),
            'The service must persist the requested Event, Person, and display order values.'
        );
        vgpc_test_same(
            [(int) $speaker->term_id, (int) $host->term_id],
            $GLOBALS['vgpc_test_relationships']['event_role'][$participation_id],
            'The service must write event roles through the native taxonomy relationship.'
        );
    }

    public function test_create_accepts_each_supported_post_status(): void
    {
        foreach (['publish', 'draft', 'pending', 'private', 'future'] as $status) {
            $service = $this->service();
            $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
            $this->add_endpoint(201, 'person', 'Mia Botha');

            $participation_id = $service->create(101, 201, [], 0, $status);

            vgpc_test_true(is_int($participation_id), sprintf('The %s status must be supported.', $status));
            vgpc_test_same(
                $status,
                get_post($participation_id)->post_status,
                sprintf('The requested %s status must be persisted.', $status)
            );
        }
    }

    public function test_create_rejects_retired_and_internal_statuses_before_insert(): void
    {
        foreach (['trash', 'auto-draft', 'inherit'] as $status) {
            $service = $this->service();
            $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
            $this->add_endpoint(201, 'person', 'Mia Botha');

            $result = $service->create(101, 201, [], 0, $status);

            vgpc_test_true(is_wp_error($result), sprintf('The %s status must be rejected.', $status));
            vgpc_test_same(
                'invalid_participation_status',
                $result->get_error_code(),
                sprintf('The %s status must return the status validation error.', $status)
            );
            vgpc_test_same([], $GLOBALS['vgpc_test_insert_post_calls'], 'An unsupported status must not insert a participation.');
        }
    }

    public function test_post_insert_write_failure_hard_deletes_only_the_new_participation(): void
    {
        foreach (['field', 'taxonomy'] as $failure_point) {
            $service = $this->service();
            $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
            $this->add_endpoint(102, 'tc_events', 'Ander geleentheid');
            $this->add_endpoint(201, 'person', 'Mia Botha');
            $this->add_endpoint(202, 'person', 'Ander persoon');
            $this->add_participation(401, 'draft', 102, 202);
            $role = vgpc_test_add_term('Spreker', 'spreker', 0, 'event_role');

            if ($failure_point === 'field') {
                $GLOBALS['vgpc_test_fail_update_field_name'] = EventParticipationRepository::PERSON_FIELD;
            } else {
                $GLOBALS['vgpc_test_fail_set_term_call'] = 1;
            }

            $result = $service->create(101, 201, [(int) $role->term_id]);

            vgpc_test_true(is_wp_error($result), 'A failed post-insert write must return an error.');
            vgpc_test_true(get_post(401) !== false, 'Rollback must preserve a pre-existing participation.');
            vgpc_test_true(get_post(101) !== false && get_post(201) !== false, 'Rollback must preserve both endpoints.');
            vgpc_test_same(
                [['post_id' => 5000, 'force_delete' => true]],
                $GLOBALS['vgpc_test_delete_post_calls'],
                'Rollback must hard-delete only the participation created by this call.'
            );
            vgpc_test_same([401], array_values(array_filter(
                array_keys($GLOBALS['vgpc_test_posts']),
                static fn (int $post_id): bool => get_post_type($post_id) === 'event_participation'
            )), 'The pre-existing participation must remain and the failed new participation must be gone.');
        }
    }

    private function service(): EventParticipationService
    {
        vgpc_test_reset();
        acf_add_local_field_group([
            'key' => 'group_event_participation_service_test',
            'fields' => [
                ['key' => 'field_event_participation_event', 'name' => EventParticipationRepository::EVENT_FIELD],
                ['key' => 'field_event_participation_person', 'name' => EventParticipationRepository::PERSON_FIELD],
                ['key' => 'field_event_participation_display_order', 'name' => EventParticipationRepository::DISPLAY_ORDER_FIELD],
            ],
        ]);
        register_taxonomy('event_role', [EventParticipationRepository::POST_TYPE]);

        return new EventParticipationService(new EventParticipationRepository());
    }

    private function add_endpoint(int $id, string $post_type, string $title, string $status = 'publish'): void
    {
        $GLOBALS['vgpc_test_posts'][$id] = (object) [
            'ID' => $id,
            'post_type' => $post_type,
            'post_status' => $status,
            'post_title' => $title,
        ];
    }

    private function add_participation(int $id, string $status, int $event_id, int $person_id): void
    {
        $GLOBALS['vgpc_test_posts'][$id] = (object) [
            'ID' => $id,
            'post_type' => EventParticipationRepository::POST_TYPE,
            'post_status' => $status,
            'post_title' => 'Existing participation',
        ];
        $GLOBALS['vgpc_test_meta'][$id][EventParticipationRepository::EVENT_FIELD] = $event_id;
        $GLOBALS['vgpc_test_meta'][$id][EventParticipationRepository::PERSON_FIELD] = $person_id;
    }
}
