# Performance Audit Report

## Scope

This report summarizes the local measurements and code-path analysis for the
current SLAED install in `/mnt/c/OSPanel/home/slaed.loc/public`.

The review covered:

- frontend requests such as `https://slaed.loc/index.php?name=news`
- admin entry requests such as `https://slaed.loc/admin.php`
- admin dashboard and admin module paths
- core bootstrap, tracking, and block rendering in `core/system.php`
- admin bootstrap and layout helpers in `admin/index.php` and `core/admin.php`
- the GeoIP helper in `core/classes/geoip.php`
- the active config switches in `config/global.php`, `config/referers.php`,
  `config/statistic.php`, `config/scheduler.php`, and `config/security.php`

## Executive Summary

The slowdown is not caused by a single broken template or one bad module.
The real issue is that the runtime does a lot of global work on every request.

The main contributors are:

- `getConfig()` scanning and hashing config files on every request
- `setHead()` always triggering session, referer, and statistic tracking
- GeoIP lookup inside the statistics path
- `getBlocks('r')` on the frontend, especially the voting block
- `admininfo()` on the admin dashboard
- `getAdminPanelBlocks()` on admin module pages
- module-specific admin pages such as `modules/news/admin/index.php`

The frontend and admin areas behave differently, but both pay a large live cost
before they can finish rendering.

## Measured Request Times

### Frontend

Observed browser generation times for `index.php?name=news` were in the
roughly `0.46 s` to `0.50 s` range in the warm browser case.

### Admin

Observed `admin.php` browser behavior:

- without a valid admin session, the site shows the login shell
- with a valid simulated admin session, the admin bootstrap path is measurably
  heavier than the login shell

Local CLI timings from the same code path showed:

- admin login shell: about `75 ms`
- admin dashboard bootstrap: about `166 ms`
- admin module page `admin.php?name=news`: about `242 ms`

These are PHP-side request times, not full browser navigation totals.

## Frontend Findings

### 1. `getBlocks('r')` is the largest single frontend block

Relevant code:

- [`core/system.php`](../core/system.php)
- `getBlocks()` around line 522
- `getVotingView()` around line 2809
- `getUserSessionInfo()` around line 3347

Measured behavior:

- `getBlocks('r')` was about `23 ms` to `29 ms`
- a minimal baseline call such as `getBlocks('x')` was about `4 ms`
- `getVotingView()` was the biggest sub-cost in the right sidebar
- `blocks/user_info.php` was smaller, but still visible

Interpretation:

- the sidebar is not cheap decoration
- the voting block is the dominant payload in that area
- this is a good candidate for fragment caching or lazy loading

### 2. `setHead()` always triggers tracking

Relevant code:

- [`core/system.php`](../core/system.php)
- `setHead()` around line 1435
- `updateSessionTrack()` around line 945
- `updateRefererTrack()` around line 994
- `updateStatsTrack()` around line 1225

Measured behavior:

- `updateStatsTrack()` was usually in the `5 ms` to `11 ms` range
- `updateSessionState()` could be almost free or close to `9 ms`, depending on
  the current `sessions.log` state
- `updateSessionTrack()` and `updateRefererTrack()` were smaller, but still
  part of the live request budget

Interpretation:

- these are monitoring concerns, but they are executed synchronously during
  page rendering
- the runtime is paying for analytics on every request

### 3. GeoIP is a real contributor

Relevant code:

- [`core/classes/geoip.php`](../core/classes/geoip.php)

Measured behavior:

- first `Geoip::getCountry()` call in a request: about `3.7 ms`
- first `Geoip::getInfo()` call in a request: about `4.0 ms`
- repeated calls in the same request were effectively cheap

Interpretation:

- GeoIP is not the main bottleneck, but it is part of the hot path
- the current cache is request-local only
- if statistics stay enabled, GeoIP stays in the critical path

### 4. `getConfig()` is more expensive than it looks

Relevant code:

- [`core/system.php`](../core/system.php)
- `getConfig()` around line 26

Measured behavior:

- config bootstrap was around `4 ms` to `7 ms`

Interpretation:

- this is not the biggest cost, but it is repeated on every request
- `dev_mode = 0` stops fingerprint writes, but it does not remove the config
  scan itself

## Admin Findings

### 1. Admin bootstrap is separate and intentionally disables cache

Relevant code:

- [`admin.php`](../admin.php)
- [`admin/index.php`](../admin/index.php)

Important details:

