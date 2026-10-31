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
  images/
  sound/           # optional voices of the theme
  layouts/
  pages/
  partials/
  fragments/
  sections/        # optional section types of the site builder, <type>.json and <type>.html
  places.json      # optional parts, menus and zones of blocks the site builder edits
  presets.json     # optional ready pages of the site builder
```

The last three are the contract of the site builder, "Layout Sections" below; a site theme without them shows no
sections and no parts, and the panel theme `admin` has none.

`sound/` holds the voices `plugins/system/sound.js` plays: `success`, `error`, `warn`, `queue`, `win` in both themes,
`inbox` and `send` in `lite`, each `<voice>.mp3`. The layout names the folder on `<html>` as `data-sl-sound` from
`getThemeFileUrl('sound/')`, and `getAssetList()` adds the engine to the derived script list only for a theme that has
the folder, so no request looks at the disk and a theme without it stays silent; `checkThemeAssets()` does not ask for it. A voice sounds only in answer to an action or an
event, not before the first touch of the page, at most once in 2.5 s per kind, and only while the switch "Звук" of
the panel Options and its zone in the profile settings are on — both kept in this browser through
`getToggleState()` (`sound`, `sound-zones`). Scripts call `window.setSoundPlay(voice)`; a response of the server
names its voice by `HX-Trigger: {"sl-sound": "<voice>"}`.

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
- the root of the site: `{{ root }}`, put into every render by `Template::getView()` (see "Links In Templates")
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
- admin requests get the theme `admin` from `getTheme()` in `core/system.php`; `$conf['theme']` stays the site theme, which the panel reads for the logos and banners of the site; a rank is drawn by the fragment of the theme in use and reads no file of the site theme
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
- `assets/css/base.css` and `assets/css/theme.css` — the two CSS files every theme package ships; `lite` adds `assets/css/presentation.css`, the stylesheet of the one page of the presentation module, and `getThemeAssets()` lists every file of `assets/css/` in name order, so it sits between the two
- `assets/js/<name>.js` — theme-owned scripts; `admin` carries `admin-ui.js`, `lite` ships none
- a script one page needs stays out of the global `script_f` list: the module hands `script => ['src' => 'plugins/<name>/<name>.js', 'attr' => 'defer']` to its partial, which prints it through `fragments/head-script-src.html` — `plugins/presentation/presentation.js` is loaded this way, the captcha provider the same
- `assets/vendor/<library>/...`
- `assets/editors/<editor-id>/skin.css` — when an editor manifest declares `theme.skin`

Example already present — the icon stylesheet and its WOFF2 font, nothing else of Bootstrap:
- `templates/lite/assets/vendor/bootstrap-icons/`
- `templates/admin/assets/vendor/bootstrap-icons/`

### Versioned Addresses And Order
Every `<link>` and `<script src>` a theme prints carries the version of the file's content, `?v=` with the first ten hex characters of its SHA-1, from `Template::getAssetUrl()`; the browser keeps such a file a year and a changed file arrives under a new address. A template never writes an asset address by hand: it receives `href` or `src` already versioned from PHP and prints it through `fragments/head-link.html` or `fragments/head-script-src.html`; the one own tag is the module loader of `fragments/captcha-altcha.html`, whose `script_src` comes versioned from `Captcha` as well. A CSS or JS file added to a theme or a plugin gets its version with the next rebuild of `config/local.php`; a file the derived map does not know is printed plain and kept a week. A font or an image reached by a relative `url()` of a stylesheet carries no version and is kept a week as well. Every head script is `defer` and runs in the order of `getAssetList()`, so an inline script that calls one of them waits for `DOMContentLoaded`. The lifetimes, the server rules and the measurement are in `docs/PERFORMANCE.md`, section "Static Assets".

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
    'route' => $path,
]);
```

Behavior:

- `route` is the current path of the entity of the page from its reader (`null` for an entity without one); the canonical address is built from it, and `setHead()` first answers any other form of the page with one 301 through `setSeoMove()` (`docs/ARCHITECTURE.md`, "Address Boundary")
- a page of the site builder passes `title`, `desc`, `img` (its picture for social networks), `robots` (`noindex, follow` for a page hidden from search engines) and its path as `route`; read from the draft it is `noindex` with no canonical address (`docs/ARCHITECTURE.md`, "Layout Boundary")
- supported page kinds are `website`, `collection`, `article`, `news`, `product`, `forum`, `profile`, and `utility`
- `canon` and `robots` are explicit overrides; normal routes use route-aware defaults
- search, account forms, add/send flows, and other service routes are `noindex, follow` without canonical
- head values are rendered through context-specific fragments; raw HTML concatenation is not allowed
- configurable Open Graph and Schema.org templates are parsed and re-serialized before output
- `jsonld` accepts one object or a list of objects and is encoded centrally
- `config/local.php` is a generated snapshot and must not be edited manually

Heading components use explicit boolean context flags because the template engine does not compare string values. Cards use `is_detail` for `H1`, `is_nested` for `H3`, and `H2` by default. Voting uses exactly one of `is_page`, `is_section`, or `is_widget`.

## Links In Templates

A page is opened at a path such as `/news/internet`, so a relative address in its markup would resolve under `/news/`. Every address a template prints starts at the root of the site, `{{ root }}`, which is `HOME_PATH` — `/`, or `/sub/` for a site below a folder:

- the address of an entity comes from PHP, built by `getSeoUrl()` from the path its reader joined; a template never assembles one from an `id`
- a fixed page link is a path: `{{ root }}account/privat`, `{{ root }}files`, `{{ root }}search`
- endpoints, forms and htmx attributes keep their query behind the root: `{{ root }}index.php?go=1&op=…`, `action="{{ root }}index.php"`
- a file of the theme is `{{ root }}templates/{{ theme }}/images/…` in a template and `getThemeFileUrl('images/…')` in PHP, from the root of the theme; `getThemeFilePath()` stays the path of the file below `PUBLIC_DIR`
- an asset reaches the template versioned and rooted from `Template::getAssetUrl()` (see "Versioned Addresses And Order")
- a fragment link `href="#…"` stays as it is; there is no `<base>` tag, which would send it to the start page

`RouteBuildTest::aPageAtAPathCarriesNoRelativeAddress` opens pages at paths of two and three segments as a guest, a member and the administrator and fails on any `href`, `src`, `action`, `hx-*` or `data-*` address, inline `url()` or `fetch()` that does not start at the root.

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
| `labelledby` | `getTplRadioGroup()`, `getTplTextarea()`, `block-content`, `editor-frame`, `editor-mount` | the caption id the group or editor is named by |
| `describedby` | `input`, `select`, `textarea`, `checkbox`, `block-content`, `div` | the hint id the control is described by |

- **A radio group carries the name, not its radios.** `getTplRadioGroup()` renders `fragments/block-content.html` with `is_radio_group`, which writes `role="group"` and `aria-labelledby`. The switch variant is a group too: two radios sharing one name still answer one question. A list of checkboxes is `fragments/check-list.html` in both themes and nothing else: the items are the data of `fragments/checkbox.html`, the group writes `role="group"` and the `aria-labelledby` of its row label, a row of a tree carries `--sl-d-depth`, and a full admin row lays it out in the columns of a radio group. No form offers a `<select multiple>`: a choice of several never asks for a modifier key.
- **The hint is never part of the caption.** It sits in the label cell and outside the `<label>`, because a caption that swallowed it would read the explanation out as the name of the field and the description would then be announced twice.
- **An editor is named server-side and moved by its driver.** `Editor::getContent()` settles the name once — `labelledby` when the row has a caption, `aria-label` when it has none — and hands both down. `plain` and `codemirror` write them onto the `<textarea>` of `editor-frame`, and the shell runtime copies the name onto the editable area of CodeMirror when it mounts; `toastui` and `ckeditor` write them onto the mount and their own JS moves them to the element that actually holds `role="textbox"`; **TinyMCE takes `aria-label` only**, because it puts its editable body in a second document and an IDREF does not cross that boundary. A code editor of `Editor::getCode()` is named by the `label` of its caller, or `_TEXT`.
- **The read-only value row is not a form row.** `fragments/field-value.html` renders `.sl-value-row` / `.sl-value-label` / `.sl-value-text` in both themes, with a `<span>` caption that labels nothing. `.sl-form-*` means the editable row and nothing else. The width token of the panel caption keeps the name `--sl-form-label-width`: that API block is frozen, and a theme copied from it reads a name this tree cannot reach to rename.
- **A row folds on the box it stands in.** Both themes keep the viewport step at 900px and add a container step beside it: `.sl-div-grid` and `.sl-form` (and `.sl-oauth-form`) declare `container-name`, so a grid nested in another row's field cell, a composer in a narrow pane and an OAuth card in a `minmax(280px, 1fr)` column fold on their own width. The radio-group ladder moves with the row — both steps or neither, or a single-column row ends up holding a four-across group.

