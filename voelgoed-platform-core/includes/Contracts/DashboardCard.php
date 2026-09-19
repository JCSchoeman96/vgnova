<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Contracts;

use InvalidArgumentException;

/**
 * Creates the normalized card shape rendered by the dashboard shell.
 */
final class DashboardCard
{
    /**
     * @param array<int, array<string, mixed>> $actions
     * @param array<int, mixed>                $items
     * @return array{
     *     key: string,
     *     label: string,
     *     visible: bool,
     *     state: string,
     *     summary: string,
     *     items: array<int, mixed>,
     *     actions: array<int, array{key: string, label: string, url: string}>
     * }
     */
    public static function make(
        string $key,
        string $label,
        string $state,
        string $summary = '',
        array $actions = [],
        array $items = [],
        bool $visible = true
    ): array {
        $normalized_key = self::key($key);
        $normalized_label = self::display($label);

        if ($normalized_key === '') {
            throw new InvalidArgumentException('Dashboard card key cannot be empty.');
        }

        if ($normalized_label === '') {
            throw new InvalidArgumentException('Dashboard card label cannot be empty.');
        }

        $normalized_actions = [];
        foreach ($actions as $action) {
            if (! is_array($action)) {
                throw new InvalidArgumentException('Dashboard card actions must be arrays.');
            }

            $normalized_actions[] = DashboardAction::normalize($action);
        }

        return [
            'key'     => $normalized_key,
            'label'   => $normalized_label,
            'visible' => $visible,
            'state'   => self::key($state),
            'summary' => self::display($summary),
            'items'   => array_values($items),
            'actions' => $normalized_actions,
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
        string $state = 'unavailable',
        string $summary = '',
        bool $visible = true
    ): array {
        return self::make($key, $label, $state, $summary, [], [], $visible);
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
