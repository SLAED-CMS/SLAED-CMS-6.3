# Template System Documentation

This document describes the current template reality in the repository.

## Current State
The active file-backed template runtime in the current repository is `Template` in `core/classes/template.php`.

### Template Runtime
File:
- `core/classes/template.php`

Role:
- active runtime for current page, partial, and fragment rendering

Public API:
- `getHtmlPage()`
- `getHtmlPart()`
- `getHtmlFrag()`

Shared runtime object:
- `$tpl`

Created in:
- `core/system.php`

## Themes
Current theme directories:
- `templates/admin`
- `templates/lite`

## Theme Roles

### `templates/admin`
- current admin theme files
- contains admin layouts, pages, partials, and fragments used by the admin runtime

### `templates/lite`
- current bundled frontend theme
- contains frontend layouts, pages, partials, fragments, assets, and images
- includes a local copy of Bootstrap Icons under `assets/vendor/bootstrap-icons/`

## Theme Independence

`templates/admin` and every installed frontend theme are independent runtime packages. Each theme owns its layouts, pages, partials, fragments, CSS, JavaScript, vendor assets, and images. A theme must not include templates or load assets from another theme, and the runtime must not provide cross-theme fallback or inheritance.

Matching relative filenames do not imply a shared source file or byte-equal implementation. Theme inventories, DOM, selectors, and declaration values may evolve independently as long as each theme keeps its own runtime contract. `templates/common`, cross-theme `@import`, symbolic links, hard links, and copying assets from another theme at runtime are not supported.

PHP may provide the same data keys and semantic flags to multiple themes, but each active theme owns its HTML structure and CSS class mapping. Theme-local asset URLs must use an explicitly passed `theme` value or a path relative to the current theme asset.

## Theme Structure
Themes should follow this structure:

```text
templates/<theme>/
  index.php        # optional theme hook
  assets/
    css/
    js/
    vendor/
  layouts/
  pages/
  partials/
  fragments/
```

`index.html` is not the main architectural entry for the active template runtime.

Common active layout files in `templates/lite` include `layouts/app.html` and `layouts/home.html`. `templates/admin` uses admin-specific layouts such as `layouts/admin.html` and `layouts/bare.html`.

## Theme Hooks

`templates/<theme>/index.php` is the PHP extension hook for a theme.

This file name is intentional and should stay stable. Theme authors can use it
to add small theme-specific data providers without changing core files. It is
not a page renderer and it is not a replacement for layouts, pages, partials,
or fragments.

The core loads the active theme hook during bootstrap:

```php
if (is_file(BASE_DIR.'/templates/'.$theme.'/index.php')) {
    require_once BASE_DIR.'/templates/'.$theme.'/index.php';
}
```

Supported hook functions:

- `getThemeHeadVars(): array`
- `getThemeFootVars(): array`
- `getAdminHeadVars(): array`

These functions are optional. If a theme does not need additional runtime
variables, it does not need `index.php`.

### `getThemeHeadVars()`

Used by frontend rendering before the final page is opened.

Purpose:
- add theme-specific head-time variables
- prepare lightweight view data for `pages/` or `layouts/`
- enrich the default `$sitevars` array

Example:

```php
function getThemeHeadVars(): array {
    return [
        'season' => date('n') >= 12 ? 'winter' : '',
    ];
}
```

### `getThemeFootVars()`

Used by frontend rendering before the final page is closed.

Purpose:
- add theme-specific footer variables
- override small footer labels or controls
- enrich the final `$sitevars` array

Example:

```php
function getThemeFootVars(): array {
    return [
        'upper' => _PAGETOP,
    ];
}
```

### `getAdminHeadVars()`

Used by admin themes to enrich or override admin page variables.

Purpose:
- provide admin menu data or rendered menu HTML
- provide language switcher data or rendered language switcher HTML
- provide admin sidebar blocks
- provide login screen helper text

The bundled admin theme uses standard admin variables from `core/admin.php`:
`getAdminLayoutVars()`, `getAdminTopMenu()`, and `getAdminLanguageLinks()`.
Custom admin themes can define this hook when they need to override or enrich
those values.

### Hook Rules

