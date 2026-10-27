<?php
declare(strict_types=1);
namespace Voelgoed\PlatformCore\Person;
defined('ABSPATH') || exit;

/** Projects authoritative source fields into the disposable reverse-query index. */
final class PersonRelationshipIndexer
{
    private const DIRTY_GENERATION_META_KEY = '_vg_person_relationship_index_dirty_generation';
    /** @var callable(string):void */
    private $logger;

    public function __construct(
        private PersonRelationshipSourceLock $lock,
        private PersonRelationshipSourceReader $reader,
        private PersonRelationshipValidator $validator,
        private PersonRelationshipRepository $repository,
        ?callable $logger = null,
    ) {
        $this->logger = $logger ?? static function (string $message): void { error_log($message); };
    }

    public function rebuild_source(int $source_post_id): true|\WP_Error
    {
        $result = $this->perform_rebuild($source_post_id);
        if ($result === true && $source_post_id > 0 && ! $this->lock->release_succeeded($source_post_id)) {
            return $this->fail($source_post_id, new \WP_Error('source_lock_release_failed', 'Could not release the source relationship lock.'));
        }
        return $result;
    }

    private function perform_rebuild(int $source_post_id): true|\WP_Error
    {
        if ($source_post_id < 1) {
            return $this->fail($source_post_id, new \WP_Error('invalid_source_id', 'Source post ID must be positive.'));
        }

        $locked = false;
        try {
            $source = get_post($source_post_id);
            if (! is_object($source)) {
                return $this->fail($source_post_id, new \WP_Error('source_not_found', 'The source post does not exist.'));
            }
            if (! PersonRelationshipContract::supported_source_type((string) ($source->post_type ?? ''))) {
                return $this->fail($source_post_id, new \WP_Error('unsupported_source', 'The source post type has no approved Person relationship contract.'));
            }
            if (! $this->lock->acquire($source_post_id)) {
                return $this->fail($source_post_id, new \WP_Error('source_lock_failed', 'Could not acquire the source relationship lock.'));
            }
            $locked = true;

            $generation_snapshot = get_post_meta($source_post_id, self::DIRTY_GENERATION_META_KEY, true);
            $projection = $this->reader->read($source_post_id);
            if (is_wp_error($projection)) {
                return $this->fail($source_post_id, $projection);
            }
            $current_source = get_post($source_post_id);
            if (! is_object($current_source) || (string) ($current_source->post_type ?? '') !== $projection['source_post_type']) {
                return $this->fail($source_post_id, new \WP_Error('source_read_failed', 'Could not read the current source state.'));
            }
            $current_status = (string) ($current_source->post_status ?? '');
            $validation_status = $current_status === 'future' ? 'publish' : $current_status;
            $valid = $this->validator->validate_projection($projection, $validation_status);
            if (is_wp_error($valid)) {
                return $this->fail($source_post_id, $valid);
            }
            $result = $this->repository->replace_source_relationships(
                $source_post_id,
                $projection['source_post_type'],
                $projection['relationships'],
            );
            if (is_wp_error($result)) {
                return $this->fail($source_post_id, $result);
            }
            if (! $this->clear_dirty_if_unchanged($source_post_id, $generation_snapshot)) {
                return $this->fail($source_post_id, new \WP_Error('dirty_marker_clear_failed', 'Could not clear the dirty marker.'));
            }
            return true;
        } catch (\Throwable) {
            return $this->fail($source_post_id, new \WP_Error('projection_failed', 'Could not project source relationships.'));
        } finally {
            if ($locked) {
                $this->lock->release($source_post_id);
            }
        }
    }

    public function remove_source(int $source_post_id): true|\WP_Error
    {
        $result = $this->perform_remove($source_post_id);
        if ($result === true && $source_post_id > 0 && ! $this->lock->release_succeeded($source_post_id)) {
            return $this->fail($source_post_id, new \WP_Error('source_lock_release_failed', 'Could not release the source relationship lock.'));
        }
        return $result;
    }

