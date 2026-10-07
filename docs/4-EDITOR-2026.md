# Editor 2026

Work plan for one editor shell and a second role for CodeMirror: CodeMirror stays the code editor and becomes a fifth
text editor to choose beside Plain, TinyMCE, CKEditor and Toast UI; Plain stays the light one. Plain and CodeMirror
wear the same frame, capsule, status line, palette and comparison window.

Status: batches 0 to 2 done on 2026-10-07 (the decisions answered, the `<br>` check, the baseline; CodeMirror painted
by the admin theme, One Dark gone, the panels in six locales; `type` read as a list, the core and each language built
as files of their own). The order of the batches is kept in `docs/ROADMAP-2026.md`. Update this line as batches land.

No line numbers anywhere in this document on purpose: every reference names the function, the file or the constant
it points at, and that name is what to search for.

## Decisions

Taken by the owner on 2026-10-06; settled, not to be reopened by a batch.

- **CodeMirror in two roles.** It stays the editor of `Editor::getCode()` and joins the list of text editors that
  `Editor::getContent()` and the settings offer. The owner of a site chooses it like any other text editor; nobody
  receives it without choosing it.
- **Plain stays light, not primitive.** Plain keeps zero script weight of an engine and takes every ability that is
  cheap and fast on a textarea (owner, 2026-10-06): the list under "Plain: what it takes" is the rule. What redraws the
  whole text on every key — the highlighting mirror and line numbers of the stand — stays out; who wants it chooses
  CodeMirror.
- **One plugin, `type` as a list.** `plugins/editors/codemirror/manifest.json` declares `"type": ["content", "code"]`;
  `EditorCodemirror` implements both `ContentDriver` and `CodeDriver`. A manifest may still give `type` as one string;
  both forms are read through one check. No second plugin directory.
- **The interfaces stay.** `ContentDriver`, `CodeDriver`, `Editor::getContent()` and `Editor::getCode()` keep their
  signatures; the change is the manifest field `type` and the checks that read it.
- **The look is the stand's final face** `public/demo/ed-code-09-final.html` ("Фокус · финал"): at rest the text, the
  format tab and the rail; under the hand the command capsule beside the tab; the status line under the text.
- **The comparison is a window of the canon** (`docs/WINDOW.md`) with the views "Вместе" and "Рядом", the count and
  "Вернуть было"; it is checked before saving and moves nothing in the form.
- **CodeMirror is painted by theme tokens.** `classHighlighter` writes `tok-*` classes, the theme colours them, the
  scheme switches the code with the page; One Dark leaves the tree.
- **Esc closes the palette at once,** even with text in its search field.
- **The file window serves Plain and CodeMirror as text** (owner, 2026-10-06), the same window Toast UI opens from its
  folder button, through an adapter; CodeMirror as code gets none. Toast UI keeps its own wiring.

