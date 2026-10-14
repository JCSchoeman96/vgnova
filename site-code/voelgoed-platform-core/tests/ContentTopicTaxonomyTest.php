<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Taxonomy\ContentTopicTaxonomy;

final class ContentTopicTaxonomyTest
{
    public function test_definitions_match_the_complete_locked_tree(): void
    {
        $expected = [
            ['Nuwe Jy', 'nuwe-jy', null],
            ['Gesondheid', 'gesondheid', 'nuwe-jy'],
            ['Vrouegesondheid', 'vrouegesondheid', 'gesondheid'],
            ['Voeding & gewig', 'voeding-gewig', 'nuwe-jy'],
            ['Gesonde eetgewoontes', 'gesonde-eetgewoontes', 'voeding-gewig'],
            ['Gewigsbestuur', 'gewigsbestuur', 'voeding-gewig'],
            ['Oefening & fiksheid', 'oefening-fiksheid', 'nuwe-jy'],
            ['Skoonheid & versorging', 'skoonheid-versorging', 'nuwe-jy'],
            ['Velsorg', 'velsorg', 'skoonheid-versorging'],
            ['Grimering', 'grimering', 'skoonheid-versorging'],
            ['Hare & lyfsorg', 'hare-lyfsorg', 'skoonheid-versorging'],
            ['Resepte', 'resepte', 'nuwe-jy'],
            ['Geloof', 'geloof', null],
            ['Gebed', 'gebed', 'geloof'],
            ['Bybel', 'bybel', 'geloof'],
            ['Geloofslewe', 'geloofslewe', 'geloof'],
            ['Geestelike groei', 'geestelike-groei', 'geloof'],
            ['Vrae oor geloof', 'vrae-oor-geloof', 'geloof'],
            ['Hoop & vertroue', 'hoop-vertroue', 'geloof'],
            ['Self', 'self', null],
            ['Emosionele welstand', 'emosionele-welstand', 'self'],
            ['Selfbeeld & selfvertroue', 'selfbeeld-selfvertroue', 'self'],
            ['Persoonlike groei', 'persoonlike-groei', 'self'],
            ['Doel & betekenis', 'doel-betekenis', 'self'],
            ['Verhoudings & grense', 'verhoudings-grense', 'self'],
            ['Stres & balans', 'stres-balans', 'self'],
            ['Werk + Geld', 'werk-geld', null],
            ['Werk', 'werk', 'werk-geld'],
            ['Loopbaan', 'loopbaan', 'werk'],
            ['Besigheid & entrepreneurskap', 'besigheid-entrepreneurskap', 'werk'],
            ['Leierskap & produktiwiteit', 'leierskap-produktiwiteit', 'werk'],
            ['Geld', 'geld', 'werk-geld'],
            ['Begroting & geldbestuur', 'begroting-geldbestuur', 'geld'],
            ['Skuld & krediet', 'skuld-krediet', 'geld'],
            ['Spaar & beleggings', 'spaar-beleggings', 'geld'],
            ['Aftrede', 'aftrede', 'geld'],
            ['Boedels & testamente', 'boedels-testamente', 'geld'],
            ['Ouerskap', 'ouerskap', null],
            ['Kinderontwikkeling', 'kinderontwikkeling', 'ouerskap'],
            ['Opvoeding & dissipline', 'opvoeding-dissipline', 'ouerskap'],
            ['Emosies & selfvertroue', 'emosies-selfvertroue', 'ouerskap'],
            ['Skool & leer', 'skool-leer', 'ouerskap'],
            ['Tegnologie & skermtyd', 'tegnologie-skermtyd', 'ouerskap'],
            ['Gesinslewe', 'gesinslewe', 'ouerskap'],
            ['Ma-wees & balans', 'ma-wees-balans', 'ouerskap'],
            ['Huwelik', 'huwelik', null],
            ['Kommunikasie', 'kommunikasie', 'huwelik'],
            ['Liefde & verbinding', 'liefde-verbinding', 'huwelik'],
            ['Konflik & herstel', 'konflik-herstel', 'huwelik'],
            ['Vertroue & ontrouheid', 'vertroue-ontrouheid', 'huwelik'],
            ['Intimiteit', 'intimiteit', 'huwelik'],
            ['Geld & huwelik', 'geld-huwelik', 'huwelik'],
            ['Saam groei', 'saam-groei', 'huwelik'],
            ['Mensstories', 'mensstories', null],
            ['Bekende mense', 'bekende-mense', 'mensstories'],
            ['Ware lewensverhale', 'ware-lewensverhale', 'mensstories'],
        ];

        $actual = array_map(
            static fn (array $term): array => [$term['name'], $term['slug'], $term['parent']],
            ContentTopicTaxonomy::definitions()
        );

        vgpc_test_same(56, count($actual), 'The locked tree must contain 56 terms.');
        vgpc_test_same($expected, $actual, 'Names, slugs, order, and parent slugs must match the locked tree.');
    }

