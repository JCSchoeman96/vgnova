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
            $code = $projection->get_error_code() === 'unsupported_source'
                ? 'unsupported_source_type'
                : ($projection->get_error_code() === 'malformed_relationship' ? 'invalid_person_reference' : 'dirty_source');
            $issues[] = ['code' => $code, 'source_post_id' => $source_post_id, 'details' => ['error' => $projection->get_error_code()]];
            return $issues;
        }

        $status = (string) ($source->post_status ?? '');
        $validation_status = $status === 'future' ? 'publish' : $status;
        $valid = $this->validator->validate_projection($projection, $validation_status);
        if (is_wp_error($valid)) {
            $code = match ($valid->get_error_code()) {
                'invalid_person_id', 'person_not_found', 'wrong_person_type', 'invalid_person_status' => 'invalid_person_reference',
                'invalid_relationship' => 'unsupported_role',
                default => 'invalid_person_reference',
            };
            $issues[] = ['code' => $code, 'source_post_id' => $source_post_id, 'details' => ['error' => $valid->get_error_code()]];
        }

        $expected = $projection['relationships'];
        $actual = $this->repository->relationships_for_source($source_post_id);
        $expected_by_person = [];
        foreach ($expected as $row) $expected_by_person[$row['person_id']] = $row;
        $actual_by_person = [];
        foreach ($actual as $row) $actual_by_person[$row['person_id']] = $row;
        foreach ($expected_by_person as $person_id => $row) {
            if (! isset($actual_by_person[$person_id])) {
                $issues[] = ['code' => 'missing_index_row', 'source_post_id' => $source_post_id, 'details' => ['person_id' => (int) $person_id, 'role_key' => $row['role_key']]];
                continue;
            }
            if ($actual_by_person[$person_id]['role_key'] !== $row['role_key']) $issues[] = ['code' => 'wrong_role', 'source_post_id' => $source_post_id, 'details' => ['person_id' => (int) $person_id]];
            if ($actual_by_person[$person_id]['position'] !== $row['position']) $issues[] = ['code' => 'wrong_position', 'source_post_id' => $source_post_id, 'details' => ['person_id' => (int) $person_id]];
        }
        foreach ($actual_by_person as $person_id => $row) {
            if (! isset($expected_by_person[$person_id])) $issues[] = ['code' => 'stale_index_row', 'source_post_id' => $source_post_id, 'details' => ['person_id' => (int) $person_id, 'role_key' => $row['role_key']]];
            if (! in_array($row['role_key'], PersonRelationshipContract::allowed_roles($source_type), true)) $issues[] = ['code' => 'unsupported_role', 'source_post_id' => $source_post_id, 'details' => ['person_id' => (int) $person_id, 'role_key' => $row['role_key']]];
            if (is_wp_error($this->validator->validate_person((int) $person_id, $validation_status === 'publish'))) {
                $issues[] = ['code' => 'invalid_person_reference', 'source_post_id' => $source_post_id, 'details' => ['person_id' => (int) $person_id]];
            }
        }
        return $issues;
    }
}
