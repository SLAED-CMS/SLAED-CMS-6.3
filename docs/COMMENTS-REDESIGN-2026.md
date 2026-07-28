# Comments Subsystem Redesign

Status date: 2026-07-28. Approved; stage 0 delivered, stage 1 running. The comment
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
- **The rewritten list loop lost its snake_case names.** `$com_modul`,
  `$com_text`, `$com_status`, `$uname` and `$get_id` broke
  `.rules/global.md:102-103` and are now `$cmod`, `$val['body']`, `$stat`,
  `$val['name']` and `$gid`; `$backStatus` became `$tab`. Pure renames inside
  the lines the move rewrites, no output change.

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
  of the edit handler (`core/system.php:5685`) falls into the same gap: it needs
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

### Open blockers

- Stage 1 cannot be called complete until the frontend page-cache helper is
  identified — see **Open decisions**.
- ~~The markup baseline covers six of the eight modules on this stand~~ —
  **closed 2026-07-28, before batch 1 touched any code.** Both fixtures were
  re-prepared per `docs/TESTS.md`, which now records the preparation as well as
  the revert, and `capture` recorded all **eight** modules: `faq`, `files`,
  `links`, `media`, `news`, `pages`, `shop`, `voting`.

## Facts (measured 2026-07-27)

- `ashowcom()` was **253 lines** (`core/system.php:5477-5729`) carrying SQL,
  permissions, pagination, module links and HTML assembly at once. Re-measured
  after batch 2 moved its reads: **186 lines**, `core/system.php:5484-5669`, and
  no SQL left in it. The remainder is the HTML assembly and the module links.
- Eight modules render comments through `setComShow($id, $acomm)`: `faq`,
  `files`, `links`, `media`, `news`, `pages`, `shop`, `voting`.
- The admin panel does **not** call `ashowcom()` — `admin/modules/comments.php`
  builds its own table. Its only two callers are `core/user.php:13` and
  `core/user.php:280`, so the eight `defined('ADMIN_FILE')` branches inside
  `ashowcom()` are unreachable.
- Adding a comment returns and replaces the whole list: one POST answers with
  51059 bytes. Edit and status actions already update a single comment region.
- Adding a comment takes **26.7 s**, of which `addAdminMail()` is 26.6 s and
  rendering is 0.02 s. The recipient breakdown once quoted alongside that figure
  ("13 recipients, ~2.05 s per call") does not survive checking: `_admins` holds
  3 rows and exactly one has `smail = '1'`. One recipient and 26.6 s means one
  blocking `mail()` call against an unconfigured transport, which is a property
  of this development host and not of production. The split between mail and
  rendering is the part that matters here and it stands; the absolute number
  must be re-measured before it is used to justify anything — see
  `docs/MAIL-2026.md` and the workflow in `docs/PERFORMANCE.md`.
- `EXPLAIN` on the live list query: `type=ref key=cid rows=20`,
  `Extra=Using where; Using filesort` — no composite index backs the sort.
- The flood check runs `WHERE ip = ?` (`getLastTime()`,
  `core/classes/comment.php:254` since batch 3) with **no index on
  `ip`**; `_comment` carries only `cid`, `uid`, `modul_status` and `time`
  (`setup/sql/table.sql:141-144`).
- Table `_comment` (re-checked 2026-07-28, after the stage 1 baseline fixtures):
  **7357 rows**. `body` is `TEXT`, the IP is stored in clear text. Distribution:
  files 4821, news 1088, voting 1083, faq 141, pages 116, links 104, shop 2,
  media 2. Status: 7353 published, 4 pending. The two `media` rows are the
  markup-baseline fixture, re-created before the stage 1 `capture`; every other
  count is unchanged from the 2026-07-27 measurement.
- Confirmed InnoDB on this installation: `_comment`, `_users`, `_news`, `_files`,
  `_voting`, `_newsletter`.
- Confirmed index list on `_comment`: `PRIMARY(id)`, `cid`, `uid`,
  `modul_status(modul, status)`, `time` — and nothing on `ip`.
- Validation is duplicated in `addComment()` and `updateComment()`. The word
  length defect fixed in the first copy still lives in the second — both copies
  moved into the class in batch 3 and the defect with them
  (`checkEditRules()`, `core/classes/comment.php:238`): the loop keeps only the
  last word it saw, so only that one is measured, and it measures bytes with
  `strlen()` rather than characters.
- Replying inserts `[b]name[/b],` into the editor: there is no thread structure.
- `acomm` is a three-state mode per target row: `0` = disabled (`_DEACTIVATE`),
  `1` = moderated (`_APOSTMOD`), `2` = open (`_APOSTNOMOD`). The final status of
  a submission also depends on the global anonymous-posting mode, moderator
  permissions and user access restrictions.
- The storage format of `body` is decided at write time by `getEditorMode()`
  (`core/system.php:3849`, called from `filterHtml()`,
  `core/security.php:976-983`). It is a **site-wide** setting and `_comment`
  carries no per-row record of which format a row was written in.
