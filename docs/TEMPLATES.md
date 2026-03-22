# Template System Documentation

This document describes the current template reality in the repository.

## Current State
The repository contains two template layers:

### 1. Legacy Template Layer
File:
- `core/template.php`

Role:
- legacy placeholder-based rendering
- still used by large parts of the project

Status:
- legacy-only
- not the target for new template architecture work

### 2. Final Template Runtime
File:
- `core/classes/template.php`

Role:
- final runtime for new template work

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
- `templates/default_old`
- `templates/lite`
- `templates/simple`

## Theme Roles

### `templates/default_old`
- primary legacy source set
- contains the large old template inventory that is being audited for consolidation

### `templates/default`
- current new target theme
- still incomplete

### `templates/simple`
- minimal clean reference theme
- designed for the final runtime
- includes local Bootstrap 5 assets

### `templates/admin`
- current admin theme files
- contains both legacy flat templates and a small number of new partials

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

Preferred main entry:
- `layouts/main.html`

Optional secondary layout:
- `layouts/bare.html`

## Final Runtime Syntax
The current `Template` runtime supports:
- escaped output: `{{ var }}`
- raw output: `{{{ var }}}`
- conditions:
  - `{% if %}`
  - `{% elseif %}`
  - `{% else %}`
  - `{% endif %}`
- loops:
  - `{% for item in items %}`
  - `{% endfor %}`
- includes
- layout inheritance
- blocks

## Current Migration Direction
New template work should:
- move HTML out of PHP
- pass plain data from PHP
- render through `Template/$tpl`
- avoid new bridge wrappers
- avoid fallback inside completed slices

Do not:
- extend `core/template.php` for new architecture work
- add placeholder mapping helpers for new slices
- copy `templates/default_old` one-to-one into new themes

## Current New-Only Slices
Already moved to the final runtime:
- admin login
- admin registration
- admin preview
- admin searchbox

## Assets
Theme-local assets should live inside the theme.

Recommended pattern:
- `assets/css/theme.css`
- `assets/js/theme.js`
- `assets/vendor/<library>/...`

Example already present:
- `templates/simple/assets/vendor/bootstrap/`

## Migration Source Of Truth
For active migration rules and sequence, use:
- `docs/plans/ROADMAP_TEMPLATE.md`

For current runtime status, use:
- `docs/TEMPLATE_STATUS.md`
