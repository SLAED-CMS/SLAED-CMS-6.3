# Quick Edit 2026

Work plan for one in-place edit of a text, shared by every place that offers it:
a Node material, a forum post, a comment. The reader presses "Quick edit" in the
item's dial, the rendered text turns into the editor in the same spot, and a save
puts the rendered text back without leaving the page.

Status: batch 0 landed on 2026-09-30, batches 1 to 4 on 2026-10-04; the lasting
part is in `docs/ARCHITECTURE.md` ("Quick edit"), `docs/NODE.md` and
`docs/VERSIONS.md`, and this file is kept by the owner until it is committed,
then deleted. The plan was
audited against the code on 2026-10-03 and the owner's decisions of that day are
folded in. Batches run in order; update this line as they land. The last batch
moves what lasts into the permanent reference and deletes this file.

No line numbers for this tree anywhere in this document on purpose: every
reference names the function, the file or the constant it points at.

## What exists today

Two inline edits exist, both built on one helper, and Node has none.

| | Forum post | Comment |
| --- | --- | --- |
| Button | dial item in `view()` of `modules/forum/index.php`, token in `hx_headers` | dial item in `getCommentView()` of `core/user.php`, token declared on the comment element |
| Route | `go=1&op=updatePost`, GET and POST | `go=1&op=updateComment`, GET and POST |
| Handler | `updatePost()` in `core/user.php` | `updateComment()` in `core/system.php` → `Comment::updateComment()` |
| Form | `getTplAjaxTextarea()` in `core/helpers.php` | the same |
| Right | `checkForumRight()` over `getForumPlace()`: moderator of the stored category, or signed-in author with `pedit` in an open topic | `is_moder()`, or author within `comments.edit` seconds |
| Write | bare `UPDATE _forum`, no guard, no transaction | write guard + transaction + `Cache::addEpoch(true)` |
| Concurrency | none, last write wins | none, last write wins |
| Render | `filterContent(..., false, mod, 2)`, trusted, wrapped in `filterTextHighlight()` on the page | `filterContent(..., true, mod, 2, 'breaks')`, safe |
| Edited mark | `inline-badge` `is_topic_edit` inside the post's `date`, built inline in `view()`, shown only to a moderator or with `forum.ledit` | `inline-badge` `is_comment_edit` in the `edited` key of `fragments/comment.html` |

Node writes only through `NodeService::updateNode()`. It is for moderators only
(`getNodeHead()`), takes the full `NodeInput` and checks the expected
`version`. The public module has no edit op. The moderator dial
`getNodeModerDial()` offers only a link to the admin form, plus the status moves,
and it serves the panel list as well as the public page.

The editor is global: `getEditorKey()` picks one of five drivers in
`plugins/editors/` (`ckeditor`, `codemirror`, `plain`, `tinymce`, `toastui`), and
each driver with assets dedups them with its own per-request `static $done`.

### Defects the plan closes

These were checked against the code, not taken from the audit alone.

1. **Closed in batch 0.** **The forum right came from the request, not from the post.** `updatePost()`
   read `pedit`/`pmod` of the category named by the request `cid` and never
   compared that category with the one the post is stored in.
2. **Closed in batch 0.** **The button and the handler disagreed.** The forum button showed for any truthy
   topic status, while the handler asked for `status > 2`.
3. **Closed in batches 1 and 2.** **A refusal loses the typed text.** Both handlers answer a refusal (rules,
   room, expired window) with an alert that replaces the editor, with HTTP 200.
4. **Closed for comments and forum posts in batches 1 and 2.** **Last write wins.** Two editors of one item overwrite each other silently.
5. **Closed in batch 1.** **Cancel is a round trip.** "Back" asks the server to render the body again.
   For a comment it does this by calling `updateComment($id, '')`.
6. **Closed in batch 2.** **The forum bumps the page cache on every call.** `index.php` calls
   `Cache::addEpoch()` after every `updatePost`, including a form load or a
   refusal. This runs outside the write guard.
7. **Closed in batches 1 and 2.** **The "edited" mark goes stale.** Only the body region is swapped. The
   forum's `etime` badge and the comment's edited badge sit outside it and keep
   their old state until the page reloads.
