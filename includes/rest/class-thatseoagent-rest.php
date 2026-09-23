<?php
/**
 * REST API of the ThatSeoAgent screen, namespace `thatseoagent/v1`.
 *
 * One controller per resource:
 *
 *     GET    /bulletin               the site bulletin (level, observations)
 *     POST   /preferences            the current user's screen preferences
 *     GET    /audit?post_type=       the last finished content check, as { last }
 *     POST   /audit/runs             start a content check
 *     POST   /audit/runs/{token}     check the next batch
 *     DELETE /audit/runs/{token}     stop the check
 *     POST   /llms                   regenerate llms.txt
 *     POST   /crawlers/probe         request the homepage as each AI crawler
 *     POST   /markdown/check         ask a post for Markdown and for HTML
 *     POST   /markdown/htaccess      write the Markdown rule to .htaccess
 *     DELETE /markdown/htaccess      take it out
 *
 * Settings are not here: they are registered options, saved through core's
 * /wp/v2/settings with the same sanitizers as the settings form.
 *
 * Successful responses are the data itself; failures are WP_Errors with a
 * code, a message written for the person reading it, and an HTTP status.
 *
 * @package ThatSeoAgent
 * @since 1.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers the controllers.
 *
 * @since 1.18.0
 */
class ThatSeoAgent_REST {

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
        foreach ( array( 'ThatSeoAgent_REST_Bulletin', 'ThatSeoAgent_REST_Preferences', 'ThatSeoAgent_REST_Audit', 'ThatSeoAgent_REST_Llms', 'ThatSeoAgent_REST_Crawlers', 'ThatSeoAgent_REST_Markdown' ) as $class ) {
            ( new $class() )->register_routes();
        }
    }
}
