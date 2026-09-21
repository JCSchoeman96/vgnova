<?php
declare(strict_types=1);

namespace Voelgoed\PlatformCore;

use Voelgoed\PlatformCore\Bricks\DashboardDynamicData;
use Voelgoed\PlatformCore\Bricks\DashboardQueryProvider;
use Voelgoed\PlatformCore\Dashboard\AccessGuard;
use Voelgoed\PlatformCore\Dashboard\DashboardRegistry;
use Voelgoed\PlatformCore\Dashboard\Navigation;
use Voelgoed\PlatformCore\Performance\CachePolicy;

/**
 * Wires the dashboard integration classes after WordPress has loaded plugins.
 *
 * Provider behaviour belongs in the focused services. This class only keeps
 * the registration order and optional integration boundaries in one place.
 */
final class Plugin
{
    /**
     * @var array<string, object>
     */
    private array $components = [];

    private bool $registered = false;

    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        $this->components = [];

        $registry = new DashboardRegistry();
        $this->components['registry'] = $registry;

        $navigation = new Navigation($registry);
        $this->components['navigation'] = $navigation;

        $cache_policy = new CachePolicy();
        $this->components['cache_policy'] = $cache_policy;

        $this->register_component('access_guard', AccessGuard::class);
        $this->register_component('dynamic_data', DashboardDynamicData::class, [$registry, $cache_policy]);
        $this->register_component('query_provider', DashboardQueryProvider::class, [$registry]);

        foreach ($this->components as $component) {
            if (method_exists($component, 'register')) {
                $component->register();
            }
        }

        $this->registered = true;
    }

    /**
     * @return array<string, object>
     */
    public function components(): array
    {
        return $this->components;
    }

    /**
     * @param array<int, mixed> $constructor_args
     */
    private function register_component(string $key, string $class, array $constructor_args = []): void
    {
        if (! class_exists($class)) {
            return;
        }

        $component = new $class(...$constructor_args);

        if (is_object($component)) {
            $this->components[$key] = $component;
        }
    }
}
