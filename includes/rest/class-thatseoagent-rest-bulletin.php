<?php
/**
 * GET /thatseoagent/v1/bulletin: the site bulletin, for the sidebar.
 *
 * @package ThatSeoAgent
 * @since 1.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_REST_Bulletin extends ThatSeoAgent_REST_Controller {

    /**
     * @var string
     */
    protected $rest_base = 'bulletin';

    /**
     * @since 1.18.0
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_item' ),
                    'permission_callback' => array( $this, 'can_manage' ),
                ),
            )
        );
    }

    /**
     * @since 1.18.0
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function get_item( $request ) {
        return $this->fresh( ThatSeoAgent_Bulletin::for_js() );
    }
}
