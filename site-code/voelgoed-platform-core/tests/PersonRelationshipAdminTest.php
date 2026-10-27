<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Person\PersonRelationshipAdmin;
use Voelgoed\PlatformCore\Person\PersonRelationshipContract;
use Voelgoed\PlatformCore\Person\PersonRelationshipIndexer;
use Voelgoed\PlatformCore\Person\PersonRelationshipRepository;
use Voelgoed\PlatformCore\Person\PersonRelationshipSchema;
use Voelgoed\PlatformCore\Person\PersonRelationshipSourceLock;
use Voelgoed\PlatformCore\Person\PersonRelationshipSourceReader;
use Voelgoed\PlatformCore\Person\PersonRelationshipValidator;
use Voelgoed\PlatformCore\Product\ProductContributionAdmin;

if (! function_exists('check_and_publish_future_post')) {
    function check_and_publish_future_post(int $post_id): void
    {
        $GLOBALS['vgpc_test_core_future_publish_calls'][] = $post_id;
    }
}
if (! function_exists('remove_action')) {
    function remove_action(string $hook, callable|string $callback, int $priority = 10): bool
    {
        return true;
    }
}

if (! class_exists('VGPC_TestRestRequest')) {
    final class VGPC_TestRestRequest
    {
        public function __construct(private array $params) {}
        public function get_params(): array { return $this->params; }
        public function get_param(string $key): mixed { return $this->params[$key] ?? null; }
    }
}

final class PersonRelationshipAdminTest
{
    public function test_registers_all_field_and_lifecycle_hooks(): void
    {
        $admin = $this->admin();
        $admin->register();

        foreach ([
            'field_vg_article_primary_author', 'field_vg_article_additional_authors',
            'field_vg_article_expert_contributors', 'field_vg_article_reviewed_by',
            'field_vg_course_lead_instructor', 'field_vg_course_additional_instructors',
            'field_vg_course_experts',
        ] as $key) {
            vgpc_test_true(isset($GLOBALS['vgpc_test_filters']['acf/validate_value/key=' . $key]), 'Every approved field key must have validation.');
        }
        vgpc_test_true(isset($GLOBALS['vgpc_test_filters']['wp_insert_post_data']), 'Source publication must be checked before save.');
        vgpc_test_true(isset($GLOBALS['vgpc_test_actions']['acf/save_post']), 'ACF saves must project at priority 20.');
        vgpc_test_same(1, min(array_column($GLOBALS['vgpc_test_actions']['acf/save_post'], 'priority')), 'Actual ACF saves must validate before ACF writes its fields.');
        vgpc_test_true(isset($GLOBALS['vgpc_test_actions']['shutdown']), 'Source write locks must release after editor and REST persistence has completed.');
        vgpc_test_true(isset($GLOBALS['vgpc_test_actions']['transition_post_status']), 'Published source transitions must be checked for bypassed relationship validation.');
        vgpc_test_true(isset($GLOBALS['vgpc_test_actions']['rest_after_insert_post']), 'REST Article saves must project after REST metadata persistence.');
        vgpc_test_true(isset($GLOBALS['vgpc_test_actions']['rest_after_insert_mto-course']), 'REST Course saves must project after REST metadata persistence.');
        vgpc_test_true(isset($GLOBALS['vgpc_test_actions']['before_delete_post']), 'Permanent deletion must clean derived rows.');
        vgpc_test_true(isset($GLOBALS['vgpc_test_filters']['voelgoed/platform_core/future_post_can_publish']), 'Scheduled publication must use the shared filter seam.');
        vgpc_test_true(! isset($GLOBALS['vgpc_test_filters']['query']), 'Person publication validation must not register a global SQL query interception filter.');
    }

    public function test_platform_core_person_source_does_not_register_global_sql_query_interception(): void
    {
        $includes = dirname(__DIR__) . '/includes';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($includes, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') continue;
            $source = file_get_contents($file->getPathname());
            vgpc_test_true(is_string($source), 'Platform Core source must be readable for the SQL interception policy check.');
            vgpc_test_true(
                preg_match('/add_filter\\s*\\(\\s*([\'\\\"])query\\1/i', $source) !== 1,
                'Platform Core production source must not register the global query filter.'
            );
        }
    }

