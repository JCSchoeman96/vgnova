<?php
declare(strict_types=1);

final class EventParticipationSchemaTest
{
    public function test_event_participation_uses_acf_sentinel_for_disabled_query_var(): void
    {
        $source_path = dirname(__DIR__, 2) . '/acf-json/post_type_event_participation.json';
        $source = file_get_contents($source_path);
        vgpc_test_true(is_string($source), 'The Event Participation ACF CPT source must be readable.');

        $post_type = json_decode($source, true);
        vgpc_test_same(
            'none',
            $post_type['query_var'] ?? null,
            'Event Participation must use ACF\'s disabled-query-var sentinel so WordPress registers query_var=false.'
        );
        vgpc_test_same('', $post_type['query_var_name'] ?? null, 'Event Participation must not define a query variable name.');
    }
}
