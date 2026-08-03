# Database Backup

Status: **complete**. All seven batches are done as of 2026-08-03. `Backup` is
the only database export implementation, the scheduler runs on an OS lock behind
a POST-only boundary, consumers and documentation match what the code does, and
the artifact the live site produces has been restored into a disposable database
and verified row by row. The privilege matrix was confirmed against MySQL 8.4.4,
MariaDB 11.7.2, and MariaDB 10.4.32; the MySQL-only rows of that matrix remain
covered by unit tests over fixture grants, which is the one gap worth naming.

Replace `addBackupTask()` with one final `Backup` class that creates a verified,
restorable dump **of the selected base tables**. Migrate the scheduler in the
same change and delete the procedural implementation. No wrapper or intermediate
legacy algorithm.

The wording is deliberate: this is not a whole-database backup. Views, triggers,
events, and routines are never serialized, so what the artifact restores is the
declared table scope and nothing else. Every completeness claim in this document
is bounded by that scope.

Source line numbers describe the working tree at 2026-08-03 (`8438eac8`).
Resolve the named symbols again before editing because adjacent work moves them.

## Scope

In scope:

- schema and data export for base tables;
- verified compression with an explicit algorithm;
- artifact retention by count, including legacy-named archives;
- deploy-safe delivery of the new default settings;
- scheduler authorization, serialization, crash-state handling, and artifact
  identity;
- the monitor and admin-list consumers of that state;
- automated integrity tests and a real disposable restore test.

Out of scope:

- filesystem backup;
- servers below MySQL 8.0 or MariaDB 10.4 (D2);
- serializing views, triggers, events, or routines;
- remote storage, encryption, and admin restore UI;
- editing job settings through the admin UI (system jobs stay config-driven);
- killing a job that exceeded its budget (see D9 — there is no watchdog).

A future filesystem backup must be a separate `FileBackup` class. If both jobs
later need orchestration, add a coordinator; do not combine storage traversal
and SQL export in `Backup`.

## Decisions

These are settled. Do not re-open them during implementation.

**D1 — Completeness is explicit and strict by default.** A dump that silently
drops triggers restores into a database that *behaves* differently, which is the
one failure mode a backup must never have. So an unsupported object in the
selected scope fails the run unless the operator has explicitly accepted an
incomplete artifact via `allow_incomplete = 1`.

That switch exists because the opposite absolute is also a real failure mode:
the 6.3 helper procedures are dropped at the very end of the migration
(`setup/sql/table_update6_3.sql:1702`, `:1812`), so without an escape hatch an
interrupted upgrade would disable every nightly run precisely when backups
matter most.

| `allow_incomplete` | Views / events / routines | Triggers |
|---|---|---|
| `0` (default) | fail before output | fail before output |
| `1` | reported in `unsupported`, run succeeds | reported in `unsupported`, run succeeds |

Only `0` and `1` are accepted; any other value fails preflight. When the run
succeeds with `1`, the result carries `complete => false` and the scheduler
message names every skipped object. The restore test compares only the declared
supported scope — base tables — and must never assert on object classes this
class does not serialize.

Hard failure, regardless of the switch, is reserved for conditions that make the
artifact *wrong*:

- a foreign key that points outside the selected scope;
- an unsatisfied privilege contract (D2);
- DDL that changed between the pre- and post-export fingerprint;
- an explicitly requested compressor that is unavailable (checked in preflight,
  see D8);
- any unchecked database call or stream write.

Foreign keys *inside* the scope are supported: their definitions live in
`CREATE TABLE` and restore correctly while foreign-key checks are disabled.

**D2 — Absence of an object class must be proven, never inferred.** This is what
makes strict D1 meaningful. `INFORMATION_SCHEMA` shows only objects the
connected account may see, so an empty `TRIGGERS` result is indistinguishable
between "no triggers exist" and "this account cannot see triggers". Reading it
as the former would let strict mode certify a dump that silently lost triggers —
exactly the failure D1 exists to prevent.

Preflight therefore establishes visibility per object class *before* trusting
any catalog query. A bare `SHOW GRANTS FOR CURRENT_USER()` is not sufficient
input for that: on MySQL it omits privileges granted through roles unless those
roles are named in `USING`, the set of active roles is session-dependent, and
partial revokes appear as separate `REVOKE` lines that must be subtracted from
the granted set; on MariaDB the `USING` clause does not exist and role grants
have to be read separately. Reading only the plain output would
under-report privileges and fail runs that should succeed — or, worse, be
"fixed" later by relaxing the check into the inference this decision exists to
forbid.

Supported servers are **MySQL 8.0+ and MariaDB 10.4+**. Both have roles, which
keeps the resolver at two branches instead of growing role-less sub-branches for
servers that predate them. Preflight reads the version first and refuses to run
below either minimum with an explicit message: a resolver that cannot correctly
evaluate a server must say so, not return an answer it has no basis for. Within
MySQL, `partial_revokes` (8.0.16+) and `SHOW_ROUTINE` (8.0.20+) are handled as
version checks inside the one branch.

Effective-privilege resolution therefore runs as an explicit algorithm with two
vendor branches. They are not a stylistic difference: MariaDB has no
`SHOW GRANTS ... USING` syntax at all, so a single implementation would fail
outright there.

Common steps:

1. resolve vendor and version from `SELECT VERSION()`;
2. read `CURRENT_USER()` and `CURRENT_ROLE()`;
3. collect grant lines per the vendor branch below;
4. build the effective set by subtracting `REVOKE` lines from `GRANT` lines,
   keeping the scope of each entry: global (`*.*`), schema (`db.*`), or object;
5. treat `ALL PRIVILEGES` at a scope as satisfying every requirement at or below
   that scope.

MySQL branch — confirmed on 8.4.4:

- `CURRENT_ROLE()` returns the string `NONE` when nothing is active, otherwise
  `` `role`@`host` ``;
- role-derived privileges are materialized with
  `SHOW GRANTS FOR CURRENT_USER() USING <roles>`, naming **only the active
  roles** from `CURRENT_ROLE()`. This is a correctness requirement, not a
  preference: `USING` reports what the named role *would* grant, whether or not
  it is active. The spike account held its privileges solely through an inactive
  role — `USING` listed the full grant while the session could see nothing at
  all, not even base tables. Passing every granted role would therefore
  over-report privileges and hand the resolver exactly the false confidence this
  contract exists to prevent. When `CURRENT_ROLE()` is `NONE`, no `USING` call
  is made;
