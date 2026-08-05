# Privat

Status: approved, not started. Last review: 2026-08-05.

Replace the procedural private-message subsystem with one `Privat` class.
Migrate every frontend, block, profile, and admin caller in the same change,
then remove the old functions and duplicate routes. No compatibility wrappers,
runtime schema detection, dual-schema fallback, or auxiliary state table.

The public module name, config namespace, and admin route remain `privat`.
Source line numbers below were resolved against the working tree on 2026-08-05;
resolve the named symbols again before editing because adjacent work may move
them.

The procedural code lives in `core/user.php:624-986`, not in the account module.
`modules/account/index.php` only holds the `privat()` route, the dashboard
preview query, and the `psmail` preference field.

Run this plan one step at a time with `/privat`. Everything below the progress
table is specification and is amended only with explicit approval.

## Progress

Status is one of `not started`, `in progress`, `done`, `blocked`. Evidence names
the commit, the files, and the checks that actually passed. This table is the
only part of this file a working session may edit on its own.

| # | Stage | Step | Status | Evidence |
|---|---|---|---|---|
| 1 | 1 | `runifcol()` and `modcol()` helpers, state-column migration across all three channels, stale `status` index removed, parity fixtures for states A-D | done | Committed in `4bae07b9`. `setup/sql/table.sql` (four state columns, four keys), `setup/sql/table_update6_3.sql` (`runifcol()`, `modcol()`, rewritten `_privat` batch, stale `addidx` on `status` deleted), `setup/sql/update6_3_patch.sql` (section 5 with its own helper set), `tests/Support/privat_probe.php` and `tests/Unit/PrivatMigrationTest.php` (states A-D against both upgrade channels on a live server), `tests/SchemaUpdateValidationTest.php` (reads `modcol` declarations and both channels). Passed: `php -l`, `composer analyse`, `composer test` (658 tests), `php-cs-fixer --dry-run`, and the probe: all 8 scenarios converge on the fresh definition byte for byte, are safe to run twice, keep every mailbox count, and leave an already converted table unrebuilt. `EXPLAIN` on the new keys is deferred to the query step, as the index section requires. |
| 2 | 1 | `Privat` class and its unit tests, one query per predicate-table row | done | Committed in `4bae07b9`. `core/classes/privat.php` (final class `Privat` plus the `PrivatBox` enum; every predicate built in one place, every mutation transactional and authorized by the detail predicate of its own side), `core/system.php` (`$prv` built beside `$com`), `tests/Support/privat_class_probe.php` and `tests/Unit/PrivatClassTest.php` (21 tests, 86 assertions against a disposable schema carrying the two shipped tables, refused statements included). Passed: `php -l`, `composer analyse`, `composer test` (679 tests), `php-cs-fixer --dry-run`, plus real requests to `/` and `/index.php?name=account` with clean logs. Deferred by the plan's own staging: the `FOR UPDATE` lock of the send protocol and the deadlock retry (step 5), and `EXPLAIN` on the predicates. |
| 3 | 1 | Account page, dashboard preview, navigation badge, user block moved onto `Privat`; lazy tab loading; bulk actions | done | Committed in `4bae07b9`. `core/user.php` (`getPrivateMessageView()` rewritten onto `$prv` with one list branch for all three mailboxes, the detail view resolving the counterpart instead of `uidout`, `addPrivateMessage()`/`setPrivateMessageSaved()`/`deletePrivateMessage()` moved onto the class, new POST handler `updatePrivatBox()`, navigation badge on `getUnreadCount()`), `core/classes/privat.php` (`getUnreadOutCount()` for the amended predicate row), `blocks/user_info.php`, `modules/account/index.php` (dashboard preview on `getRecentList()`, only the opening mailbox rendered and the other three fetched on click), `index.php` (POST-only bulk route), six `lang/*.php` (`_PRIVAT_NEW`, `_PRIVAT_NOSEL`, `_PRIVAT_READ`, `_PRIVAT_UNSAVE`), `templates/lite` (`table`/`table-row` check column, `block-content` and `inline-badge` flags, `content-list` bulk slot, `theme.css`), `tests/Support/privat_class_probe.php` and `tests/Unit/PrivatClassTest.php` (the amended predicate row gets its own test). No direct `_privat` SQL is left outside the class in frontend, block or profile code; the admin list and its delete stay with step 6. Passed: `php -l`, `composer analyse`, `composer test` (680 tests, 7650 assertions, four consecutive clean runs), `php-cs-fixer --dry-run`, and 47 authenticated HTTPS checks on the migrated dev database covering lazy panels, bulk read/unread/save/unsave/delete, independent sides, pager pages one and two, a recipient name longer than 25 bytes, and the refusals (GET, missing token, wrong token, foreign id, foreign mailbox, unknown action) — `error_php.log` and `error_sql.log` stayed silent throughout. Two amendments were approved before the step ran: the `Outbox unread badge` predicate row, and the bulk POST handler landing here rather than in step 4. |
| 4 | 1 | GET read routes and POST mutation routes replace the old AJAX ops; markup moved to the template boundary | done | Committed in `4bae07b9`. `core/user.php` (`getPrivateMessageView()` is a pure read that echoes nothing and takes the opened row instead of loading it twice, the new POST route `setPrivateMessageRead()` owns the mark-read of an opened message, `setPrivateMessageSaved()` and `deletePrivateMessage()` are deleted because a row action is `updatePrivatBox()` with one id, `addPrivateMessage()` echoes like every other handler, the saved-folder fill notice moved into the one mailbox mutation route, the compose form carries its token as a body field and its broken inline `alert()` is gone), `index.php` (`getPrivateMessageView` joins the token-free authenticated reads, `addPrivateMessage`, `setPrivateMessageRead` and `updatePrivatBox` are POST only, the two deleted ops are off the route table), `modules/account/index.php` (the tab strip asks without a token). No template file needed a change: `link` and `dial` already carry `is_post` and `hx_headers`, and `form-add` already has the slot the token field went into. A self review of the step found and fixed one regression it had introduced: folding the single-row save into the one mutation route had made every refused save claim a full folder, so the quota message is now kept for a batch the folder really cannot take and every other refusal answers the generic error. Passed: `php -l`, `composer analyse`, `composer test` (680 tests, 7650 assertions), `php-cs-fixer --dry-run`, and 37 authenticated HTTPS checks against the migrated dev database — every read is a GET with no token in its address, every action is a POST, GET, missing token, wrong token, a foreign message id and an action a mailbox never offers all change nothing, and the persisted state after the run matched row by row: opened rows `viewed = 1`, the saved row `saved = 1` with `viewed = 0`, the deleted row `delin = 1` with `delout = 0`, and two messages really stored by the compose route. The second round covered what the first had not: the outbox open link asks the sent side and leaves the recipient copy unread, the saved tab acts on its own mailbox and a delete out of it leaves `delout = 0`, a save carrying one foreign id changes nothing and no longer claims a full folder, and page two of the list reads without a token. The full-folder message itself was not exercised: `messsav` is 250 against 15 saved rows and a batch is bounded at 100, so the branch is unreachable without filling the folder. Logs stayed clean for both runs; the two `error_php.log` entries at 10:24 are the file caught mid-edit by a polling browser tab and no longer reproduce. |
| 5 | 1 | Concurrency protocol in `addMessage()` and the saved-quota lock in `setMessageSaved()`, with deadlock retry and the documented result shape | done | Committed in `4bae07b9`. `core/classes/privat.php` (`getUserLock()` locks both participants in one statement ascending by id, `addMessage()` is now the retry wrapper over `addMessageRow()` which runs the whole protocol from its own transaction, `checkDeadlock()` reads errno 1205 and 1213, `getFailure()` carries the retry decision of the two call sites where a statement really answered false and strips it off before the caller sees it, `setMessageSaved()` locks its own account before it counts the folder and only in the branch that reads the quota), `tests/Support/privat_class_probe.php` and `tests/Unit/PrivatClassTest.php` (two scenarios and two tests: a second session holding the account row, a trigger that counts every insert attempt in a session variable a rollback cannot take back and fails the ones the scenario wants failed, and a race in which two accounts send into the last free place of one mailbox from processes of their own, held back until one shared moment because a single connection cannot race itself). A self review of the step found the lock alone does not do what the plan asks of it, and the fix is part of this row. InnoDB fixes the snapshot every later plain read of a transaction answers from at its **first** plain read, and the recipient lookup was that first read. The interval and both quota counts behind the lock were therefore answered out of a snapshot older than the lock, and a send that had just committed and released the lock was invisible to them. Measured, not reasoned about: a two-process race for the last free place of one mailbox ended `['ok', 'ok']` with the mailbox holding four messages against a quota of three. The name is now resolved before the transaction opens, so the locking read is the first statement inside it and the snapshot is taken behind the lock; the same race then ends `['ok', 'quota']` with the mailbox holding exactly its quota. The resolved id is not trusted for anything: the lock itself reports whether that account is still there. `setMessageSaved()` already opened its transaction with the lock and needed no change. Passed: `php -l`, `composer analyse`, `composer test` (683 tests, 7663 assertions), `php-cs-fixer --dry-run`, `EXPLAIN` on both lock forms (send lock `range`/`PRIMARY`, 2 rows, no filesort; the saved lock is `const`/`PRIMARY`, byte for byte the plan of the `id = :uid` form the plan writes), and 13 authenticated HTTPS checks on the migrated dev database, run again after the reorder — the send is accepted once, the interval and the unknown recipient are refused behind the lock, a save and an unsave still pass, a batch with a foreign id changes nothing and claims no full folder, and a send without a token stores nothing. The database after the run held exactly one row per accepted send and none per refusal, so no retry duplicated a message. Two deviations from the letter of the plan, both recorded here rather than improvised silently: `setMessageSaved()` takes its account lock through the same `IN (:usra, :usrb)` helper with both names bound to its own id instead of a second `id = :uid` statement, which `EXPLAIN` shows is the same plan on the same row; and only a send that owns its transaction is retried, because a caller that opened one owns everything a rollback would take with it. A sender the lock no longer finds answers `not_logged`, which is the closed set's code for a request without an account. |
| 6 | 1 | Both user-deletion paths transactional; admin list migrated; `go=5` delete route deleted | done | Committed in `4bae07b9`. `core/admin.php` (`getAdminPrivateList()` reads `$prv->getAdminList()`, so the last direct `_privat` SQL of the panel is gone together with its own `COUNT` through `getTplPager()`; the pager is now built from the same numbers the rows came from, the page is read from the request so a list rebuilt by a POST action stays where it was, and the row state is the labelled chip of the five derived states instead of the two-value badge of the old column; `deleteAdminPrivate()` deleted), `index.php` (the `go=5` delete route deleted), `admin/modules/privat.php` (`delete()` runs through `Privat::deleteAdminMessage()` and answers a failed delete instead of always claiming success), `modules/account/admin/index.php` (`delete()` wraps the user row, the favorites, the OAuth links, `Comment::deleteUser()` and `Privat::deleteUser()` in one transaction and rolls the whole account deletion back when one statement fails), `admin/index.php` (`add_admin()` resolves the id of the same-named account before the row goes, then deletes it and cleans its mailboxes in one transaction), `core/system.php` (the private-message branch of `ad_status()` is deleted with the shared column it rendered; no other caller passed that argument), six `lang/*.php` (`_PRIVAT_DELIN`, `_PRIVAT_DELOUT`). Passed: `php -l` on all twelve changed files, `composer analyse`, `composer test` (683 tests, 7663 assertions), `php-cs-fixer --dry-run`, `EXPLAIN` on the admin list (`type=index`, `key=time`, 50 rows, no filesort, so `time` really serves it as the index section claims, and its count is a covering scan of the same key), and authenticated admin checks against the migrated dev database on a seven-row fixture covering every state: the five states each render their own chip and label, a copy deleted by one side is still listed, the pager pages one, two, twenty-nine and thirty match the SQL row for row and page 999 clamps to thirty, a trash action clicked in the panel really erased its row, a row deleted from page two carried that page in its own action and left the rebuilt list on page two, a delete without a token and one with a wrong token changed nothing, the removed `go=5` route answered nothing, and the account deletion left exactly the rows the model predicts: the three messages the deleted account had sent stayed for the recipient as `delout = 1`, the one it had received stayed for the sender as `delin = 1`, the two copies the counterpart had already deleted were erased, the account row and its comment authorship were gone in the same commit, and both are rendered as `_ANONYM`. Logs stayed silent: `error_php.log` and `error_sql.log` were last written at 10:24 and 09:33, before the run. Not exercised at runtime: `admin/index.php:150` only runs on an installation with no administrator at all, so that path is covered by review, lint and static analysis alone. |
| 7 | 1 | `psmail` and returned ID for mail, points after insert, session counter cache removed, procedural functions deleted, `rg` proofs | done | Committed in `4bae07b9`. `core/user.php` (the notification reads `psmail` instead of the forum preference `fsmail`, `deletePrivatCounts()` is deleted together with its three call sites, and the comment block of `addPrivateMessage()` records both the preference and that points and mail are side effects of a send that was really stored), `blocks/user_info.php` (the sidebar counts both badges through `$prv` on every render, with no session copy and no 60-second window in front of them), `modules/account/index.php` (`logout()` no longer drops a cache that no longer exists). The returned id and points-after-insert halves of this row were already satisfied by steps 4 and 5 and were re-verified rather than rewritten. Proofs, each `rg` over the tree: no `PREFIX_DB.'_privat'` outside `core/classes/privat.php` and the two probes; no `deletePrivatCounts`, `setPrivateMessageSaved`, `deletePrivateMessage` or `deleteAdminPrivate` anywhere; no `$conf['user_c'].'-privat'` session key; no `status` in the class; no `ORDER BY id DESC LIMIT 1` against `_privat`; the only two `DELETE FROM PREFIX_users` statements are `admin/index.php:153` and `modules/account/admin/index.php:920`, and both stand in the same condition chain as a `$prv->deleteUser()`, so there is no third path. Passed: `php -l` on the three changed files, `composer analyse`, `composer test` (683 tests, 7663 assertions), `php-cs-fixer --dry-run`, and 16 authenticated HTTPS checks on the migrated dev database: with `psmail = 1` and `fsmail = 0` a send stores exactly one row and queues exactly one `privat` mail to the recipient address whose link carries the id that was really stored; with `psmail = 0` and `fsmail = 1` the same send stores its row and queues nothing; the two sidebar counters match the database before the send, both rise by one on the render right after it, and both fall again on the render right after the message is opened, which is what the removed cache used to be able to delay. A self review of that run found it proved less than it claimed: every send in it went to the sender's own account, so `uidin` equalled `uidout` and the check would have passed just as well had the preference been read from the sending side. A second run of 11 checks closed it with two distinct accounts and two distinct addresses: with the sender at `psmail = 0` and the recipient at `psmail = 1` the notification is queued and goes to the recipient's address, and with the sender at `psmail = 1`, the recipient at `psmail = 0` and `fsmail = 1` on both, the message is stored and nothing is queued. The gate is the recipient's `psmail`, never the sender's and never `fsmail`, and the address is resolved from the stored message rather than from the account that sent it. The database ended both runs in the state it started in: 1472 message rows and 10 queued notifications, with both accounts back on their original preferences. `error_php.log` and `error_sql.log` were untouched by either run; `error_site.log` only carries the scheduler's standing `mail()` failure against the missing local SMTP server, one entry of which is this run's own queued notification being drained. Recorded for step 8, not acted on here because the index set is that step's decision: `EXPLAIN` on the two badge predicates the sidebar now runs per render shows `Unread badge` as `ref`/`in_new`, 1 row, `Using index`, but `Outbox unread badge` as a full scan for the one account that owns 341 of 1472 rows, because `out_box` is `(uidout, delout, time)` and does not carry `viewed`; for an ordinary sender it is `ref`/`out_box` over 3 rows. Step 8 should decide whether that row earns `KEY out_new (uidout, delout, viewed)` beside `in_new`. |
| 8 | 1 | Six-locale constants, `docs/VERSIONS.md` entry, `update6_3_patch.sql` section, stage 1 verification matrix | done | Committed in `4bae07b9`. `setup/sql/table.sql`, `setup/sql/table_update6_3.sql` and `setup/sql/update6_3_patch.sql` (the two keys the `EXPLAIN` step decided on — `out_new (uidout, delout, viewed)` and `flood (uidout, time)` — added to all three channels in the same order, so the three still end on one definition), `docs/VERSIONS.md` (dated stage 1 entry: which of the three channels an installation needs, what the section does to the table, that the section and the runtime code deploy together, and every behavior change the release carries), `docs/PRIVAT-2026.md` (the index section records the final set and the measurement behind each key, as that section itself required). The constants and the patch section needed no change: they landed complete in steps 1, 3 and 6, and this step proved it rather than rewriting it. Constants: all 57 language constants the whole stage 1 diff names are defined in all six locales, each in exactly one scope, none a scoped duplicate of a global, the six new `_PRIVAT_*` ones between 11 and 14 characters. The audit was run twice because the first pass knew only four locale directories and would have called a constant of some other module undefined; the second pass reads all 40 locale directories of the tree and finds no constant the diff names that no locale file defines. None of the six is referenced from a template, which matters because `{{ _CONST }}` resolves only names of at most twelve characters and two of them are longer. Passed: `composer analyse`, `composer test` (683 tests, 7667 assertions, two clean runs in agreement; a third run failed one test and counted six assertions fewer, which was this session's own route check working the same database at the same moment and not a defect — the suite passes whenever it owns the stand), `php-cs-fixer --dry-run` (nothing to fix), the migration probe over both upgrade channels times states A through D (all 8 converge on the fresh definition, all safe to run twice), and the patch section run against the migrated dev database through the shipped splitter: 1472 rows in, 1472 out, the table now carrying exactly the seven keys of the fresh schema. `php -l` was not run because this step changed no PHP file. `EXPLAIN` covers every row of the predicate table against the real 1472-row table, for the account owning 341 of them and for an ordinary one, before and after the two keys on an exact copy of the data: the outgoing badge went from `ALL` over the whole table to `ref`/`out_new`, 8 and 18 rows, `Using index`; the send interval went from `ref`/`out_box` with a filesort — and a backwards `time` scan for the heavy account — to `range`/`flood`, `Using index`, no filesort; every other row was already covered and is recorded in the index section. Route matrix: 45 authenticated HTTPS checks in one run over the final tree — every read a GET without a token, every mutation a POST with the token in the body, the badges read out of the rendered sidebar and falling on the render right after a message is opened, both participants' sides independent, a sender deleting a message already read, a saved message still in its sender's outbox, the send storing exactly one row and queueing exactly one notification whose link carries that id, the interval refusing the second send, and eight refusals (GET, no token, wrong token, foreign id, one foreign id in a batch, an action a mailbox never offers, a GET on the read route, and the removed `go=5`, which answers `Illegal file access`) each changing nothing. The account-admin deletion path was exercised through the panel: the deleted account leaves the counterpart every copy it still holds, only the rows both sides had let go are erased, and the counterpart's list renders the gone account under the anonymous label. The second path, `add_admin()`, was not exercised here either: its whole body sits behind `SELECT id FROM PREFIX_DB._admins LIMIT 1` answering no row, so it runs only on an installation carrying no administrator at all, and reaching it on this stand would mean emptying `_admins` first. It stays covered by review, lint and static analysis, exactly as step 6 recorded. The run created its own two accounts and its own administrator, removed them again, and ended with the database holding exactly what it held before it: 1472 messages, 11845 users, 81 administrators, 23 queued mails, with `error_php.log`, `error_sql.log` and `error_site.log` byte for byte unchanged. Two amendments this step made under the authority the plan gives it: the index set is final and now names seven keys, and the fresh dev database carries them. |
| 9 | 2 | `format` column in all three channels | done | Not committed. `setup/sql/table.sql` (`format VARCHAR(20) NOT NULL DEFAULT ''` as the last column of `_privat`, after `delout`, which is where `addcol` appends it in both upgrade channels), `setup/sql/table_update6_3.sql` (the `addcol` in Batch K after the three state columns and before the backfill, so the column order of an upgraded table is the column order of a fresh one), `setup/sql/update6_3_patch.sql` (its own section 6, because an installation that already applied section 5 never re-runs it, plus the two header paragraphs that enumerate what the file changes and what it never does), `tests/Support/privat_probe.php` and `tests/Unit/PrivatMigrationTest.php` (state E and the `PROBENEW` set). The column ships empty on purpose and no statement backfills it: the verdict is per body, not per column, and `tools/privat-migrate.php` of step 10 is what computes it. The deployment gate the plan already carries — no row outside `plain`/`markdown` — is what makes `''` a state the release cannot end in. Passed: `php -l` on the two changed PHP files, `composer analyse`, `composer test` (683 tests, 7679 assertions, 6 pre-existing skips), `php-cs-fixer --dry-run` (nothing to fix), the probe over both upgrade channels times states A through E (all 10 converge on the fresh definition byte for byte, all safe to run twice, no message row touched), and section 6 run against the migrated dev database through the shipped splitter: 1472 rows in, 1472 out, 1472 out again on a second run, the table now carrying the eight columns and seven keys of the fresh schema, every row `format = ''` and none classified. A smoke check of `/` and `/index.php?name=account` over HTTPS answered 200 and left `error_php.log`, `error_sql.log` and `error_site.log` byte for byte unchanged. One gap this step found and closed rather than left: the probe's states C and D build their table from `table.sql`, so the moment `format` entered the fresh schema they stopped describing the installation section 6 is written for — a table carrying every stage 1 column and key and only `format` missing. That is now state E, seeded from the fresh schema with `format` dropped. It is not the only state the column is added in — A and B start from the legacy shape and never carried it either — but it is the only one that starts from the table stage 1 really shipped, which is the only shape the patch channel's section 6 will ever meet in the field. `moved` was generalized with it: it used to assert only that state D starts on the wrong definition, and now asserts that every state except the already-final C really differs from the final definition before the run. One amendment to the specification itself, made with explicit approval on 2026-08-05 because the shipped column is wider than the sentence that described it: the storage model now records `format` as `VARCHAR(20) NOT NULL DEFAULT ''` and names `''` as the unclassified state that exists only between the schema section and the gate, and the content contract now says in as many words that the `''` of the storage model is a migration state the gate has already cleared rather than a third format runtime has to render. Neither sentence changes what is built; they close the one reading under which the two sections looked like they disagreed. Not done here, because the plan gives them their own rows: no runtime code reads or writes `format` yet (step 11), and the `docs/VERSIONS.md` entry with the stage 2 deployment sequence is step 12. |
| 10 | 2 | `tools/privat-migrate.php` with report/classify/convert/sample/title, ledger, rehearsal options | done | Not committed. `tools/privat-migrate.php` (the five modes the plan names and no sixth; `report` reads and prints only, `classify` writes `format` and the ledger `storage/migrate/privat-format.json` and rewrites nothing, `convert` and `title` are two independent ledger-driven passes that store the value they replaced, mark every row they finish and rewrite nothing twice, `sample` prints both fields before and after per class; `--default=`, `--size=`, `--num=`, `--out=`, `--db=`, `--prefix=` and `--force=1`), `tests/Support/privat_format_probe.php` and `tests/Unit/PrivatFormatTest.php` (11 tests, 59 assertions over 10 fixture messages in a disposable schema, driven through `--db` and `--prefix`, which is the rehearsal contract itself). The body classifier is the one `tools/comment-migrate.php` carries, because the writer is literally the same `filterHtml()`; the tool shares that discipline and none of its code. The title needs a verdict of its own and has no column to keep it in, so the ledger keeps it: a title was written by the html branch when its own row's body was, because both fields of one message went through one editor mode in one request, and a `&#034;` in the title alone still settles it because the other branch escapes a quote as `&quot;` and can never produce that entity. A self review of the step found and fixed three defects it had introduced, and all three are part of this row. The refusal read the completion stamp alone, and a pass stamps the ledger only when it finishes: an interrupted `convert` leaves finished rows behind and no stamp at all, so `classify` would have accepted that ledger, reset every mark and handed those rows to a second reversal — the one operation no restore short of the dump can undo. The refusal now counts the rows a pass really finished and never reads a stamp. The row verdict and the body decode also disagreed: the ledger's `html` flag knew the whole request had gone through the html branch whenever the title carried a `&#034;` the other branch cannot produce, while `getConvertBody()` re-derived the branch from the class of the body alone, so one row could be read as written by two different editors. The body now follows the row verdict wherever its own class carries no evidence — classes 4 and 5 have neither a tag nor a break — with class 2 the one exception, because a break with the line ending behind it is written by `nl2br()` and by nothing else, and `nl2br()` runs in no branch but the plain one. And `sample` dereferenced the result of a read that can answer no row; it now skips that id. What the review did not change is the branch structure, and that is deliberate and load-bearing: the tag map runs on the legacy branch alone, because the plain entity map turns an authored `&lt;a&gt;` back into a real tag, and a tag map running after it would silently promote text the author typed as escaped characters into a live Markdown link. Row 14 of the dev table is exactly that shape and must stay text. Passed: `php -l` on the three new files, `composer analyse` (292 files, no errors), `composer test` (694 tests, 7738 assertions, the same 6 pre-existing skips), `php-cs-fixer --dry-run` (nothing to fix), and a full rehearsal, re-run on a fresh `mysqldump`/restore copy of the real 1472-row table after the three fixes: `report` classified 1138 rows as `plain` and 334 as `markdown` with no row left over, `classify` wrote every format, `convert` and `title` rewrote 1472 rows each, and the table ended with 1472 rows, 0 outside `plain`/`markdown`, 0 bodies still carrying a `<br>` and 0 titles still carrying a writer entity. Running both passes again rewrote 0 rows and `classify` refused the finished ledger; `--force=1` accepted it. The site table was read by nothing and ended the run exactly as it started: 1472 rows, all `format = ''`, 1138 still carrying `<br>`. The rehearsal schema and its ledger were removed. `error_php.log` and `error_sql.log` were last written at 10:24 and 09:33, before the run; `error_site.log` carries only the scheduler's standing sendmail failure, 174 entries spread evenly over every hour of the day. Four things this step decided and records rather than improvised silently: the html branch is seeded as stored bytes instead of written, because every content editor a user may pick answers `plain` or `markdown` and only the admin editors answer `html`, so no user request on this installation can reach that branch; `convert` and `title` each exit non-zero only on a defect of their own — a row without a final format or a row count that moved — while the gate is printed as a readout ending in `clean` or `not clean yet`, so a successful `convert` does not abort a scripted deployment merely because `title` has not run yet; `classify` refuses a ledger whose rows a pass has already rewritten before it scans the table, so a converted table is never described by a table of verdicts that no longer mean anything; and the report counts the titles carrying a writer entity and the titles carrying markup separately, because a plain title keeps markup the map does not name as its own characters. Not done here, because the plan gives them their own rows: no runtime code reads `format` yet (step 11) and the `docs/VERSIONS.md` entry with the stage 2 deployment sequence is step 12. The real conversion is not this step's to run: it belongs in the maintenance window of the deployment sequence, behind the closed site and the rehearsed restore. |
| 11 | 2 | Safe rendering for body and title, write-time encoding and `getDecodedText()` compensation removed | not started | |
| 12 | 2 | `docs/VERSIONS.md` entry with deployment sequence, stage 2 verification matrix | not started | |

## Decisions

These answer the open policy questions before any code is written. Changing one
of them invalidates the plan below it.

**Admin access stays super-admin only.** `admin/modules/privat.php:7` and the
`go=5` branch at `index.php:187` both gate on `isAdmin(true)`. There is no
assignable per-module `privat` right today and this project does not introduce
one. `checkAdminPost('privat')` is a POST-and-scoped-CSRF check
(`core/security.php:715-718`), not an authorization check, and the plan must not
describe it as one. Designing an assignable right is out of scope.

**Three update channels must stay in sync.** Every schema change lands in all
three or the release is incomplete:

| Channel | File | Audience |
|---|---|---|
| Fresh install | `setup/sql/table.sql:582` | new installations |
| 6.2 to 6.3 | `setup/sql/table_update6_3.sql:758` | installations upgrading from 6.2 |
| Already 6.3 | `setup/sql/update6_3_patch.sql` | installations already running 6.3 |

The third channel is the one the previous revision of this plan missed. An
installation that already runs 6.3 never re-runs the 6.2 upgrade, so shipping
runtime code that reads `viewed`/`saved`/`delin`/`delout` without a patch section
breaks every such installation on deploy. Each stage adds its own section to
`update6_3_patch.sql` and its own dated entry to `docs/VERSIONS.md`.

**Title becomes plain source, like the body.** See "Content contract".

## Staging

The work splits into two independently shippable stages. Each stage ends with a
consistent tree, passing tests, and no compatibility wrapper. Stage 2 must not
start before stage 1 is merged and deployed.

Stage 1 — state model. Introduce `Privat`, the four independent state columns,
and migrate every caller. Every High finding except the content contract is
resolved here.

Stage 2 — content contract. Introduce `format`, migrate stored titles and
bodies with a dedicated tool, and move frontend and admin rendering onto
`Parser::filterContent()` with `safe = true`.

The split is deliberate: stage 2 carries the only irreversible body conversion in
the project, needs a maintenance window and a restore rehearsal, and depends on
the editor and parser stack. Stage 1 is a schema and ownership change validated
by row counts alone. Merging them would put the reversible fix behind the
irreversible one.

## Scope

Included in stage 1:

- inbox, outbox, saved messages, detail view, and compose form;
- read/unread and bulk mailbox actions;
- unread and mailbox counters;
- account dashboard preview;
- email notification queueing and points side effects;
- every user-deletion path;
- admin list and deletion;
- state columns in all three update channels.

Included in stage 2:

- the `format` column in all three update channels;
- `tools/privat-migrate.php` and the conversion of stored titles and bodies;
- frontend and admin rendering through the safe parser contract.

Out of scope: conversations, attachments, group messages, realtime delivery,
encryption, search, new moderation features, and an assignable per-module admin
right.

## Architecture

Create `core/classes/privat.php` with final class `Privat`. Instantiate it in the
core bootstrap beside `Comment` (`core/system.php:147`).

The class owns:

- every read and write of `_privat`;
- mailbox visibility and ownership rules;
- recipient resolution, limits, and flood control;
- message creation, read/unread, save, bulk actions, account cleanup, user
  delete, and admin delete;
- list counts and pagination data.

HTTP adapters keep:

- `getVar()`, authentication, POST/CSRF checks, and route responses;
- templates, parser output, links, redirects, and localized messages;
- points and queued email as post-write side effects.

No template, request, session, or mail dependency belongs in `Privat`.
Points and email queueing run only after a successful insert; their failure is
logged and never removes an accepted private message.

Keep the public API small: list/count/detail/recent/admin reads plus send,
read/unread, save, bulk action, account cleanup, user delete, and admin delete.

`addMessage()` returns `['id' => int, 'error' => string]`. `id` is
`getSqlLastId()` on success and `0` otherwise. `error` is a stable machine code
from a closed set — `ok`, `no_recipient`, `unknown_recipient`, `self`,
`no_title`, `no_body`, `word_long`, `flood`, `quota`, `not_logged`, `sql` — and
never a localized string. The adapter maps the code to a language constant, and
the notification side effect keys off `id`. Every other mutating method returns
`bool`; every read returns a typed array.

## Storage model

Keep one `_privat` table:

- existing message fields: `id`, `uidin`, `uidout`, `title`, `body`, `time`,
  `ip`;
- `viewed`: recipient has read the message;
- `saved`: recipient moved the message to saved;
- `delin`: recipient removed the message from their mailbox;
- `delout`: sender removed the message from their outbox;
- `format` (stage 2): `plain` or `markdown`, and `''` while a row is still
  unclassified.

Every state column is `TINYINT UNSIGNED NOT NULL DEFAULT 0`. The current `status`
column is declared `BOOLEAN` while the code stores `2`; the replacement must not
repeat that mismatch.

`format` is `VARCHAR(20) NOT NULL DEFAULT ''`, the same declaration `_comment`
already carries. The empty default is the point: no statement in a schema section
can tell a `plain` body from a `markdown` one, because that verdict is computed
per body by `tools/privat-migrate.php` and not per column. `''` therefore means
"not classified yet" and exists only inside the stage 2 maintenance window,
between the schema section and the gate. The gate is what makes it a state the
release cannot end in, and it is why runtime needs no `legacy` format and no
unsafe fallback: by the time stage 2 code is deployed, `''` no longer exists in
the table. Added on 2026-08-05 during step 9, which shipped the column.

### Mailbox predicates

Nothing below may be restated in a caller. Every list, count, and quota is one of
these predicates, and the index set and the parity checks are derived from this
table alone.

| View | Predicate | Order |
|---|---|---|
| Inbox list | `uidin = :uid AND delin = 0 AND saved = 0` | `time DESC` |
| Inbox count / quota `messin` | `uidin = :uid AND delin = 0 AND saved = 0` | — |
| Unread badge | `uidin = :uid AND delin = 0 AND viewed = 0` | — |
| Saved list | `uidin = :uid AND delin = 0 AND saved = 1` | `time DESC` |
| Saved count / quota `messsav` | `uidin = :uid AND delin = 0 AND saved = 1` | — |
| Outbox list / count | `uidout = :uid AND delout = 0` | `time DESC` |
| Outbox unread badge | `uidout = :uid AND delout = 0 AND viewed = 0` | — |
| Dashboard preview | inbox list, `LIMIT 6` | `time DESC` |
| Detail as recipient | `id = :id AND uidin = :uid AND delin = 0` | — |
| Detail as sender | `id = :id AND uidout = :uid AND delout = 0` | — |
| Flood window | `uidout = :uid`, no state filter | `time DESC LIMIT 1` |
| Admin list | no state filter | `time DESC` |
| Physical delete | `delin = 1 AND delout = 1` | — |

The unread badge ignores `saved` on purpose: a message saved without being opened
is still unread and must stay in the badge. The flood window ignores `delout` on
purpose: deleting your own sent message must not reset your rate limit.

The outbox unread badge is the sidebar counter `_PROUTNO`, "how many of the
messages I sent has the recipient not opened yet". It was added to this table on
2026-08-05, during step 3, because `blocks/user_info.php:23` already counted it
as `uidout = :uid AND status = 0` and the table had no row for it. Dropping the
counter would have been a behavior change nothing in this project asked for. It
reads the `out_box` prefix `(uidout, delout)` and filters `viewed`; whether it
earns a key of its own is decided by the `EXPLAIN` step with every other row.

The outbox predicate is a behavior change. Today the outbox filters
`status <= 1`, so the moment a recipient saves a message it vanishes from the
sender's outbox. `delout = 0` is the correct rule and restores those rows.

### Admin status

Derived from the four columns, evaluated in this order, first match wins:

1. `delin = 1` — deleted by recipient
2. `delout = 1` — deleted by sender
3. `saved = 1` — saved
4. `viewed = 1` — read
5. otherwise — unread

`delin` and `delout` are never both set on a visible row: setting the second one
deletes the row. The recipient-side flags outrank `saved` and `viewed` because a
deleted copy is what an administrator needs to see first.

### Indexes

Final set, confirmed by the `EXPLAIN` step on 2026-08-05. It drops three of the
four single-column keys the table carried; `time` keeps its place:

```
PRIMARY KEY (id)
KEY time    (time)
KEY in_box  (uidin, delin, saved, time)
KEY in_new  (uidin, delin, viewed)
KEY out_box (uidout, delout, time)
KEY out_new (uidout, delout, viewed)
KEY flood   (uidout, time)
```

`in_box` serves the inbox list, the saved list, and both quotas. `in_new` serves
the badge, which does not filter `saved`. `out_box` serves the outbox list.
`out_new` serves the outgoing unread badge. `flood` serves the send interval.
`time` serves the admin list.

No key carries `id` explicitly: an InnoDB secondary key ends on the primary key,
so every one of these is really `(..., id)`. That is what makes
`ORDER BY time DESC, id DESC` an index read rather than a filesort in the three
list predicates, and the same holds for the admin list on `time`.

The last two keys were added by this step, both on measured evidence against the
migrated dev database (1472 rows, one account owning 341 of them):

- `out_new`: without it the outgoing unread badge of `blocks/user_info.php` was
  `type=ALL` over the whole table for that account, because `out_box` is
  `(uidout, delout, time)` and cannot filter `viewed`; for an ordinary account it
  read 93 index entries to answer 18. With it the same reads are `ref`/`out_new`,
  8 and 18 rows, `Using index`. The block renders on every authenticated page, so
  this is the one predicate whose plan is paid for per request.
- `flood`: without it the send interval was `ref`/`out_box` with `Using filesort`
  for an ordinary sender, and `index`/`time` for the heavy one — a backwards walk
  of the `time` index that has no bound at all for an account that has never sent
  anything. With it both are `range`/`flood`, `Using index`, no filesort. This is
  the case the index section already named in advance.

`time` is declared ahead of the composites on purpose. An upgrade drops the three
single-column keys and adds the composites last, so `time` is the only key that
keeps its place; declaring it first in the fresh schema is what lets all three
channels end on a byte-identical table without rebuilding an index on every run.

Dropped: `uidin`, `uidout`, `status`.

Every remaining row of the predicate table was confirmed by the same run and
needs no key of its own: the two detail reads are `const`/`PRIMARY`, the physical
delete and every bulk write are `range`/`PRIMARY` over the ids they name, and the
admin list and its count are an ordered read of `time`, 50 rows and a covering
scan. The two `deleteUser()` sweeps are not predicate rows and are left as they
are — `range` over `in_box` and `out_box` for an ordinary account, a primary-key
scan for the heaviest one — because they run once per deleted account.

## Behavior changes

Intended product changes, not incidental effects of the refactor. Each one gets a
line in `docs/VERSIONS.md` in the release that ships it.

- A sender may delete an outgoing message after the recipient has read it. Today
  the delete is restricted to `status = 0` (`core/user.php:982`), so a read
  message stays in the outbox forever.
- A recipient deleting a message no longer removes it from the sender's outbox,
  and the reverse. Today one delete removes the single shared row.
- A message the recipient saved stays visible in the sender's outbox.
- Saving no longer discards the read state, and a saved message no longer becomes
  undeletable for the sender.
- Read and unread become separate actions; today a message can only move from
  unread to read.
- Bulk read, unread, save, and delete actions appear in the mailbox views.
- A send is refused when the saved folder of the recipient is full, and not only
  when their inbox is. Both quotas are rechecked in the write transaction, so the
  mailbox a message cannot fit into is the whole mailbox rather than one half of
  it. Today `messsav` bounds only what a recipient saves for themselves.

## Concurrency protocol

"Serialize sufficiently" is not an instruction. `addMessage()` runs exactly this:

1. Resolve the recipient ID from the submitted name, before the transaction is
   opened. Step 3 is what makes that safe.
2. `$own = !$db->checkSqlActive();` and `setSqlBegin()` when `$own` — the same
   nested-transaction idiom `Comment::setStatus()` uses
   (`core/classes/comment.php:346`).
3. Lock both participants in one statement, ascending by ID:
   `SELECT id FROM PREFIX_users WHERE id IN (:a, :b) ORDER BY id FOR UPDATE`.
   One statement with a deterministic order means two sends in opposite
   directions cannot deadlock against each other. A self-message collapses to one
   row, which is correct. The statement also reports which of the two accounts
   the server really holds, so the ID resolved in step 1 is confirmed here and is
   never trusted on its own: an account the lock does not find is gone.
4. Re-run the flood query and both quota queries inside the transaction. The
   values read before the lock are advisory only and are never trusted for the
   decision.
5. `INSERT`, then `getSqlLastId()`.
6. Commit when `$own`. Roll back on any failed check and return the matching
   error code.

The order of the first two steps is load-bearing and not cosmetic: **the lock
must be the first statement of the transaction.** InnoDB fixes the snapshot that
every later consistent read of a transaction answers from at that transaction's
first consistent read. A plain read taken before the lock therefore makes step 4
answer out of a snapshot older than the lock itself, and a competing send that
has just committed and released the lock is invisible to it — the exact race the
lock exists to close. Measured during step 5 on 2026-08-05, not reasoned about:
with the recipient lookup inside the transaction, two processes racing for the
last free place of one mailbox both passed the quota and left that mailbox
holding four messages against a quota of three; with the lookup moved in front of
the transaction, so the locking read is its first statement, the same race ends
in one accepted send and one `quota` refusal. This rule binds every write in the
class that takes a lock, `setMessageSaved()` included.

Locking `_users` rather than `_privat` is deliberate: a gap lock on `_privat`
cannot serialize the first message ever sent to a user, because there is no row
to lock. Both quota checks and the flood check key off user identity, so user
rows are the correct lock target.

Deadlock handling: on SQLSTATE `40001` (errno 1213) or a lock-wait timeout
(errno 1205), roll back and retry the whole step once. A second failure returns
`error = sql` and writes one line to `error_sql.log`. Retries never re-run the
points or mail side effects, which sit outside the transaction and after it.

`setMessageSaved()` takes the same kind of lock for the same reason. It rechecks
the saved quota inside its own transaction, but a batch locks only the rows it
names, so two parallel batches would both read a folder with room and both fill
it. Before it counts, it locks the account it belongs to —
`SELECT id FROM PREFIX_users WHERE id = :uid FOR UPDATE` — which is the same row
a send locks, so a save and a send racing over the same mailbox serialize against
each other rather than past each other. No other mutation carries a quota and no
other one takes the lock.

## Content contract

Private messages store source, never trusted HTML.

Today `filterHtml()` encodes both fields on write (`core/user.php:928-929`) and
both are rendered with `safe = false` (`core/user.php:831`, `core/admin.php:816`).
The admin list then decodes the title back on read (`core/admin.php:813`). That
is not an open hole, but every stored row depends on the `filterHtml()` rules in
force when it was written, and the title carries a second, undocumented contract
of its own.

Stage 2 replaces both with one rule:

- `body` is source. Every row runtime ever reads carries `format` as `plain` or
  `markdown` and nothing else — the storage model's `''` is a migration state the
  gate has already cleared before this code is live, not a third format this
  contract has to render. An HTML editor is not a trust grant: its submitted
  markup is treated as Markdown source and escaped. Frontend and admin both
  render it through `Parser::filterContent()` with `safe = true` and the stored
  format.
- `title` is plain text source. It carries no markup and no format column, is
  stored decoded, and is escaped at the template boundary by `{{ }}`. The
  `getDecodedText()` call at `core/admin.php:813` is removed with the contract it
  compensated for.

Administrators are explicitly allowed to read private-message contents in the
existing `privat` admin module. This is a system policy, not an accidental
popover side effect. The admin view uses the same safe renderer as the recipient
and exposes no raw stored body to a template.

## Migration

### Stage 1 procedure

The same statements go into all three channels. They must be safe to run against
any of these states, because a crash or a re-run lands in one of them:

| State | Shape | Required outcome |
|---|---|---|
| A | `status` present, no new columns | full conversion |
| B | `status` present, some new columns present | finish the conversion |
| C | `viewed` present, all new columns present | no change |
| D | `viewed` present with the wrong type or default | fix the definition only |

Order, because it is load-bearing:

1. `addcol` `saved`, `delin`, `delout` as `TINYINT UNSIGNED NOT NULL DEFAULT 0`.
   `addcol` is already guarded and skips a column that exists.
2. Backfill `saved` from `status`. **This is the step the existing helper set
   cannot express.** A bare `UPDATE ... SET saved = 1 WHERE status = 2` fails in
   states C and D because `status` no longer exists, which breaks the restartable
   requirement. Add a guarded procedure alongside the others in
   `setup/sql/table_update6_3.sql:24-33`:

   ```
   runifcol(ptab, pcol, psql)   -- executes psql as dynamic SQL only when ptab.pcol exists
   ```

   The backfill runs through it, keyed on `status`. The same condition makes the
   step self-idempotent: once `status` is gone the backfill can never run again.
3. `rencol('{prefix}_privat', 'status', 'viewed')` — already guarded.
4. Fix the definition. `rencol` preserves the old `BOOLEAN` type, so `viewed`
   must be forced to the final definition. The helper set has no conditional
   `MODIFY`, so add:

   ```
   modcol(ptab, pcol, pdef)     -- MODIFY only when the column exists and its
                                -- information_schema definition differs from pdef
   ```

   Call it for `viewed` with `TINYINT UNSIGNED NOT NULL DEFAULT 0`. The
   definition comparison is what makes state D converge and stops a pointless
   table rewrite in state C.
5. Normalize: `UPDATE {prefix}_privat SET viewed = 1 WHERE viewed > 1`. Safe to
   re-run, and a no-op once step 2 has mapped the saved rows.
6. Indexes: `delidx` the old `uidin`, `uidout`, `status` keys, then `addidx` the
   composites from the index section. **Delete the existing
   `CALL addidx('{prefix}_privat', 'status', '`status`', 0)` at
   `setup/sql/table_update6_3.sql:766`** — leaving it makes the same file create
   an index it then drops, and leaves a key on a column that no longer exists.

Steps 2 and 3 are ordered: the backfill must read `status` before the rename
consumes it.

### Stage 1 parity

Run before dropping any index, and again after, per user ID, comparing against a
snapshot of the legacy data:

- legacy `status <= 1 AND uidin = :uid` equals new `delin = 0 AND saved = 0`;
- legacy `status = 2 AND uidin = :uid` equals new `saved = 1`;
- legacy `uidout = :uid AND status <= 1` is a **subset** of new
  `uidout = :uid AND delout = 0`, because the new outbox predicate restores rows
  the old one hid;
- total row count is unchanged; no row is deleted by the migration.

One documented consequence: a legacy saved message becomes `viewed = 1`, because
`status = 2` carried no read bit. Saving requires opening the message in the UI,
so read is the correct assumption, and it is recorded here rather than guessed at
later.

### Stage 2 procedure

`tools/comment-migrate.php` cannot be reused. It is hardwired to `_comment`
(`tools/comment-migrate.php:148` and every statement after it) and its modes are
written against the comment column set. Stage 2 ships `tools/privat-migrate.php`,
modelled on it and sharing its discipline, not its code:

- modes `report`, `classify`, `convert`, `sample`, and `title`;
- `classify` writes `format` and a per-row ledger in
  `storage/migrate/privat-format.json`, converts nothing, and is reviewed before
  `convert` runs;
- `convert` is driven by the ledger, marks every row it finishes, stores the body
  it replaced, and converts nothing twice on a second run;
- `title` decodes the legacy entity-encoded titles, with the same ledger
  discipline;
- `--db=` and `--prefix=` so the whole run is rehearsed against a restored copy
  first;
- `--size=` batching, so a large table is not one transaction.

**Stage 2 requires a maintenance window with the site closed.** This is the same
rule the comment migration already carries (`docs/VERSIONS.md:89`), and it is
stricter here: classification and conversion are two passes, so a message written
or edited between them would be converted under a verdict that was never computed
for it. Closing the site is what makes the two passes describe the same rows. A
database dump and a rehearsed restore are a precondition, not a recommendation.

Deployment sequence for stage 2, in this order and with no step folded into
another:

1. Close the site.
2. Dump the database and rehearse the restore.
3. Run the `format` schema section for this installation's channel.
4. Run `classify` and read its report.
5. Run `convert`, then `title`.
6. **Gate.** Prove no row is left behind:
   `SELECT COUNT(*) FROM {prefix}_privat WHERE format NOT IN ('plain','markdown')`
   must return `0`, the ledger must report no unfinished row, and the row count
   must equal the pre-migration count. A non-zero result stops the deployment —
   restore or finish the conversion, do not proceed.
7. Deploy the stage 2 runtime code.
8. Reopen the site.

Runtime has no `legacy` format and no unsafe fallback, so the code must not be
live before step 6 has passed.

Previously deleted shared rows cannot be reconstructed; that limitation is
documented rather than papered over with placeholder messages.

## User deletion paths

`Privat` owns mailbox cleanup, so every path that physically deletes a `_users`
row calls `Privat::deleteUser()` inside the same transaction. Two exist today:

- `modules/account/admin/index.php:918` — account admin deletion, alongside
  `_favorites` and `_user_oauth`;
- `admin/index.php:150` — creating an administrator with `auser_new` deletes a
  same-named `_users` row first. That user can own messages, so this path is not
  exempt.

Step 7 of the plan proves with `rg` that no third path exists. If `Privat` turns
out not to be bootstrapped in the `admin/index.php` context, that is a stop
condition: raise it rather than silently exempting the path.

Settled on 2026-08-05, so step 6 does not have to ask again: `admin/index.php:8`
loads `core/system.php`, and the service is built above the branch that separates
the frontend from the panel, so `$prv` exists in that context. The path itself
sits in `add_admin()` and reaches the service through the `global` list of that
function. It deletes the account by name rather than by id, so the id has to be
resolved before the row goes, or the cleanup has nothing left to key off.

`deleteUser()` marks the user's incoming and outgoing sides deleted, removes rows
already deleted by both sides, and leaves the counterpart's copy visible with the
missing account rendered as `_ANONYM`.

## Security and consistency

- Keep all SQL parameterized and authorization inside class queries.
- All mutations are POST-only and require `checkSiteToken()`. Tokens are sent in
  the POST body, not in action URLs.
- Authenticated list and counter GET routes verify `is_user()` without a CSRF
  token. Opening a message marks it read, so that HTMX action is POST/CSRF.
- Admin deletion runs through one POST handler using `checkAdminPost('privat')`
  for method and scoped-CSRF enforcement, behind the existing `isAdmin(true)`
  gate. The `go=5` GET route is deleted, not re-secured.
- Recheck recipient existence, inbox quota, saved quota, and sender flood window
  inside the write transaction, under the lock protocol above.
- Measure the longest word in characters, not the last word in bytes.
- Read the private-message notification preference from `psmail`, not `fsmail`.
- Use the ID returned by `getSqlLastId()` for the notification link.
- Return no message data when the relevant `delin` or `delout` flag is set.
- Bound, deduplicate, and validate every bulk ID through `getVar()` before the
  class rechecks ownership under its transaction. One foreign or invalid ID
  rejects the whole batch.
- Account deletion wraps user cleanup and `Privat` cleanup in one transaction.
- The class returns data, never HTML; only safely rendered output crosses an
  `*_html` template boundary.

## Localization and release notes

- New actions (read, unread, select-all, bulk apply, per-state admin labels) need
  constants in all six locales — `de`, `en`, `fr`, `pl`, `ru`, `uk` — per
  `.rules/constants.md`, in `modules/account/lang/` and `admin/lang/`.
- Reuse an existing global constant wherever one covers the concept; a scoped
  duplicate of a global is a defect.
- Genuinely new feature constants take the `_PRIVAT_` scope prefix matching the
  `privat` config namespace, within the 2-18 character limit. Do not extend the
  legacy unprefixed `_PR*` family.
- Each stage adds a dated `docs/VERSIONS.md` entry covering: which of the three
  channels the installation needs, the behavior changes above, and for stage 2
  the mandatory tool run and maintenance window.
- The stage 1 entry states that the schema section and the runtime code of the
  release are deployed together. An installation that applies the state-column
  section while still running code that reads `status` answers an SQL error on
  every private-message page until the code follows it.

## Current findings

Verified against the working tree on 2026-08-05.

| Stage | Priority | Finding | Evidence |
|---|---|---|---|
| 1 | High | One delete removes the shared row, so a recipient deleting a message also destroys the sender's outbox copy. | `core/user.php:982`, `setup/sql/table.sql:590` |
| 1 | High | A single `status` encodes unread, read, and saved for both sides: saving discards the read state, hides the message from the sender's outbox, and makes it undeletable for the sender. | `core/user.php:776`, `core/user.php:969`, `core/user.php:982`, `core/user.php:688` |
| 1 | High | Flood window and inbox quota are read outside the insert transaction, so concurrent sends pass both checks. | `core/user.php:905`, `core/user.php:924`, `core/user.php:930` |
| 1 | High | The inserted ID is rediscovered with `ORDER BY id DESC LIMIT 1`, so a concurrent send can put a foreign message ID in the notification link. | `core/user.php:936` |
| 1 | High | Account deletion removes users, favorites, and OAuth links but never touches `_privat`. | `modules/account/admin/index.php:918` |
| 1 | High | A second user-deletion path exists and was previously unaccounted for: creating an administrator deletes a same-named user row. | `admin/index.php:150` |
| 1 | Medium | The notification reads the forum preference `fsmail`, so the `psmail` setting has no effect. | `core/user.php:934`, `modules/account/index.php:865` |
| 1 | Medium | The word-length loop overwrites its result each iteration and measures bytes, so only the last word is validated. | `core/user.php:909` |
| 1 | Medium | The detail view always loads the profile of `uidout`, so the outbox shows the sender their own profile instead of the recipient's. | `core/user.php:772`, `core/user.php:784` |
| 1 | Medium | Save and delete run over GET. CSRF is enforced for the whole `go=1` branch, so the defect is a credential carried in the URL and a non-idempotent GET, not a missing check. | `core/user.php:957`, `core/user.php:979`, `core/user.php:738`, `index.php:107` |
| 1 | Medium | A second admin delete route duplicates the first over GET with the token in the URL. Both are super-admin gated, so this is duplication and credential exposure, not privilege escalation. | `core/admin.php:868`, `index.php:187`, `index.php:200`, `admin/modules/privat.php:88` |
| 1 | Medium | Direct mailbox SQL spans frontend, block, profile, and admin. | `core/user.php:340`, `blocks/user_info.php:23`, `modules/account/index.php:485`, `core/admin.php:805` |
| 1 | Medium | The 6.2 upgrade adds a `status` index this project removes, so the same file would create and drop it. | `setup/sql/table_update6_3.sql:766` |
| 1 | Low | `status` is declared `BOOLEAN` but stores `2`. | `setup/sql/table.sql:590`, `core/user.php:969` |
| 1 | Low | Mailbox counters are cached in the session and invalidated by hand from every mutation. | `core/user.php:468`, `blocks/user_info.php:19` |
| 2 | Medium | Bodies are encoded on write and rendered with `safe = false`, so stored rows depend on the `filterHtml()` rules in force when they were written. | `core/user.php:929`, `core/user.php:831`, `core/admin.php:816` |
| 2 | Medium | The title carries a second, undocumented contract: encoded on write, decoded on admin read. | `core/user.php:928`, `core/admin.php:813` |

## Implementation plan

### Stage 1 — state model

1. Add `runifcol()` and `modcol()` to the migration helper set, then write the
   state-column migration for all three channels per the procedure above,
   including the removal of the stale `status` index call. Add fixture-based
   parity checks and prove states A through D all converge.
2. Add `Privat` and focused unit tests for reads, permissions, limits,
   transactions, independent states, account cleanup, and bounded bulk actions.
   Every query is one row of the predicate table.
3. Migrate the account page, dashboard preview, navigation badge, and user block
   so no direct private-message SQL remains outside `Privat`. Load only the
   active mailbox initially; fetch other tabs on demand. Add read, unread, save,
   and delete bulk actions to the relevant mailbox views.
4. Replace the old AJAX operations with explicit GET read routes and POST
   mutation routes. Keep the handlers thin and move their markup to the existing
   template boundary where necessary.
5. Implement the concurrency protocol in `addMessage()` and the saved-quota lock
   in `setMessageSaved()`, including the deadlock retry, and return the
   documented result shape.
6. Integrate `Privat::deleteUser()` into both deletion paths transactionally.
   Migrate the admin list and collapse the two admin delete paths into the one
   POST handler; delete the `go=5` route and its `core/admin.php` function.
7. Queue email using `psmail` and the returned ID, update points only after a
   successful insert, remove the session-only counter cache, then delete the
   procedural functions. Prove with `rg` that only `Privat` touches `_privat` and
   that no third user-deletion path exists.
8. Add the six-locale constants, the `docs/VERSIONS.md` entry, and the
   `update6_3_patch.sql` section. Run the stage 1 verification matrix.

### Stage 2 — content contract

9. Add `format` to all three channels.
10. Write `tools/privat-migrate.php` with the modes, ledger, and rehearsal
    options above, and test it against a restored copy.
11. Move frontend and admin rendering onto `Parser::filterContent()` with
    `safe = true`, make the title plain source escaped at the template boundary,
    and drop the write-time `filterHtml()` encoding and the `getDecodedText()`
    compensation.
12. Add the `docs/VERSIONS.md` entry with the deployment sequence and the
    maintenance-window requirement, then run the stage 2 verification matrix.

## Verification

Required automated checks, per stage:

- `php -l` for every changed PHP file;
- project PHPStan, PHPUnit, and PHP-CS-Fixer checks;
- migration tests for fresh install, 6.2 upgrade, 6.3 patch, second-run safety,
  each of the states A through D, row and per-user count parity, and
  byte-identical final table definitions across all three channels;
- stage 1 unit tests for every predicate-table row, plus ownership,
  self-message, read/unread/save/delete independence, both user-deletion paths,
  admin delete, bounded bulk actions, mixed owned and foreign IDs, quota
  boundaries, flood control, SQL failures, deadlock retry, and concurrent sends
  that must not both pass quota or flood;
- stage 2 tests for title and body conversion, ledger idempotence, and parser
  payloads proving stored HTML and malicious content render safely in both
  frontend and admin views;
- source checks proving the old functions, old routes, direct table SQL, shared
  `status`, `fsmail` notification read, duplicate admin delete route,
  `getDecodedText()` compensation, and latest-row ID query are gone;
- `EXPLAIN` for every predicate-table row against representative data, with the
  flood query result recorded in the index section.

Required route checks:

- real authenticated inbox, outbox, saved, detail, compose, and dashboard reads;
- send, read, unread, save, bulk actions, user delete, account delete, and admin
  delete through valid POST/CSRF;
- GET, missing token, wrong token, foreign message ID, quota overflow, flood,
  and concurrent attempts make no unauthorized persistent change;
- the removed `go=5` delete route returns no result and changes nothing;
- deletion and saving by one participant leave the other participant's state
  intact;
- a sender can delete a message the recipient has already read, and still sees a
  message the recipient has saved;
- both user-deletion paths remove only that account's mailbox sides and preserve
  the counterpart's safely rendered anonymous copy;
- authorized administrators can read safely rendered message content;
- queued notification references the exact new message and respects `psmail`;
- review `storage/logs/error_php.log`, `error_sql.log`, and `error_site.log`.

## Acceptance

Stage 1:

- `Privat` is the only runtime owner of `_privat`.
- Every query in the codebase matches a row of the predicate table.
- Sender and recipient mailbox states are independent.
- Every write is authorized, POST/CSRF protected, checked, and transactional.
- Concurrent sends cannot bypass flood or quota rules, and a deadlock does not
  lose an accepted message or duplicate a side effect.
- Read/unread and bulk actions cannot mutate foreign messages.
- Every user-deletion path cleans its mailbox sides without deleting the
  counterpart's copy.
- One admin delete path remains and it is POST-only.
- Fresh install, 6.2 upgrade, and 6.3 patch produce identical definitions, and
  each is safe to run twice.
- The old functions, duplicate routes, shared status model, session counter
  cache, and compatibility paths are removed.
- Six locales, `docs/VERSIONS.md`, and the patch file are updated.

Stage 2:

- Body has one safe `plain`/`markdown` storage and rendering contract, and title
  is plain source escaped at the template boundary.
- No stored value is trusted HTML and no runtime path renders with
  `safe = false`.
- `tools/privat-migrate.php` is idempotent, ledgered, and rehearsable.
- Administrator access to safely rendered message content is intentional and
  documented.
