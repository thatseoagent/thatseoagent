<?php
/**
 * Asking the site itself for its own pages.
 *
 * Three checks do it: the content check reads the homepage and asks for the
 * addresses it did not recognize, the access check requests the homepage as
 * each AI crawler and reads robots.txt, and the Markdown check asks one post
 * as an agent and as a browser. Before 2.7.0 each built its own requests,
 * repeated core's rule for loopback SSL, and two of them went around the
 * WordPress HTTP API, so nothing could see or answer them.
 *
 * This is the only module that goes to the network for them. Every request,
 * one or many, passes through `http_request_args` and `pre_http_request`
 * like any made with wp_remote_get(): a filter that answers one is the
 * response, and only the rest are sent — the many at once.
 *
 * Each answer is reduced to what the checks read:
 *
 *     status   HTTP status, 0 when there was no answer
 *     headers  lower-case name => value, repeated ones joined with ", "
 *     body     the body, '' for a HEAD
 *     error    why there was no answer, or ''
 *
 * @package ThatSeoAgent
 * @since 2.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Loopback {

    /**
     * Ask for one address.
     *
     * @since 2.7.0
     * @param string $url  URL.
     * @param array  $args method ('GET' or 'HEAD'), headers, user-agent,
     *                     timeout (seconds, default 10) and redirection
     *                     (default 5).
     * @return array{status: int, headers: array<string, string>, body: string, error: string}
     */
    public static function get( $url, array $args = array() ) {
        return self::reduce( wp_remote_request( $url, self::args( $args ) ) );
    }

    /**
     * Ask for several addresses at once.
     *
     * @since 2.7.0
     * @param array<string, array> $requests Key => url, and the arguments
     *                                       get() takes.
     * @return array<string, array{status: int, headers: array<string, string>, body: string, error: string}> Same keys.
     */
    public static function many( array $requests ) {
        $results = array();
        $pending = array();
        $options = array();

        foreach ( $requests as $key => $request ) {
            $url  = $request['url'];
            $args = self::args( $request );

            /** This filter is documented in wp-includes/class-wp-http.php */
            $args = apply_filters( 'http_request_args', $args, $url );

            /** This filter is documented in wp-includes/class-wp-http.php */
            $pre = apply_filters( 'pre_http_request', false, $args, $url );
            if ( false !== $pre ) {
                $results[ $key ] = self::reduce( $pre );
                continue;
            }

            $pending[ $key ] = array(
                'url'     => $url,
                'type'    => $args['method'],
                'headers' => array( 'User-Agent' => $args['user-agent'] ) + $args['headers'],
            );

            // One set of options for the whole batch: the checks ask all
            // their addresses the same way.
            $options = array(
                'timeout'          => $args['timeout'],
                'follow_redirects' => $args['redirection'] > 0,
                'redirects'        => max( 1, $args['redirection'] ),
                'verify'           => $args['sslverify'],
            );
        }

        $responses = $pending ? \WpOrg\Requests\Requests::request_multiple( $pending, $options ) : array();

        foreach ( array_keys( $pending ) as $key ) {
            $response = isset( $responses[ $key ] ) ? $responses[ $key ] : null;

            if ( $response instanceof \WpOrg\Requests\Response ) {
                $headers = array();
                foreach ( $response->headers as $name => $value ) {
                    $headers[ strtolower( $name ) ] = (string) $value;
                }

                $results[ $key ] = array(
                    'status'  => (int) $response->status_code,
                    'headers' => $headers,
                    'body'    => (string) $response->body,
                    'error'   => '',
                );
            } else {
                $results[ $key ] = self::nothing( $response instanceof Exception ? $response->getMessage() : __( 'No answer', 'thatseoagent' ) );
            }
        }

        // In the order they were asked.
        return array_replace( array_intersect_key( $requests, $results ), $results );
    }

    /**
     * The WordPress HTTP arguments of a request.
     *
     * @since 2.7.0
     * @param array $request As get() takes it.
     * @return array
     */
    private static function args( array $request ) {
        return array(
            'method'      => isset( $request['method'] ) ? strtoupper( $request['method'] ) : 'GET',
            'timeout'     => isset( $request['timeout'] ) ? (int) $request['timeout'] : 10,
            'redirection' => isset( $request['redirection'] ) ? (int) $request['redirection'] : 5,
            'headers'     => isset( $request['headers'] ) ? (array) $request['headers'] : array(),
            'user-agent'  => isset( $request['user-agent'] ) ? (string) $request['user-agent'] : 'ThatSeoAgent/' . THATSEOAGENT_VERSION . ' (+' . home_url( '/' ) . ')',
            // Same rule core applies to its own loopback requests.
            'sslverify'   => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.
        );
    }

    /**
     * A WordPress HTTP response, reduced.
     *
     * @since 2.7.0
     * @param array|WP_Error $response As wp_remote_request() returns it.
     * @return array{status: int, headers: array<string, string>, body: string, error: string}
     */
    private static function reduce( $response ) {
        if ( is_wp_error( $response ) ) {
            return self::nothing( $response->get_error_message() );
        }

        $headers = array();
        foreach ( wp_remote_retrieve_headers( $response ) as $name => $value ) {
            $headers[ strtolower( $name ) ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
        }

        return array(
            'status'  => (int) wp_remote_retrieve_response_code( $response ),
            'headers' => $headers,
            'body'    => (string) wp_remote_retrieve_body( $response ),
            'error'   => '',
        );
    }

    /**
     * No answer.
     *
     * @since 2.7.0
     * @param string $error Why.
     * @return array{status: int, headers: array, body: string, error: string}
     */
    private static function nothing( $error ) {
        return array(
            'status'  => 0,
            'headers' => array(),
            'body'    => '',
            'error'   => (string) $error,
        );
    }
}
