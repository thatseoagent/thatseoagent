<?php
/**
 * The default author: who is credited on posts that have no author.
 *
 * Stored in the `thatseoagent_schema` option and read by ThatSeoAgent_Schema when it
 * builds an Article's author. This module owns the settings section, its
 * fields and the sanitizer.
 *
 * @package ThatSeoAgent
 * @since 1.19.0 Moved out of ThatSeoAgent_Admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Default_Author {

    /**
     * Option holding the default author. Named after the schema it feeds,
     * from before it had a module of its own.
     */
    const OPTION_KEY = 'thatseoagent_schema';

    /**
     * Whether a default author is set.
     *
     * @since 2.6.0
     * @return bool
     */
    public static function is_set() {
        $saved = get_option( self::OPTION_KEY, array() );

        return is_array( $saved ) && ! empty( $saved['author_name'] );
    }

    /**
     * The default author with WordPress's placeholders for what is not
     * saved: the site's name and address, as a Person.
     *
     * @since 1.3.0 As ThatSeoAgent_Schema::get_publisher_defaults().
     * @since 2.7.0 Moved here, to the owner of the option.
     * @return array{author_name: string, author_url: string, author_type: string}
     */
    public static function defaults() {
        $saved = get_option( self::OPTION_KEY, array() );

        return wp_parse_args(
            is_array( $saved ) ? $saved : array(),
            array(
                'author_name' => get_bloginfo( 'name' ),
                'author_url'  => home_url( '/' ),
                'author_type' => 'Person',
            )
        );
    }

    /**
     * Who an unattributed post is credited to, as the markup names them.
     *
     * @since 2.7.0
     * @return array{@type: string, name: string, url: string}|null Null when
     *         no default author is set.
     */
    public static function credited() {
        if ( ! self::is_set() ) {
            return null;
        }

        $author = self::defaults();

        return array(
            '@type' => $author['author_type'],
            'name'  => $author['author_name'],
            'url'   => $author['author_url'],
        );
    }

    /**
     * Whether a post names nobody as its author: none assigned, a user that
     * no longer exists, or one without a display name.
     *
     * @since 2.7.0
     * @param WP_Post $post Post.
     * @return bool
     */
    public static function is_unattributed( WP_Post $post ) {
        $user = $post->post_author ? get_userdata( (int) $post->post_author ) : false;

        return ! $user || '' === (string) $user->display_name;
    }

    /**
     * How many published articles have no author, as is_unattributed() says:
     * none assigned, a user that no longer exists, or one without a display
     * name. They are credited to the site itself.
     *
     * @since 2.6.0
     * @return int
     */
    public static function unattributed_count() {
        global $wpdb;

        $types = ThatSeoAgent_Schema::article_post_types();
        if ( ! $types ) {
            return 0;
        }

        $placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // A count with a join to users no query API offers; read on the
        // bulletin, memoised with it.
        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->users} u ON u.ID = p.post_author
                 WHERE p.post_status = 'publish'
                   AND p.post_type IN ($placeholders)
                   AND ( u.ID IS NULL OR u.display_name = '' )",
                $types
            )
        );
        // phpcs:enable

        return $count;
    }

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
                    'author_name' => array( 'type' => 'string' ),
                    'author_url'  => array( 'type' => 'string' ),
                    'author_type' => array(
                        'type' => 'string',
                        'enum' => array( 'Person', 'Organization' ),
                    ),
                ),
            ),
        );
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
     * @since 1.3.0 As ThatSeoAgent_Admin::register_settings().
     */
    public static function register_section() {
        add_settings_section(
            'thatseoagent_schema_section',
            __( 'Default author', 'thatseoagent' ),
            function () {
                echo '<p>' . esc_html__( 'Credited on posts that have no author assigned. Leave blank to credit the site itself.', 'thatseoagent' ) . '</p>';
            },
            'thatseoagent_settings'
        );

        $fields = array(
            'author_name' => __( 'Name', 'thatseoagent' ),
            'author_url'  => __( 'Web address', 'thatseoagent' ),
            'author_type' => __( 'The author is', 'thatseoagent' ),
        );

        foreach ( $fields as $key => $label ) {
            add_settings_field(
                'thatseoagent_schema_' . $key,
                $label,
                array( __CLASS__, 'render_field' ),
                'thatseoagent_settings',
                'thatseoagent_schema_section',
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
        $saved    = get_option( self::OPTION_KEY, array() );
        $defaults = self::defaults();
        $key      = $args['key'];
        $value    = isset( $saved[ $key ] ) ? $saved[ $key ] : '';

        if ( 'author_type' === $key ) {
            printf(
                '<select name="thatseoagent_schema[%s]" id="thatseoagent_schema_%s">',
                esc_attr( $key ),
                esc_attr( $key )
            );
            $types = array(
                'Person'       => __( 'A person', 'thatseoagent' ),
                'Organization' => __( 'An organization', 'thatseoagent' ),
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
                '<input type="%s" name="thatseoagent_schema[%s]" id="thatseoagent_schema_%s" value="%s" placeholder="%s" class="regular-text">',
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

    /**
     * This module's part of the bulletin: articles credited to nobody, for
     * which the site stands in as the author. Only while no default author
     * is set: with one, those posts do name someone.
     *
     * @since 2.7.0 Moved from ThatSeoAgent_Bulletin::compose().
     * @return array{observations: array, warnings: array}
     */
    public static function bulletin() {
        $unattributed = self::is_set() ? 0 : self::unattributed_count();
        $warnings     = array();

        if ( $unattributed ) {
            $warnings[] = ThatSeoAgent_Bulletin::warning(
                'yellow',
                /* translators: %d: number of posts. */
                sprintf( _n( '%d post has no author', '%d posts have no author', $unattributed, 'thatseoagent' ), $unattributed ),
                __( 'They are credited to the site itself. Google asks for a byline where one is expected: assign each post its author, whose profile can carry a job title and profiles elsewhere, or set a default author in the settings.', 'thatseoagent' ),
                __( 'See the posts', 'thatseoagent' ),
                'posts'
            );
        }

        return array(
            'observations' => array(),
            'warnings'     => $warnings,
        );
    }
}
