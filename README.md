# Lean SEO

Lightweight SEO for WordPress — no bloat, no upsells, just what you need.

## Description

Essential SEO in ~1,500 lines of PHP: meta tags, Open Graph, Twitter Cards, XML sitemaps, Schema/JSON-LD markup, canonical URLs, and per-post SEO fields with live preview.

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Installation

```bash
cp -r lean-seo /path/to/wp-content/plugins/
wp plugin activate lean-seo
```

Zero configuration required — activate and it works.

## Features

- **Meta Tags** — Title, description, Open Graph, Twitter Cards
- **XML Sitemaps** — Auto-generated, paginated for large sites (1000 posts per page)
- **Schema/JSON-LD** — WebSite, Organization, Article, BreadcrumbList
- **Canonical URLs** — Proper canonicalization
- **Per-Post SEO** — Custom title/description meta box with live preview

## Sitemaps

| URL | Content |
|-----|---------|
| `/sitemap.xml` | Index |
| `/sitemap-posts.xml` | Posts (auto-paginates) |
| `/sitemap-pages.xml` | Pages |
| `/sitemap-categories.xml` | Categories |
| `/sitemap-tags.xml` | Tags |

## Hooks/Filters

```php
// Custom description for special pages
add_filter( 'lean_seo_custom_description', function( $desc ) {
    if ( is_page( 'special' ) ) return 'Custom description';
    return $desc;
} );

// Default OG image fallback
add_filter( 'lean_seo_default_image', function( $url ) {
    return 'https://example.com/default.jpg';
} );

// Add post types to the SEO meta box
add_filter( 'lean_seo_meta_box_post_types', function( $types ) {
    $types[] = 'product';
    return $types;
} );

// Add custom sitemaps to the index
add_action( 'lean_seo_sitemap_index', function() {
    echo '<sitemap><loc>' . home_url( '/custom.xml' ) . '</loc></sitemap>';
} );
```

## Abilities API

Lean SEO registers abilities via the WordPress Abilities API (`wp_abilities_api_init`) for programmatic SEO management:

| Ability | Description | Permission |
|---------|-------------|------------|
| `lean-seo/get-sitemap-urls` | Returns all sitemap URLs | `manage_options` |
| `lean-seo/get-post-seo` | Get SEO data for a specific post | `edit_post` |
| `lean-seo/update-post-seo` | Update title/description for a post | `edit_post` |
| `lean-seo/audit-post-seo` | Analyze a post for SEO issues with scoring | `edit_post` |
| `lean-seo/scan-seo-issues` | Scan multiple posts for issues, sorted by score | `manage_options` |

The audit checks title length, description length, word count, heading structure, internal/external links, image count, and alt text coverage — returning a 0–100 score with actionable issue details.

## Known Issues

**Version constant mismatch:** The plugin header declares version `1.0.1` but `LEAN_SEO_VERSION` is defined as `1.0.0`. This is cosmetic and does not affect functionality.

## FAQ

**Why not Yoast?**
Lean SEO does the same essential work in ~1,500 lines vs. megabytes. No configuration wizard, no upsells, no admin bloat.

**Does it work with custom post types?**
Yes. Use the `lean_seo_meta_box_post_types` filter to add SEO fields to any post type.

---

**License:** GPL-2.0+
**Author:** [Sarai Chinwag](https://saraichinwag.com) for [Extra Chill](https://extrachill.com)
