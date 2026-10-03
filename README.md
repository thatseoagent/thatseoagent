# ThatSeoAgent

Lightweight SEO for WordPress — no bloat, no upsells, just what you need.

## Description

Essential SEO without the weight: meta tags, Open Graph, Twitter Cards, XML sitemaps, Schema/JSON-LD, canonical URLs, per-post SEO fields with live preview, Product schema for custom post type catalogs, IndexNow submission, llms.txt, and a Markdown version of every post for AI agents.

## Requirements

- WordPress 7.1+
- PHP 8.3+

## Installation

```bash
cp -r thatseoagent /path/to/wp-content/plugins/
wp plugin activate thatseoagent
```

Activate and it works; the defaults need no configuration. Settings — site identity, homepage, product catalogs, llms.txt, IndexNow — live in their own **ThatSeoAgent** admin menu.

With WooCommerce active, WooCommerce marks up its products — the Product with its prices, and the BreadcrumbList of the shop's pages — and ThatSeoAgent keeps their title, description and sharing image. WooCommerce's products are never a product catalog.

While another SEO plugin is active (Yoast SEO, Rank Math, All in One SEO, SEOPress, The SEO Framework, Squirrly), ThatSeoAgent outputs nothing in `<head>`, serves no sitemaps or llms.txt and leaves robots.txt alone, so the two never duplicate each other's tags. Import that plugin's data with `wp thatseoagent import`, then deactivate it.

## Features

- **Meta tags** — title, description, Open Graph, Twitter Cards, article metadata
- **XML sitemaps** — auto-generated and paginated at 1000 URLs per file, without the posts kept out of search or protected by a password
- **Robots meta** — `noindex` on search results, 404 pages and private posts, largest image and text previews everywhere else, through core's `wp_robots`
- **Schema/JSON-LD** — WebSite, Organization *or* Person, Article with its author as a Person (with a job title and profiles elsewhere, from two fields the plugin adds to the user profile), WebPage, CollectionPage, ProfilePage, BreadcrumbList, FAQPage (from question headings and Details blocks)
- **Dashboard widget** — the bulletin's headline, on the colour of its level, and its three most serious warnings on the WordPress dashboard, for administrators, in the admin's own markup
- **Admin screen** — a site bulletin: whether the site is fine in one sentence, on the color of its warning level, the warnings in force and what to do about each; plus the product report, llms.txt and settings
- **Product catalogs** — mark a custom post type as a catalog and map its brand, category, specifications and gallery; each entry becomes a validated schema.org Product
- **Breadcrumbs** — the trail the schema states, for a theme to print with `thatseoagent_breadcrumbs()`, `[thatseoagent_breadcrumbs]` or the Breadcrumbs block; accessible markup, no styles
- **Canonical URLs** — replaces core's `rel_canonical`; each page of a paginated listing or post is its own canonical, with `rel="prev"`/`rel="next"`
- **Per-post SEO** — title/description meta box with live search preview, and a "Keep out of search results" box (`noindex`), exposed to the REST API
- **Crawl cleanup** — no shortlink, RSD, WLW or generator tag, comment feeds out of search indexes; optionally only the main feed, and spam searches sent to the homepage
- **Attachment pages** — redirected to their file, as WordPress does on sites installed since 6.4
- **Site identity** — declare whether the site represents a Person or an Organization
- **Site verification** — Google Search Console, Bing, Yandex, Baidu and Pinterest codes, printed on the homepage
- **Homepage SEO** — custom title/description with `%%sitename%%`, `%%tagline%%`, `%%sep%%`
- **IndexNow** — notify Bing/Yandex on publish (opt-in: no key, no requests)
- **Markdown for AI agents** — any post or page at its URL plus `.md`, with YAML frontmatter, announced by a `rel="alternate"` link in the head and in a `Link` header; the page's own URL answers `Accept: text/markdown` with it too
- **llms.txt** — a generated index of the site linking to the Markdown versions, and `llms-full.txt` with their full text in one file
- **AI crawlers** — see which AI crawlers can read the site and why, check each one against the live server, and block them by group or one by one in robots.txt
- **Content check** — every page of a content type, a few at a time, against the same rules as That SEO Agent's MCP server: nothing Google does not ask for is reported as a problem, each finding says whether Google, accessibility guidelines or our own judgement asks for it, and a check that cannot run says so
- **WP-CLI** — generate missing meta descriptions, import from other SEO plugins, validate product schema
- **Bulk action** — "Generate meta description" on the posts list

