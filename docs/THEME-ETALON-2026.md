# Theme Etalon 2026

Turning the two shipped themes into reference etalons that hundreds of
independent themes are copied from.

Status: batch 7 next. Batches run 7 → 8; batch 9 is independent and may run
alongside 7. A step that is done is deleted from this file, not marked done.

**Every scale in lite is on the ladder.** `count` fell 1209 → 386 and `bare` 107 → 0:
font size, line height, weight, tracking, opacity, radius, padding, margin and gap all
reach their value through a token, the `--sl-space-xs…xl` and `--sl-radius-control`/
`-card` names are gone, the four `--sl-overlay-*` are two `--sl-scrim-*` steps, and
every viewport breakpoint sits on `560 / 768 / 900 / 1200`. What is left in lite is
batch 7's: width, height, animation, shadow, background and the colour tail.

**Two contract changes were needed and both were asked for before they were made.**
The type ladder gained `--sl-font-hero: 48px` above `display`, because a slider
headline and a dashboard number are the largest type a page carries and folding them
onto `display` would size a hero like an h1. The spacing ladder gained `9 10 11` at
`32 40 48`, because lite carries a **second rhythm admin does not have** — the gap
between the sections of a page, measured at 50px six times, 45 and 40 three times
each, 30 five times. Admin has not one spacing atom above 24px, which is why the
ladder had stopped there; a frontend theme reaches further and the steps are declared
in lite alone.

**Three things the tool had been counting are not decisions, and each is now a fixture.**
A descriptor block is not a rule — `var()` is invalid inside `@font-face`, so the weight
of the face could never have been tokenised. The middle of a `clamp()` is the rate the
value travels between two bounds that are decisions themselves; no ladder in the contract
carries it, every step being px, seconds or unitless. And a percentage gap is measured
against the container's width, not against the rhythm, so no `--sl-space-*` step can
express one. All three are in `tests/Fixtures/ui/` with a test that reads the answer off
the fixture.

**`admin` is the first etalon and it is closed.** Every count it controls is at
zero — no untokenised decision in any of its three files, no bare number, no
grammar violation, no dead or unmet name of its own, and no contrast pair below
AA in either mode. What is left in its baseline is what batch 8 owns: 178
duplicate rule bodies, 4 never-referenced classes, 23 `!important`, and the two
names `--sl-float-top` and `--sl-float-left` that both themes read and nothing
writes.

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
Every site baseline had once been captured that way — logged out, under names that
say otherwise. The manifest names `https`, both contexts ignore the certificate a
development stand signs itself, and every state including `profile` and `private`
now carries a baseline taken with a real session, which is what makes it a
measurement of the theme rather than of who was logged in.

**No manifest page opens the editor, so `skin.css` is outside the screenshot gate.**
It is at zero now and checked by the audit alone; a batch that changes it has no
visual net under it.

**The two `skin.css` copies must stay byte-identical, and a test says so.**
`tests/Unit/EditorWindowTest::theWindowKeepsItsStylesUnderItsOwnRoot` asserts it, which
is why batch 2 left the file alone. Batch 3 migrated the ladder names, batch 4 the
remaining 76 decisions, and each copied the result to lite in the same commit,
declaring in lite's API block exactly the names the copied file reads and no more — a
step nothing reads is a dead token, and `dead` is ratcheted at zero. Lite's API block
therefore already carries `--sl-time-fast`, `--sl-ease-out`, `--sl-z-raised`,
`--sl-z-overlay`, `--sl-z-toast`, `--sl-ring-width`, `--sl-ring-gap`, `--sl-tint`,
`--sl-tint-strong` and `--sl-scrim`, which batch 7 extends rather than invents.

**The colour mode is plumbed and admin carries the toggle.** `getThemeMode()` in
`core/system.php` reads the `mode` cookie through `getCookies()` and answers `auto` to
everything it does not recognise; `$adminvars`, `$sitevars` and the message page
`core/security.php` builds itself all carry the key; admin's two layouts write
`data-theme="{{ mode }}"`; and `getAdminModeSwitch()` in `core/admin.php` renders one
POST button with a token that steps system → light → dark → system.
`templates/admin/fragments/mode-switch.html` is the markup. Batch 7 adds lite's four
document templates and the site header toggle on the same plumbing.

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

**The tool learned four things in batch 4, and each one had been silently wrong.**
A `color-mix()` was one opaque colour atom, so `color-mix(in srgb, var(--sl-primary)
10%, transparent)` counted as untokenised however many tokens were inside it, and
`color-mix(in srgb, #0877b1 13%, transparent)` counted the same. Its arguments are
walked now, and the **ratio is a decision of its own kind** — a `mix` — because
otherwise a literal `13%` beside two tokens would pass. `light-dark()` is walked the
same way. `getValueKind()` reads both functions as colours, or a token gaining its dark
half would look like a different kind from the same name in the theme that has not
gained one. `getRgbValues()` resolves a `light-dark()` to the half asked for and
defaults to light, or every categorical distinguishability check quietly measures
nothing and passes. Each is covered by a fixture in `tests/Fixtures/ui/`, because a
wrong classifier writes a plausible number into the baseline and everything downstream
trusts it.

The screenshot rig is `tools/ui-shots.json` and `tools/ui-shots.mjs`:
`--capture` writes the baselines under `tools/ui-baseline/` together with each
state's measured noise floor, `--check` compares the tree against them and fails
past the larger of the manifest threshold and that floor. It diffs on a canvas
inside the page, so the rig carries no image dependency. Credentials are never
stored: it reads `SLAED_UI_USER` and `SLAED_UI_PASS` from the environment and
skips every page needing a session when they are absent. `modes` is what the shots are
captured in and `contrastmodes` what the contrast walk drives, because doubling the
image set to cover a second mode is a cost the pair registry does not carry. `cookie`
is the name the CMS actually reads, which carries the `user_c` prefix — the loop wrote
a bare `mode` before, and a mode cookie under the wrong name changes nothing at all.

