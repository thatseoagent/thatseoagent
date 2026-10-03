<?php

/*
 * The admin screens the plugin draws or adds to, as an administrator opens
 * them: each one answers, shows its part, and makes PHP log nothing.
 */

use ThatSeoAgent\Tests\Response;

/**
 * Cookies of a logged-in session for a new administrator.
 *
 * @return array<string, string> Header name => value.
 */
function adminSession(): array {
    $user    = user( 'administrator' );
    $expires = time() + HOUR_IN_SECONDS;
    $token   = WP_Session_Tokens::get_instance( $user->ID )->create( $expires );

    return array(
        'Cookie' => AUTH_COOKIE . '=' . rawurlencode( wp_generate_auth_cookie( $user->ID, $expires, 'auth', $token ) )
            . '; ' . LOGGED_IN_COOKIE . '=' . rawurlencode( wp_generate_auth_cookie( $user->ID, $expires, 'logged_in', $token ) ),
    );
}

/**
 * An admin screen, as an administrator.
 */
function adminPage( string $path ): Response {
    return fetch( admin_url( $path ), adminSession() );
}

it( 'draws each view of its screen', function ( string $view, string $marker ) {
    $response = adminPage( 'admin.php?page=thatseoagent' . ( '' === $view ? '' : '&view=' . $view ) );

    expect( $response->status )->toBe( 200 )
        ->and( $response->body )->toContain( $marker )
        ->and( $response->body )->not->toContain( 'There has been a critical error' );
} )->with( array(
    'the overview'      => array( '', 'Warning scale' ),
    'products'          => array( 'products', 'Products' ),
    'the content check' => array( 'audit', 'Content check' ),
    'AI crawlers'       => array( 'crawlers', 'AI crawlers' ),
    'the AI index'      => array( 'llms', 'llms.txt' ),
    'settings'          => array( 'settings', 'Settings' ),
) );

it( 'sends a view on its own to the screen’s router', function () {
    $response = fetch( admin_url( 'admin.php?page=thatseoagent&view=settings' ), adminSession() + array( 'X-ThatSeoAgent-View' => '1' ) );
    $json     = json_decode( $response->body, true );

    expect( $response->status )->toBe( 200 )
        ->and( $json )->toHaveKeys( array( 'version', 'html', 'title', 'bulletin' ) );
} );

it( 'puts the bulletin on the WordPress dashboard', function () {
    expect( adminPage( 'index.php' )->body )->toContain( 'id="' . ThatSeoAgent_Dashboard_Widget::ID . '"' );
} );

it( 'adds the SEO fields to the post editor', function () {
    $post = post();

    $response = adminPage( 'post.php?post=' . $post->ID . '&action=edit' );

    expect( $response->status )->toBe( 200 )
        ->and( $response->body )->toContain( 'thatseoagent_meta' );
} );

it( 'adds the SEO fields to a category’s screen', function () {
    $news = term( 'News' );

    $response = adminPage( 'term.php?taxonomy=category&tag_ID=' . $news->term_id );

    expect( $response->status )->toBe( 200 )
        ->and( $response->body )->toContain( ThatSeoAgent_Term_Seo::NONCE );
} );

it( 'adds the author’s job title and profiles to their profile', function () {
    $response = adminPage( 'profile.php' );

    expect( $response->status )->toBe( 200 )
        ->and( $response->body )->toContain( 'name="thatseoagent_job_title"', 'name="thatseoagent_profiles"' );
} );

it( 'adds the bulk action to the posts list', function () {
    post();

    expect( adminPage( 'edit.php' )->body )->toContain( 'value="' . ThatSeoAgent_Bulk_Descriptions::ACTION . '"' );
} );
