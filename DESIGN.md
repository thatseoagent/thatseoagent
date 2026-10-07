---
name: ThatSeoAgent admin
description: The site's SEO state read as a weather bulletin inside wp-admin, in the That SEO Agent brand.
colors:
  paper: "#f8f5f1"
  sheet: "#ffffff"
  column: "#f1ece3"
  rule: "#e7e0d8"
  rule-strong: "#d6ccc0"
  ink: "#1c1815"
  ink-2: "#5f564e"
  ink-3: "#736a5f"
  met: "#1c1815"
  press: "#1c1815"
  press-hover: "#3a332d"
  on-press: "#f8f5f1"
  level-clear: "#1e7a3e"
  level-yellow: "#9a7200"
  level-orange: "#b45309"
  level-red: "#c0362b"
  on-level: "#ffffff"
  brand-mark: "#ff4e20"
  night-paper: "#15110e"
  night-sheet: "#1e1813"
  night-column: "#110d0b"
  night-rule: "#2a231c"
  night-rule-strong: "#3a3128"
  night-ink: "#f4efe7"
  night-ink-2: "#a0968a"
  night-ink-3: "#8b8175"
  night-met: "#f4efe7"
  night-press: "#f4efe7"
  night-press-hover: "#ddd5ca"
  night-on-press: "#15110e"
  night-level-clear: "#4ade80"
  night-level-yellow: "#c49b00"
  night-level-orange: "#f0883e"
  night-level-red: "#f04444"
  night-on-level: "#15110e"
typography:
  condition:
    fontFamily: "Space Grotesk, system-ui, sans-serif"
    fontSize: "44px"
    fontWeight: 700
    lineHeight: 1.02
    letterSpacing: "-0.03em"
  page-title:
    fontFamily: "Space Grotesk, system-ui, sans-serif"
    fontSize: "26px"
    fontWeight: 700
    lineHeight: 1.25
    letterSpacing: "-0.015em"
  section-title:
    fontFamily: "Space Grotesk, system-ui, sans-serif"
    fontSize: "17px"
    fontWeight: 700
    lineHeight: 1.5
  body:
    fontFamily: "Space Grotesk, system-ui, sans-serif"
    fontSize: "14px"
    fontWeight: 400
    lineHeight: 1.55
  small:
    fontFamily: "Space Grotesk, system-ui, sans-serif"
    fontSize: "13px"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "Space Mono, ui-monospace, monospace"
    fontSize: "11px"
    fontWeight: 700
    lineHeight: 1.3
    letterSpacing: "0.1em"
    textTransform: "uppercase"
  reading:
    fontFamily: "Space Mono, ui-monospace, monospace"
    fontSize: "22px"
    fontWeight: 700
    lineHeight: 1
  nav:
    fontFamily: "Space Mono, ui-monospace, monospace"
    fontSize: "12px"
    fontWeight: 400
    letterSpacing: "0.08em"
    textTransform: "uppercase"
rounded:
  none: "0px"
  dot: "9999px"
components:
  button-press:
    backgroundColor: "{colors.press}"
    textColor: "{colors.on-press}"
    typography: "{typography.label}"
    rounded: "{rounded.none}"
    padding: "0 1.25rem"
    height: "2.5rem"
  button-rule:
    backgroundColor: "transparent"
    textColor: "{colors.ink}"
    typography: "{typography.label}"
    rounded: "{rounded.none}"
    padding: "0 0.9rem"
    height: "2.25rem"
  input:
    backgroundColor: "{colors.sheet}"
    textColor: "{colors.ink}"
    rounded: "{rounded.none}"
    padding: "0.5rem 0.75rem"
    height: "2.5rem"
  condition-cell:
    backgroundColor: "{colors.sheet}"
    textColor: "{colors.ink}"
    rounded: "{rounded.none}"
    padding: "40px"
---

# Design System: ThatSeoAgent admin

Scope: the plugin's wp-admin screen (Overview, Products, Content check, AI crawlers, AI index, Settings). The meta box in the post editor keeps wp-admin's look, and the plugin's front-end output has no visual design.

## Overview

**Creative North Star: "The Weather Bulletin, on the brand's paper"**

The site gets a weather bulletin, not a dashboard. Each view opens with one plain condition sentence, then the warnings in force, a row of observations, then the readings. The warning levels follow the European weather-warning scale (none, yellow, orange, red), which people read without knowing anything about SEO.

Since 2.6.0 the bulletin wears the That SEO Agent brand: warm paper, hairline rules, square corners, no shadow, Space Grotesk for sentences, Space Mono for labels and numbers, and Deep Ōtan Red as the one lamp. When this file and the brand disagree, the brand wins; the exceptions are named below.

