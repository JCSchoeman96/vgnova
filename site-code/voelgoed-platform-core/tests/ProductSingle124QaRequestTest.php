<?php
declare(strict_types=1);

$request_helper = dirname(__DIR__, 2) . '/runtime-tests/product-single-124-qa-request.php';
if (is_file($request_helper)) {
    require_once $request_helper;
}

final class ProductSingle124QaRequestTest
{
    public function test_authenticated_same_origin_frontend_request_helper_exists(): void
    {
        vgpc_test_true(
            function_exists('vg_product_single_124_qa_frontend_request_args'),
            'Task 7B frontend QA must use the guarded authenticated same-origin request helper.'
        );
    }

    public function test_privileged_basic_request_is_same_origin_and_never_follows_redirects(): void
    {
        $args = $this->request_args(
            'http://localhost:10039/mense/qa-person/',
            'http://localhost:10039/',
            'Basic dGVzdDp0ZXN0',
            true
        );

        vgpc_test_same('Basic dGVzdDp0ZXN0', $args['headers']['Authorization'] ?? null, 'The existing Basic credential must be passed only to the verified same-origin request.');
        vgpc_test_same(0, $args['redirection'] ?? null, 'An authenticated QA request must not forward credentials across redirects.');
        vgpc_test_same(25, $args['timeout'] ?? null, 'The frontend request must retain its bounded timeout.');
        vgpc_test_same(true, $args['sslverify'] ?? null, 'Authenticated QA must verify TLS when the configured origin uses HTTPS.');
    }

    public function test_coming_soon_bypass_token_is_added_only_to_product_urls(): void
    {
        $token = str_repeat('a1', 32);
        $product_url = vg_product_single_124_qa_frontend_bypass_url(
            'http://localhost:10039/winkel/qa-product/?preview=1#details',
            'http://localhost:10039/',
            $token
        );
        vgpc_test_same(
            'http://localhost:10039/winkel/qa-product/?preview=1&vg_task_7b_qa_bypass=' . $token . '#details',
            $product_url,
            'The scoped Coming Soon token must preserve the Product URL query and fragment.'
        );

        $person_url = 'http://localhost:10039/mense/qa-person/';
        vgpc_test_same(
            $person_url,
            vg_product_single_124_qa_frontend_bypass_url($person_url, 'http://localhost:10039/', $token),
            'The Coming Soon bypass token must not be added to canonical Person profile requests.'
        );
    }

    public function test_coming_soon_bypass_rejects_bad_tokens_and_cross_origin_urls(): void
    {
        $this->assert_rejected(static function (): void {
            vg_product_single_124_qa_frontend_bypass_url(
                'http://localhost:10039/winkel/qa-product/',
                'http://localhost:10039/',
                'too-short'
            );
        });
        $this->assert_rejected(static function (): void {
            vg_product_single_124_qa_frontend_bypass_url(
                'http://example.test/winkel/qa-product/',
                'http://localhost:10039/',
                str_repeat('b2', 32)
            );
        });
    }

    public function test_frontend_dom_checks_ignore_css_and_extract_source_event_card(): void
    {
        $html = '<!doctype html><html><head><style>.vg-product-people-section{display:none}</style></head><body>'
            . '<section class="vg-product-source-event"><div data-vg-card="event">'
            . '<h3>QA Event</h3><a>Bekyk gebeurtenis</a></div></section></body></html>';

        vgpc_test_same(false, vg_product_single_124_qa_html_has_class($html, 'vg-product-people-section'), 'A CSS selector must not count as a rendered empty Product People section.');
        vgpc_test_same(true, vg_product_single_124_qa_html_has_class($html, 'vg-product-source-event'), 'Rendered section classes must be read from HTML elements.');
        vgpc_test_same(['QA EventBekyk gebeurtenis'], vg_product_single_124_qa_event_card_texts($html), 'The Event Card assertion must read text from its rendered card node.');
    }

    public function test_frontend_dom_extracts_canonical_product_person_card_fields(): void
    {
        $html = '<div class="vg-medewerker-card"><img src="https://example.test/person.jpg" alt="QA Person">'
            . '<h3 class="vg-medewerker-card__name">QA Person</h3>'
            . '<p class="vg-medewerker-card__title">Speaker</p>'
            . '<div class="vg-medewerker-card__bio"><p>Canonical excerpt</p></div>'
            . '<a class="vg-medewerker-card__link" href="https://example.test/mense/qa-person/">Bekyk profiel</a></div>';

        vgpc_test_same([[
            'title' => 'QA Person',
            'professional_title' => 'Speaker',
            'excerpt' => 'Canonical excerpt',
            'profile_url' => 'https://example.test/mense/qa-person/',
            'image_urls' => ['https://example.test/person.jpg'],
            'cta' => 'Bekyk profiel',
        ]], vg_product_single_124_qa_person_card_values($html), 'Product Person Card values must be extracted from each card element, not matched anywhere in the page.');
    }

    public function test_unprivileged_request_is_rejected(): void
    {
        $this->assert_rejected(static function (): void {
            vg_product_single_124_qa_frontend_request_args(
                'http://localhost:10039/winkel/qa-product/',
                'http://localhost:10039/',
                'Basic dGVzdDp0ZXN0',
                false
            );
        });
    }

    public function test_cross_origin_request_is_rejected(): void
    {
        $this->assert_rejected(static function (): void {
            vg_product_single_124_qa_frontend_request_args(
                'http://example.test/winkel/qa-product/',
                'http://localhost:10039/',
                'Basic dGVzdDp0ZXN0',
                true
            );
        });
    }

