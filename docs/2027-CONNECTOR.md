# Connector 2027

Work plan for SLAED's single machine-to-machine entry point: the connector. Every client that is not a browser page
of the site — a Vine Monitor agent, a headless front end, a Telegram bot, an AI agent, another SLAED site asking for
updates — talks to the site through it, and the site talks to the outside world (update server, Telegram Bot API, AI
providers) through the same layer.

Status: planned, nothing implemented. The owner's decisions of 2026-10-07 are in "Decisions"; "Recommended
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

What the connector takes from LAAS CMS (`C:\OSPanel\home\laas.loc`, `docs/API.md`, `docs/ROUTES.md`,
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

- **Own entry `public/api.php`** next to `index.php` and `admin.php` (how it boots: "Recommended decisions").
- **slaed.net is the master:** the update server of every SLAED site (service `update`).
- **Anonymous access only to safe actions:** an action is open without a key only if it is `GET`/`HEAD`, declared
  `'auth' => 'public'`, read-only and returns only data a guest may see on the site anyway; it is rate-limited per IP
  and cacheable. Everything else needs a key or, for an inbound webhook, its declared secret.
- **No Composer, the system ships clean:** no runtime dependency, no vendored framework. A framework kernel is not
  adopted; the connector gets its own minimal pipeline (section "Kernel"), which is the part of a framework that pays
  off here.
- **MCP in the `ai` step** (step 9): an inbound MCP endpoint for AI agents, after `content` reads exist.
- **Update channels `stable` + `beta`** (service `update`).
- **Two signing keys:** a primary that signs releases and a recovery key kept offline in a safe, used only for key
  statements — replacing or revoking keys (service `update`).
- **Update check on by default, with a notice:** it sends the site's channel and key sequence `kseq` to slaed.net, and
  with it the server's IP; the admin shows this and has a switch (service `update`).
- **Step order:** Vine right after clients and guards; outbound, queue and events right before Telegram (section
  "Steps"). Vine needs none of the queue, and the queue is built for its first real consumer.
- **Agent rules for the code:** `.rules/api.md` binds every connector and service change; `CLAUDE.md` lists it.
- **Operations:** one key per caller (no shared keys), an audit log of admin actions, a status page, the server clock
  in every reply, a live API reference `system/schema`.
- **Developer tools:** `tools/api.http` for the VS Code REST Client, `tools/apikey.php` for dev keys, release builds
  from a git tag by `tools/release.php`.
- **Later, not in the steps:** staged rollout of `stable` (section "Later").

## Recommended decisions

- **How `api.php` works** (as in LAAS and Joomla): JSON only, no theme, no template, no session, no HTML error page.
  Pretty route `/api/v1/<service>/<action>` by one rewrite rule in `public/.htaccess` and `nginx.conf.example`; the
  plain form `api.php?r=<service>/<action>` always works. Reason for a separate entry rather than `index.php?go=api`:
  the SLAED boot loads the theme and the template object on every request, and a Vine agent beats every 45 s — the
  light boot pays off, and a fault in the API cannot render a site page.
- **A light boot of the core:** `api.php` defines `API_FILE`; `core/system.php` accepts it beside `MODULE_FILE` and
  `ADMIN_FILE` and skips the theme, the template object and everything visual. Config, DB, security, logger, cache,
  mail and the classes that services need stay loaded. `.rules/architecture.md` gets `public/api.php` and `API_FILE`
  in its entry points in the same step.
- **One client registry for every caller:** a client is a key with scopes. A Vine agent, a headless app, an AI agent
  and a partner site are rows of the same table; a service never invents its own key scheme.
- **Inbound and outbound in one layer:** `ApiHttp` is the only place API code calls external hosts, with a host
  allowlist in config, SSRF protection, timeouts and logging. The existing `getSchedulerFetch()` moves onto it.
- **Slow work never runs inside a request:** a webhook or an AI request that calls an external host or may exceed 1 s
  enqueues a job; the existing scheduler drains the queue.
- **No new runtime dependency** (`docs/PRINCIPLES.md`): plain PHP 8.4 with its bundled extensions (`json`, `sodium`,
  `hash`); outbound HTTP through PHP streams, as `getSchedulerFetch()` does today, so `curl` is not needed. A missing
  extension disables the feature that needs it with a clear message in the admin (no `sodium` → no update apply).
- **Versioned contract:** `v1` in the path; a breaking change is `v2` beside `v1`, never in place.
- **Node gains a headless read API.** `docs/NODE.md` says today that Node offers no public headless API; this plan
  changes that sentence on purpose (content service, read first, write later through the shared writer).
- **Scheduler jobs follow the existing pattern:** connector and Vine jobs (`apiclean`, `apiqueue`, `updatecheck`,
  `vinewatch`, `vineclean`) are entries in `config/scheduler.php` with `'type' => 'system'` and a branch in the
  `match` of `addSchedulerSystemJob()`, exactly as `newsletter` and `nodepublish` are today. A module job type is not
  introduced by this plan.

## Kernel

Why no framework kernel (PSR-7/15, a container, a middleware stack):

- **It would be a second architecture** beside the procedural SLAED core: two ways to read input, two error paths, two
  boot sequences — the cost lands on every module, not only on the API.
- **The useful part is small:** an ordered list of guards around a handler. That is about 300 lines of SLAED-style
  PHP and needs no interface package.
- **Shipping clean:** a framework means either Composer or a vendored copy to keep updated; both contradict the
  delivery model.

What the connector has instead: `ApiRouter::getResponse()` runs a fixed guard list and returns the reply array;
`public/api.php` passes it to `setApiReply()`, the only function that writes headers and body. The guards, in order:

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
 clients                         public/api.php  (API_FILE, light core)
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
                 services: vine · content · telegram · ai · update · system
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

Core services (`system`, `update`, `ai`) live in `core/classes/api/services/<service>.php`, a declaration and its
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

| Table | Columns | Keys |
| --- | --- | --- |
| `api_client` | `id`, `name`, `kind` (agent, app, bot, ai, site), `prefix` CHAR(8), `token` CHAR(64) sha256, `scopes` TEXT, `etime` (expiry, NULL = none), `rtime` (revoked), `uid` (acting site user, 0 = guest rights), `origins` TEXT, `ips` TEXT, `rate` SMALLINT (per minute), `active` BOOL, `seen`, `ip`, `ctime` | PK `id`, UNIQUE `token` |
| `api_log` | `id` BIGINT, `time`, `client`, `ip`, `route`, `status` SMALLINT, `ms` INT, `rid` VARCHAR(32), `error` VARCHAR(64) | KEY `time`, KEY `client,time` |
| `api_idem` | `client`, `ikey` VARCHAR(64), `rhash` CHAR(64), `state` (pending, done), `time`, `status`, `body` MEDIUMTEXT | PK `client,ikey` |
| `api_queue` | `id`, `kind` VARCHAR(32) (job or event name), `data` MEDIUMTEXT, `ntime`, `tries`, `status`, `error`, `ctime` | KEY `status,ntime` |
| `api_audit` | `id`, `time`, `aname` (admin), `ip`, `act` VARCHAR(32), `target` VARCHAR(64), `info` TEXT (JSON old/new) | KEY `time`, KEY `target` |

Retention, enforced by the scheduler job `apiclean`: `api_log` 30 days, `api_idem` 24 h, finished `api_queue` 7
days, `api_audit` 365 days.

`config/api.php` (`$conf['api']`): `active`, `off` (disabled services and actions — switches only, no routes),
`outbound` (host allowlist), `limits` (default rate, burst, body size), `cors` (off by default, global origin
allowlist for preflights), `log` (all / errors only), `maint` (answer 503 to everything but `system`), `hooks`
(outbound webhook URLs and the events each one receives), `telegram` (bot token, webhook secret, id of the bot's
client), `ai` (provider, model, provider key), `master` (1 only on slaed.net), `dev` (1 only on a
development host; `tools/apikey.php` refuses to run without it).

`config/update.php` (`$conf['update']`): `check` (daily check on/off, default 1), `channel` (`stable` or `beta`,
default `stable`).

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
- **`update`:** see the section after it.

## Service `ai`

- Two directions, one tool table.
- Inbound, MCP (Model Context Protocol, the open standard by which AI applications — Claude Desktop, Claude Code,
  VS Code, Cursor and others — use external tools): endpoint `/api/v1/ai/mcp`, transport Streamable HTTP, JSON-RPC 2.0
  over one POST per call; `setApiReply()` has two modes, `envelope` and `jsonrpc`, and this action uses the second.
- Protocol revision `2026-07-28`, which is stateless: no session, no `initialize` handshake, protocol version and
  client capabilities in `_meta` of every request, `server/discover` mandatory for the server — so one PHP request
  answers one call, a good fit for `api.php`.
- Methods: `server/discover` (`serverInfo` in the result `_meta`), `tools/list` (only the flagged actions whose scope
  the key holds, deterministic order, `ttlMs`, `cacheScope: "private"` because the list depends on the key),
  `tools/call` (through `ApiRouter::getActionResult()`, so the tool's own scope, kill switch, limit and input filter
  apply; `ai:tools` alone is never enough); every result carries `resultType: "complete"`; later `resources/*` for
  materials.
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
- Tools are read-only or draft-only, as in LAAS: an AI agent proposes, a person applies. Text that comes from outside
  (a product title) is returned as data, never as an instruction.
- Outbound: an AI bot on the site or in Telegram answers from site content through a provider API called by `ApiHttp`,
  run from the queue.

## Service `update`

slaed.net is the master; every other SLAED site is a client of it.

- **Master side**, only where `$conf['api']['master'] = 1`: one public action `update/check` (`GET`, param
  `channel`, optional `kseq`). It returns signed data only, never an unsigned verdict: the signed manifest of the
  latest release of the asked channel (for `beta` also of `stable`) and every key statement with a `kseq` above the
  one sent. The zip itself is a static file on slaed.net, named in the manifest `url`, so no binary passes through
  `setApiReply()`.
- **Manifest:** a JSON file with `type` (`release`), `version`, `channel`, `min` (lowest version that may update
  from it), `sha256`, `size`, `url`, `commit` (the git commit the zip was built from), `time`, `expires` (`time` + 45
  days) and `kid` (the key that signed it). Versions are `MAJOR.MINOR.PATCH`, digits only, compared with
  `version_compare()`.
- **Signature:** Ed25519 (`sodium_crypto_sign_detached()`), detached, over the exact raw bytes of the manifest as
  served, prefixed with the type tag `SLAED-UPDATE-MANIFEST-v1\n`. The client verifies first, then decodes the same
  bytes with `JSON_THROW_ON_ERROR`, never re-encodes. Signatures and keys are base64, strictly decoded, exactly 64 and
  32 bytes.
- **Channels:** a site follows `stable` by default, `beta` only when its admin switches it on
  (`$conf['update']['channel']`). A `stable` site accepts only `channel = stable`; a `beta` site accepts `beta` and
  `stable` and takes the newer. A release goes to `beta` first and is promoted to `stable` with the same zip and the
  same version string, only a new signed manifest. Switching back to `stable` never downgrades: the site waits until
  `stable` passes its version.
- **Freshness:** the client accepts a manifest only if its `version` is above the installed one, the installed one is
  at least `min`, and its `time` is not older than the newest `time` it has seen for that channel. An expired
  manifest is not blocked but shows a red warning on the admin update page («Сервер обновлений отдаёт устаревшие
  данные»). The owner re-signs the latest manifest at least once a month even without a release.
- **Keys:**
  - **primary** signs release manifests and nothing else; a release verifies only against the current primary;
  - **recovery** signs key statements `{type: keychange, kseq, role, newkey, revoked: [kid…], time}` with the type tag
    `SLAED-KEYCHANGE-v1\n`, `role` = `primary` or `recovery`; a site accepts a statement only if it verifies against
    the recovery key and its `kseq` is above the stored one; revoked keys are stored and never trusted again; planned
    and emergency rotations go the same way;
  - a statement with `role` = `recovery` (the recovery key itself is replaced) needs a second signature `sig2` by the
    current primary over the same bytes; recovery alone is refused;
  - the trusted keys, `kseq` and the revoked list live in `storage/update/keys.json` (gitignored), written only by
    the key-statement handler; no package may contain that path (apply refuses it); the core carries only the factory
    keys used when that file does not exist yet;
  - both private keys stay offline: the primary on the owner's machine, the recovery key in a safe (printed and on a
    separate USB stick); neither ever touches a server, the repository or CI; key fingerprints are also published
    outside slaed.net (repository README and docs).
