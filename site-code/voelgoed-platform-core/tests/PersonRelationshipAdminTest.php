<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Person\PersonRelationshipAdmin;
use Voelgoed\PlatformCore\Person\PersonRelationshipContract;
use Voelgoed\PlatformCore\Person\PersonRelationshipIndexer;
use Voelgoed\PlatformCore\Person\PersonRelationshipRepository;
use Voelgoed\PlatformCore\Person\PersonRelationshipSchema;
use Voelgoed\PlatformCore\Person\PersonRelationshipSourceLock;
use Voelgoed\PlatformCore\Person\PersonRelationshipSourceReader;
use Voelgoed\PlatformCore\Person\PersonRelationshipService;
use Voelgoed\PlatformCore\Person\PersonRelationshipValidator;
use Voelgoed\PlatformCore\Product\ProductContributionAdmin;

if (! class_exists('WP_Hook')) {
    /** Test shim for the WP_Hook priority iteration used in this regression. */
    final class WP_Hook
    {
        private array $callbacks = [];

        public function add_filter(string $hook_name, callable $callback, int $priority, int $accepted_args): void
        {
            $this->callbacks[$priority][] = ['callback' => $callback, 'accepted_args' => $accepted_args];
            ksort($this->callbacks, SORT_NUMERIC);
        }

        public function do_action(array $args): void
        {
            // WP_Hook snapshots active priorities. A newly added lower priority
            // cannot run after iteration has already reached PHP_INT_MAX.
            $priorities = array_keys($this->callbacks);
            foreach ($priorities as $priority) {
                foreach ($this->callbacks[$priority] as $entry) {
                    ($entry['callback'])(...array_slice($args, 0, $entry['accepted_args']));
                }
            }
        }
    }
}
if (! function_exists('_wp_filter_build_unique_id')) {
    function _wp_filter_build_unique_id(string $hook_name, mixed $callback, int $priority): string
    {
        if (is_string($callback)) return $callback;
        if (is_array($callback)) return (is_object($callback[0]) ? spl_object_hash($callback[0]) : (string) $callback[0]) . '::' . $callback[1];
        return spl_object_hash($callback);
    }
}

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
        vgpc_test_true(isset($GLOBALS['vgpc_test_actions']['save_post']), 'Supported source type transitions must recover when wp_after_insert_post is suppressed.');
        vgpc_test_true(in_array(PHP_INT_MIN, array_column($GLOBALS['vgpc_test_actions']['save_post'], 'priority'), true), 'Same-source save nesting must be tracked before other save_post callbacks run.');
        vgpc_test_true(in_array(1001, array_column($GLOBALS['vgpc_test_actions']['save_post'], 'priority'), true), 'No-ACF post saves must release their source lock after the type-transition callback.');
        vgpc_test_true(isset($GLOBALS['vgpc_test_actions']['wp_after_insert_post']), 'The post-insert transition callback must remain registered as a fallback.');
        vgpc_test_true(isset($GLOBALS['vgpc_test_actions']['acf/save_post']), 'ACF saves must project at priority 20.');
        vgpc_test_same(1, min(array_column($GLOBALS['vgpc_test_actions']['acf/save_post'], 'priority')), 'Actual ACF saves must validate before ACF writes its fields.');
        vgpc_test_true(isset($GLOBALS['vgpc_test_actions']['shutdown']), 'Source write locks must release after editor and REST persistence has completed.');
        vgpc_test_true(isset($GLOBALS['vgpc_test_actions']['transition_post_status']), 'Published source transitions must be checked for bypassed relationship validation.');
        vgpc_test_true(isset($GLOBALS['vgpc_test_actions']['rest_after_insert_post']), 'REST Article saves must project after REST metadata persistence.');
        vgpc_test_true(isset($GLOBALS['vgpc_test_actions']['rest_after_insert_mto-course']), 'REST Course saves must project after REST metadata persistence.');
        vgpc_test_true(isset($GLOBALS['vgpc_test_actions']['before_delete_post']), 'Permanent deletion must clean derived rows.');
        vgpc_test_true(isset($GLOBALS['vgpc_test_filters']['voelgoed/platform_core/future_post_can_publish']), 'Scheduled publication must use the shared filter seam.');
        vgpc_test_true(isset($GLOBALS['vgpc_test_actions']['publish_future_post']), 'Scheduled publication locks must release after core returns, including no-transition returns.');
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

    public function test_editor_publish_rejects_unsafe_partial_role_move_before_post_write(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(740, 'publish', [741]);
        $GLOBALS['vgpc_test_posts'][741]->post_status = 'publish';
        update_field('primary_author', 741, 740);
        $_POST = ['post_ID' => 740, 'post_status' => 'publish', 'acf' => [
            'field_vg_article_primary_author' => null,
            'field_vg_article_expert_contributors' => [741],
        ]];
        try {
            apply_filters('wp_insert_post_data', ['ID' => 740, 'post_type' => 'post', 'post_status' => 'publish'], ['ID' => 740]);
            throw new RuntimeException('An unsafe partial role move must fail before WordPress writes the post.');
        } catch (RuntimeException $error) {
            vgpc_test_true(str_contains($error->getMessage(), 'possible partial relationship save'), 'The partial-state validator must reject the cross-role overlap.');
        }
        vgpc_test_same(741, get_field('primary_author', 740, false), 'Rejected preflight must leave persisted ACF data unchanged.');
    }

    public function test_meta_input_cannot_write_governed_values_or_acf_reference_keys(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(755, 'draft', [756]);
        foreach ([
            [[], ['post_type' => 'post', 'post_status' => 'draft'], ['primary_author' => 756]],
            [['ID' => 755], ['ID' => 755, 'post_type' => 'post', 'post_status' => 'draft'], ['_additional_authors' => 'field_vg_article_additional_authors']],
            [['ID' => 755], ['ID' => 755, 'post_type' => 'post', 'post_status' => 'draft'], ['reviewed_by' => 756]],
            [['ID' => 762], ['ID' => 762, 'post_type' => 'mto-course', 'post_status' => 'draft'], ['_experts' => 'field_vg_course_experts']],
        ] as [$postarr, $data, $meta_input]) {
            $postarr['meta_input'] = $meta_input;
            try {
                apply_filters('wp_insert_post_data', $data, $postarr);
                throw new RuntimeException('Direct governed metadata writes must fail before WordPress persists them.');
            } catch (RuntimeException $error) {
                vgpc_test_true(str_contains($error->getMessage(), 'supported ACF editor flow'), 'The direct metadata rejection must identify the supported write path.');
            }
        }
    }

    public function test_invalid_published_source_can_demote_without_publish_subset_validation(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(742, 'publish', [743]);
        update_field('primary_author', 743, 742);
        update_field('additional_authors', [743], 742);
        $_POST = ['post_ID' => 742, 'post_status' => 'draft', 'acf' => []];
        $data = ['ID' => 742, 'post_type' => 'post', 'post_status' => 'draft'];
        vgpc_test_same($data, apply_filters('wp_insert_post_data', $data, ['ID' => 742]), 'Demotion must proceed so the source can be repaired.');
    }

    public function test_status_only_acf_demotion_skips_invalid_relationship_validation(): void
    {
        $admin = $this->admin();
        $this->source_and_people(743, 'draft', [744]);
        $GLOBALS['vgpc_test_posts'][743]->post_status = 'draft';
        update_field('primary_author', 744, 743);
        update_field('additional_authors', [744], 743);
        $_POST = ['post_ID' => 743, 'post_status' => 'draft', 'acf' => []];
        $admin->validate_actual_acf_save(743);
        vgpc_test_same('1', (string) get_post_meta(743, PersonRelationshipContract::DIRTY_META_KEY, true), 'A status-only demotion must mark the derived projection for repair without validating malformed published relationships.');
        $admin->release_source_write_lock(743);
    }

    public function test_populated_unchanged_acf_fields_allow_invalid_source_demotion_repair_and_republish(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(757, 'publish', [758, 759]);
        $GLOBALS['vgpc_test_posts'][758]->post_status = 'publish';
        $GLOBALS['vgpc_test_posts'][759]->post_status = 'publish';
        update_field('primary_author', 758, 757);
        update_field('additional_authors', [758], 757);

        $_POST = ['post_ID' => 757, 'post_status' => 'draft', 'acf' => [
            'field_vg_article_primary_author' => 758,
            'field_vg_article_additional_authors' => [758],
        ]];
        $demotion = ['ID' => 757, 'post_type' => 'post', 'post_status' => 'draft'];
        vgpc_test_same($demotion, apply_filters('wp_insert_post_data', $demotion, ['ID' => 757]), 'WordPress must allow the non-public status write before the ACF save callback.');
        $GLOBALS['vgpc_test_posts'][757]->post_status = 'draft';
        $admin->validate_actual_acf_save(757);
        vgpc_test_same('1', (string) get_post_meta(757, PersonRelationshipContract::DIRTY_META_KEY, true), 'An unchanged populated ACF payload must permit demotion of an invalid source and mark the index dirty.');
        $admin->release_source_write_lock(757);

        $_POST['acf']['field_vg_article_additional_authors'] = [];
        $admin->validate_actual_acf_save(757);
        update_field('additional_authors', [], 757);
        $admin->release_source_write_lock(757);
        vgpc_test_same([], get_field('additional_authors', 757, false), 'A valid relationship repair must persist while the source is draft.');

        $_POST['post_status'] = 'publish';
        $publish = ['ID' => 757, 'post_type' => 'post', 'post_status' => 'publish'];
        vgpc_test_same($publish, apply_filters('wp_insert_post_data', $publish, ['ID' => 757]), 'The repaired relationship state must pass the normal publish preflight.');
        $admin->release_source_write_lock(757);
    }

    public function test_status_only_editor_demotion_ignores_malformed_omitted_relationships(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(7590, 'publish', [7591]);
        update_field('primary_author', 7591, 7590);
        update_field('additional_authors', 'malformed-stored-value', 7590);
        $GLOBALS['vgpc_test_apply_wp_update_filter'] = true;
        $GLOBALS['vgpc_test_wp_update_post_hooks'] = true;
        $_POST = ['post_ID' => 7590, 'post_status' => 'draft', 'acf' => [
            'field_vg_article_primary_author' => 7591,
        ]];

        vgpc_test_same(7590, wp_update_post(['ID' => 7590, 'post_status' => 'draft'], true), 'The WordPress status write must finish before ACF processes the demotion payload.');
        do_action('acf/save_post', 7590);
        vgpc_test_same('malformed-stored-value', get_field('additional_authors', 7590, false), 'Demotion must leave malformed omitted relationships untouched.');
        vgpc_test_same(7591, get_field('primary_author', 7590, false), 'An unchanged submitted primary author must remain untouched.');
        vgpc_test_same('1', (string) get_post_meta(7590, PersonRelationshipContract::DIRTY_META_KEY, true), 'Demotion must leave the derived index marked for recovery.');
        $admin->release_source_write_lock(7590);
    }

    public function test_changed_invalid_relationships_do_not_use_the_unchanged_demotion_exception(): void
    {
        $admin = $this->admin();
        $this->source_and_people(760, 'draft', [761]);
        update_field('primary_author', 761, 760);
        $_POST = ['post_ID' => 760, 'post_status' => 'draft', 'acf' => ['field_vg_article_primary_author' => 999]];
        try {
            $admin->validate_actual_acf_save(760);
            throw new RuntimeException('A changed invalid value must not pass through the status-only demotion exception.');
        } catch (RuntimeException $error) {
            vgpc_test_true(str_contains($error->getMessage(), 'Person 999'), 'Changed invalid values must fail ordinary projection validation.');
        }
        vgpc_test_same(761, get_field('primary_author', 760, false), 'Rejected relationship changes must preserve the stored value.');
        $admin->release_source_write_lock(760);
    }

    public function test_rest_status_only_demotion_accepts_invalid_published_relationships(): void
    {
        $admin = $this->admin();
        $this->source_and_people(750, 'publish', [751]);
        update_field('primary_author', 751, 750);
        update_field('additional_authors', [751], 750);
        $prepared = (object) ['ID' => 750, 'post_type' => 'post', 'post_status' => 'draft'];
        $result = $admin->validate_rest_source($prepared, new VGPC_TestRestRequest([]));
        vgpc_test_same($prepared, $result, 'REST must allow status-only demotion so an invalid published source can enter the repair workflow.');
        $admin->release_source_write_lock(750);
    }

    public function test_new_source_is_reread_under_lock_before_acf_field_writes(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(0, 'publish', [746, 747]);
        $GLOBALS['vgpc_test_posts'][746]->post_status = 'publish';
        $GLOBALS['vgpc_test_posts'][747]->post_status = 'publish';
        $_POST = ['post_status' => 'future', 'post_type' => 'post', 'acf' => ['field_vg_article_expert_contributors' => [746]]];
        $get_lock_calls = 0;
        $lock_held = false;
        $GLOBALS['wpdb']->get_var_handler = static function (string $query) use (&$lock_held, &$get_lock_calls): string {
            if (str_contains($query, 'GET_LOCK')) { $get_lock_calls++; if ($lock_held) return '0'; $lock_held = true; return '1'; }
            if (str_contains($query, 'RELEASE_LOCK')) { $lock_held = false; return '1'; }
            return '1';
        };
        $pre_insert = ['post_type' => 'post', 'post_status' => 'future'];
        vgpc_test_same($pre_insert, apply_filters('wp_insert_post_data', $pre_insert, []), 'A new source may pass the pre-insert validation before WordPress assigns its ID.');
        vgpc_test_same(0, $get_lock_calls, 'The pre-insert phase has no source ID to lock.');
        // WordPress inserts the post row, then another writer persists a
        // primary author before this request reaches ACF save_post priority 1.
        $this->source_and_people(745, 'future', [746, 747]);
        update_field('primary_author', 746, 745);
        $_POST['post_ID'] = 745;
        try {
            $admin->validate_actual_acf_save(745);
            throw new RuntimeException('The post-ID callback must catch a relationship added after the pre-insert check.');
        } catch (RuntimeException $error) {
            vgpc_test_true(str_contains($error->getMessage(), 'possible partial relationship save'), 'The post-ID callback must enumerate fresh old/new states before ACF writes.');
        }
        vgpc_test_same(746, get_field('primary_author', 745, false), 'Rejected callback validation must preserve the concurrent writer relationship.');
        vgpc_test_true(! $lock_held, 'A rejected actual-save validation must release the source lock.');
    }

    public function test_scheduled_event_releases_lock_when_core_returns_without_transition(): void
    {
        $admin = $this->admin();
        $this->source_and_people(748, 'future', [749]);
        $GLOBALS['vgpc_test_posts'][749]->post_status = 'publish';
        update_field('primary_author', 749, 748);
        $lock_held = false;
        $GLOBALS['wpdb']->get_var_handler = static function (string $query) use (&$lock_held): string {
            if (str_contains($query, 'GET_LOCK')) { if ($lock_held) return '0'; $lock_held = true; return '1'; }
            if (str_contains($query, 'RELEASE_LOCK')) { $lock_held = false; return '1'; }
            return '1';
        };
        vgpc_test_same(true, $admin->future_post_can_publish(true, 748, get_post(748)), 'The first event may acquire and validate the future source.');
        $admin->release_future_publish_lock_after_core(748);
        $other_writer = new PersonRelationshipSourceLock();
        vgpc_test_true($other_writer->acquire(748), 'A stale or early core return must release the source lock for later cron events.');
        $other_writer->release(748);
    }

    public function test_supported_article_to_course_transition_validates_target_fields_and_holds_lock(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(750, 'publish', [751, 752, 753, 754]);
        $GLOBALS['vgpc_test_posts'][751]->post_status = 'publish';
        $GLOBALS['vgpc_test_posts'][752]->post_status = 'publish';
        $GLOBALS['vgpc_test_posts'][753]->post_status = 'publish';
        $GLOBALS['vgpc_test_posts'][754]->post_status = 'publish';
        update_field('primary_author', 751, 750);
        update_field('lead_instructor', 752, 750);
        update_field('experts', [753], 750);
        $_POST = ['post_ID' => 750, 'post_status' => 'publish', 'acf' => ['field_vg_course_lead_instructor' => 754]];
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

        $data = ['ID' => 750, 'post_type' => 'mto-course', 'post_status' => 'publish'];
        vgpc_test_same($data, apply_filters('wp_insert_post_data', $data, ['ID' => 750]), 'The transition must validate target-type fields before WordPress writes the new type.');
        vgpc_test_same(752, get_field('lead_instructor', 750, false), 'Preflight must leave target fields unchanged until ACF writes them.');
        vgpc_test_same([753], get_field('experts', 750, false), 'An omitted target field must retain its persisted value during the projection.');

        $other_writer = new PersonRelationshipSourceLock();
        vgpc_test_true(! $other_writer->acquire(750), 'The transition must retain the source lock while later ACF field writes run.');
        $admin->release_source_write_lock(750);
        vgpc_test_true($other_writer->acquire(750), 'The source lock must become available after the transition save completes.');
        $other_writer->release(750);
    }

    public function test_supported_course_to_article_transition_uses_submitted_target_fields(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(755, 'publish', [756, 757, 758]);
        $GLOBALS['vgpc_test_posts'][756]->post_status = 'publish';
        $GLOBALS['vgpc_test_posts'][757]->post_status = 'publish';
        $GLOBALS['vgpc_test_posts'][758]->post_status = 'publish';
        update_field('lead_instructor', 756, 755);
        update_field('primary_author', 757, 755);
        update_field('reviewed_by', [758], 755);
        $_POST = ['post_ID' => 755, 'post_status' => 'publish', 'acf' => ['field_vg_article_primary_author' => 756]];

        $data = ['ID' => 755, 'post_type' => 'post', 'post_status' => 'publish'];
        vgpc_test_same($data, apply_filters('wp_insert_post_data', $data, ['ID' => 755]), 'The reverse transition must validate submitted Article fields before the post row write.');
        vgpc_test_same(757, get_field('primary_author', 755, false), 'Article target values must remain unchanged until ACF persistence.');
        vgpc_test_same([758], get_field('reviewed_by', 755, false), 'Omitted Article fields must be included from persisted target values.');
        $admin->release_source_write_lock(755);
    }

    public function test_supported_type_entry_from_page_validates_target_projection(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(7560, 'page', [7561, 7562], 'page');
        $lock_held = false;
        $GLOBALS['wpdb']->get_var_handler = static function (string $query) use (&$lock_held): string {
            if (str_contains($query, 'GET_LOCK')) { if ($lock_held) return '0'; $lock_held = true; return '1'; }
            if (str_contains($query, 'RELEASE_LOCK')) { $lock_held = false; return '1'; }
            return '1';
        };
        $GLOBALS['vgpc_test_posts'][7561]->post_status = 'publish';
        $GLOBALS['vgpc_test_posts'][7562]->post_status = 'publish';
        $_POST = ['post_ID' => 7560, 'acf' => ['field_vg_article_primary_author' => 7561]];
        $data = ['ID' => 7560, 'post_type' => 'post', 'post_status' => 'publish'];
        vgpc_test_same($data, apply_filters('wp_insert_post_data', $data, ['ID' => 7560]), 'Entering the Article contract from page must validate the target projection.');
        $admin->release_source_write_lock(7560);

        $GLOBALS['vgpc_test_posts'][7560]->post_type = 'page';
        $_POST = ['post_ID' => 7560, 'acf' => ['field_vg_course_lead_instructor' => 7561]];
        $data = ['ID' => 7560, 'post_type' => 'mto-course', 'post_status' => 'publish'];
        vgpc_test_same($data, apply_filters('wp_insert_post_data', $data, ['ID' => 7560]), 'Entering the Course contract from page must validate the target projection.');
        $admin->release_source_write_lock(7560);

        $GLOBALS['vgpc_test_posts'][7562]->post_status = 'draft';
        $_POST = ['post_ID' => 7560, 'acf' => ['field_vg_article_primary_author' => 7562]];
        $data = ['ID' => 7560, 'post_type' => 'post', 'post_status' => 'publish'];
        try {
            apply_filters('wp_insert_post_data', $data, ['ID' => 7560]);
            throw new RuntimeException('An unpublished target Person must reject the published source transition.');
        } catch (RuntimeException $error) {
            vgpc_test_true(str_contains($error->getMessage(), 'Person 7562'), 'Target publication validation must inspect the target projection.');
        }
        vgpc_test_same('page', get_post_type(7560), 'An invalid target must be rejected before WordPress changes the type.');
        $other_writer = new PersonRelationshipSourceLock();
        vgpc_test_true(! $other_writer->acquire(7560), 'The pre-write lock must remain held until request cleanup after rejection.');
        $admin->release_source_write_lock(7560);
        $GLOBALS['vgpc_test_posts'][7562]->post_status = 'publish';

        $_POST = ['post_ID' => 7560, 'acf' => ['field_vg_article_primary_author' => ['bad']]];
        $data = ['ID' => 7560, 'post_type' => 'post', 'post_status' => 'publish'];
        try {
            apply_filters('wp_insert_post_data', $data, ['ID' => 7560]);
            throw new RuntimeException('Malformed target fields must fail before a supported type transition.');
        } catch (RuntimeException $error) {
            vgpc_test_true(str_contains($error->getMessage(), 'primary_author'), 'Malformed target input must report the target relationship field.');
        }
        $admin->release_source_write_lock(7560);

        $_POST = ['post_ID' => 7560, 'acf' => ['field_vg_course_lead_instructor' => ['bad']]];
        $data = ['ID' => 7560, 'post_type' => 'mto-course', 'post_status' => 'publish'];
        try {
            apply_filters('wp_insert_post_data', $data, ['ID' => 7560]);
            throw new RuntimeException('Malformed Course target fields must fail before the type transition.');
        } catch (RuntimeException $error) {
            vgpc_test_true(str_contains($error->getMessage(), 'lead_instructor'), 'Malformed Course input must identify the target field.');
        }
        $admin->release_source_write_lock(7560);
    }

    public function test_published_wp_api_can_enter_each_supported_type_from_page_with_hooks_suppressed(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(7580, 'publish', [7581], 'page');
        $this->source_and_people(7582, 'publish', [7583], 'page');
        $GLOBALS['vgpc_test_posts'][7581]->post_status = 'publish';
        $GLOBALS['vgpc_test_posts'][7583]->post_status = 'publish';
        update_field('primary_author', 7581, 7580);
        update_field('lead_instructor', 7583, 7582);
        $GLOBALS['vgpc_test_apply_wp_update_filter'] = true;
        $_POST = [];

        vgpc_test_same(7580, wp_update_post(['ID' => 7580, 'post_type' => 'post', 'post_status' => 'publish'], true, false), 'WordPress must accept a valid published page-to-Article transition with after hooks disabled.');
        vgpc_test_same('post', get_post_type(7580), 'The API must persist the supported Article target type.');
        do_action('shutdown');
        vgpc_test_same(7582, wp_update_post(['ID' => 7582, 'post_type' => 'mto-course', 'post_status' => 'publish'], true, false), 'WordPress must accept a valid published page-to-Course transition with after hooks disabled.');
        vgpc_test_same('mto-course', get_post_type(7582), 'The API must persist the supported Course target type.');
        do_action('shutdown');
    }

    public function test_batch_source_saves_release_no_acf_locks_per_post_and_allow_suppressed_hooks(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(7570, 'draft', [], 'post');
        $this->source_and_people(7571, 'draft', [], 'post');
        $this->source_and_people(7572, 'draft', [], 'post');
        $lock_held = [];
        $GLOBALS['wpdb']->get_var_handler = static function (string $query, array $args) use (&$lock_held): string {
            if (str_contains($query, 'GET_LOCK')) { $id = (int) str_replace('vg_person_rel_', '', (string) ($args[0] ?? '')); if (isset($lock_held[$id])) return '0'; $lock_held[$id] = true; return '1'; }
            if (str_contains($query, 'RELEASE_LOCK')) { $id = (int) str_replace('vg_person_rel_', '', (string) ($args[0] ?? '')); unset($lock_held[$id]); return '1'; }
            return '1';
        };
        $GLOBALS['vgpc_test_apply_wp_update_filter'] = true;
        $GLOBALS['vgpc_test_wp_update_post_hooks'] = true;
        $GLOBALS['vgpc_test_suppress_after_insert_post'] = true;
        vgpc_test_same(7570, wp_update_post(['ID' => 7570, 'post_title' => 'A'], true, false), 'First bulk source update must complete with fire_after_hooks=false.');
        vgpc_test_same(7571, wp_update_post(['ID' => 7571, 'post_title' => 'B'], true, false), 'Second bulk source update must acquire its own lock while the first remains held.');
        vgpc_test_same([], $lock_held, 'save_post must release each no-ACF lock even when after hooks are suppressed.');
        $GLOBALS['vgpc_test_wp_update_post_hooks'] = true;
        vgpc_test_same(7572, wp_update_post(['ID' => 7572, 'post_title' => 'C'], true), 'A normal no-ACF save must complete.');
        vgpc_test_same([], $lock_held, 'A normal post save must release only its own lock.');
        do_action('shutdown');
        vgpc_test_same([], $lock_held, 'No-ACF post saves must release each source lock even when wp_after_insert_post is suppressed.');
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

    public function test_mismatched_governed_payload_blocks_publish_before_wordpress_writes_status(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(7181, 'draft', [7182]);
        $this->source_and_people(7183, 'publish', [7184]);
        $GLOBALS['vgpc_test_posts'][7182]->post_status = 'publish';
        $GLOBALS['vgpc_test_posts'][7184]->post_status = 'draft';
        update_field('primary_author', 7182, 7181);
        update_field('primary_author', 7184, 7183);
        $_POST = ['post_ID' => 7183, 'post_status' => 'publish', 'acf' => ['field_vg_article_primary_author' => 7184]];
        $GLOBALS['vgpc_test_apply_wp_update_filter'] = true;
        $GLOBALS['vgpc_test_wp_update_post_hooks'] = true;
        $GLOBALS['vgpc_test_acf_save_during_core_save'] = true;
        try {
            wp_update_post(['ID' => 7181, 'post_status' => 'publish'], true);
            throw new RuntimeException('A mismatched governed ACF payload must block publication before the post write.');
        } catch (RuntimeException $error) {
            vgpc_test_true(str_contains($error->getMessage(), 'wp_die:'), 'The ambiguous publish must stop at the pre-write boundary.');
        }
        vgpc_test_same('draft', $GLOBALS['vgpc_test_posts'][7181]->post_status, 'The rejected save must stop before WordPress writes publish status.');
        vgpc_test_same(7182, get_field('primary_author', 7181, false), 'The mismatched ACF payload must not be applied to source A.');
    }

    public function test_nested_draft_save_with_mismatched_acf_form_payload_does_not_apply_payload(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(7185, 'draft', [7186]);
        $GLOBALS['vgpc_test_posts'][7186]->post_status = 'publish';
        update_field('primary_author', null, 7185);
        $_POST = ['post_ID' => 7187, 'post_status' => 'draft', 'acf' => ['field_vg_article_primary_author' => 7186]];
        $GLOBALS['vgpc_test_apply_wp_update_filter'] = true;
        $GLOBALS['vgpc_test_wp_update_post_hooks'] = true;
        $GLOBALS['vgpc_test_acf_save_during_core_save'] = true;
        vgpc_test_same(7185, wp_update_post(['ID' => 7185, 'post_status' => 'draft'], true), 'A nested non-public WordPress save may proceed when ACF form binding names another post.');
        vgpc_test_same(null, get_field('primary_author', 7185, false), 'ACF_Form_Post must skip the mismatched payload for nested source B.');
    }

    public function test_preflight_resolves_acf_name_and_numeric_id_selectors_and_rejects_alias_duplicates(): void
    {
        foreach (['primary_author', 'numeric_id'] as $selector) {
            vgpc_test_reset();
            $admin = $this->admin();
            $admin->register();
            $this->source_and_people(7901, 'publish', [7902]);
            $GLOBALS['vgpc_test_posts'][7902]->post_status = 'publish';
            if ($selector === 'numeric_id') {
                $field_group = $GLOBALS['vgpc_test_field_groups']['test_person_relationship_admin_fields'];
                $numeric_id = 0;
                foreach ($field_group['fields'] as $field) {
                    if ($field['key'] === 'field_vg_article_primary_author') $numeric_id = (int) $field['ID'];
                }
                vgpc_test_true($numeric_id > 0, 'The test ACF field must have a numeric ID.');
                $selector = $numeric_id;
            }
            update_field('primary_author', null, 7901);
            $_POST = ['post_ID' => 7901, 'post_status' => 'publish', 'acf' => [$selector => 7902]];
            $data = ['ID' => 7901, 'post_type' => 'post', 'post_status' => 'publish'];
            vgpc_test_same($data, apply_filters('wp_insert_post_data', $data, ['ID' => 7901]), 'Name and numeric ACF selectors must be normalized before publication preflight.');
        }

        vgpc_test_reset();
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(7903, 'publish', [7904]);
        $GLOBALS['vgpc_test_posts'][7904]->post_status = 'publish';
        update_field('primary_author', null, 7903);
        $_POST = ['post_ID' => 7903, 'post_status' => 'publish', 'acf' => [
            'field_vg_article_primary_author' => 7904,
            'primary_author' => null,
        ]];
        try {
            apply_filters('wp_insert_post_data', ['ID' => 7903, 'post_type' => 'post', 'post_status' => 'publish'], ['ID' => 7903]);
            throw new RuntimeException('Mixed aliases must be rejected before the WordPress post write.');
        } catch (RuntimeException $error) {
            vgpc_test_true(str_contains($error->getMessage(), 'more than one ACF selector'), 'Duplicate alias rejection must explain the conflict.');
        }
    }

    public function test_actual_acf_callback_resolves_numeric_id_before_any_field_write(): void
    {
        vgpc_test_reset();
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(7905, 'publish', [7906, 7907]);
        $GLOBALS['vgpc_test_posts'][7906]->post_status = 'publish';
        $GLOBALS['vgpc_test_posts'][7907]->post_status = 'publish';
        update_field('primary_author', 7906, 7905);
        $numeric_selector = (int) acf_get_field('field_vg_article_primary_author')['ID'];
        vgpc_test_same('primary_author', acf_get_field($numeric_selector)['name'] ?? null, 'The ACF test double must resolve numeric IDs.');
        $_POST = ['acf' => [$numeric_selector => 7907]];
        add_action('acf/save_post', static function (int $post_id): void {
            acf_update_values($_POST['acf'], $post_id);
        }, 10, 1);
        do_action('acf/save_post', 7905);
        vgpc_test_same(7907, get_field('primary_author', 7905, false), 'The test ACF writer must accept numeric IDs like acf_update_values.');
    }

    public function test_duplicate_governed_name_with_unapproved_key_or_id_is_rejected(): void
    {
        foreach (['key', 'numeric_id'] as $selector_type) {
            vgpc_test_reset();
            $admin = $this->admin();
            $admin->register();
            $this->source_and_people(7912, 'publish', [7913]);
            $GLOBALS['vgpc_test_posts'][7913]->post_status = 'publish';
            $collision = ['key' => 'field_unapproved_primary_author', 'name' => 'primary_author'];
            if ($selector_type === 'numeric_id') $collision['ID'] = 987654;
            acf_add_local_field_group(['key' => 'test_governed_name_collision', 'fields' => [$collision]]);
            $selector = $selector_type === 'key' ? $collision['key'] : $collision['ID'];
            $_POST = ['post_ID' => 7912, 'post_status' => 'publish', 'acf' => [$selector => 7913]];
            $data = ['ID' => 7912, 'post_type' => 'post', 'post_status' => 'publish'];
            try {
                apply_filters('wp_insert_post_data', $data, ['ID' => 7912]);
                throw new RuntimeException('A governed name resolved through a different ACF key must be rejected.');
            } catch (RuntimeException $error) {
                vgpc_test_true(str_contains($error->getMessage(), 'wp_die:'), 'The unapproved duplicate-name selector must fail before post write.');
            }
        }
    }

    public function test_actual_acf_callback_rejects_mixed_aliases_before_acf_update_values(): void
    {
        vgpc_test_reset();
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(7908, 'publish', [7909]);
        $GLOBALS['vgpc_test_posts'][7909]->post_status = 'publish';
        update_field('primary_author', null, 7908);
        $_POST = ['acf' => ['field_vg_article_primary_author' => 7909, 'primary_author' => null]];
        add_action('acf/save_post', static function (int $post_id): void { acf_update_values($_POST['acf'], $post_id); }, 10, 1);
        try {
            do_action('acf/save_post', 7908);
            throw new RuntimeException('The direct ACF callback must reject duplicate field aliases before ACF writes.');
        } catch (RuntimeException $error) {
            vgpc_test_true(str_contains($error->getMessage(), 'more than one ACF selector'), 'Direct callback must reject duplicate aliases at priority 1.');
        }
        vgpc_test_same(null, get_field('primary_author', 7908, false), 'No ACF value may be written when mixed aliases are ambiguous.');
    }

    public function test_non_governed_acf_field_continues_through_the_same_save(): void
    {
        vgpc_test_reset();
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(7910, 'publish', [7911]);
        $GLOBALS['vgpc_test_posts'][7911]->post_status = 'publish';
        update_field('primary_author', 7911, 7910);
        $_POST = ['post_ID' => 7910, 'post_status' => 'publish', 'acf' => ['field_unrelated_notes' => 'kept']];
        $data = ['ID' => 7910, 'post_type' => 'post', 'post_status' => 'publish'];
        vgpc_test_same($data, apply_filters('wp_insert_post_data', $data, ['ID' => 7910]), 'Unrelated fields must not enter Person relationship preflight.');
        $this->acf_form_post_save(7910);
        vgpc_test_same('kept', get_field('unrelated_notes', 7910, false), 'ACF must still persist a non-governed field.');
        vgpc_test_same(7911, get_field('primary_author', 7910, false), 'The unrelated write must leave governed relationships unchanged.');
    }

    public function test_new_source_preflight_uses_only_matching_new_post_form_payload(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(7185, 'publish', [7186]);
        $GLOBALS['vgpc_test_posts'][7186]->post_status = 'publish';
        $_POST = ['post_ID' => 0, 'post_status' => 'publish', 'acf' => ['field_vg_article_primary_author' => 7186]];

        $data = ['post_type' => 'post', 'post_status' => 'publish'];
        vgpc_test_same($data, apply_filters('wp_insert_post_data', $data, []), 'A new source form with post_ID 0 may validate its submitted relationship before WordPress assigns an ID.');
    }

    public function test_prewrite_preflight_validates_acf_payload_with_missing_or_zero_post_id(): void
    {
        foreach ([[], ['post_ID' => 0], ['_acf_post_id' => 7399]] as $form_binding) {
            vgpc_test_reset();
            $admin = $this->admin();
            $admin->register();
            $this->source_and_people(7390, 'publish', [7391]);
            $GLOBALS['vgpc_test_posts'][7391]->post_status = 'publish';
            update_field('primary_author', 7391, 7390);
            $_POST = $form_binding + [
                'post_status' => 'publish',
                'acf' => ['field_vg_article_additional_authors' => [7391]],
            ];

            try {
                apply_filters('wp_insert_post_data', ['ID' => 7390, 'post_type' => 'post', 'post_status' => 'publish'], ['ID' => 7390]);
                throw new RuntimeException('ACF-accepted missing/zero post_ID payload must fail partial-state validation before the post write.');
            } catch (RuntimeException $error) {
                vgpc_test_true(str_contains($error->getMessage(), 'possible partial relationship save'), 'The pre-write rejection must identify the unsafe partial projection.');
            }
            vgpc_test_same('publish', $GLOBALS['vgpc_test_posts'][7390]->post_status, 'Rejected payload must not reach the WordPress post write.');
            vgpc_test_same(null, get_field('additional_authors', 7390, false), 'Rejected payload must not reach ACF field persistence.');
        }
    }

    public function test_prewrite_preflight_rejects_mismatched_post_id_even_when_acf_would_skip(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(7392, 'publish', [7393]);
        $GLOBALS['vgpc_test_posts'][7393]->post_status = 'publish';
        update_field('primary_author', 7393, 7392);
        $_POST = ['post_ID' => 7394, '_acf_post_id' => 7392, 'post_status' => 'publish', 'acf' => ['field_vg_article_additional_authors' => [7393]]];

        $data = ['ID' => 7392, 'post_type' => 'post', 'post_status' => 'publish'];
        try {
            apply_filters('wp_insert_post_data', $data, ['ID' => 7392]);
            throw new RuntimeException('A positive mismatched post_ID with governed ACF values must be rejected as ambiguous.');
        } catch (RuntimeException $error) {
            vgpc_test_true(str_contains($error->getMessage(), 'wp_die:'), 'Ambiguous governed payloads must fail before WordPress writes the source.');
        }
        vgpc_test_same('publish', $GLOBALS['vgpc_test_posts'][7392]->post_status, 'Preflight rejection must leave WordPress status unchanged.');
    }

    public function test_admin_retries_exceptional_source_lock_release_at_shutdown(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(7187, 'draft', [], 'post');
        $release_attempts = 0;
        $held = false;
        $GLOBALS['wpdb']->get_var_handler = static function (string $query) use (&$release_attempts, &$held): string {
            if (str_contains($query, 'GET_LOCK')) { $held = true; return '1'; }
            if (str_contains($query, 'RELEASE_LOCK')) {
                if (++$release_attempts === 1) throw new RuntimeException('temporary connection error');
                $held = false;
                return '1';
            }
            return '1';
        };
        $admin->validate_source_before_save(['ID' => 7187, 'post_type' => 'post', 'post_status' => 'draft'], ['ID' => 7187]);
        $shutdown = new WP_Hook();
        $shutdown->add_filter('shutdown', static function () use ($admin): void {
            $admin->release_source_write_lock(7187);
            // This reproduces the rejected design: WP_Hook will not revisit
            // PHP_INT_MAX - 1 after its active iteration has reached max.
            $GLOBALS['vgpc_test_late_retry_runs'] = 0;
            $GLOBALS['vgpc_test_active_shutdown_hook']->add_filter('shutdown', static function (): void {
                ++$GLOBALS['vgpc_test_late_retry_runs'];
            }, PHP_INT_MAX - 1, 0);
        }, PHP_INT_MAX, 0);
        $GLOBALS['vgpc_test_late_retry_runs'] = 0;
        $GLOBALS['vgpc_test_active_shutdown_hook'] = $shutdown;
        $shutdown->do_action([]);
        vgpc_test_same(2, $release_attempts, 'A release that first fails at PHP_INT_MAX must retry within its active WP_Hook callback.');
        vgpc_test_true(! $held, 'The bounded retry must release the tracked source lock.');
        vgpc_test_same(0, $GLOBALS['vgpc_test_late_retry_runs'], 'WP_Hook must skip a retry registered at an earlier priority after shutdown reaches PHP_INT_MAX.');
        unset($GLOBALS['vgpc_test_active_shutdown_hook'], $GLOBALS['vgpc_test_late_retry_runs']);
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

    public function test_editor_preflight_rejects_a_role_swap_that_acf_could_persist_partially(): void
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
        try {
            apply_filters('wp_insert_post_data', $data, ['ID' => 733]);
            throw new RuntimeException('A possible partial ACF write must be rejected before WordPress saves the post.');
        } catch (RuntimeException $error) {
            vgpc_test_true(str_contains($error->getMessage(), 'possible partial relationship save'), 'The old/new subset overlap must cause rejection.');
        }
        vgpc_test_same(734, get_field('primary_author', 733, false), 'Preflight must leave the old primary author in place.');
        vgpc_test_same([735], get_field('additional_authors', 733, false), 'Preflight must leave the old additional authors in place.');
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

    public function test_nested_same_source_save_keeps_outer_lock_until_outer_acf_projection(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(727, 'draft', [728, 729]);
        $GLOBALS['vgpc_test_apply_wp_update_filter'] = true;
        $GLOBALS['vgpc_test_wp_update_post_hooks'] = true;
        $GLOBALS['vgpc_test_acf_save_during_core_save'] = true;
        $_POST = ['post_ID' => 727, 'post_status' => 'draft', 'acf' => ['field_vg_article_primary_author' => 728]];
        $nested = false;
        $contender_results = [];
        $outer_pending_values = [];
        $competing_probe = false;
        add_action('save_post', static function (int $post_id) use (&$nested, &$competing_probe, &$contender_results, &$outer_pending_values): void {
            if ($post_id !== 727 || $nested) return;
            $nested = true;
            $outer_fields = $_POST['acf'];
            $_POST['acf'] = ['field_vg_article_primary_author' => 729];
            wp_update_post(['ID' => 727, 'post_status' => 'draft'], true);
            $_POST['acf'] = $outer_fields;
            $outer_pending_values[] = get_field('primary_author', 727, false);
            $competitor = new PersonRelationshipSourceLock();
            $competing_probe = true;
            $contender_results[] = $competitor->acquire(727);
            $competing_probe = false;
            $competitor->release(727);
        }, 5, 1);
        $lock_held = false;
        $lock_depth = 0;
        $successful_lock_acquisitions = 0;
        $GLOBALS['wpdb']->get_var_handler = static function (string $query, array $args) use (&$lock_held, &$lock_depth, &$competing_probe, &$successful_lock_acquisitions): string {
            if (str_contains($query, 'GET_LOCK')) {
                if ($competing_probe && $lock_held) return '0';
                $lock_held = true;
                ++$lock_depth;
                ++$successful_lock_acquisitions;
                return '1';
            }
            if (str_contains($query, 'RELEASE_LOCK')) {
                --$lock_depth;
                $lock_held = $lock_depth > 0;
                return '1';
            }
            return '1';
        };

        wp_update_post(['ID' => 727, 'post_status' => 'draft'], true);

        vgpc_test_same([false], $contender_results, 'A different writer must remain blocked while the outer ACF payload is pending.');
        vgpc_test_same([729], $outer_pending_values, 'The nested ACF write must finish while the outer requested value is still pending.');
        vgpc_test_same(728, get_field('primary_author', 727, false), 'The outer ACF write must persist after the nested save returns.');
        $queries = array_column($GLOBALS['wpdb']->queries, 'query');
        $get_lock_count = count(array_filter($queries, static fn (string $query): bool => $query === 'SELECT GET_LOCK(%s, %d)'));
        $release_lock_count = count(array_filter($queries, static fn (string $query): bool => $query === 'SELECT RELEASE_LOCK(%s)'));
        vgpc_test_true($get_lock_count > 0, 'The test must observe named-lock acquisition and the competing writer attempt.');
        vgpc_test_same($successful_lock_acquisitions, $release_lock_count, 'Every successful named-lock acquisition must be released after outer ACF projection.');
        $competitor = new PersonRelationshipSourceLock();
        $competing_probe = true;
        vgpc_test_true($competitor->acquire(727), 'A competitor may enter after the outer ACF projection completes.');
        $competitor->release(727);
    }

    public function test_nested_same_source_failure_releases_lock_at_shutdown(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(730, 'draft', [731]);
        $GLOBALS['vgpc_test_apply_wp_update_filter'] = true;
        $GLOBALS['vgpc_test_wp_update_post_hooks'] = true;
        $GLOBALS['vgpc_test_acf_save_during_core_save'] = true;
        $_POST = ['post_ID' => 730, 'post_status' => 'draft', 'acf' => ['field_vg_article_primary_author' => 'malformed']];
        $nested = false;
        add_action('save_post', static function (int $post_id) use (&$nested): void {
            if ($post_id !== 730 || $nested) return;
            $nested = true;
            wp_update_post(['ID' => 730, 'post_status' => 'draft'], true);
        }, 5, 1);
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
        try {
            wp_update_post(['ID' => 730, 'post_status' => 'draft'], true);
            throw new RuntimeException('Malformed nested ACF data must abort the save.');
        } catch (RuntimeException $error) {
            vgpc_test_true(str_starts_with($error->getMessage(), 'wp_die:'), 'Nested ACF validation must abort before field persistence.');
        }
        do_action('shutdown');
        $shutdown_releases = count(array_filter($GLOBALS['wpdb']->queries, static fn (array $query): bool => $query['query'] === 'SELECT RELEASE_LOCK(%s)'));
        vgpc_test_same(1, $shutdown_releases, 'The failed nested save must release the held source lock at shutdown.');
        $competitor = new PersonRelationshipSourceLock();
        vgpc_test_true($competitor->acquire(730), 'Shutdown cleanup must release the outer lock after nested ACF failure.');
        $competitor->release(730);
        vgpc_test_same(null, get_field('primary_author', 730, false), 'A failed nested save must not persist malformed relationship data.');
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

    public function test_actual_acf_form_callback_uses_actual_post_id_when_post_id_is_missing_or_zero(): void
    {
        foreach ([
            ['post_id' => [], 'new' => false],
            ['post_id' => ['post_ID' => 0], 'new' => false],
            ['post_id' => [], 'new' => true],
            ['post_id' => ['post_ID' => 0], 'new' => true],
        ] as $case) {
            vgpc_test_reset();
            $admin = $this->admin();
            $admin->register();
            $source_id = $case['new']
                ? wp_insert_post(['post_type' => 'post', 'post_status' => 'draft'], true)
                : ($case['post_id'] === [] ? 130 : 131);
            vgpc_test_true(is_int($source_id) && $source_id > 0, 'A new form must use the ID WordPress assigned to its inserted post.');
            $person_id = $source_id + 1;
            $this->source_and_people($source_id, 'draft', [$person_id]);
            $GLOBALS['vgpc_test_posts'][$person_id]->post_status = 'publish';
            $_POST = $case['post_id'] + ['post_status' => 'draft', 'acf' => ['field_vg_article_primary_author' => $person_id]];

            vgpc_test_true($this->acf_form_post_save($source_id), 'ACF accepts a missing or zero post_ID and saves against its actual save_post ID.');
            vgpc_test_same($person_id, get_field('primary_author', $source_id, false), 'The actual ACF callback must persist the submitted relationship to the callback post ID.');
        }
    }

    public function test_actual_acf_callback_rejects_published_partial_and_invalid_draft_relationship_states(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(133, 'publish', [134]);
        $GLOBALS['vgpc_test_posts'][134]->post_status = 'publish';
        update_field('primary_author', 134, 133);
        $_POST = ['post_ID' => 133, 'post_status' => 'publish', 'acf' => ['field_vg_article_additional_authors' => [134]]];
        try {
            $this->acf_form_post_save(133);
            throw new RuntimeException('The actual published ACF callback must reject an invalid partial projection.');
        } catch (RuntimeException $error) {
            vgpc_test_true(str_contains($error->getMessage(), 'possible partial relationship save'), 'Published ACF partial validation must report the unsafe intermediate state.');
        }
        vgpc_test_same(null, get_field('additional_authors', 133, false), 'Rejected published ACF writes must not persist the invalid role overlap.');

        $draft_admin = $this->admin();
        $draft_admin->register();
        $this->source_and_people(135, 'draft', [136]);
        $_POST = ['post_ID' => 135, 'post_status' => 'draft', 'acf' => [
            'field_vg_article_primary_author' => 136,
            'field_vg_article_additional_authors' => [136],
        ]];
        try {
            $this->acf_form_post_save(135);
            throw new RuntimeException('The actual draft ACF callback must reject invalid relationship data.');
        } catch (RuntimeException $error) {
            vgpc_test_true(str_contains($error->getMessage(), 'may hold only one role'), 'Draft callback validation must enforce the same role uniqueness rule.');
        }
        vgpc_test_same([], $GLOBALS['vgpc_test_meta'][135] ?? [], 'Invalid draft ACF values must fail before ACF persistence.');
    }

    public function test_direct_acf_save_callback_validates_payload_for_actual_source_despite_post_form_mismatch(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(140, 'publish', [141]);
        $GLOBALS['vgpc_test_posts'][141]->post_status = 'publish';
        update_field('primary_author', 141, 140);
        // acf_save_post($id) dispatches this callback even if ACF_Form_Post's
        // post_ID gate would have skipped its ordinary save_post callback.
        $_POST = ['post_ID' => 999, 'post_status' => 'publish', 'acf' => [
            'field_vg_article_expert_contributors' => [141],
        ]];
        try {
            do_action('acf/save_post', 140);
            throw new RuntimeException('Direct ACF callback must reject the unsafe payload ACF will persist for its actual source ID.');
        } catch (RuntimeException $error) {
            vgpc_test_true(str_contains($error->getMessage(), 'wp_die:'), 'Direct callback must reject the mismatched payload before ACF writes the source.');
        }
        vgpc_test_same(null, get_field('expert_contributors', 140, false), 'Rejected direct callback must leave the duplicate role unwritten.');
        $admin->release_source_write_lock(140);
    }

    public function test_acf_form_callback_skips_nested_save_for_another_explicit_post_id(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(137, 'draft', [138]);
        $GLOBALS['vgpc_test_posts'][138]->post_status = 'publish';
        $_POST = ['post_ID' => 139, 'post_status' => 'draft', 'acf' => ['field_vg_article_primary_author' => 138]];

        vgpc_test_same(false, $this->acf_form_post_save(137), 'ACF must skip a nested save when a positive explicit post_ID names another post.');
        vgpc_test_same(null, get_field('primary_author', 137, false), 'A nested unrelated ACF payload must not be persisted against the callback post.');
    }

    public function test_source_type_change_keeps_dirty_state_when_projection_cannot_acquire_lock(): void
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

        $GLOBALS['vgpc_test_apply_wp_update_filter'] = true;
        $GLOBALS['vgpc_test_wp_update_post_hooks'] = true;
        $GLOBALS['vgpc_test_meta'][114][PersonRelationshipContract::DIRTY_META_KEY] = '0';
        vgpc_test_same(114, wp_update_post(['ID' => 114, 'post_type' => 'page', 'post_status' => 'draft'], true), 'The supported WordPress API must save the source type transition.');
        vgpc_test_same('page', get_post_type(114), 'The transition hook must observe the post type WordPress actually persisted.');
        vgpc_test_same('1', (string) get_post_meta(114, PersonRelationshipContract::DIRTY_META_KEY, true), 'The post-save transition hook must mark the old index for recovery.');
        $cleanup = $this->indexer()->remove_stale_source(114);
        vgpc_test_same(true, $cleanup, 'A failed transition projection must release its writer lock so reconciliation can retry.');

        do_action('shutdown');
        $GLOBALS['wpdb']->get_col_handler = static fn (): array => [114];
        $service = new PersonRelationshipService(new PersonRelationshipSourceReader(), new PersonRelationshipValidator(), new PersonRelationshipRepository(new PersonRelationshipSchema()));
        vgpc_test_same([], $service->articles_for_person(115), 'Reverse Article queries must suppress stale rows after the actual type change.');
        vgpc_test_same([], $service->courses_for_person(115), 'Reverse Course queries must suppress rows until reconciliation rebuilds them.');
        $cleanup = $this->indexer()->remove_stale_source(114);
        vgpc_test_same(true, $cleanup, 'Repeated cleanup remains safe after the unsupported source has no index rows.');
    }

    public function test_supported_wordpress_type_transition_marks_dirty_and_reverse_query_uses_current_type(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(116, 'publish', [117], 'post');
        $GLOBALS['vgpc_test_posts'][117]->post_status = 'publish';
        update_field('primary_author', 117, 116);
        update_field('lead_instructor', 117, 116);
        $GLOBALS['vgpc_test_options'][PersonRelationshipSchema::VERSION_OPTION] = PersonRelationshipSchema::SCHEMA_VERSION;
        $GLOBALS['vgpc_test_apply_wp_update_filter'] = true;
        $GLOBALS['vgpc_test_wp_update_post_hooks'] = true;
        $GLOBALS['vgpc_test_suppress_after_insert_post'] = true;
        $GLOBALS['wpdb']->fail_insert = false;
        $GLOBALS['vgpc_test_wp_hook_order'] = [];
        $GLOBALS['wpdb']->get_var_handler = static function (string $query): string {
            if (str_contains($query, 'SHOW TABLES')) return 'wp_vg_person_relationship_index';
            return '1';
        };
        vgpc_test_same(116, wp_update_post(['ID' => 116, 'post_type' => 'mto-course', 'post_status' => 'publish'], true), 'WordPress must persist a supported source type transition without an ACF payload.');
        vgpc_test_same('mto-course', get_post_type(116), 'The saved post must expose its current source type.');
        vgpc_test_same(['save_post'], $GLOBALS['vgpc_test_wp_hook_order'], 'save_post must handle the transition when fire_after_hooks suppresses wp_after_insert_post.');
        vgpc_test_same('', (string) get_post_meta(116, PersonRelationshipContract::DIRTY_META_KEY, true), 'save_post must rebuild a supported transition when wp_after_insert_post is suppressed.');
        vgpc_test_true(count(array_filter($GLOBALS['wpdb']->inserted, static fn (array $row): bool => ($row[1]['source_post_id'] ?? null) === 116 && ($row[1]['source_post_type'] ?? null) === 'mto-course')) > 0, 'The save_post transition path must project the target source type.');

        $queried_types = [];
        $GLOBALS['wpdb']->get_col_handler = static function (string $query, array $args) use (&$queried_types): array {
            $queried_types[] = $args[1] ?? null;
            return [116];
        };
        $service = new PersonRelationshipService(new PersonRelationshipSourceReader(), new PersonRelationshipValidator(), new PersonRelationshipRepository(new PersonRelationshipSchema()));
        vgpc_test_same([], $service->articles_for_person(117), 'Article reverse results must disappear after the actual transition.');
        vgpc_test_same([116], $service->courses_for_person(117), 'The rebuilt current-type index row must be visible through Course reverse queries.');
        vgpc_test_true(in_array('mto-course', $queried_types, true), 'Reverse lookup must query using the source post type WordPress persisted.');
        do_action('shutdown');
    }

    public function test_type_transition_recovers_when_global_acf_payload_is_unrelated_or_skipped(): void
    {
        foreach ([
            'unrelated_post' => ['post_ID' => 7770],
            'invalid_nonce' => ['post_ID' => 7771, 'nonce_invalid' => true],
            'validation_failure' => ['post_ID' => 7772, 'validation_failed' => true],
            'suppressed_after_hooks' => ['post_ID' => 7773, 'fire_after_hooks' => false],
        ] as $case => $options) {
            vgpc_test_reset();
            $admin = $this->admin();
            $admin->register();
            $post_id = 7780 + count($GLOBALS['vgpc_test_posts']);
            $person_id = $post_id + 1;
            $this->source_and_people($post_id, 'draft', [$person_id], 'page');
            $GLOBALS['vgpc_test_posts'][$person_id]->post_status = 'publish';
            update_field('primary_author', $person_id, $post_id);
            $GLOBALS['vgpc_test_options'][PersonRelationshipSchema::VERSION_OPTION] = PersonRelationshipSchema::SCHEMA_VERSION;
            $GLOBALS['vgpc_test_apply_wp_update_filter'] = true;
            $GLOBALS['vgpc_test_wp_update_post_hooks'] = true;
            $GLOBALS['vgpc_test_acf_save_during_core_save'] = true;
            $GLOBALS['vgpc_test_acf_nonce_invalid'] = (bool) ($options['nonce_invalid'] ?? false);
            $GLOBALS['vgpc_test_acf_validation_failed'] = (bool) ($options['validation_failed'] ?? false);
            $_POST = ['post_ID' => $case === 'unrelated_post' ? (int) $options['post_ID'] : $post_id, 'acf' => ['field_vg_article_primary_author' => $person_id]];
            $GLOBALS['wpdb']->get_var_handler = static fn (string $query): string => str_contains($query, 'SHOW TABLES') ? 'wp_vg_person_relationship_index' : '1';

            $fire_after_hooks = (bool) ($options['fire_after_hooks'] ?? true);
            vgpc_test_same($post_id, wp_update_post(['ID' => $post_id, 'post_type' => 'post'], true, $fire_after_hooks), 'WordPress must persist the type transition for case ' . $case . '.');
            vgpc_test_same('', (string) get_post_meta($post_id, PersonRelationshipContract::DIRTY_META_KEY, true), 'Transition recovery must finish for case ' . $case . '.');
            vgpc_test_true(count(array_filter($GLOBALS['wpdb']->inserted, static fn (array $row): bool => ($row[1]['source_post_id'] ?? null) === $post_id && ($row[1]['source_post_type'] ?? null) === 'post')) > 0, 'Recovery must project the current source type for case ' . $case . '.');
            do_action('shutdown');
        }
    }

    public function test_shutdown_recovers_type_transition_when_acf_validation_aborts_save_post(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(7790, 'draft', [7791], 'page');
        $GLOBALS['vgpc_test_posts'][7791]->post_status = 'publish';
        update_field('primary_author', 7791, 7790);
        $GLOBALS['vgpc_test_options'][PersonRelationshipSchema::VERSION_OPTION] = PersonRelationshipSchema::SCHEMA_VERSION;
        $GLOBALS['vgpc_test_apply_wp_update_filter'] = true;
        $GLOBALS['vgpc_test_wp_update_post_hooks'] = true;
        $GLOBALS['vgpc_test_acf_save_during_core_save'] = true;
        $GLOBALS['wpdb']->get_var_handler = static fn (string $query): string => str_contains($query, 'SHOW TABLES') ? 'wp_vg_person_relationship_index' : '1';
        $_POST = ['post_ID' => 7790, 'post_status' => 'draft', 'acf' => ['field_vg_article_primary_author' => 'malformed']];

        try {
            wp_update_post(['ID' => 7790, 'post_type' => 'post'], true);
            throw new RuntimeException('Malformed ACF validation must abort the nested save callback.');
        } catch (RuntimeException $error) {
            vgpc_test_true(str_starts_with($error->getMessage(), 'wp_die:'), 'The nested ACF validation failure must abort the core save_post callback.');
        }
        vgpc_test_same('post', get_post_type(7790), 'WordPress has already persisted the target source type before ACF validation aborts.');
        do_action('shutdown');
        vgpc_test_same('', (string) get_post_meta(7790, PersonRelationshipContract::DIRTY_META_KEY, true), 'Shutdown recovery must rebuild the source after the interrupted ACF callback.');
        vgpc_test_true(count(array_filter($GLOBALS['wpdb']->inserted, static fn (array $row): bool => ($row[1]['source_post_id'] ?? null) === 7790 && ($row[1]['source_post_type'] ?? null) === 'post' && ($row[1]['person_id'] ?? null) === 7791)) > 0, 'Shutdown recovery must index the persisted target type using stored relationship values.');
    }

    public function test_type_transition_projection_failure_keeps_the_dirty_marker(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(118, 'draft', [119], 'post');
        $GLOBALS['vgpc_test_meta'][118]['primary_author'] = 119;
        $GLOBALS['vgpc_test_meta'][118]['lead_instructor'] = 119;
        $GLOBALS['vgpc_test_posts'][119]->post_status = 'publish';
        $GLOBALS['vgpc_test_options'][PersonRelationshipSchema::VERSION_OPTION] = PersonRelationshipSchema::SCHEMA_VERSION;
        $GLOBALS['vgpc_test_apply_wp_update_filter'] = true;
        $GLOBALS['vgpc_test_wp_update_post_hooks'] = true;
        unset($GLOBALS['vgpc_test_suppress_after_insert_post']);
        $GLOBALS['wpdb']->fail_insert = false;
        $GLOBALS['wpdb']->fail_insert = true;
        $GLOBALS['wpdb']->get_var_handler = static fn (string $query): string => str_contains($query, 'SHOW TABLES') ? 'wp_vg_person_relationship_index' : '1';

        vgpc_test_same(118, wp_update_post(['ID' => 118, 'post_type' => 'mto-course'], true), 'WordPress must persist the supported type transition.');
        vgpc_test_same('1', (string) get_post_meta(118, PersonRelationshipContract::DIRTY_META_KEY, true), 'A failed projection must retain dirty state after the source type changes.');
    }

    public function test_wp_after_insert_does_not_redirty_a_fresh_save_post_projection(): void
    {
        $admin = $this->admin();
        $admin->register();
        $this->source_and_people(120, 'draft', [121], 'post');
        $GLOBALS['vgpc_test_meta'][120]['primary_author'] = 121;
        $GLOBALS['vgpc_test_meta'][120]['lead_instructor'] = 121;
        $GLOBALS['vgpc_test_posts'][121]->post_status = 'publish';
        $GLOBALS['vgpc_test_options'][PersonRelationshipSchema::VERSION_OPTION] = PersonRelationshipSchema::SCHEMA_VERSION;
        $GLOBALS['vgpc_test_apply_wp_update_filter'] = true;
        $GLOBALS['vgpc_test_wp_update_post_hooks'] = true;
        $GLOBALS['vgpc_test_wp_hook_order'] = [];
        $GLOBALS['vgpc_test_acf_save_during_core_save'] = true;
        $_POST = ['post_ID' => 120, 'post_status' => 'draft', 'acf' => ['field_vg_course_lead_instructor' => 121]];
        add_action('acf/save_post', static function (int $post_id): void {
            update_field('lead_instructor', 121, $post_id);
        }, 10, 1);
        $GLOBALS['wpdb']->get_var_handler = static fn (string $query): string => str_contains($query, 'SHOW TABLES') ? 'wp_vg_person_relationship_index' : '1';

        vgpc_test_same(120, wp_update_post(['ID' => 120, 'post_type' => 'mto-course'], true), 'WordPress must persist the source type transition.');
        vgpc_test_same(['save_post', 'acf/save_post', 'wp_after_insert_post'], $GLOBALS['vgpc_test_wp_hook_order'], 'ACF must run from its save_post priority 10 handler before wp_after_insert_post.');
        vgpc_test_same('', (string) get_post_meta(120, PersonRelationshipContract::DIRTY_META_KEY, true), 'wp_after_insert_post must not mark the successful save_post projection dirty again.');
        vgpc_test_true(count(array_filter($GLOBALS['wpdb']->inserted, static fn (array $row): bool => ($row[1]['source_post_id'] ?? null) === 120 && ($row[1]['source_post_type'] ?? null) === 'mto-course' && ($row[1]['person_id'] ?? null) === 121 && ($row[1]['role_key'] ?? null) === 'lead_instructor')) > 0, 'The index must reflect target-type ACF values written at priority 10 before projection at priority 20.');
        unset($GLOBALS['vgpc_test_acf_save_during_core_save']);
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
            ['key' => 'field_unrelated_notes', 'name' => 'unrelated_notes'],
        ]]);
        $GLOBALS['vgpc_test_posts'][$source_id] = (object) ['ID' => $source_id, 'post_type' => $source_type, 'post_status' => $status];
        foreach ($person_ids as $person_id) {
            $GLOBALS['vgpc_test_posts'][$person_id] = (object) ['ID' => $person_id, 'post_type' => 'person', 'post_status' => 'draft'];
        }
    }

    /** Model ACF_Form_Post::save_post(), acf_save_post(), and ACF's priority-10 value write. */
    private function acf_form_post_save(int $post_id): bool
    {
        if (! isset($GLOBALS['vgpc_test_posts'][$post_id])) return false;
        $form_post_id = (int) ($_POST['post_ID'] ?? 0);
        if ($form_post_id > 0 && $form_post_id !== $post_id) return false;
        if (empty($_POST['acf']) || ! is_array($_POST['acf'])) return false;

        $previous_actions = $GLOBALS['vgpc_test_actions']['acf/save_post'] ?? [];
        add_action('acf/save_post', static function (int $actual_post_id): void {
            acf_update_values($_POST['acf'] ?? [], $actual_post_id);
        }, 10, 1);
        try {
            do_action('acf/save_post', $post_id);
        } finally {
            $GLOBALS['vgpc_test_actions']['acf/save_post'] = $previous_actions;
        }
        return true;
    }
}
