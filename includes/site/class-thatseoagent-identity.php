<?php
/**
 * Site Identity
 *
 * Who the site represents (a Person or an Organization), their name,
 * description, logo, default OG image, Twitter @handle, and social profile
 * URLs: the settings, and what they make of the site in the markup and the
 * meta tags — the Person node, the Organization's details, the handle.
 * ThatSeoAgent_Schema and ThatSeoAgent_Meta ask for them and run the public
 * filters (thatseoagent_person_schema, thatseoagent_organization_schema,
 * thatseoagent_primary_entity, thatseoagent_twitter_handle) on top.
 *
 * @package ThatSeoAgent
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Identity {

    /**
     * Option key storing all identity settings as a single array.
     *
     * @var string
     */
    const OPTION_KEY = 'thatseoagent_identity';

    /**
     * The option as ThatSeoAgent_Settings registers it: type, sanitizer,
     * default and REST schema.
     *
     * @since 1.20.0 Moved from ThatSeoAgent_Settings::definitions().
     * @return array{type: string, sanitize: callable, default: mixed, schema: array}
     */
    public static function setting() {
        $social = array();
        foreach ( array_keys( self::get_social_networks() ) as $network ) {
            $social[ $network ] = array( 'type' => 'string' );
        }

        return array(
            'type'     => 'object',
            'sanitize' => array( __CLASS__, 'sanitize' ),
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
        );
    }

    /**
     * Supported social profile networks.
     *
     * Order here determines display order in the settings UI.
     *
     * @return array<string, string> slug => label
     */
    public static function get_social_networks() {
        return array(
            'twitter'   => __( 'Twitter / X', 'thatseoagent' ),
            'github'    => __( 'GitHub', 'thatseoagent' ),
            'facebook'  => __( 'Facebook', 'thatseoagent' ),
            'linkedin'  => __( 'LinkedIn', 'thatseoagent' ),
            'instagram' => __( 'Instagram', 'thatseoagent' ),
            'youtube'   => __( 'YouTube', 'thatseoagent' ),
            'mastodon'  => __( 'Mastodon', 'thatseoagent' ),
        );
    }

    /**
     * Get the full settings array with defaults applied.
     *
     * @return array
     */
    public static function get_settings() {
        $saved = get_option( self::OPTION_KEY, array() );

        $defaults = array(
            'type'              => 'person', // 'person' | 'organization'
            'name'              => '',
            'description'       => '',
            'logo_id'           => 0,
            'default_og_image_id' => 0,
            'twitter_handle'    => '',
            'social'            => array(), // network slug => url
        );

        return wp_parse_args( $saved, $defaults );
    }

    /**
     * Whether the site owner has ever saved the identity settings.
     *
     * get_settings() always returns a usable array thanks to the defaults,
     * so it cannot distinguish "configured as a Person" from "never touched".
     * The Person schema node depends on that difference.
     *
     * @since 1.8.0
     * @return bool
     */
    public static function is_configured() {
        return false !== get_option( self::OPTION_KEY, false );
    }

    /**
     * Whether there is something to recognize the site by: a logo, a
     * description or social profiles.
     *
     * Saving the settings stores the option even when nothing in it was
     * filled in, so is_configured() alone proves nothing about that.
     *
     * @since 1.20.0
     * @return bool
     */
    public static function is_recognizable() {
        if ( ! self::is_configured() ) {
            return false;
        }

        $settings = self::get_settings();

        return $settings['logo_id'] || '' !== $settings['description'] || ! empty( $settings['social'] );
    }

    /**
     * Which entity this site represents, according to saved settings.
     *
     * The single answer to "is this site a Person?". Three callers used to
     * recombine is_configured() and the type themselves, and one of them
     * (the Organization filter) used a different rule than its siblings.
     *
     * An untouched install reports 'organization': the default type is
     * 'person', but with nothing saved there is no person to describe.
     *
     * @since 1.9.0
     * @return string 'person' or 'organization'.
     */
    public static function primary_entity() {
        if ( ! self::is_configured() ) {
            return 'organization';
        }

        $settings = self::get_settings();

        return 'person' === $settings['type'] ? 'person' : 'organization';
    }

    /**
     * The site's Person node, when the site represents a person.
     *
     * Without saved settings there is nothing to describe: a Person node
     * named after the site would add a second, contentless entity to the
     * graph.
     *
     * @since 2.7.0 Moved from ThatSeoAgent_Identity_Applier.
     * @return array|null
     */
    public static function person_node() {
        if ( 'person' !== self::primary_entity() ) {
            return null;
        }

        $settings = self::get_settings();

        $node = array(
            '@type' => 'Person',
            '@id'   => home_url( '/#person' ),
            'name'  => $settings['name'] ? $settings['name'] : get_bloginfo( 'name' ),
            'url'   => home_url( '/' ),
        );

        if ( $settings['description'] ) {
            $node['description'] = $settings['description'];
        }

        $logo = ThatSeoAgent_Image::of( $settings['logo_id'] );
        if ( $logo ) {
            $node['image'] = ThatSeoAgent_Image::object( $logo, home_url( '/#personimage' ) );
        }

        $same_as = self::same_as( $settings );
        if ( $same_as ) {
            $node['sameAs'] = $same_as;
        }

        return $node;
    }

    /**
     * The Organization node with what the settings say about it: its name,
     * description, logo and profiles over the ones taken from WordPress.
     * Unchanged when the site represents a person.
     *
     * @since 2.7.0 Moved from ThatSeoAgent_Identity_Applier.
     * @param array $node The Organization node built from WordPress.
     * @return array
     */
    public static function organization_node( array $node ) {
        if ( 'organization' !== self::primary_entity() ) {
            return $node;
        }

        $settings = self::get_settings();

        if ( $settings['name'] ) {
            $node['name'] = $settings['name'];
        }

        if ( $settings['description'] ) {
            $node['description'] = $settings['description'];
        }

        $logo = ThatSeoAgent_Image::of( $settings['logo_id'] );
        if ( $logo ) {
            $node['logo'] = ThatSeoAgent_Image::object( $logo );
        }

        $same_as = self::same_as( $settings );
        if ( $same_as ) {
            $node['sameAs'] = $same_as;
        }

        return $node;
    }

    /**
     * The site's X (Twitter) handle, as saved.
     *
     * @since 2.7.0 Moved from ThatSeoAgent_Identity_Applier.
     * @return string
     */
    public static function twitter_handle() {
        $settings = self::get_settings();

        return (string) $settings['twitter_handle'];
    }

    /**
     * The social profile URLs, each once.
     *
     * @since 2.7.0 Moved from ThatSeoAgent_Identity_Applier.
     * @param array $settings Identity settings.
     * @return array<int, string>
     */
    private static function same_as( array $settings ) {
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

    /**
     * Register the hooks: the settings section and the media picker.
     *
     * @since 1.19.0
     */
    public static function register() {
        add_action( 'admin_init', array( __CLASS__, 'register_section' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
    }

    /**
     * Register the settings sections and fields.
     *
     * The option itself is registered by ThatSeoAgent_Settings.
     *
     * @since 1.6.0 As register().
     */
    public static function register_section() {
        add_settings_section(
            'thatseoagent_identity_section',
            __( 'Who the site is', 'thatseoagent' ),
            function () {
                echo '<p>' . esc_html__( 'Tell search engines whether the site belongs to a person or an organization, and how to recognize it: name, logo and social profiles.', 'thatseoagent' ) . '</p>';
            },
            'thatseoagent_settings'
        );

        $fields = array(
            'type'                => array( 'label' => __( 'The site belongs to', 'thatseoagent' ),      'cb' => 'render_type_field' ),
            'name'                => array( 'label' => __( 'Name', 'thatseoagent' ),                     'cb' => 'render_text_field' ),
            'description'         => array( 'label' => __( 'Short description', 'thatseoagent' ),        'cb' => 'render_textarea_field' ),
            'logo_id'             => array( 'label' => __( 'Logo or photo', 'thatseoagent' ),            'cb' => 'render_media_field' ),
            'default_og_image_id' => array( 'label' => __( 'Default sharing image', 'thatseoagent' ),    'cb' => 'render_media_field' ),
            'twitter_handle'      => array( 'label' => __( 'X (Twitter) username', 'thatseoagent' ),     'cb' => 'render_text_field' ),
            'social'              => array( 'label' => __( 'Social profiles', 'thatseoagent' ),          'cb' => 'render_social_field' ),
        );

        foreach ( $fields as $key => $config ) {
            add_settings_field(
                'thatseoagent_identity_' . $key,
                $config['label'],
                array( __CLASS__, $config['cb'] ),
                'thatseoagent_settings',
                'thatseoagent_identity_section',
                array( 'key' => $key )
            );
        }
    }

    /**
     * Render the identity type radio (Person | Organization).
     */
    public static function render_type_field( $args ) {
        // Not get_settings()['type']: its default is 'person', and an
        // unconfigured site is an Organization. Pre-selecting "Person" meant
        // that saving the page for any other reason switched the site's
        // schema to a Person.
        $value = self::primary_entity();
        ?>
        <fieldset>
            <label style="margin-right: 20px;">
                <input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[type]" value="person" <?php checked( $value, 'person' ); ?>>
                <?php esc_html_e( 'A person', 'thatseoagent' ); ?>
            </label>
            <label>
                <input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[type]" value="organization" <?php checked( $value, 'organization' ); ?>>
                <?php esc_html_e( 'An organization or business', 'thatseoagent' ); ?>
            </label>
            <p class="description"><?php esc_html_e( 'Search engines describe the site as one or the other.', 'thatseoagent' ); ?></p>
        </fieldset>
        <?php
    }

    /**
     * Render a plain text field bound to a top-level identity key.
     */
    public static function render_text_field( $args ) {
        $settings = self::get_settings();
        $key      = $args['key'];
        $value    = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';

        $placeholder = '';
        $description = '';

        if ( 'twitter_handle' === $key ) {
            $placeholder = __( '@username', 'thatseoagent' );
            $description = __( 'Shown on link previews on X. The @ is optional.', 'thatseoagent' );
        } elseif ( 'name' === $key ) {
            $placeholder = get_bloginfo( 'name' );
            $description = __( 'The name of the person or organization this site represents.', 'thatseoagent' );
        }

        printf(
            '<input type="text" name="%s[%s]" id="thatseoagent_identity_%s" value="%s" class="regular-text" placeholder="%s">',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $key ),
            esc_attr( $key ),
            esc_attr( $value ),
            esc_attr( $placeholder )
        );

        if ( $description ) {
            echo '<p class="description">' . esc_html( $description ) . '</p>';
        }
    }

    /**
     * Render the description textarea.
     */
    public static function render_textarea_field( $args ) {
        $settings = self::get_settings();
        $value    = isset( $settings[ $args['key'] ] ) ? (string) $settings[ $args['key'] ] : '';
        ?>
        <textarea
            name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $args['key'] ); ?>]"
            id="thatseoagent_identity_<?php echo esc_attr( $args['key'] ); ?>"
            rows="3"
            class="large-text"
        ><?php echo esc_textarea( $value ); ?></textarea>
        <p class="description"><?php esc_html_e( 'One or two sentences about who you are and what you do.', 'thatseoagent' ); ?></p>
        <?php
    }

    /**
     * Render a media picker field (attachment ID).
     */
    public static function render_media_field( $args ) {
        $settings      = self::get_settings();
        $key           = $args['key'];
        $attachment_id = isset( $settings[ $key ] ) ? (int) $settings[ $key ] : 0;

        $preview_url = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';

        $description = '';
        if ( 'logo_id' === $key ) {
            $description = __( "Shown to search engines next to the site's name. Separate from the theme's logo.", 'thatseoagent' );
        } elseif ( 'default_og_image_id' === $key ) {
            $description = __( 'Used when a page without an image of its own is shared on social media.', 'thatseoagent' );
        }
        ?>
        <div class="thatseoagent-media-field" data-key="<?php echo esc_attr( $key ); ?>">
            <input
                type="hidden"
                name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $key ); ?>]"
                id="thatseoagent_identity_<?php echo esc_attr( $key ); ?>"
                value="<?php echo esc_attr( $attachment_id ); ?>"
            >
            <div class="thatseoagent-media-preview" style="margin-bottom: 8px;">
                <?php if ( $preview_url ) : ?>
                    <img src="<?php echo esc_url( $preview_url ); ?>" alt="" style="max-width: 150px; height: auto; border: 1px solid #ccd0d4; padding: 4px; background: #fff;">
                <?php endif; ?>
            </div>
            <button type="button" class="button thatseoagent-media-select"><?php esc_html_e( 'Select Image', 'thatseoagent' ); ?></button>
            <button type="button" class="button thatseoagent-media-remove" <?php echo $attachment_id ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove', 'thatseoagent' ); ?></button>
            <?php if ( $description ) : ?>
                <p class="description"><?php echo esc_html( $description ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render the social profile URLs block.
     */
    public static function render_social_field( $args ) {
        $settings = self::get_settings();
        $social   = is_array( $settings['social'] ) ? $settings['social'] : array();
        $networks = self::get_social_networks();
        ?>
        <div class="thatseoagent-social-grid" style="display: grid; grid-template-columns: max-content 1fr; gap: 8px 12px; max-width: 600px;">
            <?php foreach ( $networks as $slug => $label ) :
                $url = isset( $social[ $slug ] ) ? (string) $social[ $slug ] : '';
                ?>
                <label for="thatseoagent_identity_social_<?php echo esc_attr( $slug ); ?>" style="align-self: center;">
                    <?php echo esc_html( $label ); ?>
                </label>
                <input
                    type="url"
                    id="thatseoagent_identity_social_<?php echo esc_attr( $slug ); ?>"
                    name="<?php echo esc_attr( self::OPTION_KEY ); ?>[social][<?php echo esc_attr( $slug ); ?>]"
                    value="<?php echo esc_attr( $url ); ?>"
                    placeholder="https://..."
                    class="regular-text"
                >
            <?php endforeach; ?>
        </div>
        <p class="description"><?php esc_html_e( 'Lets search engines connect the site with these profiles.', 'thatseoagent' ); ?></p>
        <?php
    }

    /**
     * Sanitize the full identity settings array on save.
     *
     * @param array $input Raw submitted input.
     * @return array Sanitized settings ready for wp_options.
     */
    public static function sanitize( $input ) {
        $clean = array();

        // Type — strict whitelist.
        if ( isset( $input['type'] ) && in_array( $input['type'], array( 'person', 'organization' ), true ) ) {
            $clean['type'] = $input['type'];
        } else {
            $clean['type'] = 'person';
        }

        $clean['name']        = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';
        $clean['description'] = isset( $input['description'] ) ? sanitize_textarea_field( $input['description'] ) : '';
        $clean['logo_id']     = isset( $input['logo_id'] ) ? max( 0, (int) $input['logo_id'] ) : 0;
        $clean['default_og_image_id'] = isset( $input['default_og_image_id'] ) ? max( 0, (int) $input['default_og_image_id'] ) : 0;

        // Twitter handle — strip leading @ for storage; filter re-adds on output.
        $handle = isset( $input['twitter_handle'] ) ? sanitize_text_field( $input['twitter_handle'] ) : '';
        $clean['twitter_handle'] = ltrim( $handle, '@' );

        // Social URLs — per-network sanitization, drop empties.
        $clean['social'] = array();
        if ( isset( $input['social'] ) && is_array( $input['social'] ) ) {
            $allowed = array_keys( self::get_social_networks() );
            foreach ( $input['social'] as $slug => $url ) {
                if ( ! in_array( $slug, $allowed, true ) ) {
                    continue;
                }
                $url = trim( (string) $url );
                if ( '' === $url ) {
                    continue;
                }
                $sanitized = esc_url_raw( $url );
                if ( $sanitized ) {
                    $clean['social'][ $slug ] = $sanitized;
                }
            }
        }

        return $clean;
    }

    /**
     * Enqueue the media uploader + a small inline script wiring the pickers.
     *
     * Only runs on the ThatSeoAgent settings page.
     *
     * @param string $hook_suffix Current admin page hook.
     */
    public static function enqueue_assets( $hook_suffix ) {
        if ( ThatSeoAgent_App::PAGE_HOOK !== $hook_suffix ) {
            return;
        }

        wp_enqueue_media();

        $script = <<<'JS'
( function ( $ ) {
    $( document ).on( 'click', '.thatseoagent-media-select', function ( e ) {
        e.preventDefault();
        var $wrap = $( this ).closest( '.thatseoagent-media-field' );
        var frame = wp.media( {
            title: 'Select Image',
            button: { text: 'Use this image' },
            multiple: false,
            library: { type: 'image' }
        } );
        frame.on( 'select', function () {
            var attachment = frame.state().get( 'selection' ).first().toJSON();
            $wrap.find( 'input[type="hidden"]' ).val( attachment.id );
            var size = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
            $wrap.find( '.thatseoagent-media-preview' ).html( '<img src="' + size + '" alt="" style="max-width:150px;height:auto;border:1px solid #ccd0d4;padding:4px;background:#fff;">' );
            $wrap.find( '.thatseoagent-media-remove' ).show();
        } );
        frame.open();
    } );

    $( document ).on( 'click', '.thatseoagent-media-remove', function ( e ) {
        e.preventDefault();
        var $wrap = $( this ).closest( '.thatseoagent-media-field' );
        $wrap.find( 'input[type="hidden"]' ).val( '' );
        $wrap.find( '.thatseoagent-media-preview' ).empty();
        $( this ).hide();
    } );
} )( jQuery );
JS;

        wp_add_inline_script( 'jquery-core', $script );
    }

    /**
     * This module's part of the bulletin: whether search engines can tell
     * who runs the site.
     *
     * @since 2.7.0 Moved from ThatSeoAgent_Bulletin::compose().
     * @return array{observations: array, warnings: array}
     */
    public static function bulletin() {
        $identity = self::is_recognizable();
        $warnings = array();

        if ( ! $identity ) {
            $warnings[] = ThatSeoAgent_Bulletin::warning(
                'yellow',
                __( 'Search engines do not know who runs the site', 'thatseoagent' ),
                __( 'Say whether the site is a person or an organization, and add its logo and social profiles.', 'thatseoagent' ),
                __( 'Set up the identity', 'thatseoagent' ),
                'identity'
            );
        }

        return array(
            'observations' => array(
                ThatSeoAgent_Bulletin::observation( 'identity', __( 'Identity', 'thatseoagent' ), $identity ? 'ok' : 'yellow', $identity ? __( 'Set', 'thatseoagent' ) : __( 'Missing', 'thatseoagent' ) ),
            ),
            'warnings'     => $warnings,
        );
    }
}