8. **Closed in batch 1, confirmed in the browser first.** **Editor assets are emitted per response.** Every driver dedups with a
   per-request static, so every fragment carries the engine scripts again; for
   Toast UI `editor-tags.js` adds its global listeners again. The Toast UI inline
   init returns silently when `window.toastui` is not loaded yet. On a page
   without a comment form this can leave the textarea hidden, which is not yet
   confirmed in the browser. `SlaedToastUi` keeps every registered editor after
   its node is swapped away.
9. **Node needs its full form for a typo.** A moderator fixing one word leaves
   the page for `admin.php?name=node&op=edit`.

## Design

### One subject protocol, three kinds

A subject is one editable text of one item, named by three closed values:

- `kind`: `node`, `forum` or `comment`
- `id`: the item
- `field`: `body` for a forum post and a comment; `intro` or `body` for a node,
  limited to the texts the type carries

Node takes part only for types without an extension (`$type->ext === ''`, owner,
2026-10-03), the same filter `getNodeModeType()` applies. The closed action set
of the extensions (`comment`, `rate`, `favorite`, `asset`, `report`) has no
`edit`, so an extension could not refuse a quick edit, and the `sync` extension
owns the body of its materials: its `updateNodeData()` refuses a changed body,
and a `null` data there means "only the state changed". Types with an extension
keep the full form; the `NodeExtension` contract is not touched.

The node `title` is not a quick-edit field (owner, 2026-10-03). The address of a
material is built from it (`getViewHref()` → `getSeoUrl()` with `title`), and so
are the `h1`, the document `<title>`, the breadcrumbs and the page meta; a
region swap cannot update any of them. It is also a plain string of at most 100
characters (`checkText()`), not an editor text. It stays in the full form.

A new class `QuickEdit` in `core/classes/quick.php` owns the protocol and nothing
else (one short lowercase word, as the naming rule asks of a PHP filename). It
receives one adapter per kind as closures, the way `getRatingService()` builds
`Rating` from its read and write closures. Its factory is `getQuickService()` in
`core/system.php`. Each adapter answers three questions:

- **source**: may the actor edit this subject, what is the stored text, what is
  its stamp, and which editor store (`nodes.body`, `forum.body`,
  `comment.body`, …) and module it belongs to. Every one of these comes from
  the stored row, never from the request. This is the rule
  `CommentTrustBoundaryTest` already holds for comments, extended to every kind.
- **write**: store the text at the expected stamp through the kind's own
  guarded writer, in the order of "The write under the lock", answering one of
  the closed result codes. The existing write helpers do not meet that contract
  yet, and the batch that wires a kind changes them:
  - a writer must tell a closed write guard (`blocked`) from a failed statement
    (`storage`). `Comment::setWriteBegin()` answers `false` for both a refused
    guard and a failed `BEGIN`; `NodeService::setNodeWrite()` turns a refused
    guard into `NodeException::STORAGE`, and `NodeException` has no code for it.
    Comment splits the two answers; Node gets a new code. `setNodeWrite()` is
    shared by every Node write, so the new code reaches every caller, and the
    maps that turn codes into a message or a status send an unknown code to
    their `default` arm, which in the panel reads as a bad input
    (`_NODE_BAD`). Every map learns the new code in the same batch: the four
    `match` blocks of `admin/modules/categories.php`, the one in
    `core/admin.php` (node type save), `getNodeStatus()` (503) with the message
    map beside it in `modules/node/index.php`, and the panel's fault texts in
    `modules/node/admin/index.php`, where `STORAGE` with the config journal
    reads `_CONFIG_PENDING`. The other uses of `NodeException::STORAGE`
    (`core/system.php`, `core/user.php`, `core/classes/comment.php`,
    `modules/voting/admin/index.php`) only throw it and stay as they are; a
    search for `NodeException::STORAGE` finds any consumer added since.
  - an equal text must not bump the epoch. `Comment::setWriteDone()` already
    takes `$moved = false` for that. `setNodeWrite()` bumps after every
    finished work closure, including one that wrote nothing, so it learns from
    the closure whether a write happened.
  - `Comment::updateComment()` answers both failures today as
    `saved => false` and gains a code.
- **view**: render the stored subject exactly as the page renders it, together
  with its edited mark (see "The swapped region" below).

