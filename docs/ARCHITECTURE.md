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
- module, block, layout, template, editor, parser, cache, scheduler and journal boundaries
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

There is no central application kernel or framework middleware pipeline in the
current codebase. The one registry of routes is that of public addresses, `_routes`:
it maps a path to an entity, never to code (see "Address Boundary").

## Entry Points

### Frontend

File:
- `public/index.php`, the front controller in the document root

Role:
- defines `BASE_DIR` (the folder above it, the project) and `PUBLIC_DIR` (its own folder) unless a test or a tool
  defined them first
- defines `MODULE_FILE`, loads `core/stream.php` and answers a request under `uploads/` there through the light
  path, before the core boots
- answers a request under `api/` as the one entry of the API: it defines `API_FILE`, loads the light core of
  `core/system.php` - configuration, database, security, logger, cache, mail and helpers, without theme, template,
  parser, visual classes, session or the layer of the user - and `ApiRouter::setApiReply()` writes the JSON reply of
  `ApiRouter::getResponse()`; in this mode `core/security.php` keeps no path, reads no account cookie, scans no input
  (`ApiInput` is the filter of the API) and answers a ban, a lost database or an uncaught error in JSON without
  detail (`.rules/api.md`)
- the router of the API: a path `v<n>/<service>/<action>` names a core service of `core/classes/api/services/` or
  the `api.php` of an active module; only the service the path names is read, so no list is kept. The guards run
  in the order of `.rules/api.md` - route, maintenance and switches, CORS, the bucket of the address (`ApiLimit`,
  IPv6 by its /64, the address only as a keyed hash in the file cache), auth, size, typed input (`ApiInput`) -
  each refusal an `ApiError` of the closed code list; the `common.php` of a module loads after the last guard, and a
  fault of a handler is logged under the request id and answers 500 `internal`. `config/api.php` holds the switches
  (`active`, `maint`, `off`), the global CORS list and the limits (`flood`, `burst`, `size`); `system/ping` and
  `system/schema`, the live reference built from the declarations, are the core service
- loads `core/system.php`
- resolves a path the cut of `core/security.php` kept into the route keys of `$_GET` (see "Address Boundary")
- reads `go`, `name`, `op`, and `file` through `getVar()`
- routes normal page requests to `modules/<name>/<file>.php`
- routes helper/AJAX operations through numeric and named `go` branches

Normal frontend module route:

```text
index.php
  -> core/system.php
  -> a path resolved into name, op, id, cat by the configuration and _routes
  -> getVar('req', 'name')
  -> modules/<name>/<file>.php
  -> setHead()
  -> module output
  -> setFoot()
```

Home route:

```text
index.php
  -> the page home of config/layout.php
  -> the module its slot names, else the first entry of the module list of config/global.php
  -> requested file, defaulting to index
  -> modules/<selected-module>/<file>.php
  -> setFoot() draws the sections of the page around the module output
```

The start page is the page `home` of the layout (see "Layout Boundary"). The module
slot of that page names the module of the home route; a slot left empty, or a layout
without a home page, takes the first entry of the comma-separated module list of
`config/global.php`. With no module the runtime renders the sections of the page alone.

The block columns of the start page are the `side` and `top` of the page itself
(`Layout::getPageColumns()`, applied in `setFoot()`), which win over those of the
module; without a home page in the layout the home route reads them from the module
row of `config/modules.php` as the named route does. The columns have one owner per
thing: a module its row of `config/modules.php` (the form "Модули"), a Node type its
`view` (the type form), a page of the builder without a module the page. The page whose
slot holds a module answers `null` from `getPageColumns()`, so its index and its inner
pages show the columns of the module; the builder shows them there with a link to the
owner form and stores none. A module whose output carries its
own `h1` sets `$sitevars['has_own_title']` after `setHead()` — `setHead()` rebuilds
that array — and `layouts/home.html` then skips the site name heading; `layouts/app.html`
never prints one. `modules/presentation` is the module that does this. A Node list run as the
home page keeps the site name as the one `h1` and prints its own title as `h2` (`is_home` of
`getNodeHead()`); the first page of that type at its own address names the site root as canonical.

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
| `file` | a file of a closed owner, Node included, granted by `FileAccess` (`setFileRoute()`); every refusal is 404 |

### Admin

Files:
- `public/admin.php`, the short entry the installer and the security section rename to the panel name
- `admin/index.php`

Role:
- `public/admin.php` defines `ADMIN_FILE`, `BASE_DIR` and `PUBLIC_DIR`, and loads
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
  the seed it runs; `storage/update/sql/node.sql`: the rubrics of the Node types,
  run by `addNodeProfiles()` once the types exist, as a type is not created over
  rubrics of its name
- `templates/admin/pages/setup.html`: the page of every stop

Role:
- `setup.php` installs a new site and nothing else. It updates no existing site;
  a 6.2 site is brought over by `update.php`, an internal tool of the maintainer
  that the release does not ship (`docs/NODE.md`, "The 8.0 update").
- When the run is over, the closing stop deletes `setup.php`. This is the only
  place it deletes itself. A file the server would not let go, or one that stays on
  an installed site, leaves the `_DELSETUP` warning on the closing stop and turns the
  lamp of the cockpit on the home of the panel yellow (signal `setup`).

Boot:
- the installer is `public/setup.php`; every request defines `SETUP_FILE`, `BASE_DIR`, `PUBLIC_DIR`, `CONFIG_DIR`, `BACKUP_DIR` and
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
   `getSetupProbe()`: the connection, MariaDB 10.5.2+ or MySQL 8.0.16+ (MySQL 8.4
   or MariaDB 10.11 and newer recommended), and a prefix no table of the database
   carries
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
  `sitename`, and `adminmail`, an empty `slogan`, the signature of `mtemp` and `schema`
  without the accounts of slaed.net, so no new site mails or names the vendor as itself), the rename of `admin.php` to the panel name, `security.php`
  (`afile`), `db.php`, and `deleteSetupTypes()` removing the Node types of the
  shipped `config/node.php` with their package, and the contacts of the shipped
  `config/layout.php` give way to the address of the site and the mail of the
  administrator, its version plate switched off, the season line titled with the name of the
  site and the links of the technology tiles removed; the database password leaves the session afterwards
- `table.sql` in groups of seven statements, then every statement of
  `insert.sql` alone; the last one writes the marks `points`, `ratings`, `fields`
  and `node` of `config/update.php`, so a failed statement leaves no mark
- administrator: the core boots, the `_admins` row and the optional `_users` row
  take the hash, then `addNodeProfiles()` creates the shipped types, seeds
  `node.sql`, adds four starter news and removes the mark `node`
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
- `core/security.php` first cuts the path, before the database: for `index.php` it
  keeps up to five segments in `$urlroute` and answers 404 for any other path (see
  "Address Boundary"); for the other scripts a path after the script answers 301
  to the address of the script and any other path gets the 404 page
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
- `id`, `cat`, `uname`: the entity of a page; a page number of a public list is `page`

On a page of the site the keys usually come from the path, which `public/index.php`
writes into `$_GET` before the first read (see "Address Boundary").

Admin request keys:

- `name`: target admin module or module admin area
- `op`: admin operation, default `show`
- `id`: numeric entity identifier
- `act`: numeric action flag

Input should be read through `getVar()` rather than directly from `$_GET`,
`$_POST`, or `$_REQUEST`.

## Address Boundary

Every page of the site has one readable address without an `id`, kept in one registry; every other form of the same
page answers one 301 to it, and an address once given never points anywhere else.

The addresses below show the form of each kind of page; the names in them are examples, not pages of a particular
site.

| Page | Address |
|---|---|
| Home of a type or a module | `/files`, `/forum`, `/users` |
| Material, category of a type | `/files/adaptivnyi-shablon-pure`, `/files/moduli` |
| Page of a list; sorting, letter, filter | `/files/moduli?page=2`; `/files?order=views`, `/files?let=a` |
| Forum topic, forum section | `/forum/modul-poiska`, `/forum/obshchie-voprosy` |
| Profile, poll | `/users/eduard`, `/voting/kak-vam-novyi-dizain` |
| Page of the site builder | `/o-kompanii` |
| Operations | `/files/add`, `/files/asset/2`, `/account/login`, `/account/privat` |

- The prefix of the type or module is part of every address; the separator is a fixed `/`, no setting moves it. A
  page of the site builder answers at the root, as a type does.
- Materials and categories of a type share one namespace; the category is not in the address of a material, so a
  material keeps its address when it moves. The forum names topics and sections the same way under `/forum`.
- The profile lives under the list of users and is named by its login: a new login in the panel moves it, and the old
  path is never handed to the next user who takes the freed login.
- A private type (`ext` `support`) has no address in the registry: its materials stay `/<type>/view/<id>` with
  `noindex`, so the title of a request never enters an address.
- AJAX and feeds (`go=…`), the panel, `/uploads/` and the module sub-files (`&file=…`) keep their query addresses.

### The registry

`_routes` (`storage/update/sql/table.sql`): `path` (ASCII, binary, unique, without the leading slash), `kind`
(`type`, `node`, `cat`, `topic`, `user`, `poll`, `page`), `oid` (for `page` the permanent number of the page in
`config/layout.php`, whose owner is the layout and not a row of a table), `state` (`1` current, `0` former, `2` gone) and `cur`, a stored
column generated from `state` — `1` on the current row, `NULL` on every other — so the unique key `(kind, oid, cur)`
lets an entity hold any number of former paths and at most one current one. No foreign key: the rows outlive their
entity on purpose.

- A former path answers 301 to the current path of the same entity, never to the path that followed it, so there is
  no chain. When an entity is gone every row of it gets `state = 2` and its paths answer 410 for ever.
- One class owns the table, `Route` in `core/classes/route.php` (`new Route($db)`): `getRoute()` finds a path with the
  current path of its entity in one query, `getRouteList()` answers the current paths of a set of entities,
  `addRoute()` gives the first path, `setRoute()` moves it, `setRouteName()` applies the field "Адрес", `deleteRoute()`
  marks every row gone, `isRouteReserved()` and `checkTypeName()` answer the reserved words. No other code writes the
  table.
- A write runs inside the transaction of its writer, behind a savepoint, or opens its own. A path that is taken —
  current, former or gone — or reserved takes the first free suffix `-2`, `-3` …; a racing writer that took the path
  first answers 1062 and the write moves on to the next suffix, at most 20 times. A move to a path the entity held
  before takes that row back.
