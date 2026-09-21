<?php
/**
 * Meta description resolution.
 *
 * The single answer to "what description does this post get?". Before 1.9.0
 * four modules answered it differently: the front end trimmed to 30 words,
 * the abilities re-typed that same logic, WP-CLI extracted whole sentences
 * capped at 155 characters, and the editor's Google preview trimmed to 25
 * words without stripping shortcodes — so the preview showed a description
 * the front end never emitted.
 *
 * The interface takes a post. It does not read the loop, which is what makes
 * it callable from wp_head, from WP-CLI, from an ability and from the meta
 * box alike — and testable without building a WP_Query.
 *
 * @package Lean_SEO
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lean_SEO_Description {

    /**
     * Maximum description length, in characters.
     *
     * Search engines truncate around 155–160; we compose to 155 so the
     * result is never cut mid-word by someone else.
     */
    const MAX_LENGTH = 155;

    /**
     * Sentences shorter than this are treated as filler and skipped.
     */
    const MIN_SENTENCE = 40;

    /**
     * The description this post actually gets.
     *
     * Custom meta wins; otherwise one is generated from the content.
     *
     * @since 1.9.0
     * @param WP_Post|int $post Post object or ID.
     * @return string Empty string when the post has no usable content.
     */
    public static function for_post( $post ) {
        $post = get_post( $post );
        if ( ! $post ) {
            return '';
        }

        $custom = Lean_SEO_Post_Seo::get( $post, 'description' );
        if ( $custom ) {
            return $custom;
        }

        return self::generate( $post );
    }

    /**
     * Generate a description from the post's own content.
     *
     * Ignores any stored `_lean_seo_description`, so callers that are about
     * to *write* one (WP-CLI) get a fresh suggestion rather than an echo of
     * what is already saved.
     *
     * Strategy: excerpt if the post has one, otherwise the first substantive
     * sentences of the content — skipping intro filler — composed up to
     * MAX_LENGTH and cut at a word boundary.
     *
     * @since 1.9.0
     * @param WP_Post|int $post Post object or ID.
     * @return string
     */
    public static function generate( $post ) {
        $post = get_post( $post );
        if ( ! $post ) {
            return '';
        }

        if ( ! empty( $post->post_excerpt ) ) {
            return self::truncate( self::to_text( $post->post_excerpt ) );
        }

        $text = self::to_text( $post->post_content );
        if ( mb_strlen( $text ) < 20 ) {
            return '';
        }

        $sentences = self::split_sentences( $text );
        if ( empty( $sentences ) ) {
            return self::truncate( $text );
        }

        $description = '';

        foreach ( $sentences as $sentence ) {
            $sentence = trim( $sentence );

            if ( mb_strlen( $sentence ) < self::MIN_SENTENCE || self::is_filler( $sentence ) ) {
                continue;
            }

            if ( '' === $description ) {
                if ( mb_strlen( $sentence ) <= self::MAX_LENGTH ) {
                    $description = $sentence;
                    continue;
                }

                // A single sentence longer than the cap: truncate and stop.
                return self::truncate( $sentence );
            }

            $combined = $description . ' ' . $sentence;
            if ( mb_strlen( $combined ) > self::MAX_LENGTH ) {
                break;
            }

            $description = $combined;
        }

        // Every sentence was filler or too short — fall back to raw text.
        if ( '' === $description ) {
            $description = self::truncate( $text );
        }

        return $description;
    }

    /**
     * Convert post content to plain text.
     *
     * @since 1.9.0
     * @param string $content Raw content.
     * @return string
     */
    public static function to_text( $content ) {
        // Block delimiters first: they are comments, so stripping tags later
        // would leave their contents behind.
        $text = preg_replace( '/<!--\s*\/?wp:\S.*?-->/s', '', (string) $content );
        $text = strip_shortcodes( $text );
        $text = wp_strip_all_tags( $text );
        $text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $text = preg_replace( '/\s+/', ' ', $text );

        return trim( $text );
    }

    /**
     * Split text into sentences.
     *
     * Splits on sentence-ending punctuation followed by whitespace and the
     * start of a new sentence. The lookahead accepts any Unicode uppercase
     * letter (so "Él", "Ángel" split correctly) plus the Spanish opening
     * marks ¿ and ¡ and an opening quote. Abbreviations like "Dr." and
     * decimals are left intact because neither is followed by whitespace
     * plus a capital.
     *
     * @since 1.9.0
     * @param string $text Plain text.
     * @return array<int, string>
     */
    public static function split_sentences( $text ) {
        $parts = preg_split(
            '/(?<=[.!?])\s+(?=[\p{Lu}"\x{201C}\x{00BF}\x{00A1}])/u',
            $text,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        return $parts ? $parts : array();
    }

    /**
     * Whether a sentence is throat-clearing rather than substance.
     *
     * @since 1.9.0
     * @param string $sentence Sentence to test.
     * @return bool
     */
    public static function is_filler( $sentence ) {
        $patterns = array(
            '/^(have you ever|if you\'ve ever|you might|you may|you\'ve probably)/i',
            '/^(imagine|picture this|let\'s|let us|we\'ve all)/i',
            '/^(in this (article|post|guide|blog))/i',
            '/^(today,?\s+(we|I|we\'re|I\'m)\s+(will|are|\'re|\'m)\s+(going to|gonna|exploring|looking|diving))/i',
            '/let\'s (dive|get|jump|explore|find out|take a look)/i',
            '/here\'s (the thing|what|the deal)/i',
            '/ever wonder(ed)?/i',
            '/^(hey|hello|hi|greetings|welcome)\b/i',
            '/^(do you|are you|does the|have you)\b.*\?\s*$/i',
            '/^(so,?\s+you\'ve|well,?\s+you\'ve|okay,?\s+so)/i',
            '/^(ever (watched|found|noticed|seen|looked))\b/i',
        );

        /**
         * Filter the intro-filler patterns.
         *
         * Each entry is a full preg_match pattern tested against one
         * sentence. Site-specific phrases ("<author> here") belong here
         * rather than compiled into the plugin.
         *
         * @since 1.9.0
         * @param array<int, string> $patterns Regex patterns.
         * @param string             $sentence Sentence under test.
         */
        $patterns = apply_filters( 'lean_seo_description_filler_patterns', $patterns, $sentence );

        foreach ( (array) $patterns as $pattern ) {
            if ( preg_match( $pattern, $sentence ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Truncate to MAX_LENGTH at a word boundary.
     *
     * @since 1.9.0
     * @param string   $text      Text to truncate.
     * @param int|null $max_chars Override for the default cap.
     * @return string
     */
    public static function truncate( $text, $max_chars = null ) {
        $max_chars = null === $max_chars ? self::MAX_LENGTH : (int) $max_chars;
        $text      = trim( (string) $text );

        if ( mb_strlen( $text ) <= $max_chars ) {
            return $text;
        }

        $cut        = mb_substr( $text, 0, $max_chars - 3 );
        $last_space = mb_strrpos( $cut, ' ' );

        if ( false !== $last_space && $last_space > $max_chars * 0.5 ) {
            $cut = mb_substr( $cut, 0, $last_space );
        }

        return $cut . '...';
    }
}
