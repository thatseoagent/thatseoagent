# ThatSeoAgent

A WordPress SEO plugin that publishes what search engines and AI assistants read about a site, and tells the people running the site whether that is in good shape and what to do next.

## Language

### The site's state

**Bulletin**:
The site's SEO condition at this moment: one warning level, the warnings in force, and the observations behind them. Computed from the site as it is, never stored.
_Avoid_: dashboard, health score, report

**Warning level**:
How serious the bulletin is, on the European weather-warning scale: no warnings, yellow, orange, red. The level of the bulletin is the level of its most serious warning.
_Avoid_: severity, status, grade

**Warning**:
A problem in force that needs someone to act, stated in plain words with one action that fixes it. Optional features that are not set up never raise one.
_Avoid_: alert, error, issue

**Readings**:
What the overview shows beside the bulletin without judging it: how many posts carry their own SEO fields, and which public files the site is serving. Readings never raise a warning.
_Avoid_: stats, metrics, KPIs

**Observation**:
One fact checked about the site (indexing allowed, permalinks, other SEO plugins, identity, homepage description, trust pages, product catalog, AI crawlers, IndexNow), with its value in a word or two.
_Avoid_: check, metric, test

**Stepping aside**:
What ThatSeoAgent does while another SEO plugin is active: it prints nothing in the page head, serves no sitemaps or AI index, and leaves robots.txt alone.
_Avoid_: conflict mode, disabled, compatibility mode

### What a post says

**SEO fields**:
A post's own title and description for search results, written by a person, and whether it is kept out of search. Any may be empty.
_Avoid_: meta, SEO settings, overrides

**Kept out of search**:
A page search engines are told not to index (`noindex`): search results, the 404 page, private posts, and posts whose SEO fields say so. It prints no canonical and is listed in no sitemap and not in the AI index; it stays public to anyone with the link.
_Avoid_: hidden, blocked, deindexed

**Search title**:
The title a post shows in search results and in its browser tab: the one its SEO fields give (on the homepage, the homepage's own title first), or else its name followed by the site's. Distinct from the post's name, which is what the markup, the breadcrumb trail and the AI index call it.
_Avoid_: SEO title, meta title, document title

**Primary category**:
The one category that names a post where only one fits: search results, the breadcrumb, the product markup. Chosen in the SEO fields, or else the deepest one the post has.
_Avoid_: main category, primary term

**Generated description**:
The description a post gets from its own content when its SEO fields have none. What the page publishes and what the editor preview shows are the same generated description.
_Avoid_: auto description, excerpt, fallback

**Markdown version**:
A post's content as Markdown with a frontmatter, published at the post's URL plus `.md`, and at the post's own URL to a client that asks for Markdown, for AI agents.
_Avoid_: export, API, feed

**Markdown check**:
Asking one post from outside, on demand, first for its Markdown version and then as a browser, to see whether a cache between WordPress and its visitors undoes the negotiation.
_Avoid_: probe, test, scan

**AI index**:
The site's llms.txt: a list of its pages, each linking to its Markdown version where there is one; and llms-full.txt, the same pages' full text in one file.
_Avoid_: llms file, AI sitemap

### Who reads the site

**AI crawler**:
A program an AI company sends to read web pages, known by the name it gives in robots.txt (GPTBot, ClaudeBot…). Google's, Bing's and Apple's search crawlers are not AI crawlers here: they are never blocked from this plugin.
_Avoid_: bot, spider, agent

**Crawler group**:
What an AI crawler reads the site for: search and answers, a visit a person asked for, or training. The site owner allows or blocks a whole group, with exceptions crawler by crawler.
_Avoid_: category, type

**Crawler access**:
Whether an AI crawler may read the site according to the robots.txt the site serves, whatever the site owner chose; the two can differ when a physical robots.txt or another plugin decides.
_Avoid_: permission, status

**Access check**:
Requesting the homepage as each AI crawler, on demand, to see whether the server lets it through.
_Avoid_: probe, test, scan

### Who the site is

**Site identity**:
Whether the site belongs to a person or an organization, and how to recognize it: name, description, logo, social profiles.
_Avoid_: publisher, brand, organization settings

**Author profile**:
What a post's author is beyond a name, entered in their WordPress profile: a job title and their profiles on other sites. It becomes the author's `jobTitle` and `sameAs` in the markup.
_Avoid_: bio, author box

**Default author**:
Who is credited on posts that have no author assigned. When unset, the site identity is.
_Avoid_: fallback author, publisher

**Trust pages**:
The pages that say who runs the site, how to reach it and what it does with visitors' data: about, contact and privacy policy. Found by the privacy page WordPress has assigned, a published page whose address names it, or a menu link to one.
_Avoid_: legal pages, E-E-A-T pages

**Breadcrumb trail**:
Where a page sits in the site, from the homepage down: its primary category or parent pages, its archive. One trail per page, stated in the markup and printed wherever the theme places the breadcrumbs.
_Avoid_: path, navigation

### Product catalogs

**Catalog**:
A content type marked as listing products, with or without prices. Each of its published posts is a **catalog entry**.
_Avoid_: shop, store, product post type

**Mapping**:
Where a catalog keeps each product detail: which taxonomy is the brand and the category, and which fields hold the specifications, the gallery and the identifiers (SKU, MPN, GTIN).
_Avoid_: field settings, configuration

**Product markup**:
The schema.org Product description of a catalog entry. Every detail in it is validated first; one that fails is left out rather than published half-formed.
_Avoid_: product schema, rich snippet

**Complete** / **with gaps** / **not marked up**:
The three states of a catalog entry's product markup: nothing recommended is missing; something recommended is missing; there is no product markup at all.
_Avoid_: ok / warning / error (those are the issue severities below)

**Issue severity**:
How much a single finding about a post or product matters: error, warning or info. Distinct from the warning level, which belongs to the bulletin.
_Avoid_: level

### Checking content

**Content check**:
A check of every published post of one content type, a few at a time, giving each a score out of 100 and the reasons. It applies the same rules as That SEO Agent's MCP server, so the two never tell a site different things. The last finished check of each content type is kept.
_Avoid_: scan, crawl, SEO audit

**Finding source**:
Who asks for what a finding reports: Google Search Central, accessibility guidelines (WCAG 2.2), or our own judgement. Only the first two lower the score; our own judgement is marked as such and costs nothing.
_Avoid_: category, rule type

**Not measured**:
A check that could not run on a post — its text is not in the editor, or the page never shows its content — listed apart from the findings. It neither passes nor fails.
_Avoid_: skipped, failed, n/a

**Orphan page**:
A page no other part of the site links to: not the menus, not the header or footer the theme prints, not another page's text. Only pages and content types without listings can be one; posts and catalog entries always appear in an archive or a category.
_Avoid_: unlinked, isolated

**Broken link**:
A link to an address of the site that answers "not found" (404 or 410) when asked.
_Avoid_: dead link, 404 link

### The screen

**Edition**:
The ThatSeoAgent screen's color scheme for one person: Day (the default) or Night.
_Avoid_: theme (that is the WordPress theme), dark mode
