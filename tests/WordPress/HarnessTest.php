<?php

/*
 * What every WordPress test can count on: the plugin is loaded, what a test
 * writes is gone in the next one, and no request leaves the site.
 */

use ThatSeoAgent\Tests\HttpFake;

it( 'runs with the plugin loaded', function () {
    expect( defined( 'THATSEOAGENT_VERSION' ) )->toBeTrue()
        ->and( ThatSeoAgent::get_instance() )->toBeInstanceOf( ThatSeoAgent::class );
} );

it( 'writes to the database', function () {
    update_option( 'thatseoagent_tests_marker', 'written' );
    wp_insert_post( array( 'post_title' => 'Harness marker', 'post_status' => 'publish' ) );

    expect( get_option( 'thatseoagent_tests_marker' ) )->toBe( 'written' );
} );

it( 'finds nothing the previous test wrote', function () {
    $posts = get_posts( array( 'title' => 'Harness marker', 'post_status' => 'any' ) );

    expect( get_option( 'thatseoagent_tests_marker' ) )->toBeFalse()
        ->and( $posts )->toBe( array() );
} );

it( 'answers the requests a test fakes, and records them', function () {
    fakeHttp( array( 'https://api.indexnow.org/*' => array( 'code' => 202 ) ) );

    $response = wp_remote_post( 'https://api.indexnow.org/indexnow', array( 'body' => 'urls' ) );

    expect( wp_remote_retrieve_response_code( $response ) )->toBe( 202 )
        ->and( httpRequests() )->toBe( array(
            array( 'url' => 'https://api.indexnow.org/indexnow', 'method' => 'POST', 'body' => 'urls' ),
        ) );
} );

it( 'refuses the requests a test did not fake', function () {
    $response = wp_remote_get( 'https://example.com/' );

    expect( $response )->toBeInstanceOf( WP_Error::class )
        ->and( HttpFake::stop() )->toBe( array( 'https://example.com/' ) );

    // Refused on purpose: start over so this test does not fail for it.
    HttpFake::start();
} );
