# Lean SEO

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
One fact checked about the site (indexing allowed, permalinks, other SEO plugins, identity, homepage description, product catalog, IndexNow), with its value in a word or two.
_Avoid_: check, metric, test

**Stepping aside**:
What Lean SEO does while another SEO plugin is active: it prints nothing in the page head, serves no sitemaps or AI index, and leaves robots.txt alone.
_Avoid_: conflict mode, disabled, compatibility mode

### What a post says

**SEO fields**:
A post's own title and description for search results, written by a person. Either may be empty.
_Avoid_: meta, SEO settings, overrides

**Generated description**:
The description a post gets from its own content when its SEO fields have none. What the page publishes and what the editor preview shows are the same generated description.
_Avoid_: auto description, excerpt, fallback

**Markdown version**:
A post's content as Markdown with a frontmatter, published at the post's URL plus `.md`, for AI agents.
_Avoid_: export, API, feed

**AI index**:
The site's llms.txt: a list of its pages, each linking to its Markdown version where there is one.
_Avoid_: llms file, AI sitemap

### Who the site is

**Site identity**:
Whether the site belongs to a person or an organization, and how to recognize it: name, description, logo, social profiles.
_Avoid_: publisher, brand, organization settings

**Default author**:
Who is credited on posts that have no author assigned. When unset, the site identity is.
_Avoid_: fallback author, publisher

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
A check of every published post of one content type, a few at a time, giving each a score out of 100 and the reasons. The last finished check of each content type is kept.
_Avoid_: scan, crawl, SEO audit

### The screen

**Edition**:
The Lean SEO screen's color scheme for one person: Day (the default) or Night.
_Avoid_: theme (that is the WordPress theme), dark mode