Numbers here are the tool's, and drift as work lands.

## Contract

- **Themes are independent.** No inheritance in `Template`; the **158**
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
  the same zero, and it is at zero. The two copies are byte-identical, which
  `EditorWindowTest` enforces, so it is migrated once in admin and copied to lite in
  the same commit. `checkThemeAssets()` requires it when an editor manifest declares a
  skin. `assets/vendor/` stays out of scope.
- **`admin` first**, `lite` mirrors it: admin is smaller, so mistakes are cheap.
- **Canon scope:** CSS, `fragments`, `partials`. Not `layouts` and `pages` — the
  page shells differ by nature.

## The one metric

**Untokenised visual decisions: admin 0, lite 386 → 0.** `--count`

Measured by the tool, which is the authority. Counted over every CSS file that is
not the API block: `theme.css`, the element styles below the marker, and
`skin.css`. A transition counts twice, because its duration and its easing take two
tokens. Split by file:

| | `theme.css` | `skin.css` | `base.css` below the marker |
|---|---|---|---|
admin | 0 | 0 | 0 |
lite | 380 | 0 | 6 |

Monotonic in one direction: **no batch raises a count it controls.** Batch 7 lowers the
untokenised count; 8 and 9 hold it flat.

The ratchet holds `count`, `bare`, `dup`, `names`, `dead`, `alias`, `unsat`,
`unmet`, `scoped`, `clash`, `classes`, `important` and `contrast`. It does **not** hold
`tokens` or `single`: extracting an axis into the API block raises both on
purpose, and a component token that one component reads is correct at one use.
The list lives in `tools/ui-contract.php` under `ratchet`.

`clash` is one name declared twice inside one API block, where the second
declaration silently wins and nothing else would ever say so. Both themes are at
zero, and stayed there while the API blocks moved from 121 and 169 tokens to 291
and 212 — a rename is exactly when a name lands on one already taken.

**A name is not free just because one theme's block has it spare.** `checkNameKinds()`
compares the two blocks and reports a name holding two kinds across them; batch 4 hit
it twice, on `--sl-changelog-border`, which lite spends on a colour, and it does not
catch the worse case — `--sl-drop-width` means a dropdown's width in lite and was
about to mean a drag edge in admin, and both are lengths. Grep the other block before
declaring a name, whatever the tool says.

Counted **per part of a value, not per declaration** — the tool strips `var()`,
drops neutral parts and judges the rest, so this line is already clean:

```css
border: 1px solid var(--sl-border);   /* the decision is the colour */
```

1992 declarations in admin and 2299 in lite already reach every decision through a
token; 0 and 89 more are half done. `--count`

**Second check: bare numbers.** `--bare` — **zero in both themes.** Lite spelled 63
weights six ways, 27 line heights fifteen ways — `1.05`…`1.62` plus `1.428571429` and
`normal` — and 17 opacities seven ways; each family now names its step.

These carry no unit and no colour, so the first counter is blind to them. A bare
number in the four must come from a token; `0` and `1` stay neutral in `opacity`
and `line-height`, and `z-index: 0` is a stacking context and therefore a
decision.

**Third check: repetition.** `--dup`

| | lite | admin |
|---|---|---|
identical bodies | 110 groups | 80 groups |
**redundant blocks** | **266** | **178** |
of them inside `@media` | 23 | 15 |

`display: none` appears 16 times in lite under 16 selectors, `margin: 0` twelve.
These 444 are **candidates, not certainties** — whether selectors belong together
is a human call, so batch 8 merges a group or allowlists it with a reason. What
is certain is that none may be left unexamined. Repetition **with** need is not
counted: `display: flex` appears 122 times because 122 elements are flex
containers.

**Fourth check: a name that cannot invert.** `--names`

**Zero, in both themes.** No `white`, `black`, `light` or `dark` is left in a
name: the bevel that carried the last two is one `--sl-btn-shadow` holding both
edges and the lift under them, and the admin switch reads `--sl-switch-inverse`
off `--sl-on-solid`, which now turns over with the mode. `inverse` passes the law:
"opposite of the page" holds in both modes.

`prefers-color-scheme` still appears zero times and must: admin carries both modes
inside one declaration through `light-dark()`, and `ThemeContractTest` fails on either
`light-dark(` or `prefers-color-scheme` below the marker, in any of the four files.

`--names` reports **admin 0, lite 18** violations over the declared tokens, and
all but three are the fourth law alone — a name not yet registered in
`tools/ui-contract.php`. Every one belongs to the colour tail of batch 7; the scale
families and the login geometry are gone. `--names` prints each with the law it
breaks, which is the work list batch 7 inherits.

**Decision or structure.**

| Property | Tokenised | Stays literal |
|---|---|---|
`padding`, `margin`, `gap`, `inset` | `--sl-space-1…11` | `0`, `auto`, a percentage of the container |
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
and JavaScript falls through to its own default. Inside the four theme CSS files
the `unmet` count now catches it; everywhere else the list below is the only guard.

| Place | Files | Occurrences | What it does |
|---|---|---|---|
theme CSS | 4 | 4253 `var()` — lite `theme.css` 2252, admin 1803, the two `base.css` 198 | reads |
`editors/toastui/skin.css` | 2 | 513 each | reads; part of the package |
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
2. **At most three segments after `--sl-`.** 39 in lite over its API block, up to
   `--sl-login-dropdown-form-margin-left` at five. Admin is at zero, and the law is
   what decides a component's name: `--sl-fm-bar-min-width` is four segments and
   became `--sl-fm-search-width`, because a compound component name plus a `min-`
   prop does not fit and the shorter true name does.
