# Comments Subsystem Redesign

Status date: 2026-07-27. Approved, not started. The comment engine is shared by
eight frontend modules, the admin panel, the user activity feed and every module
delete handler, so each stage below has to keep all of them working while the
internals move into one place.

## Facts (measured 2026-07-27)

- `ashowcom()` (`core/system.php:5477-5729`) is **252 lines** carrying SQL,
  permissions, pagination, module links and HTML assembly at once.
- Eight modules render comments through `setComShow($id, $acomm)`: `faq`,
  `files`, `links`, `media`, `news`, `pages`, `shop`, `voting`.
- The admin panel does **not** call `ashowcom()` — `admin/modules/comments.php`
  builds its own table. The eight `defined('ADMIN_FILE')` branches inside
  `ashowcom()` are therefore unreachable.
- Adding a comment returns and replaces the whole list: one POST answers with
  51059 bytes. Edit and status actions already update a single comment region.
- Adding a comment takes **26.7 s**, of which `addAdminMail()` is 26.6 s
  (13 recipients, ~2.05 s per `mail()` call) and rendering is 0.02 s.
- `EXPLAIN` on the live list query: `type=ref key=cid rows=20`,
  `Extra=Using where; Using filesort` — no composite index backs the sort.
- The flood check runs `WHERE ip = ?` with **no index on `ip`**.
- Table `_comment`: 7355 rows, `body` is `TEXT`, the IP is stored in clear text.
  Distribution: files 4821, news 1088, voting 1083, faq 141, pages 116,
  links 104, shop 2. Status: 7351 published, 4 pending.
- Validation is duplicated in `addComment()` and `updateComment()`. The word
  length defect fixed in the first copy still lives in the second
  (`core/system.php:5752`).
- Replying inserts `[b]name[/b],` into the editor: there is no thread structure.
- `acomm` is a three-state mode per target row: `0` = disabled (`_DEACTIVATE`),
  `1` = moderated (`_APOSTMOD`), `2` = open (`_APOSTNOMOD`). The final status of
  a submission also depends on the global anonymous-posting mode, moderator
  permissions and user access restrictions.
- Direct `_comment` consumers outside the render path:
  - frontend write handlers — `core/user.php` (flood check, insert, last id),
    `core/system.php` (`updateComment()`, `updateCommentStatus()`)
  - admin module — `admin/modules/comments.php` (module list, search list, edit
    save, bulk actions, approve, delete, pager table binding)
  - user activity feed — `core/user.php` UNION branch
  - installer schema — `setup/sql/table.sql`, `setup/sql/table_update*.sql`
  - module deletion handlers — `faq`, `files`, `links`, `media`, `news`,
    `pages`, `shop`, `voting` admin modules
  All of them must be migrated before direct table access is removed.

## Problems this causes

1. **Adding repaints everything.** A successful add returns the complete comment
   list, transfers about 51 KB and replaces the whole list container; scroll
   position and focus are lost.
2. **No feedback on premoderation.** The new comment is invisible in the
   returned list, so the user assumes failure and submits again — and a
   duplicate is created, because nothing is idempotent.
3. **Unreadable core.** A 252-line function with 18-slot `list()` unpacking and
   an `O(n*m)` nested loop matching users to comments.
4. **Divergent copies.** Two validation paths mean a fix lands in one of them.
5. **No threads.** Discussions are flat; replies are a naming convention.
6. **Blocking mail.** The request waits for SMTP once per recipient.
7. **Storage.** Indexes do not match the real access pattern, the flood query
   has none at all, and there is no soft delete and no edit timestamp.

## Target design

### Data

The table keeps its name and every existing column. Nothing is renamed, nothing
is rewritten:

```
{prefix}_comment
  id, cid, modul, time, uid, name, ip, body, status     -- unchanged
```

Added in stage 2:

```
  edited    DATETIME NULL
  deleted   DATETIME NULL
  reqkey    BINARY(16) NULL
  iphash    BINARY(16) NULL
```

Added in stage 5:

```
  pid       INT UNSIGNED NULL
  path      VARCHAR(255) NULL
```

Column names stay single-word, matching the existing schema, where no column
uses an underscore and `pid` already means parent in `{prefix}_forum`.

Indexes:

```
INDEX (modul, cid, status, deleted, time, id)
INDEX (modul, cid, deleted, time, id)
INDEX (iphash, time, id)
UNIQUE (reqkey)
```

