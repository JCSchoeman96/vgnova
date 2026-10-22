<?php
declare(strict_types=1);

/**
 * Build bounded HTTP arguments for authenticated Task 7B frontend checks.
 *
 * The credential is accepted only for an exact same-origin request and redirects
 * are disabled so it cannot be forwarded to another host.
 *
 * @return array<string, mixed>
 */
function vg_product_single_124_qa_frontend_request_args(
    string $url,
    string $home_url,
    string $authorization_header,
    bool $is_privileged
): array {
    if (! $is_privileged) {
        throw new RuntimeException('Authenticated frontend QA requires manage_woocommerce capability.');
    }

    if (preg_match('/\ABasic [A-Za-z0-9+\/]+={0,2}\z/i', trim($authorization_header)) !== 1) {
        throw new RuntimeException('Authenticated frontend QA requires the existing Basic authorization header.');
    }

    $origin = static function (string $candidate): array {
        $parts = parse_url($candidate);
        if (
            ! is_array($parts)
            || ! isset($parts['scheme'], $parts['host'])
            || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new RuntimeException('Authenticated frontend QA requires an absolute HTTP(S) URL without user info.');
        }

        $scheme = strtolower($parts['scheme']);
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        return [$scheme, strtolower($parts['host']), $port];
    };

    if ($origin($url) !== $origin($home_url)) {
        throw new RuntimeException('Authenticated frontend QA is restricted to the WordPress home origin.');
    }

    return [
        'headers' => ['Authorization' => trim($authorization_header)],
        'timeout' => 25,
        'redirection' => 0,
        'sslverify' => true,
    ];
}

/** Select and verify the CLI PHP executable used by isolated Task 7B checks. */
function vg_product_single_124_qa_php_cli_binary(?string $configured_binary = null): string
{
    $binary = trim($configured_binary ?? (string) getenv('VG_TASK_7B_PHP_CLI'));
    if ($binary === '') {
        $binary = PHP_BINARY;
    }

    if (! is_file($binary) || ! is_executable($binary) || ! function_exists('exec')) {
        throw new RuntimeException('Task 7B isolated checks require an executable PHP CLI binary and PHP exec support.');
    }

    $output = [];
    $exit = 1;
    exec(
        escapeshellarg($binary) . ' -r ' . escapeshellarg('echo PHP_SAPI;') . ' 2>&1',
        $output,
        $exit
    );

    if ($exit !== 0 || implode("\n", $output) !== 'cli') {
        throw new RuntimeException('VG_TASK_7B_PHP_CLI must name an executable PHP CLI binary.');
    }

    return $binary;
}

/** Add the one-request Coming Soon token only to same-origin Product URLs. */
function vg_product_single_124_qa_frontend_bypass_url(string $url, string $home_url, string $token): string
{
    if (preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1) {
        throw new RuntimeException('Task 7B requires a 64-character hexadecimal frontend bypass token.');
    }

    $origin = static function (string $candidate): array {
        $parts = parse_url($candidate);
        if (
            ! is_array($parts)
            || ! isset($parts['scheme'], $parts['host'])
            || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new RuntimeException('Task 7B frontend bypass requires an absolute HTTP(S) URL without user info.');
        }

        $scheme = strtolower($parts['scheme']);
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        return [$scheme, strtolower($parts['host']), $port];
    };

    if ($origin($url) !== $origin($home_url)) {
        throw new RuntimeException('Task 7B frontend bypass is restricted to the WordPress home origin.');
    }

    $parts = parse_url($url);
    $path = is_array($parts) ? (string) ($parts['path'] ?? '/') : '/';
    if (preg_match('#\A/winkel(?:/|\z)#', $path) !== 1) {
        return $url;
    }

    $query = (string) ($parts['query'] ?? '');
    parse_str($query, $existing_query);
    if (array_key_exists('vg_task_7b_qa_bypass', $existing_query)) {
        throw new RuntimeException('Task 7B frontend URL already contains a Coming Soon bypass parameter.');
    }

    $host = (string) $parts['host'];
    if (str_contains($host, ':') && ! str_starts_with($host, '[')) {
        $host = '[' . $host . ']';
    }
    $scheme = strtolower((string) $parts['scheme']);
    $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
    $request_url = $scheme . '://' . $host . $port . $path;
    $request_query = $query === ''
        ? 'vg_task_7b_qa_bypass=' . rawurlencode($token)
        : $query . '&vg_task_7b_qa_bypass=' . rawurlencode($token);

    return $request_url . '?' . $request_query . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
}

/** Parse the rendered HTML into a DOM, excluding CSS selectors and script text. */
function vg_product_single_124_qa_html_xpath(string $html): DOMXPath
{
    if (! class_exists(DOMDocument::class)) {
        throw new RuntimeException('The PHP DOM extension is required for frontend markup assertions.');
    }

    $previous_errors = libxml_use_internal_errors(true);
    $document = new DOMDocument('1.0', 'UTF-8');
    $loaded = $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous_errors);

    if (! $loaded) {
        throw new RuntimeException('Could not parse frontend response HTML.');
    }

    return new DOMXPath($document);
}

