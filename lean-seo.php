<?php
/**
 * Plugin Name: Lean SEO
 * Plugin URI: https://github.com/Sarai-Chinwag/lean-seo
 * Description: Lightweight SEO without the bloat. Meta tags, Open Graph, Schema markup, XML sitemaps, per-post SEO fields, and Markdown for AI agents. A Yoast replacement that doesn't slow your site down.
 * Version: 1.20.0
 * Author: Sarai Chinwag
 * Author URI: https://saraichinwag.com
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lean-seo
 * Domain Path: /languages
 * Requires at least: 7.1
 * Requires PHP: 7.4
 *
 * @package Lean_SEO
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('LEAN_SEO_VERSION', '1.20.0');
define('LEAN_SEO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LEAN_SEO_PLUGIN_URL', plugin_dir_url(__FILE__));

// Third-party libraries, namespaced under Lean_SEO\Dependencies by Strauss.
require_once LEAN_SEO_PLUGIN_DIR . 'vendor-prefixed/autoload.php';

// The plugin's own classes, one per file under includes/, loaded on first
// use: nothing here depends on the order files are read in.
require_once LEAN_SEO_PLUGIN_DIR . 'includes/autoload.php';

// Initialize
function lean_seo_init() {
    return Lean_SEO::get_instance();
}
add_action('plugins_loaded', 'lean_seo_init');

/**
 * Load translations from /languages.
 *
 * Plugins hosted on WordPress.org get their translations delivered by
 * translate.wordpress.org and need no call at all. This one is distributed
 * outside the directory, so it has to load its own .mo files.
 */
function lean_seo_load_textdomain() {
    load_plugin_textdomain( 'lean-seo', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'lean_seo_load_textdomain' );

// Abilities API. Guarded rather than assumed: the function comes with the
// Abilities API, and without it the hook below never fires.
if ( function_exists( 'wp_register_ability' ) ) {
    // The hook may already have fired by the time this file loads.
    if ( did_action( 'wp_abilities_api_init' ) ) {
        Lean_SEO_Abilities::register();
    } else {
        add_action( 'wp_abilities_api_init', array( 'Lean_SEO_Abilities', 'register' ) );
    }
}

// Activation hook
register_activation_hook(__FILE__, 'lean_seo_activate');
/**
 * Flag the rewrite rules for a refresh.
 *
 * We cannot flush here: during activation `plugins_loaded` has already
 * fired, so Lean_SEO never boots and the sitemap rewrite rules are not
 * registered yet. Flushing now would persist a rule set *without* the
 * sitemap routes and every /sitemap*.xml URL would 404. Instead we drop
 * the stored rewrite version so Lean_SEO::maybe_flush_rewrite_rules()
 * flushes on the next `init`, once the rules exist.
 */
function lean_seo_activate() {
    delete_option(Lean_SEO::REWRITE_VERSION_OPTION);
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'lean_seo_deactivate');
function lean_seo_deactivate() {
    delete_option(Lean_SEO::REWRITE_VERSION_OPTION);

    // Drop any IndexNow submissions still queued; their callback disappears
    // with the plugin and WP-Cron would keep retrying a missing hook.
    wp_unschedule_hook(Lean_SEO_IndexNow::CRON_HOOK);

    // Not flush_rewrite_rules(): `init` already ran in this request, so the
    // sitemap and .md rules are registered and a flush would persist them —
    // /sitemap.xml and /post.md would keep routing to a plugin that is gone.
    // Deleting the option makes WordPress rebuild the rules on the next
    // request, without them.
    delete_option('rewrite_rules');

    Lean_SEO_Markdown_Cache::purge_all();
}

// WP-CLI commands.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'lean-seo', 'Lean_SEO_CLI' );
}