- **Build and sign:** `tools/release.php <tag>` builds the zip from a git tag only (`git archive`, never the working
  tree), refuses a tag that contains a symlink and writes the unsigned manifest; `tools/sign.php` signs it on the
  owner's machine.
- **Client side, check:** the scheduler job `updatecheck` asks the master once a day; the request carries only the
  channel and `kseq`, but the master sees the server's IP, so the job is on by default with a switch
  (`$conf['update']['check']`); the admin update page says what is sent and where; `docs/` gets a short GDPR (DSGVO)
  note for site owners; with the switch off the owner checks with a button.
- **Client side, download:** on the owner's click only; `ApiHttp` streams the zip to `storage/update/tmp/`, aborts as
  soon as it exceeds the signed `size`, then checks `size` and SHA-256 with `hash_equals()`.
- **Client side, unpack:** into `storage/update/stage/<version>/`; an entry is refused if it is absolute, contains
  `..`, a backslash or a drive letter, is a symlink, or lies outside the shipped top-level directories (`admin`,
  `config`, `core`, `modules`, `public`, `storage/update`, `tools`, the root files of the release); a total
  uncompressed size above 10 × `size` is refused (zip bomb); files get mode 0644, directories 0755.
- **Client side, apply:** set the maintenance flag, back up files and database to `storage/backup/`, copy, run the
  SQL, clear the flag; on any error restore the backup automatically; a marker file makes an apply interrupted by a
  crash visible on the next request. Never an automatic apply.
