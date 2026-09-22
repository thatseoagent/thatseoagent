<?php
/**
 * Main Lean SEO Class
 *
 * @package Lean_SEO
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Lean_SEO {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load dependencies
     */
    private function load_dependencies() {
        require_once LEAN_SEO_PLUGIN_DIR . 'includes/class-lean-seo-meta.php';
        require_once LEAN_SEO_PLUGIN_DIR . 'includes/class-lean-seo-sitemap.php';
        require_once LEAN_SEO_PLUGIN_DIR . 'includes/class-lean-seo-faq.php';
        require_once LEAN_SEO_PLUGIN_DIR . 'includes/class-lean-seo-schema.php';
        require_once LEAN_SEO_PLUGIN_DIR . 'includes/class-lean-seo-admin.php';
        require_once LEAN_SEO_PLUGIN_DIR . 'includes/class-lean-seo-identity.php';
        require_once LEAN_SEO_PLUGIN_DIR . 'includes/class-lean-seo-homepage.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Meta tags
        add_filter('pre_get_document_title', array($this, 'filter_document_title'), 10);
        add_filter('document_title_separator', array($this, 'title_separator'));
        add_action('wp_head', array($this, 'output_meta_tags'), 1);
        add_action('wp_head', array($this, 'output_canonical'), 1);
        add_action('wp_head', array($this, 'output_schema'), 2);

        // Remove WP default canonical
        remove_action('wp_head', 'rel_canonical');

        // Per-post SEO fields (also exposes them to the REST API / block editor)
        add_action('init', array($this, 'register_meta_fields'));

        // Sitemap
        add_action('init', array($this, 'register_sitemap_routes'), 20);
        add_action('init', array($this, 'maybe_flush_rewrite_rules'), 21);
        add_filter('query_vars', array($this, 'sitemap_query_vars'));
        add_action('template_redirect', array($this, 'handle_sitemap'));

        // Disable canonical redirects for sitemap URLs to prevent redirect chains
        add_filter('redirect_canonical', array($this, 'disable_sitemap_redirect'), 10, 2);

        // Admin
        if (is_admin()) {
            add_action('add_meta_boxes', array($this, 'add_meta_box'));
            add_action('save_post', array($this, 'save_meta'), 10, 1);
            add_action('admin_menu', array('Lean_SEO_Admin', 'add_settings_page'));
            add_action('admin_init', array('Lean_SEO_Admin', 'register_settings'));
            add_action('admin_init', array('Lean_SEO_Identity', 'register'));
            add_action('admin_init', array('Lean_SEO_Homepage', 'register'));
            add_action('admin_enqueue_scripts', array('Lean_SEO_Identity', 'enqueue_assets'));
            add_action('admin_enqueue_scripts', array('Lean_SEO_Admin', 'enqueue_meta_box_assets'));
        }

        // Filter appliers run everywhere (front-end + admin previews).
        Lean_SEO_Identity_Applier::register();
        Lean_SEO_Homepage_Applier::register();

        // Filter robots.txt to include our sitemap
        add_filter('robots_txt', array($this, 'filter_robots_txt'), 999, 2);
    }

    /**
     * Filter robots.txt to include our sitemap
     */
    public function filter_robots_txt($output, $public) {
        // Remove Yoast comment blocks
        $output = preg_replace('/# START YOAST BLOCK.*?# END YOAST BLOCK\s*/s', '', $output);

        // Drop Sitemap: directives that point at this site — ours is appended
        // below, and leftovers from a previous SEO plugin are stale. Sitemap
        // directives for other hosts belong to someone else and are kept.
        $home_host = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
        $lines     = preg_split('/\r\n|\r|\n/', $output);
        $kept      = array();

        foreach ($lines as $line) {
            if (preg_match('/^\s*sitemap\s*:\s*(\S+)/i', $line, $matches)) {
                $host = strtolower((string) wp_parse_url($matches[1], PHP_URL_HOST));
                if ('' === $host || $host === $home_host) {
                    continue;
                }
            }

            $kept[] = $line;
        }

        $output = implode("\n", $kept);
        
        // Trim and add our sitemap
        $output = trim($output);
        if ($output) {
            $output .= "\n\n";
        }
        $output .= "Sitemap: " . home_url('/sitemap.xml') . "\n";
        
        return $output;
    }

    /**
     * Disable canonical redirects for sitemap URLs
     *
     * Prevents WordPress from redirecting sitemap.xml to sitemap.xml/
     * which causes "Sitemap error" in Google Search Console
     *
     * @param string $redirect_url The redirect URL
     * @param string $requested_url The requested URL
     * @return string|false The redirect URL or false to prevent redirect
     */
    public function disable_sitemap_redirect($redirect_url, $requested_url) {
        // Check if this is a sitemap request
        if (preg_match('/sitemap[^?]*\.xml/', $requested_url)) {
            // Prevent canonical redirect for sitemap URLs
            return false;
        }
        return $redirect_url;
    }

    /**
     * Short-circuit the document <title> tag entirely.
     *
     * Returning a non-empty string here bypasses WordPress core's title
     * assembly. We only act if a filter callback provides a value via
     * the `lean_seo_document_title` filter, letting themes/site-specific
     * plugins set the homepage title or override any context without
     * rebuilding the full fallback chain.
     *
     * @since 1.5.0
     * @param string $title Current title (empty string by default).
     * @return string
     */
    public function filter_document_title($title) {
        /**
         * Filter the final <title> tag output.
         *
         * Return a non-empty string to replace WordPress's document title
         * entirely. Return empty (default) to let core + lean_seo build
         * the title normally. This is the only filter that can override
         * the homepage title without touching the theme.
         *
         * @since 1.5.0
         * @param string $title   Empty by default; override with a string to short-circuit.
         * @param string $context Current page context (see Lean_SEO_Meta::get_context()).
         */
        $override = apply_filters('lean_seo_document_title', '', Lean_SEO_Meta::get_context());

        if ('' !== $override) {
            return $override;
        }

        // A custom SEO title replaces the document title outright, rather
        // than only its title part. The field is the full title a site owner
        // wants in search results — it is what the meta box preview shows
        // verbatim — and before 1.12.0 WordPress still appended the site name
        // after it, so "Product | Acme" went out as "Product | Acme | Acme".
        if (is_singular()) {
            $custom_title = Lean_SEO_Post_Seo::get(get_post(), 'title');
            if ($custom_title) {
                return $custom_title;
            }
        }

        return $title;
    }

    /**
     * Title separator
     *
     * @since 1.0.0
     * @since 1.5.0 Made filterable via lean_seo_title_separator.
     */
    public function title_separator($sep) {
        /**
         * Filter the separator used in the document title.
         *
         * @since 1.5.0
         * @param string $separator Default '|'.
         */
        return apply_filters('lean_seo_title_separator', '|');
    }

    /**
     * Output meta tags
     */
    public function output_meta_tags() {
        Lean_SEO_Meta::output();
    }

    /**
     * Output canonical
     */
    public function output_canonical() {
        Lean_SEO_Meta::output_canonical();
    }

    /**
     * Output schema
     */
    public function output_schema() {
        Lean_SEO_Schema::output();
    }

    /**
     * Register the per-post SEO meta fields.
     *
     * @since 1.8.0
     * @since 1.9.0 Field definitions moved to Lean_SEO_Post_Seo.
     */
    public function register_meta_fields() {
        Lean_SEO_Post_Seo::register_meta(Lean_SEO_Admin::get_meta_box_post_types());
    }

    /**
     * Register sitemap routes
     */
    public function register_sitemap_routes() {
        Lean_SEO_Sitemap::register_routes();
    }

    /**
     * Flush rewrite rules once after activation or a version bump.
     *
     * Runs at init priority 21 — after register_sitemap_routes() — so the
     * flushed rule set actually contains the sitemap routes.
     *
     * @since 1.7.1
     */
    public function maybe_flush_rewrite_rules() {
        if (get_option('lean_seo_rewrite_version') === LEAN_SEO_VERSION) {
            return;
        }

        flush_rewrite_rules();

        // Autoloaded on purpose: this runs on every init, and a
        // non-autoloaded option costs one query per request forever to read a
        // short version string that changes once per release.
        update_option('lean_seo_rewrite_version', LEAN_SEO_VERSION, true);
    }

    /**
     * Sitemap query vars
     */
    public function sitemap_query_vars($vars) {
        $vars[] = 'lean_sitemap';
        $vars[] = 'sitemap_page';
        $vars[] = 'lean_cpt';
        return $vars;
    }

    /**
     * Handle sitemap requests
     */
    public function handle_sitemap() {
        Lean_SEO_Sitemap::handle_request();
    }

    /**
     * Add meta box
     */
    public function add_meta_box() {
        Lean_SEO_Admin::add_meta_box();
    }

    /**
     * Save meta
     */
    public function save_meta($post_id) {
        Lean_SEO_Admin::save_meta($post_id);
    }
}
