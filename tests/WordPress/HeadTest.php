<?php

/*
 * What each kind of page says in its <head>: title, description, Open
 * Graph, Twitter, canonical, robots and the links beside them.
 */

describe( 'a post', function () {
    it( 'says its title, description and address once each, the same everywhere', function () {
        $post = post( array( 'post_title' => 'Choosing a drill' ) );
        $page = pageAt( get_permalink( $post ) );

        expect( $page->title() )->toBe( 'Choosing a drill | thatseoagent' )
            ->and( $page->metas( 'og:title' ) )->toBe( array( 'Choosing a drill | thatseoagent' ) )
            ->and( $page->metas( 'description' ) )->toBe( array( 'Some content for the post, long enough to say something about it.' ) )
            ->and( $page->meta( 'og:description' ) )->toBe( $page->meta( 'description' ) )
            ->and( $page->link( 'canonical' ) )->toBe( get_permalink( $post ) )
            ->and( $page->meta( 'og:url' ) )->toBe( get_permalink( $post ) )
            ->and( $page->meta( 'og:type' ) )->toBe( 'article' )
            ->and( $page->meta( 'og:site_name' ) )->toBe( 'thatseoagent' )
            ->and( $page->meta( 'og:locale' ) )->toBe( 'en_US' )
            ->and( $page->meta( 'twitter:card' ) )->toBe( 'summary_large_image' );
    } );

    it( 'uses its SEO title and description, as written', function () {
        $post = post( array( 'post_title' => 'Choosing a drill' ), array(
            'title'       => 'Drills: the buyer’s guide',
            'description' => 'Which drill for which job, & why.',
        ) );
        $page = pageAt( get_permalink( $post ) );

        expect( $page->title() )->toBe( 'Drills: the buyer’s guide' )
            ->and( $page->meta( 'og:title' ) )->toBe( 'Drills: the buyer’s guide' )
            ->and( $page->meta( 'description' ) )->toBe( 'Which drill for which job, & why.' )
            ->and( $page->meta( 'og:description' ) )->toBe( 'Which drill for which job, & why.' );
    } );

    it( 'takes its description from the excerpt before the content', function () {
        $post = post( array( 'post_excerpt' => 'The short version, from the excerpt.' ) );

        expect( pageAt( get_permalink( $post ) )->meta( 'description' ) )->toBe( 'The short version, from the excerpt.' );
    } );

    it( 'prints no description when there is nothing to say', function () {
        $post = post( array( 'post_content' => '' ) );
        $page = pageAt( get_permalink( $post ) );

        expect( $page->meta( 'description' ) )->toBeNull()
            ->and( $page->meta( 'og:description' ) )->toBeNull();
    } );

    it( 'states its publication date, and its update only once it changed', function () {
        $post = post( array(
            'post_date_gmt' => '2026-01-10 09:00:00',
            'post_date'     => '2026-01-10 09:00:00',
        ) );
        $fresh = pageAt( get_permalink( $post ) );

        global $wpdb;
        $wpdb->update( $wpdb->posts, array( 'post_modified' => '2026-02-01 12:00:00', 'post_modified_gmt' => '2026-02-01 12:00:00' ), array( 'ID' => $post->ID ) );
        clean_post_cache( $post->ID );
        $updated = pageAt( get_permalink( $post ) );

        expect( $fresh->meta( 'article:published_time' ) )->toBe( '2026-01-10T09:00:00+00:00' )
            ->and( $fresh->meta( 'article:modified_time' ) )->toBeNull()
            ->and( $updated->meta( 'article:modified_time' ) )->toBe( '2026-02-01T12:00:00+00:00' );
    } );

    it( 'names its primary category as the section', function () {
        $news = term( 'News' );
        $post = post( array( 'post_category' => array( $news->term_id ) ) );

        expect( pageAt( get_permalink( $post ) )->meta( 'article:section' ) )->toBe( 'News' );
    } );

    it( 'keeps the query string a visitor arrived with out of og:url', function () {
        $post = post();
        $page = pageAt( add_query_arg( 'utm_source', 'newsletter', get_permalink( $post ) ) );

        expect( $page->meta( 'og:url' ) )->toBe( get_permalink( $post ) )
            ->and( $page->link( 'canonical' ) )->toBe( get_permalink( $post ) );
    } );

    it( 'announces its Markdown version', function () {
        $post  = post( array( 'post_name' => 'choosing-a-drill' ) );
        $links = pageAt( get_permalink( $post ) )->linkTags( 'alternate' );

        $markdown = array_values( array_filter( $links, fn ( $link ) => 'text/markdown' === ( $link['type'] ?? '' ) ) );

        expect( $markdown )->toHaveCount( 1 )
            ->and( $markdown[0]['href'] )->toBe( untrailingslashit( get_permalink( $post ) ) . '.md' );
    } );

    it( 'leaves the Twitter tags Open Graph already covers out, unless asked', function () {
        $post = post();

        $default = pageAt( get_permalink( $post ) );

        add_filter( 'thatseoagent_twitter_repeat_open_graph', '__return_true' );
        $repeated = pageAt( get_permalink( $post ) );
        remove_filter( 'thatseoagent_twitter_repeat_open_graph', '__return_true' );

        expect( $default->meta( 'twitter:title' ) )->toBeNull()
            ->and( $default->meta( 'twitter:description' ) )->toBeNull()
            ->and( $repeated->meta( 'twitter:title' ) )->toBe( $repeated->meta( 'og:title' ) )
            ->and( $repeated->meta( 'twitter:description' ) )->toBe( $repeated->meta( 'og:description' ) );
    } );

    it( 'names the site’s X account, with a single @', function ( string $handle ) {
        update_option( ThatSeoAgent_Identity::OPTION_KEY, array( 'twitter_handle' => $handle ) );

        expect( pageAt( get_permalink( post() ) )->meta( 'twitter:site' ) )->toBe( '@thatseoagent' );
    } )->with( array( 'thatseoagent', '@thatseoagent' ) );
} );

