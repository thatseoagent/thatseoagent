<?php
/**
 * Attachment pages lead to the file.
 *
 * WordPress gives every uploaded file a page of its own: the file's title,
 * the file, and the theme around it. Search engines find these pages through
 * the "Link to: Attachment page" option and index them by the thousand —
 * near-empty pages competing with the posts the files belong to.
 *
 * Each one is sent, permanently, to the file itself. WordPress 6.4 does the
 * same for new sites (the `wp_attachment_pages_enabled` option); sites
 * installed earlier keep the pages until someone changes the option, which
 * has no screen. This makes the behaviour the same on both.
 *
 * @package ThatSeoAgent
 * @since 2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Attachment_Redirect {

    /**
     * Register the hooks.
     *
     * template_redirect priority 1, before anything renders or redirects
     * the page another way.
     *
     * @since 2.2.0
     */
    public static function register() {
        add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 1 );
    }

    /**
     * Send an attachment page to its file.
     *
     * @since 2.2.0
     */
    public static function maybe_redirect() {
        if ( ! is_attachment() ) {
            return;
        }

        /**
         * Filter whether attachment pages redirect to their file.
         *
         * Return false to keep attachment pages, for a theme that makes them
         * worth visiting (a photo gallery with captions and comments).
         *
         * @since 2.2.0
         * @param bool $enabled Default true.
         */
        if ( ! apply_filters( 'thatseoagent_redirect_attachment_pages', true ) ) {
            return;
        }

        $url = wp_get_attachment_url( get_queried_object_id() );
        if ( ! $url ) {
            return;
        }

        // Not wp_safe_redirect(): the file may be served from a CDN on
        // another host. The URL is WordPress's own for the attachment.
        wp_redirect( $url, 301, 'ThatSeoAgent' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- See above.
        exit;
    }
}