3. **One axis, one prefix, from the closed list.** Colour is the default axis and
   carries no prefix — it is the largest family and `color` bought nothing.
4. **State is not an axis, and modifiers do not stack.** One offender left:
   `--sl-color-bg-soft-soft` in lite.

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
a minimum difference. Declared sets: `chart` (`up`, `down`, `cpu`, `ram`),
`season`, and `progress` (`1`…`5`, the five tones of the level meter, which a poll
paints by option number). Adding one is a contract change.

This is what keeps the axis lists short: a value fitting no axis is usually a
token of another kind, not a missing axis.

**Level 2 axes.** Closed list; a new axis is a contract change.

| Axis | Form | Roles |
|---|---|---|
colour | `--sl-<role>[-<step>]` | `bg`, `surface`, `border`, `text`, `primary`, `success`, `warning`, `danger`, `accent`, `info`, `on-solid`, `scrim`, `tint`; steps `-subtle`, `-muted`, base, `-strong`, `-inverse`, never stacked; `surface` also `-sunken`, `-raised` |
spacing | `--sl-space-<n>` | 1…11; 9 to 11 are the second rhythm of a page and only a frontend theme reaches them |
radius | `--sl-radius-<n>`, `-pill`, `-circle` | 1…3 |
type size | `--sl-font-<role>` | `hero`, `display`, `h1`…`h4`, `body`, `small`, `micro` |
type face | `--sl-face-<role>` | `body`, `display`, `mono`, `quote` |
line height | `--sl-line-<role>` | `tight`, `normal`, `loose` |
type weight | `--sl-weight-<role>` | `normal`, `medium`, `semibold`, `bold` |
tracking | `--sl-track-<role>` | `tight`, `normal`, `wide` |
shadow | `--sl-shadow-<role>`, `--sl-shadow-color` | `xs`, `raised`, `float`, `overlay`, `inset`, `focus` |
gradient | `--sl-grad-<role>` | `line`, `gloss`, `stripe`, `progress-1`…`5` |
motion | `--sl-time-<role>`, `--sl-ease-<role>` | `fast`, `base`, `slow` / `out`, `in-out`; the animated property name stays literal |
layer | `--sl-z-<role>` | `base`, `raised`, `dropdown`, `sticky`, `overlay`, `modal`, `popover`, `toast` |
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
the shadow roles, batch 7 | lite `--sl-field-invalid-ring` and `--sl-field-valid-ring`. One component carries one `ring` and one `shadow`, so a second of either has nowhere to go until the roles are settled |
the colour ramp, batch 7 | lite `--sl-color-text-ink`, `--sl-color-text-link`, `--sl-color-border-stronger`, `--sl-color-brand`/`-banner`/`-strong`, `--sl-color-tone-neutral`/`-primary`, `--sl-login-link-hover-color`, and the eight `--sl-changelog-*` that lite does not share with a semantic colour. Each is one value more than its role has steps, which the ramp folds rather than the grammar renames |

**How admin's nine unsettled shadows and two colour tails were settled**, which is the
shape batch 7 copies. A shadow named after where it is used became the component token
of that place — `--sl-shadow-sidebar` → `--sl-wrap-shadow`, `--sl-shadow-bar` →
`--sl-bar-shadow`. A component needing two of them used both props for what they are:
`--sl-check-ring` is the ring the pointer draws, `--sl-check-shadow` the surface of the
checked box, and `--sl-switch-ring` the ring the switch draws. The four `--sl-focus-*`
ring colours collapsed into one law instead of four values: `--sl-ring-mix: 30%`, with
`--sl-ring-bg` mixing the brand colour at that alpha and a status ring mixing its own
colour at the same alpha at the two sites that need one. `--sl-ring-width` and
`--sl-ring-gap` carry the geometry every `outline` in the theme reads. Of the two
colour tails, `--sl-color-primary-hover-soft` folded onto `--sl-primary-strong` — it
was the worst contrast failure in the theme at 2.41:1 — and `--sl-color-text-soft`
folded onto `--sl-text`, the darker of its two neighbours, because folding a text tone
upward can only raise contrast and folding it down can only lower it.

One row of the map could not be written as it stood. The bevel row asked for
`--sl-btn-border` above and `--sl-btn-shadow` below, but a border and a shadow are
two properties for one edge treatment, and lite already spent `--sl-btn-shadow` on
the control shadow. Batch 5 settled it by composing all three — the inner edge, the
outer edge and the lift — into the single `--sl-btn-shadow` admin already carried,
which is why the map now has no bevel row at all.

**Every mapping is checked against the target name's current occupants — in both
themes.** `--sl-shadow-strong` already exists in admin holding a composed shadow;
mapping a colour onto it would overwrite a live value with one of a different kind.
The tool reports a collision as an error, never a merge. It only sees a collision of
two **kinds**, though: two lengths under one name pass every check and mean different
things, which `--sl-drop-width` nearly became.

**A rename is free only when the value survives it.** `--sl-color-primary` →
`--sl-primary` was free; `--sl-space-sm: 6px` → `--sl-space-3` was not, since the ladder
has no 6 and 53 lite reads moved 2px with it. So a scale family is renamed **and snapped
in one step**, which is how both themes did it, and a tie between two neighbouring steps
is not broken the same way on every ladder.

**On spacing and radius the tie goes up** — `3→4`, `6→8`, `9→10`, `11→12`, `14→16`,
`18→20`, `22→24` — because a rhythm step is read as breathing room and the denser of
two neighbours is the one a reader notices. 61 of the 6px sites moved that way, and
one moved down: the gap of a toolbar link, where going up pushed an already
overflowing language row further off the edge.

