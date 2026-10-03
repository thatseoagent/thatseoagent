<?php

/*
 * What the plugin does with the world outside the page: IndexNow, and the
 * rule it writes into .htaccess for page caches.
 */

/**
 * The queued IndexNow submissions.
 *
 * @return list<array<mixed>>
 */
function queuedSubmissions(): array {
    $queued = array();
    foreach ( (array) _get_cron_array() as $hooks ) {
        foreach ( $hooks[ ThatSeoAgent_IndexNow::CRON_HOOK ] ?? array() as $event ) {
            $queued[] = $event['args'];
        }
    }

    return $queued;
}

describe( 'IndexNow', function () {
    beforeEach( function () {
        wp_clear_scheduled_hook( ThatSeoAgent_IndexNow::CRON_HOOK );
    } );

    it( 'sends nothing, and queues nothing, without a key', function () {
        post();

        expect( queuedSubmissions() )->toBe( array() );
    } );

    it( 'queues a published post’s address, once, to send out of band', function () {
        update_option( ThatSeoAgent_IndexNow::OPTION_KEY, 'a1b2c3d4e5f6a7b8' );
        $post = post();
        wp_update_post( array( 'ID' => $post->ID, 'post_title' => 'Saved again' ) );

        expect( queuedSubmissions() )->toBe( array( array( array( get_permalink( $post ) ) ) ) )
            ->and( httpRequests() )->toBe( array() );
    } );

    it( 'queues no draft', function () {
        update_option( ThatSeoAgent_IndexNow::OPTION_KEY, 'a1b2c3d4e5f6a7b8' );
        post( array( 'post_status' => 'draft' ) );

        expect( queuedSubmissions() )->toBe( array() );
    } );

    it( 'sends the host, the key, where to find it and the addresses', function () {
        update_option( ThatSeoAgent_IndexNow::OPTION_KEY, 'a1b2c3d4e5f6a7b8' );
        fakeHttp( array( ThatSeoAgent_IndexNow::API_URL => array( 'code' => 202 ) ) );
        $post = post();

        $sent = ThatSeoAgent_IndexNow::submit_urls( array( get_permalink( $post ) ) );

        expect( $sent )->toBeTrue()
            ->and( httpRequests() )->toHaveCount( 1 )
            ->and( json_decode( httpRequests()[0]['body'], true ) )->toBe( array(
                'host'        => 'localhost',
                'key'         => 'a1b2c3d4e5f6a7b8',
                'keyLocation' => home_url( '/a1b2c3d4e5f6a7b8.txt' ),
                'urlList'     => array( get_permalink( $post ) ),
            ) );
    } );

    it( 'leaves out a post kept out of search by the time it sends', function () {
        update_option( ThatSeoAgent_IndexNow::OPTION_KEY, 'a1b2c3d4e5f6a7b8' );
        $post = post( array(), array( 'noindex' => '1' ) );

        expect( ThatSeoAgent_IndexNow::submit_urls( array( get_permalink( $post ) ) ) )->toBeTrue()
            ->and( httpRequests() )->toBe( array() );
    } );

    it( 'reports what the API answered when it refuses', function () {
        update_option( ThatSeoAgent_IndexNow::OPTION_KEY, 'a1b2c3d4e5f6a7b8' );
        fakeHttp( array( ThatSeoAgent_IndexNow::API_URL => array( 'code' => 403 ) ) );

        $error = ThatSeoAgent_IndexNow::submit_urls( array( get_permalink( post() ) ) );

        expect( $error->get_error_message() )->toBe( 'IndexNow API returned 403' );
    } );

    it( 'accepts only keys IndexNow does', function ( string $input, string $stored ) {
        update_option( ThatSeoAgent_IndexNow::OPTION_KEY, 'previouskey' );

        expect( ThatSeoAgent_IndexNow::sanitize_key_setting( $input ) )->toBe( $stored );
    } )->with( array(
        'a valid key'                => array( 'a1b2c3d4-e5f6', 'a1b2c3d4-e5f6' ),
        'stray characters stripped'  => array( 'a1b2 c3d4!e5f6', 'a1b2c3d4e5f6' ),
        'too short, the old one kept' => array( 'abc', 'previouskey' ),
        'empty, switched off'        => array( '', '' ),
    ) );
} );

describe( 'the .htaccess rule', function () {
    beforeEach( function () {
        $this->file = tempnam( sys_get_temp_dir(), 'htaccess' );
    } );

    afterEach( function () {
        @unlink( $this->file );
    } );

    it( 'goes at the very top, above a page cache’s rules, keeping them', function () {
        file_put_contents( $this->file, "# BEGIN WP Rocket\nRewriteRule ^ cached [L]\n# END WP Rocket\n\n# BEGIN WordPress\nRewriteRule . /index.php [L]\n# END WordPress\n" );

        ThatSeoAgent_Markdown_Htaccess::install( $this->file );
        $contents = file_get_contents( $this->file );

        expect( $contents )->toStartWith( '# BEGIN ThatSeoAgent Markdown' )
            ->and( $contents )->toContain( "# BEGIN WP Rocket\nRewriteRule ^ cached [L]\n# END WP Rocket" )
            ->and( $contents )->toContain( '# END WordPress' )
            ->and( ThatSeoAgent_Markdown_Htaccess::status( $this->file ) )->toMatchArray( array( 'present' => true, 'below' => '' ) );
    } );

    it( 'is written once, however often it is installed', function () {
        ThatSeoAgent_Markdown_Htaccess::install( $this->file );
        ThatSeoAgent_Markdown_Htaccess::install( $this->file );

        expect( substr_count( file_get_contents( $this->file ), '# BEGIN ThatSeoAgent Markdown' ) )->toBe( 1 );
    } );

    it( 'says when a cache plugin wrote its rules above it', function () {
        ThatSeoAgent_Markdown_Htaccess::install( $this->file );
        file_put_contents( $this->file, "# BEGIN WP Rocket\n# END WP Rocket\n" . file_get_contents( $this->file ) );

        expect( ThatSeoAgent_Markdown_Htaccess::status( $this->file )['below'] )->toBe( 'WP Rocket' );
    } );

    it( 'comes out leaving the rest of the file as it was', function () {
        $original = "# BEGIN WordPress\nRewriteRule . /index.php [L]\n# END WordPress\n";
        file_put_contents( $this->file, $original );

        ThatSeoAgent_Markdown_Htaccess::install( $this->file );
        ThatSeoAgent_Markdown_Htaccess::remove( $this->file );

        expect( file_get_contents( $this->file ) )->toBe( $original );
    } );

    it( 'sends requests asking for Markdown, and only those, to WordPress', function () {
        $lines = ThatSeoAgent_Markdown_Htaccess::lines();

        expect( $lines )->toContain( 'RewriteCond %{HTTP:Accept} text/(x-)?markdown [NC]' )
            ->and( implode( "\n", $lines ) )->toContain( '[END]' );
    } );
} );
