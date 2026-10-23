<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Product {
    /** Allow the test harness to model replacement of WordPress's cron callback. */
    function remove_action(string $hook, callable|string $callback, int $priority = 10): bool
    {
        $actions = $GLOBALS['vgpc_test_actions'][$hook] ?? [];
        $remaining = array_values(array_filter(
            $actions,
            static fn (array $action): bool => ! (
                (int) ($action['priority'] ?? 10) === $priority
                && ($action['callback'] ?? null) === $callback
            )
        ));
        $removed = count($actions) !== count($remaining);
        $GLOBALS['vgpc_test_actions'][$hook] = $remaining;
        return $removed;
    }

    /** Model wp_insert_post's promotion of an overdue future row to publish. */
    function wp_update_post(array $postarr, bool $wp_error = false): int|\WP_Error
    {
        if (! empty($GLOBALS['vgpc_test_fail_scheduled_wp_update']) && ($postarr['post_status'] ?? null) === 'publish') {
            $GLOBALS['vgpc_test_fail_scheduled_wp_update'] = false;
            return new \WP_Error('injected_scheduled_update_failure', 'Injected scheduled update failure.');
        }
        $post_id = (int) ($postarr['ID'] ?? 0);
        $post = \get_post($post_id);
        if (
            $post
            && ($post->post_status ?? null) === 'future'
            && ! array_key_exists('post_status', $postarr)
            && strtotime((string) ($post->post_date_gmt ?? '') . ' GMT') <= time()
        ) {
            $postarr['post_status'] = 'publish';
        }
        return \wp_update_post($postarr, $wp_error);
    }

    function wp_clear_scheduled_hook(string $hook, array $args = []): int|false
    {
        $GLOBALS['vgpc_test_schedule_clears'][] = ['hook' => $hook, 'args' => $args];
        if (! empty($GLOBALS['vgpc_test_fail_next_schedule_clear'])) {
            $GLOBALS['vgpc_test_fail_next_schedule_clear'] = false;
            return false;
        }
        return 1;
    }

    function wp_schedule_single_event(int $timestamp, string $hook, array $args = []): bool
    {
        $GLOBALS['vgpc_test_schedule_attempts'] ??= [];
        $GLOBALS['vgpc_test_schedule_attempts'][] = ['timestamp' => $timestamp, 'hook' => $hook, 'args' => $args];
        if ((int) ($GLOBALS['vgpc_test_fail_schedule_attempts'] ?? 0) > 0) {
            --$GLOBALS['vgpc_test_fail_schedule_attempts'];
            return false;
        }
        $GLOBALS['vgpc_test_scheduled_events'][] = ['timestamp' => $timestamp, 'hook' => $hook, 'args' => $args];
        return true;
    }

    function wp_next_scheduled(string $hook, array $args = []): int|false
    {
        foreach ($GLOBALS['vgpc_test_scheduled_events'] ?? [] as $event) {
            if (($event['hook'] ?? null) === $hook && ($event['args'] ?? []) === $args) {
                return (int) $event['timestamp'];
            }
        }
        return false;
    }

    function error_log(string $message): bool
    {
        $GLOBALS['vgpc_test_error_log'] ??= [];
        $GLOBALS['vgpc_test_error_log'][] = $message;
        return true;
    }
}

namespace {

if (! function_exists('check_and_publish_future_post')) {
    function check_and_publish_future_post(int $post_id): void
    {
        $post = get_post($post_id);
        if ($post && ($post->post_status ?? null) === 'future') {
            $post->post_status = 'publish';
        }
    }
}

use Voelgoed\PlatformCore\Event\EventParticipationRepository;
use Voelgoed\PlatformCore\Product\ProductContributionAdmin;
use Voelgoed\PlatformCore\Product\ProductContributionPairLock;
use Voelgoed\PlatformCore\Product\ProductContributionRepository;
use Voelgoed\PlatformCore\Product\ProductContributionService;
use Voelgoed\PlatformCore\Product\ProductPeopleFields;
use Voelgoed\PlatformCore\Product\ProductRoleVocabulary;

final class ProductContributionAdminTest
{
    public function test_scheduled_future_publication_runs_through_canonical_validation(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $this->add_contribution(401, 'future', 101, 201);
        $GLOBALS['vgpc_test_posts'][401]->post_date_gmt = '2000-01-01 00:00:00';

        add_action('publish_future_post', 'check_and_publish_future_post', 10, 1);
        $admin = new ProductContributionAdmin($repository);
        $admin->register();
        $GLOBALS['vgpc_test_apply_wp_update_filter'] = true;

        vgpc_test_true(! in_array('check_and_publish_future_post', array_column($GLOBALS['vgpc_test_actions']['publish_future_post'] ?? [], 'callback'), true), 'The unguarded WordPress cron handler must be removed before the event runs. Registered: ' . var_export(array_column($GLOBALS['vgpc_test_actions']['publish_future_post'] ?? [], 'callback'), true));

        do_action('publish_future_post', 401);

        vgpc_test_same('future', $GLOBALS['vgpc_test_posts'][401]->post_status, 'Scheduled publication must leave an invalid contribution inactive.');
        vgpc_test_same([], $GLOBALS['vgpc_test_update_post_calls'], 'Rejected scheduled publication must not write the post status.');
        vgpc_test_true(! in_array('check_and_publish_future_post', array_column($GLOBALS['vgpc_test_actions']['publish_future_post'] ?? [], 'callback'), true), 'The unguarded WordPress cron handler must not remain registered.');
        do_action('shutdown');
    }

    public function test_valid_scheduled_future_publication_uses_guarded_post_update(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $roles = $this->approved_roles();
        $this->add_contribution(401, 'future', 101, 201);
        $GLOBALS['vgpc_test_posts'][401]->post_date_gmt = '2000-01-01 00:00:00';
        $GLOBALS['vgpc_test_relationships'][ProductRoleVocabulary::TAXONOMY][401] = [$roles['aanbieder']];

        add_action('publish_future_post', 'check_and_publish_future_post', 10, 1);
        $admin = new ProductContributionAdmin($repository);
        $admin->register();
        $GLOBALS['vgpc_test_apply_wp_update_filter'] = true;

        do_action('publish_future_post', 401);

        vgpc_test_same('publish', $GLOBALS['vgpc_test_posts'][401]->post_status, 'A valid due contribution must become active.');
        vgpc_test_same(401, $GLOBALS['vgpc_test_update_post_calls'][0]['ID'] ?? null, 'A scheduled contribution must use wp_update_post so canonical filters run.');
        vgpc_test_same('publish', $GLOBALS['vgpc_test_update_post_calls'][0]['post_status'] ?? null, 'The guarded update must transition the post to publish.');
        vgpc_test_same('vgpc_pc_' . substr(hash('sha256', $this->lock_scope('contribution', 401)), 0, 56), $GLOBALS['wpdb']->queries[0]['args'][0] ?? null, 'Scheduled transition must acquire the contribution-row lock first.');
        do_action('shutdown');
    }

    public function test_other_scheduled_post_types_still_use_the_wordpress_publish_handler(): void
    {
        [$repository] = $this->fixture();
        $this->add_endpoint(501, 'post', 'Scheduled article', 'future');
        add_action('publish_future_post', 'check_and_publish_future_post', 10, 1);
        $admin = new ProductContributionAdmin($repository);
        $admin->register();

        do_action('publish_future_post', 501);

        vgpc_test_same('publish', $GLOBALS['vgpc_test_posts'][501]->post_status, 'Non-contribution scheduled posts must keep WordPress publishing behavior.');
    }