### The stamp

Optimistic locking needs no schema change.

- A node uses its `version` column. Closed form `/^[1-9][0-9]{0,9}$/D`.
- A forum post and a comment have no version column. Their stamp is
  `sha1(body."\0".etime)` / `sha1(body."\0".edited)` of the stored row, closed
  form `/^[a-f0-9]{40}$/D`. Hashing the body as well as the time is deliberate:
  an edit through the admin form (`Comment::updateBody()`, the forum `send()`)
  changes the body and therefore the stamp, so it is caught too.

The editor carries the stamp it was opened with. "Equal" compares the
**canonical stored form**: the kind's own filter (`filterCommentBody()`,
`filterHtml()`, `filterTrustedTags()`) runs on the incoming text first. The raw
editor text never equals the stored text for a non-HTML editor, because
`getTplTextarea()` hands it over through `getDecodedText(replace_break())`.

### The write under the lock

Everything a save decides from the stored row is checked again **under the
lock, inside the transaction**, not only the stamp. A read before the guard, as
`Comment::updateComment()` does today for the right and the window, and as
`updatePost()` does through `getForumPlace()`, leaves a gap between the check and
the write, and the stamp does not cover that gap: closing a topic or moving a
post changes neither the body nor `etime`. The rights check before the
transaction stays as a cheap early refusal; the one that counts is the second.

The order is fixed, for every kind. What depends on the text alone may run
before the transaction; what depends on the stored row is decided under the
lock:

1. before the transaction: canonicalize the text and run the kind's rules and
   room check (`rules`); for Node also `checkNodeFiles()` under the directory
   lock of the type, which `setNodeWrite()` takes before the guard and `BEGIN`
2. lock the rows and read them again: the item exists and is not deleted
   (`unavailable`)
3. the right and the window from the locked rows (`denied`)
4. **equal text**: the canonical text against the locked row; answer `saved`
   with the current stamp, without a write
5. **stale stamp**: answer `conflict`
6. write

The comment rules on an edit carry no time-based check (`checkRules()` with
`$isnew = false` skips the flood window), so step 1 answers a repetition the same
way as the first save.

Equality comes before the stamp on purpose. A save whose response was lost has
already written; its repetition carries the old stamp and the same text, and
must answer `saved`, not `conflict`. A save of the text another editor already
stored answers `saved` the same way. Step 4 changes nothing: no version step, no
edit time, no status move, no epoch bump.

The lock order per kind:

- **Comment**: the comment row `FOR UPDATE` and nothing else. An edit reads and
  writes no row of the material a Node comment hangs on, so it takes no lock
  there; with a single lock there is no order to keep against
  `Comment::setStatus()`, which locks the material before the comment.
- **Forum**: the topic row before the post row, both `FOR UPDATE` (for a first
  post they are one row), which is the order the forum rating already takes on
  the topic row. The category's `pedit`/`pmod` are read inside the transaction
  but not locked: they are panel settings, not item state.
- **Node**: the type row (`getTypeLock()`), then the material `FOR UPDATE`, as
  `updateNode()` already does, with `version = :ver` in the update.

### Two routes, strict like the rating endpoint

`getRatingView()` is the model: closed regex forms for every field, a token,
one HTTP status per refusal, nothing read from where it does not belong.

- `go=1&op=getQuickEdit`, **GET only**: answers the editor fragment for one
  subject, or the refusal status.
- `go=1&op=updateQuickEdit`, **POST only**: `kind`, `id`, `field`, `stamp` and
  `text` in the body. It answers the rendered region on success.

The router's shared token gate in `index.php` (the `is_numeric($go)` branch)
must not answer for these two ops. It reads the token from the request
parameters before the header and refuses with `die()` of an alert at HTTP 200,
and htmx swaps a 200 into the target: an expired session would replace the open
editor with the alert and lose the typed text, which is defect 3 again. So both
ops join the router's `$public` list next to `getRatingView`, which already
checks its own token, and the handlers do it themselves:

- the method first: 405 with `Allow: GET` or `Allow: POST`
- the token **only** from the `X-CSRF-TOKEN` header, never from a parameter, so
  no token ever appears in a URL or in a log; a missing or failing token
  (`checkSiteToken()`) answers **403** with `_TOKENMISS`, and the script keeps
  the editor and the text like any other refusal
