# SLAED Performance Guide

This document is the single performance reference for the current repository.
It describes confirmed current code paths, performance risks, and measurement
workflow. Every durable performance fact lives here; time-bound audit and
remediation plans are separate documents and are removed once implemented.

## Status

- Current baseline: code-backed architecture map and measurement workflow
- Rule: measure the current code path before assigning priority
- Scope: frontend, admin, template runtime, config bootstrap, changelog,
  scheduler, security request overhead, and the versions, lifetimes, order and
  compression of static assets

## Current Request Flow

### Frontend

1. `index.php` defines the frontend context and loads `core/system.php`.
2. `core/system.php` loads config through `getConfig()`.
3. `core/security.php` starts the session, initializes language, creates the
   database connection, and runs request security checks.
4. The active theme hook and runtime classes such as `Template`, `Parser`,
   `Geoip`, `Captcha`, and `Cache` are loaded.
5. `setHead()` prepares SEO/head data, tracking, assets, login/header state,
   scheduler trigger, and theme variables.
6. The routed module renders content.
7. `setFoot()` collects blocks and renders the final page through
   `$tpl->getHtmlPage(...)`.

### Admin

1. `admin.php` loads `admin/index.php`.
2. `admin/index.php` loads `core/system.php`, disables cache headers, and checks
   admin access.
3. `setHead()` enters the admin branch and builds admin layout variables through
   `getAdminLayoutVars()`.
4. Admin pages render through `admin/modules/*.php` or `modules/*/admin/`.
5. `setFoot()` renders the admin page through `$tpl->getHtmlPage(...)`.

## Confirmed Current Facts

### Config Bootstrap

`getConfig()` first reads `config/local.php` if it contains `_meta` and `_config`
with a valid `cache_version`. Only when that generated cache is missing or
invalid does it scan `config/*.php`, hash source config files, merge config
arrays, and rewrite `config/local.php`.

Implication:

- config scanning and hashing do not happen on every request when
  `config/local.php` is present and valid
- config cache invalidation still matters because admin config writes remove
  `config/local.php` and rebuild it
- direct edits to source config files are not re-fingerprinted on every request
  while `config/local.php` remains valid

### Response Caching

Every page is rendered live on every request and its HTML is sent with
no-store headers (`Cache::setHeaders()`, mode `none`). Only the OpenSearch
description and the XSL stylesheet are sent public, and
`Cache::setHeaders()` drops every pending `Set-Cookie` whenever it emits
`Cache-Control: public`, so a shared proxy or CDN can never store one
visitor's stats cookie, locale cookie, or `PHPSESSID` and hand it to the next
visitor.

Cache cleanup runs as a scheduler task (`cachegc`), active by default, not a
per-request sweep: it removes data and compiled template files not
rewritten for `Cache::KEEP` seconds, one day.

### Parser Cache

`Parser::filterContent()` stores the finished rendering of one text under
`storage/cache/data`, so a news body, a page or a comment thread is parsed
once and read back on every later request. It is gated by the `cache` setting
(on/off, `Parser::checkCacheReady()`), and by nothing else.

- **Size threshold**: `Parser::CACHEMIN` is 2048 bytes of source, and
  `getCachePath()` returns an empty string below it, which gates the read and
  the write in one place. The reason is write amortization, not parse cost: a
  hit saves a fraction of a millisecond, a write costs about a millisecond,
  and a read never refreshes `mtime`, so with `CACHETTL` at one day every live
  entry is rewritten daily. Break-even sits near 700 bytes, but only at some
  thirty hits per day per fragment; from 2 KB a write repays after three or
  four hits, which is almost any traffic. Short comment lines and news teasers
  therefore never reach the disk, and the directory holds the few documents
  that are actually expensive to parse.
- **Identity**: the key carries the source hash, the safety flag, the module,
  the heading offset, the format, the theme, the locale, the mtime of
  `core/classes/parser.php` and a hash of every configuration value the class
  reads (replace rules, upload settings, file types, `homeurl`). A changed
  rule or a deployed parser retires every stored rendering without anyone
  clearing a cache.
- **Never stored**: a rendering that varies per request. `[block=id]` and
  `[usephp]` set `$this->vary` while parsing and `filterContent()` skips the
  write, whatever the size of the source.