Theme hooks should:
- return arrays only
- keep logic small and theme-specific
- prepare data for templates instead of building large page sections in PHP
- use `$tpl->getHtmlPart()` or `$tpl->getHtmlFrag()` when a small rendered
  fragment is still needed
- escape dynamic raw HTML if it is built directly in PHP
- avoid database writes and state-changing behavior
- avoid request routing and module business logic
- avoid defining functions that are not part of the documented hook contract

Theme hooks should not:
- output HTML directly with `echo`
- call `setHead()` or `setFoot()`
- start or close output buffers
- include module controllers
- replace layouts, pages, partials, or fragments
- contain large HTML strings that belong in `.html` template files

### Standard Implementation Direction

Default SLAED behavior lives in core runtime code and template files. Theme
hooks exist for extension and customization while keeping
`templates/<theme>/index.php` as the stable optional extension point for SLAED
users.

## Admin Theme Storage Hierarchy

For `templates/admin`, the storage hierarchy should be treated from larger composition units to smaller reusable contracts:

1. `layouts/`
- full page shells

2. `pages/`
- complete pages bound to routes or final screen outputs

3. `partials/`
- large composed sections of a page
- suitable for panel-like, section-like, or feature-level blocks

4. `fragments/`
- reusable UI contracts and low/mid-level building blocks
- should not become a dump for page-sized or section-sized compositions

5. `assets/`
- CSS, JS, vendor assets

## Admin Fragment Hierarchy

New admin fragments should be organized by UI structure, not by module scenario names like `add`, `edit`, or `config`.

Recommended logical ladder:

1. `form`
- top-level `<form>` contract

2. layout families and structural blocks
- `div-*`
- `table-*`
- `tabs-*`

3. structural children
- rows
- items
- hidden fields
- actions
- submit blocks

4. controls
- `input`
- `textarea`
- `select`
- `select-option`
- `checkbox`
- `radio`
- `button`

Practical implication:

- `add`, `edit`, and `config` should converge on the same structural form contracts
- naming should describe HTML structure and UI role, not module-specific business meaning
- tables should remain a separate family from div-based form layouts

## Admin Naming Direction

For the new admin fragment layer:

- prefer neutral structural names
- prefer naming from larger container to smaller child
- keep form layout contracts separate from page partials
- keep table contracts separate from form contracts

Current direction:

- `form`
- `div-*`
- `table-*`
- `tabs-*`
- control-level fragments such as `input`, `textarea`, `checkbox`

Avoid for new base contracts:

- module-prefixed names like `admin-foo-*`
- scenario-prefixed names like `add-*`, `edit-*`, `config-*` when the contract is actually generic
- mixing page-sized sections into `fragments/`
- **creating subdirectories in `fragments/` (e.g. `new/`, `blocks/`). The fragment namespace is rigorously flat.**

## Runtime Syntax
The current `Template` runtime supports:
- escaped output: `{{ var }}`
- raw output: `{{{ var }}}`
- language constants: `{{ _CONST }}` (UPPER_SNAKE_CASE with `_` prefix — resolved at runtime via `defined()`, cache-safe)
- conditions:
  - `{% if var %}`
  - `{% elseif var %}`
  - `{% else %}`
  - `{% endif %}`
  - boolean combinations with `and`, `or`, and `not`
  - dot-path lookups such as `user.name`
- loops:
  - `{% for item in items %}`
  - `{% endfor %}`
- includes:
  - `{% include 'menu' %}` (a bare name auto-resolves to `partials/<name>.html`)
  - `{% include 'partials/custom.html' %}`
  - `{% include 'fragments/foo.html' %}`
  - `{% include 'fragments/row.html' with row %}` (context passing supported)
- layout inheritance
- blocks
- components:
  - `{% component 'window-gallery' %}` (a bare name auto-resolves to `partials/<name>.html`)
  - `{% slot header %}`
- free site blocks:
  - `{% freeblock 15 %}` renders the site block with the given numeric id through `getBlocks()`
  - intended for free (infly) blocks placed in the layout outside the standard positions
  - block status, expiry, language binding, view rights and flyfix module rules all apply; unavailable blocks render as empty output
  - frontend only (skipped in the admin runtime), no nesting inside block output
  - not related to `{% block name %}` layout inheritance blocks

Additional confirmed runtime behavior:

