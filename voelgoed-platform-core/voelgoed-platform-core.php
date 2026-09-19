<?php
declare(strict_types=1);

/**
 * Plugin Name: Voelgoed Platform Core
 * Description: Unified authenticated Voelgoed customer dashboard integrations.
 * Version: 0.1.0
 * Text Domain: voelgoed-platform-core
 */

namespace Voelgoed\PlatformCore;

defined('ABSPATH') || exit;

const VGPC_VERSION = '0.1.0';
const VGPC_PLUGIN_DIR = __DIR__ . '/';

require_once VGPC_PLUGIN_DIR . 'includes/Plugin.php';
require_once VGPC_PLUGIN_DIR . 'includes/Contracts/DashboardServiceInterface.php';
require_once VGPC_PLUGIN_DIR . 'includes/Contracts/DashboardCard.php';
require_once VGPC_PLUGIN_DIR . 'includes/Contracts/DashboardAction.php';
require_once VGPC_PLUGIN_DIR . 'includes/Config/AfrikaansLabels.php';

add_action('plugins_loaded', static function (): void {
    (new Plugin())->register();
}, 20);
