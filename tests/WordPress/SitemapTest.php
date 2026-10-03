<?php

/*
 * The sitemaps and robots.txt: what search engines are asked to index, and
 * where they are told to find it.
 */

/**
 * A sitemap's entries, read as a search engine reads them.
 *
 * @param array<string, mixed> $args
 * @return array{root: string, entries: list<array{loc: string, lastmod: string|null}>}
 */
function sitemap( string $which, array $args = array() ): array {
    $xml = ThatSeoAgent_Sitemap::render( $which, $args );
    expect( $xml )->toStartWith( '<?xml version="1.0" encoding="UTF-8"?>' );

    $document = simplexml_load_string( $xml );
    expect( $document )->not->toBeFalse( 'Not well-formed: ' . $xml );
    expect( $document->getNamespaces()[''] ?? null )->toBe( 'http://www.sitemaps.org/schemas/sitemap/0.9' );

    $entries = array();
    foreach ( $document->children() as $entry ) {
        $entries[] = array(
            'loc'     => (string) $entry->loc,
            'lastmod' => isset( $entry->lastmod ) ? (string) $entry->lastmod : null,
        );
    }

    return array(
        'root'    => $document->getName(),
        'entries' => $entries,
    );
}

/**
 * The URLs a sitemap lists.
 *
 * @param array<string, mixed> $args
 * @return list<string>
 */
function sitemapUrls( string $which, array $args = array() ): array {
    return array_column( sitemap( $which, $args )['entries'], 'loc' );
}

/**
 * robots.txt, as the site serves it.
 */
function robotsTxt(): string {
    ob_start();
    do_robots();

    return (string) ob_get_clean();
}

describe( 'the index', function () {
    it( 'lists the sitemaps of posts, pages, categories and tags', function () {
        post();
        $index = sitemap( 'index' );

        expect( $index['root'] )->toBe( 'sitemapindex' )
            ->and( array_column( $index['entries'], 'loc' ) )->toBe( array(
                home_url( '/sitemap-posts.xml' ),
                home_url( '/sitemap-pages.xml' ),
                home_url( '/sitemap-categories.xml' ),
                home_url( '/sitemap-tags.xml' ),
            ) );
    } );

    it( 'dates each sitemap by its latest change', function () {
        post( array( 'post_date' => '2026-01-10 09:00:00', 'post_date_gmt' => '2026-01-10 09:00:00' ) );
        $latest = post( array( 'post_date' => '2026-03-01 09:00:00', 'post_date_gmt' => '2026-03-01 09:00:00' ) );

        global $wpdb;
        $wpdb->query( "UPDATE {$wpdb->posts} SET post_modified = post_date, post_modified_gmt = post_date_gmt WHERE post_type = 'post'" );
        clean_post_cache( $latest->ID );
        $newest = $wpdb->get_var( "SELECT MAX(post_modified) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'" );

        $posts = sitemap( 'index' )['entries'][0];

        expect( $posts['lastmod'] )->toBe( mysql2date( 'c', $newest ) );
    } );

    it( 'leaves out a custom post type with nothing to list, and lists one with posts', function () {
        register_post_type( 'machine', array( 'public' => true, 'label' => 'Machines' ) );

        $empty = sitemapUrls( 'index' );
        post( array( 'post_type' => 'machine' ) );
        ThatSeoAgent_Memo::reset();
        $with = sitemapUrls( 'index' );

        unregister_post_type( 'machine' );

        expect( $empty )->not->toContain( home_url( '/sitemap-machine.xml' ) )
            ->and( $with )->toContain( home_url( '/sitemap-machine.xml' ) );
    } );
} );

