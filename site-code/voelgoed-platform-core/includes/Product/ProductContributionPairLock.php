<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Product;

defined('ABSPATH') || exit;

/** Serializes Product × Person writes and edits to existing contribution rows. */
final class ProductContributionPairLock
{
    private const WAIT_TIMEOUT_SECONDS = 10;

    /** @var array<string, true> */
    private array $held_locks = [];

    public function acquire(int $product_id, int $person_id): bool
    {
        if ($product_id <= 0 || $person_id <= 0) {
            return false;
        }

        return $this->acquire_lock($this->pair_lock_name($product_id, $person_id));
    }

    /** Serialize concurrent edits to one existing contribution post. */
    public function acquire_contribution(int $contribution_id): bool
    {
        if ($contribution_id <= 0) {
            return false;
        }

        return $this->acquire_lock($this->contribution_lock_name($contribution_id));
    }

    private function acquire_lock(string $lock_name): bool
    {
        if (isset($this->held_locks[$lock_name])) {
            return true;
        }

        global $wpdb;
        if (! is_object($wpdb) || ! method_exists($wpdb, 'prepare') || ! method_exists($wpdb, 'get_var')) {
            return false;
        }

        try {
            $result = $wpdb->get_var($wpdb->prepare(
                'SELECT GET_LOCK(%s, %d)',
                $lock_name,
                self::WAIT_TIMEOUT_SECONDS
            ));
        } catch (\Throwable) {
            return false;
        }

        if ((string) $result !== '1') {
            return false;
        }

        $this->held_locks[$lock_name] = true;
        return true;
    }

    /** Release only locks acquired inside the scope, in reverse acquisition order. */
    public function with_scoped_locks(callable $operation): mixed
    {
        $held_before = array_keys($this->held_locks);
        try {
            return $operation();
        } finally {
            $acquired_in_scope = array_values(array_diff(array_keys($this->held_locks), $held_before));
            foreach (array_reverse($acquired_in_scope) as $lock_name) {
                $this->release_lock($lock_name);
            }
        }
    }

    public function release(int $product_id, int $person_id): bool
    {
        if ($product_id <= 0 || $person_id <= 0) {
            return false;
        }

        return $this->release_lock($this->pair_lock_name($product_id, $person_id));
    }

    public function release_contribution(int $contribution_id): bool
    {
        if ($contribution_id <= 0) {
            return false;
        }

        return $this->release_lock($this->contribution_lock_name($contribution_id));
    }

    public function release_all(): void
    {
        foreach (array_keys($this->held_locks) as $lock_name) {
            $this->release_lock($lock_name);
        }
    }

    private function release_lock(string $lock_name): bool
    {
        if (! isset($this->held_locks[$lock_name])) {
            return true;
        }

        global $wpdb;
        if (! is_object($wpdb) || ! method_exists($wpdb, 'prepare') || ! method_exists($wpdb, 'get_var')) {
            return false;
        }

        try {
            $result = $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        } catch (\Throwable) {
            return false;
        }

        unset($this->held_locks[$lock_name]);
        return (string) $result === '1' || $result === null;
    }

    private function pair_lock_name(int $product_id, int $person_id): string
    {
        global $wpdb;

        $database = is_object($wpdb) ? (string) ($wpdb->dbname ?? '') : '';
        $prefix = is_object($wpdb) ? (string) ($wpdb->prefix ?? '') : '';
        $blog_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0;
        $scope = implode(':', [$database, $prefix, $blog_id, $product_id, $person_id]);

        // MySQL limits named locks to 64 characters.
        return 'vgpc_pc_' . substr(hash('sha256', $scope), 0, 56);
    }

    private function contribution_lock_name(int $contribution_id): string
    {
        global $wpdb;

        $database = is_object($wpdb) ? (string) ($wpdb->dbname ?? '') : '';
        $prefix = is_object($wpdb) ? (string) ($wpdb->prefix ?? '') : '';
        $blog_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0;
        $scope = implode(':', [$database, $prefix, $blog_id, 'contribution', $contribution_id]);

        return 'vgpc_pc_' . substr(hash('sha256', $scope), 0, 56);
    }
}
