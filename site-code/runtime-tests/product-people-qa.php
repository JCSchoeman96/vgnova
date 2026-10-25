<?php
declare(strict_types=1);

/**
 * Task 7A guarded Product People runtime QA.
 *
 * Creates only uniquely owned temporary records (marker: _vg_task_7a_qa_owner = "[QA][AUTOMATED] Task 7A <token>"),
 * exercises the Product People resolver through ACF and guarded raw-meta injection, hard-deletes every owned record,
 * and proves protected runtime state is unchanged. Run inside the loaded runtime with `wp eval-file`.
 */

use Voelgoed\PlatformCore\Event\EventParticipationRepository;
use Voelgoed\PlatformCore\Event\EventParticipationService;
use Voelgoed\PlatformCore\Product\ProductContributionRepository;
use Voelgoed\PlatformCore\Product\ProductContributionService;
use Voelgoed\PlatformCore\Product\ProductPeopleFields;
use Voelgoed\PlatformCore\Product\ProductPeopleResolver;
use Voelgoed\PlatformCore\Product\ProductRoleVocabulary;

if (! defined('ABSPATH')) {
    echo json_encode([
        'result' => 'FAIL',
        'phase' => 'bootstrap',
        'mutation_started' => false,
        'failures' => [[
            'phase' => 'bootstrap',
            'message' => 'Run this integration check inside the loaded WordPress runtime.',
        ]],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(1);
}

const VG_PRODUCT_PEOPLE_QA_OWNER_META = '_vg_task_7a_qa_owner';
const VG_PRODUCT_PEOPLE_QA_OWNER_LABEL = '[QA][AUTOMATED] Task 7A';
const VG_PRODUCT_PEOPLE_QA_SNIPPET_HASHES = [
    '9-acf-product-copy.php' => '58e8035502b30397b9659f952e4d49bbdbd7cdbd15070e37e5b07ebaa5ce2f70',
    '10-vg-bricks-medewerker-queries.php' => 'e1b7c014cd15a673d663426403a2024a21a1286075c355015f4e217d7e498fa2',
];
const VG_PRODUCT_PEOPLE_QA_PROTECTED_PRODUCTS = [765, 767, 768];
const VG_PRODUCT_PEOPLE_QA_PROTECTED_EVENT = 763;
const VG_PRODUCT_PEOPLE_QA_PROTECTED_PROFILES = [753, 754, 755, 756, 757];
const VG_PRODUCT_PEOPLE_QA_PROTECTED_TEMPLATES = [124, 772, 774];
const VG_PRODUCT_PEOPLE_QA_PROTECTED_COMPONENTS = ['6b463f', '194bba'];
const VG_PRODUCT_PEOPLE_QA_CANONICAL_KEYS = [
    'product_people_mode',
    '_product_people_mode',
    'product_people_override',
    '_product_people_override',
];
const VG_PRODUCT_PEOPLE_QA_VOLATILE_META = ['_edit_lock', '_edit_last'];
const VG_PRODUCT_PEOPLE_QA_LEGACY_SOURCE_FIELD = 'produk_bron_event';
const VG_PRODUCT_PEOPLE_QA_LEGACY_MEDEWERKER_FIELD = 'produk_medewerkers';

require_once __DIR__ . '/product-people-qa-contributions.php';

function vg_product_people_qa_check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

/** Order-preserving integer IDs from an ACF value. */
function vg_product_people_qa_ints(mixed $value): array
{
    if ($value === null || $value === false || $value === '') {
        return [];
    }
    $values = is_array($value) ? $value : [$value];
    $ids = [];
    foreach ($values as $item) {
        if (is_object($item) && isset($item->ID)) {
            $ids[] = (int) $item->ID;
        } elseif (is_array($item) && isset($item['ID'])) {
            $ids[] = (int) $item['ID'];
        } elseif (is_int($item) || (is_string($item) && ctype_digit($item))) {
            $ids[] = (int) $item;
        } else {
            $ids[] = -1;
        }
    }

    return $ids;
}

function vg_product_people_qa_sorted_ids(mixed $value): array
{
    $ids = vg_product_people_qa_ints($value);
    sort($ids);

    return $ids;
}

require_once __DIR__ . '/product-people-qa-contributions.php';

function vg_product_people_qa_db_count(string $sql, string $description): int
{
    global $wpdb;
    $value = $wpdb->get_var($sql);
    if ($wpdb->last_error !== '') {
        throw new RuntimeException('Could not read ' . $description . ': ' . $wpdb->last_error);
    }

    return (int) $value;
}

function vg_product_people_qa_post_count(string $post_type): int
{
    global $wpdb;

    return vg_product_people_qa_db_count(
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", $post_type),
        'post type ' . $post_type
    );
}

function vg_product_people_qa_meta_count(int $post_id): int
{
    global $wpdb;

    return vg_product_people_qa_db_count(
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d", $post_id),
        'metadata for post ' . $post_id
    );
}

function vg_product_people_qa_relationship_count(int $post_id): int
{
    global $wpdb;

    return vg_product_people_qa_db_count(
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE object_id = %d", $post_id),
        'term relationships for post ' . $post_id
    );
}

/** @return array<string, int> Every post type and status count, including trash, drafts, and revisions. */
function vg_product_people_qa_post_type_counts(): array
{
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT post_type, post_status, COUNT(*) AS n FROM {$wpdb->posts} GROUP BY post_type, post_status ORDER BY post_type, post_status",
        ARRAY_A
    );
    if ($wpdb->last_error !== '' || ! is_array($rows)) {
        throw new RuntimeException('Could not read post type counts: ' . $wpdb->last_error);
    }
    $counts = [];
    foreach ($rows as $row) {
        $counts[$row['post_type'] . '|' . $row['post_status']] = (int) $row['n'];
    }
    ksort($counts);

    return $counts;
}

function vg_product_people_qa_token_exists(string $token): bool
{
    global $wpdb;
    $like = '%' . $wpdb->esc_like($token) . '%';
    $post_matches = vg_product_people_qa_db_count(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title LIKE %s OR post_name LIKE %s OR post_content LIKE %s OR post_excerpt LIKE %s",
            $like,
            $like,
            $like,
            $like
        ),
        'run token in posts'
    );
    $meta_matches = vg_product_people_qa_db_count(
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_value LIKE %s", $like),
        'run token in post metadata'
    );
    $option_matches = vg_product_people_qa_db_count(
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_value LIKE %s", $like),
        'run token in options'
    );

    return $post_matches > 0 || $meta_matches > 0 || $option_matches > 0;
}

function vg_product_people_qa_terms(int $post_id, string $taxonomy): array
{
    $terms = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'all']);
    if (is_wp_error($terms)) {
        throw new RuntimeException($terms->get_error_message());
    }
    $result = array_map(static fn (WP_Term $term): array => ['id' => (int) $term->term_id, 'slug' => $term->slug], $terms);
    usort($result, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);

    return $result;
}