describe( 'the posts sitemap', function () {
    it( 'lists published posts, with when each last changed', function () {
        $post = post();
        $entries = sitemap( 'posts' )['entries'];

        $entry = array_values( array_filter( $entries, fn ( $e ) => get_permalink( $post ) === $e['loc'] ) );

        expect( sitemap( 'posts' )['root'] )->toBe( 'urlset' )
            ->and( $entry )->toHaveCount( 1 )
            ->and( $entry[0]['lastmod'] )->toBe( get_the_modified_date( 'c', $post ) );
    } );

    it( 'leaves out what search engines should not index', function ( Closure $make ) {
        $post = $make();

        expect( sitemapUrls( 'posts' ) )->not->toContain( get_permalink( $post ) );
    } )->with( array(
        'kept out by hand'   => fn () => post( array(), array( 'noindex' => '1' ) ),
        'password-protected' => fn () => post( array( 'post_password' => 'secret' ) ),
        'private'            => fn () => post( array( 'post_status' => 'private' ) ),
        'a draft'            => fn () => post( array( 'post_status' => 'draft' ) ),
        'scheduled'          => fn () => post( array( 'post_status' => 'future', 'post_date' => '2099-01-01 00:00:00' ) ),
    ) );

    it( 'lists a post whose noindex field was saved empty, as the REST API leaves it', function () {
        $post = post();
        update_post_meta( $post->ID, ThatSeoAgent_Post_Seo::NOINDEX_KEY, '' );

        expect( sitemapUrls( 'posts' ) )->toContain( get_permalink( $post ) );
    } );

    it( 'escapes each address for XML', function () {
        $post = post( array( 'post_name' => 'tools-and-parts' ) );
        $odd  = add_query_arg( array( 'a' => '1', 'b' => '2' ), get_permalink( $post ) );
        $with_query = fn ( $link, $p ) => $p->ID === $post->ID ? $odd : $link;

        add_filter( 'post_link', $with_query, 10, 2 );
        $urls = sitemapUrls( 'posts' );
        remove_filter( 'post_link', $with_query, 10 );

        expect( $urls )->toContain( $odd );
    } );
} );

describe( 'the pages sitemap', function () {
    it( 'starts with the homepage and lists every page', function () {
        $page = page();
        $urls = sitemapUrls( 'pages' );

        expect( $urls[0] )->toBe( home_url( '/' ) )
            ->and( $urls )->toContain( get_permalink( $page ) );
    } );

    it( 'lists a static front page once, as the homepage', function () {
        $front = page( array( 'post_title' => 'Welcome' ) );
        update_option( 'show_on_front', 'page' );
        update_option( 'page_on_front', $front->ID );

        $urls = sitemapUrls( 'pages' );

        // Its permalink is the homepage's address.
        expect( get_permalink( $front ) )->toBe( home_url( '/' ) )
            ->and( array_count_values( $urls )[ home_url( '/' ) ] )->toBe( 1 )
            ->and( $urls )->not->toContain( home_url( '/welcome/' ) );
    } );

    it( 'leaves out a page kept out of search', function () {
        $page = page( array(), array( 'noindex' => '1' ) );

        expect( sitemapUrls( 'pages' ) )->not->toContain( get_permalink( $page ) );
    } );
} );

describe( 'the term sitemaps', function () {
    it( 'list categories and tags with posts, not empty ones', function () {
        $news  = term( 'News' );
        $empty = term( 'Empty' );
        $tag   = term( 'Drills', 'post_tag' );
        post( array( 'post_category' => array( $news->term_id ), 'tags_input' => array( 'Drills' ) ) );

        expect( sitemapUrls( 'categories' ) )->toContain( get_term_link( $news ) )
            ->not->toContain( get_term_link( $empty ) )
            ->and( sitemapUrls( 'tags' ) )->toContain( get_term_link( $tag ) );
    } );

    it( 'leave out a term kept out of search', function () {
        $news = term( 'News' );
        post( array( 'post_category' => array( $news->term_id ) ) );
        ThatSeoAgent_Term_Seo::save( $news, array( 'noindex' => '1' ) );

        expect( sitemapUrls( 'categories' ) )->not->toContain( get_term_link( $news ) );
    } );
} );

