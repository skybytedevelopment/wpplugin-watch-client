<?php
/**
 * Lifecycle class.
 *
 * Handles plugin deactivation and uninstall hooks. Notifies the backend so it
 * can update the install record status, then cleans up all local plugin data
 * on uninstall.
 *
 * IMPORTANT -- class loading in uninstall context:
 * WordPress uninstall hooks run in a separate PHP request. At that point
 * plugins_loaded has not fired, so WPW_API, WPW_Settings, and WPW_Updater
 * are not autoloaded. on_uninstall() explicitly requires the class files it
 * needs before using them.
 *
 * @package WPPlugin_Watch
 * @license GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPW_Lifecycle {

    /**
     * Register the deactivation and uninstall hooks for the given plugin file.
     *
     * Must be called before plugins_loaded (i.e. from the main plugin file,
     * not from an add_action callback) so WordPress can find the hooks.
     *
     * @param  string $plugin_file Absolute path to the main plugin file (__FILE__).
     * @return void
     */
    public static function register( $plugin_file ) {
        register_deactivation_hook( $plugin_file, array( __CLASS__, 'on_deactivate' ) );
        register_uninstall_hook( $plugin_file, __CLASS__ . '::on_uninstall' );
    }

    /**
     * Handle plugin deactivation.
     *
     * Sends a fire-and-forget 'deactivated' event to the backend so the
     * install record status can be updated. WPW_Updater::unschedule() is
     * called separately via register_deactivation_hook in wppluginwatch.php.
     *
     * @return void
     */
    public static function on_deactivate() {
        WPW_API::lifecycle( 'deactivated' );
    }

    /**
     * Handle plugin uninstall (triggered on "Delete" in the plugins screen).
     *
     * Runs in a separate request context -- explicitly requires class files
     * before use. Notifies the backend first, then removes all local data so
     * a reinstall starts clean.
     *
     * wp_options keys removed: wpw_site_fingerprint, wpw_last_scan
     * Transients removed:      wpw_last_scan_results, wpw_version_notice
     *
     * @return void
     */
    public static function on_uninstall() {
        // Explicitly load dependencies -- plugins_loaded has not fired here.
        $dir = plugin_dir_path( __FILE__ );
        require_once $dir . 'class-wpw-settings.php';
        require_once $dir . 'class-wpw-api.php';

        // Also need the Updater constant for the notice transient key.
        require_once $dir . 'class-wpw-updater.php';

        // Notify the backend before wiping local data.
        WPW_API::lifecycle( 'deleted' );

        // Remove all local plugin data.
        delete_option( 'wpw_site_fingerprint' );
        delete_option( 'wpw_last_scan' );
        delete_transient( 'wpw_last_scan_results' );
        delete_transient( WPW_Updater::NOTICE_KEY );
    }
}
