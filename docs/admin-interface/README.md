# Admin Interface

ThatSeoAgent adds SEO fields to the post and term editors, a bulk action and a dashboard widget, and its own screen under **ThatSeoAgent** in the admin menu.

## The SEO meta box

`ThatSeoAgent_Meta_Box` (`includes/admin/class-thatseoagent-meta-box.php`) adds a meta box titled **SEO** to the edit screen of every post type that has SEO fields. The values go through `ThatSeoAgent_Post_Seo`, which owns the fields: storage, sanitization and slashing.

- `add()` registers the meta box.
- `render()` prints the fields and the preview.
- `save()` saves on `save_post`, through `ThatSeoAgent_Post_Seo::save_from_request()` and `ThatSeoAgent_Primary_Term::save_from_request()`.
- `enqueue_assets()` loads `assets/admin-meta-box.css`, `assets/admin-meta-box.js` and the media library on those edit screens only.

### Post types

`ThatSeoAgent_Post_Seo::post_types()` answers which post types get the fields: every public post type with `show_ui`, except attachments, through the `thatseoagent_meta_box_post_types` filter. The meta box, the bulk action and the registered post meta all follow it.

```php
// No SEO fields on a custom post type.
add_filter( 'thatseoagent_meta_box_post_types', function ( $post_types ) {
    return array_values( array_diff( $post_types, array( 'portfolio' ) ) );
} );
```

### Fields