- `extends` resolves parent layouts inside the active theme
- includes and components are validated against theme paths
- the runtime can register companion CSS and JS assets for included partials and components

## Current Migration Direction
HTML has left PHP and `--markup` holds it at zero, so this is a rule to keep rather than a direction to travel.
New template work should:
- keep HTML out of PHP
- pass plain data from PHP
- render through `Template/$tpl`
- avoid new bridge wrappers
- avoid fallback inside completed slices

Do not:
- add placeholder mapping helpers for new slices
- copy theme inventories from installations or snapshots that are not present in the current repository

### Admin Semantic Wrapper Variants

`templates/admin/partials/div.html` owns two specialized wrappers through semantic flags:

- `is_searchbox` renders `sl-searchbox` only when trusted `content_html` is non-empty
- `is_menu_grid` always renders the `sl-menu-grid` wrapper, including an empty grid

The branch order is `is_searchbox`, `is_menu_grid`, generic `content_html`, then generic `rows`. The generic content contract and the `rows`-driven `sl-div-grid` contract remain independent. PHP callers pass renderer-produced `content_html` and flags; they do not pass wrapper CSS classes.

Frontend theme assets referenced by a partial must use an explicitly passed and escaped `theme` value. Page/layout variables are not implicitly injected into direct `getHtmlPart()` calls.

## Current Runtime Status

Confirmed current usage:

- frontend/admin bootstrap creates `$tpl = new Template($theme)` in `core/system.php`
- admin requests force `$conf['theme'] = 'admin'` in `core/system.php`
- direct admin helper endpoints can create `new Template('admin')` from `index.php`
- frontend pages are finalized through `setFoot()` and `$tpl->getHtmlPage(...)`
- admin page rendering also ends in `$tpl->getHtmlPage(...)`
- frontend and admin fragments are rendered through `$tpl->getHtmlFrag(...)`
- PHP callers render larger reusable parts through `$tpl->getHtmlPart(...)`

Current template-related tests include:

- `tests/TemplateValidationTest.php`
- `tests/Unit/AdminCssClassUsageTest.php`
- `tests/Unit/AdminLoginBridgeFlowTest.php`
- `tests/Unit/AdminPageRenderFlowTest.php`
- `tests/Unit/AdminPreviewBridgeFlowTest.php`
- `tests/Unit/AdminSearchboxBridgeFlowTest.php`
- `tests/Unit/ViewBridgeSmokeTest.php`

## Assets
Theme-local assets should live inside the theme.

Recommended pattern:
- `assets/css/base.css` and `assets/css/theme.css` — the two CSS files every theme package ships
- `assets/js/<name>.js` — theme-owned scripts; `lite` carries `lib.js`, `admin` carries `admin-ui.js`
- `assets/vendor/<library>/...`
- `assets/editors/<editor-id>/skin.css` — when an editor manifest declares `theme.skin`

Example already present — the icon stylesheet and its WOFF2 font, nothing else of Bootstrap:
- `templates/lite/assets/vendor/bootstrap-icons/`
- `templates/admin/assets/vendor/bootstrap-icons/`

### Automatic Asset Loading
The current runtime automatically injects CSS and JS files for components and blocks. If a file named identically to the included partial exists — `partials/<name>.css` or `partials/<name>.js` beside `partials/<name>.html` — the engine detects it at compile time and adds the asset to the page. No shipped partial uses this today; the mechanism is available, not idiomatic.

The tag around that asset belongs to the theme, not to the engine: `addAssetPath()` renders it through `fragments/head-link.html` and `fragments/head-script-src.html`, the same two fragments every other `<link>` and `<script>` of the document goes through. They are reached with `getHtml()` rather than `getHtmlFrag()` on purpose — `getHtmlFrag()` resets the asset list it is in the middle of building, and would re-emit what it had already collected.

## Head and SEO Integration

Frontend modules normally prepare head data through `setHead()` before the final page is rendered.

Current SEO contract:

```php
setHead([
    'title' => $title,
    'kind' => 'news',
    'ctitle' => $category,
    'desc' => $description,
    'time' => $published,
    'mtime' => $modified,
    'author' => $author,
    'img' => $image,
]);
```

Behavior:

- supported page kinds are `website`, `collection`, `article`, `news`, `product`, `forum`, `profile`, and `utility`
- `canon` and `robots` are explicit overrides; normal routes use route-aware defaults
- search, account forms, add/send flows, and other service routes are `noindex, follow` without canonical
- head values are rendered through context-specific fragments; raw HTML concatenation is not allowed
- configurable Open Graph and Schema.org templates are parsed and re-serialized before output
- `jsonld` accepts one object or a list of objects and is encoded centrally
- `config/local.php` is a generated snapshot and must not be edited manually

Heading components use explicit boolean context flags because the template engine does not compare string values. Cards use `is_detail` for `H1`, `is_nested` for `H3`, and `H2` by default. Voting uses exactly one of `is_page`, `is_section`, or `is_widget`.

## Engine Limitations
- no advanced expression language
- no equality/comparison operators in `{% if %}` expressions, such as `==`, `!=`, `<`, `>`, `<=`, or `>=`
- no complex named include arguments as a supported baseline (simple context passing like `with item` is permitted)
- no deep inheritance chains

## Form Row Contract

A form row is a caption and a control, joined by explicit ids and never by wrapping the row in a `<label>` — the field cell is a `<div>`, and a `<div>` inside a `<label>` breaks the content model. The panel row is `fragments/div-row.html` (`.sl-div-item` inside `.sl-div-grid`), the site row `fragments/form-field-row.html` (`.sl-form-row` inside `.sl-form`).

**`getFieldIds(string $id, string $mint = ''): array`** in `core/helpers.php` is the one owner of the three ids a row needs, returning `input`, `label` and `hint`. It **takes** the control id and derives the other two from it — `f-dump-skip` gives `f-dump-skip-label` and `f-dump-skip-hint`. It never derives the control id from the field name: this tree writes ids by hand and the mapping is no rule, so re-deriving one would rewrite an id that JavaScript, CSS or an outside reference hangs on. An existing id passes through untouched, duplicates included; the crawl's duplicate-id assertion is what guards those.

Only a row whose field has no labelable control of its own — a radio group, an editor, a date pair — mints an id, from the seed the caller names and a per-request counter, so two rows of the same shape on one page cannot collide.

| Key | On | Carries |
|---|---|---|
| `label_for` | the row | the id of a labelable control; renders `<label for>`, and a `<span>` without it |
| `label_id` | the row | the id of the caption, for a field that cannot be pointed at with `for` |
| `hint_html` | the row | the explanation, a `<span class="sl-small">` beside the caption and outside the `<label>` |
| `hint_id` | the row | the id of that hint |
| `labelledby` | `getTplRadioGroup()`, `getTplTextarea()`, `block-content`, `editor-mount` | the caption id the group or editor is named by |
| `describedby` | `input`, `select`, `textarea`, `checkbox`, `block-content`, `div` | the hint id the control is described by |

- **A radio group carries the name, not its radios.** `getTplRadioGroup()` renders `fragments/block-content.html` with `is_radio_group`, which writes `role="group"` and `aria-labelledby`. The switch variant is a group too: two radios sharing one name still answer one question. A checkbox list built through `partials/div.html` with `is_radio_group` takes the same two attributes.
- **The hint is never part of the caption.** It sits in the label cell and outside the `<label>`, because a caption that swallowed it would read the explanation out as the name of the field and the description would then be announced twice.
- **An editor is named server-side and moved by its driver.** `Editor::getContent()` settles the name once — `labelledby` when the row has a caption, `aria-label` when it has none — and hands both down. `plain` writes them onto its `<textarea>`; `toastui` and `ckeditor` write them onto the mount and their own JS moves them to the element that actually holds `role="textbox"`; **TinyMCE takes `aria-label` only**, because it puts its editable body in a second document and an IDREF does not cross that boundary. `codemirror` implements a different interface, is reached only by `Editor::getCode()`, and has no naming contract yet.
- **The read-only value row is not a form row.** `fragments/field-value.html` renders `.sl-value-row` / `.sl-value-label` / `.sl-value-text` in both themes, with a `<span>` caption that labels nothing. `.sl-form-*` means the editable row and nothing else. The width token of the panel caption keeps the name `--sl-form-label-width`: that API block is frozen, and a theme copied from it reads a name this tree cannot reach to rename.
- **A row folds on the box it stands in.** Both themes keep the viewport step at 900px and add a container step beside it: `.sl-div-grid` and `.sl-form` (and `.sl-oauth-form`) declare `container-name`, so a grid nested in another row's field cell, a composer in a narrow pane and an OAuth card in a `minmax(280px, 1fr)` column fold on their own width. The radio-group ladder moves with the row — both steps or neither, or a single-column row ends up holding a four-across group.

