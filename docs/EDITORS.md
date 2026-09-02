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
├── plain/          # Fallback dumb textarea
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
    
    // Returns the actual <textarea> and initialization scripts specific to a syntax lang
    public function getWidget(string $id, string $name, string $value, string $lang, string $profile): string;
}
```

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

**The namespaces are split and there is no alias.** All three editor plugin
scripts share one object: each opens with `var api = win.SlaedToastUi || {}` and
republishes it, and the `i18n/emoji-*.js` files hang `emojiWords` on it too. So
`SlaedToastUi` stays — it is the editor plugin's own namespace for tags, emoji and
the word lists — and only the file-manager runtime left it. `editor-tags.js`
calls the new namespace explicitly:

```js
if (win.SlaedFileManager) win.SlaedFileManager.addUpload(id, ed, opt || {});
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
`emoji-panel`, `emoji-tab`, `emoji-item`, `emoji-empty` — stay in
`partials/editor-toastui-templates.html` and stay with the driver. Without this
split the window opens on a page with no editor and draws no tile, no row, no
queue card and no message, every one of them silently, because `getTpl()` answers
null and every caller tolerates null.

`data-editor` was deliberately **not** renamed. It is read from the runtime, from
`editor-emoji.js`, from the partial itself, from `getWindowShot()` and from the
insert-options window in `driver.php`; a template-only rename breaks the editor
silently, and the gain is cosmetic — the attribute names the window instance,
which is true in both modes.

## Open Defect: the editor drops every `<br>` on save

**Live, unfixed, and it loses member data.** A value that reaches a driver carrying `<br>` comes back without them. Measured across one save of `_users.block`: 1475 bytes fell to 1387, which is exactly 22 tags of four bytes; `_users.sig` fell 213 to 201, exactly 3. Nothing else in either value changed.

The reach is wider than one field. The account settings page rewrites `sig` and `block` on every save of its shared form, so **every member who opens settings and saves anything at all loses every line break in their signature and their custom menu** — a member editing their e-mail address pays for it with their sidebar menu. Both columns sit in a block shown on every page, so the damage is visible site-wide the moment it happens.

Two wrong guesses are already recorded so the next reader does not spend them again. It is **not** a trim of trailing whitespace: three saves with and without waiting for the mount leave the value byte-identical apart from a one-off trim at the very end of the field. It is **not** lost markdown hard breaks: an earlier repair wrote two trailing spaces in their place and produced a residual gap it could not explain, because the original carried no hard break at all — it carried `<br>`.

The byte arithmetic points at the driver rather than at the parser or the handler, but which of the four `ContentDriver` implementations eats them, and whether it happens on mount or on submit, is not pinned down. Pin it before writing a repair: the last two attempts each fixed a symptom and left the mechanism alone.
## Content Heading Rule

The module title field owns the page `H1`. Inside an article body, authors start with a first-level Markdown section (`# Section`); the rendering call uses heading offset `1`, so it becomes `H2` on the public detail page. Card, comment, block, and forum contexts apply their own deeper offsets. Do not copy the page title into the body and do not use headings only to change font size.