- **Without `sodium`** the check still shows news, but download and apply are disabled with a message.

## Security

- Keys: 32 random bytes, shown once, stored as SHA-256, compared with `hash_equals`; rotating issues a new key and
  revokes the old one.
- HTTPS only outside the dev host; `Strict-Transport-Security` from the entry.
- Scopes are explicit strings declared by their services, e.g. `vine:agent`, `vine:read`, `vine:admin`,
  `content:read`, `content:write`, `ai:tools`, `system:self`, `system:info`; no wildcard.
- Limits per IP (before auth) and per client; failed auth counted per IP and fed to the existing security log and
  blocker — except a well-formed `SLAED_` key of a revoked or expired client, which is logged but not counted, so a
  forgotten agent at the owner's home cannot get the owner's own IP blocked.
- Every input through `ApiInput` with declared types and caps; prepared statements with named placeholders only.
- No CSRF token on the API: actions are session-less and authenticated per request by key or secret;
  `admin/modules/api.php` and every module admin keep `checkSiteToken()` (recorded in `.rules/global.md`).
- Outbound (`ApiHttp`): HTTPS only, port 443 unless the allowlist entry names a port; the host is resolved once,
  loopback, private (RFC 1918, `fc00::/7`), link-local (`169.254.0.0/16`, cloud metadata) and unspecified addresses are
  refused, and the checked IP is the one connected to (stream context `peer_name` = host), so DNS rebinding does not
  work; redirects are not followed (a 3xx is an error); the response size is capped per call.
