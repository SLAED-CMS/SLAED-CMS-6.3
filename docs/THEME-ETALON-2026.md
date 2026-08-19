# Theme Etalon 2026

Turning the two shipped themes into reference etalons that hundreds of
independent themes are copied from.

Status: batch 2 next. Batches run 2 → 8; batch 9 is independent and may run
alongside 2 to 7. A step that is done is deleted from this file, not marked done.

The contract, the tool, the baseline, the screenshot rig and the two gates are in
the tree: `tools/ui-contract.php`, `tools/ui-audit.php`,
`tools/ui-audit-baseline.json`, `tools/ui-contrast.json`, `tools/ui-shots.json`,
`tools/ui-shots.mjs`, `tools/ui-baseline/*.png` with its `noise-floor.json`,
`tests/Unit/UiAuditTest.php` over `tests/Fixtures/ui/`,
`tests/Unit/ThemeContractTest.php`, and `.claude/hooks/lint-edit.php` per machine.
Both `base.css` now carry the `/* --- end tokens --- */` marker.

**The rig runs over `https` now, because the scheme is part of the login.**
`setCookies()` marks the session cookie `secure` whenever `homeurl` is `https`, so
a run over plain `http` fills the form, posts it and is handed a cookie the
browser drops: the page comes back carrying the login block and no error to read.
Every site baseline had been captured that way — logged out, under names that say
otherwise — and `profile` and `private` had no baseline at all. The manifest names
`https` and both contexts ignore the certificate a development stand signs itself,
and the 84 baselines were re-captured from the pre-batch tree, which is what makes
them a measurement of the theme rather than of who was logged in.

Two changes in admin are expected of batch 1 and are **not** covered by that gate,
because no manifest page opens the editor: `skin.css` read `--sl-color-bg-soft-alt`,
a name only lite ever declared, and read `--sl-shadow-soft` as a `box-shadow`, a
colour no `box-shadow` can render. Five backgrounds and one shadow now appear where
the file always meant them to be. Opening the editor is what batch 4 adds.

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
   starting point, not the numbers written here.
3. Do the work. Commit per property group where the batch says so.
4. Run the batch's verification in full before reporting done. Where it says
   "screenshots identical", that is `node tools/ui-shots.mjs --check` after
   emptying `storage/cache/pages/*` and `storage/cache/templates/*`; where a
   batch moves pixels on purpose, review the diff and then `--capture` to adopt
   it. `vendor/bin/phpunit --filter ThemeContract` is the gate that fails on a
   grown count.
5. Re-store the baseline with `php tools/ui-audit.php --store` and commit it.
   Written once and never lowered, a baseline lets a regression from 300 back to
   350 pass against an old 570. The tool refuses to store while a ratcheted count
   is above what it holds.

**Every number here names the command that produced it.** A figure without one is
not to be trusted and must be re-derived before it sizes any work — six figures
in earlier drafts were wrong and one was invented, and none could be checked
without redoing the analysis by hand.

The commands are flags of `tools/ui-audit.php`:

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

Plus `--file` and `--migrating` for the hook, `--strict` for a theme with no
baseline entry, and `--store` to rewrite the baseline from a full run. With no
flag at all the tool prints every count for both themes and exits non-zero when a
ratcheted one grew.

Two files feed it beside the CSS. `tools/ui-contract.php` is the tracked
contract — axes, ladders, allowlist with a written reason per entry, categorical
sets, declared components, the ramp, the ratchet list, the markup exclusions.
`tools/ui-contrast.json` is the generated pair registry: `node
tools/ui-shots.mjs --contrast` walks the manifest, drives the states it names,
resolves each text colour against the background it really sits on — through
ancestors, and through the worst stop of a gradient — and the PHP tool checks
those pairs offline on every run.

The screenshot rig is `tools/ui-shots.json` and `tools/ui-shots.mjs`:
`--capture` writes the baselines under `tools/ui-baseline/` together with each
state's measured noise floor, `--check` compares the tree against them and fails
past the larger of the manifest threshold and that floor. It diffs on a canvas
inside the page, so the rig carries no image dependency. Credentials are never
stored: it reads `SLAED_UI_USER` and `SLAED_UI_PASS` from the environment and
skips every page needing a session when they are absent.

Numbers here are the tool's, and drift as work lands.

## Contract

- **Themes are independent.** No inheritance in `Template`; the **188**
  byte-identical rules they share stay duplicated on purpose. `--cross`
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
  declares a skin. 1657 lines, **231 untokenised decisions** per theme — and the
  two copies are byte-identical, so it is migrated once in admin and copied.
  `assets/vendor/` stays out of scope.
- **`admin` first**, `lite` mirrors it: admin is smaller, so mistakes are cheap.
- **Canon scope:** CSS, `fragments`, `partials`. Not `layouts` and `pages` — the
  page shells differ by nature.

## The one metric

**Untokenised visual decisions: admin 1003, lite 1637 → 0.** `--count`

Measured by the tool, which is the authority. Counted over every CSS file that is
not the API block: `theme.css`, the element styles below the marker, and
`skin.css`. The earlier figures — admin 570, lite 1044 — covered `theme.css`
alone and counted a transition as one decision; the tool counts its duration and
its easing apart, because they take two tokens. Split by file:

