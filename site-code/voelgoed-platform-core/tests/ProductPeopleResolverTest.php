<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Event\EventParticipationRepository;
use Voelgoed\PlatformCore\Product\ProductPeopleFields;
use Voelgoed\PlatformCore\Product\ProductPeopleResolver;

final class ProductPeopleResolverTest
{
    private const EVENT = 101;
    private const EVENT_TRASHED = 102;
    private const EVENT_WRONG_TYPE = 103;
    private const PERSON_A = 201;
    private const PERSON_B = 202;
    private const PERSON_DRAFT = 203;
    private const PERSON_PRIVATE = 204;
    private const PERSON_E = 205;
    private const MEDEWERKER = 301;
    private const PRODUCT = 401;
    private const PRODUCT_DRAFT = 402;
    private const NON_PRODUCT = 403;

    public function test_invalid_product_ids_return_empty(): void
    {
        $this->fixture();
        $this->set_mode(self::PRODUCT, 'event');
        $this->set_source(self::PRODUCT, self::EVENT);

        vgpc_test_same([], $this->resolver()->people_for_product(0), 'A zero Product ID must resolve to no People.');
        vgpc_test_same([], $this->resolver()->people_for_product(-5), 'A negative Product ID must resolve to no People.');
        vgpc_test_same([], $this->resolver()->people_for_product(999), 'A missing Product must resolve to no People.');
    }

    public function test_non_product_post_returns_empty(): void
    {
        $this->fixture();
        $this->set_mode(self::NON_PRODUCT, 'event');
        $this->set_source(self::NON_PRODUCT, self::EVENT);
        $this->participation(501, 'publish', self::EVENT, self::PERSON_B, 10);

        vgpc_test_same([], $this->resolver()->people_for_product(self::NON_PRODUCT), 'A non-product post must never resolve People.');
    }

    public function test_draft_product_may_resolve_when_otherwise_configured(): void
    {
        $this->event_fixture();
        $this->set_mode(self::PRODUCT_DRAFT, 'event');
        $this->set_source(self::PRODUCT_DRAFT, self::EVENT);
        $this->participation(501, 'publish', self::EVENT, self::PERSON_B, 10);
        $this->participation(502, 'publish', self::EVENT, self::PERSON_A, 20);

        vgpc_test_same([self::PERSON_B, self::PERSON_A], $this->resolver()->people_for_product(self::PRODUCT_DRAFT), 'Product publication status must not gate People resolution.');
    }

    public function test_absent_mode_with_valid_source_event_returns_empty(): void
    {
        $this->event_fixture();
        $this->set_source(self::PRODUCT, self::EVENT);

        vgpc_test_same([], $this->resolver()->people_for_product(self::PRODUCT), 'An absent mode must remain UNCONFIGURED even with a populated source Event.');
    }

    public function test_empty_stored_mode_with_valid_source_event_returns_empty(): void
    {
        $this->event_fixture();
        $this->set_mode(self::PRODUCT, '');
        $this->set_source(self::PRODUCT, self::EVENT);

        vgpc_test_same([], $this->resolver()->people_for_product(self::PRODUCT), 'An empty stored mode must remain UNCONFIGURED even with a populated source Event.');
    }

    public function test_invalid_non_empty_modes_return_empty(): void
    {
        $this->event_fixture();
        $this->set_source(self::PRODUCT, self::EVENT);

        foreach (['Event', 'EVENT', ' event', 'event ', 'manual ', 'Manual', 'other', '0', '1'] as $mode) {
            $this->set_mode(self::PRODUCT, $mode);
            vgpc_test_same([], $this->resolver()->people_for_product(self::PRODUCT), 'Invalid stored mode "' . $mode . '" must fail closed.');
        }
    }

    public function test_event_mode_without_source_event_returns_empty(): void
    {
        $this->fixture();
        $this->set_mode(self::PRODUCT, 'event');
        $this->participation(501, 'publish', self::EVENT, self::PERSON_B, 10);

        vgpc_test_same([], $this->resolver()->people_for_product(self::PRODUCT), 'Event mode without a source Event must return no People.');
    }

