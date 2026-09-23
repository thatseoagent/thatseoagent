<?php
/**
 * llms-full.txt: the full text of the pages llms.txt lists, in one file.
 *
 * The companion the llms.txt proposal describes: where llms.txt is an index
 * of links, llms-full.txt carries their content, so an agent reads the site
 * in one request instead of one per page. Like llms.txt, it is a convention
 * no AI provider has committed to reading and not a ranking signal.
 *
 *     # Site name
 *
 *     > What the site is.
 *
 *     ---
 *
 *     # Page title
 *
 *     Source: https://example.com/page
 *
 *     The page's content, as Markdown…
 *
 * Only pages with a Markdown version are included, in llms.txt's order and
 * from the same per-post Markdown cache the `.md` URLs use. The frontmatter
 * of each is left out: the title and the source URL are what a reader of one
 * long file needs to tell the pages apart.
 *
 * Served with llms.txt, under the same switch. The file is cached until a
 * post or the site identity changes, like llms.txt.
 *
 * @package ThatSeoAgent
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Llms_Full {

    /**
     * Transient holding the generated file.
     */
    const CACHE_KEY = 'thatseoagent_llms_full_txt';

    /**
     * Register the hooks.
     *
     * The cache is dropped by ThatSeoAgent_Llms::purge(), on the same events
     * as llms.txt's.
     *
     * @since 2.3.0
     */
    public static function register() {
        // Priority 20: before ThatSeoAgent::maybe_flush_rewrite_rules() at 21.
        add_action( 'init', array( __CLASS__, 'register_routes' ), 20 );
        add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
        add_action( 'parse_request', array( __CLASS__, 'handle_request' ) );
    }

    /**
     * Add the rewrite rule.
     *
     * @since 2.3.0
     */
    public static function register_routes() {
        add_rewrite_rule( '^llms-full\.txt$', 'index.php?thatseoagent_llms_full=1', 'top' );
    }

    /**
     * Add the query var.
     *
     * @since 2.3.0
     * @param array $vars Query vars.
     * @return array
     */
    public static function query_vars( $vars ) {
        $vars[] = 'thatseoagent_llms_full';
        return $vars;
    }

    /**
     * The file's URL.
     *
     * @since 2.3.0
     * @return string
     */
    public static function url() {
        return home_url( '/llms-full.txt' );
    }

    /**
     * Answer a request for /llms-full.txt.
     *
     * A physical llms-full.txt in the site root is served by the web server
     * before WordPress runs, so it always wins over this one.
     *
     * @since 2.3.0
     * @param WP $wp Current WordPress environment.
     */
    public static function handle_request( $wp ) {
        if ( empty( $wp->query_vars['thatseoagent_llms_full'] ) ) {
            return;
        }

        if ( ! ThatSeoAgent_Llms::is_enabled() ) {
            status_header( 404 );
            nocache_headers();
            header( 'Content-Type: text/plain; charset=utf-8' );
            echo esc_html__( 'Not found.', 'thatseoagent' );
            exit;
        }

        $body = self::cached();

        status_header( 200 );
        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'X-Robots-Tag: noindex' );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text Markdown built from the posts' own rendered content.
        echo $body;
        exit;
    }

    /**
     * The file, from the cache or built and cached.
     *
     * @since 2.3.0
     * @return string
     */
    public static function cached() {
        $body = get_transient( self::CACHE_KEY );

        if ( ! is_string( $body ) ) {
            $body = self::build();
            set_transient( self::CACHE_KEY, $body, DAY_IN_SECONDS );
        }

        return $body;
    }

    /**
     * Drop the cached file.
     *
     * @since 2.3.0
     */
    public static function purge() {
        delete_transient( self::CACHE_KEY );
    }

    /**
     * Generate the file.
     *
     * Stops before the size limit rather than cutting a page in half, and
     * says so at the end: an agent reading a truncated file should know
     * there is more, and where to find it.
     *
     * @since 2.3.0
     * @return string
     */
    public static function build() {
        $identity = ThatSeoAgent_Identity::get_settings();
        $summary  = $identity['description'] ? $identity['description'] : get_bloginfo( 'description' );
        $header   = '# ' . self::line( get_bloginfo( 'name' ) ) . "\n\n";

        if ( $summary ) {
            $header .= '> ' . self::line( $summary ) . "\n\n";
        }

        /**
         * Maximum size of llms-full.txt, in bytes.
         *
         * Pages past it are left out, and the file ends saying how many.
         *
         * @since 2.3.0
         * @param int $max_bytes Default 2 MB.
         */
        $max_bytes = max( 1024, (int) apply_filters( 'thatseoagent_llms_full_max_bytes', 2 * MB_IN_BYTES ) );

        $body           = $header;
        $left           = 0;
        $limit          = ThatSeoAgent_Llms::limit();
        $markdown_types = ThatSeoAgent_Markdown_Endpoint::post_types();

        foreach ( ThatSeoAgent_Llms::post_types() as $post_type ) {
            if ( ! in_array( $post_type, $markdown_types, true ) ) {
                continue;
            }

            foreach ( ThatSeoAgent_Llms::posts( $post_type, $limit ) as $post ) {
                if ( '' === ThatSeoAgent_Markdown_Endpoint::url_for( $post ) ) {
                    continue;
                }

                if ( $left ) {
                    $left++;
                    continue;
                }

                $section = self::section( $post );
                ThatSeoAgent_Memo::forget_post( $post );

                if ( strlen( $body ) + strlen( $section ) > $max_bytes ) {
                    $left = 1;
                    continue;
                }

                $body .= $section;
            }
        }

        if ( $left ) {
            $body .= "---\n\n" . sprintf(
                /* translators: 1: number of pages, 2: llms.txt URL. */
                _n(
                    '%1$d more page did not fit in this file. It is listed in %2$s, with a link to its Markdown version.',
                    '%1$d more pages did not fit in this file. They are listed in %2$s, each with a link to its Markdown version.',
                    $left,
                    'thatseoagent'
                ),
                $left,
                home_url( '/llms.txt' )
            ) . "\n";
        }

        /**
         * Filter the generated llms-full.txt.
         *
         * @since 2.3.0
         * @param string $body File contents.
         */
        return (string) apply_filters( 'thatseoagent_llms_full_txt', $body );
    }

    /**
     * One page: title, source and content, without the frontmatter.
     *
     * @since 2.3.0
     * @param WP_Post $post Post.
     * @return string
     */
    private static function section( WP_Post $post ) {
        $markdown = ThatSeoAgent_Markdown_Endpoint::markdown( $post );
        $content  = trim( (string) preg_replace( '/\A---\R.*?\R---\R/s', '', $markdown ) );

        return "---\n\n"
            . '# ' . self::line( get_the_title( $post ) ) . "\n\n"
            . 'Source: ' . get_permalink( $post ) . "\n\n"
            . ( '' !== $content ? $content . "\n\n" : '' );
    }

    /**
     * Text reduced to one plain line.
     *
     * @since 2.3.0
     * @param string $text Text.
     * @return string
     */
    private static function line( $text ) {
        $text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        return trim( preg_replace( '/\s+/u', ' ', $text ) );
    }
}
