<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Event\EventParticipationRepository;

final class EventParticipationRepositoryTest
{
    public function test_repository_constants_match_the_canonical_contract(): void
    {
        $this->repository();

        vgpc_test_same('event_participation', EventParticipationRepository::POST_TYPE, 'The repository must use the canonical participation post type.');
        vgpc_test_same('event_participation_event', EventParticipationRepository::EVENT_FIELD, 'The repository must use the canonical Event field.');
        vgpc_test_same('event_participation_person', EventParticipationRepository::PERSON_FIELD, 'The repository must use the canonical Person field.');
        vgpc_test_same('event_participation_display_order', EventParticipationRepository::DISPLAY_ORDER_FIELD, 'The repository must use the canonical display order field.');
        vgpc_test_same('event_role', EventParticipationRepository::ROLE_TAXONOMY, 'The repository must use the canonical role taxonomy.');
        vgpc_test_same('publish', EventParticipationRepository::ACTIVE_STATUS, 'Published participations must be active.');
        vgpc_test_same(['publish', 'draft', 'pending', 'private', 'future'], EventParticipationRepository::UNIQUENESS_STATUSES, 'Duplicate checks must cover all non-retired statuses.');
    }

    public function test_is_valid_event_accepts_only_existing_non_trashed_tc_events(): void
    {
        $repository = $this->repository();
        $this->add_post(101, 'tc_events');
        $this->add_post(102, 'tc_events', 'trash');
        $this->add_post(103, 'post');

        vgpc_test_true($repository->is_valid_event(101), 'An existing published Tickera Event must be valid.');
        vgpc_test_true(! $repository->is_valid_event(102), 'A trashed Tickera Event must be invalid.');
        vgpc_test_true(! $repository->is_valid_event(103), 'A post of another type must not be a valid Event.');
        vgpc_test_true(! $repository->is_valid_event(104), 'A missing post must not be a valid Event.');
        vgpc_test_true(! $repository->is_valid_event(0), 'A non-positive ID must not be a valid Event.');
    }

    public function test_is_valid_person_accepts_only_existing_non_trashed_people(): void
    {
        $repository = $this->repository();
        $this->add_post(201, 'person');
        $this->add_post(202, 'person', 'trash');
        $this->add_post(203, 'medewerker');

        vgpc_test_true($repository->is_valid_person(201), 'An existing published Person must be valid.');
        vgpc_test_true(! $repository->is_valid_person(202), 'A trashed Person must be invalid.');
        vgpc_test_true(! $repository->is_valid_person(203), 'A post of another type must not be a valid Person.');
        vgpc_test_true(! $repository->is_valid_person(204), 'A missing post must not be a valid Person.');
        vgpc_test_true(! $repository->is_valid_person(0), 'A non-positive ID must not be a valid Person.');
    }

    public function test_participations_for_event_returns_published_valid_rows_in_display_order(): void
    {
        $repository = $this->repository();
        $this->add_post(101, 'tc_events');
        $this->add_post(201, 'person');
        $this->add_participation(301, 'publish', 101, 201, 2);
        $this->add_participation(302, 'publish', 101, 201, 1);
        $this->add_participation(303, 'publish', 101, 201, 1);
        $this->add_participation(304, 'draft', 101, 201, 0);
        $this->add_participation(305, 'future', 101, 201, 0);
        $this->add_participation(306, 'publish', 101, 999, 0);

        vgpc_test_same([302, 303, 301], $repository->participations_for_event(101), 'Event participations must include only published valid rows ordered by display order and ID.');
    }

    public function test_participations_for_person_returns_published_rows_only(): void
    {
        $repository = $this->repository();
        $this->add_post(101, 'tc_events');
        $this->add_post(201, 'person');
        $this->add_participation(301, 'publish', 101, 201, 0);
        $this->add_participation(302, 'draft', 101, 201, 0);
        $this->add_participation(303, 'pending', 101, 201, 0);

        vgpc_test_same([301], $repository->participations_for_person(201), 'Person participations must include only published rows.');
    }

