<?php
/**
 * WP Speed Optimization - Image Optimizer
 *
 * Generates WebP versions and implements lazy-loading for images.
 *
 * @package WP_Speed_Optimization
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WPSO_Image_Optimizer' ) ) {

class WPSO_Image_Optimizer {
    /**
     * Initialize hooks for image optimization
     */
    public static function init() {
        add_filter( 'wp_get_attachment_url', [ __CLASS__, 'replace_with_webp' ], 10, 2 );
        add_filter( 'the_content', [ __CLASS__, 'lazy_load_images' ] );
    }

    /**
     * Replace image URLs with WebP if available
     *
     * @param string $url Original attachment URL
     * @param int    $post_id Attachment ID
     * @return string Modified URL
     */
    public static function replace_with_webp( $url, $post_id ) {
        $webp_path = self::get_webp_path( $url );
        if ( file_exists( $webp_path ) ) {
            return self::webp_url( $url );
        }
        return $url;
    }

    /**
     * Add loading="lazy" to img tags in content
     *
     * @param string $content Post content
     * @return string Modified content
     */
    public static function lazy_load_images( $content ) {
        return preg_replace( '/<img(.*?)>/', '<img loading="lazy"$1>', $content );
    }

    private static function get_webp_path( $url ) {
        $file = wp_parse_url( $url, PHP_URL_PATH );
        return ABSPATH . ltrim( $file, '/' ) . '.webp';
    }

    private static function webp_url( $url ) {
        return $url . '.webp';
    }
}

// Initialize
WPSO_Image_Optimizer::init();

}
