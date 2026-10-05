# Ratings

The rating subsystem is the class `Rating` in `core/classes/rating.php`. It is the one writer of the vote history,
of the last participation of every voter and, through a trusted adapter, of the aggregate every rated target shows.
It knows neither Node nor Point: a vote never creates, compensates or reaches points. The request builds one
instance through `getRatingService()` in `core/system.php`; the public vote endpoint, the rating widget and the
admin screen `admin.php?name=ratings` all use that instance.

## Model

### Targets and scopes

A target is a pair of a scope and a positive id up to 4294967295. The scope is a closed grammar, which is also the
key of its rule:

| Scope | Target | Aggregate columns (count, sum) | Owner |
|---|---|---|---|
| `account` | a row of `_users` | `votes`, `tvotes` | the user itself (`id`) |
| `forum` | a topic of `_forum` (`pid = 0`); a reply is never a target | `ratings`, `score` | the topic starter (`uid`) |
| `node.<name>` | a material of the registered Node type `<name>` | `_nodes.ratings`, `_nodes.score` | the author (`uid`) |

`<name>` matches `[a-z][a-z0-9]{0,19}`. No scope is ever used as an SQL identifier or a path: the adapters map the
three fixed scopes through a closed array, and `node.<name>` goes through `NodeQuery`/`NodeService` only.

### Actors

The actor is built by the server, never read from the request. `getRatingService()` passes exactly four keys:
`uid` (verified member session, `0` for a guest), `ip` (`getIp()`), `aid` (verified admin session) and `super`
(`isAdmin(true)`). The constructor refuses any other shape, and a `super` actor without a positive `aid`; a refused
actor answers `denied` to every method without a statement.

The stored identity is `u:<uid>` for an account and `g:<address>` for a guest, the address normalized by
`getIpNorm()` so two spellings of one IPv6 address are one guest. An empty or unparsable address, `0.0.0.0` and
`::` identify nobody: such a guest reads ratings with `canvote = false` and every vote is `denied`. An account is
one actor from every address and device; two accounts may share an address. A guest and an account are
independent: logging in or out neither carries nor resets a term. The address of an account is never stored.

### Votes and the aggregate

A vote is a value 1 to 5. Stars send 1..5; the thumbs control sends 1 (down) or 5 (up). The aggregate is a whole
sum and a whole count kept in the owner row; the average is derived, never stored: 5 + 1 gives 6 over 2, average
`3`. A target nobody voted on has no average (`null`).

The aggregate always equals the starting balance of `_rating_targets` (`base`, `votes`) plus the value and the
count of every vote that is not annulled. It is maintained by increment under the lock of the owner row, not
recomputed from history on each vote. Rating checks that the new totals are not negative; the write adapter checks
that the column can hold them.

Nobody changes or removes a vote. The only correction is the annulment of one vote by the main administrator,
with a reason: exactly the stored value and one count leave the aggregate, the vote row keeps `annulled`, `aid`
and `reason`, and the last participation of the voter stays as it was. There is no method and no screen that
resets the ratings of a target or of a scope. History is kept when a target is deleted; an annulment on a target
that is physically gone answers `unavailable` and changes nothing.

### Rules checked on a vote

| Rule | Behaviour |
|---|---|
| Rule of the scope | Missing or invalid rule: the scope is blocked (`blocked`) and the reason is logged once per build. An exactly empty rule set blocks nothing and logs nothing: a scope without a rule is `unavailable`. |
| `active = '0'` | The aggregate stays readable; every vote is `denied`. |
| `guests = '0'` | A guest vote is `denied`. |
| Own target | An account never votes on its own profile, its own forum topic or its own Node material. A guest is nobody's owner; `uid = 0` of a target never proves guest authorship. Products have no owner. |
| Target not reachable | The read adapter answers `null`: `unavailable`, counters hidden. |
| Target reachable but closed | The adapter answers `enabled = false` (Node: `features.rating` off, material not readable to the actor, or the extension refuses `rate`): `denied`, counters shown. |
| Interval | See Periods: `interval` with the remaining seconds. |
| `detail` | A place of display only, never an access rule (see Owner map). |