- `storage/cache/data` also holds two smaller JSON caches that follow
  the same retention: the category map (`core/system.php`) and the MX lookup
  of the mail transport (`core/classes/mail.php`).

### Template Runtime

The active engine (`core/classes/template.php`) compiles templates to PHP on
disk (`storage/cache/templates/<theme>/`) with a stable content key and
request-local memoization (`$fresh`, `$rpath`). On a warm cache a template
render performs no template source reads and no re-compilation of template
content (a cheap name-validation `preg_match` per call remains) — roughly ten
stat-class calls on the first render of a file, then a plain `include` for
repeats. String-sourced
fragments (bodies of `{% block %}` / `{% slot %}`) are compiled to
content-addressed `inline-*.php` files; superseded ones are swept by the
scheduler `cachegc` task (`addCacheGcTask()` runs `Cache::deleteStale`
over `storage/cache/templates` with the same retention, `Cache::KEEP`, as
the data cache), so stale compiled templates are collected
instead of accumulating forever.

### Changelog

`config/changelog.php` is gitignored and per-installation; the values below are
the ones this development stand carries:

- `source = github`
- `limit = 500`
- `cachettl = 900`
- GitHub API timeout constants in `modules/changelog/common.php`

Current code behavior:

- initial GitHub cache rebuild is synchronous
- if a non-expired cache exists, it is returned directly
- if an expired cache exists and filters are plain, refresh can fall back to
  stale cached data on GitHub error
- local git mode is also cached

Performance risk:

- first request after cache miss can still block on GitHub network calls and JSON
  processing
- this path has the highest current risk of multi-second latency when cache is
  missing or expired

Recommended direction:

1. Do not rebuild GitHub changelog synchronously in a frontend request.
2. Use stale-while-revalidate: serve stale cache and refresh in scheduler/admin.
3. Keep high commit limits for admin/export; use a smaller frontend-home limit if
   changelog is used as a public page.
4. Add a short fallback path when no cache exists and GitHub is slow or unavailable.

### Admin Runtime

Admin layout assembly still performs live work:

- `getAdminPanelBlocks()` scans configured modules and checks admin entry files
- `getAdminPanel()` repeats module/menu assembly for dashboard panels
- `getAdminInfo()` issues per-module pending-content COUNT queries
- admin menu rendering resolves icon paths and template fragments repeatedly

Performance risk:

- dashboard and module pages pay for module discovery and sidebar counters
- admin DB time may be low while PHP/template/filesystem overhead remains visible

Recommended direction:

1. Cache module admin-entry discovery inside the request.
2. Cache resolved admin icon paths inside the request.
3. Cache language-loading state per module.
4. Convert pending-content counters from `SELECT id` patterns to explicit
   `COUNT(*)` where possible.
5. Optionally cache admin counter blocks for a short TTL.

### Security Request Checks

`core/security.php` always starts a PHP session and runs blocker checks. Request
logging is guarded by `conf['security']['log']`, currently `0`.

Current risks:

- large blocker lists increase per-request scanning cost
- GET/POST/COOKIE security scans run regex checks over request values
- security code should not be refactored for performance without regression tests

Recommended direction:

- cache parsed blocker lists if they become large
- keep raw security behavior unchanged unless the change is independently tested
- do not optimize away checks without current measurements and regression tests

### Scheduler And Heavy Jobs

Scheduler config has pseudo-triggering enabled. Frontend output can include a
small asynchronous trigger for due scheduler work. Heavy system jobs include
database backup, file scan, sitemap generation, and cache cleanup.

Recommended direction:

- prefer a real OS cron (`pseudo = 0`) so frontend renders skip the trigger
  file checks entirely
- keep file scan and backup under locks and progress state
- do not run heavy jobs synchronously in normal user requests

### Outgoing Mail

No outgoing message is sent inside the request that triggers it. There is one
entry point, `$mailer->addQueue()` in `core/classes/mail.php`, reached from 27
call sites in 17 files, and it does nothing but store a row in `{prefix}_mail`
and answer whether the queue accepted it. No caller learns a delivery outcome
synchronously.

