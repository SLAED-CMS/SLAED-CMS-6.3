# Database Backup

Status date: 2026-07-28. Proposed, not started.

This plan replaces the procedural `addBackupTask()` database dump with one final
`Backup` class. The class owns database export and creation of one verified,
restorable backup artifact. Scheduler access, locking, state, and presentation
remain in the scheduler subsystem.

The class is named `Backup`, not `DatabaseBackup`, as requested. Its first
public operation is explicitly named `addDatabaseBackup()` so the shorter class
name does not imply that it also backs up the filesystem.

The implementation is one atomic replacement. The current algorithm is not
first copied into a class and hardened later; atomic output, consistent reading,
checked writes, exact artifact identity, cleanup, and restore verification are
part of the first and only production implementation.

## Progress

Written at the end of every implementation batch before its report.

| Date | Stage / batch | Outcome |
|---|---|---|
| 2026-07-28 | Analysis and implementation plan | complete; no PHP source changed |
| 2026-07-28 | Final-contract correction | complete; existing scheduler settings, POST-only mutations, one consistent unbuffered algorithm, fail-closed object scope, deterministic restore order, and verified compression settled |

## Goal

- Create `core/classes/backup.php` with class `Backup`.
- Move database dump state and operations out of `core/system.php`.
- Keep the supported `dbbackup` scheduler route and state contract while
  deleting the procedural implementation.
- Make incomplete dumps impossible to publish as successful backups.
- Add restore-based verification so “backup completed” means the artifact can
  recreate the supported database scope.
- Keep database backup independent from any future filesystem backup.

## Scope

In scope:

- SQL schema and row export for the currently selected tables;
- table filtering and structure-only engine rules;
- bounded row batching;
- consistent snapshot behavior where the storage engine supports it;
- temporary SQL output, compression, final artifact verification, and cleanup;
- scheduler integration and scheduler result metadata;
- logging and restore rehearsal;
- tests against a disposable MySQL/MariaDB database.

Outside scope:

- uploads or general filesystem backup;
- combining database and filesystem backup in one class;
- backup retention, replication, encryption, or remote storage;
- an admin restore feature;
- point-in-time recovery and binary logs;
- redesigning all scheduler jobs;
- converting `addCompress()` in this plan;
- changing database schema for backup history.

## Facts measured in the current tree

### Runtime flow

The scheduled job is configured at `config/scheduler.php:24-35`:

- key: `dbbackup`;
- system dispatcher value: `backup`;
- schedule: `30 3 * * *`;
- lock timeout: 1,800 seconds;
- manual execution enabled.

The HTTP scheduler route reads the job, trigger, and token at
`index.php:139-153`. The admin manual route validates its token and calls
`addSchedulerRun()` at `admin/modules/scheduler.php:292-310`.

The scheduler dispatcher calls `addBackupTask()` at
`core/system.php:525-534`. The complete database export is one function at
`core/system.php:721-993`.

Scheduler runtime state is stored in
`storage/logs/scheduler/<job>.json` by `getSchedulerState()` and
`setSchedulerState()` at `core/system.php:308-327`.

On 2026-07-28 the local state file reported:

- last success: 2026-07-21;
- trigger: `pseudo`;
- duration: 3 seconds;
- table count: 34;
- artifact: `slaed_2026-07-21_20-25-20.zip`;
- artifact size: 5,879,589 bytes.

The artifact exists under `storage/backup`. This proves creation and scheduler
recording, but there is no recorded restore rehearsal, so it does not yet prove
recoverability.

No backup-focused tests exist. The only matches are stale unused-code audit
entries for the former `addBackupDb` name at
`tests/UnusedCodeAuditTest.php:221-224`.

The read-only database inventory on 2026-07-28 found:

- 36 base tables, all InnoDB;
- no views, triggers, events, stored functions, or procedures;
- no foreign keys, generated columns, or binary/bit columns;
- one single-column primary key on every table.

These facts permit one final consistent-snapshot implementation without a
lock-based fallback. The class still checks these assumptions at runtime and
fails before creating an artifact if an unsupported object or engine appears.

### Current dump algorithm

1. Check `security.log_b` and increase memory/time limits at
   `core/system.php:721-735`.
2. Use hardcoded charset, structure-only engine, and table-filter values at
   `core/system.php:737-756`.
