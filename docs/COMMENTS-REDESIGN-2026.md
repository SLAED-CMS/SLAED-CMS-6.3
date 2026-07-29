# Comments Subsystem Redesign

Status date: 2026-07-29. Approved; every stage delivered. The comment
engine is shared by eight frontend modules, the admin panel, the user activity
feed and every module delete handler, so each stage below has to keep all of them
working while the internals move into one place.

## Progress

Written by the assistant at the end of every batch, before its report. A new chat
knows nothing of previous ones; this is the only place decisions survive.

| Date | Stage / batch | Outcome |
|---|---|---|
| 2026-07-27 | stage 0, URL schemes | **done, ahead of the stage.** `filterUrl()` (`core/classes/parser.php:228`) refuses `data:`, `javascript:` and `vbscript:` in every mode; 8 fixture cases added, verified to fail against the unpatched file |
| 2026-07-28 | stage 0, client-chosen moderation and module | **done.** `getCommentMode()` (`core/system.php:5705` today) resolves the target through the fixed eight-entry map; `addComment()` no longer reads `cid`, `updateComment()` and `updateCommentStatus()` read `modul` from the comment row. `tests/Unit/CommentTrustBoundaryTest.php` (6 cases) fails against the unpatched files; baseline `verify` unchanged |
| 2026-07-28 | Stage 1, batch 1 — `Comment`, `CommentStatus`, `CommentMode`, read methods | done. `core/classes/comment.php` added and wired into `core/system.php:143`; six public reads (`getCount()`, `getList()`, `getAdminList()`, `getComment()`, `getUserList()`, `getModuleList()`) and six private helpers, called by nothing yet. The stage baseline was re-prepared and captured first: **all eight modules**, not the six stage 0 ran against. 8 parity tests in `tests/Unit/CommentReadTest.php` drive a new `commentread` probe mode that puts every legacy statement beside the method replacing it against the live rows; the list case was proven to fail by flipping the sort direction. `php -l`, full `phpunit` (314 tests, 3 skipped), `phpstan`, `php-cs-fixer check` and `comment-baseline verify` all green; the front page, `admin.php` and the `news` and `media` comment pages answer 200. `error_php.log` and `error_sql.log` did not grow; `error_site.log` grew only by the `Mail:` entries the transport tests produce, which are the paths those tests own |
| 2026-07-28 | Stage 1, batch 2 — frontend reads move to the class | done. `ashowcom()` (`core/system.php:5483`) lost its count query, its list query and its author join to one `$com->getList()` call and shrank by 67 lines; the render half is untouched. Parity was measured rather than asserted: `comment-baseline verify` stayed OK on all eight modules, and a 17-URL probe over `voting/17`, `news/53`, `files/604`, `links/1` and the two targets carrying pending rows — first, middle and last page, an out-of-range page and page 0 — produced byte-identical regions against the pre-move file, run twice, once per sort direction, the two directions differing from each other so both were really exercised. A second probe added the cases the first could not reach — a target with no comments at all, one that spills a single row onto a second page, one holding exactly the page size, and `com=abc`, `com=-3`, `com=0` and a missing target id — and every comment region matched the pre-move file there too. The four pending comments stayed invisible to a guest. `php -l`, full `phpunit` (314 tests, 3 skipped), `phpstan`, `php-cs-fixer check` all green. `error_php.log` and `error_sql.log` did not grow; `error_site.log` grew by the `Mail:` entries of the suite runs and by four `404` entries, one per probe run, produced by the missing-id case the probe requests on purpose — both code versions logged them alike |
| 2026-07-28 | Stage 1, batch 3 — frontend writes move to the class | done. `addComment()`, `updateComment()` and `setStatus()` added to `core/classes/comment.php`, with five private helpers (`checkAddRules()`, `checkEditRules()`, `getLinkFlag()`, `getLastTime()`, `getLastId()`); the three request handlers shrank to 16, 22 and 7 lines (`core/user.php:266`, `core/system.php:5672` and `:5696`) and hold no `_comment` SQL any more. Parity was measured twice over. A rolled-back transaction drove the class against the live rows: all **eight** modules stored the row they resolved, each incremented **its own** target counter by one and awarded **its own** points slot, an anonymous add landed pending and moved neither, every refusal answered the message the submit path answered, the author edited inside the window and was refused after it, and a visitor could neither edit nor moderate — `tests/Unit/CommentWriteTest.php`, 9 cases, proven to bite by pointing `numcom()` at the wrong module, which failed on the counter and the slot at once. The six POSTs that reach the three migrated routes — empty text, wrong token, unknown module, guest edit, guest preview, guest status — answered **byte-identically** before and after the move, with the comment count and the target counter unchanged. The GET that fetches the form for its token is not part of that comparison: two runs of one code version already differ in page length and token, so the page is not byte-stable on this stand and `comment-baseline verify` is what covers it. `php -l`, full `phpunit` (320 tests, 4 skipped), `phpstan`, `php-cs-fixer check` and `comment-baseline verify` on all eight modules all green. `error_sql.log` did not grow; `error_php.log` grew only by one warning from the scratch probe script itself; `error_site.log` grew only by the `Mail:` entries of the suite runs |
| 2026-07-28 | Stage 1, batch 4 — the moderation module moves to the class | done. `admin/modules/comments.php` holds no `_comment` SQL any more, takes no `$db` in any handler, and shrank from 529 to 467 lines: the module selector reads `getModuleList()`, the list and its count read `getAdminList()`, the edit form reads `getComment()`, and the four write handlers call `setStatus()` and the new `deleteComment()` and `updateBody()`. The separately built count query is gone with `getTplPager()` — the pager renders through `getTplPagerView()` from the total the list itself counted, so result and count can no longer disagree. Parity was measured over real HTTP with a signed-in administrator, which closes the **moderator** gap batches 1-3 left open. **42 admin URLs in two rounds, 39 byte-identical** in the module content region: round one covered both tabs, all five search fields, the module filter, first, second and last page, two empty-result cases, the edit form, the edit form of a missing id, `config` and `info`; round two added the shapes the first could not reach — the pending tab crossed with every search field, the module filter crossed with two of them, the edit form of a pending row and of `id=0`, and search terms carrying `%`, `_`, a backslash, `<b>`, Cyrillic, surrounding spaces and `' OR 1=1 --`, plus a quoted and an uppercased module filter. All three that differ are the same page clamp, recorded below. The write routes produced **identical** persistent state across 16 scenarios — status both ways, a repeated transition, a missing id, single and bulk delete, a repeated delete, bulk with no type, the moderation body save and four wrong-token refusals — same row states, same bodies, same target counter (6 → 3), same points (6 → 1), same row count; 15 of the 16 answered the same redirect, the sixteenth being the `delete()` deviation below. `php -l`, full `phpunit` (323 tests, 4 skipped), `phpstan`, `php-cs-fixer check` and `comment-baseline verify` on all eight modules all green. `error_site.log` grew only by the `Mail:` entries of the suite runs; `error_php.log` and `error_sql.log` grew by the same entries in both code versions, except the one SQL syntax error the migration removed |
| 2026-07-28 | Stage 1, batch 5 — activity feed, profile hub and the eight target-delete handlers | done. `deleteTarget()` and `getUserCount()` added to `core/classes/comment.php`; the eight module delete handlers, the profile feed (`core/user.php:722`) and the profile hub (`modules/account/index.php:327`) hold no `_comment` statement any more, and `productops()` in `modules/shop/admin/index.php` binds its whole id list instead of pasting it into six `IN (...)` clauses. **One runtime consumer is left and is not this batch's to take**: the waiting-content chip of the admin sidebar, `core/admin.php:319`, which counts pending comments through `getAdminCountRow()` — see the deviations. Parity was measured three ways. The **feed renders byte for byte**: `tests/Support/contract_probe.php` holds a verbatim copy of the pre-move function and puts it beside the migrated one on the live rows — 10 accounts, six with comments, one without, plus a missing, a zero and a negative id, all identical once the per-render instance counter of the tabs widget is normalised, and the hub triple (count, rating, favourites) matched for all seven accounts. `deleteTarget()` was driven **inside a rolled-back transaction**: each of the eight modules removed exactly the rows its target held and no others, a bulk selection removed both its targets, a target id shared by five modules was deleted for one and left the other four untouched, an empty module, an empty list and a list of non-positive ids each removed nothing, and a crafted list and a crafted module name removed exactly the id they decode to and no row at all. The eight handlers were then exercised **over real HTTP with a signed-in administrator**: a fixture of 2 comments per module against a target id that belongs to no row, a wrong token first (fixture untouched), then the eight routes one at a time — each removed **only its own module's** two rows, the `files` route also removed its fixture target row, a repeated delete was a no-op, and every per-module total returned to the value it started from. `php -l`, full `phpunit` (334 tests, 12 skipped), `phpstan`, `php-cs-fixer check` and `comment-baseline verify` on all eight modules all green. `error_sql.log` grew by one entry the migration does not own, and `error_php.log` grew by the same two entries in both code versions — both recorded below |
| 2026-07-28 | Stage 1, batch 6 — `numcom()` and the resolver absorbed, `ashowcom()` deleted, the stage guard | done, **stage 1 closed**. The three globals are gone from `core/system.php`: `numcom()` is `updateTargetCount()` (`core/classes/comment.php:240`), `getCommentMode()` is `getTargetMode()` (`:126`), and both read one `MODULES` map (`:28`) that carries the target table and the points slot of the eight modules at once — the counter map and the supported-module list are now one list. The three retired branches (`account`/`members`, `gallery`, `multimedia`, slots 3, 17, 29) left with the move; the `users.points` CSV still holds its 45 positions. `ashowcom()` was deleted and its frontend half is `getCommentList()` in `core/user.php:10`, beside `setComShow()`, which is where the plan always put the rendering; the seven unreachable `defined('ADMIN_FILE')` branches went with it and `core/system.php` shrank by 255 lines. The **last runtime consumer batch 5 left is closed**: the waiting chip reads `getStatusCount(CommentStatus::Pending)` and `getAdminCountRow()` takes an optional precomputed number (`core/admin.php:274`, `:320`), and the three dead keys of the comment entry of `getProfileModules()` are gone with it. `tests/Unit/CommentIsolationTest.php` is the stage guard — 6 cases, and it was run against the stashed pre-batch tree: **3 fail there** (`ashowcom()` still defined, the module map absent, the profile map still carrying a table name) and the sidebar assertion was shown to fire on `core/admin.php:319` by hand. The other 3 pass against both trees by design — batch 5 had already removed the last literal statement and the points CSV was never touched, so those guard forward rather than backing this batch. Two deletions were also checked against the live table rather than argued: `status` is `tinyint(1)`, so the chip's old `status = '0'` and the new bound integer answer the same 3; and **no stored row carries a module outside the eight-entry map** (files 4821, voting 1084, news 1083, faq 141, pages 116, links 104, media 2, shop 2, and 0 anywhere else), so dropping the three retired counter branches cannot move a counter or a points award for any row that exists. Parity was measured over real HTTP against the pre-move tree, 80 URLs per round — per module the busiest target at first, middle and last page, `com=0`, `com=-3`, `com=99999`, `com=abc` and no page at all, its smallest target, a target with no comments, a missing id and a missing target parameter. Descending: **80/80 byte-identical**, 64 of them carrying a rendered region. Ascending: 73/80, and the 7 that differ were reproduced by **one code version against itself** across two runs — the unstable `ORDER BY time` tie-break stage 2 replaces with `time, id`, not this batch. The admin sidebar chip region is byte-identical between the two trees and reads `3` on both. What the guest probe cannot reach — the **moderator** branch of the render — was closed by **source equivalence** instead: the deleted `ashowcom()` and the new `getCommentList()` were put side by side with the rename map applied, and the only differences left are the seven `defined('ADMIN_FILE')` branches, `$afile`, `$mark` and `$val['cid']` that went with them, and the empty-list branch turning into an early return. The three htmx moderation links do not appear in that diff at all. `php -l`, full `phpunit` (340 tests, 12 skipped), `phpstan` and `php-cs-fixer check` all green, and `comment-baseline verify` answered OK on all eight modules **twice during the batch**; on the final run it reported CHANGED for `news`, `pages` and `shop`, which is the points drift batch 5 recorded and not markup — see the deviations. `error_sql.log` did not grow at all; `error_php.log` and `error_site.log` grew only by entries both trees produce — recorded below |

| 2026-07-28 | Stage 2 — validation, storage and write consistency | done, **stage 2 closed**. The schema carries the five columns and the five indexes, `KEY cid` and `KEY modul_status` are gone and `KEY time` stayed; a scratch database proved that a fresh `table.sql` install and the upgrade batch produce a **byte-identical** table definition, that the upgrade is idempotent on a second run and that it repairs a table missing three of its indexes to the same definition. `reqkey` was backfilled in SQL before its unique index existed — 7353 rows, 7353 distinct 32-character keys, none empty. `tools/comment-migrate.php` classified and rewrote every body: 101 legacy, 1709 plain, 1 review, 4 multi-line, 5538 single-line, then backfilled `iphash` for all 7353. Parity was measured as **meaning rather than bytes**, as the plan asks: the pre-migration body rendered the old way and the migrated body rendered the new way were compared for all 7353 rows, **7232 identical**; of the 121 that differ, 68 are the plain format no longer applying Markdown, 9 are legacy tags becoming Markdown inside code-like text, 1 is the review row and the rest are constructs the old unsafe render swallowed and the new one shows. Both classes round-trip: the moderation save and the author edit both store back exactly what the editor was handed. `checkRules()` is one rule set for both paths and measures the longest word in characters; `CommentMode` replaced the bare `acomm` comparisons; add, edit, status and delete are transactional with checked results, conditional updates and a soft delete; `reqkey` answers a replay from the failed insert; the flood window reads `iphash`; listings sort on `time, id`. All four render sites moved to `safe = true` — the three the plan names and the profile feed label, which renders a comment body too. `tests/Unit/CommentStateTest.php` is the stage guard, 14 cases through a probe that **signs in as an administrator before the core boots**, which closes the moderator gap batches 1-3 left open for status and delete. `php -l`, full `phpunit` (398 tests, 12 skipped), `phpstan` and `php-cs-fixer check` all green; the eight module pages answer 200 and both routes refuse a wrong token without writing a row. The markup baseline was re-captured at the end, as this stage changes rendered output by design, and `verify` is green on all eight modules against it. `error_sql.log` did not grow at all; `error_php.log` grew only by the `core/user.php` guest defect batch 5 recorded; `error_site.log` grew only by the `Mail:` entries of the suite |

| 2026-07-28 | Stage 3 — move comment mail off the request | done, **stage 3 closed**. The submit handler (`core/user.php:406`) owns one transaction now: it opens it, `Comment::addComment()` joins it instead of committing its own, `addAdminMail()` writes the queue rows inside it, and the handler's commit closes the comment and its notification together. `addComment()` answers a fourth key, `new`, so the notification is written **once per stored comment**: a replayed `reqkey` answers the first comment and queues nothing. Measured through the new `commentnotify` probe against live rows inside a transaction it rolls back: one add stored 1 comment and queued 1 row — exactly one administrator of this installation is subscribed and reaches the `news` audience — the row carries `kind = comment`, `status = 0`, `tries = 0`, `prio = 1`, `ref = 0` and a body linking to `#<new id>`, and the rollback took the comment and the job away together (both deltas 0). A refused add wrote neither; the replay wrote neither and answered the first id; a refused `addQueue()` (a rejected address) answered `false`, left the transaction open and left the stored comment in place, which is what "a delivery failure never rolls back the comment" means at the write boundary. Latency: the comment write and its notification cost **5.8 ms** together and the notification alone **0.8 ms**, against **77 ms** for one HTTP render of the target page — the 26.6 s of the old synchronous `mail()` left with stage 2 of `docs/MAIL-2026.md` and cannot recur, because `addAdminMail()` ends in `addQueue()` and holds no send of its own. `tests/Unit/CommentNotifyTest.php` is the stage guard, 10 cases, and **6 of them fail against the stashed pre-batch tree**. `php -l`, full `phpunit` (410 tests, 12 skipped), `phpstan`, `php-cs-fixer check` and `comment-baseline verify` on all eight modules all green. `error_sql.log` did not grow at all; `error_php.log` grew by 6 entries of the known `core/user.php:158` guest defect, exactly two per `verify` run and nothing else; `error_site.log` grew only by the `Mail:` entries of the suite |