**On the type ladder the tie is broken by the siblings**, not by a direction. `11px`
went **down** to 10 at all twelve sites, because every one is a caption standing
beside a 12px sibling and sending both to 12 would have merged two sizes the design
tells apart. `13px` went **up** to 14 at nine sites that are a title or the base font
of a panel, and **down** to 12 at five that are metadata beside a 14 or 16px sibling.
A type step is a rank, so the tie is decided by what it must stay distinguishable
from.

Two element sizes moved further than one step and are named here because no rule
derives them: `h5` took `--sl-font-body` at 14 rather than the nearer 16, which keeps
the heading ladder strictly descending against `h4`; and `ol` lost 6.8px of indent
when `2.2em` snapped to the ladder's top step of 24.

**Three sites had the layout bend instead of the value.** The module grid counted its
gutters as a literal `21px` that no longer matched the snapped gap and dropped four
columns to three; it now reads `calc((100% - (3 * var(--sl-space-3))) / 4)`. The card
of a module and the caption of a file tile each held a right padding sized to clear
the speed dial standing over them — 38px and 40px, which a snap to 24 would have run
the title under. Both now say what the figure is for, in the spelling the sidebar
already used: `calc(var(--sl-size-chip) + var(--sl-space-3) * 2)`. **A padding that
makes room for an absolutely positioned control is not a rhythm step**, and the audit
cannot tell the two apart — only the screenshot can.

**Lite's own three had the same shape, and one was a regression the audit could not see.**
The row of a main list reserved a literal `80px` on the left for a 60px thumbnail pulled
into it by a literal `-80px`; both now read `--sl-thumb-height` plus one rhythm step,
which is the name admin already spends on a square thumbnail. The login profile reserved
`56px` for an avatar in a ring; it now says
`calc(var(--sl-size-avatar) + var(--sl-avatar-border) * 2 + var(--sl-space-4))`. And the
**top menu wrapped onto a second row at 900px**: its link gutter was `15px`, the upward
tie sent it to 16, and eight items times two sides put the last one over the edge. It
takes `--sl-space-5` at 12 instead — the other neighbour, chosen for a measured reason,
which is what admin did once with a toolbar gap. Nothing about it raised a count.

**On opacity the tie is broken by role.** `0.5` sits exactly between `0.55` and `0.45`,
so distance decides nothing. What is switched off takes `--sl-fade-disabled` — a hidden
item, a hidden button under the pointer, a disabled dialog button — and what is merely
secondary takes `--sl-fade-muted`: the unselected dot of a slider pager, a rating widget
beside its own `.active`. The roles of the axis are the tie-break the numbers cannot give.

**Three placements were spelled as margins and are now spelled as placements.** A slider
image, its caption and its pager each carried `left: 50%` beside a negative half-width
margin — `-960px` and twice `-550px`. Each is one `left: calc(50% - <n>px)` now, which is
the same pixel and one address instead of two, and `left` is structure the ladder never
governed. The `max-width: 1400px` bound of the wrap gutter went with them: above 1400 the
`min()` already caps at the container width, so the bound decided nothing.

**Forbidden, each reported by the tool:** a token name not registered in
`tools/ui-contract.php`; a name read and declared nowhere, met by no fallback; an alias of an alias with one use and no theming intent;
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
spacing | 857 | 62 | `2 4 8 10 12 16 20 24 32 40 48` | closed in both themes; steps 9 to 11 are lite's alone |
`font-size` | 294 | 41, `px`/`em` mixed | 10 / 12 / 14 / 16 / 18 / 20 / 24 / 32 / 48 | closed; the 48 step is `hero`, which only lite reads |
`line-height` | 55 | 15 | `1.2` / `1.45` / `1.6`, plus neutral `1` | closed; fifteen spellings collapsed to three |
`font-weight` | 128 | 6 spellings | `400` / `500` / `600` / `700` | closed; nothing moved |
`border-radius` | 120 | 15 | `4 8 12`, plus `pill`, `circle` | closed; `999`/`9999` merged, and `15`, `21`, `30` turned out to be a pill and two circles |
`transition` | 171 durations | 3 | `0.15s` / `0.2s` / `0.35s` | closed in lite with no exception; admin still holds `--sl-knob-dur` on one transition |
easing | 192 | 3 | `ease`, one `cubic-bezier` | closed; `linear` is allowlisted and two overshoots are component `ease` tokens |
`opacity` | 28 | 9 | `0.8` / `0.55` / `0.45`, plus `0`, `1` | closed; the `0.5` tie was broken by role, not by distance |
`z-index` | 36 | 8 | 8 named layers | closed; admin reads all eight roles, lite the seven it can show to overlap |
`box-shadow` | 96 | 34 | 6 roles + `--sl-shadow-color` | — |
`letter-spacing` | 16 | 5, `px`/`em` mixed | 1 role | closed; every site in both themes is an uppercase run and takes `--sl-track-wide` |
breakpoints | admin 4 widths, lite 4 | 4 distinct | `560` / `768` / `900` / `1200` | closed for the viewport; the two `@container` widths of the message split, 800 and 1040, are measured against a panel and the viewport ladder does not govern them |
colour | 87 untokenised | 90 lite tokens, 66 admin | `--sl-<family>-50…900` | derived by `--ramp` |

`--dist=<property>` per row; `--ramp` for colour.

**Spacing runs on both rhythms at once, which is why the ladder mixes them.** Over
836 pixel spacing decisions in the two themes, 345 sit on a multiple of five and
329 on a multiple of four — neither rhythm wins, and a ladder committed to either
one would move about 500 sites. The eight measured steps `2 4 8 10 12 16 20 24`
take **60%** of the sites exactly and leave 338 to snap, and the largest single
value is `10px` at 84 lite sites against `12px` at 50.