## Settings Page Contract

`index.php?name=account&op=edithome` is the one page in the tree whose whole body is nested data: `modules/account/index.php` hands `partials/account-settings.html` a list of sections, each holding tiles, each tile holding lines, fields, rows or a log, and the template owns every tag and every class. PHP names a width number and a tone number; the template maps both. No CSS class name crosses that boundary.

| Level | Key | Carries |
|---|---|---|
| page | `sections` | the sections that actually rendered, in reading order |
| page | `lamps`, `rail` | the state tiles above the form and the marks of the scroll spy |
| section | `id`, `icon`, `title` | the anchor the rail points at, and its heading |
| section | `inform` | this section's tiles belong inside the shared form |
| section | `alert_html` | a validation message tagged with this section's name, rendered beside its cause |
| tile | `width` | 2, 3, 4 or 6 of a six-track grid; the template writes `sl-opt-w<n>` |
| tile | `tone` | 0 to 5; the template writes `sl-cat-tone-<n>` |
| tile | `fields`, `lines`, `rows_html`, `log`, `meter`, `face_src`, `text` | the six shapes a tile can hold |

- **The width is a number and never a class.** PHP owns how much of the row a tile deserves, the theme owns what that means in pixels, and the four widths are the only arithmetic the page carries. A tile at 3 is about 345 points on this layout, which is where a caption-left row folds — so the fold is a property of the tile, not of the window.
- **The tile is the fold container.** `.sl-opt-tile` declares `container-type: inline-size` and `container-name: sl-form-box`, the name the form row already watches, so every row inside folds by the rule the rest of the tree carries rather than by a second one.
- **The form opens and closes around sections, not inside them.** The template opens the shared form before the first section marked `inform` and closes it after the last, so the form, the password form and the OAuth unlink buttons are siblings and never nest. The password keeps its own form on purpose: a member who filled fifteen fields and mistyped the old password must not be told "saved" about half the page.
- **The rail counts what rendered.** A section that produces nothing is never appended, so the rail is five marks on an account with no external links and never a fixed six; below two sections there is no rail at all.
- **Open defect: a validation stop discards the whole input.** On any `$stop` the handler calls `edithome()`, which rebuilds every field from the stored row — measured with a deliberately broken token, a typed `occ` came back as the stored value. The save bar is honest about it, since nothing is unsaved once nothing survived, but the premise the shared form was built on is not: the reason the password keeps a form of its own is exactly the outcome the shared form still produces. Repopulating from the POST when one is present is the fix, and it is not written.
- **A read-only value is not a row.** The settings tiles reuse `fragments/field-value.html`, and `.sl-opt-tile .sl-value-text` is masked in the screenshot rig because a points counter and a last-activity stamp move on their own.

**Three page behaviours live in `plugins/system/slaed.js` and only on this page.**

| Attribute | On | Does |
|---|---|---|
| `data-sl-spy` with `data-sl-spy-mark="<id>"` | the rail and each mark | marks the section under the observer band as current and colours the road behind it; the bottom of the document reads as the last mark, because a short final section never rises into the band |
| `data-sl-meter` with `data-sl-meter-fill`, `data-sl-meter-num`, `data-sl-meter-left` | the ring, the lamp and each counted control | recomputes profile completeness as the member types; `data-sl-meter-fill` carries the value that counts as empty, so one rule serves a text field and a select whose zero is a real option |
| `data-sl-dirty` with `data-sl-clean` | the shared form and the discard button | raises the save bar on the first change and reverts on discard. The hidden state is armed by the script and never by the markup: a page whose JavaScript never ran keeps a bar that is simply always there, instead of a form whose only submit can no longer be made to appear |

## Theme Contract

A new theme is made by copying an etalon and editing one file: `templates/<theme>/assets/css/base.css` — its `@font-face` and `:root` block, down to the `/* --- end tokens --- */` marker. Everything below the marker is reset and element styles.

