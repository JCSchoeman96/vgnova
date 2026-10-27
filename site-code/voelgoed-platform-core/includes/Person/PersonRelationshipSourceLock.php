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
        if (isset($this->held[$name])) return true;
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
        try {
            $released = $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
        } catch (\Throwable) {
            $this->release_failed[$name] = true;
            error_log('Person relationship source lock release failed.');
            return;
        }
        if ((string) $released === '1') {
            unset($this->held[$name]);
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
}
