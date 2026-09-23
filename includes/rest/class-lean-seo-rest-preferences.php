<?php
/**
 * POST /lean-seo/v1/preferences: the current user's screen preferences.
 *
 * @package Lean_SEO
 * @since 1.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lean_SEO_REST_Preferences extends Lean_SEO_REST_Controller {

    /**
     * @var string
     */
    protected $rest_base = 'preferences';

    /**
     * @since 1.18.0
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'update_item' ),
                    'permission_callback' => array( $this, 'can_manage' ),
                    'args'                => array(
                        'theme' => array(
                            'type'     => 'string',
                            'enum'     => array( 'light', 'dark' ),
                            'required' => true,
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * @since 1.18.0
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function update_item( $request ) {
        $theme = $request['theme'];

        // The default needs no row in usermeta.
        if ( 'light' === $theme ) {
            delete_user_meta( get_current_user_id(), Lean_SEO_App::THEME_META );
        } else {
            update_user_meta( get_current_user_id(), Lean_SEO_App::THEME_META, $theme );
        }

        return $this->fresh( array( 'theme' => Lean_SEO_App::theme() ) );
    }
}
