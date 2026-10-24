<?php
declare(strict_types=1);

final class ProductContributionSchemaTest
{
    public function test_contribution_post_type_is_non_public_and_has_title_support_only(): void
    {
        $post_type = $this->json('post_type_product_contribution.json');

        vgpc_test_same('post_type_product_contribution', $post_type['key'] ?? null, 'The Product Contribution post type key must be canonical.');
        vgpc_test_same('product_contribution', $post_type['post_type'] ?? null, 'The Product Contribution post type must be canonical.');
        vgpc_test_same(false, $post_type['public'] ?? null, 'Product Contribution must not be public.');
        vgpc_test_same(false, $post_type['hierarchical'] ?? null, 'Product Contribution must not be hierarchical.');
        vgpc_test_same(false, $post_type['publicly_queryable'] ?? null, 'Product Contribution must not be publicly queryable.');
        vgpc_test_same(true, $post_type['exclude_from_search'] ?? null, 'Product Contribution must be excluded from search.');
        vgpc_test_same('Product Contributions', $post_type['labels']['menu_name'] ?? null, 'Product Contribution must have its canonical admin menu name.');
        vgpc_test_same(true, $post_type['show_ui'] ?? null, 'Product Contribution must have an admin UI.');
        vgpc_test_same(true, $post_type['show_in_menu'] ?? null, 'Product Contribution must appear in its own admin menu.');
        vgpc_test_same('', $post_type['admin_menu_parent'] ?? null, 'Product Contribution must not be nested under another admin menu.');
        vgpc_test_same(false, $post_type['show_in_admin_bar'] ?? null, 'Product Contribution must not appear in the admin bar.');
        vgpc_test_same(false, $post_type['show_in_nav_menus'] ?? null, 'Product Contribution must not appear in navigation menus.');
        vgpc_test_same(true, $post_type['show_in_rest'] ?? null, 'Product Contribution must be available in the REST API for editor support.');
        vgpc_test_same('product_contribution', $post_type['rest_base'] ?? null, 'The Product Contribution REST route base must be canonical.');
        vgpc_test_same('wp/v2', $post_type['rest_namespace'] ?? null, 'The Product Contribution REST route namespace must be canonical.');
        vgpc_test_same('WP_REST_Posts_Controller', $post_type['rest_controller_class'] ?? null, 'Product Contribution must use the WordPress post REST controller.');
        vgpc_test_same(false, $post_type['has_archive'] ?? null, 'Product Contribution must not have an archive.');
        vgpc_test_same('', $post_type['has_archive_slug'] ?? null, 'Product Contribution must not define an archive slug.');
        vgpc_test_same('no_permalink', $post_type['rewrite']['permalink_rewrite'] ?? null, 'Product Contribution must not have a public permalink route.');
        vgpc_test_same('', $post_type['rewrite']['slug'] ?? null, 'Product Contribution must not define a rewrite slug.');
        vgpc_test_same(false, $post_type['rewrite']['with_front'] ?? null, 'Product Contribution rewrites must not use the site permalink front.');
        vgpc_test_same('none', $post_type['query_var'] ?? null, 'Product Contribution must use ACF\'s disabled-query-var sentinel.');
        vgpc_test_same('', $post_type['query_var_name'] ?? null, 'Product Contribution must not define a query variable name.');
        vgpc_test_same(false, $post_type['show_in_graphql'] ?? null, 'Product Contribution must not register a GraphQL route.');
        vgpc_test_same(['title'], $post_type['supports'] ?? null, 'Product Contribution must support the title only.');
    }

