<?php
/**
 * FAQ extraction.
 *
 * A post's content is read once into sections — a heading and the text that
 * follows it — and the extraction strategies are predicates over that list.
 *
 * Before 1.9.0 each strategy re-implemented the scaffolding inline: three
 * copies of the heading split, three copies of "the answer is the next part
 * unless it is a heading", and three copies of the 20/500 character
 * thresholds. Changing one threshold meant three edits, and the thematic
 * strategy had to re-derive the other two strategies' predicates — with the
 * numbered pattern written twice, in two different forms.
 *
 * @package Lean_SEO
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * One heading and the answer text beneath it.
 *
 * @since 1.9.0
 */
final class Lean_SEO_FAQ_Section {

    /**
     * Heading level: 2 or 3.
     *
     * @var int
     */
    public $level;

    /**
     * Heading text, tags stripped.
     *
     * @var string
     */
    public $heading;

    /**
     * Answer text, cleaned and truncated.
     *
     * @var string
     */
    public $answer;

    /**
     * @param int    $level   Heading level.
     * @param string $heading Heading text.
     * @param string $answer  Answer text.
     */
    public function __construct( $level, $heading, $answer ) {
        $this->level   = (int) $level;
        $this->heading = (string) $heading;
        $this->answer  = (string) $answer;
    }

    /**
     * Whether the answer is substantial enough to mark up.
     *
     * @return bool
     */
    public function has_answer() {
        return mb_strlen( $this->answer ) >= Lean_SEO_FAQ::MIN_ANSWER;
    }
}

/**
 * Turns post content into FAQ question/answer pairs.
 *
 * @since 1.9.0
 */
class Lean_SEO_FAQ {

    /**
     * Answers shorter than this are not a real Q&A.
     */
    const MIN_ANSWER = 20;

    /**
     * Google recommends concise FAQ answers; longer ones are truncated.
     */
    const MAX_ANSWER = 500;

    /**
     * Google requires at least this many items for FAQ rich results.
     */
    const MIN_PAIRS = 2;

    /**
     * Cap on pairs produced by the synthesising strategies.
     */
    const MAX_SYNTHESISED = 5;

    /**
     * Words that make a heading a question even without a question mark.
     *
     * @var string
     */
    private static $question_words = 'What|Why|How|When|Where|Can|Do|Is|Are|Does|Did|Will|Would|Should|Could';

    /**
     * Question/answer pairs for a post.
     *
     * Strategies run in order; the first to yield MIN_PAIRS wins.
     *
     * 1. Real question headings — always on.
     * 2. Numbered / stepped headings — opt-in, synthesises a question.
     * 3. Thematic headings on spiritual-meaning posts — opt-in, synthesises.
     *
     * @since 1.9.0
     * @param WP_Post $post Post to read.
     * @return array<int, array{question: string, answer: string}>
     */
    public static function pairs_for_post( $post ) {
        $post = get_post( $post );
        if ( ! $post ) {
            return array();
        }

        // Render blocks/shortcodes to get final HTML.
        $html = do_shortcode( do_blocks( $post->post_content ) );

        $sections = self::sections( $html );
        if ( empty( $sections ) ) {
            return array();
        }

        $pairs = self::question_pairs( $sections );
        if ( count( $pairs ) >= self::MIN_PAIRS ) {
            return $pairs;
        }

        /**
         * Enable the numbered/stepped FAQ extraction strategy.
         *
         * Off by default since 1.8.0: it turns statements into questions by
         * appending "?" ("Parrots Are Exceptionally Smart?"), so the
         * marked-up question is not the text a visitor sees. Re-enable with:
         *
         *     add_filter( 'lean_seo_faq_numbered_enabled', '__return_true' );
         *
         * @since 1.8.0
         * @param bool    $enabled Whether the strategy runs. Default false.
         * @param WP_Post $post    Current post object.
         */
        if ( apply_filters( 'lean_seo_faq_numbered_enabled', false, $post ) ) {
            $numbered = self::numbered_pairs( $sections, $post );
            if ( count( $numbered ) >= self::MIN_PAIRS ) {
                return $numbered;
            }
        }

        /**
         * Enable the thematic FAQ extraction strategy.
         *
         * Off by default since 1.8.0: it synthesises questions that appear
         * nowhere on the page ("What does fire mean spiritually in terms of
         * transformation?"), which conflicts with Google's requirement that
         * marked-up content be visible to the user. Re-enable with:
         *
         *     add_filter( 'lean_seo_faq_thematic_enabled', '__return_true' );
         *
         * @since 1.8.0
         * @param bool    $enabled Whether the strategy runs. Default false.
         * @param WP_Post $post    Current post object.
         */
        if ( apply_filters( 'lean_seo_faq_thematic_enabled', false, $post ) ) {
            $thematic = self::thematic_pairs( $sections, $post );
            if ( count( $thematic ) >= self::MIN_PAIRS ) {
                return $thematic;
            }
        }

        return $pairs;
    }

