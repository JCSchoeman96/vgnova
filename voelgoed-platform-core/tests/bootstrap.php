<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/Contracts/DashboardServiceInterface.php';
require_once dirname(__DIR__) . '/includes/Contracts/DashboardAction.php';
require_once dirname(__DIR__) . '/includes/Contracts/DashboardCard.php';
require_once dirname(__DIR__) . '/includes/Config/AfrikaansLabels.php';

if (! function_exists('home_url')) {
    function home_url(string $path = ''): string
    {
        return 'https://voelgoed.test' . $path;
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
