# Theme Etalon 2026

Turning the two shipped themes into reference etalons that hundreds of
independent themes are copied from.

Status: batch 0 in progress. Batches run in order 0 → 8; batch 9 is independent
and may run alongside 2 to 7. A step that is done is deleted from this file, not
marked done.

No line numbers anywhere: every reference names a file, selector, token or
function, and that name is what to search for.

## Goal

A new theme is created by copying an etalon and editing **one file**:
`templates/<theme>/assets/css/base.css` — its leading `@font-face` and `:root`
block. Everything below that marker is the reset and the element styles, which a
theme author does not touch.

Measurable target: every visual decision has exactly one address.

## How to run a batch

Each batch is run in a fresh session and must not depend on any earlier
conversation.

1. Read `.rules/global.md`, `.rules/theme.md` and this file's batch section.
2. Run `php tools/ui-audit.php --theme=<theme>` first — it prints the current
   counts, and they are the batch's starting point, not the numbers written here.
3. Do the work. Commit per property group where the batch says so.
4. Run the batch's verification in full before reporting done.

Numbers in this document were measured on `d413f314` and drift as development
lands. The tool is the authority; this document is the plan.

## Contract

- **Themes are independent.** No inheritance in `Template`. A theme is a
  self-contained package; the 115 byte-identical rules the two themes share stay
  duplicated on purpose.
- **The engine is not touched.** No filter is added to `Template`, no grammar is
  extended, and `getFile()` and `checkFile()` stay as they are so the security
  boundary around theme paths does not move. The markup batch moves what PHP
  writes by hand, not the engine that renders it.
- **One canon, many skins.** Class names, token names, component semantics and
  rule structure are shared by convention. Values are the theme's to change — but
  today only colour actually differs; the scales are already identical in both,
  so they are shared and a fork of one needs a written reason.
- **Two CSS files per theme:** `base.css` (`@font-face`, `:root` API block,
  marker comment, then reset and element styles) and `theme.css` (components,
  zero literals).
- **A third file consumes tokens and is not API:**
  `assets/editors/toastui/skin.css`, in both themes, reads `--sl-color-*` and
  `--sl-radius-control`. `checkThemeAssets()` requires it when an editor manifest
  declares a skin, so every rename reaches it. `assets/vendor/` is out of scope.
- **`admin` first**, `lite` mirrors it. `admin` is smaller, so the contract and
  the tool get shaken out where mistakes are cheap.
- **Canon scope:** CSS, `fragments`, `partials`. Not `layouts` and `pages` — the
  page shell of the admin panel and of the site differ by nature.

## The one metric

**Untokenised visual decisions in `theme.css`: admin 570, lite 1044 → 0.**

Monotonic, machine-checked. Every batch lowers it; no batch may raise it.

Counted **per part of a value, not per declaration**. A composite value can be
finished while carrying digits:

```css
border: 1px solid var(--sl-border);   /* clean: the decision is the colour */
padding: var(--sl-space-3) var(--sl-space-4);
```

The tool strips `var()` references, drops neutral parts, judges what is left.
1046 declarations in lite and 867 in admin already reach every decision through a
token. A further 98 in lite and 83 in admin are half done — a token and a literal
side by side.

**The second check: bare numbers.** Values that carry no unit and no colour are
invisible to the counter above, and they hold more sites than its largest
property:

| Property | Sites | Values | Spelled |
|---|---|---|---|
`line-height` | 115 | 22 | `1.2`…`1.6` plus `1.428571429` — nine ways to say "normal" |
`font-weight` | 107 | 6 | `normal` and `400`; `bold` and `700` — four weights, six spellings |
`opacity` | 76 | 10 | — |
`z-index` | 42 | 12 | — |

A bare number in these four must come from a token. `0` and `1` stay neutral in
`opacity` and `line-height` — they switch a thing off rather than shade it.

**The fourth check: a name that cannot invert.** Neither theme has a dark mode
today — `prefers-color-scheme` appears zero times in both, while
`prefers-reduced-motion` appears 14 times and `focus-visible` 26. Accessibility
was thought about; the mode was not.

Dark mode is a second value inside the same declaration, so it costs nothing in
structure — but only if every name says its job. Measured, **98 sites carry a
name that lies under inversion**:

| Name | lite | admin |
|---|---|---|
`--sl-color-on-dark` | 44 | 9 |
`--sl-overlay-10/12/15/20` | 17 | — |
`--sl-text-shadow-dark` / `-light` | — | 11 |
`--sl-color-bg-soft-dark` / `-darker` | 9 | — |
`--sl-but-border-light` / `-dark` | 6 | — |
`--sl-switch-dark` | — | 2 |

The tool reports any `white`, `black`, `light`, `dark` or numeric-alpha segment
in a token name as an error. `inverse` passes: it means "opposite of the page",
which holds in both modes.

**The third check: repetition.** A thing written more than once without need is a
zoo whether or not it sits on a ladder. Measured over rule bodies sharing one
`@media` context:

| | lite | admin |
|---|---|---|
| identical bodies | 100 groups over 315 blocks | 62 groups over 187 blocks |
| **redundant blocks** | **215** | **125** |
| of them inside `@media` | 21 | 9 |