    public function test_event_mode_with_trashed_or_wrong_type_source_event_returns_empty(): void
    {
        $this->fixture();
        $this->set_mode(self::PRODUCT, 'event');
        $this->participation(501, 'publish', self::EVENT, self::PERSON_B, 10);

        $this->set_source(self::PRODUCT, self::EVENT_TRASHED);
        vgpc_test_same([], $this->resolver()->people_for_product(self::PRODUCT), 'A trashed source Event must return no People.');

        $this->set_source(self::PRODUCT, self::EVENT_WRONG_TYPE);
        vgpc_test_same([], $this->resolver()->people_for_product(self::PRODUCT), 'A source post of another type must return no People.');
    }

    public function test_event_mode_with_missing_source_event_returns_empty(): void
    {
        $this->fixture();
        $this->set_mode(self::PRODUCT, 'event');
        $this->set_source(self::PRODUCT, 999);

        vgpc_test_same([], $this->resolver()->people_for_product(self::PRODUCT), 'A missing source Event must return no People.');
    }

    public function test_event_mode_with_malformed_source_event_returns_empty(): void
    {
        $this->event_fixture();
        $this->set_mode(self::PRODUCT, 'event');

        foreach ([[self::EVENT], ['event' => self::EVENT], self::EVENT . 'abc', 0, -101, '', false, null, 1.5] as $source) {
            $this->set_source(self::PRODUCT, $source);
            vgpc_test_same([], $this->resolver()->people_for_product(self::PRODUCT), 'A malformed source Event value must fail closed.');
        }
    }

    public function test_event_mode_preserves_repository_participation_order(): void
    {
        $this->event_fixture();
        $this->set_mode(self::PRODUCT, 'event');
        $this->set_source(self::PRODUCT, self::EVENT);
        $this->participation(503, 'publish', self::EVENT, self::PERSON_E, 5);

        vgpc_test_same(
            [self::PERSON_E, self::PERSON_B, self::PERSON_A],
            $this->resolver()->people_for_product(self::PRODUCT),
            'Event mode must preserve the Event Participation display order.'
        );
    }

    public function test_event_mode_filters_missing_and_wrong_type_person_endpoints(): void
    {
        $this->event_fixture();
        $this->set_mode(self::PRODUCT, 'event');
        $this->set_source(self::PRODUCT, self::EVENT);
        $this->participation(504, 'publish', self::EVENT, 999, 1);
        $this->participation(505, 'publish', self::EVENT, self::MEDEWERKER, 2);

        vgpc_test_same(
            [self::PERSON_B, self::PERSON_A],
            $this->resolver()->people_for_product(self::PRODUCT),
            'Missing and non-person endpoints must not appear in Event-derived People.'
        );
    }

    public function test_event_mode_filters_draft_and_private_persons(): void
    {
        $this->event_fixture();
        $this->set_mode(self::PRODUCT, 'event');
        $this->set_source(self::PRODUCT, self::EVENT);
        $this->participation(506, 'publish', self::EVENT, self::PERSON_DRAFT, 1);
        $this->participation(507, 'publish', self::EVENT, self::PERSON_PRIVATE, 2);

        vgpc_test_same(
            [self::PERSON_B, self::PERSON_A],
            $this->resolver()->people_for_product(self::PRODUCT),
            'Published participations pointing at draft or private Persons must be filtered at Product level.'
        );
    }

    public function test_event_mode_excludes_non_published_participations_through_the_repository(): void
    {
        $this->event_fixture();
        $this->set_mode(self::PRODUCT, 'event');
        $this->set_source(self::PRODUCT, self::EVENT);
        $this->participation(508, 'draft', self::EVENT, self::PERSON_E, 0);

        vgpc_test_same(
            [self::PERSON_B, self::PERSON_A],
            $this->resolver()->people_for_product(self::PRODUCT),
            'Draft participations must stay outside the Event Participation contract.'
        );
    }

