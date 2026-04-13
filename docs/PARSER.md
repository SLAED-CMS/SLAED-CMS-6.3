# Parser System Documentation

This document describes the `Parser` class, the unified content processing and sanitization engine replacing legacy `filterMarkdown()` and `filterReplaceText()` functions.

## Architecture Overview

The `Parser` class (`core/classes/parser.php`) is responsible for converting user-provided and administrative content (Markdown and BBCode) into safe HTML. It runs a strict, iterative pipeline to ensure security and predictable formatting.

The parser uses a caching mechanism and internally employs a stateless "stash" token strategy (`$this->stash`). This prevents block-level components and raw HTML from being mangled or double-escaped by inline filters.

## Public Endpoints

The `Parser` should be instantiated and used dynamically where needed, or invoked via the global context if already bootstrapped. The primary public APIs are:

### `Parser::filterContent(string $src, bool $safe, string $mod): string`
The standard rendering pipeline for all modules. It processes Markdown, BBCode, applies safe HTML escaping (if `$safe` is `true`), and **applies module-specific word-replacement rules** (such as automatic censoring or dynamic acronym expansions from `$conf['replace']`).

### `Parser::filterDoc(string $src, bool $safe = true, string $mod = ''): string`
The core parser operation. Processes all markup but **skips** the global search-and-replace rules. Use this for static documents, changelogs, search rendering, or anywhere where automatic keyword replacement could corrupt the output.

## Security Context (`$safe`)

The `$safe` boolean parameter is crucial:
* **`true` (User Content):** The parser strictly escapes all HTML tags injected in the raw source using `htmlspecialchars(..., ENT_QUOTES | ENT_HTML5)`. Indented code blocks are allowed. Unsafe URL protocols are blocked. Raw `[usephp]` and `[usehtml]` BB tags are stripped or ignored.
* **`false` (Admin Content):** The parser inherently trusts the source. It preserves manually injected HTML tags. It processes `[usehtml]` blocks literally and executes PHP code inside `[usephp]` blocks via `eval()`.

## Supported Markup

The `Parser` supports a hybrid composition of Markdown and legacy SLAED BBCode to preserve backward compatibility while enabling modern authoring workflows.

### Markdown Support
- **Headers:** ATX headers (`# h1` through `###### h6`) and Setext headers. Automatically generates `id="slug"` attributes for anchor linking.
- **Lists:** Ordered (`1. Name`) and unordered (`- Name`, `* Name`), including task list checkboxes `[x]`, `[ ]`.
- **Blockquotes:** Standard `> Quote`, including GitHub-flavored Alerts (`> [!NOTE]`, `> [!WARNING]`).
- **Tables:** Full GFM table support with column alignment.
- **Code:** Inline backticks `` `code` `` and triple-backtick fenced blocks (` ``` `). Indented code blocks (4 spaces).
- **Inline:** Markdown links `[text](url)` and images `![alt](url "title")`. (Note: bold/italic relies on either BBCode or modern CSS defaults, check your theme).

### BBCode Legacy Support
- **Typography:** `[b]`, `[i]`, `[u]`, `[s]`, `[color=X]`, `[size=N]`, `[family=X]`, `[left]`, `[center]`, `[right]`, `[justify]`.
- **Blocks:** `[quote]`, `[hide]` (visible only to admins/auth users), `[tabs]...[tab]`.
- **Code:** `[code]`, `[php]`, `[code=lang]`.
- **Media & Assets:** `[url]`, `[mail]`, `[img=align]`.
- **Local Attachments:** `[attach=file.png align=X width=Y]` - Resolves to local uploaded files, generates thumbs automatically via GD if needed.
- **Admin/Macros:** `*NN` (Smilies), `[hr]`, `[li]`, `[usehtml]`, `[usephp]`.

## Authoring Guidelines

- **New Code:** Stop using `filterText()` and `filterMarkdown()`. Route all text display through `$parser->filterContent()`.
- **Module `$mod` Parameter:** Always try to pass the active module name (`$mod`). It routes local attachment queries to `uploads/<module>/` and binds the module's custom replacement tables appropriately.
- **Trusted vs Untrusted:** Never pass `$safe = false` if the input originates directly from a standard user session or anonymous request.
