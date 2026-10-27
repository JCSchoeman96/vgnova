<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
define('REST_REQUEST', true);
foreach (['PersonRelationshipContract', 'PersonRelationshipSchema', 'PersonRelationshipSourceLock', 'PersonRelationshipRepository', 'PersonRelationshipSourceReader', 'PersonRelationshipValidator', 'PersonRelationshipIndexer', 'PersonRelationshipAdmin'] as $class) {
    require_once __DIR__ . '/../includes/Person/' . $class . '.php';
}

use Voelgoed\PlatformCore\Person\PersonRelationshipAdmin;
use Voelgoed\PlatformCore\Person\PersonRelationshipContract;

vgpc_test_reset();
acf_add_local_field_group(['key' => 'rest_repair_test', 'fields' => [
    ['key' => 'field_vg_article_primary_author', 'name' => 'primary_author'],
    ['key' => 'field_vg_article_additional_authors', 'name' => 'additional_authors'],
    ['key' => 'field_vg_article_expert_contributors', 'name' => 'expert_contributors'],
    ['key' => 'field_vg_article_reviewed_by', 'name' => 'reviewed_by'],
]]);
$GLOBALS['vgpc_test_posts'][900] = (object) ['ID' => 900, 'post_type' => 'post', 'post_status' => 'draft'];
$GLOBALS['vgpc_test_posts'][901] = (object) ['ID' => 901, 'post_type' => 'person', 'post_status' => 'draft'];
$GLOBALS['vgpc_test_posts'][902] = (object) ['ID' => 902, 'post_type' => 'person', 'post_status' => 'publish'];
$GLOBALS['vgpc_test_posts'][903] = (object) ['ID' => 903, 'post_type' => 'person', 'post_status' => 'publish'];
$GLOBALS['vgpc_test_meta'][900]['primary_author'] = 901;
$GLOBALS['vgpc_test_meta'][900]['additional_authors'] = [902];
$admin = new PersonRelationshipAdmin();
$data = ['ID' => 900, 'post_type' => 'post', 'post_status' => 'publish'];
vgpc_test_same($data, $admin->validate_source_before_save($data, ['ID' => 900]), 'The pre-insert hook must not reject REST repair using stale stored ACF values.');
$request = new class {
    public function get_params(): array { return ['primary_author' => 903]; }
};
$prepared = (object) ['ID' => 900, 'post_type' => 'post', 'post_status' => 'publish'];
vgpc_test_same($prepared, $admin->validate_rest_source($prepared, $request), 'A valid partial REST repair must merge with other stored relationship fields.');
$GLOBALS['vgpc_test_posts'][902]->post_status = 'draft';
$rejected = $admin->validate_rest_source($prepared, $request);
vgpc_test_true(is_wp_error($rejected), 'A partial REST update must validate unchanged stored fields, not drop them.');
vgpc_test_same('invalid_person_status', $rejected->get_error_code(), 'The unchanged invalid stored relationship must remain visible to validation.');
echo "PASS REST repair bypasses stale pre-insert state and validates partial updates against stored fields.\n";
