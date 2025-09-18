<?php
/**
 * WP Speed Optimization - CSS Optimizer
 * 
 * Advanced CSS optimization with:
 * - Critical CSS generation and inlining
 * - Unused CSS removal
 * - CSS minification and combining
 * - Async CSS loading
 * - Google Fonts optimization
 * 
 * @package WP_Speed_Optimization
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

class WPSO_CSS_Optimizer {
    
    /**
     * Plugin settings
     * 
     * @var array
     */
    private $settings;
    
    /**
     * CSS cache directory
     * 
     * @var string
     */
    private $css_cache_dir;
    
    /**
     * Critical CSS directory
     * 
     * @var string
     */
    private $critical_css_dir;
    
    /**
     * Registered stylesheets
     * 
     * @var array
     */
    private $stylesheets = [];
    
    /**
     * Critical CSS content
     * 
     * @var string
     */
    private $critical_css = '';
    
    /**
     * Constructor
     * 
     * @param array $settings Plugin settings
     * @since 2.0.0
     */
    public function __construct(array $settings = []) {
        $this->settings = $settings;
        $this->init_css_optimization();
    }
    
    /**
     * Initialize CSS optimization
     * 
     * @since 2.0.0
     */
    private function init_css_optimization(): void {
        $upload_dir = wp_upload_dir();
        $this->css_cache_dir = $upload_dir['basedir'] . '/wp-speed-optimization/css/';
        $this->critical_css_dir = $upload_dir['basedir'] . '/wp-speed-optimization/critical-css/';
        
        // Create directories
        wp_mkdir_p($this->css_cache_dir);
        wp_mkdir_p($this->critical_css_dir);
        
        // Setup hooks
        if (!is_admin()) {
            add_action('wp_enqueue_scripts', [$this, 'capture_stylesheets'], 999);
            add_action('wp_head', [$this, 'output_critical_css'], 1);
            add_action('wp_footer', [$this, 'defer_non_critical_css'], 999);
            
            // Remove unnecessary CSS
            add_action('wp_enqueue_scripts', [$this, 'remove_unused_wp_css'], 1);
            
            // Optimize Google Fonts
            if ($this->settings['optimize_google_fonts'] ?? false) {
                add_action('wp_enqueue_scripts', [$this, 'optimize_google_fonts'], 1);
            }
        }
        
        // Handle CSS combining
        if ($this->settings['combine_css'] ?? false) {
            add_action('wp_print_styles', [$this, 'combine_css_files'], 999);
        }
    }
    
    /**
     * Capture registered stylesheets
     * 
     * @since 2.0.0
     */
    public function capture_stylesheets(): void {
        global $wp_styles;
        
        if (!$wp_styles instanceof WP_Styles) {
            return;
        }
        
        foreach ($wp_styles->queue as $handle) {
            if (!isset($wp_styles->registered[$handle])) {
                continue;
            }
            
            $style = $wp_styles->registered[$handle];
            
            $this->stylesheets[$handle] = [
                'src' => $style->src,
                'deps' => $style->deps,
                'ver' => $style->ver,
                'media' => $style->args,
                'extra' => $wp_styles->get_data($handle, 'after') ?? [],
                'critical' => $this->is_critical_css($handle)
            ];
        }
    }
    
    /**
     * Check if CSS is critical
     * 
     * @param string $handle CSS handle
     * @return bool
     * @since 2.0.0
     */
    private function is_critical_css(string $handle): bool {
        $critical_handles = apply_filters('wpso_critical_css_handles', [
            'wp-block-library',
            'wp-block-library-theme',
            'global-styles',
            'classic-theme-styles',
            get_template() . '-style',
            get_stylesheet() . '-style'
        ]);
        
        return in_array($handle, $critical_handles) || 
               strpos($handle, 'critical') !== false ||
               strpos($handle, 'above-fold') !== false;
    }
    
    /**
     * Output critical CSS
     * 
     * @since 2.0.0
     */
    public function output_critical_css(): void {
        if (!($this->settings['critical_css'] ?? false)) {
            return;
        }
        
        $critical_css = $this->get_critical_css();
        
        if (!empty($critical_css)) {
            echo '<style id="wpso-critical-css">' . "\n";
            echo $this->minify_css($critical_css);
            echo "\n" . '</style>' . "\n";
        }
    }
    
    /**
     * Get critical CSS content
     * 
     * @return string
     * @since 2.0.0
     */
    private function get_critical_css(): string {
        if (!empty($this->critical_css)) {
            return $this->critical_css;
        }
        
        // Try to load from cache
        $cache_key = $this->get_critical_css_cache_key();
        $cache_file = $this->critical_css_dir . $cache_key . '.css';
        
        if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 86400) { // 24 hours
            $this->critical_css = file_get_contents($cache_file);
            return $this->critical_css;
        }
        
        // Generate critical CSS
        $this->critical_css = $this->generate_critical_css();
        
        // Cache the result
        if (!empty($this->critical_css)) {
            file_put_contents($cache_file, $this->critical_css, LOCK_EX);
        }
        
        return $this->critical_css;
    }
    
    /**
     * Generate critical CSS cache key
     * 
     * @return string
     * @since 2.0.0
     */
    private function get_critical_css_cache_key(): string {
        $url = $this->get_current_url();
        $template = get_template();
        $stylesheet = get_stylesheet();
        
        return md5($url . $template . $stylesheet . wp_get_theme()->get('Version'));
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
     * Generate critical CSS
     * 
     * @return string
     * @since 2.0.0
     */
    public function generate_critical_css(): string {
        $critical_css = '';
        
        // Method 1: Extract from critical CSS files
        foreach ($this->stylesheets as $handle => $style) {
            if ($style['critical']) {
                $css_content = $this->get_css_content($style['src']);
                if ($css_content) {
                    $critical_css .= $this->extract_above_fold_css($css_content);
                }
            }
        }
        
        // Method 2: Use predefined critical CSS rules
        $critical_css .= $this->get_predefined_critical_css();
        
        // Method 3: Auto-generate based on common patterns (fallback)
        if (empty($critical_css)) {
            $critical_css = $this->generate_fallback_critical_css();
        }
        
        return $this->minify_css($critical_css);
    }
    
    /**
     * Get CSS content from URL
     * 
     * @param string $url CSS URL
     * @return string
     * @since 2.0.0
     */
    private function get_css_content(string $url): string {
        // Convert relative URLs to absolute
        if (strpos($url, '//') === 0) {
            $url = (is_ssl() ? 'https:' : 'http:') . $url;
        } elseif (strpos($url, '/') === 0) {
            $url = home_url($url);
        }
        
        // Check if it's a local file
        $parsed_url = parse_url($url);
        $site_parsed_url = parse_url(home_url());
        
        if ($parsed_url['host'] === $site_parsed_url['host']) {
            // Local file - read directly
            $file_path = ABSPATH . ltrim($parsed_url['path'], '/');
            if (file_exists($file_path)) {
                return file_get_contents($file_path);
            }
        }
        
        // Remote file - fetch with caching
        $cache_key = md5($url);
        $cache_file = $this->css_cache_dir . $cache_key . '.css';
        
        if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 3600) { // 1 hour
            return file_get_contents($cache_file);
        }
        
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'user-agent' => 'WP Speed Optimization CSS Optimizer'
        ]);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $content = wp_remote_retrieve_body($response);
            file_put_contents($cache_file, $content, LOCK_EX);
            return $content;
        }
        
        return '';
    }
    
    /**
     * Extract above-the-fold CSS
     * 
     * @param string $css_content CSS content
     * @return string
     * @since 2.0.0
     */
    private function extract_above_fold_css(string $css_content): string {
        $critical_selectors = [
            // Layout and typography
            'body', 'html', 'main', 'header', 'nav', 'h1', 'h2', 'h3', 'p', 'a',
            // WordPress specific
            '.site-header', '.site-title', '.site-description', '.main-navigation',
            '.entry-header', '.entry-title', '.entry-content', '.wp-block-*',
            // Common theme classes
            '.header', '.navigation', '.menu', '.logo', '.hero', '.banner',
            '.content', '.main', '.primary', '.secondary', '.sidebar',
            // Visibility and layout
            '.screen-reader-text', '.sr-only', '.visuallyhidden', '.hidden',
            '.show', '.hide', '.visible', '.invisible'
        ];
        
        $critical_css = '';
        $lines = explode("\n", $css_content);
        $in_critical_block = false;
        $current_block = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Check if line contains critical selector
            foreach ($critical_selectors as $selector) {
                if (strpos($line, $selector) !== false && strpos($line, '{') !== false) {
                    $in_critical_block = true;
                    break;
                }
            }
            
            if ($in_critical_block) {
                $current_block .= $line . "\n";
                
                // Check for end of block
                if (strpos($line, '}') !== false) {
                    $critical_css .= $current_block;
                    $current_block = '';
                    $in_critical_block = false;
                }
            }
        }
        
        return $critical_css;
    }
    
    /**
     * Get predefined critical CSS
     * 
     * @return string
     * @since 2.0.0
     */
    private function get_predefined_critical_css(): string {
        return '
        /* Core Critical CSS */
        html { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
        body { margin: 0; padding: 0; line-height: 1.6; }
        h1, h2, h3, h4, h5, h6 { font-weight: bold; line-height: 1.2; margin: 0 0 1rem; }
        p { margin: 0 0 1rem; }
        a { color: #0073aa; text-decoration: none; }
        a:hover { text-decoration: underline; }
        
        /* Layout */
        .wp-site-blocks { display: block; }
        .wp-block-group { box-sizing: border-box; }
        
        /* WordPress Core Blocks */
        .wp-block-image { margin: 0 0 1em; }
        .wp-block-image img { height: auto; max-width: 100%; }
        .wp-block-heading { margin: 0 0 1rem; }
        .wp-block-paragraph { margin: 0 0 1rem; }
        
        /* Screen Reader Text */
        .screen-reader-text { clip: rect(1px, 1px, 1px, 1px); position: absolute !important; height: 1px; width: 1px; overflow: hidden; }
        
        /* Basic responsive */
        @media (max-width: 768px) {
            body { font-size: 16px; }
            h1 { font-size: 1.8em; }
            h2 { font-size: 1.5em; }
        }
        ';
    }
    
    /**
     * Generate fallback critical CSS
     * 
     * @return string
     * @since 2.0.0
     */
    private function generate_fallback_critical_css(): string {
        // This would be a more sophisticated fallback
        // For now, return the predefined critical CSS
        return $this->get_predefined_critical_css();
    }
    
    /**
     * Defer non-critical CSS
     * 
     * @since 2.0.0
     */
    public function defer_non_critical_css(): void {
        if (!($this->settings['defer_css'] ?? false)) {
            return;
        }
        
        ?>
        <script>
        (function() {
            'use strict';
            
            // Load non-critical CSS asynchronously
            function loadCSS(href, before, media, callback) {
                var doc = window.document;
                var ss = doc.createElement('link');
                var ref;
                
                if (before) {
                    ref = before;
                } else {
                    var refs = (doc.body || doc.getElementsByTagName('head')[0]).childNodes;
                    ref = refs[refs.length - 1];
                }
                
                var sheets = doc.styleSheets;
                
                if (callback) {
                    ss.onload = callback;
                }
                
                ss.rel = 'stylesheet';
                ss.href = href;
                ss.media = 'only x';
                
                function ready(cb) {
                    if (doc.body) {
                        return cb();
                    }
                    setTimeout(function() {
                        ready(cb);
                    });
                }
                
                ready(function() {
                    ref.parentNode.insertBefore(ss, (before ? ref : ref.nextSibling));
                });
                
                var onloadcssdefined = function(ss, cb) {
                    var resolvedHref = ss.href;
                    var i = sheets.length;
                    while (i--) {
                        if (sheets[i].href === resolvedHref) {
                            return cb();
                        }
                    }
                    setTimeout(function() {
                        onloadcssdefined(ss, cb);
                    });
                };
                
                function loadCB() {
                    if (ss.addEventListener) {
                        ss.removeEventListener('load', loadCB);
                    }
                    ss.media = media || 'all';
                }
                
                if (ss.addEventListener) {
                    ss.addEventListener('load', loadCB);
                }
                ss.onloadcssdefined = onloadcssdefined;
                onloadcssdefined(ss, loadCB);
                
                return ss;
            }
            
            // Load deferred stylesheets
            var deferredStyles = [
                <?php $this->output_deferred_styles_js_array(); ?>
            ];
            
            deferredStyles.forEach(function(style) {
                loadCSS(style.href, null, style.media);
            });
            
            // Preload fonts with font-display: swap
            var fontLinks = document.querySelectorAll('link[rel="preload"][as="font"]');
            fontLinks.forEach(function(link) {
                if (!link.crossOrigin) {
                    link.crossOrigin = 'anonymous';
                }
            });
        })();
        </script>
        
        <noscript>
            <?php $this->output_noscript_styles(); ?>
        </noscript>
        <?php
    }
    
    /**
     * Output deferred styles JavaScript array
     * 
     * @since 2.0.0
     */
    private function output_deferred_styles_js_array(): void {
        $deferred_styles = [];
        
        foreach ($this->stylesheets as $handle => $style) {
            if (!$style['critical']) {
                $deferred_styles[] = [
                    'href' => $style['src'],
                    'media' => $style['media'] ?: 'all'
                ];
            }
        }
        
        foreach ($deferred_styles as $i => $style) {
            if ($i > 0) echo ', ';
            echo '{"href":"' . esc_js($style['href']) . '","media":"' . esc_js($style['media']) . '"}';
        }
    }
    
    /**
     * Output noscript styles for fallback
     * 
     * @since 2.0.0
     */
    private function output_noscript_styles(): void {
        foreach ($this->stylesheets as $handle => $style) {
            if (!$style['critical']) {
                printf(
                    '<link rel="stylesheet" href="%s" media="%s">%s',
                    esc_url($style['src']),
                    esc_attr($style['media'] ?: 'all'),
                    "\n"
                );
            }
        }
    }
    
    /**
     * Remove unused WordPress CSS
     * 
     * @since 2.0.0
     */
    public function remove_unused_wp_css(): void {
        if (!($this->settings['remove_unused_css'] ?? false)) {
            return;
        }
        
        // Remove block library CSS if not using blocks
        if (!has_blocks()) {
            wp_dequeue_style('wp-block-library');
            wp_dequeue_style('wp-block-library-theme');
            wp_dequeue_style('wc-blocks-style'); // WooCommerce blocks
        }
        
        // Remove classic theme styles if using block theme
        if (wp_is_block_theme()) {
            wp_dequeue_style('classic-theme-styles');
        }
        
        // Remove global styles if not needed
        if (!wp_theme_has_theme_json()) {
            wp_dequeue_style('global-styles');
        }
        
        // Remove emoji styles
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('admin_print_styles', 'print_emoji_styles');
        
        // Remove dashicons for non-logged users
        if (!is_user_logged_in()) {
            wp_dequeue_style('dashicons');
        }
    }
    
    /**
     * Optimize Google Fonts
     * 
     * @since 2.0.0
     */
    public function optimize_google_fonts(): void {
        global $wp_styles;
        
        if (!$wp_styles instanceof WP_Styles) {
            return;
        }
        
        $google_fonts_handles = [];
        
        // Find Google Fonts
        foreach ($wp_styles->registered as $handle => $style) {
            if (strpos($style->src, 'fonts.googleapis.com') !== false) {
                $google_fonts_handles[] = $handle;
            }
        }
        
        if (empty($google_fonts_handles)) {
            return;
        }
        
        // Combine Google Fonts requests
        $combined_url = $this->combine_google_fonts($google_fonts_handles);
        
        if ($combined_url) {
            // Remove individual font requests
            foreach ($google_fonts_handles as $handle) {
                wp_dequeue_style($handle);
            }
            
            // Add optimized Google Fonts
            wp_enqueue_style('wpso-google-fonts-optimized', $combined_url, [], null);
            
            // Add preconnect for Google Fonts
            add_action('wp_head', function() {
                echo '<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>' . "\n";
                echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
            }, 1);
        }
    }
    
    /**
     * Combine Google Fonts requests
     * 
     * @param array $handles Google Fonts handles
     * @return string|null
     * @since 2.0.0
     */
    private function combine_google_fonts(array $handles): ?string {
        global $wp_styles;
        
        $families = [];
        
        foreach ($handles as $handle) {
            $style = $wp_styles->registered[$handle];
            $url = $style->src;
            
            // Parse Google Fonts URL
            $parsed = parse_url($url);
            if (isset($parsed['query'])) {
                parse_str($parsed['query'], $params);
                if (isset($params['family'])) {
                    $families[] = $params['family'];
                }
            }
        }
        
        if (empty($families)) {
            return null;
        }
        
        // Combine families and add display=swap
        $combined_families = implode('|', array_unique($families));
        
        return add_query_arg([
            'family' => $combined_families,
            'display' => 'swap'
        ], 'https://fonts.googleapis.com/css');
    }
    
    /**
     * Combine CSS files
     * 
     * @since 2.0.0
     */
    public function combine_css_files(): void {
        global $wp_styles;
        
        if (!$wp_styles instanceof WP_Styles) {
            return;
        }
        
        $combinable_styles = [];
        
        foreach ($wp_styles->queue as $handle) {
            if (!isset($wp_styles->registered[$handle])) {
                continue;
            }
            
            $style = $wp_styles->registered[$handle];
            
            // Only combine local CSS files
            if ($this->is_local_css($style->src) && !$this->is_critical_css($handle)) {
                $combinable_styles[$handle] = $style;
            }
        }
        
        if (count($combinable_styles) < 2) {
            return; // Not worth combining
        }
        
        $combined_hash = $this->get_combined_css_hash($combinable_styles);
        $combined_file = $this->css_cache_dir . 'combined-' . $combined_hash . '.css';
        $combined_url = wp_upload_dir()['baseurl'] . '/wp-speed-optimization/css/combined-' . $combined_hash . '.css';
        
        // Generate combined file if it doesn't exist
        if (!file_exists($combined_file)) {
            $this->generate_combined_css_file($combinable_styles, $combined_file);
        }
        
        if (file_exists($combined_file)) {
            // Remove individual styles
            foreach ($combinable_styles as $handle => $style) {
                wp_dequeue_style($handle);
            }
            
            // Add combined style
            wp_enqueue_style('wpso-combined-css', $combined_url, [], filemtime($combined_file));
        }
    }
    
    /**
     * Check if CSS is local
     * 
     * @param string $src CSS source URL
     * @return bool
     * @since 2.0.0
     */
    private function is_local_css(string $src): bool {
        $site_url = parse_url(home_url(), PHP_URL_HOST);
        $css_url = parse_url($src, PHP_URL_HOST);
        
        return $css_url === $site_url || empty($css_url);
    }
    
    /**
     * Get combined CSS hash
     * 
     * @param array $styles Styles to combine
     * @return string
     * @since 2.0.0
     */
    private function get_combined_css_hash(array $styles): string {
        $hash_data = '';
        
        foreach ($styles as $handle => $style) {
            $hash_data .= $handle . $style->src . $style->ver;
        }
        
        return md5($hash_data);
    }
    
    /**
     * Generate combined CSS file
     * 
     * @param array $styles Styles to combine
     * @param string $output_file Output file path
     * @since 2.0.0
     */
    private function generate_combined_css_file(array $styles, string $output_file): void {
        $combined_css = "/* WP Speed Optimization - Combined CSS */\n\n";
        
        foreach ($styles as $handle => $style) {
            $css_content = $this->get_css_content($style->src);
            
            if ($css_content) {
                $combined_css .= "/* {$handle} */\n";
                $combined_css .= $this->process_css_urls($css_content, $style->src);
                $combined_css .= "\n\n";
            }
        }
        
        // Minify combined CSS
        $combined_css = $this->minify_css($combined_css);
        
        file_put_contents($output_file, $combined_css, LOCK_EX);
    }
    
    /**
     * Process CSS URLs to make them absolute
     * 
     * @param string $css_content CSS content
     * @param string $css_url Original CSS URL
     * @return string
     * @since 2.0.0
     */
    private function process_css_urls(string $css_content, string $css_url): string {
        $css_base_url = dirname($css_url);
        
        return preg_replace_callback(
            '/url\s*\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/',
            function($matches) use ($css_base_url) {
                $url = trim($matches[1]);
                
                // Skip data URLs and absolute URLs
                if (strpos($url, 'data:') === 0 || strpos($url, 'http') === 0 || strpos($url, '//') === 0) {
                    return $matches[0];
                }
                
                // Convert relative URLs to absolute
                if (strpos($url, '/') === 0) {
                    $absolute_url = home_url($url);
                } else {
                    $absolute_url = $css_base_url . '/' . $url;
                }
                
                return 'url(' . $absolute_url . ')';
            },
            $css_content
        );
    }
    
    /**
     * Minify CSS
     * 
     * @param string $css CSS content
     * @return string
     * @since 2.0.0
     */
    public function minify_css(string $css): string {
        if (!($this->settings['minify_css'] ?? true)) {
            return $css;
        }
        
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Remove whitespace
        $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Remove unnecessary whitespace around specific characters
        $css = str_replace([' {', '{ ', ' }', '; ', ': ', ', '], ['{', '{', '}', ';', ':', ','], $css);
        
        // Remove last semicolon before closing brace
        $css = preg_replace('/;(?=\s*})/', '', $css);
        
        // Remove any remaining unnecessary whitespace
        $css = trim($css);
        
        return $css;
    }
    
    /**
     * Preload critical CSS
     * 
     * @since 2.0.0
     */
    public function preload_critical_css(): void {
        if (!($this->settings['critical_css'] ?? false)) {
            return;
        }
        
        $critical_css_url = $this->get_critical_css_url();
        
        if (!empty($critical_css_url)) {
            printf(
                '<link rel="preload" as="style" href="%s">%s',
                esc_url($critical_css_url),
                "\n"
            );
        }
    }
    
    /**
     * Get critical CSS URL
     * 
     * @return string
     * @since 2.0.0
     */
    private function get_critical_css_url(): string {
        $upload_dir = wp_upload_dir();
        $cache_key = $this->get_critical_css_cache_key();
        
        return $upload_dir['baseurl'] . '/wp-speed-optimization/critical-css/' . $cache_key . '.css';
    }
    
    /**
     * Remove unused CSS (advanced)
     * 
     * @param string $css CSS content
     * @return string
     * @since 2.0.0
     */
    public function remove_unused_css_advanced(string $css): string {
        // This would implement more advanced unused CSS removal
        // For now, return the CSS as-is
        // Full implementation would require DOM analysis
        return $css;
    }
    
    /**
     * Get CSS optimization stats
     * 
     * @return array
     * @since 2.0.0
     */
    public function get_optimization_stats(): array {
        $stats = [
            'critical_css_files' => 0,
            'combined_css_files' => 0,
            'total_savings' => 0,
            'cache_size' => 0
        ];
        
        // Count critical CSS files
        if (is_dir($this->critical_css_dir)) {
            $critical_files = glob($this->critical_css_dir . '*.css');
            $stats['critical_css_files'] = count($critical_files);
        }
        
        // Count combined CSS files
        if (is_dir($this->css_cache_dir)) {
            $combined_files = glob($this->css_cache_dir . 'combined-*.css');
            $stats['combined_css_files'] = count($combined_files);
            
            // Calculate cache size
            foreach ($combined_files as $file) {
                $stats['cache_size'] += filesize($file);
            }
        }
        
        $stats['cache_size_formatted'] = size_format($stats['cache_size']);
        
        return $stats;
    }
    
    /**
     * Clear CSS cache
     * 
     * @since 2.0.0
     */
    public function clear_css_cache(): void {
        // Clear combined CSS cache
        $combined_files = glob($this->css_cache_dir . 'combined-*.css');
        foreach ($combined_files as $file) {
            unlink($file);
        }
        
        // Clear critical CSS cache
        $critical_files = glob($this->critical_css_dir . '*.css');
        foreach ($critical_files as $file) {
            unlink($file);
        }
        
        // Clear CSS content cache
        $cache_files = glob($this->css_cache_dir . '*.css');
        foreach ($cache_files as $file) {
            if (strpos(basename($file), 'combined-') !== 0) {
                unlink($file);
            }
        }
    }
}