Cheap refusals happen before any SQL or transaction: the form of the arguments, the missing rule,
`active = '0'`, a guest without an address, a guest where `guests = '0'`. A network repeat of a request that was
accepted before the rating was switched off or closed to guests therefore also answers `denied`; the stored vote
does not change.

### Periods and the delivery key

`period` is a whole number of seconds; `0` means no waiting. A new vote is accepted when
`now - last >= period`, which cannot overflow. `last` moves only together with an accepted vote, so a refusal never
extends the term, and it is kept whatever the interval is: shortening or lengthening `period` applies to existing
terms at once. A `last` in the future is broken data: the vote answers `storage` and is logged.

Every intended click carries a new `request`, 32 lowercase hex characters; a network repeat carries the same one.
The key identifies a delivery, not a permission. A repeat of the same actor, target and key answers the stored vote
again with `duplicate = true`; the same key with another value is `conflict`. The key is checked before the own-vote
rule and the interval, and uniqueness of the key never replaces the interval.

### The database clock

The only clock is the database server: `UNIX_TIMESTAMP()` on the same connection fills `created`, `last` and
`annulled` and measures the interval. `getRating()` reads `now` in the same statement as the actor row; the write
path reads it once with its own `SELECT`. PHP time takes no part.

### Storage

Three InnoDB tables in `storage/update/sql/table.sql`; `scope`, `actor` and `request` are ASCII with binary collation. There
are no foreign keys to the polymorphic targets. All times are Unix seconds.

| Table | Columns | Keys |
|---|---|---|
| `_rating_targets` | `scope` VARCHAR(50), `mid` INT UNSIGNED, `base` BIGINT UNSIGNED (starting sum), `votes` BIGINT UNSIGNED (starting count), `created` BIGINT UNSIGNED | PRIMARY (`scope`, `mid`) |
| `_rating_actors` | `scope`, `mid`, `actor` VARCHAR(50), `last` BIGINT UNSIGNED | PRIMARY (`scope`, `mid`, `actor`) |
| `_rating_votes` | `id` BIGINT UNSIGNED AUTO_INCREMENT, `scope`, `mid`, `actor`, `uid` INT UNSIGNED (0 for a guest), `value` TINYINT UNSIGNED, `request` CHAR(32), `created`, `annulled` (default 0), `aid` INT UNSIGNED (default 0), `reason` VARCHAR(255) (default `''`) | PRIMARY (`id`); UNIQUE `request` (`scope`, `mid`, `actor`, `request`); KEY `target` (`scope`, `mid`, `id`); KEY `scope` (`scope`, `id`); CHECK `value` BETWEEN 1 AND 5 |

A target gets its `_rating_targets` row at its first vote, with a zero balance. A target whose owner row carries a
non-zero aggregate but has no such row was never carried over by the 6.3 update: it takes no vote (`storage`,
logged). A voter has no `_rating_actors` row until the first accepted vote. `KEY target` serves the journal of one
target, `KEY scope` the journal of one scope by cursor.

The old table `_rating` belongs to polls only (`modul = 'voting'`); ratings never read or write it.

### Write order and locks

Every write runs in `Rating::setUnitRun()`:

1. Refuse when the connection already has an open transaction (`storage`, logged): Rating owns its transaction.
2. `BEGIN`.
3. The read adapter with `lock = true`: its first statement is the locking read of the owner row (Node: the type
   row, then the material row). This lock serializes one target.
4. `_rating_targets` row (`FOR UPDATE`, created when missing), then `_rating_actors` row (`FOR UPDATE`), then the
   vote by `request` (`FOR UPDATE`).
5. Insert the vote, insert or update `last`, hand the new totals to the write adapter (Node: the extension follows
   the vote inside the same transaction).
6. `COMMIT`.

The annulment reads the vote once without a lock to learn its target, then takes the same locks in the same order
and reads the vote again `FOR UPDATE`; this avoids a vote-to-target lock inversion. A vote on an account locks only
the rated user, not the voter, so mutual votes never lock a pair of users.

An adapter must not run a plain `SELECT` before its locking read when `lock = true`: under
`innodb_snapshot_isolation` a snapshot taken before the lock wait turns an ordinary race into an error. An adapter
throws on a failed statement of its own; `null` means an unreachable target, never a failure. A throw is caught,
rolled back, logged and answered as `storage`.

