<?php
/**
 * POST /lean-seo/v1/llms: rebuild llms.txt.
 *
 * @package Lean_SEO
 * @since 1.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Lean_SEO_REST_Llms extends Lean_SEO_REST_Controller {

    /**
     * @var string
     */
    protected $rest_base = 'llms';

    /**
     * @since 1.18.0
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'create_item' ),
                    'permission_callback' => array( $this, 'can_manage' ),
                ),
            )
        );
    }

    /**
     * Rebuild the file now instead of on its next request.
     *
     * @since 1.18.0
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function create_item( $request ) {
        Lean_SEO_Llms::purge();

        $body = Lean_SEO_Llms::build();
        set_transient( Lean_SEO_Llms::CACHE_KEY, $body, DAY_IN_SECONDS );

        return $this->fresh( Lean_SEO_Llms::describe( $body ) );
    }
}
