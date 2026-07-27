# SLAED Performance Guide

This document is the single performance reference for the current repository.
It describes confirmed current code paths, performance risks, and measurement
workflow. Every durable performance fact lives here; time-bound audit and
remediation plans are separate documents and are removed once implemented.

## Status

- Current baseline: code-backed architecture map and measurement workflow
- Rule: measure the current code path before assigning priority
- Scope: frontend, admin, template runtime, config bootstrap, changelog,
  scheduler, security request overhead, and web-server static caching

## Current Request Flow

### Frontend

1. `index.php` defines the frontend context and loads `core/system.php`.
2. `core/system.php` loads config through `getConfig()`.
3. `core/security.php` starts the session, initializes language, creates the
   database connection, and runs request security checks.
4. The active theme hook and runtime classes such as `Template`, `Parser`,
   `Geoip`, `Captcha`, and `Cache` are loaded.
5. `setHead()` prepares SEO/head data, tracking, optional page-cache lookup,
   assets, login/header state, scheduler trigger, and theme variables.
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

### Page Cache

The frontend page cache is enabled for guests (`cache = 1`) and is safe for
visitor-bound content through the dynamic-regions mechanism:

- **Dynamic regions**: visitor-bound content (CSRF tokens, captcha, the whole
  voting widget) is stored in the cache as signed markers
  (`[[sldyn:type:par:hmac]]`, HMAC via `getSecret('dynreg')` so user content
  cannot forge substitutable markers) and substituted with freshly rendered
  content at serve time — on cache hits over `Cache::getBody()` and on misses
  before the direct echo. Cache files on disk contain zero live tokens.
  Emitters: `getPageToken()` (token-or-marker), captcha/voting ternaries in
  `setHead`, `blocks/login.php`, `blocks/user_info.php`, `blocks/voting.php`.
- **Default-deny allowlist**: `checkPageCache()` only caches route/op pairs
  explicitly allowlisted (currently `news` list); everything else renders
  live.
- **Bounded identity**: `getCacheRouteVars()` validates the request against a
  per-route parameter contract (`Cache::getQueryVars()`; for the `news` list:
  `name`, `op`, `cat`, `num`, with tracking keys dropped through the central
  list). Unknown, duplicate, or malformed query keys make the request render
  live and never create a cache entry. The cache identity (`getPageHash()`,
  identity version `pc2`) is built from the validated values plus the
  canonical `homeurl` host — `num=1` and the omitted page share one entry,
  alternate encodings collapse, and a foreign `Host` header cannot create a
  cache namespace. Pre-contract cache files are unreachable and removed by
  normal GC.
- **Marker contract**: only approved type/parameter combinations
  (`checkDynamicMark()`: `token` scopes `ajax`/`account`/`scheduler`,
  `captcha` action `login`, `voting` positive IDs) can be signed; an invalid
  emitter poisons the build, is logged, and falls back to live rendering.
  The contract is revalidated at serve time, so forged or stale markers stay
  inert.
- **Poison guard**: any live `getSiteToken()`/`getCaptcha()` call during a
  cacheable build marks the build poisoned (`checkCachePoison()`) and the
  page is never stored — unregistered visitor-bound content on an
  allowlisted route disables caching automatically instead of leaking.
- **Fail-closed sidecar**: every cached body has a `.json` sidecar with the
  body hash and a `dyn` flag. A missing, corrupt, or mismatched sidecar is
  treated as dynamic. Dynamic pages are served with no-store browser headers
  and never answer 304; only a valid `dyn=false` sidecar allows the public
  `cache_b` header branch.
- **No cookies on public responses**: `Cache::setHeaders()` drops every
  pending `Set-Cookie` whenever it emits `Cache-Control: public`, so a shared
  proxy or CDN can never store one visitor's stats cookie, locale cookie, or
  `PHPSESSID` and hand it to the next visitor. A first request that lands on
  a publicly cacheable page therefore receives no cookie until the next
  no-store response; exact counters are unaffected because they come from the
  server-side `ips.log`/`user.log` sets, and `?newlang=` requests are never
  cacheable. Existing sessions survive (PHP sends the session cookie only when
  it creates a new ID), and CSRF is unaffected because any page carrying a
  token or captcha is dynamic and therefore no-store.

Page-cache cleanup runs as a scheduler task (`cachegc`), not a per-request
sweep. CSS/JS bundling cache (`cache_css`/`cache_script`) remains a separate
setting.

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
scheduler `cachegc` task (`addCacheGcTask()` runs `Cache::deleteStaleTree`
over `storage/cache/templates` with the same `cache_t`-derived retention as
page, data, and lock caches), so stale compiled templates are collected
instead of accumulating forever.

### Changelog

Current `config/changelog.php` uses:

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
database backup, file scan, sitemap generation, and page-cache cleanup.

Recommended direction:

- prefer a real OS cron (`pseudo = 0`) so frontend renders skip the trigger
  file checks entirely
