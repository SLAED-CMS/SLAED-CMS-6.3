# Upload

Status: approved, not started. Last review: 2026-08-03. Every open decision is
resolved in this document; implement it as written and raise a question only if
the working tree contradicts it.

Work through `Execution batches`, one batch per session, each ending with the
self-check defined there.

Last completed batch: 5 — the editor adapter. `addEditorUpload()` is a thin
adapter now: it resolves the rule, checks access and the token, hands the whole
`$_FILES['file']` shape to `addUploadedFiles()` and maps the returned codes onto
the constants of `Error codes and messages`. The duplicated validation, quota,
naming and storage block is gone, the generic `go=4` default answers 400 with
`{"ok":false,"error":"<_ERROR>"}` instead of calling `upload()`, and
`getEditorFileJson()` answers a guest with an empty list.

Decisions of batch 5, all visible in the code:

- the code-to-constant mapping is a `match` inside the adapter, not a helper.
  `Acceptance` allows exactly two new functions and both already exist, so each
  remaining adapter repeats a match over the codes its own flow can receive.
- the owner is `is_user() ? $user[0] : (is_moder($mod) ? null : 0)`. The old
  line read a guest owner out of `?userid=`, which a guest could choose freely;
  a privileged moderator without a site user now publishes with no suffix at
  all, which is the `null` half of the naming grammar.
- the guest listing guard is one early return before the scan rather than a
  filter inside it. Moderator listing is left exactly as it was — every file of
  the directory, canonical or legacy — because `Acceptance` keeps moderator and
  user behavior compatible and only the guest half is a documented change.
- the duplicated access test in `plugins/editors/toastui/driver.php:66` needed
  no deletion: batch 1 had already moved that line onto
  `checkEditorUploadAccess()`.
- the route test found one defect in the class and this batch fixed it there:
  `addUploadedFile()` normalized the separator of `$_FILES['tmp_name']` before
  the transfer, and PHP matches that path literally against the received
  uploads, so `move_uploaded_file()` refused every local upload on Windows and
  the adapter answered `transfer`. The source path now stays exactly as the SAPI
  reported it. No offline test could see this, because the probe replaces
  `addSourceFile()` — so batches 6, 7 and 8 must each publish one real file
  through their real route before claiming the flow works.

Decisions of batch 4, still true. `addRemoteFile()` sits next to the local entry
points and reuses the publication step unchanged: the fetch writes the same
`.upload-<hex>.part` in the final destination, and from the moment the body has
arrived the extension, MIME, image, quota, naming and rename checks are
literally the local ones.

- the two seams the plan names are `getHostRecords()` (the resolver) and
  `getRemoteReply()` (the cURL execution). The second takes the finished option
  array, so a test drives the real header and body callbacks of the class and
  can also assert how the request was configured. That is what makes the proxy,
  protocol, pinning, TLS and timeout policy testable without a socket.
- the redirect chain is followed by the class, not by cURL: `FOLLOWLOCATION` is
  off and `MAXREDIRS` is 0, because a hop cURL follows itself would skip the
  address policy. Each hop is normalized, resolved and validated again, and a
  non-2xx body is discarded instead of written.
- the extension comes from the final URL only, as the plan says, so a redirect
  service whose first URL carries no extension still works. The body is bounded
  by `Content-Length` and by the streaming counter before that point, so a
  wasted download is bounded by `maxbytes`.
- `maxbytes` of `0` disables the byte limit for a remote fetch exactly as it
  does for a local one. Every adapter passes a configured limit; a hand-edited
  record with an empty field 2 buys an unbounded download, which is the same
  contract the rule table states for every other limit.
- a URL whose host is already an address carries no `CURLOPT_RESOLVE` entry.
  There is nothing to resolve, and libcurl refuses to parse an entry whose host
  part is an IPv6 literal — it fails the whole transfer rather than ignoring the
  entry, verified on the libcurl 8.12.1 build of this stand. An offline test
  cannot see this, because the seam never hands the entry to libcurl, so the
  contract test asserts the entry is absent and the reason is recorded here.
- the address policy is a CIDR block list rather than `filter_var()` flags,
  because those flags miss `100.64.0.0/10`, multicast, the documentation ranges
  and every IPv4 address smuggled in as `::ffff:a.b.c.d`. One non-public
  address anywhere in an answer refuses the whole host.

Three decisions of batch 3, still true:

- the clock seam the plan names is one of six. `is_uploaded_file()`,
  `move_uploaded_file()`, the random name segment, the publishing `rename()`
  and the partial `unlink()` sit behind protected methods as well, because no
  unit test can forge a request-scoped upload and neither a rename nor an
  unlink can be made to fail on demand on a real filesystem. The single
  test-only subclass is declared in `tests/Support/upload_probe.php`;
  production code uses the public methods only.
- a successful result carries `width` and `height` only for images. A non-image
  publishes with both `null` rather than with a fabricated `0`.
- `addUploadedFiles()` answers an empty or malformed submission with one
  `missing` result, for the same reason a batch over `maxfiles` answers with one
  `count` result: an empty list would be indistinguishable from success.

`tests/Unit/UploadContractTest.php` over `tests/Support/upload_probe.php` covers
every local result code, the naming grammar, collision retry and exhaustion, the
one-hour sweep, quota boundaries and exclusions, destination policy,
compensation, both batch shapes and now every rejection path of `Remote upload`
— 25 tests, 812 assertions, one skipped because Windows refuses the probe a
directory symlink without elevation. Five of them are worth knowing about: one
fixture per mapped extension proves the type map against the libmagic build of
this machine, and all 21 pass; four concurrent child processes against one
destination prove the lock, publishing exactly the two files the quota admits
and refusing the other two; a source level test keeps the two rejected
publication strategies rejected, so no future edit reintroduces `link()` or
reserves the final name with `fopen()` before the rename; a second source level
test keeps the network inside the two seams, so no future edit reaches for
`file_get_contents()` again; and the remote scenarios answer both seams from a
scripted zone and a scripted reply chain, so a private, loopback, link-local,
CGNAT, multicast, documentation, ULA or IPv4-mapped answer is refused in a test
that opens no socket at all.

What the offline tests deliberately do not prove is that the production seams
themselves work, so the batch also ran the unmodified class against the network
once by hand: a public PNG published through real DNS, a real pinned connection
and a real streaming transfer; the same file published again through a real 302
between two hosts; `127.0.0.1` and `169.254.169.254` refused without a packet;
an unresolvable host refused; and a 32-byte limit answered with `size`, no
partial and an empty `error_file.log`. That run is what found the resolve-entry
defect above. Repeat it after any change to the two seams — a scripted reply
cannot fail the way libcurl does.

Batch 5 verified the same way, through the real routes of the running stand
rather than through the suite: an unknown operation answered 400 for a
configured and an unconfigured `mod` with no PHP warning; a write to a module
without a record refused and wrote nothing; a site user published one file and a
mixed batch, was refused a batch of eleven, an oversized file and a `txt`, and
saw only the two files carrying the own suffix; a moderator published a file
with no suffix at all and saw all five files of the directory; and a guest, with
`guestupload` briefly raised on one record and restored afterwards, uploaded
successfully and still received an empty list while a `-0` file sat in the
directory. `error_php.log` and `error_file.log` stayed empty,
`storage/logs/uploads` holds nothing but lock files, and the only new
`error_sql.log` lines are the `pid` column failures described below. Every
fixture — the user, the seeded files, the published files — was removed
afterwards.

One finding that belongs to no batch of this plan: 33 tests of the comment
suites fail on this stand, and the cause is the database rather than the code —
`sport_comment` has no `pid` column, so every comment insert fails with
`42S22/1054`. The schema update of the comment threads commit was never applied
here. It has nothing to do with uploads and is stated only so the next session
does not spend its time on it. Batches 1 to 5 are all uncommitted in the
working tree.
Update this line as the final act of every batch —
it is how the next session knows where to start. Do not rely on a commit
message: `.rules/git.md` allows committing only on explicit instruction, so a
batch may well end with nothing committed at all.

## Running this plan

Written for the session that opens this file cold, with no memory of the ones
before it.

**One session per batch, in a fresh window.** Not because the file is large — it
is about 78 KB, some 20k tokens — but because a batch fills a session with code,
diffs, and test output. A new window starts clean.

**The prompt is one line.** `Выполни этап N из docs/UPLOAD-2026.md`, or, when
the number is not at hand, "continue from the next unfinished batch" — the
`Last completed batch` line above answers that. Do not paste the file into the
prompt; read it from disk. `CLAUDE.md` already routes you through
`.agents/SYSTEM-PREAMBLE.md` and `.rules/*` first.

**Two preconditions, or the self-check degrades into theatre:**

- Start from a clean working tree. Self-check step 2 asks you to read your own
  `git diff` and trace every line to a bullet of the current batch; unrelated
  edits sitting in the tree make that impossible.
- Get the previous batch committed before starting the next one. You may not
  commit unprompted, so ask for it at the end of a batch. Two or three
  uncommitted batches stacked on top of each other and the diff stops being
  readable.

**One batch, then stop.** Ten batches, ten sessions. Do not pick up the next one
because the current felt small: the self-check, the report, and the commit all
belong to the batch, and a second batch in the same window degrades the first
one's `git diff` into something no longer traceable. Finishing early is a
finished session, not a spare half.

Batch 3 is the one most likely to overflow on its own. If it does, split it into
`3a` (validation, naming, paths) and `3b` (publication, quota, locking) as
`Execution batches` describes, and stop at the boundary.

Replace the procedural upload pipeline with one `Upload` production class.
Migrate every supported publisher in the same change and remove the old
functions, the duplicated editor logic, and the generic upload fallback. Do not
keep a compatibility wrapper, transitional implementation, or runtime dual path.

The migration is also a consolidation: one publication primitive, one rule
resolver, one service accessor, one lock location, and no new language
constants. Every artifact this plan adds must immediately replace existing
duplicates. See `Removal` for what disappears in the same change.

Source line numbers below describe the current working tree. Resolve every
named symbol again before editing because adjacent work may move it.

## Scope

Supported publication flows:

- account avatar;
- frontend and admin file modules;
- Toast UI editor;
- admin local upload;
- admin remote upload.

Existing admin deletion, legacy filename handling, filesystem backup, media
processing, cloud storage, chunked uploads, antivirus integration, and
retention policy are out of scope. `deleteStoredFile()` is used only for
class-owned files created during the current operation, including checked
compensation after a failed database write.