Outcomes:

- A unit that wrote nothing or failed is rolled back.
- An unknown `COMMIT` outcome answers `storage`. Repeating the same `request` finds out what happened
  idempotently; a repeated annulment is an empty success.
- A lost race (deadlock, duplicate key) answers `storage`; the repeat of the same `request` is idempotent.

Every failure a client cannot cause answers `storage` and goes to the site log with the prefix `Rating:`: a foreign
open transaction, an aggregate that was never carried over, a `last` in the future, an adapter answer outside its
contract, a negative total on annulment. `invalid`, `denied`, `conflict` and `interval` are client reasons only.

## Configuration

`config/ratings.php` returns the area `ratings`: one rule per scope, keyed `account`, `forum` and
`node.<name>` for every registered Node type. Every rule has exactly four string keys:

| Key | Stored form | Meaning | Default of a new rule |
|---|---|---|---|
| `active` | `'0'` or `'1'` | voting is switched on | `'1'` |
| `period` | canonical decimal string of seconds, a whole number of days, at most `intdiv(PHP_INT_MAX, 86400) * 86400` | interval between two votes of one actor on one target; `'0'` = no waiting | `'2592000'` (30 days) |
| `detail` | `'0'` or `'1'` | the live widget appears only on the detail view of the target | `'1'` |
| `guests` | `'0'` or `'1'` | guests may vote | `'1'` |

The shipped file carries `detail = '0'` for the three fixed scopes and `'1'` for the shipped Node types, all with
`active = '1'`, `period = '2592000'`, `guests = '1'`.

The constructor converts each rule once. Truthy casts are not used: anything but these exact strings makes the rule
invalid. A missing fixed scope, a key outside the grammar and an invalid rule each block their own scope, are
logged, and never fall back to a default. Explicit refusals (`'0'`) are kept.

The rules take effect only behind the mark `update.ratings = '6.3.0'` in `config/update.php`. Without it
`getRatingService()` passes an empty rule set, so no scope has a rule and a vote is `unavailable`, nothing is logged,
`getRatingAsync()` draws no live widget and the settings screen shows a warning instead of the form. A clean installation writes the mark directly.

Writers of the area:

- The admin screen writes the three fixed scopes through `setConfigFile()` in its Closure form, under the shared
  configuration lock.
- `node.<name>` belongs to its type. `NodeService` writes it with the type (`NodeTypeInput::$rating`, checked by
  the same four-key rules, expected type version); `NodeType::$rating` is the checked snapshot, not a second
  configuration. Creating a type without a rule gives it the default rule; clone copies it; deleting the type
  removes only `node.<name>`.

## Public API

### Rating

```php
public function __construct(Database $db, array $conf, array $actor, Closure $read, Closure $write)
public function getRule(string $scope): array
public function getRating(string $scope, int $id): array
public function addRating(string $scope, int $id, int $value, string $request): array
public function deleteRating(int $vote, string $reason): array
public function getRatingList(string $scope = '', int $id = 0, int $after = 0, int $limit = 50): array
public static function getAverage(int $score, int $num, int $digits = 6): ?string
```

`$conf` is the `ratings` area; `$actor` the four-key actor. The class is `final`.

| Method | Who | Behaviour |
|---|---|---|
| `getRule()` | anyone | The checked rule of one scope - `active`, `period`, `detail`, `guests` as bool, int, bool, bool - or `[]` for a scope without a usable rule. |
| `getRating()` | anyone | Reads without writing. A reachable target answers `ok` with its aggregate even when voting is off, closed to guests, own or waiting; `canvote` says whether a new vote would be taken, `wait` speaks of the interval only. Needs a valid rule. |
| `addRating()` | anyone | Places one vote under the rules above. `value` 1..5, `request` `/^[a-f0-9]{32}$/`. |
| `deleteRating()` | `super` only | Annuls one vote. `reason`: valid UTF-8, not blank, at most 255 characters, no markup, no control characters. Needs no rule, no active rating, no open interval. A vote already annulled answers `ok` with `duplicate = true` and changes nothing, reason included; an unknown vote id is `unavailable`. `canvote` and `wait` describe the calling administrator as a voter. |
| `getRatingList()` | `super` only | Pages the journal by `id > after`, ascending, `limit` 1..100, without a count. `scope = ''` lists everything and requires `id = 0`; a non-empty scope must match the grammar; `id > 0` narrows to one target. Needs no rule, so the history of a removed type stays readable. |
| `getAverage()` | static | The one average rule: long division on integers, rounded half up, `digits` clamped to 1..6, no trailing zeros, `null` for `num < 1`. |

