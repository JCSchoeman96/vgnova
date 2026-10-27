<?php
declare(strict_types=1);
namespace Voelgoed\PlatformCore\Person;
defined('ABSPATH') || exit;

/** Stable forward and reverse queries for canonical Article/Course relationships. */
final class PersonRelationshipService
{
    public function __construct(
        private PersonRelationshipSourceReader $reader,
        private PersonRelationshipValidator $validator,
        private PersonRelationshipRepository $repository,
    ) {}

    /** @return array{primary_author:?int,additional_authors:list<int>,expert_contributors:list<int>,reviewed_by:list<int>} */
    public function people_for_article(int $article_id): array
    {
        return $this->forward($article_id, PersonRelationshipContract::ARTICLE_POST_TYPE, [
            'primary_author' => null, 'additional_authors' => [], 'expert_contributors' => [], 'reviewed_by' => [],
        ]);
    }

    /** @return array{lead_instructor:?int,additional_instructors:list<int>,experts:list<int>} */
    public function people_for_course(int $course_id): array
    {
        return $this->forward($course_id, PersonRelationshipContract::COURSE_POST_TYPE, [
            'lead_instructor' => null, 'additional_instructors' => [], 'experts' => [],
        ]);
    }

    /** @return list<int> */
    public function articles_for_person(int $person_id, array $role_keys = []): array
    {
        return $this->reverse($person_id, PersonRelationshipContract::ARTICLE_POST_TYPE, $role_keys);
    }

    /** @return list<int> */
    public function courses_for_person(int $person_id, array $role_keys = []): array
    {
        return $this->reverse($person_id, PersonRelationshipContract::COURSE_POST_TYPE, $role_keys);
    }

    private function forward(int $source_id, string $expected_type, array $empty): array
    {
        if ($source_id < 1 || (string) get_post_meta($source_id, PersonRelationshipContract::DIRTY_META_KEY, true) === '1') return $empty;
        $source = get_post($source_id);
        if (! is_object($source) || (string) ($source->post_type ?? '') !== $expected_type) return $empty;
        $projection = $this->reader->read($source_id);
        if (is_wp_error($projection)) return $empty;
        $status = (string) ($source->post_status ?? '');
        $validation_status = $status === 'future' ? 'publish' : $status;
        if (is_wp_error($this->validator->validate_projection($projection, $validation_status))) return $empty;
        foreach ($projection['relationships'] as $row) {
            if (! array_key_exists($row['role_key'], $empty)) return $empty;
            if ($row['role_key'] === 'primary_author' || $row['role_key'] === 'lead_instructor') $empty[$row['role_key']] = $row['person_id'];
            else $empty[$row['role_key']][] = $row['person_id'];
        }
        return $empty;
    }

    /** @return list<int> */
    private function reverse(int $person_id, string $source_type, array $role_keys): array
    {
        if ($person_id < 1 || is_wp_error($this->validator->validate_person($person_id, true))) return [];
        $allowed = PersonRelationshipContract::allowed_roles($source_type);
        if ($role_keys && (! array_is_list($role_keys) || array_diff($role_keys, $allowed))) return [];
        $ids = $this->repository->source_ids_for_person($person_id, $source_type, $role_keys);
        $results = [];
        foreach ($ids as $id) {
            $source = get_post($id);
            if (! is_object($source)
                || (string) ($source->post_type ?? '') !== $source_type
                || (string) ($source->post_status ?? '') !== 'publish'
                || (string) get_post_meta($id, PersonRelationshipContract::DIRTY_META_KEY, true) === '1'
            ) continue;
            // Fail closed even if both dirty-marker writes failed. The source
            // ACF fields remain authoritative, so require a matching row.
            $projection = $this->reader->read($id);
            if (is_wp_error($projection)) continue;
            $matches = false;
            foreach ($projection['relationships'] as $relationship) {
                if ($relationship['person_id'] === $person_id
                    && (! $role_keys || in_array($relationship['role_key'], $role_keys, true))) {
                    $matches = true;
                    break;
                }
            }
            if (! $matches) continue;
            $results[$id] = $id;
        }
        ksort($results, SORT_NUMERIC);
        return array_values($results);
    }
}
