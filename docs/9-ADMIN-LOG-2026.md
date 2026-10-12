# Admin Log 2026

Work plan for everything the site journals before release 8.0. The journal of administration — who changed what in
the panel and when — is written by the core into `storage/logs/admin.log` in the one format of the other journals,
together with the logins of the panel; the login journals of the site and of OAuth and the journals of the database
tool and of the file scan move onto the same format. The logins are also kept in the table `_login_log`, so a user
reads their own in the profile and the panel reads them all. The journals are read on the page "Безопасность"; a
module links to its entries there. The plan replaces the client-only "Журнал действий" (Э30) of the stand
`public/demo/admin/comments.html` and takes over the login journal of `docs/2027-CORE.md` (section 3).

Status: open; batch 0 is the first step. The order of the batches is kept in `docs/ROADMAP-2026.md`. Update this line
as batches land.

No line numbers anywhere in this document on purpose: every reference names the table, the column, the function or
the file it points at, and that name is what to search for.

## Decisions

Taken by the owner on 2026-10-12:

- **The journal of actions is the file `storage/logs/admin.log`**, written through `Logger` as one JSON line per event
  like `error_php.log`, `error_site.log`, `hack.log` and the others, not a table: the rotation by
  `$conf['security']['log_size']` keeps it bounded and it needs no cleanup job. The logins of the panel that
  `addLoginReport()` writes into the same file today become events of the same journal.
- **It is written in one place.** The token check of the panel, `checkAdminPost()`, stands in front of every change an
  administrator makes; the journal records there, so every module is covered by one change of the core and a module
  written later is covered without remembering to. A call in each handler (titles of the objects, exact results) was
  weighed and declined: two to three times the cost and a rule every new handler would have to keep.
- **It is shown only on "Безопасность"** (`admin.php?name=security`): `admin.log` opens as a table with filters
  instead of the raw text of the file. A module carries a link "Журнал действий" to that table filtered by itself; no
  module shows a block of entries on its screen, which would read the tail of the file on every show. The block Э30
  of the stand goes.
- **Every journal takes the one format.** `user.log`, written by `addLoginReport()` beside `admin.log`, `oauth.log`,
  written by `Oauth::setLog()`, `database.log`, written by `addDblog()` of `admin/modules/database.php`, and
  `filescan.log`, written by the file scan of `core/system.php`, become channels of `Logger` too. `filescan_tree.log`
  is a snapshot of the tree the next scan compares with, not a journal, and keeps its form.
- **Everything about journaling the site lives in this plan.** The login journal of `docs/2027-CORE.md` (section 3,
  step 4) moved here on 2026-10-12: the table `_login_log` beside the files and not instead of them (its question 6
  answered so), the profile screen "Журнал входов" with its pager, the same journal of everybody in the panel with a
  filter, 90 days, removed by `cachegc`. What journals the objects of a subsystem that does not exist yet stays with
  that subsystem in 2027: the request log `api_log` and the audit table `api_audit` of `docs/2027-CONNECTOR.md` (the
  latter keeps the old and new values and the 365 days the file cannot hold; the journal of this plan records the
  POSTs of that admin like any other, and the connector plan says so), and the receiver of CSP reports of
  `docs/2027-CORE.md` (question 9).
- **A login is written at one point.** Every login, by password or by OAuth, of the site or of the panel, is written
  by `addLoginReport()` alone: one event in `admin.log` or `user.log` and one row of `_login_log`. `oauth.log` keeps the
  events of the protocol only — the start, the errors of a provider, link, unlink, a created account — and no longer a
  second record of the login itself (`oauth_callback_success`); a refused password of the link form is the refused
  login of `addLoginReport()`, not a second event `link_login_failed`. The monitor counts the refused logins of the
  panel from `_login_log`. The failure counter of the captcha (`Captcha::registerLoginFailure()`) stays: it is a short
  window per address that decides when the captcha shows, not a journal.
- **The logins are shown once.** The tab "Журнал входов" of "Безопасность" reads `_login_log`; the table view of
  `admin.log` opens on the actions and shows the logins of its file only when its filter of events asks for them.
- **Before the remaining SCHEMA batches.** `_login_log` is new schema, and the SCHEMA plan keeps its last batches for
  the end so that its final pass also covers what the steps before it add.
