# Theme Etalon 2026

Turning the two shipped themes into reference etalons that hundreds of
independent themes are copied from.

Status: batch 0 in progress. Batches run 0 → 8; batch 9 is independent and may
run alongside 2 to 7. A step that is done is deleted from this file, not marked
done.

No line numbers anywhere: every reference names a file, selector, token or
function, and that name is what to search for.

## Goal

A new theme is created by copying an etalon and editing **one file**:
`templates/<theme>/assets/css/base.css` — its `@font-face` and `:root` block,
down to the marker. Everything below the marker is reset and element styles.

Measurable target: every visual decision has exactly one address.

One exception, a limit of CSS rather than a gap: **breakpoint widths are canon,
not API**. `@media` cannot read a custom property, so a theme changes how things
look at a breakpoint, never where it sits.

## How to run a batch

Each batch runs in a fresh session and must not depend on any earlier
conversation.

1. Read `.rules/global.md`, `.rules/theme.md` and this file's batch section.
2. Run `php tools/ui-audit.php --theme=<theme>` first; its counts are the
   starting point, not the numbers written here. Batch 0 is the exception — it
   builds the tool.
3. Do the work. Commit per property group where the batch says so.
4. Run the batch's verification in full before reporting done.

**Every number here names the command that produced it.** A figure without one is
not to be trusted and must be re-derived before it sizes any work — six figures
in earlier drafts were wrong and one was invented, and none could be checked
without redoing the analysis by hand.

The commands are flags of `tools/ui-audit.php`, and batch 0 must implement every
one this document quotes:

| Flag | Answers |
|---|---|
`--count` | untokenised decisions, per property, per file |
`--bare` | bare numbers in `z-index`, `opacity`, `line-height`, `font-weight` |
`--dist=<property>` | one property's value distribution, for placing a ladder |
`--dup` | identical rule bodies sharing one `@media` context |
`--names` | grammar violations, including names that cannot invert |
`--ramp` | colour families by saturation, and their lightness spread |
`--cross` | selectors and templates that differ between themes |
`--markup` | class, style and tag literals in PHP, by file |

Plus `--file` and `--migrating` for the hook, and `--strict` for a theme with no
baseline. Numbers here were measured on `d413f314` and drift as work lands.

## Contract

- **Themes are independent.** No inheritance in `Template`; the 115
  byte-identical rules they share stay duplicated on purpose.
- **The engine is not touched.** No filter added, no grammar extended,
  `getFile()` and `checkFile()` unchanged so the security boundary around theme
  paths does not move.
- **One canon, many skins.** Names, semantics and rule structure are shared.
  Values are the theme's — but today only colour differs; the scales are already
  identical, so they are shared and forking one needs a written reason.
- **Two CSS files per theme:** `base.css` (`@font-face`, `:root`, marker, reset,
  element styles) and `theme.css` (components, zero literals).
- **`editors/toastui/skin.css` consumes tokens and is not API**, but is held to
  the same zero. `checkThemeAssets()` requires it when an editor manifest
  declares a skin. 1657 lines, 288 `var()`, **272 literals** per theme — and the
  two copies are byte-identical, so it is migrated once in admin and copied.
  `assets/vendor/` stays out of scope.
- **`admin` first**, `lite` mirrors it: admin is smaller, so mistakes are cheap.
- **Canon scope:** CSS, `fragments`, `partials`. Not `layouts` and `pages` — the
  page shells differ by nature.

## The one metric

**Untokenised visual decisions: admin 570, lite 1044 → 0.** `--count`

Counted over every CSS file that is not the API block: `theme.css`, the element
styles below the marker, and `skin.css`. The figures above covered `theme.css`
alone, so batch 0's re-count rises before it falls — `skin.css` adds 272 per
theme.

Monotonic in one direction: **no batch raises a count it controls.** Batches 2–7
lower the untokenised count; 0, 1, 8 and 9 hold it flat. "Every batch lowers it"
would be false for four of the ten.

Counted **per part of a value, not per declaration** — the tool strips `var()`,
drops neutral parts and judges the rest, so this line is already clean:

```css
border: 1px solid var(--sl-border);   /* the decision is the colour */
```

1046 declarations in lite and 867 in admin already reach every decision through a
token; 98 and 83 more are half done.

**Second check: bare numbers.** `--bare`

| Property | Sites | Values | Spelled |
|---|---|---|---|
`line-height` | 115 | 22 | `1.2`…`1.6` plus `1.428571429` — nine ways to say "normal" |
`font-weight` | 107 | 6 | `normal` and `400`, `bold` and `700` — four weights, six spellings |
`opacity` | 76 | 10 | — |
`z-index` | 42 | 12 | — |

These carry no unit and no colour, so the first counter is blind to them. A bare
number in the four must come from a token; `0` and `1` stay neutral in `opacity`
and `line-height`.

**Third check: repetition.** `--dup`

| | lite | admin |
|---|---|---|
identical bodies | 100 groups over 315 blocks | 62 over 187 |
**redundant blocks** | **215** | **125** |
of them inside `@media` | 21 | 9 |

`display: none` appears 16 times in lite under 16 selectors, `margin: 0` twelve.
These 340 are **candidates, not certainties** — whether selectors belong together
is a human call, so batch 8 merges a group or allowlists it with a reason. What
is certain is that none may be left unexamined. Repetition **with** need is not
counted: `display: flex` appears 122 times because 122 elements are flex
containers.

**Fourth check: a name that cannot invert.** `--names`

| Name | lite | admin |
|---|---|---|
`--sl-color-on-dark` | 44 | 9 |
`--sl-overlay-10/12/15/20` | 17 | — |
`--sl-text-shadow-dark` / `-light` | — | 11 |
`--sl-color-bg-soft-dark` / `-darker` | 9 | — |
`--sl-but-border-light` / `-dark` | 6 | — |
`--sl-switch-dark` | — | 2 |

