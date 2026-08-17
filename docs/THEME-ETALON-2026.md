# Theme Etalon 2026

Work plan for turning the two shipped themes into reference etalons that hundreds
of independent themes are copied from.

Status: planned, nothing implemented. Run the batches in order 0 → 9. Batch 0
changes no CSS, batch 1 changes no rendering, and every later batch depends on
both. Update this line as batches land.

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
- **No PHP in this program.** `Template::getFile()` and `checkFile()` are not
  touched, so the security boundary around theme paths does not move. The
  template engine grammar is not extended either. The work is CSS, one audit
  tool and documentation.
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

Taken 2026-08-17 on a clean tree. The audit tool of batch 0 must reproduce these
numbers, otherwise the tool is wrong, not the baseline.

| Metric | lite | admin |
|---|---|---|
`theme.css` rules / declarations | 1383 / 4325 | 953 / 2983 |
declarations via `var(--token)` | 849 (20%) | 731 (25%) |
structurally neutral declarations | 2312 | 1597 |
**literal visual declarations** | **1164 (27%)** | **655 (22%)** |
tokens declared in `base.css` | 168 | 120 |
tokens declared inside `theme.css` (scoped) | 30 | 43 |
dead tokens | 3 | not measured |
single-use tokens | 67 | not measured |
`sl-*` classes in CSS | 654 | 464 |
classes never referenced anywhere | 15 | 5 |
raw `px` in `theme.css` | 1237 | 680 |
hex colours in `theme.css` | 23 | 1 |
`!important` | 11 | 15 |

Per axis, `theme.css` plus `base.css`, split by "reaches the value through a
token" against "carries the literal in place":

| Axis | lite decls / token / literal / distinct literals | admin decls / token / literal / distinct |
|---|---|---|
`border-radius` | 135 / 42 / 86 / 24 | 96 / 66 / 27 / 8 |
`box-shadow` | 66 / 27 / 18 / 11 | 55 / 37 / 9 / 6 |
`font-size` | 192 / 41 / 149 / 33 | 107 / 41 / 66 / 28 |
gradients | 21 sites / **0** / 21 / 20 | 41 sites / **0** / 41 / 25 |
`transition` | 53 / **0** / 53 / 43 | 35 / **0** / 35 / 27 |
`z-index` | 26 / **0** / 26 / 12 | 16 / **0** / 16 / 9 |

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

**Literal visual declarations in `theme.css`: admin 655, lite 1164 → 0.**

Monotonic, machine-checked, no aesthetic argument possible. Every batch lowers
it and no batch may raise it. A declaration counts as a literal visual decision
when its value carries a length, a percentage, a duration, a colour or a
gradient and does not come from `var(--sl-…)`.

Excluded by contract, because they express structure and not appearance:

- `grid-template-columns`, `grid-template-rows`, `grid-area`, `flex`,
  `flex-basis`, `order`, `aspect-ratio`
- the neutral value set: `0`, `1px` borders, `100%`, `100vh`, `100vw`, `auto`,
  `none`, `inherit`, `50%` used for a circle
- `content` strings and counters

The allowlist lives in `.rules/theme.md` and in the audit tool as one shared
list. Growing it requires a written reason next to the entry.

## Token contract

Three levels, and a token must sit on exactly one of them.

1. **Primitive** — a raw value with no meaning: `--sl-blue-500`, `--sl-gray-100`.
   Declared only in `tokens.css`. Never referenced by a component.
2. **Semantic** — a role: `--sl-color-primary`, `--sl-color-text-muted`,
   `--sl-space-4`, `--sl-radius-pill`, `--sl-z-modal`, `--sl-motion-fast`.
   Points at a primitive. This is what components read.
3. **Component** — the theming surface of one component:
   `--sl-field-bg`, `--sl-field-height`, `--sl-btn-shadow`. Points at a semantic
   token. Single-use by design, and that is correct: `--sl-field-*` already works
   exactly this way and `admin` already overrides it.

Grammar: `--sl-<group>-<role>[-<variant>]`, lower case, hyphen separated, no
abbreviations invented per site.

Forbidden, and the audit tool reports each:

- an alias of an alias with one use and no theming intent — the whole
  `--sl-login-dropdown-*` family is the current example