- **The old files of the stand are gone.** `log_admin.log`, `log_user.log`, `log_oauth.log` and the empty `log.log`,
  left from before the journals were renamed on 2026-10-07, and `dump.log` and `dump_log.log`, which nothing writes,
  were deleted on 2026-10-12; nothing is carried over. Empty `user.log`, `oauth.log` and `request.log` were created in
  their place beside the existing `admin.log`.

Proposed by the plan on 2026-10-12, to be put to the owner in batch 0; they stand unless the owner overrules them:

- **The table is written whatever the switches say.** `log_a` and `log_u` keep switching the events of `admin.log`
  and `user.log`; the rows of `_login_log` are written always, because the profile shows them to the user and the
  release ships `log_u` off — tied to the switch, the screen of the profile would be empty on every fresh site.
- **An event carries no request body.** `Logger` copies `$_GET` and `$_POST` into every line (50 fields of up to 1024
  characters, names like `pass`, `token`, `key` masked): for an action of the panel that is the text of a block or a
  letter, an address of a user, a whole configuration. The channels `admin`, `user` and `oauth` keep the address, the
  method, the URL and the browser of the request and leave `query`, `post`, `cookie_keys` and `session_keys` out.
- **A refused token is no event.** It changed nothing; a forged request already reaches the hack journal through the
  input filter, and a stale form after a long pause is no event of administration.
- **The action is shown in words** for the common operations (add, save, delete, status, order), from language
  constants of the panel; any other operation shows its name as it is (`bansave`, `configsave`).
- **The object is its id**, and the number of ids for a bulk action; the title is not stored (see "Out of scope").
- **Levels**: an action and an accepted login are `info`, a refused login `warning`.
- **Every journal of `Logger` opens as a table** in `logview`, not only `admin.log`: they share one format, so one
  reader serves them all; `filescan_tree.log` keeps the raw view.
- **The link of a module is shown to an administrator who may open "Безопасность"** and to no one else.
- **The tab "Журнал входов"** filters by account, panel or site, result, way and period.
- **A login with a name that matches no account** is a row with `uid` 0, seen in the panel and in no profile.
- **`way` tells a password login from an OAuth one**, a column the table of `docs/2027-CORE.md` did not have.
- **The results** are `ok`, `password` and `blocked` now; the steps of TOTP in `docs/2027-CORE.md` add `totp` and
  `recovery` when they land.

## What exists today

Confirmed on 2026-10-12 against the code.

- `Logger` (`core/classes/logger.php`) writes the channels `php`, `sql`, `file`, `site`, `warn`, `hack` and `request`
  into `error_php.log`, `error_sql.log`, `error_file.log` or `file.log`, `error_site.log`, `warn.log`, `hack.log` and
  `request.log`: one JSON line with `ts`, `level`, `chan`, `msg`, `fingerprint`, the request (`ip`, `method`, `url`,
  `referer`, `ua`), the context (`query`, `post` with secret names masked, `cookie_keys`, `session_keys`), the memory
  and the caller's fields. `addLine()` rotates a file at `log_size` through `addCompress()`. The channel list of
  `getChan()` is closed. `request.log` is written by `core/security.php` for every request when
  `$conf['security']['log']` is on.
- `addLoginReport(int $id, int $typ, string $login)` in `core/system.php` writes every login attempt of the panel to
  `admin.log` and of the site to `user.log`, switched by `log_a` and `log_u` (the release ships `log_a` on and `log_u`
  off), as a block of text lines ("Вход: Да", the address, the name, the URL, the browser, the date) ended by `---`,
  rotating at `log_size` on its own. It is told the panel or the site, accepted or refused and the name tried, not the
  account. No attempted password is written. Its callers: `admin/index.php` (accepted, refused), `setUserLogin()`,
  `login()` and the link form of OAuth in `modules/account/index.php`.
- A login by OAuth is recorded three times: `oauthlogin()` writes `oauth_callback_success`, `oauth_link` or
  `oauth_create` to `oauth.log`, then `setUserLogin()` calls `addLoginReport()`, which writes `user.log`. A refused
  password on the link form writes both `addLoginReport()` and `Oauth::setLog('link_login_failed')`.
- `Oauth::setLog()` writes `oauth.log` as one line `date | event | provider | uid=… | ip=…`, truncating the file at
  `log_size` instead of rotating it.
