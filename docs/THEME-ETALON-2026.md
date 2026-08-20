# Theme Etalon 2026

Turning the two shipped themes into reference etalons that hundreds of
independent themes are copied from.

Status: batch 8 is part done and its remainder is written out below. Batch 9 is
independent and may run alongside it. A step that is done is deleted from this
file, not marked done.

**Both themes are at zero on every count either of them controls, and the two are
now measured against each other as well as against themselves.** `count`, `bare`,
`dup`, `names`, `dead`, `alias`, `unsat`, `unmet`, `clash`, `classes` and
`contrast` all read zero in admin and in lite, and the new global `cross` reads
zero beside them. What is left in the baseline is `scoped` at 54 and 92,
`important` at 23 and 17, and `markup` at 143, which is batch 9's.

**The cross-theme gate was measuring the wrong thing, and that is what made it
unanswerable.** It reported 135 divergent shared selectors as one undifferentiated
list, and a list of 135 is a list nobody works through. A shared selector is now
read two ways. When both themes declare the **same set of properties**, the pair
is legal by the contract's own words — one canon carrying many skins — because
the difference already lives in values that reach their tokens, and 71 of the 117
divergences are exactly that. When the **property sets differ**, canon has to
answer for it, and each of the remaining 46 carries a written reason in
`tools/ui-contract.php` under `divergent`. `--cross` prints the split and names
every entry with no reason; the global `cross` count is what the ratchet holds.

**A shorthand is not the longhands it expands to, and reading it as one cost 82
elements.** Admin's `ul` was three longhands with `list-style-type` deliberately left
out, so a nested list changed its mark — disc, then circle, then square. Folding that
into `list-style: disc outside` looked like two spellings of one intent and is not:
**every longhand a shorthand omits is reset to its initial value**, which flattened the
second level back to a disc everywhere. No count moved and no committed image moved,
because no manifest page shows a nested mark. What found it was a diff of the computed
styles of every element against the same page served the CSS from `HEAD`, through
request interception — 29 404 element comparisons over 17 pages, which reported eight
changed declarations in admin and **zero in lite**. That diff is the tool for this
question; a pixel gate answers a narrower one.

**The element reset is the part of canon every new theme inherits, so it was
settled structurally rather than registered.** `h1`…`h5` each declare
`font-size`, `margin`, `display` and `color` in both themes; `hr`, `ul`, `ol`,
`fieldset`, `a:hover` and the two check inputs hold one property set each. The
values stay the theme's own, and most of the added declarations spell what the
element already computed, so the reset moved almost no pixels: admin's headings
gained the two rendering hints lite already had and its `hr` took a rhythm step in
place of the browser's `0.5em`.

**The 424 duplicate rule bodies were 424 because the count was measuring three
different things.** A body of exactly **one declaration** is need and not
repetition — the same reading the plan already applied to `display: flex` under 122
flex containers — because it is one property reaching one token, and a selector
list of everything that happens to be hidden names nothing while scattering each
component's definition. A body met **in two files** cannot be merged at all: CSS
has no selector list that crosses a file. Those two shapes are 126 and 9 groups and
are counted separately now, which left **50 groups that are a real human call**.
Seven were merged, one rule that restated a less specific one was deleted, and 34
carry a written reason. `dup` is 0 in both themes and every group is accounted for.

**A theme is created and checked by a test now, which is the goal of this document
expressed as a gate.** `tests/Unit/ThemeCreationTest.php` copies an etalon under a
name nothing else uses, repaints **only** the API block, and then asks the whole
contract of the copy: every audit count at zero, every contrast pair the walk
recorded for the etalon still clearing AA after the same repaint, the runtime file
list satisfied through a probe that boots the real core, every template compiling
through `Template`, and every custom property the templates write registered under
`data`. The scratch tree is removed in `tearDownAfterClass` against a path the
harness built and never one it guessed.

**The test caught an error in its own premise, which is the whole reason to write
one.** The repaint began as a hue rotation holding HSL lightness, on the assumption
that lightness is what a contrast ratio reads. It is not: the three channels carry
different weight, so the same lightness in orange is brighter than in blue, and
`#111827` turned half way round gained half again as much luminance. The repaint
searches back onto the original relative luminance now, and the contrast half of the
test measures the **real pair registry** put through the same function rather than
the palette in isolation.

**The skeleton is the union of two lists that are not the same list, and it is
checked against both rather than against itself.** It lives in
`tools/ui-contract.php` under `skeleton`, split into what every theme needs, what a
frontend theme needs on top, and what an editor manifest adds, with the gate that
demands each entry named beside it. `ThemeCreationTest` reads `checkThemeAssets()`
and `TemplateValidationTest` and fails on an entry nobody demands as well as on a
demand nobody listed.

