# Parser System Documentation

This document describes the `Parser` class, the unified content processing and sanitization engine.

## Architecture Overview

The `Parser` class (`core/classes/parser.php`) is responsible for converting user-provided and administrative content (Markdown with the SLAED tags) into safe HTML. It runs a strict, iterative pipeline to ensure security and predictable formatting.

The parser uses a caching mechanism and internally employs a stateless "stash" token strategy (`$this->stash`). This prevents block-level components and raw HTML from being mangled or double-escaped by inline filters.

### The standard

Markdown is read by **CommonMark 0.31.2** with the extensions of **GFM 0.29-gfm**: tables, task list items, strikethrough, extended autolinks and the filter of disallowed raw HTML. The engine is the parser's own, written after the reference implementations: `getBlockTree()` reads the block structure one line at a time as commonmark.js does, `getInlineTree()` reads the inline content with the delimiter and bracket stacks of the specification, and the tree is rendered through the fragments of the theme. The examples of both specifications are fixtures (`tests/Fixtures/spec/`), and `ParserFixturesTest::filterDocMeetsTheSpec()` runs them in both modes as the spec runner of cmark-gfm does: the CommonMark examples with the tag filter and the extended autolinks off (`Parser::$gfmoff`, a switch of the test alone), the GFM ones with them on. `tests/Fixtures/spec/failing.json` lists the examples that fail and is empty; a listed example that passes fails the test, so the list can only shrink.

The engine is the parser's own rather than a library (`league/commonmark` was weighed and declined): the parser keeps no runtime dependency, and the fragments of the theme, the stash and the cache stay as they are. Measured on the stand on 2026-10-26, the 28344 renderable texts of the migrated production database render in 8 to 10 s, the time of the regex parser it replaced, the slowest text in 43 ms, without one warning. Parentheses in a link destination are bounded at 32 levels, as cmark bounds them and the specification allows.

