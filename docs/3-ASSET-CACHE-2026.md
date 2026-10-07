# Asset Cache 2026

Work plan for the browser cache of static assets: the browser keeps the styles, scripts, fonts and images of a
site for as long as they stay the same, and a changed file reaches every visitor on the next page.

Status: batch 0 done (2026-10-07): the inventory and the baseline stand below. Batch 1 done (2026-10-07): every
printer of the inventory goes through `Template::getAssetUrl()`, which reads the version from
`$conf['derived']['version']`, the map `Template::getAssetVersions()` builds of every CSS and JS file below
`templates/` and `plugins/` with `config/local.php` (`cache_version` 5); `getAssetList()` stays a list of plain
addresses, so the theme lists and the editor, parser, captcha and module files share one map. `dev_mode`, `setup.php`
and `update.php` hash per request; `doCss()` and `doScript()` stat no file; the robots screen lost its second tag of
`editor-robots.js`; tests in `tests/Unit/AssetVersionTest.php` with the probe `tests/Support/asset_probe.php`. The
order of the batches is kept in `docs/ROADMAP-2026.md`. Batch 2 writes into the `public/.htaccess` and the `nginx.conf.example` of
0-PRIVATE-DATA-2026.md, so it lands after batches 2 and 4 of that plan. Update this line as batches land.

No line numbers anywhere in this document on purpose: every reference names the function, the file or the constant
it points at, and that name is what to search for.

## What exists today

- Every page is rendered live and sent `no-store`; only the static files can be kept by a browser.
- `doCss()` and `doScript()` in `core/system.php` print one `<link>` or `<script>` per file of `getAssetList()`: the
  entries of `css_f` or `script_f`, then the theme package (`getThemeAssets()`), by its plain path:
  `templates/lite/assets/css/theme.css`. The list is built once with the derived configuration. No address carries
  a version, so a long lifetime would keep an old file in a browser after a release.
- The few answers PHP sends cacheable go through `Cache::setHeaders()` and its mode: `none` for every page,
  `private` for the files of the routes, `public` (`Cache::STATICDAYS`) for the OpenSearch and XSL descriptions.
- The editors load their own files through `Editor::getAssetTags()`; on an htmx fragment the client loader
  `SlaedEditors` adds what the page lacks.
- `public/.htaccess` asks `mod_expires` for 30 days on CSS, JS and images and a year on fonts. On the stand nginx
  serves the static files itself, so the Apache rule never applies: the answer to `theme.css` carries `ETag` and
  `Last-Modified` and no `Cache-Control` and no `Expires`, and the browser guesses a lifetime or asks again on every
  page. nginx gets no rule at all.
- `script_a` puts `async` on every script, which breaks the order `htmx` → `global-func.js` → `slaed.js` the site
  relies on. `script_b` was meant to move the scripts to the end of the page; on the site it drops the output of
  `doScript()` entirely and nothing prints it later. Both are the answers of an older web to a render-blocking
  script.
- Other printers of an asset address: `Template::getAssetTag()` (the companion CSS and JS of a template, already
  `defer`), the highlight scripts of `Parser`, the altcha loader of `Captcha` with its fragment `captcha-altcha.html`
  and the module imports `plugins/altcha/altcha-init.js` makes at runtime, the editor screen of
  `admin/modules/editor.php`, the error page of `setExit()` in `core/security.php`, `setup.php`, `update.php`, the
  favicon of `setHead()`, the image preload of `templates/lite/index.php` and whatever `config/header.php` prints.

## Inventory (batch 0)

### Printers of an asset address

All of them print through the two theme fragments `head-link` and `head-script-src`, except the altcha fragment, the
theme preload and the runtime loaders.

