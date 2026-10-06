# Private Data 2026

Work plan for the private data of an installation: no secret in a journal, a project tree whose private part the web
server cannot reach, a self-check that proves it, a server configuration in the delivery, and journal names that say
what they hold.

Status: batch 1 done 2026-10-05 (`JournalSecretTest`); batch 2 done 2026-10-05 (`PublicTreeTest`): the `public/` tree
with the real entries in it, `core/stream.php` with the light path, `getUploadPublic()`, `getUploadUrl()` and the one sender,
no guard outside `public/`, the key `webroot`, the stand on `public/`. Three points differ from the text of batch 2:
`getUploadUrl()` lives in `core/stream.php` beside the list, so the file layer and the tests reach it without the core;
`getFileStream()` sends the sandbox policy on every answer, `op=attach` and `op=asset` included; and the Node rule of
UPGRADING.md went with batch 2, because the guards it relied on are gone, so batch 4 only points UPGRADING.md at
`nginx.conf.example`. On the owner's request the server half of batch 4 landed with batch 2 as well: the file
`nginx.conf.example`, `NginxConfigTest`, the help of the security section with the two modes and the variants of
shared hosting, README.md and UPGRADING.md pointing at the file; batch 4 keeps only `setup_old/`. The critical files
of the file layer follow the document root by `PUBLIC_DIR`, so a root named `public_html/` keeps them. Batch 3 done
2026-10-06 (`SelfCheckTest`): `checkPrivateRoots()` over the project and the four folders with the transport as its
seam, the markers by `setPrivateMark()`, the system job `selfcheck` every hour, which keeps the verdicts in its own
scheduler state, and `getSelfCheckAlert()` on the home of the panel (only a problem or no run within a day) and in
the security section (the all-clear as well); the last part of `setup.php` writes the markers, since it is the one that runs with the core; `checkTypeGuard()` asks the upload root
alone. The plain name was tried on the stock OSPanel nginx of the stand: `check.txt` is served as it is. Batch 4
done 2026-10-06: `setup_old/` left the tree with its ignored `.sql` files, and `PublicTreeTest` no longer exempts it;
the `.sql` files of the old installer are in history before `b2973ad4`, the schema in `storage/update/sql`. Batches
5-6 open. The order
of the batches is kept in `docs/ROADMAP-2026.md`: batches 0 and 1 of 1-FILES-2026.md run between batch 1 and batch 2,
so the Node types sit in `uploads/node/<type>/` before the tree is split. Update this line as batches land.

No line numbers anywhere in this document on purpose: every reference names the function, the file or the constant
it points at, and that name is what to search for.

## Problem

Two independent halves. Either one alone is survivable; together they turn one server misconfiguration into a
credential leak.

**Secrets reach the journals.** `Logger` masks them already: `MASKKEYS` replaces the value of every key matching
`pass`, `pwd`, `secret`, `token`, `key` or `code` before a line is written, in the body as well as in the query
string. Two writers of secrets predate that class and bypass it, writing with `fopen()` and `fwrite()`; three more
bypass it without secrets (`OAuth::setLog()`, `addDblog()`, `write_log()`) and are named by batch 5 only:

- `addLoginReport()` in `core/system.php` writes the first 25 characters of the submitted password into
  `log_admin.log` or `log_user.log` whenever a login is refused. It has five call sites, two in `admin/index.php`
  and three in `modules/account/index.php`; the three that report a refusal pass the password. Its rotation opens
  the file before `addCompress()` moves it away, so the line written at the rotation lands in a file that no longer
  exists.
- `addLog()` in `core/security.php` writes the whole request through `getVariablesInfo()` into `log.log`: `POST`,
  `GET`, `COOKIE`, `FILES` and `SESSION` verbatim, on every request while the `log` setting of the security
  configuration is on. The setting ships off (`'log' => '0'` in `config/security.php`).

