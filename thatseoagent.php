<?php
/**
 * Plugin Name: ThatSeoAgent
 * Description: Lightweight SEO without the bloat. Meta tags, Open Graph, Schema markup, XML sitemaps, per-post SEO fields, and Markdown for AI agents. A Yoast replacement that doesn't slow your site down.
 * Version: 2.4.0
 * Author: Angel Cruz
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: thatseoagent
 * Domain Path: /languages
 * Requires at least: 7.1
 * Requires PHP: 7.4
 *
 * @package ThatSeoAgent
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('THATSEOAGENT_VERSION', '2.4.0');
define('THATSEOAGENT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('THATSEOAGENT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Third-party libraries, namespaced under ThatSeoAgent\Dependencies by Strauss.
require_once THATSEOAGENT_PLUGIN_DIR . 'vendor-prefixed/autoload.php';

// The plugin's own classes, one per file under includes/, loaded on first
// use: nothing here depends on the order files are read in.
require_once THATSEOAGENT_PLUGIN_DIR . 'includes/autoload.php';

// Initialize
function thatseoagent_init() {
    return ThatSeoAgent::get_instance();
}
add_action('plugins_loaded', 'thatseoagent_init');

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
}

// Activation hook
register_activation_hook(__FILE__, 'thatseoagent_activate');
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
    delete_option(ThatSeoAgent::REWRITE_VERSION_OPTION);
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'thatseoagent_deactivate');
function thatseoagent_deactivate() {
    delete_option(ThatSeoAgent::REWRITE_VERSION_OPTION);

    // Drop any IndexNow submissions still queued; their callback disappears
    // with the plugin and WP-Cron would keep retrying a missing hook.
    wp_unschedule_hook(ThatSeoAgent_IndexNow::CRON_HOOK);

    // Not flush_rewrite_rules(): `init` already ran in this request, so the
    // sitemap and .md rules are registered and a flush would persist them —
    // /sitemap.xml and /post.md would keep routing to a plugin that is gone.
    // Deleting the option makes WordPress rebuild the rules on the next
    // request, without them.
    delete_option('rewrite_rules');

    ThatSeoAgent_Markdown_Cache::purge_all();
}

// WP-CLI commands.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'thatseoagent', 'ThatSeoAgent_CLI' );
}