function vg_product_people_qa_vocab(string $taxonomy): array
{
    $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
    if (is_wp_error($terms)) {
        throw new RuntimeException('Could not read ' . $taxonomy . ': ' . $terms->get_error_message());
    }
    $result = array_map(static fn (WP_Term $term): array => [
        'id' => (int) $term->term_id,
        'slug' => $term->slug,
        'name' => $term->name,
        'parent' => (int) $term->parent,
        'count' => (int) $term->count,
    ], $terms);
    usort($result, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);

    return $result;
}

/**
 * Every tc_api_keys record (any status) with the raw fields needed for ownership proof. The secret api_key value is never read.
 *
 * @return list<array{id:int, post_type:string, status:string, api_key_name:mixed, event_name:mixed}>
 */
function vg_product_people_qa_api_key_rows(): array
{
    global $wpdb;
    $ids = array_map('intval', $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'tc_api_keys' ORDER BY ID"));
    if ($wpdb->last_error !== '') {
        throw new RuntimeException('Could not read tc_api_keys IDs: ' . $wpdb->last_error);
    }

    $rows = [];
    foreach ($ids as $id) {
        $post = get_post($id);
        if (! $post instanceof WP_Post) {
            continue;
        }
        $rows[] = [
            'id' => $id,
            'post_type' => $post->post_type,
            'status' => $post->post_status,
            'api_key_name' => get_post_meta($id, 'api_key_name', true),
            'event_name' => get_post_meta($id, 'event_name', true),
        ];
    }

    return $rows;
}

/** Normalized pre/post-QA state of tc_api_keys, compared for exact equality as a protected-state gate. */
function vg_product_people_qa_api_key_snapshot_rows(): array
{
    $snapshot = [];
    foreach (vg_product_people_qa_api_key_rows() as $row) {
        $snapshot[] = [
            'id' => $row['id'],
            'status' => $row['status'],
            'api_key_name' => $row['api_key_name'],
            'event_name' => $row['event_name'],
            'event_refs' => vg_product_people_qa_extract_event_refs($row['event_name']),
        ];
    }

    return $snapshot;
}

/**
 * Hard-deletes only Tickera tc_api_keys side effects provably owned by this run, before the owned Event is deleted.
 * Each candidate is re-classified immediately before deletion. Unexplained records are reported and never deleted.
 *
 * @param list<int> $pre_api_key_ids tc_api_keys IDs captured before any QA mutation.
 * @return array{deleted:list<int>, unexplained:list<array{id:int, reason:string}>, failures:list<array{id:int, message:string}>}
 */
function vg_product_people_qa_delete_tickera_side_effects(int $event_id, string $owner, array $pre_api_key_ids): array
{
    $report = ['deleted' => [], 'unexplained' => [], 'failures' => []];
    $plan = vg_product_people_qa_plan_tickera_side_effects(vg_product_people_qa_api_key_rows(), $pre_api_key_ids, $owner, [$event_id]);

    $report['unexplained'] = $plan['unexplained'];
    foreach ($plan['unexplained'] as $item) {
        $report['failures'][] = ['id' => $item['id'], 'message' => 'tc_api_keys side effect is not provably owned: ' . $item['reason']];
    }

    foreach ($plan['delete'] as $id) {
        try {
            $current = array_values(array_filter(vg_product_people_qa_api_key_rows(), static fn (array $row): bool => $row['id'] === $id));
            $recheck = vg_product_people_qa_plan_tickera_side_effects($current, $pre_api_key_ids, $owner, [$event_id]);
            vg_product_people_qa_check(
                $recheck['delete'] === [$id] && $recheck['unexplained'] === [],
                'ownership could not be re-proven immediately before deletion'
            );
            wp_delete_post($id, true);
            vg_product_people_qa_check(
                get_post($id) === null && vg_product_people_qa_meta_count($id) === 0,
                'deletion did not remove the post and its metadata'
            );
            $report['deleted'][] = $id;
        } catch (Throwable $error) {
            $report['failures'][] = ['id' => $id, 'message' => 'tc_api_keys: ' . $error->getMessage()];
        }
    }

    return $report;
}

/**
 * Whole-record evidence for a protected post: core fields, content digest, and a digest of every raw meta row
 * (volatile editor-lock keys excluded).
 */
function vg_product_people_qa_record(int $post_id): array
{
    global $wpdb;
    $post = get_post($post_id);
    vg_product_people_qa_check($post instanceof WP_Post, 'Protected post ' . $post_id . ' is missing.');

    $rows = $wpdb->get_results(
        $wpdb->prepare("SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d ORDER BY meta_key, meta_value", $post_id),
        ARRAY_A
    );
    if ($wpdb->last_error !== '' || ! is_array($rows)) {
        throw new RuntimeException('Could not read metadata for protected post ' . $post_id . ': ' . $wpdb->last_error);
    }
    $meta = [];
    foreach ($rows as $row) {
        if (in_array($row['meta_key'], VG_PRODUCT_PEOPLE_QA_VOLATILE_META, true)) {
            continue;
        }
        $meta[] = [$row['meta_key'], md5((string) $row['meta_value'])];
    }

    return [
        'post_type' => $post->post_type,
        'status' => $post->post_status,
        'name' => $post->post_name,
        'title' => $post->post_title,
        'modified' => $post->post_modified,
        'content_md5' => md5((string) $post->post_content),
        'meta_row_count' => count($meta),
        'meta_digest' => md5(json_encode($meta, JSON_THROW_ON_ERROR)),
    ];
}

function vg_product_people_qa_snapshot(): array
{
    global $wpdb;

    $products = [];
    foreach (VG_PRODUCT_PEOPLE_QA_PROTECTED_PRODUCTS as $id) {
        $products['product_' . $id] = vg_product_people_qa_record($id) + [
            'acf' => [
                'produk_bron_event' => vg_product_people_qa_ints(get_field(VG_PRODUCT_PEOPLE_QA_LEGACY_SOURCE_FIELD, $id, false)),
                'produk_medewerkers' => vg_product_people_qa_ints(get_field(VG_PRODUCT_PEOPLE_QA_LEGACY_MEDEWERKER_FIELD, $id, false)),
            ],
        ];
    }

    $event = VG_PRODUCT_PEOPLE_QA_PROTECTED_EVENT;
    $event_state = vg_product_people_qa_record($event) + [
        'acf' => ['event_medewerkers' => vg_product_people_qa_ints(get_field('event_medewerkers', $event, false))],
    ];

    $profiles = [];
    foreach (VG_PRODUCT_PEOPLE_QA_PROTECTED_PROFILES as $id) {
        $profiles['profile_' . $id] = vg_product_people_qa_record($id) + [
            'roles' => vg_product_people_qa_terms($id, 'medewerker_rol'),
            'groups' => vg_product_people_qa_terms($id, 'medewerker_groep'),
            'topics' => vg_product_people_qa_terms($id, 'content_topic'),
            'categories' => vg_product_people_qa_terms($id, 'content_category'),
        ];
    }

    $templates = [];
    foreach (VG_PRODUCT_PEOPLE_QA_PROTECTED_TEMPLATES as $id) {
        $templates['template_' . $id] = vg_product_people_qa_record($id);
    }

    $components = get_option('bricks_components');
    $component_digests = [];
    if (is_array($components)) {
        foreach ($components as $component) {
            $component_id = is_array($component) ? ($component['id'] ?? null) : null;
            if (in_array($component_id, VG_PRODUCT_PEOPLE_QA_PROTECTED_COMPONENTS, true)) {
                $component_digests[(string) $component_id] = md5(serialize($component));
            }
        }
    }
    ksort($component_digests);

    $all_product_ids = array_map('intval', $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' ORDER BY ID"));
    if ($wpdb->last_error !== '') {
        throw new RuntimeException('Could not read Product IDs: ' . $wpdb->last_error);
    }

    $canonical_placeholders = implode(', ', array_fill(0, count(VG_PRODUCT_PEOPLE_QA_CANONICAL_KEYS), '%s'));
    $canonical_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT pm.post_id, pm.meta_key, pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = 'product' AND pm.meta_key IN ({$canonical_placeholders}) ORDER BY pm.post_id, pm.meta_key",
        ...VG_PRODUCT_PEOPLE_QA_CANONICAL_KEYS
    ), ARRAY_A);
    if ($wpdb->last_error !== '' || ! is_array($canonical_rows)) {
        throw new RuntimeException('Could not read canonical Product People metadata: ' . $wpdb->last_error);
    }

    $event_name_rows = $wpdb->get_results(
        "SELECT pm.post_id, pm.meta_key, pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = 'product' AND pm.meta_key = '_event_name' ORDER BY pm.post_id",
        ARRAY_A
    );
    if ($wpdb->last_error !== '' || ! is_array($event_name_rows)) {
        throw new RuntimeException('Could not read _event_name metadata: ' . $wpdb->last_error);
    }

    $snippet_hashes = [];
    foreach (VG_PRODUCT_PEOPLE_QA_SNIPPET_HASHES as $file => $_expected) {
        $path = WP_CONTENT_DIR . '/fluent-snippet-storage/' . $file;
        $snippet_hashes[$file] = is_file($path) ? hash_file('sha256', $path) : null;
    }

    $term_vocab = [];
    foreach (['content_topic', 'event_role'] as $taxonomy) {
        $term_vocab[$taxonomy] = vg_product_people_qa_vocab($taxonomy);
    }

    return [
        'products' => $products,
        'all_product_ids' => $all_product_ids,
        'canonical_product_meta_rows' => $canonical_rows,
        'event_name_meta_rows' => $event_name_rows,
        'event' => $event_state,
        'profiles' => $profiles,
        'templates' => $templates,
        'bricks_components' => [
            'option_md5' => is_array($components) ? md5(serialize($components)) : null,
            'component_digests' => $component_digests,
        ],
        'snippet_hashes' => $snippet_hashes,
        'vocabularies' => $term_vocab,
        'post_type_counts' => vg_product_people_qa_post_type_counts(),
        'tickera_api_keys' => vg_product_people_qa_api_key_snapshot_rows(),
        'person_count' => vg_product_people_qa_post_count('person'),
        'event_participation_count' => vg_product_people_qa_post_count(EventParticipationRepository::POST_TYPE),
    ];
}