3. query server version and tables at `core/system.php:760-840`;
4. use `SHOW TABLE STATUS` estimates for row totals and batch size at
   `core/system.php:842-864`;
5. open the final `.sql` path directly at `core/system.php:866-885`;
6. write headers and table schema at `core/system.php:888-920`;
7. export rows with offset-based `SELECT * ... LIMIT` at
   `core/system.php:922-968`;
8. close and call `addCompress()` at `core/system.php:971-974`;
9. guess which archive extension was created and return scheduler metadata at
   `core/system.php:976-992`.

## Findings

| Severity | Finding | Evidence | Consequence |
|---|---|---|---|
| Critical | A successful artifact has no restore-based acceptance test | scheduler success is based on compression at `core/system.php:971-992`; no backup tests exist | A syntactically broken or incomplete dump can be reported as recoverable |
| High | The dump is read without a consistent snapshot and uses offset pagination without deterministic ordering | `core/system.php:933-965` | Concurrent inserts, deletes, or updates can produce missing, duplicated, or cross-table-inconsistent data |
| High | Query failures can collapse into false/empty results without a single fail-fast boundary | `Database::getSqlQuery()` returns `false` at `core/classes/pdo.php:118-162`; several backup calls immediately fetch or loop at `core/system.php:798-864`, `core/system.php:912-965` | A partial dump may continue or fail with secondary warnings instead of one controlled error |
| High | The final `.sql` filename is opened before export succeeds | `core/system.php:879-885` | An interrupted or failed run can leave a file that looks final |
| High | `fwrite()` and `fclose()` results are not checked | `core/system.php:888-893`, `core/system.php:917`, `core/system.php:935-967`, `core/system.php:971` | Disk-full and short-write failures can be followed by compression and success reporting |
| High | Table emptiness is inferred from approximate `SHOW TABLE STATUS.Rows` | `core/system.php:850-856`, `core/system.php:933` | InnoDB estimates can be zero for a non-empty table, causing data omission |
| Medium | SQL is built around `INSERT INTO table VALUES` without a column list | `core/system.php:935-960` | Restore depends on exact physical column order and gives poor diagnostics when schema/data drift |
| Medium | Numeric detection only recognizes integer/year prefixes | `core/system.php:922-927` | Decimal, float, bit, boolean aliases, binary data, and edge values rely on inconsistent serialization paths |
| Medium | Session charset changes are not restored | `core/system.php:788-794`, `core/system.php:902-909` | The shared request connection can remain in the last table charset after the job |
| Medium | Table selection and structure-only engine rules are hardcoded local variables | `core/system.php:739-756` | Operators cannot see or test the effective backup scope from scheduler configuration |
| Medium | Compression returns only a boolean and the backup scans for a guessed extension | `core/system.php:972-982`; `addCompress()` contract at `core/system.php:2351-2385` | Artifact identity is inferred instead of returned and verified |
| High | Scheduler run, unlock, delete, and direct execution mutate state through GET requests | `admin/modules/scheduler.php:72-90`, `admin/modules/scheduler.php:292-341`, `index.php:139-153` | State-changing routes conflict with the project POST/CSRF boundary and can be triggered by navigation or prefetch |
| Medium | The function raises process-wide memory/time settings and never restores the memory limit | `core/system.php:724-735` | One job mutates request runtime state and hides the algorithm's actual memory behavior |
| Medium | Views and other schema objects do not have an explicit support contract | table discovery uses `SHOW TABLES` at `core/system.php:798`, while schema uses `SHOW CREATE TABLE` at `core/system.php:912` | A “database backup” may not recreate views, triggers, routines, or events |
| Low | `$backup_start` is assigned but unused | `core/system.php:724` | It is dead state inside an already oversized function |
| Low | Database export, filesystem writes, compression, logging, and scheduler result assembly share one function | `core/system.php:721-993` | Failure handling and isolated tests are unnecessarily difficult |

## Decisions

### Class and file name

- Class: `Backup`.
- File: `core/classes/backup.php`.
- Public operation: `addDatabaseBackup()`.

The explicit method name prevents ambiguity. `Backup` does not become a generic
container for unrelated backup kinds.

### Database and filesystem backups stay separate

