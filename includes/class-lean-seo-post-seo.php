<?php
/**
 * Per-post SEO fields: storage, sanitization and slashing.
 *
 * The owner of `_lean_seo_title` and `_lean_seo_description`. Before 1.9.0
 * the two key strings appeared in six files — including a hand-written SQL
 * join in the WP-CLI command and a hard-coded list in uninstall.php — and
 * each writer decided its own sanitizer and its own slashing.
 *
 * Slashing is the invariant this module exists to hold. WordPress hands you
 * `$_POST` already slashed, and `update_post_meta()` unslashes what it is
 * given; so a value read from a form must stay slashed, while a value from
 * an ability or WP-CLI must be slashed first. Getting it backwards mangles
 * backslashes. Rather than document that, there are two entry points and
 * each caller picks the one matching where its data came from:
 *
 *     save()              — values from code (an ability, WP-CLI, a filter)
 *     save_from_request() — values from $_POST / $_REQUEST
 *
 * @package Lean_SEO
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lean_SEO_Post_Seo {

    /**
     * Meta key holding the custom SEO title.
     */
    const TITLE_KEY = '_lean_seo_title';

    /**
     * Meta key holding the custom meta description.
     */
    const DESCRIPTION_KEY = '_lean_seo_description';

    /**
     * The fields, their storage keys and their sanitizers.
     *
     * @since 1.9.0
     * @return array<string, array{key: string, sanitize: string}>
     */
    public static function fields() {
        return array(
            'title'       => array(
                'key'      => self::TITLE_KEY,
                'sanitize' => 'sanitize_text_field',
            ),
            'description' => array(
                'key'      => self::DESCRIPTION_KEY,
                'sanitize' => 'sanitize_textarea_field',
            ),
        );
    }

    /**
     * Every meta key this plugin writes to posts.
     *
     * uninstall.php reads this so deleting the plugin cannot miss a key.
     *
     * @since 1.9.0
     * @return array<int, string>
     */
    public static function keys() {
        return wp_list_pluck( self::fields(), 'key' );
    }

    /**
     * Read one field.
     *
     * @since 1.9.0
     * @param WP_Post|int $post  Post or ID.
     * @param string      $field 'title' or 'description'.
     * @return string Empty string when unset or unknown.
     */
    public static function get( $post, $field ) {
        $fields = self::fields();
        $post   = get_post( $post );

        if ( ! $post || ! isset( $fields[ $field ] ) ) {
            return '';
        }

        return (string) get_post_meta( $post->ID, $fields[ $field ]['key'], true );
    }

    /**
     * Read every field.
     *
     * @since 1.9.0
     * @param WP_Post|int $post Post or ID.
     * @return array<string, string> Keyed by field name.
     */
    public static function all( $post ) {
        $values = array();

        foreach ( array_keys( self::fields() ) as $field ) {
            $values[ $field ] = self::get( $post, $field );
        }

        return $values;
    }

    /**
     * Write fields whose values come from code.
     *
     * Values must be unslashed — the normal PHP case. An empty string clears
     * the field. Fields absent from $values are left alone.
     *
     * @since 1.9.0
     * @param WP_Post|int          $post   Post or ID.
     * @param array<string,string> $values Keyed by field name.
     * @return bool False when the post does not exist.
     */
    public static function save( $post, array $values ) {
        $post = get_post( $post );
        if ( ! $post ) {
            return false;
        }

        foreach ( self::fields() as $field => $config ) {
            if ( ! array_key_exists( $field, $values ) ) {
                continue;
            }

            $value = call_user_func( $config['sanitize'], (string) $values[ $field ] );

            if ( '' === $value ) {
                delete_post_meta( $post->ID, $config['key'] );
                continue;
            }

            // update_post_meta() unslashes; slash so backslashes survive.
            update_post_meta( $post->ID, $config['key'], wp_slash( $value ) );
        }

        return true;
    }

    /**
     * Write fields whose values come from a request.
     *
     * Expects the raw, still-slashed values WordPress puts in $_POST. Pass
     * the superglobal straight in: unslashing happens here, once.
     *
     * @since 1.9.0
     * @param WP_Post|int $post Post or ID.
     * @param array       $raw  Raw request data, keyed by field name.
     * @return bool False when the post does not exist.
     */
    public static function save_from_request( $post, array $raw ) {
        $values = array();

        foreach ( array_keys( self::fields() ) as $field ) {
            if ( array_key_exists( $field, $raw ) ) {
                $values[ $field ] = wp_unslash( $raw[ $field ] );
            }
        }

        return self::save( $post, $values );
    }

    /**
     * Register the fields on `init`, once the post types exist.
     *
     * @since 1.19.0 Moved out of Lean_SEO.
     */
    public static function register() {
        add_action( 'init', array( __CLASS__, 'register_for_post_types' ) );
    }

    /**
     * Register the fields for every post type that has them.
     *
     * @since 1.19.0
     */
    public static function register_for_post_types() {
        self::register_meta( self::post_types() );
    }

    /**
     * Post types that get the SEO fields: the meta box in the editor, the
     * registered meta, the bulk action, the content check and WP-CLI.
     *
     * Defaults to every post type with an editing screen. The rest of the
     * plugin already treats custom post types as first-class — they appear in
     * the sitemap, they get WebPage and BreadcrumbList schema, and a stored
     * `_lean_seo_description` is honoured on the front end for any post type
     * — so limiting the editing UI to posts and pages (the default before
     * 1.12.0) left no way to enter the values the plugin was already reading.
     *
     * Attachments are excluded: the media modal has no meta box.
     *
     * @since 1.12.0 As Lean_SEO_Admin::get_meta_box_post_types().
     * @since 1.19.0 Moved here: it is a fact about the fields, not the admin.
     * @return array<int, string>
     */
    public static function post_types() {
        $post_types = get_post_types(
            array(
                'public'  => true,
                'show_ui' => true,
            ),
            'names'
        );

        unset( $post_types['attachment'] );

        /**
         * Filter the post types that get the SEO meta box.
         *
         * @since 1.0.0
         * @param array<int, string> $post_types Post type names.
         */
        return (array) apply_filters( 'lean_seo_meta_box_post_types', array_values( $post_types ) );
    }

    /**
     * Expose the fields to the REST API and the block editor.
     *
     * @since 1.9.0
     * @param array<int, string> $post_types Post types to register for.
     */
    public static function register_meta( array $post_types ) {
        $auth_callback = function ( $allowed, $meta_key, $post_id ) {
            return current_user_can( 'edit_post', $post_id );
        };

        foreach ( $post_types as $post_type ) {
            foreach ( self::fields() as $config ) {
                register_post_meta( $post_type, $config['key'], array(
                    'type'              => 'string',
                    'single'            => true,
                    'default'           => '',
                    'show_in_rest'      => true,
                    'sanitize_callback' => $config['sanitize'],
                    'auth_callback'     => $auth_callback,
                ) );
            }
        }
    }

    /**
     * Count published posts of a type with no value for a field.
     *
     * The SQL lives here because the meta key does. WP-CLI used to carry its
     * own copy of this join.
     *
     * @since 1.9.0
     * @param string $post_type Post type.
     * @param string $field     Field name. Default 'description'.
     * @return int
     */
    public static function count_missing( $post_type, $field = 'description' ) {
        global $wpdb;

        $fields = self::fields();
        if ( ! isset( $fields[ $field ] ) ) {
            return 0;
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        // Counting rows that lack a meta key has no WP_Query equivalent that
        // does not load the posts. Deliberately uncached: the only caller is
        // the WP-CLI command, which runs once and needs a live count to size
        // its progress bar — a cached number would be wrong the moment the
        // command starts writing.
        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm
                   ON p.ID = pm.post_id AND pm.meta_key = %s
                 WHERE p.post_type = %s
                   AND p.post_status = 'publish'
                   AND (pm.meta_value IS NULL OR pm.meta_value = '')",
                $fields[ $field ]['key'],
                $post_type
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        return $count;
    }

    /**
     * A meta_query matching posts with no value for a field.
     *
     * @since 1.9.0
     * @param string $field Field name. Default 'description'.
     * @return array
     */
    public static function missing_meta_query( $field = 'description' ) {
        $fields = self::fields();
        if ( ! isset( $fields[ $field ] ) ) {
            return array();
        }

        $key = $fields[ $field ]['key'];

        return array(
            'relation' => 'OR',
            array(
                'key'     => $key,
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key'     => $key,
                'value'   => '',
                'compare' => '=',
            ),
        );
    }
}
