# Editor uploads 2026 — access belongs to the settings, not to the editor

Status: closed on 2026-08-10. Decisions were closed on 2026-08-06; most of the schema part of
section 3 landed in commit `4b5b679f` and the batches of section 4 landed in `91552eae`.

Both proofs of batch 6 have now been run. `tests/Unit/EditorRoomTest.php` with
`tests/Support/editor_probe.php` runs in the suite, and the `editor` mode of
`tools/upload-route-check.php` was walked against a live stand on 2026-08-10 with every row
passing: the four settings combinations against moderator, member and guest, the guest isolation
of two sessions, and the write guard over real HTTP. `config/uploads.php` was restored byte for
byte, the upload tree was left as it was found, and the three error logs gained nothing.

One nuance the header of that tool understates: the walk needs two accounts, not four separate
people. `is_moder()` reads the admin session only, so the same account answers as a moderator in
the admin cookie jar and as a plain member in the front-end one.

Nothing in this plan is open. What builds on it now is `docs/FILE-MANAGER-CONCEPT-2026.md`.

The content editor decides for itself what a visitor may do with files. It must not. The
module upload settings at `admin.php?name=uploads&op=config` are the only place that
decides, and the editor renders what those settings allow.

## How to work on this

Read this file to the end before the first edit. It is a plan whose decisions are closed, not a
discussion — the reasoning is written down so it does not have to be had again, and every argument
below survived a review that tried to break it.

**The order is fixed and 6 is where to continue.** Batch 1 depended on nothing and carried the last
outstanding column, which the room table in batch 4 has to be able to classify. Batch 2 gave a guest
an owner of their own, which is what batch 3 rested on: removing the guest guard before the owner
existed would have handed every guest the uploads of every other guest. Batch 4 introduced the room
table — `getEditorRoomData()` in `core/helpers.php` — and batch 5 read it, so the guard was written
against a table that already existed. Batch 6 proves all of it.
Finish and verify one batch before starting the next, and commit each on its own.

**These five are settled. Reopening them is not work, it is rework.** Each is argued where it is
named, and section 6 gates it:

| Settled | Not this |
| --- | --- |
| The role decides who may act, the settings decide how much and what. `is_moder()` stays the first line of `checkEditorUploadAccess()` (§1, §6) | "The settings must decide everything, so the moderator bypass has to go" |
| `null` in the owner argument means *this file has no owner*. Three call sites depend on that meaning and the editor route reaches it legitimately (§1, batch 2) | "`null` means moderator and must be replaced by a flag" |
| Embedding gains no setting. The policy is per field and lives in the width of the column, declared by `store` (§2, §3) | "Add `useembed` and `guestembed` beside the two upload switches" |
| The room table stores the column type, not a byte count, and derives "may embed" from the width (§3) | "Store the number, or add a second boolean" |
| A missing `store` means the narrowest field there is — `TEXT`, no embed (§2, batch 4) | "Default to the widest so nothing breaks" |

**Two things bite quietly.** The owner comparison must become a string comparison in the same edit
that widens the token, or every guest matches every other guest (batch 2). And the guard must
measure `strlen()` and not `mb_strlen()`, or it never fires in a Cyrillic text until the database
does (§3, batch 5).

**Batch 2 changes the format of names in `uploads/`.** Files stored under the old pattern keep
resolving, and no migration is planned — but this is the one place in the work where a mistake is
invisible at the moment it happens. Copy `uploads/` before starting it if the data is real.

## 1. What is wrong today

The permission itself already lives in one function, which is the good half:
`checkEditorUploadAccess()` (`core/system.php:4295`) reads `userupload` and `guestupload`
from the module rule, and both routes call it — `addEditorUpload()` (`:4349`) and
`getEditorFileJson()` (`:4382`). Nothing below changes that gate; everything below removes
the rules that were bolted on beside it.

That gate is not purely settings-driven, and it is not meant to become so. Its first line is
`if (is_moder($mod)) return true;` — a moderator of the module passes before any rule is read.
This release keeps that line and states the division it belongs to instead of pretending it is
absent: **the role decides who may act at all, the settings decide how much and what.** A
moderator is the one role standing above the rules; every other visitor is answered by
`userupload` and `guestupload` alone. What section 6 gates is that division holding in exactly
one place, not the disappearance of the role.

