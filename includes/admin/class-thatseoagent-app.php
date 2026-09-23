<?php
/**
 * The ThatSeoAgent admin screen.
 *
 * One top-level page, `admin.php?page=thatseoagent`, with its own sidebar and a
 * view per section: `&view=products`, `&view=settings`, …. Each view is a
 * server-rendered template in includes/admin/views/, so every section works
 * without JavaScript; Alpine.js adds interactivity on top of that markup.
 *
 * What the screen shows about the site comes from ThatSeoAgent_Bulletin and
 * ThatSeoAgent_Readings; this class decides where it goes and how it is painted.
 *
 * Styling comes from assets/build/admin.css — Tailwind, compiled from
 * assets/src/admin.css — loaded on this screen only.
 *
 * @package ThatSeoAgent
 * @since 1.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_App {

    /**
     * Menu slug of the screen.
     */
    const SLUG = 'thatseoagent';

    /**
     * Hook suffix of the screen, as passed to admin_enqueue_scripts and as
     * the id of its WP_Screen.
     *
     * @since 1.15.0 As ThatSeoAgent_Admin::PAGE_HOOK.
     */
    const PAGE_HOOK = 'toplevel_page_thatseoagent';

    /**
     * User meta holding the chosen edition. Absent means Day.
     */
    const THEME_META = 'thatseoagent_admin_theme';

    /**
     * Register the hooks.
     *
     * @since 1.17.0
     */
    public static function register() {
        add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
        add_action( 'load-' . self::PAGE_HOOK, array( __CLASS__, 'maybe_send_view' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
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
            __( 'ThatSeoAgent', 'thatseoagent' ),
            __( 'ThatSeoAgent', 'thatseoagent' ),
            'manage_options',
            self::SLUG,
            array( __CLASS__, 'render' ),
            'dashicons-search',
            81
        );
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
                'label'    => __( 'Overview', 'thatseoagent' ),
                'title'    => __( 'Overview', 'thatseoagent' ),
                'subtitle' => '',
                'icon'     => 'dashboard',
            ),
            'products'  => array(
                'label'    => __( 'Products', 'thatseoagent' ),
                'title'    => __( 'Products', 'thatseoagent' ),
                'subtitle' => __( 'How each product in the catalog is described to search engines, and what is missing.', 'thatseoagent' ),
                'icon'     => 'package',
            ),
            'audit'     => array(
                'label'    => __( 'Content check', 'thatseoagent' ),
                'title'    => __( 'Content check', 'thatseoagent' ),
                'subtitle' => __( 'Titles, descriptions, headings, links and images, page by page.', 'thatseoagent' ),
                'icon'     => 'audit',
            ),
            'crawlers'  => array(
                'label'    => __( 'AI crawlers', 'thatseoagent' ),
                'title'    => __( 'AI crawlers', 'thatseoagent' ),
                'subtitle' => __( 'Which AI crawlers can read the site right now, and why.', 'thatseoagent' ),
                'icon'     => 'bot',
            ),
            'llms'      => array(
                'label'    => __( 'AI index', 'thatseoagent' ),
                'title'    => __( 'AI index', 'thatseoagent' ),
                'subtitle' => __( 'llms.txt: the list of your pages that AI assistants can read.', 'thatseoagent' ),
                'icon'     => 'file',
            ),
            'settings'  => array(
                'label'    => __( 'Settings', 'thatseoagent' ),
                'title'    => __( 'Settings', 'thatseoagent' ),
                'subtitle' => __( 'Who the site is, the homepage, the product catalog and integrations.', 'thatseoagent' ),
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
     * Whether the current admin screen is this one.
     *
     * @since 1.17.0
     * @return bool
     */
    public static function is_screen() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        return $screen && ThatSeoAgent_App::PAGE_HOOK === $screen->id;
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

        return $classes . ' thatseoagent-screen thatseoagent-theme-' . self::theme();
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
        if ( ThatSeoAgent_App::PAGE_HOOK !== $hook_suffix ) {
            return;
        }

        wp_enqueue_style( 'thatseoagent-app', THATSEOAGENT_PLUGIN_URL . 'assets/build/admin.css', array(), self::asset_version( 'assets/build/admin.css' ) );

        wp_enqueue_script(
            'thatseoagent-app',
            THATSEOAGENT_PLUGIN_URL . 'assets/admin/app.js',
            array( 'wp-api-fetch' ),
            self::asset_version( 'assets/admin/app.js' ),
            array(
                'in_footer' => true,
                'strategy'  => 'defer',
            )
        );

        wp_enqueue_script(
            'thatseoagent-alpine',
            THATSEOAGENT_PLUGIN_URL . 'assets/vendor/alpine.min.js',
            array( 'thatseoagent-app' ),
            '3.17.4',
            array(
                'in_footer' => true,
                'strategy'  => 'defer',
            )
        );

        wp_add_inline_script( 'thatseoagent-app', 'window.thatSeoAgent = ' . wp_json_encode( self::script_data() ) . ';', 'before' );
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
            // Compared with each view the router fetches: see maybe_send_view().
            'version'  => self::client_version(),
            'theme'    => self::theme(),
            'bulletin' => ThatSeoAgent_Bulletin::for_js(),
            'i18n'     => array(
                'saving'        => __( 'Saving…', 'thatseoagent' ),
                'saved'         => __( 'Saved. The changes are live.', 'thatseoagent' ),
                'unsaved'       => __( 'You have unsaved changes.', 'thatseoagent' ),
                'leave'         => __( 'You have unsaved changes. Leave anyway?', 'thatseoagent' ),
                'saveFailed'    => __( 'The settings were not saved.', 'thatseoagent' ),
                'indexnowKept'  => __( 'The IndexNow key was not changed: it must be 8 to 128 letters, numbers or dashes.', 'thatseoagent' ),
                'themeFailed'   => __( 'The edition could not be saved; it will reset on the next visit.', 'thatseoagent' ),
                'checkFailed'   => __( 'The check stopped because of an error.', 'thatseoagent' ),
                'offline'       => __( 'The site could not be reached. Check your connection and try again.', 'thatseoagent' ),
                'regenerated'   => __( 'llms.txt was rebuilt.', 'thatseoagent' ),
                'regenFailed'   => __( 'llms.txt could not be rebuilt.', 'thatseoagent' ),
                'probeDone'     => __( 'Access checked.', 'thatseoagent' ),
                'probeFailed'   => __( 'The access check could not run.', 'thatseoagent' ),
                'probeNoAnswer' => __( 'No answer: the site did not respond to this crawler', 'thatseoagent' ),
                /* translators: %d: HTTP status code. */
                'probeReached'  => __( 'Reached the site (%d)', 'thatseoagent' ),
                /* translators: %d: HTTP status code. */
                'probeTurnedAway' => __( 'Turned away by the server (%d)', 'thatseoagent' ),
                'mdChecked'       => __( 'Markdown checked.', 'thatseoagent' ),
                'mdCheckFailed'   => __( 'The Markdown check could not run.', 'thatseoagent' ),
                'mdNoAnswer'      => __( 'no answer', 'thatseoagent' ),
                'mdRuleAdded'     => __( 'The rule is at the top of .htaccess.', 'thatseoagent' ),
                'mdRuleRemoved'   => __( 'The rule was removed from .htaccess.', 'thatseoagent' ),
                'mdRuleFailed'    => __( '.htaccess was not changed.', 'thatseoagent' ),
                'mdRuleNotApache' => __( 'This server does not apply .htaccess rewrite rules, so here the rule does nothing. The lines are here to paste at the very top of .htaccess on an Apache server, before a page cache that serves from that file.', 'thatseoagent' ),
                'mdRuleMissing'   => __( 'Not in .htaccess. Without a page cache it changes nothing; add it before installing one, and it keeps requests asking for Markdown away from the cached copies.', 'thatseoagent' ),
                'mdRuleNotWritable' => __( 'WordPress cannot write to the file: paste the lines at its very top by hand.', 'thatseoagent' ),
                /* translators: %s: page cache plugin name. */
                'mdRuleBelow'     => __( 'In .htaccess, but below the rules of %s, which run first: write it again to move it to the top.', 'thatseoagent' ),
                'mdRuleOk'        => __( 'At the top of .htaccess: requests asking for Markdown reach WordPress before any page cache served from this file.', 'thatseoagent' ),
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
        $path = THATSEOAGENT_PLUGIN_DIR . $relative;

        return file_exists( $path ) ? THATSEOAGENT_VERSION . '.' . filemtime( $path ) : THATSEOAGENT_VERSION;
    }

    /**
     * Answer a view request from the screen's own navigation.
     *
     * The screen moves between its views without reloading (app.js): a link
     * to another view is fetched with the X-ThatSeoAgent-View header, and
     * the answer is the screen alone — no admin menu, bar or footer — with
     * the document title and the bulletin, so the sidebar can follow. Runs
     * on the page's load hook: admin_init has already registered the
     * settings, and what a link does on arrival (the review link of new
     * content types) has already been done.
     *
     * @since 2.6.0
     */
    public static function maybe_send_view() {
        if ( empty( $_SERVER['HTTP_X_THATSEOAGENT_VIEW'] ) || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $views = self::views();
        $view  = $views[ self::current_view() ];

        ob_start();
        self::render();
        $html = ob_get_clean();

        nocache_headers();
        wp_send_json(
            array(
                'version'  => self::client_version(),
                'html'     => $html,
                /* translators: 1: view title, 2: site name. */
                'title'    => sprintf( __( '%1$s ‹ %2$s — WordPress', 'thatseoagent' ), $view['title'], get_bloginfo( 'name' ) ),
                'bulletin' => ThatSeoAgent_Bulletin::for_js(),
            )
        );
    }

    /**
     * The version of the screen's script and styles as served now.
     *
     * The router compares it with the one the open page loaded: a page
     * left open across an update of the plugin runs the old script, which
     * would misread what the new server answers, so a view fetched with a
     * different version is loaded as a whole page instead.
     *
     * @since 2.6.0
     * @return string
     */
    private static function client_version() {
        return self::asset_version( 'assets/admin/app.js' ) . '/' . self::asset_version( 'assets/build/admin.css' );
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

        include THATSEOAGENT_PLUGIN_DIR . 'includes/admin/views/layout.php';
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

        include THATSEOAGENT_PLUGIN_DIR . 'includes/admin/views/' . $name . '.php';
    }

    /**
     * Classes each warning level paints with: its small square, and the
     * full field of the condition panels.
     *
     * @since 1.20.0 Split from ThatSeoAgent_App::levels(), whose names and
     *               order moved to ThatSeoAgent_Bulletin::levels().
     * @param string $level Warning level.
     * @return array{square: string, field: string}
     */
    public static function level_classes( $level ) {
        $classes = array(
            'clear'  => array(
                'square' => 'bg-level-clear',
                'field'  => 'border border-rule bg-sheet text-ink',
                'dot'    => 'bg-on-clear',
            ),
            'yellow' => array(
                'square' => 'bg-level-yellow',
                'field'  => 'border border-rule bg-sheet text-ink',
                'dot'    => 'bg-on-yellow',
            ),
            'orange' => array(
                'square' => 'bg-level-orange',
                'field'  => 'border border-rule bg-sheet text-ink',
                'dot'    => 'bg-on-orange',
            ),
            'red'    => array(
                'square' => 'bg-level-red',
                'field'  => 'border border-rule bg-sheet text-ink',
                'dot'    => 'bg-on-red',
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
            case 'general':
                return admin_url( 'options-general.php' );
            case 'plugins':
                return admin_url( 'plugins.php' );
            case 'identity':
                return self::url( 'settings' ) . '#thatseoagent_identity_section';
            case 'homepage':
                return self::url( 'settings' ) . '#thatseoagent_homepage_section';
            case 'products':
                return self::url( 'products' );
            case 'crawlers':
                return self::url( 'crawlers' );
            case 'markdown':
                return self::url( 'llms' ) . '#thatseoagent-markdown';
            case 'privacy':
                return admin_url( 'options-privacy.php' );
            case 'new_page':
                return admin_url( 'post-new.php?post_type=page' );
            case 'audit':
                return self::url( 'audit' );
            case 'posts':
                return admin_url( 'edit.php?post_status=publish' );
            case 'review_types':
                return ThatSeoAgent_New_Types::review_url();
            case 'edit_post':
                $link = ! empty( $action['id'] ) ? get_edit_post_link( (int) $action['id'], 'raw' ) : '';
                return $link ? $link : admin_url( 'edit.php' );
        }

        return self::url();
    }
}
