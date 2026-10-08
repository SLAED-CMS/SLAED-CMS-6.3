# Editor System Documentation

This document describes the pluggable editor system.

## Architecture Overview

The system uses a central `Editor` class (`core/classes/editor.php`) to manage and initialize different editors for content and code. Editors are loaded dynamically based on user and administrator configuration.

The primary entry points are:
- `Editor::getContent(array $data)` — renders a WYSIWYG or plain text editor
- `Editor::getCode(array $data)` — renders a code/syntax editor
- `Editor::getSelect(...)` — renders a `<select>` dropdown for admin panels

## Directory Structure

All editor plugins must be securely encapsulated in their own subdirectories under `plugins/editors/`:

```text
plugins/editors/
├── ckeditor/       # CKEditor (HTML)
├── codemirror/     # CodeMirror (Syntax/Code)
├── plain/          # Plain Plus, the light textarea and the fallback
├── tinymce/        # TinyMCE (HTML)
└── toastui/        # ToastUI (Markdown/WYSIWYG)
```

Other general JS plugins live directly under `plugins/` (e.g. `plugins/htmx/`, `plugins/highlightjs/`).

## The Manifest File (`manifest.json`)

Each editor must provide a `manifest.json` file in its folder. The system uses this file to discover, validate, and instantiate editors.

```json
{
    "id": "toastui",
    "label": "TOAST UI Markdown 3",
    "type": "content",
    "driver": "EditorToastUi",
    "entry": "driver.php",
    "enabled": true,
    "priority": 50,
    "roles": ["user", "admin"],
    "profiles": ["simple", "full"],
    "formats": ["markdown"],
    "theme": {
        "skin": true,
        "partials": ["file-manager", "file-manager-templates", "editor-toastui-templates"]
    }
}
```

### Fields

- `id`: *Required.* Unique folder and identifier name.
- `label`: *Required.* Human-readable name used in admin settings.
- `type`: *Required.* `content` (WYSIWYG/Text) or `code` (Syntax Highlighting).
- `driver`: *Required.* The PHP class name implementing the editor's logic.
- `entry`: *Required.* The PHP file to require (usually `driver.php`).
- `enabled`: *Required.* Boolean. If `false`, the editor is ignored.
- `priority`: *Required.* Integer sorting order for dropdown panels.
- `roles`: *Required.* Array containing `user` and/or `admin`. Determines where it can be used.
- `profiles`: *Required.* Array containing `simple` and/or `full`. Represents configurations.
- `formats`: *Required.* Array containing values such as `plain`, `html`, `markdown`, or code-related output formats. The current manifest validation expects this field for every editor.
- `lang`: *Required for code editors.* Array of supported languages (e.g., `["php", "html", "css", "js", "json", "sql", "xml", "text"]`). This is additional code-editor metadata, not a replacement for `formats`.
- `theme`: *Optional.* Names what the active theme must ship for this editor. `theme.skin` set to `true` makes the runtime load `assets/editors/<id>/skin.css` from the current theme and log through `Logger::addSite()` when it is missing. `theme.partials` lists partial names the driver renders, so the window markup of an editor stays theme-owned. Only `toastui` declares it today. Independently of that block, every driver renders its mount point through the shared `fragments/editor-mount.html` and hides the original textarea with the `hidden` attribute rather than an inline style, so no driver spells markup of its own.

## Driver Interfaces

Editor driver classes (defined in `entry`) must implement either `ContentDriver` or `CodeDriver` (defined in `core/classes/editor.php`).

### ContentDriver

Used for `type: "content"`.

```php
interface ContentDriver {
    // Returns HTML for <script> and <link> tags
    public function getAssets(string $profile): string;
    
    // Returns the actual <textarea> and initialization scripts
    public function getWidget(string $id, string $name, string $value, string $profile, array $data = []): string;
}
```

### CodeDriver

Used for `type: "code"`.

```php
interface CodeDriver {
    // Returns HTML for <script> and <link> tags
    public function getAssets(string $profile): string;
    
    // Returns the actual <textarea> and initialization scripts specific to a syntax lang;
    // $label is the accessible name the driver puts on its editable area, never empty
    public function getWidget(string $id, string $name, string $value, string $lang, string $profile, string $label): string;
}
```

A code editor has no caption of its own, so `Editor::getCode()` hands every driver a name: the `label`
key of its data, or the generic `_TEXT` when a caller gives none. CodeMirror writes it as `aria-label` of
its content through `EditorView.contentAttributes`, so a screen reader announces the file it edits.

## Usage

Use the `Editor` class in module templates/PHP wrappers.

### Content Editor

