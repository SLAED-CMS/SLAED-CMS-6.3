# Connector 2027

Work plan for SLAED's single machine-to-machine entry point: the connector. Every client that is not a browser page
of the site — a Vine Monitor agent, a headless front end, a Telegram bot, an AI agent, another SLAED site asking for
updates — talks to the site through it, and the site talks to the outside world (update server, Telegram Bot API, AI
providers) through the same layer.

Status: planned, nothing implemented. The updates, the entry of the API and steps 1–2 moved into
`docs/11-UPDATE-2026.md` before 8.0 (owner, 2026-10-15); this plan starts after them, with step 3.
The owner's decisions of 2026-10-07 and 2026-10-14 are in "Decisions"; "Recommended
decisions" and everything below it is the working design, changed only through "Open questions". The steps are at the
end, one per session. The binding coding rules are in `.rules/api.md`; where this plan and that file differ, the file
wins and this plan is corrected.

No line numbers anywhere in this document on purpose: every reference names the function, the file or the constant
it points at, and that name is what to search for.

## Why one connector

Without it every feature grows its own door: `go=3&op=scheduler` today, `op=vine` tomorrow, a Telegram webhook, an
update check, a JSON route per module. Each door needs auth, limits, validation, JSON errors, logging and a kill
switch, and each would get them slightly differently. The connector does these once; a service only declares its
actions and writes their bodies.

## Prior art

What the connector takes from LAAS CMS (`E:\OSPanel\home\laas.loc`, `docs/API.md`, `docs/ROUTES.md`,
`modules/Api/`) and from other CMSs — the contract and the ideas, not the code: LAAS runs on Composer, a kernel and a
middleware queue, which `docs/PRINCIPLES.md` rules out here.

- **LAAS:**
  - `public/api.php` beside `index.php`, prefix `/api/v1`, a `v2` beside `v1`;
  - the envelope `{ok, data, meta}` / `{ok:false, error:{code, message, details}}`;
  - Bearer personal tokens `LAAS_<prefix>.<secret>` stored as SHA-256 and shown once, with rotate, revoke and
    expiry;
  - a separate API rate bucket with `Retry-After`;
  - CORS deny by default with an origin allowlist;
  - uniform auth errors, no logging of `Authorization`, `Cache-Control: no-store` on auth routes;
  - AI routes `ai/tools`, `ai/run`, `ai/propose` read-only or draft-only, apply only after a human confirms.

  To avoid: scopes kept in a central route → scope map in `config/api.php` and auth rules hard-coded by path in
  `ApiMiddleware` — a new module cannot declare its own routes without editing the core.
- **Joomla 4:** a separate API application under `/api/index.php`; components ship their own API controllers and a
  webservices plugin registers their routes — the closest model to a separate light entry with extensible services.
- **WordPress:** one front controller, the REST API under `/wp-json/` (plain form `?rest_route=`), any plugin calls
  `register_rest_route()` on `rest_api_init` with its own `permission_callback` — extensible by declaration, every
  route carries its own permission.
- **Bitrix:** the `rest` module answers under `/rest/`; a module adds methods through the event
  `OnRestServiceBuildDescription` with a scope per method; incoming webhooks are keys with a set of scopes.

Common to all: one prefix, one auth and envelope layer, routes contributed by modules with their permission declared
next to the route. That is what the connector does in SLAED terms.

## Decisions

Taken by the owner on 2026-10-07; settled, not to be reopened by a step.

- **One entry, the branch `/api/` of `public/index.php`** (owner, 2026-10-15, in place of the own entry
  `public/api.php` of 2026-10-07): no new rewrite rule on either server, one door for every machine client.
- **slaed.net is the master:** the update server of every SLAED site — now the module `master` and the core page
  "Обновления" of `docs/11-UPDATE-2026.md`, built before 8.0.
- **Anonymous access only to safe actions:** an action is open without a key only if it is `GET`/`HEAD`, declared
  `'auth' => 'public'`, read-only and returns only data a guest may see on the site anyway; it is rate-limited per IP
  and cacheable. Everything else needs a key or, for an inbound webhook, its declared secret.
- **No Composer, the system ships clean:** no runtime dependency, no vendored framework. A framework kernel is not
  adopted; the connector gets its own minimal pipeline (section "Kernel"), which is the part of a framework that pays
  off here.
- **MCP in the `ai` step** (step 9): an inbound MCP endpoint for AI agents, after `content` reads exist.
- **Full, safe administrator access for AI agents** (owner, 2026-10-14): an agent may do over MCP what the
  administrator it acts for may do in the panel, if the site owner — the main administrator — opens it for that key.
  It is off by default; "safe" means every action has a risk class with an owner-set policy, a short-lived key, a
  fixed list of actions no agent ever gets, and an audit trail (section "Administrator access"). This supersedes
  "read-only or draft-only" for administrator keys; a user key stays draft-only.
- **Update channels, the two signing keys and the check on by default with a notice** — decided here on 2026-10-07,
  carried into `docs/11-UPDATE-2026.md`, which holds them now.
- **Step order:** Vine right after clients and guards; outbound, queue and events right before Telegram (section
  "Steps"). Vine needs none of the queue, and the queue is built for its first real consumer.
- **Operations:** one key per caller (no shared keys), an audit log of admin actions, a status page, the server clock
  in every reply, a live API reference `system/schema`.
