<?php

/*
 * The Markdown version of a post: its frontmatter, its content, its address
 * and who is asked to read it.
 */

/**
 * A post's Markdown, split into its frontmatter fields and its body.
 *
 * Reads the YAML the plugin writes: `key: "value"`, numbers and booleans,
 * lists of quoted strings and one level of quoted mappings.
 *
 * @return array{fields: array<string, mixed>, body: string}
 */
function markdownOf( WP_Post $post ): array {
    $markdown = ThatSeoAgent_Markdown::convert( $post );
    expect( $markdown )->toStartWith( "---\n" );

    list( , $yaml, $body ) = explode( "---\n", $markdown, 3 );

    $unquote = fn ( string $value ) => json_decode( $value );
    $fields  = array();
    $current = null;
    foreach ( explode( "\n", rtrim( $yaml, "\n" ) ) as $line ) {
        if ( preg_match( '/^  - (".*")$/', $line, $m ) ) {
            $fields[ $current ][] = $unquote( $m[1] );
        } elseif ( preg_match( '/^  ("[^"]*"|[\w-]+): (".*")$/', $line, $m ) ) {
            $fields[ $current ][ trim( $m[1], '"' ) ] = $unquote( $m[2] );
        } elseif ( preg_match( '/^("(?:[^"\\\\]|\\\\.)*"|[\w-]+):\s*(.*)$/', $line, $m ) ) {
            $current = str_starts_with( $m[1], '"' ) ? $unquote( $m[1] ) : $m[1];
            $value   = $m[2];
            $fields[ $current ] = '' === $value ? array() : ( str_starts_with( $value, '"' ) ? $unquote( $value ) : $value );
        } else {
            throw new RuntimeException( "Not a frontmatter line: $line" );
        }
    }

    return array(
        'fields' => $fields,
        'body'   => ltrim( $body, "\n" ),
    );
}

describe( 'the frontmatter', function () {
    it( 'carries the post’s title, dates, author, address and description', function () {
        $post = post( array(
            'post_title'    => 'Choosing a drill',
            'post_date'     => '2026-01-10 09:00:00',
            'post_date_gmt' => '2026-01-10 09:00:00',
        ) );

        expect( markdownOf( $post )['fields'] )->toMatchArray( array(
            'title'       => 'Choosing a drill',
            'date'        => '2026-01-10T09:00:00+00:00',
            'author'      => 'admin',
            'permalink'   => get_permalink( $post ),
            'type'        => 'post',
            'description' => ThatSeoAgent_Description::for_post( $post ),
        ) );
    } );

    it( 'names its categories and tags', function () {
        $news = term( 'News' );
        $post = post( array( 'post_category' => array( $news->term_id ), 'tags_input' => array( 'Drills', 'Bits' ) ) );

        $fields = markdownOf( $post )['fields'];

        expect( $fields['categories'] )->toBe( array( 'News' ) )
            ->and( $fields['tags'] )->toEqualCanonicalizing( array( 'Drills', 'Bits' ) );
    } );

    it( 'keeps quotes, backslashes and new lines inside their value', function () {
        $post = post( array( 'post_title' => 'A "quoted" title' ), array( 'description' => "Two lines\nwith a \\ backslash" ) );

        $fields = markdownOf( $post )['fields'];

        expect( $fields['title'] )->toBe( 'A "quoted" title' )
            ->and( $fields['description'] )->toBe( "Two lines\nwith a \\ backslash" );
    } );

    it( 'says names as plain text, never as HTML entities', function () {
        wp_update_user( array( 'ID' => 1, 'display_name' => 'Ana & Bob' ) );
        $news = term( 'News & views' );
        $post = post( array( 'post_title' => 'Bob\'s drills & bits', 'post_category' => array( $news->term_id ), 'tags_input' => array( 'Nuts & bolts' ) ) );

        $fields = markdownOf( $post )['fields'];

        expect( array( $fields['title'], $fields['author'], $fields['categories'][0], $fields['tags'][0] ) )
            ->toBe( array( 'Bob\'s drills & bits', 'Ana & Bob', 'News & views', 'Nuts & bolts' ) );
    } );

    it( 'shows the post’s own image', function () {
        $image = image( 'A red drill' );
        $post  = post();
        set_post_thumbnail( $post, $image );

        expect( markdownOf( $post )['fields'] )->toMatchArray( array(
            'image'     => wp_get_attachment_url( $image ),
            'image_alt' => 'A red drill',
        ) );
    } );

    it( 'leaves custom fields out unless asked, and then the protected ones still out', function () {
        $post = post();
        add_post_meta( $post->ID, 'price', '120' );
        add_post_meta( $post->ID, '_secret', 'token' );
        add_post_meta( $post->ID, 'config', array( 'a' => 1 ) );

        $default = markdownOf( $post )['fields'];

        add_filter( 'thatseoagent_markdown_include_custom_fields', '__return_true' );
        $with = markdownOf( $post )['fields'];
        remove_filter( 'thatseoagent_markdown_include_custom_fields', '__return_true' );

        expect( $default )->not->toHaveKey( 'custom_fields' )
            ->and( $with['custom_fields'] )->toBe( array( 'price' => '120' ) );
    } );

    it( 'quotes a key that could break the frontmatter', function () {
        $odd = fn ( $fields ) => $fields + array( "evil: key\nadmin" => 'yes' );
        add_filter( 'thatseoagent_markdown_frontmatter', $odd );
        $fields = markdownOf( post() )['fields'];
        remove_filter( 'thatseoagent_markdown_frontmatter', $odd );

        expect( $fields )->toHaveKey( "evil: key\nadmin" )
            ->and( $fields )->not->toHaveKey( 'admin' );
    } );
} );