- **A lost transaction.** With `innodb_snapshot_isolation` (the MariaDB default from 11.6.2) the server answers two
  moves of one entity, and on some versions the race for one path, with 1020 instead of waiting it out; 1020 and a
  deadlock 1213 roll the whole transaction back, so no savepoint absorbs them. A write in its own transaction then
  runs whole again with a fresh snapshot, at most three times, and takes the next free suffix. A write joined to the
  transaction of its writer throws `RuntimeException`, as `Point` does (`docs/POINTS.md`): the operation of the writer
  ends without success, and only the whole operation can be repeated. `RouteRegistryTest` runs both races.
- **The name.** `filterSlug()` in `core/system.php` decodes the stored entities of a title, transliterates the letters
  of all six locales from one table (the letters ru and uk share take the Russian form; `і ї є ґ` → `i yi ye g`; `ü` →
  `ue`), lowercases, turns every run of other characters into one `-`, and cuts to 80 characters at the last `-` or,
  inside one long word, hard. An apostrophe joins its word. An empty result takes the `id`. No `intl`.
- **Reserved words.** Within a prefix, the operations of its module (`Route::OPS`, a prefix that is no module takes
  those of `getNodeOps()`) and `mode`, `newlang`, which `public/index.php` answers under any name; `users` reserves
  `rules` and `stats` in front of the profiles. For the name of a new Node type, the modules, the entries of `public/`,
  `uploads`, `api` and every `type` row of the registry, so the name of a deleted type is never given again.
  `RouteRegistryTest` holds the lists against the switches, `getNodeOps()`, `public/index.php` and the tree.
- **A page at the root.** `type` and `page` are the two kinds without a prefix (`checkRouteOwner()`). The name of a
  page is `filterSlug()` of its field "Адрес" or its title, one segment of `PATH`; at the root `isRouteReserved()`
  reserves `mode`, `newlang`, the entries of `TYPES` and the modules, and a path a type or another page holds takes
  the next suffix. Unlike a type a page moves, with 301 from its former path, as a material does.

### When an entity gets and changes its path

| Kind | First path | Changes | Gone (410) |
|---|---|---|---|
| `type` | creation of the type | never — the name is permanent | deletion of the type (only when empty) |
| `node` | its first move to Published — by hand, by the scheduler; Draft and Pending have none, a private type never | the field "Адрес" of the form | `deleteNode()` for good; the trash and Disabled keep the path |
| `cat` | creation, for the categories of a Node type and of the forum | the field "Адрес" of the category form | deletion of the category, its subtree with it |
| `topic` | creation of the topic; a reply the recycle section turns into a topic | never by the title | deletion for good; the recycle section keeps it |
| `user` | creation of the `_users` row: activation, OAuth sign-up, the panel, the first administrator | a new login in the panel | deletion of the account |
| `poll` | save of a poll without a module (a poll of a module has no page) | the field "Адрес" of the poll form | deletion |
| `page` | the publication of the layout that first holds the page, unless it is the home page, the page 404 or holds a module slot that is switched on | the field "Адрес" of the page, applied by the next publication | its deletion, applied by the next publication |

- The address follows the title only until the first publication; after that a new title, category or display name
  never moves it, only the field "Адрес" does. `Route::setRouteName()` is its one rule: the name the form gave, else
  the current name under a new prefix (a category moved to another module), else the first path from the title.
- The entity and its paths change in one transaction; a refused path rolls the whole write back.
  `getCategoryMap()` carries the path of every category, and a writer that moves one drops the map.
- **Migration.** `addRouteBacklog()` in `core/system.php` gives every entity that is due an address and lacks one its
  first path, in a fixed order so a collision always resolves alike: types; per type its categories before its
  materials; forum sections before topics; users; polls; inside each group the oldest `id` first. A material is due
  when it was ever public — Published, Disabled or Deleted with `published <= NOW()` — in a type that is not private.
  The second stage of `update.php` runs it on every run (`setMigrateRoutes()`), `setup.php` for its seed; a second
  call adds nothing.

### Parsing

Two stages, because the registry needs the database and the cut runs before it.

1. **The cut**, the first block of `core/security.php`, for `index.php` only: a path after the base, with
   `/index.php/` in front read as nothing, of up to five segments of `[A-Za-z0-9_-]` is kept lowercased in
   `$urlroute['seg']`. Any other path — a `.`, an encoded byte, an empty segment, more segments — answers 404 before
   the database. A path that names only the script is the start page. No 6.2 path form is parsed: 6.2 printed query
   addresses, and its `.html` scheme answers 404.
2. **The resolution**, in `public/index.php` after the check of a closed site and before the first `getVar()`, by one
   grammar, first match wins: a type or module alone is its home; with a reserved operation that operation, with
   digits after it also the `id` (`/files/asset/2`); any other path is asked of the registry by its whole length — a
   current or former path opens the page of its entity, a gone path answers 410, a path never given 404, a known
   prefix with more segments than its grammar 404. The page of an entity is `name=<type>` for `type`,
   `name=<type>&op=view&id=` for `node`, `name=<prefix>&cat=` for `cat`, `name=forum&op=view&id=` for `topic`,
   `name=account&op=view&id=` for `user`, `name=voting&op=view&id=` for `poll`, `name=page-<oid>` for `page`. The
   keys replace the same keys of the query in `$_GET`.

`getVar()` reads scalars from `$_GET` and `$_POST` like its array branch, not through `filter_input()`, so the keys a
path wrote reach every reader. The price of an address is one indexed query per page view, with no cache in front of
it.

### Building

`getSeoUrl()` in `core/system.php` is the one builder, and every address it answers starts at the root of the site,
`HOME_PATH` — the path of `homeurl` with both slashes (`/`, or `/sub/` below a folder), defined right after
`getConfig()`. `getPublicUrl()` makes any address of the site absolute.

- A passed `route` path absorbs `name`, `id`, `cat`, `uname` and an `op` of `view`; without one the address is the
  module, the operation and a numeric `id` in the path (`/news/asset/2`). The other keys are sorted by name, so a page
  has one query in every caller; `page` below 2 is left out.
- The stored path reaches the builder in the parameters; it never looks one up. A list brings it from a `LEFT JOIN` of
  the registry (`cur = 1`) in its own query; where no join fits (comment targets, favorites, a report) the caller asks
  `Route::getRouteList()` once for the set. The one lookup of the builder is a category link, answered from the
  category map. An entity that has no path by design is passed with `'route' => null` and gets
  `/<type>/view/<id>`; a call with `op=view` and an `id` or `uname` but no key `route` is a defect, logged to
  `error_site.log` with its caller.
- Every address the site prints starts at the root: page links through `getSeoUrl()`, mails through
  `getPublicUrl()`, and every endpoint `go=…`, form, htmx attribute, script address, theme image
  (`getThemeFileUrl()`), avatar and favicon from `HOME_PATH`, so a page opened two segments deep resolves nothing
  against its own path. Templates take `{{ root }}` (`docs/TEMPLATES.md`, "Links In Templates"). Stored texts keep
  the form `./index.php?go=file…`, which `Parser::filterUrl()` reads from the root.
- The `redirect_uri` of OAuth, the panel and its scripts, and the absolute addresses of the feed and the sitemap keep
  their form.

### One redirect

The parser never redirects. `setSeoMove()` in `core/system.php` collects every reason and answers once, after the
module has read its entity and checked access: the material (`setNodeView()`), the topic, the profile and the poll
call it right after their read, every other page reaches it as the first step of `setHead()`, so a redirect pays for
nothing else and counts no view.

- The target is the address of the page — its route keys and its current `route` through `getSeoUrl()`, or the `url`
  of a 6.2 address — with every other pair of the raw query kept in its order and spelling, `num` renamed to `page`
  (the activation code of `account` keeps its `num`) and `page=1` dropped; absolute, on the scheme and host of
  `forcessl` / `forcehost` or of the request.
- Reasons: a former path, upper case, a trailing slash, `/index.php/`, a query address of 6.2 or 8.0
  (`index.php?name=files&op=view&id=5061`, `?uname=`, `?num=`), the wrong scheme or host. Any mix is one 301.
- Never moved: a method other than `GET` / `HEAD` (a `HEAD` gets the same status and `Location`), an htmx request, an
  answer of 400 and above — the current path of a closed entity never leaks through the 301 of a former one — an
  endpoint `go=`, a module file, the panel, the CLI, another script, an unknown name and an operation the parser would
  not read back.
- The early `forcessl` / `forcehost` block of `core/security.php` serves the endpoints, the panel and the scripts
  only; a page leaves the host to `setSeoMove()`, so the wrong host and an old address are one hop.
- The query addresses of the removed 6.2 modules go through `_node_legacy`: `getNodeLegacyUrl()` answers the current
  registry path, and its callers — the not-found branch of `setNodeView()`, an unknown operation of a type and the
  unknown name of `public/index.php` — pass it to `setSeoMove()` as `url`.
- `getSeoRoute()` serializes the same address as the canonical one; page 2 and on carry their own `?page=N`.
  `getTplPagerView()` keeps the largest page count of the request, and `setFoot()` answers 404 to a `?page=N` above it,
  so a page without a list, or a number past its last page, has no copy under another address. The
  sitemap and the feed write `getPublicUrl()` of `getSeoUrl()`; `addSitemapTask()` writes the absolute
  `Sitemap: <homeurl>/sitemap.xml` into `public/robots.txt`, replacing a line naming `sitemap.xml` or adding one.

The server sends every path that is neither a file nor a folder to `index.php`: `public/.htaccess`, and
`try_files` of `nginx.conf.example`. The tests are `RouteRegistryTest`, `RouteWriterTest`, `RouteParseTest`,
`RouteBuildTest` and `RouteMoveTest` over `tests/Support/registry_probe.php` and `route_probe.php`; the 6.2 rehearsal
of `UpdateSiteTest` holds the registry a migration fills.

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

The name of a module is the constant its row names, resolved by `getModuleLabel()`: when the common language lacks
it, the language of the module itself is loaded for the current context (`modules/<name>/lang` on the site,
`modules/<name>/admin/lang` in the panel), so a module that is not shipped carries its name in both. A module declares
its scheduler jobs in `modules/<name>/scheduler.php` — the defaults of the row and the handler as a closure under
`run`; `getSchedulerJob()` and `addSchedulerSystemJob()` take them after the jobs of the core, and a job of an inactive
module answers disabled. `master` declares its two jobs this way.

Modules are still PHP include targets, not isolated service packages. A module
can use shared globals such as `$conf`, `$db`, `$tpl`, `$prs`, `$user`, and
`$admin` when it runs inside the normal bootstrap.

## Block Boundary

Block files live under:
- `blocks/`

