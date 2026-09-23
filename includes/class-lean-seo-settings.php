<?php
/**
 * The registry of Lean SEO's options.
 *
 * Every option the settings page writes is registered here, once, with its
 * sanitizer and a JSON schema. Before 1.16.0 each module called
 * register_setting() on `admin_init`, which left the options invisible to the
 * REST API: `/wp/v2/settings` only lists settings registered with a schema
 * and `show_in_rest`, and REST requests never run `admin_init`.
 *
 * Registering on `init` serves both paths. options.php (the classic settings
 * form) runs `init` before it saves, and REST requests run it before they
 * dispatch — so a form post and a REST update go through the same sanitizer.
 *
 * The modules still own their data: each sanitizer and each settings section
 * stays in the module that reads the option.
 *
 * @package Lean_SEO
 * @since 1.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lean_SEO_Settings {

    /**
     * Settings group used by the settings page form.
     */
    const GROUP = 'lean_seo_settings';

    /**
     * Register the hooks.
     *
     * @since 1.16.0
     */
    public static function register() {
        add_action( 'init', array( __CLASS__, 'register_settings' ) );
    }

    /**
     * Every option, keyed by option name.
     *
     * @since 1.16.0
     * @return array<string, array{type: string, sanitize: callable, default: mixed, schema: array}>
     */
    public static function definitions() {
        $social = array();
        foreach ( array_keys( Lean_SEO_Identity::get_social_networks() ) as $network ) {
            $social[ $network ] = array( 'type' => 'string' );
        }

        return array(
            'lean_seo_schema'       => array(
                'type'     => 'object',
                'sanitize' => array( 'Lean_SEO_Admin', 'sanitize_schema_settings' ),
                'default'  => array(),
                'schema'   => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => array(
                        'author_name' => array( 'type' => 'string' ),
                        'author_url'  => array( 'type' => 'string' ),
                        'author_type' => array(
                            'type' => 'string',
                            'enum' => array( 'Person', 'Organization' ),
                        ),
                    ),
                ),
            ),
            'lean_seo_identity'     => array(
                'type'     => 'object',
                'sanitize' => array( 'Lean_SEO_Identity', 'sanitize' ),
                'default'  => array(),
                'schema'   => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => array(
                        'type'                => array(
                            'type' => 'string',
                            'enum' => array( 'person', 'organization' ),
                        ),
                        'name'                => array( 'type' => 'string' ),
                        'description'         => array( 'type' => 'string' ),
                        'logo_id'             => array( 'type' => 'integer' ),
                        'default_og_image_id' => array( 'type' => 'integer' ),
                        'twitter_handle'      => array( 'type' => 'string' ),
                        'social'              => array(
                            'type'                 => 'object',
                            'additionalProperties' => false,
                            'properties'           => $social,
                        ),
                    ),
                ),
            ),
            'lean_seo_homepage'     => array(
                'type'     => 'object',
                'sanitize' => array( 'Lean_SEO_Homepage', 'sanitize' ),
                'default'  => array(),
                'schema'   => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => array(
                        'title'       => array( 'type' => 'string' ),
                        'description' => array( 'type' => 'string' ),
                    ),
                ),
            ),
            'lean_seo_products'     => array(
                'type'     => 'object',
                'sanitize' => array( 'Lean_SEO_Product', 'sanitize' ),
                'default'  => array(),
                'schema'   => Lean_SEO_Product::rest_schema(),
            ),
            'lean_seo_llms_txt'     => array(
                'type'     => 'boolean',
                'sanitize' => 'rest_sanitize_boolean',
                'default'  => true,
                'schema'   => array( 'type' => 'boolean' ),
            ),
            'lean_seo_indexnow_key' => array(
                'type'     => 'string',
                'sanitize' => array( 'Lean_SEO_IndexNow', 'sanitize_key_setting' ),
                'default'  => '',
                'schema'   => array( 'type' => 'string' ),
            ),
        );
    }

    /**
     * Register every option with WordPress.
     *
     * @since 1.16.0
     */
    public static function register_settings() {
        foreach ( self::definitions() as $option => $definition ) {
            register_setting(
                self::GROUP,
                $option,
                array(
                    'type'              => $definition['type'],
                    'sanitize_callback' => $definition['sanitize'],
                    'default'           => $definition['default'],
                    'show_in_rest'      => array(
                        'name'   => $option,
                        'schema' => $definition['schema'],
                    ),
                )
            );
        }
    }
}
