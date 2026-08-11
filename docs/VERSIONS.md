# Versions

## 2026-08-07

### One window and one icon insert an image, and a denied upload no longer means an unbounded one

The image dialog and the file catalogue are one window behind one toolbar icon. The
vendor image popup is removed from the toolbar, and what replaces it is our own markup:
the image address with its description, the file picker with its drag target, the three
insert modes, the stored-file list and the limits block, all siblings in one window
instead of two windows held together by measured geometry. Nothing in the editor reaches
into a popup it does not own any more, and the link dialog and the emoji window are
untouched.

The icon is there for every visitor. Inserting an image by its address touches no file
and no server, so it is available to everyone, always — and a visitor allowed nothing
beyond that opens the same window and finds the address field in it, which is an honest
answer where a missing icon would not be one.

A visitor who may not upload no longer gets a *worse* path than one who may. Dragging an
image onto the editor, pasting one from the clipboard and picking one in the window all
go through one bounded path from this release on: at most `Parser::EMBEDMAX` and only a
type the parser will draw. Until now the editor left that event to the vendor default
whenever uploading was denied, and that default base64-encoded any picked file straight
into the body — no extension check, no size check, no dimension check — so a
three-megabyte photo became four megabytes of base64 in the row and then did not display
at all.

### Whether a field may hold an embedded image is a property of the field

Every editor call site now names where its text is stored, and one table turns that name
into the room the storage has. A field wide enough for a whole embedded image offers the
embed mode; a summary, a signature or a link description does not, and offers the
address field instead. That is not a restriction on graphics: a linked image is accepted
in all of them, and it is the better delivery there anyway, because a list page draws the
summary of twenty rows and the browser fetches a linked image once instead of re-sending
a base64 copy twenty times.

A call site that names no storage renders a working editor with the room of `TEXT` and no
embed mode, so a forgotten one costs its author a button and never costs them a post.

The five image types the parser will draw have one definition, `Parser::EMBEDIMG`. The
render bound, the upload adapter and the editor read it from there, and a test fails if
they disagree.

### No stored text is longer than the column that holds it

One guard measures a finished text against the room its field declares, before the query
runs, and refuses with a message instead of letting the database answer `ERROR 1406` and
the author lose the post. It measures bytes and not characters, because bytes are what a
column bounds — in utf8mb4 a Cyrillic letter costs two of them, so a `TEXT` column runs
out at about 32700 Russian characters rather than 65535. That holds for plain prose as
much as for an embedded image: 70 KB of text in a news article was refused by the
database before this release and is refused with a message now.

The same guard measures what a text embeds, so the field rule is enforced and not merely
offered. A field that may not hold a data URI refuses one at any size; a field that may
refuses one over `Parser::EMBEDMAX` or of a type the parser will not draw. The editor
still refuses first, so an author is told at the moment they act, and a request that
never rendered an editor is told the same thing.

The one writer into a guarded column that has no author to tell is the content module: an
item with a feed address rewrites its body from whatever the far end served. A feed that
does not fit leaves the stored body untouched and writes the reason to the site log, since
a stale item is a better answer to a visitor than a lost one.

### The editor file panel is answered by the settings, not by the role

A guest allowed to upload now sees a file list. Until this release the editor listing
route refused every visitor who was not logged in, whatever
`admin.php?name=uploads&op=config` said, so a guest could upload a file under
`guestupload` and never see it again — not even their own, not even once. That rule is
gone: whether a list is answered at all is decided by the module upload settings and by
the module moderator, which is the one role standing above them.

A guest is shown the files of their own session and no other guest's, which rests on the
owner token below. The next session is a new session, and the files of the old one are no
longer listed.

The listing limit is chosen from three values instead of two. A moderator is bounded by
`moderfiles`, a member by `userfiles` and a guest by `guestfiles` — the field the release
below adds, which this one gives something to bound. Which of the three applies is the
only role question left on the route; what each of them is worth is a setting.

### A stored file belongs to a token, so one guest is no longer every guest

The owner segment of a stored upload name — the `-42` in `news-a1b2c3d4e5-42.png` — is an
alphanumeric token instead of a number. A member still owns their files by their user id
and a privileged upload still carries no owner segment at all, but a guest now owns theirs
by a token derived from the session rather than by the shared `0` every guest carried. Two
guests are therefore two owners, which is what a per-guest file list has to rest on.

The token is derived from the session and is never the session id itself, because the
segment ends up in a public file name and must authenticate nothing when it is read off a
URL. It lasts as long as the session: the next session is a new one, and the files of the
old one are no longer listed.