Block definitions and placement are database-driven. `setFoot()` asks
`getBlocks()` for the four zones left, right, center and down (`bpos` l, r, c, d);
file-backed blocks are included from `blocks/<file>`, and a row whose file is gone
prints nothing but to the super administrator. `isBlockShown()` reads `which` for every block,
`getBlockWhich()` of `core/admin.php` writes it, and `setBlockOrder()` there is the
one writer of `bpos` and `weight`: every zone numbered from 1. `setBlockRow()` there is the
one writer of a row for "Блоки и баннеры" and the builder alike, by the one spec of the
fields `getBlockSpec()` both forms read: the title cut, the file of `blocks/`, the room of
the text, the term capped, a feed that fails at a new address refused, and a system block
(`bkey` set) never deleted and never given another kind. A value of `which` is a
module, a Node type or `page-<number>`, a page of the site builder (`getBlockPlaces()`
lists them, `getBlockStray()` the values no page answers); a page holding a module slot
answers to the name of its module.

Free blocks (the `infly` mark in the `which` column) are excluded from zone
rendering and are output in two ways: content places a `[block=id]` tag, which
the parser renders in a raw or a trusted rendering and `filterTrustedTags()` keeps
from every writer but the super administrator, and theme templates place a
`{% freeblock id %}` tag (compiled by the template engine). Both entry points
call the same `getBlocks()` engine, apply the same visibility rules, and do
not expand nested tags inside block output.

Block rendering is part of the frontend page assembly path. Treat it as a shared
runtime boundary, not as module-local output.

## Layout Boundary

The layout is what a site says without HTML: the sections of its pages, its menus and the parts of the theme around
the content. It is one file, `config/layout.php`, read by `getConfig()` as `$conf['layout']` and edited by the site
builder of the panel; blocks stay rows of `_blocks`. The theme side of the contract — the description of a section,
its fragment, the places and the ready pages of a theme — is `docs/TEMPLATES.md`, "Layout Sections".

`Layout` (`core/classes/layout.php`) is a thin core: it reads the file, renders it, publishes it and gives it as data
(`getData()`, the form a later answer of the API takes). It knows no section, part or menu by name; every one comes
from its description, and the one type the core owns is the module slot (`Layout::SLOT`, `module`).

### The file

| Key | Holds |
|---|---|
| `v` | the version of the shape, `Layout::VERSION`; a file of another version reads as an empty layout, so a change of the shape is a PHP step of the update that moves the file of a site |
| `shipped` | true marks a delivery no site has published yet: the ready pages are offered while it holds, and the first publication clears it; nothing writes true, and the delivered file carries false |
| `next` | the counter of ids: pages, sections and menu items take their numbers from it, never given twice; a version back from the history or an imported file keeps the larger counter |
| `home`, `notfound` | the numbers of the home page and of the page 404 |
| `theme` | the values of the parts of the theme by flat keys without underscore, kept across a change of theme; a theme prints the parts it declares and passes over the others |
| `menus` | the menus by key, each a list of items: `id`, `title`, `href` (an address of the site from `/` or of `https`), `icon`, `blank`, `who` and `kids` |
| `pages` | the pages keyed by their permanent number: `title`, `addr`, `desc`, `og`, `noindex`, `who` (0 all, 1 users), `band` (the title band), `side` and `top` (the scale of `config/modules.php`, read on a page without a module slot only) and `sections` |

A section is `id`, `type`, `on`, the fields of "Показ" — `who` (the scale 0–3 of `_blocks.view`), `lang`, `from`,
`till`, `anchor`, `phone`, `desk` — and the values of its type by the keys of its description; the slot carries
`module`, empty for the home module of the settings. The delivered file is the layout of slaed.net as its stand
publishes it: every menu of aurora filled, every key of the parts of aurora, a home page with its slot, a page 404 that is
not indexed. A new installation opens with it; an update writes the whole file only to a site that lacks it and
otherwise adds just the keys the site does not have (`Update::getConfigMerge()`), its pages and menus staying the
site's. `LayoutTest` holds the shape of the delivery, its counter and the addresses of its menus.

### Rendering

- **The page around a module.** `public/index.php` builds `$layout` before it routes and answers the number `$lkey`
  of the page around the request: the page `page-<n>` the request names, the page whose switched-on slot holds the
  module of a front view (`getModuleKey()`, never the home page or the page 404), or on the start page the home page.
  `setFoot()` then wraps the module output in the sections (`getPageHtml()`) and takes the columns of the page
  (`getPageColumns()`) unless the page holds the slot of a module; a page without a slot prints the module output after its
  sections.
- **A page of sections alone** — no slot, its own address — sets `$conf['name']` to `page-<n>` and passes its
  title, description, picture, `robots` and its path as `route` to `setHead()`. The name `page-<n>` answers 301 to
  that path; the home page, the page 404, a page holding a slot and a page without a row of `_routes` answer 404 under
  it. A page for users only answers a guest with the 403 of a closed module and its link to the login.
- **A section.** `isSectionShown()` reads `on`, the dates, the audience and the language of a multilingual site;
  `Layout::isViewShown()` is the one reading of the scale 0–3, shared by blocks, sections and menu items. Every
  section stands in `fragments/section.html` of the theme; a section whose fragment prints nothing loses its frame and
  its number.
- **The chrome.** `setHead()` gives every page of the site `parts` (`getThemeData()`) and `menus` (`getMenu()`, as
  deep as the theme declares); `getThemeHeadVars()` of aurora prints the title band of a page with `band`.
- **The page 404.** `setError(404)` of `core/security.php` prints the page `notfound` while it has a section the
  visitor sees, with the code 404 kept; an empty page, an error inside it, the panel, an endpoint `go=` and an htmx
  request get the standard page of the error.
- **The sitemap.** `addSitemapTask()` lists the pages with an address of their own that a guest sees and that are not
  `noindex`.
- **Feeds.** `getNodeFeed()` of `core/system.php` is the one engine of the block `node.php` and of the section
  "Свежие материалы" of the module Node.

### The draft and the publication

The panel module `admin/modules/layout.php` is the editor, for the super administrator only, every write a POST
through `checkAdminPost('layout')`. The screen covers the window and shows the page in a frame by the link of the
draft; its help is `admin/info/layout/ru.md`.

- **Storage.** `LAYOUT_DIR` (`storage/layout/`, ignored by git, made by the first write, never in a package) holds
  `draft.json` (`Layout::DRAFT`, JSON so no include ever runs it), the publications `pub-<n>.json` of the history
  (`Layout::HISTORY`, ten, each with its author, its note and the blocks, the name and the logo as published) and
  `mine.json`, the own sections shared by the administrators. The draft keeps beside the layout the changed, new and
  deleted rows of `_blocks` with each touched row as it began (`blocks`, `bwas`), `sitename` and `site_logo` where
  they differ from `config/global.php` (`conf`), the steps of Undo and Redo, the counter `link` of the draft links and
  the revision, the time and the editor of its last write. One draft serves every administrator: a write that saw
  another revision is refused and named.
- **The link of the draft.** `index.php?name=page-<n>&draft=<term>.<hmac>` signed by `Layout::getDraftToken()` with
  `getSecret('layout-draft')` over the page, the term and the counter `link`; a new link raises the counter and so
  takes the earlier ones back. The page renders from the draft with `noindex`, no canonical address, no redirect and the
  mark "Черновик"; a wrong or expired link answers 403. `guest=1` reads it as a visitor, `edit=1` of a super
  administrator turns on the edit mode, `mod=<module>` shows a module page the draft has not written yet.
- **The edit mode.** `Layout::setBuildMode()` hands the fragments `is_build` and the picks of their parts;
  `setFoot()` prints `fragments/builder.html` of the theme and no floating menu, and `doScript()` loads
  `plugins/system/builder.js` in place of `dock.js`. The page and the screen talk by `postMessage`, the protocol
  written at the head of `builder.js`. Nothing of it reaches a visitor.
- **The publication.** `Layout::setPublish()` runs inside the Closure form of `setConfigFile()`, where `layout` is
  one of the shared areas (`docs/NODE.md`, "Configuration protocol"): the published file is checked against the checksum the
  screen opened (`getLayoutHash()`), then the changed rows of `_blocks` (`setLayoutRows()` of the module, each against
  its row in `bwas` and written by `setBlockRow()`, whose refusal stops the publication by the name of the block), the addresses through `Route` — a new or renamed page by `setRouteName()`, a deleted one by
  `deleteRoute()` — and the file are written in one transaction. The proof of an uncertain commit is a witness row:
  the first touched row of `_blocks` or of `_routes` whose fingerprint (`getWitnessHash()`) changed, which
  `setConfigRestore()` reads again `FOR UPDATE`. A publication that changes no row opens no transaction. `sitename` and
  `site_logo` follow in a second call of the string form, as "Конфигурации" writes them.
- **Export and import** carry the layout alone, as JSON: an import checks every value by the descriptions of the
  theme on the server and refuses the file on an unknown type, a refused value or a twin id; the blocks, the name and
  the logo stay with the site.
- **Uploads.** The pictures of the sections are the place `layout.attach`, the public folder `uploads/layout/`, with
  the row `layout` of `config/uploads.php` (the types of `content`, no uploads of users and guests).

No table holds the layout and no cache of pages stands in front of it. A publication runs on its click only, never by
a schedule: the dates of "Показ" already hold a section back until its day. A page holds one module at most, and a
section is a file of a theme or of a module, never installed by upload; the builder edits data and never a file of a
theme. An update adds the keys a site lacks to `theme` and takes `pages` and `menus` whole (`docs/UPDATE.md`, "Settings,
Languages and Themes"). The tests are `LayoutTest` (rendering, the delivered file from the index of git, the marks of
the fragments), `LayoutDraftTest` (the screen over `tests/Support/layout_probe.php`), `LayoutPublishTest` (the
publication over `route_probe.php publish`), `BlocksTest` and the route tests of "Address Boundary".

## Template Boundary

The active template runtime is `Template` in:
- `core/classes/template.php`

Current theme directories:
- `templates/admin`
- `templates/aurora`

Page output should be finalized through `setHead()` and `setFoot()` unless the
route is a deliberate helper endpoint that returns JSON, upload output, or
another non-page response.

Detailed template contracts live in:
- `docs/TEMPLATES.md`

## Parser Boundary

The active content parser is `Parser` in:
- `core/classes/parser.php`

Use the parser for Markdown and SLAED tag content rendering. Security text filtering
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
- the constructor sets the collation of `config/db.php`, the time zone of PHP
  and one strict `sql_mode` with `ONLY_FULL_GROUP_BY` on every connection: the
  site, the panel, `setup.php`, `update.php`, the tools and the probes that load
  the code
- `getSqlTwin()` names the unique key a refused duplicate hit

New and refactored SQL should use prepared statements and named placeholders
where practical.

The schema is `storage/update/sql/table.sql`; its column domains, the time
rule, the collation and the keys are in `docs/NODE.md`, "Schema conventions",
held by `tests/SchemaConventionTest.php`.

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
- provides CSRF helpers such as `getSiteToken()`, `checkSiteToken()` and `checkSitePost()`, the POST and body token of a write of the site in the scope of its module
- provides admin/user access helpers

