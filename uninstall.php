<?php
/**
 * Uninstall routine for Lean SEO.
 *
 * Runs only when the user deletes the plugin from the Plugins screen.
 * Removes every option and post meta key the plugin created, and nothing
 * else — in particular the legacy theme option `sarai_chinwag_indexnow_key`
 * is left alone because Lean SEO only ever read from it.
 *
 * @package Lean_SEO
 * @since 1.8.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Options created by Lean SEO.
 *
 * @var string[]
 */
const LEAN_SEO_UNINSTALL_OPTIONS = array(
    'lean_seo_schema',
    'lean_seo_identity',
    'lean_seo_homepage',
    'lean_seo_products',
    'lean_seo_llms_txt',
    'lean_seo_audit_results',
    'lean_seo_indexnow_key',
    'lean_seo_rewrite_version',
    'lean_seo_markdown_cache_version',
);

// The meta keys are owned by Lean_SEO_Post_Seo. Loading the file (it needs
// nothing but ABSPATH) is how this script asks instead of keeping a copy
// that can fall out of step when a field is added.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-lean-seo-post-seo.php';

/**
 * Delete all Lean SEO data for the current site.
 *
 * @return void
 */
function lean_seo_uninstall_site() {
    foreach ( LEAN_SEO_UNINSTALL_OPTIONS as $option ) {
        delete_option( $option );
    }

    foreach ( Lean_SEO_Post_Seo::keys() as $meta_key ) {
        delete_post_meta_by_key( $meta_key );
    }

    wp_unschedule_hook( 'lean_seo_indexnow_submit' );
    delete_transient( 'lean_seo_llms_txt' );
    delete_transient( 'lean_seo_product_summary' );
    delete_metadata( 'user', 0, 'lean_seo_admin_theme', '', true );
}

/**
 * Run the uninstall across every site, or just this one.
 *
 * Wrapped in a function so the loop variables stay out of the global scope —
 * uninstall.php executes at file scope, where a bare $site_id would become a
 * global.
 *
 * @return void
 */
function lean_seo_uninstall_all_sites() {
    if ( ! is_multisite() ) {
        lean_seo_uninstall_site();
        return;
    }

    $site_ids = get_sites(
        array(
            'fields'                 => 'ids',
            'number'                 => 0,
            'update_site_meta_cache' => false,
        )
    );

    foreach ( $site_ids as $site_id ) {
        switch_to_blog( $site_id );
        lean_seo_uninstall_site();
        restore_current_blog();
    }
}

lean_seo_uninstall_all_sites();
