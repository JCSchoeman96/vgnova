<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Taxonomy;

defined('ABSPATH') || exit;

/**
 * Registers and seeds the canonical cross-platform topic taxonomy.
 *
 * Terms are created only by the explicit `wp vg-content-topic-setup` command.
 * This keeps rewrite flushing out of normal front-end and admin requests.
 */
final class ContentTopicTaxonomy
{
    public const TAXONOMY = 'content_topic';

    public const OBJECT_TYPES = [
        'post',
        'mto-course',
        'product',
        'tc_events',
        'medewerker',
        'uitsending',
        'kompetisie',
        'person',
    ];

    private const REWRITE_SETUP_OPTION = 'vgpc_content_topic_rewrite_flushed_v1';

    private const TERMS = [
        ['name' => 'Nuwe Jy', 'slug' => 'nuwe-jy', 'parent' => null],
        ['name' => 'Gesondheid', 'slug' => 'gesondheid', 'parent' => 'nuwe-jy'],
        ['name' => 'Voeding & gewig', 'slug' => 'voeding-gewig', 'parent' => 'nuwe-jy'],
        ['name' => 'Gesonde eetgewoontes', 'slug' => 'gesonde-eetgewoontes', 'parent' => 'voeding-gewig'],
        ['name' => 'Gewigsbestuur', 'slug' => 'gewigsbestuur', 'parent' => 'voeding-gewig'],
        ['name' => 'Oefening & fiksheid', 'slug' => 'oefening-fiksheid', 'parent' => 'nuwe-jy'],
        ['name' => 'Skoonheid & versorging', 'slug' => 'skoonheid-versorging', 'parent' => 'nuwe-jy'],
        ['name' => 'Velsorg', 'slug' => 'velsorg', 'parent' => 'skoonheid-versorging'],
        ['name' => 'Grimering', 'slug' => 'grimering', 'parent' => 'skoonheid-versorging'],
        ['name' => 'Hare & lyfsorg', 'slug' => 'hare-lyfsorg', 'parent' => 'skoonheid-versorging'],
        ['name' => 'Resepte', 'slug' => 'resepte', 'parent' => 'nuwe-jy'],
        ['name' => 'Geloof', 'slug' => 'geloof', 'parent' => null],
        ['name' => 'Gebed', 'slug' => 'gebed', 'parent' => 'geloof'],
        ['name' => 'Bybel', 'slug' => 'bybel', 'parent' => 'geloof'],
        ['name' => 'Geloofslewe', 'slug' => 'geloofslewe', 'parent' => 'geloof'],
        ['name' => 'Geestelike groei', 'slug' => 'geestelike-groei', 'parent' => 'geloof'],
        ['name' => 'Vrae oor geloof', 'slug' => 'vrae-oor-geloof', 'parent' => 'geloof'],
        ['name' => 'Hoop & vertroue', 'slug' => 'hoop-vertroue', 'parent' => 'geloof'],
        ['name' => 'Self', 'slug' => 'self', 'parent' => null],
        ['name' => 'Emosionele welstand', 'slug' => 'emosionele-welstand', 'parent' => 'self'],
        ['name' => 'Selfbeeld & selfvertroue', 'slug' => 'selfbeeld-selfvertroue', 'parent' => 'self'],
        ['name' => 'Persoonlike groei', 'slug' => 'persoonlike-groei', 'parent' => 'self'],
        ['name' => 'Doel & betekenis', 'slug' => 'doel-betekenis', 'parent' => 'self'],
        ['name' => 'Verhoudings & grense', 'slug' => 'verhoudings-grense', 'parent' => 'self'],
        ['name' => 'Stres & balans', 'slug' => 'stres-balans', 'parent' => 'self'],
        ['name' => 'Werk + Geld', 'slug' => 'werk-geld', 'parent' => null],
        ['name' => 'Werk', 'slug' => 'werk', 'parent' => 'werk-geld'],
        ['name' => 'Loopbaan', 'slug' => 'loopbaan', 'parent' => 'werk'],
        ['name' => 'Besigheid & entrepreneurskap', 'slug' => 'besigheid-entrepreneurskap', 'parent' => 'werk'],
        ['name' => 'Leierskap & produktiwiteit', 'slug' => 'leierskap-produktiwiteit', 'parent' => 'werk'],
        ['name' => 'Geld', 'slug' => 'geld', 'parent' => 'werk-geld'],
        ['name' => 'Begroting & geldbestuur', 'slug' => 'begroting-geldbestuur', 'parent' => 'geld'],
        ['name' => 'Skuld & krediet', 'slug' => 'skuld-krediet', 'parent' => 'geld'],
        ['name' => 'Spaar & beleggings', 'slug' => 'spaar-beleggings', 'parent' => 'geld'],
        ['name' => 'Aftrede', 'slug' => 'aftrede', 'parent' => 'geld'],
        ['name' => 'Boedels & testamente', 'slug' => 'boedels-testamente', 'parent' => 'geld'],
        ['name' => 'Ouerskap', 'slug' => 'ouerskap', 'parent' => null],
        ['name' => 'Kinderontwikkeling', 'slug' => 'kinderontwikkeling', 'parent' => 'ouerskap'],
        ['name' => 'Opvoeding & dissipline', 'slug' => 'opvoeding-dissipline', 'parent' => 'ouerskap'],
        ['name' => 'Emosies & selfvertroue', 'slug' => 'emosies-selfvertroue', 'parent' => 'ouerskap'],
        ['name' => 'Skool & leer', 'slug' => 'skool-leer', 'parent' => 'ouerskap'],
        ['name' => 'Tegnologie & skermtyd', 'slug' => 'tegnologie-skermtyd', 'parent' => 'ouerskap'],
        ['name' => 'Gesinslewe', 'slug' => 'gesinslewe', 'parent' => 'ouerskap'],
        ['name' => 'Ma-wees & balans', 'slug' => 'ma-wees-balans', 'parent' => 'ouerskap'],
        ['name' => 'Huwelik', 'slug' => 'huwelik', 'parent' => null],
        ['name' => 'Kommunikasie', 'slug' => 'kommunikasie', 'parent' => 'huwelik'],
        ['name' => 'Liefde & verbinding', 'slug' => 'liefde-verbinding', 'parent' => 'huwelik'],
        ['name' => 'Konflik & herstel', 'slug' => 'konflik-herstel', 'parent' => 'huwelik'],
        ['name' => 'Vertroue & ontrouheid', 'slug' => 'vertroue-ontrouheid', 'parent' => 'huwelik'],
        ['name' => 'Intimiteit', 'slug' => 'intimiteit', 'parent' => 'huwelik'],
        ['name' => 'Geld & huwelik', 'slug' => 'geld-huwelik', 'parent' => 'huwelik'],
        ['name' => 'Saam groei', 'slug' => 'saam-groei', 'parent' => 'huwelik'],
        ['name' => 'Mensstories', 'slug' => 'mensstories', 'parent' => null],
        ['name' => 'Bekende mense', 'slug' => 'bekende-mense', 'parent' => 'mensstories'],
        ['name' => 'Ware lewensverhale', 'slug' => 'ware-lewensverhale', 'parent' => 'mensstories'],
    ];