Files stored under the old pattern keep resolving, because digits are still a valid token,
and nothing already in `uploads/` is renamed or moved.

The per-guest file list above rests on this token: without it every guest would carry the
same owner and would see the uploads of every other guest.

### A link description is no longer one sentence, and the guest file list gets its own limit

`{prefix}_auto_links.intro` moves from `VARCHAR(255)` to `TEXT`. Those 255 bytes are
about 127 Cyrillic characters in utf8mb4 — one sentence for a field the site asks a
visitor to describe a whole site in. It joins the summary class rather than the body
class and stays `TEXT`: a link description is drawn once per row of a link list, which
is exactly the shape that must not carry an embedded image. It is the last column of
the schema work below that had not moved yet, and with it every column a rich editor
writes into is `TEXT` or `MEDIUMTEXT` and no `VARCHAR` sits behind an editor.

**Deployment:** a 6.2 upgrade and a fresh install carry the column already. No data is
lost or rewritten — the column only gets wider, and no stored description changes.

The upload rule of a module gains a thirteenth field, `guestfiles`, with its own input
on `admin.php?name=uploads&op=config` beside the two upload switches. A rule stored
before this release is one field short and keeps working untouched: the missing position
answers the user limit rather than zero, because zero means *no limit* to the reader and
would hand an unbounded list to the one role that never had one. The first save
normalises the rule to the full field order without changing a value it already carried,
and every save after that reproduces it.

The limit bounds the guest file list described above.

## 2026-08-05

### Private messages store source, and both fields are rendered safe

A private message is the source its author wrote from this release on. The body is
rendered by the parser with `safe = true`, the title is plain text escaped where a
template prints it, and no stored value is trusted HTML any more. **This release follows the state-model release below it and
must not be deployed before that one is live**, because it reads the columns that
release adds.

> **Superseded on 2026-08-06 by the content contract.** This entry originally
> shipped a `format` column on `{prefix}_privat` and a mandatory
> `tools/privat-migrate.php` run that rewrote stored bodies in place. Neither
> exists any more: the column is gone from all three SQL channels, the tool is
> deleted, and no text migration is part of any release. What survives is the part
> below — a message is stored as source and rendered safe — and it needs no
> conversion pass to be true.

A body is read through one contract. `plain` and the editors are input interfaces,
not storage formats, and nothing in the rendering branches on the editor an author
happened to type in. That is why no column names a syntax and why there is nothing
to classify: the same stored bytes render the same way whichever editor wrote them.

No deployment step is needed for this beyond the runtime code itself. The schema
work belongs to the state-model entry below, which stays exactly as it was.

What changes for the people using the site:

- A message is stored as it was written and escaped when it is read. Markup a
  sender types is text on the recipient's screen instead of live HTML, while
  Markdown, the bracket tags and the smilies still render.
- The editor an author writes in is an input interface and nothing more. An HTML
  editor is not a trust grant either: its markup is stored as source and escaped
  like any other, and the reading side never asks which editor produced a message.
- The subject line is plain text now. It carries no markup, it is stored decoded,
  and the template escapes it where it prints it — in the mailbox list, in its
  attribute and in the administrator panel alike. The panel used to decode it back
  on read to compensate for a writer that no longer exists.
- An administrator reads a message body through the same safe renderer its
  recipient does. Access to private-message contents in the `privat` section is a
  deliberate system policy, super-administrator only as before, and no raw stored
  body reaches a template on that path.
- One limitation the state model cannot repair either: a message
  either side deleted while both sides still shared one row is gone from the
  database and cannot be reconstructed for the other participant. That is recorded
  rather than papered over with a placeholder message.

### Private messages: four independent states instead of one shared column

The private-message subsystem is now one class, `Privat`, and one message carries
four state columns instead of the single `status` it shared between both
participants. **The schema section and the runtime code of this release are
deployed together.** An installation that applies the section while still running
code that reads `status` answers an SQL error on every private-message page until
the code follows it, and code deployed before the section does the same in the
other direction.

Which file an installation needs depends on where it comes from, and it is
exactly one of the two:

| Coming from | File | How |
|---|---|---|
| a new installation | `setup/sql/table.sql` | the installer, nothing to do |
| 6.2 | `setup/sql/table_update6_3.sql` | the installer, per the section below |