Neither theme has a dark mode — `prefers-color-scheme` appears zero times, while
`prefers-reduced-motion` appears 14 and `focus-visible` 26. Dark is a second
value inside the same declaration, so it costs nothing structurally, but only if
every name says its job: **98 sites carry a name that lies under inversion.** The
tool rejects `white`, `black`, `light`, `dark` or a numeric alpha in a name.
`inverse` passes — "opposite of the page" holds in both modes.

**Decision or structure.**

| Property | Tokenised | Stays literal |
|---|---|---|
`padding`, `margin`, `gap`, `inset` | `--sl-space-1…8` | `0`, `auto` |
`border-radius` | `--sl-radius-1…3`, `-pill`, `-circle` | `0`, `50%` for a circle |
`border` | the colour only | `1px`, `solid`, triangle geometry |
`font-size`, `line-height`, `font-weight`, `letter-spacing` | their axes | `1` |
`box-shadow` | the whole value | — |
`transition` | `--sl-time-*`, `--sl-ease-*` | the property name it animates |
`opacity` | `--sl-fade-*` | `0`, `1` |
`width`, `height`, `min-*`, `max-*` | `--sl-size-*` when sizing a control, icon or avatar | `100%`, `auto`, figures sizing a layout region |
`grid-template-*`, `flex`, `top/left/right/bottom` | — | structure entirely |

`width` and `height` split by **what the figure sizes**, never by how large it
is: a 96px avatar is a token, a 40px column is not, so no numeric threshold makes
this call.

**Three questions, in order:**

1. Appears more than once? → a scale.
2. Unique, but a theme author would change it? → a component token.
3. Unique and nobody will ever change it — an optical `-1px`, a `top: 3px`
   aligning a glyph? → a literal, allowlisted with its reason.

Off-step values snap to the nearest step. **When a snap looks worse, the layout
bends, not the ladder**: a value needing `13px` between steps `12` and `14` was
arbitrary to begin with, so the neighbouring padding or height adjusts instead.
Reverting the snap is not an option — it leaves the value off-ladder and the
audit red, which is how a zoo grows back one exception at a time.

**Structural allowlist:** `grid-template-*`, `grid-area`, `flex`, `flex-basis`,
`order`, `aspect-ratio`; `0`, `1`, `1px`, `solid`, `100%`, `100vh`, `100vw`,
`auto`, `none`, `inherit`, `50%` for a circle; `content` strings and counters;
CSS-triangle borders (`border: <n>px solid transparent`, 16 sites); `0.01ms`,
which means motion off. It lives in `tools/ui-contract.php`; `.rules/theme.md`
quotes it. Growing it requires a written reason beside the entry.

## One name, two kinds

```
admin/base.css   --sl-shadow-soft: rgba(32, 75, 102, 0.18);         a colour
lite/base.css    --sl-shadow-soft: 0 1px 2px rgba(42, 48, 60, .12); a shadow
```

Each theme uses its own correctly, so nothing renders wrong — but a rule written
against one is wrong in the other, and no reader can tell which without opening
both. admin's becomes `--sl-shadow-color`, lite's `--sl-shadow-xs`. The tool must
report a shared name whose value is of a different kind; no other check sees it.

## Where token names appear

Eight places. A miss fails silently — a stray custom property is not an error,
and JavaScript falls through to its own default.

| Place | Files | Occurrences | What it does |
|---|---|---|---|
theme CSS | 4 | ~2000 `var()` | reads |
`editors/toastui/skin.css` | 2 | ~15 | reads; part of the package |
`error.html`, repo root | 1 | 39 declared, 34 read | a self-contained third token set |
`lite` templates | 5 | 9 | **writes** a live value inline |
admin PHP | 2 | 16 | reads inside markup it assembles |
JavaScript | 2 | 5 | reads and writes |
`tests/Unit/EditorWindowTest.php` | 1 | 1 | asserts `--sl-dial-count` |
`tools/ui-contract.php`, `docs/WINDOW.md` | 2 | — | the contract and its prose |

`error.html` is not production markup but it ships — it renders when the CMS
cannot, and holds its own `:root`. Migrated once, in batch 8.

## Two kinds of value

**Theme tokens** — declared in the API block, read by CSS. The public API.

**Data tokens** — written from outside by a template or JavaScript, read only by
CSS: comment depth, profile level, group colour, session percentages, popover
arrow offset, scroll distance, dial count. They carry `--sl-d-*` so the tool can
tell "used but never declared" from "declared as API"; without the split, batch 1
deletes them as junk and the profile ring stops moving.

`--sl-size-chip` crosses the other way: `slaed.js` reads it through
`getComputedStyle` and it drives dial geometry. Tokens read by JavaScript are
listed in the contract and cannot be renamed without touching the script in the
same commit.

## Naming grammar

Four laws, each machine-checkable, each with current offenders.

1. **The name says the role, never the value.** Offenders:
   `--sl-overlay-10/12/15/20`, `--sl-h1`…`--sl-h4`.
2. **At most three segments after `--sl-`.** 24 in admin, 39 in lite, up to
   `--sl-login-dropdown-form-margin-left` at five.
3. **One axis, one prefix, from the closed list.** Colour is the default axis and
   carries no prefix — it is the largest family and `color` bought nothing.
4. **State is not an axis, and modifiers do not stack.** Offenders: 18
   `--sl-hover-*` in admin, 9 byte-identical to an existing colour;
   `--sl-hover-opacity`; `--sl-bg-hover-gloss`; `--sl-color-bg-soft-soft`.

Average name length is 20.5 characters; under this grammar about 14.

**Four kinds of token, four laws, nothing outside them.**

**Primitive** — `--sl-<family>-<step>` on a colour ramp, read only by a semantic
token, never by a component.

**Semantic** — an axis from the closed list. Must sit on a ladder step.