    public function test_invalid_bypass_publish_is_diagnosed_dirtied_and_left_published_without_rewriting_acf(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(701, 'draft', [702]);
        $GLOBALS['vgpc_test_posts'][702]->post_status = 'publish';
        update_field('primary_author', 702, 701);
        $GLOBALS['vgpc_test_enable_query_cache'] = true;
        vgpc_test_same(702, get_field('primary_author', 701, false), 'The fixture must prime a stale ACF value before the stored field changes.');
        get_post(702);
        $GLOBALS['vgpc_test_posts'][701]->post_status = 'publish';
        $GLOBALS['vgpc_test_posts'][702]->post_status = 'draft';
        vgpc_test_same('publish', $GLOBALS['vgpc_test_posts'][701]->post_status, 'The low-level bypass fixture starts after WordPress has already written the published status.');
        $GLOBALS['vgpc_test_meta'][701][PersonRelationshipContract::DIRTY_META_KEY] = '0';
        $diagnostics = [];
        add_action('voelgoed/platform_core/person_relationship_integrity_violation', static function (array $event) use (&$diagnostics): void { $diagnostics[] = $event; }, 10, 1);

        do_action('transition_post_status', 'publish', 'draft', $GLOBALS['vgpc_test_posts'][701]);
        do_action('shutdown');
        do_action('transition_post_status', 'publish', 'publish', $GLOBALS['vgpc_test_posts'][701]);
        do_action('shutdown');

        vgpc_test_same('1', (string) get_post_meta(701, PersonRelationshipContract::DIRTY_META_KEY, true), 'A bypassed invalid publish must set dirty to exactly 1.');
        vgpc_test_same(1, count($diagnostics), 'Repeated transition callbacks must not duplicate diagnostics.');
        vgpc_test_same(['severity' => 'high', 'code' => 'invalid_person_relationships_on_publish', 'source_id' => 701, 'source_type' => 'post', 'dirty_marker_persisted' => true], $diagnostics[0] ?? null, 'The diagnostic must contain only sanitized integrity details and confirmed marker state.');
        vgpc_test_same('publish', $GLOBALS['vgpc_test_posts'][701]->post_status, 'The detector must leave the already-written status unchanged.');
        vgpc_test_same(702, get_field('primary_author', 701, false), 'The detector must leave authoritative ACF relationships unchanged.');
        vgpc_test_same([[701, 'post_meta']], $GLOBALS['vgpc_test_cache_deletes'], 'The detector must invalidate stored post metadata before rereading ACF.');
    }

    public function test_valid_or_unsupported_publish_transition_does_not_dirty_or_diagnose(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(703, 'draft', [704]);
        update_field('primary_author', 704, 703);
        $GLOBALS['vgpc_test_posts'][703]->post_status = 'publish';
        $GLOBALS['vgpc_test_posts'][704]->post_status = 'publish';
        $GLOBALS['vgpc_test_posts'][705] = (object) ['ID' => 705, 'post_type' => 'product', 'post_status' => 'publish'];
        $diagnostics = [];
        add_action('voelgoed/platform_core/person_relationship_integrity_violation', static function (array $event) use (&$diagnostics): void { $diagnostics[] = $event; }, 10, 1);

        do_action('transition_post_status', 'publish', 'draft', $GLOBALS['vgpc_test_posts'][703]);
        do_action('transition_post_status', 'publish', 'draft', $GLOBALS['vgpc_test_posts'][705]);
        do_action('shutdown');

        vgpc_test_same('', (string) get_post_meta(703, PersonRelationshipContract::DIRTY_META_KEY, true), 'A valid publish must not mutate dirty metadata.');
        vgpc_test_same('', (string) get_post_meta(705, PersonRelationshipContract::DIRTY_META_KEY, true), 'Unsupported source types must not mutate dirty metadata.');
        vgpc_test_same([], $diagnostics, 'Valid and unsupported transitions must not report integrity violations.');
    }

    public function test_failed_dirty_marker_write_is_reported_without_claiming_quarantine(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(707, 'draft', [708]);
        update_field('primary_author', 708, 707);
        $GLOBALS['vgpc_test_posts'][707]->post_status = 'publish';
        $GLOBALS['vgpc_test_posts'][708]->post_status = 'draft';
        $GLOBALS['vgpc_update_post_meta_handler'] = static fn (int $id, string $key): bool => $key !== PersonRelationshipContract::DIRTY_META_KEY;
        $diagnostics = [];
        add_action('voelgoed/platform_core/person_relationship_integrity_violation', static function (array $event) use (&$diagnostics): void { $diagnostics[] = $event; }, 10, 1);

        do_action('transition_post_status', 'publish', 'draft', $GLOBALS['vgpc_test_posts'][707]);
        do_action('shutdown');

        vgpc_test_same('', (string) get_post_meta(707, PersonRelationshipContract::DIRTY_META_KEY, true), 'A failed marker write must remain observable as absent.');
        vgpc_test_same(false, $diagnostics[0]['dirty_marker_persisted'] ?? null, 'The integrity diagnostic must state that quarantine persistence failed.');
        vgpc_test_same('publish', $GLOBALS['vgpc_test_posts'][707]->post_status, 'A failed marker write must not rewrite published status.');
        vgpc_test_same(708, get_field('primary_author', 707, false), 'A failed marker write must not alter authoritative ACF fields.');
        $GLOBALS['vgpc_update_post_meta_handler'] = null;
    }

