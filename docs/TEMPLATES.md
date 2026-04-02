# Template System Documentation

This document describes the current template reality in the repository.

## Current State
The active file-backed template runtime in the current repository is `Template` in `core/classes/template.php`.

Historical legacy rendering still exists in PHP-side markup generation and fragment assembly, but the repository snapshot does not contain an active `core/template.php` runtime file.

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
- `templates/default`
- `templates/lite`
- `templates/simple`

## Theme Roles

### `templates/default`
- bundled frontend theme directory with layouts, pages, partials, fragments, assets, and images

### `templates/simple`
- bundled frontend theme with the same template tree structure as the other frontend themes
- includes local Bootstrap 5 assets

### `templates/admin`
- current admin theme files
- contains admin layouts, pages, partials, and fragments used by the admin runtime

### `templates/lite`
- bundled frontend theme

## Theme Structure
Themes should follow this structure:

```text
templates/<theme>/
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

Common active layout files in bundled themes include `layouts/app.html` and `layouts/home.html`.

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

## Runtime Syntax
The current `Template` runtime supports:
- escaped output: `{{ var }}`
- raw output: `{{{ var }}}`
- conditions:
  - `{% if var %}`
  - `{% elseif var %}`
  - `{% else %}`
  - `{% endif %}`
- loops:
  - `{% for item in items %}`
  - `{% endfor %}`
- includes:
  - `{% include 'header' %}` (auto-resolves to `partials/header.html`)
  - `{% include 'partials/custom.html' %}`
  - `{% include 'fragments/foo.html' %}`
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
- assume a removed legacy runtime file still exists as a valid target for new architecture work
- add placeholder mapping helpers for new slices
- copy theme inventories from installations or snapshots that are not present in the current repository

## Current Runtime Slices
Already moved to the current runtime:
- admin login
- admin registration
- admin preview
- admin searchbox

Additional confirmed current usage:

- frontend pages are finalized through `setFoot()` and `$tpl->getHtmlPage(...)`
- admin page rendering also ends in `$tpl->getHtmlPage(...)`
- frontend and admin fragments are rendered through `$tpl->getHtmlFrag(...)`

## Assets
Theme-local assets should live inside the theme.

Recommended pattern:
- `assets/css/theme.css`
- `assets/js/theme.js`
- `assets/vendor/<library>/...`

Example already present:
- `templates/simple/assets/vendor/bootstrap/`

### Automatic Asset Loading
The current runtime automatically injects CSS and JS files for components and blocks. If a file named identically to the included partial exists (for example `partials/alerts.css` or `partials/alerts.js`), the engine detects it at compile time and injects `<link>` and `<script defer>` tags into the compiled PHP output. This keeps asset registration close to the template file instead of spreading it across PHP callers.

## Head and SEO Integration

Frontend modules normally prepare head data through `setHead()` before the final page is rendered.

Confirmed current SEO overrides:

```php
setHead([
    'title' => $title,
    'canon' => 'index.php?name=news&op=view&id='.$id,
    'robots' => 'noindex, follow',
]);
```

Behavior:

- `canon` overrides the centrally generated canonical URL
- `robots` overrides the default robots meta value
- if `canon` is omitted, the runtime builds the canonical URL from normalized route parameters

## Migration Source Of Truth
For active repository template guidance, use:

- `docs/TEMPLATE_STATUS.md`

For current runtime status, use:
- `docs/TEMPLATE_STATUS.md`
