# Changelog

## [Unreleased]

### Changed
- **PHP 8.3 or later is required**, up from 7.4.

### Fixed
- **A robots.txt that starts with a byte order mark is read whole.** The
  mark hid its first line, usually a `User-agent`, so the rules of that
  group were dropped and AI crawlers it blocks were reported as allowed.

## [2.10.0] - 2026-09-28

### Added
- **The bulletin on the WordPress dashboard** (`ThatSeoAgent_Dashboard_Widget`):
  the headline in a core notice coloured by the warning level, the three most
  serious warnings with a link to where each is fixed, how many more there
  are, and a link to the whole bulletin. For administrators only, in the
  admin's own markup.

### Changed
- **WooCommerce marks up its own products.** WooCommerce prints a Product
  with its offers, and a BreadcrumbList, in its own JSON-LD; ThatSeoAgent
  keeps the title, the description and the sharing image, and leaves the
  rest to it (`ThatSeoAgent_WooCommerce`). While WooCommerce is active:
  - its product types cannot be a catalog: they are no longer offered in
    Settings → Product catalog, a saved mapping of them is ignored, and the
    bulletin never asks whether they list products. With no other catalog,
    the Products screen and the "Products marked up" reading are gone,
    `/catalog.jsonl` is not served and the bulletin reads "Left to
    WooCommerce".
  - the graph has no BreadcrumbList on products, the shop page and the
    product category, tag and brand archives, where WooCommerce states
    one. The visible breadcrumbs stay. A theme that prints no WooCommerce
    breadcrumbs can keep ThatSeoAgent's with the new
    `thatseoagent_woocommerce_states_breadcrumbs` filter.
- **`og:type` is `product`** on catalog entries and WooCommerce products,
  which no longer get `article:*` or `og:pin:*` tags either.
- **Clearer wording** across the screen, the meta box and the bulletin:
  no dashes as connectors, no title case on field labels, no emoji in the
  meta box title. The bulletin's summary is translated into Spanish, which it was not.
- **The featured image is shared again**, after the sharing image chosen
  for the post and the default sharing image, before the theme logo. A
  post with neither of the first two was shared with the logo, or with no
  image at all: a product without its photo.

## [2.9.0] - 2026-09-27

### Added
- **SEO for term archives.** Categories, tags and the public taxonomies of
  other types — brands, product categories — get a search title, a meta
  description and noindex of their own (`ThatSeoAgent_Term_Seo`), on the
  term's add and edit screens. A written title is the full title, as for
  posts; without one the archive keeps the term name and the site name.
  The description falls back on the term's own description, then on a
  translated sentence naming it. noindex reaches the robots meta and takes
  the term out of the sitemap.
- **Sitemaps for custom taxonomies**: `/sitemap-tax-{taxonomy}.xml` for
  each public taxonomy other than categories and tags, listed in the
  index. Brand and product category archives were in no sitemap.
- **Nine new abilities, fourteen MCP tools**: `generate-descriptions`,
  `list-term-seo`, `get-term-seo`, `update-term-seo`, `get-seo-settings`,
  `update-seo-settings` (every option of the settings registry, through
  its schema and sanitizer; `tracking` also needs `unfiltered_html`),
  `get-duplicates`, `get-link-report` and `get-site-bulletin`
  (`ThatSeoAgent_Site_Abilities`). `ThatSeoAgent_Duplicates::report()` and
  `ThatSeoAgent_Links::report()` back the reports.
- **`update-post-seo` sets the sharing image and the primary term** of each
  taxonomy, and returns `warnings`: a title or description that may be cut
  (over 70 or 165 characters) or that another page shares. They never
  block the save. `get-post-seo` returns the image the page is shared with
  and its source, and the primary terms.

### Changed
- **`scan-seo-issues` takes `post_type: any`, `status: any`, `issue` and
  `offset`**, returns each post's issues in full, and answers an object:
  `results`, `scanned` and `next_offset`. It used to answer the list
  alone. `ThatSeoAgent_Audit::scan_report()` does the work; `scan()` wraps
  it.
- **The post abilities only take content with SEO fields.** A revision, an
  attachment or a trashed post is answered with why, and a missing post
  with "No post exists with that ID." instead of a permissions error.
- The descriptions of `update-post-seo` and `get-post-seo` say that a
  written title is printed as it is, without the site name.
- An `update-post-seo` or `update-term-seo` call with no field to change is
  an error instead of a silent success.

### Fixed
- The descriptions of term archives, searches and author pages were built
  from untranslated English sentences.

## [2.7.0] - Unreleased

### Added
- **The access check and the Markdown check are kept, and warn.** Each
  run is stored with the one before it (`ThatSeoAgent_Checks`), the screen
  opens on the last one and says what changed since the one before
  ("GPTBot: reached (200) → turned away (403)"), and both run once a week
  on their own. From a result at most 8 days old the bulletin warns when
  robots.txt answers a server error (red), when it answers a 4xx while the
  site blocks crawlers (yellow), when the server turns away a crawler
  robots.txt lets in — training crawlers aside (orange) — when a cache
  hands the Markdown version to browsers (red), and when agents asking for
  Markdown get the HTML page (yellow). A check that got no answer is no
  warning.

### Changed
- **`pnpm run dev` watches the screen's views and rebuilds its CSS**
  (Tailwind with `--watch=always`, so it keeps running in the background).
- **A post publishes one title and one description, wherever it is
  read.** `ThatSeoAgent_Description::for_post()` is the description the
  head, the JSON-LD, the Product markup, the Markdown version, llms.txt and
  the editor's preview all carry, and the new `ThatSeoAgent_Title::for_post()`
  is the search title the `<title>`, og:title, the editor's preview and the
  content check all use. Until now the `thatseoagent_description` filter
  and the homepage's own description reached the head alone, so llms.txt
  and the Markdown version could say something else.
- **`thatseoagent_title`, `thatseoagent_document_title` and
  `thatseoagent_description` receive the post** as a third argument (`null`
  on a listing), and for a post they run wherever its title or description
  is used, not only while its page is served. A callback that branches on
  conditional tags like `is_page()` should branch on the post instead.
- **`thatseoagent_title` reaches the `<title>` too**, not only the meta
  tags, so the `<title>` and og:title always say the same.
- **The homepage's own title and description come first on the homepage,
  both.** The description used to give way to one generated from the
  homepage's text; now the order is the homepage's own, then the page's SEO
  fields, then the generated one, then the tagline — as the title already
  was.
- **The content check measures the title the page publishes**, with the
  site name, as That SEO Agent's MCP reads it from the page; some titles now
  count as long enough to be cut. It and the `get-post-seo` ability report
  the description after the homepage's and the filter, too.
- **The editor's preview shows the full search title**, with the site name
  when the SEO title is empty, and an emptied field previews what it would
  publish instead of the value last saved.
- **Pages that share a title** are compared by the search title each
  publishes, filters included.
