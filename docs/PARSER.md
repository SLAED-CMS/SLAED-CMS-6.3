# Parser System Documentation

This document describes the `Parser` class, the unified content processing and sanitization engine.

## Architecture Overview

The `Parser` class (`core/classes/parser.php`) is responsible for converting user-provided and administrative content (Markdown and BBCode) into safe HTML. It runs a strict, iterative pipeline to ensure security and predictable formatting.

The parser uses a caching mechanism and internally employs a stateless "stash" token strategy (`$this->stash`). This prevents block-level components and raw HTML from being mangled or double-escaped by inline filters.

## Public Endpoints

The `Parser` should be instantiated and used dynamically where needed, or invoked via the global context if already bootstrapped. The primary public APIs are:

### `Parser::filterContent(string $src, bool $safe, string $mod, int $hoff = 0, string $fmt = ''): string`
The standard rendering pipeline for all modules. It processes Markdown, BBCode, applies safe HTML escaping (if `$safe` is `true`), and **applies module-specific word-replacement rules** (such as automatic censoring or dynamic acronym expansions from `$conf['replace']`).

### `Parser::filterDoc(string $src, bool $safe = true, string $mod = '', int $hoff = 0, string $fmt = ''): string`
The core parser operation. Processes all markup but **skips** the global search-and-replace rules. Use this for static documents, changelogs, search rendering, or anywhere where automatic keyword replacement could corrupt the output.

## Source Format (`$fmt`)

The format names the syntax the stored source was written in. It is an input to rendering, not a security switch:
* **`'plain'`:** no Markdown construct is recognised — no headings, lists, tables, quotes, emphasis, code fences, inline code or `[t](u)` links. A blank line separates paragraphs and every other line ending becomes a `<br>`. The bracket layer (BBCode, smilies, attachments) still runs.
* **anything else, including `''`:** Markdown, which is the behaviour every caller had before the parameter existed.

No stored content passes this argument. Comments and private messages used to carry a `format` column and hand it over here; `docs/CONTENT-CONTRACT-2026.md` removed both columns, because `plain` and the editors are input interfaces rather than storage formats and rendering must not branch on the editor an author typed in. Every caller of stored content therefore omits the argument, and the parameter survives for a caller that knows the syntax of a string it built itself.

## Security Context (`$safe`)

The `$safe` boolean parameter is crucial:
* **`true` (User Content):** The parser strictly escapes all HTML tags injected in the raw source using `htmlspecialchars(..., ENT_QUOTES | ENT_HTML5)`. Indented code blocks are allowed. Unsafe URL protocols are blocked. Raw `[usehtml]` and `[usephp]` BB tags are stripped or ignored. The parser's **own** output is not escaped: the inline BB pairs (`[b]`, `[i]`, `[u]`, `[s]`, `[color]`, `[family]`, `[size]`) render like `[url]` and `[img]` always did, so legacy content stays readable. What the author wrote as a tag stays text; what the parser produced stays markup.
* **`false` (Admin Content):** The parser inherently trusts the source. It preserves manually injected HTML tags. It processes `[usehtml]` blocks literally and executes PHP code inside `[usephp]` blocks via `eval()`.

## Trusted author capability

`$safe` decides how a **stored** source is escaped. It never decides who was allowed to write the source, and passing `false` is not a grant of trust.

`[usehtml]` and `[usephp]` are the two tags that turn stored text into raw markup and into executed code. The capability to author them belongs to the **super administrator alone** and is settled at the write boundary by `filterTrustedTags()` in `core/security.php`: anyone else loses the tokens as the text is stored, in any field, so no render mode can hand the capability back. Every write path reaches that check — typed input through `filterText()` and `filterHtml()` (before the editor branch, because an html editor stores its input almost verbatim), comments through `Comment::filterCommentBody()` and `Comment::updateBody()`, private messages through `Privat::filterMessageText()`.

Comments and private messages never carry the tags at all, whoever writes them: those channels call `filterTrustedTags()` without the capability argument, and they additionally render with `$safe = true`, so the escape hatch is closed twice.

Preview and publication must resolve the capability identically. A preview therefore renders the exact string the save handler would store, which means the add/reply form reads its body with `getVar(..., 'text')`, never `'raw'`. Reading a body raw for preview is what once let an anonymous request drive trusted rendering — and with `[usephp]` restored that defect is remote code execution, not merely stored XSS. It must not reappear.

Because `[usephp]` output is produced per render, no cache may store it. The exclusion lives in the parser itself: the handler sets `$this->vary`, as the `[block=id]` handler does, and `filterContent()` skips the write for any source whose render varied. No caller has to know the rule, and a source that gained the tag stops being stored the moment it is parsed again.

## Supported Markup

The `Parser` supports a hybrid composition of Markdown and SLAED BBCode.

### Markdown Support
- **Headers:** ATX headers (`# h1` through `###### h6`) and Setext headers. Automatically generates `id="slug"` attributes for anchor linking.
- **Lists:** Ordered (`1. Name`) and unordered (`- Name`, `* Name`), including task list checkboxes `[x]`, `[ ]`.
- **Blockquotes:** Standard `> Quote`, including GitHub-flavored Alerts (`> [!NOTE]`, `> [!WARNING]`).
- **Tables:** Full GFM table support with column alignment.
- **Code:** Inline backticks `` `code` `` and triple-backtick fenced blocks (` ``` `). Indented code blocks (4 spaces).
- **Inline:** Markdown links `[text](url)`, images `![alt](url "title")`, and emphasis markers such as `***`, `**`, `*`, and `_`.

### BBCode Support
- **Typography:** `[b]`, `[i]`, `[u]`, `[s]`, `[color=X]`, `[size=N]`, `[family=X]`, `[left]`, `[center]`, `[right]`, `[justify]`.
- **Blocks:** `[quote]`, `[hide]` (visible only to admins/auth users), `[tabs]...[tab]`.
- **Code:** `[code]`, `[php]`, `[code=lang]`.
- **Media & Assets:** `[url]`, `[mail]`, `[img=align]`.
- **Local Attachments:** `[attach=file.png align=X width=Y]` - Resolves to local uploaded files, generates thumbs automatically via GD if needed.
- **Admin/Macros:** `*NN` (Smilies), `[hr]`, `[li]`, `[usehtml]`, `[usephp]` (the last two are super administrator only).

## Authoring Guidelines

- **Content rendering:** Use `$parser->filterContent()` for Markdown/BBCode display. `filterText()` is a security text helper in `core/security.php`, not the content rendering pipeline.
- **Module `$mod` Parameter:** Always try to pass the active module name (`$mod`). It routes local attachment queries to `uploads/<module>/` and binds the module's custom replacement tables appropriately.
- **Trusted vs Untrusted:** Never pass `$safe = false` if the input originates directly from a standard user session or anonymous request.
- **Heading offset:** Keep `0` for a standalone document. Use `1` for an article body below its page `H1`, and `2` for card, comment, block, or forum content below a component heading. Levels are capped at `H6`; IDs and duplicate-ID suffixes remain stable.
- **Raw HTML:** Trusted raw HTML is preserved and is not rewritten with regular expressions. Existing absolute heading tags therefore require a content audit or a separately approved data migration.
