<?php
/**
 * The site's readings: what the dashboard shows next to the bulletin.
 *
 * How many posts carry their own SEO fields, and which public files the
 * plugin is serving. Neither raises a warning; that is the bulletin's job.
 *
 * @package ThatSeoAgent
 * @since 1.20.0 Extracted from ThatSeoAgent_App.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Readings {

    /**
     * How much of the site has its own SEO fields: published posts, and how
     * many carry a written description and title, or are kept out of search;
     * catalog entries.
     *
     * @since 1.17.0 As ThatSeoAgent_App::stats().
     * @since 2.2.0 noindex.
     * @return array{published: int, descriptions: int, titles: int, noindex: int, products: int}
     */
    public static function counts() {
        $stats = array(
            'published'    => 0,
            'descriptions' => 0,
            'titles'       => 0,
            'noindex'      => 0,
            'products'     => 0,
        );

        $product_types = ThatSeoAgent_Product::post_types();

        foreach ( ThatSeoAgent_Post_Seo::post_types() as $post_type ) {
            $published = (int) wp_count_posts( $post_type )->publish;

            $stats['published']    += $published;
            $stats['descriptions'] += $published - ThatSeoAgent_Post_Seo::count_missing( $post_type, 'description' );
            $stats['titles']       += $published - ThatSeoAgent_Post_Seo::count_missing( $post_type, 'title' );
            $stats['noindex']      += $published - ThatSeoAgent_Post_Seo::count_missing( $post_type, 'noindex' );

            if ( in_array( $post_type, $product_types, true ) ) {
                $stats['products'] += $published;
            }
        }

        return $stats;
    }

    /**
     * The public files the plugin serves, and whether each is live.
     *
     * @since 1.17.0 As ThatSeoAgent_App::resources().
     * @return array<int, array{label: string, url: string, active: bool, note: string}>
     */
    public static function published() {
        $outputs   = ThatSeoAgent_Compat::outputs_enabled();
        $permalink = (bool) get_option( 'permalink_structure' );
        $front     = (int) get_option( 'page_on_front' );
        $markdown  = $front ? ThatSeoAgent_Markdown_Endpoint::url_for( $front ) : '';
        $off       = __( 'Off while another SEO plugin is active', 'thatseoagent' );

        return array(
            array(
                'label'  => __( 'XML sitemap', 'thatseoagent' ),
                'url'    => home_url( '/sitemap.xml' ),
                'active' => $outputs && $permalink,
                'note'   => $outputs ? '' : $off,
            ),
            array(
                'label'  => __( 'llms.txt', 'thatseoagent' ),
                'url'    => home_url( '/llms.txt' ),
                'active' => $outputs && $permalink && ThatSeoAgent_Llms::is_enabled(),
                'note'   => $outputs ? ( ThatSeoAgent_Llms::is_enabled() ? '' : __( 'Switched off in the settings', 'thatseoagent' ) ) : $off,
            ),
            array(
                'label'  => __( 'llms-full.txt', 'thatseoagent' ),
                'url'    => ThatSeoAgent_Llms_Full::url(),
                'active' => $outputs && $permalink && ThatSeoAgent_Llms::is_enabled(),
                'note'   => $outputs ? ( ThatSeoAgent_Llms::is_enabled() ? '' : __( 'Switched off in the settings', 'thatseoagent' ) ) : $off,
            ),
            array(
                'label'  => __( 'Catalog as JSON Lines', 'thatseoagent' ),
                'url'    => ThatSeoAgent_Catalog_Feed::url(),
                'active' => $outputs && ThatSeoAgent_Catalog_Feed::is_published(),
                'note'   => $outputs ? ( ThatSeoAgent_Product::post_types() ? '' : __( 'Needs a product catalog', 'thatseoagent' ) ) : $off,
            ),
            array(
                'label'  => __( 'robots.txt', 'thatseoagent' ),
                'url'    => home_url( '/robots.txt' ),
                'active' => true,
                'note'   => '',
            ),
            array(
                'label'  => __( 'Markdown of the homepage', 'thatseoagent' ),
                'url'    => $markdown,
                'active' => '' !== $markdown,
                'note'   => '' !== $markdown ? '' : __( 'Needs a static front page and readable permalinks', 'thatseoagent' ),
            ),
        );
    }
}
