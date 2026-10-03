<?php
/**
 * Abilities API registration: the SEO of posts and terms.
 *
 * The site-wide abilities — options, duplicates, links, the bulletin —
 * are in ThatSeoAgent_Site_Abilities.
 *
 * @package ThatSeoAgent
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ThatSeoAgent_Abilities {

    /**
     * Most posts scan-seo-issues returns in one call.
     */
    const SCAN_MAX = 200;

    /**
     * Most posts generate-descriptions handles in one call.
     */
    const GENERATE_MAX = 100;

    /**
     * Register abilities.
     */
    public static function register() {
        self::register_post_abilities();
        self::register_term_abilities();
        ThatSeoAgent_Site_Abilities::register();
    }

    /**
     * The abilities of a post's SEO.
     *
     * @since 2.9.0 Split out of register().
     */
    private static function register_post_abilities() {
        $post_id = array(
            'type'        => 'integer',
            'minimum'     => 1,
            'description' => __( 'ID of a post, page or other content with SEO fields: every public type with an editing screen, attachments aside.', 'thatseoagent' ),
        );

        wp_register_ability(
            'thatseoagent/get-sitemap-urls',
            array(
                'label'               => __( 'Get Sitemap URLs', 'thatseoagent' ),
                'description'         => __( 'Returns every sitemap URL the site publishes: posts, pages, categories, tags, each custom post type and each custom taxonomy (brands, product categories).', 'thatseoagent' ),
                'category'            => 'site',
                'execute_callback'    => array( __CLASS__, 'get_sitemap_urls' ),
                'permission_callback' => array( __CLASS__, 'can_manage' ),
                'meta'                => self::annotations( true ),
                'input_schema'        => array(
                    'type'                 => 'object',
                    'properties'           => array(),
                    'additionalProperties' => false,
                    // Hardening for the indirect-invocation path, which is
                    // stricter than direct invocation about a zero-arg call.
                    // An array, as core declares its own: an object default
                    // reaches the callbacks as a stdClass they can't index.
                    'default'              => array(),
                ),
                'output_schema'       => array(
                    'type'  => 'array',
                    'items' => array(
                        'type' => 'string',
                    ),
                ),
            )
        );

        wp_register_ability(
            'thatseoagent/get-post-seo',
            array(
                'label'               => __( 'Get Post SEO', 'thatseoagent' ),
                'description'         => __( 'Returns the SEO of a post, page or product as its page publishes it: the search title and meta description (and the ones written for it, if any), the canonical URL, noindex, the image it is shared with, its primary term per taxonomy and its JSON-LD graph.', 'thatseoagent' ),
                'category'            => 'site',
                'execute_callback'    => array( __CLASS__, 'get_post_seo' ),
                'permission_callback' => array( __CLASS__, 'can_edit_post' ),
                'meta'                => self::annotations( true ),
                'input_schema'        => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => array(
                        'post_id' => $post_id,
                    ),
                    'required'             => array( 'post_id' ),
                ),
                'output_schema'       => self::post_seo_schema(),
            )
        );

        wp_register_ability(
            'thatseoagent/audit-post-seo',
            array(
                'label'               => __( 'Audit Post SEO', 'thatseoagent' ),
                'description'         => __( 'Analyzes a post for SEO issues and returns recommendations. Only what Google or accessibility guidelines ask for lowers the score; findings of our own judgement are marked as such. Also returns the post\'s structure as facts — outline, lists, tables, figures, opening — for you to judge: no AI provider publishes how it chooses what to cite, so the plugin scores none of it. Read markdown_url for the full content.', 'thatseoagent' ),
                'category'            => 'site',
                'execute_callback'    => array( __CLASS__, 'audit_post_seo' ),
                'permission_callback' => array( __CLASS__, 'can_edit_post' ),
                'meta'                => self::annotations( true ),
                'input_schema'        => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => array(
                        'post_id' => $post_id,
                    ),
                    'required'             => array( 'post_id' ),
                ),
                'output_schema'       => self::audit_schema(),
            )
        );

        wp_register_ability(
            'thatseoagent/scan-seo-issues',
            array(
                'label'               => __( 'Scan SEO Issues', 'thatseoagent' ),
                'description'         => __( 'Audits many posts, newest first, and lists the ones with issues, worst first, each with its issues in full (type, severity, source, message). Filter by content type, status and issue type. A scan reads at most max(500, limit × 20) posts: when next_offset is not null, call again with offset = next_offset to go on.', 'thatseoagent' ),
                'category'            => 'site',
                'execute_callback'    => array( __CLASS__, 'scan_seo_issues' ),
                'permission_callback' => array( __CLASS__, 'can_manage' ),
                'meta'                => self::annotations( true ),
                'input_schema'        => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => array(
                        'limit'      => array(
                            'type'        => 'integer',
                            'minimum'     => 1,
                            'maximum'     => self::SCAN_MAX,
                            'description' => __( 'Maximum posts to return (default: 50).', 'thatseoagent' ),
                            'default'     => 50,
                        ),
                        'min_issues' => array(
                            'type'        => 'integer',
                            'minimum'     => 0,
                            'description' => __( 'Minimum issues to include in results (default: 1).', 'thatseoagent' ),
                            'default'     => 1,
                        ),
                        'post_type'  => array(
                            'type'        => 'string',
                            'description' => __( 'Post type to scan, or "any" for every type with SEO fields (default: post).', 'thatseoagent' ),
                            'default'     => 'post',
                        ),
                        'status'     => array(
                            'type'        => 'string',
                            'enum'        => array( 'publish', 'any' ),
                            'description' => __( 'publish (default) or any: drafts, pending and scheduled posts too.', 'thatseoagent' ),
                            'default'     => 'publish',
                        ),
                        'issue'      => array(
                            'type'        => 'string',
                            'description' => __( 'Only posts with this issue type, such as missing_description, title_may_be_cut, orphan or featured_image_missing_alt.', 'thatseoagent' ),
                        ),
                        'offset'     => array(
                            'type'        => 'integer',
                            'minimum'     => 0,
                            'description' => __( 'Posts to skip, newest first: the next_offset of the previous call.', 'thatseoagent' ),
                            'default'     => 0,
                        ),
                    ),
                ),
                'output_schema'       => array(
                    'type'       => 'object',
                    'properties' => array(
                        'results'     => array(
                            'type'  => 'array',
                            'items' => array(
                                'type'       => 'object',
                                'properties' => array(
                                    'post_id'     => array( 'type' => 'integer' ),
                                    'title'       => array( 'type' => 'string' ),
                                    'url'         => array( 'type' => 'string' ),
                                    'score'       => array( 'type' => 'integer' ),
                                    'issue_count' => array( 'type' => 'integer' ),
                                    'top_issues'  => array( 'type' => 'array' ),
                                    'issues'      => array( 'type' => 'array' ),
                                ),
                            ),
                        ),
                        'scanned'     => array( 'type' => 'integer' ),
                        'next_offset' => array( 'type' => array( 'integer', 'null' ) ),
                    ),
                ),
            )
        );

        wp_register_ability(
            'thatseoagent/update-post-seo',
            array(
                'label'               => __( 'Update Post SEO', 'thatseoagent' ),
                'description'         => __( 'Updates the SEO of a post, page or product; only the fields you send. The title is the full title in search results, printed as written: the site name is not added, so include it if you want it ("Terms of use | Acme"). Returns what the page now publishes, and `warnings` when a title or description may be cut in results (over 70 or 165 characters) or another page has the same one. Warnings never block the save.', 'thatseoagent' ),
                'category'            => 'site',
                'execute_callback'    => array( __CLASS__, 'update_post_seo' ),
                'permission_callback' => array( __CLASS__, 'can_edit_post' ),
                'meta'                => array(
                    'annotations' => array(
                        // Writes post meta, so not readonly. Not destructive:
                        // fields are updated, and an empty string clears one
                        // field rather than destroying a record. Idempotent:
                        // repeat calls with the same input write the same
                        // values with no accumulating effect.
                        'readonly'    => false,
                        'destructive' => false,
                        'idempotent'  => true,
                    ),
                ),
                'input_schema'        => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => array(
                        'post_id'      => $post_id,
                        'title'        => array(
                            'type'        => 'string',
                            'description' => __( 'Custom SEO title, the full title as it should read in results. Empty string clears the value.', 'thatseoagent' ),
                        ),
                        'description'  => array(
                            'type'        => 'string',
                            'description' => __( 'Custom meta description. Empty string clears the value.', 'thatseoagent' ),
                        ),
                        'noindex'      => array(
                            'type'        => 'boolean',
                            'description' => __( 'True keeps the post out of search results, the sitemap and llms.txt; false lets search engines index it again.', 'thatseoagent' ),
                        ),
                        'share_image'  => array(
                            'type'        => array( 'integer', 'null' ),
                            'minimum'     => 1,
                            'description' => __( 'Attachment ID of the image the post is shared with (Open Graph, X), or null to go back to its own image and then the site\'s.', 'thatseoagent' ),
                        ),
                        'primary_term' => array(
                            'type'                 => 'object',
                            'description'          => __( 'Primary term per taxonomy — the one breadcrumbs and article:section show — as taxonomy => term ID, or null to let the plugin choose. The term must be assigned to the post.', 'thatseoagent' ),
                            'additionalProperties' => array( 'type' => array( 'integer', 'null' ) ),
                        ),
                    ),
                    'required'             => array( 'post_id' ),
                ),
                'output_schema'       => self::post_seo_schema( true ),
            )
        );

        wp_register_ability(
            'thatseoagent/generate-descriptions',
            array(
                'label'               => __( 'Generate Descriptions', 'thatseoagent' ),
                'description'         => __( 'Saves, as written descriptions, the meta description each post already publishes when none was written: the one generated from its content. The page does not change; the description just stops depending on the content staying as it is. Posts with a description of their own are left alone. Up to 100 posts per call.', 'thatseoagent' ),
                'category'            => 'site',
                'execute_callback'    => array( __CLASS__, 'generate_descriptions' ),
                'permission_callback' => array( __CLASS__, 'can_edit_posts' ),
                'meta'                => self::annotations( false, true ),
                'input_schema'        => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => array(
                        'post_ids' => array(
                            'type'     => 'array',
                            'minItems' => 1,
                            'maxItems' => self::GENERATE_MAX,
                            'items'    => array(
                                'type'    => 'integer',
                                'minimum' => 1,
                            ),
                        ),
                    ),
                    'required'             => array( 'post_ids' ),
                ),
                'output_schema'       => array(
                    'type'       => 'object',
                    'properties' => array(
                        'generated' => array( 'type' => 'array' ),
                        'skipped'   => array( 'type' => 'array' ),
                    ),
                ),
            )
        );
    }

    /**
     * The abilities of a term's SEO: its archive page.
     *
     * @since 2.9.0
     */
    private static function register_term_abilities() {
        $taxonomy = array(
            'type'        => 'string',
            'description' => __( 'Taxonomy, such as category, post_tag, or a custom one like a brand or a product category. Only public taxonomies with archives have SEO fields.', 'thatseoagent' ),
        );
        $term_id  = array(
            'type'    => 'integer',
            'minimum' => 1,
        );

        wp_register_ability(
            'thatseoagent/list-term-seo',
            array(
                'label'               => __( 'List Term SEO', 'thatseoagent' ),
                'description'         => __( 'Lists the terms of the taxonomies with SEO fields — categories, tags, brands, product categories — with the title and description their archive publishes, whether a title or description was written, noindex, and how many posts each has. Filter by taxonomy and by what is missing.', 'thatseoagent' ),
                'category'            => 'site',
                'execute_callback'    => array( __CLASS__, 'list_term_seo' ),
                'permission_callback' => array( __CLASS__, 'can_manage_terms' ),
                'meta'                => self::annotations( true ),
                'input_schema'        => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'default'              => array(),
                    'properties'           => array(
                        'taxonomy' => $taxonomy,
                        'missing'  => array(
                            'type'        => 'string',
                            'enum'        => array( 'title', 'description' ),
                            'description' => __( 'Only terms without a written title, or without a written description.', 'thatseoagent' ),
                        ),
                    ),
                ),
                'output_schema'       => array(
                    'type'       => 'object',
                    'properties' => array(
                        'taxonomies' => array( 'type' => 'array' ),
                        'terms'      => array( 'type' => 'array' ),
                    ),
                ),
            )
        );

        wp_register_ability(
            'thatseoagent/get-term-seo',
            array(
                'label'               => __( 'Get Term SEO', 'thatseoagent' ),
                'description'         => __( 'Returns the SEO of a term\'s archive page — a category, tag, brand or product category: its URL, the search title and meta description it publishes, the ones written for it, noindex and whether it is in the sitemap.', 'thatseoagent' ),
                'category'            => 'site',
                'execute_callback'    => array( __CLASS__, 'get_term_seo' ),
                'permission_callback' => array( __CLASS__, 'can_edit_term' ),
                'meta'                => self::annotations( true ),
                'input_schema'        => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => array(
                        'taxonomy' => $taxonomy,
                        'term_id'  => $term_id,
                    ),
                    'required'             => array( 'taxonomy', 'term_id' ),
                ),
                'output_schema'       => self::term_seo_schema(),
            )
        );

        wp_register_ability(
            'thatseoagent/update-term-seo',
            array(
                'label'               => __( 'Update Term SEO', 'thatseoagent' ),
                'description'         => __( 'Updates the SEO of a term\'s archive page; only the fields you send. As with posts, the title is the full title, printed as written: the site name is not added. noindex keeps the archive out of search results and the sitemap. Returns what the archive now publishes, with warnings when a title or description may be cut (over 70 or 165 characters).', 'thatseoagent' ),
                'category'            => 'site',
                'execute_callback'    => array( __CLASS__, 'update_term_seo' ),
                'permission_callback' => array( __CLASS__, 'can_edit_term' ),
                'meta'                => self::annotations( false, true ),
                'input_schema'        => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'properties'           => array(
                        'taxonomy'    => $taxonomy,
                        'term_id'     => $term_id,
                        'title'       => array(
                            'type'        => 'string',
                            'description' => __( 'Custom SEO title, the full title. Empty string clears it: the term name and the site name.', 'thatseoagent' ),
                        ),
                        'description' => array(
                            'type'        => 'string',
                            'description' => __( 'Custom meta description. Empty string clears it: the term\'s own description, or a sentence naming it.', 'thatseoagent' ),
                        ),
                        'noindex'     => array( 'type' => 'boolean' ),
                    ),
                    'required'             => array( 'taxonomy', 'term_id' ),
                ),
                'output_schema'       => self::term_seo_schema( true ),
            )
        );
    }

    // --- Schemas -------------------------------------------------------------

    /**
     * MCP annotations.
     *
     * @since 2.9.0
     * @param bool $readonly   Whether the ability only reads.
     * @param bool $idempotent Whether repeating it changes nothing more.
     * @return array
     */
    public static function annotations( $readonly, $idempotent = true ) {
        return array(
            'annotations' => array(
                'readonly'    => $readonly,
                'destructive' => false,
                'idempotent'  => $idempotent,
            ),
        );
    }

    /**
     * What get-post-seo and update-post-seo return.
     *
     * @since 2.9.0
     * @param bool $with_warnings Whether it carries update warnings.
     * @return array
     */
    private static function post_seo_schema( $with_warnings = false ) {
        $schema = array(
            'type'       => 'object',
            'properties' => array(
                'post_id'       => array( 'type' => 'integer' ),
                'title'         => array( 'type' => 'string' ),
                'description'   => array( 'type' => 'string' ),
                'canonical'     => array( 'type' => 'string' ),
                'noindex'       => array(
                    'type'        => 'boolean',
                    'description' => __( 'Whether the post is kept out of search results, the sitemap and llms.txt.', 'thatseoagent' ),
                ),
                'custom'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'title'       => array( 'type' => 'string' ),
                        'description' => array( 'type' => 'string' ),
                        'share_image' => array( 'type' => array( 'integer', 'null' ) ),
                    ),
                ),
                'share_image'   => array(
                    'type'        => array( 'object', 'null' ),
                    'description' => __( 'The image the page is shared with, and where it comes from: chosen for it, its own (featured, gallery, content), the site\'s default image or the logo.', 'thatseoagent' ),
                ),
                'primary_terms' => array(
                    'type'        => 'object',
                    'description' => __( 'Per taxonomy the primary term, the one breadcrumbs show, and whether it was chosen by hand.', 'thatseoagent' ),
                ),
                'schema'        => array(
                    'type'        => 'array',
                    'description' => __( 'The JSON-LD @graph the post\'s page publishes, node by node, as its head prints it. Empty while another SEO plugin is active and ThatSeoAgent prints nothing.', 'thatseoagent' ),
                    'items'       => array( 'type' => 'object' ),
                ),
            ),
        );

        if ( $with_warnings ) {
            $schema['properties']['warnings'] = self::warnings_schema();
        }

        return $schema;
    }

    /**
     * What get-term-seo and update-term-seo return.
     *
     * @since 2.9.0
     * @param bool $with_warnings Whether it carries update warnings.
     * @return array
     */
    private static function term_seo_schema( $with_warnings = false ) {
        $schema = array(
            'type'       => 'object',
            'properties' => array(
                'term_id'             => array( 'type' => 'integer' ),
                'taxonomy'            => array( 'type' => 'string' ),
                'name'                => array( 'type' => 'string' ),
                'url'                 => array( 'type' => 'string' ),
                'count'               => array( 'type' => 'integer' ),
                'title'               => array( 'type' => 'string' ),
                'description'         => array( 'type' => 'string' ),
                'description_written' => array( 'type' => 'boolean' ),
                'noindex'             => array( 'type' => 'boolean' ),
                'in_sitemap'          => array( 'type' => 'boolean' ),
                'custom'              => array(
                    'type'       => 'object',
                    'properties' => array(
                        'title'       => array( 'type' => 'string' ),
                        'description' => array( 'type' => 'string' ),
                    ),
                ),
            ),
        );

        if ( $with_warnings ) {
            $schema['properties']['warnings'] = self::warnings_schema();
        }

        return $schema;
    }

    /**
     * The warnings an update returns.
     *
     * @since 2.9.0
     * @return array
     */
    private static function warnings_schema() {
        return array(
            'type'        => 'array',
            'description' => __( 'What may go wrong in search results. They never block the save.', 'thatseoagent' ),
            'items'       => array(
                'type'       => 'object',
                'properties' => array(
                    'field'   => array( 'type' => 'string' ),
                    'type'    => array( 'type' => 'string' ),
                    'message' => array( 'type' => 'string' ),
                ),
            ),
        );
    }

    /**
     * What audit-post-seo returns.
     *
     * @since 2.9.0 Moved out of register().
     * @return array
     */
    private static function audit_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'post_id'      => array( 'type' => 'integer' ),
                'title'        => array( 'type' => 'string' ),
                'url'          => array( 'type' => 'string' ),
                'seo'          => array(
                    'type'        => 'object',
                    'description' => __( 'The title and description the page shows in search results, and whether the description was written or generated from the content.', 'thatseoagent' ),
                    'properties'  => array(
                        'title'               => array( 'type' => 'string' ),
                        'description'         => array( 'type' => 'string' ),
                        'description_written' => array( 'type' => 'boolean' ),
                    ),
                ),
                'score'        => array( 'type' => 'integer' ),
                'issues'       => array(
                    'type'  => 'array',
                    'items' => array(
                        'type'       => 'object',
                        'properties' => array(
                            'type'     => array( 'type' => 'string' ),
                            'severity' => array( 'type' => 'string' ),
                            'source'   => array(
                                'type'        => 'string',
                                'enum'        => array( 'google', 'accessibility', 'heuristic' ),
                                'description' => __( 'Who asks for it: Google Search Central, accessibility guidelines (WCAG 2.2), or our own judgement, which never lowers the score.', 'thatseoagent' ),
                            ),
                            'message'  => array( 'type' => 'string' ),
                            'value'    => array( 'type' => 'string' ),
                        ),
                    ),
                ),
                'not_measured' => array(
                    'type'        => 'array',
                    'description' => __( 'Checks that could not run on this post, and why. A check that could not run is not a check that passed or failed.', 'thatseoagent' ),
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'type'    => array( 'type' => 'string' ),
                            'message' => array( 'type' => 'string' ),
                        ),
                    ),
                ),
                'stats'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'title_length'       => array( 'type' => 'integer' ),
                        'description_length' => array( 'type' => 'integer' ),
                        'word_count'         => array( 'type' => 'integer' ),
                        'h1_count'           => array( 'type' => 'integer' ),
                        'h2_count'           => array( 'type' => 'integer' ),
                        'internal_links'     => array( 'type' => 'integer' ),
                        'external_links'     => array( 'type' => 'integer' ),
                        'uncrawlable_links'  => array( 'type' => 'integer' ),
                        'images'             => array( 'type' => 'integer' ),
                        'images_without_alt' => array( 'type' => 'integer' ),
                        'images_decorative'  => array( 'type' => 'integer' ),
                    ),
                ),
                'structure'    => array(
                    'type'        => 'object',
                    'description' => __( 'How the post is built, as facts with no verdict attached.', 'thatseoagent' ),
                    'properties'  => array(
                        'declared_language' => array(
                            'type'        => 'string',
                            'description' => __( 'The language the site declares in <html lang>, from Settings → General. Not detected from the text, which may be written in another.', 'thatseoagent' ),
                        ),
                        'published'         => array( 'type' => 'string' ),
                        'modified'          => array( 'type' => 'string' ),
                        'markdown_url'      => array(
                            'type'        => 'string',
                            'description' => __( 'The post as Markdown, to read its full content. Empty when it has no Markdown version.', 'thatseoagent' ),
                        ),
                        'words'             => array( 'type' => 'integer' ),
                        'paragraphs'        => array( 'type' => 'integer' ),
                        'headings'          => array(
                            'type'        => 'array',
                            'description' => __( 'The content\'s headings in order, up to 60. The page title the theme prints is not among them.', 'thatseoagent' ),
                            'items'       => array(
                                'type'       => 'object',
                                'properties' => array(
                                    'level' => array( 'type' => 'integer' ),
                                    'text'  => array( 'type' => 'string' ),
                                ),
                            ),
                        ),
                        'lists'             => array(
                            'type'       => 'object',
                            'properties' => array(
                                'unordered' => array( 'type' => 'integer' ),
                                'ordered'   => array( 'type' => 'integer' ),
                                'items'     => array( 'type' => 'integer' ),
                            ),
                        ),
                        'tables'            => array(
                            'type'       => 'object',
                            'properties' => array(
                                'count' => array( 'type' => 'integer' ),
                                'rows'  => array( 'type' => 'integer' ),
                            ),
                        ),
                        'blockquotes'       => array( 'type' => 'integer' ),
                        'details'           => array(
                            'type'        => 'integer',
                            'description' => __( 'Details blocks: collapsible question-and-answer sections.', 'thatseoagent' ),
                        ),
                        'code_blocks'       => array( 'type' => 'integer' ),
                        'numbers'           => array(
                            'type'        => 'integer',
                            'description' => __( 'Figures in the text: every run of digits, years and prices included.', 'thatseoagent' ),
                        ),
                        'percentages'       => array( 'type' => 'integer' ),
                        'opening'           => array(
                            'type'        => 'string',
                            'description' => __( 'The first 150 words of the text, as a reader meets them.', 'thatseoagent' ),
                        ),
                    ),
                ),
            ),
        );
    }

    // --- Permissions ---------------------------------------------------------

    /**
     * Permission check for the site-wide abilities.
     *
     * @return bool
     */
    public static function can_manage() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Permission check for sitemap URLs.
     *
     * @deprecated 2.9.0 Use can_manage().
     * @return bool
     */
    public static function can_view_sitemaps() {
        return self::can_manage();
    }

    /**
     * Permission check for post SEO abilities.
     *
     * For a post that does not exist or has no SEO fields there is nothing
     * of its own to check: anyone who can edit posts gets through, and the
     * ability answers why (not found, an attachment, in the trash). A
     * WP_Error here would not reach them: core logs it and answers a bare
     * "does not have necessary permission", which read as a permissions
     * problem for a mistyped ID.
     *
     * @param array $input Ability input.
     * @return bool
     */
    public static function can_edit_post( $input ) {
        $post = self::seo_post( isset( $input['post_id'] ) ? $input['post_id'] : 0 );

        if ( is_wp_error( $post ) ) {
            return current_user_can( 'edit_posts' );
        }

        return current_user_can( 'edit_post', $post->ID );
    }

    /**
     * Permission check for generate-descriptions: each post is checked
     * again as it is handled.
     *
     * @since 2.9.0
     * @return bool
     */
    public static function can_edit_posts() {
        return current_user_can( 'edit_posts' );
    }

    /**
     * Permission check for list-term-seo.
     *
     * @since 2.9.0
     * @return bool
     */
    public static function can_manage_terms() {
        return current_user_can( 'manage_categories' );
    }

    /**
     * Permission check for a term's SEO, with the same reasoning as
     * can_edit_post() for a term that is not one.
     *
     * @since 2.9.0
     * @param array $input Ability input.
     * @return bool
     */
    public static function can_edit_term( $input ) {
        $term = self::seo_term( isset( $input['taxonomy'] ) ? $input['taxonomy'] : '', isset( $input['term_id'] ) ? $input['term_id'] : 0 );

        if ( is_wp_error( $term ) ) {
            return current_user_can( 'manage_categories' );
        }

        return current_user_can( 'edit_term', $term->term_id );
    }

    /**
     * A post with SEO fields, or why it is not one.
     *
     * Revisions, attachments, menu items and trashed posts have no page of
     * their own in search results: their "SEO" was a canonical like
     * ?p=1710 and a title nobody sees.
     *
     * @since 2.9.0
     * @param mixed $post_id Post ID.
     * @return WP_Post|WP_Error
     */
    public static function seo_post( $post_id ) {
        $post_id = absint( $post_id );
        $post    = $post_id ? get_post( $post_id ) : null;

        if ( ! $post ) {
            return new WP_Error( 'thatseoagent_invalid_post_id', __( 'No post exists with that ID.', 'thatseoagent' ) );
        }

        $types = ThatSeoAgent_Post_Seo::post_types();

        if ( ! in_array( $post->post_type, $types, true ) ) {
            return new WP_Error(
                'thatseoagent_no_seo_fields',
                /* translators: 1: post ID, 2: its post type, 3: post types with SEO fields. */
                sprintf( __( 'Post %1$d is of type %2$s, which has no SEO fields. Types with them: %3$s.', 'thatseoagent' ), $post->ID, $post->post_type, implode( ', ', $types ) )
            );
        }

        if ( 'trash' === $post->post_status ) {
            /* translators: %d: post ID. */
            return new WP_Error( 'thatseoagent_trashed', sprintf( __( 'Post %d is in the trash.', 'thatseoagent' ), $post->ID ) );
        }

        return $post;
    }

    /**
     * A term with SEO fields, or why it is not one.
     *
     * @since 2.9.0
     * @param mixed $taxonomy Taxonomy.
     * @param mixed $term_id  Term ID.
     * @return WP_Term|WP_Error
     */
    public static function seo_term( $taxonomy, $term_id ) {
        $taxonomies = ThatSeoAgent_Term_Seo::taxonomies();
        $taxonomy   = (string) $taxonomy;

        if ( ! in_array( $taxonomy, $taxonomies, true ) ) {
            return new WP_Error(
                'thatseoagent_no_seo_fields',
                /* translators: 1: taxonomy, 2: taxonomies with SEO fields. */
                sprintf( __( 'Taxonomy "%1$s" has no SEO fields. Taxonomies with them: %2$s.', 'thatseoagent' ), $taxonomy, implode( ', ', $taxonomies ) )
            );
        }

        $term = get_term( absint( $term_id ), $taxonomy );

        if ( ! $term instanceof WP_Term ) {
            /* translators: 1: term ID, 2: taxonomy. */
            return new WP_Error( 'thatseoagent_invalid_term_id', sprintf( __( 'No term %1$d exists in %2$s.', 'thatseoagent' ), absint( $term_id ), $taxonomy ) );
        }

        return $term;
    }

    // --- Posts -----------------------------------------------------------------

    /**
     * Return sitemap URLs.
     *
     * Asks the sitemap module rather than re-deriving its URL scheme. The
     * previous copy had drifted: it omitted custom post type sitemaps and
     * page pagination, so this ability contradicted the site's own
     * /sitemap.xml.
     *
     * @return array
     */
    public static function get_sitemap_urls() {
        $urls = array( home_url( '/sitemap.xml' ) );

        foreach ( ThatSeoAgent_Sitemap::get_sitemap_urls() as $entry ) {
            $urls[] = $entry['loc'];
        }

        return $urls;
    }

    /**
     * Return SEO data for a post.
     *
     * @param array $input Ability input.
     * @return array|WP_Error
     */
    public static function get_post_seo( $input ) {
        $post = self::seo_post( isset( $input['post_id'] ) ? $input['post_id'] : 0 );
        if ( is_wp_error( $post ) ) {
            return $post;
        }

        ThatSeoAgent_Memo::forget_post( $post );

        $custom = ThatSeoAgent_Post_Seo::all( $post );
        $image  = ThatSeoAgent_Image::for_post( $post, 'share' );

        $primary = array();
        foreach ( ThatSeoAgent_Primary_Term::taxonomies( $post->post_type ) as $taxonomy ) {
            $term    = ThatSeoAgent_Primary_Term::get( $post, $taxonomy );
            $chosen  = (int) get_post_meta( $post->ID, ThatSeoAgent_Primary_Term::key( $taxonomy ), true );
            $primary[ $taxonomy ] = $term ? array(
                'term_id' => (int) $term->term_id,
                'name'    => html_entity_decode( $term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
                'chosen'  => $chosen === (int) $term->term_id,
            ) : null;
        }

        return array(
            'post_id'       => $post->ID,
            'title'         => ThatSeoAgent_Title::for_post( $post ),
            'description'   => ThatSeoAgent_Description::for_post( $post ),
            'canonical'     => get_permalink( $post ),
            'noindex'       => ThatSeoAgent_Indexing::is_post_noindex( $post ),
            'custom'        => array(
                'title'       => $custom['title'],
                'description' => $custom['description'],
                'share_image' => '' !== $custom['share_image'] ? (int) $custom['share_image'] : null,
            ),
            'share_image'   => $image ? array(
                'id'     => (int) $image['id'],
                'url'    => $image['url'],
                'source' => $image['source'],
            ) : null,
            'primary_terms' => (object) $primary,
            // What the page's head prints, without requesting the page.
            'schema'        => ThatSeoAgent_Compat::outputs_enabled() ? ThatSeoAgent_Schema::for_post( $post ) : array(),
        );
    }

    /**
     * Update SEO data for a post.
     *
     * Everything is checked before anything is written: an image that is
     * not one, or a primary term the post does not have, saves nothing.
     *
     * @param array $input Ability input.
     * @return array|WP_Error
     */
    public static function update_post_seo( $input ) {
        $post = self::seo_post( isset( $input['post_id'] ) ? $input['post_id'] : 0 );
        if ( is_wp_error( $post ) ) {
            return $post;
        }

        $fields = array( 'title', 'description', 'noindex', 'share_image', 'primary_term' );
        if ( ! array_intersect( $fields, array_keys( $input ) ) ) {
            return new WP_Error(
                'thatseoagent_nothing_to_change',
                /* translators: %s: field names. */
                sprintf( __( 'Send at least one of: %s.', 'thatseoagent' ), implode( ', ', $fields ) )
            );
        }

        $values = array();
        foreach ( array( 'title', 'description' ) as $field ) {
            if ( array_key_exists( $field, $input ) ) {
                $values[ $field ] = (string) $input[ $field ];
            }
        }

        if ( array_key_exists( 'noindex', $input ) ) {
            $values['noindex'] = $input['noindex'] ? '1' : '';
        }

        if ( array_key_exists( 'share_image', $input ) ) {
            $image = absint( $input['share_image'] );

            if ( $image && ! wp_attachment_is_image( $image ) ) {
                /* translators: %d: attachment ID. */
                return new WP_Error( 'thatseoagent_invalid_image', sprintf( __( 'Attachment %d is not an image.', 'thatseoagent' ), $image ) );
            }

            $values['share_image'] = $image ? (string) $image : '';
        }

        $primary = isset( $input['primary_term'] ) ? (array) $input['primary_term'] : array();
        $allowed = ThatSeoAgent_Primary_Term::taxonomies( $post->post_type );

        foreach ( $primary as $taxonomy => $term_id ) {
            if ( ! in_array( $taxonomy, $allowed, true ) ) {
                return new WP_Error(
                    'thatseoagent_invalid_taxonomy',
                    /* translators: 1: taxonomy, 2: taxonomies with a primary term. */
                    sprintf( __( 'Taxonomy "%1$s" has no primary term for this post. It has: %2$s.', 'thatseoagent' ), $taxonomy, $allowed ? implode( ', ', $allowed ) : __( 'none', 'thatseoagent' ) )
                );
            }

            if ( $term_id && ! has_term( (int) $term_id, $taxonomy, $post ) ) {
                return new WP_Error(
                    'thatseoagent_term_not_assigned',
                    /* translators: 1: term ID, 2: taxonomy. */
                    sprintf( __( 'Term %1$d of %2$s is not assigned to the post: assign it first.', 'thatseoagent' ), (int) $term_id, $taxonomy )
                );
            }
        }

        ThatSeoAgent_Post_Seo::save( $post, $values );

        foreach ( $primary as $taxonomy => $term_id ) {
            ThatSeoAgent_Primary_Term::save( $post, $taxonomy, (int) $term_id );
        }

        // The groups are memoised per request, and may predate this save.
        ThatSeoAgent_Memo::forget( 'duplicates' );

        $result             = self::get_post_seo( array( 'post_id' => $post->ID ) );
        $result['warnings'] = self::warnings(
            $result['title'],
            $result['description'],
            ThatSeoAgent_Duplicates::title_twins( $post ),
            ThatSeoAgent_Duplicates::description_twins( $post )
        );

        return $result;
    }

    /**
     * What may go wrong in search results with a title and a description,
     * by the content check's own thresholds.
     *
     * @since 2.9.0
     * @param string $title             Title as published.
     * @param string $description       Description as published.
     * @param int[]  $title_twins       Other pages with the same title.
     * @param int[]  $description_twins Other pages with the same written description.
     * @return array<int, array{field: string, type: string, message: string}>
     */
    private static function warnings( $title, $description, array $title_twins = array(), array $description_twins = array() ) {
        $warnings = array();

        if ( mb_strlen( $title ) > ThatSeoAgent_Audit::TITLE_MAY_TRUNCATE ) {
            $warnings[] = array(
                'field'   => 'title',
                'type'    => 'title_may_be_cut',
                /* translators: 1: characters, 2: limit. */
                'message' => sprintf( __( 'The title has %1$d characters: past about %2$d, search results may cut it.', 'thatseoagent' ), mb_strlen( $title ), ThatSeoAgent_Audit::TITLE_MAY_TRUNCATE ),
            );
        }

        if ( mb_strlen( $description ) > ThatSeoAgent_Audit::DESCRIPTION_MAY_TRUNCATE ) {
            $warnings[] = array(
                'field'   => 'description',
                'type'    => 'description_may_be_cut',
                /* translators: 1: characters, 2: limit. */
                'message' => sprintf( __( 'The description has %1$d characters: past about %2$d, search results may cut it.', 'thatseoagent' ), mb_strlen( $description ), ThatSeoAgent_Audit::DESCRIPTION_MAY_TRUNCATE ),
            );
        }

        if ( $title_twins ) {
            $warnings[] = array(
                'field'   => 'title',
                'type'    => 'title_shared',
                /* translators: %s: post IDs. */
                'message' => sprintf( __( 'Other pages have the same title (IDs: %s). Google asks for a distinct title on each page.', 'thatseoagent' ), implode( ', ', $title_twins ) ),
            );
        }

        if ( $description_twins ) {
            $warnings[] = array(
                'field'   => 'description',
                'type'    => 'description_shared',
                /* translators: %s: post IDs. */
                'message' => sprintf( __( 'Other pages have the same description (IDs: %s). Google asks for a distinct description on each page.', 'thatseoagent' ), implode( ', ', $description_twins ) ),
            );
        }

        return $warnings;
    }

    /**
     * Save the generated description of each post that has none.
     *
     * @since 2.9.0
     * @param array $input Ability input.
     * @return array
     */
    public static function generate_descriptions( $input ) {
        $generated = array();
        $skipped   = array();

        foreach ( array_unique( array_map( 'absint', (array) $input['post_ids'] ) ) as $post_id ) {
            $post = self::seo_post( $post_id );

            if ( is_wp_error( $post ) ) {
                $skipped[] = array(
                    'post_id' => $post_id,
                    'reason'  => $post->get_error_message(),
                );
                continue;
            }

            if ( ! current_user_can( 'edit_post', $post->ID ) ) {
                $skipped[] = array(
                    'post_id' => $post->ID,
                    'reason'  => __( 'You cannot edit this post.', 'thatseoagent' ),
                );
                continue;
            }

            if ( '' !== ThatSeoAgent_Post_Seo::get( $post, 'description' ) ) {
                $skipped[] = array(
                    'post_id' => $post->ID,
                    'reason'  => __( 'It already has a description of its own.', 'thatseoagent' ),
                );
                continue;
            }

            $description = ThatSeoAgent_Description::generate( $post );
            ThatSeoAgent_Memo::forget_post( $post );

            if ( '' === $description ) {
                $skipped[] = array(
                    'post_id' => $post->ID,
                    'reason'  => __( 'Its content has no text to describe it with.', 'thatseoagent' ),
                );
                continue;
            }

            ThatSeoAgent_Post_Seo::save( $post, array( 'description' => $description ) );
            $generated[] = array(
                'post_id'     => $post->ID,
                'description' => $description,
            );
        }

        return array(
            'generated' => $generated,
            'skipped'   => $skipped,
        );
    }

    /**
     * Audit a single post for SEO issues.
     *
     * @param array $input Ability input.
     * @return array|WP_Error
     */
    public static function audit_post_seo( $input ) {
        $post = self::seo_post( isset( $input['post_id'] ) ? $input['post_id'] : 0 );
        if ( is_wp_error( $post ) ) {
            return $post;
        }

        $audit = ThatSeoAgent_Audit::post( $post );

        // Facts for the agent to judge; the plugin draws no conclusion
        // from them. Not part of the content check on the screen, where
        // nobody reads them.
        $audit['structure'] = ThatSeoAgent_Structure::of( $post );

        return $audit;
    }

    /**
     * Scan multiple posts for SEO issues.
     *
     * @param array $input Ability input.
     * @return array|WP_Error
     */
    public static function scan_seo_issues( $input ) {
        $post_type = isset( $input['post_type'] ) ? sanitize_key( $input['post_type'] ) : 'post';
        $types     = ThatSeoAgent_Post_Seo::post_types();

        if ( 'any' === $post_type ) {
            $post_types = $types;
        } elseif ( post_type_exists( $post_type ) ) {
            $post_types = array( $post_type );
        } else {
            return new WP_Error(
                'thatseoagent_invalid_post_type',
                /* translators: %s: post types with SEO fields. */
                sprintf( __( 'That post type does not exist. Types with SEO fields: %s, or any.', 'thatseoagent' ), implode( ', ', $types ) )
            );
        }

        $any = isset( $input['status'] ) && 'any' === $input['status'];

        return ThatSeoAgent_Audit::scan_report( array(
            'limit'      => min( self::SCAN_MAX, isset( $input['limit'] ) ? absint( $input['limit'] ) : 50 ),
            'min_issues' => isset( $input['min_issues'] ) ? absint( $input['min_issues'] ) : 1,
            'post_types' => $post_types,
            'statuses'   => $any ? array( 'publish', 'draft', 'pending', 'future', 'private' ) : array( 'publish' ),
            'issue'      => isset( $input['issue'] ) ? sanitize_key( $input['issue'] ) : '',
            'offset'     => isset( $input['offset'] ) ? absint( $input['offset'] ) : 0,
        ) );
    }

    // --- Terms -----------------------------------------------------------------

    /**
     * List the terms with SEO fields.
     *
     * @since 2.9.0
     * @param array $input Ability input.
     * @return array|WP_Error
     */
    public static function list_term_seo( $input ) {
        $taxonomies = ThatSeoAgent_Term_Seo::taxonomies();

        if ( ! empty( $input['taxonomy'] ) ) {
            if ( ! in_array( $input['taxonomy'], $taxonomies, true ) ) {
                return self::seo_term( $input['taxonomy'], 0 );
            }

            $taxonomies = array( $input['taxonomy'] );
        }

        $missing = isset( $input['missing'] ) ? (string) $input['missing'] : '';
        $terms   = get_terms( array(
            'taxonomy'   => $taxonomies,
            'hide_empty' => false,
        ) );

        $list = array();
        foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
            if ( '' !== $missing && '' !== ThatSeoAgent_Term_Seo::get( $term, $missing ) ) {
                continue;
            }

            $list[] = self::describe_term( $term );
        }

        return array(
            'taxonomies' => array_values( $taxonomies ),
            'terms'      => $list,
        );
    }

    /**
     * Return the SEO of a term's archive.
     *
     * @since 2.9.0
     * @param array $input Ability input.
     * @return array|WP_Error
     */
    public static function get_term_seo( $input ) {
        $term = self::seo_term( $input['taxonomy'], $input['term_id'] );

        return is_wp_error( $term ) ? $term : self::describe_term( $term );
    }

    /**
     * Update the SEO of a term's archive.
     *
     * @since 2.9.0
     * @param array $input Ability input.
     * @return array|WP_Error
     */
    public static function update_term_seo( $input ) {
        $term = self::seo_term( $input['taxonomy'], $input['term_id'] );
        if ( is_wp_error( $term ) ) {
            return $term;
        }

        $fields = array( 'title', 'description', 'noindex' );
        if ( ! array_intersect( $fields, array_keys( $input ) ) ) {
            return new WP_Error(
                'thatseoagent_nothing_to_change',
                /* translators: %s: field names. */
                sprintf( __( 'Send at least one of: %s.', 'thatseoagent' ), implode( ', ', $fields ) )
            );
        }

        $values = array();
        foreach ( array( 'title', 'description' ) as $field ) {
            if ( array_key_exists( $field, $input ) ) {
                $values[ $field ] = (string) $input[ $field ];
            }
        }

        if ( array_key_exists( 'noindex', $input ) ) {
            $values['noindex'] = $input['noindex'] ? '1' : '';
        }

        ThatSeoAgent_Term_Seo::save( $term, $values );

        $result             = self::describe_term( get_term( $term->term_id, $term->taxonomy ) );
        $result['warnings'] = self::warnings( $result['title'], $result['description'] );

        return $result;
    }

    /**
     * A term's SEO as the term abilities return it.
     *
     * @since 2.9.0
     * @param WP_Term $term Term.
     * @return array
     */
    private static function describe_term( WP_Term $term ) {
        $link    = get_term_link( $term );
        $custom  = ThatSeoAgent_Term_Seo::all( $term );
        $noindex = ThatSeoAgent_Term_Seo::is_noindex( $term );

        return array(
            'term_id'             => (int) $term->term_id,
            'taxonomy'            => $term->taxonomy,
            'name'                => html_entity_decode( $term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
            'url'                 => is_wp_error( $link ) ? '' : $link,
            'count'               => (int) $term->count,
            'title'               => ThatSeoAgent_Term_Seo::title( $term ),
            'description'         => ThatSeoAgent_Term_Seo::description( $term ),
            'description_written' => ThatSeoAgent_Term_Seo::has_written_description( $term ),
            'noindex'             => $noindex,
            // Empty terms are left out of the sitemap too.
            'in_sitemap'          => ! $noindex && $term->count > 0,
            'custom'              => array(
                'title'       => $custom['title'],
                'description' => $custom['description'],
            ),
        );
    }
}
