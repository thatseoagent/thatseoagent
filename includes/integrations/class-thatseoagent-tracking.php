<?php
/**
 * Analytics and advertising tags.
 *
 * Google Analytics 4 and Google Ads both load the Google tag (gtag.js):
 * the settings take each one's ID — alone, or the whole snippet as Google
 * shows it — and the page loads gtag.js once, with one `config` per ID.
 *
 * Any other service's code goes in one of three boxes, by where it asks to
 * be placed: the page head, the opening of the body, or the footer. The
 * code is printed as written, so only users who may publish unfiltered
 * HTML can change it.
 *
 * The tags are printed while ThatSeoAgent steps aside too: other SEO
 * plugins do not load them, and a site would lose its analytics without
 * a word the day one was activated.
 *
 * @package ThatSeoAgent
 * @since 2.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Tracking {

    /**
     * Option holding the IDs and the code.
     */
    const OPTION_KEY = 'thatseoagent_tracking';

    /**
     * The services loaded through gtag.js, the ID each takes and where to
     * find it. Adding another Google tag product is one entry here.
     *
     * @since 2.8.0
     * @return array<string, array{label: string, pattern: string, example: string, help: string}>
     */
    public static function tags() {
        return array(
            'ga4'        => array(
                'label'   => 'Google Analytics 4',
                'pattern' => '/\bG-[A-Z0-9]{4,20}\b/',
                'example' => 'G-XXXXXXXXXX',
                'help'    => 'https://support.google.com/analytics/answer/9539598',
            ),
            'google_ads' => array(
                'label'   => 'Google Ads',
                'pattern' => '/\bAW-[0-9]{4,20}\b/',
                'example' => 'AW-123456789',
                'help'    => 'https://support.google.com/google-ads/answer/7548399',
            ),
        );
    }

    /**
     * The places free-form code can go, and the hook that prints each.
     *
     * Without labels: this is read on plugins_loaded, before translations
     * may load. The labels are in place_labels().
     *
     * @since 2.8.0
     * @return array<string, array{hook: string, priority: int}>
     */
    public static function places() {
        return array(
            'code_head'   => array(
                'hook'     => 'wp_head',
                'priority' => 99,
            ),
            'code_body'   => array(
                'hook'     => 'wp_body_open',
                'priority' => 1,
            ),
            'code_footer' => array(
                'hook'     => 'wp_footer',
                'priority' => 99,
            ),
        );
    }

    /**
     * Each place's label and help, for the settings page.
     *
     * @since 2.8.0
     * @return array<string, array{label: string, help: string}>
     */
    private static function place_labels() {
        return array(
            'code_head'   => array(
                'label' => __( 'Code in the head', 'thatseoagent' ),
                'help'  => __( 'For code the service asks to place inside <head>.', 'thatseoagent' ),
            ),
            'code_body'   => array(
                'label' => __( 'Code after the opening body tag', 'thatseoagent' ),
                'help'  => __( 'For code the service asks to place right after <body>, such as a <noscript> fallback. Printed only if the theme supports it; block themes do.', 'thatseoagent' ),
            ),
            'code_footer' => array(
                'label' => __( 'Code in the footer', 'thatseoagent' ),
                'help'  => __( 'For code the service asks to place before </body>.', 'thatseoagent' ),
            ),
        );
    }

    /**
     * The option as ThatSeoAgent_Settings registers it.
     *
     * @since 2.8.0
     * @return array{type: string, sanitize: callable, default: mixed, schema: array}
     */
    public static function setting() {
        $properties = array();
        foreach ( array_merge( array_keys( self::tags() ), array_keys( self::places() ) ) as $key ) {
            $properties[ $key ] = array( 'type' => 'string' );
        }

        return array(
            'type'     => 'object',
            'sanitize' => array( __CLASS__, 'sanitize' ),
            'default'  => array(),
            'schema'   => array(
                'type'                 => 'object',
                'additionalProperties' => false,
                'properties'           => $properties,
            ),
        );
    }

    /**
     * Keep an ID per known tag, and the code as written.
     *
     * An ID is taken from wherever it appears in the input, so pasting
     * Google's whole snippet works. The code is kept only from users who
     * may publish unfiltered HTML; from anyone else, the saved code stays
     * as it was.
     *
     * @since 2.8.0
     * @param mixed $input Raw value.
     * @return array<string, string>
     */
    public static function sanitize( $input ) {
        $input = is_array( $input ) ? $input : array();
        $clean = array();

        foreach ( self::tags() as $key => $tag ) {
            $value = isset( $input[ $key ] ) ? strtoupper( trim( (string) $input[ $key ] ) ) : '';

            if ( preg_match( $tag['pattern'], $value, $match ) ) {
                $clean[ $key ] = $match[0];
            }
        }

        $saved   = self::read( get_option( self::OPTION_KEY, array() ) );
        $may_set = current_user_can( 'unfiltered_html' );

        foreach ( array_keys( self::places() ) as $key ) {
            if ( $may_set ) {
                $code = isset( $input[ $key ] ) ? trim( (string) $input[ $key ] ) : '';
            } else {
                $code = isset( $saved[ $key ] ) ? $saved[ $key ] : '';
            }

            if ( '' !== $code ) {
                $clean[ $key ] = $code;
            }
        }

        return $clean;
    }

    /**
     * The saved IDs and code, keeping only known keys and strings.
     *
     * @since 2.8.0
     * @param mixed $value Stored value.
     * @return array<string, string>
     */
    private static function read( $value ) {
        $value = is_array( $value ) ? $value : array();
        $known = array_merge( array_keys( self::tags() ), array_keys( self::places() ) );
        $out   = array();

        foreach ( $known as $key ) {
            if ( isset( $value[ $key ] ) && is_string( $value[ $key ] ) && '' !== $value[ $key ] ) {
                $out[ $key ] = $value[ $key ];
            }
        }

        return $out;
    }

    /**
     * The saved IDs and code.
     *
     * @since 2.8.0
     * @return array<string, string>
     */
    public static function saved() {
        return self::read( get_option( self::OPTION_KEY, array() ) );
    }

    /**
     * Register the settings section.
     *
     * @since 2.8.0
     */
    public static function register_settings() {
        add_action( 'admin_init', array( __CLASS__, 'register_section' ) );
    }

    /**
     * Register the front-end hooks.
     *
     * @since 2.8.0
     */
    public static function register() {
        add_action( 'wp_head', array( __CLASS__, 'output_gtag' ), 2 );

        foreach ( self::places() as $key => $place ) {
            add_action(
                $place['hook'],
                function () use ( $key ) {
                    self::output_code( $key );
                },
                $place['priority']
            );
        }
    }

    /**
     * Load gtag.js once and configure every saved ID.
     *
     * @since 2.8.0
     */
    public static function output_gtag() {
        $saved = self::saved();
        $ids   = array();

        foreach ( array_keys( self::tags() ) as $key ) {
            if ( isset( $saved[ $key ] ) ) {
                $ids[] = $saved[ $key ];
            }
        }

        if ( empty( $ids ) ) {
            return;
        }

        wp_print_script_tag(
            array(
                'async' => true,
                'src'   => 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $ids[0] ),
            )
        );

        $script = "window.dataLayer = window.dataLayer || [];\nfunction gtag(){dataLayer.push(arguments);}\ngtag('js', new Date());\n";
        foreach ( $ids as $id ) {
            $script .= 'gtag(\'config\', ' . wp_json_encode( $id ) . ");\n";
        }

        wp_print_inline_script_tag( $script );
    }

    /**
     * Print the code saved for one place.
     *
     * @since 2.8.0
     * @param string $key The place.
     */
    public static function output_code( $key ) {
        $saved = self::saved();

        if ( isset( $saved[ $key ] ) ) {
            echo $saved[ $key ] . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Code saved as written by a user with unfiltered_html.
        }
    }

    /**
     * Add the settings section.
     *
     * @since 2.8.0
     */
    public static function register_section() {
        add_settings_section(
            'thatseoagent_tracking_section',
            __( 'Analytics and tags', 'thatseoagent' ),
            function () {
                echo '<p>' . esc_html__( 'Google Analytics and Google Ads share one Google tag: paste each one\'s ID, or the whole snippet as Google shows it, and the tag is loaded once for both. For any other service, paste its code in the box for the place it asks you to put it.', 'thatseoagent' ) . '</p>';
            },
            ThatSeoAgent_Settings::GROUP
        );

        foreach ( self::tags() as $key => $tag ) {
            add_settings_field(
                'thatseoagent_tracking_' . $key,
                $tag['label'],
                array( __CLASS__, 'render_tag_field' ),
                ThatSeoAgent_Settings::GROUP,
                'thatseoagent_tracking_section',
                array(
                    'key'       => $key,
                    'label_for' => 'thatseoagent_tracking_' . $key,
                )
            );
        }

        foreach ( self::place_labels() as $key => $place ) {
            add_settings_field(
                'thatseoagent_tracking_' . $key,
                $place['label'],
                array( __CLASS__, 'render_code_field' ),
                ThatSeoAgent_Settings::GROUP,
                'thatseoagent_tracking_section',
                array(
                    'key'       => $key,
                    'label_for' => 'thatseoagent_tracking_' . $key,
                )
            );
        }
    }

    /**
     * One tag's ID field.
     *
     * @since 2.8.0
     * @param array $args Field arguments.
     */
    public static function render_tag_field( $args ) {
        $key   = $args['key'];
        $tags  = self::tags();
        $saved = self::saved();

        printf(
            '<input type="text" id="%1$s" name="%2$s" value="%3$s" placeholder="%4$s" class="regular-text code" autocomplete="off" spellcheck="false">',
            esc_attr( 'thatseoagent_tracking_' . $key ),
            esc_attr( self::OPTION_KEY . '[' . $key . ']' ),
            esc_attr( isset( $saved[ $key ] ) ? $saved[ $key ] : '' ),
            esc_attr( $tags[ $key ]['example'] )
        );
        printf(
            '<p class="description"><a href="%s" target="_blank" rel="noopener">%s</a></p>',
            esc_url( $tags[ $key ]['help'] ),
            esc_html__( 'Where to find the ID', 'thatseoagent' )
        );
    }

    /**
     * One place's code field.
     *
     * Read-only for users who may not publish unfiltered HTML: the field
     * still posts its value, and the sanitizer keeps the saved code anyway.
     *
     * @since 2.8.0
     * @param array $args Field arguments.
     */
    public static function render_code_field( $args ) {
        $key     = $args['key'];
        $places  = self::place_labels();
        $saved   = self::saved();
        $may_set = current_user_can( 'unfiltered_html' );

        printf(
            '<textarea id="%1$s" name="%2$s" rows="5" class="large-text code" spellcheck="false"%3$s>%4$s</textarea>',
            esc_attr( 'thatseoagent_tracking_' . $key ),
            esc_attr( self::OPTION_KEY . '[' . $key . ']' ),
            $may_set ? '' : ' readonly',
            esc_textarea( isset( $saved[ $key ] ) ? $saved[ $key ] : '' )
        );
        echo '<p class="description">' . esc_html( $places[ $key ]['help'] ) . '</p>';

        if ( ! $may_set ) {
            echo '<p class="description">' . esc_html__( 'Only users who may publish unfiltered HTML can change this code.', 'thatseoagent' ) . '</p>';
        }
    }
}
