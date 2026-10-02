# SLAED Architecture

This document is the current architecture map for this repository. It describes
confirmed runtime structure and points to topic-specific documents instead of
duplicating their details.

## Scope

This guide covers:

- request entry points
- bootstrap and configuration flow
- frontend and admin routing
- core runtime objects and global dependencies
- module, block, template, editor, parser, cache, and scheduler boundaries
- performance-sensitive and refactor-sensitive areas

This guide does not describe a future framework, dependency injection container,
middleware stack, headless API, or module manifest system unless such behavior is
present in the current repository.

## Runtime Shape

SLAED currently uses a procedural PHP runtime with shared bootstrap state. The
main execution model is:

1. an entry script defines the request context
2. `core/system.php` loads early classes, configuration, security, helpers, and
   theme runtime
3. routing selects a frontend module, admin module, helper endpoint, or scheduler
   operation
4. modules call `setHead()`, prepare data, and emit content
5. `setHead()` and `setFoot()` assemble the page shell through the shared
   `Template` instance

`setHead()` is also the single serialization boundary for public SEO data. Modules pass facts (`kind`, title, description, dates, author, image, and optional JSON-LD objects); core resolves robots/canonical policy, Open Graph, route schema, and breadcrumbs. Configurable Graph/Schema templates are decoded before values are substituted and are re-rendered through safe fragments.

There is no central application kernel, route registry, or framework middleware
pipeline in the current codebase.

## Entry Points

### Frontend

File:
- `index.php`

Role:
- defines `MODULE_FILE`
- defines `BASE_DIR`
- loads `core/system.php`
- reads `go`, `name`, `op`, and `file` through `getVar()`
- routes normal page requests to `modules/<name>/<file>.php`
- routes helper/AJAX operations through numeric and named `go` branches

Normal frontend module route:

```text
index.php
  -> core/system.php
  -> getVar('req', 'name')
  -> modules/<name>/<file>.php
  -> setHead()
  -> module output
  -> setFoot()
```

Home route:

```text
index.php
  -> configured module list from config/global.php
  -> requested file, defaulting to index
  -> modules/<selected-module>/<file>.php
```

If `config/global.php` contains a comma-separated module list, the home route
selects one entry for the request. If no home module is configured, the runtime
renders an empty page shell.

The home route reads the `side` and `top` positions of the selected module from
`config/modules.php` the same way the named route does, so `admin.php?name=modules`
governs the block columns of the start page too. A module whose output carries its
own `h1` sets `$sitevars['has_own_title']` after `setHead()` — `setHead()` rebuilds
that array — and `layouts/home.html` then skips the site name heading; `layouts/app.html`
never prints one. `modules/presentation` is the module that does this.

Frontend module access checks are performed before including module files:

- `view = 0`: public module
- `view = 1`: authenticated user/group access or moderator access
- `view = 2`: moderator access
- inactive modules can still be reached by module moderators

Frontend helper routing includes:

| `go` value | Role |
|---|---|
| `1` | common AJAX helpers such as comments, ratings, favorites, voting, and sessions |
| `3` | scheduler HTTP runner |
| `4` | uploads and editor file helpers |
| `5` | admin-only helper endpoints |
| `rss` | RSS output |
| `search` | OpenSearch output |
| `xsl` | XSL output |
| `asset` | generated asset output |
| `captcha` | captcha provider endpoint |

### Admin

Files:
- `admin.php`
- `admin/index.php`

Role:
- `admin.php` defines `ADMIN_FILE`, defines `BASE_DIR`, and loads
  `admin/index.php`
- `admin/index.php` loads `core/system.php`
- admin language and access checks are performed before admin routing
- authenticated admin routing reads `name`, `op`, `id`, and `act` through
  `getVar()`
- unauthenticated admin flow reads POST `op` for login and initial administrator
  creation actions

Admin route shape:

```text
admin.php
  -> admin/index.php
  -> core/system.php
  -> checkAccess()
  -> admin/modules/<name>.php for core admin modules
  -> modules/<name>/admin/index.php for feature admin handlers
  -> setHead()
  -> admin output
  -> setFoot()
```

The admin dashboard and sidebar are assembled in `admin/index.php` and
`core/admin.php`.

### Installation

