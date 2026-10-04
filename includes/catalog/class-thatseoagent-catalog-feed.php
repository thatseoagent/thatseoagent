<?php
/**
 * The catalog as JSON Lines: every catalog entry's Product markup, one per
 * line, at /catalog.jsonl.
 *
 * The same Product node each product page carries in its JSON-LD, so an
 * agent can read the whole catalog in a few requests instead of one page
 * per product. Like llms.txt it is a convention for agents, not something
 * search engines read.
 *
 *     GET /catalog.jsonl            the first 100 products
 *     GET /catalog.jsonl?page=2     the next 100
 *
 * Each page answers with `Link: <…?page=N>; rel="next"` while there are
 * more. Each line is a complete document: its `@context` included, and no
 * reference to the page's other nodes (the WebPage), which are not in the
 * file. Entries kept out of search or behind a password are left out.
 *
 * Only while a catalog is declared, and cached per page until a catalog
 * entry or the declarations change.
 *
 * @package ThatSeoAgent
 * @since 2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Catalog_Feed {

    /**
     * Products per page. Each one renders its description, so fewer than a
     * sitemap's thousand.
     */
    const PER_PAGE = 100;

    /**
     * Option holding the cache version; bumping it drops every page.
     */
    const VERSION_OPTION = 'thatseoagent_catalog_feed_version';

    /**
     * Register the hooks.
     *
     * @since 2.5.0
     */
    public static function register() {
        // Priority 20: before ThatSeoAgent::maybe_flush_rewrite_rules() at 21.
        add_action( 'init', array( __CLASS__, 'register_routes' ), 20 );
        add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
        add_action( 'parse_request', array( __CLASS__, 'handle_request' ) );

        add_action( 'save_post', array( __CLASS__, 'purge_for_post' ) );
        add_action( 'deleted_post', array( __CLASS__, 'purge' ) );
        add_action( 'set_object_terms', array( __CLASS__, 'purge_for_post' ) );
    }

    /**
     * Add the rewrite rule.
     *
     * @since 2.5.0
     */
    public static function register_routes() {
        add_rewrite_rule( '^catalog\.jsonl$', 'index.php?thatseoagent_catalog=1', 'top' );
    }

    /**
     * Add the query var.
     *
     * @since 2.5.0
     * @param array $vars Query vars.
     * @return array
     */
    public static function query_vars( $vars ) {
        $vars[] = 'thatseoagent_catalog';
        return $vars;
    }

    /**
     * Whether the file is published: a catalog is set up.
     *
     * @since 2.5.0
     * @return bool
     */
    public static function is_published() {
        return (bool) ThatSeoAgent_Product::post_types() && (bool) get_option( 'permalink_structure' );
    }

    /**
     * The URL of a page of the file.
     *
     * @since 2.5.0
     * @param int $page Page number.
     * @return string
     */
    public static function url( $page = 1 ) {
        $url = home_url( '/catalog.jsonl' );

        return $page > 1 ? add_query_arg( 'page', (int) $page, $url ) : $url;
    }

    /**
     * Answer a request for /catalog.jsonl.
     *
     * @since 2.5.0
     * @param WP $wp Current WordPress environment.
     */
    public static function handle_request( $wp ) {
        if ( empty( $wp->query_vars['thatseoagent_catalog'] ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A public, read-only page number.
        $page  = isset( $_GET['page'] ) ? max( 1, absint( $_GET['page'] ) ) : 1;
        $total = self::count();

        if ( ! self::is_published() || ( $page > 1 && ( $page - 1 ) * self::PER_PAGE >= $total ) ) {
            status_header( 404 );
            nocache_headers();
            header( 'Content-Type: text/plain; charset=utf-8' );
            echo esc_html__( 'Not found.', 'thatseoagent' );
            exit;
        }

        status_header( 200 );
        header( 'Content-Type: application/x-ndjson; charset=utf-8' );
        header( 'X-Robots-Tag: noindex' );

        if ( $page * self::PER_PAGE < $total ) {
            header( 'Link: <' . esc_url_raw( self::url( $page + 1 ) ) . '>; rel="next"', false );
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded line by line in lines().
        echo self::page( $page );
        exit;
    }

    /**
     * One page of the file, from the cache or built and cached.
     *
     * @since 2.5.0
     * @param int $page Page number.
     * @return string
     */
    public static function page( $page ) {
        $key  = 'thatseoagent_catalog_' . (int) get_option( self::VERSION_OPTION, 1 ) . '_' . (int) $page;
        $body = get_transient( $key );

        if ( ! is_string( $body ) ) {
            $body = self::lines( $page );
            set_transient( $key, $body, DAY_IN_SECONDS );
        }

        return $body;
    }

    /**
     * Build one page: a JSON document per line.
     *
     * @since 2.5.0
     * @param int $page Page number.
     * @return string
     */
    private static function lines( $page ) {
        $lines = array();

        foreach ( self::posts( $page ) as $post ) {
            $node = ThatSeoAgent_Product::schema( $post );
            ThatSeoAgent_Memo::forget_post( $post );

            if ( ! $node ) {
                continue;
            }

            // The WebPage it points at is not in this file.
            unset( $node['mainEntityOfPage'] );

            $lines[] = wp_json_encode( array( '@context' => 'https://schema.org' ) + $node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        }

        return $lines ? implode( "\n", $lines ) . "\n" : '';
    }

    /**
     * The catalog entries of one page, oldest first so pages stay put as
     * products are added.
     *
     * @since 2.5.0
     * @param int $page Page number.
     * @return array<int, WP_Post>
     */
    private static function posts( $page ) {
        return get_posts( self::query_args( array(
            'posts_per_page' => self::PER_PAGE,
            'offset'         => ( max( 1, (int) $page ) - 1 ) * self::PER_PAGE,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ) ) );
    }

    /**
     * How many catalog entries the file lists.
     *
     * @since 2.5.0
     * @return int
     */
    public static function count() {
        if ( ! ThatSeoAgent_Product::post_types() ) {
            return 0;
        }

        $query = new WP_Query( self::query_args( array(
            'posts_per_page'         => 1,
            'fields'                 => 'ids',
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ) ) );

        return (int) $query->found_posts;
    }

    /**
     * A query limited to the catalog entries that may be listed.
     *
     * @since 2.5.0
     * @param array $args Query arguments.
     * @return array
     */
    private static function query_args( array $args ) {
        return array_merge(
            array(
                'post_type'   => ThatSeoAgent_Product::post_types(),
                'post_status' => 'publish',
            ),
            $args,
            ThatSeoAgent_Indexing::listed_query_args()
        );
    }

    /**
     * Drop every cached page when a catalog entry is saved.
     *
     * @since 2.5.0
     * @param int $post_id Post ID.
     */
    public static function purge_for_post( $post_id ) {
        if ( in_array( get_post_type( $post_id ), ThatSeoAgent_Product::post_types(), true ) ) {
            self::purge();
        }
    }

    /**
     * Drop every cached page. The old transients expire on their own.
     *
     * @since 2.5.0
     */
    public static function purge() {
        update_option( self::VERSION_OPTION, (int) get_option( self::VERSION_OPTION, 1 ) + 1, false );
    }
}
