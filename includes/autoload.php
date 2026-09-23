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
 *     admin/         the Lean SEO screen, the meta box, the bulk action
 *     rest/          the screen's REST controllers (lean-seo/v1)
 *     tooling/       WP-CLI, the Abilities API, the importer
 *     integrations/  other SEO plugins, IndexNow
 *
 * The map is explicit rather than derived from the class name: the folder
 * is a fact about the concept, which the name does not carry, and a class
 * missing from the map fails loudly instead of being searched for on disk.
 * Adding a class means adding its line here.
 *
 * Uses __DIR__, not LEAN_SEO_PLUGIN_DIR: uninstall.php loads this file
 * without the plugin's main file.
 *
 * @package Lean_SEO
 * @since 1.19.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

spl_autoload_register(
    function ( $class ) {
        static $map = array(
        'Lean_SEO_Settings'          => 'class-lean-seo-settings.php',
        'Lean_SEO'                   => 'class-lean-seo.php',

        'Lean_SEO_Content'           => 'content/class-lean-seo-content.php',
        'Lean_SEO_Description'       => 'content/class-lean-seo-description.php',
        'Lean_SEO_FAQ_Section'       => 'content/class-lean-seo-faq-section.php',
        'Lean_SEO_FAQ'               => 'content/class-lean-seo-faq.php',
        'Lean_SEO_Post_Seo'          => 'content/class-lean-seo-post-seo.php',

        'Lean_SEO_Meta'              => 'head/class-lean-seo-meta.php',
        'Lean_SEO_Schema'            => 'head/class-lean-seo-schema.php',
        'Lean_SEO_Title'             => 'head/class-lean-seo-title.php',

        'Lean_SEO_Default_Author'    => 'site/class-lean-seo-default-author.php',
        'Lean_SEO_Homepage_Applier'  => 'site/class-lean-seo-homepage-applier.php',
        'Lean_SEO_Homepage'          => 'site/class-lean-seo-homepage.php',
        'Lean_SEO_Identity_Applier'  => 'site/class-lean-seo-identity-applier.php',
        'Lean_SEO_Identity'          => 'site/class-lean-seo-identity.php',

        'Lean_SEO_Product_Report'    => 'catalog/class-lean-seo-product-report.php',
        'Lean_SEO_Product_Settings'  => 'catalog/class-lean-seo-product-settings.php',
        'Lean_SEO_Product'           => 'catalog/class-lean-seo-product.php',

        'Lean_SEO_Robots'            => 'sitemap/class-lean-seo-robots.php',
        'Lean_SEO_Sitemap'           => 'sitemap/class-lean-seo-sitemap.php',

        'Lean_SEO_Llms'              => 'markdown/class-lean-seo-llms.php',
        'Lean_SEO_Markdown_Cache'    => 'markdown/class-lean-seo-markdown-cache.php',
        'Lean_SEO_Markdown_Endpoint' => 'markdown/class-lean-seo-markdown-endpoint.php',
        'Lean_SEO_Markdown'          => 'markdown/class-lean-seo-markdown.php',

        'Lean_SEO_Audit_Run'         => 'audit/class-lean-seo-audit-run.php',
        'Lean_SEO_Audit'             => 'audit/class-lean-seo-audit.php',

        'Lean_SEO_App'               => 'admin/class-lean-seo-app.php',
        'Lean_SEO_Bulk_Descriptions' => 'admin/class-lean-seo-bulk-descriptions.php',
        'Lean_SEO_Meta_Box'          => 'admin/class-lean-seo-meta-box.php',

        'Lean_SEO_REST_Audit'        => 'rest/class-lean-seo-rest-audit.php',
        'Lean_SEO_REST_Bulletin'     => 'rest/class-lean-seo-rest-bulletin.php',
        'Lean_SEO_REST_Controller'   => 'rest/class-lean-seo-rest-controller.php',
        'Lean_SEO_REST_Llms'         => 'rest/class-lean-seo-rest-llms.php',
        'Lean_SEO_REST_Preferences'  => 'rest/class-lean-seo-rest-preferences.php',
        'Lean_SEO_REST'              => 'rest/class-lean-seo-rest.php',

        'Lean_SEO_Abilities'         => 'tooling/class-lean-seo-abilities.php',
        'Lean_SEO_CLI'               => 'tooling/class-lean-seo-cli.php',
        'Lean_SEO_Importer'          => 'tooling/class-lean-seo-importer.php',

        'Lean_SEO_Compat'            => 'integrations/class-lean-seo-compat.php',
        'Lean_SEO_IndexNow'          => 'integrations/class-lean-seo-indexnow.php',
        );

        if ( isset( $map[ $class ] ) ) {
            require __DIR__ . '/' . $map[ $class ];
        }
    }
);