- `@@GLOBAL.mandatory_roles` is not an input: it says what is granted to every
  account, not what is active in this session;
- when `@@partial_revokes` is `ON` (8.0.16+), `SHOW GRANTS` emits standalone
  `REVOKE ... ON \`db\`.* FROM ...` lines that must be subtracted. The spike
  account had `GRANT SELECT, TRIGGER ON *.*` with a schema-level revoke and
  could see nothing in that schema; a resolver that ignored the `REVOKE` line
  would have accepted the empty catalog as proof of absence.

MariaDB branch — confirmed on 11.7.2 and 10.4.32:

- there is no `USING` clause on either version: `SHOW GRANTS FOR <user> USING
  <role>` fails with `ERROR 1064`, so the MySQL branch cannot merely be reused
  with a version check;
- `CURRENT_ROLE()` returns `NULL` when nothing is active, otherwise the bare
  role name without `@host`, and at most one role is active at a time;
- issue `SHOW GRANTS FOR CURRENT_USER()`, then a separate
  `SHOW GRANTS FOR '<active role>'`. That second call **already expands nested
  granted roles** — the spike's parent role emitted its child role's `TRIGGER`
  grant — so no manual recursion is needed. Because the output then contains
  lines addressed to several grantees, the parser must accept every returned
  line and must not filter by the requested role name;
- there are no partial revokes to subtract.

MariaDB `SHOW GRANTS` output embeds `IDENTIFIED BY PASSWORD '<hash>'`. Grant
output must never be written to logs or to a result message.

Required visibility, evaluated against that set — a row is satisfied by **any**
of its alternatives. Every cell below was verified by creating one object of
each class and reading `information_schema` as accounts holding exactly one
candidate privilege:

| Object class | Satisfied by |
|---|---|
| base tables, columns, indexes | `SELECT`, global or on the schema |
| views | `SELECT`, global or on the schema; reading the definition additionally needs `SHOW VIEW` |
| triggers | `TRIGGER`, global or on the schema. Global `SELECT` does **not** suffice |
| events | `EVENT`, global or on the schema. Global `SELECT` does **not** suffice |
| routines | global `SELECT`; or `SHOW_ROUTINE` (a **global dynamic** privilege, MySQL 8.0.20+, not a schema grant); or `CREATE ROUTINE`, `ALTER ROUTINE`, or `EXECUTE` in scope. MariaDB has no `SHOW_ROUTINE` and follows the remaining alternatives |

The two "does not suffice" cells are the load-bearing ones. An account holding
only schema `SELECT` listed the table and the view but reported **zero**
triggers, zero events, and zero routines while all three existed — the exact
inference this decision forbids. Global `SELECT` behaved identically for
triggers and events on both vendors.

Being a routine's definer is deliberately absent from that table, and the spike
shows why concretely: an account with only schema `SELECT` saw exactly one
routine — the one it owned by definer — where two existed. Definer visibility
therefore produces a *non-empty* catalog that still hides other definers'
routines, so "I can see at least one object" can never support the scope-wide
claim that none exist. The same reasoning bars any future shortcut of that
shape.

A class whose visibility cannot be established fails preflight with a message
naming the class and the missing alternatives. It is never treated as an empty
catalog.

This matrix was the implementation's input contract, so it was settled by a
vendor spike before any code was written (batch 0, completed 2026-08-03 against
MySQL 8.4.4, MariaDB 11.7.2, and MariaDB 10.4.32). Everything stated above is
observed behaviour, not documentation summary.

**D3 — A table without a primary key is exported, not rejected**, and verified
differently. Deterministic row order is a diff convenience, not a correctness
property; under a consistent snapshot an unordered read is equally consistent.
Such tables are streamed unordered and listed in `unordered` in the result.

Their restore verification cannot use a PK-ordered checksum, so it compares the
**multiset** of canonical row hashes: hash each row canonically, group by hash,
and compare `hash => occurrence count` maps plus the total row count between
source and restored database. Duplicate rows are therefore preserved as a
verified property rather than collapsed. PK-bearing tables keep the ordered
checksum.

**D4 — `Backup` chooses the compressor itself and never passes `auto` down.**
`addCompress()` treats "auto with no compressor available" as a hard error
(`core/system.php:2370`), so the fallback the pipeline needs does not exist at
that layer. `Backup` resolves one concrete algorithm during preflight and passes
`zip`, `gz`, or `bz2` explicitly.

**D5 — One canonical artifact name, two recognized patterns.** The final name is

```
<stem>_<Y-m-d_H-i-s>_<8 hex>.sql            uncompressed
<stem>_<Y-m-d_H-i-s>_<8 hex>.sql.<ext>      ext in {zip, gz, bz2}
```

`.sql` is always present, including for zip, so one suffix rule covers every
format. `<stem>` is the sanitized current database name — the same
`preg_replace('/[^a-zA-Z0-9_-]/', '_', ...)` transform the legacy code used
(`core/system.php:868`).

Staging filenames are deliberately decoupled from the final name: the dump is
always `dump.sql` inside the staging directory and the candidate is always
`dump.part.<ext>`. That keeps the zip inner entry a constant — `addCompress()`
stores `basename($src)` (`core/system.php:2417`) — and leaves the final name
entirely under this class's control instead of `addCompress()`'s naming rules.

The legacy pattern `<stem>_<Y-m-d_H-i-s>.<ext>` — the 36 existing archives — is
recognized for retention only, never produced.

**D6 — Publication is atomic and never replaces.** `rename()` silently
overwrites an existing destination, and a `file_exists()` guard before it is a
TOCTOU race. The staging `fopen($path, 'xb')` reserves nothing in the backup
root, because the staging directory is unique by construction.

So the artifact is published with `link($candidate, $final)`, which fails rather
than replacing when `$final` exists, followed by `unlink($candidate)`. On
collision, regenerate the hex suffix and retry. Both paths are inside
`BACKUP_DIR`, so they are on one filesystem by construction.

Preflight probes `link()` the same way publication will use it: source inside
staging, destination directly in the backup root, then both removed. A pair
entirely inside staging would prove nothing about creating a link in the root,
which is where it matters. If `link()` is unsupported, the run fails there with
an explicit message; it does not fall back to `rename()`. An unsafe publication
path is not an acceptable degradation, and a filesystem that cannot hard-link is
a deployment problem to surface, not to work around.

