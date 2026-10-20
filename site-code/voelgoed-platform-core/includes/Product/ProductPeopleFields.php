<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Product;

defined('ABSPATH') || exit;

/**
 * Registers the canonical Product People ACF fields in PHP.
 *
 * Owns only product_people_mode and product_people_override. It never defines
 * produk_bron_event or produk_medewerkers and never writes Product meta.
 */
final class ProductPeopleFields
{
    public const GROUP_KEY = 'group_vg_product_people';
    public const MODE_FIELD_KEY = 'field_vg_product_people_mode';
    public const OVERRIDE_FIELD_KEY = 'field_vg_product_people_override';
    public const MODE_FIELD = 'product_people_mode';
    public const OVERRIDE_FIELD = 'product_people_override';
    public const MODE_EVENT = 'event';
    public const MODE_MANUAL = 'manual';

    public function register(): void
    {
        add_action('acf/init', [$this, 'register_field_group']);
    }

    public function register_field_group(): void
    {
        if (! function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key' => self::GROUP_KEY,
            'title' => 'Product People',
            'fields' => [
                [
                    'key' => self::MODE_FIELD_KEY,
                    'label' => 'Product: People mode',
                    'name' => self::MODE_FIELD,
                    'type' => 'select',
                    'instructions' => 'Choose Event-derived or Manual to configure canonical Product People. Leave blank to keep this Product unconfigured.',
                    'required' => 0,
                    'choices' => [
                        self::MODE_EVENT => 'Event-derived',
                        self::MODE_MANUAL => 'Manual',
                    ],
                    'default_value' => '',
                    'allow_null' => 1,
                    'multiple' => 0,
                    'ui' => 0,
                    'return_format' => 'value',
                ],
                [
                    'key' => self::OVERRIDE_FIELD_KEY,
                    'label' => 'Product: Manual People',
                    'name' => self::OVERRIDE_FIELD,
                    'type' => 'relationship',
                    'instructions' => 'Used only when People mode is Manual. Selected People are shown in the order chosen here.',
                    'required' => 0,
                    'post_type' => ['person'],
                    'filters' => ['search'],
                    'elements' => [],
                    'min' => 0,
                    'max' => 0,
                    'return_format' => 'id',
                    'bidirectional' => 0,
                    'conditional_logic' => [[
                        [
                            'field' => self::MODE_FIELD_KEY,
                            'operator' => '==',
                            'value' => self::MODE_MANUAL,
                        ],
                    ]],
                ],
            ],
            'location' => [[[
                'param' => 'post_type',
                'operator' => '==',
                'value' => 'product',
            ]]],
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'active' => true,
            'show_in_rest' => 0,
        ]);
    }
}