- Direct `_comment` consumers outside the render path. The first two entries are
  **closed**: the frontend write handlers moved into `Comment` in batch 3 and the
  admin module in batch 4, and neither file holds a `_comment` statement any
  more:
  - ~~frontend write handlers — `core/user.php` (flood check, insert, last id),
    `core/system.php` (`updateComment()`, `updateCommentStatus()`)~~
  - ~~admin module — `admin/modules/comments.php` (module list, search list, edit
    save, bulk actions, approve, delete, pager table binding)~~
  - user activity feed — `core/user.php:732` UNION branch
  - installer schema — `setup/sql/table.sql`, `setup/sql/table_update*.sql`
  - module deletion handlers — `faq`, `files`, `links`, `media`, `news`,
    `pages`, `shop`, `voting` admin modules
  - one dead consumer — `modules/account/admin/index.php:921` holds a
    **commented-out** `DELETE FROM _comment WHERE uid = :id`, so deleting a user
    currently orphans their comments
  All of them must be migrated before direct table access is removed.
- `modules/shop/admin/index.php:707` interpolates its id list straight into
  `IN (...)` with no parameters, unlike the `news` and `pages` handlers.

## Problems this causes

1. **The moderation mode came from the client — closed by stage 0 on
   2026-07-28.** `addComment()` read `cid` from the request and decided the
   published/pending status from it, so submitting a different value published
   past premoderation; `mod` was request-supplied too and fed both
   `is_moder($mod)` and the stored `modul` column, so a comment could be attached
   to an arbitrary module. The same held in `updateCommentStatus()`, where the
   request `mod` drove the permission check **and** was passed to `numcom()`,
   moving the counter of the wrong target row. Today the mode comes from
   `getCommentMode()` (`core/system.php:5705`, called at
   `core/classes/comment.php:113` since batch 3) and both update paths read
   `modul` from the comment row (`core/classes/comment.php:139`, `:158`, `:171`, and
   before batch 3 in the two handlers themselves). The description stays here
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
`getAdminScope()` (`core/classes/comment.php:197`, and
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
(`core/system.php:5602`, `:5691`, `admin/modules/comments.php:106`),
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
`getBranch()`. `getComment()`, `getModuleList()` and `updateBody()` were added
during stage 1 rather than designed here: the moderation module reads one comment
for its edit form, asks for the module names of its selector, and saves a body
without the author's edit rules, and the stage cannot claim "no direct `_comment`
SQL outside `Comment`" while any of the three has nowhere to go.

The verbs are spelled out rather than left as `add()`, `update()` and `delete()`
because `.rules/global.md` requires 6-24 characters and a verb-plus-noun shape.
The names no longer collide with the global functions of the same name, since
those are deleted in the same stage that introduces them.

Two enums:

```
CommentStatus: Pending = 0, Published = 1
CommentMode:   Disabled = 0, Moderated = 1, Open = 2
```

`setComShow()` stays as the rendering function. `ashowcom()` is deleted once its
callers move, in the same stage — no wrapper, no transitional alias.

Every direct SQL statement against `_comment` moves into `Comment`: frontend
list/add/edit/status, the admin module, the user activity feed, the deletions
performed when a target row is removed, and bulk moderation.

### Counters and points

`numcom()` (`core/system.php:5728`) is not a comment query — it writes the
`comments` column of the target table and calls `updatePoints()`. It becomes a
**private** method of `Comment`, invoked from `addComment()`, `setStatus()` and
`deleteComment()` inside their transactions, and disappears as a global
function.

Three of its eleven branches are dropped in the move, covering four module
names: `account`/`members` (points slot 3, `core/system.php:5734-5736`),
`gallery` (slot 17, `:5743-5745`) and `multimedia` (slot 29, `:5752-5754`).
Each has its table `UPDATE` already
commented out and does nothing but award points, and none of them is reachable:

- `numcom()` is called from the `Comment` class alone since batch 4 —
  `core/classes/comment.php:131`, `:165` and `:176`, and nowhere else in the
  project;
- `modules/` contains no `gallery`, `multimedia` or `members`, and `account`
  does not render comments, so `_comment.modul` can only hold one of the eight
  supported modules;
- the only way to reach them today is the request-supplied `mod` described in
  problem 1 — sending `mod=gallery` awards points for a comment stored against
  another module. Stage 0 closes that, which is what makes the branches
  provably dead rather than merely unused.

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
last inserted comment (`getLastId()`, `core/classes/comment.php:260` since batch
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
deciding whether to write and to count (`core/classes/comment.php:158-165` and
`:171-177`, the guard batch 4 gave them). That closes the *repeated click*, which
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
  call `getSiteToken()` directly (`core/system.php:5622`, `:5634`, `:5638`,
  `:5642`, `:5653`), so under page cache they are baked in and served to the
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
  (`core/classes/pdo.php:148`); stage 1 records which existing mechanism covers
  the frontend and stage 4 confirms it against both transports.

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
| 8 | **this plan, stages 4 and 5** | stage 2 |

Only row 6 crosses between the plans. Everything else within a plan is strictly
sequential, and the two plans are otherwise independent.

### Stage 0 — close the trust boundary

A hotfix in the current procedural code, shipped before any refactor. It is
small, independently deployable, and does not wait on the class. It closes two
separate holes that happen to live in the same request path.

**Stored XSS in comments — already fixed, ahead of this plan.** Comments render
at `safe = false` (`core/system.php:5602`, `:5691`,
`admin/modules/comments.php:106`), and the parser refused only `data:` links
unconditionally, so `[url=javascript:alert(1)]` produced a working
`href="javascript:..."` for every author, anonymous included. Neither author nor
viewer needed any privilege: an anonymous comment lands pending and fires in the
**moderator's** browser during review (`core/system.php:5497-5502` shows the
moderator branch has no status filter), while an ordinary registered user
publishes immediately (`core/classes/comment.php:122` since batch 3).

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
| 6 | `numcom()` absorbed as a private method, `ashowcom()` deleted | both are unreferenced by the time this lands |

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
  files. `tools/` and `tests/` are excluded from that sweep by name:
  `tools/comment-baseline.php:37` queries the table directly on purpose, because
  a parity tool that went through the class under test would prove nothing.

### Stage 2 — validation, storage and write consistency

- Merge add and edit validation into `checkRules()`.
- Fix the maximum word length check: measure the longest word, not the last one,
  and measure characters rather than bytes (`checkEditRules()`,
  `core/classes/comment.php:238`).
- Apply `CommentMode` to every remaining bare `acomm` comparison; the enum itself
  arrives in stage 1 with the class.
- Make add/edit/status/delete transactional, with the explicit result checks and
  conditional updates described above.
- Add `format`, `reqkey`, `edited`, `deleted`, `iphash` and the five new indexes;
  drop `cid` and `modul_status`, **keep `time`**.
- Backfill `reqkey` with a random key per row, and `iphash` from `ip`.
- Run the body migration below, then switch **all three** render sites to
  `safe = true` (`core/system.php:5602`, `:5691`,
  `admin/modules/comments.php:106`). They were four until batch 3 merged the two
  inside `updateComment()` into one; the fourth, `core/system.php:5558`, sits in
  the unreachable `defined('ADMIN_FILE')` branch of `ashowcom()` that batch 6
  deletes with the function.
- Replace `filterHtml()` in the comment write path with the comment-specific
  normalisation that stores source; `filterHtml()` itself is not modified.
- Reject `html` as a comment format at the write boundary.
- Switch listings to `time, id`. Rows sharing a timestamp may change relative
  order — that is expected and is exactly what the stable sort fixes.

### The body migration, concretely

Rendering at `safe = true` requires the stored body to be source rather than
pre-escaped HTML, so all existing rows are converted once. There is no `format`
column to consult, but the writer left signatures, and they were measured across
all 7355 rows on 2026-07-28:

**The signatures overlap, so classification is ordered, not parallel.** Measured
on this installation: 1766 rows carry a machine-written `<br>`, 100 carry an
unescaped tag, and **56 carry both**. Counting each signature independently — as
an earlier revision of this table did — produces groups that sum past the row
count and a row that would receive two verdicts. Every row gets exactly one
`format`, decided by the first rule that matches:

| # | Rule | Verdict | Rows here |
|---|---|---|---|
| 1 | contains an unescaped tag other than `<br>` | `legacy` | 100 |
| 2 | contains `<br>`, `<br/>` or `<br />` immediately followed by `\n` or `\r\n` | `plain` | 1709 |
| 3 | contains any `<br>` form not followed by a line break | manual review | 1 |
| 4 | contains a line break | `markdown` | 10 |
| 5 | anything else — a single line | `markdown` | 5535 |

Rule 1 comes first because a row carrying real tags cannot be treated as plain
text no matter what else it contains; the 56 overlapping rows land there. The
counts above are what an ordered pass produces, and they sum to 7355 exactly.

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

- Replace the synchronous `addAdminMail()` call with a queue job.
- Write the job inside the same transaction as the comment.
- A delivery failure must never roll back a stored comment.
- Verify submit latency drops to the render cost.

### Stage 4 — fragment responses

- Change transport and template targets only.
- Add returns one fragment or the pending confirmation.
- Edit, status and delete operate on a single comment.
- Move mutations to POST.
- Move CSRF out of the URL and route every token through `getPageToken()`.
- Fix the form reset so it happens only on success.
- Verify both the HTMX path and the plain POST fallback.

### Stage 5 — threads

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
- **Which existing mechanism purges the frontend page cache.** The *contract* is
  settled in **Page cache** above — `Comment` purges, after commit, never on
  failure, across every pagination and sort variant. What stage 1 still has to
  identify is which existing helper already does this for the frontend;
  `Cache::addEpoch()` covers admin mutations only (`core/classes/pdo.php:148`).
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
