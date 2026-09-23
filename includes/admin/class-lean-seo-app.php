<?php
/**
 * The Lean SEO admin screen.
 *
 * One top-level page, `admin.php?page=lean-seo`, with its own sidebar and a
 * view per section: `&view=products`, `&view=settings`, …. Each view is a
 * server-rendered template in includes/admin/views/, so every section works
 * without JavaScript; Alpine.js adds interactivity on top of that markup.
 *
 * What the screen shows about the site comes from Lean_SEO_Bulletin and
 * Lean_SEO_Readings; this class decides where it goes and how it is painted.
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
     * Hook suffix of the screen, as passed to admin_enqueue_scripts and as
     * the id of its WP_Screen.
     *
     * @since 1.15.0 As Lean_SEO_Admin::PAGE_HOOK.
     */
    const PAGE_HOOK = 'toplevel_page_lean-seo';

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

        return $screen && Lean_SEO_App::PAGE_HOOK === $screen->id;
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
        if ( Lean_SEO_App::PAGE_HOOK !== $hook_suffix ) {
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
            'bulletin' => Lean_SEO_Bulletin::for_js(),
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
     * Classes each warning level paints with: its small square, and the
     * full field of the condition panels.
     *
     * @since 1.20.0 Split from Lean_SEO_App::levels(), whose names and
     *               order moved to Lean_SEO_Bulletin::levels().
     * @param string $level Warning level.
     * @return array{square: string, field: string}
     */
    public static function level_classes( $level ) {
        $classes = array(
            'clear'  => array(
                'square' => 'bg-level-clear',
                'field'  => 'bg-level-clear text-on-clear',
            ),
            'yellow' => array(
                'square' => 'bg-level-yellow',
                'field'  => 'bg-level-yellow text-on-yellow',
            ),
            'orange' => array(
                'square' => 'bg-level-orange',
                'field'  => 'bg-level-orange text-on-orange',
            ),
            'red'    => array(
                'square' => 'bg-level-red',
                'field'  => 'bg-level-red text-on-red',
            ),
        );

        return isset( $classes[ $level ] ) ? $classes[ $level ] : $classes['clear'];
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
     * Where a bulletin action leads.
     *
     * The bulletin names the place; only the screen knows where each one
     * lives.
     *
     * @since 1.20.0
     * @param array{label: string, destination: string} $action A warning's action.
     * @return string
     */
    public static function action_url( array $action ) {
        switch ( $action['destination'] ) {
            case 'reading':
                return admin_url( 'options-reading.php' );
            case 'permalinks':
                return admin_url( 'options-permalink.php' );
            case 'plugins':
                return admin_url( 'plugins.php' );
            case 'identity':
                return self::url( 'settings' ) . '#lean_seo_identity_section';
            case 'homepage':
                return self::url( 'settings' ) . '#lean_seo_homepage_section';
            case 'products':
                return self::url( 'products' );
        }

        return self::url();
    }
}
