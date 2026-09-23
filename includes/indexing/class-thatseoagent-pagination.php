<?php
/**
 * Paginated listings and posts, as search engines should see them.
 *
 * Each page of a paginated listing is its own URL with its own posts, so it
 * is its own canonical: before 2.2.0 page 2 of a category declared page 1 as
 * its canonical, which tells search engines it is a duplicate and hides the
 * older posts it is the only link to. The same holds for a post split with
 * `<!--nextpage-->`.
 *
 * `rel="prev"` and `rel="next"` link the pages in order. Google stopped using
 * them in 2019; Bing and other crawlers still read them.
 *
 * @package ThatSeoAgent
 * @since 2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Pagination {

    /**
     * Register the hooks.
     *
     * wp_head priority 1, registered after ThatSeoAgent_Meta: the links follow
     * the canonical they relate to.
     *
     * @since 2.2.0
     */
    public static function register() {
        add_action( 'wp_head', array( __CLASS__, 'output_links' ), 1 );
    }

    /**
     * The current page number, 1 on an unpaginated view.
     *
     * A singular view counts `<!--nextpage-->` pages (query var `page`); a
     * listing counts pages of posts (query var `paged`).
     *
     * @since 2.2.0
     * @return int
     */
    public static function current() {
        return max( 1, (int) get_query_var( is_singular() ? 'page' : 'paged' ) );
    }

    /**
     * How many pages the current view has.
     *
     * @since 2.2.0
     * @return int
     */
    public static function total() {
        if ( is_singular() ) {
            $post = get_queried_object();

            // What setup_postdata() counts; a Page Break block saves the same
            // comment inside its own markup.
            return $post instanceof WP_Post ? substr_count( $post->post_content, '<!--nextpage-->' ) + 1 : 1;
        }

        global $wp_query;

        return $wp_query instanceof WP_Query ? max( 1, (int) $wp_query->max_num_pages ) : 1;
    }

    /**
     * The URL of page N of a listing.
     *
     * Built from the listing's canonical, not from the requested URL, so
     * query strings the visitor arrived with (`?utm_source=`) stay out.
     *
     * @since 2.2.0
     * @param string $base Canonical URL of the listing's first page.
     * @param int    $page Page number.
     * @return string
     */
    public static function listing_url( $base, $page ) {
        global $wp_rewrite;

        if ( $page <= 1 ) {
            return $base;
        }

        if ( $wp_rewrite->using_permalinks() && false === strpos( $base, '?' ) ) {
            return user_trailingslashit( trailingslashit( $base ) . $wp_rewrite->pagination_base . '/' . $page, 'paged' );
        }

        return add_query_arg( 'paged', $page, $base );
    }

    /**
     * The URL of page N of a post split with `<!--nextpage-->`.
     *
     * The rules of core's private _wp_link_page(), without the markup.
     *
     * @since 2.2.0
     * @param WP_Post $post Post.
     * @param int     $page Page number.
     * @return string
     */
    public static function post_page_url( WP_Post $post, $page ) {
        global $wp_rewrite;

        $permalink = get_permalink( $post );

        if ( $page <= 1 ) {
            return $permalink;
        }

        if ( ! $wp_rewrite->using_permalinks() || in_array( $post->post_status, array( 'draft', 'pending' ), true ) ) {
            return add_query_arg( 'page', $page, $permalink );
        }

        // A static front page paginates as /page/2/: /2/ would be read as a
        // date archive.
        if ( 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === (int) $post->ID ) {
            return trailingslashit( $permalink ) . user_trailingslashit( $wp_rewrite->pagination_base . '/' . $page, 'single_paged' );
        }

        return trailingslashit( $permalink ) . user_trailingslashit( (string) $page, 'single_paged' );
    }

    /**
     * The URL of another page of the current view, or '' when there is none.
     *
     * @since 2.2.0
     * @param int $page Page number.
     * @return string
     */
    public static function url_for( $page ) {
        if ( $page < 1 || $page > self::total() ) {
            return '';
        }

        if ( is_singular() ) {
            $post = get_queried_object();
            return $post instanceof WP_Post ? self::post_page_url( $post, $page ) : '';
        }

        $base = ThatSeoAgent_Meta::get_canonical_base();

        return $base ? self::listing_url( $base, $page ) : '';
    }

    /**
     * Print rel="prev" and rel="next".
     *
     * Not on a page kept out of search indexes: it prints no canonical
     * either, and its sequence is of no use to a search engine.
     *
     * @since 2.2.0
     */
    public static function output_links() {
        if ( self::total() < 2 || ThatSeoAgent_Indexing::is_noindex() ) {
            return;
        }

        /**
         * Filter whether rel="prev" and rel="next" are printed.
         *
         * @since 2.2.0
         * @param bool $enabled Default true.
         */
        if ( ! apply_filters( 'thatseoagent_adjacent_links', true ) ) {
            return;
        }

        $current = self::current();

        foreach ( array( 'prev' => $current - 1, 'next' => $current + 1 ) as $rel => $page ) {
            $url = self::url_for( $page );
            if ( '' !== $url ) {
                echo '<link rel="' . esc_attr( $rel ) . '" href="' . esc_url( $url ) . '">' . "\n";
            }
        }
    }
}
