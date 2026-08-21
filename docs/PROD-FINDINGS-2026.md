# Prod Findings 2026

Work plan for the two items the production log audit of 2026-08-21 left open.
Neither of them caused the outage of that day: the site was down because the
PHP-FPM backend had stopped and nginx answered every dynamic request with 502.
Both items were found while reading the logs the outage made someone open.

Status: planned, nothing implemented. The two items are independent and can be
taken in either order. Update this line as they land.

No line numbers for this tree anywhere in this document on purpose: every
reference names the function, the file or the constant it points at, and that
name is what to search for. The one line number that does appear belongs to the
production release and is evidence, not a pointer into this repository.

## The evidence base

Both items come from two files copied off production into `storage/logs`:
`error_php.log` and `error_site.log`. The site log covers 2026-08-18 09:30 to
2026-08-21 06:36 — a window of roughly 2.9 days — and holds 14445 production
records. Anything quoted below is counted over that window.

Two cautions for whoever picks this up.

- **Production runs an older release than this tree.** The warning of item 2
  names `core/system.php` at a line whose content does not match this
  repository, and the function it lands in has no callers left here at all. Do
  not assume a finding on production maps one-to-one onto this working copy;
  confirm against the deployed release first.
- **Do not keep production logs inside `storage/logs`.** While the copies sat
  there, the local development site appended 21 of its own records to
  `error_site.log` — local mail-transport test noise, distinguishable only by
  the `+01:00` offset and the `127.0.0.1` address against production's `+02:00`.
  The next audit deserves a clean file.

## Item 1 — a 301 map from the old `.html` addresses

### Problem

The old URL scheme is still the one the outside world holds, and every one of
those addresses now answers 404. Over the window:

| Address | 404s |
| --- | --- |
| `/news.html` | 7058 |
| `/faq-cat-39.html` | 458 |
| `/files.html` | 204 |
| `/links.html` | 22 |

That is roughly 2400 wasted 404 responses a day on `/news.html` alone, spread
over 276 distinct addresses. The traffic is overwhelmingly machine: `curl/8.7.1`
(181), ChatGPT-User (260 across its two UA spellings), Amzn-SearchBot (147),
Google-Extended (99), PerplexityBot (85), OAI-SearchBot (85), GPTBot (67),
Amazonbot (45). These are the crawlers that decide what an assistant says about
the project, and what they are being told is that the news section does not
exist.

A second, smaller family has the same shape: 1049 requests to
`/index.php/index.php`, of which 187 are exactly `?name=sitemap`, plus 40 to
`/index.php/`. The referer on those is Google. It is path-info duplication in
links that were published once and are still being followed.

### Why they miss

`getSeoUrl()` in `core/system.php` joins its segments with `conf['sep']`, whose
default in `config/global.php` is `%2F` — today's SEF address is a slash-joined
path, and no branch of that function can emit a `.html` suffix. The rewrite in
`.htaccess` sends everything that is not a physical file or directory to
`index.php`, so the request does reach PHP; PHP then finds no route matching
`news.html` and hands back the 404 page through `ErrorDocument`. Nothing is
broken — the addresses simply belong to a scheme that no longer exists.

### What to do

Add a redirect layer that maps the retired scheme onto the current one and
answers 301, not 404. Two constraints on where it lives:

- It belongs in PHP, not in `.htaccess` or a server block. The portability rule
  for this project puts this kind of logic in the application so that Apache,
  nginx and LiteSpeed all behave the same, and production is nginx while this
  tree is developed under Apache.
- It runs before routing decides on 404, and it must not widen into a general
  rewrite engine. A finite table of retired patterns is the whole feature.

### Open

- **The exact old scheme is not established.** `/news.html` reads as
  `index.php?name=news` and `/faq-cat-39.html` as a `faq` category view of 39,
  but that is inference from the shape of two addresses, not a mapping anybody
  confirmed. Recover the real rules from the release that produced them before
  writing the table; a 301 to a wrong target is worse than the 404 it replaces.
- Which tree the fix targets: this one, the deployed release, or both. Only the
  deployed release is taking the traffic.
- Whether the `/index.php/index.php` family is worth a rule of its own or is
  better handled by one normalisation of a duplicated script segment.
- Whether any retired address maps to content that no longer exists, which would
  make 410 the honest answer for that subset rather than 301.

## Item 2 — `addFile()` receives a list of addresses instead of a path

### Problem

Production logged 15 identical-shaped warnings between 2026-08-14 and
2026-08-21, all under fingerprint `12e8afa9`, all at severity WARNING:

```
is_file(): open_basedir restriction in effect.
File(141.148.184.29,) is not within the allowed path(s):
(/www/wwwroot/slaed.net/:/tmp/:/proc/)
```

The address inside `File(...)` differs on every occurrence and always ends in a
comma. That is a comma-joined list of addresses being handed to a parameter that
expects a filesystem path. `open_basedir` is what makes it visible; without that
restriction the call would fail silently and the caller would read the failure
as "no such file".

The warning is raised at `core/system.php` line 2120 of the deployed release.
In this tree the nearest match is the opening `is_file($src)` guard of
`addFile()` — the same call, in a function whose `$src` parameter is documented
as a source file to read, copy or append.

Two facts that shape the work:

- **The caller is not in this tree.** Searching this repository for `addFile(`
  outside `tests/` returns nothing: the function survives here with no callers,
  so whatever passes it an address list exists only in the deployed release.
- **It fires early and on unrelated routes.** The captured requests are an
  account profile view, a users listing, a files listing, an article view and a
  junk query string, all at 2 MB of memory. Nothing ties the warning to one
  module; it looks like something on the common path of a request.

### What to do

Read `core/system.php` around line 2120 in the deployed release, walk up to the
caller, and establish what the address list actually is — a ban list, an online
list and a stats writer are all plausible and all would explain a comma-joined
set of client addresses. Then decide whether the caller is passing the wrong
argument or calling the wrong function.

Until the caller is identified this cannot be scoped further, and the stop
condition for this project applies: a root cause that cannot be mapped to a
file and a line is a reason to pause and ask, not to guess.

### Open

- Which release production is on, and whether the caller still exists in any
  currently shipped version or only in that one.
- Whether the swallowed failure has a visible consequence — a file that is never
  written, a list that never grows — or whether the call has been dead the whole
  time and the fix is to delete the caller.
- Whether `addFile()` itself should stay in this tree given it has no callers
  left here. That is a dead-code question, and it should be answered only after
  the deployed release is understood, not before.

## Out of scope

- The outage itself. PHP-FPM stopping is an infrastructure fault with no code
  change attached to it, and diagnosing it needs `php-fpm.log`, the nginx error
  log, `dmesg` and disk and inode usage — none of which are in this repository.
- The 403 responses on forum topics 3255, 3634, 11518 and 16315. `setError(403)`
  in `modules/forum/index.php` is doing what a closed section asks of it. That
  search engines keep re-crawling those topics is an indexing question, not a
  defect.
- Scanner noise. The bulk of the 404s and 69 of the 95 403s are probes for
  `/.well-known/*.php`, `wp-login.php` and similar. They are correctly refused
  and there is nothing to fix.
- The rejected registration address and the two 400 responses. One malformed
  address rejected once is the filter working.
