# Points

Points are a site-wide reward balance per account. One class, `Point` in `core/classes/point.php`, is the only
writer of the points journal `_points` and of the fast balance `_users.points`. Every module that rewards an event
calls it with an approved action; no other code changes `_users.points`. Points are independent of ratings: a vote
or its cancellation never reaches `Point`, and `core/classes/rating.php` does not know the class. `Point` has no
HTTP, template, rating or Node dependency.

## Model

### Events

An event is identified by four values that together form the unique key `event` of `_points`:

| Part | Meaning |
|---|---|
| `uid` | Recipient. For an ordinary user action the recipient is also the actor. |
| `action` | One of the fourteen approved actions. Decides the amount. |
| `scope` | Context of the event, for audit, grouping and uniqueness. Never changes meaning or amount. |
| `source` | Server-made stable key of one fact. Never taken from a user request. |

A repeat of the same key is an empty success: nothing is written and nothing is awarded twice, even after the
original award was compensated. Deleting or cancelling a result is not a new action; it is a compensation.

The closed vocabulary (`Point::ACTIONS`):

| Action | Rewards |
|---|---|
| `publish` | a published material or forum topic |
| `comment` | a comment, reply or forum post |
| `view` | a counted view of an object |
| `download` | a counted download |
| `visit` | a counted visit of an external link |
| `poll` | a vote in a poll |
| `favorite` | an item added to favorites |
| `message` | a message or contact request |
| `recommend` | a recommendation of the site |
| `register` | an activated account |
| `login` | a counted sign-in |
| `report` | a report of a broken resource judged useful |
| `moderate` | a finished moderator action |
| `adjust` | a manual correction by an administrator |

Actions are validated in PHP, not by SQL `ENUM` or `CHECK`, so the vocabulary is not part of the DDL.

### Scopes and sources

| Value | Grammar | Length |
|---|---|---|
| `scope` | `^[a-z][a-z0-9.:-]{0,49}$` | 1..50 ASCII |
| `source` | `^[A-Za-z0-9][A-Za-z0-9:._-]{0,63}$` | 1..64 ASCII |

Neither value is ever an SQL identifier, class name, table or path. The scope grammar has no underscore, so an event
whose scope carries one is refused.
Node materials use `node.<type name>`; fixed modules use their module name (see the owner map).

The source prefix `reverse:` is reserved for compensations: an event without `rid` whose source starts with
`reverse:` is refused, and `getEventId()` answers false for such a key. Otherwise an ordinary event could occupy
the key of a future compensation, which would then silently become a repeat.

Most sources are the stable id of the owner's row (see the owner map). An action without a stored row uses
`req:<32 hex>` from 16 random bytes: each HTTP request is a new fact, and abuse is bounded by period and limit.

### Optional data

The optional `$data` of `addEvent()` accepts only the keys `mid`, `aid`, `rid`, `note`, `points`; any other key
refuses the event.

| Key | Type | Rule |
|---|---|---|
| `mid` | int | 0 or positive, at most 4294967295. Related object, for reference and filtering. |
| `aid` | int | 0 or positive, at most 4294967295. Administrator of an administrative event; 0 = automatic. |
| `rid` | int | 0 or positive. Id of the origin row a compensation reverses. |
| `note` | string | Plain text, see `checkNote()`. Empty for automatic events, required for `adjust`. |
| `points` | int | Only for `adjust`: non-zero, `abs() <= 1000000`, requires `aid > 0`, a non-blank note and no `rid`. |

An ordinary action never accepts `points`; its amount comes from the configuration alone.

### Balance

`_users.points` (`INT UNSIGNED NOT NULL DEFAULT 0`, indexed) is the balance every reader uses: profiles, lists,
point groups (`_groups.points` thresholds for groups with `extra != 1`), module access checks. Ordinary reads never
touch `_points`. A non-zero event moves the balance and writes its journal row in one unit under a lock of the
account row, so the starting balance (see the 6.3 carry-over) plus the sum of the journal always equals the
aggregate.