/** Record ownership on a created post immediately; a conflicting marker is never overwritten. */
function vg_product_people_qa_mark(int $post_id, string $post_type, string $owner): void
{
    vg_product_people_qa_check($post_id > 0, 'Cannot mark an invalid post ID.');
    $post = get_post($post_id);
    vg_product_people_qa_check($post instanceof WP_Post && $post->post_type === $post_type, 'Created QA record ' . $post_id . ' has an unexpected post type.');

    $marker = get_post_meta($post_id, VG_PRODUCT_PEOPLE_QA_OWNER_META, true);
    vg_product_people_qa_check(
        $marker === '' || $marker === false || $marker === null || $marker === $owner,
        'Created QA record ' . $post_id . ' has a conflicting ownership marker.'
    );
    if ($marker !== $owner) {
        update_post_meta($post_id, VG_PRODUCT_PEOPLE_QA_OWNER_META, $owner);
    }
    vg_product_people_qa_check(get_post_meta($post_id, VG_PRODUCT_PEOPLE_QA_OWNER_META, true) === $owner, 'Could not verify ownership marker on QA record ' . $post_id . '.');
}

/**
 * Hard-delete only proven-owned records, in dependency order: Product, participations, Persons, Event.
 * Each record is deleted only when its exact post type and owner marker match; a failed proof retains the record.
 *
 * @param list<int> $pre_api_key_ids tc_api_keys IDs captured before any QA mutation.
 * @param array{tickera_side_effects?: array} $report Receives the Tickera side-effect cleanup evidence.
 * @return list<array{id:int, message:string}>
 */