## Editor Fragments

The editor shell is canon: its fragments are byte-identical in `templates/admin` and `templates/lite`, its rules stand in
both `theme.css`, its names in `tools/ui-contract.php`. `Editor::getFrame()` in `core/classes/editor.php` renders them;
a driver passes data and writes no class string. The behaviour is `plugins/system/editor.js`, described with the data
keys in `docs/EDITORS.md`, "The Shell".

| Fragment | Rendered by | Holds |
|---|---|---|
| `fragments/editor-frame.html` | `Editor::getFrame()`, once per editor of Plain Plus and CodeMirror | `.sl-editor` with its `data-sl-editor-*` attributes, the format tab, the `<textarea>` that carries the value, the status line |
| `fragments/editor-kit.html` | `Editor::getFrame()`, once per answer | a `<template data-sl-editor-kit>` with the words as JSON, the capsule, the draft offer, the findings, the preview pane, the variable list, the palette and the comparison dialogs |
| `partials/emoji-panel.html` | `Editor::getEmojiPanel()`, once per answer | the four `<template data-tpl>` of the emoji panel with its words and script addresses |
| `fragments/editor-mount.html` | the Toast UI and CKEditor drivers | the empty mount a vendor engine builds into, with the name attributes |
| `fragments/textarea.html` | TinyMCE, Toast UI for the field its engine fills, and every plain form field | a bare `<textarea>` |

- **The kit is printed once and cloned.** The runtime clones the capsule, the lists and the pane into a frame when it
  needs them and the two dialogs into the page on their first call, so a page of many editors carries one copy of each.
- **The textarea is the value.** CodeMirror mounts over the textarea of the frame and writes every change back into it;
  the textarea is hidden, never removed, so a page without script and a form submit see the same field.
- **Every word comes from PHP.** The titles of the capsule, the palette and the comparison are template variables of
  the kit, the phrases of the status line and of the CodeMirror panels its `words_json`; the script carries no text.
- **The preview frame is sandboxed.** The pane writes a page of the theme into an `<iframe sandbox="allow-same-origin">`
  by `srcdoc`, with the stylesheets of the page it stands on: no script of a text runs inside.

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
| `data-sl-spy` with `data-sl-spy-mark="<id>"` | the rail and each mark | marks the section under the observer band as current and colours the road behind it; the bottom of the document reads as the last mark, because a short final section never rises into the band. `data-sl-spy="px"` is the mode of the presentation rail: `--sl-d-spy` then carries the centre of the current mark in pixels instead of a percentage, the mark is scrolled into the visible part of the rail, and the rail height is published as `--sl-d-rail` on the root so every section clears the sticky dock |
| `data-sl-meter` with `data-sl-meter-fill`, `data-sl-meter-num`, `data-sl-meter-left` | the ring, the lamp and each counted control | recomputes profile completeness as the member types; `data-sl-meter-fill` carries the value that counts as empty, so one rule serves a text field and a select whose zero is a real option |
| `data-sl-dirty` with `data-sl-clean` | the shared form and the discard button | raises the save bar on the first change and reverts on discard. The hidden state is armed by the script and never by the markup: a page whose JavaScript never ran keeps a bar that is simply always there, instead of a form whose only submit can no longer be made to appear |

## Admin List Contract

A list of the panel is `getTplAdminList()` of `core/helpers.php` on `partials/admin-list.html` of the theme `admin`.
The module hands the meaning of each row — its name, its moment, its details, its edit, its delete, its status — and
the canon builds the markup from it: the relative date, the "i", the key E, the boxes, the foot "Отмечено: N" with its
action and "Отметить все N". No list of the canon glues a `popover`, a `stamp`, a `data-sl-edit`, an `hx-boost` or
the box of a row pick into a cell of its own. The etalon is "Комментарии" (`admin/modules/comments.php`), the shortest full example
"Избранное" (`admin/modules/favorites.php`). "Группы" (the ladder), "Блоки", "Категории" and the file manager are
components of their own and stand outside this contract.

| Key | On | The module hands | The canon builds |
|---|---|---|---|
| `name` | the row | the name of the row as a reader says it | `data-sl-row`, the label of its box, the label of its "i", the name in the toast |
| `moment` | a cell | a `DATETIME` string or a Unix time | `<time datetime data-sl-ago>` with the text in `_TIMESTRING` and the sort key; an empty or zero moment keeps the `content_text` of its module, else the cell is empty |
| `details` | the row | the items of `fragments/popover.html` | the "i" in the first cell past `is_col_id`, `tip_id` `sl-tip-<row number>`, labelled `_DETAILS: <name>` |
| `dial` | the actions cell | the items of `fragments/dial.html` | the dial titled by its column, with the item of `drop` appended last |
| `is_edit` | a dial item | the pencil of the row | `data-sl-edit`, which the key E clicks |
| `is_find` | a dial item | the address of a filter by the value of the row | `hx-boost="true"`, the list swaps in place |
| `drop` | the row | `getTplPostAction()` of the delete, `id[]` holding the key of the row | the dial item with `later` `gone del`, the box `id[]` of the row and the action `del` of the foot; `later` `gone move` makes it the action `move` titled by the item |
| `stat` | a cell | the status action with `later` `gone …` or `lamp …` and `is_on` | the lamp; beside a `drop`, the actions `on` and `off` of the foot from the fields of a row turned off and on |
| `upto` | the list | the greatest key of the list under its condition | the offer "Отметить все N" when the list has more than one page |
| `none_text`, `is_calm` | the list | the text of the empty list, and whether empty is the calm state of a queue | `sl-list-none`, with `sl-list-calm` |

- **One row has a box only by its `drop`.** A list whose rows carry no `drop` has no box column and no foot. The key in
  `id[]` is the key of the row in its own list: an id, the name of a scheduler job, the file of a journal, the entry
  of a ban (`cidr|hash|time`, `nick|time`), the row of `_user_oauth`. A field that changes from row to row — the type
  and version of a Node material, the provider of an OAuth link — is not put into `drop`: the handler reads it itself.
- **The foot is one form and sends one group.** `sl-list-pick` holds a `<fieldset data-sl-pick>` per action (`on`,
  `off`, `del`, `move`) with the hidden fields of the first row that offers it, `id[]` taken off: op, direction, the
  keys of the filter and the token. The boxes stand in the table and join the form by `form="sl-list-pick"`. The select
  `pick` enables its own group and disables the others, so the browser posts that group alone; without a script the
  first action is sent. The form carries `data-sl-later`: the rows leave or the lamps turn at once, Undo stands for ten
  seconds, then the form goes with `later=1`, which `setRedirect()` answers 204 or 409 alone.
- **A handler of the foot takes `id[]` and refuses whole.** It reads `id[]` by the type of the key of its rows — `num`
  for ids, `var` for the files of a journal, `raw` for a ban entry, the job names of "Планировщик" filtered to their
  letters — so one id is a list of one; it checks the token once for all of them and returns to its filter and its
  page. A handler that serves "Отметить все N" holds its row action once as a closure, for the picked rows and for the
  portions alike.
- **"Отметить все N" is the handler posted with `upto`.** A whole page picked offers the whole list; taken, its rows
  leave at once and Undo stands for ten seconds, then `admin-ui.js` posts the group of the foot without `id[]`, with
  `later=1`, `after` and `upto`. `setListPortion($key, $from, $where, $pars, $act, $limit = 100)` runs first in the
  handler: without `upto` it answers `false` and the handler goes on with `id[]`; with it, it passes the next portion of
  ascending keys above `after` and up to `upto` under the same condition as the list to the closure, writes one record
  of `setAdminLog()` and answers `{done, fail, after, more}` as JSON. The script follows the cursor with a progress line
  and a pause until `more` is false, and warns when a portion failed or was refused. Rows added after the page was drawn lie above
  `upto` and are not touched.
- **The filter is one condition.** The list, its count, the address of the pager, the foot and its portions read one
  condition of the filter, never a copy of it. "Комментарии" and the six growing lists — "Избранное", the private
  messages, "Сообщения", "Голосования", the campaigns and the undeliverable addresses of "Рассылка" — have
  `get<X>Filter($src)` in their module: the state of the request and the query `base` that keeps it, with the `from`,
  the `where` and its `pars` where the module owns the query; the foot reads it with `'post'`. `get<X>Search($flt)`
  draws `fragments/list-filter.html` into the `subtitle_html` of the tabs. Where a class owns the query, it answers the
  condition itself: `Comment::getAdminScope()`, `Privat::getAdminScope()`, `Mail::getQueueScope()`,
  `Mail::getDeadScope()`, `NodeQuery::getNodeScope()`; the three lists of "Пользователи" read `getAccountScope()` of the
  account admin, "Поиск" `getSearchwhere()`. The highlight of the found text is `filterTextHighlight()` on the column
  searched.