    /**
     * Read rendered HTML into sections.
     *
     * Splits on H2 and H3 and pairs each heading with the text that follows
     * it, stopping at the next heading of either level. Answers are cleaned
     * to plain text and truncated to MAX_ANSWER.
     *
     * @since 1.9.0
     * @param string $html Rendered HTML.
     * @return array<int, Lean_SEO_FAQ_Section>
     */
    public static function sections( $html ) {
        $parts = preg_split(
            '/(<h[23][^>]*>.*?<\/h[23]>)/is',
            (string) $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        if ( ! $parts ) {
            return array();
        }

        $sections = array();
        $count    = count( $parts );

        for ( $i = 0; $i < $count; $i++ ) {
            $part = trim( $parts[ $i ] );

            if ( ! preg_match( '/<h([23])[^>]*>(.*?)<\/h\1>/is', $part, $match ) ) {
                continue;
            }

            $heading = trim( wp_strip_all_tags( $match[2] ) );
            if ( '' === $heading ) {
                continue;
            }

            // The answer is the following part, unless that part is itself a
            // heading (an empty section).
            $answer_html = '';
            if ( isset( $parts[ $i + 1 ] ) ) {
                $next = trim( $parts[ $i + 1 ] );
                if ( ! preg_match( '/^<h[23][^>]*>/i', $next ) ) {
                    $answer_html = $next;
                }
            }

            $sections[] = new Lean_SEO_FAQ_Section(
                (int) $match[1],
                $heading,
                self::clean_answer_text( $answer_html )
            );
        }

        return $sections;
    }

    /**
     * Strategy 1 — headings that are already questions.
     *
     * @param array<int, Lean_SEO_FAQ_Section> $sections Sections.
     * @return array<int, array{question: string, answer: string}>
     */
    private static function question_pairs( $sections ) {
        $pairs = array();

        foreach ( $sections as $section ) {
            // An "FAQ" / "Frequently Asked" heading labels the section; it is
            // not itself a question.
            if ( preg_match( '/\b(FAQ|Frequently\s+Asked)/i', $section->heading ) ) {
                continue;
            }

            if ( ! self::is_question_heading( $section->heading ) || ! $section->has_answer() ) {
                continue;
            }

            $pairs[] = array(
                'question' => $section->heading,
                'answer'   => $section->answer,
            );
        }

        return $pairs;
    }

    /**
     * Strategy 2 — numbered or stepped H3 headings.
     *
     * "1. Parrots Are Exceptionally Smart" → "Parrots Are Exceptionally Smart?"
     * Leading "They/They're/Their" is replaced with the subject taken from a
     * "Facts About X" title.
     *
     * @param array<int, Lean_SEO_FAQ_Section> $sections Sections.
     * @param WP_Post                          $post     Post, for the subject.
     * @return array<int, array{question: string, answer: string}>
     */
    private static function numbered_pairs( $sections, $post ) {
        $subject = '';
        if ( preg_match( '/\bfacts about\s+(.+?)(?:\s*[\|\-–].*)?$/i', $post->post_title, $m ) ) {
            $subject = trim( $m[1] );
        }

        $pairs = array();

        foreach ( $sections as $section ) {
            if ( 3 !== $section->level || ! $section->has_answer() ) {
                continue;
            }

            $text = self::numbered_heading_text( $section->heading );
            if ( null === $text || mb_strlen( $text ) < 10 ) {
                continue;
            }

            if ( $subject ) {
                $text = preg_replace( '/^They\'re\s/i', $subject . ' are ', $text );
                $text = preg_replace( '/^They\'ve\s/i', $subject . ' have ', $text );
                $text = preg_replace( '/^They\s/i', $subject . ' ', $text );
                $text = preg_replace( '/^Their\s/i', $subject . '\'s ', $text );
            }

            $pairs[] = array(
                'question' => self::as_question( $text ),
                'answer'   => $section->answer,
            );

            if ( count( $pairs ) >= self::MAX_SYNTHESISED ) {
                break;
            }
        }

        return $pairs;
    }

    /**
     * Strategy 3 — thematic H3 headings on spiritual-meaning posts.
     *
     * @param array<int, Lean_SEO_FAQ_Section> $sections Sections.
     * @param WP_Post                          $post     Post, for the topic.
     * @return array<int, array{question: string, answer: string}>
     */
    private static function thematic_pairs( $sections, $post ) {
        if ( ! preg_match( '/\b(spiritual\s+meaning|symbolism|symbolize)\b/i', $post->post_title ) ) {
            return array();
        }

        $topic = self::topic_from_title( $post->post_title );
        if ( '' === $topic ) {
            return array();
        }

        // Headings that label a section rather than name a theme.
        $skip = '/^(conclusion|takeaway|final|summary|related|introduction|overview|quick|note|tip|warning|caution|read|faq|frequently)/i';

        $pairs = array();

        foreach ( $sections as $section ) {
            if ( 3 !== $section->level || ! $section->has_answer() ) {
                continue;
            }

            // Leave anything the earlier strategies would claim. Both
            // predicates are the shared ones, so they cannot drift apart.
            if ( self::is_question_heading( $section->heading ) ) {
                continue;
            }
            if ( null !== self::numbered_heading_text( $section->heading ) ) {
                continue;
            }

            if ( mb_strlen( $section->heading ) < 5 || preg_match( $skip, $section->heading ) ) {
                continue;
            }

            $pairs[] = array(
                'question' => 'What does ' . $topic . ' mean spiritually in terms of ' . lcfirst( $section->heading ) . '?',
                'answer'   => $section->answer,
            );

            if ( count( $pairs ) >= self::MAX_SYNTHESISED ) {
                break;
            }
        }

        return $pairs;
    }

    /**
     * Whether a heading is a question.
     *
     * True when it ends in "?" or opens with a question word.
     *
     * @since 1.9.0
     * @param string $heading Heading text.
     * @return bool
     */
    public static function is_question_heading( $heading ) {
        if ( '?' === mb_substr( $heading, -1 ) ) {
            return true;
        }

        return (bool) preg_match( '/^(' . self::$question_words . ')\s/i', $heading );
    }

    /**
     * The text of a numbered or stepped heading, without its prefix.
     *
     * Matches "1. Text", "1) Text", "Step 1: Text", "Step 1. Text". The one
     * definition of the numbered pattern — strategies 2 and 3 both use it.
     *
     * @since 1.9.0
     * @param string $heading Heading text.
     * @return string|null Text without the prefix, or null when not numbered.
     */
    public static function numbered_heading_text( $heading ) {
        if ( preg_match( '/^\d+[.)]\s+(.+)$/', $heading, $m ) ) {
            return trim( $m[1] );
        }

        if ( preg_match( '/^Step\s*\d+[.:]\s*(.+)$/i', $heading, $m ) ) {
            return trim( $m[1] );
        }

        return null;
    }

    /**
     * Extract the topic from a spiritual-meaning title.
     *
     * "The Spiritual Meaning of Fire" → "fire"
     * "Bird of Paradise Spiritual Meaning" → "Bird of Paradise"
     * "Crow Symbolism: What Does It Mean?" → "Crow"
     *
     * @param string $title Post title.
     * @return string Empty string when no topic is found.
     */
    private static function topic_from_title( $title ) {
        $topic = '';

        if ( preg_match( '/spiritual\s+meaning\s+of\s+(.+?)(?:\s*[\|\-–:].*)?$/i', $title, $m ) ) {
            $topic = trim( $m[1] );
        } elseif ( preg_match( '/^(.+?)\s+spiritual\s+meaning/i', $title, $m ) ) {
            $topic = trim( $m[1] );
        } elseif ( preg_match( '/^(.+?)\s+symbolism/i', $title, $m ) ) {
            $topic = trim( $m[1] );
        }

        if ( '' === $topic ) {
            return '';
        }

        // Strip leading articles for natural sentence flow.
        return preg_replace( '/^(a|an|the)\s+/i', '', $topic );
    }

    /**
     * Ensure a string reads as a question.
     *
     * @param string $text Text.
     * @return string
     */
    private static function as_question( $text ) {
        return '?' === mb_substr( $text, -1 ) ? $text : $text . '?';
    }

    /**
     * Clean answer HTML into plain text suitable for schema.
     *
     * @since 1.1.0
     * @param string $html Raw HTML answer content.
     * @return string
     */
    private static function clean_answer_text( $html ) {
        if ( '' === $html ) {
            return '';
        }

        // Images and figures carry nothing useful as schema text.
        $html = preg_replace( '/<figure[^>]*>.*?<\/figure>/is', '', $html );
        $html = preg_replace( '/<img[^>]*>/is', '', $html );

        // Keep list structure legible.
        $html = preg_replace( '/<li[^>]*>/i', '• ', $html );

        // Block ends become spaces so words do not run together.
        $html = preg_replace( '/<br\s*\/?>/i', ' ', $html );
        $html = preg_replace( '/<\/(p|div|li|ul|ol)>/i', ' ', $html );

        $text = wp_strip_all_tags( $html );
        $text = preg_replace( '/\s+/', ' ', $text );
        $text = trim( $text );

        if ( mb_strlen( $text ) > self::MAX_ANSWER ) {
            $text = mb_substr( $text, 0, self::MAX_ANSWER - 3 ) . '...';
        }

        return $text;
    }
}