Delivery is the `maildrain` scheduler job (`*/5 * * * *`, `lock_timeout` 900),
which runs `addMailTask()` and through it `Mail::updateQueue()`. `getBatch()`
claims due rows with one conditional `UPDATE` that stamps `locked`/`lockid` and
pushes `ntime` behind a lock window, so two runs can never take the same row and
a dead run's rows become claimable again when the window passes. A row that
exhausts its attempts stays in the queue as failed. A permanent refusal of the
recipient, answered in the SMTP dialogue or reported later by a delivery status
notification that the same job reads from the bounce mailbox over POP3, is
counted in `{prefix}_maildead`.
Failures are recorded through `Logger::addSite()`.

The measurements below predate that split. They are kept because the *ratio*
they establish is the durable fact and because they are what the queue was built
against; the absolute numbers describe a request path that no longer exists.
Measured on 2026-07-27:

- adding a comment took **26.7 s**, of which `addAdminMail()` was **26.6 s** and
  rendering **0.02 s**;
- that total was produced by **one** recipient, not many: `_admins` holds 3 rows
  and exactly one carries `smail = '1'` (`super = 1`, empty `modules`). An
  earlier reading of "13 recipients, ~2.05 s per call, 51 admins" does not hold —
  there are not 51 admins on this installation;
- so the figure is roughly **26 s for a single `mail()` call**, which is what a
  blocking connect to an unconfigured SMTP host looks like on this development
  host. It is a timeout artefact, not a per-message production cost.

Implication:

- the split is the durable fact — a synchronous transport dominates the request
  and rendering is free, which is why delivery moved to the scheduler. The
  absolute number is environment-specific and must be re-measured on a host with
  a working transport before it is quoted;
- any before/after comparison for mail work has to state which transport was
  configured, or it compares two different timeouts;
- the request cost of adding a comment is now one `INSERT` into the queue,
  whatever the transport is doing.

Newsletter throughput, same date:

- `{prefix}_newsletter.mails` is a comma-separated `MEDIUMTEXT`;
  `updateNewsletter()` (`core/system.php:3751`) slices `newsletter.count = 4`
  addresses per run and rewrites the remainder;
- the `newsletter` job is scheduled `1 * * * *` but ships `active = '0'`;
- 164 subscribers carry `users.newslet = 1`, so a full mailing at that rate would
  run about 41 hours;
- history: 62 mailings, largest delivered to 12775 recipients, no row currently
  mid-flight.

User base age, same date — this decides what a mass mailing actually costs:

- 11 845 accounts, registrations spanning 2005-04-30 to 2024-10-15;
- last visit within 1 year: 9; 1-3 years: 45; 3-5 years: 57;
  **older than 5 years: 11 734**; never: 0;
- one syntactically invalid address.

Implication:

- the "mass mail" audience in `admin/modules/newsletter.php:84-85` counts the
  whole user table, so it would send 11 734 messages to addresses dormant for
  over five years;
- a list that old hard-bounces at tens of percent, while providers throttle above
  roughly 5%. Sending it is a domain-reputation event, not a throughput problem;
- the numbers above describe **this** installation. Dormancy is not a reliable
  proxy for a dead address in general — a shop customer or a newsletter reader
  can be perfectly reachable without ever logging in — so the protection belongs
  in measurement, not in a usage heuristic: a canary batch before the full send,
  a hard-failure-rate circuit breaker during it, and suppression driven by
  recorded outcomes. None of the three needs inbound mail or an assumption about
  the project.

Address verification cost, same date:

- 11 845 addresses resolve to only **864 distinct domains**, so per-domain checks
  scale with the domain count rather than the list size;
- **330 of those domains (38%) no longer resolve at all** — neither MX nor A —
  and **902 addresses, 7.6% of the list**, sit behind them;
- resolving all 864 sequentially and uncached took **152 s**, which is why
  verdicts are cached per domain rather than recomputed per mailing.

Implication:

- a domain-level check removes 7.6% of this list before any SMTP connection, on
  its own crossing the ~5% band where providers start throttling;
- it removes dead providers, not dead mailboxes — a resolving domain says nothing
  about whether the address exists;
- a resolver failure must never be read as a dead domain, so verification fails
  open and the message is sent.

Comment storage, same date:

- `{prefix}_comment`: 7353 rows — files 4821, voting 1084, news 1083, faq 141,
  pages 116, links 104, shop 2, media 0; 7348 published, 3 pending;
