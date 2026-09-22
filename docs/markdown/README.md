# Markdown for AI Agents

Lean SEO serves every published post and page as Markdown at its URL plus `.md`.

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
featured_image: "https://example.com/wp-content/uploads/image.jpg"
featured_image_alt: "Alt text"
---

The post's content, as Markdown…
```

## Code Structure

| Class | File | Responsibility |
|-------|------|----------------|
| `Lean_SEO_Markdown_Endpoint` | `includes/class-lean-seo-markdown-endpoint.php` | Rewrite rule, request handling, headers |
| `Lean_SEO_Markdown` | `includes/class-lean-seo-markdown.php` | Frontmatter and HTML → Markdown |
| `Lean_SEO_Markdown_Cache` | `includes/class-lean-seo-markdown-cache.php` | Per-post cache and its invalidation |

The content comes from `Lean_SEO_Content::html()` and the description from `Lean_SEO_Description::for_post()`, so the Markdown says what the page and its meta tags say. The conversion uses `league/html-to-markdown`, bundled in `vendor-prefixed/` under the `Lean_SEO\Dependencies\` namespace.

## How a Request Is Answered

1. The rewrite rule `(.+)\.md$` maps the request to the `lean_markdown` query var.
2. On `parse_request` — before the main query and `redirect_canonical()` — the path is resolved with `url_to_postid()`. That goes through the site's own rewrite rules, so any permalink structure works: `/%postname%/`, dates, categories, hierarchical pages, custom post types.
3. The post must be of an enabled post type, readable by the visitor (published, or `read_post` for anything else) and not password-protected.
4. `If-Modified-Since` is answered with `304 Not Modified`.
5. The Markdown is served from the cache, or built and cached.

Errors are plain text: `404` when the path matches no enabled post, `403` when the visitor may not read it.

The posts page (`page_for_posts`) has no content of its own and returns `404`. Plain permalinks (`?p=123`) have no rewrite rules, so `.md` URLs need pretty permalinks.

## Search Engines

The Markdown is a copy of the HTML page. Every response sends `X-Robots-Tag: noindex` and a `Link: <permalink>; rel="canonical"` header, so search engines keep indexing the HTML.

## Caching

One transient per post, `lean_seo_md_{version}_{post_id}`, stored for an hour. It uses the object cache when one is installed.

A cached entry is dropped when:

- the post is saved or deleted,
- its categories or tags change,
- its meta changes — the featured image and the SEO description both live in post meta (`_edit_lock` and other editor bookkeeping are ignored),
- and the entry records the `post_modified_gmt` it was built from, so a post changed some other way is rebuilt anyway.

Renaming a term or updating a user's profile purges every entry, since term and author names appear in many posts' frontmatter. Purging bumps `lean_seo_markdown_cache_version`; the old transients become unreachable and expire on their own.

Purge by hand:

```bash
wp eval 'Lean_SEO_Markdown_Cache::purge_all();'
```

## Filters

### Post types

```php
add_filter( 'lean_seo_markdown_post_types', function ( $post_types ) {
    $post_types[] = 'product';
    return $post_types;
} );
```

### Frontmatter

```php
add_filter( 'lean_seo_markdown_frontmatter', function ( $fields, $post ) {
    $fields['reading_time'] = ceil( Lean_SEO_Content::word_count( $post ) / 200 );
    unset( $fields['author'] );
    return $fields;
}, 10, 2 );
```

Scalars become `key: "value"`, lists become YAML sequences and associative arrays one level of nested mapping. Keys with anything but letters, digits, `_` and `-` are quoted.

### Custom fields

Off by default. Post meta not registered with `show_in_rest` is not public, and plugins routinely store emails, IDs and tokens in keys without a leading underscore. Turn them on and keep an allowlist:

```php
add_filter( 'lean_seo_markdown_include_custom_fields', '__return_true' );

add_filter( 'lean_seo_markdown_custom_fields', function ( $fields, $post_id ) {
    return array_intersect_key( $fields, array_flip( array( 'price', 'sku' ) ) );
}, 10, 2 );
```

Protected meta and serialized values are always skipped.

### Page builders

Builders that keep their layout outside `post_content` produce empty Markdown until they supply their rendered HTML:

```php
add_filter( 'lean_seo_markdown_html', function ( $html, $post ) {
    $document = \Elementor\Plugin::$instance->documents->get( $post->ID );
    if ( $document && $document->is_built_with_elementor() ) {
        return \Elementor\Plugin::$instance->frontend->get_builder_content( $post->ID );
    }
    return $html;
}, 10, 2 );
```

### Cache lifetime

```php
add_filter( 'lean_seo_markdown_cache_duration', function () {
    return DAY_IN_SECONDS;
} );
```
