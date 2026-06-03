# Class Candidates Analysis

Analysis of procedural subsystems in SLAED core that would benefit more from being
encapsulated as classes than the scheduler family (which is intentionally kept
procedural — it is stateless).

## Methodology

A subsystem is a "class candidate" when it shows at least one of:

- **State / resource** threaded through several functions, or through one mega-function
- **Invariants** that should be protected behind a private boundary
- **Lifecycle / dependency injection** (built once, reused)
- **Shared private machinery** repeated across related functions

Source: full inventory of `core/system.php` (165 functions, ~248 KB), cross-checked
against the existing service classes in `core/classes/`.

## Background

`core/system.php` is a god-file with **165 functions**. Every existing class
(`pdo`, `template`, `parser`, `logger`, `geoip`, `captcha`, `editor`) is a
**stateful service or resource wrapper**. That is the bar for "make it a class":
real state, not just a namespace. A static-only utility class would be an
anti-pattern relative to how SLAED classes are built.

---

## Tier 1 — strong candidates (real resource/state through a mega-function)

### 1. Backup + Integrity-Dump — top candidate

- Functions: `addBackupTask()` (`core/system.php`, ~275 lines), `addFilescanTask()`,
  `create_dump()`, `write_dump()`, `diff_dump()`, `write_log()`
- **State via locals**: file handle `$fp`, table cursor, `$charset` / `$last_charset`,
  `$tab_type` / `$tab_charset` / `$tabsize`, counters `$bsize` / `$tabinfo`.
  `create_dump(..., array &$log, ...)` threads state **by reference** — a classic
  OOP smell.
- **Why a class**: a single 275-line procedure carrying ~10 locals is a textbook
  "replace long method with object". A `Backup` service encapsulates handle +
  config + progress with methods like `dumpTable()`, `compress()`, `finalize()`.

### 2. Sitemap

- `addSitemapTask()` (`core/system.php`, ~204 lines)
- **State**: accumulators `$map_h` / `$map_m` / `$map_c` / `$map_p`, nested maps
  `$info` / `$htm` / `$cd`, chunking at 50k URLs / 10 MB.
- **Why a class**: four buffers plus three maps live across the whole function.
  A `Sitemap` class with `collectModules()`, `buildUrls()`, `writeChunks()` would
  read far cleaner. The recent `count(null)` fatal here was a direct symptom of
  function length.

### 3. Stats / Tracking / Counter — a subsystem, not a function

- `updateSessionTrack()`, `updateRefererTrack()`, `updateSessionState()`,
  `updateStatsTrack()`, `getCounterField()`, `updateCounterField()`,
  `updateHoursField()`, plus bucket helpers
- **Shared resource**: counter log files (`COUNTER_DIR/*.log`) and the session DB
  table, read/written across all of them.
- **Why a class**: ~10 functions share one resource set and `$ctime`. This is a
  `Tracker($db, $conf)` service, not scattered free functions.

---

## Tier 2 — moderate

### 4. Editor upload → fold into the existing class, not a new one

- Procedural: `getEditorKey()`, `checkHtmlEditor()`, `getEditorMode()`,
  `getEditorJson()`, `getEditorUploadData()`, `checkEditorUploadAccess()`,
  `getEditorImageData()`, `getEditorFileData()`, `addEditorUpload()`,
  `getEditorFileJson()`
- A `class Editor` **already exists** (static, with manifests and drivers,
  `core/classes/editor.php`). These ~10 functions are the same domain, orphaned in
  `system.php`.
- **Action**: move them in as `Editor::*` methods — removes a split domain without
  introducing a new name.

### 5. Asset / Compress pipeline

- `checkCompress()`, `addCompress()`, `getCompressCss/Code/Html()`,
  `doScript()`, `doCss()`, `getThemeCssFiles/Assets()`, `getAssetFiles()`,
  `setScript()`, `setCss()`
- An `AssetBundler` service (theme + minification + cache). State is weaker
  (mostly transforms), hence Tier 2.

### 6. Comments and 7. Voting / Rating

- Comments: `ashowcom()`, `updateComment()`, `updateCommentStatus()`, `numcom()`
- Voting: `getVotingView()`, `updateVotingResult()`, `getRatingView()`,
  `update_points()`
- DB-backed subsystems with cohesive logic — medium-weight candidates.

---

## Tier 3 — weak (keep as functions)

- **Cart** (`getCartSummary()`, `addCartItem()`, `deleteCartItem()`) — little state
  (session), small.
- **RSS** (`rss_select/read/load`) — small feed fetch.
- **SEO / Head** (`setHead()` ~250 lines, `setFoot()`, `getSeoUrl()`,
  `getPublicUrl()`) — long, but rendering, not state; class payoff is low.

## Not candidates (correct as functions)

- **Scheduler** — stateless (see scheduler hardening work).
- Pure utilities: `format_time()`, `filterSlug()`, `getRandomString()`,
  `getConst()`, `is_user()`, `cutstr()`, `getProtocol()`, etc. — deterministic
  input → output; a class would be an anti-pattern.

---

## Recommendation (priority)

1. **Backup** — best benefit/risk ratio (long procedure + resource); indirectly
   reduces bugs like the recent sitemap fatal.
2. **Stats / Tracker** — tidies the most spread-out subsystem.
3. **Sitemap** — as a side effect makes the code resistant to null bugs.
4. **Editor functions → `class Editor`** — cheap, no new names, fixes an orphaned
   domain.

**Constraints (per project rules):** each is a separate, deliberate refactor on its
own branch/commit; an instance service with DI (`new Backup($db, $conf)`, like
`$db` / `$tpl`), **not** a static bag; all call sites migrated in one pass; and only
after current hotfixes reach production. This is a direction, not a local
optimization.