    public function test_scheduled_publication_rechecks_status_after_acquiring_the_row_lock(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $roles = $this->approved_roles();
        $this->add_contribution(401, 'future', 101, 201);
        $GLOBALS['vgpc_test_posts'][401]->post_date_gmt = '2000-01-01 00:00:00';
        $GLOBALS['vgpc_test_relationships'][ProductRoleVocabulary::TAXONOMY][401] = [$roles['aanbieder']];
        add_action('publish_future_post', 'check_and_publish_future_post', 10, 1);
        $admin = new ProductContributionAdmin($repository);
        $admin->register();
        $GLOBALS['vgpc_test_apply_wp_update_filter'] = true;
        $row_lock = $this->expected_row_lock(401);
        $GLOBALS['wpdb']->get_var_handler = static function (string $query, array $args) use ($row_lock): string {
            if ($query === 'SELECT GET_LOCK(%s, %d)' && ($args[0] ?? null) === $row_lock) {
                // Simulate an editor completing a draft transition before cron acquires this row lock.
                $GLOBALS['vgpc_test_posts'][401]->post_status = 'draft';
            }
            return '1';
        };

        do_action('publish_future_post', 401);

        vgpc_test_same('draft', $GLOBALS['vgpc_test_posts'][401]->post_status, 'Cron must not overwrite a status transition that completed before its row lock.');
        vgpc_test_same([], $GLOBALS['vgpc_test_update_post_calls'], 'Cron must stop before writing when the fresh locked row is no longer future.');
        vgpc_test_same(
            [$row_lock],
            array_values(array_map(
                static fn (array $query): string => (string) ($query['args'][0] ?? ''),
                array_filter($GLOBALS['wpdb']->queries, static fn (array $query): bool => $query['query'] === 'SELECT GET_LOCK(%s, %d)')
            )),
            'A fresh non-future status must stop before acquiring the Product × Person pair lock.'
        );
        do_action('shutdown');
    }

    public function test_scheduled_publication_reschedules_when_date_moves_forward_under_lock(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $roles = $this->approved_roles();
        $this->add_contribution(401, 'future', 101, 201);
        $GLOBALS['vgpc_test_posts'][401]->post_date_gmt = '2000-01-01 00:00:00';
        $GLOBALS['vgpc_test_relationships'][ProductRoleVocabulary::TAXONOMY][401] = [$roles['aanbieder']];
        $new_date_gmt = gmdate('Y-m-d H:i:s', time() + 3600);
        $GLOBALS['vgpc_test_schedule_clears'] = [];
        $GLOBALS['vgpc_test_scheduled_events'] = [];
        add_action('publish_future_post', 'check_and_publish_future_post', 10, 1);
        $admin = new ProductContributionAdmin($repository);
        $admin->register();
        $GLOBALS['vgpc_test_apply_wp_update_filter'] = true;
        $row_lock = $this->expected_row_lock(401);
        $GLOBALS['wpdb']->get_var_handler = static function (string $query, array $args) use ($row_lock, $new_date_gmt): string {
            if ($query === 'SELECT GET_LOCK(%s, %d)' && ($args[0] ?? null) === $row_lock) {
                // Another editor rescheduled this contribution before cron acquired its row lock.
                $GLOBALS['vgpc_test_posts'][401]->post_date_gmt = $new_date_gmt;
            }
            return '1';
        };

        do_action('publish_future_post', 401);

        vgpc_test_same('future', $GLOBALS['vgpc_test_posts'][401]->post_status, 'A contribution rescheduled while cron waited must remain inactive.');
        vgpc_test_same([], $GLOBALS['vgpc_test_update_post_calls'], 'A not-yet-due contribution must not be published.');
        vgpc_test_same(
            [['timestamp' => strtotime($new_date_gmt . ' GMT'), 'hook' => 'publish_future_post', 'args' => [401]]],
            $GLOBALS['vgpc_test_scheduled_events'],
            'Cron must schedule the fresh post date after a concurrent reschedule.'
        );
        do_action('shutdown');
    }

    public function test_early_cron_event_uses_the_fresh_date_after_acquiring_the_row_lock(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $roles = $this->approved_roles();
        $this->add_contribution(401, 'future', 101, 201);
        $fresh_date_gmt = gmdate('Y-m-d H:i:s', time() + 3600);
        $stale_date_gmt = gmdate('Y-m-d H:i:s', time() + 10800);
        $GLOBALS['vgpc_test_posts'][401]->post_date_gmt = $fresh_date_gmt;
        $GLOBALS['vgpc_test_relationships'][ProductRoleVocabulary::TAXONOMY][401] = [$roles['aanbieder']];
        $GLOBALS['vgpc_test_enable_query_cache'] = true;
        $stale_post = clone $GLOBALS['vgpc_test_posts'][401];
        $stale_post->post_date_gmt = $stale_date_gmt;
        $GLOBALS['vgpc_test_get_post_cache'][401] = $stale_post;
        $GLOBALS['vgpc_test_schedule_clears'] = [];
        $GLOBALS['vgpc_test_scheduled_events'] = [];
        add_action('publish_future_post', 'check_and_publish_future_post', 10, 1);
        $admin = new ProductContributionAdmin($repository);
        $admin->register();
        $GLOBALS['vgpc_test_apply_wp_update_filter'] = true;

        do_action('publish_future_post', 401);

        vgpc_test_same(
            [['timestamp' => strtotime($fresh_date_gmt . ' GMT'), 'hook' => 'publish_future_post', 'args' => [401]]],
            $GLOBALS['vgpc_test_scheduled_events'],
            'An early cron callback must requeue using the fresh date instead of its stale cached date.'
        );
        vgpc_test_same(
            $this->expected_row_lock(401),
            $GLOBALS['wpdb']->queries[0]['args'][0] ?? null,
            'Cron must hold the contribution-row lock before replacing a scheduled event.'
        );
        vgpc_test_same('future', $GLOBALS['vgpc_test_posts'][401]->post_status, 'An early scheduled contribution must remain inactive.');
        do_action('shutdown');
    }

    public function test_scheduled_publication_retries_after_a_transient_row_lock_failure(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $roles = $this->approved_roles();
        $this->add_contribution(401, 'future', 101, 201);
        $GLOBALS['vgpc_test_posts'][401]->post_date_gmt = '2000-01-01 00:00:00';
        $GLOBALS['vgpc_test_relationships'][ProductRoleVocabulary::TAXONOMY][401] = [$roles['aanbieder']];
        $GLOBALS['vgpc_test_schedule_clears'] = [];
        $GLOBALS['vgpc_test_scheduled_events'] = [];
        add_action('publish_future_post', 'check_and_publish_future_post', 10, 1);
        $admin = new ProductContributionAdmin($repository);
        $admin->register();
        $GLOBALS['vgpc_test_apply_wp_update_filter'] = true;
        $row_lock = $this->expected_row_lock(401);
        $GLOBALS['wpdb']->get_var_handler = static fn (string $query, array $args): string =>
            $query === 'SELECT GET_LOCK(%s, %d)' && ($args[0] ?? null) === $row_lock ? '0' : '1';
        $started_at = time();

        do_action('publish_future_post', 401);

        vgpc_test_same('future', $GLOBALS['vgpc_test_posts'][401]->post_status, 'A transient row lock failure must leave the contribution inactive.');
        vgpc_test_same(1, count($GLOBALS['vgpc_test_scheduled_events']), 'A transient row lock failure must leave a retry event.');
        vgpc_test_true(
            $GLOBALS['vgpc_test_scheduled_events'][0]['timestamp'] >= $started_at + 59
            && $GLOBALS['vgpc_test_scheduled_events'][0]['timestamp'] <= $started_at + 61,
            'A transient row lock failure must retry after a short delay.'
        );
        do_action('shutdown');
    }