`display: none` is written 16 times in lite under 16 selectors; `margin: 0`
twelve times; `color: var(--sl-color-primary)` ten. All 340 collapse into
selector lists with no markup edit and no visual change.

Repetition **with** need is not counted: `display: flex` appears 122 times
because 122 elements are flex containers, and no selector list expresses that.
The check reports only whole bodies that repeat verbatim in one context.

**Decision or structure.** The token carries the step; the property consumes it.
Properties are many, scales are few.

| Property | Tokenised | Stays literal |
|---|---|---|
`padding`, `margin`, `gap`, `inset` | `--sl-space-1…8` | `0`, `auto` |
`border-radius` | `--sl-radius-1…3`, `-pill`, `-circle` | `0`, `50%` for a circle |
`border` | the colour only | `1px`, `solid`, triangle geometry |
`font-size`, `line-height`, `font-weight`, `letter-spacing` | their axes | `1` |
`box-shadow`, `transition` | the whole value | — |
`opacity` | `--sl-fade-*` | `0`, `1` |
`width`, `height`, `min-*`, `max-*` | `--sl-size-*` below 48px | `100%`, `auto`, figures above 48px |
`grid-template-*`, `flex`, `top/left/right/bottom` | — | structure entirely |

**Three questions, in order:**

1. Does the value appear more than once? → a scale.
2. Unique, but would a theme author change it? → a component token,
   `--sl-btn-pad-x`.
3. Unique and nobody will ever change it — an optical `-1px`, a `top: 3px` that
   lines a glyph up? → a literal, in the allowlist with its reason.

Anything off-step **snaps to the nearest one**. A new step is a contract change.

**Structural allowlist:** `grid-template-*`, `grid-area`, `flex`, `flex-basis`,
`order`, `aspect-ratio`; the neutral set `0`, `1`, `1px`, `solid`, `100%`,
`100vh`, `100vw`, `auto`, `none`, `inherit`, `50%` for a circle; `content`
strings and counters; CSS-triangle borders (`border: 8px solid transparent` and
its `17px` twin, 16 sites); `0.01ms`, which means motion off. The list lives in
`.rules/theme.md` and in the tool as one shared source. Growing it requires a
written reason beside the entry.

## Where token names appear

Eight. A miss fails silently — a stray custom property is not an error, and
JavaScript falls through to its own default.

| Place | Files | Occurrences | What it does |
|---|---|---|---|
theme CSS | 4 | ~2000 `var()` | reads |
`assets/editors/toastui/skin.css` | 2 | ~15 | reads; part of the theme package |
`error.html`, repo root | 1 | 39 declared, 34 read | self-contained third token set |
`lite` templates | 5 | 9 | **writes** a live value through an inline style |
admin PHP | 2 | 16 | reads inside markup it assembles |
JavaScript | 2 | 5 | reads and writes |
`tests/Unit/EditorWindowTest.php` | 1 | 1 | asserts `--sl-dial-count` |
`.rules/global.md`, `docs/WINDOW.md` | 2 | — | name tokens as binding contract |

`error.html` is not production markup but it ships: it renders when the CMS
cannot, and it holds its own `:root` with 39 tokens. It is migrated once, in
batch 8, after the names stop moving. Batches 1 to 7 leave it alone.

## Two kinds of tokens

**Theme tokens** — declared in the API block, read by CSS. The public API.

**Data tokens** — written from outside, by a template inline style or JavaScript,
and only read by CSS: comment depth, profile level, group colour, session
percentages, popover arrow offset, scroll distance, dial count. They carry the
`--sl-d-*` prefix so the tool can tell "used but never declared" from "declared as
API". Without the split, batch 1 deletes them as single-use junk and the profile
ring stops moving.

`--sl-size-chip` crosses the other way: `slaed.js` reads it through
`getComputedStyle` and it drives speed dial geometry. Tokens read by JavaScript
are marked in `.rules/theme.md` and cannot be renamed without touching the script
in the same commit.

## Naming grammar

Four laws, each machine-checkable, each with current offenders.

1. **The name says the role, never the value.** Offenders: `--sl-overlay-10/12/15/20`,
   `--sl-h1`…`--sl-h4`.
2. **At most three segments after `--sl-`.** Offenders: 24 in admin, 39 in lite,
   up to `--sl-login-dropdown-form-margin-left` at five.
3. **One axis, one prefix, from the closed list.** Colour is the default axis and
   carries no prefix — it is the largest family and the word `color` bought
   nothing.
4. **State is not an axis, and modifiers do not stack.** `hover`, `focus`,
   `active` live in the selector. Offenders: 18 `--sl-hover-*` in admin, 9 of them
   byte-identical to an existing colour; `--sl-hover-opacity`;
   `--sl-bg-hover-gloss`; `--sl-color-bg-soft-soft` in lite.

Average name length is 20.5 characters today; under this grammar about 14.

**Level 1, primitives.** `--sl-<hue>-<step>`: `--sl-blue-600`, `--sl-gray-100`.
Never read by a component. A primitive exists only if a semantic token points at
it.

**Level 2, semantic axes.** Closed list; values in "The ladders" below.

