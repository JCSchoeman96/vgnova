<?php
declare(strict_types=1);

final class ProductPeopleIntegrationTest
{
    public function test_bootstrap_loads_both_product_classes(): void
    {
        $added = $this->added_lines();

        vgpc_test_true(
            in_array("'includes/Product/ProductPeopleFields.php',", $added, true),
            'integration.patch must load includes/Product/ProductPeopleFields.php in the bootstrap.'
        );
        vgpc_test_true(
            in_array("'includes/Product/ProductPeopleResolver.php',", $added, true),
            'integration.patch must load includes/Product/ProductPeopleResolver.php in the bootstrap.'
        );
        vgpc_test_true(
            in_array("'includes/Product/ProductPeopleBricks.php',", $added, true),
            'integration.patch must load includes/Product/ProductPeopleBricks.php in the bootstrap.'
        );
        vgpc_test_true(
            in_array("'includes/Product/ProductSourceEventBricks.php',", $added, true),
            'integration.patch must load includes/Product/ProductSourceEventBricks.php in the bootstrap.'
        );
    }

    public function test_plugin_imports_and_registers_product_bricks_components_without_registering_resolver(): void
    {
        $added = $this->added_lines();

        vgpc_test_true(
            in_array('use Voelgoed\PlatformCore\Product\ProductPeopleFields;', $added, true),
            'integration.patch must import ProductPeopleFields into includes/Plugin.php.'
        );
        vgpc_test_true(
            in_array("\$this->register_component('product_people_fields', ProductPeopleFields::class);", $added, true),
            'integration.patch must register the product_people_fields lifecycle component.'
        );
        vgpc_test_true(
            in_array('use Voelgoed\PlatformCore\Product\ProductPeopleBricks;', $added, true),
            'integration.patch must import ProductPeopleBricks into includes/Plugin.php.'
        );
        vgpc_test_true(
            in_array('use Voelgoed\PlatformCore\Product\ProductSourceEventBricks;', $added, true),
            'integration.patch must import ProductSourceEventBricks into includes/Plugin.php.'
        );
        vgpc_test_true(
            in_array("\$this->register_component('product_people_bricks', ProductPeopleBricks::class);", $added, true),
            'integration.patch must register the product_people_bricks lifecycle component.'
        );
        vgpc_test_true(
            in_array("\$this->register_component('product_source_event_bricks', ProductSourceEventBricks::class);", $added, true),
            'integration.patch must register the product_source_event_bricks lifecycle component.'
        );
    }

    public function test_resolver_is_not_registered_as_a_lifecycle_component(): void
    {
        foreach ($this->added_lines() as $line) {
            vgpc_test_true(
                ! str_contains($line, 'ProductPeopleResolver::class'),
                'ProductPeopleResolver must not be registered as a lifecycle component: ' . $line
            );
            vgpc_test_true(
                ! str_contains($line, "register_component('product_people_resolver'"),
                'ProductPeopleResolver must not have a lifecycle registration key: ' . $line
            );
        }
    }

    public function test_patch_is_additive_and_preserves_existing_integration_lines(): void
    {
        $removed = [];
        foreach (explode("\n", $this->patch()) as $line) {
            if (str_starts_with($line, '-') && ! str_starts_with($line, '---')) {
                $removed[] = $line;
            }
        }
        vgpc_test_same([], $removed, 'integration.patch must not remove any existing bootstrap line.');

        $added = $this->added_lines();
        vgpc_test_true(
            in_array("'includes/Event/EventParticipationBricks.php',", $added, true),
            'The existing Event Participation bootstrap line must remain in the patch.'
        );
        vgpc_test_true(
            in_array("\$this->register_component('event_participation_bricks', EventParticipationBricks::class);", $added, true),
            'The existing Event Participation registration line must remain in the patch.'
        );
    }

    public function test_patch_hunk_headers_match_their_added_line_counts(): void
    {
        $expected = null;
        $actual = 0;
        $hunks = 0;

        foreach (explode("\n", $this->patch()) as $line) {
            if (str_starts_with($line, '--- ') || str_starts_with($line, '+++ ')) {
                $this->assert_hunk_count($expected, $actual);
                $expected = null;
                continue;
            }
            if (preg_match('/^@@ -\d+,0 \+\d+,(\d+) @@$/', $line, $match) === 1) {
                $this->assert_hunk_count($expected, $actual);
                $expected = (int) $match[1];
                $actual = 0;
                ++$hunks;
                continue;
            }
            if (str_starts_with($line, '@@')) {
                throw new RuntimeException('Malformed integration.patch hunk header: ' . $line);
            }
            if ($expected !== null && str_starts_with($line, '+')) {
                ++$actual;
            }
        }
        $this->assert_hunk_count($expected, $actual);

        vgpc_test_true($hunks >= 2, 'integration.patch must contain the bootstrap and Plugin hunks.');
    }