The screen lives inside wp-admin and must not leak into it: no global reset, a reset scoped to `#thatseoagent-app`, utilities marked important so unlayered wp-admin rules cannot override them. Two editions exist, Day (warm paper, default) and Night (the report's warm espresso, never cold black), switched from the sidebar.

**Key Characteristics:**
- One condition sentence per view, in a white cell, beside the warning scale.
- Warm paper, white cells, hairline rules; square corners; no shadow anywhere.
- Deep Ōtan Red in the logo only. The brand puts it on links, focus, the active mark and the one filled control; here those are ink, because on a screen of warnings red reads as one more thing wrong (since 2.7.0).
- The warning scale in the brand's status tones; the level's name in the sidebar and the scale, for screen readers elsewhere.
- Space Mono, uppercase and letter-spaced, for every label, count and number.

## Colors

### Accent
- **Deep Ōtan Red** is the logo's alone (`#ff4e20`, drawn in the mark itself). Never a state: red reads as something wrong, so nothing that is fine, finished or in progress is painted with it.
- **Met** (links, told apart by their underline; focus outlines; the checked radio and checkbox; the caret and the text selection) is ink, the night ink in Night.
- **Press** (the one filled control) and the active nav icon are ink, not red: a red button beside a warning reads as one more thing wrong. Paper label on ink; a lighter ink on hover. In Night, the night ink with an espresso label.
- **Ōtan Red** (brand-mark): the logo square only, fixed in both editions.

### Warning Scale
The brand's status tones, darkened for paper: clear `#1e7a3e`, yellow `#9a7200`, orange `#b45309` (the brand has no orange; this one sits between its warning and danger), red `#c0362b`. They mark squares, bars and dots; see the Named Level Rule for where the name is written. Fine is clear green: an observation in order, a finished or filling progress bar, a check that passed. Something under way and not yet good or bad (saving) is neutral rule grey. Yellow is 4.0:1 on paper, enough for a mark (3:1) but not for text, so no text is set in a level color.

### Neutral
- **Paper** (paper): the page ground, also behind wp-admin's body on this screen.
- **Sheet** (sheet): cells, tables, inputs, the active nav item.
- **Column** (column): the sidebar, code insets.
- **Rule / Strong rule**: every 1px divider; strong rule under section titles and around secondary controls.
- **Ink / Ink 2 / Ink 3**: text in three tiers, all AA on paper and on sheet.

### Named Rules
**The One Lamp Rule.** Red is the only saturated accent, and rare. Plain information takes no color: info notices and toasts use an ink-3 square, not red.

**The Named Level Rule.** A warning level is written out where it is the subject: the sidebar's mini bulletin and the warning scale beside the condition, which marks the current one. Elsewhere — the warnings list, the products report, the observation cells — its square or bar shows it on screen and the name is there for screen readers only, so rows do not repeat "Yellow warning" down the page.

## Typography

**Sentences:** Space Grotesk, self-hosted variable woff2 (300–700, latin and latin-ext).
**Labels and numbers:** Space Mono, self-hosted woff2 (400 and 700, latin and latin-ext).
Both from `assets/fonts` (`pnpm run vendor:fonts`); no request leaves the site.

- **Condition** (700, 44px / 32px mobile, 1.02, -0.03em).
- **Page title** (700, 26px) with a 15px ink-2 subtitle.
- **Section title** (700, 17px) over a strong rule.
- **Body** (400, 14px, 1.55). The brand's body is 300 at 15px; wp-admin's denser 14px keeps 400 so it stays legible.
- **Label** (`.tsa-label`: Space Mono 700, 11px, uppercase, 0.1em): observation labels, table heads, the section index heading, the sidebar caption.
- **Reading** (Space Mono 700, 22px): single numbers in readings and stats.
- **Nav** (Space Mono 12px, uppercase, 0.08em; bold when active).
- **Buttons**: Space Mono 700, uppercase, letter-spaced, 11–12px.

**The Mono-Label Rule.** If it labels or counts, it is mono; if it speaks in sentences, it is Space Grotesk. Form field labels in Settings are sentences, so they stay in Space Grotesk.

## Layout

A two-column app shell: a 15rem sticky sidebar in column tone with a 1px right rule; the main column is padded 20px / 40px and capped at 68rem. The sidebar opens with the brand mark (an Ōtan Red square holding the bot) beside the wordmark. The Overview reads: bulletin line; the condition cell (the warning scale at its right on md+); the observation row; warnings and readings; what the site publishes. Settings uses a 12rem sticky section index beside the form and a sticky save bar.

Lists and tables are divided rows. **The last row draws no bottom rule** where a section separator or another rule follows, so two lines never meet.

## Elevation & Depth

None. Depth comes from paper → sheet and 1px rules. A selected segment or nav item is a sheet with a 1px hairline (`ring-1`), never a shadow; input focus is a second 1px line in ink (met), never a glow.

## Shapes

Square corners everywhere: cells, buttons, inputs, notices, segments, markers. Circles only for round things: status dots and the dot inside the current level's square.

## Components

- **Press (primary):** the one filled control per surface; ink with a paper label, Space Mono uppercase; hover turns a lighter ink (press-hover); in Night, the night ink with an espresso label; color-only transition, 150ms. Settings' wp-admin primary button matches.
- **Rule button (secondary):** transparent, 1px strong-rule border, Space Mono uppercase; hover darkens the border to ink.
- **Link:** ink (met), underlined 3px below the text, thicker on hover.
- **Segmented choice:** a ruled strip on paper; the checked segment is a sheet with a hairline.
- **Condition cell (signature):** a white cell with a hairline border; the sentence, a summary and at most one press, with the warning scale at its right marking the current level. On Products the right side carries the segmented bar with its legend.
- **Observation row (signature):** white cells on a 1px-gap rule grid, each with a 4px top bar (clear green for OK, rule when not in use, the level's tone for a warning), a mono label and the value. One light sweep crosses it on load; none under reduced motion.
- **Inputs:** sheet, 1px strong-rule border, square; hover darkens the border to ink 3; focus turns the border ink (met) with a second 1px ink line.
- **Notices:** a sheet with a rule border and a square marker: ink-3 for information, the level tones for success, warning and error.

## Do's and Don'ts

### Do:
- **Do** write the level's name in the sidebar and the scale, and keep it for screen readers everywhere else.
- **Do** keep one press per surface; everything else is a rule button or a link.
- **Do** set labels, counts and numbers in Space Mono.
- **Do** keep all styling scoped to `#thatseoagent-app` and the screen class.

### Don't:
- **Don't** add a shadow, a glow, a gradient fill or a rounded corner (dots excepted).
- **Don't** use red for anything that is not the accent's job: not for plain information, not as decoration.
- **Don't** paint a surface in a level color, or set text in one.
- **Don't** load fonts or icons from a remote service.
