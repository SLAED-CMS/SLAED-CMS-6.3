# Theme Etalon 2026

Work plan for turning the two shipped themes into reference etalons that hundreds
of independent themes are copied from.

Status: planned, nothing implemented. Run the batches in order 0 → 9; batch 10 is
independent and may run alongside 3 to 8. Batch 0 changes no CSS, batch 1 changes
no rendering, and every later batch depends on both. Update this line as batches
land.

No line numbers anywhere in this document on purpose: every reference names the
file, the selector, the token or the function it points at, and that name is what
to search for.

## Goal

A new theme is created by copying an etalon and editing **one file**:
`templates/<theme>/assets/css/tokens.css`. Nothing else needs to be touched to
get a different look. That is the whole target, and it is measurable: every
visual decision must have exactly one address.

## Decisions already taken

Recorded so they are not re-litigated.

- **Themes stay independent.** No inheritance chain in `Template`, no shared
  `core.css`. A theme is a self-contained package. Accepted cost: the 115
  byte-identical rules the two themes share stay duplicated on purpose, and a fix
  in one theme does not propagate to the other.
- **The engine is not touched.** `Template::getFile()` and `checkFile()` are not
  touched, so the security boundary around theme paths does not move, and the
  template grammar is not extended. The PHP that batch 10 cleans is the markup it
  writes by hand, not the engine.
- **One canon, many skins.** Class names, token names, component semantics and
  rule structure are single and shared by convention. Values are per theme.
- **Three CSS files per theme:** `tokens.css` (tokens only — the public API and
  the only file a theme author edits), `base.css` (`@font-face`, reset, element
  styles), `theme.css` (components, zero literals).
- **`admin` is etalonised first**, `lite` mirrors it afterwards. `admin` is
  smaller and further along, so the contract and the audit tool get shaken out
  where mistakes are cheap.
- **Canon scope.** It covers CSS, `fragments` and `partials`. It does not cover
  `layouts` and `pages`: the page shell of the admin panel and of the site differ
  by nature.

## Measured baseline

Taken 2026-08-18 on a tree that still carries the uncommitted window-canon work,
and produced by one classifier so the rows add up. These numbers move as normal
development lands — that work alone added roughly 700 lines to the two
`theme.css` files during a single day. Batch 0 re-measures on a committed tree and
stores its own baseline; the table is the order of magnitude to expect, not a
constant. What the audit tool must reproduce is whatever it measured last,
otherwise the tool is wrong and not the tree.

| Metric | lite | admin |
|---|---|---|
`theme.css` declarations, custom properties excluded | 4471 | 3121 |
structural properties skipped | 218 | 179 |
no visual decision left | 3209 | 2372 |
  of those, reaching a decision through a token | 1046 | 867 |
**untokenised visual decisions** | **1044** | **570** |
  of those, already half tokenised | 98 | 83 |
tokens declared in `base.css` | 168 | 120 |
tokens declared inside `theme.css` (scoped) | 30 | 43 |
dead tokens | 3 | not measured |
single-use tokens | 67 | not measured |
`sl-*` classes in CSS | 654 | 464 |
classes never referenced anywhere | 15 | 5 |
`!important` | 11 | 15 |

Untokenised decisions by property, the twelve largest:

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

How many different spellings one intent currently has — the argument for scales,
and the reason a snap is unavoidable:

| Axis | distinct values in lite | in admin |
|---|---|---|
`font-size` | 33 | 28 |
`border-radius` | 24 | 8 |
`transition` | 43 | 27 |
gradients | 20 | 25 |
`z-index` | 12 | 9 |
`box-shadow` | 11 | 6 |

Gradients, `transition` and `z-index` reach none of those values through a token
today: 0% of 62 gradient sites, 0% of 91 transitions, 0% of 42 layers.

Cross-theme state, which the canon has to resolve:

- 236 selectors exist in both themes: 115 with a byte-identical body, **121
  divergent**. The divergent set includes plain element rules (`h5`, `ol`),
  keyframe stops (`20%`, `50%`), `.sl-highlight`, `.sl-preview-meta`,
  `.sl-alert-flash-bar`, `.sl-progress-line div`, `.sl-debug-stats dd`.
- Same-named templates: `fragments` 50 shared names, 27 identical; `partials` 12
  shared, 5 identical; `layouts` 2 shared, 0 identical; `pages` 3 shared, 2
  identical. **33 same-named templates carry different markup.**
- Breakpoints are ad hoc and contradictory: `768px` and `760px`, `900px` and
  `901px`, plus `560px` and `1400px`.
- Radius spells one intent several ways: `999px` (11×) and `9999px` (1×) both
  mean "pill"; `8px` (13×) competes with `--sl-radius-card: 10px`.

Package size a new theme copies today: `lite` 665 files / 3601 KB (157 html, 409
svg, 4 css, 2 woff2), `admin` 490 files / 1947 KB (106 html, 338 svg, 4 css, 1
woff2).

## The one metric

**Untokenised visual decisions in `theme.css`: admin 570, lite 1044 → 0.**

Monotonic, machine-checked, no aesthetic argument possible. Every batch lowers it
and no batch may raise it.

The count is taken **per part of a value, not per declaration**. A composite
value can be finished while still carrying digits:

```css
border: 1px solid var(--sl-border);   /* clean: the decision is the colour */
padding: var(--sl-space-3) var(--sl-space-4);
```

1046 declarations in lite and 867 in admin already reach every decision they make
through a token. An audit tool that reads whole values calls the first line a
violation and demands that `1px` be removed, which is nonsense — so the tool
strips the `var()` references, drops the neutral parts, and judges what is left.
Zero means no visual decision without a token, not no digits in the file.

A further 98 declarations in lite and 83 in admin are half done: they carry a
token and an untokenised part side by side. Those are the cheapest to finish and
the easiest to miss by eye.

**What the metric does not see.** Decisions carried by a bare number — `z-index`,
`opacity`, unitless `line-height`, `font-weight` — match no unit and no colour, so
this counter is blind to them. The layer ladder and the opacity roles are enforced
by a second, small check in the audit tool: a bare number in those properties must
come from a token. Without it, batch 3 could report success while 42 hand-written
`z-index` values remain.

**What is a decision, and what is structure.** The token carries the scale step;
the property consumes it. Properties are many, scales are few — `padding`,
`margin`, `gap` and `inset` all read one `--sl-space-*` ladder rather than owning
three families of their own.

| Property | Tokenised | Stays literal |
|---|---|---|
`padding`, `margin`, `gap`, `inset` | `--sl-space-1…8` | `0`, `auto` |
`border-radius` | `--sl-radius-1…3`, `-pill`, `-circle` | `0`, `50%` for a circle |
`border` | the colour only | `1px`, `solid` |
`font-size` | `--sl-font-*` | — |
`line-height` | `--sl-line-*` | `1` |
`box-shadow` | the whole value, `--sl-shadow-*` | — |
`transition` | `--sl-time-*`, `--sl-ease-*` | — |
`width`, `height`, `min-*`, `max-*` | `--sl-size-*` for controls | `100%`, `auto`, layout figures |
`grid-template-*`, `flex`, `top/left/right/bottom` | — | structure entirely |

`width` carries a number 127 times in lite and almost all of them are layout: a
theme author never touches them, and a token per site would bloat the API for
nothing.

**The three questions that decide.** Applied in order, they make the call
reproducible instead of a matter of taste:

1. Does the value appear more than once? → it belongs to a scale.
2. Unique, but would a theme author want to change it? → a component token,
   `--sl-btn-pad-x`.
3. Unique and nobody will ever change it — an optical nudge of `-1px`, a `top: 3px`
   that lines a glyph up with its text? → a literal, entered in the allowlist with
   its reason beside it.

