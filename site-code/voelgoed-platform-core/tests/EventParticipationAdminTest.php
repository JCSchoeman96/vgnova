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