    public function test_scheduled_publication_retries_after_wp_update_post_error(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $roles = $this->approved_roles();
        $this->add_contribution(401, 'future', 101, 201);
        $GLOBALS['vgpc_test_posts'][401]->post_date_gmt = '2000-01-01 00:00:00';
        $GLOBALS['vgpc_test_relationships'][ProductRoleVocabulary::TAXONOMY][401] = [$roles['aanbieder']];
        $GLOBALS['vgpc_test_schedule_clears'] = [];
        $GLOBALS['vgpc_test_scheduled_events'] = [];
        $GLOBALS['vgpc_test_schedule_attempts'] = [];
        $GLOBALS['vgpc_test_fail_schedule_attempts'] = 1;
        $GLOBALS['vgpc_test_fail_scheduled_wp_update'] = true;
        add_action('publish_future_post', 'check_and_publish_future_post', 10, 1);
        $admin = new ProductContributionAdmin($repository);
        $admin->register();
        $GLOBALS['vgpc_test_apply_wp_update_filter'] = true;
        $started_at = time();

        do_action('publish_future_post', 401);

        vgpc_test_same('future', $GLOBALS['vgpc_test_posts'][401]->post_status, 'An update error must leave the contribution inactive.');
        vgpc_test_same(1, count($GLOBALS['vgpc_test_scheduled_events']), 'A failed scheduled status update must leave a retry event.');
        vgpc_test_same(2, count($GLOBALS['vgpc_test_schedule_attempts']), 'A failed schedule attempt must be followed by a retry attempt.');
        vgpc_test_true(
            $GLOBALS['vgpc_test_scheduled_events'][0]['timestamp'] >= $started_at + 59
            && $GLOBALS['vgpc_test_scheduled_events'][0]['timestamp'] <= $started_at + 61,
            'A failed scheduled status update must retry after a short delay.'
        );
        do_action('shutdown');
    }

    public function test_scheduled_publication_retries_after_transient_endpoint_read_failure(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $roles = $this->approved_roles();
        $this->add_contribution(401, 'future', 101, 201);
        $GLOBALS['vgpc_test_posts'][401]->post_date_gmt = '2000-01-01 00:00:00';
        $GLOBALS['vgpc_test_relationships'][ProductRoleVocabulary::TAXONOMY][401] = [$roles['aanbieder']];
        $GLOBALS['vgpc_test_schedule_clears'] = [];
        $GLOBALS['vgpc_test_scheduled_events'] = [];
        add_action('publish_future_post', 'check_and_publish_future_post', 10, 1);
        $admin = new ProductContributionAdmin($repository);
        $admin->register();
        $GLOBALS['vgpc_test_apply_wp_update_filter'] = true;
        $GLOBALS['vgpc_test_fail_get_post_id'] = 101;

        do_action('publish_future_post', 401);

        vgpc_test_same('future', $GLOBALS['vgpc_test_posts'][401]->post_status, 'An uncertain endpoint read must not publish the contribution.');
        vgpc_test_same(1, count($GLOBALS['vgpc_test_scheduled_events']), 'An uncertain endpoint read must leave a retry event.');
        do_action('shutdown');
    }

    public function test_scheduled_publication_retries_after_initial_post_read_error(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $roles = $this->approved_roles();
        $this->add_contribution(401, 'future', 101, 201);
        $GLOBALS['vgpc_test_posts'][401]->post_date_gmt = '2000-01-01 00:00:00';
        $GLOBALS['vgpc_test_relationships'][ProductRoleVocabulary::TAXONOMY][401] = [$roles['aanbieder']];
        $GLOBALS['vgpc_test_schedule_clears'] = [];
        $GLOBALS['vgpc_test_scheduled_events'] = [];
        add_action('publish_future_post', 'check_and_publish_future_post', 10, 1);
        $admin = new ProductContributionAdmin($repository);
        $admin->register();
        $GLOBALS['vgpc_test_fail_get_post_id'] = 401;

        do_action('publish_future_post', 401);

        vgpc_test_same('future', $GLOBALS['vgpc_test_posts'][401]->post_status, 'An uncertain initial post read must not publish.');
        vgpc_test_same(1, count($GLOBALS['vgpc_test_scheduled_events']), 'An initial database read error must leave a retry event.');
        do_action('shutdown');
    }

    public function test_scheduled_publication_retries_after_fresh_product_read_error(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $roles = $this->approved_roles();
        $this->add_contribution(401, 'future', 101, 201);
        $GLOBALS['vgpc_test_posts'][401]->post_date_gmt = '2000-01-01 00:00:00';
        $GLOBALS['vgpc_test_relationships'][ProductRoleVocabulary::TAXONOMY][401] = [$roles['aanbieder']];
        $GLOBALS['vgpc_test_schedule_clears'] = [];
        $GLOBALS['vgpc_test_scheduled_events'] = [];
        add_action('publish_future_post', 'check_and_publish_future_post', 10, 1);
        $admin = new ProductContributionAdmin($repository);
        $admin->register();
        $GLOBALS['vgpc_test_apply_wp_update_filter'] = true;
        $GLOBALS['vgpc_test_fail_get_post_id'] = 101;

        do_action('publish_future_post', 401);

        vgpc_test_same('future', $GLOBALS['vgpc_test_posts'][401]->post_status, 'A Product read error must not publish the contribution.');
        vgpc_test_same(1, count($GLOBALS['vgpc_test_scheduled_events']), 'A transient fresh Product read error must leave a retry event.');
        do_action('shutdown');
    }

    public function test_scheduled_publication_retries_after_native_role_read_error(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $roles = $this->approved_roles();
        $this->add_contribution(401, 'future', 101, 201);
        $GLOBALS['vgpc_test_posts'][401]->post_date_gmt = '2000-01-01 00:00:00';
        $GLOBALS['vgpc_test_relationships'][ProductRoleVocabulary::TAXONOMY][401] = [$roles['aanbieder']];
        $GLOBALS['vgpc_test_schedule_clears'] = [];
        $GLOBALS['vgpc_test_scheduled_events'] = [];
        add_action('publish_future_post', 'check_and_publish_future_post', 10, 1);
        $admin = new ProductContributionAdmin($repository);
        $admin->register();
        $GLOBALS['vgpc_test_apply_wp_update_filter'] = true;
        $GLOBALS['vgpc_test_fail_stored_role_read'] = true;

        do_action('publish_future_post', 401);

        vgpc_test_same('future', $GLOBALS['vgpc_test_posts'][401]->post_status, 'A failed role read must not be treated as a role-less valid publication.');
        vgpc_test_same([], $GLOBALS['vgpc_test_update_post_calls'], 'A failed role read must stop scheduled publication.');
        vgpc_test_same(1, count($GLOBALS['vgpc_test_scheduled_events']), 'A failed role read must leave a retry event.');
        do_action('shutdown');
    }

    public function test_failed_cron_event_clear_is_reported_while_replacement_is_scheduled(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $roles = $this->approved_roles();
        $this->add_contribution(401, 'future', 101, 201);
        $GLOBALS['vgpc_test_posts'][401]->post_date_gmt = '2000-01-01 00:00:00';
        $GLOBALS['vgpc_test_relationships'][ProductRoleVocabulary::TAXONOMY][401] = [$roles['aanbieder']];
        $GLOBALS['vgpc_test_schedule_clears'] = [];
        $GLOBALS['vgpc_test_scheduled_events'] = [];
        $GLOBALS['vgpc_test_error_log'] = [];
        $GLOBALS['vgpc_test_fail_next_schedule_clear'] = true;
        add_action('publish_future_post', 'check_and_publish_future_post', 10, 1);
        $admin = new ProductContributionAdmin($repository);
        $admin->register();
        $GLOBALS['vgpc_test_apply_wp_update_filter'] = true;
        $row_lock = $this->expected_row_lock(401);
        $new_date_gmt = gmdate('Y-m-d H:i:s', time() + 3600);
        $GLOBALS['wpdb']->get_var_handler = static function (string $query, array $args) use ($row_lock, $new_date_gmt): string {
            if ($query === 'SELECT GET_LOCK(%s, %d)' && ($args[0] ?? null) === $row_lock) {
                $GLOBALS['vgpc_test_posts'][401]->post_date_gmt = $new_date_gmt;
            }
            return '1';
        };

        do_action('publish_future_post', 401);

        vgpc_test_same(1, count($GLOBALS['vgpc_test_scheduled_events']), 'A failed clear must not prevent a replacement event from being scheduled.');
        vgpc_test_true(
            count($GLOBALS['vgpc_test_error_log']) === 1 && str_contains($GLOBALS['vgpc_test_error_log'][0], 'clear'),
            'The cron clear failure must be recorded for operators.'
        );
        do_action('shutdown');
    }

