# Editor uploads 2026 — access belongs to the settings, not to the editor

Status: decisions closed on 2026-08-06. Implementation has not started.

The content editor decides for itself what a visitor may do with files. It must not. The
module upload settings at `admin.php?name=uploads&op=config` are the only place that
decides, and the editor renders what those settings allow.

Work in the numbered batches below. Finish and verify one batch before starting the next.

## 1. What is wrong today

The permission itself is already settings-driven, which is the good half:
`checkEditorUploadAccess()` (`core/system.php:4295`) reads `userupload` and `guestupload`
from the module rule, and both routes call it — `addEditorUpload()` (`:4349`) and
`getEditorFileJson()` (`:4382`). Nothing below changes that gate; everything below removes
the rules that were bolted on beside it.

**Three role rules exist that no setting can express.**

| Place | Hard-coded rule |
| --- | --- |
| `core/system.php:4386` | `if (!$all && $uid < 1)` — a guest is always answered an empty file list. With `guestupload = 1` a guest uploads a file and cannot see it, not even their own, not even once |
| `core/system.php:4351` | ownership is assigned by role: member gets their own id, moderator gets `null`, guest gets `0` |
| `core/system.php:4387` | the listing limit is `moderfiles` or `userfiles`; the rule set carries no guest limit at all |

The guest guard is not laziness and must not simply be deleted. Ownership lives **in the
file name**: `core/classes/upload.php:574` builds `name-salt[-owner].ext` and
`core/system.php:4391` matches the owner back out of it. A guest owns `0`, so every guest
file carries the same owner and every guest would otherwise see the uploads of every other
guest. Removing the guard without replacing the owner is a privacy defect, not a fix.

The same line carries the moderator rule: a `null` uid writes **no owner segment at all**,
which is why a moderator's file matches no owner and is invisible to members, and why a
moderator has to be handed the whole directory by a separate flag.

**The client mirrors the role rather than the settings.** `plugins/editors/toastui/driver.php:66`
resolves one boolean and, when it is false, renders neither the file panel nor the toolbar
button (`plugins/editors/toastui/assets/editor-upload.js:742`). That is the guest variant of
the editor: a second, reduced interface that exists only because of who is looking at it.

**The denied path is the most permissive one, and this is the defect that matters most.**
`editor-upload.js:555` registers the `addImageBlobHook` **only** when uploading is allowed.
Toast UI ships a default listener for that hook, and the default is a `FileReader` that
base64-encodes the picked file straight into the document. So a visitor who may not upload
opens the same image dialog, picks the same file, and the editor embeds it into the body —
with no extension check, no size check, no dimension check, and not even the 64 KB bound of
`addEmbed()`, because our own embed path is never reached. The only thing that stops the
result is `Parser::EMBEDMAX` at render time, so a three-megabyte photo becomes four megabytes
of base64 in the row and then does not display at all. Denying the upload right today does
not deny images; it silently swaps a bounded, audited path for an unbounded one.

**One mode is inverted.** The file tab offers three modes
(`templates/*/partials/editor-toastui-files.html:35-37`): upload to the server, insert as an
attachment, and embed into the text. Embedding base64-encodes the file into the body and
writes nothing to `uploads/`, so it is subject to no module setting whatsoever — not the
extension list, not the size, not the dimensions, not the file count, not the quota. The
only bound is `Parser::EMBEDMAX`, 64 KB, in code. Today it is offered only to those who may
already upload, which is the one group that does not need it.

## 2. Fixed decisions

- Inserting an image by its address stays available to everyone, always. It touches no file
  and no server and is the natural fallback for a visitor who may not upload. After the merge
  below it is a field of our own window rather than a tab of Toast UI's, and that changes
  where it lives, not who may use it.
- A guest sees the files of their own session and no others. The next session is a new
  session and the old files are gone from the list, which is the intended behaviour rather
  than a limitation to work around.
- **Embedding into the text is open to everyone and gains no setting.** It was going to get a
  pair of rights symmetric with uploading; that is dropped, because the setting could not be
  enforced. A base64 image never reaches an upload route: it is produced in the browser and
  arrives inside the body, so there is no request to refuse. Enforcing it would mean filtering
  data URIs at every content write boundary of the project, which is a different task with a
  different scope. A control that looks like a boundary and is not one is worse than no
  control, and two more values on a page that already carries twelve per module is the kind of
  growth this project refuses.
- The bound on embedding is therefore code and not configuration: `Parser::EMBEDMAX`, one
  constant, enforced at render by `Parser::checkImageSource()`, the same in every module.
