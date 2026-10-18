<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Event\EventParticipationAdmin;
use Voelgoed\PlatformCore\Event\EventParticipationRepository;

final class EventParticipationAdminTest
{
    public function test_register_wires_acf_validation_and_save_hooks(): void
    {
        $admin = $this->fixture();
        $admin->register();
        $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $_POST['acf'] = [
            EventParticipationAdmin::EVENT_FIELD_KEY => '101',
            EventParticipationAdmin::PERSON_FIELD_KEY => '201',
        ];
        $_POST['post_ID'] = '300';
        $post_data = ['post_type' => EventParticipationRepository::POST_TYPE, 'post_status' => 'publish'];
        vgpc_test_same(
            $post_data,
            apply_filters('wp_insert_post_data', $post_data, ['ID' => 300]),
            'A valid editor save must pass the pre-write pair check.'
        );

        $event_validation = $this->validate_field(
            EventParticipationAdmin::EVENT_FIELD_KEY,
            101,
            300,
            101,
            201
        );
        $person_validation = $this->validate_field(
            EventParticipationAdmin::PERSON_FIELD_KEY,
            201,
            300,
            101,
            201
        );
        $this->add_participation(300, 'publish', 101, 201, 'Wrong title');
        do_action('acf/save_post', 300);

        vgpc_test_same(true, $event_validation, 'The Event field validation hook must accept a valid pair.');
        vgpc_test_same(true, $person_validation, 'The Person field validation hook must accept a valid pair.');
        vgpc_test_same('Vroue-aand — Mia Botha', get_the_title(300), 'The ACF save hook must derive the participation title.');
        vgpc_test_same(1, count($GLOBALS['vgpc_test_update_post_calls']), 'The ACF save hook must update the participation title once.');
        vgpc_test_same(
            [
                'SELECT GET_LOCK(%s, %d)',
                'SELECT GET_LOCK(%s, %d)',
                'SELECT RELEASE_LOCK(%s)',
                'SELECT RELEASE_LOCK(%s)',
            ],
            array_column($GLOBALS['wpdb']->queries, 'query'),
            'The editor must hold the participation and pair locks across the post and ACF field writes.'
        );
    }

    public function test_admin_save_rechecks_under_lock_before_post_write(): void
    {
        $admin = $this->fixture();
        $admin->register();
        $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $_POST['acf'] = [
            EventParticipationAdmin::EVENT_FIELD_KEY => '101',
            EventParticipationAdmin::PERSON_FIELD_KEY => '201',
        ];
        $simulated_concurrent_commit = false;
        $GLOBALS['wpdb']->get_var_handler = function (string $query) use (&$simulated_concurrent_commit): string {
            if ($query === 'SELECT GET_LOCK(%s, %d)' && ! $simulated_concurrent_commit) {
                // The competing request commits while this editor save waits for the same pair lock.
                $this->add_participation(401, 'publish', 101, 201, 'Competing participation');
                $simulated_concurrent_commit = true;
            }

            return '1';
        };

        $rejected = false;
        try {
            apply_filters(
                'wp_insert_post_data',
                ['post_type' => EventParticipationRepository::POST_TYPE, 'post_status' => 'publish'],
                ['ID' => 0]
            );
        } catch (RuntimeException $error) {
            $rejected = str_contains($error->getMessage(), 'already has a participation');
        }

        vgpc_test_true($rejected, 'A competing committed pair must stop the editor save before the post write.');
        vgpc_test_same([], $GLOBALS['vgpc_test_update_post_calls'], 'A rejected editor save must not update a participation post.');
        do_action('shutdown');
        vgpc_test_same(
            ['SELECT GET_LOCK(%s, %d)', 'SELECT RELEASE_LOCK(%s)'],
            array_column($GLOBALS['wpdb']->queries, 'query'),
            'The held lock must be released after the rejected save ends.'
        );
    }