**Component** — `--sl-<component>-<prop>`. The prop is closed: `bg`, `border`,
`text`, `radius`, `height`, `width`, `pad-x`, `pad-y`, `gap`, `shadow`, `ring`,
`dur`. The component is **open but declared** in `tools/ui-contract.php` — a
closed list of sixteen cannot cover a CMS, a declared list grows visibly. It
reads a semantic token where one fits and **holds its own literal where none
does**: `--sl-btn-pad-x: var(--sl-space-3)` and `--sl-spin-dur: .8s` are both
correct. What it may never do is hold a value a ladder already covers.

**Categorical** — `--sl-<set>-<member>` for a set with **no order**: winter is
not more than summer, upload is not more than CPU. A ladder cannot apply, so the
law differs — members must be mutually distinguishable, which the tool checks as
a minimum difference. Declared sets: `chart` (`up`, `down`, `cpu`, `ram`) and
`season`. Adding one is a contract change.

This is what keeps the axis lists short: a value fitting no axis is usually a
token of another kind, not a missing axis.

**Level 2 axes.** Closed list; a new axis is a contract change.

| Axis | Form | Roles |
|---|---|---|
colour | `--sl-<role>[-<step>]` | `bg`, `surface`, `border`, `text`, `primary`, `success`, `warning`, `danger`, `accent`, `info`, `on-solid`, `scrim`; steps `-subtle`, `-muted`, base, `-strong`, `-inverse`, never stacked; `surface` also `-sunken`, `-raised` |
spacing | `--sl-space-<n>` | 1…8 |
radius | `--sl-radius-<n>`, `-pill`, `-circle` | 1…3 |
type size | `--sl-font-<role>` | `display`, `h1`…`h4`, `body`, `small`, `micro` |
type face | `--sl-face-<role>` | `body`, `display`, `mono`, `quote` |
line height | `--sl-line-<role>` | `tight`, `normal`, `loose` |
type weight | `--sl-weight-<role>` | `normal`, `medium`, `semibold`, `bold` |
tracking | `--sl-track-<role>` | `tight`, `normal`, `wide` |
shadow | `--sl-shadow-<role>`, `--sl-shadow-color` | `xs`, `raised`, `float`, `overlay`, `inset`, `focus` |
gradient | `--sl-grad-<role>` | `line`, `gloss`, `stripe`, `progress-1`…`5` |
motion | `--sl-time-<role>`, `--sl-ease-<role>` | `fast`, `base`, `slow` / `out`, `in-out`; the animated property name stays literal |
layer | `--sl-z-<role>` | `base`, `dropdown`, `sticky`, `overlay`, `modal`, `popover`, `toast` |
control size | `--sl-size-<role>` | `control`, `chip`, `tile`, `avatar`, `icon-xs`…`icon-lg` |
opacity | `--sl-fade-<role>` | `subtle`, `muted`, `disabled` |
layout | `--sl-layout-<role>` | `container`, `sidebar`, `gutter`, `grid` |
breakpoint | `--sl-bp-<role>` | `sm`, `md`, `lg`, `xl` — canon, not API |

Border width gets no axis: `1px` is structural, anything thicker is a component
decision.

**Colour primitives.** `--sl-<family>-<step>`, steps `50…900`. Each step carries
a **role**, not just a lightness: `50`–`100` backgrounds, `200`–`300` hovered
backgrounds, `400`–`500` borders, `600`–`700` solid fills, `800`–`900` text. Two
near values are one colour when they serve one role, never by distance — and the
roles are what make dark mechanical, since the ramp reverses while roles hold.
Families split by **saturation first, hue second**: a cool `#e5e7eb` is a
neutral, not a blue, whatever its hue. `--sl-gray-*` takes everything below
`S 30`. A primitive exists only if a semantic token points at it.

**Migration map:**

| Today | Under the grammar |
|---|---|
`--sl-color-primary` | `--sl-primary` |
`--sl-color-text-muted` | `--sl-text-muted` |
`--sl-color-bg-soft-soft` | `--sl-bg-muted` |
`--sl-color-bg-soft-dark` / `-darker` / `-divider` | `--sl-bg-inverse` / `--sl-surface-sunken` / `--sl-border-inverse` |
`--sl-color-primary-hover` + 3 `--sl-hover-*` twins | `--sl-primary-strong`; in lite it is **not dead** — its 4 uses in `skin.css` move with it |
`--sl-color-tone-success` | deleted, duplicates `--sl-success` |
`--sl-overlay-10/12/15/20`, lite only | `--sl-scrim-subtle` / `--sl-scrim` / `--sl-scrim-strong`; `0.12` folds into `0.1` |
`--sl-h1`…`--sl-h4` | `--sl-font-h1`…`--sl-font-h4` |
`--sl-shadow-soft`, admin — a colour | `--sl-shadow-color` |
`--sl-shadow-soft`, lite — a shadow | `--sl-shadow-xs` |
`--sl-hover-opacity` | deleted; the state moves to the selector and reads `--sl-fade-subtle` |
`--sl-but-border-light` / `-dark`, `--sl-text-shadow-light` / `-dark` | no new token: a bevel is `--sl-btn-border` above and `--sl-btn-shadow` below; an emboss composes `--sl-on-solid` with `--sl-shadow-color` |
`--sl-radius-control` + `--sl-radius-panel`, both 4px | `--sl-radius-1` |
`--sl-space-xs/-sm/-md/-lg/-xl`, 4/6/8/12/16 | `--sl-space-1…8`; `6px` has no step |
`font-weight: normal` / `bold` / `400` / `700` | `--sl-weight-normal` / `-bold`, one spelling each |
`line-height: 1.4`…`1.6` | `--sl-line-normal` |
`--sl-container` / `--sl-sidebar` / `--sl-gutter` / `--sl-grid-gap` | `--sl-layout-container` / `-sidebar` / `-gutter` / `-grid` |
`--sl-img-placeholder-icon-size` | `--sl-placeholder-height`, a component token |
`--sl-progress-fill-3-start` / `-end` | `--sl-grad-progress-3` |
`--sl-but-neutral-start` / `-end` | `--sl-grad-gloss` |
`--sl-monitor-orange/green/red/blue` | `--sl-chart-up/down/cpu/ram` — the real series; there is no disk |
`--sl-changelog-*`, 8 of 9 duplicate a semantic colour | deleted |
`--sl-login-*`, 27 tokens | 4 component tokens or none |
`--sl-season-winter` | unchanged, a categorical ramp |
`--sl-profile-level`, written by a template | `--sl-d-level` |

