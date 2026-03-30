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
- current new target theme
- current frontend theme directory with layouts, pages, partials, fragments, assets, and images

### `templates/simple`
- minimal clean reference theme
- designed for the final runtime
- includes local Bootstrap 5 assets

### `templates/admin`
- current admin theme files
- contains admin layouts, pages, partials, and fragments used by the admin runtime

### `templates/lite`
- existing legacy frontend theme

## Final Theme Structure
New themes should follow this structure:

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

`index.html` is not the main architectural entry for the final system.

Common active layout files in bundled themes include `layouts/app.html` and `layouts/home.html`.

## Final Runtime Syntax
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

## Current New-Only Slices
Already moved to the final runtime:
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

### Smart Asset Loading (Zero-Overhead)
The final runtime automatically injects CSS and JS files for components and blocks. If a file named identically to the included partial exists (e.g., `partials/alerts.css` or `partials/alerts.js`), the engine detects it at compile-time and injects `<link>` and `<script defer>` tags into the compiled PHP output. This feature ensures assets are loaded exactly once per request, with absolutely no file I/O overhead at runtime.

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
- `docs/RAW_SLOTS_ADMIN.md`

For current runtime status, use:
- `docs/TEMPLATE_STATUS.md`
