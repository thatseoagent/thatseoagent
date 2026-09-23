# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Three audiences share the same admin screen:

- **Agency team** (developers and marketers) who install the plugin on client sites, configure identity, product catalogs and integrations, and check SEO health across sites.
- **Non-technical clients**: business owners and editors of the sites the agency builds (for example a machinery catalog site). They open the screen occasionally, know little about SEO, and need to understand what is fine, what is not, and what to do next.
- **Public WordPress users** who install the plugin on their own, with no agency behind them.

The screen must be usable without SEO knowledge and without reading documentation, while giving the agency the detail it needs.

## Product Purpose

A lightweight SEO plugin for WordPress: meta tags, Open Graph, JSON-LD schema (including Product schema for custom post type catalogs), XML sitemaps, canonical URLs, per-post SEO fields, IndexNow, a Markdown version of every post for AI agents, and llms.txt. The admin screen shows the site's SEO state, what is missing, and where to fix it. Success: a user leaves knowing the site is in good shape or knowing the one thing to do next.

## Positioning

No upsells, no paid tier, no remote service, no external requests (IndexNow only when a key is set). Reports only what it can verify on the site itself, and says so when something cannot be checked. Steps aside automatically when another SEO plugin is active instead of fighting it.

## Operating Context

- Lives inside wp-admin, next to the standard WordPress menu and toolbar; must not restyle or break the rest of the admin.
- Used on laptops and desktops mostly; the admin also runs on tablets and phones.
- Also operated from WP-CLI (`wp lean-seo …`) and the Abilities API by agents; the screen and those tools share the same data and checks.
- Settings are WordPress options exposed through `/wp/v2/settings`; interactivity is planned with Alpine.js over the REST API.

## Capabilities and Constraints

- Server-rendered PHP templates in `includes/admin/views/`, styled with Tailwind CSS v4 compiled by `pnpm run build:css` into a committed stylesheet; installing the plugin requires no Node.
- No web fonts or other assets from external hosts.
- Source strings in English, translated through WordPress (`languages/`, Spanish shipped). Spanish strings run longer and must fit.
- PHP 7.4+, WordPress 7.1+.
- Open decision: the fork will get its own name, logo and voice; not decided yet. "Lean SEO" is the working name until then.

## Brand Commitments

- It is the agency's own fork; the original author is credited in the plugin header and README.
- Name and logo are undecided; do not invent a final brand name.

## Evidence on Hand

Real site data available on the local install (wordpress.test): 107 published posts, pages and products, a 93-entry product catalog (`producto` post type) with validation results, a generated llms.txt. No testimonials, benchmarks, customers or usage metrics exist; none may be fabricated.

## Product Principles

1. Tell people what to do next, not just what is wrong.
2. Only show what was actually checked; label anything unverified.
3. Plain language first, technical detail on demand.
4. Stay light: no upsells, no external calls, no extra weight on the front end.
5. Belong in wp-admin: native controls and behavior, nothing that fights the rest of the admin.
