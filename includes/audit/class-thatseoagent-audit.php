<?php
/**
 * SEO audit of a post.
 *
 * The checks behind the `audit-post-seo` and `scan-seo-issues` abilities.
 * They lived inside ThatSeoAgent_Abilities until 1.16.0; the abilities, WP-CLI
 * and the admin now share this one implementation, so a score cannot differ
 * depending on who asked.
 *
 * Since 2.3.0 the checks follow the rules That SEO Agent's MCP server applies
 * (docs/google-search-central-conformance.md in that repository): the plugin
 * and the MCP must not tell the same site two different things. The rule
 * they share is that nothing Google does not ask for is reported as a
 * problem. Every finding says where it comes from:
 *
 *     google         Google Search Central asks for it
 *     accessibility  WCAG 2.2 asks for it; Google does not rank on it
 *     heuristic      our own judgement, said to be
 *
 * Only the first two move the score. Gone, because Google says the opposite:
 * minimum title and description lengths ("There's no limit on how long a
 * meta description can be"), a minimum word count ("The length of the
 * content alone doesn't matter for ranking purposes"), and requiring H2
 * headings or images. Titles and descriptions are truncated by pixel width
 * and device, so a long one "may be cut", never "is too long".
 *
 * A check that cannot be measured is not a check that failed. When a post
 * has almost no text of its own in the editor — its page is built by the
 * theme, a page builder or custom fields — findings that assert an absence
 * ("no links") are not made; the post lists them as not measured instead.
 *
 * @package ThatSeoAgent
 * @since 1.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Audit {

    /**
     * Title length past which it may be cut in search results.
     *
     * Deliberately high: truncation depends on pixel width and device, and
     * Google publishes no limit. The MCP uses the same figure.
     *
     * @since 2.3.0
     */
    const TITLE_MAY_TRUNCATE = 70;

    /**
     * Description length past which it may be cut in search results.
     *
     * @since 2.3.0
     */
    const DESCRIPTION_MAY_TRUNCATE = 165;

    /**
     * Characters of text below which a post is not measured for absences.
     *
     * The MCP's threshold for "the content did not arrive in the HTML".
     *
     * @since 2.3.0
     */
    const MEASURABLE_TEXT = 300;

    /**
     * Points each finding costs, by severity.
     *
     * Heuristic findings are always 'info', so they cost nothing.
     *
     * @since 2.3.0
     */
    const COST = array(
        'error'   => 20,
        'warning' => 5,
        'info'    => 0,
    );

    /**
     * Audit one post.
     *
     * @since 1.16.0 Moved from ThatSeoAgent_Abilities::audit_post_seo().
     * @since 2.3.0 Aligned with the MCP's rules; `source` on each issue, and
     *              `not_measured`.
     * @param WP_Post $post Post to audit.
     * @since 2.3.0 `seo`: the title and description the page shows in results.
     * @return array{post_id: int, title: string, url: string, seo: array, score: int, issues: array, not_measured: array, stats: array}
     */
    public static function post( WP_Post $post ) {
        // Rendered, not the raw serialization: the headings, images and links
        // of a block-built post only exist once the blocks have run.
        $content      = ThatSeoAgent_Content::html( $post );
        $issues       = array();
        $not_measured = array();

        $custom       = ThatSeoAgent_Post_Seo::all( $post );
        $title        = '' !== $custom['title'] ? $custom['title'] : get_the_title( $post );
        $description  = '' !== $custom['description'] ? $custom['description'] : ThatSeoAgent_Description::generate( $post );

        $title_length = mb_strlen( $title );
        $desc_length  = mb_strlen( $description );
        $word_count   = ThatSeoAgent_Content::word_count( $post );
        $measurable   = mb_strlen( ThatSeoAgent_Content::text( $post ) ) > self::MEASURABLE_TEXT;

        $headings = self::heading_stats( $content );
        $links    = self::link_stats( $content );
        $images   = self::image_alt_stats( $content );

        $stats = array(
            'title_length'       => $title_length,
            'description_length' => $desc_length,
            'word_count'         => $word_count,
            'h1_count'           => $headings['h1'],
            'h2_count'           => $headings['h2'],
            'internal_links'     => $links['internal'],
            'external_links'     => $links['external'],
            'uncrawlable_links'  => $links['uncrawlable'],
            'images'             => $images['total'],
            'images_without_alt' => $images['missing'],
            'images_decorative'  => $images['decorative'],
        );

        // The blog's post list: its own content is never shown, and its
        // description is the site's.
        if ( (int) get_option( 'page_for_posts' ) === $post->ID ) {
            return array(
                'post_id'      => $post->ID,
                'title'        => $post->post_title,
                'url'          => get_permalink( $post->ID ),
                'seo'          => array(
                    'title'               => ThatSeoAgent_Duplicates::effective_title( $post->post_title, $custom['title'] ),
                    'description'         => $description,
                    'description_written' => '' !== $custom['description'],
                ),
                'score'        => 100,
                'issues'       => array(),
                'not_measured' => array(
                    array(
                        'type'    => 'posts_page',
                        'message' => __( 'Not checked: this page is the list of posts (Settings → Reading), so its own content is never shown.', 'thatseoagent' ),
                    ),
                ),
                'stats'        => $stats,
            );
        }

        if ( ThatSeoAgent_Indexing::is_post_noindex( $post ) ) {
            $issues[] = self::issue(
                'noindex',
                'info',
                'google',
                __( 'Kept out of search results: it has noindex and is not in the sitemap', 'thatseoagent' )
            );
        }

        if ( $title_length > self::TITLE_MAY_TRUNCATE ) {
            $issues[] = self::issue(
                'title_may_truncate',
                'info',
                'google',
                __( 'The title may be cut in search results, which trim it to the width of the screen', 'thatseoagent' ),
                /* translators: %d: number of characters. */
                sprintf( __( '%d characters', 'thatseoagent' ), $title_length )
            );
        }

        if ( 0 === $desc_length ) {
            $issues[] = self::issue(
                'description_missing',
                'warning',
                'google',
                __( 'No description: there is no text to write one from, so search engines will pick a snippet themselves', 'thatseoagent' )
            );
        } elseif ( $desc_length > self::DESCRIPTION_MAY_TRUNCATE ) {
            $issues[] = self::issue(
                'description_may_truncate',
                'info',
                'google',
                __( 'The description may be cut in search results; Google sets no limit, the screen does', 'thatseoagent' ),
                /* translators: %d: number of characters. */
                sprintf( __( '%d characters', 'thatseoagent' ), $desc_length )
            );
        }

        $title_twins = ThatSeoAgent_Duplicates::title_twins( $post );
        if ( $title_twins ) {
            $issues[] = self::issue(
                'duplicate_title',
                'warning',
                'google',
                /* translators: %d: number of other pages. */
                sprintf( _n( 'Same title in search results as %d other page: people cannot tell them apart', 'Same title in search results as %d other pages: people cannot tell them apart', count( $title_twins ), 'thatseoagent' ), count( $title_twins ) ),
                self::twin_names( $title_twins )
            );
        }

        $description_twins = ThatSeoAgent_Duplicates::description_twins( $post );
        if ( $description_twins ) {
            $issues[] = self::issue(
                'duplicate_description',
                'warning',
                'google',
                /* translators: %d: number of other pages. */
                sprintf( _n( 'Same written description as %d other page', 'Same written description as %d other pages', count( $description_twins ), 'thatseoagent' ), count( $description_twins ) ),
                self::twin_names( $description_twins )
            );
        }

        if ( $links['uncrawlable'] > 0 ) {
            $issues[] = self::issue(
                'uncrawlable_links',
                'warning',
                'google',
                __( 'Some links cannot be followed by search engines: they have no href, or sit on an element that is not a link', 'thatseoagent' ),
                $links['uncrawlable_example']
            );
        }

        if ( $measurable ) {
            if ( 0 === $links['internal'] ) {
                $issues[] = self::issue(
                    'no_internal_links',
                    'info',
                    'heuristic',
                    __( 'No links to other pages of the site: readers and crawlers reach nothing else from here', 'thatseoagent' )
                );
            }
        } else {
            $not_measured[] = array(
                'type'    => 'no_internal_links',
                'message' => __( 'Links to other pages: not measured, the page has almost no text in the editor. Its content may come from the theme, a page builder or custom fields.', 'thatseoagent' ),
            );
        }

        // Links between the site's pages, from the whole site's graph.
        $graph = ThatSeoAgent_Links::graph();

        if ( ThatSeoAgent_Links::needs_links( $post ) && empty( $graph['inbound'][ $post->ID ] ) && empty( $graph['navigation'][ $post->ID ] ) && ! ThatSeoAgent_Indexing::is_post_noindex( $post ) ) {
            $issues[] = self::issue(
                'orphan',
                'info',
                'heuristic',
                __( 'No page of the site links here — not the menus, not the header or footer, not another page\'s text. Search engines still find it in the sitemap; people browsing the site never reach it.', 'thatseoagent' )
            );
        }

        if ( ! empty( $graph['broken'][ $post->ID ] ) ) {
            $broken   = $graph['broken'][ $post->ID ];
            $issues[] = self::issue(
                'broken_links',
                'info',
                'heuristic',
                /* translators: %d: number of links. */
                sprintf( _n( '%d link leads to an address of the site that does not exist (404)', '%d links lead to addresses of the site that do not exist (404)', count( $broken ), 'thatseoagent' ), count( $broken ) ),
                implode( ', ', array_slice( $broken, 0, 3 ) ) . ( count( $broken ) > 3 ? ', …' : '' )
            );
        }

        if ( ! empty( $graph['unchecked'][ $post->ID ] ) ) {
            $not_measured[] = array(
                'type'    => 'broken_links',
                /* translators: %d: number of links. */
                'message' => sprintf( _n( 'Links: %d link to an address the site did not recognize was not checked this time.', 'Links: %d links to addresses the site did not recognize were not checked this time.', $graph['unchecked'][ $post->ID ], 'thatseoagent' ), $graph['unchecked'][ $post->ID ] ),
            );
        }

        if ( $images['missing'] > 0 ) {
            $issues[] = self::issue(
                'missing_alt_text',
                'warning',
                'google',
                __( 'Some images have no alt attribute. One that only decorates should have an empty one, alt="", so screen readers skip it.', 'thatseoagent' ),
                /* translators: %d: number of images. */
                sprintf( _n( '%d image without alt', '%d images without alt', $images['missing'], 'thatseoagent' ), $images['missing'] )
            );
        }

        // The featured image is printed by the theme, outside the content,
        // so the scan above never sees it.
        $thumbnail_id = get_post_thumbnail_id( $post );
        if ( $thumbnail_id && '' === trim( (string) get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) ) ) {
            $issues[] = self::issue(
                'featured_image_missing_alt',
                'info',
                'google',
                __( 'The featured image has no alt text in the media library', 'thatseoagent' ),
                (string) $thumbnail_id
            );
        }

        if ( $headings['h1'] > 0 ) {
            $issues[] = self::issue(
                'h1_in_content',
                'info',
                'accessibility',
                __( 'The content has its own H1; with the title the theme prints, the page usually ends up with two. Google does not mind, but screen readers announce each as the page\'s title.', 'thatseoagent' )
            );
        }

        if ( '' !== $headings['skipped'] ) {
            $issues[] = self::issue(
                'heading_level_skipped',
                'info',
                'accessibility',
                __( 'A heading skips a level, so the outline screen readers navigate by has a gap. Google does not mind the order.', 'thatseoagent' ),
                $headings['skipped']
            );
        }

        // Catalog entries: what their Product markup is missing, measured
        // against Google's product structured data.
        foreach ( ThatSeoAgent_Product::validate( $post ) as $issue ) {
            $issues[] = $issue + array( 'source' => 'google' );
        }

        return array(
            'post_id'      => $post->ID,
            'title'        => $post->post_title,
            'url'          => get_permalink( $post->ID ),
            'seo'          => array(
                'title'               => ThatSeoAgent_Duplicates::effective_title( $post->post_title, $custom['title'] ),
                'description'         => $description,
                'description_written' => '' !== $custom['description'],
            ),
            'score'        => self::score( $issues, $images['missing'] ),
            'issues'       => $issues,
            'not_measured' => $not_measured,
            'stats'        => $stats,
        );
    }

    /**
     * The first few pages of a group, by title, for a finding's value.
     *
     * @since 2.3.0
     * @param array<int, int> $ids Post IDs.
     * @return string
     */
    public static function twin_names( array $ids ) {
        $names = array();

        foreach ( array_slice( $ids, 0, 3 ) as $id ) {
            $names[] = html_entity_decode( get_the_title( $id ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        }

        if ( count( $ids ) > 3 ) {
            $names[] = '…';
        }

        return implode( ', ', $names );
    }

    /**
     * The score out of 100: what the findings backed by Google or WCAG cost.
     *
     * Each image without alt costs on its own, up to four.
     *
     * @since 2.3.0
     * @param array $issues             Findings.
     * @param int   $images_without_alt Images without alt.
     * @return int
     */
    private static function score( array $issues, $images_without_alt ) {
        $score = 100;

        foreach ( $issues as $issue ) {
            if ( 'heuristic' === $issue['source'] ) {
                continue;
            }

            $cost   = isset( self::COST[ $issue['severity'] ] ) ? self::COST[ $issue['severity'] ] : 0;
            $count  = 'missing_alt_text' === $issue['type'] ? min( 4, max( 1, (int) $images_without_alt ) ) : 1;
            $score -= $cost * $count;
        }

        return max( 0, $score );
    }

    /**
     * One finding.
     *
     * @since 2.3.0
     * @param string $type     Machine-readable type.
     * @param string $severity 'error', 'warning' or 'info'.
     * @param string $source   'google', 'accessibility' or 'heuristic'.
     * @param string $message  What is wrong and why, in plain words.
     * @param string $value    The measured value, when there is one.
     * @return array{type: string, severity: string, source: string, message: string, value: string}
     */
    private static function issue( $type, $severity, $source, $message, $value = '' ) {
        return array(
            'type'     => $type,
            'severity' => 'heuristic' === $source ? 'info' : $severity,
            'source'   => $source,
            'message'  => $message,
            'value'    => (string) $value,
        );
    }

    /**
     * Count headings, and find the first skipped level.
     *
     * Only between headings of the content: the first one sets where the
     * outline starts. What the theme prints above the content — the title,
     * a section heading — cannot be seen from here, so a content starting
     * at H3 is not called a skip.
     *
     * @since 2.3.0
     * @param string $html Rendered HTML.
     * @return array{h1: int, h2: int, skipped: string} `skipped` reads "H2 → H4", or ''.
     */
    public static function heading_stats( $html ) {
        $stats = array(
            'h1'      => 0,
            'h2'      => 0,
            'skipped' => '',
        );

        $tags     = new WP_HTML_Tag_Processor( (string) $html );
        $previous = 0;

        while ( $tags->next_tag() ) {
            if ( $tags->is_tag_closer() || ! preg_match( '/^H([1-6])$/', $tags->get_tag(), $match ) ) {
                continue;
            }

            $level = (int) $match[1];

            if ( 1 === $level ) {
                $stats['h1']++;
            } elseif ( 2 === $level ) {
                $stats['h2']++;
            }

            if ( '' === $stats['skipped'] && $previous && $level > $previous + 1 ) {
                $stats['skipped'] = sprintf( 'H%d → H%d', $previous, $level );
            }

            $previous = $level;
        }

        return $stats;
    }

    /**
     * Count links, and the ones search engines cannot follow.
     *
     * Google follows `<a href>` and nothing else: not an `<a>` without href
     * that navigates with a click handler or a router attribute, and not an
     * href on a span, div, li or button ("Make your links crawlable").
     *
     * @since 2.3.0
     * @param string $html Rendered HTML.
     * @return array{internal: int, external: int, uncrawlable: int, uncrawlable_example: string}
     */
    public static function link_stats( $html ) {
        $stats = array(
            'internal'            => 0,
            'external'            => 0,
            'uncrawlable'         => 0,
            'uncrawlable_example' => '',
        );

        $site_host = wp_parse_url( home_url(), PHP_URL_HOST );
        $tags      = new WP_HTML_Tag_Processor( (string) $html );

        while ( $tags->next_tag() ) {
            if ( $tags->is_tag_closer() ) {
                continue;
            }

            $tag  = $tags->get_tag();
            $href = $tags->get_attribute( 'href' );

            if ( 'A' === $tag && is_string( $href ) && '' !== trim( $href ) ) {
                $href = trim( $href );

                // Same-page anchors, mail and phone links go nowhere a
                // crawler counts.
                if ( '#' === $href[0] || preg_match( '/^(mailto|tel|javascript):/i', $href ) ) {
                    continue;
                }

                $host = wp_parse_url( $href, PHP_URL_HOST );
                if ( ! $host || $host === $site_host ) {
                    $stats['internal']++;
                } else {
                    $stats['external']++;
                }
                continue;
            }

            $navigates = null !== $tags->get_attribute( 'routerlink' ) || null !== $tags->get_attribute( 'ui-sref' );
            $uncrawlable = ( 'A' === $tag && ( $navigates || null !== $tags->get_attribute( 'onclick' ) ) )
                || ( in_array( $tag, array( 'SPAN', 'DIV', 'LI', 'BUTTON' ), true ) && null !== $href )
                || ( 'A' !== $tag && $navigates );

            if ( $uncrawlable ) {
                $stats['uncrawlable']++;
                if ( '' === $stats['uncrawlable_example'] ) {
                    $stats['uncrawlable_example'] = '<' . strtolower( $tag ) . '>';
                }
            }
        }

        return $stats;
    }

    /**
     * Count images and classify their alt attributes.
     *
     * Three cases, which a regex over the tag cannot tell apart reliably:
     *
     *     <img src="…">             missing    — a real defect
     *     <img src="…" alt="">      decorative — correct for ornamental
     *                                            images; screen readers skip it
     *     <img src="…" alt="Text">  described
     *
     * Before 1.16.0 the decorative case counted as missing, so a correctly
     * marked-up spacer or icon lowered the score.
     *
     * @since 1.16.0
     * @param string $html Rendered HTML.
     * @return array{total: int, missing: int, decorative: int}
     */
    public static function image_alt_stats( $html ) {
        $stats = array(
            'total'      => 0,
            'missing'    => 0,
            'decorative' => 0,
        );

        $tags = new WP_HTML_Tag_Processor( (string) $html );

        while ( $tags->next_tag( 'img' ) ) {
            $stats['total']++;

            $alt = $tags->get_attribute( 'alt' );

            if ( null === $alt ) {
                $stats['missing']++;
            } elseif ( true === $alt || '' === trim( (string) $alt ) ) {
                // A bare `alt` attribute is an empty one.
                $stats['decorative']++;
            }
        }

        return $stats;
    }

    /**
     * Audit many posts, worst first.
     *
     * @since 1.16.0 Moved from ThatSeoAgent_Abilities::scan_seo_issues().
     * @param int    $limit      Maximum posts to return.
     * @param int    $min_issues Minimum issues for a post to be listed.
     * @param string $post_type  Post type to scan.
     * @return array<int, array>
     */
    public static function scan( $limit = 50, $min_issues = 1, $post_type = 'post' ) {
        $limit      = absint( $limit );
        $min_issues = absint( $min_issues );

        if ( $limit < 1 ) {
            $limit = 50;
        }

        // Posts are fetched in batches so a large site never loads every
        // post_content into memory at once. Scanning stops as soon as $limit
        // matching posts are found, or once the scan cap is reached.
        $batch_size = 100;
        $max_scan   = max( 500, $limit * 20 );
        $scanned    = 0;
        $offset     = 0;
        $results    = array();

        while ( $scanned < $max_scan && count( $results ) < $limit ) {
            $posts = get_posts( array(
                'post_type'              => $post_type,
                'post_status'            => 'publish',
                'posts_per_page'         => $batch_size,
                'offset'                 => $offset,
                'orderby'                => 'date',
                'order'                  => 'DESC',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ) );

            if ( empty( $posts ) ) {
                break;
            }

            $offset += count( $posts );

            foreach ( $posts as $post ) {
                $scanned++;
                $audit = self::post( $post );

                // Each audit renders the post; the memoised copies are only
                // useful within one post, and would otherwise pile up for the
                // whole scan.
                ThatSeoAgent_Memo::forget_post( $post );

                if ( count( $audit['issues'] ) >= $min_issues ) {
                    $results[] = array(
                        'post_id'     => $audit['post_id'],
                        'title'       => $audit['title'],
                        'url'         => $audit['url'],
                        'score'       => $audit['score'],
                        'issue_count' => count( $audit['issues'] ),
                        'top_issues'  => array_slice( array_column( $audit['issues'], 'type' ), 0, 3 ),
                    );
                }

                if ( count( $results ) >= $limit || $scanned >= $max_scan ) {
                    break;
                }
            }
        }

        // Sort by score ascending (worst first)
        usort( $results, function( $a, $b ) {
            return $a['score'] - $b['score'];
        } );

        return $results;
    }
}