    public function test_title_sync_does_not_publish_a_due_future_contribution(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $this->add_contribution(401, 'future', 101, 201);
        $GLOBALS['vgpc_test_posts'][401]->post_date_gmt = '2000-01-01 00:00:00';
        $this->new_admin($repository);
        $GLOBALS['vgpc_test_apply_wp_update_filter'] = true;

        wp_update_post(['ID' => 101, 'post_title' => 'New Product Name'], true);

        vgpc_test_same('New Product Name — Mia Botha', get_the_title(401), 'Title synchronization must still update the derived title on a future contribution.');
        vgpc_test_same('future', $GLOBALS['vgpc_test_posts'][401]->post_status, 'A title-only endpoint rename must not publish a due future contribution without role validation.');
        do_action('shutdown');
    }

    public function test_empty_acf_role_value_is_allowed_for_a_draft_but_not_publish(): void
    {
        [$repository] = $this->fixture();
        $admin = new ProductContributionAdmin($repository);
        $_POST['post_status'] = 'draft';

        $draft_valid = $admin->validate_roles_field(
            true,
            '',
            ['key' => ProductContributionAdmin::ROLES_FIELD_KEY],
            'acf[roles]'
        );

        $_POST['post_status'] = 'publish';
        $publish_valid = $admin->validate_roles_field(
            true,
            '',
            ['key' => ProductContributionAdmin::ROLES_FIELD_KEY],
            'acf[roles]'
        );

        vgpc_test_same(true, $draft_valid, 'An empty ACF role selection must normalize to an empty list for drafts.');
        vgpc_test_true(is_string($publish_valid) && str_contains($publish_valid, 'at least one approved Product Role'), 'An empty ACF role selection must still be rejected for publication.');
    }

    public function test_existing_edit_acquires_row_lock_before_fresh_endpoints_then_target_pair_lock(): void
    {
        [$repository, $product_fields] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_product(102, 'manual');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $this->add_endpoint(202, 'person', 'New Person');
        $this->add_contribution(401, 'draft', 101, 201);
        $admin = new ProductContributionAdmin($repository);
        $admin->register();
        $this->install_lookup_and_lock_order();
        $_POST['acf'] = [$product_fields['product'] => '102'];

        $result = $admin->validate_pair_before_post_save(
            ['post_type' => ProductContributionRepository::POST_TYPE, 'post_status' => 'draft'],
            ['ID' => 401]
        );

        vgpc_test_same('draft', $result['post_status'], 'A valid partial draft update must pass post-save validation.');
        vgpc_test_same('vgpc_pc_' . substr(hash('sha256', $this->lock_scope('contribution', 401)), 0, 56), $GLOBALS['wpdb']->queries[0]['args'][0], 'The contribution-row lock must be acquired first.');
        vgpc_test_same($this->expected_pair_lock(102, 202), $GLOBALS['wpdb']->queries[1]['args'][0], 'The pair lock must use the fresh stored Person resolved after the row lock.');
        vgpc_test_same(
            ['lock', 'lock', 'fresh-pair-check'],
            array_column($GLOBALS['vgpc_test_write_events'], 'type'),
            'The fresh duplicate check must follow the row lock and the resolved target pair lock.'
        );
        vgpc_test_same(['product_id' => 102, 'person_id' => 202, 'matches' => []], end($GLOBALS['vgpc_test_fresh_pair_lookups']), 'The fresh duplicate check must use the post-lock stored endpoint.');
        do_action('shutdown');
    }

    public function test_two_existing_rows_converging_on_one_pair_have_only_one_winner(): void
    {
        [$repository, $product_fields] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_product(102, 'manual');
        $this->add_product(104, 'manual');
        $this->add_endpoint(202, 'person', 'Mia Botha');
        $this->add_contribution(401, 'draft', 101, 202);
        $this->add_contribution(402, 'draft', 104, 202);
        $this->install_lookup_and_lock_order();

        $lock_a = new ProductContributionPairLock();
        $admin_a = new ProductContributionAdmin($repository, new ProductContributionService($repository, $lock_a), $lock_a);
        $admin_a->register();
        $_POST['acf'] = [$product_fields['product'] => '102'];
        $first = $admin_a->validate_pair_before_post_save(
            ['post_type' => ProductContributionRepository::POST_TYPE, 'post_status' => 'draft'],
            ['ID' => 401]
        );
        vgpc_test_same('draft', $first['post_status'], 'The first edit must be allowed to claim the target pair.');

        // Persist request A while its locks are held, then release them before request B starts.
        update_field('field_vg_product_contribution_product', 102, 401);
        do_action('shutdown');

        $lock_b = new ProductContributionPairLock();
        $admin_b = new ProductContributionAdmin($repository, new ProductContributionService($repository, $lock_b), $lock_b);
        $admin_b->register();
        $_POST['acf'] = [$product_fields['product'] => '102'];
        $rejected = false;
        try {
            $admin_b->validate_pair_before_post_save(
                ['post_type' => ProductContributionRepository::POST_TYPE, 'post_status' => 'draft'],
                ['ID' => 402]
            );
        } catch (RuntimeException $error) {
            $rejected = str_contains($error->getMessage(), 'already has');
        }

        vgpc_test_true($rejected, 'Request B must fail after acquiring the target pair lock and reading request A\'s stored pair.');
        vgpc_test_same(102, (int) $GLOBALS['vgpc_test_meta'][401][ProductContributionRepository::PRODUCT_FIELD], 'Only request A may own the target pair.');
        vgpc_test_same(104, (int) $GLOBALS['vgpc_test_meta'][402][ProductContributionRepository::PRODUCT_FIELD], 'The rejected request must leave its original pair unchanged.');
        vgpc_test_same(
            ['product_id' => 102, 'person_id' => 202, 'matches' => [401]],
            end($GLOBALS['vgpc_test_fresh_pair_lookups']),
            'Request B must see request A\'s freshly persisted pair after acquiring the same target lock.'
        );
        vgpc_test_same(
            $this->expected_pair_lock(102, 202),
            $GLOBALS['wpdb']->queries[count($GLOBALS['wpdb']->queries) - 1]['args'][0],
            'Request B must acquire the same target Product × Person lock before its fresh check.'
        );
        do_action('shutdown');
    }

