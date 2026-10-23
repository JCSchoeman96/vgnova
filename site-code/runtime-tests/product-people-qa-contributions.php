<?php
declare(strict_types=1);

/**
 * Shared runtime QA helpers for canonical manual Product People via Product Contributions.
 */

use Voelgoed\PlatformCore\Product\ProductContributionRepository;
use Voelgoed\PlatformCore\Product\ProductPeopleFields;
use Voelgoed\PlatformCore\Product\ProductPeopleResolver;
use Voelgoed\PlatformCore\Product\ProductRoleVocabulary;

/** Resolve one approved Product Role term for QA contribution fixtures. */
function vg_product_people_qa_default_product_role_term_id(): int
{
    static $cached = null;
    if (is_int($cached)) {
        return $cached;
    }

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
        if (is_object($term) && ! is_wp_error($term) && ProductRoleVocabulary::is_approved_term_id((int) $term->term_id)) {
            $cached = (int) $term->term_id;

            return $cached;
        }
    }

    $slug = 'aanbieder';
    $label = ProductRoleVocabulary::approved_terms()[$slug];
    $created = wp_insert_term($label, ProductRoleVocabulary::TAXONOMY, ['slug' => $slug]);
    vg_product_people_qa_check(! is_wp_error($created), 'Could not seed the default QA Product Role term.');
    $term_id = (int) ($created['term_id'] ?? 0);
    vg_product_people_qa_check(ProductRoleVocabulary::is_approved_term_id($term_id), 'The default QA Product Role term is not approved.');

    $cached = $term_id;

    return $cached;
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
        vg_product_people_qa_check(get_post_meta($contribution_id, VG_PRODUCT_PEOPLE_QA_OWNER_META, true) === $owner_marker, 'Cleanup refused an unowned Product Contribution.');
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
        $delete($contribution_id, ProductContributionRepository::POST_TYPE, $owner, null);
        unset($owned_contributions[$contribution_id]);
    }

    $role_term_id = vg_product_people_qa_default_product_role_term_id();
    foreach (array_values($person_ids) as $index => $person_id) {
        $person_id = (int) $person_id;
        if ($person_id <= 0) {
            continue;
        }

        $contribution_id = wp_insert_post([
            'post_type' => ProductContributionRepository::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $owner . ' Contribution ' . $product_id . '-' . $person_id,
        ], true);
        vg_product_people_qa_check(! is_wp_error($contribution_id) && (int) $contribution_id > 0, 'Could not create a QA Product Contribution.');
        $contribution_id = (int) $contribution_id;
        $mark($contribution_id, ProductContributionRepository::POST_TYPE, $owner);
        update_field(ProductContributionRepository::PRODUCT_FIELD, $product_id, $contribution_id);
        update_field(ProductContributionRepository::PERSON_FIELD, $person_id, $contribution_id);
        update_field(ProductContributionRepository::DISPLAY_ORDER_FIELD, $index, $contribution_id);
        update_field('product_contribution_roles', [$role_term_id], $contribution_id);
        wp_set_object_terms($contribution_id, [$role_term_id], ProductContributionRepository::ROLE_TAXONOMY, false);
        $owned_contributions[$contribution_id] = ['product_id' => $product_id, 'person_id' => $person_id];
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
        $endpoint_check = static function () use ($contribution_id, $pair): void {
            vg_product_people_qa_check(
                (int) get_field(ProductContributionRepository::PRODUCT_FIELD, $contribution_id) === (int) $pair['product_id']
                    && (int) get_field(ProductContributionRepository::PERSON_FIELD, $contribution_id) === (int) $pair['person_id'],
                'Cleanup refused a changed Product Contribution endpoint.'
            );
        };
        try {
            $result = $delete_owned($contribution_id, ProductContributionRepository::POST_TYPE, $owner, $endpoint_check);
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
