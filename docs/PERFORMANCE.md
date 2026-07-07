# SLAED Performance Guide

This document is the single performance reference for the current repository.
It describes confirmed current code paths, performance risks, and measurement
workflow.

## Status

- Current baseline: code-backed architecture map and risk register
- Rule: measure the current code path before assigning priority
- Scope: frontend, admin, template runtime, config bootstrap, changelog, GeoIP,
  tracking, blocks, scheduler, and security request overhead

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
with `cache_version = 1`. Only when that generated cache is missing or invalid
does it scan `config/*.php`, hash source config files, merge config arrays, and
rewrite `config/local.php`.

Implication:

- config scanning and hashing do not happen on every request when
  `config/local.php` is present and valid
- config cache invalidation still matters because admin config writes remove
  `config/local.php` and rebuild it
- direct edits to source config files are not re-fingerprinted on every request
  while `config/local.php` remains valid

### Page Cache

Frontend page cache exists, but current `config/global.php` has:

- `cache = 0`
- `cache_css = 0`
- `cache_script = 0`

Implication:

- normal frontend pages are rendered live in the current default config
- CSS/JS bundling cache is also disabled by default
- page-cache cleanup is implemented as `addCacheGcTask()` and is not a normal
  per-request full cache sweep

### Template Runtime

The active template engine is `core/classes/template.php`.

Confirmed costs and risks:

- every template render checks source/cache freshness with `filemtime()`
- every template file validation goes through `checkFile()`
- `checkFile()` calls `realpath($this->base)` and validates the target path
- high-fragment pages can produce many small filesystem checks, especially on
  Windows/NTFS

Recommended direction:

- add in-request metadata caches for `checkFile()`, `realpath()`, and `filemtime()`
- keep template validation semantics intact
- avoid deep template rewrites before cheaper request-path fixes are exhausted

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

### Frontend Tracking And Blocks

`setHead()` still performs live request work when enabled:

- session tracking when `conf['session']` is truthy
- referer tracking when `conf['referers']['refer']` is truthy
- statistic tracking when `conf['statistic']['stat']` is truthy

`setFoot()` still collects footer, left, right, center, and down blocks before
final page rendering.

Current config enables:

- `session = 1`
- `referers.refer = 1`
- `statistic.stat = 1`

Performance risk:

- monitoring and analytics are in the render path
- right/left block zones can add DB, template, and parser work before the final
  page can be emitted

Recommended direction:

- cache or batch tracking writes where correctness allows it
- avoid rendering invisible block zones
- cache stable block fragments with explicit invalidation
- profile each block before optimizing globally

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

### GeoIP

`Geoip::getInfo()` has request-local result caching, but `getMmdb()` reads the
entire MMDB file into memory with `file_get_contents()` and keeps it in a static
per-process cache.

Performance risk:

- first lookup per PHP worker can be expensive with large MMDB files
- cache is not shared across workers
- ASN and country databases can both be loaded

Recommended direction:

- use a shared cache if available, or
- read MMDB data through file handles and offsets instead of loading full files,
  if this code path becomes a measured bottleneck

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
small asynchronous trigger for due scheduler work.

Heavy system jobs include:

- database backup
- file scan
- sitemap generation
- page-cache cleanup

Current scheduler config enables database backup, file scan, and sitemap jobs.
Page-cache cleanup is defined but inactive in the current default config.

Performance risk:

- heavy jobs are not part of normal PHP render when they are executed correctly,
  but pseudo-triggered jobs can still compete for the same local PHP/IO resources

Recommended direction:

- prefer a dedicated scheduler trigger outside normal page rendering for heavy
  jobs
- keep file scan and backup under locks and progress state
- do not run heavy jobs synchronously in normal user requests

## Priority Register

| Priority | Area | Current confidence | Main risk | First action |
|---:|---|---|---|---|
| P0 | Changelog GitHub cache miss | High | seconds on cold/missing cache | stale cache + async refresh |
| P1 | Template filesystem metadata | High | repeated small IO on heavy pages | in-request metadata cache |
| P1 | Admin module/sidebar assembly | High | repeated module scan, icons, counters | request-local caches + `COUNT(*)` |
| P1 | Frontend tracking/block zones | Medium | live analytics/block work in render path | measure per block/track function |
| P2 | GeoIP MMDB load | Medium | expensive first lookup per worker | shared cache or file-offset reads |
| P2 | Asset discovery/bundling | Medium | disabled bundling and repeated file checks | enable/test asset cache |
| P2 | Security blocker scans | Medium | large lists and regex cost | parsed-list cache, no behavior change |
| P3 | Scheduler heavy jobs | Medium | background work competes with requests | dedicated scheduler trigger |

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

Font file names are static (for example `magistralb-wf.woff2`). A 1-year /
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
- Do not treat template IO as the only root cause; it is a persistent overhead,
  not the only proven multi-second source.
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