- **Developer tools:** `tools/api.http` for the VS Code REST Client, `tools/apikey.php` for dev keys, release builds
  from a git tag by `tools/release.php`.
- **Later, not in the steps:** staged rollout of `stable` (section "Later").

## Recommended decisions

- **How the API is entered:** JSON only, no theme, no template, no session, no HTML error page. `public/index.php`
  takes a path under `/api/` right after its light path of `uploads/`, before the core boots; every other path is the
  site. The fallback of both servers already sends every path that is no file to `index.php`, so no rewrite rule is
  added; `api` is reserved in `Route::TYPES`. There is no query form: readable addresses are mandatory. The SLAED boot
  loads the theme and the template object on every request, and a Vine agent beats every 45 s — the branch before
  the boot pays off, and a fault in the API cannot render a site page.
- **A light boot of the core:** the branch defines `API_FILE`; `core/system.php` accepts it beside `MODULE_FILE` and
  `ADMIN_FILE` and skips the theme, the template object and everything visual. Config, DB, security, logger, cache,
  mail and the classes that services need stay loaded. Built by `docs/11-UPDATE-2026.md`, batch 1.
- **One client registry for every caller:** a client is a key with scopes. A Vine agent, a headless app, an AI agent
  and a partner site are rows of the same table; a service never invents its own key scheme.
- **Inbound and outbound in one layer:** `ApiHttp` is the only place API code calls external hosts, with a host
  allowlist in config, SSRF protection, timeouts and logging. The existing `getSchedulerFetch()` moves onto it, but
  its own-site calls keep working: `addSchedulerCustom()` accepts `http://` and a relative address resolved against
  `$conf['homeurl']`, which on a dev host is plain http or loopback, so a call to the site's own host is exempt from
  the HTTPS and loopback rules of "Security"; only a foreign host goes through them.
- **Slow work never runs inside a request:** a webhook or an AI request that calls an external host or may exceed 1 s
  enqueues a job; the existing scheduler drains the queue.
- **No new runtime dependency** (`docs/PRINCIPLES.md`): plain PHP 8.4 with its bundled extensions (`json`, `sodium`,
  `hash`); outbound HTTP through PHP streams, as `getSchedulerFetch()` does today, so `curl` is not needed. A missing
  extension disables the feature that needs it with a clear message in the admin (no `sodium` → no update apply).
- **Versioned contract:** `v1` in the path; a breaking change is `v2` beside `v1`, never in place.
- **Node gains a headless read API.** `docs/NODE.md` says today that Node offers no public headless API; this plan
  changes that sentence on purpose (content service, read first, write later through the shared writer).
- **Scheduler jobs register the way the core does when the step runs:** connector and Vine jobs (`apiclean`,
  `apiqueue`, `updatecheck`, `vinewatch`, `vineclean`). Today that is three places, as for `nodepublish`: an entry in
  `config/scheduler.php` (`'type' => 'system'`, `'system' => '<name>'`), a branch in the `match` of
  `addSchedulerSystemJob()` and a key in the static map of `getSchedulerJob()` (`core/system.php`), plus the step of
  `update.php` that seeds the job on an installed site. Once step 2 of `docs/2027-CORE.md` (module-declared jobs) has
  landed, the connector jobs stand in the one list of the core and the Vine jobs in `modules/vine/scheduler.php`.
  This plan adds no job mechanism of its own.

## Kernel

Why no framework kernel (PSR-7/15, a container, a middleware stack):

- **It would be a second architecture** beside the procedural SLAED core: two ways to read input, two error paths, two
  boot sequences — the cost lands on every module, not only on the API.
- **The useful part is small:** an ordered list of guards around a handler. That is about 300 lines of SLAED-style
  PHP and needs no interface package.
- **Shipping clean:** a framework means either Composer or a vendored copy to keep updated; both contradict the
  delivery model.

What the connector has instead: `ApiRouter::getResponse()` runs a fixed guard list and returns the reply array;
the API branch of `public/index.php` passes it to `setApiReply()`, the only function that writes headers and body.
The guards, in order:

1. `checkApiRoute` — parse the path, find service and action: 404 `no_route`, 405 `no_method`.
2. `checkApiMaint` — maintenance flag (everything but `system` gets 503 `maintenance`) and the kill switch per
   service and action (503 `disabled`).
3. `checkApiCors` — answers an `OPTIONS` preflight with 204 against the global origin allowlist, without auth; a
   present `Origin` not on that list gets 403; sets `Vary: Origin` on every reply, errors included.
4. `checkApiFlood` — the per-IP bucket, before any database lookup.
5. `checkApiAuth` — by the action's declared `auth`: `key` (Bearer; a client with `ips` set is refused from any other
   IP), `secret` (declared header), `public` (none).
6. `checkApiScope` — the action's scope must be among the client's scopes.
7. `checkApiOrigin` — a browser request's `Origin` must be among the client's own `origins`.
8. `checkApiLimit` — the per-client bucket.
9. `checkApiSize` — body size against the action's `size` or the default.
10. `filterApiInput` — typed reads of the declared params into `$in`.

Then the handler. Each guard returns normally or throws `ApiError`; the list lives in one place, and a service cannot
reorder it.

