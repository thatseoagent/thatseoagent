<?php
/**
 * The sample post and page WordPress installs with, while still published.
 *
 * "Hello world!" and the "Sample Page" are published on every new site. Left
 * there, they are indexed like any page: search results and AI assistants
 * show a placeholder as part of the site, and the sitemap lists it.
 *
 * Recognized by the slug WordPress gave them, in English, in the site's
 * language and in the languages sites are most often installed in — the
 * slug is translated at install time.
 *
 * @package ThatSeoAgent
 * @since 2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Sample_Content {

    /**
     * The sample post and page, while published.
     *
     * @since 2.5.0
     * @return array<int, array{id: int, title: string, type: string}>
     */
    public static function published() {
        $found = array();

        foreach ( array( 'post' => self::post_slugs(), 'page' => self::page_slugs() ) as $post_type => $slugs ) {
            $posts = get_posts(
                array(
                    'post_type'      => $post_type,
                    'post_status'    => 'publish',
                    'post_name__in'  => $slugs,
                    'posts_per_page' => 1,
                    'orderby'        => 'ID',
                    'order'          => 'ASC',
                    'no_found_rows'  => true,
                )
            );

            foreach ( $posts as $post ) {
                $found[] = array(
                    'id'    => (int) $post->ID,
                    'title' => html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
                    'type'  => $post_type,
                );
            }
        }

        return $found;
    }

    /**
     * Slugs of the sample post.
     *
     * @since 2.5.0
     * @return array<int, string>
     */
    private static function post_slugs() {
        return array_values(
            array_unique(
                array(
                    'hello-world',
                    // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Core's own string, translated as core translated it at install.
                    sanitize_title( _x( 'hello-world', 'Default post slug', 'default' ) ),
                    'hola-mundo',
                    'ola-mundo',
                    'bonjour-tout-le-monde',
                    'hallo-welt',
                    'ciao-mondo',
                )
            )
        );
    }

    /**
     * Slugs of the sample page.
     *
     * @since 2.5.0
     * @return array<int, string>
     */
    private static function page_slugs() {
        return array_values(
            array_unique(
                array(
                    'sample-page',
                    // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Core's own string, translated as core translated it at install.
                    sanitize_title( _x( 'sample-page', 'Default page slug', 'default' ) ),
                    'pagina-ejemplo',
                    'pagina-de-exemplo',
                    'page-d-exemple',
                    'beispiel-seite',
                    'pagina-di-esempio',
                )
            )
        );
    }

    /**
     * This module's part of the bulletin: the sample post and page, while
     * still published.
     *
     * @since 2.7.0 Moved from ThatSeoAgent_Bulletin::compose().
     * @return array{observations: array, warnings: array}
     */
    public static function bulletin() {
        $sample   = self::published();
        $warnings = array();

        if ( $sample ) {
            $titles     = wp_list_pluck( $sample, 'title' );
            $warnings[] = ThatSeoAgent_Bulletin::warning(
                'yellow',
                /* translators: %s: the titles, e.g. "Hello world! and Sample Page". */
                sprintf( _n( 'WordPress\'s sample content is still published: %s', 'WordPress\'s sample content is still published: %s', count( $sample ), 'thatseoagent' ), wp_sprintf_l( '%l', $titles ) ),
                __( 'Search engines index it like any page, and it is in the sitemap: a placeholder shown as part of the site. Delete it, or replace it with something of your own.', 'thatseoagent' ),
                1 === count( $sample ) ? __( 'Edit it', 'thatseoagent' ) : __( 'See the posts', 'thatseoagent' ),
                1 === count( $sample ) ? 'edit_post' : 'posts',
                1 === count( $sample ) ? $sample[0]['id'] : 0
            );
        }

        return array(
            'observations' => array(),
            'warnings'     => $warnings,
        );
    }
}