describe( 'the image a page is shared with', function () {
    it( 'is the featured image, with its size, type and alt text', function () {
        $image = image( 'A red drill' );
        $post  = post();
        set_post_thumbnail( $post, $image );
        $page = pageAt( get_permalink( $post ) );

        expect( $page->meta( 'og:image' ) )->toBe( wp_get_attachment_url( $image ) )
            ->and( $page->meta( 'og:image:width' ) )->toBe( '1200' )
            ->and( $page->meta( 'og:image:height' ) )->toBe( '630' )
            ->and( $page->meta( 'og:image:type' ) )->toBe( 'image/png' )
            ->and( $page->meta( 'og:image:alt' ) )->toBe( 'A red drill' )
            ->and( $page->meta( 'twitter:image:alt' ) )->toBe( 'A red drill' )
            ->and( $page->meta( 'og:pin:media' ) )->toBe( wp_get_attachment_url( $image ) );
    } );

    it( 'is the one chosen in the SEO fields before the featured image', function () {
        $featured = image();
        $chosen   = image();
        $post     = post( array(), array( 'share_image' => (string) $chosen ) );
        set_post_thumbnail( $post, $featured );

        expect( pageAt( get_permalink( $post ) )->meta( 'og:image' ) )->toBe( wp_get_attachment_url( $chosen ) );
    } );

    it( 'is the site’s default before the featured image', function () {
        $featured = image();
        $default  = image();
        update_option( ThatSeoAgent_Identity::OPTION_KEY, array( 'default_og_image_id' => $default ) );
        $post = post();
        set_post_thumbnail( $post, $featured );

        expect( pageAt( get_permalink( $post ) )->meta( 'og:image' ) )->toBe( wp_get_attachment_url( $default ) );
    } );

    it( 'is none on a page with no image and a site with none', function () {
        $page = pageAt( get_permalink( post() ) );

        expect( $page->meta( 'og:image' ) )->toBeNull()
            ->and( $page->meta( 'twitter:image:alt' ) )->toBeNull();
    } );
} );

describe( 'a page', function () {
    it( 'is not dated', function () {
        $page = pageAt( get_permalink( page() ) );

        expect( $page->meta( 'og:type' ) )->toBe( 'article' )
            ->and( $page->meta( 'article:published_time' ) )->toBeNull()
            ->and( $page->meta( 'og:pin:description' ) )->toBeNull();
    } );
} );