    public function test_quick_status_save_rechecks_stored_pair_under_lock(): void
    {
        $admin = $this->fixture();
        $admin->register();
        $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $this->add_participation(301, 'draft', 101, 201, 'Existing participation');
        $GLOBALS['wpdb']->get_var_handler = function (string $query): string {
            if ($query === 'SELECT GET_LOCK(%s, %d)') {
                $this->add_participation(401, 'publish', 101, 201, 'Competing participation');
            }

            return '1';
        };

        $rejected = false;
        try {
            apply_filters(
                'wp_insert_post_data',
                ['post_type' => EventParticipationRepository::POST_TYPE, 'post_status' => 'publish'],
                ['ID' => 301]
            );
        } catch (RuntimeException $error) {
            $rejected = str_contains($error->getMessage(), 'already has a participation');
        }

        vgpc_test_true($rejected, 'A status change must not reactivate a pair committed by a competing request.');
        vgpc_test_same('draft', get_post(301)->post_status, 'A rejected status change must leave the current post row unchanged.');
        do_action('shutdown');
    }

    public function test_existing_participation_update_locks_post_before_pair(): void
    {
        $admin = $this->fixture();
        $this->add_endpoint(101, 'tc_events', 'Original Event');
        $this->add_endpoint(102, 'tc_events', 'New Event');
        $this->add_endpoint(201, 'person', 'Original Person');
        $this->add_endpoint(202, 'person', 'New Person');
        $this->add_participation(301, 'draft', 101, 201, 'Existing participation');
        $_POST['acf'] = [
            EventParticipationAdmin::EVENT_FIELD_KEY => '102',
            EventParticipationAdmin::PERSON_FIELD_KEY => '202',
        ];

        $admin->validate_pair_before_post_save(
            ['post_type' => EventParticipationRepository::POST_TYPE, 'post_status' => 'draft'],
            ['ID' => 301]
        );

        $acquisitions = array_values(array_filter(
            $GLOBALS['wpdb']->queries,
            static fn (array $query): bool => $query['query'] === 'SELECT GET_LOCK(%s, %d)'
        ));
        vgpc_test_same(2, count($acquisitions), 'An existing edit must acquire both its post lock and its target pair lock.');
        vgpc_test_true(
            $acquisitions[0]['args'][0] !== $acquisitions[1]['args'][0],
            'The participation lock and Event/Person pair lock must be distinct.'
        );
        vgpc_test_same(
            $acquisitions[0]['args'][0],
            $GLOBALS['wpdb']->queries[0]['args'][0],
            'The participation lock must be acquired before the pair lock.'
        );
    }

