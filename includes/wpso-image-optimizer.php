<?php
/**
 * WP Speed Optimization – Image Optimizer
 *
 * @package WP_Speed_Optimization
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WPSO_Image_Optimizer' ) ) {
class WPSO_Image_Optimizer {
    public static function init() {
        add_filter( 'wp_get_attachment_image_attributes', [ __CLASS__, 'add_lazy_load' ], 10, 3 );
    }

    /**
     * Add loading="lazy" to img attributes
     */
    public static function add_lazy_load( $attr, $attachment, $size ) {
        $attr['loading'] = 'lazy';
        return $attr;
    }

    /**
     * Run optimization via AJAX
     */
    public static function run_optimization() {
        $attachments = get_posts([
            'post_type'      => 'attachment',
            'post_mime_type' => [ 'image/jpeg', 'image/png' ],
            'numberposts'    => -1,
            'fields'         => 'ids',
        ]);

        foreach ( $attachments as $id ) {
            $file = get_attached_file( $id );
            $path = pathinfo( $file );
            $webp = "{$path['dirname']}/{$path['filename']}.webp";
            if ( ! file_exists( $webp ) ) {
                // Convert to WebP if Imagick is available
                if ( class_exists( 'Imagick' ) ) {
                    $image = new Imagick( $file );
                    $image->setImageFormat( 'webp' );
                    $image->writeImage( $webp );
                }
            }
        }
        return true;
    }
}

WPSO_Image_Optimizer::init();
}