    public function test_lifecycle_statuses_control_activity_and_pair_reservation(): void
    {
        foreach (['publish', 'draft', 'pending', 'private', 'future', 'trash', 'auto-draft'] as $status) {
            [$repository] = $this->fixture();
            $this->add_product(101, 'manual');
            $this->add_endpoint(201, 'person', 'Mia Botha');
            $roles = $this->approved_roles();
            $this->add_contribution(401, $status, 101, 201);
            if ($status === 'publish') {
                $GLOBALS['vgpc_test_relationships'][ProductRoleVocabulary::TAXONOMY][401] = [$roles['aanbieder']];
            }

            $active_ids = $repository->contributions_for_product(101);
            $pair_ids = $repository->find_pair_ids(101, 201);
            $is_active = $status === 'publish';
            $reserves_pair = in_array($status, ProductContributionRepository::UNIQUENESS_STATUSES, true);
            vgpc_test_same($is_active ? [401] : [], $active_ids, sprintf('%s rows must follow the active lifecycle rule.', $status));
            vgpc_test_same($reserves_pair ? [401] : [], $pair_ids, sprintf('%s rows must follow the pair-reservation rule.', $status));
        }
    }

    public function test_published_row_can_move_to_draft_pending_or_private_and_still_reserves_its_pair(): void
    {
        foreach (['draft', 'pending', 'private', 'future'] as $target_status) {
            [$repository] = $this->fixture();
            $this->add_product(101, 'manual');
            $this->add_endpoint(201, 'person', 'Mia Botha');
            $roles = $this->approved_roles();
            $this->add_contribution(401, 'publish', 101, 201);
            $GLOBALS['vgpc_test_relationships'][ProductRoleVocabulary::TAXONOMY][401] = [$roles['aanbieder']];
            $admin = $this->new_admin($repository);
            $_POST['acf'] = [];

            $result = $admin->validate_pair_before_post_save(
                ['post_type' => ProductContributionRepository::POST_TYPE, 'post_status' => $target_status],
                ['ID' => 401]
            );

            vgpc_test_same($target_status, $result['post_status'], sprintf('A published row must be allowed to move to %s.', $target_status));
            $GLOBALS['vgpc_test_posts'][401]->post_status = $target_status;
            vgpc_test_same([], $repository->contributions_for_product(101), sprintf('%s rows must be inactive.', $target_status));
            vgpc_test_same([401], $repository->find_pair_ids(101, 201), sprintf('%s rows must keep reserving their pair.', $target_status));
            do_action('shutdown');
        }
    }

    public function test_incomplete_draft_and_auto_draft_placeholder_do_not_claim_a_pair(): void
    {
        [$repository] = $this->fixture();
        $admin = $this->new_admin($repository);
        $_POST['acf'] = [];

        $draft = $admin->validate_pair_before_post_save(
            ['post_type' => ProductContributionRepository::POST_TYPE, 'post_status' => 'draft'],
            []
        );
        $placeholder = $admin->validate_pair_before_post_save(
            ['post_type' => ProductContributionRepository::POST_TYPE, 'post_status' => 'auto-draft'],
            []
        );

        vgpc_test_same('draft', $draft['post_status'], 'An incomplete draft may save without endpoints or roles.');
        vgpc_test_same('auto-draft', $placeholder['post_status'], 'WordPress auto-drafts must pass as placeholders.');
        vgpc_test_same([], $GLOBALS['wpdb']->queries, 'Incomplete drafts and placeholders must not acquire pair locks.');
        vgpc_test_same([], $repository->find_pair_ids(101, 201), 'An auto-draft placeholder must not reserve a pair.');
    }

    public function test_future_row_reserves_pair_for_a_new_admin_save(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $this->add_contribution(401, 'future', 101, 201);
        $admin = $this->new_admin($repository);
        $_POST['acf'] = $this->submitted_pair(101, 201);
        $rejected = false;
        try {
            $admin->validate_pair_before_post_save(
                ['post_type' => ProductContributionRepository::POST_TYPE, 'post_status' => 'draft'],
                []
            );
        } catch (RuntimeException $error) {
            $rejected = str_contains($error->getMessage(), 'already has');
        }

        vgpc_test_true($rejected, 'A future-dated row must prevent another row for the same pair.');
        vgpc_test_same([], $repository->contributions_for_product(101), 'A future row must remain inactive.');
        do_action('shutdown');
    }

    public function test_trash_transition_succeeds_when_product_is_deleted(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $this->add_contribution(401, 'publish', 101, 201);
        unset($GLOBALS['vgpc_test_posts'][101]);
        $admin = $this->new_admin($repository);
        $_POST['acf'] = [];

        $result = $admin->validate_pair_before_post_save(
            ['post_type' => ProductContributionRepository::POST_TYPE, 'post_status' => 'trash'],
            ['ID' => 401]
        );

        vgpc_test_same('trash', $result['post_status'], 'A deleted Product must not block retirement.');
        vgpc_test_same(1, count($GLOBALS['wpdb']->queries), 'Retirement must acquire only the contribution-row lock.');
        vgpc_test_same([], $GLOBALS['vgpc_test_fresh_pair_lookups'], 'Retirement must skip endpoint and duplicate validation.');
        do_action('shutdown');
    }

    public function test_trash_transition_succeeds_when_person_is_trashed(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_endpoint(201, 'person', 'Mia Botha', 'trash');
        $this->add_contribution(401, 'publish', 101, 201);
        $admin = $this->new_admin($repository);

        $result = $admin->validate_pair_before_post_save(
            ['post_type' => ProductContributionRepository::POST_TYPE, 'post_status' => 'trash'],
            ['ID' => 401]
        );

        vgpc_test_same('trash', $result['post_status'], 'A trashed Person must not block retirement.');
        vgpc_test_same(1, count($GLOBALS['wpdb']->queries), 'The contribution-row lock must be the only database call.');
        vgpc_test_same([], $GLOBALS['vgpc_test_fresh_pair_lookups'], 'Retirement must skip fresh pair validation.');
        do_action('shutdown');
    }

    public function test_trash_transition_succeeds_with_invalid_roles_or_invalid_product_mode(): void
    {
        foreach (['invalid-role', 'invalid-mode'] as $broken_state) {
            [$repository] = $this->fixture();
            $this->add_product(101, $broken_state === 'invalid-mode' ? 'broken' : 'manual');
            $this->add_endpoint(201, 'person', 'Mia Botha');
            $this->add_contribution(401, 'publish', 101, 201);
            if ($broken_state === 'invalid-role') {
                $fifth = vgpc_test_add_term('Mentor', 'mentor', 0, ProductRoleVocabulary::TAXONOMY);
                $GLOBALS['vgpc_test_relationships'][ProductRoleVocabulary::TAXONOMY][401] = [$fifth->term_id];
            }
            $admin = $this->new_admin($repository);

            $result = $admin->validate_pair_before_post_save(
                ['post_type' => ProductContributionRepository::POST_TYPE, 'post_status' => 'trash'],
                ['ID' => 401]
            );

            vgpc_test_same('trash', $result['post_status'], sprintf('A row with %s must still be retired.', $broken_state));
            vgpc_test_same([], $GLOBALS['vgpc_test_fresh_pair_lookups'], 'Retirement must bypass role and mode checks.');
            do_action('shutdown');
        }
    }

    public function test_trash_transition_succeeds_when_event_membership_is_stale(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'event', 301);
        $this->add_endpoint(301, 'tc_events', 'Source Event');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $this->add_event_participation(501, 'draft', 301, 201);
        $this->add_contribution(401, 'publish', 101, 201);
        $admin = $this->new_admin($repository);

        $result = $admin->validate_pair_before_post_save(
            ['post_type' => ProductContributionRepository::POST_TYPE, 'post_status' => 'trash'],
            ['ID' => 401]
        );

        vgpc_test_same('trash', $result['post_status'], 'Stale source-Event membership must not block retirement.');
        vgpc_test_same([], $GLOBALS['vgpc_test_fresh_pair_lookups'], 'Retirement must skip Event-membership checks.');
        do_action('shutdown');
    }

