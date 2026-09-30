# Quick Edit 2026

Work plan for one in-place edit of a text, shared by every place that offers it:
a Node material, a forum post, a comment. The reader presses "Quick edit" in the
item's dial, the rendered text turns into the editor in the same spot, and a save
puts the rendered text back without leaving the page.

Status: batch 0 landed on 2026-09-30, batches 1 to 5 planned. Batches run in
order; update this line as they land. The last batch moves what lasts into the permanent reference and
deletes this file.

No line numbers for this tree anywhere in this document on purpose: every
reference names the function, the file or the constant it points at.

## What exists today

Two inline edits exist, both built on one helper, and Node has none.

| | Forum post | Comment |
| --- | --- | --- |
| Button | dial item in `view()` of `modules/forum/index.php` | dial item in `getCommentView()` of `core/user.php` |
| Route | `go=1&op=updatePost`, GET and POST | `go=1&op=updateComment`, GET and POST |
| Handler | `updatePost()` in `core/user.php` | `updateComment()` in `core/system.php` → `Comment::updateComment()` |
| Form | `getTplAjaxTextarea()` in `core/helpers.php` | the same |
| Right | `checkForumRight()` over `getForumPlace()`: moderator of the stored category, or signed-in author with `pedit` in an open topic | `is_moder()`, or author within `comments.edit` seconds |
| Write | bare `UPDATE _forum`, no guard, no transaction | write guard + transaction + `Cache::addEpoch(true)` |
| Concurrency | none, last write wins | none, last write wins |
| Render | `filterContent(..., false, mod, 2)`, trusted | `filterContent(..., true, mod, 2, 'breaks')`, safe |

Node writes only through `NodeService::updateNode()`. It is for moderators only
(`getNodeHead()`), takes the full `NodeInput` and checks the expected
`version`. The public module has no edit op. The moderator dial
`getNodeModerDial()` offers only a link to the admin form, plus the status moves.

### Defects the plan closes

These were checked against the code, not taken from the audit alone.

1. **Closed in batch 0.** **The forum right came from the request, not from the post.** `updatePost()`
   reads `pedit`/`pmod` of the category named by the request `cid` and never
   compares that category with the one the post is stored in. A moderator of any
   single category can therefore edit every post of the forum by sending their
   own `cid`. The full edit (`add()`/`send()` in `modules/forum/index.php`)
   follows the same pattern and must be checked in the same batch.
2. **Closed in batch 0.** **The button and the handler disagreed.** The forum button shows for any truthy
   topic status, while the handler asks for `status > 2`. A button can open an
   editor that the save then refuses.
3. **A refusal loses the typed text.** Both handlers answer a refusal (rules,
   room, expired window) with an alert that replaces the editor, with HTTP 200.
4. **Last write wins.** Two editors of one item overwrite each other silently.
5. **Cancel is a round trip.** "Back" asks the server to render the body again.
   For a comment it does this by calling `updateComment($id, '')`.
6. **The forum bumps the page cache on every call.** `index.php` calls
   `Cache::addEpoch()` after every `updatePost`, including a form load or a
   refusal. This runs outside the write guard.
7. **The "edited" mark goes stale.** Only the body region is swapped. The
   forum's `etime` line and the comment's edited badge keep their old state until
   the page reloads.
8. **Editor assets are emitted per response.** `EditorToastUi::getAssets()`
   dedups with a per-request static, so every fragment carries the engine
   scripts again, and `editor-tags.js` adds its global listeners again. The
   inline init returns silently when `window.toastui` is not loaded yet. On a
   page without a comment form this can leave the textarea hidden, which needs
   confirming in the browser. `SlaedToastUi` keeps every registered editor after
   its node is swapped away.
9. **Node needs its full form for a typo.** A moderator fixing one word leaves
   the page for `admin.php?name=node&op=edit`.

## Design

### One subject protocol, three kinds

A subject is one editable text of one item, named by three closed values:

- `kind`: `node`, `forum` or `comment`
- `id`: the item
- `field`: `body` for a forum post and a comment; `title`, `intro` or `body` for
  a node, limited to the fields the type carries

A new class `QuickEdit` (`core/classes/quick_edit.php`) owns the protocol and
nothing else. It receives one adapter per kind as closures, the way
`getRatingService()` builds `Rating` from its read and write closures. Its
factory is `getQuickService()` in `core/system.php`. Each adapter answers
three questions:

- **source**: may the actor edit this subject, what is the stored text, what is
  its stamp, and which editor store (`nodes.body`, `forum.body`,
  `comment.body`, …) and module it belongs to. Every one of these comes from
  the stored row, never from the request. This is the rule
  `CommentTrustBoundaryTest` already holds for comments, extended to every kind.
- **write**: store the text at the expected stamp through the kind's own
  guarded writer, answering one of the closed result codes.
- **view**: render the stored subject exactly as the page renders it, together
  with its edited mark. The kind uses the same function its page uses, so the
  swapped region and a reload can never differ.

### The stamp

Optimistic locking needs no schema change.

- A node uses its `version` column.
- A forum post and a comment have no version column. Their stamp is a hash of the
  stored text and its edit time (`etime` / `edited`).

The editor carries the stamp it was opened with. A save with a stale stamp
answers **conflict**. A save whose text equals the stored text answers
**saved** without writing, so repeating a request whose response was lost is
harmless.

### Two routes, strict like the rating endpoint

`getRatingView()` is the model: closed regex forms for every field, a token,
one HTTP status per refusal, nothing read from where it does not belong.

- `go=1&op=getQuickEdit`, **GET only**: answers the editor fragment for one
  subject, or the refusal status.
- `go=1&op=updateQuickEdit`, **POST only**: `kind`, `id`, `field`, `stamp` and
  `text` in the body, and the token in the `X-CSRF-TOKEN` header. It answers the
  rendered region on success.

| Code | Status | Meaning |
| --- | --- | --- |
| saved | 200 | the rendered region with its fresh edited mark |
| invalid | 422 | a field outside its closed form |
| denied | 403 | no right, or the edit window has closed |
| unavailable | 404 | the item is gone or deleted |
| conflict | 409 | the stamp is stale; the body carries the current text rendered |
| rules | 422 | rules or room refused the text; the body carries the messages |
| storage | 500 | the write failed |
| blocked | 503 | the write guard is closed |

Both ops enter the POST-only and token lists in `index.php` in the same way
the comment ops do. The forum-only `Cache::addEpoch()` in the router goes away:
each kind's writer bumps the epoch inside its own write guard.

### One fragment, one script

- `fragments/quick-edit.html` in both themes holds the form, the editor slot, Save and
  Cancel. It replaces the `form-wrap` built in PHP by `getTplAjaxTextarea()`, and no
  class string is built in PHP any more.
- The region to replace is a wrapper around the rendered text, marked with
  `data-sl-quick`. Its id is derived from the subject. The dial item that opens it
  is an ordinary dial entry (`is_htmx`, `hx_target`), so `fragments/dial.html`
  stays as it is.
- `plugins/system/slaed.js` gets one delegated handler, `setQuickEdit`:
  - Cancel restores the rendered HTML it kept when the editor opened, with no
    request.
  - Escape cancels and Ctrl+Enter saves. Cancel with unsaved changes asks through
    `setConfirmTask()`, the window canon of `docs/WINDOW.md`.
  - A refusal leaves the editor and the typed text in place and tells the reason
    on the warning toast. The 4xx handling of the rating votes already does this.
  - A conflict opens the confirm window with the current text: "Take theirs"
    reloads the region, "Keep mine" saves again at the fresh stamp.
  - The editor instance is unregistered from `SlaedToastUi` when its region is
    swapped away.
- Editor assets become idempotent on the client. The engine is loaded once per
  page, `editor-tags.js` does not re-add its listeners, and the inline init waits
  for the engine instead of returning. This is done in the editor plugin, not in
  the quick edit.