- **That constant only became honest once the columns could hold what it allows.**
  `EMBEDMAX` is 65536 bytes of *binary*; base64 turns that into 87384 characters before the
  data URI prefix and the markdown around it, while the columns the editor writes to were
  `TEXT`, which holds 65535. One image at exactly the size the parser was willing to render
  did not fit, and with `STRICT_TRANS_TABLES` the result is not a lost image but a lost post:
  `ERROR 1406 (22001) Data too long for column 'body'`. Halving the cap to 32 KB was
  considered and rejected — it only moves the overflow from the first image to the second and
  leaves the same class of bug. Thirteen columns widen instead, listed in section 3.
- **The column width is the per-field policy, and no per-field capability flag is added.**
  Not every editor field should hold an embedded image: a summary is drawn by its module's
  list query, so a page of twenty rows carries twenty of them, and a signature is repeated
  under every post its author made on the page. Those fields stay `TEXT`, which is room enough
  for a line of prose beside an image referenced by address or uploaded to the server, and not
  room for an 87 KB data URI. That is worth saying plainly: **graphics are not refused in a
  summary or a signature — one delivery method for them is.** A linked image is also the better
  one there, because the browser fetches it once and reuses it across all twenty renders, while
  a base64 copy is re-sent whole every time and defeats the page cache.
  Expressing this through the column rather than through a new flag is deliberate. A flag would
  be a third axis after the module rule and the role, plumbed through `getTplTextarea()`,
  `Editor::getContent()`, `driver.php` and the JavaScript, and it would have to be set correctly
  at 55 call sites. The column already knows.
- The editor refuses an oversized file **before** inserting it, rather than storing something
  the database will refuse. It knows `EMBEDMAX` and the room the field has, and it can measure
  the blob. A refusal the author sees at the moment they act is worth more than `ERROR 1406`
  and a lost post, and it is what turns the paragraph above from a trap into a rule.
- **Nothing in the project guards the length of a stored text today** — no write path measures
  a body against its column. That is older than embedding and independent of it: 70 KB of
  plain prose in a news article fails the same way. The guard belongs with this work because
  this work is what makes the limit reachable in one paste.
- Today the editor embeds without any bound whenever uploading is denied — that is the defect
  in section 1, and it is what this release removes.
- **"Insert image" and "File catalogue" become one window behind one toolbar icon**, and that
  window is ours. Toast UI's image item is removed with `removeToolbarItem()` and its popup is
  not used at all. The link dialog and the emoji window are untouched and stay Toast UI's own.
- That merge is not a preference; it deletes a class of code. Making the two windows look like
  one already costs `setImageChrome()` and `setPopupChrome()` — 107 of the 745 lines of
  `editor-upload.js` — and what those lines do is reach into a foreign popup: add classes to
  its file row, strip its `accept` attribute, rewrite a text node inside its label, measure
  three bounding rectangles, move our mode block into another parent, absolutely position it
  above the popup's button container and push that container down with a computed margin.
  A window we own needs none of it, because the modes, the picker, the stored-file list and
  the URL field are siblings in one markup instead of two DOM trees held together by geometry.
- What we take over from Toast UI's popup is small and known: a URL field, an alt field and an
  insert button. Inserting at the cursor is already ours — `api.insertText()` and the
  attachment insert do it today.
- The blob hook still matters after the merge and is not made redundant by it: dragging an
  image onto the editor body and pasting one from the clipboard both go through
  `addImageBlobHook`, never through any window. That is why section 4 registers it
  unconditionally.

## 3. The settings contract

One module rule is a pipe-separated string in `config/uploads.php`, written by
`setUploadRuleData()` (`core/system.php:4278`) from a fixed key order and read back by
index in `getUploadRuleData()` (`:4250`). The final key order appends one key and
reorders nothing:

`extensions`, `maxquota`, `maxbytes`, `maxwidth`, `maxheight`, `maxfiles`, `thumbwidth`,
`adminlist`, `moderfiles`, `userfiles`, `userupload`, `guestupload`, **`guestfiles`**

Appending is what makes the change need no config migration: a stored rule that ends at
`guestupload` keeps working and answers the reader's default for the new position.

That default needs care. The listing limit treats `0` as "no limit"
(`core/system.php:4396`), so a `guestfiles` that defaults to `0` would hand an unbounded
list to the very role that had none. `guestfiles` therefore falls back to `userfiles` when
its position is absent, and the admin form writes an explicit value from then on.

**Embedding gains no setting**, and that is a decision rather than an omission. The admin page
already carries twelve values per module, and a thirteenth and fourteenth that cannot be
enforced would be the worst kind of addition: a control that looks like a boundary and is not
one. Section 2 records why no boundary exists there — the base64 is produced in the browser and
arrives inside the body, so no upload route ever sees it and there is nothing to refuse.
Embedding is therefore open to everyone, and its bound is code: `Parser::EMBEDMAX`, one
constant, the same in every module, enforced at render by `Parser::checkImageSource()`.
Inserting an image by its address is likewise open to everyone and bounded by nothing, because
it stores nothing.

