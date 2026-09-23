<?php
/**
 * SEO audit of a post.
 *
 * The checks behind the `audit-post-seo` and `scan-seo-issues` abilities.
 * They lived inside ThatSeoAgent_Abilities until 1.16.0; the abilities, WP-CLI
 * and the admin now share this one implementation, so a score cannot differ
 * depending on who asked.
 *
 * @package ThatSeoAgent
 * @since 1.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Audit {

    /**
     * Audit one post.
     *
     * @since 1.16.0 Moved from ThatSeoAgent_Abilities::audit_post_seo().
     * @param WP_Post $post Post to audit.
     * @return array{post_id: int, title: string, url: string, score: int, issues: array, stats: array}
     */
    public static function post( WP_Post $post ) {
        // Rendered, not the raw serialization: the headings, images and links
        // of a block-built post only exist once the blocks have run.
        $content = ThatSeoAgent_Content::html( $post );
        $issues = array();
        $score = 100;

        // Get SEO meta
        $custom       = ThatSeoAgent_Post_Seo::all( $post );
        $custom_title = $custom['title'];
        $custom_desc  = $custom['description'];
        $title        = $custom_title ? $custom_title : get_the_title( $post );
        $description  = $custom_desc ? $custom_desc : ThatSeoAgent_Description::generate( $post );

        // Stats
        $title_length = mb_strlen( $title );
        $desc_length  = mb_strlen( $description );
        $word_count   = ThatSeoAgent_Content::word_count( $post );
        
        // Count headings
        preg_match_all( '/<h1[^>]*>/i', $content, $h1_matches );
        preg_match_all( '/<h2[^>]*>/i', $content, $h2_matches );
        $h1_count = count( $h1_matches[0] );
        $h2_count = count( $h2_matches[0] );

        // Count links
        $site_host = wp_parse_url( home_url(), PHP_URL_HOST );
        preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $link_matches );
        $internal_links = 0;
        $external_links = 0;
        foreach ( $link_matches[1] as $href ) {
            $link_host = wp_parse_url( $href, PHP_URL_HOST );
            if ( ! $link_host || $link_host === $site_host ) {
                $internal_links++;
            } else {
                $external_links++;
            }
        }

        // Count images and alt text
        $images             = self::image_alt_stats( $content );
        $image_count        = $images['total'];
        $images_without_alt = $images['missing'];

        // Check title length (ideal: 50-60)
        if ( $title_length < 30 ) {
            $issues[] = array(
                'type'     => 'title_short',
                'severity' => 'warning',
                'message'  => __( 'Title is too short (under 30 characters)', 'thatseoagent' ),
                'value'    => (string) $title_length . ' chars',
            );
            $score -= 10;
        } elseif ( $title_length > 60 ) {
            $issues[] = array(
                'type'     => 'title_long',
                'severity' => 'warning',
                'message'  => __( 'Title may be truncated in search results (over 60 characters)', 'thatseoagent' ),
                'value'    => (string) $title_length . ' chars',
            );
            $score -= 5;
        }

        // Check description length (ideal: 150-160)
        if ( $desc_length < 100 ) {
            $issues[] = array(
                'type'     => 'description_short',
                'severity' => 'warning',
                'message'  => __( 'Meta description is too short (under 100 characters)', 'thatseoagent' ),
                'value'    => (string) $desc_length . ' chars',
            );
            $score -= 10;
        } elseif ( $desc_length > 160 ) {
            $issues[] = array(
                'type'     => 'description_long',
                'severity' => 'info',
                'message'  => __( 'Meta description may be truncated (over 160 characters)', 'thatseoagent' ),
                'value'    => (string) $desc_length . ' chars',
            );
            $score -= 3;
        }

        // Check word count (thin content)
        if ( $word_count < 300 ) {
            $issues[] = array(
                'type'     => 'thin_content',
                'severity' => 'error',
                'message'  => __( 'Content is very thin (under 300 words)', 'thatseoagent' ),
                'value'    => (string) $word_count . ' words',
            );
            $score -= 20;
        } elseif ( $word_count < 800 ) {
            $issues[] = array(
                'type'     => 'short_content',
                'severity' => 'warning',
                'message'  => __( 'Content is relatively short (under 800 words)', 'thatseoagent' ),
                'value'    => (string) $word_count . ' words',
            );
            $score -= 10;
        }

        // Check H2 headings
        if ( $h2_count === 0 && $word_count > 300 ) {
            $issues[] = array(
                'type'     => 'no_h2',
                'severity' => 'warning',
                'message'  => __( 'No H2 headings found - consider adding structure', 'thatseoagent' ),
                'value'    => '0 H2 tags',
            );
            $score -= 10;
        }

        // Check internal links
        if ( $internal_links === 0 ) {
            $issues[] = array(
                'type'     => 'no_internal_links',
                'severity' => 'warning',
                'message'  => __( 'No internal links - consider linking to related content', 'thatseoagent' ),
                'value'    => '0 internal links',
            );
            $score -= 10;
        }

        // Check images
        if ( $image_count === 0 && $word_count > 300 ) {
            $issues[] = array(
                'type'     => 'no_images',
                'severity' => 'warning',
                'message'  => __( 'No images in content', 'thatseoagent' ),
                'value'    => '0 images',
            );
            $score -= 10;
        }

        // Check image alt text
        if ( $images_without_alt > 0 ) {
            $issues[] = array(
                'type'     => 'missing_alt_text',
                'severity' => 'warning',
                'message'  => __( 'Some images are missing alt text', 'thatseoagent' ),
                'value'    => (string) $images_without_alt . ' images without alt',
            );
            $score -= 5 * $images_without_alt;
        }

        // The featured image is printed by the theme, outside the content,
        // so the scan above never sees it.
        $thumbnail_id = get_post_thumbnail_id( $post );
        if ( $thumbnail_id && '' === trim( (string) get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) ) ) {
            $issues[] = array(
                'type'     => 'featured_image_missing_alt',
                'severity' => 'info',
                'message'  => __( 'The featured image has no alt text in the media library', 'thatseoagent' ),
                'value'    => (string) $thumbnail_id,
            );
        }

        // Catalog entries: what their Product markup is missing.
        foreach ( ThatSeoAgent_Product::validate( $post ) as $issue ) {
            $issues[] = $issue;
            $score   -= array( 'error' => 20, 'warning' => 5, 'info' => 0 )[ $issue['severity'] ];
        }

        $score = max( 0, $score );

        return array(
            'post_id' => $post->ID,
            'title'   => $post->post_title,
            'url'     => get_permalink( $post->ID ),
            'score'   => $score,
            'issues'  => $issues,
            'stats'   => array(
                'title_length'       => $title_length,
                'description_length' => $desc_length,
                'word_count'         => $word_count,
                'h1_count'           => $h1_count,
                'h2_count'           => $h2_count,
                'internal_links'     => $internal_links,
                'external_links'     => $external_links,
                'images'             => $image_count,
                'images_without_alt' => $images_without_alt,
                'images_decorative'  => $images['decorative'],
            ),
        );
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
