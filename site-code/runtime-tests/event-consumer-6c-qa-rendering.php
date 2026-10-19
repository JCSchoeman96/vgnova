<?php
declare(strict_types=1);

function vg_event_consumer_6c_qa_section_details(string $html): array
{
    if (! class_exists(DOMDocument::class) || ! class_exists(DOMXPath::class)) {
        throw new RuntimeException('PHP DOM support is required to inspect the rendered participant cards.');
    }
    $previous_errors = libxml_use_internal_errors(true);
    try {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($document);
        $sections = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " vg-event-participants-section ")]');
        if ($sections === false || $sections->length !== 1) {
            return [];
        }

        $section = $sections->item(0);
        $links = [];
        foreach ($xpath->query('.//a[@href]', $section) ?: [] as $anchor) {
            $classes = preg_split('/\s+/', trim((string) $anchor->getAttribute('class')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if (in_array('brx-query-trail', $classes, true)) {
                continue;
            }
            $links[] = html_entity_decode((string) $anchor->getAttribute('href'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return [
            'text' => trim((string) $section->textContent),
            'html' => (string) $document->saveHTML($section),
            'links' => $links,
        ];
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous_errors);
    }
}
