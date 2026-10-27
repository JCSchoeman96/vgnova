<?php
declare(strict_types=1);
namespace Voelgoed\PlatformCore\Person;
defined('ABSPATH') || exit;

final class PersonRelationshipSourceLock
{
    private array $held = [];
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
        if (! isset($this->held[$name])) return;
        global $wpdb;
        try { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name)); } catch (\Throwable) {}
        unset($this->held[$name]);
    }
    public function release_all(): void
    {
        foreach (array_keys($this->held) as $name) {
            global $wpdb;
            try { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name)); } catch (\Throwable) {}
        }
        $this->held = [];
    }
}
