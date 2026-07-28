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

- request authorization and CSRF;
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
single-column primary keys and none of the unsupported objects above. These are
observations, not assumptions the implementation may hard-code.

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
- Fingerprint the selected schema before and after export and fail if DDL
  changed.
- Check every database call and every stream write. Roll back and clean up on
  any error.

The dump prologue/epilogue must save and restore foreign-key checks, unique
checks, time zone, SQL mode, and charset/collation state. Export in UTC, use
`NO_AUTO_VALUE_ON_ZERO`, and make `SET NAMES` reversible.

### Atomic artifact

1. Reserve a collision-free artifact stem and create `<name>.sql` exclusively
   inside a private, uniquely named staging directory below the backup root.
2. Stream the dump, flush it, close it, and calculate SHA-256.
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

All state-changing scheduler actions are POST-only and CSRF-protected:
manual run, direct run, unlock, and delete. GET only displays state.

Access matrix:

- admin UI: authenticated administrator plus valid scheduler CSRF token;
- direct pseudo invocation: valid SLAED site token for the scheduler flow;
- direct cron invocation: configured static scheduler token;
- remove the unconditional `isAdmin()` bypass from direct dispatch.

Keep scheduler action markup in `templates/admin/dial.html`: one form with
common hidden fields, mutation buttons, and a separate edit link.

Use one scheduler-wide OS `flock` held by `addSchedulerRun()` for the complete
job execution. JSON status and heartbeat are diagnostic only, not lock
authority. Unlock may repair stale status after the OS lock is free but must
never break a live lock or delete the lock file. Treat configured timeout as an
execution budget and stale-state diagnostic, not permission to overlap jobs.

Because the lock is scheduler-wide, regression coverage must include
`dbbackup`, `maildrain`, and one ordinary/custom scheduler job.

## Current findings

| Priority | Finding | Evidence |
|---|---|---|
| Critical | The dump is paginated without one consistent snapshot. | `core/system.php:934` (`addBackupTask()`) |
| Critical | Writes and final publication are not fully checked or atomic. | `core/system.php:880`, `core/system.php:973` |
| High | Estimated row counts influence export control flow. | `core/system.php:844`, `core/system.php:934` |
| High | SQL value serialization is not metadata-driven for every type. | `core/system.php:923` |
| High | The routine guesses artifact identity and does not prove restoreability. | `core/system.php:973` |
| High | Mutating scheduler routes include GET/weak direct-dispatch boundaries. | `admin/modules/scheduler.php:72`, `index.php:139`, `core/system.php:415` |
| High | JSON timeout state can permit overlapping jobs without a process-held lock. | `core/system.php:331`, `core/system.php:539` |

## Implementation plan

1. Add `Backup` with focused unit tests for filtering, discovery failures,
   deterministic serialization, session restoration, checked writes, cleanup,
   compression verification, and exact results.
2. Implement the consistent unbuffered export and atomic artifact pipeline.
3. Harden the scheduler boundary: POST/CSRF access matrix, template actions,
   process-held lock, stale-state repair, and exact artifact consumption.
4. Replace the `dbbackup` dispatcher call and delete `addBackupTask()` without
   leaving aliases or compatibility wrappers.
5. Run the full verification matrix, including a real disposable restore, then
   inspect the final diff for obsolete routes and hidden fallback behavior.

## Verification

Required automated checks:

- `php -l` for every changed PHP file;
- project PHPStan, PHPUnit, and PHP-CS-Fixer checks;
- unit tests for settings, object/engine/PK rejection, quoting and binary
  values, generated columns, schema drift, read/write failures, cleanup,
  compression fallback/failure, checksum verification, and exact artifact
  metadata;
- route tests proving GET cannot mutate, each accepted token path works, and
  invalid/missing tokens fail;
- concurrent process tests proving scheduler serialization for `dbbackup`,
  `maildrain`, and one custom job;
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
- Live jobs cannot overlap or be unlocked through stale JSON state.
- A generated artifact passes the disposable restore test.
- Database and filesystem backup remain separate responsibilities.