Files:
- `setup.php`: the installer, one file in the root
- `storage/update/sql/table.sql`, `storage/update/sql/insert.sql`: the schema and
  the seed it runs
- `templates/admin/pages/setup.html`: the page of every stop

Role:
- `setup.php` installs a new site and nothing else. It updates no existing site;
  a 6.2 site is brought over by `update.php`, an internal tool of the maintainer
  that the release does not ship (`docs/NODE.md`, "The 6.3 update").
- When the run is over, the closing stop deletes `setup.php`. This is the only
  place it deletes itself. A file the server would not let go, or one that stays on
  an installed site, leaves the `_DELSETUP` warning on the closing stop and on the
  dashboard of the panel.

Boot:
- every request defines `SETUP_FILE`, `BASE_DIR`, `CONFIG_DIR`, `BACKUP_DIR` and
  `LOGS_DIR`, reads `config/global.php` and `config/security.php`, loads
  `core/admin.php` (`getSqlbatch()`, `getSqlinfo()`, `addNodeProfiles()`), starts
  the session, then defines `FUNC_FILE` and loads `filemanager`, `logger`, `pdo`,
  `template`, `lang/<code>.php` and `admin/lang/<code>.php`
- `core/system.php` is not loaded: a site without `config/db.php` cannot boot it.
  Only the administrator part of the run boots the core (`MODULE_FILE`, then
  `getLang('admin')`) on the configuration the earlier parts wrote
- because that part loads the core into the same process, no function of
  `setup.php` carries a name the core declares; all of them are `*Setup*`
- `Database` throws a `RuntimeException` under `SETUP_FILE` instead of calling
  `setExit()`, and the installer shows the reason
- the request is read only through `getSetupVar()`; the core `getVar()` is not
  loaded

Installed site:
- `checkSetupDone()` counts a site as installed when `config/db.php` names a
  database whose `_admins` table holds a row, or that does not answer at all, so a
  site whose server is down is never handed to a visitor. Only the browser whose
  session holds a run under way passes.
- `setSetupShut()` answers an installed site with `_SETUP_INSTALLED`, without a
  form, a write or a delete, and drops the session of the installation; `setup.php`
  stays, so a development copy of an installed site keeps its installer

State and CSRF:
- the answers of the stops live in the PHP session under
  `$conf['user_c'].'-setup'`, never in hidden fields or cookies; no password is
  printed back into a form, and the administrator password is kept only as its
  hash
- the first form puts a random token into that session. Every form carries it in
  the hidden field `token`, and `checkSetupToken()` guards every POST and every
  part of the run; the token of another browser is refused like none. This is the one exception to `getSiteToken()`,
  recorded in `.rules/global.md`
- every form posts `token`, `stop` and `go` (`back`, `probe`, `next`, `part`);
  `reach` in the session is the furthest stop the browser may post from, and a run
  under way forces every request to the run stop

Stops, each its own request, seven on the road of the card:
1. language: six tiles; the choice becomes the core cookie
   `{user_c}-language` in the administrator part
2. server: PHP 8.4, mbstring, PDO MySQL, JSON, writable `config/`
   (with `db.php`, `global.php`, `security.php`, `update.php`), `storage/`,
   `uploads/`, and the protocol; a failed row keeps the stop. Zip and Zlib are
   optional: a missing one is a warning row that lets the stop pass
3. database: host, user, password, name and a random prefix; `go=probe` runs
   `getSetupProbe()`: the connection, MariaDB 10.5.2+ or MySQL 8.0.16+, and a
   prefix no table of the database carries
4. site: name, address (http or https, no user, query or fragment) and a random
   panel file name; `index`, `setup`, `update` and another file of the root are
   refused
5. administrator: nickname, e-mail, password twice, and whether a site account of
   the same name and password is created; Install runs `checkSetupRun()`:
   grammar of prefix and panel, the four config files writable, no
   `storage/backup/config/marker.json`, both SQL files split, the probe again
6. run: the progress line of the admin theme; the driver in
   `templates/admin/assets/js/admin-ui.js` posts `go=part` until `more` is false,
   then submits the form with `go=next`
7. done: the panel address, which the site never links, the self-deletion, a
   Node profile that could not be finished, and the links to the site and the
   panel