What the web server may reach, the self-check and the journals are under
"Private Data Boundary"; how the journals are written and read, the record of
the panel's actions and the login table under "Journal Boundary".

**The way back after a login** is one field for the panel, the site and OAuth.
Every login form carries a hidden `back` from `getBackUrl()` (`core/system.php`):
the posted or linked `back`, else the address of the page itself on a plain GET;
a POST without the field and an htmx fragment have none. A link that invites a
guest to log in is `getLoginUrl()`, the login page with that `back`, and the
OAuth buttons pass it to `oauth_init`, whose row in `_oauth_temp` holds it until
the callback. `filterBackUrl()` accepts only a path under `HOME_PATH` without
`//`, a backslash, a space or a control byte, so no form or link can send the
visitor to another host; a login with no valid `back` lands where it always did.
The panel's end of session reloads the page it was on, so its login form already
stands at the section the administrator returns to. `refer=1` with the Referer
is left to the switches that return to the page they were pressed on.

Security can exit early for blocked or invalid requests before the normal
`setHead()` / `setFoot()` page lifecycle.

Security behavior is part of the runtime contract. Do not optimize or bypass it
without focused regression tests.

## Cache And Scheduler Boundary

Cache runtime:
- `core/classes/cache.php`

Scheduler integration:
- HTTP runner through frontend helper branch `go = 3`
- scheduler configuration under `config/scheduler.php`
- scheduler admin module under `admin/modules/scheduler.php`
- pseudo-trigger injection from `setHead()` when scheduler config, heartbeat,
  due-job, and cooldown checks allow it
- scheduler state under `storage/logs/scheduler/`, its locks under `storage/cache/locks/scheduler/`

The confirmed current runner is HTTP/admin-triggered. Heavy jobs should stay out
of synchronous page-render side effects.

Server metrics:
- `core/monitor.php` holds the CPU, memory, network, disk, uptime, log-tail and
  security counters; `admin/modules/monitor.php` keeps only its page, charts and
  template variables
- `getMonitorSample()` is the one writer of `storage/logs/monitor.json`: called
  with `$fresh`, it retakes a sample older than ten seconds under the non-blocking
  lock `storage/cache/locks/scheduler/monitor-sample.lock`, and a request that does
  not get the lock reads the file as it stands, so the OS probes (PowerShell on
  Windows) never run twice at once. The sample holds the CPU share and details,
  memory, disk, network and disk-io rates with their histories and the time of each
  point (`sys_hist_time`), `uptime`, `cores`, `soft`, the uploads size retaken once
  an hour (`uploads`, `uploads_at`) and `sampled_at`
- on Windows every figure comes from one PowerShell run per sample,
  `getWindowsProbe()`, started by `proc_open()` and killed after ten seconds; a
  run that gave nothing writes no sample, so the old figures stay and age instead
  of turning into zeros
- three readers share it: the cockpit poll (`getCockpitData()`) and the poll `op=live`
  of the monitor page pass `$fresh`; the monitor page itself and the presentation
  module only read. Every request carrying `X-SL-Poll` releases the session in
  `core/security.php` right after the idle check, so no poll, the sampling one
  included, holds another request of the same visitor; a poll handler therefore
  never writes the session
- the load of a page is part of the statistic: `updateStatsTrack()`, the deferred
  task `setHead()` queues while the statistic is on, adds the generation (ms), the
  queries and the peak memory (KB) of the page to three hour fields of
  `statistic.log` (17, 18, 19, twenty-four sums each, beside the hits by hour of
  field 13), so they roll into `days.log` and the month archives with the day.
  The statistic page shows the average page per day and per hour; the strip of the
  cockpit is the average page of the running hour (`getLoadAverage()`), of the hour
  before while this one has none. JSON polls never reach `setHead()` and never count
- the `monitor` scheduler job (`* * * * *`, priority 9, the lowest, so it never
  takes a tick from `maildrain` or `newsletter`) calls `addMonitorSample()`, which
  takes the same sample so the history goes on while no panel polls, and fails when
  the sample is older than a minute: a stuck or failing writer shows in its state
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
`account` keeps the attachments of private messages under `$conf['uploads']['account']`,
those of its signature, own block and profile comments under `$conf['uploads']['profile']`
(moderated as `account`), and the avatar under `$conf['users']`. These are two different things and one rule cannot hold
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
| `maxquota` | config | `aquota` |
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

## Image Fit Boundary

An image over the bounds of its upload rule (`maxwidth`, `maxheight`) is scaled
down to them when it is uploaded, not refused. `Upload`
(`core/classes/upload.php`) owns that and nothing outside it scales an upload.
There is one behaviour for every rule and for the avatar, whose bounds are
`awidth` and `aheight` of `$conf['users']`: an avatar over them is fitted in
proportion, without a crop. The rule keeps its twelve keys.

**The plan is made from the header, and the pixels are decoded once.**
`getImageBounds()` reads the size (`getImageInfo()`), the marks
(`getImageMarks()`), the fit (`getFitSize()`) and the memory the decode needs
(`getDecodeBytes()`), and answers every refusal before a decoder allocates. An
image inside the bounds is decoded by `getDecodeError()` as the proof of its
pixels and stored byte for byte, with its metadata. An image over them leaves
as a plan under the key `fit`, and `addImageFit()` scales the partial after the
transfer in `addUploadedFile()` and after the fetch in `addRemoteFile()`: one
decode, `imagecopyresampled()`, the turn, the writer of the same type.

**What a stored file is.**

- The bounds are compared with the size the image is shown at, the header size
  with its orientation applied. A 4000×3000 photo turned by EXIF into a rule of
  4096×3200 is scaled to 2400×3200 and stored upright. The fit keeps the
  proportion; a bound of zero is no bound.
- The type is kept: the extension and the MIME type of a stored file never
  differ from the transferred one. `QUALITY` writes JPEG and WebP at 85 and
  AVIF at 60, because the scales of the codecs differ; PNG is written without
  loss, GIF through the 256-colour palette GD builds.
- Only pixels are written, so EXIF with its GPS position leaves a scaled file.
  A scaled JPEG keeps one thing of its metadata, the colour profile: without it
  the pixels of a wide-gamut photo are read as sRGB. `getJpegProfile()` takes
  the ICC segments before the scan, whole and in their order, and
  `addImageFit()` writes them behind the JFIF segment of the scaled file. A
  profile is carried only when it is an RGB one of a picture of three
  components, complete, and no more than 64 KB; any other stays behind. PNG,
  WebP and AVIF carry none.
- The result of a scaled file carries the stored width and height; a file
  inside the bounds keeps the width and height of its header.
- `maxbytes` is the limit of the transfer. `checkUploadInput()` and
  `addRemoteFetch()` compare it with the transferred file before any of this,
  so a 5 MB photo into a rule of 1 MB is refused with `size`. A scaled file
  heavier than `maxbytes` is stored, and `maxquota` counts the scaled file.

**What is still refused with `dimensions`.**

- An animation over the bounds — GIF, WebP, APNG, an AVIF sequence — because GD
  would keep one frame. `checkGifMotion()` walks the block chain of a GIF,
  `getPngMarks()` meets `acTL`, the `VP8X` flag marks a WebP and the brand
  `avis` an AVIF.
- An image past the memory budget, inside the bounds as well as over them.
- A type this build cannot decode or cannot write, over the bounds:
  `decoder_missing` and `encoder_missing` of `addCapsNote()`. AVIF without
  `imageavif()` is the case.

**The orientation is read by a parser of the class**, and the extension `exif`
is not required: the product is distributed. `getJpegTurn()` reads the Exif
segment before the scan, `getPngMarks()` the `eXIf` chunk before `IDAT`,
`getAvifMarks()` the `irot` and `imir` of the primary item; `TURNS` maps the
eight EXIF values onto `imageflip()` and `imagerotate()`. A WebP is never
turned. The marks are read only when the header size or its quarter turn
exceeds a bound; a PNG is opened always, for its colour type.

**The decode runs inside a budget derived from `memory_limit`.**
`getMemoryRoom()` is the seam: `getMemoryLimitBytes(true)` less the memory in
use. `getDecodeBytes()` budgets phase by phase — the canvas of
`getCanvasBytes()` with what its reader holds, the canvas beside the fit, the
fit beside its turn, the fit beside the buffer of its writer — and `SPARE`
adds 4 MB. A JPEG that carries a profile is written through the output buffer,
so that the segments are placed without the file being read back, and is
budgeted 4 bytes a pixel of the fit for it.

**What stays outside.** `checkFieldFile()` of
`public/plugins/system/filemanager.js` checks extension and weight at the pick
and no bounds (`docs/WINDOW.md`), and the limit sentence of the window
(`getFileManagerText()`) names the formats and the weight alone. `getImageThumb()` (`core/system.php`) builds
a thumbnail by width, in the format of the source, with no orientation. An
image already stored is not scaled, and nothing is scaled in the browser before
the transfer. The demo (`DEMO_FAIL.dimensions` of `public/demo/assets/demo.js`)
keeps its refusal on the bounds at the pick; the difference from the product is
known and stays.

### Measured

Home stand, 2026-11-10. Weight, time and memory: console PHP 8.4 and 8.5 with
bundled GD.

Weight and time:

- A 4032×3024 JPEG decoded and resampled to 1920×1440: 564 ms, peak 58.6 MB
  over the base, which is the two truecolor canvases.
- The 1920×1440 result written: JPEG 85 — 336 KB in 42 ms, WebP 85 — 197 KB in
  209 ms, AVIF 85 — 310 KB in 307 ms, AVIF 60 — 165 KB in 281 ms. A 640×439
  result: JPEG 85 — 58 KB, WebP 85 — 41 KB, AVIF 85 — 57 KB, AVIF 60 — 32 KB.
- A scaled file can weigh more than the one sent: a 5000×5000 palette PNG of
  hairlines, 8874 bytes, is stored as 4096×4096 at 78268 bytes; a 6000×3000
  JPEG of quality 20, 407 KB, as 4096×2048 at 459 KB.
- Through the route of the editor window on the stand (PHP 8.5): a 5000×60 PNG
  is stored as 4096×49, a 6000×3000 JPEG as 4096×2048, a 4500×4500 WebP as
  4096×4096, a 1025×3000 and a 300×200 PNG byte for byte.

Orientation, Chromium and Firefox of the repository Playwright; GD applies none
of it on decode:

- JPEG: the Exif segment before the scan turns the picture in both browsers,
  all eight values.
- PNG: an `eXIf` chunk before `IDAT` turns it in both, all eight values; behind
  `IDAT` neither browser reads it.
