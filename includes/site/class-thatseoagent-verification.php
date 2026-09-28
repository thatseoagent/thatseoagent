<?php
/**
 * Site verification for search engine consoles.
 *
 * Google Search Console, Bing Webmaster Tools and the others prove a site
 * is yours by looking for a meta tag with a code they give you, on the
 * homepage. The code goes in the settings — alone, or the whole tag as the
 * service shows it — and the tag is printed on the homepage only.
 *
 * Search Console also verifies through DNS, which covers every address of
 * the domain and needs nothing here; the tag is for when DNS is not at hand.
 *
 * @package ThatSeoAgent
 * @since 2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Verification {

    /**
     * Option holding the codes.
     */
    const OPTION_KEY = 'thatseoagent_verification';

    /**
     * The services, the meta name each reads, and where to get the code.
     *
     * @since 2.5.0
     * @return array<string, array{label: string, meta: string, help: string}>
     */
    public static function services() {
        return array(
            'google'    => array(
                'label' => 'Google Search Console',
                'meta'  => 'google-site-verification',
                'help'  => 'https://search.google.com/search-console',
            ),
            'bing'      => array(
                'label' => 'Bing Webmaster Tools',
                'meta'  => 'msvalidate.01',
                'help'  => 'https://www.bing.com/webmasters',
            ),
            'yandex'    => array(
                'label' => 'Yandex Webmaster',
                'meta'  => 'yandex-verification',
                'help'  => 'https://webmaster.yandex.com',
            ),
            'baidu'     => array(
                'label' => 'Baidu Search Resource Platform',
                'meta'  => 'baidu-site-verification',
                'help'  => 'https://ziyuan.baidu.com',
            ),
            'pinterest' => array(
                'label' => 'Pinterest',
                'meta'  => 'p:domain_verify',
                'help'  => 'https://www.pinterest.com/settings/claim',
            ),
        );
    }

    /**
     * The option as ThatSeoAgent_Settings registers it.
     *
     * @since 2.5.0
     * @return array{type: string, sanitize: callable, default: mixed, schema: array}
     */
    public static function setting() {
        $properties = array();
        foreach ( array_keys( self::services() ) as $service ) {
            $properties[ $service ] = array( 'type' => 'string' );
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
     * Keep a code per known service.
     *
     * Accepts the code, or the whole meta tag the service shows — the code
     * is taken from its content attribute. Codes are letters, digits, dashes,
     * underscores and the = padding some use; anything else is dropped.
     *
     * @since 2.5.0
     * @param mixed $input Raw value.
     * @return array<string, string>
     */
    public static function sanitize( $input ) {
        $input = is_array( $input ) ? $input : array();
        $clean = array();

        foreach ( array_keys( self::services() ) as $service ) {
            $value = isset( $input[ $service ] ) ? trim( (string) $input[ $service ] ) : '';

            if ( preg_match( '/content\s*=\s*["\']([^"\']*)["\']/i', $value, $match ) ) {
                $value = $match[1];
            }

            $value = preg_replace( '/[^A-Za-z0-9_=\-]/', '', $value );

            if ( '' !== $value ) {
                $clean[ $service ] = substr( $value, 0, 128 );
            }
        }

        return $clean;
    }

    /**
     * The saved codes.
     *
     * @since 2.5.0
     * @return array<string, string>
     */
    public static function codes() {
        return self::sanitize( get_option( self::OPTION_KEY, array() ) );
    }

    /**
     * Register the settings section.
     *
     * @since 2.5.0
     */
    public static function register_settings() {
        add_action( 'admin_init', array( __CLASS__, 'register_section' ) );
    }

    /**
     * Register the front-end hook.
     *
     * @since 2.5.0
     */
    public static function register() {
        add_action( 'wp_head', array( __CLASS__, 'output' ), 1 );
    }

    /**
     * Print the verification tags, on the homepage only.
     *
     * @since 2.5.0
     */
    public static function output() {
        if ( ! is_front_page() ) {
            return;
        }

        $services = self::services();

        foreach ( self::codes() as $service => $code ) {
            echo '<meta name="' . esc_attr( $services[ $service ]['meta'] ) . '" content="' . esc_attr( $code ) . '">' . "\n";
        }
    }

    /**
     * Add the settings section.
     *
     * @since 2.5.0
     */
    public static function register_section() {
        add_settings_section(
            'thatseoagent_verification_section',
            __( 'Site verification', 'thatseoagent' ),
            function () {
                echo '<p>' . esc_html__( 'To prove to a search engine\'s console that the site is yours, paste the code it gives you, or the whole meta tag as it shows it. The tag goes on the homepage only. Google Search Console can also verify through DNS, which needs nothing here.', 'thatseoagent' ) . '</p>';
            },
            ThatSeoAgent_Settings::GROUP
        );

        foreach ( self::services() as $service => $info ) {
            add_settings_field(
                'thatseoagent_verification_' . $service,
                $info['label'],
                array( __CLASS__, 'render_field' ),
                ThatSeoAgent_Settings::GROUP,
                'thatseoagent_verification_section',
                array(
                    'service'   => $service,
                    'label_for' => 'thatseoagent_verification_' . $service,
                )
            );
        }
    }

    /**
     * One service's code field.
     *
     * @since 2.5.0
     * @param array $args Field arguments.
     */
    public static function render_field( $args ) {
        $service  = $args['service'];
        $services = self::services();
        $codes    = self::codes();

        printf(
            '<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text code" autocomplete="off" spellcheck="false">',
            esc_attr( 'thatseoagent_verification_' . $service ),
            esc_attr( self::OPTION_KEY . '[' . $service . ']' ),
            esc_attr( isset( $codes[ $service ] ) ? $codes[ $service ] : '' )
        );
        printf(
            '<p class="description">%s <code>%s</code> · <a href="%s" target="_blank" rel="noopener">%s</a></p>',
            esc_html__( 'Printed as', 'thatseoagent' ),
            esc_html( $services[ $service ]['meta'] ),
            esc_url( $services[ $service ]['help'] ),
            esc_html__( 'Get the code', 'thatseoagent' )
        );
    }
}