### The schema half of the contract

The editor is rendered by `getTplTextarea()` at 55 call sites in 18 modules, and where each one
writes decides what it can hold. These columns hold an authored text and are `MEDIUMTEXT`:

`comment.body`, `privat.body`, `news.body`, `forum.body`, `faq.body`, `help.body`,
`jokes.body`, `files.body`, `links.body`, `media.note`, `money.note`, `products.body`,
`message.body`, `newsletter.body`, `order.note` — joining `content.body` and `pages.body`,
which already were.

These are drawn many times onto one page and stay `TEXT` on purpose, which is what keeps an
embedded image out of them: `news.intro`, `files.intro`, `links.intro`, `media.intro`,
`money.intro`, `pages.intro`, `products.intro`, `users.block`.

`order.info` also stays `TEXT` and is not an editor field at all despite its neighbour being
one: it is filled by `filterFields()` from the structured order form, and the `info` textarea
on the admin page writes to `config/order.php` instead.

`users.sig` moves from `VARCHAR(255)` to `TEXT`, and for a reason of its own rather than for
embedding. It is too small for what people already write: of 828 stored signatures the longest
is exactly 255 and 95 sit above 240, so a tenth are pressed against the ceiling today with no
image involved. `TEXT` gives a signature room for prose beside a linked image and stops well
short of a data URI, which is the right shape for something repeated under every post.

All of it lands in `setup/sql/table.sql`, `setup/sql/table_update6_3.sql` and
`setup/sql/update6_3_patch.sql` (section 9), and the three agree column for column.

## 4. Implementation batches

1. **Settings**
   - Add the one key `guestfiles` to `setUploadRuleData()` and `getUploadRuleData()`, with the
     fallback named above.
   - Add the one field to the module rule form in `admin/modules/uploads.php` beside the
     two upload switches it belongs with, and the six locale strings for it. The two upload
     switches keep their present meaning and wording; nothing on that page changes for
     embedding, which has no setting.
   - One invariant moves with it. `UploadIntegrationTest:75` asserts that reading a stored
     rule and writing it back reproduces the stored string byte for byte, and a rule written
     before this release is one field short, so writing it back cannot reproduce it. The
     invariant becomes what it really is: the first write normalises the rule to the full key
     order, and every write after that is stable. `tests/Support/upload_probe.php:812`
     duplicates the key list and must read it from the shipped code instead, or it will drift
     the next time a key is added.

2. **Ownership**
   - Give a guest a per-session owner token instead of the shared `0`, so a guest sees their
     own uploads and only those. The token is derived from the session and is not the
     session id itself.
   - The owner segment is an integer today, in the name builder
     (`core/classes/upload.php:574`, `?int $uid`) and in the matching pattern
     (`core/system.php:4391`, `([0-9]+)`). Both widen to alphanumerics together, and files
     stored under the old pattern keep resolving because digits remain a valid token.
   - Replace the `null` uid that means "moderator" with an explicit flag, so ownership and
     privilege stop travelling in one variable. This is the change that reaches furthest:
     six call sites pass that argument today — `core/system.php:4354`,
     `admin/modules/uploads.php:175` and `:176`, `modules/account/index.php:1078`,
     `modules/files/admin/index.php:245` and `modules/files/index.php:547` — and three of
     them pass `null` to mean "no owner", which is the meaning that has to survive.

3. **Server**
   - Remove the guest guard at `core/system.php:4386`. Whether a list is answered at all is
     decided by `checkEditorUploadAccess()` and by nothing else.
   - Choose the listing limit from three values rather than two.
   - Nothing is added here for embedding, and that is deliberate: no request carries it, so
     there is no route on which to refuse it. See the decision in section 2.