The 8-hex suffix is obscurity and collision *reduction*, never a guarantee — the
guarantee is `link()`. Treat the artifact name as public:
`storage/backup/.htaccess` is `deny from all`, which only Apache reads, so on
nginx, LiteSpeed, or Caddy the dumps are reachable by anyone who guesses a name.
That is a deployment requirement, documented in `admin/info/scheduler/ru.md`:
non-Apache deployments must deny `/storage/` at the server level. Create staging
directories `0700` and artifacts `0600`.

**D7 — Retention ships in this change, disabled by default, and covers legacy
archives.** The backup directory already holds 36 archives accumulated since
2026-06-03 and grows every night, and `admin/modules/monitor.php:919` surfaces
its size. Retention that only recognized the new naming scheme would not touch a
single one of them.

`keep` is the number of newest artifacts to retain; `0` keeps everything and is
the default, so the first run after the upgrade cannot silently delete an
operator's history. Candidates are files in the backup root matching either
pattern from D5 for the current `<stem>`. Both classes age out together.
Ordering is by the timestamp parsed out of the filename, which is stable across
file copies and restores; `mtime` breaks ties and is never the primary key.
Anything that does not match — foreign files, `index.html`, `.htaccess`, staging
directories, artifacts of another database — is invisible to retention.

**D8 — Everything that can fail without touching the database fails in
preflight**, in exactly this order, because each step depends on the previous
one:

1. settings validation and pattern compilation;
2. compressor resolution and availability;
3. backup-root existence and writability;
4. staging directory creation;
5. `link()` probe: source in staging, destination in the backup root, both
   removed afterwards (D6);
6. the privilege contract (D2).

Only then does the export transaction open. The acceptance rule "an unavailable
explicit compressor fails before output" is only true if the check runs here.

**D9 — Failure after successful publication does not un-succeed the run.** Once
the artifact is linked into place and its checksum verified, it is a valid
backup. If retention or staging cleanup then fails, the artifact stays, the
status remains `success`, and the reason is appended to `message` and to a
`warnings` array in `extra`. Deleting a verified backup because a cleanup step
failed would be strictly worse than leaving stale files behind. Failure *before*
publication always removes staging and returns `failed`.

**D10 — Lock protocol and state machine.** The OS `flock` is the only authority
on whether a job runs. JSON is status. Both must be read together, because a
crashed process releases its `flock` but leaves `running = 1` behind.

| flock | JSON `running` | Meaning | Action |
|---|---|---|---|
| held | 1 | live run | not due, not startable, unlock refused |
| held | 0 | JSON lost or repaired early | not due, not startable, unlock refused |
| free | 1 | **crashed run** | reconcile, then startable |
| free | 0 | idle | normal due check |

Reading that table without holding the lock is a race: another runner can
acquire the lock between the observation and the write, after which the late
reader would reset a live process's state. So every transition follows one
protocol, and there is no other way to mutate job state:

1. open the job lock file and attempt `flock(LOCK_EX | LOCK_NB)`;
2. on failure, the job is live — report, change nothing, return;
3. on success, **re-read the JSON while still holding the lock**;
4. if `running = 1`, this is a crashed run: reconcile;
5. then either start the job and hold the handle for its whole execution, or —
   for unlock — write the reconciled state and release.

Reconciliation is idempotent: `last_status = 'crashed'`, `running = 0`,
`started_at = 0`, `fail_count++`, `last_error` set to a fixed English marker.
`last_run` is **not** rewritten: `setSchedulerStart()` already stamped it at
acquisition (`core/system.php:398`) and `getSchedulerPlannedTime()` computes the
next slot from it (`core/system.php:264`, via `core/system.php:347`). Leaving it
alone means a crashed job resumes at its next scheduled slot instead of retrying
in a crash loop. There is no automatic retry inside a scheduling period; that is
the policy, not an oversight.

Read-only consumers — the admin list, the monitor — never write. They derive the
displayed status from the same two inputs and show it without repairing
anything; repair happens on the next protocol-following call.

Unlock therefore never "frees" anything. It refuses while the OS lock is held,
and otherwise performs only the reconciliation above. It never deletes the lock
file.

`lock_timeout` is **diagnostic only**. Nothing in SLAED can terminate a running
PHP process, so calling it an execution budget would promise enforcement that
does not exist. An over-budget job that still holds its `flock` keeps running
and keeps blocking its own restart, which is the correct outcome. A real budget
needs an external watchdog and is out of scope. Both the UI and
`admin/info/scheduler/ru.md:72` currently promise forced termination and must be
corrected (see "Status presentation").

**D11 — Session and buffering control goes through `$db->sqlconnid`.** The
`Database` facade exposes no unbuffered-query or transaction-state API, but the
PDO handle is public (`core/classes/pdo.php:10`) and is already used directly
elsewhere (`core/system.php:5478`). Do not add new methods to `Database` for
this. `PDO::ATTR_EMULATE_PREPARES` is already `false` (`core/classes/pdo.php:26`),
so native unbuffered cursors are available.

**D12 — New settings reach existing installations by bumping the config cache
version.** `getConfig()` returns `config/local.php` verbatim whenever its
`cache_version` matches, without ever hashing the source files
(`core/system.php:40-45`). A shipped edit to `config/scheduler.php` is therefore
invisible on any deployed site, and `config/local.php` is in `.gitignore` — a
commit cannot delete it. The 6.3 upgrade block is no fallback either: it only
runs when the operator selects that specific migration (`setup/index.php:499`).

So the delivery mechanism is a version bump: raise `cache_version` from `2` to
`3` in both the read guard (`core/system.php:42`) and the writer
(`core/system.php:97`). Every installation then rebuilds `config/local.php` from
source on the next request. The 6.3 upgrade block still fills missing keys for
operators who run it, but correctness no longer depends on it.

**D13 — Reuse the existing dial POST contract.** `templates/admin/fragments/dial.html:4`
and `:6` already render a per-item submit button bound to a generated form, and
`core/helpers.php:651` already uses it. A dedicated "scheduler mode" with one
form and several submit buttons would be a second mechanism for the same result
and would additionally need `name`/`value` on the button, which the fragment
does not carry. Pass `form_id` and `hidden` per action instead.

**D14 — CSRF scope separation is advisory.** `checkSiteToken()` accepts the
`ajax` token for any scope (`core/security.php:711`). This is the project's
canonical mechanism and is not changed here; the acceptance criteria are worded
against what it actually guarantees.