- WebP: an `EXIF` chunk turns it in neither browser, so the class reads none.
- AVIF: `irot` and `imir` of the primary item turn it in both; `imir` axis 0
  exchanges top and bottom, axis 1 left and right; the turn is applied before
  the mirror whatever order the file lists them in (Chromium; Firefox refuses a
  file that lists the mirror first).
- Every one of the 34 fixtures stored by the class spells the corners the
  browsers drew.

The colour profile, two photos of an iPhone with Display P3 (5712×4284 of
flowers, 4032×3024 of a street) scaled to 1920×1440, painted by Chromium of the
repository Playwright:

- Without the profile the scaled file is drawn duller: 29 % of the pixels of
  the flowers and 1.3 % of the street differ by more than 10 levels of 255 from
  the same file with the profile, 8.9 and 2.3 levels on average.
- With the profile, 554 bytes, the scaled file is drawn as its source.
- The headless Firefox of the rig paints all three alike; a Firefox with a
  window was not measured.
- Through the catalogue of the panel on the stand (PHP 8.5): the 5712×4284
  photo, 2.8 MB, is stored as 4096×3072 at 934 KB with the segment of its
  profile and without its Exif.

Memory, each case in a process of its own:

- A truecolor canvas is not 4 bytes a pixel: GD allocates a row at a time and
  PHP rounds a row past 3072 bytes to whole pages of 4096, so 1024×8000 takes
  30 MB and 1025×8000 takes 62 MB (7.9 bytes a pixel).
- A PNG decode holds the rows of libpng beside the canvas: 7.3 bytes a pixel
  for RGB, 8.4 for RGBA and for grey with alpha, 2.1 for a palette or grey, 16
  bits as 8.
- A WebP or AVIF reader holds the file itself, 1.1 to 1.2 times its weight; a
  JPEG reader holds nothing.
- A writer holds beside the canvas: PNG as much again, WebP 4 bytes a pixel,
  GIF one byte a pixel, JPEG and AVIF nothing. A JPEG written through the
  output buffer holds its file there: noise at quality 85 weighs 0.76 bytes a
  pixel and its write peaks at 2.1 bytes a pixel beside the canvas.
- Under a real `memory_limit`, 108 cases on 8.4 and 27 on 8.5 (ten kinds of
  file, four sizes, inside the bounds and two fits): at the limit the class
  starts to accept a file the request survives, below it the class answers
  `dimensions`, and with the budget lifted the request survives 2 to 8 MB
  lower. A limit of 5 to 9 MB kills PHP while it loads `fileinfo`, before the
  class is reached.
- What `getDecodeBytes()` asks with `SPARE`: a 4032×3024 JPEG inside the bounds
  51 MB and fitted to 1920×1440 63 MB; a 4032×3024 RGBA PNG 98 MB; a 5712×4284
  JPEG fitted to 4096×3072 153 MB; an 8064×6048 JPEG fitted to 4096×3072
  241 MB.
- The readers of the marks answered 44000 mutated and truncated files without a
  warning.
- A GIF cut into sub-blocks of one byte, 25 MB of them, over the bounds: the
  walk of `checkGifMotion()` takes 0.56 s; inside the bounds no walk runs and
  the call takes 0.03 s.

## File Delivery Boundary

Every uploaded file has one owner, and the stored text that names the file
decides who receives it. The class `FileAccess` (`core/classes/access.php`) owns
that decision and nothing else. Its factory `getFileService()` in
`core/system.php` builds one adapter per owner as two closures, the way
`getRatingService()` builds `Rating`:

- **folder** — the folder of the owner relative to `UPLOADS_DIR`;
- **grant** — whether the reader receives this name of this target, answered
  from stored rows only, never from the request.

The owners are the closed set `FileAccess::OWNERS`:

| Owner | Folder | Target | Who receives a name |
|---|---|---|---|
| `node` | `uploads/node/<type>/` | material | the reader of the material whose text or published comment carries it (`NodeService::getNodeFile()`, `docs/NODE.md`) |
| `forum` | `uploads/forum/` | post | a reader of the category while the post and its topic are published, a moderator of the forum every post (`checkForumFile()`) |
| `privat` | `uploads/account/` | private message | a side that still holds the message, a moderator of `account` (`checkPrivatFile()`) |
| `comment` | `uploads/voting/`, `uploads/profile/` | comment | a reader of its poll or profile while the comment is published, a moderator of the module (`checkCommentFile()`); a comment of a Node material takes the route of its material |
| `profile` | `uploads/profile/` | account | the signature to whoever may open the profile, the own block to its owner alone (`checkProfileFile()`) |
| `public` | `uploads/all/`, `uploads/avatars/`, `uploads/layout/`, `uploads/presentation/` | none | anyone, at the direct address |

`getUploadOwner()` names the owner of an upload module, and `getCommentPlace()`
the folder the comment form of a module uploads into: a comment on a profile
into `uploads/profile/`, any other into the folder of its module.

**The route.** One endpoint serves every closed owner:
`index.php?go=file&own=<owner>&id=<target>&key=<name>`, with an optional
`thumb=1`. An owner of `FileAccess::PREVIEW` also answers
`go=file&own=<owner>&name=<module>&key=<name>&preview=1`, the visitor's own
upload before its text is saved. `setFileRoute()` reads the exact query and
`FileAccess::getFilePath()` adds the checks every owner shares: a bare name of
a type `Upload::getSupportedTypes()` knows, the file inside the folder of its
owner, a thumb only when it exists. Every refusal is the same 404, and every
byte leaves through `getFileStream()`.

**What a text carries is served.** The grant asks whether the stored text
carries the name, whatever its form; a random part of the name protects
nothing. The upload rule of a folder decides what may be uploaded, previewed
and newly bound, never whether a stored name is served, so narrowing a rule
cuts off no file uploaded under it.

**Addresses.** The parser takes a file context `['<owner>', <target>]` and asks
`FileAccess::getFileUrl()` for the address of every `[attach]`
(`docs/PARSER.md`): a closed owner answers `go=file`, a public one the direct
address `getUploadUrl()` builds. The parser cache never stores a text that
carries an `[attach]`, so the address is built on every render.

**Writers.** A writer binds a new `[attach]` name only when the file is the
writer's own upload of the folder, the writer moderates the folder, or the
same target already serves the name — a quote within a forum topic, a reply or
a forward of a private message, a published comment of the same target. The
checks are `NodeService::checkNodeFiles()` for a material and, over the shared
`checkUploadNames()`, `checkForumNames()`, `checkPrivatNames()`,
`checkProfileNames()` and `Comment::checkAttachNames()`, whose refusal names
the file with `_FILE_FOREIGN`; the preview of these owners is
`checkUploadPreview()`.

**Closing a folder.** A folder is public when it is on `getUploadPublic()` in
`core/stream.php`. The light path of `index.php` serves a file of such a folder
before the core boots and answers 410 for every other folder and every missing
file, `uploads/archive/` included. Closing an owner is one entry off that list
and its adapter; no folder moves, and no folder carries a guard file. A new
owner takes a value of `FileAccess::OWNERS`, an adapter in `getFileService()`,
its upload module in `getUploadOwner()`, the file context its renderer passes,
the check of its writers and its cases in `FileAccessTest`.

**Unused files.** The file browser of the uploads screen offers "Unused: N" at
the foot of a folder whose references it can read: a Node type, `forum`,
`account`, `profile`, `voting` and the avatar folder. `all` and `presentation`
are not covered: any text may address `all` directly, from a site message to a
mail template, and `presentation` ships its files. `getAdminFileRefs()` in
`core/admin.php` reads every stored row whatever its state with the readers of
the route — `Parser::getAttachList()` over posts, signatures, own blocks,
`Privat::getAttachBodies()` and `Comment::getAttachTexts()`,
`NodeService::getTypeFiles()` for the materials and resources of a type, and
`users.avatar` — so a draft, an unpublished post and a message one side
deleted keep their files. `unused=1` keeps the files nothing names; a thumb
follows its original, and a file younger than a day is left out because its
text may not be saved yet. Nothing is deleted on its own: the marking and the
deletion of the browser remove what the filter shows.

## Storage Boundary

Runtime-generated files are stored under:

- `storage/cache/`
- `storage/cache/data/`
- `storage/cache/templates/`
- `storage/cache/locks/`
- `storage/captcha/`
- `storage/counter/`
- `storage/geoip/`
- `storage/logs/`
- `storage/logs/scheduler/`
- `storage/sitemap/`
- `storage/backup/`
- `storage/layout/`, the draft, the history and the own sections of the site builder (`LAYOUT_DIR`, see "Layout Boundary")

`storage/update/sql/` is source, not runtime data: the schema `table.sql`, the
seed `insert.sql` and the 6.2 update `table_update6_3.sql`. `storage/` lies outside
the document root `public/`, so the web never reaches it.

Uploads are stored under:
- `uploads/`, at the project level and outside the document root. A file of a public
  folder (`getUploadPublic()` in `core/stream.php`) is served at `uploads/<folder>/<name>`
  by the light path of `index.php` before the core boots; any other folder only by the
  route of its owner, and the light path answers its address with 410. `getUploadUrl()` answers the address, `getFileStream()` sends every byte.

Do not treat generated storage contents as source files. Documentation and tests
should describe the directories and contracts, not specific generated artifacts.

## Private Data Boundary

The private part of an installation — the code, `config/`, `storage/` and
`uploads/` — is kept from the web by the layout of the tree, not by rules a
server may ignore, and the installation checks that it is.

**The tree.** The document root is `public/`. It holds the entries
`index.php`, `admin.php`, `setup.php` and, in this repository, `update.php`,
then `.htaccess`, `favicon.ico`, `robots.txt`, `error.html`, the sitemap files,
`templates/`, `plugins/` and `demo/`. Everything else stays at the
project level. Every entry defines `BASE_DIR` (the folder above its own) and
`PUBLIC_DIR` (its own folder) — all but `admin.php` only when a test or a tool
has not defined them first — so the root may carry any name, `public_html/`
included. A filesystem path into the
public part reads `PUBLIC_DIR`, a path into the project `BASE_DIR` or one of the
runtime constants (`CONFIG_DIR`, `CACHE_DIR`, `LOGS_DIR`, `UPLOADS_DIR`, …);
no path relies on the working directory, which is `public/` on the web and
something else under the tests and the tools. Outside `public/` no folder
carries a guard `.htaccess` or `index.html`, and no writer creates one. Inside it the server closes the listing
(`Options -Indexes` of `public/.htaccess`, `autoindex off` of `nginx.conf.example`), so no folder carries a guard page
either: an `index.html` there is a real page, as `phoenix/` has. `PublicTreeTest` holds both sides.

**Two modes.** The installer records the one it found as `webroot` in
`config/global.php` (`getSetupRoot()` of `setup.php`):