**The contrast walk composites a sheer layer now instead of passing through it, and
it found a failure no gate could see.** The admin footer band is a diagonal hatch of
`rgba(255, 255, 255, 0.08)` over a brand gradient, and white text on the bottom stop
of that gradient reads **4.26:1**. The old walk skipped the sheer layer and measured
the plain `#0877b1` at 4.90:1, which passed. The hatch is decoration and the band
under it carries white text, so its alpha is what the ratio allows rather than what
looks best on its own: at `0.04` the same pair reads 4.60:1.

**Compositing is per layer, not per stop, and getting that wrong invents failures.**
`background-image` holds a list painted back to front, and the first version of the
composite laid the hatch onto the ancestor's background instead of onto the gradient
beneath it in the same declaration. That reported two dark pairs at 1.3:1 that
nobody could ever see. Layers are walked back to front now and each is composited
onto what the walk has gathered under it. The registry holds **460 pairs** — 122 in
admin and 338 in lite — and both halves are at zero below AA.

**The two names both themes read and nothing wrote are gone, and the fix moved a
decision out of the script.** `placeFloat()` measured where a floating panel fits and
then wrote six inline properties and a hardcoded `zIndex = '3000'`, while the theme
rule asked for `--sl-float-top` and `--sl-float-left`, which no one ever set. The
script writes `--sl-d-float-top` and `--sl-d-float-left` through `setProperty`, the
way it already wrote `--sl-d-arrow`, and the theme owns the rule that reads them.
The marker that turns that rule on is `data-sl-float-placed` on the host and it
outlives `sl-is-open` on purpose, so a closing panel fades out where it stood
instead of jumping back to its static spot. Measured on the running stand: hovering
sets the two properties and nothing else, `z-index` resolves to `--sl-z-modal`, and
the placement survives the close. `unmet` fell 2 → 0 in both themes.

**Three zoos in admin were the same three lite had already closed.** The alert
carried `--sl-alert-c-soft` and `--sl-alert-c-strong` per tone, five and six scoped
declarations including a raw `#f3e8f8`; it reads the one
`--sl-alert-tint: color-mix(in srgb, var(--sl-alert-c) 9%, transparent)` now and its
glyph takes the base tone. The loading dots spelled their geometry and their three
frames through eight scoped internals; they read the API block and the tone
directly, in the shape lite carries in its own colour. The switch aliased four
semantic tokens under `--sl-switch-*` and painted its focus ring in the success
colour; it spells the tokens and draws the theme's one ring. `scoped` fell 84 → 54,
and `--sl-danger-subtle` had no ground left and was deleted, exactly as it was in
lite.

**Four spellings of one intent are gone.** `:before`/`:after` became `::before`/`::after`
at 57 sites, `bootstrap-icons` became `"bootstrap-icons"` at seven, `border-radius: 50%`
became `var(--sl-radius-circle)` at 28 in lite, and admin's three sort arrows took the
escapes lite already used instead of literal glyphs. `--sl-knob-dur: 1s` folded onto
`--sl-time-slow`, which closes the last transition in either theme that was off the
ladder.

**Fourteen never-referenced classes were five false alarms and nine real ones.**
`isClassUsed()` looked for a class after one of eight delimiters, and a class emitted
straight after a template tag — `{% endif %}sl-collapsible` — matched none of them. The
boundary is the name alphabet itself now, with a fixture carrying every shape a class
arrives in. The nine that were real are deleted: the two move-ordering arrows and their
container, superseded by drag ordering, and lite's gallery, `sl-mt-5` and `sl-home`.
`classes` is 0 in both themes.

**`error.html` is on the settled names.** It is the one surface that renders when the
CMS cannot, so it keeps its own `:root`: 60 tokens, none dead, none read and undeclared,
with `--sl-alert-c` and `--sl-alert-tint` scoped on the alert root as internals. Its
values are the light half of lite, because there is no server here to write
`data-theme`. Read against the previous file element by element, every difference is
one the theme had already taken: `--sl-text-muted` `#6e7c8b` → `#64717e`, the login link
colour folded onto `--sl-text-strong`, and the card's paddings landed on the ladder.

**What is in the tree.** The contract and its generated half:
`tools/ui-contract.php`, `tools/ui-audit.php`, `tools/ui-audit-baseline.json`,
`tools/ui-contrast.json`. The screenshot rig: `tools/ui-shots.json`,
`tools/ui-shots.mjs`, `tools/ui-baseline/*.png` with its `noise-floor.json`. The
gates: `tests/Unit/ThemeContractTest.php` over the stored baseline,
`tests/Unit/UiAuditTest.php` over `tests/Fixtures/ui/`,
`tests/Unit/ThemeCreationTest.php` over a scratch copy of an etalon with
`tests/Support/theme_probe.php` behind it, and `.claude/hooks/lint-edit.php` per
machine. Both `base.css` carry the `/* --- end tokens --- */` marker.

