# Prod Findings 2026

Work plan for the two items the production log audit of 2026-08-21 left open. Neither of them caused the outage of
that day: the site was down because the PHP-FPM backend had stopped and nginx answered every dynamic request with
502. Both items were found while reading the logs the outage made someone open.

Status: item 1 done 2026-10-06 (step 7 of the roadmap), with the owner's change of its first rule recorded below;
the stand's `.osp/nginx/slaed.loc.conf` carries the same location of a path after a script. Item 2 open. The two items are independent; their order is kept in `docs/ROADMAP-2026.md`. Neither needs the code of
the release production runs. Update this line as they land; the step that lands the second item moves the lasting
part into `docs/VERSIONS.md` and deletes this file.

No line numbers for this tree anywhere in this document on purpose: every reference names the function, the file or
the constant it points at.

## The evidence base

Both items come from two files copied off production: `error_php.log` and `error_site.log`. The site log covers
2026-08-18 09:30 to 2026-08-21 06:36 — roughly 2.9 days — and holds 14445 production records. Anything quoted below
is counted over that window.

Production runs an older release than this tree, so a line number of its warnings does not map onto this working
copy. Production logs are not kept inside `storage/logs`: while the copies sat there, the development site appended 21
records of its own to `error_site.log`, distinguishable only by the `+01:00` offset and the `127.0.0.1` address.

## Item 1 — retired addresses

### Problem

Three families of addresses the outside world still holds answer 404 or will soon.

- **The `.html` scheme.** A scheme production retired long ago. Over the window: `/news.html` 7058 times,
  `/faq-cat-39.html` 458, `/files.html` 204, `/links.html` 22 — 276 distinct addresses, roughly 2400 wasted
  responses a day on `/news.html` alone. The traffic is overwhelmingly machine: `curl/8.7.1` (181), ChatGPT-User
  (260), Amzn-SearchBot (147), Google-Extended (99), PerplexityBot (85), OAI-SearchBot (85), GPTBot (67), Amazonbot
  (45). The ranking these addresses once carried is gone with years of 404; only the load is left.
- **A duplicated script segment.** 1049 requests to `/index.php/index.php`, 187 of them exactly `?name=sitemap`, plus
  40 to `/index.php/`. The referer is Google: path-info duplication in links published once and still followed.
- **Direct file addresses.** Production serves `uploads/<module>/<name>` directly today. After 8.0 the folders of
  the closed owners — Node types, the forum, private messages, the archive — are served only by their routes
  (0-PRIVATE-DATA-2026.md, 1-FILES-2026.md); no upload is under the document root any more, and the public owners keep
  their addresses through the light path of the front controller. Images of a closed owner indexed by search engines
  or linked from elsewhere stop answering.

### What to do

One layer in PHP, run by the front controller before routing decides on 404. It belongs in PHP, not in `.htaccess`
or a server block, so Apache, nginx and LiteSpeed behave the same. It is a finite set of rules, not a rewrite
engine. Node keeps `getNodeLegacyUrl()` for the old ops and ids of a migrated type; this layer covers what never
reaches a module.

- **A path that is no address answers 404.** Decided by the owner 2026-10-06, in place of a 410 for `*.html`: 8.0
  has no HTML pages, so `.html` is no special case. The site answers only at its folder and its scripts; any other
  path that reaches `index.php` — an old `*.html` address, a stray path, a missing file of a theme that nginx sent on
  — sets error 404 and gets the standard page through `setError()`. Before this the router read only the query, so
  `/news.html` rendered the start page with 200. A real `.html` file (`error.html`, the pages of `demo/`) is served
  by the server before PHP is asked, and a status an error document brings in `?error=` is kept. No table of the old
  scheme is built. Apache sends a missing path to PHP through the `!-f` rewrite and nginx through `location /`, so
  no `*.html` fallback is needed in `nginx.conf.example`.
