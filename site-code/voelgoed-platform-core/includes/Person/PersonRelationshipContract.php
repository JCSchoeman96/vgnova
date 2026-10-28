<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Person;

defined('ABSPATH') || exit;

/** Defines the approved source types and roles for simple Person relationships. */
final class PersonRelationshipContract
{
    public const PERSON_POST_TYPE = 'person';
    public const ARTICLE_POST_TYPE = 'post';
    public const COURSE_POST_TYPE = 'mto-course';
    public const DIRTY_META_KEY = '_vg_person_relationship_index_dirty';

    /** @var array<string, array{roles: list<string>}> */
    private const SOURCE_CONTRACTS = [
        self::ARTICLE_POST_TYPE => [
            'roles' => ['primary_author', 'additional_authors', 'expert_contributors', 'reviewed_by'],
        ],
        self::COURSE_POST_TYPE => [
            'roles' => ['lead_instructor', 'additional_instructors', 'experts'],
        ],
    ];

    /** @return array{roles: list<string>}|null */
    public static function source_contract(string $post_type): ?array
    {
        return self::SOURCE_CONTRACTS[$post_type] ?? null;
    }

    public static function supported_source_type(string $post_type): bool
    {
        return self::source_contract($post_type) !== null;
    }

    /** @return list<string> */
    public static function allowed_roles(string $post_type): array
    {
        return self::source_contract($post_type)['roles'] ?? [];
    }
}