File relocation is out of scope as a feature, but the admin files handler must
stop relocating a file it has just published in the same request; see
`Adapter write ordering and compensation`.

Security behavior changes:

- Guest editor uploads may remain enabled by module configuration, but
  `editorFiles` must not enumerate historical files owned by the shared guest
  value `0`. A guest receives the files returned by the current upload response
  only. This closes cross-session disclosure without adding a new identity or
  persistence contract.
- The account avatar handler must require POST and a valid `account` token for
  the whole operation, including preset selection. Today the token is only
  checked when `op` arrives in the POST body
  (`modules/account/index.php:1052`), so `GET index.php?name=account&
  op=saveavatar&avatar=<preset>` writes `users.avatar` with no token at
  `modules/account/index.php:1064`. Leaving that open would contradict this
  plan's own acceptance criteria, and the handler is being edited anyway.

## Architecture

Create `core/classes/upload.php` with class `Upload`. This is the only new
production class. Like every other class in `core/classes`, it is not declared
`final`: a test-only subclass must be able to replace protected DNS, cURL, and
clock seams without changing the public constructor or using public network
services. Production code must not subclass it.

Add `ext-fileinfo` and `ext-curl` to the `require` block of `composer.json`;
that file has no `config.platform` section and none is added. Missing runtime
capabilities fail closed with `unsupported`.

### Service accessor

`core/classes` has no runtime autoload. Do not require `upload.php` from
`core/system.php`, which runs on every request; load it lazily, as
`admin/modules/monitor.php:921` already does for `Backup`.

Add exactly one accessor in `core/system.php`:

```php
getUploadService(): Upload
```

It requires the file once, builds the single instance with `UPLOADS_DIR` and
the lock directory, and returns it. Adapters never call `new Upload(...)`, so
the root and lock arguments exist in one place only.

### Lock directory

Lock files live in `LOGS_DIR.'/uploads'`, matching the existing convention
(`LOGS_DIR.'/scheduler/<name>.lock'`, `core/system.php:339`; the statistics lock
at `core/system.php:1147`). Do not add a `storage/locks` tree: `/storage/logs/*`
is already ignored, protected, and covered by the permission tooling, nothing in
the project deletes files from `LOGS_DIR`, and cache cleanup only walks
`storage/cache` (`core/classes/cache.php:81`). The class creates the directory
on demand and fails with `destination` when it cannot.

### Responsibilities

The class owns:

- validation of extension, MIME type, size, image dimensions, destination, and
  quota;
- safe local and remote transfer;
- collision-free naming, lock-guarded atomic publication, partial cleanup, and
  returned metadata.

HTTP adapters keep:

- `$_FILES`, `getVar()`, authorization, CSRF, request-method, and operation
  checks;
- conversion of existing configuration paths to upload-root-relative paths;
- database writes and checked compensation;
- templates, JSON, redirects, localized messages, and public stored-reference
  formats.

### Public contract

```php
__construct(string $root, string $locks)

addUploadedFile(
    array $file,
    array $rule,
    string $dir,
    string $base,
    ?int $uid = null
): array

addUploadedFiles(
    array $files,
    array $rule,
    string $dir,
    string $base,
    ?int $uid = null
): array

addRemoteFile(
    string $url,
    array $rule,
    string $dir,
    string $base,
    ?int $uid = null
): array

deleteStoredFile(string $path): bool
```

Owner semantics:

- `null`: privileged upload without an owner suffix;
- `0`: guest or unowned content, never enumerable by a non-moderator;
- positive integer: owning site user.

Rules use stable keys: `extensions`, `maxbytes`, `maxwidth`, `maxheight`,
`maxfiles`, and `maxquota`. A missing or zero numeric key disables that limit;
`extensions` is always required.

Every single-file operation returns the same shape:

```php
[
    'ok' => bool,
    'file' => ?string,
    'path' => ?string,
    'size' => int,
    'mime' => ?string,
    'width' => ?int,
    'height' => ?int,
    'error' => ?string,
]
```

`addUploadedFiles()` returns a plain list of that shape, one entry per
submitted file, in submission order, with mixed successes and failures. It
returns no envelope: the editor adapter builds its own
`{ok, files, errors, error}` JSON from the list, which is how the existing
response shape at `core/system.php:4344` is preserved.

A batch over `maxfiles` transfers nothing and returns a **single** result with
`ok=false` and `error=count`, not an empty list. An empty list would be
indistinguishable from "no files submitted" and would force the adapter to
re-evaluate the rule it just passed in.

### Rule resolution

The per-module string in `config/uploads.php` is the single source of upload
rules for the editor and, through the `all` record, for admin upload. It has
twelve pipe-separated fields, all currently in use:

Every field gets a name. Naming only the six the class consumes would leave
`$con[6]`-style indexing alive in the adapters and give the serializer nothing
to order by, which defeats the point of having one definition of the format.
The resolver returns this exact key set, and the serializer writes it back in
this exact order:

| Index | Key | Meaning | Consumer after migration |
|---|---|---|---|
| 0 | `extensions` | allowed extensions | rule |
| 1 | `maxquota` | module storage quota | rule |
| 2 | `maxbytes` | maximum single file size | rule |
| 3 | `maxwidth` | maximum image width | rule |
| 4 | `maxheight` | maximum image height | rule |
| 5 | `maxfiles` | maximum files per batch | rule |
| 6 | `thumbwidth` | thumbnail width | parser (read at `core/classes/parser.php:480`) |
| 7 | `adminlist` | admin file list page size | admin (read at `core/admin.php:883`) |
| 8 | `moderfiles` | editor listing limit for moderators | editor adapter |
| 9 | `userfiles` | editor listing limit for users | editor adapter |
| 10 | `userupload` | authenticated upload allowed | adapter authorization |
| 11 | `guestupload` | guest upload allowed | adapter authorization |

The six rule keys are passed to `Upload` as the `$rule` array; the other six
stay with the adapters. Both halves travel in one array, so
`setUploadRuleData(getUploadRuleData($mod))` reproduces the original string
byte for byte — the round-trip the contract test asserts for every field.

Do not add a second resolver. Rename the existing `getEditorUploadData()`
(`core/system.php:4224`) to `getUploadRuleData()` and widen it to return all
twelve keys above, keeping `ok`, `error`, `dir`, and `path` alongside them. It
becomes the only place that splits the configuration string. Every current call
site must move onto it; the list is complete and was verified with
`rg "explode\('\|'"`:

| Site | What it reads | After migration |
|---|---|---|
| `core/system.php:4227` | the whole string | becomes the resolver body |
| `core/system.php:4237-4238` | fields 10, 11 | `checkEditorUploadAccess()` takes the resolved array |
| `core/system.php:4291-4296` | fields 0-5 | rule keys |
| `core/system.php:4359` | fields 8, 9 | resolved array |
| `admin/modules/uploads.php:161` | nothing; limits hardcoded | `getUploadRuleData('all')` |
| `admin/modules/uploads.php:258` | all twelve, for the form | resolved array |
| `core/admin.php:882` | field 7, admin list page size | resolved array |
| `core/classes/parser.php:479` | field 6, thumbnail width | resolved array |
| `core/helpers.php:550` | passed to the editor factory | resolved array |
| `plugins/editors/toastui/driver.php:66` | fields 10, 11 | `checkEditorUploadAccess()` |

This is a rename plus a widened return, not a new helper. The four sites outside
`core/system.php` and the editor are not optional: the acceptance criterion is a
single parser, and leaving any of them behind would keep the field indexes
duplicated across the codebase.

Reading is only half of it. `configsave()` assembles the same twelve fields by
hand (`admin/modules/uploads.php:355`), so the field order would still live in
two places after the resolver lands. Add the matching writer next to it:

```php
setUploadRuleData(array $rule): string
```

It takes the resolved array and returns the pipe-separated string.
`configsave()` builds its array and calls it instead of concatenating. Both
directions then share one definition of the format, and a future field can be
added without hunting for the second copy. This is the only justification for a
second function — it removes a real duplicate, not a hypothetical one.

### Error codes and messages

Class error codes stay machine-readable: `missing`, `transfer`, `size`, `count`,
`extension`, `mime`, `image`, `dimensions`, `quota`, `destination`, `exists`,
`write`, `remote`, and `unsupported`. Database failures remain adapter errors
and must not be reported as successful uploads.

Adapters map codes to the existing language constants. No new language constant
is introduced, so no locale file changes and `.rules/constants.md` requires
nothing here:

| Code | Constant | Note |
|---|---|---|
| `missing`, `transfer`, `remote` | `_ERROR_DOWN` | source file never arrived |
| `size` | `_ERROR_BIG` | |
| `count` | `_FILEUP` | adapter appends `': '.$rule['maxfiles']` exactly as `core/system.php:4298` does today |
| `extension`, `mime`, `unsupported`, `image` | `_ERROR_FILE` | inadmissible or undecodable format; `image` keeps the constant `core/system.php:4246` returns today |
| `dimensions` | `_ERROR_SIZE` | matches `core/system.php:4249` |
| `quota` | `_FSIZEALL` | adapter appends `filterSize($rule['maxquota'])` as it does today |
| `exists` | `_ERROR_EXIST` | |
| `destination`, `write` | `_ERROR_UP` | |

`image` and `dimensions` stay separate codes precisely because they map to
different constants; collapsing them would change visible text.

Every failure returns `ok=false`, no `file`/`path`, and its error code. A
successful result never carries an error: publication either completes or leaves
nothing behind.

## Format policy

`Upload` matches the extension against the exact configured allowlist, then
verifies the detected `finfo` MIME against an explicit map. Because one
extension legitimately produces several MIME strings across libmagic builds,
the map is extension to a **set** of accepted MIME types. A single accepted
value per extension would reject files that upload successfully today.

### Inventory

Three configuration files define uploadable extensions, and a fourth copy is
generated:

- `config/uploads.php` key `typ` (26 entries today): the global list of formats
  the CMS knows — it drives the Templates tab and the render map, and is not
  itself an upload allowlist for any route; see `Two allowlist levels`;
- `config/uploads.php` per-module strings, field 0 (`gif,jpg,jpeg,png,zip,rar`),
  including the `all` record, which is what admin upload actually resolves;
- `config/files.php` key `typefile` (`zip,gzip,7z,rar,tar`);
- `config/users.php` key `atypefile` (`jpg,jpeg,gif,png`);
- `config/filetype.php` holds a render template per extension and currently has
  exactly the same 26 keys as `typ`, which it must stay in sync with; see
  `Admin configuration surface`;