| Printer | Files | Attribute today | Batch 1 | Batch 3 |
| --- | --- | --- | --- | --- |
| `doCss()` | `css_f` + `getThemeAssets()` of the theme | — | version from `$conf['derived']['assets']` | — |
| `doScript()` | `script_f` + theme JS, then `config/header.php` | `async` by `script_a` | version | `defer` |
| `Editor::getAssetTags()`: drivers ckeditor, codemirror, tinymce, toastui; `getThemeSkin()`; `getFileManagerWindow()` (`plugins/system/filemanager.js`) | engine CSS and JS, skin, file manager | none: a parser-blocking script inside the body | version before the htmx list | stays in order with its init, see below |
| `SlaedEditors` in `plugins/system/slaed.js` (`getEditorScript()`, `setEditorStyle()`) | what an htmx fragment names | `async = false`; dedup by the absolute URL against `document.scripts` and the stylesheet links | compares the versioned URL, so the page and the fragment must print the same address | — |
| `Template::getAssetTag()` (companion `.css`/`.js` of a template) | `templates/<theme>/<base>.css` and `.js` | `defer` | version | — |
| `Parser` code block (`hljs`) | `plugins/highlightjs/highlight.min.js`, `highlight-line-numbers.min.js` | none, inside the content, followed by an inline call | version | inline call, see below |
| `AltchaCaptchaProvider::html()` + `captcha-altcha.html` | `plugins/altcha/altcha-init.js` | `type="module"` (deferred by nature) | version | — |
| `altcha-init.js` at runtime | `altcha.min.js` (static `import`), `altcha-sha.js` (worker), `altcha.css` (`<link>`) | relative to `import.meta.url` | unversioned: they stay on the week | — |
| `getRobotsButton()` in `admin/modules/editor.php` | `templates/admin/assets/js/editor-robots.js` | `defer` | the file is part of the admin theme package, so `doScript()` already prints it on every panel page and the robots screen runs it twice (two click listeners, the same value set twice); with a version the two tags become two addresses, so the own tag goes | — |
| module partial `script` key (`modules/presentation/index.php` → `partials/presentation.html`) | `plugins/presentation/presentation.js` | `defer` | version | — |
| `setExit()` in `core/security.php` | favicon, `getThemeAssets($theme, 'css')` | — | version | — |
| `setHead()` | favicon of the theme | — | stays on the week | — |
| `getTemplateLcpPreload()` in `templates/lite/index.php` | the season image | raw `<link rel="preload">` in PHP | stays on the week | — |
| `public/setup.php`, `public/update.php` | favicon, the admin theme CSS by `glob`; setup also `admin-ui.js` | `defer` on `admin-ui.js` | standalone, no derived configuration: the version per request, as `dev_mode` builds it | — |
| `config/header.php` | shipped empty | — | the operator's own | — |
| theme CSS | `base.css` → `fonts/magistral.woff2`, `theme.css` → `images/seasons/*.webp`, bootstrap-icons → its font with `?<hash>` | relative `url()` | unversioned: the week; the font query carries no `v` | — |

Apart from `captcha-altcha.html`, no template writes a `<link>` or `<script src>` of its own: the layouts print `{{{ links }}}` and `{{{ scripts }}}` in
the head (`lite/partials/site-header.html`, both `admin` layouts) or at the end of the body (`lite/layouts/bare.html`).
`doCss()` and `doScript()` call `file_exists()` for every listed file on every request although the list comes from the
derived configuration; batch 1 drops that stat as the design asks.

### Inline scripts that depend on a loaded file

| Inline script | Depends on | Under `defer` of the head |
| --- | --- | --- |
| `Editor::getInitScript()` (four drivers) | `window.SlaedEditors` of `slaed.js`; the engine global | `slaed.js` would run after it: `go()` runs at once, the engine is there (its body script is synchronous), but no teardown is registered and an htmx swap leaks the instance |
| field of `getFileManagerField()` in `core/helpers.php` | `window.SlaedFileManager` of `filemanager.js` | `filemanager.js` is a synchronous body script printed before it: unaffected. The field is rendered on page loads only today (`edithome()` of account, the resources of the Node form); in an htmx fragment it would skip itself, since `SlaedEditors.load()` fetches the file after the inline script ran |
| `Parser` code block | `hljs` | its two scripts are synchronous body scripts before it: unaffected while they stay synchronous |
| `Editor::getAssetTags()` on htmx | `window.SlaedEditors` | runs in a swap, after every page script: unaffected |
| scheduler trigger of `setHead()` | `fetch` on `load` | unaffected |
| JSON-LD | — | data, not a script |

