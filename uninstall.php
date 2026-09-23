<?php
/**
 * Uninstall routine for ThatSeoAgent.
 *
 * Runs only when the user deletes the plugin from the Plugins screen.
 * Removes every option and post meta key the plugin created, and its
 * block in .htaccess, and nothing else.
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
            ThatSeoAgent_Catalog_Feed::VERSION_OPTION,
            ThatSeoAgent_New_Types::OPTION_KEY,
        )
    );

    foreach ( $options as $option ) {
        delete_option( $option );
    }

    foreach ( ThatSeoAgent_Post_Seo::keys() as $meta_key ) {
        delete_post_meta_by_key( $meta_key );
    }

    // Primary terms: one key per taxonomy, whichever taxonomies existed.
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A one-off cleanup; no API deletes meta by prefix.
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( ThatSeoAgent_Primary_Term::KEY_PREFIX ) . '%' ) );

    wp_unschedule_hook( ThatSeoAgent_IndexNow::CRON_HOOK );
    delete_transient( ThatSeoAgent_Llms::CACHE_KEY );
    delete_transient( ThatSeoAgent_Llms_Full::CACHE_KEY );
    delete_transient( ThatSeoAgent_Links::CACHE_KEY );
    delete_transient( ThatSeoAgent_Product_Report::SUMMARY_KEY );
    delete_transient( ThatSeoAgent_Product_Report::STATUS_KEY );
    delete_metadata( 'user', 0, ThatSeoAgent_App::THEME_META, '', true );
    foreach ( ThatSeoAgent_Author_Profile::keys() as $meta_key ) {
        delete_metadata( 'user', 0, $meta_key, '', true );
    }
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
    // One .htaccess for the whole install. Only the plugin's own block goes;
    // a file WordPress cannot write is left as it is.
    ThatSeoAgent_Markdown_Htaccess::remove();

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
