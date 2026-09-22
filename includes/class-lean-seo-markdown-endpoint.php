<?php
/**
 * Serve posts as Markdown at their URL plus `.md`.
 *
 *     https://example.com/my-post     → HTML, for people
 *     https://example.com/my-post.md  → Markdown, for AI agents
 *
 * The path is resolved with url_to_postid(), i.e. through the site's own
 * rewrite rules, so it works with any permalink structure — dates,
 * categories, hierarchical pages, custom post types.
 *
 * The request is answered on `parse_request`, before the main query and
 * redirect_canonical() run: neither has anything to add, and the main query
 * would otherwise load the front page's posts for nothing.
 *
 * @package Lean_SEO
 * @since 1.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lean_SEO_Markdown_Endpoint {

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
        // Priority 20: before Lean_SEO::maybe_flush_rewrite_rules() at 21.
        add_action( 'init', array( __CLASS__, 'register_routes' ), 20 );
        add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
        add_action( 'parse_request', array( __CLASS__, 'handle_request' ) );
    }

    /**
     * Add the `.md` rewrite rule.
     *
     * @since 1.14.0
     */
    public static function register_routes() {
        add_rewrite_rule( self::RULE, 'index.php?lean_markdown=$matches[1]', 'top' );
    }

    /**
     * Add the query var.
     *
     * @since 1.14.0
     * @param array $vars Query vars.
     * @return array
     */
    public static function query_vars( $vars ) {
        $vars[] = 'lean_markdown';
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
        return (array) apply_filters( 'lean_seo_markdown_post_types', array( 'post', 'page' ) );
    }

    /**
     * Answer a `.md` request.
     *
     * @since 1.14.0
     * @param WP $wp Current WordPress environment.
     */
    public static function handle_request( $wp ) {
        if ( empty( $wp->query_vars['lean_markdown'] ) ) {
            return;
        }

        $post = self::find_post( (string) $wp->query_vars['lean_markdown'] );

        if ( ! $post ) {
            self::send_error( 404, __( 'Post not found.', 'lean-seo' ) );
        }

        if ( ! self::can_access( $post ) ) {
            self::send_error( 403, __( 'Access denied.', 'lean-seo' ) );
        }

        self::send_not_modified_if_fresh( $post );

        $markdown = Lean_SEO_Markdown_Cache::get( $post );

        if ( null === $markdown ) {
            $markdown = Lean_SEO_Markdown::convert( $post );
            Lean_SEO_Markdown_Cache::set( $post, $markdown );
        }

        self::send( $markdown, $post );
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
     * @since 1.14.0
     * @param string  $markdown Markdown.
     * @param WP_Post $post     Post object.
     */
    private static function send( $markdown, WP_Post $post ) {
        status_header( 200 );
        header( 'Content-Type: text/markdown; charset=utf-8' );

        // The HTML page is the one to index; this is a copy of it.
        header( 'X-Robots-Tag: noindex' );
        header( 'Link: <' . esc_url_raw( get_permalink( $post ) ) . '>; rel="canonical"' );

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