**The rig runs over `https`, because the scheme is part of the login.**
`setCookies()` marks the session cookie `secure` whenever `homeurl` is `https`, so
a run over plain `http` fills the form, posts it and is handed a cookie the browser
drops: the page comes back carrying the login block and no error to read.

**No manifest page opens the editor, so `skin.css` is outside the screenshot gate.**
It is at zero and checked by the audit alone; a batch that changes it has no visual
net under it. **The two copies must stay byte-identical**, which
`EditorWindowTest::theWindowKeepsItsStylesUnderItsOwnRoot` asserts, so every edit
lands in both in one commit.

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
`--dup` | identical rule bodies sharing one `@media` context, split from the two shapes that are exempt by construction |
`--names` | grammar violations, including names that cannot invert |
`--ramp` | colour families by saturation, and their lightness spread |
`--cross` | selectors and templates that differ between themes, split into value-only and structural, with the reason beside each of the second kind |
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

**The walk composites a sheer layer rather than skipping it, which is the difference
between measuring nothing and measuring what is there.** Nine per cent of orange over a
white page is a definite colour and text on it stands on that colour. `under()` walks out
to the first ancestor that paints something opaque and composites every sheer layer met
on the way back down; `worst()` does the same across the layers of one `background-image`,
back to front, so a hatch laid over a gradient is a hatch on that gradient and never on
the page two boxes further out. Getting that second part wrong is not a smaller error
than skipping: the first version composited the hatch onto the ancestor and reported two
dark pairs at `1.3:1` that nobody could ever see.

**What it bought was a failure no other gate could reach.** The admin footer band is a
diagonal hatch of `rgba(255, 255, 255, 0.08)` over a brand gradient, and white text on
the bottom stop of that gradient reads `4.26:1`. The plain colour under the hatch is
`#0877b1` at `4.90:1`, which is what the old walk measured and passed. A hatch is
decoration and the band under it carries white text, so its alpha is what the ratio
allows rather than what looks best on its own: at `0.04` the pair reads `4.60:1`.

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
skips every page needing a session when they are absent. `--out=` sends a capture or a
check somewhere outside the repository, which is what lets a batch compare its own two
captures instead of trusting a committed baseline the stand's own data has moved under.
`modes` is what the shots are captured in and `contrastmodes` what the contrast walk
drives; both read `light`/`dark` today, the first through `auto` and `dark`. `cookie`
is the name the CMS actually reads, which carries the `user_c` prefix — the loop wrote
a bare `mode` before, and a mode cookie under the wrong name changes nothing at all.

Numbers here are the tool's, and drift as work lands.

## Contract

- **Themes are independent.** No inheritance in `Template`; the **216**
  byte-identical rules they share stay duplicated on purpose. A shared selector
  whose two rules hold the same property set is legal whatever its values say;
  one whose property sets differ carries a written reason. `--cross`
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

**Untokenised visual decisions: admin 0, lite 0.** `--count`

Measured by the tool, which is the authority. Counted over every CSS file that is
not the API block: `theme.css`, the element styles below the marker, and
`skin.css`. A transition counts twice, because its duration and its easing take two
tokens. Both themes are at zero in all three files.

Monotonic in one direction: **no batch raises a count it controls.** 8 and 9 hold it
flat.

The ratchet holds `count`, `bare`, `dup`, `names`, `dead`, `alias`, `unsat`,
`unmet`, `scoped`, `clash`, `classes`, `important` and `contrast` per theme, and
`kinds`, `cross` and `markup` globally. It does **not** hold `tokens` or `single`:
extracting an axis into the API block raises both on purpose, and a component token
that one component reads is correct at one use. The list lives in
`tools/ui-contract.php` under `ratchet`.

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

1855 declarations in admin and 2644 in lite reach every decision through a token, and
neither theme has one left half done. `--count`

**Second check: bare numbers.** `--bare` — **zero in both themes.** Lite spelled 63
weights six ways, 27 line heights fifteen ways — `1.05`…`1.62` plus `1.428571429` and
`normal` — and 17 opacities seven ways; each family now names its step.

These carry no unit and no colour, so the first counter is blind to them. A bare
number in the four must come from a token; `0` and `1` stay neutral in `opacity`
and `line-height`, and `z-index: 0` is a stacking context and therefore a
decision.

**Third check: repetition.** `--dup` — **zero in both themes**, and the number
reads zero because the count was split into the three different things it had been
adding together.

**A body of exactly one declaration is need.** It is one property reaching one
token, the same way `display: flex` under 122 flex containers is: `display: none`
appeared under 16 lite selectors and `margin: 0` under twelve, and a selector list
holding every element that happens to be hidden names nothing while scattering each
component's definition across the file. 126 groups are this shape.

**A body met in two files cannot be merged at all**, because CSS has no selector
list that crosses a file. 9 groups are this shape, most of them a theme meeting its
own editor skin.

