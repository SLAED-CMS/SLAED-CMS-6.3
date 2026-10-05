# Private Data 2026

Work plan for the private data of an installation: no secret in a journal, a project tree whose private part the web
server cannot reach, a self-check that proves it, a server configuration in the delivery, and journal names that say
what they hold.

Status: batch 1 done 2026-10-05 (`JournalSecretTest`); batches 2-6 open. The order of the batches is kept in `docs/ROADMAP-2026.md`: batches 0 and 1
of 1-FILES-2026.md run between batch 1 and batch 2, so the Node types sit in `uploads/node/<type>/` before the tree is
split. Update this line as batches land.

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
keep them private with 94 tracked `.htaccess` files carrying `deny from all` (outside `setup_old/`), and `Captcha`
and `FileManager::getGuardFiles()` write more at runtime. Apache honours them; nginx never reads them, and nothing in
the delivery states the same intent in a form nginx understands. The installation has no way to notice.

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

The only measure no server misconfiguration can undo. It belongs to a major release, and 8.0 is that release.

**The tree.** The document root is `public/`. It holds what a browser fetches and nothing else:

| In `public/` | Stays at the project level |
| --- | --- |
| `index.php`, `admin.php`, `setup.php`, `update.php` as entry stubs | `core/`, `modules/`, `admin/`, `blocks/`, `lang/` |
| `.htaccess`, `favicon.ico`, `robots.txt`, `error.html` | `config/`, `storage/` |
| `sitemap.xml` and its parts `sitemap-N.xml(.gz)` | `uploads/node/`, the closed root of 1-FILES-2026.md batch 1 |
| `templates/`, `plugins/`, `sound/`, `demo/` | `tools/`, `tests/`, `docs/` |
| `uploads/` with every other owner, as today | |

`templates/` and `plugins/` move whole: no address of an asset changes and the theme package contract stays as it
is. The markup of the templates is not secret, but it is still refused: the rules of `templates/.htaccess` (`*.php`)
and of the theme folders (`*.html`) move into `public/.htaccess`, scoped to `templates/`, and batch 4 states the same
for nginx. `demo/` moves into `public/demo/`: its static pages link `../uploads` and `../templates`, which keep
resolving.

The upload owners other than Node stay in `public/uploads/` as they are: the forum and the private messages leave
it with their own batches of 1-FILES-2026.md, which close each one behind its route. `UPLOADS_DIR` names the closed
root at the project level and a second constant the public one, so no caller spells either path.

**One `.htaccess` per side.** The deny-all `.htaccess` files of the tree, the guard `index.html` files outside
`public/` and the guard files written at runtime — into a type folder by `FileManager::getGuardFiles()`, into
`CAPTCHA_DIR` by `Captcha::ensureDir()` — go; the guard `index.html` files inside `public/` stay. `public/.htaccess`
carries what the root `.htaccess` carries today plus the template refusals above.
One `.htaccess` at the project level serves the root-on-the-project mode: it rewrites every request into `public/`,
and where `mod_rewrite` is missing it refuses everything outside `public/` through `mod_authz_core`, so a host
without the rewrite gets a broken site rather than an open one.

**Two modes.** Both are supported and the installer records which one it found.

- *Root on `public/`.* The server points its document root at `public/`; nothing outside it exists for the web.
  This is the mode for nginx, whose server block sets the root anyway, and the recommended one everywhere.
- *Root on the project.* For a host whose document root cannot be moved, the project sits in the root whole and a
  `.htaccess` at the project level rewrites every request into `public/`. Apache and LiteSpeed only. A request for a
  file outside `public/` is rewritten into `public/` and answers 404 (2-PROD-FINDINGS-2026.md answers 410 for a
  `*.html` path and for a missing file under `uploads/`).

**One place decides.** `BASE_DIR` is the project, a new `PUBLIC_DIR` is `public/`. Every filesystem path into the
public part reads `PUBLIC_DIR` — the template, plugin, robots and sitemap paths and the public upload folders — and
every path that relies on the working directory being the document root is replaced by a constant. The entry stubs
set both constants and require the real entry from the project level.

**What follows the tree.** The PHP built-in servers of the tests and tools (`tests/Support/route_web.php` with
`route_probe` and `node_probe`, `install_probe`, `web_probe`, `FileStreamTest`, `tools/node-profile.php`) serve
`public/`. The stand points its document root at `public/`; the screenshot tooling runs against the stand and
follows without a change. The editor of `.htaccess` (`admin/modules/editor.php`) and `FileManager::CRIT` name the
files of the root and follow the tree. The administrative help, the security section and the documentation of this
repository print the paths of the `public/` tree.

Acceptance: with the root on `public/`, no file of the project outside `public/` is reachable over HTTP; with the
root on the project, the same holds on Apache through the rewrite; every existing route, asset address and upload
address answers as before.

Tests: a built-in server on `public/` answers no file of `config/`, `storage/` or `uploads/node/`, and every asset of
a rendered page; a static test reads the project-level `.htaccess` and asserts the rewrite into `public/` and the
`mod_authz_core` refusal outside `<IfModule mod_rewrite.c>`; `public/.htaccess` refuses `*.php` and `*.html` under
`templates/`.

## Batch 3 — the installation proves its private part is private

The check that tells the operator, from inside the administration, whether the private part can be reached.

- **Write a marker.** One new file per watched root, `check.txt`, written at installation and never tracked in git.
  The name is plain — a leading dot invites a server rule that hides the marker while serving everything beside it,
  which is a false all-clear. Its body is a random string, so a copy of the delivery cannot be recognised by
  content alone. A marker that is missing is written again by the check before it asks, so a restored backup or a
  moved tree never reports `unknown` for that reason alone.
- **The watched roots.** `storage/`, `config/`, the closed upload root `uploads/` and `admin/info`, each asked at the
  address it would have if the project root were served. With the root on `public/` they answer nothing; with the
  root on the project the check proves the rewrite works.
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
  fetches. UPGRADING.md points at it instead of quoting rules of its own; the rule for Node upload folders it quotes
  today goes away, because batch 2 took `uploads/node/` out of the root. This plan owns that removal; 1-FILES-2026.md
  does not repeat it.
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

The lasting part — the `public/` tree and its two modes, the self-check, the shipped server configuration and the
journal names — goes into `docs/ARCHITECTURE.md`, the help of the security section and UPGRADING.md. An entry in
`docs/VERSIONS.md`. Delete this file.

## Out of scope

- The content security policy of an installation. It is a property of the deployment, it breaks the editor when
  tightened blindly, and it has nothing to do with the data this plan protects.
- Rotation, retention and the format of the journals. Batch 1 changes what goes into a line, batch 5 the file it
  lands in and the name of its archive; neither changes how long it is kept.
- The journals of an existing installation: 8.0 is installed from scratch, and no file of the release renames or
  converts them.

## Open

Nothing. Batch 3 tests the plain marker name once against a stock panel configuration before it is trusted.
