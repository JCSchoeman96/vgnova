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
        vgpc_test_true($indexer->is_dirty(11), 'Failed projection must set dirty state.');
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
        vgpc_test_true($indexer->is_dirty(15), 'Scheduled source validation failure must mark it dirty.');
    }

    public function test_rebuild_marks_source_dirty_when_repository_fails(): void
    {
        $this->ready_schema();
        $this->add_source_and_person(12, 'draft', 22, 'draft');
        $GLOBALS['vgpc_test_meta'][12]['primary_author'] = 22;
        $GLOBALS['wpdb']->fail_insert = true;
        $indexer = $this->indexer();

        vgpc_test_true(is_wp_error($indexer->rebuild_source(12)), 'Repository failure must be returned.');
        vgpc_test_true($indexer->is_dirty(12), 'Repository failure must mark source dirty.');
        vgpc_test_true(in_array('SELECT RELEASE_LOCK(%s)', array_column($GLOBALS['wpdb']->queries, 'query'), true), 'Repository failure must release lock.');
    }

    public function test_rebuild_marks_source_dirty_when_lock_cannot_be_acquired(): void
    {
        $this->ready_schema();
        $this->add_source_and_person(13, 'draft', 23, 'draft');
        $GLOBALS['wpdb']->get_var_handler = static fn (string $query): string => str_contains($query, 'GET_LOCK') ? '0' : '1';

        $indexer = $this->indexer();
        vgpc_test_true(is_wp_error($indexer->rebuild_source(13)), 'Contended lock must fail projection.');
        vgpc_test_true($indexer->is_dirty(13), 'Lock failure must mark source dirty.');
    }

    public function test_concurrent_save_failure_dirty_marker_survives_older_rebuild(): void
    {
        $this->ready_schema();
        $this->add_source_and_person(16, 'draft', 26, 'draft');
        $GLOBALS['vgpc_test_meta'][16]['primary_author'] = 26;
        $GLOBALS['wpdb']->fail_insert = false;
        $GLOBALS['wpdb']->fail_query = false;
        $GLOBALS['wpdb']->query_handler = static function (string $query): void {
            if (str_starts_with($query, 'DELETE FROM wp_vg_person_relationship_index')) {
                update_post_meta(16, PersonRelationshipContract::DIRTY_META_KEY, 1);
                update_post_meta(16, '_vg_person_relationship_index_dirty_generation', 'new-save-generation');
            }
        };

        $indexer = $this->indexer();
        vgpc_test_same(true, $indexer->rebuild_source(16), 'Older rebuild may finish after newer save misses the lock.');
        vgpc_test_true($indexer->is_dirty(16), 'Newer save dirty marker must survive older rebuild completion.');
    }

    public function test_dirty_marker_is_exact_integer_one(): void
    {
        update_post_meta(60, PersonRelationshipContract::DIRTY_META_KEY, 'random-string');
        $indexer = $this->indexer();
        vgpc_test_true(! $indexer->is_dirty(60), 'Arbitrary marker values must not be treated as dirty.');
        update_post_meta(60, PersonRelationshipContract::DIRTY_META_KEY, 1);
        vgpc_test_true($indexer->is_dirty(60), 'Integer one must mark the source dirty.');
    }

    public function test_dirty_marker_persistence_failure_returns_error(): void
    {
        $this->add_source_and_person(61, 'draft', 62, 'draft');
        $GLOBALS['wpdb']->get_var_handler = static fn (string $query): string => str_contains($query, 'GET_LOCK') ? '0' : '1';
        $GLOBALS['vgpc_update_post_meta_handler'] = static fn (int $id, string $key): bool => $key !== PersonRelationshipContract::DIRTY_META_KEY;
        $indexer = $this->indexer();
        $result = $indexer->rebuild_source(61);
        vgpc_test_true(is_wp_error($result), 'Failure to persist dirty state must be reported.');
        vgpc_test_same('dirty_marker_persistence_failed', $result->get_error_code(), 'Dirty persistence failure must fail closed with a diagnostic code.');
        vgpc_test_true(! $indexer->is_dirty(61), 'Failed persistence must not claim the marker was stored.');
    }

    public function test_unchanged_dirty_marker_is_confirmed_after_update_returns_false(): void
    {
        $GLOBALS['vgpc_update_post_meta_handler'] = null;
        update_post_meta(63, PersonRelationshipContract::DIRTY_META_KEY, 1);
        $GLOBALS['vgpc_test_posts'][63] = (object) ['ID' => 63, 'post_type' => 'post', 'post_status' => 'draft'];
        $GLOBALS['wpdb']->get_var_handler = static fn (string $query): string => str_contains($query, 'GET_LOCK') ? '0' : '1';
        $indexer = $this->indexer();
        $result = $indexer->rebuild_source(63);
        vgpc_test_true(is_wp_error($result), 'Lock failure remains an error.');
        vgpc_test_true($indexer->is_dirty(63), 'Unchanged integer marker must count as persisted despite update_post_meta returning false.');
        vgpc_test_same('source_lock_failed', $result->get_error_code(), 'Unchanged marker must not replace the original failure.');
    }

    public function test_dirty_failure_interleaved_with_atomic_clear_survives(): void
    {
        $this->ready_schema();
        $this->add_source_and_person(66, 'draft', 67, 'draft');
        $GLOBALS['vgpc_test_meta'][66]['primary_author'] = 67;
        update_post_meta(66, PersonRelationshipContract::DIRTY_META_KEY, 1);
        update_post_meta(66, '_vg_person_relationship_index_dirty_generation', 'older-generation');
        $interleaved = false;
        $GLOBALS['wpdb']->query_handler = static function (string $query) use (&$interleaved): void {
            if (! $interleaved && str_contains($query, 'DELETE dirty FROM wp_postmeta')) {
                $interleaved = true;
                update_post_meta(66, '_vg_person_relationship_index_dirty_generation', 'newer-generation');
                update_post_meta(66, PersonRelationshipContract::DIRTY_META_KEY, 1);
            }
        };

        $indexer = $this->indexer();
        vgpc_test_same(true, $indexer->rebuild_source(66), 'An older successful projection can finish while a newer save records failure.');
        vgpc_test_true($interleaved, 'Dirty-state compare and clear must run as one database operation.');
        vgpc_test_true($indexer->is_dirty(66), 'A newer failure interleaved with marker clearing must remain dirty.');
    }

    public function test_release_failure_is_reported_without_retrying_unconfirmed_ownership(): void
    {
        $this->ready_schema();
        $this->add_source_and_person(64, 'draft', 65, 'draft');
        $attempts = 0;
        $GLOBALS['wpdb']->get_var_handler = static function (string $query) use (&$attempts): mixed {
            if (str_contains($query, 'SHOW TABLES')) return 'wp_vg_person_relationship_index';
            if (str_contains($query, 'GET_LOCK')) return '1';
            if (str_contains($query, 'RELEASE_LOCK')) { $attempts++; return null; }
            return '1';
        };
        $lock = new \Voelgoed\PlatformCore\Person\PersonRelationshipSourceLock();
        $indexer = $this->indexer(null, $lock);
        $result = $indexer->rebuild_source(64);
        vgpc_test_true(is_wp_error($result), 'Failed lock release must turn projection success into an error.');
        vgpc_test_same('source_lock_release_failed', $result->get_error_code(), 'Release failure must be diagnosable.');
        vgpc_test_true($indexer->is_dirty(64), 'Release failure must leave the source marked dirty for reconciliation.');
        $lock->release_all();
        vgpc_test_same(1, $attempts, 'An unconfirmed release must not be retried.');
    }

    public function test_new_operation_does_not_inherit_previous_release_failure(): void
    {
        $this->ready_schema();
        $this->add_source_and_person(68, 'draft', 69, 'draft');
        $GLOBALS['vgpc_test_meta'][68]['primary_author'] = 69;
        $release_attempts = 0;
        $GLOBALS['wpdb']->get_var_handler = static function (string $query) use (&$release_attempts): mixed {
            if (str_contains($query, 'SHOW TABLES')) return 'wp_vg_person_relationship_index';
            if (str_contains($query, 'GET_LOCK')) return '1';
            if (str_contains($query, 'RELEASE_LOCK')) return ++$release_attempts === 1 ? '0' : '1';
            return '1';
        };
        $lock = new \Voelgoed\PlatformCore\Person\PersonRelationshipSourceLock();
        $indexer = $this->indexer(null, $lock);

        $first = $indexer->rebuild_source(68);
        vgpc_test_true(is_wp_error($first), 'An unconfirmed release must fail the operation.');
        vgpc_test_same('source_lock_release_failed', $first->get_error_code(), 'The first release failure must be reported.');
        vgpc_test_same(true, $indexer->rebuild_source(68), 'A fresh lock acquisition and release must not inherit stale failure status.');
        vgpc_test_same(2, $release_attempts, 'Each operation must release only its own confirmed acquisition.');
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

    private function indexer(?callable $logger = null, ?\Voelgoed\PlatformCore\Person\PersonRelationshipSourceLock $lock = null): PersonRelationshipIndexer
    {
        $schema = new PersonRelationshipSchema();
        $logger ??= static function (): void {};
        return new PersonRelationshipIndexer(
            $lock ?? new \Voelgoed\PlatformCore\Person\PersonRelationshipSourceLock(),
            new \Voelgoed\PlatformCore\Person\PersonRelationshipSourceReader(),
            new \Voelgoed\PlatformCore\Person\PersonRelationshipValidator(),
            new PersonRelationshipRepository($schema),
            $logger,
        );
    }
}
