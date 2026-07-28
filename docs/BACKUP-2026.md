# Database Backup

Status: proposed, not started. Last review: 2026-07-28.

Replace `addBackupTask()` with one final `Backup` class that creates a verified,
restorable database artifact. Migrate the scheduler in the same change and
delete the procedural implementation. No wrapper or intermediate legacy
algorithm.

Source line numbers below describe the current working tree. Resolve the named
symbols again before editing because adjacent work may move them.

## Scope

In scope:

- database schema and data export;
- verified optional compression;
- scheduler authorization, serialization, status, and artifact identity;
- automated integrity tests and a real disposable restore test.

Out of scope:

- filesystem backup;
- retention, remote storage, encryption, and admin restore UI;
- views, triggers, events, routines, and foreign-key dependency ordering in the
  first implementation.

A future filesystem backup must be a separate `FileBackup` class. If both jobs
later need orchestration, add a coordinator; do not combine storage traversal
and SQL export in `Backup`.

## Architecture

Create `core/classes/backup.php` with class `Backup`. Load the class normally,
but instantiate it lazily only in the `dbbackup` scheduler dispatcher.

```php
__construct(
    Database $db,
    array $dbconf,
    array $settings,
    string $dir
)

addDatabaseBackup(): array
```

`Backup` owns:

- discovery and validation of the selected database scope;
- deterministic schema/data serialization;
- consistent reading, checked streaming writes, compression verification,
  atomic artifact publication, and cleanup;
- exact artifact metadata and checksums.

The scheduler owns:

- request authorization, CSRF, and cron-token validation;
- process serialization, execution status, logging, and presentation.

Use existing settings at
`$conf['scheduler']['jobs']['dbbackup']['settings']`:

```php
[
    'include' => '*',
    'exclude' => 'ipb_*',
    'schemaonly' => 'MRG_MyISAM,MERGE,HEAP,MEMORY',
    'compress' => 'auto',
]
```

Keep these defaults synchronized in `config/scheduler.php` and
`config/local.php`. The 6.3 upgrade path in `setup/index.php` fills only missing
`dbbackup.settings` keys and never overwrites configured values. The dispatcher
passes the complete settings array to `Backup`; malformed explicit values fail
before output.

Patterns are comma-separated full table-name globs where `*` matches any
sequence and `?` one character. Validate them before creating output, respect
the server's table-name case rules, and let `exclude` win over `include`.

The result retains the scheduler's existing success/error fields and adds the
exact artifact path, format, byte size, SHA-256 checksum, and uncompressed SQL
checksum. The scheduler must never guess the filename.

## Supported database contract

### Scope discovery

Before output:

- identify base tables and their engines;
- fail if views, triggers, events, routines, or foreign keys are in the selected
  scope;
- fail if metadata visibility cannot be established with sufficient
  privileges; absence must not be inferred from an unreadable catalog;
- fail if a data-exported table has no primary key;
- fail if a selected table uses a non-InnoDB engine unless it is explicitly
  matched by `schemaonly`, even when currently empty.

`schemaonly` means structure only by explicit operator choice.

At review time the local database contains 37 base tables, all InnoDB, with
single-column primary keys, no views, triggers, events, foreign keys, or
generated columns, but it does contain nine migration helper procedures. Their
cleanup is already defined in `setup/sql/table_update6_3.sql`; completing that
cleanup is a release prerequisite. `Backup` must not delete schema objects and
must continue to fail closed while unsupported routines exist.

### Consistent deterministic export

- Refuse to start inside a caller-owned transaction. Save and restore PDO
  buffering and every affected session variable in `finally`.
- Export under `REPEATABLE READ` with
  `START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY`.
- Use one forward-only, unbuffered `SELECT` per table with explicit columns and
  `ORDER BY` the full primary key. Do not use `OFFSET`.
- Exclude generated columns from `INSERT`.
- Serialize binary and bit values as hex. Emit numeric literals only when both
  column metadata and value prove they are numeric; quote all other values
  through the database driver.
- Use deterministic table order and include `DROP TABLE IF EXISTS` before each
  `CREATE TABLE` while foreign-key checks are disabled.
- Fingerprint canonical columns, indexes, constraints, and table definitions
  before and after export and fail if DDL changed. Exclude volatile
  `AUTO_INCREMENT` counters and table statistics so concurrent DML does not look
  like schema drift.
- Check every database call and every stream write. Roll back and clean up on
  any error.

The dump prologue/epilogue must save and restore foreign-key checks, unique
checks, time zone, SQL mode, and charset/collation state. Export in UTC, use
`NO_AUTO_VALUE_ON_ZERO`, and make `SET NAMES` reversible.

### Atomic artifact

1. Reserve a collision-free artifact stem and create `<name>.sql` exclusively
   inside a private, uniquely named staging directory below the backup root.
2. Stream the dump, flush and `fsync()` it, close it, and calculate SHA-256.
3. If compression is disabled or `auto` has no supported compressor, publish
   the staged SQL atomically as `<name>.sql`.
4. Otherwise compress through the existing `addCompress()` facility with
   source deletion disabled into `<name>.part.<ext>`.
5. Stream-decompress the candidate and verify its SQL SHA-256 against the
   source, then atomically rename it to the final artifact.
6. Remove staging only after successful publication or during checked failure
   cleanup.