The result of `getRating()`, `addRating()` and `deleteRating()` has exactly these keys:

| Key | Type | Meaning |
|---|---|---|
| `ok` | bool | `code === 'ok'` |
| `code` | string | `ok`, `invalid`, `denied`, `unavailable`, `blocked`, `interval`, `conflict`, `storage` |
| `vote` | int | id of the created, repeated or annulled vote; `0` for `getRating()` and when none |
| `score`, `ratings` | int | the aggregate; `0` when the target is hidden |
| `average` | ?string | `getAverage(score, ratings)` with six digits; `null` without votes |
| `wait` | int | seconds left in the interval, never negative |
| `duplicate` | bool | a repeated delivery or a repeated annulment |
| `canvote` | bool | whether a new vote would be taken in the returned state |

`getRatingList()` answers exactly `ok`, `code` (`ok`, `invalid`, `denied`, `storage`), `rows` and `next`. Each row is
the stored columns of `_rating_votes` (`id`, `scope`, `mid`, `actor`, `uid`, `value`, `request`, `created`,
`annulled`, `aid`, `reason`); `next` is the last id of the page, `0` when the page is empty. A failed read answers
`storage` with empty rows.

### Adapters

Both callbacks are built once by `getRatingService()` and are never taken from a request or a configuration.

```php
$read  = function (string $scope, int $id, array $actor, bool $lock): ?array;   // owner, score, ratings, enabled
$write = function (string $scope, int $id, int $score, int $ratings): bool;
```

`read` answers exactly `owner:int`, `score:int`, `ratings:int` (all non-negative) and `enabled:bool`, or `null`
for a target the actor may not reach; any other shape is logged and answered as `storage`. With `lock = true` it
repeats the current rights and locks the owner row as its first statement. `write` stores the checked totals into
the target already locked, without a `COMMIT` of its own; `false` rolls the vote back as `storage`.

The adapters of `getRatingService()`:

| Scope | Reachable for a visitor | Reachable for the main administrator / forum moderator |
|---|---|---|
| `account` | every user row | same |
| `forum` | topic with `time <= NOW()` and `status > 1`, then the read right `pread` of its category (checked after the lock) | the main administrator and a forum moderator (`is_moder('forum') === 1`) reach every topic |
| `node.<name>` | active type; the material as `NodeQuery::getNodeTarget()` shows it to the actor; under the lock `NodeService::getLockedTarget()` (type row, then material row) | the main administrator reaches a material in any state and a disabled type, with `enabled = false` |

For the fixed scopes `enabled` is always `true`. For Node it is true only when the material is readable to the
actor, the type has `features.rating`, and the extension (`checkNodeAction($type, $target, 'rate')`) allows it.
The fixed write adapter refuses totals above 4294967295; the Node write adapter calls
`NodeService::updateNodeRating()`, which also requires `ratings <= score <= 5 * ratings`, and, when the count grew,
`updateNodeAction($type, $target, 'rate', $uid)` of the extension. An annulment therefore works on a disabled
material, a type with `features.rating` off and a disabled type; only a physically missing target is
`unavailable`.

`getRatingService()` loads the Node types before any transaction: a plain read inside the vote would open its
snapshot before the locks.

### Rendering helpers

```php
function getRatingService(): Rating
function getRatingAsync(mixed $typ, mixed $id, mixed $mod, mixed $rat, mixed $scor, string $obj = '', string $stl = ''): string
```