    public function test_registration_uses_exact_object_types_and_safe_rewrite_options(): void
    {
        vgpc_test_reset();
        $taxonomy = new ContentTopicTaxonomy();
        $taxonomy->register();

        vgpc_test_same(1, count($GLOBALS['vgpc_test_actions']['init'] ?? []), 'Registration must wait for init.');
        vgpc_test_same(100, $GLOBALS['vgpc_test_actions']['init'][0]['priority'], 'Registration must run after plugin CPTs.');
        vgpc_test_true(isset(WP_CLI::$commands['vg-content-topic-setup']), 'The explicit setup command must be registered as a standalone command.');

        $taxonomy->register_taxonomy();
        $definition = get_taxonomy('content_topic');
        vgpc_test_same(
            ['post', 'mto-course', 'product', 'tc_events', 'medewerker', 'uitsending', 'kompetisie'],
            $definition->object_type,
            'Only the seven approved object types may be attached.'
        );
        vgpc_test_same(true, $definition->hierarchical, 'The taxonomy must be hierarchical.');
        vgpc_test_same(true, $definition->public, 'The taxonomy must be public.');
        vgpc_test_same(true, $definition->publicly_queryable, 'The taxonomy must be publicly queryable.');
        vgpc_test_same(true, $definition->show_ui, 'The taxonomy must have an admin UI.');
        vgpc_test_same(true, $definition->show_in_rest, 'The taxonomy must be exposed in REST.');
        vgpc_test_same(true, $definition->show_in_nav_menus, 'The taxonomy must be available in navigation menus.');
        vgpc_test_same(true, $definition->query_var, 'The taxonomy must have a query var.');
        vgpc_test_same('Onderwerpe', $definition->labels['name'], 'The plural label must match.');
        vgpc_test_same('Onderwerp', $definition->labels['singular_name'], 'The singular label must match.');
        vgpc_test_same(
            ['slug' => 'onderwerp', 'with_front' => false, 'hierarchical' => true],
            $definition->rewrite,
            'The temporary hierarchical rewrite must stay under /onderwerp/. '
        );
    }

