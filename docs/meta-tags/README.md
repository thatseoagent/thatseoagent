# Meta Tags

ThatSeoAgent automatically generates essential meta tags for SEO, social media sharing, and search engine crawling.

## What It Does

The `ThatSeoAgent_Meta` class handles output of:

- HTML meta description
- Open Graph tags (og:title, og:description, og:image, og:url, og:type, og:site_name)
- Twitter Card tags (twitter:card, twitter:site, twitter:image:alt; title, description and image come from Open Graph, which X reads when its own are absent)
- Canonical URLs

Robots directives and pagination links come from the modules in `includes/indexing/` — see below.

## Code Structure

The meta tags are generated in `includes/head/class-thatseoagent-meta.php`:

- `output()` - Main method that echoes all meta tags
- `get_description()` - The description of the current request: a post's from `ThatSeoAgent_Description::for_post()`, a listing's from its own chain
- `get_image()` - Gets primary image (post thumbnail or site logo)
- `get_url()` - The og:url: the canonical, else the requested URL
- `get_canonical()` - The canonical URL of the page, pagination included
- `get_canonical_base()` - The canonical URL of the first page of a listing

## Configuration Options

### Custom Descriptions

A post's description is the homepage's own on the homepage (ThatSeoAgent → Settings), then its SEO description, then one generated from its content. `ThatSeoAgent_Description::for_post()` gives it, and the head, the markup, the Markdown version, llms.txt and the editor's preview all use it. The `thatseoagent_description` filter has the last word, for a post wherever its description is used, so branch on the post it receives rather than on conditional tags:

```php
add_filter('thatseoagent_description', function($description, $context, $post) {
    if ($post && 'contact' === $post->post_name) {
        return 'Get in touch with us for custom web development services';
    }
    return $description;
}, 10, 3);
```

The title follows the same order — the homepage's own, the SEO title, then the post's name and the site's as WordPress builds it — through `ThatSeoAgent_Title::for_post()` and the `thatseoagent_title` filter, and the `<title>`, og:title and the editor's preview say the same.

### Which image a page is shared with

`ThatSeoAgent_Image` picks it, first found wins:

1. the featured image
2. a catalog entry's first gallery image
3. the first image in the content (galleries included)
4. the default sharing image (ThatSeoAgent → Settings → Identity)
5. the `thatseoagent_default_image` filter
6. the theme logo

Listings start at 4. For sharing, the largest size under 2 MB is used (filter `thatseoagent_og_image_size` to force one), with its width, height, type and alt text. The schema's primary image uses the post's own images only.

### Default Fallback Image

Set a default Open Graph image when a page has no image of its own:

```php
add_filter('thatseoagent_default_image', function($url) {
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

- Generated descriptions are composed to 155 characters, under where results may cut them; written ones have no limit, as in Google
- Titles have no limit either; search results trim them to the width of the screen, so past about 70 characters they may be cut
- Open Graph images should be at least 1200x630 pixels
- Twitter Cards use "summary_large_image" format by default

## Robots meta

`ThatSeoAgent_Indexing` adds its directives through core's `wp_robots` filter (priority 20), so they merge with what WordPress and other plugins set:

| Page | robots |
|------|--------|
| Any indexable page | `max-snippet:-1, max-video-preview:-1, max-image-preview:large` |
| Search results, 404, `?replytocom=` links | `noindex, follow` |
| Private post | `noindex, follow` |
| Post marked **Keep out of search results** | `noindex, follow` |
| Any page while Settings → Reading discourages search engines | `noindex, nofollow` (core's) |

A page with `noindex` prints no canonical, and no `rel="prev"`/`rel="next"`.

```php
// Keep tag archives out of search.
add_filter( 'thatseoagent_noindex', function ( $noindex, $context ) {
    return $noindex || is_tag();
}, 10, 2 );
```

`thatseoagent_noindex` decides the page only; the sitemaps and llms.txt follow each post's field.

## Pagination

Every page of a paginated listing, and of a post split with `<!--nextpage-->`, is its own canonical: page 2 of a category lists posts page 1 does not, and declaring page 1 as its canonical would tell search engines to drop it. `ThatSeoAgent_Pagination` also prints `rel="prev"` and `rel="next"`:

```html
<!-- /category/news/page/2/ -->
<link rel="canonical" href="https://example.com/category/news/page/2/">
<link rel="prev" href="https://example.com/category/news/">
<link rel="next" href="https://example.com/category/news/page/3/">
```

Paginated URLs are built from the canonical, so query strings a visitor arrived with (`?utm_source=`) never reach the canonical or og:url. Turn the links off with `add_filter( 'thatseoagent_adjacent_links', '__return_false' );`.

## Attachment pages

`ThatSeoAgent_Attachment_Redirect` sends every attachment page to its file with a 301, as WordPress does for sites installed since 6.4. Keep the pages with `add_filter( 'thatseoagent_redirect_attachment_pages', '__return_false' );`.
