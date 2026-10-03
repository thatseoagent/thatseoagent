<?php

/*
 * The REST API of the ThatSeoAgent screen, thatseoagent/v1: who may call
 * it, and what each route answers.
 */

/**
 * A request to the plugin's REST API, as the current user.
 *
 * @param array<string, mixed> $params
 */
function rest( string $method, string $route, array $params = array() ): WP_REST_Response {
    $request = new WP_REST_Request( $method, '/thatseoagent/v1' . $route );
    foreach ( $params as $name => $value ) {
        $request->set_param( $name, $value );
    }

    return rest_get_server()->dispatch( $request );
}

/**
 * The homepage and robots.txt as the site's own requests see them, every
 * crawler let in unless it is one of the turned away.
 *
 * @param list<string> $turned_away User-agent tokens answered 403.
 */
function fakeOwnSite( array $turned_away = array() ): void {
    fakeHttp( array(
        home_url( '/robots.txt' ) => array( 'body' => "User-agent: *\nDisallow: /wp-admin/\n" ),
        home_url( '/*' )          => function ( array $args ) use ( $turned_away ) {
            foreach ( $turned_away as $token ) {
                if ( str_contains( (string) $args['user-agent'], $token ) ) {
                    return array( 'code' => 403, 'body' => 'Forbidden' );
                }
            }

            return array( 'body' => '<html><body>Home</body></html>', 'headers' => array( 'Content-Type' => 'text/html' ) );
        },
    ) );
}

afterEach( function () {
    if ( function_exists( 'wp_set_current_user' ) ) {
        wp_set_current_user( 0 );
    }
} );

describe( 'every route', function () {
    it( 'is for administrators only', function ( string $method, string $route ) {
        actingAs( 'editor' );

        $response = rest( $method, $route, array( 'theme' => 'dark', 'post_type' => 'post' ) );

        expect( $response->get_status() )->toBe( 403 )
            ->and( $response->get_data()['code'] )->toBe( 'thatseoagent_forbidden' );
    } )->with( array(
        array( 'GET', '/bulletin' ),
        array( 'POST', '/preferences' ),
        array( 'GET', '/audit' ),
        array( 'POST', '/audit/runs' ),
        array( 'POST', '/llms' ),
        array( 'POST', '/crawlers/probe' ),
        array( 'POST', '/markdown/check' ),
        array( 'POST', '/markdown/htaccess' ),
        array( 'DELETE', '/markdown/htaccess' ),
    ) );

    it( 'asks a visitor to log in', function () {
        wp_set_current_user( 0 );

        expect( rest( 'GET', '/bulletin' )->get_status() )->toBe( 401 );
    } );

    it( 'keeps page caches away from its answers', function () {
        actingAs( 'administrator' );

        expect( rest( 'GET', '/bulletin' )->get_headers()['Cache-Control'] ?? '' )->toContain( 'no-cache' );
    } );
} );

describe( 'GET /bulletin', function () {
    it( 'answers the level, its name, the headline and the observations', function () {
        actingAs( 'administrator' );

        expect( rest( 'GET', '/bulletin' )->get_data() )->toHaveKeys( array( 'level', 'name', 'headline', 'observations' ) );
    } );
} );

describe( 'POST /preferences', function () {
    it( 'keeps the screen’s theme for the user, with no row for the default', function () {
        $admin = actingAs( 'administrator' );

        $dark  = rest( 'POST', '/preferences', array( 'theme' => 'dark' ) )->get_data();
        $light = rest( 'POST', '/preferences', array( 'theme' => 'light' ) )->get_data();

        expect( $dark )->toBe( array( 'theme' => 'dark' ) )
            ->and( $light )->toBe( array( 'theme' => 'light' ) )
            ->and( metadata_exists( 'user', $admin->ID, ThatSeoAgent_App::THEME_META ) )->toBeFalse();
    } );

    it( 'refuses a theme it does not have', function () {
        actingAs( 'administrator' );

        expect( rest( 'POST', '/preferences', array( 'theme' => 'sepia' ) )->get_status() )->toBe( 400 );
    } );
} );