**Every mapping is checked against the target name's current occupants.**
`--sl-shadow-strong` already exists in admin holding a composed shadow; mapping a
colour onto it would overwrite a live value with one of a different kind. The
tool reports a collision as an error, never a merge.

**A rename is free only when the value survives it.** `--sl-color-primary` →
`--sl-primary` is free; `--sl-space-sm: 6px` → `--sl-space-<n>` is not, since the
ladder has no 6 and 219 lite sites move. So the scale families — `--sl-space-*`,
`--sl-radius-*`, `--sl-overlay-*` — are renamed **and snapped in one step**, in
batches 3 and 6, not in batch 1.

**Forbidden, each reported by the tool:** a token name not registered in
`tools/ui-contract.php`; an alias of an alias with one use and no theming intent;
a literal outside the API block beyond the allowlist; a custom property in
`theme.css` that could be mistaken for API — scoped ones are allowed only on a
component root, only as internal; two spellings of one intent, as `999px` and
`9999px`; a dead token; a token whose value cannot satisfy the property reading
it; a rename onto an occupied name.

After batch 8 the names are frozen.

## The ladders

Steps sit where values already cluster, which is what makes migration a snap
rather than a redesign. Batch 0 writes them into `tools/ui-contract.php`; prose
quotes them. One ladder serves both themes, values included — measured, the two
already use identical scale values and differ in colour, not in scale.

| Axis | Sites | Values now | Ladder | Displaced |
|---|---|---|---|---|
spacing | 593 | 33 | `2 4 8 10 12 16 20 24` | 41%, of which 87 by 2px |
`font-size` | 194 | 29, `px`/`em` mixed | 10 / 12 / 14 / 16 / 18 / 20 / 24 / 32 | 5 sites above 32px stay exceptions |
`line-height` | 115 | 22 | `1.2` / `1.45` / `1.6`, plus neutral `1` | nine "normal" spellings collapse |
`font-weight` | 107 | 6 spellings | `400` / `500` / `600` / `700` | nothing moves |
`border-radius` | 104 | 16 | `4 8 12`, plus `pill`, `circle` | `999`/`9999` merge; `15`, `21`, `30` are exceptions |
`transition` | 149 | 16 durations | `0.15s` / `0.2s` / `0.35s` | 116 sites already inside 0.14–0.24s |
easing | 140 | 5 | `ease`, one `cubic-bezier` | 133 of 140 already `ease` |
`opacity` | 76 | 10 | `0.8` / `0.55` / `0.45`, plus `0`, `1` | — |
`z-index` | 42 | 12 | 7 named layers | spelling only |
`box-shadow` | 27 literal | 15 | 6 roles + `--sl-shadow-color` | — |
`letter-spacing` | 8 | 5, `px`/`em` mixed | 3 roles | — |
breakpoints | 31 `@media` | 11 | `560` / `768` / `900` / `1200` | `600`, `700`, `720`, `760`, `769`, `901`, `1100` move |
colour | 270 | 114 lite, 83 admin | `--sl-<family>-50…900` | derived by `--ramp` |

`--dist=<property>` per row; `--ramp` for colour.

**Spacing runs on a rhythm of five, not four.** `10px`×100, `20px`×67, `15px`×38,
`5px`×32 is 237 sites against `12px`×49, `8px`×42, `16px`×31, `4px`×20 — 142. A
ritm-4 ladder lands 38% exactly and moves 223 sites by 2px; the measured ladder
lands 59% and moves 87, with the same eight steps.

**Transitions are the worst zoo relative to meaning.** `0.14`…`0.24s` is seven
spellings over 116 sites, indistinguishable to a viewer. Collapsing them shifts
some sites by up to 40ms — intended, and recorded rather than hidden under
"values preserved".

**Colour is two ramps split by saturation.** Of lite's 40 "blues", ~20 are cool
neutrals (`S 12–27`) and ~15 saturated brand blues (`S 54–100`). A hue-first
classifier files the neutral ramp under blue and reports only 14 grays — wrong by
more than half. Neutrals are cheap: their lightness is already almost a ladder
(98 96 93 92 91 82 76 70 68 54 49 40 28 27 20 17 16 11). Saturated blues are not:
collapsing `#0077ff`, `#0866ff` and `#0a66c2` asserts they are one colour, when
they may be a link, a button and a focus ring. Each collapse in that half is
decided per value against what reads it.

**Animation duration gets no ladder.** 41 sites, 18 values, near one each — a
spinner at `0.8s`, a pulse at `2s`, a marquee at `5s`. These are the character of
one animation, not steps of a scale, so they become component tokens
(`--sl-spin-dur`, `--sl-pulse-dur`) and are the single documented exception.

## Baseline

Measured on `d413f314`. Re-measure with the tool before acting on any figure.

| Metric | lite | admin |
|---|---|---|
`theme.css` declarations, custom properties excluded | 4471 | 3121 |
untokenised visual decisions | **1044** | **570** |
  of those, half tokenised | 98 | 83 |
tokens in `base.css` | 169 | 121 |
tokens scoped inside `theme.css` | 90 | 74 |
dead tokens | 2 | not measured |
single-use tokens | 67 | not measured |
`sl-*` classes in CSS | 654 | 464 |
classes never referenced | 15 | 5 |
`!important` | 12 | 16 |
names longer than three segments | 39 | 24 |