- Refused logins are counted by the captcha (`Captcha::registerLoginFailure()`, per address, to show the captcha) and
  by `getFailedLoginCountHours()` of `core/monitor.php`, which reads the tail of `admin.log` and matches words of its
  blocks.
- `database.log` is written by `addDblog()` in `admin/modules/database.php` as `[date] text`; `filescan.log` and the
  snapshot `filescan_tree.log` by the file scan of `core/system.php`, in forms of their own.
- No table holds logins; a user sees none of their own. The job `cachegc` (`addCacheGcTask()`) is a system job of the
  scheduler and runs daily.
- No change made after the login is recorded anywhere.
- `checkAdminPost(string $scope): bool` in `core/security.php` refuses anything but a POST and checks the token of the
  scope with `checkSiteToken()`. It is called 115 times in 35 files of `admin/`, `modules/*/admin/` and `core/`, with
  37 scopes (`node` 14 times, `newsletter` 8, `blocks` 7, `comments`, `search` and `account` 6 each, …).
- Two handlers of the panel check the token directly with `checkSiteToken()` and bypass `checkAdminPost()`:
  `updateAdminCategoryOrder()` and `updateAdminBlockOrder()` in `core/admin.php`, the drag-and-drop order of
  categories and blocks.
- `setRedirect(string $url, bool $refer, int $code, string $text, bool $warn): never` ends a handler; `$warn` marks
  the flash message as a refusal. A handler that shows its form again with errors does not redirect.
- "Безопасность" (`admin/modules/security.php`) lists every `*.log` of `storage/logs/` with its size and date;
  `logview` shows a file as raw text in the code editor, `download` hands it out, `delete` removes it.
- Subsystems keep histories of their own and stay as they are: the point journal `_points`, the annulled votes of
  `_rating_votes`, the edit and delete moments of `_comment`.

## Design

### Login table

`_login_log`, on the domains of `docs/7-SCHEMA-2026.md`:

| Column | Declaration | Meaning |
| --- | --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | a log that may outgrow `INT` |
| `uid` | `INT UNSIGNED NOT NULL DEFAULT 0` | the account of the user or the administrator, 0 for a name that matches none |
| `admin` | `BOOLEAN NOT NULL DEFAULT 0` | a login of the panel |
| `result` | `VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL` | `ok`, `password`, `blocked` |
| `way` | `VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'login'` | `login` or `oauth` |
| `ip` | `VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT ''` | where from |
| `agent` | `VARCHAR(255) NOT NULL DEFAULT ''` | the browser, cut to the column |
| `time` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | when |

Keys: `PRIMARY (id)`, `uid_time (admin, uid, time, id)` for the profile and the filter by account, `time (time)` for
the panel, the count of the monitor and the cleanup. The trap of `docs/2027-CORE.md` is kept out: no unique key over
`(uid, time)` and no `INSERT IGNORE`, or two attempts within one second would keep only one.

`addLoginReport()` alone writes the row, so it learns the account and the way: its callers pass the id they found (0
for a name that matches none) and `setUserLogin()` passes `oauth` when `oauthlogin()` called it. `agent` is cut to its
column by `filterCutText()`, as every writer of an outside value is since SCHEMA batch 2. `getFailedLoginCountHours()`
counts the refused rows with `admin = 1`. `cachegc` deletes rows older than 90 days in batches. The 6.2 path creates
the table with `CREATE TABLE IF NOT EXISTS` in `table_update6_3.sql` and lists it in its `setconv` block.

### Channels

`Logger` gains the channels `admin`, `user`, `oauth`, `database` and `filescan`, written to the files of those names
and rotated by `addLine()` like the others. The first three carry the request without its body (see "Decisions").
`addLoginReport()`, `Oauth::setLog()`, `addDblog()` and the file scan write through them and lose their own file
handling.

An event of the panel adds to the common fields:

| Field | Meaning |
| --- | --- |
| `event` | `login` or `action` |
| `aid`, `aname` | the administrator and the name at the time |
| `modul` | the scope of the token |
| `op` | the operation of the request |
| `target` | the first id of the request, 0 for none |
| `num` | how many ids a bulk action named |
| `result` | `done`, `refused`, or `open` for a form shown again or a request ended without an answer |

A login keeps what `addLoginReport()` writes today: whether it was let in, the name tried, the administrator or the
user already signed in.

### Write

