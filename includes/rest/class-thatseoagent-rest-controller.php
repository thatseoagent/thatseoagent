<?php
/**
 * Shared by every ThatSeoAgent REST controller: the namespace, the permission check and uncached responses.
 *
 * @package ThatSeoAgent
 * @since 1.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class ThatSeoAgent_REST_Controller extends WP_REST_Controller {

    /**
     * @var string
     */
    protected $namespace = 'thatseoagent/v1';

    /**
     * Only people who can reach the ThatSeoAgent screen.
     *
     * @since 1.18.0
     * @return true|WP_Error
     */
    public function can_manage() {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        return new WP_Error(
            'thatseoagent_forbidden',
            __( 'Only administrators can do this.', 'thatseoagent' ),
            array( 'status' => rest_authorization_required_code() )
        );
    }

    /**
     * Keep page caches (LiteSpeed, Varnish, hosting caches) away from these
     * answers: each one is about this moment and this user.
     *
     * @since 1.18.0
     * @param mixed $data Response data.
     * @return WP_REST_Response
     */
    protected function fresh( $data ) {
        $response = rest_ensure_response( $data );
        foreach ( wp_get_nocache_headers() as $name => $value ) {
            if ( $value ) {
                $response->header( $name, $value );
            }
        }

        return $response;
    }
}
