# Lean SEO

Lightweight SEO for WordPress — no bloat, no upsells, just what you need.

## Description

Essential SEO without the weight: meta tags, Open Graph, Twitter Cards, XML sitemaps, Schema/JSON-LD, canonical URLs, per-post SEO fields with live preview, Product schema for custom post type catalogs, IndexNow submission, llms.txt, and a Markdown version of every post for AI agents.

## Requirements

- WordPress 7.1+
- PHP 7.4+

## Installation

```bash
cp -r lean-seo /path/to/wp-content/plugins/
wp plugin activate lean-seo
```

Activate and it works; the defaults need no configuration. Settings — site identity, homepage, product catalogs, llms.txt, IndexNow — live in their own **Lean SEO** admin menu.

While another SEO plugin is active (Yoast SEO, Rank Math, All in One SEO, SEOPress, The SEO Framework, Squirrly), Lean SEO outputs nothing in `<head>`, serves no sitemaps or llms.txt and leaves robots.txt alone, so the two never duplicate each other's tags. Import that plugin's data with `wp lean-seo import`, then deactivate it.

## Features

- **Meta tags** — title, description, Open Graph, Twitter Cards, article metadata
- **XML sitemaps** — auto-generated and paginated at 1000 URLs per file
- **Schema/JSON-LD** — WebSite, Organization *or* Person, Article with its author as a Person, WebPage, CollectionPage, ProfilePage, BreadcrumbList, FAQPage (from question headings and Details blocks)
- **Admin screen** — a site bulletin: whether the site is fine in one sentence, on the color of its warning level, the warnings in force and what to do about each; plus the product report, llms.txt and settings
- **Product catalogs** — mark a custom post type as a catalog and map its brand, category, specifications and gallery; each entry becomes a validated schema.org Product
- **Canonical URLs** — replaces core's `rel_canonical`
- **Per-post SEO** — title/description meta box with live search preview, exposed to the REST API
- **Site identity** — declare whether the site represents a Person or an Organization
- **Homepage SEO** — custom title/description with `%%sitename%%`, `%%tagline%%`, `%%sep%%`
- **IndexNow** — notify Bing/Yandex on publish (opt-in: no key, no requests)
- **Markdown for AI agents** — any post or page at its URL plus `.md`, with YAML frontmatter, announced by a `rel="alternate"` link
- **llms.txt** — a generated index of the site linking to the Markdown versions
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

`sitemap_index.xml` redirects to the index for Yoast compatibility, and the plugin rewrites the `Sitemap:` directive in `robots.txt`.

## Markdown for AI agents

Append `.md` to the URL of any published post or page:

```bash
curl https://example.com/my-post.md
curl https://example.com/about/team.md        # hierarchical pages
curl https://example.com/2026/02/my-post.md   # any permalink structure
```

An agent fetching the HTML pays for the navigation, sidebar, footer and scripts; the Markdown is only the content, typically 80–99% fewer tokens. The frontmatter carries the title, dates, author, permalink, excerpt, the same description the meta tags use, categories, tags and featured image.

- `X-Robots-Tag: noindex` and a canonical `Link` header keep search engines on the HTML page.
- `Last-Modified` is sent and `If-Modified-Since` answered with `304`.
- Private, draft and password-protected posts follow the same rules as the HTML page.
- Cached in a transient per post (object-cache aware), invalidated when the post, its terms or its meta change. Needs pretty permalinks.

See [docs/markdown](docs/markdown/) for the details.

## Product catalogs

A catalog without WooCommerce — machinery, parts, a range of models — is usually a custom post type with its own taxonomies and meta. Under **Lean SEO → Settings → Product catalogs**, every active public post type is listed; tick the catalog and map its fields:

| Field | Source | Schema |
|-------|--------|--------|
| Brand | taxonomy | `brand` (`Brand`) |
| Category | taxonomy | `category`, as "Parent > Child" |
| Specifications | meta key: JSON or array of name/value pairs, or a name => value map | `additionalProperty` (`PropertyValue`) |
| Gallery | meta key: attachment IDs, comma-separated or array | extra `image` entries |
| SKU, MPN, GTIN | meta keys | `sku`, `mpn`, `gtin` |

