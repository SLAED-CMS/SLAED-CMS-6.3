# Editor Style Standardization Plan

Status date: 2026-07-16. Follow-up to `docs/THEME-CONSOLIDATION-PLAN.md`
(executed). Goal: the editor must inherit not only theme colors and fonts
(done) but the full visual language — radii, shadows, borders, field styling,
focus states — so that switching a theme changes the editor look completely.

## Binding rules for this work

- The vendor CSS (`toastui-editor.min.css`) is never edited; the theme skin
  (`templates/<theme>/assets/editors/toastui/skin.css`) overrides it and is
  guaranteed to load after the engine CSS (Editor::getThemeSkin contract).
- Override only look: color, background, border, border-radius, box-shadow,
  font, outline. Never override vendor layout, positioning, z-index mechanics,
  or sizes that JS depends on.
- Every value comes from a token; literal values are allowed only as the last
  fallback in a `var()` chain. No new `--sl-editor-*` tokens.
- Match vendor selector specificity exactly and win by load order; use
  `!important` only where the vendor itself uses it.
- Both theme skins stay structurally identical (same selectors, same token
  names); themes differ only in token values.

## Current state (analysis, 2026-07-16)

Already themed: all colors and spacing of SLAED-owned windows (file manager,
emoji panel, window heads), toolbar icon glyphs and their color
(`--sl-color-text-muted`), fonts (`inherit`), shadows of our windows
(`--sl-shadow-panel`).

### Gap inventory

| # | Element group | Vendor look today | Theme target (tokens) |
|---|---|---|---|
| 1 | Hardcoded radii inside skin.css (8 spots: inputs, buttons, file rows, emoji cells, tab corners) | literal `2px`/`4px` from the old isolated design | `--sl-radius-control` |
| 2 | Editor body `.toastui-editor-defaultUI`, fullscreen | vendor border `#dadde6`, radius 4px | `--sl-color-border` + `--sl-radius-card`; our windows (upload panel, emoji panel) also move to `--sl-radius-card` |
| 3 | Toolbar `.toastui-editor-defaultUI-toolbar` | vendor `#f7f9fc` bg, own border | `--sl-color-bg-soft` + `--sl-color-border` |
| 4 | Native popups add-image/add-link/add-table/add-heading (`.toastui-editor-popup`) | vendor white box, border `#dadde6`, radius 2px, own shadow | `--sl-color-bg`, `--sl-color-border-strong`, `--sl-radius-card`, `--sl-shadow-panel` |
| 5 | Popup buttons `.toastui-editor-ok-button` / `.toastui-editor-close-button` (outside our file manager) | vendor blue `#00a9ff` / gray | same rules as the already-themed file-manager buttons: `--sl-color-primary` (+ `color-mix` hover), `--sl-color-bg-soft` + `--sl-color-border`; consolidate into one selector set covering both contexts |
| 6 | Inputs in popups and our search fields | vendor border `#e1e3e9`, radius 2px, blue focus | field tokens: `--sl-field-border`, `--sl-field-radius`, `--sl-field-bg`, focus via `--sl-field-focus` + `--sl-field-focus-ring`, `--sl-shadow-input` |
| 7 | Image popup tabs `.toastui-editor-tabs .tab-item` | vendor gray/blue active | `--sl-color-text-muted`, active: `--sl-color-primary` + border |
| 8 | Table context menu `.toastui-editor-context-menu` (20 vendor rules) | vendor white, `#333`, own hover | `--sl-color-bg`, `--sl-color-text`, `--sl-color-border`, `--sl-radius-control`, `--sl-shadow-panel`, hover `--sl-color-bg-soft` |
| 9 | Focus/selection states | vendor blue `#00a9ff` rings, md selection highlight | `--sl-field-focus-ring`; md highlight: `color-mix` on `--sl-color-primary` |
| 10 | Markdown syntax colors (`.toastui-editor-md-delimiter`, `-meta`, `-heading`) | vendor grays/blues | `--sl-color-text-muted` / `--sl-color-primary` |
| 11 | WYSIWYG content typography `.toastui-editor-contents` (93 vendor rules: headings, blockquote, code, tables, links) | vendor neutral typography | separate decision — see Phase D |
| 12 | Tooltip `.toastui-editor-tooltip` | vendor dark | optional: `--sl-color-text` on dark stays acceptable; align only radius `--sl-radius-control` |

### Token gap between themes

The editor needs the field group; today it exists only in lite:

| Token | lite | admin |
|---|---|---|
| `--sl-field-bg/border/focus/focus-ring/radius/placeholder` | defined | missing (admin has equivalents: `--sl-focus-primary`, `--sl-focus-primary-ring`, `--sl-shadow-input`, `--sl-radius-control`) |

Decision: extend the canonical dictionary (`.rules/global.md`) with the field
group; admin `base.css` defines `--sl-field-*` from its existing values (about
6 lines). Skins then consume `--sl-field-*` directly with no fallback chains,
keeping both skin files identical.

## Execution phases

### Phase A — geometry parity (small, immediate)
- Replace the 8 hardcoded radii in both skins with `--sl-radius-control`;
  large surfaces (editor body, upload panel, emoji panel, popups) get
  `--sl-radius-card`.
- Add the "vendor chrome" section: editor body border/radius, toolbar
  background/border (gap rows 1-3).
- Verify: screenshots of the editor block in both themes; no layout shifts.

### Phase B — dialogs and controls
- Popup boxes, buttons, inputs, image tabs, context menu (gap rows 4-8).
- Prerequisite: add `--sl-field-*` to admin `base.css` and to the canonical
  dictionary in `.rules/global.md`.
- Button rules are consolidated: one selector block covers file-manager and
  native popup ok/close buttons.
- Verify: Playwright flows — open image/link dialogs, upload flow, table
  context menu; screenshots in both themes.

### Phase C — states
- Focus rings on all editor inputs/buttons, md selection highlight, md syntax
  accents (gap rows 9-10).
- Verify: keyboard walk (Tab) through dialogs, focus visible and in theme
  colors; md mode highlight check.

### Phase D — WYSIWYG content typography (decided 2026-07-16: light alignment)
- Light alignment only: links `--sl-color-primary`, blockquote and code
  backgrounds/borders from tokens, table borders `--sl-color-border` with
  `--sl-color-bg-soft` header, text/headings `--sl-color-text` /
  `--sl-color-text-heading`; vendor font sizing, margins, and line heights
  stay untouched (editing comfort).
- Full site-render parity was rejected: the real preview already goes through
  the actual parser (one click), duplicating content styles in the skin would
  require permanent synchronization with theme.css, and site-scale display
  headings do not belong inside a form field.
- Verify: type a document with headings, quote, code, table, links in both
  themes; colors match the theme, no metric shifts while typing.

### Phase E — cleanup
- Re-check zero literal colors/radii in both skins except last-resort
  fallbacks; update `.rules/global.md` canonical dictionary; screenshots of
  every editor surface archived in the task report.

## Out of scope

- Vendor file edits, editor JS behavior, other editor plugins.
- Dark mode (no dark theme exists yet; token-based styling makes it automatic
  later).