## Sitemaps

| URL | Content |
|-----|---------|
| `/sitemap.xml` | Index |
| `/sitemap-posts.xml` | Posts (paginates to `-2`, `-3`, …) |
| `/sitemap-pages.xml` | Pages (paginates) |
| `/sitemap-categories.xml` | Categories |
| `/sitemap-tags.xml` | Tags |
| `/sitemap-{cpt}.xml` | Each public custom post type (paginates) |

`sitemap_index.xml` redirects to the index for Yoast compatibility, and the plugin rewrites the `Sitemap:` directive in `robots.txt`. WordPress's own `/wp-sitemap.xml` is switched off while these are served, so search engines see one set.

A sitemap asks for its URLs to be indexed, so it lists no post marked **Keep out of search results**, no password-protected post and no attachment page. llms.txt leaves out the same posts.

## Markdown for AI agents

Append `.md` to the URL of any published post or page:

```bash
curl https://example.com/my-post.md
curl https://example.com/about/team.md        # hierarchical pages
curl https://example.com/2026/02/my-post.md   # any permalink structure
```

Or ask the page's own URL for Markdown, as HTTP content negotiation does:

```bash
curl -H 'Accept: text/markdown' https://example.com/my-post
```

Browsers never ask for it, so people keep getting the HTML. Both responses say `Vary: Accept`, and the Markdown one tells page caches not to store it.

A cache that answers before WordPress — a page cache plugin, a CDN — would still hand agents the stored HTML. **AI index → Markdown for agents** checks this from outside on demand and says which layer answered and what to change. **Settings → Caches and CDN** has the fixes: on Apache it can write a rule at the top of `.htaccess` that sends requests asking for Markdown to WordPress before any page cache plugin serves them (elsewhere it shows the lines to copy), and it gives the Cloudflare Cache Rules steps with links to Cloudflare's documentation.

An agent fetching the HTML pays for the navigation, sidebar, footer and scripts; the Markdown is only the content, typically 80–99% fewer tokens. The frontmatter carries the title, dates, author, permalink, excerpt, the same description the meta tags use, categories, tags and featured image.

- `X-Robots-Tag: noindex` and a canonical `Link` header keep search engines on the HTML page.
- `Last-Modified` is sent and `If-Modified-Since` answered with `304`.
- Private, draft and password-protected posts follow the same rules as the HTML page.
- Cached in a transient per post (object-cache aware), invalidated when the post, its terms or its meta change. Needs pretty permalinks.

See [docs/markdown](docs/markdown/) for the details.

## Product catalogs

A catalog without WooCommerce — machinery, parts, a range of models — is usually a custom post type with its own taxonomies and meta. Under **ThatSeoAgent → Settings → Product catalogs**, every active public post type is listed — but WooCommerce's products, which WooCommerce marks up itself; tick the catalog and map its fields:

| Field | Source | Schema |
|-------|--------|--------|
| Brand | taxonomy | `brand` (`Brand`) |
| Category | taxonomy | `category`, as "Parent > Child" |
| Specifications | meta key: JSON or array of name/value pairs, or a name => value map | `additionalProperty` (`PropertyValue`) |
| Gallery | meta key: attachment IDs, comma-separated or array | extra `image` entries |
| SKU, MPN, GTIN | meta keys | `sku`, `mpn`, `gtin` |

Taxonomies and meta keys are detected from the stored data and pre-selected by name. Name, description and featured image come from the post. Each entry is output as the page's `mainEntity`, instead of an Article.

`/catalog.jsonl` gives agents the whole catalog as JSON Lines: each entry's Product markup, one per line, 100 per page (`?page=2`, announced with `Link: rel="next"`). Like llms.txt, it is for agents; search engines read the markup on each page.

Values are validated and dropped rather than output half-formed. **ThatSeoAgent → Products** lists what each product is missing, and `wp thatseoagent validate-products` checks the whole catalog. Without a price no `offers` is output, so Google shows no product rich result — that needs offers, a review or a rating — but the markup still describes each product to search engines and AI assistants.

## llms.txt

