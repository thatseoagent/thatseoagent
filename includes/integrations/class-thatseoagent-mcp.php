<?php
/**
 * The plugin's own MCP server.
 *
 * When the Lean MCP plugin is active, the abilities of
 * ThatSeoAgent_Abilities are exposed as the tools of a dedicated server,
 * `thatseoagent`, whatever the theme:
 *
 *     /wp-json/lean-mcp/thatseoagent
 *
 * A dedicated server rather than Lean MCP's default one: it is reached
 * only by editors and administrators, and its tools are exactly these.
 *
 * Without Lean MCP nothing happens: the hook never fires, and the
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
     * What the server offers, for the model.
     */
    const DESCRIPTION = 'SEO of every post, page, product and term archive; the plugin\'s settings; audits, duplicates, links and the site\'s bulletin; sitemap URLs.';

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
        add_action( 'lean_mcp_init', array( __CLASS__, 'register_lean_mcp_server' ) );
    }

    /**
     * Declare the server to Lean MCP.
     *
     * @since 2.10.1
     */
    public static function register_lean_mcp_server() {
        if ( ! function_exists( 'lean_mcp_register_server' ) ) {
            return;
        }

        lean_mcp_register_server(
            self::SERVER_ID,
            array(
                'title'        => 'ThatSeoAgent',
                'version'      => THATSEOAGENT_VERSION,
                'instructions' => self::DESCRIPTION,
                // Editors and administrators: a subscriber's, author's or
                // contributor's application password can't reach the tools.
                // Each ability also checks its own.
                'capability'   => 'edit_others_posts',
                'abilities'    => self::TOOLS,
            )
        );
    }
}
