# Changelog

## [1.12.0] - 2026-09-21

### Changed
- **The SEO meta box now appears on every post type with an editing screen**,
  not just posts and pages. The rest of the plugin already treated custom post
  types as first-class — they appear in the sitemap, they get WebPage and
  BreadcrumbList schema, and a stored `_lean_seo_description` was honoured on
  the front end for any post type — so the old default left no way to enter
  values the plugin was already reading. Attachments are excluded.

  The `lean_seo_meta_box_post_types` filter still governs the list, so a post
  type can be removed:

      add_filter( 'lean_seo_meta_box_post_types', function ( $types ) {
          return array_diff( $types, array( 'producto' ) );
      } );

- The registered REST meta fields follow the same list, so custom post types
  get `_lean_seo_title` and `_lean_seo_description` in the REST API too.

### Fixed
- **A custom SEO title no longer gets the site name appended to it.** The
  field replaced only the *title part* of the document title, so WordPress
  still added the site name afterwards: a title of "Product | Acme" was
  emitted as `Product | Acme | Acme`, in `<title>` and in `og:title`. It now
  replaces the document title outright.

  This is the same class of bug as the meta-box description preview fixed in
  1.9.0 — the preview showed the title verbatim while the front end emitted
  something else. Preview, `<title>` and `og:title` now agree.

  Precedence is unchanged: the Homepage SEO settings still win on the front
  page, and a post with no custom title still gets WordPress's own title with
  the site name appended.

### Removed
- `Lean_SEO::filter_title_parts()` and its `document_title_parts` hook. With
  the title short-circuited at `pre_get_document_title`, the callback was
  unreachable whenever it had work to do and a no-op otherwise.

## [1.11.0] - 2026-09-21

### Changed
- **`Requires at least` raised from 6.0 to 7.1.** The plugin registers
  Abilities API abilities, and `wp_register_ability()` landed in WordPress
  6.9 — the header previously claimed support for versions where that call
  does not exist. The registration stays behind its `function_exists()`
  guard: the header controls what WordPress will install, not what happens at
  runtime.

  This clears the five `wp_function_not_compatible_with_requires_wp` errors
  from `wp plugin check`. Note that 6.9 is the technical floor; 7.1 is the
  version this plugin is developed and tested against.

## [1.10.3] - 2026-09-21

Profiling pass. Three of the queries the plugin added to every front-end
request were avoidable; they are gone.

### Fixed
- `lean_seo_rewrite_version` is now autoloaded. It is read on every `init` by
  `maybe_flush_rewrite_rules()`, and storing it with `autoload = false`
  (1.7.1) cost one query on every request — front end and admin — to read a
  short version string that changes once per release.
- The IndexNow key-file route tests the request path before reading any
  option. It ran on every front-end request and read the key option (plus the
  legacy theme option when the key was empty, which is the common case)
  *before* checking whether the URL could even be a key file: two queries per
  request on every site, including those that never configured IndexNow. The
  path shape — `<8-128 chars from [a-zA-Z0-9-]>.txt` — is now tested first.

### Measured

Queries attributable to the plugin on a single-post render, object cache
cold, `alloptions` excluded:

```
before   12   (10 measured + 2 IndexNow, which WP-CLI short-circuits)
after     9
```

The nine that remain are the post and its meta, the featured image and its
meta, the post's terms, `site_logo`, and the two plugin options that do not
exist yet. Those last two — `lean_seo_identity` and `lean_seo_schema` — cost
one query each only while unconfigured: WordPress stores new options with
`autoload = auto`, so both join `alloptions` and become free as soon as the
settings are saved.

Wall-clock cost of the plugin's `wp_head` callbacks, averaged over 20 runs:
`Meta::output` 0.96 ms, `Schema::output` 0.49 ms, `output_canonical` 0.02 ms.

## [1.10.2] - 2026-09-21

Findings from `wp plugin check`. Warnings went from 11 to 2; the two that
remain are deliberate.

### Fixed
- `article:published_time` and `article:modified_time` were printed without
  escaping.