Stage 5 adds:

```
INDEX (modul, cid, path)
INDEX (pid)
```

`ip` stays for admin search and GeoIP; `iphash` serves flood control only.
Dropping the plain address is a separate task, after the moderation policy is
settled.

Query rules: public reads use `status = 1` instead of `status != 0`, and from
stage 2 every listing sorts by `time, id` so equal timestamps keep a stable
order. Stage 1 keeps the current sort untouched.

### Identifier confusion to remove

The request parameter named `cid` is **not** the table column `cid`. Today the
comment form sends the target's `acomm` mode under that name
(`core/user.php:45`), while `_comment.cid` holds the target row id. This is the
single most confusing part of the current code and it goes away:

- the request loses `cid` entirely;
- `acomm` is loaded from the target row on the server, never accepted from the
  client;
- on edit, status and delete, `modul` and `cid` are read from the comment row by
  its id, not taken from the request;
- the target table is resolved through a fixed list of the eight supported
  modules — never by interpolating a module name from the request.

### Code

One class, `Comments` in `core/classes/comments.php`, built with its
dependencies and using the existing column names throughout:

```php
$com = new Comments($db, $prs);
$rows = $com->getList($modul, $id, $page);
$com->add($modul, $id, $body);
```

Author, uid, ip, `acomm` mode and permissions are resolved **inside** the class
from the server context — never taken from the request.

`Comments` owns SQL, validation, permissions and state changes. It returns data
and semantic flags. `setComShow()` renders the existing template fragments. The
point of the work is to remove the HTML monolith, not to move it into a class.

Public methods: `getList()`, `getAdminList()`, `getUserList()`, `getCount()`,
`add()`, `update()`, `setStatus()`, `delete()`, `deleteTarget()`,
`checkRules()`. Stage 5 adds `getBranch()`.

Two enums:

```
CommentStatus: Pending = 0, Published = 1
CommentMode:   Disabled = 0, Moderated = 1, Open = 2
```

`setComShow()` stays as the rendering function. `ashowcom()` is deleted once its
callers move, in the same stage — no wrapper, no transitional alias.

Every direct SQL statement against `_comment` moves into `Comments`: frontend
list/add/edit/status, the admin module, the user activity feed, the deletions
performed when a target row is removed, bulk moderation and the counters.

### Parser

`body` keeps the current normalized stored content; the parser renders on read,
as today. No persisted HTML.

