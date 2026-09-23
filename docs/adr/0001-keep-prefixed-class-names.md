# Keep the Lean_SEO_ prefixed class names instead of namespaces

_Superseded by [0002](0002-rename-to-thatseoagent.md): the prefix changed to `ThatSeoAgent_`. The reasoning against namespaces still holds._

The plugin's classes are named `Lean_SEO_*` and stay that way, although PHP namespaces (`LeanSEO\Content\Description`) would be the modern default. The names are public: the README and `docs/` tell site owners to call `Lean_SEO_Content::word_count()` and `Lean_SEO_Markdown_Cache::purge_all()` from their own filters, so renaming them breaks their code, and `class_alias` shims for every class would be permanent noise. The 1.19.0 reorganization therefore grouped the files into folders by concept and mapped each class to its file in `includes/autoload.php`, without touching a name.
