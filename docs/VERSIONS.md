# Versions

## 2026-10-07

### A saved text keeps its line breaks in every editor format

`getTplTextarea()` deleted every `<br>` of a stored value before an editor saw it, for every format but `html`. The
`plain` save put them back through `nl2br()`; the `markdown` save of Toast UI, the editor of the site, did not. Every
member who saved anything on the account settings lost every line break of the signature and the custom menu, which
both show on every page: one save took the 22 breaks of a 1475-byte menu.

- **The mount.** A stored `<br>`, with the line end after it, mounts as a line end for `plain` and as a Markdown hard
  break, two spaces and a line end, for `markdown`; `html` mounts the value as it is. Measured in the browser through
  one save of the account settings: the menu keeps its 22 breaks as hard breaks and a second save changes no byte.
- **Stored form.** A value saved in `markdown` holds two spaces where it held `<br>`, two bytes less per break; every
  render of the parser, the escaped one of comments and the forum included, draws them as `<br>`, where an escaped
  render used to print a stored `<br>` as text. A line of nothing but a `<br>` becomes an empty line, so the lines
  around it render as two paragraphs.
- **Test.** `EditorBreakTest` drives stored values through the shipped mount, the browser submit and the save of both
  formats. The reference is `docs/EDITORS.md`, "Line Breaks on Mount".

### The private part of an installation is out of web reach, proves it every hour, and no journal holds a secret

The whole project sat in the document root and kept its private part with 95 `.htaccess` files of `deny from all`,
which nginx never reads; the login journal held the first 25 characters of every refused password and the request
journal the whole request with cookies and session. One server misconfiguration was a credential leak, and the
installation had no way to notice. The current state is in `docs/ARCHITECTURE.md`, "Private Data Boundary".

- **No secret in a journal.** `addLoginReport()` records who tried, under which login, from where and when, and
  rotates before it opens the file. The request journal is the channel `request` of `Logger` with its masking and
  only the names of cookies and session keys; `addLog()` and `getVariablesInfo()` are gone.
- **The document root is `public/`.** The entries, `.htaccess`, `robots.txt`, `favicon.ico`, `error.html`, the
  sitemap, `templates/`, `plugins/`, `sound/` and `demo/` are in it; the code, `config/`, `storage/` and the one
  upload root `uploads/` stay at the project level. Every entry defines `BASE_DIR` and `PUBLIC_DIR`, and no path relies
  on the working directory. A request under `/uploads/` reaches the light path of `core/stream.php`, which serves a
  public folder before the core boots and answers 410 for everything else; `getFileStream()` moved there with it and
  learned the public mode.
- **Two modes.** A root on `public/`, or on the project with the `.htaccess` of the project rewriting into `public/`
  and refusing everything without `mod_rewrite`; the installer records `webroot`. `nginx.conf.example` is the server
  block of the first mode, and `NginxConfigTest` keeps it refusing what `public/.htaccess` refuses. The help of the
  security section names the variants of shared hosting.
- **Guards gone.** Every `.htaccess` outside `public/` but the one of the project and every guard `index.html` outside
  it left the tree with their writers (`NodeService::setTypeGuards()`, `FileManager::getGuardFiles()`, the guard
  writer of `CaptchaStore`); the upload service creates a missing folder of its owner on the first write.
  `setup_old/` left the tree.
- **The self-check.** A marker `check.txt` in the project, `storage/`, `config/`, `uploads/` and `admin/info/`, asked
  over the site address every hour by the job `selfcheck` and judged by the body: `open`, `closed` or `unknown`. The
  home of the panel warns about a problem or a missing verdict, the security section shows the all-clear too, and a
  Node type is switched on only while `uploads/` is closed.
- **Journal names.** `error_*.log` is what failed, `<meaning>.log` what happened: `admin.log`, `user.log`,
  `oauth.log`, `request.log`, `filescan.log`, `filescan_tree.log`, `filescan.json`, and the new `file.log` for file
  operations below `error`. `Logger` and `addCompress()` name an archive `<name>_<date>.log.<ext>`; the locks moved into
  `storage/cache/locks/`. The dashboard error counter reads the structured lines and counts the problem levels, so it
  no longer reports zero whatever the journals hold.
- **Breaking:** the document root moves to `public/`; `addLoginReport()` lost its password parameter; `addLog()`,
  `getVariablesInfo()`, `FileManager::getGuardFiles()` and `NodeService::setTypeGuards()` are gone; the journals carry
  the names above and the label key `log` of the security section is `request`; `$conf['users']['adirectory']` is
  the folder name `avatars`. The journals of an existing installation are not renamed: 8.0 installs a new site.

### Every asset address carries the version of its content, the browser keeps it a year, and the head scripts run deferred

No static file carried a version, so a long browser lifetime would have kept an old file after a release. nginx sent
no `Cache-Control` at all and the `mod_expires` block of `public/.htaccess` never applied on the stand; a browser
guessed a tenth of a file's age, so right after a release every returning visitor asked for every file again, and a
file untouched for half a year stayed about eighteen days after it changed.

- **Versions.** Every printer of a stylesheet or script address goes through `Template::getAssetUrl()`:
  `theme.css?v=0a5af6e4ae`, ten hex characters of the SHA-1 of the file, from the map
  `$conf['derived']['version']` that `Template::getAssetVersions()` builds of `templates/` and `plugins/` with
  `config/local.php` (`cache_version` 5). A request stats no asset file; `dev_mode`, `setup.php` and `update.php` hash
  per request. The highlight versions are part of the parser cache key. The robots screen lost its second tag of
  `editor-robots.js`.
