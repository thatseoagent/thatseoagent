<?php
/**
 * Public content types the site owner has not looked at yet.
 *
 * A plugin or a theme can register a public content type at any time, and
 * from that moment its entries are in the sitemap, get SEO fields and are
 * indexed. If they list products — machinery, parts, a range of models —
 * they are a catalog, which the code that adds them declares; the site owner
 * is the one who notices it did not. The bulletin raises the question once
 * per new type.
 *
 * The types that existed the first time the bulletin was read are known
 * from the start: installing the plugin asks nothing about them. A type is
 * known once someone marks it as reviewed from the warning, or once the code
 * that adds it declares it as a product catalog.
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
     * The first call records what exists and returns nothing. WooCommerce's
     * products are never asked about: they cannot be a catalog.
     *
     * @since 2.5.0
     * @since 2.10.0 Without WooCommerce's product types.
     * @return array<int, array{name: string, label: string}>
     */
    public static function unreviewed() {
        $known = get_option( self::OPTION_KEY, null );

        if ( ! is_array( $known ) ) {
            self::mark_reviewed();
            return array();
        }

        $new = array();
        foreach ( array_diff( self::current(), $known, ThatSeoAgent_Product::post_types(), ThatSeoAgent_WooCommerce::post_types() ) as $type ) {
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
     * The address the warning leads to: the overview, marking the new types
     * as looked at on arrival.
     *
     * @since 2.5.0
     * @return string
     */
    public static function review_url() {
        // Not wp_nonce_url(): it escapes for HTML, and the screen escapes
        // the URL again when it prints it.
        return add_query_arg( '_wpnonce', wp_create_nonce( self::NONCE ), ThatSeoAgent_App::url( 'dashboard', array( 'review_types' => 1 ) ) );
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

    /**
     * This module's part of the bulletin: public content types nobody has
     * looked at. They are published, and only the site owner knows whether
     * they list products.
     *
     * @since 2.7.0 Moved from ThatSeoAgent_Bulletin::compose().
     * @return array{observations: array, warnings: array}
     */
    public static function bulletin() {
        $new_types = self::unreviewed();
        $warnings  = array();

        if ( $new_types ) {
            $names      = wp_list_pluck( $new_types, 'label' );
            $warnings[] = ThatSeoAgent_Bulletin::warning(
                'yellow',
                /* translators: %s: content type names, e.g. "Machines and Parts". */
                sprintf( _n( 'A new content type is being published: %s', 'New content types are being published: %s', count( $new_types ), 'thatseoagent' ), wp_sprintf_l( '%l', $names ) ),
                __( 'Its pages are already in the sitemap and have SEO fields. If they list products, such as machines, parts or models, the theme or plugin that adds it can declare it as a product catalog, so each one is described as a product.', 'thatseoagent' ),
                __( 'Mark it as reviewed', 'thatseoagent' ),
                'review_types'
            );
        }

        return array(
            'observations' => array(),
            'warnings'     => $warnings,
        );
    }
}
