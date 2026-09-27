# Node Findings 2026

Work plan for everything the Node plan left open when it closed on 2026-09-27.
The Node plan in `docs/node/` recorded these items window by window as "outside
acceptance", "open records" and "earlier findings"; before that plan is removed
they were collected here and every one was checked against the working tree of
2026-09-27. Most of them are outside Node itself — shop, order, forum, account,
the core, the installer, the tooling — because the Node windows met them on the
way and were not allowed to fix them.

Status: planned, nothing implemented. Batches run in order, one batch per
window; a batch that meets a fork asks the owner first (`AskUserQuestion`) and
writes the answer next to the item before touching code. Update this line and
the batch heading as they land. The last batch deletes this file.

No line numbers anywhere on purpose: every reference names the function, the
file, the config key or the constant, and that name is what to search for.

Each batch closes with the gates of the project (`php -l` of the touched files,
phpstan, php-cs-fixer check, the full phpunit, `npm run ui:gates`) and, where it
writes over HTTP, a read of `storage/logs/error_php.log`, `error_sql.log` and
`error_site.log`. A batch that changes templates or theme CSS takes the
`ui:before`/`ui:after` pair. Every fix of a defect comes with a test that fails
without it, unless the item says why not.

## Batch 1 — shipped configuration and admin security

Highest first: two of these leak data on a clean installation.

- **Debug panel open to every visitor.** The shipped `config/global.php` carries
  `var_view => '1'` with `variables` sections on, so `checkDebugView()` in
  `core/security.php` opens the panel to all visitors; `getVariables()` in
  `core/system.php` prints COOKIE, SESSION, FILES and SERVER, and prints them
  unescaped when its flag is false. Ship `var_view => '0'` and escape every
  value in `getVariables()` regardless of the flag. The stand keeps its own
  value (stand difference, not staged).
- **Test bans in the shipped `config/security.php`.** `blocker_ip` and
  `blocker_user` in HEAD carry the stand's test entries (`fan-ip-*`,
  `fanuser*`, `dummy-c`, `test`). Ship them empty. The file also carries the
  stand `secret`, which is never staged; the commit of this batch stages a copy
  with both lists and `secret` emptied and restores the stand file after.
- **Admin file rename without a name check.** `configsave()` in
  `admin/modules/security.php` reads `afile` with the `text` filter and passes
  it to `rename()`; a value with `../` moves `admin.php` out of the web root or
  over another PHP file, and a failed `rename()` only raises a warning. Accept
  `^[a-z0-9_-]+$` only, check the result of `rename()` and answer a flash error.
- **Shop and order handlers without the admin POST check.** The state-changing
  handlers of `modules/shop/admin/index.php` and `modules/order/admin/index.php`
  call `checkSiteToken()`, which takes the token from a header, GET or POST and
  does not check the method. Switch them to `checkAdminPost()` with their scope,
  as every other admin handler does.
- **`config/` served by nginx.** Owned by `docs/PRIVATE-DATA-2026.md` (package
  3); not duplicated here — this batch only confirms that plan still carries it.

## Batch 2 — shop, order and forum correctness

- **Checkout sees an empty cart (high).** The cart is written through
  `setCookies('shop')` as `<user_c>-shop`; the checkout in
  `modules/shop/index.php` reads the raw cookie `shop` and clears it with a raw
  `setcookie('shop', false)`. Read through `getCookies('shop')`, clear through
  `setCookiesDelete()`.
- **Clients pager of the shop.** `op=clients` calls `getTplPager()` without
  `table` and `where`, so the count runs `COUNT(id) FROM <prefix>` and fails;
  the `count` and `pages` keys it passes are unused. Pass the table and the
  condition, or the known count.
- **Ambiguous `status` in the product list.** The admin list filters `status`
  while joining `_categories`, which has its own `status`. Qualify the column.
- **Mass "move to category" writes the comment mode.** The mass action builds
  `c<id>` and the handler writes it into `acomm`. The category branch sets
  `cid`; the comment mode gets its own prefix.
- **Point compensations that swallow `false`.** Forum post deletion, order
  activation and cancellation, and three shop client paths treat `false` from
  `Point::getEventId()` as "no award" and drop a `false` from `addEvent()`. By
  `docs/POINTS.md` `false` is an error: roll
  the owner back, as `Comment` does.
- **Product CSV import writes the rating aggregate.** The import of `modules/shop/admin/index.php` writes
  `_products.votes` and `tvotes` directly; a product with a non-zero aggregate and no `_rating_targets` row then
  refuses every vote as `storage`, and an overwritten product breaks the rule that the aggregate is the starting
  balance plus the active votes (docs/RATINGS.md). Leave the aggregate out of the import, or register the start
  through `Rating`.