| | `theme.css` | `skin.css` | `base.css` below the marker |
|---|---|---|---|
admin | 739 | 231 | 33 |
lite | 1364 | 231 | 42 |

Monotonic in one direction: **no batch raises a count it controls.** Batches 2–7
lower the untokenised count; 1, 8 and 9 hold it flat. "Every batch lowers it"
would be false for three of the nine.

The ratchet holds `count`, `bare`, `dup`, `names`, `dead`, `alias`, `unsat`,
`scoped`, `clash`, `classes`, `important` and `contrast`. It does **not** hold
`tokens` or `single`: extracting an axis into the API block raises both on
purpose, and a component token that one component reads is correct at one use.
The list lives in `tools/ui-contract.php` under `ratchet`.

`clash` is one name declared twice inside one API block, where the second
declaration silently wins and nothing else would ever say so. Both themes are at
zero, and stayed there while the API blocks fell from 121 and 169 tokens to 105
and 154 — a rename is exactly when a name lands on one already taken.

Counted **per part of a value, not per declaration** — the tool strips `var()`,
drops neutral parts and judges the rest, so this line is already clean:

```css
border: 1px solid var(--sl-border);   /* the decision is the colour */
```

1174 declarations in admin and 1296 in lite already reach every decision through a
token; 54 and 83 more are half done.

**Second check: bare numbers.** `--bare` — admin 107, lite 150

| Property | lite sites / values | admin sites / values | Spelled |
|---|---|---|---|
`font-weight` | 71 / 6 | 55 / 6 | `normal` and `400`, `bold` and `700` — four weights, six spellings |
`line-height` | 33 / 15 | 22 / 10 | `1.05`…`1.62` plus `1.428571429` and `normal` — fifteen ways to say it |
`z-index` | 29 / 14 | 19 / 10 | — |
`opacity` | 17 / 7 | 11 / 7 | — |

These carry no unit and no colour, so the first counter is blind to them. A bare
number in the four must come from a token; `0` and `1` stay neutral in `opacity`
and `line-height`, and `z-index: 0` is a stacking context and therefore a
decision.

**Third check: repetition.** `--dup`

| | lite | admin |
|---|---|---|
identical bodies | 122 groups | 83 groups |
**redundant blocks** | **276** | **181** |
of them inside `@media` | 24 | 14 |

`display: none` appears 16 times in lite under 16 selectors, `margin: 0` twelve.
These 457 are **candidates, not certainties** — whether selectors belong together
is a human call, so batch 8 merges a group or allowlists it with a reason. What
is certain is that none may be left unexamined. Repetition **with** need is not
counted: `display: flex` appears 122 times because 122 elements are flex
containers.

**Fourth check: a name that cannot invert.** `--names`

**Zero, in both themes.** No `white`, `black`, `light` or `dark` is left in a
name: the bevel that carried the last two says `--sl-but-border-inner` and
`-outer`, which is where they sit and not what colour they are, and the admin
switch reads `--sl-switch-inverse` off `--sl-on-solid` — the same white today and
the right one under inversion. `inverse` passes the law: "opposite of the page"
holds in both modes.

Neither theme has a dark mode yet — `prefers-color-scheme` appears zero times,
while `prefers-reduced-motion` appears 14 and `focus-visible` 40. Dark is a
second value inside the same declaration, so it costs nothing structurally, and
no name stands in its way any more.

`--names` reports **admin 71, lite 102** violations over the declared tokens, and
every one is the fourth law alone — a name not yet registered in
`tools/ui-contract.php`. Each belongs to a family whose batch has not run: the
gradients and progress fills of batches 2 and 5, the `--sl-space-*` and
`--sl-radius-*` scales of batches 3 and 6, the composed shadows and ring colours
of batch 4, and the colour tails of batches 4 and 7. `--names` prints each with
the law it breaks, which is the work list those batches inherit.

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
CSS-triangle borders (`border: <n>px solid transparent`, 22 sites); `0.01ms`,
which means motion off. It lives in `tools/ui-contract.php`; `.rules/theme.md`
quotes it. Growing it requires a written reason beside the entry.

## Where token names appear

Eight places. A miss fails silently — a stray custom property is not an error,
and JavaScript falls through to its own default.

| Place | Files | Occurrences | What it does |
|---|---|---|---|
theme CSS | 4 | 2481 `var()` — lite `theme.css` 1309, admin 1085, the two `base.css` 87 | reads |
`editors/toastui/skin.css` | 2 | 295 each | reads; part of the package |
`error.html`, repo root | 1 | 39 in its `:root`, 41 read | a self-contained third token set; the two extra are `--sl-alert-c` and `--sl-alert-tint`, scoped on the alert root as internals, which is legal |
`lite` templates | 5 | 9 | **writes** a live value inline |
admin PHP | 2 | 16 | reads inside markup it assembles |
JavaScript | 2 | 5 | reads and writes |
`tests/Unit/EditorWindowTest.php` | 1 | 1 | asserts `--sl-d-count` |
`tools/ui-contract.php`, `docs/WINDOW.md` | 2 | — | the contract and its prose |

