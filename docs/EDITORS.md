# Editor System Documentation

This document describes the pluggable editor system: the manifests and drivers, the one shell Plain Plus and CodeMirror
wear, its runtime, and what a page may tell an editor.

## Architecture Overview

The system uses a central `Editor` class (`core/classes/editor.php`) to choose, load and render an editor for a text or
for code. Editors are discovered from their manifests and chosen by the settings of the site, the panel and the
administrator's own profile.

The primary entry points are:
- `Editor::getContent(array $data)` — renders a text editor; `getTplTextarea()` in `core/helpers.php` is its one caller
  and the door every form of the tree uses
- `Editor::getCode(array $data)` — renders a code editor for the screens of the panel
- `Editor::getSelect(...)` — renders the editor list of a settings screen, filtered by type and role
- `Editor::getFrame(array $data)` — renders one editor in the shell; Plain Plus and CodeMirror call it, the other drivers
  draw their own widget

## Directory Structure

All editor plugins live in their own subdirectories under `public/plugins/editors/`:

```text
plugins/editors/
├── ckeditor/       # CKEditor 5 (HTML, panel only)
├── codemirror/     # CodeMirror 6: the code editor, and a text editor to choose
├── plain/          # Plain Plus, the light textarea and the fallback
├── tinymce/        # TinyMCE (HTML, panel only)
└── toastui/        # Toast UI (Markdown/WYSIWYG)
```

What the editors share lives beside the core script in `public/plugins/system/`: `editor.js` (the runtime of the shell),
`filemanager.js` (the file window), `emoji.js` with its word lists in `emoji/<locale>.js` (the emoji panel). Other
general JS plugins live directly under `plugins/` (e.g. `plugins/htmx/`, `plugins/highlightjs/`).

## The Manifest File (`manifest.json`)

Each editor provides a `manifest.json` in its folder. The system uses this file to discover, validate and instantiate
editors. CodeMirror serves both roles from one plugin:

```json
{
    "id": "codemirror",
    "label": "CodeMirror 6",
    "type": ["content", "code"],
    "driver": "EditorCodemirror",
    "entry": "driver.php",
    "enabled": true,
    "priority": 60,
    "roles": ["user", "admin"],
    "profiles": ["simple", "full"],
    "formats": ["markdown", "plain", "html"],
    "lang": ["php", "html", "css", "js", "json", "sql", "xml", "markdown", "ini", "apache", "robots", "text"]
}
```

### Fields

- `id`: *Required.* Unique identifier, equal to the name of the folder; `Editor::getManifest()` refuses a manifest named
  apart from its directory, so no key reaches a manifest or a driver outside its own folder.
- `label`: *Required.* Human-readable name used in the settings.
- `type`: *Required.* `content` (a text editor), `code` (a code editor), or a list of both. `Editor::checkType()` reads
  one string and a list through the same check; `getCode()`, `checkManifest()` and `getEditorList()` all ask it.
- `driver`: *Required.* The PHP class name implementing the editor's logic.
- `entry`: *Required.* The PHP file to require (usually `driver.php`).
- `enabled`: *Required.* Boolean. If `false`, the editor is ignored.
- `priority`: *Required.* Integer sorting order of the settings lists.
- `roles`: *Required.* `user` and/or `admin`: where the editor may be chosen. A code editor needs `admin`.
- `profiles`: *Required.* `simple` and/or `full`.
- `formats`: *Required.* `plain`, `markdown`, `html`. The first is the one the editor **stores**, `Editor::getFormat()`:
  CodeMirror as a text stores Markdown, as Toast UI does, so a site moves between the two without touching a stored text.
  `html` stays the panel's: `getContent()` gives a member asking for it the editor of the role instead.
- `lang`: *Optional, for a code editor.* The languages it highlights; `getCode()` turns any other into `text`. Without
  the field `getCode()` passes every language through, so a code editor declares it.
- `theme`: *Optional.* Names what the active theme must ship for this editor. `theme.skin` set to `true` makes the
  runtime load `assets/editors/<id>/skin.css` from the current theme and log through `Logger::addSite()` when it is
  missing. `theme.partials` lists partial names the driver renders. Only `toastui` declares it today.

