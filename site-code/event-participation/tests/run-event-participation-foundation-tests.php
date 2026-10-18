<?php
declare(strict_types=1);

function event_participation_test_assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . PHP_EOL
            . 'Expected: ' . var_export($expected, true) . PHP_EOL
            . 'Actual: ' . var_export($actual, true)
        );
    }
}

function event_participation_test_load_json(string $filename): array
{
    $path = dirname(__DIR__, 2) . '/acf-json/' . $filename;
    if (! is_file($path)) {
        throw new RuntimeException('Missing Task 6A ACF JSON file: ' . $path);
    }

    $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($data)) {
        throw new RuntimeException('ACF Local JSON file must contain an object: ' . $path);
    }

    return $data;
}

$tests = [
    'event participation is a non-public title-only post type' => static function (): void {
        $config = event_participation_test_load_json('post_type_event_participation.json');

        event_participation_test_assert_same('post_type_event_participation', $config['key'] ?? null, 'The CPT key must be stable.');
        event_participation_test_assert_same('event_participation', $config['post_type'] ?? null, 'The CPT slug must be canonical.');
        event_participation_test_assert_same('Event Participation', $config['labels']['singular_name'] ?? null, 'The singular editor label must be canonical.');
        event_participation_test_assert_same('Event Participations', $config['labels']['name'] ?? null, 'The plural editor label must be canonical.');
        event_participation_test_assert_same(false, $config['public'] ?? null, 'The CPT must not be public.');
        event_participation_test_assert_same(false, $config['publicly_queryable'] ?? null, 'The CPT must not be publicly queryable.');
        event_participation_test_assert_same(true, $config['exclude_from_search'] ?? null, 'The CPT must be excluded from search.');
        event_participation_test_assert_same(true, $config['show_ui'] ?? null, 'The CPT must have an admin UI.');
        event_participation_test_assert_same(true, $config['show_in_rest'] ?? null, 'The CPT must support the REST API.');
        event_participation_test_assert_same(false, $config['has_archive'] ?? null, 'The CPT must not have an archive.');
        event_participation_test_assert_same(
            'no_permalink',
            $config['rewrite']['permalink_rewrite'] ?? null,
            'ACF must disable CPT permalinks using its no_permalink setting.'
        );
        event_participation_test_assert_same(false, $config['query_var'] ?? null, 'The CPT must not register a query variable.');
        event_participation_test_assert_same(['title'], $config['supports'] ?? null, 'The CPT must support only the title.');
    },
    'event role is a private taxonomy with no public permalink' => static function (): void {
        $config = event_participation_test_load_json('taxonomy_event_role.json');

        event_participation_test_assert_same('taxonomy_event_role', $config['key'] ?? null, 'The taxonomy key must be stable.');
        event_participation_test_assert_same('event_role', $config['taxonomy'] ?? null, 'The taxonomy slug must be canonical.');
        event_participation_test_assert_same('Event Role', $config['labels']['singular_name'] ?? null, 'The singular editor label must be canonical.');
        event_participation_test_assert_same('Event Roles', $config['labels']['name'] ?? null, 'The plural editor label must be canonical.');
        event_participation_test_assert_same(['event_participation'], $config['object_type'] ?? null, 'The taxonomy must attach only to event participations.');
        event_participation_test_assert_same(false, $config['hierarchical'] ?? null, 'The taxonomy must be flat.');
        event_participation_test_assert_same(false, $config['public'] ?? null, 'The taxonomy must not be public.');
        event_participation_test_assert_same(false, $config['publicly_queryable'] ?? null, 'The taxonomy must not be publicly queryable.');
        event_participation_test_assert_same(true, $config['show_ui'] ?? null, 'The taxonomy must have an admin UI.');
        event_participation_test_assert_same(true, $config['show_in_rest'] ?? null, 'The taxonomy must support the REST API.');
        event_participation_test_assert_same('disabled', $config['meta_box'] ?? null, 'The native taxonomy meta box must be disabled.');
        event_participation_test_assert_same('no_permalink', $config['rewrite']['permalink_rewrite'] ?? null, 'The taxonomy must not register a permalink.');
        event_participation_test_assert_same('none', $config['query_var'] ?? null, 'The taxonomy must not register a query variable.');
    },
    'event participation details contain only the four approved fields' => static function (): void {
        $group = event_participation_test_load_json('group_vg_event_participation_details.json');

        event_participation_test_assert_same('group_vg_event_participation_details', $group['key'] ?? null, 'The field group key must be stable.');
        event_participation_test_assert_same('Event Participation Details', $group['title'] ?? null, 'The field group title must match the approved design.');
        event_participation_test_assert_same(true, $group['active'] ?? null, 'The field group must be active.');
        event_participation_test_assert_same([[
            ['param' => 'post_type', 'operator' => '==', 'value' => 'event_participation'],
        ]], $group['location'] ?? null, 'The field group must target event_participation only.');

        $fields = [];
        foreach (($group['fields'] ?? []) as $field) {
            $fields[$field['name'] ?? ''] = $field;
        }
        event_participation_test_assert_same([
            'event_participation_event',
            'event_participation_person',
            'event_participation_roles',
            'event_participation_display_order',
        ], array_keys($fields), 'The field group must contain exactly the four approved fields in order.');

        $event = $fields['event_participation_event'];
        event_participation_test_assert_same('field_vg_event_participation_event', $event['key'] ?? null, 'The Event field key must be stable.');
        event_participation_test_assert_same('Event Participation: Event', $event['label'] ?? null, 'The Event field label must be canonical.');
        event_participation_test_assert_same('post_object', $event['type'] ?? null, 'The Event field must use a post object.');
        event_participation_test_assert_same(['tc_events'], $event['post_type'] ?? null, 'The Event field must select Tickera Events only.');
        event_participation_test_assert_same([], $event['post_status'] ?? [], 'The Event field must not exclude otherwise valid non-trashed Events by status.');
        event_participation_test_assert_same(1, (int) ($event['required'] ?? 0), 'The Event field must be required.');
        event_participation_test_assert_same(0, (int) ($event['multiple'] ?? 1), 'The Event field must be single-valued.');
        event_participation_test_assert_same('id', $event['return_format'] ?? null, 'The Event field must return an ID.');

        $person = $fields['event_participation_person'];
        event_participation_test_assert_same('field_vg_event_participation_person', $person['key'] ?? null, 'The Person field key must be stable.');
        event_participation_test_assert_same('Event Participation: Person', $person['label'] ?? null, 'The Person field label must be canonical.');
        event_participation_test_assert_same('post_object', $person['type'] ?? null, 'The Person field must use a post object.');
        event_participation_test_assert_same(['person'], $person['post_type'] ?? null, 'The Person field must select People only.');
        event_participation_test_assert_same([], $person['post_status'] ?? [], 'The Person field must not exclude otherwise valid non-trashed People by status.');
        event_participation_test_assert_same(1, (int) ($person['required'] ?? 0), 'The Person field must be required.');
        event_participation_test_assert_same(0, (int) ($person['multiple'] ?? 1), 'The Person field must be single-valued.');
        event_participation_test_assert_same('id', $person['return_format'] ?? null, 'The Person field must return an ID.');

        $roles = $fields['event_participation_roles'];
        event_participation_test_assert_same('field_vg_event_participation_roles', $roles['key'] ?? null, 'The Roles field key must be stable.');
        event_participation_test_assert_same('Event Participation: Roles', $roles['label'] ?? null, 'The Roles field label must be canonical.');
        event_participation_test_assert_same('taxonomy', $roles['type'] ?? null, 'Roles must use a taxonomy field.');
        event_participation_test_assert_same('event_role', $roles['taxonomy'] ?? null, 'Roles must use the event_role taxonomy.');
        event_participation_test_assert_same('multi_select', $roles['field_type'] ?? null, 'Roles must allow multiple selections.');
        event_participation_test_assert_same(1, (int) ($roles['multiple'] ?? 0), 'Roles must be multi-valued.');
        event_participation_test_assert_same(0, (int) ($roles['required'] ?? 1), 'Roles must be optional.');
        event_participation_test_assert_same(0, (int) ($roles['add_term'] ?? 1), 'Editors must not create role terms inline.');
        event_participation_test_assert_same(1, (int) ($roles['save_terms'] ?? 0), 'Role selections must save native terms.');
        event_participation_test_assert_same(1, (int) ($roles['load_terms'] ?? 0), 'Role selections must load native terms.');
        event_participation_test_assert_same('id', $roles['return_format'] ?? null, 'The Roles field must return IDs.');

        $display_order = $fields['event_participation_display_order'];
        event_participation_test_assert_same('field_vg_event_participation_display_order', $display_order['key'] ?? null, 'The Display Order field key must be stable.');
        event_participation_test_assert_same('Event Participation: Display Order', $display_order['label'] ?? null, 'The Display Order field label must be canonical.');
        event_participation_test_assert_same('number', $display_order['type'] ?? null, 'Display Order must use a number field.');
        event_participation_test_assert_same(0, (int) ($display_order['required'] ?? 1), 'Display Order must be optional.');
        event_participation_test_assert_same(0, $display_order['default_value'] ?? null, 'Display Order must default to zero.');
        event_participation_test_assert_same(0, (int) ($display_order['min'] ?? -1), 'Display Order must not accept negative values.');
        event_participation_test_assert_same('1', $display_order['step'] ?? null, 'Display Order must accept integers.');
    },
];

$passed = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
    } catch (Throwable $error) {
        fwrite(STDERR, 'FAIL: ' . $name . PHP_EOL . $error->getMessage() . PHP_EOL);
        exit(1);
    }

    ++$passed;
    echo '.';
}

echo PHP_EOL . $passed . ' Event Participation schema contract tests passed.' . PHP_EOL;
