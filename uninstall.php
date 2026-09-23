<?php
/**
 * Uninstall routine for ThatSeoAgent.
 *
 * Runs only when the user deletes the plugin from the Plugins screen.
 * Removes every option and post meta key the plugin created, and nothing
 * else.
 *
 * @package ThatSeoAgent
 * @since 1.8.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Every name below is asked of the module that owns it — the settings from
// ThatSeoAgent_Settings, the rest from their classes — instead of kept as a
// copy that can fall out of step when something is added. The classes need
// nothing but ABSPATH, and the autoloader nothing but its own directory.
require_once plugin_dir_path( __FILE__ ) . 'includes/autoload.php';

/**
 * Delete all ThatSeoAgent data for the current site.
 *
 * @return void
 */
function thatseoagent_uninstall_site() {
    $options = array_merge(
        array_keys( ThatSeoAgent_Settings::definitions() ),
        array(
            ThatSeoAgent_Audit_Run::RESULTS_OPTION,
            ThatSeoAgent::REWRITE_VERSION_OPTION,
            ThatSeoAgent_Markdown_Cache::VERSION_OPTION,
        )
    );

    foreach ( $options as $option ) {
        delete_option( $option );
    }

    foreach ( ThatSeoAgent_Post_Seo::keys() as $meta_key ) {
        delete_post_meta_by_key( $meta_key );
    }

    wp_unschedule_hook( ThatSeoAgent_IndexNow::CRON_HOOK );
    delete_transient( ThatSeoAgent_Llms::CACHE_KEY );
    delete_transient( ThatSeoAgent_Product_Report::SUMMARY_KEY );
    delete_metadata( 'user', 0, ThatSeoAgent_App::THEME_META, '', true );
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
function thatseoagent_uninstall_all_sites() {
    if ( ! is_multisite() ) {
        thatseoagent_uninstall_site();
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
        thatseoagent_uninstall_site();
        restore_current_blog();
    }
}

thatseoagent_uninstall_all_sites();
