<?php

/*
 * llms.txt and llms-full.txt: the site's index for AI agents, and its full
 * text in one file.
 */

/**
 * The lines of llms.txt under one `##` heading.
 *
 * @return list<string>
 */
function llmsSection( string $body, string $heading ): array {
    $lines   = explode( "\n", $body );
    $start   = array_search( "## $heading", $lines, true );
    $section = array();

    if ( false === $start ) {
        return $section;
    }

    foreach ( array_slice( $lines, $start + 1 ) as $line ) {
        if ( str_starts_with( $line, '## ' ) ) {
            break;
        }
        if ( '' !== $line ) {
            $section[] = $line;
        }
    }

    return $section;
}

describe( 'llms.txt', function () {
    it( 'opens with the site’s name and what it is about', function () {
        update_option( 'blogname', 'Tools & Co' );
        update_option( 'blogdescription', 'Drills, bits & advice' );
        $lines = explode( "\n", ThatSeoAgent_Llms::build() );

        expect( array_slice( $lines, 0, 3 ) )->toBe( array( '# Tools & Co', '', '> Drills, bits & advice' ) );
    } );

    it( 'says the identity’s description before the tagline', function () {
        update_option( 'blogdescription', 'The tagline' );
        update_option( ThatSeoAgent_Identity::OPTION_KEY, array( 'description' => 'What the site is, in a sentence.' ) );

        expect( ThatSeoAgent_Llms::build() )->toContain( "\n> What the site is, in a sentence.\n" );
    } );

    it( 'lists each post with its Markdown version and its description', function () {
        withoutSampleContent();
        $post = post( array( 'post_title' => 'Choosing a [good] drill' ), array( 'description' => 'Which drill for which job.' ) );

        expect( llmsSection( ThatSeoAgent_Llms::build(), 'Posts' ) )->toBe( array(
            '- [Choosing a (good) drill](' . ThatSeoAgent_Markdown_Endpoint::url_for( $post ) . '): Which drill for which job.',
        ) );
    } );

    it( 'lists posts newest first, and pages in menu order', function () {
        withoutSampleContent();
        post( array( 'post_title' => 'Older', 'post_date' => '2026-01-01 00:00:00' ) );
        post( array( 'post_title' => 'Newer', 'post_date' => '2026-02-01 00:00:00' ) );
        page( array( 'post_title' => 'Second', 'menu_order' => 2 ) );
        page( array( 'post_title' => 'First', 'menu_order' => 1 ) );

        $body  = ThatSeoAgent_Llms::build();
        $names = fn ( array $lines ) => array_map( fn ( $line ) => preg_replace( '/^- \[([^\]]+)\].*$/', '$1', $line ), $lines );

        expect( $names( llmsSection( $body, 'Posts' ) ) )->toBe( array( 'Newer', 'Older' ) )
            ->and( $names( llmsSection( $body, 'Pages' ) ) )->toBe( array( 'First', 'Second' ) );
    } );

    it( 'leaves out what search engines are not to index, and the blog’s own page', function () {
        withoutSampleContent();
        $kept   = post( array( 'post_title' => 'Kept out' ), array( 'noindex' => '1' ) );
        $locked = post( array( 'post_title' => 'Locked', 'post_password' => 'secret' ) );
        $draft  = post( array( 'post_title' => 'Draft', 'post_status' => 'draft' ) );
        $blog   = page( array( 'post_title' => 'Blog' ) );
        update_option( 'show_on_front', 'page' );
        update_option( 'page_on_front', page( array( 'post_title' => 'Welcome' ) )->ID );
        update_option( 'page_for_posts', $blog->ID );

        $body = ThatSeoAgent_Llms::build();

        expect( $body )->not->toContain( 'Kept out' )
            ->and( $body )->not->toContain( 'Locked' )
            ->and( $body )->not->toContain( 'Draft' )
            ->and( $body )->not->toContain( '[Blog]' );
    } );

    it( 'puts the about, contact and privacy pages first, and only there', function () {
        withoutSampleContent();
        $about = page( array( 'post_title' => 'About us', 'post_name' => 'about' ) );
        $body  = ThatSeoAgent_Llms::build();

        expect( llmsSection( $body, 'About the site' ) )->toBe( array(
            '- [About us](' . ThatSeoAgent_Markdown_Endpoint::url_for( $about ) . '): ' . ThatSeoAgent_Description::for_post( $about ),
        ) )
            ->and( llmsSection( $body, 'Pages' ) )->toBe( array() );
    } );

    it( 'ends pointing at the full text and the sitemap', function () {
        expect( llmsSection( ThatSeoAgent_Llms::build(), 'Optional' ) )->toBe( array(
            '- [Full text](' . home_url( '/llms-full.txt' ) . '): every page above with a Markdown version, in one file',
            '- [Sitemap](' . home_url( '/sitemap.xml' ) . ')',
        ) );
    } );

    it( 'lists as many posts of a type as its limit allows', function () {
        withoutSampleContent();
        foreach ( range( 1, 4 ) as $n ) {
            post( array( 'post_title' => "Post $n" ) );
        }
        $two = fn () => 2;

        add_filter( 'thatseoagent_llms_txt_limit', $two );
        $section = llmsSection( ThatSeoAgent_Llms::build(), 'Posts' );
        remove_filter( 'thatseoagent_llms_txt_limit', $two );

        expect( $section )->toHaveCount( 2 );
    } );

    it( 'is built again once a post changes', function () {
        withoutSampleContent();
        $post = post( array( 'post_title' => 'Before' ) );
        set_transient( ThatSeoAgent_Llms::CACHE_KEY, ThatSeoAgent_Llms::build(), DAY_IN_SECONDS );

        wp_update_post( array( 'ID' => $post->ID, 'post_title' => 'After' ) );

        expect( get_transient( ThatSeoAgent_Llms::CACHE_KEY ) )->toBeFalse();
    } );

    it( 'is built again once a post’s SEO fields change', function () {
        $post = post();
        set_transient( ThatSeoAgent_Llms::CACHE_KEY, 'cached', DAY_IN_SECONDS );

        ThatSeoAgent_Post_Seo::save( $post, array( 'noindex' => '1' ) );

        expect( get_transient( ThatSeoAgent_Llms::CACHE_KEY ) )->toBeFalse();
    } );
} );

