<?php
/**
 * What WooCommerce marks up itself.
 *
 * WooCommerce prints its own JSON-LD in the footer (WC_Structured_Data): a
 * Product with its offers on each product, and a BreadcrumbList on the
 * product, the shop and the product category, tag and brand archives. It
 * prints no title, no description and no Open Graph, so ThatSeoAgent keeps
 * those and leaves the rest to it:
 *
 *     Product         WooCommerce's alone: its types are never a catalog
 *     BreadcrumbList  WooCommerce's alone on the pages it states one
 *     og:type         `product`, without article tags
 *
 * The visible breadcrumbs a theme prints with ThatSeoAgent stay: only the
 * markup is WooCommerce's.
 *
 * @package ThatSeoAgent
 * @since 2.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_WooCommerce {

    /**
     * Whether WooCommerce is active.
     *
     * @since 2.10.0
     * @return bool
     */
    public static function active() {
        return class_exists( 'WooCommerce', false );
    }

    /**
     * WooCommerce's product types, while it is active.
     *
     * @since 2.10.0
     * @return array<int, string>
     */
    public static function post_types() {
        return self::active() ? array( 'product', 'product_variation' ) : array();
    }

    /**
     * Whether a post is a WooCommerce product.
     *
     * @since 2.10.0
     * @param WP_Post|int|null $post Post object or ID.
     * @return bool
     */
    public static function is_product( $post ) {
        $post = get_post( $post );

        return $post && in_array( $post->post_type, self::post_types(), true );
    }

    /**
     * Whether WooCommerce states the BreadcrumbList of a page.
     *
     * A post's page from the post, wherever it is asked; with no post, the
     * current view: the shop and the product archives.
     *
     * @since 2.10.0
     * @param WP_Post|null $post The post whose page it is, null for the current view.
     * @return bool
     */
    public static function states_breadcrumbs( $post = null ) {
        if ( ! self::active() ) {
            return false;
        }

        if ( $post instanceof WP_Post ) {
            $states = self::is_product( $post )
                || ( function_exists( 'wc_get_page_id' ) && (int) $post->ID === (int) wc_get_page_id( 'shop' ) );
        } else {
            $states = ( function_exists( 'is_shop' ) && is_shop() )
                || ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() );
        }

        /**
         * Filter whether WooCommerce states the BreadcrumbList of a page, so
         * ThatSeoAgent's graph leaves it out.
         *
         * WooCommerce builds it while the theme prints its breadcrumbs.
         * Return false for a theme that prints none, to keep ThatSeoAgent's.
         *
         * @since 2.10.0
         * @param bool         $states Whether WooCommerce states it.
         * @param WP_Post|null $post   The post whose page it is, null for the current view.
         */
        return (bool) apply_filters( 'thatseoagent_woocommerce_states_breadcrumbs', $states, $post );
    }
}
