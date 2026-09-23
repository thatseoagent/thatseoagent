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
     * Register settings, sections, and fields.
     *
     * Hooked on admin_init from Lean_SEO::init_hooks().
     */
    public static function register() {
        register_setting( 'lean_seo_settings', self::OPTION_KEY, array(
            'type'              => 'array',
            'sanitize_callback' => array( __CLASS__, 'sanitize' ),
            'default'           => array(),
        ) );

        add_settings_section(
            'lean_seo_identity_section',
            __( 'Site Identity', 'lean-seo' ),
            function () {
                echo '<p>' . esc_html__( 'Tell search engines who this site represents. These values populate Open Graph meta tags and JSON-LD schema (Person or Organization).', 'lean-seo' ) . '</p>';
            },
            'lean_seo_settings'
        );

        $fields = array(
            'type'                => array( 'label' => __( 'Identity Type', 'lean-seo' ),       'cb' => 'render_type_field' ),
            'name'                => array( 'label' => __( 'Name', 'lean-seo' ),                'cb' => 'render_text_field' ),
            'description'         => array( 'label' => __( 'Description / Bio', 'lean-seo' ),  'cb' => 'render_textarea_field' ),
            'logo_id'             => array( 'label' => __( 'Logo / Photo', 'lean-seo' ),       'cb' => 'render_media_field' ),
            'default_og_image_id' => array( 'label' => __( 'Default OG Image', 'lean-seo' ),   'cb' => 'render_media_field' ),
            'twitter_handle'      => array( 'label' => __( 'Twitter @handle', 'lean-seo' ),    'cb' => 'render_text_field' ),
            'social'              => array( 'label' => __( 'Social Profiles', 'lean-seo' ),    'cb' => 'render_social_field' ),
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
        $settings = self::get_settings();
        $value    = $settings['type'];
        ?>
        <fieldset>
            <label style="margin-right: 20px;">
                <input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[type]" value="person" <?php checked( $value, 'person' ); ?>>
                <?php esc_html_e( 'Person', 'lean-seo' ); ?>
            </label>
            <label>
                <input type="radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[type]" value="organization" <?php checked( $value, 'organization' ); ?>>
                <?php esc_html_e( 'Organization', 'lean-seo' ); ?>
            </label>
            <p class="description"><?php esc_html_e( 'Determines whether the site outputs Person or Organization schema.', 'lean-seo' ); ?></p>
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
            $description = __( 'Used as twitter:site on social cards. The leading @ is optional.', 'lean-seo' );
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
        <p class="description"><?php esc_html_e( 'Short bio or organization description used in schema output.', 'lean-seo' ); ?></p>
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
            $description = __( 'Used in Person/Organization schema output. Separate from the theme Site Logo.', 'lean-seo' );
        } elseif ( 'default_og_image_id' === $key ) {
            $description = __( 'Fallback Open Graph image for posts without a featured image.', 'lean-seo' );
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
        <p class="description"><?php esc_html_e( 'Emitted as sameAs links in schema so search engines can link your site to these profiles.', 'lean-seo' ); ?></p>
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
        if ( Lean_SEO_Admin::PAGE_HOOK !== $hook_suffix ) {
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

/**
 * Identity Applier
 *
 * Reads Lean_SEO_Identity settings and wires them into the lean_seo_*
 * filters introduced in 1.5.0. Kept separate from the settings class so
 * storage and output concerns stay decoupled.
 *
 * @since 1.6.0
 */
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
