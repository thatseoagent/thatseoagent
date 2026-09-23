<?php
/**
 * How a post is built: its structure, as facts.
 *
 * What an agent reviewing the site needs to form its own view of a page —
 * the outline, the lists and tables, the figures, how it opens — without
 * the plugin passing judgement on any of it. No AI provider publishes how it
 * chooses what to cite, so no score here pretends to measure that: whoever
 * reads these facts decides what they mean, and reads the Markdown version
 * for the rest.
 *
 * @package ThatSeoAgent
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Structure {

    /**
     * Words of the opening returned.
     */
    const OPENING_WORDS = 150;

    /**
     * Headings returned at most.
     */
    const MAX_HEADINGS = 60;

    /**
     * The structure of a post.
     *
     * @since 2.3.0
     * @param WP_Post $post Post.
     * @return array{declared_language: string, published: string, modified: string, markdown_url: string, words: int, paragraphs: int, headings: array, lists: array, tables: array, blockquotes: int, details: int, code_blocks: int, numbers: int, percentages: int, opening: string}
     */
    public static function of( WP_Post $post ) {
        $facts = self::walk( ThatSeoAgent_Content::html( $post ) );
        $text  = ThatSeoAgent_Content::text( $post );

        preg_match_all( '/[\\p{L}\\p{N}]+/u', $text, $words );

        return array_merge(
            array(
                // What the site declares in <html lang>, from its settings:
                // not detected from the text, which may be in another one.
                'declared_language' => get_bloginfo( 'language' ),
                'published'         => (string) get_post_time( 'c', true, $post ),
                'modified'          => (string) get_post_modified_time( 'c', true, $post ),
                'markdown_url'      => ThatSeoAgent_Markdown_Endpoint::url_for( $post ),
                'words'             => count( $words[0] ),
            ),
            $facts,
            array(
                'numbers'     => (int) preg_match_all( '/(?<![\\p{L}\\p{N}])\\d+(?:[.,]\\d+)*/u', $text ),
                'percentages' => (int) preg_match_all( '/\\d+(?:[.,]\\d+)?\\s?%/u', $text ),
                'opening'     => self::opening( $text ),
            )
        );
    }

    /**
     * Walk the rendered HTML once, counting what it is built from.
     *
     * @since 2.3.0
     * @param string $html Rendered content.
     * @return array{paragraphs: int, headings: array<int, array{level: int, text: string}>, lists: array{unordered: int, ordered: int, items: int}, tables: array{count: int, rows: int}, blockquotes: int, details: int, code_blocks: int}
     */
    private static function walk( $html ) {
        $facts = array(
            'paragraphs'  => 0,
            'headings'    => array(),
            'lists'       => array(
                'unordered' => 0,
                'ordered'   => 0,
                'items'     => 0,
            ),
            'tables'      => array(
                'count' => 0,
                'rows'  => 0,
            ),
            'blockquotes' => 0,
            'details'     => 0,
            'code_blocks' => 0,
        );

        $tokens  = new WP_HTML_Tag_Processor( (string) $html );
        $heading = null;

        while ( $tokens->next_token() ) {
            $type = $tokens->get_token_type();

            if ( '#text' === $type ) {
                if ( null !== $heading ) {
                    $heading['text'] .= $tokens->get_modifiable_text();
                }
                continue;
            }

            if ( '#tag' !== $type ) {
                continue;
            }

            $tag    = $tokens->get_tag();
            $closer = $tokens->is_tag_closer();

            if ( preg_match( '/^H([1-6])$/', $tag, $match ) ) {
                if ( ! $closer ) {
                    $heading = array(
                        'level' => (int) $match[1],
                        'text'  => '',
                    );
                } elseif ( null !== $heading ) {
                    $heading['text'] = trim( preg_replace( '/\\s+/u', ' ', html_entity_decode( $heading['text'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
                    if ( count( $facts['headings'] ) < self::MAX_HEADINGS ) {
                        $facts['headings'][] = $heading;
                    }
                    $heading = null;
                }
                continue;
            }

            if ( $closer ) {
                continue;
            }

            switch ( $tag ) {
                case 'P':
                    $facts['paragraphs']++;
                    break;
                case 'UL':
                    $facts['lists']['unordered']++;
                    break;
                case 'OL':
                    $facts['lists']['ordered']++;
                    break;
                case 'LI':
                    $facts['lists']['items']++;
                    break;
                case 'TABLE':
                    $facts['tables']['count']++;
                    break;
                case 'TR':
                    $facts['tables']['rows']++;
                    break;
                case 'BLOCKQUOTE':
                    $facts['blockquotes']++;
                    break;
                case 'DETAILS':
                    $facts['details']++;
                    break;
                case 'PRE':
                    $facts['code_blocks']++;
                    break;
            }
        }

        return $facts;
    }

    /**
     * The first words of the text, as a reader meets them.
     *
     * @since 2.3.0
     * @param string $text Plain text.
     * @return string
     */
    private static function opening( $text ) {
        $words = preg_split( '/\\s+/u', trim( (string) $text ), self::OPENING_WORDS + 1 );

        if ( count( $words ) > self::OPENING_WORDS ) {
            array_pop( $words );
            return implode( ' ', $words ) . ' …';
        }

        return implode( ' ', $words );
    }
}
