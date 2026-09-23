<?php
/**
 * One heading and the answer text beneath it.
 *
 * @package Lean_SEO
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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
