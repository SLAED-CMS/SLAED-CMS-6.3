# Upload Field 2026

Take the file manager out of the editor plugin and make it the one way the whole
system asks for a file. Leader: `demo/up-02-window.html` — "One door". The form
keeps no file field; a button in the row opens that same window; what was picked
comes back as a chip.

The point is not the button. It is that "add a file" becomes one thing in the
whole system, and the storage of already uploaded files becomes reachable from
every place a file is asked for, instead of only from inside an editor.

Status: planned, nothing implemented. Third of three plans.

Written for the agent who executes it. **Every batch starts in a fresh session**
— see *Cold start*. Decisions under *Settled* were taken with the author and are
not reopened; if the tree contradicts one, stop and ask.

No line numbers on purpose: every reference names a file, function, fragment or
config key. A name survives drift; a number does not.

---

## Where this sits in the queue

Three plans run back to back:

1. `docs/FORM-FIELDS-2026.md` — the form row standard and its accessibility;
2. `docs/ACCOUNT-SETTINGS-2026.md` — the account settings page redesign;
3. **this one**.

### Git

Stage by **exact path only**. No `git add -A`, no `git add .`. Commit **only on
an explicit request from the author** — never at the end of a batch on your own
initiative, and never at the end of the plan without being asked. Message per
`.gitmessage`; no `Co-Authored-By`, no "Generated with". `.rules/git.md` governs.

### What plan 1 hands over, finished

New markup here is **born on that contract**, not retrofitted to it:

- `getFieldIds(string $id, string $mint = ''): array` in `core/helpers.php` —
  the `input` / `label` / `hint` triple. Ids come from it. Never hand-written.
- The `hint_html` row key; the hint no longer lives inside the caption.
- `.sl-value-row` / `.sl-value-label` / `.sl-value-text` instead of a `<label>`
  with no control. No `<label>` is emitted without a control.
- The row fold is a `@container` query. No new fold against the viewport.
- `npm run ui:label` with the tracked baseline `tools/label-audit-baseline.json`.
  It is **its own command, not inside `ui:gates`**; `pre-commit` runs it when the
  staged set touches the row, label or control fragments.

### What plan 2 hands over, and what it invalidates here

- `case 'saveavatar'` leaves `switch ($op)`. **The `saveavatar` route no longer
  exists.** Profile fields, avatar upload and preset pick travel in one POST to
  `savehome()`.
- The preset gallery is **a radio field named `avatar` inside the shared form**.
  It has no form of its own.
- The avatar upload row keeps `fragments/file-input.html` untouched on purpose,
  so batch 8 replaces **one row inside the avatar tile** and touches nothing
  around it.
- The `'input_id' => 'f-userfile'` defect is fixed there. **Batch 0 shrinks to
  the class miss alone.**
- **Its avatar arbitration wins over this plan's.** See *Settled*, decision 5.

`docs/PAGE-CACHE-ROUTES-2026.md` is not in this queue and does not overlap:
`files` caches only `list` and `view`, `account` is never cached.

---

## Cold start

Read `CLAUDE.md`, then the rule governing the batch — `.rules/theme.md` for
batch 3, `.rules/git.md` before touching the index, `.rules/global.md`
otherwise. Report per `.rules/report.md`. `tools/ui-contract.php` is tracked and
outranks the prose of `.rules/theme.md`; batch 3 edits it, which is expected.

Skills by batch: `manage-theme-tokens` (3), `manage-slaed-templates` (4, 7, 8),
`secure-inputs-and-forms` (2, 7, 8), `execute-test-suite` (any).

PHP tooling is invisible from Git Bash on this machine. Run from PowerShell:

```
php -l <file>
php vendor/bin/phpstan analyse
php vendor/bin/phpunit
php vendor/bin/php-cs-fixer check
npm run ui:gates      # ui-audit, ui-audit --markup, phpunit filtered
npm run ui:label      # separate; plan 1 added it
npm run ui:before / npm run ui:after    # needs https://slaed.loc/ answering
```

After editing anything under `config/`, delete `config/local.php` or the change
is not read.

### Which batch am I on

Nothing is committed between batches, so git will not tell you. One probe each,
from the repo root. A hit means that batch is **done**:

| Batch | Probe | Done when |
|---|---|---|
| 0 | `grep -l sl-field-auto templates/*/fragments/file-input.html` | no match |
| 1 | `grep -c getUploadPlaceRule core/system.php` | non-zero |
| 2 | `grep -c "'place'" index.php` | non-zero — the entry guard is the load-bearing half |
| 3 | `grep -c sl-fm-win templates/lite/assets/css/theme.css` | non-zero |
| 4 | `ls templates/lite/partials/file-manager.html` | exists |
| 5 | `ls templates/lite/partials/file-manager-templates.html` | exists — written last in the batch, so the script move alone never reads as done |
| 6 | `grep -c addField plugins/system/filemanager.js` | non-zero |
| 7 | `grep -c "getHtmlFrag('file-input'" modules/files/index.php` | zero |
| 8 | `grep -c "getHtmlFrag('file-input'" modules/account/index.php` | zero |
| 9 | `ls templates/lite/fragments/file-input.html` | missing |

