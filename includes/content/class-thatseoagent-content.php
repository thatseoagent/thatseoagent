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
 * @package ThatSeoAgent
 * @since 1.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Content {

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

        return ThatSeoAgent_Memo::remember(
            'content_html',
            $post->ID,
            function () use ( $post ) {
                return self::render( $post );
            }
        );
    }

    /**
     * Render a post's content, without memoisation.
     *
     * @since 1.20.0 Split from html().
     * @param WP_Post $post Post object.
     * @return string
     */
    private static function render( $post ) {
        $content = (string) $post->post_content;

        // Same order as core's `the_content`: blocks, or paragraphs for
        // classic content, then shortcodes. Without wpautop a classic post's
        // paragraphs are bare newlines, which the Markdown export collapses.
        if ( has_blocks( $content ) ) {
            $content = do_blocks( $content );
        } else {
            $content = shortcode_unautop( wpautop( $content ) );
        }

        return do_shortcode( $content );
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

        return ThatSeoAgent_Memo::remember(
            'content_text',
            $post->ID,
            function () use ( $post ) {
                return self::to_text( self::html( $post ) );
            }
        );
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
