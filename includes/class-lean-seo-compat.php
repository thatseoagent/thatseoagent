<?php
/**
 * Coexistence with other SEO plugins.
 *
 * Two SEO plugins printing into the same `<head>` produce two titles, two
 * descriptions, two canonicals and two JSON-LD graphs that disagree with each
 * other. Search engines pick one — not necessarily the one the site owner
 * maintains. Rather than negotiate node by node which plugin owns what, Lean
 * SEO steps aside entirely while another SEO plugin is active: no head output,
 * no sitemaps, no robots.txt changes. The Markdown endpoint keeps working, as
 * no other plugin serves those URLs.
 *
 * @package Lean_SEO
 * @since 1.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lean_SEO_Compat {

    /**
     * SEO plugins Lean SEO steps aside for.
     *
     * Detected by a constant each one defines while loading, so the check
     * costs nothing and works before `init`.
     *
     * @since 1.16.0
     * @return array<string, string> Constant => plugin name.
     */
    public static function known_plugins() {
        return array(
            'WPSEO_VERSION'              => 'Yoast SEO',
            'RANK_MATH_VERSION'          => 'Rank Math',
            'AIOSEO_VERSION'             => 'All in One SEO',
            'SEOPRESS_VERSION'           => 'SEOPress',
            'THE_SEO_FRAMEWORK_VERSION'  => 'The SEO Framework',
            'SQ_VERSION'                 => 'Squirrly SEO',
        );
    }

    /**
     * Register the hooks.
     *
     * @since 1.19.0
     */
    public static function register() {
        add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
    }

    /**
     * The name of the other active SEO plugin, if any.
     *
     * Needs `plugins_loaded` to have fired: before that, a plugin loading
     * after Lean SEO has not defined its constant yet.
     *
     * @since 1.16.0
     * @return string Plugin name, or an empty string when none is active.
     */
    public static function other_seo_plugin() {
        $detected = '';

        foreach ( self::known_plugins() as $constant => $name ) {
            if ( defined( $constant ) ) {
                $detected = $name;
                break;
            }
        }

        /**
         * Filter the SEO plugin Lean SEO steps aside for.
         *
         * Return an empty string to keep Lean SEO's output on even though
         * another SEO plugin is active — for instance while migrating, with
         * the other plugin's output switched off in its own settings.
         *
         * Read on `plugins_loaded`, before the theme loads: hook it from a
         * plugin or an mu-plugin, not from functions.php.
         *
         * @since 1.16.0
         * @param string $detected Name of the detected plugin, or ''.
         */
        return (string) apply_filters( 'lean_seo_other_seo_plugin', $detected );
    }

    /**
     * Whether Lean SEO should print meta tags, schema and sitemaps.
     *
     * @since 1.16.0
     * @return bool
     */
    public static function outputs_enabled() {
        return '' === self::other_seo_plugin();
    }

    /**
     * Tell administrators why Lean SEO is silent.
     *
     * Shown on the Dashboard, the Plugins screen and Lean SEO's own page:
     * where someone would look after noticing, not on every admin screen.
     *
     * @since 1.16.0
     */
    public static function admin_notice() {
        $plugin = self::other_seo_plugin();
        if ( '' === $plugin || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins', Lean_SEO_App::PAGE_HOOK ), true ) ) {
            return;
        }

        // Lean SEO's own dashboard explains it in a banner of its own.
        if ( Lean_SEO_App::PAGE_HOOK === $screen->id && 'dashboard' === Lean_SEO_App::current_view() ) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p>%s</p></div>',
            esc_html(
                sprintf(
                    /* translators: %s: name of the other SEO plugin, e.g. "Yoast SEO". */
                    __( '%s is active, so Lean SEO is not printing meta tags, schema or sitemaps: two SEO plugins would duplicate every tag. Deactivate one of them. The Markdown versions of your posts keep working.', 'lean-seo' ),
                    $plugin
                )
            )
        );
    }
}
