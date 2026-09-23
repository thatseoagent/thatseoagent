<?php
/**
 * XML Sitemap
 *
 * The renderers return XML strings; exactly one method sends headers, prints
 * and terminates the request. Before 1.9.0 every renderer echoed directly and
 * handle_request() ended in exit, so there was no value to assert on and the
 * only way to test a sitemap was reflection plus output buffering.
 *
 * The set of sitemaps the site publishes is also stated once, in
 * get_sitemap_urls(). It used to be restated in the index renderer and again
 * in Lean_SEO_Abilities, where the copy had drifted: it omitted custom post
 * type sitemaps and page pagination.
 *
 * @package Lean_SEO
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Lean_SEO_Sitemap {

    /**
     * Maximum URLs per sitemap file.
     */
    const PER_PAGE = 1000;

    /**
     * Register the hooks.
     *
     * Routes at init priority 20, before Lean_SEO::maybe_flush_rewrite_rules()
     * at 21, so a flush persists them.
     *
     * @since 1.19.0 Moved out of Lean_SEO.
     */
    public static function register() {
        add_action('init', array(__CLASS__, 'register_routes'), 20);
        add_filter('query_vars', array(__CLASS__, 'query_vars'));
        add_action('template_redirect', array(__CLASS__, 'handle_request'));
        add_filter('redirect_canonical', array(__CLASS__, 'disable_redirect'), 10, 2);
    }

    /**
     * Sitemap query vars.
     *
     * @since 1.19.0 Moved out of Lean_SEO.
     * @param array $vars Query vars.
     * @return array
     */
    public static function query_vars($vars) {
        $vars[] = 'lean_sitemap';
        $vars[] = 'sitemap_page';
        $vars[] = 'lean_cpt';
        return $vars;
    }

    /**
     * Disable canonical redirects for sitemap URLs
     *
     * Prevents WordPress from redirecting sitemap.xml to sitemap.xml/
     * which causes "Sitemap error" in Google Search Console
     *
     * @since 1.19.0 Moved out of Lean_SEO.
     * @param string $redirect_url The redirect URL
     * @param string $requested_url The requested URL
     * @return string|false The redirect URL or false to prevent redirect
     */
    public static function disable_redirect($redirect_url, $requested_url) {
        if (preg_match('/sitemap[^?]*\.xml/', $requested_url)) {
            return false;
        }
        return $redirect_url;
    }

    /**
     * Register rewrite rules
     */
    public static function register_routes() {
        add_rewrite_rule('^sitemap\.xml$', 'index.php?lean_sitemap=index', 'top');
        // Legacy Yoast sitemap URL redirect for backwards compatibility
        add_rewrite_rule('^sitemap_index\.xml$', 'index.php?lean_sitemap=index', 'top');
        add_rewrite_rule('^sitemap-posts\.xml$', 'index.php?lean_sitemap=posts', 'top');
        add_rewrite_rule('^sitemap-posts-([0-9]+)\.xml$', 'index.php?lean_sitemap=posts&sitemap_page=$matches[1]', 'top');
        add_rewrite_rule('^sitemap-pages\.xml$', 'index.php?lean_sitemap=pages', 'top');
        add_rewrite_rule('^sitemap-pages-([0-9]+)\.xml$', 'index.php?lean_sitemap=pages&sitemap_page=$matches[1]', 'top');
        add_rewrite_rule('^sitemap-categories\.xml$', 'index.php?lean_sitemap=categories', 'top');
        add_rewrite_rule('^sitemap-tags\.xml$', 'index.php?lean_sitemap=tags', 'top');

        // Custom post type sitemaps
        foreach (self::get_cpts() as $cpt) {
            add_rewrite_rule('^sitemap-' . $cpt . '\.xml$', 'index.php?lean_sitemap=cpt&lean_cpt=' . $cpt, 'top');
            add_rewrite_rule('^sitemap-' . $cpt . '-([0-9]+)\.xml$', 'index.php?lean_sitemap=cpt&lean_cpt=' . $cpt . '&sitemap_page=$matches[1]', 'top');
        }
    }

    /**
     * Serve a sitemap request.
     *
     * The one place that touches the response: resolves the request from
     * query vars, prints what render() returns, and exits.
     */
    public static function handle_request() {
        $sitemap = get_query_var('lean_sitemap');
        if (!$sitemap) {
            return;
        }

        $xml = self::render($sitemap, array(
            'page'      => self::get_page_number(),
            'post_type' => get_query_var('lean_cpt'),
        ));

        if ('' === $xml) {
            return;
        }

        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex, follow');

        echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput -- built and escaped in render().
        exit;
    }

    /**
     * Render a sitemap as an XML document.
     *
     * @since 1.9.0
     * @param string $which One of: index, posts, pages, categories, tags, cpt.
     * @param array  $args {
     *     @type int    $page      1-based chunk number. Default 1.
     *     @type string $post_type Post type, required when $which is 'cpt'.
     * }
     * @return string XML document, or empty string when $which is unknown.
     */
    public static function render($which, $args = array()) {
        $args = wp_parse_args($args, array(
            'page'      => 1,
            'post_type' => '',
        ));

        $page = max(1, (int) $args['page']);

        switch ($which) {
            case 'index':
                $body = self::render_index();
                break;
            case 'posts':
                $body = self::render_post_type('post', $page);
                break;
            case 'pages':
                $body = self::render_pages($page);
                break;
            case 'categories':
                $body = self::render_taxonomy('category');
                break;
            case 'tags':
                $body = self::render_taxonomy('post_tag');
                break;
            case 'cpt':
                if (!$args['post_type'] || !post_type_exists($args['post_type'])) {
                    return '';
                }
                $body = self::render_post_type($args['post_type'], $page);
                break;
            default:
                return '';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $body;
    }

    /**
     * Every sitemap this site publishes, in index order.
     *
     * The single statement of which sitemaps exist and what they are called.
     * Both the index renderer and the get-sitemap-urls ability read it, so
     * the two can no longer disagree.
     *
     * @since 1.9.0
     * @return array<int, array{loc: string, lastmod: string|null}>
     */
    public static function get_sitemap_urls() {
        $entries = array();

        // Posts
        foreach (self::chunks_for('post') as $i => $offset) {
            $suffix    = null === $offset ? '' : '-' . ($i + 1);
            $entries[] = array(
                'loc'     => home_url("/sitemap-posts{$suffix}.xml"),
                'lastmod' => self::get_latest_modified_date('post', self::PER_PAGE, (int) $offset),
            );
        }

        // Pages
        foreach (self::chunks_for('page') as $i => $offset) {
            $suffix    = null === $offset ? '' : '-' . ($i + 1);
            $entries[] = array(
                'loc'     => home_url("/sitemap-pages{$suffix}.xml"),
                'lastmod' => self::get_latest_modified_date('page', self::PER_PAGE, (int) $offset),
            );
        }

        // Terms
        $entries[] = array(
            'loc'     => home_url('/sitemap-categories.xml'),
            'lastmod' => self::get_term_latest_modified('category'),
        );
        $entries[] = array(
            'loc'     => home_url('/sitemap-tags.xml'),
            'lastmod' => self::get_term_latest_modified('post_tag'),
        );

        // Custom post types
        foreach (self::get_cpts() as $cpt) {
            $counts = wp_count_posts($cpt);
            if (!$counts || $counts->publish < 1) {
                continue;
            }

            foreach (self::chunks_for($cpt) as $i => $offset) {
                $suffix    = null === $offset ? '' : '-' . ($i + 1);
                $entries[] = array(
                    'loc'     => home_url("/sitemap-{$cpt}{$suffix}.xml"),
                    'lastmod' => self::get_latest_modified_date($cpt, self::PER_PAGE, (int) $offset),
                );
            }
        }

        /**
         * Filter the sitemaps listed in the index.
         *
         * @since 1.9.0
         * @param array $entries List of ['loc' => string, 'lastmod' => string|null].
         */
        return apply_filters('lean_seo_sitemap_entries', $entries);
    }

    /**
     * Offsets for each chunk of a post type, or [null] when it fits in one file.
     *
     * @param string $post_type Post type.
     * @return array<int, int|null>
     */
    private static function chunks_for($post_type) {
        $counts = wp_count_posts($post_type);
        $count  = $counts ? (int) $counts->publish : 0;
        $needed = (int) ceil($count / self::PER_PAGE);

        if ($needed <= 1) {
            return array(null);
        }

        $offsets = array();
        for ($i = 0; $i < $needed; $i++) {
            $offsets[] = $i * self::PER_PAGE;
        }

        return $offsets;
    }

    /**
     * Public, non-builtin post types.
     *
     * @return array<int, string>
     */
    private static function get_cpts() {
        return get_post_types(array('public' => true, '_builtin' => false), 'names');
    }

    /**
     * Current sitemap page number, clamped to a sane value.
     *
     * Without clamping, /sitemap-posts-0.xml yields a negative offset.
     *
     * @since 1.7.1
     * @return int
     */
    private static function get_page_number() {
        return max(1, absint(get_query_var('sitemap_page', 1)));
    }

    /**
     * Render the sitemap index.
     *
     * @return string
     */
    private static function render_index() {
        $xml = '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach (self::get_sitemap_urls() as $entry) {
            $xml .= '  <sitemap>' . "\n";
            $xml .= '    <loc>' . esc_url($entry['loc']) . '</loc>' . "\n";
            if (!empty($entry['lastmod'])) {
                $xml .= '    <lastmod>' . esc_html($entry['lastmod']) . '</lastmod>' . "\n";
            }
            $xml .= '  </sitemap>' . "\n";
        }

        // Back-compat: themes echo extra <sitemap> entries from this action.
        // Prefer the lean_seo_sitemap_entries filter, which needs no buffering.
        ob_start();
        do_action('lean_seo_sitemap_index');
        $xml .= ob_get_clean();

        return $xml . '</sitemapindex>';
    }

    /**
     * Render one chunk of a post type.
     *
     * @param string $post_type Post type.
     * @param int    $page      1-based chunk number.
     * @return string
     */
    private static function render_post_type($post_type, $page = 1) {
        $posts = get_posts(array(
            'post_type'              => $post_type,
            'post_status'            => 'publish',
            'posts_per_page'         => self::PER_PAGE,
            'offset'                 => (max(1, (int) $page) - 1) * self::PER_PAGE,
            'orderby'                => 'modified',
            'order'                  => 'DESC',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ));

        $urls = array();
        foreach ($posts as $post) {
            $urls[] = array(
                'loc'     => get_permalink($post),
                'lastmod' => get_the_modified_date('c', $post),
            );
        }

        return self::urlset($urls);
    }

    /**
     * Render one chunk of the pages sitemap.
     *
     * The home URL leads the first chunk; the page assigned as the static
     * front page is skipped so the URL is not listed twice.
     *
     * @param int $page 1-based chunk number.
     * @return string
     */
    private static function render_pages($page = 1) {
        $page          = max(1, (int) $page);
        $front_page_id = 'page' === get_option('show_on_front') ? (int) get_option('page_on_front') : 0;

        $urls = array();

        if (1 === $page) {
            $urls[] = array('loc' => home_url('/'), 'lastmod' => null);
        }

        $pages = get_posts(array(
            'post_type'              => 'page',
            'post_status'            => 'publish',
            'posts_per_page'         => self::PER_PAGE,
            'offset'                 => ($page - 1) * self::PER_PAGE,
            'orderby'                => 'ID',
            'order'                  => 'ASC',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ));

        foreach ($pages as $p) {
            if ($front_page_id && $front_page_id === (int) $p->ID) {
                continue;
            }

            $urls[] = array(
                'loc'     => get_permalink($p),
                'lastmod' => get_the_modified_date('c', $p),
            );
        }

        return self::urlset($urls);
    }

    /**
     * Render a taxonomy's terms.
     *
     * @param string $taxonomy Taxonomy name.
     * @return string
     */
    private static function render_taxonomy($taxonomy) {
        $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => true));

        if (is_wp_error($terms) || empty($terms)) {
            return self::urlset(array());
        }

        // One query for every term's lastmod. Asking per term made this an
        // N+1: a site with 200 categories ran 200 queries per request.
        $lastmods = self::get_term_lastmods($taxonomy);

        $urls = array();
        foreach ($terms as $term) {
            $link = get_term_link($term);
            if (is_wp_error($link)) {
                continue;
            }

            $urls[] = array(
                'loc'     => $link,
                'lastmod' => isset($lastmods[$term->term_id]) ? $lastmods[$term->term_id] : null,
            );
        }

        return self::urlset($urls);
    }

    /**
     * Wrap URL entries in a urlset element.
     *
     * @param array<int, array{loc: string, lastmod: string|null}> $urls URL entries.
     * @return string
     */
    private static function urlset($urls) {
        $xml = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . esc_url($url['loc']) . '</loc>' . "\n";
            if (!empty($url['lastmod'])) {
                $xml .= '    <lastmod>' . esc_html($url['lastmod']) . '</lastmod>' . "\n";
            }
            $xml .= '  </url>' . "\n";
        }

        return $xml . '</urlset>';
    }

    /**
     * Get latest modified date for a post type, optionally within a page range
     */
    private static function get_latest_modified_date($post_type = 'post', $limit = 1, $offset = 0) {
        $posts = get_posts(array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'offset' => $offset,
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ));

        if ($posts) {
            return get_the_modified_date('c', $posts[0]);
        }

        return current_time('c');
    }

    /**
     * The latest modified date of a published post in each term of a taxonomy.
     *
     * One grouped query for the whole taxonomy, memoised per request. The
     * previous shape asked per term, which made rendering a taxonomy sitemap
     * an N+1.
     *
     * @since 1.10.2
     * @param string $taxonomy Taxonomy name.
     * @return array<int, string> term_id => ISO 8601 date.
     */
    private static function get_term_lastmods($taxonomy) {
        return Lean_SEO_Memo::remember('term_lastmods', $taxonomy, function () use ($taxonomy) {
            return self::query_term_lastmods($taxonomy);
        });
    }

    /**
     * The query behind get_term_lastmods(), without memoisation.
     *
     * @since 1.20.0 Split from get_term_lastmods().
     * @param string $taxonomy Taxonomy name.
     * @return array<int, string> term_id => ISO 8601 date.
     */
    private static function query_term_lastmods($taxonomy) {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        // Direct query on purpose: this replaces one WP_Query per term with a
        // single grouped read. Results are memoised for the request,
        // and a sitemap is fetched rarely enough that a persistent cache would
        // mostly serve stale lastmod values.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT tt.term_id AS term_id, MAX(p.post_modified_gmt) AS lastmod
                 FROM {$wpdb->term_relationships} tr
                 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                 INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
                 WHERE tt.taxonomy = %s
                   AND p.post_status = 'publish'
                 GROUP BY tt.term_id",
                $taxonomy
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        $map = array();
        foreach ((array) $rows as $row) {
            if (!empty($row->lastmod)) {
                $map[(int) $row->term_id] = get_date_from_gmt($row->lastmod, 'c');
            }
        }

        return $map;
    }

    /**
     * The most recent modification across a whole taxonomy.
     *
     * Reads the map built by get_term_lastmods(), so the index costs no extra
     * query beyond the one that renders the taxonomy.
     *
     * @param string $taxonomy Taxonomy name.
     * @return string|null
     */
    private static function get_term_latest_modified($taxonomy) {
        $lastmods = self::get_term_lastmods($taxonomy);

        if (empty($lastmods)) {
            return null;
        }

        return max($lastmods);
    }
}