| 2026-07-29 | Stage 4 — fragment responses | done, **stage 4 closed**. Adding a comment answers **one comment fragment** instead of the whole list: the response names its own placement through `HX-Reswap`/`HX-Retarget`, so a cacheable page carries no swap decision. The per-comment render left `getCommentList()` as `getCommentView()` (`core/user.php:13`), which the list, the add response and the status response all call. `addComment`, `updateCommentStatus` and the new `deleteComment` are **POST only**, refused in one place before any handler runs (`index.php:110`); the edit form is the only comment route a GET still reaches, and `updateComment()` reads a body from POST alone. **No token rides in a comment URL any more**: the form carries a hidden `token` from `getPageToken()`, the moderation actions inherit an `X-CSRF-TOKEN` header the comment element declares once, and `getTplAjaxTextarea()` was moved to the same shape, which takes the forum editor with it. A comment that offers no action declares no token at all, which is why the guest markup is **byte-identical** to the pre-stage capture on all eight modules. The form clears only on a stored comment: the response answers `HX-Trigger: sl-comment-add` and the shared script resets the form and mints the next `reqkey` there, so a refused submit keeps the text and retries under the same key. The **key is minted in the browser** as the design asks, `Cache::addEpoch()` moved from the route into the class and fires only after a write that succeeded, and `hx_on_after` left both button fragments with its last user. Measured over real HTTP with a signed-in administrator against the `media` fixture: a GET mutation refused, a wrong token refused and storing nothing, an empty body refused without a reset trigger, a valid add answering `afterbegin` with one fragment and moving the counter by one, a replay storing no second row and answering the first comment, status both ways answering `outerHTML` with the comment itself, the edit form and its save, a **plain POST without HTMX answering `303` to the target anchor** and storing the comment, delete answering `HX-Reswap: delete` and taking the counter back, a repeated delete moving nothing. On the busiest `voting` target the two branches the fixture cannot reach were measured as well: a full first page **sheds its far-end row out of band** — the id was predicted and matched — and a reader on page three gets the announcement with a link rather than a row in the wrong slice. Every probe row was removed again and both tables returned to the values they started from. `tests/Unit/CommentTransportTest.php` is the stage guard, 12 cases, and **all 12 fail against the stashed pre-batch tree**. `php -l`, full `phpunit` (448 tests, 5 skipped), `phpstan`, `php-cs-fixer check` and `comment-baseline verify` on all eight modules all green |

| 2026-07-29 | Stage 5 — threads | done, **stage 5 closed**. `_comment` carries `pid` and an ascii-binary `path` of ten-digit segments, with `modul_cid_pid_time` and `modul_cid_path` beside them; the upgrade adds the columns, makes **every stored comment a root of its own** and only then builds the indexes, so they are built once over final values. Measured on this installation: 7357 rows, **0 without a path and 0 carrying a parent without one**. Nothing was re-parented from the old `[b]name[/b],` convention. `addComment()` takes a parent id and resolves it through `getParentPath()`: a parent of another target, a removed one, one the writer cannot see and one already at the depth limit are refused alike, so a crafted `pid` can only fail. The page **counts and paginates roots** and every root arrives with its whole branch in path order; `getBranch()` answers one branch on its own with an explicit limit. A removed comment that still carries a live reply **stays as a tombstone** and leaves the page once its last reply is gone — the rule is a predicate rather than a stored flag, so it can never drift. Measured against live rows inside a rolled-back transaction: the three-comment chain stored `pid` and path exactly, a crafted and a foreign parent were refused, the **twentieth segment stored and the twenty-first refused**, the page total equalled the root count while the page carried 27 rows for 8 roots in `[0,0,1,2]` depth order, `getBranch()` honoured a limit of 3 and of 1, the tombstone kept its place and its child and then disappeared with it, and the table came back to 7357 rows. Over real HTTP: the form carries the reply field, a new root answers `afterbegin` with `pid = 0`, a reply answers `HX-Reswap: afterend` retargeted at the branch it joins and is stored as `0000020693/0000021547`, a crafted parent is refused without a reset trigger and stores nothing, and the tombstone renders with no author column while its reply stays visible. `EXPLAIN` on the four new statements: root count and root page on `modul_cid_status_deleted`/`modul_cid_pid_time`, the branch load and `getBranch()` on `modul_cid_path`, the tombstone test an `index_subquery` — all sub-millisecond on the 131-comment target. `tests/Unit/CommentThreadTest.php` is the stage guard, 10 cases, and **all 10 fail against the stashed pre-batch tree**. `php -l`, full `phpunit` (458 tests, 5 skipped), `phpstan` and `php-cs-fixer check` all green; the markup baseline was **re-captured at the end**, as this stage changes rendered output by design, and `verify` is green on all eight modules against it. `error_php.log` did not grow at all, `error_sql.log` grew by the single duplicate-key entry the replay probe produces by design, and `error_site.log` grew only by the `Mail:` entries of the suite runs |

| 2026-07-29 | Stage 4 — the `page` row of the transport table | done, **the transport table is complete**. The rows of the list moved into `#repcrows` and the numbered pager renders after it, so a slice can be appended without landing behind the pager. `getCommentRows()` renders the rows of one page and, while the discussion continues, one control at the end of them: an ordinary link to `&com=N+1#comm` carrying `hx-get` to the new `getCommentPage` route, `hx-target="this"` and `hx-swap="outerHTML"`, so it **replaces itself** with the answer and always stands at the end of what is loaded. The route answers rows plus the control for the page after them and nothing else — no container, no pager — and **refuses** a page past the last rather than letting the pager clamp and answer the last page a second time. A cursor was considered and rejected: `filterCanonicalParams()` already drops `com` from the canonical, so paginated comment URLs consolidate onto the target page, and cursor addresses would only add an unbounded crawl space for no indexing gain. The dead anchor of the notification was fixed with it: the mail link and the "on another page" notice now carry `&at=<comment id>`, which `setComShow()` resolves through the new `Comment::getRootPage()` — a range count over roots on `modul_cid_pid_time`. Measured over real HTTP on `news/53`, 81 comments across 6 pages: the first page carries the container, exactly one control whose plain href is `&com=2#comm`, and the pager after it; page two answers **15 rows with no overlap** against page one, no container, no pager and the control for page three; the last page answers its 6 rows and **no** control; page 99 answers nothing; the route without a token is refused; and `&at=1489` — a comment that is not on page one — renders that comment with the pager marking its page. `tests/Unit/CommentTransportTest.php` grew to 15 cases and all 15 fail against the stashed pre-batch tree. `php -l`, full `phpunit` (463 tests, 5 skipped), `phpstan` and `php-cs-fixer check` all green; the markup baseline was re-captured, because the row container and the control are new markup by design |

### Decisions made during execution

- The stored-XSS fix landed in the parser rather than in the comment render
  sites, so it covers forum posts and every other content path. The rendering
  mode itself is untouched and still moves to `safe = true` in stage 2.
- The resolver is one procedural function, `getCommentMode($mod, $id)`, returning
  the target's `acomm` and `0` for "not writable" — unknown module, missing row,
  invisible row or comments disabled all collapse into the same refusal. Stage 1
  absorbs it into `Comment`; it is deliberately not a class in a hotfix stage.
- Visibility is the module's own `view()` predicate, copied per module rather
  than generalized: `time <= NOW() AND status != '0'` plus `catmids()` for the
  seven category modules, and the `modul = '' AND enddate/status` rule for
  `voting` (`modules/voting/index.php:96`).
- A target with `acomm = 0` now refuses the write for **everyone**, moderators
  included. Previously the form was merely not rendered.
- The request keeps carrying `mod`; it is a lookup key into the map and never a
  table name. The moderator links inside `ashowcom()` still append `&mod=`, which
  the server now ignores — removing it is markup churn and belongs to stage 4.
- **The constructor takes `$conf` as a third argument**, so the wiring line is
  `new Comment($db, $prs, $conf)` rather than the plan's two-argument sketch. No
  class in `core/classes/` reaches for a global — `Mail` takes `($db, $conf)` for
  the same reason — and the reads need `comments.num`, `comments.anum` and
  `comments.sort` for their own pagination. Only the `comments` section is kept.
- **`$prs` is stored and unused until a later batch**, exactly as `Mail` kept
  `$db` through stage 1: the parser belongs to the read helpers that render a
  body, and taking it now means the wiring line never has to change again.
- **The pager arithmetic is a method, not a copy.** `getPager()` resolves total,
  pages, offset and the running number of the first row from one place, because
  the frontend list and the moderation list both need it and the current code
  spells it out twice with different variable names.
- **The visibility scope is resolved once per read.** `getScope()` answers the
  count query and the page query with the same predicate, which is what the
  stage 2 acceptance criterion asks of the admin list and what the frontend list
  already needs today; `getAdminScope()` does the same against the account join,
  so the count can no longer drift from the result the way the two hand-built
  `$where`/`$wcnt` strings in `admin/modules/comments.php:95-126` could — batch 4
  removed both.
- **Public reads ask for `status = 1`, not `status != 0`**, per the query rule in
  the design. The column holds 0 and 1 only — measured, all 7357 rows — so the
  result is identical and the parity probe proves it row by row.
- **The author search keeps two placeholders for one term.** `:fnam` and `:fusr`
  look redundant, but `PDO::ATTR_EMULATE_PREPARES` is off (`core/classes/pdo.php:26`)
  and a native prepared statement refuses a named placeholder used twice.
- **A list resolves its scope once and counts against it.** `getList()` first
  built the scope for its count and again for its page, which called
  `is_moder()` twice and left two places able to describe the same list. One
  `getTotal($from, $pars)` now counts whatever source the list itself reads,
  frontend and moderation list alike. Cheap either way — `is_moder()` reaches the
  database only for a signed-in administrator and memoizes it statically
  (`core/system.php:4767`) — but two descriptions of one list is the defect the
  admin `$where`/`$wcnt` pair already demonstrates.
- **`getUserList()` collides in name with a global function that stays.**
  `core/system.php:4792` defines `getUserList()` for the user-name autocomplete;
  it has nothing to do with comments and no stage deletes it. A method and a
  function of one name coexist without ambiguity in PHP, so the plan's name is
  kept, but a reader of `core/user.php` will meet both — worth knowing before
  batch 5 migrates the activity feed.

- **The unreachable admin read branch left with the read it belonged to.** The
  `defined('ADMIN_FILE')` half that built `WHERE status != 0 AND modul = ...`,
  `anum` and `anump` is exactly the code `getList()` replaces, and the facts above
  (`:115-118`) already record that no admin file calls `ashowcom()`. Keeping it
  would have meant forking the function around a branch nothing can reach. The
  admin **render** branches — the checkbox column, the moderation links, the
  `form-wrap` — are untouched; they are the HTML monolith batch 6 removes with the
  function itself.
- **A broken `comments.num` no longer divides by zero.** The old arithmetic
  computed `ceil($total / $conf['comments']['num'])` directly; `getPager()` falls
  back to 15 when the setting is missing or zero. The value is `15` here, so the
  rendered output is identical, and the difference is only reachable through a
  configuration that used to be fatal.
- **`$a` and `$b` became `$numb` and `$mark`.** Both are assigned in the lines
  the move rewrites — `$numb` now comes from `getPager()` — and both broke the
  single-letter rule of `.rules/global.md:106`. Pure renames, no output change.
- **Two readers of one setting, and they agree only by an invariant.** `Comment`
  snapshots `$conf['comments']` in its constructor (`core/system.php:146`) while
  `ashowcom()` still reads `sort` and `nump` live for the pager. Checked before
  relying on it: nothing in `core/`, `modules/` or `admin/` writes that section at
  runtime, so the snapshot cannot drift inside a request. A later batch that
  introduces a per-request override would break the pager silently and has to move
  those two reads into the class instead.
- **No new test in batch 2.** The stage criterion that batch works toward is
  "no direct `_comment` SQL outside `Comment`", which cannot be asserted while
  five call sites still hold it; that guard belongs to batch 6, once, for the
  whole stage. What was checkable there is byte parity, and that is what the
  baseline tool and the page probe measured.
- **The class calls `getCommentMode()` and `numcom()` as globals for now.** The
  batch table says the counters still go through `numcom()`, and the resolver is
  in the same position: both are absorbed by batch 6, which owns the deletions.
  Moving the resolver here as well would have re-pointed the stage 0 guard twice
  for no behaviour change.
- **The write path returns data, and the handler renders.** `addComment()`
  answers `id`, `name` and `error`; `updateComment()` answers `allow`, `mod`,
  `body`, `saved` and `error`; `setStatus()` answers a bool. The alert fragments,
  the ajax textarea, `$prs->filterContent()`, `ashowcom()` and `addAdminMail()`
  all stay in the handler, because the point of the stage is to remove the HTML
  monolith rather than move it into the class.
- **Validation moved with the write it belongs to, both copies unmerged.**
  `checkAddRules()` returns the single message the submit path shows and
  `checkEditRules()` the list the edit path shows, because that is what the two
  paths do today. Stage 2 merges them into `checkRules()`; merging them here
  would have changed behaviour in a batch that promises none.
- **The edit length check still measures the last word in bytes.** It is the
  defect stage 2 fixes (`checkEditRules()` in the class now, `core/system.php:5695`
  before the move), so it was moved verbatim; only the single-letter loop
  variables it used were renamed to satisfy `.rules/global.md:106`.
- **`!$body` guards the edit, not `$body === ''`.** The old branch tested `!$text`,
  so a body of exactly `0` opened the editor instead of saving. A strict
  comparison would have silently changed that; the falsy test keeps it, quirk
  included.
- **`CommentStatus` is applied, `CommentMode` is not.** The enum names the value
  written to the `status` column, so the write path uses it. The `acomm == 1`
  comparisons stay bare because the plan schedules `CommentMode` for stage 2, and
  the two changes are independent.
- **`checkCaptcha()` moved into the class with the rest of the rules.** It is the
  last rule the submit path applies and therefore the one that overrides all
  others; leaving it in the handler would have meant reproducing that precedence
  at the call site.
- **The write parity is measured inside a transaction that is rolled back.**
  `tests/Support/contract_probe.php` opens one, sets the target it picked to
  `acomm = 2`, writes through the class, reads the row, the counter and the
  points back, and rolls the whole thing away; the probe reports the comment
  count from before and after so the test can assert the rollback really
  happened. Every table involved is InnoDB — checked before relying on it. That
  is what makes a real "published add" checkable on the same stand the markup
  baseline is captured from, which stage 0 could not do.
- **The stage 0 guard follows the code it guards.** `CommentTrustBoundaryTest`
  read the three request handlers by name; the handlers no longer hold the
  decision, so it now reads the class methods and asserts of the handlers only
  that they carry no `mod` from the request and no `_comment` SQL at all. The
  behaviour half over `getCommentMode()` is untouched. Batch 4 extended the same
  guard to `deleteComment()`, because it is a third path that decides a
  permission from a stored row, and re-pointed the `numcom()` assertion at the
  `$cid` the status guard needs.
- **`setStatus()` took the admin's read-then-compare guard, and the frontend
  path inherits it.** The moderation module skipped both the write and
  `numcom()` when the row already carried the wanted status; the frontend
  handler did not, so a repeated `updateCommentStatus` moved the target counter
  and the author's points a second time. One method cannot hold both, and
  routing the admin through the unguarded version would have introduced that
  double count into the path this batch migrates. The guard is what stage 2
  wants anyway, in its conditional-update form. `setStatus()` still answers
  `true` for a no-op transition, so the frontend fragment it renders is
  unchanged — only the redundant counter movement is gone.
- **The moderation body save is its own method.** `updateBody()` stores what the
  moderator typed, without the author's edit window, `checkEditRules()` or
  `filterHtml()`, because none of the three ever ran on this path.
  `updateComment()` is the author path and applies all three; merging them would
  have changed one of the two.
- **`updateBody()` reads no row and checks no moderator.** Its two siblings
  check `is_moder()` because they already load the row for their counters; this
  one would need an extra query for a check that cannot fail, since
  `admin/modules/comments.php:7` already refuses everyone but a super
  administrator, for whom `is_moder()` is true by definition
  (`core/system.php:4784`).
- **The admin list lost its second description of itself.** `getTplPager()`
  builds a `COUNT()` from a `$where` string the caller hands it, which is why
  `admin/modules/comments.php` carried `$where` and `$wcnt` side by side, the
  second spelling the author search as a subquery instead of a join. The pager
  now renders through `getTplPagerView()` — the same function `getTplPager()`
  ends in — from the total `getAdminList()` counted against the very source it
  read. Measured equal on the join: `u.id` is the primary key of the account
  table, so the `LEFT JOIN` answers exactly one row per comment.
- **`$anum` is gone from the module; the pager reads `$data['limit']`.** The
  page size now has one reader, the class, and the module renders whatever the
  class actually paginated with. `$anump` stays in the module, because the
  number of pager links is a rendering setting the class does not own.
- **`deleteTarget()` binds one placeholder per id and takes no counter with it.** A deleted
  target has no row left to hold a `comments` value and no page left to render, so the
  method removes the comments and stops there — `numcom()` is not called, exactly as the
  eight handlers behaved before the move. The id list is bound value by value rather than
  joined into the statement, which is what closes the `shop` interpolation the stage lists
  as an acceptance criterion, and it makes the bulk and the single case one method instead
  of two.
- **`deleteTarget()` does not re-validate the module against the eight-entry map.** Every
  caller passes a literal, never a request value, and a name outside the map simply matches
  no row — measured: `deleteTarget('gallery', [1])` removes nothing. Reproducing the map
  here would be a second copy of the one `getCommentMode()` owns, and batch 6 is the batch
  that absorbs that resolver.
- **The whole shop id list was bound, not just the comment statement.** The plan names the
  parameterisation of `modules/shop/admin/index.php:707` as an acceptance criterion, and
  that line disappears into the class anyway; leaving the neighbouring `_favorites` and
  `_products` deletes pasting the same list would have satisfied the letter of it and none
  of the point. `productops()` now builds `$keys`/`$pars` exactly the way the `news` and
  `pages` handlers already did — the divergence the facts list flags is gone, and all six
  `IN (...)` clauses in that function bind. The list was integers before and after
  (`array_filter(array_map('intval', ...))`), so this is robustness, not a live hole.
- **The feed keeps one round trip per module and adds one for comments.** The UNION now
  carries the seven content modules and `getUserList()` answers the comment tab on its own;
  the profile hub is the same shape with `getUserCount()`. That is two statements more per
  profile page than before, and it is the price of the comment table having one reader —
  the alternative is a UNION branch built from a module map, which is exactly the
  construction that made `_comment` reachable from two files that own no comments.
