<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Event\EventParticipationRepository;
use Voelgoed\PlatformCore\Event\EventParticipationService;
use Voelgoed\PlatformCore\Product\PersonProductsBricks;
use Voelgoed\PlatformCore\Product\ProductContributionRepository;
use Voelgoed\PlatformCore\Product\ProductContributionService;
use Voelgoed\PlatformCore\Product\ProductPeopleFields;
use Voelgoed\PlatformCore\Product\ProductPeopleResolver;
use Voelgoed\PlatformCore\Product\ProductRoleVocabulary;

if (! defined('ABSPATH') && ! defined('VG_PRODUCT_CONTRIBUTION_QA_LIBRARY_ONLY')) {
    echo json_encode([
        'result' => 'FAIL',
        'mutation_started' => false,
        'cleanup_attempted' => false,
        'cleanup_errors' => [],
        'owned_records_remaining' => [],
        'failures' => [['phase' => 'bootstrap', 'message' => 'Run this harness with WP-CLI eval-file in the loaded WordPress runtime.']],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(1);
}

const VG_PRODUCT_CONTRIBUTION_QA_OWNER_META = '_vg_task_8c_product_contribution_qa_owner';
const VG_PRODUCT_CONTRIBUTION_QA_OWNER_LABEL = '[QA][AUTOMATED] Product Contribution';
const VG_PRODUCT_CONTRIBUTION_QA_SOURCE_PATH = '/home/jcschoeman96/projects/current/vgnova/site-code/acf-json';
const VG_PRODUCT_CONTRIBUTION_QA_PLATFORM_CORE_AUTHORITY = [
    'artifact_name' => 'voelgoed-platform-core-ab7e7d853772fb920237e47d8fa83c72ed389fba',
    'source_commit' => 'ab7e7d853772fb920237e47d8fa83c72ed389fba',
    'schema_version' => 3,
    'file_count' => 55,
    'tree_sha256' => '5816b80ac4355782523aa4baa82bd9e55dd87326f15972f287ff1afa7567482a',
    'archive_sha256' => '22d998019e3fe3d93d675250a26eba5be3dbc248badd9be4d7507fbdf9cffdb9',
    'manifest_sha256' => 'ceba4caa9394ca3dc6dedca2794199c921fed85118b9d4a4d6606e711a8d12a8',
];
const VG_PRODUCT_CONTRIBUTION_QA_EXPECTED_PRODUCT_IDS = [525, 527, 529, 531, 642, 765, 767, 768, 842, 844, 845, 846, 905, 908, 911, 914, 917, 920, 923, 1186];

require_once __DIR__ . '/product-people-qa-side-effects.php';
require_once __DIR__ . '/platform-core-runtime-integrity.php';

function vg_product_contribution_qa_check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

/** @param array<string, mixed> $report */
function vg_product_contribution_qa_assert_platform_core_integrity(array $report): void
{
    if (($report['exact'] ?? false) !== true) {
        throw new RuntimeException(
            'Active Platform Core does not match the pinned PE-005 artifact: '
            . json_encode($report, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );
    }
}

/** @param array<string, mixed> $context @param array<string, mixed> $authority */
function vg_product_contribution_qa_record_platform_core_integrity(
    array &$context,
    string $phase,
    string $plugin_root,
    string $manifest_path,
    array $authority
): void {
    $report_key = match ($phase) {
        'preflight' => 'platform_core_integrity',
        'post_cleanup' => 'platform_core_integrity_post_cleanup',
        default => throw new InvalidArgumentException('Unknown Platform Core integrity phase: ' . $phase),
    };
    $context[$report_key] = vg_product_contribution_qa_platform_core_integrity_report(
        $plugin_root,
        $manifest_path,
        $authority
    );
    vg_product_contribution_qa_assert_platform_core_integrity($context[$report_key]);
}

function vg_product_contribution_qa_flush_person_endpoint_cache(int $contribution_id): void
{
    if (function_exists('acf_flush_value_cache')) {
        acf_flush_value_cache($contribution_id, ProductContributionRepository::PERSON_FIELD);
    }
    clean_post_cache($contribution_id);
}

function vg_product_contribution_qa_with_temporary_contribution_person_endpoint(
    int $contribution_id,
    int $temporary_person_id,
    int $original_person_id,
    callable $assertion
): mixed {
    try {
        vg_product_contribution_qa_check(
            update_field(ProductContributionRepository::PERSON_FIELD, $temporary_person_id, $contribution_id) !== false,
            'Could not install an owned malformed endpoint for the read-filter assertion.'
        );
        vg_product_contribution_qa_flush_person_endpoint_cache($contribution_id);
        return $assertion();
    } finally {
        vg_product_contribution_qa_check(
            update_field(ProductContributionRepository::PERSON_FIELD, $original_person_id, $contribution_id) !== false,
            'Could not restore the owned contribution endpoint after malformed-endpoint filtering.'
        );
        vg_product_contribution_qa_flush_person_endpoint_cache($contribution_id);
    }
}

function vg_product_contribution_qa_query_var_is_disabled(mixed $query_var): bool
{
    return $query_var === false;
}

/** Executes read-only preflight before opening the mutation window. */
function vg_product_contribution_qa_guarded_run(callable $preflight, callable $body, callable $cleanup): array
{
    $state = [
        'mutation_started' => false,
        'cleanup_attempted' => false,
        'error' => null,
        'cleanup_errors' => [],
    ];

    try {
        $preflight();
        $state['mutation_started'] = true;
        try {
            $body();
        } catch (Throwable $error) {
            $state['error'] = $error->getMessage();
        } finally {
            $state['cleanup_attempted'] = true;
            try {
                $cleanup();
            } catch (Throwable $error) {
                $state['cleanup_errors'][] = $error->getMessage();
            }
        }
    } catch (Throwable $error) {
        $state['error'] = $error->getMessage();
    }

    return $state;
}

/** @param array<string, mixed> $identity */
function vg_product_contribution_qa_validate_identity(array $identity): void
{
    vg_product_contribution_qa_check(
        ($identity['wordpress_path'] ?? null) === '/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/',
        'WordPress path is not the authorized VGNova installation.'
    );
    vg_product_contribution_qa_check(
        ($identity['home'] ?? null) === 'http://localhost:10039'
            && ($identity['site'] ?? null) === 'http://localhost:10039'
            && ($identity['effective_port'] ?? null) === 10039,
        'VGNova must resolve to localhost:10039 for both home and site URL.'
    );
    vg_product_contribution_qa_check(
        ($identity['database'] ?? null) === 'local'
            && ($identity['database_host'] ?? null) === 'localhost',
        'Database identity is not local@localhost.'
    );
    vg_product_contribution_qa_check(
        is_string($identity['php'] ?? null) && preg_match('/^8\.5\./', $identity['php']) === 1,
        'PHP runtime is not 8.5.x.'
    );
    vg_product_contribution_qa_check(
        is_string($identity['platform_core_path'] ?? null)
            && $identity['platform_core_path'] === '/home/jcschoeman96/Local Sites/voelgoed-novamira/app/public/wp-content/plugins/voelgoed-platform-core/',
        'Platform Core is not loading from the authorized VGNova plugin path.'
    );
}

function vg_product_contribution_qa_hash(mixed $value): string
{
    $previous_precision = ini_get('serialize_precision');
    if ($previous_precision === false || ini_set('serialize_precision', '-1') === false) {
        throw new RuntimeException('Could not normalize PHP float serialization precision for protected fingerprints.');
    }
    try {
        return hash('sha256', serialize($value));
    } finally {
        ini_set('serialize_precision', $previous_precision);
    }
}

function vg_product_contribution_qa_count(string $sql, string $description): int
{
    global $wpdb;
    $value = $wpdb->get_var($sql);
    if ($wpdb->last_error !== '' || $value === null) {
        throw new RuntimeException('Could not read ' . $description . ': ' . $wpdb->last_error);
    }
    return (int) $value;
}

function vg_product_contribution_qa_post_count(string $post_type): int
{
    global $wpdb;
    return vg_product_contribution_qa_count(
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", $post_type),
        'post count for ' . $post_type
    );
}

function vg_product_contribution_qa_post_meta_count(int $post_id): int
{
    global $wpdb;
    return vg_product_contribution_qa_count(
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d", $post_id),
        'postmeta count for ' . $post_id
    );
}

function vg_product_contribution_qa_relationship_count(int $post_id): int
{
    global $wpdb;
    return vg_product_contribution_qa_count(
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE object_id = %d", $post_id),
        'term relationship count for ' . $post_id
    );
}

/** @return array{post_count:int,postmeta_count:int,term_relationship_count:int,posts_sha256:string,postmeta_sha256:string,term_relationships_sha256:string} */
function vg_product_contribution_qa_group_fingerprint(array $ids): array
{
    global $wpdb;
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if ($ids === []) {
        throw new RuntimeException('Protected fingerprint ID list cannot be empty.');
    }
    $in = implode(',', $ids);
    $posts = $wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE ID IN ({$in}) ORDER BY ID ASC", ARRAY_A);
    $meta = $wpdb->get_results("SELECT * FROM {$wpdb->postmeta} WHERE post_id IN ({$in}) ORDER BY post_id ASC, meta_key ASC, meta_id ASC", ARRAY_A);
    $relationships = $wpdb->get_results(
        "SELECT r.object_id,r.term_taxonomy_id,r.term_order,tt.taxonomy,tt.term_id,t.slug FROM {$wpdb->term_relationships} r JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=r.term_taxonomy_id JOIN {$wpdb->terms} t ON t.term_id=tt.term_id WHERE r.object_id IN ({$in}) ORDER BY r.object_id ASC,r.term_taxonomy_id ASC,r.term_order ASC,tt.taxonomy ASC,tt.term_id ASC,t.slug ASC",
        ARRAY_A
    );
    if ($wpdb->last_error !== '') {
        throw new RuntimeException('Could not read protected post fingerprints: ' . $wpdb->last_error);
    }
    return [
        'post_count' => count($posts),
        'postmeta_count' => count($meta),
        'term_relationship_count' => count($relationships),
        'posts_sha256' => vg_product_contribution_qa_hash($posts),
        'postmeta_sha256' => vg_product_contribution_qa_hash($meta),
        'term_relationships_sha256' => vg_product_contribution_qa_hash($relationships),
    ];
}

function vg_product_contribution_qa_bricks_component_fingerprint(): array
{
    $components = get_option('bricks_components');
    vg_product_contribution_qa_check(is_array($components), 'Bricks component registry is unavailable.');
    foreach ($components as $component) {
        if (is_array($component) && (string) ($component['id'] ?? '') === '6b463f') {
            return ['sha256' => vg_product_contribution_qa_hash($component)];
        }
    }
    throw new RuntimeException('Protected Bricks component 6b463f is missing.');
}

function vg_product_contribution_qa_event_schema_signature(): array
{
    $post_type = get_post_type_object(EventParticipationRepository::POST_TYPE);
    vg_product_contribution_qa_check($post_type instanceof WP_Post_Type, 'Event Participation post type is not registered.');
    vg_product_contribution_qa_check(
        vg_product_contribution_qa_query_var_is_disabled($post_type->query_var),
        'Event Participation runtime query_var must be boolean false.'
    );
    $group = function_exists('acf_get_field_group') ? acf_get_field_group('group_vg_event_participation_details') : false;
    vg_product_contribution_qa_check(is_array($group) && (int) ($group['ID'] ?? 0) === 1263, 'Event Participation ACF group ID/key changed.');
    $fields = acf_get_fields($group['ID']);
    $signature = array_map(static fn (array $field): array => [
        'key' => $field['key'] ?? null,
        'name' => $field['name'] ?? null,
        'label' => $field['label'] ?? null,
        'type' => $field['type'] ?? null,
        'required' => (int) ($field['required'] ?? 0),
        'parent' => 'group_vg_event_participation_details',
    ], $fields ?: []);
    $source = json_decode(
        (string) file_get_contents(VG_PRODUCT_CONTRIBUTION_QA_SOURCE_PATH . '/group_vg_event_participation_details.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    $source_signature = array_map(static fn (array $field): array => [
        'key' => $field['key'] ?? null,
        'name' => $field['name'] ?? null,
        'label' => $field['label'] ?? null,
        'type' => $field['type'] ?? null,
        'required' => (int) ($field['required'] ?? 0),
        'parent' => 'group_vg_event_participation_details',
    ], $source['fields'] ?? []);
    vg_product_contribution_qa_check(count($signature) === 4 && $signature === $source_signature, 'Protected Event Participation field schema differs from canonical JSON.');
    return [
        'post_type_query_var_is_boolean_false' => vg_product_contribution_qa_query_var_is_disabled($post_type->query_var),
        'fields' => $signature,
    ];
}

function vg_product_contribution_qa_acf_database_fingerprint(): array
{
    global $wpdb;
    $posts = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->posts} WHERE post_type LIKE %s ORDER BY ID ASC",
            'acf-%'
        ),
        ARRAY_A
    );
    if ($wpdb->last_error !== '' || ! is_array($posts)) {
        throw new RuntimeException('Could not read ACF database definition posts: ' . $wpdb->last_error);
    }

    $ids = array_values(array_filter(array_map(static fn (array $post): int => (int) ($post['ID'] ?? 0), $posts)));
    $meta = [];
    if ($ids !== []) {
        $in = implode(',', $ids);
        $meta = $wpdb->get_results(
            "SELECT * FROM {$wpdb->postmeta} WHERE post_id IN ({$in}) ORDER BY post_id ASC, meta_key ASC, meta_id ASC",
            ARRAY_A
        );
        if ($wpdb->last_error !== '' || ! is_array($meta)) {
            throw new RuntimeException('Could not read ACF database definition metadata: ' . $wpdb->last_error);
        }
    }

    return [
        'post_count' => count($posts),
        'postmeta_count' => count($meta),
        'posts_sha256' => vg_product_contribution_qa_hash($posts),
        'postmeta_sha256' => vg_product_contribution_qa_hash($meta),
    ];
}

