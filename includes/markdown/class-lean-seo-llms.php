<?php
/**
 * llms.txt: an index of the site for language models.
 *
 * A proposed convention (https://llmstxt.org), not an adopted standard: no
 * major AI provider has committed to reading it, and it is not a ranking
 * signal. It is cheap to publish, though, and it pairs with the Markdown
 * endpoint — each entry links to the page's `.md` version where there is one,
 * which is the version an agent should read.
 *
 * The file is generated, not edited: it lists what the site publishes, in the
 * structure the proposal describes —
 *
 *     # Site name
 *
 *     > What the site is.
 *
 *     ## Pages
 *
 *     - [Title](https://example.com/page.md): Description.
 *
 *     ## Optional
 *
 *     - [Sitemap](https://example.com/sitemap.xml)
 *
 * It is cached until something it lists changes.
 *
 * @package Lean_SEO
 * @since 1.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lean_SEO_Llms {

    /**
     * Option switching the file on or off.
     */
    const OPTION_KEY = 'lean_seo_llms_txt';

    /**
     * Transient holding the generated file.
     */
    const CACHE_KEY = 'lean_seo_llms_txt';

    /**
     * Register the hooks.
     *
     * @since 1.16.0
     */
    public static function register() {
        // Priority 20: before Lean_SEO::maybe_flush_rewrite_rules() at 21.
        add_action( 'init', array( __CLASS__, 'register_routes' ), 20 );
        add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
        add_action( 'parse_request', array( __CLASS__, 'handle_request' ) );

        add_action( 'save_post', array( __CLASS__, 'purge' ) );
        add_action( 'deleted_post', array( __CLASS__, 'purge' ) );
        foreach ( array( 'blogname', 'blogdescription', 'lean_seo_identity', 'lean_seo_products', self::OPTION_KEY ) as $option ) {
            add_action( 'update_option_' . $option, array( __CLASS__, 'purge' ) );
        }
    }

    /**
     * Register the settings section.
     *
     * Separate from register(): the section stays available while another
     * SEO plugin is active, when the file itself is not served.
     *
     * @since 1.19.0
     */
    public static function register_settings() {
        add_action( 'admin_init', array( __CLASS__, 'register_section' ) );
    }

    /**
     * Whether the site publishes the file.
     *
     * @since 1.16.0
     * @return bool
     */
    public static function is_enabled() {
        return (bool) get_option( self::OPTION_KEY, true );
    }

    /**
     * Add the rewrite rule.
     *
     * @since 1.16.0
     */
    public static function register_routes() {
        add_rewrite_rule( '^llms\.txt$', 'index.php?lean_llms=1', 'top' );
    }

    /**
     * Add the query var.
     *
     * @since 1.16.0
     * @param array $vars Query vars.
     * @return array
     */
    public static function query_vars( $vars ) {
        $vars[] = 'lean_llms';
        return $vars;
    }

    /**
     * Answer a request for /llms.txt.
     *
     * A physical llms.txt in the site root is served by the web server before
     * WordPress runs, so it always wins over this one.
     *
     * @since 1.16.0
     * @param WP $wp Current WordPress environment.
     */
    public static function handle_request( $wp ) {
        if ( empty( $wp->query_vars['lean_llms'] ) ) {
            return;
        }

        if ( ! self::is_enabled() ) {
            status_header( 404 );
            nocache_headers();
            header( 'Content-Type: text/plain; charset=utf-8' );
            echo esc_html__( 'Not found.', 'lean-seo' );
            exit;
        }

        $body = get_transient( self::CACHE_KEY );
        if ( ! is_string( $body ) ) {
            $body = self::build();
            set_transient( self::CACHE_KEY, $body, DAY_IN_SECONDS );
        }

        status_header( 200 );
        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'X-Robots-Tag: noindex' );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text built from titles and descriptions already stripped of markup.
        echo $body;
        exit;
    }

    /**
     * Drop the cached file.
     *
     * Every save clears it — cheaper than working out whether the saved post
     * is one the file lists, and the rebuild happens only when the file is
     * next requested.
     *
     * @since 1.16.0
     */
    public static function purge() {
        delete_transient( self::CACHE_KEY );
    }

    /**
     * Counts and section names of a generated file, for the screen.
     *
     * @since 1.18.0
     * @param string $body File contents.
     * @return array{body: string, entries: int, sections: array<int, string>}
     */
    public static function describe( $body ) {
        $lines = explode( "\n", (string) $body );

        return array(
            'body'     => (string) $body,
            'entries'  => count( preg_grep( '/^- \[/', $lines ) ),
            'sections' => array_values(
                array_map(
                    function ( $line ) {
                        return substr( $line, 3 );
                    },
                    preg_grep( '/^## /', $lines )
                )
            ),
        );
    }

    /**
     * Post types the file lists.
     *
     * The ones served as Markdown, plus product catalogs.
     *
     * @since 1.16.0
     * @return array<int, string>
     */
    public static function post_types() {
        $post_types = array_unique( array_merge( Lean_SEO_Markdown_Endpoint::post_types(), Lean_SEO_Product::post_types() ) );
        $post_types = array_values( array_filter( $post_types, 'is_post_type_viewable' ) );

        /**
         * Filter the post types listed in llms.txt.
         *
         * @since 1.16.0
         * @param array<int, string> $post_types Post types.
         */
        return (array) apply_filters( 'lean_seo_llms_txt_post_types', $post_types );
    }

    /**
     * Generate the file.
     *
     * @since 1.16.0
     * @return string
     */
    public static function build() {
        $lines = array( '# ' . self::line( get_bloginfo( 'name' ) ), '' );

        $identity = Lean_SEO_Identity::get_settings();
        $summary  = $identity['description'] ? $identity['description'] : get_bloginfo( 'description' );
        if ( $summary ) {
            $lines[] = '> ' . self::line( $summary );
            $lines[] = '';
        }

        /**
         * Maximum entries listed per post type.
         *
         * Pages come in menu order, everything else newest first.
         *
         * @since 1.16.0
         * @param int $limit Default 100.
         */
        $limit = max( 1, (int) apply_filters( 'lean_seo_llms_txt_limit', 100 ) );

        foreach ( self::post_types() as $post_type ) {
            $entries = self::entries( $post_type, $limit );
            if ( ! $entries ) {
                continue;
            }

            $object  = get_post_type_object( $post_type );
            $lines[] = '## ' . self::line( $object ? $object->labels->name : $post_type );
            $lines[] = '';
            $lines   = array_merge( $lines, $entries );
            $lines[] = '';
        }

        if ( Lean_SEO_Compat::outputs_enabled() ) {
            $lines[] = '## Optional';
            $lines[] = '';
            $lines[] = sprintf( '- [%s](%s)', __( 'Sitemap', 'lean-seo' ), home_url( '/sitemap.xml' ) );
            $lines[] = '';
        }

        /**
         * Filter the generated llms.txt.
         *
         * @since 1.16.0
         * @param string $body File contents.
         */
        return (string) apply_filters( 'lean_seo_llms_txt', implode( "\n", $lines ) );
    }

    /**
     * The list entries of one post type.
     *
     * @since 1.16.0
     * @param string $post_type Post type.
     * @param int    $limit     Maximum entries.
     * @return array<int, string>
     */
    private static function entries( $post_type, $limit ) {
        $hierarchical = is_post_type_hierarchical( $post_type );

        $posts = get_posts(
            array(
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'has_password'   => false,
                // The blog index page has no content of its own; its posts are
                // listed under their own heading.
                'post__not_in'   => array_filter( array( (int) get_option( 'page_for_posts' ) ) ),
                'posts_per_page' => $limit,
                'orderby'        => $hierarchical ? array( 'menu_order' => 'ASC', 'title' => 'ASC' ) : 'date',
                'order'          => 'DESC',
                'no_found_rows'  => true,
            )
        );

        $entries = array();

        foreach ( $posts as $post ) {
            $markdown = Lean_SEO_Markdown_Endpoint::url_for( $post );
            $url      = $markdown ? $markdown : get_permalink( $post );
            $title    = self::line( get_the_title( $post ) );

            $entry       = sprintf( '- [%s](%s)', str_replace( array( '[', ']' ), array( '(', ')' ), $title ), $url );
            $description = self::line( Lean_SEO_Description::for_post( $post ) );

            if ( '' !== $description ) {
                $entry .= ': ' . $description;
            }

            $entries[] = $entry;

            Lean_SEO_Content::forget( $post );
        }

        return $entries;
    }

    /**
     * Text reduced to one plain line.
     *
     * @since 1.16.0
     * @param string $text Text.
     * @return string
     */
    private static function line( $text ) {
        $text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        return trim( preg_replace( '/\s+/u', ' ', $text ) );
    }

    /**
     * Add the settings section.
     *
     * @since 1.16.0
     */
    public static function register_section() {
        add_settings_section(
            'lean_seo_llms_section',
            __( 'AI index', 'lean-seo' ),
            function () {
                echo '<p>' . esc_html__( 'A plain-text index of the site for AI assistants, linking to the Markdown version of each page. It is a proposed convention, not a ranking factor: publishing it costs nothing, but no AI provider has committed to reading it.', 'lean-seo' ) . '</p>';
            },
            Lean_SEO_Settings::GROUP
        );

        add_settings_field(
            self::OPTION_KEY,
            __( 'llms.txt', 'lean-seo' ),
            array( __CLASS__, 'render_field' ),
            Lean_SEO_Settings::GROUP,
            'lean_seo_llms_section'
        );
    }

    /**
     * Render the on/off checkbox.
     *
     * @since 1.16.0
     */
    public static function render_field() {
        // The hidden field makes an unticked box submit "0" instead of
        // nothing, which options.php would read as "leave unchanged".
        printf( '<input type="hidden" name="%s" value="0">', esc_attr( self::OPTION_KEY ) );
        printf(
            '<label><input type="checkbox" name="%s" value="1" %s> %s</label>',
            esc_attr( self::OPTION_KEY ),
            checked( self::is_enabled(), true, false ),
            esc_html__( 'Publish the list at /llms.txt', 'lean-seo' )
        );

        if ( self::is_enabled() && get_option( 'permalink_structure' ) ) {
            printf(
                ' — <a href="%s" target="_blank" rel="noopener">%s</a>',
                esc_url( home_url( '/llms.txt' ) ),
                esc_html__( 'View', 'lean-seo' )
            );
        }
    }
}
