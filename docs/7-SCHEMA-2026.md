# Schema 2026

Work plan for the database schema of release 8.0 — `storage/update/sql/table.sql` with its seed `insert.sql`, its 6.2
path `storage/update/sql/table_update6_3.sql` with the steps of `public/update.php`, and the contract of the
connection — brought to one set of conventions before the release, while a change still costs one edit instead of a
migration on every installed site.

Status: planned, nothing implemented. The order of the batches is kept in `docs/ROADMAP-2026.md`. Update this line
as batches land.

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
  takes, to confirm in batch 0.
- **`_favorites.time` stays nullable**, NULL meaning "not recorded" for the 6.2 rows.
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
- The code has thirteen `GROUP BY`; the list of `admin/modules/referers.php` fails with error 1055 under
  `ONLY_FULL_GROUP_BY` (tested).
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
| language | one width and set for every `lang` column, chosen in batch 0 from the codes in use, `DEFAULT ''` |
| text | `TEXT` / `MEDIUMTEXT NOT NULL`; the insert supplies it |
| long address | `VARCHAR(2048)`, keyed by prefix where a key is needed (`_node_assets.src`) |

A column leaves its domain only for a reason written beside it in this plan. NULL means "none" or "not recorded" and
nothing else.

### Connection

`Database::__construct()` runs once after connecting: `SET NAMES utf8mb4 COLLATE` the configured collation,
`time_zone` from `date('P')`, and a fixed `sql_mode` — `STRICT_TRANS_TABLES`, `ONLY_FULL_GROUP_BY`, `NO_ZERO_DATE`,
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
and the referer in `updateRefererTrack()` — so no write fails with 1406, while `getAgent()` and the hashes built on it
stay as they are. The cut counts characters of the stored, already escaped value and never splits an HTML entity.

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
their domains; `_session.uname` and `_referer.name` at 45; the cuts of "Writes fit their columns". The 6.2 path
converts each table without losing its `ascii_bin` and `utf8mb4_bin` columns, and the joins of old and new tables in
`update.php` are checked. Tests: `SchemaConventionTest` for the text part; a probe that 😀 and 😃 are two names and
that `_maildead.email` stays case-sensitive; an agent longer than the column is stored cut and still blocked by its
full hash; `UpdateSiteTest`.

### Batch 3 — time

The `VARCHAR(14)` columns out; each time column on the form batch 0 gave it; the readers and writers follow (`time()`
against a timer, `NOW()` or a bound `DATETIME` against a moment, `0` becoming `NULL` where "never" was spelled 0). The
block save and the seed stop writing `''` into `_blocks.time`; the 6.2 path turns `''` and `'0'` into 0 before the
type changes and converts the values (`FROM_UNIXTIME` for a moment, the number as is for a timer); the readings of
`_rating.time` in `update.php` keep working on the new type. Tests: the cleanup of `_users_temp` and `_rating`, the
refresh and the expiry of a block, the expiry of a message, the session sweep, `UpdateSiteTest`.

### Batch 4 — NULL, defaults, types

The nullable columns of "What exists today" on their domains, their NULL rows turned into the empty value the code
already reads them as, each insert supplying what it now must; `_users.viewmail` NULL → 0 with `DEFAULT 0` (or as the
owner decided in batch 0), the settings switch for it, the cabinet lamp and the panel form reading the flag as the
profile does, without changing the other switches of `getSetupSwitch()`; `_favorites.time` left nullable;
`_users.grp` unsigned; `_users.lang` on its domain, the 6.2 rows turned from names into codes in `update.php` by the
map `setUpdateConfig()` already has, and the default of `configsave()` in `admin/modules/lang.php` off `russian`.
Tests: the profile showing the language name for a migrated row; for a user whose `viewmail` was NULL the address stays
hidden, the switch and the lamp say so, and saving the settings untouched keeps it hidden; `UpdateSiteTest`.

### Batch 5 — keys

`UNIQUE` on `_users.email`, `_admins.email` and `_users_temp.email`, a missing address stored as NULL: every writer
batch 0 listed turns `''` into NULL, every reader takes NULL as no address; the registration, the activation, the OAuth
registration, the settings save and the panel answer a duplicate key as "address taken", never as a 500. The 6.2 path
turns empty addresses into NULL and then checks for duplicates in a PHP step of `update.php` before the key is added,
listing every duplicated address with its accounts in the report rows and stopping the run — a `SIGNAL` of a
procedure like `stopcol` carries one short message, too little for the list. The five covered indexes and every
`(191)` prefix on a column a key can hold whole out, `_node_assets.src` keeping its prefix; the indexes batch 0 found
missing in; `_node_categories` on `PRIMARY KEY (nid, cid)` without its `id`, `NodeModelTest` and `docs/NODE.md`
following; `_node_relations` and `_points` spelling `ON UPDATE RESTRICT`. Tests: `SchemaConventionTest` whole; a
parallel double registration ends in one account; two OAuth users without an address both register; a settings save
with a taken address is refused; the update probe with a duplicate address stops and names it; `UpdateSiteTest`.

### Batch 6 — rehearsal

`update.php` over a fresh 6.2 dump the owner provides, as the FILES rehearsal did: the stop on duplicates, then the
run to the end, `/schema` against the result, the language names gone, the collation and the domains in place;
`UpdateSiteTest`; a fresh install by `setup.php` on MySQL 8.4 and on MariaDB 10.11 if the owner has them, else on the
stand server, said so in the report.

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
