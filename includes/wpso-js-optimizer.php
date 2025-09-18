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
    private static $initialized = false;
    private static $whitelist = [
        // Always exclude these handles from deferral
        'jquery-core',
        'jquery-migrate',
        'jquery',
        'wp-api-fetch',
        'wp-element',
        'wp-hooks',
        'wp-i18n',
        'wp-ajax-response',
        'wp-util',
        'wp-components',
        'wp-dom-ready',
        'wp-polyfill',
    ];

    public static function init() {
        if ( self::$initialized ) {
            return;
        }
        self::$initialized = true;

        // After all scripts are registered, capture which to defer
        add_action( 'wp_print_scripts', [ __CLASS__, 'capture_scripts' ], 100 );
        add_filter( 'script_loader_tag', [ __CLASS__, 'modify_script_tag' ], 10, 3 );
    }

    /** Capture scripts registered after jQuery */
    public static function capture_scripts() {
        global $wp_scripts;
        $found_jquery = false;

        foreach ( $wp_scripts->queue as $handle ) {
            if ( 'jquery-core' === $handle || 'jquery' === $handle ) {
                $found_jquery = true;
                continue;
            }

            if ( ! $found_jquery ) {
                // Not yet reached jQuery in the queue—skip
                continue;
            }

            // Skip whitelisted handles
            if ( in_array( $handle, self::$whitelist, true ) ) {
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
     *
     * @return bool
     */
    public static function run_optimization() {
        // No real concatenation yet, just simulate success
        return true;
    }
}
}

// Only initialize if enabled
$settings = get_option( 'wpso_settings', [] );
if ( ! empty( $settings['js_optimization'] ) && class_exists( 'WPSO_JS_Optimizer' ) ) {
    WPSO_JS_Optimizer::init();
}
