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
    }

    public function test_plugin_imports_and_registers_only_the_field_component(): void
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
