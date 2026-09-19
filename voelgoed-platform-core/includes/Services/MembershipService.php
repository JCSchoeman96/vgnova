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
        if ($user_id <= 0) {
            return DashboardCard::empty(
                'membership',
                AfrikaansLabels::MEMBERSHIP,
                'empty',
                'Meld aan om jou lidmaatskap te sien.'
            );
        }

        $levels = $this->levels($user_id);
        if ($levels === null) {
            return DashboardCard::empty(
                'membership',
                AfrikaansLabels::MEMBERSHIP,
                'unavailable',
                'Lidmaatskap is tans nie beskikbaar nie.'
            );
        }

        $items = [];
        $first_level_id = null;

        foreach ($levels as $level) {
            $name = $this->value($level, 'name');
            if ($name === '') {
                continue;
            }

            $status = $this->status($this->value($level, 'status'));
            $level_id = $this->scalar_value($level, 'ID');
            if ($level_id === null) {
                $level_id = $this->scalar_value($level, 'id');
            }

            if ($first_level_id === null && $level_id !== null && $level_id !== '') {
                $first_level_id = $level_id;
            }

            $cancel_action = null;
            if ($level_id !== null && $level_id !== '') {
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

        if ($items === []) {
            return DashboardCard::empty(
                'membership',
                AfrikaansLabels::MEMBERSHIP,
                'empty',
                'Geen aktiewe lidmaatskap nie.'
            );
        }

        $actions = [];
        $this->append_action($actions, 'manage', 'Bestuur lidmaatskap', $this->url('account'));
        $this->append_action($actions, 'invoice', 'Sien fakture', $this->url('invoice'));

        if ($first_level_id !== null) {
            $this->append_action(
                $actions,
                'cancel',
                'Kanselleer lidmaatskap',
                $this->url('cancel', 'levelstocancel=' . rawurlencode((string) $first_level_id))
            );
        }

        return DashboardCard::make(
            'membership',
            AfrikaansLabels::MEMBERSHIP,
            'active',
            sprintf('%d aktiewe lidmaatskap', count($items)),
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
        } catch (Throwable) {
            return null;
        }

        if ($levels === false || $levels === null) {
            return [];
        }

        if ($levels instanceof \Traversable) {
            $levels = iterator_to_array($levels, false);
        }

        return is_array($levels) ? array_values($levels) : [];
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

            return $this->scalar_string(\pmpro_url($page, $query, 'https'));
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
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_object($value) && method_exists($value, 'date')) {
            try {
                return $this->scalar_string($value->date('Y-m-d'));
            } catch (Throwable) {
                return '';
            }
        }

        if (is_numeric($value) && (int) $value > 0) {
            return function_exists('wp_date')
                ? (string) \wp_date('Y-m-d', (int) $value)
                : gmdate('Y-m-d', (int) $value);
        }

        return $this->scalar_string($value);
    }

    private function status(string $status): string
    {
        $status = strtolower(trim($status));

        return $status !== '' ? $status : 'active';
    }

    private function status_label(string $status): string
    {
        return [
            'active'   => 'Aktief',
            'inactive' => 'Onaktief',
            'expired'  => 'Verstreke',
            'cancelled' => 'Gekanselleer',
        ][$status] ?? ucfirst(str_replace('-', ' ', $status));
    }
}
