<?php
/**
 * Identity Applier
 *
 * Reads ThatSeoAgent_Identity settings and wires them into the thatseoagent_*
 * filters introduced in 1.5.0. Kept separate from the settings class so
 * storage and output concerns stay decoupled.
 *
 * @package ThatSeoAgent
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Identity_Applier {

    /**
     * Register filter hooks.
     */
    public static function register() {
        add_filter( 'thatseoagent_twitter_handle',        array( __CLASS__, 'filter_twitter_handle' ) );
        add_filter( 'thatseoagent_person_schema',         array( __CLASS__, 'filter_person_schema' ) );
        add_filter( 'thatseoagent_organization_schema',   array( __CLASS__, 'filter_organization_schema' ) );
        add_filter( 'thatseoagent_primary_entity',        array( __CLASS__, 'filter_primary_entity' ) );
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
        return ThatSeoAgent_Identity::primary_entity();
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
        $settings = ThatSeoAgent_Identity::get_settings();
        return $settings['twitter_handle'];
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
        if ( 'person' !== ThatSeoAgent_Identity::primary_entity() ) {
            return $schema;
        }

        $settings = ThatSeoAgent_Identity::get_settings();

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

        $logo = ThatSeoAgent_Image::of( $settings['logo_id'] );
        if ( $logo ) {
            $node['image'] = ThatSeoAgent_Image::object( $logo, home_url( '/#personimage' ) );
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
     * (name, url, logo-from-theme) is already built by ThatSeoAgent_Schema;
     * we layer description and sameAs on top.
     *
     * @param array $schema Existing Organization schema.
     * @return array
     */
    public static function filter_organization_schema( $schema ) {
        if ( 'organization' !== ThatSeoAgent_Identity::primary_entity() ) {
            return $schema;
        }

        $settings = ThatSeoAgent_Identity::get_settings();

        // Name override if user set one.
        if ( $settings['name'] ) {
            $schema['name'] = $settings['name'];
        }

        if ( $settings['description'] ) {
            $schema['description'] = $settings['description'];
        }

        // Logo from identity settings takes precedence when set (otherwise
        // ThatSeoAgent_Schema::get_organization_schema already uses custom_logo).
        $logo = ThatSeoAgent_Image::of( $settings['logo_id'] );
        if ( $logo ) {
            $schema['logo'] = ThatSeoAgent_Image::object( $logo );
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