**Transitions were the worst zoo relative to meaning, and they are collapsed.**
`0.14`…`0.24s` had been **eight spellings over 140 of the 171 duration sites** —
`0.14 0.15 0.16 0.18 0.19 0.2 0.22 0.24` — indistinguishable to a viewer. Lite
held **81 duration parts over 13 spellings**; 32 were already on a step, 31 moved by
20ms or less — `0.18` up and `0.22` and `0.16` down — and 18 moved further: `0.24`
by 40ms, `0.3` and `0.4` by 50 to `0.35`, the seven `0.5`/`0.6` sites down to it by
150 and 250, and the two longest of all — the poll bar filling at `0.8s` and the
profile ring drawing itself at `0.9s` — down to the same `0.35s`. Those two were
first argued out of the ladder as the character of one element, on the strength of
admin's `--sl-knob-dur`; the ladder took them instead, because an exception granted
by argument is how a zoo grows back. **Admin still spells its own ring `--sl-knob-dur:
1s`, and that divergence is now batch 8's to settle**, in the direction lite has
already taken. Of the five curve spellings, `ease` took 71 parts and stayed,
`linear` is allowlisted, `cubic-bezier(0.2, 0.8, 0.2, 1)` folded onto
`--sl-ease-in-out`, `cubic-bezier(0.2, 0.7, 0.3, 1)` onto `--sl-ease-out`, and the
overshoot `cubic-bezier(0.3, 1.6, 0.5, 1)` became `--sl-vote-ease` because a curve
leaving the 0-1 range is a different kind of motion. All of it is
recorded here rather than hidden under "values preserved", and it is verified by
reading computed `transition-duration`, `transition-delay` and
`transition-timing-function` off the rendered page, because no screenshot sees a
duration.

**Twelve layers became seven roles, and every merge names what it was measured against.**
Lite spelled `0 1 2 4 5 30 100 1000 2001 2005 3000 6000` over 26 sites. Five were already
a role's own value and kept it: `0` base, `1` raised, `30` dropdown, `3000` modal, and the
image popup at `1000` popover. `2` and `5` both mean "painted over the box it sits in" — a
slider arrow, an online badge, a scroll fade, a sticky day header, two corner tool clusters —
and they are one `--sl-z-overlay`. Nothing inside the old `2` set moved against anything else
in it; the only place a `5` meets a `2` is the user card, where the tool chip is placed at
`top: -38px` and the online badge at `top: 20px`, so they never touch. `4` is the session
panel that unfolds over that same card: it must stay above the badge, so it went up to
`--sl-z-dropdown` rather than across. That puts it over the tool chip that used to cover it,
which is safe by measurement and not by argument — the chip ends 12px above the card, while
the panel is pinned `bottom: 36px` with `max-height: calc(100% - 36px)` and cannot reach past
the card's top edge. `6000` had no neighbour above it and became `--sl-z-toast`.

**Four orders changed, and they are named here rather than buried.** The header login form
fell from `1000` to `--sl-z-dropdown` and the top-menu submenu from `2001` to the same,
because that is what both are; they keep their old relative order for the reason that decided
it before, that they tie and `#topmenu` comes later in the document than the header login.
The tooltip's anchored hover panel rose from `100` to `--sl-z-popover` and now paints over
both of those instead of under them — both are pointer-opened and the submenu closes when the
pointer leaves the bar, so keyboard focus is the only way to see the pair at once. The image
popup kept `--sl-z-popover` and so also passed above the submenu. The two fixed edge tabs fell
from `2005` to `--sl-z-popover` and now tie with the tooltip and the popup instead of standing
above them; `partials/site-footer.html` is the last thing both layouts include, so the tie
still resolves the tabs' way.

`--sl-z-sticky` stays undeclared in lite, because nothing there reads it and a step nothing
reads is a dead token.

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
declarations outside the API block, custom properties excluded | 5400 | 4035 |
untokenised visual decisions | **386** | **0** |
  of those, half tokenised | 89 | 0 |
  declarations fully reached through a token | 2299 | 1855 |
bare numbers in the four properties | 0 | 0 |
redundant duplicate blocks | 266 | 178 |
grammar violations | 18 | 0 |
tokens in `base.css` | 212 | 291 |
tokens scoped outside the API block | 103 | 84 |
dead tokens | 0 | 0 |
names read and declared nowhere | 2 | 2 |
single-use tokens | 67 | 146 |
alias chains | 0 | 0 |
tokens that cannot satisfy their property | 0 | 0 |
one name declared twice in the API block | 0 | 0 |
`sl-*` classes in CSS | 757 | 549 |
classes never referenced | 10 | 4 |
classes assembled from a prefix, a human call | 62 | 41 |
`!important` | 19 | 23 |
contrast pairs that really meet on screen | 150 | 124 |
  of those, below AA | 53 | 0 |

Global, not per theme: **0** names holding two kinds across the themes, and
**143** occurrences of markup hardcoded in PHP.

**Contrast.** The registry holds **274 pairs that really meet on screen** — 124 in
admin, 150 in lite — collected by walking every page and state in the manifest.
Of those, **0 in admin and 53 in lite are below AA**, and the ratchet holds both
numbers so they can only fall. Lite's are ordinary near-misses, not structural ones:
`#207fb6` on white at 4.4:1 against a needed 4.5, `#5c9425` on white at 3.67,
`#6e7c8b` on `#f8f9fb` at 4.05. Every one is a colour decision, and batch 7 closes
them in both modes.

