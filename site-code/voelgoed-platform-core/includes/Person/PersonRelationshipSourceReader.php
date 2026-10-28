<?php
declare(strict_types=1);
namespace Voelgoed\PlatformCore\Person;
defined('ABSPATH') || exit;

/** Reads the authoritative ACF fields for an approved Article or Course. */
final class PersonRelationshipSourceReader
{
    public function read(int $source_post_id): array|\WP_Error
    {
        return $this->read_for_source_type($source_post_id, null);
    }

    /** Read the governed fields for a supported target type before a type transition is written. */
    public function read_for_source_type(int $source_post_id, ?string $source_type): array|\WP_Error
    {
        if ($source_post_id < 1) {
            return new \WP_Error('invalid_source_id', 'Source post ID must be positive.');
        }

        $source = get_post($source_post_id);
        if (! is_object($source)) {
            return new \WP_Error('source_not_found', 'The source post does not exist.');
        }

        $source_type ??= (string) ($source->post_type ?? '');
        $roles = PersonRelationshipContract::allowed_roles($source_type);
        if ($roles === []) {
            return new \WP_Error('unsupported_source', 'The source post type has no approved Person relationship contract.');
        }
        if (! function_exists('get_field')) {
            return new \WP_Error('acf_unavailable', 'ACF is required to read authoritative Person relationships.');
        }

        $relationships = [];
        foreach ($roles as $role) {
            try {
                $value = get_field($role, $source_post_id, false);
            } catch (\Throwable) {
                return new \WP_Error('relationship_read_failed', 'Could not read authoritative Person relationship fields.');
            }

            if ($value === null || $value === false || $value === '') {
                continue;
            }
            $is_single_value_role = in_array($role, ['primary_author', 'lead_instructor'], true);
            if ($is_single_value_role) {
                if (is_array($value)) {
                    return new \WP_Error('malformed_relationship', sprintf('The %s field must contain a single Person value.', $role));
                }
                $values = [$value];
            } else {
                if (! is_array($value) || ! array_is_list($value)) {
                    return new \WP_Error('malformed_relationship', sprintf('The %s field must contain an ordered list of Person values.', $role));
                }
                $values = $value;
            }
            foreach ($values as $position => $person_value) {
                $person_id = $this->person_id($person_value);
                if ($person_id === null) {
                    return new \WP_Error('malformed_relationship', sprintf('The %s field contains an invalid Person value.', $role));
                }
                $relationships[] = ['person_id' => $person_id, 'role_key' => $role, 'position' => $position];
            }
        }

        return [
            'source_post_id' => $source_post_id,
            'source_post_type' => $source_type,
            'relationships' => $relationships,
        ];
    }

    private function person_id(mixed $value): ?int
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
}
