<?php
/**
 * WP Speed Optimization - Admin AJAX Handlers
 *
 * Handles AJAX requests for Tools page actions.
 *
 * @package WP_Speed_Optimization
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_ajax_wpso_clear_cache', 'wpso_ajax_clear_cache' );
add_action( 'wp_ajax_wpso_generate_critical_css', 'wpso_ajax_generate_critical_css' );
add_action( 'wp_ajax_wpso_optimize_db', 'wpso_ajax_optimize_database' );
add_action( 'wp_ajax_wpso_test_performance', 'wpso_ajax_test_performance' );
add_action( 'wp_ajax_wpso_optimize_js', 'wpso_ajax_optimize_js' );
add_action( 'wp_ajax_wpso_optimize_images', 'wpso_ajax_optimize_images' );

function wpso_ajax_clear_cache() {
    check_ajax_referer( 'wpso_admin_nonce', 'nonce' );
    $result = WPSO_Cache_Handler::clear_cache();
    wp_send_json_success( array( 'message' => 'Cache cleared!' ) );
}

function wpso_ajax_generate_critical_css() {
    check_ajax_referer( 'wpso_admin_nonce', 'nonce' );
    $result = method_exists( 'WPSO_CSS_Optimizer', 'generate_critical_css' )
        ? WPSO_CSS_Optimizer::generate_critical_css()
        : false;
    $message = $result ? 'Critical CSS generated!' : 'Failed to generate CSS.';
    wp_send_json_success( array( 'message' => $message ) );
}

function wpso_ajax_optimize_database() {
    check_ajax_referer( 'wpso_admin_nonce', 'nonce' );
    WP_Speed_Optimization::optimize_database_scheduled();
    wp_send_json_success( array( 'message' => 'Database optimized!' ) );
}

function wpso_ajax_test_performance() {
    check_ajax_referer( 'wpso_admin_nonce', 'nonce' );
    $data = method_exists( 'WPSO_Performance_Monitor', 'run_test' )
        ? WPSO_Performance_Monitor::run_test()
        : array();
    wp_send_json_success( array( 'message' => 'Performance tested!', 'data' => $data ) );
}

function wpso_ajax_optimize_js() {
    check_ajax_referer( 'wpso_admin_nonce', 'nonce' );
    $result = method_exists( 'WPSO_JS_Optimizer', 'run_optimization' )
        ? WPSO_JS_Optimizer::run_optimization()
        : false;
    $message = $result ? 'JavaScript optimized!' : 'Failed to optimize JS.';
    wp_send_json_success( array( 'message' => $message ) );
}

function wpso_ajax_optimize_images() {
    check_ajax_referer( 'wpso_admin_nonce', 'nonce' );
    $result = method_exists( 'WPSO_Image_Optimizer', 'run_optimization' )
        ? WPSO_Image_Optimizer::run_optimization()
        : false;
    $message = $result ? 'Images optimized!' : 'Failed to optimize images.';
    wp_send_json_success( array( 'message' => $message ) );
}