4. **Editor**
   - Merge the two windows into one behind one icon. `removeToolbarItem('image')` drops Toast
     UI's item, one item of ours replaces it, and the window it opens carries the URL field,
     the file picker with its drag target, the three modes, the stored-file list and the
     limits block as one markup.
   - Delete `setImageChrome()` and `setPopupChrome()` with the merge. Nothing measures a
     foreign popup any more, nothing moves a node between parents, and nothing rewrites a text
     node inside somebody else's label.
   - `driver.php` stops choosing between a full and a reduced editor. It resolves two flags
     instead of one boolean and hands them to the window: may upload, may list.
   - The three modes of the file panel divide by those flags, and they do not divide evenly:
     "upload to the server" and "insert as an attachment" both store the file and both follow
     the upload right, while "embed into the text" stores nothing, has no right to follow and
     is offered to everyone. The stored-file list follows the upload right, because a mode
     that stores nothing has nothing to list.
   - `editor-upload.js` shows each section from those flags instead of from `if (opt.upload)`.
   - The embed path checks the blob against `EMBEDMAX` before it inserts and refuses with a
     message naming the limit. The constant is handed to the editor from PHP rather than
     copied into the JavaScript, so the cap has one definition.
   - A field whose column cannot hold a data URI does not offer the embed mode at all, and it
     learns that from the room it has rather than from a flag of its own. `getTplTextarea()`
     already knows the module and the field; it passes the room down beside the upload rule.
   - A write guard measures the finished text against the room before the query runs and
     refuses with a message instead of letting `ERROR 1406` reach the author. This is the part
     that has to hold for plain prose too, not only for an embedded image.
   - **The icon is always there**, because the URL field inside it is always available and
     inserting a link is what a visitor who may not upload still does. A visitor allowed
     nothing beyond that opens the same window and finds the URL field in it and nothing else,
     which is an honest answer; a missing icon would not be one. This is the merge paying for
     itself: before it, "no upload right" had to mean "no button", because the button opened a
     panel that would have been empty.
   - **`addImageBlobHook` is registered unconditionally**, which is the fix for the defect in
     section 1. Our hook must own that event for every visitor, because leaving it unregistered
     hands the file to Toast UI's default listener and that listener embeds without a bound.
     A visitor who may not upload gets the embed path from our hook, under `EMBEDMAX`, instead
     of Toast UI's unbounded one — which is the difference between a rule and a silent swap to
     a worse path. Drag onto the body and paste from the clipboard reach the editor only here,
     never through a window, so this is the only place that can hold them.
   - Both themes carry the same window markup; no theme gains a variant of its own.

5. **Proof**
   - The matrix: guest, member and moderator, times `userupload` and `guestupload` on and
     off — driven as real HTTP requests, not as unit calls, and read back from `uploads/` and
     from the stored body.
   - An embed at exactly `EMBEDMAX` survives the round trip into every body column of
     section 3 and back out through the parser, and one byte over is refused by the editor
     before it is stored. This is the test the widening exists for, and it fails on `TEXT`.
   - The same embed offered into a summary field is refused by the editor, and a linked image
     into the same field is accepted — which is the pair that proves the restriction is about
     delivery and not about graphics.
   - A refusal has to hold when the client is bypassed: a direct POST to the upload route
     with the setting off must be refused whatever the page rendered.
   - Two guests in two sessions must not see each other's files.

## 5. Files to audit

- `core/system.php` — `getUploadRuleData()`, `setUploadRuleData()`, `checkEditorUploadAccess()`,
  `addEditorUpload()`, `getEditorFileJson()`, `getEditorFileData()`;
- `core/classes/upload.php` — the stored file name and its owner segment;
- `admin/modules/uploads.php` — the module rule form and its save;
- `plugins/editors/toastui/driver.php` and `plugins/editors/toastui/assets/editor-upload.js`;
- `templates/lite/partials/editor-toastui-files.html` and its admin twin;
- `config/uploads.php`, the six locale files, and the tests that cover uploads.

## 6. Acceptance gates

- No editor file decides access. The only answer to "may this visitor upload or list" comes
  from the module rule; embedding asks nobody, because it is open to everyone.
- A stored rule written before this release keeps working untouched, and the first save
  normalises it to the full key order without changing any value it already carried.
- A guest allowed to upload sees their own files and no other guest's, in the same session,
  and none of them in the next one.
- The editor never embeds past `EMBEDMAX`, and that holds for the window, for a pasted image
  and for one dropped on the body alike. This is a gate on the editor and not on the stored
  text; the bound on the text is the same constant at render, as section 2 records.
- The fourteen body columns of section 3 are `MEDIUMTEXT` and the eight summary columns are
  `TEXT` in all three schema paths — fresh install, 6.2 upgrade and production patch — and the
  three agree column for column and index for index.
- No stored text is ever refused by the database. A body too long for its column is refused by
  the write guard, with a message, before the query runs.
- One window and one icon replace the image dialog and the file catalogue, the icon is present
  for every visitor, and no reduced variant survives in either theme.
- `setImageChrome()` and `setPopupChrome()` are gone, and no code measures or rewrites a popup
  it does not own.
- `php -l`, `composer analyse`, `composer test` and `php-cs-fixer --dry-run` pass, and the
  log files carry no new error after the matrix.

## Non-goals

- No change to what the upload service does with a file once it is accepted.
- No new storage for guest ownership; the file name already carries the owner.
- No change to the link dialog and the emoji window; both stay Toast UI's own.
- No rewrite of the editor beyond the image and file windows. The toolbar, the two edit modes
  and the bracket-tag buttons are not part of this.
- No migration of files already stored under the current name pattern.
