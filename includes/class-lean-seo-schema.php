<?php
/**
 * Schema/JSON-LD Handler
 *
 * @package Lean_SEO
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Lean_SEO_Schema {

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

        // Article schema for posts
        if (is_singular('post')) {
            $schema[] = self::get_webpage_schema();
            $schema[] = self::get_article_schema();
            $schema[] = self::get_breadcrumb_schema();
            $faq_schema = self::get_faq_schema();
            if ($faq_schema) {
                $schema[] = $faq_schema;
            }
        }

        // Page schema
        if (is_singular('page')) {
            $schema[] = self::get_webpage_schema();
            $schema[] = self::get_breadcrumb_schema();
        }

        // Filter empty values
        $schema = array_filter($schema);

        /**
         * Filter the complete JSON-LD @graph before output.
         *
         * Allows adding, removing, or reordering schema nodes. Runs after
         * individual node filters (lean_seo_website_schema, etc.) so the
         * graph this filter receives contains each node's final shape.
         *
         * @since 1.5.0
         * @param array $graph Array of schema node arrays.
         */
        $schema = apply_filters('lean_seo_schema_graph', $schema);

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
         * Lean_SEO_Identity_Applier sets this to 'person' when the Site
         * Identity settings say so. When it resolves to 'person' and a Person
         * node exists, the Organization node is omitted and the Person becomes
         * the publisher of every Article.
         *
         * @since 1.8.0
         * @param string $entity Either 'organization' (default) or 'person'.
         */
        $entity = apply_filters('lean_seo_primary_entity', 'organization');

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
     * Website schema
     */
    private static function get_website_schema() {
        $schema = array(
            '@type' => 'WebSite',
            '@id' => home_url('/#website'),
            'url' => home_url('/'),
            'name' => get_bloginfo('name'),
            'description' => get_bloginfo('description'),
            'potentialAction' => array(
                '@type' => 'SearchAction',
                'target' => array(
                    '@type' => 'EntryPoint',
                    'urlTemplate' => home_url('/?s={search_term_string}')
                ),
                'query-input' => 'required name=search_term_string'
            )
        );

        /**
         * Filter the WebSite schema node.
         *
         * @since 1.5.0
         * @param array $schema WebSite schema array.
         */
        return apply_filters('lean_seo_website_schema', $schema);
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
        return apply_filters('lean_seo_organization_schema', $schema);
    }

    /**
     * Person schema (opt-in via filter).
     *
     * Not emitted by default. Sites representing an individual (personal
     * blogs, author sites) can return a populated Person node via the
     * lean_seo_person_schema filter and it will be added to the graph.
     *
     * @since 1.5.0
     * @return array|null Person schema array, or null to omit.
     */
    private static function get_person_schema() {
        // Memoised: output() and get_publisher_id() both ask, and the filter
        // chain behind this reaches the options table and the media library.
        static $resolved = false;
        static $cache    = null;

        if ( $resolved ) {
            return $cache;
        }

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
        $cache    = apply_filters('lean_seo_person_schema', $default);
        $resolved = true;

        return $cache;
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
            'wordCount' => self::count_words($post->post_content),
            'publisher' => array('@id' => self::get_publisher_id()),
            'author' => self::get_author_schema(),
        );

        // Add image
        if (has_post_thumbnail()) {
            $thumb_id = get_post_thumbnail_id(get_the_ID());
            $thumb_url = get_the_post_thumbnail_url(get_the_ID(), 'large');
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
        $description = Lean_SEO_Meta::get_description();
        if ($description) {
            $schema['description'] = $description;
        }

        return $schema;
    }

    /**
     * Count words in content in a multibyte-safe way.
     *
     * str_word_count() is ASCII-only and miscounts accented languages.
     *
     * @since 1.7.1
     * @param string $content Raw post content.
     * @return int
     */
    private static function count_words( $content ) {
        $text = wp_strip_all_tags( strip_shortcodes( $content ) );

        return (int) preg_match_all( '/[\p{L}\p{N}]+/u', $text );
    }

    /**
     * WebPage schema
     */
    private static function get_webpage_schema() {
        $schema = array(
            '@type' => 'WebPage',
            '@id' => get_permalink() . '#webpage',
            'url' => get_permalink(),
            'name' => get_the_title(),
            'isPartOf' => array('@id' => home_url('/#website')),
            'datePublished' => get_the_date('c'),
            'dateModified' => get_the_modified_date('c'),
        );

        /**
         * Filter the WebPage schema node.
         *
         * @since 1.5.0
         * @param array $schema WebPage schema array.
         */
        return apply_filters('lean_seo_webpage_schema', $schema);
    }

    /**
     * Breadcrumb schema
     *
     * Emits a BreadcrumbList. On the homepage, only a single "Home"
     * crumb is emitted (the current-page entry is omitted to avoid the
     * "Home → Home" duplicate — see GitHub issue #2). On singular views,
     * the home crumb is followed by an optional category (posts) and
     * the current page title.
     */
    private static function get_breadcrumb_schema() {
        $items = array();
        $position = 1;

        // Home crumb
        $items[] = array(
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => 'Home',
            'item' => home_url('/')
        );

        // Category for posts
        if (is_singular('post')) {
            $categories = get_the_category();
            if ($categories) {
                $cat = $categories[0];
                $items[] = array(
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => $cat->name,
                    'item' => get_category_link($cat->term_id)
                );
            }
        }

        // Current page (singular only — the homepage is already "Home").
        // Last crumb intentionally omits the item URL per BreadcrumbList
        // best practices.
        if (is_singular() && ! is_front_page() && ! is_home()) {
            $items[] = array(
                '@type' => 'ListItem',
                'position' => $position,
                'name' => get_the_title()
            );
        }

        $schema = array(
            '@type' => 'BreadcrumbList',
            '@id' => ((is_front_page() || is_home()) ? home_url('/') : get_permalink()) . '#breadcrumb',
            'itemListElement' => $items
        );

        /**
         * Filter the BreadcrumbList schema node.
         *
         * @since 1.5.0
         * @param array $schema Breadcrumb schema array.
         */
        return apply_filters('lean_seo_breadcrumb_schema', $schema);
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

        $saved = get_option( 'lean_seo_schema', array() );

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
            return array(
                '@type' => 'Person',
                'name'  => $author_name,
                'url'   => get_author_posts_url( $author_id ),
            );
        }

        $publisher = self::get_publisher_defaults();

        return array(
            '@type' => $publisher['author_type'],
            'name'  => $publisher['author_name'],
            'url'   => $publisher['author_url'],
        );
    }

    /**
     * FAQPage schema, built from the post's own headings.
     *
     * The reading of the content lives in Lean_SEO_FAQ; this method only
     * shapes the result into a schema node.
     *
     * @since 1.1.0
     * @since 1.9.0 Extraction moved to Lean_SEO_FAQ.
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
        if ( ! apply_filters( 'lean_seo_faq_schema_enabled', true, $post->ID ) ) {
            return null;
        }

        $qa_pairs = Lean_SEO_FAQ::pairs_for_post( $post );

        /**
         * Filter the extracted FAQ Q&A pairs before schema output.
         *
         * @param array $qa_pairs Array of ['question' => string, 'answer' => string].
         * @param int   $post_id  The current post ID.
         */
        $qa_pairs = apply_filters( 'lean_seo_faq_pairs', $qa_pairs, $post->ID );

        if ( count( $qa_pairs ) < Lean_SEO_FAQ::MIN_PAIRS ) {
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