    public function test_product_role_taxonomy_is_non_public_and_attached_only_to_contributions(): void
    {
        $taxonomy = $this->json('taxonomy_product_role.json');

        vgpc_test_same('taxonomy_product_role', $taxonomy['key'] ?? null, 'The Product Role taxonomy key must be canonical.');
        vgpc_test_same('product_role', $taxonomy['taxonomy'] ?? null, 'The Product Role taxonomy name must be canonical.');
        vgpc_test_same(['product_contribution'], $taxonomy['object_type'] ?? null, 'Product Role must attach only to Product Contribution.');
        vgpc_test_same(false, $taxonomy['public'] ?? null, 'Product Role must not be public.');
        vgpc_test_same(false, $taxonomy['hierarchical'] ?? null, 'Product Role must be a flat taxonomy.');
        vgpc_test_same(false, $taxonomy['publicly_queryable'] ?? null, 'Product Role must not be publicly queryable.');
        vgpc_test_same('Product Roles', $taxonomy['labels']['menu_name'] ?? null, 'Product Role must have its canonical admin menu name.');
        vgpc_test_same('Product Role', $taxonomy['labels']['name_admin_bar'] ?? null, 'Product Role must have its canonical admin bar name.');
        vgpc_test_same(true, $taxonomy['show_ui'] ?? null, 'Product Role must have an admin UI.');
        vgpc_test_same(true, $taxonomy['show_in_menu'] ?? null, 'Product Role must appear in its own admin menu.');
        vgpc_test_same(true, $taxonomy['show_in_rest'] ?? null, 'Product Role must be available in the REST API for editor support.');
        vgpc_test_same('product_role', $taxonomy['rest_base'] ?? null, 'The Product Role REST route base must be canonical.');
        vgpc_test_same('wp/v2', $taxonomy['rest_namespace'] ?? null, 'The Product Role REST route namespace must be canonical.');
        vgpc_test_same('WP_REST_Terms_Controller', $taxonomy['rest_controller_class'] ?? null, 'Product Role must use the WordPress terms REST controller.');
        vgpc_test_same(false, $taxonomy['show_in_nav_menus'] ?? null, 'Product Role must not appear in navigation menus.');
        vgpc_test_same(false, $taxonomy['show_tagcloud'] ?? null, 'Product Role must not appear in tag clouds.');
        vgpc_test_same(false, $taxonomy['show_in_quick_edit'] ?? null, 'Product Role must not appear in quick edit.');
        vgpc_test_same(false, $taxonomy['show_admin_column'] ?? null, 'Product Role must not add a post list column.');
        vgpc_test_same('disabled', $taxonomy['meta_box'] ?? null, 'Product Role must use the dedicated ACF field instead of a second taxonomy meta box.');
        vgpc_test_same('no_permalink', $taxonomy['rewrite']['permalink_rewrite'] ?? null, 'Product Role must not have a public archive route.');
        vgpc_test_same('', $taxonomy['rewrite']['slug'] ?? null, 'Product Role must not define a public rewrite slug.');
        vgpc_test_same(false, $taxonomy['rewrite']['with_front'] ?? null, 'Product Role rewrites must not use the site permalink front.');
        vgpc_test_same(false, $taxonomy['rewrite']['rewrite_hierarchical'] ?? null, 'Product Role rewrites must not be hierarchical.');
        vgpc_test_same('none', $taxonomy['query_var'] ?? null, 'Product Role must not register a query var.');
        vgpc_test_same('', $taxonomy['query_var_name'] ?? null, 'Product Role must not define a query variable name.');
        vgpc_test_same(false, $taxonomy['show_in_graphql'] ?? null, 'Product Role must not register a GraphQL route.');
    }

    public function test_contribution_field_group_has_exactly_four_fields_and_targets_only_contributions(): void
    {
        $group = $this->json('group_vg_product_contribution_details.json');

        vgpc_test_same('group_vg_product_contribution_details', $group['key'] ?? null, 'The Product Contribution field group key must be canonical.');
        vgpc_test_same('Product Contribution Details', $group['title'] ?? null, 'The Product Contribution field group title must be canonical.');
        vgpc_test_same(
            [[['param' => 'post_type', 'operator' => '==', 'value' => 'product_contribution']]],
            $group['location'] ?? null,
            'The field group must be located on Product Contribution only.'
        );
        vgpc_test_same(true, $group['active'] ?? null, 'The Product Contribution field group must be active.');
        vgpc_test_same(
            [
                'product_contribution_product',
                'product_contribution_person',
                'product_contribution_roles',
                'product_contribution_display_order',
            ],
            array_column($group['fields'] ?? [], 'name'),
            'The group must contain exactly the four canonical contribution fields, with no duplicate endpoint relationship fields.'
        );
    }

