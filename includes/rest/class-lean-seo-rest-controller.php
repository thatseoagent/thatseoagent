<?php
/**
 * Shared by every Lean SEO REST controller: the namespace, the permission check and uncached responses.
 *
 * @package Lean_SEO
 * @since 1.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class Lean_SEO_REST_Controller extends WP_REST_Controller {

    /**
     * @var string
     */
    protected $namespace = 'lean-seo/v1';

    /**
     * Only people who can reach the Lean SEO screen.
     *
     * @since 1.18.0
     * @return true|WP_Error
     */
    public function can_manage() {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        return new WP_Error(
            'lean_seo_forbidden',
            __( 'Only administrators can do this.', 'lean-seo' ),
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