What the upgrade does to `{prefix}_privat`: it adds `saved`, `delin` and `delout`,
carries the saved messages over from `status` before that column is renamed to
`viewed`, forces `viewed` onto `TINYINT UNSIGNED NOT NULL DEFAULT 0` — the old
declaration was `BOOLEAN` while the code stored `2` — and replaces the three
single-column keys `uidin`, `uidout` and `status` with the composites
`in_box`, `in_new`, `out_box`, `out_new` and `flood`. It deletes no row and no
message, and the row count it starts from is the row count it ends on. It is safe
to run twice, and safe to run again after a crash: a re-run reads the shape the
table is really in and finishes only what is missing. Both files end on the same
table definition, byte for byte.

Building five indexes rewrites the table, and InnoDB holds the rows while it
does. On a large private-message table that is a maintenance window rather than a
page reload — the same rule the comment upgrade below already carries.

One documented consequence of the conversion: a message that was saved under the
old model becomes `viewed = 1`, because `status = 2` carried no read bit of its
own. Saving required opening the message, so read is the correct assumption.

What changes for the people using the site:

- A recipient deleting a message no longer removes it from the sender's outbox,
  and the reverse. Until now one delete destroyed the single shared row.
- A message the recipient saved stays visible in the sender's outbox, where it
  used to vanish the moment it was saved.
- A sender may delete an outgoing message the recipient has already read. It used
  to stay in the outbox forever.
- Saving no longer discards the read state, and a saved message no longer becomes
  undeletable for the sender.
- Read and unread are two actions now, not one transition, and inbox, saved and
  outbox carry bulk read, unread, save and delete.
- A send is refused when the saved folder of the recipient is full and not only
  when their inbox is, so the mailbox a message cannot fit into is the whole
  mailbox. Both quotas and the send interval are rechecked inside the write
  transaction, under a lock on both accounts, so two simultaneous sends can no
  longer both take the last free place.
- The notification mail reads the `psmail` preference of the recipient, which is
  the setting the account page has always offered and which had no effect until
  now — the code read the forum preference `fsmail` instead. Its link carries the
  id of the message that was really stored.
- Deleting an account now cleans its mailboxes in the same transaction that
  deletes the account, on both paths that delete a user row. The counterpart keeps
  a readable copy with the gone account rendered as an anonymous sender.
- The second administrator delete route, a GET carrying its token in the address,
  is gone. One POST route remains, super-administrator only as before.

## 2026-07-29

### Upgrade 6.2 → 6.3 must go through the installer, not through the SQL page

`setup/sql/table_update6_3.sql` is **not the whole upgrade**. Five steps of the 6.2 → 6.3
migration live in PHP, in the `update6_3` branch of `setup/index.php`, and pasting the
SQL file into **Database → Inquiry** performs none of them. The schema will look
correct — an upgrade of a real 6.2 database was compared against a fresh
`setup/sql/table.sql` install, table by table: 38 tables, 494 columns, 171 indexes,
engine and collation, zero differences, and the file is idempotent — but the data
around it will not be.

What the SQL file alone does **not** do:

1. **Administrator module permissions stay numeric and stop matching.** In 6.2 the
   `modules` column of `{prefix}_admins` held numeric ids from the `{prefix}_modules`
   table; 6.3 stores module names. `getAdminModuleNames()` (`core/system.php:998`)
   splits the column and does not translate ids, so every administrator who is not a
   super administrator silently loses **all** module permissions, comment moderation
   included. Only the installer rewrites the column.
2. **`config/modules.php` is not rebuilt.** 6.3 moves the module registry out of the
   `{prefix}_modules` table into that config file. The installer scans
   `admin/modules/*.php` and `modules/*`, folds in the old table and the existing
   file, and writes it.
3. **Pending newsletter recipients are lost.** The upgrade drops
   `{prefix}_newsletter.mails` (line 1696 of the SQL file), and it is the installer
   that reads those addresses first and writes them into the new mail queue
   afterwards. The SQL file does not carry them anywhere. Check before a manual run:
   `SELECT id, title, mails FROM {prefix}_newsletter WHERE mails IS NOT NULL AND mails != ''`
   — if that is empty, nothing is lost.
4. **The mail queue is never drained.** 6.3 stores outgoing mail instead of sending it
   inside the request, and the `maildrain` scheduler job is what delivers it. The
   installer seeds that job into `config/scheduler.php`; without it **all outgoing
   mail stops**.
5. **`config/newsletter.php` is not created**, so the campaign limits fall back to
   nothing.

The upgrade runs through the installer and nowhere else. The SQL page parses and
executes what it is given, but it performs none of the five PHP steps above, so a
schema pasted into it leaves the data around it wrong.