- `public` — the server points its document root at `public/`; nothing outside
  it exists for the web. Recommended, and the only mode for nginx.
- `project` — the root is the whole project, and the `.htaccess` of the project
  rewrites every request into `public/`; where `mod_rewrite` is missing it
  refuses every request through `mod_authz_core`, so the site breaks rather than
  opens. Apache and LiteSpeed only. A path to a file outside `public/` lands in
  `public/`, finds no file there and gets the 404 page; one under `uploads/`
  reaches the light path below. The slash redirect of `mod_dir` is off
  (`DirectorySlash Off`), so a folder of the project — `docs/` beside the Node
  type `docs` — never answers in place of an address.

**One upload root.** `UPLOADS_DIR` is `uploads/` at the project level, the
folder of every owner. A request under `/uploads/` reaches `index.php` (`!-f` on
Apache, `location ^~ /uploads/` on nginx), whose light path in `core/stream.php`
answers it before the core boots: a file of a folder on `getUploadPublic()` with
its type from a fixed map, `nosniff`, `Content-Security-Policy: sandbox` and a
public lifetime of a day, everything else 410. The owners and their routes are
under "File Delivery Boundary".

**The server files.** `public/.htaccess` carries the rewrite to the front
controller, the refusal of `*.php` and of the theme `*.html` under
`templates/`, the refusal of every dot file or folder but `.well-known/`, the
lifetimes and the compression; the `.htaccess` of the project carries only the
rewrite of the second mode and `DirectorySlash Off`. nginx reads neither:
`nginx.conf.example` at the project level is the `server` block of the first
mode, and `NginxConfigTest` walks every file of `public/` through both and
fails when they refuse different things. The settings for shared hosting are in
README.md, "Document Root and Web Server", and in the help of the security
section.

**The self-check.** `getPrivateRoots()` names five roots: the project itself,
which proves the document root, and `storage/`, `config/`, `uploads/` and
`admin/info/`, which hold the secrets and catch an alias that opens one of them
while the root is right. Each holds an untracked marker `check.txt` with 32
random hex characters, written by the last part of `setup.php` and again by
`setPrivateMark()` whenever it is missing. `checkPrivateRoots()` asks the site
address for each marker through `getSchedulerFetch()` and judges by the body
alone, since a site can answer an error page with 200 or a file with 404:
`open` when the body carries the marker, `closed` when it does not, `unknown`
when no request could run. The system job `selfcheck` runs it every hour and
keeps the verdicts in its scheduler state; `getSelfCheckState()` reads them, the
lamp of the cockpit on the home of the panel turns red on an `open` root and yellow
on an `unknown` one or on no verdict within a day (signal `dirs`), and
`getSelfCheckAlert()` prints the same verdict, or the all-clear, in the security section. A Node type is switched on
only while the upload root is `closed` (`NodeService::checkTypeGuard()`).

**Journals.** No secret reaches `storage/logs/`. `Logger` masks the value of
every key matching `pass`, `pwd`, `secret`, `token`, `key` or `code` in the body
and the query string, and writes the names of cookies and session keys, never
their values; the journals of the site's own events carry no body at all
("Journal Boundary"). The request journal (the `log` switch of
`config/security.php`, off by default) is its channel `request`; a login is
recorded by `addLoginLog()` with who tried, under which login, from where and
when, never the attempted password. A file is named after what it holds:
`error_*.log` is what the system could not do, `<meaning>.log` what happened,
and no name carries a `log_` prefix.

| File | Carries | Writer |
|---|---|---|
| `error_php.log`, `error_sql.log`, `error_site.log` | failures | `Logger` |
| `error_file.log` | file operations at `error` and `critical` | `Logger`, channel `file` |
| `file.log` | file operations below `error`, such as a remote address the policy refused | `Logger`, channel `file` |
| `hack.log`, `warn.log` | recognised attacks, refused requests | `Logger` |
| `request.log` | the request journal | `Logger`, channel `request` |
| `admin.log` | actions and logins of the panel | `Logger`, channel `admin` |
| `user.log` | logins of the site | `Logger`, channel `user` |
| `oauth.log` | OAuth protocol events, no token, code or claim | `Logger`, channel `oauth` |
| `database.log` | runs of the database tool of the panel | `Logger`, channel `database` |
| `filescan.log` | changes of the file tree | `Logger`, channel `filescan` |
| `filescan_tree.log` | the snapshot of the tree the next scan compares with | job `filescan` |

A line goes to one file only, by its level. `Logger` moves a full journal
through `addCompress()` into an archive `<name>_<date>.log.<zip|gz|bz2>`, or
`.log.bak` without a compressor; `filescan_tree.log` is rewritten whole by each
scan. Beside the
journals `storage/logs/` holds only state as `.json` (`scheduler/<job>.json`,
`scheduler/heartbeat.json`, `scheduler/trigger.json`, `scheduler/mailrate.json`, `monitor.json`,
`filescan.json`); the locks
live in `storage/cache/locks/`, which the cache sweeps never empty. The labels
of the logs section come from one map in `admin/modules/logs.php`, and
`JournalNameTest` holds the files, the channels and the map together.

## Journal Boundary

Everything the site journals has one format, one writer and one reader per
store. The files are listed under "Private Data Boundary", "Journals"; this
section is how they are written and read.

**One format.** `Logger` (`core/classes/logger.php`) writes every journal of
`storage/logs/` but the snapshot `filescan_tree.log`: one JSON line with `ts`,
`level`, `chan`, `msg`, `fingerprint`, the request (`ip`, `method`, `url`,
`referer`, `ua`), the memory and the fields of the caller. The channels of
failures, attacks, refusals, file operations and the request journal (`php`,
`sql`, `site`, `file`, `hack`, `warn`, `request`) add the request body
(`query`, `post`, `cookie_keys`, `session_keys`), masked as above, because it
serves debugging. The channels of
the site's own events, listed in `Logger::BARECHANS` — `admin`, `user`,
`oauth`, `database` and `filescan` — leave it out: for an action of the panel
it would be the text of a block, a letter or a whole configuration, for the
database tool the whole dump. The channel list of `getChan()` is closed, every
channel has its method (`addPhp()`, `addSql()`, `addFile()`, `addSite()`,
`addWarn()`, `addHack()`, `addRequest()`, `addAdmin()`, `addUser()`,
`addOauth()`, `addDatabase()`, `addFilescan()`), and `addEntry()` is protected,
so no caller names a channel by string and no module keeps file handling of its
own. Levels are `debug`, `info`, `notice`, `warning`, `error`, `critical`.

**One writer and one reader per store.** Nothing else writes or reads a
journal; a new function here is an owner's decision.

| Store | Writes | Reads |
|---|---|---|
| the files of `storage/logs/` | `Logger::add<Channel>(string $levl, string $mesg, array $ctx = []): bool` | `Logger::getEntries(string $chan, array $filt, int $page, int $num): array` |
| the table `_login_log` | `addLoginLog(int $uid, bool $adm, string $res, string $login, string $way = 'login'): void`, `core/system.php` | `getLoginLog(array $filt, int $page, int $num): array`, `core/system.php` |
| an action of the panel | `setAdminLog(array $data): void` notes it, `addAdminLog(): bool` writes it, `core/security.php` | `Logger::getEntries('admin', …)` |

**The actions of the panel.** They are recorded at one point: the token check
`checkAdminPost()` stands in front of every change an administrator makes, so
every module, one written later included, is covered without a line of its
own. A passed check calls `setAdminLog()` with its scope, which opens one
record per request — a second check changes nothing — and registers
`addAdminLog()` for the end of the request. `setRedirect()` gives an open
record its result from `$warn`; a request without an open record is untouched.
`addAdminLog()` writes the line when the request ends, so a handler that stops
without a redirect is recorded too, and a failed write never breaks the action.
A record opens only where `ADMIN_FILE` is defined: the switches of the site
that share `checkAdminPost()` — the colour mode, the view of a list, the
language — write nothing. A refused token is no event: it changed nothing, and
a forged request reaches `hack.log` through the input filter. The
drag-and-drop order of categories and blocks (`updateAdminCategoryOrder()`,
`updateAdminBlockOrder()` in `core/admin.php`) checks through
`checkAdminPost()` as well. The switch `log_a` of `config/security.php` turns
the lines on and off. A line is `info`, its message `<scope> <op>`, with:

| Field | Meaning |
|---|---|
| `event` | `action`, or `login` for a login of the panel |
| `aid`, `aname` | the administrator and the name at the time |
| `modul` | the scope of the token |
| `op` | the operation of the request |
| `target` | the first id of `id`, `bid`, `mid`, `cid`, `nid`, `uid`, `aid` or of the list `id[]`, 0 for none |
| `num` | how many ids a bulk action named |
| `result` | `done`, `refused`, or `open` for a form shown again or a request ended without an answer |

The record keeps neither the values before and after a change nor the title of
the object: both would need a call in every handler. Changes made outside the
panel — the quick edit of the site, the connector under `/api/`, the jobs of
the scheduler, `update.php`, `setup.php` — are not recorded here; the connector
keeps its own audit table (the connector plan).

**Logins.** Every login, by password or OAuth, of the site or the panel, is
written by `addLoginLog()` alone: one row of `_login_log` always, and one event
in `admin.log` or `user.log` while `log_a` or `log_u` is on — `info` "Login
accepted" or `warning` "Login refused" with `event`, `result`, `way`, `uid`,
`login` and the administrator or user already signed in. The rows do not follow
the switches, because the profile shows them and the release ships `log_u` off.
`oauth.log` keeps the events of the protocol only (the start, the errors of a
provider, link, unlink, a created account), never a second record of the login.
The failure counter of the captcha (`Captcha::registerLoginFailure()`) is a
short window per address that decides when the captcha shows, not a journal.

`_login_log` lies on the column domains of the schema:

| Column | Declaration | Meaning |
|---|---|---|
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` | a log that may outgrow `INT` |
| `uid` | `INT UNSIGNED NOT NULL DEFAULT 0` | the account, 0 for a name that matches none |
| `admin` | `BOOLEAN NOT NULL DEFAULT 0` | a login of the panel |
| `result` | `VARCHAR(10)` ascii | `ok`, `password`, `blocked` |
| `way` | `VARCHAR(10)` ascii, default `login` | `login` or `oauth` |
| `ip` | `VARCHAR(45)` ascii | where from |
| `agent` | `VARCHAR(255)` | the browser, cut to the column by `filterCutText()` |
| `time` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` | when |

