# Meta Tags

Lean SEO automatically generates essential meta tags for SEO, social media sharing, and search engine crawling.

## What It Does

The `Lean_SEO_Meta` class handles output of:

- HTML meta description
- Open Graph tags (og:title, og:description, og:image, og:url, og:type, og:site_name)
- Twitter Card tags (twitter:card, twitter:title, twitter:description, twitter:image)
- Canonical URLs

## Code Structure

The meta tags are generated in `includes/class-lean-seo-meta.php`:

- `output()` - Main method that echoes all meta tags
- `get_description()` - Generates meta description from custom fields, excerpt, or content
- `get_image()` - Gets primary image (post thumbnail or site logo)
- `get_url()` - Gets current page URL
- `get_canonical()` - Generates canonical URL for the page

## Configuration Options

### Custom Descriptions

You can provide custom descriptions for specific pages using the `lean_seo_custom_description` filter:

```php
add_filter('lean_seo_custom_description', function($description) {
    if (is_page('contact')) {
        return 'Get in touch with us for custom web development services';
    }
    return $description;
});
```

### Default Fallback Image

Set a default Open Graph image when no post thumbnail is available:

```php
add_filter('lean_seo_default_image', function($url) {
    return 'https://example.com/images/default-og-image.jpg';
});
```

### Description Sources

Descriptions are automatically generated from:

1. Custom SEO description field (highest priority)
2. Post/page excerpt
3. Auto-generated from content (first 30 words)

## Usage

Meta tags are automatically added to the `<head>` section of all pages. No manual configuration is needed.

For posts and pages, you can customize the SEO title and description using the "SEO Settings" meta box in the editor.

### Examples

**Homepage meta tags:**
```html
<meta name="description" content="Welcome to our blog about web development...">
<meta property="og:title" content="My Blog | Latest Web Development Tips">
<meta property="og:description" content="Welcome to our blog about web development...">
<meta property="og:image" content="https://example.com/wp-content/uploads/logo.jpg">
```

**Post meta tags:**
```html
<meta name="description" content="Learn how to optimize your WordPress site for better SEO...">
<meta property="og:type" content="article">
<meta property="article:published_time" content="2024-01-15T10:00:00+00:00">
<meta property="article:modified_time" content="2024-01-20T15:30:00+00:00">
```

## Technical Details

- Meta descriptions are limited to 160 characters
- SEO titles should be 50-60 characters
- Open Graph images should be at least 1200x630 pixels
- Twitter Cards use "summary_large_image" format by default