| Axis | Form | Closed set |
|---|---|---|
colour | `--sl-<role>[-<step>]` | `bg`, `surface`, `border`, `text`, `primary`, `success`, `warning`, `danger`, `accent`, `info`, `on-solid`, `scrim`; ladder `-subtle`, `-muted`, base, `-strong`, `-inverse`; `surface` also `-sunken`, `-raised` |
spacing | `--sl-space-<n>` | 1…8 |
radius | `--sl-radius-<n>`, `-pill`, `-circle` | 1…3 |
type size | `--sl-font-<role>` | `display`, `h1`…`h4`, `body`, `small`, `micro` |
type face | `--sl-face-<role>` | `body`, `display`, `mono`, `quote` |
line height | `--sl-line-<role>` | `tight`, `normal`, `loose` |
type weight | `--sl-weight-<role>` | `normal`, `medium`, `semibold`, `bold` |
tracking | `--sl-track-<role>` | `tight`, `normal`, `wide` |
shadow | `--sl-shadow-<role>`, plus `--sl-shadow-color` | `xs`, `raised`, `float`, `overlay`, `inset`, `focus` |
gradient | `--sl-grad-<role>` | `line`, `gloss`, `stripe`, `progress-1`…`5` |
motion | `--sl-time-<role>`, `--sl-ease-<role>` | `fast`, `base`, `slow` / `out`, `in-out` |
layer | `--sl-z-<role>` | `base`, `dropdown`, `sticky`, `overlay`, `modal`, `popover`, `toast` |
control size | `--sl-size-<role>` | `control`, `chip`, `tile`, `avatar`, `icon-xs`…`icon-lg` |
opacity | `--sl-fade-<role>` | `subtle`, `muted`, `disabled` |
layout | `--sl-layout-<role>` | `container`, `sidebar`, `gutter`, `grid` |
breakpoint | `--sl-bp-<role>` | `sm`, `md`, `lg`, `xl` |

`@media` cannot read a custom property, so breakpoint tokens are documented
constants the tool enforces, not values the cascade resolves. Editing
`--sl-bp-md` changes nothing until the media queries are edited too, and
`.rules/theme.md` says so in place.

Border width gets no axis: `1px` is structural, anything thicker is a component
decision.

**Level 3, component tokens.** `--sl-<component>-<prop>`. `prop` from: `bg`,
`border`, `text`, `radius`, `height`, `width`, `pad-x`, `pad-y`, `gap`, `shadow`,
`ring`. Components: `field`, `check`, `switch`, `btn`, `chip`, `alert`, `table`,
`card`, `modal`, `popover`, `pager`, `progress`, `tab`, `dial`, `avatar`,
`badge`. `--sl-field-*` is the model — admin already overrides it and the
component follows.

**Migration map:**

| Today | Under the grammar |
|---|---|
`--sl-color-primary` | `--sl-primary` |
`--sl-color-text-muted` | `--sl-text-muted` |
`--sl-color-bg-soft-soft` | `--sl-bg-muted` |
`--sl-color-bg-soft-dark` / `-darker` / `-divider` | `--sl-bg-inverse` / `-inverse-strong` / `--sl-border-inverse` |
`--sl-color-primary-hover` + 3 `--sl-hover-*` twins, admin | `--sl-primary-strong` |
`--sl-color-primary-hover`, lite | deleted, unused |
`--sl-color-tone-success` | deleted, duplicates `--sl-success` |
`--sl-overlay-10/12/15/20`, four alphas of black | `--sl-scrim-subtle` / `--sl-scrim` / `--sl-scrim-strong`; 0.12 snaps to 0.1 |
`--sl-h1`…`--sl-h4` | `--sl-font-h1`…`--sl-font-h4` |
`--sl-shadow-soft`, lite — an rgba colour | `--sl-shadow-color`, and its 10 dead sites recomposed |
`--sl-shadow-soft`, admin — a real shadow | `--sl-shadow-xs` |
`--sl-hover-opacity` | deleted; state moves to the selector, reads `--sl-fade-subtle` |
`--sl-radius-control` + `--sl-radius-panel`, both 4px | `--sl-radius-1` |
`--sl-space-xs/-sm/-md/-lg/-xl`, 4/6/8/12/16 | `--sl-space-1…8`; `6px` has no step |
`font-weight: normal` / `bold` / `400` / `700` | `--sl-weight-normal` / `-bold`, one spelling each |
`line-height: 1.4` / `1.45` / `1.5` / `1.55` / `1.6` | `--sl-line-normal` |
`--sl-container` / `--sl-sidebar` / `--sl-gutter` / `--sl-grid-gap` | `--sl-layout-container` / `-sidebar` / `-gutter` / `-grid` |
`--sl-img-placeholder-icon-size` | `--sl-size-placeholder` |
`--sl-progress-fill-3-start` / `-end` | `--sl-grad-progress-3` |
`--sl-but-neutral-start` / `-end` | `--sl-grad-btn` |
`--sl-monitor-orange/green/red/blue` | `--sl-chart-net/cpu/ram/disk` |
`--sl-changelog-*`, 8 of 9 duplicate a semantic colour | deleted |
`--sl-login-*`, 27 tokens | 4 component tokens or none |
`--sl-season-winter` | unchanged, a categorical ramp |
`--sl-profile-level`, written by a template | `--sl-d-level` |