`Backup` owns a database dump. A future file backup should be a separate
`FileBackup` class because its consistency model, exclusions, change detection,
retention, restore process, and resource profile are different.

If a future “full site backup” needs both, a scheduler coordinator may invoke
the two services and write a manifest. It must not merge both implementations
into `Backup`, and neither artifact may be reported as a complete pair until
both succeed.

### Direct migration, not a wrapper

The implementation stage:

1. adds and tests `Backup`;
2. loads it in `core/system.php`;
3. passes the complete scheduler job into the system dispatcher;
4. changes the `backup` arm to instantiate it lazily from database config,
   `dbbackup.settings`, and `BACKUP_DIR`;
5. calls `addDatabaseBackup()`;
6. removes `addBackupTask()` in the same stage;
7. proves no obsolete reference remains.

An `addBackupTask()` wrapper is rejected. There is one caller, so a wrapper
would add indirection without compatibility value.

### Lazy instance

Unlike `Upload`, backup is used only by one scheduler job. The class file may be
loaded with the normal core class list, but the object is created only inside
the `backup` dispatcher arm:

```php
(new Backup($db, $conf['db'], $job['settings'] ?? [], BACKUP_DIR))
    ->addDatabaseBackup()
```

The constructor accepts the existing `Database` instance, database connection
settings, scheduler-job settings, and fixed output directory. It copies only
validated scalar/list values into typed properties and performs no query or
filesystem mutation.

### Ownership boundary

`Backup` owns:

- effective database backup settings;
- server/session capability discovery;
- object and table selection;
- schema and row serialization;
- snapshot lifecycle;
- temporary dump lifecycle;
- compression invocation and artifact checks;
- cleanup and structured result data;
- backup-specific logs.

The scheduler owns:

- HTTP and admin access;
- CSRF token validation;
- job due calculation;
- locking and heartbeat;
- duration and failure count;
- persistence in `storage/logs/scheduler/dbbackup.json`;
- rendering success/failure to the caller.

`addCompress()` remains the final shared compression dependency for ZIP, GZ, and
BZ2 creation. `Backup` always calls it with `$del=false`, calculates the source
SQL SHA-256 first, identifies the output from an explicit algorithm, streams the
artifact back through the matching decompressor, and requires the decompressed
SHA-256 to equal the source hash. Only then does `Backup` delete the SQL source.

This compensates for the helper's boolean-only contract and prevents its
internal source-deletion timing from controlling backup correctness. There is
no wrapper or second compression implementation.

### Public API and result

The production public surface is one method:

```php
public function __construct(
    Database $db,
    array $dbconf,
    array $settings,
    string $dir
)
public function addDatabaseBackup(): array
```

Success preserves the scheduler contract:

```php
[
    'status' => 'success',
    'message' => 'Database backup completed',
    'extra' => [
        'last_backup_file' => 'slaed_2026-07-28_03-30-00-a1b2c3d4.zip',
        'last_backup_size' => 5879589,
        'last_table_count' => 34,
        'last_backup_hash' => '<artifact sha256>',
        'last_sql_hash' => '<sql sha256>',
    ],
]
```

The class adds diagnostic fields under `extra`, such as
dump byte count, row count, duration by phase, engine counts, and a checksum.
The scheduler's existing keys remain unchanged.

Failure returns:

- `status => failed`;
- a stable, non-sensitive operator message;
- no `last_backup_file`;
- optional bounded diagnostic context for logs.

Exceptions do not escape the scheduler boundary. The class catches `Throwable`,
logs once with phase context, restores connection state, removes partial files,
and returns failure.

### Internal method families

Private methods follow the project verb and length rules. Expected families are:

- discovery: `getServerVersion()`, `getTableNames()`, `getTableInfo()`;
- validation: `checkTableName()`, `checkQueryResult()`,
  `checkFileWrite()`;
- naming/path: `getBackupName()`, `getBackupPath()`,
  `getArtifactPath()`;
- dump writing: `addDumpHeader()`, `addTableSchema()`,
  `addTableRows()`;
- SQL state: `setDumpSession()`, `setBufferMode()`,
  `setSnapshotBegin()`, `setSnapshotEnd()`;
- schema: `getSchemaHash()`, `checkObjectTypes()`,
  `checkTableEngine()`;
- cleanup: `deletePartialFile()`.

