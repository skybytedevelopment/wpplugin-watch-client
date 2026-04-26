

<?php
// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete plugin options
delete_option( 'wpw_enable_scanning' );
delete_option( 'wpw_site_fingerprint' );

// If you ever store site-specific options (multisite safe cleanup)
if ( is_multisite() ) {
    global $wpdb;
    $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );

    foreach ( $blog_ids as $blog_id ) {
        switch_to_blog( $blog_id );
        delete_option( 'wpw_enable_scanning' );
        delete_option( 'wpw_site_fingerprint' );
        restore_current_blog();
    }
}