<?php
/**
 * Cache for the Markdown version of posts.
 *
 * One transient per post, `thatseoagent_md_{version}_{post_id}`, storing the
 * Markdown next to the post_modified_gmt it was built from. There is no
 * registry of keys to maintain: purging everything bumps the version held in
 * an option, which makes every old transient unreachable, and they expire on
 * their own.
 *
 * @package ThatSeoAgent
 * @since 1.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Markdown_Cache {

    /**
     * Option holding the current cache version.
     */
    const VERSION_OPTION = 'thatseoagent_markdown_cache_version';

    /**
     * Meta keys whose changes never affect the Markdown.
     *
     * The editor rewrites _edit_lock every few seconds while a post is open.
     */
    const IGNORED_META_KEYS = array( '_edit_lock', '_edit_last', '_wp_old_slug', '_encloseme', '_pingme' );

    /**
     * Register the invalidation hooks.
     *
     * Runs on every request, so it catches every save path: block editor,
     * classic editor, Quick Edit, WP-CLI, cron, abilities.
     *
     * @since 1.14.0
     */
    public static function register() {
        add_action( 'save_post', array( __CLASS__, 'invalidate' ) );
        add_action( 'deleted_post', array( __CLASS__, 'invalidate' ) );
        add_action( 'set_object_terms', array( __CLASS__, 'invalidate' ) );
        add_action( 'added_post_meta', array( __CLASS__, 'invalidate_on_meta_change' ), 10, 3 );
        add_action( 'updated_post_meta', array( __CLASS__, 'invalidate_on_meta_change' ), 10, 3 );
        add_action( 'deleted_post_meta', array( __CLASS__, 'invalidate_on_meta_change' ), 10, 3 );

        // Term and author names appear in the frontmatter of many posts.
        add_action( 'edited_term', array( __CLASS__, 'purge_all' ), 10, 0 );
        add_action( 'profile_update', array( __CLASS__, 'purge_all' ), 10, 0 );
    }

    /**
     * Cached Markdown for a post.
     *
     * @since 1.14.0
     * @param WP_Post $post Post object.
     * @return string|null Null when missing or built from an older revision.
     */
    public static function get( WP_Post $post ) {
        $cached = get_transient( self::key( $post->ID ) );

        if ( ! is_array( $cached ) || ! isset( $cached['modified'], $cached['markdown'] ) ) {
            return null;
        }

        if ( $cached['modified'] !== $post->post_modified_gmt ) {
            return null;
        }

        return (string) $cached['markdown'];
    }

    /**
     * Cache the Markdown for a post.
     *
     * @since 1.14.0
     * @param WP_Post $post     Post object.
     * @param string  $markdown Markdown.
     * @return bool
     */
    public static function set( WP_Post $post, $markdown ) {
        /**
         * Filter how long a post's Markdown is cached, in seconds.
         *
         * @since 1.14.0
         * @param int $duration Default one hour.
         */
        $duration = (int) apply_filters( 'thatseoagent_markdown_cache_duration', HOUR_IN_SECONDS );

        return set_transient(
            self::key( $post->ID ),
            array(
                'modified' => $post->post_modified_gmt,
                'markdown' => (string) $markdown,
            ),
            $duration
        );
    }

    /**
     * Drop the cached Markdown for one post.
     *
     * @since 1.14.0
     * @param int $post_id Post ID.
     */
    public static function invalidate( $post_id ) {
        delete_transient( self::key( (int) $post_id ) );
    }

    /**
     * Drop a post's cached Markdown when its meta changes.
     *
     * The featured image and the SEO description both live in post meta.
     *
     * @since 1.14.0
     * @param int|int[] $meta_ids Meta ID(s).
     * @param int       $post_id  Post ID.
     * @param string    $meta_key Meta key.
     */
    public static function invalidate_on_meta_change( $meta_ids, $post_id, $meta_key ) {
        if ( in_array( $meta_key, self::IGNORED_META_KEYS, true ) ) {
            return;
        }

        self::invalidate( $post_id );
    }

    /**
     * Drop every post's cached Markdown.
     *
     * @since 1.14.0
     */
    public static function purge_all() {
        update_option( self::VERSION_OPTION, self::version() + 1, false );
    }

    /**
     * Transient key for a post.
     *
     * @since 1.14.0
     * @param int $post_id Post ID.
     * @return string
     */
    private static function key( $post_id ) {
        return sprintf( 'thatseoagent_md_%d_%d', self::version(), $post_id );
    }

    /**
     * Current cache version.
     *
     * @since 1.14.0
     * @return int
     */
    private static function version() {
        return (int) get_option( self::VERSION_OPTION, 1 );
    }
}
