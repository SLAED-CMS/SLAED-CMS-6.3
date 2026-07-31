# Versions

## 2026-07-29

### If the installation already runs 6.3

One file brings it up to date: **`setup/sql/update6_3_patch.sql`**, pasted into
**Administrator panel → Database → Inquiry** (`admin.php?name=database&op=dump`). That
page substitutes `{prefix}` itself, so the same file serves any prefix, and its parse
action shows the statements before anything executes.

Four sections, in this order:

1. `pid` and `path` on `{prefix}_comment` with their two indexes, and the backfill that
   makes every stored comment a root of its own — where the reply threads live.
2. The `{prefix}_admins` column types the fresh schema declares. The upgrade file used
   to declare `editor` as `BOOLEAN`, which fails on any installation whose
   administrators carry an editor name, and because an `ALTER` is all or nothing the
   twelve other columns of that statement went down with it.
3. `{prefix}_users.points` back to `NOT NULL DEFAULT 0`. The upgrade declared that
   column twice and the nullable declaration won.
4. The `comments` column of the eight target tables, brought in line with the comments
   really published under them.

It drops nothing, deletes no row, touches no comment and recalculates no user point.
Columns and indexes are added only when absent and every counter statement writes only
the rows that disagree, so a second run changes nothing.

Nothing has to be scheduled afterwards: every comment write recomputes the counter of
its own target, so section 4 is a one-off repair of what drifted before. What it cannot
reach is a target nobody has commented on since — the comments section of the panel
reports those on its first tab and repairs them on a click, and
`tools/comment-recount.php report|fix` does the same from the shell.
`php tools/comment-migrate.php` is still required if the body migration has never run
here — see the comment subsystem notes below.

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

- The upgrade adds seven columns to `{prefix}_comment` (`format`, `edited`,
  `deleted`, `reqkey`, `iphash`, `pid`, `path`), creates seven indexes, drops the
  two the composites supersede (`cid`, `modul_status`) and backfills `reqkey` and
  `path` for every existing row.
- **This is not instant on a large table.** Every index build and every backfill
  rewrites the table, and InnoDB holds the rows while it does. It took under a
  second against 7357 rows; on a table with orders of magnitude more it is a
  maintenance window, not a page reload. Close the site for it rather than letting
  a visitor discover it during a lock.
- **`php tools/comment-migrate.php` is mandatory, not optional.** From 6.3 a
  comment body is the source its author wrote and the parser escapes it on read,
  so until the migration has run every stored body still carries the escaping of
  the old writer and is displayed with it — `&lt;b&gt;` instead of a bold word.
  Run `classify`, read its report, then `convert`, then `iphash`. The tool keeps a
  ledger in `storage/migrate/`, marks every row it finishes, stores the body it
  replaced, and takes `--db=` so the whole run can be rehearsed against a restored
  copy first.
- The migration is idempotent: a second run converts nothing twice.
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