- **Each bulletin check lives in the module it checks.** Indexing, Compat,
  Identity, Homepage, Trust_Pages, New_Types, Default_Author,
  Sample_Content, Links, Product_Report, Crawler_Access and IndexNow each
  answer `bulletin()` with their observations and warnings;
  `ThatSeoAgent_Bulletin` gathers them, ranks the warnings and words the
  headline. Adding a check is that method and one line in the bulletin's
  list. `ThatSeoAgent_Bulletin::facts()` and `compose()` are gone; the
  bulletin reads the same as before.
- **One content check, whoever runs it.** The screen's check and the
  `scan-seo-issues` ability are the same check — the site's links read
  fresh first, each post audited, then the findings that need every post —
  in batches on the screen and in one go for the ability
  (`ThatSeoAgent_Audit::prepare()`, `post()`, `among()`).
  `ThatSeoAgent_Audit_Run` only keeps the queue and the last result.
- **`audit-post-seo` never reads the whole site for one post.** Without
  the site's links read in the last hour, orphan pages and broken links are
  listed as not measured instead of fetching the homepage and up to 40
  addresses; whether another page generates the same description is listed
  as not measured too, since only a whole content type can tell.
- **A catalog's primary category comes from its mapped category
  taxonomy**, even when its content type has `category` too, so the
  breadcrumb trail and the Product markup name the same one. The catalog
  says so through `thatseoagent_main_taxonomy`, at priority 5.
- **`ThatSeoAgent_Primary_Term` answers for the category everywhere it is
  named**: `named()` (the primary term, unless it is the default category),
  `path()` and `label()` ("Grúas > Articuladas"), used by the breadcrumb
  trail, article:section and the Product markup.
- **`ThatSeoAgent_Image` answers for every image a post publishes**: `own()`
  (its featured, gallery or content image), `all()` (featured and gallery,
  for the Product markup), `of()` and `object()`, the one ImageObject
  builder behind the primary image, the Product markup and the logos. An
  ImageObject now carries the image's alt text as `caption` wherever it
  has one.
- **Only image files with an absolute address are published** as og:image,
  the primary image, the Product markup's images and the logos; a PDF set
  as a featured image is left out everywhere, not only in the Product
  markup.
- **og:pin:media is the post's own image**, featured, gallery or in the
  content, as the primary image is; before, a post without a featured
  image had none.
- **`ThatSeoAgent_Indexing::is_listed()`**: whether one post may be listed
  for search engines and AI assistants, the check `listed_query_args()`
  makes in a query. llms.txt's trust pages use it, and so does IndexNow,
  which no longer announces password-protected posts.
- **`ThatSeoAgent_Loopback` is the only way the checks ask the site for its
  own pages**: the content check's homepage and addresses, the access check
  and robots.txt, the Markdown check. Every request, the ones sent at once
  included, passes through `http_request_args` and `pre_http_request`, so a
  filter can see or answer it; before, the broken-link probe and the access
  check went around the WordPress HTTP API.
- **The site identity reaches the markup directly**: `ThatSeoAgent_Identity`
  builds the Person node, the Organization's details and the X handle, and
  the schema and the meta tags ask for them; `thatseoagent_person_schema`,
  `thatseoagent_organization_schema`, `thatseoagent_primary_entity` and
  `thatseoagent_twitter_handle` receive the identity's answer instead of an
  empty default, and still have the last word.
- **`ThatSeoAgent_Default_Author` answers for the default author**:
  `defaults()`, `credited()` and `is_unattributed()`. A post is
  unattributed when it has no author, one that no longer exists, or one
  without a display name — the same for the markup and the bulletin's
  count.
- **The Markdown cache is keyed by the plugin's version**
  (`thatseoagent_md_{plugin}_{version}_{post_id}`), so an update that
  changes what the Markdown says is served at once, not after the copies
  cached before it expire.
- **A post's JSON-LD graph can be built from anywhere**:
  `ThatSeoAgent_Schema::for_post( $post )` returns the graph its page's head
  prints, node for node, without a front-end request, and
  `ThatSeoAgent_Breadcrumbs::for_post( $post )` its breadcrumb trail. The
  head prints what they return. `thatseoagent_webpage_schema`,
  `thatseoagent_breadcrumb_schema`, `thatseoagent_breadcrumb_trail` and
  `thatseoagent_schema_graph` receive the post (`null` on a listing).
- **The `get-post-seo` and `update-post-seo` abilities return the post's
  JSON-LD** in `schema`, so an agent sees the markup without requesting
  the page; empty while another SEO plugin is active.
- **The score's colours come from the server**: each row of the content
  check carries its level (`ThatSeoAgent_Audit::level_for_score()`).

### Fixed
- **`scan-seo-issues` scored differently from the screen's content check**:
  it never flagged generated descriptions two pages share, and it measured
  links against a graph up to an hour old.
- **"Uncategorized" left the breadcrumb trail**: a post filed only under
  the default category went out as Home › Uncategorized › Post, while
  article:section already left it out.