**D15 — Technical failure messages stay English literals**, without language
constants, matching the current handler (`core/system.php:840`). The single
exception is the new job status label described under "Status presentation".

**D16 — The class is required lazily.** There is no autoloader for
`core/classes` — they are plain `require_once` (`core/security.php:49`).
Requiring `backup.php` globally would parse it on every request for a job that
runs once a day, so the `require_once` belongs inside the dispatcher branch.

## Architecture

Create `core/classes/backup.php` with class `Backup`:

```php
__construct(
    Database $db,
    array $dbconf,
    array $settings,
    string $dir,
    ?array $caps = null
)

addDatabaseBackup(): array
```

`$caps` is the compressor capability map in the shape `checkCompress()` returns
(`core/system.php:2347`). It exists as the test seam for D4 and D8: production
passes `null` and the constructor resolves it through `checkCompress()`, while
unit tests inject any combination including all-false. It is the only injected
capability; the staging root is already injectable through `$dir`, which is how
tests exercise write failures and unwritable locations.

`Backup` owns:

- preflight validation of settings, patterns, compressor, `link()` support,
  backup root, and the privilege contract;
- discovery and classification of the selected database scope;
- deterministic schema/data serialization;
- consistent reading, checked streaming writes, compression verification,
  atomic publication, retention, and cleanup;
- exact artifact metadata and checksums.

The scheduler owns:

- request authorization, CSRF, and cron-token validation;
- the lock protocol, crash reconciliation, status, logging, and presentation.

### Settings

`$conf['scheduler']['jobs']['dbbackup']['settings']`:

```php
[
    'include' => '*',
    'exclude' => 'ipb_*',
    'schemaonly' => 'MRG_MyISAM,MERGE,HEAP,MEMORY',
    'compress' => 'auto',
    'keep' => '0',
    'allow_incomplete' => '0',
]
```

Write these defaults into `config/scheduler.php:33` and deliver them through the
`cache_version` bump (D12). The 6.3 upgrade block in `setup/index.php:608-638`
fills only missing keys and never overwrites configured values. The admin save
already preserves system-job settings verbatim
(`admin/modules/scheduler.php:278`).

`include` and `exclude` are comma-separated full table-name globs where `*`
matches any sequence and `?` exactly one character. Validate them before
creating output, respect the server's table-name case rules, and let `exclude`
win over `include`. `compress` is `auto`, `zip`, `gz`, `bz2`, or `none`. `keep`
is a non-negative integer. `allow_incomplete` is exactly `0` or `1`.

### Migrating the hardcoded legacy values

`addBackupTask()` carries three literals (`core/system.php:740`, `:744`,
`:757`). Their fate is decided, and none of them survives as a literal:

| Legacy | Becomes | Note |
|---|---|---|
| `$conlycreate = 'MRG_MyISAM,MERGE,HEAP,MEMORY'` | `schemaonly`, same default | Straight move, no behaviour change |
| `$ctables = '^ipb_*'` | `include => '*'` plus `exclude => 'ipb_*'` | Same effect, different syntax |
| `$ccharset = 'auto'` | nothing — the concept is removed | Behaviour change, see below |

The `^` prefix disappears as a concept. In the legacy string it does three jobs
at once: it marks a pattern as an exclusion, it supplies the leading regex
anchor (`core/system.php:814` anchors only the end, so `^` in the pattern itself
provides the start), and its presence as the string's first character switches
the whole list between include-mode and exclude-mode
(`core/system.php:775`). Two explicit keys replace all three behaviours. Note
the asymmetry this removes: legacy `include` patterns are anchored at both ends
while `exclude` patterns rely on the operator writing `^` themselves. The new
globs are always full-name matches.

`$ccharset` is deliberately dropped rather than migrated. With `auto` the legacy
routine re-issues `SET NAMES` mid-dump for each table's own charset
(`core/system.php:903-911`), so the output depends on table order and is not
reproducible. A deterministic export in a fixed encoding with a reversible
`SET NAMES` is incompatible with a per-table charset choice, so keeping the
setting would mean shipping a key that cannot be honoured. Anyone who needs a
different target encoding converts the restored database, not the dump.

### Result contract

The result keeps the scheduler's existing `status`/`message`/`extra` shape and
adds, under `extra`: the exact artifact path, format, byte size, SHA-256 of the
published artifact, SHA-256 of the uncompressed SQL, table count, row count,
`complete`, `unsupported`, `unordered`, `warnings` (D9), and the number of
artifacts removed by retention. The scheduler must never derive the filename.

## Supported database contract

### Preflight and scope discovery

Preflight, in the order given by D8, ending with the privilege contract of D2.
Then, before producing output:

- identify base tables and their engines;
- fail if a foreign key in the selected scope references a table outside it;
- classify views, triggers, events, and routines into `unsupported` and apply
  D1;
- collect primary-key-less tables into `unordered` (D3);
- fail if a selected table uses a non-InnoDB engine unless it is explicitly
  matched by `schemaonly`, even when currently empty.

`schemaonly` means structure only by explicit operator choice.

At review time the local database contains 37 base tables, all InnoDB, with
single-column primary keys and no views, triggers, events, foreign keys, or
generated columns. `setup/sql/table_update6_3.sql` creates eleven helper
procedures (`:24-33`, `:1776`) and drops them at `:1702-1711` and `:1812`;
completing that cleanup is a release prerequisite, because with the default
`allow_incomplete = 0` a leftover procedure fails the run. `Backup` must never
delete schema objects.

### Consistent deterministic export

- Refuse to start inside a caller-owned transaction. Save and restore PDO
  buffering and every affected session variable in `finally`.
- Export under `REPEATABLE READ` with
  `START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY`. The snapshot is
  consistent for InnoDB only, which is why a non-InnoDB engine outside
  `schemaonly` is a hard failure, and why concurrent DDL can still corrupt the
  dump — the fingerprint check below is not optional and neither is its test.
- The transaction runs on the shared connection, so `Backup` must be the only
  database user for its duration: the pseudo-cron entry point already closes the
  session before dispatch (`index.php:160`) and `Logger` writes to files, not
  tables — verify both still hold before shipping.
- Use one forward-only, unbuffered `SELECT` per table with explicit columns,
  ordered by the full primary key where one exists (D3). Do not use `OFFSET`.
