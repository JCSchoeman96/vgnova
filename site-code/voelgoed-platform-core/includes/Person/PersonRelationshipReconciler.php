<?php
declare(strict_types=1);
namespace Voelgoed\PlatformCore\Person;
defined('ABSPATH') || exit;

/** Compares authoritative source relationships with their disposable index rows. */
final class PersonRelationshipReconciler
{
    public function __construct(
        private PersonRelationshipSourceReader $reader,
        private PersonRelationshipRepository $repository,
        private PersonRelationshipValidator $validator,
    ) {}

    /** @return list<array{code:string,source_post_id:int,details?:array}>|\WP_Error */
    public function inspect_source(int $source_post_id): array|\WP_Error
    {
        $source = get_post($source_post_id);
        if (! is_object($source)) return new \WP_Error('source_not_found', 'The source post does not exist.');
        $source_type = (string) ($source->post_type ?? '');
        if (! PersonRelationshipContract::supported_source_type($source_type)) {
            return [['code' => 'unsupported_source_type', 'source_post_id' => $source_post_id, 'details' => ['source_post_type' => $source_type]]];
        }

        $issues = [];
        if ((string) get_post_meta($source_post_id, PersonRelationshipContract::DIRTY_META_KEY, true) === '1') {
            $issues[] = ['code' => 'dirty_source', 'source_post_id' => $source_post_id];
        }
        $projection = $this->reader->read($source_post_id);
        if (is_wp_error($projection)) {
            if ($projection->get_error_code() === 'unsupported_source') {
                $issues[] = ['code' => 'unsupported_source_type', 'source_post_id' => $source_post_id];
                return $issues;
            }
            return $projection;
        }

        $status = (string) ($source->post_status ?? '');
        $validation_status = $status === 'future' ? 'publish' : $status;
        $valid = $this->validator->validate_projection($projection, $validation_status);
        if (is_wp_error($valid)) {
            $code = match ($valid->get_error_code()) {
                'invalid_person_id', 'person_not_found', 'wrong_person_type', 'invalid_person_status' => 'invalid_person_reference',
                default => null,
            };
            if ($code === null) return $valid;
            $issues[] = ['code' => $code, 'source_post_id' => $source_post_id, 'details' => ['error' => $valid->get_error_code()]];
        }

        $expected = $projection['relationships'];
        $source_type_rows = $this->repository->index_source_anomalies();
        if (is_wp_error($source_type_rows)) return $source_type_rows;
        foreach ($source_type_rows as $type_row) {
            if ($type_row['source_post_id'] === $source_post_id) {
                $issues[] = ['code' => 'wrong_source_post_type', 'source_post_id' => $source_post_id, 'details' => [
                    'stored_source_post_type' => $type_row['source_post_type'],
                    'current_source_post_type' => $type_row['current_post_type'],
                ]];
            }
        }
        $actual = $this->repository->relationships_for_source_checked($source_post_id);
        if (is_wp_error($actual)) return $actual;
        $expected_by_person = [];
        foreach ($expected as $row) $expected_by_person[$row['person_id']] = $row;
        $matched_expected = [];
        $seen_actual = [];
        foreach ($actual as $row) {
            $person_id = (int) $row['person_id'];
            $role_key = (string) $row['role_key'];
            $position = (int) $row['position'];
            $row_key = $person_id . ':' . $role_key;
            $duplicate = isset($seen_actual[$row_key]);
            $seen_actual[$row_key] = true;

            if (! in_array($role_key, PersonRelationshipContract::allowed_roles($source_type), true)) {
                $issues[] = ['code' => 'unsupported_role', 'source_post_id' => $source_post_id, 'details' => ['person_id' => $person_id, 'role_key' => $role_key]];
            }
            if ($duplicate) {
                $issues[] = ['code' => 'stale_index_row', 'source_post_id' => $source_post_id, 'details' => ['person_id' => $person_id, 'role_key' => $role_key, 'reason' => 'duplicate_row']];
            }

            $expected_row = $expected_by_person[$person_id] ?? null;
            if ($expected_row === null) {
                if (! $duplicate) $issues[] = ['code' => 'stale_index_row', 'source_post_id' => $source_post_id, 'details' => ['person_id' => $person_id, 'role_key' => $role_key]];
            } elseif (! $duplicate) {
                if ($role_key !== $expected_row['role_key']) {
                    $issues[] = ['code' => 'wrong_role', 'source_post_id' => $source_post_id, 'details' => ['person_id' => $person_id, 'role_key' => $role_key, 'expected_role_key' => $expected_row['role_key']]];
                } else {
                    $matched_expected[$person_id] = true;
                }
                if ($position !== $expected_row['position']) $issues[] = ['code' => 'wrong_position', 'source_post_id' => $source_post_id, 'details' => ['person_id' => $person_id, 'position' => $position, 'expected_position' => $expected_row['position']]];
            }
            if (is_wp_error($this->validator->validate_person($person_id, $validation_status === 'publish'))) {
                $issues[] = ['code' => 'invalid_person_reference', 'source_post_id' => $source_post_id, 'details' => ['person_id' => $person_id]];
            }
        }
        foreach ($expected_by_person as $person_id => $row) {
            if (isset($matched_expected[$person_id])) continue;
            $has_wrong_role = false;
            foreach ($actual as $actual_row) {
                if ((int) $actual_row['person_id'] === (int) $person_id && (string) $actual_row['role_key'] !== $row['role_key']) $has_wrong_role = true;
            }
            if (! $has_wrong_role) $issues[] = ['code' => 'missing_index_row', 'source_post_id' => $source_post_id, 'details' => ['person_id' => (int) $person_id, 'role_key' => $row['role_key']]];
        }
        return $issues;
    }

    /** @return list<array{code:string,source_post_id:int,details:array}>|\WP_Error */
    public function inspect_index(): array|\WP_Error
    {
        $anomalies = $this->repository->index_source_anomalies();
        if (is_wp_error($anomalies)) return $anomalies;
        $issues = [];
        foreach ($anomalies as $row) {
            $code = $row['current_post_type'] === null
                ? 'orphan_index_source'
                : (! PersonRelationshipContract::supported_source_type($row['current_post_type'])
                    ? 'unsupported_index_source'
                    : 'wrong_source_post_type');
            $issues[] = ['code' => $code, 'source_post_id' => $row['source_post_id'], 'details' => [
                'stored_source_post_type' => $row['source_post_type'],
                'current_source_post_type' => $row['current_post_type'],
            ]];
        }
        return $issues;
    }
}
