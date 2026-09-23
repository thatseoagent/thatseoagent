---
name: Lean SEO admin
description: The site's SEO state read as a weather bulletin inside wp-admin.
colors:
  paper: "#f2f4f3"
  sheet: "#ffffff"
  column: "#e6eae9"
  rule: "#d2d9d7"
  rule-strong: "#a3aeac"
  ink: "#111d27"
  ink-2: "#44525f"
  ink-3: "#5f6c78"
  met-blue: "#0b5378"
  press: "#111d27"
  on-press: "#ffffff"
  level-clear: "#1d774a"
  level-yellow: "#f1c02b"
  level-orange: "#e5711a"
  level-red: "#c41f38"
  on-clear: "#ffffff"
  on-yellow: "#111d27"
  on-orange: "#111d27"
  on-red: "#ffffff"
  field-ink: "#111d27"
  night-paper: "#0e1b24"
  night-sheet: "#13242f"
  night-column: "#0a151d"
  night-rule: "#223844"
  night-rule-strong: "#3a5664"
  night-ink: "#e4ebee"
  night-ink-2: "#a8b6bf"
  night-ink-3: "#8b9aa4"
  night-met-blue: "#8ccaee"
  night-press: "#e4ebee"
  night-on-press: "#0e1b24"
  night-level-clear: "#238a57"
typography:
  condition:
    fontFamily: "Public Sans, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif"
    fontSize: "44px"
    fontWeight: 800
    lineHeight: 1.08
    letterSpacing: "-0.025em"
  page-title:
    fontFamily: "Public Sans, sans-serif"
    fontSize: "26px"
    fontWeight: 700
    lineHeight: 1.25
    letterSpacing: "-0.015em"
  section-title:
    fontFamily: "Public Sans, sans-serif"
    fontSize: "17px"
    fontWeight: 700
    lineHeight: 1.5
  reading:
    fontFamily: "Public Sans, sans-serif"
    fontSize: "22px"
    fontWeight: 700
    lineHeight: 1
    fontFeature: "tnum"
  item-title:
    fontFamily: "Public Sans, sans-serif"
    fontSize: "16px"
    fontWeight: 600
    lineHeight: 1.375
  body:
    fontFamily: "Public Sans, sans-serif"
    fontSize: "14px"
    fontWeight: 400
    lineHeight: 1.5
  small:
    fontFamily: "Public Sans, sans-serif"
    fontSize: "13px"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "Public Sans, sans-serif"
    fontSize: "12px"
    fontWeight: 600
    lineHeight: 1.5
  mono:
    fontFamily: "ui-monospace, SFMono-Regular, Menlo, Consolas, Liberation Mono, monospace"
    fontSize: "0.86em"
    fontWeight: 400
rounded:
  square: "1px"
  mark: "2px"
  control: "4px"
  sheet: "6px"
spacing:
  hairline: "1px"
  row: "14px"
  gutter: "20px"
  field-x: "40px"
  section: "48px"
components:
  button-press:
    backgroundColor: "{colors.press}"
    textColor: "{colors.on-press}"
    rounded: "{rounded.control}"
    padding: "0 1.1rem"
    height: "2.5rem"
  button-press-on-field:
    backgroundColor: "{colors.field-ink}"
    textColor: "{colors.on-press}"
    rounded: "{rounded.control}"
    padding: "0 1.1rem"
    height: "2.5rem"
  button-rule:
    backgroundColor: "transparent"
    textColor: "{colors.ink}"
    rounded: "{rounded.control}"
    padding: "0 0.85rem"
    height: "2.25rem"
  input:
    backgroundColor: "{colors.sheet}"
    textColor: "{colors.ink}"
    rounded: "{rounded.control}"
    padding: "0.5rem 0.75rem"
    height: "2.5rem"
  condition-field-yellow:
    backgroundColor: "{colors.level-yellow}"
    textColor: "{colors.on-yellow}"
    rounded: "{rounded.sheet}"
    padding: "40px"
  observation-cell:
    backgroundColor: "{colors.sheet}"
    textColor: "{colors.ink}"
    padding: "0 16px 14px"
  nav-item-active:
    backgroundColor: "{colors.sheet}"
    textColor: "{colors.ink}"
    rounded: "{rounded.control}"
    padding: "8px 12px"
  nav-item:
    backgroundColor: "transparent"
    textColor: "{colors.ink-2}"
    rounded: "{rounded.control}"
    padding: "8px 12px"
---

# Design System: Lean SEO admin

Scope: the plugin's wp-admin screen (Overview, Products, Content check, AI index, Settings). The plugin's front-end output has no visual design and is not covered.

## Overview

**Creative North Star: "The Weather Bulletin"**

The site gets a weather bulletin, not a dashboard. Each view opens with one plain condition sentence set on a flat, full-width field painted in its warning color, then the warnings in force, a row of observations, then the readings. The warning levels follow the European weather-warning scale (none, yellow, orange, red), which people read without knowing anything about SEO. Everything around the field is cool bulletin paper, ink and 1px rules, so the warning color is the only saturated thing on the screen and always means something.