    public function test_event_mode_deduplicates_person_ids_preserving_first_occurrence(): void
    {
        $this->fixture();
        $this->set_mode(self::PRODUCT, 'event');
        $this->set_source(self::PRODUCT, self::EVENT);
        $this->participation(510, 'publish', self::EVENT, self::PERSON_A, 1);
        $this->participation(511, 'publish', self::EVENT, self::PERSON_A, 2);
        $this->participation(512, 'publish', self::EVENT, self::PERSON_B, 3);

        vgpc_test_same(
            [self::PERSON_A, self::PERSON_B],
            $this->resolver()->people_for_product(self::PRODUCT),
            'Duplicate Person endpoints must appear once, at their first participation position.'
        );
    }

    public function test_event_mode_ignores_populated_manual_override(): void
    {
        $this->event_fixture();
        $this->set_mode(self::PRODUCT, 'event');
        $this->set_source(self::PRODUCT, self::EVENT);
        $this->set_override(self::PRODUCT, [self::PERSON_E]);

        vgpc_test_same(
            [self::PERSON_B, self::PERSON_A],
            $this->resolver()->people_for_product(self::PRODUCT),
            'Event mode must ignore stale manual override data.'
        );
    }

    public function test_manual_mode_preserves_override_order(): void
    {
        $this->event_fixture();
        $this->set_mode(self::PRODUCT, 'manual');
        $this->set_override(self::PRODUCT, [self::PERSON_E, self::PERSON_A, self::PERSON_B]);

        vgpc_test_same(
            [self::PERSON_E, self::PERSON_A, self::PERSON_B],
            $this->resolver()->people_for_product(self::PRODUCT),
            'Manual mode must preserve the editor-selected order.'
        );
    }

    public function test_manual_mode_accepts_numeric_string_ids_and_returns_integers(): void
    {
        $this->event_fixture();
        $this->set_mode(self::PRODUCT, 'manual');
        $this->set_override(self::PRODUCT, [(string) self::PERSON_B, (string) self::PERSON_A]);

        $result = $this->resolver()->people_for_product(self::PRODUCT);
        vgpc_test_same([self::PERSON_B, self::PERSON_A], $result, 'Digit-only numeric strings must resolve to Person IDs.');
        vgpc_test_true(array_is_list($result), 'People results must be a zero-indexed list.');
        vgpc_test_true(is_int($result[0]) && is_int($result[1]), 'People results must contain integers.');
    }

    public function test_manual_mode_empty_override_returns_empty_despite_valid_source_event(): void
    {
        $this->event_fixture();
        $this->set_mode(self::PRODUCT, 'manual');
        $this->set_source(self::PRODUCT, self::EVENT);
        $this->set_override(self::PRODUCT, []);

        vgpc_test_same([], $this->resolver()->people_for_product(self::PRODUCT), 'An intentionally empty manual override must never fall back to Event People.');
    }

    public function test_manual_mode_filters_invalid_override_endpoints(): void
    {
        $this->event_fixture();
        $this->set_mode(self::PRODUCT, 'manual');
        $this->set_override(self::PRODUCT, [0, -5, 'abc', '12abc', 1.5, null, true, 999, self::MEDEWERKER, self::PERSON_B]);

        vgpc_test_same([self::PERSON_B], $this->resolver()->people_for_product(self::PRODUCT), 'Only positive integer or digit-string IDs that are published Persons may survive.');
    }

    public function test_manual_mode_filters_draft_and_private_persons(): void
    {
        $this->event_fixture();
        $this->set_mode(self::PRODUCT, 'manual');
        $this->set_override(self::PRODUCT, [self::PERSON_DRAFT, self::PERSON_PRIVATE, self::PERSON_A]);

        vgpc_test_same([self::PERSON_A], $this->resolver()->people_for_product(self::PRODUCT), 'Manual mode must filter draft and private Persons.');
    }

