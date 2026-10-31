# Upgrading SLAED CMS

> **Migration Guide for SLAED CMS**

This document says what this release does with an existing site and what changed for code written against the
previous release.

## Table of Contents

- [Existing Sites](#existing-sites)
- [Breaking and Important Changes](#breaking-and-important-changes)
- [Migration Checklist for Custom Code](#migration-checklist-for-custom-code)
- [Troubleshooting](#troubleshooting)

---

## Existing Sites

> [!IMPORTANT]
> This release installs a new site only. It does not update a site of SLAED CMS 6.2 or of an earlier release:
> no file of the release reads or converts an existing database or configuration, and `setup.php` refuses a site
> that is already installed.

Install the release as a new site, as `README.md` describes, into an empty database or under a table prefix that no
table of the database carries yet. Keep the old site and its backups until the new one is ready.

### Minimum Runtime

- **PHP:** 8.4+
- **Database:** PDO MySQL-compatible server: MariaDB 10.5.2+ or MySQL 8.0.16+, InnoDB tables; MySQL 8.4 or MariaDB 10.11
  and newer are recommended, since MySQL 8.0, MariaDB 10.5 and 10.6 are out of upstream support

### Writable Directories

Typical writable paths:

- `config/`
- `storage/`
- `uploads/`

The installer never changes file permissions. It does not install while `config/db.php`, `config/global.php`,
`config/security.php` or `config/update.php` is not writable by PHP (for one of them that does not exist yet:
`config/` itself).

---

## Breaking and Important Changes

The current codebase confirms these project-level changes in 6.3:

### Node Replaces Nine Content Modules

The modules `news`, `pages`, `faq`, `help`, `jokes`, `content`, `links`, `files` and `media` are gone, with
their blocks, configuration files and tables. Their content is served by Node
(`modules/node`, `core/classes/node/`): one module that runs any number of content types side by side.

- A type keeps the name of the module it replaces as its address, `/<type>`; a material opens at its own
  path, `/<type>/<name>`, and has one global id across all types. The 6.2 query addresses answer 301 to it.
- The installer creates ten active types from `modules/node/profiles/*.json` — the nine replacements
  and `docs` — with the first administrator, and one welcome news item.
- Custom code that read these tables or called helpers of the removed modules must move to `NodeQuery`
  (reads) and `NodeService` (writes).

### Shop, Order and Service Modules Are Not Shipped

The modules `shop`, `clients`, `order`, `money`, `auto_links` and `whois` are not part of the package, nor
their block `blocks/auto_links.php`, configuration files, upload directories and tables.

- The shared subsystems have no scope for them: the shop cart, the shop comments, ratings and favorites, the
  points action `order`, the extra-field area `order`, and the RSS feed, sitemap section, search source and
  newsletter audiences of these modules do not exist.
- `config/global.php` has no `amod`.
- A site that needs these modules stays on its current release.

### The Referers Module Moved Into the Statistics

The hosts and landing pages of visits are counted in the files of the statistics, so the page `name=referers`, the table
`{prefix}_referer`, `config/referers.php` and the log of addresses with them are gone.

- The robots, by the types `ai`, `search`, `seo`, `social` and `other`, and the hosts of the sources `search`, `social`
  and `ai` are edited on the settings of the statistics and live in `config/statistic.php`; `bots` and `fbots` left
  `config/global.php`, and `fbots` has no successor by that name.
- A release adds the robots it ships and the site lacks to `bots` of the site, so a robot the administrator removed or
  moved to another type comes back with the next update. The host lists stay as the site has them.
- Custom code calls `getBotInfo(getAgent())`, which answers the `name` and `type` of a robot, with an empty `name` for a
  person, in place of `is_bot()`.
- The tabs of `name=config` after "Цензура" moved up by one: the cache is `tab=4` and the mail is `tab=5`.

### Node Upload Directories Under `uploads/node/`

Every Node type keeps its files in `uploads/node/<type>/`; the folders of the modules (`account`, `all`, `avatars`,
`forum`, `presentation`, `profile`, `voting`) stay in `uploads/`. Custom code asks `getUploadFolder()` for the folder
of an owner instead of building `'uploads/'.$mod`.

No folder of `uploads/` carries a guard file any more, and the upload service creates a missing folder of its owner
on the first write. No stored text and no setting carries the folder: texts reach their files through `go=file`
and `op=asset` by name, and the default folder of the uploads screen (`dir` in `config/uploads.php`) names a type,
not a path.

### The Document Root Is `public/`

The browser reaches `public/` and nothing else: the entries `index.php`, `admin.php` and `setup.php`,
`.htaccess`, `robots.txt`, `favicon.ico`, `error.html`, the sitemap files, `templates/`, `plugins/`
and `demo/`. `core/`, `modules/`, `admin/` with the body of the panel, `config/`, `storage/` and the whole
`uploads/` stay at the project level. Point the document root of the server at `public/`; where a host cannot move
it, the `.htaccess` of the project rewrites every request into `public/` (Apache and LiteSpeed with `mod_rewrite`
only, and without the module it refuses everything). The server settings and the variants for shared hosting are in
README.md, "Document Root and Web Server". The installer records the mode it found as `webroot` in
`config/global.php`.

No upload lies in the document root. An address `uploads/<folder>/<name>` reaches `index.php`, whose light path
serves a file of a public folder (avatars, `presentation`, `layout` and `all`) before the core boots and
answers 410 for every other folder and every missing file; the files of a Node type leave only through `go=file` and
`op=asset`, the files of the forum only through `go=file` to a reader of the post that names them, the files of
`uploads/account/` only to the two sides of the private message that names them and to a moderator of `account`, and
the files of a comment on a poll (`uploads/voting/`) or on a profile (`uploads/profile/`) only to a reader of its
poll or profile while the comment is published, and to a moderator of `voting` or `account`. A new `[attach]` in a
comment, Node included, names an own upload or a file the same target already serves; any other is refused. The
owners, the route and the checks are in `docs/ARCHITECTURE.md`, "File Delivery Boundary".

`uploads/account/` holds the files of the private messages alone. The signature, the own block and the comments on a
profile upload into `uploads/profile/`; a signature is served from there to whoever may open the profile, an own
block to its owner alone; the mail texts of the panel take their pictures from the public `uploads/all/`. The release
added `profile` to `config/uploads.php`. nginx reads no `.htaccess`: the release ships `nginx.conf.example`, whose
`server` block carries the root on `public/`, the prefix `/uploads/` for the front controller, a path after a script
sent to that script and the refusals of `public/.htaccess`.

A path of `index.php` is a readable address (see "Readable Addresses" below); a path after another script answers
301 to that script with the same query, and an old `*.html` address gets the 404 page.

Every `.htaccess` outside `public/` but the one of the project, and every guard `index.html` outside it, are gone:
a folder outside the document root needs none.

The installation checks this itself. The project, `storage/`, `config/`, `uploads/` and `admin/info/` each hold an
untracked marker `check.txt`; the system job `selfcheck` asks the site address for every marker each hour and judges by
the body, and the panel warns when a marker is served, when the site could not be asked, or when there is no verdict
within a day. A Node type is switched on only while `uploads/` is closed. The markers are not part of the release; the
installer writes them, and the check writes a missing one again. `docs/ARCHITECTURE.md`, "Private Data Boundary",
describes the tree, the two modes and the check.

### Journals Carry No Secret and Say What They Hold

- A refused login is recorded without the attempted password. The request journal, behind the `log` switch of
  `config/security.php`, is written by `Logger` with the masking of every other channel: the values of keys matching
  `pass`, `pwd`, `secret`, `token`, `key` or `code` are replaced, and cookies and the session leave their names only.
- The files of `storage/logs/` are renamed: `log_admin.log` → `admin.log`, `log_user.log` → `user.log`,
  `log_oauth.log` → `oauth.log`, `log.log` → `request.log`, `dump_log.log` → `filescan.log`, `dump.log` →
  `filescan_tree.log`, `dump_map.json` → `filescan.json`. A file operation lands in `file.log` below the level `error`
  and in `error_file.log` from it on, never in both. A rotation archive is `<name>_<date>.log.<zip|gz|bz2>`, or
  `<name>_<date>.log.bak` without a compressor.
- The locks of the scheduler and of the upload service live in `storage/cache/locks/`, which a cache clear leaves in
  place.
- A log reader, a monitoring rule or a `logrotate` entry of your own that names the old files has to follow.

### Readable Addresses

Every page has one address without an id — `/files/adaptivnyi-shablon-pure`, `/files/moduli`, `/forum/modul-poiska`,
`/users/eduard` — held in the registry `_routes`. A 6.2 query address, a former path, upper case or a trailing slash
answers one 301 to it; the path of a deleted entity answers 410 and is never given again. `update.php` fills the
registry. The reference is `docs/ARCHITECTURE.md`, "Address Boundary".

- The server must send every path that is no file or folder to `index.php`: `public/.htaccess` does it for Apache and
  LiteSpeed, `try_files` of `nginx.conf.example` for nginx.
- `rewrite`, `sep`, `tsep`, `title` and `ctitle` are no settings any more.
- Custom code builds a page link through `getSeoUrl()` with the `route` its query joined from `_routes` (`cur = 1`),
  `null` for an entity without one; every address starts at the root of the site, `HOME_PATH`, `{{ root }}` in a
  template. A public list counts its pages by `page`, not `num`.
- `getVar()` reads `$_GET` and `$_POST` itself, so the keys of a path reach every reader.

### Updates From the Panel

From 8.0 a site updates itself: the scheduler job `updatecheck` asks `slaed.net` once a day, and the page
"Обновления" of the panel downloads, verifies and installs a release in steps, with a rollback of files, `config/` and
database on any failure. The reference is `docs/UPDATE.md`; the help of the page is `admin/info/update/ru.md`.

- The version is `SL_VERSION` and `SL_CODENAME` in `core/version.php`; `$conf['version']` is built from both at boot and
  is no key of `config/global.php` any more, and the settings form no longer writes it.
- `public/index.php`, `public/admin.php` and `public/setup.php` call `getSiteGate()` of `core/gate.php` first, before
  the core; an entry of your own that loads the core does the same, or it opens while an installation is under way.
- An update replaces the language files of the shipped modules and the themes `admin` and `lite` whole: keep edits in
  a theme of your own. Every other changed core file stops an installation until "перезаписать" is ticked; a PHP file of
  your own in `admin/`, `core/`, `lang/` or `public/` is reported by "Проверить целостность".
- An update adds the options a site lacks to `config/` and keeps every value the site saved; code of your own reads a
  new option with a fallback (`$conf['section']['option'] ?? …`), since a site updated by FTP without `config/` lacks it.
- `public/sitemap.xml` is no file of the repository; the job `sitemap` writes it, and `public/robots.txt` is a site
  file that an update never overwrites.
- The API answers under `/api/v1/` of `public/index.php`; the server must send that path to `index.php` like every
  other path that is no file.

### Points, Ratings and Extra Fields

- Points run on the journal `{prefix}_points`; `{prefix}_users.points` is the balance.
- Ratings live in `{prefix}_rating_targets`, `{prefix}_rating_actors` and `{prefix}_rating_votes`; a vote is a
  POST request. The rules of every target are in `config/ratings.php`.
- Extra fields of accounts and forum posts are named definitions in `config/fields.php` with JSON
  values, handled by the `Field` class; the positional `||` strings of 6.2 are no longer read.

### OAuth2/OIDC Login Added

Built-in OAuth2 Authorization Code Flow with PKCE (Google and Microsoft in V1, no Composer dependencies) replaces the legacy third-party social login integration:

- The tables `{prefix}_user_oauth` (permanent provider links) and `{prefix}_oauth_temp` (one-time state/pending records) hold the provider data; `{prefix}_users` has no `network` column.
- Providers are configured by the super administrator in the admin panel under Security, the tab "OAuth keys" (Client ID / Client Secret per provider), or in `config/oauth.php`. The redirect URI to register at the provider console is `https://your-site/index.php?name=account&op=oauth`.
- Accounts created through OAuth have no password (an invalid `!`-prefixed marker is stored); a password can be added later via password recovery.

### Cache And Asset Settings

`config/global.php` holds the cache field `cache`: it switches the parser cache on (`1`) or off (`0`). The `cachegc` job removes cache files not rewritten for a day (`Cache::KEEP`). Styles and scripts are linked one file at a time from `css_f`, `script_f` and the theme package. Keys the settings form does not write are ignored and disappear on its next save.

`cache_version` `4` rebuilds `config/local.php` on the first request.

`config/local.php` is the merged configuration cache. It is accepted on its version marker alone and is never compared against the source files it was built from. The installer removes it with every configuration file it writes; a hand edit of a file in `config/` needs `rm -f config/local.php`.

### Database Layer

The active database class is `Database` in `core/classes/pdo.php`.

Current method family:

- `getSqlQuery()`
- `getSqlRow()`
- `getSqlRows()`
- `getSqlField()`
- `getSqlRowCount()`

Custom code should use the current `Database` API.

- `Database::__construct()` takes the collation as its fifth argument. Every connection runs `utf8mb4` in the
  collation of `config/db.php` (`utf8mb4_unicode_520_ci` for a new site), the time zone of PHP and the SQL mode
  `STRICT_TRANS_TABLES, ONLY_FULL_GROUP_BY, NO_ZERO_DATE, NO_ZERO_IN_DATE, ERROR_FOR_DIVISION_BY_ZERO,
  NO_ENGINE_SUBSTITUTION`. A `GROUP BY` must name every selected column no aggregate wraps, and a value longer than
  its column fails instead of being cut: cut it with `filterCutText()` before the write. `$conf['db']['sync']` is
  gone.
- A moment is `DATETIME`, a machine timer is `BIGINT` unix seconds, and no column holds a time as text.
  `_users_temp.time` is gone; `_blocks.expire` and `_message.expire` are `DATETIME`, NULL for no expiry.
- `_users.email`, `_admins.email` and `_users_temp.email` are unique and NULL for "no address": write NULL, never `''`,
  and read the result of the write — `Database::getSqlTwin()` names the key of a refused duplicate.
- Every `lang` column holds a language code (`ru`, `en`, …), never a name such as `russian`.
- The conventions are in `docs/NODE.md`, "Schema conventions".

### Input Handling

The current project pattern is to use `getVar()` instead of direct request access:

```php
$id = getVar('post', 'id', 'num');
```

### Password Hashing

Current helpers in the runtime:

- `getPassHash()`
- `checkPassHash()`

### CSRF Tokens

Current helpers:

- `getSiteToken()`
- `checkSiteToken()`

### Content Editors

The pluggable editor layer is active via the `Editor` class (`core/classes/editor.php`). Forms and textareas should output via `Editor::getContent()` or `Editor::getCode()` rather than hardcoded editor initializers. The available editor drivers are bundled under `plugins/editors/`.

A code editor driver of your own must accept a sixth argument: `CodeDriver::getWidget(string $id, string $name, string $value, string $lang, string $profile, string $label)`. The label is the accessible name of the editable area and is never empty; a driver with the old five-argument signature no longer loads. Pass `'label'` to `Editor::getCode()` to name the field.

### Content Parsing

User and administrative content formatting should be passed through the unified `Parser` class (`core/classes/parser.php`), typically accessed via its `filterContent()` method.

### Template Layer

The active file-backed template runtime is `core/classes/template.php`.

New template work should target the modern runtime and theme HTML files under `templates/`.
The modern engine supports automatic CSS and JS injection for components placed in `partials/`: `{% component '<name>' %}` also loads `partials/<name>.css` and `partials/<name>.js` at compile time when those files exist.

When upgrading custom modules:
- Remove subdirectories from your module's `fragments/` logic (e.g. `new/`). The fragment namespace has been strictly flattened. Update `$tpl->getHtmlFrag(...)` calls accordingly.

### Themes

Themes currently present in the repository:

- `admin`
- `lite`

A custom theme directory of an older release has to be reviewed against the structure of these two before it is used.

---

## Migration Checklist for Custom Code

Use this checklist when reviewing custom modules, custom admin code, or local patches.

### Security

- [ ] Replace direct request access with `getVar()` where possible
- [ ] Replace string-built SQL with prepared statements
- [ ] Review state-changing actions for CSRF protection

### Database

- [ ] Update custom DB calls to current `Database` method names
- [ ] Re-test custom queries against the current schema

### Templates

- [ ] Review custom templates against the current theme structure
- [ ] For new template work, prefer the modern `Template` runtime
- [ ] Keep HTML in theme files, never in PHP - `php tools/ui-audit.php --markup` fails on a hardcoded class, inline style or tag

### Configuration

- [ ] Read `$conf['version']` or `SL_VERSION`, never a `version` key of `config/global.php`
- [ ] Read every new option with a fallback
- [ ] Keep theme and language edits out of `admin`, `lite` and the shipped language files
- [ ] Review local config overrides
- [ ] Re-check file permissions after deployment

### Runtime Validation

- [ ] Check `storage/logs/`
- [ ] Test admin login
- [ ] Test frontend entry page
- [ ] Test the modules critical to your site

---

## Troubleshooting

### White Screen / HTTP 500

Check:

- PHP error log
- `storage/logs/`
- web server logs

### Database Connection Errors

Verify:

- database credentials
- database server status
- charset and prefix settings

### Cache Issues

Clear runtime cache:

```bash
rm -rf storage/cache/*
```

### Missing Constants or Language Errors

Review:

- `lang/*.php`
- `admin/lang/*.php`
- module-specific language files

---

## Support

- **Documentation EN:** [slaed.info](https://slaed.info)
- **Documentation DE:** [slaed.de](https://slaed.de)
- **Forum:** [slaed.net/forum](https://slaed.net/index.php?name=forum)
- **Email:** info@slaed.net

---

*SLAED CMS © 2005 - 2026 Eduard Laas. Released under MIT License.*