These are implementation guidance, not permission to create methods that have
only one trivial caller. During implementation, inline one-use pass-through
operations unless a separate error or resource boundary justifies the method.

### Source of truth

There are three distinct sources of truth:

1. The database being exported is authoritative for schema and data.
2. The finalized compressed or SQL artifact plus its checksums is authoritative
   for recoverable backup content.
3. `storage/logs/scheduler/dbbackup.json` is authoritative only for scheduler
   run state and the pointer/checksum metadata of the last artifact.

The scheduler JSON is not proof that the artifact is valid. A success result may
be produced only after the final artifact exists, is readable, has a non-zero
size, passes its format-specific stream check, and decompresses to the exact SQL
checksum. Restore parity is the release and periodic operational acceptance
check; it is not simulated inside the production database.

No new database table or sidecar manifest is required. Scheduler state stores
the exact artifact name, size, artifact SHA-256, decompressed SQL SHA-256, and
measured export counters.

### Atomic artifact lifecycle

One run uses these states:

```text
<name>.sql.part
        |
        | dump complete, all writes checked
        v
<name>.sql
        |
        | optional compression to <name>.part.<ext>
        | decompressed checksum verified
        v
<name>.<zip|gz|bz2> or final <name>.sql
```

Rules:

- append a cryptographically random suffix to the timestamped database label;
- reject any existing path for that run basename;
- open `.sql.part` in exclusive-create mode;
- write only to `.sql.part`;
- check every write for the full expected byte count;
- flush and close successfully;
- rename `.sql.part` to `.sql` only after a complete dump;
- compress to `<name>.part.<ext>` when one is available/configured;
- confirm the exact output path and decompressed content checksum;
- atomically rename the verified compressed temporary file to its final name;
- delete `.sql` only after successful compressed-content verification;
- keep `.sql` as the final artifact when compression mode is `none`;
- remove `.part` and failed output in `finally`;
- after acquiring the scheduler lock, remove only backup partials matching the
  class naming pattern that are older than `lock_timeout`;
- never overwrite an existing artifact.

`auto` resolves once, before writing, in this order: ZIP, GZ, BZ2, then `none`.
An explicit unavailable algorithm fails before creating `.sql.part`; it never
falls back to another compressor. `none` publishes the checked `.sql` artifact.
The `.bak` fallback is removed from database backup semantics.

### Consistency model

The target guarantee has no best-effort branch:

- every data-bearing selected table must use InnoDB;
- every data-bearing selected table must expose a primary key whose ordered
  columns define deterministic export order;
- engines listed in `schemaonly` contribute structure only;
- any other non-transactional data-bearing table fails the run before the
  temporary dump is opened;
- views, triggers, events, functions, and procedures are unsupported in this
  SLAED backup contract and likewise fail the run before output;
- all accepted table data comes from one read-only consistent snapshot;
- schema-changing DDL is detected by comparing ordered schema fingerprints
  before and after export; a difference invalidates and deletes the run.

After discovery and the first schema fingerprint, the class executes:

```sql
SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ;
START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY;
```

The local PHP 8.4 PDO driver was verified to accept both the explicit snapshot
statement and runtime buffered-query switching. The class then switches the
existing PDO connection to
`PDO::MYSQL_ATTR_USE_BUFFERED_QUERY = false` and reads one sequential
forward-only `SELECT` with an explicit ordered column list and `ORDER BY` the
primary-key columns. The statement is exhausted and closed before the next
query. There is no OFFSET, keyset alternative, or table-size-dependent
algorithm. In `finally`, the class rolls back the read-only transaction and
restores the original PDO buffering and session settings.

The plan does not use `SHOW TABLE STATUS.Rows` to decide whether data exists.
The row cursor itself determines emptiness.

### SQL serialization

The dump prologue stores and disables restore-time dependency checks:

```sql
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
```

The epilogue restores both values. Tables are emitted in stable name order.

For every table:

1. validate the identifier before interpolation;
2. use `SHOW CREATE TABLE` and fail if it is unavailable;
3. obtain the ordered column list and exclude generated columns from INSERT;
4. write `INSERT INTO <table> (<columns>) VALUES`;
5. serialize `NULL` as SQL `NULL`;
6. emit binary/bit values as hexadecimal SQL literals;
7. quote every other non-null value through the database/PDO quoting boundary;
8. emit numeric values without quotes only when column metadata and value syntax
   both prove the representation is finite and valid;