- **The Markdown version of a post kept out of search no longer contradicts
  its page**: asked for at the post's own URL it says `X-Robots-Tag:
  noindex`, as the HTML's robots meta does (the `thatseoagent_noindex`
  filter included), and neither it nor the `.md` URL sends a canonical,
  which the HTML leaves out too.
- **Nothing that is fine is painted red.** The content check's progress
  bar used the brand's red accent, and an observation in order a black bar:
  both are green now. The settings' "saving" dot is grey, the Markdown
  check's dot follows its verdict (red, yellow, green or grey), and a
  robots.txt answering a 4xx is yellow, a 5xx red, as the bulletin rates
  them.
- **The screen's red is the logo's alone.** Filled buttons, the active
  section's icon, links (told apart by their underline), the focus ring,
  checked boxes and radios, the caret and the text selection are ink: a
  red button or link beside a warning read as one more thing wrong.
- **The blog's posts page** no longer takes the homepage's title and
  description, and its own SEO fields now reach its `<title>` and meta
  description; without a description of its own it says the tagline, as
  before.

### Removed
- **The `thatseoagent_custom_description` filter.** Use
  `thatseoagent_description`, which receives the post.
- **`seo_title` in the Markdown version's frontmatter**, the SEO field as
  written and most often empty. `title` is the post's name, `description`
  the one the page publishes.
- **`featured_image` and `featured_image_alt` in the Markdown version's
  frontmatter**, now `image` and `image_alt`: the post's own image, the one
  its markup names, featured or not.
- **`ThatSeoAgent_Identity_Applier::filter_default_image()`**: the default
  sharing image was already read before the `thatseoagent_default_image`
  filter runs.
- **`ThatSeoAgent_Identity_Applier`** and
  **`ThatSeoAgent_Schema::get_publisher_defaults()`** (now
  `ThatSeoAgent_Default_Author::defaults()`).
- **`ThatSeoAgent_Homepage_Applier`** and
  **`ThatSeoAgent_Duplicates::effective_title()`**: the title and the
  description read the homepage settings themselves, and
  `ThatSeoAgent_Title::for_post()` is the search title.

## [2.6.0] - Unreleased

### Added
- **A bulletin warning when published posts have no author** — none
  assigned, or a user that no longer exists — while no default author is
  set: they are credited to the site itself. It leads to the posts list;
  the detail names the default author as the other way out.
- **AboutPage and ContactPage**: the about and contact pages the bulletin
  finds are marked up with those WebPage subtypes instead of a plain
  WebPage.

### Fixed
- **Content check: choosing a content type never checked failed with "The
  response is not a valid JSON response."** `GET /thatseoagent/v1/audit`
  answered null for it, which the REST server sends as an empty body. It now
  answers `{ "last": … }`, `last` being null when there is no check yet.
- **A page left open across an update of the plugin no longer runs the old
  script against the new server.** Each view the router fetches carries the
  version of the screen's script and styles; when it differs from the one
  the page loaded, the link loads as a whole page.

- **No doubled rules at the end of a list**: the last Settings section, the
  last product row and the last crawler of each AI crawlers group no longer
  draw a bottom rule against the separator that follows.

### Changed
- **The screen moves between its views without reloading.** A link to
  another view — the navigation, a warning's action, the products' pages —
  fetches that view alone (the `X-ThatSeoAgent-View` header; the server
  answers with the screen, its title and the bulletin, as JSON) and swaps it
  in: history, the title and the sidebar follow, the page scrolls to the
  link's anchor, and a view with unsaved settings asks before it goes.
  Anything unexpected falls back to loading the page, and without
  JavaScript every link works as before.
- **Shorter lists**: the products report shows 20 per page (was 50), and
  the content check pages its results 20 at a time.
- **Each Products tab is its own list** — All, Need attention, Complete —
  filtered over the whole catalog on the server (`&state=`), with its own
  pages and counts that stay the same from page to page. They used to
  filter only the page on screen, so the counts changed as you paged. Each
  entry's state is validated once for the catalog and cached with the
  summary (`ThatSeoAgent_Product_Report::statuses()`); only the rows shown
  are validated again for their issues.
- **Settings → AI crawlers: each group lists its own crawlers.** Under a
  group's Allow and Block, each of its crawlers has a three-way choice — as
  the group, allow, block — and the first names what the group does now,
  following it as it changes. The separate "Choose crawler by crawler" list
  is gone; the saved setting is the same.
- **Settings → Caches and CDN**, a section of its own for what a cache in
  front of WordPress needs so that agents asking for Markdown get it. The
  .htaccess rule for page cache plugins moved here from AI index, and is
  shown on every server: where .htaccess rewrites are not applied (nginx),
  with a notice that it does nothing there and its lines to paste on an
  Apache server; its buttons still appear only where it works. Beside it,
  the Cloudflare steps — keep one copy per format with Vary on Accept, or a
  Cache Rule that bypasses the cache for requests asking for Markdown —
  with links to Cloudflare's documentation. The Markdown check stays in AI
  index, and its advice now points to the section.
- **The Markdown check tells when Cloudflare answers with its own
  conversion.** Cloudflare's Markdown for Agents stamps what it converts
  with `x-markdown-tokens`; when that is what agents get, the check says so
  — Markdown made from the theme's page, without the author, dates, content
  type and SEO title of ThatSeoAgent's — and how to turn it off in
  Cloudflare.
- **The screen wears the That SEO Agent brand**, the one the MCP server
  and the website share: warm paper (`#f8f5f1`), white cells, hairline
  rules, square corners and no shadow; Deep Ōtan Red (`#c4331a`) as the one
  accent, for links, focus, the active mark and the filled button; the brand
  mark beside the name in the sidebar. Space Grotesk for sentences and Space
  Mono — uppercase, letter-spaced — for labels, numbers, the menu and the
  buttons, both self-hosted (`pnpm run vendor:fonts`); Public Sans is gone.
  The condition is no longer painted in its warning color: it sits in a
  white cell beside the warning scale, and the levels use the brand's
  status tones. The level's name is written only in the sidebar and the
  scale; in the warnings list and the products report its square shows
  it, and screen readers still hear the name. The Night edition is the report's warm espresso.
- The Article post types filter, `thatseoagent_article_post_types`, is read
  through `ThatSeoAgent_Schema::article_post_types()`.

## [2.5.0] - 2026-09-23

### Added
- **Crawl cleanup** (`ThatSeoAgent_Crawl_Cleanup`). Always: the shortlink
  (in the head and the `Link` header), the RSD and WLW links and the
  generator tag (pages and feeds) are left out, and comment feeds are sent
  with `X-Robots-Tag: noindex, follow`. Optional, off by default, in
  Settings → Crawl cleanup: **only the main feed** — the other feeds are no
  longer announced and redirect (301) to the page they follow — and
  **filter spam searches** — searches with emoji and other symbols,
  full-width punctuation, messenger handles or over 100 characters redirect
  to the homepage; accented letters and punctuation in any language pass.
  Filter `thatseoagent_spam_search`.
- **The catalog as JSON Lines** (`ThatSeoAgent_Catalog_Feed`):
  `/catalog.jsonl`, every catalog entry's Product markup — the node its page
  carries — one complete document per line, 100 per page
  (`?page=2`, announced with `Link: rel="next"`), `noindex`, cached until a
  catalog entry or the catalog settings change. Entries kept out of search
  or behind a password are left out. Linked from llms.txt and listed in
  "What the site publishes". Only while a catalog is set up.
- **llms.txt opens with "About the site"**: the about, contact and privacy
  pages the bulletin finds, before any other section and not repeated under
  their post type. llms-full.txt keeps them.
- **llms.txt lists a catalog's 20 newest products**, then "All N products"
  linking to `/catalog.jsonl`, which then leaves the Optional section. Filter
  `thatseoagent_llms_txt_catalog_limit`. On this kind of site the file goes
  from over a hundred links to a few dozen.