- Exclude generated columns from `INSERT`.
- Serialize binary and bit values as hex. Emit numeric literals only when both
  column metadata and value prove they are numeric; quote every other value
  through `getSqlValue()` (`core/classes/pdo.php:75`).
- Use deterministic table order and write `DROP TABLE IF EXISTS` before each
  `CREATE TABLE` while foreign-key checks are disabled.
- Fingerprint canonical columns, indexes, constraints, and table definitions
  before and after export and fail if the DDL changed. Exclude volatile
  `AUTO_INCREMENT` counters and table statistics so that concurrent DML does not
  look like schema drift.
- Check every database call and every stream write. Roll back and clean up on
  any error.

The prologue/epilogue must save and restore foreign-key checks, unique checks,
time zone, SQL mode, and charset/collation state. Export in UTC, use
`NO_AUTO_VALUE_ON_ZERO`, and make `SET NAMES` reversible.

### Atomic artifact

1. Create a private `0700` `.stage-<uniq>` directory below the backup root, and
   `dump.sql` inside it with `fopen($path, 'xb')`. Staging lives inside
   `BACKUP_DIR` so that publication stays within one filesystem (D6).
2. Stream the dump, flush and `fsync()` it, close it with a checked result, and
   calculate SHA-256.
3. If the compressor resolved in preflight is none, the candidate is `dump.sql`
   itself; skip to step 6.
4. Compress through `addCompress()` with the explicit algorithm and source
   deletion disabled into `dump.part.<ext>`.
5. Stream-decompress the candidate and verify its SQL SHA-256 against the
   source. This is also what covers `addCompress()` not checking its own
   `gzclose()`/`fclose()`.
6. Durably persist the candidate before it becomes the artifact: `addCompress()`
   owns and closes its own handle, so reopen the candidate with
   `fopen($path, 'rb+')`, `fsync()` it, and close with a checked result. Without
   this the compressed artifact could survive publication as a verified name
   pointing at unflushed content after a power loss.
7. Publish with `link($candidate, $final)` under the D5 name, then
   `unlink($candidate)`. On `link()` collision, regenerate the suffix and retry.
8. Apply retention (D7).
9. Remove staging. Failures in steps 8 and 9 follow D9.

Never publish `.part` or `.bak` files, and never replace an existing final
artifact.

## Scheduler boundary

Every state-changing scheduler action is POST-only. That is five entry points,
not four: **save, run, unlock, delete, and the direct dispatcher**. `save()`
rewrites `config/scheduler.php` and is as state-changing as the rest
(`admin/modules/scheduler.php:238`).

Switching the form method is not sufficient. `checkSiteToken()` with no argument
falls back to `getRequestToken()`, which reads the `X-CSRF-Token` and
`X-XSRF-Token` headers and the `token` request parameter including GET
(`core/security.php:670-681`). All four admin handlers call it bare
(`admin/modules/scheduler.php:242`, `:294`, `:315`, `:328`), so all four accept
a credential from the query string. Three of them additionally read their
payload with `getVar('req', ...)` and are therefore reachable by a pure GET;
`save()` already reads its fields from POST (`admin/modules/scheduler.php:240`)
and is the narrower case. The fix is identical for all four. Each must:

1. reject unless `$_SERVER['REQUEST_METHOD'] === 'POST'`;
2. read the token only from the POST body;
3. validate it against the scheduler scope.

```php
checkSiteToken(getVar('post', 'token', 'text'), 'scheduler')
```

The forms must emit `getSiteToken('scheduler')`, not the default `ajax` token,
so that the scope is meaningful in the direction it can be (D14).

Access matrix:

- admin UI: authenticated administrator plus a valid scheduler token from the
  POST body;
- direct pseudo invocation: valid SLAED site token for the scheduler flow, in
  the POST body;
- direct cron invocation: configured static scheduler token, in the POST body;
- remove the unconditional `isAdmin()` bypass at `core/system.php:417`.

The direct dispatcher accepts exactly two `trigger` values, `pseudo` and `cron`,
and rejects everything else before authorization runs. `manual` is removed from
that endpoint entirely: it has no credential of its own in the matrix above and
exists only as the admin-UI path, which goes through `run()`. The current
default of `manual` when the parameter is absent (`index.php:152`) therefore
becomes an invalid request rather than a silently weaker branch.

Update the inline pseudo fetch (`core/system.php:444`) and the dispatcher
(`index.php:147-163`) together with the admin forms.

### Locking

Use an OS `flock` keyed by canonical job name, following the protocol in D10.
`addSchedulerRun()` (`core/system.php:539`) acquires it and holds the handle for
the job's complete execution.

`checkSchedulerLock()` (`core/system.php:331`) must report the OS lock rather
than `started_at + lock_timeout`, because `checkSchedulerDue()` gates on it
(`core/system.php:344`): if JSON stayed authoritative there, a stale
`running = 1` would make the job permanently not-due while unlock — which may no
longer touch a live lock — could not repair it. Crash reconciliation is what
makes the job due again, and it happens under the lock, never from a read path.

Different jobs remain independent and may run concurrently.

### Status presentation

`admin/modules/scheduler.php:35` maps any unknown `last_status` to
`_SCHEDULER_IDLE`, so a crashed job would be displayed as idle — which is how
the D10 state becomes invisible. Add a `'crashed'` branch backed by one new
constant `_SCHEDULER_CRASH`, defined in `admin/lang/en.php` first and translated
into all six locales in the same commit (`de`, `en`, `fr`, `pl`, `ru`, `uk`,
around line 690).

Over-budget is not a status and gets no constant: the job is still `running`,
and the excess belongs in the existing per-row popover
(`admin/modules/scheduler.php:63`). That row currently shows `last_duration`
(`admin/modules/scheduler.php:47`) — the stored duration of the previous
*finished* run — which says nothing about how long the current one has been
going. For a job whose lock is held, the popover must instead show the live
elapsed time computed as `time() - started_at`, reusing `_SCHEDULER_RUNTIME`. No
live value, no claim of visibility: this is the one change that makes the
diagnostic real rather than nominal.

`admin/info/scheduler/ru.md:72` states that the scheduler forcibly kills an
over-budget process and marks the task as failed. That is false and must be
rewritten to describe `lock_timeout` as a diagnostic marker. The same file needs
the cron invocation example it currently lacks entirely (74 lines, no
`curl`/token section), showing a POST body, plus the non-Apache deny requirement
from D6.