"Normalized" is deliberate: `filterHtml()` (`core/security.php:973`) already
rewrites the submitted text before it is stored — it runs `filterClickable()`,
`stripslashes()`, escapes `$`, `\`, quotes, and applies `nl2br()` in plain mode.
The stored value is therefore not the raw submission today either.

Stage 2 must define the concrete storage behaviour per editor format, since
`Editor::getFormat()` returns `plain`, `markdown` or `html`:

- what `safe = true` means for each of the three formats;
- which transformation happens on write and which on read;
- legacy content keeps its current appearance;
- `[hide]`, theme differences and module replacement rules each behave as
  before.

### Mail dependency

Comment notifications are not solved here. Outgoing mail is a system-wide
concern — 26 call sites, a private newsletter queue and a synchronous
`mail()` — and it has its own plan, `docs/MAIL-QUEUE-2026.md`. This redesign
consumes it: once the queue exists, the comment path stores a job instead of
calling `addAdminMail()`, and the job is written inside the comment transaction.

Ordering: the mail queue ships **before** stage 3 of this plan. It is useful on
its own, independent of comments, and it is what removes the 26.6 s from the
submit path.

### Soft delete

- `delete()` sets `deleted` once; a second call changes nothing;
- regular list, count and pager queries filter on `deleted IS NULL`;
- a repeated delete never touches counters or points again;
- deleting a pending comment does not decrement the public counter;
- `deleteTarget()` removes the comments of a deleted target row physically —
  there is nothing left to reference them;
- from stage 5, a deleted comment that still has replies stays as a tombstone
  so the branch does not break.

### Transactions

Each operation wraps **only its own writes**, using the existing API:
`$db->setSqlBegin()`, `$db->setSqlCommit()`, `$db->setSqlRollback()`. Any SQL
error rolls the whole operation back.

| Operation | Inside the transaction |
|---|---|
| add | comment row, target counter, user points, `reqkey`, mail job (from stage 3, only when notifications are enabled) |
| update | comment row, `edited` |
| setStatus | comment row, target counter, user points |
| delete | comment row `deleted`, target counter, user points |

The new row id comes from `$db->getSqlLastId()`. The current re-`SELECT` of the
last inserted comment (`core/user.php:304`) is removed: it is both redundant and
racy.

A repeated `setStatus()` with the same status must not touch counters or points
again.

All participating tables are InnoDB — verified on this installation for
`_comment`, `_users` and every target table — so the transaction is real. The
migration must keep it that way, including `_mailqueue`.

### Idempotency

- the browser generates a random `reqkey` per form;
- the database guarantees uniqueness through the unique index;
- a repeated POST returns the result of the first operation;
- the key is regenerated only after a successful response;
- `reqkey` participates in `add` only — edit, status and delete are naturally
  idempotent through their own state checks;
- two concurrent POSTs create exactly one row.

### Transport

| Action | Response | Swap |
|---|---|---|
| add published | one comment fragment | depends on sort and page, see below |
| add pending | moderation confirmation | separate status zone |
| edit form | editor | `innerHTML` |
| edit save | comment body | `innerHTML` |
| status | updated comment fragment | `outerHTML` |
| delete | empty | `delete` |
| page | next slice | `beforeend` |

Where a published comment may be inserted depends on where the reader currently
is — inserting blindly would corrupt the slice:

- descending sort on the **first** page: `afterbegin`;
- ascending sort on the **last** page: `beforeend`;
- any other page: do **not** insert into the current slice; show a success
  message with a link to the comment instead;
- the page size must stay intact — an insert never makes a slice longer than
  the configured count;
- a pending comment is never inserted anywhere.

Rules:

- add/edit/status/delete are POST only; GET is allowed solely to load the edit
  form;
- CSRF travels in a hidden field or a header, never in the URL;
- the frontend token comes from `getPageToken()`;
- the form is cleared only after a successful insert — a validation error must
  keep the text;
- a pending response is never inserted into the public list;
- a plain POST/redirect/GET path keeps working without HTMX.

### Templates

- reuse the existing `comment` fragment;
- do not add a `comment-list` partial until threads actually need one;
- a page response may be a sequence of existing `comment` fragments;
- the admin table keeps its current markup;
- PHP passes data and semantic flags, HTML stays in templates;
- new styling hooks use `sl-*` names only;
- any new user-visible text lands in all six locales at once
  (`de`, `en`, `fr`, `pl`, `ru`, `uk`).

## Implementation steps

### Stage 1 — centralize the current implementation

- No table or column changes.
- Add `Comments`, `CommentStatus`, `CommentMode`.
- Move every `_comment` read and write into the class.
- Migrate frontend, admin module, user activity feed and module delete handlers.
- Preserve current behaviour and HTML byte for byte. **The current sort stays
  untouched in this stage** — `time, id` arrives in stage 2, so byte parity is
  achievable here.
- Delete `ashowcom()` once its callers are migrated.
- Verify no direct `_comment` SQL remains outside `Comments`, setup and
  migration files.

### Stage 2 — validation and write consistency

- Merge add and edit validation into `checkRules()`.
- Fix the maximum word length check (the copy still broken in `updateComment()`).
- Introduce `CommentMode` and use it instead of bare `acomm` comparisons.
- Drop the request `cid`; load `acomm` from the target row; resolve `modul`/`cid`
  for edit, status and delete from the comment row.
- Verify the target row exists, resolving its table through the fixed module
  list.
- Never trust `modul`, `uid`, `name` or `ip` coming from the request.
- Make add/edit/status/delete transactional.
- Add `reqkey`, `edited`, `deleted`, `iphash` and the composite indexes.
- Switch listings to `time, id`. Rows sharing a timestamp may change relative
  order — that is expected and is exactly what the stable sort fixes.

`iphash` migration, in this order:

1. compute it as a truncated raw HMAC-SHA256 of the normalized IP, keyed by its
   own purpose secret `getSecret('commentip')`;
2. backfill all existing rows from `ip`;
3. only then switch the flood query from `ip` to `iphash`;
4. the query must never support both columns at once.

`ip` itself is kept for admin search and GeoIP; removing it is a separate task.

### Stage 3 — move comment mail off the request

Requires `docs/MAIL-QUEUE-2026.md` stages 1-2 to be delivered first.

- Replace the synchronous `addAdminMail()` call with a queue job.
- Write the job inside the same transaction as the comment.
- A delivery failure must never roll back a stored comment.
- Verify submit latency drops to the render cost.

### Stage 4 — fragment responses

- Change transport and template targets only.
- Add returns one fragment or the pending confirmation.
- Edit, status and delete operate on a single comment.
- Move mutations to POST.
- Move CSRF out of the URL.
- Fix the form reset so it happens only on success.
- Verify both the HTMX path and the plain POST fallback.

### Stage 5 — threads

`path` is a sort key, so it is `VARBINARY(255)` — a collated `VARCHAR` would
order it by collation rules, and unpadded numbers would order `10` before `9`.
Each segment is the comment id zero-padded to ten digits:

```
root  0000000012
child 0000000012/0000000048
```

- Add `pid` and `path`; existing comments become roots.
- Backfill `path` for all existing rows.
- Enforce a maximum depth of 20 segments.
- Validate that a parent belongs to the same `modul`/`cid`.
- Index `path`.
- Paginate **root** comments; a branch is either loaded whole or fetched through
  `getBranch()` with an explicit limit.
- Show a deleted parent with live replies as a tombstone.
- Never re-parent old comments automatically.

Stages are independently shippable and must be delivered in this order.

## Verification

### Read

- all eight modules;
- guest, user and moderator;
- ascending and descending sort;
- a pending comment is invisible to a normal user;
- a moderator sees pending comments;
- first, middle and last page;
- identical ordering when timestamps are equal (from stage 2);
- admin parity, item by item: result query, count query, pager URL, selected
  module, selected status, search term, highlighting, bulk action form.

### Write

- published add;
- pending add;
- repeated POST with the same `reqkey`;
- two concurrent POSTs;
- edit by the owner;
- edit after the edit window expired;
- edit by a moderator;
- repeated status transition;
- delete pending;
- delete published;
- delete a target row together with all its comments.

### HTTP routes

Exercised explicitly, not through the UI alone:

```
GET  index.php?name=news&op=view&id=<fixture>
POST index.php?go=1&op=addComment
POST index.php?go=1&op=updateComment
POST index.php?go=1&op=updateCommentStatus
POST admin.php?name=comments&op=actions
```

The add request carries `id`, `mod`, `name`, `text`, `reqkey`, `token` — and no
`cid` field.

For every POST check: `checkSiteToken()` rejects a missing or wrong token; the
HTMX response; the plain `303` redirect without HTMX; the `_comment` row; the
target counter; user points; the queue row; and the three runtime logs.

### Persistence

After every operation check: the `_comment` row, the target comment counter,
user points, `reqkey`, the mail queue and `deleted`/`edited`.

### Migration

- fresh install through `setup/sql/table.sql`;
- upgrade path as its own SQL file, separate from the fresh schema;
- both MySQL 8 and MariaDB;
- every new column and index present afterwards;
- `iphash` backfilled in stage 2, `path` backfilled in stage 5;
- a dump taken before any DDL, and a restore rehearsed.

### Performance

`EXPLAIN` for the public list, the moderator list and the flood query; response
size; submit latency; mail queue drain time.

### Browser

- smoke run of all eight modules;
- load scenarios on `files`, `news` and `voting`;
- scroll and focus preserved after add and edit;
- pending confirmation visible;
- repeated double click;
- plain submit without HTMX;
- admin list and bulk actions.

### Static, per stage

`php -l` on every touched file, `phpstan`, the full `phpunit` suite and
`php-cs-fixer --dry-run` — each stage, not once at the end.

After every state-changing run check `storage/logs/error_php.log`,
`storage/logs/error_sql.log` and `storage/logs/error_site.log`.

## Risks

- A direct `_comment` SQL statement missed during centralization.
- Comment counters drifting apart from `_comment`.
- Points changed twice on a retry.
- A mail job lost between the insert and the queue write.
- Soft delete interacting badly with target row deletion.
- A branch broken when its parent is removed.
- The public query and the admin count query disagreeing.

## Out of scope

- Renaming `{prefix}_comment` or its existing columns.
- SQL aliases and dual-schema compatibility.
- Persisted parser HTML.
- Full edit revision history.
- Comment likes and rating redesign.
- Forum posts.
- The mail queue itself, its schema, drain task and the migration of the other
  senders — all covered by `docs/MAIL-QUEUE-2026.md`.