    public function test_existing_dirty_marker_false_on_unchanged_counts_as_persisted(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(709, 'draft', [710]);
        update_field('primary_author', 710, 709);
        $GLOBALS['vgpc_test_posts'][709]->post_status = 'publish';
        $GLOBALS['vgpc_test_posts'][710]->post_status = 'draft';
        update_post_meta(709, PersonRelationshipContract::DIRTY_META_KEY, 1);
        $GLOBALS['vgpc_update_post_meta_handler'] = static function (): never { throw new RuntimeException('An unchanged existing marker should not need a write.'); };
        $diagnostics = [];
        add_action('voelgoed/platform_core/person_relationship_integrity_violation', static function (array $event) use (&$diagnostics): void { $diagnostics[] = $event; }, 10, 1);

        do_action('transition_post_status', 'publish', 'draft', $GLOBALS['vgpc_test_posts'][709]);
        do_action('shutdown');

        vgpc_test_same(true, $diagnostics[0]['dirty_marker_persisted'] ?? null, 'An already-persisted marker must count as successful despite WordPress false-on-unchanged semantics.');
        $GLOBALS['vgpc_update_post_meta_handler'] = null;
    }

    public function test_supported_editor_relationship_save_finishes_before_deferred_bypass_check(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(711, 'draft', [712, 713]);
        update_field('primary_author', 712, 711);
        $GLOBALS['vgpc_test_posts'][711]->post_status = 'publish';
        $GLOBALS['vgpc_test_posts'][712]->post_status = 'draft';
        $GLOBALS['vgpc_test_posts'][713]->post_status = 'publish';
        $_POST = ['post_ID' => 711, 'post_status' => 'publish', 'acf' => ['field_vg_article_primary_author' => 713]];
        $diagnostics = [];
        add_action('voelgoed/platform_core/person_relationship_integrity_violation', static function (array $event) use (&$diagnostics): void { $diagnostics[] = $event; }, 10, 1);

        do_action('transition_post_status', 'publish', 'draft', $GLOBALS['vgpc_test_posts'][711]);
        vgpc_test_same('', (string) get_post_meta(711, PersonRelationshipContract::DIRTY_META_KEY, true), 'The detector must wait until supported editor ACF writes finish.');
        $valid = apply_filters('acf/validate_value/key=field_vg_article_primary_author', true, 713, ['key' => 'field_vg_article_primary_author', 'name' => 'primary_author'], 'acf[field_vg_article_primary_author]');
        vgpc_test_same(true, $valid, 'The supported editor path must validate the submitted replacement before ACF persists it.');
        update_field('primary_author', 713, 711);
        do_action('shutdown');

        vgpc_test_same(713, get_field('primary_author', 711, false), 'The supported editor save must persist its validated replacement relationship.');
        vgpc_test_same([], $diagnostics, 'A supported editor save must not be misreported from the pre-ACF transition state.');
    }

    public function test_editor_publish_preflight_uses_persisted_fields_before_core_status_write(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(715, 'draft', [716, 717]);
        update_field('primary_author', 716, 715);
        $GLOBALS['vgpc_test_posts'][716]->post_status = 'draft';
        $GLOBALS['vgpc_test_posts'][717]->post_status = 'publish';
        $_POST = ['post_ID' => 715, 'post_status' => 'publish', 'acf' => ['field_vg_article_primary_author' => 717]];
        try {
            apply_filters('wp_insert_post_data', ['ID' => 715, 'post_type' => 'post', 'post_status' => 'publish'], ['ID' => 715]);
            throw new RuntimeException('Core must not publish using a submitted relationship that ACF has not persisted.');
        } catch (RuntimeException $error) {
            vgpc_test_true(str_contains($error->getMessage(), 'Person 716'), 'Preflight must report the invalid stored Person instead of trusting the submitted replacement.');
        }
        vgpc_test_same(716, get_field('primary_author', 715, false), 'Preflight must not rewrite stored ACF authority.');
    }

    public function test_acf_validation_failure_after_core_write_leaves_only_valid_stored_relationship_published(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(718, 'draft', [719, 720]);
        $GLOBALS['vgpc_test_posts'][719]->post_status = 'publish';
        $GLOBALS['vgpc_test_posts'][720]->post_status = 'publish';
        update_field('primary_author', 719, 718);
        $_POST = ['post_ID' => 718, 'post_status' => 'publish', 'acf' => ['field_vg_article_primary_author' => 720]];

        $data = ['ID' => 718, 'post_type' => 'post', 'post_status' => 'publish'];
        vgpc_test_same($data, apply_filters('wp_insert_post_data', $data, ['ID' => 718]), 'Core preflight may publish against valid stored authority.');
        $GLOBALS['vgpc_test_posts'][718]->post_status = 'publish';
        do_action('transition_post_status', 'publish', 'draft', $GLOBALS['vgpc_test_posts'][718]);
        // The core status write has happened. Model a nonce/ACF validation failure
        // before update_field is reached, so the submitted value never persists.
        do_action('shutdown');

        vgpc_test_same('publish', $GLOBALS['vgpc_test_posts'][718]->post_status, 'The test models WordPress writing status before ACF validation fails.');
        vgpc_test_same(719, get_field('primary_author', 718, false), 'Failed ACF validation must leave the prior stored value intact.');
        vgpc_test_same('', (string) get_post_meta(718, PersonRelationshipContract::DIRTY_META_KEY, true), 'The deferred check must recognize the valid stored relationship and avoid false bypass reports.');
    }

