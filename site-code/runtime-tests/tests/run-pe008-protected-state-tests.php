<?php
declare(strict_types=1);

$site_code = dirname(__DIR__, 2);
require_once $site_code . '/runtime-tools/pe008-protected-state.php';

function pe008_protected_state_assert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function pe008_protected_state_all_values_are_strings(mixed $value): bool
{
    if (! is_array($value)) {
        return is_string($value);
    }
    foreach ($value as $child) {
        if (! pe008_protected_state_all_values_are_strings($child)) {
            return false;
        }
    }
    return true;
}

$fixture_path = __DIR__ . '/../fixtures/person-1705-fingerprint-capture.json';
$fixture_bytes = file_get_contents($fixture_path);
pe008_protected_state_assert(is_string($fixture_bytes), 'Captured Person 1705 fixture could not be read.');
pe008_protected_state_assert(hash('sha256', $fixture_bytes) === '8bb5f5a701d2554c235e55a7759e942dbada36e6c4a708fbc5b7e53e7310c316', 'Captured Person 1705 fixture bytes changed.');
$fixture = json_decode($fixture_bytes, true, 512, JSON_THROW_ON_ERROR);
$raw_capture_path = __DIR__ . '/../fixtures/person-1705-task10a-capture.raw.json';
$raw_capture_bytes = file_get_contents($raw_capture_path);
pe008_protected_state_assert(is_string($raw_capture_bytes), 'Original Task 10A capture could not be read.');
pe008_protected_state_assert(hash('sha256', $raw_capture_bytes) === 'acba754272b709d5088474acde4bd9733f91744988a16b812bacfdea5dc68fbd', 'Original Task 10A capture bytes changed.');
$raw_capture = json_decode($raw_capture_bytes, true, 512, JSON_THROW_ON_ERROR)['fullStatePerson'];
$capture = $fixture['captured_rows'];
$post = $capture['post'];
$postmeta = $capture['postmeta'];
$task10a_relationships = $capture['taxonomy_relationships_task10a_projection'];
$raw_relationships = $raw_capture['taxonomy_relationships'];
$group_relationships = vg_pe008_project_group_relationship_rows($task10a_relationships);
$previous_precision = ini_get('serialize_precision');
pe008_protected_state_assert(is_string($previous_precision), 'PHP serialize_precision could not be read.');