    public function test_acf_validators_allow_invalid_fields_during_trash_transition(): void
    {
        [$repository] = $this->fixture();
        $admin = $this->new_admin($repository);
        $_POST['post_ID'] = '401';
        $_POST['post_status'] = 'trash';
        $_POST['acf'] = [
            'field_vg_product_contribution_product' => '999',
            'field_vg_product_contribution_person' => '998',
            'field_vg_product_contribution_roles' => ['999'],
        ];

        foreach (['field_vg_product_contribution_product', 'field_vg_product_contribution_person', 'field_vg_product_contribution_roles'] as $field_key) {
            $valid = apply_filters('acf/validate_value/key=' . $field_key, true, '999', ['key' => $field_key], 'acf[' . $field_key . ']');
            vgpc_test_same(true, $valid, 'ACF validation must not prevent retirement from broken historical state.');
        }
    }

    public function test_admin_publish_uses_shared_role_mode_and_event_membership_validation(): void
    {
        foreach (['missing-role', 'invalid-mode', 'stale-event-membership'] as $case) {
            [$repository] = $this->fixture();
            $mode = $case === 'stale-event-membership' ? 'event' : ($case === 'invalid-mode' ? '' : 'manual');
            $this->add_product(101, $mode, $case === 'stale-event-membership' ? 301 : 0);
            $this->add_endpoint(301, 'tc_events', 'Source Event');
            $this->add_endpoint(201, 'person', 'Mia Botha');
            $roles = $this->approved_roles();
            $submitted_roles = $case === 'missing-role' ? [] : [$roles['aanbieder']];
            $admin = $this->new_admin($repository);
            $_POST['acf'] = $this->submitted_pair(101, 201, $submitted_roles);
            $rejected = false;

            try {
                $admin->validate_pair_before_post_save(
                    ['post_type' => ProductContributionRepository::POST_TYPE, 'post_status' => 'publish'],
                    []
                );
            } catch (RuntimeException $error) {
                $rejected = true;
            }

            vgpc_test_true($rejected, sprintf('Admin publish must reject %s through shared service validation.', $case));
            vgpc_test_same([], $GLOBALS['vgpc_test_posts'][5000] ?? [], 'Rejected admin publication must not create a canonical row.');
            do_action('shutdown');
        }
    }

    public function test_acf_field_validators_reject_invalid_product_and_person_endpoints(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $this->new_admin($repository);
        $_POST['post_status'] = 'publish';
        $_POST['acf'] = [
            'field_vg_product_contribution_product' => '999',
            'field_vg_product_contribution_person' => '201',
        ];

        $product_result = apply_filters(
            'acf/validate_value/key=field_vg_product_contribution_product',
            true,
            '999',
            ['key' => 'field_vg_product_contribution_product'],
            'acf[field_vg_product_contribution_product]'
        );
        $person_result = apply_filters(
            'acf/validate_value/key=field_vg_product_contribution_person',
            true,
            '999',
            ['key' => 'field_vg_product_contribution_person'],
            'acf[field_vg_product_contribution_person]'
        );

        vgpc_test_true(is_string($product_result) && $product_result !== '', 'The Product field validator must report a missing Product.');
        vgpc_test_true(is_string($person_result) && $person_result !== '', 'The Person field validator must report a missing Person.');
    }

    public function test_restore_from_trash_runs_publish_guards_and_reactivates_a_valid_row(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $roles = $this->approved_roles();
        $this->add_contribution(401, 'trash', 101, 201);
        $GLOBALS['vgpc_test_relationships'][ProductRoleVocabulary::TAXONOMY][401] = [$roles['aanbieder']];
        $admin = $this->new_admin($repository);
        $_POST['acf'] = $this->submitted_pair(101, 201, [$roles['aanbieder']]);

        $result = $admin->validate_pair_before_post_save(
            ['post_type' => ProductContributionRepository::POST_TYPE, 'post_status' => 'publish'],
            ['ID' => 401]
        );

        vgpc_test_same('publish', $result['post_status'], 'A valid restore must run and pass publish validation.');
        $GLOBALS['vgpc_test_posts'][401]->post_status = 'publish';
        vgpc_test_same([401], $repository->contributions_for_product(101), 'A valid restored row must become active.');
        do_action('shutdown');
    }

    public function test_restore_from_trash_fails_if_another_row_reserves_the_pair(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $roles = $this->approved_roles();
        $this->add_contribution(401, 'trash', 101, 201);
        $this->add_contribution(402, 'pending', 101, 201);
        $admin = $this->new_admin($repository);
        $_POST['acf'] = $this->submitted_pair(101, 201, [$roles['aanbieder']]);
        $rejected = false;
        try {
            $admin->validate_pair_before_post_save(
                ['post_type' => ProductContributionRepository::POST_TYPE, 'post_status' => 'publish'],
                ['ID' => 401]
            );
        } catch (RuntimeException $error) {
            $rejected = str_contains($error->getMessage(), 'already has');
        }

        vgpc_test_true($rejected, 'A restore must run duplicate validation against pair-reserving statuses.');
        vgpc_test_same('trash', get_post_status(401), 'A rejected restore must leave the row retired.');
        do_action('shutdown');
    }

    public function test_trashing_a_row_frees_its_pair_for_reuse(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $roles = $this->approved_roles();
        $this->add_contribution(401, 'publish', 101, 201);
        $admin = $this->new_admin($repository);
        $_POST['acf'] = [];
        $admin->validate_pair_before_post_save(
            ['post_type' => ProductContributionRepository::POST_TYPE, 'post_status' => 'trash'],
            ['ID' => 401]
        );
        $GLOBALS['vgpc_test_posts'][401]->post_status = 'trash';
        do_action('shutdown');

        $service = new ProductContributionService($repository, new ProductContributionPairLock());
        $new_id = $service->create(101, 201, [$roles['aanbieder']]);

        vgpc_test_true(is_int($new_id), 'A retired Product × Person pair must be available for reuse.');
        vgpc_test_same([5000], $repository->find_pair_ids(101, 201), 'Only the new non-retired contribution may reserve the pair.');
    }

    public function test_product_rename_updates_related_contribution_title_under_row_lock(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $this->add_contribution(401, 'publish', 101, 201);
        $this->new_admin($repository);

        wp_update_post(['ID' => 101, 'post_title' => 'New Product Name'], true);

        vgpc_test_same('New Product Name — Mia Botha', get_the_title(401), 'A Product rename must update the derived contribution title.');
        vgpc_test_same(2, count($GLOBALS['vgpc_test_update_post_calls']), 'The Product and contribution title must each be written once.');
        vgpc_test_same($this->expected_row_lock(401), $GLOBALS['wpdb']->queries[0]['args'][0], 'Title sync must lock the contribution row first.');
        do_action('shutdown');
    }

    public function test_person_rename_updates_related_contribution_title_under_row_lock(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $this->add_contribution(401, 'publish', 101, 201);
        $this->new_admin($repository);

        wp_update_post(['ID' => 201, 'post_title' => 'Mia van der Merwe'], true);

        vgpc_test_same('Product 101 — Mia van der Merwe', get_the_title(401), 'A Person rename must update the derived contribution title.');
        vgpc_test_same(2, count($GLOBALS['vgpc_test_update_post_calls']), 'The Person and contribution title must each be written once.');
        vgpc_test_same($this->expected_row_lock(401), $GLOBALS['wpdb']->queries[0]['args'][0], 'Person title sync must lock the contribution row first.');
        do_action('shutdown');
    }

    public function test_unchanged_endpoint_title_does_not_write_a_contribution_title(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $this->add_contribution(401, 'publish', 101, 201);
        $this->new_admin($repository);

        wp_update_post(['ID' => 101, 'post_title' => 'Product 101'], true);

        vgpc_test_same('Stored title', get_the_title(401), 'A no-op endpoint title save must not change the contribution title.');
        vgpc_test_same(1, count($GLOBALS['vgpc_test_update_post_calls']), 'Only the unchanged Product save may be recorded.');
        vgpc_test_same([], $GLOBALS['wpdb']->queries, 'An unchanged endpoint title must not acquire a row lock.');
    }