## Batch 3 — admin forms and categories outside Node

- **Form fields named `name`.** The auto_links admin form and the account block
  form (`name_b` screen) post a field `name`, which overrides the module name
  `getVar('req', 'name')` routes by. Rename the fields and their reads.
- **Category cycles for forum and shop.** The non-Node branch of `save()` in
  `admin/modules/categories.php` writes `parent` from POST without checking
  that it is neither the category itself nor one of its descendants; Node does
  that in `checkCatParent()`. Check the same there.
- **`setForumLast()` does not end on a cycle.** The walk up `$up` has no step
  limit and the subtree collection grows without a seen set; with a stored
  cycle every forum write hangs. Bound the walk and track visited ids — even
  after the previous item, stored data may already hold a cycle.
- **Favourites of forum and shop count the limit without a lock.** The legacy
  branch of `addFavorite()` in `core/user.php` counts and inserts without a
  transaction. Lock the `_users` row as the Node branch does.
- **Demo field `field3` blocks saving the profile.** The shipped
  `config/fields.php` has an account demo field with `req => true`.
  Fork: drop the demo fields from the shipped config, or only un-require them.

## Batch 4 — core writes, cache and templates

- **Compiled template written in place.** `Template` writes the compiled file
  with `LOCK_EX` directly into its final path while another request may
  `include` it — the source of the `errno=13` lines in `error_php.log` (276 on
  the stand). Write to a temporary file in the same directory and `rename()`.
- **Early epoch bump suppresses the final one.** The admin auto-bump in
  `core/classes/pdo.php` sets the bumped flag of `Cache`, so the final
  `addEpoch()` after commit returns early and a page can be cached between the
  two. Fork: drop the pdo auto-bump, or force the bump on commit.
- **`Comment::updateBody()` writes outside the guard.** It runs an autocommit
  UPDATE without the write guard, the epoch or the lock of a Node material.
  Wrap it like the other writers of `Comment`, with the material lock for a
  Node target.
- **Deferred comment recount of shop and voting.** `setTargetCount()` runs after
  the response outside the guard. Take the guard and bump the epoch there.
- **Silent failures.** `Comment::deleteTarget()` returns `false` without a log
  line, and `Comment::getUserCount()` turns a `NodeException` of a batch into
  zero without one. Log the reason before answering.

## Batch 5 — Node lists, comments and counts

- **Replies of later roots crowded out (medium).** `Comment::getTreeRows()`
  reads replies with one `LIMIT count(roots) * reps` in `sk` order; a long
  branch of an early root takes the whole limit and later roots show a reply
  count without rows. Limit per root.
- **Batch sizes above `limits.syncbatch`.** `Comment::getUserList()` slices by
  500, the related cards of a material slice by 500, and `checkNodeRefs()`
  chunks by 500, while `NodeQuery::getNodeTargetList()` refuses more than
  `min(500, syncbatch)` — lowering `syncbatch` below the stored relations makes
  the view throw and drops comments from the profile feed. Use
  `min(500, syncbatch)` everywhere, as `getUserCount()` does.
- **Support thread N+1.** `support()` in the Node admin calls
  `Comment::getBranch()` per truncated root of every page. One read of the tree
  of the target.
- **Profile comment count per view.** `Comment::getUserCount()` groups every
  comment of the account and reads targets in batches on each profile view.
  Fork: exact count per viewer, or a cached count per account reset on write.
- **`NodeQuery::getNodeCount()` counts every published row of the type.**
  Fork: exact count, capped pager, or cached count.
- **`NodeQuery::getCatMap()` computes `view`/`post` on every read.** Negligible
  cost. Fork: leave, or compute lazily for the form only.

## Batch 6 — Node type settings, forms and admin

- **Stored type turned unreadable by a later rule.** The sync extension refuses
  `features.submit` and the asset rule refuses `report` outside `download` and
  `link`; both checks also run when a stored type is read, so a stored type
  that predates the rule disappears. No such type exists today. Fork: keep
  refusing on read, or normalise on read with a log line and refuse on write.
- **Report checkbox offered to every mode.** The role editor shows the
  `report` flag for every mode; only `download` and `link` accept it. Show it
  by mode (`sl-x-{{ mode }}` plus CSS), no comparison in the template.
- **`view.mode` is free text.** The type constructor posts it raw and
  `NodeQuery` checks only the name pattern; `support` can be chosen by a type
  without the extension. Validate against the list of modes and require the
  support extension for `support`.
- **External address of a non-moderator.** A non-moderator sets a new external
  address only on a material that goes to pre-moderation (decision of S22.3),
  yet the upload field of `*.attach` always offers the link input and the
  refusal is the general `_NODE_INVALID` without a path. Fork: hide the link
  input for such a writer, or keep it and answer a specific message; in both
  cases `getNodeFault()` names `assets.<n>.src`.
