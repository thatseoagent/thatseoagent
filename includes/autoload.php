<?php
/**
 * Class autoloader.
 *
 * One class per file, grouped by concept:
 *
 *     content/       what a post says: content, description, FAQ, SEO fields
 *     head/          what <head> prints: meta tags, title, JSON-LD
 *     site/          who the site is: identity, homepage, default author
 *     catalog/       product catalogs and how complete they are
 *     sitemap/       XML sitemaps and robots.txt
 *     markdown/      the Markdown version of each post, and llms.txt
 *     audit/         the SEO audit and the batched content check
 *     bulletin/      the site's state: the bulletin and the readings
 *     admin/         the ThatSeoAgent screen, the meta box, the bulk action
 *     rest/          the screen's REST controllers (thatseoagent/v1)
 *     tooling/       WP-CLI, the Abilities API, the importer
 *     integrations/  other SEO plugins, IndexNow
 *
 * The map is explicit rather than derived from the class name: the folder
 * is a fact about the concept, which the name does not carry, and a class
 * missing from the map fails loudly instead of being searched for on disk.
 * Adding a class means adding its line here.
 *
 * Uses __DIR__, not THATSEOAGENT_PLUGIN_DIR: uninstall.php loads this file
 * without the plugin's main file.
 *
 * @package ThatSeoAgent
 * @since 1.19.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

spl_autoload_register(
    function ( $class ) {
        static $map = array(
        'ThatSeoAgent_Settings'          => 'class-thatseoagent-settings.php',
        'ThatSeoAgent_Memo'              => 'class-thatseoagent-memo.php',
        'ThatSeoAgent'                   => 'class-thatseoagent.php',

        'ThatSeoAgent_Content'           => 'content/class-thatseoagent-content.php',
        'ThatSeoAgent_Description'       => 'content/class-thatseoagent-description.php',
        'ThatSeoAgent_FAQ_Section'       => 'content/class-thatseoagent-faq-section.php',
        'ThatSeoAgent_FAQ'               => 'content/class-thatseoagent-faq.php',
        'ThatSeoAgent_Post_Seo'          => 'content/class-thatseoagent-post-seo.php',

        'ThatSeoAgent_Meta'              => 'head/class-thatseoagent-meta.php',
        'ThatSeoAgent_Schema'            => 'head/class-thatseoagent-schema.php',
        'ThatSeoAgent_Title'             => 'head/class-thatseoagent-title.php',

        'ThatSeoAgent_Default_Author'    => 'site/class-thatseoagent-default-author.php',
        'ThatSeoAgent_Homepage_Applier'  => 'site/class-thatseoagent-homepage-applier.php',
        'ThatSeoAgent_Homepage'          => 'site/class-thatseoagent-homepage.php',
        'ThatSeoAgent_Identity_Applier'  => 'site/class-thatseoagent-identity-applier.php',
        'ThatSeoAgent_Identity'          => 'site/class-thatseoagent-identity.php',

        'ThatSeoAgent_Product_Report'    => 'catalog/class-thatseoagent-product-report.php',
        'ThatSeoAgent_Product_Settings'  => 'catalog/class-thatseoagent-product-settings.php',
        'ThatSeoAgent_Product'           => 'catalog/class-thatseoagent-product.php',

        'ThatSeoAgent_Robots'            => 'sitemap/class-thatseoagent-robots.php',
        'ThatSeoAgent_Sitemap'           => 'sitemap/class-thatseoagent-sitemap.php',

        'ThatSeoAgent_Llms'              => 'markdown/class-thatseoagent-llms.php',
        'ThatSeoAgent_Markdown_Cache'    => 'markdown/class-thatseoagent-markdown-cache.php',
        'ThatSeoAgent_Markdown_Endpoint' => 'markdown/class-thatseoagent-markdown-endpoint.php',
        'ThatSeoAgent_Markdown'          => 'markdown/class-thatseoagent-markdown.php',

        'ThatSeoAgent_Audit_Run'         => 'audit/class-thatseoagent-audit-run.php',
        'ThatSeoAgent_Audit'             => 'audit/class-thatseoagent-audit.php',

        'ThatSeoAgent_Bulletin'          => 'bulletin/class-thatseoagent-bulletin.php',
        'ThatSeoAgent_Readings'          => 'bulletin/class-thatseoagent-readings.php',

        'ThatSeoAgent_App'               => 'admin/class-thatseoagent-app.php',
        'ThatSeoAgent_Bulk_Descriptions' => 'admin/class-thatseoagent-bulk-descriptions.php',
        'ThatSeoAgent_Icons'             => 'admin/class-thatseoagent-icons.php',
        'ThatSeoAgent_Meta_Box'          => 'admin/class-thatseoagent-meta-box.php',

        'ThatSeoAgent_REST_Audit'        => 'rest/class-thatseoagent-rest-audit.php',
        'ThatSeoAgent_REST_Bulletin'     => 'rest/class-thatseoagent-rest-bulletin.php',
        'ThatSeoAgent_REST_Controller'   => 'rest/class-thatseoagent-rest-controller.php',
        'ThatSeoAgent_REST_Llms'         => 'rest/class-thatseoagent-rest-llms.php',
        'ThatSeoAgent_REST_Preferences'  => 'rest/class-thatseoagent-rest-preferences.php',
        'ThatSeoAgent_REST'              => 'rest/class-thatseoagent-rest.php',

        'ThatSeoAgent_Abilities'         => 'tooling/class-thatseoagent-abilities.php',
        'ThatSeoAgent_CLI'               => 'tooling/class-thatseoagent-cli.php',
        'ThatSeoAgent_Importer'          => 'tooling/class-thatseoagent-importer.php',

        'ThatSeoAgent_Compat'            => 'integrations/class-thatseoagent-compat.php',
        'ThatSeoAgent_IndexNow'          => 'integrations/class-thatseoagent-indexnow.php',
        );

        if ( isset( $map[ $class ] ) ) {
            require __DIR__ . '/' . $map[ $class ];
        }
    }
);