Both are reported beside the count rather than inside it. What is left is the human
call the check exists for: **50 groups**, of which 7 were merged, 1 was a rule
restating a less specific one and was deleted, and 34 carry a written reason under
`duplicates`. Whether selectors belong together is still a human call; what the
gate now guarantees is that the call was made.

**Fourth check: a name that cannot invert.** `--names`

**Zero, in both themes.** No `white`, `black`, `light` or `dark` is left in a
name: the bevel that carried the last two is one `--sl-btn-shadow` holding both
edges and the lift under them, and the admin switch reads `--sl-switch-inverse`
off `--sl-on-solid`, which now turns over with the mode. `inverse` passes the law:
"opposite of the page" holds in both modes.

`prefers-color-scheme` still appears zero times and must: admin carries both modes
inside one declaration through `light-dark()`, and `ThemeContractTest` fails on either
`light-dark(` or `prefers-color-scheme` below the marker, in any of the four files.

`--names` reports **zero in both themes**. The colour tail that carried the last
eighteen is folded onto the roles, and every component name lite gained on the way
is registered in `tools/ui-contract.php`, which is what the count actually measures.

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
theme CSS | 4 | 4677 `var()` — lite `theme.css` 2625, admin 1801, the two `base.css` 251 | reads |
`editors/toastui/skin.css` | 2 | 501 each | reads; part of the package |
`error.html`, repo root | 1 | 60 in its `:root`, 60 read | a self-contained third token set, closed on itself: nothing declared goes unread and nothing read goes undeclared. `--sl-alert-c` and `--sl-alert-tint` are scoped on the alert root as internals, which is legal |
`lite` templates | 5 | 9 | **writes** a live value inline |
admin PHP | 2 | 16 | reads inside markup it assembles |
JavaScript | 2 | 8 | reads and writes, including the two float coordinates |
`tests/Unit/EditorWindowTest.php` | 1 | 1 | asserts `--sl-d-count` |
`tools/ui-contract.php`, `docs/WINDOW.md` | 2 | — | the contract and its prose |

`error.html` is not production markup but it ships — it renders when the CMS
cannot, and holds its own `:root`. It carries the light half of lite, because
there is no server here to write `data-theme`.

## Two kinds of value

**Theme tokens** — declared in the API block, read by CSS. The public API.

**Data tokens** — written from outside by a template or JavaScript, read only by
CSS: comment depth, profile level, group colour, session percentages, popover
arrow offset, scroll distance, dial count, and the two coordinates a floating panel
is placed at. They carry `--sl-d-*` so the tool can
tell "used but never declared" from "declared as API"; without the split a dead
token scan deletes them as junk and the profile ring stops moving.

`--sl-size-chip` crosses the other way: `slaed.js` reads it through
`getComputedStyle` and it drives dial geometry. Tokens read by JavaScript are
listed in the contract and cannot be renamed without touching the script in the
same commit.

## Naming grammar

Four laws, each machine-checkable, and **both themes are at zero against all
four**. The offenders each one closed are kept here because a law without the case
that made it is a rule nobody can apply.

1. **The name says the role, never the value.** It closed `--sl-overlay-10/12/15/20`,
   which named an alpha, and `--sl-h1`…`--sl-h4`, which named a tag.
2. **At most three segments after `--sl-`.** It closed 39 names in lite, up to
   `--sl-login-dropdown-form-margin-left` at five, and it is what decides a
   component's name rather than merely trimming one: `--sl-fm-bar-min-width` is four
   segments and became `--sl-fm-search-width`, because a compound component name plus
   a `min-` prop does not fit and the shorter true name does.
3. **One axis, one prefix, from the closed list.** Colour is the default axis and
   carries no prefix — it is the largest family and `color` bought nothing.
4. **State is not an axis, and modifiers do not stack.** It closed
   `--sl-color-bg-soft-soft`.

A name is checked against `tools/ui-contract.php`, not against this prose: absence
from **that** file is the violation.

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

**The map has nothing left.** Every name it wrote is in the tree, and the last two rows
waited on batch 7. The shadow roles took the field's two extra rings: one law,
`--sl-ring-mix`, replaced both colours, so a status ring mixes its own colour at the alpha
the brand ring uses and the component keeps its one `ring` and its one `shadow`. The colour
ramp took the rest, folding each name onto the role it had been spelling by hand.

**How admin's nine unsettled shadows and two colour tails were settled**, which is the
shape lite then copied. A shadow named after where it is used became the component token
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

**A breakpoint that moves eight pixels moves a layout inside those eight pixels, and only
there.** The account deck and the profile split stacked below `760`; on the ladder they
stack below `768`, so between 761 and 768 the rail now sits above the panels instead of
beside them. The `md` viewport is exactly 768 and shows it: the rail goes from 220px wide
to the full 742. Nothing overflows and nothing is clipped at any width — the geometry
probe says so on all eight session states — but the band is a real change and is named
here rather than found later.

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

