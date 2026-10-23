<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Event\EventParticipationPairLock;
use Voelgoed\PlatformCore\Product\ProductContributionPairLock;

final class ProductContributionPairLockTest
{
    public function test_pair_and_row_locks_use_a_product_contribution_specific_namespace(): void
    {
        $lock = $this->lock();
        $event_lock = new EventParticipationPairLock();

        vgpc_test_true($lock->acquire(101, 201), 'A valid Product × Person pair lock must be acquired.');
        vgpc_test_true($event_lock->acquire(101, 201), 'The Event Participation pair lock fixture must be acquired.');
        vgpc_test_true($lock->acquire(101, 202), 'A second Product × Person pair lock must be acquired.');
        vgpc_test_true($lock->acquire_contribution(301), 'A valid contribution-row lock must be acquired.');

        $names = array_column($GLOBALS['wpdb']->queries, 'args');
        $lock_names = array_map(static fn (array $args): string => (string) ($args[0] ?? ''), $names);
        vgpc_test_same(4, count(array_unique($lock_names)), 'Different Product pairs, Event pairs, and contribution-row locks must not collide.');
        vgpc_test_true(str_starts_with($lock_names[0], 'vgpc_pc_'), 'Product Contribution locks must use their own named-lock namespace.');
        vgpc_test_true(str_starts_with($lock_names[1], 'vgpc_ep_'), 'Event Participation must retain its own named-lock namespace.');
        vgpc_test_true($lock_names[0] !== $lock_names[2], 'Distinct Product × Person pairs must receive distinct lock keys.');
        vgpc_test_true(str_starts_with($lock_names[3], 'vgpc_pc_'), 'Contribution-row locks must share only the Product Contribution namespace.');
    }

    public function test_invalid_lock_keys_fail_without_database_access(): void
    {
        $lock = $this->lock();

        vgpc_test_true(! $lock->acquire(0, 201), 'A non-positive Product ID cannot be locked.');
        vgpc_test_true(! $lock->acquire(101, 0), 'A non-positive Person ID cannot be locked.');
        vgpc_test_true(! $lock->acquire_contribution(0), 'A non-positive contribution ID cannot be locked.');
        vgpc_test_same([], $GLOBALS['wpdb']->queries, 'Invalid lock keys must not hit the database.');
    }

    public function test_failed_acquire_is_not_released_and_unheld_release_is_a_noop(): void
    {
        $lock = $this->lock();
        $GLOBALS['wpdb']->get_var_handler = static fn (string $query): string => $query === 'SELECT GET_LOCK(%s, %d)' ? '0' : '1';

        vgpc_test_true(! $lock->acquire(101, 201), 'A timed-out named lock must report failure.');
        vgpc_test_true($lock->release(101, 201), 'Releasing a pair not held by this instance must be a successful no-op.');
        vgpc_test_true($lock->release_contribution(301), 'Releasing a row not held by this instance must be a successful no-op.');
        vgpc_test_same(['SELECT GET_LOCK(%s, %d)'], array_column($GLOBALS['wpdb']->queries, 'query'), 'Failed or unheld locks must never be sent RELEASE_LOCK.');
    }

    public function test_contribution_row_lock_releases_once_and_a_second_release_is_a_noop(): void
    {
        $lock = $this->lock();

        vgpc_test_true($lock->acquire_contribution(301), 'A contribution-row lock must be acquired before release.');
        vgpc_test_true($lock->release_contribution(301), 'The acquired contribution-row lock must be released.');
        vgpc_test_true($lock->release_contribution(301), 'Releasing the same contribution-row lock again must be a no-op.');

        $release_queries = array_values(array_filter(
            $GLOBALS['wpdb']->queries,
            static fn (array $query): bool => $query['query'] === 'SELECT RELEASE_LOCK(%s)'
        ));
        vgpc_test_same(1, count($release_queries), 'A successful row-lock release must issue one RELEASE_LOCK query, and the second release must issue none.');
    }

    public function test_scoped_locks_release_only_new_locks_in_reverse_order_and_keep_prior_locks(): void
    {
        $lock = $this->lock();
        vgpc_test_true($lock->acquire(101, 201), 'The outer pair lock must be held before entering scope.');

        $lock->with_scoped_locks(function () use ($lock): void {
            vgpc_test_true($lock->acquire(102, 202), 'The scoped pair lock must be acquired.');
            vgpc_test_true($lock->acquire_contribution(302), 'The scoped row lock must be acquired.');
        });

        $queries = $GLOBALS['wpdb']->queries;
        $release_queries = array_values(array_filter($queries, static fn (array $query): bool => $query['query'] === 'SELECT RELEASE_LOCK(%s)'));
        vgpc_test_same(2, count($release_queries), 'Only the two locks acquired inside scope must be released.');
        vgpc_test_same($queries[2]['args'][0], $release_queries[0]['args'][0], 'The latest scoped lock must be released first.');
        vgpc_test_same($queries[1]['args'][0], $release_queries[1]['args'][0], 'The earlier scoped lock must be released second.');

        vgpc_test_true($lock->release(101, 201), 'The pre-existing outer lock must remain held after scope exit.');
        vgpc_test_same(3, count(array_filter($GLOBALS['wpdb']->queries, static fn (array $query): bool => $query['query'] === 'SELECT RELEASE_LOCK(%s)')), 'Explicit release must release the preserved outer lock.');
    }

    public function test_release_all_releases_every_lock_still_held_by_this_instance(): void
    {
        $lock = $this->lock();
        vgpc_test_true($lock->acquire(101, 201), 'The pair lock must be acquired.');
        vgpc_test_true($lock->acquire_contribution(301), 'The contribution-row lock must be acquired.');

        $lock->release_all();

        $release_queries = array_values(array_filter(
            $GLOBALS['wpdb']->queries,
            static fn (array $query): bool => $query['query'] === 'SELECT RELEASE_LOCK(%s)'
        ));
        vgpc_test_same(2, count($release_queries), 'Release-all must release every pair and contribution-row lock.');
        $released_names = array_column(array_column($release_queries, 'args'), 0);
        vgpc_test_same(2, count(array_unique($released_names)), 'Release-all must release each held named lock exactly once.');
        vgpc_test_true($lock->release(101, 201), 'Release after release-all must be a no-op.');
        vgpc_test_same(2, count(array_filter(
            $GLOBALS['wpdb']->queries,
            static fn (array $query): bool => $query['query'] === 'SELECT RELEASE_LOCK(%s)'
        )), 'A released lock must no longer be tracked as held.');
    }

    private function lock(): ProductContributionPairLock
    {
        vgpc_test_reset();
        vgpc_test_true(class_exists(ProductContributionPairLock::class), 'ProductContributionPairLock is not implemented yet.');

        return new ProductContributionPairLock();
    }
}