- **Two health checks in the bulletin**: permalinks without `%postname%`
  (numbers and dates, not words) turn the Permalinks observation yellow,
  with a warning that says changing them on an established site moves every
  post's address; and WordPress's default tagline ("Just another WordPress
  site", in the install's language) raises a warning while the homepage has
  no description of its own, since it is then what search results and
  llms.txt say the site is.
- **A bulletin warning when a new public content type is published**
  (`ThatSeoAgent_New_Types`): its pages are already in the sitemap, and only
  the site owner knows whether it lists products. The types that existed the
  first time the bulletin is read are known from the start; the warning goes
  once someone follows it to the catalog settings or saves them.
- **A bulletin warning while WordPress's sample post or page is still
  published** ("Hello world!", "Sample Page"), recognized by the slug
  WordPress gave them in English, the site's language and the languages
  sites are most often installed in (`ThatSeoAgent_Sample_Content`). The
  action opens the post when there is one, the list when there are two.
- **Site verification** (`ThatSeoAgent_Verification`, Settings → Site
  verification): the code — or the whole meta tag, as the service shows it —
  for Google Search Console, Bing Webmaster Tools, Yandex, Baidu and
  Pinterest, printed as its meta tag on the homepage only.

### Not done, on purpose
- Moving `utm_*` parameters out of the URL: the redirect loses the
  campaign in Google Analytics, and the canonical already leaves them out.
- Blocking search results in robots.txt: they carry `noindex`, which a
  crawler kept out by robots.txt never reads — the URL can stay in results
  from links to it. The spam search filter covers the abuse.
- A robots.txt rules hook: ThatSeoAgent keeps what other plugins add
  through core's `robots_txt` filter, which already is that hook.
- Yoast's llms.txt rules of dropping posts older than 12 months and putting
  "cornerstone" content first: evergreen content does not age out, and
  ThatSeoAgent has no cornerstone flag to read.
- Images in the sitemaps: Google uses them to discover images it cannot
  find by crawling the page, such as ones loaded by script. Images printed
  in the HTML are found anyway, and every sitemap request would have to read
  each post's content and gallery.

## [2.4.0] - 2026-09-23

### Changed
- **The image a page is shared with** (`ThatSeoAgent_Image`): the featured
  image, else a catalog entry's first gallery image, else the first image in
  the content (galleries included), else the default sharing image of the
  site identity, else the theme logo. Before, the theme logo came right
  after the featured image and the content was never looked at. Listings
  start at the default sharing image.
- **Size for sharing**: the largest of full, large and medium_large that
  weighs 2 MB or less — Facebook, WhatsApp and LinkedIn drop heavier ones.
  Filter `thatseoagent_og_image_size` to force one.
- `og:image:width` and `og:image:height` for every image, not only the
  featured one; `og:image:type`, and `og:image:alt` and `twitter:image:alt`
  from the media library's alt text.
- **The schema's primary image and the Article's image** come from the same
  chain, the post's own images only: the site's logo or default image says
  nothing about one article. The alt text becomes the ImageObject's caption.
- `thatseoagent_default_image` now runs before the theme logo rather than
  after it.
- **`og:type`** is `article` on every single page but the front page (it
  was only on posts), `profile` on author archives and `website` elsewhere.
  Filter `thatseoagent_og_type`.
- **`article:published_time`, `article:modified_time` and
  `article:section`** on dated content of any type — not pages, not catalog
  entries — and `article:modified_time` only when the post changed after it
  was published.
- **Primary category** (`ThatSeoAgent_Primary_Term`): the one category
  that names a post in `article:section`, the BreadcrumbList (with the
  categories above it) and a catalog entry's Product category. Chosen in the
  SEO meta box when the post has two or more (`_thatseoagent_primary_{taxonomy}`,
  in the REST API); otherwise the deepest assigned, leaving out
  "Uncategorized" when there is another. Each content type has a main
  taxonomy: `category` for posts, a catalog's mapped category, else one whose
  name says it is a category (filter `thatseoagent_main_taxonomy`).
  `%category%` in permalinks follows a category chosen by hand only, so no
  existing address moves on its own. `wp thatseoagent import` brings Yoast's
  and Rank Math's primary terms over.
- Breadcrumbs of custom post types now include their primary category, not
  only posts'.
- **Breadcrumbs for people** (`ThatSeoAgent_Breadcrumbs`): the trail the
  BreadcrumbList states, printed by `thatseoagent_breadcrumbs()` in a
  template, `[thatseoagent_breadcrumbs]` in content, or the Breadcrumbs block
  — a `nav` landmark with an ordered list, `aria-current` on the current page,
  the separator hidden from screen readers, and no styles of its own. The
  plugin prints them nowhere by itself. Filters `thatseoagent_breadcrumb_trail`
  (the trail, for the schema and the visible breadcrumbs alike) and
  `thatseoagent_breadcrumb_home`.
- **The schema graph is checked before it is printed**: a BreadcrumbList
  with a crumb missing its name, or a link before the last, is dropped whole
  along with the WebPage's reference to it, and references to nodes of the
  site the graph no longer contains — removed by a filter, or never built —
  are taken out. References to other sites are left alone.
- **Twitter no longer repeats Open Graph**: X reads og:title,
  og:description and og:image when its own tags are absent, so
  `twitter:title`, `twitter:description` and `twitter:image` are left out.
  `twitter:card`, `twitter:site` and `twitter:image:alt` stay. Filter
  `thatseoagent_twitter_repeat_open_graph` to print them again.

## [2.3.0] - 2026-09-23

The content check follows the rules of That SEO Agent's MCP server
(`docs/google-search-central-conformance.md` in that repository), so the
plugin and the MCP no longer tell the same site different things. The rule
they share: nothing Google does not ask for is reported as a problem.

### Changed
- **Each finding says where it comes from** (`source`): Google Search
  Central, accessibility guidelines (WCAG 2.2), or our own judgement. Only
  the first two lower the score; our own judgement is marked on the screen
  and costs nothing.
- **Titles and descriptions**: no minimums. Past 70 and 165 characters they
  are reported as "may be cut", not as too long — results trim by screen
  width, and Google sets no limit on descriptions. A post with no
  description at all, written or generated, is a warning.
- **Checks that could not run are listed apart** (`not_measured`) instead of
  failing: a post with almost no text in the editor, whose page is built by
  the theme, a page builder or custom fields, is not told it has no links.
  The blog's posts page is not checked, since its content is never shown.
- **The meta box and the homepage settings** use the same figures: the
  counters turn red past 70 and 165 characters, the description is no longer
  cut at 160 and the title at 70, and the hints no longer ask for 50–60 and
  150–160 characters.

### Added
- **Titles and descriptions two pages share**, which Google asks to be
  distinct: titles as they show in results (the SEO title, or the post title
  with the site name) and written descriptions are compared across every
  searchable page of the site in one query; generated descriptions, among
  the pages of each content check when it ends — the last step now sends the
  whole list as stored. Pages kept out of search or behind a password do not
  count. `seo` in the `audit-post-seo` ability gives the title and
  description the page shows (`ThatSeoAgent_Duplicates`).
- **Orphan pages and broken links** (`ThatSeoAgent_Links`), as our own
  judgement in the content check: pages nothing on the site links to, and
  links to addresses of the site that answer 404 or 410. The site's links
  are read from every searchable page's content, the menus and Navigation
  blocks, and the homepage as the server renders it — the header and footer
  a theme hard-codes included. Addresses the site does not recognize are
  asked for with a HEAD request, 40 at most per build; the rest are listed
  as not measured. Built when a check starts or an agent audits a page, and
  kept an hour or until a post or menu changes. Only pages and content types
  without listings can be orphans.
- **A bulletin warning when the site's navigation links to pages that do
  not exist**, once the links have been read.
- **Links search engines cannot follow**: an `<a>` that navigates with a
  click handler or a router attribute, an `href` on a span, div, li or
  button. Google follows `<a href>` only.
- **Headings**, as accessibility: an H1 inside the content, and a level
  skipped between two headings of the content. Google does not rank on
  either.
- **Kept out of search**: posts marked noindex are listed, so none is hidden
  by accident.
- `uncrawlable_links` in the audit's stats; `source` and `not_measured` in
  the `audit-post-seo` ability.
- **`structure` in the `audit-post-seo` ability**: how the post is built, as
  facts with no verdict — headings in order, lists, tables, quotes, Details
  blocks, code blocks, figures and percentages, paragraphs and words, the
  first 150 words, the dates, the declared language and the Markdown URL —
  for an agent reviewing the site to judge (`ThatSeoAgent_Structure`). No
  "citability" score: no AI provider publishes how it chooses what to cite.
- **Content negotiation for the Markdown version**: a post's own URL answers
  `Accept: text/markdown` with the same Markdown as its `.md` URL, with
  `Content-Location` pointing there and no `noindex`. Browsers and wildcard
  `Accept` headers keep getting the HTML. Every response of a post with a
  Markdown version says `Vary: Accept`; the Markdown one defines
  `DONOTCACHEPAGE` and is `Cache-Control: private`, so page caches keyed on
  the URL do not serve it to browsers. Filter
  `thatseoagent_markdown_negotiation`.
- **Link headers** on HTML pages: `rel="alternate"; type="text/markdown"`
  pointing at the post's `.md` URL (same filter as the `<link>` tag,
  `thatseoagent_markdown_alternate_link`), and `rel="sitemap"` pointing at
  `/sitemap.xml` on every page while ThatSeoAgent serves the sitemaps
  (filter `thatseoagent_sitemap_link_header`). Not on feeds, robots.txt,
  the sitemaps or the Markdown responses.