Run, in parts (`getSetupParts()`), each its own POST:
- configuration: the probe again, `global.php` (`language`, `homeurl`,
  `sitename`), the rename of `admin.php` to the panel name, `security.php`
  (`afile`), `db.php`, and `deleteSetupTypes()` removing the Node types of the
  shipped `config/node.php` with their package; the database password leaves the
  session afterwards
- `table.sql` in groups of seven statements, then every statement of
  `insert.sql` alone; the last one writes the marks `points`, `ratings`, `fields`
  and `node` of `config/update.php`, so a failed statement leaves no mark
- administrator: the core boots, the `_admins` row and the optional `_users` row
  take the hash, then `addNodeProfiles()` creates the ten shipped types and the
  starter news and removes the mark `node`
- a part is marked busy in the session before it runs, and a request that meets a
  busy part fails the run instead of running it twice. A failed part sends the
  browser back to the administrator stop with the reason; once a table part has
  run, Install again meets the taken prefix, so the tables are dropped or another
  prefix is chosen

Page:
- every stop renders `pages/setup.html` on `layouts/bare.html` of the admin
  theme; the stop is data, PHP prints no markup and no class, and the styles are
  the `sl-setup-*` rules of `templates/admin/assets/css/theme.css` with the
  tokens `--sl-setup-*` declared in `tools/ui-contract.php`
- the texts are the `_SETUP_*` constants of `admin/lang/*.php` beside globals of
  `lang/*.php` and `admin/lang/*.php`, so a future update module can reuse them

Recovery:
- `addAdminAccount()` and the empty-table form of `admin/index.php`
  (`op=add_admin`, token scope `add_admin`) stay as the way back into the panel
  when the admins table has lost its last row

Tests: `tests/Unit/NodeProfileTest.php` walks the installer over real HTTP
through `tests/Support/install_probe.php`; `tests/Unit/UpdateSetupTest.php`
checks its preflight and the order of its checks.

## Bootstrap

Main bootstrap file:
- `core/system.php`

Confirmed bootstrap responsibilities:

- defines common directories
- loads early classes such as `Editor` and `Logger`
- loads configuration through `getConfig()`
- applies admin theme override when `ADMIN_FILE` is defined
- loads `core/security.php`
- `core/security.php` loads `core/classes/pdo.php`, initializes `$db`, and starts
  request security/session handling
- resolves and loads the active theme hook
- loads `Template`, `Parser`, `Geoip`, `Captcha`, and `Cache`
- creates shared runtime objects, in this order because the second needs the first:
  - `$tpl = new Template($theme)`
  - `$prs = new Parser()` - every element the parser emits is rendered by a theme fragment, so without `$tpl` it produces text with no markup at all
- loads helpers and admin helpers when needed

Configuration loading:

- `getConfig()` first reads `config/local.php` when it contains a valid generated
  cache payload
- if the generated cache is missing or invalid, source files under `config/*.php`
  are loaded, merged, hashed, and written back to `config/local.php`
- when `config/local.php` is valid, source config fingerprints are not checked on
  each request; direct edits to source config files require cache regeneration
  before they affect runtime config

## Core Runtime Objects

| Object | Source | Role |
|---|---|---|
| `$conf` | `core/system.php`, `config/*.php`, `config/local.php` | merged runtime configuration |
| `$db` | `core/security.php`, `core/classes/pdo.php` | PDO-backed database adapter |
| `$tpl` | `core/system.php`, `core/classes/template.php` | shared template renderer |
| `$prs` | `core/system.php`, `core/classes/parser.php` | shared parser instance |
| `$user` | `core/security.php` | current user context |
| `$admin` | `core/security.php` | current admin context |
| `$afile` | `core/security.php` | configured admin entry filename |

These globals are part of the current runtime contract. New code should avoid
introducing additional global state unless there is a clear compatibility reason.

## Routing Keys

Frontend request keys:

- `name`: target module directory under `modules/`
- `file`: target module file, default `index`
- `op`: module operation
- `go`: helper/AJAX/scheduler branch selector

Admin request keys:

- `name`: target admin module or module admin area
- `op`: admin operation, default `show`
- `id`: numeric entity identifier
- `act`: numeric action flag

Input should be read through `getVar()` rather than directly from `$_GET`,
`$_POST`, or `$_REQUEST`.

