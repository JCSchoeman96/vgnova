<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Event\EventParticipationRepository;
use Voelgoed\PlatformCore\Product\ProductContributionRepository;
use Voelgoed\PlatformCore\Product\ProductPeopleFields;
use Voelgoed\PlatformCore\Product\ProductRoleVocabulary;

final class ProductContributionRepositoryTest
{
    private const CONTRIBUTION_TYPE = 'product_contribution';
    private const PRODUCT_TYPE = 'product';
    private const PERSON_TYPE = 'person';
    private const PRODUCT_FIELD = 'product_contribution_product';
    private const PERSON_FIELD = 'product_contribution_person';
    private const DISPLAY_ORDER_FIELD = 'product_contribution_display_order';
    private const SOURCE_EVENT_FIELD = 'produk_bron_event';
    private const ROLE_TAXONOMY = 'product_role';
    private const PRODUCT = 101;
    private const PRODUCT_TWO = 102;
    private const PRODUCT_DRAFT = 103;
    private const PERSON = 201;
    private const PERSON_TWO = 202;
    private const PERSON_DRAFT = 203;
    private const PERSON_THREE = 205;
    private const PERSON_TRASHED = 204;
    private const EVENT = 301;
    private const OTHER_EVENT = 302;

    public function test_endpoint_validation_requires_existing_correct_nontrashed_products_and_people(): void
    {
        $repository = $this->repository();
        $this->add_post(self::PRODUCT, 'product');
        $this->add_post(self::PERSON, 'person');
        $this->add_post(110, 'product', 'trash');
        $this->add_post(210, 'person', 'trash');
        $this->add_post(111, 'post');
        $this->add_post(211, 'medewerker');

        vgpc_test_true($repository->is_valid_product(self::PRODUCT), 'An existing non-trashed Product must be valid.');
        vgpc_test_true($repository->is_valid_person(self::PERSON), 'An existing non-trashed Person must be valid.');
        vgpc_test_true(! $repository->is_valid_product(110), 'A trashed Product must be invalid.');
        vgpc_test_true(! $repository->is_valid_person(210), 'A trashed Person must be invalid.');
        vgpc_test_true(! $repository->is_valid_product(111), 'A post of another type must not be a valid Product.');
        vgpc_test_true(! $repository->is_valid_person(211), 'A post of another type must not be a valid Person.');
        vgpc_test_true(! $repository->is_valid_product(999), 'A missing Product must be invalid.');
        vgpc_test_true(! $repository->is_valid_person(999), 'A missing Person must be invalid.');
        vgpc_test_true(! $repository->is_valid_product(0), 'A non-positive Product ID must be invalid.');
        vgpc_test_true(! $repository->is_valid_person(-1), 'A non-positive Person ID must be invalid.');
    }

    public function test_fresh_endpoint_validation_clears_stale_post_cache(): void
    {
        $repository = $this->repository();
        $this->add_post(self::PRODUCT, 'post');
        $this->add_post(self::PERSON, 'person');
        $GLOBALS['vgpc_test_enable_query_cache'] = true;
        get_post(self::PRODUCT);
        get_post(self::PERSON);
        $GLOBALS['vgpc_test_posts'][self::PRODUCT] = $this->post(self::PRODUCT, 'product');
        $GLOBALS['vgpc_test_posts'][self::PERSON] = $this->post(self::PERSON, 'person', 'trash');

        vgpc_test_true($repository->is_valid_product_fresh(self::PRODUCT), 'A fresh Product check must not trust a stale wrong-type post cache.');
        vgpc_test_true(! $repository->is_valid_person_fresh(self::PERSON), 'A fresh Person check must not trust a stale published post cache after it was trashed.');
    }