Calling another service's action (MCP `tools/call`, a Telegram command) goes only through
`ApiRouter::getActionResult($service, $action, $in, $cli)`, which repeats steps 2, 6, 8 and 10 for the target action;
the caller's own scope is never enough.

When a kernel would be justified: if the frontend and the admin also moved to request/response objects — a separate
decision for all of SLAED, not for this plan.

## Architecture

```
 clients                         public/index.php /api/  (API_FILE, light core)
 ───────                         ──────────────────────────────────────
 Vine agents        ─┐           ApiRouter::getResponse()
 headless front end ─┤   HTTPS     ├─ route      /v1/<service>/<action>
 Telegram (webhook) ─┼──────────►  ├─ maint      maintenance, kill switch
 AI agents (MCP)    ─┤             ├─ cors, IP limit
 other SLAED sites  ─┘             ├─ auth       key | secret header | public
                                   ├─ scope, origin, client limit, size
                                   ├─ input      declared params
                                   ├─ call       handler → array
                                   └─ reply      setApiReply(): envelope or JSON-RPC, log, request id
                                       │
                 services: vine · content · telegram · ai · master · system
                                       │
                                 ApiQueue ◄── scheduler job "apiqueue"
                                       │
 outbound: ApiHttp (allowlist) ──► slaed.net updates · api.telegram.org · AI provider
```

## Contract

- **Request:** `POST` for actions that change something, `GET` for reads; JSON body; `Authorization: Bearer <key>`;
  optional `Idempotency-Key` on writes; `X-Request-Id` echoed when it matches `^[A-Za-z0-9-]{8,32}$`, otherwise
  generated.
- **Response envelope (same as LAAS):**

  ```json
  {"ok": true, "data": {}, "meta": {"id": "<request id>", "ver": "v1", "ms": 12, "now": 1791379534232}}
  {"ok": false, "error": {"code": "<code>", "message": "<text>", "details": {}}, "meta": {}}
  ```

  `ms` is the processing time, `now` the server time in unix milliseconds. The one exception is the MCP endpoint,
  which answers in JSON-RPC 2.0 (service `ai`).
- **Server clock:** `meta.now` in every reply; a client that plans by time (a Vine agent: lease `until`, `nrun`)
  keeps an offset to it and never trusts its own clock.
- **Token format:** `SLAED_<prefix>.<secret>`; the prefix is stored in plain text for display and lookup, the whole
  token only as SHA-256; shown once; rotate, revoke, optional expiry. Auth errors are uniform; `Authorization` is never
  logged.
- **Status codes:** 200, 201, 204, 304 (ETag on reads), 400 `bad_request`, 401 `no_auth`, 403 `no_scope`, 404
  `no_route`, 405 `no_method`, 409 `conflict`, 413 `too_large`, 422 `invalid`, 429 `too_many` (+ `Retry-After`), 503
  `maintenance` / `disabled`, 500 `internal` (uncaught).
- **Caching:** an authenticated reply is `Cache-Control: private, no-store` (an ETag is allowed); only a public action
  requested without `Authorization` may be `public`.
- **Idempotency:** `Idempotency-Key` is at most 64 characters of `[A-Za-z0-9_-]`; `api_idem` stores it with `rhash`
  (SHA-256 of method, route and body). The row is written as `pending` before the handler runs; a concurrent duplicate
  gets 409 with `Retry-After: 1`, the same key with another `rhash` gets 422, a finished one replays the stored reply.
- **Paging:** `limit` (≤ 100 unless the action declares a larger cap) and an opaque `cursor`; never offsets on large
  tables.
- **Errors:** no stack, path or SQL in a response; full detail goes to the API log with the request id.
- **CORS:** off by default. The global list `$conf['api']['cors']` answers preflights; a client's own `origins` decide
  the real request. A client's origin missing from the global list is added there when the admin saves the client.
- **Client IP:** only from the core's `getIp()`; the API never reads forwarding headers itself.

## Building blocks

Files under `core/classes/api/`, one class each; names follow `.rules/global.md`.

| File | Class | Job |
| --- | --- | --- |
| `router.php` | `ApiRouter` | parse the route, find the action, run the guards, load and call the handler, build the reply; `getActionResult()`; `setApiReply()` |
| `client.php` | `ApiClient` | load a client by key hash, scopes, owner user, limits, origins; `isScopeAllowed()`; `addClient()`, `updateClient()` (name, scopes, limits, origins, IPs, on/off), `setClientKey()` (rotate), `setClientRevoke()`, `deleteClient()`, `getClientLog()` — the only way a module creates, rotates, revokes, deletes or reads the log of a client |
| `limit.php` | `ApiLimit` | token buckets per IP and per client in the cache; `Retry-After` |
| `input.php` | `ApiInput` | the API's input filter: reads the JSON body once, size cap, typed reads by declared params (`num`, `word`, `text`, `json`, `asin`, `list`, `rows`, `jpeg64`, …) |
| `http.php` | `ApiHttp` | outbound calls: allowlist, SSRF guard, timeouts, retries with backoff, response size cap, redacted log |
| `queue.php` | `ApiQueue` | `addJob()`, drained by the scheduler job `apiqueue` with locks and retries |
| `event.php` | `ApiEvent` | `addApiEvent()`: delivery of an event to the declared subscribers and outbound webhooks through the queue |
| `audit.php` | `ApiAudit` | `addApiAudit()`: one audit row per admin action |
| `error.php` | `ApiError` | the one exception a guard or handler throws: code, status, text |