`getRatingAsync()` (`core/helpers.php`) draws the shared block from the aggregate the owner already read (`$rat`
count, `$scor` sum). `$typ`: `0` a list or inline place, `1` the detail view, `2` the inner block alone (the answer
of a vote). `$stl = '1'` selects the thumbs fragment `rating-like`, otherwise the stars fragment `rating-bar`.
`$obj` is appended to the DOM id `rep<id>` so one account shown several times on a page keeps unique ids. The
average shown is `Rating::getAverage()` with two digits.

The block is live only when the mark is set, the rule has `active = '1'`, `$typ` is not `2`, and either `$typ` is
`1` or the rule has `detail != '1'`. Without an active rule and without votes it renders nothing; with votes it
renders the static aggregate. The widget and the rating sums of the profile read the rule through
`getRatingService()->getRule()`, so a rule the class blocks draws no live widget.

## HTTP endpoint

`POST index.php?go=1&op=getRatingView`, handled by `getRatingView()` in `core/system.php`.

- Only `go` and `op` are in the address; nothing else is read from it, so an old link with query parameters is a
  wrong form.
- The body carries `mod`, `id`, `rate`, `request`, `typ` and `token`. Each is checked on the raw value:
  `mod` the scope grammar, `id` `/^[1-9][0-9]{0,9}$/`, `rate` `/^[1-5]$/`, `request` `/^[a-f0-9]{32}$/`,
  `typ` `stars` or `thumbs`. `typ` chooses the fragment of the answer only; it affects neither math nor access.
- The handler checks the method first, then the site token from the POST body with `checkSiteToken()`. For that
  reason `index.php` lists `getRatingView` among the self-guarding handlers of `go=1`, so the shared token check does
  not run first and a `GET` gets 405 rather than a token refusal.
- Every `go=1` response is sent with `Cache::setHeaders()`: `Cache-Control: no-store`.

| Outcome | Status | Body |
|---|---|---|
| accepted or repeated vote | 200 | `getRatingAsync(2, ...)`: the refreshed inner block, without controls |
| method is not POST | 405, `Allow: POST` | alert `_ERROR` |
| token missing or wrong | 403 | alert `_TOKENMISS` |
| a field fails its pattern, `invalid` | 422 | alert `_RATINGS_FORM` |
| `denied` | 403 | alert `_RATINGS_DENY` |
| `unavailable` | 404 | alert `_RATINGS_GONE` |
| `blocked` | 503 | alert `_RATINGS_OFF` |
| `conflict` | 409 | alert `_RATINGS_TWICE` |
| `interval` | 429, `Retry-After: <seconds>` | alert `_RATINGS_WAIT` with the date the next vote opens, in `_DATESTRING` |
| `storage` | 500 | alert `_RATINGS_FAIL` |

A refusal is always its own status with a `warn` alert fragment, never a refreshed block that would hide it, so the
answer reads without JavaScript. The six texts are defined in the six `lang/*.php` files.

Client side (`plugins/system/slaed.js`, `setRatingVotes()`):

- The live container carries `data-sl-rate` and `hx-vals` with `mod`, `id`, `typ` and `token`; each control
  carries `hx-post` and `hx-vals` with its `rate`. The answer replaces the container content
  (`hx-swap="innerHTML"`). The page holds no voting address with parameters.
- On `htmx:configRequest` the control gets a key from `getRequestKey()` (16 random bytes from
  `crypto.getRandomValues()`, shared with comments), stored in `data-sl-rate-key` and sent as `request`.
- On `htmx:afterRequest` a status from 1 to 499 drops the key, so the next click is a new intention; a lost
  connection or a 5xx keeps it, and the repeat of the same click is idempotent.
- A status of 400 or more keeps the widget in place and shows `setToast(text, true)`: the shared `.sl-toast` in its
  warn variant `.sl-toast-warn` with the icon `bi-exclamation-triangle`, the text taken from `.sl-alert-text` of the
  refusal body (at most 200 characters, the status code when empty).

## Admin screens

`admin/modules/ratings.php`, reachable by the main administrator only (`isAdmin(true)`). Three tabs: settings,
journal, docs.

