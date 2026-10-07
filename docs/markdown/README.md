# Markdown for AI Agents

ThatSeoAgent serves every published post and page as Markdown at its URL plus `.md`, and at its own URL to any client that asks for Markdown in `Accept`.

## What It Does

An AI agent that fetches a post as HTML pays for the navigation, the sidebar, the footer, the scripts and the styles. The content is often under 5% of the bytes. The Markdown version is only the content, with the post's metadata in a YAML frontmatter:

```
GET /my-post.md

HTTP/1.1 200 OK
Content-Type: text/markdown; charset=utf-8
X-Robots-Tag: noindex
Link: <https://example.com/my-post>; rel="canonical"
Last-Modified: Fri, 13 Feb 2026 10:00:00 GMT

---
title: "My Post"
date: "2026-02-13T10:00:00+00:00"
modified: "2026-02-13T15:45:00+00:00"
author: "Jane Doe"
permalink: "https://example.com/my-post"
type: "post"
description: "The same description the page's meta tags carry."
categories:
  - "News"
tags:
  - "wordpress"
image: "https://example.com/wp-content/uploads/image.jpg"
image_alt: "Alt text"
---

The post's content, as Markdown…
```

## Code Structure

| Class | File | Responsibility |
|-------|------|----------------|
| `ThatSeoAgent_Markdown_Endpoint` | `includes/markdown/class-thatseoagent-markdown-endpoint.php` | Rewrite rule, request handling, headers |
| `ThatSeoAgent_Markdown` | `includes/markdown/class-thatseoagent-markdown.php` | Frontmatter and HTML → Markdown |
| `ThatSeoAgent_Markdown_Cache` | `includes/markdown/class-thatseoagent-markdown-cache.php` | Per-post cache and its invalidation |

The content comes from `ThatSeoAgent_Content::html()` and the description from `ThatSeoAgent_Description::for_post()`, so the Markdown says what the page and its meta tags say. The conversion uses `league/html-to-markdown`, bundled in `vendor-prefixed/` under the `ThatSeoAgent\Dependencies\` namespace.

## How a Request Is Answered

1. The rewrite rule `(.+)\.md$` maps the request to the `thatseoagent_markdown` query var.
2. On `parse_request` — before the main query and `redirect_canonical()` — the path is resolved with `url_to_postid()`. That goes through the site's own rewrite rules, so any permalink structure works: `/%postname%/`, dates, categories, hierarchical pages, custom post types.
3. The post must be of an enabled post type, readable by the visitor (published, or `read_post` for anything else) and not password-protected.
4. `If-Modified-Since` is answered with `304 Not Modified`.
5. The Markdown is served from the cache, or built and cached.

Errors are plain text: `404` when the path matches no enabled post, `403` when the visitor may not read it.

The posts page (`page_for_posts`) has no content of its own and returns `404`. Plain permalinks (`?p=123`) have no rewrite rules, so `.md` URLs need pretty permalinks.

## Announcing it

The HTML page of every post with a Markdown version says so twice, once for clients that parse HTML and once for those that read only headers:

```html
<link rel="alternate" type="text/markdown" href="https://example.com/my-post.md">
```

```
Link: <https://example.com/my-post.md>; rel="alternate"; type="text/markdown"
```

Both follow the `thatseoagent_markdown_alternate_link` filter: return false and neither is printed.

## Content negotiation

An agent that only knows the page's URL can ask for Markdown the HTTP way:

```
GET /my-post
Accept: text/markdown