- **Two CSS files per theme.** `base.css` holds `@font-face`, the `:root` API block, the marker, then reset and element styles. `theme.css` holds components and no literal visual value.
- **Themes are independent.** The engine has no inheritance. Rules two themes share stay duplicated on purpose.
- **The API is frozen.** A theme package may gain a role and may never lose or rename one. The roster under `api` in `tools/ui-audit-baseline.json` holds it on every run and against `--store`.
- **Canon is CSS, `fragments/` and `partials/`.** `layouts/` and `pages/` are outside it: the page shells of a panel and a site differ by nature.
- **Breakpoints are canon, not API.** `@media` cannot read a custom property, so a theme decides how things look at a breakpoint, never where it sits.
- **`assets/editors/*/skin.css`** consumes tokens, declares no API, and is byte-identical in both themes — `EditorWindowTest` enforces that, so it is edited in both in one commit.
- **`checkThemeAssets()`** in `core/system.php` defines the files a theme must ship; the same list is mirrored under `skeleton` in `tools/ui-contract.php`.

Axes, ladders, the allowlist, categorical sets, declared component names, the ramp and the ratchet list live in `tools/ui-contract.php`. That file is the contract: it is committed, and it is what every tool reads.

- **`error.html`** is the one surface that renders when the CMS cannot, so it carries its own `:root` instead of reading a theme. Its values are the light half of `lite`, because no server is there to write `data-theme`.
- **`--sl-size-chip`** is read by `plugins/system/slaed.js` for speed-dial geometry and cannot be renamed without touching the script in the same commit. Names written from outside CSS carry `--sl-d-*` and are registered under `data` in the contract.

Both themes stand at **zero untokenised visual decisions**, and every ratcheted count is at zero except `scoped` and `important`. Neither of those two is a defect list: a scoped custom property on a component root is an internal the rules permit, and an `!important` is permitted where it is need. What the ratchet buys is that neither can grow.

## Theme Gates

| Command | Checks |
|---|---|
| `php tools/ui-audit.php` | every count for both themes; exits non-zero when a ratcheted count grew |
| `php tools/ui-audit.php --markup` | no class attribute, inline style or HTML tag hardcoded in PHP; `limit` is `0` |
| `php tools/ui-audit.php --file=<css> --migrating` | one file, naming the token that replaces each violation |
| `php tools/ui-audit.php --store` | rewrites `tools/ui-audit-baseline.json` from the current tree |
| `vendor/bin/phpunit` | `ThemeContractTest` (ratchet + contrast registry), `UiAuditTest` (the tool against fixtures), `ThemeCreationTest` (a scratch copy audits clean and renders) |
| `npm run ui:before` | captures the tree you are about to change into a temp directory |
| `npm run ui:after` | empties the caches, compares against that capture, regenerates the contrast registry when a `base.css` moved, then reads the counts |
| `npm run ui:gates` | the fast gates by hand — the same set the pre-commit hook runs |
| `npm run ui:label` | the label crawl: every `for`, id and aria reference on every rendered route, against `tools/label-audit-baseline.json` |
| `node tools/ui-shots.mjs --capture --out=<dir>` | writes a PNG set and each state's measured noise floor |
| `node tools/ui-shots.mjs --check --out=<dir>` | compares the tree against a set captured earlier; a missing image fails the run instead of being written |
| `node tools/ui-shots.mjs --contrast` | regenerates `tools/ui-contrast.json`, the pairs that really meet on screen |
| `node tools/ui-shots.mjs --newtheme` | builds a scratch theme and serves every frontend page of the manifest in it |

**The fast gates run themselves.** `tools/hooks/pre-commit` runs the audit, the markup scan and the theme, template and parser tests on every commit that carries a file able to move a count — about twenty seconds, and nothing at all on a documentation commit. Enable it once per clone with `npm run ui:hooks` (`git config core.hooksPath tools/hooks`); skip one commit with `SLAED_SKIP_GATES=1`. When a commit touches theme CSS or a canon template it also prints the two visual commands, because no count can see a moved pixel.