    public function test_stored_pair_read_is_fresh_and_returns_null_when_the_post_read_is_uncertain(): void
    {
        $repository = $this->repository();
        $this->add_contribution(401, 'draft', 111, 211, [], 0);
        $GLOBALS['vgpc_test_enable_query_cache'] = true;
        get_field(self::PRODUCT_FIELD, 401, false);
        get_field(self::PERSON_FIELD, 401, false);
        $GLOBALS['vgpc_test_meta'][401][self::PRODUCT_FIELD] = self::PRODUCT;
        $GLOBALS['vgpc_test_meta'][401][self::PERSON_FIELD] = self::PERSON;

        vgpc_test_same(
            ['product_id' => self::PRODUCT, 'person_id' => self::PERSON],
            $repository->stored_pair_ids_fresh(401),
            'Stored endpoints must be re-read after WordPress and ACF caches are cleared.'
        );

        $GLOBALS['vgpc_test_fail_get_post_id'] = 401;
        vgpc_test_same(null, $repository->stored_pair_ids_fresh(401), 'An uncertain candidate post read must fail closed.');
    }

    public function test_pair_lookup_includes_all_uniqueness_statuses_excludes_current_row_and_sorts_ids(): void
    {
        $repository = $this->repository();
        $statuses = ['publish', 'draft', 'pending', 'private', 'future'];
        foreach ([405, 401, 404, 402, 403] as $offset => $id) {
            $this->add_contribution($id, $statuses[$offset], self::PRODUCT, self::PERSON, [], 0);
        }
        $this->add_contribution(406, 'trash', self::PRODUCT, self::PERSON, [], 0);
        $this->add_contribution(407, 'auto-draft', self::PRODUCT, self::PERSON, [], 0);
        $this->add_contribution(408, 'publish', self::PRODUCT_TWO, self::PERSON, [], 0);

        vgpc_test_same([401, 402, 403, 404, 405], $repository->find_pair_ids(self::PRODUCT, self::PERSON), 'Every non-retired status must reserve the pair in numeric ID order.');
        vgpc_test_same([401, 402, 404, 405], $repository->find_pair_ids(self::PRODUCT, self::PERSON, 403), 'The current contribution must be excluded from its own duplicate lookup.');
        vgpc_test_same([], $repository->find_pair_ids(0, self::PERSON), 'Invalid pair endpoints must return no matches.');
    }

    public function test_fresh_pair_lookup_bypasses_stale_post_and_acf_caches_and_uses_targeted_status_sql(): void
    {
        $repository = $this->repository();
        $this->add_contribution(401, 'draft', self::PRODUCT_TWO, self::PERSON, [], 0);
        $GLOBALS['vgpc_test_enable_query_cache'] = true;
        get_post(401);
        get_field(self::PRODUCT_FIELD, 401, false);
        get_field(self::PERSON_FIELD, 401, false);
        $GLOBALS['vgpc_test_posts'][401] = $this->post(401, self::CONTRIBUTION_TYPE, 'publish');
        $GLOBALS['vgpc_test_meta'][401][self::PRODUCT_FIELD] = self::PRODUCT;

        $statement = null;
        $GLOBALS['wpdb']->get_col_handler = static function (string $query, array $args) use (&$statement): array {
            $statement = ['query' => $query, 'args' => $args];
            return [401];
        };

        vgpc_test_same([401], $repository->find_pair_ids_fresh(self::PRODUCT, self::PERSON), 'The fresh pair check must see current database endpoint values instead of stale ACF and post caches.');
        vgpc_test_true(is_array($statement), 'The authoritative pair lookup must execute a database query.');
        vgpc_test_true(str_contains($statement['query'], 'INNER JOIN wp_postmeta'), 'The authoritative lookup must use targeted postmeta joins.');
        vgpc_test_true(str_contains($statement['query'], 'post_status IN'), 'The authoritative lookup must filter to uniqueness-reserving statuses.');
        vgpc_test_true(in_array(self::PRODUCT_FIELD, $statement['args'], true), 'The SQL must constrain the Product field.');
        vgpc_test_true(in_array(self::PERSON_FIELD, $statement['args'], true), 'The SQL must constrain the Person field.');
        vgpc_test_true(in_array(self::CONTRIBUTION_TYPE, $statement['args'], true), 'The SQL must constrain the contribution post type.');
        foreach (['publish', 'draft', 'pending', 'private', 'future'] as $status) {
            vgpc_test_true(in_array($status, $statement['args'], true), 'The SQL must reserve pairs in ' . $status . ' status.');
        }
    }