**Every mapping is checked against the target name's current occupants before it
is applied.** `--sl-shadow-strong` already exists in admin holding a composed
shadow; mapping a colour onto it would overwrite a live value with one of a
different kind. The tool reports a collision as an error, never a merge.

**A rename is free only when the value survives it.** `--sl-color-primary` →
`--sl-primary` is free. `--sl-space-sm: 6px` → `--sl-space-<n>` is not: the ladder
has no 6, so 219 lite sites move to 4 or 8. `5px`, 32 sites, is the same. So the
scale families are renamed **and snapped in one step**, in batches 3 and 6 — not
in batch 1, which renames only what survives.

**Forbidden, each reported by the tool:** an alias of an alias with one use and no
theming intent; a literal outside the API block beyond the allowlist; a token in
`theme.css` that could be mistaken for API — scoped custom properties are allowed
only on a component root, only as internal; two spellings of one intent, as
`999px` and `9999px`; a dead token; a token whose value cannot satisfy the
property that reads it.

After batch 8 the names are frozen: hundreds of themes bind to them.

## The ladders

Steps are placed where values already cluster, so the migration is a snap and not
a redesign. Batch 0 copies this into `.rules/theme.md`; the tool reads it there.

**One ladder for both themes, values included.** Measured, the two themes already
use identical scale values — spacing is `4 6 8 12 16` in both — and they differ in
colour, not in scale. So a shared ladder costs nothing and needs no per-theme
escape hatch; a theme that later needs its own value for a step declares it with
a written reason.

The `12px`/`13px` split is not a difference between the themes: **both** use
both, lite 29 against 21 sites and admin 14 against 12. It is one theme saying
one thing two ways, which is the zoo, and it snaps inside each theme.

| Axis | Sites | Values now | Ladder | Displaced |
|---|---|---|---|---|
spacing | 593 | 33 | `2 4 8 10 12 16 20 24` | 41%, of which 87 by 2px |
`font-size` | 194 | 29, `px`/`em` mixed | 10 / 12 / 14 / 16 / 18 / 20 / 24 / 32 | 5 sites above 32px stay exceptions |
`line-height` | 115 | 22 | `1.2` / `1.45` / `1.6`, plus neutral `1` | nine "normal" spellings collapse to one |
`font-weight` | 107 | 6 spellings | `400` / `500` / `600` / `700` | nothing moves |
`border-radius` | 104 | 16 | `4 8 12`, plus `pill`, `circle` | `999`/`9999` merge; `15`, `21`, `30` are exceptions |
`transition` | 149 | 16 durations | `0.15s` / `0.2s` / `0.35s` | 116 sites already sit inside 0.14–0.24s |
easing | 140 | 5 | `ease`, one `cubic-bezier` | 133 of 140 are already `ease` |
`opacity` | 76 | 10 | `0.8` / `0.55` / `0.45`, plus `0`, `1` | — |
`z-index` | 42 | 12 | 7 named layers | spelling only |
`box-shadow` | 27 literal | 15 | 6 roles + `--sl-shadow-color` | — |
`letter-spacing` | 8 | 5, `px`/`em` mixed | 3 roles | — |
breakpoints | 31 `@media` | 11 | `560` / `768` / `900` / `1200` | `600`, `700`, `720`, `760`, `769`, `901`, `1100` move |
colour | 270 | **114 lite, 83 admin** | `--sl-<family>-50…900` | derived by the tool; see below |

**Colour is two ramps, not one, and they split by saturation.** Of the 40 values
that read as "blue" in lite, roughly 20 are cool neutrals (`S 12–27`:
`#f9fafb`, `#e5e7eb`, `#a5b2bf`, `#374151`, `#111827`) and 15 are saturated brand
blues (`S 54–100`: `#0077ff`, `#1d9bf0`, `#207fb6`, `#0a66c2`). A hue-first
classifier files the neutral ramp under blue and reports only 14 grays — wrong by
more than half, and the reason the ramp is defined by saturation first.

The two halves cost differently:

- **Neutrals are cheap.** Their lightness is already almost a ladder — 98 96 93
  92 91 82 76 70 68 54 49 40 28 27 20 17 16 11. Snapping moves a handful of
  values by 1–2 points, invisible in place.
- **Saturated blues are not.** `L 42…73` across `S 54…100` and `H 201…217`.
  Collapsing `#0077ff`, `#0866ff` and `#0a66c2` onto one step asserts they are
  one colour — and they may be a link, a button and a focus ring, which are three
  roles, not three spellings. Each collapse in this half is decided per value
  against what reads it, never by distance alone.

**Spacing runs on a rhythm of five, not four.** `10px`×100, `20px`×67, `15px`×38,
`5px`×32 is 237 sites; `12px`×49, `8px`×42, `16px`×31, `4px`×20 is 142. A ritm-4
ladder lands 38% of sites exactly and moves 223 by 2px. The measured ladder lands
59% and moves 87, with the same eight steps.