A journal holding a password is a credential store nobody audits. It travels into backups, into the rotation
archives `addCompress()` writes beside it, and into any archive handed to support. Web access is only one of its
exits.

**The whole project sits in the document root.** The server can reach every file of the tree: the journals of
`storage/logs`, the parser and template caches of `storage/cache`, the SQL dumps of `storage/backup`, the database
password and the secret of `config/`, the closed upload folders, and the administrative help of `admin/info`, which
describes the closed paths and the critical files of the system to a reader who never logged in. The tree tries to
keep them private with 95 tracked `.htaccess` files carrying `deny from all` (outside `setup_old/`), and
`CaptchaStore::ensureDir()` and the writers of `FileManager::getGuardFiles()` write more at runtime. Apache honours
them; nginx never reads them, and nothing in the delivery states the same intent in a form nginx understands. The
installation has no way to notice.

## What already holds

Written down so the next reader does not re-derive it.

- **Masking works** for the structured channels of `Logger`: `php`, `sql`, `file`, `site`, `warn` and `hack`. The
  request context it writes carries `cookie_keys` and `session_keys`, the names and never the values.
- **The runtime paths are constants.** `CONFIG_DIR`, `BACKUP_DIR`, `CACHE_DIR`, `COUNTER_DIR`, `LOGS_DIR`,
  `SITEMAP_DIR`, `CAPTCHA_DIR` and `UPLOADS_DIR` are resolved once at boot in `core/system.php`.
- **Sessions are not in the tree.** No code sets a session save path; PHP keeps them where its configuration says.
- **The administration already warns about its environment.** `checkPerms()` prints a warning alert above a
  settings screen when the permissions of a path are wrong. A verdict about reachability belongs beside it.
- **A transport for the self-check exists.** `getSchedulerFetch()` performs a plain GET against the site itself and
  reports the status, the body and the transport error; Node already asks it whether a type folder is refused.
  `Upload::addRemoteFile()` refuses every address that is not globally reachable, which is exactly the address a
  site has when it asks itself a question, so its policy is not reused.

## Batch 1 — no secret reaches a journal

The smallest change and the only one that removes a cause instead of a consequence.

- **The login report carries no password.** `addLoginReport()` loses its `$pass` parameter and its five call sites
  follow. What a refused login records is who tried, under which login, from where and when. The rotation opens the
  file after `addCompress()` has moved the old one.
- **The request journal moves onto `Logger`.** `addLog()` and `getVariablesInfo()` are removed. While the `log`
  setting is on, every request is written by `Logger` on a channel of its own into `request.log`, with the masking
  and the `cookie_keys` / `session_keys` rule every other channel already follows. One definition of a secret serves
  the whole system. Its readers follow in the same batch: `getSecurityEventHours()` in `core/monitor.php` reads the
  structured `request.log` instead of the text of `log.log`, and the label key `log` of `admin/modules/security.php`
  becomes `request`.

Tests: a probe that drives both writers with a request carrying a password, a session and cookies, then asserts the
resulting files contain none of the three values and do contain the key names. A second case asserts a refused
login writes an entry without the attempted string, a third that the entry written at the rotation reaches the new
file, and a fourth that the security dashboard counts the events of `request.log`.

## Batch 2 — the project out of web reach

The only measure no server misconfiguration can undo. It belongs to a major release, and 8.0 is that release. The
decisions marked 2026-10-05 below were taken by the owner when this batch opened; they are settled.

**The tree.** The document root is `public/`. It holds what a browser fetches and nothing else:

| In `public/` | Stays at the project level |
| --- | --- |
| `index.php`, `setup.php`, `update.php` whole, `admin.php` as the stub of `admin/index.php` | `admin/index.php`, the body of the panel |
| `.htaccess`, `favicon.ico`, `robots.txt`, `error.html` | `core/`, `modules/`, `admin/`, `blocks/`, `lang/` |
| `sitemap.xml` and its parts `sitemap-N.xml(.gz)` | `config/`, `storage/` |
| `templates/`, `plugins/`, `sound/`, `demo/` | `uploads/`, whole: every owner, the one upload root |
| | `tools/`, `tests/`, `docs/` |