    public function test_title_sync_skips_trashed_and_auto_draft_contributions(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $this->add_contribution(401, 'trash', 101, 201);
        $this->add_contribution(402, 'auto-draft', 101, 201);
        $this->new_admin($repository);

        wp_update_post(['ID' => 101, 'post_title' => 'New Product Name'], true);

        vgpc_test_same('Stored title', get_the_title(401), 'A trashed contribution must not receive a title update.');
        vgpc_test_same('Stored title', get_the_title(402), 'An auto-draft placeholder must not receive a title update.');
        vgpc_test_same(1, count($GLOBALS['vgpc_test_update_post_calls']), 'Only the Product update may be recorded.');
        vgpc_test_same([], $GLOBALS['wpdb']->queries, 'Retired and placeholder rows must not acquire title-sync locks.');
    }

    public function test_title_sync_does_not_require_valid_roles_or_current_event_membership(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'event', 301);
        $this->add_endpoint(301, 'tc_events', 'Source Event');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $this->add_contribution(401, 'publish', 101, 201);
        $fifth = vgpc_test_add_term('Mentor', 'mentor', 0, ProductRoleVocabulary::TAXONOMY);
        $GLOBALS['vgpc_test_relationships'][ProductRoleVocabulary::TAXONOMY][401] = [$fifth->term_id];
        $this->new_admin($repository);
        $GLOBALS['vgpc_test_apply_wp_update_filter'] = true;

        wp_update_post(['ID' => 101, 'post_title' => 'Renamed Product'], true);
        wp_update_post(['ID' => 201, 'post_title' => 'Renamed Person'], true);

