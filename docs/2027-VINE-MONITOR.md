# Vine Monitor 2027

Work plan for the server side of Vine Monitor: a SLAED module `vine` that coordinates several browser agents
(the userscript `vine-monitor.user.js`, v5.0 today) watching one Amazon Vine account (amazon.de), stores the full
journal in MySQL and gives the owner full control and statistics in the admin panel, and read-only statistics to
permitted users in their profile.

Status: planned, nothing implemented. Steps are at the end; one step per session, mark `[x]` as they land.

Depends on `docs/2027-CONNECTOR.md`, steps 1–3: the agents talk to the site only through the connector
(`/api/v1/vine/<action>`), and keys, scopes, limits, logging and the audit log come from there. Until the connector's
step 5 (events) Vine records captcha, idle and resume only as `vine_event` rows; from step 5 it also raises the events
`vine.hit`, `vine.captcha`, `vine.idle`, `vine.resume`, and from step 6 Telegram sends them to the phone.

No line numbers anywhere in this document on purpose: every reference names the function, the file or the constant
it points at, and that name is what to search for.

## Decisions

Taken by the owner on 2026-10-07; settled, not to be reopened by a step.

- **One Amazon account, three or more agents, all of them the owner's.** An agent is one Firefox + Tampermonkey
  profile on one machine (work, home, laptop, …). At no moment do two agents talk to Amazon.
- **The server never talks to Amazon.** It stores, plans and commands. Every Amazon request stays in the browser of
  the active agent, with the session of that browser, exactly as in v5.0.
- **The agent talks to one host only besides amazon.de:** the owner's server, through `GM_xmlhttpRequest`
  (`@connect <domain>`, `anonymous: true`), so the Amazon page never sees these requests and no Amazon cookie goes to
  the server. No other host, no CDN, no analytics.
- **Full journal on the server** (items, cycles, finds, events), retention set in the admin panel, default 90 days.
- **Admin has full control of every agent:** start, pause, stop, check now, make active, resync, new key, delete;
  per-agent settings; a weekly schedule with priorities that decides who runs when.
- **Profile page for permitted users:** statistics and finds only, no agent control, no settings.
- **Server side is a SLAED 6.3 module** (`modules/vine/`, admin part in `modules/vine/admin/`), PHP 8.4, MySQL 8 /
  MariaDB 10, following `.rules/global.md`, `.rules/architecture.md` and `.rules/api.md` without exceptions.
- **The userscript keeps a standalone mode:** with no server configured it behaves exactly like v5.0.
- **Captcha stops the account, not the agent:** a captcha on the active agent pauses every agent, records an event
  and, once Telegram exists, sends an alert; monitoring resumes only when the owner presses «Запуск всех» (after
  solving the captcha in that browser). No automatic hand-over and no timed resume.
- **Server unreachable:** the active agent keeps working for `grace` (default 3600 s = 60 min, set on the server),
  buffering everything; then it stops until the server answers again.
- **Nobody working alert:** when for `idle` (default 1800 s = 30 min, set on the server) inside a schedule window no
  agent holds the lease or the holder has not beaten, the server records an event and, once Telegram exists, sends an
  alert — once per incident and once on recovery.
- **Panel data from the server:** in server mode the panel sections «Находки», «База товаров» and «Статистика» show
  the server's data, so every agent shows the same; the local journal is only the offline buffer and the panel's
  fallback while the server is unreachable.
- **Every admin action on agents goes to the connector's audit log** (`addApiAudit()`).

## Architecture

```
 Agent A (active)            Agent B (standby)        Agent C (paused)
 Firefox + Tampermonkey      Firefox + Tampermonkey   Firefox + Tampermonkey
   │ amazon.de/vine/* only     │ no Amazon requests      │ no Amazon requests
   │                           │                         │
   └── GM_xmlhttpRequest ──────┴──────────┬──────────────┘
                                          ▼
                    https://<domain>/api/v1/vine/<action>   (connector, public/api.php)
                    modules/vine/api.php + common.php ── MySQL {prefix}_vine_*
                                          ▲
                    admin.php?name=vine (owner)   index.php?name=vine (permitted users)
```

- **Agent roles,** decided by the server on every heartbeat: `active` (holds the lease, runs cycles), `standby`
  (beats only), `paused`, `stopped`, `disabled`. A standby agent makes zero requests to Amazon.