- `admin.php` loads `admin/index.php`
- `admin/index.php` includes `core/system.php`
- `admin/index.php` sets cache to `0`
- `checkAccess()` runs in the admin path

Interpretation:

- the admin area is not a thin wrapper over the frontend
- it builds its own layout and its own admin shell

### 2. `getAdminLayoutVars()` is the main admin aggregator

Relevant code:

- [`core/admin.php`](../core/admin.php)
- `getAdminLayoutVars()` around line 257
- `getAdminLanguageLinks()` around line 204
- `getAdminTopMenu()` around line 225
- `admininfo()` around line 270
- `adminblock()` around line 3497 in `core/system.php`

Measured behavior:

- `getAdminLanguageLinks()` about `2 ms`
- `getAdminTopMenu()` about `3 ms`
- `admininfo()` about `29 ms`
- `adminblock()` about `5 ms`
- full `getAdminLayoutVars()` about `43 ms`

Interpretation:

- the admin shell spends a noticeable amount of time on live composition
- the heavy part is not the top menu or language links
- the heavy part is the live status and count aggregation

### 3. `admininfo()` is the biggest admin dashboard hotspot

Relevant code:

- [`core/admin.php`](../core/admin.php)
- `admininfo()` around line 270

Observed behavior:

- the function performs many separate count queries
- it checks several modules one by one
- it renders live blocks for pending users, FAQ, files, help, jokes, links,
  media, news, pages, shop, whois, and comments

Interpretation:

- this is a classic N+1 style dashboard pattern
- it is likely the largest single admin-dashboard optimization target

### 4. `getAdminPanelBlocks()` is expensive on module pages

Relevant code:

- [`admin/index.php`](../admin/index.php)
- `getAdminPanelBlocks()` around line 62

Observed behavior:

- when the admin panel is not in dashboard mode, the function scans module
  definitions
- it checks for admin files with `file_exists()`
- it loads module language data per active module

Measured behavior:

- module-page `getAdminPanelBlocks()` was about `67 ms`
- `getAdminLayoutVars()` on a module page was about `94 ms`

Interpretation:

- the module list is rebuilt live on each request
- this is expensive when the module list grows

### 5. The news admin page adds more work on top

Relevant code:

- [`modules/news/admin/index.php`](../modules/news/admin/index.php)

Observed behavior:

- the page loads a list of news rows
- it joins categories and users
- it formats dates and user info
- it calls `Geoip::getIpHtml()` for IP display
- it builds bulk actions, categories, and pager UI

Measured behavior:

- full `admin.php?name=news` request time was about `242 ms`

Interpretation:

- the admin shell is already heavy
- module pages add their own work on top of that

## Comparison With Version 6.2

The 6.2 snapshot in `/mnt/c/Users/eduard.laas/Downloads/slaed/slaed.loc`
is a useful baseline.

What changed in the current codebase:

- config handling became more unified and more dynamic
- statistics became more detailed
- session state became more expensive and file-based
- admin layout assembly became more helper-driven
- the sidebar and footer are more fragment-heavy

What stayed the same conceptually:

- the runtime still does a lot of live work per request
- global blocks are still part of the render path
- admin pages still assemble live counters and lists from the database

Interpretation:

- the regression is cumulative, not tied to one isolated line
- the cost comes from many synchronous subsystems stacked together

## What Looked Normal

These were not the main cause of the slowdown:

- the admin login shell itself
- the browser console and network state on `admin.php`
- `core/classes/template.php` as a constructor-only concern
- `core/security.php` as the single dominant bottleneck

`core/security.php` still runs on every request, but the larger measured costs
were elsewhere.

## Prioritized Fix Targets

1. Reduce or cache `getBlocks('r')`
2. Reduce `admininfo()` count-query work
3. Reduce `getAdminPanelBlocks()` module scanning
4. Move or cache `updateStatsTrack()` and `updateSessionState()`
5. Cache or decouple GeoIP lookups inside statistics
6. Rework `getConfig()` so config scanning is not part of every request
7. Review page-specific admin modules like news admin for extra query cost

## Bottom Line

The system is slow because it performs too much global synchronous work on
each request.

The strongest practical hotspots are:

- frontend right sidebar and voting
- frontend statistics and session tracking
- admin dashboard counters
- admin module scanning
- per-request config scanning

If the goal is to recover the earlier faster behavior, the best wins will come
from removing work from the live request, not from polishing the template
layer.
