<?php

/*
 * What the site answers, headers included, to the requests that end before
 * any template: sitemaps, robots.txt, the Markdown versions, llms.txt and
 * the catalog file.
 */

describe( 'a page', function () {
    it( 'announces the sitemap and its Markdown version in its headers', function () {
        $post     = post( array( 'post_name' => 'headers-of-a-page' ) );
        $response = fetch( get_permalink( $post ) );

        expect( $response->status )->toBe( 200 )
            ->and( $response->headers( 'link' ) )->toContain( '<' . home_url( '/sitemap.xml' ) . '>; rel="sitemap"; type="application/xml"' )
            ->and( $response->headers( 'link' ) )->toContain( '<' . ThatSeoAgent_Markdown_Endpoint::url_for( $post ) . '>; rel="alternate"; type="text/markdown"' )
            ->and( $response->header( 'vary' ) )->toContain( 'Accept' );
    } );
} );

describe( 'the sitemaps', function () {
    it( 'are XML, kept out of search themselves', function ( string $path ) {
        $response = fetch( home_url( $path ) );

        expect( $response->status )->toBe( 200 )
            ->and( $response->header( 'content-type' ) )->toBe( 'application/xml; charset=UTF-8' )
            ->and( $response->header( 'x-robots-tag' ) )->toBe( 'noindex, follow' )
            ->and( simplexml_load_string( $response->body ) )->not->toBeFalse();
    } )->with( array( '/sitemap.xml', '/sitemap_index.xml', '/sitemap-posts.xml', '/sitemap-pages.xml', '/sitemap-categories.xml', '/sitemap-tags.xml' ) );

    it( 'list a post published a moment ago', function () {
        $post = post( array( 'post_name' => 'fresh-in-the-sitemap' ) );

        expect( fetch( home_url( '/sitemap-posts.xml' ) )->body )->toContain( '<loc>' . get_permalink( $post ) . '</loc>' );
    } );
} );

describe( 'robots.txt', function () {
    it( 'is served with the sitemap index', function () {
        $response = fetch( home_url( '/robots.txt' ) );

        expect( $response->status )->toBe( 200 )
            ->and( $response->body )->toContain( 'Sitemap: ' . home_url( '/sitemap.xml' ) );
    } );
} );

describe( 'a post’s .md address', function () {
    it( 'answers its Markdown, a copy for search engines to skip', function () {
        $post     = post( array( 'post_title' => 'Markdown over HTTP', 'post_name' => 'markdown-over-http' ) );
        $response = fetch( ThatSeoAgent_Markdown_Endpoint::url_for( $post ) );

        expect( $response->status )->toBe( 200 )
            ->and( $response->header( 'content-type' ) )->toBe( 'text/markdown; charset=utf-8' )
            ->and( $response->header( 'x-robots-tag' ) )->toBe( 'noindex' )
            ->and( $response->header( 'link' ) )->toBe( '<' . get_permalink( $post ) . '>; rel="canonical"' )
            ->and( $response->header( 'last-modified' ) )->toBe( get_post_modified_time( 'D, d M Y H:i:s', true, $post ) . ' GMT' )
            ->and( $response->body )->toStartWith( "---\ntitle: \"Markdown over HTTP\"" );
    } );

    it( 'answers 304 to a client whose copy is current', function () {
        $post = post( array( 'post_name' => 'markdown-not-modified' ) );
        $url  = ThatSeoAgent_Markdown_Endpoint::url_for( $post );
        $last = get_post_modified_time( 'D, d M Y H:i:s', true, $post ) . ' GMT';

        $current = fetch( $url, array( 'If-Modified-Since' => $last ) );
        $stale   = fetch( $url, array( 'If-Modified-Since' => 'Mon, 01 Jan 2001 00:00:00 GMT' ) );

        expect( $current->status )->toBe( 304 )
            ->and( $current->body )->toBe( '' )
            ->and( $stale->status )->toBe( 200 );
    } );

    it( 'says noindex and names no canonical for a post kept out of search', function () {
        $post     = post( array( 'post_name' => 'markdown-kept-out' ), array( 'noindex' => '1' ) );
        $response = fetch( untrailingslashit( get_permalink( $post ) ) . '.md' );

        expect( $response->status )->toBe( 200 )
            ->and( $response->header( 'x-robots-tag' ) )->toBe( 'noindex' )
            ->and( $response->headers( 'link' ) )->toBe( array() );
    } );

    it( 'is not found for an address that is no post', function () {
        $response = fetch( home_url( '/no-such-post-anywhere.md' ) );

        expect( $response->status )->toBe( 404 )
            ->and( $response->header( 'content-type' ) )->toBe( 'text/plain; charset=utf-8' )
            ->and( $response->header( 'x-robots-tag' ) )->toBe( 'noindex' );
    } );

    it( 'is refused for a post the visitor may not read', function ( Closure $make ) {
        $post = $make();
        $url  = untrailingslashit( get_permalink( $post ) ) . '.md';

        expect( fetch( $url )->status )->toBeIn( array( 403, 404 ) )
            ->and( fetch( $url )->body )->not->toContain( 'The secret part' );
    } )->with( array(
        'password-protected' => fn () => post( array( 'post_name' => 'md-password', 'post_password' => 'secret', 'post_content' => '<p>The secret part of the post.</p>' ) ),
        'private'            => fn () => post( array( 'post_name' => 'md-private', 'post_status' => 'private', 'post_content' => '<p>The secret part of the post.</p>' ) ),
    ) );
} );

