<?php
declare(strict_types=1);

/**
 * Build the read-only SQL used by the Product Contribution protected-group snapshot.
 *
 * @return array{posts:string,postmeta:string,term_relationships:string}
 */
function vg_pe008_group_fingerprint_queries(object $wpdb, array $ids): array
{
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if ($ids === []) {
        throw new InvalidArgumentException('Protected fingerprint ID list cannot be empty.');
    }

    $in = implode(',', $ids);
    return [
        'posts' => "SELECT * FROM {$wpdb->posts} WHERE ID IN ({$in}) ORDER BY ID ASC",
        'postmeta' => "SELECT * FROM {$wpdb->postmeta} WHERE post_id IN ({$in}) ORDER BY post_id ASC, meta_key ASC, meta_id ASC",
        'term_relationships' => "SELECT r.object_id,r.term_taxonomy_id,r.term_order,tt.taxonomy,tt.term_id,t.slug FROM {$wpdb->term_relationships} r JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=r.term_taxonomy_id JOIN {$wpdb->terms} t ON t.term_id=tt.term_id WHERE r.object_id IN ({$in}) ORDER BY r.object_id ASC,r.term_taxonomy_id ASC,r.term_order ASC,tt.taxonomy ASC,tt.term_id ASC,t.slug ASC",
    ];
}

/**
 * Project captured Task 10A relationship rows into the six columns read by the QA group query.
 *
 * @param list<array<string, mixed>> $rows
 * @return list<array{object_id:mixed,term_taxonomy_id:mixed,term_order:mixed,taxonomy:mixed,term_id:mixed,slug:mixed}>
 */
function vg_pe008_project_group_relationship_rows(array $rows): array
{
    $fields = ['object_id', 'term_taxonomy_id', 'term_order', 'taxonomy', 'term_id', 'slug'];
    $projected = [];
    foreach ($rows as $row) {
        $projected_row = [];
        foreach ($fields as $field) {
            if (! array_key_exists($field, $row)) {
                throw new InvalidArgumentException('Captured relationship row is missing ' . $field . '.');
            }
            $projected_row[$field] = $row[$field];
        }
        $projected[] = $projected_row;
    }
    return $projected;
}

/**
 * Return the exact canonical serialization size and digest used by protected snapshots.
 *
 * @return array{serialized_bytes:int,sha256:string}
 */
function vg_pe008_serialized_fingerprint(mixed $value): array
{
    $previous_precision = ini_get('serialize_precision');
    if (! is_string($previous_precision) || ini_set('serialize_precision', '-1') === false) {
        throw new RuntimeException('Could not normalize PHP float serialization precision for protected fingerprints.');
    }

    try {
        $serialized = serialize($value);
        return [
            'serialized_bytes' => strlen($serialized),
            'sha256' => hash('sha256', $serialized),
        ];
    } finally {
        ini_set('serialize_precision', $previous_precision);
    }
}

/**
 * Fingerprint rows already captured by the caller. This function performs no database access.
 *
 * @param list<array<string, mixed>> $posts
 * @param list<array<string, mixed>> $postmeta
 * @param list<array<string, mixed>> $term_relationships
 * @return array{post_count:int,postmeta_count:int,term_relationship_count:int,posts_sha256:string,postmeta_sha256:string,term_relationships_sha256:string}
 */
function vg_pe008_group_fingerprint_from_rows(array $posts, array $postmeta, array $term_relationships): array
{
    return [
        'post_count' => count($posts),
        'postmeta_count' => count($postmeta),
        'term_relationship_count' => count($term_relationships),
        'posts_sha256' => vg_pe008_serialized_fingerprint($posts)['sha256'],
        'postmeta_sha256' => vg_pe008_serialized_fingerprint($postmeta)['sha256'],
        'term_relationships_sha256' => vg_pe008_serialized_fingerprint($term_relationships)['sha256'],
    ];
}