describe( 'the homepage', function () {
    it( 'lists the latest posts as the site, with the site’s name', function () {
        $page = pageAt( home_url( '/' ) );

        expect( $page->meta( 'og:type' ) )->toBe( 'website' )
            ->and( $page->link( 'canonical' ) )->toBe( home_url( '/' ) )
            ->and( $page->meta( 'og:url' ) )->toBe( home_url( '/' ) )
            ->and( $page->meta( 'og:title' ) )->toBe( $page->title() );
    } );

    it( 'says the homepage’s own title and description, with their variables', function () {
        update_option( 'blogdescription', 'Tools that last' );
        update_option( ThatSeoAgent_Homepage::OPTION_KEY, array(
            'title'       => '%%sitename%% %%sep%% %%tagline%%',
            'description' => 'Everything about %%sitename%%.',
        ) );
        $page = pageAt( home_url( '/' ) );

        expect( $page->title() )->toBe( 'thatseoagent | Tools that last' )
            ->and( $page->meta( 'og:title' ) )->toBe( 'thatseoagent | Tools that last' )
            ->and( $page->meta( 'description' ) )->toBe( 'Everything about thatseoagent.' );
    } );

    it( 'falls back to the tagline for its description', function () {
        update_option( 'blogdescription', 'Tools that last' );

        expect( pageAt( home_url( '/' ) )->meta( 'description' ) )->toBe( 'Tools that last' );
    } );

    it( 'says the same when it is a page', function () {
        $front = page( array( 'post_title' => 'Welcome' ), array( 'title' => 'The page’s own title' ) );
        update_option( 'show_on_front', 'page' );
        update_option( 'page_on_front', $front->ID );
        update_option( ThatSeoAgent_Homepage::OPTION_KEY, array( 'title' => 'The homepage’s title', 'description' => 'The homepage’s description.' ) );
        $page = pageAt( home_url( '/' ) );

        expect( $page->title() )->toBe( 'The homepage’s title' )
            ->and( $page->meta( 'description' ) )->toBe( 'The homepage’s description.' )
            ->and( $page->meta( 'og:type' ) )->toBe( 'website' )
            ->and( $page->link( 'canonical' ) )->toBe( home_url( '/' ) );
    } );

    it( 'leaves the blog’s own page its own canonical', function () {
        $front = page( array( 'post_title' => 'Welcome' ) );
        $blog  = page( array( 'post_title' => 'Blog' ) );
        update_option( 'show_on_front', 'page' );
        update_option( 'page_on_front', $front->ID );
        update_option( 'page_for_posts', $blog->ID );

        expect( pageAt( get_permalink( $blog ) )->link( 'canonical' ) )->toBe( get_permalink( $blog ) );
    } );
} );

describe( 'a category', function () {
    it( 'is its own canonical, and says its own title and description', function () {
        $news = term( 'News' );
        ThatSeoAgent_Term_Seo::save( $news, array( 'title' => 'All the news', 'description' => 'What happened this week.' ) );
        post( array( 'post_category' => array( $news->term_id ) ) );
        $page = pageAt( get_term_link( $news ) );

        expect( $page->link( 'canonical' ) )->toBe( get_term_link( $news ) )
            ->and( $page->title() )->toBe( 'All the news' )
            ->and( $page->meta( 'description' ) )->toBe( 'What happened this week.' )
            ->and( $page->meta( 'og:type' ) )->toBe( 'website' );
    } );

    it( 'is kept out of search when its SEO fields say so', function () {
        $news = term( 'News' );
        ThatSeoAgent_Term_Seo::save( $news, array( 'noindex' => '1' ) );
        post( array( 'post_category' => array( $news->term_id ) ) );
        $page = pageAt( get_term_link( $news ) );

        expect( $page->robots() )->toContain( 'noindex', 'follow' )
            ->and( $page->link( 'canonical' ) )->toBeNull();
    } );

    it( 'links each page of its listing to the ones beside it, each its own canonical', function () {
        update_option( 'posts_per_page', 1 );
        $news = term( 'News' );
        foreach ( range( 1, 3 ) as $n ) {
            post( array( 'post_title' => "News $n", 'post_category' => array( $news->term_id ) ) );
        }
        $base = get_term_link( $news );

        $first  = pageAt( $base );
        $second = pageAt( $base . 'page/2/' );
        $last   = pageAt( $base . 'page/3/' );

        expect( $first->link( 'canonical' ) )->toBe( $base )
            ->and( $first->links( 'prev' ) )->toBe( array() )
            ->and( $first->link( 'next' ) )->toBe( $base . 'page/2/' )
            ->and( $second->link( 'canonical' ) )->toBe( $base . 'page/2/' )
            ->and( $second->link( 'prev' ) )->toBe( $base )
            ->and( $second->link( 'next' ) )->toBe( $base . 'page/3/' )
            ->and( $last->link( 'prev' ) )->toBe( $base . 'page/2/' )
            ->and( $last->links( 'next' ) )->toBe( array() );
    } );
} );