- `config/local.php` is not an override file but the generated config cache:
  `getConfig()` returns `$cache['_config']` whole as long as
  `_meta.cache_version` matches, without re-hashing the sources
  (`core/system.php:38-46`). A repository-side edit of any configuration file is
  therefore invisible until `config/local.php` is deleted. Saving from the admin
  panel handles this already: `setConfigFile()` writes the source file, unlinks
  the cache, and rebuilds it (`core/system.php:2516-2518`).

### Format policy for 2026 browsers

The configured list is rebuilt around one rule: a format stays only if every
engine in the support baseline can actually present it, or if it is an archive
that is merely downloaded. Everything else is withdrawn rather than carried as a
MIME entry, a fixture, a render template, and a code branch nobody can use.

Support baseline, pinned to versions rather than to "current", because major
releases keep moving during a multi-batch migration — Edge alone moved to a
two-week major cadence from 152. Fixed as of the 2026-08-03 revision:

| Engine | Minimum | What it decides |
|---|---|---|
| Safari / iOS / iPadOS | 18.4 | the only binding constraint: Ogg container support for Opus and Vorbis arrived here |
| Chrome / Edge | 85 | avif; every other kept format predates it |
| Firefox | 93 | avif; every other kept format predates it |

No decision below depends on a Chromium or Gecko release newer than those, so
their release cadence does not affect this plan. Re-evaluate the table only when
a format is added, and cite what specifically fails for each format rather than
a blanket claim.

Withdrawn, each on its own evidence:

| Extension | Why it goes |
|---|---|
| `swf` | Flash is end-of-life; the stored template emits `<embed type="application/x-shockwave-flash">`, which no engine runs. Also web-active, so the class denies it regardless of configuration |
| `ogv` | Ogg video means Theora. WebKit's Ogg support covers Opus and Vorbis audio only, Theora is not included, and the codec is superseded by VP9 and AV1 in `webm` |
| `ogx` | `application/ogg` is a multiplexed stream, not a media type `<audio>` or `<video>` can play in any engine |
| `spx` | Speex, deprecated by Xiph in favour of Opus, and outside the Opus/Vorbis support WebKit added |
| `m4p` | Apple FairPlay DRM container; never playable in a browser |
| `m4r` | iPhone ringtone container; no site use |
| `m4b`, `m4v` | the same MP4 container as `m4a` and `mp4`, kept only as spellings; the two canonical extensions cover them |
| `bmp` | uncompressed, superseded by `webp` and `avif`, and the thumbnail helper never produced thumbnails for it because `IMAGETYPE_BMP` has no branch |
| `wave` | obsolete spelling of `wav` |

`ogg`, `oga`, and `opus` are **kept**. An earlier revision of this plan withdrew
the whole Ogg family on the claim that WebKit does not support the container.
That is no longer true: WebKit added Ogg container support for both Opus and
Vorbis audio in Safari 18.4, on macOS Sequoia 15.4, iOS 18.4, iPadOS 18.4, and
visionOS 2.4. Within the stated baseline these three play everywhere, so they
stay. This is also why the withdrawals above are listed one per row: they fail
for different reasons, and a shared reason would have been wrong for most of
them.

Renamed, because the configured spelling is not a real extension: `7zip` becomes
`7z`; `gzip` becomes `gz`, in `config/files.php` `typefile` as well.

Added, all universally supported in 2026:

| Extension | Why it comes in |
|---|---|
| `webp` | already treated as an image by `core/system.php:2973`, `core/system.php:4243`, and the data-URI check at `core/classes/parser.php:176`, yet impossible to upload because no configuration listed it |
| `avif` | the current best-compression image format; Chrome 85+, Firefox 93+, Safari 16.4+. Verified available in the target build: `gd_info()` reports AVIF support and `imagecreatefromavif()` exists |
| `flac` | lossless audio played by every engine in the baseline |
| `pdf` | every browser has a built-in viewer, and a CMS with no PDF support is a gap, not a policy |

Deliberately not added: `svg` carries scripts and event handlers and would need a
sanitizer before it is safe; `jxl` is not supported by Chrome; `heic`/`heif` are
not rendered by browsers; `mov` depends on the codec inside and is covered by
`mp4`; `docx`/`xlsx` are ZIP containers that `finfo` reports as
`application/zip`, which would poison a strict MIME map.

Which of these extensions each setting actually enables is a separate question,
answered in `Two allowlist levels` below. Being in the canonical set means the
software can validate and render the format, not that every uploader may send
it.

Canonical set — 21 extensions, down from 26:

| Family | Extensions |
|---|---|
| Images | `avif`, `gif`, `jpeg`, `jpg`, `png`, `webp` |
| Audio | `flac`, `m4a`, `mp3`, `oga`, `ogg`, `opus`, `wav` |
| Video | `mp4`, `webm` |
| Documents | `pdf` |
| Archives | `7z`, `gz`, `rar`, `tar`, `zip` |

### Two allowlist levels

The canonical set is what the software supports. It is not what every visitor
may upload, and the two must not be conflated — an earlier revision of this plan
did exactly that and would have handed site visitors the right to upload PDF,
video, and archives through the editor.

| Setting | Who it governs | Value |
|---|---|---|
| `typ` | the global format list: drives the Templates tab and the render map | the full canonical set |
| `all` field 0 | admin upload from the Files tab, an operator-only route behind `isAdmin()` | the full canonical set |
| per-module field 0 | what visitors may upload into a module through the editor | `gif`, `jpg`, `jpeg`, `png`, `webp`, `avif`, `zip`, `rar` |
| `config/files.php` `typefile` | the file module's downloads | `zip`, `gz`, `7z`, `rar`, `tar` |
| `config/users.php` `atypefile` | avatars | `jpg`, `jpeg`, `gif`, `png` |

Per-module lists gain exactly `webp` and `avif` — direct equivalents of the
images they already allow — and nothing else. Widening them further is a product
decision nobody asked for, and this migration does not make it. An administrator
who wants a module to accept audio can still enable it on the Preferences tab;
the point is that the migration must not do it silently.

`atypefile` stays as it is for the same reason.

### Map

| Extensions | Accepted MIME types |
|---|---|
| `gif` | `image/gif` |
| `jpg`, `jpeg` | `image/jpeg` |
| `png` | `image/png` |
| `webp` | `image/webp` |
| `avif` | `image/avif` |
| `mp3` | `audio/mpeg` |
| `wav` | `audio/x-wav`, `audio/wav`, `audio/vnd.wave` |
| `flac` | `audio/flac`, `audio/x-flac` |
| `ogg`, `oga`, `opus` | `audio/ogg`, `application/ogg` |
| `m4a` | `audio/mp4`, `audio/x-m4a`, `video/mp4` |
| `mp4` | `video/mp4`, `application/mp4` |
| `webm` | `video/webm`, `audio/webm` |
| `pdf` | `application/pdf` |
| `zip` | `application/zip` |
| `rar` | `application/x-rar`, `application/vnd.rar` |
| `gz` | `application/gzip`, `application/x-gzip` |
| `7z` | `application/x-7z-compressed` |
| `tar` | `application/x-tar`, `application/tar` |

### Render templates

`config/filetype.php` is rebuilt in the same change, because half of its entries
are wrong even for the formats that stay: `m4a` and the whole Ogg family are
rendered through `<video>` with a `video/*` type, and every image entry carries
duplicated inline styling plus `class="screens"`, which no stylesheet or script
in the project consumes — verified across `templates/*/assets/css` and the theme
scripts. One template per family, nothing repeated:

| Family | Template |
|---|---|
| Images | `<a rel="[rel]" title="[title]" href="[src]" class="sl-attach sl-attach-[align]"><img src="[tsrc]" style="max-width:[twidth]px" alt="[title]" loading="lazy"></a>` |
| Audio | `<audio controls preload="metadata" src="[src]" title="[title]"></audio>` |
| Video | `<video controls preload="metadata" width="[width]" height="[height]" src="[src]" title="[title]"></video>` |
| Documents | `<object data="[src]" type="application/pdf" width="[width]" height="[height]"><a href="[src]">[title]</a></object>` |
| Archives | `<a href="[src]" target="_blank" rel="noopener" title="[title]">[title]</a>` |

Templates may only use tokens `filterAttach()` already substitutes — `[src]`,
`[tsrc]`, `[width]`, `[height]`, `[twidth]`, `[align]`, `[title]`, `[quot]`,
`[rel]` (`core/classes/parser.php:514-526`). That is why audio and video set
`src` directly on the element instead of wrapping a `<source>` with a `type`:
a per-extension MIME token does not exist, inventing one would mean teaching the
parser a new substitution and giving it a dependency on the upload class, and a
single-source element does not need the hint anyway — the engine sniffs the
file. A literal `type="<mime>"` in the output would be a rendering bug, and the
render test below exists to catch exactly that.

This constraint binds the templates this plan ships, not what an administrator
may later save. The Templates tab writes free-form HTML by design, is reachable
only behind `isAdmin()`, and an administrator who can save a template can
already run arbitrary code elsewhere in the panel — so validating tokens on save
would add a check without adding a boundary. The render test therefore asserts
against the shipped `config/filetype.php`, and a hand-edited template with an
unknown token is the administrator's own doing.

`[twidth]` stays inline because it is a per-module configuration value a
stylesheet cannot know; the float and spacing that were inline move into
`.sl-attach` and its three alignment modifiers, added to
`templates/lite/assets/css/base.css` and `templates/admin/assets/css/base.css`
using existing spacing tokens. The dead `screens` class is dropped. `<object>`
with a link fallback is used for PDF so engines that refuse inline PDF, notably
Safari on iOS, still show a working download.

`application/octet-stream` is never accepted. A configured extension absent
from this map returns `unsupported`. Executable and web-active formats
(`php*`, `phtml`, `js`, `htm`, `html`, `cgi`, `pl`, `perl`, `asp`, `swf`) are
denied even when configuration lists them.

The table above is the starting policy, not a verified constant: libmagic
output varies by build. The contract test ships one small fixture per extension
and asserts that the local `finfo` result is a member of the mapped set, so a
mismatch on the target build fails a test instead of silently rejecting user
uploads.

## Admin configuration surface

Everything this plan reads from `config/uploads.php` and `config/filetype.php`
is edited by administrators at `admin.php?name=uploads`. The class contract must
fit that page as it exists; the page is adjusted only where the migration makes
its current behavior wrong.

### What the page owns

