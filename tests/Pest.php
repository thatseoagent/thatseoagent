<?php

use ThatSeoAgent\Tests\Fixtures;
use ThatSeoAgent\Tests\HttpFake;
use ThatSeoAgent\Tests\Page;
use ThatSeoAgent\Tests\Response;

/*
 * The WordPress suite needs a real WordPress: it runs in wp-env.
 *
 * Each test runs inside a database transaction that is rolled back after
 * it, with the object cache and the plugin's per-request memo emptied, so
 * nothing a test writes reaches the next one. No request leaves the site:
 * the ones a test has not faked with fakeHttp() fail it.
 */
uses()
    ->beforeEach( function () {
        if ( ! function_exists( 'add_filter' ) ) {
            $this->markTestSkipped( 'Needs WordPress: run pnpm test:wordpress.' );
        }

        global $wpdb;
        $wpdb->query( 'START TRANSACTION' );
        wp_cache_flush();
        ThatSeoAgent_Memo::reset();
        HttpFake::start();
    } )
    ->afterEach( function () {
        if ( ! function_exists( 'add_filter' ) ) {
            return;
        }

        global $wpdb;
        $unfaked = HttpFake::stop();

        // Files are not in the transaction: image() leaves them to delete.
        foreach ( $GLOBALS['thatseoagent_test_attachments'] ?? array() as $attachment ) {
            wp_delete_attachment( $attachment, true );
        }
        $GLOBALS['thatseoagent_test_attachments'] = array();

        $wpdb->query( 'ROLLBACK' );
        wp_cache_flush();
        ThatSeoAgent_Memo::reset();

        expect( $unfaked )->toBe( array(), 'Requests the test did not fake with fakeHttp().' );
    } )
    ->in( 'WordPress' );

/*
 * The Http suite asks the test site's web server, as a crawler or an agent
 * would: status codes, headers and bodies of responses that end the
 * request. What its tests write is committed, for the server to see, and
 * undone after each one (Fixtures).
 */
uses()
    ->beforeEach( function () {
        if ( ! function_exists( 'add_filter' ) ) {
            $this->markTestSkipped( 'Needs WordPress: run pnpm test:wordpress.' );
        }

        Fixtures::start();
    } )
    ->afterEach( function () {
        if ( function_exists( 'add_filter' ) ) {
            Fixtures::clean();
        }
    } )
    ->in( 'Http' );

/**
 * The site's answer to a request.
 *
 * @param array<string, string> $headers
 */
function fetch( string $url, array $headers = array(), string $method = 'GET' ): Response {
    return Response::fetch( $url, $headers, $method );
}

/**
 * Answers the requests whose URL matches a pattern, for one test.
 *
 * @param array<string, array{code?: int, body?: string, headers?: array<string, string>}|WP_Error> $responses
 *        `fnmatch()` pattern => response.
 */
function fakeHttp( array $responses ): void {
    HttpFake::answer( $responses );
}

/**
 * The requests the test made, faked ones included.
 *
 * @return list<array{url: string, method: string, body: mixed}>
 */
function httpRequests(): array {
    return HttpFake::requests();
}

/**
 * The _doing_it_wrong() messages a callback triggers, kept out of the output.
 *
 * @return list<string>
 */
function doingItWrong( Closure $test ): array {
    $messages = array();
    $record   = static function ( string $function, string $message ) use ( &$messages ) {
        $messages[] = $message;
    };
    add_action( 'doing_it_wrong_run', $record, 10, 2 );
    add_filter( 'doing_it_wrong_trigger_error', '__return_false' );

    try {
        $test();
    } finally {
        remove_action( 'doing_it_wrong_run', $record, 10 );
        remove_filter( 'doing_it_wrong_trigger_error', '__return_false' );
    }

    return $messages;
}

/**
 * Serves a URL of the site the way WordPress serves a request, up to the
 * template: the query, the conditional tags and the globals a theme sees.
 */
