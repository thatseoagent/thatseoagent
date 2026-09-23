<?php
/**
 * Pages that share a title or a description.
 *
 * Google asks for them to be distinct ("Make sure each page on your site
 * has a unique title", "Create unique descriptions for each page"): when two
 * pages say the same thing in search results, people cannot tell them
 * apart, and Google may rewrite them.
 *
 * Titles and written descriptions are compared across the whole site in one
 * query. Generated descriptions come from each post's rendered content, too
 * costly to build for every post on every request, so the content check
 * compares them among the posts it has just read (see
 * ThatSeoAgent_Audit_Run).
 *
 * Only pages that can appear in search results count: published, without a
 * password, not kept out of search. Two titles are the same when they read
 * the same, whatever their case, spacing or punctuation.
 *
 * @package ThatSeoAgent
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Duplicates {

    /**
     * The other pages with the same title as a post.
     *
     * @since 2.3.0
     * @param WP_Post $post Post.
     * @return array<int, int> Their IDs, empty when the title is unique.
     */
    public static function title_twins( WP_Post $post ) {
        return self::twins( 'titles', $post->ID );
    }

    /**
     * The other pages with the same written description as a post.
     *
     * @since 2.3.0
     * @param WP_Post $post Post.
     * @return array<int, int> Their IDs, empty when unique or not written.
     */
    public static function description_twins( WP_Post $post ) {
        return self::twins( 'descriptions', $post->ID );
    }

    /**
     * How a text is compared: lower case, letters and digits only.
     *
     * @since 2.3.0
     * @param string $text Title or description.
     * @return string
     */
    public static function key( $text ) {
        $text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        return trim( (string) preg_replace( '/[^\p{L}\p{N}]+/u', ' ', mb_strtolower( $text ) ) );
    }

    /**
     * The title a post shows in search results.
     *
     * Its SEO title, which replaces the document title outright, or the
     * post title with the site name, as WordPress builds it.
     *
     * @since 2.3.0
     * @param string $post_title Post title.
     * @param string $seo_title  SEO title, or ''.
     * @return string
     */
    public static function effective_title( $post_title, $seo_title ) {
        if ( '' !== trim( (string) $seo_title ) ) {
            return (string) $seo_title;
        }

        /** This filter is documented in includes/head/class-thatseoagent-title.php */
        $separator = (string) apply_filters( 'thatseoagent_title_separator', '|' );

        return $post_title . ' ' . $separator . ' ' . get_bloginfo( 'name' );
    }

    /**
     * The IDs sharing a group with a post.
     *
     * @since 2.3.0
     * @param string $which   'titles' or 'descriptions'.
     * @param int    $post_id Post ID.
     * @return array<int, int>
     */
    private static function twins( $which, $post_id ) {
        $groups = self::groups();

        if ( ! isset( $groups[ $which ]['of'][ $post_id ] ) ) {
            return array();
        }

        $key = $groups[ $which ]['of'][ $post_id ];

        return array_values( array_diff( $groups[ $which ]['ids'][ $key ], array( (int) $post_id ) ) );
    }

    /**
     * Every group of two or more pages that share a title or a written
     * description.
     *
     * Memoised per request: the content check asks once per post.
     *
     * @since 2.3.0
     * @return array{titles: array{of: array<int, string>, ids: array<string, array<int, int>>}, descriptions: array{of: array<int, string>, ids: array<string, array<int, int>>}}
     */
    private static function groups() {
        return ThatSeoAgent_Memo::remember(
            'duplicates',
            'site',
            function () {
                return self::build_groups();
            }
        );
    }

    /**
     * The query behind groups(), without memoisation.
     *
     * @since 2.3.0
     * @return array
     */
    private static function build_groups() {
        global $wpdb;

        $post_types = ThatSeoAgent_Post_Seo::post_types();
        if ( ! $post_types ) {
            return array(
                'titles'       => array( 'of' => array(), 'ids' => array() ),
                'descriptions' => array( 'of' => array(), 'ids' => array() ),
            );
        }

        $types = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // One read of every searchable post's title and SEO fields, instead
        // of one query per post the content check reads. The placeholders
        // for the post type list are built above, one per type. Memoised
        // for the request; stored nowhere, so it can never be stale.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.ID, p.post_title, t.meta_value AS seo_title, d.meta_value AS seo_description
                 FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} t ON t.post_id = p.ID AND t.meta_key = %s
                 LEFT JOIN {$wpdb->postmeta} d ON d.post_id = p.ID AND d.meta_key = %s
                 LEFT JOIN {$wpdb->postmeta} n ON n.post_id = p.ID AND n.meta_key = %s
                 WHERE p.post_status = 'publish'
                   AND p.post_password = ''
                   AND p.post_type IN ($types)
                   AND ( n.meta_value IS NULL OR n.meta_value <> '1' )",
                array_merge(
                    array( ThatSeoAgent_Post_Seo::TITLE_KEY, ThatSeoAgent_Post_Seo::DESCRIPTION_KEY, ThatSeoAgent_Post_Seo::NOINDEX_KEY ),
                    $post_types
                )
            )
        );
        // phpcs:enable

        $titles       = array();
        $descriptions = array();

        foreach ( (array) $rows as $row ) {
            $id    = (int) $row->ID;
            $title = self::key( self::effective_title( (string) $row->post_title, (string) $row->seo_title ) );

            if ( '' !== $title ) {
                $titles[ $title ][] = $id;
            }

            $description = self::key( (string) $row->seo_description );
            if ( '' !== $description ) {
                $descriptions[ $description ][] = $id;
            }
        }

        return array(
            'titles'       => self::index( $titles ),
            'descriptions' => self::index( $descriptions ),
        );
    }

    /**
     * Keep the groups of two or more, and index them by post.
     *
     * @since 2.3.0
     * @param array<string, array<int, int>> $groups Key => IDs.
     * @return array{of: array<int, string>, ids: array<string, array<int, int>>}
     */
    private static function index( array $groups ) {
        $index = array(
            'of'  => array(),
            'ids' => array(),
        );

        foreach ( $groups as $key => $ids ) {
            if ( count( $ids ) < 2 ) {
                continue;
            }

            $index['ids'][ $key ] = $ids;
            foreach ( $ids as $id ) {
                $index['of'][ $id ] = (string) $key;
            }
        }

        return $index;
    }
}
