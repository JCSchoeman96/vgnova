<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Person\PersonRelationshipContract;
use Voelgoed\PlatformCore\Person\PersonRelationshipIndexer;
use Voelgoed\PlatformCore\Person\PersonRelationshipRepository;
use Voelgoed\PlatformCore\Person\PersonRelationshipSchema;

final class PersonRelationshipIndexerTest
{
    public function test_rebuild_replaces_projection_and_clears_dirty_state(): void
    {
        $this->ready_schema();
        $this->add_source_and_person(10, 'publish', 20, 'publish');
        $GLOBALS['vgpc_test_meta'][10]['primary_author'] = 20;
        update_post_meta(10, PersonRelationshipContract::DIRTY_META_KEY, 1);

        $indexer = $this->indexer();
        vgpc_test_same(true, $indexer->rebuild_source(10), 'Valid source projection should rebuild.');
        vgpc_test_same(false, $indexer->is_dirty(10), 'Successful rebuild should clear dirty state.');
        vgpc_test_same(1, count($GLOBALS['wpdb']->inserted), 'Successful rebuild should insert the full projection.');
        vgpc_test_same(20, $GLOBALS['wpdb']->inserted[0][1]['person_id'], 'Projection must use canonical Person ID.');
        vgpc_test_true(in_array('SELECT RELEASE_LOCK(%s)', array_column($GLOBALS['wpdb']->queries, 'query'), true), 'Rebuild must release the source lock.');
    }

    public function test_rebuild_marks_source_dirty_when_validation_fails(): void
    {
        $this->ready_schema();
        $this->add_source_and_person(11, 'publish', 21, 'draft');
        $GLOBALS['vgpc_test_meta'][11]['primary_author'] = 21;
        $logged = [];
        $indexer = $this->indexer(static function (string $message) use (&$logged): void { $logged[] = $message; });

        $result = $indexer->rebuild_source(11);
        vgpc_test_true(is_wp_error($result), 'Published source with a draft Person must fail projection.');
        vgpc_test_same(1, get_post_meta(11, PersonRelationshipContract::DIRTY_META_KEY, true), 'Failed projection must set dirty state.');
        vgpc_test_same(1, count($logged), 'Failure must be logged once.');
        vgpc_test_true(str_contains($logged[0], '11') && str_contains($logged[0], 'invalid_person_status'), 'Log must contain source ID and sanitized error code.');
        vgpc_test_true(in_array('SELECT RELEASE_LOCK(%s)', array_column($GLOBALS['wpdb']->queries, 'query'), true), 'Validation failure must release lock.');
    }

    public function test_scheduled_source_requires_published_person(): void
    {
        $this->ready_schema();
        $this->add_source_and_person(15, 'future', 25, 'draft');
        $GLOBALS['vgpc_test_meta'][15]['primary_author'] = 25;

        $indexer = $this->indexer(static function (): void {});
        vgpc_test_true(is_wp_error($indexer->rebuild_source(15)), 'Scheduled public source must not accept a draft Person.');
        vgpc_test_same(1, get_post_meta(15, PersonRelationshipContract::DIRTY_META_KEY, true), 'Scheduled source validation failure must mark it dirty.');
    }

    public function test_rebuild_marks_source_dirty_when_repository_fails(): void
    {
        $this->ready_schema();
        $this->add_source_and_person(12, 'draft', 22, 'draft');
        $GLOBALS['vgpc_test_meta'][12]['primary_author'] = 22;
        $GLOBALS['wpdb']->fail_insert = true;
        $indexer = $this->indexer();

        vgpc_test_true(is_wp_error($indexer->rebuild_source(12)), 'Repository failure must be returned.');
        vgpc_test_same(1, get_post_meta(12, PersonRelationshipContract::DIRTY_META_KEY, true), 'Repository failure must mark source dirty.');
        vgpc_test_true(in_array('SELECT RELEASE_LOCK(%s)', array_column($GLOBALS['wpdb']->queries, 'query'), true), 'Repository failure must release lock.');
    }

    public function test_rebuild_marks_source_dirty_when_lock_cannot_be_acquired(): void
    {
        $this->ready_schema();
        $this->add_source_and_person(13, 'draft', 23, 'draft');
        $GLOBALS['wpdb']->get_var_handler = static fn (string $query): string => str_contains($query, 'GET_LOCK') ? '0' : '1';

        $indexer = $this->indexer();
        vgpc_test_true(is_wp_error($indexer->rebuild_source(13)), 'Contended lock must fail projection.');
        vgpc_test_same(1, get_post_meta(13, PersonRelationshipContract::DIRTY_META_KEY, true), 'Lock failure must mark source dirty.');
    }

    public function test_remove_source_deletes_rows_and_clears_dirty_state(): void
    {
        $this->ready_schema();
        update_post_meta(14, PersonRelationshipContract::DIRTY_META_KEY, 1);
        $indexer = $this->indexer();

        vgpc_test_same(true, $indexer->remove_source(14), 'Removed source rows should be deleted.');
        vgpc_test_same(false, $indexer->is_dirty(14), 'Successful source removal should clear dirty state.');
        vgpc_test_true(in_array('SELECT RELEASE_LOCK(%s)', array_column($GLOBALS['wpdb']->queries, 'query'), true), 'Source removal must release lock.');
    }

    private function ready_schema(): void
    {
        $GLOBALS['vgpc_test_options'][PersonRelationshipSchema::VERSION_OPTION] = PersonRelationshipSchema::SCHEMA_VERSION;
        $GLOBALS['wpdb']->get_var_handler = static fn (string $query): string => str_contains($query, 'SHOW TABLES') ? 'wp_vg_person_relationship_index' : '1';
    }

    private function add_source_and_person(int $source_id, string $source_status, int $person_id, string $person_status): void
    {
        $GLOBALS['vgpc_test_posts'][$source_id] = (object) ['ID' => $source_id, 'post_type' => 'post', 'post_status' => $source_status];
        $GLOBALS['vgpc_test_posts'][$person_id] = (object) ['ID' => $person_id, 'post_type' => 'person', 'post_status' => $person_status];
        acf_add_local_field_group(['key' => 'test_person_relationships', 'fields' => [
            ['key' => 'field_primary_author', 'name' => 'primary_author'],
            ['key' => 'field_additional_authors', 'name' => 'additional_authors'],
            ['key' => 'field_expert_contributors', 'name' => 'expert_contributors'],
            ['key' => 'field_reviewed_by', 'name' => 'reviewed_by'],
        ]]);
    }

    private function indexer(?callable $logger = null): PersonRelationshipIndexer
    {
        $schema = new PersonRelationshipSchema();
        $logger ??= static function (): void {};
        return new PersonRelationshipIndexer(
            new \Voelgoed\PlatformCore\Person\PersonRelationshipSourceLock(),
            new \Voelgoed\PlatformCore\Person\PersonRelationshipSourceReader(),
            new \Voelgoed\PlatformCore\Person\PersonRelationshipValidator(),
            new PersonRelationshipRepository($schema),
            $logger,
        );
    }
}
