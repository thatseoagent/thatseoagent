# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Three audiences share the same admin screen:

- **Developers and marketers** who build and run sites for others: they install the plugin, configure identity and integrations, declare product catalogs from the site's theme or a plugin, and check SEO health across sites.
- **Non-technical site owners and editors**: business owners and editors of those sites (for example a product catalog site). They open the screen occasionally, know little about SEO, and need to understand what is fine, what is not, and what to do next.
- **WordPress users** who install the plugin on their own site and run it themselves.

The screen must be usable without SEO knowledge and without reading documentation, while giving developers and marketers the detail they need.

## Product Purpose

A lightweight SEO plugin for WordPress: meta tags, Open Graph, JSON-LD schema (including Product schema for custom post type catalogs), XML sitemaps, canonical URLs, per-post SEO fields, IndexNow, Google Analytics and Google Ads tags, a Markdown version of every post for AI agents, and llms.txt. The admin screen shows the site's SEO state, what is missing, and where to fix it. Success: a user leaves knowing the site is in good shape or knowing the one thing to do next.

## Positioning

No upsells, no paid tier, no remote service, no external requests of its own (IndexNow only when a key is set; the Google tag, gtag.js, only when a Google Analytics or Google Ads ID is set). Reports only what it can verify on the site itself, and says so when something cannot be checked. Steps aside automatically when another SEO plugin is active instead of fighting it.

## Operating Context

- Lives inside wp-admin, next to the standard WordPress menu and toolbar; must not restyle or break the rest of the admin.
- Used on laptops and desktops mostly; the admin also runs on tablets and phones.
- Also operated from WP-CLI (`wp thatseoagent …`) and the Abilities API by agents; the screen and those tools share the same data and checks.
- Settings are WordPress options exposed through `/wp/v2/settings`; interactivity is built with Alpine.js (vendored in `assets/vendor`, components in `assets/admin/app.js`) over the plugin's REST API.

## Capabilities and Constraints

- Server-rendered PHP templates in `includes/admin/views/`, styled with Tailwind CSS v4 compiled by `pnpm run build:css` into a committed stylesheet; installing the plugin requires no Node.
- No web fonts or other assets from external hosts.
- Source strings in English, translated through WordPress (`languages/`, Spanish shipped). Spanish strings run longer and must fit.
- PHP 8.4+, WordPress 7.1+.
- The product is named ThatSeoAgent; its voice is still open.

## Brand Commitments

- An open-source plugin (GPL-2.0-or-later), grown from a fork of Lean SEO; the original author is credited in the README and LICENSE.
- The name is ThatSeoAgent; the brand mark is the Ōtan Red square holding the bot, in the screen's sidebar (see DESIGN.md). Do not invent another.

## Evidence on Hand

No testimonials, benchmarks, customers or usage metrics exist; none may be fabricated.

## Product Principles

1. Tell people what to do next, not just what is wrong.
2. Only show what was actually checked; label anything unverified.
3. Plain language first, technical detail on demand.
4. Stay light: no upsells, no external calls the site owner did not set up, no extra weight on the front end.
5. Belong in wp-admin: native controls and behavior, nothing that fights the rest of the admin.