### Node gets a partial write

`NodeService::updateNodeText(int $id, string $field, string $text, int
$version): Node` changes one text field. It runs on the same path as
`updateNode()`:

- `getNodeHead()` for the right
- `getInputData()` validation of the one field, including
  `filterTrustedTags()` and `checkEditorTextRoom()`
- the version check on the head and on the locked row
- `setNodeWrite()` for the guard, the transaction and the epoch, with
  `version + 1` and `updated`
- `updateNodeData()` of the extension

Categories, fields, relations and assets are left untouched. `updateNode()`
keeps its full-set contract; the partial method is a second writer with its own
tests, not a merge mode of the first.

In the templates, the view partials of every mode wrap `intro_html` and
`body_html` in addressable regions. `getNodeModerDial()` gets "Quick edit"
before "Full edit". Cards stay without it: a card shows a shortened intro, and
editing that in place would edit a text the reader does not see whole.

### What each kind keeps

- **Comment**: its right and window stay as they are, and so do its filters
  (`checkRules()`, `filterCommentBody()`) and its writer. `Comment::updateComment()`
  gains the stamp check and loses the empty-body render branch.
- **Forum**: the right of batch 0, `getForumPlace()` and `checkForumRight()`, used
  by both the button and the adapter. It reads the category and the topic status from the stored post. The
  write goes through a guarded writer that bumps the epoch only when it writes.
- **Node**: moderators at any time, the author within the window of the type.

## Batches

Every batch that touches markup or theme CSS takes its own `npm run ui:before` /
`ui:after` pair.

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
1. **Protocol.** This batch adds:
   - `QuickEdit`, `getQuickService()`, the two routes and their router lists
   - the fragment in both themes and `setQuickEdit` in `slaed.js`
   - client-side idempotent editor assets (defect 8)
   - `QuickEditTest`: closed forms, status per code, the stamp conflict, and
     "saved without write" on an equal text

   No kind is wired yet; a stub adapter drives the tests.
2. **Comment.** Add the comment adapter and switch the dial item. Then remove
   `op=updateComment`, the handler `updateComment()` in `core/system.php` and its
   router entries. Adapt `CommentTransportTest`, `CommentWriteTest` and
   `CommentStateTest` to the new routes. The window, trust-boundary and room
   assertions must keep holding.
3. **Forum.** Add the forum adapter and the guarded writer. Remove `updatePost()`,
   its router entry and the router's `Cache::addEpoch()`. Remove
   `getTplAjaxTextarea()` once nothing calls it; `EditorRoomTest` must still find
   a store at every textarea call site. Swap the edited line with the body
   (defect 7).
4. **Node.** Add `NodeService::updateNodeText()` with its NodeServiceTest cases
   (moderator and author rights, the author window, version, room, extension
   hook, untouched sets), the window setting of the type, the node adapter, the
   view regions in every mode, the dial item for moderators and the author's own
   entry. Add probe cases in
   `tests/Support/node_probe.php` for the HTTP path.
5. **Reference.** Write the lasting part — the subject protocol, the stamp rule,
   the status table and how to add a kind — into the permanent documentation
   (`docs/ARCHITECTURE.md`, helper endpoint section). Add an entry to
   `docs/VERSIONS.md` and delete this file.

## Decisions before work starts

- **Node author edit — decided: authors edit within a window.** Node has no
  owner edit anywhere today, and `getNodeHead()` refuses everyone but
  moderators. Batch 4 therefore adds an author path next to the moderator one:
  the author of a published or pending material edits its texts in place for a
  window counted from the publication, the way comments count theirs. The window
  is a setting of the type, and a closed window leaves only moderators. The
  author path goes through `updateNodeText()` too, with its own right check
  inside the service rather than a second writer; its limits — which fields,
  which states, whether an author edit sends a published material back to
  moderation — are fixed at the start of batch 4 and pinned by NodeServiceTest.
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
- Admin full forms (Node `edit()`, comment `editsave`) keep their pages.