| Tab | Route | Writes |
|---|---|---|
| Files | `name=uploads` | uploads a file into the selected directory (`userfile` or `sitefile`), no configuration |
| Templates | `op=tplconfig` / `op=tplsave` | rebuilds all of `config/filetype.php` |
| Preferences | `op=config` / `op=configsave` | rewrites all of `config/uploads.php` |
| Docs | `op=info` | edits `admin/info/uploads/<locale>.md` |

### Constraints this imposes

- The twelve-field string is reassembled verbatim by the form. The field order
  and count are part of the contract: `getUploadRuleData()` reads the six rule
  fields and passes the other six through untouched. This plan does not change
  the string format, add a field, or reorder it.
- The radio inputs for fields 10 and 11 are named by list position
  (`$i.'upload'` and `$i.'upguest'`), so `config()` and `configsave()` must walk
  the module list in the same order or the saved values shift to the wrong
  module. Batch 1 removed the hazard at its source: both once carried their own
  hardcoded copy of the list, and both now call `getUploadModuleList()`, which
  derives it from the records of `config/uploads.php` — every key whose value is
  a pipe-separated string, sorted, with `all` kept first. The derived list is
  identical to the two literals it replaced, index for index, so no stored value
  moved. A record added to the configuration file now appears on the tab instead
  of being silently dropped on the next save.
- Empty numeric inputs are replaced by the form defaults on save
  (`admin/modules/uploads.php:344-352`), so a limit can never actually be stored
  as `0` through the panel. The rule contract still treats `0` as "disabled",
  because a record can also reach the resolver from a hand-edited file where a
  field is empty. This is not a fallback: a directory without a record resolves
  to nothing at all — the admin flow asks for `'all'` by name, and an editor
  request naming an unconfigured module is refused.
- The directory dropdowns are built from `scandir(UPLOADS_DIR)`
  (`admin/modules/uploads.php:13`, `:238`), which lists directories such as
  `screens`, `clients`, and `avatars` that have no record in
  `config/uploads.php`.
- The general `all` record is the first editable block on the Preferences tab,
  and `admin/info/uploads/ru.md:53` already documents it as the universal
  setting applied to pages and third-party modules without their own upload
  configuration. No code reads it today: `$conf['uploads'][$mod]` is only ever
  looked up by module name. This plan gives it exactly one consumer, the admin
  upload flow, which requests it by name. It stays a named record, never an
  implicit fallback — see the security note in `Adapter contract`. The help text
  is corrected accordingly rather than treated as a specification.
- The upload form on the Files tab already renders both `userfile` and
  `sitefile` (`admin/modules/uploads.php:53-80`). Local-first/remote-second
  selection needs no markup change.

### Templates tab and the format cleanup

`tplsave()` does not edit `config/filetype.php` key by key. It rebuilds the
whole file by walking `explode(',', $conf['uploads']['typ'])` and pairing each
extension with the matching textarea (`admin/modules/uploads.php:219-222`).

Two consequences for the `swf`/`7zip` cleanup, both mandatory:

- Removing `swf` from `typ` makes the Templates tab drop its render entry on the
  next save, but the entry survives until then. Delete it from
  `config/filetype.php` in the same change instead of waiting for a save.
- Renaming `7zip` to `7z` or `gzip` to `gz` in `typ` without moving the render
  template creates an empty new block and permanently loses the old one on the
  next save. The same change must carry each template over to its new key.
- The same applies in reverse to `webp`: adding it to `typ` without adding a
  `config/filetype.php` entry leaves an empty template block for an otherwise
  working format.

### Free-form extension input

`configsave()` accepts any comma list for `typ`: it only lowercases the value
and strips whitespace (`admin/modules/uploads.php:317-318`), and per-module
lists get the same treatment at `:343`. An administrator can therefore configure
an extension the class has no MIME policy for, and every upload of that type
then fails with `unsupported` while the panel shows it as enabled.

Validate on save instead of failing silently: `configsave()` drops extensions
that are not in the class policy and reports the dropped values, and the
Preferences tab shows the supported list as a hint. The source is one new public
static method on the class, `Upload::getSupportedTypes(): array`, so the
allowlist still exists in exactly one place. Do not copy the extension list into
the admin module.

### Documentation

`admin/info/uploads/ru.md` is the Docs tab and currently describes the old
behavior. It is part of this change, not a follow-up. Keep its existing style:
Russian prose, `---` separators, `**bold**` labels, and the two GitHub-style
alerts it already uses. Do not introduce paired block BB tokens, which the
trusted-mode renderer consumes.

Required edits, by section:

| Line | Now | Must become |
|---|---|---|
| 12 | "укажите прямую ссылку на файл в интернете (система сама скачает его на сервер)" | same, plus: only `http`/`https`, only publicly routable addresses, at most three redirects, connect and total timeouts, and a size limit; the download is refused for local, private, and reserved addresses |
| 12 | no mention of precedence | add: a selected local file always wins; the link is only used when no file was chosen, and a rejected local file never falls back to the link |
| 20-22 | template list described as free-form per extension | add: the block list is generated from "Форматы приложений", and saving rebuilds the whole file, so removing or renaming an extension there drops or blanks its template |
| 39 | "Форматы приложений: список расширений" | add: only extensions the system has a type policy for are accepted; anything else is dropped on save and reported |
| 45 | "Максимальный размер одного файла и общий размер всех файлов" | add: the per-file limit also governs uploads made from this panel through the "Модуль: Все" record |
| 53 | TIP: `all` applies to pages and third-party modules | correct it: `all` supplies the rules for uploads made from the Files tab of this panel, whatever directory is selected. It is not applied automatically to modules that have no record of their own — those simply cannot be used as editor upload targets |
| 56 | IMPORTANT: keep dangerous extensions out of the list | add: file type is now verified by content, not only by extension, and executable or web-active formats are refused even if configured |
| new | — | the canonical 21-format list by family, and what changed: ten formats withdrawn, each for its own reason rather than one blanket claim (`swf` is dead, `ogv`/`ogx`/`spx` are not playable, `m4p`/`m4r` are unusable containers, `m4b`/`m4v`/`wave` are duplicate spellings, `bmp` is superseded), two spellings corrected (`7zip`→`7z`, `gzip`→`gz`), five added to the global list (`webp`, `avif`, `flac`, `pdf`, `tar`), of which modules gain only `webp` and `avif`; note that content already inserted with a withdrawn format still renders, as a plain link |
| new | — | one line stating that a guest no longer sees previously uploaded files in the editor file panel and only receives the files from the current upload |

## Adapter contract

Configuration paths such as `uploads/avatars` and `uploads/files/temp` are
public project-relative paths, while `Upload::$dir` is relative to
`UPLOADS_DIR`. Each adapter must strip exactly one leading `uploads/`, reject
an empty result and reject every path that does not resolve below
`UPLOADS_DIR`. Do not silently reinterpret absolute paths or paths outside the
upload root.

| Flow | Request file/input | Class directory and base | Rules | Owner | Stored reference |
|---|---|---|---|---|---|
| Account avatar | `$_FILES['userfile']` | `$conf['users']['adirectory']` normalized below the root; base `$conf['name']` | `$conf['users']` avatar keys | current positive user ID | filename only in `users.avatar` |
| Frontend files | `$_FILES['userfile']` | `$conf['files']['temp']` normalized below the root; base `files` | `$conf['files']` keys | current user ID or `0` | project-relative `uploads/<dir>/<file>` in `_files.url` |
| Admin files | `$_FILES['filesite']` | selected `path` field, else `$conf['files']['path']`, normalized below the root; base `files` | `$conf['files']` keys | `null` | project-relative `uploads/<dir>/<file>` in `_files.url` |
| Editor | normalized `$_FILES['file']` single or multi shape | module name below the root; base module name | `getUploadRuleData($mod)` | site user ID, `null` for a privileged moderator without a site user, otherwise `0` | existing editor JSON URL shape |
| Admin upload | `$_FILES['userfile']` or POST `sitefile` | selected directory below the root; base directory name | `getUploadRuleData('all')` | `null` | filesystem only |

Avatar and file-module rules keep their own configuration namespaces; they are
not moved into `config/uploads.php`.

Admin upload rule details, so no rule stays hardcoded at the call site:

- admin upload always resolves `$conf['uploads']['all']`, never the record of
  the module whose directory was selected. It writes into any directory under
  `UPLOADS_DIR`, including `screens`, `clients`, `avatars`, and thumbnail
  directories that have no module record at all, and it is an operator tool
  rather than a visitor-facing publisher. Binding it to the general record keeps
  one rule source for the whole flow and leaves per-module limits, which govern
  what visitors may upload, untouched.
- `getUploadRuleData()` itself has **no** fallback. An unknown key returns
  `ok=false`, exactly as `core/system.php:4226` does today, and only the admin
  adapter passes the literal `'all'`. An implicit fallback inside the resolver
  would be a security regression, not a convenience: the editor calls the same
  resolver with a request-derived `mod`, the `all` record permits authenticated
  uploads (field 10 is `1`), and any directory under `uploads/` would become a
  valid editor target — which is precisely the generic `go=4` path this plan
  removes.
- `maxbytes` reads field 2, the documented "maximum size of one file". Field 1
  is the module storage quota and must not be reused as a file-size limit the
  way `admin/modules/uploads.php:161` does today. `maxquota` is passed as 0,
  because admin upload enforces no quota now and introducing one would be an
  unrelated behavior change.
- `maxwidth`/`maxheight` read fields 3 and 4 instead of the hardcoded 1600.
- The `all` record is raised to match, but in two steps, because limits and
  allowlists carry different risk:
  - **Batch 8**, with the adapter: field 2 from `1048576` to `104857600`, and
    fields 3 and 4 from `500` to `1600`. These preserve the limits the call site
    hardcodes today, so the flow keeps behaving as it does now.
  - **Batch 9**, with every other allowlist: field 0 from
    `gif,jpg,jpeg,png,zip,rar` to the full canonical set. Admin upload is the
    operator's file manager, reached only behind `isAdmin()`, and it is the
    route the Files tab exists for; restricting it to six formats while the CMS
    supports twenty-one would leave the panel unable to place a PDF or a video
    on the site. Between the two batches admin upload accepts only those six
    formats — a temporary narrowing, not a widening, so nothing is exposed.
- No other record is touched in batch 8. Raising field 2 elsewhere would raise
  the per-file limit for visitor uploads, and widening another field 0 would
  widen what visitors may send — see `Two allowlist levels`.

