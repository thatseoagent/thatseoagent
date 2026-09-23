# XML Sitemaps

ThatSeoAgent automatically generates XML sitemaps for better search engine crawling and indexing.

## What It Does

The `ThatSeoAgent_Sitemap` class creates XML sitemaps including:

- **Index sitemap**: Main sitemap listing all sub-sitemaps
- **Posts sitemap**: All published posts with modification dates
- **Pages sitemap**: All published pages
- **Categories sitemap**: All categories
- **Tags sitemap**: All tags

## Code Structure

Located in `includes/sitemap/class-thatseoagent-sitemap.php`:

- `register_routes()` - Sets up rewrite rules for sitemap URLs
- `handle_request()` - Processes sitemap requests and outputs XML
- `render_index()` - Creates the main sitemap index
- `render_posts()` - Generates posts sitemap (paginated for large sites)
- `render_pages()` - Creates pages sitemap
- `render_categories()` - Builds categories sitemap
- `render_tags()` - Creates tags sitemap

## Configuration Options

### Custom Sitemaps

Add custom sitemaps to the index using the `thatseoagent_sitemap_index` action:

```php
add_action('thatseoagent_sitemap_index', function() {
    echo '  <sitemap>' . "\n";
    echo '    <loc>' . home_url('/sitemap-products.xml') . '</loc>' . "\n";
    echo '    <lastmod>' . date('c') . '</lastmod>' . "\n";
    echo '  </sitemap>' . "\n";
});
```

### Exclude Content from Sitemaps

A sitemap lists what search engines should index, so it leaves out:

- posts marked **Keep out of search results** in the SEO meta box (`_thatseoagent_noindex`), which also get `noindex`;
- password-protected posts, whose content nobody can read;
- private and draft posts, which are not published;
- attachment pages, which redirect to their file.

To keep a post out of the sitemap, mark it in the meta box, with the `thatseoagent/update-post-seo` ability (`"noindex": true`), or in code:

```php
ThatSeoAgent_Post_Seo::save( $post_id, array( 'noindex' => true ) );
```

The same posts are left out of llms.txt.

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

### Link header

Every page announces the sitemap in its response headers, for clients that read headers rather than robots.txt:

```
Link: <https://example.com/sitemap.xml>; rel="sitemap"; type="application/xml"
```

`sitemap` is not a registered link relation, so search engines keep finding the sitemap through robots.txt; the header is for agents that look for it there. Not sent on feeds, robots.txt or the sitemaps themselves, nor while another SEO plugin is active. Turn it off with `add_filter( 'thatseoagent_sitemap_link_header', '__return_false' );`.

### robots.txt Integration

The plugin automatically adds the main sitemap URL to `robots.txt`:

```
Sitemap: https://example.com/sitemap.xml
```

It keeps every other line other plugins or the theme add, so rules of your own go through WordPress's own filter:

```php
add_filter( 'robots_txt', function ( $output, $public ) {
    return $output . "\nUser-agent: *\nDisallow: /private-area/\n";
}, 10, 2 );
```

Search results are not blocked here on purpose: they carry `noindex`, and a crawler kept out by robots.txt never reads it.

## Technical Details

- Uses WordPress rewrite API for clean URLs
- Outputs proper XML headers and content-type
- Includes last modification dates for posts/pages
- Excludes posts kept out of search and password-protected posts; the index only announces chunks that have URLs
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