function vg_product_people_qa_cleanup(array $owned, string $owner, array $pre_api_key_ids, array &$report): array
{
    $failures = [];
    $dependencies_clear = true;

    $delete = static function (int $post_id, string $post_type, string $owner, ?callable $endpoint_check = null) use (&$failures): bool {
        try {
            if ($post_id <= 0) {
                throw new RuntimeException('Tracked ID is invalid.');
            }
            $post = get_post($post_id);
            if ($post !== null) {
                if (! ($post instanceof WP_Post)) {
                    throw new RuntimeException('Cleanup could not verify the tracked post object.');
                }
                if ($post->post_type !== $post_type) {
                    throw new RuntimeException('Cleanup refused because the tracked ID is no longer a ' . $post_type . ' post.');
                }
                $marker = get_post_meta($post_id, VG_PRODUCT_PEOPLE_QA_OWNER_META, true);
                if ($marker !== $owner) {
                    throw new RuntimeException('Cleanup refused because the ownership marker is missing or conflicts.');
                }
                if ($endpoint_check !== null) {
                    $endpoint_check();
                }
                wp_delete_post($post_id, true);
            }
            if (get_post($post_id) !== null
                || vg_product_people_qa_meta_count($post_id) !== 0
                || vg_product_people_qa_relationship_count($post_id) !== 0
            ) {
                throw new RuntimeException('Cleanup did not remove the post, metadata, and term relationships.');
            }

            return true;
        } catch (Throwable $error) {
            $failures[] = ['id' => $post_id, 'message' => $post_type . ': ' . $error->getMessage()];

            return false;
        }
    };

    $contributions_clear = vg_product_people_qa_delete_owned_contributions(
        $owned['contributions'],
        $owner,
        static fn (int $id, string $type, string $owner_marker, ?callable $endpoint_check = null): bool => $delete($id, $type, $owner_marker, $endpoint_check)
    );
    $dependencies_clear = $contributions_clear && $dependencies_clear;

    if ($owned['product'] > 0) {
        $dependencies_clear = $delete((int) $owned['product'], 'product', $owner) && $dependencies_clear;
    }

    $participations_clear = true;
    foreach ($owned['participations'] as $participation_id => $endpoints) {
        $participation_id = (int) $participation_id;
        $ok = $delete(
            $participation_id,
            EventParticipationRepository::POST_TYPE,
            $owner,
            static function () use ($participation_id, $endpoints): void {
                if ((int) get_field(EventParticipationRepository::EVENT_FIELD, $participation_id) !== (int) $endpoints['event_id']
                    || (int) get_field(EventParticipationRepository::PERSON_FIELD, $participation_id) !== (int) $endpoints['person_id']
                ) {
                    throw new RuntimeException('Cleanup refused because a tracked participation endpoint changed.');
                }
            }
        );
        $participations_clear = $participations_clear && $ok;
    }
    $dependencies_clear = $dependencies_clear && $participations_clear;

    $persons_clear = true;
    $tracked_persons = array_filter(array_map('intval', $owned['persons']), static fn (int $id): bool => $id > 0);
    if ($dependencies_clear) {
        foreach ($tracked_persons as $person_id) {
            $persons_clear = $delete($person_id, 'person', $owner) && $persons_clear;
        }
    } elseif ($tracked_persons !== []) {
        $failures[] = ['id' => 0, 'message' => 'Persons retained because not all tracked participations were verified as removed.'];
        $persons_clear = false;
    }
    $dependencies_clear = $dependencies_clear && $persons_clear;

    if ($owned['event'] > 0) {
        if ($dependencies_clear) {
            // Tickera side effects are removed while the owned Event still proves their ownership, then the Event goes.
            $side_effects = vg_product_people_qa_delete_tickera_side_effects((int) $owned['event'], $owner, $pre_api_key_ids);
            $report['tickera_side_effects'] = $side_effects;
            if ($side_effects['failures'] === []) {
                $delete((int) $owned['event'], 'tc_events', $owner);
            } else {
                foreach ($side_effects['failures'] as $side_effect_failure) {
                    $failures[] = ['id' => $side_effect_failure['id'], 'message' => 'Tickera side effect: ' . $side_effect_failure['message']];
                }
                $failures[] = ['id' => (int) $owned['event'], 'message' => 'Event retained because Tickera side effects were not verified as removed.'];
            }
        } else {
            $failures[] = ['id' => (int) $owned['event'], 'message' => 'Event retained because dependent QA records were not verified as removed.'];
        }
    }

    return $failures;
}

$preflight_failure = null;
try {
    vg_product_people_qa_check(class_exists(ProductPeopleFields::class), 'Platform Core ProductPeopleFields is not loaded.');
    vg_product_people_qa_check(class_exists(ProductPeopleResolver::class), 'Platform Core ProductPeopleResolver is not loaded.');
    vg_product_people_qa_check(class_exists(ProductContributionRepository::class), 'Platform Core ProductContributionRepository is not loaded.');
    vg_product_people_qa_check(class_exists(ProductRoleVocabulary::class), 'Platform Core ProductRoleVocabulary is not loaded.');
    vg_product_people_qa_check(class_exists(ProductContributionService::class), 'Platform Core ProductContributionService is not loaded.');
    vg_product_people_qa_assert_approved_product_role_vocabulary();
    vg_product_people_qa_check(class_exists(EventParticipationRepository::class), 'Platform Core EventParticipationRepository is not loaded.');
    vg_product_people_qa_check(class_exists(EventParticipationService::class), 'Platform Core EventParticipationService is not loaded.');
    foreach (['product', 'tc_events', 'person', EventParticipationRepository::POST_TYPE, ProductContributionRepository::POST_TYPE] as $post_type) {
        vg_product_people_qa_check(post_type_exists($post_type), 'Required post type ' . $post_type . ' is not registered.');
    }

    // Locked runtime schema for the Task 7A Product People fields.
    $group = acf_get_field_group('group_vg_product_people');
    vg_product_people_qa_check(is_array($group) && ($group['active'] ?? false) === true, 'Product People ACF group must exist and be active.');
    $mode_field = acf_get_field(ProductPeopleFields::MODE_FIELD_KEY);
    vg_product_people_qa_check(is_array($mode_field), 'Product People mode field must be registered.');
    vg_product_people_qa_check(
        $mode_field['type'] === 'select'
            && $mode_field['name'] === ProductPeopleFields::MODE_FIELD
            && $mode_field['choices'] === ['event' => 'Event-derived', 'manual' => 'Manual']
            && (int) $mode_field['allow_null'] === 1
            && (int) $mode_field['required'] === 0
            && (int) $mode_field['multiple'] === 0
            && $mode_field['return_format'] === 'value'
            && $mode_field['default_value'] === '',
        'product_people_mode does not match the locked schema.'
    );
    vg_product_people_qa_check(
        is_string($mode_field['instructions'] ?? null)
            && str_contains((string) $mode_field['instructions'], 'Product Contributions'),
        'Manual-mode guidance must direct editors to Product Contributions.'
    );
    vg_product_people_qa_check(
        acf_get_field('field_vg_product_people_override') === false,
        'product_people_override must not remain registered in the Product People group.'
    );
    $legacy_source = acf_get_field('field_produk_bron_event');
    $legacy_medewerkers = acf_get_field('field_produk_medewerkers');
    vg_product_people_qa_check(
        is_array($legacy_source) && $legacy_source['type'] === 'post_object' && $legacy_source['post_type'] === ['tc_events'] && $legacy_source['return_format'] === 'id' && (int) $legacy_source['multiple'] === 0,
        'produk_bron_event must remain the legacy tc_events post_object field.'
    );
    vg_product_people_qa_check(
        is_array($legacy_medewerkers) && $legacy_medewerkers['type'] === 'relationship' && $legacy_medewerkers['post_type'] === ['medewerker'],
        'produk_medewerkers must remain the legacy medewerker relationship field.'
    );

    vg_product_people_qa_check(vg_product_people_qa_post_count('person') === 0, 'Persistent Person count must be zero before QA.');
    vg_product_people_qa_check(vg_product_people_qa_post_count(EventParticipationRepository::POST_TYPE) === 0, 'Persistent event_participation count must be zero before QA.');

    global $wpdb;
    $canonical_nonempty = vg_product_people_qa_db_count(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = 'product' AND pm.meta_key IN (%s, %s, %s, %s) AND pm.meta_value <> ''",
            ...VG_PRODUCT_PEOPLE_QA_CANONICAL_KEYS
        ),
        'non-empty canonical Product People values'
    );
    vg_product_people_qa_check($canonical_nonempty === 0, 'Existing Products already carry non-empty canonical Product People values.');

    foreach (VG_PRODUCT_PEOPLE_QA_SNIPPET_HASHES as $file => $expected_hash) {
        $path = WP_CONTENT_DIR . '/fluent-snippet-storage/' . $file;
        vg_product_people_qa_check(is_file($path) && hash_file('sha256', $path) === $expected_hash, 'Legacy snippet/helper ' . $file . ' does not match its approved hash.');
    }

    $protected_event = get_field('event_medewerkers', VG_PRODUCT_PEOPLE_QA_PROTECTED_EVENT, false);
    vg_product_people_qa_check(vg_product_people_qa_ints($protected_event) === [753, 757], 'Event 763 must retain its exact legacy relationships 753 and 757.');
    vg_product_people_qa_check(vg_product_people_qa_ints(get_field('produk_bron_event', 767, false)) === [763] && vg_product_people_qa_ints(get_field('produk_medewerkers', 767, false)) === [753], 'Product 767 relationships differ from the approved baseline.');
    vg_product_people_qa_check(vg_product_people_qa_ints(get_field('produk_bron_event', 768, false)) === [763] && vg_product_people_qa_sorted_ids(get_field('produk_medewerkers', 768, false)) === [753, 757], 'Product 768 relationships differ from the approved baseline.');
    vg_product_people_qa_check(vg_product_people_qa_ints(get_field('produk_bron_event', 765, false)) === [763], 'Product 765 source Event differs from the approved baseline.');
    foreach (VG_PRODUCT_PEOPLE_QA_PROTECTED_PRODUCTS as $protected_product) {
        vg_product_people_qa_check(get_post_type($protected_product) === 'product', 'Protected Product ' . $protected_product . ' is missing.');
    }

    $before = vg_product_people_qa_snapshot();
} catch (Throwable $error) {
    $preflight_failure = $error;
}