- **llms-full.txt**: the full text of the pages llms.txt lists that have a
  Markdown version, in its order — title, URL and Markdown of each, without
  the frontmatter — so an agent reads the site in one request. Built from
  the per-post Markdown cache, cached until a post or the site identity
  changes, and served and switched off with llms.txt, which now links to it
  under `## Optional`. Stops before 2 MB between two pages and says how many
  did not fit (filter `thatseoagent_llms_full_max_bytes`; the file itself,
  `thatseoagent_llms_full_txt`). Listed in "What the site publishes".
- **Author profile** (`ThatSeoAgent_Author_Profile`): a job title and
  "Profiles elsewhere" (one address per line) in the user profile screen,
  for people who can publish. They become `jobTitle` and `sameAs` on the
  author's Person node, next to the profile's website. Addresses on the site
  itself, and lines that are not addresses, are left out. User meta
  `_thatseoagent_job_title` and `_thatseoagent_profiles`, in the REST API
  for people who may edit the user; deleted with the plugin.
- **Trust pages** in the bulletin: an observation of how many of the about,
  contact and privacy pages the site has, and one yellow warning naming the
  missing ones, leading to Settings → Privacy when the privacy page is among
  them and to a new page otherwise. Found from inside the site: the privacy
  page WordPress has assigned, a published page whose slug names it in the
  languages That SEO Agent's MCP reads (the same lists; filter
  `thatseoagent_trust_page_slugs`), or a menu link to one — a `mailto:`
  counts as contact. Not called a ranking factor: Google asks whether
  visitors can tell who is behind a site.
- The observation row is three columns wide, nine on wide screens, so the
  nine observations leave no cell on its own.
- **Markdown check** (AI index → Markdown for agents): asks one post from
  outside, first as an agent and then as a browser, and says what each got.
  When a cache answered, it names the layer from the response headers
  (Cloudflare, LiteSpeed, Varnish, an Nginx cache, a page cache plugin) and
  what to change there. On demand only (`POST /thatseoagent/v1/markdown/check`).
- **An .htaccess rule for page caches**, on Apache: written at the top of the
  file when asked (`POST /thatseoagent/v1/markdown/htaccess`), it sends
  requests asking for Markdown to WordPress with `[END]`, before a cache
  plugin can serve its stored HTML. The screen says when another plugin's
  block has since been written above it. Removed with `DELETE` on the same
  route, and when the plugin is deleted.

### Removed
- **Word count as a finding** ("thin" under 300 words, "short" under 800):
  Google says length alone does not matter for ranking. The count is still
  in the stats.
- **"No H2 headings" and "No images"**, which no Google guideline asks for.
- **"No internal links" as a cost**: still reported, as our own judgement.

## [2.2.0] - 2026-09-23

What search engines may index, answered in one place
(`ThatSeoAgent_Indexing`) and read by the robots meta, the canonical, the
sitemaps, llms.txt and IndexNow.

### Added
- **Robots meta**, through core's `wp_robots` filter so it merges with what
  WordPress and other plugins set. Indexable pages get
  `max-snippet:-1, max-video-preview:-1, max-image-preview:large`; search
  results, the 404 page, private posts and `?replytocom=` links get
  `noindex, follow`. Filter `thatseoagent_noindex`.
- **Keep out of search results**, a box in the SEO meta box (post meta
  `_thatseoagent_noindex`, in the REST API): `noindex` on the page, and the
  post leaves the sitemaps and llms.txt. The `get-post-seo` and
  `update-post-seo` abilities read and write it as `noindex`, and
  `wp thatseoagent import` brings it over from Yoast SEO, Rank Math and
  All in One SEO.
- **`rel="prev"` and `rel="next"`** on paginated listings and on posts split
  with `<!--nextpage-->`. Filter `thatseoagent_adjacent_links`.
- **Attachment pages redirect to their file** (301), as WordPress does on
  sites installed since 6.4. Filter `thatseoagent_redirect_attachment_pages`.
- A **Kept out of search** reading on the dashboard.

### Changed
- **Each page of a paginated listing is its own canonical.** Page 2 of a
  category declared page 1 as its canonical, telling search engines it was
  a duplicate and hiding the older posts it links to. Posts split with
  `<!--nextpage-->` use core's `wp_get_canonical_url()`, which counts the
  page. Date archives get a canonical too.
- **No canonical on a page kept out of search**: the two directives
  contradict each other.
- **og:url is the canonical** on every view. On listings it was the
  requested URL, query string included (`?utm_source=`).
- **Sitemaps leave out password-protected posts and posts kept out of
  search**, and the index only announces chunks that have URLs in them.
- **llms.txt leaves out posts kept out of search**, and is rebuilt when a
  post's SEO fields change through the REST API or an ability, which write
  them after `save_post` has fired.
- **IndexNow** skips posts kept out of search, checked when the queued
  submission runs.

## [2.1.0] - 2026-09-23

### Added
- **AI crawlers** (`admin.php?page=thatseoagent&view=crawlers`): for 16 AI
  crawlers and the three search engine crawlers, whether the served
  robots.txt lets them read the site and which rule decides it, read as
  crawlers do (RFC 9309: most specific group, longest match, `*` and `$`).
  **Check access now** requests the homepage as each crawler, all at once,
  to catch a firewall or CDN turning it away, and checks that robots.txt
  itself answers 200 — crawlers ignore a robots.txt served with a 404,
  however right its contents (`POST /thatseoagent/v1/crawlers/probe`).
- **Rules per group** in Settings → AI crawlers: allow or block search and
  answers, visits asked for by a person, and training, with exceptions per
  crawler (option `thatseoagent_ai_crawlers`). Blocking adds a single
  `Disallow: /` group to the virtual robots.txt; allowing adds nothing, so
  allowed crawlers keep following the `*` rules. Nothing is written by
  default or on a site that discourages search engines. With a physical
  robots.txt the screen gives the lines to paste.
