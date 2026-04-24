<?php
/**
 * API class.
 *
 * All HTTP communication with the WPPlugin Watch backend (api.wpplugin.watch).
 * Every method that requires authentication sends the site fingerprint as a
 * Bearer token. The fingerprint is the sole identity credential -- there are
 * no separate tokens or API keys managed by this class.
 *
 * Endpoint constants are defined in wppluginwatch.php:
 *   WPW_API_SCAN_URL          POST /client-scan   (authenticated)
 *   WPW_API_VERSION_CHECK_URL POST /version-check (unauthenticated)
 *   WPW_API_LIFECYCLE_URL     POST /lifecycle     (authenticated)
 *
 * @package WPPlugin_Watch
 * @license GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPW_API {

    /**
     * Run a vulnerability scan against the backend.
     *
     * Sends the plugin/theme/core inventory collected by WPW_Scanner::collect()
     * to POST /client-scan. The fingerprint is sent as the Bearer credential.
     * The backend authenticates, enforces the rate limit, runs the lookup
     * against the vulns table, and returns structured results.
     *
     * @param  array           $payload Scan payload from WPW_Scanner::collect().
     * @return array|WP_Error  Decoded JSON response array, or WP_Error on failure.
     */
    public static function scan( $payload ) {
        $fp = WPW_Settings::get_fingerprint();

        $response = wp_remote_post( WPW_API_SCAN_URL, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $fp,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 20,
        ) );

        return self::parse_response( $response );
    }

    /**
     * Lightweight version/update check. Unauthenticated.
     *
     * Sends only the plugin version hash to POST /version-check. The backend
     * compares it against current#latest in DynamoDB and returns a version
     * notice if the plugin is out of date. No credentials are required, so
     * this works before fingerprint generation, after any credential change,
     * and from any context (e.g. WP-Cron).
     *
     * @return array|WP_Error Decoded JSON response, or WP_Error on failure.
     */
    public static function version_check() {
        $response = wp_remote_post( WPW_API_VERSION_CHECK_URL, array(
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( array(
                'plugin_version_hash' => WPW_VERSION_HASH,
            ) ),
            'timeout' => 10,
        ) );

        return self::parse_response( $response );
    }

    /**
     * Fire-and-forget lifecycle notification.
     *
     * Informs the backend of deactivation or deletion events so it can update
     * the install record status. Uses blocking=false and a near-zero timeout
     * so it does not delay the user's action -- the request is intentionally
     * not waited on and any failure is silently ignored.
     *
     * @param  string $event 'deactivated' or 'deleted'.
     * @return void
     */
    public static function lifecycle( $event ) {
        $fp = WPW_Settings::get_fingerprint();

        // Non-blocking fire-and-forget: timeout of 0.01s + blocking=false
        // means we hand the request to the socket and move on immediately.
        // Failures are acceptable -- the backend treats missing lifecycle
        // events as eventually consistent through the purge_installs Lambda.
        wp_remote_post( WPW_API_LIFECYCLE_URL, array(
            'headers'  => array(
                'Authorization' => 'Bearer ' . $fp,
                'Content-Type'  => 'application/json',
            ),
            'body'     => wp_json_encode( array(
                'event'      => $event,
                'wp_version' => get_bloginfo( 'version' ),
            ) ),
            'timeout'  => 0.01,
            'blocking' => false,
            'sslverify' => true,
        ) );
    }

    /**
     * Parse a wp_remote_post response into an array or WP_Error.
     *
     * Handles transport errors, non-200 HTTP status codes, and malformed JSON.
     * For non-200 responses, attempts to extract a 'message' field from the
     * JSON body (the backend always returns one on error).
     *
     * @param  array|WP_Error $response Return value of wp_remote_post().
     * @return array|WP_Error           Decoded response body, or WP_Error.
     */
    private static function parse_response( $response ) {
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code !== 200 ) {
            $error_body = json_decode( $body, true );
            $message    = isset( $error_body['message'] )
                ? $error_body['message']
                : sprintf( __( 'API returned HTTP %d', 'wppluginwatch' ), $code );

            return new WP_Error( 'api_error', $message, array( 'status' => $code ) );
        }

        $decoded = json_decode( $body, true );
        if ( ! is_array( $decoded ) ) {
            return new WP_Error( 'bad_json', __( 'Invalid JSON from API.', 'wppluginwatch' ) );
        }

        return $decoded;
    }
}