- The journal stores the amount really applied. A debit never takes the balance below zero; the row carries the
  clamped value.
- An event that would lift the balance above 4294967295 is refused, logged, and changes nothing.
- Points do not expire, are not transferred between accounts and are not spent.
- Deleting an account leaves its journal rows: `_points` has no foreign key to users or administrators.
- The visibility of points in the interface (`blocks/user_info.php`, profile, user lists, `getUserTip()`,
  `getUserLevelData()`, the rules page `modules/users` op `rules`) follows `Point::$active`: `points.active = '1'`
  of a configuration that passed its check behind the mark `update.points`, so a closed class shows no points.

### Limits

`period` and `limit` bound how many rewarded events of one action a recipient collects in a sliding window of
`period` seconds. The count covers all scopes of that action, and only origin rows (`rid IS NULL AND points > 0`);
compensations and `adjust` never count, while a compensated origin still counts inside its window. A reached limit
is an empty success: no row, no award, the owner's action is not affected. Source uniqueness holds independently
of period and limit.

The window uses only the database clock: `created` defaults to `CURRENT_TIMESTAMP` and the boundary is computed in
the same statement with `NOW()`. PHP time is never mixed into the comparison.

### Compensation

A compensation reverses one positive origin award exactly once:

1. The owner calls `getEventId()` with the origin key inside its own open transaction. It answers the origin id,
   0 (nothing to reverse) or false (error).
2. For an id above 0 the owner calls `addEvent()` with the same action, scope and recipient, source
   `reverse:<rid>` and `data['rid'] = <rid>`, in the same transaction.

`Point` locks the origin row, checks that it has the same `uid`, `action` and `scope`, `rid IS NULL` and
`points > 0`, and writes one row with `-min(origin points, current balance)`. The amount is never taken from the
caller. A compensation or an `adjust` cannot be compensated (an `adjust` row is never an origin for
`getEventId()`). A compensation is written even when the applied amount is 0, so it can never be repeated after the
balance grows again; the unique key `rid` enforces one compensation per origin. The origin row is never changed or
deleted. `mid`, `aid` and `note` of the compensation row come from the caller's `data`.

Compensations and `adjust` work whatever `active` and the configured amount say. View, visit, message, login,
poll, favorite, register, recommend, report and moderate awards are never compensated.

## Storage

```sql
CREATE TABLE `{prefix}_points` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uid` INT UNSIGNED NOT NULL,
  `aid` INT UNSIGNED NOT NULL DEFAULT 0,
  `action` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `scope` VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `mid` INT UNSIGNED NOT NULL DEFAULT 0,
  `source` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `points` INT NOT NULL,
  `rid` BIGINT UNSIGNED DEFAULT NULL,
  `note` VARCHAR(255) NOT NULL DEFAULT '',
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event` (`uid`, `action`, `scope`, `source`),
  UNIQUE KEY `rid` (`rid`),
  KEY `uid_created` (`uid`, `created`),
  KEY `uid_action_created` (`uid`, `action`, `created`),
  KEY `action_created` (`action`, `created`),
  KEY `created` (`created`),
  KEY `target` (`scope`, `mid`),
  CONSTRAINT `{prefix}_fk_points_rid` FOREIGN KEY (`rid`) REFERENCES `{prefix}_points` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `{prefix}_chk_points_uid` CHECK (`uid` > 0)
) ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate};
```

- `id`/`rid` are `BIGINT`: the journal grows faster than content tables.
- `points`: 1..1000 for a configured award; negative only for a compensation or an `adjust` (never 0 for
  `adjust`; only a compensation row may carry 0).
- Append-only: no `updated`, `status`, IP or JSON column, no garbage collection. Constraint names carry
  `{prefix}_` so two prefixes can share one database.
- Created by `storage/update/sql/table.sql` on install and `storage/update/sql/table_update6_3.sql` on update.

## Configuration