| Route | Method | Behaviour |
|---|---|---|
| `admin.php?name=ratings` | GET | Without the mark: the warning `_RATINGS_NOMARK`, no form. Otherwise one block per scope, the three fixed ones and every registered Node type, with the interval in days and three yes/no switches (`active` `_C_21`, `detail` `_C_22`, `guests` `_RATINGS_GUESTS`). |
| `admin.php?name=ratings&op=save` | POST, token scope `ratings` | Days must be a canonical whole number from 0 to `intdiv(PHP_INT_MAX, 86400)`; otherwise `_RATINGS_BADDAYS` and nothing is written. The posted scope list must equal the current one, otherwise `_NODE_STALE`. The fixed scopes go through `setConfigFile()`; a changed Node rule goes through `updateNodeTypePart($name, 'rating', $rule, $version)` with the posted type version. |
| `admin.php?name=ratings&op=votes` | GET | The journal through `getRatingList()`: filter by scope (`scope`) and target id (`mid`, only with a scope), cursor `after`, 50 rows per page, a next link when the page is full. Columns: id, date, target, voter key, value, annulment state (date, admin id, reason). A row not annulled links to `&vote=<id>`, which shows the reason form above the list. Works without the mark. |
| `admin.php?name=ratings&op=annul` | POST, token scope `ratings` | `deleteRating(vote, reason)`, then a redirect to the journal with `_RATINGS_DONE`, `_RATINGS_FORM`, `_RATINGS_GONE`, `_RATINGS_DENY` or `_RATINGS_FAIL`. |
| `admin.php?name=ratings&op=info` | GET | The docs tab. |

The admin texts `_RATINGS_GUESTS`, `_RATINGS_NOMARK`, `_RATINGS_BADDAYS`, `_RATINGS_VOTES`, `_RATINGS_ANNUL`,
`_RATINGS_REASON`, `_RATINGS_DONE`, `_RATINGS_ACTOR` and `_RATINGS_TARGET` are defined in the six `admin/lang/*.php`
files. Users have no operation to change or withdraw a vote, and the account admin has no mass reset of votes.

## Owner map

Every place that renders a rating reads the aggregate with its own query and calls `getRatingAsync()`; every vote
goes through the one endpoint.

| Place | Call | Scope | Look |
|---|---|---|---|
| `modules/account/index.php`, profile | `getRatingAsync(1, $uid, 'account', ...)` | `account` | thumbs, detail |
| `modules/users/index.php`, user list | `getRatingAsync(1, $id, 'account', ...)` in `rating-box` | `account` | thumbs |
| `core/user.php`, author card of a comment | `getRatingAsync(0, $auid, 'account', ..., $cmid, 1)` | `account` | thumbs, list |
| `modules/forum/index.php`, first post of a topic | `getRatingAsync(1, $fid, 'forum', ...)` | `forum` | thumbs, detail |
| `modules/forum/index.php`, author of each post | `getRatingAsync(0, $uid, 'account', ..., $fid, 1)` | `account` | thumbs, list |
| `modules/node/index.php`, material view | `getRatingAsync(1, $node->id, 'node.<name>', ...)` when `features.rating` | `node.<name>` | stars, detail |
| `NodeView` (`core/classes/node/view.php`) | key `rating` = `Rating::getAverage($node->score, $node->ratings)` | `node.<name>` | six-digit average, no widget |

The Node list also sorts by `rating`. The fragments are `templates/lite/fragments/rating-bar.html`,
`rating-like.html` and `rating-box.html`; the wrapper is the `div` partial with `is_rate`.

## 6.3 carry-over

The ratings unit is `setUpdateRatings()` in `update.php`, run after the points unit and before the fields
unit. It carries the accumulated aggregates of the fixed scopes as starting balances and the real last
participations as terms. It invents no vote and no point event; `_rating_votes` stays empty. Node targets start
with a zero balance, as the update imports no content of the removed modules.

**Targets.** Every row of `_users` and every topic of `_forum` (`pid = 0`), zero
aggregates included, becomes one `_rating_targets` row: `base` = the old sum (`tvotes`, `score`), `votes` = the old
count (`votes`, `ratings`), `created` = the database clock at the moment of the update. After the carry-over a new
vote lands on top of this balance, and its annulment takes exactly that vote back: with 37 over 10 kept, a vote of
5 gives 42 over 11 and its annulment returns 37 over 10. The balance cannot be annulled vote by vote, since the old
single values are unknown.