For admin upload, a submitted local file always wins. If it is malformed or
fails validation, return that failure and do not fall back to `sitefile`.
Process the remote URL only when no local file was submitted.

For frontend and admin file records, a submitted local file wins over a typed
external URL. A rejected local file must not silently fall back to that URL.

## Mandatory invariants

### Validation and paths

- `$dir` and returned `path` are always relative to the upload root.
- Extensions are normalized to lowercase and matched against the exact
  configured allowlist, then against the MIME map in `Format policy`.
- Images must decode successfully and satisfy configured dimensions. The class
  must not carry over the `@getimagesize` suppression used at
  `core/system.php:4245`; `@` is forbidden by `.rules/global.md`.
- The destination must resolve below `UPLOADS_DIR`; reject absolute paths,
  traversal, NUL/control bytes, symlink escapes, missing directories, and
  unwritable directories.
- The sanitized base uses `[a-zA-Z0-9_]+`; the random segment uses a fixed
  length and `[a-zA-Z0-9]+`. Stored names are
  `<base>-<random>-<uid>.<ext>` for integer owners and
  `<base>-<random>.<ext>` for `null`.
- On a name collision the class regenerates the random segment and retries up
  to five times under the destination lock, then returns `exists`. This
  preserves the retry behavior at `core/system.php:4336` and replaces the
  immediate `_ERROR_EXIST` of `core/system.php:5354`.
- Editor ownership parsing must use exactly that grammar. Moderators may list
  all canonical files, authenticated users only their positive owner suffix,
  and guests no historical files.
- `deleteStoredFile()` accepts a root-relative result path, verifies the exact
  canonical class-owned grammar, repeats containment checks, uses the same
  destination lock, and never follows a path outside the upload root.

### Publication and quota

- All publications to one destination use the same OS lock, whether quota is
  enabled or not. Derive the lock filename from the canonical destination.
- Transfer into a unique `.upload-<hex>.part` in the final destination first.
  Then hold the destination lock while removing class partials older than one
  hour, checking quota in constant memory, and selecting the final name. The
  one-hour partial sweep applies to local and remote publication alike.
- Publish with one primitive and no alternatives: still holding the destination
  lock, confirm with `file_exists()` that the chosen final name is free, then
  `rename($partial, $final)`. Partial and final always live in the same
  directory, so the rename never crosses a filesystem and replaces the file
  atomically for readers.
- The destination lock, not a filesystem primitive, provides mutual exclusion.
  Every writer of this class serialises on it, and nothing outside the class
  creates names matching the canonical grammar with a freshly drawn random
  segment, so the existence check and the rename act as one step.
- Two alternatives were considered and rejected; do not reintroduce either.
  `link()` is frequently disabled on shared hosting, which would make every
  upload there `unsupported`. Reserving the name with `fopen($final, 'xb')` is
  worse: on Windows `rename()` over the still-open reservation fails with access
  denied, verified on the target PHP 8.4 build, and closing the handle first
  leaves a zero-byte file under the canonical name, which `editorFiles` and the
  admin listing scan without holding the destination lock and would briefly
  report as a real file.
- A failure anywhere before the rename creates no final file. If the rename
  itself fails, delete the partial and return `write`. There is no partially
  published state, and no successful result ever carries an error.
- Check the result of that delete. A partial can survive a failed `unlink()` —
  a read-only directory, a lock held by a scanner — and pretending otherwise
  would make the guarantee untestable. On failure log through `Logger::addFile()`
  with the root-relative partial path and still return `write`: the caller's
  outcome does not change, but the stranded file is visible in
  `error_file.log` and is retried by every subsequent sweep of that
  destination. Do not promise it will be gone within the hour: whatever stopped
  the delete — a read-only directory, a held handle — can equally stop the
  sweep. Each retry that fails is logged again, so the condition stays visible
  instead of being quietly assumed away.
- Exclude `.upload-*.part`, `index.html`, and `.htaccess` sentinels from quota.
  Count the incoming file exactly once.
- Local files must pass the complete PHP upload error and
  `is_uploaded_file()` checks before publication.
- A batch that exceeds `maxfiles` performs no transfer. Quota is rechecked
  under the destination lock for every accepted file, so concurrent batches
  cannot oversubscribe it.
- Because a file is fully written to its partial before the quota check, N
  concurrent uploads can transiently occupy up to N times `maxbytes` on disk
  above the quota. This is bounded by `maxbytes` and accepted; the partials are
  removed on rejection and swept after one hour.

### Adapter write ordering and compensation

- Require the expected HTTP method, authorization, CSRF token, operation, and
  all non-file business validation before calling `Upload`.
- Preview, invalid-token, failed-captcha, and other rejected/non-save paths must
  not call `Upload` and must leave filesystem and database state unchanged.
- Existing delete operations must not call `Upload` or publish a new file; they
  retain their existing deletion behavior outside this migration.
- The account avatar flow requires POST and a valid token for the whole
  operation, preset selection included; the preset branch itself still does not
  call `Upload`. Changing the handler alone breaks the page: presets are
  rendered as GET links at `modules/account/index.php:920-928`, so each one
  becomes a POST submit carrying the `account` token. The row cells feed
  `templates/lite/fragments/table-row.html`, whose `is_avatar_link` branch emits
  an `<a>`; the fragment needs a form/button variant, and the `sl-avatar-link`
  styling must follow it so the grid looks unchanged.
- The admin files handler must not publish and then relocate in one request.
  When a file is submitted, pass the selected `path` field to `Upload` as the
  destination so the file is published where it belongs; the
  `rename()`-after-publish branch at `modules/files/admin/index.php:247-255`
  becomes dead for that case and is removed. Relocation of an already stored
  file with no new upload keeps its current behavior. Without this, the
  compensation rule below would delete a path that no longer exists and leave
  the orphan the plan exists to prevent.
- After a successful publication, use returned `size` and `path` metadata
  instead of probing a request-controlled path.
- Check every database write result. If the write fails, delete only the exact
  root-relative `path` returned by the current `Upload` call.
- If compensation succeeds, report the database failure. If compensation
  fails, report the database failure plus the compensation failure and log it with
  `Logger::addFile()` without logging file contents or the remote URL query.
- Existing records, legacy files, and external URLs are never passed to
  `deleteStoredFile()` as compensation.

### Remote upload

Use ext-cURL only:

- parse and normalize the URL before DNS; allow only `http` and `https`, reject
  credentials, fragments, malformed/ambiguous hosts, and non-default ports;
- resolve CNAME chains with a fixed limit, reject loops, and validate every
  resolved A/AAAA address against private, loopback, link-local, reserved, and
  otherwise non-public ranges;
- choose a validated address deterministically, resolve every redirect again,
  allow at most three redirects, and discard intermediate response bodies;
- disable environment proxies, restrict cURL protocols to HTTP/HTTPS, pin the
  validated address with `CURLOPT_RESOLVE`, and verify the connected primary
  IP against that address;
- keep TLS peer and host verification enabled; use a 5-second connect timeout
  and 30-second total timeout;
- enforce both `Content-Length` and streaming byte limits without accumulating
  the response in memory;
- accept only a final 2xx response, derive the extension from the normalized
  final URL path, and run the same MIME, image, quota, naming, and publication
  checks as a local upload.

Keep DNS resolution and cURL execution behind narrow protected methods, joining
the clock seam that local publication already needs for the one-hour partial
sweep. Unit tests replace all three through a test-only subclass; production
code uses the unchanged public constructor and methods.

## Current findings

| Priority | Finding | Evidence |
|---|---|---|
| Critical | The reachable admin remote branch accepts a raw URL through a PHP stream, buffers it without a streaming limit, and performs no SSRF validation; the unused type-4 branch is unsafe as well. | `core/system.php:5409-5455`, `core/system.php:5456-5476` (`upload()`) |
| High | Extension checks use substring regular expressions rather than an exact MIME/content policy. | `core/system.php:5317-5321` (`check_file()`), `core/system.php:5332-5479` (`upload()`) |
| High | The generic default branch inside `go=4` exposes an unintended upload path and raises PHP warnings for an unknown module. | `index.php:180-183` |
| High | Editor upload duplicates validation, quota, naming, and storage rules. | `core/system.php:4276-4344` (`addEditorUpload()`) |
| High | Destination construction accepts absolute and project-relative paths without one upload-root containment boundary. | `core/system.php:5334-5339` (`upload()`) |
| High | Account and file handlers may publish before failed CSRF/business validation, preview selection, and unchecked SQL writes, leaving orphans. | `modules/account/index.php:1052-1064`, `modules/files/index.php:525-545`, `modules/files/admin/index.php:231-260` |
| High | The account avatar token is only checked when `op` is posted, so a GET request sets a preset avatar with no token. | `modules/account/index.php:1052`, `modules/account/index.php:1064` |
| High | Guest editor ownership collapses to suffix `-0`, allowing one guest to enumerate other guest files when guest upload is enabled. | `core/system.php:4357-4364` (`getEditorFileJson()`) |
| Medium | The admin files handler publishes a file and then renames it in the same request, before the SQL write, so any compensation keyed on the returned path would miss. | `modules/files/admin/index.php:238`, `modules/files/admin/index.php:247-255` |
| Medium | Admin upload renders local and remote inputs but invokes only the remote type-3 branch, so local upload has no explicit working path. | `admin/modules/uploads.php:53-80`, `admin/modules/uploads.php:157-167` |
| Medium | Admin upload hardcodes its extension list, size, and dimensions at the call site instead of reading configuration. | `admin/modules/uploads.php:161` |
| Medium | Admin files uses multipart field `filesite`, while the procedural single-file branch expects `userfile`. | `modules/files/admin/index.php:192`, `core/system.php:5340-5374` |
| Medium | The upload configuration string is split by hand in five places, each repeating the index meanings. | `core/system.php:4237-4238`, `core/system.php:4291-4296`, `core/system.php:4359`, `admin/modules/uploads.php:161`, `plugins/editors/toastui/driver.php:66` |
| Medium | The Preferences tab stores any extension an administrator types, with no check that a MIME policy exists, so a configured type can silently never upload. | `admin/modules/uploads.php:317-318`, `admin/modules/uploads.php:343` |
| Low | The Templates tab rebuilds `config/filetype.php` from the `typ` list, so renaming or removing an extension there silently drops or blanks its render template. | `admin/modules/uploads.php:219-222` |
| Medium | Ten of the 26 configured formats cannot be presented by a browser engine in the support baseline, and two spellings are not real extensions (`7zip`, `gzip`). | `config/uploads.php:26`, `config/filetype.php:9-86`, `config/files.php` |
| Medium | Render templates are wrong for formats that do work: `m4a` and the whole Ogg family are emitted as `<video>` with a `video/*` type. | `config/filetype.php:27-62` |
| Low | `webp` is treated as an image by three code paths but cannot be uploaded, because no configuration lists it and the parser image list omits it. | `core/system.php:2973`, `core/system.php:4243`, `core/classes/parser.php:176` against `core/classes/parser.php:481`, `config/uploads.php:26` |
| Low | `create_img_gd()` decodes `IMAGETYPE_SWF` through `imagecreatefromwbmp()` and has no webp or avif branch, so modern images silently fall back to the full-size file. | `core/system.php:5651-5656`, `core/system.php:5671-5674` |
| Low | Every image render template repeats the same inline styling and tags a `screens` class that no stylesheet or script consumes. | `config/filetype.php:13`, `:16`, `:22`, `:25`, `:64` |
| Low | `config/uploads.php` carries `album` and `info` records, but neither module exists under `modules/`. The admin module lists that duplicated the same two names are gone since batch 1; only the records remain. | `config/uploads.php:10`, `config/uploads.php:20` |