    public function test_setup_seeds_exactly_once_and_flushes_rewrites_once(): void
    {
        vgpc_test_reset();
        $taxonomy = new ContentTopicTaxonomy();
        $taxonomy->register_taxonomy();

        $first = $taxonomy->setup();
        vgpc_test_true(! is_wp_error($first), 'The first setup must succeed.');
        vgpc_test_same(56, $first['total_terms'], 'Setup must report 56 terms.');
        vgpc_test_same(7, $first['root_terms'], 'Setup must report seven roots.');
        vgpc_test_same(56, $first['created_terms'], 'The first setup must create all terms.');
        vgpc_test_same(true, $first['rewrite_flushed'], 'The first setup must flush rewrite rules.');

        $second = $taxonomy->setup();
        vgpc_test_true(! is_wp_error($second), 'A second setup must succeed.');
        vgpc_test_same(0, $second['created_terms'], 'A second setup must not create terms.');
        vgpc_test_same(56, $second['existing_terms'], 'A second setup must reuse every term.');
        vgpc_test_same(false, $second['rewrite_flushed'], 'A second setup must not flush again.');
        vgpc_test_same(56, count(get_terms(['taxonomy' => 'content_topic', 'hide_empty' => false])), 'The taxonomy must contain 56 terms.');
        vgpc_test_same(1, $GLOBALS['vgpc_test_rewrite_flush_count'], 'Rewrite rules must flush once only.');
        vgpc_test_same([], array_values(array_filter(
            get_terms(['taxonomy' => 'content_topic', 'hide_empty' => false]),
            static fn (object $term): bool => $term->name === 'Leefstyl' || $term->slug === 'leefstyl'
        )), 'Leefstyl must not exist in content_topic.');
        vgpc_test_same('Voeding &amp; gewig', get_term_by('slug', 'voeding-gewig', 'content_topic')->name, 'The stored ampersand must follow WordPress term-name sanitization.');

        $by_slug = [];
        foreach (get_terms(['taxonomy' => 'content_topic', 'hide_empty' => false]) as $term) {
            $by_slug[$term->slug] = $term;
        }
        $parent_slug = static fn (object $term): ?string => $term->parent === 0
            ? null
            : (get_term((int) $term->parent, 'content_topic')->slug ?? null);

        vgpc_test_same('skoonheid-versorging', $parent_slug($by_slug['velsorg']), 'Velsorg must be under Skoonheid & versorging.');
        vgpc_test_same('geld', $parent_slug($by_slug['aftrede']), 'Aftrede must be under Geld.');
        vgpc_test_same('werk', $parent_slug($by_slug['besigheid-entrepreneurskap']), 'Besigheid must be under Werk.');
        vgpc_test_same(0, $by_slug['nuwe-jy']->parent, 'Nuwe Jy must be a root.');
        vgpc_test_same(0, $by_slug['geloof']->parent, 'Geloof must be a root.');
        vgpc_test_same(0, $by_slug['self']->parent, 'Self must be a root.');
        vgpc_test_same(0, $by_slug['werk-geld']->parent, 'Werk + Geld must be a root.');
        vgpc_test_same(0, $by_slug['ouerskap']->parent, 'Ouerskap must be a root.');
        vgpc_test_same(0, $by_slug['huwelik']->parent, 'Huwelik must be a root.');
        vgpc_test_same(0, $by_slug['mensstories']->parent, 'Mensstories must be a root.');
        vgpc_test_same(7, count(array_filter($by_slug, static fn (object $term): bool => $term->parent === 0)), 'There must be seven roots.');
    }

    public function test_setup_fails_without_mutation_when_an_unexpected_term_exists(): void
    {
        vgpc_test_reset();
        $taxonomy = new ContentTopicTaxonomy();
        $taxonomy->register_taxonomy();
        vgpc_test_add_term('Leefstyl', 'leefstyl');

        $result = $taxonomy->setup();

        vgpc_test_true(is_wp_error($result), 'An unexpected term must stop setup.');
        vgpc_test_same(0, $GLOBALS['vgpc_test_insert_count'], 'Setup must not partially seed around an unexpected term.');
        vgpc_test_same(1, count(get_terms(['taxonomy' => 'content_topic', 'hide_empty' => false])), 'The existing term must remain untouched.');
        vgpc_test_same(0, $GLOBALS['vgpc_test_rewrite_flush_count'], 'Failed setup must not flush rewrites.');
    }

    public function test_setup_fails_without_reparenting_an_existing_term(): void
    {
        vgpc_test_reset();
        $taxonomy = new ContentTopicTaxonomy();
        $taxonomy->register_taxonomy();
        $wrong_parent = vgpc_test_add_term('Self', 'self');
        $existing_child = vgpc_test_add_term('Velsorg', 'velsorg', $wrong_parent->term_id);

        $result = $taxonomy->setup();

        vgpc_test_true(is_wp_error($result), 'An unexpected parent must stop setup.');
        vgpc_test_same($wrong_parent->term_id, $existing_child->parent, 'Setup must not change the existing parent.');
        vgpc_test_same(2, count(get_terms(['taxonomy' => 'content_topic', 'hide_empty' => false])), 'Setup must not create other terms after a preflight conflict.');
        vgpc_test_same(0, $GLOBALS['vgpc_test_rewrite_flush_count'], 'Failed setup must not flush rewrites.');
    }