Rejected, with the reason, so it is not proposed again: CodeMirror as the only engine (it would take the zero-weight
choice away), and Plain as a light code editor (the code screens are the panel's, where CodeMirror is always wanted).

## Measured weights

Gzip, measured on 2026-10-06 from the files of the tree and from esbuild builds of the same packages.

| Asset | Weight |
| --- | --- |
| Toast UI, the default text editor of the site: script, style, Russian locale, emoji words, its two scripts | about 285 KB |
| `plugins/system/filemanager.js`, added where the file window is drawn | 18 KB |
| `cm6.bundle.js` today, seven languages | 237 KB |
| CodeMirror core with `basicSetup` and lint, no language | 126 KB |
| CodeMirror with `minimalSetup` and markdown (markdown pulls html, css and js for code blocks) | 177 KB |
| Plain | 0 |

## What exists today

- `Editor::getCode()` serves nine screens of the panel: `admin/modules/blocks.php`, `database.php`, `editor.php`,
  `security.php`, `template.php`, `uploads.php` (`tplconfig`), the system file editor of `core/admin.php` and two
  screens of `modules/sitemap/admin/index.php`. `$conf['editor']['code']` is `codemirror`.
- `Editor::getContent()` has one caller, `getTplTextarea()` in `core/helpers.php`, which nineteen places in ten files
  use: `core/system.php`, `core/user.php`, `modules/account`, `modules/forum`, `modules/node`, `modules/contact/admin`,
  `admin/modules/newsletter.php`, `admin/modules/messages.php` and others. `getEditorKey()` takes, for the panel, the
  editor of the administrator's own profile (`$admin[3]`), then `$conf['editor']['admin']`, then `plain`; for the site
  `$conf['editor']['user']`, then `plain`. `config/global.php` sets `user` to `toastui` and no `admin` key, so Plain is
  the text editor of every administrator who chose none and the fallback of the site.
- The manifest field `type` is compared as one string in three places of `core/classes/editor.php`: `getCode()`,
  `checkManifest()` and `getEditorList()`. `getSelect()` builds the editor lists of `admin/modules/config.php`,
  `admin/modules/admins.php` and `core/admin.php` through `getEditorList()`.
- `EditorPlain::getWidget()` renders the fragment `textarea` and nothing else.
- `EditorCodemirror::getWidget()` assembles its start script as a PHP string, mounts into the fragment `editor-mount`
  and adds `CM6.oneDark` for the `full` profile, which every caller gets. `plugins/editors/codemirror/assets/cm6.css`
  fixes the editor at 400 points.
- The admin `theme.css` gives `.sl-div-form .cm-editor .cm-content` the body face Tahoma at a 16-point line, and gives
  editors a `--sl-editor-height` floor doubled for the second editor of a form.
- `plugins/editors/codemirror/build/entry.js` exports `EditorView`, `keymap`, `EditorState`, `basicSetup`,
  `indentWithTab`, seven languages and `oneDark`; esbuild writes one IIFE bundle, `cm6.bundle.js`.
- Toast UI binds the file window through `SlaedFileManager.addUpload(id, ed, opt)` in `plugins/system/filemanager.js`,
  which reads and writes the editor through its own `getEditor()` and `insertText()`.
- Fragments `editor-mount.html` and `textarea.html` exist in both themes; `window.SlaedEditors` in
  `plugins/system/slaed.js` loads and releases editor scripts on htmx swaps; `Editor::getInitScript()` wraps a
  driver's start code.
- Tests touching the area: `tests/Unit/EditorFormatTest.php`, `tests/Unit/EditorWindowTest.php`.
- `docs/EDITORS.md` recorded an open defect: some `ContentDriver` drops every `<br>` on save. It was the mount of
  `getTplTextarea()`, repaired on 2026-10-07 by step 27 of `docs/ROADMAP-2026.md`; the contract stands under "Line
  Breaks on Mount", and CodeMirror as text mounts through the same function.

## Design

### The manifest

`type` is a string or a list of `content` and `code`. One private check in `Editor` answers "does this manifest
serve this type" and replaces the three comparisons. CodeMirror's manifest becomes:

```json
{
    "id": "codemirror",
    "label": "CodeMirror 6",
    "type": ["content", "code"],
    "roles": ["user", "admin"],
    "formats": ["plain", "markdown", "html"],
    "lang": ["php", "html", "css", "js", "json", "sql", "xml", "text"]
}
```

`getCode()` keeps refusing a code editor that lacks the `admin` role; the `html` format stays the panel's, as
`getContent()` already enforces for every editor.

### The shell

One component in canon, byte-identical in `templates/admin` and `templates/lite`, as the window canon is:

```
.sl-editor               frame; carries data-dirty, data-lint, data-view; the rail colour follows them
  .sl-editor-row         the format tab and the command capsule
  .sl-editor-card        the engine (textarea or CodeMirror mount), the preview, the status line
  .sl-editor-status      caret, size, variables, "изменено" (opens the comparison), lint badge, language
```

The markup lives in fragments (`editor-frame.html` and what it needs), the rules in `theme.css`, the values in the
API block of `base.css`; the component names are declared in `tools/ui-contract.php`. A driver hands data to the
fragments and writes no class string of its own.

### The runtime

One script, `plugins/system/editor.js`, delivered by `Editor::getAssetTags()` under a `static $done`, owns the
behaviour of the stand's engine (`public/demo/assets/demo.js`, the editors section): status, dirty mark, full screen,
wrap, copy, reset, undo, the capsule, the palette, the comparison and the preview. It mounts CodeMirror itself when the
frame asks for it, so the start script of `EditorCodemirror` stops being PHP that writes JavaScript. Registration with
`window.SlaedEditors` stays, so htmx swaps keep releasing editors. The textarea stays in the markup under CodeMirror
and carries the value to the server, so a page without script still submits.

### Loading CodeMirror

The build writes the core and each language as separate files instead of one bundle; the runtime loads the core when
an editor frame enters the viewport or takes focus, and the language the frame names. A page of text loads no PHP or
SQL grammar; a code screen loads one language.

### What the page tells the editor

A caller may pass, through the data of `getCode()` / `getContent()`:

| Key | Meaning |
| --- | --- |
| `vars` | the variables of a template with their descriptions; turns on the variable list, the hint and the unknown-variable check |
| `preview` | how to render the text for the preview; absent means no preview button |
| `lint` | on for code in `html`; the checks of the stand: unknown variable, tag left open or closed twice, img without alt, `_blank` without noopener, value without quotes, template without `[src]` when `vars` holds it |

`tplconfig` is the first caller to pass `vars`, taken from the same constants `_TPINFO` lists today.

### Plain: what it takes

Every ability that costs nothing at rest and nothing per key beyond the key itself:

- the shell, the capsule, the status line, the dirty mark, full screen, wrap, copy, reset, undo and redo, the
  palette;
- height from the text (`field-sizing: content`, a measured fallback where it is missing);
- the variable list and its insertion at the caret, and the hint at the caret after `[`, which measures the caret only
  while a `[…` is being typed;
- for `html`: the lint of the template as a badge and a list, run after a pause, without marks inside the text; "тег на
  строку" and "в одну строку";
- for `markdown`: bold, italic, code, link, list and quote around the selection, and a list continued by Enter — the
  same commands CodeMirror as text gets;
- a counter of characters with what is left, when the field carries a limit;
- comparison and preview, both computed only when asked for.

Out: the highlighting mirror and line numbers, a search panel of its own (the browser's Ctrl+F searches a textarea),
multiple carets, folding.

### File manager

The window is the runtime's, not an editor's: `getFileManagerWindow()` draws it, `plugins/system/filemanager.js`
behaves it, and `SlaedFileManager.addUpload(id, ed, opt)` binds it to an editor that answers `focus()`,
`insertText()`, `exec('addImage', …)`, `getMarkdown()`, `addHook('addImageBlobHook', …)`, `addCommand()` and
`insertToolbarItem()`. The last two are Toast UI's toolbar and the runtime already skips them when they are missing.

- **An adapter** answers the first five over a CodeMirror view or a textarea: insertion at the caret with undo, a
  markdown picture for `addImage`, the whole text for the meter, and paste and drop handlers that pass an image to
  the upload as Toast UI's hook does.
- **The folder button lives in the capsule** with the icon Toast UI shows, and the palette carries "Файлы: загрузить и
  вставить"; the runtime gains one public entry that opens the window, in place of the internal `addPanel()`.
- **One computation of the options.** The upload place, its rule, the limits, the tokens and the actions are worked
  out inside `EditorToastUi` today; they move to one shared function the three drivers call.
- **Where it appears:** wherever Toast UI would show it, that is a field with an upload place; never for code.
- **Above the full screen.** The window is non-modal; `setWindowFront()` in `slaed.js` lifts such windows from layer
  10050 up, above the `--sl-z-modal` the shell's full screen stands on, so the order holds by itself and the batch only
  checks it.

### Comparison of long texts

The token diff of the stand fills a table of both lengths, which a forum post of ten thousand words turns into about
two hundred megabytes. Above a token limit the comparison works by lines, and inside a changed line by tokens.

### Engines

| Ability | Plain | CodeMirror as text | CodeMirror as code |
| --- | --- | --- | --- |
| frame, rail, capsule, status, full screen, wrap, copy, reset, undo, palette, comparison | yes | yes | yes |
| preview | yes | yes | yes |
| file window (`SlaedFileManager.addUpload`) | yes, through the adapter | yes, through the adapter | no |
| variables, hint at the caret | when `vars` is passed | when `vars` is passed | when `vars` is passed |
| markdown commands, list continued by Enter, character counter | yes | yes | no |
| lint | `html`: badge and list | no | badge, list, marks in the text |
| highlighting, search panel, multiple carets, folding | no | markdown | the language |

### Strings

Every word of the shell is a constant in the six locales under `.rules/constants.md`; the runtime reads them from
the fragments, never from a string in the script.

## Answered decisions

Put to the owner in batch 0 and answered on 2026-10-07; settled like the decisions above.

1. **Preview files: bundled samples of the theme.** The preview of a file template takes tiny sample files shipped in
   `templates/admin/assets`: one picture, one audio, one video and one PDF; an archive needs no file, its template
   is a link. The preview works on a fresh installation, the same every time, and shows no upload of anybody.
   Rejected: the newest real upload of that type (empty on a fresh installation, changes from upload to upload), and
   no preview for a kind without a sample.
2. **Preview frame: always sandboxed, a link for PDF.** Every preview renders in a sandboxed frame, in the panel as
   on the site; a PDF is not embedded, the preview shows a link that opens the sample. One rule for the frame.
   Rejected: no sandbox for the panel only (two modes of the frame).
3. **Preview of text: a new route.** One `hx-post` with a token renders the text through `getTplPreviewContent()`,
   the block the "Предпросмотр" buttons of the forum, the private messages and the newsletter already draw by
   submitting the whole form; the size of the text is limited. It is a POST with a token, never a GET, as the CSRF
   contract of the htmx routes asks. Rejected: the preview for code templates only.
4. **Capsule on the site: the full one.** A member in the lite theme gets the same capsule as the panel: every
   command the engine supports.
5. **Emoji: carried over, loaded on the first click.** The emoji panel of Toast UI serves Plain and CodeMirror as text
   through the adapter of the file window; its script and the search words of the locale load when the panel is
   first opened, so Plain at rest stays at zero script weight. Rejected: emoji for Toast UI only.
6. **A draft in the browser: `sessionStorage` for everyone.** An unsent text is kept in `sessionStorage` of the tab,
   as WordPress keeps its backup copy: it survives a reload, a link followed by mistake, an expired token and
   Ctrl+Shift+T, and is erased on submit. It dies with the browser unless the browser restores its last session on
   start, which brings `sessionStorage` back with the tabs. Rejected: `localStorage` everywhere or for the panel
   only (a shared computer keeps the text for the next person), and no draft.
7. **The demo stand: not this plan's.** `public/demo/` is left as it is; the owner deletes it himself later. No batch
   adds to it, edits it or deletes from it; batches only read it as the stand of record.

## Batches

Each batch ends with its checks green, a report, and no commit without the owner's command.

### Batch 0 — *decision*: questions and baseline

- Ask the open decisions, write the answers into this plan: done 2026-10-07, under "Answered decisions".
- Pin whether `EditorPlain` is the driver of the `<br>` defect in `docs/EDITORS.md`: one save through Plain on
  `_users.sig` with a known value. If it is, the repair belongs to batch 3; if not, it stays out of this plan.
  Done 2026-10-07 by a probe of the server path in place of the save: it is not. No driver eats them;
  `getTplTextarea()` deletes them on mount for every format but `html`, and only the `markdown` save fails to write
  them back. The mechanism stands in `docs/EDITORS.md`; the repair stays out of this plan and is a step of its own in
  `docs/ROADMAP-2026.md`, with the live save through the browser.
- `npm run ui:before` on the panel screens with editors (`tplconfig`, the system file editor, blocks, template) and on
  one site form with Plain.
  Done 2026-10-07 over the whole manifest of `tools/ui-shots.json`, which gained `admin-tplconfig`, `admin-sysfile`,
  `admin-blockfile` (its `code` state is the editor of the first file block), `admin-template-style` and
  `admin-account-add`.
  The template screen is taken by its style tab: the html tab opens 208 editors on one page. The site edits with
  Toast UI on the stand, so the Plain form of the baseline is the panel's new member form.

### Batch 1 — CodeMirror in the theme

- `entry.js` exports what the shell needs: `Compartment`, `ViewPlugin`, `Decoration`, `MatchDecorator`,
  `syntaxHighlighting`, `classHighlighter`, `openSearchPanel`, `foldAll`, `unfoldAll`, `undo`, `redo`,
  `toggleComment`, `selectAll`, `startCompletion`, `linter`, `lintGutter`, `openLintPanel`, `nextDiagnostic`; `oneDark`
  leaves.
- The token theme of the stand (`.d-cm` rules of `demo.css`) is carried into the admin `theme.css` under the shell
  name; `cm6.css` loses the fixed 400 points; the Tahoma rule on `.cm-content` and the second-editor height hack go.
- Russian phrases for the CodeMirror panels.
- Checks: every code screen above opens with code in the mono face, light and dark; `ui:after`, `npm run ui:gates`.
- Done 2026-10-07. The mount of a code editor carries the shell name `sl-editor` in place of `sl-code-editor` (both
  `editor-mount.html`, both `theme.css`), so the token theme hangs on the class the frame of batch 3 takes over; until
  then the mount is the shell. `--sl-face-mono` and `--sl-editor-max-height` (50vh, the stand's cap) join the admin API
  block; the floor `--sl-editor-height` now stands on `.cm-content` and `.cm-gutter`, as CodeMirror asks, so the gutter
  reaches the bottom of a short file. `cm6.css` keeps only the focus outline. The phrases are every one the bundled
  packages translate, the stand's set and what it left out (the `regexp` label, the control-character title and the
  announcements to a screen reader): twenty-five `_EDITOR_*` constants plus `_ALL`, `_CLOSE`, `_EDITOR_PREV` and
  `_EDITOR_NEXT`; until batch 3 the driver writes them into its start script. The bundle is 240 KB gzip (237 before): the lint, search and language exports outweigh One Dark.
  The stand's colours were held to AA by the regenerated contrast registry: line numbers and comments take
  `--sl-text-muted` in place of `--sl-text-subtle`, tags `--sl-primary-strong`. The same crawl was the first over
  `admin-sysfile` and found the current node of the file tree at 4.09 to 1; by the owner's answer of 2026-10-07 it was
  fixed in this batch, `.sl-fm-node[aria-current]` on `--sl-primary-strong`.

### Batch 2 — the manifest and the split build

- `type` as a list, the one check in `Editor`, the CodeMirror manifest above; tests for both forms of `type` and for
  the editor lists of `getSelect()`.
- The build writes the core and each language as separate files; the runtime loader of batch 3 is their only user.
- `@codemirror/lang-markdown` joins `build/package.json` for the text role.
- Done 2026-10-07. `Editor::checkType()` reads `type` as one string or a list and replaces the three comparisons; the
  fourth, `isValidEditor()` of `admin/index.php`, was a copy of `Editor::isValidEditor()` with its own string comparison
  and is gone, its two callers call the class; the one thing only the copy checked, a manifest `id` equal to its
  directory, moved into `Editor::getManifest()`, so no key reaches a manifest or a driver outside its own folder. By the owner's answer of 2026-10-07 the CodeMirror manifest takes the
  list form as `["code"]` only, with its roles and format unchanged: `content`, the `user` role and the formats of the
  design join in batch 4 together with `ContentDriver`, so no list offers a text editor that renders Plain. Tests in
  `EditorFormatTest`: both forms of `type`, the three lists of `getSelect()`, a file for every language of the manifest.
  `build.mjs` writes ES modules with splitting into `assets/`: `core.js`, `lang-<key>.js` for the seven languages of the
  manifest and `markdown`, each exporting `language`, and shared `chunk-<hash>.js` files whose name follows their
  content, so the week an unversioned address lives in the browser is safe. A language file never imports `core.js`,
  which the build refuses and the test checks: the loader takes the core by its versioned address, and an import of
  the bare one would load a second core. `entry.js` re-exports `core.js` and still builds `cm6.bundle.js`, the asset of
  the current driver, unchanged in size. Gzip, measured: the core 130.5 KB; with a language css 151.8, sql 151.8, xml
  145.0, json 140.4, js 171.9, html 195.8, markdown 210.6, php 223.5, against 239.5 for the bundle on every code
  screen. Each language highlighted its sample in the browser from the split files with no error.

### Batch 3 — the shell and the runtime

- Fragments and `theme.css` rules of the shell in both themes, its tokens in both `base.css`, the names in
  `tools/ui-contract.php`.
- `plugins/system/editor.js` with the frame, status, dirty mark, full screen, wrap, copy, reset, undo and the lazy
  loading of CodeMirror; Plain and CodeMirror as code render through the shell; `EditorCodemirror` keeps no
  JavaScript string.
- `cm6.bundle.js`, its `entry.js` and the IIFE half of `build.mjs` leave once the driver loads through the runtime; the
  readers of `CM6.editors` (`admin-ui.js`, `editor-robots.js`, the editor module of the panel) move to what the runtime
  offers.
- The draft of the tab in `sessionStorage` (answered decision 6): kept while the text differs from the loaded one,
  offered back when the same form opens again in the tab, erased on submit.
- Constants in six locales.
- Tests: a driver test asserting both drivers render the shell fragments and no class string from PHP; the theme
  gates.

### Batch 4 — CodeMirror as a text editor

- `EditorCodemirror` implements `ContentDriver`: markdown, plain and, in the panel, html.
- The manifest becomes the one under "The manifest": `type` `["content", "code"]`, roles `user` and `admin`, the formats
  `plain`, `markdown`, `html` (owner, 2026-10-07: not before the driver serves them).
- It appears in the editor lists of the settings for both roles.

### Batch 5 — file manager

- The shared computation of the file options out of `EditorToastUi`, which calls it as well; the adapter for a
  CodeMirror view and for a textarea; the public entry that opens the window; the folder button and the palette line;
  paste and drop of an image; the emoji panel loaded on its first click (answered decision 5); the window above the
  full screen.
- Checks: upload, link, embed and "Мои файлы" insert into Plain and CodeMirror as text as they insert into Toast UI;
  Toast UI unchanged.

### Batch 6 — capsule and palette

- The capsule of the final face with the commands each engine supports, the same in both themes (answered decision
  4); the palette as a window of the canon with the groups, the fuzzy filter, the recent command and the key
  reference; Ctrl+K only while focus is in an editor.
- The markdown commands, the list continued by Enter and the character counter for Plain and CodeMirror as text.

### Batch 7 — variables and the hint

- The `vars` key through `getCode()` / `getContent()`; the variable list for every engine, the marks in code and the
  completion in CodeMirror, the hint at the caret of Plain; `tplconfig` passes its variables.

### Batch 8 — lint and format

- The checks under "What the page tells the editor", the badge, the gutter marks and the fixes of one click in
  CodeMirror, the badge and the list in Plain; "тег на строку" and "в одну строку" for both.

### Batch 9 — comparison

- The comparison window for every engine, opened from the capsule, the status mark and the palette; the line mode
  above the token limit.

### Batch 10 — preview

- As answered under decisions 1–3: the sample files of the admin theme, the sandboxed frame with a link for PDF, the
  text route through `getTplPreviewContent()`.

### Batch 11 — reference

- `docs/EDITORS.md` describes the manifest list type, the shell, the runtime, the data keys and the engines;
  `docs/TEMPLATES.md` the fragments; `docs/VERSIONS.md` the change. `public/demo/` stays untouched (answered
  decision 7). This file is deleted.