function serve( string $url ): void {
    $_GET = array();
    $_POST = array();
    foreach ( array( 'query_string', 'id', 'postdata', 'authordata', 'day', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages', 'pagenow', 'current_screen', 'post' ) as $global ) {
        unset( $GLOBALS[ $global ] );
    }

    $parts = wp_parse_url( $url );
    $request = ( $parts['path'] ?? '/' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
    if ( isset( $parts['query'] ) ) {
        parse_str( $parts['query'], $_GET );
    }

    $_SERVER['REQUEST_URI'] = $request;
    unset( $_SERVER['PATH_INFO'] );
    wp_cache_flush();
    ThatSeoAgent_Memo::reset();

    $GLOBALS['wp_the_query'] = new WP_Query();
    $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];

    $public = $GLOBALS['wp']->public_query_vars;
    $private = $GLOBALS['wp']->private_query_vars;
    $GLOBALS['wp'] = new WP();
    $GLOBALS['wp']->public_query_vars = $public;
    $GLOBALS['wp']->private_query_vars = $private;

    // Globals named after query vars would leak into the new query.
    foreach ( array_merge( $public, $private ) as $var ) {
        unset( $GLOBALS[ $var ] );
    }

    $GLOBALS['wp']->main( $parts['query'] ?? '' );
}

/**
 * The <head> a URL of the site prints.
 */
function pageAt( string $url ): Page {
    serve( $url );

    ob_start();
    wp_head();

    // A block theme prints the <title> from its template, which is not
    // rendered here: WordPress's own answer stands in for it.
    return new Page( (string) ob_get_clean(), wp_get_document_title() );
}

/**
 * A published post, or whatever the arguments make it.
 *
 * @param array<string, mixed> $args  wp_insert_post() arguments.
 * @param array<string, string> $seo  Its SEO fields.
 */
function post( array $args = array(), array $seo = array() ): WP_Post {
    $id = wp_insert_post(
        $args + array(
            'post_title'   => 'A post',
            'post_content' => '<p>Some content for the post, long enough to say something about it.</p>',
            'post_status'  => 'publish',
            'post_type'    => 'post',
            'post_author'  => 1,
        ),
        true
    );
    expect( $id )->toBeInt();

    if ( array() !== $seo ) {
        ThatSeoAgent_Post_Seo::save( $id, $seo );
    }

    return get_post( $id );
}

/**
 * A published page.
 *
 * @param array<string, mixed> $args
 * @param array<string, string> $seo
 */
function page( array $args = array(), array $seo = array() ): WP_Post {
    return post( $args + array( 'post_type' => 'page', 'post_title' => 'A page' ), $seo );
}

/**
 * A term.
 *
 * @param array<string, mixed> $args wp_insert_term() arguments.
 */
function term( string $name = 'A term', string $taxonomy = 'category', array $args = array() ): WP_Term {
    $term = wp_insert_term( $name, $taxonomy, $args );
    expect( $term )->toBeArray();

    return get_term( $term['term_id'], $taxonomy );
}

/**
 * An image in the media library, with its file and sizes, deleted after the
 * test.
 */
function image( string $alt = '', int $width = 1200, int $height = 630 ): int {
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $file = wp_upload_dir()['path'] . '/thatseoagent-test-' . wp_generate_password( 8, false ) . '.png';
    $gd   = imagecreatetruecolor( $width, $height );
    imagepng( $gd, $file );

    $id = wp_insert_attachment(
        array(
            'post_mime_type' => 'image/png',
            'post_title'     => 'Test image',
            'post_status'    => 'inherit',
        ),
        $file
    );
    wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
    if ( '' !== $alt ) {
        update_post_meta( $id, '_wp_attachment_image_alt', $alt );
    }

    $GLOBALS['thatseoagent_test_attachments'][] = $id;

    return $id;
}

/**
 * The site's own content only: the post and page wp-env ships with would
 * otherwise sit in every list.
 */
function withoutSampleContent(): void {
    foreach ( get_posts( array( 'post_type' => array( 'post', 'page' ), 'post_status' => 'any', 'numberposts' => -1 ) ) as $post ) {
        wp_delete_post( $post->ID, true );
    }
}

/**
 * A user with a role.
 */
function user( string $role ): WP_User {
    $id = wp_insert_user( array(
        'user_login' => $role . '-' . wp_generate_password( 6, false ),
        'user_pass'  => wp_generate_password(),
        'role'       => $role,
    ) );
    expect( $id )->toBeInt();

    return get_user_by( 'id', $id );
}

/**
 * A user with a role, current from now on.
 */
function actingAs( string $role ): WP_User {
    $user = user( $role );
    wp_set_current_user( $user->ID );

    return $user;
}