Density is that of a printed service bulletin: sheets divided by hairline rules, not cards floating on a grey ground. Numbers are tabular. There is one filled control per surface, an ink "press" button, and every other action is ruled. Motion is a single event: one sweep of light across the observation row when the page loads, meaning "these were just checked".

The screen lives inside wp-admin and must not leak into it: no global reset, a reset scoped to `#lean-seo-app`, utilities marked important so unlayered wp-admin rules cannot override them. Two editions exist, Day (default) and Night (deep service blue, never neutral black), switched from the sidebar.

**Key Characteristics:**
- One condition sentence on a flat warning-colored field per view.
- European warning scale as the only saturated color.
- Cool paper, ink, service blue for links; 1px rules; small squares as markers.
- One filled ink control per surface.
- Tabular numerals everywhere numbers appear.
- A single load-time light sweep across the observation row.

## Colors

A cool, near-neutral bulletin palette with one service blue for interaction, and a four-step warning scale that carries all saturation.

### Primary
- **Service Blue** (met-blue): links, the active nav icon, focus outlines, input focus ring, checkbox and radio accent, caret, text selection tint (22% mix). Never a fill for buttons or fields. In Night it lifts to night-met-blue.

### Warning Scale
- **No Warnings Green** (level-clear): the "no warnings" field, published-state dots, the "complete" segment. Text on it is white (on-clear). Night uses a slightly lighter green (night-level-clear).
- **Yellow Warning** (level-yellow): "be aware". Text on it is ink (on-yellow).
- **Orange Warning** (level-orange): "be prepared". Text on it is ink (on-orange).
- **Red Warning** (level-red): "act now". Text on it is white (on-red).

Yellow, orange and red are identical in both editions: a warning field reads the same by day and by night.

### Neutral
- **Bulletin Paper** (paper): the page ground, also behind wp-admin's body on this screen.
- **Sheet White** (sheet): tables, observation cells, the sidebar's mini bulletin, the active nav item, form inputs.
- **Column Grey** (column): the sidebar.
- **Rule** (rule): every 1px divider and default border.
- **Strong Rule** (rule-strong): section-heading underlines, secondary button and input borders, inactive-state dots.
- **Ink** (ink): primary text, "OK" observation bars, and the press fill.
- **Ink 2** (ink-2): secondary text, descriptions, inactive nav items.
- **Ink 3** (ink-3): tertiary text, counts, metadata, placeholders, inactive icons.
- **Field Ink** (field-ink): the fixed ink used for the press button and scale dot on a warning field, whatever the edition.

### Named Rules
**The Only Saturation Rule.** The four warning levels are the only saturated colors. Service blue is for interaction only; nothing is decorative color.

**The Field Keeps Its Colors Rule.** On a warning field, the press button stays field-ink on white and text uses the level's own on-* color in both editions.

**The Night Edition Rule.** Night is deep service blue (night-paper), never neutral black; its surfaces are the same roles re-valued, not a separate palette.

## Typography

**Body and Display Font:** Public Sans, self-hosted variable woff2 (weights 100-900, latin and latin-ext), falling back to the system UI stack. No request leaves the site.
**Mono Font:** the system monospace stack, for code, meta keys and URLs.

**Character:** A plain civic sans with government-document sobriety; weight, not size variety, carries hierarchy.

### Hierarchy
- **Condition** (800, 44px desktop / 32px mobile, 1.08, -0.025em): the one condition sentence on the Overview field. Sub-view fields use the same weight at a smaller size.
- **Page Title** (700, 26px, tight, -0.015em): the header of every view except Overview, with a 15px ink-2 subtitle under it.
- **Section Title** (700, 17px): "Warnings in force", "Readings", table headings; always over a strong-rule underline.
- **Reading** (700, 22px, line-height 1, tabular): a single number in a readings list.
- **Item Title** (600, 16px, 1.375): a warning's title.
- **Body** (400, 14px, 1.5): running text; line length held to 36-42rem.
- **Small** (400, 13px): bulletin line, counts, button-adjacent notes.
- **Label** (600, 12px, ink-2): observation labels and table column heads. Sentence case, no tracking.

### Named Rules
**The Tabular Rule.** Every number (counts, readings, dates, pagination) is set with tabular numerals; tables inherit them by default.

**The Sentence Case Rule.** Labels are sentence case with no letter-spacing; the bulletin speaks in plain words, not caps.

## Layout

A two-column app shell: a 15rem (240px) sticky sidebar in column grey, full viewport height under the admin bar, with a 1px right rule; the main column is padded 20px (mobile) / 40px (md+) and capped at 68rem, centered. Below 782px the sidebar collapses to a top block with the nav as wrapping pills and the mini bulletin as one line.

The Overview reads top to bottom: bulletin line (site, host, time observed) over a rule; the condition field (28px padding, 40px on md+, two columns on md+ with the warning scale at the right); the observation row directly beneath (12px gap); then a 48px gap to a two-column body (warnings, and a 20rem readings column on lg); then "What the site publishes". Sub-views repeat the pattern: a condition field or status block, then a ruled two-column body with a 16-18rem aside.