- then the closed forms, the right, and the write

They do not join the router's POST-only list: that list answers its 405 before
the handler, which is harmless, but one place for the method check is clearer.
`QuickEditTest` covers a save with an expired token: 403, editor in place.

| Code | Status | Meaning |
| --- | --- | --- |
| saved | 200 | the rendered region with its fresh edited mark out of band (a Node author edit that moves the material to moderation answers as fixed in "Decisions") |
| invalid | 422 | a field outside its closed form |
| denied | 403 | no right, the edit window has closed, or the token is missing or stale |
| unavailable | 404 | the item is gone or deleted |
| conflict | 409 | the stamp is stale; the body carries the current text rendered and the fresh stamp |
| rules | 422 | rules or room refused the text; the body carries the messages |
| storage | 500 | the write failed |
| blocked | 503 | the write guard is closed |

The stamp lives in the editor form only, as a hidden field `stamp` that
`getQuickEdit` fills from the stored row; the rendered region never carries one.
Opening the editor again fetches a fresh one, so a 200 answer needs none. A 409
answer is not swapped by htmx: it carries the current text rendered inside an
element with `data-sl-quick-stamp`, and the script copies that stamp into the
hidden field before it asks the conflict question. No response header is
needed.

The cache epoch: each kind's writer bumps it inside its own write guard, and
only when it wrote. In the router line
`if (in_array($op, ['updatePost', 'updateVotingResult'], true)) Cache::addEpoch();`
only `'updatePost'` goes; **the line stays for `updateVotingResult`**, whose
cache invalidation hangs on it. `CommentTransportTest` asserts that line
verbatim and follows.

### The swapped region

The edited mark sits outside the body in both existing kinds, so swapping the
body alone cannot refresh it (defect 7). The rule, the same for every kind: the
region is the body wrapper marked `data-sl-quick`, and a save answers the body
plus the edited mark as an out-of-band element with `hx-swap-oob="outerHTML"`.

- The existing `swap-oob` fragment cannot carry it: it renders an empty element
  with `hx-swap-oob="delete"` and serves only the removal of comment rows.
- An out-of-band swap replaces an element that is already there. Today the mark
  is printed only when the item was edited (`{{{ edited }}}` in
  `fragments/comment.html`, the badge appended to `$date` in the forum), so a
  first edit would have nothing to replace. The mark therefore gets a wrapper
  with an id derived from the subject that the page always prints, empty while
  the item is unedited or the reader may not see the mark.

Per kind:

- **Comment**: the wrapper stands where `{{{ edited }}}` stands in
  `fragments/comment.html`; the view adapter renders the body exactly as
  `getCommentView()` does and the wrapper out of band.
- **Forum**: the post is built inline in `view()`, so there is no function to
  share. Batch 2 extracts the body and the edited badge of one post out of
  `view()` into one function used by both the page and the adapter. The badge
  keeps its rule, shown to a moderator or with `forum.ledit`, inside the
  always-printed wrapper. The page wraps the
  body in `filterTextHighlight($word)`; the adapter renders without a search
  word, and Cancel restores the highlighted HTML it kept.
- **Node**: the view partials of every mode a type without an extension can
  take wrap `intro_html` and `body_html` in addressable regions; the `support`
  mode belongs to the `support` extension alone (`filterViewRule()`) and stays
  as it is. The view adapter takes the two keys from the public
  `NodeView::getNodeView($type, $node, 'view')`, the call the page makes, so the
  heading offset and the attachment links match; no new method of `NodeView` is
  needed. Node has no edited mark in the view today, so nothing goes out of band.

### One fragment, one script

- `fragments/quick-edit.html` in the `lite` theme holds the form, the editor
  slot, Save and Cancel. The `admin` theme gets no copy: no quick-edit subject is
  rendered there (it has no `comment.html` or `forum-post.html` either), and
  `tools/ui-contract.php` asks no parity of `fragments`. It replaces the
  `form-wrap` built in PHP by `getTplAjaxTextarea()`, and no class string is
  built in PHP any more. The editor itself still comes from `getTplTextarea()`.
  The form declares the page token in its own `hx-headers`, as
  `getTplAjaxTextarea()` does today: a comment would inherit it from its
  element, but a forum post and a Node view have no element that declares one,
  and the save reads the token from the header alone.