`--count` and `--names`.

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

**Cross-theme state.** `--cross`

- 236 selectors exist in both: 115 byte-identical, **121 divergent** — including
  `h5`, `ol`, keyframe stops at `20%` and `50%`, `.sl-highlight`,
  `.sl-preview-meta`, `.sl-alert-flash-bar`, `.sl-progress-line div`,
  `.sl-debug-stats dd`.
- Same-named templates: `fragments` 50 shared / 24 identical, `partials` 13 / 5,
  `layouts` 2 / 0, `pages` 3 / 0. **39 carry different markup**, of which 34 are
  in canon scope.

Package a new theme copies: `lite` 665 files / 3601 KB, `admin` 490 / 1947 KB.

## Batches

### Batch 0 — contract, audit tool, baseline

**Causa.** With independent themes the only thing that travels is convention.
Nothing is enforceable until it is written down and machine-checked, and no batch
that moves pixels may start before there is a picture to compare against.

**Steps.**
- **Put the machine contract in a tracked file.** `.rules/`, `.agents/` and
  `.claude/` are all gitignored, so a clone gets neither the rules nor the hook.
  Axes, ladder steps, allowlist, categorical sets, declared component names and
  the contrast registry go into `tools/ui-contract.php`, committed and quoted by
  `.rules/theme.md`. Prose may lag; the tracked file may not.
- **Implement every flag this document quotes** — `--count`, `--bare`, `--dist`,
  `--dup`, `--names`, `--ramp`, `--cross`, `--markup`, plus `--file`,
  `--migrating`, `--strict`. A missing flag turns its figure back into folklore.
- Write `tools/ui-audit.php`, plain PHP, no dependencies. Beyond the flags it
  reports literals outside the API block; tokens whose value cannot satisfy their
  property; a shared name of differing kind across themes; name collisions;
  alias-of-alias chains; dead and single-use tokens; classes never referenced.
  Non-zero exit when a count grows against the stored baseline.
- Derive the colour ramp with `--ramp`, clustering by saturation then hue, and
  assign each step its role so a collapse is decided by role and reverses cleanly
  for dark. Write the result into `tools/ui-contract.php`.
- **Add the contrast gate over pairs that actually meet**, at AA — 4.5:1, 3:1 for
  large text, in both modes. A static reader cannot decide which pairs meet:
  cascade, inheritance, state and alpha all matter. So the registry is
  **generated** — a Playwright pass walks the manifest, drives the states it
  names, resolves the effective background through ancestors and emits pairs; the
  PHP tool then checks them offline on every run. `getComputedStyle()` returns a
  resolved `rgb()`, not the token, so provenance comes from the CSS side: the
  crawler reports selector plus property and the tool maps it back. A colour
  matching no token means a literal survived. For alpha and gradients the crawler
  records the worst case; the 3:1 threshold needs the rendered size and weight,
  which it records with the pair.

  Not the cross product of every text token against every surface token: run that
  way it reports 39 failures in lite and 16 in admin, worst of them
  `--sl-on-solid` against the page background — a pair that appears nowhere. A
  gate opening with 39 false alarms is switched off in a day.
- Store the baseline in `tools/ui-audit-baseline.json`, committed — it cannot
  live beside the gitignored contract or it would not survive a clone.
- **Re-store the baseline at the end of every batch.** Written once and never
  lowered, it defeats the ratchet: after 570 drops to 300, a regression to 350
  still passes against 570. The tool refuses to lower a baseline while any count
  is above it.
- **Add the marker comment** `/* --- end tokens --- */` at the end of the `:root`
  block in both `base.css`. The one CSS edit this batch makes.
- **Extend `.claude/hooks/lint-edit.php`:** on an edit to
  `templates/*/assets/css/*.css` or `editors/*/skin.css`, run
  `--file --migrating` and print each violation with the token that replaces it.
  `--migrating` is always passed — a half-renamed tree is normal during batches
  1–7, and without it the hook blocks every file it touches. `.claude/` is
  gitignored, so this gate is per-machine and cannot be relied on alone.
- **Write `tests/Unit/ThemeContractTest.php`:** runs the audit against the stored
  baseline, fails on any grown count. It catches what the hook cannot — manual
  edits and merges. There are no git hooks and no CI here, so these are the whole
  enforcement.
- **Write `tests/Unit/UiAuditTest.php` — the tool's own test, on fixtures.** One
  fixture with three literals and one duplicated body must report `3` and `1`; a
  second, whose two identical bodies sit in **different** `@media` contexts, must
  report `0` duplicate groups. Cover each classifier separately.

  The tool is the authority for every number, and a wrong classifier is
  invisible: it produces a plausible count that goes straight into the baseline.
  Two classifiers written while drafting this plan were wrong — one grouped
  colours by hue and filed the neutral ramp under blue, the other counted
  duplicate bodies without `@media` context. Both were caught only by re-deriving
  the number a second way by hand.