The names freeze when batch 8 closes, and `frozen` in the contract is what says
so. It is still `false`.

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
`transition` | 171 durations | 3 | `0.15s` / `0.2s` / `0.35s` | closed in both themes with no exception |
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
by argument is how a zoo grows back. **Admin's own ring followed**: `--sl-knob-dur: 1s`
folded onto `--sl-time-slow`, which leaves no transition in either theme off the
ladder. Of the five curve spellings, `ease` took 71 parts and stayed,
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
untokenised visual decisions | **0** | **0** |
bare numbers in the four properties | **0** | **0** |
duplicate groups left unexamined | **0** | **0** |
grammar violations | **0** | **0** |
dead tokens | **0** | **0** |
alias chains | **0** | **0** |
tokens that cannot satisfy their property | **0** | **0** |
names read and declared nowhere | **0** | **0** |
one name declared twice in the API block | **0** | **0** |
classes never referenced | **0** | **0** |
contrast pairs below AA | **0** | **0** |
tokens in `base.css` | 404 | 297 |
tokens scoped outside the API block | 92 | 54 |
single-use tokens | 202 | 147 |
`!important` | 17 | 23 |
contrast pairs that really meet on screen | 338 | 122 |

Global, not per theme: **0** names holding two kinds across the themes, **0**
shared selectors whose property sets differ with no written reason, and **143**
occurrences of markup hardcoded in PHP.

**`dup` reads zero because the count was split into what it was actually
measuring.** 126 groups are a body of one declaration, which is need, and 9 are a
body met in two files, which no selector list can join; both are reported beside
the count rather than inside it. Of the 50 that were a real human call, 7 were
merged, 1 redundant rule was deleted, and 34 carry a written reason in
`tools/ui-contract.php` under `duplicates`.

**Contrast.** The registry holds **460 pairs that really meet on screen** — 122 in
admin, 338 in lite — collected by walking every page and state in the manifest in
both modes. **Both halves are at zero below AA**, and the ratchet holds both numbers
so they can only fall. The count rose 442 → 460 when the walk began compositing a
sheer layer instead of passing through it: text on a tint is a pair that was always
on screen and had simply never been measured. Two readings disappeared in the same
move, both of them pairs whose ground the walk had been taking from the wrong box.

**The registry stores the colour it resolved, not the name that gave it**, so a value
changed in the API block does not move the count until the walk is run again: a colour
fix and its measurement are two separate acts, and only the second one moves the figure.
The walk needs `SLAED_UI_USER` and `SLAED_UI_PASS` in the environment; without them it
skips `profile`, `private` and `admin-account` and would file a fall in coverage as a
fall in failures.

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
fresh pairs over the committed file and keeping lite's 150 untouched. Batch 7 did the
same for lite and did not have to merge anything: run with credentials, the walk returned
admin's 124 pairs and admin's zero unchanged three times over, so re-generating the whole
file cost admin's half nothing. Lite's own half rose 150 → 318 on coverage alone, which is
the one moment that figure may legitimately move up.

**Every property is at zero in both themes.** What lite's 386 turned into is a component
name per figure, in the shape admin already had: a size that sizes a control, an icon or a
page region has one address, an animation carries its own `dur` and its own `ease`, a shadow
is one whole value under one name, and every wash of a colour is one `color-mix()` reading
`currentColor` at one ratio. Four laws did most of the work, and each removed a zoo rather
than renaming one:

- **the tinted pill**, where six tone classes and eight state marks now read one
  `--sl-chip-bg` and the pointer deepens the same wash through `--sl-chip-tint-bg`
- **the focus ring**, where `outline` and `box-shadow` draw the same `--sl-ring-width` in the
  same `--sl-ring-bg`, and a status ring mixes its own colour at `--sl-ring-mix` — which is
  what let the two `--sl-field-*-ring` colours the map had been holding open disappear
- **the tile**, where a ground and a border are `--sl-ico-bg` and `--sl-ico-border` off
  `currentColor`, and only the ratio is hoisted where the tone is itself scoped
- **the arrow**, where every CSS triangle in the theme reads one `--sl-arrow-width`

**Six values moved on purpose and are named here rather than found later.** The three
favourite-burst durations `0.45 / 0.55 / 0.6` became one `--sl-fav-dur`, in the same move
admin made when its five alert animations took one `--sl-alert-dur`. The dialog's closing
motion was `0.13s ease-in` and is now `--sl-time-fast` with `--sl-ease-in-out`, which is the
spelling admin already had for the same keyframe. The switch's focus ring was painted in the
*valid* field colour and is now the theme's one focus ring. The inner photo of the sidebar
avatar carried a 3px collar beside its neighbour's 4px, and both are `--sl-ava-border: 4px`.
The chip washes `12 / 14 / 16%` and their hovers `22 / 24 / 26%` are one `14%` and one `24%`.
And the search button's hover restated its whole bevel one hundredth deeper; it now changes
its face and keeps its lift, which is the law every other control in this theme follows.