**Three rules were bolted on beside that gate, and the settings reached none of them.** All three
are gone: batch 2 replaced the shared guest owner and batch 3 removed the guard and the two-value
limit. The table records what they were, because the batches below are written against it.

| Place | Bolted-on rule |
| --- | --- |
| `core/system.php:4386` | `if (!$all && $uid < 1)` — a guest is always answered an empty file list. With `guestupload = 1` a guest uploads a file and cannot see it, not even their own, not even once |
| `core/system.php:4351` | ownership is assigned by role: member gets their own id, administrator gets `null`, guest gets the shared `0` |
| `core/system.php:4387` | both listing limits are settings, but which of the two applies is decided by the role, and the rule set carries no guest limit at all |

The guest guard is not laziness and must not simply be deleted. Ownership lives **in the
file name**: `core/classes/upload.php:574` builds `name-salt[-owner].ext` and
`core/system.php:4391` matches the owner back out of it. A guest owns `0`, so every guest
file carries the same owner and every guest would otherwise see the uploads of every other
guest. Removing the guard without replacing the owner is a privacy defect, not a fix.

The `null` on that line is a different matter and is not a defect. It writes **no owner segment
at all**, which is what the admin file manager and the module attachment forms also ask for, and
the editor route reaches it because `is_moder()` reads the admin session: an administrator need
not be a site user, so there is no id to own the file with. Their files are therefore ownerless
and invisible to members, and the whole directory reaches them through `$all = is_moder($mod)` in
the listing route instead. Section 4 keeps all of that and only widens the type.

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
  pair of rights symmetric with uploading, and that is dropped — but not for the reason first
  written down. The first reason was that the setting could not be enforced, because a base64
  image never reaches an upload route: it is produced in the browser and arrives inside the body,
  so there is no request to refuse. The write guard below refuses exactly that, so the claim no
  longer holds and is not worth keeping out of loyalty to it.
  The reason that survives is a different one. A per-role right answers "may *this visitor* embed",
  and nobody asks that question — what the site actually needs to say is "may *this field* hold an
  embed", which is a property of where the text is drawn and not of who typed it. A signature is a
  bad place for a data URI whether an administrator or a guest wrote it. So the policy is per field
  and it lives in the width of the column, and two more values on an admin page that already
  carries twelve per module is the kind of growth this project refuses.
- The bound on embedding is therefore code and not configuration: `Parser::EMBEDMAX`, one
  constant, the same in every module. It is enforced in three places that share the one
  definition — the editor before it inserts, the write guard before the query, and
  `Parser::checkImageSource()` at render.
- **A size bound alone is not the contract; the type is half of it.** `addEmbed()`
  (`editor-upload.js:389`) measures the blob and reads it, and accepts any `File` it is handed —
  while the parser admits five raster types and nothing else
  (`core/classes/parser.php:227`: `png`, `jpe?g`, `gif`, `webp`, `avif`). The picker cannot be
  trusted to narrow it either, because `setImageChrome()` strips the `accept` attribute today, and
  the blob hook receives whatever was dropped or pasted. So a PDF, an SVG or an unknown type is
  base64-encoded into the body, stored, counted against the column, and then silently not
  rendered — the same shape of defect as an unbounded size, arriving through the type instead.
  Both bounds are checked wherever one of them is.
- **That list of types exists twice in PHP already and gains no third copy in the JavaScript.**
  `Parser::checkImageSource()` carries it as a pattern and `getEditorImageData()`
  (`core/system.php:4306`) carries it as an array. They are consolidated into one definition beside
  `EMBEDMAX`, and it is handed to the editor from PHP the same way the cap is. One list, one place,
  three enforcers.