**Transitions.** `0.14 / 0.15 / 0.16 / 0.18 / 0.2 / 0.22 / 0.24` — seven spellings
over 116 sites, indistinguishable to a viewer. Collapsing them shifts some sites
by up to 40ms; that is an intended change, recorded here rather than hidden under
"values preserved".

**Breakpoints.** Five pairs say one thing twice: `768`/`769`/`760`, `900`/`901`,
`700`/`720`, `600`/`560`, `1100`/`1200`. The `600`, `700` and `720` sites move far
enough that each is reviewed at its own viewport width.

**Animation duration gets no ladder.** 41 sites, 18 values, near one each — a
spinner at `0.8s`, a pulse at `2s`, a marquee at `5s`. These are the character of
one animation, not steps of a scale. They become component tokens
(`--sl-dur-spin`, `--sl-dur-pulse`), listed in `.rules/theme.md` as the single
documented exception.

## Baseline

Measured on `d413f314`. Re-measure with the tool before acting on any figure.

| Metric | lite | admin |
|---|---|---|
`theme.css` declarations, custom properties excluded | 4471 | 3121 |
untokenised visual decisions | **1044** | **570** |
  of those, half tokenised | 98 | 83 |
tokens in `base.css` | 169 | 121 |
tokens scoped inside `theme.css` | 90 | 74 |
dead tokens | 3 | not measured |
single-use tokens | 67 | not measured |
`sl-*` classes in CSS | 654 | 464 |
classes never referenced | 15 | 5 |
`!important` | 12 | 16 |
names longer than three segments | 39 | 24 |

Untokenised decisions by property, largest first:

| Property | lite | admin |
|---|---|---|
`font-size` | 140 | 61 |
`padding` | 118 | 63 |
`height` | 74 | 38 |
`width` | 72 | 50 |
`margin` | 66 | 25 |
`gap` | 58 | 44 |
`border-radius` | 54 | 17 |
`margin-bottom` | 51 | 21 |
`transition` | 49 | 35 |
`margin-top` | 41 | — |
`box-shadow` | 33 | 12 |
`animation` | 27 | 13 |
`background` | — | 35 |
`min-height` | — | 28 |

Gradients, `transition` and `z-index` reach no value through a token: 0% of 62
gradient sites, 0% of 91 transitions, 0% of 42 layers.

**Cross-theme state the canon must resolve:**

- 236 selectors exist in both themes: 115 byte-identical, **121 divergent**. The
  divergent set includes `h5`, `ol`, keyframe stops at `20%` and `50%`,
  `.sl-highlight`, `.sl-preview-meta`, `.sl-alert-flash-bar`, `.sl-progress-line
  div`, `.sl-debug-stats dd`.
- Same-named templates: `fragments` 50 shared / 24 identical, `partials` 13 / 5,
  `layouts` 2 / 0, `pages` 3 / 0. **39 carry different markup.**
- `--sl-shadow-soft` means two different kinds across the themes: a composed
  shadow in admin, a bare `rgba()` in lite.

**A live bug this uncovered.** In lite, `--sl-shadow-soft` holds
`rgba(32, 75, 102, 0.18)` and all **10** of its uses are
`box-shadow: var(--sl-shadow-soft)`. A shadow of one colour and no lengths is
invalid, so the browser drops all ten and those elements carry no shadow. Batch 1
fixes the sites; batch 0 makes the tool able to catch the class of fault.

Package a new theme copies: `lite` 665 files / 3601 KB, `admin` 490 / 1947 KB.

## Batches

### Batch 0 — contract, audit tool, baseline

**Causa.** With independent themes the only thing that travels between them is
convention. Nothing is enforceable until it is written down and machine-checked,
and no batch that moves pixels may start before there is a picture to compare
against.

**Steps.**
- Write `tools/ui-audit.php`, plain PHP, no dependencies:
  `php tools/ui-audit.php --theme=admin`. It reports literal visual declarations
  per property; bare numbers in `z-index`, `opacity`, `line-height`,
  `font-weight`; values that miss their ladder; the distribution per axis; dead
  tokens; single-use tokens; alias-of-alias chains; literals outside the API
  block; tokens whose value cannot satisfy the property reading them; name
  collisions; **identical rule bodies sharing one `@media` context**; classes
  never referenced; and a cross-theme diff of shared selectors. Non-zero exit
  when a count grows against the stored baseline.
- Derive the colour ramp with `--ramp`: cluster by saturation first, then hue,
  and write the resulting lightness steps into `.rules/theme.md`. Assign each
  step its role — background, hovered background, border, solid fill, text — so a
  collapse is decided by role and not by distance, and so the ramp can reverse
  for dark mode without a role moving. The neutral ramp is settled by the tool;
  each saturated collapse is confirmed against what reads the value.
- **Add the contrast gate**, over pairs that actually meet. The tool finds every
  place a text colour and a surface colour apply to the same element — the same
  rule, or a rule and the container it sits in — and fails below 4.5:1, 3:1 for
  large text, in both modes. This is what makes hundreds of copied themes safe:
  an author who recolours the palette is told the text is unreadable before
  shipping. No CMS named in the survey gates on this.

  **Not the cross product of every text token against every surface token.** Run
  that way it reports 39 failures in lite and 16 in admin, and its worst offender
  is `--sl-on-solid` against the page background — a pair that appears nowhere.
  A gate that opens with 39 false alarms is switched off in a day.
