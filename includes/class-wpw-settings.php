<?php
/**
 * Settings class.
 *
 * Manages site-level configuration. Currently the only "setting" is the site
 * fingerprint, which is derived deterministically rather than entered by the
 * user. init() is a stub reserved for future settings registration (e.g. a
 * premium license key input field) -- do not remove it.
 *
 * @package WPPlugin_Watch
 * @license GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPW_Settings {

    /**
     * Register any WordPress settings. Currently a no-op.
     *
     * The fingerprint is derived automatically and is not a user-facing
     * setting, so there is nothing to register here yet. This method exists
     * as a hook point for future settings (e.g. license key for premium tier).
     *
     * @return void
     */
    public static function init() {
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
    }

    /**
     * Register settings and fields at the correct admin lifecycle hook.
     */
    public static function register_settings() {
        // Register the setting (stored in options table)
        register_setting( 'general', 'wpw_enable_scanning', [
            'type' => 'boolean',
            'sanitize_callback' => function ( $value ) {
                return (bool) $value;
            },
            'default' => false,
        ] );

        // Add a checkbox field to Settings → General
        add_settings_field(
            'wpw_enable_scanning',
            'WPPlugin Watch Scanning',
            [ __CLASS__, 'render_enable_scanning_field' ],
            'general'
        );
    }

    /**
     * Return the site fingerprint, generating and persisting it on first call.
     *
     * The fingerprint is a SHA-256 hash of: domain + site URL + AUTH_SALT.
     * It is one-way (SHA-256 is not reversible), contains no PII, and is
     * stable across plugin reinstalls as long as AUTH_SALT does not change.
     * It is used as the bearer credential on authenticated API requests.
     *
     * If AUTH_SALT changes (e.g. WordPress security key rotation), the
     * fingerprint changes and the site gets a fresh install record on the
     * backend. This is acceptable -- see ADR-001.
     *
     * @return string 64-character hex SHA-256 fingerprint.
     */
    public static function get_fingerprint() {
        $fp = get_option( 'wpw_site_fingerprint', '' );
        if ( $fp ) {
            return $fp;
        }
        return self::generate_fingerprint();
    }

    /**
     * Compute, persist, and return a fresh fingerprint.
     *
     * Called automatically by get_fingerprint() on first activation, or any
     * time the stored value is missing (e.g. after a manual option delete).
     *
     * @return string 64-character hex SHA-256 fingerprint.
     */
    public static function generate_fingerprint() {
        $domain   = wp_parse_url( get_site_url(), PHP_URL_HOST );
        $site_url = get_site_url();
        $salt     = defined( 'AUTH_SALT' ) ? AUTH_SALT : wp_salt( 'auth' );

        $fp = hash( 'sha256', $domain . $site_url . $salt );
        update_option( 'wpw_site_fingerprint', $fp );
        return $fp;
    }
    /**
     * Render the enable scanning checkbox field.
     */
    public static function render_enable_scanning_field() {
        $enabled = (bool) get_option( 'wpw_enable_scanning', false );
        ?>
        <label for="wpw_enable_scanning">
            <input type="checkbox" id="wpw_enable_scanning" name="wpw_enable_scanning" value="1" <?php checked( $enabled ); ?> />
            Enable vulnerability scanning (sends plugin, theme, and WordPress version data to WPPlugin Watch API)
        </label>
        <p class="description">
            This feature sends installed software version data to an external service to check for known vulnerabilities. No personal data is transmitted.
        </p>
        <?php
    }
}