    private function perform_remove(int $source_post_id): true|\WP_Error
    {
        if ($source_post_id < 1) {
            return $this->fail($source_post_id, new \WP_Error('invalid_source_id', 'Source post ID must be positive.'));
        }

        $locked = false;
        try {
            $source = get_post($source_post_id);
            if (is_object($source) && ! PersonRelationshipContract::supported_source_type((string) ($source->post_type ?? ''))) {
                return $this->fail($source_post_id, new \WP_Error('unsupported_source', 'The source post type has no approved Person relationship contract.'));
            }
            if (! $this->lock->acquire($source_post_id)) {
                return $this->fail($source_post_id, new \WP_Error('source_lock_failed', 'Could not acquire the source relationship lock.'));
            }
            $locked = true;
            $generation_snapshot = get_post_meta($source_post_id, self::DIRTY_GENERATION_META_KEY, true);
            $result = $this->repository->delete_source($source_post_id);
            if (is_wp_error($result)) {
                return $this->fail($source_post_id, $result);
            }
            if (! $this->clear_dirty_if_unchanged($source_post_id, $generation_snapshot)) {
                return $this->fail($source_post_id, new \WP_Error('dirty_marker_clear_failed', 'Could not clear the dirty marker.'));
            }
            return true;
        } catch (\Throwable) {
            return $this->fail($source_post_id, new \WP_Error('projection_failed', 'Could not remove source relationships.'));
        } finally {
            if ($locked) {
                $this->lock->release($source_post_id);
            }
        }
    }

    public function is_dirty(int $source_post_id): bool
    {
        return $source_post_id > 0 && $this->is_dirty_marker(get_post_meta($source_post_id, PersonRelationshipContract::DIRTY_META_KEY, true));
    }

    private function fail(int $source_post_id, \WP_Error $error): \WP_Error
    {
        if ($source_post_id > 0) {
            // Advance first. A concurrent successful rebuild can then either
            // retain the marker or delete it before this write sets it again.
            $generation = bin2hex(random_bytes(16));
            $generation_ok = update_post_meta($source_post_id, self::DIRTY_GENERATION_META_KEY, $generation);
            if (! $generation_ok || get_post_meta($source_post_id, self::DIRTY_GENERATION_META_KEY, true) !== $generation) {
                $error = new \WP_Error('dirty_marker_persistence_failed', 'Could not persist dirty generation state.');
            }
            $marker_ok = update_post_meta($source_post_id, PersonRelationshipContract::DIRTY_META_KEY, 1);
            if (! $marker_ok && ! $this->is_dirty_marker(get_post_meta($source_post_id, PersonRelationshipContract::DIRTY_META_KEY, true))) {
                $error = new \WP_Error('dirty_marker_persistence_failed', 'Could not persist the dirty marker.');
            }
        }
        $code = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $error->get_error_code()) ?: 'projection_failed';
        ($this->logger)(sprintf('Person relationship projection failed for source %d (%s).', max(0, $source_post_id), $code));
        return $error;
    }

    private function clear_dirty_if_unchanged(int $source_post_id, mixed $generation_snapshot): bool
    {
        global $wpdb;
        $meta_table = $wpdb->postmeta;
        $sql = "DELETE dirty FROM {$meta_table} AS dirty
            LEFT JOIN {$meta_table} AS generation
              ON generation.post_id = dirty.post_id
             AND generation.meta_key = %s
             AND generation.meta_value <> %s
            WHERE dirty.post_id = %d
              AND dirty.meta_key = %s
              AND dirty.meta_value = '1'
              AND generation.meta_id IS NULL";
        return $wpdb->query($wpdb->prepare(
            $sql,
            self::DIRTY_GENERATION_META_KEY,
            (string) $generation_snapshot,
            $source_post_id,
            PersonRelationshipContract::DIRTY_META_KEY,
        )) !== false;
    }

    private function is_dirty_marker(mixed $value): bool
    {
        return $value === 1 || $value === '1';
    }
}