describe( 'the content check', function () {
    it( 'runs in batches to the end, and keeps the result as the last check', function () {
        actingAs( 'administrator' );
        fakeOwnSite();
        withoutSampleContent();
        post( array( 'post_title' => 'Checked one' ) );
        post( array( 'post_title' => 'Checked two', 'post_content' => '' ) );

        $run = rest( 'POST', '/audit/runs', array( 'post_type' => 'post' ) )->get_data();
        expect( $run )->toHaveKey( 'token' );

        // The last step sends the whole list, as it was stored.
        $progress = array();
        for ( $i = 0; $i < 20 && ! isset( $progress['all'] ); $i++ ) {
            $progress = rest( 'POST', '/audit/runs/' . $run['token'] )->get_data();
        }

        $last = rest( 'GET', '/audit', array( 'post_type' => 'post' ) )->get_data()['last'];

        expect( $progress )->toHaveKey( 'all' )
            ->and( $last )->not->toBeNull()
            ->and( rest( 'POST', '/audit/runs/' . $run['token'] )->get_status() )->toBe( 409 );
    } );

    it( 'answers { last: null } for a type never checked', function () {
        actingAs( 'administrator' );

        expect( rest( 'GET', '/audit', array( 'post_type' => 'page' ) )->get_data() )->toBe( array( 'last' => null ) );
    } );

    it( 'refuses a type it cannot check, and a token that is not the run’s', function () {
        actingAs( 'administrator' );

        expect( rest( 'POST', '/audit/runs', array( 'post_type' => 'nav_menu_item' ) )->get_status() )->toBe( 400 )
            ->and( rest( 'POST', '/audit/runs/AAAAAAAAAAAAAAAA' )->get_status() )->toBeGreaterThanOrEqual( 400 );
    } );
} );

describe( 'POST /llms', function () {
    it( 'builds llms.txt again and describes it', function () {
        actingAs( 'administrator' );
        withoutSampleContent();
        post( array( 'post_title' => 'In the index' ) );

        $described = rest( 'POST', '/llms' )->get_data();

        // The post, and the full text and the sitemap under Optional.
        expect( $described['entries'] )->toBe( 3 )
            ->and( $described['sections'] )->toContain( 'Posts', 'Optional' )
            ->and( get_transient( ThatSeoAgent_Llms::CACHE_KEY ) )->toBe( $described['body'] );
    } );
} );

describe( 'POST /crawlers/probe', function () {
    it( 'asks the homepage as each crawler, and the bulletin names the ones turned away', function () {
        actingAs( 'administrator' );
        fakeOwnSite( array( 'OAI-SearchBot' ) );

        $result = rest( 'POST', '/crawlers/probe' )->get_data();
        ThatSeoAgent_Memo::reset();
        $warnings = ThatSeoAgent_Bulletin::get()['warnings'];

        expect( $result['robots'] )->toBe( 200 )
            ->and( $result['bots']['OAI-SearchBot']['reached'] )->toBeFalse()
            ->and( $result['bots']['GPTBot']['reached'] )->toBeTrue()
            ->and( implode( ' ', array_column( $warnings, 'title' ) ) )->toContain( 'OAI-SearchBot' );
    } );
} );

describe( 'POST /markdown/check', function () {
    it( 'tells what agents and browsers each get', function ( array $agent, array $browser, string $verdict ) {
        actingAs( 'administrator' );
        withoutSampleContent();
        post();
        fakeHttp( array(
            home_url( '/*' ) => function ( array $args ) use ( $agent, $browser ) {
                $asks_markdown = str_contains( (string) ( $args['headers']['Accept'] ?? '' ), 'text/markdown' );

                return $asks_markdown ? $agent : $browser;
            },
        ) );

        expect( rest( 'POST', '/markdown/check' )->get_data()['verdict'] )->toBe( $verdict );
    } )->with( array(
        'it works'                   => array(
            array( 'headers' => array( 'Content-Type' => 'text/markdown; charset=utf-8', 'Vary' => 'Accept' ), 'body' => '# Post' ),
            array( 'headers' => array( 'Content-Type' => 'text/html', 'Vary' => 'Accept' ), 'body' => '<html></html>' ),
            'works',
        ),
        'a cache gives agents HTML'  => array(
            array( 'headers' => array( 'Content-Type' => 'text/html' ), 'body' => '<html></html>' ),
            array( 'headers' => array( 'Content-Type' => 'text/html' ), 'body' => '<html></html>' ),
            'html_to_agents',
        ),
        'a cache gives browsers Markdown' => array(
            array( 'headers' => array( 'Content-Type' => 'text/markdown' ), 'body' => '# Post' ),
            array( 'headers' => array( 'Content-Type' => 'text/markdown' ), 'body' => '# Post' ),
            'markdown_to_browsers',
        ),
        'Cloudflare converted it'    => array(
            array( 'headers' => array( 'Content-Type' => 'text/markdown', 'X-Markdown-Tokens' => '120' ), 'body' => '# Post' ),
            array( 'headers' => array( 'Content-Type' => 'text/html' ), 'body' => '<html></html>' ),
            'cloudflare_markdown',
        ),
        'no answer'                  => array(
            array( 'code' => 0 ),
            array( 'code' => 0 ),
            'no_answer',
        ),
    ) );
} );