    public function test_editor_cannot_repair_published_duplicate_roles_in_one_acf_save(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(731, 'publish', [732]);
        $GLOBALS['vgpc_test_posts'][732]->post_status = 'publish';
        update_field('primary_author', 732, 731);
        update_field('additional_authors', [732], 731);
        $_POST = ['post_ID' => 731, 'post_status' => 'publish', 'acf' => [
            'field_vg_article_primary_author' => null,
            'field_vg_article_additional_authors' => [732],
        ]];

        try {
            apply_filters('wp_insert_post_data', ['ID' => 731, 'post_type' => 'post', 'post_status' => 'publish'], ['ID' => 731]);
            throw new RuntimeException('Invalid persisted state must fail the current preflight.');
        } catch (RuntimeException $error) {
            vgpc_test_true(str_contains($error->getMessage(), 'may hold only one role'), 'The stored duplicate must be the reason repair is blocked.');
        }
        vgpc_test_same(732, get_field('primary_author', 731, false), 'Preflight must not silently rewrite the authoritative field while rejecting repair.');
        vgpc_test_same([732], get_field('additional_authors', 731, false), 'The submitted repair must not be partially applied by preflight.');
    }

    public function test_acf_field_write_failure_can_leave_a_duplicate_after_valid_submission(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(733, 'publish', [734, 735]);
        $GLOBALS['vgpc_test_posts'][734]->post_status = 'publish';
        $GLOBALS['vgpc_test_posts'][735]->post_status = 'publish';
        update_field('primary_author', 734, 733);
        update_field('additional_authors', [735], 733);
        $_POST = ['post_ID' => 733, 'post_status' => 'publish', 'acf' => [
            'field_vg_article_primary_author' => 735,
            'field_vg_article_additional_authors' => [734],
        ]];

        $data = ['ID' => 733, 'post_type' => 'post', 'post_status' => 'publish'];
        vgpc_test_same($data, apply_filters('wp_insert_post_data', $data, ['ID' => 733]), 'The old and submitted complete projections are both valid.');
        $admin->validate_actual_acf_save(733);
        // ACF validates the submitted fields before its save loop, then writes
        // each field independently. Model the first successful update and a
        // later per-field persistence failure, without a cross-field rollback.
        update_field('primary_author', 735, 733);
        $projection = (new PersonRelationshipSourceReader())->read(733);
        vgpc_test_true(is_array($projection), 'The first independently persisted ACF field must be readable.');
        $partial = (new PersonRelationshipValidator())->validate_projection($projection, 'publish');
        vgpc_test_true(is_wp_error($partial), 'A later ACF field write failure can leave the published stored projection invalid.');
        vgpc_test_same(735, get_field('primary_author', 733, false), 'The successful first field write remains authoritative.');
        vgpc_test_same([735], get_field('additional_authors', 733, false), 'The failed later field retains its prior value and creates a cross-role duplicate.');
        $admin->release_source_write_lock(733);
    }

    public function test_rest_flushes_cached_fields_after_lock_and_validates_fresh_values(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(721, 'draft', [722, 723]);
        $GLOBALS['vgpc_test_posts'][722]->post_status = 'draft';
        $GLOBALS['vgpc_test_posts'][723]->post_status = 'publish';
        update_field('primary_author', 722, 721);
        $GLOBALS['vgpc_test_enable_query_cache'] = true;
        vgpc_test_same(722, get_field('primary_author', 721, false), 'Prime a stale ACF value.');
        update_field('primary_author', 723, 721);
        $lock_seen = false;
        $GLOBALS['wpdb']->get_var_handler = static function (string $query) use (&$lock_seen): string {
            if (str_contains($query, 'GET_LOCK')) { $lock_seen = true; return '1'; }
            return '1';
        };
        $request = new VGPC_TestRestRequest(['status' => 'publish']);
        $result = $admin->validate_rest_source((object) ['ID' => 721, 'post_type' => 'post', 'post_status' => 'publish'], $request);

        vgpc_test_true(! is_wp_error($result), 'REST must validate the fresh stored Person after taking the source lock.');
        vgpc_test_true($lock_seen, 'REST validation must acquire the source lock before reading ACF values.');
        vgpc_test_true(
            array_search('get_lock', $GLOBALS['vgpc_test_database_sequence'], true) < array_search('acf_flush:primary_author', $GLOBALS['vgpc_test_database_sequence'], true),
            'REST must acquire the source lock before flushing ACF field caches.'
        );
        vgpc_test_same(723, get_field('primary_author', 721, false), 'REST validation must flush the stale field cache.');
        $admin->release_source_write_lock(721);
    }