`config/points.php` returns one scope, merged into `config/local.php` like every other configuration file:

```php
return [
    'points' => [
        'active' => '1',
        'actions' => [
            'publish' => ['points' => '10', 'period' => '86400', 'limit' => '10'],
            ...
        ],
    ],
];
```

| Key | Type | Default | Rule |
|---|---|---|---|
| `points.active` | string | `'1'` | `'0'` or `'1'`. `'0'` stops ordinary awards (without any SQL) and hides points in the interface; compensations and `adjust` keep working. |
| `points.actions` | array | 14 rules | Exactly the fourteen actions, each an array of exactly the three keys below. |
| `actions.<name>.points` | string | see below | Canonical decimal, 0..1000. |
| `actions.<name>.period` | string | see below | Canonical decimal, seconds: `'0'` or 60..31536000. |
| `actions.<name>.limit` | string | see below | Canonical decimal: `'0'` or 1..10000. |

Shipped rules:

| Action | points | period | limit |
|---|---|---|---|
| `publish` | 10 | 86400 | 10 |
| `comment` | 5 | 86400 | 30 |
| `view` | 0 | 86400 | 50 |
| `download` | 3 | 86400 | 20 |
| `visit` | 0 | 86400 | 20 |
| `poll` | 3 | 86400 | 10 |
| `favorite` | 0 | 86400 | 20 |
| `message` | 0 | 86400 | 20 |
| `recommend` | 0 | 86400 | 5 |
| `register` | 0 | 0 | 0 |
| `login` | 0 | 86400 | 1 |
| `report` | 3 | 86400 | 5 |
| `moderate` | 0 | 86400 | 100 |
| `adjust` | 0 | 0 | 0 |

Validation (`Point::filterConfig()`), applied once in the constructor to the whole scope:

- Every number is a canonical decimal string matching `^(?:0|[1-9][0-9]{0,8})$`: no native int or bool, no sign,
  space or leading zero. The strings are what `setConfigFile()` writes.
- `period` and `limit` are both `'0'` or both positive.
- `adjust` must be all zeros: its amount comes only from `data['points']` of an authorised administrator.
- Negative configured amounts do not exist; debits happen only through compensation and `adjust`.
- Any wrong, missing or extra key or value makes the whole scope invalid. No partly valid rule is ever applied.

The core builds the class in `core/system.php` before `Comment`:

```php
$pnt = new Point($db, ($conf['update']['points'] ?? '') === '6.3.0' ? ($conf['points'] ?? []) : []);
```

Without the data-update mark `update.points = '6.3.0'` in `config/update.php` the class gets an empty scope. An
exactly empty scope is a subsystem closed on purpose: switched off, no log line. A non-empty invalid scope switches
the class off and writes one error to the site log when the object is built. A clean installation writes the mark
directly; an updated site gets it from the 6.3 points unit. `tools/node-profile.php` builds its own instance from
`$conf['points']` without the mark check.

## Public API

```php
final class Point {
    public private(set) bool $valid;
    public private(set) bool $active;
    public function __construct(Database $db, array $conf)
    public function addEvent(string $action, string $scope, string $source, int $uid, array $data = []): bool
    public function getEventId(string $action, string $scope, string $source, int $uid): int|false
    public function setUserLocks(array $uids): bool
    public function checkNote(string $note): bool
}
```

