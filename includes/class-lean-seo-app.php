<?php
/**
 * The Lean SEO admin screen.
 *
 * One top-level page, `admin.php?page=lean-seo`, with its own sidebar and a
 * view per section: `&view=products`, `&view=settings`, …. Each view is a
 * server-rendered template in includes/admin/views/, so every section works
 * without JavaScript; Alpine.js adds interactivity on top of that markup.
 *
 * Styling comes from assets/build/admin.css — Tailwind, compiled from
 * assets/src/admin.css — loaded on this screen only.
 *
 * @package Lean_SEO
 * @since 1.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lean_SEO_App {

    /**
     * Menu slug of the screen.
     */
    const SLUG = 'lean-seo';

    /**
     * User meta holding the chosen edition. Absent means Day.
     */
    const THEME_META = 'lean_seo_admin_theme';

    /**
     * Register the hooks.
     *
     * @since 1.17.0
     */
    public static function register() {
        add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
        add_action( 'admin_init', array( __CLASS__, 'redirect_legacy_urls' ) );
    }

    /**
     * Add the top-level menu page.
     *
     * No submenus: the screen has its own navigation.
     *
     * @since 1.17.0
     */
    public static function add_page() {
        add_menu_page(
            __( 'Lean SEO', 'lean-seo' ),
            __( 'Lean SEO', 'lean-seo' ),
            'manage_options',
            self::SLUG,
            array( __CLASS__, 'render' ),
            'dashicons-search',
            81
        );

        // The Product schema report had its own page before 1.17.0. WordPress
        // refuses an unregistered page before admin_init runs, so the old
        // slug stays registered — hidden, with no menu entry — and redirects.
        $legacy = add_submenu_page( '', __( 'Product schema', 'lean-seo' ), '', 'manage_options', 'lean-seo-products', '__return_null' );
        if ( $legacy ) {
            add_action( 'load-' . $legacy, array( __CLASS__, 'redirect_legacy_products_url' ) );
        }
    }

    /**
     * Send the pre-1.17.0 Product schema page to its view.
     *
     * @since 1.17.0
     */
    public static function redirect_legacy_products_url() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect.
        $paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 0;

        wp_safe_redirect( self::url( 'products', $paged ? array( 'paged' => $paged ) : array() ) );
        exit;
    }

    /**
     * The sections of the screen, in navigation order.
     *
     * @since 1.17.0
     * @return array<string, array{label: string, title: string, subtitle: string, icon: string}>
     */
    public static function views() {
        return array(
            'dashboard' => array(
                'label'    => __( 'Overview', 'lean-seo' ),
                'title'    => __( 'Overview', 'lean-seo' ),
                'subtitle' => '',
                'icon'     => 'dashboard',
            ),
            'products'  => array(
                'label'    => __( 'Products', 'lean-seo' ),
                'title'    => __( 'Products', 'lean-seo' ),
                'subtitle' => __( 'How each product in the catalog is described to search engines, and what is missing.', 'lean-seo' ),
                'icon'     => 'package',
            ),
            'audit'     => array(
                'label'    => __( 'Content check', 'lean-seo' ),
                'title'    => __( 'Content check', 'lean-seo' ),
                'subtitle' => __( 'Titles, descriptions, headings, links and images, page by page.', 'lean-seo' ),
                'icon'     => 'audit',
            ),
            'llms'      => array(
                'label'    => __( 'AI index', 'lean-seo' ),
                'title'    => __( 'AI index', 'lean-seo' ),
                'subtitle' => __( 'llms.txt: the list of your pages that AI assistants can read.', 'lean-seo' ),
                'icon'     => 'file',
            ),
            'settings'  => array(
                'label'    => __( 'Settings', 'lean-seo' ),
                'title'    => __( 'Settings', 'lean-seo' ),
                'subtitle' => __( 'Who the site is, the homepage, the product catalog and integrations.', 'lean-seo' ),
                'icon'     => 'settings',
            ),
        );
    }

    /**
     * The view being displayed.
     *
     * @since 1.17.0
     * @return string
     */
    public static function current_view() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation only.
        $view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'dashboard';

        return array_key_exists( $view, self::views() ) ? $view : 'dashboard';
    }

    /**
     * URL of a view.
     *
     * @since 1.17.0
     * @param string $view View key.
     * @param array  $args Extra query arguments.
     * @return string
     */
    public static function url( $view = 'dashboard', array $args = array() ) {
        $query = array( 'page' => self::SLUG );
        if ( 'dashboard' !== $view ) {
            $query['view'] = $view;
        }

        return add_query_arg( array_merge( $query, $args ), admin_url( 'admin.php' ) );
    }

    /**
     * Send the settings URL from before 1.15.0 to its view.
     *
     * @since 1.17.0
     */
    public static function redirect_legacy_urls() {
        global $pagenow;

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only redirects.
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

        if ( 'options-general.php' === $pagenow && self::SLUG === $page ) {
            wp_safe_redirect( self::url( 'settings' ) );
            exit;
        }

        // phpcs:enable WordPress.Security.NonceVerification.Recommended
    }

    /**
     * Whether the current admin screen is this one.
     *
     * @since 1.17.0
     * @return bool
     */
    public static function is_screen() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        return $screen && Lean_SEO_Admin::PAGE_HOOK === $screen->id;
    }

    /**
     * Mark the body so the stylesheet can adjust the admin chrome.
     *
     * @since 1.17.0
     * @param string $classes Space-separated body classes.
     * @return string
     */
    public static function body_class( $classes ) {
        if ( ! self::is_screen() ) {
            return $classes;
        }

        return $classes . ' lean-seo-screen lean-seo-theme-' . self::theme();
    }

    /**
     * The color theme: 'light' (the default) or 'dark'.
     *
     * @since 1.17.0
     * @return string
     */
    public static function theme() {
        return 'dark' === get_user_meta( get_current_user_id(), self::THEME_META, true ) ? 'dark' : 'light';
    }

    /**
     * Load the stylesheet and scripts on this screen.
     *
     * Alpine.js ships inside the plugin (assets/vendor, pinned by
     * package.json): the screen makes no request outside the site. The
     * screen's own components register on `alpine:init`, so their script
     * must run before Alpine's; both are deferred, and deferred scripts run
     * in the order they appear.
     *
     * @since 1.17.0
     * @since 1.18.0 Loads Alpine.js and the screen's components.
     * @param string $hook_suffix Current admin page.
     */
    public static function enqueue( $hook_suffix ) {
        if ( Lean_SEO_Admin::PAGE_HOOK !== $hook_suffix ) {
            return;
        }

        wp_enqueue_style( 'lean-seo-app', LEAN_SEO_PLUGIN_URL . 'assets/build/admin.css', array(), self::asset_version( 'assets/build/admin.css' ) );

        wp_enqueue_script(
            'lean-seo-app',
            LEAN_SEO_PLUGIN_URL . 'assets/admin/app.js',
            array( 'wp-api-fetch' ),
            self::asset_version( 'assets/admin/app.js' ),
            array(
                'in_footer' => true,
                'strategy'  => 'defer',
            )
        );

        wp_enqueue_script(
            'lean-seo-alpine',
            LEAN_SEO_PLUGIN_URL . 'assets/vendor/alpine.min.js',
            array( 'lean-seo-app' ),
            '3.17.4',
            array(
                'in_footer' => true,
                'strategy'  => 'defer',
            )
        );

        wp_add_inline_script( 'lean-seo-app', 'window.leanSeo = ' . wp_json_encode( self::script_data() ) . ';', 'before' );
    }

    /**
     * What the scripts start from: the state the page was rendered with, so
     * nothing is fetched on load, plus the words they show.
     *
     * @since 1.18.0
     * @return array
     */
    private static function script_data() {
        return array(
            'theme'    => self::theme(),
            'bulletin' => self::bulletin_for_js(),
            'i18n'     => array(
                'saving'        => __( 'Saving…', 'lean-seo' ),
                'saved'         => __( 'Saved. The changes are live.', 'lean-seo' ),
                'unsaved'       => __( 'You have unsaved changes.', 'lean-seo' ),
                'leave'         => __( 'You have unsaved changes. Leave anyway?', 'lean-seo' ),
                'saveFailed'    => __( 'The settings were not saved.', 'lean-seo' ),
                'indexnowKept'  => __( 'The IndexNow key was not changed: it must be 8 to 128 letters, numbers or dashes.', 'lean-seo' ),
                'themeFailed'   => __( 'The edition could not be saved; it will reset on the next visit.', 'lean-seo' ),
                'checkFailed'   => __( 'The check stopped because of an error.', 'lean-seo' ),
                'offline'       => __( 'The site could not be reached. Check your connection and try again.', 'lean-seo' ),
                'regenerated'   => __( 'llms.txt was rebuilt.', 'lean-seo' ),
                'regenFailed'   => __( 'llms.txt could not be rebuilt.', 'lean-seo' ),
            ),
        );
    }

    /**
     * The bulletin as the sidebar's script reads it.
     *
     * Carries the class names each state paints with, so the script never
     * needs its own copy of the level-to-color mapping.
     *
     * @since 1.18.0
     * @return array{level: string, name: string, headline: string, square: string, observations: array}
     */
    public static function bulletin_for_js() {
        $bulletin = self::bulletin();
        $levels   = self::levels();

        $observations = array();
        foreach ( $bulletin['observations'] as $observation ) {
            $observations[] = array(
                'key'   => $observation['key'],
                'label' => $observation['label'],
                'value' => $observation['value'],
                'state' => $observation['state'],
                'bar'   => self::state_bar( $observation['state'] ),
            );
        }

        return array(
            'level'        => $bulletin['level'],
            'name'         => $levels[ $bulletin['level'] ]['name'],
            'headline'     => $bulletin['headline'],
            'square'       => $levels[ $bulletin['level'] ]['square'],
            'observations' => $observations,
        );
    }

    /**
     * Version string of a plugin asset: the plugin version plus the file's
     * modification time, so a rebuild is never served from the browser cache.
     *
     * @since 1.18.0
     * @param string $relative Path from the plugin root.
     * @return string
     */
    private static function asset_version( $relative ) {
        $path = LEAN_SEO_PLUGIN_DIR . $relative;

        return file_exists( $path ) ? LEAN_SEO_VERSION . '.' . filemtime( $path ) : LEAN_SEO_VERSION;
    }

    /**
     * Render the screen.
     *
     * @since 1.17.0
     */
    public static function render() {
        $views   = self::views();
        $current = self::current_view();
        $view    = $views[ $current ];

        include LEAN_SEO_PLUGIN_DIR . 'includes/admin/views/layout.php';
    }

    /**
     * Include a view's template.
     *
     * @since 1.17.0
     * @param string $name Template name, without the extension.
     * @param array  $vars Variables for the template.
     */
    public static function template( $name, array $vars = array() ) {
        // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Template variables, keys controlled by the caller.
        extract( $vars, EXTR_SKIP );

        include LEAN_SEO_PLUGIN_DIR . 'includes/admin/views/' . $name . '.php';
    }

    /**
     * Warning levels, mildest first.
     *
     * The European weather-warning scale: people who know nothing about SEO
     * already know that yellow means "be aware" and red means "act now".
     *
     * @since 1.17.0
     * @return array<string, array{rank: int, name: string, square: string, field: string}>
     */
    public static function levels() {
        return array(
            'clear'  => array(
                'rank'   => 0,
                'name'   => __( 'No warnings', 'lean-seo' ),
                'square' => 'bg-level-clear',
                'field'  => 'bg-level-clear text-on-clear',
            ),
            'yellow' => array(
                'rank'   => 1,
                'name'   => __( 'Yellow warning', 'lean-seo' ),
                'square' => 'bg-level-yellow',
                'field'  => 'bg-level-yellow text-on-yellow',
            ),
            'orange' => array(
                'rank'   => 2,
                'name'   => __( 'Orange warning', 'lean-seo' ),
                'square' => 'bg-level-orange',
                'field'  => 'bg-level-orange text-on-orange',
            ),
            'red'    => array(
                'rank'   => 3,
                'name'   => __( 'Red warning', 'lean-seo' ),
                'square' => 'bg-level-red',
                'field'  => 'bg-level-red text-on-red',
            ),
        );
    }

    /**
     * The site's bulletin: its warning level, the warnings in force, and one
     * observation per check.
     *
     * Computed from the site's actual state on every load, never from stored
     * "done" flags, so a warning cannot read as lifted after it came back.
     * Optional features that are not set up are observations, never warnings:
     * a site without a product catalog is not doing anything wrong.
     *
     * Memoised per request: the sidebar and the dashboard both read it.
     *
     * @since 1.17.0
     * @return array{level: string, headline: string, summary: string, action: array|null, warnings: array, observations: array, observed: int}
     */
    public static function bulletin() {
        static $bulletin = null;

        if ( null !== $bulletin ) {
            return $bulletin;
        }

        $warnings     = array();
        $observations = array();
        $other        = Lean_SEO_Compat::other_seo_plugin();
        $homepage     = Lean_SEO_Homepage::get_settings();

        // Indexing.
        $public = (bool) get_option( 'blog_public' );
        $observations[] = self::observation( 'indexing', __( 'Indexing', 'lean-seo' ), $public ? 'ok' : 'red', $public ? __( 'Allowed', 'lean-seo' ) : __( 'Blocked', 'lean-seo' ) );
        if ( ! $public ) {
            $warnings[] = self::warning(
                'red',
                __( 'Search engines are asked to stay away', 'lean-seo' ),
                __( '"Discourage search engines from indexing this site" is ticked, so the site will not appear in search results.', 'lean-seo' ),
                __( 'Open Reading settings', 'lean-seo' ),
                admin_url( 'options-reading.php' )
            );
        }

        // Permalinks.
        $pretty = (bool) get_option( 'permalink_structure' );
        $observations[] = self::observation( 'permalinks', __( 'Permalinks', 'lean-seo' ), $pretty ? 'ok' : 'red', $pretty ? __( 'Readable', 'lean-seo' ) : __( 'Plain', 'lean-seo' ) );
        if ( ! $pretty ) {
            $warnings[] = self::warning(
                'red',
                __( 'Sitemaps and llms.txt are offline', 'lean-seo' ),
                __( 'Plain permalinks (?p=123) leave no address for the sitemap, llms.txt or the Markdown pages.', 'lean-seo' ),
                __( 'Choose a permalink structure', 'lean-seo' ),
                admin_url( 'options-permalink.php' )
            );
        }

        // Another SEO plugin.
        $observations[] = self::observation( 'plugins', __( 'SEO plugins', 'lean-seo' ), '' === $other ? 'ok' : 'orange', '' === $other ? __( 'Only this one', 'lean-seo' ) : $other );
        if ( '' !== $other ) {
            $warnings[] = self::warning(
                'orange',
                /* translators: %s: name of the other SEO plugin. */
                sprintf( __( '%s is active, so Lean SEO is standing aside', 'lean-seo' ), $other ),
                __( 'Two SEO plugins would print every tag twice. Import its data with wp lean-seo import, then deactivate one of them.', 'lean-seo' ),
                __( 'Open Plugins', 'lean-seo' ),
                admin_url( 'plugins.php' )
            );
        }

        // Identity. Saving the settings stores the identity option even when
        // nothing in it was filled in, so "configured" alone proves nothing:
        // it counts once there is something to recognize the site by.
        $identity_settings = Lean_SEO_Identity::get_settings();
        $identity          = Lean_SEO_Identity::is_configured()
            && ( $identity_settings['logo_id'] || '' !== $identity_settings['description'] || ! empty( $identity_settings['social'] ) );
        $observations[] = self::observation( 'identity', __( 'Identity', 'lean-seo' ), $identity ? 'ok' : 'yellow', $identity ? __( 'Set', 'lean-seo' ) : __( 'Missing', 'lean-seo' ) );
        if ( ! $identity ) {
            $warnings[] = self::warning(
                'yellow',
                __( 'Search engines do not know who runs the site', 'lean-seo' ),
                __( 'Say whether the site is a person or an organization, and add its logo and social profiles.', 'lean-seo' ),
                __( 'Set up the identity', 'lean-seo' ),
                self::url( 'settings' ) . '#lean_seo_identity_section'
            );
        }

        // Homepage description.
        $described = '' !== $homepage['description'];
        $observations[] = self::observation( 'homepage', __( 'Homepage', 'lean-seo' ), $described ? 'ok' : 'yellow', $described ? __( 'Described', 'lean-seo' ) : __( 'Automatic', 'lean-seo' ) );
        if ( ! $described ) {
            $warnings[] = self::warning(
                'yellow',
                __( 'The homepage description is taken from the page text', 'lean-seo' ),
                __( 'It is the first thing people read about the site in search results; a sentence written for them works better.', 'lean-seo' ),
                __( 'Write it', 'lean-seo' ),
                self::url( 'settings' ) . '#lean_seo_homepage_section'
            );
        }

        // Product catalog.
        if ( empty( Lean_SEO_Product::post_types() ) ) {
            $observations[] = self::observation( 'products', __( 'Products', 'lean-seo' ), 'off', __( 'No catalog', 'lean-seo' ) );
        } else {
            $summary = Lean_SEO_Product_Admin::summary();
            $state   = $summary['error'] ? 'orange' : ( $summary['warning'] ? 'yellow' : 'ok' );

            $observations[] = self::observation(
                'products',
                __( 'Products', 'lean-seo' ),
                $state,
                /* translators: 1: complete products, 2: all products. */
                sprintf( __( '%1$d of %2$d complete', 'lean-seo' ), $summary['ok'] + $summary['info'], $summary['total'] )
            );

            if ( $summary['error'] ) {
                $warnings[] = self::warning(
                    'orange',
                    /* translators: %d: number of products. */
                    sprintf( _n( '%d product is not marked up as a product', '%d products are not marked up as products', $summary['error'], 'lean-seo' ), $summary['error'] ),
                    __( 'Without a title there is no Product markup at all.', 'lean-seo' ),
                    __( 'See the products', 'lean-seo' ),
                    self::url( 'products' )
                );
            }

            if ( $summary['warning'] ) {
                $warnings[] = self::warning(
                    'yellow',
                    /* translators: %d: number of products. */
                    sprintf( _n( '%d product has gaps in its details', '%d products have gaps in their details', $summary['warning'], 'lean-seo' ), $summary['warning'] ),
                    __( 'Missing specifications, images or brand make the product harder to find and to compare.', 'lean-seo' ),
                    __( 'See the products', 'lean-seo' ),
                    self::url( 'products' )
                );
            }
        }

        // IndexNow.
        $indexnow = '' !== Lean_SEO_IndexNow::get_api_key();
        $observations[] = self::observation( 'indexnow', __( 'IndexNow', 'lean-seo' ), $indexnow ? 'ok' : 'off', $indexnow ? __( 'On', 'lean-seo' ) : __( 'Off', 'lean-seo' ) );

        $levels = self::levels();
        usort(
            $warnings,
            function ( $a, $b ) use ( $levels ) {
                return $levels[ $b['level'] ]['rank'] - $levels[ $a['level'] ]['rank'];
            }
        );

        $level = $warnings ? $warnings[0]['level'] : 'clear';

        $headlines = array(
            'clear'  => __( 'Clear. Nothing needs you right now.', 'lean-seo' ),
            'yellow' => __( 'Mostly fine, with room to do better.', 'lean-seo' ),
            'orange' => __( 'Something is holding the site back.', 'lean-seo' ),
            'red'    => __( 'Search engines cannot see this site properly.', 'lean-seo' ),
        );

        if ( $warnings ) {
            $summary_text = count( $warnings ) > 1
                /* translators: 1: the most serious warning, 2: number of other warnings. */
                ? sprintf( _n( '%1$s — and %2$d more below.', '%1$s — and %2$d more below.', count( $warnings ) - 1, 'lean-seo' ), $warnings[0]['title'], count( $warnings ) - 1 )
                : $warnings[0]['title'] . '.';
        } else {
            $summary_text = __( 'Every check passed. The meta tags, schema and sitemaps are being published as they should.', 'lean-seo' );
        }

        $bulletin = array(
            'level'        => $level,
            'headline'     => $headlines[ $level ],
            'summary'      => $summary_text,
            'action'       => $warnings ? $warnings[0]['action'] : null,
            'warnings'     => $warnings,
            'observations' => $observations,
            'observed'     => time(),
        );

        return $bulletin;
    }

    /**
     * One observation of the bulletin.
     *
     * @since 1.17.0
     * @param string $key   Machine key.
     * @param string $label Short label.
     * @param string $state 'ok', 'off', or a warning level.
     * @param string $value What was observed, in a word or two.
     * @return array{key: string, label: string, state: string, value: string}
     */
    private static function observation( $key, $label, $state, $value ) {
        return array(
            'key'   => $key,
            'label' => $label,
            'state' => $state,
            'value' => $value,
        );
    }

    /**
     * One warning of the bulletin.
     *
     * @since 1.17.0
     * @param string $level  Warning level.
     * @param string $title  What is wrong, in plain words.
     * @param string $detail Why it matters.
     * @param string $label  Action label.
     * @param string $url    Where to fix it.
     * @return array{level: string, title: string, detail: string, action: array{label: string, url: string}}
     */
    private static function warning( $level, $title, $detail, $label, $url ) {
        return array(
            'level'  => $level,
            'title'  => $title,
            'detail' => $detail,
            'action' => array(
                'label' => $label,
                'url'   => $url,
            ),
        );
    }

    /**
     * Classes of an observation's marker bar.
     *
     * @since 1.17.0
     * @param string $state 'ok', 'off', or a warning level.
     * @return string
     */
    public static function state_bar( $state ) {
        $bars = array(
            'ok'     => 'bg-ink',
            'off'    => 'bg-rule',
            'yellow' => 'bg-level-yellow',
            'orange' => 'bg-level-orange',
            'red'    => 'bg-level-red',
        );

        return isset( $bars[ $state ] ) ? $bars[ $state ] : $bars['off'];
    }

    /**
     * Counts for the dashboard cards.
     *
     * @since 1.17.0
     * @return array{published: int, descriptions: int, titles: int, products: int}
     */
    public static function stats() {
        $stats = array(
            'published'    => 0,
            'descriptions' => 0,
            'titles'       => 0,
            'products'     => 0,
        );

        $product_types = Lean_SEO_Product::post_types();

        foreach ( Lean_SEO_Admin::get_meta_box_post_types() as $post_type ) {
            $published = (int) wp_count_posts( $post_type )->publish;

            $stats['published']    += $published;
            $stats['descriptions'] += $published - Lean_SEO_Post_Seo::count_missing( $post_type, 'description' );
            $stats['titles']       += $published - Lean_SEO_Post_Seo::count_missing( $post_type, 'title' );

            if ( in_array( $post_type, $product_types, true ) ) {
                $stats['products'] += $published;
            }
        }

        return $stats;
    }

    /**
     * The public files the plugin serves, and whether each is live.
     *
     * @since 1.17.0
     * @return array<int, array{label: string, url: string, active: bool, note: string}>
     */
    public static function resources() {
        $outputs   = Lean_SEO_Compat::outputs_enabled();
        $permalink = (bool) get_option( 'permalink_structure' );
        $front     = (int) get_option( 'page_on_front' );
        $markdown  = $front ? Lean_SEO_Markdown_Endpoint::url_for( $front ) : '';
        $off       = __( 'Off while another SEO plugin is active', 'lean-seo' );

        return array(
            array(
                'label'  => __( 'XML sitemap', 'lean-seo' ),
                'url'    => home_url( '/sitemap.xml' ),
                'active' => $outputs && $permalink,
                'note'   => $outputs ? '' : $off,
            ),
            array(
                'label'  => __( 'llms.txt', 'lean-seo' ),
                'url'    => home_url( '/llms.txt' ),
                'active' => $outputs && $permalink && Lean_SEO_Llms::is_enabled(),
                'note'   => $outputs ? ( Lean_SEO_Llms::is_enabled() ? '' : __( 'Switched off in the settings', 'lean-seo' ) ) : $off,
            ),
            array(
                'label'  => __( 'robots.txt', 'lean-seo' ),
                'url'    => home_url( '/robots.txt' ),
                'active' => true,
                'note'   => '',
            ),
            array(
                'label'  => __( 'Markdown of the homepage', 'lean-seo' ),
                'url'    => $markdown,
                'active' => '' !== $markdown,
                'note'   => '' !== $markdown ? '' : __( 'Needs a static front page and readable permalinks', 'lean-seo' ),
            ),
        );
    }

    /**
     * The warning level a product validation status maps to.
     *
     * @since 1.17.0
     * @param string $status 'error', 'warning', 'info' or 'ok'.
     * @return string
     */
    public static function status_level( $status ) {
        $map = array(
            'error'   => 'orange',
            'warning' => 'yellow',
            'info'    => 'clear',
            'ok'      => 'clear',
        );

        return isset( $map[ $status ] ) ? $map[ $status ] : 'clear';
    }

    /**
     * An inline SVG icon.
     *
     * Paths from Lucide (https://lucide.dev, ISC license), inlined so the
     * screen loads no icon font and no extra request.
     *
     * @since 1.17.0
     * @param string $name  Icon name.
     * @param string $class Classes for the <svg>.
     * @return string
     */
    public static function icon( $name, $class = 'size-4' ) {
        $icons = array(
            'dashboard' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
            'package'   => '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
            'audit'     => '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/>',
            'file'      => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
            'settings'  => '<line x1="21" x2="14" y1="4" y2="4"/><line x1="10" x2="3" y1="4" y2="4"/><line x1="21" x2="12" y1="12" y2="12"/><line x1="8" x2="3" y1="12" y2="12"/><line x1="21" x2="16" y1="20" y2="20"/><line x1="12" x2="3" y1="20" y2="20"/><line x1="14" x2="14" y1="2" y2="6"/><line x1="8" x2="8" y1="10" y2="14"/><line x1="16" x2="16" y1="18" y2="22"/>',
            'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>',
            'moon'      => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
            'check'     => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
            'circle'    => '<circle cx="12" cy="12" r="10"/>',
            'alert'     => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
            'external'  => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
            'arrow'     => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
            'refresh'   => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>',
            'search'    => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
            'list'      => '<path d="m3 17 2 2 4-4"/><path d="m3 7 2 2 4-4"/><path d="M13 6h8"/><path d="M13 12h8"/><path d="M13 18h8"/>',
            'map'       => '<path d="M14.106 5.553a2 2 0 0 0 1.788 0l3.659-1.83A1 1 0 0 1 21 4.619v12.764a1 1 0 0 1-.553.894l-4.553 2.277a2 2 0 0 1-1.788 0l-4.212-2.106a2 2 0 0 0-1.788 0l-3.659 1.83A1 1 0 0 1 3 19.381V6.618a1 1 0 0 1 .553-.894l4.553-2.277a2 2 0 0 1 1.788 0z"/><path d="M15 5.764v15"/><path d="M9 3.236v15"/>',
            'bot'       => '<path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/>',
            'pen'       => '<path d="M12 20h9"/><path d="M16.376 3.622a1 1 0 0 1 3.002 3.002L7.368 18.635a2 2 0 0 1-.855.506l-2.872.838a.5.5 0 0 1-.62-.62l.838-2.872a2 2 0 0 1 .506-.854z"/>',
            'info'      => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
        );

        if ( ! isset( $icons[ $name ] ) ) {
            return '';
        }

        return sprintf(
            '<svg class="%s" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
            esc_attr( $class ),
            $icons[ $name ]
        );
    }

    /**
     * Echo an icon. The markup is the fixed SVG above.
     *
     * @since 1.17.0
     * @param string $name  Icon name.
     * @param string $class Classes for the <svg>.
     */
    public static function the_icon( $name, $class = 'size-4' ) {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG markup, class attribute escaped in icon().
        echo self::icon( $name, $class );
    }
}