    public function test_fresh_pair_lookup_excludes_current_row_and_fails_closed_on_database_errors(): void
    {
        $repository = $this->repository();
        $this->add_contribution(401, 'publish', self::PRODUCT, self::PERSON, [], 0);
        $this->add_contribution(402, 'pending', self::PRODUCT, self::PERSON, [], 0);
        $GLOBALS['wpdb']->get_col_handler = static fn (): array => [402, 401];

        vgpc_test_same([401, 402], $repository->find_pair_ids_fresh(self::PRODUCT, self::PERSON), 'Fresh pair results must be sorted numerically even if the database adapter returns candidates out of order.');
        vgpc_test_same([402], $repository->find_pair_ids_fresh(self::PRODUCT, self::PERSON, 401), 'The fresh SQL result must exclude the contribution being edited.');

        $GLOBALS['wpdb']->get_col_handler = static function (): array {
            $GLOBALS['wpdb']->last_error = 'Injected authoritative lookup failure.';
            return [];
        };
        vgpc_test_same(null, $repository->find_pair_ids_fresh(self::PRODUCT, self::PERSON), 'An uncertain database result must not be mistaken for an empty duplicate set.');

        $GLOBALS['wpdb']->last_error = '';
        $GLOBALS['wpdb']->get_col_handler = static function (): array {
            throw new RuntimeException('Injected query exception.');
        };
        vgpc_test_same(null, $repository->find_pair_ids_fresh(self::PRODUCT, self::PERSON), 'A database exception must fail closed.');
    }

    public function test_nonretired_title_queries_keep_all_live_statuses_without_role_or_mode_filters(): void
    {
        $repository = $this->repository();
        $statuses = ['publish', 'draft', 'pending', 'private', 'future'];
        foreach ($statuses as $offset => $status) {
            $this->add_contribution(401 + $offset, $status, self::PRODUCT, self::PERSON, [], 0);
        }
        $this->add_contribution(410, 'trash', self::PRODUCT, self::PERSON, [], 0);
        $this->add_contribution(411, 'auto-draft', self::PRODUCT, self::PERSON, [], 0);
        $GLOBALS['vgpc_test_meta'][self::PRODUCT][ProductPeopleFields::MODE_FIELD] = 'invalid-mode';

        vgpc_test_same([401, 402, 403, 404, 405], $repository->non_retired_contributions_for_product(self::PRODUCT), 'Product title-resync lookup must include all non-retired rows, regardless of mode and roles.');
        vgpc_test_same([401, 402, 403, 404, 405], $repository->non_retired_contributions_for_person(self::PERSON), 'Person title-resync lookup must include all non-retired rows, regardless of mode and roles.');
    }

    public function test_active_contribution_reads_require_published_rows_approved_roles_and_current_mode(): void
    {
        $repository = $this->repository();
        $this->add_product(self::PRODUCT, 'manual');
        $this->add_product(self::PRODUCT_TWO, 'invalid-mode');
        $this->add_post(self::PERSON, 'person');
        $this->add_post(self::PERSON_TWO, 'person');
        $approved = $this->approved_role_ids();
        $fifth = vgpc_test_add_term('Mentor', 'mentor', 0, ProductRoleVocabulary::TAXONOMY);
        $this->add_contribution(401, 'publish', self::PRODUCT, self::PERSON, [$approved['aanbieder']], 0);
        $this->add_contribution(402, 'draft', self::PRODUCT, self::PERSON, [$approved['aanbieder']], 0);
        $this->add_contribution(403, 'publish', self::PRODUCT, self::PERSON_TWO, [], 0);
        $this->add_contribution(404, 'publish', self::PRODUCT, self::PERSON_TWO, [$fifth->term_id], 0);
        $this->add_contribution(405, 'publish', self::PRODUCT_TWO, self::PERSON_TWO, [$approved['aanbieder']], 0);
        $this->add_contribution(406, 'publish', self::PRODUCT, 999, [$approved['aanbieder']], 0);
        $this->add_post(207, 'person', 'trash');
        $this->add_contribution(407, 'publish', self::PRODUCT, 207, [$approved['aanbieder']], 0);

        vgpc_test_same([401], $repository->contributions_for_product(self::PRODUCT), 'Active contribution reads must omit unpublished, role-less, unapproved, and invalid endpoint rows.');
        vgpc_test_same([401], $repository->contributions_for_person(self::PERSON), 'Person-side active contribution reads must use the same publication and role checks.');
        vgpc_test_same([], $repository->contributions_for_person(self::PERSON_TWO), 'A role-less or fifth-role contribution must not become an active association.');
    }