9. terminate every statement before moving to the next table.

Prepared placeholders remain mandatory for values where queries accept them.
MySQL object identifiers cannot be placeholders, so table/column identifiers
are validated and quoted before interpolation. No user input reaches these
identifiers.

Batching is controlled by emitted bytes and/or row count, not by average row
length estimates. The implementation records actual rows and bytes.

### Supported database objects

The final contract is deliberately bounded to SLAED base tables:

- InnoDB tables: schema and data;
- engines listed in `schemaonly`: schema only;
- primary keys: required for every data-bearing table and used for stable export
  order;
- generated columns: schema plus exclusion from INSERT;
- binary and bit columns: hexadecimal row values;
- foreign keys: schema plus restore prologue/epilogue with checks disabled only
  inside the dump session.

Views, triggers, events, stored functions, and procedures are not partially
serialized. Discovery uses `information_schema` and fails the complete run if
any exists. This is safer than publishing a database artifact with silently
missing objects and matches the measured SLAED database, which contains none.

### Configuration

The final implementation uses the existing
`$conf['scheduler']['jobs']['dbbackup']['settings']` array. No new config file
or hidden second settings source is added. `addSchedulerSystemJob()` receives
the complete job array instead of only its `system` string, so `Backup` receives
the exact settings of the job that invoked it.

The scheduler job's `active` value is the one enable switch. Manual, pseudo, and
cron runs all reject an inactive job. The unrelated
`$conf['security']['log_b']` gate and its scheduler warning are removed.

The configuration contract is:

| Key | Type | Final default |
|---|---|---|
| `include` | `string` | `*` |
| `exclude` | `string` | `ipb_*` |
| `schemaonly` | `string` | `MRG_MyISAM,MERGE,HEAP,MEMORY` |
| `compress` | `string` | `auto` |

`BACKUP_DIR` remains the fixed destination. Configuration is validated before
any query or write. There is no implicit fallback to hardcoded local variables
after an invalid value.

The source connection keeps `$conf['db']['charset']`; the class does not switch
character sets per table. The dump prologue emits one validated `SET NAMES`
value from that database setting, while each `CREATE TABLE` retains its own
collation.

No new admin settings fields are added. The checked-in scheduler settings are
the explicit operator contract; an admin editor would require separate product
requirements and language constants.

The class does not raise its memory limit. Before dispatch, the scheduler sets
the execution limit to the selected job's validated `lock_timeout`; the default
database-backup value is 1,800 seconds at `config/scheduler.php:31`. Failure to
set the limit is logged, and the run proceeds under the stricter server limit
without changing the class algorithm.

## Implementation steps

### Preflight — define recoverability before changing production

Files:

- `tests/Integration/BackupRestoreTest.php` or the repository's established
  integration-test location;
- test fixtures/scripts under existing test support;
- no production source change.

Work:

1. Inventory actual base tables, views, triggers, events, routines, engines,
   collations, generated columns, binary columns, and largest row sizes.
2. Record current included/excluded tables under the `^ipb_*` filter.
3. Confirm the measured inventory: InnoDB base tables only and no unsupported
   object types.
4. Open the newest local artifact with its format-specific reader and verify SQL
   readability without treating that as a restore test.
5. Restore the current artifact into a disposable database and record every
   failure or parity difference.
6. Create fixtures for empty table, one-row table, more-than-one-batch table,
   nulls, numeric extremes, decimal text, quotes, newlines, UTF-8, binary data,
   generated columns, foreign keys, missing/composite primary keys, and each
   rejected object/engine type.
7. Record baseline peak memory, duration, dump bytes, archive bytes, and row
   counts.

Stop conditions:

- no disposable database is available;
- restoring would target a non-disposable database;
- the current filter excludes an unknown but required application table;
- the measured production database contradicts the recorded object/engine
  inventory.

### Implementation — one atomic final migration

Files:

- add `core/classes/backup.php`;
- update class loading and scheduler dispatch in `core/system.php`;
- update scheduler HTTP handling in `index.php`;
- update the manual-run form and handler in `admin/modules/scheduler.php`;
- add `tests/Unit/BackupTest.php`;
- update `config/scheduler.php` with the final `dbbackup.settings`;
- add or extend the integration test from the preflight;
- update stale unused-code audit entries.