Render scheduler row actions through `templates/admin/fragments/dial.html` using
the existing `form_id`/`hidden` per-item contract (D13). PHP passes URLs,
values, labels, icons, and semantic flags — never markup or CSS class names.
Existing non-scheduler dial rendering must remain unchanged.

## Current findings

| Priority | Finding | Evidence |
|---|---|---|
| Critical | The dump is paginated without one consistent snapshot. | `core/system.php:934`, `core/system.php:938` |
| Critical | Writes and final publication are not checked or atomic. | `core/system.php:890`, `core/system.php:972` |
| High | Estimated row counts and average row length drive export control flow. | `core/system.php:844`, `core/system.php:857`, `core/system.php:934` |
| High | SQL value serialization is a regex over the type name, not metadata. | `core/system.php:927` |
| High | The routine guesses artifact identity and does not prove restoreability. | `core/system.php:977` |
| High | The direct dispatch grants any admin access without CSRF. | `core/system.php:417` |
| High | Four scheduler mutations are fully GET-reachable; `save()` reads its fields from POST but still accepts its credential from the query string. | `admin/modules/scheduler.php:74`, `:240-242`, `index.php:147` |
| High | POST-only is not reachable by method alone: token lookup falls back to GET and headers. | `core/security.php:670-681` |
| High | JSON timeout state can permit two processes to run the same job. | `core/system.php:331`, `core/system.php:539` |
| High | `checkSchedulerDue()` gates on the same JSON lock, so stale state disables the schedule. | `core/system.php:344`, `core/system.php:364` |
| High | Shipped config changes never reach a deployed site: the cache is returned without fingerprint checks. | `core/system.php:40-45`, `setup/index.php:499` |
| Medium | `addCompress('auto')` hard-fails instead of falling back when no compressor exists. | `core/system.php:2370` |
| Medium | Default `dbbackup.settings` is empty. | `config/scheduler.php:33` |
| Medium | Unknown job status renders as idle, so a crashed run is indistinguishable from an idle one. | `admin/modules/scheduler.php:35` |
| Medium | Documentation promises forced termination of over-budget jobs, which nothing implements. | `admin/info/scheduler/ru.md:72` |
| Medium | Monitor derives the last backup from directory mtime and counts the whole tree. | `admin/modules/monitor.php:920`, `:950` |
| Medium | Scheduler mutations are rendered as links although the dial fragment already supports POST. | `admin/modules/scheduler.php:72`, `templates/admin/fragments/dial.html:4` |
| Low | No retention: 36 legacy-named archives since 2026-06-03, growing nightly. | `storage/backup` |
| Low | Dumps are protected by an Apache-only `deny from all`. | `storage/backup/.htaccess` |

## Implementation plan

Seven verifiable batches, one final release. The dispatcher is switched last,
only after a restore test has passed.

**Batch 0 — privilege spike. Done, 2026-08-03.** Ran against MySQL 8.4.4,
MariaDB 11.7.2, and MariaDB 10.4.32 (OSPanel modules, started alongside the
running instances and stopped afterwards). Covered an account privileged only
through an inactive role, a MySQL partial revoke, a MariaDB nested granted-role
chain, and a schema holding a routine owned by another definer. All findings are
folded into D2 above; the four that changed the specification were: `USING`
reporting inactive roles' privileges, `SHOW GRANTS ... USING` being absent from
MariaDB entirely, MariaDB expanding nested roles by itself, and definer
visibility yielding a non-empty but incomplete routine catalog. Fixtures were
dropped and `partial_revokes` restored on every server.

**Batch 1 — contract and config delivery. Done, 2026-08-03.** The six settings
defaults now ship in `config/scheduler.php`, the 6.3 block in `setup/index.php`
fills only the keys an upgraded site is missing, and `cache_version` moved from
`2` to `3` in both the read guard and the writer. Delivery was verified against
a live stale cache: a `config/local.php` carrying version `2` was rebuilt on the
next request and came back with the new `dbbackup` settings, without deleting
the file by hand. The 6.3 migration reaches its procedure cleanup — the setup
statement splitter parses the file into 521 statements in which all eleven
created procedures are also dropped, the last of them being the final statement
— and the local database currently reports zero routines, triggers, views, and
events, so the strict `allow_incomplete = 0` default has nothing to trip over.

**Batch 2 — `Backup` plus pure unit tests. Done, 2026-08-03.**
`core/classes/backup.php` holds the whole class and `tests/Unit/BackupContractTest.php`
its 52 pure tests; the dispatcher still calls `addBackupTask()`. Retention was
additionally replayed against the real `storage/backup`, where it recognized all
36 legacy archives, left `.htaccess` and `index.html` untouched, and deleted
nothing at `keep = 0`. Four things are
worth recording because they are decisions the specification left to the
implementation. First, subtracting a `REVOKE` at the same scope key would be a
no-op for exactly the case D2 was written for — the spike's account held
`SELECT, TRIGGER ON *.*` with a schema-level revoke — so the resolver keeps
denials as their own scoped set and lets a denial at the schema override a
global grant; a mutation that treats revoke lines as grants is caught by
`aPartialRevokeIsSubtractedFromTheGlobalGrant`. Second, an unknown key in
`settings` fails preflight instead of being ignored, since silent ignoring is
the same failure mode D1 forbids. Third, scope for the unsupported classes reads
as: views match the scope patterns, triggers follow their table, and events and
routines are schema-wide, which is what makes a leftover 6.3 procedure fail a
strict run. Fourth, the artifact timestamp stays in local time like the 36
legacy names, so retention orders both schemes on one clock, while the dump
content is UTC. The collaborator is built with `createStub()` rather than
`createMock()` — PHPUnit 12 emits a notice for a mock without expectations, and
the seam is the same. Nothing consumes the legacy `last_backup_*` extra keys, so
the result contract was free to name its own.

The test coverage this batch owed: settings validation including the
`allow_incomplete` domain, pattern filtering with an explicit case proving the
legacy `^`-prefixed form is rejected rather than silently reinterpreted, scope
classification and both completeness branches, privilege-contract evaluation
from fixture grant strings, canonical row serialization from injected metadata,
artifact naming and collision retry, retention candidate selection across both
naming schemes, post-publication warning handling, and result shape.

