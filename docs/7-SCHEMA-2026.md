# Schema 2026

Work plan for the database schema of release 8.0 — `storage/update/sql/table.sql` with its seed `insert.sql`, its 6.2
path `storage/update/sql/table_update6_3.sql` with the steps of `public/update.php`, and the contract of the
connection — brought to one set of conventions before the release, while a change still costs one edit instead of a
migration on every installed site.

Status: batch 0 done 2026-10-12 — the inventory stands under "Inventory", its decisions under "Decisions"; batch 1 done
2026-10-12 — the connection contract of "Design / Connection" is in `Database::__construct()`; batch 2 done 2026-10-12 — the
text domains, the collation from one place and the cuts, its choices under "Decisions"; batch 3 done 2026-10-12 — every
time column on its form, the forum new mark on the last visit, its choices under "Decisions"; batch 4 done 2026-10-12 —
no flag, counter or text is NULL, `_users.lang` holds codes, an empty birthday is NULL, its choices and the release state
of the stand under "Decisions"; batch 5 done 2026-10-12 — unique addresses with NULL for none, every key of "Inventory /
Indexes" out or in, `_node_categories` on its pair, its choices under "Decisions"; batch 6 done 2026-10-13 — any 6.2
site migrates (owner), the rehearsal on a clean 6.2 on three servers, the fresh installation green again; batch 7
open. The order of the batches is kept in `docs/ROADMAP-2026.md`. Update this line as batches land.

No line numbers anywhere in this document on purpose: every reference names the table, the column, the function or
the file it points at, and that name is what to search for.

"What exists today" holds only facts confirmed on 2026-10-09 by an independent check against every reader and writer
in the tree and against the stand database. Whatever was not confirmed that way is not stated as a fact here; it is a
task of batch 0.

## Decisions

Taken by the owner on 2026-10-09; settled, not to be reopened by a batch. The plan itself may still be changed by the
owner.

- **Everything right before the release.** After 8.0 every change of a type, a key or a collation is a migration step
  on every installed site; before it, the same change is one edit of the schema places. The plan therefore does the
  whole set, not the cheapest part.
- **Time.** A moment a person reads or sets is `DATETIME`; a machine timer — a session, a time to live, a window of
  the rating — is `BIGINT UNSIGNED` unix seconds; a column that fits neither reading is classified in batch 0.
  `VARCHAR` never holds a time. Every connection runs in the zone of PHP, set when `Database` connects, so `NOW()`
  and `date()` write the same moment on every path, not only on a site request. Rows written before keep the offset
  they were written with: the skew of the past cannot be known per row and is not back-filled.
- **Email is unique** for `_users` and `_admins`. When a 6.2 base holds duplicates, `update.php` stops with the list
  of them and the operator resolves them by hand; the update never rewrites an address on its own.
