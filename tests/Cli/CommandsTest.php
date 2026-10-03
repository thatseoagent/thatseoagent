<?php

/*
 * The WP-CLI commands, run as a person runs them: `wp thatseoagent …` in a
 * process of its own, against what the test wrote.
 */

/**
 * Runs `wp thatseoagent …` on the test site.
 *
 * @return array{status: int, out: string, err: string}
 */
function wpCli( string $arguments ): array {
    $process = proc_open(
        'wp thatseoagent ' . $arguments . ' --path=' . escapeshellarg( ABSPATH ),
        array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ),
        $pipes
    );
    $out = stream_get_contents( $pipes[1] );
    $err = stream_get_contents( $pipes[2] );
    fclose( $pipes[1] );
    fclose( $pipes[2] );

    $result = array( 'status' => proc_close( $process ), 'out' => (string) $out, 'err' => (string) $err );

    // The command wrote through its own connection.
    wp_cache_flush();

    return $result;
}

describe( 'generate-descriptions', function () {
    it( 'previews on a dry run, and saves nothing', function () {
        $post = post( array( 'post_title' => 'CLI preview', 'post_content' => '<p>A cordless drill does most jobs around the house and garden.</p>' ) );

        $run = wpCli( 'generate-descriptions --dry-run' );

        expect( $run['status'] )->toBe( 0, $run['err'] )
            ->and( $run['out'] )->toContain( 'A cordless drill does most jobs around the house and garden.' )
            ->and( get_post_meta( $post->ID, ThatSeoAgent_Post_Seo::DESCRIPTION_KEY, true ) )->toBe( '' );
    } );

    it( 'saves the missing descriptions, leaving the written ones alone', function () {
        $bare    = post( array( 'post_content' => '<p>A cordless drill does most jobs around the house and garden.</p>' ) );
        $written = post( array(), array( 'description' => 'Written by hand.' ) );

        $run = wpCli( 'generate-descriptions' );

        expect( $run['status'] )->toBe( 0, $run['err'] )
            ->and( get_post_meta( $bare->ID, ThatSeoAgent_Post_Seo::DESCRIPTION_KEY, true ) )->toBe( 'A cordless drill does most jobs around the house and garden.' )
            ->and( get_post_meta( $written->ID, ThatSeoAgent_Post_Seo::DESCRIPTION_KEY, true ) )->toBe( 'Written by hand.' );
    } );
} );

describe( 'import', function () {
    it( 'brings Yoast SEO’s fields', function () {
        $post = post();
        update_post_meta( $post->ID, '_yoast_wpseo_metadesc', 'From Yoast over the command line.' );

        $run = wpCli( 'import --from=yoast' );

        expect( $run['status'] )->toBe( 0, $run['err'] )
            ->and( get_post_meta( $post->ID, ThatSeoAgent_Post_Seo::DESCRIPTION_KEY, true ) )->toBe( 'From Yoast over the command line.' );
    } );

    it( 'shows what it would import on a dry run', function () {
        $post = post();
        update_post_meta( $post->ID, '_yoast_wpseo_metadesc', 'Only previewed.' );

        $run = wpCli( 'import --from=yoast --dry-run' );

        expect( $run['status'] )->toBe( 0, $run['err'] )
            ->and( $run['out'] . $run['err'] )->toContain( 'Only previewed.' )
            ->and( get_post_meta( $post->ID, ThatSeoAgent_Post_Seo::DESCRIPTION_KEY, true ) )->toBe( '' );
    } );

    it( 'refuses a plugin it cannot import from', function () {
        expect( wpCli( 'import --from=nonsense' )['status'] )->not->toBe( 0 );
    } );
} );

describe( 'validate-products', function () {
    it( 'lists each product with the problems in its markup', function () {
        update_option( 'thatseoagent_tests_machine_catalog', '1' );
        register_post_type( 'machine', array( 'public' => true, 'label' => 'Machines' ) );
        update_option( ThatSeoAgent_Product::OPTION_KEY, array( 'machine' => array( 'enabled' => true ) ) );
        $untitled = post( array( 'post_type' => 'machine', 'post_title' => '' ) );
        unregister_post_type( 'machine' );

        $run  = wpCli( 'validate-products --format=json' );
        $rows = json_decode( $run['out'], true );

        expect( $run['status'] )->toBe( 0, $run['err'] )
            ->and( $rows )->toBeArray()
            ->and( $rows )->toBe( array( array( 'ID' => $untitled->ID, 'title' => '', 'status' => 'error', 'issues' => 'product_name_missing' ) ) );
    } );

    it( 'answers an empty JSON list when every product is complete', function () {
        update_option( 'thatseoagent_tests_machine_catalog', '1' );
        register_post_type( 'machine', array( 'public' => true, 'label' => 'Machines' ) );
        update_option( ThatSeoAgent_Product::OPTION_KEY, array( 'machine' => array( 'enabled' => true ) ) );
        unregister_post_type( 'machine' );

        $run = wpCli( 'validate-products --format=json' );

        expect( json_decode( $run['out'], true ) )->toBe( array() );
    } );
} );