- **An AI crawlers observation** in the bulletin, with an orange warning
  when robots.txt keeps search crawlers out and yellow ones when a physical
  robots.txt ignores the chosen rules or another plugin (AEO God Mode) also
  writes crawler rules. Blocking training never raises one.
- Filter `thatseoagent_ai_crawlers` to add or regroup crawlers.

### Changed
- WordPress's own `/wp-sitemap.xml` is switched off while ThatSeoAgent
  serves its sitemaps.
- Internal query vars finish the rename: `thatseoagent_sitemap`,
  `thatseoagent_cpt`, `thatseoagent_llms`, `thatseoagent_markdown`.
- The observation row wraps to four columns until the screen fits eight.

## [2.0.0] - 2026-09-23

Lean SEO is now **ThatSeoAgent**, by Angel Cruz. Credits to the original
Lean SEO, by Sarai Chinwag for Extra Chill, are in the README and LICENSE.

### Changed
- **Every name**: the plugin folder and file (`thatseoagent/thatseoagent.php`),
  the text domain (`thatseoagent`), classes (`ThatSeoAgent_*`), constants
  (`THATSEOAGENT_*`), actions and filters (`thatseoagent_*`), options and
  post meta (`thatseoagent_*`, `_thatseoagent_title`,
  `_thatseoagent_description`), the admin page (`admin.php?page=thatseoagent`),
  the REST namespace (`thatseoagent/v1`), the WP-CLI command
  (`wp thatseoagent`) and the abilities (`thatseoagent/*`).
- **No migration**: settings, SEO fields and caches stored by Lean SEO are
  not read. See `docs/adr/0002`.
- The original Lean SEO counts as another SEO plugin: with it active,
  ThatSeoAgent steps aside instead of printing every tag twice.
- A `LICENSE` file carries the GPL text, the original copyright and the
  bundled components' licenses.

### Removed
- The redirects from the pre-1.17.0 admin URLs.
- The fallback to the `sarai_chinwag_indexnow_key` theme option.

## Before 2.0.0: Lean SEO

The entries below are the history of Lean SEO, under its own names.

## [1.20.0] - 2026-09-23

Internal reorganization. Every page, admin view, sitemap, llms.txt, Markdown
page and WP-CLI command produces the same output as in 1.19.0.

### Changed
- **`Lean_SEO_Bulletin`** (new `includes/bulletin/`) owns the bulletin,
  taken out of `Lean_SEO_App`. It works in two steps: `facts()` asks each
  owning module one question, and `compose( $facts )` applies the rules
  without touching WordPress, so they can be checked with facts written by
  hand. Callers use `get()`; `levels()` and `level_for_status()` carry the
  warning scale.
- **A warning's action names a destination** (`reading`, `permalinks`,
  `plugins`, `identity`, `homepage`, `products`) instead of a URL;
  `Lean_SEO_App::action_url()` knows where each one lives. The level colors
  moved to `Lean_SEO_App::level_classes()`: the bulletin knows nothing
  about the screen.
- **`Lean_SEO_Identity::is_recognizable()`** (a logo, a description or
  social profiles) and **`Lean_SEO_Homepage::has_description()`** answer
  what the bulletin used to read from their settings itself.
- **`Lean_SEO_Readings`** holds the dashboard counts and the public files
  served (`counts()`, `published()`, were `Lean_SEO_App::stats()` and
  `resources()`). **`Lean_SEO_Icons`** holds the inline icons (`the()`,
  `get()`).
- `GET /lean-seo/v1/bulletin` no longer includes the `square` and `bar`
  class names; the screen's script paints from the level and state alone,
  as it already did.
- **Each module declares its own option**: `setting()` next to its
  `OPTION_KEY` returns the type, sanitizer, default and REST schema, and
  `Lean_SEO_Settings` only gathers and registers them. Adding an option
  touches one file. `lean_seo_schema` is now
  `Lean_SEO_Default_Author::OPTION_KEY`.
- **`uninstall.php` asks instead of copying**: the options come from
  `Lean_SEO_Settings::definitions()` and the owners' constants
  (`Lean_SEO::REWRITE_VERSION_OPTION`, `Lean_SEO_IndexNow::CRON_HOOK`, …),
  so a new option cannot be left behind. It deletes exactly what it did
  before.
- **`Lean_SEO_Memo`** holds every per-request cache: rendered content,
  generated descriptions, the Person node, taxonomy lastmods, the
  bulletin. `Lean_SEO_Memo::forget_post()` replaces
  `Lean_SEO_Content::forget()` and `Lean_SEO_Description::forget()` (and
  with them the Content ↔ Description cycle); `reset()` empties it, which
  no static variable inside a function allowed.
- `docs/adr/0001` records why class names keep the `Lean_SEO_` prefix.

## [1.19.0] - 2026-09-23

Internal reorganization. No behaviour changes: every page head, sitemap,
robots.txt, llms.txt, Markdown page, admin view, REST response, ability and
WP-CLI command produces the same output as in 1.18.0.

### Changed
- **One class per file, grouped by concept** under `includes/`: `content/`,
  `head/`, `site/`, `catalog/`, `sitemap/`, `markdown/`, `audit/`, `admin/`,
  `rest/`, `tooling/`, `integrations/`. Files that held several classes
  (the REST controllers, the identity and homepage appliers, the FAQ
  section) are split.
- **An autoloader** (`includes/autoload.php`, an explicit class map)
  replaces every `require_once` of the plugin's own classes; nothing
  depends on the order files are read in any more. `uninstall.php` uses it
  too.
- **`Lean_SEO` is the composition root**: it calls each module's
  `register()`. Its seven forwarding methods are gone; the robots.txt
  filter moved to `Lean_SEO_Robots`, the document-title filters to
  `Lean_SEO_Title`, the sitemap query vars and redirect exemption to
  `Lean_SEO_Sitemap`.
- **`Lean_SEO_Admin` is removed**, its jobs given to their owners:
  `Lean_SEO_Post_Seo::post_types()` (was `get_meta_box_post_types()`),
  `Lean_SEO_App::PAGE_HOOK`, `Lean_SEO_Default_Author`,
  `Lean_SEO_Meta_Box`, `Lean_SEO_Bulk_Descriptions`.
- **`Lean_SEO_Product_Admin` is split**: `Lean_SEO_Product_Report` (the
  catalog summary and per-product report) and `Lean_SEO_Product_Settings`
  (the settings section).
- Class names are unchanged, so code calling the documented ones
  (`Lean_SEO_Content`, `Lean_SEO_Markdown_Cache`, …) keeps working. The
  removed and moved methods above were never documented.
- `CONTEXT.md` records the plugin's vocabulary.

## [1.18.0] - 2026-09-22

The Lean SEO screen now does what it shows. Alpine.js adds the interactive
layer on top of the server-rendered views, which still work without it.

### Added
- **Content check runs.** Pick a content type and start it: pages are checked
  a few at a time over the REST API, with a progress bar, so it finishes on any
  hosting. It can be stopped midway. Results come in worst first, filterable
  between pages to improve and all pages, each with its score, its reasons and
  links to edit and view it. The last finished check of each content type is
  kept and shown on the next visit.