**Batch 3 — integration. Done, 2026-08-03.** `tests/Support/backup_probe.php`
boots the real core per scenario and drives the class against a disposable
schema it creates and drops again, through a scratch backup root, so neither the
site database nor `storage/backup` is touched;
`tests/Unit/BackupIntegrationTest.php` holds the 24 tests over it. It ran
against MariaDB 11.7.2.

Three findings changed something. First, `addCompress()` could never produce a
bz2 archive on PHP 8: it opened the target with `bzopen($file, 'wb')`, and PHP 8
accepts only `r` or `w`, so every bz2 run died with a `ValueError`. Fixed at
`core/system.php:2490`; the run had correctly returned `failed` and cleaned up,
which is what the `Throwable` catch is for. Second, a concurrent `ALTER` cannot
actually reach an export: the metadata lock the export transaction holds queues
the DDL until the run commits, and the probe confirms the artifact carries the
pre-`ALTER` definition after a competing `ALTER` on a 65k-row table. The
fingerprint therefore guards the narrow window before the tables are first read
rather than the whole export, and it is asserted directly instead — it changes
on a new column and does not change on concurrent inserts and deletes. Third,
artifacts are created `0600` but Windows reports `666`, so file mode is a POSIX
assertion and is not made here.

Two rows of the batch 0 matrix cannot be exercised locally and stay covered by
unit tests over fixture grant strings: MySQL partial revokes (`partial_revokes`
does not exist on MariaDB) and the MySQL `USING` branch. What did run live: an
account holding only schema `SELECT` reads a catalog of zero triggers and zero
routines while both exist and is refused; the same account with an **inactive**
role is still refused; activating a role whose grant comes through a nested role
resolves triggers and events and leaves exactly `routines` unproven; and a fully
privileged account gets past preflight and stops on the strict completeness rule
instead. The artifact was additionally restored into an empty database and
compared by row count and canonical row-hash multiset, duplicates included —
that is serialization evidence, not the batch 6 restore gate, which additionally
owes an application smoke test.

**Batch 4 — scheduler hardening. Done, 2026-08-03.** The OS lock is now the only
authority: `getSchedulerLockHandle()` takes it without waiting and the run holds
the handle for its whole execution, `checkSchedulerLock()` reports it instead of
reading `started_at + lock_timeout`, and `updateSchedulerCrash()` performs the
reconciliation of D10. The two writers were renamed to say what they now do —
`addSchedulerLock()` became `setSchedulerStart()` and `deleteSchedulerLock()`
became `setSchedulerDone()`; neither manages a lock any more, both write state
the caller already owns. One decision the specification left open: the planned
time cached in job state is written **only** while holding the lock, because
"job state is only ever mutated while holding the lock" applies to that cache
too — a read path that finds the cache stale now recomputes without writing.

The request boundary: all five entry points are POST-only, the four admin
handlers read both their payload and their credential from the body and validate
it against the `scheduler` scope, the direct endpoint accepts only `pseudo` and
`cron` and rejects everything else before authorization runs, and the
unconditional `isAdmin()` bypass is gone. The pseudo trigger changed shape for
this: `addSchedulerTrigger()` returns the endpoint and the credential separately
and the injected fetch posts a body, so the token no longer travels in an
address. Row actions render through the existing dial contract with `form_id`
and `hidden`.

Verified three ways. The lock protocol runs in `tests/Support/scheduler_probe.php`
with a real second process holding the lock — all four rows of the D10 table,
serialization of one job against independence of another, reconciliation that is
exact and idempotent and preserves `last_run`, read paths that repair nothing,
and unlock that refuses a live job and never deletes the lock file. The direct
endpoint was driven over real HTTP against the running site: GET with a
query-string token, POST without a credential, `manual`, a missing trigger and
wrong tokens are all refused, while a valid pseudo credential succeeds as a POST
from the session it was issued to and is refused both without that session and
over GET. The admin UI was driven in a real browser: the row actions render as
12 submit buttons bound to POST forms carrying their token in the body, a GET
carrying a token does not run the job, and a real click does.

**Batch 5 — consumers and documentation. Done, 2026-08-03.** The admin list now
derives what it shows from both inputs of D10 without writing anything: a held
lock reads as running whatever the JSON says, a free lock over `running = 1`
reads as crashed, and `last_status = 'crashed'` has its own branch. The label is
`_SCHEDULER_CRASH`, defined in `admin/lang/en.php` and translated into all six
locales. For a job whose lock is held the popover shows `time() - started_at`
instead of the previous run's duration, so an over-budget run is recognizable
from a value that moves.

The monitor no longer measures the directory: `getBackupArtifacts()` counts
published artifacts of the current database only. To avoid a second copy of the
naming rules, recognition moved into two static methods on `Backup` —
`getArtifactStem()` and `getArtifactMark()` — which retention and the monitor now
share; `getLatestFileMTime()` had no callers left and was removed.

`admin/info/scheduler/ru.md` gained the two sections it lacked entirely — a cron
invocation with a POST body and the non-Apache deny requirement for `/storage/`
— the false promise that an over-budget process is killed was replaced by what
`lock_timeout` actually is, and the status list gained the crashed row.

Verified in the browser against the running site: with `running = 1` and no lock
the row reads "Аварийно"; with a second process really holding the lock it reads
"Выполняется" and the popover duration advanced from 40 to 45 across two loads,
where the stored `last_duration` was 7. The monitor reported 210.68 MB against
an independently computed artifact sum of 210.68 MB, and it kept reporting
exactly that after 8 MB of staging and `.part` noise was placed in the backup
root — the figure the whole-tree measurement would have inflated to 218.68 MB.

**Batch 6 — cutover. Done, 2026-08-03.** `addSchedulerSystemJob()` now routes
`backup` to `addBackupJob()`, which keeps the `security.log_b` gate, requires
`core/classes/backup.php` lazily and instantiates the class over the job's own
settings. `addBackupTask()` was deleted whole — 274 lines, no alias, no wrapper —
and `rg` finds no reference to it or to its `last_backup_*` result keys anywhere
outside the tests that assert its absence.

The gate ran against the real site. A backup started from the admin list through
the new path finished in 9 seconds: 72 tables, 66 937 rows, `complete = true`,
nothing unsupported, nothing unordered, a 6.6 MB zip artifact with both
checksums recorded and an empty PHP error log. That artifact was then restored
twice into disposable databases — once with the real `mysql` client, the way an
operator would, and once statement by statement from the probe. Both restores
gave back 72 tables with identical names, identical row counts and identical
normalized definitions; 66 tables were additionally compared row by row as
canonical hash multisets with no difference, and the core's own page-view
queries ran against the result.

