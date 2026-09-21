# Lean SEO Documentation

## Overview

Lean SEO is a lightweight WordPress plugin that provides essential SEO functionality without the bloat of popular alternatives like Yoast SEO. It focuses on core SEO features: meta tags, Open Graph, Twitter Cards, XML sitemaps, Schema markup, and per-post SEO fields.

The plugin is designed to be developer-friendly with clean, hookable code and no unnecessary features. It's built for performance and simplicity.

## Features

- **Meta Tags**: Automatic generation of title, description, Open Graph, and Twitter Card meta tags
- **Schema/JSON-LD**: Structured data markup for better search engine understanding
- **XML Sitemaps**: Auto-generated, paginated sitemaps for posts, pages, categories, and tags
- **Canonical URLs**: Proper canonicalization to prevent duplicate content issues
- **Per-Post SEO**: Custom title and description fields with live preview in the admin
- **Developer Hooks**: Extensive filters and actions for customization

## Installation

1. Download the plugin zip file
2. Upload the `lean-seo` folder to your `/wp-content/plugins/` directory
3. Activate the plugin through the WordPress admin 'Plugins' menu
4. No additional configuration is required - the plugin works out of the box

### Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher

### Activation

Upon activation, the plugin automatically:
- Registers rewrite rules for XML sitemaps
- Adds hooks for meta tag output
- Creates the SEO meta box for posts and pages

## Documentation Structure

This documentation is organized by feature area:

- [Meta Tags](meta-tags/) - How meta tags are generated and customized
- [Schema Markup](schema-markup/) - JSON-LD structured data implementation
- [XML Sitemaps](xml-sitemaps/) - Sitemap generation and customization
- [Admin Interface](admin-interface/) - Per-post SEO fields and admin functionality

## Quick Start

After installation, Lean SEO automatically handles:

1. **Meta Tags**: Added to `<head>` for all pages/posts
2. **Sitemaps**: Available at `/sitemap.xml` (submit this to Google Search Console)
3. **Schema**: JSON-LD output for better rich snippets
4. **SEO Fields**: Available in post/page editors for custom titles and descriptions

## Customization

The plugin provides numerous hooks for customization. See the feature-specific documentation for details on available filters and actions.

## Support

- Check the [GitHub repository](https://github.com/Sarai-Chinwag/lean-seo) for issues and updates
- Review the code comments and inline documentation for implementation details

## License

GPL-2.0+ (same as WordPress)