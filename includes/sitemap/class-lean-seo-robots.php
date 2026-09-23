<?php
/**
 * robots.txt.
 *
 * WordPress serves a virtual robots.txt when the site root has no physical
 * one; this module edits it so the `Sitemap:` directive points at Lean SEO's
 * index, and clears what a previous SEO plugin left behind.
 *
 * @package Lean_SEO
 * @since 1.19.0 Moved out of Lean_SEO.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lean_SEO_Robots {

    /**
     * Register the hooks.
     *
     * Priority 999: after anything else that edits the file, so the
     * directives this removes cannot be added back behind it.
     *
     * @since 1.19.0
     */
    public static function register() {
        add_filter( 'robots_txt', array( __CLASS__, 'filter' ), 999, 2 );
    }

    /**
     * Point the Sitemap: directive at Lean SEO's index.
     *
     * @since 1.0.0
     * @param string $output robots.txt as built so far.
     * @param bool   $public Whether the site is public. Unused: the sitemap
     *                       is listed either way, as core does.
     * @return string
     */
    public static function filter( $output, $public ) {
        // Remove Yoast comment blocks
        $output = preg_replace( '/# START YOAST BLOCK.*?# END YOAST BLOCK\s*/s', '', $output );

        // Drop Sitemap: directives that point at this site — ours is appended
        // below, and leftovers from a previous SEO plugin are stale. Sitemap
        // directives for other hosts belong to someone else and are kept.
        $home_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
        $lines     = preg_split( '/\r\n|\r|\n/', $output );
        $kept      = array();

        foreach ( $lines as $line ) {
            if ( preg_match( '/^\s*sitemap\s*:\s*(\S+)/i', $line, $matches ) ) {
                $host = strtolower( (string) wp_parse_url( $matches[1], PHP_URL_HOST ) );
                if ( '' === $host || $host === $home_host ) {
                    continue;
                }
            }

            $kept[] = $line;
        }

        $output = implode( "\n", $kept );

        // Trim and add our sitemap
        $output = trim( $output );
        if ( $output ) {
            $output .= "\n\n";
        }
        $output .= 'Sitemap: ' . home_url( '/sitemap.xml' ) . "\n";

        return $output;
    }
}
