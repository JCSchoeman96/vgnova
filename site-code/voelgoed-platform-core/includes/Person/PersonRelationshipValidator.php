<?php
declare(strict_types=1);
namespace Voelgoed\PlatformCore\Person;
defined('ABSPATH') || exit;

/** Validates normalized Person relationship projections before they are used or written. */
final class PersonRelationshipValidator
{
    public function validate_projection(array $projection, string $target_source_status): true|\WP_Error
    {
        $allowed_source_statuses = ['publish', 'future', 'draft', 'pending', 'private', 'auto-draft'];
        if (! in_array($target_source_status, $allowed_source_statuses, true)) {
            return new \WP_Error('invalid_source_status', 'Source status is not a recognized WordPress publication state.');
        }
        $source_id = $projection['source_post_id'] ?? null;
        $source_type = $projection['source_post_type'] ?? null;
        $relationships = $projection['relationships'] ?? null;
        if (! is_int($source_id) || $source_id < 1 || ! is_string($source_type) || ! PersonRelationshipContract::supported_source_type($source_type)) {
            return new \WP_Error('invalid_source', 'Projection must identify an approved source post.');
        }
        if (! is_array($relationships) || ! array_is_list($relationships)) {
            return new \WP_Error('invalid_relationships', 'Projection relationships must be an ordered list.');
        }

        $roles = PersonRelationshipContract::allowed_roles($source_type);
        $seen_people = [];
        $seen_role_people = [];
        $role_positions = [];
        foreach ($relationships as $row) {
            if (! is_array($row)
                || ! isset($row['person_id'], $row['role_key'], $row['position'])
                || ! is_int($row['person_id']) || $row['person_id'] < 1
                || ! is_string($row['role_key']) || ! in_array($row['role_key'], $roles, true)
                || ! is_int($row['position']) || $row['position'] < 0
            ) {
                return new \WP_Error('invalid_relationship', 'Projection contains a malformed or unapproved relationship row.');
            }

            $person_id = $row['person_id'];
            $role_key = $row['role_key'];
            if (isset($seen_role_people[$role_key][$person_id])) {
                return new \WP_Error('duplicate_relationship', sprintf('Person %d appears more than once in %s.', $person_id, $role_key));
            }
            if (isset($seen_people[$person_id])) {
                return new \WP_Error('multiple_person_roles', sprintf('Person %d may hold only one role on this source.', $person_id));
            }
            $seen_role_people[$role_key][$person_id] = true;
            $seen_people[$person_id] = true;
            $role_positions[$role_key][] = $row['position'];

            $person_valid = $this->validate_person($person_id, $target_source_status === 'publish');
            if ($person_valid !== true) {
                return $person_valid;
            }
        }

        foreach ($role_positions as $positions) {
            sort($positions, SORT_NUMERIC);
            if ($positions !== range(0, count($positions) - 1)) {
                return new \WP_Error('invalid_relationship_positions', 'Relationship positions must be unique and contiguous within each role.');
            }
        }

        return true;
    }

    public function validate_person(int $person_id, bool $must_be_public): true|\WP_Error
    {
        if ($person_id < 1) {
            return new \WP_Error('invalid_person_id', 'Person ID must be positive.');
        }
        $person = get_post($person_id);
        if (! is_object($person)) {
            return new \WP_Error('person_not_found', sprintf('Person %d does not exist.', $person_id));
        }
        if (($person->post_type ?? '') !== PersonRelationshipContract::PERSON_POST_TYPE) {
            return new \WP_Error('wrong_person_type', sprintf('Post %d is not a canonical Person.', $person_id));
        }
        $status = (string) ($person->post_status ?? '');
        $allowed_statuses = $must_be_public ? ['publish'] : ['publish', 'draft', 'private'];
        if (! in_array($status, $allowed_statuses, true)) {
            return new \WP_Error('invalid_person_status', sprintf('Person %d must have an allowed status for this source.', $person_id));
        }
        return true;
    }
}
