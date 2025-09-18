<?php
/**
 * WP Speed Optimization – JavaScript Optimizer
 *
 * @package WP_Speed_Optimization
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WPSO_JS_Optimizer' ) ) {
class WPSO_JS_Optimizer {
    private static $defer_handles = [];

    public static function init() {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'capture_scripts' ], 0 );
        add_filter( 'script_loader_tag', [ __CLASS__, 'modify_script_tag' ], 10, 3 );
    }

    public static function capture_scripts() {
        global $wp_scripts;
        foreach ( $wp_scripts->registered as $handle => $script ) {
            if ( 'jquery' === $handle || is_admin() ) {
                continue;
            }
            self::$defer_handles[] = $handle;
        }
    }

    public static function modify_script_tag( $tag, $handle, $src ) {
        if ( in_array( $handle, self::$defer_handles, true ) ) {
            return str_replace( '<script ', '<script defer ', $tag );
        }
        return $tag;
    }

    public static function run_optimization() {
        return true;
    }
}
}

// Conditional initialization: only if setting is enabled
$settings = get_option( 'wpso_settings', [] );
if ( ! empty( $settings['js_optimization'] ) ) {
    WPSO_JS_Optimizer::init();
}
