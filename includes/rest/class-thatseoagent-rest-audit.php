<?php
/**
 * /thatseoagent/v1/audit and /audit/runs: the batched content check.
 *
 * @package ThatSeoAgent
 * @since 1.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_REST_Audit extends ThatSeoAgent_REST_Controller {

    /**
     * @var string
     */
    protected $rest_base = 'audit';

    /**
     * @since 1.18.0
     */
    public function register_routes() {
        $post_type = array(
            'type'              => 'string',
            'required'          => true,
            'validate_callback' => array( $this, 'validate_post_type' ),
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_item' ),
                    'permission_callback' => array( $this, 'can_manage' ),
                    'args'                => array( 'post_type' => $post_type ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/runs',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'create_item' ),
                    'permission_callback' => array( $this, 'can_manage' ),
                    'args'                => array( 'post_type' => $post_type ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/runs/(?P<token>[A-Za-z0-9]{16})',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'update_item' ),
                    'permission_callback' => array( $this, 'can_manage' ),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'delete_item' ),
                    'permission_callback' => array( $this, 'can_manage' ),
                ),
            )
        );
    }

    /**
     * Only post types the screen offers.
     *
     * @since 1.18.0
     * @param string $value Post type.
     * @return true|WP_Error
     */
    public function validate_post_type( $value ) {
        if ( in_array( $value, ThatSeoAgent_Post_Seo::post_types(), true ) ) {
            return true;
        }

        return new WP_Error( 'thatseoagent_invalid_post_type', __( 'That content type cannot be checked.', 'thatseoagent' ), array( 'status' => 400 ) );
    }

    /**
     * GET /audit: the last finished check.
     *
     * @since 1.18.0
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function get_item( $request ) {
        return $this->fresh( ThatSeoAgent_Audit_Run::last( $request['post_type'] ) );
    }

    /**
     * POST /audit/runs: start.
     *
     * @since 1.18.0
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function create_item( $request ) {
        return $this->fresh( ThatSeoAgent_Audit_Run::start( get_current_user_id(), $request['post_type'] ) );
    }

    /**
     * POST /audit/runs/{token}: the next batch.
     *
     * @since 1.18.0
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response|WP_Error
     */
    public function update_item( $request ) {
        $progress = ThatSeoAgent_Audit_Run::batch( get_current_user_id(), $request['token'] );

        return is_wp_error( $progress ) ? $progress : $this->fresh( $progress );
    }

    /**
     * DELETE /audit/runs/{token}: stop.
     *
     * @since 1.18.0
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function delete_item( $request ) {
        ThatSeoAgent_Audit_Run::cancel( get_current_user_id() );

        return $this->fresh( array( 'status' => 'cancelled' ) );
    }
}