- **Lifetimes.** A style or script with `v=` in the query is kept `public, max-age=31536000, immutable`, any other
  static file `public, max-age=604800`, revalidated by `ETag`; pages stay `no-store`, the public uploads keep the day
  of the light path. `public/.htaccess` sets this through `mod_headers`, `nginx.conf.example` through a server-level
  `$asset_cache` and turns `gzip` on for the types the `.htaccess` deflates.
- **Defer.** `doScript()` prints every head script `defer` in the order of `getAssetList()`.
  `Editor::getInitScript()` waits for `DOMContentLoaded` while `window.SlaedEditors` is missing, so an editor of a
  page load registers its teardown.
- **Measured** on the stand, median of three runs, guest, start page / list / view against the baseline of the same
  day: a warm page costs one network request (the page) as before, now for a stated year instead of a guess; cold
  376 576 / 353 477 / 643 884 → 377 584 / 354 380 / 645 434 bytes (the `?v=` of every address), warm 30 449 /
  16 483 / 21 047 → 30 593 / 16 536 / 21 309 bytes, first paint warm 536 / 300 / 364 → 540 / 352 / 348 ms, within
  the spread of the runs. The current table is in `docs/PERFORMANCE.md`.
- **Breaking:** the settings `script_a` (async scripts) and `script_b` (scripts at the end of the page) left
  `config/global.php` and the settings screen, and `update.php` drops both from a carried 6.2 configuration. The
  `mod_expires` block left `public/.htaccess`; an Apache without `mod_headers` sends no lifetime. A theme or plugin
  file replaced by hand needs `config/local.php` rebuilt to reach the browsers.

### Retired addresses answer 404, 410 or 301, and the statistics append only what they are given

Two findings of the production logs of 2026-08-18 to 2026-08-21, neither of them the cause of that day's outage
(PHP-FPM had stopped, nginx answered 502).

- **A path that is no address answers 404.** The router read only the query, so `/news.html` of the long-retired
  `.html` scheme rendered the start page with 200 — 7058 requests in 2.9 days, almost all of them machines. Any path
  that reaches `index.php` other than the folder and its scripts now gets the standard 404 page; a real `.html` file
  is served by the server first, and a status an error document brings in `?error=` is kept. No table of the old
  scheme is built.
- **A path after a script answers 301.** `/index.php/index.php?name=sitemap` (1049 requests, from links Google
  keeps), `/index.php/` and `/admin.php/x` answer 301 to the script with the same query, or to the folder for a bare
  `index.php`. The target is built from the script name, which closes the open redirect of the old rule
  (`//other.host/index.php` sent `Location: //other.host/`); `nginx.conf.example` sends such a path to the script.
- **A retired file answers 410.** A missing file under `uploads/` and the direct address of a file of a closed owner.
- **`addFile()` writes data only.** It took its second argument as a source file or as the data and told them apart
  with `is_file()`. The visit statistics append an address or a user name followed by a comma, so every new visitor
  probed a path, which `open_basedir` logged as a warning (`File(141.148.184.29,) is not within the allowed path(s)`),
  and data that named an existing file would have appended the content of that file. `addFile(string $file, string
  $data, string $mode = 'w'): bool` writes or appends exactly the data; the compression branch had no caller and went
  with `$comp`, `$del` and `$max`.
- **Breaking:** `addFile()` returns `bool` instead of the codes `0`–`3` and drops its parameters `$comp`, `$del` and
  `$max`; a caller that copied a file reads it first.

### Every uploaded file leaves through the owner of the text that names it

