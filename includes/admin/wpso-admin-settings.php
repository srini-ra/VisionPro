<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WPSO_Admin_Settings' ) ) {

class WPSO_Admin_Settings {
    private $settings;

    public function __construct( $settings ) {
        $this->settings = $settings;
    }

    public function render() {
        ?>
        <div class="wrap wpso-wrap">
            <h1><?php esc_html_e( 'WP Speed Optimization Settings', 'wp-speed-optimization' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'wpso_settings' );
                do_settings_sections( 'wpso_settings' );
                ?>
                <table class="form-table">
                    <?php $this->checkbox( 'cache_enabled', __( 'Enable Page Cache', 'wp-speed-optimization' ) ); ?>
                    <?php $this->checkbox( 'critical_css', __( 'Generate & Inline Critical CSS', 'wp-speed-optimization' ) ); ?>
                    <?php $this->checkbox( 'css_optimization', __( 'Optimize & Minify CSS', 'wp-speed-optimization' ) ); ?>
                    <?php $this->checkbox( 'js_optimization', __( 'Optimize & Defer JavaScript', 'wp-speed-optimization' ) ); ?>
                    <?php $this->checkbox( 'image_optimization', __( 'Optimize Images & Lazy Load', 'wp-speed-optimization' ) ); ?>
                    <?php $this->checkbox( 'database_optimization', __( 'Enable Database Cleanup', 'wp-speed-optimization' ) ); ?>
                    <?php $this->checkbox( 'performance_monitoring', __( 'Enable Performance Monitoring', 'wp-speed-optimization' ) ); ?>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function checkbox( $key, $label ) {
        $checked = ! empty( $this->settings[ $key ] ) ? 'checked' : '';
        ?>
        <tr valign="top">
            <th scope="row"><?php echo esc_html( $label ); ?></th>
            <td><input type="checkbox" name="wpso_settings[<?php echo esc_attr( $key ); ?>]" <?php echo $checked; ?> /></td>
        </tr>
        <?php
    }
}

} // end if class_exists
