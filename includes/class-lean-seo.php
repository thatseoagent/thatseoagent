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
        foreach ( array(
            'compat', 'settings', 'product', 'product-report', 'product-settings', 'app', 'audit-run', 'rest',
            'meta', 'title', 'schema', 'sitemap', 'robots', 'faq', 'default-author', 'meta-box', 'bulk-descriptions',
            'identity', 'homepage', 'markdown', 'markdown-cache', 'markdown-endpoint', 'llms',
        ) as $module ) {
            require_once LEAN_SEO_PLUGIN_DIR . 'includes/class-lean-seo-' . $module . '.php';
        }
    }

    /**
     * Wire every module.
     *
     * Each module registers its own hooks; this list is the only place that
     * decides which run, and when. The order matters where two modules share
     * a hook and priority: the Markdown alternate link, then the meta tags,
     * then the canonical, all at wp_head priority 1.
     *
     * @since 1.19.0 Modules register their own hooks; the forwarders that
     *              stood in for them here are gone.
     */
    private function init_hooks() {
        // Everywhere, including REST and WP-CLI.
        Lean_SEO_Settings::register();
        Lean_SEO_REST::register();
        Lean_SEO_Post_Seo::register();
        Lean_SEO_IndexNow::register();
        Lean_SEO_Markdown_Endpoint::register();
        Lean_SEO_Markdown_Cache::register();
        Lean_SEO_Product_Report::register();
        Lean_SEO_Identity_Applier::register();
        Lean_SEO_Homepage_Applier::register();

        add_action('init', array($this, 'maybe_flush_rewrite_rules'), 21);

        if (is_admin()) {
            // Before the bulk action's: its notice has always come first.
            Lean_SEO_Compat::register();
            Lean_SEO_Meta_Box::register();
            Lean_SEO_Bulk_Descriptions::register();
            Lean_SEO_App::register();
            Lean_SEO_Default_Author::register();
            Lean_SEO_Identity::register();
            Lean_SEO_Homepage::register();
            Lean_SEO_Product_Settings::register();
            Lean_SEO_Llms::register_settings();
        }

        // Everything below writes to <head>, serves sitemaps or edits
        // robots.txt — all of which another active SEO plugin does too.
        if (! Lean_SEO_Compat::outputs_enabled()) {
            return;
        }

        Lean_SEO_Title::register();
        Lean_SEO_Meta::register();
        Lean_SEO_Schema::register();
        Lean_SEO_Sitemap::register();
        Lean_SEO_Robots::register();

        // llms.txt — Yoast, Rank Math and AIOSEO generate one of their own.
        Lean_SEO_Llms::register();
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
        // The rule set differs while another SEO plugin is active (no
        // sitemap routes), so the stored version records that too: activating
        // or deactivating that plugin must trigger a flush, or /sitemap.xml
        // stays missing after it is gone.
        $version = LEAN_SEO_VERSION . (Lean_SEO_Compat::outputs_enabled() ? '' : '-compat');

        if (get_option('lean_seo_rewrite_version') === $version) {
            return;
        }

        flush_rewrite_rules();

        // Autoloaded on purpose: this runs on every init, and a
        // non-autoloaded option costs one query per request forever to read a
        // short version string that changes once per release.
        update_option('lean_seo_rewrite_version', $version, true);
    }
}