- **That constant only became honest once the columns could hold what it allows.**
  `EMBEDMAX` is 65536 bytes of *binary*; base64 turns that into 87384 characters before the
  data URI prefix and the markdown around it, while the columns the editor writes to were
  `TEXT`, which holds 65535. One image at exactly the size the parser was willing to render
  did not fit, and with `STRICT_TRANS_TABLES` the result is not a lost image but a lost post:
  `ERROR 1406 (22001) Data too long for column 'body'`. Halving the cap to 32 KB was
  considered and rejected — it only moves the overflow from the first image to the second and
  leaves the same class of bug. Fifteen columns widen instead, joining the two that already were,
  and all seventeen are listed in section 3.
- **The column width is the per-field policy, and no per-field capability flag is added.**
  Not every editor field should hold an embedded image: a summary is drawn by its module's
  list query, so a page of twenty rows carries twenty of them, and a signature is repeated
  under every post its author made on the page. Those fields stay `TEXT`, which is room enough
  for a line of prose beside an image referenced by address or uploaded to the server, and not
  room for an 87 KB data URI. The width names which class a field is in — wide enough for an
  embed, or not — and the guard below is what holds a field to its class, because a width alone
  refuses only the images too big for it and not the ones merely unwelcome. That is worth saying
  plainly: **graphics are not refused in a summary or a signature — one delivery method for them
  is.** A linked image is also the better one there, because the browser fetches it once and reuses
  it across all twenty renders, while a base64 copy is re-sent whole every time and defeats the
  page cache.
  Expressing this through the column rather than through a new capability flag is deliberate, but
  not because it is cheaper: the storage contract below reaches the same 56 call sites a flag would
  have reached, and counting them as a saving would be arithmetic in the service of a conclusion.
  The reason is that a flag is an opinion and a width is a fact. A wrong flag is invisible until an
  author loses a post, while a wrong column name fails against the schema, which is checkable and is
  checked in section 6. The width is also the one value that cannot drift away from what the
  database will accept, because it is read from what the database accepts.
- The editor refuses an oversized file **before** inserting it, rather than storing something
  the database will refuse. It knows `EMBEDMAX` and the room the field has, and it can measure
  the blob. A refusal the author sees at the moment they act is worth more than `ERROR 1406`
  and a lost post, and it is what turns the paragraph above from a trap into a rule.
- **Nothing in the project guards the length of a stored text today** — no write path measures
  a body against its column. That is older than embedding and independent of it: 70 KB of
  plain prose in a news article fails the same way. The guard belongs with this work because
  this work is what makes the limit reachable in one paste.
- **The field must name where it is stored, because nothing the editor is handed can work it out.**
  `getTplTextarea()` receives a form field name and an upload directory key, and neither one is a
  column. News posts `hometext` and `bodytext` into `intro` and `body`; shop posts `ptext` and
  `pbodytext`; files and links post `description` and `bodytext`. The directory key is not a table
  either — eight call sites pass `mod => 'all'`, which is a shared upload directory that no table
  answers to. And several editors write to no column at all: the money and order admin texts are
  saved into `config/money.php` and `config/order.php`, and `mailtext` into `$conf['mtemp']`. So the
  room is declared at the call site, as one value naming the storage, and one table in one place
  turns that name into bytes. Section 3 carries the contract.
- **A field that does not name its storage is treated as the narrowest field there is** — the room
  of `TEXT`, and no embed mode. With 56 call sites a permissive default would mean every one of them
  has to be right the first time; a strict default means a forgotten call site costs its author the
  embed button, and never costs them a post.
- **The write guard measures embedded weight as well as length, because a column alone cannot
  express the summary rule.** `EMBEDMAX` permits 87384 characters of base64 and `TEXT` holds 65535,
  so "the column already knows" is true above 65 KB and false below it: a 60 KB data URI fits a
  summary column, satisfies the parser and is then drawn twenty times onto one list page. The guard
  already walks the finished text before the query runs, so this is one more measurement inside a
  function that is being written anyway — no new boundary, no new call site, no new setting. A field
  that may not embed refuses a data URI at any size; a field that may refuses one over `EMBEDMAX`.
  This is what makes the client rule a rule rather than a decoration, and it is the only reason a
  direct POST cannot walk around it.
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

## 3. The contract

