<?php
/**
 * Plugin Name: WP Speed Optimization
 * Plugin URI: https://wpelance.com/wp-speed-optimization
 * Description: Advanced WordPress speed optimization plugin with Core Web Vitals optimization, intelligent caching, CSS/JS optimization, image optimization, and modern performance enhancements. Boost your site's performance with enterprise-grade optimization features.
 * Version: 2.0.0
 * Author: WPelance
 * Author URI: https://wpelance.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-speed-optimization
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: true
 * 
 * @package WP_Speed_Optimization
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

// Define plugin constants
define('WPSO_VERSION', '2.0.0');
define('WPSO_FILE', __FILE__);
define('WPSO_FILE_BASENAME', basename(__FILE__));
define('WPSO_DIR_URL', plugin_dir_url(__FILE__));
define('WPSO_DIR_PATH', plugin_dir_path(__FILE__));
define('WPSO_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WPSO_MIN_PHP_VERSION', '7.4');
define('WPSO_MIN_WP_VERSION', '5.0');

/**
 * Main WP Speed Optimization Plugin Class
 * 
 * @since 2.0.0
 */
class WP_Speed_Optimization {
    
    /**
     * Plugin instance
     * 
     * @var WP_Speed_Optimization
     */
    private static $instance = null;
    
    /**
     * Plugin settings
     * 
     * @var array
     */
    private $settings = [];
    
    /**
     * Cache handler instance
     * 
     * @var WPSO_Cache_Handler
     */
    private $cache_handler = null;
    
    /**
     * CSS optimizer instance
     * 
     * @var WPSO_CSS_Optimizer
     */
    private $css_optimizer = null;
    
    /**
     * JS optimizer instance
     * 
     * @var WPSO_JS_Optimizer
     */
    private $js_optimizer = null;
    
    /**
     * Image optimizer instance
     * 
     * @var WPSO_Image_Optimizer
     */
    private $image_optimizer = null;

    /**
     * Get plugin instance (Singleton pattern)
     * 
     * @return WP_Speed_Optimization
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Initialize the plugin
     * 
     * @since 2.0.0
     */
    private function __construct() {
        // Check compatibility before initialization
        if (!$this->check_compatibility()) {
            return;
        }
        
        // Initialize plugin
        add_action('init', [$this, 'init']);
        
        // Register activation/deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        register_uninstall_hook(__FILE__, [__CLASS__, 'uninstall']);
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
            add_action('admin_notices', [$this, 'admin_notices']);
            add_action('admin_init', [$this, 'admin_init']);
        }
        
        // AJAX handlers
        add_action('wp_ajax_wpso_clear_cache', [$this, 'ajax_clear_cache']);
        add_action('wp_ajax_wpso_test_optimization', [$this, 'ajax_test_optimization']);
        add_action('wp_ajax_wpso_generate_critical_css', [$this, 'ajax_generate_critical_css']);
        add_action('wp_ajax_wpso_optimize_database', [$this, 'ajax_optimize_database']);
        
        // Frontend optimization hooks
        add_action('template_redirect', [$this, 'start_output_buffer']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_optimizations'], 999);
        
        // Core Web Vitals optimization
        add_action('wp_head', [$this, 'add_critical_css'], 1);
        add_action('wp_head', [$this, 'add_preload_hints'], 2);
        add_action('wp_footer', [$this, 'defer_non_critical_css'], 999);
    }