- **Track the whole screenshot rig.** `tools/ui-shots.json` holds every URL,
  viewport, logged-in state, baseline path, diff threshold, masked region **and
  the interactions to drive** — hover, focus, open a dropdown, open a modal —
  because a contrast pair existing only on hover is invisible to a crawler that
  never hovers. `tools/ui-shots.mjs` is the runner; PNG baselines live under
  `tools/ui-baseline/` and are committed. Nothing in `c:\tmp\`, or the next
  session has nothing to run.
- Capture at the four breakpoints: front page, article, forum topic, profile,
  private messages, admin sections. The admin login field is `name="pwd"`.
- **Neutralise every cache before capturing.** Empty `storage/cache/pages/*` and
  `storage/cache/templates/*`, and turn off **both** `cache_css` and `css_h` —
  `doCss()` bundles when either is set. The bundle's `cssfp` fingerprint sits
  inside `$conf['derived']['assets']`, so a stylesheet can survive a CSS edit
  until the config is rebuilt, and a warm-cache comparison compares caches.

**Verification.** `UiAuditTest` passes on fixtures **first** — an unverified tool
must not write a baseline. Then the tool runs on both themes, its output becomes
the baseline, and `ThemeContractTest` passes against it. The hook fires on a
deliberate test edit and names the replacement. `phpunit`, `phpstan`,
`php-cs-fixer --dry-run` pass.

The batch's own diff — not the whole working tree — touches `tools/ui-audit.php`,
`tools/ui-contract.php`, `tools/ui-audit-baseline.json`, `tools/ui-shots.json`,
`tools/ui-shots.mjs`, `tools/ui-baseline/*.png`,
`tests/Unit/ThemeContractTest.php`, `tests/Unit/UiAuditTest.php` with its
fixtures, and the marker comment in the two `base.css`, one line each.

### Batch 1 — token hygiene

**Causa.** Later batches create hundreds of new references; cleaning afterwards
means re-touching all of them, and a polluted API is irreversible once themes
ship against it.

**Steps.**
- Delete the **2** dead lite tokens: `--sl-content`, `--sl-h3`.
  `--sl-color-primary-hover` is **not** dead — it is read four times in
  `toastui/skin.css`. Any dead-token scan skipping `skin.css` proposes deleting
  live tokens.
- Split `--sl-shadow-soft`: admin's colour becomes `--sl-shadow-color`, lite's
  shadow becomes `--sl-shadow-xs`. No rendering changes.
- Triage the 67 single-use lite tokens into component tokens that are correct
  (`--sl-field-*` is the model) and instance junk; fold the junk into what it
  aliases. The 27 `--sl-login-*` are first.
- Review the 90 lite and 74 admin tokens scoped inside `theme.css`; mark each
  internal or promote it. Re-measure first — these were 30 and 43 one revision
  ago.
- Apply the migration map, **except the scale families**: `--sl-space-*`,
  `--sl-radius-*` and `--sl-overlay-*` move with their snap in batches 3 and 6.
- Move values written from outside onto `--sl-d-*`.

**Every rename runs across the seven production places in one commit**: 4 theme
CSS, 2 `skin.css`, 5 lite templates, 2 admin PHP, 2 JavaScript,
`EditorWindowTest`, and the contract — `tools/ui-contract.php`, with
`.rules/theme.md` and `docs/WINDOW.md` quoting it. A rename leaving the contract
behind makes the tool reject the name it just enforced. `error.html` is batch 8.

**Verification.** Screenshots **identical**, no exception — every rename here
preserves its value. The tool reports 0 dead tokens, 0 alias chains, 0
collisions, 0 tokens that cannot satisfy their property; every remaining
single-use token is annotated. `phpunit` passes, with `EditorWindowTest` the
canary for the `--sl-d-*` move.

### Batch 2 — admin: the zero-percent axes

**Causa.** Gradients, transitions and `z-index` are tokenised nowhere, so a theme
author cannot restyle 41 gradients, 36 transitions or 16 layers without forking
rules. Values move verbatim, so rendering cannot change by construction.

**Steps.** Extract `--sl-grad-*`, `--sl-time-*`, `--sl-ease-*` and `--sl-z-*` as
a named layer ladder. A transition is tokenised in two parts of three — duration
and easing read tokens, the animated property name stays literal.

**`z-index` does not collapse by distance.** admin holds `0 1 2 3 20 30 40 1000
3000`, lite `0 1 2 4 5 30 100 1000 2001 2005 3000 6000` — twelve values against
seven layers. `2001` and `2005` sit four apart, and that is a deliberate
two-level stack: merge them and order falls to source position, so a popover
slides under its modal and nothing here notices. Two layers merge only when the
elements are shown never to overlap; if they do overlap and there is nowhere to
go, the ladder is missing a layer, which is a contract change.

Duration is the caveat: seven spellings collapse onto `--sl-time-fast`, shifting
some sites by up to 40ms. A screenshot cannot see that, so this batch verifies
motion by measuring computed `transition-duration` against the ladder. `0.01ms`
stays literal. Animation durations are untouched here — they become component
tokens in batch 4.

**Verification.** Screenshots identical. The metric drops by roughly 60. The
`z-index` ladder is checked by the bare-number rule and by opening a modal over a
dropdown over a sticky header. `phpunit` passes.

### Batch 3 — admin: scales

**Causa.** 28 `font-size` values and 8 radius literals are not a system, and
spacing is the largest single block. First batch that changes pixels.

**Steps.** Migrate every axis onto the batch 0 ladders: `font-size` (61),
`padding` (63), `gap` (44), `margin` with `margin-bottom` (46), `border-radius`
(17), `line-height`, `font-weight`, `letter-spacing`, `opacity`. `font-weight` is
free — `normal`→`400`, `bold`→`700` change nothing on screen.

Carries the renames deferred from batch 1: `--sl-space-*` onto `--sl-space-<n>`,
`--sl-radius-control`/`-panel` onto `--sl-radius-1`, `--sl-hover-opacity` onto
`--sl-fade-subtle`. `--sl-overlay-*` is **not** here — it exists only in lite.
`6px` and the 32 `5px` sites move to 4 or 8 site by site, judged against the
screenshot.

Name the four breakpoints and collapse admin's values. `900`/`901` is free;
`1100` moves to `1200`; `700` and `720` move to `768`; `600` moves **down** to
`560` — the measured pair is `600`/`560`, and sending it to `768` would widen the
rule by 168px instead of 40. Each non-free move is reviewed at its own viewport.

**Commit per property group**: `font-size`; `padding`+`gap`; `margin`;
`border-radius`; the bare-number group; breakpoints. A bad snap in one group must
be revertable without losing the others.

**Verification.** Screenshot diff page by page, plus one pass per breakpoint at
its own width; every difference either intended by the snap or resolved by
adjusting the layout around it. The metric drops by roughly 230.

### Batch 4 — admin: the remainder, first etalon closed

**Causa.** A theme 90% tokenised still forces its author into `theme.css`. Only
zero is a contract.

**Steps.** `background` (35), `width` (50), `height` (38), `min-height` (28),
`box-shadow` (12) and the tail — roughly 230 decisions, plus the 83 half
tokenised. `editors/toastui/skin.css` is finished here too, 272 literals; since
the lite copy is byte-identical it is written once and copied in batch 7.
Animation durations become component tokens.

**Dark mode reaches admin here** — the shared plumbing and the admin toggle. It
splits across two batches because lite has no dark tokens until batch 7.

- **The cookie goes through the project's API**: `setCookies('mode', $time,
  $value)` and `getCookies('mode')`, which apply the `$conf['user_c']` prefix.
- **The toggle is a POST with a CSRF token, not a JS cookie write.**
  `setCookies()` sets `httponly`, so a script cannot write this cookie at all and
  a JS toggle would fail silently.
- Read and validate: `light`, `dark` or `auto`; anything else, including absent,
  resolves to `auto`.
- **Every render path gets the `mode` key.** `$adminvars` and `$sitevars` feed
  the layouts, but `core/security.php` renders its own message page from an array
  it builds itself, and the shop builds another. A path missing the key renders
  without the attribute and flashes the wrong mode.
- `data-theme="{{ mode }}"` on `<html>` in admin's `layouts/admin.html` and
  `layouts/bare.html`, plus the toggle and its component tokens.

`light-dark()` returns a **colour**, so only colour tokens carry both values in
one declaration. Shadows and gradients compose from colour tokens instead —
`--sl-shadow-raised: 0 1px 2px var(--sl-shadow-color)`, with `--sl-shadow-color`
holding the `light-dark()`. No component rule mentions the mode; one that cannot
follow is missing a role. This raises the browser baseline to Chrome 123 / Safari
17.5 / Firefox 120, above the `:has()` baseline already assumed.

`templates/lite/fragments/shop-invoice.html` is **out of dark scope**: its
`<head>` links no stylesheet, so it has no tokens to switch.

**Verification.** `--theme=admin` reports **0** untokenised decisions outside the
allowlist, **0** bare numbers in the four properties beyond neutral `0` and `1`,
and **0** values missing their ladder. Contrast holds at AA in both modes.
Screenshots reviewed, `phpunit` passes. From here admin is the working example.

### Batch 5 — lite: the zero-percent axes

Batch 2 on the names admin settled: 21 gradients, 49 transitions, 27 animations,
26 `z-index`. The metric drops by roughly 80.

**Not mechanical.** lite holds twelve distinct `z-index` values against seven
roles, so five merges must each be shown safe. Every pair that could overlap is
opened together: modal over dropdown, popover over modal, sticky header under
both, and the editor, which stacks its own layers inside the page.

### Batch 6 — lite: scales

Batch 3 on the same ladders: `font-size` (140), `padding` (118), `margin` with
`margin-bottom` and `margin-top` (158), `gap` (58), `border-radius` (54),
`line-height`, `font-weight`, `letter-spacing`, `opacity`, plus the deferred
`--sl-space-*` rename over 219 `sm` sites alone. Largest batch at roughly 530
decisions; the per-group commit rule applies with more force.

Two things live only here because they exist only in lite: the fold of
`--sl-overlay-10/12/15/20` onto the three `--sl-scrim-*` roles, where `0.12`
folds into `0.1`; and the `760px` breakpoint, an 8px move to `768`.

### Batch 7 — lite: the remainder, second etalon closed

`height` (74), `width` (72), `box-shadow` (33) and the tail, plus the 98 half
tokenised, plus lite's dark values. `skin.css` is copied from admin rather than
migrated — the two were byte-identical before the work and stay so after; if they
have diverged by then, that divergence is itself a finding.

**The frontend half of the switch lands here**, on the plumbing batch 4 built:
`data-theme` on lite's `layouts/admin.html`, `layouts/bare.html` and
`partials/site-header.html` — the last opens every ordinary page, so without it
the public site never switches — and the toggle in the site header.

**Verification.** The switch is tested as a route, not only as CSS: posting the
toggle sets the cookie and the next response carries the new attribute; the
cookie survives a fresh request; `auto` and an unrecognised value both resolve to
`auto`; both layouts carry the attribute; the first-byte capture shows no flash.
Then `--theme=lite` reports **0** on all checks, in both modes.

### Batch 8 — canon reconciliation, skeleton, freeze

**Causa.** Independence makes divergence permanent. A contradiction shipped in
the etalon is taught to every descendant, and after distribution the names can no
longer be corrected.

**Steps.**
- Resolve the 121 divergent shared selectors: each becomes identical in structure
  with the difference expressed by a token, or gets an allowlist entry with a
  written reason. Neither one nor the other is a bug.
- Resolve the **34** same-named templates in canon scope — 26 `fragments`, 8
  `partials`. The measured total is 39; the other five are 2 `layouts` and 3
  `pages`, which canon does not cover.

  **Audit the call sites first.** A shared name is not a shared contract: the two
  `alert` fragments differ in the keys they accept (`is_flash`, `alert_attr`,
  extra wrappers), and unifying markup without reconciling keys silently drops
  data or changes what is escaped. List every caller, diff the key sets, decide
  the union, then unify.
- **Collapse the 340 redundant rule blocks** — 215 lite, 125 admin — into
  selector lists. Two conditions, both required: one `@media` context, and
  selectors that belong together. `.sl-none` beside `.sl-dial-post` is a utility
  beside a component; merging scatters the component's definition and is refused.
  "Belong together" is a human call, so the gate does not demand zero: every
  group is merged or entered in a **duplicate allowlist** with a reason, and the
  gate fails only on a group that is neither. Merging moves a rule in the
  cascade, so this is its own commit against the baseline.
- Migrate `error.html` onto the settled names. It stays self-contained — it is
  the only surface that renders when the CMS cannot.
- Decide the 15 lite and 5 admin never-referenced classes: delete or document.
  Check `sl-attach-*` (parser `[attach]`) and the seasonal classes for dynamic
  composition in PHP before removing anything.
- Write the theme skeleton. Two existing lists constrain it and they are **not
  the same list**: `checkThemeAssets()` requires CSS, the icon font, system
  avatars and editor skins, for every theme; `TemplateValidationTest` requires
  frontend templates — `fragments/title.html`, `partials/content-list.html`,
  `pages/module.html`, `layouts/app.html` — for frontend themes only, which is
  why `admin` passes without them. The skeleton is their union, split by what
  each kind needs.
- **Write `tests/Unit/ThemeCreationTest.php` — the goal, as a test.** It copies
  an etalon under a unique name, rewrites **only** the API block with a different
  palette, and asserts the audit passes, contrast holds at AA in both modes,
  `checkThemeAssets()` accepts it, and every template compiles through `Template`
  without an undefined token.

  It is **two gates**, because one test cannot do both jobs: this half is static,
  and rendering a real page needs HTTP, so that half rides with the screenshot
  runner, which already walks `tools/ui-shots.json` and runs once more against
  the scratch theme. They share one lifecycle — created once, both gates run,
  then removed in `finally` against a path the harness built, never one it
  guessed. The HTTP half selects the theme before the request and restores it
  after; `getTheme()` caches in a static, so the switch must precede the first
  call.

  A manual look once proves nothing about the day after the freeze, and after the
  freeze the names cannot be corrected.
- Freeze the API and note it in the contract.

**Verification.** The cross-theme diff reports only allowlisted divergences, each
with a reason. `ThemeCreationTest` and the HTTP pass both succeed.

### Batch 9 — markup leaves PHP

**Causa.** A theme cannot restyle what PHP hardcodes, and "one address per
decision" cannot hold while classes, inline styles and tags are assembled outside
the template layer. Independent of the CSS work.

**Scope**, measured with `--markup`. 15 files, 100 occurrences: 47 `class="`, 14
`style="`, 39 literal tags. Three files carry 80.

| File | `class=` | `style=` | tags |
|---|---|---|---|
`core/classes/parser.php` | 4 | 7 | 18 |
`admin/modules/statistic.php` | 14 | 3 | 10 |
`admin/modules/monitor.php` | 23 | — | 1 |
editor drivers, 3 files | 1 | 2 | 3 |
remaining 9 files | 5 | 2 | 7 |

**`modules/rss/` is only partly out of scope.** `modules/rss/index.php` renders a
real HTML page — title, alert, form rows — and stays in scope. Excluded is the
feed markup in `modules/rss/lang/*.php`: **six** files, one per locale, holding
the XML document a theme never styles.

**Steps.** Each site moves into a fragment through `getHtmlFrag()`; PHP passes
data. The monitor chart becomes a fragment taking the four series, the statistic
tables one taking rows, and the parser emits the existing `sl-alert` and code
fragments instead of building its own.

**Fix the escaping contract before moving anything.** Every key carries its
suffix: `*_text` for content the template escapes, `*_attr` for an attribute
value, `*_html` only for output a renderer already made safe. The parser is the
hard case — it handles user content, so a value arriving escaped and escaped
again shows entities, while one skipping both is an injection. The monitor SVG is
the other: its attribute values are numbers and token names, so `*_attr`, never
`*_html`.

**Verification.** `php tools/ui-audit.php --markup`, the same tool — not a second
scanner. It tokenises each PHP file and reports a string literal holding a class
attribute, a style attribute or an HTML tag, excluding `modules/rss/lang/` and
`config/filetype.php`. Tokenising alone is not enough: `'<di' . 'v>'` is two
harmless tokens, so the scanner folds constant concatenations first. A grep
cannot do this, and the shell is not neutral — the default here is PowerShell,
where a Bash pipeline does not run.

**`config/filetype.php` is a mechanism, not a leftover.** It maps each extension
to an HTML template — `<a class="sl-attach sl-attach-[align]"><img
style="max-width:[twidth]px">` — read by `parser.php` and **edited by the
administrator** through `admin/modules/uploads.php`. It is user data that happens
to be markup. Whether those templates should become fragments with fixed classes
and editable values belongs to the parser, not here.

**Done when** no PHP file **hardcodes** a class, an inline style or a tag — with
`config/filetype.php` the one named exception, because its markup is
runtime-editable configuration — and the scan returns nothing.

## Risks

- **Snap regressions.** Batches 3 and 6 change pixels on purpose; the batch 0
  screenshot set is the only defence, and a page never captured is a page where a
  regression ships silently.
- **Cache masking.** `cssfp` in `$conf['derived']['assets']` and
  `storage/cache/pages/*` can each serve a stale result over a correct edit, in
  either direction. Clear both, and disable `cache_css` **and** `css_h`.
- **Rollback granularity.** Batches 3 and 6 touch roughly 760 declarations with a
  human review as the only gate; the per-property-group commit keeps one bad snap
  to one group.
- **Over-unification.** The temptation in batch 8 is to make the admin panel look
  like the site. A difference is legal only when a token expresses it.
- **Premature freeze.** Freezing before both themes reach zero bakes in names the
  remaining literals contradict.
- **An occupied rename target.** Every mapping is checked against the target's
  current occupants; the tool reports a collision as an error.
- **Volume.** 1614 declarations plus 272 in `skin.css` is the bulk of the work.
  Batch 6 alone is roughly 530; splitting it is expected.
- **Consolidation is not compression.** Expect roughly 10–15% fewer rules, not a
  smaller codebase. The deliverable is one address per decision.
