# Upload

Status date: 2026-07-28. Proposed, not started.

This plan replaces the procedural `upload()`, `check_file()`, and `check_size()`
pipeline with one final `Upload` class. It also consolidates the separate editor
upload implementation into the same validation and storage boundary.

The migration is one atomic replacement. All supported production flows move
to the final contract, obsolete routes and functions are deleted, and no
compatibility wrapper or behavior-preserving intermediate implementation is
created.

## Progress

Written at the end of every implementation batch before its report.

| Date | Stage / batch | Outcome |
|---|---|---|
| 2026-07-28 | Analysis and implementation plan | complete; no PHP source changed |
| 2026-07-28 | Final-contract correction | complete; owner context, quota locking, DNS-pinned remote transfer, stale-partial recovery, and supported-flow criteria settled without fallback implementations |
| 2026-07-28 | Security self-review correction | complete; proxy bypass is disabled and CNAME resolution has a bounded fail-closed contract |

## Goal

- Create `core/classes/upload.php` with class `Upload`.
- Put extension, MIME, byte-size, image-dimension, destination, filename, and
  transfer checks behind one typed API.
- Keep only the supported business routes and their required stored references;
  obsolete entry points and accidental procedural contracts are not preserved.
- Migrate every current caller and delete the procedural implementation.
- Make upload behavior independently testable without rendering templates or
  constructing HTTP requests inside the class.

## Scope

In scope:

- single local uploads from the account and files modules;
- Toast UI editor uploads;
- the super-administrator upload page;
- its local file and remote URL inputs;
- validation, file naming, quota checks, storage, and raw file metadata;
- tests for the class and real route-level verification.

Outside scope:

- thumbnail generation and image transformation;
- file browsing, deletion, and download authorization;
- antivirus or content-disarm services;
- object storage and chunked/resumable transfers;
- backup of uploaded files;
- converting `addCompress()` to a class;
- changing upload configuration storage or adding database schema.

## Facts measured in the current tree

### Runtime flow

| Flow | Entry and policy boundary | Current storage call | Persistent result |
|---|---|---|---|
| Avatar | `modules/account/index.php:1048-1065` | `upload(1, ...)` at `modules/account/index.php:1056` | file plus `users.avatar` update at `modules/account/index.php:1064` |
| Public file submission | `modules/files/index.php:512-545` | `upload(1, ...)` at `modules/files/index.php:534` | temporary file plus `files.url` and `files.filesize` at `modules/files/index.php:541-545` |
| Admin file record | `modules/files/admin/index.php:213-244` | `upload(1, ...)` at `modules/files/admin/index.php:238` | public file path stored by the existing save flow |
| Obsolete default `go=4` branch | `index.php:156-175` | `upload(2, ...)` at `index.php:170` | unowned files under a caller-selected module directory |
| Editor upload | `index.php:162-166` | separate implementation at `core/system.php:4282-4350` | files plus JSON response |
| Admin upload page | `admin/modules/uploads.php:52-81` | `upload(3, ...)` at `admin/modules/uploads.php:160` | file under `UPLOADS_DIR/<dir>` |

The procedural API consists of:

- `check_file()` at `core/system.php:5319-5323`;
- `check_size()` at `core/system.php:5326-5329`;
- the nine-argument, mixed-return `upload()` at
  `core/system.php:5334-5481`.

The editor repeats upload-error, byte-limit, extension, image, quota, name, and
move logic at `core/system.php:4291-4348`. Its access and token checks are
already explicit at `core/system.php:4289-4290`.

The upload root is `UPLOADS_DIR`, defined at `core/system.php:30-31`. Current
module configuration supplies paths such as `uploads/avatars`,
`uploads/files/temp`, and `uploads/files/public` in `config/users.php:9-16` and
`config/files.php:26-35`.

No upload-focused class tests or route-contract tests exist. The current test
references found under `tests/` are configuration fixtures and generic path
exclusions, not transfer or validation coverage.

## Findings