**The visual gate is a pair of commands, and it has to be.** `ui:before` captures the tree before the edit, which no hook can be in time for; everything after that is done for you. Both refuse to start without `SLAED_UI_USER` and `SLAED_UI_PASS`, because a run without them skips every state that needs a session and silently guards two thirds of the manifest.

Re-store the audit baseline at the end of any change that moves a count. A baseline written once and never lowered lets a regression from 300 back to 350 pass against an old 570.

A change to `tools/ui-audit.php` needs a fixture in `tests/Fixtures/ui/` covering it. The tool is the authority for every count, so a wrong classifier is invisible: it writes a plausible number into the baseline and everything downstream trusts it.

## Theme Risks And Findings

### CSS and markup

- A shorthand resets every longhand it omits. Folding three longhands into one shorthand silently restores the initial value of the fourth.
- An SVG presentation attribute — `stroke`, `fill`, `filter` — loses to any CSS rule. Where the theme styles the class, the attribute on the element is dead weight.
- `[hidden]` carries `!important` in both resets. To hide something a component gives a `display` to, use the attribute; a class of equal specificity loses to any rule declared later.
- A custom property substitutes its `var()` where it is **declared**, so a `:root` token that reads a scoped one resolves against the root, not against the element.
- A theme cannot restyle what PHP hardcodes. PHP passes data, text, URLs, attributes and semantic flags; templates own structure and class mapping; CSS owns values.

### Template engine

- `{% if %}` is truthiness-only. A value of `0` or `'0'` is false, so pass an explicit flag when zero must still render.
- `{% include 'x' %}` **without** `with` inherits the caller's scope. A flag name a page sets leaks into the fragment, so a flag must not reuse a name that means something else elsewhere.
- `getHtmlFrag()` and `getHtmlPart()` reset the asset list they then re-emit. Code running while a page's assets are being gathered must reach the engine through `getHtml()` instead.

### Screenshot rig

- **Nothing may touch the tree while the rig runs.** A capture takes about a quarter of an hour. A `git stash` during that window leaves a set half from one tree and half from another, with nothing in the output to say so.
- **Always compare two captures of your own.** The stand's own data moves between runs, so a `--check` against any set captured earlier than minutes ago reports the week rather than the change. `ui:before` and `ui:after` exist to make that pair the default path.
- **Re-capture only the states that moved.** `--capture --only=<page>` writes that page's images and merges its entries into `noise-floor.json`, leaving every other floor and image untouched.
- Before capturing, empty `storage/cache/pages/*` and `storage/cache/templates/*`, keep `cache_css` and `css_h` at `'0'`, and delete `config/local.php` after any hand edit of `config/*`.
- Motion is switched off with `animation: none`. A near-zero duration does not stop an infinite animation; it makes it cycle as fast as the compositor can draw, and the frame a screenshot catches is then chosen by the scheduler.
- An element that refetches itself on a timer cannot be part of a still image. `mask` hides what moves inside a box of stable size; `drop` takes out of layout what changes size. A mask cannot save a box whose height is what moves.
- **Masking an element for the pixel gate removes it from the contrast gate.** The pair collector skips `visibility: hidden` and `display: none`, which is what `mask` and `drop` apply. The two gates trade against each other.
- A change that moves every page height gets nothing from an image diff. Drive a geometry probe over the same manifest instead: horizontal overflow, page height, and the count of elements whose box leaves the viewport, on the pristine tree and on the changed one.
- The rig runs over `https`. `setCookies()` marks the session cookie `secure` when `homeurl` is `https`, so a run over plain `http` fills the login form and is handed a cookie the browser drops.
- Credentials come from `SLAED_UI_USER` and `SLAED_UI_PASS` and are never stored in the manifest. A run without them skips every state that needs a session and captures the rest logged out, which is a different baseline from the one a full run writes.
- A gate that is red on arrival is a gate somebody switches off, so run `--check` against a freshly captured baseline rather than trusting the capture.

### Label crawl

`node tools/label-audit.mjs`, own script `npm run ui:label`. It walks the panel, the site as a member and the site as a guest — three sets of form rows, and only the guest is shown the login, registration and lost-password forms — and asks of every rendered document the questions no count in `tools/ui-audit.php` can ask, because they are questions about a document rather than about a file.