- `_comment`, `_users`, `_voting` and `_newsletter` are all InnoDB.

The index gaps that reading found are closed. `{prefix}_comment` in
`storage/update/sql/table.sql` now carries `ip_time(ip, time, id)` for the flood check
and four composite indexes that back the list orderings —
`modul_cid_status_deleted`, `modul_cid_deleted`, `status_deleted_time` and
`modul_cid_pid_time` — plus a `UNIQUE(reqkey)` for double-submit. Re-measure
before quoting a filesort here.

### PHP Environment

The single largest generation-time factor measured in 2026-07 was OPcache being
disabled in the local OSPanel PHP config (`;zend_extension = opcache`): every
request re-compiled ~150-200 ms of PHP (core files plus compiled templates).
Verify OPcache is loaded in the web SAPI before profiling anything else; without
it, code-size growth translates directly into generation time.

## Static Assets: Versions, Lifetimes, Order And Compression

A browser keeps the styles, scripts, fonts and images of a site for as long as
they stay the same, and a changed file reaches every visitor on the next page.
The static files never reach PHP: the web server serves them and sets their
headers, PHP only prints their addresses.

### A version in every asset address

Every printer of a stylesheet or script address goes through
`Template::getAssetUrl()`, which appends the version of the file's content:
`templates/lite/assets/css/theme.css?v=0a5af6e4ae`, the first ten hex
characters of its SHA-1.

- The versions are the map `$conf['derived']['version']`, which
  `Template::getAssetVersions()` builds of every CSS and JS file below
  `templates/` and `plugins/` together with `config/local.php`. A request stats
  no asset file and hashes nothing.
- The head lists are `$conf['derived']['assets']`, plain addresses from
  `getAssetList()`: `css_f` or `script_f`, then the theme package of
  `getThemeAssets()`. `doCss()` and `doScript()` print them through the map, as
  do `Editor::getAssetTags()` with the editor skin, `Template::getAssetTag()`
  (the companion files of a partial), the highlight scripts of `Parser`, the
  altcha loader of `Captcha`, the module partials and the error page of
  `setExit()`.
- The highlight versions are part of the parser cache key, since a stored
  rendering carries their tags.
- `dev_mode` hashes per request, so an edit on a working copy shows at once;
  `setup.php` and `update.php` run without the derived configuration and hash per
  request as well.
- A file the map does not know is printed without a version and keeps the week
  of the table below. So do the files a stylesheet or a script reaches by a
  relative address: the fonts, the season images, the bootstrap-icons font and
  the runtime imports of `altcha-init.js`.
- A changed theme or plugin file gets its new version with the next rebuild of
  `config/local.php`: a save in the panel, `update.php`, or deleting the file.
  Until then the page keeps printing the old version, and the browser keeps the
  old file.
- The client loader `SlaedEditors` deduplicates by the exact address, so a page
  load and an htmx fragment print the same versioned address and an engine is
  fetched once.

### Lifetimes by kind

| Kind | Where | Cache-Control |
| --- | --- | --- |
| CSS and JS with `v=` in the query | `templates/`, `plugins/` | `public, max-age=31536000, immutable` |
| CSS and JS without a version, fonts, images, icons, sounds | `templates/`, `plugins/`, `sound/` | `public, max-age=604800`, revalidated by `ETag` |
| Public uploads | `uploads/` of a public owner, through the light path of `index.php` | `public, max-age=86400` with the validators |
| Pages | every route of PHP | `no-store` |
| Files of the routes | `go=file` of a stored target, `op=asset` | `private, no-cache, must-revalidate` with `ETag` |
| Previews and administrative downloads | `go=file` with `preview=1`, the file view of the panel | `no-store` |

The versioned case is told by its query alone: a request with `v` in the query
gets the year, any other the week. No upload lies under the document root, so
the light path sets the header of the public uploads itself.

**Apache.** `public/.htaccess` sets the two static rows through
`mod_headers`, guarded by `<IfModule>`: a `<FilesMatch>` on the style and script
extensions with an `<If>` on `%{QUERY_STRING}` (Apache 2.4), and a second one on
the fonts, images, icons and sounds. Without `mod_headers` no rule applies and
the browser guesses a lifetime from the validators. Neither that case nor
LiteSpeed reading the `<If>` block has been checked on a real server.

