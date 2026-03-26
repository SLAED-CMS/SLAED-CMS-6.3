# Template Status

## Final Runtime
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

## Current New-Only Slices
Already running through the final runtime:
- admin login
- admin registration
- admin preview
- admin searchbox

## Current Themes
Present theme directories:
- `templates/admin`
- `templates/default`
- `templates/default_old`
- `templates/lite`
- `templates/simple`

Reference minimal final theme:
- `templates/simple`

## Legacy Boundary
- `core/template.php` is legacy-only
- legacy rendering still exists outside migrated slices
- `templates/default_old` remains the main migration source set

## Tests
Current relevant runtime-related tests:
- `tests/Unit/AdminLoginBridgeFlowTest.php`
- `tests/Unit/AdminPageRenderFlowTest.php`
- `tests/Unit/AdminPreviewBridgeFlowTest.php`
- `tests/Unit/AdminSearchboxBridgeFlowTest.php`
- `tests/Unit/ViewBridgeSmokeTest.php`

## Not Migrated
- main site layouts
- broader frontend page rendering
- block system
- head/foot systems
- most legacy template calls in PHP

## Limits
- no advanced expression language
- no named include arguments
- no deep inheritance chains
- project-wide migration is still incomplete