`error.html` is not production markup but it ships — it renders when the CMS
cannot, and holds its own `:root`. Migrated once, in batch 8.

## Two kinds of value

**Theme tokens** — declared in the API block, read by CSS. The public API.

**Data tokens** — written from outside by a template or JavaScript, read only by
CSS: comment depth, profile level, group colour, session percentages, popover
arrow offset, scroll distance, dial count. They carry `--sl-d-*` so the tool can
tell "used but never declared" from "declared as API"; without the split a dead
token scan deletes them as junk and the profile ring stops moving.

`--sl-size-chip` crosses the other way: `slaed.js` reads it through
`getComputedStyle` and it drives dial geometry. Tokens read by JavaScript are
listed in the contract and cannot be renamed without touching the script in the
same commit.

## Naming grammar

Four laws, each machine-checkable, each with current offenders.

1. **The name says the role, never the value.** Offenders:
   `--sl-overlay-10/12/15/20`, `--sl-h1`…`--sl-h4`.
2. **At most three segments after `--sl-`.** 17 in admin and 39 in lite over the
   API blocks, up to `--sl-login-dropdown-form-margin-left` at five.
3. **One axis, one prefix, from the closed list.** Colour is the default axis and
   carries no prefix — it is the largest family and `color` bought nothing.
4. **State is not an axis, and modifiers do not stack.** Offenders: 18
   `--sl-hover-*` in admin, 11 byte-identical to an existing token;
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

**What the map has left.** Everything else it named is in the tree; these wait on
the batch that owns their value, because the rename is not free until that batch
moves the value with it.

| Waiting on | Names |
|---|---|
gradients, batches 2 and 5 | `--sl-progress-*` and `--sl-line-gradient` in both, `--sl-bg-footer-stripe` in admin, `--sl-grad-info/success-*`, `--sl-bg-hover-gloss` and the `--sl-but-*` bevel in lite |
the spacing and radius ladders, batches 3 and 6 | `--sl-space-xs/-sm/-md/-lg/-xl` onto `--sl-space-1…8`; `--sl-radius-control`/`-panel`/`-soft`/`-card` onto `--sl-radius-1…3`; `--sl-overlay-10/12/15/20` onto `--sl-scrim-*`, lite only, with `0.12` folding into `0.1`; `--sl-hover-opacity` onto `--sl-fade-subtle` |
the type and size ladders, batches 3 and 6 | admin `--sl-size-tile-icon`, both `--sl-img-placeholder-icon-size`, lite `--sl-size-meta-row` and the login geometry — `--sl-login-field-pad-x`/`-y`, `--sl-login-dropdown-form-left`/`-width`/`-max-width`, `--sl-login-dropdown-offset-x`, each a figure a breakpoint overrides |
the shadow roles, batch 4 | admin `--sl-shadow-sidebar`, `--sl-shadow-bar`, `--sl-shadow-hover-soft`, `--sl-shadow-input-active`, `--sl-hover-shadow-input`, `--sl-focus-primary`, `--sl-focus-danger`, `--sl-focus-success`, `--sl-focus-success-ring`; lite `--sl-field-invalid-ring` and `--sl-field-valid-ring`. One component carries one `ring` and one `shadow`, so a second of either has nowhere to go until the roles are settled |
the colour ramp, batches 4 and 7 | admin `--sl-color-text-soft`, `--sl-color-primary-hover-soft`, `--sl-color-primary-center`; lite `--sl-color-text-ink`, `--sl-color-text-link`, `--sl-color-border-stronger`, `--sl-color-brand`/`-banner`/`-strong`, `--sl-color-tone-neutral`/`-primary`, `--sl-login-link-hover-color`, and the eight `--sl-changelog-*` that lite does not share with a semantic colour. Each is one value more than its role has steps, which the ramp folds rather than the grammar renames |

Two rows of the map could not be written as they stood. `--sl-img-placeholder-icon-size`
is the glyph inside the placeholder and not the box, so `--sl-placeholder-height`
took the box that is measured twice and the glyph waits for the type ladder. The
bevel row asked for `--sl-btn-border` above and `--sl-btn-shadow` below, but lite
already spends `--sl-btn-shadow` on the control shadow, so the two edges say
`--sl-but-border-inner` and `-outer` until batch 5 composes them.

**Every mapping is checked against the target name's current occupants.**
`--sl-shadow-strong` already exists in admin holding a composed shadow; mapping a
colour onto it would overwrite a live value with one of a different kind. The
tool reports a collision as an error, never a merge.

**A rename is free only when the value survives it.** `--sl-color-primary` →
`--sl-primary` was free; `--sl-space-sm: 6px` → `--sl-space-<n>` is not, since the
ladder has no 6 and 219 lite sites move. So the scale families — `--sl-space-*`,
`--sl-radius-*`, `--sl-overlay-*` — are renamed **and snapped in one step**, in
batches 3 and 6.

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
rather than a redesign. They live in `tools/ui-contract.php`; prose
quotes them. One ladder serves both themes, values included — measured, the two
already use identical scale values and differ in colour, not in scale.