**nginx.** nginx reads no `.htaccess`; `nginx.conf.example` carries the same
rows. The choice between the year and the week is a server-level
`set $asset_cache` with `if ($arg_v)`, because an `if` inside a location would
drop its `try_files`; the two static locations send `add_header Cache-Control`
and pass a missing file to the front controller and its 404.
`tests/Unit/NginxConfigTest.php` walks every file of `public/` through both
files and keeps them in step.

### Order without async

`doScript()` prints every head script with `defer`, in the order of
`getAssetList()`: the scripts load in parallel and run in document order after
the parser, so `htmx` → `global-func.js` → `slaed.js` holds. There is no switch
for `async` or for moving the scripts to the end of the page.

An inline script that calls a function of a deferred file waits for it:
`Editor::getInitScript()` waits for `DOMContentLoaded` while
`window.SlaedEditors` is missing, so an instance of a page load registers its
teardown. The file-manager field of `getFileManagerField()` and the highlight
call of `Parser` need no wait, since their files are synchronous body scripts
printed before them. A new inline script that needs a head script waits the same
way or moves into that file.

### Compression

`public/.htaccess` deflates by response type (`AddOutputFilterByType`), which is
what covers the pages themselves: a page is built by `index.php`, so a rule
matching file names would never reach the largest response of the site.
`nginx.conf.example` turns `gzip` on for the same types; `text/html` is
compressed by nginx whenever gzip is on and is not listed in `gzip_types`.
WOFF2/WOFF, images and archives are already compressed and stay out.

### Measurement

Start page, a list and a material view on the stand (nginx, HTTPS, Brotli, the
migrated production database), Chromium of Playwright, 1366×900, guest, median
of three runs, 2026-10-07. Cold is a fresh browser context; warm is the same
context after `about:blank`, a normal navigation, not a reload. Bytes are the
encoded transfer of every request, headers included.

| Page | Load | Requests | From network | From cache | Bytes | FCP ms | DCL ms | load ms |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| start `/` | cold | 25 | 25 | 0 | 377 584 | 724 | 767 | 785 |
| start `/` | warm | 25 | 1 | 24 | 30 593 | 540 | 559 | 569 |
| list `?name=news` | cold | 24 | 24 | 0 | 354 380 | 424 | 420 | 426 |
| list `?name=news` | warm | 24 | 1 | 23 | 16 536 | 352 | 349 | 349 |
| view `?name=news&op=view&id=3918` | cold | 38 | 38 | 0 | 645 434 | 424 | 506 | 528 |
| view `?name=news&op=view&id=3918` | warm | 38 | 1 | 37 | 21 309 | 348 | 378 | 385 |

A warm page costs one network request, the page itself, and the browser keeps
the versioned files for the stated year: a release makes a returning visitor
fetch only the files whose address changed, on the next page. The view carries the toastui engine for the comment form of a
guest (about 233 KB of its 645 KB cold), a matter of the editor, not of the
cache.

### PHP error pages (nginx `fastcgi_intercept_errors`)

SLAED renders its own `404`/`403`/`503` responses in PHP through `setError()`
(and `500`, e.g. on a DB-connection failure): the correct HTTP status, a branded
message page (logo, title, home link, search form), and `Cache-Control: no-store`
so the error is not cached.

If nginx has `fastcgi_intercept_errors on` (or an `error_page` on the PHP location
that matches), nginx discards the PHP body and serves its own error page instead.
The HTTP status stays correct, but the operator loses the SLAED page, the
`no-store` headers are stripped (the error may then be cached), and the bare nginx
page leaks the server name.

Keep FastCGI errors from PHP passing through unchanged:

```nginx
location ~ \.php$ {
    fastcgi_intercept_errors off;   # let PHP-generated 4xx/5xx reach the client
    # ... existing fastcgi_pass / params ...
}
```

Two classes of 5xx need different handling:

- **App-emitted (PHP alive):** the app returns the status while it can still
  render — `404`/`403`, `503` (maintenance, captcha/limits), `500` (caught error
  such as a failed DB connection). These should pass through (`intercept off`) so
  the SLAED page and `no-store` reach the client.