- **Lease with a fencing epoch.** The active agent holds a lease `{agent, epoch, until}` renewed by its heartbeat.
  Every hand-over increments `epoch`. A state write with an old epoch is rejected, so a woken-up former active agent
  cannot overwrite the new one. The epoch protects the data; the hand-over rules in "Coordination" protect Amazon from
  two agents at once.
- **The cadence lives on the server.** After each cycle the server sets the next run time (`nrun`, interval and
  backoff from the settings); the active agent only executes it. A hand-over keeps the rhythm.
- **Shared monitor state on the server:** the seen-ASIN set (`known`), the page-1 snapshot, category counters,
  category snapshots, growth waits, the ETV queue, stats counters, cycle number, backoff, category names. The agent
  that becomes active pulls it before its first cycle, so it does not mistake the whole shop window for news.
- **Facts are always accepted, state only from the current epoch.** Items, cycles, finds and events are idempotent
  inserts; `state` and `known` writes require the current epoch.

## Data model

All tables `{prefix}_vine_*`, `ENGINE={engine} DEFAULT CHARSET={charset} COLLATE={collate}`, added to
`storage/update/sql/table.sql` and, for existing sites, to the update SQL the project uses for 6.3 installs.
Times are `DATETIME` in UTC; the admin renders Europe/Berlin; the API sends times as unix milliseconds. Column names
follow the 2–8 lowercase style of the project tables.

| Table | Columns | Keys |
| --- | --- | --- |
| `vine_agent` | `id`, `client` (id in `{prefix}_api_client`, which holds the key and the scopes), `name` VARCHAR(40), `active` BOOL, `mode` TINYINT (1 run, 2 pause, 3 stop), `prio` TINYINT (1 = highest), `sets` TEXT (JSON overrides), `state` VARCHAR(16) (last reported), `outbox` SMALLINT, `qlen` SMALLINT (last reported), `info` VARCHAR(255) (last error), `ver` VARCHAR(16), `ip` VARCHAR(45), `seen` DATETIME, `ctime` DATETIME | PK `id`, UNIQUE `client` |
| `vine_slot` | `id`, `agent`, `dow` TINYINT (0 = Monday … 6 = Sunday, as MySQL `WEEKDAY()`), `smin` SMALLINT, `emin` SMALLINT (minutes of the day, Europe/Berlin) | KEY `agent` |
| `vine_cmd` | `id`, `agent`, `cmd` VARCHAR(16), `args` TEXT, `ctime`, `dtime` (delivered), `atime` (acked), `result` VARCHAR(255) | KEY `agent,atime` |
| `vine_lease` | `id`=1, `agent`, `epoch` INT UNSIGNED, `gtime`, `until`, `nrun`, `next` (agent waiting for a release), `hold` TINYINT (0 none, 1 captcha, 2 owner), `htime`, `prev` TEXT (JSON: agents with `mode` run before the hold), `idleat` (start of an open idle incident, NULL = none) | PK `id` |
| `vine_state` | `name` VARCHAR(32), `val` MEDIUMTEXT (JSON), `epoch`, `seq` INT UNSIGNED (cycle number), `mtime` | PK `name` |
| `vine_known` | `asin` CHAR(10), `pn` VARCHAR(20), `ftime`, `ltime` | PK `asin`, KEY `ltime,asin` |
| `vine_item` | `id` BIGINT, `asin`, `day` DATE (set by the server from `time`, Europe/Berlin), `time`, `agent`, `title` VARCHAR(300), `src` VARCHAR(120), `pn`, `pos` SMALLINT, `par` BOOL, `etv` DECIMAL(9,2) NULL, `etvmin` DECIMAL(9,2) NULL, `nvar` TINYINT, `kw` VARCHAR(60), `stop` VARCHAR(60), `shift` BOOL, `old` BOOL, `expired` BOOL, `tries` TINYINT | PK `id`, UNIQUE `day,asin`, KEY `time`, KEY `etv` |
| `vine_cycle` | `id` BIGINT, `agent`, `time`, `total` INT, `newn`, `shiftn`, `req`, `ms`, `err` VARCHAR(255), `data` MEDIUMTEXT (JSON: p1/p1add/p1gone, cats, grown, checked, deep, subErr) | PK `id`, UNIQUE `agent,time`, KEY `time` |
| `vine_hit` | `id`, `asin`, `time`, `agent`, `etv`, `etvmin`, `kw`, `title`, `src`, `pos`, `shift`, `old`, `thumb` MEDIUMTEXT (`data:image/jpeg;base64,…`, ≤ 16 KB) — no URL column: every link is built from `asin` | PK `id`, UNIQUE `asin`, KEY `time` |
| `vine_event` | `id`, `agent`, `uid` CHAR(16) (outbox id, NULL for server events), `time`, `level` TINYINT, `kind` VARCHAR(20) (start, stop, captcha, login, throttle, error, handover, cmd, idle, resume, hold), `text` VARCHAR(255) | KEY `time`, KEY `agent`, UNIQUE `agent,uid` |

