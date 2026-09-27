<?php
/**
 * The search title.
 *
 * The single answer to "what title does this post show in search results?":
 * the one the <title> tag carries, og:title repeats, the editor's preview
 * shows and the content check measures. Before 2.7.0 each of them built its
 * own — the preview left the site name out, the content check measured the
 * bare post title, and the thatseoagent_title filter reached the meta tags
 * but not the <title>.
 *
 * The interface takes a post, like ThatSeoAgent_Description's, so it answers
 * the same from wp_head, from the editor, from an ability and from WP-CLI.
 * On a single page the <title> is this module's answer; on a listing,
 * WordPress still builds it.
 *
 * @package ThatSeoAgent
 * @since 1.19.0 Moved out of ThatSeoAgent.
 * @since 2.7.0 for_post(): the search title of any post.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Title {

    /**
     * Whether for_post() is building a title, so the document_title
     * callback leaves the thatseoagent_title filter to it.
     *
     * @var bool
     */
    private static $building = false;

    /**
     * Register the hooks.
     *
     * @since 1.19.0
     */
    public static function register() {
        add_filter( 'pre_get_document_title', array( __CLASS__, 'filter_document_title' ), 10 );
        add_filter( 'document_title', array( __CLASS__, 'filter_listing_title' ), 99 );
        add_filter( 'document_title_separator', array( __CLASS__, 'separator' ) );
    }

    /**
     * The search title of a post.
     *
     * The `thatseoagent_document_title` override first; then, on the
     * homepage, the homepage's own title; then the post's SEO title; then
     * the title WordPress would build — the post's name and the site's, or
     * on the homepage the site's name and tagline — through core's
     * document_title filters, so what other plugins do to it still applies.
     * The `thatseoagent_title` filter has the last word.
     *
     * @since 2.7.0
     * @param WP_Post|int $post    Post object or ID.
     * @param string|null $written The SEO title to assume, '' for none;
     *                             null reads the post's own. The editor's
     *                             preview passes '' to show what an empty
     *                             field publishes.
     * @return string Plain text, not escaped.
     */
    public static function for_post( $post, $written = null ) {
        $post = get_post( $post );
        if ( ! $post ) {
            return '';
        }

        $context = ThatSeoAgent_Homepage::is_front_page( $post ) || ThatSeoAgent_Homepage::is_posts_page( $post ) ? 'home' : 'single';
        $written = null === $written ? ThatSeoAgent_Post_Seo::get( $post, 'title' ) : (string) $written;

        /**
         * Filter the final <title> tag output.
         *
         * Return a non-empty string to replace the title entirely. Return
         * empty (default) to let core + thatseoagent build it normally.
         * This is the only filter that can override the homepage title
         * without touching the theme.
         *
         * @since 1.5.0
         * @since 2.7.0 $post: the post whose title it is, null on a listing.
         * @param string       $title   Empty by default; override with a string to short-circuit.
         * @param string       $context Current page context (see ThatSeoAgent_Meta::get_context()).
         * @param WP_Post|null $post    The post.
         */
        $title = (string) apply_filters( 'thatseoagent_document_title', '', $context, $post );

        if ( '' === $title && ThatSeoAgent_Homepage::is_front_page( $post ) ) {
            $title = ThatSeoAgent_Homepage::expand_variables( ThatSeoAgent_Homepage::get_settings()['title'] );
        }

        // A custom SEO title replaces the document title outright, rather
        // than only its title part. The field is the full title a site owner
        // wants in search results — it is what the meta box preview shows
        // verbatim — and before 1.12.0 WordPress still appended the site name
        // after it, so "Product | Acme" went out as "Product | Acme | Acme".
        if ( '' === $title ) {
            $title = $written;
        }

        if ( '' === $title ) {
            $title = self::built( $post );
        }

        $title = html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        /**
         * Filter the search title.
         *
         * @since 1.5.0
         * @since 2.7.0 Reaches the <title> too, not only the meta tags, and
         *              receives the post.
         * @param string       $title   The search title.
         * @param string       $context Current page context (see ThatSeoAgent_Meta::get_context()).
         * @param WP_Post|null $post    The post, null on a listing.
         */
        return (string) apply_filters( 'thatseoagent_title', $title, $context, $post );
    }

    /**
     * The title WordPress builds for a post, as wp_get_document_title()
     * does on its page.
     *
     * @since 2.7.0
     * @param WP_Post $post Post.
     * @return string Escaped, as core leaves it.
     */
    private static function built( WP_Post $post ) {
        $site = get_bloginfo( 'name', 'display' );

        if ( ThatSeoAgent_Homepage::is_front_page( $post ) ) {
            $parts = array(
                'title'   => $site,
                'tagline' => get_bloginfo( 'description', 'display' ),
            );
        } else {
            $parts = array(
                /** This filter is documented in wp-includes/general-template.php */
                'title' => apply_filters( 'single_post_title', $post->post_title, $post ),
                'site'  => $site,
            );
        }

        // Page 2 of a paginated post, while its own page is being served.
        $current = did_action( 'wp' ) ? ThatSeoAgent_Meta::current_post() : null;
        $page    = max( (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
        if ( $current && $current->ID === $post->ID && $page >= 2 ) {
            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Core's own string, as wp_get_document_title() prints it.
            $parts['page'] = sprintf( __( 'Page %s', 'default' ), $page );
        }

        self::$building = true;

        /** This filter is documented in wp-includes/general-template.php */
        $parts = apply_filters( 'document_title_parts', $parts );

        /** This filter is documented in wp-includes/general-template.php */
        $separator = apply_filters( 'document_title_separator', self::separator( '-' ) );

        $title = implode( " $separator ", array_filter( $parts ) );
        $title = capital_P_dangit( esc_html( convert_chars( wptexturize( $title ) ) ) );

        /** This filter is documented in wp-includes/general-template.php */
        $title = apply_filters( 'document_title', $title );

        self::$building = false;

        return (string) $title;
    }

    /**
     * The document <title>.
     *
     * On a single page, and on the blog's posts page, the post's search
     * title. On other listings a `thatseoagent_document_title` override
     * replaces WordPress's title, and so does the homepage's own title on a
     * homepage that lists the latest posts; otherwise WordPress builds it.
     *
     * @since 1.5.0
     * @since 2.7.0 A single page's is for_post()'s.
     * @param string $title Current title (empty string by default).
     * @return string
     */
    public static function filter_document_title( $title ) {
        $post = ThatSeoAgent_Meta::current_post();
        if ( $post ) {
            return esc_html( self::for_post( $post ) );
        }

        $context = ThatSeoAgent_Meta::get_context();

        /** This filter is documented in includes/head/class-thatseoagent-title.php */
        $override = (string) apply_filters( 'thatseoagent_document_title', '', $context, null );

        if ( '' === $override && is_front_page() ) {
            $override = esc_html( ThatSeoAgent_Homepage::expand_variables( ThatSeoAgent_Homepage::get_settings()['title'] ) );
        }

        // A term archive's own title, the full title as written.
        if ( '' === $override && ( is_category() || is_tag() || is_tax() ) ) {
            $term = get_queried_object();

            if ( $term instanceof WP_Term ) {
                $override = esc_html( ThatSeoAgent_Term_Seo::get( $term, 'title' ) );
            }
        }

        // WordPress runs no document_title filter on a title that replaces
        // its own, so the last word is given here.
        if ( '' !== $override ) {
            /** This filter is documented in includes/head/class-thatseoagent-title.php */
            return (string) apply_filters( 'thatseoagent_title', $override, $context, null );
        }

        return $title;
    }

    /**
     * Run the thatseoagent_title filter on the title WordPress built for a
     * listing, so the <title> and og:title say the same.
     *
     * @since 2.7.0
     * @param string $title The document title.
     * @return string
     */
    public static function filter_listing_title( $title ) {
        if ( self::$building ) {
            return $title;
        }

        /** This filter is documented in includes/head/class-thatseoagent-title.php */
        return (string) apply_filters( 'thatseoagent_title', $title, ThatSeoAgent_Meta::get_context(), null );
    }

    /**
     * Title separator.
     *
     * @since 1.0.0
     * @since 1.5.0 Made filterable via thatseoagent_title_separator.
     * @param string $sep Core's separator, ignored.
     * @return string
     */
    public static function separator( $sep ) {
        /**
         * Filter the separator used in the document title.
         *
         * @since 1.5.0
         * @param string $separator Default '|'.
         */
        return apply_filters( 'thatseoagent_title_separator', '|' );
    }
}
