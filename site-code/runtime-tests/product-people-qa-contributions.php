<?php
declare(strict_types=1);

/**
 * Shared runtime QA helpers for canonical manual Product People via Product Contributions.
 */

use Voelgoed\PlatformCore\Product\ProductContributionRepository;
use Voelgoed\PlatformCore\Product\ProductContributionService;
use Voelgoed\PlatformCore\Product\ProductPeopleFields;
use Voelgoed\PlatformCore\Product\ProductPeopleResolver;
use Voelgoed\PlatformCore\Product\ProductRoleVocabulary;

function vg_product_people_qa_assert_approved_product_role_vocabulary(): void
{
    vg_product_people_qa_check(
        class_exists(ProductRoleVocabulary::class),
        'ProductRoleVocabulary must be loaded before QA Product Contribution fixtures run.'
    );
    vg_product_people_qa_check(
        taxonomy_exists(ProductRoleVocabulary::TAXONOMY),
        'The product_role taxonomy must be registered before QA Product Contribution fixtures run.'
    );

    foreach (ProductRoleVocabulary::approved_terms() as $slug => $label) {
        $term = get_term_by('slug', $slug, ProductRoleVocabulary::TAXONOMY);
        vg_product_people_qa_check(
            is_object($term)
                && ! is_wp_error($term)
                && ($term->name ?? null) === $label
                && ProductRoleVocabulary::is_approved_term_id((int) $term->term_id),
            'Approved Product Role vocabulary is incomplete; run ProductRoleSeeder before QA (missing or invalid term: ' . $slug . ').'
        );
    }
}

/** Resolve the canonical Aanbieder role for QA contribution fixtures. */
function vg_product_people_qa_default_product_role_term_id(): int
{
    static $cached = null;
    if (is_int($cached)) {
        return $cached;
    }

    vg_product_people_qa_assert_approved_product_role_vocabulary();
    $term = get_term_by('slug', 'aanbieder', ProductRoleVocabulary::TAXONOMY);
    vg_product_people_qa_check(
        is_object($term) && ! is_wp_error($term) && ProductRoleVocabulary::is_approved_term_id((int) $term->term_id),
        'The canonical Aanbieder Product Role term must exist before QA contributions are created.'
    );

    $cached = (int) $term->term_id;

    return $cached;
}

function vg_product_people_qa_contribution_service(): ProductContributionService
{
    vg_product_people_qa_check(
        class_exists(ProductContributionService::class),
        'ProductContributionService must be loaded before QA Product Contribution fixtures run.'
    );

    return new ProductContributionService();
}

/** @param array{product_id:int,person_id:int} $expected_pair */
function vg_product_people_qa_assert_owned_contribution_delete_target(int $contribution_id, string $owner, array $expected_pair): void
{
    vg_product_people_qa_check($contribution_id > 0, 'A contribution delete target must have a positive ID.');
    vg_product_people_qa_check(
        get_post_meta($contribution_id, VG_PRODUCT_PEOPLE_QA_OWNER_META, true) === $owner,
        'Cleanup refused an unowned Product Contribution.'
    );
    $post = get_post($contribution_id);
    vg_product_people_qa_check(
        $post instanceof WP_Post && $post->post_type === ProductContributionRepository::POST_TYPE,
        'Cleanup refused a Product Contribution with an unexpected post type.'
    );
    vg_product_people_qa_check(
        (int) get_field(ProductContributionRepository::PRODUCT_FIELD, $contribution_id) === (int) $expected_pair['product_id']
            && (int) get_field(ProductContributionRepository::PERSON_FIELD, $contribution_id) === (int) $expected_pair['person_id'],
        'Cleanup refused a Product Contribution with unexpected endpoints.'
    );
}

/**
 * @param array<int, array{product_id:int, person_id:int}> $owned_contributions
 */
function vg_product_people_qa_create_owned_contribution(
    int $product_id,
    int $person_id,
    int $display_order,
    string $owner,
    array &$owned_contributions,
    callable $mark,
    string $status = 'publish',
    ?array $product_role_term_ids = null
): int {
    if ($product_role_term_ids === null) {
        $product_role_term_ids = [vg_product_people_qa_default_product_role_term_id()];
    }

    $created = vg_product_people_qa_contribution_service()->create(
        $product_id,
        $person_id,
        $product_role_term_ids,
        $display_order,
        $status
    );
    vg_product_people_qa_check(! is_wp_error($created) && (int) $created > 0, 'Could not create a QA Product Contribution through the canonical service.');
    $contribution_id = (int) $created;
    $owned_contributions[$contribution_id] = ['product_id' => $product_id, 'person_id' => $person_id];
    $mark($contribution_id, ProductContributionRepository::POST_TYPE, $owner);

    return $contribution_id;
}

/**
 * @param array<int, array{product_id:int, person_id:int}> $owned_contributions
 * @param list<int> $person_ids
 */