### The editors

| Editor | `type` | Roles | Stores | Widget |
| --- | --- | --- | --- | --- |
| Plain Plus | `content` | user, admin | `plain` | the shell around a textarea, no engine |
| Toast UI | `content` | user, admin | `markdown` | its own, mounted on `fragments/editor-mount.html` |
| CodeMirror | `content`, `code` | user, admin | `markdown` as a text | the shell, CodeMirror mounted over the textarea |
| TinyMCE | `content` | admin | `html` | its own, on `fragments/textarea.html` |
| CKEditor | `content` | admin | `html` | its own, mounted on `fragments/editor-mount.html` |

By priority the site lists `plain toastui codemirror`, the panel `plain toastui codemirror tinymce ckeditor`. `getEditorKey()` takes,
for the panel, the editor of the administrator's own profile, then `$conf['editor']['admin']`, then `plain`; for the
site `$conf['editor']['user']`, then `plain`. Code always takes `$conf['editor']['code']`, `codemirror`. CodeMirror as a
text is offered and never given: nobody receives it without choosing it.

Plain Plus stays light, not primitive: it takes every ability that costs nothing at rest and nothing per key beyond the
key itself. What redraws the whole text on every key — a highlighting mirror, line numbers — stays out; who wants it
chooses CodeMirror. Rejected, so they are not proposed again: CodeMirror as the only engine (it would take the
zero-weight choice away) and Plain Plus as a light code editor (the code screens are the panel's, where CodeMirror is
always wanted).

## Driver Interfaces

Editor driver classes (defined in `entry`) implement `ContentDriver`, `CodeDriver`, or both (defined in
`core/classes/editor.php`).

### ContentDriver

Used for `type` `content`.

```php
interface ContentDriver {
    public function getAssets(string $profile): string;

    public function getWidget(string $id, string $name, string $value, string $profile, array $data = []): string;
}
```

### CodeDriver

Used for `type` `code`; the label is the accessible name of the editable area and is never empty.

```php
interface CodeDriver {
    public function getAssets(string $profile): string;

    public function getWidget(string $id, string $name, string $value, string $lang, string $profile, string $label): string;
}
```

`EditorCodemirror` implements both through one `getWidget()` whose fifth argument tells the role: the data array of
`getContent()` or the profile string of `getCode()`. A text takes the grammar of its format (Markdown on the GitHub base
Toast UI writes, HTML, none for `plain`) and the rows of its field; code takes its language and its label.

A driver hands data to the fragments and writes no class string of its own. `EditorCodemirror` writes no JavaScript
either: its `getAssets()` prints `cm6.css` once and its widget names the core and the grammar the runtime imports.

## The Shell

One component in canon, byte-identical in `templates/admin` and `templates/lite`, as the window canon is. The look is the
stand's final face `public/demo/ed-code-09-final.html`: at rest the text, the format tab and the rail; under the hand the
command capsule beside the tab; the status line under the text.

```
.sl-editor               frame; carries data-dirty, data-invalid, data-lint, data-view; the rail colour follows them
  .sl-editor-row         the format tab and the command capsule
  .sl-editor-card        the draft offer, the engine (textarea or CodeMirror), the variable list, the findings, the preview,
                         the status line
  .sl-editor-status      caret, size, room left, variables used, "изменено" (opens the comparison), lint badge, language
```

- **`fragments/editor-frame.html`** draws one editor: the frame with its data attributes, the tab with the icon of the
  format and the label of the field, the textarea, the status line ending in the name of the format. The textarea stays in the markup under CodeMirror and carries the
  value to the server, so a page without script still submits.
- **`fragments/editor-kit.html`** is a `<template>` printed once per answer: the capsule, the draft offer, the list of
  findings, the preview pane, the variable list, the palette and the comparison window, and the words of the runtime as
  JSON. A page of 208 editors carries one copy.
- **`partials/emoji-panel.html`**, printed once per answer by `Editor::getEmojiPanel()` for every text of Plain Plus and
  CodeMirror and for Toast UI.

