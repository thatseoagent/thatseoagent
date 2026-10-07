# AGENTS.md

ThatSeoAgent: a lightweight WordPress SEO plugin (meta tags, schema, sitemaps, llms.txt, Markdown per post, a "weather bulletin" admin screen, and 14 abilities, exposed as the MCP server `thatseoagent` when the Lean MCP plugin is active). PHP 8.4+ only.

Domain terms: see `GLOSSARY.md`. Decisions: `docs/adr/`. Design: `PRODUCT.md`, `DESIGN.md`. Changelog: `docs/CHANGELOG.md`.

## Commands

- `composer test`: Pest. Outside WordPress the WordPress/Http/Cli suites skip themselves; only Unit runs.
- `pnpm test:wordpress`: the WordPress, Http and Cli suites inside wp-env. Start with `pnpm env:start`, stop with `pnpm env:stop` as soon as you are done (don't leave it running or ask). Any `wp-env run` call needs `< /dev/null` or it hangs.
- `composer analyse` (PHPStan, baseline in `phpstan-baseline.neon`), `composer lint` (Pint check), `composer format` (Pint fix).
- `pnpm dev`: Tailwind watcher that rebuilds `assets/build/admin.css`. Run this yourself (in the background) when touching admin views; the compiled CSS is committed. `pnpm build:css` for a one-off minified build.
- `pnpm release`: production copy in `dist/thatseoagent/` (`scripts/release.mjs`). Only when asked.
- Third-party PHP deps are prefixed with Strauss into `vendor-prefixed/` (`composer prefix-namespaces`, runs after install/update).

## Code conventions

- One class per file under `includes/<concept>/`, classes `ThatSeoAgent_*`, prefixes `thatseoagent_` / `THATSEOAGENT_`, text domain `thatseoagent`, REST `thatseoagent/v1`, WP-CLI `wp thatseoagent`, CSS/Alpine prefix `tsa`.
- The autoloader map in `includes/autoload.php` is explicit: adding a class means adding its line there.
- Admin screen is an Alpine SPA (`assets/admin/app.js`); Alpine is vendored in `assets/vendor` (no CDN). Use `x-show.important` (Tailwind utilities are `!important`).
- Ability `permission_callback`s return bool; "not found" is answered by `execute`.
- Translations in `languages/` (es_ES, es_UY). When touching a .po, translate every untranslated string in it, not only the new ones (`msgattrib --untranslated` to list them).