- Store the baseline in **`tools/ui-audit-baseline.json`**, committed. `.rules/`
  and `.agents/` are gitignored, so the ratchet cannot live beside the contract —
  it would not survive a clone.
- **Add the marker comment** `/* --- end tokens --- */` at the end of the `:root`
  block in both `base.css` files. This is the one CSS edit batch 0 makes; it is a
  comment and changes no rendering.
- **Extend `.claude/hooks/lint-edit.php`:** when the edited path matches
  `templates/*/assets/css/*.css`, run the audit on that file and print each
  violation together with the token that replaces it. This is the only gate that
  fires while the edit is still in hand.
- **Write `tests/Unit/ThemeContractTest.php`:** runs the audit against the stored
  baseline and fails on any grown count. It catches what the hook cannot — manual
  edits and merges. There are no git hooks and no CI in this repository, so these
  two gates are the whole enforcement.
- **Write `tests/Unit/UiAuditTest.php` — the tool's own test, on fixtures.**
  Small CSS files with a known answer: three literals and one duplicate inside a
  `@media` context must report 3 and 0. Cover each classifier separately —
  literal counting, the bare-number check, ladder membership, colour family by
  saturation, duplicate bodies within one context, contrast pairs that actually
  meet, name collisions.

  The tool is the authority for every number in this plan, and a wrong
  classifier is invisible: it produces a plausible count that goes straight into
  the baseline, and eight batches then measure themselves against it. Two
  classifiers written while drafting this plan were wrong — one grouped colours
  by hue and filed the cool neutral ramp under blue, halving the grey count; the
  other counted duplicate rule bodies without `@media` context and would have
  merged rules that cannot merge. Both were caught only by re-deriving the number
  a second way by hand. Fixtures make that check automatic instead of lucky.