- `ApiHttp` logs scheme, host, the path with secrets redacted (`/bot***/sendMessage`), status and duration — never a
  query string, request headers or a body.
- The webhook secret, bot token and AI provider key live in config written by the admin, never in git
  (`docs/0-PRIVATE-DATA-2026.md` applies).
- Kill switch per service and the maintenance flag; a disabled service answers 503 without loading its handlers.
- One key per caller: every agent, app, bot and site gets its own client; a key is never shared, so revoking one
  stops one caller.
- Audit log: every admin action on clients, services and agents (create, rotate, revoke, enable, disable, start,
  pause, stop, takeover, delete, settings) is written with admin, IP, time, target and old/new value; read-only in
  the admin, kept 365 days. SLAED has no general admin audit today (checked: only `addLoginReport()`), so this plan
  adds the table `api_audit` and the function `addApiAudit()`, used by the Vine admin as well.

## Admin

New admin module `admin/modules/api.php` (tabs through `getTplAdminTabs()`, CSRF on every POST):

- **Состояние:** one table — requests and errors per hour, 401/429 counts, last run of each scheduler job, services
  switched off, maintenance flag; from step 5 also queue depth with the oldest waiting job and outbound failures; a
  red line for anything outside its normal range.
- **Клиенты:** create (name, kind, scopes, user, limits, origins, IPs), show the key once, rotate, revoke, disable.
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
- **Light boot:** `api.php` loads no theme and no template (assert on included files) and answers in under 30 ms for
  `system/ping` on the dev host; `index.php` and `admin.php` routes of at least three modules unchanged.