- **The unreachable "no parts" guard of the feed moved to the query.** `if (!$parts) return '';`
  could never fire while the comment branch was added unconditionally, and after the move it
  would suppress a feed that has comments on an installation with every content module
  disabled. The guard now wraps the UNION itself, so a comment-only feed still renders and
  the empty case renders exactly what it rendered before.
- **One map answers both questions the eight modules raise.** `getCommentMode()`
  carried a module-to-table map and `numcom()` an eleven-branch chain over the
  same names; the tables in the two agreed exactly. `MODULES`
  (`core/classes/comment.php:28`) is that one list, each entry holding the target
  table and the points slot, and the resolver and the counter both index it. A
  name outside it resolves nothing and moves nothing, which is the same refusal
  both spelled out separately before.
- **The resolver is public, the counter is private.** `getTargetMode()` answers a
  question about a target that a caller may legitimately ask — the stage 0 probe
  does, and `setComShow()` is handed the same `acomm` by its module — while
  `updateTargetCount()` is bookkeeping that only a write of this class may
  perform. Making the counter public would re-open exactly the hole stage 0
  closed, from inside the project instead of from the request.
- **`ashowcom()` was deleted, not renamed in place.** Its two callers are both in
  `core/user.php`, the design puts the rendering in `setComShow()`, and the name
  broke the verb-plus-noun rule of `.rules/global.md:112-116`. The frontend half
  is now `getCommentList()` directly above `setComShow()`, so the whole comment
  render-and-request surface reads top to bottom in one file, and
  `core/system.php` keeps only the two ajax handlers that answer a route.
- **The seven unreachable admin branches went with the function.** The facts list
  already recorded that no admin file calls `ashowcom()`; the checkbox column, the
  `markcheck` header, the moderation link set, the popover, the `form-wrap` and
  the `_NO_INFO` empty text were reachable only under `defined('ADMIN_FILE')` and
  are deleted rather than moved. `admin/modules/comments.php` builds its own
  table and is untouched. The move makes the claim structural rather than
  observational: `core/user.php` is required only when `MODULE_FILE` is defined
  (`core/system.php:153-157`), so `getCommentList()` cannot execute under
  `ADMIN_FILE` at all, and a branch on that constant inside it would be dead by
  construction rather than by inspection.
- **The waiting chip took a number, not a table.** `getAdminCountRow()`
  (`core/admin.php:274`) counts by building `{prefix}_{table}` from what it is
  handed, and it is shared by fifteen sidebar rows across nine modules, so the
  comment row could only leave it by handing over a precomputed value. The helper
  now takes an optional `$num` and skips its own query when it is given; the other
  fifteen callers pass nothing and are unchanged by construction. This is the
  consumer batch 5 measured and deliberately deferred.
- **The three dead keys left the comment entry of the profile map.**
  `'table' => 'comment'`, `where` and `rate` were read by nobody once batch 5
  moved the feed and the hub, and a table name sitting in a module map is exactly
  the disguise that hid two consumers from every `_comment` sweep. The guard
  asserts they stay gone; `fav` stays because the favourites lookup reads the map
  by module key.
- **The guard looks for the table name, not for the word.** A sweep for
  `_comment` matches `is_comment_date`, `cap_comments` and a dozen template flags;
  the guard matches `PREFIX_DB.'_comment` instead, strips whole-line comments
  first so a commented-out statement does not count as a consumer, and pins the
  list of files that build a table name from a variable so a new one has to be
  reviewed rather than discovered later.
- **The rewritten list loop lost its snake_case names.** `$com_modul`,
  `$com_text`, `$com_status`, `$uname` and `$get_id` broke
  `.rules/global.md:102-103` and are now `$cmod`, `$val['body']`, `$stat`,
  `$val['name']` and `$gid`; `$backStatus` became `$tab`. Pure renames inside
  the lines the move rewrites, no output change.
- **The moved render loop lost its snake_case names too.** `$com_id`, `$com_cid`,
  `$com_modul`, `$com_date`, `$com_uid`, `$com_name`, `$com_host`, `$com_text`,
  `$com_status`, the fifteen `$user_*` author fields and `$uname_html` all broke
  `.rules/global.md:102-105`, and the function was being rewritten rather than
  copied, so they are `$cmid`, `$cmod`, `$when`, `$cuid`, `$cnam`, `$host`,
  `$body`, `$stat`, the `$a*` author group and `$unam`. `$numstories` became
  `$total`, `$ccnum` `$size` and `$plnum` `$links`. Two duplicated expressions
  were named while they were being renamed anyway — `$gone` for the deleted-account
  test the avatar and the tooltip both computed, and `$rimg` for the rank image
  path `file_exists()` and the fragment each built for themselves — which is what
  brings those two lines under the 180-character limit. Pure renames; the 64
  rendered regions of the parity round are byte-identical.

- **`plain` means no Markdown, not no BB.** The plan defines the format by four
  Markdown examples — `*text*`, `# title`, `` `code` `` and `[t](u)` — and says
  nothing about BB. `filterPlain()` (`core/classes/parser.php:680`) therefore
  runs the bracket layer and the escape and stops there: no headings, lists,
  tables, quotes, emphasis, code fences or Markdown links, and every line ending
  becomes a `<br>`. Killing BB as well would have made the 1709 migrated `plain`
  rows show `[b]` as text, which is the appearance the migration promises to
  preserve.
- **The inline BB pairs had to survive `safe = true`, and that is a change to the
  shared parser.** `[b]`, `[i]`, `[u]`, `[s]`, `[color]`, `[family]` and `[size]`
  produced plain `<strong>`-style output, which `filterSafe()` then escaped, so
  moving comments to `safe = true` would have rendered `&lt;strong&gt;` as
  **visible text** in 294 single-line rows alone. They now stash their opening and
  closing tag the way `[url]` and `[img]` always did, so the content between them
  still goes through the rest of the pipeline. One fixture recorded the old
  behaviour (`ParserFixturesTest`, `bold md + bb bold`) and was updated with five
  new cases beside it. Every other safe-mode consumer gains the same reading of
  inline BB, which is what "read-only legacy that still displays" already meant.
- **An html editor no longer decides a comment format; it is refused and the body
  is kept as markdown source.** `getBodyFormat()` maps `getEditorMode()` through
  the two allowed values, and anything else falls back to `markdown`. Refusing
  the write instead would stop commenting altogether on an installation running
  CKEditor or TinyMCE, which is a bigger change than the one the stage is making,
  and the trust grant is closed either way because the body is escaped at render.
- **The legacy tag map does not escape what it does not cover.** The plan says
  "everything else is escaped", which was written when escaping still belonged to
  the write path; escaping at storage now would double-escape at render, exactly
  the defect the same section warns about for legacy rows. An unknown tag is left
  as the text it is and `safe = true` escapes it once, which is the same visible
  result the plan asks for — measured on the 101 converted rows.
- **The single review row is converted rather than parked.** Rule 3 has no verdict
  in the plan, and a row without a `format` fails the migration's own invariant.
  It is treated as `plain`, its `<br>` becomes the line ending it stood for, and
  its id is printed by `report` and `classify` so an administrator can look at it.
- **The idempotency key is minted on the server when the request carries none.**
  The plan puts the key in the browser at submit time, and that belongs to the
  transport work of stage 4 — but the unique index exists from this stage, so an
  add that stored an empty key would fail for every comment after the first one.
  `addComment()` accepts a `^[0-9a-f]{32}$` value from the request and generates
  one otherwise, and the replay path is already live: a duplicate key answers the
  comment the first request stored.
- **The class joins a transaction that is already open instead of refusing.**
  `Pdo::setSqlBegin()` answers `false` both when it cannot start a transaction and
  when one is already running, so the first version of this stage broke every
  parity probe stage 1 is built on — they drive the class inside a transaction
  they roll back. `Database::checkSqlActive()` (`core/classes/pdo.php:254`) now
  answers the question directly: an operation owns the transaction only when none
  is open, and otherwise performs its writes and reports failure to whoever does.
  Nothing in the project calls a comment write from inside another transaction, so
  production behaviour is the plan's; what this buys is the ability to measure the
  writes against live rows without leaving a row behind.
- **`getTargetMode()` answers `CommentMode` rather than an int.** That is what
  "apply `CommentMode` to every remaining bare `acomm` comparison" means for the
  resolver itself; `setComShow()` still takes the column value its eight modules
  read and converts it at the boundary. A backed enum encodes as its own value, so
  the probe reports are unchanged.
- **The moderation save writes `edited` and reads the raw field.** The body is
  source from this stage on, and `getVar(..., 'text')` runs `filterHtml()`, which
  would escape the moderator's text a second time on every save. `editsave()`
  reads `raw` and `updateBody()` stores it verbatim, which is what makes the
  round-trip check pass for both classes.
- **The comment write path lost `stripslashes()` and the `$`/`\` escaping with
  `filterHtml()`.** `filterCommentBody()` keeps only what changes the text rather
  than its markup: the trusted-html tokens a visitor may not open, the clickable
  link rewrite and the censor list. A backslash an author types is now stored and
  shown as a backslash.
- **`setStatus()` binds the wanted state twice under two names.** The conditional
  update needs it in `SET` and in `WHERE`, and a native prepared statement rejects
  one named placeholder used in two positions — the same rule the author search of
  the moderation list already documents. It was found by the probe, which reported
  every transition as refused until the second name was added.
- **`deleteTarget()` stays a physical delete and opens no transaction.** A deleted
  target has no row left to reference and no counter to move, so there is nothing
  to keep consistent with; it is also the one write the probes drive in bulk, and
  a transaction of its own would have made the batch-5 measurements unrepeatable.
- **The migration keeps a ledger and can be rehearsed against a copy.** `classify`
  writes the verdict of every row to `storage/migrate/comment-format.json` as well
  as to the column, `convert` marks each row it finishes there and stores the body
  it replaced, and `--db=` points the whole run at a restored copy. That is what
  makes the destructive half re-runnable, reviewable and undoable in place, and it
  is how the conversion of this installation was proved before it was applied.

- **The transaction moved to the handler; the mail did not move into the class.**
  Composing the notification is `addAdminMail()`'s job — recipients, subject,
  `$conf['mtemp']`, the anchor fragment — and it is shared with eleven module call
  sites, so reproducing it inside `Comment` would have been a second copy of the
  audience expander. The other direction is what the two designs already ask for:
  `Mail::addQueue()` is documented as "called inside the caller's transaction"
  (`docs/MAIL-2026.md:1379`) and every `Comment` write joins an open transaction
  rather than insisting on its own, so the request handler is the only place that
  can own both. It is not a new pattern here — `modules/account/index.php:1359`
  already owns a transaction in a request handler, and
  `tests/Support/contract_probe.php` has driven the class inside one since batch 3.
- **`addComment()` answers a fourth key instead of the handler guessing.** Only
  the class knows whether the row was inserted or the `reqkey` was replayed, and a
  replay stores nothing, so it must queue nothing. `new` is that answer, and it is
  a **behaviour change**: before this batch a replayed submit sent the subscribed
  administrators a second notification for one comment. `getKeyResult()` therefore
  answers `new = false` in both of its branches, and so does every refusal.
- **A refused queue write is ignored on purpose.** `addQueue()` answers `false`
  and records the reason through `Logger`; the handler does not read it, because a
  comment that is already stored must not be lost over a notification that could
  not be written. A statement that fails does not abort an InnoDB transaction, so
  the commit still stores the comment — measured through the probe, which drives
  a rejected address and finds the transaction open and the row in place.
- **The probe drives the two writes, not the handler.** `getVar()` reads a plain
  scalar through `filter_input()` (`core/security.php:856-857`), which reads the
  real request and not `$_POST`, so a CLI process cannot feed the handler at all:
  the first version of this probe called it and every add came back `_CERROR3`,
  the guest-name refusal. The behaviour is therefore measured on the two writes in
  the handler's order, and the handler's own wiring — the transaction spanning
  them and the `new` gate — is asserted against its source, the way the stage 0
  guard already reads the write path.

- **The response names the swap, the markup does not.** `hx-swap` on a moderation
  action is `none` and the answer sends `HX-Reswap`, so a refused status change or
  a refused delete leaves the element the request named exactly as it was. The
  first version put `outerHTML` and `delete` in the link, which meant an empty
  refusal removed the comment from the page.
- **A comment element is addressed as `[id='123']`, not as `#123`.** The wrapper
  keeps the bare numeric id every anchor in the project already links to, and a
  selector may not start with a digit — the attribute form is the same element
  without renaming it and without a second attribute to keep in step.
- **The token is declared once per comment and only when there is something to
  post.** `hx-headers` on the comment element is inherited by every action inside
  it, which is one attribute instead of one per link; a comment that offers no
  action is rendered without it, so a reader who may change nothing is served no
  credential — and the guest markup stays byte-identical to the pre-stage capture.
- **The add form carries its token in a hidden field rather than as a header.**
  That is what makes the plain POST path work at all: `hx-include` sends the field
  with the HTMX request and the browser sends it with a plain submit, so one
  mechanism serves both transports. `index.php:104` already accepted either.
- **`beforeend` was never used, because the pager lives inside the list region.**
  `#repcsave` holds the comments *and* `getPageNumbers()`, so appending to it would
  put a comment after the pager. Every insertion except the very first row is
  therefore `afterend` of the row before it, which is also what a reply needs.
- **The form reset moved out of the markup and into the shared script.** The
  submit button reset the form on **every** answer, refusals included;
  `HX-Trigger: sl-comment-add` now fires only for a stored comment and
  `plugins/system/slaed.js` listens for it, resets the form and mints the next
  `reqkey`. `hx_on_after` had exactly one user and left both button fragments.
- **The class invalidates on a write that changed something, not on every call.**
  `setStatus()` and `deleteComment()` answer `true` for a repeated action that
  moved nothing, so they bump the epoch only when the conditional update really
  affected a row. `addComment()` does not bump for a replayed `reqkey` either,
  because that request stored nothing.
- **A frontend delete route was added, because "edit, status and delete operate on
  a single comment" has no other reading.** `Comment::deleteComment()` has existed
  since stage 1 batch 4 and enforces the moderator check itself; the route is the
  fourth action of the same speed dial and shares its token and its refusal path.
- **The old reply action was a naming habit and is now a structure.** The dial
  entry inserted `[b]name[/b],` into the editor; it now points the form at one
  comment through a hidden `pid`. Clicking it twice takes the reply back to the
  top level, and the comment being answered is marked while the form holds it.
- **The tombstone rule is a predicate, not a stored flag.** A removed comment is
  kept in the page exactly while some visible reply still descends from it, which
  is asked of the query rather than written at delete time — a flag would have to
  be cleared again when the last reply goes, and every path that removes a reply
  would have to remember to do it.
- **The tree scope binds the wanted state twice under two names.** The visibility
  test appears in the outer predicate and again inside the tombstone subquery, and
  a native prepared statement rejects one named placeholder used twice — the same
  rule the author search of the moderation list already documents.
- **A branch is loaded per page, not per root.** The page reads its roots first
  and then fetches every reply under any of them in one statement keyed by path
  prefix, so a page of fifteen roots costs one extra round trip rather than
  fifteen. `getBranch()` exists for the caller that wants one branch on its own.
- **The running number follows the roots.** A reply carries no number and no
  anchor chip, because "comment 12 of 131" is a statement about the discussion and
  not about a position inside a branch. The number the status response echoes back
  travels in the action URL, since a re-rendered single comment cannot know where
  the reader is in the page without counting the whole list again.
- **The append is an addition to the numbered pager, and a cursor was rejected on
  SEO grounds.** A seek cursor is the better primitive for a stream that is being
  written to — it does not drift, and `modul_cid_pid_time` already backs it — but
  `filterCanonicalParams()` (`core/system.php:1485`) drops `com` from the
  canonical, so every comment page already consolidates onto the target page while
  staying `index, follow`. Cursor URLs would therefore add an **unbounded** set of
  crawled addresses for no indexing gain at all, where `com=N` is finite. The
  numbered page stays the coordinate system; the append is what a reader gets on
  top of it.
- **The rows moved into a container of their own.** `#repcsave` held the comments
  **and** the pager, so appending to it would have put the next slice behind the
  pager — the trap the add response already avoids by inserting `afterend` of a
  row. `#repcrows` now holds the rows alone, the pager renders after it, and the
  submit form targets the rows rather than the region around them.
- **The first comment of a target replaces the empty notice instead of standing
  above it.** With the row container empty the "no comments" alert is its only
  child, so an `afterbegin` would have left the alert under the new comment. The
  add answers `innerHTML` when the stored comment is the only root there is.
- **A link to one comment names the comment, not the page it happened to be on.**
  The notification built `...&op=view&id=<target>#<id>` with no page at all: under
  the descending default that anchor dies as soon as fifteen newer comments
  arrive, and under an ascending one it never resolved. Both the notification and
  the "on another page" notice now carry `&at=<comment id>`, and `setComShow()`
  resolves it through `Comment::getRootPage()` — one range count over roots on the
  index the list already reads. A page number in the link would have rotted the
  same way; the comment id does not.
- **Depth is a custom property, not one class per level.** The comment element
  carries `--sl-com-depth` and the stylesheet turns it into an indent that is
  capped at half the width, so twenty levels need one rule instead of twenty
  classes and no PHP hands a class name to a template.

### Deviations from the plan