| Axis | Sites | Values now | Ladder | Displaced |
|---|---|---|---|---|
spacing | 857 | 62 | `2 4 8 10 12 16 20 24` | 40% off step, `10px` the largest single value |
`font-size` | 294 | 41, `px`/`em` mixed | 10 / 12 / 14 / 16 / 18 / 20 / 24 / 32 | sites above 32px stay exceptions |
`line-height` | 55 | 15 | `1.2` / `1.45` / `1.6`, plus neutral `1` | fifteen spellings collapse to three |
`font-weight` | 128 | 6 spellings | `400` / `500` / `600` / `700` | nothing moves |
`border-radius` | 120 | 15 | `4 8 12`, plus `pill`, `circle` | `999`/`9999` merge; `15`, `21`, `30` are exceptions |
`transition` | 171 durations | 20 | `0.15s` / `0.2s` / `0.35s` | 140 sites already inside 0.14–0.24s |
easing | 192 | 11 | `ease`, one `cubic-bezier` | 145 already `ease`; six distinct curves collapse to one |
`opacity` | 28 | 9 | `0.8` / `0.55` / `0.45`, plus `0`, `1` | — |
`z-index` | 36 | 14 | 7 named layers | spelling only, except where a stack is deliberate |
`box-shadow` | 96 | 34 | 6 roles + `--sl-shadow-color` | — |
`letter-spacing` | 16 | 5, `px`/`em` mixed | 3 roles | — |
breakpoints | admin 8 widths, lite 11 | 12 distinct | `560` / `768` / `900` / `1200` | admin `600`, `700`, `720`, `1100`; lite `600`, `720`, `760`, `769`, `800`, `901`, `1040`, `1400` |
colour | 87 untokenised | 90 lite tokens, 66 admin | `--sl-<family>-50…900` | derived by `--ramp` |

`--dist=<property>` per row; `--ramp` for colour.

**Spacing runs on both rhythms at once, which is why the ladder mixes them.** Over
836 pixel spacing decisions in the two themes, 345 sit on a multiple of five and
329 on a multiple of four — neither rhythm wins, and a ladder committed to either
one would move about 500 sites. The eight measured steps `2 4 8 10 12 16 20 24`
take **60%** of the sites exactly and leave 338 to snap, and the largest single
value is `10px` at 84 lite sites against `12px` at 50.

**Transitions are the worst zoo relative to meaning.** `0.14`…`0.24s` is **eight
spellings over 140 of the 171 duration sites** — `0.14 0.15 0.16 0.18 0.19 0.2
0.22 0.24` — indistinguishable to a viewer. Collapsing them shifts
some sites by up to 40ms — intended, and recorded rather than hidden under
"values preserved".

**Colour is two ramps split by saturation.** `--ramp` files lite's colours as
**gray 42, blue 15, green 10, red 9, orange 6, violet 5, teal 3**, and admin's as
gray 27, blue 12, green 7, red 7, orange 5, teal 4, violet 4. The 15 saturated
brand blues are what the plan predicted; the cool neutrals that a hue-first
classifier would file under blue are in gray, where they belong. Neutrals are
cheap: their lightness is already almost a ladder (100 98 96 93 92 91 86 82 70 49
46 40 27 26 20 17 16 13 11 0). Saturated blues are not:
collapsing `#0077ff`, `#0866ff` and `#0a66c2` asserts they are one colour, when
they may be a link, a button and a focus ring. Each collapse in that half is
decided per value against what reads it.

**Animation duration gets no ladder.** 56 sites over both themes, 18 values, near
one each — `0.13s 0.22s 0.45s 0.55s 0.6s 0.8s 0.9s 1.1s 1.2s 1.5s 1.8s 1.9s 2s
2.6s 4s 5s 6s` and the allowlisted `0.01ms`: a spinner at `0.8s`, a pulse at
`2s`, a marquee at `5s`. These are the character of
one animation, not steps of a scale, so they become component tokens
(`--sl-spin-dur`, `--sl-pulse-dur`) and are the single documented exception.

## Baseline

Measured by `php tools/ui-audit.php`, over `base.css`, `theme.css` and
`skin.css`, and stored in `tools/ui-audit-baseline.json`. Re-measure with the
tool before acting on any figure; every count below is the one the ratchet holds.

| Metric | lite | admin |
|---|---|---|
declarations outside the API block, custom properties excluded | 5403 | 4026 |
untokenised visual decisions | **1637** | **1003** |
  of those, half tokenised | 83 | 54 |
  declarations fully reached through a token | 1296 | 1174 |
bare numbers in the four properties | 150 | 107 |
redundant duplicate blocks | 276 | 181 |
grammar violations | 102 | 71 |
tokens in `base.css` | 154 | 105 |
tokens scoped outside the API block | 109 | 88 |
dead tokens | 0 | 0 |
single-use tokens | 55 | 31 |
alias chains | 0 | 0 |
tokens that cannot satisfy their property | 0 | 0 |
one name declared twice in the API block | 0 | 0 |
`sl-*` classes in CSS | 757 | 547 |
classes never referenced | 10 | 4 |
classes assembled from a prefix, a human call | 62 | 41 |
`!important` | 19 | 23 |
contrast pairs that really meet on screen | 150 | 53 |
  of those, below AA | 53 | 14 |