### Upgrade notes for the comment subsystem

Read this before running `setup/sql/table_update6_3.sql` on an installation with a
large comment table. **Take a dump of `{prefix}_comment` first and rehearse the
restore.**

- The upgrade adds four columns to `{prefix}_comment` (`pid`, `edited`, `deleted`,
  `reqkey`), places `pid` directly behind `id`, makes `time` required, stores `ip`
  under a binary ascii collation, creates the index set the real list, count and
  thread predicates are read through, and drops the keys those supersede (`cid`,
  `modul_status`). No column named `format`, `iphash` or `path` is created, no
  comment rate table is built, and no idempotency key is minted for an existing
  row: a comment written before this release was never replayed and needs none.
- Two guards stop the run instead of guessing, and both stop it before anything
  else has changed: a `reqkey` found as hex text, which means the table was carried
  through a transitional release, and a `NULL` in `time`.
- **This is not instant on a large table.** Every index build rewrites the table,
  and InnoDB holds the rows while it does. It took under a second against 7358
  rows; on a table with orders of magnitude more it is a maintenance window, not a
  page reload. Close the site for it rather than letting a visitor discover it
  during a lock.
- **No text migration is needed and none is shipped.** A comment body is the
  source its author wrote and is rendered through one contract; nothing branches on
  an editor and no column names a storage format.
- The upgrade is idempotent: a second run changes neither schema nor data.
- Deleting a user no longer orphans their comments. The rows stay and lose only
  the reference to the account, so discussions and reply branches survive.
- **Repair the comment counters after the upgrade.** The `comments` column of the
  eight target tables is denormalised, and until 6.3 the counter could be moved for
  the wrong target by a request-supplied module name. The write path is fixed, the
  residue is not: on the reference installation 23 of 885 targets disagreed with
  their live count. Two ways in, both writing the same numbers:
  - `php tools/comment-recount.php report` reads only and prints every target that
    disagrees, `fix` writes the live count back;
  - the first tab of the comments section, which reports what is left and repairs it
    on a click.
  Both are safe to repeat: only rows that disagree are written, so a second
  run reports zero affected rows. None of them touches the comment table, and no
  user points are recalculated. Beyond them the counter maintains itself: every
  comment write recomputes the target it touched instead of nudging it by one.
- New setting `comments.reps` (default 5): how many replies a page shows under one
  comment before it offers to load the rest. It bounds what one long discussion can
  put in front of a reader; the remaining replies stay reachable both through the
  control and, without JavaScript, through the `&all=` link it carries.

### Fixed in the upgrade file itself

- `{prefix}_users`: the file modified `network` and then dropped it further down,
  so a re-run failed and discarded the type normalisation of thirteen other
  columns with it. The upgrade is idempotent for that table again.
- `{prefix}_admins.editor` was declared `BOOLEAN` while `setup/sql/table.sql`
  defines `VARCHAR(32) NOT NULL DEFAULT 'plain'`. It failed on any installation
  whose administrators carry an editor name, and would have destroyed those names
  had it passed.
- `{prefix}_users.points` was declared twice with two different definitions, and
  the second one undid the first. `table.sql` now agrees at `NOT NULL DEFAULT 0`.
- `SchemaUpdateValidationTest` compares column definitions between the fresh
  schema and the upgrade, and rejects a column declared twice with two
  definitions, so this class of defect fails a test instead of an installation.

## 2026-07-10
- Added the route-aware SEO head contract with safe HTML/JSON serialization, canonical and robots policy, Open Graph, and typed JSON-LD.
- Normalized frontend `H1-H3` ownership, card and voting contexts, comments, related content, tables, landmarks, and category breadcrumbs.
- Added scoped Markdown heading offsets for article, card, comment, block, and forum contexts.
- Added `SeoSemanticsValidationTest` and the executable `tools/seo-audit.php` HTTP contract audit.
- Corrected the English and Ukrainian Open Graph locale identifiers. `hreflang` remains disabled until languages have stable public URLs.

## 2026-05-20
- Responsive baseline closed for `lite` and `admin`.
- `lite` mobile/tablet layout fixed without changing the desktop visual style.
- Top menu, dropdowns, table wrappers, forum posts, comments, cards, media, and admin forms were verified after the CSS updates.
- Browser verification was completed with Playwright and Chromium.
- Authenticated admin pages were verified after login.
- Remaining component-specific backlog items are non-blocking: CodeMirror editor surface on the admin template page, statistic chart fixed-width images, and monitor widget internal widths.
