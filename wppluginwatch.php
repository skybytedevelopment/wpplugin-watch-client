<?php
/**
 * Plugin Name: WPPlugin Watch
 * Plugin URI:  https://wpplugin.watch
 * Description: Continuous vulnerability monitoring for WordPress plugins and themes.
 * Version:     1.0.4
 * Author:      Skybyte Development
 * Author URI:  https://skybyte.dev
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Text Domain: wppluginwatch
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin version. Must match the Version header above.
 * Injected by build.sh on each release alongside WPW_VERSION_HASH.
 */
define( 'WPW_VERSION',      '1.0.4' );

/**
 * SHA-256 hash of the plugin codebase, computed by build.sh at release time.
 *
 * IMPORTANT: The hash is computed from all plugin files EXCLUDING this file
 * (wppluginwatch.php). This file is excluded because it contains the hash
 * constant itself — including it would create a circular dependency. Do not
 * move WPW_VERSION or WPW_VERSION_HASH into any other file without updating
 * the build.sh exclusion list.
 *
 * The hash is sent with every scan and version-check request so the backend
 * can compare it against current#latest in DynamoDB and return a version
 * notice when the plugin is out of date.
 */
define( 'WPW_VERSION_HASH', '6c3a8071bdc8f3a170dcbc46a5455651a17b3d0dc7c355f7b9aa522e80f7d455' );

define( 'WPW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * API base URL.
 *
 * Defaults to the production endpoint. To target a different endpoint without
 * touching source code, define WPW_API_BASE_OVERRIDE in wp-config.php on
 * your local/dev environment:
 *
 *   define( 'WPW_API_BASE_OVERRIDE', 'https://api-dev.your-endpoint-here.com' );
 *
 * This constant is never committed to source control. The public plugin always
 * hits production unless WPW_API_BASE_OVERRIDE is explicitly set on the host.
 */
define( 'WPW_API_BASE',
    defined( 'WPW_API_BASE_OVERRIDE' )
        ? WPW_API_BASE_OVERRIDE
        : 'https://api.wpplugin.watch'
);

// Derived endpoint constants
define( 'WPW_API_SCAN_URL',          WPW_API_BASE . '/client-scan' );
define( 'WPW_API_LIFECYCLE_URL',     WPW_API_BASE . '/lifecycle' );
define( 'WPW_API_VERSION_CHECK_URL', WPW_API_BASE . '/version-check' );

require_once WPW_PLUGIN_DIR . 'includes/helpers.php';
require_once WPW_PLUGIN_DIR . 'includes/class-wpw-settings.php';
require_once WPW_PLUGIN_DIR . 'includes/class-wpw-api.php';
require_once WPW_PLUGIN_DIR . 'includes/class-wpw-scanner.php';
require_once WPW_PLUGIN_DIR . 'includes/class-wpw-updater.php';
require_once WPW_PLUGIN_DIR . 'includes/class-wpw-admin.php';
require_once WPW_PLUGIN_DIR . 'includes/class-wpw-lifecycle.php';

// Register deactivation and uninstall hooks before plugins_loaded.
WPW_Lifecycle::register( __FILE__ );

// Schedule the daily version-check cron on activation; unschedule on deactivation.
register_activation_hook( __FILE__, array( 'WPW_Updater', 'schedule' ) );
register_deactivation_hook( __FILE__, array( 'WPW_Updater', 'unschedule' ) );

add_action( 'plugins_loaded', function () {
    WPW_Settings::init();
    WPW_Admin::init();
    WPW_Updater::init();
} );
