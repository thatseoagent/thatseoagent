<?php

namespace ThatSeoAgent\Tests;

/**
 * An answer of the site, over HTTP.
 */
final class Response {

    /**
     * @param array<string, list<string>> $headers Lowercased name => values.
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }

    /**
     * A header's value, its values joined when it came more than once.
     */
    public function header( string $name ): ?string {
        $values = $this->headers[ strtolower( $name ) ] ?? null;

        return null === $values ? null : implode( ', ', $values );
    }

    /**
     * Every value of a header.
     *
     * @return list<string>
     */
    public function headers( string $name ): array {
        return $this->headers[ strtolower( $name ) ] ?? array();
    }

    /**
     * Requests a URL of the site from its web server, as a client would,
     * without following redirects.
     *
     * @param array<string, string> $headers
     * @param string|null           $body    Request body, for a POST.
     */
    public static function fetch( string $url, array $headers = array(), string $method = 'GET', ?string $body = null ): self {
        // The site's address, localhost:8894, is the host's; inside wp-env
        // its web server is wordpress, asked for that host.
        $home   = wp_parse_url( home_url() );
        $server = getenv( 'THATSEOAGENT_HTTP_SERVER' ) ?: 'http://wordpress';
        $parts  = wp_parse_url( $url );
        $target = $server . ( $parts['path'] ?? '/' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );

        $headers['Host'] = $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );

        $received = array();
        $curl     = curl_init( $target );
        curl_setopt_array(
            $curl,
            array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_NOBODY         => 'HEAD' === $method,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => array_map(
                    static fn ( string $name, string $value ): string => "$name: $value",
                    array_keys( $headers ),
                    $headers
                ),
                CURLOPT_HEADERFUNCTION => static function ( $curl, string $line ) use ( &$received ): int {
                    $parts = explode( ':', $line, 2 );
                    if ( 2 === count( $parts ) ) {
                        $received[ strtolower( trim( $parts[0] ) ) ][] = trim( $parts[1] );
                    }

                    return strlen( $line );
                },
            )
        );
        $body   = (string) curl_exec( $curl );
        $status = (int) curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );
        curl_close( $curl );

        return new self( $status, $received, $body );
    }
}