function vg_product_contribution_qa_route_configuration_fingerprint(): array
{
    global $wpdb;
    $option_names = [
        'rewrite_rules',
        'permalink_structure',
        'category_base',
        'tag_base',
        'home',
        'siteurl',
        'show_on_front',
        'page_on_front',
        'page_for_posts',
    ];
    $placeholders = implode(', ', array_fill(0, count($option_names), '%s'));
    $option_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name IN ({$placeholders}) ORDER BY option_name ASC",
            ...$option_names
        ),
        ARRAY_A
    );
    if ($wpdb->last_error !== '' || ! is_array($option_rows)) {
        throw new RuntimeException('Could not read persisted route configuration: ' . $wpdb->last_error);
    }
    $options = array_map(static fn (array $row): array => [
        'option_name' => (string) $row['option_name'],
        'option_value_sha256' => hash('sha256', (string) $row['option_value']),
        'autoload' => $row['autoload'],
    ], $option_rows);

    $routes = ['post_types' => [], 'taxonomies' => []];
    foreach (get_post_types([], 'objects') as $name => $object) {
        $routes['post_types'][$name] = [
            'public' => (bool) $object->public,
            'publicly_queryable' => (bool) $object->publicly_queryable,
            'exclude_from_search' => (bool) $object->exclude_from_search,
            'has_archive' => $object->has_archive,
            'rewrite' => $object->rewrite,
            'query_var' => $object->query_var,
            'show_in_rest' => (bool) $object->show_in_rest,
            'rest_base' => $object->rest_base,
            'rest_namespace' => $object->rest_namespace,
        ];
    }
    foreach (get_taxonomies([], 'objects') as $name => $object) {
        $object_types = array_values((array) $object->object_type);
        sort($object_types);
        $routes['taxonomies'][$name] = [
            'object_type' => $object_types,
            'public' => (bool) $object->public,
            'publicly_queryable' => (bool) $object->publicly_queryable,
            'hierarchical' => (bool) $object->hierarchical,
            'rewrite' => $object->rewrite,
            'query_var' => $object->query_var,
            'show_in_rest' => (bool) $object->show_in_rest,
            'rest_base' => $object->rest_base,
            'rest_namespace' => $object->rest_namespace,
        ];
    }
    ksort($routes['post_types']);
    ksort($routes['taxonomies']);

    return [
        'persisted_options' => $options,
        'registered_post_type_count' => count($routes['post_types']),
        'registered_taxonomy_count' => count($routes['taxonomies']),
        'registered_routes_sha256' => vg_product_contribution_qa_hash($routes),
    ];
}

function vg_product_contribution_qa_snippet_fingerprints(): array
{
    $result = [];
    foreach (['9-acf-product-copy.php', '10-vg-bricks-medewerker-queries.php'] as $file) {
        $path = WP_CONTENT_DIR . '/fluent-snippet-storage/' . $file;
        vg_product_contribution_qa_check(is_file($path), 'Protected FluentSnippet is missing: ' . $file);
        $result[$file] = ['size' => filesize($path), 'sha256' => hash_file('sha256', $path)];
    }
    return $result;
}

function vg_product_contribution_qa_post_type_counts(): array
{
    global $wpdb;
    $rows = $wpdb->get_results("SELECT post_type,post_status,COUNT(*) AS n FROM {$wpdb->posts} GROUP BY post_type,post_status ORDER BY post_type,post_status", ARRAY_A);
    if ($wpdb->last_error !== '' || ! is_array($rows)) {
        throw new RuntimeException('Could not read post type/status counts: ' . $wpdb->last_error);
    }
    $counts = [];
    foreach ($rows as $row) {
        $counts[$row['post_type'] . '|' . $row['post_status']] = (int) $row['n'];
    }
    ksort($counts);
    return $counts;
}

function vg_product_contribution_qa_protected_snapshot(): array
{
    global $wpdb;
    $option_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->options} WHERE option_name = %s", 'bricks_components'), ARRAY_A);
    if ($wpdb->last_error !== '') {
        throw new RuntimeException('Could not read Bricks component option row: ' . $wpdb->last_error);
    }
    return [
        'profiles_753_757' => vg_product_contribution_qa_group_fingerprint([753, 754, 755, 756, 757]),
        'event_763' => vg_product_contribution_qa_group_fingerprint([763]),
        'products_765_767_768' => vg_product_contribution_qa_group_fingerprint([765, 767, 768]),
        'templates_773_1127' => vg_product_contribution_qa_group_fingerprint([773, 1127]),
        'bricks_component_6b463f' => vg_product_contribution_qa_bricks_component_fingerprint(),
        'bricks_option_row_sha256' => $option_row ? vg_product_contribution_qa_hash($option_row) : null,
        'fluentsnippets' => vg_product_contribution_qa_snippet_fingerprints(),
        'event_participation_schema' => vg_product_contribution_qa_event_schema_signature(),
        'acf_database_definitions' => vg_product_contribution_qa_acf_database_fingerprint(),
        'route_configuration' => vg_product_contribution_qa_route_configuration_fingerprint(),
        'post_type_status_counts' => vg_product_contribution_qa_post_type_counts(),
    ];
}

function vg_product_contribution_qa_runtime_identity(): array
{
    $plugin_path = WP_PLUGIN_DIR . '/voelgoed-platform-core/';
    $header = get_file_data($plugin_path . 'voelgoed-platform-core.php', ['Version' => 'Version']);
    $theme_json_path = get_stylesheet_directory() . '/acf-json';
    $active_json_path = realpath($theme_json_path);
    return [
        'wordpress_path' => ABSPATH,
        'home' => untrailingslashit(home_url()),
        'site' => untrailingslashit(site_url()),
        'effective_port' => (int) (parse_url(home_url(), PHP_URL_PORT) ?: 80),
        'database' => DB_NAME,
        'database_host' => DB_HOST,
        'php' => PHP_VERSION,
        'wordpress' => get_bloginfo('version'),
        'acf_pro' => defined('ACF_VERSION') ? ACF_VERSION : null,
        'woocommerce' => defined('WC_VERSION') ? WC_VERSION : null,
        'platform_core_version' => $header['Version'] ?? null,
        'platform_core_path' => $plugin_path,
        'child_theme_acf_json_path' => $active_json_path,
    ];
}

function vg_product_contribution_qa_canonicalize_acf_value(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }
    if (array_is_list($value)) {
        return array_map('vg_product_contribution_qa_canonicalize_acf_value', $value);
    }
    ksort($value);
    foreach ($value as $key => $nested) {
        $value[$key] = vg_product_contribution_qa_canonicalize_acf_value($nested);
    }
    return $value;
}

function vg_product_contribution_qa_normalize_acf_definition(array $definition, array $source, array $generated_defaults): array
{
    unset($definition['ID']);
    foreach ($generated_defaults as $key => $expected_default) {
        if (array_key_exists($key, $source)) {
            continue;
        }
        vg_product_contribution_qa_check(
            array_key_exists($key, $definition) && $definition[$key] === $expected_default,
            'ACF generated metadata differs from its documented default: ' . $key
        );
        unset($definition[$key]);
    }
    return vg_product_contribution_qa_canonicalize_acf_value($definition);
}

