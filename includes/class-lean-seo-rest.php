<?php
/**
 * REST API of the Lean SEO screen, namespace `lean-seo/v1`.
 *
 * One controller per resource:
 *
 *     GET    /bulletin               the site bulletin (level, observations)
 *     POST   /preferences            the current user's screen preferences
 *     GET    /audit?post_type=       the last finished content check
 *     POST   /audit/runs             start a content check
 *     POST   /audit/runs/{token}     check the next batch
 *     DELETE /audit/runs/{token}     stop the check
 *     POST   /llms                   regenerate llms.txt
 *
 * Settings are not here: they are registered options, saved through core's
 * /wp/v2/settings with the same sanitizers as the settings form.
 *
 * Successful responses are the data itself; failures are WP_Errors with a
 * code, a message written for the person reading it, and an HTTP status.
 *
 * @package Lean_SEO
 * @since 1.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shared by every controller.
 *
 * @since 1.18.0
 */
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

/**
 * GET /bulletin.
 *
 * @since 1.18.0
 */
class Lean_SEO_REST_Bulletin extends Lean_SEO_REST_Controller {

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
        return $this->fresh( Lean_SEO_App::bulletin_for_js() );
    }
}

/**
 * POST /preferences.
 *
 * @since 1.18.0
 */
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

/**
 * /audit and /audit/runs.
 *
 * @since 1.18.0
 */
class Lean_SEO_REST_Audit extends Lean_SEO_REST_Controller {

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
        if ( in_array( $value, Lean_SEO_Admin::get_meta_box_post_types(), true ) ) {
            return true;
        }

        return new WP_Error( 'lean_seo_invalid_post_type', __( 'That content type cannot be checked.', 'lean-seo' ), array( 'status' => 400 ) );
    }

    /**
     * GET /audit: the last finished check.
     *
     * @since 1.18.0
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function get_item( $request ) {
        return $this->fresh( Lean_SEO_Audit_Run::last( $request['post_type'] ) );
    }

    /**
     * POST /audit/runs: start.
     *
     * @since 1.18.0
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function create_item( $request ) {
        return $this->fresh( Lean_SEO_Audit_Run::start( get_current_user_id(), $request['post_type'] ) );
    }

    /**
     * POST /audit/runs/{token}: the next batch.
     *
     * @since 1.18.0
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response|WP_Error
     */
    public function update_item( $request ) {
        $progress = Lean_SEO_Audit_Run::batch( get_current_user_id(), $request['token'] );

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
        Lean_SEO_Audit_Run::cancel( get_current_user_id() );

        return $this->fresh( array( 'status' => 'cancelled' ) );
    }
}

/**
 * POST /llms.
 *
 * @since 1.18.0
 */
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

/**
 * Registers the controllers.
 *
 * @since 1.18.0
 */
class Lean_SEO_REST {

    /**
     * @since 1.18.0
     */
    public static function register() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    /**
     * @since 1.18.0
     */
    public static function register_routes() {
        foreach ( array( 'Lean_SEO_REST_Bulletin', 'Lean_SEO_REST_Preferences', 'Lean_SEO_REST_Audit', 'Lean_SEO_REST_Llms' ) as $class ) {
            ( new $class() )->register_routes();
        }
    }
}
