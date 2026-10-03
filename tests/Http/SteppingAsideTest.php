<?php

/*
 * Stepping aside: while another SEO plugin is active, ThatSeoAgent prints
 * nothing in the page head but the analytics tags, serves no sitemaps or
 * AI index, and leaves robots.txt alone. The Markdown versions stay.
 *
 * The other plugin is played by tests/mu-plugin.php, which the test site
 * loads: the decision is taken on plugins_loaded, before any test code.
 */

use ThatSeoAgent\Tests\Page;

beforeEach( function () {
    if ( function_exists( 'update_option' ) ) {
        update_option( 'thatseoagent_tests_other_seo_plugin', '1' );
    }
} );

it( 'prints no tags of its own in the head, but the analytics', function () {
    update_option( ThatSeoAgent_Tracking::OPTION_KEY, array( 'ga4' => 'G-TEST12345' ) );
    $post = post( array( 'post_name' => 'stepping-aside-head' ), array( 'description' => 'Ours, not printed.' ) );

    $html = fetch( get_permalink( $post ) )->body;
    $head = new Page( substr( $html, 0, (int) strpos( $html, '</head>' ) ) );

    expect( $head->meta( 'og:title' ) )->toBeNull()
        ->and( $head->meta( 'description' ) )->toBeNull()
        ->and( $head->graph() )->toBe( array() )
        ->and( $head->links( 'canonical' ) )->toBe( array( get_permalink( $post ) ) )
        ->and( $html )->toContain( 'googletagmanager.com/gtag/js?id=G-TEST12345' );
} );

it( 'serves no sitemaps and no AI index', function () {
    $sitemap = fetch( home_url( '/sitemap.xml' ) );

    expect( $sitemap->body )->not->toContain( 'sitemap-posts.xml' )
        ->and( fetch( home_url( '/llms.txt' ) )->status )->toBe( 404 )
        ->and( fetch( home_url( '/llms-full.txt' ) )->status )->toBe( 404 );
} );

it( 'leaves robots.txt to WordPress', function () {
    expect( fetch( home_url( '/robots.txt' ) )->body )->not->toContain( 'Sitemap: ' . home_url( '/sitemap.xml' ) );
} );

it( 'keeps serving the Markdown versions', function () {
    $post = post( array( 'post_title' => 'Still in Markdown', 'post_name' => 'stepping-aside-markdown' ) );

    $response = fetch( untrailingslashit( get_permalink( $post ) ) . '.md' );

    expect( $response->status )->toBe( 200 )
        ->and( $response->body )->toContain( 'title: "Still in Markdown"' );
} );