## Execution batches

This is the only ordering in the document. Each batch is one working session,
and ends with the self-check below — a batch that has not passed it is not
finished. Never start a batch without finishing the previous one it depends on.

Finish a batch within its session. If, partway in, it becomes clear the batch is
too large, do not improvise a stopping point: split it into named sub-batches at
a boundary where the self-check can pass, write the split into this document as
`8a`, `8b`, record the completed one on the `Last completed batch` line, and
stop. The rule is not "never stop early" but "never leave an unnamed, unrecorded
partial state" — the next session starts from this document alone.

Read the whole document once, then re-read only the sections a batch names.

### Intermediate states

This runs on a debug stand, so a batch does not have to leave every flow
working. Do not spend effort keeping the old pipeline alive next to the new
one: delete superseded code in the batch where its last caller disappears, and
do not build shims, feature switches, or temporary dual paths to keep an
unmigrated flow running in the meantime. A half-migrated tree between batches is
expected and acceptable.

What this does **not** relax, because it is about the finished result rather
than the road to it:

- stored reference formats — `users.avatar` filenames, project-relative
  `_files.url` values, and the editor JSON shape stay exactly as specified;
- the security invariants in `Mandatory invariants`, which are the point of the
  migration and are never "temporarily" skipped;
- `php -l`, PHPStan, and PHP-CS-Fixer, which must pass in every batch — they are
  cheap and stop broken syntax from accumulating.

Functional route checks are expected to fail for flows a batch has not reached
yet. Say which ones in the report instead of working around them.

### Self-check after every batch

A batch is not finished when the edits are made; it is finished when this list
has been walked. Run it before writing the report, in this order. Each batch
also names its own `Verify` steps — those are additional, not a replacement.

1. Re-read this document's section for the batch. A long session drifts, and the
   plan is the specification, not memory of it.
2. Read your own `git diff` in full. Every changed line must trace to a bullet
   of the current batch. Anything that traces to a later batch, or to nothing,
   comes out — scope creep is a defect even when the code is good.
3. Check the diff against `.rules/global.md`: variable names 2-8 lowercase
   letters, `verb + noun` function names, comment placement, no `@`, no
   `declare(strict_types=1)`, `exit;` after a direct `header('Location: ...')`,
   no `exit;` after `setRedirect()`, prepared statements with named
   placeholders, input through `getVar()`.
4. Grep your own diff for leftovers: `var_dump`, `print_r`, `TODO`, `FIXME`,
   commented-out code, debug logging, temporary files outside the scratchpad.
5. Run the static checks — `php -l` on every touched file, PHPStan, PHPUnit,
   PHP-CS-Fixer dry run — and read the output. These must pass in every batch.
6. Run the batch's own `Verify` steps, including log inspection where the batch
   changed state.
7. `rg` for whatever this batch was supposed to remove, and confirm it is gone.
8. Confirm the batch's `Done when` line is literally true. If it is not, the
   batch is not done: finish it or stop and report the gap.

Report what was actually run and what it printed. Never record a check that was
not executed, and never present a passing static check as evidence that a flow
works. If the self-check finds a problem, fix it inside the same batch rather
than carrying it forward — a defect handed to the next session costs far more
than one caught here.

| # | Batch | Depends on | Touches | Delivers |
|---|---|---|---|---|
| 1 | Rule resolver | — | `core/system.php`, 4 consumers, `composer.json` | one parser for the config string |
| 2 | Image pipeline | — | image lists, thumbnail helper, both `base.css` | webp/avif thumbnails, one styling hook |
| 3 | `Upload` class, local half | 1 | `core/classes/upload.php`, `core/system.php`, tests | validation, naming, publication, quota |
| 4 | `Upload` class, remote half | 3 | same class, tests | SSRF-safe remote fetch |
| 5 | Editor adapter | 3 | `core/system.php`, `index.php` | editor migrated, `go=4` closed, editor duplicates deleted |
| 6 | Account avatar | 3 | `modules/account`, one fragment, CSS | avatar migrated, presets POST-only |
| 7 | File modules | 3 | `modules/files`, `modules/files/admin` | both file flows migrated |
| 8 | Admin upload and settings | 4 | `admin/modules/uploads.php`, help | last flow migrated, old pipeline deleted |
| 9 | Format switch | 2, 5, 6, 7, 8 | `config/*`, `config/filetype.php` | the 2026 format set goes live |
| 10 | Final sweep | all | verification only | proof that nothing legacy survives |

Batches 1 and 2 are independent of each other and of the class; 5, 6 and 7 are
independent of each other once 3 is done.

The format switch is deliberately last among the changes. Widening the allowlist
adds types that are accepted on the strength of their extension alone until the
class owns validation, and the class only owns every flow after batch 8. Batch 2
therefore prepares the code that new formats need — thumbnails, image lists,
styling — without touching a single allowlist, and batch 9 flips configuration
and rendering together, when a rejected MIME is a real rejection.

### Batch 1 — Rule resolver

Read: `Rule resolution`, `Admin configuration surface`.

- Rename `getEditorUploadData()` to `getUploadRuleData()`, widen its return to
  all twelve named keys from `Rule resolution`, and keep the unknown-key
  behavior returning `ok=false`.
- Add `setUploadRuleData()` and move `configsave()` onto it. No adapter may keep
  indexing `$con[N]` afterwards.
- Update `checkEditorUploadAccess()` to take the resolved array.
- Migrate all ten call sites in the table in `Rule resolution`.
- Add `ext-fileinfo` and `ext-curl` to `require` in `composer.json`.

Verify: `php -l`, PHPStan, PHPUnit; save the admin Preferences tab and confirm
every field of every module survives the round trip byte for byte;
`rg "explode\('\|'"` shows no upload-config split outside the resolver.

Done when: exactly one function parses the string and exactly one assembles it.

### Batch 2 — Image pipeline

Read: `Format policy for 2026 browsers` for the canonical image list, `Removal`.

This batch changes no allowlist; it only makes the code able to handle the
formats batch 9 will enable.

- Rename `create_img_gd()` to `getImageThumb()` and update its only caller at
  `core/classes/parser.php:501`.
- In that function drop the `case 4` / `IMG_WBMP` branch and add
  `IMAGETYPE_WEBP` and `IMAGETYPE_AVIF` behind `function_exists()`.
- Bring the four image lists up to date. They do not currently hold the same
  contents, so the edit differs per list:

  | List | Holds today | Add here | `bmp` |
  |---|---|---|---|
  | `core/system.php:2973` | png, jpg, jpeg, gif, webp, bmp | `avif` | leave |
  | `core/system.php:4243` | png, jpg, jpeg, gif, bmp, webp | `avif` | leave |
  | `core/classes/parser.php:481` | png, jpg, jpeg, gif, bmp | `webp`, `avif` | leave |
  | `core/classes/parser.php:176` (data URI) | png, jpe?g, gif, webp | `avif` | **do not add** — it is absent today, and adding it would newly permit BMP in a data URI |

- **Leave `bmp` where it already is.** Those lists decide whether a file is
  decoded and dimension-checked at all: `getEditorImageData()` returns
  `image => false` and skips `getimagesize()` entirely for an extension outside
  its list (`core/system.php:4243-4244`). Dropping `bmp` while it is still an
  allowed extension would accept bmp uploads with neither a decode nor a
  dimension check — a `Mandatory invariants` violation, and those are never
  relaxed for convenience. `bmp` leaves the three lists in batch 9, in the same
  edit that removes it from the allowlists.
- Add `.sl-attach` and its alignment modifiers to both themes' `base.css`.

Verify: `php -l`, PHPStan, PHPUnit, the thumbnail tests. Call the thumbnail
helper directly on a webp and an avif fixture — do not try to reach it through
the admin panel, whose local upload does not work until batch 8. The test that
the four lists match the canonical set belongs to batch 9; until `bmp` and the
allowlists move together, the lists are neither equal to that set nor equal to
each other, and that is correct here.

Done when: no WBMP or `IMAGETYPE_SWF` branch remains, all four lists accept avif,
all three non-data-URI lists accept webp, `bmp` is still accepted everywhere it
was, and webp and avif fixtures produce thumbnails.

### Batch 3 — `Upload` class, local half

Read: `Architecture`, `Public contract`, `Error codes and messages`,
`Validation and paths`, `Publication and quota`.

- Add `core/classes/upload.php` with everything except `addRemoteFile()`.
- Add the protected clock seam here, not in batch 4: the one-hour partial sweep
  is part of local publication, and its test needs a controllable clock.
- Add `getUploadService()` and `Upload::getSupportedTypes()`.
- Add `tests/Unit/UploadContractTest.php` and
  `tests/Support/upload_probe.php` covering the local checks listed in
  `Automated checks`.

Verify: `php -l`, PHPStan, PHPUnit, PHP-CS-Fixer dry run.

Done when: every local result code has a test, and the publication tests prove
that a failed rename publishes no final file and removes the partial — or, when
the filesystem refuses, logs the stranded path and still returns `write`.

### Batch 4 — `Upload` class, remote half

Read: `Remote upload`, `Automated checks`.

- Add `addRemoteFile()` and the protected DNS and cURL seams; the clock seam
  already exists from batch 3.
- Add the remote tests through a test-only subclass.

Verify: PHPUnit; no network access in unit tests.

