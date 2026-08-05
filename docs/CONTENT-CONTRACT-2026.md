# Content contract — decision and handover

Status: decided 2026-08-05, not started. Written to resume in a fresh session.

This note carries two independent things. The first is a security finding that must
be fixed regardless of anything else. The second is the design decision about the
`format` column. They do not depend on each other and can be worked in either order,
but the security fix is the one with a clock on it.

---

## 1. Security finding — unauthenticated RCE in the content preview

**Severity: critical. Verified on the dev stand on 2026-08-05 with a harmless payload,
not reasoned about.** The database was not touched and the test artifact was removed.

### What was proven

An anonymous POST, with no CSRF token, to `index.php?name=news&op=add` carrying
`hometext=[usephp]echo 6*7;[/usephp]` returned, inside the rendered preview block:

```html
<div class="sl-preview-section sl-preview-field-primary">42</div>
```

`42` is the result of `eval()` run on the server against a string taken from the body
of an unauthenticated request. This is remote code execution, not XSS.

### The chain, by file

1. `add()` reads the field as `'raw'`, so the write-time text filter never runs on it —
   `modules/news/index.php:410`.
2. `getTplPreviewContent()` renders it through `filterContent($texta, false, $mod)`,
   i.e. **`safe = false`** — `core/helpers.php:189`.
3. Under `safe = false` the parser reaches the `[usephp]` branch and runs
   `eval(htmlspecialchars_decode(...))` — `core/classes/parser.php:364` (the block is
   `353-372`; `[usehtml]` right above it emits raw HTML the same way).

### Why every existing guard missed it

- The three places that strip `[usehtml]`/`[usephp]` for a non-admin only run **on
  write**: `core/security.php:928`, `core/classes/comment.php:583`,
  `core/classes/privat.php:178`. The preview writes nothing and passes them by.
- The global request blacklist looks for HTML and XSS shapes. `[usephp]echo 6*7;[/usephp]`
  has no `<` and no `javascript:`, so nothing matches. **`storage/logs/hack.log` got
  no entry** — its last line is from 2026-07-28.
- The route checks no token: `add()` only re-renders the form.

### Radius

Anonymous submission is on in the shipped configs (`addquest = 1`) for `news`, `faq`,
`links`, `pages`. The same preview call — `getTplPreviewContent()` with a `'raw'` read
and `safe = false` — also stands in forum, files, media, help, jokes, auto_links and in
every module's `admin/index.php`. **Only `news` was tested.** The others are the same
code shape but were not exercised; do not claim them fixed or vulnerable without a check.

### Remediation

- **Minimal patch:** `getTplPreviewContent()` must not render with `safe = false`. The
  preview must go through the same contract as publication, and `[usehtml]`/`[usephp]`
  must be stripped for a non-admin before the parser sees them.
- **Root fix (preferred, and it is the same fix the design decision below wants):** the
  right to open `[usehtml]`/`[usephp]` is decided **inside `Parser`** by the author's
  privilege, once, instead of being re-decided by a `safe` literal at each of ~20 call
  sites. One of those literals is already wrong; that is the defect class, not this one
  call site.
- After fixing, add a regression test: an anonymous preview POST carrying `[usephp]`
  must render it as inert text, and `hack.log`/`error_php.log` must stay clean.

---

## 2. Decision on the `format` column: **without format**

The column should not exist — in any table. Your own long-standing rule, "raw HTML only
inside `[usehtml]`", replaces it and does so more precisely.

### Why

`format` names a *syntax*, and a syntax is a property of the tool the text was typed
with, not of the content. That is exactly how it leaked into storage: the editor choice
reached the table and started changing the meaning of bytes already written. What
actually differs per row is not syntax but **trust**, and trust is already expressed —
by the `[usehtml]` marker the author places explicitly in the text, gated by a write
privilege. That is finer-grained than any column and costs zero schema.

### The standard, for the whole system

1. **One storage — Markdown source.** Everywhere: comments, private messages, forum,
   visitor submissions, news, pages, blocks.
2. **One syntax.** Editors are input interfaces only. The set offered is derived from the
   editor manifests, not from a global setting — this closes "what if the admin switches
   the user's editor" by construction rather than by convention. Manifests today:
   `plain` and `toastui` are the only `user`-role editors; `ckeditor`, `tinymce`,
   `codemirror` are `admin`-only. A user cannot be handed an HTML editor.
3. **Raw HTML only inside `[usehtml]`, PHP only inside `[usephp]`.**
4. **The right to open those blocks is checked in the parser**, once, by the author's
   privilege — not by the call site, the module, or an argument flag.
5. **`$safe` stops being the caller's decision.** Today it is a literal at ~20 sites, and
   one of them is the RCE above.

Point 4 is not decoration: the RCE is the price of a rule enforced in scattered places —
three sites remember `[usephp]`, a fourth forgot.

### What it costs (state it, do not hide it)

- The user has one syntax forever — no plain/markdown toggle for other people's content.
- The "no Markdown at all" mode disappears; code blocks replace it.
- The right to `[usehtml]` becomes a real security boundary and must be held in one place,
  seriously.

### What this means for today's tree

Private-message step 11 already moves in this direction: it stores source and renders
safe. The `format` column in it is a transitional detail that dies once the syntax is
fixed. So the path is:

1. Commit steps 11 and 12 (they are in the working tree, uncommitted). Rolling back here
   would *increase* the split, because comments already shipped on the `format` contract
   (`Comment` writes `format`, `comment-migrate` is mandatory from 6.3).
2. Fix the syntax invariant in the manifests so no panel switch can mint a new epoch.
3. Normalise the ~2850 `plain` rows in `_comment` and `_privat` and **drop the column
   from both**. Render is byte-identical: markdown hard breaks reproduce the `plain`
   line-ending behaviour.
4. Class B tables (forum/help/files/news/pages/faq/whois/jokes — ~17 671 rows) are **not**
   converted wholesale. Move a module to source only when it is already open for another
   reason; forum last or never.

### What would reverse the decision

Only one thing: if "choose the syntax for your own post" is a product feature you intend
to sell — different posts in different markup, importing foreign content in its native
syntax, supporting wiki-text or AsciiDoc alongside Markdown. Then the column is
mandatory and this becomes the Drupal/MediaWiki path. In twenty years nobody asked for it.

### Where the rule is already written (so it is not re-derived from scratch)

- `admin/info/config/ru.md:212-229` — "raw HTML in content is rarely needed, so there is
  no separate raw mode", keep structural BB markers outside `[usehtml]`, do not widen the
  user editor's rights to store raw HTML.
- `docs/PARSER.md:32-33` — what `safe = true`/`false` does with `[usehtml]`.
- The canonical `.rules/*` do **not** yet carry this rule. If it is meant to be binding,
  that is where it belongs, not only in the help docs.

---

## Suggested order for the next session

1. Fix the RCE first (section 1) — it is unauthenticated and live on the stand.
2. Then take the content-contract work (section 2) as its own change, root fix in
   `Parser` preferred since it settles both the RCE class and the design at once.