function vg_product_contribution_qa_field_signature(array $fields, string $parent_key): array
{
    return array_map(static fn (array $field): array => [
        'key' => $field['key'] ?? null,
        'name' => $field['name'] ?? null,
        'label' => $field['label'] ?? null,
        'type' => $field['type'] ?? null,
        'required' => (int) ($field['required'] ?? 0),
        'parent' => $parent_key,
        'post_type' => $field['post_type'] ?? null,
        'taxonomy' => $field['taxonomy'] ?? null,
    ], $fields);
}

/** Verify the three active runtime definitions against their canonical Local JSON sources. */
function vg_product_contribution_qa_verify_acf_schema(): array
{
    $active_path = realpath(get_stylesheet_directory() . '/acf-json');
    vg_product_contribution_qa_check($active_path === VG_PRODUCT_CONTRIBUTION_QA_SOURCE_PATH, 'Active child-theme ACF JSON path is not the canonical shared repository path.');
    $source_hashes = [];
    foreach ([
        'post_type_product_contribution.json',
        'taxonomy_product_role.json',
        'group_vg_product_contribution_details.json',
    ] as $file) {
        $path = $active_path . '/' . $file;
        vg_product_contribution_qa_check(is_file($path), 'Required Product Contribution ACF JSON file is missing: ' . $file);
        $source_hashes[$file] = hash_file('sha256', $path);
    }

    $source_cpt = json_decode((string) file_get_contents($active_path . '/post_type_product_contribution.json'), true, 512, JSON_THROW_ON_ERROR);
    $source_taxonomy = json_decode((string) file_get_contents($active_path . '/taxonomy_product_role.json'), true, 512, JSON_THROW_ON_ERROR);
    $source_group = json_decode((string) file_get_contents($active_path . '/group_vg_product_contribution_details.json'), true, 512, JSON_THROW_ON_ERROR);
    $db_cpt = acf_get_raw_post_type('post_type_product_contribution');
    $db_taxonomy = acf_get_raw_taxonomy('taxonomy_product_role');
    $db_group = acf_get_raw_field_group('group_vg_product_contribution_details');
    vg_product_contribution_qa_check(is_array($db_cpt) && (int) ($db_cpt['ID'] ?? 0) > 0, 'Product Contribution ACF post type is not database-backed.');
    vg_product_contribution_qa_check(is_array($db_taxonomy) && (int) ($db_taxonomy['ID'] ?? 0) > 0, 'Product Role ACF taxonomy is not database-backed.');
    vg_product_contribution_qa_check(is_array($db_group) && (int) ($db_group['ID'] ?? 0) > 0, 'Product Contribution ACF field group is not database-backed.');
    $db_group_id = (int) $db_group['ID'];

    $normalized_db_cpt = vg_product_contribution_qa_normalize_acf_definition(
        $db_cpt,
        $source_cpt,
        ['allow_ai_access' => false, 'ai_description' => '']
    );
    $normalized_db_taxonomy = vg_product_contribution_qa_normalize_acf_definition(
        $db_taxonomy,
        $source_taxonomy,
        ['meta_box_cb' => '', 'meta_box_sanitize_cb' => '', 'allow_ai_access' => false, 'ai_description' => '']
    );
    vg_product_contribution_qa_check(
        $normalized_db_cpt === vg_product_contribution_qa_canonicalize_acf_value($source_cpt),
        'Persisted Product Contribution ACF post type differs from canonical JSON.'
    );
    vg_product_contribution_qa_check(
        $normalized_db_taxonomy === vg_product_contribution_qa_canonicalize_acf_value($source_taxonomy),
        'Persisted Product Role ACF taxonomy differs from canonical JSON.'
    );

    $runtime_cpt = get_post_type_object('product_contribution');
    vg_product_contribution_qa_check($runtime_cpt instanceof WP_Post_Type, 'Product Contribution post type is not registered.');
    vg_product_contribution_qa_check(
        vg_product_contribution_qa_query_var_is_disabled($runtime_cpt->query_var),
        'Product Contribution runtime query_var must be boolean false.'
    );
    $supports = array_keys(get_all_post_type_supports('product_contribution'));
    sort($supports);
    vg_product_contribution_qa_check(
        ! $runtime_cpt->public && ! $runtime_cpt->publicly_queryable && $runtime_cpt->exclude_from_search
            && $runtime_cpt->show_ui && $runtime_cpt->show_in_rest && ! $runtime_cpt->has_archive
            && $runtime_cpt->rewrite === false && $supports === ['title'],
        'Product Contribution runtime registration violates its non-public title-only contract.'
    );

    $runtime_taxonomy = get_taxonomy('product_role');
    vg_product_contribution_qa_check($runtime_taxonomy instanceof WP_Taxonomy, 'Product Role taxonomy is not registered.');
    $taxonomy_objects = array_values((array) $runtime_taxonomy->object_type);
    sort($taxonomy_objects);
    vg_product_contribution_qa_check(
        ! $runtime_taxonomy->public && ! $runtime_taxonomy->publicly_queryable && ! $runtime_taxonomy->hierarchical
            && $runtime_taxonomy->show_ui && $runtime_taxonomy->show_in_rest
            && $runtime_taxonomy->rewrite === false && $runtime_taxonomy->query_var === false
            && $taxonomy_objects === ['product_contribution'],
        'Product Role runtime registration violates its private flat single-object contract.'
    );

    $source_fields = $source_group['fields'] ?? [];
    $db_fields = acf_get_raw_fields((int) $db_group['ID']);
    vg_product_contribution_qa_check(count($db_fields) === count($source_fields) && count($db_fields) === 4, 'Persisted Product Contribution field count differs from canonical JSON.');
    foreach ($db_fields as $index => &$db_field) {
        $source_field = $source_fields[$index] ?? null;
        vg_product_contribution_qa_check(is_array($source_field), 'Persisted Product Contribution field order differs from canonical JSON.');
        vg_product_contribution_qa_check((int) ($db_field['ID'] ?? 0) > 0, 'A Product Contribution child field is not database-backed.');
        vg_product_contribution_qa_check(
            (int) ($db_field['parent'] ?? 0) === (int) $db_group['ID']
                && (int) ($db_field['menu_order'] ?? -1) === $index,
            'Persisted Product Contribution field parent/order differs from the target group.'
        );
        unset($db_field['ID'], $db_field['menu_order'], $db_field['parent']);
        if (isset($source_field['step']) && is_numeric($source_field['step'])) {
            vg_product_contribution_qa_check(
                isset($db_field['step']) && is_numeric($db_field['step']) && (float) $db_field['step'] === (float) $source_field['step'],
                'Persisted Product Contribution number step differs from canonical JSON.'
            );
            $db_field['step'] = $source_field['step'];
        }
        if (! array_key_exists('placeholder', $source_field)) {
            vg_product_contribution_qa_check(($db_field['placeholder'] ?? '') === '', 'Unexpected non-empty ACF generated placeholder value.');
            unset($db_field['placeholder']);
        }
    }
    unset($db_field);
    unset($db_group['ID']);
    $db_group['fields'] = $db_fields;
    vg_product_contribution_qa_check(
        vg_product_contribution_qa_canonicalize_acf_value($db_group)
            === vg_product_contribution_qa_canonicalize_acf_value($source_group),
        'Persisted Product Contribution field group differs from canonical JSON.'
    );
    $runtime_signature = vg_product_contribution_qa_field_signature($db_fields, (string) $source_group['key']);
    foreach ($runtime_signature as $field) {
        vg_product_contribution_qa_check($field['required'] === 0, 'Product Contribution fields must all remain optional.');
    }

    return [
        'verified' => true,
        'canonical_source_path' => $active_path,
        'source_file_hashes' => $source_hashes,
        'parity_normalization' => [
            'generated_acf_defaults' => [
                'post_type_product_contribution' => ['allow_ai_access=false', 'ai_description=""'],
                'taxonomy_product_role' => ['meta_box_cb=""', 'meta_box_sanitize_cb=""', 'allow_ai_access=false', 'ai_description=""'],
            ],
            'database_ids' => 'Verified as positive and excluded from JSON equality comparison.',
            'field_parent_and_order' => 'Parent IDs and menu_order values verified against the persisted group and source position, then excluded from JSON equality comparison.',
            'number_step' => 'Source and database values must be numerically equal; the persisted value is normalized to the source scalar type.',
            'empty_placeholder' => 'An ACF-added empty placeholder is accepted only when absent from source JSON.',
            'key_order' => 'Associative keys are sorted for comparison; list order is preserved.',
        ],
        'database_backed_ids' => [
            'post_type_product_contribution' => (int) $db_cpt['ID'],
            'taxonomy_product_role' => (int) $db_taxonomy['ID'],
            'group_vg_product_contribution_details' => $db_group_id,
        ],
        'runtime_contracts' => [
            'post_type_product_contribution' => [
                'public' => (bool) $runtime_cpt->public,
                'publicly_queryable' => (bool) $runtime_cpt->publicly_queryable,
                'exclude_from_search' => (bool) $runtime_cpt->exclude_from_search,
                'show_ui' => (bool) $runtime_cpt->show_ui,
                'show_in_rest' => (bool) $runtime_cpt->show_in_rest,
                'has_archive' => (bool) $runtime_cpt->has_archive,
                'rewrite' => $runtime_cpt->rewrite,
                'query_var' => $runtime_cpt->query_var,
                'query_var_is_boolean_false' => vg_product_contribution_qa_query_var_is_disabled($runtime_cpt->query_var),
                'supports' => $supports,
            ],
            'taxonomy_product_role' => [
                'public' => (bool) $runtime_taxonomy->public,
                'publicly_queryable' => (bool) $runtime_taxonomy->publicly_queryable,
                'hierarchical' => (bool) $runtime_taxonomy->hierarchical,
                'show_ui' => (bool) $runtime_taxonomy->show_ui,
                'show_in_rest' => (bool) $runtime_taxonomy->show_in_rest,
                'rewrite' => $runtime_taxonomy->rewrite,
                'query_var' => $runtime_taxonomy->query_var,
                'object_type' => $taxonomy_objects,
            ],
            'field_signature' => $runtime_signature,
        ],
    ];
}

function vg_product_contribution_qa_role_terms(): array
{
    $terms = get_terms(['taxonomy' => ProductRoleVocabulary::TAXONOMY, 'hide_empty' => false]);
    if (is_wp_error($terms)) {
        throw new RuntimeException('Could not inspect Product Roles: ' . $terms->get_error_message());
    }
    $result = array_map(static fn (WP_Term $term): array => [
        'id' => (int) $term->term_id,
        'name' => $term->name,
        'slug' => $term->slug,
        'parent' => (int) $term->parent,
        'count' => (int) $term->count,
    ], $terms);
    usort($result, static fn (array $a, array $b): int => $a['slug'] <=> $b['slug']);
    return $result;
}