function vg_product_people_qa_replace_owned_contributions(
    int $product_id,
    array $person_ids,
    string $owner,
    array &$owned_contributions,
    callable $mark,
    ?callable $delete_owned = null
): void {
    vg_product_people_qa_check(
        class_exists(ProductContributionRepository::class),
        'ProductContributionRepository must be loaded before QA Product Contribution fixtures run.'
    );
    vg_product_people_qa_check(
        post_type_exists(ProductContributionRepository::POST_TYPE),
        'The product_contribution post type must be registered before QA Product Contribution fixtures run.'
    );

    $delete = $delete_owned ?? static function (int $contribution_id, string $post_type, string $owner_marker, ?callable $endpoint_check = null): void {
        $pair = [
            'product_id' => (int) get_field(ProductContributionRepository::PRODUCT_FIELD, $contribution_id),
            'person_id' => (int) get_field(ProductContributionRepository::PERSON_FIELD, $contribution_id),
        ];
        vg_product_people_qa_assert_owned_contribution_delete_target($contribution_id, $owner_marker, $pair);
        if ($endpoint_check !== null) {
            $endpoint_check();
        }
        wp_delete_post($contribution_id, true);
        vg_product_people_qa_check(get_post($contribution_id) === null, 'Product Contribution hard delete did not complete.');
    };

    foreach (array_keys($owned_contributions) as $contribution_id) {
        $contribution_id = (int) $contribution_id;
        if (($owned_contributions[$contribution_id]['product_id'] ?? 0) !== $product_id) {
            continue;
        }
        $pair = $owned_contributions[$contribution_id];
        vg_product_people_qa_assert_owned_contribution_delete_target($contribution_id, $owner, $pair);
        $delete($contribution_id, ProductContributionRepository::POST_TYPE, $owner, null);
        unset($owned_contributions[$contribution_id]);
    }

    foreach (array_values($person_ids) as $index => $person_id) {
        $person_id = (int) $person_id;
        if ($person_id <= 0) {
            continue;
        }

        vg_product_people_qa_create_owned_contribution(
            $product_id,
            $person_id,
            $index,
            $owner,
            $owned_contributions,
            $mark,
            'publish'
        );
    }
}

/**
 * @param array<int, array{product_id:int, person_id:int}> $owned_contributions
 * @param list<int> $person_ids
 */
function vg_product_people_qa_configure_product_people(
    int $product_id,
    ?string $mode,
    array $person_ids,
    string $owner,
    array &$owned_contributions,
    callable $mark,
    ?callable $delete_owned = null
): void {
    if ($mode === null) {
        delete_field(ProductPeopleFields::MODE_FIELD, $product_id);
    } else {
        update_field(ProductPeopleFields::MODE_FIELD, $mode, $product_id);
    }
    acf_flush_value_cache($product_id, ProductPeopleFields::MODE_FIELD);

    vg_product_people_qa_replace_owned_contributions($product_id, $mode === ProductPeopleFields::MODE_MANUAL ? $person_ids : [], $owner, $owned_contributions, $mark, $delete_owned);

    $stored_mode = get_post_meta($product_id, ProductPeopleFields::MODE_FIELD, true);
    vg_product_people_qa_check(
        $mode === null
            ? ! in_array($stored_mode, [ProductPeopleFields::MODE_EVENT, ProductPeopleFields::MODE_MANUAL], true)
            : $stored_mode === $mode,
        'Product People mode did not read back through ACF.'
    );

    if ($mode === ProductPeopleFields::MODE_MANUAL) {
        $resolved = (new ProductPeopleResolver())->people_for_product($product_id);
        vg_product_people_qa_check(
            vg_product_people_qa_ints($resolved) === vg_product_people_qa_ints($person_ids),
            'Manual mode must resolve canonical Product Contributions in fixture order.'
        );
    }
}

/** @param array<int, array{product_id:int, person_id:int}> $owned_contributions */
function vg_product_people_qa_delete_owned_contributions(
    array &$owned_contributions,
    string $owner,
    callable $delete_owned
): bool {
    $all_clear = true;
    foreach (array_keys($owned_contributions) as $contribution_id) {
        $contribution_id = (int) $contribution_id;
        $pair = $owned_contributions[$contribution_id];
        try {
            vg_product_people_qa_assert_owned_contribution_delete_target($contribution_id, $owner, $pair);
            $result = $delete_owned($contribution_id, ProductContributionRepository::POST_TYPE, $owner, static function () use ($contribution_id, $pair): void {
                vg_product_people_qa_check(
                    (int) get_field(ProductContributionRepository::PRODUCT_FIELD, $contribution_id) === (int) $pair['product_id']
                        && (int) get_field(ProductContributionRepository::PERSON_FIELD, $contribution_id) === (int) $pair['person_id'],
                    'Cleanup refused a changed Product Contribution endpoint.'
                );
            });
            if ($result === false) {
                $all_clear = false;
                continue;
            }
            unset($owned_contributions[$contribution_id]);
        } catch (Throwable) {
            $all_clear = false;
        }
    }

    return $all_clear;
}
