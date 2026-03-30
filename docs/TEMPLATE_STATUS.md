# Template Status

## Current Runtime
- class: `Template`
- file: `core/classes/template.php`
- shared runtime object: `$tpl`
- public API:
  - `getHtmlPage()`
  - `getHtmlPart()`
  - `getHtmlFrag()`

## Implemented Runtime Features
- load
- compile
- cache
- render
- escaped output
- raw output
- conditions
- loops
- include
- layout inheritance
- blocks
- components
- slots
- automatic asset injection (CSS/JS)

Confirmed from the runtime implementation:

- parent layout resolution through `extends`
- block overrides inside inherited layouts
- path validation for includes and components

## Current Runtime Slices
Already running through the current runtime:
- admin login
- admin registration
- admin preview
- admin searchbox

Additional confirmed current usage:

- frontend pages are finalized through `setFoot()` and `$tpl->getHtmlPage(...)`
- admin pages also render through `$tpl->getHtmlPage(...)`
- frontend and admin fragments render through `$tpl->getHtmlFrag(...)`

## Current Themes
Present theme directories:
- `templates/admin`
- `templates/default`
- `templates/lite`
- `templates/simple`

Reference bundled minimal frontend theme:
- `templates/simple`

Main active frontend theme:
- `templates/default`

## Tests
Current relevant runtime-related tests:
- `tests/Unit/AdminLoginBridgeFlowTest.php`
- `tests/Unit/AdminPageRenderFlowTest.php`
- `tests/Unit/AdminPreviewBridgeFlowTest.php`
- `tests/Unit/AdminSearchboxBridgeFlowTest.php`
- `tests/Unit/ViewBridgeSmokeTest.php`

## Not Migrated
- block system
- most legacy template calls in PHP

The remaining migration pressure is primarily in PHP-side data preparation, raw HTML assembly, and legacy helper output feeding the template boundary.

## Limits
- no advanced expression language
- no named include arguments
- no deep inheritance chains
- project-wide migration is still incomplete

## Notes

- the repository snapshot does not contain an active `core/template.php` runtime file
- current runtime status should be read together with `docs/TEMPLATES.md`