`templates/` and `plugins/` move whole: no address of an asset changes and the theme package contract stays as it
is. The markup of the templates is not secret, but it is still refused: the rules of `templates/.htaccess` (`*.php`)
and of the theme folders (`*.html`) move into `public/.htaccess`, scoped to `templates/`, and batch 4 states the same
for nginx. `demo/` moves into `public/demo/`: its static pages link `../templates` and `../uploads/presentation`,
which keep resolving, the second through the light path below.

**Real entries** (2026-10-05, revised the same day by the owner). `index.php`, `setup.php` and `update.php` move into
`public/` whole, under their names; each defines `BASE_DIR`, the folder above its own, and `PUBLIC_DIR`, its own
folder, unless a test or a tool defined them first. `admin.php` stays the short file it always was and requires
`admin/index.php`. No entry is split in two, so no name exists twice. The panel file that `setSetupConfig()`,
`setUpdateRun()` and `configsave()` of the security section rename lies in `public/`; so does the `setup.php` whose
presence `admin/index.php` warns about and which `setSetupDone()` removes as `__FILE__`. The price, accepted: when the
PHP handler of a host fails, the server shows the source of the three entries instead of a stub of ten lines; they
carry no secret. Later the routing of `index.php` may move into the core, which leaves a short front controller.
Rejected: a stub in `public/` with the body of the same name at the project level, the first form of this decision,
for the two files of one name it leaves; the bodies in `core/` under new names.

**One standard for every uploaded file** (2026-10-05). Every file belongs to an owner and lives in the folder of
that owner below the one upload root `uploads/` at the project level, `UPLOADS_DIR`; no upload is under the document
root, so no server, whatever its configuration, can execute or list one. The root, the folder names
(`getUploadFolder()`), the paths `Upload` writes and returns, the upload area of the administration and the locks
stay as they are; only the root moves out of the document root. What differs between owners is the delivery:

- *Public owner* — avatars, `presentation`, `all`, `voting`, `archive`, and the forum and the account until
  1-FILES-2026.md closes them. The address stays `uploads/<folder>/<name>`, so no stored text, outside link or image
  index changes. No file exists there in `public/`, so the request reaches the front controller (`!-f` on Apache, a
  `location ^~ /uploads/` on nginx), and the **light path** answers it before the core boots: no configuration, no
  database, no session. It serves a file of a public folder, a `thumb/` copy included, and refuses everything else
  — a private folder, a name that leaves its folder, a missing file — with 404 until item 1 of
  2-PROD-FINDINGS-2026.md turns that into 410.
- *Private owner* — the Node types now; the forum, the private messages and the profile comments with their batches
  of 1-FILES-2026.md. Only the route of the owner delivers a file, after its rights check: `op=attach` and `op=asset`
  of Node, `go=file` of 1-FILES-2026.md batch 4. The light path refuses the folder like any other it does not serve.

The answer of the light path:

- `Content-Type` from a fixed map of the extensions the upload service accepts. A type of the safe list of
  `getFileStream()` (raster images, audio, video) goes out inline; every other file, an SVG included, goes out as an
  attachment.
- Every answer carries `X-Content-Type-Options: nosniff` and `Content-Security-Policy: sandbox`, so a stored SVG or
  HTML file runs no script on the site.
- A public file is cacheable: `public, max-age=86400` with `ETag` and `Last-Modified`, 304 on a match and ranges.
  `getFileStream()` knows only `private` and `none` today, and `Cache::setHeaders('public')` sends seven days
  (`Cache::STATICDAYS`) without an `ETag`; `getFileStream()` gains the public mode with the lifetime of the table of
  3-ASSET-CACHE-2026.md and its `ETag`. A managed name carries a random part, so a browser or a CDN asks for it once.