| Member | Contract |
|---|---|
| `__construct()` | Takes only the checked `points` scope, never the whole configuration. |
| `$valid` | `true` when the scope passed validation. False means closed (empty scope) or broken; an owner reads it to tell a closed subsystem from a failed write. |
| `$active` | `true` when the scope passed validation and `points.active` is `'1'`; the interface of points shows balances only then. |
| `addEvent()` | `true`: stored, or an empty success (repeat, reached limit, zero amount, `active = '0'` for an ordinary award, an `adjust` that applied nothing). `false`: error or refusal. Throws `RuntimeException` when the outcome cannot be proven (see Transactions). |
| `getEventId()` | Positive id of the origin row (`rid IS NULL`, `points > 0`) of the key; `0` when there is confirmed none or the account does not exist; `false` for an invalid class, `adjust`, a bad key, a `reverse:` source, no open transaction, or a failed read. Throws when the owner's transaction is lost. |
| `setUserLocks()` | Locks the `_users` rows of several recipients by ascending id with one `SELECT ... ORDER BY id FOR UPDATE`. Ids that are 0, negative, above 4294967295 or duplicated are dropped. Fewer than two ids: `true` without SQL. Two or more outside a transaction: `false` without SQL. Otherwise `true` or `false` by the statement. |
| `checkNote()` | `true` for valid UTF-8 of at most 255 characters, unchanged by `strip_tags()`, without control characters (`\x00-\x1F`, `\x7F`). The same check `addEvent()` applies; forms call it before they write. |

`false` is always an error or refusal, never "nothing to do". The details of `addEvent()`:

- `$valid === false`: false for every event, including compensations and `adjust`.
- Key or data validation failure: false, no SQL, no log line. This is a bug of the caller.
- An ordinary event (not `adjust`, no `rid`) with `active = '0'` or a configured amount of 0: true, no SQL, and the
  account is not checked.
- A written event on a missing account: false (not logged).
- A compensation whose origin does not exist or does not match: false.
- An `adjust` debit on a zero balance: true, no row; its source stays free.
- Overflow of the balance: false, logged.

`getEventId()` works with rewards switched off. Its locks (account first, then the origin row, both by current
read) are held until the owner's transaction ends. A false answer is logged as a points error with the server
error code; it never means "no origin".

`Point` writes to the site log (`Logger::addSite('error', 'Point: ...')`, with action, scope, source, uid and the
server error code): an invalid non-empty configuration, a refused overflow, an own transaction that could not be
opened, a failed statement of the event unit, and a failed origin read in `getEventId()`. A unique-key violation
answered as a repeat is not logged.

## Transactions and SAVEPOINT

A non-zero event runs as one unit:

1. Lock the account row: `SELECT points FROM _users WHERE id = :uid FOR UPDATE`. The account is always the first
   lock of the unit; this serializes every event of one recipient and keeps the lock order fixed.
2. Look up the event key; an existing row is an empty success.
3. Compensation: lock and check the origin row. `adjust`: clamp to the balance. Ordinary with a limit: count the
   window.
4. Insert the journal row and update `_users.points` with the same amount.

The unit chooses its frame with `Database::checkSqlActive()`:

- No open transaction: `Point` opens its own with `setSqlBegin()` and ends it with `setSqlCommit()` or
  `setSqlRollback()`.
- Inside the owner's transaction: `SAVEPOINT slpoint`, then `RELEASE SAVEPOINT slpoint` on success or
  `ROLLBACK TO SAVEPOINT slpoint` on failure. The name is a fixed trusted identifier.

Outcome rules (`setEventEnd()`):