Keys: `PRIMARY (id)`, `uid_time (admin, uid, time, id)` for the profile and the
filter by account, `time (time)` for the panel, the monitor and the cleanup. No
unique key over `(uid, time)` and no `INSERT IGNORE`, or two attempts within
one second would keep one. `blocked` is a refusal by a guard before the
password counts — the captcha with its limit, or the stale token of the form of
the site; `password` is every other refusal. The panel looks the name up even
when the captcha refused, so a blocked attempt on a real administrator keeps
its id; a name that matches no account is a row with `uid` 0, seen in the
panel and in no profile. `getFailedLoginCountHours()` of `core/monitor.php`
counts the refused rows of the panel through `getLoginLog()`; the daily system
job `cachegc` deletes rows older than 90 days, a thousand per statement. The
install schema creates the table and the 6.2 update creates it with
`CREATE TABLE IF NOT EXISTS`, nothing carried over.

**Reading.** A user reads their own logins in the profile,
`index.php?name=account&op=logins`, with the pager. The panel reads them all on
the tab "Входы" of "Журналы" (`name=logs&op=logins`), filtered by account,
panel or site, result, way and period. Every journal of `Logger` opens on
"Журналы" as a table (`op=logview&file=<name>`), read by
`Logger::getEntries()`: newest first across every file of the channel, so
`file` reads `file.log` and `error_file.log` together; the level is a
threshold ("≥ warning"), `from` and `to` are whole days, and any other key
matches the field of the line. A line that is not JSON of the channel — an old
block of a site updated from 6.2 — is skipped, and a rotated archive is not
read. `admin.log` shows time, level, who, module, action, object, result and
address and opens on its actions; its filter of events shows the logins or
both. The common operations (add, save, delete, status, order) are shown in
words, any other as its name. The other journals show time, level, message and
address; `filescan_tree.log` keeps the raw view.

**One journal.** The actions of a module are read in "Журналы" by its filter
"Модуль" (`modul=<name>`), open to an administrator for whom `isAdmin(true)`
holds. No module carries a tab or a link to the journal, or shows a block of
entries on its own screen.

`LoginLogTest` holds the table, its reader, the monitor count and the cleanup
over a disposable schema (`tests/Support/login_probe.php`); `AdminLogTest` the
file journals, the record of the panel, the view and the head of a module without a link to it over real
requests (`tests/Support/route_probe.php journal`); `JournalSecretTest` and
`JournalNameTest` that no secret reaches a journal and every file is named
after what it holds.

## Update Boundary

The updater is part of the core, not a module. The reference is `docs/UPDATE.md`; this section names only what the
runtime of every request carries.

- **The gate.** `public/index.php`, `public/admin.php` and `public/setup.php` call `getSiteGate()` of `core/gate.php`
  first, right after the folder constants, before the core and before the light path of `uploads/`, and `require` the
  file it names at the top level: while `storage/update/client/apply.lock` lies, the installed `core/rescue.php`, the executor copy
  `client/run/apply.php` under the token of the installation, or the static 503 of `public/error.html`. Then
  `checkSiteGate()` closes the site with the same 503 while `SL_VERSION` is above the last database step done, and
  `public/admin.php` goes to the light mode `UPDATE_FILE` (`admin/update.php`). Both calls stay first in every
  entry of every version.
- **The version.** `core/version.php` holds `SL_VERSION` and `SL_CODENAME`; the gate reads it without the core, and
  `core/system.php` builds `$conf['version']` from both.
- **The scheduler** starts no job while `apply.lock` lies (`addSchedulerRun()`).
- **The class `Update`** (`core/classes/update.php`) loads only where it is used — the job `updatecheck`, the
  panel ("Обновления", the banner, the warning of "Тема оформления"), `setup.php`, the light mode, the executor and the
  module `master` — never on a page view of the site, unless the pseudo-cron runs the job. Its one external request
  goes to `Update::HOST` only. Its state lies in `storage/update/client/`, which no package may hold.
- **Trust** starts from `Update::getFactoryKeys()`: the constants `Update::PRIMARY` and `Update::RECOVERY`, or
  `UPDATE_KEYS` where a test run defines it before the core; no setting names a key or a server.

## Admin Shell Boundary

The shell is what every screen of the panel shares — the options, the queues, the end of the session, the cockpit of
the home, the admin block, the floating menu, the voices and the screensaver. The markup is the theme's
(`docs/TEMPLATES.md`, "Panel Shell", "Admin List Contract", "Admin Form Contract"); this section names the state and
the requests behind it. The help of the panel is `admin/info/panel/ru.md`, opened by `admin.php?op=info`.

- **State that changes the first paint is a cookie.** `$conf['user_c'].'-<key>'` through `setCookies()`, written by op
  `option` of `admin/index.php` (`setAdminOption()`: POST with the token of scope `option`, 204, 400 for an unknown key
  or a bad value). The keys: `mode` (the colour mode), `still`, `rail`, `dock-off`, `dock-site-off` (`1` or no cookie),
  `density`, `rows`, `pins` (up to eight section keys joined by `-`), and `editor`, which lands in `_admins.editor` and
  the session instead of a cookie. `isThemeFlag($key)` of `core/system.php` reads a flag before the first byte, and the
  five layouts that open a document write `data-sl-still` on `<html>`, the panel layout also `data-sl-rail` and
  `data-sl-dock-off`; the script changes the attribute at once and does not wait for the answer. One sender,
  `setOptionSend()` of `plugins/system/dock.js`, takes the address and the token from the root of the menu
  (`data-sl-option`, `data-sl-option-token`) for the options, the rail and the view of the lists. The language keeps
  its own form, op `newlang`, and the only reload.
- **What belongs to a device is `localStorage`.** `getToggleState()` and `setToggleState()` of `slaed.js`
  (`slaed-toggle:<name>`, every access in `try`) hold the sound and its zones, the screensaver, the place, edge, axis,
  fold and growth of the floating menu (the site keeps its own place, axis and fold) and the sort of every list.
  PHP never reads them.
- **One motion switch.** Every duration of the API block of `base.css` is multiplied by `--sl-time-scale`;
  `prefers-reduced-motion: reduce` and `:root[data-sl-still]` set it to 0 and nothing else, and `isMotionStill()` of
  `slaed.js` is the one answer for scripts. `setSlideMotion()` takes an axis.
- **The queues have one source.** `getAdminQueues()` of `core/system.php` answers the queues this administrator may
  open, the fullest first — new accounts, comments waiting (super administrator only), the waiting materials of each
  Node type and the default support queue — as rows `key, mod, label, title, icon, href, num`. The bell, the block
  "Ждут внимания" (`getAdminInfo()`), the chips `sl-queue` of the menu and the cards and the queue of the floating menu
  read it. The poll is `index.php?go=5&op=getAdminQueueData` with the token, once a
  minute for every administrator, answering `{left, rows}`.
- **A poll is no action.** The limit is the key `idle` of `config/security.php`; `getAdminIdle()` of
  `core/security.php` answers the lesser of it and `session.gc_maxlifetime`, never under a minute.
  `$_SESSION[$conf['admin_c'].'-time']` is renewed by every request of the panel (`admin.php` or `go=5`) that does not
  carry `X-SL-Poll`, and `core/security.php` ends an expired session on any request of the panel and the site. Every
  timed request marks itself — the bell, the cockpit, the watch window, the count of private messages, the htmx
  `every` polls through `htmx:configRequest` of `slaed.js`. The window `#sl-session` of the panel layout counts down
  from `left`; op `keep` (`updateAdminSession()`, POST with its token, 204) renews, and at 0:00 the page goes to the
  login by a GET of its own address.
- **The cockpit reads the monitor.** The data functions of the monitor live in `core/monitor.php`, read by
  "Мониторинг" and the home alike. `getMonitorCache($key, $ttl, $make)` is the one store: a JSON file of `Cache` per
  site under the memory of the request, never APCu, which each php-cgi of a Windows pool keeps to itself.
  `getSystemSignals()` answers the sixteen signals of the lamp (`key, level, label, fact, href`, failures first, kept
  60 s), the age of a backup measured against `dump_age` of `config/security.php`; `getSelfCheckState()` feeds both the
  signal and `getSelfCheckAlert()`. The home draws the lamp and the signals with the page and never calls
  `getCockpitData()` itself — a snapshot costs seconds on Windows — so the rings come from the poll
  `go=5&op=getCockpitData` at once and then at `getLiveEvery('panel')` while the tab is seen; super administrator only.
- **Two lamps, two lists.** `getSystemSignals()` of the home checks SLAED itself, `getServerSignalList()` of "Мониторинг" the
  server and its programs: thirteen rows over the one reading `getServerFacts()`, failures first, and a reading that failed
  is neutral. The thresholds of both are the help `admin/info/monitor/ru.md`; `CockpitTest` and `MonitorSignalTest` hold them.
  Off OPcache is yellow on both. `getSupportEnd()` answers the end of support of the branches of PHP, MariaDB and MySQL and
  is refreshed with every release (`docs/UPDATE.md`); a branch outside the table is neutral. The web server shows its version
  without a verdict, as a distribution patches it under the old number. `getExtensionWeight()` sorts the extensions:
  required are `composer.json` and the calls with no way round, recommended what turns a part off, and APCu is neither, as
  no code calls it.
- **"Мониторинг" reads one sample.** `monitor()` and `live()` of `admin/modules/monitor.php` build the first paint and the
  answer of `op=live` from `getMonitorView()`, the poll every 4 s; `getMonitorGround()` draws the other windows once. The
  chart is the 30 points of `addHistory()` with their times (`sys_hist_time`), every `getLiveEvery('panel')` under a viewer and once a minute
  without one.
- **The pulse is an honest rate apart from its beat.** The figure is the pages per minute of `getStatsToday()`, the
  reckoning by hours that "Затишье" of `getCockpitTone()` uses, as a counter by the minute would write on every request of
  the site. `getServerLamp()` beats by the tone and the CPU: 72, 96 or 168 × (0.8 + CPU/100). The tone decides first, so
  "внимание" and "сбой" beat at no pages at all; with every signal passed no page in the hour is a flat line, and under 20
  a lull that beats as a norm.
- **"Статистика" reads the counter.** `statistic()` and `live()` of `admin/modules/statistic.php` build the first paint and
  the answer of `op=live` from `getStatView()`, one reading of `getStatData()`: the month, its sums, the same days a month
  before, the day in scope, the run of the rhythm, the week and the pace of `getStatPace()`. A day is `getStatRow()` over
  `COUNTER_DIR`: the archive `statistic/statistic_<month>.log`, then `days.log` and `statistic.log`, as a month stays there
  until the first hit of the next. `getStatArchive()` keeps the sums of closed months in `archive.json` until the stamp of
  a file changes, so the strip reads no file of the archive again. `updateStatsTrack()` of `core/system.php` writes the
  fields 20–23 (hosts, landing pages, names and types of robots), and the lists stand in `config/statistic.php`.