- The plan's `_comment` facts were re-measured on 2026-07-28 and updated in
  place: 7355 rows instead of 7353, and the body-classification, `<br>`-spelling
  and legacy-tag counts with them. The classification methodology is unchanged.
- Stage 0 did not run a live "published add still works" check, because that
  writes a row, moves a target counter and awards points on the stand the markup
  baseline is captured from. The refusal path was exercised over real HTTP
  (four crafted POSTs, `_comment` count unchanged); the accept path is verified
  by code and by the resolver probe, and belongs to the browser checks.
- Batch 1 adds **six** public read methods where the plan names four. `getComment()`
  and `getModuleList()` are not API growth for its own sake: `admin/modules/comments.php`
  reads one comment by id for its edit form (`:265`) and asks for the distinct module
  names of the selector (`:21`), and the stage cannot claim "no direct `_comment` SQL
  outside `Comment`" while batch 4 has nowhere to send either query. Reads belong to
  the batch that owns reads, so they land here rather than being invented in batch 4.
- The parity probe runs as a guest, so the **moderator branch of `getScope()` is
  written but not measured**: the pending comments a moderator of the module also
  sees are asserted by reading the predicate against `core/system.php:5502-5508`,
  not by a comparison against live rows. `isAdmin()` needs a session and the probe
  is a CLI process; that path belongs to the browser checks, together with the
  moderator view the baseline tool deliberately does not capture.
- Batch 2 inherits that gap unchanged. The read it migrates is measured for a
  **guest** across both sort directions and five page positions; the "user" and
  "moderator" rows of the **Verification / Read** list stay open, because both
  need a signed-in session and no credentials for this stand are available to the
  batch. The predicate itself did not change in this batch — `getScope()` landed
  in batch 1 — so what is unmeasured is the same branch batch 1 already flagged.
- Batch 3 closes the "published add" gap of stage 0 and most of the
  **Verification / Write** list — published add, pending add, edit by the owner,
  edit after the window, and a visitor refused on edit and on status — by driving
  the class inside a rolled-back transaction. What stays open is the **moderator**
  half: edit by a moderator, a repeated status transition and the admin bulk
  actions all need `isAdmin()`, which needs a session the CLI probe cannot hold.
  It is the same gap batches 1 and 2 flagged, and it belongs to the browser
  checks together with the moderator view of the list. The ajax-textarea branch
  of the edit handler (`core/system.php:5496`) falls into the same gap: it needs
  an allowed edit **and** a rendered response, so no check here reaches it and it
  is covered by reading the diff alone.
- Three defects were found while measuring batch 3 and left **untouched**,
  because each is outside a batch that promises no behaviour change:
  - the anchor query of a fresh comment, `getLastId()` in the class and
    `core/user.php:304` before the move, reads back by `cid` and `uid` without
    `modul`, so two targets that share an id across two modules can hand the
    admin notification the wrong anchor. `getSqlLastId()` is the fix and belongs
    to stage 2, which makes the write transactional and adds the explicit result
    checks;
  - `updateComment()` returns its alert (`_PEDEND`, and the validation list) but
    `index.php:116` discards the return value, so a refused edit answers an empty
    body over HTTP. The refusal itself works; only the message is lost. Stage 4
    owns the fragment responses of this path;
  - `is_moder('')` decides the edit permission for a comment id that no longer
    exists, because `$mod` is then empty. A full administrator passes that test
    and the handler falls through to an empty answer, so nothing is writable
    either way — but the permission is being asked about a row that is gone.
- Batch 4 changes admin behaviour in **three** measured places, each of them a
  consequence of the move rather than a choice, and each verified against the
  pre-move code over real HTTP:
  - **an out-of-range page now clamps.** `getPager()` pins the page into
    `1..pages`, which the frontend list has always done
    (`core/system.php:5518-5519` before batch 2) and the admin list never did.
    `admin.php?name=comments&num=99999` answered `_NO_INFO`; it now answers the
    last page, byte-identical to `&num=295`. `&status=1&num=2` is the same case
    reached without a silly number — the waiting tab holds four rows, so page two
    of it never existed — and now answers the waiting tab itself. Reproducing the
    old answer would have meant a second pager for one list, which is the defect
    this batch removes;
  - **`&num=0` was a SQL syntax error.** `$offset = ($num - 1) * $anum` produced
    `LIMIT -25, 25`, logged as `42000/1064` and rendered as `_NO_INFO`. The same
    clamp fixes it: the page now answers page one, byte-identical to the list
    without `num`. The error is in `error_sql.log` from the pre-move capture and
    absent from the post-move one;
  - **`delete()` redirected by the deleted row's status, not by the tab.**
    `[$cid, $mod, $uid, $status] = ...` overwrote the request `status` the back
    URL is built from, so deleting a published comment from the home tab landed
    on the *waiting* tab. The row read moved into `deleteComment()`, the
    shadowing is gone with it, and the redirect now keeps the tab the request
    came from. It is one redirect target out of the sixteen write scenarios
    measured; nothing stored changes. `approve()` shadowed the same variable but
    built its back URL from `$typ`, so removing the now-dead read there changes
    nothing.
- Two runtime log entries were **found and left alone**, because neither is
  `_comment` SQL and both behave identically before and after the move:
  `admin/modules/comments.php:321` reads `$typ[0]` when the bulk form is
  submitted with no action selected, logging `Uninitialized string offset 0`;
  and `updatePoints()` drives the unsigned `points` column below zero when a
  moderation action takes more points than the author has, logging
  `22003/1264`. Both appeared exactly twice, once per code version.
- **The two clocks of this stand disagree by an hour**, and it changes what two
  of the write rules can do. MySQL runs at `Etc/GMT-1` while PHP runs at
  `Europe/Berlin`, so a row written with `NOW()` reads back 3600 s in the past
  for `strtotime()`. The flood window (`send`, 30 s) therefore never fires, and
  the edit window (`edit`, 600 s) is already closed by the time the author sees
  their own comment. Both are properties of the installation, not of the code —
  the arithmetic is unchanged by the move — and the probe measures both: it
  reports the skew, and the edit case is asserted against a row whose timestamp
  the probe writes from PHP, so the branch itself is proven to work. Worth fixing
  on the stand, or in the plan, before stage 2 assumes either window is
  observable.

- Batch 5 found a **ninth direct `_comment` consumer the facts list did not name** and
  migrated it: `modules/account/index.php:336` built the profile hub from the same
  `getProfileModules()` map as the feed, so `PREFIX_DB.'_'.$inf['table']` resolved to the
  comment table there too. It never appeared in a `_comment` grep because the name is
  assembled. Leaving it would have made the stage criterion unreachable, since batch 6 owns
  deletions rather than migrations and no later batch owns a read. `getUserCount()` is the
  method it needed; like `getComment()` and `getModuleList()` in batch 1, it is a read that
  landed in the batch that owns reads rather than being invented later.
- Batch 5 also found a **tenth** consumer and deliberately left it: the waiting
  chip of the admin sidebar, `core/admin.php:319`, counts pending comments by
  handing the string `comment` to `getAdminCountRow()`. It is left because the
  fix is not in the comment path: that helper is shared by fifteen sidebar rows
  across nine modules and does the counting itself, so the comment row can only
  leave it by taking a precomputed number — a change to admin chrome, in the
  surface batch 4 already shipped. It is one `SELECT COUNT(id)`, it is
  parameterless, and nothing about it is unsafe; what it blocks is the stage
  claim, not the stage. Whoever takes it needs a `getStatusCount(CommentStatus)`
  on the class and one optional argument on the helper.
- **The comment entry of `getProfileModules()` now carries three dead keys.**
  `table`, `where` and `rate` are read by nobody once the feed and the hub go
  through the class — measured against all four callers of that map. They are
  left because the map is uniform and the other eight entries need them, but a
  `'table' => 'comment'` sitting in a module map is exactly the disguise that hid
  two consumers from every `_comment` sweep, and it will hide the next one.
- Batch 5 closes the **moderator** gap for the delete path alone. The eight target-delete
  routes were driven over real HTTP with a signed-in administrator, so the handlers, their
  token check and their persistence are measured. The moderator halves that batches 1-3
  flagged for the **list and the edit** are untouched by this batch and stay open.
- **The `_comment` counts in the facts list were stale and are re-measured here**, on
  2026-07-28 after batch 4: 7353 rows, not 7357, and 3 pending rather than 4. The drift is
  not this batch's: the figure was already 7353 before any file was touched, the fixture
  round returned every per-module total to the value it started from, and the two batches
  that ran HTTP write scenarios against live rows sit between the two measurements. The
  distribution moved with it — `news` 1088 to 1083 and `voting` 1083 to 1084. The column
  still holds 0 and 1 only, so the `status = 1` rule the reads follow is unaffected.
- **`comment-baseline verify` reported CHANGED for `news`, `pages` and `shop`, and the
  cause was the verification itself.** The three captures differ in one number, at equal
  byte length: the points of account 7885, the administrator the HTTP round signs in as,
  which the comment author card renders. `updatePoints(1)` (`core/system.php:2141`) awards
  one point per rendered page and `users.point` is enabled here, so nine admin renders moved
  it from 40150 to 40159. No markup changed. The value was restored to 40150 under a
  `points = 40159` guard and `verify` answers OK on all eight modules again — the baseline
  was **not** re-captured, because `capture` is a once-per-stage operation. Worth knowing
  before the next admin HTTP round: any signed-in admin traffic moves a value the markup
  baseline compares.
- Two log entries were **found and left alone**, neither owned by this batch:
  - `modules/shop/admin/index.php:447` selects `p.status` and joins `_categories`, which
    also has `status`, then filters on the bare column — `23000/1052: Column 'status' in
    WHERE is ambiguous`, logged when the products list is reached with a status filter. The
    diff of that file is confined to `productops()`; the list query is byte-identical to the
    committed version;
  - `core/user.php:18` reads `$userinfo['access']` after `getUserInfo()` answered null for a
    guest, on the two modules whose target has `acomm != 1` and therefore does not
    short-circuit the test. It appeared twice per `comment-baseline verify` run, before and
    after the batch alike. Batch 6 met it again at `core/user.php:156`, which is
    the same line of the same function after `getCommentList()` was inserted above
    it — 58 entries against the pre-move tree, 102 against the post-move one,
    same defect, same code.
- Batch 6 takes the consumer batch 5 deferred, because no later batch of stage 1
  exists and the stage claim depends on it. The change is the one batch 5
  described — `getStatusCount(CommentStatus)` on the class and one optional
  argument on `getAdminCountRow()` — and it is the only admin-chrome edit in this
  batch.