Core services (`system`, `ai`) live in `core/classes/api/services/<service>.php`, a declaration and its
handlers in one file.

Input types worth naming: `rows` — a list of rows with a nested column schema and a row cap, e.g.
`'items' => ['rows', 500, ['asin' => 'asin', 'title' => ['text', 300]]]`; `jpeg64` — a `data:image/jpeg;base64,`
string, strict base64, decoded bytes start with `FFD8FF`, at most 16 KB encoded.

## Extensibility

A service is added without touching the connector or any central list:

- **Discovery:** a service is any enabled module with a file `modules/<module>/api.php`, plus the core services in
  `core/classes/api/services/`. The router builds the service list from the enabled modules (cached, rebuilt when
  `config/modules.php` changes). Service name = module name; `/api/v1/vine/beat` → module `vine`, action `beat`.
- **Declaration next to the code:** `modules/<module>/api.php` only returns the action table — method, auth, scope,
  params, size cap, cache, handler, flags; it defines no function. The handlers live in `modules/<module>/common.php`,
  which the router includes only after every guard passed. The admin shows the declarations as the live API
  reference, and `system/schema` returns them.
- **Scopes declared by the service:** a service lists its scopes with a title (`'scopes' => ['vine:agent' => '…']`);
  the admin offers them when it creates a client. No central route → scope map.
- **Enable and disable:** per service and per action in the admin (`config/api.php` stores only the switches, never
  the routes); a disabled service answers 503 and its handlers are not loaded.
- **Events between services:** a service raises an event (`addApiEvent('vine.hit', $data)`); other services subscribe
  in their declaration (`'events' => ['vine.hit' => 'setTelegramHit']`); delivery goes through the queue. Vine does
  not know Telegram exists, Telegram does not know Vine's tables.
- **Outbound webhooks** are one more subscriber: an admin-defined URL from the allowlist receives chosen events,
  signed with HMAC-SHA256 over `timestamp.body`, sent as `X-Slaed-Signature: t=<unix>,v1=<hex>`; a receiver rejects a
  signature older than 5 minutes.
- **AI tools for free:** an action flagged `'tool' => ['title' => …, 'desc' => …]` is offered by the `ai` service as an
  MCP tool named `<service>_<action>` (e.g. `vine_stats`), with the same params and scope; no second definition.
- **Versions:** an action may exist in `v1` and `v2` side by side (`'ver' => [1, 2]`), as LAAS keeps pages v1/v2.

Declaration format (`modules/vine/api.php`, an excerpt — the full param lists are in the Vine plan):

```php
return [
    'title' => 'Vine Monitor',
    'scopes' => ['vine:agent' => 'Vine Monitor agent', 'vine:read' => 'Vine statistics, read only'],
    'events' => [],
    'actions' => [
        'beat' => ['method' => 'POST', 'scope' => 'vine:agent', 'params' => ['state' => 'word', 'epoch' => 'num'], 'call' => 'updateVineBeat'],
        'push' => ['method' => 'POST', 'scope' => 'vine:agent', 'size' => 1048576, 'call' => 'addVinePush',
            'params' => ['epoch' => 'num', 'seq' => 'num', 'done' => 'num', 'items' => ['rows', 500, []], 'state' => 'json']],
        'stats' => ['method' => 'GET', 'scope' => 'vine:read', 'params' => ['days' => 'num'], 'call' => 'getVineStats',
            'tool' => ['title' => 'Vine statistics', 'desc' => 'New Vine items and ETV over a period']],
    ],
];
```

`auth` defaults to `key`. An agent splits a large push so each call stays under the action's `size`. A handler is
`function (array $in, ApiClient $cli): array`; it returns data or throws `ApiError`.

## Data model

Every column takes its domain from `docs/NODE.md`, "Schema conventions", which `tests/SchemaConventionTest.php`
holds for every table of `table.sql`: ids and references `INT UNSIGNED` (the log `BIGINT UNSIGNED`), counters and
numbers `INT UNSIGNED NOT NULL DEFAULT 0`, flags `BOOLEAN`, `ip` the ascii `VARCHAR(45)`, a moment a person reads
`DATETIME`, a timer no person reads `BIGINT UNSIGNED` unix seconds, NULL only for a moment that has not happened.
Codes, hashes and names of routes are `CHARACTER SET ascii COLLATE ascii_bin`. The table names what the domains do
not decide.