- **The taxonomy sitemaps ran one query per term** to find each term's last
  modification. One grouped query now covers the whole taxonomy, memoised per
  request, and the sitemap index reuses the same map. Rendering
  `sitemap-categories.xml` dropped from 8 queries to 3 on a site with two
  categories, and the count no longer grows with the number of terms. Output
  is unchanged, down to the `lastmod` format.
- The Abilities API registration is now behind `function_exists(
  'wp_register_ability' )`. The declared minimum stays at WordPress 6.0:
  everything except the abilities works there, and previously the code was
  safe only because `wp_abilities_api_init` never fires on older versions —
  nothing stated the dependency.
- `$_SERVER['REQUEST_URI']` is sanitized before use in the IndexNow key-file
  route.
- `uninstall.php` no longer leaks `$site_ids` / `$site_id` into the global
  scope; the multisite loop moved into a function.

### Notes on the remaining plugin-check output

Four findings are left standing on purpose:

- **`load_plugin_textdomain()` discouraged** — the tool's advice assumes the
  plugin is hosted on WordPress.org, where translations arrive automatically.
  This one is not, so removing the call would break the bundled Spanish
  translation.
- **`wp_register_ability()` requires WP 6.9** — the call is guarded by
  `function_exists()`; static analysis cannot see that. Raising `Requires at
  least` to 6.9 would lock out WordPress 6.0–6.8, where every other feature
  works.
- **`.gitignore` is a hidden file** — a packaging rule for the WordPress.org
  zip. It belongs in the repository.
- **`readme.txt` headers (`Tested up to`, `License`, `Stable tag`)** — the
  plugin is distributed outside the directory, so `README.md` is the
  canonical documentation.

Two more are documented inline with `phpcs:ignore` and a reason: the meta box
passes raw `$_POST` to `Lean_SEO_Post_Seo::save_from_request()`, which must
receive still-slashed values to unslash them correctly; and
`Lean_SEO_Post_Seo::count_missing()` is a deliberately uncached direct query
whose only caller is the WP-CLI command.

## [1.10.1] - 2026-09-21

Abilities API verification pass, run against the live WordPress 7.1.1 install.

### Added
- Semantic annotations on all five abilities, under `meta.annotations`. The
  four reads declare `readonly: true, destructive: false, idempotent: true`;
  `update-post-seo` declares `readonly: false, destructive: false,
  idempotent: true`.

  This is not cosmetic. The Abilities REST run controller routes by
  annotation: with `readonly` unset, all five abilities were routed as POST,
  including the four that only read, and an agent introspecting them could
  not tell `get-post-seo` apart from `update-post-seo`. They now route as
  GET, GET, GET, GET and POST.

  The reads were adversarially confirmed write-free before the annotation was
  added — executed with `postmeta`, `options` and cron row counts taken
  before and after, all unchanged — so `readonly: true` is a verified claim,
  not an assumption.
- `additionalProperties: false` on all five input schemas. A misspelled key
  (`post-id` for `post_id`) was previously accepted and fell through to a
  callback that saw no ID; it is now rejected at validation with
  `ability_invalid_input`.
- `default => (object) array()` on `get-sitemap-urls`, the documented
  hardening for zero-argument abilities on the indirect-invocation path.

### Changed
- `lean_seo_post_not_found` folded into `lean_seo_invalid_post_id`. The
  Abilities error-code vocabulary has no "not found" category: an ID that
  resolves to no post is a semantically wrong field value, which agents
  handle the same way — correct the value and retry. The distinct message
  ("No post exists with that ID.") keeps the information a human needs.

## [1.10.0] - 2026-09-21

Structured data audit against Google's requirements, run on live pages.

### Added
- `inLanguage` on the WebSite, WebPage and Article nodes, taken from the
  site language.
- `lean_seo_article_post_types` filter — post types that get an Article node
  (default `array('post')`).
- `lean_seo_schema_image_size` filter — image size for the Article image
  (default `full`).

### Fixed
- **Custom post types received no page-level schema at all.** `output()`
  handled only `post` and `page`, so every public CPT reached search engines
  with just WebSite and Organization: no WebPage, no BreadcrumbList. Any
  singular view now gets both.
- **The Article author claimed a person wrote posts that have no author.**
  With nothing configured, the fallback built a Person node named after the
  blog. It now references the entity that already publishes the site
  (`@id` of the Organization or Person node). An explicitly configured
  fallback author still wins.
