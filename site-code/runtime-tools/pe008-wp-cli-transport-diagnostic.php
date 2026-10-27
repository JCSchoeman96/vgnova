<?php
declare(strict_types=1);

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_hook('before_wp_load', static function (): void {
        if (! defined('DISABLE_WP_CRON')) {
            define('DISABLE_WP_CRON', true);
        }
        if (! defined('WPMU_PLUGIN_DIR')) {
            define('WPMU_PLUGIN_DIR', '/dev/null');
        }
    });

    WP_CLI::add_command('pe008 db-transport-boot', static function (): void {
        WP_CLI::line(json_encode([
            'probe' => 'wordpress_bootstrap',
            'result' => 'PASS',
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }, ['when' => 'after_wp_load']);
}
