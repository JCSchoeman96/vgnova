<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Product\ProductPeopleFields;

final class ProductPeopleFieldsTest
{
    public function test_register_adds_exactly_one_acf_init_callback_for_field_registration(): void
    {
        vgpc_test_reset();
        $fields = new ProductPeopleFields();
        $fields->register();

        $callbacks = $GLOBALS['vgpc_test_actions']['acf/init'] ?? [];
        vgpc_test_same(1, count($callbacks), 'Product People registration must add exactly one acf/init callback.');
        vgpc_test_same([$fields, 'register_field_group'], $callbacks[0]['callback'], 'The acf/init callback must be register_field_group().');
    }

    public function test_register_field_group_is_a_noop_when_acf_local_field_group_api_is_unavailable(): void
    {
        // The shared test bootstrap always defines acf_add_local_field_group(), so this
        // guard is proven in a separate PHP process where ACF is not loaded.
        $class_file = dirname(__DIR__) . '/includes/Product/ProductPeopleFields.php';
        $code = "define('ABSPATH', '/'); require " . var_export($class_file, true)
            . "; (new Voelgoed\\PlatformCore\\Product\\ProductPeopleFields())->register_field_group(); echo 'NOOP_OK';";
        $output = [];
        $exit_code = 0;
        exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' 2>&1', $output, $exit_code);

        vgpc_test_same(0, $exit_code, 'register_field_group() must not fatal without ACF. Output: ' . implode("\n", $output));
        vgpc_test_same('NOOP_OK', trim(implode("\n", $output)), 'register_field_group() must return silently without ACF.');
    }

    public function test_field_group_has_canonical_key_title_and_product_only_location(): void
    {
        $group = $this->group();

        vgpc_test_same('group_vg_product_people', $group['key'], 'The Product People group key must be canonical.');
        vgpc_test_same('Product People', $group['title'], 'The Product People group title must be canonical.');
        vgpc_test_same(
            [[['param' => 'post_type', 'operator' => '==', 'value' => 'product']]],
            $group['location'],
            'The Product People group must be located on post_type == product only.'
        );
        vgpc_test_same(true, $group['active'], 'The Product People group must be active.');
    }

    public function test_group_defines_only_the_mode_field_and_no_legacy_product_fields(): void
    {
        $names = array_column($this->group()['fields'], 'name');

        vgpc_test_same(['product_people_mode'], $names, 'The group must define only the canonical Product People mode field.');
        vgpc_test_true(! in_array('product_people_override', $names, true), 'The group must not register product_people_override.');
        vgpc_test_true(! in_array('produk_bron_event', $names, true), 'The group must not define produk_bron_event.');
        vgpc_test_true(! in_array('produk_medewerkers', $names, true), 'The group must not define produk_medewerkers.');
    }

    public function test_override_field_constants_are_retired(): void
    {
        $constants = (new ReflectionClass(ProductPeopleFields::class))->getConstants();

        vgpc_test_true(! array_key_exists('OVERRIDE_FIELD_KEY', $constants), 'OVERRIDE_FIELD_KEY must be removed from ProductPeopleFields.');
        vgpc_test_true(! array_key_exists('OVERRIDE_FIELD', $constants), 'OVERRIDE_FIELD must be removed from ProductPeopleFields.');
    }

    public function test_mode_field_is_an_optional_single_select_with_only_event_and_manual(): void
    {
        $field = $this->field('product_people_mode');

        vgpc_test_same('field_vg_product_people_mode', $field['key'], 'The mode field key must be canonical.');
        vgpc_test_same('select', $field['type'], 'The mode field must be an ACF Select.');
        vgpc_test_same(['event' => 'Event-derived', 'manual' => 'Manual'], $field['choices'], 'The mode field must expose exactly event and manual.');
        vgpc_test_same('', $field['default_value'], 'The mode field must have no default value.');
        vgpc_test_same(1, $field['allow_null'], 'The mode field must allow a blank value.');
        vgpc_test_same(0, $field['required'], 'The mode field must be optional.');
        vgpc_test_same(0, $field['multiple'], 'The mode field must be single-valued.');
        vgpc_test_same('value', $field['return_format'], 'The mode field must return the stored value.');
        vgpc_test_true(
            str_contains($field['instructions'], 'Leave blank to keep this Product unconfigured.'),
            'The mode field instructions must state that blank keeps the Product unconfigured.'
        );
    }

    public function test_mode_instructions_direct_editors_to_product_contributions(): void
    {
        $field = $this->field('product_people_mode');

        vgpc_test_true(
            str_contains($field['instructions'], 'Product Contributions'),
            'Manual-mode guidance must direct editors to Product Contributions.'
        );
    }

    public function test_registration_does_not_create_or_mutate_product_meta(): void
    {
        vgpc_test_reset();
        $GLOBALS['vgpc_test_posts'][5001] = (object) ['ID' => 5001, 'post_type' => 'product', 'post_status' => 'publish'];
        $GLOBALS['vgpc_test_meta'][5001] = ['produk_bron_event' => '763', '_produk_bron_event' => 'field_produk_bron_event'];
        $meta_before = $GLOBALS['vgpc_test_meta'];

        (new ProductPeopleFields())->register_field_group();

        vgpc_test_same($meta_before, $GLOBALS['vgpc_test_meta'], 'Registering the field group must not write any post meta.');
        vgpc_test_same([], $GLOBALS['vgpc_test_update_field_calls'], 'Registering the field group must not call update_field().');
        vgpc_test_same([], $GLOBALS['vgpc_test_update_post_calls'], 'Registering the field group must not call wp_update_post().');
    }

    /** @return array<string, mixed> */
    private function group(): array
    {
        vgpc_test_reset();
        (new ProductPeopleFields())->register_field_group();
        $group = $GLOBALS['vgpc_test_field_groups'][ProductPeopleFields::GROUP_KEY] ?? null;
        vgpc_test_true(is_array($group), 'The Product People field group must be registered through acf_add_local_field_group().');

        return $group;
    }

    /** @return array<string, mixed> */
    private function field(string $name): array
    {
        foreach ($this->group()['fields'] as $field) {
            if (($field['name'] ?? null) === $name) {
                return $field;
            }
        }

        throw new RuntimeException('Missing Product People field: ' . $name);
    }
}