## Module Boundary

Current frontend modules live under:
- `modules/`

Current core admin modules live under:
- `admin/modules/`

Feature modules can also provide admin handlers under:
- `modules/<name>/admin/index.php`

Module availability and navigation metadata come from configuration, especially
`config/modules.php`. That file contains both site modules and core admin module
metadata. `type = 1` marks site modules and `type = 0` marks core admin modules.
Frontend routing checks the configured module state and access flags before
including module files. Admin routing uses access checks and file existence;
inactive modules can still appear dimmed in admin navigation.

Modules are still PHP include targets, not isolated service packages. A module
can use shared globals such as `$conf`, `$db`, `$tpl`, `$prs`, `$user`, and
`$admin` when it runs inside the normal bootstrap.

## Block Boundary

Block files live under:
- `blocks/`

Block definitions and placement are database-driven. `setFoot()` asks
`getBlocks()` for footer, left, right, center, and down zones; file-backed blocks
are included from `blocks/<file>`.

Free blocks (the `infly` mark in the `which` column) are excluded from zone
rendering and are output in two ways: trusted content places a `[block=id]`
tag (resolved by the parser for admin-authored content with `$safe === false`,
left as literal text in user-submitted content), and theme templates place a
`{% freeblock id %}` tag (compiled by the template engine). Both entry points
call the same `getBlocks()` engine, apply the same visibility rules, and do
not expand nested tags inside block output.

Block rendering is part of the frontend page assembly path. Treat it as a shared
runtime boundary, not as module-local output.

## Template Boundary

The active template runtime is `Template` in:
- `core/classes/template.php`

Current theme directories:
- `templates/admin`
- `templates/lite`

Page output should be finalized through `setHead()` and `setFoot()` unless the
route is a deliberate helper endpoint that returns JSON, upload output, or
another non-page response.

Detailed template contracts live in:
- `docs/TEMPLATES.md`

## Parser Boundary

The active content parser is `Parser` in:
- `core/classes/parser.php`

Use the parser for Markdown/BBCode/content rendering. Security text filtering
helpers are not the main content rendering pipeline.

Detailed parser contracts live in:
- `docs/PARSER.md`

## Editor And Plugin Boundary

The editor system is managed by:
- `core/classes/editor.php`
- `plugins/editors/*/manifest.json`
- `plugins/editors/*/driver.php`

Editor plugins use the strongest plugin-like contract currently present in the
repository: manifest metadata, typed drivers, validation, and runtime discovery.

Other plugin directories under `plugins/` are not all governed by the same
contract. Treat `docs/PLUGINS.md` as a design note unless the code implements the
specific plugin behavior being discussed.

Detailed editor and plugin notes live in:
- `docs/EDITORS.md`
- `docs/PLUGINS.md`

## Database Boundary

Database access goes through:
- `core/classes/pdo.php`

Current behavior:

- `Database` wraps PDO
- `core/security.php` creates `$db`
- `PREFIX_DB` is defined from database config
- `getSqlQuery()` supports prepared statements with named or positional
  parameters

New and refactored SQL should use prepared statements and named placeholders
where practical.

## Security Boundary

Security bootstrap lives in:
- `core/security.php`

Confirmed responsibilities:

- initializes `$db`
- defines `PREFIX_DB`
- initializes admin/security aliases
- starts the PHP session when needed
- runs request blocker and request value checks
- provides `getVar()`
- provides CSRF helpers such as `getSiteToken()` and `checkSiteToken()`
- provides admin/user access helpers

Security can exit early for blocked or invalid requests before the normal
`setHead()` / `setFoot()` page lifecycle.

Security behavior is part of the runtime contract. Do not optimize or bypass it
without focused regression tests.

## Cache And Scheduler Boundary

Cache runtime:
- `core/classes/cache.php`

Page-cache integration:
- `checkPageCache()`
- `Cache::setBody()`
- `Cache::setHeaders()`

Scheduler integration:
- HTTP runner through frontend helper branch `go = 3`
- scheduler configuration under `config/scheduler.php`
- scheduler admin module under `admin/modules/scheduler.php`
- pseudo-trigger injection from `setHead()` when scheduler config, heartbeat,
  due-job, and cooldown checks allow it
