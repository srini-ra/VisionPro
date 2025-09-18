<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WPSO_Admin_Dashboard' ) ) {

class WPSO_Admin_Dashboard {
    private $settings;
    private $cache_handler;
    private $vitals;

    public function __construct( $settings ) {
        $this->settings      = $settings;
        $this->cache_handler = new WPSO_Cache_Handler( $settings );
        $this->vitals        = new WPSO_Core_Web_Vitals( $settings );
    }

    public function render() {
        $stats    = $this->cache_handler->get_cache_stats();
        $averages = $this->vitals->get_vitals_averages( 7 );
        $score    = $this->vitals->get_performance_score( $averages );
        ?>
        <div class="wrap wpso-wrap">
            <h1><?php esc_html_e( 'WP Speed Optimization Dashboard', 'wp-speed-optimization' ); ?></h1>
            <div class="wpso-dashboard">
                <section class="wpso-section wpso-cache-stats">
                    <h2><?php esc_html_e( 'Cache Statistics', 'wp-speed-optimization' ); ?></h2>
                    <table class="wpso-table">
                        <tr><th><?php esc_html_e( 'Hits', 'wp-speed-optimization' ); ?></th><td><?php echo esc_html( $stats['hits'] ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Misses', 'wp-speed-optimization' ); ?></th><td><?php echo esc_html( $stats['misses'] ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Hit Ratio', 'wp-speed-optimization' ); ?></th><td><?php echo esc_html( $stats['hit_ratio'] ); ?>%</td></tr>
                        <tr><th><?php esc_html_e( 'Files Cached', 'wp-speed-optimization' ); ?></th><td><?php echo esc_html( $stats['files'] ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Cache Size', 'wp-speed-optimization' ); ?></th><td><?php echo esc_html( $stats['size_formatted'] ); ?></td></tr>
                    </table>
                </section>
                <section class="wpso-section wpso-vitals">
                    <h2><?php esc_html_e( 'Core Web Vitals (7-day Avg)', 'wp-speed-optimization' ); ?></h2>
                    <table class="wpso-table">
                        <tr><th><?php esc_html_e( 'Overall Score', 'wp-speed-optimization' ); ?></th><td><?php echo esc_html( $score['overall_score'] ); ?>/100</td></tr>
                        <?php foreach ( $score['vitals'] as $metric => $data ) : ?>
                        <tr>
                            <th><?php echo esc_html( strtoupper( $metric ) ); ?></th>
                            <td><?php echo esc_html( $data['score'] ); ?> (<?php echo esc_html( $data['status'] ); ?>)</td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </section>
            </div>
        </div>
        <?php
    }
}

} // end if class_exists