try {
    $post_row = vg_pe008_serialized_fingerprint($post);
    $post_list = vg_pe008_serialized_fingerprint([$post]);
    $postmeta_rows = vg_pe008_serialized_fingerprint($postmeta);
    $task10a_relationship_rows = vg_pe008_serialized_fingerprint($task10a_relationships);
    $group_relationship_rows = vg_pe008_serialized_fingerprint($group_relationships);

    pe008_protected_state_assert($post_row === [
        'serialized_bytes' => 779,
        'sha256' => '792654d2d6a47aec75ef635e4bc51e3a67d35f621dfef021f1dcd4f97e6e080f',
    ], 'Task 10A single-row post digest or byte length differs.');
    pe008_protected_state_assert($post_list === [
        'serialized_bytes' => 789,
        'sha256' => 'f2a980eb64045700dc3a4ca07a7950ec3cb1daea14e4a400ac48e6837b2e9b5b',
    ], 'Product Contribution post-list digest or byte length differs.');
    pe008_protected_state_assert($postmeta_rows === [
        'serialized_bytes' => 137,
        'sha256' => '0d043dd56c404e0ddb6d7ad496249b770d296a6cd72290bfd7774189e5c820e5',
    ], 'Postmeta control digest or byte length differs.');
    pe008_protected_state_assert($task10a_relationship_rows === [
        'serialized_bytes' => 494,
        'sha256' => '220c90c6c8838738030fe3ae3c3011446cd9d6b6b0162cba8c6597d438ab13b6',
    ], 'Task 10A nine-column relationship digest or byte length differs.');
    pe008_protected_state_assert($group_relationship_rows === [
        'serialized_bytes' => 364,
        'sha256' => '1d347313f990ba8063fe34926ef537e85d2dc4bdc575e5369338be45b9bdf7fb',
    ], 'Product Contribution six-column relationship digest or byte length differs.');

    $wpdb_names = (object) [
        'posts' => 'wp_posts',
        'postmeta' => 'wp_postmeta',
        'term_relationships' => 'wp_term_relationships',
        'term_taxonomy' => 'wp_term_taxonomy',
        'terms' => 'wp_terms',
    ];
    $queries = vg_pe008_group_fingerprint_queries($wpdb_names, [1705]);
    $expected_contract = $fixture['product_contribution_group_contract'];
    foreach ([
        'posts' => 'post_query',
        'postmeta' => 'postmeta_query',
        'term_relationships' => 'term_relationships_query',
    ] as $query_name => $fixture_key) {
        $expected_sql = str_replace(
            ['{posts}', '{postmeta}', '{term_relationships}', '{term_taxonomy}', '{terms}'],
            ['wp_posts', 'wp_postmeta', 'wp_term_relationships', 'wp_term_taxonomy', 'wp_terms'],
            $expected_contract[$fixture_key]
        );
        pe008_protected_state_assert($queries[$query_name] === $expected_sql, 'Pinned SQL projection or ordering differs for ' . $query_name . '.');
    }
    pe008_protected_state_assert($expected_contract['term_relationship_projection'] === ['object_id', 'term_taxonomy_id', 'term_order', 'taxonomy', 'term_id', 'slug'], 'Pinned relationship projection changed.');
    pe008_protected_state_assert($fixture['capture_authority']['post_query'] === "SELECT * FROM {posts} WHERE post_type = 'person' ORDER BY ID ASC", 'Task 10A Person query changed.');
    pe008_protected_state_assert($fixture['capture_authority']['postmeta_query'] === 'SELECT * FROM {postmeta} WHERE post_id = 1705 ORDER BY meta_id ASC', 'Task 10A postmeta query changed.');
    pe008_protected_state_assert($fixture['capture_authority']['taxonomy_projection'] === ['object_id', 'term_taxonomy_id', 'term_order', 'taxonomy', 'term_id', 'slug', 'name', 'parent', 'count'], 'Task 10A relationship projection changed.');
    pe008_protected_state_assert($fixture['capture_authority']['canonicalization'] === 'hash(\'sha256\', serialize($value)); PHP serialize_precision=-1', 'Task 10A canonicalization rule changed.');
    pe008_protected_state_assert($raw_capture['post'] === $post && $raw_capture['postmeta'] === $postmeta && $raw_relationships === $task10a_relationships, 'Derived fixture rows differ from the preserved original Task 10A capture.');
    pe008_protected_state_assert($fixture['task10a_hashes'] === [
        'post_row' => $post_row['sha256'],
        'postmeta_rows' => $postmeta_rows['sha256'],
        'taxonomy_relationship_rows' => $task10a_relationship_rows['sha256'],
    ], 'Task 10A source fingerprints do not reproduce from preserved captured rows.');
    pe008_protected_state_assert(array_keys($post) === [
        'ID', 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_excerpt',
        'post_status', 'comment_status', 'ping_status', 'post_password', 'post_name', 'to_ping', 'pinged',
        'post_modified', 'post_modified_gmt', 'post_content_filtered', 'post_parent', 'guid', 'menu_order',
        'post_type', 'post_mime_type', 'comment_count',
    ], 'Captured post SELECT * column ordering changed.');
    pe008_protected_state_assert(array_keys($postmeta[0]) === ['meta_id', 'post_id', 'meta_key', 'meta_value'], 'Captured postmeta column ordering changed.');
    pe008_protected_state_assert(array_keys($group_relationships[0]) === ['object_id', 'term_taxonomy_id', 'term_order', 'taxonomy', 'term_id', 'slug'], 'Six-column relationship projection ordering changed.');
    pe008_protected_state_assert(pe008_protected_state_all_values_are_strings([$post, $postmeta, $task10a_relationships]), 'Captured database scalar values are not all PHP strings.');
    pe008_protected_state_assert($post['ID'] === '1705' && $post['post_type'] === 'person' && $post['post_status'] === 'publish' && $post['post_title'] === 'Lynette Beer' && $post['post_name'] === 'lynette-beer', 'Captured core Person identity differs from Task 10A.');
    pe008_protected_state_assert($post['post_content'] === '' && $post['post_excerpt'] === '' && $raw_capture['raw_acf_fields'] === [], 'Captured Person editorial fields are not empty.');
    pe008_protected_state_assert(array_column($task10a_relationships, 'name') === ['Mentor', 'Kenner'], 'Captured Person Type assignments differ from Task 10A.');
    pe008_protected_state_assert(count(array_filter($task10a_relationships, static fn (array $row): bool => $row['taxonomy'] === 'content_topic')) === 0, 'Captured Person has a content_topic relationship.');

    $group = vg_pe008_group_fingerprint_from_rows([$post], $postmeta, $group_relationships);
    pe008_protected_state_assert($group === [
        'post_count' => 1,
        'postmeta_count' => 1,
        'term_relationship_count' => 2,
        'posts_sha256' => 'f2a980eb64045700dc3a4ca07a7950ec3cb1daea14e4a400ac48e6837b2e9b5b',
        'postmeta_sha256' => '0d043dd56c404e0ddb6d7ad496249b770d296a6cd72290bfd7774189e5c820e5',
        'term_relationships_sha256' => '1d347313f990ba8063fe34926ef537e85d2dc4bdc575e5369338be45b9bdf7fb',
    ], 'The read-only group fingerprint differs from the captured Product Contribution query result.');
    pe008_protected_state_assert($group === vg_pe008_group_fingerprint_from_rows([$post], $postmeta, $group_relationships), 'The same captured rows produced different hashes on repeat.');
} finally {
    ini_set('serialize_precision', $previous_precision);
}

