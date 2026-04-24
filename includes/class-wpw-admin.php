<?php
/**
 * Admin class.
 *
 * Registers the WPPlugin Watch admin menu page, enqueues assets, handles the
 * AJAX scan request, and renders the main admin UI. All scan execution is
 * manual -- there is no automated scan trigger in this class.
 *
 * @package WPPlugin_Watch
 * @license GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPW_Admin {

    /**
     * Attach all admin hooks. Called on plugins_loaded.
     *
     * @return void
     */
    public static function init() {
        add_action( 'admin_menu',            array( __CLASS__, 'menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
        add_action( 'wp_ajax_wpw_run_scan',  array( __CLASS__, 'ajax_scan' ) );
    }

    /**
     * Register the top-level admin menu page.
     *
     * @return void
     */
    public static function menu() {
        add_menu_page(
            __( 'WPPlugin Watch', 'wppluginwatch' ),
            __( 'WPPlugin Watch', 'wppluginwatch' ),
            'manage_options',
            'wppluginwatch',
            array( __CLASS__, 'render' ),
            'dashicons-shield-alt'
        );
    }

    /**
     * Enqueue admin CSS and JS, and pass server-side data to the script.
     *
     * Only loads assets on the WPPlugin Watch admin page. Passes cached scan
     * results and the current version notice via wp_localize_script so the JS
     * can restore state on page load without an additional request.
     *
     * @param  string $hook Current admin page hook suffix.
     * @return void
     */
    public static function assets( $hook ) {
        if ( $hook !== 'toplevel_page_wppluginwatch' ) {
            return;
        }

        wp_enqueue_style( 'wpw-admin', WPW_PLUGIN_URL . 'assets/admin.css', array(), WPW_VERSION );
        wp_enqueue_script( 'wpw-admin', WPW_PLUGIN_URL . 'assets/admin.js', array(), WPW_VERSION, true );

        $cached         = get_transient( 'wpw_last_scan_results' );
        $version_notice = WPW_Updater::get_notice();

        wp_localize_script( 'wpw-admin', 'wpwData', array(
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'wpw_run_scan' ),
            'cachedResults' => $cached ?: null,
            'versionNotice' => $version_notice ?: null,
        ) );
    }

    /**
     * Handle the wpw_run_scan AJAX request.
     *
     * Collects the site inventory, calls the backend scan API, caches the
     * results for 24 hours, and returns the response to the JS. Also updates
     * the version notice transient if the scan response includes one -- this
     * is a belt-and-suspenders path in case WP-Cron is unreliable on the host.
     *
     * Sends JSON success/error via wp_send_json_success() / wp_send_json_error().
     *
     * @return void
     */
    public static function ajax_scan() {
        check_ajax_referer( 'wpw_run_scan', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wppluginwatch' ) ), 403 );
        }

        $payload  = WPW_Scanner::collect();
        $response = WPW_API::scan( $payload );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ) );
        }

        // Cache results for 24 hours so they survive page navigation.
        set_transient( 'wpw_last_scan_results', $response, DAY_IN_SECONDS );
        update_option( 'wpw_last_scan', time() );

        // Belt-and-suspenders: update version notice from scan response.
        // The daily WP-Cron job is the primary path; this covers hosts where
        // WP-Cron is unreliable.
        if ( ! empty( $response['version_notice'] ) ) {
            set_transient( WPW_Updater::NOTICE_KEY, $response['version_notice'], WPW_Updater::NOTICE_TTL );
        } else {
            delete_transient( WPW_Updater::NOTICE_KEY );
        }

        wp_send_json_success( $response );
    }

    /**
     * Render the main WPPlugin Watch admin page.
     *
     * Outputs the full page HTML including the stats bar, scan button, and
     * result containers. Dynamic content (scan results, version notices) is
     * populated by admin.js after a scan or from cached data on page load.
     *
     * @return void
     */
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $last_scan = get_option( 'wpw_last_scan', null );
        $last_scan_label = $last_scan
            ? sprintf(
                __( 'Last scan: %s', 'wppluginwatch' ),
                wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_scan )
                . ' ' . wp_date( 'T', $last_scan )
            )
            : __( 'Never scanned', 'wppluginwatch' );

        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $plugin_count = count( get_plugins() );
        $theme_count  = count( wp_get_themes() );

        global $wp_version;
        ?>
        <div class="wrap wpw-wrap">

            <div class="wpw-header">
                <div class="wpw-logo">
                    <span class="dashicons dashicons-shield-alt"></span>
                </div>
                <div class="wpw-header-text">
                    <h1 class="wpw-title">
                        <?php esc_html_e( 'WPPlugin Watch', 'wppluginwatch' ); ?>
                        <span class="wpw-version"><?php echo esc_html( WPW_VERSION ); ?></span>
                    </h1>
                    <p class="wpw-subtitle"><?php esc_html_e( 'Continuous vulnerability monitoring', 'wppluginwatch' ); ?></p>
                </div>
                <div class="wpw-last-scan" id="wpw-last-scan"><?php echo esc_html( $last_scan_label ); ?></div>
            </div>

            <div class="wpw-stats-row">
                <div class="wpw-stat-group wpw-stat-group-scanned">
                    <div class="wpw-stat-group-label"><?php esc_html_e( 'Scanned', 'wppluginwatch' ); ?></div>
                    <div class="wpw-stat-group-inner">
                        <div class="wpw-stat">
                            <span class="wpw-stat-num" id="wpw-stat-plugins"><?php echo esc_html( $plugin_count ); ?></span>
                            <span class="wpw-stat-label"><?php esc_html_e( 'Plugins', 'wppluginwatch' ); ?></span>
                        </div>
                        <div class="wpw-stat">
                            <span class="wpw-stat-num" id="wpw-stat-themes"><?php echo esc_html( $theme_count ); ?></span>
                            <span class="wpw-stat-label"><?php esc_html_e( 'Themes', 'wppluginwatch' ); ?></span>
                        </div>
                    </div>
                </div>
                <div class="wpw-stat-group wpw-stat-group-found">
                    <div class="wpw-stat-group-label"><?php esc_html_e( 'Found', 'wppluginwatch' ); ?></div>
                    <div class="wpw-stat-group-inner">
                        <div class="wpw-stat">
                            <span class="wpw-stat-num wpw-num-danger" id="wpw-stat-vulns">&mdash;</span>
                            <span class="wpw-stat-label"><?php esc_html_e( 'Vulnerabilities', 'wppluginwatch' ); ?></span>
                        </div>
                        <div class="wpw-stat">
                            <span class="wpw-stat-num wpw-num-warn" id="wpw-stat-critical">&mdash;</span>
                            <span class="wpw-stat-label"><?php esc_html_e( 'Critical / High', 'wppluginwatch' ); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="wpw-card">
                <div class="wpw-card-header">
                    <span class="wpw-card-title"><?php esc_html_e( 'Vulnerability scan', 'wppluginwatch' ); ?></span>
                </div>
                <div class="wpw-card-body wpw-token-row">
                    <button type="button" id="wpw-scan-btn" class="wpw-btn-scan">
                        <span class="wpw-btn-icon dashicons dashicons-update"></span>
                        <span id="wpw-btn-label"><?php esc_html_e( 'Scan now', 'wppluginwatch' ); ?></span>
                    </button>
                    <span class="wpw-scan-status" id="wpw-scan-status"></span>
                </div>
                <div class="wpw-disclosure">
                    <span class="dashicons dashicons-lock"></span>
                    <?php esc_html_e( 'We send plugin slugs and version numbers only. No content, no user data, no passwords.', 'wppluginwatch' ); ?>
                </div>
            </div>

            <div id="wpw-version-notice" style="display:none"></div>

            <div id="wpw-results" style="display:none">

                <div id="wpw-commentary" class="wpw-card wpw-commentary" style="display:none"></div>

                <div class="wpw-card">
                    <div class="wpw-card-header">
                        <span class="wpw-card-title"><?php esc_html_e( 'Plugins', 'wppluginwatch' ); ?></span>
                        <span class="wpw-badge" id="wpw-badge-plugins"></span>
                    </div>
                    <div id="wpw-plugins-list"></div>
                </div>

                <div class="wpw-card">
                    <div class="wpw-card-header">
                        <span class="wpw-card-title"><?php esc_html_e( 'Themes', 'wppluginwatch' ); ?></span>
                        <span class="wpw-badge" id="wpw-badge-themes"></span>
                    </div>
                    <div id="wpw-themes-list"></div>
                </div>

                <div class="wpw-card">
                    <div class="wpw-card-header">
                        <span class="wpw-card-title">
                            <?php printf( esc_html__( 'WordPress core %s', 'wppluginwatch' ), esc_html( $wp_version ) ); ?>
                        </span>
                        <span class="wpw-badge" id="wpw-badge-core"></span>
                    </div>
                    <div id="wpw-core-list"></div>
                </div>

            </div>

            <p class="wpw-footer">
                <?php esc_html_e( 'WPPlugin Watch by Skybyte Development · Vulnerability data from Wordfence Intelligence', 'wppluginwatch' ); ?>
            </p>

        </div>
        <?php
    }
}
