<?php
declare(strict_types=1);

namespace Bricks {
    if (! class_exists(Database::class)) {
        final class Database
        {
            /** @var array<string, mixed> */
            public static array $page_data = [];
        }
    }
}

namespace {
    require_once __DIR__ . '/bootstrap.php';

    $repository_file = dirname(__DIR__) . '/includes/Event/EventParticipationRepository.php';
    if (is_file($repository_file)) {
        require_once $repository_file;
    }
    $adapter_file = dirname(__DIR__) . '/includes/Event/EventParticipationBricks.php';
    if (is_file($adapter_file)) {
        require_once $adapter_file;
    }

    $GLOBALS['vgpc_bricks_test_queried_object'] = null;
    $GLOBALS['vgpc_bricks_test_current_post_id'] = 0;
    $GLOBALS['vgpc_bricks_test_is_singular'] = false;
    $GLOBALS['vgpc_bricks_test_is_builder'] = false;
    $GLOBALS['vgpc_bricks_test_is_builder_call'] = false;

    if (! function_exists('is_singular')) {
        function is_singular(string|array $post_types = ''): bool
        {
            if (! $GLOBALS['vgpc_bricks_test_is_singular']) {
                return false;
            }
            if ($post_types === '') {
                return true;
            }

            $post = $GLOBALS['vgpc_bricks_test_queried_object'];
            $post_type = is_object($post) ? (string) ($post->post_type ?? '') : '';
            return in_array($post_type, (array) $post_types, true);
        }
    }

    if (! function_exists('get_queried_object')) {
        function get_queried_object(): mixed
        {
            return $GLOBALS['vgpc_bricks_test_queried_object'];
        }
    }

    if (! function_exists('get_the_ID')) {
        function get_the_ID(): int
        {
            return (int) $GLOBALS['vgpc_bricks_test_current_post_id'];
        }
    }

    if (! function_exists('bricks_is_builder')) {
        function bricks_is_builder(): bool
        {
            return (bool) $GLOBALS['vgpc_bricks_test_is_builder'];
        }
    }

    if (! function_exists('bricks_is_builder_call')) {
        function bricks_is_builder_call(): bool
        {
            return (bool) $GLOBALS['vgpc_bricks_test_is_builder_call'];
        }
    }

    require_once __DIR__ . '/EventParticipationBricksTest.php';

    $test = new EventParticipationBricksTest();
    $passed = 0;
    $failed = 0;
    foreach (get_class_methods($test) as $method) {
        if (! str_starts_with($method, 'test_')) {
            continue;
        }

        try {
            $test->$method();
            ++$passed;
            echo 'PASS ' . $method . PHP_EOL;
        } catch (Throwable $error) {
            ++$failed;
            fwrite(STDERR, 'FAIL ' . $method . ': ' . $error->getMessage() . PHP_EOL);
        }
    }

    if ($failed > 0) {
        fwrite(STDERR, sprintf('%d of %d Event Participation Bricks tests failed.' . PHP_EOL, $failed, $passed + $failed));
        exit(1);
    }

    echo sprintf('%d Event Participation Bricks tests passed.' . PHP_EOL, $passed);
}