The head scripts themselves need their order and nothing more: `slaed.js` touches `htmx` only inside functions and
starts on `DOMContentLoaded`; `presentation.js` and `admin-ui.js` wait for `DOMContentLoaded` as well; no template
carries an inline `on*=` handler; the top level of `global-func.js` only adds delegated listeners. The one real
conflict of batch 3 is therefore `getInitScript()`: batch 3 makes it wait for `DOMContentLoaded` when
`window.SlaedEditors` is missing, or moves the engine files of a page load behind `slaed.js`.

## Baseline (batch 0)

Measured 2026-10-07 on the stand (nginx, HTTPS, Brotli, the migrated production database), Chromium of Playwright,
1366×900, browser cache on, guest. Cold is a fresh context; warm is the same context after `about:blank`, a normal
navigation, not a reload. Median of three runs; bytes are the encoded transfer of every request, headers included; FCP is
`first-contentful-paint`.

| Page | Load | Requests | From network | From cache | Bytes | FCP ms | DCL ms | load ms |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| start `/` | cold | 25 | 25 | 0 | 376 576 | 692 | 714 | 749 |
| start `/` | warm | 25 | 1 | 24 | 30 449 | 536 | 552 | 562 |
| list `?name=news` | cold | 24 | 24 | 0 | 353 477 | 424 | 420 | 434 |
| list `?name=news` | warm | 24 | 1 | 23 | 16 483 | 300 | 294 | 295 |
| view `?name=news&op=view&id=3918` | cold | 38 | 38 | 0 | 643 884 | 472 | 575 | 576 |
| view `?name=news&op=view&id=3918` | warm | 38 | 1 | 37 | 21 047 | 364 | 427 | 441 |

- The warm hits are not granted by any header: nginx sends `ETag` and `Last-Modified` only, and Chromium takes the
  heuristic freshness of RFC 9111, a tenth of the age the file had when it was fetched (computed, not measured). The
  measured files were a day old, so Chromium keeps them about two and a half hours. Right after a release the files
  are minutes old and every asset is asked again (one round trip per file); a file untouched for half a year is kept
  about eighteen days by Chromium, and a change to it reaches a returning visitor
  only after that. This is the case the versions of batch 1 close.
- A page costs one network request warm, the page itself: whatever batches 1 and 2 change, the warm figure to keep is
  that one, now for a stated lifetime instead of a guess.
- The view carries the toastui engine for the comment form of a guest (`toastui-editor.all.min.js` 150 004 and its CSS
  83 581 bytes, 41% of the cold view) and every page carries `presentation.css` (23 795) of the theme package. Neither
  is a matter of caching; recorded for the editor plan and the theme package, not changed here.

## Design

### A version in every asset address

`doCss()`, `doScript()` and `Editor::getAssetTags()` print each file with a version of its content:
`templates/lite/assets/css/theme.css?v=3fa2c19d0b`. The version is the first ten hex characters of the SHA-1 of the
file. The list of the theme files and their versions belongs to the derived configuration that is built with
`config/local.php` (`$conf['derived']['assets']`), so a request stats no file and hashes nothing; `dev_mode` builds
the versions per request instead, so an edit on a working copy shows at once. A file the list does not know is
printed without a version and keeps the short lifetime below.

### Lifetimes by kind

| Kind | Where | Cache-Control |
| --- | --- | --- |
| CSS and JS with a version | `templates/`, `plugins/` | `public, max-age=31536000, immutable` |
| CSS and JS without a version, fonts, images, icons, sounds | `templates/`, `plugins/`, `sound/` | `public, max-age=604800` with the validators |
| Public uploads | `uploads/` of a public owner, through the light path | `public, max-age=86400` with the validators |
| Pages | every route of PHP | `no-store`, as today |
| Files of the routes | `go=file` of a stored target, `op=asset` | `private, no-cache` with ETag, as `getFileStream()` answers today |
| Previews and administrative downloads | `go=file` with `preview=1`, the file view of the panel | `no-store`, as today |

