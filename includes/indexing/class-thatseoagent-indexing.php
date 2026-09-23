<?php
/**
 * What search engines may index.
 *
 * The single answer to "should this URL be in a search index?". Before
 * 2.2.0 there was no answer at all: the plugin printed no robots meta, so
 * search results pages and the 404 page were indexable, a post could not be
 * kept out of search, and the sitemap listed posts a search engine would be
 * told to drop anyway.
 *
 * The answer is read by everything that publishes a URL to a search engine
 * or an AI assistant, so they cannot disagree:
 *
 *     robots meta   filter_robots(), on core's wp_robots
 *     canonical     ThatSeoAgent_Meta, which prints none on a noindex URL
 *     sitemaps      listed_query_args()
 *     AI index      listed_query_args()
 *     IndexNow      is_post_noindex()
 *
 * The robots meta goes through core's `wp_robots` filter rather than a tag
 * of its own, so it merges with what WordPress and other plugins already
 * decided — a site that discourages search engines stays `noindex, nofollow`.
 *
 * @package ThatSeoAgent
 * @since 2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Indexing {

    /**
     * Register the hooks.
     *
     * Priority 20, after core's own wp_robots callbacks at 10, so the
     * directives they set are there to merge with.
     *
     * @since 2.2.0
     */
    public static function register() {
        add_filter( 'wp_robots', array( __CLASS__, 'filter_robots' ), 20 );
    }

    /**
     * Whether a post is kept out of search indexes.
     *
     * A private post is only ever seen by people logged in to edit it; the
     * rest follow their SEO fields.
     *
     * @since 2.2.0
     * @param WP_Post|int $post Post or ID.
     * @return bool
     */
    public static function is_post_noindex( $post ) {
        $post = get_post( $post );
        if ( ! $post ) {
            return false;
        }

        return 'private' === $post->post_status || '1' === ThatSeoAgent_Post_Seo::get( $post, 'noindex' );
    }

    /**
     * Whether the current request is kept out of search indexes.
     *
     * Search results and the 404 page are never worth indexing; neither is a
     * `?replytocom=` link, one duplicate of the post per comment.
     *
     * Pagination is not a reason: page 2 of a category lists posts page 1
     * does not, and is the only way a crawler reaches them.
     *
     * @since 2.2.0
     * @return bool
     */
    public static function is_noindex() {
        $noindex = is_search()
            || is_404()
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read to decide a meta tag, nothing is processed.
            || isset( $_GET['replytocom'] )
            || ( is_singular() && self::is_post_noindex( get_queried_object() ) );

        /**
         * Filter whether the current request is kept out of search indexes.
         *
         * Only the page itself: a post kept out here still appears in the
         * sitemaps and the AI index, which follow the post's SEO fields.
         *
         * @since 2.2.0
         * @param bool   $noindex Whether the page gets noindex.
         * @param string $context Current page context (see ThatSeoAgent_Meta::get_context()).
         */
        return (bool) apply_filters( 'thatseoagent_noindex', $noindex, ThatSeoAgent_Meta::get_context() );
    }

    /**
     * Add ThatSeoAgent's directives to the robots meta.
     *
     * An indexable page gets the largest previews search engines offer:
     * without `max-image-preview:large`, Google shows no large image in
     * Discover or results. A page with `noindex` — ours, or one WordPress or
     * another plugin set — drops the preview limits, which mean nothing on a
     * page that is not shown.
     *
     * @since 2.2.0
     * @param array<string, bool|string> $robots Directives, as core's wp_robots passes them.
     * @return array<string, bool|string>
     */
    public static function filter_robots( $robots ) {
        $robots = (array) $robots;

        if ( self::is_noindex() ) {
            $robots['noindex'] = true;

            // Its links still lead somewhere worth crawling, unless something
            // else already said otherwise.
            if ( empty( $robots['nofollow'] ) ) {
                $robots['follow'] = true;
            }
        }

        if ( ! empty( $robots['noindex'] ) ) {
            unset( $robots['index'], $robots['max-snippet'], $robots['max-image-preview'], $robots['max-video-preview'] );
            return $robots;
        }

        $robots['max-snippet']       = '-1';
        $robots['max-image-preview'] = 'large';
        $robots['max-video-preview'] = '-1';

        return $robots;
    }

    /**
     * Query arguments limiting a post query to what may be listed.
     *
     * For the lists handed to search engines and AI assistants — the
     * sitemaps and the AI index. Merged into a `publish` query, so private
     * posts are already out; this removes the password-protected ones, whose
     * content nobody can read, and the ones kept out of search.
     *
     * @since 2.2.0
     * @return array
     */
    public static function listed_query_args() {
        return array(
            'has_password' => false,
            // The field is deleted when cleared, but the REST API stores an
            // empty string instead: both mean "indexable".
            'meta_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One LEFT JOIN on an indexed key; the alternative is loading every post to filter it in PHP.
                'relation' => 'OR',
                array(
                    'key'     => ThatSeoAgent_Post_Seo::NOINDEX_KEY,
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => ThatSeoAgent_Post_Seo::NOINDEX_KEY,
                    'value'   => '1',
                    'compare' => '!=',
                ),
            ),
        );
    }
}