Taxonomies and meta keys are detected from the stored data and pre-selected by name. Name, description and featured image come from the post. Each entry is output as the page's `mainEntity`, instead of an Article.

Values are validated and dropped rather than output half-formed. **Lean SEO → Products** lists what each product is missing, and `wp lean-seo validate-products` checks the whole catalog. Without a price no `offers` is output, so Google shows no product rich result — that needs offers, a review or a rating — but the markup still describes each product to search engines and AI assistants.

## llms.txt

`/llms.txt` lists the posts served as Markdown and the product catalogs, each linking to its `.md` version where there is one, with its description. It is a [proposed convention](https://llmstxt.org), not a standard or a ranking factor. Regenerated when a post or the site identity changes; switch it off in the settings. A physical `llms.txt` in the site root takes precedence.

## Privacy

The plugin sends no data anywhere **unless you configure an IndexNow API key**. With a key set, publishing or updating a post queues a background request to `https://api.indexnow.org/indexnow` containing your site host, the key, and the URL of the post. Clearing the key stops all outbound requests. The plugin also serves the `{key}.txt` verification file IndexNow requires.

## Architecture

One class per file under `includes/`, grouped by concept and loaded by `includes/autoload.php` (an explicit class map: add a line there for a new class). `Lean_SEO` is the composition root: it calls each module's `register()` and nothing else. `CONTEXT.md` defines the terms used below.

| Folder | Module | Responsibility |
|--------|--------|----------------|
| `includes/` | `Lean_SEO` | Composition root: which modules run, and when |
| | `Lean_SEO_Settings` | Gathers each module's option (`setting()`) and registers it, with a schema, for the form and the REST API |
| | `Lean_SEO_Memo` | Every per-request cache: `forget_post()` after each post in a loop, `reset()` to read the site again |
| `content/` | `Lean_SEO_Content` | The single answer to "what is this post's content?" |
| | `Lean_SEO_Description` | The single answer to "what description does this post get?" |
| | `Lean_SEO_FAQ`, `Lean_SEO_FAQ_Section` | Reads content into sections; FAQ extraction |
| | `Lean_SEO_Post_Seo` | Per-post SEO fields: which post types get them, storage, sanitization and slashing |
| `head/` | `Lean_SEO_Meta` | `<head>` meta tags and canonical |
| | `Lean_SEO_Title` | The document `<title>`: overrides and separator |
| | `Lean_SEO_Schema` | JSON-LD graph |
| `site/` | `Lean_SEO_Identity`, `Lean_SEO_Identity_Applier` | Who the site is: settings, and the filters that feed them to meta tags and schema |
| | `Lean_SEO_Homepage`, `Lean_SEO_Homepage_Applier` | Homepage title and description: settings, and their filters |
| | `Lean_SEO_Default_Author` | The author credited on posts without one |
| `catalog/` | `Lean_SEO_Product` | Product catalogs: mapping, detection, Product node, validation |
| | `Lean_SEO_Product_Report` | How complete the catalog is: summary and per-product report |
| | `Lean_SEO_Product_Settings` | The catalog's settings section and field mapping |
| `sitemap/` | `Lean_SEO_Sitemap` | Sitemap routes and rendering (returns XML strings) |
| | `Lean_SEO_Robots` | The `Sitemap:` directive in robots.txt |
| `markdown/` | `Lean_SEO_Markdown` | A post as Markdown with YAML frontmatter |
| | `Lean_SEO_Markdown_Endpoint` | The `.md` URLs and the `rel="alternate"` link |
| | `Lean_SEO_Markdown_Cache` | Per-post Markdown cache and its invalidation |
| | `Lean_SEO_Llms` | llms.txt |
| `audit/` | `Lean_SEO_Audit` | The SEO audit of a post, and site scans |
| | `Lean_SEO_Audit_Run` | The batched content check and its last results |
| `bulletin/` | `Lean_SEO_Bulletin` | The site's bulletin: facts asked of each module, rules that turn them into warnings |
| | `Lean_SEO_Readings` | What the dashboard shows beside it: SEO field counts, the public files served |
| `admin/` | `Lean_SEO_App` | The Lean SEO screen: navigation, views, where each action leads, level colors, styles, scripts |
| | `Lean_SEO_Icons` | The screen's inline SVG icons |
| | `Lean_SEO_Meta_Box` | The SEO fields in the post editor |
| | `Lean_SEO_Bulk_Descriptions` | The "Generate meta description" bulk action |
| | `views/` | The screen's templates |
| `rest/` | `Lean_SEO_REST` | Registers the screen's controllers (`lean-seo/v1`) |
| | `Lean_SEO_REST_Controller` | Shared by every controller: namespace, permission, uncached responses |
| | `Lean_SEO_REST_Bulletin`, `_Preferences`, `_Audit`, `_Llms` | One controller per resource |
| `tooling/` | `Lean_SEO_CLI` | WP-CLI commands |
| | `Lean_SEO_Abilities` | Abilities API registration |
| | `Lean_SEO_Importer` | Import from Yoast SEO, Rank Math, All in One SEO |
| `integrations/` | `Lean_SEO_Compat` | Stepping aside while another SEO plugin is active |
| | `Lean_SEO_IndexNow` | IndexNow key, verification file and submission |

## Filters

**Title and description**

| Filter | Purpose |
|--------|---------|
| `lean_seo_document_title` | Override the `<title>` entirely (receives context) |
| `lean_seo_title` | The resolved SEO title |
| `lean_seo_title_separator` | Title separator (default `\|`) |
| `lean_seo_description` | The resolved description (receives context) |
| `lean_seo_custom_description` | Legacy short-circuit, runs first |
| `lean_seo_description_filler_patterns` | Intro-filler regexes skipped when generating |

Context is one of `home`, `single`, `archive`, `taxonomy`, `search`, `author`, `date`, `404`, `other`.

**Social**

`lean_seo_og_title` · `lean_seo_og_site_name` · `lean_seo_og_locale` · `lean_seo_twitter_handle` · `lean_seo_default_image`

**Schema**

| Filter | Purpose |
|--------|---------|
| `lean_seo_primary_entity` | `'person'` or `'organization'` — decides the publisher |
| `lean_seo_website_schema` | WebSite node |
| `lean_seo_organization_schema` | Organization node |
| `lean_seo_person_schema` | Person node |
| `lean_seo_webpage_schema` | WebPage node |
| `lean_seo_breadcrumb_schema` | BreadcrumbList node |
| `lean_seo_schema_graph` | The complete `@graph` before output |
| `lean_seo_faq_schema_enabled` | Disable FAQ schema per post |
| `lean_seo_faq_pairs` | The extracted Q&A pairs |
| `lean_seo_faq_numbered_enabled` | Opt in to numbered-heading FAQs (off) |
| `lean_seo_faq_thematic_enabled` | Opt in to thematic FAQs (off) |
| `lean_seo_author_schema` | Person node of a post author |
| `lean_seo_listing_page_schema` | CollectionPage / ProfilePage node of an archive |
| `lean_seo_product_post_types` | Post types marked up as products |
| `lean_seo_product_schema` | Product node |
| `lean_seo_product_properties` | Specifications before they become PropertyValues |

Both FAQ opt-ins are off by default: they synthesise questions that do not appear on the page, which conflicts with Google's requirement that marked-up content be visible.

**Sitemaps and admin**

`lean_seo_sitemap_entries` (the index listing) · `lean_seo_meta_box_post_types` · action `lean_seo_sitemap_index`

**Other SEO plugins and llms.txt**

`lean_seo_other_seo_plugin` (return `''` to keep Lean SEO's output on) · `lean_seo_llms_txt_post_types` · `lean_seo_llms_txt_limit` · `lean_seo_llms_txt`

**Markdown**

| Filter | Purpose |
|--------|---------|
| `lean_seo_markdown_post_types` | Post types served as Markdown (default `post`, `page`) |
| `lean_seo_markdown_frontmatter` | The frontmatter fields |
| `lean_seo_markdown_html` | The HTML converted to Markdown (page builders) |
| `lean_seo_markdown_include_custom_fields` | Opt in to custom fields in the frontmatter (off) |
| `lean_seo_markdown_custom_fields` | The custom fields included |
| `lean_seo_markdown_cache_duration` | Cache lifetime in seconds (default one hour) |
| `lean_seo_markdown_alternate_link` | Print the `rel="alternate"` link to the `.md` URL (default on) |

Custom fields are off by default: post meta not registered with `show_in_rest` is not public, and plugins routinely keep private data in it.

## Examples

```php
// Custom description for a specific page
add_filter( 'lean_seo_description', function ( $desc, $context ) {
    return is_page( 'special' ) ? 'Custom description' : $desc;
}, 10, 2 );

// SEO fields on a custom post type
add_filter( 'lean_seo_meta_box_post_types', function ( $types ) {
    $types[] = 'product';
    return $types;
} );

// Site-specific intro filler to skip when generating descriptions
add_filter( 'lean_seo_description_filler_patterns', function ( $patterns ) {
    $patterns[] = '/welcome to our blog/i';
    return $patterns;
} );

// Extra sitemaps in the index
add_filter( 'lean_seo_sitemap_entries', function ( $entries ) {
    $entries[] = array( 'loc' => home_url( '/custom.xml' ), 'lastmod' => null );
    return $entries;
} );
```

## Abilities API

Registered on `wp_abilities_api_init`:

| Ability | Description | Capability |
|---------|-------------|------------|
| `lean-seo/get-sitemap-urls` | Every sitemap URL the site publishes | `manage_options` |
| `lean-seo/get-post-seo` | SEO data for a post | `edit_post` |
| `lean-seo/update-post-seo` | Update title/description | `edit_post` |
| `lean-seo/audit-post-seo` | Audit one post, 0–100 score | `edit_post` |
| `lean-seo/scan-seo-issues` | Scan many posts of a type, worst first | `manage_options` |

The audit checks title and description length, word count, heading structure, internal/external links, image count and alt coverage — telling missing alt text from the empty alt of decorative images — and, on catalog entries, their Product markup.

Every option is also registered with `show_in_rest`, so administrators can read and update them at `/wp/v2/settings`.

## WP-CLI

```bash
wp lean-seo generate-descriptions [--post-type=post] [--batch-size=50] [--limit=0] [--dry-run]
wp lean-seo import --from=<yoast|rankmath|aioseo> [--post-type=<types>] [--identity] [--overwrite] [--dry-run]
wp lean-seo validate-products [--post-type=<type>] [--all] [--format=<table|csv|json>]
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

The screen's components live in `assets/admin/app.js` and register on `alpine:init`. Every view is server-rendered first and keeps working without scripts; the components start from the state printed into the page (`window.leanSeo`), so nothing is fetched on load. Because Tailwind's utilities are `!important`, use `x-show.important` on elements that also carry a display utility.

REST endpoints for the screen, all administrators-only, under `lean-seo/v1`: `GET /bulletin`, `POST /preferences`, `GET /audit`, `POST /audit/runs`, `POST|DELETE /audit/runs/{token}`, `POST /llms`. Settings are saved through core's `/wp/v2/settings`.

Tailwind scans `includes/admin/` and `assets/admin/app.js` only, so the output holds just the classes they use. Colors are named by role (`bg-paper`, `bg-sheet`, `text-ink-2`, `border-rule`, `text-met`, `bg-level-yellow`…) and resolve to CSS variables that switch between the Day and Night editions. Public Sans is self-hosted from `assets/fonts/` (SIL Open Font License); `pnpm install` also pulls it from npm, should it need updating.

### Dependencies

The HTML-to-Markdown library, `league/html-to-markdown`, ships in `vendor-prefixed/` with its namespace rewritten to `Lean_SEO\Dependencies\` by [Strauss](https://github.com/BrianHenryIE/strauss), so another plugin loading its own copy cannot conflict. `vendor-prefixed/` is committed; installing the plugin needs no Composer. To update the library:

```bash
composer update league/html-to-markdown   # Strauss runs on post-update-cmd
git add vendor-prefixed/ composer.lock
```

## Translations

Ships with Spanish (`es_ES`). To add a language, copy `languages/lean-seo.pot` and compile:

```bash
wp i18n make-mo languages/lean-seo-<locale>.po
```

## Uninstalling

Deleting the plugin removes its options, its post meta and any queued IndexNow events, on every site of a multisite network. Deactivating leaves your data intact.

---

**License:** GPL-2.0+
**Author:** [Sarai Chinwag](https://saraichinwag.com) for [Extra Chill](https://extrachill.com)