```php
echo Editor::getContent([
    'id' => 'body',
    'name' => 'body',
    'value' => $content,
    // Optional overrides: 'role' => 'admin', 'profile' => 'full'
]);
```

### Code Editor

```php
echo Editor::getCode([
    'id' => 'source',
    'name' => 'source',
    'label' => _FILE.': '.$path,
    'text' => $codeContent,
    'lang' => 'php'
]);
```

## The File Manager Left The Plugin

The file window is no longer an editor ability. It is built by
`getFileManagerWindow(array $opt): string` in `core/helpers.php` from the rule of
one upload place — `docs/ARCHITECTURE.md`, *Upload Place Boundary* — and drawn from
`partials/file-manager.html`, which is the theme's markup in both themes. The
editor driver calls that helper instead of assembling markup of its own; a form
row calls `getFileManagerField()`, which wraps the same helper in field mode.

**The runtime lives in `plugins/system/filemanager.js`**, beside `slaed.js`, and
publishes `window.SlaedFileManager` with two entries:

| Entry | Use |
|---|---|
| `addUpload(id, ed, opt)` | the window bound to an editor instance; installs the paste hook and the toolbar button |
| `addField(id, node, opt)` | the window bound to a form row's box; installs neither, because both return early on a missing editor |

It was delivered by one line in `plugins/editors/toastui/driver.php`, so a page
carrying no Toast UI editor never received it and the window had no behaviour
there. Delivery is now `getFileManagerWindow()` under a `static $done` — the
pattern `Editor::getThemeSkin()` already uses — and not `$conf['global']['script_f']`,
which would load it on every page of the site.

**The namespaces are split and there is no alias.** `SlaedToastUi` is the editor
plugin's own namespace for its tags; the file-manager runtime left it first and the
emoji panel followed, as `SlaedEmoji` in `plugins/system/emoji.js` with its word
lists in `plugins/system/emoji/<locale>.js`, because Plain Plus and CodeMirror as
text open it too. `editor-tags.js` calls both namespaces explicitly:

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

A stored value can carry `<br>`: the `plain` save writes one before every line end through `nl2br()`, the `html` editors write them as markup, and the values migrated from 6.2 hold them: on the stand 8453 of 15261 forum posts, 73 of 829 signatures and 123 of 785 custom menus. `getTplTextarea()` turns each one into what the save of the active format turns back into a break, before any driver sees the value; the `html` format mounts the value as it is.

| Format | A stored `<br>`, with the line end after it if there is one, mounts as | What the save writes |
| --- | --- | --- |
| `plain` | a line end | `<br>` and the line end again, through `nl2br()` of `filterHtml()` |
| `markdown` | a Markdown hard break: two spaces and a line end | the two spaces and the line end, which every render of the parser, the escaped one included, turns into `<br>` |

A second mount and save leaves the value byte for byte as the first save wrote it. `<br />` comes back as `<br>` in `plain`; a `<br>` with no line end after it gains one; a `<br>` at the very end of the field is lost to the `trim()` of the `text` filter, as any trailing line end is. In `markdown` a line of nothing but a `<br>` becomes a line of two spaces, which Markdown reads as an empty line: the lines around it render as two paragraphs. A `<br>` a member typed as text is stored escaped, `&lt;br&gt;`, and is not touched. `tests/Unit/EditorBreakTest.php` drives each case through the shipped mount, the browser submit and the save with `tests/Support/break_probe.php`.

Toast UI starts in its markdown mode and hands the text back byte for byte; measured in the browser on 2026-10-07 with `_users.sig` and `_users.block` through one save of the account settings, `block` went from 1475 bytes with 22 tags to 1431 with 22 hard breaks and a second save changed nothing. Its WYSIWYG mode, one click away in the editor, rewrites the text on the way back: it reads every line as a paragraph and writes a hard break back as nothing (`a` and two spaces, a line end and `b` come back `ab`), and a `<br>` as a bare line end. A member who switches modes loses breaks there, whatever the server mounts.

`replace_break()`, which deletes every `<br>`, is no part of the mount; it keeps its other callers: the private messages hand the text to forward and the quote to reply in one hidden field the script copies into the editor, stripped the old way; they render in the `breaks` format, where the line end that remains still breaks, so only a `<br>` with no line end after it is lost there.

## Content Heading Rule

The module title field owns the page `H1`. Inside an article body, authors start with a first-level Markdown section (`# Section`); the rendering call uses heading offset `1`, so it becomes `H2` on the public detail page. Card, comment, block, and forum contexts apply their own deeper offsets. Do not copy the page title into the body and do not use headings only to change font size.
