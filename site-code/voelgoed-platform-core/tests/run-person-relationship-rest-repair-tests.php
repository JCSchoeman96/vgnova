<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
define('REST_REQUEST', true);
foreach (['PersonRelationshipContract', 'PersonRelationshipSchema', 'PersonRelationshipSourceLock', 'PersonRelationshipRepository', 'PersonRelationshipSourceReader', 'PersonRelationshipValidator', 'PersonRelationshipIndexer', 'PersonRelationshipAdmin'] as $class) {
    require_once __DIR__ . '/../includes/Person/' . $class . '.php';
}

use Voelgoed\PlatformCore\Person\PersonRelationshipAdmin;

vgpc_test_reset();
acf_add_local_field_group(['key' => 'rest_repair_test', 'fields' => [
    ['key' => 'field_vg_article_primary_author', 'name' => 'primary_author'],
    ['key' => 'field_vg_article_additional_authors', 'name' => 'additional_authors'],
    ['key' => 'field_vg_article_expert_contributors', 'name' => 'expert_contributors'],
    ['key' => 'field_vg_article_reviewed_by', 'name' => 'reviewed_by'],
]]);
$GLOBALS['vgpc_test_posts'][900] = (object) ['ID' => 900, 'post_type' => 'post', 'post_status' => 'draft'];
$GLOBALS['vgpc_test_posts'][901] = (object) ['ID' => 901, 'post_type' => 'person', 'post_status' => 'publish'];
$GLOBALS['vgpc_test_posts'][902] = (object) ['ID' => 902, 'post_type' => 'person', 'post_status' => 'draft'];
$GLOBALS['vgpc_test_meta'][900]['primary_author'] = 901;
$admin = new PersonRelationshipAdmin();
$prepared = (object) ['ID' => 900, 'post_type' => 'post', 'post_status' => 'publish'];
$request = new class {
    public function get_params(): array { return []; }
};
vgpc_test_same($prepared, $admin->validate_rest_source($prepared, $request), 'A publish request with a valid stored Person must pass after normal REST preparation.');
$GLOBALS['vgpc_test_meta'][900]['primary_author'] = 902;
$rejected = $admin->validate_rest_source($prepared, $request);
vgpc_test_true(is_wp_error($rejected), 'A publish request with invalid persisted ACF relationships must fail.');
vgpc_test_same('invalid_person_status', $rejected->get_error_code(), 'Validation must use persisted relationship authority.');
foreach ([
    ['primary_author' => 901],
    ['acf' => ['primary_author' => 901]],
    ['meta' => ['field_vg_article_primary_author' => 901]],
] as $params) {
    $override_request = new class($params) {
        public function __construct(private array $params) {}
        public function get_params(): array { return $this->params; }
    };
    $override_result = $admin->validate_rest_source((object) ['ID' => 900, 'post_type' => 'post', 'post_status' => 'draft'], $override_request);
    vgpc_test_true(is_wp_error($override_result), 'REST must reject relationship values that ACF groups will not persist.');
    vgpc_test_same('unsupported_person_relationship_rest_write', $override_result->get_error_code(), 'Rejected overrides must have a stable error code.');
}
echo "PASS REST validates persisted ACF authority and rejects unpersisted relationship overrides.\n";