describe( 'a request', function () {
    it( 'finds each sitemap at its address', function ( string $path, string $which, int $page ) {
        serve( home_url( $path ) );

        expect( get_query_var( 'thatseoagent_sitemap' ) )->toBe( $which )
            ->and( max( 1, (int) get_query_var( 'sitemap_page', 1 ) ) )->toBe( $page );
    } )->with( array(
        array( '/sitemap.xml', 'index', 1 ),
        array( '/sitemap_index.xml', 'index', 1 ),
        array( '/sitemap-posts.xml', 'posts', 1 ),
        array( '/sitemap-posts-2.xml', 'posts', 2 ),
        array( '/sitemap-pages.xml', 'pages', 1 ),
        array( '/sitemap-categories.xml', 'categories', 1 ),
        array( '/sitemap-tags.xml', 'tags', 1 ),
    ) );

    it( 'gets nothing for a sitemap that does not exist', function () {
        expect( ThatSeoAgent_Sitemap::render( 'nonsense' ) )->toBe( '' )
            ->and( ThatSeoAgent_Sitemap::render( 'cpt', array( 'post_type' => 'no-such-type' ) ) )->toBe( '' );
    } );

    it( 'has WordPress’s own sitemaps switched off', function () {
        expect( wp_sitemaps_get_server()->sitemaps_enabled() )->toBeFalse();
    } );
} );

describe( 'robots.txt', function () {
    it( 'points at the sitemap index, once', function () {
        $robots = robotsTxt();

        expect( substr_count( $robots, 'Sitemap:' ) )->toBe( 1 )
            ->and( $robots )->toContain( 'Sitemap: ' . home_url( '/sitemap.xml' ) )
            ->and( $robots )->not->toContain( 'wp-sitemap.xml' );
    } );

    it( 'drops stale Sitemap lines for this site, and keeps other sites’', function () {
        $stale = function ( $output ) {
            return $output . "\nSitemap: " . home_url( '/sitemap_index.xml' ) . "\nSitemap: https://cdn.example.com/sitemap.xml\n# START YOAST BLOCK\nUser-agent: *\n# END YOAST BLOCK\n";
        };
        add_filter( 'robots_txt', $stale, 10 );
        $robots = robotsTxt();
        remove_filter( 'robots_txt', $stale, 10 );

        expect( $robots )->not->toContain( 'sitemap_index.xml' )
            ->and( $robots )->toContain( 'Sitemap: https://cdn.example.com/sitemap.xml' )
            ->and( $robots )->not->toContain( 'YOAST' );
    } );

    it( 'keeps out the AI crawlers the site owner blocks, and no others', function () {
        update_option( ThatSeoAgent_AI_Crawlers::OPTION_KEY, array( 'training' => 'block' ) );
        $robots = robotsTxt();

        expect( ThatSeoAgent_Robots_Parser::check( $robots, 'GPTBot', '/' )['allowed'] )->toBeFalse()
            ->and( ThatSeoAgent_Robots_Parser::check( $robots, 'CCBot', '/' )['allowed'] )->toBeFalse()
            ->and( ThatSeoAgent_Robots_Parser::check( $robots, 'OAI-SearchBot', '/' )['allowed'] )->toBeTrue()
            ->and( ThatSeoAgent_Robots_Parser::check( $robots, 'Googlebot', '/' )['allowed'] )->toBeTrue()
            ->and( ThatSeoAgent_Robots_Parser::check( $robots, 'Googlebot', '/wp-admin/' )['allowed'] )->toBeFalse();
    } );

    it( 'lets a crawler excepted by hand through its blocked group', function () {
        update_option( ThatSeoAgent_AI_Crawlers::OPTION_KEY, array(
            'training' => 'block',
            'bots'     => array( 'GPTBot' => 'allow' ),
        ) );
        $robots = robotsTxt();

        expect( ThatSeoAgent_Robots_Parser::check( $robots, 'GPTBot', '/' )['allowed'] )->toBeTrue()
            ->and( ThatSeoAgent_Robots_Parser::check( $robots, 'ClaudeBot', '/' )['allowed'] )->toBeFalse();
    } );

    it( 'writes no crawler rules while the site discourages search engines', function () {
        update_option( 'blog_public', '0' );
        update_option( ThatSeoAgent_AI_Crawlers::OPTION_KEY, array( 'training' => 'block' ) );

        expect( robotsTxt() )->not->toContain( 'GPTBot' );
    } );
} );
