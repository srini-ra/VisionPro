<?php
/**
 * WP Speed Optimization - Advanced Cache Handler
 * 
 * Enterprise-grade caching system with intelligent preloading,
 * smart invalidation, and Core Web Vitals optimization
 * 
 * @package WP_Speed_Optimization
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

class WPSO_Cache_Handler {
    
    /**
     * Cache directory path
     * 
     * @var string
     */
    private $cache_dir;
    
    /**
     * Plugin settings
     * 
     * @var array
     */
    private $settings;
    
    /**
     * Cache statistics
     * 
     * @var array
     */
    private $stats = [];
    
    /**
     * Excluded URLs from caching
     * 
     * @var array
     */
    private $excluded_urls = [];
    
    /**
     * Mobile cache enabled
     * 
     * @var bool
     */
    private $mobile_cache = true;
    
    /**
     * Constructor
     * 
     * @param array $settings Plugin settings
     * @since 2.0.0
     */
    public function __construct(array $settings = []) {
        $this->settings = $settings;
        $this->init_cache_system();
        $this->setup_hooks();
        $this->load_cache_stats();
        $this->set_excluded_urls();
    }
    
    /**
     * Initialize cache system
     * 
     * @since 2.0.0
     */
    private function init_cache_system(): void {
        $upload_dir = wp_upload_dir();
        $this->cache_dir = $upload_dir['basedir'] . '/wp-speed-optimization/cache/';
        
        // Ensure cache directory exists
        if (!file_exists($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
            $this->create_cache_htaccess();
        }
        
        // Initialize mobile cache directory
        if ($this->mobile_cache) {
            $mobile_cache_dir = $this->cache_dir . 'mobile/';
            if (!file_exists($mobile_cache_dir)) {
                wp_mkdir_p($mobile_cache_dir);
            }
        }
    }
    
    /**
     * Setup WordPress hooks
     * 
     * @since 2.0.0
     */
    private function setup_hooks(): void {
        // Cache invalidation hooks
        add_action('save_post', [$this, 'invalidate_post_cache'], 10, 1);
        add_action('wp_update_comment_count', [$this, 'invalidate_post_cache'], 10, 1);
        add_action('transition_comment_status', [$this, 'invalidate_comment_cache'], 10, 3);
        add_action('customize_save_after', [$this, 'clear_all_cache']);
        add_action('switch_theme', [$this, 'clear_all_cache']);
        add_action('activated_plugin', [$this, 'clear_all_cache']);
        add_action('deactivated_plugin', [$this, 'clear_all_cache']);
        
        // Cache preloading
        add_action('wpso_cache_preload', [$this, 'preload_cache']);
        
        // Advanced cache serving
        if (!is_admin() && !wp_doing_ajax() && !wp_doing_cron()) {
            add_action('template_redirect', [$this, 'serve_cached_page'], 1);
            add_action('shutdown', [$this, 'maybe_cache_page'], 999);
        }
        
        // Clean expired cache
        add_action('wp_scheduled_delete', [$this, 'clean_expired_cache']);
    }
    
    /**
     * Load cache statistics
     * 
     * @since 2.0.0
     */
    private function load_cache_stats(): void {
        $this->stats = get_option('wpso_cache_stats', [
            'hits' => 0,
            'misses' => 0,
            'size' => 0,
            'files' => 0,
            'last_cleanup' => time()
        ]);
    }
    
    /**
     * Set excluded URLs from caching
     * 
     * @since 2.0.0
     */
    private function set_excluded_urls(): void {
        $default_exclusions = [
            '/wp-admin/',
            '/wp-login.php',
            '/wp-cron.php',
            '/xmlrpc.php',
            '/wp-json/',
            '/.well-known/',
            '/feed/',
            '/comments/feed/',
            '/cart/',
            '/checkout/',
            '/my-account/',
            '?add-to-cart=',
            '?wc-ajax=',
            '?s=',
            '?p=',
            '?preview=',
            '?customize_changeset_uuid='
        ];
        
        $custom_exclusions = $this->settings['cache_exclusions'] ?? [];
        $this->excluded_urls = array_merge($default_exclusions, $custom_exclusions);
        
        // Apply filters for extensibility
        $this->excluded_urls = apply_filters('wpso_cache_excluded_urls', $this->excluded_urls);
    }
    
    /**
     * Check if current request should be cached
     * 
     * @return bool
     * @since 2.0.0
     */
    private function should_cache_request(): bool {
        // Don't cache if user is logged in (unless specified)
        if (is_user_logged_in() && !$this->settings['cache_logged_in_users']) {
            return false;
        }
        
        // Don't cache POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return false;
        }
        
        // Don't cache if cookies indicate dynamic content
        if ($this->has_dynamic_cookies()) {
            return false;
        }
        
        // Check excluded URLs
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        foreach ($this->excluded_urls as $excluded) {
            if (strpos($request_uri, $excluded) !== false) {
                return false;
            }
        }
        
        // Don't cache if query parameters exist (unless whitelisted)
        if (!empty($_GET) && !$this->has_whitelisted_params()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if request has dynamic cookies
     * 
     * @return bool
     * @since 2.0.0
     */
    private function has_dynamic_cookies(): bool {
        $dynamic_cookies = [
            'comment_author',
            'wp-postpass',
            'woocommerce_cart_hash',
            'woocommerce_items_in_cart',
            'wp_woocommerce_session'
        ];
        
        foreach ($dynamic_cookies as $cookie) {
            if (isset($_COOKIE[$cookie])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if request has whitelisted query parameters
     * 
     * @return bool
     * @since 2.0.0
     */
    private function has_whitelisted_params(): bool {
        $whitelisted_params = apply_filters('wpso_cache_whitelisted_params', [
            'utm_source',
            'utm_medium', 
            'utm_campaign',
            'utm_content',
            'utm_term',
            'fbclid',
            'gclid'
        ]);
        
        foreach ($_GET as $param => $value) {
            if (!in_array($param, $whitelisted_params)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Generate cache key for current request
     * 
     * @return string
     * @since 2.0.0
     */
    private function generate_cache_key(): string {
        $url = $this->get_current_url();
        $key = md5($url);
        
        // Add mobile suffix if mobile cache is enabled
        if ($this->mobile_cache && wp_is_mobile()) {
            $key .= '_mobile';
        }
        
        // Add user role suffix if role-based caching is enabled
        if ($this->settings['role_based_cache'] ?? false) {
            $user = wp_get_current_user();
            if (!empty($user->roles)) {
                $key .= '_' . md5(serialize($user->roles));
            }
        }
        
        return $key;
    }
    
    /**
     * Get current URL
     * 
     * @return string
     * @since 2.0.0
     */
    private function get_current_url(): string {
        $protocol = is_ssl() ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        return $protocol . $host . $uri;
    }
    
    /**
     * Get cache file path
     * 
     * @param string $key Cache key
     * @return string
     * @since 2.0.0
     */
    private function get_cache_file_path(string $key): string {
        $subdir = substr($key, 0, 2) . '/';
        $cache_subdir = $this->cache_dir . $subdir;
        
        if (!file_exists($cache_subdir)) {
            wp_mkdir_p($cache_subdir);
        }
        
        return $cache_subdir . $key . '.html';
    }
    
    /**
     * Get cache metadata file path
     * 
     * @param string $key Cache key
     * @return string
     * @since 2.0.0
     */
    private function get_cache_meta_path(string $key): string {
        return $this->get_cache_file_path($key) . '.meta';
    }
    
    /**
     * Serve cached page if available
     * 
     * @since 2.0.0
     */
    public function serve_cached_page(): void {
        if (!$this->should_cache_request()) {
            return;
        }
        
        $cache_key = $this->generate_cache_key();
        $cache_file = $this->get_cache_file_path($cache_key);
        $meta_file = $this->get_cache_meta_path($cache_key);
        
        if (!file_exists($cache_file) || !file_exists($meta_file)) {
            return;
        }
        
        // Check if cache is expired
        $meta = json_decode(file_get_contents($meta_file), true);
        $cache_lifetime = $this->settings['cache_lifetime'] ?? 3600; // 1 hour default
        
        if ((time() - $meta['timestamp']) > $cache_lifetime) {
            // Cache expired, delete files
            unlink($cache_file);
            unlink($meta_file);
            return;
        }
        
        // Update statistics
        $this->stats['hits']++;
        update_option('wpso_cache_stats', $this->stats);
        
        // Set appropriate headers
        $this->set_cache_headers($meta);
        
        // Serve cached content
        $cached_content = file_get_contents($cache_file);
        
        // Add cache info comment
        $cache_info = sprintf(
            "\n<!-- Cached by WP Speed Optimization on %s -->\n<!-- Cache key: %s -->\n",
            date('Y-m-d H:i:s', $meta['timestamp']),
            $cache_key
        );
        
        echo $cached_content . $cache_info;
        exit;
    }
    
    /**
     * Set cache headers
     * 
     * @param array $meta Cache metadata
     * @since 2.0.0
     */
    private function set_cache_headers(array $meta): void {
        // Set content type
        if (!empty($meta['content_type'])) {
            header('Content-Type: ' . $meta['content_type']);
        }
        
        // Set cache control headers
        $max_age = $this->settings['browser_cache_lifetime'] ?? 86400; // 24 hours default
        header('Cache-Control: public, max-age=' . $max_age);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $max_age) . ' GMT');
        
        // Set ETag
        if (!empty($meta['etag'])) {
            header('ETag: "' . $meta['etag'] . '"');
        }
        
        // Set Last-Modified
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $meta['timestamp']) . ' GMT');
        
        // Add custom headers
        header('X-Cache: HIT');
        header('X-Cache-Time: ' . date('c', $meta['timestamp']));
    }
    
    /**
     * Maybe cache current page
     * 
     * @since 2.0.0
     */
    public function maybe_cache_page(): void {
        if (!$this->should_cache_request()) {
            return;
        }
        
        // Don't cache if there were any errors
        if (http_response_code() !== 200) {
            return;
        }
        
        // Get output buffer content
        $content = ob_get_contents();
        if (empty($content)) {
            return;
        }
        
        // Don't cache if content contains dynamic elements
        if ($this->contains_dynamic_content($content)) {
            return;
        }
        
        $cache_key = $this->generate_cache_key();
        $this->store_cache($cache_key, $content);
        
        // Update statistics
        $this->stats['misses']++;
        update_option('wpso_cache_stats', $this->stats);
    }
    
    /**
     * Check if content contains dynamic elements
     * 
     * @param string $content Page content
     * @return bool
     * @since 2.0.0
     */
    private function contains_dynamic_content(string $content): bool {
        $dynamic_patterns = [
            '/<form[^>]*method=["\']post["\'][^>]*>/',
            '/wp_nonce_field/',
            '/nonce.*value/',
            '/csrfmiddlewaretoken/',
            '/<input[^>]*name=["\']_token["\'][^>]*>/'
        ];
        
        foreach ($dynamic_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Store content in cache
     * 
     * @param string $key Cache key
     * @param string $content Content to cache
     * @since 2.0.0
     */
    private function store_cache(string $key, string $content): void {
        $cache_file = $this->get_cache_file_path($key);
        $meta_file = $this->get_cache_meta_path($key);
        
        // Prepare metadata
        $meta = [
            'timestamp' => time(),
            'url' => $this->get_current_url(),
            'size' => strlen($content),
            'etag' => md5($content),
            'content_type' => 'text/html; charset=UTF-8',
            'mobile' => wp_is_mobile(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'compression' => 'none'
        ];
        
        // Compress content if enabled
        if ($this->settings['cache_compression'] ?? true) {
            $compressed = gzencode($content, 9);
            if ($compressed && strlen($compressed) < strlen($content)) {
                $content = $compressed;
                $meta['compression'] = 'gzip';
            }
        }
        
        // Store cache file
        if (file_put_contents($cache_file, $content, LOCK_EX) !== false) {
            file_put_contents($meta_file, json_encode($meta), LOCK_EX);
            
            // Update cache statistics
            $this->update_cache_stats($cache_file, $meta);
        }
    }
    
    /**
     * Update cache statistics
     * 
     * @param string $cache_file Cache file path
     * @param array $meta Cache metadata
     * @since 2.0.0
     */
    private function update_cache_stats(string $cache_file, array $meta): void {
        $this->stats['files']++;
        $this->stats['size'] += filesize($cache_file);
        update_option('wpso_cache_stats', $this->stats);
    }
    
    /**
     * Clear all cache
     * 
     * @since 2.0.0
     */
    public function clear_all_cache(): void {
        $this->remove_directory_recursive($this->cache_dir);
        wp_mkdir_p($this->cache_dir);
        $this->create_cache_htaccess();
        
        // Reset statistics
        $this->stats = [
            'hits' => 0,
            'misses' => 0,
            'size' => 0,
            'files' => 0,
            'last_cleanup' => time()
        ];
        update_option('wpso_cache_stats', $this->stats);
        
        // Clear object cache if available
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        do_action('wpso_cache_cleared');
    }
    
    /**
     * Invalidate post cache
     * 
     * @param int $post_id Post ID
     * @since 2.0.0
     */
    public function invalidate_post_cache(int $post_id): void {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }
        
        // Get post URL and related URLs
        $urls_to_invalidate = [
            get_permalink($post_id),
            home_url('/'),
            get_post_type_archive_link($post->post_type)
        ];
        
        // Add category and tag archives
        if ($post->post_type === 'post') {
            $categories = get_the_category($post_id);
            foreach ($categories as $category) {
                $urls_to_invalidate[] = get_category_link($category->term_id);
            }
            
            $tags = get_the_tags($post_id);
            if ($tags) {
                foreach ($tags as $tag) {
                    $urls_to_invalidate[] = get_tag_link($tag->term_id);
                }
            }
        }
        
        // Add author archive
        $urls_to_invalidate[] = get_author_posts_url($post->post_author);
        
        // Add date archives
        $post_date = get_the_date('Y-m-d', $post_id);
        $date_parts = explode('-', $post_date);
        $urls_to_invalidate[] = get_year_link($date_parts[0]);
        $urls_to_invalidate[] = get_month_link($date_parts[0], $date_parts[1]);
        $urls_to_invalidate[] = get_day_link($date_parts[0], $date_parts[1], $date_parts[2]);
        
        // Remove duplicates and invalidate
        $urls_to_invalidate = array_unique($urls_to_invalidate);
        foreach ($urls_to_invalidate as $url) {
            $this->invalidate_url_cache($url);
        }
    }
    
    /**
     * Invalidate comment cache
     * 
     * @param string $new_status New comment status
     * @param string $old_status Old comment status
     * @param WP_Comment $comment Comment object
     * @since 2.0.0
     */
    public function invalidate_comment_cache(string $new_status, string $old_status, $comment): void {
        if ($new_status === $old_status) {
            return;
        }
        
        // Invalidate post cache
        $this->invalidate_post_cache($comment->comment_post_ID);
    }
    
    /**
     * Invalidate cache for specific URL
     * 
     * @param string $url URL to invalidate
     * @since 2.0.0
     */
    public function invalidate_url_cache(string $url): void {
        $cache_key = md5($url);
        $cache_file = $this->get_cache_file_path($cache_key);
        $meta_file = $this->get_cache_meta_path($cache_key);
        
        if (file_exists($cache_file)) {
            unlink($cache_file);
        }
        
        if (file_exists($meta_file)) {
            unlink($meta_file);
        }
        
        // Also invalidate mobile version
        if ($this->mobile_cache) {
            $mobile_key = $cache_key . '_mobile';
            $mobile_cache_file = $this->get_cache_file_path($mobile_key);
            $mobile_meta_file = $this->get_cache_meta_path($mobile_key);
            
            if (file_exists($mobile_cache_file)) {
                unlink($mobile_cache_file);
            }
            
            if (file_exists($mobile_meta_file)) {
                unlink($mobile_meta_file);
            }
        }
    }
    
    /**
     * Preload cache for important pages
     * 
     * @since 2.0.0
     */
    public function preload_cache(): void {
        $urls_to_preload = $this->get_preload_urls();
        
        foreach ($urls_to_preload as $url) {
            $this->preload_url($url);
            
            // Prevent timeout
            if (function_exists('set_time_limit')) {
                set_time_limit(30);
            }
            
            // Small delay to prevent server overload
            usleep(100000); // 0.1 second
        }
    }
    
    /**
     * Get URLs to preload
     * 
     * @return array
     * @since 2.0.0
     */
    private function get_preload_urls(): array {
        $urls = [
            home_url('/'),
            get_permalink(get_option('page_on_front')),
            get_permalink(get_option('page_for_posts'))
        ];
        
        // Add recent posts
        $recent_posts = get_posts([
            'numberposts' => 10,
            'post_type' => 'post',
            'post_status' => 'publish'
        ]);
        
        foreach ($recent_posts as $post) {
            $urls[] = get_permalink($post->ID);
        }
        
        // Add important pages
        $important_pages = get_posts([
            'numberposts' => 10,
            'post_type' => 'page',
            'post_status' => 'publish',
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ]);
        
        foreach ($important_pages as $page) {
            $urls[] = get_permalink($page->ID);
        }
        
        // Remove duplicates and filter
        $urls = array_unique(array_filter($urls));
        
        return apply_filters('wpso_preload_urls', $urls);
    }
    
    /**
     * Preload specific URL
     * 
     * @param string $url URL to preload
     * @since 2.0.0
     */
    private function preload_url(string $url): void {
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'user-agent' => 'WP Speed Optimization Cache Preloader',
            'headers' => [
                'Cache-Control' => 'no-cache'
            ]
        ]);
        
        if (is_wp_error($response)) {
            error_log('WP Speed Optimization: Failed to preload ' . $url . ' - ' . $response->get_error_message());
        }
    }
    
    /**
     * Clean expired cache files
     * 
     * @since 2.0.0
     */
    public function clean_expired_cache(): void {
        $cache_lifetime = $this->settings['cache_lifetime'] ?? 3600;
        $current_time = time();
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->cache_dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'meta') {
                $meta_content = file_get_contents($file->getPathname());
                $meta = json_decode($meta_content, true);
                
                if ($meta && ($current_time - $meta['timestamp']) > $cache_lifetime) {
                    // Remove both cache file and meta file
                    $cache_file = str_replace('.meta', '', $file->getPathname());
                    if (file_exists($cache_file)) {
                        unlink($cache_file);
                    }
                    unlink($file->getPathname());
                }
            }
        }
        
        // Update last cleanup time
        $this->stats['last_cleanup'] = $current_time;
        update_option('wpso_cache_stats', $this->stats);
    }
    
    /**
     * Get cache statistics
     * 
     * @return array
     * @since 2.0.0
     */
    public function get_cache_stats(): array {
        // Recalculate current size and file count
        $size = 0;
        $files = 0;
        
        if (file_exists($this->cache_dir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->cache_dir)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                    $files++;
                }
            }
        }
        
        $this->stats['size'] = $size;
        $this->stats['files'] = $files;
        
        // Calculate hit ratio
        $total_requests = $this->stats['hits'] + $this->stats['misses'];
        $hit_ratio = $total_requests > 0 ? ($this->stats['hits'] / $total_requests) * 100 : 0;
        
        return array_merge($this->stats, [
            'hit_ratio' => round($hit_ratio, 2),
            'size_formatted' => size_format($size),
            'cache_dir' => $this->cache_dir
        ]);
    }
    
    /**
     * Create cache .htaccess file
     * 
     * @since 2.0.0
     */
    private function create_cache_htaccess(): void {
        $htaccess_content = "# WP Speed Optimization Cache Protection\n";
        $htaccess_content .= "Options -Indexes\n";
        $htaccess_content .= "Deny from all\n";
        $htaccess_content .= "<Files \"*.html\">\n";
        $htaccess_content .= "Allow from all\n";
        $htaccess_content .= "</Files>\n";
        
        file_put_contents($this->cache_dir . '.htaccess', $htaccess_content);
    }
    
    /**
     * Remove directory recursively
     * 
     * @param string $dir Directory path
     * @since 2.0.0
     */
    private function remove_directory_recursive(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->remove_directory_recursive($path) : unlink($path);
        }
        rmdir($dir);
    }
    
    /**
     * Get cache size limit
     * 
     * @return int Size limit in bytes
     * @since 2.0.0
     */
    public function get_cache_size_limit(): int {
        return $this->settings['cache_size_limit'] ?? (1024 * 1024 * 1024); // 1GB default
    }
    
    /**
     * Check if cache size limit is exceeded
     * 
     * @return bool
     * @since 2.0.0
     */
    public function is_cache_size_limit_exceeded(): bool {
        return $this->stats['size'] > $this->get_cache_size_limit();
    }
    
    /**
     * Cleanup old cache files when size limit is exceeded
     * 
     * @since 2.0.0
     */
    public function cleanup_old_cache(): void {
        if (!$this->is_cache_size_limit_exceeded()) {
            return;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->cache_dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        $files = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'html') {
                $files[] = [
                    'path' => $file->getPathname(),
                    'mtime' => $file->getMTime(),
                    'size' => $file->getSize()
                ];
            }
        }
        
        // Sort by modification time (oldest first)
        usort($files, function($a, $b) {
            return $a['mtime'] - $b['mtime'];
        });
        
        // Remove oldest files until under limit
        $removed_size = 0;
        $target_size = $this->get_cache_size_limit() * 0.8; // Remove until 80% of limit
        
        foreach ($files as $file) {
            if (($this->stats['size'] - $removed_size) <= $target_size) {
                break;
            }
            
            unlink($file['path']);
            
            // Also remove meta file
            $meta_file = $file['path'] . '.meta';
            if (file_exists($meta_file)) {
                unlink($meta_file);
            }
            
            $removed_size += $file['size'];
        }
        
        // Update statistics
        $this->stats['size'] -= $removed_size;
        update_option('wpso_cache_stats', $this->stats);
    }
}