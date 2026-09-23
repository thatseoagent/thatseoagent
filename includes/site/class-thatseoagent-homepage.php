<?php
/**
 * Homepage SEO Settings
 *
 * UI for setting a custom homepage title and meta description, which
 * ThatSeoAgent_Title and ThatSeoAgent_Description put first on the homepage.
 *
 * Supports a minimal template-variable system so Yoast converts feel at
 * home: %%sitename%%, %%tagline%%, %%sep%%.
 *
 * @package ThatSeoAgent
 * @since 1.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Homepage {

    /**
     * Option key storing homepage SEO settings.
     *
     * @var string
     */
    const OPTION_KEY = 'thatseoagent_homepage';

    /**
     * The option as ThatSeoAgent_Settings registers it: type, sanitizer,
     * default and REST schema.
     *
     * @since 1.20.0 Moved from ThatSeoAgent_Settings::definitions().
     * @return array{type: string, sanitize: callable, default: mixed, schema: array}
     */
    public static function setting() {
        return array(
            'type'     => 'object',
            'sanitize' => array( __CLASS__, 'sanitize' ),
            'default'  => array(),
            'schema'   => array(
                'type'                 => 'object',
                'additionalProperties' => false,
                'properties'           => array(
                    'title'       => array( 'type' => 'string' ),
                    'description' => array( 'type' => 'string' ),
                ),
            ),
        );
    }

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
     * Whether a post is the page the homepage shows.
     *
     * @since 2.7.0
     * @param WP_Post $post Post.
     * @return bool False when the homepage lists the latest posts.
     */
    public static function is_front_page( WP_Post $post ) {
        return 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $post->ID;
    }

    /**
     * Whether a post is the blog's posts page: a page whose own content is
     * never shown, only the list of posts.
     *
     * @since 2.7.0
     * @param WP_Post $post Post.
     * @return bool
     */
    public static function is_posts_page( WP_Post $post ) {
        return 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_for_posts' ) === $post->ID;
    }

    /**
     * Whether the homepage has a description written for it, rather than
     * one taken from the page text.
     *
     * @since 1.20.0
     * @return bool
     */
    public static function has_description() {
        $settings = self::get_settings();

        return '' !== $settings['description'];
    }

    /**
     * Whether the tagline is still the one WordPress installed with.
     *
     * Without a homepage description of its own, the tagline is what search
     * results and llms.txt say the site is. Sites installed before 6.1 got
     * "Just another WordPress site" (earlier still, "weblog"), in the
     * install's language; newer ones get none.
     *
     * @since 2.5.0
     * @return bool
     */
    public static function has_default_tagline() {
        $tagline = trim( (string) get_option( 'blogdescription' ) );

        if ( '' === $tagline ) {
            return false;
        }

        $defaults = array(
            'Just another WordPress site',
            'Just another WordPress weblog',
            // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Core's own string, in the language core translated it to at install.
            __( 'Just another WordPress site', 'default' ),
            'Otro sitio realizado con WordPress',
            'Un sitio más de WordPress',
        );

        return in_array( $tagline, $defaults, true );
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
     * The option itself is registered by ThatSeoAgent_Settings.
     *
     * @since 1.7.0 As register().
     */
    public static function register_section() {
        add_settings_section(
            'thatseoagent_homepage_section',
            __( 'Homepage', 'thatseoagent' ),
            function () {
                echo '<p>' . esc_html__( 'What search results show for the homepage. Leave blank to use the site name and tagline.', 'thatseoagent' ) . '</p>';
                echo '<p><strong>' . esc_html__( 'You can use:', 'thatseoagent' ) . '</strong> ';
                echo '<code>%%sitename%%</code>, <code>%%tagline%%</code>, <code>%%sep%%</code></p>';
            },
            'thatseoagent_settings'
        );

        add_settings_field(
            'thatseoagent_homepage_title',
            __( 'Title', 'thatseoagent' ),
            array( __CLASS__, 'render_title_field' ),
            'thatseoagent_settings',
            'thatseoagent_homepage_section'
        );

        add_settings_field(
            'thatseoagent_homepage_description',
            __( 'Description', 'thatseoagent' ),
            array( __CLASS__, 'render_description_field' ),
            'thatseoagent_settings',
            'thatseoagent_homepage_section'
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
            id="thatseoagent_homepage_title"
            value="<?php echo esc_attr( $value ); ?>"
            class="large-text"
            placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
        >
        <p class="description">
            <?php esc_html_e( 'Leave blank to use the site name. Search results trim titles to the width of the screen; past about 70 characters it may be cut.', 'thatseoagent' ); ?>
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
            id="thatseoagent_homepage_description"
            rows="3"
            class="large-text"
            maxlength="300"
            placeholder="<?php echo esc_attr( get_bloginfo( 'description' ) ); ?>"
        ><?php echo esc_textarea( $value ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'The sentence people read under the title in search results and link previews. Google sets no limit, but past about 165 characters it may be cut.', 'thatseoagent' ); ?>
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
     *   %%sep%%      — thatseoagent_title_separator (default '|')
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

        $separator = apply_filters( 'thatseoagent_title_separator', '|' );

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

    /**
     * This module's part of the bulletin: how search results describe the
     * homepage.
     *
     * @since 2.7.0 Moved from ThatSeoAgent_Bulletin::compose().
     * @return array{observations: array, warnings: array}
     */
    public static function bulletin() {
        $described = self::has_description();
        $warnings  = array();

        // WordPress's default tagline, which stands in for a homepage
        // description that was never written.
        if ( ! $described && self::has_default_tagline() ) {
            $warnings[] = ThatSeoAgent_Bulletin::warning(
                'yellow',
                __( 'Search results describe the site with WordPress\'s default tagline', 'thatseoagent' ),
                /* translators: %s: the tagline. */
                sprintf( __( '"%s" is what the homepage says about the site, in search results and in llms.txt. Write a tagline of your own, or a homepage description.', 'thatseoagent' ), get_bloginfo( 'description' ) ),
                __( 'Open General settings', 'thatseoagent' ),
                'general'
            );
        }

        if ( ! $described ) {
            $warnings[] = ThatSeoAgent_Bulletin::warning(
                'yellow',
                __( 'The homepage description is taken from the page text', 'thatseoagent' ),
                __( 'It is the first thing people read about the site in search results; a sentence written for them works better.', 'thatseoagent' ),
                __( 'Write it', 'thatseoagent' ),
                'homepage'
            );
        }

        return array(
            'observations' => array(
                ThatSeoAgent_Bulletin::observation( 'homepage', __( 'Homepage', 'thatseoagent' ), $described ? 'ok' : 'yellow', $described ? __( 'Described', 'thatseoagent' ) : __( 'Automatic', 'thatseoagent' ) ),
            ),
            'warnings'     => $warnings,
        );
    }
}
