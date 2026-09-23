<?php
/**
 * What the plugin remembers for the rest of the request.
 *
 * Every per-request cache lives here, in a named group: the rendered content
 * and the generated description of each post, the Person node, the lastmod
 * dates of a taxonomy, the bulletin. Keeping them in one place gives two
 * things a static variable inside a function cannot:
 *
 * - forget_post() drops everything computed from one post, whichever module
 *   computed it. A scan reads hundreds of posts one after another, and each
 *   rendered copy would otherwise stay in memory until the request ends.
 * - reset() starts from nothing, for code (and tests) that change the site
 *   within one request and need it read again.
 *
 * Nothing here outlives the request; persistent caches (the llms.txt
 * transient, the Markdown cache) have their own owners.
 *
 * @package ThatSeoAgent
 * @since 1.20.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Memo {

    /**
     * Remembered values: group => key => value.
     *
     * @var array<string, array<int|string, mixed>>
     */
    private static $values = array();

    /**
     * The value remembered under a group and key, computing it the first
     * time.
     *
     * Groups whose values are computed from a post use the post ID as the
     * key, so forget_post() can find them. A null result is remembered too.
     *
     * @since 1.20.0
     * @param string     $group   What is remembered, e.g. 'content_html'.
     * @param int|string $key     Which one: a post ID, a taxonomy, ….
     * @param callable   $compute Returns the value when it is not known yet.
     * @return mixed
     */
    public static function remember( $group, $key, callable $compute ) {
        if ( ! isset( self::$values[ $group ] ) || ! array_key_exists( $key, self::$values[ $group ] ) ) {
            self::$values[ $group ][ $key ] = $compute();
        }

        return self::$values[ $group ][ $key ];
    }

    /**
     * Forget everything computed from a post.
     *
     * @since 1.20.0 Replaces ThatSeoAgent_Content::forget() and
     *               ThatSeoAgent_Description::forget().
     * @param WP_Post|int $post Post object or ID.
     */
    public static function forget_post( $post ) {
        $post_id = $post instanceof WP_Post ? $post->ID : (int) $post;

        foreach ( array_keys( self::$values ) as $group ) {
            unset( self::$values[ $group ][ $post_id ] );
        }
    }

    /**
     * Forget one group, for a value about to be read again.
     *
     * @since 2.7.0
     * @param string $group The group given to remember().
     */
    public static function forget( $group ) {
        unset( self::$values[ $group ] );
    }

    /**
     * Forget everything.
     *
     * @since 1.20.0
     */
    public static function reset() {
        self::$values = array();
    }
}
