<?php
/**
 * Homepage SEO Settings + Applier
 *
 * UI for setting a custom homepage title and meta description, plus the
 * applier that feeds those values into the thatseoagent_document_title and
 * thatseoagent_description filters (added in 1.5.0).
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
            <?php esc_html_e( 'Aim for 50–60 characters. Leave blank to use the site name.', 'thatseoagent' ); ?>
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
            <?php esc_html_e( 'Aim for 150–160 characters: the sentence people read under the title in search results and link previews.', 'thatseoagent' ); ?>
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
}
