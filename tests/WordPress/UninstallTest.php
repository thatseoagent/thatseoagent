<?php

/*
 * Deleting the plugin: everything it wrote goes, and nothing else does.
 */

/**
 * The rows of the options, post meta, term meta and user meta tables whose
 * name mentions the plugin.
 *
 * @return array<string, list<string>>
 */
function pluginRows(): array {
    global $wpdb;
    $like = '%' . $wpdb->esc_like( 'thatseoagent' ) . '%';

    return array(
        'options'  => $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ),
        'postmeta' => $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $like ) ),
        'termmeta' => $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_key FROM {$wpdb->termmeta} WHERE meta_key LIKE %s", $like ) ),
        'usermeta' => $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_key FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $like ) ),
    );
}

it( 'leaves no trace of the plugin, and the rest of the site as it was', function () {
    // The site as months of use leave it.
    $admin = actingAs( 'administrator' );
    fakeOwnSite();
    $news = term( 'News' );
    $post = post( array( 'post_category' => array( $news->term_id ) ), array( 'title' => 'T', 'description' => 'D', 'noindex' => '1', 'share_image' => (string) image() ) );
    ThatSeoAgent_Primary_Term::save( $post, 'category', $news->term_id );
    ThatSeoAgent_Term_Seo::save( $news, array( 'title' => 'T', 'description' => 'D', 'noindex' => '1' ) );
    update_user_meta( $admin->ID, ThatSeoAgent_App::THEME_META, 'dark' );
    foreach ( ThatSeoAgent_Author_Profile::keys() as $key ) {
        update_user_meta( $admin->ID, $key, 'x' );
    }
    foreach ( ThatSeoAgent_Settings::definitions() as $option => $definition ) {
        update_option( $option, $definition['default'] ?: 'x' );
    }
    update_option( ThatSeoAgent_IndexNow::OPTION_KEY, 'a1b2c3d4e5f6a7b8' );
    post();
    rest( 'POST', '/llms' );
    rest( 'POST', '/crawlers/probe' );
    ThatSeoAgent_Llms_Full::cached();
    ThatSeoAgent_Markdown_Endpoint::markdown( $post );
    ThatSeoAgent_Links::report( true );
    ThatSeoAgent_Bulletin::get();
    ThatSeoAgent_Checks::schedule();

    // And what belongs to others.
    update_option( 'another_plugin_setting', 'kept' );
    update_post_meta( $post->ID, '_another_plugin_field', 'kept' );

    $before = pluginRows();

    if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
        define( 'WP_UNINSTALL_PLUGIN', 'thatseoagent/thatseoagent.php' );
    }
    require dirname( __DIR__, 2 ) . '/uninstall.php';
    wp_cache_flush();

    expect( array_filter( $before ) )->not->toBe( array() )
        ->and( pluginRows() )->toBe( array( 'options' => array(), 'postmeta' => array(), 'termmeta' => array(), 'usermeta' => array() ) )
        ->and( wp_next_scheduled( ThatSeoAgent_IndexNow::CRON_HOOK ) )->toBeFalse()
        ->and( wp_next_scheduled( ThatSeoAgent_Checks::CRON_HOOK ) )->toBeFalse()
        ->and( get_option( 'another_plugin_setting' ) )->toBe( 'kept' )
        ->and( get_post_meta( $post->ID, '_another_plugin_field', true ) )->toBe( 'kept' )
        ->and( get_post( $post->ID ) )->not->toBeNull();
} );