Run them top down; the first not-done row is your batch. If `git status` is
clean and probe 0 says not-done, the plan has not started.

---

## The mechanism that already exists

Written and good. It needs unpacking, not inventing.

| Layer | Where | State |
|---|---|---|
| Window markup | `templates/{lite,admin}/partials/editor-toastui-files.html` | complete, both themes byte-identical |
| Geometry | `--sl-fm-*` in `assets/css/base.css` — 20 in lite, 28 in admin | complete; the 8 extra are the browser's, see below |
| Draw templates | `partials/editor-toastui-templates.html` — 11 the runtime needs, 4 the emoji panel needs | complete, delivered by the editor driver |
| Rules | `assets/editors/toastui/skin.css` — 143 of 299 selector lines are the window | all under `.sl-toastui-upload` |
| Behaviour | `plugins/editors/toastui/assets/editor-upload.js`, entry `api.addUpload(id, ed, opt)` | bound to an editor instance |
| Delivery | `plugins/editors/toastui/driver.php` (script), `Editor::getThemeSkin()` (skin) | only where an editor is |
| Server | `op=editorUpload`, `editorFiles`, `editorDelete`, `editorArchive` | complete, keyed by `mod` |
| Route guard | `getEditorRouteRule()` — rule, visitor right, token | complete |
| Rule | `getUploadRuleData()` — twelve fields from `$conf['uploads'][<mod>]` | complete |
| Refusal words | `getUploadFailText()` — one code, one phrase | complete |
| Owner token | `getEditorFileOwner()`; `FileManager::getFileOwner()` reads it back | complete |
| Window canon | `fragments/window.html`, `plugins/system/slaed.js`, `docs/WINDOW.md` | already system-wide |

The last row is the enabling one: `setWindowOpen()` / `setWindowClose()` live in
`plugins/system/slaed.js` and reach every page. The system can already open a
window without an editor.

### The three places outside

- `modules/files/index.php`, `add()` — via `fragments/file-input.html`;
- `modules/account/index.php`, `edithome()` — same fragment, inside the avatar
  tile plan 2 builds;
- `templates/admin/partials/file-browser.html` — raw input, *Out of scope*.

---

## Settled

**1. The rule belongs to a place, not a module.** `files` is two places:
`$conf['uploads']['files']` for attachments to the description text
(`uploads/files`, 1 MB, 500 × 500, up to ten) and `$conf['files']` for the
distributed file (`uploads/files/temp` for a visitor, `uploads/files/public` for
an administrator, zip/gz/7z/rar/tar, 10 MB, one). `account` likewise:
`$conf['uploads']['account']` versus `$conf['users']['a*']`. Not duplicates —
two different things in one module, and one rule cannot hold both.

The uploader opens **for a place**, named with a dot — `files.dist`,
`users.avatar`, `<mod>.attach` — and reads the settings of that place's module.
Configs stay put. No administrative settings screen is touched.

Naming precedent in the tree: `checkEditorTextRoom($text, 'files.intro')` and
`getEditorRoomData()` in `core/helpers.php`.

**2. Outside the editor the window only picks — the form uploads.** Inside the
editor it keeps uploading over AJAX; there is no form there to carry the file.
Outside, the file rides an ordinary multipart POST on submit, as today.

**3. "My files" is given to both places**, scoped to the owner.

**4. The "Link" row leaves the catalogue form.** The address is typed in the
window's "Link to file" tab. The tab is **off for `users.avatar`**:
`users.avatar` stores a file name resolved against `adirectory`, so an external
address cannot go there. The place rule carries this as `canlink`.

**5. For the avatar, the preset wins.** Plan 2 defines the arbitration and this
plan does not contradict it. There are two levels and they must not be
confused:

- *Inside the window*, the three tabs are mutually exclusive by construction —
  the window cannot hand back two. The handler still reads them in a fixed
  defensive order: `$_FILES`, then storage path, then address.
- *Inside the avatar form*, plan 2's table governs: a preset filename beats
  anything the window produced, because a preset is picked by a click while a
  file may be left over from an earlier attempt. That is also what
  `saveavatar()` does today — `if ($avatar) … elseif (aupload) …`.

An earlier draft of this plan said "upload wins" at the form level. That
sentence is dead.

### Consequences

Three mutually exclusive outcomes outside the editor:

| Tab | Handed to the form | Uploads |
|---|---|---|
| Upload to server | the `File`, via `DataTransfer` into a hidden `<input type="file">` | yes, on submit |
| My files | the path of a stored file | no |
| Link to file | the address | no |