    public static function definitions(): array
    {
        return self::TERMS;
    }

    public function register(): void
    {
        add_action('init', [$this, 'register_taxonomy'], 100);

        if (defined('WP_CLI') && WP_CLI && class_exists('\WP_CLI')) {
            \WP_CLI::add_command('vg-content-topic-setup', [$this, 'setup_command']);
        }
    }

    public function register_taxonomy(): void
    {
        if (taxonomy_exists(self::TAXONOMY)) {
            return;
        }

        register_taxonomy(self::TAXONOMY, self::OBJECT_TYPES, [
            'labels' => [
                'name' => 'Onderwerpe',
                'singular_name' => 'Onderwerp',
            ],
            'hierarchical' => true,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_rest' => true,
            'show_in_nav_menus' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => [
                'slug' => 'onderwerp',
                'with_front' => false,
                'hierarchical' => true,
            ],
        ]);
    }

    public function setup(): array|\WP_Error
    {
        $registration_error = $this->validate_registration();
        if (is_wp_error($registration_error)) {
            return $registration_error;
        }

        $preflight = $this->preflight_terms();
        if (is_wp_error($preflight)) {
            return $preflight;
        }

        $created_terms = 0;
        foreach (self::TERMS as $definition) {
            if (get_term_by('slug', $definition['slug'], self::TAXONOMY)) {
                continue;
            }

            $parent_id = 0;
            if ($definition['parent'] !== null) {
                $parent = get_term_by('slug', $definition['parent'], self::TAXONOMY);
                if (! $parent) {
                    return new \WP_Error(
                        'vgpc_content_topic_parent_missing',
                        sprintf('Parent slug "%s" is missing for topic "%s".', $definition['parent'], $definition['slug'])
                    );
                }
                $parent_id = (int) $parent->term_id;
            }

            $inserted = wp_insert_term($definition['name'], self::TAXONOMY, [
                'slug' => $definition['slug'],
                'parent' => $parent_id,
                'description' => '',
            ]);
            if (is_wp_error($inserted)) {
                return new \WP_Error(
                    'vgpc_content_topic_insert_failed',
                    sprintf('Could not create topic "%s": %s', $definition['slug'], $inserted->get_error_message())
                );
            }

            $term_id = (int) ($inserted['term_id'] ?? 0);
            $term = $term_id > 0 ? get_term($term_id, self::TAXONOMY) : false;
            if (
                ! $term
                || $term->name !== $this->stored_name($definition['name'])
                || $term->slug !== $definition['slug']
                || (int) $term->parent !== $parent_id
            ) {
                if ($term) {
                    wp_delete_term($term_id, self::TAXONOMY);
                }

                return new \WP_Error(
                    'vgpc_content_topic_insert_conflict',
                    sprintf('WordPress did not create topic "%s" with its requested name, slug, and parent. No suffixed slug was kept.', $definition['slug'])
                );
            }

            ++$created_terms;
        }

        $verification = $this->preflight_terms();
        if (is_wp_error($verification)) {
            return $verification;
        }

        $terms = get_terms([
            'taxonomy' => self::TAXONOMY,
            'hide_empty' => false,
        ]);
        if (is_wp_error($terms)) {
            return $terms;
        }

        $root_terms = count(array_filter(
            $terms,
            static fn (object $term): bool => (int) $term->parent === 0
        ));

        if (count($terms) !== count(self::TERMS) || $root_terms !== 7) {
            return new \WP_Error(
                'vgpc_content_topic_count_mismatch',
                sprintf('Expected 55 topics and 7 roots; found %d topics and %d roots.', count($terms), $root_terms)
            );
        }

        $rewrite_flushed = false;
        if (! get_option(self::REWRITE_SETUP_OPTION, false)) {
            flush_rewrite_rules(false);
            update_option(self::REWRITE_SETUP_OPTION, '1', false);
            $rewrite_flushed = true;
        }

        return [
            'created_terms' => $created_terms,
            'existing_terms' => count($terms) - $created_terms,
            'root_terms' => $root_terms,
            'total_terms' => count($terms),
            'rewrite_flushed' => $rewrite_flushed,
        ];
    }