1. **SEO title**: the full title in search results; the site name is not added. Empty: the post's name and the site's, as WordPress builds it. Its counter turns red past 70 characters, where search results may cut it.
2. **Meta description**: empty, one is generated from the excerpt or the content. Its counter turns red past 165 characters.
3. **Keep out of search results**: adds `noindex, follow` to the page, prints no canonical, and leaves the post out of the sitemaps and llms.txt. The page stays public for anyone with the link.
4. **Social sharing image**: chosen from the media library; used for Open Graph ahead of the default sharing image and the featured image (see [Meta tags](../meta-tags/#which-image-a-page-is-shared-with)).
5. **Primary {taxonomy}**: one select per public hierarchical taxonomy in which the post has two or more terms. **Automatic** names the term chosen otherwise: the deepest assigned one, leaving out the default category. The primary term names the post in the breadcrumbs, `article:section`, a catalog entry's Product category, and `%category%` in the permalink.

The counters only warn: the inputs have no `maxlength`, and nothing is cut. Google sets no limit on either.

### Preview

Below the fields, a search result preview: the title, the URL and the description as the front end publishes them, from `ThatSeoAgent_Title::for_post()` and `ThatSeoAgent_Description::for_post()`. `assets/admin-meta-box.js` (jQuery) updates it and the counters as you type; with a field empty, the preview shows what the front end publishes with it empty, not the placeholder.

### Storage

Post meta, registered with `show_in_rest` for users who can edit the post:

| Key | Holds |
|-----|-------|
| `_thatseoagent_title` | SEO title |
| `_thatseoagent_description` | Meta description |
| `_thatseoagent_noindex` | `1` when the post is kept out of search |
| `_thatseoagent_share_image` | Attachment ID of the social sharing image |
| `_thatseoagent_primary_{taxonomy}` | Term ID of the primary term in that taxonomy |

An emptied field deletes its meta. Only fields present in the submission are touched.

The user profile gets two more fields, under **As an author**, for users who can publish: `_thatseoagent_job_title` and `_thatseoagent_profiles` in user meta (`ThatSeoAgent_Author_Profile`), read by the author's Person node in the [schema](../schema-markup/).

### Security

- Nonce `thatseoagent_save`, checked on save.
- `current_user_can( 'edit_post', $post_id )`.
- Autosaves are skipped.
- Title sanitized with `sanitize_text_field()`, description with `sanitize_textarea_field()`, the flag and the attachment ID with their own sanitizers.

### Integration

- `ThatSeoAgent_Title` sets the `<title>` through `pre_get_document_title`, `document_title` and `document_title_separator`; the SEO title replaces the whole title.
- `ThatSeoAgent_Description::for_post()` gives the description to the head, the schema, the Markdown version, llms.txt and the preview.
- `ThatSeoAgent_Indexing` reads the noindex flag; `ThatSeoAgent_Image` the sharing image; `ThatSeoAgent_Primary_Term` the primary terms.

## Term fields

`ThatSeoAgent_Term_Seo` adds a **Search engines** group to the add and edit screens of every public taxonomy with an archive, post formats excluded (`thatseoagent_term_seo_taxonomies`):

- **SEO title**: the full title in search results; the site name is not added.
- **Meta description**: empty, the term's own description.
- **Search results**: **Keep this page out of search results and the sitemap**.

Stored in term meta under the same keys as a post's: `_thatseoagent_title`, `_thatseoagent_description`, `_thatseoagent_noindex`.

## Bulk action

`ThatSeoAgent_Bulk_Descriptions` adds **Generate meta description** to the posts list of every post type with SEO fields. For each selected post the user can edit that has no description of its own, it saves the one the front end was already generating. A notice reports how many were saved and how many skipped (already had one, or no content to describe).

## Dashboard widget

`ThatSeoAgent_Dashboard_Widget` adds a **ThatSeoAgent** widget to the WordPress dashboard, for users with `manage_options`: the bulletin's headline in a core admin notice of its warning level, its three most serious warnings with where each is fixed, and a link to the whole bulletin. No styles of its own.

## The ThatSeoAgent screen

`ThatSeoAgent_App` (`includes/admin/class-thatseoagent-app.php`) adds a top-level **ThatSeoAgent** menu page (`toplevel_page_thatseoagent`) for users with `manage_options`. It has no submenus: the screen has its own navigation, one view per `?view=`:

| View | `view` | Shows |
|------|--------|-------|
| Overview | `dashboard` | The site bulletin: the headline on the color of its warning level, the warnings in force and what to do about each, and the readings beside them |
| Products | `products` | How each catalog entry is described to search engines and what is missing. Hidden while WooCommerce is active and no catalog is declared |
| Content check | `audit` | Titles, descriptions, headings, links and images, page by page, run a few at a time |
| AI crawlers | `crawlers` | Which AI crawlers can read the site and why, and the on-demand access check |
| AI index | `llms` | llms.txt and the Markdown check |
| Settings | `settings` | The settings, in sections |

Templates live in `includes/admin/views/`. The screen is styled with Tailwind and made interactive with Alpine.js; see Development in the root [README](../../README.md#development).

### Settings

The Settings view lays out the Settings API sections registered on the `thatseoagent_settings` page, with an index, in this order:

1. **Who the site is**: Person or Organization, name, short description, logo or photo, default sharing image, X username, social profiles
2. **Homepage**: title and description, with `%%sitename%%`, `%%tagline%%`, `%%sep%%`
3. **Site verification**: search engine console codes
4. **Analytics and tags**: Google Analytics and Google Ads IDs, and code for the head, the opening of the body and the footer (changing the code needs `unfiltered_html`)
5. **Default author**: credited on posts with no author
6. **AI index**: llms.txt
7. **Caches and CDN**: the `.htaccess` rule and the Cloudflare steps for Markdown requests
8. **AI crawlers**: allow or block each group, with exceptions
9. **Crawl cleanup**
10. **IndexNow**

Saving applies to the whole site at once. Every option is also registered with `show_in_rest`, at `/wp/v2/settings`.

### Moving between views

The ThatSeoAgent screen behaves like a single-page app: links between its views are fetched with the `X-ThatSeoAgent-View` header, which makes `ThatSeoAgent_App::maybe_send_view()` answer with the screen alone as JSON (`version`, `html`, `title`, `bulletin`) instead of the whole admin page. In `assets/admin/app.js`, the `tsaScreen` component on `#thatseoagent-app` sends clicks on links to other views, and back and forward (`@popstate.window`), to `Alpine.store( 'router' ).go()`, which swaps the navigation and the main column and keeps history, the title and the sidebar in step. Alpine's own mutation observer stops the components that leave and starts the ones that arrive, so none is initialised twice; `$store.router.loading` sets `aria-busy` on the screen while the next view loads. A view with unsaved work sets `Alpine.store( 'router' ).leave` to be asked before it is replaced. A view fetched with a different `version` than the open page loaded, after a plugin update, is loaded as a whole page. Without JavaScript, or on any error, the link loads as a normal page.

## Best practices

- **Titles**: distinct for each page and descriptive; past about 70 characters they may be cut.
- **Descriptions**: unique and relevant rather than long; past about 165 characters they may be cut.
- **Preview**: check it before publishing.