describe( 'a post split into pages', function () {
    it( 'makes each page its own canonical, linked to the ones beside it', function () {
        $post = post( array( 'post_content' => '<p>One, the first part of it all.</p><!--nextpage--><p>Two.</p><!--nextpage--><p>Three.</p>' ) );
        $base = get_permalink( $post );

        $second = pageAt( $base . '2/' );

        expect( $second->link( 'canonical' ) )->toBe( $base . '2/' )
            ->and( $second->link( 'prev' ) )->toBe( $base )
            ->and( $second->link( 'next' ) )->toBe( $base . '3/' );
    } );
} );

describe( 'robots', function () {
    it( 'offers the largest previews on a page worth indexing', function () {
        $robots = pageAt( get_permalink( post() ) )->robots();

        expect( $robots )->toContain( 'max-image-preview:large', 'max-snippet:-1', 'max-video-preview:-1' )
            ->not->toContain( 'noindex' );
    } );

    it( 'keeps a page out of search, without a canonical or previews', function ( Closure $url ) {
        $page = pageAt( $url() );

        expect( $page->robots() )->toContain( 'noindex', 'follow' )
            ->not->toContain( 'max-image-preview:large' )
            ->and( $page->links( 'canonical' ) )->toBe( array() );
    } )->with( array(
        'search results'          => fn () => home_url( '/?s=drill' ),
        'the 404 page'            => fn () => home_url( '/no-such-page-here/' ),
        'a post kept out by hand' => fn () => get_permalink( post( array(), array( 'noindex' => '1' ) ) ),
        'a reply-to-comment link' => fn () => add_query_arg( 'replytocom', '1', get_permalink( post() ) ),
    ) );

    it( 'keeps a private post out of search', function () {
        wp_set_current_user( 1 );
        $post = post( array( 'post_status' => 'private' ) );
        $page = pageAt( get_permalink( $post ) );
        wp_set_current_user( 0 );

        expect( $page->robots() )->toContain( 'noindex', 'follow' )
            ->and( $page->links( 'canonical' ) )->toBe( array() );
    } );

    it( 'leaves nofollow to a site that discourages search engines', function () {
        update_option( 'blog_public', '0' );
        $robots = pageAt( get_permalink( post() ) )->robots();

        expect( $robots )->toContain( 'noindex', 'nofollow' )
            ->not->toContain( 'follow' )
            ->not->toContain( 'max-image-preview:large' );
    } );

    it( 'lets the thatseoagent_noindex filter keep a kind of page out', function () {
        $tag = term( 'Drills', 'post_tag' );
        post( array( 'tags_input' => array( 'Drills' ) ) );
        $noindex_tags = fn ( $noindex ) => $noindex || is_tag();

        add_filter( 'thatseoagent_noindex', $noindex_tags );
        $page = pageAt( get_term_link( $tag ) );
        remove_filter( 'thatseoagent_noindex', $noindex_tags );

        expect( $page->robots() )->toContain( 'noindex' );
    } );
} );

describe( 'search results', function () {
    it( 'say what was searched', function () {
        expect( pageAt( home_url( '/?s=drill' ) )->meta( 'description' ) )->toBe( 'Search results for "drill" on thatseoagent' );
    } );
} );

describe( 'an author', function () {
    it( 'is a profile, its own canonical', function () {
        post();
        $page = pageAt( get_author_posts_url( 1 ) );

        expect( $page->meta( 'og:type' ) )->toBe( 'profile' )
            ->and( $page->link( 'canonical' ) )->toBe( get_author_posts_url( 1 ) );
    } );
} );

describe( 'every page', function () {
    it( 'prints one canonical at most, and core’s is gone', function ( Closure $url ) {
        expect( count( pageAt( $url() )->links( 'canonical' ) ) )->toBeLessThanOrEqual( 1 );
    } )->with( array(
        'a post'     => fn () => get_permalink( post() ),
        'a page'     => fn () => get_permalink( page() ),
        'a category' => fn () => get_term_link( term( 'News' ) ),
        'the home'   => fn () => home_url( '/' ),
    ) );

    it( 'prints no description, og:title or og:url twice', function ( Closure $url ) {
        $page = pageAt( $url() );

        foreach ( array( 'description', 'og:title', 'og:url', 'og:type', 'og:description' ) as $key ) {
            expect( count( $page->metas( $key ) ) )->toBeLessThanOrEqual( 1, $key );
        }
    } )->with( array(
        'a post'   => fn () => get_permalink( post() ),
        'the home' => fn () => home_url( '/' ),
        'a search' => fn () => home_url( '/?s=x' ),
    ) );
} );