- **Empty says why.** An empty list under a condition of its filter says `_NONE_FOUND`, without one `_NO_INFO` (the
  default of `none_text`). The queues of `getAdminQueues()` — comments waiting, new accounts, Node "Ждут проверки", the
  default support queue — say `_ADM_CLEAN` with `is_calm`; the mail queue is a delivery journal and is not one of them.
  A text with a meaning of its own stays: `_NODE_NOTYPES` for Node without types, `_ERROR` for "База данных".
- **What stays single.** A delete under a question — TRUNCATE and DROP of "База данных", an administrator, a Node type,
  "Рефереры — удалить всё", the tables of a module — has no box, as has "Аннулировать" of a rating vote, which asks for a
  reason, and a campaign with mail in its queue. Short service lists — "Языки", "База данных", "Планировщик",
  "Администраторы", the Node types, the journals of "Безопасность" — have no filter bar.

**The frame swaps itself.** `partials/admin-list.html` is `#sl-list` with `hx-target="this"`,
`hx-swap="outerHTML show:none"`, `hx-history-elt` and the header `X-SL-Part`; on that header `setFoot()` answers the
`<title>` and the content of the module alone, so a module writes no code for a partial answer, and a page that is not
the panel layout (the login after a lost session) answers `HX-Refresh`. Back redraws a restored list through
`htmx:historyRestore` in `slaed.js`. The module passes the rest of the frame:

| Key | Carries |
|---|---|
| `head`, `rows` | the columns and the rows by meaning; the canon writes the name of each column into `label` of its cells, which the cards read on a phone |
| `href` | the address of a page without `num`, ending in `&`; both pagers and the rows chip append to it |
| `count`, `page`, `pages`, `maxpg` | the rows under the filter and the pager window |
| `limit`, `base` | the rows of this page from `getListLimit()` and the list's own number; `base` brings the rows chip |
| `key` | the key the sort is kept under, the module name by default; the second list of a module names its own (`security-logins`, `newsletter-dead`) |
| `head_html`, `note_html`, `foot_html` | the tabs with the filter bar, a note above the table, a form under it that has to live inside `#sl-list` (the ban lists) |
| `pane` | the window round the list: the keys of `pane-cap.html` and `tiles_html` above the table (the tables of "База данных") |
| `is_fixed` | the fixed table layout |

- **The filter is a GET form.** `fragments/list-filter.html` draws labelled fields with no `onchange`; the tabs that
  change the list in place are the indexes in `part` of `getTplAdminTabs()`. The filter stays in the address with `num`,
  and both pagers stand whenever there is more than one page, the filter applied by the server. Without a pager the key
  tip "i" (`fragments/pager-keys.html`) stands alone above and below the table.
- **The view is one for every list.** The switch `sl-seg sl-seg-view` writes the cookie `density` through op `option`:
  `standard` (no cookie), `full`, `compact`, `cards`; `getTplAdminList()` writes `data-sl-density` on the table from the
  first byte and the script changes the attribute at once. The sort is kept per `key` by `getToggleState()` and said on
  the line `data-sl-said` of `layouts/admin.html`, which also says the range after every swap; the rows that a filter, a
  tab or a page brings enter one after another by `--sl-d-order`.
- **A failed swap says so.** The alert `data-sl-list-error` with "Повторить" (`_ADM_LISTERR`, `_RETRY`) replaces a list
  that did not come, and the list carries `aria-busy` while it is asked; "Повторить" repeats the same request.