| Situation | Result |
|---|---|
| Unit succeeded, commit or release confirmed | `true` |
| Commit or release failed | `RuntimeException` (outcome unknown) |
| `SAVEPOINT` refused | `RuntimeException` (outer transaction unknown) |
| Refusal or recoverable SQL failure, rollback confirmed and the outer transaction still active | `false` |
| Server error 1062 (unique key) after a confirmed rollback | `true` (a repeat hidden from the owner's snapshot) |
| Server error 1213 (deadlock) or 1020 (record changed since the snapshot), own or joined | `RuntimeException`: the server rolled back the whole transaction |
| Rollback not confirmed, or outer transaction gone | `RuntimeException` |
| Lock wait timeout 1205 | recoverable: `false` after a confirmed rollback |

After a failure `getEventId()` confirms the owner's transaction with a round trip (`DO 1`) before
`checkSqlActive()`: the connection learns the transaction state only from a server answer. `getSqlError()` keeps
the last error until another replaces it, so it is read right after a false result and never used alone.

What owners must do:

- Call `Point` inside the transaction of the rewarded action, before its `COMMIT`, so both commit together.
- A false award never undoes the main action; `Point` already logged it.
- A `RuntimeException` stops the whole operation, which must not report success. A retry is only a full retry with
  the same source. `NodeService` converts it to `NodeException::STORAGE`; `Comment` rolls its write back; procedural
  owners let it end the request, and the open transaction is rolled back when the connection closes.
- An owner that moves points of several accounts in one transaction calls `setUserLocks()` with all of them before
  its first `addEvent()` or `getEventId()`, so two operations never lock the same accounts crosswise. Users:
  `Comment::deleteTarget()` (authors of the deleted comments plus the extra accounts the caller passes, which is how
  `NodeService::deleteNode()` adds the material author).
- Lock order for Node writes: extension rows, then `Point` accounts, then the journal. `addNode()`, `updateNode()`,
  `updateNodeStatus()` and `deleteNode()` let the extension write its rows before `setPublishJob()` or the
  compensation.

Stale owner snapshot: with `innodb_snapshot_isolation` (MariaDB default from 11.6.2) the account lock answers 1020
and the owner retries whole; without it (MySQL 8, older MariaDB) the unique key stops the insert and 1062 is
answered `true`, the owner's transaction intact.

The limit count inside an owner's transaction is a non-locking read from the owner's snapshot. Without snapshot
isolation, N parallel requests of one recipient can collect up to N-1 awards above the limit, never a duplicate
event or a balance that disagrees with the journal. Do not make the count locking: its gap locks on the journal
index deadlock neighbouring recipients and fail an unrelated owner's action. In the class's own transaction the
snapshot starts after the account lock, so the limit is exact.

## Owner map

Every call site of `addEvent()`/`getEventId()` in the tree. "Never" means the award is not compensated.

| Action | Scope | Source, data | Owner | Moment | Compensation |
|---|---|---|---|---|---|
| `publish` | `node.<type>` | `node:<id>` | `NodeService::setPublishJob()` from `addNode()`, `updateNode()`, `updateNodeStatus()`; `setPublishDue()` from `updateNodePublishList()` | First Published state with a reached date, in the write's transaction; a future date is awarded when the scheduler job `nodepublish` delivers it. Recipient: the registered author. | `deleteNode()`, in the delete transaction; a failed compensation fails the delete |
| `publish` | `forum.topic` | `topic:<id>`, `mid` topic id | `modules/forum/index.php` `send()` | Registered author creates a visible topic, or a moderator edit makes a hidden one visible | Final `delete()` of the topic |
| `comment` | `forum.topic` | `post:<id>`, `mid` topic id | `modules/forum/index.php` `send()` | Registered author creates a visible post, or a moderator edit makes a hidden one visible | Final `delete()` of the post |
| `comment` | `account`, `voting`, `node.<type>` | `comment:<id>`, `mid` target id | `Comment::addComment()`, `Comment::setStatus()` via `updateTargetPoints()` | The comment first becomes visible: added published, or approved | `Comment::deleteComment()`, `Comment::deleteTarget()` |
| `view` | `node.<type>` | `node:<id>` | `NodeService::updateNodeViews()` | Counted view by a registered reader, once per material | Never |
| `download` | `node.<type>` | `asset:<id>` | `NodeService::updateNodeAssetHits()` | Allowed download start of a role with mode `download`, registered visitor, once per resource | Never |
| `visit` | `node.<type>` | `asset:<id>` | `NodeService::updateNodeAssetHits()` | External visit of a role with mode `link` and an `http(s)` source | Never |
| `poll` | `voting` | `poll:<id>` | `updateVotingResult()` in `core/system.php` | Accepted vote of a registered user, once per poll | Never |
| `favorite` | `favorites` | `<module>:<id>`, `mid` target id | `addFavorite()` in `core/user.php` | Item added (Node type or forum topic) | Never, also not when the item or target is removed |
| `message` | `privat` | `privat:<id>` | `addPrivateMessage()` in `core/user.php` | Message stored | Never |
| `message` | `contact` | `req:<32 hex>` | `modules/contact/index.php` `contact()` | Mail queued, registered sender | Never |
| `recommend` | `recommend` | `req:<32 hex>` | `modules/recommend/index.php` `send()` | Mail queued, registered sender | Never |
| `register` | `account` | `user:<uid>` | `modules/account/index.php` `activate()`, `oauthfinish()` | Account activated, or first created through OAuth | Never |
| `login` | `account` | `day:YYYYMMDD` (UTC, `gmdate`) | `setUserLogin()` in `modules/account/index.php`, used by `login()` and `oauthlogin()` | Every successful sign-in; the sliding period still applies across day boundaries | Never |
| `report` | `node.<type>` | `report:<asset id>:<16 hex>` | `NodeService::deleteNodeAssetReport()` with `useful` | Useful report of a registered reporter other than the deciding site account | Never |
| `moderate` | `node.<type>` | `approve:<id>`, `aid` | `NodeService::updateNodeStatus()` | Moderator moves a foreign material from Pending to Published | Never |
| `moderate` | `node.<type>` | `report:<asset id>:<16 hex>`, `aid` | `NodeService::deleteNodeAssetReport()` | Decision on an open report, useful or not, not the moderator's own report | Never |
| `moderate` | `account`, `voting`, `node.<type>` | `comment:<id>`, `mid` target, `aid` | `Comment::setStatus()` via `addModerPoint()` | First publication (`shown` was NULL) of a foreign comment by a moderator of the module | Never |
| `moderate` | `node.<type>` | `reply:<request id>`, `mid` comment id, `aid` | `Comment` via `updateNodeAction()` and `addModerPoint()` | First visible reply of staff in a request of a type with extension `support`: the author is the request's site account, moderates the type and does not own the request; once per staff member and request | Never |
| `adjust` | `account` | `adjust:<32 hex>`, `aid`, `note`, `points` | `addsave()` in `modules/account/admin/index.php` | Administrator correction | Not compensable |
| `adjust` | `account` | `reset:<16 hex>:<uid>:<part>`, `aid`, `note = 'reset'`, `points` | `setPointsReset()` in `modules/account/admin/index.php` | Shared reset | Not compensable |

Rules behind the map:

- `moderate` goes to the site account of the same request (`NodeContext::$uid`; in `Comment`, `getNodeContext()`),
  and the row carries the administrator of the context as `aid`. Moderators are `_admins` accounts with no link to
  `_users`, so a moderator who is not signed in to the site gets no award, and no award is deferred. An own action
  earns nothing: own material, own comment, own report, own request. `approve:<id>` is unique per moderator and
  material, so unpublishing and approving again gives no second award. The award is not compensated when the result
  is later undone.
- Node awards pass no `mid`; `NodeService::addNodePoint()` skips a recipient below 1 and passes only `aid` for
  `moderate`.
- Comment: hiding a comment moves no points. Removal compensates the award whatever the current status, so a
  published, hidden, then removed comment keeps nothing. A refused compensation rolls the removal back. With
  `$valid === false` the removal goes on without compensation and logs a warning. `Comment::deleteUser()` leaves
  points alone.
- Forum: moving to the recycle category moves no points; only the final delete compensates. `delete()` opens one
  transaction for the whole removal, counters of the categories included (`getEventId()` requires one). A post moved to the recycle bin loses its `pid` and
  becomes a topic row, so the final delete of a row without `pid` looks for `topic:<id>` first and then
  `post:<id>`; topic and post ids share one sequence, so both sources never exist for one id. Deleting a topic
  compensates only the topic author's `publish`; the `comment` awards of posts inside it stay. A new post takes its
  id from `getSqlLastId()`.
- Node deferred publication: a future date creates a `_node_publish` job, no early `Point` call. The source stays
  `node:<id>`, so a repeated job never awards twice. Zero, disabled or over the limit: processed without a row;
  `$valid === false`: delivered without award, warning logged; recoverable refusal: job moved 60 seconds on; lost
  transaction: job untouched; author without an account: passed over.
- Re-publication awards for the first time only if no award was recorded before; an existing award, even
  compensated, makes it an empty repeat. Switching points on never awards the existing archive.
- Owners without a transaction of their own open one: forum `delete()`. Like `Comment` and `NodeService` they check the compensation: a
  false `getEventId()` or a refused compensation (already logged by `Point`) rolls the deletion or status change
  back and answers `_ERROR`. The award of these owners stays unchecked, as every award.
- Removed without replacement: page views in `setHead()` (no object and no stable source) and rating rewards in
  `getRatingView()`. `setHead()` and `getRatingView()` never reach `$pnt`.

## Administration

### Rules and journal: `admin/modules/groups.php`

Tab `_POINTS` of the groups screen, ops `points` and `pointssave`.

- `points()` shows the write permission of `config/points.php` (`checkPerms()`), the warning `_POINTS_NOMARK` when
  `update.points` is not `6.3.0`, a radio `active` and a table of the fourteen rules without `adjust` (inputs
  `rule[<action>][points|period|limit]`). Labels are `constant('_POINTS_'.strtoupper($name))`.
- `pointssave()` checks the admin token, reads every value with `getVar(..., 'raw')` (the `text` filter would turn
  `'0'` into an empty string), trims it and checks it against the canonical regex and the same bounds as `Point`
  (points 1000, period 31536000, limit 10000, period/limit pairing, period at least 60). `adjust` is kept as it is.
  Any bad value: `_NONUMVALUE`, nothing written. The file is written only through `setConfigFile('points.php', ...)`
  and its boolean is checked: false gives `_CONFIG_PENDING` when an interrupted configuration operation is pending
  (`getConfigJournal()`), otherwise `_ERROR_UP`. The redirect shows the values read back.
- `getPointsJournal()` (only with the mark) lists `_points` with `_users.name`, ordered `created DESC, id DESC`,
  50 rows per page. Filters: `uid`, `act` (one of the configured actions), `day` (`YYYY-MM-DD`). The page number is
  clamped to 1..100 and the total is counted by `SELECT COUNT(*) FROM (SELECT 1 ... LIMIT 5000)`, so the screen
  costs the same on a journal of any length; older rows are reached through the filters. Columns: id, date, user
  and uid, action, scope / source, points, note.

### Account form: `modules/account/admin/index.php`

- `add()` shows the balance read-only and, for an existing account, the fields `pdiff` (signed difference, text,
  max length 8) and `pnote` (reason, max length 255) plus a hidden `pkey` of 32 hex characters. A form shown again
  after a refusal keeps its `pkey`, except after `_POINTS_TWICE`, which hands it a fresh one.
- `addsave()` reads `pdiff`, `pnote` and `pkey` as `raw` and trims them. `pdiff` must match `^-?[1-9][0-9]{0,6}$`
  with `abs() <= 1000000`, the note must be non-empty and pass `Point::checkNote()`, and `pkey` must be 32 hex;
  otherwise the whole form is refused with `_POINTS_BADDIFF`. `getVar(..., 'num')` is never used for `pdiff`.
  A `pkey` the journal already carries for the account as `adjust:<pkey>` refuses the whole form with
  `_POINTS_TWICE`: the same form sent again, even with another amount, never answers an empty success.
  After the profile update the correction is `addEvent('adjust', 'account', 'adjust:'.$pkey, $uid, ['aid' => ...,
  'note' => ..., 'points' => ...])` in its own `Point` transaction; a false answer redirects to the form with
  `_POINTS_BADSAVE`.

### Shared reset: `modules/account/admin/index.php`

Tab `_NULLPOINTS` (op `pointreset`), submitted to `resave()` with `points = 1`, runs `setPointsReset()`:

- The operation state lives in `$_SESSION[$conf['admin_c'].'-reset']`: a random id (16 hex) and the confirmed
  cursor.
- Accounts are read as `id > cursor AND points > 0 ORDER BY id ASC LIMIT 500`. Each account is one transaction: the
  balance is locked and debited through `adjust` rows of at most 1000000 each (`note = 'reset'`, up to 5000 parts),
  source `reset:<operation>:<uid>:<part>`, then committed; only then does the cursor advance.
- The request budget is 20 seconds. An unfinished reset answers `_POINTS_RESETMORE`; submitting the form again
  continues the same operation with the same sources, so a repeat after a failure debits nothing twice. The state
  is removed when the last batch is done.
- The reset is global and irreversible; it is verified on a disposable schema, never on a live site.

`_users.points` is never written directly by any of these screens.

## 6.3 carry-over

The points unit of the 6.3 data update is `setUpdatePoints()` in `update.php`; it runs after
`storage/update/sql/table_update6_3.sql` succeeded. It concerns only balances and the switch:

- Balances are kept as they are, including earlier rating rewards; nothing is zeroed or recalculated, and no
  historic `adjust` rows are invented. The journal starts with new events. A new account starts at 0.
- Before the first new event the unit writes a snapshot `uid => _users.points`, read in one transaction, to
  `storage/backup/update/points/balances.json`, and a manifest `manifest.json` with its SHA-256, the account count
  and a state (`prepared`, `applying`, `verified`). The snapshot plus the journal restore any account; the journal
  alone cannot restore unknown history.
- A repeat never takes the snapshot again: it resumes from the manifest, and a `verified` unit is skipped. Journal
  rows or an existing `update.points` mark without a manifest stop the unit, because a current balance is never
  taken for a starting one. A snapshot that does not match its manifest stops it too.
- The legacy switch `users.point` becomes `points.active` (`'1'`/`'0'`); `config/points.php` is written only when
  the value differs, and `config/users.php` only when it still carries `point` or `points`, which are removed. The
  shipped `points.php` is otherwise not rewritten. A points scope without fourteen actions or with a bad `active`
  stops the unit.
- Last, the unit writes `update.points = '6.3.0'` into `config/update.php`, which opens the class (see
  Configuration). If that write fails the subsystem stays closed.

The operator procedure of the update is described in `docs/NODE.md`, "The 6.3 update".

## Tests

- `tests/Unit/PointTest.php` runs `tests/Support/point_probe.php` in a separate PHP process. The probe boots the
  real core, creates a disposable schema from the `_users` and `_points` DDL of `storage/update/sql/table.sql`, reseeds it
  for every scenario, reads persistent results through a second connection, and drops the schema; a `Database`
  subclass can make a commit answer unknown. It covers configuration, validation, limits, overflow, compensation,
  savepoints, lock order, unproven outcomes, stale snapshots, concurrent writers (real processes) and the resumable
  shared reset (`setPointsReset()` lifted from the account admin by name).
- `tests/Unit/PointOwnersTest.php` checks the source tree: the files calling `$pnt->addEvent`/`getEventId` are
  exactly the owner map with its event keys; the legacy helpers `updatePoints()`/`addPointsAction()` and
  `users.point(s)` are gone; ratings and `setHead()` never reach `Point`; each `_POINTS_<ACTION>` label exists once
  in all six `lang/*.php`; the lock order; the 100-page journal count. The labels are read only through
  `constant('_POINTS_'.strtoupper($name))`, so this test is where a search by name finds them.
- `tests/Unit/UpdatePointsTest.php` drives `setUpdatePoints()` through `tests/Support/update_probe.php`: a clean
  run keeps starting balances, a repeat never retakes the snapshot, an interrupted run continues, target data
  without a manifest stops the unit, a broken source leaves no mark, the preflight refuses before anything changes.
- Node awards (publish, deferred delivery, delete compensation, report, moderate) are checked in
  `tests/Support/node_probe.php`.
