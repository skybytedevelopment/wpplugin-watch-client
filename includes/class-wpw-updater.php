<?php
/**
 * Updater class.
 *
 * Manages the daily WP-Cron job that checks for plugin updates by calling the
 * unauthenticated POST /version-check endpoint. Results are cached in a
 * transient and read by WPW_Admin on every admin page load.
 *
 * Version notice display rules (per ADR section 7):
 *   - Security notice (type=security): red banner, non-dismissible.
 *   - Standard notice (type=standard): yellow banner, dismissible.
 *
 * The scan response (POST /client-scan) also returns a version_notice as a
 * belt-and-suspenders update path. WPW_Admin::ajax_scan() updates the
 * transient from the scan response so users who scan frequently still receive
 * notices even if WP-Cron is unreliable on their host.
 *
 * @package WPPlugin_Watch
 * @license GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPW_Updater {

    /** WP-Cron hook name for the daily version check. */
    const CRON_HOOK  = 'wpw_daily_version_check';

    /** Transient key for the cached version notice. */
    const NOTICE_KEY = 'wpw_version_notice';

    /**
     * TTL for the cached version notice transient.
     *
     * Set to 2 days so users still see the notice if WP-Cron misses a day.
     * Note: if a security notice is set and WP-Cron subsequently fires during
     * a temporary API outage, delete_transient() in check() will clear it
     * prematurely. Consider extending TTL for security-type notices in a
     * future release.
     */
    const NOTICE_TTL = 2 * DAY_IN_SECONDS;

    /**
     * Attach the cron action handler. Called on plugins_loaded.
     *
     * @return void
     */
    public static function init() {
        add_action( self::CRON_HOOK, array( __CLASS__, 'check' ) );
    }

    /**
     * Schedule the daily version-check cron event. Called on plugin activation.
     *
     * @return void
     */
    public static function schedule() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'daily', self::CRON_HOOK );
        }
    }

    /**
     * Unschedule the daily version-check cron event. Called on deactivation.
     *
     * @return void
     */
    public static function unschedule() {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
    }

    /**
     * Run the version check and cache the result. Called once daily by WP-Cron.
     *
     * On a successful clean response (no version_notice returned), the existing
     * transient is deleted so the banner is cleared. On API error, the existing
     * transient is left untouched so previously shown notices remain visible.
     *
     * @return void
     */
    public static function check() {
        $response = WPW_API::version_check();

        if ( is_wp_error( $response ) ) {
            // Leave any existing notice in place -- do not clear on API error.
            return;
        }

        $notice = isset( $response['version_notice'] ) ? $response['version_notice'] : null;

        if ( $notice ) {
            set_transient( self::NOTICE_KEY, $notice, self::NOTICE_TTL );
        } else {
            // Confirmed up to date -- clear any existing notice.
            delete_transient( self::NOTICE_KEY );
        }
    }

    /**
     * Return the cached version notice, or null if none.
     *
     * The notice array contains at minimum: type (security|standard) and
     * available_version. The admin JS uses type to determine banner style
     * and dismissibility.
     *
     * @return array|null Version notice array, or null if the plugin is current.
     */
    public static function get_notice() {
        return get_transient( self::NOTICE_KEY ) ?: null;
    }
}
