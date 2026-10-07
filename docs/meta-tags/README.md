# Meta Tags

ThatSeoAgent prints the meta description, Open Graph and Twitter Card tags and the canonical URL in `<head>`, sets the document `<title>`, and adds its robots directives through core's `wp_robots`.

While another SEO plugin is active (see `ThatSeoAgent_Compat` and the root [README](../../README.md)), none of this is registered: no title, meta tags, canonical, robots directives, pagination links or verification tags. Two things stay: the analytics and tag code from **Settings → Analytics and tags** (`ThatSeoAgent_Tracking`), which other SEO plugins do not print, and the Markdown `rel="alternate"` link, as no other plugin serves those URLs.

## What is printed

`ThatSeoAgent_Meta` (`includes/head/class-thatseoagent-meta.php`), on `wp_head` at priority 1:

| Tag | When |
|-----|------|
| `<meta name="description">` | When there is a description |
| `og:locale` | Always (`get_locale()`, e.g. `es_ES`) |
| `og:type` | Always: `website`, `article`, `product` or `profile` (see below) |
| `og:title` | Always |
| `og:description` | When there is a description |
| `og:url` | Always: the canonical, else the requested URL |
| `og:site_name` | Always |
| `og:image` | When the page has a sharing image |
| `og:image:width`, `og:image:height` | With the image, when its size is known |
| `og:image:type` | With the image, when its MIME type is known |
| `og:image:alt` | With the image, when it has alt text |
| `article:published_time` | Dated articles (below) |
| `article:modified_time` | Dated articles, only when modified after publication |
| `article:section` | Dated articles with a primary category other than the default one |
| `og:pin:media` | Dated articles with an image of their own, for Pinterest rich pins: the post's own image at the schema's size, never the site's |
| `og:pin:description` | Dated articles with a description |
| `twitter:card` | Always `summary_large_image`; there is no filter for it |
| `twitter:site` | When an X handle is set (**Settings → Who the site is**), with the `@` added if missing |
| `twitter:title`, `twitter:description`, `twitter:image` | Only with `thatseoagent_twitter_repeat_open_graph`: X reads the Open Graph tags when its own are absent |
| `twitter:image:alt` | With the image, when it has alt text: X has no fallback for it |
| `<link rel="canonical">` | Every page with a canonical, except one with `noindex` |

`og:type` is `website` on the homepage and the posts page, `product` on a catalog entry or a WooCommerce product, `article` on any other single page, `profile` on an author archive, and `website` elsewhere.

A dated article is a single post of a non-hierarchical type that is not a product and not the front page: posts, and custom post types like them. Pages and products get no `article:*` or `og:pin:*` tags, as their publication date tells a reader nothing.

## Code structure

- `output()` prints the meta tags; `output_canonical()` the canonical. Core's `rel_canonical` is removed.
- `get_title()`: the `<title>`'s, through `ThatSeoAgent_Title`.
- `get_description()`: a post's from `ThatSeoAgent_Description::for_post()`, a listing's from its own chain (below).
- `get_image()`: the URL of `ThatSeoAgent_Image::for_request()`.
- `get_og_type()`, `is_dated_article()`, `get_context()`.
- `get_url()`, `get_canonical()`, `get_canonical_base()`: og:url, the canonical with pagination, and the first page of a listing.

## Titles

`ThatSeoAgent_Title` (`includes/head/class-thatseoagent-title.php`) hooks `pre_get_document_title`, `document_title` and `document_title_separator`.

A post's title, from `ThatSeoAgent_Title::for_post()`, first found wins:

1. the `thatseoagent_document_title` override;
2. on the homepage, its own title (**Settings → Homepage**, with `%%sitename%%`, `%%tagline%%`, `%%sep%%`);
3. the post's SEO title, as the full title: the site name is not added;
4. the title WordPress builds: the post's name and the site's, or on the homepage the site's name and tagline, through core's `document_title_parts` and `document_title` filters.

`thatseoagent_title` has the last word. The `<title>`, og:title and the editor's preview say the same.

On listings WordPress builds the title, unless `thatseoagent_document_title` overrides it, the homepage has its own title (a homepage that lists the latest posts), or a term archive has an SEO title. `thatseoagent_title` runs on the result.

The separator is `|`, filterable with `thatseoagent_title_separator`.

## Descriptions

A post's description, from `ThatSeoAgent_Description::for_post()`, first found wins:

1. on the homepage, its own description (**Settings → Homepage**);
2. the post's SEO description;
3. one generated from the post (not on the posts page, whose content is never shown): its excerpt, else the first substantive sentences of its content, skipping intro filler and sentences under 40 characters, composed of whole sentences up to 155 characters (`ThatSeoAgent_Description::MAX_LENGTH`); a single longer sentence is cut at a word boundary;
4. on the homepage and the posts page, the site's tagline.

`thatseoagent_description` has the last word, for a post wherever its description is used: the head, the markup, the Markdown version, llms.txt and the editor's preview. Branch on the post it receives rather than on conditional tags:

```php
add_filter( 'thatseoagent_description', function ( $description, $context, $post ) {
    if ( $post && 'contact' === $post->post_name ) {
        return 'Get in touch with us for custom web development services';
    }
    return $description;
}, 10, 3 );
```