**Three figures could not be hoisted and say so.** A rate inside a `clamp()` and a percentage
gap were already fixtures; the third is the ratio of a `color-mix()` whose tone is a scoped
custom property. A root token reading `var(--sl-cat-tone)` resolves against the root, where
that name does not exist, so `--sl-tile-mix` and `--sl-ico-mix` carry the ratio and the tone
stays at the use site. That is the whole reason the `mix` prop exists.

**Motion, layers, gradients and every scale are closed in both themes.** Every lite
transition reaches its duration and its curve through a token, every layer sits on a
named role, every gradient lives at one address, and every figure of type, rhythm and
radius sits on a step, and lite carries both modes on the same ladders.

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

- 333 selectors exist in both: 216 byte-identical and **117 divergent**, of which
  **71 differ in values alone** — one canon carrying two skins, which needs no entry
  — and **46 differ in their property sets**, each with a written reason under
  `divergent`. The raw number rises while one theme is ahead of the other and falls
  back as the matching batch lands; what it can no longer do is hide behind a single
  figure, because the two halves answer to different rules.
- Same-named templates: `fragments` 51 shared / 30 identical, `partials` 13 / 6,
  `layouts` 2 / 0, `pages` 3 / 2. **31 carry different markup**, of which 28 are
  in canon scope — 21 `fragments` and 7 `partials`. Line endings accounted for four
  of the ones that closed, and they were never in the repository: `.gitattributes`
  normalises every `.html` to LF on the way into the index, so ten lite templates
  carrying CRLF were drift in one working tree. The tool reads the tree, so it saw
  four divergences a fresh clone would not have — worth knowing before trusting any
  figure it prints about templates.

Package a new theme copies: `lite` 667 files / 5069 KB, `admin` 491 / 3092 KB.

## Batches

### Batch 8 — the rest of canon, and the freeze

**Causa.** Independence makes divergence permanent. A contradiction shipped in
the etalon is taught to every descendant, and after distribution the names can no
longer be corrected.

**What is left.**

- Resolve the **28 same-named templates in canon scope** — 21 `fragments`, 7
  `partials`. Five closed on the way here and none of them by unifying markup:
  four differed only in line endings, which `.gitattributes` already says must be
  LF and ten lite templates were not, and `post-button` gained in lite the
  `form_attr` hook admin already had, which is inert when the key is absent.

  **Audit the call sites first.** A shared name is not a shared contract: the two
  `alert` fragments differ in the keys they accept (`is_flash`, `alert_attr`,
  extra wrappers), the two `dial` fragments differ by the whole htmx half, and
  unifying markup without reconciling keys silently drops data or changes what is
  escaped. List every caller, diff the key sets, decide the union, then unify.

  Measured on the way: `link` and `table-row` differ by over a hundred lines each
  and `block-content`, `inline-badge`, `table` and `span` by about forty. Those
  six are not one contract with two spellings; they are two contracts, and the
  decision for each is whether canon wants one.

- **Decide `.sl-hidden`, which means two different things.** In admin it is
  `display: none !important`; in lite it is `opacity: var(--sl-fade-disabled)`.
  Six lite call sites mean gone — an empty `sl-cab-badge`, a hidden row, a hidden
  image — and one means closed but still readable. An empty badge is drawn today
  as a red pill at 45 per cent instead of not at all. Settling it needs a second
  class for the dimmed state, which is a new name and needs asking for.

- **Decide the link focus of lite.** `a { outline: none }` kills the focus ring on
  every link on the site and nothing draws one in its place: lite has no
  `a:focus-visible` at all. Admin does not do this, which is why the pair sits in
  the divergence allowlist rather than being unified — taking the outline off admin
  too would spread the defect. The fix is a focus ring of the site's own, which is
  a pass over every link state.

- Look at **`important`**, 23 in admin and 17 in lite. Read once: most of it is
  need — a utility that has to win, the `prefers-reduced-motion` blocks, the icon
  face beating an element font, and the editor engine's own inline styles, which
  `skin.css` says so beside. What is left after those is small and worth naming
  rather than deleting blind.

- Give **`ThemeCreationTest` its HTTP half**. The static half is in the tree; the
  half that renders a real page needs HTTP and rides with the screenshot runner,
  which walks `tools/ui-shots.json` once more against the same scratch theme. They
  share one lifecycle — created once, both gates run, then removed in `finally`
  against a path the harness built. The HTTP half selects the theme before the
  request and restores it after; `getTheme()` caches in a static, so the switch
  must precede the first call.