| Severity | Finding | Evidence | Consequence |
|---|---|---|---|
| Critical | Remote upload accepts a raw URL/path and opens it from the server | `core/system.php:5411-5417` | A super-administrator form can cause server-side network or local-path reads; a compromised privileged session turns this into SSRF/LFI exposure |
| High | Remote content is accumulated in memory before the maximum size is checked | `core/system.php:5433-5444` | A large source can exhaust memory before rejection |
| High | Mode 4 downloads a URL without the normal extension, size, image, or destination checks | `core/system.php:5458-5478`; no caller is present in the repository search | Retaining this branch would preserve an unsafe, unowned API |
| High | The extension allowlist is interpolated into a regular expression and is not an exact-token comparison | `core/system.php:5319-5322` | Partial matches and regex metacharacters can accept an unintended suffix |
| High | Validation trusts a filename extension and does not verify detected MIME type | `core/system.php:5348-5360`, `core/system.php:5384-5395`, `core/system.php:5413-5440` | Renamed executable or malformed content may be stored under an allowed extension |
| High | Single and obsolete default-route uploads copy a temporary upload instead of moving it | `core/system.php:5360`, `core/system.php:5395` | The storage boundary does not use the upload-specific atomic transfer primitive after `is_uploaded_file()` |
| High | The default `go=4` branch does not apply the editor access policy used by the explicit editor endpoint | `index.php:156-170` compared with `core/system.php:4285-4290` | Knowing a module key may reach a state-changing upload route with weaker authorization |
| Medium | Image dimensions are requested for every allowed extension and failure is not handled safely | `core/system.php:5326-5329`, called for archives at `core/system.php:5349` and `core/system.php:5385` | Non-image uploads can warn or fail through an image-only operation |
| Medium | The editor suppresses image-decoder warnings with `@` | `core/system.php:4248-4255` | It violates the project rule and hides useful failure context |
| Medium | Destination paths are accepted as caller-provided relative or absolute strings | `core/system.php:5336-5341` | Configuration mistakes can write outside `UPLOADS_DIR`; there is no canonical-root or symlink boundary |
| Medium | The class-worthy domain logic is coupled to globals, superglobals, templates, tokens, and user state | `core/system.php:5334-5335`, `core/system.php:5377-5410` | The operation cannot be unit-tested or reused without a complete request runtime |
| Medium | The admin form offers both `userfile` and `sitefile`, but its handler always invokes remote mode | `admin/modules/uploads.php:52-78`, `admin/modules/uploads.php:156-164` | A submitted local file is ignored and the handler can redirect with a success message despite storing nothing |
| Medium | The current return contract varies between filename, `0`, `null`, direct HTML, and mutation of `$stop` | `core/system.php:5334-5480` | Callers cannot distinguish “no file”, validation rejection, and storage failure reliably |
| Low | Upload logic has two independent implementations | `core/system.php:4282-4350` and `core/system.php:5319-5481` | Fixes can diverge and regression coverage must be duplicated |

## Decisions

### Class and file name

- Class: `Upload`.
- File: `core/classes/upload.php`.
- The shorter class name is deliberate. It owns the upload domain, while
  `FileUpload` would repeat the only thing this service can upload.

### Direct migration, not a wrapper

The project rule for a full migration is decisive:

1. define the final accepted flows and security contract with tests;
2. add `Upload`, wire it, and migrate every supported call site atomically;
3. delete the default `go=4` branch, mode 4, `upload()`, `check_file()`, and
   `check_size()` in that same change;
4. prove with `rg` that no obsolete reference remains.

An `upload()` wrapper around `$upl->addUploadedFile()` is rejected. It would keep
the nine-argument API, globals, mixed result, and dual ownership that the class
is intended to remove.

### Ownership boundary

The class owns:

- normalization of one or many file descriptors supplied by a caller;
- exact extension allowlisting;
- detected MIME validation;
- upload error and byte-size validation;
- image decodability and optional dimensions;
- destination canonicalization under `UPLOADS_DIR`;
- collision-resistant server filename generation;
- concurrency-safe per-directory quota calculation when requested;
- local uploaded-file transfer;
- bounded remote transfer when explicitly requested;
- raw stored-file metadata and structured error codes;
- cleanup of partial output after every failure.

The route or module owns:

- reading `$_FILES`, because it is the HTTP adapter;
- all `getVar()` input reads;
- authentication and module authorization;
- `getSiteToken()` and `checkSiteToken()`;
- selecting the applicable configuration;
- translating class error codes to existing language constants;
- `$stop`, templates, redirects, headers, and JSON;
- database updates that reference the stored file.

This keeps HTML and transport out of the class and prevents `Upload` from
depending on `$user`, `$conf`, `$stop`, or `$tpl`.

### Instance lifetime