    public function test_people_for_event_returns_unique_valid_person_ids_only(): void
    {
        $repository = $this->repository();
        $this->add_post(101, 'tc_events');
        $this->add_post(201, 'person');
        $this->add_post(202, 'medewerker');
        $this->add_post(203, 'person', 'trash');
        $this->add_participation(301, 'publish', 101, 201, 1);
        $this->add_participation(302, 'publish', 101, '201', 2);
        $this->add_participation(303, 'publish', 101, 202, 0);
        $this->add_participation(304, 'publish', 101, 203, 0);
        $this->add_participation(305, 'publish', 101, 999, 0);

        vgpc_test_same([201], $repository->people_for_event(101), 'An Event must expose each valid Person ID once and omit invalid endpoints.');
    }

    public function test_events_for_person_returns_valid_tc_event_ids_in_relationship_order(): void
    {
        $repository = $this->repository();
        $this->add_post(101, 'tc_events');
        $this->add_post(102, 'tc_events');
        $this->add_post(103, 'tc_events', 'trash');
        $this->add_post(104, 'post');
        $this->add_post(201, 'person');
        $this->add_participation(301, 'publish', 101, 201, 2);
        $this->add_participation(302, 'publish', 102, 201, 1);
        $this->add_participation(303, 'publish', 103, 201, 0);
        $this->add_participation(304, 'publish', 104, 201, 0);
        $this->add_participation(305, 'publish', 999, 201, 0);

        vgpc_test_same([102, 101], $repository->events_for_person(201), 'A Person must see valid Events ordered by display order and participation ID.');
    }

    public function test_display_order_for_pair_returns_order_from_the_first_active_valid_pair(): void
    {
        $repository = $this->repository();
        $this->add_post(101, 'tc_events');
        $this->add_post(201, 'person');
        $this->add_participation(301, 'publish', 101, 201, 8);
        $this->add_participation(302, 'publish', 101, 201, 3);
        $this->add_participation(303, 'draft', 101, 201, 0);

        vgpc_test_same(3, $this->display_order_for_pair($repository, 101, 201), 'The method must return display order from the first active pair under display-order and row-ID ordering.');
    }

    public function test_display_order_for_pair_returns_null_when_pair_is_missing_or_trashed(): void
    {
        $repository = $this->repository();
        $this->add_post(101, 'tc_events');
        $this->add_post(201, 'person');
        $this->add_participation(301, 'trash', 101, 201, 8);

        vgpc_test_same(null, $this->display_order_for_pair($repository, 101, 201), 'A trashed participation must not supply active Event order.');
        vgpc_test_same(null, $this->display_order_for_pair($repository, 101, 202), 'A missing pair must return null.');
    }

    public function test_display_order_for_pair_returns_null_for_invalid_endpoints(): void
    {
        $repository = $this->repository();
        $this->add_post(101, 'tc_events', 'trash');
        $this->add_post(201, 'medewerker');
        $this->add_participation(301, 'publish', 101, 201, 8);

        vgpc_test_same(null, $this->display_order_for_pair($repository, 101, 201), 'A published participation with a trashed Event and wrong-type Person must not be active.');
        vgpc_test_same(null, $this->display_order_for_pair($repository, 0, 201), 'A non-positive Event endpoint must fail closed.');
        vgpc_test_same(null, $this->display_order_for_pair($repository, 101, 0), 'A non-positive Person endpoint must fail closed.');
    }

    public function test_participation_queries_omit_malformed_endpoint_relationships(): void
    {
        $repository = $this->repository();
        $this->add_post(101, 'tc_events');
        $this->add_post(102, 'post');
        $this->add_post(201, 'person');
        $this->add_post(202, 'person', 'trash');
        $this->add_participation(301, 'publish', 101, 201, 0);
        $this->add_participation(302, 'publish', 101, 999, 0);
        $this->add_participation(303, 'publish', 101, 202, 0);
        $this->add_participation(304, 'publish', 102, 201, 0);
        $this->add_participation(305, 'publish', 999, 201, 0);

        vgpc_test_same([301], $repository->participations_for_event(101), 'An Event query must omit participations with missing or invalid Person endpoints.');
        vgpc_test_same([301], $repository->participations_for_person(201), 'A Person query must omit participations with missing or invalid Event endpoints.');
        vgpc_test_same([], $repository->participations_for_event(102), 'A wrong-type Event argument must return no participations.');
    }