- **Batch 6 leaves the last `_comment` text in the project untouched**:
  `modules/account/admin/index.php:921` is a **commented-out**
  `DELETE FROM _comment WHERE uid = :id`, so deleting a user still orphans their
  comments. Uncommenting it is a behaviour change in a batch that promises none,
  and `deleteUser()` — the method the design names for it — needs the decision the
  **Verification / Write** list asks for ("confirm the decided behaviour for their
  comments") and which nothing has recorded. The guard strips whole-line comments,
  so the line does not defeat it and would fail it the moment it becomes live code.
- **A defect was found in the moved render and left verbatim.**
  `isset($auid) == intval($user[0])` (`core/user.php:104`, `core/system.php:5590`
  before the move) compares a bool with an int, so it is true for **any** signed-in
  visitor rather than for the author: the edit link is offered to every logged-in
  user inside the edit window, and the write itself is refused by
  `updateComment()`, which checks the stored `uid`. It is a rendering defect, not
  a permission hole, and the batch promises byte parity, so it moved unchanged.
  Stage 4 owns this markup.
- **The ascending round is 73/80, and the 7 that differ are the tie-break, not the
  batch.** Six `faq` regions differ at equal byte length and one `files` region at
  a different length; the same seven differ when **one** code version is run twice
  with other work in between, while two back-to-back runs of it are identical.
  Rows sharing a `time` value come back in whatever order the engine picks, which
  is precisely what stage 2 fixes by sorting on `time, id`. Worth knowing before
  reading any future ascending capture as a regression.
- **Two measurement artefacts, both from the probe method rather than the code.**
  The first admin request issued immediately after `git stash` answered a 1300-byte
  error page and logged `Call to undefined method Comment::getStatusCount()` at
  `core/admin.php:320` — the new `core/admin.php` running against a
  `core/classes/comment.php` opcache had not revalidated yet. The retry was clean
  and the measurement was taken from it. `core/classes/cache.php:74` also logged
  two `rename(...): Zugriff verweigert` warnings from the statistics counter, one
  under each probe, which is a Windows file-lock race unrelated to comments.
- **The points drift of batch 5 recurred, and the batch could not clear it.**
  `comment-baseline verify` answered OK twice during batch 6 and CHANGED for
  `news`, `pages` and `shop` on the final run. The captures differ in **one number
  at identical byte length**, in all three: `<dd>40150</dd>` became
  `<dd>40153</dd>`, the points of account 7885 rendered by the comment author
  card — 2 differing lines in `news` and `shop`, 4 in `pages`, and nothing else.
  The remedy is the one batch 5 recorded: restore the value under a guard and do
  **not** re-capture, since `capture` is a once-per-stage operation. The restore
  is a direct `UPDATE` against live data and this session was not permitted to run
  it, so the baseline is left reporting CHANGED and the statement is handed over
  instead:
  `UPDATE {prefix}_users SET points = 40150 WHERE id = 7885 AND points = 40153`.
  Worth deciding before stage 2 rather than per batch: as long as `users.point` is
  on and the author card renders a live counter, every signed-in round of testing
  moves a value the markup baseline compares, and the parity tool will keep going
  red for a reason that is not the code.
- **`config/comments.php` is not the file a sort change has to be made in.**
  `config/local.php` is a config **cache** (`getConfig()`, `core/system.php:38-44`)
  and is returned whole whenever its `cache_version` matches, so editing
  `config/comments.php` alone changes nothing at runtime. The first ascending round
  of this batch measured descending twice before that was noticed; the round was
  redone with the cache deleted. Both files were restored afterwards and
  `'sort' => '0'` was verified in each.

- **Stage 2 changed what 121 of 7353 rendered comments say, and every one of them
  was looked at by class.** 68 are `plain` rows where the old render applied
  Markdown to text that was never Markdown — a line beginning `1.` became an
  ordered list, `==x==` became a highlight — and the migrated row now shows what
  the author typed. 9 are legacy rows whose `<b>`/`<tt>` tags became `**` and
  `` ` `` inside pasted code, where the emphasis lands on a different word than
  the tag did; they are exactly the rows `report` lists so an administrator can
  review them. 1 is the review row. The rest are text the old render swallowed
  because it looked like a tag — `<--- if (...)` is the clearest case — and which
  now appears. No row lost text.
- **Two rows show an entity the migration does not reverse**, ids 1133 and 1872:
  they carry `&#8206;`, `&#229;` and their kind, which no branch of the writer
  could have produced and which the plan therefore does not decode. Before the
  stage the browser decoded them; now they read as their own text. `report` counts
  and lists them, because an installation whose content came from another engine
  can carry many more.
- **The moderator half was measured through the class, not over HTTP.** Batches 4
  and 5 signed an administrator in over real HTTP; no credentials for this stand
  were available to this session, so the probe signs in by filling the admin
  session **before the core boots** — `isAdmin()` memoizes its verdict on the
  first call the boot itself makes, so signing in afterwards cannot work. What
  that leaves unmeasured is the admin **routes** of `admin.php?name=comments`,
  which this stage changes in one place only: `editsave()` reads the raw field.
  The round-trip it exists for is measured directly on the class instead.
- **`KEY cid` and `KEY modul_status` left batch F rather than being added and
  dropped in one run.** The 6.2 → 6.3 batch created both a few hundred lines
  before batch Y would drop them again. Batch Y still calls `delidx` for both, so
  a 6.2 database that carries them from its own past loses them exactly once.
- **`addidx` cannot repair an index whose columns changed, only one that is
  missing.** Dropping the `iphash` column leaves `iphash_time` behind as
  `(time, id)`, and the helper skips it because the name exists. That state cannot
  arise from a partially applied upgrade — it needs a column to be dropped by
  hand — and it was found while trying to build a partial state, not in one. The
  realistic case, three missing indexes, repairs to the identical definition.
- **The row count is 7353 and the class counts differ from the plan's table.**
  Rule 4 holds 4 rows here where the plan measured 10 and rule 5 holds 5538 where
  it measured 5535; the plan's numbers were taken on 7355 rows before two batches
  drove HTTP write scenarios against live rows. The table above carries the
  measured values and the methodology is unchanged.
- **The points drift of batches 5 and 6 is absorbed rather than fixed.** `verify`
  reported CHANGED for `news`, `pages` and `shop` at the start of this batch — the
  same one number at equal byte length — and the re-capture this stage owns took
  the current value with it. It then recurred during the self-review, because
  every probe run that signs an administrator in renders the author card and
  `updatePoints(1)` awards a point for it, so the baseline was captured once more
  after the last measurement. The property is unchanged and will keep costing a
  re-capture: as long as `users.point` is on, signed-in traffic moves a number the
  markup baseline compares.
- **The page cache still bumps by route.** `index.php:130` invalidates after the
  five ajax writes whether they succeeded or were refused, and `Comment` does not
  own the decision. Stage 4 owns that half of the **Page cache** contract; nothing
  in this stage changed it.
- **`deleteUser()` is still not delivered.** It was stage 1's one missing item and
  stage 2 does not list it; the commented-out statement at
  `modules/account/admin/index.php:921` is still the last `_comment` text outside
  the class, and the behaviour question the **Verification / Write** list asks is
  still unanswered.

- **Stage 3 did not drive the submit handler over HTTP.** Batches 4 and 5 signed
  an administrator in over real HTTP; a guest submit needs no credentials but
  **stores a pending comment and a real queue row**, and the drain would deliver
  that row to the administrator's address within five minutes. No admin
  credentials were available to this session to remove either through the app, and
  a hand-written `DELETE` against live rows is not something a batch decides on its
  own. What that leaves unmeasured is the handler's own wiring — the transaction it
  opens, the `new` gate and the commit — which is asserted against its source
  instead; every call it makes is measured against live rows through the probe. The
  render figure in the Progress row is a plain `GET` and stores nothing.
- **Atomicity moved the window in which a comment can be lost.** The commit is now
  the last thing the handler does before rendering, so a fatal error between the
  stored row and the commit — inside `addAdminMail()`, inside the `link` fragment —
  loses the comment where it used to be committed already. That is what "one unit
  of work" costs, and it is the trade the stage asks for; the alternative is a
  notification for a comment nobody has. Nothing in the enlarged window can fail
  loudly: `getSqlQuery()` catches its own exceptions, and the fragment is one line
  of template.
- **Three of the ten guard cases pass against the pre-batch tree by design.** The
  submit-latency case, the refused-queue case and the "the notification path only
  queues" case all guard what stage 2 of `docs/MAIL-2026.md` delivered — the
  synchronous `mail()` had already left the comment path before this batch started.
  They guard forward rather than backing this batch, exactly as three of the six
  stage 1 guard cases did.
- **`docs/TESTS.md` names two of the seven comment test files.** Its unit list
  carries `CommentReadTest` and `CommentTrustBoundaryTest` and misses
  `CommentIsolationTest`, `CommentStateTest`, `CommentTargetTest`,
  `CommentWriteTest` and now `CommentNotifyTest`. It is left alone: repairing it
  means adding five files owed by three different stages, and this batch owns one
  of them.

- **The stand these two stages ran on had to be repaired before either could
  start, and the repair is not theirs.** The database had been rolled back to a
  dump predating stage 1 — `_comment` carried none of the five stage 2 columns,
  `_mail` and `_maildead` did not exist, and `_users` and `_admins` were still on
  the 6.2 shapes. `phpunit` answered 45 failures and 2 errors and
  `comment-baseline verify` reported CHANGED on all eight modules with a 244-byte
  "no comments" region, so nothing later could have been attributed. It was
  repaired with the shipped path — `setup/sql/table_update6_3.sql` followed by
  `tools/comment-migrate.php classify`, `convert` and `iphash` — after a dump of
  the whole database was taken. The stage baseline was then captured fresh, which
  is the once-per-stage operation `docs/EXECUTION-2026.md` prescribes.
- **The upgrade and a fresh install agree, and the live stand's column order does
  not.** A scratch database seeded with the pre-6.3 `_comment` definition and run
  through `table_update6_3.sql` produces a table definition **identical** to the
  one `setup/sql/table.sql` creates, `pid`, `path` and both new indexes included.
  The live table differs from it in one position — `format` sits after `iphash`
  instead of before `edited` — because the repair applied that one `addcol` on a
  second pass; nothing about a type, a default or an index differs, and column
  order carries no meaning here.
- **The markup baseline was re-captured a second time, and not because of code.**
  A signed-in visit to the site while this batch was running moved the points of
  account 7885 from 50503 to 50513, and the comment author card renders that
  number, so `verify` reported CHANGED for `media`, `news`, `pages` and `shop`
  **at identical byte length** — the drift batches 5 and 6 already recorded. The
  code had not changed since the capture, so re-capturing hides nothing; it is
  stable across two consecutive `verify` runs afterwards. Worth knowing before
  reading any future CHANGED as a regression: check the byte length first.
- **Three defects were found in `table_update6_3.sql` while repairing the stand,
  and all three were fixed rather than only reported.** They are not comment
  defects, but they are what an upgrade of this release does to every
  installation, and each one silently discards work rather than failing loudly:
  - **`_users` was not idempotent.** `UPDATE ... SET network = ''` and the
    `MODIFY network` that closed the `_users` `ALTER` both refuse once the OAuth
    block at the end of the same file has dropped that column, and because an
    `ALTER` is all-or-nothing the failure took the normalisation of thirteen
    other columns — `id`, `password`, `ip`, `regdate`, `lastvis`, `points` and
    the rest — with it. Both statements are gone; the column has an owner at the
    end of the file and needed none in the middle;
  - **`_admins.editor` disagreed with the fresh schema.** The upgrade declared
    `BOOLEAN DEFAULT NULL` while `table.sql` defines
    `VARCHAR(32) NOT NULL DEFAULT 'plain'`. The column names the editor plugin an
    administrator writes with — `getEditorKey()` (`core/system.php:3937`) reads it
    through `Editor::isValidEditor()` — so on any installation whose
    administrators carry a name the statement failed outright and took the other
    thirteen `_admins` columns with it, and had it ever succeeded it would have
    **destroyed those names**. It now declares the type `table.sql` declares, with
    a normalisation of the legacy `NULL`, `''`, `'0'` and `'1'` in front of it,
    because a `NULL` would refuse the `NOT NULL` under strict mode;
  - **`_users.points` was declared twice, and the second declaration undid the
    first.** Line 1053 set it `NOT NULL DEFAULT 0` and line 1538 set it back to
    nullable, so the last writer won and the upgraded column disagreed with what
    the same file had just asked for. The duplicate is gone, and `table.sql` —
    which was the nullable one — now matches at `NOT NULL DEFAULT 0`.
  Rehearsed rather than argued: a scratch database seeded with the pre-6.3
  `_users`, `_admins`, `_users_temp` and `_comment` definitions from the dump,
  carrying an administrator whose editor is `toastui` and a user whose points are
  `NULL`, was run through the whole file **twice**. Both passes reported **zero
  failures** against those tables, `toastui` survived, `points` ended `NOT NULL`,
  `network` was dropped, and all four tables ended **identical to what
  `setup/sql/table.sql` creates**.
- **`SchemaUpdateValidationTest` grew the two cases that would have caught all
  three.** It compared table *names* only. It now also compares every column
  definition the upgrade declares — through `MODIFY` and through `addcol` alike —
  against `table.sql`, treating `BOOLEAN` and `TINYINT(1)` as the one type the
  server stores either way, and it fails when one column is declared twice with
  two different definitions. Against the unfixed file the two cases name all
  three defects with their line numbers; 261 of the 264 declarations already
  agreed, so the drift was three columns rather than a pattern.
- **One preflight failure was a defect in a test rather than in the code.**
  `CommentNotifyTest` expected one queue row per administrator with `smail = '1'`
  while `addAdminMail()` also scopes the audience by language when the site is
  multilingual (`core/system.php:3719-3722`). The verified stand had three
  administrators and never showed the difference; the restored dump has 86, and
  22 subscribed against 13 written. The probe's expectation now applies the same
  language scope. Nothing in the mail path changed.
- ~~**The `page` row of the transport table was not taken.**~~ — **delivered
  after the rest of stage 4**, as an addition to the numbered pager rather than a
  replacement for it, because the numbered page is what a link, a crawler and a
  reader without HTMX can all name. The control is an ordinary link to
  `&com=N+1#comm` that HTMX upgrades into an append; it stands last inside the row
  container and replaces **itself** with the answer, so nothing has to be kept in
  step with it and the last page simply renders no control at all.
- **The moderation actions do not degrade without JavaScript.** They are `POST`
  routes reached through `hx-post`, and the `href` the speed dial also renders is
  a GET the router now refuses, so a click without HTMX answers a refusal rather
  than acting. Only the submit form has a plain path, which is what the stage
  asks for; moderating without JavaScript was never supported and is not made
  worse, but it is now an explicit refusal instead of a working GET.
- **The debug panel is appended to every ajax answer on this stand, so a "delete
  answers nothing" check reads a body that is not empty.** It is
  `$conf['variables']` output, it is added to all five frontend ajax routes alike
  and it predates this work; the header contract and the persistent state were
  measured instead, and the fragment responses were matched from their first
  byte.
- **A replayed submit writes one entry to `error_sql.log` by design.** Stage 2
  detects a duplicate `reqkey` from the failed insert rather than from a prior
  lookup, and `Pdo::getSqlQuery()` logs every statement that fails. The single
  entry this batch added to that log is exactly that, produced by the replay
  probe.
- **Two more tokens still ride in a URL, and neither belongs to comments.** The
  private-message routes (`core/user.php:536`-`682`) and the favourites routes
  (`:955`, `:1060`) build `&token=` into their hrefs. They were left untouched:
  the stage owns the comment render path, and `getTplAjaxTextarea()` was taken
  with it only because the comment editor is one of its two callers.
- **`_PCOPEN` was removed from all six locales.** The status action answers the
  re-rendered comment now instead of an alert, which left that constant with no
  reader at all; `_PCLOSED` stays, because the comment fragment still uses it as
  the title of a hidden comment.
- **`docs/TESTS.md` was repaired rather than left drifting again.** Stage 3
  recorded that its unit list named two of seven comment files; it now names all
  nine, and the four mail files that were missing with them.

### Open blockers

- ~~Stage 1 cannot be called complete until the frontend page-cache helper is
  identified~~ — **closed by batch 6.** It is `index.php:130`: after any of
  `addComment`, `updateComment`, `updateCommentStatus`, `updatePost` and
  `updateVotingResult` the request calls `Cache::addEpoch()`, and `getPageHash()`
  (`core/system.php:1811`) mixes the epoch into every cached page key, so one bump
  invalidates every pagination and sort variant at once. Admin mutations are
  covered separately by `core/classes/pdo.php:149`. Two gaps against the contract
  in **Page cache** remain and belong to stage 4, which owns this path: the bump
  is by route rather than by target, and it fires whether the operation succeeded
  or was refused.
- ~~The markup baseline covers six of the eight modules on this stand~~ —
  **closed 2026-07-28, before batch 1 touched any code.** Both fixtures were
  re-prepared per `docs/TESTS.md`, which now records the preparation as well as
  the revert, and `capture` recorded all **eight** modules: `faq`, `files`,
  `links`, `media`, `news`, `pages`, `shop`, `voting`.

## Facts (measured 2026-07-27)

- `ashowcom()` was **253 lines** (`core/system.php:5477-5729`) carrying SQL,
  permissions, pagination, module links and HTML assembly at once. Re-measured
  after batch 2 moved its reads: **186 lines**, and no SQL left in it. Batch 6
  deleted it: its frontend half is `getCommentList()`, **136 lines** at
  `core/user.php:10-145`, and the eight unreachable admin branches are gone.
  `core/system.php` went from 5944 to **5689** lines with the three globals.
- Eight modules render comments through `setComShow($id, $acomm)`: `faq`,
  `files`, `links`, `media`, `news`, `pages`, `shop`, `voting`.
- The admin panel did **not** call `ashowcom()` — `admin/modules/comments.php`
  builds its own table. Its only two callers were `setComShow()` and the
  `addComment()` handler, both in `core/user.php`, so the seven
  `defined('ADMIN_FILE')` branches inside it were unreachable; batch 6 deleted
  them with the function and both callers now call `getCommentList()`
  (`core/user.php:151` and `:418`).
- Adding a comment returns and replaces the whole list: one POST answers with
  51059 bytes. Edit and status actions already update a single comment region.
- ~~Adding a comment takes **26.7 s**, of which `addAdminMail()` is 26.6 s and
  rendering is 0.02 s~~ — **closed by stage 3**, and by stage 2 of
  `docs/MAIL-2026.md` before it. Re-measured on 2026-07-28: the comment write and
  its notification together cost **5.8 ms** and the notification alone **0.8 ms**,
  against **77 ms** for one HTTP render of the target page. The recipient
  breakdown once quoted alongside the old figure ("13 recipients, ~2.05 s per
  call") never survived checking either: `_admins` holds 3 rows and exactly one
  has `smail = '1'`, so 26.6 s was one blocking `mail()` call against an
  unconfigured transport — a property of this development host. There is no
  `mail()` in the request path any more; `addAdminMail()` ends in
  `Mail::addQueue()` and the drain owns delivery.
- `EXPLAIN` on the live list query: `type=ref key=cid rows=20`,
  `Extra=Using where; Using filesort` — no composite index backs the sort.
- ~~The flood check runs `WHERE ip = ?` with **no index on `ip`**~~ — **closed by
  stage 2**: `getLastTime()` matches `iphash` and `KEY iphash_time`
  (`iphash`, `time`, `id`) backs it. The stage also dropped `KEY cid` and
  `KEY modul_status` for the composites that supersede them and kept `KEY time`.
- Table `_comment` (re-checked 2026-07-28, after batch 4): **7353 rows**. `body`
  is `TEXT`, the IP is stored in clear text. Distribution: files 4821,
  voting 1084, news 1083, faq 141, pages 116, links 104, media 2, shop 2.
  Status: 7350 published, 3 pending. The two `media` rows are the
  markup-baseline fixture, re-created before the stage 1 `capture`. The earlier
  figure in this list — 7357 rows, news 1088, voting 1083, 4 pending — was taken
  before the batches that drive HTTP write scenarios against live rows; the body
  migration of stage 2 quotes it and must be re-measured against the table it
  actually runs on rather than against either number here.
- Confirmed InnoDB on this installation: `_comment`, `_users`, `_news`, `_files`,
  `_voting`, `_newsletter`.
- Index list on `_comment` after stage 2: `PRIMARY(id)`, `UNIQUE reqkey`, `uid`,
  `time`, `modul_cid_status_deleted`, `modul_cid_deleted`, `status_deleted_time`,
  `iphash_time`. Before it: `PRIMARY(id)`, `cid`, `uid`,
  `modul_status(modul, status)`, `time` — and nothing on `ip`.
- ~~Validation is duplicated in `addComment()` and `updateComment()`, and the word
  length defect fixed in the first copy still lives in the second~~ — **closed by
  stage 2**: `checkRules()` is one ordered rule set for both paths, the add-only
  rules are gated on the path, and the length rule measures the **longest** word
  in **characters**. The add path shows the last rule that fired and the edit path
  the whole list, which is what each of them did before.
- Replying inserts `[b]name[/b],` into the editor: there is no thread structure.
- `acomm` is a three-state mode per target row: `0` = disabled (`_DEACTIVATE`),
  `1` = moderated (`_APOSTMOD`), `2` = open (`_APOSTNOMOD`). The final status of
  a submission also depends on the global anonymous-posting mode, moderator
  permissions and user access restrictions.
- ~~The storage format of `body` is decided at write time by `getEditorMode()`
  and `_comment` carries no per-row record of it~~ — **closed by stage 2**: every
  row carries `format`, the write path resolves it through `getBodyFormat()`
  (`plain` or `markdown`, never `html`), and all four render sites pass it to the
  parser. `filterHtml()` itself is untouched and keeps its other callers.
- Direct `_comment` consumers outside the render path. **Two entries in this list
  were found by reading, not by grepping, and both assemble the table name from a
  variable** — `PREFIX_DB.'_'.$table` with `'comment'` arriving as data. A sweep
  for the literal `_comment` misses them, which is why the list was wrong twice.
  The stage guard batch 6 owns has to look for the assembled form as well:
  `getProfileModules()` and `getAdminCountRow()` are the two shapes seen so far.
  Everything below is closed except the sidebar chip:
  - ~~frontend write handlers — `core/user.php` (flood check, insert, last id),
    `core/system.php` (`updateComment()`, `updateCommentStatus()`)~~
  - ~~admin module — `admin/modules/comments.php` (module list, search list, edit
    save, bulk actions, approve, delete, pager table binding)~~
  - ~~user activity feed — `core/user.php:732` UNION branch~~
  - ~~profile hub counter — `modules/account/index.php:336` UNION branch, which
    reached the table through the `getProfileModules()` map rather than by name
    and was therefore missing from this list until batch 5 measured it~~
  - ~~module deletion handlers — `faq`, `files`, `links`, `media`, `news`,
    `pages`, `shop`, `voting` admin modules~~
  - ~~admin sidebar waiting chip, `core/admin.php:319`: it called
    `getAdminCountRow(..., 'comment', "status = '0'")`, and that helper built
    `SELECT COUNT(id) FROM {prefix}_comment WHERE status = '0'` from the name it
    was handed~~ — **closed by batch 6**: the chip reads
    `getStatusCount(CommentStatus::Pending)` and hands the number over, the helper
    (`core/admin.php:274`) takes an optional `$num` and skips its own query when it
    is given, and the other fifteen rows across nine modules are unchanged
  - installer schema — `setup/sql/table.sql`, `setup/sql/table_update*.sql`
  - one dead consumer — `modules/account/admin/index.php:921` holds a
    **commented-out** `DELETE FROM _comment WHERE uid = :id`, so deleting a user
    currently orphans their comments. It is the last `_comment` text outside the
    class, `tools/` and `tests/`, and `deleteUser()` is what replaces it. Batch 6
    left it: making it live is a behaviour change, and the behaviour has not been
    decided — see the **Verification / Write** list. The stage guard strips
    whole-line comments, so it does not hide behind the comment marker either
  Only the installer schema and that dead line are left, and neither runs a
  statement against the table.
- ~~`modules/shop/admin/index.php:707` interpolates its id list straight into
  `IN (...)` with no parameters, unlike the `news` and `pages` handlers~~ —
  **closed by batch 5**: `productops()` builds `$keys`/`$pars` like those two
  handlers and all six of its `IN (...)` clauses bind.

## Problems this causes

1. **The moderation mode came from the client — closed by stage 0 on
   2026-07-28.** `addComment()` read `cid` from the request and decided the
   published/pending status from it, so submitting a different value published
   past premoderation; `mod` was request-supplied too and fed both
   `is_moder($mod)` and the stored `modul` column, so a comment could be attached
   to an arbitrary module. The same held in `updateCommentStatus()`, where the
   request `mod` drove the permission check **and** was passed to `numcom()`,
   moving the counter of the wrong target row. Today the mode comes from
   `Comment::getTargetMode()` (`core/classes/comment.php:126` since batch 6,
   `getCommentMode()` in `core/system.php` before it, called at
   `core/classes/comment.php:151`) and both update paths read
   `modul` from the comment row (`core/classes/comment.php:177`, `:196`, `:209`, and
   before batch 3 in the two handlers themselves). The counter that used to take
   the request `mod` is `updateTargetCount()` (`:240`), private to the class since
   batch 6 and reachable through no other path. The description stays here
   because the rest
   of the plan is written against it.
2. **Adding repaints everything.** A successful add returns the complete comment
   list, transfers about 51 KB and replaces the whole list container; scroll
   position and focus are lost.
3. **No feedback on premoderation.** The new comment is invisible in the
   returned list, so the user assumes failure and submits again — and a
   duplicate is created, because nothing is idempotent.
4. **Unreadable core.** A 253-line function with 18-slot `list()` unpacking and
   an `O(n*m)` nested loop matching users to comments.
5. **Divergent copies.** Two validation paths mean a fix lands in one of them.
6. **No threads.** Discussions are flat; replies are a naming convention.
7. **Blocking mail.** The request waits for SMTP once per recipient.
8. **Storage.** Indexes do not match the real access pattern, the flood query
   has none at all, and there is no soft delete and no edit timestamp.

## Target design

### Schema standards these columns follow

Derived from all 36 tables in `setup/sql/table.sql`, not assumed:

- column names are lowercase, one word, **no underscore** — there is not a
  single underscored column in the whole schema;
- a state column is called `status`; a creation stamp is called `time`;
- random identifiers are stored as hex `CHAR(n)` — `token CHAR(64)`,
  `nonce CHAR(64)`; the schema contains **no** `BINARY` or `VARBINARY` column;
- parent references are `pid` (`_forum`, `_categories`), and the project prefers
  `NOT NULL DEFAULT 0` over nullable integer keys;
- every index is named, and a composite index is named by its columns joined
  with underscores (`modul_status`, `word_modul`, `mid_modul_ip`,
  `modul_lang_status`);
- `format VARCHAR(...) NOT NULL DEFAULT ''` already exists in `_media`
  (`setup/sql/table.sql:359`).

### Data

The table keeps its name and every existing column. Nothing is renamed, nothing
is rewritten:

```
{prefix}_comment
  id, cid, modul, time, uid, name, ip, body, status     -- unchanged
```

Added in stage 2:

```
  `format` VARCHAR(20) NOT NULL DEFAULT '',
  `edited` DATETIME DEFAULT NULL,
  `deleted` DATETIME DEFAULT NULL,
  `reqkey` CHAR(32) NOT NULL DEFAULT '',
  `iphash` CHAR(64) NOT NULL DEFAULT '',
```

Added in stage 5:

```
  `pid` INT UNSIGNED NOT NULL DEFAULT 0,
  `path` VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
```

That is **five** columns in stage 2, not four — an earlier revision of this
document miscounted, and the migration must add all five.

`reqkey` is `NOT NULL`. The legacy rows are not an argument for nullability: the
same migration backfills every existing row with a random 32-character key. A
nullable idempotency key is a key that can be absent, and an absent key is a
replay that nobody detects.

The DDL order matters, because the unique index cannot be created while 7355 rows
share the default:

1. add the column as `CHAR(32) NOT NULL DEFAULT ''`, without the index;
2. backfill a distinct random key into **every** row, in batches;
3. assert no row is left empty and no value repeats — this is what makes step 4
   able to succeed rather than fail halfway through an `ALTER`;
4. only then add `UNIQUE KEY reqkey`.

Indexes added in stage 2:

```
  KEY `modul_cid_status_deleted` (`modul`, `cid`, `status`, `deleted`, `time`, `id`),
  KEY `modul_cid_deleted` (`modul`, `cid`, `deleted`, `time`, `id`),
  KEY `status_deleted_time` (`status`, `deleted`, `time`, `id`),
  KEY `iphash_time` (`iphash`, `time`, `id`),
  UNIQUE KEY `reqkey` (`reqkey`)
```

`status_deleted_time` exists because the admin list is **not** scoped by module.
`getAdminScope()` (`core/classes/comment.php:258`, and
`admin/modules/comments.php:95-128` before batch 4 moved it) builds
`WHERE s.status ... ORDER BY s.time`
with `modul` only when a filter is selected, so every `modul`-leading index is
useless to it and the query would fall back to a scan plus filesort. This was
missing from an earlier revision.

Dropped in the same migration: `KEY cid` and `KEY modul_status`, covered by the
composites above. **`KEY time` stays** — the unfiltered admin list orders by
`time` and other consumers reach the table by time alone. `KEY uid` stays for the
activity feed.

Stage 5 adds:

```
  KEY `modul_cid_pid_time` (`modul`, `cid`, `pid`, `time`, `id`),
  KEY `modul_cid_path` (`modul`, `cid`, `path`)
```

Root pagination filters `modul`, `cid`, `pid = 0` and sorts by `time, id`; a bare
`KEY pid` cannot serve that and is not created. `modul_cid_path` serves branch
loading and the tree order.

`ip` stays for admin search and GeoIP; `iphash` serves flood control only.
Dropping the plain address is a separate task, after the moderation policy is
settled.

Query rules: public reads use `status = 1` instead of `status != 0`, and from
stage 2 every listing sorts by `time, id` so equal timestamps keep a stable
order. Stage 1 keeps the current sort untouched.

### Storage format

`body` keeps the current normalized stored content and the parser renders on
read, as today. No persisted HTML.

"Normalized" is deliberate: `filterHtml()` (`core/security.php:973`) already
rewrites the submitted text before it is stored — it runs `filterClickable()`,
`stripslashes()`, escapes `$`, `\`, quotes, and applies `nl2br()` in plain mode.
The stored value is therefore not the raw submission today either.

The problem this creates: which of those transformations ran depends on
`getEditorMode()` **at write time**, and that is a site-wide setting. Switch the
site from Markdown to plain and every previously stored comment renders
differently, with no way to tell which rule it was written under.

### Trust is not format

`filterHtml()` conflates two independent things, and that is the actual defect
behind the format question:

- **format** is the syntax the author writes in — `plain`, `markdown`, `html` —
  and it follows from which editor the site runs;
- **trust** is whether an author may emit live HTML, and it follows from *who is
  writing*, never from which editor is configured.

Today the `html` branch (`core/security.php:981-983`) escapes only `"`, `$`, `'`
and `\` and leaves tags untouched, so choosing an HTML editor silently grants
every anonymous commenter full trust. `getEditorKey()` (`core/system.php:3838`)
resolves one site-wide `editor.user` for all frontend authoring, and `ckeditor`
and `tinymce` both declare `formats=html`, so this is one admin setting away at
any time. Comments render with `safe = false`
(`core/user.php:109`, `core/system.php:5503`, `admin/modules/comments.php:106`),
which means nothing downstream would catch it either.

**Escaping belongs to the read path, and comments render with `safe = true`.**
An earlier revision of this plan proposed the opposite — escape everything on
write and keep rendering at `safe = false`. That was wrong, and measuring it says
why. Escaping the body does not protect the parser's own constructs, because
square brackets survive `htmlspecialchars` untouched:

```
input:       [url=javascript:alert(1)]click[/url]
safe = false: <a href="javascript:alert(1)">click</a>
safe = true:  <a href="#">click</a>
```

Measured against `Parser::filterContent()` on 2026-07-27. This is not gated on
the editor mode: it reproduces on the `markdown` editor this installation runs
today, which makes it a **live stored-XSS vector in comments**, not a latent one.
`[usehtml]` behaves the same way at `safe = false` and is only stopped because
`filterText()` strips it for non-admins on write — one layer, in the wrong place.

Both `.rules/architecture.md:100` and `docs/PARSER.md:51` already require
`safe = true` for comments. The code does not comply, and the plan must not
document the non-compliance as acceptable.

| format | stored | rendered |
|---|---|---|
| `plain` | normalized source, not pre-escaped | parser, `safe = true`, no Markdown |
| `markdown` | normalized source, not pre-escaped | parser, `safe = true`, Markdown rendered |
| `html` | **not offered to untrusted authors** | — |

`html` is not an available comment format. Allowing it would require an
allowlist sanitiser, and writing one is exactly the kind of machinery this
project avoids; the alternative — trusting the author — is what the current
`filterHtml()` branch does and is the defect being removed.

**This has consequences the plan must carry, not hide:**

- storing the source instead of pre-escaped HTML means `filterHtml()`'s current
  behaviour is no longer what comments want. The comment write path gets its own
  normalisation inside `Comment`, and the shared `filterHtml()` is **not** changed
  on behalf of comments — it has callers in forum, private messages and money
  that are out of scope here;
- the 7355 existing rows are pre-escaped, so rendering them at `safe = true`
  double-escapes: `&lt;b&gt;` becomes `&amp;lt;b&amp;gt;`, measured. They need a
  migration that decodes the stored escaping once;
- that migration changes rendered output, so **stage 1 byte parity cannot cover
  it**. Parity is asserted for the refactor itself; the format migration is a
  deliberate, separately verified change of output.

`format` therefore records the rendering syntax — Markdown or not — for a body
that is stored as source in both cases.

**The parser API does not take a format yet.**
`Parser::filterContent(string $src, bool $safe, string $mod, int $hoff = 0)`
(`core/classes/parser.php:59`) has no parameter for it, so storing the column
without extending the call is a column nobody reads. Stage 2 settles the
contract:

- allowed comment formats are `plain` and `markdown`; `html` is not offered;
- `plain` renders without Markdown block or inline syntax, with newlines turned
  into breaks at render time rather than baked into storage;
- `markdown` renders Markdown, with the same `safe = true` restrictions;
- both keep the author's line endings in `body` unchanged, so a re-edit shows
  what was typed;
- editing does **not** rewrite `format`. A body written as Markdown stays
  Markdown even if the site editor has since changed, and the editor loaded for
  that edit is the one matching the row's format. Otherwise an edit silently
  reinterprets text the author never revised;
- how the format reaches the parser — an added argument or an explicit entry
  point per format — is an implementation choice, but the column must be an
  input to rendering, not decoration.

`Editor::getFormat()` (`core/classes/editor.php:131`) resolves a format from an
editor **manifest id**; the runtime write path is `getEditorMode()`. The column
stores what the latter returned.

### Identifier confusion to remove

The request parameter named `cid` is **not** the table column `cid`. Today the
comment form sends the target's `acomm` mode under that name
(`core/user.php:45`), while `_comment.cid` holds the target row id. This is the
single most confusing part of the current code, and — as problem 1 above records
— it is also how the moderation mode reaches the server from the client. It goes
away:

- the request loses `cid` entirely;
- `acomm` is loaded from the target row on the server, never accepted from the
  client;
- on edit, status and delete, `modul` and `cid` are read from the comment row by
  its id, not taken from the request;
- the target table is resolved through a fixed list of the eight supported
  modules — never by interpolating a module name from the request.

### The target resolver

Saying "the module comes from the server context" was not a contract, and there
is no such context to take it from: `index.php?go=1&op=addComment` is a shared
route (`index.php:108-118`) with no module of its own, and the POST still carries
`mod`. What follows is the actual rule.

`mod` is accepted from the request, but **only as a lookup key into a fixed
server-side map**, never as data. One resolver, used by every write:

1. `mod` must be a key of the eight-entry map; anything else is rejected outright.
2. The map yields the table name and the columns to read. No name is ever
   interpolated from the request.
3. The target row is loaded by `id` from that table.
4. The row must exist **and be visible**: its own `status`, its publication time
   where the module has one, and its category permissions through the same filter
   the module's own view uses. Existence alone is not authorisation — a comment
   must not be attachable to a draft, an expired item or a category the author
   cannot read.
5. `acomm` is read from that row, and a value of `Disabled` rejects the write.
6. Only then is the comment stored, with `modul` and `cid` taken from the
   resolved target rather than from the request.

The same resolver answers "does this target still exist" for `deleteTarget()` and
the counter updates, so there is exactly one place that maps a module name to a
table.

A signed target descriptor carrying module and id would remove the lookup, but it
adds a token format to maintain and buys nothing once the resolver validates
visibility — the check has to happen either way.

This is a security fix, not a naming cleanup, and stage 0 ships it before the
refactor rather than after it.

### Naming

The subsystem is called **comment**, singular, everywhere: table
`{prefix}_comment`, class `Comment` in `core/classes/comment.php`. That matches
the table it owns, every existing service class (`Cache`, `Parser`, `Editor`,
`Template`, `Logger`, `Geoip`, `Captcha`, `Oauth`) and the `Mail` class of
`docs/MAIL-2026.md`.

### Code

One class, `Comment` in `core/classes/comment.php`, built with its dependencies
and using the existing column names throughout:

```php
$com = new Comment($db, $prs);
$rows = $com->getList($modul, $id, $page);
$com->addComment($modul, $id, $body);
```

Author, uid, ip, `acomm` mode and permissions are resolved **inside** the class
from the server context — never taken from the request.

`Comment` owns SQL, validation, permissions and state changes. It returns data
and semantic flags. `setComShow()` renders the existing template fragments. The
point of the work is to remove the HTML monolith, not to move it into a class.

Public methods: `getList()`, `getAdminList()`, `getUserList()`, `getCount()`,
`addComment()`, `updateComment()`, `updateBody()`, `setStatus()`,
`deleteComment()`, `deleteTarget()`, `deleteUser()`, `checkRules()`. Stage 5 adds
`getBranch()`. `getComment()`, `getModuleList()`, `updateBody()`,
`getUserCount()`, `getStatusCount()` and `getTargetMode()` were added during
stage 1 rather than designed here: the
moderation module reads one comment for its edit form, asks for the module names
of its selector, and saves a body without the author's edit rules, the
profile hub counts an account's comments beside its other module counters, the
admin sidebar shows how many are waiting, and the resolver of stage 0 came home
in batch 6 — and the stage cannot claim "no direct `_comment` SQL outside
`Comment`" while any of them has nowhere to go. `deleteUser()` is the one method
of this list stage 1 did not deliver; see the **Deviations**.

The verbs are spelled out rather than left as `add()`, `update()` and `delete()`
because `.rules/global.md` requires 6-24 characters and a verb-plus-noun shape.
The names no longer collide with the global functions of the same name, since
those are deleted in the same stage that introduces them.

Two enums:

```
CommentStatus: Pending = 0, Published = 1
CommentMode:   Disabled = 0, Moderated = 1, Open = 2
```

`setComShow()` stays as the rendering function. `ashowcom()` was deleted in
batch 6 once its callers moved — no wrapper, no transitional alias. Its frontend
half is `getCommentList()` (`core/user.php:10`), directly above `setComShow()`,
so the whole comment render-and-request surface reads top to bottom in one file.

Every direct SQL statement against `_comment` moves into `Comment`: frontend
list/add/edit/status, the admin module, the user activity feed, the deletions
performed when a target row is removed, and bulk moderation.

### Counters and points

`numcom()` was not a comment query — it writes the `comments` column of the
target table and calls `updatePoints()`. Batch 6 made it
`updateTargetCount()` (`core/classes/comment.php:240`), **private** to `Comment`
and invoked from `addComment()`, `setStatus()` and `deleteComment()` — inside
their transactions once stage 2 opens them — and the global function is gone.

The eleven-branch chain is now a map lookup. `MODULES`
(`core/classes/comment.php:28`) holds the eight modules with the target table and
the points slot of each, and `getTargetMode()` indexes the same map, so the
counter map and the supported-module list are one list rather than two that have
to be kept in step.

Three of the eleven branches were dropped in the move, covering four module
names: `account`/`members` (points slot 3), `gallery` (slot 17) and `multimedia`
(slot 29). Each had its table `UPDATE` already
commented out and did nothing but award points, and none of them was reachable:

- `numcom()` was called from the `Comment` class alone since batch 4 —
  `core/classes/comment.php:169`, `:203` and `:214` today — and nowhere else in
  the project;
- `modules/` contains no `gallery`, `multimedia` or `members`, and `account`
  does not render comments, so `_comment.modul` can only hold one of the eight
  supported modules;
- the only way to reach them today is the request-supplied `mod` described in
  problem 1 — sending `mod=gallery` awards points for a comment stored against
  another module. Stage 0 closes that, which is what makes the branches
  provably dead rather than merely unused;
- measured against the live table on 2026-07-28, after the move: **0 rows** carry
  a `modul` outside the eight-entry map, so no stored comment can reach a dropped
  branch even if one were still there.

Points slots 3, 17 and 29 are used nowhere else in the project, but the three
CSV positions in `users.points` (`config/users.php`) **stay untouched**. That
list is positional — `updatePoints()` indexes it as `$id - 1` — so removing
entries would silently renumber every slot above them and change the point value
of 42 unrelated events.

The eight surviving branches (`faq`, `files`, `links`, `media`, `news`, `pages`,
`shop`, `voting`) all perform a real `UPDATE` and keep their slots unchanged.
They are exactly the eight modules that render comments, so the counter map and
the supported-module list become one list instead of two.

### Mail dependency

Comment notifications are not solved here. Outgoing mail is a system-wide
concern — 26 call sites, a private newsletter queue and a synchronous
`mail()` — and it has its own plan, `docs/MAIL-2026.md`. This redesign consumes
it: once the queue exists, the comment path stores a job instead of calling
`addAdminMail()`, and the job is written inside the comment transaction.

**Delivered by stage 3.** The consumption turned out to be the other way round
from the sketch above: `addAdminMail()` **is** the queue call now — stage 2 of
that plan turned `addQueue()` into a store and every audience expander ends in
it — so what stage 3 had left to do was make the two writes share one
transaction, which the submit handler opens.

Ordering: **stage 2 of `docs/MAIL-2026.md` ships before stage 3 of this plan.**
That plan now has a transport stage in front of the queue — stage 1 replaces the
bare `mail()` with a configurable PHP-mail/Sendmail/SMTP transport, stage 2 adds
queueing and draining together. Stage 2 has to deliver both halves at once,
because a release that queues without draining would stop all outgoing mail, and
it is what removes the 26.6 s from the submit path. Both stages are useful on
their own, independent of comments.

Stage 1 of that plan shortens the submit path without removing it — a configured
transport does not block the way an unconfigured `mail()` does — so it is worth
measuring the comment submit latency again after it lands, before assuming the
26.6 s figure still holds. That figure came from **one** recipient, not thirteen;
see the facts above.

### Soft delete

- `deleteComment()` sets `deleted` once; a second call changes nothing;
- regular list, count and pager queries filter on `deleted IS NULL`, in the
  admin module as well as the frontend, and the count query must carry the same
  predicate as the result query;
- a repeated delete never touches counters or points again;
- deleting a pending comment does not decrement the public counter;
- `deleteTarget()` removes the comments of a deleted target row physically —
  there is nothing left to reference them;
- `deleteUser()` **keeps the comments and anonymises them**: `uid` goes to `0`
  and `name` is replaced with the deleted-user label the avatar helper already
  recognises (`getUserAvatarUrl(..., $deleted)`). Discussions stay readable,
  replies keep their parents, target counters do not move and points are not
  recalculated for an account that no longer exists. Deleting the rows instead
  would silently shrink counters and, from stage 5, break every branch below
  them. This replaces the dead commented-out statement at
  `modules/account/admin/index.php:921`, which currently leaves the comments
  pointing at a `uid` that no longer resolves;
- from stage 5, a deleted comment that still has replies stays as a tombstone
  so the branch does not break.

### Transactions

Each operation wraps **only its own writes**, using the existing API:
`$db->setSqlBegin()`, `$db->setSqlCommit()`, `$db->setSqlRollback()`.

| Operation | Inside the transaction |
|---|---|
| addComment | comment row, target counter, user points, `reqkey`, mail job (from stage 3, only when notifications are enabled) |
| updateComment | comment row, `edited` |
| setStatus | comment row, target counter, user points |
| deleteComment | comment row `deleted`, target counter, user points |

**Errors do not roll anything back on their own.** An earlier revision claimed
"any SQL error rolls the whole operation back", which is false against this API:
`Pdo::getSqlQuery()` catches `PDOException` and returns `false`
(`core/classes/pdo.php:126-137`), so nothing propagates and a following
`setSqlCommit()` would happily commit a half-written operation. Every operation
therefore checks explicitly:

- `setSqlBegin()` is checked before any write is attempted;
- every `INSERT`/`UPDATE`/`DELETE` result is checked;
- the first `false` triggers `setSqlRollback()` and aborts the operation;
- `setSqlCommit()` is checked, and a failed commit triggers a rollback if a
  transaction is still open;
- `getSqlLastId()` is read only after an `INSERT` that returned success.

The new row id comes from `$db->getSqlLastId()`. The current re-`SELECT` of the
last inserted comment (`getLastId()`, `core/classes/comment.php:321` since batch
3) is removed: it is redundant, racy,
and matches on `cid` plus `uid`, so for an anonymous author (`uid = 0`) it can
return a different guest's comment and build the wrong anchor link.

### Concurrency, not just transactions

A transaction alone does not make `setStatus()` and `deleteComment()`
idempotent. Two parallel requests can both read a published comment, both write
the new state and both move the target counter and the author's points — the
reads do not conflict, so nothing serialises them.

State changes are therefore expressed as **conditional updates**, and the
counters follow the affected-row count rather than a prior `SELECT`:

```sql
UPDATE {prefix}_comment SET status = :next
 WHERE id = :id AND status = :current

UPDATE {prefix}_comment SET deleted = NOW()
 WHERE id = :id AND deleted IS NULL
```

If the statement affected no row, the transition already happened: the operation
returns the same result as the winner and touches neither counters nor points.
Where a value has to be read before it can be decided — the target id, the
author — the read is `SELECT ... FOR UPDATE` inside the same transaction.

`setStatus()` and `deleteComment()` today compare the status they read before
deciding whether to write and to count (`core/classes/comment.php:195-207` and
`:177-182`, the guard batch 4 gave them). That closes the *repeated click*, which
is what the moderation module always did, but not the *concurrent* one: the read
and the write are still two statements, so two parallel requests can both pass
the comparison. Turning the comparison into the `WHERE` clause above is what
stage 2 still owes.

All participating tables are InnoDB — verified on this installation for
`_comment`, `_users` and every target table — so the transaction is real. The
migration must keep it that way, including `_mail`.

### Idempotency

- the key is a 32-character lowercase hex string, generated **in the browser at
  submit time**, never rendered into the form markup by PHP;
- that is not a style preference. Pages are cached, which is why
  `getPageToken()` and `getPageCaptcha()` exist as signed dynamic regions
  (`core/system.php:1742-1753`). A key baked into cached HTML would be served
  identically to every visitor, and the unique index would reject every comment
  after the first one on that page. If the key ever has to come from the server,
  it comes as a dynamic region, not as static markup;
- the server rejects anything that does not match `^[0-9a-f]{32}$` before it
  reaches the database, so the key space cannot be flooded with chosen values;
- the database guarantees uniqueness through `UNIQUE KEY reqkey`;
- **the duplicate is detected from the failed insert, not from a prior lookup.**
  Checking first and inserting second is itself a race: two concurrent requests
  both find nothing and both proceed. The sequence is: begin, insert, and on a
  duplicate-key failure roll back, select the existing row by `reqkey` and return
  its result. `getSqlQuery()` returns `false` rather than throwing, so the
  handler distinguishes a duplicate key from any other failure through
  `getSqlError()` — a generic failure is an error, not a replay;
- the key is regenerated only after a successful response;
- `reqkey` participates in add only — edit, status and delete are naturally
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
- a pending comment is never inserted anywhere.

**Keeping the page size means removing a row, not wishing it away.** Inserting a
fragment into a slice that already holds the configured count produces
`count + 1`; an earlier revision required both and specified neither. The
contract is: the add response carries the new comment **and** an out-of-band
removal of the row that falls off the far end — `afterbegin` drops the last,
`beforeend` drops the first. Both travel in one HTMX response, so the slice is
never briefly wrong.

The same question applies to the other mutations, and the answer is deliberately
the cheaper one:

- **delete and status** leave the gap they create. Pulling a replacement in from
  the next page would need a second query and a second swap on every moderation
  click, and the slice is already stale the moment anyone else posts;
- the pager count is **not** recomputed in the fragment response. It is a number
  on a page that is already a snapshot, and a wrong-by-one pager is cheaper than
  a query per action;
- a comment moving between pending and published is a visibility change, not a
  move: for a moderator it stays in place with new state, for everyone else it
  was never in the slice.

The plain POST path re-renders the page and gets all of this for free, which is
the reference behaviour the HTMX path approximates.

Rules:

- add/edit/status/delete are POST only; GET is allowed solely to load the edit
  form;
- CSRF travels in a hidden field or a header, never in the URL;
- **every** token in the comment render path comes from `getPageToken()`. Today
  the form uses it (`core/user.php:45`) but the action links inside `ashowcom()`
  call `getSiteToken()` directly (`core/user.php:82`, `:86`, `:90`, `:100`), so under page cache they are baked in and served to the
  wrong visitor. Validation stays `checkSiteToken()`;
- the rating widget embedded in each comment is **already correct** and is the
  pattern to copy: `getRatingAsync()` (`core/helpers.php:723`) takes its token
  from `getPageToken()`, so a cacheable page stores a signed marker rather than
  one visitor's token. It emits that token once per comment, which is why the
  markup baseline has to normalise `X-CSRF-TOKEN` before two captures compare
  equal — that is session variance between two fetches, not a leak;
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

### Page cache

Adding, approving or deleting a comment changes the rendered target page, and the
answer is the same for every write path rather than being decided per stage:

- the cache entry belonging to the target page is invalidated by `Comment`
  itself, not by the caller, so the HTMX path and the plain POST path cannot
  drift apart;
- invalidation happens **after** a successful commit, never inside the
  transaction. Purging first would open a window where the cache is rebuilt from
  data that is then rolled back;
- a failed operation invalidates nothing;
- `add`, `setStatus`, `deleteComment` and `deleteTarget` all invalidate;
  `updateComment` does too, because the body is part of the rendered page;
- what is purged is the target page in every pagination and sort variant it can
  be reached under, since a new comment shifts the slice boundaries and the
  pager. Purging only the first page leaves stale slices behind;
- the write path already runs `Cache::addEpoch()` for admin mutations
  (`core/classes/pdo.php:149`) and `index.php:130` runs it for the five frontend
  ajax writes, comments among them — identified by stage 1, batch 6. Stage 4
  confirms it against both transports and moves the decision from the route into
  `Comment`, so a refused write stops invalidating.

## Implementation steps

How to hand a stage to an assistant — session scope, the per-batch verification
loop, and the prompt template — is in `docs/EXECUTION-2026.md`, shared with the
mail plan.

Order across both 2026 plans, since they interlock at one point:

| # | Stage | Blocked by |
|---|---|---|
| 1 | **this plan, stage 0 — trust boundary** | nothing |
| 2 | `docs/MAIL-2026.md` stage 1 — transport | nothing |
| 3 | **this plan, stage 1 — centralize** | nothing |
| 4 | `docs/MAIL-2026.md` stage 2 — queue and drain | its stage 1 |
| 5 | **this plan, stage 2 — schema** | stage 1 |
| 6 | **this plan, stage 3 — mail off request** | **`docs/MAIL-2026.md` stage 2** |
| 7 | `docs/MAIL-2026.md` stages 3 and 4 | its stage 2 |
| 8 | ~~**this plan, stages 4 and 5**~~ — **done** | stage 2 |

Only row 6 crosses between the plans. Everything else within a plan is strictly
sequential, and the two plans are otherwise independent.

### Stage 0 — close the trust boundary

A hotfix in the current procedural code, shipped before any refactor. It is
small, independently deployable, and does not wait on the class. It closes two
separate holes that happen to live in the same request path.

**Stored XSS in comments — already fixed, ahead of this plan.** Comments render
at `safe = false` (`core/user.php:109`, `core/system.php:5503`,
`admin/modules/comments.php:106`), and the parser refused only `data:` links
unconditionally, so `[url=javascript:alert(1)]` produced a working
`href="javascript:..."` for every author, anonymous included. Neither author nor
viewer needed any privilege: an anonymous comment lands pending and fires in the
**moderator's** browser during review (`getScope()`, `core/classes/comment.php:249-253`, shows the
moderator branch has no status filter), while an ordinary registered user
publishes immediately (`core/classes/comment.php:159` since batch 3).

`filterUrl()` (`core/classes/parser.php:228`) now refuses `data:`, `javascript:`
and `vbscript:` in **every** mode, comparing against a copy with whitespace and
control characters removed so `java&#9;script:` cannot slip through after entity
decoding. One line, no storage change, and it covers forum posts and every other
content path rather than comments alone. The rationale was already in the code:
`data:` was refused unconditionally because "a link must never carry an inline
payload", and the two script schemes are the same class.

That closes the exploitable hole. It does **not** make the rendering model
correct, and the rest stays in this plan:

- comments still render at `safe = false`, against `.rules/architecture.md:100`
  and `docs/PARSER.md:51`. Only the URL class is now safe; the mode is not;
- the `html` branch of `filterHtml()` (`core/security.php:981-983`) still stores
  raw tags whenever an HTML editor is selected;
- moving to `safe = true` needs the storage migration described above, and
  `safe = true` alone would double-escape every existing row. It ships with
  stage 2, together with `format`, or not at all — a half-applied version is
  worse than either end state.

**Client-chosen moderation and module.**

- `addComment()` loads `acomm` from the target row instead of reading `cid` from
  the request.
- `addComment()` accepts `mod` only as a key into the fixed eight-entry map and
  resolves the table from that map — see **The target resolver** above. The
  target row must exist **and be visible**; existence alone is not authorisation.
- `updateComment()` and `updateCommentStatus()` read `modul` and `cid` from the
  comment row by its id, and the permission check uses that value.
- `numcom()` is called with the module read from the row.
- Verify: a crafted `cid` can no longer publish past premoderation; a crafted
  `mod` can no longer attach a comment to another module or move another
  target's counter; a moderator of one module cannot change a comment in
  another.
- Verify existing comments still render byte for byte through
  `tools/comment-baseline.php verify`.

Both halves are **done**: the URL-scheme fix on 2026-07-27 and the request-side
trust boundary on 2026-07-28 — see the Progress table for what landed where.
Stage 0 is closed; the next chat starts at stage 1.

The four-editor check — `<script>` and `<img onerror=...>` neutralised under
`toastui`, `plain`, `ckeditor` and `tinymce` alike — belongs to **stage 2**, not
here. Stage 0 does not touch `filterHtml()`, so the `html` branch still stores raw
tags and that test would fail by design; asserting it in stage 0 would be
asserting something the stage deliberately does not deliver.

### Stage 1 — centralize the current implementation

**Delivered 2026-07-28, all six batches.** The comment table has one owner, the
three globals are gone and `tests/Unit/CommentIsolationTest.php` guards it. One
item of this list was not delivered and is recorded in the **Deviations**:
`deleteUser()`, because the commented-out statement it replaces asks a question
about behaviour that nothing has answered.

**One release, six review units.** Around twenty call sites across thirteen files
move here, and a single diff of that size gets approved rather than read. Unlike
the mail stage these batches are also individually *safe*: each one leaves the
system working, because `ashowcom()` and `numcom()` stay until the last, and
`tools/comment-baseline.php verify` is run after every batch — a byte difference
appears at the batch that caused it instead of at the end of a twenty-site diff.

| # | Batch | Leaves working because |
|---|---|---|
| 1 | `Comment`, `CommentStatus`, `CommentMode`, read methods | nothing calls them yet |
| 2 | Frontend reads move to the class | `ashowcom()` still present and still correct |
| 3 | Frontend writes move — add, edit, status | the class now owns the write path; counters still via `numcom()` |
| 4 | Admin module moves — list, search, edit, bulk, approve, delete | admin parity checked item by item against the list in **Verification** |
| 5 | Activity feed and the eight target-delete handlers, including the `IN (...)` parameterisation in `modules/shop/admin/index.php:707` | independent of the render path |
| 6 | `numcom()` and `getCommentMode()` absorbed, `ashowcom()` deleted, the sidebar chip closed, the stage guard added | both are unreferenced by the time this lands |

The order matters: reads before writes, frontend before admin, and the deletions
last. Reversing any of it means deleting something still in use.

- No table or column changes.
- Add `Comment`, `CommentStatus`, `CommentMode`.
- Move every `_comment` read and write into the class, including `numcom()` as a
  private method, dropping its `account`/`members`, `gallery` and `multimedia`
  branches and leaving the `users.points` CSV untouched.
- Migrate frontend, admin module, user activity feed and module delete handlers.
- Parameterize the id list in `modules/shop/admin/index.php:707` as part of the
  move — an acceptance criterion of this stage, not a follow-up.
- Preserve current behaviour and HTML byte for byte, on top of the stage 0
  behaviour. **The current sort stays untouched in this stage** — `time, id`
  arrives in stage 2, so byte parity is achievable here.
- Parity is checked, not asserted: run `php tools/comment-baseline.php capture`
  **before** touching anything and `verify` after every step. All eight modules
  are covered; `docs/TESTS.md` records what had to be prepared on this
  installation to get `shop` and `media` rendering, and how to revert it.
- Delete `ashowcom()` once its callers are migrated.
- Verify no direct `_comment` SQL remains outside `Comment`, setup and migration
  files. **A sweep for the literal name is not enough** — two consumers assembled
  it from a variable and were missed twice by exactly that sweep, so the guard
  has to cover `PREFIX_DB.'_'.$var` reached with `'comment'` as data. `tools/`
  and `tests/` are excluded from it by name:
  `tools/comment-baseline.php:37` queries the table directly on purpose, because
  a parity tool that went through the class under test would prove nothing.
  Delivered as `tests/Unit/CommentIsolationTest.php`: it matches
  `PREFIX_DB.'_comment` rather than the word `_comment`, strips whole-line
  comments first, asserts the class still holds the statements so the sweep
  cannot pass on an empty project, pins the seven production files that build a
  table name from a variable, and checks that neither `getProfileModules()` nor
  `getAdminCountRow()` can be handed the comment table. `setup/` and `storage/`
  are excluded with `tools/` and `tests/` — the installer creates the table and
  `storage/cache/templates` is generated output.

### Stage 2 — validation, storage and write consistency

**Delivered 2026-07-28, one release.** Every item of the list below shipped. The
storage migration ran on this installation through `tools/comment-migrate.php`,
the markup baseline was re-captured afterwards because this stage changes
rendered output on purpose, and `tests/Unit/CommentStateTest.php` guards the
behaviour. What the stage did **not** do is recorded in the **Deviations**: the
admin routes were measured through the class rather than over HTTP, and
`deleteUser()` is still outstanding from stage 1.

- Merge add and edit validation into `checkRules()`.
- Fix the maximum word length check: measure the longest word, not the last one,
  and measure characters rather than bytes (`checkEditRules()`,
  `core/classes/comment.php:299`).
- Apply `CommentMode` to every remaining bare `acomm` comparison; the enum itself
  arrives in stage 1 with the class.
- Make add/edit/status/delete transactional, with the explicit result checks and
  conditional updates described above.
- Add `format`, `reqkey`, `edited`, `deleted`, `iphash` and the five new indexes;
  drop `cid` and `modul_status`, **keep `time`**.
- Backfill `reqkey` with a random key per row, and `iphash` from `ip`.
- Run the body migration below, then switch **all three** render sites to
  `safe = true` (`core/user.php:109`, `core/system.php:5503`,
  `admin/modules/comments.php:106`). They were four until batch 3 merged the two
  inside `updateComment()` into one, and the fourth sat in the unreachable
  `defined('ADMIN_FILE')` branch of `ashowcom()` that batch 6 deleted with the
  function, so three is the final count.
- Replace `filterHtml()` in the comment write path with the comment-specific
  normalisation that stores source; `filterHtml()` itself is not modified.
- Reject `html` as a comment format at the write boundary.
- Switch listings to `time, id`. Rows sharing a timestamp may change relative
  order — that is expected and is exactly what the stable sort fixes.

### The body migration, concretely

Rendering at `safe = true` requires the stored body to be source rather than
pre-escaped HTML, so all existing rows are converted once. There is no `format`
column to consult, but the writer left signatures, and they were re-measured
across all 7353 rows on 2026-07-28, when the migration ran:

**The signatures overlap, so classification is ordered, not parallel.** Measured
on this installation: 1769 rows carry a machine-written `<br>`, 100 carry an
unescaped tag, and 56 carry both. Counting each signature independently — as
an earlier revision of this table did — produces groups that sum past the row
count and a row that would receive two verdicts. Every row gets exactly one
`format`, decided by the first rule that matches:

| # | Rule | Verdict | Rows here |
|---|---|---|---|
| 1 | contains an unescaped tag other than `<br>`, **or carries `&#034;`** | `legacy` | 101 |
| 2 | contains `<br>`, `<br/>` or `<br />` immediately followed by `\n` or `\r\n` | `plain` | 1709 |
| 3 | contains any `<br>` form not followed by a line break | `plain`, listed for review | 1 |
| 4 | contains a line break | `markdown` | 4 |
| 5 | anything else — a single line | `markdown` | 5538 |

Rule 1 comes first because a row carrying real tags cannot be treated as plain
text no matter what else it contains; the 56 overlapping rows land there. The
counts above are what the ordered pass of `tools/comment-migrate.php` produced,
and they sum to 7353 exactly. `&#034;` was added to rule 1 during execution: only
the `html` branch of the writer could produce it, so a body carrying it is a
legacy row even without a tag — it moved exactly one row here and it is the
difference between that row reading `"quoted"` and reading `&#034;quoted&#034;`.

Rule 5 assigns `markdown` to single-line rows rather than leaving them unset.
**A format is required even when the body needs no conversion**, because `plain`
and `markdown` render the same single line differently — `*text*`, `# title`,
`` `code` `` and `[t](u)` all mean something in one and nothing in the other.
`markdown` is chosen because it is the mode this installation writes in today, so
existing rows keep their current appearance; an installation whose `editor.user`
is `plain` sets the default the other way, which is a one-line change in the
migration and must be a stated input to it, not a hardcoded assumption.

**The `<br>` signature has three spellings and two line endings.** Of the 1766
machine-written rows here, **1709 carry `<br />`** and 59 carry `<br>` — two
rows carry both, which is why the two figures sum past the total — and **1645
end the line with CRLF**. Today's `nl2br($out, false)` (`core/security.php:980`)
emits the bare form, but older SLAED releases called `nl2br()` with its XHTML
default, and both are in the table. The migration matches
`<br\s*/?>(\r?\n)` and restores the captured line ending unchanged.

**Entity decoding is a separate axis, not a class**, and the set is eight
patterns, not six. Measured occurrences: `&quot;` 267, `&#039;` 68, `&amp;` 64,
`&gt;` 44, `&#092;` 33, `&lt;` 30, `&#036;` 3, `&#034;` 1 — **401 rows** carry at
least one. The 348 quoted in an earlier revision omitted `&amp;` and `&#034;`,
and the 333 before that came from a four-pattern predicate that did not match the
six listed beside it.

`&amp;` matters most: `htmlspecialchars()` encodes the ampersand first, so
decoding the others without it turns an authored `&amp;lt;` into a live `&lt;`.
The reversal therefore runs **`&amp;` last**, exactly mirroring the order the
writer applied.

**The two writer branches did not produce the same entities**, so reversing them
is two different operations, not one with an extra pattern. From
`core/security.php:976-983`:

| Branch | What it wrote | Producing |
|---|---|---|
| `plain` / `markdown` | `filterText($text, 2)` → `htmlspecialchars(..., ENT_QUOTES)`, then `$` and `\` | `&amp; &lt; &gt; &quot; &#039; &#036; &#092;` |
| `html` | `str_replace` of `" $ ' \` only — no `htmlspecialchars` at all | `&#034; &#036; &#039; &#092;` |

So the migration runs two distinct steps:

1. **Reversing the writer.** For `plain` and `markdown`, undo the seven patterns
   above, `&amp;` last. For `legacy`, undo **only the four** the `html` branch
   could have written. A `&lt;` in a legacy row was never produced by the
   writer — the author typed it — and reversing it would turn their literal text
   into markup.
2. **Converting legacy markup to source**, which is a separate operation with its
   own semantics: the tag map, then escaping of whatever the map does not cover.
   Any entity still present at that point is the author's and is left alone.

Within a class an author-typed entity is indistinguishable from a writer-inserted
one, and the migration does not try to tell them apart; it reverses exactly what
that class's writer could have produced, once, and records the row count touched.

**Legacy HTML is converted, not preserved.** The 100 rows here contain only
`<b>` 82, `<tt>` 11, `<i>` 8, `<u>` 3, `<li>` 2, `<a>` 1 — counted as rows
carrying that tag, so a row using two of them appears twice — with zero
executable tags and zero `on*` attributes — but that is a fact about **this** database, and
the migration ships to installations whose contents nobody has seen. Keeping a
`format = html` that renders through the trusted path would contradict the two
rules this stage exists to establish: only `plain` and `markdown` are valid, and
every render site moves to `safe = true`.

So `legacy` is a classification, not a stored format. Rows matching rule 1 are
converted:

- a small fixed map turns the formatting tags that actually appear —
  `b`, `strong`, `i`, `em`, `u`, `tt`, `code`, `a`, `li` — into their Markdown or
  BB equivalents, preserving appearance for the overwhelmingly common case;
- **everything else is escaped**, so an unknown or hostile tag on some other
  installation becomes visible text rather than markup;
- the row is then stored with `format = markdown` like any other.

No sanitiser is written and no allowlist has to be maintained, because anything
outside the map is escaped rather than filtered. Rows touched by rule 1 are
listed in the migration report so an administrator can review what changed.

Order of operations, because it is destructive:

1. take a dump of `_comment` and rehearse the restore before any statement runs;
2. classify every row and **write the verdict into `format` first**, in its own
   pass, so the classification is reviewable before anything is rewritten;
3. **stop on a broken invariant, not on a number.** The counts above describe
   this installation and will differ everywhere else, so they are a sample, not a
   gate. The migration halts if any row ends with an empty `format`, if any row
   receives **zero or more than one verdict**, if the class counts do not sum to
   the row count, or if the row count changes between the start and the end of
   the run.

   Matching several *signatures* is normal and is not a fault: 55 rows here carry
   both a raw tag and a machine-written `<br>`, which is exactly why the rules are
   ordered. The invariant is about verdicts assigned, not predicates satisfied —
   an earlier revision said "matched more than one rule" and would have halted on
   those 55 rows every time;
4. convert bodies per class, in a transaction, in batches;
5. re-render a sample of each class and compare against the pre-migration output
   for meaning, not for bytes — byte parity is impossible here by construction,
   which is why `tools/comment-baseline.php` is re-captured after this stage
   rather than asserted through it;
6. verify that re-editing a row of each class round-trips: the editor shows the
   source, saving it changes nothing.

`iphash` migration, in this order:

1. compute it as a hex HMAC-SHA256 of the normalized IP, keyed by its own
   purpose secret `getSecret('commentip')` (`core/security.php:645`);
2. backfill all existing rows from `ip`;
3. only then switch the flood query from `ip` to `iphash`;
4. the query must never support both columns at once.

`ip` itself is kept for admin search and GeoIP; removing it is a separate task.

### Stage 3 — move comment mail off the request

Requires stage 2 of `docs/MAIL-2026.md` — the queue and its drain — to be
delivered first.

**Delivered 2026-07-28, one release.** Every item of the list below shipped and
`tests/Unit/CommentNotifyTest.php` guards it. What the stage did **not** do is
recorded in the **Deviations**: the submit handler was not driven over HTTP,
because a guest submit on this stand stores a comment and a queue row that the
drain would deliver, and nothing here could take either back.

- Replace the synchronous `addAdminMail()` call with a queue job.
- Write the job inside the same transaction as the comment.
- A delivery failure must never roll back a stored comment.
- Verify submit latency drops to the render cost.

### Stage 4 — fragment responses

**Delivered 2026-07-29, one release.** Every bullet below shipped, and the `page`
row of the transport table followed in the same release;
`tests/Unit/CommentTransportTest.php` guards all of it.

- Change transport and template targets only.
- Add returns one fragment or the pending confirmation.
- Edit, status and delete operate on a single comment.
- Move mutations to POST.
- Move CSRF out of the URL and route every token through `getPageToken()`.
- Fix the form reset so it happens only on success.
- Verify both the HTMX path and the plain POST fallback.

### Stage 5 — threads

**Delivered 2026-07-29, one release.** Every item of the list below shipped and
`tests/Unit/CommentThreadTest.php` guards it.

`path` is a sort key, so it is stored as ASCII with a binary collation — a
collated `VARCHAR` would order it by collation rules, and unpadded numbers would
order `10` before `9`. Each segment is the comment id zero-padded to ten digits:

```
root  0000000012
child 0000000012/0000000048
```

- Add `pid` and `path`; existing comments become roots with `pid = 0`.
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
- a row written under one `format` still renders correctly after the site
  editor mode is switched (from stage 2);
- admin parity, item by item: result query, count query, pager URL, selected
  module, selected status, search term, highlighting, bulk action form — the
  count query in `admin/modules/comments.php` is built separately from the
  result query, so `deleted IS NULL` must be added to both.

### Write

- published add;
- pending add;
- a crafted request cannot choose its own moderation mode or module (stage 0);
- repeated POST with the same `reqkey`;
- two concurrent POSTs;
- edit by the owner;
- edit after the edit window expired;
- edit by a moderator;
- repeated status transition;
- delete pending;
- delete published;
- delete a target row together with all its comments;
- delete a user and confirm the decided behaviour for their comments.

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
user points, `reqkey`, `format`, the mail queue and `deleted`/`edited`.

### Migration

- fresh install through `setup/sql/table.sql`;
- upgrade path as its own SQL file, separate from the fresh schema, using the
  existing procedure helpers (`addcol`, `addidx`, `rencol`) from
  `table_update6_3.sql`;
- the upgrade is idempotent and survives a partially applied prior state: it
  ships to 100 000 installations that cannot be inspected individually, so a DDL
  that fails halfway on one engine variant is not one bug but many;
- the stage 2 migration adds five columns and five indexes to `_comment`, drops
  two and backfills three of the new columns. That is instant against the 7355
  rows here and is not instant on
  an installation with a large comment table, so the upgrade notes must say so
  rather than letting an administrator discover it during a lock;
- both MySQL 8 and MariaDB;
- every new column and index present afterwards, `KEY time` still present, and
  the two superseded
  indexes gone;
- `format` and `iphash` backfilled in stage 2, `path` backfilled in stage 5;
- a dump taken before any DDL, and a restore rehearsed.

### Performance

`EXPLAIN` for the public list, the moderator list, the admin count query and the
flood query; response size; submit latency; mail queue drain time.

### Browser

- smoke run of all eight modules;
- load scenarios on `files`, `news` and `voting`;
- scroll and focus preserved after add and edit;
- pending confirmation visible;
- repeated double click;
- a second visitor submitting on the same cached page — two distinct `reqkey`
  values, both accepted;
- plain submit without HTMX;
- admin list and bulk actions.

### Tests

Added with the stage that introduces the behaviour, not at the end:

- stage 0 — a request cannot set its own moderation mode or module;
- stage 1 — each of the eight modules increments its own target counter and
  awards its own points slot, and the values match the pre-refactor ones;
- stage 2 — `checkRules()` rejects the longest word, not the last, and counts
  characters; a repeated `setStatus()` leaves counters and points unchanged;
  soft delete is idempotent;
- stage 2 — a row renders through its stored `format` after the site setting
  changes;
- stage 4 — two POSTs with one `reqkey` produce one row.

### Static, per stage

`php -l` on every touched file, `phpstan`, the full `phpunit` suite and
`php-cs-fixer --dry-run` — each stage, not once at the end.

After every state-changing run check `storage/logs/error_php.log`,
`storage/logs/error_sql.log` and `storage/logs/error_site.log`.

## Open decisions

Nothing here blocks a stage any longer. The two questions that did are recorded
below with their answers, so a session reading only this file sees what was
decided and why rather than reopening it.

### Storage format semantics — settled

Resolved; see **Trust is not format** and **The body migration, concretely** in
the target design. In short: comments store **source**, allow only `plain` and
`markdown`, and render with **`safe = true`** from stage 2, after a one-time
migration of every existing body.

An earlier revision of this section said the opposite — escaping owned by the
write path, `html` permitted, rendering left at `safe = false`. Measurement
disproved it: `[url=javascript:alert(1)]` survives write-time escaping untouched
and rendered as a live `href` at `safe = false`. That specific hole is closed in
`core/classes/parser.php:228`, but the trust model is only corrected in stage 2.

### Test stand versus distribution — settled

`config/modules.php` now has every module active, including `media`. This was a
deliberate decision, not a leftover from preparing the baseline.

Worth knowing about that file, because it is easy to mistake for a distribution
template: it is **runtime site state**, written by the admin panel in four places
(`admin/modules/modules.php:101`, `:313`, `:339`, `admin/modules/groups.php:297`)
and carrying 238 `'active' =>` changes in its git history. The installer merges
an existing file over its defaults (`setup/index.php:594-598`), so a change here
never disturbs a live installation — it only decides what a fresh checkout
starts with.

### Deferred by choice, not forgotten

- **Dropping `ip` from `_comment`.** Kept for admin search and GeoIP; removing it
  waits until the moderation policy is settled, and `iphash` already covers flood
  control.
- ~~**Which existing mechanism purges the frontend page cache.**~~ —
  **identified by batch 6.** It is `index.php:130`: the ajax router calls
  `Cache::addEpoch()` after `addComment`, `updateComment`,
  `updateCommentStatus`, `updatePost` and `updateVotingResult`, and
  `getPageHash()` (`core/system.php:1811`) mixes the epoch into every cached page
  key, so one bump invalidates every pagination and sort variant at once — the
  "purge every variant" half of the contract is already met. Admin mutations keep
  their own path (`core/classes/pdo.php:149`). ~~Two halves of the contract in
  **Page cache** are still open and belong to stage 4~~ — **closed by stage 4**:
  the five writes of `Comment` call `Cache::addEpoch()` themselves, after their own
  writes succeeded and never for a refusal, a no-op transition or a replayed
  `reqkey`, and `index.php:133` keeps only `updatePost` and `updateVotingResult`.
- **Migration cost on a large comment table.** Stage 2 adds five columns and five
  indexes, drops two and rewrites every body. Instant against the 7355 rows here;
  the upgrade notes
  must state what it costs on an installation with orders of magnitude more,
  rather than letting an administrator find out during a lock.

## Risks

- A direct `_comment` SQL statement missed during centralization.
- A surviving `numcom()` branch dropped by accident, or a `users.points` CSV
  position removed along with the three retired slots, renumbering every event
  above them.
- Comment counters drifting apart from `_comment`.
- Points changed twice on a retry.
- A mail job lost between the insert and the queue write.
- Soft delete interacting badly with target row deletion.
- A branch broken when its parent is removed.
- The public query and the admin count query disagreeing.
- A cached page serving a stale token or a shared idempotency key.

## Out of scope

- Renaming `{prefix}_comment` or its existing columns.
- SQL aliases and dual-schema compatibility.
- Persisted parser HTML.
- Full edit revision history.
- Comment likes and rating redesign.
- Forum posts.
- The mail subsystem itself — transports, configuration, the queue schema, the
  drain task and the migration of the other senders — all covered by
  `docs/MAIL-2026.md`.
