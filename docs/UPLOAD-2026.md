# Upload

Status: proposed, not started. Last review: 2026-07-28.

Replace the procedural upload pipeline with one final `Upload` class. Migrate
all supported callers in the same change and remove the old functions and
obsolete route. No compatibility wrapper or transitional implementation.

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

- Extensions are matched against the exact configured allowlist.
- Executable and web-active formats are denied even if configuration is wrong.
- MIME is detected with `finfo` and checked against an explicit map covering
  every supported extension.
- Images must decode successfully and satisfy configured dimensions.
- The destination must resolve below `UPLOADS_DIR`; reject absolute paths,
  traversal, NUL/control bytes, symlink escapes, missing directories, and
  unwritable directories.
- Stored names use a sanitized base plus sufficient randomness. Return a path
  relative to the upload root, never an arbitrary filesystem path.

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

## Current findings

| Priority | Finding | Evidence |
|---|---|---|
| Critical | Remote upload accepts a raw URL without complete SSRF and streaming controls. | `core/system.php:5434`, `core/system.php:5481` (`upload()`) |
| High | Extension checks are not an exact MIME/content policy. | `core/system.php:5342` (`check_file()`), `core/system.php:5357` (`upload()`) |
| High | The generic default branch inside `go=4` exposes an accidental upload endpoint. | `index.php:156` |
| High | Editor upload duplicates validation and storage rules. | `core/system.php:4305` (`addEditorUpload()`) |
| High | Destination construction lacks one canonical containment boundary. | `core/system.php:5359` (`upload()`) |
| Medium | Admin upload does not provide one explicit local-first/remote-second decision. | `admin/modules/uploads.php:160` |

## Implementation plan

1. Add `Upload` and unit tests for the contract, validation, containment,
   locking, quota, atomic publication, cleanup, and remote security.
2. Migrate the account, file-module, editor, and admin adapters. For admin
   upload, prefer a supplied local file; otherwise process the remote URL.
3. Preserve each flow's current database record and response format, but derive
   stored metadata only from a successful class result.
4. Move `editorUpload` and `editorFiles` to explicit routes, then delete
   `upload()`, `check_file()`, `check_size()`, editor duplication, and the
   generic `go=4` dispatcher. Do not leave aliases or wrappers.
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
  timeout, and streaming overflow;
- `rg` confirms no old functions, generic `go=4` dispatcher, or obsolete
  callers remain.

Required integration checks:

- real multipart requests through every supported route;
- admin local and remote uploads;
- editor JSON response;
- filesystem and database persistence agree after success and remain unchanged
  after failure;
- security and PHP logs contain no new warnings.

## Acceptance

- All supported flows use `Upload`.
- No obsolete upload pipeline or hidden compatibility path remains.
- Failed transfers never publish a final file or database record.
- Concurrent uploads cannot exceed quota through a check/write race or
  overwrite one another.
- Remote upload fails closed for unresolved, redirected, or non-public targets.
- Existing successful flow behavior and stored references remain compatible.
