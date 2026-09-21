<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/Contracts/DashboardServiceInterface.php';
require_once dirname(__DIR__) . '/includes/Contracts/DashboardAction.php';
require_once dirname(__DIR__) . '/includes/Contracts/DashboardCard.php';
require_once dirname(__DIR__) . '/includes/Config/AfrikaansLabels.php';
require_once dirname(__DIR__) . '/includes/Services/MembershipService.php';
require_once dirname(__DIR__) . '/includes/Services/SubscriptionService.php';
require_once dirname(__DIR__) . '/includes/Services/CommerceService.php';
require_once dirname(__DIR__) . '/includes/Services/LearningService.php';
require_once dirname(__DIR__) . '/includes/Services/TicketService.php';
require_once dirname(__DIR__) . '/includes/Services/MemberAreaService.php';
require_once dirname(__DIR__) . '/includes/Dashboard/DashboardContext.php';
require_once dirname(__DIR__) . '/includes/Dashboard/DashboardRoutes.php';
require_once dirname(__DIR__) . '/includes/Dashboard/DashboardRegistry.php';
require_once dirname(__DIR__) . '/includes/Dashboard/Navigation.php';
require_once dirname(__DIR__) . '/includes/Dashboard/AccessGuard.php';
require_once dirname(__DIR__) . '/includes/Bricks/DashboardDynamicData.php';
require_once dirname(__DIR__) . '/includes/Bricks/DashboardQueryProvider.php';
require_once dirname(__DIR__) . '/includes/Performance/CachePolicy.php';
require_once dirname(__DIR__) . '/includes/Plugin.php';

if (! defined('MASTERIYO_VERSION')) {
    define('MASTERIYO_VERSION', '3.4.2');
}

if (! function_exists('home_url')) {
    function home_url(string $path = ''): string
    {
        return (string) ($GLOBALS['vgpc_home_url'] ?? 'https://voelgoed.test') . $path;
    }
}

if (! isset($GLOBALS['vgpc_current_user_id'])) {
    $GLOBALS['vgpc_current_user_id'] = 42;
}

if (! function_exists('get_current_user_id')) {
    function get_current_user_id(): int
    {
        return (int) ($GLOBALS['vgpc_current_user_id'] ?? 0);
    }
}

if (! function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool
    {
        return (int) ($GLOBALS['vgpc_current_user_id'] ?? 0) > 0;
    }
}

if (! function_exists('wp_unslash')) {
    function wp_unslash(mixed $value): mixed
    {
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (! function_exists('wp_validate_redirect')) {
    function wp_validate_redirect(string $location, string $fallback_url = ''): string
    {
        $parts = parse_url($location);
        if (is_array($parts) && str_starts_with($location, '/') && ! str_starts_with($location, '//')) {
            return $location;
        }

        if (is_array($parts) && strtolower((string) ($parts['host'] ?? '')) === 'voelgoed.test') {
            return $location;
        }

        return $fallback_url;
    }
}

if (! function_exists('wp_login_url')) {
    function wp_login_url(string $redirect = ''): string
    {
        return home_url('/wp-login.php?redirect_to=' . rawurlencode($redirect));
    }
}

if (! function_exists('wp_safe_redirect')) {
    function wp_safe_redirect(string $location, int $status = 302, string $x_redirect_by = ''): bool
    {
        $GLOBALS['vgpc_last_redirect'] = $location;

        return true;
    }
}

if (! function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void
    {
        $GLOBALS['vgpc_actions'][$hook][] = [
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        ];
    }
}

if (! function_exists('has_action')) {
    function has_action(string $hook, mixed $callback = null): int|false
    {
        $actions = $GLOBALS['vgpc_actions'][$hook] ?? [];
        if (! is_array($actions) || $actions === []) {
            return false;
        }

        if ($callback === null) {
            return 10;
        }

        foreach ($actions as $action) {
            if (($action['callback'] ?? null) === $callback) {
                return (int) ($action['priority'] ?? 10);
            }
        }

        return false;
    }
}

if (! function_exists('wp_get_current_user')) {
    function wp_get_current_user(): object
    {
        return (object) [
            'ID'         => (int) ($GLOBALS['vgpc_current_user_id'] ?? 0),
            'user_email' => (string) ($GLOBALS['vgpc_current_user_email'] ?? ''),
        ];
    }
}

if (! function_exists('get_permalink')) {
    function get_permalink(int $post_id): string
    {
        return (string) (($GLOBALS['vgpc_permalinks'] ?? [])[$post_id] ?? '');
    }
}

if (! function_exists('vgpc_set_current_user_id')) {
    function vgpc_set_current_user_id(int $user_id): void
    {
        $GLOBALS['vgpc_current_user_id'] = $user_id;
    }
}

if (! function_exists('vgpc_set_current_user_email')) {
    function vgpc_set_current_user_email(string $email): void
    {
        $GLOBALS['vgpc_current_user_email'] = $email;
    }
}

if (! function_exists('vgpc_set_permalink')) {
    function vgpc_set_permalink(int $post_id, string $url): void
    {
        if (! isset($GLOBALS['vgpc_permalinks']) || ! is_array($GLOBALS['vgpc_permalinks'])) {
            $GLOBALS['vgpc_permalinks'] = [];
        }

        $GLOBALS['vgpc_permalinks'][$post_id] = $url;
    }
}

if (! function_exists('vgpc_set_home_url')) {
    function vgpc_set_home_url(string $url): void
    {
        $GLOBALS['vgpc_home_url'] = rtrim($url, '/');
    }
}

if (! isset($GLOBALS['vgpc_assertion_count'])) {
    $GLOBALS['vgpc_assertion_count'] = 0;
}

if (! function_exists('record_assertion')) {
    function record_assertion(): void
    {
        ++$GLOBALS['vgpc_assertion_count'];
    }
}

if (! function_exists('assert_true')) {
    function assert_true(bool $condition, string $message = 'Expected the condition to be true.'): void
    {
        record_assertion();

        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}

if (! function_exists('assert_false')) {
    function assert_false(bool $condition, string $message = 'Expected the condition to be false.'): void
    {
        assert_true(! $condition, $message);
    }
}

if (! function_exists('assert_same')) {
    function assert_same(mixed $expected, mixed $actual, string $message = 'Values differ.'): void
    {
        record_assertion();

        if ($expected !== $actual) {
            throw new RuntimeException(
                $message
                . PHP_EOL
                . 'Expected: ' . var_export($expected, true)
                . PHP_EOL
                . 'Actual: ' . var_export($actual, true)
            );
        }
    }
}

if (! function_exists('assert_not_same')) {
    function assert_not_same(mixed $expected, mixed $actual, string $message = 'Values should differ.'): void
    {
        record_assertion();

        if ($expected === $actual) {
            throw new RuntimeException($message . PHP_EOL . 'Value: ' . var_export($actual, true));
        }
    }
}

if (! function_exists('assert_contains')) {
    function assert_contains(string $needle, string $haystack, string $message = 'String does not contain the expected value.'): void
    {
        assert_true(str_contains($haystack, $needle), $message);
    }
}

if (! function_exists('assert_throws')) {
    /**
     * @param class-string<Throwable> $exception_class
     */
    function assert_throws(string $exception_class, callable $callback, string $message = 'Expected an exception.'): void
    {
        record_assertion();

        try {
            $callback();
        } catch (Throwable $exception) {
            assert_true($exception instanceof $exception_class, $message . ' Wrong exception type.');

            return;
        }

        throw new RuntimeException($message);
    }
}