    public function test_manual_forward_read_orders_by_display_then_id_deduplicates_and_hides_unpublished_people(): void
    {
        $repository = $this->repository();
        $this->add_product(self::PRODUCT_DRAFT, 'manual');
        $this->add_post(self::PERSON, 'person');
        $this->add_post(self::PERSON_TWO, 'person');
        $this->add_post(self::PERSON_THREE, 'person');
        $this->add_post(self::PERSON_DRAFT, 'person', 'draft');
        $approved = $this->approved_role_ids();
        $this->add_contribution(312, 'publish', self::PRODUCT_DRAFT, self::PERSON_TWO, [$approved['aanbieder']], 0);
        $this->add_contribution(308, 'publish', self::PRODUCT_DRAFT, self::PERSON_THREE, [$approved['aanbieder']], 1);
        $this->add_contribution(310, 'publish', self::PRODUCT_DRAFT, self::PERSON, [$approved['aanbieder']], 1);
        $this->add_contribution(311, 'publish', self::PRODUCT_DRAFT, self::PERSON, [$approved['aanbieder']], 1);
        $this->add_contribution(313, 'publish', self::PRODUCT_DRAFT, self::PERSON_DRAFT, [$approved['aanbieder']], 0);

        vgpc_test_same([self::PERSON_TWO, self::PERSON_THREE, self::PERSON], $repository->people_for_product(self::PRODUCT_DRAFT), 'Manual results must sort by display order then contribution ID across People, deduplicate People, and keep draft Products usable for previews.');
    }

    public function test_manual_mode_fails_closed_and_never_falls_back_to_legacy_relationships(): void
    {
        $repository = $this->repository();
        $this->add_product(self::PRODUCT, 'manual');
        $this->add_post(self::PERSON, 'person');
        $this->set_legacy_values(self::PRODUCT, [self::PERSON]);
        $GLOBALS['vgpc_test_meta'][self::PRODUCT][ProductPeopleFields::OVERRIDE_FIELD] = [self::PERSON];

        vgpc_test_same([], $repository->people_for_product(self::PRODUCT), 'Manual mode with no canonical Product Contribution must not fall back to the old override.');

        foreach (['', 'manual ', 'Event', 'invalid'] as $mode) {
            $GLOBALS['vgpc_test_meta'][self::PRODUCT][ProductPeopleFields::MODE_FIELD] = $mode;
            vgpc_test_same([], $repository->people_for_product(self::PRODUCT), 'Blank and invalid Product modes must fail closed.');
        }
        vgpc_test_same([], $repository->people_for_product(999), 'A missing Product must return no People.');
        $this->add_post(109, 'product', 'trash');
        vgpc_test_same([], $repository->people_for_product(109), 'A trashed Product must return no People.');
    }

    public function test_event_forward_membership_comes_only_from_event_participation_and_preserves_order(): void
    {
        $repository = $this->repository();
        $this->add_product(self::PRODUCT, 'event', self::EVENT);
        $this->add_post(self::EVENT, 'tc_events');
        $this->add_post(self::PERSON, 'person');
        $this->add_post(self::PERSON_TWO, 'person');
        $this->add_post(self::PERSON_DRAFT, 'person', 'draft');
        $this->add_participation(501, 'publish', self::EVENT, self::PERSON_TWO, 5);
        $this->add_participation(502, 'publish', self::EVENT, self::PERSON, 10);
        $this->add_participation(503, 'draft', self::EVENT, self::PERSON_DRAFT, 0);
        $approved = $this->approved_role_ids();
        $this->add_contribution(401, 'publish', self::PRODUCT, self::PERSON_DRAFT, [$approved['aanbieder']], 0);

        vgpc_test_same([self::PERSON_TWO, self::PERSON], $repository->people_for_product(self::PRODUCT), 'Event mode must use active Event Participation membership and its order, ignoring contribution annotations.');
    }

