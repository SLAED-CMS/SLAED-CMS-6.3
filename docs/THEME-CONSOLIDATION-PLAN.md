# Theme Visuals Consolidation Plan

Status date: 2026-07-16, revision 3 (after two architecture audit rounds).
**EXECUTED: all 10 steps below were completed and verified on 2026-07-16**
(php -l, phpstan, phpunit 194 tests OK, Playwright flows: emoji panel, file
upload, multi-editor dedup, theme validator negative test, setExit page).
Post-execution follow-ups: plugin JS renamed to `editor-{tags,emoji,upload}.js`
(historical `slaed-*.js` mentions below refer to the pre-rename files); the
toolbar icon color selector in the skins was raised to
`button.toastui-editor-toolbar-icons` so the theme token beats the vendor rule.
The binding principle and the canonical theme layout live in
`.rules/architecture.md` (section "Theme ownership"); the canonical token
dictionary lives in `.rules/global.md`. This file remains as the design
record for the executed migration.

Usage/reference counts are intentionally not recorded here: they drift with
every edit. Re-measure with grep immediately before executing a step.

## Principle (approved, binding)

- A theme (`templates/<theme>/`) is a self-contained, replaceable visual
  package: skins, icon libraries, icon mappings, component partials, default
  avatars.
- A plugin owns behavior, integration logic, and versioned vendor engine files
  (JS/CSS/i18n that must update together with plugin code).
- Switching the theme changes the entire visual identity: editor, icons,
  avatars, dialogs, everything.
- No fallback copies in plugins: a theme is mandatory, the requirement is
  replaceability, not optionality.
- Physically identical files across themes are intentional: theme independence
  outweighs deduplication.
- Icon contract: Bootstrap Icons is the mandatory platform icon contract.
  Every theme physically ships the library (`assets/vendor/`), and theme
  partials, plugin JS, and skins may rely on `bi-*` class names and glyph
  codepoints. A theme may replace the underlying library only by providing a
  compatible `bi-*` mapping. Record this wording in `.rules/architecture.md`
  when executing step 8.

## Completed

| Step | Result |
|---|---|
| Avatars | `templates/<theme>/images/avatars/system/{user,guest,deleted}.svg` + `presets/01-56.svg`; `getUserAvatarUrl()` resolves from the active theme; local DB values migrated to `presets/NN.svg`; hardcoded gallery/save filters removed; `uploads/avatars/default/` deleted. Reproducible upgrade migration is still missing — see step 4 |
| Bootstrap Icons | `templates/<theme>/assets/vendor/bootstrap/css/` + fonts; `plugins/bootstrap-icons/` deleted; `core/security.php` and `core/system.php` updated |
| Editor Toast UI | Dialog partials -> `templates/<theme>/partials/editor-{dialogs,files}.html` via standard `$tpl->getHtmlPart()`; skin -> `templates/<theme>/assets/css/editor.css` (token-based); `EditorToastTemplate` class deleted. Two audit findings remain, both fixed by step 1: CSS load order and generic naming |
| Rules | "Theme ownership" section + canonical theme layout recorded in `.rules/architecture.md` |

## Audit findings driving this revision

1. Blocker — CSS order: theme skin is bundled into `<head>` while
   `Editor::getContent()` / `Editor::getCode()` emit driver assets
   (`toastui-editor.min.css`) inline before the widget
   (`core/classes/editor.php`), so the vendor engine CSS loads after the theme
   skin and wins equal-specificity conflicts on shared selectors.
2. Blocker — the plugin JS (`slaed-emoji.js`, `slaed-upload.js`) still builds
   visual markup via `innerHTML`; a theme designer cannot change that markup
   without editing plugin code.
3. Blocker for upgrades — the avatar DB migration was executed locally but does
   not exist as a versioned SQL migration; other installations would break
   after update (`uploads/avatars/default/` gone, old values still in DB).
4. No-fallback mode requires a theme structure validator covering every theme
   selection path, not only activation.
5. `editor.css` / `editor-*.html` names are Toast-UI-specific in content but
   generic in name; a second editor would collide (fixed by step 1).
6. The avatar resolver accepts any `presets/...` value and concatenates it into
   a theme path; canonical-format validation must live in the resolver itself,
   not only in the save path.

## Planned work (approved, in execution order)

### 1. Namespaced editor asset contract + deterministic CSS order

- New canonical layout per editor key:
  - `templates/<theme>/assets/editors/toastui/skin.css`
  - `templates/<theme>/partials/editor-toastui-dialogs.html`
  - `templates/<theme>/partials/editor-toastui-files.html`