- **Freeze the API and note it in the contract.** `frozen` is still `false`, and it
  stays false while any of the above is open: freezing before canon is settled bakes
  in names the remainder contradicts.

**Verification.** `--cross` reports only allowlisted divergences, each with a
reason, and the template halves of the same report reach zero or carry one.
`ThemeCreationTest` and the HTTP pass both succeed.

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
  list — the screenshot at `xl` showed titles short enough to hide it. **21 states over
  four breakpoints and two modes are captured today**, 168 images — front page, article, news and forum
  lists, forum topic, voting, content, login, profile, private messages, five admin
  sections, and the hover and focus states the manifest drives. A page outside that list
  is unguarded, and so is the twenty-second name: `admin-config` declares an optional
  `modal-open` step whose trigger the page does not carry, so it never shoots and the rig
  says nothing, which is what `optional` means.

  The profile and the private messages now carry baselines: the site login takes
  with the same credentials as the admin one, and the rig proves a session twice —
  something only a session shows must appear, and the password field must be gone.

  `noise-floor.json` holds one entry per page state and none for a state the manifest
  no longer names: the four `admin-users-*` leftovers of the `?name=users` module are
  gone. `--capture` does not clear such an entry by itself, so it is a hand edit.

  **The baselines are committed, so whatever they show is published.** The IP
  column of the user list and the mail fields of the configuration are masked for
  that reason: a guard against a layout change has no business carrying anyone's
  address into the repository.

  **Dark is guarded by pixels now, and batch 7 is why.** `modes` reads
  `["auto", "dark"]`, so the committed set is 168 images instead of 84. What that
  buys is the only gate that would have caught either of the two dark breaks batch 7
  shipped green through everything else: the headline over the photo band flipped from
  white to near-black, because the band is a photograph that has no dark variant while
  `--sl-on-solid` turns over; and the footer wordmark disappeared, because the band
  under it inverted and the wordmark is an image with white baked into it. **Both pass
  AA in both modes.** A contrast pair cannot report a logo, and it cannot tell
  "readable" from "as designed".

  The rule that stood in place of the gate still stands beside it: **a batch that
  touches colour opens the pages in dark and looks at them**, and says in its report
  that it did. A pixel diff reports that something moved, never whether what moved was
  meant to.

- **Baseline weight.** The image set is about 72 MB now, and a scale batch re-captures
  it whole, so each re-capture adds about that much to history permanently. If that
  becomes the wrong trade, the answer is to narrow the manifest, not to stop
  committing baselines — an uncommitted baseline is no baseline at all.
- **The committed baseline is not a "before".** The stand's own data moves between
  runs, so a `--check` against the committed set reports the week rather than the
  change. A batch captures the tree it starts from with `--out=` into a directory
  outside the repository and compares its own two captures; that is what the flag is
  for and there was no way to do it before batch 8.
- **Cache masking.** `cssfp` in `$conf['derived']['assets']` and
  `storage/cache/pages/*` can each serve a stale result over a correct edit, in
  either direction. Clear both, and disable `cache_css` **and** `css_h` — both
  are `'0'` in `config/global.php` today, and `config/local.php` must be deleted
  after any hand edit of `config/*` or it serves the old values.
- **The stand's own data moves, and the committed baseline goes stale with it.**
  A `--check` run at the head of batch 3, against a pristine tree, failed on every
  one of the images then committed — including the frontend, which the batch never touches.
  Nothing in the theme had changed: forum reply and view counters had moved, and a
  sidebar block whose file is missing under `blocks/` had begun rendering
  `_BLOCKPROBLEM` where it used to render nothing. Neither is masked. So a batch
  cannot trust the committed baseline as its "before": it must **capture the
  pristine tree first**, keep that capture outside the repository, and compare its
  own two captures. That is what batch 3 did, and it is what made "the frontend
  moved by 0.001% to 0.024%" a statement about the change instead of about the week.

