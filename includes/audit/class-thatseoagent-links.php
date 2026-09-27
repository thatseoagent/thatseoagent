<?php
/**
 * How the site's pages link to each other: which ones nothing links to,
 * and which links lead nowhere.
 *
 * Read from three places, because links live in all three:
 *
 *     the content of every searchable page
 *     the menus and Navigation blocks
 *     the homepage as the server renders it — the header and footer the
 *     theme prints on every page, menus hard-coded in templates included
 *
 * An internal address that is no page, term or listing the site knows is
 * asked for with a HEAD request to the site itself: a 404 or 410 is a
 * broken link. Only so many are asked per build (PROBE_LIMIT); the rest are
 * reported as not measured, never as fine.
 *
 * Built on demand — when a content check starts, or an agent audits a
 * page — and kept for an hour, or until a post or a menu changes.
 *
 * @package ThatSeoAgent
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Links {

    /**
     * Transient holding the last graph.
     */
    const CACHE_KEY = 'thatseoagent_link_graph';

    /**
     * Unknown addresses asked for at most, per build.
     */
    const PROBE_LIMIT = 40;

    /**
     * Posts whose content is read per query.
     */
    const BATCH = 200;

    /**
     * Register the hooks that make the graph stale.
     *
     * @since 2.3.0
     */
    public static function register() {
        foreach ( array( 'save_post', 'deleted_post', 'wp_update_nav_menu', 'switch_theme' ) as $hook ) {
            add_action( $hook, array( __CLASS__, 'purge' ) );
        }
    }

    /**
     * Drop the cached graph.
     *
     * @since 2.3.0
     */
    public static function purge() {
        delete_transient( self::CACHE_KEY );
    }

    /**
     * The graph, from the cache or built and cached.
     *
     * @since 2.3.0
     * @return array{built: int, inbound: array<int, int>, navigation: array<int, bool>, broken: array<int, array<int, string>>, unchecked: array<int, int>, navigation_broken: array<int, string>, navigation_unchecked: int}
     */
    public static function graph() {
        return ThatSeoAgent_Memo::remember(
            'link_graph',
            'site',
            function () {
                $graph = get_transient( self::CACHE_KEY );

                if ( ! is_array( $graph ) ) {
                    $graph = self::build();
                    set_transient( self::CACHE_KEY, $graph, HOUR_IN_SECONDS );
                }

                return $graph;
            }
        );
    }

    /**
     * The cached graph, or null when none has been built lately. Never
     * builds one: for callers that must stay cheap, like the bulletin.
     *
     * @since 2.3.0
     * @return array|null
     */
    public static function cached() {
        $graph = get_transient( self::CACHE_KEY );

        return is_array( $graph ) ? $graph : null;
    }

    /**
     * The link report the get-link-report ability returns: the pages
     * nothing links to, the links to addresses that do not exist, and the
     * header, footer or menu links that lead nowhere.
     *
     * Reads the graph from the last hour's cache unless $fresh, which reads
     * the whole site again (it requests the front page and checks up to 40
     * unknown addresses).
     *
     * @since 2.9.0
     * @param bool $fresh Read the site's links again.
     * @return array{built: string, orphans: array<int, array>, broken: array<int, array>, navigation_broken: array<int, string>, unchecked: int}
     */
    public static function report( $fresh = false ) {
        if ( $fresh ) {
            self::purge();
            ThatSeoAgent_Memo::forget( 'link_graph' );
        }

        $graph    = self::graph();
        $describe = function ( WP_Post $post ) {
            return array(
                'post_id' => $post->ID,
                'type'    => $post->post_type,
                'title'   => get_the_title( $post ),
                'url'     => get_permalink( $post ),
            );
        };

        $orphans = array();
        $posts   = get_posts( array(
            'post_type'              => ThatSeoAgent_Post_Seo::post_types(),
            'post_status'            => 'publish',
            'has_password'           => false,
            'numberposts'            => -1,
            'update_post_term_cache' => false,
        ) );

        foreach ( $posts as $post ) {
            if ( self::needs_links( $post )
                && ! ThatSeoAgent_Indexing::is_post_noindex( $post )
                && empty( $graph['inbound'][ $post->ID ] )
                && empty( $graph['navigation'][ $post->ID ] )
            ) {
                $orphans[] = $describe( $post );
            }
        }

        $broken = array();
        foreach ( (array) $graph['broken'] as $post_id => $urls ) {
            $post = get_post( $post_id );
            if ( $post ) {
                $broken[] = $describe( $post ) + array( 'links' => array_values( (array) $urls ) );
            }
        }

        return array(
            'built'             => wp_date( 'Y-m-d H:i:s', (int) $graph['built'] ),
            'orphans'           => $orphans,
            'broken'            => $broken,
            'navigation_broken' => array_values( (array) ( $graph['navigation_broken'] ?? array() ) ),
            'unchecked'         => (int) array_sum( (array) $graph['unchecked'] ) + (int) ( $graph['navigation_unchecked'] ?? 0 ),
        );
    }

    /**
     * Whether a post can only be reached through links to it.
     *
     * Posts of a type with an archive, or with a public taxonomy, are
     * listed on pages WordPress builds (the blog, a category, the product
     * archive), so something always links to them. Pages, and types with
     * neither, are reached through links or not at all.
     *
     * @since 2.3.0
     * @param WP_Post $post Post.
     * @return bool
     */
    public static function needs_links( WP_Post $post ) {
        if ( (int) get_option( 'page_on_front' ) === $post->ID || (int) get_option( 'page_for_posts' ) === $post->ID ) {
            return false;
        }

        $type = get_post_type_object( $post->post_type );
        if ( ! $type || 'post' === $post->post_type || ! empty( $type->has_archive ) ) {
            return false;
        }

        foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $taxonomy ) {
            if ( $taxonomy->public && $taxonomy->publicly_queryable ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Build the graph.
     *
     * @since 2.3.0
     * @return array See graph().
     */
    private static function build() {
        $known   = self::known_urls();
        $pages   = $known['pages'];
        $graph   = array(
            'built'                => time(),
            'inbound'              => array(),
            'navigation'           => array(),
            'broken'               => array(),
            'unchecked'            => array(),
            'navigation_broken'    => array(),
            'navigation_unchecked' => 0,
        );
        $unknown = array(); // URL => array( 'sources' => post IDs, 'navigation' => bool ).

        // Content: who links to whom.
        $sources = array();
        foreach ( self::contents() as $id => $content ) {
            foreach ( self::internal_links( $content ) as $url ) {
                if ( isset( $pages[ $url ] ) ) {
                    if ( $pages[ $url ] !== $id ) {
                        $sources[ $pages[ $url ] ][ $id ] = true;
                    }
                } elseif ( ! isset( $known['other'][ $url ] ) ) {
                    $unknown[ $url ]['sources'][] = $id;
                }
            }
        }

        foreach ( $sources as $target => $from ) {
            $graph['inbound'][ $target ] = count( $from );
        }

        // Navigation: menus, Navigation blocks and the rendered homepage.
        foreach ( self::navigation_links() as $url ) {
            if ( isset( $pages[ $url ] ) ) {
                $graph['navigation'][ $pages[ $url ] ] = true;
            } elseif ( ! isset( $known['other'][ $url ] ) ) {
                $unknown[ $url ]['navigation'] = true;
            }
        }

        // Unknown addresses: ask the site.
        $statuses = self::probe( array_slice( array_keys( $unknown ), 0, self::PROBE_LIMIT ) );

        foreach ( $unknown as $url => $where ) {
            $status  = array_key_exists( $url, $statuses ) ? $statuses[ $url ] : null;
            $sources = isset( $where['sources'] ) ? array_unique( $where['sources'] ) : array();

            if ( null === $status ) {
                foreach ( $sources as $id ) {
                    $graph['unchecked'][ $id ] = ( isset( $graph['unchecked'][ $id ] ) ? $graph['unchecked'][ $id ] : 0 ) + 1;
                }
                if ( ! empty( $where['navigation'] ) ) {
                    $graph['navigation_unchecked']++;
                }
                continue;
            }

            if ( 404 !== $status && 410 !== $status ) {
                continue;
            }

            foreach ( $sources as $id ) {
                $graph['broken'][ $id ][] = $url;
            }
            if ( ! empty( $where['navigation'] ) ) {
                $graph['navigation_broken'][] = $url;
            }
        }

        return $graph;
    }

    /**
     * Every address the site is known to answer, normalized.
     *
     * `pages` maps each searchable post's address to its ID; `other` holds
     * the listings WordPress builds — home, blog, archives, terms.
     *
     * @since 2.3.0
     * @return array{pages: array<string, int>, other: array<string, bool>}
     */
    private static function known_urls() {
        $pages = array();
        foreach ( self::searchable_ids() as $id ) {
            $pages[ self::normalize( (string) get_permalink( $id ) ) ] = $id;
        }

        $other = array( self::normalize( home_url( '/' ) ) => true );

        $posts_page = (int) get_option( 'page_for_posts' );
        if ( $posts_page ) {
            $other[ self::normalize( (string) get_permalink( $posts_page ) ) ] = true;
        }

        foreach ( ThatSeoAgent_Post_Seo::post_types() as $post_type ) {
            $archive = get_post_type_archive_link( $post_type );
            if ( $archive ) {
                $other[ self::normalize( $archive ) ] = true;
            }
        }

        $taxonomies = get_taxonomies(
            array(
                'public'             => true,
                'publicly_queryable' => true,
            )
        );
        $terms      = get_terms(
            array(
                'taxonomy'   => array_values( $taxonomies ),
                'hide_empty' => false,
            )
        );
        foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
            $link = get_term_link( $term );
            if ( ! is_wp_error( $link ) ) {
                $other[ self::normalize( $link ) ] = true;
            }
        }

        return array(
            'pages' => $pages,
            'other' => $other,
        );
    }

    /**
     * IDs of the posts that can appear in search results.
     *
     * @since 2.3.0
     * @return array<int, int>
     */
    private static function searchable_ids() {
        return array_map(
            'intval',
            get_posts(
                array_merge(
                    array(
                        'post_type'      => ThatSeoAgent_Post_Seo::post_types(),
                        'post_status'    => 'publish',
                        'posts_per_page' => -1,
                        'fields'         => 'ids',
                        'no_found_rows'  => true,
                    ),
                    ThatSeoAgent_Indexing::listed_query_args()
                )
            )
        );
    }

    /**
     * The saved content of every searchable post, a batch at a time.
     *
     * The stored markup, not the rendered page: rendering every post would
     * cost far more, and links a block builds on render (a query loop, a
     * latest-posts list) point at listed posts, which need no links.
     *
     * @since 2.3.0
     * @return Generator<int, string> Post ID => content.
     */
    private static function contents() {
        global $wpdb;

        foreach ( array_chunk( self::searchable_ids(), self::BATCH ) as $ids ) {
            $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            // Only the ID and the content, a batch at a time: get_posts()
            // would load every column of every post into memory at once.
            $rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_content FROM {$wpdb->posts} WHERE ID IN ($placeholders)", $ids ) );
            // phpcs:enable

            foreach ( (array) $rows as $row ) {
                yield (int) $row->ID => (string) $row->post_content;
            }
        }
    }

    /**
     * Links in the site's navigation: menus, Navigation blocks, and the
     * homepage as rendered.
     *
     * @since 2.3.0
     * @return array<int, string> Normalized internal URLs.
     */
    private static function navigation_links() {
        $links = array();

        foreach ( wp_get_nav_menus() as $menu ) {
            foreach ( (array) wp_get_nav_menu_items( $menu ) as $item ) {
                if ( ! empty( $item->url ) ) {
                    $links = array_merge( $links, self::internal_links( '<a href="' . esc_attr( $item->url ) . '">' ) );
                }
            }
        }

        $navigations = get_posts(
            array(
                'post_type'      => 'wp_navigation',
                'post_status'    => 'publish',
                'posts_per_page' => 20,
                'no_found_rows'  => true,
            )
        );
        foreach ( $navigations as $navigation ) {
            // Navigation Link blocks keep the address in their attributes.
            preg_match_all( '/"url":"([^"]+)"/', $navigation->post_content, $urls );
            foreach ( $urls[1] as $url ) {
                $links = array_merge( $links, self::internal_links( '<a href="' . esc_attr( stripslashes( $url ) ) . '">' ) );
            }
        }

        $home = ThatSeoAgent_Loopback::get(
            home_url( '/' ),
            array(
                'redirection' => 3,
                'user-agent'  => self::user_agent(),
            )
        );
        if ( 200 === $home['status'] ) {
            $links = array_merge( $links, self::internal_links( $home['body'] ) );
        }

        return array_values( array_unique( $links ) );
    }

    /**
     * The internal page addresses an HTML fragment links to, normalized.
     *
     * Leaves out anchors, mail and phone links, other sites, and addresses
     * that are not pages: files, feeds, the admin, the REST API.
     *
     * @since 2.3.0
     * @param string $html HTML.
     * @return array<int, string>
     */
    public static function internal_links( $html ) {
        preg_match_all( '/<a\s[^>]*href\s*=\s*["\']([^"\']+)["\']/i', (string) $html, $matches );

        $home  = wp_parse_url( home_url( '/' ) );
        $host  = isset( $home['host'] ) ? strtolower( $home['host'] ) : '';
        $links = array();

        foreach ( $matches[1] as $href ) {
            $href = trim( html_entity_decode( $href, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

            if ( '' === $href || '#' === $href[0] || preg_match( '/^(mailto|tel|javascript|data):/i', $href ) ) {
                continue;
            }

            if ( 0 === strpos( $href, '//' ) ) {
                $href = ( isset( $home['scheme'] ) ? $home['scheme'] : 'https' ) . ':' . $href;
            } elseif ( '/' === $href[0] ) {
                $href = home_url( $href );
            } elseif ( ! preg_match( '#^https?://#i', $href ) ) {
                continue;
            }

            $parts = wp_parse_url( $href );
            if ( empty( $parts['host'] ) || strtolower( $parts['host'] ) !== $host ) {
                continue;
            }

            $path = isset( $parts['path'] ) ? $parts['path'] : '/';
            if ( preg_match( '#^/(wp-content|wp-admin|wp-includes|wp-json)(/|$)#', $path ) || preg_match( '#/(feed|embed)/?$#', $path ) || preg_match( '#\.(?!md$)[a-z0-9]{2,5}$#i', $path ) ) {
                continue;
            }

            // A link to a page's Markdown version is a link to the page.
            $links[] = preg_replace( '/\.md$/', '', self::normalize( $href ) );
        }

        return array_values( array_unique( $links ) );
    }

    /**
     * An address reduced to how the site tells pages apart: scheme and
     * host dropped, no query or fragment, no trailing slash, lower case.
     *
     * @since 2.3.0
     * @param string $url URL.
     * @return string
     */
    public static function normalize( $url ) {
        $path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
        $path = strtolower( rawurldecode( $path ) );

        return '/' . trim( $path, '/' );
    }

    /**
     * Ask the site for addresses it did not recognize.
     *
     * HEAD requests, all at once. Only the status matters: 404 and 410 are
     * broken; a failed request is left unknown.
     *
     * @since 2.3.0
     * @param array<int, string> $paths Normalized paths.
     * @return array<string, int> Path => status, for the ones that answered.
     */
    private static function probe( array $paths ) {
        if ( ! $paths ) {
            return array();
        }

        $requests = array();
        foreach ( $paths as $path ) {
            $requests[ $path ] = array(
                'url'        => home_url( user_trailingslashit( $path ) ),
                'method'     => 'HEAD',
                'timeout'    => 5,
                'user-agent' => self::user_agent(),
            );
        }

        $statuses = array();
        foreach ( ThatSeoAgent_Loopback::many( $requests ) as $path => $response ) {
            if ( $response['status'] ) {
                $statuses[ $path ] = $response['status'];
            }
        }

        return $statuses;
    }

    /**
     * How the link check introduces itself.
     *
     * @since 2.7.0
     * @return string
     */
    private static function user_agent() {
        return 'ThatSeoAgent/' . THATSEOAGENT_VERSION . ' (link check; +' . home_url( '/' ) . ')';
    }

    /**
     * This module's part of the bulletin: navigation that leads nowhere,
     * known once a content check (or an agent's audit) has read the site's
     * links. Only a graph already built: the bulletin never requests pages.
     *
     * @since 2.7.0 Moved from ThatSeoAgent_Bulletin::compose().
     * @return array{observations: array, warnings: array}
     */
    public static function bulletin() {
        $links    = self::cached();
        $warnings = array();

        if ( $links && ! empty( $links['navigation_broken'] ) ) {
            $count      = count( $links['navigation_broken'] );
            $warnings[] = ThatSeoAgent_Bulletin::warning(
                'yellow',
                /* translators: %d: number of links. */
                sprintf( _n( 'The site\'s navigation links to %d page that does not exist', 'The site\'s navigation links to %d pages that do not exist', $count, 'thatseoagent' ), $count ),
                /* translators: %s: the addresses, comma-separated. */
                sprintf( __( 'The menus, header or footer point to %s, which answer "not found". Visitors who click get an error page; fix the links in the theme or the menus, or create the pages.', 'thatseoagent' ), implode( ', ', array_slice( $links['navigation_broken'], 0, 4 ) ) . ( $count > 4 ? ', …' : '' ) ),
                __( 'See the content check', 'thatseoagent' ),
                'audit'
            );
        }

        return array(
            'observations' => array(),
            'warnings'     => $warnings,
        );
    }
}