Three parts: what the settings say, what the schema holds, and how a field names which part of the
schema is its own.

### The settings part

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

**Embedding gains no setting**, and that is a decision rather than an omission. A setting here
would answer "may this visitor embed", and that is not the question the site has: the question is
whether the field the text lands in is a place for an embedded image, which is a property of the
field and not of its author. Section 2 records the full argument. So the policy lives in the width
of the column, embedding is open to everyone, and its bound is one constant, `Parser::EMBEDMAX`,
the same in every module and enforced at all three of the editor, the write guard and the render.
Inserting an image by its address is likewise open to everyone and bounded by nothing, because
it stores nothing.

### The schema part

Most of this part is in the tree: fifteen columns widened in commit `4b5b679f` and the seventeen
below are `MEDIUMTEXT` in all three schema paths. One column still has to move, and it is named
at the end of this part.

The editor is rendered by `getTplTextarea()` at 56 call sites in 18 modules, and where each one
writes decides what it can hold. These columns hold an authored text and are `MEDIUMTEXT`:

`comment.body`, `privat.body`, `news.body`, `forum.body`, `faq.body`, `help.body`,
`jokes.body`, `files.body`, `links.body`, `media.note`, `money.note`, `products.body`,
`message.body`, `newsletter.body`, `order.note` — joining `content.body` and `pages.body`,
which already were.

These are drawn many times onto one page and stay `TEXT` on purpose, which is how a field says it
is no place for an embedded image: `news.intro`, `files.intro`, `links.intro`, `media.intro`,
`money.intro`, `pages.intro`, `products.intro`, `users.block` — and `auto_links.intro` once it
arrives there, below.

`order.info` also stays `TEXT` and is not an editor field at all despite its neighbour being
one: it is filled by `filterFields()` from the structured order form, and the `info` textarea
on the admin page writes to `config/order.php` instead. It is in the room table all the same,
because the guard of batch 5 measures it: a column of this contract written by a path that never
rendered an editor is a guarded field, and a guarded field names a room the table carries rather
than falling back to the default that a forgotten call site gets.

`users.sig` is `TEXT` rather than the `VARCHAR(255)` it was, and for a reason of its own rather
than for embedding. That size was too small for what people already write: of 828 stored
signatures the longest is exactly 255 and 95 sit above 240, so a tenth were pressed against the
ceiling with no image involved. `TEXT` gives a signature room for prose beside a linked image and
stops well short of a data URI, which is the right shape for something repeated under every post.

`auto_links.intro` is the one column of this contract still to move, and it moves to `TEXT` for the
same reason `users.sig` did. It is `VARCHAR(255)`, and a rich editor writes into it: the public
submit form renders `getTplTextarea()` at `modules/auto_links/index.php:150` under the field name
`desc`, and `:193` inserts that value as `intro`. In utf8mb4 those 255 bytes are about 127 Cyrillic
characters, which is a sentence — for a field the site asks a visitor to describe a whole site in.
It joins the summary class rather than the body class: a link description is drawn once per row of
a link list, which is exactly the shape that must not carry a data URI.

**No editor column may be a third type.** The room table knows `text` and `mediumtext` and nothing
else, and the test in section 6 fails on any `store` whose column is neither. That is what turns
finding the next `auto_links.intro` from a matter of review into a matter of running the suite,
and it is the reason the room table is not given a `varchar` kind to accommodate this one: a
`VARCHAR` behind a rich editor is a defect to fix, not a shape to support.

All of it lives in `setup/sql/table.sql`, `setup/sql/table_update6_3.sql` and
`setup/sql/update6_3_patch.sql`, and the three agree column for column.

### The storage part

The widths above settle nothing on their own, because the editor is never told which of them
applies to it. `getTplTextarea()` (`core/helpers.php:536`) is handed `name` and `mod`, and passes `mod`
into exactly one place — `getUploadRuleData()`. Neither value identifies a column:

| What the editor has | Why it is not the storage |
| --- | --- |
| `name`, the form field | `hometext` and `bodytext` are stored as `intro` and `body`; shop sends `ptext` and `pbodytext`; files and links send `description` and `bodytext` |
| `mod`, the upload directory | eight call sites pass `all`, a shared directory that answers to no table |
| neither | money and order write their admin texts into `config/money.php` and `config/order.php`, `mailtext` into `$conf['mtemp']`, and `core/helpers.php:411` into a file the admin page edits |

So the call site declares it, as one added key that names the storage and nothing else:

- `'store' => 'news.body'` — a table and a column. One table in one place maps it to bytes, and
  the mapping is checked against the shipped schema rather than maintained by hand.
- `'store' => 'config'` — a configuration file. No column and no `ERROR 1406`, but not unbounded
  either: it takes the same room as `TEXT`, because a value written into a PHP file is loaded on
  every request that reads that config.
- absent — the narrowest field there is, `TEXT` and no embed mode, per the decision in section 2.

`getTplAjaxTextarea()` (`core/helpers.php:575`) sends `name => 'text'` for several different
targets and therefore takes `store` from its caller too, rather than deriving one.

### The room table

The map from a `store` to the room it has does not exist today; it arrives with `store` in batch 4.
It is one associative array in one function, and it exists because two places need the same number
and must not each work it out: the editor needs it while rendering, to decide whether the embed mode
is offered and what number to refuse with, and the write guard needs it before the query, to refuse
for real when the client was bypassed. Two copies of that number would drift, and drift silently —
the editor allowing what the write rejects, or the reverse.

**The value stored is the column type, not the byte count.** The count follows from the type without
ambiguity, while the type can be compared line for line against `setup/sql/table.sql`. A map of
numbers is checked by eye; a map of types is checked by a test.

| Room | Stores |
| --- | --- |
| `mediumtext` | the seventeen body columns listed above |
| `text` | `news.intro`, `files.intro`, `links.intro`, `media.intro`, `money.intro`, `order.info`, `pages.intro`, `products.intro`, `auto_links.intro`, `users.block`, `users.sig` |
| `config` | every field that writes to a file: the money and order admin texts, `mailtext`, and the adminfo text at `core/helpers.php:411` |

That is about twenty-eight keys for 56 call sites, because an add form and an edit form write to the
same column.

One converter turns a type into bytes — `text` is 65535 and `mediumtext` is 16777215 — and the
question "may this field embed" is **derived rather than stored**: a field may embed when its room
holds a whole image of `EMBEDMAX`. Base64 of 65536 binary bytes is 87384 characters, and the
`data:image/jpeg;base64,` prefix carries it past 87400, which `TEXT` cannot hold at all. So a summary
field refuses a data URI of any size rather than only a large one, and a body field accepts one up to
the cap. This is what the decision in section 2 means by "no per-field capability flag": there is no
second value in the table, because the width already answers.

`TEXT` bounds **bytes and not characters**, and `strlen()` is what the guard must measure. In
utf8mb4 a Cyrillic letter costs two bytes, so `TEXT` is about 32700 Russian characters rather than
65535 — which is the difference between a guard that fires and a guard that fires too late for the
sites this project serves.

The table lives in `core/helpers.php`, beside `getTplTextarea()`. It needs no file and no class of
its own: `core/system.php:152` loads the helpers on every request, and the write paths sit below
that. A test keeps it honest in both directions — it parses `setup/sql/table.sql`, reads the type of
every `table`.`column`, and asserts that no entry misstates the schema and that no call site names a
`store` the table does not carry. A mistyped column name then fails a test rather than costing an
author a post six months later.

## 4. Implementation batches