    public function test_event_annotations_are_optional_and_stale_annotations_disappear_from_active_reads(): void
    {
        $repository = $this->repository();
        $this->add_product(self::PRODUCT, 'event', self::EVENT);
        $this->add_post(self::EVENT, 'tc_events');
        $this->add_post(self::PERSON, 'person');
        $this->add_post(self::PERSON_TWO, 'person');
        $this->add_participation(501, 'publish', self::EVENT, self::PERSON, 4);
        $approved = $this->approved_role_ids();
        $this->add_contribution(401, 'publish', self::PRODUCT, self::PERSON, [$approved['skrywer']], 88);
        $this->add_contribution(402, 'publish', self::PRODUCT, self::PERSON_TWO, [$approved['aanbieder']], 2);

        vgpc_test_same([401], $repository->contributions_for_product(self::PRODUCT), 'A valid event annotation must be active only for current Event Participation members.');
        vgpc_test_same([self::PERSON], $repository->people_for_product(self::PRODUCT), 'A contribution annotation must not create event membership.');
        vgpc_test_same(
            [
                ['product_id' => self::PRODUCT, 'person_id' => self::PERSON, 'product_role_ids' => [$approved['skrywer']], 'display_order' => 4, 'membership_source' => 'event'],
            ],
            $repository->associations_for_product(self::PRODUCT),
            'Event associations must allow annotations only for current members and use Event Participation order.'
        );

        $GLOBALS['vgpc_test_posts'][501]->post_status = 'trash';
        vgpc_test_same([], $repository->contributions_for_product(self::PRODUCT), 'A stale Event annotation must disappear when Event Participation is retired.');
        vgpc_test_same([], $repository->associations_for_product(self::PRODUCT), 'A stale annotation must not preserve Event-derived membership.');
    }

    public function test_reverse_products_union_manual_and_event_membership_deduplicate_and_sort_published_products(): void
    {
        $repository = $this->repository();
        $this->add_post(self::PERSON, 'person');
        $this->add_post(self::EVENT, 'tc_events');
        $this->add_post(self::OTHER_EVENT, 'tc_events');
        $this->add_product(self::PRODUCT, 'manual');
        $this->add_product(self::PRODUCT_TWO, 'event', self::EVENT);
        $this->add_product(self::PRODUCT_DRAFT, 'manual', 0, 'draft');
        $this->add_product(104, 'invalid-mode', self::EVENT);
        $this->add_product(105, 'event', self::OTHER_EVENT);
        $this->add_participation(501, 'publish', self::EVENT, self::PERSON, 2);
        $this->add_participation(502, 'publish', self::EVENT, self::PERSON, 3);
        $this->add_participation(503, 'publish', self::OTHER_EVENT, self::PERSON_TWO, 0);
        $approved = $this->approved_role_ids();
        $this->add_contribution(401, 'publish', self::PRODUCT, self::PERSON, [$approved['aanbieder']], 0);
        $this->add_contribution(402, 'publish', self::PRODUCT, self::PERSON, [$approved['skrywer']], 1);

        $GLOBALS['vgpc_test_get_posts_handler'] = static function (array $args): ?array {
            if (($args['post_type'] ?? null) === 'product') {
                return [self::PRODUCT_TWO, self::PRODUCT, self::PRODUCT_TWO, self::PRODUCT_DRAFT, 104, 105];
            }

            return null;
        };

        vgpc_test_same([self::PRODUCT, self::PRODUCT_TWO], $repository->products_for_person(self::PERSON), 'Reverse results must union manual and event memberships, deduplicate duplicate contribution candidates, exclude invalid/unpublished Products, and sort Product IDs.');
    }

    public function test_reverse_products_require_a_published_person(): void
    {
        $repository = $this->repository();
        $this->add_post(self::PERSON_DRAFT, 'person', 'draft');
        $this->add_product(self::PRODUCT, 'manual');
        $approved = $this->approved_role_ids();
        $this->add_contribution(401, 'publish', self::PRODUCT, self::PERSON_DRAFT, [$approved['aanbieder']], 0);

        vgpc_test_same([], $repository->products_for_person(self::PERSON_DRAFT), 'Reverse Products must be empty for an unpublished Person.');
    }