        vgpc_test_same('Renamed Product — Renamed Person', get_the_title(401), 'Title sync must work with invalid roles and stale Event membership.');
        vgpc_test_same(4, count($GLOBALS['vgpc_test_update_post_calls']), 'Product, Person, and both derived titles must be written.');
        vgpc_test_same(2, count(array_filter(
            $GLOBALS['wpdb']->queries,
            static fn (array $query): bool => $query['query'] === 'SELECT GET_LOCK(%s, %d)'
        )), 'Each title-sync pass must acquire the contribution-row lock.');
        do_action('shutdown');
    }

    public function test_title_sync_uses_stored_endpoints_read_after_the_row_lock(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_product(102, 'manual');
        $GLOBALS['vgpc_test_posts'][101]->post_title = 'Old Product';
        $GLOBALS['vgpc_test_posts'][102]->post_title = 'Current Product';
        $this->add_endpoint(201, 'person', 'Old Person');
        $this->add_endpoint(202, 'person', 'Current Person');
        $this->add_contribution(401, 'publish', 101, 201);
        $this->new_admin($repository);
        $GLOBALS['wpdb']->get_var_handler = function (string $query): string {
            if ($query === 'SELECT GET_LOCK(%s, %d)') {
                $GLOBALS['vgpc_test_meta'][401][ProductContributionRepository::PRODUCT_FIELD] = 102;
                $GLOBALS['vgpc_test_meta'][401][ProductContributionRepository::PERSON_FIELD] = 202;
            }
            return '1';
        };

        wp_update_post(['ID' => 101, 'post_title' => 'Renamed Old Product'], true);

        vgpc_test_same('Current Product — Current Person', get_the_title(401), 'Title sync must resolve current endpoints after acquiring the row lock.');
        vgpc_test_same(102, (int) get_field(ProductContributionRepository::PRODUCT_FIELD, 401), 'Title sync must preserve the current Product endpoint.');
        vgpc_test_same(202, (int) get_field(ProductContributionRepository::PERSON_FIELD, 401), 'Title sync must preserve the current Person endpoint.');
        do_action('shutdown');
    }

    public function test_editor_supplied_title_is_replaced_by_the_derived_title_on_acf_save(): void
    {
        [$repository] = $this->fixture();
        $this->add_product(101, 'manual');
        $this->add_endpoint(201, 'person', 'Mia Botha');
        $this->add_contribution(401, 'draft', 101, 201);
        $admin = $this->new_admin($repository);
        $_POST['acf'] = $this->submitted_pair(101, 201);
        $post_data = $admin->validate_pair_before_post_save(
            ['post_type' => ProductContributionRepository::POST_TYPE, 'post_status' => 'draft', 'post_title' => 'Editor title'],
            ['ID' => 401]
        );
        vgpc_test_same(2, count(array_filter(
            $GLOBALS['wpdb']->queries,
            static fn (array $query): bool => $query['query'] === 'SELECT GET_LOCK(%s, %d)'
        )), 'The row and pair locks must remain held before the WordPress and ACF writes.');
        vgpc_test_same(0, count(array_filter(
            $GLOBALS['wpdb']->queries,
            static fn (array $query): bool => $query['query'] === 'SELECT RELEASE_LOCK(%s)'
        )), 'Save locks must not be released before ACF writes complete.');

        wp_update_post(['ID' => 401, 'post_title' => $post_data['post_title']], true);
        update_field('field_vg_product_contribution_product', 101, 401);
        update_field('field_vg_product_contribution_person', 201, 401);
        do_action('acf/save_post', 401);

        vgpc_test_same('Product 101 — Mia Botha', get_the_title(401), 'The saved title must follow Product and Person, not editor input.');
        vgpc_test_same(2, count(array_filter(
            $GLOBALS['wpdb']->queries,
            static fn (array $query): bool => $query['query'] === 'SELECT RELEASE_LOCK(%s)'
        )), 'The save must release both locks after the ACF save completes.');
        do_action('shutdown');
    }

    private function fixture(): array
    {
        vgpc_test_reset();
        acf_add_local_field_group([
            'key' => 'group_vgpc_admin_test',
            'fields' => [
                ['key' => 'field_vg_product_contribution_product', 'name' => ProductContributionRepository::PRODUCT_FIELD],
                ['key' => 'field_vg_product_contribution_person', 'name' => ProductContributionRepository::PERSON_FIELD],
                ['key' => 'field_vg_product_contribution_roles', 'name' => 'product_contribution_roles'],
                ['key' => 'field_vg_product_contribution_display_order', 'name' => ProductContributionRepository::DISPLAY_ORDER_FIELD],
                ['key' => 'field_vgpc_product_mode', 'name' => ProductPeopleFields::MODE_FIELD],
                ['key' => 'field_vgpc_source_event', 'name' => ProductContributionRepository::SOURCE_EVENT_FIELD],
                ['key' => 'field_vgpc_event_id', 'name' => EventParticipationRepository::EVENT_FIELD],
                ['key' => 'field_vgpc_event_person', 'name' => EventParticipationRepository::PERSON_FIELD],
                ['key' => 'field_vgpc_event_order', 'name' => EventParticipationRepository::DISPLAY_ORDER_FIELD],
            ],
        ]);
        register_taxonomy(ProductRoleVocabulary::TAXONOMY, [ProductContributionRepository::POST_TYPE]);
        register_taxonomy(EventParticipationRepository::ROLE_TAXONOMY, [EventParticipationRepository::POST_TYPE]);
        $repository = new ProductContributionRepository();
        $this->install_fresh_pair_lookup();
        return [$repository, ['product' => 'field_vg_product_contribution_product', 'person' => 'field_vg_product_contribution_person']];
    }

    private function new_admin(ProductContributionRepository $repository): ProductContributionAdmin
    {
        $lock = new ProductContributionPairLock();
        $admin = new ProductContributionAdmin($repository, new ProductContributionService($repository, $lock), $lock);
        $admin->register();
        return $admin;
    }

    private function install_fresh_pair_lookup(): void
    {
        $GLOBALS['vgpc_test_fresh_pair_lookups'] = [];
        $GLOBALS['wpdb']->get_col_handler = static function (string $query, array $args): array {
            if (! str_contains($query, 'SELECT DISTINCT contribution.ID')) {
                return [];
            }
            $product_index = array_search(ProductContributionRepository::PRODUCT_FIELD, $args, true);
            $person_index = array_search(ProductContributionRepository::PERSON_FIELD, $args, true);
            if (! is_int($product_index) || ! is_int($person_index)) {
                return [];
            }
            $product_id = (int) ($args[$product_index + 1] ?? 0);
            $person_id = (int) ($args[$person_index + 1] ?? 0);
            $exclude_id = str_contains($query, 'contribution.ID <> %d') ? (int) end($args) : 0;
            $matches = [];
            foreach ($GLOBALS['vgpc_test_posts'] as $id => $post) {
                if (
                    ($post->post_type ?? '') === ProductContributionRepository::POST_TYPE
                    && in_array((string) ($post->post_status ?? ''), ProductContributionRepository::UNIQUENESS_STATUSES, true)
                    && (int) $id !== $exclude_id
                    && (int) ($GLOBALS['vgpc_test_meta'][$id][ProductContributionRepository::PRODUCT_FIELD] ?? 0) === $product_id
                    && (int) ($GLOBALS['vgpc_test_meta'][$id][ProductContributionRepository::PERSON_FIELD] ?? 0) === $person_id
                ) {
                    $matches[] = (int) $id;
                }
            }
            $GLOBALS['vgpc_test_fresh_pair_lookups'][] = ['product_id' => $product_id, 'person_id' => $person_id, 'matches' => $matches];
            return $matches;
        };
    }

    private function approved_roles(): array
    {
        $ids = [];
        foreach (ProductRoleVocabulary::approved_terms() as $slug => $name) {
            $term = vgpc_test_add_term($name, $slug, 0, ProductRoleVocabulary::TAXONOMY);
            $ids[$slug] = $term->term_id;
        }
        return $ids;
    }

    private function submitted_pair(int $product_id, int $person_id, array $role_ids = []): array
    {
        return [
            'field_vg_product_contribution_product' => (string) $product_id,
            'field_vg_product_contribution_person' => (string) $person_id,
            'field_vg_product_contribution_roles' => $role_ids,
        ];
    }

    private function add_product(int $id, string $mode, int $event_id = 0): void
    {
        $this->add_endpoint($id, 'product', 'Product ' . $id);
        $GLOBALS['vgpc_test_meta'][$id][ProductPeopleFields::MODE_FIELD] = $mode;
        $GLOBALS['vgpc_test_meta'][$id][ProductContributionRepository::SOURCE_EVENT_FIELD] = $event_id;
    }

    private function add_endpoint(int $id, string $type, string $title, string $status = 'publish'): void
    {
        $GLOBALS['vgpc_test_posts'][$id] = (object) ['ID' => $id, 'post_type' => $type, 'post_status' => $status, 'post_title' => $title];
    }

    private function add_contribution(int $id, string $status, int $product_id, int $person_id): void
    {
        $GLOBALS['vgpc_test_posts'][$id] = (object) ['ID' => $id, 'post_type' => ProductContributionRepository::POST_TYPE, 'post_status' => $status, 'post_title' => 'Stored title'];
        $GLOBALS['vgpc_test_meta'][$id][ProductContributionRepository::PRODUCT_FIELD] = $product_id;
        $GLOBALS['vgpc_test_meta'][$id][ProductContributionRepository::PERSON_FIELD] = $person_id;
    }

    private function add_event_participation(int $id, string $status, int $event_id, int $person_id): void
    {
        $GLOBALS['vgpc_test_posts'][$id] = (object) ['ID' => $id, 'post_type' => EventParticipationRepository::POST_TYPE, 'post_status' => $status, 'post_title' => 'Event participation'];
        $GLOBALS['vgpc_test_meta'][$id][EventParticipationRepository::EVENT_FIELD] = $event_id;
        $GLOBALS['vgpc_test_meta'][$id][EventParticipationRepository::PERSON_FIELD] = $person_id;
        $GLOBALS['vgpc_test_meta'][$id][EventParticipationRepository::DISPLAY_ORDER_FIELD] = 0;
    }

    private function install_lookup_and_lock_order(): void
    {
        $GLOBALS['vgpc_test_write_events'] = [];
        $GLOBALS['vgpc_test_fresh_pair_lookups'] = [];
        $GLOBALS['wpdb']->get_var_handler = function (string $query, array $args): string {
            if ($query === 'SELECT GET_LOCK(%s, %d)') {
                $GLOBALS['vgpc_test_write_events'][] = ['type' => 'lock', 'name' => $args[0]];
                if (count(array_filter($GLOBALS['vgpc_test_write_events'], static fn (array $event): bool => $event['type'] === 'lock')) === 1) {
                    // A concurrent writer changes the stored endpoint while this request waits for its row lock.
                    $GLOBALS['vgpc_test_meta'][401][ProductContributionRepository::PERSON_FIELD] = 202;
                }
            }
            return '1';
        };
        $GLOBALS['wpdb']->get_col_handler = static function (string $query, array $args): array {
            if (! str_contains($query, 'SELECT DISTINCT contribution.ID')) {
                return [];
            }
            $product_index = array_search(ProductContributionRepository::PRODUCT_FIELD, $args, true);
            $person_index = array_search(ProductContributionRepository::PERSON_FIELD, $args, true);
            $product_id = (int) ($args[$product_index + 1] ?? 0);
            $person_id = (int) ($args[$person_index + 1] ?? 0);
            $exclude_id = str_contains($query, 'contribution.ID <> %d') ? (int) end($args) : 0;
            $matches = [];
            foreach ($GLOBALS['vgpc_test_posts'] as $id => $post) {
                if (
                    ($post->post_type ?? '') === ProductContributionRepository::POST_TYPE
                    && in_array((string) ($post->post_status ?? ''), ProductContributionRepository::UNIQUENESS_STATUSES, true)
                    && (int) $id !== $exclude_id
                    && (int) ($GLOBALS['vgpc_test_meta'][$id][ProductContributionRepository::PRODUCT_FIELD] ?? 0) === $product_id
                    && (int) ($GLOBALS['vgpc_test_meta'][$id][ProductContributionRepository::PERSON_FIELD] ?? 0) === $person_id
                ) {
                    $matches[] = (int) $id;
                }
            }
            $GLOBALS['vgpc_test_write_events'][] = ['type' => 'fresh-pair-check'];
            $GLOBALS['vgpc_test_fresh_pair_lookups'][] = ['product_id' => $product_id, 'person_id' => $person_id, 'matches' => $matches];
            return $matches;
        };
    }

    private function expected_pair_lock(int $product_id, int $person_id): string
    {
        return 'vgpc_pc_' . substr(hash('sha256', $this->lock_scope((string) $product_id, $person_id)), 0, 56);
    }

    private function expected_row_lock(int $contribution_id): string
    {
        return 'vgpc_pc_' . substr(hash('sha256', $this->lock_scope('contribution', $contribution_id)), 0, 56);
    }

    private function lock_scope(string $kind, int $id_or_person, ?int $person_id = null): string
    {
        global $wpdb;
        $scope = $person_id === null
            ? implode(':', [(string) ($wpdb->dbname ?? ''), (string) ($wpdb->prefix ?? ''), function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0, $kind, $id_or_person])
            : implode(':', [(string) ($wpdb->dbname ?? ''), (string) ($wpdb->prefix ?? ''), function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0, $kind, $id_or_person, $person_id]);
        return $scope;
    }
}

}