Global configuration in `config/vine.php` (`$conf['vine']`), edited on the admin settings tab:

- **Monitor defaults:** `minetv` 100, `imin` 240, `imax` 360 (seconds), `keywords`, `stopwords`, `depth` 3,
  `cats` 1, `potluck` 3.
- **Coordination (all in seconds):** `beat` 45 (heartbeat), `lease` 180 (lease length), `grace` 3600 (how long the
  active agent keeps working while the server is unreachable, 0 = stop at once), `idle` 1800 (no active agent inside a
  schedule window for this long raises the alert).
- **Data:** `days` 90 (retention), `notifyall` 1 (every agent shows finds as notifications, not only the active
  one), `groups` (user groups allowed to see the profile page).

Per-agent overrides in `vine_agent.sets` may set: `minetv`, `keywords`, `stopwords`, `depth`, `cats`, `sound`,
`notify`. The interval stays global: the cadence belongs to the account, not to an agent.

## API contract

The service `vine` of the connector (`docs/2027-CONNECTOR.md`): route `/api/v1/vine/<action>`, the connector's
envelope, codes, auth (`Authorization: Bearer <key>`), limits, body caps, request log and idempotency. The action
table is `modules/vine/api.php` in the connector's declaration format; the handlers live in `modules/vine/common.php`.
Field types are the connector's `ApiInput` types (`rows` with a column schema for the lists of `push`, `jpeg64` for
thumbnails); handlers only check what a type cannot express (ranges, the caller's own command ids).

- ASIN `^[A-Z0-9]{10}$`, numbers in ranges, strings cut to column length, thumbnails `jpeg64` ≤ 16 KB; prepared
  statements with named placeholders only.
- For `vine:agent` actions the agent of a request is the `vine_agent` row whose `client` is the authenticated client;
  such a client without a row gets `403 no_scope`. `vine:read` actions need no agent row. `ack` accepts only commands
  of the caller's own agent.
- An agent's client is created with two scopes, `vine:agent` and `vine:read`; `items`, `stats` and `finds` need only
  `vine:read`, so the same actions serve a read-only client (a phone, an AI agent), and `stats` is flagged as an MCP
  tool.
- `stats` is one function, `getVineStats()`, used by this action, the admin «Статистика» and the profile page, so
  every place shows the same numbers.
- Every reply carries the connector's `meta.now`; `until`, `nrun` and all other times are unix milliseconds of server
  time.
- Every outbox entry carries `uid`, 16 random base32 characters made by the agent, so a push repeated after a lost
  reply changes nothing.

| Action | Method | Request | Response |
| --- | --- | --- | --- |
| `hello` | POST | `ver`, `ua`, local state | agent `id`/`name`, merged settings + `rev`, role, lease, pending commands |
| `beat` | POST | `state` (running/idle/paused/stopped/captcha/login), `epoch` held, `qlen` (ETV queue length), `outbox` (entries waiting), `last` (last cycle time), `error`, `want` (pause/run from the agent's own panel) | `role`, `lease {epoch, until, nrun, wend}` (`wend` = end of the current window), settings `rev`, `cmds[]` |
| `cfg` | GET | — | merged settings + `rev` |
| `pull` | GET | `cursor` | `state{…}` + `epoch`, `known[]` page (≤ 5000, declared cap; keyset on `ltime,asin`), next `cursor` |
| `push` | POST | `epoch`, `seq` (cycle number), `items[]`, `cycles[]`, `hits[]`, `events[]`, `known[]`, `state{…}`, `done` (cycle finished) | per part: accepted / rejected (`epoch`, `seq`), new `nrun` when `done` |
| `ack` | POST | `cmd` id, `result` | `ok` |
| `hits` | GET | `since` (hit id) | new finds for notifications on every agent when `notifyall` |
| `items` | GET | `days`, `q` (search), `etv` (from), `cursor` | the journal page for the panel's «База товаров» (≤ 100 rows) |
| `stats` | GET | `days` (1, 7, 30, 0 = all) | the panel's «Статистика» ready to draw: KPIs and buckets in the shape of the userscript's `statBuckets` |
| `finds` | GET | `cursor` | the panel's «Находки» list with thumbnails |

`hold` and `resume` (scope `vine:admin`) arrive with the connector's Telegram step: the same as «Пауза для всех» and
«Запуск всех» in the admin, written to the audit log with the linked admin's name.

Commands an agent executes from `cmds[]` (then `ack`):

- `run`, `pause`, `stop`: the mode set by the admin. `pause` can be lifted in the panel of that agent (`beat` with
  `want` = run), except while the account is on hold; `stop` only by the admin.
- `release`: give up the lease now — end the running cycle through the existing `PausedError` path, push the outbox,
  then `ack`; the server hands the lease on only after this ack (see "Coordination").
- `check`: the active agent runs a cycle now; to a standby agent the server sends it only after a `takeover` landed.
- `resync`: drop the local monitor state, `pull` again.

A rotated or revoked key is not a command: the next call gets 401, and the agent stops at once (no grace, no retry),
shows «Ключ отозван или неверен» and beats again only after a new key is entered in «Сервер».

## Coordination

Coordination runs inside `beat` in one transaction with `SELECT … FOR UPDATE` on the `vine_lease` row. All times are
server time.

1. **Candidates:** agents other than the holder with `active`=1, `mode`=run (an agent's own «Пауза» arrives as `beat`
   field `want` and sets its `mode`), seen within `2×beat`, last state not
   `stopped`/`login`, inside one of their `vine_slot` windows now (an agent without slots is always inside); none at
   all while `hold` is set. The `seen` filter never applies to the holder.
2. **Order:** lower `prio` first (1 = highest), then `id`.
3. **Renewal:** the holder's beat renews `until = min(now + lease, wend)`, where `wend` is the end of its current
   window, so the holder stops by itself at its window boundary; an agent without slots has no `wend` (the `beat`
   reply omits it) and gets `until = now + lease`.
4. **The holder loses the lease only when:**
   - it reported `paused`, `stopped`, `login` or `captcha` on a beat — it has already stopped, the lease is free now;
   - it acked `release` — free now;
   - it went silent and server time passed `until + grace + 30 s` — only then can it no longer be running in grace.
5. **Planned hand-over** (window boundary, a higher-priority agent at a boundary, admin «Сделать активным», admin
   pause or stop of the holder): the server sets `next` and sends the holder `release`; on the ack the lease goes to
   `next` with `epoch+1`, `nrun` kept, and an event `handover` records from → to and why. No pre-emption inside a
   window.
6. **Silent holder:** a holder that stopped beating (lid closed, browser crashed, network split) is replaced only by
   rule 4: with the default `grace` of 60 minutes, failover takes up to about 63 minutes. Meanwhile `vinewatch` reports
   the gap as `idle` with the text «активный агент молчит, возможно работает офлайн». The admin can force
   «Сделать активным» earlier only after confirming «возможна параллельная работа до N мин»; that action is audited.
7. **Captcha on the active agent:** the agent stops (StopError, as today) and reports `captcha`; the server stores
   the agents with `mode`=run in `prev`, sets `mode`=pause on every agent, `hold`=1, frees the lease and records the
   event `captcha` (agent, time). Nothing runs until the owner presses «Запуск всех»: that clears `hold`, sets
   `mode`=run on exactly the agents in `prev` and is written to the audit log. «Пауза для всех» stores `prev`, sets
   `mode`=pause on every agent and `hold`=2, sends the holder `pause` (its lease is freed by rule 4) and records the
   event `hold`. **Login expired:** the lease is free at once and goes to the next candidate (the session of one
   browser, not the account).
8. **Server unreachable** (seen by the agent): grace applies only to transport failures — timeout, network, DNS or TLS
   error, HTTP 5xx without the connector's envelope. The active agent keeps working until `until + grace`, buffering
   everything in its outbox and planning cycles from the last `imin`, `imax` and backoff it received. On 401/403 it
   stops at once; on 409 (`conflict` on `beat` or `push` because its `epoch` is no longer the lease's) it becomes
   standby at once; a 409 that carries `Retry-After` (a concurrent idempotent request) is retried, not a demotion. A 503
   `disabled` or `maintenance` with the connector's envelope is not a transport failure: the agent stops cycling at once
   and keeps beating. A standby agent never becomes active without the server.
9. **Nobody working:** the scheduler job `vinewatch` (every 5 min) checks whether the time is inside any agent's
   window, `hold` is not set, and for `idle` either no lease was held or the holder has not beaten (a silent holder,
   rule 6); then it sets `idleat` and records the event `idle` once; the first beat of a holder after it clears
   `idleat` and records `resume`. From the connector's step 5 both also raise `vine.idle` and `vine.resume`.
10. **Next run:** on `push` with `done` the server sets `nrun = now + rand(imin, imax) × 2^backoff` (backoff capped at
    1 h, as in v5.0) and returns it.

## Userscript v6 (server mode)

Changes to `vine-monitor.user.js`; everything not named here stays as in v5.0.

- **Header:** `@grant GM_xmlhttpRequest`, `@connect <domain>` (the only added host).
- **Settings tab, new block «Сервер»:** address, agent key (input, stored in GM storage, never shown again in full),
  status line «Сервер: подключено · агент „Дом“ · активен / в резерве / на паузе / остановлен сервером». An empty
  address = standalone mode.
- **Transport:** `GM_xmlhttpRequest` with `anonymous: true`, 15 s timeout, `Authorization: Bearer <key>`, the
  connector's envelope; `429` honours `Retry-After`; a failed call is retried on the next beat, never in a loop.
- **Heartbeat loop:** `beat` every `beat` s ± 20 % jitter in the Vine tab that holds the local tab lock (the existing
  `acquireLock` stays: one agent per browser).
- **Role gate:** `cycle()` starts only while the role is `active` and, in server time, `now < until − 10 s` — or, when
  the server is unreachable by a transport failure, `now < until + grace`. Standby, paused, stopped: no Amazon request
  at all; the panel shows the role instead of the countdown.
- **Clock:** with every reply the agent stores the offset to `meta.now` together with `Date.now()` and
  `performance.now()`; if the two elapsed times later differ by more than 2 s (sleep, NTP step, manual change), the
  lease counts as invalid until the next successful beat.
- **Cadence:** the timer uses `nrun` from the server; the local interval settings are hidden in server mode.
- **Becoming active:** `pull` first (state + known delta) and replace the local monitor state, then the first cycle.
  **Becoming standby or getting `release`:** abort the running cycle through `PausedError`, stop the timer, push the
  outbox, keep what was not accepted.
- **Outbox:** every log entry, cycle record, find, event and new/seen ASIN is appended to GM key `vmOutbox` with its
  `uid`; `push` sends up to 500 entries and less than the action's `size` per call, removes what the server accepted and
  what it rejected for `epoch` or `seq` (those can never be accepted), keeps only unanswered ones; cap 20,000 entries
  (oldest cycle records dropped first). State (`lastSnap`, `prevCats`, `catP1`, `growWait`, `etvQueue`, `stats`,
  `cycleNo`, `backoff`) is pushed with `epoch` and `seq` = `cycleNo` after every cycle; the server keeps it only if the
  epoch is current and `seq` is not below the stored one.
- **Finds and notifications:** the active agent notifies as today and pushes the find with its thumbnail; with
  `notifyall` every other agent polls `hits` on its beat and shows the same notification once (local seen-id).
- **Commands:** executed from `cmds[]`, then `ack`; the panel shows the last command («Пауза от администратора»).
- **Panel sections from the server:** «Находки», «База товаров» and «Статистика» call `finds`, `items`, `stats` when
  a section is opened or its period changes, cache the answer in the tab for 60 s and never call during a cycle.
  Drawing stays the v5.0 code with one hard rule: every string from the server goes through `esc()` (or
  `textContent`), every link is rebuilt by the agent from the ASIN (`https://www.amazon.de/dp/<asin>`, through
  `safeHref`), and a thumbnail is used only if it starts with `data:image/jpeg;base64,`. Without the server the
  sections show the local data with the line «Нет связи с сервером — данные этого компьютера за 7 дней».
- **Local journal:** kept for 7 days in server mode as the offline buffer and the panel's fallback only.
- **Settings precedence:** server settings replace the local ones in server mode; the local form is read-only and
  says «Настройки задаются на сервере».

## Admin panel

`admin.php?name=vine`, tabs through `getTplAdminTabs()`, handlers in `switch ($op)` named by the admin naming rule
(e.g. `start`, `pause`, `stop`, `takeover`), CSRF on every POST (`getSiteToken`/`checkSiteToken`), markup in theme
partials, no HTML in PHP. All visible text through the module's language constants in six languages.

- **Обзор (state page):** who is active and since when, lease `epoch` and `until`, `nrun`, every agent's last beat
  and its age, outbox length reported by each agent, last cycle, today's new items and finds, errors of the last
  24 h; a red banner while `hold` is set or the `idle` alert is open; global buttons «Пауза для всех» / «Запуск всех».
- **Агенты:** table — name, role, reported state, last seen, version, IP, last error, priority; actions «Запуск»,
  «Пауза», «Стоп», «Проверить сейчас», «Сделать активным», «Ресинхронизация», «Новый ключ», «Удалить»; form «Новый
  агент» (name, priority) creates the connector client with the scopes `vine:agent` and `vine:read` through
  `ApiClient::addClient()` and shows the key once; «Новый ключ» uses `setClientKey()`, «Удалить» `deleteClient()`.
- **Расписание:** per agent a weekly grid of windows (day, from, to, Europe/Berlin) and the priority; a preview line
  «Сейчас должен работать: …».
- **Настройки:** global monitor defaults, coordination values, retention, `notifyall`, profile groups; per-agent
  overrides on the agent's edit form.
- **Статистика:** periods 24 h / 7 days / 30 days / all; everything of the panel (KPIs, new per hour/day/week with the
  ETV split, hour of day, «Товаров в Vine») plus weekday × hour heatmap, ETV distribution, top categories, new items
  vs page position, per agent: cycles, requests, errors, active hours, hand-overs; Amazon requests per hour (a load
  guard).
- **Журнал:** items with filters (period, ETV from–to, search, category, agent, new/shift/old), pager, export
  CSV/JSON; a CSV cell starting with `=`, `+`, `-`, `@`, tab or carriage return gets a leading `'`.
- **Находки:** list with thumbnails, links built from the ASIN.
- **События:** filter by agent and kind (incl. `captcha`, `idle`, `resume`, `handover`, `hold`); per agent also what
  the connector logged for its client (`getClientLog()`: errors, 401, 429).
- **Справка:** `modules/vine/admin/info/ru.md` — install, keys, `@connect`.

Every action on this panel that changes an agent, a schedule or a setting is written with `addApiAudit()`.

Charts: SVG drawn by a small script under `public/plugins/vine/` from JSON the admin page embeds (a port of the
userscript's chart and tooltip functions); colours and sizes from theme tokens.

## Profile page

`index.php?name=vine`, visible only to logged-in users of the groups in `$conf['vine']['groups']` (and admins): the
statistics tab and the finds list of the admin panel, read-only; no agents, no settings, no keys, no IPs.

## Retention and maintenance

- **Scheduler job `vineclean`** (daily, like `cachegc`; an entry in `config/scheduler.php` and a branch in
  `addSchedulerSystemJob()`, the connector's pattern): delete `vine_item`, `vine_cycle`, `vine_hit` and `vine_event`
  older than `days`, acked `vine_cmd` older than 7 days, and `vine_known` rows whose `ltime` is older than 30 days,
  always keeping the newest 60,000.
- **Aggregates only if needed:** if a statistics query over the whole retention exceeds 300 ms on real data, add
  `vine_hour` (`hour`, `newn`, `hin`, `total`) filled by `push`; not before.

## Security checklist

- Keys, HTTPS, limits, failed-auth logging: the connector's (`docs/2027-CONNECTOR.md`, section "Security").
- No Amazon cookie, header or page content leaves the browser except the parsed fields listed in the data model.
- Every input validated and length-capped; output escaped at the template boundary; titles, keywords and sources are
  untrusted text everywhere — admin, profile, panel, CSV, Telegram, MCP.
- No link is ever taken from an agent: admin, profile, panel and Telegram build it from the ASIN.

## Tests

- **Unit (`tests/Unit/`):** the coordination table — holder renewing, silent holder before and after
  `until + grace + 30 s`, holder reporting paused/stopped/login/captcha, planned hand-over waits for the `release` ack,
  `until` capped at `wend`, outside window, takeover, two agents with equal priority, a candidate with an old `seen`;
  captcha (all agents paused, `prev` stored, «Запуск всех» restores exactly `prev`); `vinewatch` (alert once inside a
  window, also for a silent holder, none outside or on hold, `resume` once); epoch and `seq` fencing of `push`; a
  repeated push changes nothing (`uid`); `stats` equals SQL counts; validation of each field.
- **Structure:** `ModuleStructureTest`, `LanguageValidationTest` (all six languages), `SecurityValidationTest`,
  `PhpFileFormatTest`; the `/schema` command (database vs `table.sql`) reports no drift.
- **Userscript:** the jsdom harness gets a mock server (hello/beat/cfg/pull/push/ack/hits/items/stats/finds) — role
  gate, `until − 10 s`, grace only on transport failures, 401 stops at once, 409 on a stale epoch makes standby, 409
  with `Retry-After` is retried, 503 `disabled` stops cycling, outbox and `uid`, pull on activation, `release`,
  commands, notifications on a standby agent, panel sections from the server and the offline fallback line, a title
  `<img src=x onerror=…>` rendered as text, a server URL ignored in favour of the ASIN, clock offset (agent clock 10 min
  wrong still honours `until` and `nrun`) and a clock jump during a lease, standalone mode unchanged.
- **Field:** three Firefox profiles on two machines; scripted scenarios — close the active tab, sleep the laptop,
  server down 10 min, window boundary, admin pause/stop/takeover, captcha on the active one; after each, at no moment
  did two agents send Amazon requests (compare `vine_cycle.agent` times) and no item is missing or duplicated.

## Open questions

None; all decided on 2026-10-07 (section "Decisions"). A new question goes here before the step that needs it.

## Steps

- [ ] 1. Module skeleton: `modules/vine/` (index, common, api, lang ×6, admin/index, admin/lang ×6, admin/info/ru.md),
  `config/vine.php`, entry in `config/modules.php`, tables in `table.sql` + update SQL. Done when the structure,
  language and format tests pass and the `/schema` command reports no drift.
- [ ] 2. Service `vine` on the connector: action table, `hello`, `beat` (without coordination), `cfg`; agents tab with
  create / new key / delete through `ApiClient`, each written with `addApiAudit()`; requests in `tools/api.http`.
  Done when a curl with an agent key gets the merged settings, a key without `vine:agent` gets 403, a wrong key 401
  (the connector's tests cover limits).
- [ ] 3. Coordination: lease with `next`, `release` and `wend`, epoch, slots, candidates, `hold` with `prev` (the hold
  and resume functions; step 6 wires the admin buttons to them), commands queue, delivery and `ack`, scheduler job
  `vinewatch`. Done when the unit table passes.
- [ ] 4. Data: `push` (facts idempotent by their unique keys, events by `uid`; state by epoch and `seq`), `pull` with
  known paging, `hits`, `items`, `stats` (`getVineStats()`), `finds`, `nrun` on `done`; requests in `tools/api.http`.
  Done when a re-push changes nothing, a stale-epoch state write is rejected and facts from it are kept, and `stats`
  equals SQL counts.
- [ ] 5. Userscript v6 server mode: settings block, transport, heartbeat, clock offset and jump check, role gate,
  `nrun`, pull on activation, `release`, outbox, commands, finds on all agents, panel sections from the server with
  the escaping rule and the offline fallback; standalone unchanged. Done when the harness with the mock server passes
  and the v5.0 suite still passes.
- [ ] 6. Admin: Обзор, Агенты (all actions), Расписание, Настройки (global + per agent), audit of every action. Done
  when every action works through the browser, appears in the audit log and the error logs stay empty.
- [ ] 7. Admin: Статистика, Журнал (+ export), Находки, События; chart script under `public/plugins/vine/`. Done when
  the numbers match SQL counts on a test data set and `npm run ui:gates` passes.
- [ ] 8. Profile page with group check. Done when a user outside the groups gets 403 and a permitted one sees only
  statistics and finds.
- [ ] 9. Retention job `vineclean`, query timing on a year of synthetic data, security review of the API. Done when
  the job deletes exactly what it should and every statistics page answers under 300 ms.
- [ ] 10. Field test with three agents (scenarios above). Done when no scenario shows two agents talking to Amazon at
  the same time and the journal is complete.