- **Infrastructure (PHP not running):** `502`/`504` (PHP-FPM unreachable or timed
  out), `503` from a full FPM pool, or a hard PHP fatal. No PHP runs, so only nginx
  can answer — serve a static page scoped to server failures:
  `error_page 502 504 /error.html;`. nginx generates `502`/`504` itself, so this
  applies regardless of `fastcgi_intercept_errors`. The page (`/error.html` at the
  web root) is fully self-contained — inline `<style>` (real theme rules), inline SVG
  logo and icons, no external CSS/font/favicon/image — so it renders identically to
  the branded page even if every other file on the server is missing.

For `500` there is a trade-off: `intercept off` lets a caught `setError(500)`
render the SLAED page, but a hard fatal then yields whatever PHP emitted; a static
`error_page 500` guarantees a page but also overrides the caught case. Pick per need.

### Branded pages for nginx-native errors (`?error=`)

With `intercept off`, errors nginx raises itself (a `return 404;` guard, a truly
missing static file) still yield the bare nginx page. To brand those too, the
bootstrap in `core/security.php` reads `?error=NNN` and renders the SLAED page for a
whitelisted set (`400 401 402 403 404 500 502 503 504`); the whitelist keeps a forged
`?error=` from emitting arbitrary/invalid statuses. Point the server's error handler
at it for app-servable statuses, and keep `502`/`504` on the static file (`502`/`504`
are whitelisted only so a manual `?error=502` still renders a page when PHP is alive):

```nginx
error_page 400 401 402 403 404 500 503 /index.php?error=$status;
error_page 502 504 /error.html;
```

The same contract is mirrored for Apache in `.htaccess` (`ErrorDocument`), so the
behavior matches regardless of the web server in front of PHP.

### Working recipes

**aaPanel / 宝塔 (per-site, does not touch shared `enable-php-84.conf`):** in the
site's `server {}` block — `fastcgi_intercept_errors off;` is inherited by the PHP
`location` (which does not set it), so only this site is affected:

```nginx
server {
    # ...
    fastcgi_intercept_errors off;
    error_page 400 401 402 403 404 500 503 /index.php?error=$status;
    error_page 502 504 /error.html;
    include enable-php-84.conf;
    # ...
}
```

**OSPanel (dev, global for all PHP hosts):** the `friendly_errors.conf` snippet
(`modules/Nginx/conf/snippets/`) is included per host; add at its top so PHP errors
pass through while nginx-native errors keep the friendly page:

```nginx
fastcgi_intercept_errors off;
proxy_intercept_errors   off;
```

Note: OSPanel may overwrite this snippet on an Nginx-module update; re-apply the two
lines if branded PHP errors regress. After any change: `nginx -t && nginx -s reload`.

**Apache (`public/.htaccess`, shipped with the project):** Apache does not intercept
PHP-emitted statuses the way nginx does, so the equivalent is a set of
`ErrorDocument` directives — already present in the repo `public/.htaccess`:

```apache
ErrorDocument 404 /index.php?error=404
ErrorDocument 403 /index.php?error=403
# ... 400 401 500 503 likewise ...
ErrorDocument 502 /error.html
ErrorDocument 504 /error.html
```

## What Not To Assume

- Do not assume `getConfig()` scans all config files on every request when
  `config/local.php` is valid.
- Do not assume cache cleanup scans cache files on every request; current
  cleanup is a scheduler task.
- Do not treat SQL as the primary bottleneck without current measurements.
- Do not treat template IO as a bottleneck: on a warm cache the compiled
  engine performs no template source reads and no re-compilation.
- Do not refactor security checks for speed without focused security regression
  tests.

## Measurement Workflow

Before changing performance-sensitive code:

1. Record current config values that affect the path.
2. Test cold and warm request behavior separately.
3. Capture PHP generation time, SQL count/time, and full browser navigation time.
4. Check `storage/logs/` after admin or scheduler tests.
5. Preserve cache state in the report: empty cache, expired cache, fresh cache, or
   stale cache.
6. State whether the result was measured through browser, HTTP fetch, CLI include,
   or synthetic helper script.

Recommended focused targets:

- `/`
- `/index.php?name=forum`
- `/admin.php`
- one heavy admin module page, such as `admin.php?name=node`
- one changelog cache miss and one changelog cache hit
- one page with active right/left blocks
