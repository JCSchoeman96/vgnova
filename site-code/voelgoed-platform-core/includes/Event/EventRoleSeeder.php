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

        $created = [];
        $existing = [];
        foreach (self::APPROVED_TERMS as $slug => $name) {
            if (isset($existing_by_slug[$slug])) {
                $existing[] = $slug;
                continue;
            }

            $inserted = wp_insert_term($name, self::TAXONOMY, ['slug' => $slug]);
            if (is_wp_error($inserted)) {
                throw new \RuntimeException(sprintf(
                    'Could not create event_role term "%s": %s',
                    $slug,
                    $inserted->get_error_message()
                ));
            }

            $term_id = is_array($inserted) ? (int) ($inserted['term_id'] ?? 0) : 0;
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
                throw new \RuntimeException(sprintf(
                    'The created event_role term "%s" did not retain its approved name and slug.',
                    $slug
                ));
            }

            $created[] = $slug;
        }

        return [
            'created' => $created,
            'existing' => $existing,
        ];
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