`core/system.php` loads the file beside the other class files and creates one
lightweight request-scoped instance:

```php
$upl = new Upload(UPLOADS_DIR, CACHE_DIR.'/upload-locks');
```

The constructor stores the canonical upload root and the non-public lock
directory. It performs no directory scan and opens no resource, so creating it
on a request that does not upload is negligible. The existing short global
service pattern is consistent with `$com`, `$prs`, and `$tpl` at
`core/system.php:143-146`.

### Public API

The target public surface is intentionally small:

```php
public function __construct(string $root, string $locks)
public function addUploadedFile(
    array $file,
    array $rule,
    string $dir,
    string $base,
    ?int $uid = null
): array
public function addUploadedFiles(
    array $files,
    array $rule,
    string $dir,
    string $base,
    ?int $uid = null
): array
public function addRemoteFile(
    string $url,
    array $rule,
    string $dir,
    string $base,
    ?int $uid = null
): array
```

`addUploadedFiles()` is justified by the supported editor multi-file flow. It
delegates each item to the same single-file validation path and does not
duplicate checks.

The nullable owner ID is the complete naming context:

- `null` means a privileged administrator and omits the owner suffix;
- `0` means an anonymous or explicitly unowned public upload;
- a positive integer is the authenticated/delegated owner ID.

Authorization is still decided by the caller. The class uses this already
authorized value only for deterministic filename construction.

The rule array has one normalized internal contract:

| Key | Type | Meaning |
|---|---|---|
| `extensions` | `array` | lowercase exact extensions without dots |
| `maxbytes` | `int` | maximum stored bytes; zero is rejected rather than interpreted ambiguously |
| `maxwidth` | `int` | image width limit; zero means no dimension limit |
| `maxheight` | `int` | image height limit; zero means no dimension limit |
| `maxfiles` | `int` | multiple-upload limit; zero means no count limit |
| `maxquota` | `int` | total regular-file bytes in the destination; zero disables quota |

Callers translate current pipe-separated or comma-separated configuration at
their boundary. `Upload` receives no whole `$conf` array.

### Result contract

Every storage method returns an array with the same base shape:

```php
[
    'ok' => true,
    'file' => 'files-random-42.zip',
    'path' => 'uploads/files/temp/files-random-42.zip',
    'size' => 1234,
    'mime' => 'application/zip',
    'width' => 0,
    'height' => 0,
    'error' => '',
]
```

Failure returns `ok => false`, empty storage fields, and one stable error code.
The final code set is:

- `missing`;
- `transfer`;
- `size`;
- `extension`;
- `mime`;
- `image`;
- `dimensions`;
- `quota`;
- `destination`;
- `exists`;
- `write`;
- `remote`;
- `unsupported`.

Multiple editor upload returns `files` and `errors`. Partial success is the
defined final editor contract: every item has its own result and one invalid
item does not erase already validated files. Single-file callers translate the
one result at their transport boundary.

The class does not return localized strings. That prevents an infrastructure
class from depending on request language state.

### Extension and MIME policy

Extension validation becomes an exact membership check after:

1. extracting the final suffix with `pathinfo()`;
2. lowercasing it;
3. rejecting an empty suffix and any suffix outside `[a-z0-9]+`;
4. comparing it with the normalized configured list using strict matching;
5. applying a denylist for executable/web-active types even if configuration is
   wrong.

MIME is detected from the temporary or partial file using `finfo`. A centralized
extension-to-MIME map must cover the extensions currently exposed by
`config/uploads.php:9-29` and `config/files.php:35`. Each extension may map to
multiple real MIME values where operating systems differ, for example ZIP and
RAR archives.

The implementation batch first records `finfo` values from representative
fixtures on the supported runtime. An unknown MIME or an extension/MIME
mismatch is rejected. This is an intentional security tightening required by
the project upload baseline, not a behavior-neutral rename.

Image extensions additionally require successful decoding by `getimagesize()`.
Non-image extensions never call an image decoder. No warning is suppressed.

### Destination boundary

Every caller passes a directory relative to `UPLOADS_DIR`, never an arbitrary
absolute path. Existing values are normalized at the adapter:

- `uploads/avatars` becomes `avatars`;
- `uploads/files/temp` becomes `files/temp`;
- `uploads/files/public` becomes `files/public`;
- an editor module name remains its one directory segment.

`Upload` rejects:

- absolute paths;
- `.` and `..` segments;
- NUL bytes and control characters;
- a resolved destination outside the canonical upload root;
- a destination or parent reached through a symlink outside that root;
- a missing or non-writable directory.

Configured application directories must exist and pass the canonical-root
check. The class does not create an arbitrary caller-selected directory.

### Quota and concurrency

Quota enforcement has one final algorithm:

1. derive a lock filename from the canonical destination path;
2. validate that the lock root resolves under `CACHE_DIR`, create it with mode
   0750 when absent, reject symlinks, and derive only a SHA-256 filename from the
   destination;
3. open the stable lock file and acquire `flock(LOCK_EX)`;
4. stream regular directory entries with `FilesystemIterator` so memory usage
   does not grow with the number of files;
5. stop and reject as soon as current bytes plus incoming bytes exceed
   `maxquota`;
6. keep the lock through final move/rename and release it in `finally`.

Using `FilesystemIterator` here is an explicit exception to the general
`scandir()` preference: `scandir()` materializes the complete directory and
would make quota memory proportional to directory size. Symlinks and the lock
directory are never counted as stored content.

This provides deterministic quota behavior under concurrent uploads without a
database counter or a cache that can drift from filesystem state. The 100,
1,000, and 10,000-file measurements remain performance acceptance tests, not a
later architecture decision.

### Filename policy

The final stored-name contract is:

- `<base>-<random>.<ext>` when owner ID is `null`;
- `<base>-<random>-<uid>.<ext>` when owner ID is zero or positive;
- `<random>.<ext>` only when the caller intentionally supplies an empty base.

The caller supplies a sanitized domain base such as `files` or the module key.
`Upload` revalidates it, generates the random component, opens only a
non-existing target, and retries a bounded number of collisions. The original
client filename is metadata only and never becomes a filesystem path.

### Remote upload

Remote upload remains available only on the super-administrator upload page.
It is not a general class capability exposed by `go=4` or module handlers.

`addRemoteFile()` has one final cURL implementation:

1. Require `ext-curl`; when it is unavailable, return `unsupported` before
   opening a file or socket. There is no stream-wrapper fallback.
2. Accept ASCII `http` and `https` URLs without credentials, fragments, or
   non-default ports; allow only ports 80 and 443.
3. Require an allowed extension in the final URL path. MIME never invents an
   extension.
4. Resolve DNS with one bounded algorithm: normalize the hostname, follow a
   maximum of eight CNAME records, reject loops or malformed/empty answers, and
   collect every A and AAAA address from the terminal name. Reject the complete
   host if any CNAME step cannot be validated or any address belongs to
   loopback, link-local, private, reserved, multicast, or unspecified space.
   A host without a validated public terminal A/AAAA answer is rejected.
5. Disable automatic redirects. Process at most three `Location` hops manually,
   repeating the complete URL, CNAME, A/AAAA, and address validation for every
   new request.
6. Disable environment and configured HTTP proxies for every hop with
   `CURLOPT_PROXY = ''` and `CURLOPT_NOPROXY = '*'`; proxy support is outside
   this feature contract because it would move DNS and connection enforcement
   outside the application.
7. Pin each request to one validated address with `CURLOPT_RESOLVE`, keep TLS
   hostname and peer verification enabled, and compare
   `CURLINFO_PRIMARY_IP` with the pinned address through normalized
   `inet_pton()` bytes after connection. A mismatch fails the transfer, closing
   the DNS-rebinding gap.
8. Use 5-second connection and 30-second total limits. Reject a declared
   `Content-Length` over `maxbytes`.
9. Stream the body through `CURLOPT_WRITEFUNCTION` into a destination `.part`
   file and abort the callback as soon as received bytes exceed `maxbytes`.
10. Accept only a final 2xx response and reject authentication/proxy challenges.
11. Run the same extension, detected MIME, image, quota, and destination checks
     as a local upload.
12. Rename the complete validated file atomically while holding the destination
     lock and delete every partial file in `finally`.
13. Before a new transfer under the same destination lock, delete only class
     partials matching `.upload-<hex>.part` that are older than one hour. This
     recovers files left by process termination where `finally` could not run.

Integration coverage for IPv4, IPv6, mixed public/private DNS answers, valid and
looped/over-depth CNAME chains, redirect to private space, DNS pinning, proxy
environment variables, timeout, oversized chunked response, and TLS failure is
mandatory. The remote feature is retained; only its unsafe
`fopen()`/`file_get_contents()` implementation is removed.

