<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore\Services;

use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use Throwable;
use Voelgoed\PlatformCore\Config\AfrikaansLabels;
use Voelgoed\PlatformCore\Contracts\DashboardAction;
use Voelgoed\PlatformCore\Contracts\DashboardCard;
use Voelgoed\PlatformCore\Contracts\DashboardServiceInterface;

/**
 * Resolves the conditional member-perks hub link.
 *
 * The feature flag is deliberately an injected public boundary. The current
 * member-perks release has no approved dashboard-facing flag accessor, so the
 * production path remains hidden until one is exposed.
 */
final class MemberAreaService implements DashboardServiceInterface
{
    private mixed $provider;

    private mixed $feature_flag_resolver;

    private mixed $hub_access;

    public function __construct(mixed $provider = null, mixed $feature_flag_resolver = null, mixed $hub_access = null)
    {
        // Permit the compact test seam `new MemberAreaService($flag, $hub)`
        // while retaining the injected-boundary form used in production.
        if (
            is_callable($provider)
            && is_object($feature_flag_resolver)
            && method_exists($feature_flag_resolver, 'resolve')
            && $hub_access === null
        ) {
            $hub_access = $feature_flag_resolver;
            $feature_flag_resolver = $provider;
            $provider = null;
        }

        $this->provider = $provider;
        $this->feature_flag_resolver = $feature_flag_resolver;
        $this->hub_access = $hub_access;
    }

    /**
     * @return array<string, mixed>
     */
    public function card(int $user_id): array
    {
        if (! $this->is_authenticated_user($user_id)) {
            return $this->hidden_card();
        }

        try {
            $provider = $this->resolved_provider();
            $feature_flag = $this->feature_flag($provider);
            if ($feature_flag !== true) {
                return $this->hidden_card();
            }

            $hub_access = $this->resolved_hub_access($provider);
            if (! is_object($hub_access) || ! method_exists($hub_access, 'resolve')) {
                return $this->hidden_card();
            }

            $context = 'main';
            $hub_access_class = '\\Voelgoed\\MemberPerks\\Display\\HubAccess';
            if (class_exists($hub_access_class) && defined($hub_access_class . '::CONTEXT_MAIN')) {
                $context = constant($hub_access_class . '::CONTEXT_MAIN');
            }

            $resolution = $hub_access->resolve($context);
            if (! is_array($resolution) || ($resolution['can_view_hub'] ?? false) !== true) {
                return $this->hidden_card();
            }

            $page_id = $this->canonical_id($resolution['hub_page_id'] ?? null);
            if ($page_id === null || ! function_exists('get_permalink')) {
                return $this->hidden_card();
            }

            $url = $this->safe_url((string) get_permalink($page_id));
            if ($url === '') {
                return $this->hidden_card();
            }

            $action = DashboardAction::link('open', 'Maak Lede-area oop', $url);

            return DashboardCard::make(
                'member-area',
                AfrikaansLabels::MEMBER_AREA,
                'active',
                'Jou Lede-area is beskikbaar.',
                [$action],
                [[
                    'hub_page_id' => $page_id,
                    'url'         => $url,
                ]]
            );
        } catch (Throwable) {
            return $this->hidden_card();
        }
    }

    private function resolved_provider(): mixed
    {
        if ($this->provider !== null) {
            return $this->provider;
        }

        $class = '\\Voelgoed\\MemberPerks\\Plugin';
        if (! class_exists($class) || ! is_callable([$class, 'instance'])) {
            return null;
        }

        try {
            return $class::instance();
        } catch (Throwable) {
            return null;
        }
    }

    private function feature_flag(mixed $provider): ?bool
    {
        $resolver = $this->feature_flag_resolver;
        if ($resolver === null && is_callable($provider)) {
            $resolver = $provider;
        }

        if ($resolver !== null) {
            try {
                $value = $this->invoke_callable($resolver, [['vgmp_enable_hub_display'], []]);

                return $this->flag_value($value);
            } catch (Throwable) {
                return null;
            }
        }

        if (! is_object($provider)) {
            return null;
        }

        // Only a public method on the injected integration boundary qualifies.
        // Do not reach into SettingsRepository or read ACF options here.
        foreach (['is_hub_display_enabled', 'is_feature_enabled', 'feature_enabled', 'feature_flag', 'feature_flags'] as $method) {
            if (! method_exists($provider, $method)) {
                continue;
            }

            try {
                $reflection = new ReflectionMethod($provider, $method);
                $arguments = $method === 'feature_flags'
                    ? [[]]
                    : [['vgmp_enable_hub_display'], []];
                foreach ($arguments as $candidate) {
                    if (! $this->accepts_arguments($reflection, count($candidate))) {
                        continue;
                    }

                    return $this->flag_value($provider->{$method}(...$candidate));
                }
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private function resolved_hub_access(mixed $provider): mixed
    {
        if ($this->hub_access !== null) {
            return $this->hub_access;
        }

        if (is_object($provider) && method_exists($provider, 'hub_access')) {
            try {
                return $provider->hub_access();
            } catch (Throwable) {
                return null;
            }
        }

        if (is_object($provider) && method_exists($provider, 'resolve')) {
            return $provider;
        }

        return null;
    }

    private function flag_value(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_array($value)) {
            foreach (['vgmp_enable_hub_display', 'enable_hub_display'] as $key) {
                if (array_key_exists($key, $value) && is_bool($value[$key])) {
                    return $value[$key];
                }
            }
        }

        return null;
    }

    /**
     * @param callable|object $callable
     * @param array<int, array<int, mixed>> $variants
     */
    private function invoke_callable(mixed $callable, array $variants): mixed
    {
        $reflection = is_object($callable) && ! ($callable instanceof \Closure)
            ? new ReflectionMethod($callable, '__invoke')
            : new ReflectionFunction($callable);

        foreach ($variants as $arguments) {
            if (! $this->accepts_arguments($reflection, count($arguments))) {
                continue;
            }

            return $callable(...$arguments);
        }

        return null;
    }

    private function accepts_arguments(ReflectionFunctionAbstract $reflection, int $count): bool
    {
        $required = $reflection->getNumberOfRequiredParameters();
        $maximum = $reflection->isVariadic() ? PHP_INT_MAX : $reflection->getNumberOfParameters();

        return $count >= $required && $count <= $maximum;
    }

    private function canonical_id(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (! is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 && (string) $id === $value ? $id : null;
    }

    private function safe_url(string $url): string
    {
        if ($url === '') {
            return '';
        }

        try {
            return DashboardAction::link('url', 'Skakel', $url)['url'];
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function hidden_card(): array
    {
        return DashboardCard::empty(
            'member-area',
            AfrikaansLabels::MEMBER_AREA,
            'empty',
            'Lede-area is tans nie beskikbaar nie.',
            false
        );
    }

    private function is_authenticated_user(int $user_id): bool
    {
        if ($user_id <= 0 || ! function_exists('get_current_user_id')) {
            return false;
        }

        try {
            return (int) get_current_user_id() === $user_id;
        } catch (Throwable) {
            return false;
        }
    }
}
