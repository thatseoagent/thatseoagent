<?php
/**
 * Schema/JSON-LD Handler
 *
 * @package ThatSeoAgent
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ThatSeoAgent_Schema {

    /**
     * Register the hooks.
     *
     * @since 1.19.0 Moved out of ThatSeoAgent.
     */
    public static function register() {
        add_action('wp_head', array(__CLASS__, 'output'), 2);
    }

    /**
     * Output JSON-LD schema
     */
    public static function output() {
        $schema = array();

        // Website schema (always)
        $schema[] = self::get_website_schema();

        // Person schema — only present when the site is configured (or a
        // filter opts in) as representing an individual.
        $person_schema = self::get_person_schema();

        // The site has exactly one primary entity. Emitting both a Person and
        // an Organization leaves two nodes competing to be the publisher, so
        // the Organization node is dropped when a Person is the primary one.
        $person_is_primary = ('person' === self::get_primary_entity()) && ! empty($person_schema);

        if (! $person_is_primary) {
            $schema[] = self::get_organization_schema();
        }

        if ($person_schema) {
            $schema[] = $person_schema;
        }

        // Page-level nodes for any singular view. Before 1.10.0 only 'post'
        // and 'page' were handled, so every custom post type reached search
        // engines with no WebPage and no BreadcrumbList at all.
        if (is_singular()) {
            // Built first: the WebPage node points at it.
            $breadcrumb = self::get_breadcrumb_schema();

            // A catalog entry is a Product, and the page is about it. Not an
            // Article as well, even when its post type is listed as one: a
            // page has one main entity.
            $product = ThatSeoAgent_Product::schema(get_post());

            $schema[] = self::get_webpage_schema($product ? $product['@id'] : '', $breadcrumb);

            if ($product) {
                $schema[] = $product;
            }

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
            $article_types = apply_filters('thatseoagent_article_post_types', array('post'));

            if (! $product && is_singular((array) $article_types)) {
                $schema[] = self::get_article_schema();

                $author_schema = self::get_author_person_schema((int) get_post()->post_author);
                if ($author_schema) {
                    $schema[] = $author_schema;
                }

                $faq_schema = self::get_faq_schema();
                if ($faq_schema) {
                    $schema[] = $faq_schema;
                }
            }

            $schema[] = $breadcrumb;
        } elseif (is_author()) {
            $breadcrumb = self::get_breadcrumb_schema();
            $person     = self::get_author_person_schema(get_queried_object_id());

            $schema[] = self::get_listing_page_schema('ProfilePage', $breadcrumb, $person ? $person['@id'] : '');
            $schema[] = $person;
            $schema[] = $breadcrumb;
        } elseif (self::is_listing()) {
            $breadcrumb = self::get_breadcrumb_schema();

            $schema[] = self::get_listing_page_schema('CollectionPage', $breadcrumb);
            $schema[] = $breadcrumb;
        }

        // Filter empty values
        $schema = array_filter($schema);

        /**
         * Filter the complete JSON-LD @graph before output.
         *
         * Allows adding, removing, or reordering schema nodes. Runs after
         * individual node filters (thatseoagent_website_schema, etc.) so the
         * graph this filter receives contains each node's final shape.
         *
         * @since 1.5.0
         * @param array $graph Array of schema node arrays.
         */
        $schema = apply_filters('thatseoagent_schema_graph', $schema);

        // Output
        $output = array(
            '@context' => 'https://schema.org',
            '@graph' => $schema
        );

        echo '<script type="application/ld+json">' . "\n";
        // JSON_HEX_TAG escapes < and > so content containing "</script>"
        // cannot break out of the JSON-LD block.
        echo wp_json_encode($output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        echo "\n</script>\n";
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
         * ThatSeoAgent_Identity_Applier sets this to 'person' when the Site
         * Identity settings say so. When it resolves to 'person' and a Person
         * node exists, the Organization node is omitted and the Person becomes
         * the publisher of every Article.
         *
         * @since 1.8.0
         * @param string $entity Either 'organization' (default) or 'person'.
         */
        $entity = apply_filters('thatseoagent_primary_entity', 'organization');

        return 'person' === $entity ? 'person' : 'organization';
    }

    /**
     * The @id of the node that publishes the site's content.
     *
     * @since 1.8.0
     * @return string
     */
    public static function get_publisher_id() {
        if ('person' === self::get_primary_entity() && self::get_person_schema()) {
            return home_url('/#person');
        }

        return home_url('/#organization');
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
        return get_bloginfo('language');
    }

    /**
     * Website schema
     */
    private static function get_website_schema() {
        $schema = array(
            '@type' => 'WebSite',
            '@id' => home_url('/#website'),
            'url' => home_url('/'),
            'name' => get_bloginfo('name'),
            'inLanguage' => self::get_language(),
        );

        // Only when the site actually has a tagline: an empty string is noise
        // in the graph.
        $tagline = get_bloginfo('description');
        if ($tagline) {
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
        return apply_filters('thatseoagent_website_schema', $schema);
    }

    /**
     * Organization schema
     */
    private static function get_organization_schema() {
        $schema = array(
            '@type' => 'Organization',
            '@id' => home_url('/#organization'),
            'name' => get_bloginfo('name'),
            'url' => home_url('/'),
        );

        // Add logo if available
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
            $logo_meta = wp_get_attachment_metadata($custom_logo_id);
            $logo_schema = array(
                '@type' => 'ImageObject',
                'url' => $logo_url,
            );
            if ($logo_meta && isset($logo_meta['width'], $logo_meta['height'])) {
                $logo_schema['width'] = $logo_meta['width'];
                $logo_schema['height'] = $logo_meta['height'];
            }
            $schema['logo'] = $logo_schema;
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
        return apply_filters('thatseoagent_organization_schema', $schema);
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
        $default = null;

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
         * @param array|null $schema Default null (omitted).
         */
        return apply_filters('thatseoagent_person_schema', $default);
    }

    /**
     * Article schema for posts
     */
    private static function get_article_schema() {
        $post = get_post();
        
        $schema = array(
            '@type' => 'Article',
            '@id' => get_permalink() . '#article',
            'isPartOf' => array('@id' => get_permalink() . '#webpage'),
            'headline' => get_the_title(),
            'datePublished' => get_the_date('c'),
            'dateModified' => get_the_modified_date('c'),
            'mainEntityOfPage' => array('@id' => get_permalink() . '#webpage'),
            'wordCount' => ThatSeoAgent_Content::word_count($post),
            'inLanguage' => self::get_language(),
            'publisher' => array('@id' => self::get_publisher_id()),
            'author' => self::get_author_schema(),
        );

        // Add image
        if (has_post_thumbnail()) {
            /**
             * Image size used for the Article image.
             *
             * Google wants at least 1200px wide; the 'large' size caps at
             * 1024 by default, so 'full' is the safer default.
             *
             * @since 1.10.0
             * @param string $size Registered image size. Default 'full'.
             */
            $size = apply_filters('thatseoagent_schema_image_size', 'full');

            $thumb_id = get_post_thumbnail_id(get_the_ID());
            $thumb_url = get_the_post_thumbnail_url(get_the_ID(), $size);
            $thumb_meta = wp_get_attachment_metadata($thumb_id);
            $image_schema = array(
                '@type' => 'ImageObject',
                'url' => $thumb_url,
            );
            if ($thumb_meta && isset($thumb_meta['width'], $thumb_meta['height'])) {
                $image_schema['width'] = $thumb_meta['width'];
                $image_schema['height'] = $thumb_meta['height'];
            }
            $schema['image'] = $image_schema;
        }

        // Add description
        $description = ThatSeoAgent_Meta::get_description();
        if ($description) {
            $schema['description'] = $description;
        }

        return $schema;
    }

    /**
     * WebPage schema
     *
     * @since 1.16.0 Accepts the page's main entity and breadcrumb, and adds
     *              the description and primary image.
     * @param string     $main_entity_id @id of the node the page is about, or ''.
     * @param array|null $breadcrumb     BreadcrumbList node, if any.
     */
    private static function get_webpage_schema($main_entity_id = '', $breadcrumb = null) {
        $schema = array(
            '@type' => 'WebPage',
            '@id' => get_permalink() . '#webpage',
            'url' => get_permalink(),
            'name' => get_the_title(),
            'isPartOf' => array('@id' => home_url('/#website')),
            'inLanguage' => self::get_language(),
            'datePublished' => get_the_date('c'),
            'dateModified' => get_the_modified_date('c'),
        );

        $description = ThatSeoAgent_Meta::get_description();
        if ($description) {
            $schema['description'] = $description;
        }

        if ($breadcrumb) {
            $schema['breadcrumb'] = array('@id' => $breadcrumb['@id']);
        }

        if (has_post_thumbnail()) {
            /** This filter is documented in get_article_schema() */
            $size = apply_filters('thatseoagent_schema_image_size', 'full');
            $src  = wp_get_attachment_image_src(get_post_thumbnail_id(), $size);

            if ($src) {
                $schema['primaryImageOfPage'] = array(
                    '@type'  => 'ImageObject',
                    '@id'    => get_permalink() . '#primaryimage',
                    'url'    => $src[0],
                    'width'  => (int) $src[1],
                    'height' => (int) $src[2],
                );
            }
        }

        if ($main_entity_id) {
            $schema['mainEntity'] = array('@id' => $main_entity_id);
        }

        /**
         * Filter the WebPage schema node.
         *
         * @since 1.5.0
         * @param array $schema WebPage schema array.
         */
        return apply_filters('thatseoagent_webpage_schema', $schema);
    }

    /**
     * Breadcrumb schema
     *
     * Emits a BreadcrumbList. On the homepage, only a single "Home"
     * crumb is emitted (the current-page entry is omitted to avoid the
     * "Home → Home" duplicate — see GitHub issue #2), which is not a trail
     * and is dropped.
     *
     * The trail, by view:
     *
     *     post                 Home › Category › Post
     *     page                 Home › Parent › Page
     *     custom post type     Home › Archive › Entry   (when it has an archive)
     *     post type archive    Home › Archive
     *     term archive         Home › [Archive ›] Parent term › Term
     *     author archive       Home › Author
     *
     * @since 1.16.0 Covers pages' parents, custom post type archives, term
     *              archives and author archives.
     */
    private static function get_breadcrumb_schema() {
        $crumbs = array(
            array(
                'name' => __('Home', 'thatseoagent'),
                'item' => home_url('/'),
            ),
        );

        if (is_singular()) {
            $post = get_post();

            if (is_singular('post')) {
                $categories = get_the_category();
                if ($categories) {
                    $crumbs[] = array(
                        'name' => $categories[0]->name,
                        'item' => get_category_link($categories[0]->term_id),
                    );
                }
            } elseif (is_post_type_hierarchical($post->post_type)) {
                foreach (array_reverse(get_post_ancestors($post)) as $ancestor_id) {
                    $crumbs[] = array(
                        'name' => get_the_title($ancestor_id),
                        'item' => get_permalink($ancestor_id),
                    );
                }
            }

            $archive = self::post_type_archive_crumb($post->post_type);
            if ($archive) {
                array_splice($crumbs, 1, 0, array($archive));
            }

            if (! is_front_page() && ! is_home()) {
                $crumbs[] = array('name' => get_the_title());
            }
        } elseif (is_post_type_archive()) {
            $crumbs[] = array('name' => post_type_archive_title('', false));
        } elseif (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();

            if ($term instanceof WP_Term) {
                $taxonomy = get_taxonomy($term->taxonomy);

                // A taxonomy that belongs to a single post type with an
                // archive — a product category — sits under that archive.
                if ($taxonomy && 1 === count($taxonomy->object_type)) {
                    $archive = self::post_type_archive_crumb($taxonomy->object_type[0]);
                    if ($archive) {
                        $crumbs[] = $archive;
                    }
                }

                foreach (array_reverse(get_ancestors($term->term_id, $term->taxonomy, 'taxonomy')) as $ancestor_id) {
                    $ancestor = get_term($ancestor_id, $term->taxonomy);
                    if ($ancestor instanceof WP_Term) {
                        $crumbs[] = array(
                            'name' => $ancestor->name,
                            'item' => get_term_link($ancestor),
                        );
                    }
                }

                $crumbs[] = array('name' => $term->name);
            }
        } elseif (is_author()) {
            $author = get_queried_object();
            if ($author instanceof WP_User) {
                $crumbs[] = array('name' => $author->display_name);
            }
        } elseif (is_home() && ! is_front_page()) {
            $crumbs[] = array('name' => get_the_title((int) get_option('page_for_posts')));
        }

        // A single "Home" crumb is not a trail — Google ignores it and it
        // adds a node that says nothing.
        if (count($crumbs) < 2) {
            return null;
        }

        $items = array();
        foreach (array_values($crumbs) as $index => $crumb) {
            $item = array(
                '@type'    => 'ListItem',
                'position' => $index + 1,
                'name'     => wp_strip_all_tags((string) $crumb['name']),
            );

            // The last crumb intentionally omits the item URL per
            // BreadcrumbList best practices; so does any crumb whose link
            // could not be built.
            if (! empty($crumb['item']) && ! is_wp_error($crumb['item'])) {
                $item['item'] = $crumb['item'];
            }

            $items[] = $item;
        }

        $schema = array(
            '@type' => 'BreadcrumbList',
            '@id' => self::current_url() . '#breadcrumb',
            'itemListElement' => $items
        );

        /**
         * Filter the BreadcrumbList schema node.
         *
         * @since 1.5.0
         * @param array $schema Breadcrumb schema array.
         */
        return apply_filters('thatseoagent_breadcrumb_schema', $schema);
    }

    /**
     * The crumb for a custom post type's archive, when it has one.
     *
     * Posts and pages have no archive of their own in the trail: the blog
     * index is not a parent of each post.
     *
     * @since 1.16.0
     * @param string $post_type Post type.
     * @return array{name: string, item: string}|null
     */
    private static function post_type_archive_crumb($post_type) {
        if (in_array($post_type, array('post', 'page'), true)) {
            return null;
        }

        $object = get_post_type_object($post_type);
        $link   = get_post_type_archive_link($post_type);

        if (! $object || ! $object->has_archive || ! $link) {
            return null;
        }

        return array(
            'name' => $object->labels->name,
            'item' => $link,
        );
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
            || (is_home() && ! is_front_page());
    }

    /**
     * The URL of the current view, as the canonical tag states it.
     *
     * @since 1.16.0
     * @return string
     */
    private static function current_url() {
        if (is_singular()) {
            return get_permalink();
        }

        $canonical = ThatSeoAgent_Meta::get_canonical();

        return $canonical ? $canonical : home_url('/');
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
    private static function get_listing_page_schema($type, $breadcrumb = null, $main_entity_id = '') {
        $url = self::current_url();

        $schema = array(
            '@type'      => $type,
            '@id'        => $url . '#webpage',
            'url'        => $url,
            'name'       => wp_strip_all_tags(self::listing_title()),
            'isPartOf'   => array('@id' => home_url('/#website')),
            'inLanguage' => self::get_language(),
        );

        $description = ThatSeoAgent_Meta::get_description();
        if ($description) {
            $schema['description'] = $description;
        }

        if ($breadcrumb) {
            $schema['breadcrumb'] = array('@id' => $breadcrumb['@id']);
        }

        if ($main_entity_id) {
            $schema['mainEntity'] = array('@id' => $main_entity_id);
        }

        /**
         * Filter the CollectionPage or ProfilePage node of a listing.
         *
         * @since 1.16.0
         * @param array  $schema Page node.
         * @param string $type   'CollectionPage' or 'ProfilePage'.
         */
        return apply_filters('thatseoagent_listing_page_schema', $schema, $type);
    }

    /**
     * The title of the listing being viewed.
     *
     * @since 1.16.0
     * @return string
     */
    private static function listing_title() {
        if (is_post_type_archive()) {
            return post_type_archive_title('', false);
        }

        if (is_category() || is_tag() || is_tax()) {
            return single_term_title('', false);
        }

        if (is_author()) {
            $author = get_queried_object();
            return $author instanceof WP_User ? $author->display_name : '';
        }

        if (is_home()) {
            return get_the_title((int) get_option('page_for_posts'));
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
    private static function get_author_person_schema($user_id) {
        $user = $user_id ? get_userdata($user_id) : false;
        if (! $user || '' === $user->display_name) {
            return null;
        }

        $url = get_author_posts_url($user->ID);

        $schema = array(
            '@type' => 'Person',
            '@id'   => $url . '#person',
            'name'  => $user->display_name,
            'url'   => $url,
        );

        if ($user->description) {
            $schema['description'] = wp_strip_all_tags($user->description);
        }

        $job_title = ThatSeoAgent_Author_Profile::job_title($user->ID);
        if ('' !== $job_title) {
            $schema['jobTitle'] = $job_title;
        }

        // The profile's website field — often a personal site or a social
        // profile, which is what sameAs is for — and the profiles elsewhere
        // the author listed. The site's own URL adds nothing.
        $same_as = ThatSeoAgent_Author_Profile::profiles($user->ID);
        $website = $user->user_url ? esc_url_raw($user->user_url) : '';
        if ($website && untrailingslashit($website) !== untrailingslashit(home_url('/'))) {
            array_unshift($same_as, $website);
        }
        $same_as = array_values(array_unique(array_map('untrailingslashit', $same_as)));
        if ($same_as) {
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
        return apply_filters('thatseoagent_author_schema', $schema, $user);
    }

    /**
     * Get publisher/author defaults from options.
     *
     * @since 1.3.0
     * @return array {
     *     @type string $author_name    Fallback author name.
     *     @type string $author_url     Fallback author URL.
     *     @type string $author_type    Schema type: 'Person' or 'Organization'.
     * }
     */
    public static function get_publisher_defaults() {
        $defaults = array(
            'author_name' => get_bloginfo( 'name' ),
            'author_url'  => home_url( '/' ),
            'author_type' => 'Person',
        );

        $saved = get_option( ThatSeoAgent_Default_Author::OPTION_KEY, array() );

        return wp_parse_args( $saved, $defaults );
    }

    /**
     * Build author schema for the current post.
     *
     * Uses post author if available, otherwise falls back to
     * publisher defaults from options.
     *
     * @since 1.3.0
     * @return array Schema.org Person or Organization array.
     */
    private static function get_author_schema() {
        // get_the_author() reads the $authordata global, which is only set
        // once the loop has run — wp_head fires before that, so resolve the
        // author from the post object instead.
        $post      = get_post();
        $author_id = $post ? (int) $post->post_author : 0;

        $author_name = $author_id ? get_the_author_meta( 'display_name', $author_id ) : '';

        if ( $author_name ) {
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
        $saved = get_option( ThatSeoAgent_Default_Author::OPTION_KEY, array() );
        if ( ! empty( $saved['author_name'] ) ) {
            $publisher = self::get_publisher_defaults();

            return array(
                '@type' => $publisher['author_type'],
                'name'  => $publisher['author_name'],
                'url'   => $publisher['author_url'],
            );
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
     * @return array|null FAQPage schema array or null.
     */
    private static function get_faq_schema() {
        $post = get_post();
        if ( ! $post ) {
            return null;
        }

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
            '@id'        => get_permalink() . '#faq',
            'mainEntity' => $entities,
        );
    }
}