Done when: every rejection path in `Remote upload` has a test.

### Batch 5 — Editor adapter

Read: `Adapter contract`, `Adapter write ordering and compensation`, the editor
rows of the route matrix.

- Rewrite `addEditorUpload()` as a thin adapter over `addUploadedFiles()`.
- Apply the guest listing restriction in `getEditorFileJson()`.
- Replace the generic `go=4` default with `http_response_code(400)` plus
  `getEditorJson(['ok' => false, 'error' => _ERROR])`.
- Delete the duplicated validation, quota, naming, and storage block that
  `addEditorUpload()` no longer needs, and the duplicated access test in
  `plugins/editors/toastui/driver.php:66`.

Verify: the four editor rows of the route matrix, including the unconfigured
module case; then inspect the four logs.

Done when: moderator, user and guest behave per the matrix, no unknown
operation reaches an upload, and no editor upload logic is duplicated.

### Batch 6 — Account avatar

Read: the avatar rows of the route matrix and the avatar bullets in
`Adapter write ordering and compensation`.

- Gate the whole handler behind POST and a valid `account` token.
- Convert the preset links at `modules/account/index.php:920-928` to POST
  submits, extend the `is_avatar_link` branch of
  `templates/lite/fragments/table-row.html`, and carry the styling over.
- Move the `Upload` call after every check; use the returned metadata; check the
  SQL result and compensate the returned path.

Verify: both avatar rows of the route matrix, upload and preset, positive and
negative; confirm the preset grid looks unchanged.

Done when: no avatar path changes state without POST and a token.

### Batch 7 — File modules

Read: the two file rows of the route matrix and the relocation bullet in
`Adapter write ordering and compensation`.

- Migrate `modules/files/index.php` `send()` and
  `modules/files/admin/index.php` `save()`.
- Publish admin files straight into the selected `path` and delete the
  publish-then-rename branch for that case.
- Preserve `_files.url` shapes exactly; check SQL results; compensate only the
  returned path.

Verify: both file rows of the route matrix including preview, delete, and the
external-URL fallback checks; compare database rows before and after.

Done when: no preview or rejected save leaves a file behind.

### Batch 8 — Admin upload and settings

Read: the admin upload bullets in `Adapter contract`,
`Free-form extension input`.

- Raise the `all` record: field 2 to `104857600`, fields 3 and 4 to `1600`.
  Leave field 0 alone — it widens in batch 9 with every other allowlist.
- Migrate `uploadsave()` to local-first/remote-second with rules from
  `getUploadRuleData('all')`. This is what makes admin local upload work at all;
  today the route renders a `userfile` field and then calls the remote-only
  branch.
- Validate extension lists in `configsave()` against
  `Upload::getSupportedTypes()`, report what was dropped, show the supported
  list as a form hint.
- This is the last caller, so delete `upload()`, `check_file()`, and
  `check_size()` here rather than leaving them for the sweep.

Verify: both admin upload rows of the route matrix plus the two settings-save
rows.

Done when: every flow uses the class, no rule is hardcoded at a call site, and
the procedural pipeline is gone.

### Batch 9 — Format switch

Read: `Format policy for 2026 browsers`, `Render templates`, `Removal`,
`Documentation`.

Every flow now validates by content, so widening the allowlist is safe.

- Apply `Two allowlist levels` exactly: `typ` and the `all` record's field 0 get
  the full canonical set; every other per-module field 0 gets only `webp` and
  `avif` added to what it already allows; `config/files.php` `typefile` only has
  `gzip` corrected to `gz`. Do not widen a per-module list any further.
- Remove `bmp` from the three lists that hold it — `core/system.php:2973`,
  `core/system.php:4243`, `core/classes/parser.php:481` — in the same edit that
  removes it from the allowlists, so it is never allowed without being
  validated. The data-URI regex never held it. All four then read exactly
  `avif`, `gif`, `jpeg`/`jpg`, `png`, `webp`; add the test that asserts it.
- Drop the `album` and `info` records from `config/uploads.php`. Nothing else is
  needed: batch 1 replaced both hardcoded module lists with
  `getUploadModuleList()`, so the tab follows the records.
- Rewrite `config/filetype.php` to the five family templates, moving the `7zip`
  and `gzip` templates onto their corrected keys.
- Delete `config/local.php` so the cache rebuilds.
- Rewrite `admin/info/uploads/ru.md` per the table in `Documentation`.

Verify: the format, render, and MIME-fixture tests; upload one file per family
through the admin panel — which works now, and whose `all` record accepts all
five families — and check the rendered output of each; confirm a module editor
still refuses a format outside its own narrower list; both Preferences and
Templates tabs round-trip; confirm the help page renders.

Done when: no withdrawn format or `screens` class remains in the executable and
configuration paths listed in batch 10 — the help text and the negative tests
are expected to name them — every canonical extension uploads and renders, and
no rendered attachment contains an unresolved token.

### Batch 10 — Final sweep

Read: `Removal`, `Acceptance`.

This batch verifies rather than builds; each deletion happened in the batch
where its last caller disappeared. Anything the sweep still finds is a leftover
from an earlier batch, so fix it here.

- Run the dependency sweep: `rg` for every deleted symbol, for withdrawn format
  names, for the `screens` class, and for orphaned language constants.
- Scope those searches to executable and configuration paths — `core/`,
  `modules/`, `admin/modules/`, `plugins/`, `templates/`, `config/`, `index.php`
  — and exclude `docs/`, `admin/info/`, and `tests/`. A withdrawn format name is
  *supposed* to appear in the help text that announces its withdrawal and in the
  negative tests that prove it is refused; a literal project-wide search can
  never come back empty and would train the reader to ignore it.

Verify: `php -l`, PHPStan, PHPUnit, PHP-CS-Fixer dry run,
`tests/UnusedCodeAuditTest.php`, and the `rg` proofs; then walk **every** row of
the route matrix, including flows that earlier batches were allowed to leave
broken, and inspect all four logs.

Done when: nothing in `Acceptance` is outstanding.

## Removal

The migration is not finished until the superseded code and configuration are
gone. No aliases, no wrappers, no deprecated markers.

Delete each item in the batch where its last caller disappears, not in a final
cleanup pass: the WBMP branch in batch 2, the editor duplicates in batch 5, the
procedural pipeline in batch 8, the configuration and rendering entries in batch
9. Batch 10 only proves the result.

Delete:

- `upload()` (`core/system.php:5332-5479`);
- `check_file()` (`core/system.php:5317-5321`);
- `check_size()` (`core/system.php:5324-5328`);
- the duplicated validation, quota, naming, and storage block inside
  `addEditorUpload()` (`core/system.php:4297-4342`), which becomes a thin
  adapter over `addUploadedFiles()`;
- the generic `default:` upload branch in `go=4` (`index.php:180-183`);
- the publish-then-rename branch in the admin files save handler
  (`modules/files/admin/index.php:247-255`) for the new-upload case;
- the duplicated `$con[10]`/`$con[11]` access test in
  `plugins/editors/toastui/driver.php:66`, replaced by
  `checkEditorUploadAccess()`.

Keep, with a narrowed role:

- `getEditorImageData()` (`core/system.php:4242-4251`) is still used by
  `getEditorFileData()` for listing metadata; it stops being an upload
  validator. Re-check its remaining callers before touching it.

Configuration and rendering cleanup, per `Format policy for 2026 browsers`:

- rewrite `config/uploads.php` `typ` and the `all` record's field 0 to the
  21-extension canonical set, add only `webp` and `avif` to every other
  per-module field 0, and change `gzip` to `gz` in `config/files.php`
  `typefile`, per `Two allowlist levels`;
- rewrite `config/filetype.php` to five family templates, dropping the ten
  withdrawn entries, the duplicated inline styling, and the dead `screens`
  class, and adding `avif`, `webp`, `flac`, `pdf`, and `tar` — `tar` is new to
  `typ` and therefore new to the render map too, and takes the archive
  template;
- add `.sl-attach` and its alignment modifiers to
  `templates/lite/assets/css/base.css` and
  `templates/admin/assets/css/base.css` using existing spacing tokens;
- bring the four image-extension lists to the canonical image set. The four do
  not hold the same contents today, so follow the per-list table in `Batch 2`
  rather than applying one blanket edit: additions land in batch 2, `bmp` is
  removed in batch 9, and the data-URI regex never receives `bmp` at all;
- rename `create_img_gd()` to `getImageThumb()` and update its only caller
  (`core/classes/parser.php:501`). The function is being rewritten in this
  change, and `.rules/global.md` forbids `_` in function names and requires a
  `verb + noun` name for code that is rewritten, not only for new code;
- in that same function (`core/system.php:5648-5709`) delete the `case 4`
  branch, which decodes `IMAGETYPE_SWF` through `imagecreatefromwbmp()`, drop
  `IMG_WBMP` and `imagewbmp()` entirely, and add `IMAGETYPE_WEBP` and
  `IMAGETYPE_AVIF` branches guarded by `function_exists()` like the existing
  ones, so a GD build without them degrades to the full image instead of
  failing;
- remove the `album` and `info` records from `config/uploads.php`; the tab
  derives its module list from those records since batch 1, so nothing in
  `admin/modules/uploads.php` has to be edited with them;
- raise the `all` record: field 2 to `104857600`, fields 3 and 4 to `1600`, and
  field 0 to the canonical set, so admin upload keeps its limits after moving off
  the hardcoded call-site values and can still place any supported format. Leave
  every other record's limits alone;
- delete `config/local.php` after any repository-side configuration edit so the
  cache is rebuilt; verify afterwards that the Preferences and Templates tabs
  still round-trip every value.

Dependency sweep after the deletions:

- `rg` for every deleted symbol name across `*.php`;
- `rg` for `$stop` handling that only existed to carry `upload()` failures
  (`admin/modules/uploads.php:158-166`, `modules/account/index.php:1049`) and
  simplify what became unreachable;
- `rg` each upload-related language constant (`_ERROR_BIG`, `_ERROR_DOWN`,
  `_ERROR_EXIST`, `_ERROR_FILE`, `_ERROR_SIZE`, `_ERROR_UP`, `_FSIZEALL`,
  `_UPLOADEROR2`, `_FILEUP`) outside `lang/`. The mapping in
  `Error codes and messages` is designed to keep all of them in use; if one is
  nevertheless orphaned, remove it from all six locales in the same commit as
  required by `.rules/constants.md`;
- run `tests/UnusedCodeAuditTest.php` to confirm nothing newly dead remains.