- **Collation `utf8mb4_unicode_520_ci`.** The owner set the yardstick — what hosting supports now — and the choice
  follows from it. Now (2026-10) cPanel installs MariaDB 10.11 by default and offers 10.5 … 11.8 and MySQL 8.0 / 8.4.
  The newest collations of each family — `utf8mb4_0900_ai_ci` of MySQL, `utf8mb4_uca1400_ai_ci` of MariaDB — do not
  exist on the other family, and `0900_ai_ci` is refused by MariaDB 10.11; `unicode_520_ci` is the newest one both
  know. Against the `utf8mb4_unicode_ci` of today it tells apart the characters outside the basic plane — on the stand
  server 😀 equals 😃 and 𠀋 equals 𠀀 under `unicode_ci`, both differ under `unicode_520_ci` — and compares
  Cyrillic, case and accents exactly as before (ё = е, Е = е, é = e, ß = ss in both). Newer MariaDB carries
  `utf8mb4_0900_ai_ci` as an alias (the stand's 11.7 lists it; from which version on is not checked). Once every
  version hosts still offer knows it, the move is one value and one conversion step, which is why the collation is
  read from one place only.
- **Order:** the last steps before the close of `docs/ROADMAP-2026.md`, so the final pass also catches what the
  steps before it add to the schema.

Taken by the owner on 2026-10-12, in batch 0:

- **`_users.viewmail`: what others see is kept** — NULL becomes 0 and the column defaults to 0 (the proposal below,
  confirmed).
- **`_users_temp.time` leaves.** It repeats `regdate` of the same insert; the cleanup in `activate()` reads
  `regdate < NOW() - INTERVAL 1 DAY`.
- **The false birthdays go.** A `_users.birthday` equal to `DATE(regdate)` was written by the `date` filter of
  `getVar()` into an empty field (5 880 stand rows); the 6.2 path and the stand set those rows NULL, and every writer
  stores an empty field as NULL.
- **One cut function.** `filterCutText()` cuts a stored, already escaped value to its column by characters without
  splitting an entity; every writer of "Inventory / Uncut writes" calls it, and the `name` filter of `getVar()` and the
  byte cuts `substr(…, 0, 25)` before `filterText()` move onto it (see "Writes fit their columns").
- **The "new" mark of the forum is repaired in batch 3**, with the other time readers (see "Inventory / Time
  columns").

Taken by the owner on 2026-10-12, in batch 1:

- **A zone offset the server refuses falls back to the zone's name.** MariaDB takes an offset from −12:59 to +13:00
  only; of the zones of PHP, `Pacific/Kiritimati` (+14) and `Pacific/Chatham` in its summer (+13:45) lie beyond it, and
  MySQL 8.0.19+ takes up to +14:00. A refused offset is followed by the named zone, which works where the server has
  its zone tables loaded; a refused name leaves the session in the server zone with a row in `error_sql.log`, so the
  site keeps running rather than losing its connection.

Taken in batch 2, 2026-10-12, where the plan left the choice to it:

- **The 6.2 path converts column by column.** `setconv` of `table_update6_3.sql`, the last block of the file, sets the
  table default and modifies only the text columns of another utf8 collation; `CONVERT TO` in one statement with the
  `MODIFY` of the binary columns does not work, MariaDB 11.7 applies the conversion over the `MODIFY` and a case-only
  twin of `_maildead` breaks its unique key (tested).
- **A 6.2 `config/db.php` takes the collation of the release**; a 6.3 one keeps its own (`getUpdateCred()`).
- **Refused rather than cut** where a cut value would name something else: an address over 255 (`checkemail()`), an
  OAuth return address over 512 (`Oauth::getRedirect()`), the site address of `setup.php` over 255, a block file name,
  a group rank over 255, a category row of `NodeService` over its columns. A fixed choice outside its values becomes
  the empty column default (`bpos`, `action`), a language outside `lang/` the empty "all languages" or the site
  language for an administrator, a newsletter `apar` the parameter its audience reads.
- **`finalize_user_names` builds its temporary table in the collation of the column**: a database default other than
  the collation of the 6.2 tables failed its join with 1267 (found by the update probe once the probe database took
  the new collation).

Taken in batch 3, 2026-10-12, where the plan left the choice to it:

- **A passage to a new type runs through `runiftype()`**, a procedure of `table_update6_3.sql` that runs a statement
  only while the column still has its old type: a unix text or number becomes a 14-digit text and then the
  `DATETIME`, so a second run finds the target and changes nothing. `SchemaUpdateValidationTest` reads such a step as
  a passage, not as a declaration.
- **A conversion uses the offset of the connection**, which is today's offset of the site zone: a 6.2 expiry across a
  change of summer time lands one hour off. Expiries are days ahead and rows of the past are not back-filled (see
  "Time"), so the hour is accepted.
- **The 6.2 path stops on a missing `_forum.time`, `_newsletter.time` or `_voting.time`**, as it does for `_comment`
  and `_privat`; a missing `_forum.ltime` takes the topic time, a missing `_voting.enddate` the poll start, which is
  how the site read such a poll: closed.
- **A rating moment is the clock value of its vote**: `created` and `annulled` are `FROM_UNIXTIME(:now)` of the same
  `UNIX_TIMESTAMP()` that fills `last`, rather than a second `NOW()`, so a vote and its participation keep one moment.
- **The panel sets an expiry from days only while there is none** (`COALESCE(expire, NOW() + INTERVAL :days DAY)`),
  as the form offered the field only then; the hidden round trip of the old value and `newexpire` are gone.
- **`format_time()` takes a moment that was never recorded** and answers `''`, so `lastvis`, `lastlog` and the other
  `NULL` moments need no guard at each reader.
- **One spelling of a nullable moment**: `DATETIME DEFAULT NULL`, also for `_node_assets.reported` and the three
  of `_node_sync`, which said `DATETIME NULL DEFAULT NULL`.

Taken in batch 4, 2026-10-12, where the plan left the choice to it:

- **`_admins.password` is `NOT NULL` without a default**, as `_users.password`; `_users.sig`, `_forum.body` and
  `_newsletter.body` are `TEXT` / `MEDIUMTEXT NOT NULL` and every insert supplies them, the probes and
  `tools/node-profile.php` included. `SchemaConventionTest` holds it: a column is nullable only as a moment
  (`DATETIME DEFAULT NULL`) or as one of the named exceptions — `_comment.reqkey` (no request key), the five measures
  of `_node_assets` (an outside file), `_points.rid` (not reversed) and `_users.birthday` (a calendar day).
- **The false birthday had two sources, both fixed at the source.** The `date` filter of `getVar()` answered today
  for an empty or invalid value, and `getTplAddDateTime()` prefilled a date-only control with today, so even an
  untouched settings save posted a day. Both served the birthday alone; the filter now answers empty, the
  date-only control stays empty, and the two writers pass `null` as the default, so an empty field stores NULL.
- **`viewmail` is read as the profile reads it** — truthy shows — by the settings switch, the cabinet lamp and the
  panel form; the other switches of `getSetupSwitch()` keep their reading.
- **The 6.2 language names are mapped in every `lang` column**, not only `_users` and `_voting`: a 6.2 site may hold
  names in `_admins`, `_blocks`, `_categories` and `_message` too. The schema file empties a `_users.lang` that cannot
  be a name or a code (`NOT REGEXP '^[A-Za-z]{2,10}$'`, both cases because an interrupted run leaves the column
  `ascii_bin` and the comparison case-sensitive) before it narrows the column; `setUpdateLangs()` of `update.php`
  turns the names into codes by `getUpdateLangs()`, the map `setUpdateConfig()` reads too, and sets an account
  language no `lang/` file serves to the empty site language. Content rows keep an unknown value: it was shown to
  nobody before and would otherwise be shown to everyone.
- **`configsave()` of `admin/modules/lang.php` keeps the stored language** when the form sends none.
- **The stand is on the release** (owner, 2026-10-12): it lacked the schema of batches 2–3, although batch 3 had recorded the stand as converted, and its `config/db.php`
  still named `utf8mb4_unicode_ci`, which turned `UpdateSiteTest` red against a fresh install in the release
  collation. After a dump into `storage/backup/slaed_2026-10-12_before-stand-release.sql` the stand configuration
  names `utf8mb4_unicode_520_ci` without `sync`, `table_update6_3.sql` ran over the stand without a failed statement,
  the eight time columns of the 6.3 tables that no 6.2 site has (`_mail`, `_node_publish`, `_rating_targets`,
  `_rating_votes`, `_user_oauth`) were converted by hand, and the master secret moved from `config/local.php` into the
  uncommitted `config/security.php` before the merge was rebuilt. The seventeen old module tables keep their
  collation as the input of the migration.

Taken in batch 5, 2026-10-12, where the plan left the choice to it:

- **`Database::getSqlTwin()` names the key a duplicate entry refused** (owner, 2026-10-12): the last query failed with
  1062 and the message ends in `for key 'email'` (MariaDB) or `'<table>.email'` (MySQL). The registration, the
  activation, the OAuth registration, the settings save, the account panel and the administrators panel answer
  `email` as `_ERROR_EMAIL` and `name` as the taken name of their screen; the first administrator of
  `admin/index.php` carries it in its redirect. The `SELECT` checks before a write stay the common path; the result
  answers the race, and the refused statement stays in `error_sql.log` as the real event it is.
- **All three unique address columns are `VARCHAR(255) DEFAULT NULL`**, `_users_temp.email` too, so the domain has one
  spelling; the account panel and the administrators panel check a taken address by the row found, not by a
  case-sensitive comparison with it.
- **The keys are reshaped after the collation.** The last block of `table_update6_3.sql` drops every key that leaves
  and sets every new or changed one through `setidx()`, which rebuilds a key only while its definition differs, so a
  second run changes nothing; the unique addresses are built in the collation `checkUpdateEmails()` of `update.php`
  compared them in before the schema file ran. A server that ignores a descending key part (MariaDB before 10.8)
  rebuilds the two keys of `_node_support` on every run. Cleanup moved behind that block.
- **The form of the administrators panel shows the refusal of an edit too**: `add()` of `admin/modules/admins.php`
  dropped every message of `save()` for an existing administrator, so a taken address was refused without a word.
- **`movenet` carries a missing address as `''`** into `_user_oauth.email`, which keeps its empty default; it runs
  after the address of `_users` became NULL and no longer leans on `INSERT IGNORE` to coerce it.
- **The address check refuses beside the preflight**: `checkUpdateEmails()` runs right after `checkUpdateBase()`, so
  a site with a shared address is neither closed nor touched; each address is a failed row with its accounts.
- **`modcol()` reads a moment default as written**: MariaDB reports `current_timestamp()`, MySQL `CURRENT_TIMESTAMP`
  with `DEFAULT_GENERATED`, so since batch 3 every run rebuilt `_privat` (and every table of such a `modcol`) and
  `PrivatMigrationTest` failed on its converted state; both readings now compare equal to the definition.
- **`_nodes.expires (tid, status, expires)` leaves installed sites too**: `table.sql` dropped it on 2026-10-05, the
  update never did, and the stand and production still held it.
- **The stand's three shared addresses stay on the active account** (owner, 2026-10-12): the accounts 35095, 35124 and
  35444 (2017, uLogin) hold NULL; production gets the same by hand when `update.php` lists them.
- **Any 6.2 site migrates** (owner, 2026-10-12, batch 6). The rehearsal runs on a clean 6.2 installation from
  `slaed-old.loc/public/setup/sql` (`table.sql` and `insert.sql`), not on a production dump: production is close to
  the last 6.3 and is updated later on a fresh dump. This overrules "The rehearsal data" of the FILES plan (roadmap
  step 15), which kept the migration on the 6.3 shape of production and rejected restoring the renames:
  `table_update6_3.sql` again renames the columns of the seven source tables of Node (`_news`, `_pages`, `_faq`,
  `_help`, `_links`, `_files`, `_content`) by `rencol`, which does nothing on a table already in the 6.3 shape.
  `update.php` stays the internal tool of the maintainer that the release does not ship, and `UPGRADING.md` keeps
  saying that the release updates no 6.2 site (owner, same day); only the sentences that a clean 6.2 is not migrated
  change. Rejected: shipping `update.php` with the release, which needs a guard of its first stage and a plan of its
  own.
- **The installer starts its session once** (batch 6, 2026-10-12). `setup.php` called `session_start()` without a
  check; where a session is already open — the router of `install_probe.php`, a host with `session.auto_start` — the
  notice went out before the JSON of every part of the run. The probe then read no `more`, left the run of its
  refusal checks unfinished in the session, `setSetupStep()` pinned the real installation to that run, and its first
  part was the tables, which read the `config/db.php` the probe had just removed (`2002`). `setup.php` now starts the
  session as `core/security.php` does, only when none is open. Rejected: changing the router of the probe, which
  would have hidden the same failure on such a host.
- **Kept**: `_forum.status` and `_forum.time`, `_categories.parent` and `ordern`; not in the list of unused keys. The
  per-module statistics of the search panel (`GROUP BY modul` with the score) still scan the log, which no key
  without `score` serves; `COUNT(DISTINCT word)` and the word list read `word_modul` alone (`EXPLAIN`, stand).

Proposed by the plan on 2026-10-09 and not yet confirmed by the owner; they stand unless the owner overrules them:

- **The version floor stays** MariaDB 10.5.2+ / MySQL 8.0.16+: nothing in the plan needs more, and hosts still offer
  them. The documentation recommends MySQL 8.4 and MariaDB 10.11 or newer, since MySQL 8.0, MariaDB 10.5 and 10.6 are
  out of upstream support.
- **One zone mechanism.** The zone is set unconditionally in `Database`; the switch `$conf['db']['sync']` and the
  dead panel switch `dbsync` leave.
- **Rejected, from an outside review of 2026-10-09:** `ON UPDATE CASCADE` on the foreign keys to `_nodes` (no code
  changes `_nodes.id`; `RESTRICT` is the guard against a hand edit), `_voting.typ` as a wider type (one flag, an open
  or a closed poll — `_VOPEN` / `_VCLOSE`), an index on `_comment.cid` alone and on `_forum (uid, time)` (no query
  reads either).
- **No foreign key for a polymorphic link** (`modul` + an id: `_comment.cid`, `_favorites.fid`, `_rating.mid`) nor
  for `_users.grp`, where 0 means "no group".
- **`_rating` stays** as the vote guard of `_voting`; folding it into `_rating_*` is a change of the voting, not of the
  schema. It gets the conventions of this plan like any other table.
- **`sql_mode` with `ONLY_FULL_GROUP_BY`**, **`UNIQUE` also on `_users_temp.email`** and the guard test
  `SchemaConventionTest` (see "Design").
- **A missing email is NULL**, not an empty string, in every unique email column: the OAuth registration creates
  users without an address, and a unique key takes many NULLs but only one empty string.
- **`_users.viewmail`: what others see is kept** — NULL becomes 0 and the column defaults to 0, because the public
  profile hides the address of every NULL row today; the readers that show such an address as public are made to
  read the flag as the profile does, so a save of the settings no longer publishes an address by accident. The
  opposite reading would publish the addresses of 2 900 users and of every new one: a privacy decision only the owner
  takes; confirmed on 2026-10-12.
- **`_favorites.time` stays nullable**, NULL meaning "not recorded" for the 6.2 rows.
- **Proposed by batch 0 on 2026-10-12, read from the rule of "Time":** `_blocks.expire` and `_message.expire` are
  moments a person sets (`DATETIME NULL`, as `_nodes.expires`); the stamps of `_rating_votes`, `_rating_targets` and
  `_user_oauth` are moments; `_users.lastvis` spells "never" as NULL instead of its `1970-01-01 00:00:01`; and the
  unused keys of "Inventory / Indexes" leave, `_nodes.queue` and `_node_assets.report` included although
  `docs/NODE.md` names them.
- **The user agent is cut where it is stored, not in `getAgent()`**, because `md5(getAgent())` keys the agent
  blocker and mail hashes: cutting at the source would let a blocked long agent through.

## What exists today

Confirmed on 2026-10-09 against the code and the stand database (the migrated production base, MariaDB 11.7).

### Schema places and their tests

- Four places: `table.sql` and `insert.sql` for a fresh install (run by `public/setup.php`; `insert.sql` seeds
  `_blocks`, `_categories` and `_forum` with positional rows), `table_update6_3.sql` with its idempotent procedures
  (`rencol`, `addcol`, `modcol`, `addidx`, `delidx`, `stopcol`, `stopnull`, `mkuseruniq`, …) for a 6.2 base, and the
  PHP steps of `public/update.php` for data.
- `SchemaUpdateValidationTest` checks one direction only: every column the update file declares matches `table.sql`,
  and every table it names exists. It compares no key, index, foreign key or collation.
- `InsertValidationTest` sees only inserts with a column list inside one literal — 27 of the 66 `INSERT INTO` of the
  code. Inserts with positional `VALUES` (`insert.sql`, the block save of `admin/modules/blocks.php`, the first
  administrator of `admin/index.php`, `setup.php`, the favourites of `core/user.php`) and several column-list inserts
  are invisible to it; a change of column order or type reaches them unchecked.
- `UpdateSiteTest`, through `tests/Support/install_probe.php`, is the real guard: it updates the 6.2 fixtures, compares
  the result with a fresh install — collation, indexes, foreign keys and checks included — and requires an empty
  `error_sql.log`. The fixture `update62early` already holds an account without an address.
- `getEditorRoomData()` in `core/helpers.php` maps editor stores to column types; `EditorRoomTest` checks the map
  against `table.sql`.
- `NodeModelTest` pins `KEY src (src(191))` of `_node_assets` and the `PRIMARY` `id` of `_node_categories`, which
  `docs/NODE.md` documents; `tests/Support/update_probe.php` rewrites `new Database(` of `setup.php`, and
  `UpdateSetupTest` pins it and the session mode of `table_update6_3.sql`.
- `/schema` shows no column drift on the stand; its seventeen extra tables are the old module tables of the migrated
  production base.

### Connection, zone, mode

- `Database::__construct()` opens PDO with `charset=utf8mb4` only. The zone is set afterwards by `core/system.php`,
  `SET LOCAL time_zone = date('P')` when `$conf['db']['sync']` is on (it is `1`, written by `setup.php` and
  `update.php`). Every site and panel request is therefore in the zone of PHP; connections that bypass
  `core/system.php` are not — `setup.php`, the 6.3 run of `update.php`, tools and probes — and there the stand server
  zone (UTC+1) differs from the site zone (`Europe/Berlin`).
- The panel switch `dbsync` of `admin/modules/config.php` is saved into `config/global.php` and read by nothing.
- No `sql_mode` and no connection collation are set; the stand's connection collation is the server default
  `utf8mb4_uca1400_ai_ci`, its mode `STRICT_TRANS_TABLES, ERROR_FOR_DIVISION_BY_ZERO, NO_AUTO_CREATE_USER,
  NO_ENGINE_SUBSTITUTION`.
- The code has eighteen `GROUP BY` (batch 0 counted them); the list of `admin/modules/referers.php` fails with error
  1055 under `ONLY_FULL_GROUP_BY` (tested).
- `DATETIME` is written by `NOW()` (about a hundred places, `NodeService` by `SELECT NOW()`) and by PHP `date()`: a
  forum post (`_forum.time`), the defaults of the `time` and `date` filters of `getVar()`, the prefill of
  `getTplAddDateTime()`, `getNodeFormDate()`, the panel's registration date, `update.php`. PHP compares the values
  against `time()` and against `date()` strings (the forum blocks, the lite `index.php`, the system clock of
  `core/system.php`).

### Collation

- Spelled in five places of the runtime: `{collate}` of `config/db.php` (and its cache `config/local.php`), the default
  of `setup.php`, the hardcoded `utf8mb4_unicode_ci` of `setUpdateSql()`, the forced `collate` that `setUpdateRun()`
  writes into `config/db.php` on every 6.3 run, and the explicit `COLLATE utf8mb4_bin` of `_maildead.email`; also
  `tools/node-profile.php`. The probes under `tests/Support/` and `DatabaseBatchTest` spell `utf8mb4_unicode_ci` on
  their own; the 6.2 fixtures under `tests/Fixtures/` spell it as the dumps they stand for and keep it.
- `CONVERT TO CHARACTER SET` turns every text column into the new set and collation, `ascii_bin` and `utf8mb4_bin`
  included — an `ascii` column even becomes `utf8mb4` (tested). A literal or a bound value compared with a column takes
  the column's collation; a join of two columns of different collations fails with 1267 (tested).

### Time columns

- Four forms: `DATETIME` in most tables; `BIGINT` unix in `_session.time`, `_oauth_temp.time`, `_rating_actors.last`,
  `_rating_targets.created`, `_rating_votes.created` and `annulled`, `_user_oauth.linked` and `lastlog`; `INT` unix in
  `_message.expire`; `VARCHAR(14)` in `_blocks.time`, `_blocks.expire`, `_rating.time` and `_users_temp.time`.
- The cleanups of `_rating` and `_users_temp` compare in SQL (`time < :past`), casting every row. `_blocks.expire`
  (`time()` plus days, set in the panel) and `_message.expire` are compared in PHP, the latter on the site and in the
  panel.
- `_blocks.time` is the refresh stamp of a block: written with `time()` for a refreshed block, but as `''` by the block
  save for every other block and by the seed, and as `'0'`; 30 of the 31 stand rows hold `''`. `''` into a `BIGINT`
  under strict mode fails with 1366 (tested).
- `update.php` reads `_rating.time` as a digit string by pattern, in the migration and in `setUpdateRatings()`, after
  the schema file has run.
- `_user_oauth.linked` is shown in the profile ("since") and both `linked` and `lastlog` in the panel list.
- `_favorites.time` is NULL in 210 of the 211 stand rows, from before the time was recorded; both inserts now write
  `NOW()`.

### Language

- `_users.lang` is `VARCHAR(255) DEFAULT 'russian'`; registration and OAuth write the locale code, and `getLangName()`
  knows only codes. `update.php` turns names into codes in the config (`setUpdateConfig()`), not in the rows. The stand
  holds 11 347 empty, 11 `ru` and 491 `russian` rows; the profile prints the raw word for the last. Every other `lang`
  column is `VARCHAR(30) DEFAULT ''`. `configsave()` of `admin/modules/lang.php` falls back to `russian` for
  `$conf['lang']['lang']`, which holds `ru`.

### Email

- `_users.email` and `_admins.email` are plain keys with a 191 prefix; `_users_temp.email` has no key and is read by the
  registration and by the panel's user save.
- Writers of `_users.email` without a reliable uniqueness check: the registration checks by a separate `SELECT`
  (`_users` and `_users_temp`) and the activation does not check again; the OAuth registration checks `_users` only;
  the settings save checks nothing. On the stand three addresses are held by more than one user; `_admins` and
  `_users_temp` hold none.
- Password recovery and unsubscribe update by address (`WHERE email = :email`), so with a duplicated address a reset
  rewrites the password of every account holding it.
- The OAuth registration writes an empty email when the provider sends none or an unverified or invalid one. A user
  without an address cannot save the settings, nor be saved in the panel (`checkemail()`). The stand holds no such row.
  `getMailAudience()` filters `email != ''`, the one query relying on the empty string; it excludes NULL as well.

### Keys

- Covered by the left part of another key: `_categories.modul`, `_favorites.uid`, `_forum.cid`, `_rating.mid`,
  `_search.word` (45 292 rows).
- Prefixes `(191)`: `_admins.email`, `_groups.name`, `_users.email`, `_search.word` (two keys), the unique
  `_user_oauth.provider_puid` (where uniqueness holds for the first 191 characters only), and `_node_assets.src`, a
  `VARCHAR(2048)` that no key can hold whole. The 3072-byte limit takes a whole `VARCHAR(255)`, as `_maildead.email`
  shows.
- `_node_categories` carries a surrogate `id` beside `UNIQUE (nid, cid)`; no code reads that `id`.
- `_node_relations` (both keys) and `_points` (`rid`) spell their foreign keys without `ON UPDATE RESTRICT`, the seven
  others spell it; the server reports `RESTRICT` for all ten.

### Text columns

- `ip` is `VARCHAR(45) ascii_bin` in `_comment` and `_nodes`, `utf8mb4` in nine columns of eight other tables.
- `modul` is `VARCHAR(25)` in `_session`, 50 in most tables, 60 in `_comment`, `ascii_bin` 50 in `_node_legacy`;
  `scope` of `_points` and `_rating_*` is `ascii_bin` 50. The stand values of `modul` in `_comment` and `_categories`
  are lowercase.
- `_session.uname` (`VARCHAR(40)`) holds the user name, the administrator name, a bot name or, for a guest, `getIp()`;
  `updateRefererTrack()` writes the same value into `_referer.name` (40). `getIp()` returns the first valid address of
  `REMOTE_ADDR`, then of the proxy headers, else `0.0.0.0`.
- `getAgent()` returns the agent escaped by `filterText()` (which turns `&` into `&amp;` and cuts nothing) into
  `_users.agent VARCHAR(255)`; `updateRefererTrack()` writes `filterText($request)` into `_referer.url` and
  `getReferer()` into `_referer.referer`, both `VARCHAR(2048)`, uncut. Under the strict mode of the stand an overlong
  value fails with 1406 (tested); 264 stand rows hold an agent of exactly 255 characters, cut by a non-strict server
  before.

### NULL

- Nullable without a meaning: the flags `_admins.super`, `_admins.smail`; the counter `_forum.comments`;
  `_forum.body`, `_newsletter.body`, `_users.sig`, `_users.occ`, `_users.origin`, `_admins.title`, `_admins.password`,
  `_forum.time`. On the stand none of the `_admins`, `_forum` and `_newsletter` ones is NULL; `_users.sig` is NULL in
  753 rows, `occ` and `origin` in 2 900, all from 6.2, and the code casts them to empty strings.
- `_users.viewmail` is NULL in 2 900 rows and for every user created by registration, OAuth, the first administrator
  of `admin/index.php` or `setup.php`; at creation only the panel form sets it. Its readers disagree about NULL: the
  public profile shows the address only `if ($adm || $view)`, so NULL hides it; `getSetupSwitch()` of the settings
  preselects "yes" for anything but `'0'` and `getAccountLamps()` reads `!== '0'`, so the cabinet says "shown", and
  the first save of the settings turns NULL into 1 and publishes the address; the panel's edit form checks no radio
  for NULL and saves 0. `getSetupSwitch()` serves four other switches of the settings too. The comment and forum
  author rows load the column and do not use it.
- `_users.grp` is a signed `INT` against the unsigned `_groups.id`; no row is negative or points at a missing group.

## Inventory

Batch 0, 2026-10-12: every item checked against every reader and writer in the tree and, where it says so, by a
query or `EXPLAIN` on the stand. The batches take their lists from here; a column, key or query not named here was
found right or needs nothing.

### Time columns

Machine timers, `BIGINT UNSIGNED NOT NULL DEFAULT 0` — none of them is shown to a person:

| Column | Today | What changes |
| --- | --- | --- |
| `_mail.locked` | `DATETIME NULL`, the claim of `Mail::getBatch()`, reaped by `deleteClaim()` against `DATE_SUB(NOW(), …)` | NULL "unclaimed" becomes 0, `NOW()` becomes `UNIX_TIMESTAMP()`, the reaper reads `locked > 0 AND locked < UNIX_TIMESTAMP() - :wait` |
| `_mail.ntime` | `DATETIME NOT NULL`, the next attempt, `ntime <= NOW()` in `getBatch()` | every `NOW()` and `DATE_ADD(NOW(), …)` on it becomes unix, 0 means "due at once"; the newsletter replay insert of `update.php` follows |
| `_node_publish.due` | `DATETIME NOT NULL`, derived from `published` or `NOW() + 60 s` | `UNIX_TIMESTAMP(published)` and `UNIX_TIMESTAMP() + 60`, readers `due <= UNIX_TIMESTAMP()`; `docs/NODE.md` follows |
| `_blocks.time` | `VARCHAR(14)`, the refresh stamp of `getRssBlock()` | `''` and `'0'` become 0 in the block save, the seed and the 6.2 path before the type changes |
| `_rating.time` | `VARCHAR(14)`, the vote window of `_voting` | the type; both readings in `update.php` keep working, since native prepares return an int and `preg_match()` takes it |
| `_session.time`, `_rating_actors.last` | `BIGINT UNSIGNED NOT NULL` without a default | `DEFAULT 0` |

`_oauth_temp.time` conforms. `_users_temp.time` leaves (see "Decisions").

Moments, `DATETIME`:

| Column | Form | What changes |
| --- | --- | --- |
| `_blocks.expire` | `NULL`, NULL = no expiry, as `_nodes.expires` | the panel sets it from days (`NOW() + INTERVAL :d DAY`) and shows the rest from it; the hidden round trip of the old value through `getVar(…, 'num')` in the block form goes; the expiry writes of 0 in the block render and in `adminblock` write NULL; the PHP checks against `time()` read the moment |
| `_message.expire` | the same | the same in `admin/modules/messages.php` and `core/user.php` |
| `_rating_votes.created` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | the vote insert writes `NOW()`, `admin/modules/ratings.php` formats it without `date()`; the clock stays the database server of `docs/RATINGS.md`, "The database clock", whose text follows |
| `_rating_votes.annulled` | `NULL`, NULL = not annulled | `deleteVoteRow()` and the panel test NULL instead of 0 |
| `_rating_targets.created` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | no reader; the spelling of every other `created` |
| `_user_oauth.linked` | `NOT NULL DEFAULT CURRENT_TIMESTAMP` | `Oauth::setLink()` and `movenet` write `NOW()`; the profile and the panel list read it without `date()` |
| `_user_oauth.lastlog` | `NULL`, NULL = never | 0 becomes NULL |
| `_users.lastvis` | `NULL`, NULL = never | the 6.3 sentinel `1970-01-01 00:00:01` (3 stand rows) becomes NULL; the deferred update of the session task reads `lastvis IS NULL OR …`; `getUserLastVisit()` and the `format_time()` readers of `modules/users/index.php` and the account panel list take NULL |
| `_comment.time`, `_privat.time` | `NOT NULL`, gaining `DEFAULT CURRENT_TIMESTAMP` | — |
| `_forum.time`, `_forum.ltime`, `_newsletter.time`, `_voting.time` | `NOT NULL DEFAULT CURRENT_TIMESTAMP`; no stand row is NULL | `table_update6_3.sql` touches neither `_newsletter` nor `_voting` times today and gains their steps |
| `_voting.enddate`, `_node_publish.published` | `NOT NULL` without a default: "now" means nothing for either, the insert supplies it | `_voting.enddate` gains its 6.2 step |

Already on their form: `_admins.regdate` and `lastvis`, `_users.regdate`, `_users_temp.regdate`, `_mail.time`,
`_maildead.time`, `_referer.time`, `_search.time`, `created` and `updated` of `_node_types`, `_nodes` and
`_node_assets`, `_node_relations.created`, `_points.created`, `_node_support.activity`; and the nullable
`_comment.shown` (not yet shown), `edited`, `deleted`, `_favorites.time` (not recorded), `_forum.etime` (never
edited), `_newsletter.endtime` (not finished), `_nodes.published` and `expires`, `_node_assets.reported` (no open
report), `_node_sync.checked`, `synced` and `due` (`due` is shown to the moderator, so a moment, unlike
`_node_publish.due`).

Two columns keep a form of their own, written beside them in `table.sql` and allowed by `SchemaConventionTest`:
`_node_sync.modified` stays `VARCHAR(100) ascii` — the HTTP `Last-Modified` validator that `Feed` echoes back as
`If-Modified-Since`, not a time of the site; `_users.birthday` stays `DATE NULL`, a calendar day.

Not times: `_blocks.refresh` and `_node_sync.refresh` (intervals), `_node_assets.duration`, `_newsletter.send`.

PHP compares a `DATETIME` with `time()` through `strtotime()` in `Comment::checkEditRight()` and `checkRules()`, the
edit window of `core/user.php`, the voting end of `core/system.php`, `modules/voting/index.php` and its admin, the
forum save (`ltime`), `modules/node/index.php` (the publication date) and the account view (years since
registration); with a `date('Y-m-d H:i:s')` string in `getForumTopicState()`, `blocks/forum.php`,
`blocks/center_forum.php` and the lite `index.php`. These stay correct once every connection runs in the zone of PHP
(batch 1). No SQL compares a unix column with `NOW()`.

The skew is in the data: `_users_temp.time − UNIX_TIMESTAMP(regdate)` read in the server zone is −3600 on the 18
stand rows (−3601 on those whose two stamps fell into different seconds), the same moment written by `time()` and by
`NOW()` on a site connection in `Europe/Berlin`.

The forum "new" mark is wrong today: `forum()` of `modules/forum/index.php` and `blocks/center_forum.php` take
`$ulast = (int)$uinfo['lastvis']`, the year of a `DATETIME`, and `getForumTopicState()` compares `ltime` with it as a
string, so every topic is new. Batch 3 compares `ltime` with `getUserLastVisit()` as a moment.

### Indexes

The stand's keys match `table.sql` exactly. Rows on the stand: `_search` 45 292, `_forum` 15 261, `_rating_targets`
15 159, `_users` 11 849, `_comment` 8 417, `_referer` 3 115, `_privat` 1 472, `_nodes` 1 267, every other table
under 1 000.

The five covered keys of "What exists today" are all there are. Keys no query's plan picks and nothing else needs —
they leave in batch 5: `_admins.lastvis`, `_blocks.title`, `_blocks.lang`, `_categories.modul_lang_status`,
`_comment.time`, `_comment.modul_cid_deleted`, `_forum.counter`, `_forum.cid_status`, `_groups.name`,
`_message.lang`, `_newsletter.time`, `_node_types.active`, `_nodes.queue`, `_node_assets.report`, `_points.target`,
`_rating.uid`, `_referer.uid`, `_session.ip`, `_users_temp.code`, `_voting.status`, `_voting.lang`. `docs/NODE.md`
names `_nodes.queue` for "moderation queue and state lists" and `_node_assets.report` for "admin queue of open
reports"; no query reads either way, and the reference follows. The stand cannot judge `_nodes.home`,
`_points.uid_action_created` and `_node_relations.edge` (empty or unselective tables); they stay, as does
`_node_relations.target`, which carries the foreign key of `rid`.

Queries without a fitting key, by `EXPLAIN` on the stand — the keys enter in batch 5:

| Query | Today | Key |
| --- | --- | --- |
| `getForumTopics()` of `core/system.php`, per request (forum blocks, lite `index.php`) | range `status`, 2 203 rows, filesort | `(pid, ltime)` |
| topic list of `forum()` and both counts of `updateForumSync()` | `intersect(pid, cid)` and filesort; the count scans 15 068 rows | `(cid, pid, status, ltime)`, in place of `cid` and `cid_status` |
| `getProfileLastView()`, `view()` of the account | `intersect(uid, pid)` and filesort | `(uid, pid)`, in place of `uid` |
| `getSearchForum()` | range `time` over 15 068 rows | `(pid, time)` |
| `getSearchsum()`, `toplist()` and the list by module of the search panel | full scan, temporary, filesort | `word_modul` on the whole `word`; `(modul, time)` in place of `modul` |
| subscribers of `getMailAudience()` | full scan of `_users` | `(newslet)` |
| `Comment::getAdminList()` and `getTotal()` with a module | range over 7 844 rows, count full scan | `(modul, status, deleted, time, id)`, in the place of `modul_cid_deleted` |
| `Comment::getUserCount()` | ref `uid`, temporary, filesort | `(uid, status, deleted, modul, cid)` |
| Node moderator list without a status | range `tree`, filesort | `(tid, pinned, published, id)` |
| `getNodeSupportList()` | filesort: `prio DESC` against ascending keys | `queue (state, prio DESC, activity, id)`, `admin (aid, state, prio DESC, activity, id)` |
| `getCategoryMap()`, `setCategories()`, the categories of `forum()`, per request | ref `modul`, filesort | `(modul, ordern)`, in place of `modul` and `modul_lang_status` |
| `getBlocks()`, per request | ref `status_position`, filesort | `(status, weight)`, in place of `status_position`, whose `bpos` part no query uses |

A descending key part is honoured by MySQL 8 and MariaDB 10.8+ and parsed but built ascending below 10.8, so the floor
holds. That `word_modul` on the whole `word` lets the panel group through the key is not confirmed (a read-only
inventory cannot build it); batch 5 checks it with `EXPLAIN` after the change.

Fixed at the query, not by a key: `view()` of the account matches `BINARY u.name = :uname`, a full scan of `_users`
on every profile view — `u.name = :uname AND BINARY u.name = :uname` keeps the exact match and takes `name`;
`deleteNodeCategory()` and `updateNodeCategory()` scan the whole `cat` key and gain `tid = :tid`. Left as they are:
the `OR` join of `groups()` (eleven groups, a panel page), the `LIKE '%…%'` searches of the panels, the sweeps of
`_session`, and the scans of `_privat` on a user delete.

### GROUP BY and the mode

Every `GROUP BY`, `DISTINCT` and aggregate of the code, the update file and `tools/` was run on the stand under the
target mode. Only the list of `referers()` fails (1055, all ten sorts), and MySQL 8 refuses it too: the groups count
their hits and name their newest `id`, which is joined back, so every row of the list is one real row (batch 1; a
`MAX()` per column, the first proposal, joined the user of one row with the name of another in 57 of 399 referer and
122 of 680 address groups of the stand). `groups()` keeps its full `GROUP BY`:
MariaDB knows no functional dependency and refuses `GROUP BY g.id` alone.

No code writes `'0000-00-00'`, `''` or 0 into a date, no `DEFAULT` spells a zero date, no stand row of 56 date
columns holds one; the `time` and `date` filters of `getVar()` never return `''`. `table_update6_3.sql` drops the
`NO_ZERO_*` flags for its session and restores them. `Backup` puts its session into an export state of its own —
`sql_mode = 'NO_AUTO_VALUE_ON_ZERO'`, zone `+00:00`, `SET NAMES utf8mb4` — and the dump file opens with the same
lines; both hand back the saved mode, zone and collation afterwards. Both keep working beside the fixed connection
state, and batch 1 checks that a backup run leaves the configured one in place.

### Uncut writes

The sources of an overlong value:

- the `name` filter of `getVar()` cuts to 25 characters and escapes after the cut: 25 become up to 150;
- `substr(…, 0, 25)` in bytes before `filterText()` — `updateSessionTrack()`, `addComment()` of `core/user.php`, the
  guest name of the forum `send()`, the OAuth create — splits a UTF-8 character (1366) and escapes after the cut;
  `finnewuser()` escapes a name the filter escaped already;
- `filterText()` and the censor lengthen a value; `title`, `text`, `url`, `word`, `var` and raw cut nothing;
  `checkemail()` has no length limit.

Writers that can exceed their column, all moving onto `filterCutText()` in batch 2:

- site: `_users.agent` (the session task, `activate()`, `setUserLogin()`, `oauthfinish()`); `_referer.url` and
  `referer`; `_session.uname` and `_referer.name`; `_users_temp.name` and `email` (`finnewuser()`); `email`,
  `website`, `occ`, `origin`, `interest` of `_users` (`savehome()`, 157 stand rows exactly at a width); the guest
  `_comment.name`; `_privat.title` (19 stand rows at 100); `_forum.title`, `name` and `lname`; `_search.word` (4 stand
  rows at 255); `_oauth_temp.redirect`; `_users.name` of the OAuth create;
- the first administrator of `admin/index.php`: `url`, `email` (no check at all) and `lang` from the cookie;
- the panel: `name`, `title`, `url`, `email`, `lang` of `admins.php`; `name`, `rank`, `email`, `avatar`, `occ`,
  `origin`, `interest`, `lang`, `website` of the account panel `addsave()`; `title`, `url`, `bkey`, `bpos`, `action`,
  `lang`, `bfile` of `blocks.php`; `modul`, `title`, `img`, `lang` of `categories.php` and of
  `NodeService::addNodeCategory()` / `updateNodeCategory()`; `title`, `lang` of `messages.php`; `title`, `apar` of
  `newsletter.php`; `name`, `rank` of `groups.php`; `modul`, `title`, `lang` of the voting panel; `word`, `modul` of the
  search panel;
- `setup.php`: `url` of the first administrator.

A fixed choice (`bpos`, `action`, `lang`, `modul`, `apar`) is checked against its known values rather than cut. Cut
or refused already: `Mail`, `Oauth::filterClaims()`, `Rating`, `Point`, the checks of `NodeService`,
`_session.url` and `modul`, the favourites and comment modules, the cuts of `update.php` (which can split an entity
and move onto the function too).

### Email

- `getSqlQuery()` catches `PDOException` and returns false, so a duplicate key never reaches a 500 — it fails the
  write silently, and most writers do not look at the result: `activate()` deletes the temporary row and reports the
  account active when the insert failed, `finnewuser()` mails a code for a row it did not write. Batch 5 makes every
  email writer read the result and answer "address taken".
- Writers of `_users.email`: `finnewuser()` → `activate()` (no second check), `oauthfinish()` create (writes `''`;
  checks `_users` only and answers a failed insert as `_NICKTAKEN`), `savehome()` (no uniqueness check), the account
  panel `addsave()` (checks `_users` and `_users_temp` by the first row and a case-sensitive PHP compare), the first
  administrator of `admin/index.php` (no check, `''` possible), `setup.php`. Of `_admins.email`: `admins.php` (first
  row, case-sensitive compare, `_users` unchecked), `admin/index.php`, `setup.php`. Of `_users_temp.email`:
  `finnewuser()`. `table_update6_3.sql` sets `_users.email = ''` where it is NULL — batch 5 reverses it.
- NULL breaks one reader: `filterTextHighlight(string …)` in the user list of the account panel — a TypeError and a
  500. `checkemail(string)` takes request values only. Every other reader already takes NULL as empty
  (`getMailAudience()`, `getUserMail()`, the profile, the forum and private message notices, `Mail::addQueue()`,
  templates, the panel `LIKE`).
- By address: `passmail()` reads the first account and rewrites the password of every account holding the address;
  `unsub()` unsubscribes them all. Nothing logs in by address.
- Stand: no `''` or NULL in the three columns; `_users` holds 3 duplicated addresses on 6 accounts, byte-identical, 5
  of them linked by OAuth; 386 addresses carry capitals; none is longer than 191. `_users_temp` and `_admins` hold no
  duplicate and share no address with `_users`.
- Other address columns: `_mail.email`, `_oauth_temp.email`, `_user_oauth.email` (may be `''`, duplicates are normal
  there) and `_maildead.email` (`utf8mb4_bin`). No query joins two tables on an address; every comparison binds a
  value, so the collations never meet.

### Module and scope

Every stored `modul` and `scope` on the stand is lowercase ASCII, as are the module folders and the Node type names
(the type name rule allows `[a-z0-9]` only). Values from a request that can arrive in another case and reach a query
or a row: `modul` of `categories.php` and of `getAdminCategoryList()`, `modul` of `comments.php` and its search by
module (`s.modul LIKE :find`, case-sensitive once binary), `cmod` of the search panel `clear()`, `modul` of its
`editsave()` and of the voting panel `save()`. Batch 2 checks each against the known module and type names before use
and lowercases the comment search term. The route `name` resolves through `$conf['modules']` case-sensitively.

The data steps of `update.php` compare lowercase constants with 6.2 rows stored case-insensitively; they run before the
conversion to `ascii_bin` or compare in lowercase. The `CAST(modul AS BINARY)` comparisons of `NodeService` — in
`getNodeRemains()` and in the type delete — become redundant and go.

### Language

Every `lang` column takes the domain of "Domains". The codes are the six files of `lang/` — `de`, `en`, `fr`, `pl`,
`ru`, `uk`; `_LOCALE` is never stored. `_voting.lang` holds `russian` in 4 stand rows beside the 491 of `_users`;
`update.php` maps both through the name map of `setUpdateConfig()`. Writers that store a code unchecked check it
against the `lang/` files: the first administrator (the cookie), `admins.php`, `blocks.php`, `categories.php`,
`messages.php`, the voting panel and the account panel.

## Design

### Domains

One spelling per kind of column, in both SQL files and for every table:

| Kind | Declaration |
| --- | --- |
| identifier, reference | `INT UNSIGNED NOT NULL` (a log that may outgrow it: `BIGINT UNSIGNED`) |
| counter | `INT UNSIGNED NOT NULL DEFAULT 0` |
| flag | `BOOLEAN NOT NULL DEFAULT 0` or `1` |
| moment a person reads or sets | `DATETIME`, `NOT NULL DEFAULT CURRENT_TIMESTAMP` when it always exists, `NULL` when NULL means "not yet", "never" or "not recorded" |
| machine timer | `BIGINT UNSIGNED NOT NULL DEFAULT 0` |
| ip | `VARCHAR(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT ''` |
| module, scope | `VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL` |
| user name | `VARCHAR(25)`; a column that may also hold an ip (`_session.uname`, `_referer.name`) is 45; the display name a provider sends (`_oauth_temp.uname`) is no user name and keeps 128 |
| email | `VARCHAR(255)`, keyed whole; a unique email column is `NULL` for "no address" and every writer turns `''` into NULL |
| language | `VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT ''` for every `lang` column: the code is the name of a `lang/<code>.php` file, 10 holds any BCP 47 tag up to `sr-Latn-RS` |
| text | `TEXT` / `MEDIUMTEXT NOT NULL`; the insert supplies it |
| long address | `VARCHAR(2048)`, keyed by prefix where a key is needed (`_node_assets.src`) |

A column leaves its domain only for a reason written beside it in this plan. NULL means "none" or "not recorded" and
nothing else.

### Connection

`Database::__construct()` runs once after connecting: `SET NAMES utf8mb4 COLLATE` the configured collation,
`time_zone` from `date('P')` (by name where the server refuses the offset, see "Decisions"), and a fixed `sql_mode` — `STRICT_TRANS_TABLES`, `ONLY_FULL_GROUP_BY`, `NO_ZERO_DATE`,
`NO_ZERO_IN_DATE`, `ERROR_FOR_DIVISION_BY_ZERO`, `NO_ENGINE_SUBSTITUTION` — so the code runs alike on every host and
on every path, and the stand, being MariaDB, catches what a MySQL host would refuse. The zone line of
`core/system.php`, `$conf['db']['sync']` and `dbsync` leave. `table_update6_3.sql` keeps setting its own mode for its
session. The constructor gains the collation, passed by `core/security.php` from `$conf['db']` and by `setup.php` and
`update.php` from the configuration they write or read; `setup.php` sets the zone the site is installed with before it
connects. During a 6.2 update the old module tables keep `utf8mb4_unicode_ci` while converted ones carry the new
collation, so batch 2 checks every query of the data steps of `update.php` that joins an old and a new table.

### Collation in one place

`{collate}` of `config/db.php` is the only source. `setup.php` writes `utf8mb4_unicode_520_ci` as its default;
`setUpdateRun()` stops forcing a collation into `config/db.php` and `setUpdateSql()` reads the configured one;
`tools/node-profile.php`, the probes and `DatabaseBatchTest` follow. The 6.2 conversion converts each table and sets
its `ascii_bin` and `utf8mb4_bin` columns back in the same statement, or converts column by column; batch 2 picks one.

### Writes fit their columns

A value from outside is cut to its column where it is stored — the agent in the writers of `_users.agent`, the address
and the referer in `updateRefererTrack()`, and every writer of "Inventory / Uncut writes" — so no write fails with
1406, while `getAgent()` and the hashes built on it stay as they are. The cut counts characters of the stored, already
escaped value and never splits an HTML entity. One function does it, `filterCutText(string $text, int $len): string`
in `core/security.php` beside `filterText()`; the `name` filter of `getVar()` escapes first and cuts through it, so 25
stays 25.

### Guard

`tests/SchemaConventionTest.php` reads `table.sql` and fails on a `VARCHAR` time, a `(191)` prefix on a column that a
key could hold whole, a nullable flag or counter, an `ip`, `modul` or `scope` outside its domain, a key covered by the
left part of another, and a `utf8mb4` collation spelled on a column other than `_maildead.email`. It keeps the
conventions after the plan is gone. Because `SchemaUpdateValidationTest` and `InsertValidationTest` see only part of
the schema, each batch also runs `UpdateSiteTest`.

## Batches

Every batch changes `table.sql`, `insert.sql` and `table_update6_3.sql` together, adds the data step of `update.php`
where rows must change, applies the same statements to the stand database and ends with `/schema` showing no drift.
Tests: `SchemaUpdateValidationTest`, `InsertValidationTest`, `EditorRoomTest`, `UpdateSiteTest` and the tests of the
code the batch touches by `--filter`; one full phpunit at the end of the batch. A positional insert is checked by
hand, since no test sees it.

### Batch 0 — inventory

Done 2026-10-12; the result is "Inventory", the owner's answers stand under "Decisions".

No code. Into this plan, each item confirmed against every reader and writer:

- every time column classified by the rule of "Decisions", `_mail.locked`, `_mail.ntime`, `_node_publish.due`,
  `_blocks.expire` and `_message.expire` included;
- every index with the query it serves, by `EXPLAIN` on the stand, and every list, count and cleanup query without
  one;
- every `GROUP BY` under `ONLY_FULL_GROUP_BY`, beyond the referer list already known;
- every write of an outside value without a cut;
- every writer and reader of `_users.email`, `_admins.email` and `_users_temp.email` for the NULL of "no address", the
  readers that compare with `''`, and the paths that update by address;
- the owner's confirmation of the `viewmail` default;
- the case of every request value compared with `modul` and `scope` before they turn binary;
- the width of `lang`.

### Batch 1 — connection

The contract of "Connection"; the zone line of `core/system.php`, `$conf['db']['sync']` and `dbsync` out; the
`GROUP BY` of the referer list and whatever batch 0 found fixed at the query; `update_probe.php` and `UpdateSetupTest`
follow the constructor. Tests: a probe that `NOW()` equals `date('Y-m-d H:i:s')` within a second on a connection of
`setup.php` and of `update.php`, and that the session mode and collation are the configured ones; the whole suite; a
crawl of the site and the panel on the stand with `error_sql.log` empty after it.

### Batch 2 — text columns

The collation in one place and `utf8mb4_unicode_520_ci` everywhere; `ip`, `modul`, `scope`, user names and `lang` on
their domains; `_session.uname` and `_referer.name` at 45; `filterCutText()` and the cuts of "Writes fit their
columns" in every writer of "Inventory / Uncut writes", the fixed choices checked against their values; the request
values of "Inventory / Module and scope" checked before they meet a binary column; the `lang` writers checked against
the `lang/` files. The 6.2 path
converts each table without losing its `ascii_bin` and `utf8mb4_bin` columns, and the joins of old and new tables in
`update.php` are checked. Tests: `SchemaConventionTest` for the text part; a probe that 😀 and 😃 are two names and
that `_maildead.email` stays case-sensitive; an agent longer than the column is stored cut and still blocked by its
full hash; a name of 25 characters with `&` is stored as 25 characters and no entity is split; `UpdateSiteTest`.

### Batch 3 — time

The `VARCHAR(14)` columns out, `_users_temp.time` dropped with its cleanup on `regdate`; each time column on the form
of "Inventory / Time columns"; the readers and writers follow (`time()`
against a timer, `NOW()` or a bound `DATETIME` against a moment, `0` becoming `NULL` where "never" was spelled 0). The
block save and the seed stop writing `''` into `_blocks.time`; the 6.2 path turns `''` and `'0'` into 0 before the
type changes and converts the values (`FROM_UNIXTIME` for a moment, the number as is for a timer); the readings of
`_rating.time` in `update.php` keep working on the new type. The forum "new" mark compares `ltime` with
`getUserLastVisit()`. Tests: the cleanup of `_users_temp` and `_rating`, the refresh and the expiry of a block, the
expiry of a message, the session sweep, the mail claim and its reaper, the publication queue, a topic newer and one
older than the last visit, `UpdateSiteTest`.

### Batch 4 — NULL, defaults, types

The nullable columns of "What exists today" on their domains, their NULL rows turned into the empty value the code
already reads them as, each insert supplying what it now must; `_users.viewmail` NULL → 0 with `DEFAULT 0`, the
settings switch for it, the cabinet lamp and the panel form reading the flag as the
profile does, without changing the other switches of `getSetupSwitch()`; `_favorites.time` left nullable;
`_users.grp` unsigned; `_users.lang` on its domain, the 6.2 rows turned from names into codes in `update.php` by the
map `setUpdateConfig()` already has, `_voting.lang` likewise, and the default of `configsave()` in
`admin/modules/lang.php` off `russian`; `_users.birthday` stored NULL for an empty field, the false birthdays of
"Decisions" set NULL on the 6.2 path and on the stand. Tests: the profile showing the language name for a migrated
row; a profile saved with an empty birthday stores NULL; for a user whose `viewmail` was NULL the address stays
hidden, the switch and the lamp say so, and saving the settings untouched keeps it hidden; `UpdateSiteTest`.

### Batch 5 — keys

The tables added after batch 0 take the same pass: `_login_log` of `docs/9-ADMIN-LOG-2026.md` (roadmap steps 58–62)
comes before this batch so its keys are checked here and its 6.2 step in the rehearsal of batch 6.

`UNIQUE` on `_users.email`, `_admins.email` and `_users_temp.email`, a missing address stored as NULL: every writer
batch 0 listed turns `''` into NULL, every reader takes NULL as no address; the registration, the activation, the OAuth
registration, the settings save and the panel answer a duplicate key as "address taken", never as a 500 nor as the
silent false of `getSqlQuery()`: each email writer of "Inventory / Email" reads the result, and `activate()` keeps the
temporary row when the insert fails; the user list of the account panel takes a NULL address. The 6.2 path drops its
`email = ''` step for NULL, turns empty addresses into NULL and then checks for duplicates in a PHP step of
`update.php` before the key is added, listing every duplicated address with its accounts in the report rows and
stopping the run — a `SIGNAL` of a procedure like `stopcol` carries one short message, too little for the list. The
five covered indexes and every `(191)` prefix on a column a key can hold whole out, `_node_assets.src` keeping its
prefix; the unused keys of "Inventory / Indexes" out and its missing keys in, each checked by `EXPLAIN` of its query
after the change; the queries it fixes without a key rewritten; `_node_categories` on `PRIMARY KEY (nid, cid)`
without its `id`, `NodeModelTest` and `docs/NODE.md` following; `_node_relations` and `_points` spelling
`ON UPDATE RESTRICT`. Tests: `SchemaConventionTest` whole; a
parallel double registration ends in one account; two OAuth users without an address both register; a settings save
with a taken address is refused; the update probe with a duplicate address stops and names it; `UpdateSiteTest`.

### Batch 6 — rehearsal

`update.php` over a fresh 6.2 dump the owner provides, as the FILES rehearsal did: the stop on duplicates, then the
run to the end, `/schema` against the result, the language names gone, the collation and the domains in place;
`UpdateSiteTest`; a fresh install by `setup.php` on MySQL 8.4 and on MariaDB 10.11 if the owner has them, else on the
stand server, said so in the report.

Done 2026-10-13, on a clean 6.2 by the owner's decision "Any 6.2 site migrates". `table_update6_3.sql` renames the
seven source tables again ("Batch X"); `UpdateSiteTest` runs the second stage on its fixture and reads the two news
and the file it makes, and fails without the renames (checked by taking them out). The rehearsal is
`tests/Support/install_probe.php` in its update mode over `table.sql` and `insert.sql` of
`slaed-old.loc/public/setup/sql` with prefix `slaed`, one administrator and three accounts added (the 6.2 seed has
none; languages `russian`, `english`, `ukrainian`, a NULL and a set `viewmail`, a false and a real birthday) and the
real `config_*.php` of `slaed-old.loc`, on MariaDB 11.8, MySQL 8.4.6 and MariaDB 10.11.14 alike: two accounts on one
address stop the run before the schema file with both named; three runs without a failed statement, all marks and
manifests; the structure equals a clean installation in `information_schema`, every column with its type, NULL,
default and collation (the `/schema` comparison); `utf8mb4_unicode_520_ci` in `config/db.php` without the keys of
6.2; the languages `ru`, `en`, `uk`, the false birthday NULL; the second stage creates `news`, `faq`, `help`, `links`
and `files`, adjusts `content`, and carries the three news of the seed; PHP, SQL and site logs hold no error. A second
run on MariaDB 11.8 added one row to each of the other six tables in its 6.2 columns, a reply to the help request and a
comment on the page and on the link: all nine rows became materials of their seven types with intro and body in place
(`pages` in `docs`), the two comments stayed on their materials, the reply became a comment of the request, and the
link and the file became resources of the roles `link` and `download`, the 5 hits of the link kept. The fresh
installation, red in `NodeProfileTest` since 2026-10-04 with its root cause open, passes 14 of 14 on the three servers
after the fix of "The installer starts its session once". `UpdateSiteTest` passes on MariaDB 11.8 and 10.11; MySQL
8.4 refuses to load its fixture (`ERROR 1067 Invalid default value for 'user_regdate'`), a raw MariaDB dump with zero
dates, which is a property of the fixture, not of `update.php`.

### Batch 7 — reference

The schema conventions — the domains, the time rule, the connection contract, the collation and the path to
`0900_ai_ci` — into the schema part of `docs/NODE.md`; the version lines of `README.md`, `UPGRADING.md` and
`docs/ARCHITECTURE.md` with the recommended versions; the change in `docs/VERSIONS.md`. Deletes this file.

## Out of scope

- Renaming columns to one vocabulary (`modul` / `scope`, `time` / `created`, `typ`, `ordern`): every name is read
  across the code; it is a refactor of its own.
- Folding `_rating` into `_rating_*`.
- Raising the version floor.
- Widening `InsertValidationTest` to positional inserts: worth its own task; until then batches check them by hand.
- The old module tables in the stand database: they are the input of the 6.2 migration.