describe( 'a post’s own address, asked for Markdown', function () {
    it( 'answers Markdown to an agent, kept apart from the HTML by caches', function () {
        $post     = post( array( 'post_title' => 'Negotiated', 'post_name' => 'negotiated-markdown' ) );
        $response = fetch( get_permalink( $post ), array( 'Accept' => 'text/markdown' ) );

        expect( $response->status )->toBe( 200 )
            ->and( $response->header( 'content-type' ) )->toBe( 'text/markdown; charset=utf-8' )
            ->and( $response->header( 'vary' ) )->toContain( 'Accept' )
            ->and( $response->header( 'cache-control' ) )->toBe( 'private, no-cache' )
            ->and( $response->header( 'content-location' ) )->toBe( ThatSeoAgent_Markdown_Endpoint::url_for( $post ) )
            ->and( $response->header( 'x-robots-tag' ) )->toBeNull()
            ->and( $response->body )->toStartWith( "---\ntitle: \"Negotiated\"" );
    } );

    it( 'answers HTML to a browser', function () {
        $post     = post( array( 'post_name' => 'negotiated-browser' ) );
        $response = fetch( get_permalink( $post ), array( 'Accept' => 'text/html,application/xhtml+xml,*/*;q=0.8' ) );

        expect( $response->header( 'content-type' ) )->toContain( 'text/html' )
            ->and( $response->body )->toContain( '<html' );
    } );

    it( 'says noindex for a post kept out of search, as its HTML does', function () {
        $post     = post( array( 'post_name' => 'negotiated-kept-out' ), array( 'noindex' => '1' ) );
        $response = fetch( get_permalink( $post ), array( 'Accept' => 'text/markdown' ) );

        expect( $response->header( 'x-robots-tag' ) )->toBe( 'noindex' );
    } );

    it( 'never answers Markdown for a password-protected post', function () {
        $post     = post( array( 'post_name' => 'negotiated-password', 'post_password' => 'secret' ) );
        $response = fetch( get_permalink( $post ), array( 'Accept' => 'text/markdown' ) );

        expect( $response->header( 'content-type' ) )->toContain( 'text/html' );
    } );
} );

describe( 'llms.txt', function () {
    it( 'lists the site’s posts for agents, kept out of search itself', function () {
        $post     = post( array( 'post_title' => 'Listed for agents', 'post_name' => 'listed-for-agents' ) );
        $response = fetch( home_url( '/llms.txt' ) );

        expect( $response->status )->toBe( 200 )
            ->and( $response->header( 'content-type' ) )->toBe( 'text/plain; charset=utf-8' )
            ->and( $response->header( 'x-robots-tag' ) )->toBe( 'noindex' )
            ->and( $response->body )->toContain( '[Listed for agents](' . ThatSeoAgent_Markdown_Endpoint::url_for( $post ) . ')' );
    } );

    it( 'is not found when switched off, nor is llms-full.txt', function () {
        update_option( ThatSeoAgent_Llms::OPTION_KEY, false );

        expect( fetch( home_url( '/llms.txt' ) )->status )->toBe( 404 )
            ->and( fetch( home_url( '/llms-full.txt' ) )->status )->toBe( 404 );
    } );

    it( 'has a full-text companion', function () {
        post( array( 'post_title' => 'In the full text', 'post_name' => 'in-the-full-text', 'post_content' => '<p>The whole body of this post, for agents.</p>' ) );
        $response = fetch( home_url( '/llms-full.txt' ) );

        expect( $response->status )->toBe( 200 )
            ->and( $response->header( 'x-robots-tag' ) )->toBe( 'noindex' )
            ->and( $response->body )->toContain( 'The whole body of this post, for agents.' );
    } );
} );

describe( 'the catalog file', function () {
    it( 'is not found on a site with no catalog', function () {
        expect( fetch( home_url( '/catalog.jsonl' ) )->status )->toBe( 404 );
    } );
} );

describe( 'an attachment page', function () {
    it( 'sends the visitor to the file', function () {
        $image = image();
        $page  = get_attachment_link( $image );

        $response = fetch( $page );

        expect( $response->status )->toBe( 301 )
            ->and( $response->header( 'location' ) )->toBe( wp_get_attachment_url( $image ) );
    } );
} );

describe( 'the IndexNow key file', function () {
    it( 'is served at the key’s address, and nothing else is', function () {
        update_option( ThatSeoAgent_IndexNow::OPTION_KEY, 'a1b2c3d4e5f6a7b8' );

        $key   = fetch( home_url( '/a1b2c3d4e5f6a7b8.txt' ) );
        $other = fetch( home_url( '/ffffffffffffffff.txt' ) );

        expect( $key->status )->toBe( 200 )
            ->and( $key->body )->toBe( 'a1b2c3d4e5f6a7b8' )
            ->and( $other->status )->toBe( 404 );
    } );
} );
