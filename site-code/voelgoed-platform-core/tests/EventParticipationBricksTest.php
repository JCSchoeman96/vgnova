<?php
declare(strict_types=1);

use Bricks\Database;
use Voelgoed\PlatformCore\Event\EventParticipationBricks;

final class EventParticipationBricksTest
{
    public function test_unmarked_query_is_returned_unchanged(): void
    {
        $this->fixture();
        $adapter = $this->adapter();
        $query_vars = ['post_type' => 'post', 'posts_per_page' => 6];

        vgpc_test_same(
            $query_vars,
            $adapter->filter_participation_query_vars($query_vars, ['_cssClasses' => 'unrelated vg-event-participation-query-suffix'], 'x1', 'posts'),
            'An unmarked Bricks query must remain unchanged.'
        );
    }

    public function test_marked_query_without_event_context_fails_closed(): void
    {
        $this->fixture();

        $query_vars = $this->adapter()->filter_participation_query_vars(
            ['post_type' => 'event_participation'],
            ['_cssClasses' => 'some-existing-class vg-event-participation-query another-class'],
            'x1',
            'posts'
        );

        vgpc_test_same([0], $query_vars['post__in'] ?? null, 'A marked query without Event context must never query every participation.');
    }

    public function test_marked_query_with_non_event_context_fails_closed(): void
    {
        $this->fixture();
        $this->add_post(900, 'page');
        Database::$page_data = ['preview_or_post_id' => 900];

        $query_vars = $this->adapter()->filter_participation_query_vars(
            ['post_type' => 'event_participation'],
            ['_cssClasses' => 'vg-event-participation-query'],
            'x1',
            'posts'
        );

        vgpc_test_same([0], $query_vars['post__in'] ?? null, 'A marked query in a non-Event context must return a guaranteed-empty query.');
    }

    public function test_marked_event_query_delegates_membership_to_repository(): void
    {
        $this->fixture();
        $this->add_event(101);
        $this->add_event(102);
        $this->add_person(201);
        $this->add_participation(1305, 101, 201, 1);
        Database::$page_data = ['preview_or_post_id' => 101];
        $GLOBALS['vgpc_bricks_test_is_singular'] = true;
        $GLOBALS['vgpc_bricks_test_queried_object'] = $GLOBALS['vgpc_test_posts'][102];

        $query_vars = $this->adapter()->filter_participation_query_vars(
            ['post_type' => 'post'],
            ['_cssClasses' => 'vg-event-participation-query'],
            'x1',
            'posts'
        );

        vgpc_test_same([1305], $query_vars['post__in'] ?? null, 'The adapter must use the repository result for the current Event.');
        vgpc_test_same('event_participation', $query_vars['post_type'] ?? null, 'The marked query must target Event Participation posts.');
        vgpc_test_same('publish', $query_vars['post_status'] ?? null, 'The marked query must include only published participations.');
    }

    public function test_frontend_event_query_resolves_the_queried_object_without_bricks_preview(): void
    {
        $this->fixture();
        $this->add_event(101);
        $this->add_person(201);
        $this->add_participation(1305, 101, 201, 1);
        $GLOBALS['vgpc_bricks_test_is_singular'] = true;
        $GLOBALS['vgpc_bricks_test_queried_object'] = $GLOBALS['vgpc_test_posts'][101];

        $query_vars = $this->adapter()->filter_participation_query_vars(
            [],
            ['_cssClasses' => 'vg-event-participation-query'],
            'x1',
            'posts'
        );

        vgpc_test_same([1305], $query_vars['post__in'] ?? null, 'A frontend Event request must resolve the queried Event when Bricks has no preview ID.');
    }

    public function test_repository_order_is_preserved_with_post_in_ordering(): void
    {
        $this->fixture();
        $this->add_event(101);
        $this->add_person(201);
        $this->add_participation(1305, 101, 201, 10);
        $this->add_participation(1302, 101, 201, 20);
        $this->add_participation(1310, 101, 201, 30);
        Database::$page_data = ['preview_or_post_id' => 101];

        $query_vars = $this->adapter()->filter_participation_query_vars(
            [
                'post_type' => 'medewerker',
                'post_status' => 'any',
                'posts_per_page' => 6,
                'orderby' => 'menu_order',
                'order' => 'DESC',
                'meta_key' => 'event_medewerkers',
                'meta_query' => [['key' => 'event_medewerkers', 'value' => '753']],
                'tax_query' => [['taxonomy' => 'event_category', 'terms' => [16]]],
            ],
            ['_cssClasses' => 'some-existing-class vg-event-participation-query another-class'],
            'x1',
            'posts'
        );

        vgpc_test_same([
            'post_type' => 'event_participation',
            'post_status' => 'publish',
            'post__in' => [1305, 1302, 1310],
            'orderby' => 'post__in',
            'posts_per_page' => -1,
        ], $query_vars, 'The marked query must discard Bricks-inherited constraints and use only the repository-owned contract.');
    }

