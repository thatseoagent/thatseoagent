<?php
/**
 * Main ThatSeoAgent Class
 *
 * @package ThatSeoAgent
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ThatSeoAgent {

    /**
     * Option recording the rule set last flushed, so rewrite rules are
     * flushed once per change rather than on every request.
     */
    const REWRITE_VERSION_OPTION = 'thatseoagent_rewrite_version';

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
        $this->init_hooks();
    }

    /**
     * Wire every module.
     *
     * Each module registers its own hooks; this list is the only place that
     * decides which run, and when. The order matters where two modules share
     * a hook and priority: the Markdown alternate link, then the meta tags,
     * then the canonical, then rel="prev"/"next", all at wp_head priority 1.
     *
     * @since 1.19.0 Modules register their own hooks; the forwarders that
     *              stood in for them here are gone.
     */
    private function init_hooks() {
        // Everywhere, including REST and WP-CLI.
        ThatSeoAgent_Settings::register();
        ThatSeoAgent_REST::register();
        ThatSeoAgent_Post_Seo::register();
        ThatSeoAgent_Primary_Term::register();
        ThatSeoAgent_Product::register();
        ThatSeoAgent_Breadcrumbs::register();
        ThatSeoAgent_IndexNow::register();
        ThatSeoAgent_Markdown_Endpoint::register();
        ThatSeoAgent_Markdown_Cache::register();
        ThatSeoAgent_Product_Report::register();
        ThatSeoAgent_Links::register();
        ThatSeoAgent_Checks::register();
        ThatSeoAgent_Author_Profile::register();

        add_action('init', array($this, 'maybe_flush_rewrite_rules'), 21);

        if (is_admin()) {
            // Before the bulk action's: its notice has always come first.
            ThatSeoAgent_Compat::register();
            ThatSeoAgent_Meta_Box::register();
            ThatSeoAgent_Bulk_Descriptions::register();
            ThatSeoAgent_App::register();
            ThatSeoAgent_Default_Author::register();
            ThatSeoAgent_Identity::register();
            ThatSeoAgent_Homepage::register();
            ThatSeoAgent_Product_Settings::register();
            ThatSeoAgent_AI_Crawlers::register();
            ThatSeoAgent_Llms::register_settings();
            ThatSeoAgent_Cache_Settings::register();
            ThatSeoAgent_Crawl_Cleanup::register_settings();
            ThatSeoAgent_Verification::register_settings();
            ThatSeoAgent_New_Types::register();
        }

        // Everything below writes to <head>, serves sitemaps or edits
        // robots.txt — all of which another active SEO plugin does too.
        if (! ThatSeoAgent_Compat::outputs_enabled()) {
            return;
        }

        ThatSeoAgent_Title::register();
        ThatSeoAgent_Indexing::register();
        ThatSeoAgent_Attachment_Redirect::register();
        ThatSeoAgent_Crawl_Cleanup::register();
        ThatSeoAgent_Verification::register();
        ThatSeoAgent_Meta::register();
        ThatSeoAgent_Pagination::register();
        ThatSeoAgent_Schema::register();
        ThatSeoAgent_Sitemap::register();
        ThatSeoAgent_Robots::register();

        // llms.txt — Yoast, Rank Math and AIOSEO generate one of their own —
        // and llms-full.txt, which goes with it.
        ThatSeoAgent_Llms::register();
        ThatSeoAgent_Llms_Full::register();
        ThatSeoAgent_Catalog_Feed::register();
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
        $version = THATSEOAGENT_VERSION . (ThatSeoAgent_Compat::outputs_enabled() ? '' : '-compat');

        if (get_option(self::REWRITE_VERSION_OPTION) === $version) {
            return;
        }

        flush_rewrite_rules();

        // Autoloaded on purpose: this runs on every init, and a
        // non-autoloaded option costs one query per request forever to read a
        // short version string that changes once per release.
        update_option(self::REWRITE_VERSION_OPTION, $version, true);
    }
}