    public function test_find_pair_ids_includes_all_non_retired_uniqueness_statuses(): void
    {
        $repository = $this->repository();
        $statuses = ['publish', 'draft', 'pending', 'private', 'future'];
        $this->add_post(101, 'tc_events');
        $this->add_post(201, 'person');
        foreach ($statuses as $offset => $status) {
            $this->add_participation(301 + $offset, $status, 101, 201, 0);
        }

        vgpc_test_same([301, 302, 303, 304, 305], $repository->find_pair_ids(101, 201), 'Duplicate checks must return the pair in every non-retired status.');
    }

    public function test_find_pair_ids_ignores_trash_and_auto_draft(): void
    {
        $repository = $this->repository();
        $this->add_participation(301, 'trash', 101, 201, 0);
        $this->add_participation(302, 'auto-draft', 101, 201, 0);
        $this->add_participation(303, 'publish', 101, 202, 0);

        vgpc_test_same([], $repository->find_pair_ids(101, 201), 'Retired, auto-draft, and different pairs must not block uniqueness.');
    }

    public function test_find_pair_ids_excludes_the_participation_being_edited(): void
    {
        $repository = $this->repository();
        $this->add_participation(301, 'publish', 101, 201, 0);
        $this->add_participation(302, 'draft', 101, 201, 0);

        vgpc_test_same([302], $repository->find_pair_ids(101, 201, 301), 'An edited participation must not conflict with itself.');
    }

    public function test_fresh_pair_lookup_skips_a_candidate_that_disappeared_without_read_error(): void
    {
        $repository = $this->repository();
        $GLOBALS['wpdb']->get_col_handler = static fn (): array => [301];

        vgpc_test_same([], $repository->find_pair_ids_fresh(101, 201), 'A candidate deleted between ID lookup and post read must not be treated as a duplicate.');
    }

    public function test_live_participation_queries_include_non_retired_statuses(): void
    {
        $repository = $this->repository();
        $this->add_post(101, 'tc_events');
        $this->add_post(201, 'person');
        $this->add_participation(301, 'publish', 101, 201, 2);
        $this->add_participation(302, 'draft', 101, 201, 1);
        $this->add_participation(303, 'trash', 101, 201, 0);

        vgpc_test_same([302, 301], $repository->live_participations_for_event(101), 'Event administrative queries must include all non-retired valid rows.');
        vgpc_test_same([302, 301], $repository->live_participations_for_person(201), 'Person administrative queries must include all non-retired valid rows.');
    }

    private function repository(): EventParticipationRepository
    {
        vgpc_test_reset();
        acf_add_local_field_group([
            'key' => 'group_event_participation_repository_test',
            'fields' => [
                ['key' => 'field_event_participation_event', 'name' => 'event_participation_event'],
                ['key' => 'field_event_participation_person', 'name' => 'event_participation_person'],
                ['key' => 'field_event_participation_display_order', 'name' => 'event_participation_display_order'],
            ],
        ]);

        return new EventParticipationRepository();
    }

    private function add_post(int $id, string $post_type, string $post_status = 'publish'): void
    {
        $GLOBALS['vgpc_test_posts'][$id] = (object) [
            'ID' => $id,
            'post_type' => $post_type,
            'post_status' => $post_status,
        ];
    }

    private function add_participation(
        int $id,
        string $post_status,
        int|string $event_id,
        int|string $person_id,
        int $display_order
    ): void {
        $this->add_post($id, 'event_participation', $post_status);
        $GLOBALS['vgpc_test_meta'][$id]['event_participation_event'] = $event_id;
        $GLOBALS['vgpc_test_meta'][$id]['event_participation_person'] = $person_id;
        $GLOBALS['vgpc_test_meta'][$id]['event_participation_display_order'] = $display_order;
    }

    private function display_order_for_pair(EventParticipationRepository $repository, int $event_id, int $person_id): ?int
    {
        vgpc_test_true(method_exists($repository, 'display_order_for_pair'), 'EventParticipationRepository::display_order_for_pair() is not implemented yet.');

        return $repository->display_order_for_pair($event_id, $person_id);
    }
}