- Move the current `assets/css/editor.css` (both themes) to the new path.
  `assets/editors/` is not globbed by `getThemeAssets()`, so the skin
  automatically leaves the global auto-bundle.
- Skin loading contract in `core/classes/editor.php`, shared by
  `Editor::getContent()` AND `Editor::getCode()`:
  - the skin path is built from the editor key that is actually effective
    after ALL fallbacks (format mismatch swap, invalid-driver -> `plain`,
    CodeMirror fallback) — track the final key alongside the driver instead of
    reusing the requested `$key`;
  - the skin link is emitted after `$driver->getAssets()`;
  - deduplicate per `<theme>:<editor-key>` pair in Editor (drivers already
    suppress their own repeated assets, but the shared code must not duplicate
    the skin when several editors/widgets render on one page);
  - a missing skin file for an editor that declares one is logged to the site
    log, never silently ignored.
- Update the canonical layout list in `.rules/architecture.md`.
- Verify: HTTP render of pages with one editor, two editors, and a code
  editor; the skin `<link>` follows the engine `<link>` exactly once per
  editor key; toolbar icons and dialog styling correct in both themes; the
  global CSS bundle no longer contains editor selectors.

### 2. Move JS-generated visual markup into theme templates

- Theme-owned markup must cover the full generated surface, not only leaf
  items:
  - emoji panel: panel skeleton (container, search field, tabs container,
    grid container) plus tab button, emoji button, empty-search text —
    currently all built in `slaed-emoji.js`;
  - file manager: table skeleton (`<table><tbody>`) plus file row, empty
    list, upload error message — currently built as strings in
    `slaed-upload.js`.
- Delivery: `<template>` blocks rendered with the editor partials.
  Multi-editor pages must not produce duplicate HTML ids — one shared set of
  templates is emitted once per page, and the JS receives template references
  through the driver `$opt` payload (no lookups of ambiguous global ids).
- JS clones templates and fills only `textContent`, `src`, and `data-*`; no
  `innerHTML` with markup strings.
- Contract: template block names and `js-*`/`data-*` hooks are part of the
  theme-plugin contract recorded in `.rules/architecture.md`.
- Verify: real HTTP flow — upload a file, browse the file list, open the emoji
  panel, search with no results; page with two editors has no duplicate ids;
  check `storage/logs/*` afterwards.

### 3. Theme structure validator

- One centralized function (no theme manifest system) that checks the
  canonical structure: `assets/css/base.css`, `assets/css/theme.css`,
  Bootstrap Icons CSS + font, `images/avatars/system/{user,guest,deleted}.svg`,
  `images/avatars/presets/`, and — for every editor whose manifest declares
  theme assets — its skin and partials.
- Editor requirements are declared in the existing editor `manifest.json`
  (for example Toast UI declares required skin + partials; `plain` declares
  nothing). No hardcoded editor list inside the validator.
- Apply the validator at every theme selection path:
  - theme lists (user account list, admin user edit, global config) — hide
    incomplete themes or mark them unavailable;
  - server-side re-check on every save path (user save, admin user save,
    global config save, installer);
  - `getTheme()` runtime guard against a theme broken or removed after
    activation;
  - the mandatory `admin` theme is validated the same way.
- Verify: attempt to activate a deliberately stripped theme copy through each
  path; confirm refusal and clear error; activate a complete theme normally.

### 4. Versioned avatar DB migration + resolver hardening

- Add an idempotent migration to the upgrade chain executed by
  `setup/index.php`: `default/NN.<ext>` -> `presets/NN.svg` for existing
  presets, orphaned values (such as `default/00.gif`) -> `''`.
- Versioning decision (resolved 2026-07-16): the current 6.3 is still in
  development and unreleased, so extending `table_update6_3.sql` is
  sufficient; no new upgrade route is needed.
- Backup (decision 2026-07-16): no backup step is added; the migration must be
  strictly idempotent and safe to re-run instead.
- Before/after row counts must run in the PHP upgrade flow (or a separate
  verification routine): plain SELECTs inside the SQL file are executed but
  their results are discarded — `setup/index.php` reports output only for
  CREATE/ALTER/DELETE/DROP/RENAME/UPDATE statements.
- Harden `getUserAvatarUrl()`: accept only the canonical
  `presets/<safe-filename>.<gif|png|jpe?g|svg>` format (reject traversal and
  any other shape regardless of how the value entered the DB — admin edit
  included); everything else falls back to `system/user.svg`.
