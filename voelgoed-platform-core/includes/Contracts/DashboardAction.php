<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Contracts;

use InvalidArgumentException;

/**
 * Builds the small, safe action shape used by dashboard cards.
 */
final class DashboardAction
{
    /**
     * @return array{key: string, label: string, url: string}
     */
    public static function link(string $key, string $label, string $url): array
    {
        return self::normalize([
            'key'   => $key,
            'label' => $label,
            'url'   => $url,
        ]);
    }

    /**
     * Normalize a provider action before it reaches a card or template.
     *
     * @param array<string, mixed> $action
     * @return array{key: string, label: string, url: string}
     */
    public static function normalize(array $action): array
    {
        $raw_key = $action['key'] ?? '';
        $raw_label = $action['label'] ?? '';
        $raw_url = $action['url'] ?? '';
        $key = self::key(is_scalar($raw_key) ? (string) $raw_key : '');
        $label = self::display(is_scalar($raw_label) ? (string) $raw_label : '');
        $url = self::safe_url(is_scalar($raw_url) ? (string) $raw_url : '');

        if ($key === '') {
            throw new InvalidArgumentException('Dashboard action key cannot be empty.');
        }

        if ($label === '') {
            throw new InvalidArgumentException('Dashboard action label cannot be empty.');
        }

        if ($url === '') {
            throw new InvalidArgumentException('Dashboard action URL cannot be empty.');
        }

        return [
            'key'   => $key,
            'label' => $label,
            'url'   => $url,
        ];
    }

    private static function key(string $value): string
    {
        $value = strtolower(trim($value));
        $value = (string) preg_replace('/[^a-z0-9_-]+/', '-', $value);

        return trim($value, '-_');
    }

    private static function display(string $value): string
    {
        if (function_exists('sanitize_text_field')) {
            return trim((string) sanitize_text_field($value));
        }

        $value = strip_tags($value);
        $value = (string) preg_replace('/[\r\n\t ]+/', ' ', $value);

        return trim($value);
    }

    private static function safe_url(string $value): string
    {
        $value = trim($value);

        if ($value === '' || preg_match('/[\x00-\x20\x7f]/', $value) === 1 || str_contains($value, '\\')) {
            return '';
        }

        if (str_starts_with($value, '/')) {
            if (str_starts_with($value, '//')) {
                return '';
            }

            $parts = parse_url($value);

            return is_array($parts) && ($parts['path'] ?? '') !== '' && ! isset($parts['host'], $parts['scheme'])
                ? $value
                : '';
        }

        $parts = parse_url($value);
        if (! is_array($parts) || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
            return '';
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || ! function_exists('home_url')) {
            return '';
        }

        $site_parts = parse_url((string) home_url('/'));
        if (! is_array($site_parts)) {
            return '';
        }

        $site_host = strtolower((string) ($site_parts['host'] ?? ''));
        if ($site_host === '' || $host !== $site_host) {
            return '';
        }

        $url_port = isset($parts['port']) ? (int) $parts['port'] : null;
        $site_port = isset($site_parts['port']) ? (int) $site_parts['port'] : null;
        if ($url_port !== $site_port && ! ($url_port === null && $site_port === null)) {
            return '';
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return '';
        }

        return $value;
    }
}
