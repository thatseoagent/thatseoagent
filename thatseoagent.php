<?php
/**
 * Plugin Name: ThatSeoAgent
 * Description: SEO for WordPress with no paid tier: meta tags, Open Graph, schema markup, XML sitemaps, per-post SEO fields and a Markdown version of every post for AI agents. Replaces Yoast SEO and imports its data.
 * Version: 3.0.0
 * Author: Angel Cruz
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: thatseoagent
 * Domain Path: /languages
 * Requires at least: 7.1
 * Requires PHP: 8.3
 *
 * @package ThatSeoAgent
 */

// Prevent direct access
if ( !defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'THATSEOAGENT_VERSION', '3.0.0' );
define( 'THATSEOAGENT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'THATSEOAGENT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Third-party libraries, namespaced under ThatSeoAgent\Dependencies by Strauss.
require_once THATSEOAGENT_PLUGIN_DIR . 'vendor-prefixed/autoload.php';

// The plugin's own classes, one per file under includes/, loaded on first
// use: nothing here depends on the order files are read in.
require_once THATSEOAGENT_PLUGIN_DIR . 'includes/autoload.php';

// Initialize
function thatseoagent_init() {
    return ThatSeoAgent::get_instance();
}
add_action( 'plugins_loaded', 'thatseoagent_init' );

/**
 * Load translations from /languages.
 *
 * Plugins hosted on WordPress.org get their translations delivered by
 * translate.wordpress.org and need no call at all. This one is distributed
 * outside the directory, so it has to load its own .mo files.
 */
function thatseoagent_load_textdomain() {
    load_plugin_textdomain( 'thatseoagent', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'thatseoagent_load_textdomain' );

/**
 * Declare a content type as a product catalog.
 *
 * Call it on the `thatseoagent_init` action, from the theme or plugin that
 * registers the post type. Each entry is then marked up as a schema.org
 * Product, listed in /catalog.jsonl and llms.txt, and checked on the
 * Products screen and by `wp thatseoagent validate-products`.
 *
 *     add_action( 'thatseoagent_init', function () {
 *         thatseoagent_register_catalog( 'machine', array(
 *             'brand_taxonomy'    => 'machine_brand',
 *             'category_taxonomy' => 'machine_type',
 *             'properties'        => fn ( WP_Post $post ) => my_specs( $post->ID ),
 *             'gallery'           => '_machine_gallery',
 *         ) );
 *     } );
 *
 * @since 3.0.0
 * @param string $post_type The content type that lists the products.
 * @param array  $args {
 *     How each detail is read. Every key is optional.
 *
 *     @type string          $brand_taxonomy    Taxonomy of the brand.
 *     @type string          $category_taxonomy Taxonomy of the category, as "Parent > Child".
 *     @type string|callable $properties        Meta key, or a callback that gets the post, of the
 *                                              specifications: a list of name/value pairs or a
 *                                              name => value map, or JSON of either.
 *     @type string|callable $gallery           Meta key, or callback, of the gallery: attachment
 *                                              IDs, as a list or comma-separated.
 *     @type string|callable $sku               Meta key, or callback, of the SKU.
 *     @type string|callable $mpn               Meta key, or callback, of the MPN.
 *     @type string|callable $gtin              Meta key, or callback, of the GTIN.
 * }
 * @return bool Whether the catalog was declared.
 */
function thatseoagent_register_catalog( $post_type, array $args = array() ) {
    return ThatSeoAgent_Product::declare_catalog( $post_type, $args );
}

if ( ! function_exists( 'thatseoagent_breadcrumbs' ) ) {
    /**
     * Print or return the breadcrumbs of the current page, for templates.
     *
     * The same trail the BreadcrumbList schema states. Prints nothing on a
     * page with no trail beyond the homepage.
     *
     * @since 2.4.0
     * @param array $args See ThatSeoAgent_Breadcrumbs::render().
     * @param bool  $echo Print it (default) or return it.
     * @return string The HTML.
     */
    function thatseoagent_breadcrumbs( $args = array(), $echo = true ) {
        $html = ThatSeoAgent_Breadcrumbs::render( (array) $args );

        if ( $echo ) {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in ThatSeoAgent_Breadcrumbs::render().
        }

        return $html;
    }
}

// Abilities API. Guarded rather than assumed: the function comes with the
// Abilities API, and without it the hook below never fires.
if ( function_exists( 'wp_register_ability' ) ) {
    // The hook may already have fired by the time this file loads.
    if ( did_action( 'wp_abilities_api_init' ) ) {
        ThatSeoAgent_Abilities::register();
    } else {
        add_action( 'wp_abilities_api_init', array( 'ThatSeoAgent_Abilities', 'register' ) );
    }

    // Their own MCP server, when Lean MCP is active.
    ThatSeoAgent_MCP::register();
}

// Activation hook
register_activation_hook( __FILE__, 'thatseoagent_activate' );
/**
 * Flag the rewrite rules for a refresh.
 *
 * We cannot flush here: during activation `plugins_loaded` has already
 * fired, so ThatSeoAgent never boots and the sitemap rewrite rules are not
 * registered yet. Flushing now would persist a rule set *without* the
 * sitemap routes and every /sitemap*.xml URL would 404. Instead we drop
 * the stored rewrite version so ThatSeoAgent::maybe_flush_rewrite_rules()
 * flushes on the next `init`, once the rules exist.
 */
function thatseoagent_activate() {
    delete_option( ThatSeoAgent::REWRITE_VERSION_OPTION );
}

// Deactivation hook
register_deactivation_hook( __FILE__, 'thatseoagent_deactivate' );
function thatseoagent_deactivate() {
    delete_option( ThatSeoAgent::REWRITE_VERSION_OPTION );

    // Drop any IndexNow submissions still queued; their callback disappears
    // with the plugin and WP-Cron would keep retrying a missing hook.
    wp_unschedule_hook( ThatSeoAgent_IndexNow::CRON_HOOK );
    wp_unschedule_hook( ThatSeoAgent_Checks::CRON_HOOK );

    // Not flush_rewrite_rules(): `init` already ran in this request, so the
    // sitemap and .md rules are registered and a flush would persist them —
    // /sitemap.xml and /post.md would keep routing to a plugin that is gone.
    // Deleting the option makes WordPress rebuild the rules on the next
    // request, without them.
    delete_option( 'rewrite_rules' );

    ThatSeoAgent_Markdown_Cache::purge_all();
}

// WP-CLI commands.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'thatseoagent', 'ThatSeoAgent_CLI' );
}