- scheduler state and locks under `storage/logs/scheduler/`

The confirmed current runner is HTTP/admin-triggered. Heavy jobs should stay out
of synchronous page-render side effects.

Server metrics:
- `core/monitor.php` holds the CPU, memory, network, disk, uptime, log-tail and
  security counters; `admin/modules/monitor.php` keeps only its page, charts and
  template variables
- the `monitor` scheduler job (`* * * * *`, priority 9, the lowest, so it never
  takes a tick from `maildrain` or `newsletter`) calls `addMonitorSample()`, which
  runs the two history writers of `storage/logs/monitor.json` and then stores
  `disk_pct`, `disk_used`, `disk_total`, `uptime`, `cores`, `soft` and `sampled_at`
- a public reader (`modules/presentation`) only calls `getMetricStore()` and treats a
  missing store or a `sampled_at` older than five minutes as "no monitor": the
  pseudo-cron ticks once per page view, so after a quiet spell the first visitor
  sees the section without figures until the job runs again
- the tail of the security logs never reaches a public page; `getSecurityEventCount()`
  returns a number, `getSecurityEventHours()` stays admin-only

## Upload Place Boundary

An upload rule belongs to a **place**, not to a module. One module can hold two
unrelated stores: `files` keeps attachments to the description text under
`$conf['uploads']['files']` and the distributed file under `$conf['files']`;
`account` keeps attachments under `$conf['uploads']['account']` and the avatar
under `$conf['users']`. These are two different things and one rule cannot hold
both, so the uploader opens for a place and reads the settings of that place's
module. The configs stay where they are and no administrative settings screen is
involved.

**The grammar lives in `getUploadPlaceRule()` (`core/system.php`) and nowhere
else.** A place is `^[a-z0-9_]+\.[a-z0-9_]+$`. Anything else answers `ok => false`.
No caller carries a pattern of its own. Two branches on the suffix:

- `<mod>.attach` — `getUploadRuleData(<mod>)`, the pipe-separated config string;
- `users.avatar` — `$conf['users']`.

**The returned array answers every field the routes read**, not only the limits,
so no route reassembles a right from config:

| Key | `<mod>.attach` | `users.avatar` |
|---|---|---|
| `mod` | `<mod>` | `account` |
| `extensions` | config | `atypefile` |
| `maxbytes` | config | `amaxsize` |
| `maxwidth` / `maxheight` | config | `awidth` / `aheight` |
| `maxfiles` | config | 1 |
| `maxquota` | config | 0 |
| `thumbwidth` | config | 0 |
| `userupload` | config | `aupload` |
| `guestupload` | config | 0 — always |
| `moderfiles` | config | 250 |
| `userfiles` | config | 100 |
| `guestfiles` | config | 0 — always |
| `dir` | `uploads/<mod>` | `adirectory` |
| `canlink` | true | false |
| `ops` | all four | `editorFiles` only |

**`mod` is not the first segment of the place.** `users.avatar` maps to module
`account`, because that is the module whose moderator may moderate it and whose
page the form lives on. Every caller that needs a module name reads `$rul['mod']`
and never splits the place string.

**`users.avatar` is for a signed-in member only.** `guestupload` and `guestfiles`
are hard zero rather than read from config: an avatar belongs to an account and a
guest has none.

**A field place answers only one operation.** `ops` names which of the four
routes a place permits. `<mod>.attach` permits all four; `users.avatar` permits
`editorFiles` alone, because outside the editor the form
uploads and a reachable `editorUpload` would create orphaned files, while
`editorDelete` and `editorArchive` would hand a direct deletion route to a place
whose window never offers one. The gate is enforced in `getEditorRouteRule()`
beside the three guards it already runs, so no route restates it and none can
ship with it quietly missing. An interface that draws no button is not a guard.

**The place travels in the address as `place` and is read `raw`.** `filterVar()`
empties any string carrying a dot, so `users.avatar` cannot travel as `mod` at all;
the grammar above is what validates it. The `$go == 4` entry guard in `index.php`
reads `place`, and every endpoint URL is built server side —
`index.php?go=4&op=editorUpload&place=forum.attach`.

The window built on this boundary is documented in `docs/WINDOW.md`; the runtime
that drives it in `docs/EDITORS.md`.

