<?php
declare(strict_types=1);
namespace Voelgoed\PlatformCore\Person;
defined('ABSPATH') || exit;

final class PersonRelationshipSourceLock
{
    private array $held = [];
    private array $release_failed = [];
    public function acquire(int $source_post_id): bool
    {
        if ($source_post_id < 1) return false;
        $name = 'vg_person_rel_' . $source_post_id;
        // A lock already tracked by this object belongs to the operation that
        // acquired it. A second operation must not borrow that ownership.
        if (isset($this->held[$name])) return false;
        // Release status describes the previous attempt, not this acquisition.
        unset($this->release_failed[$name]);
        global $wpdb;
        try { $ok = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $name, 10)); }
        catch (\Throwable) { return false; }
        if ((string) $ok !== '1') return false;
        $this->held[$name] = true;
        return true;
    }
    public function release(int $source_post_id): void
    {
        $name = 'vg_person_rel_' . $source_post_id;
        $this->release_name($name);
    }

    public function release_succeeded(int $source_post_id): bool
    {
        return ! isset($this->release_failed['vg_person_rel_' . $source_post_id]);
    }

    private function release_name(string $name): void
    {
        if (! isset($this->held[$name])) return;
        global $wpdb;
        // A thrown query has an indeterminate outcome. Retry once on the same
        // connection while this cleanup call is still active. RELEASE_LOCK is
        // safe to repeat for a lock still tracked as owned by this instance.
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            try {
                $released = $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
                break;
            } catch (\Throwable) {
                if ($attempt === 1) {
                    $this->release_failed[$name] = true;
                    error_log('Person relationship source lock release failed.');
                    return;
                }
            }
        }
        // Only RELEASE_LOCK() = 1 confirms that this connection released a
        // lock it owned. For 0 or NULL, forget the claim so a later operation
        // cannot report ownership or retry against a lock another operation
        // may now own.
        unset($this->held[$name]);
        if ((string) $released === '1') {
            unset($this->release_failed[$name]);
            return;
        }
        $this->release_failed[$name] = true;
        error_log('Person relationship source lock release failed.');
    }
    public function release_all(): void
    {
        foreach (array_keys($this->held) as $name) {
            $this->release_name($name);
        }
    }

    public function release_pending(int $source_post_id): bool
    {
        $name = 'vg_person_rel_' . $source_post_id;
        return isset($this->held[$name]) && isset($this->release_failed[$name]);
    }

}
