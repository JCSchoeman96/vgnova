<?php
declare(strict_types=1);

/**
 * Guarded Task 7B Product Single 124 consumer QA.
 *
 * Run only after the approved adapter, Product Person Card, and Template 124
 * cutover are deployed:
 *   VG_TASK_7B_OWNER_TOKEN=<unique-token> VG_TASK_7B_POST_CUTOVER=1 wp eval-file site-code/runtime-tests/product-single-124-qa.php
 *
 * The harness creates owned temporary records, exercises loaded adapters and
 * the real Product frontend, then hard-deletes only records it can prove it
 * owns. It does not write existing Products, Events, People, templates, or
 * components.
 */

use Voelgoed\PlatformCore\Event\EventParticipationRepository;
use Voelgoed\PlatformCore\Event\EventParticipationService;
use Voelgoed\PlatformCore\Product\ProductPeopleBricks;
use Voelgoed\PlatformCore\Product\ProductPeopleFields;
use Voelgoed\PlatformCore\Product\ProductPeopleResolver;
use Voelgoed\PlatformCore\Product\ProductSourceEventBricks;

if (! defined('ABSPATH')) {
    echo json_encode([
        'result' => 'FAIL',
        'phase' => 'bootstrap',
        'mutation_started' => false,
        'failures' => [['message' => 'Run this integration check inside the loaded WordPress runtime.']],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(1);
}

const VG_PRODUCT_SINGLE_124_QA_OWNER_META = '_vg_task_7b_qa_owner';
const VG_PRODUCT_SINGLE_124_QA_OWNER_LABEL = '[QA][AUTOMATED] Task 7B';
const VG_PRODUCT_SINGLE_124_QA_TEMPLATE = 124;
const VG_PRODUCT_SINGLE_124_QA_EVENT = 763;
const VG_PRODUCT_SINGLE_124_QA_PROFILES = [753, 754, 755, 756, 757];
const VG_PRODUCT_SINGLE_124_QA_PRODUCTS = [765, 767, 768];
const VG_PRODUCT_SINGLE_124_QA_TEMPLATES = [772, 774];
const VG_PRODUCT_SINGLE_124_QA_COMPONENTS = ['6b463f', '194bba'];
const VG_PRODUCT_SINGLE_124_QA_PRODUCT_FIELDS = [
    'product_people_mode',
    'canonical_people_resolver',
    'produk_bron_event',
    'produk_medewerkers',
    '_event_name',
];

require_once __DIR__ . '/product-people-qa-side-effects.php';
require_once __DIR__ . '/product-single-124-qa-request.php';

if (! defined('VG_PRODUCT_PEOPLE_QA_OWNER_META')) {
    define('VG_PRODUCT_PEOPLE_QA_OWNER_META', VG_PRODUCT_SINGLE_124_QA_OWNER_META);
}
if (! function_exists('vg_product_people_qa_check')) {
    function vg_product_people_qa_check(bool $condition, string $message): void
    {
        vg_product_single_124_qa_check($condition, $message);
    }
}
if (! function_exists('vg_product_people_qa_ints')) {
    /** @return list<int> */
    function vg_product_people_qa_ints(mixed $value): array
    {
        if ($value === null || $value === false || $value === '') {
            return [];
        }
        $values = is_array($value) ? $value : [$value];
        $ids = [];
        foreach ($values as $item) {
            if (is_int($item) || (is_string($item) && ctype_digit($item))) {
                $ids[] = (int) $item;
            }
        }

        return $ids;
    }
}
require_once __DIR__ . '/product-people-qa-contributions.php';

const VG_PRODUCT_SINGLE_124_QA_EXPECTED_PRODUCT_IDS = [
    525, 527, 529, 531, 642, 765, 767, 768, 842, 844, 845, 846,
    905, 908, 911, 914, 917, 920, 923, 1186,
];
const VG_PRODUCT_SINGLE_124_QA_EXPECTED_PRODUCT_FIELDS = [
    525 => [null, [], null, null, ''],
    527 => [null, [], null, null, ''],
    529 => [null, [], null, null, ''],
    531 => [null, [], null, null, ''],
    642 => [null, [], null, null, ''],
    765 => [null, [], '763', null, ''],
    767 => [null, [], '763', ['753'], ''],
    768 => [null, [], '763', ['753', '757'], ''],
    842 => [null, [], '', '', '763'],
    844 => [null, [], '', '', '763'],
    845 => [null, [], '', '', '763'],
    846 => [null, [], '', '', '763'],
    905 => [null, [], null, null, ''],
    908 => [null, [], null, null, ''],
    911 => [null, [], null, null, ''],
    914 => [null, [], null, null, ''],
    917 => [null, [], null, null, ''],
    920 => [null, [], null, null, ''],
    923 => [null, [], null, null, ''],
    1186 => [null, [], null, null, ''],
];
const VG_PRODUCT_SINGLE_124_QA_EXPECTED_RECORD_HASHES = [
    753 => ['18b7b826b29f9a05a2e3d2fb771a9439311b8a95178f93065cca1e3200d8185f', '987899ce4d75fa9da3f5058fdd11f2251e9721e5404f390ecf9c66bb3e416181'],
    754 => ['c13c455c09829bc9b4d0695ce0dbd908e8c633c972c090ea20e8aa889c2522b4', 'd9b8b260ea2fab4d735114dc086ff660bbafaac13ffc26dd8dd26b6aa35a9db9'],
    755 => ['fe4e34803c2bf80187caa75b59cbde759d99635fba7c0424c7b806e178f7eeb7', '286c1f63a718905c7a1c3f80d912e8dd60550b9b9e1bbe6de596d9b553c475e1'],
    756 => ['1241bed4fe6f83a38401d513a08aec21f8eaa8928e21e444018b59b2f325554a', '8a30be06a201f081bfcbd8ffc94c992ccf5b46571161521537d879a3e3909e90'],
    757 => ['dd4571c80bee776651dbce3c84a3c127d570d0a159b2111169f3238d32c1d1a6', 'c58d6f6328d34842b6a13675035764d175e132c67fe123d14f4b8c47e07377ae'],
    763 => ['278adefcf2d9edd1826aa489f074fed17cd21693a3f2f5f07d354e77384457d4', '518eb2405dd703e9ae74009a126969939f633942c2c4cf7dbc4c5afa33f2a710'],
    765 => ['ddfeba8d1e1e9b2f88532340c401f0021877cf3f941214dba4e7a6ee475a916c', '74ae5f31b7095ddd556985b9cf379295f7c0da34412220642d50e90808667874'],
    767 => ['8e02c0d733a4fef0d49dfb8970a45d8583e38e1529fa91539d7de66e18178696', '92487ca037d42a709301c313a3c54f24e8f9a8fb42bbdc25bf2a1c5f9125c288'],
    768 => ['d73e67b8fb9aad5459a9ed8ecf14db09b057899f1248976084ed5964ba42f438', '4105508490935bb9508719405a6b33b750770485bae739b78a9f8084f36976db'],
    772 => ['31dea28af3d3a91ec383546ff33dc9f089161f78213859135e81d6704300ad38', '7ced9a53bb6b1373775860958a132b46f650854ce1f9cfe8b71f92698edbf01d'],
    774 => ['89d0df125e10ccc0ebfa79faca414c665022fc445f5546c5a7ff48d0e4f68762', '40d2f52644e5e14fc0e9ead57372c75a87e1f005dc26331dcdb33aa8c36510ef'],
];
const VG_PRODUCT_SINGLE_124_QA_TEMPLATE124_PROJECTION_SHA256 = '450bd890a4413699827c8d4536b3d500d9f9555492deb85141580fdeef79dc5b';
const VG_PRODUCT_SINGLE_124_QA_TEMPLATE124_CORE_SHA256 = '5bf9d2165fc25d30602ffa37a5976199b2542021273edb8e094c0290502f68b4';
const VG_PRODUCT_SINGLE_124_QA_TEMPLATE124_META_SHA256 = 'ad786ff204f521df5b496799274c93e84a471697a4c8e8990e85225655a8cb10';
const VG_PRODUCT_SINGLE_124_QA_COMPONENTS_SHA256 = 'e929fbf066ead1b9a244dd681bb72d3b3b6f95c1927051f1518482ffd9e33fc3';
const VG_PRODUCT_SINGLE_124_QA_PROTECTED_COMPONENT_HASHES = [
    '194bba' => '481c60b1bd2310b96cae05834aa49202777788192927021d218c5d1445cadc7f',
    '6b463f' => '57370bbb83d3125758eba9a5194eb1a898b8914b546ee4d84b32f642e07aeaac',
];
const VG_PRODUCT_SINGLE_124_QA_HELPER_HASH = 'e1b7c014cd15a673d663426403a2024a21a1286075c355015f4e217d7e498fa2';
const VG_PRODUCT_SINGLE_124_QA_COPY_HASH = '58e8035502b30397b9659f952e4d49bbdbd7cdbd15070e37e5b07ebaa5ce2f70';
const VG_PRODUCT_SINGLE_124_QA_TEMPLATE124_ELEMENTS = 82;
const VG_PRODUCT_SINGLE_124_QA_EXPECTED_PERSON_COUNT = 0;
const VG_PRODUCT_SINGLE_124_QA_EXPECTED_PARTICIPATION_COUNT = 0;
const VG_PRODUCT_SINGLE_124_QA_EXPECTED_API_KEY_IDS = [42, 152, 168, 170, 173, 174, 175, 177, 182, 543, 546, 549, 552, 555, 764, 1190, 1192, 1194];
const VG_PRODUCT_SINGLE_124_QA_EXPECTED_API_KEYS_SHA256 = 'a2a0d4ba8cc6f74b552c6da341faf313385f7ea6edb4107121b546c8734ca3ef';

function vg_product_single_124_qa_check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

/** @return list<int> */
function vg_product_single_124_qa_ids(mixed $value): array
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

function vg_product_single_124_qa_db_count(string $sql, string $description): int
{
    global $wpdb;
    $value = $wpdb->get_var($sql);
    if ($wpdb->last_error !== '') {
        throw new RuntimeException('Could not read ' . $description . ': ' . $wpdb->last_error);
    }

    return (int) $value;
}

function vg_product_single_124_qa_post_count(string $post_type): int
{
    global $wpdb;
    return vg_product_single_124_qa_db_count(
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", $post_type),
        'post type ' . $post_type
    );
}

function vg_product_single_124_qa_post_type_counts(): array
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

/** Search all durable WordPress text stores before ownership-token reuse. */
function vg_product_single_124_qa_token_exists(string $token): bool
{
    global $wpdb;
    $like = '%' . $wpdb->esc_like($token) . '%';
    $checks = [
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title LIKE %s OR post_name LIKE %s OR post_content LIKE %s OR post_excerpt LIKE %s OR guid LIKE %s",
            $like, $like, $like, $like, $like
        ),
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_value LIKE %s", $like),
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_value LIKE %s", $like),
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->terms} terms LEFT JOIN {$wpdb->term_taxonomy} taxonomy ON taxonomy.term_id = terms.term_id WHERE terms.name LIKE %s OR terms.slug LIKE %s OR taxonomy.description LIKE %s",
            $like,
            $like,
            $like
        ),
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_value LIKE %s", $like),
    ];
    foreach ($checks as $index => $sql) {
        if (vg_product_single_124_qa_db_count($sql, 'ownership token store ' . $index) > 0) {
            return true;
        }
    }
    return false;
}

function vg_product_single_124_qa_post_meta_count(int $post_id): int
{
    global $wpdb;
    return vg_product_single_124_qa_db_count(
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d", $post_id),
        'metadata for post ' . $post_id
    );
}

function vg_product_single_124_qa_relationship_count(int $post_id): int
{
    global $wpdb;
    return vg_product_single_124_qa_db_count(
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE object_id = %d", $post_id),
        'term relationships for post ' . $post_id
    );
}