    public function test_task_7b_token_scan_reads_term_descriptions_from_term_taxonomy(): void
    {
        $qa = (string) file_get_contents(dirname(__DIR__, 2) . '/runtime-tests/product-single-124-qa.php');

        vgpc_test_true(
            str_contains($qa, "LEFT JOIN {\$wpdb->term_taxonomy}")
                && str_contains($qa, 'taxonomy.description LIKE %s'),
            'The Task 7B ownership-token scan must join wp_term_taxonomy before searching term descriptions.'
        );
    }

    public function test_task_7b_protected_record_hashes_are_full_sha256_values(): void
    {
        $qa = (string) file_get_contents(dirname(__DIR__, 2) . '/runtime-tests/product-single-124-qa.php');
        $matched = preg_match('/const VG_PRODUCT_SINGLE_124_QA_EXPECTED_RECORD_HASHES = \[(.*?)\n\];/s', $qa, $record_block);

        vgpc_test_true($matched === 1, 'The Task 7B protected record hash map must exist.');

        preg_match_all("/\\d+ => \\['([a-f0-9]+)', '([a-f0-9]+)'\\]/", $record_block[1], $hashes);
        vgpc_test_true(count($hashes[0]) === 11, 'The Task 7B protected record map must contain all 11 audited records.');

        foreach ([...$hashes[1], ...$hashes[2]] as $hash) {
            vgpc_test_true(strlen($hash) === 64, 'Every Task 7B protected record digest must contain 64 hexadecimal characters.');
        }
    }

    public function test_task_7b_component_hashes_normalize_php_float_serialization_precision(): void
    {
        $qa = (string) file_get_contents(dirname(__DIR__, 2) . '/runtime-tests/product-single-124-qa.php');

        vgpc_test_true(
            str_contains($qa, 'function vg_product_single_124_qa_stable_hash(')
                && str_contains($qa, "ini_set('serialize_precision', '-1')")
                && str_contains($qa, 'vg_product_single_124_qa_stable_hash($component)'),
            'Task 7B component comparisons must hash values with normalized float serialization precision.'
        );
    }

    public function test_task_7b_card_evidence_uses_component_registry_id(): void
    {
        $qa = (string) file_get_contents(dirname(__DIR__, 2) . '/runtime-tests/product-single-124-qa.php');

        vgpc_test_true(
            str_contains($qa, "static fn (string \$id, array \$row): array => ['id' => \$id, 'sha256' => \$row['sha256']]")
                && str_contains($qa, "array_keys(\$before['product_person_cards'])"),
            'Task 7B card evidence must report the Product Person Card registry ID.'
        );
    }

    public function test_task_7b_featured_image_fixtures_are_valid_image_attachments(): void
    {
        $qa = (string) file_get_contents(dirname(__DIR__, 2) . '/runtime-tests/product-single-124-qa.php');

        vgpc_test_true(
            str_contains($qa, "'post_mime_type' => 'image'")
                && str_contains($qa, 'wp_attachment_is_image($attachment_id)'),
            'Task 7B Person fixtures must select valid image attachments, not SVG or other attachment types.'
        );
    }

    private function assert_hunk_count(?int $expected, int $actual): void
    {
        if ($expected !== null && $expected !== $actual) {
            throw new RuntimeException(sprintf('integration.patch hunk declares %d added lines but contains %d.', $expected, $actual));
        }
    }

    private function patch(): string
    {
        $file = dirname(__DIR__) . '/integration.patch';
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException('Unable to read integration.patch.');
        }

        return $contents;
    }

    /** @return list<string> Added lines with the leading diff marker and surrounding whitespace removed. */
    private function added_lines(): array
    {
        $added = [];
        foreach (explode("\n", $this->patch()) as $line) {
            if (str_starts_with($line, '+') && ! str_starts_with($line, '+++')) {
                $added[] = trim(substr($line, 1));
            }
        }

        return $added;
    }
}