function vg_product_contribution_qa_role_relationship_count(): int
{
    global $wpdb;
    return vg_product_contribution_qa_count(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->term_relationships} r JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = r.term_taxonomy_id WHERE tt.taxonomy = %s",
            ProductRoleVocabulary::TAXONOMY
        ),
        'Product Role relationships'
    );
}

function vg_product_contribution_qa_persistent_state(): array
{
    global $wpdb;
    $target_keys = ['post_type_product_contribution', 'taxonomy_product_role', 'group_vg_product_contribution_details'];
    $definition_ids = [
        'acf-post-type' => acf_get_raw_post_type('post_type_product_contribution'),
        'acf-taxonomy' => acf_get_raw_taxonomy('taxonomy_product_role'),
        'acf-field-group' => acf_get_raw_field_group('group_vg_product_contribution_details'),
    ];
    $definition_posts = [];
    foreach ($definition_ids as $type => $definition) {
        $definition_posts[$type] = is_array($definition) ? (int) ($definition['ID'] ?? 0) : 0;
    }
    $override_rows = [];
    foreach (['product_people_override', '_product_people_override'] as $meta_key) {
        $override_rows[$meta_key] = vg_product_contribution_qa_count(
            $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", $meta_key),
            $meta_key . ' rows'
        );
    }
    $product_ids = array_map('intval', get_posts([
        'post_type' => 'product',
        'post_status' => array_values(get_post_stati()),
        'numberposts' => -1,
        'fields' => 'ids',
        'orderby' => 'ID',
        'order' => 'ASC',
        'suppress_filters' => true,
    ]));
    sort($product_ids);
    $product_inventory = [];
    foreach ($product_ids as $product_id) {
        $product_inventory[] = [
            'id' => $product_id,
            'status' => (string) get_post_status($product_id),
            'product_people_mode' => get_field('product_people_mode', $product_id, false),
            'product_people_override' => get_post_meta($product_id, 'product_people_override', false),
            '_product_people_override' => get_post_meta($product_id, '_product_people_override', false),
        ];
    }
    return [
        'product_contribution_posts' => vg_product_contribution_qa_post_count('product_contribution'),
        'person_posts' => vg_product_contribution_qa_post_count('person'),
        'event_participation_posts' => vg_product_contribution_qa_post_count(EventParticipationRepository::POST_TYPE),
        'product_role_terms' => count(vg_product_contribution_qa_role_terms()),
        'product_role_term_slugs' => array_column(vg_product_contribution_qa_role_terms(), 'slug'),
        'product_role_relationships' => vg_product_contribution_qa_role_relationship_count(),
        'target_acf_database_definitions' => count(array_filter($definition_posts)),
        'target_acf_database_post_ids' => $definition_posts,
        'override_rows' => $override_rows,
        'product_people_inventory' => $product_inventory,
    ];
}

function vg_product_contribution_qa_token_exists(string $token): bool
{
    global $wpdb;
    $like = '%' . $wpdb->esc_like($token) . '%';
    $queries = [
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title LIKE %s OR post_name LIKE %s OR post_content LIKE %s OR post_excerpt LIKE %s OR guid LIKE %s", $like, $like, $like, $like, $like),
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_value LIKE %s", $like),
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_value LIKE %s", $like),
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->terms} t LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE t.name LIKE %s OR t.slug LIKE %s OR tt.description LIKE %s", $like, $like, $like),
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_value LIKE %s", $like),
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_content LIKE %s OR comment_author LIKE %s OR comment_author_email LIKE %s", $like, $like, $like),
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->commentmeta} WHERE meta_value LIKE %s", $like),
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_value LIKE %s", $like),
    ];
    foreach ($queries as $index => $query) {
        if (vg_product_contribution_qa_count($query, 'ownership token store ' . $index) > 0) {
            return true;
        }
    }
    return false;
}

function vg_product_contribution_qa_woocommerce_lookup_rows(int $product_id): array
{
    global $wpdb;
    vg_product_contribution_qa_check($product_id > 0, 'WooCommerce lookup checks require a positive Product ID.');
    $lookups = [
        'wc_product_meta_lookup' => ['column' => 'product_id', 'required' => true],
        'wc_product_attributes_lookup' => ['column' => 'product_or_parent_id', 'required' => false],
    ];
    $result = [];
    foreach ($lookups as $suffix => $definition) {
        $table = $wpdb->prefix . $suffix;
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        if ($wpdb->last_error !== '') {
            throw new RuntimeException('Could not inspect WooCommerce lookup table ' . $table . ': ' . $wpdb->last_error);
        }
        $exists = is_string($found) && $found === $table;
        vg_product_contribution_qa_check(
            $exists || ! $definition['required'],
            'Required WooCommerce Product lookup table is missing: ' . $table
        );
        if (! $exists) {
            $result[$suffix] = ['table_exists' => false, 'rows' => null];
            continue;
        }
        $rows = vg_product_contribution_qa_count(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$definition['column']} = %d", $product_id),
            'WooCommerce lookup rows for Product ' . $product_id . ' in ' . $table
        );
        $result[$suffix] = ['table_exists' => true, 'rows' => $rows];
    }
    return $result;
}

function vg_product_contribution_qa_track_service_contribution_result(
    mixed $result,
    int $product_id,
    int $person_id,
    string $token,
    array &$owned
): bool {
    if (is_wp_error($result)) {
        return false;
    }
    vg_product_contribution_qa_check(
        (is_int($result) || (is_string($result) && ctype_digit($result))) && (int) $result > 0,
        'Unexpected ProductContributionService result cannot be tracked by exact ID.'
    );
    $id = (int) $result;
    vg_product_contribution_qa_track(
        $owned,
        $id,
        ProductContributionRepository::POST_TYPE,
        ['product_id' => $product_id, 'person_id' => $person_id]
    );
    vg_product_contribution_qa_mark_owned($id, ProductContributionRepository::POST_TYPE, $token);
    return true;
}

/** @param array<string, array<int, array<string, mixed>>> $owned */
function vg_product_contribution_qa_track(array &$owned, int $id, string $type, array $endpoints = []): void
{
    if ($id <= 0) {
        throw new RuntimeException('Cannot track a non-positive WordPress object ID.');
    }
    $owned[$type][$id] = ['id' => $id, 'type' => $type, 'endpoints' => $endpoints];
}

function vg_product_contribution_qa_mark_owned(int $id, string $type, string $token): void
{
    $post = get_post($id);
    vg_product_contribution_qa_check($post instanceof WP_Post && $post->post_type === $type, 'New QA object has an unexpected post type.');
    vg_product_contribution_qa_check(str_contains($post->post_title, $token), 'New QA object title does not carry its unique ownership token.');
    $current = get_post_meta($id, VG_PRODUCT_CONTRIBUTION_QA_OWNER_META, true);
    vg_product_contribution_qa_check($current === '' || $current === null, 'New QA object already has an owner marker.');
    vg_product_contribution_qa_check(add_post_meta($id, VG_PRODUCT_CONTRIBUTION_QA_OWNER_META, $token, true) !== false, 'Could not write the QA ownership marker.');
    vg_product_contribution_qa_check(get_post_meta($id, VG_PRODUCT_CONTRIBUTION_QA_OWNER_META, true) === $token, 'QA ownership marker did not read back exactly.');
}

function vg_product_contribution_qa_assert_endpoints(array $record, string $token): void
{
    $id = (int) $record['id'];
    $type = (string) $record['type'];
    $expected = (array) ($record['endpoints'] ?? []);
    if ($type === 'product_contribution') {
        vg_product_contribution_qa_check(
            (int) get_field(ProductContributionRepository::PRODUCT_FIELD, $id) === (int) ($expected['product_id'] ?? 0)
                && (int) get_field(ProductContributionRepository::PERSON_FIELD, $id) === (int) ($expected['person_id'] ?? 0),
            'Cleanup refused a Product Contribution with changed endpoints.'
        );
    } elseif ($type === 'event_participation') {
        vg_product_contribution_qa_check(
            (int) get_field(EventParticipationRepository::EVENT_FIELD, $id) === (int) ($expected['event_id'] ?? 0)
                && (int) get_field(EventParticipationRepository::PERSON_FIELD, $id) === (int) ($expected['person_id'] ?? 0),
            'Cleanup refused an Event Participation with changed endpoints.'
        );
    } elseif ($type === 'product' && (int) ($expected['source_event_id'] ?? 0) > 0) {
        vg_product_contribution_qa_check(
            (int) get_field('produk_bron_event', $id, false) === (int) $expected['source_event_id']
                || (string) get_field('produk_bron_event', $id, false) === (string) $expected['source_event_id'],
            'Cleanup refused a Product with a changed source Event.'
        );
    }
    if ($type !== 'tc_api_keys') {
        vg_product_contribution_qa_check(
            get_post_meta($id, VG_PRODUCT_CONTRIBUTION_QA_OWNER_META, true) === $token,
            'Cleanup refused an object without the exact Task 8C ownership marker.'
        );
    }
}

/** Delete only exact tracked IDs after proving type, endpoints, marker, and title token. */
function vg_product_contribution_qa_delete_owned_record(
    array $record,
    string $token,
    ?callable $delete = null,
    ?array &$cleanup_evidence = null
): void
{
    $id = (int) ($record['id'] ?? 0);
    $type = (string) ($record['type'] ?? '');
    vg_product_contribution_qa_check($id > 0 && $type !== '', 'Cleanup record is missing an exact ID or expected type.');
    $post = get_post($id);
    vg_product_contribution_qa_check($post instanceof WP_Post && $post->post_type === $type, 'Cleanup refused an ID whose post type differs from its tracked type.');
    vg_product_contribution_qa_check(str_contains($post->post_title, $token), 'Cleanup refused an ID whose title no longer carries the run token.');
    vg_product_contribution_qa_assert_endpoints($record, $token);
    ($delete ?? 'wp_delete_post')($id, true);
    vg_product_contribution_qa_check(
        get_post($id) === null
            && vg_product_contribution_qa_post_meta_count($id) === 0
            && vg_product_contribution_qa_relationship_count($id) === 0,
        'Hard deletion did not remove the exact QA post, postmeta, and term relationships.'
    );
    if ($type === 'product' && function_exists('wc_get_product')) {
        vg_product_contribution_qa_check(wc_get_product($id) === false, 'WooCommerce still resolves the deleted QA Product.');
        $lookup_rows = vg_product_contribution_qa_woocommerce_lookup_rows($id);
        $cleanup_evidence = ['product_id' => $id, 'tables' => $lookup_rows];
        foreach ($lookup_rows as $table => $state) {
            vg_product_contribution_qa_check(
                ! $state['table_exists'] || $state['rows'] === 0,
                'WooCommerce lookup residue remains for deleted Product ' . $id . ' in ' . $table . '.'
            );
        }
    }
}