    public function test_setup_fails_without_renaming_an_existing_term(): void
    {
        vgpc_test_reset();
        $taxonomy = new ContentTopicTaxonomy();
        $taxonomy->register_taxonomy();
        $existing = vgpc_test_add_term('Incorrect name', 'nuwe-jy');

        $result = $taxonomy->setup();

        vgpc_test_true(is_wp_error($result), 'An unexpected name must stop setup.');
        vgpc_test_same('Incorrect name', $existing->name, 'Setup must not rename an existing term.');
        vgpc_test_same(1, count(get_terms(['taxonomy' => 'content_topic', 'hide_empty' => false])), 'Setup must not seed around a name conflict.');
        vgpc_test_same(0, $GLOBALS['vgpc_test_rewrite_flush_count'], 'Failed setup must not flush rewrites.');
    }

    public function test_setup_fails_without_replacing_a_conflicting_slug(): void
    {
        vgpc_test_reset();
        $taxonomy = new ContentTopicTaxonomy();
        $taxonomy->register_taxonomy();
        $existing = vgpc_test_add_term('Voeding &amp; gewig', 'legacy-voeding');

        $result = $taxonomy->setup();

        vgpc_test_true(is_wp_error($result), 'A canonical name with another slug must stop setup.');
        vgpc_test_same('legacy-voeding', $existing->slug, 'Setup must not replace an existing conflicting slug.');
        vgpc_test_same(1, count(get_terms(['taxonomy' => 'content_topic', 'hide_empty' => false])), 'Setup must not seed around a slug conflict.');
        vgpc_test_same(0, $GLOBALS['vgpc_test_rewrite_flush_count'], 'Failed setup must not flush rewrites.');
    }

    public function test_setup_removes_a_wordpress_suffix_and_stops(): void
    {
        vgpc_test_reset();
        $taxonomy = new ContentTopicTaxonomy();
        $taxonomy->register_taxonomy();
        $GLOBALS['vgpc_test_force_suffix_next_insert'] = true;

        $result = $taxonomy->setup();

        vgpc_test_true(is_wp_error($result), 'A suffixed slug must stop setup.');
        vgpc_test_same([], get_terms(['taxonomy' => 'content_topic', 'hide_empty' => false]), 'The generated suffixed term must be removed.');
        vgpc_test_same(0, $GLOBALS['vgpc_test_rewrite_flush_count'], 'A failed setup must not flush rewrites.');
    }

    public function test_setup_leaves_legacy_terms_and_relationships_untouched(): void
    {
        vgpc_test_reset();
        $taxonomy = new ContentTopicTaxonomy();
        $taxonomy->register_taxonomy();
        $legacy_root = vgpc_test_add_term('Leefstyl', 'leefstyl', 0, 'content_category');
        $legacy_child = vgpc_test_add_term('Vakansie', 'vakansie', $legacy_root->term_id, 'content_category');
        $GLOBALS['vgpc_test_legacy_relationships'] = [
            ['object_id' => 514, 'term_id' => $legacy_root->term_id],
            ['object_id' => 523, 'term_id' => $legacy_child->term_id],
        ];
        $before_terms = array_map(
            static fn (object $term): array => [$term->term_id, $term->name, $term->slug, $term->parent],
            get_terms(['taxonomy' => 'content_category', 'hide_empty' => false])
        );
        $before_relationships = $GLOBALS['vgpc_test_legacy_relationships'];

        $result = $taxonomy->setup();

        vgpc_test_true(! is_wp_error($result), 'Topic setup must succeed beside legacy categories.');
        $after_terms = array_map(
            static fn (object $term): array => [$term->term_id, $term->name, $term->slug, $term->parent],
            get_terms(['taxonomy' => 'content_category', 'hide_empty' => false])
        );
        vgpc_test_same($before_terms, $after_terms, 'Legacy term records must remain unchanged.');
        vgpc_test_same($before_relationships, $GLOBALS['vgpc_test_legacy_relationships'], 'Legacy assignments must remain unchanged.');
    }
}