**Terms.** From the rows of `_rating` for the kept targets, the latest time per target and actor goes into
`_rating_actors.last`: `u:<uid>` for a positive `uid`, otherwise `g:<address>` normalized with
`inet_ntop(inet_pton())` (the same result as `getIpNorm()`; `core/security.php` cannot be loaded by the
first stage of `update.php`). Accounts and guests are never merged by address. A voter without a surviving row has no term, so the
first allowed vote is accepted under the current rules; the schema file of the update keeps only the earliest row of
one address and target, so a later account of that address is such a voter.

**Rules.** A rule stored as the 6.2 string `period|active|detail` becomes the four-key form with `guests = '1'`; a
rule already in the four-key form, including an explicit `guests = '0'` and a `node.<name>` rule, is kept. Keys
outside the grammar (the removed modules) are dropped and counted.

**Preflight.** Nothing is written until everything passes; every problem is listed by table and id:

- an aggregate outside `count = 0 ⇒ sum = 0` and `count <= sum <= 5 * count`;
- a kept `_rating` row whose time is not 1..14 digits or lies after the database clock, or whose guest address
  does not normalize;
- a rule that is neither form, a period that is not a whole number of days, a missing fixed rule.

Nothing is auto-corrected. Rows of polls (`voting`), of other events (`foreign`) and of missing targets of the three
scopes (`orphan`) are counted and left in `_rating`. The existence of the voting account is not checked.

**Manifest.** `storage/backup/update/ratings/` holds `targets.json` (`[scope, mid, base, votes]`), `terms.json`
(`[scope, mid, actor, last]`), `rules.json` (source and converted rules) and `manifest.json` with `version`,
`state` (`prepared`, `applying`, `verified`), a cursor per kind, `moment`, the source hashes, the counters and, once
verified, the target hashes. Writes go in transactions of 500 rows; a row already stored must equal its snapshot,
otherwise the unit stops. Before the rules are published the unit re-reads both tables and compares their hashes
with the snapshots, re-reads the owner aggregates against `targets.json`, and checks that `_rating_votes` is empty
and the number of poll rows is unchanged. Only then does it write `config/ratings.php`, set the state `verified` and
write `update.ratings = '6.3.0'`.

**Repeats.** A unit that finds rows in the new tables, or a mark, without a manifest stops before writing: a
current aggregate is never taken for a starting one. A `prepared` or `applying` manifest continues by its cursor
over its snapshot; a `verified` one only writes the mark again. The update deletes the snapshots after a run
without errors and keeps the manifests. A 6.2 settings source is carried into `config/ratings.php` by the
configuration step of the update before this unit reads it.

## Tests

| File | Covers |
|---|---|
| `tests/Unit/RatingTest.php` with `tests/Support/rating_probe.php` | The class in an isolated CLI process of the real core, on a disposable schema from `storage/update/sql/table.sql`, test adapters, scratch cache, counter and logs. API and independence from Point, Node and the PHP clock; the tables; rules, actor, scale, balance, identity, interval, delivery key, own vote, annulment, journal; each statement failing once, deadlock, unknown commit; concurrent processes. |
| `tests/Unit/RatingOwnersTest.php` | The wiring: `_rating` statements speak of polls only; the vote reads only the POST body and checks the method before the token; `getRatingView` among the self-guarding handlers; the shipped four-key rules and the mark; no mass reset in the account admin; the seven site and nine admin texts in all six locales. |
| `tests/Unit/UpdateRatingsTest.php` with `tests/Support/update_probe.php` (`ratings`) | The carry-over: clean path, resume from `prepared`/`applying`, stop on rows or a mark without a manifest, forged snapshot or moved aggregate, the preflight report, kept four-key rules, a lost mark written again, the order after the points unit and the engine check. |
| `tests/Unit/NodeIntegTest.php`, `tests/Unit/NodeIntegrityTest.php` (`route_probe.php`) | `node.<name>` over real HTTP: live widget only with the feature, unavailable closed/pending/disabled materials, refused wrong token and `GET`; annulment on a disabled material, a type with rating off and a disabled type. |
| `tests/Unit/PointOwnersTest.php` | A rating never awards points. |