/** @return list<array{id:int,type:string,message:string}> */
function vg_product_contribution_qa_cleanup_posts(array $owned, string $token, ?callable $delete = null): array
{
    $errors = [];
    $order = [
        'product_contribution',
        'event_participation',
        'product',
        'person',
        'tc_events',
    ];
    foreach ($order as $type) {
        foreach (($owned[$type] ?? []) as $id => $record) {
            try {
                vg_product_contribution_qa_delete_owned_record($record, $token, $delete);
            } catch (Throwable $error) {
                $errors[] = ['id' => (int) $id, 'type' => $type, 'message' => $error->getMessage()];
            }
        }
    }
    return $errors;
}

/** @return list<array{id:int,post_type:string,status:string,api_key_name:mixed,event_name:mixed}> */
function vg_product_contribution_qa_tickera_api_rows(): array
{
    global $wpdb;
    $ids = array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = %s ORDER BY ID ASC", 'tc_api_keys')));
    if ($wpdb->last_error !== '') {
        throw new RuntimeException('Could not read Tickera API key IDs: ' . $wpdb->last_error);
    }
    $rows = [];
    foreach ($ids as $id) {
        $post = get_post($id);
        if ($post instanceof WP_Post) {
            $rows[] = [
                'id' => $id,
                'post_type' => $post->post_type,
                'status' => $post->post_status,
                'api_key_name' => get_post_meta($id, 'api_key_name', true),
                'event_name' => get_post_meta($id, 'event_name', true),
            ];
        }
    }
    return $rows;
}

function vg_product_contribution_qa_cleanup_tickera(array $owned_event_ids, array $preexisting_api_ids, string $token): array
{
    $report = ['deleted' => [], 'unexplained' => [], 'errors' => []];
    $plan = vg_product_people_qa_plan_tickera_side_effects(
        vg_product_contribution_qa_tickera_api_rows(),
        $preexisting_api_ids,
        $token,
        $owned_event_ids
    );
    $report['unexplained'] = $plan['unexplained'];
    foreach ($plan['unexplained'] as $row) {
        $report['errors'][] = ['id' => (int) $row['id'], 'type' => 'tc_api_keys', 'message' => 'Tickera side-effect ownership is ambiguous: ' . $row['reason']];
    }
    foreach ($plan['delete'] as $id) {
        try {
            $rows = array_values(array_filter(vg_product_contribution_qa_tickera_api_rows(), static fn (array $row): bool => $row['id'] === (int) $id));
            $recheck = vg_product_people_qa_plan_tickera_side_effects($rows, $preexisting_api_ids, $token, $owned_event_ids);
            vg_product_contribution_qa_check($recheck['delete'] === [(int) $id] && $recheck['unexplained'] === [], 'Could not re-prove Tickera side-effect ownership immediately before deletion.');
            wp_delete_post((int) $id, true);
            vg_product_contribution_qa_check(
                get_post((int) $id) === null
                    && vg_product_contribution_qa_post_meta_count((int) $id) === 0
                    && vg_product_contribution_qa_relationship_count((int) $id) === 0,
                'Tickera side-effect hard delete left post, metadata, or relationships.'
            );
            $report['deleted'][] = (int) $id;
        } catch (Throwable $error) {
            $report['errors'][] = ['id' => (int) $id, 'type' => 'tc_api_keys', 'message' => $error->getMessage()];
        }
    }
    return $report;
}

function vg_product_contribution_qa_assertion(array &$assertions, string $name, bool $passed, array $evidence = []): void
{
    $assertions[] = ['name' => $name, 'result' => $passed ? 'PASS' : 'FAIL', 'evidence' => $evidence];
    if (! $passed) {
        throw new RuntimeException('Runtime assertion failed: ' . $name);
    }
}

function vg_product_contribution_qa_create_person(string $token, string $label, string $status, array &$owned): int
{
    $id = wp_insert_post([
        'post_type' => 'person',
        'post_status' => $status,
        'post_title' => $token . ' ' . $label,
    ], true);
    vg_product_contribution_qa_check(! is_wp_error($id) && (int) $id > 0, 'Could not create owned QA Person ' . $label . '.');
    $id = (int) $id;
    vg_product_contribution_qa_track($owned, $id, 'person');
    vg_product_contribution_qa_mark_owned($id, 'person', $token);
    return $id;
}

function vg_product_contribution_qa_create_product(string $token, string $label, string $status, array &$owned, int $source_event_id = 0): int
{
    vg_product_contribution_qa_check(class_exists('WC_Product_Simple'), 'WooCommerce simple Product API is unavailable.');
    $product = new WC_Product_Simple();
    $product->set_name($token . ' ' . $label);
    $product->set_status($status);
    $product->set_regular_price('1.00');
    $product->set_catalog_visibility('hidden');
    $id = (int) $product->save();
    vg_product_contribution_qa_check($id > 0 && get_post_type($id) === 'product', 'Could not create an owned WooCommerce Product.');
    vg_product_contribution_qa_track($owned, $id, 'product', $source_event_id > 0 ? ['source_event_id' => $source_event_id] : []);
    vg_product_contribution_qa_mark_owned($id, 'product', $token);
    $lookup_rows = vg_product_contribution_qa_woocommerce_lookup_rows($id);
    vg_product_contribution_qa_check(
        ($lookup_rows['wc_product_meta_lookup']['table_exists'] ?? false)
            && ($lookup_rows['wc_product_meta_lookup']['rows'] ?? 0) > 0,
        'WooCommerce did not create a Product lookup row for owned Product ' . $id . '.'
    );
    return $id;
}

function vg_product_contribution_qa_create_event(string $token, array &$owned): int
{
    $id = wp_insert_post([
        'post_type' => 'tc_events',
        'post_status' => 'publish',
        'post_title' => $token . ' Event',
    ], true);
    vg_product_contribution_qa_check(! is_wp_error($id) && (int) $id > 0, 'Could not create an owned Tickera Event.');
    $id = (int) $id;
    vg_product_contribution_qa_track($owned, $id, 'tc_events');
    vg_product_contribution_qa_mark_owned($id, 'tc_events', $token);
    return $id;
}

function vg_product_contribution_qa_set_mode(int $product_id, string $mode): void
{
    vg_product_contribution_qa_check(update_field(ProductPeopleFields::MODE_FIELD, $mode, $product_id) !== false, 'Could not set Product People mode on an owned Product.');
    vg_product_contribution_qa_check(get_field(ProductPeopleFields::MODE_FIELD, $product_id) === $mode, 'Owned Product People mode did not read back.');
}

function vg_product_contribution_qa_set_source_event(int $product_id, int $event_id): void
{
    $updated = update_field(ProductContributionRepository::SOURCE_EVENT_FIELD, $event_id, $product_id);
    if ($updated === false) {
        update_post_meta($product_id, ProductContributionRepository::SOURCE_EVENT_FIELD, $event_id);
        if (function_exists('acf_flush_value_cache')) {
            acf_flush_value_cache($product_id, ProductContributionRepository::SOURCE_EVENT_FIELD);
        }
    }
    vg_product_contribution_qa_check(
        (int) get_field(ProductContributionRepository::SOURCE_EVENT_FIELD, $product_id, false) === $event_id
            || (string) get_field(ProductContributionRepository::SOURCE_EVENT_FIELD, $product_id, false) === (string) $event_id,
        'Owned Product source Event did not read back through ACF.'
    );
}

function vg_product_contribution_qa_create_contribution(
    int $product_id,
    int $person_id,
    array $roles,
    int $order,
    string $status,
    string $token,
    array &$owned
): int {
    $before = vg_product_contribution_qa_post_count(ProductContributionRepository::POST_TYPE);
    $created = (new ProductContributionService())->create($product_id, $person_id, $roles, $order, $status);
    vg_product_contribution_qa_check(! is_wp_error($created) && (int) $created > 0, 'Canonical ProductContributionService rejected an expected QA contribution: ' . (is_wp_error($created) ? $created->get_error_message() : 'invalid ID'));
    $id = (int) $created;
    vg_product_contribution_qa_track($owned, $id, ProductContributionRepository::POST_TYPE, ['product_id' => $product_id, 'person_id' => $person_id]);
    vg_product_contribution_qa_mark_owned($id, ProductContributionRepository::POST_TYPE, $token);
    vg_product_contribution_qa_check(get_post($id) instanceof WP_Post && get_post($id)->post_type === ProductContributionRepository::POST_TYPE, 'Service-created Product Contribution has an unexpected post type.');
    vg_product_contribution_qa_check(
        (int) get_field(ProductContributionRepository::PRODUCT_FIELD, $id) === $product_id
            && (int) get_field(ProductContributionRepository::PERSON_FIELD, $id) === $person_id,
        'Service-created Product Contribution endpoints differ from the requested pair.'
    );
    vg_product_contribution_qa_check(vg_product_contribution_qa_post_count(ProductContributionRepository::POST_TYPE) === $before + 1, 'Canonical contribution creation did not add exactly one post.');
    return $id;
}