    public function test_same_post_edits_and_trash_status_serialize_on_participation_lock(): void
    {
        $first_admin = $this->fixture();
        $second_admin = new EventParticipationAdmin();
        $this->add_endpoint(101, 'tc_events', 'Original Event');
        $this->add_endpoint(102, 'tc_events', 'First target Event');
        $this->add_endpoint(103, 'tc_events', 'Second target Event');
        $this->add_endpoint(201, 'person', 'Original Person');
        $this->add_endpoint(202, 'person', 'First target Person');
        $this->add_endpoint(203, 'person', 'Second target Person');
        $this->add_participation(301, 'draft', 101, 201, 'Existing participation');
        $_POST['acf'] = [
            EventParticipationAdmin::EVENT_FIELD_KEY => '102',
            EventParticipationAdmin::PERSON_FIELD_KEY => '202',
        ];
        $blocked_post_lock = null;
        $blocked_next_post_lock = false;
        $GLOBALS['wpdb']->get_var_handler = function (string $query, array $args = []) use (&$blocked_post_lock, &$blocked_next_post_lock): string {
            if ($query === 'SELECT GET_LOCK(%s, %d)') {
                if ($blocked_next_post_lock && $args[0] === $blocked_post_lock) {
                    return '0';
                }
                return '1';
            }

            return '1';
        };

        $first_admin->validate_pair_before_post_save(
            ['post_type' => EventParticipationRepository::POST_TYPE, 'post_status' => 'draft'],
            ['ID' => 301]
        );
        $blocked_post_lock = $GLOBALS['wpdb']->queries[0]['args'][0] ?? null;
        vgpc_test_true(is_string($blocked_post_lock), 'The first edit must hold a participation-scoped lock.');

        // Simulate ACF persisting the first edit while the second edit and a trash transition overlap.
        $GLOBALS['vgpc_test_meta'][301][EventParticipationRepository::EVENT_FIELD] = 102;
        $GLOBALS['vgpc_test_meta'][301][EventParticipationRepository::PERSON_FIELD] = 202;
        $second_admin->register();
        $_POST['acf'] = [
            EventParticipationAdmin::EVENT_FIELD_KEY => '103',
            EventParticipationAdmin::PERSON_FIELD_KEY => '203',
        ];
        $blocked_next_post_lock = true;
        $second_edit_rejected = false;
        try {
            $second_admin->validate_pair_before_post_save(
                ['post_type' => EventParticipationRepository::POST_TYPE, 'post_status' => 'draft'],
                ['ID' => 301]
            );
        } catch (RuntimeException $error) {
            $second_edit_rejected = str_contains($error->getMessage(), 'Could not reserve this participation');
        }
        vgpc_test_true($second_edit_rejected, 'A concurrent edit to another pair must wait on the same participation lock.');

        $acquisitions = array_values(array_filter(
            $GLOBALS['wpdb']->queries,
            static fn (array $query): bool => $query['query'] === 'SELECT GET_LOCK(%s, %d)'
        ));
        vgpc_test_same(3, count($acquisitions), 'The blocked edit must stop after its post lock attempt and never take another pair lock.');
        vgpc_test_same($blocked_post_lock, $acquisitions[2]['args'][0], 'Both edits of one post must contend on the same participation lock.');

        $first_admin->release_pair_locks();
        $this->add_endpoint(202, 'person', 'First target Person', 'trash');
        $blocked_next_post_lock = false;
        $_POST['acf'] = [];
        $trash_data = ['post_type' => EventParticipationRepository::POST_TYPE, 'post_status' => 'trash'];
        vgpc_test_same(
            $trash_data,
            $second_admin->validate_pair_before_post_save($trash_data, ['ID' => 301]),
            'A trash status update after the edit releases its post lock must proceed despite a trashed endpoint.'
        );
        $acquisitions_after_trash = array_values(array_filter(
            $GLOBALS['wpdb']->queries,
            static fn (array $query): bool => $query['query'] === 'SELECT GET_LOCK(%s, %d)'
        ));
        vgpc_test_same(4, count($acquisitions_after_trash), 'A retirement with an invalid endpoint needs only the participation lock.');
        vgpc_test_same($blocked_post_lock, $acquisitions_after_trash[3]['args'][0], 'Trash must serialize with edits of the same participation.');
        $second_admin->release_pair_locks();
    }

    public function test_trash_allows_trashed_or_missing_endpoints(): void
    {
        $admin = $this->fixture();
        $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
        $this->add_endpoint(201, 'person', 'Trashed Person', 'trash');
        $this->add_participation(301, 'draft', 101, 201, 'Participation with trashed Person');
        $this->add_participation(302, 'draft', 999, 201, 'Participation with missing Event');
        $trash_data = ['post_type' => EventParticipationRepository::POST_TYPE, 'post_status' => 'trash'];

        vgpc_test_same($trash_data, $admin->validate_pair_before_post_save($trash_data, ['ID' => 301]), 'A trashed endpoint must not block retiring its participation.');
        vgpc_test_same($trash_data, $admin->validate_pair_before_post_save($trash_data, ['ID' => 302]), 'A missing endpoint must not block retiring its participation.');
        $acquisitions = array_values(array_filter(
            $GLOBALS['wpdb']->queries,
            static fn (array $query): bool => $query['query'] === 'SELECT GET_LOCK(%s, %d)'
        ));
        vgpc_test_same(2, count($acquisitions), 'Each trash operation must retain the per-participation edit lock.');
    }

