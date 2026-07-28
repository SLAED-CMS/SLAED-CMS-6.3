# Upload

Status: proposed, not started. Last review: 2026-07-28.

Replace the procedural upload pipeline with one final `Upload` class. Migrate
all supported callers in the same change and remove the old functions and
generic upload fallback. No compatibility wrapper or transitional
implementation.

Source line numbers below describe the current working tree. Resolve the named
symbols again before editing because adjacent work may move them.

## Scope

Supported flows:

- account avatar;
- frontend and admin file modules;
- Toast UI editor;
- admin local upload;
- admin remote upload.

Out of scope: filesystem backup, media processing, cloud storage, chunked
uploads, antivirus integration, and retention policy.

## Architecture

Create `core/classes/upload.php` with class `Upload`.

The class owns:

- validation of extension, MIME type, size, image dimensions, destination, and
  quota;
- safe local and remote transfer;
- collision-free naming, atomic publication, cleanup, and returned metadata.

HTTP adapters keep:

- `$_FILES`, `getVar()`, authorization, CSRF, and configuration mapping;
- database writes;
- templates, JSON, redirects, and localized messages.

Public contract:

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
- `0`: guest or unowned content;
- positive integer: owning user.

Rules use stable keys: `extensions`, `maxbytes`, `maxwidth`, `maxheight`,
`maxfiles`, and `maxquota`.

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

Keep error codes machine-readable:
`missing`, `transfer`, `size`, `extension`, `mime`, `image`, `dimensions`,
`quota`, `destination`, `exists`, `write`, `remote`, `unsupported`.
`addUploadedFiles()` may return mixed successes and failures; the caller decides
whether partial success is acceptable for its business flow. `maxfiles` limits
the submitted batch and is checked before processing; `maxquota` limits the
total content stored in the destination.

## Mandatory invariants

### Validation and paths

- `$dir` and returned `path` are always relative to the upload root.
- Extensions are matched against the exact configured allowlist.
- Executable and web-active formats are denied even if configuration is wrong.
- MIME is detected with `finfo` and checked against an explicit map covering
  every supported extension.
- Images must decode successfully and satisfy configured dimensions.
- The destination must resolve below `UPLOADS_DIR`; reject absolute paths,
  traversal, NUL/control bytes, symlink escapes, missing directories, and
  unwritable directories.
- Stored names use `<sanitized-base>-<random>-<uid>.<ext>` when `$uid` is an
  integer and omit the owner suffix only when `$uid` is `null`. This contract
  remains compatible with editor ownership filtering.
- `deleteStoredFile()` accepts a root-relative result path, verifies the
  canonical class-owned filename, repeats containment checks, uses the
  destination lock, and never follows a path outside the upload root.

### Publication and quota

- All publications to one destination use the same OS lock, whether quota is
  enabled or not.
- Transfer into a unique class-owned partial first. Then hold the destination
  lock while removing stale partials, checking quota in constant memory,
  choosing the final name, and publishing atomically.
- Never overwrite an existing file.
- Exclude class partials and known non-content sentinels from quota. Count the
  incoming file exactly once.
- Local files must pass PHP upload checks before publication.

### Remote upload

Use ext-cURL only:

- allow `http` and `https`; reject credentials, fragments, and non-default
  ports;
- resolve CNAME chains with a fixed limit, reject loops, and validate every
  resolved A/AAAA address against private, loopback, link-local, reserved, and
  otherwise non-public ranges;
- resolve each redirect again, allow at most three redirects, and discard
  intermediate response bodies;
- disable environment proxies, pin the validated address with
  `CURLOPT_RESOLVE`, and verify the connected primary IP;
- keep TLS verification enabled; use a 5-second connect timeout and 30-second
  total timeout;
- enforce both `Content-Length` and streaming byte limits;
- accept only a final 2xx response, validate the extension from the final URL,
  and run the same MIME, image, quota, naming, and atomic-publication checks as
  a local upload;