- The Article image used the `large` size, which caps at 1024px by default
  while Google asks for at least 1200px wide. Now `full`.
- `WebSite.description` was emitted as an empty string on sites with no
  tagline.
- The homepage emitted a BreadcrumbList containing a single "Home" crumb,
  which is not a trail. Breadcrumbs with fewer than two items are omitted.

### Removed
- The `potentialAction` / `SearchAction` block on the WebSite node. Google
  retired the sitelinks search box, so it was dead weight on every page. Add
  it back through `lean_seo_website_schema` if another consumer needs it.

## [1.9.1] - 2026-09-21

### Added
- Spanish (`es_ES`) translation, plus `languages/lean-seo.pot` for other
  locales.
- `load_plugin_textdomain()`. Plugins hosted on WordPress.org have their
  translations delivered by translate.wordpress.org; this one is distributed
  outside the directory and has to load its own.

### Fixed
- The meta box rendered six user-facing strings in hardcoded English,
  bypassing the text domain: the box title, both field labels, both
  descriptions and the textarea placeholder.
- The ten SEO audit messages returned by `lean-seo/audit-post-seo` and
  `lean-seo/scan-seo-issues` were untranslatable.
- `Domain Path: /languages` was declared in the plugin header but the
  directory did not exist.
- Added the missing `translators:` comment for the IndexNow status-code
  string.

### Changed
- README rewritten for 1.9.0: documents all 25 hooks, the module layout, the
  WP-CLI command, the IndexNow privacy behaviour and uninstall semantics.
  Dropped a stale "Known Issues" entry about a 1.0.x version-constant
  mismatch that no longer exists, and the "~1,500 lines" claim.

## [1.9.0] - 2026-09-21

Three deepenings from the architecture review. No new features; the same
behaviour now lives behind smaller interfaces.

### Added
- `Lean_SEO_Description` — the single answer to "what description does this
  post get?". Two methods: `for_post()` (custom meta, else generated) and
  `generate()` (ignores stored meta). Takes a post, so it works from
  `wp_head`, WP-CLI, an ability and the meta box alike — and is testable
  without building a `WP_Query`.
- `Lean_SEO_Sitemap::render( $which, $args )` returns an XML string, and
  `Lean_SEO_Sitemap::get_sitemap_urls()` states once which sitemaps the site
  publishes.
- `Lean_SEO_Identity::primary_entity()` — the single answer to "is this site
  a Person?".
- `Lean_SEO_Post_Seo` — the owner of `_lean_seo_title` and
  `_lean_seo_description`: the keys, the sanitizers, the SQL and the
  slashing. Two write entry points name the invariant instead of leaving it
  to tribal knowledge: `save()` for values from code, `save_from_request()`
  for values from `$_POST`. The key strings previously appeared in six files,
  including a hand-written SQL join in WP-CLI and a hard-coded list in
  `uninstall.php`; they now appear in one, and `uninstall.php` loads the
  module to ask.
- `Lean_SEO_FAQ` and `Lean_SEO_FAQ_Section` — post content is read once into
  sections (a heading plus the text beneath it), and the three extraction
  strategies became predicates over that list. `Lean_SEO_FAQ::sections()`
  takes a plain HTML string, so FAQ extraction is testable without a post,
  a loop or reflection.
- Filters: `lean_seo_description_filler_patterns` (intro-filler regexes) and
  `lean_seo_sitemap_entries` (the index listing).

### Changed
- **Descriptions are generated one way everywhere.** The front end, the
  abilities, WP-CLI and the editor's Google preview used four different
  rules; WP-CLI's sentence extraction won. On posts with no saved
  description the front end now emits whole sentences capped at 155
  characters instead of a 30-word cut — typically shorter and cleaner.
- **The editor preview stops lying.** It rendered 25 words without stripping
  shortcodes, so it showed a description the front end never emitted.
- **The sitemap no longer echoes.** Renderers return strings; `handle_request()`
  is the only place that sends headers, prints and exits.
- **`lean-seo/get-sitemap-urls` asks the sitemap module.** Its private copy of
  the URL scheme had drifted: it omitted custom post type sitemaps and page
  pagination, so the ability contradicted the site's own `/sitemap.xml`.