    /**
     * Check plugin compatibility
     * 
     * @return bool
     */
    private function check_compatibility(): bool {
        // Check PHP version
        if (version_compare(PHP_VERSION, WPSO_MIN_PHP_VERSION, '<')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                printf(
                    esc_html__('WP Speed Optimization requires PHP %s or higher. You are running PHP %s.', 'wp-speed-optimization'),
                    WPSO_MIN_PHP_VERSION,
                    PHP_VERSION
                );
                echo '</p></div>';
            });
            return false;
        }
        
        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, WPSO_MIN_WP_VERSION, '<')) {
            add_action('admin_notices', function() use ($wp_version) {
                echo '<div class="notice notice-error"><p>';
                printf(
                    esc_html__('WP Speed Optimization requires WordPress %s or higher. You are running WordPress %s.', 'wp-speed-optimization'),
                    WPSO_MIN_WP_VERSION,
                    $wp_version
                );
                echo '</p></div>';
            });
            return false;
        }
        
        // Check required extensions
        $required_extensions = ['json', 'mbstring'];
        $missing_extensions = [];
        
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                $missing_extensions[] = $extension;
            }
        }
        
        if (!empty($missing_extensions)) {
            add_action('admin_notices', function() use ($missing_extensions) {
                echo '<div class="notice notice-error"><p>';
                printf(
                    esc_html__('WP Speed Optimization requires the following PHP extensions: %s', 'wp-speed-optimization'),
                    implode(', ', $missing_extensions)
                );
                echo '</p></div>';
            });
            return false;
        }
        
        return true;
    }

    /**
     * Initialize plugin
     * 
     * @since 2.0.0
     */
    public function init(): void {
        // Load text domain for internationalization
        load_plugin_textdomain(
            'wp-speed-optimization',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
        
        // Load plugin settings
        $this->load_settings();
        
        // Include required files
        $this->include_files();
        
        // Initialize optimization modules
        $this->init_optimization_modules();
        
        // Setup performance monitoring
        $this->setup_performance_monitoring();
    }

    /**
     * Load plugin settings
     * 
     * @since 2.0.0
     */
    private function load_settings(): void {
        $default_settings = [
            'cache_enabled' => true,
            'css_optimization' => true,
            'js_optimization' => true,
            'image_optimization' => true,
            'database_optimization' => false,
            'remove_unused_css' => false,
            'critical_css' => false,
            'lazy_loading' => true,
            'webp_conversion' => false,
            'minify_html' => true,
            'gzip_compression' => true,
            'browser_caching' => true,
            'cdn_enabled' => false,
            'performance_monitoring' => true
        ];
        
        $this->settings = wp_parse_args(
            get_option('wpso_settings', []),
            $default_settings
        );
    }

    /**
     * Include required plugin files
     * 
     * @since 2.0.0
     */
    private function include_files(): void {
        $includes = [
            'includes/wpso-init-config.php',
            'includes/wpso-cache-handler.php',
            'includes/wpso-css-optimizer.php',
            'includes/wpso-js-optimizer.php',
            'includes/wpso-image-optimizer.php',
            'includes/wpso-database-optimizer.php',
            'includes/wpso-html-optimizer.php',
            'includes/wpso-performance-monitor.php',
            'includes/wpso-core-web-vitals.php'
        ];
        
        // Include admin files only in admin area
        if (is_admin()) {
    // Include admin files only in admin area
    $admin_includes = [
        'includes/admin/wpso-admin-init.php',
        'includes/admin/wpso-admin-dashboard.php',
        'includes/admin/wpso-admin-settings.php',
        'includes/admin/wpso-admin-tools.php'
    ];
    foreach ($admin_includes as $file) {
        require_once WPSO_DIR_PATH . $file;
    }
}
        
        foreach ($includes as $file) {
            $file_path = WPSO_DIR_PATH . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }

    /**
     * Initialize optimization modules
     * 
     * @since 2.0.0
     */
    private function init_optimization_modules(): void {
        // Initialize cache handler
        if ($this->settings['cache_enabled'] && class_exists('WPSO_Cache_Handler')) {
            $this->cache_handler = new WPSO_Cache_Handler($this->settings);
        }
        
        // Initialize CSS optimizer
        if ($this->settings['css_optimization'] && class_exists('WPSO_CSS_Optimizer')) {
            $this->css_optimizer = new WPSO_CSS_Optimizer($this->settings);
        }
        
        // Initialize JS optimizer
        if ($this->settings['js_optimization'] && class_exists('WPSO_JS_Optimizer')) {
            $this->js_optimizer = new WPSO_JS_Optimizer($this->settings);
        }
        
        // Initialize image optimizer
        if ($this->settings['image_optimization'] && class_exists('WPSO_Image_Optimizer')) {
            $this->image_optimizer = new WPSO_Image_Optimizer($this->settings);
        }
    }

    /**
     * Setup performance monitoring
     * 
     * @since 2.0.0
     */
    private function setup_performance_monitoring(): void {
        if ($this->settings['performance_monitoring'] && class_exists('WPSO_Performance_Monitor')) {
            new WPSO_Performance_Monitor($this->settings);
        }
    }

    /**
     * Start output buffering for HTML optimization
     * 
     * @since 2.0.0
     */
    public function start_output_buffer(): void {
        if (!is_admin() && !wp_doing_ajax()) {
            ob_start([$this, 'optimize_html_output']);
        }
    }

    /**
     * Optimize HTML output
     * 
     * @param string $html HTML content
     * @return string Optimized HTML
     */
    public function optimize_html_output(string $html): string {
        if (class_exists('WPSO_HTML_Optimizer')) {
            $html_optimizer = new WPSO_HTML_Optimizer($this->settings);
            return $html_optimizer->optimize($html);
        }
        return $html;
    }

    /**
     * Frontend optimizations
     * 
     * @since 2.0.0
     */
    public function frontend_optimizations(): void {
        // Add async/defer attributes to scripts
        add_filter('script_loader_tag', [$this, 'add_async_defer_attributes'], 10, 3);
        
        // Preload critical resources
        add_action('wp_head', [$this, 'preload_critical_resources'], 1);
    }

    /**
     * Add async/defer attributes to scripts
     * 
     * @param string $tag Script tag
     * @param string $handle Script handle
     * @param string $src Script source
     * @return string Modified script tag
     */
    public function add_async_defer_attributes(string $tag, string $handle, string $src): string {
        if ($this->js_optimizer) {
            return $this->js_optimizer->add_async_defer_attributes($tag, $handle, $src);
        }
        return $tag;
    }

    /**
     * Add critical CSS to head
     * 
     * @since 2.0.0
     */
    public function add_critical_css(): void {
        if ($this->settings['critical_css'] && $this->css_optimizer) {
            $this->css_optimizer->output_critical_css();
        }
    }

    /**
     * Add preload hints for better performance
     * 
     * @since 2.0.0
     */
    public function add_preload_hints(): void {
        // DNS prefetch for external domains
        $external_domains = $this->get_external_domains();
        foreach ($external_domains as $domain) {
            echo '<link rel="dns-prefetch" href="//' . esc_attr($domain) . '">' . "\n";
        }
        
        // Preload critical resources
        if ($this->css_optimizer) {
            $this->css_optimizer->preload_critical_css();
        }
    }

    /**
     * Preload critical resources
     * 
     * @since 2.0.0
     */
    public function preload_critical_resources(): void {
        // Preload critical fonts, images, etc.
        $critical_resources = apply_filters('wpso_critical_resources', []);
        
        foreach ($critical_resources as $resource) {
            printf(
                '<link rel="preload" href="%s" as="%s"%s>' . "\n",
                esc_url($resource['url']),
                esc_attr($resource['type']),
                !empty($resource['crossorigin']) ? ' crossorigin' : ''
            );
        }
    }

    /**
     * Defer non-critical CSS
     * 
     * @since 2.0.0
     */
    public function defer_non_critical_css(): void {
        if ($this->css_optimizer) {
            $this->css_optimizer->defer_non_critical_css();
        }
    }

    /**
     * Get external domains for DNS prefetch
     * 
     * @return array External domains
     */
    private function get_external_domains(): array {
        return apply_filters('wpso_external_domains', [
            'fonts.googleapis.com',
            'fonts.gstatic.com',
            'www.google-analytics.com',
            'connect.facebook.net'
        ]);
    }

    /**
     * Plugin activation
     * 
     * @since 2.0.0
     */
    public function activate(): void {
        // Create cache directories
        $this->create_cache_directories();
        
        // Set default options
        $this->set_default_options();
        
        // Schedule optimization tasks
        $this->schedule_optimization_tasks();
        
        // Create database tables if needed
        $this->create_database_tables();
        
        // Set activation transient for admin notice
        set_transient('wpso_activation_notice', true, 30);
        
        // Clear any existing cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    /**
     * Plugin deactivation
     * 
     * @since 2.0.0
     */
    public function deactivate(): void {
        // Clear scheduled tasks
        wp_clear_scheduled_hook('wpso_database_optimization');
        wp_clear_scheduled_hook('wpso_cache_preload');
        
        // Clear cache
        if ($this->cache_handler) {
            $this->cache_handler->clear_all_cache();
        }
        
        // Remove .htaccess rules
        $this->remove_htaccess_rules();
    }

    /**
     * Plugin uninstallation (static method)
     * 
     * @since 2.0.0
     */
    public static function uninstall(): void {
        // Remove all plugin options
        $options_to_remove = [
            'wpso_settings',
            'wpso_cache_stats',
            'wpso_performance_data',
            'wpso_critical_css',
            'wpso_optimization_results'
        ];
        
        foreach ($options_to_remove as $option) {
            delete_option($option);
            delete_site_option($option); // For multisite
        }
        
        // Remove cache directories
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/wp-speed-optimization/';
        if (file_exists($cache_dir)) {
            self::remove_directory_recursive($cache_dir);
        }
        
        // Clear scheduled tasks
        wp_clear_scheduled_hook('wpso_database_optimization');
        wp_clear_scheduled_hook('wpso_cache_preload');
    }

    /**
     * Create cache directories
     * 
     * @since 2.0.0
     */
    private function create_cache_directories(): void {
        $upload_dir = wp_upload_dir();
        $cache_dirs = [
            $upload_dir['basedir'] . '/wp-speed-optimization/',
            $upload_dir['basedir'] . '/wp-speed-optimization/cache/',
            $upload_dir['basedir'] . '/wp-speed-optimization/css/',
            $upload_dir['basedir'] . '/wp-speed-optimization/js/',
            $upload_dir['basedir'] . '/wp-speed-optimization/images/',
            $upload_dir['basedir'] . '/wp-speed-optimization/critical-css/'
        ];
        
        foreach ($cache_dirs as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
                
                // Add .htaccess for security
                $htaccess_content = "Options -Indexes\n";
                $htaccess_content .= "Deny from all\n";
                file_put_contents($dir . '.htaccess', $htaccess_content);
            }
        }
    }

    /**
     * Set default plugin options
     * 
     * @since 2.0.0
     */
    private function set_default_options(): void {
        $default_settings = [
            'cache_enabled' => true,
            'css_optimization' => true,
            'js_optimization' => true,
            'image_optimization' => true,
            'lazy_loading' => true,
            'minify_html' => true,
            'gzip_compression' => true,
            'browser_caching' => true
        ];
        
        if (!get_option('wpso_settings')) {
            add_option('wpso_settings', $default_settings);
        }
    }

    /**
     * Schedule optimization tasks
     * 
     * @since 2.0.0
     */
    private function schedule_optimization_tasks(): void {
        // Schedule database optimization
        if (!wp_next_scheduled('wpso_database_optimization')) {
            wp_schedule_event(time(), 'weekly', 'wpso_database_optimization');
        }
        
        // Schedule cache preloading
        if (!wp_next_scheduled('wpso_cache_preload')) {
            wp_schedule_event(time(), 'hourly', 'wpso_cache_preload');
        }
    }

    /**
     * Create database tables if needed
     * 
     * @since 2.0.0
     */
    private function create_database_tables(): void {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Performance monitoring table
        $table_name = $wpdb->prefix . 'wpso_performance_log';
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            page_url varchar(255) NOT NULL,
            load_time decimal(5,3) NOT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY page_url (page_url),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Remove .htaccess rules
     * 
     * @since 2.0.0
     */
    private function remove_htaccess_rules(): void {
        if (function_exists('flush_rewrite_rules')) {
            flush_rewrite_rules();
        }
    }

    /**
     * Remove directory recursively
     * 
     * @param string $dir Directory path
     */
    private static function remove_directory_recursive(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::remove_directory_recursive($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Add admin menu
     * 
     * @since 2.0.0
     */
    public function add_admin_menu(): void {
        add_menu_page(
            esc_html__('WP Speed Optimization', 'wp-speed-optimization'),
            esc_html__('Speed Optimization', 'wp-speed-optimization'),
            'manage_options',
            'wp-speed-optimization',
            [$this, 'admin_dashboard_page'],
            'dashicons-performance',
            59
        );
        
        add_submenu_page(
            'wp-speed-optimization',
            esc_html__('Dashboard', 'wp-speed-optimization'),
            esc_html__('Dashboard', 'wp-speed-optimization'),
            'manage_options',
            'wp-speed-optimization',
            [$this, 'admin_dashboard_page']
        );
        
        add_submenu_page(
            'wp-speed-optimization',
            esc_html__('Settings', 'wp-speed-optimization'),
            esc_html__('Settings', 'wp-speed-optimization'),
            'manage_options',
            'wp-speed-optimization-settings',
            [$this, 'admin_settings_page']
        );
        
        add_submenu_page(
            'wp-speed-optimization',
            esc_html__('Tools', 'wp-speed-optimization'),
            esc_html__('Tools', 'wp-speed-optimization'),
            'manage_options',
            'wp-speed-optimization-tools',
            [$this, 'admin_tools_page']
        );
    }

    /**
     * Admin dashboard page
     * 
     * @since 2.0.0
     */
    public function admin_dashboard_page(): void {
        if (class_exists('WPSO_Admin_Dashboard')) {
            $dashboard = new WPSO_Admin_Dashboard($this->settings);
            $dashboard->render();
        }
    }

    /**
     * Admin settings page
     * 
     * @since 2.0.0
     */
    public function admin_settings_page(): void {
        if (class_exists('WPSO_Admin_Settings')) {
            $settings = new WPSO_Admin_Settings($this->settings);
            $settings->render();
        }
    }

    /**
     * Admin tools page
     * 
     * @since 2.0.0
     */
    public function admin_tools_page(): void {
        if (class_exists('WPSO_Admin_Tools')) {
            $tools = new WPSO_Admin_Tools($this->settings);
            $tools->render();
        }
    }

    /**
     * Enqueue admin scripts and styles
     * 
     * @param string $hook Current admin page hook
     * @since 2.0.0
     */
    public function admin_enqueue_scripts(string $hook): void {
        if (strpos($hook, 'wp-speed-optimization') === false) {
            return;
        }
        
        wp_enqueue_script(
            'wpso-admin',
            WPSO_DIR_URL . 'assets/js/admin.js',
            ['jquery'],
            WPSO_VERSION,
            true
        );
        
        wp_enqueue_style(
            'wpso-admin',
            WPSO_DIR_URL . 'assets/css/admin.css',
            [],
            WPSO_VERSION
        );
        
        wp_localize_script('wpso-admin', 'wpsoAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpso_admin_nonce'),
            'strings' => [
                'clearing_cache' => esc_html__('Clearing cache...', 'wp-speed-optimization'),
                'testing_optimization' => esc_html__('Testing optimization...', 'wp-speed-optimization'),
                'generating_css' => esc_html__('Generating critical CSS...', 'wp-speed-optimization'),
                'optimizing_database' => esc_html__('Optimizing database...', 'wp-speed-optimization'),
                'success' => esc_html__('Operation completed successfully!', 'wp-speed-optimization'),
                'error' => esc_html__('An error occurred. Please try again.', 'wp-speed-optimization')
            ]
        ]);
    }

    /**
     * Admin initialization
     * 
     * @since 2.0.0
     */
    public function admin_init(): void {
        // Register settings
        register_setting('wpso_settings', 'wpso_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings']
        ]);
    }

    /**
     * Sanitize settings
     * 
     * @param array $settings Settings array
     * @return array Sanitized settings
     */
    public function sanitize_settings(array $settings): array {
        $clean_settings = [];
        
        $boolean_fields = [
            'cache_enabled', 'css_optimization', 'js_optimization',
            'image_optimization', 'database_optimization', 'remove_unused_css',
            'critical_css', 'lazy_loading', 'webp_conversion',
            'minify_html', 'gzip_compression', 'browser_caching',
            'cdn_enabled', 'performance_monitoring'
        ];
        
        foreach ($boolean_fields as $field) {
            $clean_settings[$field] = !empty($settings[$field]);
        }
        
        return $clean_settings;
    }

    /**
     * Show admin notices
     * 
     * @since 2.0.0
     */
    public function admin_notices(): void {
        // Show activation notice
        if (get_transient('wpso_activation_notice')) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . esc_html__('WP Speed Optimization has been activated successfully! Visit the dashboard to start optimizing your site.', 'wp-speed-optimization') . '</p>';
            echo '</div>';
            delete_transient('wpso_activation_notice');
        }
    }

    /**
     * AJAX: Clear cache
     * 
     * @since 2.0.0
     */
    public function ajax_clear_cache(): void {
        check_ajax_referer('wpso_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'wp-speed-optimization'));
        }
        
        if ($this->cache_handler) {
            $this->cache_handler->clear_all_cache();
            wp_send_json_success(['message' => esc_html__('Cache cleared successfully!', 'wp-speed-optimization')]);
        } else {
            wp_send_json_error(['message' => esc_html__('Cache handler not available.', 'wp-speed-optimization')]);
        }
    }

    /**
     * AJAX: Test optimization
     * 
     * @since 2.0.0
     */
    public function ajax_test_optimization(): void {
        check_ajax_referer('wpso_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'wp-speed-optimization'));
        }
        
        // Run optimization test
        $test_results = $this->run_optimization_test();
        wp_send_json_success($test_results);
    }

    /**
     * AJAX: Generate critical CSS
     * 
     * @since 2.0.0
     */
    public function ajax_generate_critical_css(): void {
        check_ajax_referer('wpso_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'wp-speed-optimization'));
        }
        
        if ($this->css_optimizer) {
            $result = $this->css_optimizer->generate_critical_css();
            wp_send_json_success($result);
        } else {
            wp_send_json_error(['message' => esc_html__('CSS optimizer not available.', 'wp-speed-optimization')]);
        }
    }

    /**
     * AJAX: Optimize database
     * 
     * @since 2.0.0
     */
    public function ajax_optimize_database(): void {
        check_ajax_referer('wpso_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'wp-speed-optimization'));
        }
        
        if (class_exists('WPSO_Database_Optimizer')) {
            $db_optimizer = new WPSO_Database_Optimizer();
            $result = $db_optimizer->optimize();
            wp_send_json_success($result);
        } else {
            wp_send_json_error(['message' => esc_html__('Database optimizer not available.', 'wp-speed-optimization')]);
        }
    }

    /**
     * Run optimization test
     * 
     * @return array Test results
     */
    private function run_optimization_test(): array {
        $test_url = home_url('/');
        $start_time = microtime(true);
        
        // Test page load
        $response = wp_remote_get($test_url, [
            'timeout' => 30,
            'user-agent' => 'WP Speed Optimization Test'
        ]);
        
        $end_time = microtime(true);
        $load_time = round($end_time - $start_time, 3);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message()
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        $body_size = strlen($body);
        
        return [
            'success' => true,
            'load_time' => $load_time,
            'body_size' => size_format($body_size),
            'response_code' => wp_remote_retrieve_response_code($response),
            'message' => sprintf(
                esc_html__('Page loaded in %s seconds with %s of content.', 'wp-speed-optimization'),
                $load_time,
                size_format($body_size)
            )
        ];
    }

    /**
     * Get plugin settings
     * 
     * @return array Plugin settings
     */
    public function get_settings(): array {
        return $this->settings;
    }

    /**
     * Update plugin settings
     * 
     * @param array $new_settings New settings
     */
    public function update_settings(array $new_settings): void {
        $this->settings = wp_parse_args($new_settings, $this->settings);
        update_option('wpso_settings', $this->settings);
    }
}

// Include pluggable functions
if (!function_exists('is_user_logged_in')) {
    include_once(ABSPATH . 'wp-includes/pluggable.php');
}

// Initialize the plugin
WP_Speed_Optimization::getInstance();

// Hook for scheduled tasks
add_action('wpso_database_optimization', function() {
    if (class_exists('WPSO_Database_Optimizer')) {
        $db_optimizer = new WPSO_Database_Optimizer();
        $db_optimizer->optimize();
    }
});

add_action('wpso_cache_preload', function() {
    $plugin = WP_Speed_Optimization::getInstance();
    if ($plugin->cache_handler) {
        $plugin->cache_handler->preload_cache();
    }
});