Global, not per theme: **0** names holding two kinds across the themes, and
**143** occurrences of markup hardcoded in PHP.

**Contrast.** The registry holds **203 pairs that really meet on screen** — 53 in
admin, 150 in lite — collected by walking every page and state in the manifest.
Of those, **14 in admin and 53 in lite are below AA** today, and the ratchet holds
both numbers so they can only fall. They are ordinary near-misses, not structural
ones: `#207fb6` on white at 4.4:1 against a needed 4.5, `#5c9425` on white at
3.67, `#6e7c8b` on `#f8f9fb` at 4.05. Every one is a colour decision, so batches
2 to 7 own them and batch 4 and 7 close them in both modes.

This is what the registry buys. Run as a cross product of every text token
against every surface token it reports pairs that appear nowhere — the plan's
worked example was `--sl-on-solid` against the page background — and a gate that
opens with false alarms is switched off in a day.

Untokenised decisions by property, largest first:

| Property | lite | admin |
|---|---|---|
`padding` | 213 | 121 |
`font-size` | 191 | 103 |
`transition` | 166 | 144 |
`margin` | 103 | 40 |
`height` | 98 | 61 |
`width` | 88 | 68 |
`border-radius` | 83 | 35 |
`gap` | 80 | 65 |
`font-weight` | 71 | 57 |
`margin-bottom` | 56 | 25 |
`animation` | 55 | 26 |
`margin-top` | 45 | 12 |
`box-shadow` | 42 | 21 |
`background` | 39 | 29 |
`line-height` | 33 | 22 |
`z-index` | 29 | 19 |
`min-height` | 19 | 32 |

**No `transition` and no `z-index` reaches any value through a token** — 47 admin
and 63 lite transition declarations, 19 and 29 layers, not one of them reading a
token. Gradients are the different case, and the earlier "0% of 62 gradient
sites" was wrong: all 39 admin and 23 lite gradient sites already read colour
tokens for their stops, and only one per theme still holds a literal. What is
missing is a name for the **gradient**, which is spelled out at each use site
instead of living at one address as `--sl-grad-*`.

**Cross-theme state.** `--cross`

- 332 selectors exist in both: 188 byte-identical, **144 divergent** — including
  `body`, `h5`, `ol`, keyframe stops at `20%` and `50%`, `.sl-highlight`,
  `.sl-preview-meta`, `.sl-alert-flash-bar`, `.sl-progress-line div`,
  `.sl-debug-stats dd`.
- Same-named templates: `fragments` 50 shared / 28 identical, `partials` 13 / 6,
  `layouts` 2 / 0, `pages` 3 / 2. **32 carry different markup**, of which 29 are
  in canon scope — 22 `fragments` and 7 `partials`.

Package a new theme copies: `lite` 667 files / 5069 KB, `admin` 491 / 3092 KB.

## Batches

### Batch 2 — admin: the zero-percent axes

**Causa.** No transition, no layer and no gradient has a name of its own, so a
theme author cannot restyle **144 transition decisions, 19 layers or 39 gradient
sites** without forking rules. The gradients already read colour tokens for their
stops; what they lack is one address for the gradient. Values move verbatim, so
rendering cannot change by construction — except where a duration collapses,
which is stated below.

**Steps.** Extract `--sl-grad-*`, `--sl-time-*`, `--sl-ease-*` and `--sl-z-*` as
a named layer ladder. The gradient names admin still declares — `--sl-line-gradient`,
`--sl-bg-footer-stripe`, the five `--sl-progress-fill-*` triples and their track —
fold into that axis here, together with `--sl-color-primary-center`, which exists
only as the centre stop of the header line. A transition is tokenised in two parts of three — duration
and easing read tokens, the animated property name stays literal.

**`z-index` does not collapse by distance.** admin holds `0 1 2 3 10 20 30 40
1000 3000 10000`, lite `0 1 2 3 4 5 30 100 1000 2001 2005 3000 6000 10000` — ten
and fourteen values against seven layers. `2001` and `2005` sit four apart, and
that is a deliberate
two-level stack: merge them and order falls to source position, so a popover
slides under its modal and nothing here notices. Two layers merge only when the
elements are shown never to overlap; if they do overlap and there is nowhere to
go, the ladder is missing a layer, which is a contract change.

Duration is the caveat: eight spellings collapse onto `--sl-time-fast`, shifting
some sites by up to 40ms. A screenshot cannot see that, so this batch verifies
motion by measuring computed `transition-duration` against the ladder. `0.01ms`
stays literal. Animation durations are untouched here — they become component
tokens in batch 4.

**Verification.** Screenshots identical. The metric drops by roughly **195** —
176 motion decisions and 19 layers, measured by grouping `--count` by property
family. The `z-index` ladder is checked by the bare-number rule and by opening a
modal over a dropdown over a sticky header. `phpunit` passes.

### Batch 3 — admin: scales

**Causa.** 28 `font-size` values and 8 radius literals are not a system, and
spacing is the largest single block. First batch that changes pixels.