    public function test_scheduled_validation_releases_source_lock_after_each_publish_transition(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(724, 'future', [725]);
        $GLOBALS['vgpc_test_posts'][725]->post_status = 'publish';
        update_field('primary_author', 725, 724);
        $get_lock_calls = 0;
        $GLOBALS['wpdb']->get_var_handler = static function (string $query) use (&$get_lock_calls): string {
            if (str_contains($query, 'GET_LOCK')) {
                $get_lock_calls++;
                return $get_lock_calls === 2 ? '0' : '1';
            }
            return '1';
        };

        vgpc_test_same(true, apply_filters('voelgoed/platform_core/future_post_can_publish', true, 724, get_post(724)), 'A valid scheduled source may proceed.');
        $other_writer = new PersonRelationshipSourceLock();
        vgpc_test_true(! $other_writer->acquire(724), 'A competing writer must not enter between validation and the scheduled publish call.');
        $GLOBALS['vgpc_test_posts'][724]->post_status = 'publish';
        do_action('transition_post_status', 'publish', 'future', get_post(724));
        vgpc_test_true($other_writer->acquire(724), 'The lock must release at this publication transition, before later scheduled events run in the same cron request.');
        $other_writer->release(724);
        do_action('shutdown');
        vgpc_test_true($other_writer->acquire(724), 'The lock must remain available after deferred integrity checks finish.');
        $other_writer->release(724);
    }

    public function test_editor_publish_transition_keeps_source_lock_through_acf_persistence(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(726, 'draft', []);
        $_POST = ['post_ID' => 726, 'post_status' => 'publish', 'acf' => []];
        $lock_held = false;
        $GLOBALS['wpdb']->get_var_handler = static function (string $query) use (&$lock_held): string {
            if (str_contains($query, 'GET_LOCK')) {
                if ($lock_held) return '0';
                $lock_held = true;
                return '1';
            }
            if (str_contains($query, 'RELEASE_LOCK')) {
                $lock_held = false;
                return '1';
            }
            return '1';
        };
        $admin->validate_actual_acf_save(726);
        $GLOBALS['vgpc_test_posts'][726]->post_status = 'publish';
        do_action('transition_post_status', 'publish', 'draft', get_post(726));

        $other_writer = new PersonRelationshipSourceLock();
        vgpc_test_true(! $other_writer->acquire(726), 'An editor transition must not release its source lock before ACF finishes writing fields.');
        $admin->release_source_write_lock(726);
        vgpc_test_true($other_writer->acquire(726), 'The editor save lock must release after ACF persistence completes.');
        $other_writer->release(726);
    }

    public function test_rejects_malformed_and_duplicate_field_submissions(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(10, 'draft', [20, 21]);
        $_POST = ['post_ID' => 10, 'post_status' => 'draft', 'acf' => [
            'field_vg_article_primary_author' => 20,
            'field_vg_article_additional_authors' => ['not-an-id'],
        ]];

        $valid = apply_filters('acf/validate_value/key=field_vg_article_additional_authors', true, ['not-an-id'], ['key' => 'field_vg_article_additional_authors', 'name' => 'additional_authors'], 'acf[field_vg_article_additional_authors]');
        vgpc_test_true(is_string($valid), 'Malformed submitted values must be rejected with an ACF error.');

        $_POST['acf']['field_vg_article_additional_authors'] = [20];
        $valid = apply_filters('acf/validate_value/key=field_vg_article_additional_authors', true, [20], ['key' => 'field_vg_article_additional_authors', 'name' => 'additional_authors'], 'acf[field_vg_article_additional_authors]');
        vgpc_test_true(is_string($valid), 'A Person submitted in two roles must be rejected.');
    }

    public function test_allows_valid_draft_with_unpublished_person(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(11, 'draft', [22]);
        $_POST = ['post_ID' => 11, 'post_status' => 'draft', 'acf' => [
            'field_vg_article_primary_author' => 22,
        ]];

        $valid = apply_filters('acf/validate_value/key=field_vg_article_primary_author', true, 22, ['key' => 'field_vg_article_primary_author', 'name' => 'primary_author'], 'acf[field_vg_article_primary_author]');
        vgpc_test_same(true, $valid, 'A draft source may reference a draft Person.');
    }

    public function test_actual_acf_save_locks_writer_and_marks_before_acf_field_writes(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(111, 'draft', [222]);
        $_POST = ['post_ID' => 111, 'post_status' => 'draft'];
        $lock_calls = 0;
        $GLOBALS['wpdb']->get_var_handler = static function (string $query) use (&$lock_calls): string {
            if (str_contains($query, 'GET_LOCK')) return ++$lock_calls === 1 ? '1' : '0';
            return '1';
        };
        $_POST['acf'] = ['field_vg_article_primary_author' => 222];
        $admin->validate_actual_acf_save(111);
        vgpc_test_same('1', (string) get_post_meta(111, PersonRelationshipContract::DIRTY_META_KEY, true), 'The dirty marker must be persisted before field writes start.');
        $second = new PersonRelationshipAdmin();
        try {
            $second->validate_actual_acf_save(111);
            throw new RuntimeException('A concurrent actual ACF save should be blocked.');
        } catch (RuntimeException $error) {
            vgpc_test_true(str_contains($error->getMessage(), 'being edited by another request'), 'A concurrent writer must fail closed while another source save owns the lock.');
        }
        $admin->release_source_write_lock();
        vgpc_test_same('1', (string) get_post_meta(111, PersonRelationshipContract::DIRTY_META_KEY, true), 'An interrupted save must leave reverse reads fail-closed until repair.');
    }