Where it is decided:

-  **One list.** The public folders are named once, in the file of the light path, as a function the core uses too.
  `getUploadUrl()` (new, `core/system.php`) answers the address `uploads/<path>` of a path of a public folder and an
  empty string for a private one, whose address only the route of its owner builds. Closing an owner in
  1-FILES-2026.md is a line out of that list plus its route; no folder moves.
-  **One sender.** Every byte of an upload leaves through `getFileStream()`: the light path, `op=attach`, `op=asset`
  and later `go=file`. It moves from `core/system.php` into the file of the light path, `core/stream.php` (new), which
  the core requires as well, so the light path loads that file and the `Cache` class it calls, nothing else. Handing
  the sending to the server (`X-Accel-Redirect`, `X-Sendfile`, `X-LiteSpeed-Location`) is a later setting in that one
  function plus one `internal` location in `nginx.conf.example`; it is not part of 8.0.
-  **No spelled address.** No caller writes `'uploads/'` into an address any more: `Parser::filterAttach()`,
  `getImgText()`, `getUploadRuleData()` and `getUploadPlaceRule()` (their `dir`), `getUserAvatarUrl()`,
  `getPresentationSites()` and the other readers of `modules/presentation/index.php`, `blocks/img.php`, the folder
  labels of `admin/modules/uploads.php` and `FileManager::getFileLink()` for the upload area ask `getUploadUrl()`. A
  filesystem path into an upload folder is `UPLOADS_DIR.'/'.$path`, never `'uploads/'` relative to the working
  directory. `$conf['users']['adirectory']` ships as the folder name, `avatars`, and its settings field in
  `modules/account/admin/index.php` and the help of that module say so; the leading `uploads/` a stored value carries
  keeps being dropped, as `getUploadPlaceRule()` drops it today.

Comparable systems: Drupal's `private://` scheme behind `system/files`, Laravel's private disk behind a controller,
Kirby's originals outside the web root. Rejected: two roots with the public owners in `public/uploads/` served by the
server (the execution of an uploaded file then depends on a server rule), publication on demand into a media folder
(two copies of every file and a cache to invalidate on delete), and the server-side sending in 8.0 (`mod_xsendfile`
is not part of Apache, and every host without it needs the PHP sender anyway, so that sender comes first).

**One `.htaccess` per side.** The tree tracks 96 `.htaccess` files outside `setup_old/`, 95 of them refusing with
`deny from all`, and 141 guard `index.html` files. Every `.htaccess` goes except the root one, which becomes
`public/.htaccess`, and every guard `index.html` except those inside `public/` (`templates/`, `plugins/`, `sound/`);
the page `demo/index.html` is no guard and stays. The guard files written at runtime go with their writers:
`NodeService::setTypeGuards()` with `getGuardList()`, the `.htaccess` and the copy of `storage/index.html` that
`CaptchaStore::ensureDir()` writes into `CAPTCHA_DIR`, and the guard pages `setMigrateFiles()` of `update.php` writes
into `uploads/archive/`. `FileManager::getGuardFiles()` and its `DENY` go; its readers stop skipping guard names,
because no upload folder carries one any more. With the guards gone nothing keeps an empty owner folder in a clone,
so the writer creates it (2026-10-05): the upload service makes a missing folder of its owner, and its `thumb/`, on
the first write, only below `UPLOADS_DIR`, and a missing folder is no refusal of the rule any more, as WordPress
(`wp_mkdir_p()`) and Drupal (`prepareDirectory()`) do. Rejected: a `.gitkeep` in every empty folder, and folders
created by the installer alone. `NodeService::checkTypeGuard()` stops writing guards and keeps its
request until batch 3 moves it onto the self-check; with no file in `public/` that request already answers 404.
`public/.htaccess` carries what the root `.htaccess` carries today, without `RewriteBase /` so that its rules hold in
both modes, without the refusal of PHP under `uploads/`, which no longer exists there, and with the template refusals
above. One `.htaccess` at the project level serves the root-on-the-project mode: it rewrites every request into
`public/`, and where `mod_rewrite` is missing it refuses everything through `mod_authz_core`, so a host without the
rewrite gets a broken site rather than an open one.