1. **Settings and the last column**
   - Add the one key `guestfiles` to `setUploadRuleData()` and `getUploadRuleData()`, with the
     fallback named above.
   - Move `auto_links.intro` from `VARCHAR(255)` to `TEXT` in all three schema paths. It rides
     here because it is isolated groundwork like the rest of this batch, and because it has to be
     in place before anything reads a column type: the room table would otherwise carry a `store`
     it cannot classify.
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
     (`core/classes/upload.php:562`, `?int $uid`) and in the matching pattern
     (`core/system.php:4391`, `([0-9]+)`). Both widen to alphanumerics together, and files
     stored under the old pattern keep resolving because digits remain a valid token.
   - The argument is renamed with the type: `?int $uid` becomes `?string $owner`. This is a direct
     migration and not a wrapper — six call sites pass it (`core/system.php:4354`,
     `admin/modules/uploads.php:175` and `:176`, `modules/account/index.php:1078`,
     `modules/files/admin/index.php:245`, `modules/files/index.php:547`) and every one of them
     keeps the value it passes.
   - **`null` is not a moderator and never was.** It means one thing at all six call sites — this
     file has no owner and its name carries no owner segment — and the admin file manager and the
     module attachment forms rely on exactly that meaning. The editor route reaches `null` for an
     administrator because `is_moder()` reads the admin session and an administrator need not be a
     site user at all, so there is no id to own the file with. That is the correct value, not an
     abuse of one, and it stays. No flag is added and nothing is untangled here, because privilege
     already travels separately: `$all = is_moder($mod)` in the listing route is what hands an
     administrator the whole directory, and it is the only place that decides it.
   - One trap comes with the widening. The matcher compares with `(int)$mat[1] === $uid`
     (`core/system.php:4391`), and an integer cast turns any non-numeric token into `0` — the
     value a guest carries today. Left as it is, every guest token would match every other guest's
     files, which is the privacy defect this batch exists to remove. The comparison becomes a
     string comparison with the widening, in the same edit.

3. **Server**
   - Remove the guest guard at `core/system.php:4386`. Whether a list is answered at all is
     decided by `checkEditorUploadAccess()` and by nothing else — including its first line, the
     moderator of the module, which stays and is the one role standing above the settings.
   - Choose the listing limit from three values rather than two. Which of the three applies is a
     question of role; what each of them is worth is a question of settings.
   - The upload routes gain nothing for embedding, and that is deliberate: no upload request
     carries a data URI, so there is no route here on which to refuse one. The place that can
     refuse it is the write path, which is batch 5.

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
   - The embed path checks the blob against `EMBEDMAX` **and against the list of embeddable
     types** before it inserts, and refuses with a message naming the limit or the type. Both the
     constant and the list are handed to the editor from PHP rather than copied into the
     JavaScript, so each has one definition.
   - `addEmbed()` (`editor-upload.js:389`) takes any `File` today and must not: it is the entry
     point for the picker, for a dropped file and for a pasted one alike, and the picker's `accept`
     attribute is stripped by code this batch deletes anyway.
   - A field that may not hold a data URI does not offer the embed mode at all, and it learns
     that from its room rather than from a flag of its own. The room comes from `store`, the key
     the call site declares: `getTplTextarea()` resolves it through the one room table and
     passes the byte count down beside the upload rule. It cannot be derived from `name` or from
     `mod`, and section 3 records why.
   - Add `store` at the 56 call sites. A call site that is missed renders a field that keeps
     working and offers no embed mode, which is the whole point of the strict default.
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

