<?php
/**
 * Plugin Name:     WP Speed Optimization
 * Plugin URI:      https://wpelance.com/wp-speed-optimization
 * Description:     Advanced WordPress speed optimization plugin with Core Web Vitals, caching, CSS optimization, and more.
 * Version:         2.0.0
 * Author:          WPelance
 * Author URI:      https://wpelance.com
 * Text Domain:     wp-speed-optimization
 * Domain Path:     /languages
 * Requires at least: 5.0
 * Tested up to:    6.4
 * Requires PHP:    7.4
 * Network:         true
 *
 * @package WP_Speed_Optimization
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'WPSO_VERSION',           '2.0.0' );
define( 'WPSO_FILE',              __FILE__ );
define( 'WPSO_DIR_PATH',          plugin_dir_path( __FILE__ ) );
define( 'WPSO_DIR_URL',           plugin_dir_url( __FILE__ ) );
define( 'WPSO_PLUGIN_BASENAME',   plugin_basename( __FILE__ ) );

// Autoload core modules
require_once WPSO_DIR_PATH . 'includes/wpso-cache-handler.php';
require_once WPSO_DIR_PATH . 'includes/wpso-core-web-vitals.php';
require_once WPSO_DIR_PATH . 'includes/wpso-css-optimizer.php';
// Removed js optimizer include because file not yet created
require_once WPSO_DIR_PATH . 'includes/wpso-image-optimizer.php';
require_once WPSO_DIR_PATH . 'includes/wpso-database-optimizer.php';
require_once WPSO_DIR_PATH . 'includes/wpso-html-optimizer.php';
require_once WPSO_DIR_PATH . 'includes/wpso-performance-monitor.php';

// Initialize main plugin instance
WP_Speed_Optimization::getInstance();

// Admin initialization: register menus and pages
require_once WPSO_DIR_PATH . 'includes/admin/wpso-admin-init.php';

// Scheduled hooks
add_action( 'wpso_database_optimization', [ 'WP_Speed_Optimization', 'optimize_database_scheduled' ] );
add_action( 'wpso_cache_preload',        [ 'WP_Speed_Optimization', 'preload_cache_scheduled' ] );