The web server sets these headers, not PHP: the static files never reach PHP. The versioned case is told by its
query: a request with `v` in the query gets the year, any other the week. The public uploads are the exception: no
upload is under the document root, and the light path of 0-PRIVATE-DATA-2026.md batch 2 sets their header itself.

### Order without async

Every script of the page head carries `defer`: it loads in parallel and runs in document order after the parser,
which is what `async` and moving the scripts to the end of the page tried to buy. `script_a` and `script_b` go.
An inline script that calls a function of a deferred file is moved into that file or waits for
`DOMContentLoaded`. Three exist today: `Editor::getInitScript()` runs the editor at once when `window.SlaedEditors`
is missing, so a deferred `slaed.js` would leave every instance without its teardown and an htmx swap would leak
it; the file-manager field of `core/helpers.php` skips itself when `window.SlaedFileManager` is missing; the
highlight call of `Parser` needs `hljs`. The `go=file` route of 1-FILES-2026.md batch 4 serves every
closed owner, Node included.

### Compression

`public/.htaccess` keeps its `mod_deflate` block; `nginx.conf.example` turns `gzip` on for the same types. Fonts in
WOFF2, images and archives stay uncompressed.

## Batches

0. **Inventory and baseline.** Every place that prints an asset address: `doCss()`, `doScript()`,
   `Editor::getAssetTags()`, the client loader `SlaedEditors`, the printers listed under "What exists today", the
   `<link>` and `<script>` written in templates, the theme hooks. Every inline script that calls a function of a
   file the page loads. Measure a cold and a warm page
   load of the start page, a list and a material view on the stand with the browser cache on: requests, bytes,
   time to first render.
1. **Versioned addresses.** `getAssetList()` returns each file with its version into the derived configuration,
   `dev_mode` per request, every printer of the inventory and the client loader. `Editor::getAssetTags()` and
   `SlaedEditors` deduplicate by the exact address, so the version is added inside `getAssetTags()` before it hands
   its list to htmx; otherwise a page load and a fragment load the same engine twice. Tests: every asset tag of a
   rendered page carries a version that matches the file; a changed file changes its address; a page renders
   without stating an asset file; an editor loaded by the page and again by an htmx fragment is fetched once.
2. **Lifetimes.** The rules of the table in `public/.htaccess` (`mod_headers`, guarded by `<IfModule>`) and in
   `nginx.conf.example`; the old `mod_expires` block of the root `.htaccess` goes. The public uploads keep the header
   of the light path, which already answers the table. Tests: a test reads both files and asserts every kind of the
   table served by the server; on the stand, a versioned CSS answers the year, a plain image the week, a
   page `no-store`. The answer of a server without `mod_headers` is checked by hand once.
3. **Defer.** `defer` on every head script, the inline scripts the inventory named, `script_a` and `script_b` out of
   the settings screen, `config/global.php` and the help. Tests: the scripts of a rendered page are deferred and in
   the shipped order; a saved settings form and a rebuilt `config/local.php` carry neither key; the editors, the
   quick edit, the windows and the htmx swaps work over real HTTP. Screenshot pair.
4. **Reference.** `docs/PERFORMANCE.md` and `docs/TEMPLATES.md` describe the versions and the lifetimes, the help of
   the settings the defer; the measurement of batch 0 repeated, into `docs/VERSIONS.md`. Delete this file.

## Out of scope

- Concatenating files into bundles, inlining styles or scripts into the page and embedding images into CSS: an
  HTTP/1.1 answer that defeats the browser cache, not part of the system.
- A CDN, a service worker and the browser cache of HTML pages, which need pages without per-visitor tokens.
- Preloading fonts or images: a separate measurement, once the lifetimes hold.