- `EditorRoomTest::everyCallSiteNamesAStorageTheTableCarries` scans every
  `getTplTextarea(` call for a **literal** `'store' => '...'` and fails on any
  call without one. A single generic call in `QuickEdit` with the store in a
  variable would fail it, so the generic code never calls the editor: the source
  closure of each adapter renders its editor with its own literal store
  (`comment.body`, `forum.body`, and for Node one call per field, `nodes.intro`
  and `nodes.body`) and hands the markup to `QuickEdit`. The test is not
  weakened.
- The dial item that opens it is an ordinary dial entry (`is_htmx`, `hx_target`,
  `hx_headers` with the page token), so `fragments/dial.html` stays as it is.
- `plugins/system/slaed.js` gets one delegated handler, `setQuickEdit`:
  - Cancel restores the rendered HTML it kept when the editor opened, with no
    request.
  - Escape cancels and Ctrl+Enter saves, **except** while an editor popup, the
    editor window, `#sl-confirm` or the fullscreen editor is open: those own
    Escape first (`editor-tags.js` already leaves fullscreen on Escape). Cancel with
    unsaved changes asks through `setConfirmTask()`, the window canon of
    `docs/WINDOW.md`.
  - A refusal leaves the editor and the typed text in place and tells the reason
    on the warning toast. htmx 2 does not swap a 4xx/5xx answer by default; the
    4xx handling of the rating votes already reads the alert text from it.
  - A conflict (owner, 2026-10-03: the simple question) keeps the editor and the
    typed text. The script replaces the HTML it kept for Cancel with the current
    text from the 409 answer, takes the fresh stamp, and asks one question
    through `setConfirmTask()`: the text was changed after you opened it,
    overwrite it with yours? Yes saves again at the fresh stamp; no leaves the
    editor open, and Cancel now shows the current version. No new window type.
  - When its region is swapped away (save, Cancel, conflict resolved), the
    editor instance is **destroyed**, not only forgotten. A Toast UI editor is
    registered in two places at once: `SlaedToastUi.register()` in
    `editor-tags.js` stores it in its map and in `SlaedToastUi.options`, and
    hands it to `SlaedFileManager.addUpload()` in `filemanager.js`, which stores
    it in its own `edits` map and `options`. Teardown destroys the vendor
    instance and clears all four entries; each driver gets the same teardown for
    whatever its engine registers.
  - The init may run after the engine loads asynchronously (see the next item),
    so it first checks that its mount node is still in the document. Cancel
    pressed while the engine is still loading leaves no instance bound to a
    removed node.
- Editor assets become idempotent on the client: the engine is loaded once per
  page, global listeners are added once, and the inline init waits for the
  engine instead of returning. This is done in the editor drivers, not in the
  quick edit, and it covers **every driver with assets** (`ckeditor`,
  `codemirror`, `tinymce`, `toastui`), not Toast UI alone. Defect 8's hidden
  textarea is confirmed in the browser first.

### Node gets a partial write

`NodeService::updateNodeText(int $id, string $field, string $text, int
$version): Node` changes one text field, `intro` or `body`, of a material whose
type has no extension; a type with one is refused as `denied`. It follows the
order of "The write under the lock": a text equal to the stored one in its
canonical form answers the stored material without a write (no version step,
no `updated`, no status move, no epoch bump), and only then a stale version is a
conflict. It runs on the path of `updateNode()` with one deliberate difference:
`updateNode()` refuses a stale version already on the unlocked head, and
`updateNodeText()` must not, because that early refusal would answer the
repetition of a save whose response was lost with `conflict` before equality is
ever checked. The steps:

- its own head read and right check: the moderator of the type (as
  `getNodeHead()`, which refuses everyone else in its first line), or the author
  within the window (see "Node author edit" below)
- the text checks of `getInputData()` for the one field: `filterTrustedTags()`,
  `mb_check_encoding()`, `checkEditorTextRoom()`. These are extracted from
  `getInputData()` into one private method that both writers call, rather than
  building a full `NodeInput` from the stored material: a full input would
  re-validate every field, relation and resource, and a quick edit of the body
  would then fail on an unrelated field the type has since made required.
