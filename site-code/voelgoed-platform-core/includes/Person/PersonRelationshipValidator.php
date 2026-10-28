<?php
declare(strict_types=1);
namespace Voelgoed\PlatformCore\Person;
defined('ABSPATH') || exit;

/** Validates normalized Person relationship projections before they are used or written. */
final class PersonRelationshipValidator
{
    /** Check whether submitted fields are unchanged, without requiring the stored projection to be valid. */
    public function submitted_fields_match_persisted(int $source_post_id, array $submitted_fields, ?string $source_type = null): bool|\WP_Error
    {
        if (function_exists('clean_post_cache')) clean_post_cache($source_post_id);
        if (function_exists('wp_cache_delete')) wp_cache_delete($source_post_id, 'post_meta');
        $source = get_post($source_post_id);
        if (! is_object($source)) return new \WP_Error('source_not_found', 'The source post does not exist.');
        $source_type ??= (string) ($source->post_type ?? '');
        if (! PersonRelationshipContract::supported_source_type($source_type)) {
            return new \WP_Error('unsupported_source', 'The source post type has no approved Person relationship contract.');
        }
        if (function_exists('acf_flush_value_cache')) {
            foreach (PersonRelationshipContract::allowed_roles($source_type) as $role) acf_flush_value_cache($source_post_id, $role);
        }
        $projection = (new PersonRelationshipSourceReader())->read_for_source_type($source_post_id, $source_type);
        if (is_wp_error($projection)) return $projection;

        $persisted = array_fill_keys(PersonRelationshipContract::allowed_roles($source_type), []);
        foreach ($projection['relationships'] as $row) $persisted[$row['role_key']][] = $row['person_id'];
        foreach (['primary_author', 'lead_instructor'] as $single_role) {
            if (isset($persisted[$single_role])) $persisted[$single_role] = $persisted[$single_role][0] ?? null;
        }
        foreach ($submitted_fields as $role => $value) {
            if (! is_string($role) || ! array_key_exists($role, $persisted)) return false;
            $normalized = $this->normalize_submitted_role($role, $value);
            if (is_wp_error($normalized)) return $normalized;
            if ($normalized !== $persisted[$role]) return false;
        }
        return true;
    }

    public function validate_publish_partial_write_safety(int $source_post_id, array $submitted_fields, ?string $target_source_type = null): true|\WP_Error
    {
        if (function_exists('clean_post_cache')) {
            clean_post_cache($source_post_id);
        }
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete($source_post_id, 'post_meta');
        }
        $source = get_post($source_post_id);
        if (! is_object($source)) {
            return new \WP_Error('source_not_found', 'The source post does not exist.');
        }
        $persisted_source_type = (string) ($source->post_type ?? '');
        $source_type = $target_source_type ?? $persisted_source_type;
        if (! PersonRelationshipContract::supported_source_type($persisted_source_type)) {
            return new \WP_Error('unsupported_source', 'The persisted source post type has no approved Person relationship contract.');
        }
        $roles = PersonRelationshipContract::allowed_roles($source_type);
        if ($roles === []) {
            return new \WP_Error('unsupported_source', 'The source post type has no approved Person relationship contract.');
        }
        if (function_exists('acf_flush_value_cache')) {
            foreach ($roles as $role) {
                acf_flush_value_cache($source_post_id, $role);
            }
        }

        $reader = new PersonRelationshipSourceReader();
        $persisted_projection = $reader->read_for_source_type($source_post_id, $source_type);
        if (is_wp_error($persisted_projection)) {
            return $persisted_projection;
        }
        $old_values = array_fill_keys($roles, []);
        foreach ($persisted_projection['relationships'] as $row) {
            $old_values[$row['role_key']][] = $row['person_id'];
        }
        foreach (['primary_author', 'lead_instructor'] as $single_role) {
            if (isset($old_values[$single_role])) {
                $old_values[$single_role] = $old_values[$single_role][0] ?? null;
            }
        }

        $new_values = $old_values;
        $changed_roles = [];
        foreach ($submitted_fields as $role => $value) {
            if (! is_string($role) || ! in_array($role, $roles, true)) {
                return new \WP_Error('unrecognized_submitted_relationship', 'Submitted fields must use approved Person relationship names.');
            }
            $normalized = $this->normalize_submitted_role($role, $value);
            if (is_wp_error($normalized)) {
                return $normalized;
            }
            if ($normalized !== $old_values[$role]) {
                $new_values[$role] = $normalized;
                $changed_roles[] = $role;
            }
        }

        $combination_count = 1 << count($changed_roles);
        for ($mask = 0; $mask < $combination_count; $mask++) {
            $values = $old_values;
            foreach ($changed_roles as $bit => $role) {
                if (($mask & (1 << $bit)) !== 0) {
                    $values[$role] = $new_values[$role];
                }
            }
            $projection = $this->projection_from_role_values($source_post_id, $source_type, $roles, $values);
            $result = $this->validate_projection($projection, 'publish');
            if ($result !== true) {
                return new \WP_Error(
                    'unsafe_partial_relationship_write',
                    sprintf('A possible partial relationship save is invalid: %s', $result->get_error_message()),
                    ['combination' => $mask, 'changed_fields' => $changed_roles]
                );
            }
        }

        return true;
    }

    private function normalize_submitted_role(string $role, mixed $value): mixed
    {
        if ($value === null || $value === false || $value === '') {
            return in_array($role, ['primary_author', 'lead_instructor'], true) ? null : [];
        }
        $single = in_array($role, ['primary_author', 'lead_instructor'], true);
        if ($single) {
            if (is_array($value)) {
                return new \WP_Error('malformed_relationship', sprintf('The %s field must contain one Person value.', $role));
            }
            $person_id = $this->normalize_person_value($value);
            return $person_id === null
                ? new \WP_Error('malformed_relationship', sprintf('The %s field contains an invalid Person value.', $role))
                : $person_id;
        }
        if (! is_array($value) || ! array_is_list($value)) {
            return new \WP_Error('malformed_relationship', sprintf('The %s field must contain an ordered list of Person values.', $role));
        }
        $ids = [];
        foreach ($value as $person_value) {
            $person_id = $this->normalize_person_value($person_value);
            if ($person_id === null) {
                return new \WP_Error('malformed_relationship', sprintf('The %s field contains an invalid Person value.', $role));
            }
            $ids[] = $person_id;
        }
        return $ids;
    }

    private function normalize_person_value(mixed $value): ?int
    {
        if (is_object($value)) {
            $value = $value->ID ?? null;
        } elseif (is_array($value)) {
            $value = $value['ID'] ?? $value['id'] ?? null;
        }
        if (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1) {
            $value = (int) $value;
        }
        return is_int($value) && $value > 0 ? $value : null;
    }

    private function projection_from_role_values(int $source_id, string $source_type, array $roles, array $values): array
    {
        $relationships = [];
        foreach ($roles as $role) {
            $value = $values[$role];
            $people = in_array($role, ['primary_author', 'lead_instructor'], true)
                ? ($value === null ? [] : [$value])
                : $value;
            foreach ($people as $position => $person_id) {
                $relationships[] = ['person_id' => $person_id, 'role_key' => $role, 'position' => $position];
            }
        }
        return ['source_post_id' => $source_id, 'source_post_type' => $source_type, 'relationships' => $relationships];
    }

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
