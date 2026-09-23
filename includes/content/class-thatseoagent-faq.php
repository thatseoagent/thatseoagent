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
 * @package ThatSeoAgent
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Turns post content into FAQ question/answer pairs.
 *
 * @since 1.9.0
 */
class ThatSeoAgent_FAQ {

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
     * 1. Real question headings and question-shaped Details blocks — always on.
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

        $sections = self::sections( ThatSeoAgent_Content::html( $post ) );

        // Question headings and Details blocks are both questions the visitor
        // can read on the page; together they make one FAQ.
        $pairs = self::merge_pairs( self::question_pairs( $sections ), self::details_pairs( $post ) );
        if ( count( $pairs ) >= self::MIN_PAIRS || empty( $sections ) ) {
            return $pairs;
        }

        /**
         * Enable the numbered/stepped FAQ extraction strategy.
         *
         * Off by default since 1.8.0: it turns statements into questions by
         * appending "?" ("Parrots Are Exceptionally Smart?"), so the
         * marked-up question is not the text a visitor sees. Re-enable with:
         *
         *     add_filter( 'thatseoagent_faq_numbered_enabled', '__return_true' );
         *
         * @since 1.8.0
         * @param bool    $enabled Whether the strategy runs. Default false.
         * @param WP_Post $post    Current post object.
         */
        if ( apply_filters( 'thatseoagent_faq_numbered_enabled', false, $post ) ) {
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
         *     add_filter( 'thatseoagent_faq_thematic_enabled', '__return_true' );
         *
         * @since 1.8.0
         * @param bool    $enabled Whether the strategy runs. Default false.
         * @param WP_Post $post    Current post object.
         */
        if ( apply_filters( 'thatseoagent_faq_thematic_enabled', false, $post ) ) {
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
     * @return array<int, ThatSeoAgent_FAQ_Section>
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

            $sections[] = new ThatSeoAgent_FAQ_Section(
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
     * @param array<int, ThatSeoAgent_FAQ_Section> $sections Sections.
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
     * Details blocks whose summary is a question.
     *
     * The core Details block is the editor's own accordion: a `<summary>` the
     * visitor clicks and an answer that opens beneath it. When the summary is
     * a question, that is an FAQ item written by hand — the most reliable
     * source there is, since nothing is inferred. A summary that is not a
     * question ("Show specifications") is an ordinary disclosure and is left
     * alone.
     *
     * The answer is rendered from the block's inner blocks, so dynamic blocks
     * inside it contribute their text.
     *
     * @since 1.16.0
     * @param WP_Post $post Post to read.
     * @return array<int, array{question: string, answer: string}>
     */
    private static function details_pairs( WP_Post $post ) {
        if ( ! has_block( 'core/details', $post ) ) {
            return array();
        }

        $pairs = array();

        foreach ( self::find_blocks( parse_blocks( $post->post_content ), 'core/details' ) as $block ) {
            $question = isset( $block['attrs']['summary'] ) ? (string) $block['attrs']['summary'] : '';

            // Older serializations keep the summary only in the saved markup.
            if ( '' === $question && preg_match( '/<summary[^>]*>(.*?)<\/summary>/is', (string) $block['innerHTML'], $m ) ) {
                $question = $m[1];
            }

            $question = trim( html_entity_decode( wp_strip_all_tags( $question ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

            if ( '' === $question || ! self::is_question_heading( $question ) ) {
                continue;
            }

            $answer_html = '';
            foreach ( $block['innerBlocks'] as $inner ) {
                $answer_html .= render_block( $inner );
            }

            $answer = self::clean_answer_text( $answer_html );
            if ( mb_strlen( $answer ) < self::MIN_ANSWER ) {
                continue;
            }

            $pairs[] = array(
                'question' => $question,
                'answer'   => $answer,
            );
        }

        return $pairs;
    }

    /**
     * Every block of a type, at any depth.
     *
     * @since 1.16.0
     * @param array  $blocks Parsed blocks.
     * @param string $name   Block name.
     * @return array<int, array>
     */
    private static function find_blocks( array $blocks, $name ) {
        $found = array();

        foreach ( $blocks as $block ) {
            if ( $name === $block['blockName'] ) {
                $found[] = $block;
                // A Details block inside another's answer is part of that
                // answer, not a question of its own.
                continue;
            }

            if ( ! empty( $block['innerBlocks'] ) ) {
                $found = array_merge( $found, self::find_blocks( $block['innerBlocks'], $name ) );
            }
        }

        return $found;
    }

    /**
     * Combine pair lists, dropping repeated questions.
     *
     * @since 1.16.0
     * @param array ...$lists Pair lists.
     * @return array<int, array{question: string, answer: string}>
     */
    private static function merge_pairs( ...$lists ) {
        $pairs = array();
        $seen  = array();

        foreach ( $lists as $list ) {
            foreach ( $list as $pair ) {
                $key = mb_strtolower( $pair['question'] );
                if ( isset( $seen[ $key ] ) ) {
                    continue;
                }

                $seen[ $key ] = true;
                $pairs[]      = $pair;
            }
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
     * @param array<int, ThatSeoAgent_FAQ_Section> $sections Sections.
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
     * @param array<int, ThatSeoAgent_FAQ_Section> $sections Sections.
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
        // "¿…?" opens with its own mark, so a question that runs on after the
        // closing "?" ("¿Qué incluye? Todo.") still reads as one.
        if ( '?' === mb_substr( $heading, -1 ) || '¿' === mb_substr( $heading, 0, 1 ) ) {
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