- `checkNodeFiles()` through the `$files` closure of `setNodeWrite()`, under the
  directory lock of the type, exactly as `updateNode()` passes it. Intro and body
  may name attachments; without this check a text could bind a file of
  `uploads/<type>` the actor does not own. Its data carries the new text, the
  other text from the stored material, and an empty `assets` set, so it checks
  only the attachment names the edit adds.
- under the lock (`getTypeLock()`, then the material `FOR UPDATE`): existence,
  the right and the window again from the locked row, equality, then the
  version, then `version = :ver` in the update
- `setNodeWrite()` for the guard, the transaction and the epoch, with
  `version + 1` and `updated`; it learns from the work closure whether a write
  happened and bumps the epoch only then, and a refused guard reaches the caller
  as the new blocked code, not as `STORAGE`
- no extension hook: only types without an extension take part, so `$this->ext`
  is null on this path

Categories, fields, relations, assets and the poll are left untouched; the
publication job is touched only by the author's move back to moderation (see
"Decisions"). `updateNode()` keeps its full-set contract; the partial method is a
second writer with its own tests, not a merge mode of the first.

`getNodeModerDial()` gets "Quick edit" before "Full edit" **only on the public
page**: it also builds the panel list, where no region exists. A flag in its
signature (like `$back`) or the public call site decides it. Cards stay without
it: a card shows a shortened intro, and editing that in place would edit a text
the reader does not see whole.

### What each kind keeps

- **Comment**: its right and window stay as they are, and so do its filters
  (`checkRules()`, `filterCommentBody()`) and its writer. `Comment::updateComment()`
  gains the stamp check under the lock and a result code, and loses the
  empty-body render branch.
- **Forum**: the right of batch 0, `getForumPlace()` and `checkForumRight()`, used
  by both the button and the adapter, reading the category and the topic status
  from the stored post. It keeps the `forum.add` gate, the longest-word limit
  `forum.letter` (`_CERROR2`), `filterHtml()` and the room check of
  `updatePost()`. The write goes through a guarded writer that bumps the epoch
  only when it writes.
- **Node**: moderators at any time, the author within the window, with the
  status rule below.

## Batches

Every batch that touches markup or theme CSS takes its own `npm run ui:before` /
`ui:after` pair. Every batch adds its language constants to all six locales of
the file they belong to (`lang/*.php` for the site, `admin/lang/*.php` for the
panel field of batch 3); `LanguageValidationTest` runs only in a full `phpunit`.
A CLI test cannot drive a request, so the HTTP cases (method, header token,
status per code) go through `tests/Support/route_probe.php`, whose
`getRouteReply()` already sends headers.

0. **Forum right from the stored post — done.** `getForumPlace()` in
   `core/user.php` reads the category, the topic with its status and the author
   of a named post from its rows. `checkForumRight()` is the one right over a
   post: the moderator of its category, or its signed-in author with the category
   right while the topic is open. `updatePost()`, `add()`, `send()`, `delete()`,
   `move()` and the buttons of `view()` take both, so the request category decides
   only where a new topic goes. This closes defects 1 and 2, and also:
   - a reply always answers the topic of the post it names, and it needs the
     reply right; before, the topic right alone inserted a reply;
   - `move()` acts only on the topics stored in the moderated category;
   - a guest never matches a post written without an account;
   - the refusals of `updatePost()` are echoed, not returned into nothing.

   `ForumRightTest` pins the place against live rows, the right as a guest and as
   an account, and the handlers' use of both.
