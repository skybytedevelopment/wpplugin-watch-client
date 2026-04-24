<?php
/**
 * Helper functions.
 *
 * Thin wrappers around WordPress option functions that namespace all keys
 * under the 'wpw_' prefix to avoid collisions with other plugins.
 *
 * @package WPPlugin_Watch
 * @license GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Retrieve a plugin option by its unprefixed key.
 *
 * @param  string $key     Option key without the 'wpw_' prefix.
 * @param  mixed  $default Value to return if the option does not exist.
 * @return mixed           Option value, or $default.
 */
function wpw_get_option( $key, $default = '' ) {
    return get_option( 'wpw_' . $key, $default );
}

/**
 * Update a plugin option by its unprefixed key.
 *
 * @param  string $key   Option key without the 'wpw_' prefix.
 * @param  mixed  $value Value to store.
 * @return bool          True if the value was updated, false otherwise.
 */
function wpw_update_option( $key, $value ) {
    return update_option( 'wpw_' . $key, $value );
}