Work:

1. Implement the typed constructor and final `addDatabaseBackup()` contract.
2. Validate `dbbackup.settings`; remove the `security.log_b` coupling and
   enforce the scheduler job's active flag for every trigger.
3. Pass the complete job array through `addSchedulerSystemJob()`.
4. Replace globals inside the implementation with constructor properties.
5. Add a fail-fast boundary for every required SQL operation.
6. Reject unsupported engines/objects, fingerprint schema, start the explicit
   snapshot, and use one unbuffered forward cursor per table.
7. Implement `.sql.part`, checked writes, successful close, explicit
   compression mode, temporary compressed output, atomic publication, exact
   artifact path, format validation, decompressed checksum verification, and
   stale-partial recovery.
8. Emit the restore prologue/epilogue, explicit column lists, generated-column
   exclusion, and correct typed/binary value serialization.
9. Restore PDO buffering, transaction, and session state in `finally`.
10. Fail the whole run on any unsupported object, changed schema, missing
   schema, failed cursor, short write,
   compression error, or artifact mismatch.
11. Record actual table, row, SQL-byte, artifact-byte, engine, and checksum
   metadata.
12. Change the scheduler dispatcher to lazy `Backup` construction.
13. Remove `addBackupTask()`; do not leave a wrapper or alternate path.
14. Replace scheduler run, unlock, and delete links with existing-template POST
    forms; their handlers read POST only and reject GET before mutation.
15. Make the scheduler HTTP endpoint reject non-POST requests.
16. Change the pseudo-cron `fetch()` to POST its trigger and token; external cron
    clients must also POST.
17. Run a full disposable restore and compare the source snapshot with the
    restored database.

The change is merged only as this complete state. There is no commit or
production checkpoint containing a class-shaped copy of the old dump, unchecked
writes, offset paging, guessed archive names, or an untested restore.

## Verification

### Static and automated

- `php -l` on every touched PHP file;
- `phpstan`;
- full `phpunit`;
- `php-cs-fixer --dry-run`;
- `rg -n "\baddBackupTask\s*\("` returns no production definition or call;
- unit tests for filters, identifier rejection, SQL header/schema/data output,
  nulls, numeric values, text escaping, binary data, checked writes, cleanup,
  result metadata, and compression failure;
- integration tests run against both supported MySQL and MariaDB versions where
  project CI provides them.

### Real scheduler routes

Admin route:

```text
POST admin.php
name=scheduler&op=run&job=dbbackup&token=<valid token>
```

Direct scheduler route:

```text
POST index.php?go=3&op=scheduler
job=dbbackup&trigger=manual&token=<valid token>
```

At least one route is executed through a real authenticated HTTP session. The
test verifies the redirect or JSON result and does not call
`addDatabaseBackup()` directly as a substitute.

The admin scheduler row keeps edit as a link and renders run, unlock, and delete
as existing-template POST forms with hidden `name`, `op`, `job`, and `token`
values. The handlers read these through `getVar('post', ...)`. The `go=3`
endpoint accepts POST only and returns 405 without acquiring a lock when called
through GET. Pseudo-cron uses the same POST endpoint through `fetch()`;
configured external cron clients send the scheduler token in the POST body.

Negative route cases:

- invalid token;
- inactive scheduler job;
- GET request to either write route;
- GET requests to admin run, unlock, and delete;
- active scheduler lock;
- controlled unwritable test destination;
- forced SQL failure;
- forced compression failure.

Production `storage/backup` permissions are not changed to manufacture a
failure. Failure injection uses a temporary test destination or injectable
test boundary.

### Persistence and restore

For a successful run verify:

1. `storage/logs/scheduler/dbbackup.json` records `success` and the exact
   basename, size, and table count.
2. Exactly one new final artifact exists and no `.part` or stray `.sql` remains.
3. The stored size matches `filesize()`.
4. The recorded checksum matches a fresh checksum.
5. ZIP opens with exactly one expected SQL entry; GZ/BZ2 streams decompress
   without error; uncompressed SQL opens directly.