The storage path arrives from the client and is **never taken on trust**: it is
checked through `FileManager` for existence in the place and for ownership.

In field mode the window draws **no progress bar and no queue** — nothing is
being read; the reading happens in the form's submit. The quota **is** drawn.
Client-side rule checking before submit stays in full — extension, weight, image
dimensions — reusing the `getUploadFailText()` phrases already arriving in
`labels`. No new refusal phrase is invented.

`maxfiles` is 1 for both places, so "one refusal does not stop the rest" is not
needed outside. Batches stay an editor ability.

---

## The name collision that forbids unscoping

Dropping `.sl-toastui-upload` and leaving `.sl-fm-*` bare fails: the
administrative file browser owns the same names in
`templates/admin/assets/css/theme.css` — 118 hits — sharing `sl-fm-split`,
`sl-fm-drop`, `sl-fm-queue`, `sl-fm-queue-cap`, `sl-fm-empty`, `sl-fm-props` and
`sl-fm-main`, the last a *button* class there. A comment in `skin.css` warns
about it.

The window gets its own root, `sl-fm-win`, and stays scoped under it.

The eight `--sl-fm-*` tokens admin's `base.css` carries beyond lite's twenty —
`bar-width`, `edit-height`, `preview-height`, `search-width`, `sep-height`,
`split-height`, `thumb-height`, `thumb-width` — belong to that browser and not
to the window. They stay where they are and batch 3 does not touch them.

That comment also cites `docs/FILE-MANAGER-CONCEPT-2026.md`, absent from the
tree — a dead plan reference. Batch 3 replaces it with `docs/WINDOW.md`.

## `data-editor` is not renamed

An earlier draft renamed it to `data-sl-fm` in the partial. Rejected: the
attribute is read from `plugins/editors/toastui/assets/editor-upload.js` (28
hits), `plugins/editors/toastui/assets/editor-emoji.js`, the partial itself (29
hits), `getWindowShot()` and the insert-options window built in
`plugins/editors/toastui/driver.php`. A template-only rename breaks the editor
silently. The gain is cosmetic — the attribute names the window instance, which
is true in both modes. If it is ever wanted it is its own batch across those
five producers, migrated in one commit.

---

## Batches

Ten. Order is fixed in four places: 1 before 2 (a rule before a route to it);
3 and 4 before 6 (the JS must not hunt for classes and markup that do not
exist); 5 before 6 (the runtime must be reachable before it grows an entry);
7 and 8 last.

| # | What | Pixels |
|---|---|---|
| 0 | The file field class miss | slight |
| 1 | `getUploadPlaceRule()` — the rule and the full access matrix | no |
| 2 | Place routing: the `place` parameter through every endpoint | no |
| 3 | The window gets the root `sl-fm-win`; rules move into the theme | no |
| 4 | The window markup becomes the theme's; `getFileManagerWindow()` | no |
| 5 | The runtime leaves the plugin: `plugins/system/filemanager.js` | no |
| 6 | Second entry: the window as a form field | no |
| 7 | The catalogue moves to the door; owner migration | yes |
| 8 | The avatar moves to the door | yes |
| 9 | Cleanup, document, permanent reference | no |

Verification matrix:

| Batch | `php -l` | phpstan | phpunit | cs-fixer | `ui:gates` | `ui:before`/`after` | `ui:label` |
|---|---|---|---|---|---|---|---|
| 0 | — | — | — | — | yes | yes | yes |
| 1 | yes | yes | yes | yes | yes | — | — |
| 2 | yes | yes | yes | yes | yes | — | — |
| 3 | — | — | yes | — | yes | yes | — |
| 4 | yes | yes | yes | yes | yes | — | yes |
| 5 | yes | yes | yes | yes | yes | — | — |
| 6 | — | — | yes | — | yes | — | — |
| 7 | yes | yes | yes | yes | yes | yes | yes |
| 8 | yes | yes | yes | yes | yes | yes | yes |
| 9 | yes | yes | yes | yes | yes | — | yes |

Batch 3 takes a pair precisely to prove it moves nothing.

---

### Batch 0 — the file field class

`fragments/file-input.html` falls back to `sl-field-auto`. That rule exists in
`templates/admin/assets/css/theme.css` and **not** in lite, where the class is
`sl-field--auto` with a double dash. On the site the file field has no rules at
all — a missed name, not a missing design.

Replace the fallback in both `fragments/file-input.html`. Then check whether
`.sl-field-auto` in the admin theme has been orphaned; if it has no callers, it
goes with them.

The avatar `input_id` defect that used to live here is closed by plan 2. Confirm
with `grep -n input_id modules/account/index.php`; if plan 2 somehow left it, fix
it here and say so in the report.

---

### Batch 1 — the place rule and the access matrix

