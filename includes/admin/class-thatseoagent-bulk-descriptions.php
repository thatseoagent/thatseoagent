<?php
/**
 * "Generate meta description" on the posts list.
 *
 * A bulk action on every post type with the SEO fields: saves, for each
 * selected post without a description of its own, the description the front
 * end was already generating.
 *
 * @package ThatSeoAgent
 * @since 1.16.0
 * @since 1.19.0 Moved out of ThatSeoAgent_Admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Bulk_Descriptions {

    /**
     * The bulk action's key.
     */
    const ACTION = 'thatseoagent_generate_descriptions';

    /**
     * Register the hooks.
     *
     * @since 1.19.0
     */
    public static function register() {
        add_action( 'admin_init', array( __CLASS__, 'register_actions' ) );
        add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
    }

    /**
     * Add the bulk action to every post type with the meta box.
     *
     * Runs on admin_init, after `init` has registered the post types.
     *
     * @since 1.16.0
     */
    public static function register_actions() {
        foreach ( ThatSeoAgent_Post_Seo::post_types() as $post_type ) {
            add_filter( 'bulk_actions-edit-' . $post_type, array( __CLASS__, 'offer' ) );
            add_filter( 'handle_bulk_actions-edit-' . $post_type, array( __CLASS__, 'handle' ), 10, 3 );
        }
    }

    /**
     * Offer the bulk action.
     *
     * @since 1.16.0
     * @param array $actions Bulk actions.
     * @return array
     */
    public static function offer( $actions ) {
        $actions[ self::ACTION ] = __( 'Generate meta description', 'thatseoagent' );
        return $actions;
    }

    /**
     * Save the generated description of each selected post that has none.
     *
     * The description saved is exactly the one the front end was already
     * emitting — ThatSeoAgent_Description::generate() — so the page does not
     * change; the value just stops depending on the content staying as it is.
     * Posts with a description of their own are left alone. edit.php has
     * already checked the bulk-posts nonce by the time this runs.
     *
     * @since 1.16.0
     * @param string $redirect Redirect URL.
     * @param string $action   Chosen action.
     * @param array  $post_ids Selected post IDs.
     * @return string
     */
    public static function handle( $redirect, $action, $post_ids ) {
        if ( self::ACTION !== $action ) {
            return $redirect;
        }

        $generated = 0;
        $skipped   = 0;

        foreach ( (array) $post_ids as $post_id ) {
            $post_id = (int) $post_id;

            if ( ! current_user_can( 'edit_post', $post_id ) || '' !== ThatSeoAgent_Post_Seo::get( $post_id, 'description' ) ) {
                $skipped++;
                continue;
            }

            $description = ThatSeoAgent_Description::generate( $post_id );
            ThatSeoAgent_Memo::forget_post( $post_id );

            if ( '' === $description ) {
                $skipped++;
                continue;
            }

            ThatSeoAgent_Post_Seo::save( $post_id, array( 'description' => $description ) );
            $generated++;
        }

        return add_query_arg(
            array(
                'thatseoagent_generated' => $generated,
                'thatseoagent_skipped'   => $skipped,
            ),
            $redirect
        );
    }

    /**
     * Report the result of the bulk action.
     *
     * @since 1.16.0
     */
    public static function notice() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display only: counts from our own redirect.
        if ( ! isset( $_GET['thatseoagent_generated'] ) ) {
            return;
        }

        $generated = absint( $_GET['thatseoagent_generated'] );
        $skipped   = isset( $_GET['thatseoagent_skipped'] ) ? absint( $_GET['thatseoagent_skipped'] ) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $message = sprintf(
            /* translators: %d: number of posts. */
            _n( 'Meta description saved for %d post.', 'Meta description saved for %d posts.', $generated, 'thatseoagent' ),
            $generated
        );

        if ( $skipped ) {
            $message .= ' ' . sprintf(
                /* translators: %d: number of posts. */
                _n( '%d skipped: it already had one or has no content to describe.', '%d skipped: they already had one or have no content to describe.', $skipped, 'thatseoagent' ),
                $skipped
            );
        }

        printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $message ) );
    }
}