- **A deferred action names its kind.** `data-sl-later` on a form of the row, the dial or the foot: `gone <what>` takes
  the rows away (`del`, `move`, or the foot's `del=del on=on …`), `lamp <field>` turns the lamp in place, `flip on|off`
  turns a section of the panel menu. The action happens on the page at once, the toast offers Undo for ten seconds,
  then the form leaves with `later=1` by `fetch`, or by `navigator.sendBeacon` when the page is left; after the last
  deferred action the list redraws itself in place, so the pager and the counts are true.

**Rows per page.** One number of rows for every list of the panel, chosen on the list itself. The cookie
`$conf['user_c'].'-rows'` goes through op `option` (key `rows`): `10`, `25`, `50`, `100`, `250`, `all` or an own integer
5–1000; anything else deletes it, and without it a list keeps its own number.
`getListLimit(int $base, int|Closure $total): int` in `core/helpers.php` is the one source: it answers `$base` outside
the panel, the chosen number inside it, and `LIST_MAX` (1000, `core/system.php`) for "all" only while the filter holds
at most `LIST_MAX` rows — `$total` is the count or a closure called only then. In the panel the readers of Node
(`getPageSize()` of `NodeQuery`), the support queue (`getNodeSupportList()`) and the rating votes
(`Rating::getRatingList()`) take up to `LIST_MAX` rows a page; the site stays at `limits.maxlist` and the `list.limit`
of a type.

- **The chip is the range of the page line.** `fragments/pager-rows.html` of the theme `admin` draws "Показаны 1–25"
  as a chip in the colour of the pager; its panel is the system `sl-float` of the key tip, open on hover and focus, the
  lower one opening upward by `data-sl-float-up`. The rows are 10, 25, 50, 100, 250 with the pages each would give, "all"
  with the count (off above `LIST_MAX`), an own number in a field, and "Вернуть умолчание · N"; the list's own number is
  marked by a dot. A list of one page keeps its pager for the chip. The lite twin of the fragment prints the range text
  alone, since `PagerKeysTest` holds `pager.html` byte-identical in both themes.
- **The reader stays on the same row.** After a choice the page is counted from the first row under the table head,
  `#sl-list` is swapped by htmx with the new `num`, the script puts that row back where it stood, and the toast says the
  new number with Undo.

**The keys of a list live in `admin-ui.js`.** J and K walk the rows and outline the row in hand (`data-sl-cursor`), X
clicks its box and E its `data-sl-edit`; "/" puts the focus into the search field of the filter bar.

`AdminListRowTest` holds every key with and without it, the composition of the comment row, that no list function
glues `popover`, `stamp`, `data-sl-edit` or `hx-boost`, that every edit item carries `is_edit`, and that every list
with a filter bar passes `_NONE_FOUND` and the four queues `_ADM_CLEAN` with `is_calm`. `ListPickTest` posts every
foot handler through `tests/Support/pick_probe.php` with `id[]` and with `upto`, with and without the token;
`ListPortionTest` holds the portions of every list against its count.

## Admin Form Contract

Every POST form of the panel that `partials/form.html` draws, and the group form, carries `data-sl-dirty` and
`data-sl-leave`. `setDirtyForms()` in `slaed.js` measures a change against the values the form loaded with — a field
put back is clean again — and a form written with `data-sl-dirty="1"` (the group form after a refusal of the server)
starts changed. Before a link, the submit of another form or the window takes the page, `setConfirmTask()` asks the one
text `_ADM_LEAVE`; the window itself is asked by the browser. The boxes of rows, "Отметить все" and the action of a list
foot are no change; GET filters, htmx forms and forms inside windows never ask.

| Key | On | Carries |
|---|---|---|
| `is_valid` | `partials/form.html` | `novalidate` and `data-sl-valid`: a field is checked after the first attempt and when it is left, and the summary `sl-form-sum` (`data-sl-valid-sum`, the texts `_ADM_UNSAVED`, `_ADM_FILL`, `_ADM_BADVAL`) links into the field even on a hidden tab, whose `sl-tab-dot` (`_ADM_EDITED`) marks a change. Today only "Конфигурации" passes it |
| `find` | `partials/module-head.html` through `getTplAdminTabs()` | "Найти настройку" (`_ADM_SETFIND`): the combobox `sl-form-find` with its list `sl-form-hits` and `sl-form-none`, "/" focuses it, and Ctrl+K gets the group `_CHANGE` with every setting of the page (`data-sl-pal-sets`) |

A row that another choice of the form hides (the SMTP fields under the PHP transport) is offered neither by the find,
nor by the palette, nor by the check. The mechanism is shared in `admin-ui.js`; a form takes it by its keys, not by
code of its own.

## Alert Action Slot

An alert that carries a button carries it in one slot: the fragment `alert` of both themes takes `acts_html` and draws
it in `<div class="sl-alert-acts">` after `sl-alert-body`; without the key the alert is the plain one. The face is
"Чип": a pill of `--sl-size-chip` with the icon in a circle of the alert's tone; the quiet action
(`sl-alert-act-quiet`) has no frame, the dangerous one (`sl-alert-act-danger`) a circle of `--sl-danger`. An alert with
the slot is a container of inline size: the slot holds the end of the line and, in an alert narrower than 480 px, goes
under the text to the right — the width of the alert decides, not that of the window. The tokens are the component
`alert-act` of `tools/ui-contract.php`.

- **A module names the look, never the class.** `getTplPostButton($hide, $icon, $label, $attr, $look)` takes
  `$look` `'act'` or `'danger'`; `post-button.html` turns the flags `is_act` and `is_danger` into its classes. No module
  glues `getTplPostButton()` to the text of an alert — `TemplateValidationTest::testAlertActionsStandInTheirSlot` reads
  `admin/modules/*.php` for it, `ViewBridgeSmokeTest::viewRendersAlertActionSlot` renders the fragment with and without
  the slot.
- **Where it stands.** "Сортировка" of "Категории" (`_CATDRAGSORT`) and "Блоки", "Исправить счётчики" of
  "Комментарии", "Оптимизация" and "Ремонт" of "База данных", "Отметить все N" with the progress line `sl-alert-meter` (`--sl-d-run`), "Повторить" of a list that
  did not come, and "Отменить" of the toast of the panel. The toast of the site is a pill with `sl-toast-undo`, not an
  alert.

## Panel Shell

The shell of the panel is drawn by the theme `admin` from data PHP passes; what the site shares (the floating menu, the
admin block, the watch window) stands in each theme as a file of its own. The runtime behind it — the cookies, the
queues, the session, the cockpit, the menu, the voices — is `docs/ARCHITECTURE.md`, "Admin Shell Boundary".

| File | Theme | Drawn by | Holds |
|---|---|---|---|
| `layouts/admin.html` | admin | `setFoot()` | the skip link, the logo without `h1`, the phone drawer of the menu, the line `data-sl-said`, the window `#sl-session`, the badge of the screensaver, the root attributes `data-sl-still`, `data-sl-rail`, `data-sl-dock-off` from the first byte |
| `fragments/bell.html` | admin | `getAdminLayoutVars()` | the bell with the queues of `getAdminQueues()` and the address of their poll |
| `fragments/palette.html` | admin | the layout | Ctrl+K: the actions, the sections gathered from the menu or the cards, the settings of the page |
| `fragments/toast.html` | both | `getWindowSet()` | the one notice: in admin a flash alert with a ring of `--sl-d-toast` and "Отменить" in the slot, in lite a pill |
| `partials/cockpit.html`, `fragments/live-chip.html` | admin | `getAdminPanel()` | the four rings, the lamp with the fourteen signal rows, the strip and the live chip of the home |
| `fragments/admin-block.html`, `partials/session-summary.html` | both | `adminblock()` | the block "Администрация": the summary ring, the keys, the own links |
| `partials/session-stats.html` | both | `getSessionStats()` | the body of the watch window `#sl-stats` |
| `fragments/dock.html` | both | `getAdminDock()` | the floating menu: the home, "Эта страница в панели" on the site, the pins, the queue, "Все разделы", the watch ring, the card of the administrator, the own links, the tools |

- **The menu of the panel.** `getAdminMenu()` marks the current section with `aria-current`, carries the key of its
  module and the chip `sl-queue` of its queue; the filter `sl-menu-find` stands in `sl-menu-top`. The dial `sl-dial`
  closed is one Tab stop, its items reachable once it opens; "…" shows on hover and focus.
- **The tone of a column block is its role.** `partials/block-sidebar.html` writes `sl-block-menu` for the menu and
  `sl-block-queue` for "Ждут внимания" (`is_queue`); every other block is green. No rule reads the number of a block.
- **The styles of the block, the menu and the window** are `assets/css/dock.css` of each theme, the screensaver
  `assets/css/phoenix.css` and `assets/js/phoenix.js` of the theme `admin`; each file is listed under `themes.*.css` of
  `tools/ui-contract.php` with its components `dock`, `adm`, `stats`, `phx`; the rings of the cockpit stand on the
  component `gauge`.

### Motion

Each effect of the panel lives once: its moving rules in the block "Motion over the page" of `assets/css/theme.css`, its
behaviour in `assets/js/admin-ui.js`, its tokens in the API block of `base.css` and in `tools/ui-contract.php`. The
switch is "One motion switch" of `docs/ARCHITECTURE.md`; the option "Движение" writes `data-sl-still` through
`setOptionView()`, and the help of the home, `admin/info/panel/ru.md`, names the motion. The demo series
`public/demo/st-02-cockpit.html` and `public/demo/adm-07-effects.html` stay on the stand.

| Effect | Where | How |
|---|---|---|
| A light round the frame under the hand, blue into violet | `.sl-cockpit`, `.sl-mcard`, `.sl-pane` | `::before`, a stroke of `--sl-aurora-width` along `offset-path`; `sl-aurora` over `--sl-aurora-dur` carries its opacity, so a still panel shows none |
| The scan, a line of light going down the panel now and then | `.sl-cockpit` | `::after`, 1 px under the glow of `--sl-tint-strong` and `--sl-tint`, `sl-cockpit-scan`, at most `--sl-fade-disabled` |
| The comet round the lamp, in every state and the one beam there | `.sl-cockpit-plate` | `::before`, `sl-cockpit-spin`; the rules of the cockpit set its pace by the state, a multiple of `--sl-alert-dur` |
| The glow of a circle, at the share of the gauges of the presentation | `.sl-cockpit-ring .sl-knob-val`, `.sl-cockpit-plate` | `--sl-knob-mix`, not the shared `--sl-ring-mix`; it does not move, so it stands with the rules of the cockpit, outside the block |
| An arc fills to its share | `.sl-cockpit-ring .sl-knob-val` | `stroke-dasharray` over `--sl-knob-dur` on `--sl-alert-ease`; `partials/cockpit.html` starts it at `0 100`, so the first poll fills it from zero |
| A figure runs from its value to the new one and lights | `[data-sl-gauge-val]`, `[data-sl-strip-val]`, `[data-sl-queue-n]` | `setFigureRun()` in 900 ms, called by `setCockpitView()` and `setQueueView()`; `slFigure` keeps the goal, which `getFigureValue()` answers; `setFigureFresh()` is the one flare, `sl-figure-flash` over `--sl-knob-dur`; a still panel or a hidden tab writes the value at once |
| The tiles rise in their turn when the grid enters the view | `.sl-menu-grid` | `setArriveMarks()` at start and on `htmx:load` sets `data-sl-arrive` and `--sl-d-turn`; `sl-is-in` starts `sl-tile-rise` over `--sl-tile-dur`, and `setArriveDone()` drops the marks once every shown tile rose |
| A tile leans to the hand under a glare | `.sl-menu-grid > .sl-mcard` | one `pointermove`, not of a touch, written once a frame by `setTileTilt()`: `--sl-d-tilt-x`, `--sl-d-tilt-y`, `--sl-d-x`, `--sl-d-y`; a tile whose dial is hovered or open takes `transform: none` |
| A press spreads from the pointer | `.sl-mcard-link` of the home grid, `.sl-menu-list-item` of `.sl-admin-sidebar`, `.sl-block-sidebar-count-row`, `.sl-cockpit button` | one `pointerdown` appends `span[data-sl-ripple]` with `--sl-d-x`, `--sl-d-y`, removed when `sl-ripple` over `--sl-ripple-dur` ends; not from the dial or the drag handle |
| A chart is drawn left to right once | `.sl-chart-svg` | `data-sl-arrive`, then `sl-is-in` starts `sl-chart-draw` over `--sl-graph-dur`; a waiting chart is clipped to nothing, which the observer reads as unseen, so its parent is watched; a chart whose place (the nearest `id`) was drawn is a tick of the poll and gets no mark |

The laws of a move:

- **Duration.** A duration in the CSS of these effects is a component token `-dur` or a multiple of `--sl-alert-dur`,
  each carrying `--sl-time-scale`. The run of a figure, 900 ms, is the script's; the scripts ask `isMotionStill()`, so a
  still panel gets no arrival, tilt, ripple or run.
- **The block.** A new effect goes into "Motion over the page" and carries no reduced-motion block of its own. The block
  does not hold every animation of the theme: the knob of the segmented switch and the installer keep blocks of their
  own, and the other animations stand outside any block and stop by `--sl-time-scale` alone. That holds only where the
  end of a move hides it, so an effect that would stand visible, as the aurora under the hand, starts at opacity 0 and
  takes its light from its keyframes.
- **Colour and tokens.** Colours are tokens only; a new token is declared in the API block of `base.css` and in
  `tools/ui-contract.php`, the component under `components`, the prop under `props`.
- **No transform over a float.** `slaed.js` places a panel of `.sl-float` as `position: fixed` and `refitFloating()`
  follows the scroll; a `transform` on an ancestor becomes the frame of `fixed`, and the panel opens off its place. So
  the arrival moves by the offset `top`, a part animates its pseudo-element, and the tilt holds only a tile, whose
  dial is a fan inside it, no float.
- **A tick never replays.** A chart is drawn once; a later poll updates it without drawing it again.
- **The ripple stays on the home and the menu.** The forms and the lists of the panel take none.

## Node List Contract

The list of a Node type is one part that swaps itself: `partials/node/list.html` of `lite` is `#sl-node-list`, the
header `partials/node/head.html` inside it, the cards in `#sl-node-items`, the pager and "Показать ещё" in
`#sl-node-foot`. What the server reads and answers — the parameters, the counts, the views, the `HX-Request` answer —
is in `docs/NODE.md`, "List header", "Views of a list" and "Live list"; the keys of the three templates are in its
"Template keys". Every control is a link or a form the server answers, so the list works without a script.

- **The part is its own target and history.** The wrapper carries `hx-target="this"`, `hx-swap="outerHTML show:none"`,
  `hx-history-elt` and `hx-history="false"`, and the regions inside inherit them. `hx-boost="true"` stands on the
  filter toolbar, the three windows and the foot, not on single links, so the shared `link.html` and `pager-link.html`
  stay as they are; "Добавить" opts out by `hx-boost="false"`.
- **"Показать ещё" appends and redraws.** `#sl-node-more` overrides the target with `hx-target="#sl-node-items"`,
  `hx-swap="beforeend show:none"`, `hx-select="#sl-node-items > *"` and `hx-select-oob="#sl-node-foot"`.
- **Shared fragments.** `fragments/date-head.html` (`sl-date-head`, keys `label` and `count`) heads a month of the list
  and a day of the mailbox. `fragments/mode-switch.html` draws the colour mode and, with `is_view`, the view switch of
  the list: one POST form whose cells are submit buttons with `aria-pressed`, `hx-post` to its own `action` only for
  the view, on the segmented switch `sl-seg` with `data-sl-knob`. The rail is `sl-duo` with `sl-duo-aside` and
  `sl-duo-main`, the split the private messages stand on, with the tabs of `slaed.js` (`data-sl-tabs-init`,
  `data-sl-tab-link`, `data-sl-tab-panel`). `partials/node/hints.html` prints only the options; the list box around
  them stands in the header.

**The header behaviours live in `plugins/system/slaed.js` as attributes with no Node knowledge.**

| Attribute | On | Does |
|---|---|---|
| `data-sl-toggle-group="<name>"` | the `data-sl-toggle` blocks of the windows | `setToggleGroup()` closes the open blocks of the same name, with the motion of their own control, before one opens. The windows carry `data-sl-toggle-scope="none"`, so nothing is remembered across a reload. A block takes its default once and is marked as its control is, so a later htmx swap elsewhere on the page — a poll of the session block — keeps the window in the state it stands in |
| `data-sl-sticky`, optional `[data-sl-sticky-mark]` inside | the header | `setStickyBars()` sets `data-sl-stuck` while the mark is above the top line of the bar; the mark is its own or a zero-height one put right before it, and the observer band reaches far below the screen, so a fling or a reload at a scrolled place is not missed. Leaving the stuck state closes an open lens and keeps the word |
| `data-sl-lens="<field id>"` with `aria-expanded` | a button inside the sticky block | the block carries `data-sl-lens-open` while the field is shown over it and focused; the lens or Esc in the field closes it, empties the field with an `input` event and returns the focus to the lens |
| `data-sl-hints="<list id>"` | the search field, `role="combobox"` with `aria-controls` and `aria-expanded` | a swap into the list that brings options opens it while the field has the focus and gives each option an id; the arrows walk them round by `aria-activedescendant` and `aria-selected`, Enter opens the chosen one or, with none chosen, submits the form, Esc closes the list before the lens hears it, and a press on the list keeps the focus in the field, so a browser that focuses no link on a click still opens it. The list closes when the focus leaves both and opens again when the focus returns to a field that holds a word |
| `data-sl-swap-reveal` | the part | after a swap whose head went above the screen the part jumps back to it, past the smooth scrolling of the sheet; a part in sight stays |

- **The sheet reads the attributes, not classes the script writes.** `.sl-node-head[data-sl-stuck]` hides the count chip
  and the view switch, shows "Наверх" and keeps the pills on one scrolled row; the lens appears through `@container`
  on the bar while the stuck bar is narrower than 900 px, since beside the sidebar the list column is that narrow on
  every screen. `sl-node-list` carries `overflow-anchor: none`: the stuck bar is lower than the free one, and anchoring
  the list made the page jump back by the difference and flip the bar between its two states.
- **The hints share a look rather than a component.** `sl-node-hints` and `sl-node-hint` take the pointed and the chosen
  state of `sl-editor-hint-row`; no shared hint list stands in the tree.

## Segmented Switch

Every row of cells with one chosen — the colour mode of the site header, the view of a Node list, the numbers of the
pager — is one component, `sl-seg`, in the `theme.css` of both themes; the face it was drawn from is the stand
`public/demo/assets/demo.css`.

| Part | Is |
|---|---|
| `sl-seg` | the sunken track, an inline flex row with the plate under its cells |
| `sl-seg-cell` | a cell: a link, a button or a `span`; `sl-is-active` marks the chosen one, `sl-is-muted` one that is no choice |
| `sl-seg-knob` | the plate, an empty `span` with `data-sl-knob-mark` and `aria-hidden="true"`; it carries no copy of the cell content, the chosen cell recolours its own over it |
| `sl-seg-run` | a run of cells printed by another fragment, laid straight into the track by `display: contents` |

- **A modifier sets the tokens of its place and nothing else.** `sl-seg-mode` — the gradient of the top band, light
  icons, only in lite, since the panel has no mode switch; `sl-seg-view` — a quiet track; `sl-seg-pager` — mini cells,
  the plate set inside the track by `--sl-space-1`, the digits small and bold. A setting (the mode, the view) wears a
  neutral light plate, a place (the current page) the brand colour.
- **The tokens are API.** `--sl-seg-height`, `--sl-seg-pad-x`, `--sl-seg-pad-y`, `--sl-seg-bg`, `--sl-seg-text`,
  `--sl-seg-knob-bg`, `--sl-seg-knob-text`, `--sl-seg-knob-shadow` stand in the API block of each `base.css` as the face
  of the plain `sl-seg`; a modifier reads semantic tokens straight instead of redeclaring them, because a custom property
  declared in `theme.css` counts as scoped. `--sl-mode-width`, `--sl-mode-height` and `--sl-mode-bg` stay the cell, the
  plate and the track of `sl-seg-mode`.

**The plate travels.** One mechanism in `plugins/system/slaed.js`, `setKnobs()`, run at start and on `htmx:afterSwap`
for the swapped part:

| Attribute | On | Carries |
|---|---|---|
| `data-sl-knob="<name>"` | the track | the name of the rail — `mode`, `view`, `pager` — under which its place is handed on |
| `data-sl-knob-mark` | the plate | what the sheet moves |
| `data-sl-knob-ready` | the track, written by the script | the plate stands on the measured place; without it the plate is hidden and the chosen cell wears the plate itself, so a page without script still shows the choice |
| `data-sl-knob-still` | the track, written by the script | the plate stands on a handed place for one frame without a transition, and the observers leave it there |
| `--sl-d-knob-x`, `--sl-d-knob-w` | the track, written by the script | the left and the width of the chosen cell inside the track |

- **The script measures, the sheet moves.** `setKnobPlace()` measures the first visible `sl-is-active` of the rail; a
  `ResizeObserver` and a `MutationObserver` on the class follow a change of the chosen cell whoever makes it. The sheet
  moves the plate with `--sl-time-slow` and `--sl-ease-in-out`, and not at all under `prefers-reduced-motion`.
- **A page hands its places to the next.** On `pagehide` and `htmx:beforeSwap` the place of the first rail of every name
  goes into `sessionStorage` (`slaed-knob:place`); the next rail of that name takes it once, only on the same path and
  within five seconds, stands still on it and travels from the next frame. A link, the POST of the mode form and an htmx
  swap alike look like one plate moving. `tests/Unit/SegKnobTest.php` holds the mechanism.

## Pager

`getTplPagerView()` in `core/helpers.php` is the one renderer of both themes: a window of `$maxpg` numbers round the
current page, the first and the last outside it, the dots where numbers are skipped. Callers reach it through
`getTplPager()` (counts by a query or takes `count`) and `getPageNumbers()` (known counters); the private messages of
the site and the panel, the Node lists of the site and the panel, the panel comments and the newsletter call it
directly with a link builder of their own, the private messages with their htmx target. Which numbers the window selects is the callers'
business; the face is the stand `public/demo/pg-09-final.html`, in the panel `pg-14-admin.html`.

| Fragment | Prints |
|---|---|
| `fragments/pager.html` | the `nav` labelled `_PAGENAV`: the row `sl-pager-main` with the arrows and the rail in `sl-pager-nav`, the side group `sl-pager-side` with "Наверх" on `#top` and the key tip, then the line `sl-pager-info` |
| `fragments/pager-link.html` | one cell: a number (`sl-seg-cell`), an arrow (`sl-but-mini`) or the dots; a link by `href`, an htmx link by `query` with `target_id` and `push_url`, a `span` for the current page and for an arrow at the end of the run |

Both fragments are byte-identical in `templates/admin` and `templates/lite`; only each theme's sheet differs.

| Key | To | Carries |
|---|---|---|
| `is_cur` | `pager-link` | the current page, printed as `sl-is-active` with `aria-current="page"` |
| `is_far`, `is_near` | `pager-link` | a number more than one away from the current page, or its neighbour; never the first or the last. The fragment prints `sl-pager-far` and `sl-pager-near` |
| `is_nav`, `rel`, `icon_name`, `key_name` | `pager-link` | an arrow: `rel="prev"` or `rel="next"`, its chevron, and `aria-keyshortcuts` with `ArrowLeft` or `ArrowRight` |
| `is_dots` | `pager-link` | the muted cell `&hellip;` |
| `page_label`, `page_of`, `overall`, `count` | `pager` | "Страница 3 из 10" and "Всего 97" |
| `range_from`, `range_to`, `range_text` | `pager` | "Показаны 21–30", only when the caller names `limit` and `count` |

- **The rail is the segmented switch.** The numbers are the `sl-seg-run` of `sl-seg sl-seg-pager` with
  `data-sl-knob="pager"`, so the plate travels to the new page after a reload and after an htmx swap.
- **It narrows by its own column.** `.sl-pager` declares `container-type: inline-size`: under 520px of it the far
  numbers and the range part of the line go, under 320px the neighbours too, leaving `‹ 1 … 8 … 16 ›`. A container of
  inline size does not grow by its content, so a flex foot that holds the pager gives it a width — the panel module foot
  and the forum foot do; the other places hold it as a block. The site centres the row and puts the line under it; the panel starts the row
  at the left and pushes the line to the right, under the row in a narrow container; the panel list foot puts the bulk
  action under the pager below 560px.
- **The arrow keys page the list.** `setPagerKeys()` in `slaed.js` listens to ← and → without a modifier and clicks the
  back or forward link (`a[rel]`) of the first pager on show outside a window, so an htmx link swaps as it does for the
  pointer and two pagers of one topic lead to the same page. It stands aside when the key was handled already, when the
  focus is in a field, a `select`, a media element or an editable, on a tab or a tab list, inside a `<dialog>` — the
  file manager, the gallery, every window — and on the file desk of the panel (`.sl-fm-split`).
- **The "i" explains the keys.** The side group carries the system tip `sl-tip sl-float sl-pager-keys` labelled
  `_PAGEKEYS`, its panel `sl-pager-tip` with the heading, the two keys as `bi-arrow-left` and `bi-arrow-right` in `kbd`
  caps labelled `_KEYLEFT` and `_KEYRIGHT`, "Предыдущая страница" and "Следующая страница", and the note `_PAGEKEYNOTE`.
  The keys are a plain list, not a `dl`, which each theme lays out as label and value. A touch screen
  (`pointer: coarse`) has no arrow keys, so the "i" is hidden there; "Наверх" stays everywhere.
- **"Наверх" is a plain anchor.** Every link to the top — the pager, the stuck header of a Node list, the lower
  navigation of `search` and `users`, the panel footer — points at `#top`, which the browser takes for the start of the
  page, and the root of each theme scrolls smoothly unless the reader asked for less motion.
- `tests/Unit/PagerViewTest.php` holds the window, the marks and the range, `tests/Unit/PagerKeysTest.php` the keys and
  the tip.

## Rank

The rank of a group — the row of marks under an avatar in the forum, the comments, the profile, the user block and the
group table of the users statistics — is drawn from the string in `_groups.rank` with Bootstrap Icons, which both themes
carry as a required asset. No theme keeps rank pictures.

**The string.** `icon:lit:total:#rrggbb`, for example `star-fill:3:5:#8297a2`: M marks of one icon, the first N lit in
the colour of the rank, the rest muted; 0 ≤ N ≤ M, 1 ≤ M ≤ 10. The rank colour is its own, beside the group colour
`_groups.color` that paints the name and the ring of the avatar. Binding the rank to the group colour is not stored: the
panel form shows it bound while the two colours are equal.

| Function in `core/system.php` | Does |
|---|---|
| `getRankData(string $rank): array` | splits the string; the icon matches `^[a-z0-9-]+$`, the colour `^#[0-9a-f]{6}$`, the counts the bounds above. Anything else, a picture name included, gives an empty array |
| `getRankView(string $rank, string $title): string` | the `rank` fragment of the theme, or an empty string when the value is no rank. `$title` is the group line the reader already builds (`_GROUP.': '.$name` or `_RANK`) |

Every reader passes the raw column and its title, and every template takes the result as one `rank_html`.

- **One fragment in both themes.** `fragments/rank.html` prints `<span class="sl-rank" role="img">` with the title as
  `title` and accessible name and `--sl-d-rank` as the colour, then one `<i class="bi bi-<icon>" aria-hidden="true">`
  per mark, the lit ones `sl-is-active`. The files are the same in `templates/admin` and `templates/lite`; only the sheets
  differ.
- **The sheet.** The lit mark takes `--sl-d-rank`, the muted one `--sl-border-strong` — the same icon in a muted tone,
  not an outline variant, so every icon of the catalogue works. The marks stand at `--sl-space-1` and take the size of
  the place they are printed in by `.sl-rank .bi { font-size: inherit; }`, which the panel needs against its fixed `.bi`
  size and lite carries so both sheets read alike.
- **One contrast floor, 3:1 on both surfaces.** One stored colour serves the light and the dark theme, and no colour
  reaches 4.5:1 on both at once; 3:1 is the floor both meet (WCAG 1.4.11 for the marks). Every swatch of the panel
  passes it; a colour the administrator picks below it is warned about, never refused.
- `tests/Unit/RankDataTest.php` holds the string and the marks of the view.

## Group Ladder And Rank Composer

`admin/modules/groups.php`, tabs «Главная» and «Добавить»; the face is the stand `public/demo/grp-10-unified.html`.

| Template | Prints |
|---|---|
| `partials/group-ladder.html` | one list: the heading with its icon, subtitle and sort button, and the `ol.sl-ladder` of its steps; `sl-ladder-extra` dashes the rail of the special groups |
| `fragments/group-step.html` | one step `sl-ladder-step`: the threshold or the mark «Специальная», the node on the rail, the rank before the name, the description, «Участников: N» or «Пусто — участников нет» over the share bar, the dial |
| `partials/group-form.html` | the form `sl-rank-form` with its three numbered parts `sl-rank-part`: «Готовый ранг», «Свой ранг» (the console and the preview), «Группа» |

- **The list.** `groups()` counts the members of every group in one query with `GROUP BY` — a special group the users
  whose `grp` it is, a point group every user at or above its threshold — and prints the point groups by threshold and
  the special groups by members, most first. The members link goes to the user list of `account` filtered by it:
  `search=6&chng=<id>` for a special group, `search=7&chng=<threshold>` for a point group. A step names its sort keys in
  `data-sl-sort-*`, and the button `data-sl-ladder-sort` of its list reverses the steps in place in `admin-ui.js`;
  nothing is stored.
- **A painted name stays readable.** The step writes the group colour as `--sl-d-group`; the name reads `--sl-group-text`
  of the admin `base.css`, which keeps the hue of `currentColor` and clamps its lightness —
  `light-dark(oklch(from currentColor min(l, 0.5) c h), oklch(from currentColor max(l, 0.7) c h))` — so any stored
  colour holds 4.5:1 in both themes. The stored colour is untouched, and the contrast tiles of the form measure the name
  as it is painted.
- **The form opens filled.** `getRankPresets()` (eighteen presets), `getRankTones()` (sixteen rank swatches) and
  `getGroupTones()` (fourteen group swatches) are lists beside `add()`, each label a lang constant. For a new group
  `getGroupNext()` proposes the next step: threshold half again above the top step, rounded up to a thousand when the
  top step stands at a thousand or more and to 25 below, and at least one such step above it, the icon and rank colour of the top step with one more lit mark (one more mark too when all were
  lit, at most ten), the group colour that follows the top one in the palette; the alert `_GROUPS_NEXT` names the top
  step.
- **One handler.** `setRankForm()` in `admin-ui.js` on `[data-sl-rank-form]` runs the console, the swatch rows, the
  three preview sizes, the contrast tiles, the threshold hint and the leave guard. The tiles set `color-scheme: light`
  and `color-scheme: dark`, so each resolves its own surface from the `light-dark()` tokens and the ratio is computed
  against it. The binding to the group colour travels as the hidden `rank_sync`. The thresholds of the other steps
  arrive as `data-sl-rank-steps`, from which the hint says where the group will stand or that the threshold is taken.
- **Rows of choices are toolbars.** The presets, the forty icons and the swatch rows are `role="toolbar"` with
  `data-sl-roving`: one Tab stop on the chosen member, the arrows inside, up and down by a whole line, Home and End to
  the ends. `setRankView()` sets the stops when the form loads and after every change.
- **Leaving asks.** A changed form asks `_GROUPS_LEAVE` through `setConfirmTask()` of `slaed.js` on a tab or «Отмена»,
  and through `beforeunload` otherwise; the save is not guarded. A form returned with an error opens changed.
- **`save()` writes the joined string.** It reads `rank_icon`, `rank_lit`, `rank_total`, `rank_color` and `rank_sync`,
  clamps the counts, refuses an icon or colour `getRankData()` would refuse, a group colour that is no `#rrggbb`, and a
  threshold another point group has.
- **Tokens.** The admin `base.css` carries `--sl-group-text`, `--sl-ladder-width`, `--sl-ladder-ring`,
  `--sl-ladder-bar-height`, `--sl-ladder-bar-mix`, `--sl-wheel-bg` and `--sl-wheel-eye-width`; templates and the script
  write `--sl-d-group`, `--sl-d-share` and `--sl-d-swatch`, registered under `data` in `tools/ui-contract.php`.
- `tests/Unit/GroupFormTest.php` holds `save()` and `getGroupNext()`.

## Layout Sections

A site theme describes what the site builder places on the pages and around them; the layout keeps the values by key
and the theme prints what it declares, so a change of theme keeps every value (`docs/ARCHITECTURE.md`, "Layout
Boundary"). Everything but the fragments is data; a label is a constant of `admin/lang`, written with its leading `_`.

| File | Holds |
|---|---|
| `sections/<type>.json` | the description of a section type |
| `sections/<type>.html` | its fragment |
| `places.json` | the parts of the theme, its menus and its zones of blocks |
| `presets.json` | the ready pages |

A new theme copies all four. The demo series "Конструктор сайта" — `public/demo/sb-01-builder.html`, `sb-site.html`
and their `assets/sb-*` — stays the reference of the layout and the behaviour of the screen and of the canvas; its
sizes, spacing, fonts and styles are those of the canon of the panel, and where the two differ the demo is brought to
the canon.

### Roots and providers

`Layout::getSectionTypes()` scans `sections/` of the active theme, then `modules/<name>/sections/` of every module by
name. The first root that describes a type owns it; a type name is `[a-z][a-z0-9-]{0,39}`, and `module`, the slot
that holds the output of a module, belongs to the core and is never a description.

- **The fragment.** The theme's `sections/<type>.html` wins. A type of a module whose theme has none renders the
  module's own `sections/<type>.html` through `Template::getHtmlSection($type, $data, $dir)`, held inside that folder as
  a theme file is held inside its theme. A type with neither is skipped on the site and named in the editor.
- **The provider.** A module may bring `sections/<type>.php`, which returns a `Closure` that takes the section and
  answers values laid over those of its fields. A section on the data of a module is a type of that module, so a site
  without the module has no such type in the library.
- **Shipped.** Lite describes 17 types. The module `node` brings `feed` ("Свежие материалы", over `getNodeFeed()`, the
  engine of the block `node.php`) and `item` ("Материал", a record by `getNodeContent()` of its type); `presentation`
  brings `sites` ("Внедрения", over `getPresentationSites()`) with a default fragment. Lite draws `feed`, `item` and
  `sites` with fragments of its own.

### The description

| Key | Holds |
|---|---|
| `title`, `note` | the name and the line of its card in the library |
| `icon` | the Bootstrap icon of the card and of the head |
| `group` | the group of the library: `base`, `media`, `content`, `trust`, `contact` or `master`, shown in this order, each under its heading; an unknown group lands in `master` |
| `order` | the place of the card in its group, ascending; a type without it stands after the ordered ones, the module slot first of `content` |
| `frame` | the head of a presentation section with its number, `fragments/presentation-head.html` |
| `fields` | the fields of the section |
| `items` | an optional list: `label`, `max`, `name` (the field that names an item), `fields`, `make` |
| `make` | the defaults of a new section or item; a string with a leading `_` is a constant resolved when the editor creates it, and the site never reads `make` |

A field is `key` (`Layout::FIELD`, no underscore, so a derived key never meets a field), `label` and `type`, and
optionally `show`, `tab` (`look` for the tab "Вид"), `options` (key → label of a `select`), `min` and `max`, `when`
(`[key, value]`: the field shows and is checked only while another field holds that value), and `for` on a field `url`:
the key of the text of the button it leads from.

A button of a fragment prints only with both its text and its address, so the pair is closed on both sides: a `make`
carries no button text without its address, and the check before a publication names a filled text whose address is
empty ("Кнопка «…» никуда не ведёт"). Lite ties `href` to `btn` in `hero`, `cta`, `media` and the items of `prices`, and
`href2` to `btn2` in `hero`; `LayoutTest` holds that no default and no ready page breaks a pair.

| `type` | Edited as | The fragment gets |
|---|---|---|
| `text`, `textarea`, `email`, `date`, `url`, `int`, `bool`, `select` | a type of `Field`, checked by `checkFieldValues()`; `show` draws a `select` as `seg`, a `bool` as `switch`, an `int` as `range` | the value made canonical by `Field`; a `select` also `<key>_<option>`, a `url` outside the site `<key>_ext` |
| `markdown` | the content editor of the site in the editor window, with the file manager over `layout.attach` | `<key>_html` by `Parser::filterDoc()` with the module `layout` |
| `code` | "Свой HTML" in the code editor, for the super administrator | `<key>_html`, printed as it is |
| `file` | the file field over `layout.attach` | the address of a path below `uploads/`, in a public folder only |
| `focus` | the grid of nine | one of `Layout::FOCUS`, `center` by default |
| `icon`, `tone` | the icon window; the swatches of `Layout::TONES` | the name, or empty |
| `video` | an address of YouTube or a film of the uploads | `<key>_frame` (the privacy mode of YouTube), `<key>_file` |
| `page` | an address of `https` | `<key>_frame`, `<key>_host` |
| `point` | one field, the latitude and the longitude: "52.35, 8.23" | `<key>_frame`, the map of OpenStreetMap |
| `nodetype` | the check list of the Node types that may feed a block | the list of type names, empty for all |
| `noderef` | a search over the titles of the published records | the number of the record |
| `blockref` | the blocks of the site, the free ones first | the number, and `<key>_html`, the block by `Template::getFree()` |

Every fragment also gets `parts`, the values of the parts of the theme, and `is_build`; an item gets `order_num`, its
place as two digits, and `is_first`. A missing or refused value is empty, never an error on the site. The kinds are a
closed set of the core, so a new section needs no line of JavaScript; the kinds of the editor's own forms (`anchor`,
`slug`, `choice`, `checks`) are not for a description.

### The frame of a section

`fragments/section.html` stands around every section: `id` from its anchor (`Layout::ANCHOR`), `sl-sec-no-phone` and
`sl-sec-no-desk` for the devices it is hidden on, the head of `presentation-head.html` when the description has
`frame`, and `data-sl-pick` in the edit mode. The module slot is its output alone, and in the edit mode a `div` with
its pick. A theme without the fragment prints the sections bare.

An outside frame — a field `video`, `page` or `point` — waits in a `<template>` of `[data-sl-sec-load]`, and
`slaed.js` swaps it in on the click of its placeholder: no outside server is asked before that click and no consent
or cookie is stored. A film of the uploads plays at once.

### Places

`places.json` holds three maps; `Layout::getThemePlaces()` reads it and `getThemeData()` checks the values of `theme`
by its fields, as the values of a section.

- `parts`: each `title`, `note`, `icon`, `where` (the place the structure of the screen groups it by) and `fields` as
  in a description; optionally `items` with its `key`, the name of its list in `theme`, and `conf`, the keys of
  `config/global.php` the part edits as "Конфигурации" does. Lite declares `brand` (its `conf` is `sitename` and
  `site_logo`, its field `tagline`), `pane`, `social`, `line`, `contacts`, `marks` and `tabs`.
- `menus`: each `title`, `where`, `depth` and `icons`; lite declares `main` two levels deep and `foot` one.
- `zones`: the zones of blocks `l`, `r`, `c`, `d` with their `title` and `icon`, the Bootstrap icon of the zone in the
  tree of the screen (`layout-sidebar` without it); the tree sets them under one head "Блоки".

### Ready pages

`presets.json` maps a key to a ready page: `title`, `note`, `icon`, `band` and `sections`, each a `type` with an
optional `make` laid over the defaults of that type. The screen puts its own "Из текста" and "Пустая" first; a type the
theme and its modules do not describe is skipped when the page is made. Lite brings thirteen.

### Marks of the edit mode

Under `is_build` the fragments mark what the canvas of the builder picks and types (`plugins/system/builder.js`):

| Mark | On |
|---|---|
| `data-sl-pick` | a section and the slot (`sec:<id>`), a part of the theme (`part:<key>`), a menu item (`menu:<key>:<id>`), a block (`blk:<id>`) |
| `data-sl-text` | a text typed in place, by the key of its field; an item's as `items.{{ item.order_num }}.<key>` |
| `data-sl-lines` | beside `data-sl-text`, a text of several lines |
| `data-sl-md` | a Markdown text or "Свой HTML", which opens the editor window |

`fragments/builder.html` holds the template `[data-sl-build]` with the rings and the toolbar. A visitor gets no mark; a
theme without them keeps the rings and the inspector and loses only the typing in place. `LayoutTest` holds that each
`data-sl-text` names a field of its type.

The screen draws the page at the width of its device — 1366, 820 or 390 px — and scales it down to its stage; the
message `{sl: 'scale'}` makes `builder.js` write the inverse, at most 2, as `--sl-d-zoom` on the root of the page, which
the CSS of the names, the toolbar, the button "Добавить секцию" and the drag label takes as `zoom`, so they keep their
size (lite: `presentation.css`).

### The screen of the builder

The screen is written once on the theme `admin` (`partials/layout-*.html`, `fragments/layout-field.html`,
`assets/js/layout.js`, `assets/css/layout.css`); a site theme brings only its sections
and places. It is built of the canon of the panel — the parts of a window for the inspector, `div-row.html`,
`menu-list-item.html` for the tree, `menu-grid-item.html` for the cards, `toggle-form-block.html` for the items, the
file field, the capsule of the editors — and keeps own CSS only for the three columns, the frame of the canvas, the
schemas of the cards, the swatches, the rings and the toolbar on the page.

- **The tone of a button is its weight.** `sl-but-blue` the one main action of a window or the screen,
  `sl-but-green` the action of a field that opens its editor or picker, `sl-but-red` what removes or throws away,
  `sl-but` the way back and the secondary, `sl-but-mini sl-is-muted` the small tools of a part, their words in the
  title. Every button carries its icon.
- **One place for each kind of button.** What is done to the picked part stands only in the foot of the inspector, the
  small tools at the left, the deletion as a red icon at the right; the action of a field at the right end of its field;
  a new element at the end of its list; the foot of a window the dangerous action at the left, the way back and the main
  action at the right.
- **One capsule for every group of icons.** The top bar, the foot of the inspector and the toolbar on the page are
  `sl-editor-pill` with its groups, shown at once, a removing act in the tone of danger. Its icon action is round,
  muted and `--sl-size-chip` wide (`.sl-editor-pill .sl-modal-act`), while the same action in the head of a window and
  in the floating menu stays the square of `docs/WINDOW.md`: the capsule is a part of its own.
- **A switch says its answers.** "Да" and "Нет" answer a question asked in the positive; any other answer is a
  segmented control with its words.

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

### What Belongs In A Skin And What Belongs In The Theme

A skin dresses the vendor. A component that renders on pages where the vendor is
absent belongs in `theme.css`, because `Editor::getThemeSkin()` delivers a skin
only where an editor lives, and a rule left behind there is a component stripped
on every other page.

The file manager window is the worked example. Its rules moved from
`templates/{lite,admin}/assets/editors/toastui/skin.css` into
`templates/{lite,admin}/assets/css/theme.css`, values unchanged — a move, not a
rewrite. Five of them do not start with the window root: `.sl-shot-side .sl-fm-props`
and its four relatives dress the properties panel of the shot window, which
`getWindowShot()` builds from `core/helpers.php` and which is already system-wide;
they moved with the rest and kept their `.sl-shot-side` scope. `tools/ui-contract.php`
names them and was updated with them.

What stayed in the skin is the vendor's own: toolbar, icons, mode switch,
fullscreen, and `.sl-toastui-upload button` / `.sl-toastui-upload input`, which
cure the fixed button height the vendor root imposes on the two dialogs mounted
inside it. `sl-toastui-upload` therefore survives as a **second** class on the
window, beside its own root.

**The window could not simply be unscoped.** The administrative file browser in
`templates/admin/assets/css/theme.css` owns the same `sl-fm-*` names — it shares
`sl-fm-split`, `sl-fm-drop`, `sl-fm-queue`, `sl-fm-queue-cap`, `sl-fm-empty`,
`sl-fm-props` and `sl-fm-main`, the last a *button* class there. The window took a
root of its own, `sl-fm-win`, and stays scoped under it. The eight `--sl-fm-*`
tokens admin's `base.css` carries beyond lite's twenty — `bar-width`,
`edit-height`, `preview-height`, `search-width`, `sep-height`, `split-height`,
`thumb-height`, `thumb-width` — belong to that browser and not to the window.

The markup moved the same way and for the same reason:
`partials/editor-toastui-files.html` became `partials/file-manager.html`, still
byte-identical between the themes.

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
| `npm run ui:label` | the label crawl: every `for`, id, aria reference and field name on every rendered route, against `tools/label-audit-baseline.json` |
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

- **Nothing may touch the tree while the rig runs.** A capture takes about five minutes: the two modes of the manifest walk side by side, each in contexts of its own, because a context is what holds the mode cookie. A `git stash` during that window leaves a set half from one tree and half from another, with nothing in the output to say so.
- **Always compare two captures of your own.** The stand's own data moves between runs, so a `--check` against any set captured earlier than minutes ago reports the week rather than the change. `ui:before` and `ui:after` exist to make that pair the default path.
- **Re-capture only the states that moved.** `--capture --only=<page>` writes that page's images and merges its entries into `noise-floor.json`, leaving every other floor and image untouched. `--only` takes a comma list, `--only=settings,profile`, and serves every job: `--before --only=<pages>` replaces those pages in the pair and keeps the rest of it, `--after --only=<pages>` re-compares the pages a change touched in about a minute, one login for all of them.
- Before capturing, empty `storage/cache/data/*` and `storage/cache/templates/*`, and delete `config/local.php` after any hand edit of `config/*`.
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

Forward, if the attribute is there it must be right: every `for` resolves to a labelable element, no id repeats, no `<label>` nests, every `<label>` has a `for` or a labelable descendant, every aria IDREF resolves to an element with text. Reverse, the attribute must exist: every `.sl-radio-group` carries `role="group"` and a resolving `aria-labelledby`, every visible editable has a name, every form field has a name of its own (`control-unnamed`: a label, `aria-labelledby` or `aria-label`, where a `title` or a placeholder does not count; a field in a closed tab counts, a field whose own display is none does not), and every element carrying a `-hint` id is referenced by an `aria-describedby` in its own row, unless the slot holds only a button of the row.

- **Not part of `npm run ui:gates`**, deliberately: the gates run from `tools/hooks/pre-commit` on every commit and this needs an HTTPS stand, a browser and administrator credentials. The hook prints a reminder instead. Moving it into the gates means changing the hook in the same commit.
- **It walks frames, not the main document.** TinyMCE puts its editable body in a separate document; a main-frame query sees the `<iframe>` and stops, so an unnamed editor reads as clean.
- **Coverage is stored beside the violations and judged apart from them.** Record-bound forms are reached by following the first `op=edit&id=…` link of each list page, so an empty list or a renamed link makes violations vanish — and a baseline holding violations alone reads that as a fix. Violations down is a pass; coverage down is its own failure and offsets nothing.
- **Readable paths are asked of the stand.** A path cannot say whether `/news/internet` is a category or a material, nor whether `/account/logout` is an operation, so the crawl hands every new path to `tests/Support/address_probe.php`: an operation of its prefix by `Route::isRouteReserved()` keeps the allowlist of operations in force, a registry entry by `Route::getRoute()` is a record (`view#rec`), a category or forum section is the list of its prefix, and a path that is neither is skipped. Without it every path reads as the home page and the site is walked blind.
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