Explicitly requested but unavailable compression fails. `auto` may fall back
to plain SQL. Never publish `.part` or `.bak` files, and never replace an
existing final artifact.

## Scheduler boundary

All state-changing scheduler actions are POST-only: manual run, direct run,
unlock, and delete. GET only displays state. Browser-originated actions require
SLAED CSRF validation; cron uses its configured bearer token as authentication,
not as CSRF.

Access matrix:

- admin UI: authenticated administrator plus valid scheduler CSRF token;
- direct pseudo invocation: valid SLAED site token for the scheduler flow;
- direct cron invocation: configured static scheduler token;
- remove the unconditional `isAdmin()` bypass from direct dispatch.

Send pseudo and cron credentials in the POST body, never in the URL. Update the
inline pseudo fetch, endpoint, and `admin/info/scheduler/ru.md` cron example
together.

Render scheduler row actions through the real
`templates/admin/fragments/dial.html`. In scheduler mode it owns one POST form
with common hidden fields and submit buttons for mutations plus a separate edit
link. PHP passes URLs, values, labels, icons, and semantic flags, not markup or
CSS classes. Existing non-scheduler dial rendering must remain unchanged.

Use an OS `flock` keyed by canonical job name and held by `addSchedulerRun()` for
that job's complete execution. JSON status and heartbeat are diagnostic only,
not lock authority. Unlock may repair a job's stale status only after its OS
lock is free; it must never break a live lock or delete the lock file. Timeout
is an execution budget and stale-state diagnostic, not permission to start the
same job again. Different jobs remain independent and may run concurrently.

## Current findings

| Priority | Finding | Evidence |
|---|---|---|
| Critical | The dump is paginated without one consistent snapshot. | `core/system.php:934` (`addBackupTask()`) |
| Critical | Writes and final publication are not fully checked or atomic. | `core/system.php:880`, `core/system.php:973` |
| High | Estimated row counts influence export control flow. | `core/system.php:844`, `core/system.php:934` |
| High | SQL value serialization is not metadata-driven for every type. | `core/system.php:923` |
| High | The routine guesses artifact identity and does not prove restoreability. | `core/system.php:973` |
| High | Mutating scheduler routes include GET/weak direct-dispatch boundaries. | `admin/modules/scheduler.php:72`, `index.php:139`, `core/system.php:415` |
| High | JSON timeout state can permit two processes to run the same job. | `core/system.php:331`, `core/system.php:539` |
| High | Nine migration helper procedures currently violate the supported database contract. | `setup/sql/table_update6_3.sql:36`, `setup/sql/table_update6_3.sql:1600` |
| Medium | Default and installed `dbbackup.settings` are empty. | `config/scheduler.php:33`, `config/local.php:1193` |
| Medium | Scheduler mutations are rendered as links by the shared dial fragment. | `admin/modules/scheduler.php:72`, `templates/admin/fragments/dial.html:1` |

## Implementation plan

1. Verify that the existing setup migration reaches its procedure-cleanup
   block; do not delete routines from `Backup`. Add the canonical settings to
   both config sources and the missing-key-only upgrade migration.
2. Add `Backup` with focused unit tests for filtering, discovery failures,
   deterministic serialization, session restoration, checked writes, cleanup,
   compression verification, and exact results.
3. Implement the consistent unbuffered export and atomic artifact pipeline.
4. Harden the scheduler boundary: POST authentication matrix, actual dial
   template, per-job process lock, stale-state repair, and exact artifact use.
5. Replace the `dbbackup` dispatcher call and delete `addBackupTask()` without
   leaving aliases or compatibility wrappers.
6. Run the full verification matrix, including a real disposable restore, then
   inspect the final diff for obsolete routes and hidden fallback behavior.

## Verification

Required automated checks:

- `php -l` for every changed PHP file;
- project PHPStan, PHPUnit, and PHP-CS-Fixer checks;
- unit tests for settings, object/engine/PK rejection, quoting and binary
  values, generated columns, schema drift, read/write failures, cleanup,
  compression fallback/failure, checksum verification, and exact artifact
  metadata;
- upgrade tests proving missing settings are added and configured values are
  preserved;
- a real admin GET/POST sequence for run, unlock, and delete, followed by state
  persistence and PHP/SQL/site log review;
- route tests proving GET cannot mutate, pseudo and cron POST bodies work, and
  invalid, missing, or query-string-only credentials fail;
- concurrent process tests proving two `dbbackup` runs cannot overlap while
  `dbbackup` and `maildrain` or a custom job remain independent;
- `rg` confirms `addBackupTask()` and obsolete mutation routes are gone.

Required restore test:

- restore the produced artifact into a disposable empty database;
- compare schema object names/counts, exact row counts, and canonical row
  checksums ordered by primary key;
- run a minimal application bootstrap/query smoke test.

Production runs can verify integrity and checksums, but must not claim that each
artifact was restore-tested unless an actual restore occurred.

## Acceptance

- `Backup` is the only database export implementation.
- A successful result always identifies one complete final artifact with
  verified checksums.
- No inconsistent, truncated, or unverified artifact is published.
- Unsupported database scope fails before output.
- Scheduler mutations cannot be triggered by GET or an authorization bypass.
- Two instances of the same job cannot overlap or be unlocked through stale
  JSON state; unrelated jobs are not globally serialized.
- A generated artifact passes the disposable restore test.
- Database and filesystem backup remain separate responsibilities.