- a literal outside `tokens.css`, except the structural allowlist
- a token declared in `theme.css` that a theme author could mistake for API;
  scoped custom properties are allowed only on a component root and are internal
- two spellings of one intent (`999px` and `9999px`)
- a dead token

Everything in `tokens.css` is public API. After batch 9 those names are frozen:
hundreds of themes will bind to them and renaming stops being possible.

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
- Triage the 67 single-use tokens into two piles: component tokens that are
  correct as they are (`--sl-field-*` is the model) and instance junk. Fold the
  junk into the value it aliases; the `--sl-login-dropdown-*` family of 22 is the
  first target.
- Rename what violates the grammar. This is the last batch in which renaming is
  free.
- Review the 30 lite and 43 admin tokens declared inside `theme.css` and mark
  each as internal or promote it to `tokens.css`.

**Verification.** Screenshots identical. The audit tool reports 0 dead tokens, 0
alias-of-alias chains, and every remaining single-use token is annotated as a
component token.

**Done when** the token list is grammar-clean in both themes.

### Batch 3 — admin: the zero-percent axes

**Causa.** Gradients, transitions and `z-index` are 0% tokenised. A theme author
cannot restyle 41 gradients, 35 transitions or 16 layers without forking rules,
which is exactly what independence makes unrepairable. These values move into
tokens **verbatim**, so the rendering cannot change by construction — the
cheapest possible drop in the metric.

**Steps.** Extract `--sl-grad-*`, `--sl-motion-*` (duration and easing) and
`--sl-z-*` (a named layer ladder: base, dropdown, sticky, overlay, modal,
popover, toast) in `admin`. Collapse the 27 distinct transition spellings and the
9 distinct `z-index` values onto the ladder. Values are preserved exactly;
duplicates that differ only in spelling collapse onto one token.

**Verification.** Screenshots identical. Audit metric for admin drops by roughly
92 declarations. Layer order verified by opening a modal over a dropdown over a
sticky header.

### Batch 4 — admin: scales

**Causa.** 28 distinct `font-size` values and 8 distinct radius literals are not
a system, and spacing is the largest single block of literals. This is the first
batch that changes pixels, which is why it comes after the baseline.

**Steps.** Define the type scale, the spacing scale on a ritm of 4, and the
radius scale (one `--sl-radius-pill`, ending the `999px`/`9999px` split). Migrate
`font-size` (56), `padding` (53), `gap` (40), `margin` and `margin-bottom` (47),
`border-radius` (13) with snapping to the nearest step, and name the three
breakpoints, ending the `900`/`901` and `768`/`760` contradictions.

**Verification.** Screenshot diff reviewed page by page; every difference either
intended by the snap or reverted. Audit metric for admin drops by roughly 210.

### Batch 5 — admin: the remainder, first etalon closed

**Causa.** A theme that is 90% tokenised still forces its author into
`theme.css`. Only zero is a contract.

**Steps.** `border` (51), `background` (34), `box-shadow` (9), `width` (50),
`height` (36), `min-height` (28) and the tail.

**Verification.** `php tools/ui-audit.php --theme=admin` reports **0** literal
visual declarations outside the structural allowlist. Screenshots reviewed. From
this point `admin` is the working example every later batch is measured against.

### Batch 6 — lite: the zero-percent axes

Same as batch 3, mechanically, using the token names admin settled on: 21
gradients, 53 transitions, 26 `z-index`. Metric drops by roughly 100.

### Batch 7 — lite: scales

Same as batch 4: `font-size` (149, 33 distinct), `padding` (107), `margin` and
`margin-bottom` and `margin-top` (160), `gap` (52), `border-radius` (86, 24
distinct). Largest batch of the plan; may be split per property group if the
screenshot review gets unwieldy. Metric drops by roughly 550.

### Batch 8 — lite: the remainder, second etalon closed

`border` (66), `box-shadow` (18), `width` (71), `height` (70), and the tail.

**Verification.** `php tools/ui-audit.php --theme=lite` reports **0**. Both
themes now hold every visual decision in `tokens.css`.

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
- **Volume.** 1819 declarations across the two themes is the bulk of the work and
  it is larger than everything else in this plan combined. Batch 7 alone is
  roughly 550. Splitting it is expected, not a failure.
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