- **Settings save without a reload**, through `/wp/v2/settings` and the same
  sanitizers as the form. The save bar says when there are unsaved changes,
  while saving, when saved and what went wrong; leaving the page with unsaved
  changes asks first. An IndexNow key that fails validation is reported
  instead of silently kept. The sidebar's bulletin updates after saving.
- **The settings index follows the scroll**, marking the section in view.
- **Day / Night** switches at once and is remembered per user.
- **Products** can be filtered on the page: all, needing attention, complete.
- **AI index**: "Rebuild now" regenerates llms.txt and refreshes the preview.
- REST API `lean-seo/v1`: `GET /bulletin`, `POST /preferences`,
  `GET /audit`, `POST /audit/runs`, `POST|DELETE /audit/runs/{token}`,
  `POST /llms`. Administrators only; failures are `WP_Error`s with a message
  meant for people, and responses are sent uncached.
- Alpine.js 3.17.4 ships in `assets/vendor/` (`pnpm run vendor:alpine`): the
  screen still makes no request outside the site.

### Changed
- The site identity no longer counts as set up merely because the settings
  were saved once: it needs a logo, a description or a social profile.
- The product catalog's field mapping opens and closes with Alpine instead of
  its own inline script.

## [1.17.0] - 2026-09-22

A new admin screen that reads like a weather bulletin: whether the site is
fine, in one plain sentence, on the color of its warning level.

### Added
- **Lean SEO screen** at `admin.php?page=lean-seo`, with its own navigation:
  - **Overview** — the site bulletin. A condition sentence on a field in the
    color of the warning level (none, yellow, orange, red — the European
    weather-warning scale), the one action to take next, the level scale, a
    row of observations (indexing, permalinks, other SEO plugins, identity,
    homepage description, product catalog, IndexNow), the warnings in force
    in plain words with a link to fix each, the readings, and the public
    files the site serves. Everything is computed from the site's current
    state on every load; optional features never raise a warning.
  - **Products** — the catalog's state, the split between complete products
    and products with gaps, how the catalog is read, and product by product
    what is missing.
  - **Content check** — the layout of the batched content check and the
    checks it runs. The check itself arrives with the Alpine.js layer.
  - **AI index** — whether llms.txt is served and why not, and a preview.
  - **Settings** — the existing settings as bulletin sections with an index.
  - The sidebar shows the current warning level and the observation row on
    every view.
- A Day (default) and a Night edition. The switch is shown; saving the choice
  arrives with the Alpine.js layer.
- Styles are Tailwind CSS v4, compiled with `pnpm run build:css` into
  `assets/build/admin.css`, which is committed: installing the plugin needs no
  Node. Loaded on this screen only, without Tailwind's global reset and with
  important utilities, so it neither restyles the rest of wp-admin nor is
  overridden by it.
- Public Sans (SIL Open Font License), self-hosted in `assets/fonts/`: the
  screen makes no request outside the site.
- The catalog summary behind the Overview and Products views is cached for an
  hour and dropped whenever a post, its terms or its meta change.

### Changed
- The Product schema report moved from its own admin page to the Products
  view; `admin.php?page=lean-seo-products` redirects there, and the settings
  URL from before 1.15.0 to the Settings view.

## [1.16.0] - 2026-09-22

Lean SEO stops being zero-configuration where configuration earns its keep:
product catalogs built on custom post types, an importer for other SEO
plugins, and llms.txt. Ideas taken from a review of AEO God Mode, rebuilt to
this plugin's rules — no remote service, no tables, nothing guessed.

### Added
- **Product schema for custom post type catalogs.** Lean SEO → Settings →
  Product catalogs lists every active public post type, whoever registered
  it. Tick the one that holds products and map where its data lives: brand
  and category taxonomies, a specifications meta key, a gallery meta key, and
  optional SKU, MPN and GTIN keys. Taxonomies and meta keys are detected from
  the site's own data and pre-selected by name.
  - Each entry gets a `Product` node — name, description, images, `Brand`,
    `category` as a "Parent > Child" path, `additionalProperty` as
    `PropertyValue`s — as the `WebPage`'s `mainEntity`, instead of an
    `Article`.
  - Every field is validated before output and dropped when invalid: images
    must be image attachments with absolute URLs, specification pairs need a
    name and a value, a GTIN needs 8/12/13/14 digits and a valid check digit.
    No `offers` is emitted without a price.
  - Lean SEO → Product schema reports, per product, what its markup is missing;
    `wp lean-seo validate-products` does the same for the whole catalog, and
    the SEO audit includes the same findings.
  - Filters: `lean_seo_product_post_types`, `lean_seo_product_schema`,
    `lean_seo_product_properties`.
- **`wp lean-seo import --from=yoast|rankmath|aioseo`.** Imports per-post
  titles and descriptions, and with `--identity` the site's Person or
  Organization, name, logo and social profiles. Template variables are
  resolved against each post; values with a variable that cannot be resolved
  are skipped, as are titles equal to what Lean SEO outputs anyway. Existing
  values are kept unless `--overwrite`. The other plugin's data is only read.
- **Stepping aside for other SEO plugins.** While Yoast SEO, Rank Math, All in
  One SEO, SEOPress, The SEO Framework or Squirrly is active, Lean SEO prints
  no meta tags, canonical or JSON-LD, serves no sitemaps or llms.txt and
  leaves robots.txt alone, and says so on the Dashboard, the Plugins screen
  and its own page. Two SEO plugins duplicated every tag. The
  `lean_seo_other_seo_plugin` filter overrides the detection.
- **`<link rel="alternate" type="text/markdown">`** on every page that has a
  Markdown version, so agents can find the `.md` URLs. Filter:
  `lean_seo_markdown_alternate_link`.
- **llms.txt**, generated from the posts served as Markdown and the product
  catalogs, linking to the `.md` version where there is one. Cached until a
  post or the site identity changes; can be switched off in the settings.
  Filters: `lean_seo_llms_txt_post_types`, `lean_seo_llms_txt_limit`,
  `lean_seo_llms_txt`.
- **FAQ schema from Details blocks.** A core Details block whose summary is a
  question counts as a question and answer, alongside question headings.
  Questions opening with "¿" are recognised.
- **"Generate meta description" bulk action** on the posts list of every post
  type with the SEO meta box. Saves the description the page already had, for
  posts without one of their own.
- **Graph.** Post authors are `Person` nodes with a stable `@id`, shared with
  a `ProfilePage` on their archive; `sameAs` comes from the profile's website
  field (filter: `lean_seo_author_schema`). `WebPage` gains `description`,
  `breadcrumb` and `primaryImageOfPage`. Archives and the blog index are
  `CollectionPage`s (filter: `lean_seo_listing_page_schema`). Breadcrumbs cover
  page parents, custom post type archives and term archives:
  Home › Products › Brand.
- `Lean_SEO_Settings` registers every option with a JSON schema and
  `show_in_rest`, so `/wp/v2/settings` can read and write them through the
  same sanitizers as the settings form.