**Why first.** Batches 7 and 8 change shape, not behaviour, and that must be
provable — which needs the rule read from one place both before and after. Today
the catalogue assembles `$rule` by hand from `$conf['files']` with
`maxwidth => 1600` as a code literal, and the avatar from `$conf['users']`.

`getUploadPlaceRule(string $place): array` in `core/system.php`, above
`getUploadRuleData()`.

**The grammar lives here and nowhere else.** A place is
`^[a-z0-9_]+\.[a-z0-9_]+$`. Anything else answers `ok => false`. No caller
carries a pattern of its own.

Three branches on the suffix:

- `<mod>.attach` — `getUploadRuleData(<mod>)` unchanged. The old helper stays
  itself: it reads a config string. One new function, no zoo.
- `files.dist` — `$conf['files']`.
- `users.avatar` — `$conf['users']`.

**The returned array must answer every field the routes read**, not only the
limits. `getEditorFileJson()` reads `moderfiles`, `userfiles` and `guestfiles`;
`checkEditorUploadAccess()` reads `userupload` and `guestupload`; `is_moder()`
needs a module name. All of it is answered here:

| Key | `<mod>.attach` | `files.dist` | `users.avatar` |
|---|---|---|---|
| `mod` | `<mod>` | `files` | **`account`** — the module that owns the page, not `users` |
| `extensions` | config | `typefile` | `atypefile` |
| `maxbytes` | config | `max_size` | `amaxsize` |
| `maxwidth` / `maxheight` | config | 1600 / 1600 | `awidth` / `aheight` |
| `maxfiles` | config | 1 | 1 |
| `maxquota` | config | 0 | 0 |
| `thumbwidth` | config | 0 | 0 |
| `userupload` | config | `upload` **and** `add` | `aupload` |
| `guestupload` | config | `upload` **and** `addquest` | **0 — always** |
| `moderfiles` | config | 250 | 250 |
| `userfiles` | config | 100 | 100 |
| `guestfiles` | config | 100 | **0 — always** |
| `dir` | `uploads/<mod>` | `temp` for a visitor, `path` for a moderator | `adirectory` |
| `canlink` | true | true | **false** |
| `ops` | all four | **`files` only** | **`files` only** |

**The upload right is two settings, not one.** `add()` in
`modules/files/index.php` opens the form on
`(is_user() && $conf['files']['add'] == 1) || (!is_user() && $conf['files']['addquest'] == 1)`,
and `$conf['files']['upload']` only decides whether the file row appears inside
an already-open form. A rule reading `upload` alone would let a member upload
into a module where adding is switched off. Both sides are `AND`ed:
`userupload` is `upload && add`, `guestupload` is `upload && addquest`.

**The listing cap is the system's own, not a new number.** All fourteen rule
rows in `config/uploads.php` ship `moderfiles` 250, `userfiles` 100,
`guestfiles` 100 — verified row by row; the file's other four keys (`dir`,
`height`, `typ`, `width`) are not places and carry no pipe string. The two new
places take the same three, so nothing is invented and nothing needs defending.
`users.avatar` still answers `guestfiles` 0 — a guest has no account to own an
avatar in.

**`users.avatar` is for a signed-in member only.** `guestupload` and
`guestfiles` are hard zero, not read from config: an avatar belongs to an
account and a guest has none. The route must refuse a guest before anything
else, and the refusal is `_ACCESSDENIED` like any other.

**`mod` is not the first segment.** `users.avatar` maps to module `account`,
because that is the module whose moderator may moderate it and whose page the
form lives on. Every caller that needs a module name — `is_moder()`, the log,
the quota caption — reads `$rul['mod']` and never splits the place string.

**A field place answers only one operation.** `ops` names which of the four
routes a place permits. `<mod>.attach` permits all four: the editor uploads,
lists, deletes and packs. `files.dist` and `users.avatar` permit **`editorFiles`
alone** — decision 2 says the form uploads, so `editorUpload` reachable for them
would create exactly the orphans that decision was taken to avoid, and
`editorDelete` / `editorArchive` would hand a direct deletion route to a place
whose window never offers one. The refusal is the route's, not the window's: an
interface that simply does not draw a button is not a guard.

**The directory depends on the role and the resolver knows it.** For
`files.dist` a visitor uploads into `$conf['files']['temp']`, a moderator into
`$conf['files']['path']`; today that choice is smeared across
`modules/files/index.php` and `modules/files/admin/index.php`.

Both modules move onto the resolver **on their present markup**. No template
lines in this batch.

**Verification.** Unit test first, green against the old code:
`getUploadPlaceRule('files.dist')` and `('users.avatar')` equal the arrays the
modules assembled by hand, field for field; `('news.attach')` equals
`getUploadRuleData('news')`; `('files')`, `('files.')`, `('.dist')`,
`('files.dist.x')` and `('Files.Dist')` all answer `ok => false`. Live: upload a
catalogue file as guest and as member; upload an avatar; refusals by extension,
weight and dimensions read the same words as before.

