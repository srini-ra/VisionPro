<?php
/**
 * WP Speed Optimization - Core Web Vitals Optimizer
 * 
 * Advanced Core Web Vitals optimization focusing on:
 * - Largest Contentful Paint (LCP)
 * - First Input Delay (FID) 
 * - Cumulative Layout Shift (CLS)
 * - First Contentful Paint (FCP)
 * - Time to First Byte (TTFB)
 * 
 * @package WP_Speed_Optimization
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

class WPSO_Core_Web_Vitals {
    
    /**
     * Plugin settings
     * 
     * @var array
     */
    private $settings;
    
    /**
     * Critical resources
     * 
     * @var array
     */
    private $critical_resources = [];
    
    /**
     * LCP candidates
     * 
     * @var array
     */
    private $lcp_candidates = [];
    
    /**
     * Constructor
     * 
     * @param array $settings Plugin settings
     * @since 2.0.0
     */
    public function __construct(array $settings = []) {
        $this->settings = $settings;
        $this->init_core_web_vitals_optimization();
    }
    
    /**
     * Initialize Core Web Vitals optimization
     * 
     * @since 2.0.0
     */
    private function init_core_web_vitals_optimization(): void {
        // LCP Optimization
        add_action('wp_head', [$this, 'optimize_lcp'], 1);
        add_action('wp_head', [$this, 'preload_lcp_resources'], 2);
        
        // FID Optimization
        add_action('wp_footer', [$this, 'optimize_fid'], 999);
        add_filter('script_loader_tag', [$this, 'defer_non_critical_js'], 10, 3);
        
        // CLS Optimization
        add_action('wp_head', [$this, 'prevent_cls'], 3);
        add_filter('wp_get_attachment_image_attributes', [$this, 'add_image_dimensions'], 10, 3);
        
        // FCP Optimization
        add_action('wp_head', [$this, 'optimize_fcp'], 4);
        
        // TTFB Optimization
        add_action('init', [$this, 'optimize_ttfb'], 1);
        
        // Resource hints
        add_action('wp_head', [$this, 'add_resource_hints'], 5);
        
        // Performance monitoring
        if ($this->settings['performance_monitoring'] ?? true) {
            add_action('wp_footer', [$this, 'add_performance_monitoring'], 1000);
        }
    }
    
    /**
     * Optimize Largest Contentful Paint (LCP)
     * 
     * @since 2.0.0
     */
    public function optimize_lcp(): void {
        // Identify potential LCP elements
        $this->identify_lcp_candidates();
        
        // Preload critical fonts for text LCP
        $this->preload_critical_fonts();
        
        // Optimize hero images
        $this->optimize_hero_images();
        
        // Remove render-blocking resources
        $this->remove_render_blocking_css();
    }
    
    /**
     * Identify LCP candidates
     * 
     * @since 2.0.0
     */
    private function identify_lcp_candidates(): void {
        // Get hero/featured image
        if (is_single() || is_page()) {
            global $post;
            if (has_post_thumbnail($post->ID)) {
                $featured_image = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'full');
                if ($featured_image) {
                    $this->lcp_candidates[] = [
                        'type' => 'image',
                        'url' => $featured_image[0],
                        'width' => $featured_image[1],
                        'height' => $featured_image[2],
                        'priority' => 'high'
                    ];
                }
            }
        }
        
        // Get custom header image
        if (has_custom_header() && get_header_image()) {
            $this->lcp_candidates[] = [
                'type' => 'image',
                'url' => get_header_image(),
                'priority' => 'high'
            ];
        }
        
        // Get site logo
        if (has_custom_logo()) {
            $logo_id = get_theme_mod('custom_logo');
            $logo = wp_get_attachment_image_src($logo_id, 'full');
            if ($logo) {
                $this->lcp_candidates[] = [
                    'type' => 'image',
                    'url' => $logo[0],
                    'width' => $logo[1],
                    'height' => $logo[2],
                    'priority' => 'medium'
                ];
            }
        }
        
        // Allow themes/plugins to add LCP candidates
        $this->lcp_candidates = apply_filters('wpso_lcp_candidates', $this->lcp_candidates);
    }
    
    /**
     * Preload LCP resources
     * 
     * @since 2.0.0
     */
    public function preload_lcp_resources(): void {
        foreach ($this->lcp_candidates as $candidate) {
            if ($candidate['type'] === 'image' && $candidate['priority'] === 'high') {
                printf(
                    '<link rel="preload" as="image" href="%s"%s>%s',
                    esc_url($candidate['url']),
                    !empty($candidate['width']) && !empty($candidate['height']) 
                        ? sprintf(' imagesrcset="%s %sw" imagesizes="100vw"', esc_url($candidate['url']), $candidate['width'])
                        : '',
                    "\n"
                );
            }
        }
    }
    
    /**
     * Preload critical fonts
     * 
     * @since 2.0.0
     */
    private function preload_critical_fonts(): void {
        $critical_fonts = $this->get_critical_fonts();
        
        foreach ($critical_fonts as $font) {
            printf(
                '<link rel="preload" as="font" type="font/%s" href="%s" crossorigin>%s',
                esc_attr($font['format']),
                esc_url($font['url']),
                "\n"
            );
        }
    }
    
    /**
     * Get critical fonts
     * 
     * @return array
     * @since 2.0.0
     */
    private function get_critical_fonts(): array {
        $fonts = [];
        
        // Check for Google Fonts
        global $wp_styles;
        if (isset($wp_styles->registered['google-fonts'])) {
            $google_fonts_url = $wp_styles->registered['google-fonts']->src;
            if ($google_fonts_url) {
                // Extract font families and generate local URLs if optimization is enabled
                if ($this->settings['optimize_google_fonts'] ?? false) {
                    $fonts = array_merge($fonts, $this->get_optimized_google_fonts($google_fonts_url));
                }
            }
        }
        
        // Add custom fonts
        $custom_fonts = apply_filters('wpso_critical_fonts', []);
        $fonts = array_merge($fonts, $custom_fonts);
        
        return $fonts;
    }
    
    /**
     * Get optimized Google Fonts
     * 
     * @param string $google_fonts_url Google Fonts URL
     * @return array
     * @since 2.0.0
     */
    private function get_optimized_google_fonts(string $google_fonts_url): array {
        // This would implement Google Fonts optimization
        // For now, return empty array - full implementation would involve
        // downloading and hosting Google Fonts locally
        return [];
    }
    
    /**
     * Optimize hero images
     * 
     * @since 2.0.0
     */
    private function optimize_hero_images(): void {
        // Add fetchpriority="high" to hero images via JavaScript
        // This will be handled in the frontend optimization
    }
    
    /**
     * Remove render-blocking CSS
     * 
     * @since 2.0.0
     */
    private function remove_render_blocking_css(): void {
        if (!($this->settings['critical_css'] ?? false)) {
            return;
        }
        
        // This will work with the CSS optimizer to inline critical CSS
        // and defer non-critical CSS
    }
    
    /**
     * Optimize First Input Delay (FID)
     * 
     * @since 2.0.0
     */
    public function optimize_fid(): void {
        if (!($this->settings['optimize_fid'] ?? true)) {
            return;
        }
        
        // Add JavaScript to optimize FID
        ?>
        <script>
        (function() {
            'use strict';
            
            // Delay non-essential JavaScript until user interaction
            let interacted = false;
            const delayedScripts = [];
            
            function loadDelayedScripts() {
                if (interacted) return;
                interacted = true;
                
                delayedScripts.forEach(function(script) {
                    const newScript = document.createElement('script');
                    Array.from(script.attributes).forEach(function(attr) {
                        newScript.setAttribute(attr.name, attr.value);
                    });
                    newScript.innerHTML = script.innerHTML;
                    script.parentNode.replaceChild(newScript, script);
                });
            }
            
            // Load scripts on user interaction
            ['mousedown', 'mousemove', 'keydown', 'scroll', 'touchstart'].forEach(function(event) {
                document.addEventListener(event, loadDelayedScripts, { once: true, passive: true });
            });
            
            // Fallback: load after 5 seconds
            setTimeout(loadDelayedScripts, 5000);
            
            // Mark scripts for delayed loading
            document.addEventListener('DOMContentLoaded', function() {
                const scriptsToDelay = document.querySelectorAll('script[data-wpso-delay]');
                scriptsToDelay.forEach(function(script) {
                    delayedScripts.push(script);
                    script.type = 'wpso/delay';
                });
            });
            
            // Optimize long tasks
            if ('scheduler' in window && 'postTask' in window.scheduler) {
                // Use modern scheduler API when available
                window.wpsoScheduleTask = function(callback, priority = 'background') {
                    return window.scheduler.postTask(callback, { priority: priority });
                };
            } else {
                // Fallback for older browsers
                window.wpsoScheduleTask = function(callback) {
                    return new Promise(function(resolve) {
                        setTimeout(function() {
                            resolve(callback());
                        }, 0);
                    });
                };
            }
        })();
        </script>
        <?php
    }
    
    /**
     * Defer non-critical JavaScript
     * 
     * @param string $tag Script tag
     * @param string $handle Script handle
     * @param string $src Script source
     * @return string
     * @since 2.0.0
     */
    public function defer_non_critical_js(string $tag, string $handle, string $src): string {
        if (is_admin()) {
            return $tag;
        }
        
        // Don't defer critical scripts
        $critical_scripts = apply_filters('wpso_critical_scripts', [
            'jquery-core',
            'wp-polyfill',
            'regenerator-runtime'
        ]);
        
        if (in_array($handle, $critical_scripts)) {
            return $tag;
        }
        
        // Don't defer inline scripts
        if (empty($src)) {
            return $tag;
        }
        
        // Add delay attribute for non-essential scripts
        $delay_scripts = apply_filters('wpso_delay_scripts', [
            'google-analytics',
            'gtag',
            'facebook-pixel',
            'twitter-widgets',
            'instagram-embed'
        ]);
        
        if (in_array($handle, $delay_scripts)) {
            return str_replace('<script ', '<script data-wpso-delay="true" ', $tag);
        }
        
        // Defer other scripts
        if (strpos($tag, 'defer') === false && strpos($tag, 'async') === false) {
            return str_replace('<script ', '<script defer ', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Prevent Cumulative Layout Shift (CLS)
     * 
     * @since 2.0.0
     */
    public function prevent_cls(): void {
        if (!($this->settings['prevent_cls'] ?? true)) {
            return;
        }
        
        // Add CSS to prevent layout shifts
        ?>
        <style id="wpso-cls-prevention">
        /* Prevent layout shifts */
        img:not([width]):not([height]) {
            height: auto;
        }
        
        /* Reserve space for ads */
        .wpso-ad-placeholder {
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 12px;
        }
        
        /* Prevent font loading shifts */
        @font-face {
            font-display: swap;
        }
        
        /* Skeleton loading for dynamic content */
        .wpso-skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: wpso-skeleton-loading 1.5s infinite;
        }
        
        @keyframes wpso-skeleton-loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        </style>
        <?php
        
        // Add JavaScript for dynamic CLS prevention
        ?>
        <script>
        (function() {
            'use strict';
            
            // Add dimensions to images without them
            function addImageDimensions() {
                const images = document.querySelectorAll('img:not([width]):not([height])');
                images.forEach(function(img) {
                    if (img.naturalWidth && img.naturalHeight) {
                        img.setAttribute('width', img.naturalWidth);
                        img.setAttribute('height', img.naturalHeight);
                    } else {
                        img.addEventListener('load', function() {
                            this.setAttribute('width', this.naturalWidth);
                            this.setAttribute('height', this.naturalHeight);
                        });
                    }
                });
            }
            
            // Reserve space for lazy-loaded content
            function reserveSpaceForLazyContent() {
                const lazyElements = document.querySelectorAll('[data-src], [loading="lazy"]');
                lazyElements.forEach(function(element) {
                    if (!element.style.minHeight && !element.getAttribute('height')) {
                        // Set minimum height based on element type
                        if (element.tagName === 'IMG') {
                            element.style.minHeight = '200px';
                        } else if (element.tagName === 'IFRAME') {
                            element.style.minHeight = '400px';
                        }
                    }
                });
            }
            
            // Initialize on DOM ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    addImageDimensions();
                    reserveSpaceForLazyContent();
                });
            } else {
                addImageDimensions();
                reserveSpaceForLazyContent();
            }
            
            // Handle dynamic content
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList') {
                        addImageDimensions();
                        reserveSpaceForLazyContent();
                    }
                });
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        })();
        </script>
        <?php
    }
    
    /**
     * Add image dimensions to prevent CLS
     * 
     * @param array $attr Image attributes
     * @param WP_Post $attachment Attachment post object
     * @param string $size Image size
     * @return array
     * @since 2.0.0
     */
    public function add_image_dimensions(array $attr, $attachment, $size): array {
        if (empty($attr['width']) || empty($attr['height'])) {
            $image_meta = wp_get_attachment_metadata($attachment->ID);
            if ($image_meta) {
                if (is_array($size)) {
                    $attr['width'] = $size[0];
                    $attr['height'] = $size[1];
                } elseif (isset($image_meta['sizes'][$size])) {
                    $attr['width'] = $image_meta['sizes'][$size]['width'];
                    $attr['height'] = $image_meta['sizes'][$size]['height'];
                } else {
                    $attr['width'] = $image_meta['width'];
                    $attr['height'] = $image_meta['height'];
                }
            }
        }
        
        return $attr;
    }
    
    /**
     * Optimize First Contentful Paint (FCP)
     * 
     * @since 2.0.0
     */
    public function optimize_fcp(): void {
        if (!($this->settings['optimize_fcp'] ?? true)) {
            return;
        }
        
        // Preconnect to external domains
        $external_domains = $this->get_external_domains();
        foreach ($external_domains as $domain) {
            printf(
                '<link rel="preconnect" href="https://%s" crossorigin>%s',
                esc_attr($domain),
                "\n"
            );
        }
        
        // DNS prefetch for additional domains
        $dns_prefetch_domains = apply_filters('wpso_dns_prefetch_domains', [
            'www.google-analytics.com',
            'www.googletagmanager.com',
            'connect.facebook.net',
            'platform.twitter.com',
            'www.youtube.com',
            'cdnjs.cloudflare.com'
        ]);
        
        foreach ($dns_prefetch_domains as $domain) {
            printf(
                '<link rel="dns-prefetch" href="//%s">%s',
                esc_attr($domain),
                "\n"
            );
        }
    }
    
    /**
     * Get external domains for preconnect
     * 
     * @return array
     * @since 2.0.0
     */
    private function get_external_domains(): array {
        $domains = [];
        
        // Check for Google Fonts
        global $wp_styles;
        foreach ($wp_styles->registered as $style) {
            if (strpos($style->src, 'fonts.googleapis.com') !== false) {
                $domains[] = 'fonts.googleapis.com';
                $domains[] = 'fonts.gstatic.com';
                break;
            }
        }
        
        // Check for CDN
        if (!empty($this->settings['cdn_url'])) {
            $cdn_domain = parse_url($this->settings['cdn_url'], PHP_URL_HOST);
            if ($cdn_domain) {
                $domains[] = $cdn_domain;
            }
        }
        
        return array_unique($domains);
    }
    
    /**
     * Optimize Time to First Byte (TTFB)
     * 
     * @since 2.0.0
     */
    public function optimize_ttfb(): void {
        if (!($this->settings['optimize_ttfb'] ?? true)) {
            return;
        }
        
        // Enable output buffering with compression
        if (!ob_get_level() && extension_loaded('zlib') && !headers_sent()) {
            ob_start('ob_gzhandler');
        }
        
        // Set optimal headers
        if (!headers_sent()) {
            // Enable keep-alive
            header('Connection: keep-alive');
            
            // Set cache headers for static assets
            if ($this->is_static_asset()) {
                $expires = 365 * 24 * 60 * 60; // 1 year
                header('Cache-Control: public, max-age=' . $expires);
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT');
            }
        }
    }
    
    /**
     * Check if current request is for a static asset
     * 
     * @return bool
     * @since 2.0.0
     */
    private function is_static_asset(): bool {
        $static_extensions = ['css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'woff', 'woff2', 'ttf', 'eot'];
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $extension = pathinfo(parse_url($request_uri, PHP_URL_PATH), PATHINFO_EXTENSION);
        
        return in_array(strtolower($extension), $static_extensions);
    }
    
    /**
     * Add resource hints
     * 
     * @since 2.0.0
     */
    public function add_resource_hints(): void {
        // Preload critical CSS
        if ($this->settings['critical_css'] ?? false) {
            echo '<link rel="preload" as="style" href="' . esc_url($this->get_critical_css_url()) . '">' . "\n";
        }
        
        // Preload critical JavaScript
        $critical_js = apply_filters('wpso_critical_js', []);
        foreach ($critical_js as $js_url) {
            printf(
                '<link rel="preload" as="script" href="%s">%s',
                esc_url($js_url),
                "\n"
            );
        }
        
        // Module preload for modern JavaScript
        if ($this->settings['modern_js'] ?? false) {
            $modern_js = apply_filters('wpso_modern_js', []);
            foreach ($modern_js as $js_url) {
                printf(
                    '<link rel="modulepreload" href="%s">%s',
                    esc_url($js_url),
                    "\n"
                );
            }
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
        return $upload_dir['baseurl'] . '/wp-speed-optimization/critical-css/critical.css';
    }
    
    /**
     * Add performance monitoring script
     * 
     * @since 2.0.0
     */
    public function add_performance_monitoring(): void {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }
        
        ?>
        <script id="wpso-performance-monitor">
        (function() {
            'use strict';
            
            // Check if browser supports the APIs we need
            if (!('performance' in window) || !('PerformanceObserver' in window)) {
                return;
            }
            
            const vitals = {};
            
            // Measure Core Web Vitals
            function measureCoreWebVitals() {
                // LCP (Largest Contentful Paint)
                new PerformanceObserver(function(list) {
                    const entries = list.getEntries();
                    const lastEntry = entries[entries.length - 1];
                    vitals.lcp = Math.round(lastEntry.startTime);
                }).observe({ entryTypes: ['largest-contentful-paint'] });
                
                // FID (First Input Delay)
                new PerformanceObserver(function(list) {
                    const entries = list.getEntries();
                    entries.forEach(function(entry) {
                        vitals.fid = Math.round(entry.processingStart - entry.startTime);
                    });
                }).observe({ entryTypes: ['first-input'] });
                
                // CLS (Cumulative Layout Shift)
                let clsValue = 0;
                new PerformanceObserver(function(list) {
                    const entries = list.getEntries();
                    entries.forEach(function(entry) {
                        if (!entry.hadRecentInput) {
                            clsValue += entry.value;
                        }
                    });
                    vitals.cls = Math.round(clsValue * 1000) / 1000;
                }).observe({ entryTypes: ['layout-shift'] });
                
                // FCP (First Contentful Paint)
                new PerformanceObserver(function(list) {
                    const entries = list.getEntries();
                    entries.forEach(function(entry) {
                        if (entry.name === 'first-contentful-paint') {
                            vitals.fcp = Math.round(entry.startTime);
                        }
                    });
                }).observe({ entryTypes: ['paint'] });
                
                // TTFB (Time to First Byte)
                const navigation = performance.getEntriesByType('navigation')[0];
                if (navigation) {
                    vitals.ttfb = Math.round(navigation.responseStart - navigation.requestStart);
                }
            }
            
            // Send vitals to server
            function sendVitals() {
                if (Object.keys(vitals).length === 0) {
                    return;
                }
                
                const data = {
                    action: 'wpso_record_vitals',
                    nonce: '<?php echo wp_create_nonce('wpso_vitals_nonce'); ?>',
                    url: window.location.href,
                    vitals: vitals,
                    timestamp: Date.now(),
                    user_agent: navigator.userAgent,
                    connection: navigator.connection ? {
                        effectiveType: navigator.connection.effectiveType,
                        downlink: navigator.connection.downlink,
                        rtt: navigator.connection.rtt
                    } : null
                };
                
                // Use sendBeacon if available, fallback to fetch
                if ('sendBeacon' in navigator) {
                    navigator.sendBeacon('<?php echo admin_url('admin-ajax.php'); ?>', 
                        new URLSearchParams(data));
                } else {
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        body: new URLSearchParams(data),
                        keepalive: true
                    }).catch(function() {
                        // Ignore errors in vitals reporting
                    });
                }
            }
            
            // Initialize measurement
            measureCoreWebVitals();
            
            // Send vitals when page is about to unload
            window.addEventListener('beforeunload', sendVitals);
            
            // Also send after 10 seconds as a fallback
            setTimeout(sendVitals, 10000);
            
            // Expose vitals for debugging
            window.wpsoVitals = vitals;
        })();
        </script>
        <?php
    }
    
    /**
     * Get Core Web Vitals data
     * 
     * @param int $days Number of days to retrieve data for
     * @return array
     * @since 2.0.0
     */
    public function get_vitals_data(int $days = 30): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wpso_performance_log';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE timestamp >= DATE_SUB(NOW(), INTERVAL %d DAY) ORDER BY timestamp DESC",
            $days
        ));
        
        if (!$results) {
            return [];
        }
        
        $vitals_data = [];
        foreach ($results as $result) {
            $vitals_data[] = [
                'url' => $result->page_url,
                'timestamp' => $result->timestamp,
                'load_time' => $result->load_time,
                'vitals' => json_decode($result->vitals_data ?? '{}', true)
            ];
        }
        
        return $vitals_data;
    }
    
    /**
     * Get Core Web Vitals averages
     * 
     * @param int $days Number of days to average
     * @return array
     * @since 2.0.0
     */
    public function get_vitals_averages(int $days = 30): array {
        $vitals_data = $this->get_vitals_data($days);
        
        if (empty($vitals_data)) {
            return [];
        }
        
        $totals = ['lcp' => 0, 'fid' => 0, 'cls' => 0, 'fcp' => 0, 'ttfb' => 0];
        $counts = ['lcp' => 0, 'fid' => 0, 'cls' => 0, 'fcp' => 0, 'ttfb' => 0];
        
        foreach ($vitals_data as $data) {
            if (!empty($data['vitals'])) {
                foreach ($totals as $vital => $total) {
                    if (isset($data['vitals'][$vital]) && $data['vitals'][$vital] > 0) {
                        $totals[$vital] += $data['vitals'][$vital];
                        $counts[$vital]++;
                    }
                }
            }
        }
        
        $averages = [];
        foreach ($totals as $vital => $total) {
            $averages[$vital] = $counts[$vital] > 0 ? round($total / $counts[$vital], 2) : 0;
        }
        
        return $averages;
    }
    
    /**
     * Get performance score based on Core Web Vitals
     * 
     * @param array $vitals Core Web Vitals data
     * @return array
     * @since 2.0.0
     */
    public function get_performance_score(array $vitals): array {
        $scores = [];
        
        // LCP scoring (Good: <2.5s, Needs Improvement: 2.5-4s, Poor: >4s)
        if (isset($vitals['lcp'])) {
            $lcp_seconds = $vitals['lcp'] / 1000;
            if ($lcp_seconds <= 2.5) {
                $scores['lcp'] = ['score' => 90, 'status' => 'good'];
            } elseif ($lcp_seconds <= 4.0) {
                $scores['lcp'] = ['score' => 50, 'status' => 'needs-improvement'];
            } else {
                $scores['lcp'] = ['score' => 0, 'status' => 'poor'];
            }
        }
        
        // FID scoring (Good: <100ms, Needs Improvement: 100-300ms, Poor: >300ms)
        if (isset($vitals['fid'])) {
            if ($vitals['fid'] <= 100) {
                $scores['fid'] = ['score' => 90, 'status' => 'good'];
            } elseif ($vitals['fid'] <= 300) {
                $scores['fid'] = ['score' => 50, 'status' => 'needs-improvement'];
            } else {
                $scores['fid'] = ['score' => 0, 'status' => 'poor'];
            }
        }
        
        // CLS scoring (Good: <0.1, Needs Improvement: 0.1-0.25, Poor: >0.25)
        if (isset($vitals['cls'])) {
            if ($vitals['cls'] <= 0.1) {
                $scores['cls'] = ['score' => 90, 'status' => 'good'];
            } elseif ($vitals['cls'] <= 0.25) {
                $scores['cls'] = ['score' => 50, 'status' => 'needs-improvement'];
            } else {
                $scores['cls'] = ['score' => 0, 'status' => 'poor'];
            }
        }
        
        // FCP scoring (Good: <1.8s, Needs Improvement: 1.8-3s, Poor: >3s)
        if (isset($vitals['fcp'])) {
            $fcp_seconds = $vitals['fcp'] / 1000;
            if ($fcp_seconds <= 1.8) {
                $scores['fcp'] = ['score' => 90, 'status' => 'good'];
            } elseif ($fcp_seconds <= 3.0) {
                $scores['fcp'] = ['score' => 50, 'status' => 'needs-improvement'];
            } else {
                $scores['fcp'] = ['score' => 0, 'status' => 'poor'];
            }
        }
        
        // TTFB scoring (Good: <200ms, Needs Improvement: 200-600ms, Poor: >600ms)
        if (isset($vitals['ttfb'])) {
            if ($vitals['ttfb'] <= 200) {
                $scores['ttfb'] = ['score' => 90, 'status' => 'good'];
            } elseif ($vitals['ttfb'] <= 600) {
                $scores['ttfb'] = ['score' => 50, 'status' => 'needs-improvement'];
            } else {
                $scores['ttfb'] = ['score' => 0, 'status' => 'poor'];
            }
        }
        
        // Overall score
        $total_score = 0;
        $vital_count = count($scores);
        
        foreach ($scores as $score_data) {
            $total_score += $score_data['score'];
        }
        
        $overall_score = $vital_count > 0 ? round($total_score / $vital_count) : 0;
        
        return [
            'overall_score' => $overall_score,
            'vitals' => $scores,
            'status' => $overall_score >= 80 ? 'good' : ($overall_score >= 50 ? 'needs-improvement' : 'poor')
        ];
    }
}