6. The decompressed/opened SQL checksum equals the recorded SQL checksum.
7. The final artifact checksum equals the recorded artifact checksum.
8. The SQL restores into a newly created disposable database.
9. Restored schema object names match the supported source scope.
10. Per-table exact row counts match the source snapshot.
11. Primary-key-ordered canonical row checksums match for every table.
12. Foreign-key and application bootstrap checks pass against the restored
    database.

The restored database is the persistence source of truth. Artifact creation,
format validation, and scheduler success are necessary but insufficient.

For a failed run verify:

- scheduler state is `failed`;
- the prior successful artifact and pointer remain intact;
- no new final artifact is published;
- all temporary files are removed;
- connection transaction and charset state are restored.

### Performance

Measure with representative small, medium, and largest supported databases:

- wall time by discovery, schema, data, and compression phase;
- peak PHP memory;
- rows per second;
- SQL bytes and archive ratio;
- database load and lock duration;
- scheduler heartbeat behavior;
- free disk required during `.part` plus archive overlap.

Acceptance targets are derived from the measured baseline and deployment
limits. The class must remain bounded by batch/stream size rather than total
database size.

### Logs

Compare before and after:

- `storage/logs/error_php.log`;
- `storage/logs/error_sql.log`;
- `storage/logs/error_site.log`;
- `storage/logs/scheduler/dbbackup.json`.

One controlled backup failure should produce one primary diagnostic with phase,
safe table name where applicable, and error category. Logs must not contain
database row values, credentials, connection DSNs, or full SQL payloads.

## Security notes

- Scheduler run, unlock, delete, pseudo-cron, and direct execution retain their
  access/token checks but accept only POST for mutation; GET returns 405 before
  locking or persistence.
- No request value is used as a table name, path, compression mode, or SQL
  fragment.
- SQL values use the database quoting boundary; identifiers are validated and
  quoted because placeholders cannot represent identifiers.
- The destination is fixed under `BACKUP_DIR`; filenames use a sanitized
  database label plus server-side time.
- Temporary and final artifacts use restrictive permissions and are never
  served as public downloads.
- Backup files contain password hashes, tokens, private messages, personal data,
  and configuration-derived content; access to them is equivalent to database
  access.
- Logs contain metadata only, never row values or credentials.
- A future remote copy must encrypt transport and authenticate the destination.
- A future encryption feature must keep keys outside the repository and outside
  the backup artifact.
- No template or HTML output is produced by `Backup`.

## Risks and mitigations

| Risk | Mitigation |
|---|---|
| The atomic migration is large to review | keep preflight fixtures and review checkpoints separate, but merge only the one complete final implementation |
| PDO buffering cannot be changed on a supported deployment | capability-check it before opening `.sql.part` and fail the run; do not fall back to buffered or offset reads |
| A long snapshot increases undo history on a busy database | measure duration, use bounded scans, schedule off-peak, and expose engine/runtime metadata |
| A data-bearing MyISAM/Aria/MEMORY table appears | fail before output; only configured structure-only engines may bypass the InnoDB requirement |
| Views/triggers/events/routines appear | fail before output and name the unsupported object category; never publish a partial database artifact |
| A data-bearing table has no primary key | fail before output because deterministic streaming and parity checks are not available |
| Disk fills while both SQL and archive exist | preflight free space from measured source size and fail before writing when margin is insufficient |
| Compression succeeds but produces the wrong or unreadable artifact | choose explicit mode, calculate exact path, open/read it, and checksum before success |
| Cleanup deletes a previous good backup | operate only on the unique current run basename and never glob during failure cleanup |
| Scheduler lock expires during a long run | update heartbeat and measure worst-case duration against `lock_timeout` |
| A restore test damages live data | require a uniquely named disposable database and hard-stop on any non-test target |
| Current dirty worktree overlaps `core/system.php` | re-read current source before implementation and preserve all unrelated user changes |

## Completion criteria

The migration is complete only when:

- `Backup` is the sole database backup implementation;
- `addBackupTask()` and all references are gone;
- the scheduler state contract and new POST-only execution routes work;
- a failed query, write, close, compression, or verification cannot publish
  success;
- artifacts are atomic and partial files are cleaned;
- the consistency guarantee and unsupported object types are explicit;
- at least one real scheduler route creates an artifact;
- that artifact restores into a disposable database with verified schema and
  data parity;
- static analysis, tests, style check, performance measurement, and log review
  pass;
- the progress table records exact implementation results.