1. **Protocol and comment** (batches 1 and 2 of the first draft merged by the
   owner on 2026-10-03, so the protocol lands with a real consumer and no stub).
   This batch:
   - confirms defect 8 in the browser, then makes the assets of every editor
     driver idempotent on the client
   - adds `QuickEdit`, `getQuickService()`, the two routes and their router
     entries
   - adds `fragments/quick-edit.html` in `lite` and `setQuickEdit` in `slaed.js`,
     with the conflict question
   - adds the comment adapter, and in `Comment::updateComment()` the locked
     order (existence, right and window again, equality, stamp, write) and the
     result code; splits `Comment::setWriteBegin()` into a refused guard and a
     failed `BEGIN`, and closes an equal text, decided under the lock, with
     `setWriteDone($guard, false)`: a commit without an epoch bump
   - adds the region and the out-of-band edited mark, and switches the dial item
   - removes `op=updateComment`, the handler `updateComment()` in
     `core/system.php` and its router entry; adds the two new ops to the
     router's `$public` list, with method and header token checked in the
     handlers
   - destroys the editor on teardown and clears both Toast UI registries, with
     the mount check in the async init
   - adds `QuickEditTest`: closed forms, status per code, the stamp conflict,
     "saved without write" on an equal canonical text, **the repetition of a
     save with its old stamp answering `saved` and changing neither the edit
     time nor the epoch**, a right lost between opening and saving, the GET-only
     and POST-only refusals, a token in a parameter ignored, and an expired
     token answering 403
   - adapts `CommentTransportTest` (it reads the source of the handler
     `updateComment()` and expects `getTplAjaxTextarea` there),
     `CommentTrustBoundaryTest` (it iterates the handler `updateComment`) and
     `tests/Support/contract_probe.php` (six calls of
     `$com->updateComment($id, $body)` that break on the new signature), whose
     `edit` results `CommentWriteTest` reads. `CommentStateTest` keeps holding
     unchanged: the edit keeps its rules without the flood window. The window,
     trust-boundary and room assertions keep holding.
   - language constants: the conflict question, the unsaved-changes question,
     the refusal texts; the dial label reuses `_ONEDIT`.
2. **Forum.** Extract the body and edited badge of one post out of `view()` into
   one function used by the page and the adapter. Add the forum adapter and the
   guarded writer with the locked order: topic row, then post row, the place
   and the right read again from them, so a topic closed or a post moved after
   the editor opened is refused as `denied`; a test pins that case. Remove `updatePost()`, its router entry and `'updatePost'` from
   the router's `Cache::addEpoch()` line, which stays for `updateVotingResult`.
   Remove `getTplAjaxTextarea()` once nothing calls it; `EditorRoomTest` must
   still find a store at every textarea call site. Move the handler assertions of
   `ForumRightTest` (it reads the source of `updatePost()`) to the adapter, and
   adapt `CommentTransportTest` to the shortened router line and to the removal
   of `getTplAjaxTextarea()`, two of whose assertions read that helper's source
   (no token in its URL, the page token in its header).
3. **Node.** Add `Published → Pending` to `NodeStatus::MOVES` and to the state
   table of `docs/NODE.md`, adapting `NodeModelTest`, `NodeServiceTest` and
   `tests/Support/node_probe.php`, which pin the matrix. Teach
   `setNodeWrite()` to bump the epoch only after a real write and to report a
   refused guard with a new `NodeException` code, and map that code wherever
   the codes become a message or a status. Add
   `NodeService::updateNodeText()` with its NodeServiceTest cases (moderator and
   author rights, a guest-written material never matching, the author window,
   the move back to moderation, version, room, files, a type with an extension
   refused, an equal text without a write, the repetition with the old version
   answering the material unchanged in version, `updated`, status and epoch,
   untouched sets), the window setting,
   the node adapter, the view regions in every mode, the public-only dial item
   for moderators and the author's own entry. Add probe cases in
   `tests/Support/node_probe.php` for the HTTP path.
4. **Reference.** Write the lasting part — the subject protocol, the stamp rule,
   the status table and how to add a kind — into the permanent documentation
   (`docs/ARCHITECTURE.md`, section "Helper Endpoint"). Add an entry to
   `docs/VERSIONS.md` and delete this file.

## Decisions

- **Node fields — decided 2026-10-03: intro and body only.** See "One subject
  protocol" for why the title stays in the full form.
- **Node types — decided 2026-10-03: only types without an extension.** See
  "One subject protocol"; extension types are listed under "Outside this plan".
