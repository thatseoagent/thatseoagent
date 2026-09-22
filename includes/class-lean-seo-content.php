<?php
/**
 * A post's content, rendered.
 *
 * The single answer to "what is this post's content?". Since Gutenberg,
 * `post_content` is not the content — it is a serialization of it. A dynamic
 * or self-closing block keeps its text inside the delimiter's own attributes:
 *
 *     <!-- wp:acme/section {"content":"The actual words."} /-->
 *
 * Reading `post_content` raw therefore sees none of it. Before 1.13.0 three
 * modules read it three different ways: the FAQ extractor rendered blocks, the
 * description extractor stripped the delimiters without rendering, and the SEO
 * audit ran regexes straight over the serialization — so on a block-built page
 * the audit reported no headings, no images and "thin content" for a page with
 * two thousand characters of text.
 *
 * Rendering is memoised per post: it runs each block's render callback, which
 * is the expensive part, and several callers ask during a single request.
 *
 * @package Lean_SEO
 * @since 1.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lean_SEO_Content {

    /**
     * Rendered HTML for a post.
     *
     * Blocks and shortcodes are expanded, so headings, images and links are
     * actually present.
     *
     * @since 1.13.0
     * @param WP_Post|int $post Post object or ID.
     * @return string
     */
    public static function html( $post ) {
        $post = get_post( $post );
        if ( ! $post ) {
            return '';
        }

        static $cache = array();

        if ( isset( $cache[ $post->ID ] ) ) {
            return $cache[ $post->ID ];
        }

        $content = (string) $post->post_content;

        if ( has_blocks( $content ) ) {
            $content = do_blocks( $content );
        }

        $cache[ $post->ID ] = do_shortcode( $content );

        return $cache[ $post->ID ];
    }

    /**
     * Plain text for a post.
     *
     * @since 1.13.0
     * @param WP_Post|int $post Post object or ID.
     * @return string
     */
    public static function text( $post ) {
        $post = get_post( $post );
        if ( ! $post ) {
            return '';
        }

        static $cache = array();

        if ( isset( $cache[ $post->ID ] ) ) {
            return $cache[ $post->ID ];
        }

        $cache[ $post->ID ] = self::to_text( self::html( $post ) );

        return $cache[ $post->ID ];
    }

    /**
     * Reduce HTML to normalized plain text.
     *
     * Also accepts a raw string, so callers with a fragment (an excerpt, a
     * single answer) can share the same normalization.
     *
     * @since 1.13.0
     * @param string $html HTML or text.
     * @return string
     */
    public static function to_text( $html ) {
        // Leftover delimiters from static blocks are comments, so stripping
        // tags would otherwise leave their attributes behind.
        $text = preg_replace( '/<!--\s*\/?wp:\S.*?-->/s', '', (string) $html );
        $text = strip_shortcodes( $text );
        $text = wp_strip_all_tags( $text );
        $text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $text = preg_replace( '/\s+/', ' ', $text );

        return trim( $text );
    }

    /**
     * Word count for a post.
     *
     * Counts runs of Unicode letters and digits: `str_word_count()` is
     * ASCII-only and miscounts accented languages.
     *
     * @since 1.13.0
     * @param WP_Post|int $post Post object or ID.
     * @return int
     */
    public static function word_count( $post ) {
        return (int) preg_match_all( '/[\p{L}\p{N}]+/u', self::text( $post ) );
    }
}
