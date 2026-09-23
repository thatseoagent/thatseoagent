<?php
/**
 * POST /thatseoagent/v1/crawlers/probe: request the homepage as each AI
 * crawler and report who got through.
 *
 * @package ThatSeoAgent
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_REST_Crawlers extends ThatSeoAgent_REST_Controller {

    /**
     * @var string
     */
    protected $rest_base = 'crawlers';

    /**
     * @since 2.1.0
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/probe',
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
     * Run the check now.
     *
     * @since 2.1.0
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function create_item( $request ) {
        ThatSeoAgent_Crawler_Access::check();

        return $this->fresh( ThatSeoAgent_Crawler_Access::for_screen() );
    }
}