    public function test_manual_associations_are_canonical_both_directions_with_sorted_roles(): void
    {
        $repository = $this->repository();
        $this->add_product(self::PRODUCT, 'manual');
        $this->add_post(self::PERSON, 'person');
        $approved = $this->approved_role_ids();
        $this->add_contribution(401, 'publish', self::PRODUCT, self::PERSON, [$approved['skepper'], $approved['aanbieder']], 7);
        $expected = [
            'product_id' => self::PRODUCT,
            'person_id' => self::PERSON,
            'product_role_ids' => [$approved['aanbieder'], $approved['skepper']],
            'display_order' => 7,
            'membership_source' => 'manual',
        ];

        vgpc_test_same([$expected], $repository->associations_for_product(self::PRODUCT), 'Manual Product associations must use contribution roles/order/source.');
        vgpc_test_same([$expected], $repository->associations_for_person(self::PERSON), 'Manual Person associations must use the same canonical contribution values.');
    }

    public function test_event_associations_keep_unannotated_members_and_use_event_order_not_annotation_order(): void
    {
        $repository = $this->repository();
        $this->add_product(self::PRODUCT, 'event', self::EVENT);
        $this->add_post(self::EVENT, 'tc_events');
        $this->add_post(self::PERSON, 'person');
        $this->add_post(self::PERSON_TWO, 'person');
        $this->add_participation(501, 'publish', self::EVENT, self::PERSON, 8);
        $this->add_participation(502, 'publish', self::EVENT, self::PERSON_TWO, 3);
        $approved = $this->approved_role_ids();
        $this->add_contribution(401, 'publish', self::PRODUCT, self::PERSON, [$approved['skepper'], $approved['aanbieder']], 999);
        $roles = [$approved['aanbieder'], $approved['skepper']];

        $for_product = [
            ['product_id' => self::PRODUCT, 'person_id' => self::PERSON_TWO, 'product_role_ids' => [], 'display_order' => 3, 'membership_source' => 'event'],
            ['product_id' => self::PRODUCT, 'person_id' => self::PERSON, 'product_role_ids' => $roles, 'display_order' => 8, 'membership_source' => 'event'],
        ];
        vgpc_test_same($for_product, $repository->associations_for_product(self::PRODUCT), 'Event Product associations must preserve canonical participation order and optional sorted role annotations.');

        $for_person = $repository->associations_for_person(self::PERSON);
        vgpc_test_same([$for_product[1]], $for_person, 'Event Person associations must use the participation order and optional Product-role annotation.');
    }

    public function test_repository_delegates_event_order_and_never_queries_event_participation_directly(): void
    {
        $repository = $this->repository();
        $source_file = (new ReflectionClass($repository))->getFileName();
        vgpc_test_true(is_string($source_file), 'The Product Contribution repository source must be available for boundary verification.');
        $source = (string) file_get_contents($source_file);

        vgpc_test_true(str_contains($source, 'display_order_for_pair('), 'Event association order must use EventParticipationRepository::display_order_for_pair().');
        vgpc_test_true(! str_contains($source, 'event_participation_display_order'), 'Product Contribution must not read the Event Participation display-order field.');
        vgpc_test_true(! str_contains($source, "'event_participation'"), 'Product Contribution must not query Event Participation posts directly.');
        vgpc_test_true(! str_contains($source, 'event_medewerkers'), 'Event legacy People must not be a repository fallback.');
        vgpc_test_true(! str_contains($source, 'produk_medewerkers'), 'Legacy Product People must not be a repository fallback.');
        vgpc_test_true(! str_contains($source, 'medewerker'), 'Legacy Medewerker records must not be a repository fallback.');
        vgpc_test_true(! str_contains($source, '_event_name'), 'Event commerce metadata must not be a repository fallback.');
    }

    private function repository(): ProductContributionRepository
    {
        vgpc_test_reset();
        $this->register_fields();
        vgpc_test_true(class_exists(ProductContributionRepository::class), 'ProductContributionRepository is not implemented yet.');

        return new ProductContributionRepository(new EventParticipationRepository());
    }

