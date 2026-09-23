<?php
/**
 * POST /thatseoagent/v1/llms: rebuild llms.txt.
 *
 * @package ThatSeoAgent
 * @since 1.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_REST_Llms extends ThatSeoAgent_REST_Controller {

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
        ThatSeoAgent_Llms::purge();

        $body = ThatSeoAgent_Llms::build();
        set_transient( ThatSeoAgent_Llms::CACHE_KEY, $body, DAY_IN_SECONDS );

        return $this->fresh( ThatSeoAgent_Llms::describe( $body ) );
    }
}
