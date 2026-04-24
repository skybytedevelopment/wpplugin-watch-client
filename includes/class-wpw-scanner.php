<?php
/**
 * Scanner class.
 *
 * Collects the installed software inventory from WordPress and packages it
 * into the payload shape expected by POST /client-scan. No vulnerability
 * matching happens here -- all lookup and matching is handled server-side.
 *
 * @package WPPlugin_Watch
 * @license GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPW_Scanner {

    /**
     * Collect the installed software inventory and return a scan payload.
     *
     * Enumerates all installed plugins, themes, and WordPress core version,
     * and includes the site fingerprint and plugin version hash. The payload
     * is passed directly to WPW_API::scan().
     *
     * Plugin slugs are derived from the plugin file path: for a plugin at
     * contact-form-7/contact-form-7.php, dirname() returns contact-form-7,
     * which is the slug used as the DynamoDB partition key on the backend.
     *
     * @return array {
     *     @type string $fingerprint         Site fingerprint (Bearer credential).
     *     @type string $plugin_version_hash SHA-256 hash of this plugin build.
     *     @type array  $plugins             List of {slug, version} maps.
     *     @type array  $themes              List of {slug, version} maps.
     *     @type array  $core                WordPress core {version} map.
     * }
     */
    public static function collect() {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $plugins = array();
        foreach ( get_plugins() as $slug => $data ) {
            $plugins[] = array(
                'slug'    => dirname( $slug ),
                'version' => $data['Version'],
            );
        }

        $themes = array();
        foreach ( wp_get_themes() as $slug => $theme ) {
            $themes[] = array(
                'slug'    => $slug,
                'version' => $theme->get( 'Version' ),
            );
        }

        global $wp_version;

        return array(
            'fingerprint'         => WPW_Settings::get_fingerprint(),
            'plugin_version_hash' => WPW_VERSION_HASH,
            'plugins'             => $plugins,
            'themes'              => $themes,
            'core'                => array( 'version' => $wp_version ),
        );
    }
}
