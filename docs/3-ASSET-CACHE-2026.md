# Asset Cache 2026

Work plan for the browser cache of static assets: the browser keeps the styles, scripts, fonts and images of a
site for as long as they stay the same, and a changed file reaches every visitor on the next page.

Status: planned, nothing implemented. The order of the batches is kept in `docs/ROADMAP-2026.md`. Batch 2 writes
into the `public/.htaccess` and the `nginx.conf.example` of 0-PRIVATE-DATA-2026.md, so it lands after batches 2 and 4
of that plan. Update this line as batches land.

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
- The root `.htaccess` asks `mod_expires` for 30 days on CSS, JS and images and a year on fonts. On the stand nginx
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
| Files of the routes | `op=attach` of a stored material, `op=asset` | `private, no-cache` with ETag, as `getFileStream()` answers today |
| Previews and administrative downloads | `op=attach` with `preview=1`, the file view of the panel | `no-store`, as today |

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
highlight call of `Parser` needs `hljs`. The `go=file` route of 1-FILES-2026.md batch 4 answers like `op=attach`
when it lands.

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
