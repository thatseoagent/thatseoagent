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
 * @package ThatSeoAgent
 * @since 1.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Llms {

    /**
     * Option switching the file on or off.
     */
    const OPTION_KEY = 'thatseoagent_llms_txt';

    /**
     * Transient holding the generated file.
     */
    const CACHE_KEY = 'thatseoagent_llms_txt';

    /**
     * The option as ThatSeoAgent_Settings registers it: type, sanitizer,
     * default and REST schema.
     *
     * @since 1.20.0 Moved from ThatSeoAgent_Settings::definitions().
     * @return array{type: string, sanitize: callable, default: mixed, schema: array}
     */
    public static function setting() {
        return array(
            'type'     => 'boolean',
            'sanitize' => 'rest_sanitize_boolean',
            'default'  => true,
            'schema'   => array( 'type' => 'boolean' ),
        );
    }

    /**
     * Register the hooks.
     *
     * @since 1.16.0
     */
    public static function register() {
        // Priority 20: before ThatSeoAgent::maybe_flush_rewrite_rules() at 21.
        add_action( 'init', array( __CLASS__, 'register_routes' ), 20 );
        add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
        add_action( 'parse_request', array( __CLASS__, 'handle_request' ) );

        add_action( 'save_post', array( __CLASS__, 'purge' ) );
        add_action( 'deleted_post', array( __CLASS__, 'purge' ) );
        foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) {
            add_action( $hook, array( __CLASS__, 'purge_for_meta' ), 10, 3 );
        }
        foreach ( array( 'blogname', 'blogdescription', 'thatseoagent_identity', 'thatseoagent_products', self::OPTION_KEY ) as $option ) {
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
        add_rewrite_rule( '^llms\.txt$', 'index.php?thatseoagent_llms=1', 'top' );
    }

    /**
     * Add the query var.
     *
     * @since 1.16.0
     * @param array $vars Query vars.
     * @return array
     */
    public static function query_vars( $vars ) {
        $vars[] = 'thatseoagent_llms';
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
        if ( empty( $wp->query_vars['thatseoagent_llms'] ) ) {
            return;
        }

        if ( ! self::is_enabled() ) {
            status_header( 404 );
            nocache_headers();
            header( 'Content-Type: text/plain; charset=utf-8' );
            echo esc_html__( 'Not found.', 'thatseoagent' );
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

        // llms-full.txt lists the same pages, and their content.
        ThatSeoAgent_Llms_Full::purge();
    }

    /**
     * Drop the cached file when a post's SEO fields change.
     *
     * The REST API and the abilities write the fields after save_post has
     * fired, and the file lists each post's description and leaves out the
     * posts kept out of search.
     *
     * @since 2.2.0
     * @param int|int[] $meta_id   Meta ID, or IDs when deleted.
     * @param int       $object_id Post ID.
     * @param string    $meta_key  Meta key.
     */
    public static function purge_for_meta( $meta_id, $object_id, $meta_key ) {
        if ( in_array( $meta_key, ThatSeoAgent_Post_Seo::keys(), true ) ) {
            self::purge();
        }
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
        $post_types = array_unique( array_merge( ThatSeoAgent_Markdown_Endpoint::post_types(), ThatSeoAgent_Product::post_types() ) );
        $post_types = array_values( array_filter( $post_types, 'is_post_type_viewable' ) );

        /**
         * Filter the post types listed in llms.txt.
         *
         * @since 1.16.0
         * @param array<int, string> $post_types Post types.
         */
        return (array) apply_filters( 'thatseoagent_llms_txt_post_types', $post_types );
    }

    /**
     * Generate the file.
     *
     * @since 1.16.0
     * @return string
     */
    public static function build() {
        $lines = array( '# ' . self::line( get_bloginfo( 'name' ) ), '' );

        $identity = ThatSeoAgent_Identity::get_settings();
        $summary  = $identity['description'] ? $identity['description'] : get_bloginfo( 'description' );
        if ( $summary ) {
            $lines[] = '> ' . self::line( $summary );
            $lines[] = '';
        }

        // Who the site is and how to reach it, before anything else: the
        // first thing an agent needs to answer about a site.
        $about = self::about_entries();
        if ( $about['entries'] ) {
            $lines[] = '## ' . __( 'About the site', 'thatseoagent' );
            $lines[] = '';
            $lines   = array_merge( $lines, $about['entries'] );
            $lines[] = '';
        }

        $limit          = self::limit();
        $catalogs       = ThatSeoAgent_Product::post_types();
        $catalog_linked = false;

        foreach ( self::post_types() as $post_type ) {
            $is_catalog = in_array( $post_type, $catalogs, true );
            $entries    = self::entries( $post_type, $is_catalog ? self::catalog_limit() : $limit, $about['ids'] );
            if ( ! $entries ) {
                continue;
            }

            // A catalog lists its newest products; the rest are one link
            // away, all of them, in the catalog file.
            if ( $is_catalog && ThatSeoAgent_Catalog_Feed::is_published() ) {
                $total = self::count_listed( $post_type );
                if ( $total > count( $entries ) ) {
                    $entries[] = sprintf(
                        '- [%s](%s): %s',
                        /* translators: %d: number of products. */
                        sprintf( __( 'All %d products', 'thatseoagent' ), $total ),
                        ThatSeoAgent_Catalog_Feed::url(),
                        __( 'every product as schema.org JSON, one per line', 'thatseoagent' )
                    );
                    $catalog_linked = true;
                }
            }

            $object  = get_post_type_object( $post_type );
            $lines[] = '## ' . self::line( $object ? $object->labels->name : $post_type );
            $lines[] = '';
            $lines   = array_merge( $lines, $entries );
            $lines[] = '';
        }

        if ( ThatSeoAgent_Compat::outputs_enabled() ) {
            $lines[] = '## Optional';
            $lines[] = '';
            $lines[] = sprintf( '- [%s](%s): %s', __( 'Full text', 'thatseoagent' ), ThatSeoAgent_Llms_Full::url(), __( 'every page above with a Markdown version, in one file', 'thatseoagent' ) );
            if ( ThatSeoAgent_Catalog_Feed::is_published() && ! $catalog_linked ) {
                $lines[] = sprintf( '- [%s](%s): %s', __( 'Product catalog', 'thatseoagent' ), ThatSeoAgent_Catalog_Feed::url(), __( 'every product as schema.org JSON, one per line', 'thatseoagent' ) );
            }
            $lines[] = sprintf( '- [%s](%s)', __( 'Sitemap', 'thatseoagent' ), home_url( '/sitemap.xml' ) );
            $lines[] = '';
        }

        /**
         * Filter the generated llms.txt.
         *
         * @since 1.16.0
         * @param string $body File contents.
         */
        return (string) apply_filters( 'thatseoagent_llms_txt', implode( "\n", $lines ) );
    }

    /**
     * The list entries of one post type.
     *
     * @since 1.16.0
     * @param string          $post_type Post type.
     * @param int             $limit     Maximum entries.
     * @param array<int, int> $exclude   Post IDs to leave out.
     * @return array<int, string>
     */
    private static function entries( $post_type, $limit, array $exclude = array() ) {
        $entries = array();

        foreach ( self::posts( $post_type, $limit, $exclude ) as $post ) {
            $entries[] = self::entry( $post );

            ThatSeoAgent_Memo::forget_post( $post );
        }

        return $entries;
    }

    /**
     * One post as a list entry: its title, linking to its Markdown version
     * where there is one, and its description.
     *
     * @since 2.5.0 Split from entries().
     * @param WP_Post $post Post.
     * @return string
     */
    private static function entry( WP_Post $post ) {
        $markdown = ThatSeoAgent_Markdown_Endpoint::url_for( $post );
        $url      = $markdown ? $markdown : get_permalink( $post );
        $title    = self::line( get_the_title( $post ) );

        $entry       = sprintf( '- [%s](%s)', str_replace( array( '[', ']' ), array( '(', ')' ), $title ), $url );
        $description = self::line( ThatSeoAgent_Description::for_post( $post ) );

        if ( '' !== $description ) {
            $entry .= ': ' . $description;
        }

        return $entry;
    }

    /**
     * The site's trust pages — about, contact, privacy — as list entries,
     * and the IDs of the ones that are posts, so they are not listed twice.
     *
     * A trust page found as a menu link to another site, or a mailto: for
     * contact, is listed with its name.
     *
     * @since 2.5.0
     * @return array{entries: array<int, string>, ids: array<int, int>}
     */
    private static function about_entries() {
        $labels  = ThatSeoAgent_Trust_Pages::labels();
        $entries = array();
        $ids     = array();

        foreach ( ThatSeoAgent_Trust_Pages::found() as $kind => $url ) {
            if ( '' === $url ) {
                continue;
            }

            $post_id = 0 === strpos( $url, 'mailto:' ) ? 0 : url_to_postid( $url );
            $post    = $post_id ? get_post( $post_id ) : null;

            if ( $post && 'publish' === $post->post_status && ! post_password_required( $post ) && ! ThatSeoAgent_Indexing::is_post_noindex( $post ) ) {
                $entries[] = self::entry( $post );
                $ids[]     = (int) $post->ID;
            } elseif ( ! $post ) {
                $entries[] = sprintf( '- [%s](%s)', $labels[ $kind ], esc_url_raw( $url, array( 'http', 'https', 'mailto' ) ) );
            }
        }

        return array(
            'entries' => $entries,
            'ids'     => $ids,
        );
    }

    /**
     * Products listed per catalog.
     *
     * @since 2.5.0
     * @return int
     */
    public static function catalog_limit() {
        /**
         * Maximum products listed per catalog in llms.txt, newest first.
         *
         * The whole catalog is in /catalog.jsonl, which the list links to
         * when it holds more.
         *
         * @since 2.5.0
         * @param int $limit Default 20.
         */
        return max( 1, (int) apply_filters( 'thatseoagent_llms_txt_catalog_limit', 20 ) );
    }

    /**
     * How many posts of a type could be listed.
     *
     * @since 2.5.0
     * @param string $post_type Post type.
     * @return int
     */
    private static function count_listed( $post_type ) {
        $query = new WP_Query(
            array_merge(
                array(
                    'post_type'              => $post_type,
                    'post_status'            => 'publish',
                    'posts_per_page'         => 1,
                    'fields'                 => 'ids',
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                ),
                ThatSeoAgent_Indexing::listed_query_args()
            )
        );

        return (int) $query->found_posts;
    }

    /**
     * Maximum entries per post type.
     *
     * @since 2.3.0 Split from build().
     * @return int
     */
    public static function limit() {
        /**
         * Maximum entries listed per post type, in llms.txt and llms-full.txt.
         *
         * Pages come in menu order, everything else newest first.
         *
         * @since 1.16.0
         * @param int $limit Default 100.
         */
        return max( 1, (int) apply_filters( 'thatseoagent_llms_txt_limit', 100 ) );
    }

    /**
     * The posts of one type the file lists, in its order.
     *
     * Pages in menu order, everything else newest first. llms-full.txt reads
     * the same list, so the two files never disagree about what the site
     * offers.
     *
     * @since 2.3.0 Split from entries().
     * @since 2.5.0 $exclude.
     * @param string          $post_type Post type.
     * @param int             $limit     Maximum posts.
     * @param array<int, int> $exclude   Post IDs to leave out.
     * @return array<int, WP_Post>
     */
    public static function posts( $post_type, $limit, array $exclude = array() ) {
        $hierarchical = is_post_type_hierarchical( $post_type );

        return get_posts(
            array_merge(
                array(
                    'post_type'      => $post_type,
                    'post_status'    => 'publish',
                    // The blog index page has no content of its own; its posts are
                    // listed under their own heading.
                    'post__not_in'   => array_values( array_filter( array_merge( array( (int) get_option( 'page_for_posts' ) ), array_map( 'intval', $exclude ) ) ) ),
                    'posts_per_page' => $limit,
                    'orderby'        => $hierarchical ? array( 'menu_order' => 'ASC', 'title' => 'ASC' ) : 'date',
                    'order'          => 'DESC',
                    'no_found_rows'  => true,
                ),
                // No password-protected posts, and none kept out of search.
                ThatSeoAgent_Indexing::listed_query_args()
            )
        );
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
            'thatseoagent_llms_section',
            __( 'AI index', 'thatseoagent' ),
            function () {
                echo '<p>' . esc_html__( 'A plain-text index of the site for AI assistants, linking to the Markdown version of each page. It is a proposed convention, not a ranking factor: publishing it costs nothing, but no AI provider has committed to reading it.', 'thatseoagent' ) . '</p>';
            },
            ThatSeoAgent_Settings::GROUP
        );

        add_settings_field(
            self::OPTION_KEY,
            __( 'llms.txt', 'thatseoagent' ),
            array( __CLASS__, 'render_field' ),
            ThatSeoAgent_Settings::GROUP,
            'thatseoagent_llms_section'
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
            esc_html__( 'Publish the list at /llms.txt', 'thatseoagent' )
        );

        if ( self::is_enabled() && get_option( 'permalink_structure' ) ) {
            printf(
                ' — <a href="%s" target="_blank" rel="noopener">%s</a>',
                esc_url( home_url( '/llms.txt' ) ),
                esc_html__( 'View', 'thatseoagent' )
            );
        }
    }
}
