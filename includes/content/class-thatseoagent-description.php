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
 * Since 2.7.0 that answer includes the homepage's own description and the
 * thatseoagent_description filter, which until then reached the head alone.
 *
 * The interface takes a post. It does not read the loop, which is what makes
 * it callable from wp_head, from WP-CLI, from an ability and from the meta
 * box alike — and testable without building a WP_Query.
 *
 * @package ThatSeoAgent
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Description {

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
     * On the homepage, the homepage's own description first. Then the
     * post's SEO description; otherwise one generated from the content —
     * except on the blog's posts page, whose content is never shown, and
     * which says what the site says. The homepage falls back to the tagline
     * too. The `thatseoagent_description` filter has the last word, so the
     * head, the markup, the Markdown version, llms.txt and the editor's
     * preview all publish the same one.
     *
     * @since 1.9.0
     * @since 2.7.0 The homepage's description, the posts page's, and the
     *              thatseoagent_description filter.
     * @param WP_Post|int $post    Post object or ID.
     * @param string|null $written The SEO description to assume, '' for
     *                             none; null reads the post's own. The
     *                             editor's preview passes '' to show what an
     *                             empty field publishes.
     * @return string Empty string when the post has no usable content.
     */
    public static function for_post( $post, $written = null ) {
        $post = get_post( $post );
        if ( ! $post ) {
            return '';
        }

        $front       = ThatSeoAgent_Homepage::is_front_page( $post );
        $posts_page  = ThatSeoAgent_Homepage::is_posts_page( $post );
        $written     = null === $written ? ThatSeoAgent_Post_Seo::get( $post, 'description' ) : (string) $written;
        $description = $front ? ThatSeoAgent_Homepage::expand_variables( ThatSeoAgent_Homepage::get_settings()['description'] ) : '';

        if ( '' === $description ) {
            $description = $written;
        }

        if ( '' === $description && ! $posts_page ) {
            $description = self::generate( $post );
        }

        if ( '' === $description && ( $front || $posts_page ) ) {
            $description = (string) get_bloginfo( 'description' );
        }

        /**
         * Filter the resolved meta description.
         *
         * Runs after the default resolution and receives a context string
         * so callers can branch on page type without duplicating
         * conditional logic.
         *
         * @since 1.5.0
         * @since 2.7.0 Runs for a post wherever its description is used, not
         *              only in the head, and receives the post.
         * @param string       $description Resolved default description.
         * @param string       $context     Current page context (see ThatSeoAgent_Meta::get_context()).
         * @param WP_Post|null $post        The post, null on a listing.
         */
        return (string) apply_filters( 'thatseoagent_description', $description, $front || $posts_page ? 'home' : 'single', $post );
    }

    /**
     * Generate a description from the post's own content.
     *
     * Ignores any stored `_thatseoagent_description`, so callers that are about
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

        // Memoised per post: rendering blocks is the expensive part, and the
        // meta tags and the JSON-LD graph each ask for the description
        // separately during one request.
        return ThatSeoAgent_Memo::remember(
            'description',
            $post->ID,
            function () use ( $post ) {
                return self::build( $post );
            }
        );
    }

    /**
     * Build the description for a post, without memoisation.
     *
     * @since 1.12.3
     * @param WP_Post $post Post object.
     * @return string
     */
    private static function build( $post ) {
        if ( ! empty( $post->post_excerpt ) ) {
            return self::truncate( ThatSeoAgent_Content::to_text( $post->post_excerpt ) );
        }

        $text = ThatSeoAgent_Content::text( $post );
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
        $patterns = apply_filters( 'thatseoagent_description_filler_patterns', $patterns, $sentence );

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
