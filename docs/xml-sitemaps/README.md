# XML Sitemaps

ThatSeoAgent automatically generates XML sitemaps for better search engine crawling and indexing.

## What It Does

The `ThatSeoAgent_Sitemap` class creates XML sitemaps including:

- **Index sitemap**: Main sitemap listing all sub-sitemaps
- **Posts sitemap**: All published posts with modification dates
- **Pages sitemap**: The homepage, then all published pages (the page set as the static front page is not listed twice)
- **Categories sitemap**: All categories with posts
- **Tags sitemap**: All tags with posts
- **One sitemap per public custom post type**, listed in the index when it has at least one post to list
- **One sitemap per public custom taxonomy** (brands, product categories; since 2.9.0), listed in the index when it has at least one term to list

WordPress core's own sitemaps (`/wp-sitemap.xml`) are turned off with the `wp_sitemaps_enabled` filter, so search engines get one set, not two competing ones.

While another SEO plugin is active (Yoast SEO, Rank Math, All in One SEO…), ThatSeoAgent steps aside: it serves no sitemaps, adds nothing to robots.txt and sends no `Link` header, and leaves them to that plugin.

## Code Structure

Located in `includes/sitemap/class-thatseoagent-sitemap.php`:

- `register_routes()` - Sets up rewrite rules for sitemap URLs
- `handle_request()` - Resolves the request from query vars, prints what `render()` returns with its headers, and exits
- `render( $which, $args )` - Public; returns a sitemap as an XML string (empty for an unknown one). `$which` is `index`, `posts`, `pages`, `categories`, `tags`, `cpt` (with `$args['post_type']`) or `taxonomy` (with `$args['taxonomy']`); `$args['page']` is the 1-based chunk
- `get_sitemap_urls()` - Public; every sitemap the site publishes, in index order, as `loc`/`lastmod` pairs. The index and the `get-sitemap-urls` ability both read it
- `render_index()` - Builds the index from `get_sitemap_urls()`
- `render_post_type()` - One chunk of posts or of a custom post type
- `render_pages()` - One chunk of pages, with the homepage first
- `render_taxonomy()` - The terms of categories, tags or a custom taxonomy

## Configuration Options

### Custom Sitemaps

Add sitemaps of your own to the index with the `thatseoagent_sitemap_entries` filter. It receives the list of sitemaps the index announces, each an array with `loc` (the sitemap's URL) and `lastmod` (a date string, or `null` to leave it out), and returns it:

```php
add_filter( 'thatseoagent_sitemap_entries', function ( $entries ) {
    $entries[] = array(
        'loc'     => home_url( '/my-sitemap.xml' ),
        'lastmod' => null,
    );
    return $entries;
} );
```

The filter only lists the sitemap: serving `/my-sitemap.xml` is up to the code that adds it. The same list reaches the `get-sitemap-urls` ability, so a sitemap added here is reported there too.

The older `thatseoagent_sitemap_index` action, which echoed raw `<sitemap>` elements into the index, is kept for backward compatibility only. New code should use the filter.

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

Terms work the same way: a category, tag or custom taxonomy term marked noindex on its edit screen (or with the `thatseoagent/update-term-seo` ability) is left out of its taxonomy's sitemap, and so are terms with no posts.

## Usage

Sitemaps are automatically available at the following URLs:

- `/sitemap.xml` - Main index sitemap
- `/sitemap_index.xml` - The same index, at the address Yoast SEO used (a rewrite rule, not a redirect)
- `/sitemap-posts.xml` - All posts
- `/sitemap-pages.xml` - The homepage and all pages
- `/sitemap-categories.xml` - All categories
- `/sitemap-tags.xml` - All tags
- `/sitemap-{post_type}.xml` - Each public custom post type, e.g. `/sitemap-machine.xml`
- `/sitemap-tax-{taxonomy}.xml` - Each public custom taxonomy, e.g. `/sitemap-tax-machine_brand.xml`. The `tax-` prefix keeps it apart from a post type of the same name

The ability `thatseoagent/get-sitemap-urls` (also an MCP tool, `thatseoagent-get-sitemap-urls`) returns `/sitemap.xml` and every sitemap the index lists, for administrators (`manage_options`).

### Automatic Pagination

Posts, pages and each custom post type are split into chunks of 1000 URLs:
- URLs follow the pattern `/sitemap-posts-{n}.xml`, `/sitemap-pages-{n}.xml` and `/sitemap-{post_type}-{n}.xml`
- When everything fits in one file the index lists the URL without a number; when it does not, it lists each numbered chunk

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
- The queries that list URLs pass `no_found_rows`, and `update_post_meta_cache` and `update_post_term_cache` set to `false`, so no post meta or terms are loaded for the URLs listed
- Each term's `lastmod` comes from one query per taxonomy, not one per term

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