5. **Write guard**
   - This is its own batch and not part of the editor, because it lives on the write paths and
     because it has to hold for plain prose as well: 70 KB of text in a news article fails today
     the same way an embedded image would, and that is older than embedding.
   - One guard measures the finished text against the room the `store` names, before the query
     runs, and refuses with a message instead of letting `ERROR 1406` reach the author. It is
     `checkEditorTextRoom()` in `core/helpers.php`, beside the room table it reads, and it answers
     a ready message rather than a flag: a form adds it to the refusals it already collects, and a
     writer with no author to tell puts it in the log instead.
   - The same guard measures embedded weight **and type**, which is what turns the summary rule
     from a client decision into an enforced one. A field that may not embed refuses a data URI at any size; a
     field that may refuses one over `EMBEDMAX`. Without this, a 60 KB data URI posted directly
     fits a `TEXT` summary column, passes the length check and is drawn onto every row of a list
     page — see section 2.
   - It reads the same room table batch 4 reads, so the room has one definition for the editor
     that renders the field and for the path that stores it.
   - It measures bytes with `strlen()`, because that is what the column bounds. Section 3 records
     why this decides whether the guard works at all in a Cyrillic text.
   - **A guarded column is not the same thing as a guarded field, and the guard covers fields.**
     The same column can be written by a path that never rendered an editor: `content.body` is
     overwritten from a remote feed at `modules/content/index.php:82`, where `rss_read()` returns
     whatever the far end served and the `UPDATE` runs unmeasured. That write is in scope and calls
     the guard too, with a different action on refusal, because there is no author to tell — it
     keeps the body already stored and writes the reason to the log, since a stale item is a better
     answer than a lost one.
   - Any other non-editor writer into a column of section 3 is either brought to the guard or
     listed here as knowingly outside it. An empty list is not a claim that none exists; the sweep
     that establishes it is part of this batch.
   - The sweep found four more and brought all four to the guard. `money.intro` and `order.info`
     are packed from a structured form rather than typed into an editor, at
     `modules/money/index.php:129` and `modules/order/index.php:67`, and their admin twins at
     `modules/money/admin/index.php:250` and `modules/order/admin/index.php:132` write the same two
     columns from the record form.
   - Two writers touch a column of section 3 with a constant and are knowingly outside the guard:
     `admin/index.php:159` and `modules/account/index.php:210` insert an account with
     `block => ''`, which no request can lengthen. `newsletter.note` and `mail.body` are outside it
     too, and outside this contract: neither is an editor field, and the note is a status line the
     mail run writes rather than an authored text.
   - The `mailtext` field of `modules/account/admin/index.php:440` declares `config` and is stored
     nowhere: it is rendered into one mail body and queued. It keeps the strict room the declaration
     gives it, which is what decides whether it offers the embed mode, and there is no write for the
     guard to sit on.

6. **Proof**
   - It lands in two artifacts, because the claims divide in two. What a running stand is not
     needed for is `tests/Unit/EditorRoomTest.php`, which drives the room table and the write
     guard through `tests/Support/editor_probe.php` against the real core and reads the client
     half off the two files that own it. What only exists as a request handler is the `editor`
     mode of `tools/upload-route-check.php`, which walks the access matrix, the guest isolation
     and the write guard as real HTTP against a stand and restores `config/uploads.php` byte for
     byte afterwards.
   - The three client routes are proven where they meet rather than one by one: the window, a
     dropped file and a pasted one all reach `addImage()` and from there the one guarded
     `addEmbed()`, and the test holds that convergence together with the three checks inside it.
   - The matrix: guest, member and moderator, times `userupload` and `guestupload` on and
     off — driven as real HTTP requests, not as unit calls, and read back from `uploads/` and
     from the stored body. A moderator passes with both settings off, which is the role rule
     being asserted rather than tolerated.
   - An embed at exactly `EMBEDMAX` survives the round trip into every body column of
     section 3 and back out through the parser, and one byte over is refused by the editor
     before it is stored. This is the test the widening exists for, and it fails on `TEXT`.
   - The same embed offered into a summary field is refused by the editor, and a linked image
     into the same field is accepted — which is the pair that proves the restriction is about
     delivery and not about graphics.
   - A refusal has to hold when the client is bypassed, and on both routes: a direct POST to the
     upload route with the setting off is refused whatever the page rendered, and a direct POST
     of a body carrying a data URI is refused by the write guard — over `EMBEDMAX` into a body
     field, and at any size into a summary field.
   - A body of plain prose one byte past its column is refused with a message and never reaches
     the database, and the same holds for a Cyrillic body, where the column runs out at half the
     character count.
   - An SVG, a PDF and a file of unknown type are refused by the editor on all three routes into
     the embed path — picked in the window, dropped on the body and pasted from the clipboard —
     and a body carrying `data:application/pdf;base64,` posted directly is refused by the write
     guard. The five raster types are accepted on the same three routes.
   - The type list handed to the editor is the list the parser matches; a test compares them and
     fails on a type present in one and missing from the other.
   - A call site with no `store` renders a working editor without an embed mode.
   - The room table agrees with `setup/sql/table.sql` in both directions: no entry misstates a
     column type, and no `store` named at a call site is missing from the table.
   - No `store` resolves to a column that is neither `TEXT` nor `MEDIUMTEXT`. This is the test that
     would have found `auto_links.intro` without anybody reading the schema by hand.
   - A feed longer than `content.body` leaves the stored body untouched and a line in the log,
     rather than raising `ERROR 1406` on a page a visitor asked for.
   - Two guests in two sessions must not see each other's files.

