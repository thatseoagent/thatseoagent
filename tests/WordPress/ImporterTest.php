<?php

/*
 * Moving a site's SEO from another plugin: each post's written title,
 * description, noindex and primary category, resolved to the text they
 * printed.
 */

/**
 * A post as another SEO plugin left it.
 *
 * @param array<string, mixed> $meta
 * @param array<string, mixed> $args
 */
function postFrom( array $meta, array $args = array() ): WP_Post {
    $post = post( $args + array( 'post_title' => 'Choosing a drill' ) );
    foreach ( $meta as $key => $value ) {
        update_post_meta( $post->ID, $key, $value );
    }

    return $post;
}

describe( 'from Yoast SEO', function () {
    it( 'brings the written title and description, their variables resolved', function () {
        update_option( 'wpseo_titles', array( 'separator' => 'sc-pipe' ) );
        $post = postFrom( array(
            '_yoast_wpseo_title'    => '%%title%% %%sep%% Buying guide %%sep%% %%sitename%%',
            '_yoast_wpseo_metadesc' => 'All about %%title%% &amp; more.',
        ) );

        $results = ThatSeoAgent_Importer::import_post( 'yoast', $post->ID );

        expect( $results['title'] )->toBe( array( 'status' => 'imported', 'value' => 'Choosing a drill | Buying guide | thatseoagent' ) )
            ->and( ThatSeoAgent_Post_Seo::get( $post, 'title' ) )->toBe( 'Choosing a drill | Buying guide | thatseoagent' )
            ->and( ThatSeoAgent_Post_Seo::get( $post, 'description' ) )->toBe( 'All about Choosing a drill & more.' );
    } );

    it( 'leaves out a title that only repeats what the post would say anyway', function () {
        $post = postFrom( array( '_yoast_wpseo_title' => '%%title%% %%sep%% %%sitename%%' ) );

        $results = ThatSeoAgent_Importer::import_post( 'yoast', $post->ID );

        expect( $results['title']['status'] )->toBe( 'default' )
            ->and( ThatSeoAgent_Post_Seo::get( $post, 'title' ) )->toBe( '' );
    } );

    it( 'leaves a template with a variable it cannot resolve alone', function () {
        $post = postFrom( array( '_yoast_wpseo_metadesc' => '%%cf_price%% and more' ) );

        $results = ThatSeoAgent_Importer::import_post( 'yoast', $post->ID );

        expect( $results['description'] )->toBe( array( 'status' => 'unresolved', 'value' => '%%cf_price%% and more' ) )
            ->and( ThatSeoAgent_Post_Seo::get( $post, 'description' ) )->toBe( '' );
    } );

    it( 'drops the separators an empty variable leaves dangling', function () {
        $post = postFrom( array( '_yoast_wpseo_title' => '%%title%% %%page%% %%sep%% %%sitedesc%%' ) );
        update_option( 'blogdescription', '' );

        expect( ThatSeoAgent_Importer::import_post( 'yoast', $post->ID, false, true )['title']['value'] )->not->toMatch( '/[|-]\s*$/' );
    } );

    it( 'keeps what ThatSeoAgent already has, unless told to overwrite', function () {
        $post = postFrom( array( '_yoast_wpseo_metadesc' => 'From Yoast.' ) );
        ThatSeoAgent_Post_Seo::save( $post, array( 'description' => 'Written here.' ) );

        $kept = ThatSeoAgent_Importer::import_post( 'yoast', $post->ID );
        $kept_value = ThatSeoAgent_Post_Seo::get( $post, 'description' );
        ThatSeoAgent_Importer::import_post( 'yoast', $post->ID, true );

        expect( $kept['description']['status'] )->toBe( 'exists' )
            ->and( $kept_value )->toBe( 'Written here.' )
            ->and( ThatSeoAgent_Post_Seo::get( $post, 'description' ) )->toBe( 'From Yoast.' );
    } );

    it( 'writes nothing on a dry run', function () {
        $post = postFrom( array( '_yoast_wpseo_metadesc' => 'From Yoast.', '_yoast_wpseo_meta-robots-noindex' => '1' ) );

        $results = ThatSeoAgent_Importer::import_post( 'yoast', $post->ID, false, true );

        expect( $results['description']['status'] )->toBe( 'imported' )
            ->and( ThatSeoAgent_Post_Seo::all( $post ) )->toMatchArray( array( 'description' => '', 'noindex' => '' ) );
    } );

    it( 'keeps out of search what Yoast kept out, and nothing else', function ( string $stored, string $noindex ) {
        $post = postFrom( array( '_yoast_wpseo_meta-robots-noindex' => $stored, '_yoast_wpseo_metadesc' => 'Something.' ) );

        ThatSeoAgent_Importer::import_post( 'yoast', $post->ID );

        expect( ThatSeoAgent_Post_Seo::get( $post, 'noindex' ) )->toBe( $noindex );
    } )->with( array(
        'noindex'        => array( '1', '1' ),
        'index'          => array( '2', '' ),
        'type’s default' => array( '0', '' ),
    ) );

    it( 'brings the primary category the post still has', function () {
        $news    = term( 'News' );
        $reviews = term( 'Reviews' );
        $post    = postFrom( array( '_yoast_wpseo_primary_category' => $reviews->term_id ), array( 'post_category' => array( $news->term_id, $reviews->term_id ) ) );

        $results = ThatSeoAgent_Importer::import_post( 'yoast', $post->ID );

        expect( $results['primary_category']['status'] )->toBe( 'imported' )
            ->and( ThatSeoAgent_Primary_Term::get( $post, 'category' )->term_id )->toBe( $reviews->term_id );
    } );

    it( 'finds the posts it has something for, of the types asked', function () {
        $with    = postFrom( array( '_yoast_wpseo_metadesc' => 'Something.' ) );
        $page    = postFrom( array( '_yoast_wpseo_metadesc' => 'Something.' ), array( 'post_type' => 'page' ) );
        $empty   = postFrom( array( '_yoast_wpseo_metadesc' => '' ) );
        $trashed = postFrom( array( '_yoast_wpseo_metadesc' => 'Something.' ), array( 'post_status' => 'trash' ) );

        $ids = ThatSeoAgent_Importer::post_ids( 'yoast', array( 'post' ) );

        expect( $ids )->toContain( $with->ID )
            ->not->toContain( $page->ID )
            ->not->toContain( $empty->ID )
            ->not->toContain( $trashed->ID );
    } );

    it( 'changes nothing the second time', function () {
        $post = postFrom( array( '_yoast_wpseo_title' => 'A title of its own', '_yoast_wpseo_metadesc' => 'From Yoast.' ) );

        ThatSeoAgent_Importer::import_post( 'yoast', $post->ID );
        $again = ThatSeoAgent_Importer::import_post( 'yoast', $post->ID );

        expect( array_column( array_intersect_key( $again, array_flip( array( 'title', 'description' ) ) ), 'status' ) )->toBe( array( 'exists', 'exists' ) );
    } );
} );

describe( 'from Rank Math', function () {
    it( 'resolves its own variables and its robots list', function () {
        $post = postFrom( array(
            'rank_math_title'       => '%title% by %org_name%',
            'rank_math_description' => 'About %title%.',
            'rank_math_robots'      => array( 'noindex', 'nofollow' ),
        ) );

        ThatSeoAgent_Importer::import_post( 'rankmath', $post->ID );

        expect( ThatSeoAgent_Post_Seo::all( $post ) )->toMatchArray( array(
            'title'       => 'Choosing a drill by thatseoagent',
            'description' => 'About Choosing a drill.',
            'noindex'     => '1',
        ) );
    } );
} );
