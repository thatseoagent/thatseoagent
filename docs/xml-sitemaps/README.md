# XML Sitemaps

Lean SEO automatically generates XML sitemaps for better search engine crawling and indexing.

## What It Does

The `Lean_SEO_Sitemap` class creates XML sitemaps including:

- **Index sitemap**: Main sitemap listing all sub-sitemaps
- **Posts sitemap**: All published posts with modification dates
- **Pages sitemap**: All published pages
- **Categories sitemap**: All categories
- **Tags sitemap**: All tags

## Code Structure

Located in `includes/class-lean-seo-sitemap.php`:

- `register_routes()` - Sets up rewrite rules for sitemap URLs
- `handle_request()` - Processes sitemap requests and outputs XML
- `render_index()` - Creates the main sitemap index
- `render_posts()` - Generates posts sitemap (paginated for large sites)
- `render_pages()` - Creates pages sitemap
- `render_categories()` - Builds categories sitemap
- `render_tags()` - Creates tags sitemap

## Configuration Options

### Custom Sitemaps

Add custom sitemaps to the index using the `lean_seo_sitemap_index` action:

```php
add_action('lean_seo_sitemap_index', function() {
    echo '  <sitemap>' . "\n";
    echo '    <loc>' . home_url('/sitemap-products.xml') . '</loc>' . "\n";
    echo '    <lastmod>' . date('c') . '</lastmod>' . "\n";
    echo '  </sitemap>' . "\n";
});
```

### Exclude Content from Sitemaps

Filter posts/pages from sitemaps:

```php
add_filter('wp_sitemap_posts_query_args', function($args) {
    // Exclude posts from category ID 5
    $args['tax_query'] = array(
        array(
            'taxonomy' => 'category',
            'field'    => 'term_id',
            'terms'    => 5,
            'operator' => 'NOT IN',
        ),
    );
    return $args;
});
```

## Usage

Sitemaps are automatically available at the following URLs:

- `/sitemap.xml` - Main index sitemap
- `/sitemap-posts.xml` - All posts
- `/sitemap-posts-2.xml` - Second page of posts (for sites with >1000 posts)
- `/sitemap-pages.xml` - All pages
- `/sitemap-categories.xml` - All categories
- `/sitemap-tags.xml` - All tags

### Automatic Pagination

For sites with many posts, the posts sitemap is automatically paginated:
- 1000 posts per sitemap page
- URLs follow pattern: `/sitemap-posts-{page}.xml`

### robots.txt Integration

The plugin automatically adds the main sitemap URL to `robots.txt`:

```
Sitemap: https://example.com/sitemap.xml
```

## Technical Details

- Uses WordPress rewrite API for clean URLs
- Outputs proper XML headers and content-type
- Includes last modification dates for posts/pages
- Excludes posts with 'noindex' meta robots (if set by other plugins)
- Performance optimized with `no_found_rows` and cache disabling

## Sitemap Structure

### Index Sitemap Example
```xml
<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <sitemap>
    <loc>https://example.com/sitemap-posts.xml</loc>
    <lastmod>2024-01-15T10:00:00+00:00</lastmod>
  </sitemap>
  <sitemap>
    <loc>https://example.com/sitemap-pages.xml</loc>
  </sitemap>
  <!-- Additional sitemaps -->
</sitemapindex>
```

### Posts Sitemap Example
```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://example.com/hello-world/</loc>
    <lastmod>2024-01-15T10:00:00+00:00</lastmod>
  </url>
  <!-- Additional URLs -->
</urlset>
```

## Submission to Search Engines

Submit your sitemap to:
- [Google Search Console](https://search.google.com/search-console)
- [Bing Webmaster Tools](https://www.bing.com/webmasters)
- Other search engines that support sitemaps

The sitemap helps search engines discover and index your content more efficiently.