**Two modes.** Both are supported, and the installer records which one it found (2026-10-05): the key `webroot` of
`config/global.php` is `public` when `DOCUMENT_ROOT` resolves to `PUBLIC_DIR` and `project` otherwise,
`setSetupConfig()` writes it and `getSetupChecks()` shows it as a row.

- *Root on `public/`.* The server points its document root at `public/`; nothing outside it exists for the web.
  This is the mode for nginx, whose server block sets the root anyway, and the recommended one everywhere.
- *Root on the project.* For a host whose document root cannot be moved, the project sits in the root whole and the
  project-level `.htaccess` rewrites every request into `public/`. Apache and LiteSpeed only. A request for a file
  outside `public/` is rewritten into `public/` and answers 404, and so does a request under `uploads/` the light
  path does not serve (2-PROD-FINDINGS-2026.md turns both into 410).

**One place decides.** `BASE_DIR` is the project, `PUBLIC_DIR` is `public/`, `UPLOADS_DIR` the one upload root.
Every filesystem path into the public part reads `PUBLIC_DIR`, and every path that relies on the working directory
is replaced by a constant. The working directory of a web request becomes `public/`, so a relative path into the
project breaks outright, and one into the public part still works on the web but not under the tests and the tools.

**What follows the tree.** The PHP built-in servers of the tests and tools (`tests/Support/route_web.php` with
`route_probe` and `node_probe`, `install_probe`, `web_probe`, `FileStreamTest`, `tools/node-profile.php`) serve
`public/` and send `/uploads/` to the light path. The editor of `.htaccess` and `robots.txt`
(`admin/modules/editor.php`) and `FileManager::CRIT` name the files of `public/`. The administrative help, the
security section and the documentation of this repository print the paths of the `public/` tree. The stand points
its document root at `public/` (`.osp/project.ini`, `web_root = {base_dir}\public\public`) and gets the
`location ^~ /uploads/` of the light path in `.osp/nginx/slaed.loc.conf` ahead of its regex for static files; the
implementing session changes both and restarts OSPanel (2026-10-05). The screenshot tooling runs against the stand
and follows without a change; the derived asset list of `config/local.php` is rebuilt.

### Inventory of batch 2

Collected 2026-10-05, by function, for the implementing session.

- **Working directory into the project** — breaks: `setLang()` and `getLang()` of `core/security.php` (the
  language files of the site, the panel and the modules), `setTplAdminInfoPage()` and `getTplModuleSelect()` of
  `core/helpers.php`, `modules()` and `add()` of `admin/modules/modules.php`, the language list of `config()` in
  `admin/modules/config.php`, `config()` of `modules/search/admin/index.php` and of `modules/sitemap/admin/index.php`,
  and `addFilescanTask()`, whose scan `create_dump('./')` must walk `BASE_DIR`.
- **Working directory into the public part** — `getConfig()` (the theme list behind the derived assets),
  `getThemeAssets()`, `getAssetList()`, `doCss()`, `doScript()`, `getThemeImagePath()` and every caller that passes
  its answer to a file function (`setHead()`, `getLanguageFlagSrc()`, `getOpenXsl()`, the rank images of
  `core/user.php`, `modules/account`, `modules/forum` and `blocks/user_info.php`, `blocks/banner_random.php`,
  `Geoip`), `getUserAvatarUrl()`, `getAvatarPreset()` and `edithome()` of `modules/account`, `add()` of
  `admin/modules/groups.php`, `getTemplateEditorBlock()` of `admin/modules/template.php`, `Editor::getThemeSkin()`,
  the emoji file of `plugins/editors/toastui/driver.php`, `sitemap()` of `modules/sitemap/admin/index.php`,
  `getSetupLinks()` and `getUpdateLinks()`, and `admin/index.php` for `setup.php`.