The observation row is a 1px-gap grid on a rule background: 2 columns, 4 at sm, 7 at lg. Lists and tables are divided rows, 14px vertical padding. Settings uses a 12rem sticky section index beside the form, and a sticky save bar on paper at the bottom.

## Elevation & Depth

Flat by default. Depth comes from tonal layering (column, paper, sheet) and 1px rules, not shadows. The only shadows in the system are small and functional:

### Shadow Vocabulary
- **Press rest** (`box-shadow: 0 1px 2px rgb(0 0 0 / 0.18)`): the filled press button at rest.
- **Press hover** (`box-shadow: 0 4px 12px -2px rgb(0 0 0 / 0.28)` with a 1px lift): the press button on hover only.
- **Selected lift** (`box-shadow: 0 1px 2px rgb(17 29 39 / 0.08-0.1)`): the active nav item and the pressed Day/Night segment.

### Named Rules
**The Flat Field Rule.** Warning fields, observation cells, tables and sheets carry no shadow; a surface is set apart by its tone or a 1px rule.

## Shapes

Corners are small and nearly square. Sheets and warning fields use 6px; controls (buttons, inputs, nav items, the mini bulletin) 4px; the Day/Night segments 3px; scale swatches and segmented bars 2px; warning markers and observation bars 1px. The recurring marker is a small square (10-16px) in the level color with a faint dark ring, never a circle; circles (8-10px dots) are reserved for published / not published state. Observation cells carry a 4px color bar at their top edge; the sidebar repeats it as a strip of 4px bars.

## Components

### Buttons
- **Press (primary):** the one filled control per surface. Press fill with on-press text, 4px radius, 40px tall, 650 weight, optional trailing 16px arrow or external icon. Hover lifts 1px and deepens the shadow (160ms, cubic-bezier(0.16, 1, 0.3, 1)); active returns to rest. On a warning field it is always field-ink on white. Settings' wp-admin primary button is restyled to match.
- **Rule button (secondary):** transparent with a 1px strong-rule border, ink text, 600 weight, 36px tall; hover darkens the border to ink. Used for warning actions, pagination, and secondary links.
- **Link:** service blue, 600 weight, underline on hover only.
- **Focus:** a 2px service-blue outline, 2px offset, on every interactive element.

### Condition Field (signature)
Flat full-width sheet in the level color with its on-* text color, 6px radius, generous padding. Holds the condition sentence, a one-line summary (16px), and at most one press button. On the Overview, the warning scale sits at its right as a vertical list of 16px squares (red to clear), the current one bold with a centered dot, the others at 70% opacity. On Products the right side carries a 12px segmented bar with a count legend.

### Observation Row (signature)
Sheet cells on a 1px-gap rule grid inside a 6px-radius border. Each cell: a 4px top bar (ink for OK, rule for not in use, level color for a warning), a 12px label, a 14px semibold value (ink-3 when off). One light sweep crosses it on load (1100ms, 150ms delay, once); removed under reduced motion. The sidebar repeats it as a mini bulletin: level square, level name, and a row of 4px bars.

### Warnings List
Divided rows: 12px level square, 16px title, a bold level name leading the 14px detail, and a rule button aligned right (below on mobile). Empty state is a plain sentence, not an illustration.

### Readings
A definition list of divided rows: label and 12px note on the left, the 22px tabular number on the right.

### Inputs / Fields
Sheet background, 1px strong-rule border, 4px radius, 40px min height, max 30rem wide. Hover darkens the border to ink-3; focus turns it service blue with a 3px 22% service-blue ring. Checkboxes and radios are native, tinted with service blue. Descriptions are 13px ink-2, max 38rem.

### Navigation
Sidebar list of 14px items with 16px outline icons (inline SVG, Lucide paths), 4px radius, 8px/12px padding. Inactive: ink-2 text, ink-3 icon, hover to a translucent sheet. Active: sheet background, ink semibold text, service-blue icon, selected lift. Mobile: the same items as wrapping pills. The Day/Night "Edition" switch is a two-segment group on paper at the sidebar foot.

### Notices
wp-admin notices are restyled as a sheet with a 1px rule border, 6px radius, and a 10px square marker at the left in service blue, or clear / orange / red for success, warning, error.

## Do's and Don'ts

### Do:
- **Do** open every view with its condition: one plain sentence on a level-colored field, or a plain status line.
- **Do** map every state to the four-step warning scale and name it in words ("Yellow warning") next to the color.
- **Do** keep one filled press button per surface; every other action is a rule button or a service-blue link.
- **Do** divide content with 1px rules and use a strong rule under section titles.
- **Do** set every number with tabular numerals.
- **Do** keep all styling scoped to `#lean-seo-app` and the screen class; nothing may restyle the rest of wp-admin.

### Don't:
- **Don't** introduce a saturated color outside the warning scale, or use service blue as a fill.
- **Don't** build KPI card grids or shadowed cards; the observation row is a ruled strip, readings are divided rows.
- **Don't** add a second filled button to a surface, or recolor the press button on a warning field by edition.
- **Don't** add motion beyond the single observation sweep and 160ms state transitions; honor reduced motion.
- **Don't** use neutral black for Night, or load fonts or icons from a remote service.