- **Node author edit — decided 2026-10-03: the author edits within a window, as
  comments do.** Only the author, a moderator of the type or the main
  administrator may edit a material, and an edit never changes its author.
  Node has no owner edit anywhere today, and `getNodeHead()` refuses everyone but
  moderators, so batch 3 adds an author path inside `updateNodeText()` next to
  the moderator one, with its own right check rather than a second writer.
  - The window is counted from `created`, the way a comment counts its window
    from its own creation time; a pending material has no publication date to
    count from. A closed window leaves only moderators.
  - The author is matched by account only: a material written without one
    (`uid` 0, `aname` set) has no author who may edit it, as in the forum since
    batch 0.
  - The author edits only a `Pending` or `Published` material. `Disabled` and
    `Deleted` are moderator decisions and stay closed to the author; `Draft`
    never belongs to one, because `checkNewNode()` lets only a moderator create
    a draft.
  - **An author edit of a published material sends it back to moderation**
    (owner, 2026-10-03), unless the author may publish directly by
    `workflow.publish`. The closed matrix `NodeStatus::MOVES` has no
    `Published → Pending` today (a published material may only go to `Disabled`
    or `Deleted`), so batch 3 adds that move to the matrix (owner, 2026-10-03).
    It is a general move, not a private one of the author path: the moderator
    dial and the panel offer it too, because `getNodeModerDial()` lists every
    move the matrix allows. The text and the move to `pending` are one write and
    one version step. The move needs `checkNodeReady()` like any move to pending,
    and a material that fails it refuses the edit.
  - The publication award is keyed by the source `node:<id>`; batch 3 verifies
    that a second approval of the same material neither awards publish twice nor
    takes back the first award, and that `setPublishJob()` leaves no job behind
    for a material back in pending. The answer tells the author the
    material went to moderation instead of swapping in a text the public no
    longer sees. **Decided 2026-10-04: the region shows the saved, now pending
    text to its author and the warning toast says it went to moderation**; the
    page does not reload.
  - **The window setting is a configuration contract change. Decided
    2026-10-04: one Node-wide limit `limits.edit`** in seconds beside
    `limits.send`, like `comments.edit`; `0` switches the author edit off, the
    shipped default is `600`. `getNodeLimits()` answers an empty array for a
    `limits` section missing a key, so it needs the default in
    `config/node.php`, a step of the update run (`setUpdateRun()` in `update.php`, the way
    `config/newsletter.php` gains its missing keys) for existing
    configurations, the field on the limits tab of the panel, the six panel
    locales, and `NodeConfigTest`.
- **Conflict — decided 2026-10-03: the simple question** through
  `setConfirmTask()`, described under "One fragment, one script". No two-button
  window.
- **Forum trust mode.** Forum posts are stored through `filterHtml()` and
  rendered with `safe=false`. `.rules/architecture.md` lists forum posts under
  `safe=true`. Switching changes how existing posts render, so it is a separate
  decision and not part of this plan. Quick edit renders the way the page renders,
  whichever mode that is.
- **Guest-editable categories — settled in batch 0.** `is_acess()` admits a
  guest to a right of level 0, and the old author check compared uids. So any
  guest matched every anonymous post in such a category. `checkForumRight()` now
  requires a signed-in author.

## Outside this plan

- The editor dropping `<br>` for non-HTML editors (`getTplTextarea()` and
  `replace_break()`, recorded in `docs/EDITORS.md`) is its own defect. Quick
  edit shows it no more and no less than the full forms do.
- A moderator without the trusted-tag right loses the trusted tags of a text the
  main administrator wrote when saving it (`filterTrustedTags()` in the text
  checks). The full form behaves the same; quick edit inherits it unchanged.
- Admin full forms (Node `edit()`, comment `editsave`) keep their pages.
- `docs/PAGE-CACHE-ROUTES-2026.md` widens the ready-page cache, including a
  news view with its comment thread. It changes nothing here, but once it lands
  the epoch bump inside each writer's guard is what keeps a cached page from
  showing the old text.
- `docs/FILES-2026.md` plans attachments for forum posts, granted when the text
  of the stored post carries the name. Whichever of the two plans lands second
  adds the attachment-name check of that plan to the forum quick-edit writer, as
  `checkNodeFiles()` does for Node here.
- Quick edit for Node types with an extension. It needs an `edit` action in the
  closed action set of `NodeExtension` and a rule for the body the `sync`
  extension owns; a plan of its own if it is ever wanted.