    public function test_manual_mode_deduplicates_preserving_first_occurrence(): void
    {
        $this->event_fixture();
        $this->set_mode(self::PRODUCT, 'manual');
        $this->set_override(self::PRODUCT, [self::PERSON_A, self::PERSON_B, self::PERSON_A, (string) self::PERSON_B]);

        vgpc_test_same([self::PERSON_A, self::PERSON_B], $this->resolver()->people_for_product(self::PRODUCT), 'Manual duplicates must be removed after the first occurrence.');
    }

    public function test_manual_mode_ignores_produk_bron_event(): void
    {
        $this->event_fixture();
        $this->set_mode(self::PRODUCT, 'manual');
        $this->set_source(self::PRODUCT, self::EVENT);
        $this->set_override(self::PRODUCT, [self::PERSON_E]);

        vgpc_test_same([self::PERSON_E], $this->resolver()->people_for_product(self::PRODUCT), 'Manual mode must ignore the source Event entirely.');
    }

    public function test_non_array_manual_value_fails_closed(): void
    {
        $this->event_fixture();
        $this->set_mode(self::PRODUCT, 'manual');

        foreach ([self::PERSON_A, (string) self::PERSON_A, false, null, '', 'abc'] as $value) {
            $this->set_override(self::PRODUCT, $value);
            vgpc_test_same([], $this->resolver()->people_for_product(self::PRODUCT), 'A non-array manual value must fail closed.');
        }
    }

    public function test_produk_medewerkers_is_never_used_as_a_fallback(): void
    {
        $this->fixture();
        $this->set_meta(self::PRODUCT, 'produk_medewerkers', [self::MEDEWERKER]);

        $this->set_mode(self::PRODUCT, 'event');
        vgpc_test_same([], $this->resolver()->people_for_product(self::PRODUCT), 'Event mode must never fall back to produk_medewerkers.');

        $this->set_mode(self::PRODUCT, 'manual');
        vgpc_test_same([], $this->resolver()->people_for_product(self::PRODUCT), 'Manual mode must never fall back to produk_medewerkers.');

        $this->set_mode(self::PRODUCT, '');
        vgpc_test_same([], $this->resolver()->people_for_product(self::PRODUCT), 'UNCONFIGURED must never fall back to produk_medewerkers.');
    }

    public function test_resolution_performs_no_writes(): void
    {
        $this->event_fixture();
        $this->set_source(self::PRODUCT, self::EVENT);
        $this->set_override(self::PRODUCT, [self::PERSON_A]);
        $this->set_meta(self::PRODUCT, 'produk_medewerkers', [self::MEDEWERKER]);
        $resolver = $this->resolver();

        foreach (['event', 'manual', '', 'invalid'] as $mode) {
            $this->set_mode(self::PRODUCT, $mode);
            $meta_before = $GLOBALS['vgpc_test_meta'];
            $posts_before = $GLOBALS['vgpc_test_posts'];

            $resolver->people_for_product(self::PRODUCT);

            vgpc_test_same($meta_before, $GLOBALS['vgpc_test_meta'], 'Resolution in mode "' . $mode . '" must not write any post meta.');
            vgpc_test_same($posts_before, $GLOBALS['vgpc_test_posts'], 'Resolution in mode "' . $mode . '" must not create or change posts.');
        }

        vgpc_test_same([], $GLOBALS['vgpc_test_update_field_calls'], 'Resolution must not call update_field().');
        vgpc_test_same([], $GLOBALS['vgpc_test_update_post_calls'], 'Resolution must not call wp_update_post().');
        vgpc_test_same([], $GLOBALS['vgpc_test_insert_post_calls'], 'Resolution must not insert posts.');
    }

