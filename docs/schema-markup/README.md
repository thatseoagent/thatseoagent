# Schema Markup

`ThatSeoAgent_Schema` (`includes/head/class-thatseoagent-schema.php`) prints one JSON-LD script per page, on `wp_head` at priority 2: a single `@graph` whose nodes point at each other by `@id`.

While another SEO plugin is active (see `ThatSeoAgent_Compat`, and the root [README](../../README.md)), the schema is not registered and nothing is printed.

## How the graph is built

`output()` works out the view and asks for its graph:

| View | Graph |
|------|-------|
| A single post, page or custom post type entry | `ThatSeoAgent_Schema::for_post( $post )` |
| An author archive | Site nodes, ProfilePage, the author's Person, BreadcrumbList |
| A post type archive, a category, tag or custom taxonomy archive, the posts page | Site nodes, CollectionPage, BreadcrumbList |
| Anything else (a homepage listing the latest posts, search, date archives, 404) | Site nodes only |

`for_post()` is public and gives a post's graph the same inside its page's request and outside it; the `get-post-seo` ability returns it. Filters that receive the post should branch on it rather than on conditional tags like `is_singular()`.

Every graph then goes through the same finish:

1. Empty nodes are dropped.
2. `thatseoagent_schema_graph` runs on the complete graph.
3. References that point at nothing are pruned (see [Validation](#validation)).

The script is encoded with `JSON_HEX_TAG`, so content containing `</script>` cannot break out of it.

## Nodes

### WebSite

On every page. `@id` `{home}/#website`, with the site's name, `inLanguage` (the site language as a BCP 47 tag) and its tagline as `description` when there is one.

There is no `potentialAction`: Google retired the sitelinks search box. Add one through `thatseoagent_website_schema` if another consumer needs it.

### Organization or Person

The site has one primary entity, the publisher of every Article. **ThatSeoAgent → Settings → Who the site is** decides it (`ThatSeoAgent_Identity`); an untouched install is an Organization.

- **Organization** (`{home}/#organization`): the site's name and URL, and the theme's custom logo as `logo`. The identity settings override the name, and add their description, their logo (over the theme's) and the social profiles as `sameAs`. The theme's logo is only the fallback.
- **Person** (`{home}/#person`): when the identity says the site belongs to a person. Name (else the site's), URL, description, the logo or photo as `image`, the social profiles as `sameAs`. The Organization node is then left out, so only one node claims to be the publisher.

`thatseoagent_primary_entity` can change the answer; `thatseoagent_person_schema` receives the identity's Person node, or `null`, and can return one.

### WebPage, AboutPage, ContactPage

On every singular page. `@id` `{permalink}#webpage`, with name, URL, `isPartOf` the WebSite, language, dates, the post's description (`ThatSeoAgent_Description::for_post()`), `breadcrumb` when there is a BreadcrumbList, `primaryImageOfPage` when the post has an image of its own, and `mainEntity` when it is a catalog entry.

The about and contact pages the bulletin finds (`ThatSeoAgent_Trust_Pages`, slugs filterable with `thatseoagent_trust_page_slugs`) are typed `AboutPage` and `ContactPage`.

### Article

On posts of the types `thatseoagent_article_post_types` returns (default `['post']`), never on a catalog entry: a page has one main entity.

`@id` `{permalink}#article`, with headline, dates, `wordCount`, language, description, the post's own image, `publisher` (the Organization or Person) and `author`:

- The post's author: a Person with `@id` `{author archive}#person`, name and URL inline so the Article still names its author if the Person node is filtered out. The full Person node is added to the graph: description from the biography, `jobTitle` and `sameAs` from the **As an author** fields on the user profile (`ThatSeoAgent_Author_Profile`) and the profile's website.
- A post with no author, a deleted one or one without a display name: the default author from **Settings → Default author**, else a reference to the publisher.

### FAQPage

Added beside the Article (so on the same post types) when at least two question/answer pairs are found (`ThatSeoAgent_FAQ`):

- H2/H3 headings that are questions, with the text below them as the answer;
- `core/details` blocks whose summary is a question, with their inner blocks as the answer.

Two opt-in strategies, off by default, synthesise questions that do not appear on the page, which conflicts with Google's requirement that marked-up content be visible: `thatseoagent_faq_numbered_enabled` and `thatseoagent_faq_thematic_enabled`.

### CollectionPage

On listings: post type archives, category, tag and custom taxonomy archives, and the posts page when it is not the front page. `@id` `{canonical}#webpage`, with the listing's title, its description from the head and the BreadcrumbList.

### ProfilePage and Person

On an author archive: a ProfilePage whose `mainEntity` is the author's Person, the same node, by the same `@id`, as on every Article they wrote, so search engines join them into one person.

### BreadcrumbList

On singular pages and listings, from the trail `ThatSeoAgent_Breadcrumbs` computes: the same trail the visible breadcrumbs print (`thatseoagent_breadcrumbs()`, `[thatseoagent_breadcrumbs]`, the Breadcrumbs block). The last item is the page itself and carries no link.

None is output:

- when the trail is only "Home";
- when a crumb has no name, or one before the last has no link: the whole list is dropped rather than published broken, and the WebPage points at no breadcrumb;
- where WooCommerce outputs its own: products, the shop page and the product taxonomy archives (`thatseoagent_woocommerce_states_breadcrumbs`, return `false` for a theme that prints no breadcrumbs).

### Product

On entries of a declared product catalog, as the page's `mainEntity` instead of an Article. Catalogs are declared in code by any theme or plugin with `thatseoagent_register_catalog()` on the `thatseoagent_init` action; see [Product catalogs](../../README.md#product-catalogs) for the declaration and what each key maps to.

`@id` `{permalink}#product`, with name, URL, description, images (featured first, then the gallery), `brand`, `category`, `additionalProperty`, `sku`, `mpn` and `gtin` as declared. No `offers`: there is no price to put in it. `ThatSeoAgent_Product::validate()` reports what is missing; **ThatSeoAgent → Products** and `wp thatseoagent validate-products` list it.

### WooCommerce products

WooCommerce marks up its own products (Product with offers) and their BreadcrumbList. Its product types cannot be declared as catalogs, so ThatSeoAgent outputs only the site nodes and the WebPage on them.

## Images

The Article image and the WebPage's `primaryImageOfPage` are the post's own: its featured image, else a catalog entry's first gallery image, else the first image in its content. Never the site logo or the default sharing image, which say nothing about the post. A password-protected post's content is not searched.

Images are ImageObjects with URL, width, height and the alt text as `caption`. Attachments that are not image files, or have no absolute URL, are left out. The size is `full` by default (Google wants at least 1200 px wide); `thatseoagent_schema_image_size` takes another registered size.

## Validation

- Names and descriptions are output as plain text: tags stripped, HTML entities decoded.
- After `thatseoagent_schema_graph`, any reference (a node holding only `@id`, maybe `@type`) to an `@id` on this site that no node in the graph describes is removed, along with any list it leaves empty. A node removed by a filter takes its references with it. References to other sites are kept.
- A broken BreadcrumbList is dropped whole (see above).

## Filters

| Filter | Arguments | Purpose |
|--------|-----------|---------|
| `thatseoagent_schema_graph` | `array $graph`, `?WP_Post $post` | The complete graph before output: add, remove or reorder nodes. `$post` is `null` on any view that is not a post's page |
| `thatseoagent_website_schema` | `array $schema` | WebSite node |
| `thatseoagent_primary_entity` | `string $entity` | `'person'` or `'organization'` |
| `thatseoagent_organization_schema` | `array $schema` | Organization node, after the identity settings |
| `thatseoagent_person_schema` | `?array $schema` | The site's Person node; `null` omits it |
| `thatseoagent_webpage_schema` | `array $schema`, `WP_Post $post` | WebPage, AboutPage or ContactPage node |
| `thatseoagent_breadcrumb_schema` | `array $schema`, `?WP_Post $post` | BreadcrumbList node (`null` post on a listing) |
| `thatseoagent_breadcrumb_trail` | `array $crumbs`, `?WP_Post $post` | The trail, for the schema and the visible breadcrumbs |
| `thatseoagent_breadcrumb_home` | `string $label` | Name of the first crumb |
| `thatseoagent_listing_page_schema` | `array $schema`, `string $type` | CollectionPage or ProfilePage node |
| `thatseoagent_author_schema` | `array $schema`, `WP_User $user` | Person node of a post author; `null` omits it |
| `thatseoagent_article_post_types` | `array $post_types` | Post types that get an Article (default `['post']`) |
| `thatseoagent_faq_schema_enabled` | `bool $enabled`, `int $post_id` | Return `false` to skip the FAQPage |
| `thatseoagent_faq_pairs` | `array $pairs`, `int $post_id` | Question/answer pairs (`question`, `answer`) |
| `thatseoagent_faq_numbered_enabled` | `bool $enabled`, `WP_Post $post` | Opt in to numbered-heading FAQs (off) |
| `thatseoagent_faq_thematic_enabled` | `bool $enabled`, `WP_Post $post` | Opt in to thematic FAQs (off) |
| `thatseoagent_product_post_types` | `array $post_types` | Post types marked up as products (the declared catalogs) |
| `thatseoagent_product_schema` | `array $node`, `WP_Post $post` | Product node |
| `thatseoagent_product_properties` | `array $properties`, `WP_Post $post` | Specifications (`name`, `value`) before they become PropertyValues |
| `thatseoagent_schema_image_size` | `string $size` | Image size for the schema (default `full`) |
| `thatseoagent_trust_page_slugs` | `array $slugs` | Slugs of the about, contact and privacy pages |
| `thatseoagent_woocommerce_states_breadcrumbs` | `bool $states`, `?WP_Post $post` | Whether WooCommerce outputs the BreadcrumbList of a page |

`thatseoagent_schema_graph` is the supported extension point. Add a node to it rather than printing a second JSON-LD script: it joins the graph, can reference the WebPage by `@id`, and is subject to the same pruning.

```php
// An Event node on the pages of an "event" post type.
add_filter( 'thatseoagent_schema_graph', function ( array $graph, ?WP_Post $post ) {
    if ( ! $post || 'event' !== $post->post_type ) {
        return $graph;
    }

    $url = get_permalink( $post );

    $graph[] = array(
        '@type'            => 'Event',
        '@id'              => $url . '#event',
        'name'             => get_the_title( $post ),
        'url'              => $url,
        'startDate'        => get_post_meta( $post->ID, '_event_start', true ),
        'mainEntityOfPage' => array( '@id' => $url . '#webpage' ),
    );

    return $graph;
}, 10, 2 );
```

A catalog entry already gets its Product node: declare the catalog instead of adding one here, or the page would carry two.

## Testing

- [Google's Rich Results Test](https://search.google.com/test/rich-results)
- [Schema.org validator](https://validator.schema.org/)
- Search Console's rich result reports
