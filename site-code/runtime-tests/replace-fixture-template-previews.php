<?php
/**
 * Guarded one-time maintenance for the two fixture-backed Bricks previews.
 *
 * Execute through the Novamira `novamira/execute-php` ability on the
 * `vgnova` profile. The runner supplies `$task4f_action = 'apply'` and this
 * file's contents (without this opening tag) as the PHP code payload.
 */

if (! defined('ABSPATH')) {
    throw new RuntimeException('This maintenance script must run inside WordPress.');
}

function vg_task4f_preview_identity(int $template_id): array
{
    $post = get_post($template_id);
    $settings = \Bricks\Helpers::get_template_settings($template_id);
    $elements = get_post_meta($template_id, '_bricks_page_content_2', true);

    return [
        'id' => $template_id,
        'title' => $post ? $post->post_title : null,
        'status' => $post ? $post->post_status : null,
        'post_type' => $post ? $post->post_type : null,
        'template_type' => get_post_meta($template_id, '_bricks_template_type', true),
        'settings_hash' => hash('sha256', serialize($settings)),
        'preview_id' => isset($settings['templatePreviewPostId']) ? (string) $settings['templatePreviewPostId'] : null,
        'conditions' => $settings['templateConditions'] ?? null,
        'elements_hash' => hash('sha256', serialize($elements)),
        'other_meta_hash' => (static function () use ($template_id): string {
            $meta = get_post_meta($template_id);
            unset($meta['_bricks_template_settings']);
            return hash('sha256', serialize($meta));
        })(),
        'all_meta_hash' => hash('sha256', serialize(get_post_meta($template_id))),
    ];
}

function vg_task4f_preview_matches(int $template_id, array $expected): bool
{
    $actual = vg_task4f_preview_identity($template_id);

    foreach ($expected as $key => $value) {
        if (($actual[$key] ?? null) !== $value) {
            return false;
        }
    }

    return true;
}

$task4f_result = [
    'ok' => false,
    'site' => home_url('/'),
    'templates' => [],
];

try {
    if (home_url('/') !== 'http://localhost:10039/') {
        throw new RuntimeException('Refusing to run outside the verified local vgnova site at http://localhost:10039/.');
    }

    if (! class_exists('Bricks\\Helpers')) {
        throw new RuntimeException('Bricks Helpers is unavailable; no template settings were changed.');
    }

    if (($task4f_action ?? null) !== 'apply') {
        throw new RuntimeException('Set $task4f_action to apply to run this guarded maintenance.');
    }

    $templates = [
        372 => [
            'title' => 'Posts',
            'status' => 'publish',
            'post_type' => 'bricks_template',
            'template_type' => 'content',
            'settings_hash' => '329883311b590ed02ee5f50358d3481df72624814a6aa73051e6f3094192c375',
            'preview_id' => '521',
            'conditions' => [['main' => 'postType', 'postType' => ['post']]],
            'elements_hash' => 'ca24a5fe03788c6dae4d0f0efaf6e76ccffcf45ac9c13aedc2f61cc89c8c851f',
            'other_meta_hash' => 'adc44f9c335658a2b5b73eebe9b1d1943d7da56123767feeffe6edf36a781330',
            'after_settings' => [
                'templatePreviewType' => 'single',
                'templatePreviewPostType' => 'post',
                'templateConditions' => [['main' => 'postType', 'postType' => ['post']]],
            ],
        ],
        690 => [
            'title' => 'Article Card',
            'status' => 'publish',
            'post_type' => 'bricks_template',
            'template_type' => 'section',
            'settings_hash' => '5bec4721e5cad6051ebea93e99f941751a1bfee281b6d66b527f01bce0167341',
            'preview_id' => '523',
            'conditions' => null,
            'elements_hash' => '8599661518f167071a1a73a4b7abe8574761348562ce8d0e8e1ef7ce4b56b9b0',
            'other_meta_hash' => '32f6c14582a951b6a4d2c26898f70ab6e6a434f6a066c99e9582fa7a8155882c',
            'after_settings' => ['templatePreviewType' => 'single'],
        ],
    ];

    foreach ($templates as $template_id => $expected) {
        $identity = vg_task4f_preview_identity((int) $template_id);
        $base_expected = $expected;
        unset($base_expected['after_settings']);

        $after_state = [
            'id' => (int) $template_id,
            'title' => $expected['title'],
            'status' => $expected['status'],
            'post_type' => $expected['post_type'],
            'template_type' => $expected['template_type'],
            'settings_hash' => hash('sha256', serialize($expected['after_settings'])),
            'preview_id' => null,
            'conditions' => $expected['after_settings']['templateConditions'] ?? null,
            'elements_hash' => $expected['elements_hash'],
            'other_meta_hash' => $expected['other_meta_hash'],
        ];

        if (vg_task4f_preview_matches((int) $template_id, $after_state)) {
            $task4f_result['templates'][$template_id] = [
                'status' => 'already_applied',
                'before' => $identity,
                'after' => $identity,
            ];
            continue;
        }

        if (! vg_task4f_preview_matches((int) $template_id, $base_expected)) {
            $task4f_result['templates'][$template_id] = [
                'status' => 'refused_unexpected_state',
                'before' => $identity,
            ];
            continue;
        }

        vg_runtime_mutation_assert_session('vgnova-local-10039');

        $settings = \Bricks\Helpers::get_template_settings((int) $template_id);
        unset($settings['templatePreviewPostId']);
        \Bricks\Helpers::set_template_settings((int) $template_id, $settings);

        $after = vg_task4f_preview_identity((int) $template_id);
        $after_expected = [
            'id' => (int) $template_id,
            'title' => $expected['title'],
            'status' => $expected['status'],
            'post_type' => $expected['post_type'],
            'template_type' => $expected['template_type'],
            'settings_hash' => hash('sha256', serialize($expected['after_settings'])),
            'preview_id' => null,
            'conditions' => $expected['after_settings']['templateConditions'] ?? null,
            'elements_hash' => $expected['elements_hash'],
            'other_meta_hash' => $expected['other_meta_hash'],
        ];

        if (! vg_task4f_preview_matches((int) $template_id, $after_expected)) {
            $task4f_result['templates'][$template_id] = [
                'status' => 'verification_failed',
                'before' => $identity,
                'after' => $after,
            ];
            continue;
        }

        $task4f_result['templates'][$template_id] = [
            'status' => 'applied',
            'before' => $identity,
            'after' => $after,
        ];
    }

    $task4f_result['ok'] = true;
    foreach ($task4f_result['templates'] as $template_result) {
        if (! in_array($template_result['status'], ['applied', 'already_applied'], true)) {
            $task4f_result['ok'] = false;
        }
    }
} catch (Throwable $error) {
    $task4f_result['error'] = $error->getMessage();
}

echo wp_json_encode($task4f_result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