    public function test_status_update_reads_stored_endpoints_fresh_after_acf_cache_is_warm(): void
    {
        $admin = $this->fixture();
        $admin->register();
        $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
        $this->add_endpoint(201, 'person', 'Cached Person');
        $this->add_endpoint(202, 'person', 'Current Person', 'trash');
        $this->add_participation(301, 'draft', 101, 201, 'Existing participation');
        $GLOBALS['vgpc_test_enable_query_cache'] = true;

        // Warm ACF's request-local values, then simulate another request updating the stored relationship.
        get_field(EventParticipationRepository::EVENT_FIELD, 301);
        get_field(EventParticipationRepository::PERSON_FIELD, 301);
        $GLOBALS['vgpc_test_meta'][301][EventParticipationRepository::PERSON_FIELD] = 202;
        $_POST['acf'] = [];

        $rejected = false;
        try {
            apply_filters(
                'wp_insert_post_data',
                ['post_type' => EventParticipationRepository::POST_TYPE, 'post_status' => 'pending'],
                ['ID' => 301]
            );
        } catch (RuntimeException $error) {
            $rejected = str_contains($error->getMessage(), 'existing, non-trashed Person');
        }

        vgpc_test_true($rejected, 'A quick status change must use the stored Person endpoint from the database, not stale ACF values.');
        do_action('shutdown');
    }

    public function test_acf_validation_rejects_invalid_event_and_person_endpoints(): void
    {
        $admin = $this->fixture();
        $admin->register();
        $this->add_endpoint(101, 'post', 'Not an Event');
        $this->add_endpoint(201, 'medewerker', 'Legacy profile');

        $invalid_event = $this->validate_field(
            EventParticipationAdmin::EVENT_FIELD_KEY,
            101,
            300,
            101,
            201
        );
        $invalid_person = $this->validate_field(
            EventParticipationAdmin::PERSON_FIELD_KEY,
            201,
            300,
            101,
            201
        );

        vgpc_test_true(is_string($invalid_event), 'ACF must reject an endpoint that is not a Tickera Event.');
        vgpc_test_true(is_string($invalid_person), 'ACF must reject an endpoint that is not a canonical Person.');
    }

    public function test_acf_validation_rejects_duplicate_pairs_but_excludes_current_participation(): void
    {
        $admin = $this->fixture();
        $admin->register();
        $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $this->add_participation(300, 'draft', 101, 201, 'Current row');

        $current_row_validation = $this->validate_field(
            EventParticipationAdmin::EVENT_FIELD_KEY,
            101,
            300,
            101,
            201
        );
        $other_row_validation = $this->validate_field(
            EventParticipationAdmin::PERSON_FIELD_KEY,
            201,
            301,
            101,
            201
        );

        vgpc_test_same(true, $current_row_validation, 'Editing the existing pair must exclude its own participation ID.');
        vgpc_test_true(is_string($other_row_validation), 'A second row with the same Event and Person must be rejected.');
    }

    public function test_event_title_changes_update_non_retired_related_participations(): void
    {
        $admin = $this->fixture();
        $admin->register();
        $this->add_endpoint(101, 'tc_events', 'Old Event title');
        $this->add_endpoint(102, 'tc_events', 'Other Event');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        foreach (['publish', 'draft', 'pending', 'private', 'future', 'trash', 'auto-draft'] as $index => $status) {
            $this->add_participation(300 + $index, $status, 101, 201, 'Old derived title');
        }
        $this->add_participation(320, 'publish', 102, 201, 'Unrelated Event title');

        wp_update_post(['ID' => 101, 'post_title' => 'New Event title'], true);

        foreach (['publish', 'draft', 'pending', 'private', 'future'] as $index => $status) {
            vgpc_test_same(
                'New Event title — Mia Botha',
                get_the_title(300 + $index),
                sprintf('The %s participation title must follow its Event title.', $status)
            );
        }
        vgpc_test_same('Old derived title', get_the_title(305), 'A trashed participation title must remain untouched.');
        vgpc_test_same('Old derived title', get_the_title(306), 'An auto-draft participation title must remain untouched.');
        vgpc_test_same('Unrelated Event title', get_the_title(320), 'A participation for another Event must remain untouched.');
        vgpc_test_same(6, count($GLOBALS['vgpc_test_update_post_calls']), 'Only the Event and five non-retired related participations must be updated.');
    }

    public function test_person_title_changes_update_related_participations(): void
    {
        $admin = $this->fixture();
        $admin->register();
        $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
        $this->add_endpoint(201, 'person', 'Old Person title');
        $this->add_participation(300, 'publish', 101, 201, 'Old derived title');

        wp_update_post(['ID' => 201, 'post_title' => 'New Person title'], true);

        vgpc_test_same('Vroue-aand — New Person title', get_the_title(300), 'The participation title must follow its Person title.');
        vgpc_test_same(2, count($GLOBALS['vgpc_test_update_post_calls']), 'The Person and related participation must each be updated once.');
    }