## 5. Files to audit

- `core/system.php` — `getUploadRuleData()`, `setUploadRuleData()`, `checkEditorUploadAccess()`,
  `addEditorUpload()`, `getEditorFileJson()`, `getEditorFileData()`;
- `core/classes/upload.php` — the stored file name and its owner segment;
- `admin/modules/uploads.php` — the module rule form and its save;
- `plugins/editors/toastui/driver.php` and `plugins/editors/toastui/assets/editor-upload.js`;
- `templates/lite/partials/editor-toastui-files.html` and its admin twin;
- `core/helpers.php` — `getTplTextarea()`, `getTplAjaxTextarea()`, the room table, and the 56 call
  sites that gain a `store`;
- `core/classes/parser.php` and `getEditorImageData()` — the two copies of the embeddable type
  list that become one;
- the write paths the guard sits on, and `modules/content/index.php:82`, the one non-editor
  writer into a column of section 3 known so far;
- `setup/sql/table.sql`, `setup/sql/table_update6_3.sql` and `setup/sql/update6_3_patch.sql` —
  `auto_links.intro` moves in all three;
- `config/uploads.php`, the six locale files, and the tests that cover uploads.

## 6. Acceptance gates

- Access is decided in one function and nowhere else. `checkEditorUploadAccess()` answers "may
  this visitor upload or list", no editor file and no template has an opinion, and the only role
  rule in the project is its first line: a moderator of the module passes. Every other visitor is
  answered by `userupload` and `guestupload`. Embedding asks nobody, because it is open to
  everyone.
- Where the role still appears, it chooses between settings and never replaces one: which listing
  limit of the three applies is a question of role, what each limit is worth is a question of
  settings.
- A stored rule written before this release keeps working untouched, and the first save
  normalises it to the full key order without changing any value it already carried.
- A guest allowed to upload sees their own files and no other guest's, in the same session,
  and none of them in the next one.
- The editor never embeds past `EMBEDMAX` and never embeds a type the parser will not render, and
  both hold for the window, for a pasted image and for one dropped on the body alike.
- No stored text carries an embed the field may not hold, by size or by type, whatever produced
  the request. The editor refuses first so the author is told at the moment they act, and the write
  guard refuses again so a direct POST is told the same thing. `Parser::checkImageSource()` stays
  the render bound and is no longer the only one.
- The list of embeddable types has one definition. The parser, `getEditorImageData()` and the
  editor read it from the same place, and a test fails if they disagree.
- The seventeen body columns of section 3 are `MEDIUMTEXT`, and the nine summary columns and
  `users.sig` are `TEXT`, in all three schema paths — fresh install, 6.2 upgrade and production
  patch — and the three agree column for column and index for index.
- Every column an editor writes into is one of those two types. No `VARCHAR` sits behind a rich
  editor, and the suite says so rather than a reader.
- Every call site of `getTplTextarea()` names its storage, and the names resolve against the
  shipped schema rather than against a hand-kept list. A call site that names none renders a
  field with the room of `TEXT` and no embed mode.
- **No guarded write is ever refused by the database.** A body too long for its column is refused
  by the write guard, with a message, before the query runs. The gate says "guarded" and not "no
  stored text", because the guarded set is the editor fields plus the one non-editor writer named
  in batch 5; a column is not sealed by widening the fields that reach it.
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
- The write guard is not a sanitiser. It measures two things — the length of the text and the
  weight of what it embeds — and refuses or passes. Escaping, tag policy and trust boundaries stay
  where they are, in `filterTrustedTags()` and the parser.
- No retroactive pass over stored texts. The guard holds what is written from here on; a row
  already carrying an oversized data URI keeps failing to render, as it does today.
