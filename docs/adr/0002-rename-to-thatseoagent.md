# Rename Lean SEO to ThatSeoAgent, with no data migration

Lean SEO became ThatSeoAgent in 2.0.0: the plugin had outgrown the fork it started from and is now its own product. Every identifier changed with the name: the plugin folder and main file, the text domain, the `Lean_SEO_*` classes (now `ThatSeoAgent_*`, still prefixed rather than namespaced, for the reasons in 0001), constants, hooks, filters, option names, post meta keys, the REST namespace, the WP-CLI command, the abilities and the screen's CSS and script names. A half-renamed plugin would have kept the old name alive in every filter people write against it.

No data is migrated. No site running the fork had content worth carrying over, so ThatSeoAgent starts with empty settings and ignores anything left under `lean_seo_*`; code written against the old names has to be updated by hand. If the original Lean SEO is active alongside it, ThatSeoAgent treats it as another SEO plugin and steps aside.