- The Person schema node is resolved once per request instead of twice
  (`output()` and `get_publisher_id()` both asked, and the chain behind it
  reaches the options table and the media library).
- `filter_organization_schema()` used a different rule than its two sibling
  filters to decide the site's identity; all three now ask
  `primary_entity()`.
- Sentence splitting recognises Unicode uppercase and the Spanish `¿` / `¡`,
  so accented and Spanish-language content splits correctly.

- The FAQ scaffolding existed in triplicate: three copies of the heading
  split, three of "the answer is the next part unless it is a heading", and
  three of the 20/500 character thresholds. Each now exists once. The
  numbered-heading pattern was also written twice in two different forms —
  the thematic strategy re-derived it to know what to skip — and is now one
  shared predicate.
- An answer no longer runs past an intervening heading. The two synthesising
  strategies split on H3 only, so an H2 between an H3 and the next H3 was
  swallowed into the answer text. Both are opt-in and off by default.
- `class-lean-seo-schema.php` drops from 954 to 496 lines.

### Removed
- `Lean_SEO_CLI`'s private `extract_description()`, `content_to_text()`,
  `split_sentences()`, `is_filler()` and `truncate()` (moved into
  `Lean_SEO_Description`); the file drops from 414 to 243 lines.
- `Lean_SEO_Abilities::generate_description()` — a hand-retyped copy of the
  front end's rule.
- `Lean_SEO_Sitemap::register_query_vars()` — dead since nothing hooked it;
  the query vars are registered in `Lean_SEO::sitemap_query_vars()`.
- The `$in_faq_section` tracking in the FAQ extractor: the flag was set and
  cleared but never read, so it selected nothing.
- The site-specific filler pattern `/sarai\s*chinwag\s*here/i` no longer
  ships in the plugin. Re-add it per-site:

      add_filter( 'lean_seo_description_filler_patterns', function ( $p ) {
          $p[] = '/sarai\s*chinwag\s*here/i';
          return $p;
      } );

### Fixed
- Adding a third per-post SEO field used to mean editing six files; missing
  `uninstall.php` left orphan rows behind with nothing failing loudly.
- Docblocks in the Identity and Homepage settings modules claimed they were
  "Called from Lean_SEO_Admin::register_settings". They are hooked
  independently from `Lean_SEO::init_hooks()`.

## [1.8.0] - 2026-09-21

### Added
- `uninstall.php` — deleting the plugin now removes its options
  (`lean_seo_schema`, `lean_seo_identity`, `lean_seo_homepage`,
  `lean_seo_indexnow_key`, `lean_seo_rewrite_version`), the `_lean_seo_title`
  / `_lean_seo_description` post meta and any queued IndexNow events, across
  every site on multisite. The legacy theme option it can read from
  (`sarai_chinwag_indexnow_key`) is deliberately left untouched.
- `register_post_meta()` for both SEO fields with `show_in_rest`, a sanitizer
  and an `edit_post` auth callback, so the block editor, custom sidebars and
  headless clients can read and write them.
- `lean_seo_primary_entity` filter — declares whether the site is primarily a
  Person or an Organization.
- `lean_seo_faq_thematic_enabled` and `lean_seo_faq_numbered_enabled` filters
  — opt back in to the synthesised FAQ strategies (see below).