/** Check an exact HTML class token, not a matching CSS selector string. */
function vg_product_single_124_qa_html_has_class(string $html, string $class): bool
{
    if (preg_match('/\A[A-Za-z0-9_-]+\z/', $class) !== 1) {
        throw new RuntimeException('Frontend HTML class checks require one plain class token.');
    }

    $nodes = vg_product_single_124_qa_html_xpath($html)->query(
        '//*[contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]'
    );

    return $nodes !== false && $nodes->length > 0;
}

/** @return list<string> */
function vg_product_single_124_qa_event_card_texts(string $html): array
{
    $nodes = vg_product_single_124_qa_html_xpath($html)->query('//*[@data-vg-card="event"]');
    if ($nodes === false) {
        return [];
    }

    $texts = [];
    foreach ($nodes as $node) {
        $texts[] = trim(preg_replace('/\s+/', ' ', $node->textContent) ?? '');
    }

    return $texts;
}

/** @return list<array{title:string,professional_title:string,excerpt:string,profile_url:string,image_urls:list<string>,cta:string}> */
function vg_product_single_124_qa_person_card_values(string $html): array
{
    $xpath = vg_product_single_124_qa_html_xpath($html);
    $cards = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " vg-medewerker-card ")]');
    if ($cards === false) {
        return [];
    }

    $text_for_class = static function (DOMXPath $xpath, DOMElement $card, string $class): string {
        $nodes = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]', $card);
        return $nodes !== false && $nodes->length > 0
            ? trim(preg_replace('/\s+/', ' ', $nodes->item(0)?->textContent ?? '') ?? '')
            : '';
    };

    $values = [];
    foreach ($cards as $card) {
        if (! $card instanceof DOMElement) {
            continue;
        }

        $links = $xpath->query('.//a[contains(concat(" ", normalize-space(@class), " "), " vg-medewerker-card__link ")]', $card);
        $images = $xpath->query('.//img', $card);
        $image_urls = [];
        if ($images !== false) {
            foreach ($images as $image) {
                if (! $image instanceof DOMElement) {
                    continue;
                }
                $src = trim($image->getAttribute('src'));
                if ($src !== '') {
                    $image_urls[] = $src;
                }
                foreach (explode(',', $image->getAttribute('srcset')) as $candidate) {
                    $candidate_url = trim((string) preg_split('/\s+/', trim($candidate))[0]);
                    if ($candidate_url !== '') {
                        $image_urls[] = $candidate_url;
                    }
                }
            }
        }

        $values[] = [
            'title' => $text_for_class($xpath, $card, 'vg-medewerker-card__name'),
            'professional_title' => $text_for_class($xpath, $card, 'vg-medewerker-card__title'),
            'excerpt' => $text_for_class($xpath, $card, 'vg-medewerker-card__bio'),
            'profile_url' => $links !== false && $links->length > 0 && $links->item(0) instanceof DOMElement
                ? $links->item(0)->getAttribute('href')
                : '',
            'image_urls' => array_values(array_unique($image_urls)),
            'cta' => $links !== false && $links->length > 0
                ? trim(preg_replace('/\s+/', ' ', $links->item(0)?->textContent ?? '') ?? '')
                : '',
        ];
    }

    return $values;
}

/** @return array{user_id:int,manage_woocommerce:bool} */
function vg_product_single_124_qa_assert_identity_payload(int $expected_user_id, int $status, array $payload): array
{
    if ($status !== 200) {
        throw new RuntimeException('The forwarded Basic credential did not authenticate to the frontend identity endpoint.');
    }

    $user_id = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
    if ($expected_user_id < 1 || $user_id !== $expected_user_id) {
        throw new RuntimeException('The forwarded Basic credential resolved to a different WordPress user than the QA process.');
    }

    if (($payload['capabilities']['manage_woocommerce'] ?? null) !== true) {
        throw new RuntimeException('The forwarded Basic credential does not have manage_woocommerce capability.');
    }

    return ['user_id' => $user_id, 'manage_woocommerce' => true];
}

/** @return list<string> */
function vg_product_single_124_qa_sandbox_entries(string $directory): array
{
    if (! is_dir($directory)) {
        throw new RuntimeException('The Novamira sandbox directory is unavailable.');
    }

    $entries = array_values(array_filter(scandir($directory) ?: [], static fn (string $entry): bool => $entry !== '.' && $entry !== '..'));
    sort($entries, SORT_STRING);

    return $entries;
}

/** @param list<string> $baseline
 *  @return list<string>
 */
function vg_product_single_124_qa_assert_sandbox_unchanged(string $directory, array $baseline): array
{
    $current = vg_product_single_124_qa_sandbox_entries($directory);
    $new_entries = array_values(array_diff($current, $baseline));
    $new_php_files = array_values(array_filter(
        $new_entries,
        static fn (string $entry): bool => is_file($directory . DIRECTORY_SEPARATOR . $entry)
            && str_ends_with(strtolower($entry), '.php')
    ));

    if ($new_php_files !== []) {
        throw new RuntimeException('Unknown top-level sandbox PHP file appeared before frontend request: ' . implode(', ', $new_php_files));
    }

    if ($current !== array_values($baseline)) {
        throw new RuntimeException('Novamira sandbox directory changed before frontend request.');
    }

    return $current;
}
