<?php
/**
 * Identity Applier
 *
 * Reads Lean_SEO_Identity settings and wires them into the lean_seo_*
 * filters introduced in 1.5.0. Kept separate from the settings class so
 * storage and output concerns stay decoupled.
 *
 * @package Lean_SEO
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lean_SEO_Identity_Applier {

    /**
     * Register filter hooks.
     */
    public static function register() {
        add_filter( 'lean_seo_twitter_handle',        array( __CLASS__, 'filter_twitter_handle' ) );
        add_filter( 'lean_seo_default_image',         array( __CLASS__, 'filter_default_image' ) );
        add_filter( 'lean_seo_person_schema',         array( __CLASS__, 'filter_person_schema' ) );
        add_filter( 'lean_seo_organization_schema',   array( __CLASS__, 'filter_organization_schema' ) );
        add_filter( 'lean_seo_primary_entity',        array( __CLASS__, 'filter_primary_entity' ) );
    }

    /**
     * Declare the site's primary schema entity from the identity settings.
     *
     * Only an explicitly saved 'person' identity flips this; an untouched
     * install stays on the Organization default.
     *
     * @since 1.8.0
     * @param string $entity Existing value.
     * @return string
     */
    public static function filter_primary_entity( $entity ) {
        return Lean_SEO_Identity::primary_entity();
    }

    /**
     * Feed the configured Twitter handle into the filter.
     *
     * @param string $handle Existing value.
     * @return string
     */
    public static function filter_twitter_handle( $handle ) {
        if ( $handle ) {
            return $handle;
        }
        $settings = Lean_SEO_Identity::get_settings();
        return $settings['twitter_handle'];
    }

    /**
     * Feed the default OG image into the filter.
     *
     * @param string $url Existing URL.
     * @return string
     */
    public static function filter_default_image( $url ) {
        if ( $url ) {
            return $url;
        }
        $settings = Lean_SEO_Identity::get_settings();
        if ( ! $settings['default_og_image_id'] ) {
            return $url;
        }
        $attachment_url = wp_get_attachment_image_url( $settings['default_og_image_id'], 'full' );
        return $attachment_url ? $attachment_url : $url;
    }

    /**
     * Build the Person schema node when identity type is 'person'.
     *
     * @param array|null $schema Existing schema (null if unset).
     * @return array|null
     */
    public static function filter_person_schema( $schema ) {
        // Respect prior filter output.
        if ( is_array( $schema ) && ! empty( $schema ) ) {
            return $schema;
        }

        // Without saved settings there is nothing to describe: emitting a
        // Person node named after the site adds a second, contentless entity
        // to the graph. Stay silent until the site owner configures one.
        if ( 'person' !== Lean_SEO_Identity::primary_entity() ) {
            return $schema;
        }

        $settings = Lean_SEO_Identity::get_settings();

        $name = $settings['name'] ? $settings['name'] : get_bloginfo( 'name' );

        $node = array(
            '@type' => 'Person',
            '@id'   => home_url( '/#person' ),
            'name'  => $name,
            'url'   => home_url( '/' ),
        );

        if ( $settings['description'] ) {
            $node['description'] = $settings['description'];
        }

        if ( $settings['logo_id'] ) {
            $logo_url = wp_get_attachment_image_url( $settings['logo_id'], 'full' );
            if ( $logo_url ) {
                $meta = wp_get_attachment_metadata( $settings['logo_id'] );
                $image = array(
                    '@type' => 'ImageObject',
                    '@id'   => home_url( '/#personimage' ),
                    'url'   => $logo_url,
                );
                if ( $meta && isset( $meta['width'], $meta['height'] ) ) {
                    $image['width']  = (int) $meta['width'];
                    $image['height'] = (int) $meta['height'];
                }
                $node['image'] = $image;
            }
        }

        $same_as = self::build_same_as( $settings );
        if ( ! empty( $same_as ) ) {
            $node['sameAs'] = $same_as;
        }

        return $node;
    }

    /**
     * Enrich the Organization schema node with identity settings.
     *
     * Only runs when identity type is 'organization'. The base node
     * (name, url, logo-from-theme) is already built by Lean_SEO_Schema;
     * we layer description and sameAs on top.
     *
     * @param array $schema Existing Organization schema.
     * @return array
     */
    public static function filter_organization_schema( $schema ) {
        if ( 'organization' !== Lean_SEO_Identity::primary_entity() ) {
            return $schema;
        }

        $settings = Lean_SEO_Identity::get_settings();

        // Name override if user set one.
        if ( $settings['name'] ) {
            $schema['name'] = $settings['name'];
        }

        if ( $settings['description'] ) {
            $schema['description'] = $settings['description'];
        }

        // Logo from identity settings takes precedence when set (otherwise
        // Lean_SEO_Schema::get_organization_schema already uses custom_logo).
        if ( $settings['logo_id'] ) {
            $logo_url = wp_get_attachment_image_url( $settings['logo_id'], 'full' );
            if ( $logo_url ) {
                $meta = wp_get_attachment_metadata( $settings['logo_id'] );
                $logo = array(
                    '@type' => 'ImageObject',
                    'url'   => $logo_url,
                );
                if ( $meta && isset( $meta['width'], $meta['height'] ) ) {
                    $logo['width']  = (int) $meta['width'];
                    $logo['height'] = (int) $meta['height'];
                }
                $schema['logo'] = $logo;
            }
        }

        $same_as = self::build_same_as( $settings );
        if ( ! empty( $same_as ) ) {
            $schema['sameAs'] = $same_as;
        }

        return $schema;
    }

    /**
     * Assemble the sameAs array from configured social profile URLs.
     *
     * @param array $settings Identity settings.
     * @return array<int, string>
     */
    protected static function build_same_as( $settings ) {
        $same_as = array();
        if ( ! empty( $settings['social'] ) && is_array( $settings['social'] ) ) {
            foreach ( $settings['social'] as $url ) {
                $url = trim( (string) $url );
                if ( $url ) {
                    $same_as[] = $url;
                }
            }
        }
        return array_values( array_unique( $same_as ) );
    }
}
