<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Services;

use Throwable;
use Voelgoed\PlatformCore\Config\AfrikaansLabels;
use Voelgoed\PlatformCore\Contracts\DashboardAction;
use Voelgoed\PlatformCore\Contracts\DashboardCard;
use Voelgoed\PlatformCore\Contracts\DashboardServiceInterface;

/**
 * Reads membership access from PMPro without becoming a billing authority.
 */
final class MembershipService implements DashboardServiceInterface
{
    private ?object $provider;

    public function __construct(?object $provider = null)
    {
        $this->provider = $provider;
    }

    /**
     * @return array<string, mixed>
     */
    public function card(int $user_id): array
    {
        if (! $this->is_authenticated_user($user_id)) {
            return DashboardCard::empty(
                'membership',
                AfrikaansLabels::MEMBERSHIP,
                DashboardCard::AVAILABILITY_UNAVAILABLE,
                'Meld aan om jou lidmaatskap te sien.'
            );
        }

        $levels = $this->levels($user_id);
        if ($levels === null) {
            return DashboardCard::empty(
                'membership',
                AfrikaansLabels::MEMBERSHIP,
                DashboardCard::AVAILABILITY_UNAVAILABLE,
                'Lidmaatskap is tans nie beskikbaar nie.'
            );
        }

        $items = [];
        $active_count = 0;
        $first_active_level_id = null;

        try {
            foreach ($levels as $level) {
                $name = $this->value($level, 'name');
                if ($name === '') {
                    continue;
                }

                $status_value = $this->has_value($level, 'status')
                    ? $this->value($level, 'status')
                    : 'active';
                $status = $this->status($status_value);
                if ($status === 'active') {
                    ++$active_count;
                }

                $level_id = $this->scalar_value($level, 'ID');
                if ($level_id === null) {
                    $level_id = $this->scalar_value($level, 'id');
                }

                if ($status === 'active' && $first_active_level_id === null && $level_id !== null && $level_id !== '') {
                    $first_active_level_id = $level_id;
                }

                $cancel_action = null;
                if ($status === 'active' && $level_id !== null && $level_id !== '') {
                    $cancel_url = $this->url(
                        'cancel',
                        'levelstocancel=' . rawurlencode((string) $level_id)
                    );
                    $cancel_action = $this->action(
                        'cancel',
                        'Kanselleer lidmaatskap',
                        $cancel_url
                    );
                }

                $item_actions = [];
                if ($cancel_action !== null) {
                    $item_actions['cancel'] = $cancel_action;
                }

                $items[] = [
                    'level_name'   => $name,
                    'status'       => $status,
                    'status_label' => $this->status_label($status),
                    'start_date'   => $this->date_value($this->raw_value($level, 'startdate')),
                    'expiry_date'  => $this->date_value($this->raw_value($level, 'enddate')),
                    'renewal_date' => $this->date_value(
                        $this->first_raw_value($level, ['renewal_date', 'next_payment'])
                    ),
                    'actions'      => $item_actions,
                ];
            }
        } catch (Throwable) {
            return DashboardCard::empty(
                'membership',
                AfrikaansLabels::MEMBERSHIP,
                DashboardCard::AVAILABILITY_UNAVAILABLE,
                'Lidmaatskap is tans nie beskikbaar nie.'
            );
        }

        if ($items === []) {
            return DashboardCard::empty(
                'membership',
                AfrikaansLabels::MEMBERSHIP,
                DashboardCard::AVAILABILITY_AVAILABLE,
                'Geen aktiewe lidmaatskap nie.'
            );
        }

        $actions = [];
        $this->append_action($actions, 'manage', 'Bestuur lidmaatskap', $this->url('account'));
        $this->append_action($actions, 'invoice', 'Sien fakture', $this->url('invoice'));

        if ($first_active_level_id !== null) {
            $this->append_action(
                $actions,
                'cancel',
                'Kanselleer lidmaatskap',
                $this->url('cancel', 'levelstocancel=' . rawurlencode((string) $first_active_level_id))
            );
        }

        $has_unknown_status = in_array('unknown', array_column($items, 'status'), true);
        $summary = $active_count > 0
            ? sprintf('%d aktiewe lidmaatskap', $active_count)
            : ($has_unknown_status ? 'Lidmaatskapstatus onbekend.' : 'Geen aktiewe lidmaatskap nie.');

        return DashboardCard::make(
            'membership',
            AfrikaansLabels::MEMBERSHIP,
            DashboardCard::AVAILABILITY_AVAILABLE,
            $summary,
            $actions,
            $items
        );
    }

