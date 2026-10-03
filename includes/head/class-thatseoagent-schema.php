<?php
/**
 * Schema/JSON-LD Handler
 *
 * @package ThatSeoAgent
 * @since 1.0.0
 */

if ( !defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Schema {

    /**
     * Register the hooks.
     *
     * @since 1.19.0 Moved out of ThatSeoAgent.
     */
    public static function register() {
        add_action( 'wp_head', array( __CLASS__, 'output' ), 2 );
    }

    /**
     * Print the current view's JSON-LD.
     *
     * The adapter between the request and the graph: it works out what the
     * view is, asks for its graph and prints it. A single page's graph is
     * for_post()'s, the same wherever it is asked for.
     *
     * @since 1.0.0
     * @since 2.7.0 A single page's graph from for_post().
     */
    public static function output() {
        $post = is_singular() ? get_queried_object() : null;

        if ( $post instanceof WP_Post ) {
            $graph = self::for_post( $post );
        } elseif ( is_author() ) {
            $graph = self::listing_graph( 'ProfilePage' );
        } elseif ( self::is_listing() ) {
            $graph = self::listing_graph( 'CollectionPage' );
        } else {
            $graph = self::finish( self::site_nodes(), null );
        }

        $output = array(
            '@context' => 'https://schema.org',
            '@graph' => $graph
        );

        echo '<script type="application/ld+json">' . "\n";
        // JSON_HEX_TAG escapes < and > so content containing "</script>"
        // cannot break out of the JSON-LD block.
        echo wp_json_encode( $output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG );
        echo "\n</script>\n";
    }

    /**
     * The JSON-LD graph of a post's own page, as its head prints it, from
     * anywhere: the site's nodes, the WebPage, the Product or the Article
     * with its author and FAQ, and the BreadcrumbList — unless WooCommerce
     * states that one (ThatSeoAgent_WooCommerce).
     *
     * @since 2.7.0 From output().
     * @since 2.10.0 No BreadcrumbList where WooCommerce states one.
     * @param WP_Post $post Post.
     * @return array<int, array> The @graph's nodes.
     */
    public static function for_post( WP_Post $post ) {
        $graph = self::site_nodes();

        // Built first: the WebPage node points at it. None where
        // WooCommerce states its own.
        $breadcrumb = ThatSeoAgent_WooCommerce::states_breadcrumbs( $post )
            ? null
            : self::get_breadcrumb_schema( ThatSeoAgent_Breadcrumbs::for_post( $post ), get_permalink( $post ), $post );

        // A catalog entry is a Product, and the page is about it. Not an
        // Article as well, even when its post type is listed as one: a
        // page has one main entity.
        $product = ThatSeoAgent_Product::schema( $post );

        $graph[] = self::get_webpage_schema( $post, $product ? $product['@id'] : '', $breadcrumb );

        if ( $product ) {
            $graph[] = $product;
        }

        if ( ! $product && in_array( $post->post_type, self::article_post_types(), true ) ) {
            $graph[] = self::get_article_schema( $post );
            $graph[] = self::get_author_person_schema( (int) $post->post_author );
            $graph[] = self::get_faq_schema( $post );
        }

        $graph[] = $breadcrumb;

        return self::finish( $graph, $post );
    }

    /**
     * The graph of a listing: an archive, the blog, or an author's page.
     *
     * @since 2.7.0 From output().
     * @since 2.10.0 No BreadcrumbList on the shop and the product archives
     *               while WooCommerce states one.
     * @param string $type 'CollectionPage' or 'ProfilePage'.
     * @return array<int, array>
     */
    private static function listing_graph( $type ) {
        $graph      = self::site_nodes();
        $url        = self::current_url();
        $breadcrumb = ThatSeoAgent_WooCommerce::states_breadcrumbs()
            ? null
            : self::get_breadcrumb_schema( ThatSeoAgent_Breadcrumbs::trail(), $url, null );
        $person     = 'ProfilePage' === $type ? self::get_author_person_schema( get_queried_object_id() ) : null;

        $graph[] = self::get_listing_page_schema( $type, $breadcrumb, $person ? $person['@id'] : '' );
        $graph[] = $person;
        $graph[] = $breadcrumb;

        return self::finish( $graph, null );
    }

    /**
     * The nodes every page carries: the WebSite, and the Organization or
     * the Person that publishes it.
     *
     * @since 2.7.0 From output().
     * @return array<int, array|null>
     */
    private static function site_nodes() {
        $nodes = array( self::get_website_schema() );

        // Person schema — only present when the site is configured (or a
        // filter opts in) as representing an individual.
        $person_schema = self::get_person_schema();

        // The site has exactly one primary entity. Emitting both a Person and
        // an Organization leaves two nodes competing to be the publisher, so
        // the Organization node is dropped when a Person is the primary one.
        $person_is_primary = ( 'person' === self::get_primary_entity() ) && ! empty( $person_schema );

        if ( ! $person_is_primary ) {
            $nodes[] = self::get_organization_schema();
        }

        if ( $person_schema ) {
            $nodes[] = $person_schema;
        }

        return $nodes;
    }

    /**
     * The graph as it is published: empty nodes out, the filter, and no
     * reference left pointing at nothing.
     *
     * @since 2.7.0 From output().
     * @param array        $graph Nodes, some maybe null.
     * @param WP_Post|null $post  The post whose page it is, null otherwise.
     * @return array<int, array>
     */
    private static function finish( array $graph, $post ) {
        /**
         * Filter the complete JSON-LD @graph before output.
         *
         * Allows adding, removing, or reordering schema nodes. Runs after
         * individual node filters (thatseoagent_website_schema, etc.) so the
         * graph this filter receives contains each node's final shape.
         *
         * @since 1.5.0
         * @since 2.7.0 $post, as the graph of a post can be built outside its
         *              page's request.
         * @param array        $graph Array of schema node arrays.
         * @param WP_Post|null $post  The post whose page it is, null on any other view.
         */
        $graph = apply_filters( 'thatseoagent_schema_graph', array_filter( $graph ), $post );

        // After the filter, so a node removed there takes its references
        // with it.
        return self::without_dangling_references( array_values( array_filter( (array) $graph ) ) );
    }

    /**
     * The graph without references to nodes of this site that it does not
     * contain.
     *
     * A reference is a node holding only an `@id` (and maybe a `@type`); it
     * means "the node described elsewhere with this @id". When no node of
     * the page describes it — a breadcrumb dropped as broken, a Person a
     * filter removed — the reference points at nothing, and validators
     * report it. References to other sites are left alone: they are
     * described there.
     *
     * @since 2.4.0
     * @param array<int, array> $graph Nodes.
     * @return array<int, array>
     */
    private static function without_dangling_references( array $graph ) {
        $declared = array();
        self::collect_ids( $graph, $declared );

        $home = untrailingslashit( home_url() );

        $prune = function ( $value ) use ( &$prune, $declared, $home ) {
            if ( ! is_array( $value ) ) {
                return $value;
            }

            $clean = array();
            foreach ( $value as $key => $item ) {
                if ( is_array( $item ) && self::is_reference( $item ) ) {
                    $id = (string) $item['@id'];
                    if ( 0 === strpos( $id, $home ) && ! isset( $declared[$id] ) ) {
                        continue;
                    }
                }

                $item = $prune( $item );

                // A list emptied of every reference it held goes too.
                if ( is_array( $item ) && array() === $item && is_array( $value[$key] ) ) {
                    continue;
                }

                $clean[$key] = $item;
            }

            return array_keys( $value ) === range( 0, count( $value ) - 1 ) ? array_values( $clean ) : $clean;
        };

        $result = array();
        foreach ( $graph as $node ) {
            $result[] = is_array( $node ) ? $prune( $node ) : $node;
        }

        return $result;
    }

    /**
     * Every @id the graph describes: nodes with more than an @id and a type.
     *
     * @since 2.4.0
     * @param mixed                $value    Graph or node.
     * @param array<string, bool>  $declared IDs found, by reference.
     */
    private static function collect_ids( $value, array &$declared ) {
        if ( ! is_array( $value ) ) {
            return;
        }

        if ( isset( $value['@id'] ) && ! self::is_reference( $value ) ) {
            $declared[(string) $value['@id']] = true;
        }

        foreach ( $value as $item ) {
            self::collect_ids( $item, $declared );
        }
    }

    /**
     * Whether a node only points at another: an @id and at most a @type.
     *
     * @since 2.4.0
     * @param array $node Node.
     * @return bool
     */
    private static function is_reference( array $node ) {
        return isset( $node['@id'] ) && array() === array_diff( array_keys( $node ), array( '@id', '@type' ) );
    }

    /**
     * Which entity the site primarily represents: 'organization' or 'person'.
     *
     * @since 1.8.0
     * @return string
     */
    public static function get_primary_entity() {
        /**
         * Filter the site's primary schema entity.
         *
         * 'person' when the Site Identity settings say so. When it resolves
         * to 'person' and a Person node exists, the Organization node is
         * omitted and the Person becomes the publisher of every Article.
         *
         * @since 1.8.0
         * @since 2.7.0 Receives the identity's answer instead of
         *              'organization'.
         * @param string $entity Either 'organization' or 'person'.
         */
        $entity = apply_filters( 'thatseoagent_primary_entity', ThatSeoAgent_Identity::primary_entity() );

        return 'person' === $entity ? 'person' : 'organization';
    }

    /**
     * The @id of the node that publishes the site's content.
     *
     * @since 1.8.0
     * @return string
     */
    public static function get_publisher_id() {
        if ( 'person' === self::get_primary_entity() && self::get_person_schema() ) {
            return home_url( '/#person' );
        }

        return home_url( '/#organization' );
    }

    /**
     * The site language as a BCP 47 tag, e.g. "es-ES".
     *
     * get_locale() returns "es_ES"; schema.org wants the hyphenated form.
     *
     * @since 1.10.0
     * @return string
     */
    private static function get_language() {
        return get_bloginfo( 'language' );
    }

    /**
     * Website schema
     */
    private static function get_website_schema() {
        $schema = array(
            '@type' => 'WebSite',
            '@id' => home_url( '/#website' ),
            'url' => home_url( '/' ),
            'name' => get_bloginfo( 'name' ),
            'inLanguage' => self::get_language(),
        );

        // Only when the site actually has a tagline: an empty string is noise
        // in the graph.
        $tagline = get_bloginfo( 'description' );
        if ( $tagline ) {
            $schema['description'] = $tagline;
        }

        // No potentialAction/SearchAction: Google retired the sitelinks
        // search box, so the node was dead weight on every page. Add it back
        // through thatseoagent_website_schema if another consumer needs it.

        /**
         * Filter the WebSite schema node.
         *
         * @since 1.5.0
         * @param array $schema WebSite schema array.
         */
        return apply_filters( 'thatseoagent_website_schema', $schema );
    }

    /**
     * Organization schema
     */
    private static function get_organization_schema() {
        $schema = array(
            '@type' => 'Organization',
            '@id' => home_url( '/#organization' ),
            'name' => get_bloginfo( 'name' ),
            'url' => home_url( '/' ),
        );

        // Add logo if available
        $logo = ThatSeoAgent_Image::of( (int) get_theme_mod( 'custom_logo' ) );
        if ( $logo ) {
            $schema['logo'] = ThatSeoAgent_Image::object( $logo );
        }

        /**
         * Filter the Organization schema node.
         *
         * Use this to add sameAs (social URLs), description, founder,
         * contactPoint, or any other Schema.org Organization properties.
         *
         * @since 1.5.0
         * @param array $schema Organization schema array.
         */
        return apply_filters( 'thatseoagent_organization_schema', ThatSeoAgent_Identity::organization_node( $schema ) );
    }

    /**
     * Person schema (opt-in via filter).
     *
     * Not emitted by default. Sites representing an individual (personal
     * blogs, author sites) can return a populated Person node via the
     * thatseoagent_person_schema filter and it will be added to the graph.
     *
     * @since 1.5.0
     * @return array|null Person schema array, or null to omit.
     */
    private static function get_person_schema() {
        // Memoised: output() and get_publisher_id() both ask, and the filter
        // chain behind this reaches the options table and the media library.
        return ThatSeoAgent_Memo::remember( 'person_schema', 'site', function () {
            return self::filter_person_schema();
        } );
    }

    /**
     * The Person node as the thatseoagent_person_schema filter returns it.
     *
     * @since 1.20.0 Split from get_person_schema().
     * @return array|null
     */
    private static function filter_person_schema() {
        // The identity's Person node, when the site represents a person.
        $default = ThatSeoAgent_Identity::person_node();

        /**
         * Filter the Person schema node.
         *
         * Return a Schema.org Person array to include it in the graph,
         * or null (default) to omit. Typical shape:
         *
         *     array(
         *         '@type'       => 'Person',
         *         '@id'         => home_url('/#person'),
         *         'name'        => 'Jane Doe',
         *         'url'         => home_url('/'),
         *         'description' => 'Writer, developer, sailor.',
         *         'sameAs'      => array('https://twitter.com/...', ...),
         *     )
         *
         * @since 1.5.0
         * @since 2.7.0 Receives the identity's Person node, when there is one.
         * @param array|null $schema The identity's Person node, else null (omitted).
         */
        return apply_filters( 'thatseoagent_person_schema', $default );
    }

    /**
     * Article schema for posts
     *
     * @since 2.7.0 For a given post.
     * @param WP_Post $post Post.
     * @return array
     */
    private static function get_article_schema( $post ) {
        $url = get_permalink( $post );

        $schema = array(
            '@type' => 'Article',
            '@id' => $url . '#article',
            'isPartOf' => array( '@id' => $url . '#webpage' ),
            'headline' => get_the_title( $post ),
            'datePublished' => get_the_date( 'c', $post ),
            'dateModified' => get_the_modified_date( 'c', $post ),
            'mainEntityOfPage' => array( '@id' => $url . '#webpage' ),
            'wordCount' => ThatSeoAgent_Content::word_count( $post ),
            'inLanguage' => self::get_language(),
            'publisher' => array( '@id' => self::get_publisher_id() ),
            'author' => self::get_author_schema( $post ),
        );

        // The post's own image: the featured one, else the first in its
        // content. Never the site's logo or default image, which say
        // nothing about this article.
        $image = self::post_image( $post );
        if ( $image ) {
            $schema['image'] = $image;
        }

        // Add description
        $description = ThatSeoAgent_Description::for_post( $post );
        if ( $description ) {
            $schema['description'] = $description;
        }

        return $schema;
    }

    /**
     * The WebPage type of the current page: AboutPage or ContactPage for
     * the trust pages the site has, WebPage for the rest.
     *
     * @since 2.6.0
     * @since 2.7.0 For a given post.
     * @param WP_Post $post Post.
     * @return string
     */
    private static function webpage_type( $post ) {
        $kind = ThatSeoAgent_Trust_Pages::kind_of( $post->ID );

        return array(
            'about'   => 'AboutPage',
            'contact' => 'ContactPage',
        )[$kind] ?? 'WebPage';
    }

    /**
     * Post types that get an Article node.
     *
     * @since 2.6.0 Extracted from output(), for the bulletin's count of
     *              posts without an author.
     * @return array<int, string>
     */
    public static function article_post_types() {
        /**
         * Post types that get an Article node.
         *
         * A product or a landing page is not an Article, so this stays
         * narrow by default. Add a news-style custom post type here to
         * have it marked up as one.
         *
         * @since 1.10.0
         * @param array $post_types Default array('post').
         */
        return (array) apply_filters( 'thatseoagent_article_post_types', array( 'post' ) );
    }

    /**
     * A post's own image as an ImageObject, or null.
     *
     * @since 2.4.0 From ThatSeoAgent_Image; before, only the featured image.
     * @param WP_Post $post Post.
     * @return array|null
     */
    private static function post_image( $post ) {
        $image = ThatSeoAgent_Image::own( $post, 'schema' );

        return $image ? ThatSeoAgent_Image::object( $image ) : null;
    }

    /**
     * WebPage schema
     *
     * @since 1.16.0 Accepts the page's main entity and breadcrumb, and adds
     *              the description and primary image.
     * @since 2.7.0 For a given post.
     * @param WP_Post    $post           Post.
     * @param string     $main_entity_id @id of the node the page is about, or ''.
     * @param array|null $breadcrumb     BreadcrumbList node, if any.
     */
    private static function get_webpage_schema( $post, $main_entity_id = '', $breadcrumb = null ) {
        $url = get_permalink( $post );

        $schema = array(
            // The about and contact pages say what they are: AboutPage and
            // ContactPage are the WebPage subtypes schema.org has for them.
            '@type' => self::webpage_type( $post ),
            '@id' => $url . '#webpage',
            'url' => $url,
            'name' => get_the_title( $post ),
            'isPartOf' => array( '@id' => home_url( '/#website' ) ),
            'inLanguage' => self::get_language(),
            'datePublished' => get_the_date( 'c', $post ),
            'dateModified' => get_the_modified_date( 'c', $post ),
        );

        $description = ThatSeoAgent_Description::for_post( $post );
        if ( $description ) {
            $schema['description'] = $description;
        }

        if ( $breadcrumb ) {
            $schema['breadcrumb'] = array( '@id' => $breadcrumb['@id'] );
        }

        $image = self::post_image( $post );
        if ( $image ) {
            $schema['primaryImageOfPage'] = array_merge( array( '@id' => $url . '#primaryimage' ), $image );
        }

        if ( $main_entity_id ) {
            $schema['mainEntity'] = array( '@id' => $main_entity_id );
        }

        /**
         * Filter the WebPage schema node.
         *
         * @since 1.5.0
         * @since 2.7.0 $post.
         * @param array   $schema WebPage schema array.
         * @param WP_Post $post   The post whose page it is.
         */
        return apply_filters( 'thatseoagent_webpage_schema', $schema, $post );
    }

    /**
     * Breadcrumb schema
     *
     * The BreadcrumbList of the trail ThatSeoAgent_Breadcrumbs computes —
     * the same trail the visible breadcrumbs print. See that class for the
     * trail by view.
     *
     * @since 1.16.0 Covers pages' parents, custom post type archives, term
     *              archives and author archives.
     * @since 2.4.0 From ThatSeoAgent_Breadcrumbs, and dropped when a crumb
     *              is broken.
     * @since 2.7.0 From a given trail, for a given page.
     * @param array        $crumbs The trail.
     * @param string       $url    The page's URL.
     * @param WP_Post|null $post   The post whose page it is, null on a listing.
     * @return array|null
     */
    private static function get_breadcrumb_schema( array $crumbs, $url, $post ) {
        // A trail with a gap — a crumb with no name, or one before the last
        // with no link — is dropped whole rather than published broken; so
        // is a lone "Home", which says nothing. The WebPage then points at
        // no breadcrumb.
        if ( ! ThatSeoAgent_Breadcrumbs::is_complete( $crumbs ) ) {
            return null;
        }

        $last  = count( $crumbs ) - 1;
        $items = array();
        foreach ( $crumbs as $index => $crumb ) {
            $item = array(
                '@type'    => 'ListItem',
                'position' => $index + 1,
                'name'     => $crumb['name'],
            );

            // The last crumb is the page itself and carries no link, as
            // Google's BreadcrumbList guidance allows.
            if ( $index < $last ) {
                $item['item'] = $crumb['url'];
            }

            $items[] = $item;
        }

        $schema = array(
            '@type' => 'BreadcrumbList',
            '@id' => $url . '#breadcrumb',
            'itemListElement' => $items
        );

        /**
         * Filter the BreadcrumbList schema node.
         *
         * @since 1.5.0
         * @since 2.7.0 $post.
         * @param array        $schema Breadcrumb schema array.
         * @param WP_Post|null $post   The post whose page it is, null on a listing.
         */
        return apply_filters( 'thatseoagent_breadcrumb_schema', $schema, $post );
    }

    /**
     * Whether the view lists posts: an archive or the blog index.
     *
     * @since 1.16.0
     * @return bool
     */
    private static function is_listing() {
        return is_post_type_archive()
            || is_category()
            || is_tag()
            || is_tax()
            || ( is_home() && ! is_front_page() );
    }

    /**
     * The URL of the current view, as the canonical tag states it.
     *
     * @since 1.16.0
     * @return string
     */
    private static function current_url() {
        $canonical = ThatSeoAgent_Meta::get_canonical();

        return $canonical ? $canonical : home_url( '/' );
    }

    /**
     * The page node of a listing: a CollectionPage or a ProfilePage.
     *
     * @since 1.16.0
     * @param string     $type           'CollectionPage' or 'ProfilePage'.
     * @param array|null $breadcrumb     BreadcrumbList node, if any.
     * @param string     $main_entity_id @id of the node the page is about, or ''.
     * @return array
     */
    private static function get_listing_page_schema( $type, $breadcrumb = null, $main_entity_id = '' ) {
        $url = self::current_url();

        $schema = array(
            '@type'      => $type,
            '@id'        => $url . '#webpage',
            'url'        => $url,
            'name'       => wp_strip_all_tags( self::listing_title() ),
            'isPartOf'   => array( '@id' => home_url( '/#website' ) ),
            'inLanguage' => self::get_language(),
        );

        $description = ThatSeoAgent_Meta::get_description();
        if ( $description ) {
            $schema['description'] = $description;
        }

        if ( $breadcrumb ) {
            $schema['breadcrumb'] = array( '@id' => $breadcrumb['@id'] );
        }

        if ( $main_entity_id ) {
            $schema['mainEntity'] = array( '@id' => $main_entity_id );
        }

        /**
         * Filter the CollectionPage or ProfilePage node of a listing.
         *
         * @since 1.16.0
         * @param array  $schema Page node.
         * @param string $type   'CollectionPage' or 'ProfilePage'.
         */
        return apply_filters( 'thatseoagent_listing_page_schema', $schema, $type );
    }

    /**
     * The title of the listing being viewed.
     *
     * @since 1.16.0
     * @return string
     */
    private static function listing_title() {
        if ( is_post_type_archive() ) {
            return post_type_archive_title( '', false );
        }

        if ( is_category() || is_tag() || is_tax() ) {
            return single_term_title( '', false );
        }

        if ( is_author() ) {
            $author = get_queried_object();
            return $author instanceof WP_User ? $author->display_name : '';
        }

        if ( is_home() ) {
            return get_the_title( (int) get_option( 'page_for_posts' ) );
        }

        return wp_get_document_title();
    }

    /**
     * The Person node of a post author, shared by their posts and archive.
     *
     * The same @id on every Article they wrote and on their ProfilePage lets
     * search engines join them into one person rather than one anonymous
     * author per post.
     *
     * @since 1.16.0
     * @param int $user_id User ID.
     * @return array|null
     */
    private static function get_author_person_schema( $user_id ) {
        $user = $user_id ? get_userdata( $user_id ) : false;
        if ( ! $user || '' === $user->display_name ) {
            return null;
        }

        $url = get_author_posts_url( $user->ID );

        $schema = array(
            '@type' => 'Person',
            '@id'   => $url . '#person',
            'name'  => $user->display_name,
            'url'   => $url,
        );

        if ( $user->description ) {
            $schema['description'] = wp_strip_all_tags( $user->description );
        }

        $job_title = ThatSeoAgent_Author_Profile::job_title( $user->ID );
        if ( '' !== $job_title ) {
            $schema['jobTitle'] = $job_title;
        }

        // The profile's website field — often a personal site or a social
        // profile, which is what sameAs is for — and the profiles elsewhere
        // the author listed. The site's own URL adds nothing.
        $same_as = ThatSeoAgent_Author_Profile::profiles( $user->ID );
        $website = $user->user_url ? esc_url_raw( $user->user_url ) : '';
        if ( $website && untrailingslashit( $website ) !== untrailingslashit( home_url( '/' ) ) ) {
            array_unshift( $same_as, $website );
        }
        $same_as = array_values( array_unique( array_map( 'untrailingslashit', $same_as ) ) );
        if ( $same_as ) {
            $schema['sameAs'] = $same_as;
        }

        /**
         * Filter the Person node of a post author.
         *
         * Add sameAs profiles, jobTitle, image or any other Person property.
         * Return null to omit the node; Articles then still name the author.
         *
         * @since 1.16.0
         * @param array   $schema Person node.
         * @param WP_User $user   The author.
         */
        return apply_filters( 'thatseoagent_author_schema', $schema, $user );
    }

    /**
     * Build author schema for the current post.
     *
     * Uses post author if available, otherwise falls back to
     * publisher defaults from options.
     *
     * @since 1.3.0
     * @since 2.7.0 For a given post.
     * @param WP_Post $post Post.
     * @return array Schema.org Person or Organization array.
     */
    private static function get_author_schema( $post ) {
        // From the post object, not get_the_author(): that reads the
        // $authordata global, only set once the loop has run.
        $author_id = (int) $post->post_author;

        if ( ! ThatSeoAgent_Default_Author::is_unattributed( $post ) ) {
            $author_name = get_the_author_meta( 'display_name', $author_id );

            // The Person node itself sits in the graph, where the author's
            // archive page uses the same @id. Name and URL stay inline so the
            // Article still names its author if a filter drops that node.
            return array(
                '@type' => 'Person',
                '@id'   => get_author_posts_url( $author_id ) . '#person',
                'name'  => $author_name,
                'url'   => get_author_posts_url( $author_id ),
            );
        }

        // A configured fallback author wins.
        $credited = ThatSeoAgent_Default_Author::credited();
        if ( $credited ) {
            return $credited;
        }

        // Nothing configured: point at the entity that already publishes the
        // site. The old default built a Person node named after the blog,
        // which claimed a person wrote the post when none was assigned.
        return array( '@id' => self::get_publisher_id() );
    }

    /**
     * FAQPage schema, built from the post's own headings.
     *
     * The reading of the content lives in ThatSeoAgent_FAQ; this method only
     * shapes the result into a schema node.
     *
     * @since 1.1.0
     * @since 1.9.0 Extraction moved to ThatSeoAgent_FAQ.
     * @since 2.7.0 For a given post.
     * @param WP_Post $post Post.
     * @return array|null FAQPage schema array or null.
     */
    private static function get_faq_schema( $post ) {
        /**
         * Filter to disable FAQ schema for specific posts.
         *
         * @param bool $enabled Whether FAQ schema is enabled. Default true.
         * @param int  $post_id The current post ID.
         */
        if ( ! apply_filters( 'thatseoagent_faq_schema_enabled', true, $post->ID ) ) {
            return null;
        }

        $qa_pairs = ThatSeoAgent_FAQ::pairs_for_post( $post );

        /**
         * Filter the extracted FAQ Q&A pairs before schema output.
         *
         * @param array $qa_pairs Array of ['question' => string, 'answer' => string].
         * @param int   $post_id  The current post ID.
         */
        $qa_pairs = apply_filters( 'thatseoagent_faq_pairs', $qa_pairs, $post->ID );

        if ( count( $qa_pairs ) < ThatSeoAgent_FAQ::MIN_PAIRS ) {
            return null;
        }

        $entities = array();
        foreach ( $qa_pairs as $pair ) {
            $entities[] = array(
                '@type'          => 'Question',
                'name'           => $pair['question'],
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text'  => $pair['answer'],
                ),
            );
        }

        return array(
            '@type'      => 'FAQPage',
            '@id'        => get_permalink( $post ) . '#faq',
            'mainEntity' => $entities,
        );
    }
}