    public function test_title_sync_rechecks_status_after_waiting_for_participation_lock(): void
    {
        $admin = $this->fixture();
        $admin->register();
        $this->add_endpoint(101, 'tc_events', 'Old Event title');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $this->add_participation(301, 'publish', 101, 201, 'Old derived title');
        $GLOBALS['wpdb']->get_var_handler = function (string $query, array $args = []): string {
            if ($query === 'SELECT GET_LOCK(%s, %d)') {
                // A competing request completes its trash transition before title sync gets the lock.
                $GLOBALS['vgpc_test_posts'][301]->post_status = 'trash';
            }

            return '1';
        };

        wp_update_post(['ID' => 101, 'post_title' => 'New Event title'], true);

        vgpc_test_same('trash', get_post_status(301), 'A title update must not restore a participation trashed while lock acquisition waited.');
        vgpc_test_same('Old derived title', get_the_title(301), 'A trashed participation must not receive a stale title update.');
        vgpc_test_same(1, count($GLOBALS['vgpc_test_update_post_calls']), 'Only the Event update must reach WordPress.');
        do_action('shutdown');
    }

    public function test_title_sync_uses_current_pair_after_waiting_for_participation_lock(): void
    {
        $admin = $this->fixture();
        $admin->register();
        $this->add_endpoint(101, 'tc_events', 'Old Event title');
        $this->add_endpoint(102, 'tc_events', 'Current Event title');
        $this->add_endpoint(201, 'person', 'Old Person');
        $this->add_endpoint(202, 'person', 'Current Person');
        $this->add_participation(301, 'publish', 101, 201, 'Old derived title');
        $GLOBALS['vgpc_test_enable_query_cache'] = true;
        get_field(EventParticipationRepository::EVENT_FIELD, 301);
        get_field(EventParticipationRepository::PERSON_FIELD, 301);
        $GLOBALS['wpdb']->get_var_handler = function (string $query, array $args = []): string {
            if ($query === 'SELECT GET_LOCK(%s, %d)') {
                // A competing edit changes the pair before title sync gets the lock.
                $GLOBALS['vgpc_test_meta'][301][EventParticipationRepository::EVENT_FIELD] = 102;
                $GLOBALS['vgpc_test_meta'][301][EventParticipationRepository::PERSON_FIELD] = 202;
            }

            return '1';
        };

        wp_update_post(['ID' => 101, 'post_title' => 'Renamed Event'], true);

        vgpc_test_same('Current Event title — Current Person', get_the_title(301), 'Title sync must derive its title from the current endpoints after acquiring the participation lock.');
        vgpc_test_same(102, get_field(EventParticipationRepository::EVENT_FIELD, 301), 'Title sync must preserve the current Event endpoint.');
        vgpc_test_same(202, get_field(EventParticipationRepository::PERSON_FIELD, 301), 'Title sync must preserve the current Person endpoint.');
        do_action('shutdown');
    }

    public function test_nested_acf_save_does_not_release_outer_title_sync_locks(): void
    {
        $admin = $this->fixture();
        $admin->register();
        $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $this->add_participation(301, 'draft', 101, 201, 'Old derived title');
        $admin->validate_pair_before_post_save(
            ['post_type' => EventParticipationRepository::POST_TYPE, 'post_status' => 'draft'],
            ['ID' => 301]
        );
        $GLOBALS['vgpc_test_acf_save_during_update'] = true;

        do_action('acf/save_post', 301);

        vgpc_test_same([0], $GLOBALS['vgpc_test_acf_save_release_counts'], 'A nested ACF save must leave the outer title update locks held.');
        vgpc_test_same('Vroue-aand — Mia Botha', get_the_title(301), 'Title sync must still update the derived title.');
        $release_count = count(array_filter(
            $GLOBALS['wpdb']->queries,
            static fn (array $query): bool => $query['query'] === 'SELECT RELEASE_LOCK(%s)'
        ));
        vgpc_test_same(2, $release_count, 'The outer ACF save must release its participation and pair locks after title sync returns.');
    }