**Admin's half of the registry is now measured in both modes, and admin's seventeen
failures were closed by moving five values.** The walk drives `light` and `dark`
through the `mode` cookie, and a pair whose two colours are the same in both modes is
filed once — keying it by mode would have doubled lite's count overnight against a
ratchet that may only fall. Every admin failure wanted the same thing, a darker light
value: `--sl-primary-strong` `#207fb6` → `#1c6e9e`, `--sl-success` `#34b94b` →
`#268637`, `--sl-success-strong` `#24973a` → `#1f8031`, `--sl-text-muted` `#6b7280` →
`#636976`, `--sl-danger` `#e24c4c` → `#cd4545`. Each was derived from the worst ground
it really meets, not chosen: the target is the relative luminance the ratio 4.5 allows
against that ground, and the value is the original scaled down to it. The last failure
was in **dark** and was not a value but a role — the switch label was white on a bright
green track, because `--sl-on-solid` was white in both modes. It now reads
`light-dark(#ffffff, #0b1220)`: in light the solid fill is the dark one and its text is
white, in dark the ramp reverses and its text goes dark.

**A role with two names passes every count and is still a contradiction.** Thirteen
admin rules painted text on a solid brand fill with `var(--sl-bg)` — the coloured
buttons, the sidebar headings, the dashboard panel heads, the current page of the
pager, the changelog date header and both copyrights. In light that is white, which is
also what `--sl-on-solid` is, so nothing had ever said the two names were different.
Under inversion they part: `--sl-bg` becomes the dark page and `--sl-on-solid` becomes
the text that reads on a bright fill. All thirteen now say the role they mean, which is
a no-op in light and a shade darker in dark. No counter would have found this; only
reading the resolved colour of a heading in dark and asking which token gave it.

**The walk cannot see a knob.** A pair whose foreground equals its background exactly
is not a reading anyone can act on — it means an element between the text and the
ground the walk could not follow, a `::after`, a pseudo-element, an image. The switch
label sits on exactly such a knob, and the registry filed the track behind it as its
ground twice. The rig drops a self-pair now, which is a rule and not an exception.

This is what the registry buys. Run as a cross product of every text token
against every surface token it reports pairs that appear nowhere — the plan's
worked example was `--sl-on-solid` against the page background — and a gate that
opens with false alarms is switched off in a day.

**The registry under-covers, and re-generating it raises the count without a
regression behind it.** Re-run at the end of batch 3 it found **266** pairs where the
committed file held 203: `admin-account`, `profile` and `login-field-focus` were not
in it at all, and `private` held 49 of its 84. Those pages render only with a session
and a populated list, so the pairs were always on screen and never measured. 22 of the
new ones are below AA, all in lite on `profile` and `private`. A ratchet counts and
cannot tell coverage from regression, so **the batch that owns colour re-generates its
own half and leaves the other alone** — batch 4 did that for admin by merging its 124
fresh pairs over the committed file and keeping lite's 150 untouched. Batch 7 does the
same for lite, and that is the one moment lite's `contrast` figure may legitimately
move up, with the new pairs listed beside the reason.

Untokenised decisions by property, largest first:

| Property | lite | admin |
|---|---|---|
`width` | 79 | 0 |
`height` | 74 | 0 |
`animation` | 49 | 0 |
`box-shadow` | 36 | 0 |
`background` | 32 | 0 |
`max-width` | 23 | 0 |
`min-height` | 16 | 0 |
`min-width` | 11 | 0 |
`border-width` | 10 | 0 |
`max-height` | 10 | 0 |
`border` | 10 | 0 |
`border-color` | 9 | 0 |
`outline` | 4 | 0 |
`color` | 4 | 0 |
`border-top` | 4 | 0 |

**Motion, layers, gradients and every scale are closed in both themes.** Every lite
transition reaches its duration and its curve through a token, every layer sits on a
named role, every gradient lives at one address, and every figure of type, rhythm and
radius sits on a step. What is left in lite is the colour, size, animation and shadow
work of batch 7.

**`--sl-grad-gloss` holds admin's nothing and lite's one gloss, which is a measured
limit rather than an omission.** In lite it is the single hover sheen five controls
wear, so it fitted the axis whole. In admin the control gloss is one shape —
`to bottom, A 0%, A 48%, B 100%` — under about twenty colour pairs, and CSS can only
parameterise a shape through custom properties set on each element. Those are scoped
declarations, `scoped` is a ratcheted count, and the ratchet exists exactly to stop
decisions moving into scoped properties where no theme author can find them. So
admin's shape stays at its use sites, where every gloss it paints reads two colour
tokens and counts as tokenised. A gradient assigned to one component is
`--sl-<component>-bg`, which is how the admin header rule and lite's three edge
gradients already read.

**`scoped` is a fixed budget, and a batch that needs a new internal must retire one.**
Admin ended batch 4 exactly where it started, at 84, having added
`--sl-admin-tight-item-gap` and `--sl-admin-tight-line-height` — the two names the
sidebar read and nothing declared — and retired three that said nothing: two
`--sl-loading-shadows-a` aliases of the value beside them, and
`--sl-monitor-text-main`, an alias of `--sl-text` with one reader.

**Cross-theme state.** `--cross`

- 328 selectors exist in both: 158 byte-identical, **170 divergent** — including
  `body`, `h5`, `ol`, keyframe stops at `20%` and `50%`, `.sl-highlight`,
  `.sl-preview-meta`, `.sl-alert-flash-bar`, `.sl-progress-line div`,
  `.sl-debug-stats dd`. The number rises while one theme is ahead of the other and
  falls back as the matching batch lands; batch 8 is what settles it. It rose in
  batch 4 exactly because admin went first, and fell by 34 in batch 6 as lite took the
  same ladders — which is what "one theme ahead" looks like from the tool's side.
