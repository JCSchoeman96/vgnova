<?php
declare(strict_types=1);

/**
 * Plugin Name: Voelgoed Platform Core
 * Description: Unified authenticated Voelgoed customer dashboard integrations.
 * Version: 0.1.0
 * Requires PHP: 8.5
 * Text Domain: voelgoed-platform-core
 */

namespace Voelgoed\PlatformCore;

defined('ABSPATH') || exit;

const VGPC_VERSION = '0.1.0';
const VGPC_PLUGIN_DIR = __DIR__ . '/';

require_once VGPC_PLUGIN_DIR . 'includes/Plugin.php';

$vgpc_include_paths = [
    'includes/Contracts/DashboardServiceInterface.php',
    'includes/Contracts/DashboardAction.php',
    'includes/Contracts/DashboardCard.php',
    'includes/Config/AfrikaansLabels.php',
    'includes/Dashboard/DashboardContext.php',
    'includes/Dashboard/DashboardRoutes.php',
    'includes/Services/MembershipService.php',
    'includes/Services/SubscriptionService.php',
    'includes/Services/CommerceService.php',
    'includes/Services/LearningService.php',
    'includes/Services/TicketService.php',
    'includes/Services/MemberAreaService.php',
    'includes/Dashboard/DashboardRegistry.php',
    'includes/Dashboard/Navigation.php',
    'includes/Dashboard/AccessGuard.php',
    'includes/Bricks/DashboardDynamicData.php',
    'includes/Bricks/DashboardQueryProvider.php',
    'includes/Performance/CachePolicy.php',
];

foreach ($vgpc_include_paths as $vgpc_include_path) {
    $vgpc_include_file = VGPC_PLUGIN_DIR . $vgpc_include_path;
    if (is_file($vgpc_include_file) && is_readable($vgpc_include_file)) {
        require_once $vgpc_include_file;
    }
}

unset($vgpc_include_file, $vgpc_include_path, $vgpc_include_paths);

add_action('plugins_loaded', static function (): void {
    (new Plugin())->register();
}, 20);