    public function test_non_basic_authorization_is_rejected(): void
    {
        $this->assert_rejected(static function (): void {
            vg_product_single_124_qa_frontend_request_args(
                'http://localhost:10039/winkel/qa-product/',
                'http://localhost:10039/',
                'Bearer example-token',
                true
            );
        });
    }

    public function test_sandbox_listing_detects_new_top_level_php_before_frontend_fetches(): void
    {
        vgpc_test_true(
            function_exists('vg_product_single_124_qa_sandbox_entries')
                && function_exists('vg_product_single_124_qa_assert_sandbox_unchanged'),
            'Task 7B frontend QA must snapshot and recheck the sandbox directory before requests.'
        );

        $directory = sys_get_temp_dir() . '/vg-task-7b-sandbox-' . bin2hex(random_bytes(8));
        vgpc_test_true(mkdir($directory), 'The isolated sandbox test directory could not be created.');
        try {
            file_put_contents($directory . '/known.txt', '');
            $baseline = vg_product_single_124_qa_sandbox_entries($directory);
            vgpc_test_same(['known.txt'], $baseline, 'The sandbox listing must include its top-level entries.');

            file_put_contents($directory . '/unexpected.php', '');
            try {
                vg_product_single_124_qa_assert_sandbox_unchanged($directory, $baseline);
            } catch (RuntimeException $error) {
                vgpc_test_true(str_contains($error->getMessage(), 'unexpected.php'), 'The sandbox failure must identify the new PHP file.');
                return;
            }

            throw new RuntimeException('A new top-level PHP file must abort the next frontend request.');
        } finally {
            @unlink($directory . '/unexpected.php');
            @unlink($directory . '/known.txt');
            @rmdir($directory);
        }
    }

    public function test_runtime_qa_subprocesses_use_a_verified_php_cli_binary(): void
    {
        vgpc_test_true(
            function_exists('vg_product_single_124_qa_php_cli_binary'),
            'Task 7B isolated subprocesses must use a verified PHP CLI executable, not PHP-FPM.'
        );
        if (! function_exists('vg_product_single_124_qa_php_cli_binary')) {
            return;
        }

        vgpc_test_same(
            PHP_BINARY,
            vg_product_single_124_qa_php_cli_binary(PHP_BINARY),
            'A verified PHP CLI executable should be selected unchanged.'
        );

        try {
            vg_product_single_124_qa_php_cli_binary('/path/that/does/not/exist/php');
        } catch (RuntimeException) {
            return;
        }

        throw new RuntimeException('A nonexistent PHP executable must be rejected.');
    }

    public function test_qa_snapshot_records_and_compares_public_coming_soon_options(): void
    {
        $qa = (string) file_get_contents(dirname(__DIR__, 2) . '/runtime-tests/product-single-124-qa.php');
        vgpc_test_true(
            str_contains($qa, "'woocommerce_coming_soon' => get_option('woocommerce_coming_soon'")
                && str_contains($qa, "'woocommerce_store_pages_only' => get_option('woocommerce_store_pages_only'")
                && str_contains($qa, "\$before['woocommerce_coming_soon'] === \$after['woocommerce_coming_soon']")
                && str_contains($qa, "\$before['woocommerce_store_pages_only'] === \$after['woocommerce_store_pages_only']"),
            'Task 7B QA must record and verify the public WooCommerce Coming Soon configuration is unchanged.'
        );
    }

    public function test_authenticated_identity_requires_the_expected_privileged_user(): void
    {
        $identity = vg_product_single_124_qa_assert_identity_payload(1, 200, [
            'id' => 1,
            'capabilities' => ['manage_woocommerce' => true],
        ]);

        vgpc_test_same(1, $identity['user_id'] ?? null, 'The forwarded Basic credential must resolve to the expected QA user.');
        vgpc_test_same(true, $identity['manage_woocommerce'] ?? null, 'The forwarded Basic credential must resolve to the privileged WooCommerce capability.');
    }

    public function test_authenticated_identity_rejects_a_different_user(): void
    {
        $this->assert_identity_rejected(1, 200, [
            'id' => 2,
            'capabilities' => ['manage_woocommerce' => true],
        ]);
    }

    public function test_authenticated_identity_rejects_a_user_without_manage_woocommerce(): void
    {
        $this->assert_identity_rejected(1, 200, [
            'id' => 1,
            'capabilities' => ['manage_woocommerce' => false],
        ]);
    }

    public function test_authenticated_identity_rejects_a_non_success_response(): void
    {
        $this->assert_identity_rejected(1, 401, []);
    }

    private function request_args(string $url, string $home_url, string $authorization, bool $privileged): array
    {
        vgpc_test_true(
            function_exists('vg_product_single_124_qa_frontend_request_args'),
            'The guarded frontend request helper must be loaded before it is used.'
        );

        return vg_product_single_124_qa_frontend_request_args($url, $home_url, $authorization, $privileged);
    }

    private function assert_rejected(callable $request): void
    {
        vgpc_test_true(
            function_exists('vg_product_single_124_qa_frontend_request_args'),
            'The guarded frontend request helper must be loaded before it is used.'
        );

        try {
            $request();
        } catch (RuntimeException) {
            return;
        }

        throw new RuntimeException('Unsafe authenticated frontend request should have been rejected.');
    }

    private function assert_identity_rejected(int $expected_user_id, int $status, array $payload): void
    {
        vgpc_test_true(
            function_exists('vg_product_single_124_qa_assert_identity_payload'),
            'The forwarded Basic credential must be independently resolved to the expected privileged WordPress user.'
        );

        try {
            vg_product_single_124_qa_assert_identity_payload($expected_user_id, $status, $payload);
        } catch (RuntimeException) {
            return;
        }

        throw new RuntimeException('An unexpected or unprivileged authenticated identity must be rejected.');
    }
}
