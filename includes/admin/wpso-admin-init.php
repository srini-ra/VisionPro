<?php
/**
 * WP Speed Optimization - Admin Initialization
 * 
 * Registers admin menus, pages, and assets
 * 
 * @package WP_Speed_Optimization
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WPSO_Admin_Init' ) ) {

class WPSO_Admin_Init {
    /**
     * Initialize hooks
     */
    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_admin_menus' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
    }

    /**
     * Add admin menu and submenus
     */
    public static function add_admin_menus() {
        // Main menu page
        add_menu_page(
            __( 'WP Speed Optimization', 'wp-speed-optimization' ),
            __( 'Speed Optimization', 'wp-speed-optimization' ),
            'manage_options',
            'wp-speed-optimization-dashboard',
            [ __CLASS__, 'render_dashboard_page' ],
            'dashicons-performance',
            59
        );

        // Submenu: Dashboard
        add_submenu_page(
            'wp-speed-optimization-dashboard',
            __( 'Dashboard', 'wp-speed-optimization' ),
            __( 'Dashboard', 'wp-speed-optimization' ),
            'manage_options',
            'wp-speed-optimization-dashboard',
            [ __CLASS__, 'render_dashboard_page' ]
        );

        // Submenu: Settings
        add_submenu_page(
            'wp-speed-optimization-dashboard',
            __( 'Settings', 'wp-speed-optimization' ),
            __( 'Settings', 'wp-speed-optimization' ),
            'manage_options',
            'wp-speed-optimization-settings',
            [ __CLASS__, 'render_settings_page' ]
        );

        // Submenu: Tools
        add_submenu_page(
            'wp-speed-optimization-dashboard',
            __( 'Tools', 'wp-speed-optimization' ),
            __( 'Tools', 'wp-speed-optimization' ),
            'manage_options',
            'wp-speed-optimization-tools',
            [ __CLASS__, 'render_tools_page' ]
        );
    }

    /**
     * Enqueue admin CSS and JS
     *
     * @param string $hook Current admin page hook
     */
    public static function enqueue_admin_assets( $hook ) {
        if ( strpos( $hook, 'wp-speed-optimization' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'wpso-admin-css',
            plugins_url( 'assets/css/admin.css', WPSO_FILE ),
            [],
            WPSO_VERSION
        );
        wp_enqueue_script(
            'wpso-admin-js',
            plugins_url( 'assets/js/admin.js', WPSO_FILE ),
            [ 'jquery' ],
            WPSO_VERSION,
            true
        );
        wp_localize_script(
            'wpso-admin-js',
            'wpsoAdmin',
            [
                'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'wpso_admin_nonce' ),
                'strings'  => [
                    'clearCache'    => __( 'Clear Cache', 'wp-speed-optimization' ),
                    'generateCss'   => __( 'Generate Critical CSS', 'wp-speed-optimization' ),
                    'optimizeDb'    => __( 'Optimize Database', 'wp-speed-optimization' ),
                    'testPerf'      => __( 'Test Performance', 'wp-speed-optimization' ),
                ],
            ]
        );
    }

    /**
     * Render Dashboard page
     */
    public static function render_dashboard_page() {
        require_once WPSO_DIR_PATH . 'includes/admin/wpso-admin-dashboard.php';
    }

    /**
     * Render Settings page
     */
    public static function render_settings_page() {
        require_once WPSO_DIR_PATH . 'includes/admin/wpso-admin-settings.php';
    }

    /**
     * Render Tools page
     */
    public static function render_tools_page() {
        require_once WPSO_DIR_PATH . 'includes/admin/wpso-admin-tools.php';
    }
}

WPSO_Admin_Init::init();

} // end if class_exists