## Developer tools

- **`.rules/api.md`:** the binding rules for connector and service code — declaration format, guard order, envelope,
  error codes, scopes, input types, what a handler may and may not do. `CLAUDE.md` lists it beside the other rules.
- **`tools/api.http`:** requests for the VS Code REST Client, one per action plus an error case each; the key comes
  from `{{$dotenv SLAED_API_KEY}}`, and `tools/.env` is added to `.gitignore` in the step that creates the file.
- **`tools/apikey.php`:** CLI on the dev host — create a client with name and scopes and print the key once, list,
  revoke. Refuses to run unless `$conf['api']['dev']` is 1.
- **`tools/release.php`, `tools/sign.php`:** build from a tag and sign offline (service `update`).

## Later

Recorded so it is not forgotten; not part of the steps.

- **Staged rollout of `stable`:** the manifest gets `rollout` 1–100; a site takes the release when
  `crc32(site id) % 100 < rollout`, so a release can reach 10 % of sites first and 100 % a few days later; `beta`
  sites always get it at once; the owner raises the number by re-signing the manifest.
- **Move this plan to a stable name** (e.g. `docs/API.md`) once step 10 is done, and point `.rules/api.md` at it.

## Open questions

None; all decided on 2026-10-07 (section "Decisions"). A new question goes here before a step starts.

## Steps

Order decided on 2026-10-07: Vine right after clients and guards.

- [ ] 1. Light boot: `API_FILE` in `core/system.php`, `public/api.php`, rewrite rules, `.rules/architecture.md`
  updated; `.rules/api.md` (written and listed in `CLAUDE.md` on 2026-10-07) checked against the code as it lands.
  Done when `api.php` boots without theme and template and three modules of `index.php` and `admin.php` render
  unchanged.
- [ ] 2. Core of the connector: `ApiRouter` with the guards that need no client (`checkApiRoute`, `checkApiMaint`,
  `checkApiCors`, `checkApiSize`, `filterApiInput`), `ApiInput`, `ApiError`, `setApiReply()` with `meta.now`, service
  discovery from enabled modules with the declaration format, `system/ping`, `system/schema` (guest view);
  `config/api.php` (`active`, `off`, `maint`, `cors`, `limits`, `log`); `tools/api.http` with `ping` and `schema`,
  `tools/.env` in `.gitignore`. Done when the unit tests for routing,
  discovery, envelope and codes pass and a test module with an `api.php` answers without any core edit.
- [ ] 3. Clients and guards: `config/api.php` (`dev`), `api_client`, keys, scopes, `checkApiFlood`, `checkApiAuth`,
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
- [ ] 8. Service `update`: `tools/release.php` and `tools/sign.php`, manifest with type tag, `expires` and `commit`,
  key store with primary and recovery, `update/check` on the master, channels `stable`/`beta` with promotion, the
  check with the switch and the GDPR note, download, unpack, backup, apply on click with automatic restore. Done when
  each of these is refused before unpacking: tampered zip, tampered manifest, a release signed by the recovery key,
  wrong channel, an older or equal version, an older `time`, a zip with `..` or a symlink, a zip bomb; and when a
  `beta` → `stable` switch does not downgrade, a key statement signed by the recovery key with a higher `kseq` is
  accepted, one with a lower `kseq` or signed by the primary is refused, and a recovery replacement is accepted only
  with both signatures.
- [ ] 9. Service `ai`: tool table, MCP endpoint `/api/v1/ai/mcp` (`server/discover`, `tools/list`, `tools/call`,
  transport checks, older-client support, Bearer key), outbound provider calls through the queue. Done when Claude
  Code connects with the key, lists the flagged tools and calls `vine_stats`, a client of the older revision connects
  too, and a tool whose scope the key lacks is neither listed nor callable.
- [ ] 10. Review: security pass over every service, load test of `vine/beat` and `content` reads, docs.