Mode 4 and the default `go=4` branch are deleted. Neither has a place in the
supported final route model: editor upload must use the explicit
`editorUpload` operation and the admin URL flow must use its privileged route.

## Implementation steps

### Preflight — final fixtures and acceptance

Files:

- `tests/Unit/UploadContractTest.php`;
- route probes under the existing test support only where they can use a real
  request boundary.

Work:

1. Record the four supported business callers and mark the default `go=4`
   branch and mode 4 for deletion.
2. Add fixtures for valid JPEG, PNG, ZIP, RAR, an extension/MIME mismatch, a
   corrupt image, a PHP payload renamed to an image, empty upload, oversized
   upload, and traversal-like names.
3. Define filename and relative-path formats required by database references;
   no other procedural return behavior is carried forward.
4. Define the final error mapping for avatar, files, editor, and admin flows.
5. Treat the admin local-file defect as a mandatory correction.
6. Verify all configured destinations resolve under `UPLOADS_DIR`; stop before
   implementation if an installation-supported configuration intentionally
   points elsewhere.

Exit criteria:

- the target callers and intended behavior changes are explicit;
- fixtures are independent of production `uploads/`;
- tests do not claim success by writing directly to application storage.

### Implementation — one atomic final migration

Files:

- add `core/classes/upload.php`;
- update class loading and service wiring in `core/system.php`;
- update `modules/account/index.php`;
- update `modules/files/index.php`;
- update `modules/files/admin/index.php`;
- update `admin/modules/uploads.php`;
- update `index.php`;
- update upload/editor functions in `core/system.php`;
- add `tests/Unit/UploadTest.php`;
- update or add route-contract tests established by the preflight.

Work:

1. Implement the typed `Upload` class, private validation/storage helpers, and
   stable result contract.
2. Load and instantiate `$upl` after the security/database bootstrap.
3. Migrate avatar upload and keep the valid `users.avatar` persistence rule.
4. Migrate public and admin files-module upload with one defined relative-URL
   and byte-count contract.
5. Delete the default `go=4` upload branch; an unknown operation fails without
   writing a file.
6. Make `addEditorUpload()` normalize `$_FILES`, call
   `$upl->addUploadedFiles()`, and retain only authorization and JSON mapping.
7. Use metadata returned by `$upl->addUploadedFiles()` for newly stored editor
   files. Keep existing-file listing and display formatting in the editor
   adapter; they are not upload responsibilities.
8. Fix `uploadsave()` to choose the local upload when `userfile` is present,
   otherwise use the hardened remote path when `sitefile` is present, and report
   “missing” when neither is supplied.
9. Remove mode 4 and then remove `upload()`, `check_file()`, and `check_size()`.
10. Add structured `Logger::addFile()` context for storage failures without
    logging file contents, tokens, or sensitive remote URLs.
11. Implement bounded CNAME traversal, all-answer address validation, explicit
    proxy disabling, per-hop address pinning, and primary-IP comparison.
12. Verify `.part` cleanup for every remote failure and interrupted transfer.
13. Implement the destination lock and constant-memory quota scan, then measure
    it with 100, 1,000, and 10,000 files.
14. Document PHP, web-server, and application byte limits so each rejection
    layer is distinguishable.
15. Search the whole repository for obsolete names and duplicated extension,
    MIME, image-size, and transfer logic.

The change is merged only as the complete final state. The class, all supported
callers, route deletion, procedural deletion, security checks, failure cleanup,
tests, and verification land together. There is no production checkpoint with
two pipelines or with the old algorithm merely moved into a class.

## Verification

Verification is split by boundary. Passing unit tests alone is not enough for a
state-changing upload migration.

### Static and automated

- `php -l` on every touched PHP file;
- `phpstan`;
- full `phpunit`;
- `php-cs-fixer --dry-run`;
- `rg -n "\b(upload|check_file|check_size)\s*\("` returns no obsolete production
  definitions or calls;
- `rg -n "@getimagesize|copy\(\$_FILES"` returns no migrated pattern;
- targeted unit coverage for every error code, collision retry, path boundary,
  MIME mapping, image dimensions, quota, partial cleanup, and multiple partial
  success.

### HTTP and browser routes