The rules stand in both `theme.css` on the tokens of the API block; the component names are declared in
`tools/ui-contract.php`, the values written from the script under `data` there: `--sl-d-editor-floor` (the height of
the field CodeMirror takes the place of, so a form does not jump when it mounts), `--sl-d-hint-x`/`-y`,
`--sl-d-pill-x`/`-y` (the capsule moved by its grip) and `--sl-d-view-height`.

`Editor::getFrame()` gives the frame what its caller and its column allow:

| Attribute | When | Carries |
| --- | --- | --- |
| `data-sl-editor-lang` | always | the format of a text or the language of code |
| `data-sl-editor-code` | code | `basicSetup` and Tab indenting instead of the text setup |
| `data-sl-editor-core`, `-grammar` | CodeMirror | the versioned addresses of `core.js` and one `lang-*.js` |
| `data-sl-editor-files` | a text whose field names an upload place | the options of the file window, from `getEditorFileKit()` |
| `data-sl-editor-room` | a text whose column is not `mediumtext` | the bytes of the column, for the counter |
| `data-sl-editor-vars` | `vars` passed | the variables and their descriptions |
| `data-sl-editor-lint` | `lint` passed and the language is `html` | the check of the template |
| `data-sl-editor-preview` | `preview` passed | the sample values of a template, or the route of a text |

## The Runtime

One script, `plugins/system/editor.js`, delivered by `Editor::getAssetTags()` under the static `$done` of
`getFrame()`, owns the behaviour of every frame: status, dirty mark, the draft, full screen, wrap, copy, reset, undo,
the capsule, the palette, the variables, the check, the comparison, the preview and the file window adapter. It reads
its words from the kit, never from a string of its own. Each frame registers with `window.SlaedEditors.own()`, so an
htmx swap releases it. About 21 KB gzip, shared by Plain Plus and CodeMirror.

`window.SlaedEditor` is what the scripts of a screen call by the id of a textarea: `getText(id)`, `setText(id, text)`,
`isDirty(id)` and `getEditor(id)`, the adapter. The robots button, the leave check of the file editor and the forum's
"Лично" (`data-sl-editor-insert`) go through it.

- **The text.** CodeMirror writes every change back into the textarea, so a form, htmx or a reader of the field sees the
  text without a hook. A whole-text replacement raises `input` in both engines, so a save bar notices it.
- **Dirty and the draft.** A frame is dirty while its text differs from the loaded one. Half a second after the last
  key the runtime keeps the text in `sessionStorage` of the tab, under a key of the page, the field name and its place
  among fields of that name, and offers it back when the same form opens again with another text. A submit takes the
  text as the loaded one and erases the draft; a form reset takes the default back as the loaded one. `sessionStorage`
  and not `localStorage`, because a shared computer would keep the text for the next person.
- **A required text under CodeMirror** that is empty marks the frame `data-invalid`, says so in the note and takes the
  focus, since the browser cannot point at a hidden field.