- **`BASE_DIR` into the public part** — the theme paths of `core/system.php` (`setHead()`, the logos,
  `checkThemeAssets()`), `addSitemapTask()` and its parts, `getOpenSearch()`, `Template::__construct()`, the editor
  manifests of `Editor` and `admin/index.php`, `config()` of `admin/modules/config.php` (logos, themes),
  `admin/modules/template.php`, `templates/lite/index.php` (the season images), `setUpdateConfig()`, the editor of
  `.htaccess` and `robots.txt`, `configsave()` of the security section, and in `setup.php` and `update.php` the panel
  file and the logo.
- **Upload paths** — `'uploads/'` relative to the working directory in `getUserAvatarUrl()`; every `UPLOADS_DIR`
  caller keeps its meaning. `getUploadsSizeLabel()` of `admin/modules/monitor.php` keeps measuring `UPLOADS_DIR`.
- **Tests and tools** — about 45 test files read `templates/`, `plugins/`, `error.html` or an entry through
  `dirname(__DIR__)` or `dirname(__DIR__, 2)`; `tests/bootstrap.php` and `phpstan-bootstrap.php` define the
  constants; `StructureTest` asserts the entries; `ParserFixturesTest` writes into an upload folder;
  `route_probe` writes the root sitemap; `tools/ui-audit.php` (`UI_ROOT`) with `tools/ui-contract.php`,
  `tools/upload-route-check.php` (`ROOTDIR`) and `tools/node-profile.php` read the moved paths;
  `tests/Support/tree_walk.php` walks the root. `tools/hooks/pre-commit` watches `templates/` and `plugins/` and
  follows; `.php-cs-fixer.dist.php` (`uploads`) and `phpstan.neon` (`plugins/*`) stay valid, and the stale
  `notPath('plugins/filemanager/…')` of the fixer names a folder that no longer exists; `package.json` and
  `phpunit.xml` name none.

Acceptance: with the root on `public/`, no file of the project outside `public/` is reachable over HTTP and no
upload is served except by the light path or the route of its owner; with the root on the project, the same holds on
Apache through the rewrite; every existing route, asset address and public upload address answers as before.

Tests: a built-in server on `public/` answers no file of `config/`, `storage/`, `uploads/node/` or `admin/`, and
every asset of a rendered page; the light path serves a file and its `thumb/` copy of a public folder with the type,
the `nosniff`, the sandbox and the cache headers, answers 304 to its `ETag`, serves an SVG as an attachment, and
refuses a private folder, a traversal, a missing name and a folder that is not public, without loading the core; a
static test reads the project-level `.htaccess` and asserts the rewrite into `public/` and the `mod_authz_core`
refusal outside `<IfModule mod_rewrite.c>`; `public/.htaccess` refuses `*.php` and `*.html` under `templates/`;
`getUploadUrl()` answers an address for a public path only; no file outside `public/` is named `.htaccess` except
the project-level one, and none outside `public/` is a guard `index.html`.

## Batch 3 — the installation proves its private part is private

The check that tells the operator, from inside the administration, whether the private part can be reached.

- **Write a marker.** One new file per watched root, `check.txt`, written at installation and never tracked in git.
  The name is plain — a leading dot invites a server rule that hides the marker while serving everything beside it,
  which is a false all-clear. Its body is a random string, so a copy of the delivery cannot be recognised by
  content alone. A marker that is missing is written again by the check before it asks, so a restored backup or a
  moved tree never reports `unknown` for that reason alone.