function vg_product_contribution_qa_preflight(array &$context): void
{
    $context['identity'] = vg_product_contribution_qa_runtime_identity();
    vg_product_contribution_qa_validate_identity($context['identity']);
    vg_runtime_mutation_assert_session('vgnova-local-10039');
    vg_product_contribution_qa_record_platform_core_integrity(
        $context,
        'preflight',
        $context['identity']['platform_core_path'],
        __DIR__ . '/fixtures/voelgoed-platform-core-pe005.manifest.json',
        VG_PRODUCT_CONTRIBUTION_QA_PLATFORM_CORE_AUTHORITY
    );
    foreach ([
        ProductContributionRepository::POST_TYPE,
        ProductContributionRepository::PERSON_POST_TYPE,
        ProductContributionRepository::PRODUCT_POST_TYPE,
        'tc_events',
        EventParticipationRepository::POST_TYPE,
    ] as $type) {
        vg_product_contribution_qa_check(post_type_exists($type), 'Required runtime post type is not registered: ' . $type);
    }
    vg_product_contribution_qa_check(taxonomy_exists(ProductRoleVocabulary::TAXONOMY), 'product_role taxonomy is not registered.');
    vg_product_contribution_qa_check(class_exists(ProductContributionService::class), 'ProductContributionService is not loaded.');
    vg_product_contribution_qa_check(class_exists(EventParticipationService::class), 'EventParticipationService is not loaded.');
    vg_product_contribution_qa_check(class_exists(PersonProductsBricks::class), 'PersonProductsBricks is not loaded.');
    $context['acf_schema'] = vg_product_contribution_qa_verify_acf_schema();
    $context['roles'] = vg_product_contribution_qa_role_terms();
    $expected_roles = ProductRoleVocabulary::approved_terms();
    ksort($expected_roles);
    $actual_roles = array_column($context['roles'], 'name', 'slug');
    ksort($actual_roles);
    vg_product_contribution_qa_check($actual_roles === $expected_roles && count($context['roles']) === 4, 'Product Role vocabulary is not exactly the four approved terms.');
    vg_product_contribution_qa_check(vg_product_contribution_qa_role_relationship_count() === 0, 'Product Role relationships exist before QA.');
    vg_product_contribution_qa_check(vg_product_contribution_qa_post_count('product_contribution') === 0, 'Persistent Product Contribution posts exist before QA.');
    vg_product_contribution_qa_check(vg_product_contribution_qa_post_count('person') === 0, 'Persistent Person posts differ from the authorized baseline.');
    vg_product_contribution_qa_check(vg_product_contribution_qa_post_count(EventParticipationRepository::POST_TYPE) === 0, 'Persistent Event Participation posts differ from the authorized baseline.');
    $context['persistent_pre'] = vg_product_contribution_qa_persistent_state();
    vg_product_contribution_qa_check($context['persistent_pre']['target_acf_database_definitions'] === 3, 'Exactly three target Product Contribution ACF definitions must be persisted before QA.');
    vg_product_contribution_qa_check($context['persistent_pre']['override_rows'] === ['product_people_override' => 0, '_product_people_override' => 0], 'Historical Product People override rows must remain absent.');
    $context['protected_before'] = vg_product_contribution_qa_protected_snapshot();
    $context['preexisting_api_ids'] = array_column(vg_product_contribution_qa_tickera_api_rows(), 'id');
    $context['product_ids_before'] = array_column($context['persistent_pre']['product_people_inventory'], 'id');
    vg_product_contribution_qa_check(
        $context['product_ids_before'] === VG_PRODUCT_CONTRIBUTION_QA_EXPECTED_PRODUCT_IDS,
        'The 20-Product preflight ID inventory differs from the approved baseline.'
    );
    $context['product_people_inventory_before'] = $context['persistent_pre']['product_people_inventory'];
    $context['owned'] = [
        'product_contribution' => [],
        'event_participation' => [],
        'product' => [],
        'person' => [],
        'tc_events' => [],
    ];
    $context['assertions'] = [];
    $context['created_side_effects'] = [];
    $context['cleanup'] = [
        'errors' => [],
        'woocommerce_lookup' => [],
        'woocommerce_lookup_final' => [],
        'tickera' => ['deleted' => [], 'unexplained' => [], 'errors' => []],
    ];
}

function vg_product_contribution_qa_cleanup(array &$context, string $token): void
{
    if (! isset($context['owned']) || ! is_array($context['owned'])) {
        return;
    }
    $owned = $context['owned'];
    $contribution_errors = [];
    foreach ($owned['product_contribution'] ?? [] as $id => $record) {
        try {
            vg_product_contribution_qa_delete_owned_record($record, $token);
        } catch (Throwable $error) {
            $contribution_errors[] = ['id' => (int) $id, 'type' => 'product_contribution', 'message' => $error->getMessage()];
        }
    }
    $participation_errors = [];
    foreach ($owned['event_participation'] ?? [] as $id => $record) {
        try {
            vg_product_contribution_qa_delete_owned_record($record, $token);
        } catch (Throwable $error) {
            $participation_errors[] = ['id' => (int) $id, 'type' => 'event_participation', 'message' => $error->getMessage()];
        }
    }
    $product_errors = [];
    foreach ($owned['product'] ?? [] as $id => $record) {
        try {
            $lookup_evidence = null;
            vg_product_contribution_qa_delete_owned_record($record, $token, null, $lookup_evidence);
            if ($lookup_evidence !== null) {
                $context['cleanup']['woocommerce_lookup'][] = $lookup_evidence;
            }
        } catch (Throwable $error) {
            if (isset($lookup_evidence)) {
                $context['cleanup']['woocommerce_lookup'][] = $lookup_evidence;
            }
            $product_errors[] = ['id' => (int) $id, 'type' => 'product', 'message' => $error->getMessage()];
        }
    }
    $context['cleanup']['woocommerce_lookup_final'] = [];
    foreach ($owned['product'] ?? [] as $id => $record) {
        $product_id = (int) ($record['id'] ?? $id);
        try {
            $lookup_rows = vg_product_contribution_qa_woocommerce_lookup_rows($product_id);
            $context['cleanup']['woocommerce_lookup_final'][] = [
                'product_id' => $product_id,
                'tables' => $lookup_rows,
            ];
            foreach ($lookup_rows as $table => $state) {
                if ($state['table_exists'] && $state['rows'] !== 0) {
                    $product_errors[] = [
                        'id' => $product_id,
                        'type' => 'product',
                        'message' => 'Final WooCommerce lookup residue remains for Product ' . $product_id . ' in ' . $table . '.',
                    ];
                }
            }
        } catch (Throwable $error) {
            $product_errors[] = [
                'id' => $product_id,
                'type' => 'product',
                'message' => 'Final WooCommerce lookup audit failed for Product ' . $product_id . ': ' . $error->getMessage(),
            ];
        }
    }
    $can_delete_people = $contribution_errors === [] && $participation_errors === [];
    $person_errors = [];
    if ($can_delete_people) {
        foreach ($owned['person'] ?? [] as $id => $record) {
            try {
                vg_product_contribution_qa_delete_owned_record($record, $token);
            } catch (Throwable $error) {
                $person_errors[] = ['id' => (int) $id, 'type' => 'person', 'message' => $error->getMessage()];
            }
        }
    } elseif (($owned['person'] ?? []) !== []) {
        $person_errors[] = ['id' => 0, 'type' => 'person', 'message' => 'Persons retained because Product Contribution or Event Participation dependencies remain.'];
    }
    $event_errors = [];
    $events_clear = $contribution_errors === [] && $participation_errors === [] && $product_errors === [] && $person_errors === [];
    if ($events_clear) {
        $event_ids = array_map('intval', array_keys($owned['tc_events'] ?? []));
        $tickera = vg_product_contribution_qa_cleanup_tickera($event_ids, $context['preexisting_api_ids'] ?? [], $token);
        $context['cleanup']['tickera'] = $tickera;
        if ($tickera['errors'] === [] && $tickera['unexplained'] === []) {
            foreach ($owned['tc_events'] ?? [] as $id => $record) {
                try {
                    vg_product_contribution_qa_delete_owned_record($record, $token);
                } catch (Throwable $error) {
                    $event_errors[] = ['id' => (int) $id, 'type' => 'tc_events', 'message' => $error->getMessage()];
                }
            }
        } else {
            foreach ($owned['tc_events'] ?? [] as $id => $_record) {
                $event_errors[] = ['id' => (int) $id, 'type' => 'tc_events', 'message' => 'Event retained because Tickera side-effect ownership is ambiguous.'];
            }
        }
    } elseif (($owned['tc_events'] ?? []) !== []) {
        $event_errors[] = ['id' => (int) array_key_first($owned['tc_events']), 'type' => 'tc_events', 'message' => 'Event retained while a dependent owned record remains.'];
    }
    $context['cleanup']['errors'] = [...$contribution_errors, ...$participation_errors, ...$product_errors, ...$person_errors, ...$event_errors];
}

function vg_product_contribution_qa_owned_residue(array $context, string $token): array
{
    $remaining = [];
    foreach (($context['owned'] ?? []) as $type => $records) {
        foreach ($records as $id => $_record) {
            if (get_post((int) $id) instanceof WP_Post) {
                $remaining[] = ['id' => (int) $id, 'type' => $type];
            }
        }
    }
    foreach (vg_product_contribution_qa_tickera_api_rows() as $row) {
        if (! in_array((int) $row['id'], $context['preexisting_api_ids'] ?? [], true)
            && str_contains((string) ($row['api_key_name'] ?? ''), $token)) {
            $remaining[] = ['id' => (int) $row['id'], 'type' => 'tc_api_keys'];
        }
    }
    if (vg_product_contribution_qa_token_exists($token)) {
        $remaining[] = ['id' => null, 'type' => 'token_residue'];
    }
    return $remaining;
}