    public function setup_command(array $args, array $assoc_args): void
    {
        $result = $this->setup();
        if (is_wp_error($result)) {
            \WP_CLI::error($result->get_error_message());
        }

        \WP_CLI::success(sprintf(
            'Content topics ready: %d roots, %d terms, %d created, %d already present. Rewrite rules flushed: %s.',
            $result['root_terms'],
            $result['total_terms'],
            $result['created_terms'],
            $result['existing_terms'],
            $result['rewrite_flushed'] ? 'yes' : 'no'
        ));
    }

    private function validate_registration(): true|\WP_Error
    {
        $taxonomy = get_taxonomy(self::TAXONOMY);
        if (! $taxonomy) {
            return new \WP_Error('vgpc_content_topic_not_registered', 'The content_topic taxonomy is not registered.');
        }

        $actual_types = is_array($taxonomy->object_type ?? null) ? $taxonomy->object_type : [];
        $expected_types = self::OBJECT_TYPES;
        sort($actual_types);
        sort($expected_types);

        $labels = is_array($taxonomy->labels ?? null) ? $taxonomy->labels : (array) ($taxonomy->labels ?? []);
        $rewrite = is_array($taxonomy->rewrite ?? null) ? $taxonomy->rewrite : [];
        $query_var = $taxonomy->query_var ?? false;

        $valid = $actual_types === $expected_types
            && ($taxonomy->hierarchical ?? false) === true
            && ($taxonomy->public ?? false) === true
            && ($taxonomy->publicly_queryable ?? false) === true
            && ($taxonomy->show_ui ?? false) === true
            && ($taxonomy->show_in_rest ?? false) === true
            && ($taxonomy->show_in_nav_menus ?? false) === true
            && ($query_var === true || $query_var === self::TAXONOMY)
            && ($labels['name'] ?? null) === 'Onderwerpe'
            && ($labels['singular_name'] ?? null) === 'Onderwerp'
            && ($rewrite['slug'] ?? null) === 'onderwerp'
            && ($rewrite['with_front'] ?? null) === false
            && ($rewrite['hierarchical'] ?? null) === true;

        if (! $valid) {
            return new \WP_Error(
                'vgpc_content_topic_registration_conflict',
                'The existing content_topic registration differs from the required Platform Core configuration. It was left unchanged.'
            );
        }

        return true;
    }