- **The robots stand in a dictionary by type.** `$conf['statistic']['bots']` holds `mask => name` under `ai`, `search`,
  `seo`, `social`, `other`, so `Update::getConfigMerge` adds the new robots of a release at the price of bringing back a
  deleted one; the hosts are lists. The source `ai` is checked first, or `gemini.google.com` would be a search. A visit
  without `Accept-Language` counts as the robot `No Accept-Language`, a rule still unproved on the figures of production.
- **The sections stand by their use, and some stay apart on purpose.** Writing is one, and the entry stays where an
  administrator looks for it, so the files of the blocks and the XSL keep their screens and write through
  `FileManager::setFileBody()`. Not merged: "Рейтинги" and "Группы" (`core/classes/rating.php` reads neither points nor
  groups); "Личные сообщения" or "Группы" and "Пользователи" (the latter is delegated, the former two for the super
  administrator alone); "Блоки" and the builder (it has no interval of RSS, no ready feed, no parameters of `node.php`, no
  code of a file); "Статистика" and "Мониторинг", "Администраторы" and "Пользователи", "Обновления" and "Мастер", "Опросы" and
  "Рейтинги" (other data, roles or rights). The reset of points stays in `pointreset()` of "Пользователи", as moving it would
  narrow the rights of a delegated administrator. The flags of search and the sitemap of a type are two entries to one flag
  `settings.integrations` with one writer, `updateNodeTypePart()`, and `features.rating` and `rule.active` are two levels,
  a feature of a type and the taking of votes.
- **A section that goes takes its row with it.** The merge of settings only adds a module, and `getAdminSectionList()` does
  not check the file of a section, so a row left behind is a menu item with an empty page. `setUpdateModules()` of
  `public/update.php` drops a record without a module on the move to 8.0; after it a step of the version does (`docs/UPDATE.md`).
- **The admin block and the floating menu read one row.** `getAdminBlockLinks()` reads the row `bkey = 'admin'` of
  `_blocks` once a request, for a super administrator only, and parses its links: an `<a>` tag or a Markdown link of the
  editor, `[homeurl]`, `[host]` and `[admin]` replaced by this site, its host and its panel file, `javascript:OpenWindow`
  read as its address, a missing file inside the site marked. `adminblock()` draws the block — in the panel on the home,
  and on every page while the floating menu is off (`dock-off`); on the site first in its column whatever its weight. `getAdminDock()` draws the floating menu of the theme of the page
  for a logged administrator: the panel always prints it and hides it under `data-sl-dock-off`, the site neither prints
  it nor loads `dock.js` while `dock-site-off` is set. `getAdminSectionList()` is the
  one check of the sections an administrator may open, read by the menu and the home of the panel, the pins and "Все
  разделы" (`go=5&op=getAdminSectionData`); `getAdminPageLink()` answers "Эта страница в панели". A Node type is a
  section of its own, keyed by its name, opening `name=node&type=<name>` and carrying its queue; the home, the menu and
  "Все разделы" show the types as a group "Типы материалов" after the site modules and apart from them, since a type is no module,
  and a type is turned on and off only in the types of Node. A module
  administrator sees only the sections and queues of their own modules and types, without own links and without the watch.
- **The watch window** `#sl-stats` is printed by `getWindowSet()` on every page of a super administrator while
  sessions are on; its body is `getSessionStats()` (`go=5` in the panel, `go=1` on the site, with the token), asked on
  the first opening and polled at `getLiveEvery('panel')` only while the window is open and seen. It shows the present of `_session`
  only, without history.
- **Live intervals.** `getLiveEvery($kind)` of `core/system.php` is the one source of every timed poll, read from the
  group `live` of `config/global.php` and held to its bounds: `site` (the blocks of users and the poll, 5–300 s, 0 stops
  them), `panel` (the cockpits, the statistic, the watch window and the threshold of the server sample, 10–300 s) and
  `note` (the bell, the queue of the floating menu and the private messages, 30–600 s). A poll of the panel or the site
  reads its interval from the `data-*-every` of its own box, a notice from `data-sl-live-note` of the root; no script
  keeps a number of its own.
- **Voices.** `plugins/system/sound.js` plays `<voice>.mp3` of the theme's `sound/` (`docs/TEMPLATES.md`, "Theme
  Structure"); `getAssetList()` adds it only for a theme that has the folder. The count of private messages of a logged
  member is polled by `go=1&op=getPrivateCountData` at `getLiveEvery('note')`; a response names its voice by `HX-Trigger`.
- **The screensaver** "Возрождение" is the scene `assets/js/phoenix.js` of the theme `admin`, started by the idle timer
  of `admin-ui.js` after the minutes of `slaed-toggle:saver`; it never starts over an open window, and its badge is the
  first two parts of `SL_VERSION` with `SL_CODENAME`.

## Assets

Theme-local assets belong under each theme:

```text
public/templates/<theme>/assets/
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
  -> Layout of config/layout.php, the page around the request
  -> module include
  -> module calls setHead()
  -> module content
  -> setFoot(): the sections of the page around the content, blocks
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

#### Quick edit

One in-place edit of a stored text, shared by every place that offers it: the
item's dial opens the text as the editor in the same spot, a save puts the
rendered text back without leaving the page, Cancel restores what was shown
without a request.

**Subject.** A subject is one editable text of one item, named by three closed
values: `kind` (`comment`, `forum`, `node`), `id`, and `field` (`body`; `intro`
or `body` for a Node material of a type without an extension). The class
`QuickEdit` (`core/classes/quick.php`) owns the protocol and nothing else; its
factory `getQuickService()` in `core/system.php` builds one adapter per kind as
three closures, the way `getRatingService()` builds `Rating`:

- **source** — the right, the stored text, its stamp and the rendered editor,
  all from the stored row, never from the request. The editor is rendered
  inside the adapter with the literal store of its column (`comment.body`,
  `forum.body`, `nodes.intro`, `nodes.body`), because `EditorRoomTest` holds
  every `getTplTextarea()` call to a literal store.
- **write** — the kind's own transactional writer: `Comment::updateComment()`,
  `updateForumBody()` in `core/user.php`, `NodeService::updateNodeText()`.
- **view** — the stored text rendered exactly as its page renders it
  (`getCommentBody()`, `getForumBody()`, `NodeView::getNodeView()`), with the
  edited mark the page always prints in a wrapper (`quick-mark-<kind>-<id>`,
  empty while unedited), so a save can refresh it out of band.

**Stamp.** A Node material uses its `version`. A comment and a forum post have
no version column; their stamp is `QuickEdit::getStamp()`, the sha1 of the
stored body with its edit time (`edited`, `etime`), so an edit through a full
form changes it too. The stamp travels in the editor form only.

**Order of a save.** What depends on the text alone runs before the
transaction: the canonical form of the kind's own filter, its rules and the
room of the column, and for Node the attachment ownership under the directory
lock. Under the lock the writer decides, in this order: existence
(`unavailable`), the right and the window again from the locked rows
(`denied`), an equal canonical text (`saved` without a write, no edit time),
and only then a stale stamp (`conflict`). Equality comes first, so the
repetition of a save whose answer was lost is saved, not a conflict.
Locks: a comment locks its row alone; a forum post locks the topic row before
the post row; Node locks the type row, then the material.

**Routes.** `go=1&op=getQuickEdit` (GET only) answers the editor;
`go=1&op=updateQuickEdit` (POST only) takes `kind`, `id`, `field`, `stamp` and
`text`. Both stand in the router's `$public` list and check their own token,
from the `X-CSRF-TOKEN` header alone (`checkQuickRequest()`), so an expired
session answers 403 and never swaps an alert over the typed text.

| Code | Status | Answer |
| --- | --- | --- |
| saved | 200 | the rendered region and the edited mark with `hx-swap-oob`; a note (a Node author edit sent back to moderation) as the event header `sl-quick-note` |
| invalid | 422 | a field outside its closed form |
| denied | 403 | no right, the window has closed, or the token is missing or stale |
| unavailable | 404 | the item is gone; a Node material the visitor may neither edit nor read answers the same, as Node answers a missing and a closed material alike |
| conflict | 409 | the current text rendered inside `data-sl-quick-stamp` with the fresh stamp |
| rules | 422 | the messages of the kind's rules |
| storage | 500 | the write failed |

htmx swaps no 4xx/5xx answer: `setQuickEdit` in `plugins/system/slaed.js`
tells a refusal on the warning toast and keeps the editor and the text; on 409
it takes the current text and stamp and asks once through `setConfirmTask()`
whether to overwrite. Escape cancels and Ctrl+Enter saves unless a window, the
confirm dialog, an editor popup or the fullscreen editor stands in front.

**Editor assets.** A page load carries an editor's engine as plain tags; a
fragment answered over htmx names them to the client loader
(`Editor::getAssetTags()` → `window.SlaedEditors.load()`), which adds only what
the page lacks, so no engine and no global listener runs twice. The init of an
instance (`Editor::getInitScript()`) waits for the engine, checks that its node
is still in the document, and registers its teardown; a region swapped away
destroys every instance inside it (`SlaedEditors.drop()`), including both Toast
UI registries.

**Limits.** What the quick edit deliberately does not do, each a decision of
its own if it is ever wanted:

- Node types with an extension keep the full form: the closed action set of
  `NodeExtension` has no `edit`, and the `sync` extension owns the body of its
  materials.
- The Node title is not a quick-edit field: the address, the `h1`, the document
  title and the breadcrumbs are built from it, and a region swap updates none of
  them.
- A forum post renders as its page renders it, safe with trust, as a Node text
  does; its text is stored as typed through `filterSourceText()`.
- A moderator without the trusted-tag right drops the trusted tags of a text
  the main administrator wrote (`filterTrustedTags()`), exactly as the full forms
  do.

**Adding a kind.** Give it a stored stamp (a version column, or the body with
its edit time), a transactional writer that answers the closed codes in the
order above, a view that renders as its page does, an always-printed mark
wrapper if it has an edited mark, a region marked `data-sl-quick` on its page,
and a dial entry to `getQuickEdit` with the page token; then register the
adapter in `getQuickService()` and extend `QuickEditTest`.

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
| Updates from the panel, releases and keys | `docs/UPDATE.md` |
| Security policy | `SECURITY.md` |

## What Not To Assume

- Do not assume a framework kernel exists.
- Do not assume a dependency injection container exists.
- Do not assume a middleware pipeline exists.
- Do not assume all plugins share the editor manifest contract.
- Do not assume modules are isolated packages.
- Do not assume the address of a page holds its id: build it through `getSeoUrl()` with the `route` its reader
  joined, and never write a page address by hand.
- Do not assume parser cache, asset cache, or scheduler jobs are enabled without
  checking current configuration.
- Do not copy architecture claims from other projects without validating them
  against this repository.