**Steps.** Migrate every axis onto the contract ladders: `font-size` (103),
`padding` (121 with its directional forms), `gap` (65), `margin` with
`margin-bottom` and `margin-top` (77), `border-radius` (35), `line-height` (22),
`font-weight` (57), `letter-spacing` (7), `opacity` (11) — **529 decisions**,
grouped from `--count` by property family. `font-weight` is free —
`normal`→`400`, `bold`→`700` change nothing on screen.

Carries the renames deferred from batch 1: `--sl-space-*` onto `--sl-space-<n>`,
`--sl-radius-control`/`-panel`/`-soft`/`-card` onto `--sl-radius-1…3`,
`--sl-hover-opacity` onto `--sl-fade-subtle`, and the two figures with no step of
their own — `--sl-size-tile-icon` at 32px, which is `--sl-size-icon-lg` in lite
and 28px here, and `--sl-img-placeholder-icon-size` at 48px, a font size above
every step of the type ladder. `--sl-overlay-*` is **not** here — it exists only in lite.
`6px` and the 32 `5px` sites move to 4 or 8 site by site, judged against the
screenshot.

Name the four breakpoints and collapse admin's values. admin holds eight widths:
`560`, `600`, `700`, `720`, `768`, `900`, `1100`, `1200`, four of them already on
the ladder. `1100` moves to `1200`; `700` and `720` move to `768`; `600` moves
**down** to `560`, because sending it to `768` would widen the rule by 168px
instead of 40. Each non-free move is reviewed at its own viewport. `901` is a
lite value and belongs to batch 6.

**Commit per property group**: `font-size`; `padding`+`gap`; `margin`;
`border-radius`; the bare-number group; breakpoints. A bad snap in one group must
be revertable without losing the others.

**Verification.** Screenshot diff page by page, plus one pass per breakpoint at
its own width; every difference either intended by the snap or resolved by
adjusting the layout around it. The metric drops by roughly **530**, and this is
the largest admin batch, not batch 4.

### Batch 4 — admin: the remainder, first etalon closed

**Causa.** A theme 90% tokenised still forces its author into `theme.css`. Only
zero is a contract.

**Steps.** `background` (29), `width` (68), `height` (61), `min-height` (32),
`box-shadow` (21), the colour tail (36 colour decisions in all) and the rest —
**281 decisions**, plus the 54 half tokenised. The names batch 1 left declared
are settled here with them: `--sl-shadow-sidebar`, `--sl-shadow-bar`,
`--sl-shadow-hover-soft`, `--sl-shadow-input-active`, `--sl-hover-shadow-input`
and the four `--sl-focus-*`, none of which fits a role while one component holds
one `shadow` and one `ring`; and `--sl-color-text-soft`,
`--sl-color-primary-hover-soft`, each one value more than its role has steps.
`skin.css` also reads two names admin never declared — `--sl-color-brand-strong`
and `--sl-size-icon-md` — which is why the editor there paints with the browser
default in two places. `editors/toastui/skin.css` is
finished here too, 231 decisions; since the lite copy is byte-identical it is
written once and copied in batch 7. Animation durations become component tokens.

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

Batch 2 on the names admin settled: 23 gradient sites, 226 motion decisions, 29
layers. The metric drops by roughly **255**.

**Not mechanical.** lite holds fourteen distinct `z-index` values — `0 1 2 3 4 5
30 100 1000 2001 2005 3000 6000 10000` — against seven roles, so seven merges
must each be shown safe. Every pair that could overlap is
opened together: modal over dropdown, popover over modal, sticky header under
both, and the editor, which stacks its own layers inside the page.

### Batch 6 — lite: scales

Batch 3 on the same ladders: `font-size` (191), `padding` (213 with its
directional forms), `margin` with `margin-bottom` and `margin-top` (204), `gap`
(80), `border-radius` (83), `line-height` (33), `font-weight` (71),
`letter-spacing`, `opacity` (17), plus the deferred `--sl-space-*` rename over
219 `sm` sites alone. Largest batch of the plan at **973** decisions; the
per-group commit rule applies with more force, and splitting it is expected.

The lite figures batch 1 left are placed here: `--sl-size-meta-row`, the login
field padding, and `--sl-login-dropdown-form-left`/`-width`/`-max-width` with
`--sl-login-dropdown-offset-x`, which are the four a breakpoint overrides to put
the dropdown on a narrow screen — each must keep an address a media query can
reach.

Two things live only here because they exist only in lite: the fold of
`--sl-overlay-10/12/15/20` onto the three `--sl-scrim-*` roles, where `0.12`
folds into `0.1`; and the breakpoints lite does not share with admin — `760`
(15 rules) and `769` move 8px to `768`, `901` (7 rules) moves 1px to `900`, `800`
(15 rules) and `1040` need a direction chosen and reviewed at their own width,
and the single `1400` rule is an exception or moves to `1200`.

### Batch 7 — lite: the remainder, second etalon closed