    public function test_empty_repository_result_cannot_query_all_participations(): void
    {
        $this->fixture();
        $this->add_event(101);
        Database::$page_data = ['preview_or_post_id' => 101];

        $query_vars = $this->adapter()->filter_participation_query_vars(
            ['post_type' => 'event_participation', 'post__in' => []],
            ['_cssClasses' => 'vg-event-participation-query'],
            'x1',
            'posts'
        );

        vgpc_test_same([0], $query_vars['post__in'] ?? null, 'An empty repository result must be replaced by a guaranteed-empty ID list.');
    }

    public function test_marked_frontend_section_renders_when_repository_has_ids(): void
    {
        $this->fixture();
        $this->add_event(101);
        $this->add_person(201);
        $this->add_participation(1305, 101, 201, 1);
        Database::$page_data = ['preview_or_post_id' => 101];

        vgpc_test_same(
            true,
            $this->adapter()->filter_participant_section_render(true, $this->section('existing-class vg-event-participants-section another-class')),
            'A marked section must render when canonical participations exist.'
        );
    }

    public function test_marked_frontend_section_is_omitted_when_repository_is_empty(): void
    {
        $this->fixture();
        $this->add_event(101);
        Database::$page_data = ['preview_or_post_id' => 101];

        vgpc_test_same(
            false,
            $this->adapter()->filter_participant_section_render(true, $this->section('vg-event-participants-section')),
            'A marked frontend section must be omitted when the repository returns no IDs.'
        );
    }

    public function test_builder_keeps_an_empty_marked_section_editable(): void
    {
        $this->fixture();
        $GLOBALS['vgpc_bricks_test_is_builder'] = true;

        vgpc_test_same(
            true,
            $this->adapter()->filter_participant_section_render(true, $this->section('vg-event-participants-section')),
            'An empty marked section must remain visible in Bricks builder context.'
        );
        $GLOBALS['vgpc_bricks_test_is_builder'] = false;
        $GLOBALS['vgpc_bricks_test_is_builder_call'] = true;
        vgpc_test_same(
            true,
            $this->adapter()->filter_participant_section_render(true, $this->section('vg-event-participants-section')),
            'An empty marked section must remain visible during Bricks builder requests.'
        );
    }

    public function test_unrelated_section_render_decision_is_unchanged(): void
    {
        $this->fixture();
        $adapter = $this->adapter();
        $section = $this->section('vg-event-participants-section-extra');

        vgpc_test_same(true, $adapter->filter_participant_section_render(true, $section), 'An unrelated section that should render must remain renderable.');
        vgpc_test_same(false, $adapter->filter_participant_section_render(false, $section), 'An unrelated section that should not render must remain omitted.');
    }

    public function test_register_adds_bricks_hooks_with_expected_argument_counts(): void
    {
        $this->fixture();
        $adapter = $this->adapter();
        $adapter->register();

        vgpc_test_same(4, $GLOBALS['vgpc_test_filters']['bricks/posts/query_vars'][0]['accepted_args'] ?? null, 'The query filter must accept four Bricks arguments.');
        vgpc_test_same(2, $GLOBALS['vgpc_test_filters']['bricks/element/render'][0]['accepted_args'] ?? null, 'The section filter must accept two Bricks arguments.');
    }

    private function fixture(): void
    {
        vgpc_test_reset();
        Database::$page_data = [];
        $GLOBALS['vgpc_bricks_test_queried_object'] = null;
        $GLOBALS['vgpc_bricks_test_current_post_id'] = 0;
        $GLOBALS['vgpc_bricks_test_is_singular'] = false;
        $GLOBALS['vgpc_bricks_test_is_builder'] = false;
        $GLOBALS['vgpc_bricks_test_is_builder_call'] = false;

        acf_add_local_field_group([
            'key' => 'group_event_participation_bricks_test',
            'fields' => [
                ['key' => 'field_event_participation_event', 'name' => 'event_participation_event'],
                ['key' => 'field_event_participation_person', 'name' => 'event_participation_person'],
                ['key' => 'field_event_participation_display_order', 'name' => 'event_participation_display_order'],
            ],
        ]);
    }

    private function adapter(): EventParticipationBricks
    {
        if (! class_exists(EventParticipationBricks::class)) {
            throw new RuntimeException('EventParticipationBricks has not been implemented.');
        }

        return new EventParticipationBricks();
    }

    private function add_event(int $id): void
    {
        $this->add_post($id, 'tc_events');
    }

    private function add_person(int $id): void
    {
        $this->add_post($id, 'person');
    }

    private function add_post(int $id, string $post_type, string $post_status = 'publish'): void
    {
        $GLOBALS['vgpc_test_posts'][$id] = (object) [
            'ID' => $id,
            'post_type' => $post_type,
            'post_status' => $post_status,
        ];
    }

    private function add_participation(int $id, int $event_id, int $person_id, int $display_order): void
    {
        $this->add_post($id, 'event_participation');
        $GLOBALS['vgpc_test_meta'][$id]['event_participation_event'] = $event_id;
        $GLOBALS['vgpc_test_meta'][$id]['event_participation_person'] = $person_id;
        $GLOBALS['vgpc_test_meta'][$id]['event_participation_display_order'] = $display_order;
    }

    private function section(string $class_token): object
    {
        return (object) ['settings' => ['_cssClasses' => $class_token]];
    }
}