Anything that does not sit on a step **snaps to the nearest one**. A new step is a
contract change, not a local decision; that is what keeps the API short.

Excluded by contract, because they express structure and not appearance:

- `grid-template-columns`, `grid-template-rows`, `grid-area`, `flex`,
  `flex-basis`, `order`, `aspect-ratio`
- the neutral value set: `0`, `1px` borders, `solid`, `100%`, `100vh`, `100vw`,
  `auto`, `none`, `inherit`, `50%` used for a circle
- `content` strings and counters

The allowlist lives in `.rules/theme.md` and in the audit tool as one shared
list. Growing it requires a written reason next to the entry.

## Where token names appear

Not only in CSS. Any rename reaches five places, and a rename that misses one of
them fails silently — a stray inline property is not an error, and JavaScript
falls through to its own literal default.

| Place | Files | Occurrences | What it does |
|---|---|---|---|
theme CSS | 4 | ~2000 `var()` | reads |
`lite` templates | 5 | 9 | **writes** a live value through an inline style |
admin PHP | 2 | 16 | reads inside markup it assembles |
JavaScript | 2 | 5 | reads and writes |
`demo/*.html` | 66 | 6499 | showcase pages |

The audit tool therefore scans `*.css`, `*.html`, `*.js` and `*.php`, and every
rename batch carries the `demo/` pass with it.

## Two kinds of tokens

**Theme tokens.** Declared in `tokens.css`, read by CSS. This is the public API a
theme author edits.

**Data tokens.** Written from outside — a template inline style or JavaScript —
and only read by CSS: comment depth, profile level, group colour, session
percentages, popover arrow offset, scroll distance, dial count. A theme author
never sets them and must not look for them in `tokens.css`.

They carry their own prefix, `--sl-d-*`, so the audit tool can tell "used but
never declared" from "declared as API". Without that split, batch 2 would delete
them as single-use junk and the profile ring would silently stop moving.

One token crosses the line in the other direction: `--sl-size-chip` is read by
`slaed.js` through `getComputedStyle` and drives the speed dial geometry, not
only its look. Tokens read by JavaScript are marked as such in `.rules/theme.md`
and cannot be renamed without touching the script in the same batch.

## Naming grammar

Four laws. Each one is machine-checkable, and each one has current offenders.

1. **The name says the role, never the value.** A theme changes values; the name
   stays. Offenders: `--sl-overlay-10/12/15/20`, `--sl-h1`…`--sl-h4`.
2. **At most three segments after `--sl-`.** Offenders: 17 tokens in admin and 39
   in lite, up to `--sl-login-dropdown-form-margin-left` at five.
3. **One axis, one prefix, from the closed list below.** Colour is the default
   axis and carries no prefix at all, because it is the largest family and the
   word `color` bought nothing. A new axis needs a contract change.
4. **State is not an axis, and modifiers do not stack.** `hover`, `focus` and
   `active` live in the selector. Offenders: the 18 `--sl-hover-*` tokens in
   admin, of which 9 are byte-identical duplicates of an existing colour, and
   `--sl-color-bg-soft-soft` in lite.

Average name length today is 20.5 characters. Under this grammar it lands around
14.

**Level 1, primitives.** `--sl-<hue>-<step>`: `--sl-blue-600`, `--sl-gray-100`.
Declared in `tokens.css`, never read by a component. A primitive exists only if
a semantic token points at it.

**Level 2, semantic axes.** The closed list:

| Axis | Form | Closed set |
|---|---|---|
colour | `--sl-<role>[-<step>]` | roles: `bg`, `surface`, `border`, `text`, `primary`, `success`, `warning`, `danger`, `accent`, `info`, `on-dark`; ladder: `-subtle`, `-muted`, base, `-strong`, `-inverse` |
spacing | `--sl-space-<n>` | 1…8 on a ritm of 4 |
radius | `--sl-radius-<n>`, `--sl-radius-pill`, `--sl-radius-circle` | 1…3 |
type size | `--sl-font-<role>` | `display`, `h1`…`h4`, `body`, `small`, `micro` |
type face | `--sl-face-<role>` | `body`, `display`, `mono`, `quote` |
line height | `--sl-line-<role>` | `tight`, `normal`, `loose` |
shadow | `--sl-shadow-<role>` | `raised`, `overlay`, `inset`, `focus` |
gradient | `--sl-grad-<role>` | `line`, `gloss`, `stripe`, `progress-1`…`5` |
motion | `--sl-time-<role>`, `--sl-ease-<role>` | `fast`, `base`, `slow` / `out`, `in-out` |
layer | `--sl-z-<role>` | `base`, `dropdown`, `sticky`, `overlay`, `modal`, `popover`, `toast` |
control size | `--sl-size-<role>` | `control`, `chip`, `tile`, `avatar`, `icon-xs`…`icon-lg` |
opacity | `--sl-fade-<role>` | `muted`, `disabled` |
breakpoint | `--sl-bp-<role>` | `sm`, `md`, `lg` |

`@media` cannot read a custom property, so the breakpoint tokens are documented
constants the audit tool enforces, not values the cascade resolves. A theme
author who edits `--sl-bp-md` changes nothing until the media queries are edited
too, and `.rules/theme.md` says so in place.

Border width gets no axis: `1px` is in the structural allowlist and anything
thicker is a component decision.

**Level 3, component tokens.** `--sl-<component>-<prop>`, where `prop` comes from
a closed list: `bg`, `border`, `text`, `radius`, `height`, `width`, `pad-x`,
`pad-y`, `gap`, `shadow`, `ring`. Components: `field`, `check`, `switch`, `btn`,
`chip`, `alert`, `table`, `card`, `modal`, `popover`, `pager`, `progress`, `tab`,
`dial`, `avatar`, `badge`. The existing `--sl-field-*` family is the model: admin
already overrides it and the component follows.

**How the current names land:**

| Today | Under the grammar |
|---|---|
`--sl-color-primary` | `--sl-primary` |
`--sl-color-text-muted` | `--sl-text-muted` |
`--sl-color-bg-soft-soft` | `--sl-bg-muted` |
`--sl-color-bg-soft-dark` / `-darker` / `-divider` | `--sl-bg-inverse` / `-inverse-strong` / `--sl-border-inverse` |
`--sl-color-primary-hover` and its 3 `--sl-hover-*` twins | `--sl-primary-strong` |
`--sl-color-tone-success` | deleted, duplicates `--sl-success` |
`--sl-overlay-20` | `--sl-shadow-strong` |
`--sl-h1`…`--sl-h4` | `--sl-font-h1`…`--sl-font-h4` |
`--sl-shadow-soft` holding an rgba colour | `--sl-shadow-color` |
`--sl-radius-control` + `--sl-radius-panel`, both 4px | `--sl-radius-1` |
`--sl-img-placeholder-icon-size` | `--sl-size-placeholder` |
`--sl-progress-fill-3-start` / `-end` | `--sl-grad-progress-3` |
`--sl-but-neutral-start` / `-end` | `--sl-grad-btn` |
`--sl-changelog-*`, 8 of 9 duplicate a semantic colour | deleted |
`--sl-login-*`, 27 tokens | 4 component tokens or none |
`--sl-season-winter` | `--sl-season-winter`, a categorical ramp, stays |
`--sl-profile-level` written by a template | `--sl-d-level` |

Everything in `tokens.css` is public API. After batch 9 the names are frozen:
hundreds of themes bind to them and renaming stops being possible.

**Forbidden, and each reported by the audit tool:** an alias of an alias with one
use and no theming intent; a literal outside `tokens.css` beyond the structural
allowlist; a token declared in `theme.css` that could be mistaken for API, scoped
custom properties being allowed only on a component root and only as internal;
two spellings of one intent, as `999px` and `9999px` are today; a dead token.

## Batches