- keep file scan and backup under locks and progress state
- do not run heavy jobs synchronously in normal user requests

### Outgoing Mail

Every outgoing message is sent synchronously inside the request that triggers
it. There is exactly one send point, `mail()` inside `addMail()`
(`core/security.php:1047`), reached from 26 call sites in 16 files. The return
value is discarded and warnings are suppressed by a local handler
(`core/security.php:1044-1048`), so a failed delivery leaves no trace.

Measured and re-verified against the database on 2026-07-27:

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

- the split is the durable fact — mail dominates the request and rendering is
  free. The absolute number is environment-specific and must be re-measured on a
  host with a working transport before it is quoted;
- any before/after comparison for mail work has to state which transport was
  configured, or it compares two different timeouts.

Newsletter throughput, same date:

- `{prefix}_newsletter.mails` is a comma-separated `MEDIUMTEXT`;
  `updateNewsletter()` (`core/system.php:3747`) slices `newsletter.count = 4`
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
- indexes are `PRIMARY(id)`, `cid`, `uid`, `modul_status(modul, status)`,
  `time` — **nothing on `ip`**, while the flood check queries `WHERE ip = ?`
  (`core/user.php:274`);
- the live list query reports `type=ref key=cid rows=20`,
  `Extra=Using where; Using filesort` — no composite index backs the sort;
- `_comment`, `_users`, `_news`, `_files`, `_voting` and `_newsletter` are all
  InnoDB.

Remediation is planned separately in `docs/MAIL-2026.md` and
`docs/COMMENTS-REDESIGN-2026.md`; the facts above stay here when those documents
are removed.

### PHP Environment

The single largest generation-time factor measured in 2026-07 was OPcache being
disabled in the local OSPanel PHP config (`;zend_extension = opcache`): every
request re-compiled ~150-200 ms of PHP (core files plus compiled templates).
Verify OPcache is loaded in the web SAPI before profiling anything else; without
it, code-size growth translates directly into generation time.

## Static Asset Caching And Compression (Web Server)

SLAED ships to many users on different servers, so the safe defaults are split
between portable code (asset bundle headers in PHP) and per-server static-file
configuration that the operator must apply.

### Scope split

- The hashed CSS/JS bundle is served by PHP through `index.php?go=asset`. Its
  cache headers and gzip are handled in PHP and work on any server.
- Plain static files (images, fonts, direct `.css`/`.js`, `.svg`) are served by
  the web server itself. Their `Cache-Control`/`Expires` and compression are a
  server-config concern, not PHP.

### Apache / LiteSpeed

`.htaccess` already enables `mod_deflate` and `mod_expires` as a fallback:
text assets are compressed, images/CSS/JS get 30 days, fonts get 1 year. WOFF2
is intentionally excluded from compression because it is already compressed.
This works out of the box on Apache and on LiteSpeed in `.htaccess`-compat mode.

### nginx

nginx ignores `.htaccess`. Apply the equivalent in the server/location config.
The known production gap is that WOFF2 and some SVG have no `Cache-Control`.

```nginx
# Compression (the PHP bundle is served via index.php?go=asset, so gzip_proxied is required).
# Do not gzip woff2/woff — they are already compressed.
gzip on;
gzip_vary on;
gzip_proxied any;
gzip_comp_level 5;
gzip_min_length 1024;
gzip_types text/plain text/css text/javascript application/javascript application/json image/svg+xml application/rss+xml application/xml;

# Raster images — 30 days
location ~* \.(?:jpe?g|gif|png|webp|avif|ico)$ {
    expires 30d;
    add_header Cache-Control "public";
    access_log off;
}

# SVG (logos/icons may change) — 30 days
location ~* \.svg$ {
    expires 30d;
    add_header Cache-Control "public";
}

# Fonts — 1 year (safe once font URLs are versioned; otherwise rename on change)
location ~* \.(?:woff2?|ttf)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
    access_log off;
}
```

### Font caching caveat

Font file names are static (for example `magistral.woff2`). A 1-year /
`immutable` policy is only safe if the font URL is versioned (`?v=filemtime`) or
the file is renamed on change. Otherwise returning visitors keep the old font
after a replacement.

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

**Apache (`.htaccess`, shipped with the project):** Apache does not intercept
PHP-emitted statuses the way nginx does, so the equivalent is a set of
`ErrorDocument` directives — already present in the repo `.htaccess`:

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
- Do not assume page-cache cleanup scans cache files on every request; current
  cleanup is a scheduler task.
- Do not treat SQL as the primary bottleneck without current measurements.
- Do not treat template IO as a bottleneck: on a warm cache the compiled
  engine performs no template source reads and no re-compilation.
- Do not add visitor-bound markup to cacheable routes without registering a
  dynamic region or accepting that the poison guard disables caching for the
  route (see Page Cache above).
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
- `/index.php?name=news`
- `/admin.php`
- one heavy admin module page, such as `admin.php?name=news`
- one changelog cache miss and one changelog cache hit
- one page with active right/left blocks
