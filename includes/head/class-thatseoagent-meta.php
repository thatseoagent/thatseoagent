<?php
/**
 * Meta Tags Handler
 *
 * @package ThatSeoAgent
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ThatSeoAgent_Meta {

    /**
     * Register the hooks.
     *
     * Priority 1, after the Markdown alternate link registered at the same
     * priority by ThatSeoAgent_Markdown_Endpoint: the head reads link, meta
     * tags, canonical, then the JSON-LD at 2.
     *
     * @since 1.19.0 Moved out of ThatSeoAgent.
     */
    public static function register() {
        add_action('wp_head', array(__CLASS__, 'output'), 1);
        add_action('wp_head', array(__CLASS__, 'output_canonical'), 1);

        // Replaced by output_canonical().
        remove_action('wp_head', 'rel_canonical');
    }

    /**
     * Output meta tags
     */
    public static function output() {
        $description = self::get_description();
        $picture     = ThatSeoAgent_Image::for_request();
        $image       = $picture ? $picture['url'] : '';
        $url         = self::get_url();
        $context     = self::get_context();

        /**
         * Filter the og:site_name value.
         *
         * @since 1.5.0
         * @param string $site_name Default is get_bloginfo('name').
         */
        $site_name = apply_filters('thatseoagent_og_site_name', get_bloginfo('name'));

        /**
         * Filter the og:locale value.
         *
         * @since 1.5.0
         * @param string $locale Default is get_locale().
         */
        $locale = apply_filters('thatseoagent_og_locale', get_locale());

        $title = self::get_title();

        /**
         * Filter the og:title value specifically (overrides thatseoagent_title for OG).
         *
         * @since 1.5.0
         * @param string $og_title Default is the filtered title.
         * @param string $context  Current page context (see get_context()).
         */
        $og_title = apply_filters('thatseoagent_og_title', $title, $context);

        // Basic meta description
        if ($description) {
            echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        }

        // Open Graph
        echo '<meta property="og:locale" content="' . esc_attr($locale) . '">' . "\n";
        echo '<meta property="og:type" content="' . esc_attr(self::get_og_type()) . '">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($og_title) . '">' . "\n";

        if ($description) {
            echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
        }

        echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '">' . "\n";

        if ($image) {
            echo '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";

            // Dimensions let Facebook, LinkedIn and Pinterest lay the
            // preview out before they have fetched the image.
            if ($picture['width'] && $picture['height']) {
                echo '<meta property="og:image:width" content="' . (int) $picture['width'] . '">' . "\n";
                echo '<meta property="og:image:height" content="' . (int) $picture['height'] . '">' . "\n";
            }
            if ('' !== $picture['type']) {
                echo '<meta property="og:image:type" content="' . esc_attr($picture['type']) . '">' . "\n";
            }
            if ('' !== $picture['alt']) {
                echo '<meta property="og:image:alt" content="' . esc_attr($picture['alt']) . '">' . "\n";
            }
        }

        // Article specific: dated content, not pages or products, whose
        // publication date tells a reader nothing.
        if (self::is_dated_article()) {
            echo '<meta property="article:published_time" content="' . esc_attr(get_the_date('c')) . '">' . "\n";

            // Only when it changed after it went out.
            if ((int) get_post_modified_time('U', true) > (int) get_post_time('U', true)) {
                echo '<meta property="article:modified_time" content="' . esc_attr(get_the_modified_date('c')) . '">' . "\n";
            }

            // article:section — primary category for rich pin categorization
            $primary_category = self::get_primary_category();
            if ($primary_category) {
                echo '<meta property="article:section" content="' . esc_attr($primary_category) . '">' . "\n";
            }

            // Pinterest-optimized tags for rich pins
            $pinterest_image = self::get_pinterest_image();
            if ($pinterest_image) {
                echo '<meta property="og:pin:media" content="' . esc_url($pinterest_image) . '">' . "\n";
            }
            if ($description) {
                echo '<meta property="og:pin:description" content="' . esc_attr($description) . '">' . "\n";
            }
        }

        // Twitter Card
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";

        /**
         * Filter the @handle used for twitter:site meta.
         *
         * Return a string like '@username' (leading @ is normalized). Return
         * empty string to omit the tag entirely. Default is empty.
         *
         * @since 1.5.0
         * @param string $handle Default empty string.
         */
        $twitter_handle = apply_filters('thatseoagent_twitter_handle', ThatSeoAgent_Identity::twitter_handle());
        if ($twitter_handle) {
            $twitter_handle = '@' . ltrim($twitter_handle, '@');
            echo '<meta name="twitter:site" content="' . esc_attr($twitter_handle) . '">' . "\n";
        }

        /**
         * Filter whether twitter:title, twitter:description and
         * twitter:image repeat what Open Graph already says.
         *
         * X reads og:title, og:description and og:image when its own tags
         * are absent, so by default they are left out: the same three values
         * printed twice.
         *
         * @since 2.4.0
         * @param bool $repeat Default false.
         */
        if (apply_filters('thatseoagent_twitter_repeat_open_graph', false)) {
            echo '<meta name="twitter:title" content="' . esc_attr($og_title) . '">' . "\n";

            if ($description) {
                echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
            }

            if ($image) {
                echo '<meta name="twitter:image" content="' . esc_url($image) . '">' . "\n";
            }
        }

        // X has no fallback for the image's description.
        if ($image && '' !== $picture['alt']) {
            echo '<meta name="twitter:image:alt" content="' . esc_attr($picture['alt']) . '">' . "\n";
        }
    }

    /**
     * The og:type of the current view.
     *
     * `product` for a catalog entry or a WooCommerce product, `article`
     * for any other single page but the front page, `profile` for an
     * author's archive, `website` for the rest.
     *
     * @since 2.4.0
     * @since 2.10.0 `product` for products.
     * @return string
     */
    public static function get_og_type() {
        if (is_front_page() || is_home()) {
            $type = 'website';
        } elseif (is_singular() && self::is_product(get_queried_object())) {
            $type = 'product';
        } elseif (is_singular()) {
            $type = 'article';
        } elseif (is_author()) {
            $type = 'profile';
        } else {
            $type = 'website';
        }

        /**
         * Filter the og:type of the current view.
         *
         * @since 2.4.0
         * @param string $type    'product', 'article', 'profile' or 'website'.
         * @param string $context Current page context (see get_context()).
         */
        return (string) apply_filters('thatseoagent_og_type', $type, self::get_context());
    }

    /**
     * Whether the current view is dated content: a single post of a type
     * that is neither hierarchical (pages) nor a product.
     *
     * @since 2.4.0
     * @since 2.10.0 Not WooCommerce's products either.
     * @return bool
     */
    public static function is_dated_article() {
        if (! is_singular() || is_front_page()) {
            return false;
        }

        $post = get_queried_object();
        if (! $post instanceof WP_Post || is_post_type_hierarchical($post->post_type) || self::is_product($post)) {
            return false;
        }

        return true;
    }

    /**
     * Whether a post is a product: a catalog entry, or a WooCommerce
     * product.
     *
     * @since 2.10.0
     * @param mixed $post Post object, or the queried object.
     * @return bool
     */
    private static function is_product($post) {
        return $post instanceof WP_Post
            && (ThatSeoAgent_Product::is_product($post) || ThatSeoAgent_WooCommerce::is_product($post));
    }

    /**
     * The title of the current request: the <title> tag's.
     *
     * A post's search title (ThatSeoAgent_Title::for_post()), or on a
     * listing the title WordPress built, both through the thatseoagent_title
     * filter.
     *
     * @since 1.5.0
     * @since 2.7.0 The same as the <title>.
     * @return string
     */
    public static function get_title() {
        $post = self::current_post();

        return $post ? ThatSeoAgent_Title::for_post($post) : wp_get_document_title();
    }

    /**
     * The post the current request shows: a single page, or the blog's
     * posts page.
     *
     * @since 2.7.0
     * @return WP_Post|null Null on any other listing.
     */
    public static function current_post() {
        if (is_singular()) {
            $post = get_queried_object();

            return $post instanceof WP_Post ? $post : null;
        }

        if (is_home() && ! is_front_page()) {
            $post = get_post((int) get_option('page_for_posts'));

            return $post instanceof WP_Post ? $post : null;
        }

        return null;
    }

    /**
     * Get a symbolic context string for the current request.
     *
     * Used as the $context argument for thatseoagent_title / thatseoagent_description
     * filters so callers can branch on page type without re-running conditional
     * tags themselves.
     *
     * @since 1.5.0
     * @return string One of: 'home' | 'single' | 'archive' | 'taxonomy' |
     *                'search' | 'author' | 'date' | '404' | 'other'.
     */
    public static function get_context() {
        if (is_home() || is_front_page()) {
            return 'home';
        }
        if (is_singular()) {
            return 'single';
        }
        if (is_post_type_archive()) {
            return 'archive';
        }
        if (is_category() || is_tag() || is_tax()) {
            return 'taxonomy';
        }
        if (is_search()) {
            return 'search';
        }
        if (is_author()) {
            return 'author';
        }
        if (is_date()) {
            return 'date';
        }
        if (is_404()) {
            return '404';
        }
        return 'other';
    }

    /**
     * Output canonical URL
     *
     * @since 2.2.0 None on a page kept out of search indexes: a canonical
     *              asks for the URL to be indexed, the noindex asks the
     *              opposite, and search engines resolve the contradiction
     *              their own way.
     */
    public static function output_canonical() {
        if (ThatSeoAgent_Indexing::is_noindex()) {
            return;
        }

        $canonical = self::get_canonical();
        if ($canonical) {
            echo '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";
        }
    }

    /**
     * The description of the current request.
     *
     * A post's is ThatSeoAgent_Description::for_post()'s; a listing's comes
     * from the chain below, through the same thatseoagent_description
     * filter.
     *
     * @since 1.0.0
     * @since 2.7.0 A post's is the one it gets everywhere; the legacy
     *              thatseoagent_custom_description filter is gone.
     * @return string
     */
    public static function get_description() {
        $post = self::current_post();
        if ($post) {
            return ThatSeoAgent_Description::for_post($post);
        }

        /** This filter is documented in includes/content/class-thatseoagent-description.php */
        return (string) apply_filters('thatseoagent_description', self::listing_description(), self::get_context(), null);
    }

    /**
     * The description of a listing, before the filter.
     *
     * @since 1.5.0
     * @since 2.7.0 Listings only; a homepage that lists the latest posts
     *              says the homepage's own description first.
     * @return string
     */
    protected static function listing_description() {
        if (is_front_page()) {
            $home = ThatSeoAgent_Homepage::expand_variables(ThatSeoAgent_Homepage::get_settings()['description']);

            return '' !== $home ? $home : get_bloginfo('description');
        }

        if (is_post_type_archive()) {
            $object = get_queried_object();
            if ($object instanceof WP_Post_Type) {
                if ($object->description) {
                    return wp_strip_all_tags($object->description);
                }

                /* translators: 1: post type plural label, e.g. "Products", 2: site name. */
                return sprintf(__('Browse all %1$s on %2$s', 'thatseoagent'), $object->labels->name, get_bloginfo('name'));
            }
        }

        if (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term instanceof WP_Term) {
                return ThatSeoAgent_Term_Seo::description($term);
            }
        }

        if (is_search()) {
            /* translators: 1: search terms, 2: site name. */
            return sprintf(__('Search results for "%1$s" on %2$s', 'thatseoagent'), get_search_query(), get_bloginfo('name'));
        }

        if (is_author()) {
            $author = get_queried_object();
            if ($author) {
                /* translators: 1: author name, 2: site name. */
                return sprintf(__('Posts by %1$s on %2$s', 'thatseoagent'), $author->display_name, get_bloginfo('name'));
            }
        }

        return get_bloginfo('description');
    }

    /**
     * The URL of the image the current view is shared with.
     *
     * @since 2.4.0 Through ThatSeoAgent_Image.
     * @since 2.7.1 The post's sharing image, the default sharing image,
     *              then the theme logo.
     * @return string
     */
    public static function get_image() {
        $image = ThatSeoAgent_Image::for_request();

        return $image ? $image['url'] : '';
    }

    /**
     * The image Pinterest pins: the post's own, at the schema's size.
     *
     * Pinterest renders at high DPI, so 'full' by default rather than the
     * lighter one shared elsewhere; never the site's image or logo.
     *
     * @since 2.7.0 The post's own image, not only the featured one.
     * @return string|null Image URL or null.
     */
    public static function get_pinterest_image() {
        $post  = is_singular() ? get_queried_object() : null;
        $image = $post instanceof WP_Post ? ThatSeoAgent_Image::own($post, 'schema') : null;

        return $image ? $image['url'] : null;
    }

    /**
     * Get the primary category name for the current post.
     *
     * Returns the first category assigned to the post (WordPress stores the
     * primary/default category first). Used for article:section meta tag.
     *
     * @return string|null Category name or null.
     */
    public static function get_primary_category() {
        $post = get_queried_object();
        $term = $post instanceof WP_Post ? ThatSeoAgent_Primary_Term::named($post) : null;

        return $term ? $term->name : null;
    }

    /**
     * The URL og:url states: the canonical, else the requested URL.
     *
     * @since 2.2.0 The canonical first: the requested URL carried whatever
     *              query string the visitor arrived with.
     * @return string
     */
    public static function get_url() {
        $canonical = self::get_canonical();

        return $canonical ? $canonical : home_url(add_query_arg(array()));
    }

    /**
     * Get canonical URL
     *
     * The URL of the page being viewed, pagination included: page 2 of a
     * listing, or of a post split with <!--nextpage-->, is its own canonical.
     *
     * @since 2.2.0 Pagination, and date archives.
     * @return string|null
     */
    public static function get_canonical() {
        if (is_singular()) {
            // Core's own answer: the permalink, plus the <!--nextpage--> page
            // and the comment page, through the get_canonical_url filter.
            $canonical = wp_get_canonical_url();
            return $canonical ? $canonical : get_permalink();
        }

        $base = self::get_canonical_base();

        return $base ? ThatSeoAgent_Pagination::listing_url($base, ThatSeoAgent_Pagination::current()) : null;
    }

    /**
     * The canonical URL of the first page of the current listing.
     *
     * @since 2.2.0 Split from get_canonical().
     * @return string|null Null on a view with no canonical of its own.
     */
    public static function get_canonical_base() {
        if (is_front_page()) {
            return home_url('/');
        }

        // The blog index on its own page ("Posts page" in Settings →
        // Reading). Before 1.16.0 it declared the homepage as its canonical,
        // telling search engines the blog was a duplicate of the front page.
        if (is_home()) {
            $posts_page = (int) get_option('page_for_posts');
            return $posts_page ? get_permalink($posts_page) : home_url('/');
        }

        if (is_post_type_archive()) {
            $post_type = get_query_var('post_type');
            $link      = get_post_type_archive_link(is_array($post_type) ? reset($post_type) : $post_type);
            return $link ? $link : null;
        }

        if (is_category() || is_tag() || is_tax()) {
            $link = get_term_link(get_queried_object());
            return is_wp_error($link) ? null : $link;
        }

        if (is_search()) {
            return get_search_link();
        }

        if (is_author()) {
            return get_author_posts_url(get_queried_object_id());
        }

        if (is_day()) {
            return get_day_link(get_query_var('year'), get_query_var('monthnum'), get_query_var('day'));
        }

        if (is_month()) {
            return get_month_link(get_query_var('year'), get_query_var('monthnum'));
        }

        if (is_year()) {
            return get_year_link(get_query_var('year'));
        }

        return null;
    }
}