### Batch 0 — contract, audit tool, baseline

**Causa.** With independent themes the only thing that travels between them is
convention. Nothing else in this plan is enforceable until the convention is
written down and machine-checked, and no batch that moves pixels may start
before there is a picture to compare against.

**Steps.**
- Write `.rules/theme.md`: the token contract above, the canon, the structural
  allowlist, the forbidden list. Strict rule, binding on every edit.
- Add `.rules/theme.md` to the canonical rules list in `CLAUDE.md`.
- Write `tools/ui-audit.php`, plain PHP, no dependencies, runnable against any
  theme: `php tools/ui-audit.php --theme=admin`. It reports literal visual
  declarations per property, dead tokens, single-use tokens, alias-of-alias
  chains, literals outside `tokens.css`, classes never referenced, and a
  cross-theme diff of shared selectors. Exit code non-zero when a count grew
  against the stored baseline.
- Store the baseline numbers of this document in a file the tool reads.
- Capture Playwright baseline screenshots: all 67 pages under `demo/`, the front
  page, an article, a forum topic, the profile, the private-messages view, and
  the admin sections. Scripts under `c:\tmp\` as usual; the admin login field is
  `name="pwd"`.

**Verification.** The tool reproduces every number in the baseline table above.
No CSS file changes: `git diff --stat` shows only `.rules/theme.md`, `CLAUDE.md`,
`tools/ui-audit.php` and the baseline file.

**Done when** the tool passes on both themes at the recorded numbers.

### Batch 1 — split into tokens.css / base.css / theme.css

**Causa.** "Edit one file" is only true if that file contains nothing but the
API. Today the tokens sit next to `@font-face` and the element reset, so a theme
author editing values also has the reset under the cursor.

**Steps.** Move every `:root` token declaration out of `base.css` into a new
`tokens.css`, in both themes. `base.css` keeps `@font-face`, the reset and the
element styles. Nothing is renamed and no value changes.

`getThemeAssets()` globs `templates/<theme>/assets/css/*.css`, so the new file is
picked up with no PHP change. Glob order becomes `base, theme, tokens`, which is
irrelevant for custom properties: their resolution does not depend on declaration
order across files. The asset bundle fingerprint is `mtime`-based and needs no
change.

**Verification.** Rendering identical to the batch 0 screenshots, pixel for
pixel, on every captured page. The audit tool reports the same literal counts as
batch 0 — this batch moves declarations, it does not fix any.

**Done when** both themes have three CSS files and the screenshot diff is empty.

### Batch 2 — token hygiene and API freeze candidate

**Causa.** The scales of the following batches will create hundreds of new token
references. Cleaning the junk afterwards would mean re-touching all of them, and
freezing a polluted API is irreversible once themes ship against it.

**Steps.**
- Delete the 3 dead tokens (`--sl-content`, `--sl-color-primary-hover`,
  `--sl-h3`) after confirming no template, PHP file or JS reads them.
- Triage the 67 single-use tokens of lite into two piles: component tokens that are
  correct as they are (`--sl-field-*` is the model) and instance junk. Fold the
  junk into the value it aliases; the 27 `--sl-login-*` tokens, 10 of them under
  `--sl-login-dropdown-*`, are the first target.
- Review the 30 lite and 43 admin tokens declared inside `theme.css` and mark
  each as internal or promote it to `tokens.css`.
- Rename what violates the grammar of the naming section. This is the last batch
  in which renaming is free. Every rename runs across all five places at once:
  the theme CSS, the 5 `lite` templates that write inline values, the 2 admin PHP
  files, the 2 JavaScript files, and the 66 pages under `demo/`.
- Move the live values written from outside onto the `--sl-d-*` prefix, so the
  audit tool stops reading them as undeclared API.
- Bring `--sl-monitor-orange|green|red|blue` onto the grammar as
  `--sl-chart-net|cpu|ram|disk`: named by role, not by colour, so a theme can
  recolour the monitor and mean something.

**Verification.** Screenshots identical. The audit tool reports 0 dead tokens, 0
alias-of-alias chains, and every remaining single-use token is annotated as a
component token.

**Done when** the token list is grammar-clean in both themes.

### Batch 3 — admin: the zero-percent axes

**Causa.** Gradients, transitions and `z-index` are tokenised nowhere. A theme
author cannot restyle 41 gradients, 36 transitions or 16 layers without forking
rules, which is exactly what independence makes unrepairable. These values move
into tokens **verbatim**, so the rendering cannot change by construction — the
cheapest possible drop in the metric.

**Steps.** Extract `--sl-grad-*`, `--sl-time-*` and `--sl-ease-*`, and `--sl-z-*`
as a named layer ladder: base, dropdown, sticky, overlay, modal, popover, toast.
Collapse the 27 distinct transition spellings and the 9 distinct `z-index` values
onto the ladder. Values are preserved exactly; duplicates that differ only in
spelling collapse onto one token.

**Verification.** Screenshots identical. The metric drops by roughly 60 — 35
transitions plus 13 animations plus the gradient sites inside `background`. The
`z-index` ladder is checked by the bare-number rule, not by this metric, and by
opening a modal over a dropdown over a sticky header.

### Batch 4 — admin: scales

**Causa.** 28 distinct `font-size` values and 8 distinct radius literals are not
a system, and spacing is the largest single block. This is the first batch that
changes pixels, which is why it comes after the baseline.

**Steps.** Define the type scale, the spacing scale on a ritm of 4, and the radius
scale — one `--sl-radius-pill`, ending the `999px`/`9999px` split. Migrate
`font-size` (61), `padding` (63), `gap` (44), `margin` with `margin-bottom` (46)
and `border-radius` (17), snapping each to the nearest step. Name the three
breakpoints, ending the `900`/`901` and `768`/`760` contradictions.

**Verification.** Screenshot diff reviewed page by page; every difference either
intended by the snap or reverted. The metric drops by roughly 230.

### Batch 5 — admin: the remainder, first etalon closed

**Causa.** A theme that is 90% tokenised still forces its author into
`theme.css`. Only zero is a contract.

**Steps.** `background` (35), `width` (50), `height` (38), `min-height` (28),
`box-shadow` (12) and the tail — roughly 230 decisions, plus the half-tokenised
83 that carry a token and a literal side by side.

**Verification.** `php tools/ui-audit.php --theme=admin` reports **0**
untokenised visual decisions outside the structural allowlist, and **0** bare
numbers in `z-index` and `opacity`. Screenshots reviewed. From this point `admin`
is the working example every later batch is measured against.

### Batch 6 — lite: the zero-percent axes

Same as batch 3, mechanically, using the token names admin settled on: 21
gradients, 49 transitions, 27 animations, 26 `z-index`. The metric drops by
roughly 80.

### Batch 7 — lite: scales

Same as batch 4: `font-size` (140), `padding` (118), `margin` with
`margin-bottom` and `margin-top` (158), `gap` (58), `border-radius` (54). Largest
batch of the plan at roughly 530 decisions; may be split per property group if
the screenshot review gets unwieldy.

### Batch 8 — lite: the remainder, second etalon closed

`height` (74), `width` (72), `box-shadow` (33) and the tail, plus the 98
half-tokenised declarations.

**Verification.** `php tools/ui-audit.php --theme=lite` reports **0** on both
checks. Both themes now hold every visual decision in `tokens.css`.

### Batch 9 — canon reconciliation, skeleton, documentation

**Causa.** Independence makes divergence permanent. A contradiction shipped in
the etalon is taught to every descendant, and after distribution the token names
can no longer be corrected.

**Steps.**
- Resolve the 121 divergent shared selectors: each becomes identical in
  structure with the difference expressed by a token, or gets an entry in an
  allowlist with a written reason. Anything that is neither is a bug.
- Resolve the 33 same-named templates with different markup, `fragments` and
  `partials` only. `layouts` and `pages` are out of canon scope.
- Decide the 15 lite and 5 admin never-referenced classes: delete, or document
  why they stay. `sl-attach-*` (parser `[attach]`) and the seasonal
  `sl-winter`/`sl-spring`/`sl-summer`/`sl-autumn`/`sl-newyear` must be checked
  for dynamic composition in PHP before anything is removed.
- Write the theme skeleton: which files a new theme must contain, which it may
  change, which it must not, and how to run the audit tool against it.
- Freeze the `tokens.css` public API and note the freeze in `.rules/theme.md`.

**Verification.** Cross-theme diff of the audit tool reports only allowlisted
divergences, each with a reason. A scratch theme created from the skeleton by
editing `tokens.css` alone renders correctly.

### Batch 10 — markup leaves PHP

**Causa.** A theme cannot restyle what PHP hardcodes, and the audit tool cannot
promise "one address per decision" while class names, inline styles and tags are
assembled outside the template layer. This batch is independent of the CSS work
and may run in parallel with batches 3 to 8.

**Scope, measured.** 28 files, 108 occurrences: 47 `class="`, 19 `style="`, 42
literal tags. Three files carry 89 of them.

| File | `class=` | `style=` | tags |
|---|---|---|---|
`core/classes/parser.php` | 3 | 7 | 21 |
`admin/modules/monitor.php` | 23 | — | 1 |
`admin/modules/statistic.php` | 11 | 3 | 10 |
`modules/rss/lang/*.php`, 5 languages | — | 5 | 5 |
editor drivers, 3 files | 1 | 2 | 3 |
remaining 6 files | 9 | 2 | 2 |

**Steps.** Each site moves into a fragment rendered through `getHtmlFrag()`, and
PHP passes data instead of markup. The monitor chart becomes a fragment taking
the four series as values; the statistic tables become a fragment taking rows;
the parser emits the existing `sl-alert` and code fragments instead of building
its own. The RSS language files hold a document template, not styled markup —
they are decided separately, since RSS output is XML and not a page.

**Verification.** `grep -rE "class=\"|style=\"|'<(div|span|table|svg)" ` over
`modules core admin blocks plugins` returns nothing outside the agreed
exceptions. Rendering unchanged against the batch 0 screenshots.

**Done when** no PHP file writes a class, an inline style or a tag.

## Risks

- **Snap regressions.** Batches 4 and 7 change pixels on purpose. The only
  defence is the batch 0 screenshot set; a page that was never captured is a page
  where a regression ships silently. Capture before, not during.
- **Over-unification.** The temptation in batch 9 is to make the admin panel look
  like the site. The formal guard: a difference is legal only when it is
  expressed by a token. What cannot be expressed that way is discussed
  explicitly, not fixed by eye.
- **Premature freeze.** Freezing token names before both themes reach zero would
  bake in names that the remaining literals turn out to contradict. The freeze is
  in batch 9 for that reason.
- **Volume.** 1614 declarations across the two themes is the bulk of the work and
  it is larger than everything else in this plan combined. Batch 7 alone is
  roughly 530. Splitting it is expected, not a failure.
- **Consolidation is not compression.** Expect roughly 10–15% fewer rules, not a
  smaller codebase. The deliverable is one address per decision, not kilobytes.

## What this plan deliberately leaves out

- A utility layer. Only 77 duplicated rules are single plain classes and could
  become utilities; 54 are state-bound and must stay in CSS, 233 are structural.
  A 6% gain that requires editing markup is not worth the API surface.
- A shared `core.css`. It contradicts theme independence.
- Template engine changes. The three cheap wins measured earlier — the 12
  character limit in `filterConst` that hides 392 language constants from
  templates, a `default` filter for the 147 dynamic class attributes, literal
  comparison in `if` that would collapse 294 tone booleans into one `tone` key —
  are real but belong to a separate task. None of them affects the etalon.