- Same-named templates: `fragments` 50 shared / 24 identical, `partials` 13 / 5,
  `layouts` 2 / 0, `pages` 3 / 0. **39 carry different markup**, of which 34 are
  in canon scope — 26 `fragments` and 8 `partials`.

Package a new theme copies: `lite` 667 files / 5069 KB, `admin` 491 / 3092 KB.

## Batches

### Batch 7 — lite: the remainder, second etalon closed

The lite colour tail batch 1 left declared is folded here: `--sl-color-text-ink`
and `--sl-color-text-link`, `--sl-color-border-stronger` one unit from
`--sl-border`, the three `--sl-color-brand*`, `--sl-color-tone-neutral` and
`-primary` which are the two service colours of the social icons,
`--sl-login-link-hover-color`, and the eight `--sl-changelog-*` that share no
value with a semantic colour — an island the ramp folds onto the roles.

Three more names arrive from batch 5 and belong to the same fold. `--sl-grad-info-start`
and `-end` were carrying a colour under the gradient prefix, and one of them was also
painting the search button flat, so they could not be inlined into a gradient and had to
take a legal colour name: `--sl-info-subtle` `#78c6fb` and `--sl-info-muted` `#6dbaf7`,
two blues four points of lightness apart. `--sl-grad-success-start` turned out to be
`--sl-success-subtle` spelled twice and collapsed onto it; its partner is now
`--sl-success-muted` `#96d26d`. Whether either pair is two roles or one value written
twice is a question for the ramp, not for the grammar.

`height` (74), `width` (79), `animation` (49), `box-shadow` (36), `background` (32) and
the tail — **386 decisions** — plus the 89 half tokenised, plus lite's dark values.
`skin.css` needs nothing here: batches 3 and 4 already
copied admin's file to lite as they wrote it, because `EditorWindowTest` compares the
two byte for byte. If they have diverged by then, that divergence is itself a finding.

**The frontend half of the switch lands here**, on the plumbing batch 4 built:
`data-theme` on lite's `layouts/admin.html`, `layouts/bare.html` and
`partials/site-header.html` — the last opens every ordinary page, so without it
the public site never switches — and the toggle in the site header. The route, the
cookie, the validation and `getThemeMode()` already exist; so do the three constants
`_MODE_AUTO`, `_MODE_LIGHT` and `_MODE_DARK` in `lang/`, which is where they were put
rather than in `admin/lang/` precisely so this batch reuses them. Lite's own handler
is the piece that is missing: admin's lives in `admin/index.php` under `op=mode` and
answers on `admin.php`, and the frontend needs the same on `index.php`. Extend
`testEveryDocumentTemplateCarriesTheModeAttribute` in `ThemeContractTest` with lite's
four templates when they land.

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
- Resolve the **170** divergent shared selectors: each becomes identical in
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
- **Collapse the 449 redundant rule blocks** — 271 lite, 178 admin — into
  selector lists. Two conditions, both required: one `@media` context, and
  selectors that belong together. `.sl-none` beside `.sl-dial-post` is a utility
  beside a component; merging scatters the component's definition and is refused.
  "Belong together" is a human call, so the gate does not demand zero: every
  group is merged or entered in a **duplicate allowlist** with a reason, and the
  gate fails only on a group that is neither. Merging moves a rule in the
  cascade, so this is its own commit against the baseline.

  **Five groups are allowlisted already and each one narrows what this batch decides.**
  Three came from batch 4 folding two near values into one — the pressed filter beside
  the checked chip, the hovered compact button beside the pager's current page — and
  two are the markdown syntax marks of `skin.css` meeting a faint tone the rest of the
  theme already used. The last of those covers a group of five rules in lite that
  existed before batch 4 and that this batch would otherwise have weighed on its own
  terms; the reason written beside it is a claim to re-read, not a decision to inherit.
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

- **Snap regressions.** A batch that moves pixels on purpose has the committed
  screenshot set as its only defence, and a page never captured is a page where a
  regression ships silently. Batch 3 found four in admin that the audit could not see: a flex grid
  whose gutters were a literal `21px` that no longer matched the snapped gap, which
  dropped four columns to three; a toolbar whose language row was already overflowing
  and overflowed further; and two paddings sized to clear a speed dial, where the snap
  let the title run under the button. None of them raised a count, and the last two
  were found only by resolving every changed declaration to pixels and reading the
  list — the screenshot at `xl` showed titles short enough to hide it. **19 states over four breakpoints are captured
  today** — front page, article, news and forum lists, forum topic, voting,
  content, login, four admin sections, and the hover and focus states the
  manifest drives. A page outside that list is unguarded.

  The profile and the private messages now carry baselines: the site login takes
  with the same credentials as the admin one, and the rig proves a session twice —
  something only a session shows must appear, and the password field must be gone.

  `noise-floor.json` still carries four `admin-users-*` entries for a state the
  manifest no longer names, left over from the `?name=users` module that does not
  exist. `--capture` does not clear them; they are dead weight, not a gate.

  **The baselines are committed, so whatever they show is published.** The IP
  column of the user list and the mail fields of the configuration are masked for
  that reason: a guard against a layout change has no business carrying anyone's
  address into the repository.

  **Dark is unguarded by pixels.** The manifest captures one mode, the one a fresh
  browser answers with, and doubling the set would double 36 MB of committed images
  for a mode only admin has. Dark is guarded by the contrast half of the registry,
  which walks both modes, and by `ThemeContractTest`, which fails on any rule outside
  the API block that names the mode. What neither sees is a dark layout that breaks
  without changing a colour. Batch 7 is where that trade is worth revisiting, because
  after it both themes have the mode.
- **Baseline weight.** The image set is 36 MB, and a scale batch re-captures it
  whole, so each re-capture adds about that much to history permanently. If that
  becomes the wrong trade, the answer is to narrow the manifest, not to stop
  committing baselines — an uncommitted baseline is no baseline at all.