The lite colour tail batch 1 left declared is folded here: `--sl-color-text-ink`
and `--sl-color-text-link`, `--sl-color-border-stronger` one unit from
`--sl-border`, the three `--sl-color-brand*`, `--sl-color-tone-neutral` and
`-primary` which are the two service colours of the social icons,
`--sl-login-link-hover-color`, and the eight `--sl-changelog-*` that share no
value with a semantic colour — an island the ramp folds onto the roles.

`height` (98), `width` (88), `box-shadow` (42), `background` (39) and the tail —
**409 decisions** — plus the 97 half tokenised, plus lite's dark values. `skin.css` is copied from admin rather than
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
- Resolve the **135** divergent shared selectors: each becomes identical in
  structure with the difference expressed by a token, or gets an allowlist entry
  with a written reason. Neither one nor the other is a bug.
- Resolve the **29** same-named templates in canon scope — 22 `fragments`, 7
  `partials`. The measured total is 32; the other three are 2 `layouts` and 1
  `pages`, which canon does not cover.

  **Audit the call sites first.** A shared name is not a shared contract: the two
  `alert` fragments differ in the keys they accept (`is_flash`, `alert_attr`,
  extra wrappers), and unifying markup without reconciling keys silently drops
  data or changes what is escaped. List every caller, diff the key sets, decide
  the union, then unify.
- **Collapse the 458 redundant rule blocks** — 276 lite, 182 admin — into
  selector lists. Two conditions, both required: one `@media` context, and
  selectors that belong together. `.sl-none` beside `.sl-dial-post` is a utility
  beside a component; merging scatters the component's definition and is refused.
  "Belong together" is a human call, so the gate does not demand zero: every
  group is merged or entered in a **duplicate allowlist** with a reason, and the
  gate fails only on a group that is neither. Merging moves a rule in the
  cascade, so this is its own commit against the baseline.
- Migrate `error.html` onto the settled names. It stays self-contained — it is
  the only surface that renders when the CMS cannot. Its `:root` holds 39 tokens
  and the page reads 41; the two extra, `--sl-alert-c` and `--sl-alert-tint`, are
  scoped on the alert root as internals and stay that way.
- Decide the **10** lite and **4** admin never-referenced classes: delete or
  document. The tool reports separately the classes named nowhere in one piece
  but whose prefix appears — 41 in admin and 62 in lite, assembled from a suffix
  by PHP — and those are a human call, never a deletion the count justifies.
  Check `sl-attach-*` (parser `[attach]`) and the seasonal classes for dynamic
  composition in PHP before removing anything.
- Write the theme skeleton. Two existing lists constrain it and they are **not
  the same list**. `checkThemeAssets()` in `core/system.php` requires, for every
  theme: `assets/css/base.css`, `assets/css/theme.css`, the two Bootstrap-icons
  files under `assets/vendor/`, `images/avatars/system/{user,guest,deleted}.svg`,
  the `images/avatars/presets/` directory, and then — per editor manifest that
  declares a `theme` block — `assets/editors/<id>/skin.css` when the manifest
  declares a skin, plus every `partials/<name>.html` it lists.
  `TemplateValidationTest` requires frontend templates —
  `fragments/title.html`, `partials/content-list.html`, `pages/module.html`,
  `layouts/app.html` — for frontend themes only, which is why `admin` passes
  without them. The skeleton is their union, split by what each kind needs.
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

**Scope**, measured with `--markup`. 21 files, 143 occurrences: 44 `class="`, 11
`style="`, 88 literal tags. Three files carry 136.

| File | `class=` | `style=` | tags |
|---|---|---|---|
`core/classes/parser.php` | 4 | 7 | 55 |
`admin/modules/monitor.php` | 23 | — | 1 |
`admin/modules/statistic.php` | 11 | — | 12 |
`core/admin.php` | 1 | — | 4 |
editor drivers, 3 files | 1 | 2 | 1 |
remaining 14 files | 4 | 2 | 15 |

The earlier draft put the parser at 18 tags. It emits 55: `<li>`, `<tr>`,
`</td>`, `<blockquote><p title="`, `<pre><code`, `<code>`, `<p>` and their
closers, each one an opening or a closing tag hardcoded in PHP.

**Language files are out of scope, all of them.** The draft excluded
`modules/rss/lang/*.php` as the XML feed a theme never styles; the same holds for
every `lang/` directory, because a language file defines translated sentences and
the `<br>` or `<fieldset>` inside one is part of that text and moves with the
translation, not with a fragment. Counting them put 358 tags across 59 files into
a scan whose job is to find markup PHP **assembles**. The exclusion and its
reason live in `tools/ui-contract.php` under `markup.exclude`.

**`modules/rss/index.php` stays in scope** — it renders a real HTML page, title,
alert and form rows.

Two things the scanner ignores on purpose, each written into `getMarkupKind()`: a
regular expression that *matches* markup does not emit it, and an XML element is
not something a theme styles, so the tag test names HTML elements rather than
accepting anything shaped like a tag. Without the first, `core/system.php` reads
as 55 hits; without the second, its sitemap `<url><loc>` reads as markup.

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