The comparison removes only the markup of the theme: heading ids, the classes of the theme (`sl-*`, `bi`; a class of the author's raw HTML stays), the code figure with its head (its language label stands for `language-*`), the last line break inside a code block, blanks before `<br>`, the broken-image fallback, the cell alignment style, and the disk check of a local image (`Parser::$fileoff`); the expected side loses the final line ending `filterDoc()` trims, and its `./` link is one from the site root. Code is measured in the highlighter mode the release ships, `syntax = 2`; modes 0 and 1 keep what PHP's `highlight_string()` does to a tab. A safe rendering is not measured on raw HTML, which it escapes, nor on a link of a scheme other than `http`, `https` and `mailto`, which it refuses.

On top of the standard stand the SLAED extensions: `==mark==`, the alerts `> [!NOTE]`, `[!TIP]`, `[!IMPORTANT]`, `[!WARNING]`, `[!CAUTION]`, a heading id on every heading, an address `./x` read from the site root, and the bracket layer below.

### The bracket layer

The SLAED tags are the one bracket markup beside Markdown: `[hide]`, `[tabs=N]` with `[tab=…]`, `[attach=…]`, `[usehtml]`, `[usephp]` and `[block=id]`. Any other text in brackets is Markdown or text. The tags are read before Markdown, on a copy in which `filterMask()` hides the brackets and stars that are no tag: code spans, fenced and indented code, link targets and reference definitions, autolinks, raw HTML tags and every character a backslash escapes. The places come from a scan of the real parse, so the layer and the rendering agree on what is code. A raw pair — `[usehtml]`, `[usephp]` — whose opening tag the scan leaves visible is taken back whole, since its content is no Markdown: Markdown code around the opener wins, Markdown read inside the pair does not. A tag becomes a stash token; a line of one token is a leaf of its own. The content of `[hide]` and of a `[tab=…]` is parsed again as a document of its own.

### The parser owns no markup

Every element the parser emits is rendered by the active theme, not concatenated in PHP: paragraphs, headings, rules, lists, tables, code, quotes, callouts, links and images all come from fragments under `templates/<theme>/fragments/`. The parser passes data and semantic flags; escaping happens at the template boundary, so a value handed to a fragment arrives **unescaped** and a value that is already rendered markup travels under a `*_html` key.

Two consequences matter when using the class:

* **A global `$tpl` is required.** `core/system.php` creates it immediately before `$prs`, so any normal request has one. Without it the parser produces text with no markup at all — there is no plain-HTML fallback any more. A standalone or test use must set `$GLOBALS['tpl']` itself, which is what `ParserFixturesTest` does.
* **Rendered output is theme-specific.** `getCachePath()` already carries `getTheme()` and the class file's mtime in its key, so a stored rendering is never served to a different theme or across a change to this file.

One shape is worth knowing because it looks unusual. An element that wraps content — a paragraph, an emphasis, a list item — is rendered once with `\x01` as its content and split there (`getPartPair()`), so a theme never has to spell half a tag and the parser renders the element once per template engine.

## Public Endpoints

The `Parser` should be instantiated and used dynamically where needed, or invoked via the global context if already bootstrapped. The primary public APIs are:

### `Parser::filterContent(string $src, bool $safe, string $mod, int $hoff = 0, string $fmt = '', array $own = [], bool $trust = false): string`
The standard rendering pipeline for all modules. It processes Markdown and the SLAED tags, applies safe HTML escaping (if `$safe` is `true`), and **applies module-specific word-replacement rules** (such as automatic censoring or dynamic acronym expansions from `$conf['replace']`).

### `Parser::filterDoc(string $src, bool $safe = true, string $mod = '', int $hoff = 0, string $fmt = '', array $own = [], bool $trust = false): string`
The core parser operation. Processes all markup but **skips** the global search-and-replace rules. Use this for static documents, changelogs, search rendering, or anywhere where automatic keyword replacement could corrupt the output.

The file context `$own` names the owner and the stored target of the text, `['node', $nid]` for a Node material, `['forum', $id]` for a forum post, `['privat', $id]` for a private message, `['comment', $id]` for a comment on a poll or a profile and `['profile', $uid]` for a signature or an own block; target 0 is an unsaved text, whose attachments take the preview form of an owner that has one (`FileAccess::PREVIEW`). Every `[attach]` takes the address `FileAccess::getFileUrl()` answers for it; without a context the owner is `public`, the direct link of a public folder.

## Source Format (`$fmt`)

The format names the syntax the stored source was written in. It is an input to rendering, not a security switch:
* **`'plain'`:** no Markdown construct is recognised — no headings, lists, tables, quotes, emphasis, code fences, inline code or `[t](u)` links. A blank line separates paragraphs and every other line ending becomes a `<br>`. The bracket layer (the SLAED tags) still runs.
* **anything else, including `''`:** Markdown, which is what a caller that says nothing gets.

No stored content passes this argument, and no table carries a format column: `plain` and the editors are input interfaces rather than storage formats, and rendering must not branch on the editor an author happened to type in. The argument belongs to a caller that knows the syntax of a string it built itself.

## Security Context (`$safe`)

The `$safe` boolean parameter is crucial:
* **`true` (User Content):** No HTML block and no raw inline tag is recognised: what the author typed as a tag is text, escaped with `htmlspecialchars(..., ENT_QUOTES | ENT_HTML5)`. A link or image address without a scheme passes, one with a scheme only as `http`, `https` or `mailto`; any other becomes `#`. Raw `[usehtml]` and `[usephp]` tags stay text with their backslashes. The parser's **own** output is not escaped: what the author wrote as a tag stays text, what the parser produced stays markup.
* **`false` (Admin Content):** The parser trusts the source: raw HTML blocks and inline tags pass by the rules of CommonMark, except the tags GFM disallows (`title`, `textarea`, `style`, `xmp`, `iframe`, `noembed`, `noframes`, `script`, `plaintext`), whose `<` is escaped. It processes `[usehtml]` blocks literally and executes PHP code inside `[usephp]` blocks via `eval()`, the one way to markup the tag filter would stop.
* **Both modes:** `data:`, `javascript:` and `vbscript:` addresses become `#`, a Markdown image of `data:` passes only as an `EMBEDIMG` raster, and indented code blocks are read.

`$trust` narrows trust to the tags. With `$safe = true` and `$trust = true` the source is rendered safe exactly as above, except that the content of `[usehtml]` becomes raw markup and the content of `[usephp]` runs and `[block=id]` prints its free block; an indented line inside those tags is not read as a code block. Raw HTML and unsafe URLs around the tags stay escaped. With `$safe = false` the argument changes nothing. A Node text renders this way: a tag the super administrator added to a text somebody else wrote trusts only what it encloses, never the foreign markup around it.

## Trusted author capability

`$safe` decides how a **stored** source is escaped. It never decides who was allowed to write the source, and passing `false` is not a grant of trust.

`[usehtml]` and `[usephp]` are the two tags that turn stored text into raw markup and into executed code. The capability to author them belongs to the **super administrator alone** and is settled at the write boundary by `filterTrustedTags()` in `core/security.php`: anyone else loses the tokens as the text is stored, in any field, so no render mode can hand the capability back. Every write path reaches that check — every stored Markdown text (Node, comments, private messages, forum posts, signatures, own blocks, blocks, admin messages, newsletters, the contact text) through `filterSourceText()`, which stores the text as typed, line ends as LF, and the other typed fields through `filterText()` and `filterHtml()`.

Comments and private messages never carry the tags at all, whoever writes them: those channels call `filterSourceText()` without the capability argument, and they render with `$safe = true` and no trust, so the escape hatch is closed twice. Every other stored Markdown text renders as a Node text does, `$safe = true` with `$trust = true`: raw HTML of a trusted writer passes only inside `[usehtml]`.

Preview and publication must resolve the capability identically. A preview therefore renders the exact string the save handler would store, which means the add/reply form and the preview route read the body through the same `filterSourceText()` call as the save, never bare `'raw'`. Reading a body raw for preview is what once let an anonymous request drive trusted rendering — and with `[usephp]` restored that defect is remote code execution, not merely stored XSS. It must not reappear.

Because `[usephp]` output is produced per render, no cache may store it. The exclusion lives in the parser itself: the handler sets `$this->vary`, as the `[block=id]` handler does, and `filterContent()` skips the write for any source whose render varied. No caller has to know the rule, and a source that gained the tag stops being stored the moment it is parsed again.

## Supported Markup

The `Parser` reads Markdown and the SLAED tags. Emoji are text: the editor inserts them as Unicode characters.

### Markdown Support
Everything CommonMark 0.31.2 defines, with the GFM extensions named under "The standard":
- **Headings:** ATX (`# h1` through `###### h6`) and Setext. Every heading gets an `id="slug"` for anchor linking.
- **Lists:** Ordered (`1.`, `1)`, any start number) and unordered (`-`, `+`, `*`), tight and loose, with GFM task list checkboxes `[x]`, `[ ]`.
- **Blockquotes:** `> Quote`, with lazy continuation, and the alerts `> [!NOTE]` … `> [!CAUTION]`.
- **Tables:** GFM tables with column alignment and `\|` inside a cell.
- **Code:** Code spans of any backtick run, fenced blocks of backticks or tildes with an info string, indented code blocks.
- **Links and images:** inline, full, collapsed and shortcut reference links with their definitions, autolinks `<…>`, GFM extended autolinks (`www.`, `http://`, `https://`, `ftp://`, e-mail addresses). A bare address becomes a link at render; no writer rewrites a stored text into links.
- **Inline:** emphasis and strong emphasis by the flanking rules, `~~strikethrough~~`, `==mark==`, entity and numeric references, backslash escapes, hard and soft line breaks.

### SLAED tags
A tag is `[name]`, `[/name]`, `[name=value]` or `[name key=value …]` with no space after `[` or before `]`, the name
in any case and the attributes those the tag defines; anything else in brackets is text (`[usehtml ]`, `[ img=left … ]`,
a bare `[attach]`). `filterTrustedTags()` in `core/security.php` and the renderer of `[usehtml]` and `[usephp]` read
this one grammar, so no form renders that the write filter let through.
- **Blocks:** `[hide]…[/hide]` (its content visible only to administrators and signed-in users), `[tabs=N]` with
  `[tab=Title]…[/tab]` inside.
- **Local Attachments:** `[attach=file.png align=X title=Y]`, optionally `width=W height=H`, then `rel=R`, and last `size=full` - Resolves to local uploaded files, generates thumbs automatically via GD if needed; `size=full` renders an image through the `full` template of `config/filetype.php` at its own size and makes no thumb.
- **Trusted:** `[usehtml]…[/usehtml]` and `[usephp]…[/usephp]` (super administrator only), `[block=id]` (a free block,
  rendered outside the panel with `$safe = false` or with `$trust`; `filterTrustedTags()` keeps all three from every
  other writer).

`getImgText()` in `core/system.php` takes the meta image of a text from its first `[attach=…]`, else from its first
Markdown image `![…](…)`.

### What is no markup
- **Bracket placeholders:** the mail templates (`[text]`, `[login]`, `[pass]`, …), the meta templates (`[site]`,
  `[img]`, `[url]`, …), the templates of `config/filetype.php` (`[src]`, `[tsrc]`, …) and `[theme]` are replaced by
  their code and are no tag.
- **Text fields:** titles, the profile fields `occ`, `origin`, `interest` and `website`, the title of a poll (through
  `htmlspecialchars()`) and its answers (a `|` list of text) never pass the parser; brackets stored there print as
  written.

### Backslash literals

In every mode a backslash turns the ASCII punctuation character after it into a literal. In Markdown this is the
escape of CommonMark, which the inline parse reads where the specification does and nowhere else: not in code, an
autolink or a raw HTML tag. The bracket layer sees the same: `filterMask()` hides an escaped bracket or star from it,
so `\[hide]` stays text. The raw pairs `[usephp]` and `[usehtml]` keep every backslash as typed, also when a safe rendering does not honour `[usephp]` or `[usehtml]`. In the plain format `filterLiteral()`
replaces each pair with a token before the bracket layer, and when the regular expression engine gives up on the raw
regions every pair becomes a literal, so a failed match hides markup instead of exposing it. `getAttachList()` reads
the source through the same rules, so an escaped bracket names no attachment. Feed relies on this rule: it prefixes
every punctuation character of received text with a backslash, so a feed body renders as plain text (docs/NODE.md,
Feed).

## Texts of the BB era

The texts of SLAED 6.2 and of the 8.0 development builds were stored as BB. `getMigrateMarkdown()` in
`public/update.php` turns each text the parser renders into Markdown once, in the text unit of the update; titles and
the fields above keep what they hold. The target is the standard above, not the look of the old rendering.

| BB | Markdown | Rule |
|---|---|---|
| `*01`–`*18` | emoji | the table below |
| `[b]`, `[i]`, `[s]` | `**…**`, `*…*`, `~~…~~` | white space moves outside the markers; one pair per paragraph |
| `[url]X`, `[url=X]Y` | `<X>`, `[Y](X)` | `<X>` only for an absolute X with a scheme, a relative one takes `[X](X)`; space, `<` and `>` of X percent-encoded, in `(X)` also `(`, `)` and `\`; an empty label takes X; `www.` gains `https://`; an empty or unsafe X leaves the label as text |
| `[mail]X`, `[mail=X]Y` | `<X>`, `[Y](mailto:X)` | an invalid address stays text |
| `[img]`, `[img alt=A]`, `[img=…]`, `[img=… alt=A]` | `![A](src)` | the alignment is dropped |
| `[hr]` | `***` on its own line, blank lines around | `---` under a text line would be a setext heading |
| `[li]` | the character `• ` | |
| `[code]`, `[code=lang]`, `[php]` | a fence, with `lang` or `php` | inline it becomes a code span; the fence outgrows any backtick run inside |
| `[quote]` | `> ` lines | a nested quote becomes `> >` |
| `[u]`, `[color=…]`, `[size=…]`, `[family=…]`, `[justify]`, `[left]`, `[center]`, `[right]` | the text alone | no Markdown equivalent, the formatting goes |
| ed2k links | the text alone | |

| Code | Emoji | Code | Emoji | Code | Emoji |
|---|---|---|---|---|---|
| `*01` | 😕 | `*07` | 😎 | `*13` | 😉 |
| `*02` | 😛 | `*08` | 🤓 | `*14` | 😌 |
| `*03` | 😏 | `*09` | 🙄 | `*15` | 😠 |
| `*04` | 🙂 | `*10` | 😮 | `*16` | 😢 |
| `*05` | 😃 | `*11` | 🙁 | `*17` | 😳 |
| `*06` | 😄 | `*12` | 😳 | `*18` | 😱 |

The SLAED tags stay as they are, `[attach=…]` with its `align=`, which the theme renders through
`sl-attach-left/right/center`. The rules of the conversion:

- **Tokens** follow the grammar of "SLAED tags", and the converter also reads the forms the old parser rendered:
  `[code=]` is `[code]`, and white space around the value of `[url=…]`, `[mail=…]`, `[color=…]` and `[family=…]` and
  around `alt=` of `[img]` is dropped. Only balanced pairs convert; an unpaired or crossed tag is text.
- **Text runs** of a text written as BB are escaped by the rules of CommonMark: a backslash before the ASCII
  punctuation that would open markup, before a marker at the start of a line and before an entity, so the result renders
  the text the author typed. The entities of the old storage are decoded first, and a `<` they decode to is escaped
  too, so `&lt;font&gt;` never turns into markup. A text written as Markdown keeps its runs, its fences and its code
  spans, and only its BB tags convert; which texts are which the update decides by their origin.
- **Code keeps its characters:** a fence or a code span holds the stored breaks and entities decoded, since CommonMark
  reads no entity inside code. `[usehtml]` and `[usephp]` are copied as written; their renderer keeps decoding them.
- **The content** of `[hide]`, `[tab=…]` and `[quote]` converts like any text; that of `[usehtml]` and `[usephp]` does
  not.
- **A stored `<br>`** becomes a hard break, a backslash before the line end, or a bare line end where the line before or
  after it is empty; in a text written as BB a run of empty lines shrinks to one.
- **Local addresses:** `getMigrateLocal()` puts `./` before a bare relative address of an image or a link, which the
  parser reads from the site root; `getMigrateHtml()` writes the raw HTML of a 6.2 text as Markdown, and a trusted text
  keeps its `table`, `div`, `iframe`, `object`, `form`, `script` and `style` as markup inside `[usehtml]`.
- **A text converts once:** a second pass would escape the escapes. `tests/Unit/UpdateTextTest.php` holds the rules.

## Authoring Guidelines

- **Content rendering:** Use `$parser->filterContent()` for Markdown display. `filterText()` is a security text helper in `core/security.php`, not the content rendering pipeline.
- **Module `$mod` Parameter:** Always try to pass the active module name (`$mod`). It routes local attachment queries to `uploads/<module>/` and binds the module's custom replacement tables appropriately.
- **Trusted vs Untrusted:** Never pass `$safe = false` if the input originates directly from a standard user session or anonymous request.
- **Heading offset:** Keep `0` for a standalone document. Use `1` for an article body below its page `H1`, and `2` for card, comment, block, or forum content below a component heading. Levels are capped at `H6`; IDs and duplicate-ID suffixes remain stable.
- **Raw HTML:** Trusted raw HTML is preserved and is not rewritten with regular expressions. Existing absolute heading tags therefore require a content audit or a separately approved data migration.