- **The script segment is normalised.** A request whose path carries anything after the script that runs answers
  301 to that script with the same query string, or to the folder when the script is `index.php` and the query is
  empty. One rule covers `/index.php/index.php`, `/index.php/` and `/admin.php/x` alike, together with the bare
  `/index.php`, and replaces the guard at the top of `core/security.php` that forced `$_GET['error'] = 404` for every
  `PATH_INFO` request. The target is built from the script name, never from the path of the request, which closes
  an open redirect of the old bare rule (`//other.host/index.php` sent `Location: //other.host/`). nginx answers a
  path after a script with its own 404, whose `error_page` keeps that status over the 301 of PHP, so
  `nginx.conf.example` gains `location ~ ^(.+?\.php)/` that rewrites the path to the script itself.
- **Retired files answer 410.** Every refusal of the light path of 0-PRIVATE-DATA-2026.md batch 2 answers 410
  instead of 404: a missing file under `uploads/`, and the direct address of a file of a closed owner. No record maps
  an old address to a route (decided 2026-10-05): 8.0 is installed from scratch, and `update.php` updates the
  database only.

Tests: a `.html` path and a stray path answer the 404 page and a physical `.html` file is not touched;
`/index.php/index.php?name=sitemap` answers 301 to `/index.php?name=sitemap`, `/index.php/` and `/index.php` to `/`,
`/admin.php/x` to `/admin.php`, `//evil.example/index.php` answers 404 without a `Location`; a missing file under
`uploads/` and a file of a closed owner answer 410 and an existing public upload is served; an address of this tree
answers as before (`aRetiredAddressIsNotFoundOrMoved` of `NodeRouteTest`, `PublicTreeTest`); a static test finds the
location of the light path and the one of a path after a script in `nginx.conf.example` (`NginxConfigTest`).

## Item 2 — `addFile()` guesses whether it got a path or data

### Problem

Production logged 15 warnings of one shape between 2026-08-14 and 2026-08-21, fingerprint `12e8afa9`, severity
WARNING:

```
is_file(): open_basedir restriction in effect.
File(141.148.184.29,) is not within the allowed path(s):
(/www/wwwroot/slaed.net/:/tmp/:/proc/)
```

The cause is in this tree as well. `addFile()` in `core/system.php` takes `$src` as either a source file or the data
itself and tells them apart with `is_file($src)`. The visit statistics append to `ips.log` and `user.log` of the
counter folder through it, passing data — an address or a user name followed by a comma. `is_file()` on that data is
the warning; `open_basedir` only makes it visible. Worse than the noise: data that happens to name an existing file
is read from disk and the content of that file is appended in its place.

### What to do

`addFile()` writes or appends exactly the data it is given; the branch that reads `$src` as a file goes. Its two
callers, the visit statistics, pass data, and one that ever needs to copy a file reads it first. The parameters
`$comp`, `$del` and `$max` and the `addCompress()` branch they drive have no caller and go with it.

Tests: the `appendfail` mode of `tests/Support/contract_probe.php` and `StatsContractTest` grow the cases: appending
data that names an existing file writes the data, not the file; appending an address never calls `is_file()` on it;
the statistics keep counting new addresses and users once.

## Out of scope

- The outage itself. PHP-FPM stopping is an infrastructure fault with no code change attached to it, and diagnosing
  it needs `php-fpm.log`, the nginx error log, `dmesg` and disk and inode usage — none of which are here.
- A 301 table from the old `.html` scheme to the routes of Node, or from old file addresses to the file route.
  Decided against: it needs the old release to recover the scheme, and 8.0 keeps no map of retired addresses.
- The 403 responses on forum topics 3255, 3634, 11518 and 16315. `setError(403)` in `modules/forum/index.php` is
  doing what a closed section asks of it; that search engines keep re-crawling them is an indexing question.
- Scanner noise. The bulk of the 404s and 69 of the 95 403s are probes for `/.well-known/*.php`, `wp-login.php` and
  similar. They are correctly refused.
- The rejected registration address and the two 400 responses. One malformed address rejected once is the filter
  working.