---

### Batch 2 — place routing

**The blocker this closes.** `getEditorRouteRule()` reads
`getVar('get', 'mod', 'var', '')`, and `filterVar()` is
`preg_match('#[^a-zA-Z0-9_\-]#', $var) ? '' : $var` — **a dot empties the
string**. `files.dist` cannot travel as `mod` at all. Nothing downstream can
work until the parameter exists.

- A new request parameter `place`, read as `raw` and validated by
  `getUploadPlaceRule()`'s grammar and nowhere else. `filterVar()` is not
  touched: widening it would widen every other caller.
- **`index.php` first, or nothing downstream runs.** The `$go == 4` branch reads
  `$mod = getVar('get', 'mod', 'var') ? strtolower(getVar('get', 'mod', 'var')) : ''`
  and wraps the whole `switch ($op)` in `if ($mod)`. A URL carrying only `place`
  leaves `$mod` empty and never reaches a case at all — the request dies before
  any guard this plan writes. Replace the entry guard with `place`, read the same
  way the routes will read it, and let `getEditorRouteRule()` do the validating.
  Nothing else in that branch changes.
- **The `ops` gate lives in `getEditorRouteRule()`**, beside the three guards it
  already runs, so no route restates it and none can ship with it quietly
  missing. It refuses with `_ACCESSDENIED`. A field place reaching `editorUpload`
  must be refused by the server even though its window draws no such button.
- `getEditorRouteRule(string $src = 'post'): array` reads `place` instead of
  `mod`, calls `getUploadPlaceRule()`, and keeps the same three guards in the
  same order: rule resolves, `checkEditorUploadAccess()`, `checkSiteToken(…,
  'upload')`. It returns the rule plus `place`; `mod` is still in the array —
  from the table above — so callers reading `$rul['mod']` keep working.
- `getEditorFileArea(string $mod, array $rule)` becomes
  `getUploadFileArea(string $place, array $rule)`, building the `FileManager`
  from `$rule['dir']` and `is_moder($rule['mod'])`.
- `checkEditorUploadAccess()` gains the guest-refusal for a place whose
  `guestupload` is zero — which is already what it does; the change is that the
  zero now comes from the resolver rather than from a config string.
- Every endpoint URL carries `place` instead of `mod`:
  `index.php?go=4&op=editorUpload&place=news.attach`. The URLs are built server
  side in `plugins/editors/toastui/driver.php` and handed to the JS as
  `opt.upload` / `opt.files` / `opt.remove` / `opt.archive`, so both ends
  migrate together and no compatibility shim is needed.

Rename the four `op` values only if nothing else reads them — check
`admin/`, `core/` and the JS first. If anything does, leave the names alone:
they are a URL contract and this plan has no reason to break one.

**Verification.** Unit: a request with `place=files.dist` resolves;
`place=files` and `place=<script>` refuse; a request with the old `mod` and no
`place` refuses rather than falling through. **`op=editorUpload&place=files.dist`
and `op=editorDelete&place=users.avatar` refuse with `_ACCESSDENIED` even with a
valid token** — that is the `ops` gate, and it is the security assertion of this
batch. Live: the editor still uploads, lists, deletes and packs in a module
where a moderator is signed in, and in one where a guest is allowed.

---

### Batch 3 — the window gets its own root

`.sl-toastui-upload` as the window root becomes `.sl-fm-win`. The 143 selector
lines move from `templates/{lite,admin}/assets/editors/toastui/skin.css` into
`templates/{lite,admin}/assets/css/theme.css` **under the new root, still
scoped**. Not one value changes: a move, not a rewrite.

**Five rules move that do not start with the window root.**
`.sl-shot-side .sl-fm-props` and its four relatives dress the properties panel
of the shot window, which `getWindowShot()` builds from `core/helpers.php` —
already system-wide. Leaving them in the skin would strip that panel on any page
without an editor. They move with the rest and keep their `.sl-shot-side` scope.
`tools/ui-contract.php` names `.sl-shot-side .sl-fm-props dt` and is updated
with them.

`skin.css` keeps the editor's own — vendor toolbar, icons, mode switch,
fullscreen. `Editor::getThemeSkin()` stops answering for the window.

`sl-toastui-upload` stays on the editor's dialog as a **second** class: it owns
`.sl-toastui-upload button` and `.sl-toastui-upload input`, which cure the
vendor's fixed button height and belong where the vendor is.

Replace the dead `docs/FILE-MANAGER-CONCEPT-2026.md` citation with
`docs/WINDOW.md`.

Breaks and is fixed with it: `tools/ui-contract.php` (~14 places name
`.sl-toastui-upload .sl-fm-*`); `EditorWindowTest` and `EditorRoomTest` (pinned
to the skin path and to byte-identity of the two themes); `DeskKeysTest` and
`ThemeCreationTest` (same path — the latter's "every theme carries a skin" stays
true).

