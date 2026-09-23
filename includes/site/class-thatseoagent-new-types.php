<?php
/**
 * Public content types the site owner has not looked at yet.
 *
 * A plugin or a theme can register a public content type at any time, and
 * from that moment its entries are in the sitemap, get SEO fields and are
 * indexed. If they list products — machinery, parts, a range of models —
 * they are a catalog, and only the site owner can say so. The bulletin
 * raises the question once per new type.
 *
 * The types that existed the first time the bulletin was read are known
 * from the start: installing the plugin asks nothing about them. A type is
 * known once someone follows the warning to the catalog settings, or saves
 * those settings.
 *
 * @package ThatSeoAgent
 * @since 2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_New_Types {

    /**
     * Option holding the content types already looked at.
     */
    const OPTION_KEY = 'thatseoagent_known_post_types';

    /**
     * Nonce action of the "review" link.
     */
    const NONCE = 'thatseoagent_review_types';

    /**
     * Register the hooks.
     *
     * @since 2.5.0
     */
    public static function register() {
        add_action( 'admin_init', array( __CLASS__, 'maybe_mark_reviewed' ) );
        add_action( 'update_option_' . ThatSeoAgent_Product::OPTION_KEY, array( __CLASS__, 'mark_reviewed' ) );
        add_action( 'add_option_' . ThatSeoAgent_Product::OPTION_KEY, array( __CLASS__, 'mark_reviewed' ) );
    }

    /**
     * The public content types a plugin or theme added: not WordPress's own,
     * and viewable on the site.
     *
     * @since 2.5.0
     * @return array<int, string>
     */
    private static function current() {
        $types = get_post_types(
            array(
                'public'   => true,
                '_builtin' => false,
            )
        );

        return array_values( array_filter( $types, 'is_post_type_viewable' ) );
    }

    /**
     * The types nobody has looked at yet, with their names.
     *
     * The first call records what exists and returns nothing.
     *
     * @since 2.5.0
     * @return array<int, array{name: string, label: string}>
     */
    public static function unreviewed() {
        $known = get_option( self::OPTION_KEY, null );

        if ( ! is_array( $known ) ) {
            self::mark_reviewed();
            return array();
        }

        $new = array();
        foreach ( array_diff( self::current(), $known, ThatSeoAgent_Product::post_types() ) as $type ) {
            $object = get_post_type_object( $type );
            $new[]  = array(
                'name'  => $type,
                'label' => $object ? $object->labels->name : $type,
            );
        }

        return $new;
    }

    /**
     * Record every current type as looked at.
     *
     * Types that disappear stay recorded: if a plugin is deactivated and
     * comes back, its type was already seen.
     *
     * @since 2.5.0
     */
    public static function mark_reviewed() {
        $known = get_option( self::OPTION_KEY, array() );
        $known = is_array( $known ) ? $known : array();

        // Not autoloaded: read on the ThatSeoAgent screen only.
        update_option( self::OPTION_KEY, array_values( array_unique( array_merge( $known, self::current() ) ) ), false );
    }

    /**
     * The address the warning leads to: the catalog settings, marking the
     * new types as looked at on arrival.
     *
     * @since 2.5.0
     * @return string
     */
    public static function review_url() {
        // Not wp_nonce_url(): it escapes for HTML, and the screen escapes
        // the URL again when it prints it.
        return add_query_arg( '_wpnonce', wp_create_nonce( self::NONCE ), ThatSeoAgent_App::url( 'settings', array( 'review_types' => 1 ) ) ) . '#thatseoagent_products_section';
    }

    /**
     * Mark the types as looked at when the review link is followed.
     *
     * @since 2.5.0
     */
    public static function maybe_mark_reviewed() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified just below, before anything is written.
        if ( empty( $_GET['review_types'] ) || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        check_admin_referer( self::NONCE );
        self::mark_reviewed();
    }
}