    public function test_invalid_draft_acf_save_is_rejected_before_field_persistence(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(112, 'draft', [224]);
        $_POST = ['post_ID' => 112, 'post_status' => 'draft', 'acf' => [
            'field_vg_article_primary_author' => 224,
            'field_vg_article_additional_authors' => [224],
        ]];

        try {
            do_action('acf/save_post', 112);
            throw new RuntimeException('A duplicate draft relationship should be rejected before ACF persists it.');
        } catch (RuntimeException $error) {
            vgpc_test_true(str_contains($error->getMessage(), 'may hold only one role'), 'Actual save validation must report the duplicate role invariant.');
        }
        vgpc_test_same([], $GLOBALS['vgpc_test_meta'][112] ?? [], 'Invalid draft data must stop before ACF field persistence.');
    }

    public function test_source_type_change_serializes_anomaly_cleanup_until_after_insert(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(114, 'draft', [], 'post');
        $GLOBALS['vgpc_test_options'][PersonRelationshipSchema::VERSION_OPTION] = PersonRelationshipSchema::SCHEMA_VERSION;
        $lock_attempt = 0;
        $GLOBALS['wpdb']->get_var_handler = static function (string $query) use (&$lock_attempt): string {
            if (str_contains($query, 'SHOW TABLES')) return 'wp_vg_person_relationship_index';
            if (str_contains($query, 'GET_LOCK')) return ++$lock_attempt === 2 ? '0' : '1';
            return '1';
        };

        $data = ['ID' => 114, 'post_type' => 'page', 'post_status' => 'draft'];
        vgpc_test_same($data, apply_filters('wp_insert_post_data', $data, ['ID' => 114]), 'A source type transition must pass while holding its source lock.');
        $cleanup = $this->indexer()->remove_stale_source(114);
        vgpc_test_true(is_wp_error($cleanup) && $cleanup->get_error_code() === 'source_lock_failed', 'Anomaly cleanup must wait while the source type transition owns the lock.');

        $GLOBALS['vgpc_test_posts'][114]->post_type = 'page';
        do_action('shutdown');
        $cleanup = $this->indexer()->remove_stale_source(114);
        vgpc_test_same(true, $cleanup, 'After the transition finishes, cleanup may recheck and remove rows for the unsupported source.');
    }

    public function test_scheduled_publish_filter_blocks_invalid_and_allows_valid_sources(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(12, 'future', [23]);
        $GLOBALS['vgpc_test_meta'][12]['primary_author'] = 23;

        vgpc_test_same(false, apply_filters('voelgoed/platform_core/future_post_can_publish', true, 12, get_post(12)), 'A scheduled Article with a draft Person must stay unpublished.');
        $GLOBALS['vgpc_test_posts'][23]->post_status = 'publish';
        vgpc_test_same(true, apply_filters('voelgoed/platform_core/future_post_can_publish', true, 12, get_post(12)), 'A scheduled Article with a published Person may publish.');
    }

    public function test_scheduled_courses_use_the_same_publication_rules(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(13, 'future', [24], 'mto-course');
        $GLOBALS['vgpc_test_meta'][13]['lead_instructor'] = 24;

        vgpc_test_same(false, apply_filters('voelgoed/platform_core/future_post_can_publish', true, 13, get_post(13)), 'A scheduled Course with a draft Person must stay unpublished.');
        $GLOBALS['vgpc_test_posts'][24]->post_status = 'publish';
        vgpc_test_same(true, apply_filters('voelgoed/platform_core/future_post_can_publish', true, 13, get_post(13)), 'A scheduled Course with a published Person may publish.');
    }

    public function test_invalid_scheduled_source_reports_and_queues_only_one_retry(): void
    {
        vgpc_test_reset();
        $admin = $this->admin();
        $this->source_and_people(113, 'future', [223]);
        $GLOBALS['vgpc_test_meta'][113]['primary_author'] = 223;
        vgpc_test_same(false, $admin->future_post_can_publish(true, 113, get_post(113)), 'Invalid scheduled source must remain unpublished.');
        vgpc_test_same(1, count($GLOBALS['vgpc_test_scheduled_events']), 'The first block should queue one retry.');
        vgpc_test_same(false, $admin->future_post_can_publish(true, 113, get_post(113)), 'An unresolved relationship must remain blocked on retry.');
        vgpc_test_same(1, count($GLOBALS['vgpc_test_scheduled_events']), 'A persistent validation error must not create an endless retry loop.');
    }

    public function test_invalid_scheduled_source_is_not_sent_to_wordpress_publisher(): void
    {
        $person_admin = $this->admin();
        $person_admin->register();
        $product_admin = new ProductContributionAdmin();
        $product_admin->register();
        $this->source_and_people(16, 'future', [26]);
        $GLOBALS['vgpc_test_meta'][16]['primary_author'] = 26;
        $GLOBALS['vgpc_test_core_future_publish_calls'] = [];

        $product_admin->handle_future_publication(16);

        vgpc_test_same([], $GLOBALS['vgpc_test_core_future_publish_calls'], 'Invalid scheduled Article must not reach the WordPress publisher.');
    }