- **Categories of all languages in the form.** `getNodePostCats()` does not
  filter the category language; the contract says only lists, search and tree
  filter it. Fork: filter the form and the writer by language, or keep.
- **`order=published` of a public list answers 400** when `list.orders` does
  not name it, though the reader accepts `published` for any type. Fork: align
  the route with the reader, or narrow the reader to `list.orders`.
- **Import of a profile refuses without a reason.** A wrong extension, an upload
  error and a file over 1 MiB all end in the general `_NODE_BAD`. Answer each
  with its own message before calling the writer.
- **`checkCatRead()` also decides `pview` and `ppost`.** Rename it to what it
  checks (for example `checkCatRight()`).
- **Node materials in the sitemap ignore the language** while the categories of
  the sitemap follow it. Fork: filter materials by the language of their
  category, or keep all languages.
- **Media duration never filled.** The `duration` of a resource is only carried
  over from a previous row, so the media tile shows the year or the date.
  Fork: probe media on upload, or keep the chip as it is.
- **`docs/view.html` and `faq/view.html` are identical.** Fork: keep both, or
  drop one and let the mode fall back to the base template.

## Batch 7 — installer and the 6.3 update

- **`UNIQUE mid_modul_ip` over duplicates.** `table_update6_3.sql` adds the
  unique key of `_rating` without removing duplicate `(mid, modul, ip)` rows; a
  6.2 site with such rows stops on a red schema line. Fork: remove duplicates
  keeping the first vote, or stop with a message of its own.
- **Old admin loader left in the root.** When `admin.php` is present the update
  renames it, and the 6.2 loader under its own `afile` name stays in the root as
  a broken entry point; with the default name the new panel keeps a guessable
  name. Fork: replace the 6.2 loader automatically, refuse with a message, or
  keep the instruction in `UPGRADING.md`.
- **Setup key without an attempt limit.** `config()` and `save()` of the
  installer verify the key code with no counter and no delay, and the code sits
  in clear text in `config/setup.unlock` until the first request hashes it.
  Fork: delay per failure, counter with removal of the key after N failures,
  accept only a prepared hash in the key file, or accept the risk.
- **`_privat` missing from the InnoDB preflight** although `Privat` locks rows.
  Add it.
- **Field preflight on the full slaed-old dump** stops on 3007 values without a
  definition. Fork: migrate them, drop them with a report, or keep the refusal.
- **Comments inside `save()` of the installer.** Move them above the function.
- **Not run yet:** the `update` branch on MySQL 8 (`SHOW CREATE TABLE` parsing
  of `AUTO_INCREMENT`), the older branches `update4_1`…`update6_2`, the
  `update` mode of `install_probe` inside phpunit, and a live `save()` after a
  red line (no `modules` mark). Run each once on the fixture or a disposable
  server and record the result here.
- **Header of `table_update6_3.sql`** says missing tables are skipped silently; that holds for the procedures
  only, not for the plain `ALTER ... MODIFY` statements. Correct the header.

## Batch 8 — interface, language and small defects

- **Favourite links without htmx go to a GET.** The star in
  `fragments/favorite.html` and the trash of the favourites shelf carry an
  `href` that answers `_ERROR` since both ops are POST-only. Fork: buttons
  only (no JS, no action), or a POST form fallback.
- **POST-only ops answer 200 to a GET.** `index.php` dies with `_ERROR` and
  status 200. Answer 405 with `Allow: POST`.
- **`_BACK` means "ago" in Polish and Ukrainian** (`Temu`, `Тому`), but the
  pager, the admin buttons and the document tree use it as "back". Use
  `Wstecz` and `Назад`.
- **Admin pager shows no total** although callers pass the count; the lite
  pager does. Add the info line to the admin pager fragment.
- **`slaed.js` parses input through `innerHTML`** in the translate helper, where
  an `img onerror` fires on the detached element. Use `DOMParser` or
  `<template>`.
- **Search snippet keeps the text of `<script>` from `[usehtml]`.** Output is
  escaped, the text is noise. Remove script and style blocks before
  `strip_tags()`.
- **Header category of non-Node modules ignores the language.** `setHead()`
  checks `pread` of a module category without the language filter the sitemap
  uses. Add it for `multilingual`.
- **Title tail of `word`, `let`, `num`.** `setHead()` appends them to the title
  while canonical points elsewhere. Fork: keep, or drop the tail when canonical
  differs from the current address.
- **Account points form with a reused `pkey`.** A second post of the same form
  with another amount answers an empty success. Fork: refuse on a stored
  mismatch, rotate the key after a refusal, or keep.
- **`demo/fav-01-shelf.html` still names `hx-get`** for the deletion. Correct
  the comment.