- `Lean_SEO_Audit` holds the audit behind the abilities, WP-CLI and the admin.
  `scan-seo-issues` accepts a `post_type`.

### Fixed
- **Saving the settings page switched the site to a Person.** The identity
  radio pre-selected "Person" on a site that had never been configured, so
  saving the page for any other reason replaced the Organization schema.
  Likewise the fallback author fields rendered their defaults as values and
  saved them, crediting every authorless post to a Person named after the
  site. Both now show only what was saved.
- **Images with `alt=""` counted as missing alt text.** An empty alt is
  correct for decorative images. The audit now tells missing, decorative and
  described images apart, and notes a featured image without alt text.
- **The blog page declared the homepage as its canonical**, and post type
  archives had no canonical at all.
- A scan no longer keeps every rendered post in memory until it ends.
- Saving the IndexNow key through the REST API no longer calls a wp-admin-only
  function.

## [1.15.0] - 2026-09-22

### Changed
- **Lean SEO has its own admin menu.** The settings page moved from
  Settings → Lean SEO to a top-level **Lean SEO** menu, at
  `admin.php?page=lean-seo`. The old `options-general.php?page=lean-seo` URL
  redirects there.

## [1.14.0] - 2026-09-22

Every post and page is now also available as Markdown, for AI agents, at its
URL plus `.md`. Merged from the Content Negotiation for AI plugin.

### Added
- **Markdown for AI agents.** `/my-post.md` serves the post as Markdown with a
  YAML frontmatter: title, dates, author, permalink, excerpt, description,
  categories, tags and featured image. An agent reading it skips the
  navigation, sidebar, footer and scripts — typically 80–99% fewer tokens.
  - `Lean_SEO_Markdown_Endpoint` resolves the path with `url_to_postid()`, so
    it works with any permalink structure, and answers on `parse_request`,
    before the main query runs.
  - `Lean_SEO_Markdown` builds the Markdown from `Lean_SEO_Content::html()`
    and takes the description from `Lean_SEO_Description::for_post()`: the
    Markdown says what the page and its meta tags say.
  - `Lean_SEO_Markdown_Cache` keeps one transient per post, invalidated when
    the post, its terms or its meta change, and purged entirely when a term or
    an author is renamed.
  - Responses send `X-Robots-Tag: noindex` and a canonical `Link` header, so
    search engines keep indexing the HTML, plus `Last-Modified`; a matching
    `If-Modified-Since` gets `304`.
  - Filters: `lean_seo_markdown_post_types`, `lean_seo_markdown_frontmatter`,
    `lean_seo_markdown_html`, `lean_seo_markdown_include_custom_fields`,
    `lean_seo_markdown_custom_fields`, `lean_seo_markdown_cache_duration`.
- `league/html-to-markdown`, bundled in `vendor-prefixed/` under the
  `Lean_SEO\Dependencies\` namespace by Strauss, so a plugin loading its own
  copy cannot conflict. Installing the plugin still needs no Composer.

### Fixed
- **Deactivating left the sitemap routes behind.** The deactivation hook
  called `flush_rewrite_rules()`, but `init` had already registered the
  plugin's rules in that same request, so the flush saved them again and
  `/sitemap.xml` kept routing to a plugin that was no longer running. It now
  deletes the stored rules, and WordPress rebuilds them without the plugin on
  the next request.
- `Lean_SEO_Content::html()` did not run `wpautop()` on classic content, so a
  classic post's paragraphs were bare newlines. Descriptions and word counts
  are unchanged — plain text collapses whitespace anyway — but the HTML the
  audit and the FAQ extractor read now has its `<p>` tags, as on the page.
- The translation template was missing strings added since 1.9.0; the Spanish
  translation now covers "No post exists with that ID." too.

## [1.13.0] - 2026-09-21

Since Gutenberg, `post_content` is not the content — it is a serialization of
it. Three modules read it three different ways; now one module owns it.

### Added
- `Lean_SEO_Content` — the single answer to "what is this post's content?".
  `html()` renders blocks and shortcodes, `text()` reduces that to plain text,
  `word_count()` counts Unicode words. Rendering is memoised per post, so the
  block render callbacks run once per request no matter how many callers ask.

### Fixed
- **The SEO audit was wrong on every block-built post.** It ran its regexes
  over the raw serialization, where a dynamic block's headings, images and
  links do not exist yet. On this site's About page:

  ```
                  before        after
  words              0            137
  h2 headings        0              2
  images             0              1
  verdict      "thin content"   (accurate)
  ```

  It reported "no H2 headings", "no images" and "thin content" for a page
  with 2,359 characters of text, two headings and an image.
- `Article.wordCount` counted the serialization rather than the rendered text.
  Unchanged for classic content; correct now for block-built posts.

### Changed
- `Lean_SEO_Description`, `Lean_SEO_FAQ`, `Lean_SEO_Schema` and
  `Lean_SEO_Abilities` all read content through `Lean_SEO_Content`. Two
  character-for-character copies of `count_words()` and a second
  block-stripping implementation are gone with them.

## [1.12.3] - 2026-09-21

### Fixed
- **Pages built from dynamic blocks produced no description at all.**
  `Lean_SEO_Description::to_text()` stripped block delimiters before anything
  rendered them, but a self-closing dynamic block keeps its text inside the
  delimiter's own attributes:

      <!-- wp:acme/section {"content":"The actual words."} /-->

  Everything the block would have rendered went out with the comment, leaving
  zero characters, an empty description, and — where the site has no tagline
  to fall back on — no `<meta name="description">` on the page at all. On the
  site this was found on, all seven content pages were affected.

  `to_text()` now runs `do_blocks()` when the content has blocks, matching
  what `Lean_SEO_FAQ` already did. The two modules previously disagreed about
  what "the post's content" means.

- The generated description is memoised per post. Rendering blocks is the
  expensive part and the meta tags and the JSON-LD graph each ask for the
  description separately during a single request: 0.67 ms for a
  block-built page, now paid once instead of twice.

## [1.12.2] - 2026-09-21

### Fixed
- **The search preview showed the field's instruction text instead of the
  description.** The server rendered the real generated description into the
  preview, but the script ran `updatePreview()` on load and replaced it with
  the textarea's placeholder — "Leave blank to auto-generate from content..."
  — whenever the field was empty, which is the common case. The generated
  value now travels to the script in a `data-lean-seo-generated` attribute and
  is used as the fallback, so the preview shows what search results will
  actually show. Affected posts and pages as well as custom post types.

## [1.12.1] - 2026-09-21

### Fixed
- **The meta box rendered unstyled on custom post types.** 1.12.0 extended the
  meta box to every post type with an editing screen, but updated only two of
  the three places that resolve that list:
  `Lean_SEO_Admin::enqueue_meta_box_assets()` kept the old hardcoded
  `array( 'post', 'page' )` default, so `admin-meta-box.css` and
  `admin-meta-box.js` never loaded on a custom post type. The markup was
  there; the styling, the character counters and the live search preview were
  not. All three call sites now go through
  `Lean_SEO_Admin::get_meta_box_post_types()`.

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
