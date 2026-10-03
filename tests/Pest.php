<?php

use ThatSeoAgent\Tests\HttpFake;

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
        $wpdb->query( 'ROLLBACK' );
        wp_cache_flush();
        ThatSeoAgent_Memo::reset();

        expect( $unfaked )->toBe( array(), 'Requests the test did not fake with fakeHttp().' );
    } )
    ->in( 'WordPress' );

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
