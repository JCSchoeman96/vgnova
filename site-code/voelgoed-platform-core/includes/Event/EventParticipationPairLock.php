<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Event;

defined('ABSPATH') || exit;

/** Serializes pair writes and concurrent edits to one participation across requests. */
final class EventParticipationPairLock
{
    private const WAIT_TIMEOUT_SECONDS = 10;

    /** @var array<string, true> */
    private array $held_locks = [];

    public function acquire(int $event_id, int $person_id): bool
    {
        if ($event_id <= 0 || $person_id <= 0) {
            return false;
        }

        return $this->acquire_lock($this->lock_name($event_id, $person_id));
    }

    /** Serialize writes that edit one existing participation post. */
    public function acquire_participation(int $participation_id): bool
    {
        if ($participation_id <= 0) {
            return false;
        }

        return $this->acquire_lock($this->participation_lock_name($participation_id));
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

    public function release(int $event_id, int $person_id): bool
    {
        if ($event_id <= 0 || $person_id <= 0) {
            return false;
        }

        return $this->release_lock($this->lock_name($event_id, $person_id));
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

    private function lock_name(int $event_id, int $person_id): string
    {
        global $wpdb;

        $database = is_object($wpdb) ? (string) ($wpdb->dbname ?? '') : '';
        $prefix = is_object($wpdb) ? (string) ($wpdb->prefix ?? '') : '';
        $blog_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0;
        $scope = implode(':', [$database, $prefix, $blog_id, $event_id, $person_id]);

        // MySQL limits named locks to 64 characters.
        return 'vgpc_ep_' . substr(hash('sha256', $scope), 0, 56);
    }

    private function participation_lock_name(int $participation_id): string
    {
        global $wpdb;

        $database = is_object($wpdb) ? (string) ($wpdb->dbname ?? '') : '';
        $prefix = is_object($wpdb) ? (string) ($wpdb->prefix ?? '') : '';
        $blog_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0;
        $scope = implode(':', [$database, $prefix, $blog_id, 'participation', $participation_id]);

        return 'vgpc_ep_' . substr(hash('sha256', $scope), 0, 56);
    }
}
