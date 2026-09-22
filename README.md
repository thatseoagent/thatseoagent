# Lean SEO

Lightweight SEO for WordPress — no bloat, no upsells, just what you need.

## Description

Essential SEO without the weight: meta tags, Open Graph, Twitter Cards, XML sitemaps, Schema/JSON-LD, canonical URLs, per-post SEO fields with live preview, IndexNow submission, and a Markdown version of every post for AI agents.

## Requirements

- WordPress 7.1+
- PHP 7.4+

## Installation

```bash
cp -r lean-seo /path/to/wp-content/plugins/
wp plugin activate lean-seo
```

Zero configuration required — activate and it works. Settings live under **Settings → Lean SEO**.

## Features

- **Meta tags** — title, description, Open Graph, Twitter Cards, article metadata
- **XML sitemaps** — auto-generated and paginated at 1000 URLs per file
- **Schema/JSON-LD** — WebSite, Organization *or* Person, Article, WebPage, BreadcrumbList, FAQPage
- **Canonical URLs** — replaces core's `rel_canonical`
- **Per-post SEO** — title/description meta box with live search preview, exposed to the REST API
- **Site identity** — declare whether the site represents a Person or an Organization
- **Homepage SEO** — custom title/description with `%%sitename%%`, `%%tagline%%`, `%%sep%%`
- **IndexNow** — notify Bing/Yandex on publish (opt-in: no key, no requests)
- **Markdown for AI agents** — any post or page at its URL plus `.md`, with YAML frontmatter
- **WP-CLI** — bulk-generate missing meta descriptions

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

## Privacy

The plugin sends no data anywhere **unless you configure an IndexNow API key**. With a key set, publishing or updating a post queues a background request to `https://api.indexnow.org/indexnow` containing your site host, the key, and the URL of the post. Clearing the key stops all outbound requests. The plugin also serves the `{key}.txt` verification file IndexNow requires.

## Architecture

| Module | Responsibility |
|--------|----------------|
| `Lean_SEO` | Hook registration |
| `Lean_SEO_Meta` | `<head>` meta tags and canonical |
| `Lean_SEO_Schema` | JSON-LD graph |
| `Lean_SEO_FAQ` | Reads content into sections; FAQ extraction |
| `Lean_SEO_Content` | The single answer to "what is this post's content?" |
| `Lean_SEO_Description` | The single answer to "what description does this post get?" |
| `Lean_SEO_Post_Seo` | Per-post SEO field storage, sanitization and slashing |
| `Lean_SEO_Sitemap` | Sitemap rendering (returns XML strings) |
| `Lean_SEO_Identity` | Site identity settings + schema applier |
| `Lean_SEO_Homepage` | Homepage title/description settings + applier |
| `Lean_SEO_IndexNow` | IndexNow key, verification file and submission |
| `Lean_SEO_Markdown` | A post as Markdown with YAML frontmatter |
| `Lean_SEO_Markdown_Endpoint` | The `.md` URLs |
| `Lean_SEO_Markdown_Cache` | Per-post Markdown cache and its invalidation |
| `Lean_SEO_Admin` | Settings page and meta box |
| `Lean_SEO_Abilities` | Abilities API registration |
| `Lean_SEO_CLI` | WP-CLI commands |

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

Both FAQ opt-ins are off by default: they synthesise questions that do not appear on the page, which conflicts with Google's requirement that marked-up content be visible.

**Sitemaps and admin**

`lean_seo_sitemap_entries` (the index listing) · `lean_seo_meta_box_post_types` · action `lean_seo_sitemap_index`

**Markdown**

| Filter | Purpose |
|--------|---------|
| `lean_seo_markdown_post_types` | Post types served as Markdown (default `post`, `page`) |
| `lean_seo_markdown_frontmatter` | The frontmatter fields |
| `lean_seo_markdown_html` | The HTML converted to Markdown (page builders) |
| `lean_seo_markdown_include_custom_fields` | Opt in to custom fields in the frontmatter (off) |
| `lean_seo_markdown_custom_fields` | The custom fields included |
| `lean_seo_markdown_cache_duration` | Cache lifetime in seconds (default one hour) |

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
| `lean-seo/scan-seo-issues` | Scan many posts, worst first | `manage_options` |

The audit checks title and description length, word count, heading structure, internal/external links, image count and alt coverage.

## WP-CLI

```bash
wp lean-seo generate-descriptions [--post-type=post] [--batch-size=50] [--limit=0] [--dry-run]
```

Writes a meta description for published posts that lack one, using the same rule the front end applies.

## Development

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
