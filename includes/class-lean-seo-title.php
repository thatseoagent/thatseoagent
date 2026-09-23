<?php
/**
 * The document <title>.
 *
 * WordPress builds the title itself; this module decides when it is replaced
 * outright — by the `lean_seo_document_title` filter, then by a post's custom
 * SEO title — and supplies the separator. The `lean_seo_title` filter, which
 * shapes the title used in meta tags, belongs to Lean_SEO_Meta.
 *
 * @package Lean_SEO
 * @since 1.19.0 Moved out of Lean_SEO.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lean_SEO_Title {

    /**
     * Register the hooks.
     *
     * @since 1.19.0
     */
    public static function register() {
        add_filter( 'pre_get_document_title', array( __CLASS__, 'filter_document_title' ), 10 );
        add_filter( 'document_title_separator', array( __CLASS__, 'separator' ) );
    }

    /**
     * Short-circuit the document <title> tag entirely.
     *
     * Returning a non-empty string here bypasses WordPress core's title
     * assembly. We only act if a filter callback provides a value via
     * the `lean_seo_document_title` filter, letting themes/site-specific
     * plugins set the homepage title or override any context without
     * rebuilding the full fallback chain.
     *
     * @since 1.5.0
     * @param string $title Current title (empty string by default).
     * @return string
     */
    public static function filter_document_title( $title ) {
        /**
         * Filter the final <title> tag output.
         *
         * Return a non-empty string to replace WordPress's document title
         * entirely. Return empty (default) to let core + lean_seo build
         * the title normally. This is the only filter that can override
         * the homepage title without touching the theme.
         *
         * @since 1.5.0
         * @param string $title   Empty by default; override with a string to short-circuit.
         * @param string $context Current page context (see Lean_SEO_Meta::get_context()).
         */
        $override = apply_filters( 'lean_seo_document_title', '', Lean_SEO_Meta::get_context() );

        if ( '' !== $override ) {
            return $override;
        }

        // A custom SEO title replaces the document title outright, rather
        // than only its title part. The field is the full title a site owner
        // wants in search results — it is what the meta box preview shows
        // verbatim — and before 1.12.0 WordPress still appended the site name
        // after it, so "Product | Acme" went out as "Product | Acme | Acme".
        if ( is_singular() ) {
            $custom_title = Lean_SEO_Post_Seo::get( get_post(), 'title' );
            if ( $custom_title ) {
                return $custom_title;
            }
        }

        return $title;
    }

    /**
     * Title separator.
     *
     * @since 1.0.0
     * @since 1.5.0 Made filterable via lean_seo_title_separator.
     * @param string $sep Core's separator, ignored.
     * @return string
     */
    public static function separator( $sep ) {
        /**
         * Filter the separator used in the document title.
         *
         * @since 1.5.0
         * @param string $separator Default '|'.
         */
        return apply_filters( 'lean_seo_title_separator', '|' );
    }
}
