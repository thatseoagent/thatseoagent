<?php
/**
 * Abilities API registration: the site-wide abilities.
 *
 * The plugin's options — identity, homepage, verification, crawlers… — read and written through the same registry, schema and
 * sanitizers as the settings screen (ThatSeoAgent_Settings), plus the
 * reports the screen shows: duplicates, links and the bulletin.
 *
 * @package ThatSeoAgent
 * @since 2.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Site_Abilities {

    /**
     * Register abilities. Called from ThatSeoAgent_Abilities::register().
     *
     * @since 2.9.0
     */
    public static function register() {
        $setting = array(
            'type'        => 'string',
            'enum'        => array_keys( self::options() ),
            'description' => __( 'Which setting. See get-seo-settings for what each one holds.', 'thatseoagent' ),
        );

        wp_register_ability(
            'thatseoagent/get-seo-settings',
            array(
                'label'               => __( 'Get SEO Settings', 'thatseoagent' ),
                'description'         => __( 'Returns the plugin\'s settings as the settings screen stores them — or one of them — with what each one does and its JSON schema: identity (who the site represents: name, logo, default sharing image, social profiles), homepage (its search title and description), verification (Search Console and other webmaster codes), default_author, llms_txt, indexnow_key, ai_crawlers (robots.txt rules for AI crawlers), crawl_cleanup and tracking. With every setting it also returns catalogs: the post types the theme or plugins declared as product catalogs, and where each product detail is read from; they are declared in code and cannot be changed here.', 'thatseoagent' ),
                'category'            => 'site',
                'execute_callback'    => array( __CLASS__, 'get_settings' ),
                'permission_callback' => array( 'ThatSeoAgent_Abilities', 'can_manage' ),
                'meta'                => ThatSeoAgent_Abilities::annotations( true ),
                'input_schema'        => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'default'              => array(),
                    'properties'           => array(
                        'setting' => $setting,
                    ),
                ),
                'output_schema'       => array( 'type' => 'object' ),
            )
        );

        wp_register_ability(
            'thatseoagent/update-seo-settings',
            array(
                'label'               => __( 'Update SEO Settings', 'thatseoagent' ),
                'description'         => __( 'Changes one of the plugin\'s settings. For the settings that are objects, only the keys you send change; a key that is itself an object (identity.social, ai_crawlers.bots) is replaced whole. The value is checked against the setting\'s schema and goes through the same sanitizer as the settings screen. Live right away. tracking injects code into every page and also needs the unfiltered_html capability.', 'thatseoagent' ),
                'category'            => 'site',
                'execute_callback'    => array( __CLASS__, 'update_settings' ),
                'permission_callback' => array( __CLASS__, 'can_update_setting' ),
                'meta'                => ThatSeoAgent_Abilities::annotations( false ),
                'input_schema'        => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => array(
                        'setting' => $setting,
                        'value'   => array(
                            // The types the settings have: objects, llms_txt's
                            // boolean and indexnow_key's string.
                            'type'        => array( 'object', 'boolean', 'string' ),
                            'description' => __( 'The new value, or the keys to change for an object setting. Its shape is the setting\'s schema in get-seo-settings.', 'thatseoagent' ),
                        ),
                    ),
                    'required'             => array( 'setting', 'value' ),
                ),
                'output_schema'       => array( 'type' => 'object' ),
            )
        );

        wp_register_ability(
            'thatseoagent/get-duplicates',
            array(
                'label'               => __( 'Get Duplicates', 'thatseoagent' ),
                'description'         => __( 'Lists the groups of published pages that share a search title or a written meta description (compared ignoring case and punctuation). Google asks for both to be distinct on each page. Descriptions generated from the content are compared by audit-post-seo and scan-seo-issues instead.', 'thatseoagent' ),
                'category'            => 'site',
                'execute_callback'    => array( 'ThatSeoAgent_Duplicates', 'report' ),
                'permission_callback' => array( 'ThatSeoAgent_Abilities', 'can_manage' ),
                'meta'                => ThatSeoAgent_Abilities::annotations( true ),
                'input_schema'        => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'default'              => array(),
                    'properties'           => array(),
                ),
                'output_schema'       => array(
                    'type'       => 'object',
                    'properties' => array(
                        'titles'       => array( 'type' => 'array' ),
                        'descriptions' => array( 'type' => 'array' ),
                    ),
                ),
            )
        );

        wp_register_ability(
            'thatseoagent/get-link-report',
            array(
                'label'               => __( 'Get Link Report', 'thatseoagent' ),
                'description'         => __( 'Reports how the site\'s pages link to each other: the pages nothing links to (orphans: not the menus, the header or footer, or another page\'s text — posts and catalog entries listed in archives are never orphans), the pages with links to addresses of the site that do not exist, and the navigation links that lead nowhere. Uses the last hour\'s reading of the links unless fresh is true, which reads the whole site again.', 'thatseoagent' ),
                'category'            => 'site',
                'execute_callback'    => array( __CLASS__, 'get_link_report' ),
                'permission_callback' => array( 'ThatSeoAgent_Abilities', 'can_manage' ),
                'meta'                => ThatSeoAgent_Abilities::annotations( true ),
                'input_schema'        => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'default'              => array(),
                    'properties'           => array(
                        'fresh' => array(
                            'type'    => 'boolean',
                            'default' => false,
                        ),
                    ),
                ),
                'output_schema'       => array(
                    'type'       => 'object',
                    'properties' => array(
                        'built'             => array( 'type' => 'string' ),
                        'orphans'           => array( 'type' => 'array' ),
                        'broken'            => array( 'type' => 'array' ),
                        'navigation_broken' => array( 'type' => 'array' ),
                        'unchecked'         => array( 'type' => 'integer' ),
                    ),
                ),
            )
        );

        wp_register_ability(
            'thatseoagent/get-site-bulletin',
            array(
                'label'               => __( 'Get Site Bulletin', 'thatseoagent' ),
                'description'         => __( 'Returns the site\'s SEO bulletin, the state the plugin\'s screen opens on: a warning level (green, yellow, orange, red), a headline, the warnings in force — each with what is wrong, why it matters and the admin screen where it is fixed — and the observations: sitemap, AI crawlers, homepage, identity, trust pages and the rest. Computed from the site as it is now.', 'thatseoagent' ),
                'category'            => 'site',
                'execute_callback'    => array( __CLASS__, 'get_bulletin' ),
                'permission_callback' => array( 'ThatSeoAgent_Abilities', 'can_manage' ),
                'meta'                => ThatSeoAgent_Abilities::annotations( true ),
                'input_schema'        => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'default'              => array(),
                    'properties'           => array(),
                ),
                'output_schema'       => array( 'type' => 'object' ),
            )
        );
    }

    /**
     * The settings the abilities expose: short name => option name and
     * what it does.
     *
     * @since 2.9.0
     * @return array<string, array{option: string, about: string}>
     */
    private static function options() {
        return array(
            'identity'       => array(
                'option' => ThatSeoAgent_Identity::OPTION_KEY,
                'about'  => __( 'Who the site represents, a person or an organization: name, description, logo (logo_id), default sharing image (default_og_image_id), X handle and social profile URLs. It feeds the Organization or Person node of the structured data.', 'thatseoagent' ),
            ),
            'homepage'       => array(
                'option' => ThatSeoAgent_Homepage::OPTION_KEY,
                'about'  => __( 'The homepage\'s search title and meta description. They accept %%sitename%%, %%tagline%% and %%sep%%.', 'thatseoagent' ),
            ),
            'verification'   => array(
                'option' => ThatSeoAgent_Verification::OPTION_KEY,
                'about'  => __( 'Webmaster verification codes printed on the homepage: google, bing, yandex, baidu, pinterest. The code alone, not the whole meta tag.', 'thatseoagent' ),
            ),
            'default_author' => array(
                'option' => ThatSeoAgent_Default_Author::OPTION_KEY,
                'about'  => __( 'Who the structured data credits for posts with no author of their own: author_name, author_url and author_type (Person or Organization).', 'thatseoagent' ),
            ),
            'llms_txt'       => array(
                'option' => ThatSeoAgent_Llms::OPTION_KEY,
                'about'  => __( 'Whether the site publishes /llms.txt and /llms-full.txt.', 'thatseoagent' ),
            ),
            'indexnow_key'   => array(
                'option' => ThatSeoAgent_IndexNow::OPTION_KEY,
                'about'  => __( 'The IndexNow key, which tells Bing and other engines about new and changed pages. Empty turns IndexNow off.', 'thatseoagent' ),
            ),
            'ai_crawlers'    => array(
                'option' => ThatSeoAgent_AI_Crawlers::OPTION_KEY,
                'about'  => __( 'Whether AI crawlers may read the site, written into robots.txt: allow or block per group (search, user, training), and per crawler in bots ("" follows its group).', 'thatseoagent' ),
            ),
            'crawl_cleanup'  => array(
                'option' => ThatSeoAgent_Crawl_Cleanup::OPTION_KEY,
                'about'  => __( 'Optional crawl cleanup: main_feed_only (only the main feed, no feeds per category, tag or author) and search_spam (filter spam searches).', 'thatseoagent' ),
            ),
            'tracking'       => array(
                'option' => ThatSeoAgent_Tracking::OPTION_KEY,
                'about'  => __( 'Analytics and tags: ga4 and google_ads IDs, and code printed in the head, after the opening body tag and in the footer of every page.', 'thatseoagent' ),
            ),
        );
    }

    /**
     * Permission to change a setting: manage_options, and unfiltered_html
     * for the one that injects code into every page.
     *
     * @since 2.9.0
     * @param array $input Ability input.
     * @return bool
     */
    public static function can_update_setting( $input ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        return ! ( isset( $input['setting'] ) && 'tracking' === $input['setting'] ) || current_user_can( 'unfiltered_html' );
    }

    /**
     * Every setting, or one.
     *
     * @since 2.9.0
     * @param array $input Ability input.
     * @return array|WP_Error
     */
    public static function get_settings( $input ) {
        $definitions = ThatSeoAgent_Settings::definitions();
        $settings    = array();

        foreach ( self::options() as $name => $option ) {
            if ( ! empty( $input['setting'] ) && $input['setting'] !== $name ) {
                continue;
            }

            $definition        = $definitions[ $option['option'] ];
            $settings[ $name ] = array(
                'about'  => $option['about'],
                'value'  => get_option( $option['option'], $definition['default'] ),
                'schema' => $definition['schema'],
            );
        }

        // Declared in code by the theme or plugin that owns each content
        // type, so shown here and changed nowhere.
        if ( empty( $input['setting'] ) ) {
            return array(
                'settings' => $settings,
                'catalogs' => (object) ThatSeoAgent_Product::describe(),
            );
        }

        return array( 'settings' => $settings );
    }

    /**
     * Change a setting.
     *
     * The value is merged into the stored one for object settings, checked
     * against the schema, then passed to the owner's sanitizer — the same
     * path a settings screen save or a /wp/v2/settings request takes.
     *
     * @since 2.9.0
     * @param array $input Ability input.
     * @return array|WP_Error
     */
    public static function update_settings( $input ) {
        $options = self::options();
        $name    = (string) $input['setting'];

        if ( ! isset( $options[ $name ] ) ) {
            return new WP_Error( 'thatseoagent_invalid_setting', __( 'That setting does not exist.', 'thatseoagent' ) );
        }

        $option     = $options[ $name ]['option'];
        $definition = ThatSeoAgent_Settings::definitions()[ $option ];
        $value      = $input['value'];

        if ( 'object' === $definition['type'] ) {
            if ( ! is_array( $value ) || ( $value && wp_is_numeric_array( $value ) ) ) {
                /* translators: %s: setting name. */
                return new WP_Error( 'thatseoagent_invalid_value', sprintf( __( '%s expects an object with the keys to change.', 'thatseoagent' ), $name ) );
            }

            $current = get_option( $option, $definition['default'] );
            $value   = array_merge( is_array( $current ) ? $current : array(), $value );
        }

        $valid = rest_validate_value_from_schema( $value, $definition['schema'], $name );
        if ( is_wp_error( $valid ) ) {
            return $valid;
        }

        $value = call_user_func( $definition['sanitize'], rest_sanitize_value_from_schema( $value, $definition['schema'], $name ) );

        update_option( $option, $value );

        return self::get_settings( array( 'setting' => $name ) );
    }

    /**
     * The link report.
     *
     * @since 2.9.0
     * @param array $input Ability input.
     * @return array
     */
    public static function get_link_report( $input ) {
        return ThatSeoAgent_Links::report( ! empty( $input['fresh'] ) );
    }

    /**
     * The bulletin, with the admin URL where each warning is fixed.
     *
     * @since 2.9.0
     * @return array
     */
    public static function get_bulletin() {
        $bulletin = ThatSeoAgent_Bulletin::get();
        $levels   = ThatSeoAgent_Bulletin::levels();

        $warnings = array();
        foreach ( (array) $bulletin['warnings'] as $warning ) {
            $action     = isset( $warning['action'] ) ? $warning['action'] : null;
            $warnings[] = array(
                'level'  => $warning['level'],
                'title'  => $warning['title'],
                'detail' => $warning['detail'],
                'fix'    => $action ? array(
                    'label' => $action['label'],
                    'url'   => ThatSeoAgent_App::action_url( $action ),
                ) : null,
            );
        }

        return array(
            'level'        => $bulletin['level'],
            'level_name'   => isset( $levels[ $bulletin['level'] ] ) ? $levels[ $bulletin['level'] ]['name'] : $bulletin['level'],
            'headline'     => $bulletin['headline'],
            'summary'      => $bulletin['summary'],
            'warnings'     => $warnings,
            'observations' => array_values( (array) $bulletin['observations'] ),
        );
    }
}
