<?php
/**
 * Homepage SEO Settings + Applier
 *
 * UI for setting a custom homepage title and meta description, plus the
 * applier that feeds those values into the lean_seo_document_title and
 * lean_seo_description filters (added in 1.5.0).
 *
 * Supports a minimal template-variable system so Yoast converts feel at
 * home: %%sitename%%, %%tagline%%, %%sep%%.
 *
 * @package Lean_SEO
 * @since 1.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lean_SEO_Homepage {

    /**
     * Option key storing homepage SEO settings.
     *
     * @var string
     */
    const OPTION_KEY = 'lean_seo_homepage';

    /**
     * Get homepage settings with defaults applied.
     *
     * @return array
     */
    public static function get_settings() {
        $saved = get_option( self::OPTION_KEY, array() );

        $defaults = array(
            'title'       => '',
            'description' => '',
        );

        return wp_parse_args( $saved, $defaults );
    }

    /**
     * Register the hooks.
     *
     * @since 1.19.0
     */
    public static function register() {
        add_action( 'admin_init', array( __CLASS__, 'register_section' ) );
    }

    /**
     * Register the settings section and fields.
     *
     * The option itself is registered by Lean_SEO_Settings.
     *
     * @since 1.7.0 As register().
     */
    public static function register_section() {
        add_settings_section(
            'lean_seo_homepage_section',
            __( 'Homepage', 'lean-seo' ),
            function () {
                echo '<p>' . esc_html__( 'What search results show for the homepage. Leave blank to use the site name and tagline.', 'lean-seo' ) . '</p>';
                echo '<p><strong>' . esc_html__( 'You can use:', 'lean-seo' ) . '</strong> ';
                echo '<code>%%sitename%%</code>, <code>%%tagline%%</code>, <code>%%sep%%</code></p>';
            },
            'lean_seo_settings'
        );

        add_settings_field(
            'lean_seo_homepage_title',
            __( 'Title', 'lean-seo' ),
            array( __CLASS__, 'render_title_field' ),
            'lean_seo_settings',
            'lean_seo_homepage_section'
        );

        add_settings_field(
            'lean_seo_homepage_description',
            __( 'Description', 'lean-seo' ),
            array( __CLASS__, 'render_description_field' ),
            'lean_seo_settings',
            'lean_seo_homepage_section'
        );
    }

    /**
     * Render the homepage title input.
     */
    public static function render_title_field() {
        $settings = self::get_settings();
        $value    = $settings['title'];
        ?>
        <input
            type="text"
            name="<?php echo esc_attr( self::OPTION_KEY ); ?>[title]"
            id="lean_seo_homepage_title"
            value="<?php echo esc_attr( $value ); ?>"
            class="large-text"
            placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
        >
        <p class="description">
            <?php esc_html_e( 'Aim for 50–60 characters. Leave blank to use the site name.', 'lean-seo' ); ?>
        </p>
        <?php
    }

    /**
     * Render the homepage description textarea.
     */
    public static function render_description_field() {
        $settings = self::get_settings();
        $value    = $settings['description'];
        ?>
        <textarea
            name="<?php echo esc_attr( self::OPTION_KEY ); ?>[description]"
            id="lean_seo_homepage_description"
            rows="3"
            class="large-text"
            maxlength="300"
            placeholder="<?php echo esc_attr( get_bloginfo( 'description' ) ); ?>"
        ><?php echo esc_textarea( $value ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Aim for 150–160 characters: the sentence people read under the title in search results and link previews.', 'lean-seo' ); ?>
        </p>
        <?php
    }

    /**
     * Sanitize homepage settings on save.
     *
     * @param array $input Raw input.
     * @return array
     */
    public static function sanitize( $input ) {
        $clean = array();

        $clean['title']       = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
        $clean['description'] = isset( $input['description'] ) ? sanitize_textarea_field( $input['description'] ) : '';

        return $clean;
    }

    /**
     * Expand template variables in a string.
     *
     * Supported placeholders:
     *   %%sitename%% — get_bloginfo('name')
     *   %%tagline%%  — get_bloginfo('description')
     *   %%sep%%      — lean_seo_title_separator (default '|')
     *
     * Collapses any resulting runs of whitespace so patterns like
     * "%%sitename%% %%sep%% %%tagline%%" render cleanly even when a
     * variable resolves to an empty string.
     *
     * @param string $template Raw template string.
     * @return string
     */
    public static function expand_variables( $template ) {
        if ( '' === $template ) {
            return '';
        }

        $separator = apply_filters( 'lean_seo_title_separator', '|' );

        $replacements = array(
            '%%sitename%%' => get_bloginfo( 'name' ),
            '%%tagline%%'  => get_bloginfo( 'description' ),
            '%%sep%%'      => $separator,
        );

        $output = strtr( $template, $replacements );

        // Collapse whitespace from empty replacements.
        $output = preg_replace( '/\s+/', ' ', $output );

        return trim( $output );
    }
}

/**
 * Homepage Applier
 *
 * Reads Lean_SEO_Homepage settings and populates the title/description
 * filters when the current context is the homepage.
 *
 * @since 1.7.0
 */
class Lean_SEO_Homepage_Applier {

    /**
     * Register filter hooks.
     */
    public static function register() {
        add_filter( 'lean_seo_document_title', array( __CLASS__, 'filter_document_title' ), 10, 2 );
        add_filter( 'lean_seo_description',    array( __CLASS__, 'filter_description' ),    10, 2 );
    }

    /**
     * Return the configured homepage title when appropriate.
     *
     * Only acts on the homepage context, and only when another callback
     * hasn't already provided an override.
     *
     * @param string $title   Existing override (empty by default).
     * @param string $context Current page context.
     * @return string
     */
    public static function filter_document_title( $title, $context ) {
        if ( '' !== $title ) {
            return $title;
        }
        if ( 'home' !== $context ) {
            return $title;
        }

        $settings = Lean_SEO_Homepage::get_settings();
        if ( '' === $settings['title'] ) {
            return $title;
        }

        return Lean_SEO_Homepage::expand_variables( $settings['title'] );
    }

    /**
     * Return the configured homepage description when appropriate.
     *
     * Acts on home context. Runs after the default resolution so any
     * callback earlier in the chain still takes priority.
     *
     * @param string $description Resolved description.
     * @param string $context     Current page context.
     * @return string
     */
    public static function filter_description( $description, $context ) {
        if ( 'home' !== $context ) {
            return $description;
        }

        $settings = Lean_SEO_Homepage::get_settings();
        if ( '' === $settings['description'] ) {
            return $description;
        }

        // Only override when the default description is empty or falls back
        // to the blog tagline. Sites that explicitly wire their own home
        // description via filter still win because a non-empty, non-tagline
        // value means something intentional is already there.
        $tagline = (string) get_bloginfo( 'description' );
        if ( '' !== $description && $description !== $tagline ) {
            return $description;
        }

        return Lean_SEO_Homepage::expand_variables( $settings['description'] );
    }
}
