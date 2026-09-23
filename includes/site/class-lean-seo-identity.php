<?php
/**
 * Site Identity Settings + Applier
 *
 * Provides a UI for configuring who the site represents (a Person or
 * an Organization), their name, description, logo, default OG image,
 * Twitter @handle, and social profile URLs.
 *
 * On its own the class holds no SEO logic — it only persists settings.
 * The Lean_SEO_Identity_Applier (same file, below) reads the stored
 * values and feeds them into the filters exposed by PR 1
 * (lean_seo_twitter_handle, lean_seo_default_image, lean_seo_person_schema,
 * lean_seo_organization_schema).
 *
 * @package Lean_SEO
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lean_SEO_Identity {

    /**
     * Option key storing all identity settings as a single array.
     *
     * @var string
     */
    const OPTION_KEY = 'lean_seo_identity';

    /**
     * Supported social profile networks.
     *
     * Order here determines display order in the settings UI.
     *
     * @return array<string, string> slug => label
     */
    public static function get_social_networks() {
        return array(
            'twitter'   => __( 'Twitter / X', 'lean-seo' ),
            'github'    => __( 'GitHub', 'lean-seo' ),
            'facebook'  => __( 'Facebook', 'lean-seo' ),
            'linkedin'  => __( 'LinkedIn', 'lean-seo' ),
            'instagram' => __( 'Instagram', 'lean-seo' ),
            'youtube'   => __( 'YouTube', 'lean-seo' ),
            'mastodon'  => __( 'Mastodon', 'lean-seo' ),
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
     * The option itself is registered by Lean_SEO_Settings.
     *
     * @since 1.6.0 As register().
     */
    public static function register_section() {
        add_settings_section(
            'lean_seo_identity_section',
            __( 'Who the site is', 'lean-seo' ),
            function () {
                echo '<p>' . esc_html__( 'Tell search engines whether the site belongs to a person or an organization, and how to recognize it: name, logo and social profiles.', 'lean-seo' ) . '</p>';
            },
            'lean_seo_settings'
        );

        $fields = array(
            'type'                => array( 'label' => __( 'The site belongs to', 'lean-seo' ),      'cb' => 'render_type_field' ),
            'name'                => array( 'label' => __( 'Name', 'lean-seo' ),                     'cb' => 'render_text_field' ),
            'description'         => array( 'label' => __( 'Short description', 'lean-seo' ),        'cb' => 'render_textarea_field' ),
            'logo_id'             => array( 'label' => __( 'Logo or photo', 'lean-seo' ),            'cb' => 'render_media_field' ),
            'default_og_image_id' => array( 'label' => __( 'Default sharing image', 'lean-seo' ),    'cb' => 'render_media_field' ),
            'twitter_handle'      => array( 'label' => __( 'X (Twitter) username', 'lean-seo' ),     'cb' => 'render_text_field' ),
            'social'              => array( 'label' => __( 'Social profiles', 'lean-seo' ),          'cb' => 'render_social_field' ),
        );

        foreach ( $fields as $key => $config ) {
            add_settings_field(
                'lean_seo_identity_' . $key,
                $config['label'],
                array( __CLASS__, $config['cb'] ),
                'lean_seo_settings',
                'lean_seo_identity_section',
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
                <?php esc_html_e( 'A person', 'lean-seo' ); ?>
            </label>
            <label>
                <input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[type]" value="organization" <?php checked( $value, 'organization' ); ?>>
                <?php esc_html_e( 'An organization or business', 'lean-seo' ); ?>
            </label>
            <p class="description"><?php esc_html_e( 'Search engines describe the site as one or the other.', 'lean-seo' ); ?></p>
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
            $placeholder = __( '@username', 'lean-seo' );
            $description = __( 'Shown on link previews on X. The @ is optional.', 'lean-seo' );
        } elseif ( 'name' === $key ) {
            $placeholder = get_bloginfo( 'name' );
            $description = __( 'The name of the person or organization this site represents.', 'lean-seo' );
        }

        printf(
            '<input type="text" name="%s[%s]" id="lean_seo_identity_%s" value="%s" class="regular-text" placeholder="%s">',
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
            id="lean_seo_identity_<?php echo esc_attr( $args['key'] ); ?>"
            rows="3"
            class="large-text"
        ><?php echo esc_textarea( $value ); ?></textarea>
        <p class="description"><?php esc_html_e( 'One or two sentences about who you are and what you do.', 'lean-seo' ); ?></p>
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
            $description = __( "Shown to search engines next to the site's name. Separate from the theme's logo.", 'lean-seo' );
        } elseif ( 'default_og_image_id' === $key ) {
            $description = __( 'Used when a page without an image of its own is shared on social media.', 'lean-seo' );
        }
        ?>
        <div class="lean-seo-media-field" data-key="<?php echo esc_attr( $key ); ?>">
            <input
                type="hidden"
                name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $key ); ?>]"
                id="lean_seo_identity_<?php echo esc_attr( $key ); ?>"
                value="<?php echo esc_attr( $attachment_id ); ?>"
            >
            <div class="lean-seo-media-preview" style="margin-bottom: 8px;">
                <?php if ( $preview_url ) : ?>
                    <img src="<?php echo esc_url( $preview_url ); ?>" alt="" style="max-width: 150px; height: auto; border: 1px solid #ccd0d4; padding: 4px; background: #fff;">
                <?php endif; ?>
            </div>
            <button type="button" class="button lean-seo-media-select"><?php esc_html_e( 'Select Image', 'lean-seo' ); ?></button>
            <button type="button" class="button lean-seo-media-remove" <?php echo $attachment_id ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove', 'lean-seo' ); ?></button>
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
        <div class="lean-seo-social-grid" style="display: grid; grid-template-columns: max-content 1fr; gap: 8px 12px; max-width: 600px;">
            <?php foreach ( $networks as $slug => $label ) :
                $url = isset( $social[ $slug ] ) ? (string) $social[ $slug ] : '';
                ?>
                <label for="lean_seo_identity_social_<?php echo esc_attr( $slug ); ?>" style="align-self: center;">
                    <?php echo esc_html( $label ); ?>
                </label>
                <input
                    type="url"
                    id="lean_seo_identity_social_<?php echo esc_attr( $slug ); ?>"
                    name="<?php echo esc_attr( self::OPTION_KEY ); ?>[social][<?php echo esc_attr( $slug ); ?>]"
                    value="<?php echo esc_attr( $url ); ?>"
                    placeholder="https://..."
                    class="regular-text"
                >
            <?php endforeach; ?>
        </div>
        <p class="description"><?php esc_html_e( 'Lets search engines connect the site with these profiles.', 'lean-seo' ); ?></p>
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
     * Only runs on the Lean SEO settings page.
     *
     * @param string $hook_suffix Current admin page hook.
     */
    public static function enqueue_assets( $hook_suffix ) {
        if ( Lean_SEO_App::PAGE_HOOK !== $hook_suffix ) {
            return;
        }

        wp_enqueue_media();

        $script = <<<'JS'
( function ( $ ) {
    $( document ).on( 'click', '.lean-seo-media-select', function ( e ) {
        e.preventDefault();
        var $wrap = $( this ).closest( '.lean-seo-media-field' );
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
            $wrap.find( '.lean-seo-media-preview' ).html( '<img src="' + size + '" alt="" style="max-width:150px;height:auto;border:1px solid #ccd0d4;padding:4px;background:#fff;">' );
            $wrap.find( '.lean-seo-media-remove' ).show();
        } );
        frame.open();
    } );

    $( document ).on( 'click', '.lean-seo-media-remove', function ( e ) {
        e.preventDefault();
        var $wrap = $( this ).closest( '.lean-seo-media-field' );
        $wrap.find( 'input[type="hidden"]' ).val( '' );
        $wrap.find( '.lean-seo-media-preview' ).empty();
        $( this ).hide();
    } );
} )( jQuery );
JS;

        wp_add_inline_script( 'jquery-core', $script );
    }
}
