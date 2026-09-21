<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Contracts;

use InvalidArgumentException;

/**
 * Creates the normalized card shape rendered by the dashboard shell.
 */
final class DashboardCard
{
    public const AVAILABILITY_AVAILABLE = 'available';

    public const AVAILABILITY_UNAVAILABLE = 'unavailable';

    public const CONTENT_EMPTY = 'empty';

    public const CONTENT_POPULATED = 'populated';

    /**
     * @param array<int, array<string, mixed>> $actions
     * @param array<int, mixed>                $items
     * @return array{
     *     key: string,
     *     label: string,
     *     visible: bool,
     *     availability: self::AVAILABILITY_AVAILABLE|self::AVAILABILITY_UNAVAILABLE,
     *     content_state: self::CONTENT_EMPTY|self::CONTENT_POPULATED,
     *     summary: string,
     *     items: array<int, mixed>,
     *     actions: array<int, array{key: string, label: string, url: string}>
     * }
     */
    public static function make(
        string $key,
        string $label,
        string $availability,
        string $summary = '',
        array $actions = [],
        array $items = [],
        bool $visible = true,
        ?string $content_state = null
    ): array {
        $normalized_key = self::key($key);
        $normalized_label = self::display($label);

        if ($normalized_key === '') {
            throw new InvalidArgumentException('Dashboard card key cannot be empty.');
        }

        if ($normalized_label === '') {
            throw new InvalidArgumentException('Dashboard card label cannot be empty.');
        }

        $normalized_availability = self::key($availability);
        if (! in_array($normalized_availability, [self::AVAILABILITY_AVAILABLE, self::AVAILABILITY_UNAVAILABLE], true)) {
            throw new InvalidArgumentException('Dashboard card availability must be available or unavailable.');
        }

        $normalized_actions = [];
        foreach ($actions as $action) {
            if (! is_array($action)) {
                throw new InvalidArgumentException('Dashboard card actions must be arrays.');
            }

            $normalized_actions[] = DashboardAction::normalize($action);
        }

        $normalized_content_state = $content_state === null
            ? ($normalized_actions !== [] || $items !== [] ? self::CONTENT_POPULATED : self::CONTENT_EMPTY)
            : self::key($content_state);

        if (! in_array($normalized_content_state, [self::CONTENT_EMPTY, self::CONTENT_POPULATED], true)) {
            throw new InvalidArgumentException('Dashboard card content state must be empty or populated.');
        }

        return [
            'key'           => $normalized_key,
            'label'         => $normalized_label,
            'visible'       => $visible,
            'availability'  => $normalized_availability,
            'content_state' => $normalized_content_state,
            'summary'       => self::display($summary),
            'items'         => array_values($items),
            'actions'       => $normalized_actions,
        ];
    }

    /**
     * Create a valid hidden or unavailable card without provider-specific data.
     *
     * @return array<string, mixed>
     */
    public static function empty(
        string $key,
        string $label,
        string $availability = self::AVAILABILITY_AVAILABLE,
        string $summary = '',
        bool $visible = true
    ): array {
        return self::make($key, $label, $availability, $summary, [], [], $visible, self::CONTENT_EMPTY);
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
}