- **Cache masking.** `cssfp` in `$conf['derived']['assets']` and
  `storage/cache/pages/*` can each serve a stale result over a correct edit, in
  either direction. Clear both, and disable `cache_css` **and** `css_h` — both
  are `'0'` in `config/global.php` today, and `config/local.php` must be deleted
  after any hand edit of `config/*` or it serves the old values.
- **The stand's own data moves, and the committed baseline goes stale with it.**
  A `--check` run at the head of batch 3, against a pristine tree, failed on every
  one of the 84 images — including the frontend, which the batch never touches.
  Nothing in the theme had changed: forum reply and view counters had moved, and a
  sidebar block whose file is missing under `blocks/` had begun rendering
  `_BLOCKPROBLEM` where it used to render nothing. Neither is masked. So a batch
  cannot trust the committed baseline as its "before": it must **capture the
  pristine tree first**, keep that capture outside the repository, and compare its
  own two captures. That is what batch 3 did, and it is what made "the frontend
  moved by 0.001% to 0.024%" a statement about the change instead of about the week.

- **Two states never settle between runs, and the noise floor cannot see it.**
  The floor is measured from two renders inside one `--capture` pass, so it catches
  what changes in a second and misses what changes because the run itself changed
  the site. `admin-statistic` carries a generated chart stamped with the time and
  the running hit total, and every page the rig opens increments that total;
  `admin-monitor-knob-hover` at `lg` moved by 327px between two consecutive runs.
  Both are red against any baseline older than the current run. Until the manifest
  stabilises them they are unguarded, and the fix belongs to the manifest, not to a
  theme batch: masking a whole statistics page would hide the regressions the gate
  exists to catch.

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
  and reading its floor. **The frontend states sit at a floor of zero**; the eight
  that need a session carry one between 0.001% and 0.19%, `profile` at `sm` the
  largest. A floor near zero is not proof of stability — see the two states above.

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

- **A batch that changes every page height gets nothing from the image diff, and needs
  a second measurement.** Batch 6 moved the type ladder and the section rhythm, so all
  48 frontend images came back `100% size changed` — true, and empty of information.
  What carried the review instead was a geometry probe driven over the same manifest:
  for every page and viewport it read `scrollWidth - clientWidth`, the page height, and
  the count of elements whose box leaves the viewport, on the pristine tree and on the
  changed one. Horizontal overflow was zero on all 32 states before and after, and the
  clipped count never rose — it fell on one page and was otherwise equal. **The one
  regression of the batch showed up there and nowhere else**: the top menu wrapped onto
  a second row at 900px, which the image diff had drowned and no count could see. A
  scale batch should run that probe, not only `--check`.

- **`profile` and `private` are stale in the committed baseline.** Both render in lite
  and both need a session, so a run without `SLAED_UI_USER` and `SLAED_UI_PASS` skips
  them: batch 6 changed what they look like and could not re-capture them. Their images
  are the pre-batch ones. Whoever next runs the rig with credentials must `--capture`
  and adopt, or the two states guard a page that no longer exists. The admin images are
  untouched, because batch 6 did not touch the admin theme.
- **Rollback granularity.** A scale batch touches hundreds of declarations with a
  human review as the only gate; the per-property-group commit keeps one bad snap
  to one group.
- **Over-unification.** The temptation in batch 8 is to make the admin panel look
  like the site. A difference is legal only when a token expresses it.
- **A name read but never declared is invisible to the gate.** `dead` reports a
  token declared and never read; nothing reports the reverse, and CSS answers an
  undeclared `var()` by dropping the declaration — no error, no warning, no pixel
  that says why. Each theme reads two such names, and they are the same two in both:
  **`--sl-float-top` and `--sl-float-left` are read by both themes and written by
  nothing.** An open
  `.sl-float-panel` is `position: fixed` with `top: var(--sl-float-top)`, so both
  resolve to `auto` and the panel keeps the static position the flow gave it —
  measured at 241 / 324 on the admin user list, where it happens to look right.
  Detached from the scroll it will drift away from the control that opened it. No
  script writes them: `plugins/system/slaed.js` calls `setProperty` for
  `--sl-d-arrow`, `--sl-d-distance` and `--sl-d-duration`, and for nothing else.
  Whoever fixes the placement decides whether the two become `--sl-d-*` data tokens
  or the rule stops asking for them. The gate counts them either way: `unmet` is
  ratcheted at 2 per theme, so the number can only fall.

  The four admin names batch 4 settled show both answers. `--sl-text-subtle` and
  `--sl-color-brand-strong` were read by `skin.css` and declared only in lite, which
  is why the editor looked right there and lost fifteen colours in admin — the first
  became an admin declaration, the second pointed at `--sl-primary-strong`, which is
  what it meant. `--sl-admin-tight-item-gap` and `--sl-admin-tight-line-height` were
  two names missing from a scoped set of six that was clearly written in one sitting;
  they were declared, and the sidebar gained the 4px row gap and the 20px line box the
  author had asked for and never got.
- **Premature freeze.** Freezing before both themes reach zero bakes in names the
  remaining literals contradict.
- **An occupied rename target.** Every mapping is checked against the target's
  current occupants; the tool reports a collision as an error.
- **Volume.** 386 decisions left, all of them lite's and all of them batch 7's. The
  per-batch sizes measured by grouping `--count` by property family:

  | | motion | colour | layer | scales | rest |
  |---|---|---|---|---|---|
  admin | 0 | 0 | 0 | 0 | 0 |
  lite | 49 | 47 | 0 | 0 | 290 |
- **Consolidation is not compression.** Expect roughly 10–15% fewer rules, not a
  smaller codebase. The deliverable is one address per decision.
