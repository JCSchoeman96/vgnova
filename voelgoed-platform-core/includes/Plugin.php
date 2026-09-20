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

    public function register(): void
    {
        $this->components = [];

        $registry = new DashboardRegistry();
        $this->components['registry'] = $registry;

        $navigation = new Navigation($registry);
        $this->components['navigation'] = $navigation;

        $this->register_component('access_guard', AccessGuard::class);
        $this->register_component('dynamic_data', DashboardDynamicData::class);
        $this->register_component('query_provider', DashboardQueryProvider::class);
        $this->register_component('cache_policy', CachePolicy::class);

        foreach ($this->components as $component) {
            if (method_exists($component, 'register')) {
                $component->register();
            }
        }
    }

    /**
     * @return array<string, object>
     */
    public function components(): array
    {
        return $this->components;
    }

    private function register_component(string $key, string $class): void
    {
        if (! class_exists($class)) {
            return;
        }

        $component = new $class();

        if (is_object($component)) {
            $this->components[$key] = $component;
        }
    }
}
