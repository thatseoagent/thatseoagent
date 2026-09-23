<?php
/**
 * Homepage Applier
 *
 * Reads Lean_SEO_Homepage settings and populates the title/description
 * filters when the current context is the homepage.
 *
 * @package Lean_SEO
 * @since 1.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lean_SEO_Homepage_Applier {

    /**
     * Register filter hooks.
     */
    public static function register() {
        add_filter( 'lean_seo_document_title', array( __CLASS__, 'filter_document_title' ), 10, 2 );
        add_filter( 'lean_seo_description',    array( __CLASS__, 'filter_description' ),    10, 2 );
    }

    /**
     * Return the configured homepage title when appropriate.
     *
     * Only acts on the homepage context, and only when another callback
     * hasn't already provided an override.
     *
     * @param string $title   Existing override (empty by default).
     * @param string $context Current page context.
     * @return string
     */
    public static function filter_document_title( $title, $context ) {
        if ( '' !== $title ) {
            return $title;
        }
        if ( 'home' !== $context ) {
            return $title;
        }

        $settings = Lean_SEO_Homepage::get_settings();
        if ( '' === $settings['title'] ) {
            return $title;
        }

        return Lean_SEO_Homepage::expand_variables( $settings['title'] );
    }

    /**
     * Return the configured homepage description when appropriate.
     *
     * Acts on home context. Runs after the default resolution so any
     * callback earlier in the chain still takes priority.
     *
     * @param string $description Resolved description.
     * @param string $context     Current page context.
     * @return string
     */
    public static function filter_description( $description, $context ) {
        if ( 'home' !== $context ) {
            return $description;
        }

        $settings = Lean_SEO_Homepage::get_settings();
        if ( '' === $settings['description'] ) {
            return $description;
        }

        // Only override when the default description is empty or falls back
        // to the blog tagline. Sites that explicitly wire their own home
        // description via filter still win because a non-empty, non-tagline
        // value means something intentional is already there.
        $tagline = (string) get_bloginfo( 'description' );
        if ( '' !== $description && $description !== $tagline ) {
            return $description;
        }

        return Lean_SEO_Homepage::expand_variables( $settings['description'] );
    }
}