function vg_product_contribution_qa_run(): array
{
    vg_runtime_mutation_assert_session('vgnova-local-10039');
    $run_id = gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(5));
    $token = VG_PRODUCT_CONTRIBUTION_QA_OWNER_LABEL . ' ' . $run_id;
    $context = [];
    $guard = vg_product_contribution_qa_guarded_run(
        static function () use (&$context): void {
            vg_product_contribution_qa_preflight($context);
        },
        static function () use (&$context, $token): void {
            $assert = static function (string $name, bool $passed, array $evidence = []) use (&$context): void {
                vg_product_contribution_qa_assertion($context['assertions'], $name, $passed, $evidence);
            };
            $roles = [];
            foreach (vg_product_contribution_qa_role_terms() as $term) {
                $roles[$term['slug']] = (int) $term['id'];
            }
            $manual_product = vg_product_contribution_qa_create_product($token, 'Manual Product', 'publish', $context['owned']);
            vg_product_contribution_qa_set_mode($manual_product, ProductPeopleFields::MODE_MANUAL);
            $person_a = vg_product_contribution_qa_create_person($token, 'Person A', 'publish', $context['owned']);
            $person_b = vg_product_contribution_qa_create_person($token, 'Person B', 'publish', $context['owned']);
            $person_c = vg_product_contribution_qa_create_person($token, 'Person C', 'publish', $context['owned']);
            $person_draft = vg_product_contribution_qa_create_person($token, 'Draft Person', 'draft', $context['owned']);
            $empty_person = vg_product_contribution_qa_create_person($token, 'Empty Person', 'publish', $context['owned']);
            $manual_b = vg_product_contribution_qa_create_contribution($manual_product, $person_b, [$roles['skrywer']], 20, 'publish', $token, $context['owned']);
            $manual_a = vg_product_contribution_qa_create_contribution($manual_product, $person_a, [$roles['aanbieder']], 10, 'publish', $token, $context['owned']);
            $manual_c = vg_product_contribution_qa_create_contribution($manual_product, $person_c, [$roles['instrukteur']], 10, 'publish', $token, $context['owned']);
            $manual_draft = vg_product_contribution_qa_create_contribution($manual_product, $person_draft, [$roles['skepper']], 15, 'publish', $token, $context['owned']);

            $repo = new ProductContributionRepository();
            $resolver = new ProductPeopleResolver($repo);
            $manual_contribution_ids = $repo->contributions_for_product($manual_product);
            $expected_contribution_ids = [$manual_a, $manual_c, $manual_draft, $manual_b];
            $manual_people = $repo->people_for_product($manual_product);
            $assert('manual_product_people_authority_and_order',
                $manual_contribution_ids === $expected_contribution_ids
                    && $manual_people === [$person_a, $person_c, $person_b]
                    && $resolver->people_for_product($manual_product) === $manual_people,
                ['contribution_ids' => $manual_contribution_ids, 'people_ids' => $manual_people, 'resolver_ids' => $resolver->people_for_product($manual_product)]
            );

            $second_product = vg_product_contribution_qa_create_product($token, 'Second Manual Product', 'publish', $context['owned']);
            vg_product_contribution_qa_set_mode($second_product, ProductPeopleFields::MODE_MANUAL);
            $second_a = vg_product_contribution_qa_create_contribution($second_product, $person_a, [$roles['skrywer']], 0, 'publish', $token, $context['owned']);
            $draft_product = vg_product_contribution_qa_create_product($token, 'Draft Product', 'draft', $context['owned']);
            vg_product_contribution_qa_set_mode($draft_product, ProductPeopleFields::MODE_MANUAL);
            $draft_product_contribution = vg_product_contribution_qa_create_contribution($draft_product, $person_a, [$roles['aanbieder']], 0, 'publish', $token, $context['owned']);
            $invalid_mode_product = vg_product_contribution_qa_create_product($token, 'Invalid Mode Product', 'publish', $context['owned']);
            vg_product_contribution_qa_set_mode($invalid_mode_product, ProductPeopleFields::MODE_MANUAL);
            $invalid_mode_contribution = vg_product_contribution_qa_create_contribution($invalid_mode_product, $person_a, [$roles['aanbieder']], 0, 'publish', $token, $context['owned']);
            vg_product_contribution_qa_set_mode($invalid_mode_product, 'not-a-valid-mode');
            $reverse_products = $repo->products_for_person($person_a);
            $assert('reverse_person_products_are_sorted_and_published_only',
                $reverse_products === [$manual_product, $second_product]
                    && $repo->products_for_person($person_draft) === [],
                ['published_products' => $reverse_products, 'draft_person_products' => $repo->products_for_person($person_draft), 'excluded_draft_product' => $draft_product]
            );

            $before_invalid = vg_product_contribution_qa_post_count(ProductContributionRepository::POST_TYPE);
            $wrong_taxonomy_term = (int) (get_terms(['taxonomy' => 'event_role', 'hide_empty' => false])[0]->term_id ?? 0);
            vg_product_contribution_qa_check($wrong_taxonomy_term > 0, 'A pre-existing Event Role is required for wrong-taxonomy validation.');
            $invalid_role_results = [];
            foreach ([
                'zero_roles' => [],
                'malformed_role_id' => [999999999],
                'wrong_taxonomy' => [$wrong_taxonomy_term],
            ] as $case => $candidate_roles) {
                $result = (new ProductContributionService())->create($manual_product, $empty_person, $candidate_roles, 90, 'publish');
                $created_unexpectedly = vg_product_contribution_qa_track_service_contribution_result(
                    $result,
                    $manual_product,
                    $empty_person,
                    $token,
                    $context['owned']
                );
                $invalid_role_results[$case] = ! $created_unexpectedly;
            }
            $after_invalid = vg_product_contribution_qa_post_count(ProductContributionRepository::POST_TYPE);
            $assert('role_integrity_rejects_invalid_published_contributions_without_partial_rows',
                $invalid_role_results === ['zero_roles' => true, 'malformed_role_id' => true, 'wrong_taxonomy' => true]
                    && $after_invalid === $before_invalid
                    && (new ProductContributionRepository())->find_pair_ids($manual_product, $empty_person) === [],
                ['rejected' => $invalid_role_results, 'contribution_count_before' => $before_invalid, 'contribution_count_after' => $after_invalid]
            );

            $before_duplicate = vg_product_contribution_qa_post_count(ProductContributionRepository::POST_TYPE);
            $duplicate = (new ProductContributionService())->create($manual_product, $person_a, [$roles['aanbieder']], 99, 'publish');
            $duplicate_created_unexpectedly = vg_product_contribution_qa_track_service_contribution_result(
                $duplicate,
                $manual_product,
                $person_a,
                $token,
                $context['owned']
            );
            $after_duplicate = vg_product_contribution_qa_post_count(ProductContributionRepository::POST_TYPE);
            $assert('pair_uniqueness_rejects_second_nonretired_pair',
                ! $duplicate_created_unexpectedly
                    && $after_duplicate === $before_duplicate
                    && $repo->find_pair_ids($manual_product, $person_a) === [$manual_a],
                ['rejected' => is_wp_error($duplicate), 'pair_ids' => $repo->find_pair_ids($manual_product, $person_a)]
            );

            $initial_title = get_the_title($manual_a);
            $expected_initial_title = get_the_title($manual_product) . ' — ' . get_the_title($person_a);
            $assert('contribution_title_is_derived_at_creation', $initial_title === $expected_initial_title, ['title' => $initial_title]);
            $renamed_person_title = $token . ' Person A Renamed';
            $renamed = wp_update_post(['ID' => $person_a, 'post_title' => $renamed_person_title], true);
            vg_product_contribution_qa_check(! is_wp_error($renamed) && (int) $renamed === $person_a, 'WordPress could not rename the owned Person endpoint.');
            $renamed_product_title = $token . ' Manual Product Renamed';
            $renamed = wp_update_post(['ID' => $manual_product, 'post_title' => $renamed_product_title], true);
            vg_product_contribution_qa_check(! is_wp_error($renamed) && (int) $renamed === $manual_product, 'WordPress could not rename the owned Product endpoint.');
            $expected_derived_title = $renamed_product_title . ' — ' . $renamed_person_title;
            $assert('admin_endpoint_title_sync_updates_nonretired_contributions', get_the_title($manual_a) === $expected_derived_title, ['title' => get_the_title($manual_a)]);

            $event_id = vg_product_contribution_qa_create_event($token, $context['owned']);
            $event_nonmember = vg_product_contribution_qa_create_person($token, 'Event Nonmember', 'publish', $context['owned']);
            $event_product = vg_product_contribution_qa_create_product($token, 'Event Product', 'publish', $context['owned'], $event_id);
            vg_product_contribution_qa_set_mode($event_product, ProductPeopleFields::MODE_EVENT);
            vg_product_contribution_qa_set_source_event($event_product, $event_id);
            $event_service = new EventParticipationService();
            $participation_id = $event_service->create($event_id, $person_a, [], 5, 'publish');
            vg_product_contribution_qa_check(! is_wp_error($participation_id) && (int) $participation_id > 0, 'Canonical EventParticipationService rejected the owned Event membership.');
            $participation_id = (int) $participation_id;
            vg_product_contribution_qa_track($context['owned'], $participation_id, EventParticipationRepository::POST_TYPE, ['event_id' => $event_id, 'person_id' => $person_a]);
            vg_product_contribution_qa_mark_owned($participation_id, EventParticipationRepository::POST_TYPE, $token);
            $event_contribution = vg_product_contribution_qa_create_contribution($event_product, $person_a, [$roles['skrywer'], $roles['skepper']], 0, 'publish', $token, $context['owned']);
            $before_nonmember_attempt = vg_product_contribution_qa_post_count(ProductContributionRepository::POST_TYPE);
            $event_nonmember_attempt = (new ProductContributionService())->create(
                $event_product,
                $event_nonmember,
                [$roles['aanbieder']],
                1,
                'publish'
            );
            if (! is_wp_error($event_nonmember_attempt) && (int) $event_nonmember_attempt > 0) {
                $event_nonmember_attempt = (int) $event_nonmember_attempt;
                vg_product_contribution_qa_track($context['owned'], $event_nonmember_attempt, ProductContributionRepository::POST_TYPE, [
                    'product_id' => $event_product,
                    'person_id' => $event_nonmember,
                ]);
                vg_product_contribution_qa_mark_owned($event_nonmember_attempt, ProductContributionRepository::POST_TYPE, $token);
            }
            $after_nonmember_attempt = vg_product_contribution_qa_post_count(ProductContributionRepository::POST_TYPE);
            $event_people_before_retirement = $repo->people_for_product($event_product);
            $event_union_products = $repo->products_for_person($person_a);
            $expected_union_products = [$manual_product, $second_product, $event_product];
            sort($expected_union_products);
            $assert('event_membership_authority_comes_only_from_event_participation',
                $event_people_before_retirement === [$person_a]
                    && $repo->people_for_product($event_product) === [$person_a]
                    && $repo->products_for_person($event_nonmember) === []
                    && is_wp_error($event_nonmember_attempt)
                    && $after_nonmember_attempt === $before_nonmember_attempt
                    && $repo->associations_for_product($event_product)[0]['product_role_ids'] === [$roles['skrywer'], $roles['skepper']],
                [
                    'event_people' => $event_people_before_retirement,
                    'nonmember_create_rejected' => is_wp_error($event_nonmember_attempt),
                    'nonmember_create_error' => is_wp_error($event_nonmember_attempt) ? $event_nonmember_attempt->get_error_message() : null,
                    'contribution_count_before' => $before_nonmember_attempt,
                    'contribution_count_after' => $after_nonmember_attempt,
                    'nonmember_reverse_products' => $repo->products_for_person($event_nonmember),
                ]
            );
            $assert('reverse_products_union_manual_and_event_products_by_id',
                $event_union_products === $expected_union_products
                    && count($event_union_products) === count(array_unique($event_union_products)),
                ['actual_product_ids' => $event_union_products, 'expected_product_ids' => $expected_union_products]
            );
            $retired = wp_update_post(['ID' => $participation_id, 'post_status' => 'trash'], true);
            vg_product_contribution_qa_check(! is_wp_error($retired) && (int) $retired === $participation_id, 'Could not retire the owned Event Participation for the stale-annotation assertion.');
            $stored_event_contribution = get_post($event_contribution);
            $reverse_products_without_event = $repo->products_for_person($person_a);
            $expected_manual_products = [$manual_product, $second_product];
            sort($expected_manual_products);
            $assert('stale_event_annotation_is_stored_but_inactive',
                $stored_event_contribution instanceof WP_Post
                    && $repo->contributions_for_product($event_product) === []
                    && $repo->people_for_product($event_product) === []
                    && $reverse_products_without_event === $expected_manual_products,
                ['stored_contribution_id' => $event_contribution, 'active_contribution_ids' => $repo->contributions_for_product($event_product), 'event_people' => $repo->people_for_product($event_product), 'reverse_products' => $reverse_products_without_event]
            );
            $restored = wp_update_post(['ID' => $participation_id, 'post_status' => 'publish'], true);
            vg_product_contribution_qa_check(! is_wp_error($restored) && (int) $restored === $participation_id, 'Could not restore the owned Event Participation after the stale-annotation assertion.');
            $restored_union_products = $repo->products_for_person($person_a);
            $assert('reverse_event_product_returns_after_event_participation_restore',
                $restored_union_products === $expected_union_products
                    && count($restored_union_products) === count(array_unique($restored_union_products)),
                ['actual_product_ids' => $restored_union_products, 'expected_product_ids' => $expected_union_products]
            );

            $before_malformed = $repo->contributions_for_product($second_product);
            $second_contribution = $second_a;
            vg_product_contribution_qa_with_temporary_contribution_person_endpoint(
                $second_contribution,
                $event_id,
                $person_a,
                static function () use ($repo, $second_product, $before_malformed, $assert): void {
                    $malformed_filtered = $repo->contributions_for_product($second_product) === [];
                    $assert(
                        'public_filtering_excludes_malformed_contribution_endpoint',
                        $malformed_filtered,
                        ['before_ids' => $before_malformed]
                    );
                }
            );

            $assert('public_filtering_excludes_unpublished_people_invalid_modes_and_stale_annotations',
                ! in_array($person_draft, $repo->people_for_product($manual_product), true)
                    && $repo->people_for_product($invalid_mode_product) === []
                    && ! in_array($invalid_mode_product, $repo->products_for_person($person_a), true)
                    && $repo->people_for_product($event_product) === [$person_a],
                ['manual_people' => $repo->people_for_product($manual_product), 'invalid_mode_people' => $repo->people_for_product($invalid_mode_product), 'event_people_after_restore' => $repo->people_for_product($event_product)]
            );

            $adapter_registered = has_filter('bricks/posts/query_vars') !== false;
            $GLOBALS['post'] = get_post($person_a);
            setup_postdata($GLOBALS['post']);
            $query_args = apply_filters('bricks/posts/query_vars', ['ignored' => true], ['_cssClasses' => 'vg-person-products-query'], 'task8c-owned', 'posts');
            wp_reset_postdata();
            $expected_products = $repo->products_for_person($person_a);
            $query_post_type = $query_args['post_type'] ?? null;
            $query_post_type_is_product = $query_post_type === 'product' || $query_post_type === ['product'];
            $query_valid = $adapter_registered
                && $expected_products === $expected_union_products
                && $query_post_type_is_product
                && ($query_args['post_status'] ?? null) === 'publish'
                && ($query_args['post__in'] ?? null) === $expected_products
                && ($query_args['orderby'] ?? null) === 'post__in'
                && ($query_args['posts_per_page'] ?? null) === -1;
            $assert('person_products_bricks_adapter_resolves_canonical_repository_ids', $query_valid, ['query' => $query_args, 'expected_product_ids' => $expected_products]);
            $GLOBALS['post'] = get_post($empty_person);
            setup_postdata($GLOBALS['post']);
            $empty_query = apply_filters('bricks/posts/query_vars', ['ignored' => true], ['_cssClasses' => 'vg-person-products-query'], 'task8c-empty', 'posts');
            wp_reset_postdata();
            $empty_post_type = $empty_query['post_type'] ?? null;
            $empty_query_valid = ($empty_post_type === 'product' || $empty_post_type === ['product'])
                && ($empty_query['post_status'] ?? null) === 'publish'
                && ($empty_query['post__in'] ?? null) === [0]
                && ($empty_query['orderby'] ?? null) === 'post__in'
                && ($empty_query['posts_per_page'] ?? null) === -1;
            $assert('person_products_bricks_empty_result_uses_post_in_zero',
                $empty_query_valid,
                ['query' => $empty_query]
            );

            $all_created = [];
            foreach ($context['owned'] as $type => $records) {
                foreach (array_keys($records) as $id) {
                    $all_created[$type][] = (int) $id;
                }
            }
            $context['owned_created_ids'] = $all_created;
            $context['expected_product_ids_after'] = $context['product_ids_before'];
            $context['expected_product_ids_after'] = array_values(array_unique($context['expected_product_ids_after']));
            sort($context['expected_product_ids_after']);
        },
        static function () use (&$context, $token): void {
            vg_product_contribution_qa_cleanup($context, $token);
        }
    );

    $remaining = [];
    $cleanup_errors = $guard['cleanup_errors'];
    if ($guard['mutation_started']) {
        $remaining = vg_product_contribution_qa_owned_residue($context, $token);
        $cleanup_errors = [...$cleanup_errors, ...($context['cleanup']['errors'] ?? []), ...($context['cleanup']['tickera']['errors'] ?? [])];
        if ($remaining !== []) {
            $cleanup_errors[] = ['message' => 'Owned QA records or run-token residue remain after cleanup.', 'remaining' => $remaining];
        }
        try {
            vg_product_contribution_qa_record_platform_core_integrity(
                $context,
                'post_cleanup',
                $context['identity']['platform_core_path'],
                __DIR__ . '/fixtures/voelgoed-platform-core-pe005.manifest.json',
                VG_PRODUCT_CONTRIBUTION_QA_PLATFORM_CORE_AUTHORITY
            );
        } catch (Throwable $error) {
            $cleanup_errors[] = ['phase' => 'post_cleanup_platform_core_integrity', 'message' => $error->getMessage()];
        }
        try {
            $context['protected_after'] = vg_product_contribution_qa_protected_snapshot();
            $context['persistent_post'] = vg_product_contribution_qa_persistent_state();
            vg_product_contribution_qa_check($context['protected_after'] === $context['protected_before'], 'Protected state fingerprints changed during runtime QA.');
            vg_product_contribution_qa_check($context['persistent_post']['product_contribution_posts'] === 0, 'Product Contribution posts remain after QA cleanup.');
            vg_product_contribution_qa_check($context['persistent_post']['person_posts'] === $context['persistent_pre']['person_posts'], 'Person count did not return to its pre-QA baseline.');
            vg_product_contribution_qa_check($context['persistent_post']['event_participation_posts'] === $context['persistent_pre']['event_participation_posts'], 'Event Participation count did not return to its pre-QA baseline.');
            vg_product_contribution_qa_check($context['persistent_post']['product_role_terms'] === 4 && $context['persistent_post']['product_role_relationships'] === 0, 'Product Role vocabulary/relationships differ from the approved persistent state.');
            vg_product_contribution_qa_check($context['persistent_post']['target_acf_database_definitions'] === 3, 'Target Product Contribution ACF database definition count changed during QA.');
            vg_product_contribution_qa_check($context['persistent_post']['override_rows'] === ['product_people_override' => 0, '_product_people_override' => 0], 'Historical Product People override rows changed during QA.');
            vg_product_contribution_qa_check(
                $context['persistent_post']['product_people_inventory'] === $context['product_people_inventory_before'],
                'Product inventory, product_people_mode, or Product People override values changed during QA.'
            );
        } catch (Throwable $error) {
            $cleanup_errors[] = ['phase' => 'post_cleanup_audit', 'message' => $error->getMessage()];
        }
    }

    $created_ids = $context['owned_created_ids'] ?? [];
    if ($created_ids === [] && isset($context['owned'])) {
        foreach ($context['owned'] as $type => $records) {
            foreach (array_keys($records) as $id) {
                $created_ids[$type][] = (int) $id;
            }
        }
    }
    $assertions = $context['assertions'] ?? [];
    if (isset($context['acf_schema']['verified'])) {
        array_unshift($assertions, ['name' => 'acf_schema_source_parity', 'result' => 'PASS', 'evidence' => $context['acf_schema']]);
    }
    $roles_verified = isset($context['roles']) && count($context['roles']) === 4;
    if ($roles_verified) {
        array_unshift($assertions, ['name' => 'product_role_vocabulary_exact', 'result' => 'PASS', 'evidence' => $context['roles']]);
    }
    $report = [
        'run_token' => $token,
        'run_id' => $run_id,
        'mutation_started' => $guard['mutation_started'],
        'runtime_identity' => $context['identity'] ?? null,
        'platform_core_integrity' => $context['platform_core_integrity'] ?? null,
        'platform_core_integrity_post_cleanup' => $context['platform_core_integrity_post_cleanup'] ?? null,
        'acf_schema_verified' => $context['acf_schema'] ?? false,
        'product_roles_verified' => $roles_verified,
        'owned_records_created' => $created_ids,
        'assertions' => $assertions,
        'cleanup_attempted' => $guard['cleanup_attempted'],
        'cleanup' => $context['cleanup'] ?? [],
        'cleanup_errors' => $cleanup_errors,
        'owned_records_remaining' => $remaining,
        'persistent_state_pre' => $context['persistent_pre'] ?? null,
        'persistent_state_post' => $context['persistent_post'] ?? null,
        'protected_state_result' => isset($context['protected_before'], $context['protected_after'])
            ? ($context['protected_before'] === $context['protected_after'] ? 'PASS' : 'FAIL')
            : 'NOT_RUN',
        'protected_state_fingerprints' => [
            'before' => $context['protected_before'] ?? null,
            'after' => $context['protected_after'] ?? null,
        ],
        'failure' => $guard['error'],
        'result' => $guard['mutation_started']
            && $guard['error'] === null
            && $cleanup_errors === []
            && $remaining === []
            && ($context['protected_before'] ?? null) === ($context['protected_after'] ?? false)
            ? 'PASS'
            : 'FAIL',
    ];
    return $report;
}

if (defined('ABSPATH') && ! defined('VG_PRODUCT_CONTRIBUTION_QA_LIBRARY_ONLY')) {
    try {
        $report = vg_product_contribution_qa_run();
    } catch (Throwable $error) {
        $report = [
            'run_token' => null,
            'mutation_started' => false,
            'runtime_identity' => null,
            'acf_schema_verified' => false,
            'product_roles_verified' => false,
            'owned_records_created' => [],
            'assertions' => [],
            'cleanup_attempted' => false,
            'cleanup_errors' => [],
            'owned_records_remaining' => [],
            'failure' => $error->getMessage(),
            'result' => 'FAIL',
        ];
    }
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
    if (($report['result'] ?? 'FAIL') !== 'PASS') {
        exit(1);
    }
}