    private function register_fields(): void
    {
        acf_add_local_field_group([
            'key' => 'group_product_contribution_repository_test',
            'fields' => [
                ['key' => 'field_test_pc_product', 'name' => self::PRODUCT_FIELD],
                ['key' => 'field_test_pc_person', 'name' => self::PERSON_FIELD],
                ['key' => 'field_test_pc_roles', 'name' => 'product_contribution_roles'],
                ['key' => 'field_test_pc_order', 'name' => self::DISPLAY_ORDER_FIELD],
                ['key' => 'field_test_product_mode', 'name' => ProductPeopleFields::MODE_FIELD],
                ['key' => 'field_test_source_event', 'name' => self::SOURCE_EVENT_FIELD],
                ['key' => 'field_test_event', 'name' => EventParticipationRepository::EVENT_FIELD],
                ['key' => 'field_test_event_person', 'name' => EventParticipationRepository::PERSON_FIELD],
                ['key' => 'field_test_event_order', 'name' => EventParticipationRepository::DISPLAY_ORDER_FIELD],
            ],
        ]);
    }

    private function add_product(int $id, string $mode, int $event_id = 0, string $status = 'publish'): void
    {
        $this->add_post($id, self::PRODUCT_TYPE, $status);
        $GLOBALS['vgpc_test_meta'][$id][ProductPeopleFields::MODE_FIELD] = $mode;
        $GLOBALS['vgpc_test_meta'][$id][self::SOURCE_EVENT_FIELD] = $event_id;
    }

    private function add_contribution(
        int $id,
        string $status,
        int|string $product_id,
        int|string $person_id,
        array $role_ids,
        int $display_order
    ): void {
        $this->add_post($id, self::CONTRIBUTION_TYPE, $status);
        $GLOBALS['vgpc_test_meta'][$id][self::PRODUCT_FIELD] = $product_id;
        $GLOBALS['vgpc_test_meta'][$id][self::PERSON_FIELD] = $person_id;
        $GLOBALS['vgpc_test_meta'][$id][self::DISPLAY_ORDER_FIELD] = $display_order;
        $GLOBALS['vgpc_test_meta'][$id]['product_contribution_roles'] = $role_ids;
        if ($role_ids !== []) {
            wp_set_object_terms($id, $role_ids, self::ROLE_TAXONOMY, false);
        }
    }

    private function add_participation(int $id, string $status, int $event_id, int $person_id, int $display_order): void
    {
        $this->add_post($id, EventParticipationRepository::POST_TYPE, $status);
        $GLOBALS['vgpc_test_meta'][$id][EventParticipationRepository::EVENT_FIELD] = $event_id;
        $GLOBALS['vgpc_test_meta'][$id][EventParticipationRepository::PERSON_FIELD] = $person_id;
        $GLOBALS['vgpc_test_meta'][$id][EventParticipationRepository::DISPLAY_ORDER_FIELD] = $display_order;
    }

    private function add_post(int $id, string $post_type, string $status = 'publish'): void
    {
        $GLOBALS['vgpc_test_posts'][$id] = $this->post($id, $post_type, $status);
    }

    private function post(int $id, string $post_type, string $status = 'publish'): object
    {
        return (object) [
            'ID' => $id,
            'post_type' => $post_type,
            'post_status' => $status,
            'post_title' => 'Post ' . $id,
        ];
    }

    /** @return array<string, int> */
    private function approved_role_ids(): array
    {
        $ids = [];
        foreach (ProductRoleVocabulary::approved_terms() as $slug => $label) {
            $ids[$slug] = vgpc_test_add_term($label, $slug, 0, ProductRoleVocabulary::TAXONOMY)->term_id;
        }

        return $ids;
    }

    private function set_legacy_values(int $product_id, array $person_ids): void
    {
        $GLOBALS['vgpc_test_meta'][$product_id]['produk_medewerkers'] = $person_ids;
        $GLOBALS['vgpc_test_meta'][$product_id]['event_medewerkers'] = $person_ids;
    }
}
