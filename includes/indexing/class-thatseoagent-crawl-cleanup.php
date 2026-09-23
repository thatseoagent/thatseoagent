<?php
/**
 * What WordPress publishes that nobody needs to crawl.
 *
 * Always, with nothing to set up — no site needs them in its head, and they
 * tell crawlers and attackers more than they should:
 *
 *     the shortlink (?p=123), a second address for every post
 *     the RSD and WLW links, for desktop blogging clients of XML-RPC
 *     the generator meta tag, which publishes the WordPress version
 *     noindex on comment feeds, which repeat the posts' comments
 *
 * Optional, off by default, because a site may rely on them:
 *
 *     only the main feed    the other feeds' links are dropped, and a
 *                           request for one is sent to the page it follows
 *     filter spam searches  searches that are spam, not people looking for
 *                           something, go to the homepage
 *
 * Not here on purpose: moving `utm_*` parameters out of the URL, as Yoast
 * does. The redirect loses the campaign in Google Analytics, and the
 * canonical already names the URL without them.
 *
 * @package ThatSeoAgent
 * @since 2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Crawl_Cleanup {

    /**
     * Option holding the optional parts.
     */
    const OPTION_KEY = 'thatseoagent_crawl_cleanup';

    /**
     * Longest search kept; longer ones are treated as spam.
     */
    const SEARCH_MAX_LENGTH = 100;

    /**
     * The option as ThatSeoAgent_Settings registers it.
     *
     * @since 2.5.0
     * @return array{type: string, sanitize: callable, default: mixed, schema: array}
     */
    public static function setting() {
        return array(
            'type'     => 'object',
            'sanitize' => array( __CLASS__, 'sanitize' ),
            'default'  => array(
                'main_feed_only' => false,
                'search_spam'    => false,
            ),
            'schema'   => array(
                'type'                 => 'object',
                'additionalProperties' => false,
                'properties'           => array(
                    'main_feed_only' => array( 'type' => 'boolean' ),
                    'search_spam'    => array( 'type' => 'boolean' ),
                ),
            ),
        );
    }

    /**
     * Keep the two switches, as booleans.
     *
     * @since 2.5.0
     * @param mixed $input Raw value.
     * @return array{main_feed_only: bool, search_spam: bool}
     */
    public static function sanitize( $input ) {
        $input = is_array( $input ) ? $input : array();

        return array(
            'main_feed_only' => ! empty( $input['main_feed_only'] ) && rest_sanitize_boolean( $input['main_feed_only'] ),
            'search_spam'    => ! empty( $input['search_spam'] ) && rest_sanitize_boolean( $input['search_spam'] ),
        );
    }

    /**
     * The saved switches.
     *
     * @since 2.5.0
     * @return array{main_feed_only: bool, search_spam: bool}
     */
    public static function get_settings() {
        return self::sanitize( get_option( self::OPTION_KEY, array() ) );
    }

    /**
     * Register the settings section.
     *
     * Separate from register(): the section stays while another SEO plugin
     * is active, when the cleanup itself is its job.
     *
     * @since 2.5.0
     */
    public static function register_settings() {
        add_action( 'admin_init', array( __CLASS__, 'register_section' ) );
    }

    /**
     * Register the hooks.
     *
     * @since 2.5.0
     */
    public static function register() {
        remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
        remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
        remove_action( 'wp_head', 'rsd_link' );
        remove_action( 'wp_head', 'wlwmanifest_link' );
        remove_action( 'wp_head', 'wp_generator' );

        // Feeds print the generator too.
        add_filter( 'the_generator', '__return_empty_string' );

        add_action( 'template_redirect', array( __CLASS__, 'feeds' ), 1 );
        add_action( 'template_redirect', array( __CLASS__, 'search' ), 1 );

        if ( self::get_settings()['main_feed_only'] ) {
            add_filter( 'feed_links_show_comments_feed', '__return_false' );
            remove_action( 'wp_head', 'feed_links_extra', 3 );
        }
    }

    /**
     * Comment feeds out of search indexes; with "only the main feed", every
     * other feed sent to the page it follows.
     *
     * @since 2.5.0
     */
    public static function feeds() {
        if ( ! is_feed() ) {
            return;
        }

        if ( is_comment_feed() ) {
            header( 'X-Robots-Tag: noindex, follow' );
        }

        if ( ! self::get_settings()['main_feed_only'] ) {
            return;
        }

        $target = self::feed_page();
        if ( '' === $target ) {
            return;
        }

        wp_safe_redirect( $target, 301, 'ThatSeoAgent' );
        exit;
    }

    /**
     * The page a feed other than the main one follows, or '' for the main
     * feed.
     *
     * @since 2.5.0
     * @return string
     */
    private static function feed_page() {
        if ( is_singular() ) {
            // A post's comment feed.
            return (string) get_permalink( get_queried_object_id() );
        }

        if ( is_comment_feed() || is_search() ) {
            return home_url( '/' );
        }

        if ( is_category() || is_tag() || is_tax() ) {
            $link = get_term_link( get_queried_object() );
            return is_wp_error( $link ) ? home_url( '/' ) : $link;
        }

        if ( is_author() ) {
            return get_author_posts_url( get_queried_object_id() );
        }

        if ( is_post_type_archive() ) {
            $post_type = get_query_var( 'post_type' );
            $link      = get_post_type_archive_link( is_array( $post_type ) ? reset( $post_type ) : $post_type );
            return $link ? $link : home_url( '/' );
        }

        if ( is_date() ) {
            return home_url( '/' );
        }

        return '';
    }

    /**
     * Send spam searches to the homepage.
     *
     * Search results pages are already kept out of indexes; what this stops
     * is spammers linking to search URLs that print their text on the site,
     * and crawlers spending their visit on them.
     *
     * @since 2.5.0
     */
    public static function search() {
        if ( ! is_search() || ! self::get_settings()['search_spam'] ) {
            return;
        }

        if ( ! self::is_spam_search( (string) get_search_query( false ) ) ) {
            return;
        }

        wp_safe_redirect( home_url( '/' ), 301, 'ThatSeoAgent' );
        exit;
    }

    /**
     * Whether a search looks like spam rather than a person looking for
     * something.
     *
     * Emoji and other symbols — anything that is not a letter, a mark, a
     * number, a space or punctuation in any language, so "¿cómo?" and
     * "grúa" pass — the full-width punctuation spam campaigns use, a
     * messenger handle, or a length no one types.
     *
     * @since 2.5.0
     * @param string $search Search terms.
     * @return bool
     */
    public static function is_spam_search( $search ) {
        $search = trim( $search );

        if ( '' === $search ) {
            return false;
        }

        $spam = mb_strlen( $search ) > self::SEARCH_MAX_LENGTH
            || preg_match( '/[^\p{L}\p{M}\p{N}\p{Z}\p{P}\x00-\x7F]/u', $search )
            || preg_match( '/[：（）【】［］]/u', $search )
            || preg_match( '/\b(QQ|TALK|telegram|whatsapp|wechat)\s*[:：]/iu', $search );

        /**
         * Filter whether a search is treated as spam.
         *
         * @since 2.5.0
         * @param bool   $spam   Whether it is.
         * @param string $search Search terms.
         */
        return (bool) apply_filters( 'thatseoagent_spam_search', (bool) $spam, $search );
    }

    /**
     * Add the settings section.
     *
     * @since 2.5.0
     */
    public static function register_section() {
        add_settings_section(
            'thatseoagent_crawl_section',
            __( 'Crawl cleanup', 'thatseoagent' ),
            function () {
                echo '<p>' . esc_html__( 'Always done: the shortlink, the RSD and WLW links and the WordPress version are left out of every page, and comment feeds are kept out of search indexes. These two go further, and are off until you switch them on.', 'thatseoagent' ) . '</p>';
            },
            ThatSeoAgent_Settings::GROUP
        );

        add_settings_field(
            'thatseoagent_crawl_main_feed_only',
            __( 'Feeds', 'thatseoagent' ),
            array( __CLASS__, 'render_checkbox' ),
            ThatSeoAgent_Settings::GROUP,
            'thatseoagent_crawl_section',
            array(
                'key'         => 'main_feed_only',
                'label'       => __( 'Only the main feed', 'thatseoagent' ),
                'description' => __( 'Category, tag, author, search and comment feeds stop being announced, and a request for one goes to the page it follows. Leave off if people or services subscribe to them.', 'thatseoagent' ),
            )
        );

        add_settings_field(
            'thatseoagent_crawl_search_spam',
            __( 'Search', 'thatseoagent' ),
            array( __CLASS__, 'render_checkbox' ),
            ThatSeoAgent_Settings::GROUP,
            'thatseoagent_crawl_section',
            array(
                'key'         => 'search_spam',
                'label'       => __( 'Filter spam searches', 'thatseoagent' ),
                'description' => __( 'Searches with emoji, symbols, messenger handles ("QQ:", "Telegram:") or over 100 characters go to the homepage instead of printing the spammer\'s text on a results page.', 'thatseoagent' ),
            )
        );
    }

    /**
     * One switch.
     *
     * @since 2.5.0
     * @param array $args Field arguments.
     */
    public static function render_checkbox( $args ) {
        $name = self::OPTION_KEY . '[' . $args['key'] . ']';

        // The hidden field makes an unticked box submit "0" instead of
        // nothing, which would read as "leave unchanged".
        printf( '<input type="hidden" name="%s" value="0">', esc_attr( $name ) );
        printf(
            '<label><input type="checkbox" name="%s" value="1" %s> %s</label>',
            esc_attr( $name ),
            checked( self::get_settings()[ $args['key'] ], true, false ),
            esc_html( $args['label'] )
        );
        printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
    }
}