- **The watched roots.** The project itself, `storage/`, `config/`, the closed upload root `uploads/` and
  `admin/info`, each asked at the address it would have if the project root were served. With the root on `public/`
  they answer nothing; with the root on the project the check proves the rewrite works. The marker of the project
  (2026-10-06) proves the document root itself: it catches a root on the project that the server closes only folder by
  folder, which the four folders alone pass. The four folders hold the secrets and catch an alias of the server that
  opens one of them while the root is right. Every other folder of the project holds code, not secrets, and a host
  that keeps its code read-only would leave its marker unwritten and the check `unknown` for good. The marker of
  `uploads/` lies in no public folder, so the light path of batch 2 refuses it like any address it does not serve.
- **Ask over HTTP and judge by the body.** Request the marker through the configured site address with
  `getSchedulerFetch()` and compare the answer with the file. A status code decides nothing: an installation that
  maps its errors onto a CMS page can answer 200 with the error page or 404 with a body. The marker string is the
  only reliable signal.
- **Answer three states, never two.** `closed`, `open` and `unknown`. The third is for a transport that could not run
  at all: no stream wrapper, a loopback the firewall drops, a timeout, a name that does not resolve. Reporting an
  unchecked root as safe is the failure mode this batch exists to remove.
- **Say which root and which address.** An `open` verdict is only useful with the URL that proved it.
- **Run it on a schedule as well.** The scheduler owns the periodic run; the administration shows the last one
  beside the alerts of `checkPerms()`.
- **Be the one check.** `NodeService::checkTypeGuard()` judges a type folder by the status code today; it asks this
  check instead, and a closed owner is switched on only on `closed`.
- **Keep the transport seam.** The request goes through one replaceable function, so the three states are testable
  without a network.

Tests: three unit cases over a stubbed transport, one per state, asserting the verdict and the reported address. One
case proves a body-matching answer with a 404 status is still reported `open`, and one proves a CMS error page with a
200 status is reported `closed`. One case removes a marker and asserts the check writes it again; one runs the
scheduler job and asserts the verdict it stores for the administration.

## Batch 4 — a server configuration in the delivery

- **Ship the file.** `nginx.conf.example` at the project level: the root on `public/`, the front controller, the PHP
  handler, and a refusal of the template markup inside `public/templates/`, which PHP assembles and no browser
  fetches, and a `location ^~ /uploads/` that hands every upload address to the front controller ahead of any regex
  for static files, which is how the light path of batch 2 is reached. UPGRADING.md points at it instead of quoting
  rules of its own; the rule for Node upload folders already went with batch 2, and the `location ^~ /uploads/` and
  the document root it quotes since then move into the file. This plan owns that; 1-FILES-2026.md does not repeat it.
- **Keep it honest with a test.** Enumerate what inside `public/` is refused to the browser and assert the shipped
  file refuses the same. Without that test the file drifts, and a stale file is worse than none because it looks
  like protection.
- **Point at it from the administration.** The help of the security section names the file and the two modes.
- **`setup_old/` leaves the tree before the release.** The fragment does not cover it.

Tests: the enumeration test above, plus a syntax check of the file where an nginx binary is available; the
enumeration alone is the one that must always run.

## Batch 5 — names that say what a journal holds

**The rule.** `error_*.log` is what the system could not do. `<meaning>.log` is what happened. No name carries a
`log_` prefix: the folder `storage/logs/` and the extension `.log` already say it. The rotation archives follow the
same names instead of `log_user_…` and `dump_log_…`. Both rotations, `Logger` and `addCompress()`, name an archive
`<name>_<date>.log.<zip|gz|bz2>`, or `<name>_<date>.log.bak` where no compressor is available; `Logger` drops the
`.log` today and comes into line.
The files of the file scan take the name of its scheduler job, `filescan`, instead of `dump`, which reads as a
database dump.