    private function preflight_terms(): true|\WP_Error
    {
        $terms = get_terms([
            'taxonomy' => self::TAXONOMY,
            'hide_empty' => false,
        ]);
        if (is_wp_error($terms)) {
            return $terms;
        }

        $expected_by_slug = [];
        $expected_by_stored_name = [];
        foreach (self::TERMS as $definition) {
            $expected_by_slug[$definition['slug']] = $definition;
            $expected_by_stored_name[$this->stored_name($definition['name'])] = $definition;
        }

        foreach ($terms as $term) {
            $definition = $expected_by_slug[$term->slug] ?? null;
            if (! $definition) {
                $name_definition = $expected_by_stored_name[$term->name] ?? null;
                if ($name_definition) {
                    return new \WP_Error(
                        'vgpc_content_topic_slug_conflict',
                        sprintf('Topic name "%s" already uses slug "%s", expected "%s". The existing term was left unchanged.', $name_definition['name'], $term->slug, $name_definition['slug'])
                    );
                }

                return new \WP_Error(
                    'vgpc_content_topic_unexpected_term',
                    sprintf('Unexpected content_topic term "%s" (%s) exists. It was left unchanged.', $term->name, $term->slug)
                );
            }

            if ($term->name !== $this->stored_name($definition['name'])) {
                return new \WP_Error(
                    'vgpc_content_topic_name_conflict',
                    sprintf('Slug "%s" already belongs to "%s", expected "%s". The existing term was left unchanged.', $term->slug, $term->name, $definition['name'])
                );
            }

            $actual_parent = $this->parent_slug($term);
            if (is_wp_error($actual_parent)) {
                return $actual_parent;
            }

            if ($actual_parent !== $definition['parent']) {
                return new \WP_Error(
                    'vgpc_content_topic_parent_conflict',
                    sprintf('Topic "%s" has parent "%s", expected "%s". The existing relationship was left unchanged.', $term->slug, $actual_parent ?? '(root)', $definition['parent'] ?? '(root)')
                );
            }
        }

        return true;
    }

    private function stored_name(string $name): string
    {
        return sanitize_term_field('name', $name, 0, self::TAXONOMY, 'db');
    }

    private function parent_slug(object $term): string|null|\WP_Error
    {
        $parent_id = (int) ($term->parent ?? 0);
        if ($parent_id === 0) {
            return null;
        }

        $parent = get_term($parent_id, self::TAXONOMY);
        if (! $parent || is_wp_error($parent)) {
            return new \WP_Error(
                'vgpc_content_topic_parent_unresolvable',
                sprintf('The parent for topic "%s" cannot be resolved. The existing term was left unchanged.', $term->slug ?? '(unknown)')
            );
        }

        return (string) $parent->slug;
    }
}
