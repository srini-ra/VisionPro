<?php
/**
 * Plugin Name:     WP Speed Optimization
 * Plugin URI:      https://wpelance.com/wp-speed-optimization
 * Description:     Advanced WordPress speed optimization plugin.
 * Version:         2.0.0
 * Author:          WPelance
 * Author URI:      https://wpelance.com
 * Text Domain:     wp-speed-optimization
 * Domain Path:     /languages
 * Requires at least: 5.0
 * Tested up to:    6.4
 * Requires PHP:    7.4
 * Network:         true
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants.
define( 'WPSO_VERSION',         '2.0.0' );
define( 'WPSO_FILE',            __FILE__ );
define( 'WPSO_DIR_PATH',        plugin_dir_path( __FILE__ ) );
define( 'WPSO_DIR_URL',         plugin_dir_url( __FILE__ ) );
define( 'WPSO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Require only existing core modules
if ( file_exists( WPSO_DIR_PATH . 'includes/wpso-cache-handler.php' ) ) {
    require_once WPSO_DIR_PATH . 'includes/wpso-cache-handler.php';
}
if ( file_exists( WPSO_DIR_PATH . 'includes/wpso-core-web-vitals.php' ) ) {
    require_once WPSO_DIR_PATH . 'includes/wpso-core-web-vitals.php';
}
if ( file_exists( WPSO_DIR_PATH . 'includes/wpso-css-optimizer.php' ) ) {
    require_once WPSO_DIR_PATH . 'includes/wpso-css-optimizer.php';
}
if ( file_exists( WPSO_DIR_PATH . 'includes/wpso-js-optimizer.php' ) ) {
    require_once WPSO_DIR_PATH . 'includes/wpso-js-optimizer.php';
}
if ( file_exists( WPSO_DIR_PATH . 'includes/wpso-image-optimizer.php' ) ) {
    require_once WPSO_DIR_PATH . 'includes/wpso-image-optimizer.php';
}

// Main plugin class stub
if ( ! class_exists( 'WP_Speed_Optimization' ) ) {
    class WP_Speed_Optimization {
        private static $instance = null;
        
        public static function getInstance() {
            if ( self::$instance === null ) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        
        public static function optimize_database_scheduled() {
            // Database optimization stub
        }
        
        public static function preload_cache_scheduled() {
            // Cache preload stub
        }
    }
}

// Initialize main plugin class
WP_Speed_Optimization::getInstance();

// Admin initialization
if ( file_exists( WPSO_DIR_PATH . 'includes/admin/wpso-admin-init.php' ) ) {
    require_once WPSO_DIR_PATH . 'includes/admin/wpso-admin-init.php';
}