HTTP/1.1 200 OK
Content-Type: text/markdown; charset=utf-8
Vary: Accept
Cache-Control: private, no-cache
Content-Location: https://example.com/my-post.md
Link: <https://example.com/my-post>; rel="canonical"
```

The body is the same Markdown the `.md` URL serves, from the same cache, and `If-Modified-Since` is answered with `304` too.

- **Who gets Markdown.** A client whose `Accept` lists `text/markdown` (or `text/x-markdown`) with a weight at least that of `text/html`. Browsers never list it, and a wildcard (`*/*`) alone never selects it, so people and generic clients keep getting the HTML.
- **`Vary: Accept`** goes on both responses of every post with a Markdown version, so a cache that honors it keeps the two apart.
- **Page caches** usually key on the URL alone and ignore `Vary`. The Markdown response defines `DONOTCACHEPAGE`, which WP Super Cache, W3 Total Cache, WP Rocket and most others honor, and sends `Cache-Control: private` so no shared cache stores it. A cache that answers before WordPress runs still serves its stored HTML to an agent asking for Markdown — see below.
- **No `noindex`.** Unlike the `.md` URL, this is the page's own URL, which search engines index as HTML; `Content-Location` names the `.md` URL of this representation. A post kept out of search is the exception: its Markdown says `noindex` here too, as its HTML does.
- It runs on `template_redirect` after `redirect_canonical()`, so a non-canonical URL is redirected first. Password-protected posts are never negotiated.

### Caches that answer before WordPress

Whatever answers before WordPress — a page cache plugin serving from `.htaccess`, a CDN, a server cache — never lets the negotiation run. Two tools find and fix that.

**Check now**, under **ThatSeoAgent → AI index → Markdown for agents**, asks one of your posts from outside, first with `Accept: text/markdown` and then as a browser, and reports what each got (`POST /thatseoagent/v1/markdown/check`). The order matters: a cache that stored the agent's answer would hand it to the browser next. When the check fails, the response headers usually say which layer answered (Cloudflare, LiteSpeed, Varnish, an Nginx cache, a page cache plugin's signature), and the check says what to change there.

It also runs once a week on its own, by WP-Cron (`thatseoagent_weekly_checks`, with the crawler access check). Each run is kept with the one before it (`ThatSeoAgent_Checks`), so the screen opens on the last result and says what changed. From a result at most 8 days old the bulletin warns when a cache hands the Markdown version to browsers (red) and when agents asking for Markdown get the HTML page (yellow). A check that got no answer is no warning.

**The .htaccess rule**, on Apache, lives under **ThatSeoAgent → Settings → Caches and CDN**. It is written when you click **Add the rule to .htaccess**, never on its own (`POST /thatseoagent/v1/markdown/htaccess`; `DELETE` on the same route, **Remove the rule**, takes it out). These routes and the check's need `manage_options`:

```apache
# BEGIN ThatSeoAgent Markdown
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond %{HTTP:Accept} text/(x-)?markdown [NC]
RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$
RewriteRule ^$ /index.php [END]
RewriteCond %{HTTP:Accept} text/(x-)?markdown [NC]
RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ /index.php [END]
</IfModule>
# END ThatSeoAgent Markdown
```

It sends every request asking for Markdown straight to WordPress, and `[END]` stops any later rule from rewriting it to a cached file. It does not rewrite to the `.md` URL, so a URL with no Markdown version still gets its HTML. Browsers, which never ask for Markdown, keep getting the cached pages. It needs Apache 2.4.

Rewrite rules run top to bottom, so the block goes at the very top of the file, above the page cache plugin's. A cache plugin that later writes its own block above it (WP Rocket does, whenever its settings are saved) is reported on the screen: **Write it again at the top** moves the block back. Without a page cache the rule changes nothing. Deleting the plugin removes the block.

Cloudflare does not cache HTML unless a rule tells it to ("Cache Everything"). If one does, add a Cache Rule that bypasses the cache for requests whose `Accept` header contains `text/markdown`, then run the check again to confirm it took.

Turn negotiation off, keeping the `.md` URLs:

```php
add_filter( 'thatseoagent_markdown_negotiation', '__return_false' );
```

## Search Engines

The Markdown is a copy of the HTML page. Every response sends `X-Robots-Tag: noindex` and a `Link: <permalink>; rel="canonical"` header, so search engines keep indexing the HTML. A post kept out of search keeps its Markdown version — it stays public to anyone with the link — but gets no canonical, as its HTML prints none, and is not in llms.txt.

## Caching

One transient per post, `thatseoagent_md_{plugin version}_{cache version}_{post_id}`, stored for an hour. The plugin's version in the key means an update never serves a copy built by the previous one. It uses the object cache when one is installed.

A cached entry is dropped when:

- the post is saved or deleted,
- its categories or tags change,
- its meta changes — the featured image and the SEO description both live in post meta (`_edit_lock` and other editor bookkeeping are ignored),
- and the entry records the `post_modified_gmt` it was built from, so a post changed some other way is rebuilt anyway.

Renaming a term or updating a user's profile purges every entry, since term and author names appear in many posts' frontmatter. Purging bumps `thatseoagent_markdown_cache_version`; the old transients become unreachable and expire on their own.

Purge by hand:

```bash
wp eval 'ThatSeoAgent_Markdown_Cache::purge_all();'
```

## Filters

### Post types

```php
add_filter( 'thatseoagent_markdown_post_types', function ( $post_types ) {
    $post_types[] = 'product';
    return $post_types;
} );
```

### Frontmatter

```php
add_filter( 'thatseoagent_markdown_frontmatter', function ( $fields, $post ) {
    $fields['reading_time'] = ceil( ThatSeoAgent_Content::word_count( $post ) / 200 );
    unset( $fields['author'] );
    return $fields;
}, 10, 2 );
```

Scalars become `key: "value"`, lists become YAML sequences and associative arrays one level of nested mapping. Keys with anything but letters, digits, `_` and `-` are quoted.

### Custom fields

Off by default. Post meta not registered with `show_in_rest` is not public, and plugins routinely store emails, IDs and tokens in keys without a leading underscore. Turn them on and keep an allowlist:

```php
add_filter( 'thatseoagent_markdown_include_custom_fields', '__return_true' );

add_filter( 'thatseoagent_markdown_custom_fields', function ( $fields, $post_id ) {
    return array_intersect_key( $fields, array_flip( array( 'price', 'sku' ) ) );
}, 10, 2 );
```

Protected meta and serialized values are always skipped.

### Page builders

Builders that keep their layout outside `post_content` produce empty Markdown until they supply their rendered HTML:

```php
add_filter( 'thatseoagent_markdown_html', function ( $html, $post ) {
    $document = \Elementor\Plugin::$instance->documents->get( $post->ID );
    if ( $document && $document->is_built_with_elementor() ) {
        return \Elementor\Plugin::$instance->frontend->get_builder_content( $post->ID );
    }
    return $html;
}, 10, 2 );
```

### Cache lifetime

```php
add_filter( 'thatseoagent_markdown_cache_duration', function () {
    return DAY_IN_SECONDS;
} );
```