    /**
     * @return array<int, mixed>|null
     */
    private function levels(int $user_id): ?array
    {
        try {
            if ($this->provider !== null) {
                if (! method_exists($this->provider, 'levels')) {
                    return null;
                }

                $levels = $this->provider->levels($user_id);
            } elseif (function_exists('pmpro_getMembershipLevelsForUser')) {
                $levels = \pmpro_getMembershipLevelsForUser($user_id);
            } else {
                return null;
            }
            if ($levels instanceof \Traversable) {
                $levels = iterator_to_array($levels, false);
            }

            return is_array($levels) ? array_values($levels) : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function url(string $page, string $query = ''): string
    {
        try {
            if ($this->provider !== null) {
                if (! method_exists($this->provider, 'url')) {
                    return '';
                }

                return $this->scalar_string($this->provider->url($page, $query));
            }

            if (! function_exists('pmpro_url')) {
                return '';
            }

            $scheme = null;
            if (function_exists('home_url')) {
                $home_parts = parse_url($this->scalar_string(\home_url('/')));
                if (is_array($home_parts) && isset($home_parts['scheme'])) {
                    $candidate = strtolower((string) $home_parts['scheme']);
                    if (in_array($candidate, ['http', 'https'], true)) {
                        $scheme = $candidate;
                    }
                }
            }

            if ($scheme === null) {
                return $this->scalar_string(\pmpro_url($page, $query));
            }

            return $this->scalar_string(\pmpro_url($page, $query, $scheme));
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * @param array<string, array{key: string, label: string, url: string}> $actions
     */
    private function append_action(array &$actions, string $key, string $label, string $url): void
    {
        $action = $this->action($key, $label, $url);
        if ($action !== null) {
            $actions[] = $action;
        }
    }

    /**
     * @return array{key: string, label: string, url: string}|null
     */
    private function action(string $key, string $label, string $url): ?array
    {
        if ($url === '') {
            return null;
        }

        try {
            return DashboardAction::link($key, $label, $url);
        } catch (Throwable) {
            return null;
        }
    }

    private function value(mixed $source, string $key): string
    {
        return $this->scalar_string($this->raw_value($source, $key));
    }

    private function has_value(mixed $source, string $key): bool
    {
        if (is_array($source)) {
            return array_key_exists($key, $source);
        }

        if (! is_object($source)) {
            return false;
        }

        return property_exists($source, $key) || isset($source->{$key});
    }

    private function raw_value(mixed $source, string $key): mixed
    {
        if (is_object($source) && isset($source->{$key})) {
            return $source->{$key};
        }

        if (is_array($source) && array_key_exists($key, $source)) {
            return $source[$key];
        }

        return null;
    }

    private function scalar_value(mixed $source, string $key): int|string|null
    {
        if (is_object($source) && isset($source->{$key}) && is_scalar($source->{$key})) {
            return $source->{$key};
        }

        if (is_array($source) && array_key_exists($key, $source) && is_scalar($source[$key])) {
            return $source[$key];
        }

        return null;
    }

    /**
     * @param array<int, string> $keys
     */
    private function first_raw_value(mixed $source, array $keys): mixed
    {
        foreach ($keys as $key) {
            $value = $this->raw_value($source, $key);
            if (is_object($value) || is_array($value) || (is_scalar($value) && (string) $value !== '')) {
                return $value;
            }
        }

        return '';
    }

    private function scalar_string(mixed $value): string
    {
        if ($value instanceof \Stringable) {
            return trim((string) $value);
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function date_value(mixed $value): string
    {
        if ($value === null || $value === false) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_object($value) && method_exists($value, 'date')) {
            try {
                return $this->date_value($this->scalar_string($value->date('Y-m-d')));
            } catch (Throwable) {
                return '';
            }
        }

        if (is_numeric($value)) {
            if ((float) $value <= 0) {
                return '';
            }

            return function_exists('wp_date')
                ? (string) \wp_date('Y-m-d', (int) $value)
                : date('Y-m-d', (int) $value);
        }

        $text = $this->scalar_string($value);
        if ($text === '' || in_array($text, ['0000-00-00', '0000-00-00 00:00:00'], true)) {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $text) === 1 && ! $this->valid_iso_date($text)) {
            return '';
        }

        $timestamp = strtotime($text);
        if ($timestamp !== false) {
            return function_exists('wp_date')
                ? (string) \wp_date('Y-m-d', $timestamp)
                : date('Y-m-d', $timestamp);
        }

        return '';
    }

    private function valid_iso_date(string $value): bool
    {
        $matches = [];
        if (preg_match(
            '/^(\d{4})-(\d{2})-(\d{2})(?:(?:T| )(\d{2}):(\d{2})(?::(\d{2})(?:\.(\d+))?)?(Z|[+-]\d{2}:?\d{2})?)?$/D',
            $value,
            $matches
        ) !== 1) {
            return false;
        }

        if (! checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
            return false;
        }

        $hour = $matches[4] ?? '';
        if ($hour === '') {
            return true;
        }

        if ((int) $hour > 23 || (int) ($matches[5] ?? -1) > 59 || (int) ($matches[6] ?? 0) > 59) {
            return false;
        }

        $timezone = $matches[8] ?? '';
        if ($timezone === '' || $timezone === 'Z') {
            return true;
        }

        $zone_parts = [];
        if (preg_match('/^[+-](\d{2}):?(\d{2})$/D', $timezone, $zone_parts) !== 1) {
            return false;
        }

        return (int) $zone_parts[1] <= 23 && (int) $zone_parts[2] <= 59;
    }

    private function is_authenticated_user(int $user_id): bool
    {
        if ($user_id <= 0 || ! function_exists('get_current_user_id')) {
            return false;
        }

        try {
            $current_user_id = (int) \get_current_user_id();
        } catch (Throwable) {
            return false;
        }

        return $current_user_id > 0 && $current_user_id === $user_id;
    }

    private function status(string $status): string
    {
        $status = strtolower(trim($status));

        return in_array($status, ['active', 'inactive', 'expired', 'cancelled'], true)
            ? $status
            : 'unknown';
    }

    private function status_label(string $status): string
    {
        return [
            'active'   => 'Aktief',
            'inactive' => 'Onaktief',
            'expired'  => 'Verstreke',
            'cancelled' => 'Gekanselleer',
            'unknown'  => 'Status onbekend',
        ][$status] ?? 'Status onbekend';
    }
}