/** @return array{post:string,meta:string} */
function vg_product_single_124_qa_record_hashes(int $post_id): array
{
    $post = get_post($post_id);
    vg_product_single_124_qa_check($post instanceof WP_Post, 'Protected post ' . $post_id . ' is missing.');
    $meta = get_post_meta($post_id);
    ksort($meta);
    return [
        'post' => hash('sha256', serialize(get_object_vars($post))),
        'meta' => hash('sha256', serialize($meta)),
    ];
}

function vg_product_single_124_qa_remove_marker(array &$settings, string $marker): void
{
    $classes = $settings['_cssClasses'] ?? null;
    if (is_string($classes)) {
        $tokens = preg_split('/\\s+/', trim($classes), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_values(array_filter($tokens, static fn (string $class): bool => $class !== $marker));
        if ($tokens === []) {
            unset($settings['_cssClasses']);
        } else {
            $settings['_cssClasses'] = implode(' ', $tokens);
        }
    } elseif (is_array($classes)) {
        $tokens = array_values(array_filter($classes, static fn (mixed $class): bool => $class !== $marker));
        if ($tokens === []) {
            unset($settings['_cssClasses']);
        } else {
            $settings['_cssClasses'] = $tokens;
        }
    }
}

/** Normalize the four approved cutover sites, including Bricks' generated instance id for the card swap. */
function vg_product_single_124_qa_template_projection(string $card_id): array
{
    $elements = get_post_meta(VG_PRODUCT_SINGLE_124_QA_TEMPLATE, '_bricks_page_content_2', true);
    vg_product_single_124_qa_check(is_array($elements), 'Template 124 Bricks element list is unavailable.');
    $by_id = [];
    foreach ($elements as $index => $element) {
        if (is_array($element) && isset($element['id'])) {
            $by_id[(string) $element['id']] = $index;
        }
    }
    foreach (['vgpr00', 'vgpr03', 'vgpr13'] as $target_id) {
        vg_product_single_124_qa_check(isset($by_id[$target_id]), 'Template 124 target ' . $target_id . ' is missing.');
    }

    $card_instances = [];
    foreach ($elements as $index => $element) {
        if (
            is_array($element)
            && ($element['parent'] ?? null) === 'vgpr03'
            && ($element['cid'] ?? null) === $card_id
        ) {
            $card_instances[] = $index;
        }
    }
    vg_product_single_124_qa_check(count($card_instances) === 1, 'Template 124 must contain one direct Product Person Card instance under vgpr03.');
    $card_index = $card_instances[0];
    $card_instance_id = (string) ($elements[$card_index]['id'] ?? '');
    vg_product_single_124_qa_check($card_instance_id !== '', 'Product Person Card instance has no Bricks element ID.');
    vg_product_single_124_qa_check(
        ($elements[$by_id['vgpr03']]['children'] ?? []) === [$card_instance_id],
        'vgpr03 must contain only the Product Person Card as its direct loop child.'
    );

    $section_index = $by_id['vgpr00'];
    vg_product_single_124_qa_remove_marker($elements[$section_index]['settings'], 'vg-product-people-section');

    foreach (['vgpr03', 'vgpr13'] as $query_id) {
        $index = $by_id[$query_id];
        vg_product_single_124_qa_remove_marker($elements[$index]['settings'], 'vg-product-people-query');
        vg_product_single_124_qa_remove_marker($elements[$index]['settings'], 'vg-product-source-event-query');
        unset($elements[$index]['settings']['query']);
    }
    $people_index = $by_id['vgpr03'];
    vg_product_single_124_qa_remove_marker($elements[$people_index]['settings'], 'vg-product-people-query');
    $source_index = $by_id['vgpr13'];
    vg_product_single_124_qa_remove_marker($elements[$source_index]['settings'], 'vg-product-source-event-query');

    unset($elements[$card_index]);
    $elements = array_values($elements);
    $people_index = null;
    foreach ($elements as $index => $element) {
        if (($element['id'] ?? null) === 'vgpr03') {
            $people_index = $index;
            break;
        }
    }
    vg_product_single_124_qa_check(is_int($people_index), 'vgpr03 was lost while normalizing the Product Person Card instance.');
    $normalized_card = [
        'id' => 'vgpr04',
        'name' => 'div',
        'parent' => 'vgpr03',
        'settings' => [],
        'cid' => '{{product-person-card}}',
    ];
    $elements[$people_index]['children'] = ['vgpr04'];
    array_splice($elements, $people_index + 1, 0, [$normalized_card]);

    return $elements;
}

function vg_product_single_124_qa_component_label(array $component): string
{
    return (string) ($component['elements'][0]['label'] ?? '');
}

function vg_product_single_124_qa_stable_hash(mixed $value): string
{
    $previous_precision = ini_get('serialize_precision');
    if ($previous_precision === false || ini_set('serialize_precision', '-1') === false) {
        throw new RuntimeException('Could not set a stable PHP float serialization precision for Bricks component comparison.');
    }

    try {
        return hash('sha256', serialize($value));
    } finally {
        ini_set('serialize_precision', $previous_precision);
    }
}

/** @return array<string, mixed> */
function vg_product_single_124_qa_component_inventory(): array
{
    $components = get_option('bricks_components');
    vg_product_single_124_qa_check(is_array($components), 'Bricks component option is unavailable.');
    $protected = [];
    $cards = [];
    foreach ($components as $key => $component) {
        if (! is_array($component)) {
            continue;
        }
        $id = (string) ($component['id'] ?? '');
        if (in_array($id, VG_PRODUCT_SINGLE_124_QA_COMPONENTS, true)) {
            $protected[$id] = vg_product_single_124_qa_stable_hash($component);
        }
        if (vg_product_single_124_qa_component_label($component) === 'Product Person Card') {
            $cards[$id] = [
                'entry' => $component,
                'key' => $key,
                'sha256' => vg_product_single_124_qa_stable_hash($component),
            ];
        }
    }
    ksort($protected);
    return [
        'option' => $components,
        'protected' => $protected,
        'cards' => $cards,
    ];
}

/** @return array<string, list<array{template:int, element:string}>> */
function vg_product_single_124_qa_query_callers(): array
{
    global $wpdb;
    $ids = array_map('intval', $wpdb->get_col(
        $wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = %s ORDER BY ID", 'bricks_template')
    ));
    if ($wpdb->last_error !== '') {
        throw new RuntimeException('Could not read Bricks template IDs: ' . $wpdb->last_error);
    }
    $callers = [
        'product_medewerkers' => [],
        'product_source_event' => [],
        'medewerker_products' => [],
        'vg-product-people-query' => [],
        'vg-product-source-event-query' => [],
        'vg-product-people-section' => [],
    ];
    foreach ($ids as $template_id) {
        $elements = get_post_meta($template_id, '_bricks_page_content_2', true);
        if (! is_array($elements)) {
            continue;
        }
        foreach ($elements as $element) {
            if (! is_array($element)) {
                continue;
            }
            $element_id = (string) ($element['id'] ?? '');
            $mode = $element['settings']['query']['vg_query_type'] ?? null;
            if (is_string($mode) && isset($callers[$mode])) {
                $callers[$mode][] = ['template' => $template_id, 'element' => $element_id];
            }
            $classes = $element['settings']['_cssClasses'] ?? '';
            $tokens = is_string($classes)
                ? (preg_split('/\\s+/', trim($classes), -1, PREG_SPLIT_NO_EMPTY) ?: [])
                : (is_array($classes) ? $classes : []);
            foreach (array_keys($callers) as $marker) {
                if (str_starts_with($marker, 'vg-') && in_array($marker, $tokens, true)) {
                    $callers[$marker][] = ['template' => $template_id, 'element' => $element_id];
                }
            }
        }
    }
    $components = get_option('bricks_components');
    if (is_array($components)) {
        foreach ($components as $component) {
            foreach (($component['elements'] ?? []) as $element) {
                if (! is_array($element)) {
                    continue;
                }
                $mode = $element['settings']['query']['vg_query_type'] ?? null;
                if (is_string($mode) && isset($callers[$mode])) {
                    $callers[$mode][] = ['template' => 0, 'element' => (string) ($element['id'] ?? '')];
                }
            }
        }
    }
    return $callers;
}

/** @return array<int, array<string, mixed>> */
function vg_product_single_124_qa_existing_product_fields(): array
{
    $resolver = new ProductPeopleResolver();
    $result = [];
    foreach (VG_PRODUCT_SINGLE_124_QA_EXPECTED_PRODUCT_IDS as $id) {
        $result[$id] = [
            get_field('product_people_mode', $id, false),
            $resolver->people_for_product($id),
            get_field('produk_bron_event', $id, false),
            get_field('produk_medewerkers', $id, false),
            get_post_meta($id, '_event_name', true),
        ];
    }
    return $result;
}

/** @return list<int> */
function vg_product_single_124_qa_existing_product_ids(): array
{
    global $wpdb;
    $ids = array_map('intval', $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' ORDER BY ID"));
    if ($wpdb->last_error !== '') {
        throw new RuntimeException('Could not read existing Product IDs: ' . $wpdb->last_error);
    }
    return $ids;
}

/** @return list<array{id:int,post_type:string,status:string,api_key_name:mixed,event_name:mixed}> */
function vg_product_single_124_qa_api_key_rows(): array
{
    global $wpdb;
    $ids = array_map('intval', $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'tc_api_keys' ORDER BY ID"));
    if ($wpdb->last_error !== '') {
        throw new RuntimeException('Could not read Tickera API key rows: ' . $wpdb->last_error);
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

function vg_product_single_124_qa_owned_tickera_delete(int $event_id, string $owner, array $pre_ids): array
{
    $report = ['deleted' => [], 'unexplained' => [], 'failures' => []];
    $plan = vg_product_people_qa_plan_tickera_side_effects(
        vg_product_single_124_qa_api_key_rows(),
        $pre_ids,
        $owner,
        [$event_id]
    );
    $report['unexplained'] = $plan['unexplained'];
    foreach ($plan['unexplained'] as $row) {
        $report['failures'][] = ['id' => $row['id'], 'message' => 'Unowned or ambiguous Tickera side effect: ' . $row['reason']];
    }
    foreach ($plan['delete'] as $id) {
        try {
            $current = array_values(array_filter(
                vg_product_single_124_qa_api_key_rows(),
                static fn (array $row): bool => $row['id'] === $id
            ));
            $recheck = vg_product_people_qa_plan_tickera_side_effects($current, $pre_ids, $owner, [$event_id]);
            vg_product_single_124_qa_check(
                $recheck['delete'] === [$id] && $recheck['unexplained'] === [],
                'Tickera side-effect ownership could not be re-proven immediately before deletion.'
            );
            wp_delete_post($id, true);
            vg_product_single_124_qa_check(
                get_post($id) === null
                    && vg_product_single_124_qa_post_meta_count($id) === 0
                    && vg_product_single_124_qa_relationship_count($id) === 0,
                'Tickera side-effect hard delete left post, metadata, or term relationships.'
            );
            $report['deleted'][] = $id;
        } catch (Throwable $error) {
            $report['failures'][] = ['id' => $id, 'message' => $error->getMessage()];
        }
    }
    return $report;
}

/** Record the owner immediately after creation; never overwrite another marker. */
function vg_product_single_124_qa_mark(int $post_id, string $type, string $owner): void
{
    $post = get_post($post_id);
    vg_product_single_124_qa_check($post instanceof WP_Post && $post->post_type === $type, 'Created QA record has an unexpected type.');
    $current = get_post_meta($post_id, VG_PRODUCT_SINGLE_124_QA_OWNER_META, true);
    vg_product_single_124_qa_check($current === '' || $current === null || $current === $owner, 'Created QA record has a conflicting owner marker.');
    if ($current !== $owner) {
        update_post_meta($post_id, VG_PRODUCT_SINGLE_124_QA_OWNER_META, $owner);
    }
    vg_product_single_124_qa_check(get_post_meta($post_id, VG_PRODUCT_SINGLE_124_QA_OWNER_META, true) === $owner, 'Could not verify QA record ownership.');
}

function vg_product_single_124_qa_delete_owned(int $id, string $type, string $owner, ?callable $endpoint_check = null): void
{
    if ($id <= 0) {
        return;
    }
    $post = get_post($id);
    if ($post !== null) {
        vg_product_single_124_qa_check($post instanceof WP_Post && $post->post_type === $type, 'Cleanup refused a changed post type for ID ' . $id . '.');
        vg_product_single_124_qa_check(get_post_meta($id, VG_PRODUCT_SINGLE_124_QA_OWNER_META, true) === $owner, 'Cleanup refused an unowned record ' . $id . '.');
        if ($endpoint_check !== null) {
            $endpoint_check();
        }
        wp_delete_post($id, true);
    }
    vg_product_single_124_qa_check(
        get_post($id) === null
            && vg_product_single_124_qa_post_meta_count($id) === 0
            && vg_product_single_124_qa_relationship_count($id) === 0,
        'Hard delete left owned post state for ID ' . $id . '.'
    );
}

/** Cleanup only exact run-owned records, in dependency order. */
function vg_product_single_124_qa_cleanup(array $owned, string $owner, array $pre_api_ids): array
{
    $failures = [];
    $tickera = ['deleted' => [], 'unexplained' => [], 'failures' => []];
    $contributions_clear = vg_product_people_qa_delete_owned_contributions(
        $owned['contributions'],
        $owner,
        static function (int $id, string $type, string $owner_marker, ?callable $endpoint_check = null): bool {
            try {
                vg_product_single_124_qa_delete_owned($id, $type, $owner_marker, $endpoint_check);
                return true;
            } catch (Throwable) {
                return false;
            }
        }
    );

    $participations_clear = $contributions_clear;
    foreach ($owned['participations'] as $id => $pair) {
        try {
            vg_product_single_124_qa_delete_owned(
                (int) $id,
                EventParticipationRepository::POST_TYPE,
                $owner,
                static function () use ($id, $pair): void {
                    vg_product_single_124_qa_check(
                        (int) get_field(EventParticipationRepository::EVENT_FIELD, (int) $id) === (int) $pair['event_id']
                            && (int) get_field(EventParticipationRepository::PERSON_FIELD, (int) $id) === (int) $pair['person_id'],
                        'Cleanup refused a changed Event Participation endpoint.'
                    );
                }
            );
        } catch (Throwable $error) {
            $participations_clear = false;
            $failures[] = ['id' => (int) $id, 'message' => 'Event Participation: ' . $error->getMessage()];
        }
    }

    $product_clear = true;
    try {
        vg_product_single_124_qa_delete_owned((int) $owned['product'], 'product', $owner);
    } catch (Throwable $error) {
        $product_clear = false;
        $failures[] = ['id' => (int) $owned['product'], 'message' => 'Product: ' . $error->getMessage()];
    }

    $persons_clear = $product_clear && $participations_clear;
    if ($persons_clear) {
        foreach ($owned['persons'] as $id) {
            try {
                vg_product_single_124_qa_delete_owned((int) $id, 'person', $owner);
            } catch (Throwable $error) {
                $persons_clear = false;
                $failures[] = ['id' => (int) $id, 'message' => 'Person: ' . $error->getMessage()];
            }
        }
    } elseif ($owned['persons'] !== []) {
        $failures[] = ['id' => 0, 'message' => 'Persons retained because Product or Event Participation dependencies were not cleared.'];
    }

    $event_clear = $product_clear && $participations_clear && $persons_clear;
    if ($event_clear) {
        foreach ($owned['events'] as $id) {
            try {
                $event_tickera = vg_product_single_124_qa_owned_tickera_delete((int) $id, $owner, $pre_api_ids);
                $tickera['deleted'] = [...$tickera['deleted'], ...$event_tickera['deleted']];
                $tickera['unexplained'] = [...$tickera['unexplained'], ...$event_tickera['unexplained']];
                $tickera['failures'] = [...$tickera['failures'], ...$event_tickera['failures']];
            } catch (Throwable $error) {
                $event_tickera = ['deleted' => [], 'unexplained' => [], 'failures' => [['id' => (int) $id, 'message' => $error->getMessage()]]];
                $tickera['failures'] = [...$tickera['failures'], ...$event_tickera['failures']];
            }
            foreach ($event_tickera['failures'] as $failure) {
                $failures[] = ['id' => $failure['id'], 'message' => 'Tickera side effect: ' . $failure['message']];
            }
            if ($event_tickera['failures'] === [] && $event_tickera['unexplained'] === []) {
                try {
                    vg_product_single_124_qa_delete_owned((int) $id, 'tc_events', $owner);
                } catch (Throwable $error) {
                    $failures[] = ['id' => (int) $id, 'message' => 'Event: ' . $error->getMessage()];
                }
            } else {
                $failures[] = ['id' => (int) $id, 'message' => 'Event retained because side-effect ownership is ambiguous.'];
            }
        }
    } elseif ($owned['events'] !== []) {
        $failures[] = ['id' => (int) reset($owned['events']), 'message' => 'Events retained because dependent QA records were not cleared.'];
    }
    return ['failures' => $failures, 'tickera' => $tickera];
}

function vg_product_single_124_qa_expected_state(array $snapshot): void
{
    vg_product_single_124_qa_check(
        $snapshot['product_ids'] === VG_PRODUCT_SINGLE_124_QA_EXPECTED_PRODUCT_IDS,
        'Existing Product ID set differs from the Task 1 preflight.'
    );
    $sandbox_php_files = array_values(array_filter(
        $snapshot['sandbox_directory_entries'],
        static fn (string $entry): bool => is_file(WP_CONTENT_DIR . '/novamira-sandbox/' . $entry)
            && str_ends_with(strtolower($entry), '.php')
    ));
    vg_product_single_124_qa_check($sandbox_php_files === [], 'A top-level PHP file is present in the Novamira sandbox; stop before QA requests.');
    vg_product_single_124_qa_check(
        $snapshot['product_fields'] === VG_PRODUCT_SINGLE_124_QA_EXPECTED_PRODUCT_FIELDS,
        'Existing Product People, source Event, legacy relationship, or _event_name fields differ from Task 1.'
    );
    foreach (VG_PRODUCT_SINGLE_124_QA_EXPECTED_RECORD_HASHES as $id => [$post_hash, $meta_hash]) {
        vg_product_single_124_qa_check(
            $snapshot['records'][$id] === ['post' => $post_hash, 'meta' => $meta_hash],
            'Protected runtime record ' . $id . ' differs from Task 1.'
        );
    }
    vg_product_single_124_qa_check(
        $snapshot['template124_projection_sha256'] === VG_PRODUCT_SINGLE_124_QA_TEMPLATE124_PROJECTION_SHA256,
        'Template 124 differs outside the approved Product People/source Event cutover targets.'
    );
    vg_product_single_124_qa_check(
        $snapshot['template124_core_sha256'] === VG_PRODUCT_SINGLE_124_QA_TEMPLATE124_CORE_SHA256
            && $snapshot['template124_meta_sha256'] === VG_PRODUCT_SINGLE_124_QA_TEMPLATE124_META_SHA256,
        'Template 124 non-content record/meta fields differ from the Task 1 snapshot.'
    );
    vg_product_single_124_qa_check(
        $snapshot['components_without_product_card_sha256'] === VG_PRODUCT_SINGLE_124_QA_COMPONENTS_SHA256,
        'Bricks components differ beyond the one approved Product Person Card.'
    );
    vg_product_single_124_qa_check(
        $snapshot['protected_components'] === VG_PRODUCT_SINGLE_124_QA_PROTECTED_COMPONENT_HASHES,
        'Protected components 6b463f or 194bba differ from Task 1.'
    );
    vg_product_single_124_qa_check($snapshot['person_count'] === VG_PRODUCT_SINGLE_124_QA_EXPECTED_PERSON_COUNT, 'Person count differs from Task 1 baseline.');
    vg_product_single_124_qa_check(
        $snapshot['participation_count'] === VG_PRODUCT_SINGLE_124_QA_EXPECTED_PARTICIPATION_COUNT,
        'Event Participation count differs from Task 1 baseline.'
    );
    vg_product_single_124_qa_check(
        array_map('intval', array_column($snapshot['api_keys'], 'id')) === VG_PRODUCT_SINGLE_124_QA_EXPECTED_API_KEY_IDS
            && hash('sha256', serialize($snapshot['api_keys'])) === VG_PRODUCT_SINGLE_124_QA_EXPECTED_API_KEYS_SHA256,
        'Tickera API key state differs from the exact Task 1 protected snapshot.'
    );
    vg_product_single_124_qa_check(
        $snapshot['helper_hash'] === VG_PRODUCT_SINGLE_124_QA_HELPER_HASH
            && $snapshot['copy_hash'] === VG_PRODUCT_SINGLE_124_QA_COPY_HASH,
        'Active helper or Product copy snippet hash differs from Task 1.'
    );
    vg_product_single_124_qa_check(
        ($snapshot['callers']['product_medewerkers'] ?? []) === []
            && ($snapshot['callers']['product_source_event'] ?? []) === []
            && ($snapshot['callers']['medewerker_products'] ?? []) === [['template' => 772, 'element' => 'vgms33']],
        'Fresh helper caller counts differ from the approved post-cutover boundary.'
    );
    vg_product_single_124_qa_check(
        ($snapshot['callers']['vg-product-people-query'] ?? []) === [['template' => 124, 'element' => 'vgpr03']]
            && ($snapshot['callers']['vg-product-source-event-query'] ?? []) === [['template' => 124, 'element' => 'vgpr13']]
            && ($snapshot['callers']['vg-product-people-section'] ?? []) === [['template' => 124, 'element' => 'vgpr00']],
        'Task 7B Template 124 canonical markers are missing, duplicated, or misplaced.'
    );
    vg_product_single_124_qa_check(
        $snapshot['template124_element_count'] === VG_PRODUCT_SINGLE_124_QA_TEMPLATE124_ELEMENTS,
        'Template 124 element count changed outside the approved targeted edits.'
    );
    vg_product_single_124_qa_check(
        $snapshot['template772_reverse_mode'] === 'medewerker_products',
        'Template 772 reverse Product behavior was not preserved.'
    );
    vg_product_single_124_qa_check(count($snapshot['product_person_cards']) === 1, 'Exactly one Product Person Card must exist.');
}

/** Snapshot protected state after the approved Template 124/card mutation. */
function vg_product_single_124_qa_snapshot(): array
{
    $records = [];
    foreach (array_keys(VG_PRODUCT_SINGLE_124_QA_EXPECTED_RECORD_HASHES) as $id) {
        $records[(int) $id] = vg_product_single_124_qa_record_hashes((int) $id);
    }
    $component_state = vg_product_single_124_qa_component_inventory();
    vg_product_single_124_qa_check(count($component_state['cards']) === 1, 'Exactly one Product Person Card is required for the Template 124 projection.');
    $product_person_card_id = (string) array_key_first($component_state['cards']);
    $template_projection = vg_product_single_124_qa_template_projection($product_person_card_id);
    $template_post = get_post(VG_PRODUCT_SINGLE_124_QA_TEMPLATE);
    vg_product_single_124_qa_check($template_post instanceof WP_Post, 'Template 124 post is missing.');
    $template_post_vars = get_object_vars($template_post);
    unset($template_post_vars['post_modified'], $template_post_vars['post_modified_gmt']);
    $template_meta = get_post_meta(VG_PRODUCT_SINGLE_124_QA_TEMPLATE);
    unset($template_meta['_bricks_page_content_2'], $template_meta['_edit_lock'], $template_meta['_edit_last']);
    ksort($template_meta);

    $components_without_card = $component_state['option'];
    foreach ($component_state['cards'] as $card) {
        unset($components_without_card[$card['key']]);
    }
    $template772 = get_post_meta(772, '_bricks_page_content_2', true);
    vg_product_single_124_qa_check(is_array($template772), 'Template 772 Bricks structure is unavailable.');
    $reverse_mode = null;
    foreach ($template772 as $element) {
        if (($element['id'] ?? null) === 'vgms33') {
            $reverse_mode = $element['settings']['query']['vg_query_type'] ?? null;
            break;
        }
    }
    $helper_path = WP_CONTENT_DIR . '/fluent-snippet-storage/10-vg-bricks-medewerker-queries.php';
    $copy_path = WP_CONTENT_DIR . '/fluent-snippet-storage/9-acf-product-copy.php';

    return [
        'records' => $records,
        'product_ids' => vg_product_single_124_qa_existing_product_ids(),
        'product_fields' => vg_product_single_124_qa_existing_product_fields(),
        'template124_projection_sha256' => hash('sha256', serialize($template_projection)),
        'template124_core_sha256' => hash('sha256', serialize($template_post_vars)),
        'template124_meta_sha256' => hash('sha256', serialize($template_meta)),
        'template124_element_count' => count(get_post_meta(VG_PRODUCT_SINGLE_124_QA_TEMPLATE, '_bricks_page_content_2', true)),
        'components_without_product_card_sha256' => vg_product_single_124_qa_stable_hash($components_without_card),
        'protected_components' => $component_state['protected'],
        'product_person_cards' => $component_state['cards'],
        'callers' => vg_product_single_124_qa_query_callers(),
        'template772_reverse_mode' => $reverse_mode,
        'helper_hash' => is_file($helper_path) ? hash_file('sha256', $helper_path) : null,
        'copy_hash' => is_file($copy_path) ? hash_file('sha256', $copy_path) : null,
        'person_count' => vg_product_single_124_qa_post_count('person'),
        'participation_count' => vg_product_single_124_qa_post_count(EventParticipationRepository::POST_TYPE),
        'woocommerce_coming_soon' => get_option('woocommerce_coming_soon'),
        'woocommerce_store_pages_only' => get_option('woocommerce_store_pages_only'),
        'sandbox_directory_entries' => vg_product_single_124_qa_sandbox_entries(WP_CONTENT_DIR . '/novamira-sandbox'),
        'api_keys' => vg_product_single_124_qa_api_key_rows(),
        'post_type_counts' => vg_product_single_124_qa_post_type_counts(),
    ];
}

/** @return list<string> */
function vg_product_single_124_qa_flatten_strings(mixed $value): array
{
    $strings = [];
    if (is_string($value)) {
        $strings[] = $value;
    } elseif (is_array($value)) {
        foreach ($value as $item) {
            $strings = [...$strings, ...vg_product_single_124_qa_flatten_strings($item)];
        }
    }
    return $strings;
}

/** @return array<string, mixed> */
function vg_product_single_124_qa_card(int $person_id): array
{
    $snapshot = vg_product_single_124_qa_component_inventory();
    vg_product_single_124_qa_check(count($snapshot['cards']) === 1, 'Product Person Card is missing or ambiguous.');
    $card_id = (string) array_key_first($snapshot['cards']);
    vg_product_single_124_qa_check(! in_array($card_id, VG_PRODUCT_SINGLE_124_QA_COMPONENTS, true), 'Product Person Card reused a protected component.');
    $component = $snapshot['cards'][$card_id]['entry'];
    $strings = vg_product_single_124_qa_flatten_strings($component);
    $serialized = implode("\n", $strings);
    foreach (['{featured_image}', '{post_title}', '{acf_person_professional_title}', '{post_excerpt}', '{post_url}', 'Bekyk profiel'] as $token) {
        vg_product_single_124_qa_check(str_contains($serialized, $token), 'Product Person Card is missing ' . $token . '.');
    }
    vg_product_single_124_qa_check(! preg_match('/medewerker_/i', $serialized), 'Product Person Card contains a legacy medewerker field.');
    vg_product_single_124_qa_check(! str_contains($serialized, 'event_participation'), 'Product Person Card uses Event Participation as its object.');
    $template_elements = get_post_meta(VG_PRODUCT_SINGLE_124_QA_TEMPLATE, '_bricks_page_content_2', true);
    $instances = [];
    foreach ($template_elements as $element) {
        if (
            ($element['parent'] ?? null) === 'vgpr03'
            && ($element['cid'] ?? null) === $card_id
        ) {
            $instances[] = $element;
        }
    }
    vg_product_single_124_qa_check(count($instances) === 1, 'Template 124 does not use Product Person Card directly as its loop child.');
    vg_product_single_124_qa_check(
        ! array_filter($template_elements, static fn (array $element): bool => ($element['parent'] ?? null) === 'vgpr03' && ($element['cid'] ?? null) === '6b463f'),
        'Template 124 still contains the legacy Medewerker Card under the Product People loop.'
    );
    vg_product_single_124_qa_check(get_post_type($person_id) === 'person', 'Product card QA context is not a canonical Person.');

    return [
        'id' => $card_id,
        'sha256' => $snapshot['cards'][$card_id]['sha256'],
        'instance_id' => (string) ($instances[0]['id'] ?? ''),
        'component' => $component,
    ];
}

function vg_product_single_124_qa_set_source_event(int $product_id, int|string $event_id): void
{
    $result = update_field('produk_bron_event', $event_id, $product_id);
    if ($result === false || (string) get_field('produk_bron_event', $product_id, false) !== (string) $event_id) {
        update_post_meta($product_id, 'produk_bron_event', $event_id);
        acf_flush_value_cache($product_id, 'produk_bron_event');
    }
    vg_product_single_124_qa_check(
        get_field('produk_bron_event', $product_id, false) === $event_id
            || (string) get_field('produk_bron_event', $product_id, false) === (string) $event_id,
        'Owned QA Product source Event did not read back through ACF.'
    );
}

function vg_product_single_124_qa_set_people(int $product_id, ?string $mode, array $people): void
{
    $state = $GLOBALS['vg_product_single_124_qa_people_state'] ?? null;
    vg_product_single_124_qa_check(is_array($state), 'Product People QA state is unavailable.');
    vg_product_people_qa_configure_product_people(
        $product_id,
        $mode,
        $people,
        (string) $state['owner'],
        $state['owned']['contributions'],
        static function (int $id, string $type, string $owner_marker): void {
            vg_product_single_124_qa_mark($id, $type, $owner_marker);
        },
        static function (int $id, string $type, string $owner_marker, ?callable $endpoint_check = null): void {
            vg_product_single_124_qa_delete_owned($id, $type, $owner_marker, $endpoint_check);
        },
    );
}

function vg_product_single_124_qa_people_query(ProductPeopleBricks $adapter, int $product_id): array
{
    \Bricks\Database::$page_data = ['preview_or_post_id' => $product_id];
    return $adapter->filter_people_query_vars(
        ['post_type' => 'post', 'post__in' => [999999999]],
        ['_cssClasses' => 'vg-product-people-query'],
        'vgpr03',
        'block'
    );
}

function vg_product_single_124_qa_source_query(ProductSourceEventBricks $adapter, int $product_id): array
{
    \Bricks\Database::$page_data = ['preview_or_post_id' => $product_id];
    return $adapter->filter_source_event_query_vars(
        ['post_type' => 'post', 'post__in' => [999999999]],
        ['_cssClasses' => 'vg-product-source-event-query'],
        'vgpr13',
        'block'
    );
}

function vg_product_single_124_qa_frontend(string $url): array
{
    $public_url = $url;
    $url = vg_product_single_124_qa_frontend_bypass_url(
        $url,
        home_url('/'),
        (string) getenv('VG_TASK_7B_FRONTEND_BYPASS_TOKEN')
    );
    $authenticated_identity = $GLOBALS['vg_product_single_124_qa_authenticated_identity'] ?? null;
    vg_product_single_124_qa_check(
        is_array($authenticated_identity)
            && ($authenticated_identity['user_id'] ?? null) === get_current_user_id()
            && ($authenticated_identity['manage_woocommerce'] ?? null) === true,
        'The frontend QA request identity was not verified before fixture creation.'
    );
    $request_args = vg_product_single_124_qa_frontend_request_args(
        $url,
        home_url('/'),
        (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''),
        get_current_user_id() > 0 && current_user_can('manage_woocommerce')
    );
    $sandbox_baseline = $GLOBALS['vg_product_single_124_qa_sandbox_baseline'] ?? null;
    vg_product_single_124_qa_check(is_array($sandbox_baseline), 'The sandbox listing baseline is unavailable.');
    $sandbox_listing = vg_product_single_124_qa_assert_sandbox_unchanged(
        WP_CONTENT_DIR . '/novamira-sandbox',
        $sandbox_baseline
    );
    $response = wp_remote_get($url, $request_args);
    vg_product_single_124_qa_check(! is_wp_error($response), 'Frontend request failed: ' . (is_wp_error($response) ? $response->get_error_message() : 'unknown'));
    $status = (int) wp_remote_retrieve_response_code($response);
    $body = (string) wp_remote_retrieve_body($response);
    vg_product_single_124_qa_check($status === 200, 'Frontend URL returned HTTP ' . $status . ': ' . $url);
    return [
        'status' => $status,
        'body' => $body,
        'url' => $public_url,
        'request_path' => (string) (parse_url($url, PHP_URL_PATH) ?: ''),
        'request_actor_id' => get_current_user_id(),
        'coming_soon_bypass' => 'woocommerce_coming_soon_exclude_task_7b_request_token',
        'redirects_followed' => 0,
        'sandbox_directory_entries_before_request' => $sandbox_listing,
    ];
}

/** Verify the forwarded Basic credential resolves to this exact privileged QA actor. */
function vg_product_single_124_qa_frontend_identity(int $expected_user_id): array
{
    $request_args = vg_product_single_124_qa_frontend_request_args(
        rest_url('wp/v2/users/me?context=edit'),
        home_url('/'),
        (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''),
        $expected_user_id > 0 && current_user_can('manage_woocommerce')
    );
    $sandbox_baseline = $GLOBALS['vg_product_single_124_qa_sandbox_baseline'] ?? null;
    vg_product_single_124_qa_check(is_array($sandbox_baseline), 'The sandbox listing baseline is unavailable for identity verification.');
    $sandbox_listing = vg_product_single_124_qa_assert_sandbox_unchanged(
        WP_CONTENT_DIR . '/novamira-sandbox',
        $sandbox_baseline
    );
    $response = wp_remote_get(rest_url('wp/v2/users/me?context=edit'), $request_args);
    vg_product_single_124_qa_check(! is_wp_error($response), 'Authenticated identity request failed: ' . (is_wp_error($response) ? $response->get_error_message() : 'unknown'));
    $status = (int) wp_remote_retrieve_response_code($response);
    $payload = json_decode((string) wp_remote_retrieve_body($response), true);
    vg_product_single_124_qa_check(is_array($payload), 'Authenticated identity response was not a JSON object.');
    $identity = vg_product_single_124_qa_assert_identity_payload($expected_user_id, $status, $payload);

    return $identity + [
        'http_status' => $status,
        'auth_scheme' => 'Basic',
        'sandbox_directory_entries_before_request' => $sandbox_listing,
    ];
}

/** Run a separate loaded-file process without ACF functions to prove both adapters fail closed. */
function vg_product_single_124_qa_no_acf_process(): array
{
    vg_product_single_124_qa_check(function_exists('exec'), 'PHP exec is unavailable for the isolated no-ACF contract check.');
    $plugin_dir = WP_PLUGIN_DIR . '/voelgoed-platform-core';
    $files = [
        $plugin_dir . '/includes/Event/EventParticipationRepository.php',
        $plugin_dir . '/includes/Product/ProductRoleVocabulary.php',
        $plugin_dir . '/includes/Product/ProductPeopleFields.php',
        $plugin_dir . '/includes/Product/ProductContributionRepository.php',
        $plugin_dir . '/includes/Product/ProductPeopleResolver.php',
        $plugin_dir . '/includes/Product/ProductPeopleBricks.php',
        $plugin_dir . '/includes/Product/ProductSourceEventBricks.php',
    ];
    $require = implode('', array_map(static fn (string $file): string => 'require_once ' . var_export($file, true) . ';', $files));
    $code = "define('ABSPATH','/');"
        . "function get_the_ID(){return 401;}"
        . "function get_post_type(\$id=null){return (int)\$id===401?'product':false;}"
        . "function get_post_meta(\$id,\$key,\$single=false){return \$key==='product_people_mode'?'event':'';}"
        . "function get_post(\$id){return false;}"
        . $require
        . "\$people=(new Voelgoed\\PlatformCore\\Product\\ProductPeopleBricks())->filter_people_query_vars([],['_cssClasses'=>'vg-product-people-query'],'loop','block');"
        . "\$event=(new Voelgoed\\PlatformCore\\Product\\ProductSourceEventBricks())->filter_source_event_query_vars([],['_cssClasses'=>'vg-product-source-event-query'],'loop','block');"
        . "echo json_encode(['people'=>\$people,'event'=>\$event],JSON_THROW_ON_ERROR);";
    $output = [];
    $exit = 0;
    exec(escapeshellarg(vg_product_single_124_qa_php_cli_binary()) . ' -r ' . escapeshellarg($code) . ' 2>&1', $output, $exit);
    vg_product_single_124_qa_check($exit === 0, 'Isolated no-ACF process failed: ' . implode("\n", $output));
    $result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    vg_product_single_124_qa_check(
        ($result['people']['post_type'] ?? null) === 'person'
            && ($result['people']['post__in'] ?? null) === [0]
            && ($result['event']['post_type'] ?? null) === 'tc_events'
            && ($result['event']['post__in'] ?? null) === [0],
        'Either adapter did not fail closed when ACF was unavailable.'
    );
    return $result;
}

/** Isolated adapter process with an explicit Bricks builder or builder-call context. */
function vg_product_single_124_qa_builder_process(string $context): array
{
    vg_product_single_124_qa_check(function_exists('exec'), 'PHP exec is unavailable for the isolated builder-context check.');
    vg_product_single_124_qa_check(in_array($context, ['builder', 'builder_call'], true), 'Unknown builder context.');
    $plugin_dir = WP_PLUGIN_DIR . '/voelgoed-platform-core';
    $files = [
        $plugin_dir . '/includes/Event/EventParticipationRepository.php',
        $plugin_dir . '/includes/Product/ProductRoleVocabulary.php',
        $plugin_dir . '/includes/Product/ProductPeopleFields.php',
        $plugin_dir . '/includes/Product/ProductContributionRepository.php',
        $plugin_dir . '/includes/Product/ProductPeopleResolver.php',
        $plugin_dir . '/includes/Product/ProductPeopleBricks.php',
    ];
    $require = implode('', array_map(static fn (string $file): string => 'require_once ' . var_export($file, true) . ';', $files));
    $code = "define('ABSPATH','/');"
        . "function get_the_ID(){return 401;}"
        . "function get_post_type(\$id=null){return (int)\$id===401?'product':false;}"
        . "function get_post_meta(\$id,\$key,\$single=false){return \$key==='product_people_mode'?'manual':'';}"
        . "function get_post(\$id){return false;}"
        . "function is_singular(){return false;}"
        . "function get_queried_object(){return null;}"
        . "function bricks_is_builder(){return " . ($context === 'builder' ? 'true' : 'false') . ";}"
        . "function bricks_is_builder_call(){return " . ($context === 'builder_call' ? 'true' : 'false') . ";}"
        . $require
        . "\$result=(new Voelgoed\\PlatformCore\\Product\\ProductPeopleBricks())->filter_people_section_render(true,(object)['settings'=>['_cssClasses'=>'vg-product-people-section']]);"
        . "echo json_encode(['render'=>\$result],JSON_THROW_ON_ERROR);";
    $output = [];
    $exit = 0;
    exec(escapeshellarg(vg_product_single_124_qa_php_cli_binary()) . ' -r ' . escapeshellarg($code) . ' 2>&1', $output, $exit);
    vg_product_single_124_qa_check($exit === 0, 'Isolated builder-context process failed: ' . implode("\n", $output));
    $result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    vg_product_single_124_qa_check(($result['render'] ?? false) === true, 'Product People section was hidden in ' . $context . ' context.');
    return $result;
}

function vg_product_single_124_qa_create_person(string $owner, string $key, string $title, string $professional_title, string $excerpt, string $status, int $image_id, array &$owned_persons): int
{
    $id = wp_insert_post([
        'post_type' => 'person',
        'post_status' => $status,
        'post_title' => $owner . ' ' . $title,
        'post_excerpt' => $excerpt,
    ], true);
    vg_product_single_124_qa_check(! is_wp_error($id) && (int) $id > 0, 'Could not create QA Person ' . $key . '.');
    $id = (int) $id;
    $owned_persons[] = $id;
    vg_product_single_124_qa_mark($id, 'person', $owner);
    if ($status === 'publish') {
        vg_product_single_124_qa_check(update_field('person_professional_title', $professional_title, $id) !== false, 'Could not write canonical Person professional title.');
        vg_product_single_124_qa_check($image_id > 0 && set_post_thumbnail($id, $image_id), 'Could not assign the read-only existing image fixture to the QA Person.');
    }
    return $id;
}

function vg_product_single_124_qa_create_product(string $owner, int &$owned_product): int
{
    vg_product_single_124_qa_check(class_exists('WC_Product_Simple'), 'WooCommerce simple Product API is unavailable.');
    $product = new WC_Product_Simple();
    $product->set_name($owner . ' Product');
    $product->set_status('publish');
    $product->set_regular_price('1.00');
    $product->set_catalog_visibility('hidden');
    $id = (int) $product->save();
    $owned_product = $id;
    vg_product_single_124_qa_check($id > 0 && get_post_type($id) === 'product', 'Could not create a frontend-capable temporary Product.');
    vg_product_single_124_qa_mark($id, 'product', $owner);
    return $id;
}

function vg_product_single_124_qa_assert_card_html(array $response, array $person, int $image_id): void
{
    $url = get_permalink($person['id']);
    $image_urls = array_filter([
        wp_get_attachment_url($image_id),
        wp_get_attachment_image_url($image_id, 'full'),
        wp_get_attachment_image_url($image_id, 'large'),
        wp_get_attachment_image_url($image_id, 'medium'),
        wp_get_attachment_image_url($image_id, 'thumbnail'),
    ], 'is_string');
    $cards = vg_product_single_124_qa_person_card_values((string) $response['body']);
    $matching_cards = array_values(array_filter(
        $cards,
        static fn (array $card): bool => $card['title'] === $person['title']
    ));
    vg_product_single_124_qa_check(count($matching_cards) === 1, 'Rendered Product Person Card does not contain exactly one card for canonical Person ' . $person['id'] . '.');
    $card = $matching_cards[0];
    vg_product_single_124_qa_check($card['professional_title'] === $person['professional_title'], 'Rendered Product Person Card has the wrong canonical professional title.');
    vg_product_single_124_qa_check($card['excerpt'] === $person['excerpt'], 'Rendered Product Person Card has the wrong canonical Person excerpt.');
    vg_product_single_124_qa_check(
        $card['profile_url'] === $url,
        'Rendered Product Person Card permalink mismatch; expected ' . $url . ', rendered ' . ($card['profile_url'] !== '' ? $card['profile_url'] : '[empty href]') . '.'
    );
    vg_product_single_124_qa_check(
        $image_urls !== [] && count(array_intersect($image_urls, $card['image_urls'])) > 0,
        'Rendered Product Person Card does not use the current Person featured image.'
    );
    vg_product_single_124_qa_check($card['cta'] === 'Bekyk profiel', 'Rendered Product Person Card is missing the Product-page profile action label.');
}

function vg_product_single_124_qa_marked_section_renders(bool $render, ProductPeopleBricks $adapter): bool
{
    return $adapter->filter_people_section_render($render, (object) [
        'settings' => ['_cssClasses' => 'vg-product-people-section'],
    ]);
}

$token = (string) getenv('VG_TASK_7B_OWNER_TOKEN');
$owner = VG_PRODUCT_SINGLE_124_QA_OWNER_LABEL . ' ' . $token;
$preflight_only = getenv('VG_TASK_7B_PREFLIGHT_ONLY') === '1';
$post_cutover = getenv('VG_TASK_7B_POST_CUTOVER') === '1';
$frontend_bypass_token = (string) getenv('VG_TASK_7B_FRONTEND_BYPASS_TOKEN');
$before = null;
$frontend_identity = null;
$preflight_failure = null;
try {
    vg_product_single_124_qa_check($preflight_only || $post_cutover, 'Refusing to run without VG_TASK_7B_POST_CUTOVER=1.');
    vg_product_single_124_qa_check(
        preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{7,127}$/', $token) === 1,
        'VG_TASK_7B_OWNER_TOKEN must be a unique 8–128 character alphanumeric/dot/underscore/hyphen token.'
    );
    if ($post_cutover) {
        vg_product_single_124_qa_check(
            preg_match('/\A[a-f0-9]{64}\z/', $frontend_bypass_token) === 1,
            'VG_TASK_7B_FRONTEND_BYPASS_TOKEN must be a 64-character hexadecimal request-scoped token.'
        );
    }
    vg_product_single_124_qa_check(! vg_product_single_124_qa_token_exists($token), 'The run token already exists in durable WordPress state.');
    foreach ([
        ProductPeopleFields::class,
        ProductPeopleResolver::class,
        ProductPeopleBricks::class,
        ProductSourceEventBricks::class,
        EventParticipationRepository::class,
        EventParticipationService::class,
        \Voelgoed\PlatformCore\Product\ProductRoleVocabulary::class,
        \Voelgoed\PlatformCore\Product\ProductContributionRepository::class,
        \Voelgoed\PlatformCore\Product\ProductContributionService::class,
    ] as $class) {
        vg_product_single_124_qa_check(class_exists($class), 'Required Platform Core class is not loaded: ' . $class);
    }
    vg_product_people_qa_assert_approved_product_role_vocabulary();
    foreach (['product', 'tc_events', 'person', EventParticipationRepository::POST_TYPE, 'product_contribution'] as $post_type) {
        vg_product_single_124_qa_check(post_type_exists($post_type), 'Required post type is not registered: ' . $post_type);
    }
    vg_product_single_124_qa_check(function_exists('get_field') && function_exists('update_field'), 'ACF runtime is unavailable.');
    vg_product_single_124_qa_check(class_exists('Bricks\\Database'), 'Bricks database context is unavailable.');
    $before = vg_product_single_124_qa_snapshot();
    vg_product_single_124_qa_expected_state($before);
    $GLOBALS['vg_product_single_124_qa_sandbox_baseline'] = $before['sandbox_directory_entries'];
    if ($post_cutover) {
        $frontend_identity = vg_product_single_124_qa_frontend_identity(get_current_user_id());
        $GLOBALS['vg_product_single_124_qa_authenticated_identity'] = $frontend_identity;
    }
} catch (Throwable $error) {
    $preflight_failure = $error;
}

if ($preflight_failure instanceof Throwable) {
    $payload = json_encode([
        'result' => 'FAIL',
        'phase' => 'preflight',
        'mutation_started' => false,
        'run_token' => $token,
        'owner_marker' => $owner,
        'failures' => [['message' => $preflight_failure->getMessage()]],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (defined('WP_CLI') && WP_CLI) {
        \WP_CLI::line($payload);
        \WP_CLI::error('Task 7B Product Single 124 QA preflight failed.', 1);
    }
    echo $payload . PHP_EOL;
    return;
}

if ($preflight_only) {
    $payload = json_encode([
        'result' => 'PASS',
        'task' => '7B',
        'phase' => 'preflight',
        'mutation_started' => false,
        'run_token' => $token,
        'owner_marker' => $owner,
        'snapshot' => $before,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (defined('WP_CLI') && WP_CLI) {
        \WP_CLI::line($payload);
    } else {
        echo $payload . PHP_EOL;
    }
    return;
}

$pre_api_ids = array_map('intval', array_column($before['api_keys'], 'id'));
$owned = ['product' => 0, 'events' => [], 'persons' => [], 'participations' => [], 'contributions' => []];
$GLOBALS['vg_product_single_124_qa_people_state'] = ['owner' => $owner, 'owned' => &$owned];
$scenarios = [];
$qa_failure = null;
$cleanup = ['failures' => [], 'tickera' => ['deleted' => [], 'unexplained' => [], 'failures' => []]];
$card_evidence = null;
$frontend_evidence = [];
$no_acf_evidence = null;
$qa_event_name_before = null;
$qa_event_name_after = null;

$run = static function (string $id, callable $assertion) use (&$scenarios): void {
    try {
        $result = $assertion();
        $scenarios[] = ['id' => $id, 'result' => 'PASS', 'evidence' => $result];
    } catch (Throwable $error) {
        $scenarios[] = ['id' => $id, 'result' => 'FAIL', 'message' => $error->getMessage()];
        throw $error;
    }
};

$people_bricks = new ProductPeopleBricks();
$source_bricks = new ProductSourceEventBricks();
$resolver = new ProductPeopleResolver();

try {
    $event_id = wp_insert_post([
        'post_type' => 'tc_events',
        'post_status' => 'publish',
        'post_title' => $owner . ' Event',
    ], true);
    vg_product_single_124_qa_check(! is_wp_error($event_id) && (int) $event_id > 0, 'Could not create owned QA source Event.');
    $event_id = (int) $event_id;
    $owned['events'][] = $event_id;
    vg_product_single_124_qa_mark($event_id, 'tc_events', $owner);

    $attachments = get_posts([
        'post_type' => 'attachment',
        'post_mime_type' => 'image',
        'post_status' => 'inherit',
        'numberposts' => 4,
        'orderby' => 'ID',
        'order' => 'ASC',
        'fields' => 'ids',
    ]);
    vg_product_single_124_qa_check(is_array($attachments) && count($attachments) >= 4, 'Four existing read-only image fixtures are required.');
    $images = array_map('intval', array_slice($attachments, 0, 4));
    foreach ($images as $attachment_id) {
        vg_product_single_124_qa_check(
            $attachment_id > 0 && get_post_type($attachment_id) === 'attachment' && wp_attachment_is_image($attachment_id),
            'A selected read-only image fixture is invalid.'
        );
    }

    $person_a = vg_product_single_124_qa_create_person($owner, 'A', 'Task 7B Person A', 'QA Title A', 'QA excerpt A', 'publish', $images[0], $owned['persons']);
    $person_b = vg_product_single_124_qa_create_person($owner, 'B', 'Task 7B Person B', 'QA Title B', 'QA excerpt B', 'publish', $images[1], $owned['persons']);
    $person_c = vg_product_single_124_qa_create_person($owner, 'C', 'Task 7B Person C', 'QA Title C', 'QA excerpt C', 'publish', $images[2], $owned['persons']);
    $person_legacy = vg_product_single_124_qa_create_person($owner, 'LEGACY', 'Task 7B Legacy-only Person', 'QA Legacy Title', 'QA legacy excerpt', 'publish', $images[3], $owned['persons']);
    $person_draft = vg_product_single_124_qa_create_person($owner, 'DRAFT', 'Task 7B Draft Person', '', '', 'draft', 0, $owned['persons']);

    $product_id = vg_product_single_124_qa_create_product($owner, $owned['product']);
    $qa_event_name_before = get_post_meta($product_id, '_event_name', false);

    $participation_service = new EventParticipationService();
    foreach ([[$person_b, 10], [$person_draft, 15], [$person_a, 20], [$person_c, 30]] as [$person_id, $order]) {
        $participation_id = $participation_service->create($event_id, $person_id, [], $order, 'publish');
        vg_product_single_124_qa_check(! is_wp_error($participation_id) && (int) $participation_id > 0, 'Could not create ordered QA Event Participation.');
        $participation_id = (int) $participation_id;
        $owned['participations'][$participation_id] = ['event_id' => $event_id, 'person_id' => $person_id];
        vg_product_single_124_qa_mark($participation_id, EventParticipationRepository::POST_TYPE, $owner);
    }
    vg_product_single_124_qa_set_source_event($product_id, $event_id);

    $people_by_id = [
        $person_a => ['id' => $person_a, 'title' => $owner . ' Task 7B Person A', 'professional_title' => 'QA Title A', 'excerpt' => 'QA excerpt A', 'image_id' => $images[0]],
        $person_b => ['id' => $person_b, 'title' => $owner . ' Task 7B Person B', 'professional_title' => 'QA Title B', 'excerpt' => 'QA excerpt B', 'image_id' => $images[1]],
        $person_c => ['id' => $person_c, 'title' => $owner . ' Task 7B Person C', 'professional_title' => 'QA Title C', 'excerpt' => 'QA excerpt C', 'image_id' => $images[2]],
        $person_legacy => ['id' => $person_legacy, 'title' => $owner . ' Task 7B Legacy-only Person', 'professional_title' => 'QA Legacy Title', 'excerpt' => 'QA legacy excerpt', 'image_id' => $images[3]],
    ];

    $run('unconfigured_returns_empty', static function () use ($product_id, $resolver): array {
        vg_product_single_124_qa_set_people($product_id, null, []);
        vg_product_single_124_qa_check($resolver->people_for_product($product_id) === [], 'Unconfigured Product returned canonical People.');
        return ['ids' => []];
    });
    $run('unconfigured_section_hidden_frontend', static function () use ($product_id, $people_bricks, &$frontend_evidence): array {
        vg_product_single_124_qa_set_people($product_id, null, []);
        vg_product_single_124_qa_check(! vg_product_single_124_qa_marked_section_renders(true, $people_bricks), 'Unconfigured Product People section must be hidden on frontend.');
        $response = vg_product_single_124_qa_frontend(get_permalink($product_id));
        vg_product_single_124_qa_check(! vg_product_single_124_qa_html_has_class($response['body'], 'vg-product-people-section'), 'Unconfigured Product People section leaked into frontend HTML.');
        $frontend_evidence['unconfigured_product'] = ['status' => $response['status'], 'url' => $response['url']];
        return ['render' => false, 'frontend_status' => $response['status']];
    });
    $run('event_mode_preserves_event_participation_order', static function () use ($product_id, $resolver, $person_b, $person_a, $person_c): array {
        vg_product_single_124_qa_set_people($product_id, 'event', []);
        vg_product_single_124_qa_check($resolver->people_for_product($product_id) === [$person_b, $person_a, $person_c], 'Event-derived People order differs from participation display order.');
        return ['ids' => [$person_b, $person_a, $person_c]];
    });
    $run('event_mode_excludes_unpublished_person', static function () use ($product_id, $resolver, $person_draft): array {
        vg_product_single_124_qa_set_people($product_id, 'event', []);
        vg_product_single_124_qa_check(! in_array($person_draft, $resolver->people_for_product($product_id), true), 'Event mode included an unpublished Person.');
        return ['excluded_person_id' => $person_draft];
    });
    $run('event_mode_ignores_manual_override', static function () use ($product_id, $resolver, $person_b, $person_a, $person_c): array {
        vg_product_single_124_qa_set_people($product_id, 'event', [$person_c, $person_a]);
        vg_product_single_124_qa_check($resolver->people_for_product($product_id) === [$person_b, $person_a, $person_c], 'Event mode used the stale manual override.');
        return ['ids' => [$person_b, $person_a, $person_c]];
    });
    $run('manual_mode_preserves_explicit_order', static function () use ($product_id, $resolver, $person_c, $person_a): array {
        vg_product_single_124_qa_set_people($product_id, 'manual', [$person_c, $person_a]);
        vg_product_single_124_qa_check($resolver->people_for_product($product_id) === [$person_c, $person_a], 'Manual mode did not preserve the explicit ACF relationship order.');
        return ['ids' => [$person_c, $person_a]];
    });
    $run('manual_mode_deduplicates_first_occurrence', static function () use ($product_id, $resolver, $person_c, $person_a, $person_b): array {
        vg_product_single_124_qa_set_people($product_id, 'manual', [$person_c, $person_a, $person_c, $person_b, $person_a]);
        vg_product_single_124_qa_check($resolver->people_for_product($product_id) === [$person_c, $person_a, $person_b], 'Manual mode did not keep first occurrence while removing duplicates.');
        return ['ids' => [$person_c, $person_a, $person_b]];
    });
    $run('manual_empty_returns_empty', static function () use ($product_id, $resolver): array {
        vg_product_single_124_qa_set_people($product_id, 'manual', []);
        vg_product_single_124_qa_check($resolver->people_for_product($product_id) === [], 'Empty manual mode returned People.');
        return ['ids' => []];
    });
    $run('manual_empty_section_hidden_frontend', static function () use ($product_id, $people_bricks, &$frontend_evidence): array {
        vg_product_single_124_qa_set_people($product_id, 'manual', []);
        vg_product_single_124_qa_check(! vg_product_single_124_qa_marked_section_renders(true, $people_bricks), 'Empty manual Product People section must be hidden on frontend.');
        $response = vg_product_single_124_qa_frontend(get_permalink($product_id));
        vg_product_single_124_qa_check(! vg_product_single_124_qa_html_has_class($response['body'], 'vg-product-people-section'), 'Empty manual Product People section leaked into frontend HTML.');
        $frontend_evidence['manual_empty_product'] = ['status' => $response['status'], 'url' => $response['url']];
        return ['render' => false, 'frontend_status' => $response['status']];
    });
    $run('invalid_mode_returns_empty', static function () use ($product_id, $resolver): array {
        update_post_meta($product_id, ProductPeopleFields::MODE_FIELD, 'invalid-task-7b-mode');
        acf_flush_value_cache($product_id, ProductPeopleFields::MODE_FIELD);
        vg_product_single_124_qa_check($resolver->people_for_product($product_id) === [], 'Invalid Product People mode did not fail closed.');
        return ['ids' => [], 'stored_mode' => 'invalid-task-7b-mode'];
    });
    $run('legacy_produk_medewerkers_does_not_affect_output', static function () use ($product_id, $resolver, $person_legacy): array {
        update_post_meta($product_id, 'produk_medewerkers', [$person_legacy]);
        acf_flush_value_cache($product_id, 'produk_medewerkers');
        vg_product_single_124_qa_set_people($product_id, 'manual', []);
        vg_product_single_124_qa_check($resolver->people_for_product($product_id) === [], 'Legacy produk_medewerkers influenced canonical output.');
        return ['legacy_person_id' => $person_legacy, 'ids' => []];
    });
    $run('empty_result_query_uses_post_in_zero', static function () use ($product_id, $people_bricks, $resolver): array {
        vg_product_single_124_qa_set_people($product_id, 'manual', []);
        vg_product_single_124_qa_check($resolver->people_for_product($product_id) === [], 'Precondition expected an empty canonical result.');
        $query = vg_product_single_124_qa_people_query($people_bricks, $product_id);
        vg_product_single_124_qa_check(
            $query === ['post_type' => 'person', 'post_status' => 'publish', 'post__in' => [0], 'orderby' => 'post__in', 'posts_per_page' => -1],
            'Empty canonical query did not use exactly post__in [0].'
        );
        $wp_query = new WP_Query($query);
        vg_product_single_124_qa_check($wp_query->posts === [], 'An empty canonical query leaked arbitrary Persons.');
        return ['query' => $query, 'result_count' => count($wp_query->posts)];
    });

    $run('event_mode_empty_section_hidden_frontend', static function () use ($product_id, $event_id, $owned, $resolver, $people_bricks, &$frontend_evidence): array {
        vg_product_single_124_qa_set_people($product_id, 'event', []);
        vg_product_single_124_qa_set_source_event($product_id, $event_id);
        $participation_statuses = [];
        foreach (array_keys($owned['participations']) as $participation_id) {
            $participation_statuses[$participation_id] = (string) get_post_status((int) $participation_id);
        }
        try {
            foreach ($participation_statuses as $participation_id => $status) {
                $updated = wp_update_post(['ID' => (int) $participation_id, 'post_status' => 'draft'], true);
                vg_product_single_124_qa_check(! is_wp_error($updated) && (int) $updated === (int) $participation_id, 'Could not temporarily hide an owned Event Participation fixture.');
            }
            vg_product_single_124_qa_check($resolver->people_for_product($product_id) === [], 'Event mode returned people without published Event Participation records.');
            vg_product_single_124_qa_check(! vg_product_single_124_qa_marked_section_renders(true, $people_bricks), 'Empty event-derived Product People section must be hidden on frontend.');
            $response = vg_product_single_124_qa_frontend(get_permalink($product_id));
            vg_product_single_124_qa_check(! vg_product_single_124_qa_html_has_class($response['body'], 'vg-product-people-section'), 'Empty event-derived Product People section leaked into frontend HTML.');
            $source_query = vg_product_single_124_qa_source_query(new ProductSourceEventBricks(), $product_id);
            $source_title = get_the_title($event_id);
            $event_card_texts = vg_product_single_124_qa_event_card_texts($response['body']);
            $source_title_rendered = count(array_filter($event_card_texts, static fn (string $text): bool => str_contains($text, $source_title))) > 0;
            $source_posts = (new WP_Query($source_query))->posts;
            vg_product_single_124_qa_check(
                $source_title_rendered,
                'The source Event stopped rendering when event-derived Product People were empty. ' . json_encode([
                    'event_id' => $event_id,
                    'event_title' => $source_title,
                    'source_query' => $source_query,
                    'source_query_result_ids' => wp_list_pluck($source_posts, 'ID'),
                    'source_event_meta' => get_post_meta($product_id, 'produk_bron_event', true),
                    'source_event_field' => get_field('produk_bron_event', $product_id, false),
                    'url' => $response['url'],
                    'source_marker_present' => vg_product_single_124_qa_html_has_class($response['body'], 'vg-product-source-event-query'),
                    'source_element_id_present' => vg_product_single_124_qa_html_has_class($response['body'], 'brxe-vgpr13'),
                    'event_card_texts' => $event_card_texts,
                    'response_excerpt' => substr(strip_tags($response['body']), 0, 900),
                ], JSON_UNESCAPED_SLASHES)
            );
            $frontend_evidence['event_empty_product'] = ['status' => $response['status'], 'url' => $response['url'], 'source_event_title' => get_the_title($event_id)];
            return ['people_ids' => [], 'source_event_id' => $event_id, 'frontend_status' => $response['status']];
        } finally {
            foreach ($participation_statuses as $participation_id => $status) {
                $updated = wp_update_post(['ID' => (int) $participation_id, 'post_status' => $status], true);
                vg_product_single_124_qa_check(! is_wp_error($updated) && (int) $updated === (int) $participation_id && get_post_status((int) $participation_id) === $status, 'Could not restore an owned Event Participation status after empty-event QA.');
            }
        }
    });

    $run('builder_keeps_empty_section_editable', static function () use ($product_id): array {
        vg_product_single_124_qa_set_people($product_id, 'manual', []);
        $result = vg_product_single_124_qa_builder_process('builder');
        return ['render' => $result['render'], 'context' => 'bricks_is_builder'];
    });
    $run('builder_call_keeps_empty_section_editable', static function () use ($product_id): array {
        vg_product_single_124_qa_set_people($product_id, 'manual', []);
        $result = vg_product_single_124_qa_builder_process('builder_call');
        return ['render' => $result['render'], 'context' => 'bricks_is_builder_call'];
    });

    $run('valid_source_event_returns_exact_event', static function () use ($product_id, $event_id, $source_bricks): array {
        vg_product_single_124_qa_set_source_event($product_id, $event_id);
        $query = vg_product_single_124_qa_source_query($source_bricks, $product_id);
        vg_product_single_124_qa_check(
            $query === ['post_type' => 'tc_events', 'post__in' => [$event_id], 'orderby' => 'post__in', 'posts_per_page' => 1],
            'Source Event adapter did not return exactly the ACF Event.'
        );
        return ['query' => $query];
    });
    $run('source_event_renders_while_people_empty', static function () use ($product_id, $event_id, $resolver, $source_bricks, &$frontend_evidence): array {
        vg_product_single_124_qa_set_people($product_id, null, []);
        vg_product_single_124_qa_set_source_event($product_id, $event_id);
        vg_product_single_124_qa_check($resolver->people_for_product($product_id) === [], 'Product People should be empty for source Event independence.');
        $query = vg_product_single_124_qa_source_query($source_bricks, $product_id);
        vg_product_single_124_qa_check(($query['post__in'] ?? null) === [$event_id], 'Source Event disappeared while Product People were empty.');
        $response = vg_product_single_124_qa_frontend(get_permalink($product_id));
        $owner_event_title = get_the_title($event_id);
        $event_card_texts = vg_product_single_124_qa_event_card_texts($response['body']);
        vg_product_single_124_qa_check(
            count(array_filter($event_card_texts, static fn (string $text): bool => str_contains($text, $owner_event_title))) > 0,
            'Source Event Card did not render while Product People were empty.'
        );
        vg_product_single_124_qa_check(! vg_product_single_124_qa_html_has_class($response['body'], 'vg-product-people-section'), 'Empty Product People section leaked into frontend HTML.');
        $frontend_evidence['empty_people_source_event'] = ['status' => $response['status'], 'url' => $response['url']];
        return ['query' => $query, 'event_title' => $owner_event_title, 'people_ids' => []];
    });
    $run('manual_mode_does_not_change_source_event', static function () use ($product_id, $event_id, $source_bricks): array {
        vg_product_single_124_qa_set_people($product_id, 'manual', []);
        vg_product_single_124_qa_set_source_event($product_id, $event_id);
        $query = vg_product_single_124_qa_source_query($source_bricks, $product_id);
        vg_product_single_124_qa_check(($query['post__in'] ?? null) === [$event_id], 'Manual Product People mode changed the independent source Event.');
        return ['event_id' => $event_id];
    });
    $run('empty_source_event_returns_empty', static function () use ($product_id, $source_bricks): array {
        delete_field('produk_bron_event', $product_id);
        acf_flush_value_cache($product_id, 'produk_bron_event');
        $query = vg_product_single_124_qa_source_query($source_bricks, $product_id);
        vg_product_single_124_qa_check(($query['post__in'] ?? null) === [0], 'Empty source Event did not fail closed.');
        return ['query' => $query];
    });
    $run('malformed_array_source_event_returns_empty', static function () use ($product_id, $event_id, $source_bricks): array {
        update_post_meta($product_id, 'produk_bron_event', [$event_id]);
        acf_flush_value_cache($product_id, 'produk_bron_event');
        $query = vg_product_single_124_qa_source_query($source_bricks, $product_id);
        vg_product_single_124_qa_check(($query['post__in'] ?? null) === [0], 'One-element array source Event did not fail closed.');
        return ['query' => $query];
    });
    $run('malformed_multi_source_event_returns_empty', static function () use ($product_id, $event_id, $source_bricks): array {
        update_post_meta($product_id, 'produk_bron_event', [$event_id, $event_id + 1]);
        acf_flush_value_cache($product_id, 'produk_bron_event');
        $query = vg_product_single_124_qa_source_query($source_bricks, $product_id);
        vg_product_single_124_qa_check(($query['post__in'] ?? null) === [0], 'Multi-valued source Event did not fail closed.');
        return ['query' => $query];
    });
    $run('malformed_scalar_source_event_returns_empty', static function () use ($product_id, $event_id, $source_bricks): array {
        $values = [0, -1, ' 763', '763 ', (object) ['ID' => $event_id]];
        foreach ($values as $value) {
            update_post_meta($product_id, 'produk_bron_event', $value);
            acf_flush_value_cache($product_id, 'produk_bron_event');
            $query = vg_product_single_124_qa_source_query($source_bricks, $product_id);
            vg_product_single_124_qa_check(($query['post__in'] ?? null) === [0], 'Malformed scalar source Event value did not fail closed.');
        }
        return ['rejected_values' => ['zero', 'negative', 'leading_whitespace', 'trailing_whitespace', 'object']];
    });
    $run('missing_source_event_returns_empty', static function () use ($product_id, $source_bricks): array {
        update_post_meta($product_id, 'produk_bron_event', '999999999');
        acf_flush_value_cache($product_id, 'produk_bron_event');
        $query = vg_product_single_124_qa_source_query($source_bricks, $product_id);
        vg_product_single_124_qa_check(($query['post__in'] ?? null) === [0], 'Missing source Event did not fail closed.');
        return ['query' => $query];
    });
    $run('wrong_post_type_source_event_returns_empty', static function () use ($product_id, $source_bricks): array {
        update_post_meta($product_id, 'produk_bron_event', (string) $product_id);
        acf_flush_value_cache($product_id, 'produk_bron_event');
        $query = vg_product_single_124_qa_source_query($source_bricks, $product_id);
        vg_product_single_124_qa_check(($query['post__in'] ?? null) === [0], 'Wrong-type source target did not fail closed.');
        return ['query' => $query, 'wrong_type_id' => $product_id];
    });
    $run('trashed_source_event_returns_empty', static function () use ($event_id, $product_id, $source_bricks): array {
        vg_product_single_124_qa_set_source_event($product_id, $event_id);
        $trashed = wp_trash_post($event_id);
        vg_product_single_124_qa_check($trashed instanceof WP_Post, 'Could not trash the owned QA source Event for endpoint validation.');
        try {
            $query = vg_product_single_124_qa_source_query($source_bricks, $product_id);
            vg_product_single_124_qa_check(($query['post__in'] ?? null) === [0], 'Trashed source Event did not fail closed.');
        } finally {
            wp_untrash_post($event_id);
        }
        return ['query' => ['post_type' => 'tc_events', 'post__in' => [0]], 'event_restored' => get_post_status($event_id) === 'publish'];
    });
    $run('acf_path_is_used', static function () use ($product_id, $event_id, $source_bricks): array {
        vg_product_single_124_qa_set_source_event($product_id, $event_id);
        acf_flush_value_cache($product_id, 'produk_bron_event');
        $loaded = false;
        $watcher = static function (mixed $value, mixed $post_id, array $field) use (&$loaded): mixed {
            if ((int) $post_id === (int) $GLOBALS['vg_product_single_124_qa_product_id'] && ($field['name'] ?? null) === 'produk_bron_event') {
                $loaded = true;
            }
            return $value;
        };
        $GLOBALS['vg_product_single_124_qa_product_id'] = $product_id;
        add_filter('acf/load_value/name=produk_bron_event', $watcher, 1, 3);
        try {
            $query = vg_product_single_124_qa_source_query($source_bricks, $product_id);
        } finally {
            remove_filter('acf/load_value/name=produk_bron_event', $watcher, 1);
            unset($GLOBALS['vg_product_single_124_qa_product_id']);
        }
        vg_product_single_124_qa_check($loaded && ($query['post__in'] ?? null) === [$event_id], 'Source adapter did not load produk_bron_event through ACF.');
        return ['acf_load_value_filter_fired' => $loaded];
    });
    $run('_event_name_is_unchanged_and_irrelevant', static function () use ($product_id, $event_id, $source_bricks, $qa_event_name_before, &$qa_event_name_after): array {
        vg_product_single_124_qa_set_source_event($product_id, $event_id);
        $query = vg_product_single_124_qa_source_query($source_bricks, $product_id);
        $qa_event_name_after = get_post_meta($product_id, '_event_name', false);
        vg_product_single_124_qa_check(($query['post__in'] ?? null) === [$event_id], 'Source Event failed without _event_name involvement.');
        vg_product_single_124_qa_check($qa_event_name_after === $qa_event_name_before, 'Task 7B changed the QA Product _event_name state.');
        return ['event_name_before' => $qa_event_name_before, 'event_name_after' => $qa_event_name_after];
    });

    $run('acf_unavailable_fails_closed', static function () use (&$no_acf_evidence): array {
        $no_acf_evidence = vg_product_single_124_qa_no_acf_process();
        return $no_acf_evidence;
    });
    $run('product_person_card_uses_canonical_person_data', static function () use ($product_id, $event_id, $people_bricks, $resolver, $person_a, $person_b, $person_c, $people_by_id, &$card_evidence, &$frontend_evidence): array {
        vg_product_single_124_qa_set_source_event($product_id, $event_id);
        vg_product_single_124_qa_set_people($product_id, 'event', []);
        $ids = $resolver->people_for_product($product_id);
        vg_product_single_124_qa_check($ids === [$person_b, $person_a, $person_c], 'Product frontend fixture did not produce expected ordered Person IDs.');
        $people_query = vg_product_single_124_qa_people_query($people_bricks, $product_id);
        vg_product_single_124_qa_check(
            $people_query === ['post_type' => 'person', 'post_status' => 'publish', 'post__in' => [$person_b, $person_a, $person_c], 'orderby' => 'post__in', 'posts_per_page' => -1],
            'Product Person Card loop is not directly queried as ordered canonical Persons.'
        );
        $card_evidence = vg_product_single_124_qa_card($person_b);
        $response = vg_product_single_124_qa_frontend(get_permalink($product_id));
        $rendered_cards = vg_product_single_124_qa_person_card_values($response['body']);
        $rendered_titles = array_column($rendered_cards, 'title');
        $positions = [];
        foreach ([$person_b, $person_a, $person_c] as $id) {
            $person = $people_by_id[$id];
            vg_product_single_124_qa_assert_card_html($response, $person, $person['image_id']);
            $positions[$id] = array_search($person['title'], $rendered_titles, true);
            vg_product_single_124_qa_check(is_int($positions[$id]), 'Person title was not present in a rendered Product Person Card.');
        }
        vg_product_single_124_qa_check($positions[$person_b] < $positions[$person_a] && $positions[$person_a] < $positions[$person_c], 'Rendered Product Person Cards are not in resolver order.');
        vg_product_single_124_qa_check(get_permalink($person_b) !== false, 'Canonical Person permalink is unavailable.');
        $profile = vg_product_single_124_qa_frontend(get_permalink($person_b));
        $frontend_evidence['canonical_person'] = ['status' => $profile['status'], 'url' => $profile['url']];
        $frontend_evidence['event_product'] = ['status' => $response['status'], 'url' => $response['url'], 'person_order' => [$person_b, $person_a, $person_c]];
        return ['card_id' => $card_evidence['id'], 'card_sha256' => $card_evidence['sha256'], 'loop_query' => $people_query, 'person_order' => [$person_b, $person_a, $person_c], 'profile_http_status' => $profile['status']];
    });
    $run('manual_product_person_card_preserves_order', static function () use ($product_id, $resolver, $person_c, $person_a, $people_by_id, &$frontend_evidence): array {
        vg_product_single_124_qa_set_people($product_id, 'manual', [$person_c, $person_a]);
        vg_product_single_124_qa_check($resolver->people_for_product($product_id) === [$person_c, $person_a], 'Manual card fixture order is invalid.');
        $response = vg_product_single_124_qa_frontend(get_permalink($product_id));
        vg_product_single_124_qa_assert_card_html($response, $people_by_id[$person_c], $people_by_id[$person_c]['image_id']);
        vg_product_single_124_qa_assert_card_html($response, $people_by_id[$person_a], $people_by_id[$person_a]['image_id']);
        $rendered_titles = array_column(vg_product_single_124_qa_person_card_values($response['body']), 'title');
        $first = array_search($people_by_id[$person_c]['title'], $rendered_titles, true);
        $second = array_search($people_by_id[$person_a]['title'], $rendered_titles, true);
        vg_product_single_124_qa_check(is_int($first) && is_int($second) && $first < $second, 'Manual Product cards did not preserve the explicit order.');
        $frontend_evidence['manual_product'] = ['status' => $response['status'], 'url' => $response['url'], 'person_order' => [$person_c, $person_a]];
        return ['person_order' => [$person_c, $person_a]];
    });
} catch (Throwable $error) {
    $qa_failure = $error;
} finally {
    if ((int) $owned['product'] > 0 && get_post((int) $owned['product']) instanceof WP_Post) {
        $qa_event_name_after = get_post_meta((int) $owned['product'], '_event_name', false);
    }
    $cleanup = vg_product_single_124_qa_cleanup($owned, $owner, $pre_api_ids);
}

$audit_failures = [];
$after = null;
$audit = static function (string $name, callable $assertion) use (&$audit_failures): void {
    try {
        $assertion();
    } catch (Throwable $error) {
        $audit_failures[] = ['check' => $name, 'message' => $error->getMessage()];
    }
};

$owned_ids = [
    (int) $owned['product'],
    ...array_map('intval', $owned['events']),
    ...array_map('intval', $owned['persons']),
    ...array_map('intval', array_keys($owned['participations'])),
];
$owned_residue = ['posts' => 0, 'meta_rows' => 0, 'term_relationships' => 0];
foreach ($owned_ids as $id) {
    if ($id > 0 && get_post($id) !== null) {
        ++$owned_residue['posts'];
    }
    if ($id > 0) {
        $owned_residue['meta_rows'] += vg_product_single_124_qa_post_meta_count($id);
        $owned_residue['term_relationships'] += vg_product_single_124_qa_relationship_count($id);
    }
}
$token_present = null;
$post_type_counts_equal = false;
$person_after = null;
$participation_after = null;
$protected_state_equal = false;
$coming_soon_state_equal = false;
$sandbox_directory_equal = false;

$audit('owned_records_removed', static function () use ($owned_residue): void {
    vg_product_single_124_qa_check($owned_residue === ['posts' => 0, 'meta_rows' => 0, 'term_relationships' => 0], 'Owned QA posts/meta/relationships remain.');
});
$audit('run_token_absent', static function () use ($token, &$token_present): void {
    $token_present = vg_product_single_124_qa_token_exists($token);
    vg_product_single_124_qa_check(! $token_present, 'Task 7B ownership token remains in a durable WordPress text store.');
});
$audit('person_and_participation_counts_restored', static function () use (&$person_after, &$participation_after): void {
    $person_after = vg_product_single_124_qa_post_count('person');
    $participation_after = vg_product_single_124_qa_post_count(EventParticipationRepository::POST_TYPE);
    vg_product_single_124_qa_check(
        $person_after === VG_PRODUCT_SINGLE_124_QA_EXPECTED_PERSON_COUNT
            && $participation_after === VG_PRODUCT_SINGLE_124_QA_EXPECTED_PARTICIPATION_COUNT,
        'Person or Event Participation count did not return to the Task 1 baseline.'
    );
});
$audit('qa_product_event_name_unchanged', static function () use (&$qa_event_name_after, $qa_event_name_before): void {
    if ($qa_event_name_after === null && $qa_event_name_before !== null) {
        $qa_event_name_after = $qa_event_name_before;
    }
    vg_product_single_124_qa_check($qa_event_name_before === $qa_event_name_after, 'QA Product _event_name changed.');
});
$audit('protected_state_matches_task_1', static function () use ($before, &$after, &$post_type_counts_equal, &$protected_state_equal, &$coming_soon_state_equal, &$sandbox_directory_equal): void {
    $after = vg_product_single_124_qa_snapshot();
    vg_product_single_124_qa_expected_state($after);
    $post_type_counts_equal = $before['post_type_counts'] === $after['post_type_counts'];
    vg_product_single_124_qa_check($post_type_counts_equal, 'All post-type/status counts did not return to the Task 1 pre-QA snapshot.');
    $coming_soon_state_equal = $before['woocommerce_coming_soon'] === $after['woocommerce_coming_soon']
        && $before['woocommerce_store_pages_only'] === $after['woocommerce_store_pages_only'];
    vg_product_single_124_qa_check($coming_soon_state_equal, 'WooCommerce public Coming Soon configuration changed during QA.');
    $sandbox_directory_equal = $before['sandbox_directory_entries'] === $after['sandbox_directory_entries'];
    vg_product_single_124_qa_check($sandbox_directory_equal, 'Novamira sandbox directory entries changed during QA.');
    $protected_state_equal = $post_type_counts_equal && $coming_soon_state_equal && $sandbox_directory_equal;
});
$audit('tickera_side_effects_equal', static function () use ($before): void {
    vg_product_single_124_qa_check(vg_product_single_124_qa_api_key_rows() === $before['api_keys'], 'Tickera side-effect state did not return to the pre-QA snapshot.');
});

$failures = [];
if ($qa_failure instanceof Throwable) {
    $failures[] = ['phase' => 'qa', 'message' => $qa_failure->getMessage()];
}
foreach ($cleanup['failures'] as $failure) {
    $failures[] = ['phase' => 'cleanup'] + $failure;
}
foreach ($audit_failures as $failure) {
    $failures[] = ['phase' => 'audit'] + $failure;
}
if ($scenarios === [] || array_reduce($scenarios, static fn (bool $ok, array $scenario): bool => $ok && $scenario['result'] === 'PASS', true) !== true) {
    if ($qa_failure === null) {
        $failures[] = ['phase' => 'qa', 'message' => 'One or more required Task 7B scenarios did not pass.'];
    }
}

$report = [
    'result' => $failures === [] ? 'PASS' : 'FAIL',
    'task' => '7B',
    'base_sha' => 'a6cccb7f9862d6b8ff11d6635da6ccfd5b6d6b76',
    'run_token' => $token,
    'owner_marker' => $owner,
    'preflight' => [
        'template124_projection_sha256' => $before['template124_projection_sha256'],
        'product_person_card' => array_map(
            static fn (string $id, array $row): array => ['id' => $id, 'sha256' => $row['sha256']],
            array_keys($before['product_person_cards']),
            array_values($before['product_person_cards'])
        ),
        'caller_counts' => array_map('count', $before['callers']),
        'helper_sha256' => $before['helper_hash'],
        'copy_snippet_sha256' => $before['copy_hash'],
        'person_count' => $before['person_count'],
        'event_participation_count' => $before['participation_count'],
        'woocommerce_coming_soon' => $before['woocommerce_coming_soon'],
        'woocommerce_store_pages_only' => $before['woocommerce_store_pages_only'],
        'sandbox_directory_entries' => $before['sandbox_directory_entries'],
        'frontend_authenticated_identity' => $frontend_identity,
        'tickera_api_key_count' => count($before['api_keys']),
        'tickera_api_keys_sha256' => hash('sha256', serialize($before['api_keys'])),
    ],
    'owned_ids' => [
        'product' => $owned['product'],
        'events' => $owned['events'],
        'persons' => $owned['persons'],
        'participations' => array_keys($owned['participations']),
    ],
    'scenarios' => $scenarios,
    'card' => $card_evidence === null ? null : ['id' => $card_evidence['id'], 'sha256' => $card_evidence['sha256']],
    'frontend' => $frontend_evidence,
    'acf_unavailable' => $no_acf_evidence,
    'qa_product_event_name' => ['before' => $qa_event_name_before, 'after' => $qa_event_name_after],
    'cleanup' => $cleanup + [
        'owned_residue' => $owned_residue,
        'token_present' => $token_present,
        'person_count_after' => $person_after,
        'event_participation_count_after' => $participation_after,
        'post_type_counts_equal' => $post_type_counts_equal,
        'woocommerce_public_coming_soon_state' => [
            'before' => [
                'woocommerce_coming_soon' => $before['woocommerce_coming_soon'],
                'woocommerce_store_pages_only' => $before['woocommerce_store_pages_only'],
            ],
            'after' => $after === null ? null : [
                'woocommerce_coming_soon' => $after['woocommerce_coming_soon'],
                'woocommerce_store_pages_only' => $after['woocommerce_store_pages_only'],
            ],
            'unchanged' => $coming_soon_state_equal,
        ],
        'sandbox_directory' => [
            'before' => $before['sandbox_directory_entries'],
            'after' => $after['sandbox_directory_entries'] ?? null,
            'unchanged' => $sandbox_directory_equal,
        ],
        'protected_state_equal' => $protected_state_equal,
        'protected_state_after' => $after,
    ],
    'failures' => $failures,
];
$encoded = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::line($encoded);
    if ($failures !== []) {
        \WP_CLI::error('Task 7B Product Single 124 QA failed.', 1);
    }
} else {
    echo $encoded . PHP_EOL;
}
