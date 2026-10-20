<?php
declare(strict_types=1);

/**
 * Pure ownership rules for Tickera side effects created by the Task 7A guarded QA.
 *
 * Creating a tc_events record makes Tickera create a tc_api_keys record linked to that Event. This file holds only
 * deterministic classification logic so it can be unit-tested; the runtime QA reads WordPress and applies these rules.
 * Ownership must be provable. Anything ambiguous is reported as unexplained and is never deleted.
 */

/** @return list<int> Event IDs referenced by a Tickera event_name value (0 marks an unparseable reference). */
function vg_product_people_qa_extract_event_refs(mixed $value): array
{
    if ($value === null) {
        return [];
    }

    if (is_array($value)) {
        $refs = [];
        foreach ($value as $item) {
            foreach (vg_product_people_qa_extract_event_refs($item) as $ref) {
                $refs[] = $ref;
            }
        }

        return $refs;
    }

    if (is_int($value)) {
        return [$value];
    }

    return [is_string($value) && ctype_digit($value) ? (int) $value : 0];
}

/**
 * Classifies one tc_api_keys row as owned by this exact run or unexplained.
 *
 * @param array{id:int, post_type:string, status:string, api_key_name:mixed, event_name:mixed} $row
 * @param list<int> $owned_event_ids
 * @return array{decision:'owned'|'unexplained', reason:string}
 */
function vg_product_people_qa_classify_tickera_side_effect(array $row, string $owner, array $owned_event_ids): array
{
    if (($row['post_type'] ?? null) !== 'tc_api_keys') {
        return ['decision' => 'unexplained', 'reason' => 'record is not a tc_api_keys post'];
    }

    if (($row['api_key_name'] ?? null) !== $owner . ' Event') {
        return ['decision' => 'unexplained', 'reason' => 'api_key_name does not exactly match this run owner'];
    }

    $refs = vg_product_people_qa_extract_event_refs($row['event_name'] ?? null);
    if ($refs === []) {
        return ['decision' => 'unexplained', 'reason' => 'no provable Event reference'];
    }

    foreach ($refs as $ref) {
        if (! in_array($ref, $owned_event_ids, true)) {
            return ['decision' => 'unexplained', 'reason' => 'references non-owned or unparseable Event ' . $ref];
        }
    }

    return ['decision' => 'owned', 'reason' => 'matches run owner and references only Events owned by this run'];
}

/**
 * Plans the deletion of run-owned Tickera side effects.
 *
 * @param list<array{id:int, post_type:string, status:string, api_key_name:mixed, event_name:mixed}> $rows
 * @param list<int> $pre_existing_ids tc_api_keys IDs that existed before the QA run; never candidates.
 * @param list<int> $owned_event_ids Event IDs owned by this exact run.
 * @return array{delete:list<int>, unexplained:list<array{id:int, reason:string}>}
 */
function vg_product_people_qa_plan_tickera_side_effects(array $rows, array $pre_existing_ids, string $owner, array $owned_event_ids): array
{
    $pre_existing = array_map('intval', $pre_existing_ids);
    $owned_events = array_map('intval', $owned_event_ids);
    $delete = [];
    $unexplained = [];

    foreach ($rows as $row) {
        $id = (int) $row['id'];
        if (in_array($id, $pre_existing, true)) {
            continue;
        }

        $verdict = vg_product_people_qa_classify_tickera_side_effect($row, $owner, $owned_events);
        if ($verdict['decision'] === 'owned') {
            $delete[] = $id;
        } else {
            $unexplained[] = ['id' => $id, 'reason' => $verdict['reason']];
        }
    }

    return ['delete' => $delete, 'unexplained' => $unexplained];
}