`/llms.txt` opens with the site's about, contact and privacy pages, then lists the posts served as Markdown and the product catalogs — each catalog's 20 newest products, and a link to all of them in `/catalog.jsonl` — each entry linking to its `.md` version where there is one, with its description. It is a [proposed convention](https://llmstxt.org), not a standard or a ranking factor. Regenerated when a post or the site identity changes; switch it off in the settings. A physical `llms.txt` in the site root takes precedence.

`/llms-full.txt` carries the content of those pages instead of links: each page with a Markdown version, in the same order, as its title, its URL and its Markdown, so an agent reads the site in one request. It is served and switched off with llms.txt, which links to it under `## Optional`, and stops before 2 MB (filter `thatseoagent_llms_full_max_bytes`), ending with how many pages did not fit. A physical `llms-full.txt` takes precedence.

## AI crawlers

**ThatSeoAgent → AI crawlers** reads the robots.txt the site actually serves — the physical file in the site root if there is one, else the one WordPress builds with every plugin's edits — and shows, for each known crawler, whether it may read the site and which rule decides it. **Check access now** requests the homepage as each crawler, to catch a firewall, CDN or security plugin turning it away; it only runs when clicked.

The crawlers are grouped by what they do: search and answers (OAI-SearchBot, Claude-SearchBot, PerplexityBot, DuckAssistBot), visits asked for by a person (ChatGPT-User, Claude-User, Perplexity-User, Meta-ExternalFetcher) and training (GPTBot, ClaudeBot, Google-Extended, Applebot-Extended, Meta-ExternalAgent, Amazonbot, CCBot, Bytespider). Googlebot, Bingbot and Applebot are shown but never blocked from here.

In **Settings → AI crawlers**, allow or block each group, with exceptions crawler by crawler. Blocking adds one group with `Disallow: /` to the virtual robots.txt; allowing writes nothing, because a crawler named in its own group stops following the site's `*` rules. Nothing is written until something is blocked, or while the site discourages search engines. With a physical robots.txt the rules cannot be served, and the screen gives the lines to paste instead.

The bulletin warns when robots.txt keeps search crawlers out, when a physical file ignores the chosen rules, or when another plugin writes crawler rules too. Blocking training is a choice, never a warning.

## Privacy

The plugin sends no data anywhere **unless you configure an IndexNow API key**. (The AI crawler access check only requests the site's own homepage.) With a key set, publishing or updating a post queues a background request to `https://api.indexnow.org/indexnow` containing your site host, the key, and the URL of the post. Clearing the key stops all outbound requests. The plugin also serves the `{key}.txt` verification file IndexNow requires.

## Architecture

One class per file under `includes/`, grouped by concept and loaded by `includes/autoload.php` (an explicit class map: add a line there for a new class). `ThatSeoAgent` is the composition root: it calls each module's `register()` and nothing else. `CONTEXT.md` defines the terms used below.

| Folder | Module | Responsibility |
|--------|--------|----------------|
| `includes/` | `ThatSeoAgent` | Composition root: which modules run, and when |
| | `ThatSeoAgent_Settings` | Gathers each module's option (`setting()`) and registers it, with a schema, for the form and the REST API |
| | `ThatSeoAgent_Memo` | Every per-request cache: `forget_post()` after each post in a loop, `reset()` to read the site again |
| | `ThatSeoAgent_Loopback` | Every request the checks make to the site itself, through the WordPress HTTP API's filters |
| `content/` | `ThatSeoAgent_Content` | The single answer to "what is this post's content?" |
| | `ThatSeoAgent_Description` | The single answer to "what description does this post get?" |
| | `ThatSeoAgent_FAQ`, `ThatSeoAgent_FAQ_Section` | Reads content into sections; FAQ extraction |
| | `ThatSeoAgent_Post_Seo` | Per-post SEO fields: which post types get them, storage, sanitization and slashing |
| | `ThatSeoAgent_Structure` | How a post is built, as facts for an agent to judge |
| | `ThatSeoAgent_Image` | The image a page is represented by, for sharing and for the schema |
| | `ThatSeoAgent_Primary_Term` | The category that names a post where only one fits: the breadcrumb trail, article:section and the Product markup's category |
| | `ThatSeoAgent_Term_Seo` | Per-term SEO fields — title, description and noindex of a category, tag, brand or product category archive — on the term screens, in the head and in the sitemap |
| | `ThatSeoAgent_Sample_Content` | WordPress's sample post and page, while still published |
| `head/` | `ThatSeoAgent_Meta` | `<head>` meta tags and canonical |
| | `ThatSeoAgent_Title` | The single answer to "what title does this post show in search results?": the `<title>`, og:title, the editor's preview |
| | `ThatSeoAgent_Schema` | JSON-LD graph |
| | `ThatSeoAgent_Breadcrumbs` | The breadcrumb trail: the BreadcrumbList, and the breadcrumbs a theme prints |
| `indexing/` | `ThatSeoAgent_Indexing` | The single answer to "may search engines index this?": robots meta, and what the sitemaps and llms.txt may list |
| | `ThatSeoAgent_Pagination` | Paginated listings and posts: page numbers, their URLs, `rel="prev"`/`rel="next"` |
| | `ThatSeoAgent_Attachment_Redirect` | Attachment pages lead to their file |
| | `ThatSeoAgent_Crawl_Cleanup` | What nobody needs to crawl: shortlinks, RSD, the generator, extra feeds, spam searches |
| `site/` | `ThatSeoAgent_Identity` | Who the site is: the settings, and the Person or Organization node and the X handle they make |
| | `ThatSeoAgent_Homepage` | Homepage title and description settings |
| | `ThatSeoAgent_Default_Author` | The author credited on posts without one |
| | `ThatSeoAgent_Author_Profile` | An author's job title and profiles elsewhere, in the user profile |
| | `ThatSeoAgent_Trust_Pages` | The about, contact and privacy pages the bulletin looks for |
| | `ThatSeoAgent_New_Types` | Public content types the site owner has not looked at yet |
| | `ThatSeoAgent_Verification` | The verification tags of search engine consoles, on the homepage |
| `catalog/` | `ThatSeoAgent_Product` | Product catalogs: mapping, detection, Product node, validation |
| | `ThatSeoAgent_Product_Report` | How complete the catalog is: summary and per-product report |
| | `ThatSeoAgent_Catalog_Feed` | The catalog as JSON Lines, one Product per line, for agents |
| | `ThatSeoAgent_Product_Settings` | The catalog's settings section and field mapping |
| `sitemap/` | `ThatSeoAgent_Sitemap` | Sitemap routes and rendering (returns XML strings) |
| | `ThatSeoAgent_Robots` | The `Sitemap:` directive and the blocked AI crawlers in robots.txt |
| `crawlers/` | `ThatSeoAgent_AI_Crawlers` | The known AI crawlers, the site owner's choices and the lines they add to robots.txt |
| | `ThatSeoAgent_Crawler_Access` | Who can read the site: the served robots.txt read per crawler, and the on-demand access check |
| | `ThatSeoAgent_Robots_Parser` | Reads robots.txt as a crawler does (RFC 9309) |
| `markdown/` | `ThatSeoAgent_Markdown` | A post as Markdown with YAML frontmatter |
| | `ThatSeoAgent_Markdown_Endpoint` | The `.md` URLs and the `rel="alternate"` link |
| | `ThatSeoAgent_Markdown_Cache` | Per-post Markdown cache and its invalidation |
| | `ThatSeoAgent_Markdown_Check` | Whether agents asking for Markdown get it: the on-demand check from outside |
| | `ThatSeoAgent_Markdown_Htaccess` | The .htaccess rule that keeps page caches away from Markdown requests |
| | `ThatSeoAgent_Cache_Settings` | Settings → Caches and CDN: the .htaccess rule and the Cloudflare steps |
| | `ThatSeoAgent_Llms` | llms.txt |
| | `ThatSeoAgent_Llms_Full` | llms-full.txt: the full text of the pages llms.txt lists |
| `audit/` | `ThatSeoAgent_Audit` | The SEO audit of a post, and site scans |
| | `ThatSeoAgent_Audit_Run` | The batched content check and its last results |
| | `ThatSeoAgent_Duplicates` | Pages that share a title or a description |
| | `ThatSeoAgent_Links` | How the pages link to each other: orphan pages and broken links |
| `bulletin/` | `ThatSeoAgent_Bulletin` | The site's bulletin: gathers each checking module's `bulletin()` — its observations and warnings — ranks the warnings and words the headline |
| | `ThatSeoAgent_Checks` | The access check's and the Markdown check's last two results, and their weekly run |
| | `ThatSeoAgent_Readings` | What the dashboard shows beside it: SEO field counts, the public files served |
| `admin/` | `ThatSeoAgent_App` | The ThatSeoAgent screen: navigation, views, where each action leads, level colors, styles, scripts |
| | `ThatSeoAgent_Icons` | The screen's inline SVG icons |
| | `ThatSeoAgent_Meta_Box` | The SEO fields in the post editor |
| | `ThatSeoAgent_Bulk_Descriptions` | The "Generate meta description" bulk action |
| | `ThatSeoAgent_Dashboard_Widget` | The bulletin on the WordPress dashboard |
| | `views/` | The screen's templates |
| `rest/` | `ThatSeoAgent_REST` | Registers the screen's controllers (`thatseoagent/v1`) |
| | `ThatSeoAgent_REST_Controller` | Shared by every controller: namespace, permission, uncached responses |
| | `ThatSeoAgent_REST_Bulletin`, `_Preferences`, `_Audit`, `_Llms` | One controller per resource |
| `tooling/` | `ThatSeoAgent_CLI` | WP-CLI commands |
| | `ThatSeoAgent_Abilities` | Abilities API registration: posts and terms |
| | `ThatSeoAgent_Site_Abilities` | Abilities API registration: settings and site reports |
| | `ThatSeoAgent_Importer` | Import from Yoast SEO, Rank Math, All in One SEO |
| `integrations/` | `ThatSeoAgent_Compat` | Stepping aside while another SEO plugin is active |
| | `ThatSeoAgent_WooCommerce` | What WooCommerce marks up itself: its products and their breadcrumbs |
| | `ThatSeoAgent_IndexNow` | IndexNow key, verification file and submission |
| | `ThatSeoAgent_MCP` | The plugin's own MCP server, when Lean MCP is active |

## Filters

**Title and description**

| Filter | Purpose |
|--------|---------|
| `thatseoagent_document_title` | Override the title entirely (receives context and post) |
| `thatseoagent_title` | The resolved title, in the `<title>` and the meta tags alike (receives context and post) |
| `thatseoagent_title_separator` | Title separator (default `\|`) |
| `thatseoagent_description` | The resolved description (receives context and post) |
| `thatseoagent_description_filler_patterns` | Intro-filler regexes skipped when generating |

Context is one of `home`, `single`, `archive`, `taxonomy`, `search`, `author`, `date`, `404`, `other`. The post is the one whose title or description it is, `null` on a listing. For a post the filters run wherever its title or description is used — the head, the markup, the Markdown version, llms.txt, the editor, the content check — not only while its page is served, so branch on the post rather than on conditional tags like `is_page()`.

**Social**

`thatseoagent_og_title` · `thatseoagent_og_site_name` · `thatseoagent_og_locale` · `thatseoagent_twitter_handle` · `thatseoagent_default_image`

**Schema**

A post's graph is `ThatSeoAgent_Schema::for_post( $post )`, the same inside its page's request and outside it — the `get-post-seo` ability returns it — so the filters that receive the post should branch on it rather than on conditional tags like `is_singular()`.

| Filter | Purpose |
|--------|---------|
| `thatseoagent_primary_entity` | `'person'` or `'organization'` — decides the publisher |
| `thatseoagent_website_schema` | WebSite node |
| `thatseoagent_organization_schema` | Organization node |
| `thatseoagent_person_schema` | Person node |
| `thatseoagent_webpage_schema` | WebPage node (receives the post) |
| `thatseoagent_breadcrumb_schema` | BreadcrumbList node (receives the post, `null` on a listing) |
| `thatseoagent_breadcrumb_trail` | The breadcrumb trail, for the schema and the visible breadcrumbs (receives the post, `null` on a listing) |
| `thatseoagent_schema_graph` | The complete `@graph` before output (receives the post, `null` on any other view) |
| `thatseoagent_faq_schema_enabled` | Disable FAQ schema per post |
| `thatseoagent_faq_pairs` | The extracted Q&A pairs |
| `thatseoagent_faq_numbered_enabled` | Opt in to numbered-heading FAQs (off) |
| `thatseoagent_faq_thematic_enabled` | Opt in to thematic FAQs (off) |
| `thatseoagent_author_schema` | Person node of a post author |
| `thatseoagent_listing_page_schema` | CollectionPage / ProfilePage node of an archive |
| `thatseoagent_product_post_types` | Post types marked up as products |
| `thatseoagent_product_schema` | Product node |
| `thatseoagent_product_properties` | Specifications before they become PropertyValues |

Both FAQ opt-ins are off by default: they synthesise questions that do not appear on the page, which conflicts with Google's requirement that marked-up content be visible.

**Sitemaps and admin**

`thatseoagent_sitemap_entries` (the index listing) · `thatseoagent_meta_box_post_types` · action `thatseoagent_sitemap_index`

**Other SEO plugins and llms.txt**

`thatseoagent_other_seo_plugin` (return `''` to keep ThatSeoAgent's output on) · `thatseoagent_woocommerce_states_breadcrumbs` (return `false` to keep ThatSeoAgent's BreadcrumbList on WooCommerce's pages, for a theme that prints none) · `thatseoagent_ai_crawlers` (the known crawlers and their groups) · `thatseoagent_llms_txt_post_types` · `thatseoagent_llms_txt_limit` · `thatseoagent_llms_txt`

**Markdown**

| Filter | Purpose |
|--------|---------|
| `thatseoagent_markdown_post_types` | Post types served as Markdown (default `post`, `page`) |
| `thatseoagent_markdown_frontmatter` | The frontmatter fields |
| `thatseoagent_markdown_html` | The HTML converted to Markdown (page builders) |
| `thatseoagent_markdown_include_custom_fields` | Opt in to custom fields in the frontmatter (off) |
| `thatseoagent_markdown_custom_fields` | The custom fields included |
| `thatseoagent_markdown_cache_duration` | Cache lifetime in seconds (default one hour) |
| `thatseoagent_markdown_alternate_link` | Print the `rel="alternate"` link to the `.md` URL (default on) |

Custom fields are off by default: post meta not registered with `show_in_rest` is not public, and plugins routinely keep private data in it.

## Examples

```php
// Custom description for a specific page
add_filter( 'thatseoagent_description', function ( $desc, $context, $post ) {
    return $post && 'special' === $post->post_name ? 'Custom description' : $desc;
}, 10, 3 );

// SEO fields on a custom post type
add_filter( 'thatseoagent_meta_box_post_types', function ( $types ) {
    $types[] = 'product';
    return $types;
} );

// Site-specific intro filler to skip when generating descriptions
add_filter( 'thatseoagent_description_filler_patterns', function ( $patterns ) {
    $patterns[] = '/welcome to our blog/i';
    return $patterns;
} );

// Extra sitemaps in the index
add_filter( 'thatseoagent_sitemap_entries', function ( $entries ) {
    $entries[] = array( 'loc' => home_url( '/custom.xml' ), 'lastmod' => null );
    return $entries;
} );
```

## Abilities API

Registered on `wp_abilities_api_init`:

| Ability | Description | Capability |
|---------|-------------|------------|
| `thatseoagent/get-sitemap-urls` | Every sitemap URL the site publishes | `manage_options` |
| `thatseoagent/get-post-seo` | SEO of a post as its page publishes it: title, description, canonical, noindex, sharing image, primary terms, JSON-LD | `edit_post` |
| `thatseoagent/update-post-seo` | Update title, description, noindex, sharing image and primary terms; returns warnings (may be cut, shared with another page) | `edit_post` |
| `thatseoagent/audit-post-seo` | Audit one post, 0–100 score, and its structure as facts | `edit_post` |
| `thatseoagent/scan-seo-issues` | Scan many posts — one type or `any`, published or `any` status, one issue type — worst first, with each issue in full and `next_offset` to go on | `manage_options` |
| `thatseoagent/generate-descriptions` | Save the generated description of up to 100 posts that have none | `edit_post` per post |
| `thatseoagent/list-term-seo` | Terms of every taxonomy with SEO fields, with what their archive publishes; filter by what is missing | `manage_categories` |
| `thatseoagent/get-term-seo` | SEO of a term's archive | `edit_term` |
| `thatseoagent/update-term-seo` | Update a term archive's title, description and noindex | `edit_term` |
| `thatseoagent/get-seo-settings` | The plugin's settings (identity, homepage, verification, catalog, crawlers…), what each does and its schema | `manage_options` |
| `thatseoagent/update-seo-settings` | Change one setting, through its schema and sanitizer; `tracking` also needs `unfiltered_html` | `manage_options` |
| `thatseoagent/get-duplicates` | Pages sharing a title or a written description | `manage_options` |
| `thatseoagent/get-link-report` | Orphan pages, broken internal links, navigation that leads nowhere | `manage_options` |
| `thatseoagent/get-site-bulletin` | The site's bulletin: level, warnings with where to fix them, observations | `manage_options` |

A post or term that does not exist, or has no SEO fields (a revision, an attachment, a post in the trash), is answered with why rather than a permissions error.

The audit applies the content check's rules: titles and descriptions that may be cut or that another page shares, a missing description, links search engines cannot follow, alt text — telling missing alt from the empty alt of decorative images — headings as accessibility, internal links, orphan pages and broken links as our own judgement, and, on catalog entries, their Product markup. Each finding carries its `source`, and checks that could not run come back in `not_measured`.

It also returns `structure`: how the post is built, as facts with no verdict — its headings in order, lists, tables, quotes, question-and-answer blocks, figures and percentages, paragraph and word counts, its first 150 words, its dates and the URL of its Markdown version. Nobody publishes how AI assistants choose what to cite, so the plugin scores none of it; an agent reviewing the site reads these facts, and the Markdown, and draws its own conclusions.

Every option is also registered with `show_in_rest`, so administrators can read and update them at `/wp/v2/settings`.

## MCP server

With the Lean MCP plugin active, the fourteen abilities are also the tools of a dedicated MCP server, `thatseoagent`, whatever the theme. As tools the slash becomes a hyphen: `thatseoagent-update-post-seo`.

At `https://<site>/wp-json/lean-mcp/thatseoagent`, with the application password of an editor or administrator (`edit_others_posts`); anyone else gets a 401 or 403. Each tool also checks its own capability.

```bash
claude mcp add --transport http thatseoagent \
  https://<site>/wp-json/lean-mcp/thatseoagent \
  --header "Authorization: Basic $(printf 'USER:APPLICATION PASSWORD' | base64)"
```

Without Lean MCP the server is not created; the abilities stay available through the Abilities API.

## WP-CLI

```bash
wp thatseoagent generate-descriptions [--post-type=post] [--batch-size=50] [--limit=0] [--dry-run]
wp thatseoagent import --from=<yoast|rankmath|aioseo> [--post-type=<types>] [--identity] [--overwrite] [--dry-run]
wp thatseoagent validate-products [--post-type=<type>] [--all] [--format=<table|csv|json>]
```

- `generate-descriptions` writes a meta description for published posts that lack one, using the same rule the front end applies.
- `import` copies titles and descriptions — and with `--identity`, the site's Person or Organization, name, logo and social profiles — from another SEO plugin, resolving its template variables. Values with unknown variables, and titles equal to the default, are skipped; existing values are kept unless `--overwrite`.
- `validate-products` lists catalog entries whose Product markup has errors or warnings.

## Development

### Admin screen styles

The admin screen is styled with [Tailwind CSS](https://tailwindcss.com) v4 and made interactive with [Alpine.js](https://alpinejs.dev). The compiled stylesheet (`assets/build/admin.css`) and Alpine itself (`assets/vendor/alpine.min.js`, pinned in `package.json`) are committed; only changing the templates, `assets/src/admin.css` or the Alpine version needs Node:

```bash
pnpm install
pnpm run build          # Alpine into assets/vendor, then the CSS
pnpm run watch:css      # while editing templates
```

The screen's components live in `assets/admin/app.js` and register on `alpine:init`. Every view is server-rendered first and keeps working without scripts; the components start from the state printed into the page (`window.thatSeoAgent`), so nothing is fetched on load. Because Tailwind's utilities are `!important`, use `x-show.important` on elements that also carry a display utility.

REST endpoints for the screen, all administrators-only, under `thatseoagent/v1`: `GET /bulletin`, `POST /preferences`, `GET /audit`, `POST /audit/runs`, `POST|DELETE /audit/runs/{token}`, `POST /llms`. Settings are saved through core's `/wp/v2/settings`.

Tailwind scans `includes/admin/`, `includes/crawlers/`, the Caches and CDN settings and `assets/admin/app.js` only, so the output holds just the classes they use. Colors are named by role (`bg-paper`, `bg-sheet`, `text-ink-2`, `border-rule`, `text-met`, `bg-level-yellow`…) and resolve to CSS variables that switch between the Day and Night editions. The screen wears the That SEO Agent brand shared with the MCP server and the website (see DESIGN.md). Space Grotesk and Space Mono are self-hosted from `assets/fonts/` (SIL Open Font License); `pnpm run vendor:fonts` copies them again from npm, should they need updating.

### Dependencies

The HTML-to-Markdown library, `league/html-to-markdown`, ships in `vendor-prefixed/` with its namespace rewritten to `ThatSeoAgent\Dependencies\` by [Strauss](https://github.com/BrianHenryIE/strauss), so another plugin loading its own copy cannot conflict. `vendor-prefixed/` is committed; installing the plugin needs no Composer.

Strauss runs as a `.phar`, not as a Composer package: its dependencies clash with Pest's. The `.phar` is not committed; before the first `composer install`, download it to `bin/strauss.phar`:

```bash
mkdir -p bin && curl -fL -o bin/strauss.phar https://github.com/BrianHenryIE/strauss/releases/download/0.30.0/strauss.phar
```

To update the library:

```bash
composer update league/html-to-markdown   # Strauss runs on post-update-cmd
git add vendor-prefixed/ composer.lock
```

### Tests and checks

Tests are written with [Pest](https://pestphp.com/), in four suites. `tests/Unit` runs on the plugin's classes alone, with no WordPress. The other three run inside a disposable WordPress started with [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) (Docker), where the plugin is active:

- `tests/WordPress` calls the plugin in-process. `pageAt()` serves a URL up to the template and reads the `<head>` it prints. Each test runs inside a database transaction that is rolled back afterwards, so nothing it writes reaches the next one. No HTTP request leaves the site: a test answers the ones it expects with `fakeHttp()`, and any other request fails it.
- `tests/Http` asks the site's web server with `fetch()`, for the responses that end the request (sitemaps, Markdown, llms.txt, the MCP server) and their headers.
- `tests/Cli` runs `wp thatseoagent …` with `wpCli()`, as a person would.

The web server and WP-CLI cannot see an uncommitted transaction, so the Http and Cli tests write for real and `Fixtures` undoes it after each one. `tests/mu-plugin.php`, which wp-env loads into the test site only, gives them switches for situations a test cannot set up from its own process, such as another SEO plugin being active.

```bash
composer test               # Unit suite; the WordPress tests skip themselves
pnpm env:start              # Start the WordPress for the tests (http://localhost:8894)
pnpm test:wordpress         # WordPress, Http and Cli suites
pnpm env:stop

composer analyse            # PHPStan, level 5, with phpstan-baseline.neon
composer lint               # Pint, checking the code style (pint.json)
composer format             # Pint, fixing it
```

The MCP server's tests need Lean MCP active in the test site, and skip themselves without it. wp-env mounts it from a local `.wp-env.override.json`, not committed, that points at your copy:

```json
{
    "plugins": [".", "/path/to/lean-mcp"]
}
```

Run `pnpm env:start` again after creating it.

## Translations

Ships with Spanish (`es_ES`). To add a language, copy `languages/thatseoagent.pot` and compile:

```bash
wp i18n make-mo languages/thatseoagent-<locale>.po
```

## Uninstalling

Deleting the plugin removes its options, its post meta and any queued IndexNow events, on every site of a multisite network. Deactivating leaves your data intact.

---

## Credits

ThatSeoAgent began as a fork of [Lean SEO](https://github.com/Sarai-Chinwag/lean-seo) 1.9.0, by [Sarai Chinwag](https://saraichinwag.com) for [Extra Chill](https://extrachill.com), released under the GPL. It has since been rewritten and extended; the original copyright is kept in [LICENSE](LICENSE).

Bundled: [Alpine.js](https://alpinejs.dev) (MIT), [league/html-to-markdown](https://github.com/thephpleague/html-to-markdown) (MIT), [Space Grotesk](https://github.com/floriankarsten/space-grotesk) and [Space Mono](https://github.com/googlefonts/spacemono) (SIL Open Font License), icons from [Lucide](https://lucide.dev) (ISC).

---

**License:** GPL-2.0-or-later
**Author:** Angel Cruz