    public function test_pre_save_validation_blocks_invalid_publication_but_accepts_valid_schedule(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(17, 'draft', [27]);
        $_POST = ['post_ID' => 17, 'acf' => ['field_vg_article_primary_author' => 27]];

        try {
            apply_filters('wp_insert_post_data', ['ID' => 17, 'post_type' => 'post', 'post_status' => 'publish'], ['ID' => 17]);
            throw new RuntimeException('Invalid publication should have been blocked.');
        } catch (RuntimeException $error) {
            vgpc_test_true(str_contains($error->getMessage(), 'Person 27'), 'Pre-save validation must return an actionable Person error.');
        }

        $GLOBALS['vgpc_test_posts'][27]->post_status = 'publish';
        $data = ['ID' => 17, 'post_type' => 'post', 'post_status' => 'future'];
        vgpc_test_same($data, apply_filters('wp_insert_post_data', $data, ['ID' => 17]), 'A valid scheduled Article must pass source-level validation.');
    }

    public function test_new_source_can_pass_pre_save_validation_before_wordpress_assigns_an_id(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(28, 'publish', [29]);
        $GLOBALS['vgpc_test_posts'][29]->post_status = 'publish';
        $_POST = ['post_type' => 'mto-course', 'acf' => ['field_vg_course_lead_instructor' => 29]];
        $data = ['post_type' => 'mto-course', 'post_status' => 'future'];

        vgpc_test_same($data, apply_filters('wp_insert_post_data', $data, []), 'A valid new Course must pass pre-save validation before it has a source ID.');
    }

    public function test_unrelated_future_posts_still_delegate_to_wordpress(): void
    {
        $person_admin = $this->admin();
        $person_admin->register();
        $product_admin = new ProductContributionAdmin();
        $product_admin->register();
        $GLOBALS['vgpc_test_posts'][14] = (object) ['ID' => 14, 'post_type' => 'page', 'post_status' => 'future'];
        $GLOBALS['vgpc_test_core_future_publish_calls'] = [];

        $product_admin->handle_future_publication(14);

        vgpc_test_same([14], $GLOBALS['vgpc_test_core_future_publish_calls'], 'Unrelated future posts must continue through the WordPress publisher.');
    }

    public function test_indexer_failure_does_not_abort_source_save_and_delete_cleans_rows(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(15, 'draft', [25]);
        $GLOBALS['vgpc_test_meta'][15]['primary_author'] = 25;
        $GLOBALS['vgpc_test_options'][PersonRelationshipSchema::VERSION_OPTION] = PersonRelationshipSchema::SCHEMA_VERSION;
        $GLOBALS['wpdb']->get_var_handler = static fn (string $query): string => str_contains($query, 'SHOW TABLES') ? 'wp_vg_person_relationship_index' : '1';
        $GLOBALS['wpdb']->fail_insert = true;

        do_action('acf/save_post', 15);

        vgpc_test_same('draft', get_post(15)->post_status, 'Projection failure must not change the saved source status.');
        vgpc_test_true((new PersonRelationshipIndexer(new PersonRelationshipSourceLock(), new PersonRelationshipSourceReader(), new PersonRelationshipValidator(), new PersonRelationshipRepository(new PersonRelationshipSchema()), static function (): void {}))->is_dirty(15), 'Projection failure must retain the dirty marker.');
        $_GET['post'] = 15;
        ob_start();
        do_action('admin_notices');
        $notice = (string) ob_get_clean();
        vgpc_test_true(str_contains($notice, 'reverse index could not be updated'), 'Projection failure must give the editing operator a safe notice.');

        $GLOBALS['wpdb']->fail_insert = false;
        $GLOBALS['wpdb']->fail_query = false;
        $GLOBALS['wpdb']->query_handler = null;
        $deleted_source = get_post(15);
        do_action('before_delete_post', 15, $deleted_source);
        vgpc_test_true((bool) array_filter($GLOBALS['wpdb']->queries, static fn (array $query): bool => str_contains($query['query'], 'DELETE FROM wp_vg_person_relationship_index')), 'Permanent deletion must remove derived source rows.');
        $GLOBALS['wpdb']->fail_query = true;
        wp_delete_post(15, true);
        vgpc_test_true(! isset($GLOBALS['vgpc_test_meta'][15]), 'WordPress permanent deletion must remove source metadata after the pre-delete hook.');
        vgpc_test_true(count(array_filter($GLOBALS['wpdb']->queries, static fn (array $query): bool => str_contains($query['query'], 'DELETE FROM wp_vg_person_relationship_index'))) >= 3, 'The post-meta deletion hook must retry derived-row cleanup after an earlier cleanup failure.');
    }