| Route | Positive path | Negative path |
|---|---|---|
| Account avatar | authenticated `POST index.php?name=account` with `op=saveavatar`, scoped token, and valid image | invalid token, oversized image, corrupt image, MIME mismatch |
| Public files | allowed user/guest `POST index.php?name=files` with `op=send` and valid archive | disallowed extension, empty upload, duplicate record |
| Admin files | authenticated admin `POST admin.php?name=files` with `op=save` | invalid token and unwritable test destination |
| Editor | authorized `POST index.php?go=4&op=editorUpload&mod=<module>` | unauthorized role, invalid token, too many files, quota exceeded |
| Removed default `go=4` | none | request without `editorUpload` or `editorFiles` is rejected and creates no file |
| Admin uploads | super-admin `POST admin.php?name=uploads&op=uploadsave` for local file and separately for controlled remote URL | invalid token, private-network URL, redirect to private address, stream over byte limit |

The browser test must use actual multipart submission. Directly placing a file
in `uploads/` is not route verification.

### Persistence source of truth

For avatar:

- stored file exists under the configured avatar directory;
- database `users.avatar` equals its stored basename;
- the rendered profile resolves that value.

For files:

- stored file exists at the path represented by `files.url`;
- `files.filesize` equals the actual stored byte count;
- a rejected request creates neither a row nor a leftover file.

For editor/admin uploads:

- returned JSON or redirect message matches the result;
- stored file exists under the selected module directory;
- rejected requests and handled interruptions leave no final or `.part` file;
- a hard-killed process may leave only a class-named partial, which the next
  locked upload removes before accepting new content.

Filesystem state and the corresponding database row are authoritative. A
success alert alone is never treated as proof.

### Logs

Before and after every state-changing route test, compare:

- `storage/logs/error_php.log`;
- `storage/logs/error_sql.log`;
- `storage/logs/error_site.log`.

Expected security rejections may create deliberate bounded records. New PHP
warnings, SQL errors, leaked paths, tokens, full URLs with credentials, or
uploaded content fail the batch.

## Security notes

- `$_GET`, `$_POST`, and `$_REQUEST` remain behind `getVar()`.
- `$_FILES` is read only by HTTP adapters and normalized before class entry.
- All state-changing routes validate `checkSiteToken()` before storage begins.
- Authorization is checked before the token and before any directory scan or
  remote request.
- File acceptance requires extension, detected MIME, and size validation.
- Image acceptance additionally requires successful decoding and dimension
  validation.
- Every final and partial destination stays inside the canonical upload root.
- Remote access is privileged, bounded, and protected against local-network
  targets, CNAME indirection, redirects, DNS rebinding, and proxy bypass.
- No uploaded bytes or sensitive URL fields are written to logs.
- No SQL is added to `Upload`; callers keep prepared database statements.
- The class renders no HTML and emits no headers, so output escaping remains at
  the existing template/JSON boundary.

## Risks and mitigations

| Risk | Mitigation |
|---|---|
| MIME tightening rejects files previously accepted by suffix alone | build fixtures from every configured type, support verified MIME aliases, and document the security change |
| Existing custom configuration points outside `uploads/` | reject it and require an explicit move into the canonical upload root before migration |
| Remote URL protection differs across cURL/DNS environments | disable proxies, validate bounded CNAME and all A/AAAA answers for every hop, pin the chosen address, verify the primary IP, and fail closed on unsupported behavior |
| Multiple-upload result is ambiguous | define one result per item and route-test mixed valid/invalid editor batches |
| Error text changes | keep stable class codes and map them to existing language constants in adapters |
| A moved file is stored but the later database write fails | route adapters delete the just-created file when their persistence step fails; cover this compensation path |
| Quota scanning becomes expensive | serialize quota-enabled destinations, stream entries in constant memory, stop early on overflow, and measure the required directory sizes |
| User changes in the current dirty worktree overlap `core/system.php` | re-read and rebase the implementation against the then-current file; never overwrite unrelated edits |

## Completion criteria

The migration is complete only when:

- `Upload` is the sole upload validation and storage implementation;
- avatar, frontend files, admin files, editor, admin local upload, and admin
  remote upload all use it;
- no compatibility wrapper, obsolete route, or procedural upload function
  remains;
- all configured extensions have an explicit tested MIME policy;
- no successful result is emitted without a verified stored file;
- positive and negative real routes verify persistent state;
- static analysis, tests, style check, and log review pass;
- the progress table records the exact batch results.