**Verification.** The pair must show **zero** differences. Then `ui:gates` and
phpunit in full.

---

### Batch 4 — the window markup becomes the theme's

`partials/editor-toastui-files.html` → `partials/file-manager.html`, both
themes, still byte-identical. `data-editor` stays as it is — see above.

`getFileManagerWindow(array $opt): string` where the other markup assemblers
live: takes `getUploadPlaceRule()`, assembles captions, calls the partial.
`driver.php` calls it instead of its own `getHtmlPart()`.

The partial gains `is_field`: no queue, no progress bar, no "insert as" switch;
draws the chip of what was picked. It honours `canlink` from the place rule —
the rail hides the link tab when the place refuses one.

Opening from outside is modal, `showModal()`, no `data-sl-window`: nothing
beside a form to work on at once. Inside the editor it stays non-modal. Both are
canon axes — `docs/WINDOW.md`, *Presentation*.

**The window keeps its own id scheme — `getFieldIds()` is not for it.** The
driver mints `<id>_toast_upload`, `_toast_file`, `_toast_alt`, `_toast_object`
and the rest from the editor instance id, and the runtime finds those nodes by
exactly those ids; two windows on one page stay apart because the instance id
differs. Plan 1's `getFieldIds()` is a per-request counter for **form rows**,
and pointing the window at it would break that derivation. It governs the row
the button lives in — batches 7 and 8 — and nothing inside the window.

**Verification.** No pair — same markup, same classes, different caller. Live:
the editor inserts an image, a link, an attachment and a file from storage, both
editor modes.

---

### Batch 5 — the runtime leaves the plugin

**The blocker this closes.** `editor-upload.js` is delivered by one line in
`plugins/editors/toastui/driver.php`. A page with no Toast UI editor — the
avatar page, the catalogue form under a different editor — never receives it, so
the window has no behaviour there.

- `plugins/editors/toastui/assets/editor-upload.js` →
  `plugins/system/filemanager.js`, beside `slaed.js`. The move is a move: the
  IIFE and every function keep their names.

**The namespace split, and why there is no alias.** All three plugin scripts
share one object: each opens with `var api = win.SlaedToastUi || {}` and
republishes it at the end, and the `i18n/emoji-*.js` files hang `emojiWords` on
it too. So `SlaedToastUi` stays — it is the editor plugin's own namespace and
emoji and tags keep living there. Only the file-manager runtime leaves, onto
`win.SlaedFileManager`.

`editor-tags.js::register()` ends with `if (api.addUpload) api.addUpload(id, ed, opt || {})`.
Once `addUpload` lives elsewhere that condition is simply false and **the editor
loses its file button with no error in the console** — a silent break, which is
why an alias is the wrong answer: it would hide the coupling until batch 9 and
then break it there instead. `register()` is rewritten in this batch to call the
new namespace explicitly:

```
if (win.SlaedFileManager) win.SlaedFileManager.addUpload(id, ed, opt || {});
```

No alias is created, so batch 9 has nothing to remove.

**The runtime calls back into the editor, and that has to be cut, not aliased.**
Grep the moving file for `api.` before touching anything; there are exactly four
names and they split two and two:

| Name | Uses | Owner | What happens |
|---|---|---|---|
| `api.options` | 5 | itself — `api.options = api.options \|\| {}` | travels |
| `api.getTpl` | 12 | itself — `api.getTpl = api.getTpl \|\| function…` | travels |
| `api.getEditor` | 4 | `editor-tags.js` | **must become local** |
| `api.insertText` | 4 | `editor-tags.js` | **must become local** |

The two borrowed ones are three lines each in `editor-tags.js`:

```
function getEditor(id) { return map.get(String(id)) || null; }
api.insertText = function(id, text) { addText(getEditor(id), text); };
function addText(ed, text) { if (!ed) return; ed.focus(); ed.insertText(text); }
```

`addUpload(id, ed, opt)` already receives the editor. Keep it: a map on the
runtime, written in `addUpload()` and never in `addField()`. Then `getEditor()`
and `insertText()` are local functions over that map, copied from the three
lines above rather than reinvented. `editor-tags.js` keeps its own copies for
its own use — nothing is deleted there.

**The runtime draws nothing without its templates, and they are in the plugin
too.** `api.getTpl()` reads `opt.tpl`, which the driver sets to
`js-slaed-editor-tpl`, and finds `<template data-tpl="…">` inside
`partials/editor-toastui-templates.html` — delivered by one line in
`driver.php`, exactly like the script was. The split is clean, so make it:

| Template | Needed by |
|---|---|
| `fm-act`, `fm-busy`, `fm-dial`, `fm-job`, `fm-pick`, `fm-prop`, `fm-row`, `fm-tile`, `fm-why` | the runtime — nine |
| `msg-info`, `msg-warn` | the runtime, through `getMsg()` |
| `emoji-panel`, `emoji-tab`, `emoji-item`, `emoji-empty` | `editor-emoji.js` — four |

