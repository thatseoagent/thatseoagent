<?php
/**
 * Serve posts as Markdown at their URL plus `.md`, and at their own URL to
 * whoever asks for Markdown.
 *
 *     https://example.com/my-post     → HTML, for people
 *     https://example.com/my-post.md  → Markdown, for AI agents
 *     https://example.com/my-post     → Markdown too, with Accept: text/markdown
 *
 * The third is HTTP content negotiation (RFC 9110 §12.5.1): the same URL, the
 * representation the client prefers. Agents that know only the page's URL
 * get the Markdown without having to know the `.md` convention. Browsers
 * never list text/markdown in Accept, so people keep getting the HTML.
 *
 * The path is resolved with url_to_postid(), i.e. through the site's own
 * rewrite rules, so it works with any permalink structure — dates,
 * categories, hierarchical pages, custom post types.
 *
 * The request is answered on `parse_request`, before the main query and
 * redirect_canonical() run: neither has anything to add, and the main query
 * would otherwise load the front page's posts for nothing.
 *
 * @package ThatSeoAgent
 * @since 1.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Markdown_Endpoint {

    /**
     * Rewrite rule for `.md` URLs.
     *
     * WP::parse_request() trims slashes, so `/my-post.md/` matches too.
     */
    const RULE = '(.+)\.md$';

    /**
     * Register the hooks.
     *
     * @since 1.14.0
     */
    public static function register() {
        // Priority 20: before ThatSeoAgent::maybe_flush_rewrite_rules() at 21.
        add_action( 'init', array( __CLASS__, 'register_routes' ), 20 );
        add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
        add_action( 'parse_request', array( __CLASS__, 'handle_request' ) );

        // After redirect_canonical() at 10: a non-canonical URL is sent to
        // the canonical one first, whatever representation was asked for.
        add_action( 'template_redirect', array( __CLASS__, 'negotiate' ), 11 );

        // Printed even while another SEO plugin is active: no other plugin
        // serves these URLs.
        add_action( 'wp_head', array( __CLASS__, 'output_alternate_link' ), 1 );
    }

    /**
     * Add the `.md` rewrite rule.
     *
     * @since 1.14.0
     */
    public static function register_routes() {
        add_rewrite_rule( self::RULE, 'index.php?thatseoagent_markdown=$matches[1]', 'top' );
    }

    /**
     * Add the query var.
     *
     * @since 1.14.0
     * @param array $vars Query vars.
     * @return array
     */
    public static function query_vars( $vars ) {
        $vars[] = 'thatseoagent_markdown';
        return $vars;
    }

    /**
     * Post types served as Markdown.
     *
     * @since 1.14.0
     * @return array<int, string>
     */
    public static function post_types() {
        /**
         * Filter the post types served as Markdown.
         *
         * @since 1.14.0
         * @param array<int, string> $post_types Default post and page.
         */
        return (array) apply_filters( 'thatseoagent_markdown_post_types', array( 'post', 'page' ) );
    }

    /**
     * The `.md` URL of a post, or an empty string when it has none.
     *
     * Mirrors what handle_request() will accept: a served post type, a
     * public post, and pretty permalinks — the rewrite rule does not exist
     * without them.
     *
     * @since 1.16.0
     * @param WP_Post|int $post Post object or ID.
     * @return string
     */
    public static function url_for( $post ) {
        $post = get_post( $post );

        if ( ! $post || ! get_option( 'permalink_structure' ) ) {
            return '';
        }

        if ( 'publish' !== $post->post_status || post_password_required( $post ) ) {
            return '';
        }

        if ( ! in_array( $post->post_type, self::post_types(), true ) ) {
            return '';
        }

        // A static front page's permalink is the home URL, which has no path
        // to append `.md` to; its own path resolves to the same page.
        if ( 'page' === $post->post_type && (int) get_option( 'page_on_front' ) === $post->ID ) {
            $permalink = home_url( user_trailingslashit( get_page_uri( $post ) ) );
        } else {
            $permalink = get_permalink( $post );
        }

        if ( ! $permalink || false !== strpos( $permalink, '?' ) ) {
            return '';
        }

        return untrailingslashit( $permalink ) . '.md';
    }

    /**
     * Point agents at the Markdown version from the HTML page.
     *
     * The `.md` URLs are otherwise undiscoverable: nothing links to them.
     * `rel="alternate"` with a media type is how HTML declares "the same
     * document in another format", as it does for RSS feeds.
     *
     * @since 1.16.0
     */
    public static function output_alternate_link() {
        if ( ! is_singular() ) {
            return;
        }

        /**
         * Filter whether to print the Markdown alternate link.
         *
         * @since 1.16.0
         * @param bool    $enabled Default true.
         * @param WP_Post $post    Current post.
         */
        if ( ! apply_filters( 'thatseoagent_markdown_alternate_link', true, get_post() ) ) {
            return;
        }

        $url = self::url_for( get_post() );
        if ( '' === $url ) {
            return;
        }

        echo '<link rel="alternate" type="text/markdown" href="' . esc_url( $url ) . '">' . "\n";
    }

    /**
     * Answer a `.md` request.
     *
     * @since 1.14.0
     * @param WP $wp Current WordPress environment.
     */
    public static function handle_request( $wp ) {
        if ( empty( $wp->query_vars['thatseoagent_markdown'] ) ) {
            return;
        }

        $post = self::find_post( (string) $wp->query_vars['thatseoagent_markdown'] );

        if ( ! $post ) {
            self::send_error( 404, __( 'Post not found.', 'thatseoagent' ) );
        }

        if ( ! self::can_access( $post ) ) {
            self::send_error( 403, __( 'Access denied.', 'thatseoagent' ) );
        }

        self::send_not_modified_if_fresh( $post );
        self::send( self::markdown( $post ), $post, false, ThatSeoAgent_Indexing::is_post_noindex( $post ) );
    }

    /**
     * Answer a post's own URL with its Markdown when the client prefers it,
     * and announce the Markdown version when it does not.
     *
     * Every response of a post that has a Markdown version says
     * `Vary: Accept`, the HTML one included: without it, a cache between
     * the site and its readers may store one representation and hand it to
     * clients that asked for the other. The HTML one also carries a `Link`
     * header pointing at the `.md` URL.
     *
     * @since 2.3.0
     */
    public static function negotiate() {
        if ( ! is_singular() ) {
            return;
        }

        $post = get_queried_object();
        if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, self::post_types(), true ) || post_password_required( $post ) ) {
            return;
        }

        /**
         * Filter whether a post's URL answers Accept: text/markdown with its
         * Markdown version.
         *
         * The `.md` URL keeps working either way.
         *
         * @since 2.3.0
         * @param bool    $enabled Default true.
         * @param WP_Post $post    Requested post.
         */
        if ( apply_filters( 'thatseoagent_markdown_negotiation', true, $post ) ) {
            header( 'Vary: Accept', false );

            $accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) : '';
            if ( self::prefers_markdown( $accept ) ) {
                // Page caches key on the URL alone: a cached Markdown response
                // would be served to the next browser. Most honor this constant.
                if ( ! defined( 'DONOTCACHEPAGE' ) ) {
                    define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- The constant page cache plugins read; a prefixed one would mean nothing to them.
                }

                self::send_not_modified_if_fresh( $post );
                // The same answer the HTML at this URL gives in its robots
                // meta, filters included.
                self::send( self::markdown( $post ), $post, true, ThatSeoAgent_Indexing::is_noindex() );
            }
        }

        self::send_alternate_header( $post );
    }

    /**
     * Announce the Markdown version in the HTML response's headers.
     *
     * The HTTP form of the `<link rel="alternate">` in the page's head
     * (RFC 8288): a client that reads only the headers — a HEAD request, an
     * agent that stops at the first bytes — learns there is a Markdown
     * version without parsing HTML. Same filter as the tag, so turning one
     * off turns off both.
     *
     * @since 2.3.0
     * @param WP_Post $post Requested post.
     */
    private static function send_alternate_header( WP_Post $post ) {
        /** This filter is documented in output_alternate_link(). */
        if ( ! apply_filters( 'thatseoagent_markdown_alternate_link', true, $post ) ) {
            return;
        }

        $url = self::url_for( $post );
        if ( '' !== $url ) {
            header( 'Link: <' . esc_url_raw( $url ) . '>; rel="alternate"; type="text/markdown"', false );
        }
    }

    /**
     * Whether an Accept header prefers Markdown to HTML.
     *
     * Markdown wins when text/markdown (or text/x-markdown) is acceptable
     * and weighs at least as much as text/html. Wildcards never select it:
     * a generic client accepting any type means "anything", and the HTML
     * page is what that client has always received.
     *
     * @since 2.3.0
     * @param string $accept Accept header.
     * @return bool
     */
    public static function prefers_markdown( $accept ) {
        $markdown = 0.0;
        $html     = 0.0;

        foreach ( explode( ',', strtolower( (string) $accept ) ) as $range ) {
            $parts = array_map( 'trim', explode( ';', $range ) );
            $type  = array_shift( $parts );
            $q     = 1.0;

            foreach ( $parts as $param ) {
                if ( 0 === strpos( $param, 'q=' ) ) {
                    $q = max( 0.0, min( 1.0, (float) substr( $param, 2 ) ) );
                }
            }

            if ( in_array( $type, array( 'text/markdown', 'text/x-markdown' ), true ) ) {
                $markdown = max( $markdown, $q );
            } elseif ( in_array( $type, array( 'text/html', 'application/xhtml+xml' ), true ) ) {
                $html = max( $html, $q );
            }
        }

        return $markdown > 0 && $markdown >= $html;
    }

    /**
     * A post's Markdown, frontmatter included, from the cache or built and
     * cached.
     *
     * @since 2.3.0 Split from handle_request().
     * @param WP_Post $post Post object.
     * @return string
     */
    public static function markdown( WP_Post $post ) {
        $markdown = ThatSeoAgent_Markdown_Cache::get( $post );

        if ( null === $markdown ) {
            $markdown = ThatSeoAgent_Markdown::convert( $post );
            ThatSeoAgent_Markdown_Cache::set( $post, $markdown );
        }

        return $markdown;
    }

    /**
     * The post a `.md` path points to.
     *
     * @since 1.14.0
     * @param string $path Requested path without the extension.
     * @return WP_Post|null
     */
    private static function find_post( $path ) {
        $post_id = url_to_postid( home_url( user_trailingslashit( trim( $path, '/' ) ) ) );
        $post    = $post_id ? get_post( $post_id ) : null;

        if ( ! $post || ! in_array( $post->post_type, self::post_types(), true ) ) {
            return null;
        }

        return $post;
    }

    /**
     * Whether the current visitor may read the post.
     *
     * @since 1.14.0
     * @param WP_Post $post Post object.
     * @return bool
     */
    private static function can_access( WP_Post $post ) {
        if ( 'publish' !== $post->post_status && ! current_user_can( 'read_post', $post->ID ) ) {
            return false;
        }

        return ! post_password_required( $post );
    }

    /**
     * Answer 304 when the client's copy is current.
     *
     * @since 1.14.0
     * @param WP_Post $post Post object.
     */
    private static function send_not_modified_if_fresh( WP_Post $post ) {
        if ( empty( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) {
            return;
        }

        $since    = strtotime( sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) );
        $modified = (int) get_post_modified_time( 'U', true, $post );

        if ( $since && $since >= $modified ) {
            status_header( 304 );
            exit;
        }
    }

    /**
     * Send the Markdown and end the request.
     *
     * A post kept out of search says so here too: noindex at its own URL
     * as well, and no canonical anywhere, as its HTML prints none.
     *
     * @since 1.14.0
     * @since 2.3.0 $negotiated.
     * @since 2.7.0 $kept_out.
     * @param string  $markdown   Markdown.
     * @param WP_Post $post       Post object.
     * @param bool    $negotiated Whether it answers the post's own URL.
     * @param bool    $kept_out   Whether the post is kept out of search.
     */
    private static function send( $markdown, WP_Post $post, $negotiated = false, $kept_out = false ) {
        status_header( 200 );
        header( 'Content-Type: text/markdown; charset=utf-8' );

        if ( $negotiated ) {
            // The post's own URL, which search engines index as HTML: no
            // noindex here, unless the HTML says so too. Content-Location
            // names the URL of this representation (RFC 9110 §8.7), and no
            // shared cache keeps it.
            header( 'Cache-Control: private, no-cache' );
            $location = self::url_for( $post );
            if ( '' !== $location ) {
                header( 'Content-Location: ' . esc_url_raw( $location ) );
            }
        }

        // The HTML page is the one to index; this is a copy of it.
        if ( ! $negotiated || $kept_out ) {
            header( 'X-Robots-Tag: noindex' );
        }

        if ( ! $kept_out ) {
            header( 'Link: <' . esc_url_raw( get_permalink( $post ) ) . '>; rel="canonical"' );
        }

        header( 'Last-Modified: ' . get_post_modified_time( 'D, d M Y H:i:s', true, $post ) . ' GMT' );
        header( 'Content-Disposition: inline; filename="' . sanitize_file_name( $post->post_name . '.md' ) . '"' );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text Markdown built from the post's own rendered content.
        echo $markdown;
        exit;
    }

    /**
     * Send a plain-text error and end the request.
     *
     * @since 1.14.0
     * @param int    $status  HTTP status.
     * @param string $message Message.
     */
    private static function send_error( $status, $message ) {
        status_header( $status );
        nocache_headers();
        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'X-Robots-Tag: noindex' );

        echo esc_html( $message );
        exit;
    }
}