- Verify: run the migration twice on a DB copy (idempotence), resolver check
  with hostile values (`presets/../x.svg`, `presets/`, empty), upgrade flow
  end-to-end on a copy.

### 5. Unify emergency page assets in `setExit()`

- Replace the `getThemeCssFiles($theme)` loop and the manual Bootstrap Icons
  `$iconcss` block in `core/security.php` with one loop over
  `getThemeAssets($theme, 'css')`. The editor skin is irrelevant there
  (no editor on emergency pages; `assets/editors/` is not globbed).
- Delete `getThemeCssFiles()` from `core/system.php` (single caller).
- Note: vendor CSS moves before base/theme on the emergency page, matching
  regular pages; selectors do not overlap.
- Verify: `php -l`, trigger a `setExit()` page over HTTP, icon font and theme
  styles load, `storage/logs/*` clean.

### 6. Collapse repeated window headers in the dialogs partial

- The image/link/emoji header blocks are identical; render them with
  `{% for window in windows %}` (engine support confirmed in
  `core/classes/template.php`; examples in `content-list.html`).
- `driver.php` passes
  `'windows' => [['name' => ..., 'head_id' => ..., 'label' => ...], ...]`
  plus shared labels. Same `id`, `data-window`, `data-window-head` values —
  plugin JS untouched. Apply in both themes.
- Verify: HTTP render, three headers present with correct attributes.

### 7. Normalize the theme token dictionary

Canonical minimal set every theme must define in `assets/css/base.css`:

```
--sl-color-bg          --sl-color-bg-soft
--sl-color-border      --sl-color-border-strong
--sl-color-text        --sl-color-text-muted
--sl-color-primary     --sl-color-warning
--sl-radius-control    --sl-shadow-panel
--sl-space-xs / -sm / -md / -lg
```

- Renames, full migration, no aliases, no compatibility layers:
  lite `brand-link` -> `primary`, lite `surface` -> `bg-soft`,
  lite `shadow-medium` -> `shadow-panel`, admin `muted` -> `text-muted`.
  Re-measure usages by grep immediately before executing.
- Then remove all paired `var(a, var(b))` fallbacks from the editor skin in
  both themes; no dedicated `--sl-editor-*` variables.
- Record the canonical list in `.rules/global.md`.
- Verify: repo-wide grep shows zero old names; HTTP render of regular, admin,
  and editor pages; visual spot check of dialogs and the emoji panel.

### 8. Rename `vendor/bootstrap/` to `vendor/bootstrap-icons/`

- Directory name must match the upstream package; "bootstrap" alone reads as
  the Bootstrap framework, which this project does not use.
- Update every reference: `core/security.php` (none left after step 5 —
  verify), all `demo/*.html` links pointing at the old path, and the validator
  paths from step 3.
- Record the icon contract wording (see Principle) in
  `.rules/architecture.md`.
- Verify: HTTP check of icon font on regular and emergency pages; repo-wide
  grep for the old path returns nothing.

### 9. Editor class names and localization (promoted from backlog)

- Rename `.slaed-emoji-*` -> `.sl-editor-emoji-*` and `.slaed-bi-*` ->
  `.sl-editor-icon-*` synchronously across the full set:
  `skin.css` (both themes), `editor-toastui-*.html` partials (both themes),
  `slaed-tags.js`, `slaed-emoji.js`, `slaed-upload.js`, `driver.php`.
- Move hardcoded editor locale labels (`getEmojiLabels()` and the `'Tabs'`
  literal in `driver.php`) into the language constants system
  (all 6 languages).
- Verify: repo-wide grep shows zero `.slaed-bi-` and `.slaed-emoji-`
  occurrences anywhere (not only in the listed files); HTTP render, emoji
  panel and toolbar functional; constants present in all language files.

### 10. Final cleanup

- Delete the dead unminified vendor build
  `plugins/editors/toastui/assets/toastui-editor.js` (zero references; this is
  dead-code removal, not vendor subsetting). Grep before deletion.
- Rename `img_find()` -> `getThemeImagePath()` (returns a path; verb prefix,
  no wrapper), migrate all call sites, repo-wide grep for the old name.
- Verify: `php -l` on touched files, editor loads over HTTP, image-using pages
  render.

## Out of scope

- Other editor plugins (ckeditor, codemirror, plain, tinymce) stay untouched
  until a decision about their future; the namespaced contract from step 1 and
  the manifest-declared theme assets from step 3 already reserve their place.
- `uploads/` remains user content only; no theme assets there.
