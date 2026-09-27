<?php
/**
 * SEO fields of a term: the search title, meta description and noindex of
 * its archive page.
 *
 * A category, a tag, a brand or a product category is a page of its own —
 * often one of the site's most visited — but until 2.9.0 its title was
 * whatever WordPress built and its description the term's description or
 * a sentence in English. The owner of `_thatseoagent_title`,
 * `_thatseoagent_description` and `_thatseoagent_noindex` in the term meta:
 * the same keys as a post's, in the other table.
 *
 * The fields sit on the term's add and edit screens of every public
 * taxonomy, and the head, the sitemap and the abilities read them from here.
 *
 * @package ThatSeoAgent
 * @since 2.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Term_Seo {

    /**
     * Nonce action and field name of the term screens.
     */
    const NONCE = 'thatseoagent_term_seo';

    /**
     * The fields, their storage keys and their sanitizers.
     *
     * @since 2.9.0
     * @return array<string, array{key: string, sanitize: callable}>
     */
    public static function fields() {
        return array(
            'title'       => array(
                'key'      => ThatSeoAgent_Post_Seo::TITLE_KEY,
                'sanitize' => 'sanitize_text_field',
            ),
            'description' => array(
                'key'      => ThatSeoAgent_Post_Seo::DESCRIPTION_KEY,
                'sanitize' => 'sanitize_textarea_field',
            ),
            'noindex'     => array(
                'key'      => ThatSeoAgent_Post_Seo::NOINDEX_KEY,
                'sanitize' => array( 'ThatSeoAgent_Post_Seo', 'sanitize_flag' ),
            ),
        );
    }

    /**
     * Every meta key this plugin writes to terms, for uninstall.php.
     *
     * @since 2.9.0
     * @return array<int, string>
     */
    public static function keys() {
        return wp_list_pluck( self::fields(), 'key' );
    }

    /**
     * Taxonomies whose terms get the fields: the public ones with an
     * archive a visitor can open. Post formats are left out: nobody writes
     * a description for "Aside".
     *
     * @since 2.9.0
     * @return array<int, string>
     */
    public static function taxonomies() {
        $taxonomies = get_taxonomies(
            array(
                'public'             => true,
                'publicly_queryable' => true,
                'show_ui'            => true,
            ),
            'names'
        );

        unset( $taxonomies['post_format'] );

        /**
         * Filter the taxonomies whose terms get SEO fields.
         *
         * @since 2.9.0
         * @param array<int, string> $taxonomies Taxonomy names.
         */
        return (array) apply_filters( 'thatseoagent_term_seo_taxonomies', array_values( $taxonomies ) );
    }

    /**
     * Read one field.
     *
     * @since 2.9.0
     * @param WP_Term|int $term  Term or term ID.
     * @param string      $field 'title', 'description' or 'noindex'.
     * @return string Empty string when unset or unknown.
     */
    public static function get( $term, $field ) {
        $fields = self::fields();
        $term   = get_term( $term );

        if ( ! $term instanceof WP_Term || ! isset( $fields[ $field ] ) ) {
            return '';
        }

        return (string) get_term_meta( $term->term_id, $fields[ $field ]['key'], true );
    }

    /**
     * Read every field.
     *
     * @since 2.9.0
     * @param WP_Term|int $term Term or term ID.
     * @return array<string, string> Keyed by field name.
     */
    public static function all( $term ) {
        $values = array();
        foreach ( array_keys( self::fields() ) as $field ) {
            $values[ $field ] = self::get( $term, $field );
        }
        return $values;
    }

    /**
     * Write fields whose values come from code. Unslashed values; an empty
     * string clears a field; fields absent from $values are left alone.
     *
     * @since 2.9.0
     * @param WP_Term|int          $term   Term or term ID.
     * @param array<string,string> $values Keyed by field name.
     * @return bool False when the term does not exist.
     */
    public static function save( $term, array $values ) {
        $term = get_term( $term );
        if ( ! $term instanceof WP_Term ) {
            return false;
        }

        foreach ( self::fields() as $field => $config ) {
            if ( ! array_key_exists( $field, $values ) ) {
                continue;
            }

            $value = call_user_func( $config['sanitize'], (string) $values[ $field ] );

            if ( '' === $value ) {
                delete_term_meta( $term->term_id, $config['key'] );
                continue;
            }

            // update_term_meta() unslashes; slash so backslashes survive.
            update_term_meta( $term->term_id, $config['key'], wp_slash( $value ) );
        }

        ThatSeoAgent_Llms::purge();

        return true;
    }

    /**
     * Whether a term's archive is kept out of search results and the
     * sitemap.
     *
     * @since 2.9.0
     * @param WP_Term|int $term Term or term ID.
     * @return bool
     */
    public static function is_noindex( $term ) {
        return '1' === self::get( $term, 'noindex' );
    }

    /**
     * The search title of a term's archive: the one written for it, or the
     * one WordPress builds — the term's name and the site's.
     *
     * Like a post's, a written title is the full title, printed as it is:
     * the site name is not added to it.
     *
     * @since 2.9.0
     * @param WP_Term $term Term.
     * @return string Plain text.
     */
    public static function title( WP_Term $term ) {
        $written = self::get( $term, 'title' );

        if ( '' !== $written ) {
            return $written;
        }

        $separator = ThatSeoAgent_Title::separator( '|' );

        return html_entity_decode( $term->name . " {$separator} " . get_bloginfo( 'name' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    }

    /**
     * The meta description of a term's archive: the one written for it,
     * then the term's own description, then a sentence naming it.
     *
     * @since 2.9.0
     * @param WP_Term $term Term.
     * @return string Plain text.
     */
    public static function description( WP_Term $term ) {
        $written = self::get( $term, 'description' );

        if ( '' !== $written ) {
            return $written;
        }

        if ( '' !== trim( (string) $term->description ) ) {
            return trim( wp_strip_all_tags( $term->description ) );
        }

        /* translators: 1: term name, e.g. "Bristol", 2: site name. */
        return sprintf( __( 'Browse everything in %1$s on %2$s.', 'thatseoagent' ), $term->name, get_bloginfo( 'name' ) );
    }

    /**
     * Whether a description was written for the term, rather than taken
     * from its own description or built.
     *
     * @since 2.9.0
     * @param WP_Term $term Term.
     * @return bool
     */
    public static function has_written_description( WP_Term $term ) {
        return '' !== self::get( $term, 'description' );
    }

    // --- Registration and screens -----------------------------------------

    /**
     * Register the hooks.
     *
     * @since 2.9.0
     */
    public static function register() {
        // After the theme and plugins register their taxonomies.
        add_action( 'init', array( __CLASS__, 'register_for_taxonomies' ), 99 );
    }

    /**
     * The meta, and the fields of each taxonomy's term screens.
     *
     * @since 2.9.0
     */
    public static function register_for_taxonomies() {
        $auth_callback = function ( $allowed, $meta_key, $term_id ) {
            return current_user_can( 'edit_term', $term_id );
        };

        foreach ( self::taxonomies() as $taxonomy ) {
            foreach ( self::fields() as $config ) {
                register_term_meta( $taxonomy, $config['key'], array(
                    'type'              => 'string',
                    'single'            => true,
                    'default'           => '',
                    'show_in_rest'      => true,
                    'sanitize_callback' => $config['sanitize'],
                    'auth_callback'     => $auth_callback,
                ) );
            }

            add_action( "{$taxonomy}_add_form_fields", array( __CLASS__, 'render_add_fields' ) );
            add_action( "{$taxonomy}_edit_form_fields", array( __CLASS__, 'render_edit_fields' ) );
            add_action( "created_{$taxonomy}", array( __CLASS__, 'save_from_request' ) );
            add_action( "edited_{$taxonomy}", array( __CLASS__, 'save_from_request' ) );
        }
    }

    /**
     * The fields on the "Add New" form, stacked like core's.
     *
     * @since 2.9.0
     */
    public static function render_add_fields() {
        wp_nonce_field( self::NONCE, self::NONCE );

        foreach ( self::field_labels() as $field => $label ) {
            echo '<div class="form-field">';
            self::render_field( $field, $label['label'], '', $label['help'] );
            echo '</div>';
        }
    }

    /**
     * The fields on the term's edit screen, as table rows like core's.
     *
     * @since 2.9.0
     * @param WP_Term $term Term being edited.
     */
    public static function render_edit_fields( $term ) {
        wp_nonce_field( self::NONCE, self::NONCE );

        $values = self::all( $term );

        echo '<tr class="form-field"><th colspan="2"><h2>' . esc_html__( 'Search engines', 'thatseoagent' ) . '</h2></th></tr>';

        foreach ( self::field_labels() as $field => $label ) {
            echo '<tr class="form-field"><th scope="row">';

            if ( 'noindex' !== $field ) {
                echo '<label for="thatseoagent_term_' . esc_attr( $field ) . '">' . esc_html( $label['label'] ) . '</label>';
            } else {
                echo esc_html( $label['label'] );
            }

            echo '</th><td>';
            self::render_field( $field, 'noindex' === $field ? $label['check'] : '', $values[ $field ], $label['help'] );
            echo '</td></tr>';
        }
    }

    /**
     * Labels and help of each field.
     *
     * @since 2.9.0
     * @return array<string, array{label: string, help: string, check?: string}>
     */
    private static function field_labels() {
        return array(
            'title'       => array(
                'label' => __( 'SEO title', 'thatseoagent' ),
                'help'  => __( 'The full title in search results; the site name is not added. Empty: the name and the site name.', 'thatseoagent' ),
            ),
            'description' => array(
                'label' => __( 'Meta description', 'thatseoagent' ),
                'help'  => __( 'The text under the title in search results. Empty: the description above.', 'thatseoagent' ),
            ),
            'noindex'     => array(
                'label' => __( 'Search results', 'thatseoagent' ),
                'check' => __( 'Keep this page out of search results and the sitemap', 'thatseoagent' ),
                'help'  => '',
            ),
        );
    }

    /**
     * One field's input.
     *
     * @since 2.9.0
     * @param string $field Field name.
     * @param string $label Label: a <label> on the add form, the checkbox's text for noindex.
     * @param string $value Current value.
     * @param string $help  Help text.
     */
    private static function render_field( $field, $label, $value, $help ) {
        $id   = 'thatseoagent_term_' . $field;
        $name = 'thatseoagent_term[' . $field . ']';

        if ( 'noindex' === $field ) {
            printf(
                '<label><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s> %4$s</label>',
                esc_attr( $id ),
                esc_attr( $name ),
                checked( '1', $value, false ),
                esc_html( $label ? $label : self::field_labels()['noindex']['check'] )
            );
            return;
        }

        if ( $label ) {
            echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
        }

        if ( 'description' === $field ) {
            printf( '<textarea id="%1$s" name="%2$s" rows="3">%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( $value ) );
        } else {
            printf( '<input type="text" id="%1$s" name="%2$s" value="%3$s">', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
        }

        if ( $help ) {
            echo '<p class="description">' . esc_html( $help ) . '</p>';
        }
    }

    /**
     * Save the fields a term screen submitted.
     *
     * An unchecked checkbox sends nothing, so noindex is cleared when the
     * form was submitted without it.
     *
     * @since 2.9.0
     * @param int $term_id Term ID.
     */
    public static function save_from_request( $term_id ) {
        if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_term', $term_id ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each field goes through its sanitizer in save().
        $raw = isset( $_POST['thatseoagent_term'] ) && is_array( $_POST['thatseoagent_term'] ) ? wp_unslash( $_POST['thatseoagent_term'] ) : array();

        self::save( $term_id, array(
            'title'       => isset( $raw['title'] ) ? (string) $raw['title'] : '',
            'description' => isset( $raw['description'] ) ? (string) $raw['description'] : '',
            'noindex'     => isset( $raw['noindex'] ) ? (string) $raw['noindex'] : '',
        ) );
    }
}