Write this partial **last** in the batch: the batch probe tests it, so a
half-done batch — script moved, templates not — never reads as finished.

Move the eleven into `partials/file-manager-templates.html` in both themes,
delivered by `getFileManagerWindow()` under the same `static $done` as the
script, with a container class of its own; `opt.tpl` names that class. The four
emoji ones stay in `editor-toastui-templates.html` and stay with the driver.

Without this the window opens on a page with no editor and then draws no tile,
no row, no queue card and no message — every one of them silently, because
`getTpl()` answers null and every caller tolerates null. Same failure shape as
the missing script, one layer down.

**The null editor is the field mode, and it must stay silent.** `addText()`
already returns on a falsy editor, and `addSource()` and `setRoom()` already
guard. A field place has no editor in the map, so `getEditor()` answers null and
the four editor-only paths — `addSource`, `addAttach`, `addImage`, `setRoom` —
disable themselves. That is the designed behaviour, not a leftover: keep the
guards when copying, do not "clean them up".
- Delivery moves to `getFileManagerWindow()` with a `static $done` dedup, the
  pattern `Editor::getThemeSkin()` already uses. **Not** `$conf['global']['script_f']`:
  that would load it on every page of the site and would need an administrative
  config edit, which decision 1 refuses.
- `driver.php` drops its `head-script-src` line for this file and keeps the ones
  for `editor-emoji.js` and `editor-tags.js`.

`editor-emoji.js` is untouched and keeps reading `data-editor` on its own
markup.

**Verification.** Unit: the tests naming the old path — `DeskKeysTest::EDITJS`,
`EditorRoomTest`, `EditorWindowTest` — move with it. Then assert the cut: no
`api.getEditor` and no `api.insertText` remain in `plugins/system/filemanager.js`,
and `SlaedFileManager` is not reachable from `editor-tags.js` by anything but
the one explicit `addUpload` call.

Live, and this is the point of the batch — the namespace split is only correct
if the editor notices nothing:

- the file button still appears on the toolbar (its absence is the silent break
  above, so look for the button before anything else);
- insert an image with no alignment — `addSource()` → `ed.exec('addImage')`;
- insert an image with an alignment — `addImage()` → `insertText()` with an
  `[img=…]` tag;
- insert an attachment — `addAttach()` → `insertText()` with an `[attach=…]` tag;
- open the embed pane and watch the room meter — `setRoom()` reads
  `ed.getMarkdown()`, so a meter stuck at zero means `getEditor()` came back
  null when it should not have;
- both editor modes, markdown and wysiwyg;
- a page carrying two editors: the script **and the templates partial** are
  delivered once each, and each editor inserts into itself and not the other;
- the library tab draws tiles and rows, the queue draws a card, a refusal draws
  a message — those are `fm-tile`, `fm-row`, `fm-job` and `msg-warn`, and an
  empty area where one of them belongs means the templates partial did not
  arrive.

---

### Batch 6 — the window as a form field

```
api.addField(id, node, opt)
```

`node` is the field's box, `opt` the same object `addUpload()` takes.
`addHook()` and `addBtn()` already return early on `!ed`, so editor bindings are
simply not installed — neither needs touching.

New: `setFieldPick(id, mode, data)` puts the pick into the form by the
consequences table — a `File` through `DataTransfer` into the hidden
`<input type="file">`, a path or address into hidden text fields. A chip with
name and weight is drawn in the field box; its cross clears all three.

One file. No second upload JS.

**Verification.** phpunit — the tests that read this file check the order of its
steps. Nothing live yet: `addField()` has no caller until batch 7. Deliberate —
this batch lays the ability, 7 and 8 switch it on.

---

### Batch 7 — the catalogue moves to the door

In `modules/files/index.php`, `add()`: the "File" row carries a button, a chip
and a limits line built **from the place rule**, never typed beside it. The
"Link" row goes; its hidden `url` field stays in the POST and is filled by the
window.

Handler: read the three outcomes in the defensive order. The storage path is
checked through `FileManager` for existence in the place and for ownership.

The introductory `sl-alert` listing formats and weight stays: it explains the
rule before the window is opened.

#### Owner migration — do not skip

**The blocker this closes.** The module calls
`addUploadedFile($_FILES['userfile'], $rule, $fdir, 'files', isset($user[0]) ? (int)$user[0] : 0)`.
For a guest that fifth argument is `0`, so the stored name carries the owner
segment `0`. The listing route compares
`FileManager::getFileOwner($one['name']) !== getEditorFileOwner(...)`, and for a
guest that is an HMAC of the session. A comment in `core/system.php` already
names the hazard: *"an integer cast turns every guest token into zero and
matches one guest against another."*

