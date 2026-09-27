<?php
/**
 * The plugin's own MCP server.
 *
 * When the MCP Adapter plugin is active, the abilities of
 * ThatSeoAgent_Abilities are exposed as the tools of a dedicated server,
 * `thatseoagent`, whatever the theme:
 *
 *     HTTP:  /wp-json/mcp/thatseoagent
 *     STDIO: wp mcp-adapter serve --server=thatseoagent --user=<user>
 *
 * A dedicated server rather than the adapter's default one: each ability
 * reaches the client as a direct tool with its full schema, instead of
 * having to be discovered first through the default server's meta-tools.
 *
 * Without MCP Adapter nothing happens: the hook never fires, and the
 * abilities stay available to anything that calls the Abilities API.
 *
 * @package ThatSeoAgent
 * @since 2.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_MCP {

    /**
     * Server ID, also the last segment of its route.
     */
    const SERVER_ID = 'thatseoagent';

    /**
     * The abilities exposed as tools. As MCP tools the slash becomes a
     * hyphen: thatseoagent/get-post-seo → thatseoagent-get-post-seo.
     */
    const TOOLS = array(
        'thatseoagent/get-sitemap-urls',
        'thatseoagent/get-post-seo',
        'thatseoagent/audit-post-seo',
        'thatseoagent/scan-seo-issues',
        'thatseoagent/update-post-seo',
        'thatseoagent/generate-descriptions',
        'thatseoagent/list-term-seo',
        'thatseoagent/get-term-seo',
        'thatseoagent/update-term-seo',
        'thatseoagent/get-seo-settings',
        'thatseoagent/update-seo-settings',
        'thatseoagent/get-duplicates',
        'thatseoagent/get-link-report',
        'thatseoagent/get-site-bulletin',
    );

    /**
     * Register the hooks.
     *
     * @since 2.8.0
     */
    public static function register() {
        add_action( 'mcp_adapter_init', array( __CLASS__, 'create_server' ) );
    }

    /**
     * Create the server.
     *
     * @since 2.8.0
     * @param \WP\MCP\Core\McpAdapter $adapter The adapter.
     */
    public static function create_server( $adapter ) {
        // Class names of MCP Adapter 0.6.
        $transport     = 'WP\MCP\Transport\HttpTransport';
        $error_handler = 'WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler';
        $observability = 'WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler';

        if ( ! class_exists( $transport ) || ! is_object( $adapter ) || ! method_exists( $adapter, 'create_server' ) ) {
            return;
        }

        $result = $adapter->create_server(
            self::SERVER_ID,
            'mcp',
            self::SERVER_ID,
            'ThatSeoAgent',
            'SEO of every post, page, product and term archive; the plugin\'s settings; audits, duplicates, links and the site\'s bulletin; sitemap URLs.',
            THATSEOAGENT_VERSION,
            array( $transport ),
            class_exists( $error_handler ) ? $error_handler : null,
            class_exists( $observability ) ? $observability : null,
            self::TOOLS,
            array(),
            array(),
            array( __CLASS__, 'can_connect' )
        );

        if ( is_wp_error( $result ) ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- The adapter reports setup failures only this way.
            error_log( 'ThatSeoAgent: could not create the MCP server: ' . $result->get_error_message() );
        }
    }

    /**
     * Permission to connect over HTTP: editors and administrators. Each
     * ability also checks its own.
     *
     * edit_others_posts rather than the adapter's default of any logged-in
     * user: a subscriber's, author's or contributor's application password
     * could otherwise reach the tools. The STDIO transport does not run
     * this check; it already requires shell access to the server.
     *
     * @since 2.8.0
     * @return bool
     */
    public static function can_connect() {
        return current_user_can( 'edit_others_posts' );
    }
}