describe( 'the content', function () {
    it( 'is the rendered post as Markdown, without scripts or styles', function () {
        // Someone allowed to write scripts, or WordPress strips them first.
        wp_set_current_user( 1 );
        $post = post( array( 'post_content' => '<!-- wp:heading --><h2 class="wp-block-heading">Which one</h2><!-- /wp:heading --><!-- wp:paragraph --><p>A <strong>cordless</strong> drill, see <a href="https://example.com/">this</a>.</p><!-- /wp:paragraph --><!-- wp:list --><ul><li>Light</li><li>Fast</li></ul><!-- /wp:list --><script>alert(1)</script><style>p{}</style>' ) );

        wp_set_current_user( 0 );
        $body = markdownOf( $post )['body'];

        expect( $post->post_content )->toContain( '<script>' )
            ->and( $body )->toContain( '## Which one' )
            ->and( $body )->toContain( 'A **cordless** drill, see [this](https://example.com/).' )
            ->and( $body )->toContain( '- Light' )
            ->and( $body )->not->toContain( 'alert' )
            ->and( $body )->not->toContain( 'p{}' )
            ->and( $body )->not->toMatch( '/\n{3,}/' );
    } );
} );

describe( 'the address', function () {
    it( 'is the permalink with .md', function () {
        $post = post( array( 'post_name' => 'choosing-a-drill' ) );

        expect( ThatSeoAgent_Markdown_Endpoint::url_for( $post ) )->toBe( untrailingslashit( get_permalink( $post ) ) . '.md' );
    } );

    it( 'is the front page’s own path, not the homepage’s', function () {
        $front = page( array( 'post_title' => 'Welcome', 'post_name' => 'welcome' ) );
        update_option( 'show_on_front', 'page' );
        update_option( 'page_on_front', $front->ID );

        expect( ThatSeoAgent_Markdown_Endpoint::url_for( $front ) )->toBe( home_url( '/welcome.md' ) );
    } );

    it( 'is none for a post nobody may read as Markdown', function ( Closure $make ) {
        expect( ThatSeoAgent_Markdown_Endpoint::url_for( $make() ) )->toBe( '' );
    } )->with( array(
        'a draft'            => fn () => post( array( 'post_status' => 'draft' ) ),
        'private'            => fn () => post( array( 'post_status' => 'private' ) ),
        'password-protected' => fn () => post( array( 'post_password' => 'secret' ) ),
        'an attachment'      => fn () => get_post( image() ),
    ) );

    it( 'is none without pretty permalinks', function () {
        $post = post();
        update_option( 'permalink_structure', '' );

        expect( ThatSeoAgent_Markdown_Endpoint::url_for( $post ) )->toBe( '' );
    } );

    it( 'resolves to the query var the endpoint answers', function ( Closure $path ) {
        remove_action( 'parse_request', array( 'ThatSeoAgent_Markdown_Endpoint', 'handle_request' ) );
        serve( $path() );
        add_action( 'parse_request', array( 'ThatSeoAgent_Markdown_Endpoint', 'handle_request' ) );

        expect( get_query_var( 'thatseoagent_markdown' ) )->not->toBe( '' );
    } )->with( array(
        'a post'       => fn () => ThatSeoAgent_Markdown_Endpoint::url_for( post() ),
        'a child page' => fn () => ThatSeoAgent_Markdown_Endpoint::url_for( page( array( 'post_parent' => page()->ID ) ) ),
    ) );
} );