- **Keys.** Tab moves on in a text and indents only in code; CodeMirror as a text indents by Ctrl+] and Ctrl+[. Esc leaves the full
  screen unless a window the frame does not stand in is open.

### Loading CodeMirror

`build/build.mjs` writes ES modules with splitting into `assets/`: `core.js`, `lang-<key>.js` for each language of the
manifest and `markdown`, each exporting `language`, and shared `chunk-<hash>.js` files named after their content. A
language file never imports `core.js`, which the build refuses and `EditorFormatTest` checks: the runtime imports the
core by its versioned address, and an import of the bare one would load a second core. The runtime imports the core and
the one grammar of a frame when the frame comes within 200 points of the screen or its textarea takes focus, so a page
of text loads no PHP or SQL grammar and a code screen one language.

Gzip, measured 2026-10-09, the core with its chunks: 133.2 KB; with a language apache 133.7, ini 133.6, robots 133.6,
json 143.1, xml 147.7, css 154.5, sql 154.6, js 174.7, html 198.5, markdown 213.4, php 226.3. Toast UI with its locale,
emoji words and scripts is about 285 KB.

CodeMirror is painted by the theme: `classHighlighter` writes `tok-*` classes, both `theme.css` colour them, the scheme
switches the code with the page. A text is set in the face of the page, code in `--sl-face-mono`. The phrases of the
CodeMirror panels are `Editor::PHRASES`, constants of the six locales.

## What The Page Tells The Editor

A caller passes, through the data of `getCode()` / `getContent()`, what the page knows. `Editor::getPageData()` reads
it into the private `$page`, which `getFrame()` takes and empties, so the interfaces keep their signatures and the next
frame starts without it.

| Key | Meaning |
| --- | --- |
| `vars` | a map of `[word]` to its description; turns on the variable list, the hint and the marks in CodeMirror, and with `lint` the unknown-variable check. A key that is not `[word]` is dropped |
| `lint` | `true` turns on the check of an `html` frame; other languages ignore it |
| `preview` | `'text'` for the route, or `['vals' => [...], 'open' => url]`, the sample values of a template; absent means no preview |

`getTplTextarea()` also passes `mod` (the owner of the upload place and of the preview), `store` (the column the text is
saved to, `getEditorRoomData()`) and `room`, and `'preview' => 'text'` for every field. `tplconfig` of
`admin/modules/uploads.php` is the caller of `vars`, `lint` and the sample preview, with the `_UPLOADS_*` constants as
the descriptions. The template screen of the theme passes no `lint`: a whole template file branches its tags in
`{% if %}` and would show false errors.

## Engines

| Ability | Plain Plus | CodeMirror as text | CodeMirror as code |
| --- | --- | --- | --- |
| frame, rail, capsule, status, full screen, wrap, copy, reset, undo, palette, comparison, draft | yes | yes | yes |
| preview | yes | yes | yes |
| file window (`SlaedFileManager.addUpload`), emoji | yes, through the adapter | yes, through the adapter | no |
| variables, hint | when `vars` is passed | when `vars` is passed | when `vars` is passed |
| markdown commands, list continued by Enter | on a `markdown` field | yes | no |
| character counter | under a limited column | under a limited column | no |
| lint, "тег на строку", "в одну строку" | `html` with `lint`: badge, list with fixes | no | badge, list with fixes, marks and fixes in the text |
| highlighting, search panel, multiple carets, folding | no | markdown | the language |

### Capsule and palette

The capsule of `editor-kit.html` stands in groups (`sl-editor-grp`), each command marked with what it needs
(`data-sl-editor-need`: `cm`, `code`, `text`, `markdown`, `files`, `vars`, `lint`, `preview`); the runtime drops what the
frame cannot serve and a group left empty. It keeps to one line no wider than `--sl-capsule-max-width` and scrolls what
does not fit; under 900 points the folds and the two format commands leave it (`sl-editor-wide`) and under 560 the group
of preview, comparison, copy and reset (`sl-editor-mid`), every command staying in the palette.
A member in the lite theme gets the same capsule as the panel.

The palette is a `dialog.sl-modal` cloned from the kit on its first call, one per page: groups Правка, Формат, Вставка,
Вид and the variables, a filter by letters in their order, the last command raised in its group, the keys of the engine
under the list. Ctrl+K opens it only from a frame; Esc closes it at once, even over a typed search. A command runs once
the window has closed and given the focus back.

### Markdown commands and the counter

Bold, italic, code, link, list and quote appear where the field stores Markdown: CodeMirror as text always, Plain Plus
only on a field whose format is `markdown`, which no caller gives today, since Plain Plus stores `plain` and the parser
reads no Markdown there. Ctrl+B and Ctrl+I work only in such a text. A textarea continues a list, a numbered list, a task
and a quote by Enter and ends it on an empty item; CodeMirror does that through its grammar.

What is left is counted in bytes against the column of `getEditorRoomData()`, the same number the save checks and the
meter of the file window reads; it is shown for a `text` column (64 KB) and not for `mediumtext`, and turns to
`_ETEXTLONG` once the text no longer fits.

### Variables and the check

One list, `.sl-editor-hint` from the kit, serves the hint at the caret of a textarea after `[` (the caret is measured
only while a `[…` is typed) and every engine under the button of the capsule; Ctrl+Space opens it. It ticks a variable
the text already uses and inserts as one step of undo. CodeMirror marks the known variables (`sl-editor-var`) and
completes them through its language data, one source for the life of the editor.

The check, `getIssues()`, is one for every engine: unknown variable (naming the near one), tag left open, closing tag
without an opening one, `img` without `alt`, `_blank` without `noopener`, a value without quotes, a template without `[src]` when `vars`
holds it. A textarea runs it after the pause of the draft, CodeMirror through `linter()` with its gutter; both write the
badge, the rail (`data-lint`) and the list, whose rows select the place and whose buttons apply the fix. A fix is found
again in the text as it stands before it is written. "Тег на строку" and "в одну строку" need the check.

### Comparison

A `dialog.sl-modal.sl-modal-lg` cloned from the kit: the views "Вместе" and "Рядом" (kept for the page), the count of
units gone and new, "Вернуть было" and "Оставить стало". It opens from the capsule, the palette and the mark "изменено",
is computed only when it opens and written as text nodes with `del` and `ins`. Units are variables, words, runs of space
and single signs; the shared head and tail are cut off, the middle is aligned by its longest common subsequence while
the table holds at most four million cells, else by lines and a changed run by its units. It is checked before saving
and moves nothing in the form.

### Preview

The eye of the capsule shows the pane under the text, beside it on the full screen (stacked below 900 points); the frame
carries `data-view`. The pane renders only while shown, again after the pause of the draft, and drops an older answer.
The page in the frame is written by `srcdoc` with the stylesheets and the colour mode of the page it stands on; the frame
is `sandbox="allow-same-origin"`, so no script of a text runs and the runtime may measure the height into
`--sl-d-view-height`.

- **A template** is filled on the client with the values of `getTemplateSample()` in `admin/modules/uploads.php`: the
  samples `sample.webp`, `sample.wav`, `sample.webm` and `sample.pdf` in `templates/admin/assets/samples/`, the width and
  height of the upload settings, the file name as title, `#` for an archive. The preview works on a fresh installation,
  the same every time, and shows no upload of anybody. A PDF is never embedded: the head of the pane links the sample
  (`_EDITOR_SAMPLE`).
- **A text** is posted with a token to `getEditorPreview()` in `core/helpers.php`, through `go=1` on the site and `go=5`
  in the panel, so the format is the one of the role that drew the field. The route takes a POST only (405 otherwise),
  runs the save filter and `checkEditorTextRoom()` of the declared store, draws the text through
  `getTplPreviewContent()` with the attachments of an unsaved text of its owner, `[getUploadOwner($mod), 0]`, and ends
  the answer.

### Strings

Every word of the shell is a constant in the six locales under `.rules/constants.md`; `Editor::getKitData()` hands them
to the kit, the runtime reads them from there.

## Accessible Name

`Editor::getContent()` settles the name once through `getNameData()` — `labelledby` when the row has a caption,
`aria-label` (the label, or `_TEXT`) when it has none — and hands both down. Plain Plus writes them onto its textarea;
CodeMirror writes them onto its textarea too, and the runtime gives its editable area the same name and spell check
through `EditorView.contentAttributes` when it mounts. `Editor::getCode()` hands every code driver a name: the `label`
key of its data, or `_TEXT`. The other drivers are described in `docs/TEMPLATES.md`, "Form Row Contract".

## Usage

A form renders a text through `getTplTextarea()`, which chooses the editor and its format, mounts the stored value
(see "Line Breaks on Mount") and declares where the text is stored:

```php
$body = getTplTextarea([
    'id' => 'body',
    'name' => 'body',
    'value' => $content,
    'mod' => 'forum',
    'store' => 'forum.body',
    'labelledby' => $ids['label'],
]);
```

A screen of the panel renders code:

```php
echo Editor::getCode([
    'id' => 'source',
    'name' => 'source',
    'label' => _FILE.': '.$path,
    'text' => $codeContent,
    'lang' => 'php',
]);
```

## The File Manager Left The Plugin

The file window is no longer an editor ability. It is built by
`getFileManagerWindow(array $opt): string` in `core/helpers.php` from the rule of
one upload place — `docs/ARCHITECTURE.md`, *Upload Place Boundary* — and drawn from
`partials/file-manager.html`, which is the theme's markup in both themes. The
options, the window, the gallery and the insert options are worked out once by
`getEditorFileKit()` for the three drivers that offer it; Toast UI adds only its
toolbar words. A form row calls `getFileManagerField()`, which wraps the same
helper in field mode.

**The runtime lives in `plugins/system/filemanager.js`**, beside `slaed.js`, and
publishes `window.SlaedFileManager` with four entries:

| Entry | Use |
|---|---|
| `addUpload(id, ed, opt)` | the window bound to an editor instance; installs the paste hook and the toolbar button |
| `deleteUpload(id)` | releases that binding when the editor leaves the page |
| `addField(id, node, opt)` | the window bound to a form row's box; installs neither, because both return early on a missing editor |
| `addPanel(id)` | opens or closes the window of an editor, from a button outside the vendor toolbar |

Delivery is `getFileManagerWindow()` under a `static $done` — the pattern
`Editor::getThemeSkin()` already uses — and not `$conf['global']['script_f']`,
which would load it on every page of the site.

**Plain Plus and CodeMirror as text bind through an adapter.** `getPort()` of
`editor.js` answers the five calls the window makes of Toast UI — `focus()`,
`insertText()`, `exec('addImage')`, `getMarkdown()`, `addHook('addImageBlobHook')`
— over a textarea or a CodeMirror view; `addCommand()` and `insertToolbarItem()`
are Toast UI's toolbar and the window skips them when they are missing. The
binding happens on first use, so the order the scripts come in does not matter;
`addPanel()` opens the window from the folder button
of the capsule (`bi-folder`, `_EUPLOAD`) or the palette line "Файлы: загрузить и
вставить". A picture takes the format of the field: Markdown for `markdown`, the
`[img]` tag otherwise. A paste or a drop of an image goes to the window as Toast
UI's hook sends it; a paste that also carries `text/rtf`, as Word and Excel give
it, stays text. Code gets no file window. The window is non-modal and stands above
the full screen of the shell by itself: `setWindowFront()` in `slaed.js` lifts it
from layer 10050 up, above `--sl-z-modal`.

**The namespaces are split and there is no alias.** `SlaedToastUi` is the editor
plugin's own namespace for its tags; the file-manager runtime left it first and the
emoji panel followed, as `SlaedEmoji` in `plugins/system/emoji.js` with its word
lists in `plugins/system/emoji/<locale>.js`, because Plain Plus and CodeMirror as
text open it too, on every field and loaded on the first press. `editor-tags.js`
calls both namespaces explicitly:

```js
if (win.SlaedFileManager) win.SlaedFileManager.addUpload(id, ed, opt || {});
win.SlaedEmoji.setPanel(id, ed, button);
```

An alias would have hidden the coupling rather than cut it, and the failure it
hides is silent: a condition that is simply false leaves the editor without its
file button and writes nothing to the console. The runtime keeps its own editor
map, written in `addUpload()` and never in `addField()`, with local `getEditor()`
and `insertText()` over it; `editor-tags.js` keeps its own copies for its own use.

**The null editor is the field mode and it stays silent.** A field place has no
editor in the map, so `getEditor()` answers null and the four editor-only paths —
`addSource`, `addAttach`, `addImage`, `setRoom` — disable themselves through
guards that were already there. That is designed behaviour, not leftover code.

**The draw templates travelled with the runtime.** `api.getTpl()` finds
`<template data-tpl="…">` inside the container named by `opt.tpl`. The eleven the
runtime needs — `fm-act`, `fm-busy`, `fm-dial`, `fm-job`, `fm-pick`, `fm-prop`,
`fm-row`, `fm-tile`, `fm-why`, `msg-info`, `msg-warn` — live in
`partials/file-manager-templates.html`, delivered by `getFileManagerWindow()`
under the same `static $done` as the script. The four the emoji panel needs —
`emoji-panel`, `emoji-tab`, `emoji-item`, `emoji-empty` — live in
`partials/emoji-panel.html`, printed once per answer by `Editor::getEmojiPanel()`
with the words of the panel and the addresses its script loads by. Without this
split the window opens on a page with no editor and draws no tile, no row, no
queue card and no message, every one of them silently, because `getTpl()` answers
null and every caller tolerates null.

`data-editor` was deliberately **not** renamed. It is read from the runtime, from
`emoji.js`, from the partial itself, from `getWindowShot()` and from the
insert-options window of `getEditorFileKit()`; a template-only rename breaks the editor
silently, and the gain is cosmetic — the attribute names the window instance,
which is true in both modes.

## Line Breaks on Mount

A stored value can carry `<br>`: the `plain` save writes one before every line end through `nl2br()`, the `html` editors write them as markup, and the values migrated from 6.2 hold them: on the stand 8453 of 15261 forum posts, 73 of 829 signatures and 123 of 785 custom menus. `getTplTextarea()` turns each one into what the save of the active format turns back into a break, before any driver sees the value; the `html` format mounts the value as it is. CodeMirror as text mounts through the same function.

| Format | A stored `<br>`, with the line end after it if there is one, mounts as | What the save writes |
| --- | --- | --- |
| `plain` | a line end | `<br>` and the line end again, through `nl2br()` of `filterHtml()` |
| `markdown` | a Markdown hard break: two spaces and a line end | the two spaces and the line end, which every render of the parser, the escaped one included, turns into `<br>` |

A second mount and save leaves the value byte for byte as the first save wrote it. `<br />` comes back as `<br>` in `plain`; a `<br>` with no line end after it gains one; a `<br>` at the very end of the field is lost to the `trim()` of the `text` filter, as any trailing line end is. In `markdown` a line of nothing but a `<br>` becomes a line of two spaces, which Markdown reads as an empty line: the lines around it render as two paragraphs. A `<br>` a member typed as text is stored escaped, `&lt;br&gt;`, and is not touched. `tests/Unit/EditorBreakTest.php` drives each case through the shipped mount, the browser submit and the save with `tests/Support/break_probe.php`.

Toast UI starts in its markdown mode and hands the text back byte for byte; measured in the browser on 2026-10-07 with `_users.sig` and `_users.block` through one save of the account settings, `block` went from 1475 bytes with 22 tags to 1431 with 22 hard breaks and a second save changed nothing. Its WYSIWYG mode, one click away in the editor, rewrites the text on the way back: it reads every line as a paragraph and writes a hard break back as nothing (`a` and two spaces, a line end and `b` come back `ab`), and a `<br>` as a bare line end. A member who switches modes loses breaks there, whatever the server mounts.

`replace_break()`, which deletes every `<br>`, is no part of the mount; it keeps its other callers: the private messages hand the text to forward and the quote to reply in one hidden field the script copies into the editor, stripped the old way; they render in the `breaks` format, where the line end that remains still breaks, so only a `<br>` with no line end after it is lost there.

## Content Heading Rule

The module title field owns the page `H1`. Inside an article body, authors start with a first-level Markdown section (`# Section`); the rendering call uses heading offset `1`, so it becomes `H2` on the public detail page. Card, comment, block, and forum contexts apply their own deeper offsets. Do not copy the page title into the body and do not use headings only to change font size.

## Tests

- `tests/Unit/EditorFormatTest.php` — both forms of `type`, the editor lists, the split build, the shell rendered by both
  drivers with no class string from PHP, the accessible name, the runtime loading, the capsule and palette, the counter,
  the variables, the check, the comparison and the preview with its route.
- `tests/Unit/EditorWindowTest.php` — the file window and the emoji panel of every text engine, their words in six
  locales, the skins byte-identical in both themes.
- `tests/Unit/EditorBreakTest.php` — the line breaks on mount; `tests/Unit/EditorRoomTest.php` — the room of a column.