    public function test_default_constructor_builds_the_canonical_repository(): void
    {
        $this->event_fixture();
        $this->set_mode(self::PRODUCT, 'event');
        $this->set_source(self::PRODUCT, self::EVENT);

        vgpc_test_same(
            [self::PERSON_B, self::PERSON_A],
            (new ProductPeopleResolver())->people_for_product(self::PRODUCT),
            'The default constructor must use the canonical Event Participation repository.'
        );
    }

    private function resolver(): ProductPeopleResolver
    {
        return new ProductPeopleResolver(new EventParticipationRepository());
    }

    /** Base fixture: field definitions plus Events, Persons, Products, and a legacy Medewerker. */
    private function fixture(): void
    {
        vgpc_test_reset();
        acf_add_local_field_group([
            'key' => 'group_product_people_resolver_test',
            'fields' => [
                ['key' => 'field_produk_bron_event', 'name' => 'produk_bron_event'],
                ['key' => 'field_produk_medewerkers', 'name' => 'produk_medewerkers'],
                ['key' => ProductPeopleFields::MODE_FIELD_KEY, 'name' => ProductPeopleFields::MODE_FIELD],
                ['key' => ProductPeopleFields::OVERRIDE_FIELD_KEY, 'name' => ProductPeopleFields::OVERRIDE_FIELD],
                ['key' => 'field_event_participation_event', 'name' => 'event_participation_event'],
                ['key' => 'field_event_participation_person', 'name' => 'event_participation_person'],
                ['key' => 'field_event_participation_display_order', 'name' => 'event_participation_display_order'],
            ],
        ]);

        $this->post(self::EVENT, 'tc_events');
        $this->post(self::EVENT_TRASHED, 'tc_events', 'trash');
        $this->post(self::EVENT_WRONG_TYPE, 'post');
        $this->post(self::PERSON_A, 'person');
        $this->post(self::PERSON_B, 'person');
        $this->post(self::PERSON_DRAFT, 'person', 'draft');
        $this->post(self::PERSON_PRIVATE, 'person', 'private');
        $this->post(self::PERSON_E, 'person');
        $this->post(self::MEDEWERKER, 'medewerker');
        $this->post(self::PRODUCT, 'product');
        $this->post(self::PRODUCT_DRAFT, 'product', 'draft');
        $this->post(self::NON_PRODUCT, 'post');
    }

    /** Fixture plus two published participations for the valid Event. */
    private function event_fixture(): void
    {
        $this->fixture();
        $this->participation(501, 'publish', self::EVENT, self::PERSON_B, 10);
        $this->participation(502, 'publish', self::EVENT, self::PERSON_A, 20);
    }

    private function post(int $id, string $post_type, string $post_status = 'publish'): void
    {
        $GLOBALS['vgpc_test_posts'][$id] = (object) [
            'ID' => $id,
            'post_type' => $post_type,
            'post_status' => $post_status,
        ];
    }

    private function set_meta(int $post_id, string $meta_key, mixed $value): void
    {
        $GLOBALS['vgpc_test_meta'][$post_id][$meta_key] = $value;
    }

    private function set_mode(int $product_id, mixed $mode): void
    {
        $this->set_meta($product_id, ProductPeopleFields::MODE_FIELD, $mode);
    }

    private function set_source(int $product_id, mixed $event_id): void
    {
        $this->set_meta($product_id, 'produk_bron_event', $event_id);
    }

    /** @param list<mixed> $person_ids */
    private function set_override(int $product_id, mixed $person_ids): void
    {
        $this->set_meta($product_id, ProductPeopleFields::OVERRIDE_FIELD, $person_ids);
    }

    private function participation(
        int $id,
        string $post_status,
        int|string $event_id,
        int|string $person_id,
        int $display_order
    ): void {
        $this->post($id, 'event_participation', $post_status);
        $this->set_meta($id, 'event_participation_event', $event_id);
        $this->set_meta($id, 'event_participation_person', $person_id);
        $this->set_meta($id, 'event_participation_display_order', $display_order);
    }
}