    public function test_title_sync_releases_new_locks_in_reverse_order(): void
    {
        $admin = $this->fixture();
        $admin->register();
        $this->add_endpoint(101, 'tc_events', 'Old Event title');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $this->add_participation(301, 'publish', 101, 201, 'Old derived title');
        $GLOBALS['vgpc_test_apply_wp_update_filter'] = true;

        wp_update_post(['ID' => 101, 'post_title' => 'New Event title'], true);

        $lock_queries = array_values(array_filter(
            $GLOBALS['wpdb']->queries,
            static fn (array $query): bool => in_array($query['query'], [
                'SELECT GET_LOCK(%s, %d)',
                'SELECT RELEASE_LOCK(%s)',
            ], true)
        ));
        vgpc_test_same(
            [
                'SELECT GET_LOCK(%s, %d)',
                'SELECT GET_LOCK(%s, %d)',
                'SELECT RELEASE_LOCK(%s)',
                'SELECT RELEASE_LOCK(%s)',
            ],
            array_column($lock_queries, 'query'),
            'Title sync must acquire both locks and release them after the update.'
        );
        vgpc_test_same($lock_queries[0]['args'][0], $lock_queries[3]['args'][0], 'The participation lock must be released last.');
        vgpc_test_same($lock_queries[1]['args'][0], $lock_queries[2]['args'][0], 'The target pair lock must be released first.');
        vgpc_test_same('New Event title — Mia Botha', get_the_title(301), 'The title update must persist after its locks are released.');
    }

    public function test_unrelated_post_type_does_not_mutate_participations(): void
    {
        $admin = $this->fixture();
        $admin->register();
        $this->add_endpoint(101, 'tc_events', 'Vroue-aand');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $this->add_participation(300, 'publish', 101, 201, 'Stored title');
        $before = clone get_post(300);
        $article_before = (object) ['ID' => 401, 'post_type' => 'post', 'post_title' => 'Old article title'];
        $article_after = (object) ['ID' => 401, 'post_type' => 'post', 'post_title' => 'New article title'];

        do_action('post_updated', 401, $article_after, $article_before);

        vgpc_test_same(get_object_vars($before), get_object_vars(get_post(300)), 'An unrelated post update must not change participation data.');
        vgpc_test_same([], $GLOBALS['vgpc_test_update_post_calls'], 'An unrelated post update must not trigger a title write.');
    }

    private function fixture(): EventParticipationAdmin
    {
        vgpc_test_reset();
        acf_add_local_field_group([
            'key' => 'group_vg_event_participation_admin_test',
            'fields' => [
                ['key' => EventParticipationAdmin::EVENT_FIELD_KEY, 'name' => EventParticipationRepository::EVENT_FIELD],
                ['key' => EventParticipationAdmin::PERSON_FIELD_KEY, 'name' => EventParticipationRepository::PERSON_FIELD],
            ],
        ]);
        register_taxonomy(EventParticipationRepository::ROLE_TAXONOMY, [EventParticipationRepository::POST_TYPE]);

        return new EventParticipationAdmin();
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

    private function add_participation(int $id, string $status, int $event_id, int $person_id, string $title): void
    {
        $GLOBALS['vgpc_test_posts'][$id] = (object) [
            'ID' => $id,
            'post_type' => EventParticipationRepository::POST_TYPE,
            'post_status' => $status,
            'post_title' => $title,
        ];
        $GLOBALS['vgpc_test_meta'][$id][EventParticipationRepository::EVENT_FIELD] = $event_id;
        $GLOBALS['vgpc_test_meta'][$id][EventParticipationRepository::PERSON_FIELD] = $person_id;
    }

    private function validate_field(string $field_key, int $value, int $post_id, int $event_id, int $person_id): mixed
    {
        $_POST['post_ID'] = (string) $post_id;
        $_POST['acf'] = [
            EventParticipationAdmin::EVENT_FIELD_KEY => (string) $event_id,
            EventParticipationAdmin::PERSON_FIELD_KEY => (string) $person_id,
        ];

        return apply_filters('acf/validate_value/key=' . $field_key, true, $value, ['key' => $field_key], 'acf[' . $field_key . ']');
    }
}