| Table | Columns | Keys |
| --- | --- | --- |
| `api_client` | `id`, `name` VARCHAR(64), `kind` VARCHAR(10) ascii (agent, app, bot, ai, site), `prefix` CHAR(8) ascii, `token` CHAR(64) ascii (sha256), `scopes` TEXT, `etime` `DATETIME DEFAULT NULL` (expiry, NULL = never), `rtime` `DATETIME DEFAULT NULL` (revoked, NULL = not revoked), `uid` (acting site user, 0 = guest rights), `aid` (acting administrator, 0 = none; never replaced by `uid`, as in `NodeContext`), `policy` TEXT (JSON: risk class → `allow`, `undo`, `confirm` or `deny`; empty = the site default), `origins` TEXT, `ips` TEXT, `rate` (per minute), `active` BOOLEAN, `seen` `DATETIME DEFAULT NULL` (NULL = never used), `ip`, `ctime` `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | PK `id`, UNIQUE `token` |
| `api_log` | `id` BIGINT, `time` `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, `client` (0 = no key), `ip`, `route` VARCHAR(64) ascii, `status`, `ms`, `rid` VARCHAR(32) ascii, `error` VARCHAR(64) ascii | KEY `time`, KEY `client,time` |
| `api_idem` | `client`, `ikey` VARCHAR(64) ascii, `rhash` CHAR(64) ascii, `done` BOOLEAN (0 = pending), `time` timer, `status`, `body` MEDIUMTEXT | PK `client,ikey` |
| `api_queue` | `id`, `kind` VARCHAR(32) ascii (job or event name), `data` MEDIUMTEXT, `ntime` timer, `tries`, `status` VARCHAR(10) ascii, `error` VARCHAR(255), `ctime` `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | KEY `status,ntime` |
| `api_audit` | `id`, `time` `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, `aname` VARCHAR(25) (admin), `ip`, `act` VARCHAR(32) ascii, `target` VARCHAR(64), `info` TEXT (JSON old/new) | KEY `time`, KEY `target` |
| `api_confirm` | `id`, `client`, `route` VARCHAR(64) ascii, `rhash` CHAR(64) ascii (sha256 of the exact input), `code` CHAR(64) ascii (sha256 of the one-time confirmation code), `aname` VARCHAR(25) (admin who confirmed, empty while pending), `etime` timer (expiry), `done` BOOLEAN, `ctime` `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | KEY `client,done` |

Retention, enforced by the scheduler job `apiclean`: `api_log` 30 days, `api_idem` 24 h, finished `api_queue` 7
days, `api_audit` 365 days, `api_confirm` 24 h after expiry.

`config/api.php` (`$conf['api']`): `active`, `off` (disabled services and actions — switches only, no routes),
`outbound` (host allowlist), `limits` (default rate, burst, body size), `cors` (off by default, global origin
allowlist for preflights), `log` (all / errors only), `maint` (answer 503 to everything but `system`), `hooks`
(outbound webhook URLs and the events each one receives), `telegram` (bot token, webhook secret, id of the bot's
client), `ai` (provider, model, provider key), `risk` (the site default policy per risk class, see "Administrator
access"), `master` (1 only on slaed.net), `dev` (1 only on a
development host; `tools/apikey.php` refuses to run without it).

The settings of updates, `upcheck` and `channel`, live in `config/update.php` beside the marks of the data updates
(`docs/11-UPDATE-2026.md`); `config/api.php` holds none of them.

## Services

Each service is its own plan or section; the connector only hosts it.

- **`system`:** `ping` (public, no data), `schema` (public; the live API reference built from the declarations:
  services, actions, methods, params, scopes — a guest sees only the public actions, a key also those its scopes
  allow), `whoami` (client name and scopes; scope `system:self`, granted to every client), `version` (site version for
  partner sites, scope `system:info`).
- **`vine`:** Vine Monitor — `hello`, `beat`, `cfg`, `pull`, `push`, `hits`, `ack` (scope `vine:agent`); `items`,
  `stats`, `finds` (scope `vine:read`, `stats` flagged as a tool); `hold`, `resume` (scope `vine:admin`, added with
  the `telegram` step). Plan: `docs/2027-VINE-MONITOR.md`. The first consumer: it proves the connector.
- **`telegram`:**
  - inbound `hook`: `'auth' => 'secret'` — the header `X-Telegram-Bot-Api-Secret-Token`, set with `setWebhook`,
    compared with `hash_equals()` before anything is read; `update_id` is deduplicated; the update is stored and
    answered at once, the work goes to the queue;
  - outbound through `ApiHttp` to `api.telegram.org` only; messages are sent without `parse_mode` (plain text), so a
    product title can never become markup;
  - linking a Telegram chat to a site user by a one-time code from the profile: at least 8 characters, single use,
    valid 10 minutes, 5 tries per chat;
  - first uses: Vine finds on the phone, admin alerts (Vine captcha, nobody working, scheduler failure, security
    warning), commands `/status`, `/finds`, `/pause`, `/run` for linked admins;
  - the bot acts as its own `api_client` (kind `bot`, created in step 6, scopes set by the admin, its id in
    `$conf['api']['telegram']`); a command calls the target action through `ApiRouter::getActionResult()` with it;
    `/pause` and `/run` call `vine/hold` and `vine/resume`, which write `addApiAudit()` with the linked admin's name;
    `/run` is the same as «Запуск всех» in the Vine admin.
- **`content` (headless):**
  - reads first: types, categories, materials by type/category/search with cursor paging, one material by id or
    slug, site info;
  - the client's `uid` decides read rights through the existing category rights — no second rights system;
  - writes later, scope `content:write`, only through the shared Node writer.
- **`ai`:** see the next section.
- **`master`:** the update server, a module installed on slaed.net only — `docs/11-UPDATE-2026.md`.

## Service `ai`

- Two directions, one tool table.
- Inbound, MCP (Model Context Protocol, the open standard by which AI applications — Claude Desktop, Claude Code,
  VS Code, Cursor and others — use external tools): endpoint `/api/v1/ai/mcp`, transport Streamable HTTP, JSON-RPC 2.0
  over one POST per call; `setApiReply()` has two modes, `envelope` and `jsonrpc`, and this action uses the second.
- Protocol revision `2026-07-28`, which is stateless: no session, no `initialize` handshake, protocol version and
  client capabilities in `_meta` of every request, `server/discover` mandatory for the server — so one PHP request
  answers one call, a good fit for the API branch of `public/index.php`.
- Methods: `server/discover` (`serverInfo` in the result `_meta`), `tools/list` (only the flagged actions whose scope
  the key holds, deterministic order, `ttlMs`, `cacheScope: "private"` because the list depends on the key),
  `tools/call` (through `ApiRouter::getActionResult()`, so the tool's own scope, kill switch, limit and input filter
  apply; `ai:tools` alone is never enough); every result carries `resultType: "complete"`, or `"input_required"`
  when the action's policy is `confirm` (Multi Round-Trip Requests of `2026-07-28`, see "Administrator access");
  later `resources/*` for materials.
- Transport checks: the headers `MCP-Protocol-Version`, `Mcp-Method` and `Mcp-Name` must match the body (400 with
  JSON-RPC `-32020` otherwise); an unsupported version gets 400 with `-32022` and `data.supported`; an unknown method
  gets 404 with `-32601`; `GET` and `DELETE` get 405; a batch (JSON array body) gets 400; a present `Origin` that is
  not allowed gets 403. `ApiInput` exposes these declared headers to the handler.
- Older clients: the endpoint also answers `initialize` of revision `2025-11-25` without creating a session (no
  `Mcp-Session-Id`), returns 202 for `notifications/initialized` and serves `tools/list` and `tools/call` under the
  older rules, so a client that does not speak `2026-07-28` yet still connects.
- Auth in v1: the connector key as `Authorization: Bearer` (Claude Code and VS Code accept a header in their MCP
  config); authorization is optional in the spec. OAuth 2.1 with Client ID Metadata Documents and RFC 9728 Protected
  Resource Metadata only when a client that requires it is needed.
- With a user key (`aid` 0) tools are read-only or draft-only, as in LAAS: an AI agent proposes, a person applies.
  With an administrator key they follow "Administrator access". Text that comes from outside (a product title, a
  comment, a complaint) is returned as data, never as an instruction, and is marked as untrusted in the result.
- Outbound: an AI bot on the site or in Telegram answers from site content through a provider API called by `ApiHttp`,
  run from the queue.

### Administrator access

An agent works in the panel with the rights of one administrator and never more; whether it may, and how far, is the
decision of the site owner, the main administrator.

- **Who opens it:** only the main administrator (`isAdmin(true)`) gives a client an `aid`; a client with the `aid` of
  the main administrator can be made only by the main administrator. Off by default; a site without such a client has
  no administrator surface over MCP.
- **Rights:** the intersection of the administrator's rights (`_admins.modules`, `super`), the key's scopes, the
  action's own scope, kill switch and limit, and the policy of its risk class. Admin modules declare their actions
  with scopes `admin:<module>`, explicit, no wildcard.
- **Access for a time:** the Клиенты tab offers "Пустить агента на 1 час / 1 день / 7 дней", which sets `etime`; an
  administrator client without `etime` is refused at creation. `ips` and `rate` as for any client.
- **Risk class, declared next to each action** (`'risk' => …` in `modules/<module>/api.php`; the router refuses an
  administrator action without it):

  | Class | Examples | Default policy |
  | --- | --- | --- |
  | `read` | materials, journals, statistics, `selfcheck`, settings read | `allow` |
  | `change` | edit a material, moderate a comment, most settings | `undo`: applied at once, a revision or the old value kept, undo offered for 10 s in the panel and through a tool |
  | `danger` | delete, ban, restore a backup, apply an update, security settings, mass action over the batch cap | `confirm` |

  The owner sets the policy per class in `$conf['api']['risk']` and may tighten it per client in `api_client.policy`;
  `deny` is always available, a client policy never loosens the site default.
- **Confirmation (`confirm`):** the action is not run; `api_confirm` stores the route, the hash of the exact input and
  the hash of a one-time code, valid 10 minutes, and the MCP result is `input_required`. A person confirms in the panel
  (with the second factor when TOTP exists, `docs/2027-CORE.md`, item 2) or by a button of the linked admin in
  Telegram; the agent's retry carries the code in `inputResponses`, and the action runs only if the input hash matches.
  An agent can never confirm its own request.
- **Never, whatever the policy** — these actions carry no `tool` flag and the router refuses them for any `aid`
  client: grant or change administrator rights; create, rotate, widen or revoke an API client, its own included;
  change `api_client.policy` or `$conf['api']['risk']`; switch off the audit, `api_log` or `admin.log`; write
  executable or server files (PHP, `.htaccess`, theme code that runs on the server); read or change secrets (keys,
  passwords, provider and bot tokens, `config/db.php`).
- **Every write:** accepts `dry_run` and then answers the diff without applying; a mass action is capped per call and
  takes a snapshot first; every call is written to `api_audit` with the administrator's name and the client marked as
  an agent; a `danger` action notifies the linked admins in Telegram or by mail.

The binding rules follow in the same step: `.rules/api.md` today allows a tool only to read or create a draft and
takes rights from `scopes` and `uid` alone; the step that builds this section changes those two lines to the model
above.

## Service `update`

Moved into `docs/11-UPDATE-2026.md` and built before 8.0 (owner, 2026-10-15): the master is the module `master`
on slaed.net, the client the core page "Обновления", the download a reply in the mode `file`. The signature,
the keys, the channels, the manifest, the unpacking and the installation are designed there.

## Security

- Keys: 32 random bytes, shown once, stored as SHA-256, compared with `hash_equals`; rotating issues a new key and
  revokes the old one.
- HTTPS only outside the dev host; `Strict-Transport-Security` from the entry.
- Scopes are explicit strings declared by their services, e.g. `vine:agent`, `vine:read`, `vine:admin`,
  `content:read`, `content:write`, `ai:tools`, `system:self`, `system:info`, `admin:<module>`; no wildcard.
- An administrator client (`aid` set) always has an expiry and is checked against "Administrator access" on every
  call: rights of that administrator, risk policy, the "never" list; a `confirm` code is single use and bound to the
  hash of the input it was issued for.
- Limits per IP (before auth) and per client; failed auth counted per IP and fed to the existing security log and
  blocker — except a well-formed `SLAED_` key of a revoked or expired client, which is logged but not counted, so a
  forgotten agent at the owner's home cannot get the owner's own IP blocked.
- Every input through `ApiInput` with declared types and caps; prepared statements with named placeholders only.
- No CSRF token on the API: actions are session-less and authenticated per request by key or secret;
  `admin/modules/api.php` and every module admin keep the panel's check `checkAdminPost()`, which wraps
  `checkSiteToken()` (recorded in `.rules/global.md`).
- Outbound (`ApiHttp`): HTTPS only, port 443 unless the allowlist entry names a port; the host is resolved once,
  loopback, private (RFC 1918, `fc00::/7`), link-local (`169.254.0.0/16`, cloud metadata) and unspecified addresses are
  refused, and the checked IP is the one connected to (stream context `peer_name` = host), so DNS rebinding does not
  work; redirects are not followed (a 3xx is an error); the response size is capped per call.
- `ApiHttp` logs scheme, host, the path with secrets redacted (`/bot***/sendMessage`), status and duration — never a
  query string, request headers or a body.
- The webhook secret, bot token and AI provider key live in config written by the admin, never in git
  (`docs/ARCHITECTURE.md`, "Private Data Boundary", applies).
- Kill switch per service and the maintenance flag; a disabled service answers 503 without loading its handlers.
- One key per caller: every agent, app, bot and site gets its own client; a key is never shared, so revoking one
  stops one caller.
- Audit log: every admin action on clients, services and agents (create, rotate, revoke, enable, disable, start,
  pause, stop, takeover, delete, settings) is written with admin, IP, time, target and old/new value; read-only in the
  admin, kept 365 days. Since 8.0 SLAED keeps a general journal of administration in `storage/logs/admin.log`:
  `checkAdminPost()` opens the record and `addAdminLog()` writes it once at the end of the request; it records every
  POST of this admin too (who, scope, operation, object, result; `docs/ARCHITECTURE.md`, "Journal Boundary"); it holds
  no old and new value and is bounded by size, not by days. This plan therefore keeps the table `api_audit` and the
  function `addApiAudit()` for what the file cannot give, used by the Vine admin as well, and the two are linked, not
  merged (owner, 2026-10-12).

## Admin

New admin module `admin/modules/api.php` (tabs through `getTplAdminTabs()`, every POST through `checkAdminPost()`):

- **Состояние:** one table — requests and errors per hour, 401/429 counts, last run of each scheduler job, services
  switched off, maintenance flag; from step 5 also queue depth with the oldest waiting job and outbound failures; a
  red line for anything outside its normal range.
- **Клиенты:** create (name, kind, scopes, user, limits, origins, IPs), show the key once, rotate, revoke, disable;
  for the main administrator also the administrator, the access time ("Пустить агента на …") and the client's risk
  policy.
- **Подтверждения** (step 10): pending `confirm` requests with route, input and expiry — confirm or refuse; the risk
  policy of the site per class.
- **Сервисы:** on/off per service and action, limits, maintenance; from step 5 the outbound allowlist and the
  outbound webhooks.
- **Журнал:** requests by time/client/route/status, error detail by request id.
- **Аудит:** the audit log, filter by admin, action, target and period.
- **Очередь** (step 5): jobs, retries, failures, retry now.
- **Telegram** (step 6): bot token, webhook set/remove/check, linked chats.

## Tests

- **Unit (`tests/Unit/`):** route parsing, guard order (a preflight without a key gets 204, a request from a blocked IP
  never reaches the database), auth (missing, wrong, disabled, rotated, revoked key; secret header), scope denial,
  limits and `Retry-After`, input types and caps (`rows`, `jpeg64`), envelope and codes, idempotency (replay,
  different body, concurrent), ETag/304, cache headers, maintenance and kill switch, `getActionResult()` applies the
  target's scope.
- **Outbound (step 5):** allowlist, private and metadata addresses refused, a redirect refused, oversized response cut,
  redacted log line; queue retries; webhook signature with timestamp.
- **Update (step 8):** see the step.
- **Structure and security:** `ModuleStructureTest`, `LanguageValidationTest`, `SecurityValidationTest`,
  `PhpFileFormatTest`; the `/schema` command (database vs `table.sql`) reports no drift.
- **Tools:** `tools/api.http` runs green against the dev host; `tools/apikey.php` refuses to run without
  `$conf['api']['dev']`.
- **Light boot:** the API branch of `index.php` loads no theme and no template (assert on included files) and answers
  in under 30 ms for `system/ping` on the dev host; `index.php` and `admin.php` routes of at least three modules
  unchanged.

## Developer tools

- **`tools/api.http`:** requests for the VS Code REST Client, one per action plus an error case each; the key comes
  from `{{$dotenv SLAED_API_KEY}}`, and `tools/.env` is added to `.gitignore` in the step that creates the file.
- **`tools/apikey.php`:** CLI on the dev host — create a client with name and scopes and print the key once, list,
  revoke. Refuses to run unless `$conf['api']['dev']` is 1.
- **`tools/release.php`, `tools/sign.php`:** build from a tag and sign offline (`docs/11-UPDATE-2026.md`).

## Later

Recorded so it is not forgotten; not part of the steps.

- **Move this plan to a stable name** (e.g. `docs/API.md`) once step 11 is done, and point `.rules/api.md` at it.

## Open questions

The plan of 2026-10-07 is decided (section "Decisions"). A new question goes here before a step starts.

- **Dependencies of administrator access** (2026-10-14): the `undo` policy needs node revisions (`docs/2027-CORE.md`,
  item 3) and old values of settings; the `confirm` policy is stronger with TOTP (item 2). Build those two items
  before step 10, or let step 10 treat `undo` as `confirm` and confirm without a second factor until they exist?
- **OAuth for administrator keys:** chat clients such as Claude.ai and ChatGPT connect remote servers through OAuth
  rather than a Bearer header; an administrator who is not a developer needs OAuth 2.1 (RFC 9728, CIMD, RFC 8707,
  RFC 9207) to connect at all. Move it from "only when a client requires it" into step 10?

## Steps

Order decided on 2026-10-07: Vine right after clients and guards.

- [x] 1. Light boot — moved to `docs/11-UPDATE-2026.md`, batch 1 (the branch `/api/` of `public/index.php`).
- [x] 2. Core of the connector — moved to `docs/11-UPDATE-2026.md`, batch 2, with `checkApiFlood` and the reply
  mode `file`.
- [ ] 3. Clients and guards: `config/api.php` (`dev`), `api_client`, keys, scopes, `checkApiAuth`,
  `checkApiScope`, `checkApiOrigin`, `checkApiLimit`, `api_log`, `api_idem`, `api_audit`, `addApiAudit()`, the
  scheduler job `apiclean`, `system/whoami`, scope filtering of `system/schema`, `getActionResult()`;
  `tools/apikey.php`; admin tabs Состояние, Клиенты, Сервисы, Журнал, Аудит. Done when the auth/scope/limit tests
  pass, the admin creates and rotates a key and both actions are in the audit log.
- [ ] 4. Service `vine` on the connector — the steps of `docs/2027-VINE-MONITOR.md`.
- [ ] 5. Outbound, queue, events: `ApiHttp` with allowlist and SSRF guard, `getSchedulerFetch()` moved onto it,
  `ApiQueue` with the scheduler job `apiqueue`, `addApiEvent()` with subscribers and signed outbound webhooks; Vine
  starts raising `vine.hit`, `vine.captcha`, `vine.idle`, `vine.resume` beside its `vine_event` rows; admin tab
  Очередь, outbound allowlist and webhooks on Сервисы. Done when the outbound, queue and event tests pass and the
  scheduler runs `apiqueue`.
- [ ] 6. Service `telegram`: webhook with the secret header, linking, alerts (Vine captcha and nobody working first),
  Vine finds to the phone, admin commands with `vine/hold` and `vine/resume`; admin tab Telegram.
- [ ] 7. Service `content`: headless reads with rights, ETag, cursor paging; `docs/NODE.md` updated.
- [x] 8. Service `update` — moved to `docs/11-UPDATE-2026.md` and built before 8.0.
- [ ] 9. Service `ai`: tool table, MCP endpoint `/api/v1/ai/mcp` (`server/discover`, `tools/list`, `tools/call`,
  transport checks, older-client support, Bearer key), outbound provider calls through the queue. Done when Claude
  Code connects with the key, lists the flagged tools and calls `vine_stats`, a client of the older revision connects
  too, and a tool whose scope the key lacks is neither listed nor callable.
- [ ] 10. Administrator access: `aid` and `policy` of `api_client`, `api_confirm`, `$conf['api']['risk']`, `risk`
  in the action declaration, the "never" list in the router, `input_required` and its retry, confirmation in the panel
  and in Telegram, `dry_run`, batch cap and snapshot, admin tab Подтверждения; `.rules/api.md` changed to the model of
  "Administrator access". Done when an administrator key without `etime` is refused, a `danger` action runs only after
  a person confirms it and only with the same input, an agent cannot confirm its own request, every "never" action is
  refused for the main administrator's key too, a client policy cannot loosen the site default, and a user key still
  cannot publish.
- [ ] 11. Review: security pass over every service, load test of `vine/beat` and `content` reads, docs.