`checkAdminPost()` notes the request once it passes: scope, `op`, the first id of the known keys (`id`, `bid`, `mid`,
`cid`, `nid`, `uid`, `aid`) or of the list `id[]` with its count. A second call in the same request changes nothing.
`setRedirect()` notes the result from `$warn`. One line is written when the request ends, so a handler that stops
without a redirect is recorded too. The two order handlers of `core/admin.php` move onto `checkAdminPost()` with
their scopes. A failed write never breaks the action it records. The functions this needs are named in batch 0 and
approved before they are written.

### Read

`logview` shows a journal of `Logger` as a table instead of raw text: time, level, message, address and, for
`admin.log`, who, module, action, object and result, newest first, with filters and the pager of the panel; a line
that is not JSON — an old block of a site updated from 6.2 — is skipped. `admin.log` opens on its actions; its filter
of events shows the logins too. `filescan_tree.log` keeps the raw view. A module links to
`admin.php?name=security&op=logview&file=admin&modul=<scope>`. The words of the actions come from the language
constants of the panel in all six locales.

## Batches

Every batch runs the tests of the code it touches by `--filter` and one full phpunit at its end; theme or markup
changes take `npm run ui:before` / `ui:after` and `npm run ui:gates`. The table comes first, so the monitor moves
straight from the text of `admin.log` onto the table and never reads a format in between.

### Batch 0 — decision

The proposals of "Decisions" put to the owner, the answers written into this plan; the list of the new functions with
their names, approved before any code.

### Batch 1 — login table

The table in `table.sql` and `table_update6_3.sql` and on the stand; `addLoginReport()` learns the account and the
way and writes a row; the monitor counts the refused logins of the panel from the table; `cachegc` removes rows older
than 90 days; the profile screen "Журнал входов" of the account module with its pager, in both themes; the tab "Журнал
входов" of "Безопасность" with its filters; the language constants of the account module and of the panel in six
locales. The batch ends with `/schema` showing no drift and runs `SchemaConventionTest`, `SchemaUpdateValidationTest`
and `UpdateSiteTest`. Tests: an accepted and a refused login of the site and of the panel write one row each with
`log_u` off, two attempts in one second two rows, an OAuth login one row with `way` `oauth`; a name that matches no
account writes `uid` 0 and shows in no profile; a user reads only their own rows; the panel filters; the monitor counts
the refused rows of the panel; `cachegc` removes only rows older than 90 days; the 6.2 update creates the table.

### Batch 2 — file journals

The five channels in `Logger`; `addLoginReport()`, `Oauth::setLog()`, `addDblog()` and the file scan on them; the
login written once (`oauth_callback_success` and `link_login_failed` no longer written beside it); the record in
`checkAdminPost()` and `setRedirect()`, the write at the end of the request; the two order handlers on
`checkAdminPost()`. On the stand the old-format `admin.log`, `database.log` and `filescan.log` are emptied once the
format changes. Tests: a POST of the panel that passes writes one line with scope, operation, target and result and
no request body; a refused token writes nothing; a bulk action writes its count; a handler that shows its form again
writes `open`; a request that checks twice writes one line; a failed write leaves the action done; an accepted and a
refused login of the panel and of the site; an OAuth login writes one login event and no second one in `oauth.log`;
a protocol event of OAuth; a line of the database tool and of the file scan; the rotation of a channel.

### Batch 3 — reading the files

The table view of `logview` with its filters and pager, `admin.log` opening on its actions; the link "Журнал действий"
of a module, beginning with comments; the language constants in six locales; the template parts in both themes where
the panel needs them; the block Э30 of the stand replaced by the link. Tests: the filters, the pager, the skipped
foreign line and the link over real requests, and an administrator without "Безопасность" sees no link.

### Batch 4 — reference

The journals, their format and the login table in the permanent documentation, the change in `docs/VERSIONS.md`; the
references to this plan in `docs/2027-CORE.md` and `docs/2027-CONNECTOR.md` point at that reference. Deletes this
file.

## Out of scope

- The values before and after a change, and the title of the object at the time: they need a call in each handler,
  which "Decisions" declined.
- Changes made outside the panel: the quick edit on the site, the connector `public/api.php`, the jobs of the
  scheduler, `update.php` and `setup.php`.
- Undoing an action from the journal.
- A table of the journal of actions in the database.
