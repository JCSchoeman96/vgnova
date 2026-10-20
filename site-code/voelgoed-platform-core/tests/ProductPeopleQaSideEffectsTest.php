<?php
declare(strict_types=1);

final class ProductPeopleQaSideEffectsTest
{
    private const OWNER = '[QA][AUTOMATED] Task 7A 20261020054436-e6890be3153eeea3';

    /** Reproduces the failed Task 6 run: creating the owned QA Event made Tickera create tc_api_keys 1346. */
    public function test_owned_tickera_key_for_owned_event_is_planned_for_deletion(): void
    {
        vgpc_test_true(
            function_exists('vg_product_people_qa_plan_tickera_side_effects'),
            'The QA runtime must provide the Tickera side-effect planner.'
        );

        $plan = vg_product_people_qa_plan_tickera_side_effects(
            [$this->api_key_row(1346, self::OWNER . ' Event', ['all' => [0 => '1345']])],
            [1190, 1192, 1194],
            self::OWNER,
            [1345]
        );

        vgpc_test_same([1346], $plan['delete'], 'The owned tc_api_keys side effect must be planned for deletion.');
        vgpc_test_same([], $plan['unexplained'], 'The owned tc_api_keys side effect must not be reported as unexplained.');
    }

    public function test_zero_matching_side_effects_plans_no_deletion(): void
    {
        $plan = vg_product_people_qa_plan_tickera_side_effects([], [1190], self::OWNER, [1345]);

        vgpc_test_same([], $plan['delete'], 'Zero Tickera side effects must plan no deletion.');
        vgpc_test_same([], $plan['unexplained'], 'Zero Tickera side effects must not be reported as unexplained.');
    }

    public function test_multiple_owned_side_effects_are_all_planned(): void
    {
        $plan = vg_product_people_qa_plan_tickera_side_effects(
            [
                $this->api_key_row(1346, self::OWNER . ' Event', ['all' => [0 => '1345']]),
                $this->api_key_row(1347, self::OWNER . ' Event', ['all' => [0 => '1345']]),
            ],
            [1190],
            self::OWNER,
            [1345]
        );

        vgpc_test_same([1346, 1347], $plan['delete'], 'Every provably owned Tickera side effect must be planned for deletion.');
        vgpc_test_same([], $plan['unexplained'], 'Provably owned side effects must not be reported as unexplained.');
    }

    public function test_side_effect_referencing_a_non_owned_event_is_not_deleted(): void
    {
        $plan = vg_product_people_qa_plan_tickera_side_effects(
            [$this->api_key_row(1346, self::OWNER . ' Event', ['all' => [0 => '1345', 1 => '999']])],
            [1190],
            self::OWNER,
            [1345]
        );

        vgpc_test_same([], $plan['delete'], 'A key that also references a non-owned Event must never be deleted.');
        vgpc_test_same(1346, $plan['unexplained'][0]['id'] ?? null, 'A key referencing a non-owned Event must be reported as unexplained.');
    }

    public function test_partial_name_matches_are_unexplained_not_deleted(): void
    {
        $plan = vg_product_people_qa_plan_tickera_side_effects(
            [
                $this->api_key_row(1346, self::OWNER, ['all' => [0 => '1345']]),
                $this->api_key_row(1347, self::OWNER . ' Event extra', ['all' => [0 => '1345']]),
            ],
            [1190],
            self::OWNER,
            [1345]
        );

        vgpc_test_same([], $plan['delete'], 'Partial owner-name matches must never be deleted.');
        vgpc_test_same([1346, 1347], array_column($plan['unexplained'], 'id'), 'Partial owner-name matches must be reported as unexplained.');
    }

    public function test_pre_existing_records_are_never_candidates(): void
    {
        $plan = vg_product_people_qa_plan_tickera_side_effects(
            [$this->api_key_row(1190, self::OWNER . ' Event', ['all' => [0 => '1345']])],
            [1190],
            self::OWNER,
            [1345]
        );

        vgpc_test_same([], $plan['delete'], 'A tc_api_keys record that existed before QA must never be deleted.');
    }

    public function test_owned_name_without_an_event_reference_is_unexplained(): void
    {
        $plan = vg_product_people_qa_plan_tickera_side_effects(
            [$this->api_key_row(1346, self::OWNER . ' Event', null)],
            [1190],
            self::OWNER,
            [1345]
        );

        vgpc_test_same([], $plan['delete'], 'A key with no provable Event reference must not be deleted.');
        vgpc_test_same([1346], array_column($plan['unexplained'], 'id'), 'A key with no provable Event reference must be reported as unexplained.');
    }

    public function test_event_reference_extraction_handles_serialized_scalar_and_unparseable_values(): void
    {
        vgpc_test_same([1345], vg_product_people_qa_extract_event_refs(['all' => [0 => '1345']]), 'Tickera event_name must yield its referenced Event ID.');
        vgpc_test_same([1345], vg_product_people_qa_extract_event_refs('1345'), 'A scalar Event ID must be extracted.');
        vgpc_test_same([0], vg_product_people_qa_extract_event_refs('not-an-id'), 'An unparseable reference must be marked as 0 so it fails ownership.');
    }

    /** Guards the runtime QA wiring: side effects are cleaned before the owned Event is deleted. */
    public function test_qa_cleans_tickera_side_effects_before_deleting_the_owned_event(): void
    {
        $qa = (string) file_get_contents(dirname(__DIR__, 2) . '/runtime-tests/product-people-qa.php');

        // Match the call site, not the helper definition, so the guard cannot pass trivially.
        $cleanup_offset = strpos($qa, '$side_effects = vg_product_people_qa_delete_tickera_side_effects(');
        $event_delete_offset = strpos($qa, "\$delete((int) \$owned['event'], 'tc_events', \$owner);");
        vgpc_test_true($cleanup_offset !== false, 'The QA cleanup must call the Tickera side-effect cleaner.');
        vgpc_test_true($event_delete_offset !== false, 'The QA cleanup must still delete the owned Event.');
        vgpc_test_true(
            $cleanup_offset < $event_delete_offset,
            'The QA cleanup must remove Tickera side effects before deleting the owned Event.'
        );
    }

    /** Guards the protected-state gate: tc_api_keys must be part of the snapshot compared before and after QA. */
    public function test_qa_snapshot_protects_the_pre_qa_tickera_api_key_state(): void
    {
        $qa = (string) file_get_contents(dirname(__DIR__, 2) . '/runtime-tests/product-people-qa.php');

        vgpc_test_true(
            str_contains($qa, "'tickera_api_keys' => vg_product_people_qa_api_key_snapshot_rows()"),
            'The protected snapshot must include normalized tc_api_keys state.'
        );
    }

    /** The final token scan must cover options as well as posts and post metadata. */
    public function test_qa_token_scan_covers_options(): void
    {
        $qa = (string) file_get_contents(dirname(__DIR__, 2) . '/runtime-tests/product-people-qa.php');

        vgpc_test_true(
            str_contains($qa, "FROM {\$wpdb->options} WHERE option_value LIKE %s"),
            'The run-token scan must include wp_options.'
        );
    }

    private function api_key_row(int $id, mixed $api_key_name, mixed $event_name): array
    {
        return [
            'id' => $id,
            'post_type' => 'tc_api_keys',
            'status' => 'publish',
            'api_key_name' => $api_key_name,
            'event_name' => $event_name,
        ];
    }
}