- **An element that refetches itself on a timer cannot be part of a still image, and it
  was the whole class of drift.** Twelve frontend states failed a `--check` an hour after
  their own capture, by 0.21% to 0.28%, with the theme untouched between the two runs.
  Locating it took a diff that reports the bounding box of the changed pixels and then
  reads the DOM inside it: one band of 210 by 25 pixels, holding the session block's
  refresh control, an `hx-trigger="click, every 4s"` that replaces its own block forever.
  The shot lands wherever the swap happened to be. `[hx-trigger*="every"]` is masked now,
  together with the online counter and the four audience lines, which count whoever is on
  the stand at that second including the rig's own session.

  This is what closed the two states the plan had written off. Every one of the 60 page
  states now measures a noise floor **below the 0.2% threshold** - the largest is
  `admin-statistic` at `sm` with 0.090% - where before `admin-statistic` and
  `admin-monitor-knob-hover` were red against any baseline older than the current run.
  The fix belonged to the manifest and not to a theme batch, exactly as written; what was
  wrong was the guess about which element it was.

  **Three more of the same species were found the same way and are masked now.** A check
  run forty minutes after its own capture failed twelve states — `profile` at four
  viewports in both modes and `admin-statistic` at two — with the theme untouched between
  the two runs. The technique that named them is worth keeping: diff the leaf text of the
  page against itself across two loads with a page view in between, and read what changed.
  It answered in one line — two figures on the profile, `40 669` and `40 670`, had become
  `40 672` and `40 673`. They are the site's hit counter printed inside the profile ring
  and beside the avatar, and the statistic page draws the same counters as bar widths.
  `.sl-user-points b`, `.sl-cab-ring b` and `.sl-statx-bv` joined the mask; `profile` at
  `xl` fell from a 0.38% drift to a floor of zero.

  **A gate that is red on arrival is a gate somebody switches off**, so a batch that ships
  a fresh baseline runs `--check` against it afterwards rather than trusting the capture.
  Nothing here was a theme defect, and every one of the twelve would have been read as one
  by whoever ran the gate next.

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
  changed one. Horizontal overflow was zero on all 40 states before and after, and the
  clipped count never rose — it fell on one page and was otherwise equal. **The one
  regression of the batch showed up there and nowhere else**: the top menu wrapped onto
  a second row at 900px, which the image diff had drowned and no count could see. A
  scale batch should run that probe, not only `--check`.

- **The whole set is captured under one condition, and it must stay that way.** A run
  without `SLAED_UI_USER` and `SLAED_UI_PASS` skips the ten states that need a session
  and captures the rest logged out, which is a different baseline from the one a full
  run writes. Every image comes from a single pass with credentials, and the set
  passes `--check` twice with them and once without, because the frontend pages render
  in their own cookieless context either way. Capturing half a set in the degraded mode
  is what leaves a state guarding a page that no longer exists.
- **Rollback granularity.** A scale batch touches hundreds of declarations with a
  human review as the only gate; the per-property-group commit keeps one bad snap
  to one group.
- **Over-unification.** The temptation in canon work is to make the admin panel look
  like the site. A difference is legal when the two rules hold one property set,
  whatever their values say; where the property sets differ it is legal with a
  written reason. Neither reading permits repainting one theme in the other's image,
  and the reset pass is the shape to copy: every declaration added there spelled what
  the element already computed, so the structures met and almost no pixel moved.
- **A name read but never declared is invisible to the gate.** `dead` reports a
  token declared and never read; CSS answers an undeclared `var()` by dropping the
  declaration — no error, no warning, no pixel that says why — so `unmet` is what
  reports the reverse, and it is at zero in both themes. The two that were open,
  `--sl-float-top` and `--sl-float-left`, were read by both themes and written by
  nothing while `placeFloat()` set six inline properties and a hardcoded `3000`
  beside them. Both halves were wrong at once: the script was deciding layout and
  the theme was asking for a value nobody produced. The script writes
  `--sl-d-float-top` and `--sl-d-float-left` now and the theme owns the rule, which
  is the shape every other `--sl-d-*` already had.

  The four admin names batch 4 settled show both answers. `--sl-text-subtle` and
  `--sl-color-brand-strong` were read by `skin.css` and declared only in lite, which
  is why the editor looked right there and lost fifteen colours in admin — the first
  became an admin declaration, the second pointed at `--sl-primary-strong`, which is
  what it meant. `--sl-admin-tight-item-gap` and `--sl-admin-tight-line-height` were
  two names missing from a scoped set of six that was clearly written in one sitting;
  they were declared, and the sidebar gained the 4px row gap and the 20px line box the
  author had asked for and never got.
- **Premature freeze.** Freezing before canon is settled bakes in names the
  remainder contradicts. `frozen` is still `false`: every count is at zero, but 28
  shared templates still carry two contracts under one name, and `.sl-hidden` still
  means two things.
- **An occupied rename target.** Every mapping is checked against the target's
  current occupants; the tool reports a collision as an error.
- **Volume.** Every count either theme controls is at zero. What is left of batch 8
  is 28 templates and two decisions about a name, and what batch 9 carries is markup.
  Neither is counting.
- **A gate that measures the wrong thing cannot be answered, and looks like work
  instead of like a defect.** `dup` at 424 and `cross` at 135 were both one figure
  over three unlike populations, and a figure like that is answered by grinding
  rather than by thinking. Splitting each one into what it was adding together left
  50 duplicate groups and 46 divergences — sets small enough to decide one by one,
  which is what the check was for. Before adding a batch of work to close a count,
  read what the count is counting.
- **Consolidation is not compression.** Expect roughly 10–15% fewer rules, not a
  smaller codebase. The deliverable is one address per decision.