| Now | Becomes | Carries |
| --- | --- | --- |
| `error_php.log`, `error_sql.log`, `error_site.log` | unchanged | failures |
| `error_file.log` | `error_file.log` | file operations at `error` and above, only there |
| — | `file.log` | file operations below `error` |
| `hack.log`, `warn.log` | unchanged | recognised attacks, refused requests |
| `log_admin.log`, `log_user.log` | `admin.log`, `user.log` | login attempts |
| `log_oauth.log` | `oauth.log` | OAuth events |
| `log.log` | `request.log` | the request journal of batch 1 |
| `database.log` | unchanged | database operations of the panel |
| `dump_log.log` | `filescan.log` | changes of the file tree |
| `dump.log` | `filescan_tree.log` | the snapshot of the file tree, `path\|\|md5` lines |
| `dump_map.json` | `filescan.json` | the state of the scan job |

**What the folder holds.** `storage/logs/` holds journals (`.log`), state (`.json`) and the rotation archives of
its journals beside them; the transient `*.rotating` guard of `Logger` and the temporary files of
`Cache::setBody()` exist only while a write runs. The state files already end in `.json` and stay: the scheduler
(`scheduler/<job>.json`, `heartbeat.json`, `trigger.json`), `monitor.json` and `filescan.json`. The locks leave for
`storage/cache/locks/`: `scheduler/<job>.lock` and the locks of `FileManager`, which live in `storage/logs/uploads/`
today. The cache sweeps skip every `*.lock` (`Cache::deleteStale()`, which `Cache::deleteAll()` runs), so clearing
the cache never removes a held lock. The guard
`index.html` and `.htaccess` files of `storage/` go with batch 2, like every guard outside `public/`.
`storage/counter/` is outside this rule and keeps its names.

**Route by level, not by outcome, and write each line once.** A refusal is not an error: a file rejected for its
format, a full quota, an extension that is not allowed — the system worked as designed and the record belongs in
`file.log`. `error_file.log` gets what the system could not do: a missing decoder, a partial that would not delete, a
stored file that survived a failed database write. `notice` and `warning` go to `file.log`, `error` and `critical`
to `error_file.log`, never to both.

**The writers follow the table.** `addFilescanTask()`, `write_log()` and the skip list of the scan write the new
names; the label map of the security section carries them and nothing else.

**The labels.** The keys of the label map in `admin/modules/security.php` follow the table; the constants keep their
texts in all six locales, and `file.log` and `oauth.log`, which has no label today, need one new constant each. The
readers that name the files follow too: `core/monitor.php` reads the admin journal and lists the journals it scans.

**The two dashboard counters.** `getErrorLogCountHours()` counts lines of a bracketed timestamp format that `Logger`
has never written, which is why the dashboard reports zero errors whatever the journals hold; it reads the structured
line and counts the problem levels. `getDbIssueEventHours()` scans `error_file.log` for database keywords and
reported a stored file named after a database dump as a database incident; once operations go to `file.log` that
line no longer reaches it.

Tests: a probe asserting a refused operation appears in `file.log` and not in `error_file.log`, and a capability
failure in `error_file.log` only. A test asserting every channel of `Logger` maps to a file and the label map covers
every file, and one asserting that every name the code writes into `LOGS_DIR` is a journal, a state file or a
rotation archive of the rule above.
A cache clear while a lock of `storage/cache/locks/` is held leaves the lock in place. A test over mixed structured
lines asserting the counter reports the problem levels only.

## Batch 6 — reference

The lasting part — the `public/` tree and its two modes, the one upload root and its light path, the self-check, the
shipped server configuration and the journal names — goes into `docs/ARCHITECTURE.md`, the help of the security
section and UPGRADING.md. An entry in `docs/VERSIONS.md`. Delete this file.

## Out of scope

- The content security policy of an installation. It is a property of the deployment, it breaks the editor when
  tightened blindly, and it has nothing to do with the data this plan protects.
- Rotation, retention and the format of the journals. Batch 1 changes what goes into a line, batch 5 the file it
  lands in and the name of its archive; neither changes how long it is kept.
- The journals of an existing installation: 8.0 is installed from scratch, and no file of the release renames or
  converts them.

## Open

Nothing.