## Verification

### Automated checks

- `php -l` for every changed PHP file;
- project PHPStan, PHPUnit, and PHP-CS-Fixer dry-run checks;
- `tests/Unit/UploadContractTest.php` and `tests/Unit/UploadIntegrationTest.php`
  with `tests/Support/upload_probe.php`, following the existing
  `BackupContractTest`/`BackupIntegrationTest`/`backup_probe.php` pattern;
- unit tests for every result code, malformed single/multi `$_FILES`, upload
  error values, exact allowlists, MIME fixtures per mapped extension, image
  decode and dimensions, traversal, symlink escape, canonical naming, collision
  retry and exhaustion, quota boundaries, concurrent writers, partial cleanup
  and the one-hour sweep, and compensation;
- remote unit tests for URL normalization, DNS/CNAME policy, redirects, proxy
  bypass, protocol limits, primary-IP verification, timeout, body streaming,
  final-URL extension policy, and private-address rejection;
- a rule-resolution test that asserts every one of the twelve named fields still
  reaches its consumer after the rename, that
  `setUploadRuleData(getUploadRuleData($mod))` returns the original string byte
  for byte for every twelve-field module record — `config/uploads.php` also
  holds the scalars `typ`, `dir`, `width`, and `height`, which are not module
  records and must be excluded — that an unknown key returns
  `ok=false` and never silently resolves `all`, and that a short or malformed
  string does not fatal;
- a publication test that a `rename()` failure leaves no final file, deletes the
  partial, and returns `write`, and that no code path opens the final name
  before renaming onto it; plus a cleanup-failure case where the partial cannot
  be deleted, asserting the call still returns `write` and the stranded path is
  logged;
- a test that `Upload::getSupportedTypes()` covers every extension configured in
  `config/uploads.php`, `config/files.php`, and `config/users.php` after the
  cleanup, so no live format is disabled by omission and no withdrawn format
  survives in a configuration file;
- a test that, from batch 9 onward, the four runtime lists —
  `core/system.php:2973`, `core/system.php:4243`, `core/classes/parser.php:481`,
  and the data-URI regex at `core/classes/parser.php:176` — and the image
  entries of the MIME map all agree on the canonical image set. The four runtime
  lists drift today: `webp` uploads were impossible while three of them already
  accepted it, and `bmp` sits in three but not in the data-URI regex. The MIME
  map does not exist until batch 3, which is why the test starts at batch 9;
- a thumbnail test proving `getImageThumb()` produces webp and avif thumbnails,
  degrades to the full image when the GD build lacks either, and no longer
  contains a WBMP or `IMAGETYPE_SWF` branch;
- a test that `config/filetype.php` has exactly one entry per canonical
  extension, that no entry renders audio through `<video>`, and that no entry
  contains the `screens` class;
- a render test that feeds one `[attach=...]` per family through
  `filterAttach()` and asserts the output contains no unresolved `[token]` and
  no literal placeholder such as `<mime>`; a template may only use the tokens
  the parser substitutes;
- adapter tests inject database and deletion failures and prove that invalid
  token, failed validation, preview, and delete operations never publish;
- `rg` across executable and configuration paths only, per batch 10, confirms no
  old functions, generic `go=4` fallback, obsolete callers, or withdrawn format
  names remain, configuration and render map included.

### Route and persistence matrix

| Flow | Real route and inputs | Success source of truth | Required negative checks |
|---|---|---|---|
| Account avatar write | multipart `POST index.php?name=account`, `op=saveavatar`, scoped `account` token, `userfile` | file below normalized avatar directory and filename-only `users.avatar` | wrong method, auth, token, upload validation, SQL failure, compensation failure |
| Account preset avatar | `POST index.php?name=account`, `op=saveavatar`, scoped `account` token, `avatar` | `users.avatar` holds `presets/<file>` | GET request and missing token both change nothing; no `Upload` call |
| Frontend files write | `POST index.php?name=files`, `op=send`, scoped `files` token, captcha, `posttype=save`, optional `userfile` | file below normalized temp directory and project-relative `_files.url` | preview, captcha/business validation, token, upload failure, SQL failure, external-URL fallback after rejected local file |
| Admin files write | `POST admin.php?name=files&op=save`, admin authorization, global token, `posttype=save`, optional `filesite`, `path` | file below the selected `path` directory on first write, with no second move, and project-relative `_files.url` | preview publishes nothing; delete performs only its existing deletion; token, validation, SQL failure with compensation, cleanup failure |
| Admin local write | `POST admin.php?name=uploads`, `op=uploadsave`, scoped `uploads` token, `userfile` | one file below the selected directory, accepted up to field 2 of the `all` record and no further; no database change | malformed local file, simultaneous local/remote input, no remote fallback after local failure, a file above the `all` field-2 limit, a directory with no own record still resolving rules |
| Admin remote write | same route with no local file and POST `sitefile` | one file below the selected directory; no database change | localhost/private/reserved targets, redirect to private target, overflow, timeout, MIME mismatch |
| Editor write | `POST index.php?go=4&op=editorUpload&mod=<mod>`, scoped `upload` token, `file` single/multi shape | files and existing upload JSON shape | authorization, token, maxfiles, mixed batch, quota race |
| Editor read | `GET index.php?go=4&op=editorFiles&mod=<mod>`, global token | existing listing JSON shape | moderator sees all; user sees own positive suffix only; guest receives no historical files |
| Unknown editor operation | `index.php?go=4&op=<unknown>&mod=<mod>` | HTTP 400 and `{"ok":false,"error":"<_ERROR>"}` | no filesystem or database change and no PHP warning, including an unknown `mod` |
| Editor write to an unconfigured module | `POST index.php?go=4&op=editorUpload&mod=<directory without a record>` | refused with the existing missing-configuration error | the `all` record must not be used; nothing is written anywhere under `uploads/` |
| Upload preferences save | `POST admin.php?name=uploads&op=configsave`, scoped `uploads` token | `config/uploads.php` rewritten with twelve fields per module, `config/local.php` gone and rebuilt, every module block round-tripped unchanged | token; an unsupported extension is dropped and reported, not stored; field 10/11 values stay with their own module |
| Upload templates save | `POST admin.php?name=uploads&op=tplsave`, scoped `uploads` token | `config/filetype.php` rebuilt with one entry per `typ` extension | token; `7z` keeps the migrated template; no `swf` entry reappears |

Use a controlled public fixture for the positive remote route test. Unit tests
must remain deterministic and use the protected seams; route tests must at
least prove that localhost and redirect-to-private targets are rejected.

For every state-changing route, compare filesystem and database state before
and after success and failure. After admin tests, inspect
`storage/logs/error_php.log`, `storage/logs/error_sql.log`, and
`storage/logs/error_site.log`; also inspect `storage/logs/error_file.log` for
publication or compensation failures. Confirm that `storage/logs/uploads`
contains only lock files and that nothing else writes there. No new warning or
error may remain unexplained.

## Acceptance

- Every supported publication flow uses `Upload` through `getUploadService()`;
  legacy deletion and standalone relocation remain explicitly outside this
  migration.
- Exactly one new class, two new functions (`getUploadService()`,
  `setUploadRuleData()`), one new public static method, and two renamed
  functions (`getUploadRuleData()`, `getImageThumb()`) are added; the upload
  configuration string is parsed **and assembled** in exactly one place each; no
  new configuration key, configuration field, or language constant is
  introduced, and no new directory for user files — the lock directory under
  `LOGS_DIR` is internal state in an existing, already-ignored tree.
- The central type policy — the extension-to-MIME map and
  `Upload::getSupportedTypes()` — exists in exactly one place. The per-flow
  allowlists stay in their existing configuration files, as `Two allowlist
  levels` requires; they select from the central policy rather than restate it.
- Visitor-facing allowlists are not widened beyond `webp` and `avif`; the full
  canonical set reaches only the global `typ` list and the operator-only `all`
  record.
- The upload settings page keeps working end to end: both tabs round-trip every
  value, the configuration string keeps its twelve fields in order, no module
  block picks up another module's saved flags, and an extension without a MIME
  policy can no longer be stored as enabled.
- Admin upload takes every rule from the `all` record, no limit is hardcoded at
  a call site, and no per-module record has its per-file limit raised.
- `admin/info/uploads/ru.md` describes the shipped behavior, including the
  remote-address policy, local-first precedence, the type-by-content check, the
  withdrawal of `swf`, the `7z` spelling, and the guest listing change.
- No obsolete upload pipeline, generic `go=4` fallback, wrapper, hidden
  compatibility path, dead format entry, or orphaned constant remains.
- Invalid method, authorization, CSRF, captcha, business validation, and
  preview paths never call `Upload` or change filesystem/database state, and
  this now holds for the preset avatar path as well.
- Existing delete paths never call `Upload` or publish a new file and preserve
  their current deletion behavior outside this migration.
- Failed transfers never publish a final file or database record, and never
  leave a zero-byte placeholder. They leave no partial either, unless the
  filesystem refuses to delete it — that case is logged to `error_file.log` and
  retried by later sweeps, never silently ignored. A later SQL failure
  triggers checked compensation against a path that is still valid, because no
  adapter moves a file between publication and the database write.
- A failed compensation is logged without sensitive URL data or file content and
  is reported to the caller alongside the database failure.
- Concurrent uploads cannot exceed quota through a check/write race, and two
  publications through `Upload` can never overwrite each other, on any
  filesystem and without relying on `link()`. This is a guarantee among users of
  the class, which all take the destination lock — not a filesystem-level
  no-clobber guarantee: `rename()` replaces an existing target by definition, so
  a process writing into an upload directory outside this class is out of scope
  and is prevented by the canonical naming grammar rather than by locking.
- Remote upload fails closed for unresolved, redirected, rebound, non-public,
  oversized, or unsupported targets.
- Every surviving extension uploads successfully, proven by a MIME fixture per
  mapped extension. `webp`, `avif`, `flac`, and `pdf` become uploadable,
  thumbnails included where they are images, and the four image lists agree.
- No configuration file, render map, stylesheet, or code branch still references
  a withdrawn format, the `7zip`/`gzip` spellings, the `screens` class, or WBMP.
- Every render template matches its medium: images render as images, audio
  through `<audio>`, video through `<video>`, PDF through `<object>` with a link
  fallback, archives as links.
- Moderator and authenticated-user editor behavior remains compatible; guest
  historical listing is intentionally disabled as a documented security
  change.
- Existing successful database reference formats remain compatible.