    public function test_product_and_person_fields_are_optional_single_post_object_ids_without_bidi_links(): void
    {
        $product = $this->field('product_contribution_product');
        $person = $this->field('product_contribution_person');

        foreach ([['field' => $product, 'type' => 'product'], ['field' => $person, 'type' => 'person']] as $case) {
            vgpc_test_same('post_object', $case['field']['type'] ?? null, 'Product and Person endpoints must use ACF Post Object fields.');
            vgpc_test_same([$case['type']], $case['field']['post_type'] ?? null, 'Each endpoint field must target only its canonical post type.');
            vgpc_test_same('id', $case['field']['return_format'] ?? null, 'Endpoint fields must return IDs.');
            vgpc_test_same(0, $case['field']['multiple'] ?? null, 'Endpoint fields must be single-valued.');
            vgpc_test_same(0, $case['field']['required'] ?? null, 'Endpoint fields must be optional so incomplete drafts can be saved.');
            vgpc_test_same([], $case['field']['bidirectional_target'] ?? null, 'Endpoint fields must not write bidirectional relationships.');
        }
    }

    public function test_roles_field_is_optional_multiple_product_role_ids_with_native_term_sync(): void
    {
        $field = $this->field('product_contribution_roles');

        vgpc_test_same('taxonomy', $field['type'] ?? null, 'Product roles must use an ACF Taxonomy field.');
        vgpc_test_same('product_role', $field['taxonomy'] ?? null, 'The roles field must use the Product Role taxonomy.');
        vgpc_test_same(0, $field['required'] ?? null, 'The roles field must be optional so incomplete drafts can be saved.');
        vgpc_test_same(0, $field['add_term'] ?? null, 'Editors must not add unapproved roles through this field.');
        vgpc_test_same(1, $field['save_terms'] ?? null, 'ACF must save Product Role term relationships.');
        vgpc_test_same(1, $field['load_terms'] ?? null, 'ACF must load Product Role term relationships.');
        vgpc_test_same('id', $field['return_format'] ?? null, 'The roles field must return term IDs.');
        vgpc_test_same('multi_select', $field['field_type'] ?? null, 'The roles field must use the multi-select editor.');
        vgpc_test_same(1, $field['multiple'] ?? null, 'A contribution may have multiple Product Roles.');
        vgpc_test_same([], $field['bidirectional_target'] ?? null, 'The roles field must not create another relationship authority.');
    }

    public function test_display_order_is_a_nonnegative_integer_with_zero_default(): void
    {
        $field = $this->field('product_contribution_display_order');

        vgpc_test_same('number', $field['type'] ?? null, 'Display order must use an ACF Number field.');
        vgpc_test_same(0, $field['default_value'] ?? null, 'Display order must default to zero.');
        vgpc_test_same(0, $field['min'] ?? null, 'Display order must not allow negative values.');
        vgpc_test_same('1', $field['step'] ?? null, 'Display order must use integer steps.');
    }

    /** @return array<string, mixed> */
    private function json(string $filename): array
    {
        $path = dirname(__DIR__, 2) . '/acf-json/' . $filename;
        vgpc_test_true(is_file($path), 'Missing required ACF Local JSON definition: ' . $filename);

        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        vgpc_test_true(is_array($decoded), 'ACF Local JSON must decode to an object: ' . $filename);

        return $decoded;
    }

    /** @return array<string, mixed> */
    private function field(string $name): array
    {
        $group = $this->json('group_vg_product_contribution_details.json');
        foreach ($group['fields'] ?? [] as $field) {
            if (($field['name'] ?? null) === $name) {
                return $field;
            }
        }

        throw new RuntimeException('Missing Product Contribution field: ' . $name);
    }
}