Node closed its upload folders and served a file to the reader of its material; every other module linked its files
directly in open folders, so an attachment of a private message or of a post in a closed forum category reached
anyone who had its address. Every closed owner now has one route and one rights check (`docs/ARCHITECTURE.md`, "File
Delivery Boundary").

- **One route for every owner.** `index.php?go=file&own=<owner>&id=<target>&key=<name>` serves Node, the forum, the
  private messages, the comments on polls and profiles and the texts of an account; `FileAccess` asks the adapter of
  the owner whether the stored text carries the name and the reader may read the text. Every refusal is 404.
- **Closed folders.** `uploads/forum/`, `uploads/account/` (private messages alone), `uploads/voting/` and the new
  `uploads/profile/` (signature, own block, comments on a profile) answer 410 at their direct addresses, as
  `uploads/archive/` does; `uploads/all/`, `uploads/avatars/` and `uploads/presentation/` stay public.
- **Node under one root.** A type keeps its files in `uploads/node/<type>/` (`NODE_DIR`, `getUploadFolder()`), so a
  type can meet only another type there.
- **A signature renders the same everywhere,** in the file context of its account, on the forum, the profile, the
  private message and the comments; the comments printed its stored source unparsed until now.
- **No foreign file through a new text.** A writer binds a new `[attach]` name only when it is an own upload, the
  writer moderates the folder, or the same target already serves the name; a refusal names the file.
- **The rule governs the upload.** The extensions of an upload rule decide what may be uploaded, previewed and newly
  bound; a name a stored text carries is served whatever the rule says today.
- **Full-size attachments.** `[attach … size=full]` shows an image at its own size through the template `full` of
  `config/filetype.php`; the insert window of the editor offers "Thumbnail" or "Full size".
- **Unused files.** The file browser of the uploads screen filters a Node type, the forum, the private messages, the
  profile texts, the poll comments and the avatars to the files no stored row names, older than a day; the marking
  and the deletion of the browser remove them.
- **The update of `slaed.net`.** `update.php` rewrites stored texts and moves, copies and renames no file: a direct
  address of the own folder becomes an `[attach]`, a source inside `[usehtml]` the `go=file` address of its material,
  an address into the folder of another type or of the forum the `go=file` address of a published material or post
  that names the file. On a copy of the production dump of 2026-09-30, of the 721 file addresses of the stored texts
  657 became attachments, 24 `go=file` addresses, 9 point into public folders, and 31 answer 410, 21 because
  production does not hold the file and 10 by decision; the 1049 file references of the 231 pages that show them all
  answered 200. The upload folders move after the run; the steps are in `docs/NODE.md`, "The 6.3 update".
  `UPGRADING.md` keeps saying that the release installs a new site only.
- **Breaking:** `op=attach` of Node is gone; `uploads/forum/`, `uploads/account/`, `uploads/voting/`,
  `uploads/profile/` and `uploads/archive/` answer 410; `Parser::filterContent()` takes the file context `array $own`
  in place of `int $nid`; `uploads/jokes/`, `uploads/media/`, `uploads/pages/` and `uploads/node/files/temp/` left the
  shipped tree.

## 2026-10-05

### Every page is rendered live, and the ready-page cache leaves the system

A load test on the stand (nginx in front of eight PHP 8.4 FastCGI workers with opcache, 1000 guest requests per
page) showed the ready-page cache paying only on the lists of a type: 142 against 60 requests per second at a
concurrency of 16, 100 against 37 at 64. The material view (40 req/s) and the forum (47 req/s) were never stored,
a stored hit still cost about 56 ms of worker time, and a block printing a live token kept a page out of the cache
at random. The template, parser and data caches carry the expensive part of a page.

- **Gone with it:** the stored pages and their route contract, the signed dynamic markers of tokens, captcha and
  polls, the write guard around every content write, the cache generation moved by every database write, the
  deadline of a Node list and the single-flight rebuild.
- **Settings:** `cache` is an on/off switch of the parser cache; `cache_b`, `cache_l` and `cache_t` are gone; the
  `cachegc` job, now titled "Cache cleanup", removes files not rewritten for a day (`Cache::KEEP`).
- **Styles and scripts are linked one file at a time.** Joining them into bundles (`cache_css`, `cache_script`),
  inlining them into the page (`css_h`, `script_h`), compressing CSS (`css_c`) and embedding images into it as data
  URIs (`css_e`) are gone with the route `go=asset`: a browser keeps every file on its own, and one changed file no
  longer invalidates the rest.
- **Folders:** the caches live in `storage/cache/data/`; `storage/cache/pages/`, the guard journal and
  `storage/counter/cache.log` are no longer written. The changelog entries and the OAuth key sets moved from
  `storage/cache/changelog/` and `storage/cache/jwks_*.json` into the same data cache, so the cleanup job reaches
  them too.
- **One cache API:** `Cache::getFile()` names a stored entry, `Cache::deleteStale()` sweeps a directory, and
  `Cache::setHeaders()` takes the mode of the answer, `none`, `private` or `public`; the duplicate header
  `X-Powered-CMS` is gone. The query allowlist of an editor attachment lives in Node and refuses a tracking
  parameter like any other unknown key. One `getAssetList()` builds the linked styles and scripts of a theme.
- **The category map** is dropped explicitly by every writer of a category title, parent, order or icon.
- **Presentation:** the request path shows the parser cache, the compiled templates, the category map and the page
  built live.
- **Breaking:** `NodeException::BLOCKED`, the quick-edit result `blocked`, `getPageToken()`, `getPageCaptcha()`,
  `NodeQuery::getNodeDeadline()` and `setHead()` with a closure are gone; `Cache::getPath()`, `Cache::getHash()`,
  `Cache::getQueryVars()`, `Cache::setPrivateHeaders()` and `getAssetFiles()` are replaced as above.

## 2026-10-04

### One quick edit for comments, forum posts and Node materials

The quick edit of a comment and of a forum post were two separate paths over one helper, and Node had none. They
are one protocol now (`QuickEdit`, `getQuickService()`, the routes `getQuickEdit` and `updateQuickEdit`; see
`docs/ARCHITECTURE.md`, "Quick edit").

- **A refusal keeps the typed text.** Every refusal answers its own status and is told on the warning toast; the
  editor stays. An expired session answers 403 instead of an alert swapped over the text.
- **No silent overwrite.** A save carries the stamp the editor was opened with; a text changed meanwhile answers a
  conflict and asks once whether to overwrite. The repetition of a save whose answer was lost is saved again
  without a second write.
- **Cancel needs no request** and asks before it drops unsaved changes; Escape cancels, Ctrl+Enter saves.
- **The edited mark follows the save** for comments and forum posts.
- **The forum** writes under the write guard, with the topic and the right read again under the lock, so a topic
  closed or a post moved after the editor opened refuses the author; the page cache moves only after a write. The
  route `op=updatePost`, the route `op=updateComment` and `getTplAjaxTextarea()` are gone.
- **Node** offers the quick edit of the intro and the body on the public page of a type without an extension: to
  its moderator before the full editor, and to the signed-in author of a pending or published material for
  `limits.edit` seconds after creation (new key, default `600`, `0` off, on the limits tab; `update.php` adds it to
  an existing `config/node.php`). An author edit of a published material sends it back to moderation in the same
  write, unless the author publishes directly; the matrix of states gains `Published → Pending`.
- **Editor assets load once per page.** A fragment no longer carries the engine of an editor again: the client
  loader adds only what the page lacks, the init waits for the engine, and an editor swapped away is destroyed. On a
  page without an editor of its own the quick editor used to stay hidden.
- **Breaking:** `Comment::updateComment()` takes the stamp and answers a result code; `NodeException` gains
  `BLOCKED` (7, HTTP 503) for a closed write guard, which `setNodeWrite()` used to report as `STORAGE`.

## 2026-10-01

### One file installs a new site and deletes itself, and the 6.2 update leaves the release

The installer is one file, `setup.php`, and installs a new site only. The `setup/` directory, its key
`config/setup.unlock` and its update branches are gone; the update files of 4.1 to 6.2 are dropped.

- **Seven stops on the login card of the admin theme:** language, server checks, database with a probe, site,
  administrator, the run and the closing stop. Markup, styles and motion belong to the admin theme, the texts to the
  `_SETUP_*` constants of `admin/lang/*.php`.
- **The run in parts.** Configuration, the tables in groups, every seed statement and the administrator are separate
  requests, so the progress line shows how far the server really got. A part that broke its request off fails the
  run instead of running twice.
- **The first administrator** is created by the installer itself, with the ten Node types and the starter news.
  The form of `admin.php` for an empty admins table stays as the way back into the panel.
- **CSRF** is a random token in the session of the browser that runs the installation, carried to the end.
- **An installed site** — its database holds an administrator or does not answer — is refused without a write or a
  delete. The installer deletes itself only at the end of its own installation; a file that stays keeps the warning
  of the panel.
- **The language** chosen on the first stop reaches the panel: the installer sets the core cookie
  `{user_c}-language`, not the old `{user_c}-lang`.
- **The schema** moves to `storage/update/sql/`: `table.sql`, `insert.sql` and `table_update6_3.sql`.
- **The 6.2 update** moves into `update.php`, an internal tool of the maintainer that the release does not ship. A
  6.2 site is not updated by the release.
- **An administrator password** is hashed as typed in the installer, the recovery form and the admins module; the
  login still opens with a hash made from the old cut and escaped form. The recovery form carries a token.

### The migration of the old modules leaves one copy of every file, and every migrated image shows

A rehearsal on a copy of the production site found four faults of `update.php`, each fixed:

- **A stopped run.** The empty `index.html` placeholders of the old upload folders stayed in place, and Node took them
  for user files and refused the new type. Only the guard files of the release stay now.
- **Images replaced by `#`.** The converter wrote images and links as bare relative addresses like `uploads/x.png`,
  which the safe parser of Node replaces with `#`, so no migrated image showed. They now gain `./`. The
  entity-quoted attributes of old HTML are decoded first.
- **Duplicate files.** Every file went back into the closed type folder, and directly linked ones were copied into
  `uploads/archive/` as well, which left 270 duplicates. A file now has one place: the type folder holds what the
  materials use, the archive what the site links to directly. A file nothing uses stays outside the site in the
  working directory.
- **Broken outside links.** The forum, comments, private messages, newsletters, blocks, signatures and polls kept
  links into the folders Node closes. They are pointed at the archive as well, written as `./uploads/archive/...`.

### A comment of a Node material shows its attachments

A file uploaded into a comment of a material landed in the closed folder of the type and was linked there
directly, so it answered 403. A comment of a Node type now renders with the id of its material, and
`op=attach` grants the names its published comments carry, every comment not deleted for a moderator, with the
rights of the material. The preview of a fresh upload is open to whoever may upload into the type. The migration
keeps the attachments of comments and help replies in the type folder for the same route, so a private help
request keeps its files private.

The menu and footer of the lite theme link to `docs`, not to the removed `pages`.

## 2026-09-30

### The content of the removed modules moves into Node, and their old addresses follow it

`update.php` in the root carries `news`, `pages`, `faq`, `help`, `links`, `files` and `content` of a site that
ran the 6.3 update into Node types - `pages` into `docs`, `content` into a type without extension - with
categories, comments, favorites, rating balances, resources and files; the old tables stay. Only the main
administrator runs it, a stopped run continues from its manifest, and texts are converted from the trusted HTML
the old modules rendered into BB and Markdown. The new table `_node_legacy` maps every old address to its
material; the counter of `_nodes` continues above every old id, so an old address never names a new material,
and it answers 301 to the migrated material or list where it would otherwise meet a 404. A migrated material earns
no second publication award when a moderator publishes it again, since its module rewarded it once already.

The views and cards of Node show what the old modules showed: the share, author and moderator speed dials and the
anchor of the material, the chips of comments, author and downloads, favorites, rating, the category icon and the
fresh mark. The moderator dial of the site and the one of the panel list come from one function.

A new type of one of the nine replaced names now takes over the upload rule its old module left in
`config/uploads.php`, where it used to be refused as a taken name.

The category tiles of a Node list show the description of every category, as the old modules did;
`setCategories()` lost the two switches its one caller always set the same way.

### A forum post is acted on under the rights of the category it is stored in

The quick edit, the full edit, the reply, the deletion and the moderator actions of the forum took the rights of
the category the request named, so the moderator of one category could edit, delete, close or move the posts of
every other one. `getForumPlace()` now reads the category, the topic and the author of a named post from its rows,
and `checkForumRight()` is the one right over it that the handlers and the buttons of the view share: the
moderator of its category, or its signed-in author with the right of the category while the topic is open. A
reply always answers the topic of the post it names and needs the reply right, a guest no longer matches a post
written without an account, and a refused quick edit shows its alert instead of an empty body.

### A thumb vote bursts the thumb it pressed

The thumbs of the shared rating - a forum post, a profile, the rating under an avatar in the forum and the
comments, the user list - answer a counted vote with the burst of the favourite star, in the green or red their
hover promised.

### Code blocks are escaped in every highlighter mode

With `syntax = 2`, the shipped default, `[php]` and `[code=language]` handed their content to the page as
markup, so any author of a comment, a forum post or a material could store a script. The renderer moved into
the parser as `Parser::getCodeHtml()`; it decodes the entities of stored legacy text once and escapes in every
mode, drops only the editor line breaks before a line end instead of everything between the first `<br` and the
last `>`, and the highlighter mode joined the fingerprint of the parser cache.

## 2026-09-29

### The shop, order and service modules leave the package

`shop`, `clients`, `order`, `money`, `auto_links` and `whois` are no longer shipped: their module directories,
the block `auto_links`, their configuration files, upload directories, templates, language constants and tables
in `setup/sql/table.sql` are gone, and so is every hook the core kept for them — the cart route `go=2`, the
shop scope of comments, ratings and favorites, the points action `order`, the extra-field area `order`, the shop
feed, sitemap section and search source, the newsletter audiences of the three client lists, and the
administration counters of new clients, partners and whois requests. The referer log no longer links a visitor to
a link of `auto_links`, so `_referer.lid` is dropped, and the unread setting `amod` is gone from the global
configuration.

The `update6_3` branch leaves the tables and upload directories of these modules on an updated site untouched:
`table_update6_3.sql` no longer normalizes them, the ratings unit counts 6.2 shop rules as dropped, and the fields
unit converts account and forum fields only.

## 2026-09-24

### Node replaces nine content modules, and the 6.3 update carries a real 6.2 site over

`news`, `pages`, `faq`, `help`, `jokes`, `content`, `links`, `files` and `media` left the tree; one module,
Node, serves typed content in their place. A type is a row of `_node_types` plus its settings in
`config/node.php`, its extra fields in `fields.node`, its upload rule and its rating rule; `NodeQuery`
reads with the category rights of the visitor, `NodeService` writes in one transaction with a version
check, and two extensions carry the special cases: `support` for private requests of `help`, `sync` for
the external sources of `content`. Files of a type are served only through its controlled route, so a
type is switched on only when the web server refuses its upload directory. Ten shipped profiles in
`modules/node/profiles/` — the nine replacements and the new hierarchical `docs` — become active types
when the first administrator of a clean installation is created. Points, ratings and extra fields became
the shared classes `Point`, `Rating` and `Field`, and `Feed` fetches RSS and Atom for blocks and `sync`.

The `update6_3` branch of the installer closes the site, checks server version and table engines, runs
`table_update6_3.sql` and the resumable units points, ratings and fields with manifests under
`storage/backup/update/`, and leaves the marks in `config/update.php`. It now also reconciles
`config/modules.php` the way the modules screen does — the records of the removed modules go, `node`
gets the record of a clean installation — and drops the upload rules of the removed modules, which used
to refuse a Node type of the same name. It creates no types and touches no table of the removed modules.
Run on a dump of the stand taken before the data units, the fields preflight stopped on one order row it
could not map, wrote nothing, and finished after that row was corrected; the upgraded schema matches a
clean installation table for table.

The installer no longer needs `config/db.php` to exist, since the file is not shipped. MySQL 8.0 runs the
release: the columns `_users.rank` and `_groups.rank` are quoted wherever a query names them bare, because
`rank` is reserved there. The administrative material list of Node reads without the category,
relation and resource sets it never showed (`NodeQuery::setNodeSets(false)`), three statements a page.

`php tools/node-profile.php` builds a disposable database of 100000 materials in ten types with 200
categories, relations and resources, measures every route budget against `docs/NODE.md` (Performance), the p50 and p95
time and the plans of the main statements, and fails on a budget overrun or a full scan.

## 2026-09-11

### The presentation module renders the etalon home page from live figures, and the start page listens to its module

The etalon `demo/22-dashboard-layout-etalon.html` became `modules/presentation`: twelve
sections in a fixed order, each a partial of the `lite` theme with its repeated card as a
fragment, dressed by one stylesheet `assets/css/presentation.css`, moved by one plugin
`plugins/presentation/presentation.js`, and fed by PHP that hands over data and `is_*`/`has_*`
flags only. Every figure on the page has a source in the system: the request timing and
the memory come from `getLoadStats()` against the limits of the new `getLoadLimits()`, the
online figures from `getSessionCounts()`, today's visits and the day rows from
`getStatsToday()` and `getStatsDays()`, the table counts from `getTableCount()`, the commits
from the changelog module, the cache state from the cache config. Sites, brand materials,
principles and testimonials come from `config/presentation.php`, whose dictionary fields
name `_PRES_*` constants in six locales while proper names and quotes stay raw text.
The seventy-one site cards carry no address and open no window; the brand tiles open the
site lightbox through `a[data-sl-shot-open]`, the hook that replaced the dead `a.site-link`.

The server metrics left the admin monitor for `core/monitor.php`, and the scheduler samples
them once a minute through the `monitor` job at the lowest priority, so the public page
never runs `exec()` on a visit: it reads `storage/logs/monitor.json` and hides the section
while the sample is missing or older than five minutes. The guard section shows counts of
the last twenty-four hours and six static legend lines; the tail of the security logs stays
in the admin panel.

The start page now reads the `side` and `top` positions of its module like every named
route, so the block columns of the home page are set in `admin.php?name=modules`. This is a
breaking change for a site whose start module is `news` with `side = 2`: the empty value
used to mean both columns, and the left one disappears until the setting says `0`. A module
that prints its own `h1` raises `has_own_title` and `layouts/home.html` drops the site name
heading, so the page keeps one `h1` on either route.

The micro font step reads 11 px in both themes and the contract ladder follows: the
monospace labels of the page were unreadable at 10, and every page reading `--sl-font-micro`
grew by the same pixel. `setSpyRail()` gained the pixel mode of the presentation rail
(`data-sl-spy="px"`, `--sl-d-rail`), and the screenshot rig captures the presentation page
on its own, masks the shuffled site strip, bounds its wait for lazy images and pauses SMIL
animations before a shot.

## 2026-08-07

### One window and one icon insert an image, and a denied upload no longer means an unbounded one

The image dialog and the file catalogue are one window behind one toolbar icon. The
vendor image popup is removed from the toolbar, and what replaces it is our own markup:
the image address with its description, the file picker with its drag target, the three
insert modes, the stored-file list and the limits block, all siblings in one window
instead of two windows held together by measured geometry. Nothing in the editor reaches
into a popup it does not own any more, and the link dialog and the emoji window are
untouched.

The icon is there for every visitor. Inserting an image by its address touches no file
and no server, so it is available to everyone, always — and a visitor allowed nothing
beyond that opens the same window and finds the address field in it, which is an honest
answer where a missing icon would not be one.

A visitor who may not upload no longer gets a *worse* path than one who may. Dragging an
image onto the editor, pasting one from the clipboard and picking one in the window all
go through one bounded path from this release on: at most `Parser::EMBEDMAX` and only a
type the parser will draw. Until now the editor left that event to the vendor default
whenever uploading was denied, and that default base64-encoded any picked file straight
into the body — no extension check, no size check, no dimension check — so a
three-megabyte photo became four megabytes of base64 in the row and then did not display
at all.

### Whether a field may hold an embedded image is a property of the field

Every editor call site now names where its text is stored, and one table turns that name
into the room the storage has. A field wide enough for a whole embedded image offers the
embed mode; a summary, a signature or a link description does not, and offers the
address field instead. That is not a restriction on graphics: a linked image is accepted
in all of them, and it is the better delivery there anyway, because a list page draws the
summary of twenty rows and the browser fetches a linked image once instead of re-sending
a base64 copy twenty times.

A call site that names no storage renders a working editor with the room of `TEXT` and no
embed mode, so a forgotten one costs its author a button and never costs them a post.

The five image types the parser will draw have one definition, `Parser::EMBEDIMG`. The
render bound, the upload adapter and the editor read it from there, and a test fails if
they disagree.

### No stored text is longer than the column that holds it

One guard measures a finished text against the room its field declares, before the query
runs, and refuses with a message instead of letting the database answer `ERROR 1406` and
the author lose the post. It measures bytes and not characters, because bytes are what a
column bounds — in utf8mb4 a Cyrillic letter costs two of them, so a `TEXT` column runs
out at about 32700 Russian characters rather than 65535. That holds for plain prose as
much as for an embedded image: 70 KB of text in a news article was refused by the
database before this release and is refused with a message now.

The same guard measures what a text embeds, so the field rule is enforced and not merely
offered. A field that may not hold a data URI refuses one at any size; a field that may
refuses one over `Parser::EMBEDMAX` or of a type the parser will not draw. The editor
still refuses first, so an author is told at the moment they act, and a request that
never rendered an editor is told the same thing.

The one writer into a guarded column that has no author to tell is the content module: an
item with a feed address rewrites its body from whatever the far end served. A feed that
does not fit leaves the stored body untouched and writes the reason to the site log, since
a stale item is a better answer to a visitor than a lost one.

### The editor file panel is answered by the settings, not by the role

A guest allowed to upload now sees a file list. Until this release the editor listing
route refused every visitor who was not logged in, whatever
`admin.php?name=uploads&op=config` said, so a guest could upload a file under
`guestupload` and never see it again — not even their own, not even once. That rule is
gone: whether a list is answered at all is decided by the module upload settings and by
the module moderator, which is the one role standing above them.

A guest is shown the files of their own session and no other guest's, which rests on the
owner token below. The next session is a new session, and the files of the old one are no
longer listed.

The listing limit is chosen from three values instead of two. A moderator is bounded by
`moderfiles`, a member by `userfiles` and a guest by `guestfiles` — the field the release
below adds, which this one gives something to bound. Which of the three applies is the
only role question left on the route; what each of them is worth is a setting.

### A stored file belongs to a token, so one guest is no longer every guest

The owner segment of a stored upload name — the `-42` in `news-a1b2c3d4e5-42.png` — is an
alphanumeric token instead of a number. A member still owns their files by their user id
and a privileged upload still carries no owner segment at all, but a guest now owns theirs
by a token derived from the session rather than by the shared `0` every guest carried. Two
guests are therefore two owners, which is what a per-guest file list has to rest on.

The token is derived from the session and is never the session id itself, because the
segment ends up in a public file name and must authenticate nothing when it is read off a
URL. It lasts as long as the session: the next session is a new one, and the files of the
old one are no longer listed.

Files stored under the old pattern keep resolving, because digits are still a valid token,
and nothing already in `uploads/` is renamed or moved.

The per-guest file list above rests on this token: without it every guest would carry the
same owner and would see the uploads of every other guest.

### A link description is no longer one sentence, and the guest file list gets its own limit

`{prefix}_auto_links.intro` moves from `VARCHAR(255)` to `TEXT`. Those 255 bytes are
about 127 Cyrillic characters in utf8mb4 — one sentence for a field the site asks a
visitor to describe a whole site in. It joins the summary class rather than the body
class and stays `TEXT`: a link description is drawn once per row of a link list, which
is exactly the shape that must not carry an embedded image. It is the last column of
the schema work below that had not moved yet, and with it every column a rich editor
writes into is `TEXT` or `MEDIUMTEXT` and no `VARCHAR` sits behind an editor.

**Deployment:** a 6.2 upgrade and a fresh install carry the column already. No data is
lost or rewritten — the column only gets wider, and no stored description changes.

The upload rule of a module gains a thirteenth field, `guestfiles`, with its own input
on `admin.php?name=uploads&op=config` beside the two upload switches. A rule stored
before this release is one field short and keeps working untouched: the missing position
answers the user limit rather than zero, because zero means *no limit* to the reader and
would hand an unbounded list to the one role that never had one. The first save
normalises the rule to the full field order without changing a value it already carried,
and every save after that reproduces it.

The limit bounds the guest file list described above.

## 2026-08-05

### Private messages store source, and both fields are rendered safe

A private message is the source its author wrote from this release on. The body is
rendered by the parser with `safe = true`, the title is plain text escaped where a
template prints it, and no stored value is trusted HTML any more. **This release follows the state-model release below it and
must not be deployed before that one is live**, because it reads the columns that
release adds.

> **Superseded on 2026-08-06 by the content contract.** This entry originally
> shipped a `format` column on `{prefix}_privat` and a mandatory
> `tools/privat-migrate.php` run that rewrote stored bodies in place. Neither
> exists any more: the column is gone from all three SQL channels, the tool is
> deleted, and no text migration is part of any release. What survives is the part
> below — a message is stored as source and rendered safe — and it needs no
> conversion pass to be true.

A body is read through one contract. `plain` and the editors are input interfaces,
not storage formats, and nothing in the rendering branches on the editor an author
happened to type in. That is why no column names a syntax and why there is nothing
to classify: the same stored bytes render the same way whichever editor wrote them.

No deployment step is needed for this beyond the runtime code itself. The schema
work belongs to the state-model entry below, which stays exactly as it was.

What changes for the people using the site:

- A message is stored as it was written and escaped when it is read. Markup a
  sender types is text on the recipient's screen instead of live HTML, while
  Markdown, the bracket tags and the smilies still render.
- The editor an author writes in is an input interface and nothing more. An HTML
  editor is not a trust grant either: its markup is stored as source and escaped
  like any other, and the reading side never asks which editor produced a message.
- The subject line is plain text now. It carries no markup, it is stored decoded,
  and the template escapes it where it prints it — in the mailbox list, in its
  attribute and in the administrator panel alike. The panel used to decode it back
  on read to compensate for a writer that no longer exists.
- An administrator reads a message body through the same safe renderer its
  recipient does. Access to private-message contents in the `privat` section is a
  deliberate system policy, super-administrator only as before, and no raw stored
  body reaches a template on that path.
- One limitation the state model cannot repair either: a message
  either side deleted while both sides still shared one row is gone from the
  database and cannot be reconstructed for the other participant. That is recorded
  rather than papered over with a placeholder message.

### Private messages: four independent states instead of one shared column

The private-message subsystem is now one class, `Privat`, and one message carries
four state columns instead of the single `status` it shared between both
participants. **The schema section and the runtime code of this release are
deployed together.** An installation that applies the section while still running
code that reads `status` answers an SQL error on every private-message page until
the code follows it, and code deployed before the section does the same in the
other direction.

Which file an installation needs depends on where it comes from, and it is
exactly one of the two:

| Coming from | File | How |
|---|---|---|
| a new installation | `setup/sql/table.sql` | the installer, nothing to do |
| 6.2 | `setup/sql/table_update6_3.sql` | the installer, per the section below |

What the upgrade does to `{prefix}_privat`: it adds `saved`, `delin` and `delout`,
carries the saved messages over from `status` before that column is renamed to
`viewed`, forces `viewed` onto `TINYINT UNSIGNED NOT NULL DEFAULT 0` — the old
declaration was `BOOLEAN` while the code stored `2` — and replaces the three
single-column keys `uidin`, `uidout` and `status` with the composites
`in_box`, `in_new`, `out_box`, `out_new` and `flood`. It deletes no row and no
message, and the row count it starts from is the row count it ends on. It is safe
to run twice, and safe to run again after a crash: a re-run reads the shape the
table is really in and finishes only what is missing. Both files end on the same
table definition, byte for byte.

Building five indexes rewrites the table, and InnoDB holds the rows while it
does. On a large private-message table that is a maintenance window rather than a
page reload — the same rule the comment upgrade below already carries.

One documented consequence of the conversion: a message that was saved under the
old model becomes `viewed = 1`, because `status = 2` carried no read bit of its
own. Saving required opening the message, so read is the correct assumption.

What changes for the people using the site:

- A recipient deleting a message no longer removes it from the sender's outbox,
  and the reverse. Until now one delete destroyed the single shared row.
- A message the recipient saved stays visible in the sender's outbox, where it
  used to vanish the moment it was saved.
- A sender may delete an outgoing message the recipient has already read. It used
  to stay in the outbox forever.
- Saving no longer discards the read state, and a saved message no longer becomes
  undeletable for the sender.
- Read and unread are two actions now, not one transition, and inbox, saved and
  outbox carry bulk read, unread, save and delete.
- A send is refused when the saved folder of the recipient is full and not only
  when their inbox is, so the mailbox a message cannot fit into is the whole
  mailbox. Both quotas and the send interval are rechecked inside the write
  transaction, under a lock on both accounts, so two simultaneous sends can no
  longer both take the last free place.
- The notification mail reads the `psmail` preference of the recipient, which is
  the setting the account page has always offered and which had no effect until
  now — the code read the forum preference `fsmail` instead. Its link carries the
  id of the message that was really stored.
- Deleting an account now cleans its mailboxes in the same transaction that
  deletes the account, on both paths that delete a user row. The counterpart keeps
  a readable copy with the gone account rendered as an anonymous sender.
- The second administrator delete route, a GET carrying its token in the address,
  is gone. One POST route remains, super-administrator only as before.

## 2026-07-29

### Upgrade 6.2 → 6.3 must go through the installer, not through the SQL page

`setup/sql/table_update6_3.sql` is **not the whole upgrade**. Five steps of the 6.2 → 6.3
migration live in PHP, in the `update6_3` branch of `setup/index.php`, and pasting the
SQL file into **Database → Inquiry** performs none of them. The schema will look
correct — an upgrade of a real 6.2 database was compared against a fresh
`setup/sql/table.sql` install, table by table: 38 tables, 494 columns, 171 indexes,
engine and collation, zero differences, and the file is idempotent — but the data
around it will not be.

What the SQL file alone does **not** do:

1. **Administrator module permissions stay numeric and stop matching.** In 6.2 the
   `modules` column of `{prefix}_admins` held numeric ids from the `{prefix}_modules`
   table; 6.3 stores module names. `getAdminModuleNames()` (`core/system.php:998`)
   splits the column and does not translate ids, so every administrator who is not a
   super administrator silently loses **all** module permissions, comment moderation
   included. Only the installer rewrites the column.
2. **`config/modules.php` is not rebuilt.** 6.3 moves the module registry out of the
   `{prefix}_modules` table into that config file. The installer scans
   `admin/modules/*.php` and `modules/*`, folds in the old table and the existing
   file, and writes it.
3. **Pending newsletter recipients are lost.** The upgrade drops
   `{prefix}_newsletter.mails` (line 1696 of the SQL file), and it is the installer
   that reads those addresses first and writes them into the new mail queue
   afterwards. The SQL file does not carry them anywhere. Check before a manual run:
   `SELECT id, title, mails FROM {prefix}_newsletter WHERE mails IS NOT NULL AND mails != ''`
   — if that is empty, nothing is lost.
4. **The mail queue is never drained.** 6.3 stores outgoing mail instead of sending it
   inside the request, and the `maildrain` scheduler job is what delivers it. The
   installer seeds that job into `config/scheduler.php`; without it **all outgoing
   mail stops**.
5. **`config/newsletter.php` is not created**, so the campaign limits fall back to
   nothing.

The upgrade runs through the installer and nowhere else. The SQL page parses and
executes what it is given, but it performs none of the five PHP steps above, so a
schema pasted into it leaves the data around it wrong.

### Upgrade notes for the comment subsystem

Read this before running `setup/sql/table_update6_3.sql` on an installation with a
large comment table. **Take a dump of `{prefix}_comment` first and rehearse the
restore.**

- The upgrade adds five columns to `{prefix}_comment` (`pid`, `shown`, `edited`,
  `deleted`, `reqkey`), places `pid` directly behind `id` and `shown` behind
  `status`, fills `shown` from `time` for every published comment, makes `time` required, stores `ip`
  under a binary ascii collation, creates the index set the real list, count and
  thread predicates are read through, and drops the keys those supersede (`cid`,
  `modul_status`). No column named `format`, `iphash` or `path` is created, no
  comment rate table is built, and no idempotency key is minted for an existing
  row: a comment written before this release was never replayed and needs none.
- Two guards stop the run instead of guessing, and both stop it before anything
  else has changed: a `reqkey` found as hex text, which means the table was carried
  through a transitional release, and a `NULL` in `time`.
- **This is not instant on a large table.** Every index build rewrites the table,
  and InnoDB holds the rows while it does. It took under a second against 7358
  rows; on a table with orders of magnitude more it is a maintenance window, not a
  page reload. Close the site for it rather than letting a visitor discover it
  during a lock.
- **No text migration is needed and none is shipped.** A comment body is the
  source its author wrote and is rendered through one contract; nothing branches on
  an editor and no column names a storage format.
- The upgrade is idempotent: a second run changes neither schema nor data.
- Deleting a user no longer orphans their comments. The rows stay and lose only
  the reference to the account, so discussions and reply branches survive.
- **Repair the comment counters after the upgrade.** The `comments` column of the
  eight target tables is denormalised, and until 6.3 the counter could be moved for
  the wrong target by a request-supplied module name. The write path is fixed, the
  residue is not: on the reference installation 23 of 885 targets disagreed with
  their live count. Two ways in, both writing the same numbers:
  - `php tools/comment-recount.php report` reads only and prints every target that
    disagrees, `fix` writes the live count back;
  - the first tab of the comments section, which reports what is left and repairs it
    on a click.
  Both are safe to repeat: only rows that disagree are written, so a second
  run reports zero affected rows. None of them touches the comment table, and no
  user points are recalculated. Beyond them the counter maintains itself: every
  comment write recomputes the target it touched instead of nudging it by one.
- New setting `comments.reps` (default 5): how many replies a page shows under one
  comment before it offers to load the rest. It bounds what one long discussion can
  put in front of a reader; the remaining replies stay reachable both through the
  control and, without JavaScript, through the `&all=` link it carries.

### Fixed in the upgrade file itself

- `{prefix}_users`: the file modified `network` and then dropped it further down,
  so a re-run failed and discarded the type normalisation of thirteen other
  columns with it. The upgrade is idempotent for that table again.
- `{prefix}_admins.editor` was declared `BOOLEAN` while `setup/sql/table.sql`
  defines `VARCHAR(32) NOT NULL DEFAULT 'plain'`. It failed on any installation
  whose administrators carry an editor name, and would have destroyed those names
  had it passed.
- `{prefix}_users.points` was declared twice with two different definitions, and
  the second one undid the first. `table.sql` now agrees at `NOT NULL DEFAULT 0`.
- `SchemaUpdateValidationTest` compares column definitions between the fresh
  schema and the upgrade, and rejects a column declared twice with two
  definitions, so this class of defect fails a test instead of an installation.

## 2026-07-10
- Added the route-aware SEO head contract with safe HTML/JSON serialization, canonical and robots policy, Open Graph, and typed JSON-LD.
- Normalized frontend `H1-H3` ownership, card and voting contexts, comments, related content, tables, landmarks, and category breadcrumbs.
- Added scoped Markdown heading offsets for article, card, comment, block, and forum contexts.
- Added `SeoSemanticsValidationTest` and the executable `tools/seo-audit.php` HTTP contract audit.
- Corrected the English and Ukrainian Open Graph locale identifiers. `hreflang` remains disabled until languages have stable public URLs.

## 2026-05-20
- Responsive baseline closed for `lite` and `admin`.
- `lite` mobile/tablet layout fixed without changing the desktop visual style.
- Top menu, dropdowns, table wrappers, forum posts, comments, cards, media, and admin forms were verified after the CSS updates.
- Browser verification was completed with Playwright and Chromium.
- Authenticated admin pages were verified after login.
- Remaining component-specific backlog items are non-blocking: CodeMirror editor surface on the admin template page, statistic chart fixed-width images, and monitor widget internal widths.