- Capture Playwright baselines of what ships: the front page, an article, a forum
  topic, the profile, private messages, the admin sections. Scripts under
  `c:\tmp\`; the admin login field is `name="pwd"`.
- **Clear caches before capturing.** Empty `storage/cache/pages/*` and
  `storage/cache/templates/*` and capture with `cache_css` off. `doCss()` reads a
  `cssfp` fingerprint stored in `$conf['derived']['assets']`, so a bundled
  stylesheet can survive a CSS edit until the config is rebuilt; a comparison
  over a warm cache compares caches, not renders.

**Verification.** `UiAuditTest` passes on the fixtures **first** — an unverified
tool must not be allowed to write a baseline. Then the tool runs on both themes
and its output becomes the stored baseline, and `ThemeContractTest` passes
against it. The hook fires on a deliberate test edit and names the replacement
token. `phpunit`, `phpstan`, `php-cs-fixer --dry-run` pass.

`git diff --stat` shows only `tools/ui-audit.php`,
`tools/ui-audit-baseline.json`, `tests/Unit/ThemeContractTest.php`,
`tests/Unit/UiAuditTest.php`, its fixtures, `.claude/hooks/lint-edit.php`, and
the marker comment in the two `base.css` files — one added line each, no
declaration touched.

### Batch 1 — token hygiene

**Causa.** Later batches create hundreds of new token references. Cleaning
afterwards means re-touching all of them, and a polluted API is irreversible once
themes ship against it.

**Steps.**
- Delete the 3 dead lite tokens: `--sl-content`, `--sl-color-primary-hover`,
  `--sl-h3`. `--sl-color-primary-hover` is dead in lite only; in admin it is live
  and gets renamed.
- Fix the ten dead `box-shadow: var(--sl-shadow-soft)` sites in lite: the token
  becomes `--sl-shadow-color` and each site composes a real shadow from a
  `--sl-shadow-*` role. Ten shadows appear that were never drawn — the one
  intended visual change in this batch.
- Triage the 67 single-use lite tokens: component tokens that are correct as they
  are (`--sl-field-*` is the model) against instance junk. Fold the junk into the
  value it aliases; the 27 `--sl-login-*`, 10 of them `--sl-login-dropdown-*`,
  first.
- Review the 90 lite and 74 admin tokens scoped inside `theme.css`; mark each
  internal or promote it to the API block.
- Apply the migration map, **except the scale families** — `--sl-space-*` and
  `--sl-radius-*` move with their snap in batches 3 and 6.
- Move values written from outside onto `--sl-d-*`.

**Every rename runs across the seven production places in one commit**: 4 theme
CSS, 2 `skin.css`, 5 lite templates, 2 admin PHP, 2 JavaScript,
`EditorWindowTest`, and the names quoted in `.rules/global.md` and
`docs/WINDOW.md`. `error.html` is batch 8.

**Verification.** Screenshots identical except the ten intended shadows. The tool
reports 0 dead tokens, 0 alias chains, 0 collisions, 0 tokens that cannot satisfy
their property; every remaining single-use token is annotated as a component
token. `phpunit` passes — `EditorWindowTest` is the canary for the `--sl-d-*`
move.

### Batch 2 — admin: the zero-percent axes

**Causa.** Gradients, transitions and `z-index` are tokenised nowhere. A theme
author cannot restyle 41 gradients, 36 transitions or 16 layers without forking
rules. These move into tokens verbatim, so rendering cannot change by
construction — the cheapest drop in the metric.

**Steps.** Extract `--sl-grad-*`, `--sl-time-*`, `--sl-ease-*` and `--sl-z-*` as a
named layer ladder. Collapse the 27 transition spellings and 9 `z-index` values
onto it. Duplicates that differ only in spelling collapse onto one token.

Duration is the one caveat: seven spellings collapse onto `--sl-time-fast`,
shifting some sites by up to 40ms. Intended, not smuggled in. `0.01ms` stays
literal. Animation durations are not touched here — they become component tokens
in batch 4.

**Verification.** Screenshots identical. The metric drops by roughly 60. The
`z-index` ladder is checked by the bare-number rule and by opening a modal over a
dropdown over a sticky header. `phpunit` passes.

### Batch 3 — admin: scales

**Causa.** 28 `font-size` values and 8 radius literals are not a system, and
spacing is the largest single block. First batch that changes pixels, which is
why it follows the baseline.

**Steps.** Migrate every axis onto the batch 0 ladders: `font-size` (61),
`padding` (63), `gap` (44), `margin` with `margin-bottom` (46), `border-radius`
(17), `line-height`, `font-weight`, `letter-spacing`, `opacity`. `font-weight` is
free — `normal`→`400`, `bold`→`700` change nothing on screen.

Carries the renames deferred from batch 1: `--sl-space-*` onto `--sl-space-<n>`,
`--sl-radius-control`/`-panel` onto `--sl-radius-1`, `--sl-hover-opacity` onto
`--sl-fade-subtle`. `6px` and the 32 `5px` sites move to 4 or 8 site by site,
judged against the screenshot, not globally.

Name the four breakpoints and collapse the eleven current values. `900`/`901` and
`768`/`769`/`760` are free; `600`, `700`, `720` are not, and each is reviewed at
its own viewport width.

**Commit per property group**: `font-size`; `padding`+`gap`; `margin`;
`border-radius`; the bare-number group (`line-height`, `font-weight`, `opacity`);
breakpoints. A bad snap in one group must be revertable without losing the
others.

**Verification.** Screenshot diff page by page, plus one pass per breakpoint at
its own width; every difference either intended or reverted. The metric drops by
roughly 230. `phpunit` passes.

### Batch 4 — admin: the remainder, first etalon closed

**Causa.** A theme that is 90% tokenised still forces its author into
`theme.css`. Only zero is a contract.

**Steps.** `background` (35), `width` (50), `height` (38), `min-height` (28),
`box-shadow` (12) and the tail — roughly 230 decisions, plus the 83 half
tokenised.

`width` and `height` split on 48px: below it a control size reading `--sl-size-*`,
above it a layout figure staying literal. Animation durations become component
tokens here — `--sl-dur-spin`, `--sl-dur-pulse` and siblings, one per animation.

**Dark mode lands here**, once every colour in admin reaches a role and zero
decisions sit outside the API block. Each colour, shadow, gradient and scrim
token takes both values in one declaration through `light-dark()`; there is no
second block and no `@media`, so the two modes cannot drift apart. The switch is
two selectors on `:root` keyed by `data-theme`, which the layout writes on
`<html>` from the user setting — `templates/admin/layouts/admin.html` and
`bare.html` carry `<html lang="{{ lang }}">` today and gain the attribute.

No component rule mentions the mode. A component that cannot follow is missing a
role, and that is the finding, not a licence to write a dark rule in `theme.css`.
The contrast gate runs over both modes.

This raises the browser baseline to Chrome 123 / Safari 17.5 / Firefox 120,
mid-2024, above the `:has()` baseline the themes already assume — a deliberate
trade for a dark mode that cannot desynchronise.

**Verification.** `php tools/ui-audit.php --theme=admin` reports **0** untokenised
visual decisions outside the allowlist, **0** bare numbers in `z-index`,
`opacity`, `line-height`, `font-weight` beyond neutral `0` and `1`, and **0**
values missing their ladder. Screenshots reviewed, `phpunit` passes. From here
`admin` is the working example every later batch is measured against.

### Batch 5 — lite: the zero-percent axes

Batch 2 mechanically, on the token names admin settled: 21 gradients, 49
transitions, 27 animations, 26 `z-index`. The metric drops by roughly 80.

### Batch 6 — lite: scales

Batch 3 on the same ladders: `font-size` (140), `padding` (118), `margin` with
`margin-bottom` and `margin-top` (158), `gap` (58), `border-radius` (54),
`line-height`, `font-weight`, `letter-spacing`, `opacity`, plus the deferred
`--sl-space-*` rename over 219 `sm` sites alone. Largest batch at roughly 530
decisions; splitting per property group is expected and the per-group commit rule
applies with more force.

The ladders are the ones admin settled, values included — the two themes already
share their scale values. An extra step or a different value is a contract change
and needs a written reason.

### Batch 7 — lite: the remainder, second etalon closed

`height` (74), `width` (72), `box-shadow` (33) and the tail, plus the 98 half
tokenised, plus the dark value set as in batch 4.

**Verification.** `php tools/ui-audit.php --theme=lite` reports **0** on all
checks, in both modes. Both themes now hold every visual decision in their API
block, and both render light and dark from one set of role names.

### Batch 8 — canon reconciliation, skeleton, freeze

**Causa.** Independence makes divergence permanent. A contradiction shipped in
the etalon is taught to every descendant, and after distribution the names can no
longer be corrected.

**Steps.**
- Resolve the 121 divergent shared selectors: each becomes identical in structure
  with the difference expressed by a token, or gets an allowlist entry with a
  written reason. Neither one nor the other is a bug.
- Resolve the 39 same-named templates with different markup — 26 `fragments`, 8
  `partials`. `layouts` and `pages` are out of canon scope.
- **Collapse the 340 redundant rule blocks** — 215 lite, 125 admin — into
  selector lists. Two conditions, both required: the bodies must share one
  `@media` context, and the selectors must belong together. `.sl-none` beside
  `.sl-dial-post` is a utility beside a component; merging them scatters the
  component's definition across the file and is refused. Merging changes a rule's
  position in the cascade, so this runs as its own commit against the screenshot
  baseline, never mixed with a value change.
- Migrate `error.html` onto the settled names. It stays self-contained: it is the
  only surface that renders when the CMS cannot.
- Decide the 15 lite and 5 admin never-referenced classes: delete or document.
  Check `sl-attach-*` (parser `[attach]`) and the seasonal
  `sl-winter`/`sl-spring`/`sl-summer`/`sl-autumn`/`sl-newyear` for dynamic
  composition in PHP before removing anything.
- Write the theme skeleton: which files a new theme must contain, which it may
  change, which it must not, how to run the tool against it. It must agree with
  `checkThemeAssets()` in `core/system.php` and with `TemplateValidationTest` —
  both encode the required file list, and both are edited here if the skeleton
  changes it.
- Freeze the API and note the freeze in `.rules/theme.md`.

**Verification.** The cross-theme diff reports only allowlisted divergences, each
with a reason. A scratch theme built from the skeleton by editing the API block
alone renders correctly. `phpunit` passes.

### Batch 9 — markup leaves PHP

**Causa.** A theme cannot restyle what PHP hardcodes, and "one address per
decision" cannot hold while class names, inline styles and tags are assembled
outside the template layer. Independent of the CSS work; may run alongside
batches 2 to 7.

**Scope.** 17 files, 111 occurrences: 47 `class="`, 19 `style="`, 45 literal
tags. Three files carry 89.

| File | `class=` | `style=` | tags |
|---|---|---|---|
`core/classes/parser.php` | 3 | 7 | 21 |
`admin/modules/monitor.php` | 23 | — | 1 |
`admin/modules/statistic.php` | 11 | 3 | 10 |
`modules/rss/lang/*.php`, 5 languages | — | 5 | 5 |
editor drivers, 3 files | 1 | 2 | 3 |
remaining 6 files | 9 | 2 | 5 |

**Steps.** Each site moves into a fragment rendered through `getHtmlFrag()`; PHP
passes data. The monitor chart becomes a fragment taking the four series; the
statistic tables a fragment taking rows; the parser emits the existing `sl-alert`
and code fragments instead of building its own. The RSS language files hold a
document template, not styled markup — decided separately, since RSS output is
XML and not a page.

**Verification.** This command, returning nothing outside the agreed exceptions:

```
grep -rEn "class=\"|style=\"|['\"]<(div|span|table|svg|ul|li|a |p |img|i )" modules core admin blocks plugins --include=*.php
```

Rendering unchanged against the batch 0 screenshots. `phpunit` passes.

## Risks

- **Snap regressions.** Batches 3 and 6 change pixels on purpose. The only
  defence is the batch 0 screenshot set; a page never captured is a page where a
  regression ships silently. Capture before, not during.
- **Cache masking.** `cssfp` in `$conf['derived']['assets']` and
  `storage/cache/pages/*` can both serve a stale result over a correct edit, in
  either direction. Every screenshot round clears both first.
- **Rollback granularity.** Batches 3 and 6 touch roughly 760 declarations with a
  human screenshot review as the only gate. The per-property-group commit rule
  keeps one bad snap to one group.
- **Over-unification.** The temptation in batch 8 is to make the admin panel look
  like the site. A difference is legal only when a token expresses it; what
  cannot be expressed that way is discussed, not fixed by eye.
- **Premature freeze.** Freezing names before both themes reach zero bakes in
  names the remaining literals contradict. The freeze is in batch 8.
- **Occupied rename targets.** Every mapping is checked against the target name's
  current occupants; the tool reports a collision as an error.
- **Volume.** 1614 declarations across the two themes is the bulk of the work,
  larger than everything else combined. Batch 6 alone is roughly 530. Splitting
  it is expected.
- **Consolidation is not compression.** Expect roughly 10–15% fewer rules, not a
  smaller codebase. The deliverable is one address per decision, not kilobytes.