| Flag | What |
|---|---|
| *(none)* | crawl, and fail on anything outside `tools/label-audit-baseline.json` |
| `--store` | rewrite that baseline, refusing to store a count that grew or coverage that fell |
| `--census` | also print, per route, how many rows, groups, editors and hints were seen |
| `--only=x` | audit only routes whose signature contains `x`; the walk itself is unchanged |

Forward, if the attribute is there it must be right: every `for` resolves to a labelable element, no id repeats, no `<label>` nests, every `<label>` has a `for` or a labelable descendant, every aria IDREF resolves to an element with text. Reverse, the attribute must exist: every `.sl-radio-group` carries `role="group"` and a resolving `aria-labelledby`, every visible editable has a name, and every element carrying a `-hint` id is referenced by an `aria-describedby` in its own row.

- **Not part of `npm run ui:gates`**, deliberately: the gates run from `tools/hooks/pre-commit` on every commit and this needs an HTTPS stand, a browser and administrator credentials. The hook prints a reminder instead. Moving it into the gates means changing the hook in the same commit.
- **It walks frames, not the main document.** TinyMCE puts its editable body in a separate document; a main-frame query sees the `<iframe>` and stops, so an unnamed editor reads as clean.
- **Coverage is stored beside the violations and judged apart from them.** Record-bound forms are reached by following the first `op=edit&id=…` link of each list page, so an empty list or a renamed link makes violations vanish — and a baseline holding violations alone reads that as a fix. Violations down is a pass; coverage down is its own failure and offsets nothing.
- **Run one at a time.** Two crawls share one stand and one set of sessions, and neither result means anything.
- **Three keys are normalised or the baseline never settles**: parameters naming a record are out of the route signature, runtime state classes are out of the selector, and an id that is nothing but digits reads `<record>`.
- **The editor is a per-account setting**, so one crawl of the stand proves one driver. `ckeditor` and `tinymce` are admin-only and are switched through `admin.php?name=admins`; the setting is read into the session at sign-in, so each driver needs a session of its own.

### Contrast registry

- `tools/ui-contrast.json` is generated by `--contrast` and read by the audit on every run. If it is missing, the contrast check has no pairs and reads zero while checking nothing; `ThemeContractTest` asserts the file exists and carries pairs for every theme.
- The walk composites sheer layers instead of skipping them, and does it per layer of one `background-image`, back to front — a hatch over a gradient stands on that gradient, not on the page two boxes further out.
- **A masked element is invisible to the walk, so its colours are nobody's.** The mask list of `tools/ui-shots.json` keeps a shot still by hiding what moves, and the crawler sees the same hidden element: `time` is masked, and the registry holds no `time` pair on any page of the tree. The same goes for anything hidden when the page loads — a save bar that appears on the first keystroke is never sighted either. A component that falls in either hole has no measured contrast at all, and the only honest way to get one is to read the computed colours off the live element by hand and write the figure down.
- Regenerate the registry in the same session as the manifest that feeds it. Comparing a registry against one taken under a different `mask`/`drop` list measures the difference between two runs, not a change.

### Why the baseline is not committed

A full-page pixel baseline cannot be stable on a stand whose own content moves. Measured: a `--check` against a committed set went red hours after capture on a tree whose frontend was byte-identical — `100% size changed` at `sm`, `md` and `lg` on the front page and the forum list, and 1.6% to 3.9% even at `xl`. The page height follows the content, and a mask cannot save a box whose height is what moves.

So the reference is captured per change, minutes before the comparison, into a temp directory: that is what `ui:before` and `ui:after` do, and the two captures are close enough in time that drift stays under each state's measured noise floor. `/tools/ui-baseline/` is gitignored — the path still works for a local set, it simply never enters history again.

For the record, in case a stand with frozen content ever makes a committed set worth it: 168 images weigh about 80 MB, a PNG does not delta-compress, so each re-captured image is a fresh blob forever. Re-encoding is not a lever — Chromium already writes these about 9% smaller than a maximum-compression GD re-encode. Narrowing the manifest is not a lever either: every viewport and both modes have each caught a regression no other gate could see. `--capture --only=<page>` is the lever, writing one page's images and merging its entries into `noise-floor.json`.

## Migration Source Of Truth
For active repository template guidance and current runtime status, use this document (`docs/TEMPLATES.md`).