describe( 'llms-full.txt', function () {
    it( 'holds each listed page’s title, address and content', function () {
        withoutSampleContent();
        $post = post( array( 'post_title' => 'Choosing a drill', 'post_content' => '<p>A cordless drill does most jobs.</p>' ) );

        $body = ThatSeoAgent_Llms_Full::build();

        expect( $body )->toContain( "---\n\n# Choosing a drill\n\nSource: " . get_permalink( $post ) . "\n\nA cordless drill does most jobs.\n\n" )
            ->and( $body )->not->toContain( 'title: "' );
    } );

    it( 'leaves out the posts llms.txt leaves out', function () {
        withoutSampleContent();
        post( array( 'post_title' => 'Kept out', 'post_content' => '<p>Not for agents.</p>' ), array( 'noindex' => '1' ) );

        expect( ThatSeoAgent_Llms_Full::build() )->not->toContain( 'Not for agents.' );
    } );

    it( 'stops before its size limit, whole pages only, and says how many are left', function () {
        withoutSampleContent();
        foreach ( range( 1, 5 ) as $n ) {
            post( array(
                'post_title'   => "Long post $n",
                'post_date'    => "2026-01-0$n 00:00:00",
                'post_content' => '<p>' . str_repeat( "Sentence number $n. ", 40 ) . '</p>',
            ) );
        }
        $small = fn () => 2048;

        add_filter( 'thatseoagent_llms_full_max_bytes', $small );
        $body = ThatSeoAgent_Llms_Full::build();
        remove_filter( 'thatseoagent_llms_full_max_bytes', $small );

        preg_match( '/(\d+) more pages? did not fit/', $body, $left );
        $kept = substr_count( $body, '# Long post' );

        expect( strlen( $body ) )->toBeLessThan( 2048 + 300 )
            ->and( $kept )->toBeGreaterThan( 0 )
            ->and( (int) $left[1] )->toBe( 5 - $kept )
            ->and( $body )->toContain( home_url( '/llms.txt' ) );
    } );
} );