## Storage Boundary

Runtime-generated files are stored under:

- `storage/cache/`
- `storage/cache/pages/`
- `storage/cache/templates/`
- `storage/captcha/`
- `storage/counter/`
- `storage/geoip/`
- `storage/logs/`
- `storage/logs/scheduler/`
- `storage/sitemap/`
- `storage/backup/`

`storage/update/sql/` is source, not runtime data: the schema `table.sql`, the
seed `insert.sql` and the 6.2 update `table_update6_3.sql`. `storage/.htaccess`
keeps all of `storage/` from the web.

Uploads are stored under:
- `uploads/`

Do not treat generated storage contents as source files. Documentation and tests
should describe the directories and contracts, not specific generated artifacts.

## Assets

Theme-local assets belong under each theme:

```text
templates/<theme>/assets/
```

The current runtime discovers theme assets and can inject companion assets for
template partials/components. Detailed asset behavior is documented in
`docs/TEMPLATES.md`.

## Request Flow Summary

### Frontend Page

```text
index.php
  -> core/system.php
  -> core/security.php
  -> Template / Parser / Geoip / Captcha / Cache
  -> module include
  -> module calls setHead()
  -> module content
  -> setFoot()
  -> Template::getHtmlPage()
```

### Admin Page

```text
admin.php
  -> admin/index.php
  -> core/system.php
  -> core/security.php
  -> checkAccess()
  -> admin module include
  -> admin handler calls setHead()
  -> admin content
  -> setFoot()
  -> Template::getHtmlPage()
```

### Helper Endpoint

```text
index.php
  -> core/system.php
  -> go branch
  -> CSRF/access checks where required
  -> JSON, upload, scheduler, or fragment response
```

Helper endpoints should set their response type explicitly when they do not
return a normal HTML page.

## Performance-Sensitive Areas

Current high-interest areas:

- changelog GitHub cache miss
- template filesystem metadata checks
- frontend tracking and block rendering
- admin module/sidebar assembly
- GeoIP MMDB loading
- security request scanning
- scheduler heavy jobs

Detailed performance guidance lives in:
- `docs/PERFORMANCE.md`

## Refactor Blockers

Treat these as current constraints:

- many modules depend on shared bootstrap globals
- module routing is include-based
- admin and frontend rendering both depend on `setHead()` and `setFoot()`
- template, parser, security, and database helpers are shared across the current
  runtime
- config values are loaded into a merged `$conf` array
- admin module handlers often depend on `op` switches and include-time state
- block rendering is database-driven and can include files from `blocks/`
- helper endpoints can bypass the normal page lifecycle and return specialized
  responses

When refactoring, preserve behavior first and move one boundary at a time.

## Testing And Quality Gates

The repository defines test and analysis tooling through:

- `composer.json`
- `phpunit.xml`
- `phpstan.neon`
- `.php-cs-fixer.dist.php`
- `package.json`

Main documented commands:

- `composer test`
- `composer analyse`
- `composer quality`
- `npm run browser:audit`
- `npm run ui:gates` (theme and markup gates; `tools/hooks/pre-commit` runs the same fast set)

Detailed testing guidance lives in:
- `docs/TESTS.md`

## Documentation Map

Use these documents as the primary sources for their topics:

| Topic | Document |
|---|---|
| Engineering principles | `docs/PRINCIPLES.md` |
| Template runtime, theme contract and gates | `docs/TEMPLATES.md` |
| Window canon | `docs/WINDOW.md` |
| Parser behavior | `docs/PARSER.md` |
| Editor system | `docs/EDITORS.md` |
| Plugin architecture notes | `docs/PLUGINS.md` |
| Performance | `docs/PERFORMANCE.md` |
| Tests and quality gates | `docs/TESTS.md` |
| Upgrades | `UPGRADING.md` |
| Security policy | `SECURITY.md` |

## What Not To Assume

- Do not assume a framework kernel exists.
- Do not assume a dependency injection container exists.
- Do not assume a middleware pipeline exists.
- Do not assume all plugins share the editor manifest contract.
- Do not assume modules are isolated packages.
- Do not assume page cache, asset cache, or scheduler jobs are enabled without
  checking current configuration.
- Do not copy architecture claims from other projects without validating them
  against this repository.