### Changed
- **The two synthesised FAQ strategies are now off by default.** Both
  invented questions that appear nowhere on the page — the thematic one
  outright ("What does fire mean spiritually in terms of transformation?"),
  the numbered one by appending a question mark to a statement ("Parrots Are
  Exceptionally Smart?"). That conflicts with Google's requirement that
  marked-up content be visible to the user. Detection of real question
  headings (strategy 1, including "¿…?") is unchanged and still on. Re-enable
  the others with `add_filter( 'lean_seo_faq_thematic_enabled', '__return_true' )`
  and `add_filter( 'lean_seo_faq_numbered_enabled', '__return_true' )`.
- **The schema graph no longer emits a Person and an Organization at once.**
  Two nodes were competing to be the publisher. When Site Identity is set to
  Person, the Organization node is dropped and Articles are published by the
  Person; otherwise nothing changes.
- **The Person node is only emitted once Site Identity has been saved.**
  Unconfigured sites were getting a contentless Person named after the site.
- **`robots.txt` filtering no longer deletes every line containing the word
  "sitemap".** Only Yoast blocks and `Sitemap:` directives pointing at this
  host (ours, or a stale one from a previous plugin) are removed; third-party
  sitemap directives are preserved.
- Meta box CSS and JavaScript moved out of the rendered HTML into
  `assets/admin-meta-box.css` / `.js`, enqueued only on the edit screens of
  post types that show the box.

## [1.7.1] - 2026-09-21

### Fixed
- **Abilities API schemas were corrupted** by a bad find-replace: 11 stray
  `'category' => 'site'` keys sat inside `input_schema`/`output_schema`
  property definitions, and two of them leaked into the payload returned by
  `get-post-seo`. Removed; `required` now lives at the object level as an
  array instead of per-property.
- **JSON-LD could break out of its `<script>` tag.** `JSON_UNESCAPED_SLASHES`
  let a literal `</script>` inside post content close the block and inject
  markup into `<head>`. Added `JSON_HEX_TAG`.
- **Sitemap rewrite rules were missing after activation.** The activation hook
  flushed before the rules existed (`plugins_loaded` has already fired during
  activation), persisting a rule set without any sitemap route — every
  `/sitemap*.xml` 404'd until permalinks were saved again. Activation now
  clears `lean_seo_rewrite_version` and `Lean_SEO::maybe_flush_rewrite_rules()`
  flushes on `init` priority 21, after the routes are registered.
- **`wp lean-seo generate-descriptions` never advanced its cursor.** `$offset`
  stayed at 0, so dry runs reported the same posts repeatedly and real runs
  re-fetched skipped posts forever. The cursor now tracks the posts left
  in place. Also validates `--post-type` and guards `--batch-size`.
- **IndexNow was non-functional and blocked the editor.** There was no UI to
  set the key, nothing served the `{key}.txt` verification file (so every
  submission was rejected), and the HTTP call ran inline on `save_post` with a
  20 s timeout. Now: a key field under Settings → Lean SEO (with a suggested
  key and validation), the verification file served dynamically at the
  advertised `keyLocation`, and submissions dispatched through a scheduled
  `lean_seo_indexnow_submit` event.
- **`lean-seo/scan-seo-issues` loaded every published post** (`posts_per_page
  => -1`) with full content before applying the limit. Now scans in batches of
  100 with a scan cap, stopping as soon as `limit` results are collected.
- **Sitemap URLs were not XML-escaped.** Permalinks containing `&` produced
  invalid XML that Google rejects. All `<loc>`/`<lastmod>` values now go
  through `esc_url()`/`esc_html()`.
- **The homepage appeared twice in `sitemap-pages.xml`** when a static front
  page is configured. The assigned front page is now skipped in the loop.
- **`/sitemap-posts-0.xml` produced a negative query offset.** The page number
  is clamped via `max(1, absint())`.
- **`sitemap-pages.xml` was unpaginated** (`posts_per_page => -1`) unlike the
  posts sitemap. Pages now paginate at 1000 URLs with `sitemap-pages-N.xml`
  routes and matching index entries.
- **Article schema almost always fell back to the publisher author.**
  `get_the_author()` reads the `$authordata` global, which is not populated
  during `wp_head`; the author is now resolved from `$post->post_author`.
- **Byte-based length and word counts broke on accented content.** `strlen()`
  and `str_word_count()` are ASCII-only, inflating every SEO audit metric and
  `wordCount` in Spanish and other accented languages. Replaced with
  `mb_strlen()` and a Unicode-aware word counter.
- Deactivation now clears queued `lean_seo_indexnow_submit` cron events.
- Nonce verification now runs through `wp_unslash()`/`sanitize_text_field()`,
  and `update-post-seo` slashes values before `update_post_meta()` so
  backslashes in submitted text survive the metadata API's unslashing.

## [1.7.0] - 2026-04-19

### Added
- **Homepage SEO** settings section under Settings → Lean SEO:
  - Custom homepage title (text field with template-variable support)
  - Custom homepage meta description (textarea)
- Template variables supported in homepage title/description:
  - `%%sitename%%` → site name
  - `%%tagline%%` → site tagline
  - `%%sep%%` → title separator (respects `lean_seo_title_separator`)
- `Lean_SEO_Homepage_Applier` wires settings into filters from 1.5.0:
  - `lean_seo_document_title` ← homepage title when context is `home`
  - `lean_seo_description` ← homepage description when context is `home`
    (only when the default resolution would return an empty value or the
    generic blog tagline — explicit per-page descriptions still win)

### Changed
- No behavior changes when homepage settings are empty — the plugin
  continues to defer to WordPress defaults.

## [1.6.0] - 2026-04-19

### Added
- **Site Identity** settings section under Settings → Lean SEO. Configure
  who the site represents (Person or Organization) without writing code:
  - Identity type radio (Person | Organization)
  - Name
  - Description / bio
  - Logo / photo (media picker)
  - Default OG image (media picker, fallback for posts without featured image)
  - Twitter @handle (normalized; leading @ stored stripped)
  - Social profile URLs for Twitter/X, GitHub, Facebook, LinkedIn,
    Instagram, YouTube, Mastodon (emitted as `sameAs` in schema)
- `Lean_SEO_Identity_Applier` automatically wires settings into the
  filters added in 1.5.0:
  - `lean_seo_twitter_handle` ← Twitter handle setting
  - `lean_seo_default_image` ← default OG image setting
  - `lean_seo_person_schema` ← built when type is 'person'
  - `lean_seo_organization_schema` ← enriched with description, logo, sameAs
- Media uploader JS enqueued only on the Lean SEO settings page.

### Changed
- No behavior changes when identity settings are empty — the plugin
  continues to output the same minimal schema it always has. The UI
  is purely additive.

## [1.5.0] - 2026-04-19

### Added
- New filters for title, description, and social meta:
  - `lean_seo_title` — override the resolved SEO title
  - `lean_seo_document_title` — short-circuit the `<title>` tag (works on the homepage)
  - `lean_seo_og_title` — override the `og:title` value
  - `lean_seo_description` — override the resolved description (context-aware)
  - `lean_seo_og_site_name` — override `og:site_name`
  - `lean_seo_og_locale` — override `og:locale`
  - `lean_seo_twitter_handle` — set `@handle` for `twitter:site`
  - `lean_seo_title_separator` — customize the title separator (default `|`)
- New filters for schema enrichment:
  - `lean_seo_website_schema` — modify the WebSite node
  - `lean_seo_organization_schema` — modify the Organization node (add `sameAs`, etc.)
  - `lean_seo_person_schema` — opt-in Person node for personal sites
  - `lean_seo_webpage_schema` — modify the WebPage node
  - `lean_seo_breadcrumb_schema` — modify the BreadcrumbList node
  - `lean_seo_schema_graph` — modify the complete `@graph` before output
- `Lean_SEO_Meta::get_context()` helper exposing the current page context
  (`home` | `single` | `archive` | `taxonomy` | `search` | `author` | `date` | `404` | `other`).

### Changed
- `Lean_SEO_Meta::get_description()` now applies `lean_seo_description` after
  the default fallback chain so themes/plugins can refine the resolved value
  without rebuilding it from scratch. The legacy `lean_seo_custom_description`
  filter still short-circuits the chain for backwards compatibility.
- `pre_get_document_title` is now hooked so `lean_seo_document_title` can
  override the homepage `<title>` (which `document_title_parts` cannot).

### Fixed
- BreadcrumbList no longer emits a duplicate `Home → Home` entry on the
  homepage. The current-page crumb is now skipped when on the front page.

## [1.2.0] - 2026-02-23

### Added
- IndexNow integration for automatic search engine URL submission on publish

## [1.1.0] - 2026-02-23

### Added
- FAQPage schema auto-detection from post content

## [1.0.1] - 2026-02-01

### Changed
- Add CHANGELOG.md via homeboy
- Initial commit: Lean SEO v1.0.0

### Fixed
- Fix sitemap pagination + add robots.txt filter

## [1.0.0] - 2026-02-01
- Initial release
