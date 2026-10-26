<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Event;

defined('ABSPATH') || exit;

/** Seeds the controlled event_role taxonomy vocabulary on explicit request. */
final class EventRoleSeeder
{
    private const TAXONOMY = 'event_role';

    private const APPROVED_TERMS = [
        'spreker' => 'Spreker',
        'paneellid' => 'Paneellid',
        'fasiliteerder' => 'Fasiliteerder',
        'gas' => 'Gas',
    ];

    public function register(): void
    {
        if (defined('WP_CLI') && WP_CLI && class_exists('\WP_CLI')) {
            \WP_CLI::add_command('vg-event-role-setup', [$this, 'setup_command']);
        }
    }

    /**
     * Seed the four approved event roles after checking the complete vocabulary.
     *
     * @return array{created: list<string>, existing: list<string>}
     */
    public function seed_event_role_terms(): array
    {
        if (! taxonomy_exists(self::TAXONOMY)) {
            throw new \RuntimeException('The ACF-owned event_role taxonomy is not registered.');
        }

        $current_terms = get_terms([
            'taxonomy' => self::TAXONOMY,
            'hide_empty' => false,
        ]);
        if (is_wp_error($current_terms)) {
            throw new \RuntimeException('Could not read event_role terms: ' . $current_terms->get_error_message());
        }
        if (! is_array($current_terms)) {
            throw new \RuntimeException('Could not read event_role terms: WordPress returned an invalid result.');
        }

        $approved_names = array_flip(self::APPROVED_TERMS);
        $existing_by_slug = [];
        foreach ($current_terms as $current_term) {
            $slug = (string) ($current_term->slug ?? '');
            $name = (string) ($current_term->name ?? '');
            if (($current_term->taxonomy ?? null) !== self::TAXONOMY) {
                throw new \RuntimeException(sprintf(
                    'Unexpected event_role term "%s" (%s) was returned. No terms were changed.',
                    $name,
                    $slug
                ));
            }

            if (array_key_exists($slug, self::APPROVED_TERMS)) {
                if (self::APPROVED_TERMS[$slug] !== $name) {
                    throw new \RuntimeException(sprintf(
                        'The event_role slug "%s" conflicts with the approved name "%s". No terms were changed.',
                        $slug,
                        self::APPROVED_TERMS[$slug]
                    ));
                }

                if (isset($existing_by_slug[$slug])) {
                    throw new \RuntimeException(sprintf(
                        'Duplicate event_role slug "%s" conflicts with the approved vocabulary. No terms were changed.',
                        $slug
                    ));
                }

                $existing_by_slug[$slug] = $current_term;
                continue;
            }

            if (array_key_exists($name, $approved_names)) {
                throw new \RuntimeException(sprintf(
                    'The event_role name "%s" conflicts with the approved slug "%s". No terms were changed.',
                    $name,
                    $approved_names[$name]
                ));
            }

            throw new \RuntimeException(sprintf(
                'Unexpected event_role term "%s" (%s) exists. No terms were changed.',
                $name,
                $slug
            ));
        }

        \vg_runtime_mutation_assert_session('vgnova-local-10039');

        $created = [];
        $created_term_ids = [];
        $existing = [];
        foreach (self::APPROVED_TERMS as $slug => $name) {
            if (isset($existing_by_slug[$slug])) {
                $existing[] = $slug;
                continue;
            }

            $inserted = wp_insert_term($name, self::TAXONOMY, ['slug' => $slug]);
            if (is_wp_error($inserted)) {
                throw $this->exception_after_cleanup(sprintf(
                    'Could not create event_role term "%s": %s',
                    $slug,
                    $inserted->get_error_message()
                ), $created_term_ids);
            }

            $term_id = is_array($inserted) ? (int) ($inserted['term_id'] ?? 0) : 0;
            if ($term_id > 0) {
                $created_term_ids[] = $term_id;
            }
            $created_term = $term_id > 0 ? get_term($term_id, self::TAXONOMY) : false;
            $term_by_slug = get_term_by('slug', $slug, self::TAXONOMY);
            if (
                ! $created_term
                || is_wp_error($created_term)
                || $created_term->taxonomy !== self::TAXONOMY
                || $created_term->name !== $name
                || $created_term->slug !== $slug
                || ! $term_by_slug
                || (int) $term_by_slug->term_id !== $term_id
            ) {
                $verification_message = sprintf(
                    'The created event_role term "%s" did not retain its approved name and slug.',
                    $slug
                );
                if ($term_id <= 0) {
                    throw $this->exception_after_cleanup(
                        $verification_message . ' Cleanup was not attempted for this insert because WordPress returned no usable term ID.',
                        $created_term_ids
                    );
                }

                throw $this->exception_after_cleanup($verification_message, $created_term_ids);
            }

            $created[] = $slug;
        }

        $final_terms = get_terms([
            'taxonomy' => self::TAXONOMY,
            'hide_empty' => false,
        ]);
        if (is_wp_error($final_terms) || ! is_array($final_terms)) {
            $read_error = is_wp_error($final_terms)
                ? $final_terms->get_error_message()
                : 'WordPress returned an invalid result.';
            throw $this->exception_after_cleanup(
                'Could not verify the final event_role vocabulary: ' . $read_error,
                $created_term_ids
            );
        }

        $final_by_slug = [];
        foreach ($final_terms as $final_term) {
            $final_slug = (string) ($final_term->slug ?? '');
            if (
                ($final_term->taxonomy ?? null) !== self::TAXONOMY
                || array_key_exists($final_slug, $final_by_slug)
            ) {
                throw $this->exception_after_cleanup(
                    'The final event_role vocabulary does not match the exact approved vocabulary.',
                    $created_term_ids
                );
            }

            $final_by_slug[$final_slug] = (string) ($final_term->name ?? '');
        }

        $expected_by_slug = self::APPROVED_TERMS;
        ksort($final_by_slug);
        ksort($expected_by_slug);
        if ($final_by_slug !== $expected_by_slug) {
            throw $this->exception_after_cleanup(
                'The final event_role vocabulary does not match the exact approved vocabulary.',
                $created_term_ids
            );
        }

        return [
            'created' => $created,
            'existing' => $existing,
        ];
    }

    /**
     * Build a seeding error after removing only terms created by this attempt.
     *
     * @param list<int> $created_term_ids
     */
    private function exception_after_cleanup(string $message, array $created_term_ids): \RuntimeException
    {
        $cleanup_errors = [];
        foreach (array_reverse(array_unique($created_term_ids)) as $term_id) {
            $delete_result = wp_delete_term($term_id, self::TAXONOMY);
            $remaining_term = get_term($term_id, self::TAXONOMY);
            if (is_wp_error($remaining_term) || $remaining_term) {
                $cleanup_errors[] = sprintf(
                    'Could not remove term ID %d%s',
                    $term_id,
                    is_wp_error($delete_result) ? ': ' . $delete_result->get_error_message() : '.'
                );
            }
        }

        if ($cleanup_errors) {
            $message .= ' Cleanup failed: ' . implode(' ', $cleanup_errors);
        }

        return new \RuntimeException($message);
    }

    /** Run the seeder through the registered WP-CLI command. */
    public function setup_command(array $args, array $assoc_args): void
    {
        try {
            $result = $this->seed_event_role_terms();
        } catch (\Throwable $error) {
            \WP_CLI::error($error->getMessage());
        }

        \WP_CLI::success(sprintf(
            'Event role terms created: %s. Already present: %s.',
            $result['created'] ? implode(', ', $result['created']) : 'none',
            $result['existing'] ? implode(', ', $result['existing']) : 'none'
        ));
    }
}