- **Snap regressions.** Batches 3 and 6 change pixels on purpose; the committed
  screenshot set is the only defence, and a page never captured is a page where a
  regression ships silently. **19 states over four breakpoints are captured
  today** — front page, article, news and forum lists, forum topic, voting,
  content, login, four admin sections, and the hover and focus states the
  manifest drives. A page outside that list is unguarded.

  **The profile and the private messages are in the manifest and have no
  baseline.** The site login does not take with the credentials this machine has,
  and the rig now says so and skips them, rather than writing the anonymous page
  under their name — which is what the first version did, silently, because it
  proved the session with `.sl-user-card`, a class the logged-out page also
  carries. A session is now proved twice: something only a session shows must
  appear, and the password field must be gone. Whoever has a frontend account
  captures those two.

  The admin user list was captured from `?name=users`, a module that does not
  exist — four blank images that a noise floor of zero called stable. It is
  `?name=account`, and a baseline under 6 KB beside neighbours of 200 KB is worth
  a look before it is trusted.

  **The baselines are committed, so whatever they show is published.** The IP
  column of the user list and the mail fields of the configuration are masked for
  that reason: a guard against a layout change has no business carrying anyone's
  address into the repository.
- **Baseline weight.** The image set is 36 MB, and batches 3 and 6 re-capture it
  whole, so each re-capture adds about that much to history permanently. If that
  becomes the wrong trade, the answer is to narrow the manifest, not to stop
  committing baselines — an uncommitted baseline is no baseline at all.
- **Cache masking.** `cssfp` in `$conf['derived']['assets']` and
  `storage/cache/pages/*` can each serve a stale result over a correct edit, in
  either direction. Clear both, and disable `cache_css` **and** `css_h` — both
  are `'0'` in `config/global.php` today, and `config/local.php` must be deleted
  after any hand edit of `config/*` or it serves the old values.
- **A page that is not the same twice.** The site rotates content per request: a
  random FAQ line in `.sl-head-marquee`, a random poll in `.sl-vote`, a random
  related-article list in `.sl-related-list`, a view counter, the generation time
  in `.sl-generates`, and the debug sections, whose row count follows the number
  of SQL queries the request happened to run. Left alone they change the page
  height, and a diff of the whole page becomes a diff of the dice — measured, the
  article page moved by 20% to 100% between two consecutive loads. The manifest
  answers with two lists: `mask` hides what moves inside a box of stable size,
  `drop` takes out of layout what changes size.

  Masking is a claim, so the rig checks it. **`--capture` renders every page a
  second time and stores the difference between the two as that state's noise
  floor** in `tools/ui-baseline/noise-floor.json`; `--check` reports only past
  that floor, and both commands print every state whose floor is above the
  threshold. A page nobody managed to stabilise is therefore visibly unguarded
  instead of quietly passing. Adding a page to the manifest means capturing it
  and reading its floor. **59 of the 60 states sit at a floor of zero**; only
  `admin-config` at `sm` carries one, at 0.41%.

- **A render that is not the same twice either.** Three separate faults, each
  found by re-running the rig against its own output and each producing a diff
  that looked exactly like a theme change:
  - A full-page screenshot scrolls by itself, so the first capture asked the lazy
    images to load and the second found them decoded — 37% of the front page at
    `lg`, with an identical DOM. The runner walks each page to the bottom and
    waits for every image and every `@font-face` before it shoots. That walk reads
    the page height **once**: two admin pages grow as they are scrolled, and a
    loop that re-reads the height never reaches the bottom.
  - Motion was switched off with `animation-duration: 0.01ms`. That does not stop
    an infinite animation, it makes it cycle as fast as the compositor can draw,
    and the frame a screenshot catches is then chosen by the scheduler — a page
    whose DOM was identical over four seconds and whose pixels were not, at 21%
    and 37%. It is `animation: none` now, and the caret on a focused field is
    hidden for the same reason.
  - The page keeps repainting for about two seconds after load, so a fixed settle
    rendered one of two pages at random. **The rig now shoots when two consecutive
    screenshots match**, not when a timer runs out, and says so when a state never
    settles instead of writing whatever it had.

  A visual gate has to be able to tell a change in the theme from a change in the
  run. None of these three could be told apart by looking at one run.
- **Rollback granularity.** Batches 3 and 6 touch roughly 760 declarations with a
  human review as the only gate; the per-property-group commit keeps one bad snap
  to one group.
- **Over-unification.** The temptation in batch 8 is to make the admin panel look
  like the site. A difference is legal only when a token expresses it.
- **Premature freeze.** Freezing before both themes reach zero bakes in names the
  remaining literals contradict.
- **An occupied rename target.** Every mapping is checked against the target's
  current occupants; the tool reports a collision as an error.
- **Volume.** 2642 decisions, of which 462 are in the two `skin.css` copies and
  are done once. Batch 6 alone is 973; splitting it is expected. The per-batch
  sizes measured by grouping `--count` by property family:

  | | motion | colour | layer | scales | rest |
  |---|---|---|---|---|---|
  admin | 176 | 36 | 19 | 529 | 245 |
  lite | 226 | 51 | 29 | 973 | 358 |
- **Consolidation is not compression.** Expect roughly 10–15% fewer rules, not a
  smaller codebase. The deliverable is one address per decision.