if ($preflight_failure instanceof Throwable) {
    $preflight_report = json_encode([
        'result' => 'FAIL',
        'phase' => 'preflight',
        'mutation_started' => false,
        'failures' => [['phase' => 'preflight', 'message' => $preflight_failure->getMessage()]],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (defined('WP_CLI') && WP_CLI) {
        \WP_CLI::line($preflight_report);
        \WP_CLI::error('Product People runtime QA preflight failed.', 1);
    }
    echo $preflight_report . PHP_EOL;
    throw new RuntimeException('Product People runtime QA preflight failed.');
}

// tc_api_keys IDs that existed before any QA mutation; these are never cleanup candidates.
$pre_api_key_ids = array_map('intval', array_column($before['tickera_api_keys'], 'id'));
$cleanup_report = [];

$token = gmdate('YmdHis') . '-' . bin2hex(random_bytes(8));
$owner = VG_PRODUCT_PEOPLE_QA_OWNER_LABEL . ' ' . $token;
$owned = [
    'product' => 0,
    'event' => 0,
    'persons' => ['A' => 0, 'B' => 0, 'C' => 0],
    'participations' => [],
    'contributions' => [],
];
$scenarios = [];
$scenario_failure = null;
$qa_failure = null;
$cleanup_failures = [];
$malformed_result = 'NOT_RUN';
$acf_save_fired = null;
$resolver = new ProductPeopleResolver();

/** Run one named scenario; the first failure stops further scenarios and falls through to guarded cleanup. */
$run = static function (string $id, string $label, callable $assertion) use (&$scenarios): void {
    try {
        $assertion();
        $scenarios[] = ['id' => $id, 'label' => $label, 'result' => 'PASS'];
    } catch (Throwable $error) {
        $scenarios[] = ['id' => $id, 'label' => $label, 'result' => 'FAIL', 'message' => $error->getMessage()];
        throw $error;
    }
};

/** ACF API write for a legacy source Event; verifies the read-back and falls back to raw meta only when ACF cannot store it. */
$set_source_event = static function (int $product_id, int $event_id) : void {
    update_field(VG_PRODUCT_PEOPLE_QA_LEGACY_SOURCE_FIELD, $event_id, $product_id);
    if (vg_product_people_qa_ints(get_field(VG_PRODUCT_PEOPLE_QA_LEGACY_SOURCE_FIELD, $product_id, false)) !== [$event_id]) {
        update_post_meta($product_id, VG_PRODUCT_PEOPLE_QA_LEGACY_SOURCE_FIELD, $event_id);
        acf_flush_value_cache($product_id, VG_PRODUCT_PEOPLE_QA_LEGACY_SOURCE_FIELD);
    }
    vg_product_people_qa_check(vg_product_people_qa_ints(get_field(VG_PRODUCT_PEOPLE_QA_LEGACY_SOURCE_FIELD, $product_id, false)) === [$event_id], 'The source Event did not read back as ' . $event_id . '.');
};

try {
    vg_runtime_mutation_assert_session('vgnova-local-10039');
    // Create only owned records. Owner marker is recorded immediately after each ID is created.
    $event_id = wp_insert_post([
        'post_type' => 'tc_events',
        'post_status' => 'publish',
        'post_title' => $owner . ' Event',
    ], true);
    vg_product_people_qa_check(! is_wp_error($event_id) && (int) $event_id > 0, 'Could not create the temporary QA Event.');
    $owned['event'] = (int) $event_id;
    vg_product_people_qa_mark($owned['event'], 'tc_events', $owner);
    vg_product_people_qa_check(vg_product_people_qa_ints(get_field('event_medewerkers', $owned['event'], false)) === [], 'The temporary QA Event must start with zero event_medewerkers.');

    foreach (['A' => ['publish', 'Person A'], 'B' => ['publish', 'Person B'], 'C' => ['draft', 'Person C']] as $key => [$status, $label]) {
        $person_id = wp_insert_post([
            'post_type' => 'person',
            'post_status' => $status,
            'post_title' => $owner . ' ' . $label,
        ], true);
        vg_product_people_qa_check(! is_wp_error($person_id) && (int) $person_id > 0, 'Could not create temporary QA Person ' . $key . '.');
        $owned['persons'][$key] = (int) $person_id;
        vg_product_people_qa_mark($owned['persons'][$key], 'person', $owner);
    }
    $person_a = $owned['persons']['A'];
    $person_b = $owned['persons']['B'];
    $person_c = $owned['persons']['C'];

    $product_id = wp_insert_post([
        'post_type' => 'product',
        'post_status' => 'draft',
        'post_title' => $owner . ' Product',
    ], true);
    vg_product_people_qa_check(! is_wp_error($product_id) && (int) $product_id > 0, 'Could not create the temporary QA Product.');
    $owned['product'] = (int) $product_id;
    vg_product_people_qa_mark($owned['product'], 'product', $owner);
    $pid = $owned['product'];
    $event = $owned['event'];

    $service = new EventParticipationService();
    foreach ([[$person_b, 10], [$person_c, 15], [$person_a, 20]] as [$person_id, $display_order]) {
        $created = $service->create($event, $person_id, [], $display_order, 'publish');
        vg_product_people_qa_check(! is_wp_error($created) && (int) $created > 0, 'Could not create a published QA participation for Person ' . $person_id . '.');
        $participation_id = (int) $created;
        $owned['participations'][$participation_id] = ['event_id' => $event, 'person_id' => $person_id];
        vg_product_people_qa_mark($participation_id, EventParticipationRepository::POST_TYPE, $owner);
    }
    vg_product_people_qa_check(get_post_status($pid) === 'draft', 'The QA Product must remain in draft status.');

    // Legacy source Event attaches to the owned draft Product through ACF.
    $set_source_event($pid, $event);

    $set_manual_people = static function (?string $mode, array $people) use ($pid, $owner, &$owned): void {
        vg_product_people_qa_configure_product_people(
            $pid,
            $mode,
            $people,
            $owner,
            $owned['contributions'],
            static fn (int $id, string $type, string $owner_marker) => vg_product_people_qa_mark($id, $type, $owner_marker)
        );
    };

    $run('S01', 'absent mode with valid source Event returns empty', static function () use ($pid, $resolver): void {
        delete_field(ProductPeopleFields::MODE_FIELD, $pid);
        vg_product_people_qa_check(get_post_meta($pid, ProductPeopleFields::MODE_FIELD, true) === '', 'Absent mode must have no stored value before the scenario.');
        vg_product_people_qa_check($resolver->people_for_product($pid) === [], 'Absent mode must resolve to no People.');
    });

    $run('S02', 'ordinary ACF blank save leaves an absent mode unconfigured', static function () use ($pid, $resolver, &$acf_save_fired): void {
        $saved_post = $_POST;
        $listener_fired = false;
        $listener = static function (mixed $saved_post_id) use (&$listener_fired, $pid): void {
            if ((int) $saved_post_id === $pid) {
                $listener_fired = true;
            }
        };
        add_action('acf/save_post', $listener, 1, 1);
        try {
            $_POST = ['acf' => [ProductPeopleFields::MODE_FIELD_KEY => '']];
            acf_save_post($pid);
        } finally {
            remove_action('acf/save_post', $listener, 1);
            $_POST = $saved_post;
        }
        $acf_save_fired = $listener_fired;
        vg_product_people_qa_check($listener_fired, 'The ACF save path did not run for the QA Product.');
        $stored = get_post_meta($pid, ProductPeopleFields::MODE_FIELD, true);
        vg_product_people_qa_check(! in_array($stored, ['event', 'manual'], true), 'A blank ACF save must never store an explicit mode.');
        vg_product_people_qa_check($resolver->people_for_product($pid) === [], 'A blank ACF save must keep the Product unconfigured.');
    });

    $run('S02b', 'ordinary ACF blank save clears an explicit event mode back to unconfigured', static function () use ($pid, $resolver): void {
        update_field(ProductPeopleFields::MODE_FIELD, ProductPeopleFields::MODE_EVENT, $pid);
        vg_product_people_qa_check(get_post_meta($pid, ProductPeopleFields::MODE_FIELD, true) === 'event', 'Mode event must be stored before the blank-save proof.');
        $saved_post = $_POST;
        try {
            $_POST = ['acf' => [ProductPeopleFields::MODE_FIELD_KEY => '']];
            acf_save_post($pid);
        } finally {
            $_POST = $saved_post;
        }
        $stored = get_post_meta($pid, ProductPeopleFields::MODE_FIELD, true);
        vg_product_people_qa_check(! in_array($stored, ['event', 'manual'], true), 'Clearing the mode through ACF must remove the explicit mode.');
        vg_product_people_qa_check($resolver->people_for_product($pid) === [], 'Clearing the mode must return the Product to unconfigured.');
    });

    $run('S03', 'event mode returns published Persons in Event Participation order and excludes draft Persons', static function () use ($pid, $resolver, $person_a, $person_b): void {
        update_field(ProductPeopleFields::MODE_FIELD, ProductPeopleFields::MODE_EVENT, $pid);
        vg_product_people_qa_check($resolver->people_for_product($pid) === [$person_b, $person_a], 'Event mode must return Person B then Person A, excluding draft Person C.');
        vg_product_people_qa_check(get_post_status($pid) === 'draft', 'The Product must still be draft to prove draft Products resolve.');
    });

    $run('S04', 'event mode ignores stale legacy override meta', static function () use ($pid, $resolver, $person_a, $person_b): void {
        update_field(ProductPeopleFields::MODE_FIELD, ProductPeopleFields::MODE_EVENT, $pid);
        update_post_meta($pid, 'product_people_override', [$person_a]);
        vg_product_people_qa_check($resolver->people_for_product($pid) === [$person_b, $person_a], 'Event mode must ignore stale manual authority data.');
    });

    $run('S05', 'manual mode returns Product Contributions in editor order', static function () use ($pid, $resolver, $person_a, $person_b, $set_manual_people): void {
        $set_manual_people(ProductPeopleFields::MODE_MANUAL, [$person_a, $person_b]);
        vg_product_people_qa_check($resolver->people_for_product($pid) === [$person_a, $person_b], 'Manual mode must preserve the contribution order.');
    });

    $run('S06', 'empty manual contributions return empty despite a valid source Event', static function () use ($pid, $resolver, $event, $set_manual_people): void {
        $set_manual_people(ProductPeopleFields::MODE_MANUAL, []);
        vg_product_people_qa_check(vg_product_people_qa_ints(get_field(VG_PRODUCT_PEOPLE_QA_LEGACY_SOURCE_FIELD, $pid, false)) === [$event], 'The source Event must remain valid during the empty manual proof.');
        vg_product_people_qa_check($resolver->people_for_product($pid) === [], 'Manual mode without Product Contributions must resolve to no People.');
    });

    $run('S07', 'role-less and invalid Product Contributions do not leak into manual membership', static function () use ($pid, $resolver, $person_a, $person_b, $owner, &$owned): void {
        vg_product_people_qa_configure_product_people(
            $pid,
            ProductPeopleFields::MODE_MANUAL,
            [$person_a],
            $owner,
            $owned['contributions'],
            static fn (int $id, string $type, string $owner_marker) => vg_product_people_qa_mark($id, $type, $owner_marker)
        );
        vg_product_people_qa_create_owned_contribution(
            $pid,
            $person_b,
            0,
            $owner,
            $owned['contributions'],
            static fn (int $id, string $type, string $owner_marker) => vg_product_people_qa_mark($id, $type, $owner_marker),
            'draft',
            []
        );
        vg_product_people_qa_check($resolver->people_for_product($pid) === [$person_a], 'Role-less draft contributions must not affect manual membership.');
    });

    $run('S08', 'invalid stored mode returns empty', static function () use ($pid, $resolver, $person_a, $set_manual_people): void {
        foreach (['bogus', 'Event'] as $invalid_mode) {
            update_post_meta($pid, ProductPeopleFields::MODE_FIELD, $invalid_mode);
            acf_flush_value_cache($pid, ProductPeopleFields::MODE_FIELD);
            vg_product_people_qa_check($resolver->people_for_product($pid) === [], 'Invalid stored mode "' . $invalid_mode . '" must return no People.');
        }
        $set_manual_people(ProductPeopleFields::MODE_MANUAL, [$person_a]);
    });

    $run('S09', 'wrong-type source Event returns empty', static function () use ($pid, $resolver, $event, $person_a, $set_source_event): void {
        update_field(ProductPeopleFields::MODE_FIELD, ProductPeopleFields::MODE_EVENT, $pid);
        update_field(VG_PRODUCT_PEOPLE_QA_LEGACY_SOURCE_FIELD, $person_a, $pid);
        if (vg_product_people_qa_ints(get_field(VG_PRODUCT_PEOPLE_QA_LEGACY_SOURCE_FIELD, $pid, false)) !== [$person_a]) {
            update_post_meta($pid, VG_PRODUCT_PEOPLE_QA_LEGACY_SOURCE_FIELD, $person_a);
            acf_flush_value_cache($pid, VG_PRODUCT_PEOPLE_QA_LEGACY_SOURCE_FIELD);
        }
        try {
            vg_product_people_qa_check($resolver->people_for_product($pid) === [], 'A Person ID in the source Event field must resolve to no People.');
        } finally {
            $set_source_event($pid, $event);
        }
    });

    $run('S10', 'missing source Event returns empty', static function () use ($pid, $resolver, $event, $set_source_event): void {
        update_post_meta($pid, VG_PRODUCT_PEOPLE_QA_LEGACY_SOURCE_FIELD, 999999999);
        acf_flush_value_cache($pid, VG_PRODUCT_PEOPLE_QA_LEGACY_SOURCE_FIELD);
        try {
            vg_product_people_qa_check($resolver->people_for_product($pid) === [], 'A missing source Event must resolve to no People.');
        } finally {
            $set_source_event($pid, $event);
        }
    });

    $run('S11', 'malformed source Event returns empty', static function () use ($pid, $resolver, $event, $set_source_event, &$malformed_result): void {
        // ACF attempt first; raw meta only to force the explicit malformed state, then restore the valid Event through ACF.
        update_field(VG_PRODUCT_PEOPLE_QA_LEGACY_SOURCE_FIELD, [$event], $pid);
        update_post_meta($pid, VG_PRODUCT_PEOPLE_QA_LEGACY_SOURCE_FIELD, [$event]);
        acf_flush_value_cache($pid, VG_PRODUCT_PEOPLE_QA_LEGACY_SOURCE_FIELD);
        try {
            vg_product_people_qa_check(is_array(get_field(VG_PRODUCT_PEOPLE_QA_LEGACY_SOURCE_FIELD, $pid, false)), 'The malformed source Event state was not established.');
            vg_product_people_qa_check($resolver->people_for_product($pid) === [], 'A malformed source Event must return no People without warnings or fallback.');
            $malformed_result = 'PASS';
        } catch (Throwable $error) {
            $malformed_result = 'FAIL';
            throw $error;
        } finally {
            $set_source_event($pid, $event);
            vg_product_people_qa_check(vg_product_people_qa_ints(get_field(VG_PRODUCT_PEOPLE_QA_LEGACY_SOURCE_FIELD, $pid, false)) === [$event], 'The valid source Event must be restored before continuing.');
        }
    });

    $run('S12', 'manual mode ignores a valid source Event', static function () use ($pid, $resolver, $event, $person_a, $person_b, $set_manual_people): void {
        $set_manual_people(ProductPeopleFields::MODE_MANUAL, [$person_a, $person_b]);
        vg_product_people_qa_check(vg_product_people_qa_ints(get_field(VG_PRODUCT_PEOPLE_QA_LEGACY_SOURCE_FIELD, $pid, false)) === [$event], 'The source Event must be valid during the manual-isolation proof.');
        vg_product_people_qa_check($resolver->people_for_product($pid) === [$person_a, $person_b], 'Manual mode must ignore the source Event entirely.');
    });

    $run('S13', 'legacy produk_medewerkers never influences the canonical result', static function () use ($pid, $resolver, $event, $person_b, $person_a, $set_manual_people): void {
        update_field(VG_PRODUCT_PEOPLE_QA_LEGACY_MEDEWERKER_FIELD, [753], $pid);
        vg_product_people_qa_check(vg_product_people_qa_ints(get_field(VG_PRODUCT_PEOPLE_QA_LEGACY_MEDEWERKER_FIELD, $pid, false)) === [753], 'The legacy Medewerker value must be populated for the isolation proof.');

        $set_manual_people(ProductPeopleFields::MODE_MANUAL, []);
        vg_product_people_qa_check($resolver->people_for_product($pid) === [], 'Manual mode without Product Contributions must not fall back to produk_medewerkers.');

        update_field(ProductPeopleFields::MODE_FIELD, ProductPeopleFields::MODE_EVENT, $pid);
        vg_product_people_qa_check($resolver->people_for_product($pid) === [$person_b, $person_a], 'Event mode must not be influenced by produk_medewerkers.');
        vg_product_people_qa_check(vg_product_people_qa_ints(get_field(VG_PRODUCT_PEOPLE_QA_LEGACY_SOURCE_FIELD, $pid, false)) === [$event], 'The source Event must remain valid during the legacy-isolation proof.');

        delete_field(ProductPeopleFields::MODE_FIELD, $pid);
        vg_product_people_qa_check($resolver->people_for_product($pid) === [], 'Unconfigured mode must not fall back to produk_medewerkers.');
    });
} catch (Throwable $error) {
    $qa_failure = $error;
} finally {
    $cleanup_failures = vg_product_people_qa_cleanup($owned, $owner, $pre_api_key_ids, $cleanup_report);
}

// Post-cleanup audits. Each failure is recorded rather than hidden.
$audit_failures = [];
$audit = static function (string $name, callable $assertion) use (&$audit_failures): void {
    try {
        $assertion();
    } catch (Throwable $error) {
        $audit_failures[] = ['check' => $name, 'message' => $error->getMessage()];
    }
};

$owned_ids = array_values(array_filter([
    (int) $owned['product'],
    (int) $owned['event'],
    ...array_map('intval', array_values($owned['persons'])),
    ...array_map('intval', array_keys($owned['participations'])),
]));
$owned_posts_remaining = 0;
$owned_meta_rows = 0;
$owned_relationship_rows = 0;
foreach ($owned_ids as $owned_id) {
    if ($owned_id > 0 && get_post($owned_id) !== null) {
        ++$owned_posts_remaining;
    }
    $owned_meta_rows += $owned_id > 0 ? vg_product_people_qa_meta_count($owned_id) : 0;
    $owned_relationship_rows += $owned_id > 0 ? vg_product_people_qa_relationship_count($owned_id) : 0;
}

$token_found = null;
$persons_remaining = null;
$participations_remaining = null;
$post_type_counts_equal = false;
$post_type_count_diffs = [];
$protected_state_unchanged = false;
$canonical_baseline_unchanged = false;
$snippet_hashes_unchanged = false;

$audit('run_token_absent', static function () use ($token, &$token_found): void {
    $token_found = vg_product_people_qa_token_exists($token);
    vg_product_people_qa_check(! $token_found, 'The QA run token remains in posts or post metadata.');
});
$audit('owned_records_hard_deleted', static function () use ($owned_posts_remaining, $owned_meta_rows, $owned_relationship_rows): void {
    vg_product_people_qa_check(
        $owned_posts_remaining === 0 && $owned_meta_rows === 0 && $owned_relationship_rows === 0,
        'Owned QA records, metadata, or term relationships remain after cleanup.'
    );
});
$audit('persistent_counts_zero', static function () use (&$persons_remaining, &$participations_remaining): void {
    $persons_remaining = vg_product_people_qa_post_count('person');
    $participations_remaining = vg_product_people_qa_post_count(EventParticipationRepository::POST_TYPE);
    vg_product_people_qa_check($persons_remaining === 0 && $participations_remaining === 0, 'A persistent Person or event_participation remains after cleanup.');
});
$tickera_api_keys_unchanged = false;
$audit('tickera_api_keys_unchanged', static function () use ($before, &$tickera_api_keys_unchanged): void {
    vg_product_people_qa_check(
        vg_product_people_qa_api_key_snapshot_rows() === $before['tickera_api_keys'],
        'tc_api_keys state differs from the exact pre-QA snapshot.'
    );
    $tickera_api_keys_unchanged = true;
});
$after = null;
$audit('protected_state_unchanged', static function () use ($before, &$after, &$protected_state_unchanged, &$post_type_counts_equal, &$post_type_count_diffs, &$canonical_baseline_unchanged, &$snippet_hashes_unchanged): void {
    $after = vg_product_people_qa_snapshot();

    $post_type_counts_equal = $before['post_type_counts'] === $after['post_type_counts'];
    if (! $post_type_counts_equal) {
        foreach (array_unique(array_merge(array_keys($before['post_type_counts']), array_keys($after['post_type_counts']))) as $key) {
            $was = $before['post_type_counts'][$key] ?? 0;
            $now = $after['post_type_counts'][$key] ?? 0;
            if ($was !== $now) {
                $post_type_count_diffs[] = ['post_type_status' => $key, 'before' => $was, 'after' => $now];
            }
        }
    }
    $canonical_baseline_unchanged = $before['all_product_ids'] === $after['all_product_ids']
        && $before['canonical_product_meta_rows'] === $after['canonical_product_meta_rows']
        && $before['canonical_product_meta_rows'] === [];
    $snippet_hashes_unchanged = $before['snippet_hashes'] === $after['snippet_hashes'];

    $protected_before = $before;
    $protected_after = $after;
    unset($protected_before['post_type_counts'], $protected_after['post_type_counts'], $protected_before['person_count'], $protected_after['person_count'], $protected_before['event_participation_count'], $protected_after['event_participation_count']);
    vg_product_people_qa_check($protected_before === $protected_after, 'Protected runtime state changed during Product People QA.');
    vg_product_people_qa_check($post_type_counts_equal, 'Post-type counts differ after cleanup: ' . json_encode($post_type_count_diffs));
    $protected_state_unchanged = true;
});

$failures = [];
if ($preflight_failure instanceof Throwable) {
    $failures[] = ['phase' => 'preflight', 'message' => $preflight_failure->getMessage()];
}
if ($qa_failure instanceof Throwable) {
    $failures[] = ['phase' => 'qa', 'message' => $qa_failure->getMessage()];
}
foreach ($cleanup_failures as $failure) {
    $failures[] = ['phase' => 'cleanup'] + $failure;
}
foreach ($audit_failures as $failure) {
    $failures[] = ['phase' => 'audit'] + $failure;
}
$scenario_results_pass = $scenarios !== [] && array_reduce($scenarios, static fn (bool $carry, array $s): bool => $carry && $s['result'] === 'PASS', true);
if (! $scenario_results_pass && $qa_failure === null) {
    $failures[] = ['phase' => 'qa', 'message' => 'Not every approved scenario passed.'];
}
if ($malformed_result !== 'PASS') {
    $failures[] = ['phase' => 'qa', 'message' => 'malformed_source_event_returns_empty did not pass.'];
}

$report = json_encode([
    'result' => $failures === [] ? 'PASS' : 'FAIL',
    'run_token' => $token,
    'owner_marker' => $owner,
    'owned_ids' => [
        'product' => $owned['product'],
        'event' => $owned['event'],
        'persons' => $owned['persons'],
        'participations' => array_keys($owned['participations']),
    ],
    'scenarios' => $scenarios,
    'malformed_source_event_returns_empty' => $malformed_result,
    'acf_save_path_fired' => $acf_save_fired,
    'before_after_counts' => [
        'persons_before' => $before['person_count'] ?? null,
        'persons_after' => $persons_remaining,
        'event_participation_before' => $before['event_participation_count'] ?? null,
        'event_participation_after' => $participations_remaining,
    ],
    'protected_state_unchanged' => $protected_state_unchanged,
    'tickera_api_keys_unchanged' => $tickera_api_keys_unchanged,
    'tickera_api_keys_pre_qa_count' => count($pre_api_key_ids),
    'canonical_product_meta_baseline_unchanged' => $canonical_baseline_unchanged,
    'legacy_snippet_hashes_unchanged' => $snippet_hashes_unchanged,
    'post_type_counts_equal' => $post_type_counts_equal,
    'cleanup' => [
        'owned_posts_remaining' => $owned_posts_remaining,
        'owned_meta_rows_remaining' => $owned_meta_rows,
        'owned_term_relationships_remaining' => $owned_relationship_rows,
        'run_token_found' => $token_found,
        'persons_remaining' => $persons_remaining,
        'event_participations_remaining' => $participations_remaining,
        'post_type_count_diffs' => $post_type_count_diffs,
        'ownership_cleanup_failures' => $cleanup_failures,
        'tickera_side_effects' => $cleanup_report['tickera_side_effects'] ?? null,
    ],
    'failures' => $failures,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);

if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::line($report);
    if ($failures !== []) {
        \WP_CLI::error('Product People runtime QA failed.', 1);
    }
} else {
    echo $report . PHP_EOL;
    if ($failures !== []) {
        throw new RuntimeException('Product People runtime QA failed.');
    }
}