Listings, before the same filter (with a `null` post):

| Listing | Description |
|---------|-------------|
| Homepage listing the latest posts | Its own description, else the tagline |
| Post type archive | The post type's description, else "Browse all {type} on {site}" |
| Category, tag or custom taxonomy archive | The term's SEO description, else the term's description, else "Browse everything in {term} on {site}." |
| Search results | "Search results for "{query}" on {site}" |
| Author archive | "Posts by {author} on {site}" |
| Anything else | The tagline |

Generated descriptions are composed to 155 characters, under where results may cut them; written ones have no limit, as in Google. Titles have no limit either: search results trim them to the width of the screen, so past about 70 characters they may be cut.

## Term archives

`ThatSeoAgent_Term_Seo` adds a **Search engines** group to the add and edit screens of every public taxonomy with an archive (post formats excluded; `thatseoagent_term_seo_taxonomies`): **SEO title**, **Meta description** and **Keep this page out of search results and the sitemap**. They are stored in term meta under the same keys as a post's (`_thatseoagent_title`, `_thatseoagent_description`, `_thatseoagent_noindex`).

The SEO title replaces the archive's whole `<title>`; the site name is not added. The description follows the chain in the table above.

## Which image a page is shared with

`ThatSeoAgent_Image::for_request()` picks it, first found wins:

1. the **Social sharing image** chosen in the post's SEO meta box;
2. the default sharing image (**Settings → Who the site is**);
3. the `thatseoagent_default_image` filter (a URL);
4. the featured image;
5. the theme's custom logo.

Listings skip 1 and 4. Gallery and content images are never shared. Only image files with an absolute URL count. For sharing, the largest of `full`, `large` and `medium_large` that weighs 2 MB or less is used (filter `thatseoagent_og_image_size` to force one), with its width, height, type and alt text. The schema's primary image and `og:pin:media` use the post's own images only.

```php
add_filter( 'thatseoagent_default_image', function ( $url ) {
    return 'https://example.com/images/default-og-image.jpg';
} );
```

Open Graph images should be at least 1200 × 630 pixels.

## Filters

| Filter | Arguments | Purpose |
|--------|-----------|---------|
| `thatseoagent_document_title` | `string $title`, `string $context`, `?WP_Post $post` | Return a non-empty string to replace the title entirely |
| `thatseoagent_title` | `string $title`, `string $context`, `?WP_Post $post` | The resolved title, in the `<title>` and og:title |
| `thatseoagent_title_separator` | `string $separator` | Title separator (default `\|`) |
| `thatseoagent_description` | `string $description`, `string $context`, `?WP_Post $post` | The resolved description |
| `thatseoagent_description_filler_patterns` | `array $patterns`, `string $sentence` | Intro-filler regexes skipped when generating |
| `thatseoagent_og_title` | `string $og_title`, `string $context` | og:title only (default: the title) |
| `thatseoagent_og_site_name` | `string $site_name` | og:site_name (default: the site's name) |
| `thatseoagent_og_locale` | `string $locale` | og:locale (default: `get_locale()`) |
| `thatseoagent_og_type` | `string $type`, `string $context` | og:type |
| `thatseoagent_twitter_handle` | `string $handle` | twitter:site (default: the identity's X handle); `''` omits it |
| `thatseoagent_twitter_repeat_open_graph` | `bool $repeat` | Also print twitter:title, twitter:description and twitter:image (default `false`) |
| `thatseoagent_default_image` | `string $url` | Sharing image when a page has none chosen and no default is set |
| `thatseoagent_og_image_size` | `string $size`, `int $attachment_id` | Force the image size used for sharing |
| `thatseoagent_noindex` | `bool $noindex`, `string $context` | Whether the current page gets `noindex` |
| `thatseoagent_adjacent_links` | `bool` | Print `rel="prev"`/`rel="next"` |
| `thatseoagent_redirect_attachment_pages` | `bool` | Redirect attachment pages to their file |

`$context` is one of `home`, `single`, `archive`, `taxonomy`, `search`, `author`, `date`, `404`, `other`.

## Robots meta

`ThatSeoAgent_Indexing` adds its directives through core's `wp_robots` filter (priority 20), so they merge with what WordPress and other plugins set:

| Page | robots |
|------|--------|
| Any indexable page | `max-snippet:-1, max-video-preview:-1, max-image-preview:large` |
| Search results, 404, `?replytocom=` links | `noindex, follow` |
| Private post | `noindex, follow` |
| Post marked **Keep out of search results** | `noindex, follow` |
| Term archive marked **Keep this page out of search results and the sitemap** | `noindex, follow` |
| Any page while Settings → Reading discourages search engines | `noindex, nofollow` (core's) |

`follow` is only added when nothing else set `nofollow`. A page with `noindex` prints no canonical, and no `rel="prev"`/`rel="next"`.

```php
// Keep tag archives out of search.
add_filter( 'thatseoagent_noindex', function ( $noindex, $context ) {
    return $noindex || is_tag();
}, 10, 2 );
```

`thatseoagent_noindex` decides the page only: the sitemaps follow each post's and term's own field, and llms.txt each post's.

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

## Editing

Posts get their title, description, noindex, sharing image and primary category in the **SEO** meta box; see [Admin interface](../admin-interface/).
