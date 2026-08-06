# Versions

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
exactly one of the three:

| Coming from | File | How |
|---|---|---|
| a new installation | `setup/sql/table.sql` | the installer, nothing to do |
| 6.2 | `setup/sql/table_update6_3.sql` | the installer, per the section below |
| already 6.3 | `setup/sql/update6_3_patch.sql` | **Administrator panel → Database → Inquiry**, section 5 |

What the section does to `{prefix}_privat`: it adds `saved`, `delin` and `delout`,
carries the saved messages over from `status` before that column is renamed to
`viewed`, forces `viewed` onto `TINYINT UNSIGNED NOT NULL DEFAULT 0` — the old
declaration was `BOOLEAN` while the code stored `2` — and replaces the three
single-column keys `uidin`, `uidout` and `status` with the composites
`in_box`, `in_new`, `out_box`, `out_new` and `flood`. It deletes no row and no
message, and the row count it starts from is the row count it ends on. It is safe
to run twice, and safe to run again after a crash: a re-run reads the shape the
table is really in and finishes only what is missing. All three files end on the
same table definition, byte for byte.

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

### If the installation already runs 6.3

One file brings it up to date: **`setup/sql/update6_3_patch.sql`**, pasted into
**Administrator panel → Database → Inquiry** (`admin.php?name=database&op=dump`). That
page substitutes `{prefix}` itself, so the same file serves any prefix, and its parse
action shows the statements before anything executes.

Five sections, in this order:

1. `{prefix}_comment` on its final shape: `pid` directly behind `id` for the reply
   tree, `edited` and `deleted` for the moderation marks, `reqkey` as the binary
   idempotency key under one unique index, `time` required, `ip` byte-compared for
   the flood interval, and the index set the real list, count and thread predicates
   are read through. Every stored comment stays a root; nothing is re-parented. Two
   guards stop the run before it changes anything: a `reqkey` found as hex text and
   a `NULL` in `time`.
2. The `{prefix}_admins` column types the fresh schema declares. The upgrade file used
   to declare `editor` as `BOOLEAN`, which fails on any installation whose
   administrators carry an editor name, and because an `ALTER` is all or nothing the
   twelve other columns of that statement went down with it.
3. `{prefix}_users.points` back to `NOT NULL DEFAULT 0`. The upgrade declared that
   column twice and the nullable declaration won.
4. The `comments` column of the eight target tables, brought in line with the comments
   really published under them.

It deletes no row, touches no authored text and recalculates no user point. Columns
and indexes are added only when absent, every counter statement writes only the rows
that disagree, and the only drops are the columns and the keys the final contract
removes, which are no-ops where an installation never carried them. A second run
changes nothing.

Nothing has to be scheduled afterwards: every comment write recomputes the counter of
its own target, so section 4 is a one-off repair of what drifted before. What it cannot
reach is a target nobody has commented on since — the comments section of the panel
reports those on its first tab and repairs them on a click, and
`tools/comment-recount.php report|fix` does the same from the shell.

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

If an installation has to be updated by hand, run the installer for the upgrade and
use the SQL page only for the standalone repair files, which are written for it and
remove nothing — `setup/sql/update6_3_patch.sql` is one of those.

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
  their live count. Three ways in, all writing the same numbers:
  - `php tools/comment-recount.php report` reads only and prints every target that
    disagrees, `fix` writes the live count back;
  - `setup/sql/update6_3_patch.sql` through **Administrator panel → Database →
    Inquiry**, for an installation that already runs 6.3 and is updated by hand. Its
    fourth section is this sweep;
  - the first tab of the comments section, which reports what is left and repairs it
    on a click.
  All three are safe to repeat: only rows that disagree are written, so a second
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