describe( 'who gets Markdown', function () {
    it( 'is a client that prefers it to HTML', function ( string $accept, bool $markdown ) {
        expect( ThatSeoAgent_Markdown_Endpoint::prefers_markdown( $accept ) )->toBe( $markdown );
    } )->with( array(
        'only Markdown'                => array( 'text/markdown', true ),
        'the older type'               => array( 'text/x-markdown', true ),
        'Markdown first, HTML less'    => array( 'text/markdown, text/html;q=0.9', true ),
        'both at the same weight'      => array( 'text/html, text/markdown', true ),
        'in any case'                  => array( 'Text/Markdown', true ),
        'HTML preferred'               => array( 'text/markdown;q=0.5, text/html', false ),
        'a browser'                    => array( 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', false ),
        'any type'                     => array( '*/*', false ),
        'text of any kind'             => array( 'text/*', false ),
        'nothing said'                 => array( '', false ),
        'Markdown refused'             => array( 'text/markdown;q=0', false ),
    ) );
} );

describe( 'the cache', function () {
    it( 'serves what it stored, and rebuilds once the post changes', function () {
        $post = post( array( 'post_title' => 'Before' ) );
        $first = ThatSeoAgent_Markdown_Endpoint::markdown( $post );

        wp_update_post( array( 'ID' => $post->ID, 'post_title' => 'After' ) );
        $after = ThatSeoAgent_Markdown_Endpoint::markdown( get_post( $post->ID ) );

        expect( $first )->toContain( 'title: "Before"' )
            ->and( $after )->toContain( 'title: "After"' );
    } );

    it( 'rebuilds when the SEO description changes', function () {
        $post = post();
        ThatSeoAgent_Markdown_Endpoint::markdown( $post );

        ThatSeoAgent_Post_Seo::save( $post, array( 'description' => 'A new description.' ) );

        expect( ThatSeoAgent_Markdown_Endpoint::markdown( get_post( $post->ID ) ) )->toContain( 'description: "A new description."' );
    } );

    it( 'rebuilds when a category is renamed', function () {
        $news = term( 'News' );
        $post = post( array( 'post_category' => array( $news->term_id ) ) );
        ThatSeoAgent_Markdown_Endpoint::markdown( $post );

        wp_update_term( $news->term_id, 'category', array( 'name' => 'Updates' ) );

        expect( ThatSeoAgent_Markdown_Endpoint::markdown( get_post( $post->ID ) ) )->toContain( '"Updates"' );
    } );
} );
