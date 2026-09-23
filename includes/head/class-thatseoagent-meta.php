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
        $image       = self::get_image();
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
        echo '<meta property="og:type" content="' . (is_singular('post') ? 'article' : 'website') . '">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($og_title) . '">' . "\n";

        if ($description) {
            echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
        }

        echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '">' . "\n";

        if ($image) {
            echo '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";

            // Image dimensions help Pinterest and Facebook render correctly
            $image_id = is_singular() ? get_post_thumbnail_id() : 0;
            if ($image_id) {
                $image_meta = wp_get_attachment_image_src($image_id, 'large');
                if ($image_meta) {
                    echo '<meta property="og:image:width" content="' . (int) $image_meta[1] . '">' . "\n";
                    echo '<meta property="og:image:height" content="' . (int) $image_meta[2] . '">' . "\n";
                }
            }
        }

        // Article specific
        if (is_singular('post')) {
            echo '<meta property="article:published_time" content="' . esc_attr(get_the_date('c')) . '">' . "\n";
            echo '<meta property="article:modified_time" content="' . esc_attr(get_the_modified_date('c')) . '">' . "\n";

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
        $twitter_handle = apply_filters('thatseoagent_twitter_handle', '');
        if ($twitter_handle) {
            $twitter_handle = '@' . ltrim($twitter_handle, '@');
            echo '<meta name="twitter:site" content="' . esc_attr($twitter_handle) . '">' . "\n";
        }

        echo '<meta name="twitter:title" content="' . esc_attr($og_title) . '">' . "\n";

        if ($description) {
            echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
        }

        if ($image) {
            echo '<meta name="twitter:image" content="' . esc_url($image) . '">' . "\n";
        }
    }

    /**
     * Get the title for the current request, filterable.
     *
     * Runs wp_get_document_title() through the thatseoagent_title filter so
     * themes and plugins can override the default without having to hook
     * every meta output individually. Separate filters still exist for
     * og:title and the document <title>.
     *
     * @since 1.5.0
     * @return string
     */
    public static function get_title() {
        $title   = wp_get_document_title();
        $context = self::get_context();

        /**
         * Filter the resolved SEO title.
         *
         * @since 1.5.0
         * @param string $title   Default is wp_get_document_title().
         * @param string $context Current page context.
         */
        return apply_filters('thatseoagent_title', $title, $context);
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
     * Get meta description
     *
     * Resolves the description through the default fallback chain, then
     * applies the thatseoagent_description filter (context-aware) for fine
     * control. The legacy thatseoagent_custom_description filter still runs
     * first and short-circuits the chain for backwards compatibility.
     */
    public static function get_description() {
        // Legacy short-circuit filter (kept for backwards compatibility).
        $custom = apply_filters('thatseoagent_custom_description', false);
        if ($custom) {
            $context = self::get_context();
            /** This filter is documented below. */
            return apply_filters('thatseoagent_description', $custom, $context);
        }

        $description = self::resolve_default_description();
        $context     = self::get_context();

        /**
         * Filter the resolved meta description.
         *
         * Unlike thatseoagent_custom_description (which short-circuits the
         * fallback chain), this filter runs after the default resolution
         * and receives a context string so callers can branch on page type
         * without duplicating conditional logic.
         *
         * @since 1.5.0
         * @param string $description Resolved default description.
         * @param string $context     Current page context (see get_context()).
         */
        return apply_filters('thatseoagent_description', $description, $context);
    }

    /**
     * Resolve the default description using the built-in fallback chain.
     *
     * Separated from get_description() so the thatseoagent_description filter
     * always runs on the final value regardless of which branch supplied it.
     *
     * @since 1.5.0
     * @return string
     */
    protected static function resolve_default_description() {
        if (is_singular()) {
            $description = ThatSeoAgent_Description::for_post(get_post());
            if ($description) {
                return $description;
            }
        }

        if (is_home() || is_front_page()) {
            return get_bloginfo('description');
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
            if ($term && $term->description) {
                return wp_strip_all_tags($term->description);
            }
            return sprintf('Browse all %s posts on %s', single_term_title('', false), get_bloginfo('name'));
        }

        if (is_search()) {
            return sprintf('Search results for "%s" on %s', get_search_query(), get_bloginfo('name'));
        }

        if (is_author()) {
            $author = get_queried_object();
            if ($author) {
                return sprintf('Posts by %s on %s', $author->display_name, get_bloginfo('name'));
            }
        }

        return get_bloginfo('description');
    }

    /**
     * Get primary image
     */
    public static function get_image() {
        if (is_singular() && has_post_thumbnail()) {
            return get_the_post_thumbnail_url(get_the_ID(), 'large');
        }

        // Default fallback image
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            return wp_get_attachment_image_url($custom_logo_id, 'full');
        }

        return apply_filters('thatseoagent_default_image', '');
    }

    /**
     * Get full-resolution featured image for Pinterest.
     *
     * Pinterest renders at high DPI so we use 'full' size instead of 'large'.
     * Falls back to null when no featured image exists.
     *
     * @return string|null Full-resolution image URL or null.
     */
    public static function get_pinterest_image() {
        if (is_singular() && has_post_thumbnail()) {
            return get_the_post_thumbnail_url(get_the_ID(), 'full');
        }

        return null;
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
        $categories = get_the_category();
        if (empty($categories)) {
            return null;
        }

        // Skip "Uncategorized" — it adds no value for categorization
        foreach ($categories as $cat) {
            if ($cat->slug !== 'uncategorized') {
                return $cat->name;
            }
        }

        return null;
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