    public function test_rest_publication_validates_persisted_acf_fields_before_core_save(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(31, 'draft', [32]);
        $_POST = [];
        $GLOBALS['vgpc_test_meta'][31]['primary_author'] = 32;
        $request = new VGPC_TestRestRequest(['status' => 'publish']);

        // WP_REST_Posts_Controller dispatches rest_pre_insert_{post_type} with two args.
        $result = apply_filters('rest_pre_insert_post', (object) ['ID' => 31, 'post_type' => 'post', 'post_status' => 'publish'], $request);
        vgpc_test_true(is_wp_error($result), 'REST publication with a draft persisted Person must fail before core saves the post.');
        vgpc_test_true(str_contains($result->get_error_message(), 'Person 32'), 'REST error must identify the invalid Person.');
    }

    public function test_rest_update_rejects_unpersisted_relationship_overrides(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(33, 'draft', [34]);
        $GLOBALS['vgpc_test_meta'][33]['primary_author'] = 34;
        $request = new VGPC_TestRestRequest(['acf' => [
            'primary_author' => 34,
            'additional_authors' => [34],
        ]]);

        $result = apply_filters('rest_pre_insert_post', (object) ['ID' => 33, 'post_type' => 'post', 'post_status' => 'draft'], $request);
        vgpc_test_true(is_wp_error($result), 'REST update with a duplicate assignment must be rejected.');
        vgpc_test_same('unsupported_person_relationship_rest_write', $result->get_error_code(), 'REST must reject relationship overrides that ACF does not persist.');
    }

    public function test_rest_course_accepts_valid_scheduled_field_key_payload(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(35, 'future', [36], 'mto-course');
        $GLOBALS['vgpc_test_posts'][36]->post_status = 'publish';
        $GLOBALS['vgpc_test_meta'][35]['lead_instructor'] = 36;
        $prepared_post = (object) ['ID' => 35, 'post_type' => 'mto-course', 'post_status' => 'future'];
        $request = new VGPC_TestRestRequest(['status' => 'future']);

        vgpc_test_same($prepared_post, apply_filters('rest_pre_insert_mto-course', $prepared_post, $request), 'A valid scheduled Course REST request must pass when stored ACF fields are valid.');
    }

    public function test_rest_rejects_fields_that_acf_does_not_persist(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(37, 'draft', [38]);
        $request = new VGPC_TestRestRequest(['status' => 'draft', 'acf' => ['primary_author' => 38]]);
        $prepared = (object) ['ID' => 37, 'post_type' => 'post', 'post_status' => 'draft'];
        $result = apply_filters('rest_pre_insert_post', $prepared, $request);
        vgpc_test_true(is_wp_error($result), 'REST must reject relationship fields excluded from ACF REST persistence.');
        vgpc_test_same('unsupported_person_relationship_rest_write', $result->get_error_code(), 'REST override rejection must identify the unsupported write path.');
        vgpc_test_same([], $GLOBALS['vgpc_test_meta'][37] ?? [], 'Validation must not imply persistence of unconfigured ACF REST fields.');
    }

    public function test_projection_and_delete_failures_are_reported(): void
    {
        $admin = $this->admin();
        $admin->register();
        vgpc_test_true(isset($GLOBALS['vgpc_test_actions']['admin_notices']), 'Projection failures must register an operator notice.');
        vgpc_test_true(isset($GLOBALS['vgpc_test_actions']['acf/save_post']), 'Projection handler remains registered.');
    }

    private function admin(): PersonRelationshipAdmin
    {
        return new PersonRelationshipAdmin($this->indexer());
    }

    private function indexer(): PersonRelationshipIndexer
    {
        $schema = new PersonRelationshipSchema();
        return new PersonRelationshipIndexer(new PersonRelationshipSourceLock(), new PersonRelationshipSourceReader(), new PersonRelationshipValidator(), new PersonRelationshipRepository($schema), static function (): void {});
    }

    private function source_and_people(int $source_id, string $status, array $person_ids, string $source_type = 'post'): void
    {
        acf_add_local_field_group(['key' => 'test_person_relationship_admin_fields', 'fields' => [
            ['key' => 'field_vg_article_primary_author', 'name' => 'primary_author'],
            ['key' => 'field_vg_article_additional_authors', 'name' => 'additional_authors'],
            ['key' => 'field_vg_article_expert_contributors', 'name' => 'expert_contributors'],
            ['key' => 'field_vg_article_reviewed_by', 'name' => 'reviewed_by'],
            ['key' => 'field_vg_course_lead_instructor', 'name' => 'lead_instructor'],
            ['key' => 'field_vg_course_additional_instructors', 'name' => 'additional_instructors'],
            ['key' => 'field_vg_course_experts', 'name' => 'experts'],
        ]]);
        $GLOBALS['vgpc_test_posts'][$source_id] = (object) ['ID' => $source_id, 'post_type' => $source_type, 'post_status' => $status];
        foreach ($person_ids as $person_id) {
            $GLOBALS['vgpc_test_posts'][$person_id] = (object) ['ID' => $person_id, 'post_type' => 'person', 'post_status' => 'draft'];
        }
    }
}
