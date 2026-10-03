<?php

namespace ThatSeoAgent\Tests;

use WP_Error;

/**
 * Keeps a test's HTTP requests inside the test.
 *
 * Every request WordPress would send passes through `pre_http_request`. The
 * ones a test answered come back with its response; the rest are refused,
 * and recorded so the test fails.
 */
final class HttpFake {

    /** @var array<string, array{code?: int, body?: string, headers?: array<string, string>}|WP_Error> */
    private static array $responses = array();

    /** @var list<array{url: string, method: string, body: mixed}> */
    private static array $requests = array();

    /** @var list<string> */
    private static array $unfaked = array();

    public static function start(): void {
        self::$responses = array();
        self::$requests  = array();
        self::$unfaked   = array();
        add_filter( 'pre_http_request', array( self::class, 'intercept' ), PHP_INT_MAX, 3 );
    }

    /**
     * @return list<string> The URLs requested without a fake.
     */
    public static function stop(): array {
        remove_filter( 'pre_http_request', array( self::class, 'intercept' ), PHP_INT_MAX );

        return self::$unfaked;
    }

    /**
     * @param array<string, array{code?: int, body?: string, headers?: array<string, string>}|WP_Error> $responses
     */
    public static function answer( array $responses ): void {
        self::$responses = $responses + self::$responses;
    }

    /**
     * @return list<array{url: string, method: string, body: mixed}>
     */
    public static function requests(): array {
        return self::$requests;
    }

    /**
     * @param false|array<mixed>|WP_Error $preempt
     * @param array<string, mixed>        $args
     * @return array<mixed>|WP_Error
     */
    public static function intercept( $preempt, array $args, string $url ) {
        // Another filter already answered: the request never leaves.
        if ( false !== $preempt ) {
            return $preempt;
        }

        self::$requests[] = array(
            'url'    => $url,
            'method' => (string) ( $args['method'] ?? 'GET' ),
            'body'   => $args['body'] ?? null,
        );

        foreach ( self::$responses as $pattern => $response ) {
            if ( fnmatch( $pattern, $url ) ) {
                return $response instanceof WP_Error ? $response : self::response( $response );
            }
        }

        self::$unfaked[] = $url;

        return new WP_Error( 'http_request_blocked', sprintf( 'No HTTP in tests: %s', $url ) );
    }

    /**
     * @param array{code?: int, body?: string, headers?: array<string, string>} $response
     * @return array<string, mixed> As wp_remote_request() returns it.
     */
    private static function response( array $response ): array {
        $code = $response['code'] ?? 200;

        return array(
            'headers'  => new \WpOrg\Requests\Utility\CaseInsensitiveDictionary( $response['headers'] ?? array() ),
            'body'     => $response['body'] ?? '',
            'response' => array(
                'code'    => $code,
                'message' => get_status_header_desc( $code ),
            ),
            'cookies'  => array(),
            'filename' => null,
        );
    }
}
