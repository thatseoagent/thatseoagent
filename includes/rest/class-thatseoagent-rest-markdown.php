<?php
/**
 * The Markdown check and the .htaccess rule.
 *
 *     POST   /markdown/check     ask a post for Markdown and for HTML, now
 *     POST   /markdown/htaccess  write the rule at the top of .htaccess
 *     DELETE /markdown/htaccess  take it out
 *
 * @package ThatSeoAgent
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_REST_Markdown extends ThatSeoAgent_REST_Controller {

    /**
     * @var string
     */
    protected $rest_base = 'markdown';

    /**
     * @since 2.3.0
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/check',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'check' ),
                    'permission_callback' => array( $this, 'can_manage' ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/htaccess',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'install' ),
                    'permission_callback' => array( $this, 'can_manage' ),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array( $this, 'remove' ),
                    'permission_callback' => array( $this, 'can_manage' ),
                ),
            )
        );
    }

    /**
     * Run the check.
     *
     * @since 2.3.0
     * @return WP_REST_Response
     */
    public function check() {
        ThatSeoAgent_Markdown_Check::run();

        return $this->fresh( ThatSeoAgent_Markdown_Check::for_screen() );
    }

    /**
     * Write the rule.
     *
     * @since 2.3.0
     * @return WP_REST_Response|WP_Error
     */
    public function install() {
        if ( ! ThatSeoAgent_Markdown_Htaccess::applies() ) {
            return new WP_Error(
                'thatseoagent_htaccess_not_apache',
                __( 'This server does not read .htaccess rewrite rules, so the rule would do nothing.', 'thatseoagent' ),
                array( 'status' => 400 )
            );
        }

        $result = ThatSeoAgent_Markdown_Htaccess::install();

        return is_wp_error( $result ) ? $result : $this->fresh( ThatSeoAgent_Markdown_Htaccess::status() );
    }

    /**
     * Take the rule out.
     *
     * @since 2.3.0
     * @return WP_REST_Response|WP_Error
     */
    public function remove() {
        $result = ThatSeoAgent_Markdown_Htaccess::remove();

        return is_wp_error( $result ) ? $result : $this->fresh( ThatSeoAgent_Markdown_Htaccess::status() );
    }
}
