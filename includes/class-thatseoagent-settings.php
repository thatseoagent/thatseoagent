<?php
/**
 * The registry of ThatSeoAgent's options.
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
 * The modules own their data: each declares its option in setting() —
 * type, sanitizer, default and schema — next to the settings section and
 * the code that reads it. This class only gathers and registers them.
 *
 * @package ThatSeoAgent
 * @since 1.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Settings {

    /**
     * Settings group used by the settings page form.
     */
    const GROUP = 'thatseoagent_settings';

    /**
     * Register the hooks.
     *
     * @since 1.16.0
     */
    public static function register() {
        add_action( 'init', array( __CLASS__, 'register_settings' ) );
    }

    /**
     * The modules that own an option, in the order the options are listed.
     *
     * Each declares its option's name (OPTION_KEY) and its definition
     * (setting()). Adding an option means adding its owner here.
     *
     * @since 1.20.0
     * @return string[] Class names.
     */
    private static function owners() {
        return array(
            'ThatSeoAgent_Default_Author',
            'ThatSeoAgent_Identity',
            'ThatSeoAgent_Homepage',
            'ThatSeoAgent_Product',
            'ThatSeoAgent_Llms',
            'ThatSeoAgent_IndexNow',
            'ThatSeoAgent_AI_Crawlers',
        );
    }

    /**
     * Every option, keyed by option name.
     *
     * @since 1.16.0
     * @since 1.20.0 Gathered from each owner's setting().
     * @return array<string, array{type: string, sanitize: callable, default: mixed, schema: array}>
     */
    public static function definitions() {
        $definitions = array();

        foreach ( self::owners() as $owner ) {
            $definitions[ $owner::OPTION_KEY ] = $owner::setting();
        }

        return $definitions;
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
