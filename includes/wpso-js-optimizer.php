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
    /** Holds handles to defer */
    private static $defer_handles = [];

    public static function init() {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'capture_scripts' ], 0 );
        add_filter( 'script_loader_tag', [ __CLASS__, 'modify_script_tag' ], 10, 3 );
    }

    /** Capture handles of scripts to defer */
    public static function capture_scripts() {
        global $wp_scripts;
        foreach ( $wp_scripts->registered as $handle => $script ) {
            // Skip jQuery and admin scripts
            if ( 'jquery' === $handle || is_admin() ) {
                continue;
            }
            self::$defer_handles[] = $handle;
        }
    }

    /**
     * Add defer attribute to script tags
     *
     * @param string $tag    Tag markup
     * @param string $handle Script handle
     * @param string $src    URL
     * @return string
     */
    public static function modify_script_tag( $tag, $handle, $src ) {
        if ( in_array( $handle, self::$defer_handles, true ) ) {
            return str_replace( '<script ', '<script defer ', $tag );
        }
        return $tag;
    }

    /**
     * Run optimization via AJAX
     */
    public static function run_optimization() {
        // In a full implementation, concatenate and minify here.
        // For now, we just return success.
        return true;
    }
}

WPSO_JS_Optimizer::init();
}
