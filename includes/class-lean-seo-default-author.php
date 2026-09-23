<?php
/**
 * The default author: who is credited on posts that have no author.
 *
 * Stored in the `lean_seo_schema` option and read by Lean_SEO_Schema when it
 * builds an Article's author. This module owns the settings section, its
 * fields and the sanitizer.
 *
 * @package Lean_SEO
 * @since 1.19.0 Moved out of Lean_SEO_Admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lean_SEO_Default_Author {

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
     * @since 1.3.0 As Lean_SEO_Admin::register_settings().
     */
    public static function register_section() {
        add_settings_section(
            'lean_seo_schema_section',
            __( 'Default author', 'lean-seo' ),
            function () {
                echo '<p>' . esc_html__( 'Credited on posts that have no author assigned. Leave blank to credit the site itself.', 'lean-seo' ) . '</p>';
            },
            'lean_seo_settings'
        );

        $fields = array(
            'author_name' => __( 'Name', 'lean-seo' ),
            'author_url'  => __( 'Web address', 'lean-seo' ),
            'author_type' => __( 'The author is', 'lean-seo' ),
        );

        foreach ( $fields as $key => $label ) {
            add_settings_field(
                'lean_seo_schema_' . $key,
                $label,
                array( __CLASS__, 'render_field' ),
                'lean_seo_settings',
                'lean_seo_schema_section',
                array( 'key' => $key, 'label' => $label )
            );
        }
    }

    /**
     * Render a single schema settings field.
     *
     * @since 1.3.0
     * @param array $args Field arguments.
     */
    public static function render_field( $args ) {
        // Saved values only; the defaults are placeholders. Rendering the
        // defaults as values saved them on the first submit, and a saved
        // author name makes every authorless post credit a Person named after
        // the site — the fallback this setting exists to avoid.
        $saved    = get_option( 'lean_seo_schema', array() );
        $defaults = Lean_SEO_Schema::get_publisher_defaults();
        $key      = $args['key'];
        $value    = isset( $saved[ $key ] ) ? $saved[ $key ] : '';

        if ( 'author_type' === $key ) {
            printf(
                '<select name="lean_seo_schema[%s]" id="lean_seo_schema_%s">',
                esc_attr( $key ),
                esc_attr( $key )
            );
            $types = array(
                'Person'       => __( 'A person', 'lean-seo' ),
                'Organization' => __( 'An organization', 'lean-seo' ),
            );
            foreach ( $types as $type => $label ) {
                printf(
                    '<option value="%s"%s>%s</option>',
                    esc_attr( $type ),
                    selected( $value, $type, false ),
                    esc_html( $label )
                );
            }
            echo '</select>';
        } else {
            printf(
                '<input type="%s" name="lean_seo_schema[%s]" id="lean_seo_schema_%s" value="%s" placeholder="%s" class="regular-text">',
                'author_url' === $key ? 'url' : 'text',
                esc_attr( $key ),
                esc_attr( $key ),
                esc_attr( $value ),
                esc_attr( $defaults[ $key ] )
            );
        }
    }

    /**
     * Sanitize schema settings.
     *
     * @since 1.3.0
     * @param array $input Raw input.
     * @return array Sanitized values.
     */
    public static function sanitize( $input ) {
        $clean = array();

        if ( ! empty( $input['author_name'] ) ) {
            $clean['author_name'] = sanitize_text_field( $input['author_name'] );
        }
        if ( ! empty( $input['author_url'] ) ) {
            $clean['author_url'] = esc_url_raw( $input['author_url'] );
        }
        if ( ! empty( $input['author_type'] ) && in_array( $input['author_type'], array( 'Person', 'Organization' ), true ) ) {
            $clean['author_type'] = $input['author_type'];
        }

        return $clean;
    }
}
