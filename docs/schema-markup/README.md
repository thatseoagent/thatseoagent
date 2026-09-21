# Schema Markup

Lean SEO automatically generates JSON-LD structured data to help search engines better understand your content and potentially display rich snippets.

## What It Does

The `Lean_SEO_Schema` class outputs Schema.org JSON-LD markup including:

- **WebSite**: Site-wide information with search action
- **Organization**: Publisher information with logo
- **Article**: For blog posts with author, dates, images
- **WebPage**: For pages and general content
- **BreadcrumbList**: Navigation breadcrumbs

## Code Structure

Located in `includes/class-lean-seo-schema.php`:

- `output()` - Main method that combines and outputs all schema types
- `get_website_schema()` - Creates WebSite schema with search functionality
- `get_organization_schema()` - Publisher info with logo
- `get_article_schema()` - Article-specific data for posts
- `get_webpage_schema()` - General page information
- `get_breadcrumb_schema()` - Navigation breadcrumbs

## Configuration Options

### Custom Schema Types

You can add custom schema types using WordPress actions:

```php
add_action('wp_head', function() {
    if (is_singular('product')) {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => get_the_title(),
            'description' => get_the_excerpt(),
            // ... other product properties
        );
        echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';
    }
}, 3); // After Lean SEO's schema (priority 2)
```

### Organization Details

The Organization schema automatically uses:
- Site name from `get_bloginfo('name')`
- Site logo from WordPress custom logo setting
- Site URL from `home_url('/')`

## Usage

Schema markup is automatically added to the `<head>` section as JSON-LD script tags.

### Output Format

All schema is combined into a single JSON-LD script with `@graph` structure:

```json
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "WebSite",
      "@id": "https://example.com/#website",
      "url": "https://example.com/",
      "name": "Example Site",
      "description": "Site description",
      "potentialAction": {
        "@type": "SearchAction",
        "target": "https://example.com/?s={search_term_string}",
        "query-input": "required name=search_term_string"
      }
    },
    {
      "@type": "Organization",
      "@id": "https://example.com/#organization",
      "name": "Example Site",
      "url": "https://example.com/",
      "logo": {
        "@type": "ImageObject",
        "url": "https://example.com/wp-content/uploads/logo.png"
      }
    }
  ]
}
```

### When Schema is Generated

- **WebSite & Organization**: On all pages
- **Article**: Only on single post pages
- **WebPage**: Only on single page pages
- **BreadcrumbList**: On single post/page pages

## Technical Details

- Uses `@graph` structure for multiple schema types in one script
- Includes proper `@id` references for schema relationships
- Word count calculation for Article schema
- Automatic image inclusion when post thumbnails exist
- Breadcrumb generation follows site structure

## Benefits

Proper schema markup can enable:
- Rich snippets in search results
- Knowledge panel information
- Enhanced search result appearances
- Better understanding of content by search engines

## Testing

Test your schema markup using:
- [Google's Rich Results Test](https://search.google.com/test/rich-results)
- [Schema.org validator](https://validator.schema.org/)
- Search Console's Rich Results report