Today this is latent — the frontend writes into `uploads/files/temp` and the
editor lists `uploads/files`, so the two never meet. Giving `files.dist` a
listing makes it live.

- The module passes `getEditorFileOwner($rul['mod'])` and never
  `(int)$user[0]`. For a member the two already agree — both are the numeric id
  as a string — so only the guest case changes.
- **Old files are not migrated and must not be.** A file carrying owner `0` was
  written by a guest nobody can now identify; leaving it unlisted is the correct
  failure direction. Do not write a script that assigns them to anyone.
- Verify the isolation, and this is a required step, not an optional one: two
  browser profiles, two guest sessions, one uploaded file each. Each must see
  exactly its own in "My files". A file uploaded before this batch must be
  visible to neither.

**Verification.** Live, seven paths: guest uploads; member uploads; member takes
from storage; member gives a link; refusal by extension; refusal by weight;
submit with nothing picked → `_UPLOADEROR2`, as today. Plus the two-guest
isolation check above.

---

### Batch 8 — the avatar moves to the door

**Read plan 2's output first.** `edithome()` has been rebuilt: six sections, one
shared form, one save button. `saveavatar()` is gone; the avatar arbitration
lives inside `savehome()`. The preset gallery is a radio field named `avatar`
inside that same form.

Replace **one row inside the avatar tile**: `fragments/file-input.html` becomes
the button and chip. Touch nothing around it — plan 2 built the tile so this is
a one-row change.

`savehome()`'s arbitration gains one arm and keeps its order (decision 5):

| What arrived | What happens |
|---|---|
| `avatar`, a preset filename | wins over everything; validated against the theme preset directory, `presets/<file>` stored |
| an uploaded file | validated by the place rule, stored, the name written |
| a storage path | ownership verified through `FileManager`, the name written |
| none | the avatar is untouched |

Plan 2's rule holds: **an avatar failure does not roll back the profile.** The
profile is one `UPDATE`, the avatar a second.

`users.avatar` stores a file name, not a path, so a pick from storage hands back
the same name an upload would have. No link tab — the place rule says so. A
guest never reaches this: `guestupload` and `guestfiles` are zero.

The owner argument moves to `getEditorFileOwner($rul['mod'])` here too; for a
member it is the same value that is written today, so nothing shifts.

**Verification.** Live: upload an avatar; take one from your own past uploads;
pick a gallery preset; pick a preset **and** a file in one submit — the preset
must win; refusal by dimensions (over 100 × 100); refusal by weight (over 50 KB);
a rejected avatar together with fifteen edited fields — the fields must save.
Confirm a signed-out visitor cannot reach the route at all.

---

### Batch 9 — cleanup

`fragments/file-input.html` has no callers left after 7 and 8. Confirm
tree-wide, delete from both themes.

Move what outlives this document into permanent reference: the field-mode
contract — three outcomes, the defensive order, `is_field`, `canlink` — into
`docs/WINDOW.md`; the place grammar, the access matrix table and the
`mod`-is-not-the-first-segment rule into `docs/ARCHITECTURE.md`;
`getFileManagerWindow()` and the runtime move into `docs/EDITORS.md`; the
skin-to-theme move into `docs/TEMPLATES.md`.

Delete this file.

**Verification.** Full matrix, then grep the tree for `file-input`,
`sl-field-auto`, `sl-toastui-upload`, `editor-upload.js` and `&mod=` on the
editor routes — every hit explainable. Two things survive on purpose and must
not be "cleaned up": `SlaedToastUi`, the editor plugin's namespace for tags,
emoji and the i18n word lists; and `editor-toastui-templates.html`, which after
batch 5 holds the four `emoji-*` templates and nothing else — confirm it holds
exactly four.

---

## Open — no action needed now

Nothing blocks batch 1. The listing cap is settled at 250 / 100 / 100, the
system's own shipped values; it is a constant in the resolver and not three new
config keys, because new keys mean new administrative settings screens and
decision 1 refuses those.


- **Orphans in `uploads/files/temp`.** Decision 2 creates none, but old ones may
  exist: today a file is removed only when the database write fails, not when
  the visitor abandons the form after a successful upload. Checking production
  is separate work.
- **`maxquota` is zero for both places**, so the quota bar is honestly not drawn
  for them. Introducing a quota there is a product decision this plan does not
  take.

## Out of scope

- **The administrative file browser.** `templates/admin/partials/file-browser.html`
  is a different component with its own CSS that merely shares names. It could
  become the door's third caller — separate work, separate verification.
- **Merging the configs into `config/uploads.php`.** Rejected by decision 1.
- **Batches of files from outside.** `maxfiles` is 1 for both places.
- **A progress bar for the form's submit.** Would need `XMLHttpRequest` instead
  of an ordinary submit — the AJAX decision 2 declined.
- **Renaming `data-editor`.** Rejected; see above.