- **`OauthTest::jwtRejectsInvalidTokens`** computes "future" when the data
  provider loads; a full run longer than four minutes makes the case pass for
  the wrong reason. Compute the time inside the test.
- **Rating widget decides from the raw rule strings.** `Rating::getRating()` has no production caller; a
  malformed rule with `active = '1'` shows a live widget whose votes answer 404 with `_RATINGS_GONE` ("item was
  not found"), a misleading text for a blocked scope. Decide the widget through `Rating` and answer a blocked
  scope with its own text.
- **Points interface without the class.** The interface of points follows `$conf['points']['active']` even while
  the `update.points` mark is missing and `Point` stays closed. Show it only when the class is open.

## Batch 9 — code rules sweep

Mechanical, outside Node, no behaviour change; the tests that guard the rules
are extended in the same batch so the debt does not come back.

- **Comments inside function bodies** (`.rules/global.md`, Comments): presentation,
  account, clients (also Russian text), forum, forum admin, config admin,
  `index.php`, `core/system.php`, `core/user.php`, `core/helpers.php`,
  `tests/SchemaUpdateValidationTest.php` (also `//`). Move each above its
  function and add a token-based check to `PhpFileFormatTest` (a `#` comment
  inside a function body).
- **Multi-line comments above classes** outside Node: backup, comment, feed,
  field, filemanager, point, privat, rating, upload. One line each; the rest
  goes to the method comments. Extend the test to classes.
- **Scope of `PhpFileFormatTest`.** A wrapped comment is caught only when the
  next line starts with a lowercase ASCII letter, and `plugins/`, `lang/`,
  `tools/`, `tests/` are not scanned. Fork: widen, or keep the S22.5 boundary.
- **Lines over 180 characters** in `core/system.php`, `core/user.php`,
  `core/helpers.php`, `core/classes/parser.php`, `modules/account/index.php`,
  `modules/presentation/index.php`, `admin/modules/monitor.php`,
  `setup/index.php` and tests outside the plan (contract_probe,
  EditorWindowTest, UploadIntegrationTest, ParserFixturesTest).
  Fork for lang files: exempt one-line `define()` translations in
  `.rules/global.md`, or wrap them.
- **`list()` instead of `[...]`** in `core/system.php` outside Node functions.
- **Legacy names:** `$new_modules` in `is_admin_modul()`; `$fmassiv`,
  `$ffmassiv`, `$fav_num` in `getAdminFavoriteList()` (also read before being
  set).
- **Redundant `(string)getVar(...)`** where the filter already returns a string
  (`core/user.php`, `core/helpers.php`, several admin modules, account).
- **Untyped arrow functions in tests** (install_probe, node_probe).
- **Periods at the end of `#` lines in `table_update6_3.sql`** (20 lines).
  Fork: the comment rule applies to SQL files, or it does not.

## Batch 10 — tooling, stand and cleanup

- **`tools/ui-shots.mjs`** logs in two modes in parallel with one attempt, and
  `--before --only` wipes the whole pair directory. Serialise or retry the
  login; delete only the named pages.
- **Parallel phpunit and `ui:gates` collide** on `templates/scratch-*`. Give the
  scratch theme a per-process suffix.
- **The full phpunit writes mail test lines into `storage/logs/error_site.log`**
  of the stand. Point those tests at a scratch log.
- **Stand data, only on the owner's command:** comments of the removed modules
  shown by jokes, content and media with the same ids; the remains of faq,
  files, help, links, news and pages; the two publish/reverse pairs of user
  7885 in `sport_points` left by an acceptance run.
- **Artifacts outside git:** the acceptance reports `storage/backup/s*.json` of
  the Node windows and the scripts `c:/tmp/s19*`. Delete them.
- **Delete this file** and drop its line from the project memory.
- **`PointTest` and `RatingTest` skip when their probe fails.** `markTestSkipped('Probe: ...')` turns a broken
  probe into a green run; the other probe-driven tests fail instead. Fail, and keep the seven skips of the full
  run limited to missing environment.

## Closed on verification

Checked 2026-09-27 and no longer true in the tree, so not carried: the
20-step limit of `getNodeCatOptions()` (rewritten with a seen set); three
memoisers in `NodeRouteTest` (one `getMode()` now); long lines of
`install_probe`, `UpdateSetupTest` and `NodeServiceTest`; the red
`NodeProfileTest` after a3efe657 (fixed by S21.2, not committed yet); the site
language taken from the installer cookie on update (fixed, not committed); the
flash of the site without auto-hide (so defined by `tools/ui-contract.php`).
Plain observations of the Node windows (what the stand has, which checks ran on
a probe instead of the stand) stay in the git history of `docs/node/`.
