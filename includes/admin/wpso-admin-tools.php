<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WPSO_Admin_Tools' ) ) {

class WPSO_Admin_Tools {
    private $settings;

    public function __construct( $settings ) {
        $this->settings = $settings;
    }

    public function render() {
        ?>
        <div class="wrap wpso-wrap">
            <h1><?php esc_html_e( 'WP Speed Optimization Tools', 'wp-speed-optimization' ); ?></h1>
            <div class="wpso-tools">
                <button id="wpso-clear-cache" class="button button-primary"><?php esc_html_e( 'Clear Cache', 'wp-speed-optimization' ); ?></button>
                <button id="wpso-generate-css" class="button"><?php esc_html_e( 'Generate Critical CSS', 'wp-speed-optimization' ); ?></button>
                <button id="wpso-optimize-db" class="button"><?php esc_html_e( 'Optimize Database', 'wp-speed-optimization' ); ?></button>
                <button id="wpso-test-performance" class="button"><?php esc_html_e( 'Test Performance', 'wp-speed-optimization' ); ?></button>
            </div>
            <div id="wpso-tools-output" class="wpso-tools-output"></div>
        </div>
        <?php
    }
}

} // end if class_exists