- use uniquely named `.upload-<hex>.part` files and remove abandoned partials
  older than one hour.

Keep DNS resolution, cURL execution, and the clock behind narrow replaceable
internal methods so security cases can be tested without public network or DNS
dependencies. The business-facing constructor and methods remain unchanged.

## Current findings

| Priority | Finding | Evidence |
|---|---|---|
| Critical | Remote upload accepts a raw URL without complete SSRF and streaming controls. | `core/system.php:5434`, `core/system.php:5481` (`upload()`) |
| High | Extension checks are not an exact MIME/content policy. | `core/system.php:5342` (`check_file()`), `core/system.php:5357` (`upload()`) |
| High | The generic default branch inside `go=4` exposes an accidental upload endpoint. | `index.php:156` |
| High | Editor upload duplicates validation and storage rules. | `core/system.php:4305` (`addEditorUpload()`) |
| High | Destination construction lacks one canonical containment boundary. | `core/system.php:5359` (`upload()`) |
| High | File publication happens before unchecked account/file SQL writes, so a failed write can leave an orphan. | `modules/account/index.php:1056`, `modules/files/index.php:534` |
| High | Editor ownership depends on the current filename suffix contract. | `core/system.php:4392` (`getEditorFileJson()`) |
| Medium | Admin upload does not provide one explicit local-first/remote-second decision. | `admin/modules/uploads.php:160` |

## Implementation plan

1. Add `Upload` and unit tests for the contract, validation, containment,
   locking, quota, atomic publication, cleanup, and remote security.
2. Migrate the account, file-module, editor, and admin adapters. For admin
   upload, prefer a supplied local file; otherwise process the remote URL.
3. Preserve each flow's database record and response format, but check every
   SQL result. If a write fails after publication, call `deleteStoredFile()` for
   that exact result, report failure, and log a cleanup failure explicitly.
4. Keep `go=4` as the supported editor route with only explicit
   `editorUpload` and `editorFiles` cases; reject every other operation. Delete
   `upload()`, `check_file()`, `check_size()`, and duplicated editor upload
   logic without aliases or wrappers.
5. Run the full verification matrix and inspect the final diff for remaining
   procedural callers.

Relevant callers at review time:

- `modules/account/index.php` — avatar;
- `modules/files/index.php` — frontend files;
- `modules/files/admin/index.php` — admin files;
- `admin/modules/uploads.php` — local/remote admin upload;
- `core/system.php:addEditorUpload()` — editor adapter to replace.

## Verification

Required automated checks:

- `php -l` for every changed PHP file;
- project PHPStan, PHPUnit, and PHP-CS-Fixer checks;
- unit tests for all result codes, malformed `$_FILES`, MIME mismatch, image
  limits, traversal/symlink escape, collision, quota boundaries, concurrent
  writes, partial cleanup, redirects, DNS rebinding defenses, proxy bypass,
  timeout, streaming overflow, and safe compensating deletion;
- adapter tests inject SQL failure and prove that only the newly published file
  is removed; cleanup failure must be returned and logged;
- `rg` confirms no old functions, generic `go=4` fallback, or obsolete callers
  remain.

Required integration checks:

- real multipart requests through every supported route;
- admin local and remote uploads;
- editor upload and listing JSON, including moderator, user, and guest ownership;
- filesystem and database persistence agree after success and remain unchanged
  after failure;
- security and PHP logs contain no new warnings.

## Acceptance

- All supported flows use `Upload`.
- No obsolete upload pipeline or hidden compatibility path remains.
- Failed transfers never publish a final file or database record. A later SQL
  failure triggers checked compensation and can never be reported as success.
- Concurrent uploads cannot exceed quota through a check/write race or
  overwrite one another.
- Remote upload fails closed for unresolved, redirected, or non-public targets.
- Existing successful flow behavior and stored references remain compatible.
