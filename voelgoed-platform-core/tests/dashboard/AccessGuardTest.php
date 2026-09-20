<?php
declare(strict_types=1);

use Voelgoed\PlatformCore\Dashboard\AccessGuard;

final class AccessGuardTest
{
    public function test_authenticated_users_are_allowed_without_a_redirect(): void
    {
        assert_true(class_exists(AccessGuard::class));

        vgpc_set_current_user_id(42);
        $redirects = [];
        $guard = new AccessGuard(
            null,
            static function (string $url) use (&$redirects): void {
                $redirects[] = $url;
            },
            false
        );

        assert_true($guard->enforce('/my-voelgoed/orders/'));
        assert_same([], $redirects);
    }

    public function test_logged_out_dashboard_request_uses_a_safe_validated_return_path(): void
    {
        assert_true(class_exists(AccessGuard::class));

        vgpc_set_current_user_id(0);
        $redirects = [];
        $guard = new AccessGuard(
            null,
            static function (string $url) use (&$redirects): void {
                $redirects[] = $url;
            },
            false
        );

        try {
            assert_false($guard->enforce('/my-voelgoed/subscriptions/?tab=active'));
        } finally {
            vgpc_set_current_user_id(42);
        }

        assert_same(1, count($redirects));
        assert_contains('wp-login.php', $redirects[0]);
        assert_contains('my-voelgoed%2Fsubscriptions%2F%3Ftab%3Dactive', $redirects[0]);
    }

    public function test_external_return_urls_fall_back_to_the_dashboard_root(): void
    {
        assert_true(class_exists(AccessGuard::class));

        vgpc_set_current_user_id(0);
        $redirects = [];
        $guard = new AccessGuard(
            null,
            static function (string $url) use (&$redirects): void {
                $redirects[] = $url;
            },
            false
        );

        try {
            assert_false($guard->enforce('https://attacker.example/steal'));
        } finally {
            vgpc_set_current_user_id(42);
        }

        assert_same(1, count($redirects));
        assert_contains('my-voelgoed%2F', $redirects[0]);
        assert_false(str_contains($redirects[0], 'attacker.example'));
    }

    public function test_encoded_protocol_relative_return_urls_are_rejected(): void
    {
        assert_true(class_exists(AccessGuard::class));

        $guard = new AccessGuard(null, null, false);

        assert_same(
            'https://voelgoed.test/my-voelgoed/',
            $guard->return_url('/%2F%2Fattacker.example/steal')
        );
    }
}
