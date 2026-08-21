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
        "partials": ["editor-toastui-files", "editor-toastui-templates"]
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

## Content Heading Rule

The module title field owns the page `H1`. Inside an article body, authors start with a first-level Markdown section (`# Section`); the rendering call uses heading offset `1`, so it becomes `H2` on the public detail page. Card, comment, block, and forum contexts apply their own deeper offsets. Do not copy the page title into the body and do not use headings only to change font size.