$helper_source = file_get_contents($site_code . '/runtime-tools/pe008-protected-state.php');
$deployment_source = file_get_contents($site_code . '/bin/pe008-bootstrap-platform-core-runtime.php');
$integrity_source = file_get_contents(__DIR__ . '/../platform-core-runtime-integrity.php');
pe008_protected_state_assert(is_string($helper_source) && is_string($deployment_source) && is_string($integrity_source), 'PE-008 source could not be read for QA isolation checks.');
foreach ([$helper_source, $deployment_source, $integrity_source] as $source) {
    pe008_protected_state_assert(! str_contains($source, 'product-contribution-qa.php'), 'PE-008 fingerprint or deployment code references the live QA harness.');
    pe008_protected_state_assert(! preg_match('/\bvg_product_contribution_qa_run\s*\(/', $source), 'PE-008 fingerprint or deployment code invokes live Product Contribution QA.');
}
pe008_protected_state_assert(! preg_match('/\b(?:include|include_once|require|require_once)\b/', $helper_source), 'Read-only fingerprint library contains an executable include.');

$inventory_path = $site_code . '/runtime-tools/mutating-entrypoints.json';
$inventory = json_decode(file_get_contents($inventory_path), true, 512, JSON_THROW_ON_ERROR);
$entries = array_column($inventory['entries'], null, 'path');
pe008_protected_state_assert(($entries['site-code/runtime-tools/pe008-protected-state.php']['classification'] ?? null) === 'library', 'Fingerprint helper is not classified as a library.');
pe008_protected_state_assert(($entries['site-code/runtime-tests/tests/run-pe008-protected-state-tests.php']['classification'] ?? null) === 'test_only', 'Fingerprint regression is not classified as test-only.');

fwrite(STDOUT, "PE-008 protected-state fingerprint regression passed.\n");
