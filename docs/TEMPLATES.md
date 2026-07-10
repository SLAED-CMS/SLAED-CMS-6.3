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
- includes local Bootstrap assets under `assets/vendor/bootstrap/`

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
  - `{% include 'header' %}` (auto-resolves to `partials/header.html`)
  - `{% include 'partials/custom.html' %}`
  - `{% include 'fragments/foo.html' %}`
  - `{% include 'fragments/row.html' with row %}` (context passing supported)
- layout inheritance
- blocks
- components:
  - `{% component 'modal' %}` (auto-resolves to `partials/modal.html`)
  - `{% slot header %}`

Additional confirmed runtime behavior:

- `extends` resolves parent layouts inside the active theme
- includes and components are validated against theme paths
- the runtime can register companion CSS and JS assets for included partials and components

## Current Migration Direction
New template work should:
- move HTML out of PHP
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
- `assets/css/theme.css`
- `assets/js/theme.js`
- `assets/vendor/<library>/...`

Example already present:
- `templates/lite/assets/vendor/bootstrap/`
- `templates/admin/assets/vendor/bootstrap/`

### Automatic Asset Loading
The current runtime automatically injects CSS and JS files for components and blocks. If a file named identically to the included partial exists (for example `partials/alerts.css` or `partials/alerts.js`), the engine detects it at compile time and injects `<link>` and `<script defer>` tags into the compiled PHP output. This keeps asset registration close to the template file instead of spreading it across PHP callers.

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

## Migration Source Of Truth
For active repository template guidance and current runtime status, use this document (`docs/TEMPLATES.md`).