Two clean-ups came with the cutover. Job state now carries the contract fields
plus the metrics of the run that just finished and nothing else: `setSchedulerDone()`
intersects the stored state with `getSchedulerFields()` before merging the new
`extra`, so the `last_backup_*` keys the deleted implementation used to write no
longer linger, and the same applies to any job whose metrics change shape. This
is safe because no consumer reads those keys — only the contract fields are ever
read. And `admin/info/scheduler/ru.md` gained a full section on the backup job:
what one run actually does, the artifact name, every setting with its default,
the cases where the job refuses on purpose, the privilege table an account has
to satisfy, and how to restore an archive with a plain client.

Two findings came out of the gate, both in the checking apparatus rather than in
the artifact. Sending a whole dump as one packet fails on any real database —
27.5 MB against a 16 MB `max_allowed_packet` — while the largest single
statement the class emits is 1.59 MB, so the artifact is fine and the applier
was wrong. And a naive applier that skips blocks beginning with `#` silently
drops the first prologue statement along with the header comments, which then
fails on `SET SESSION foreign_key_checks = @SLAED_FKEY`. The `mysql` client
handled both correctly from the start. The only table whose rows differ is
`*_session`, which the live site writes on every request; the difference was one
`time` value 16 seconds newer than the snapshot, so it is excluded from the data
comparison by name and stated here rather than hidden.

## Verification

Pure unit tests (batch 2) build the collaborator with
`createMock(Database::class)`, which skips the connecting constructor, inject
compressor capabilities through `$caps`, and point `$dir` at a temporary root —
including an unwritable one to exercise write failures. Anything needing a live
PDO handle or real filesystem semantics — session-variable restoration,
unbuffered cursors, `fsync`, `link()`, snapshot behavior — belongs to batch 3
and never appears in `tests/Unit`.

Static and unit checks:

- `php -l` for every changed PHP file;
- project PHPStan, PHPUnit, and PHP-CS-Fixer checks;
- unit tests for settings validation, both `allow_incomplete` branches and
  rejection of any other value, privilege-contract evaluation, quoting and
  binary values, generated columns, retention boundaries across legacy and
  current names, naming collision retry, post-publication warnings, and exact
  result metadata;
- upgrade tests proving missing settings are added, configured values are
  preserved, and a stale `config/local.php` is rebuilt after the version bump;
- a locale test proving `_SCHEDULER_CRASH` exists in all six files.

Integration and route checks:

- schema-drift test: concurrent DDL during export must fail the run;
- privilege tests reproducing the batch 0 fixtures on both vendors: an account
  with only schema `SELECT` must fail preflight rather than report zero
  triggers; an account whose grant comes from an **inactive** role must fail,
  and must pass once the role is active; a MySQL partial revoke must be
  subtracted; a MariaDB nested role chain must resolve through one
  `SHOW GRANTS FOR '<role>'`; and an account seeing only its own definer-owned
  routine must not satisfy the routines row;
- publication test: a pre-existing final name must not be replaced;
- a real admin POST sequence for save, run, unlock, and delete, followed by
  state persistence and review of `storage/logs/error_php.log`,
  `error_sql.log`, and `error_site.log`;
- route tests proving GET cannot mutate any of the five entry points, that
  pseudo and cron POST bodies work, that a missing or unknown `trigger` is
  rejected, and that invalid, missing, or query-string-only credentials fail;
- concurrent process tests proving two `dbbackup` runs cannot overlap while
  `dbbackup` and `maildrain` or a custom job remain independent;
- crash-state test: kill a running job, then assert the D10 table — the read
  path repairs nothing, the next protocol-following call reconciles to
  `crashed`, unlock is refused while the lock is held, `last_run` is preserved,
  the admin list shows the crashed label, and the job becomes due again at its
  next scheduled slot;
- `rg` confirms `addBackupTask()` and the obsolete mutation routes are gone.

Restore test (gates batch 6):

- restore the produced artifact into a disposable empty database;
- compare base-table names and counts only — never object classes this class
  does not serialize (D1);
- compare exact row counts, PK-ordered canonical checksums for keyed tables, and
  the canonical hash multiset with occurrence counts for `unordered` tables (D3);
- run a minimal application bootstrap/query smoke test.

Production runs can verify integrity and checksums, but must not claim that each
artifact was restore-tested unless an actual restore occurred.

## Acceptance

- `Backup` is the only database export implementation.
- A successful result always identifies one complete final artifact with
  verified checksums, and the scheduler never derives its name.
- No inconsistent, truncated, or unverified artifact is published, and no
  existing artifact is ever replaced.
- A wrong artifact is never produced: cross-scope foreign keys, an unsatisfied
  privilege contract, DDL drift, an unavailable explicit compressor, and a
  filesystem that cannot publish atomically all fail before any output exists.
- Absence of an object class is proven by effective privilege — including
  role-derived grants and partial revokes — never inferred from an empty
  catalog, and an unsupported server version is refused rather than guessed at.
- Both the dump and the published candidate are durably persisted before the
  artifact name exists.
- Completeness is never silently reduced: with the default settings an
  unsupported object fails the run, and an incomplete artifact is only possible
  after an explicit opt-in that is recorded in the result.
- A verified artifact is never discarded because a post-publication cleanup step
  failed; such failures surface as warnings on a successful run.
- The new defaults reach an already deployed site without manual file deletion.
- Retention removes only canonical artifacts of the current database, ages
  legacy and current names together, and is off by default.
- Scheduler mutations cannot be triggered by GET, by a query-string token, or by
  the admin bypass, across all five entry points, and the direct endpoint
  accepts only `pseudo` and `cron`.
- Job state is only ever mutated while holding the job's OS lock.
- Two instances of the same job cannot overlap; a crashed run is reconciled to a
  defined state, is visible as such in the admin list, and becomes due again
  without manual file surgery; unrelated jobs are not globally serialized.
- No documentation or UI claims an enforcement or a visibility the code does not
  implement; an over-budget run is recognizable from live elapsed time, not from
  the previous run's duration.
- Monitor reports the last backup and the directory size from final artifacts
  only.
- A generated artifact passes the disposable restore test within its declared
  scope.
- Database and filesystem backup remain separate responsibilities.
