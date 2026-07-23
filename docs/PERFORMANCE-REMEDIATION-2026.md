# SLAED Performance Remediation Follow-up (2026-07)

## Status

- Audit date: 2026-07-23
- Status: OPEN
- Scope: follow-up fixes required after the implementation audit of the 2026 performance work
- Release rule: do not mark this document complete or remove it until every acceptance check is backed by a recorded test result
- Source changes: none in this document; this is an implementation and verification plan

## Goal

Close the remaining correctness, availability, deployment, and verification gaps without reverting the completed performance work:

1. Bound the public page-cache key space.
2. Restore the exact unique-host and unique-user contract.
3. Make statistics rotation fail-safe.
4. Complete the OPcache and scheduler deployment configuration.
5. Harden the dynamic-region marker contract.
6. Add permanent regression coverage and synchronize the durable documentation.

## Findings

| Priority | Finding | Evidence | Required outcome |
|---:|---|---|---|
| P0 | A guest can create a distinct page-cache file with every unknown query parameter/value | `core/system.php:1697-1720`, `core/classes/cache.php:30-51` | Cacheability and cache identity use a bounded per-route parameter contract |
| P0 | The page-cache hash includes the request host without canonical-host validation | `core/security.php:638-641`, `core/system.php:1715-1720` | Cache identity uses the configured canonical host or an explicit host allowlist |
| P1 | Unique-day cookie and user-session marks are set before the locked server-side count succeeds | `core/system.php:1309-1319`, failure exits at `:1346-1351` | Client state never suppresses an exact count unless server persistence has already succeeded |
| P1 | A failed monthly `rename()` is followed by deletion of the source `days.log` | `core/system.php:1378-1383` | Rotation failure preserves the source and is logged |
| P1 | Counter truncation and writes are not checked completely | `core/system.php:1409-1412` | Failed or partial persistence is detected and reported without a false success acknowledgement |
| P1 | OPcache is disabled in the configured OSPanel PHP runtime | `C:\OSPanel\modules\PHP-8.4\PHP\php.ini:309` | OPcache is loaded and enabled in the web SAPI after an OSPanel restart |
| P2 | Pseudo-cron is still enabled | `config/scheduler.php:74` | A real cron trigger is proven healthy before `pseudo` is changed to `0` |
| P2 | Marker creation accepts unknown types and unvalidated parameters | `core/system.php:1665-1693` | Only known region types with type-specific validated parameters can be signed |
| P2 | The implementation has no permanent regression tests for the new performance contracts | `tests/` and the verification matrix below | Every critical contract is covered by an automated or recorded integration check |
| P3 | Durable documentation still says superseded inline template caches are never collected | `docs/PERFORMANCE.md:98-100`, implementation at `core/system.php:1723-1728` | Documentation describes the implemented template/data GC |

## Decisions

### Page-cache identity

- Keep the current default-deny route/op policy.
- Add a parameter contract to every cacheable route. The initial contract remains limited to the `news` list operation.
- For `news` with an empty operation, the only semantic query inputs are `cat` and `num`; `name` and `op` identify the route.
- Existing known tracking parameters may be discarded through the centralized tracking-parameter list.
- Any other query key, duplicate semantic key, malformed value, or unsupported operation makes the request non-cacheable. It must render live; it must not be silently normalized into a cache entry.
- Build the cache identity from validated semantic values, not the raw query string. Equivalent inputs such as an omitted page and `num=1` must resolve to one identity.
- Use the host from `homeurl` as the default cache host. Multi-domain installations require an explicit configured allowlist; raw `HTTP_HOST` must not create arbitrary cache namespaces.
- Add a page-cache identity version to the hash. Old cache entries stay unreachable and are removed by normal GC; a destructive cache purge is not required.
- Do not expand the cacheable-route list in this batch.

### Exact statistics

- Exact totals must not depend on optimistic cookie or `$_SESSION` acknowledgements.
- Remove `uniq-day` and user-count marks from the decision that skips `checkUniqueIp()` or `check_user()`.
- Continue to perform both exact uniqueness checks under the existing `statistic.log` lock.
- The stats cookie may continue to carry approximate session metrics and country cache data, but no cookie field may feed exact hosts/users.
- A cookie schema change must use a new version. The old cookie may be discarded; runtime dual-version compatibility is unnecessary because this is disposable analytics state.
- If exact uniqueness later needs a faster durable index, design it separately. Do not reintroduce a client-side acknowledgement that can get ahead of persistence.

This deliberately chooses correctness over the current optimistic skip fast-path. It is the smallest safe correction and does not require a schema change.

### Statistics persistence and rotation

- Check `ftruncate()`, `fwrite()`, and `fflush()` results.
- Treat a short write as a failure.
- Log persistence failures through the existing site logger without including cookies, IP addresses, usernames, or secrets.
- Rotate `days.log` only after the archive directory exists and the move succeeds.
- If the destination already exists or `rename()` fails, retain `days.log`, log the error, and retry on a later request. Never unlink the source on the failure path.
- Keep all current-day transition decisions under the single `statistic.log` lock.

### Dynamic-region markers

- `token` accepts only an approved scope identifier.
- `captcha` accepts only an approved action identifier.
- `voting` accepts only a positive decimal ID in the supported integer range.
- Unknown types and invalid parameters must poison the cache build and fail visibly during development; they must never produce a trusted signed marker.
- Marker payloads continue to carry data only, never PHP callables or serialized objects.
- The serve-time dispatcher remains available before `setHead()`.

### Deployment configuration

- OPcache is an environment change, not a repository code change.
- Uncomment `zend_extension = opcache`, keep JIT disabled, restart OSPanel, and verify the web SAPI rather than relying only on CLI output.
- Configure a protected OS-level cron call to `index.php?go=3&op=scheduler&trigger=cron` using the configured scheduler token.
- Confirm a fresh `storage/logs/scheduler/heartbeat.json` with `trigger=cron` and at least one successful due-job execution.
- Only after that confirmation, set `scheduler.pseudo` to `0`, rebuild `config/local.php` through the normal configuration workflow, and verify that frontend HTML no longer contains a pseudo-trigger.
- Never place the scheduler token in this document, a test artifact, shell history, or a committed file.

## Ordered implementation batches

### Batch 1 — bound page-cache keys

Target files:

- `core/system.php`
- `core/classes/cache.php`
- focused cache contract tests

Work:

1. Define the route/op/parameter contract in the existing page-cache decision path.
2. Reject unknown, duplicate, or malformed semantic parameters.
3. Canonicalize the accepted values for the cache identity.
4. Replace raw host identity with the configured canonical host or explicit host allowlist.
5. Add a cache-identity version so pre-fix files cannot be reused.
6. Keep dynamic-region, sidecar, no-store, and 304 behavior unchanged.

Acceptance:

- One hundred requests containing different unknown parameters create zero page-cache files.
- `num` omitted and `num=1` use the same cache file.
- Valid `cat` and `num` combinations create only their expected entries.
- Alternate encodings and duplicate keys do not create new entries.
- A foreign `Host` value cannot create a new cache namespace.
- Existing two-cookie-jar CSRF, captcha, and voting isolation still passes.

### Batch 2 — exact counters and fail-safe rotation

Target files:

- `core/system.php`
- focused statistics contract tests

Work:

1. Remove optimistic unique-host/user marks from exact-count skip decisions.
2. Version or simplify the stats cookie accordingly.
3. Check every truncate, write, flush, archive-directory, and rename result.
4. Preserve `days.log` on every rotation failure.
5. Log errors with non-sensitive context.

Acceptance:

- Parallel first requests for one IP/user count the entity once.
- Parallel requests from distinct IP/user fixtures count every distinct entity.
- A failed open, lock, truncate, write, flush, or rename never creates a successful client acknowledgement.
- The next successful request after an injected failure can still record the previously unacknowledged unique entity.
- Day rollover counts the first hit and retains the previous day.
- Existing archive files cannot cause deletion or overwriting of unarchived data.
- Approximate cookie session buckets never feed exact counters.

### Batch 3 — marker contract hardening

Target files:

- `core/system.php`
- `blocks/voting.php` if its call contract changes
- focused dynamic-region tests

Work:

1. Validate every marker type and its parameters before signing.
2. Keep signature verification and fail-closed sidecar behavior unchanged.
3. Verify all current emitters against the stricter contract.

Acceptance:

- Valid `token`, `captcha`, and `voting` markers render.
- Unknown types, invalid scopes/actions, negative IDs, oversized IDs, and punctuation payloads cannot become signed cache markers.
- Forged or malformed markers remain inert.
- Cache files contain markers and no live CSRF or captcha token.

### Batch 4 — environment and scheduler

Target files and systems:

- `C:\OSPanel\modules\PHP-8.4\PHP\php.ini`
- OSPanel service state
- OS scheduler/cron configuration
- `config/scheduler.php` through the normal configuration workflow

Work:

1. Enable OPcache and restart the PHP web runtime.
2. Record web-SAPI OPcache status.
3. Install and verify the protected cron trigger.
4. Disable pseudo-cron only after the cron heartbeat is healthy.
5. Re-run list/detail benchmarks.

Acceptance:

- Web SAPI reports `Zend OPcache` loaded and enabled.
- News list cache hit and miss measurements are recorded after restart.
- News detail generation is measured separately.
- Cron heartbeat remains healthy beyond one configured interval.
- Frontend rendering performs no pseudo-trigger injection after `pseudo=0`.

### Batch 5 — permanent verification and documentation

Target files:

- `tests/`
- `docs/PERFORMANCE.md`
- this document

Work:

1. Add focused regression tests for cache identity, marker validation, stats-cookie semantics, locked counters, rotation failure, GeoIP streaming, and cache sidecars.
2. Run the database migration fixture on both MySQL and MariaDB.
3. Correct the template/data GC wording in `PERFORMANCE.md`.
4. Record commands, versions, dates, and results below.
5. Mark this document complete only after every required row passes.

## Verification matrix

| Area | Required check | Source of truth | Status |
|---|---|---|---|
| Syntax | `php -l` on every changed PHP file | Command output | Pending |
| Static analysis | `vendor/bin/phpstan analyse --no-progress` | Command output | Pending |
| Unit/integration suite | `vendor/bin/phpunit --colors=never` | PHPUnit result | Pending |
| Formatting | PHP-CS-Fixer dry-run on changed PHP files | Command output | Pending |
| Diff hygiene | `git diff --check` | Command output | Pending |
| Cache cardinality | Random unknown query keys create no files | `storage/cache/pages/html` before/after | Pending |
| Cache canonicalization | Equivalent valid inputs share one identity | Cache filenames and response equality | Pending |
| Host isolation | Unapproved Host cannot create an entry | Cache filenames and HTTP status | Pending |
| Dynamic isolation | Two cookie jars receive different live tokens | HTTP response bodies | Pending |
| Cached captcha | Challenge from cached output validates before expiry | Real GET/POST flow | Pending |
| Voting isolation | Form/results follow each visitor's cookie/IP/user state | Two independent visitor flows | Pending |
| Dynamic browser policy | Dynamic page is no-store and future IMS returns 200 | HTTP response headers/status | Pending |
| Static browser policy | Static allowlisted fixture supports public headers and 304 | HTTP response headers/status | Pending |
| Stats cookie | First/next hit, tamper, IP change, expiry, disabled cookies | Cookie jar and counter files | Pending |
| Stats concurrency | Exact hits/hosts/users under parallel requests | `statistic.log`, `ips.log`, `user.log` | Pending |
| Stats failure | Injected IO failures preserve later countability | Counter files and error log | Pending |
| Day rollover | Parallel first requests retain old day and count new day | `days.log`, archive, `statistic.log` | Pending |
| Session schema | Duplicate fixture migrates and concurrent upsert stays unique | MySQL DB state | Pending |
| Session schema | Duplicate fixture migrates and concurrent upsert stays unique | MariaDB DB state | Pending |
| GeoIP equality | Old/new corpus equality for IPv4/IPv6 and records 24/28/32 | Test output | Pending |
| GeoIP memory | Old/new readers in separate processes | Peak-memory output | Pending |
| Parser cache | Content/config/theme/locale changes rotate keys; bypass cases stay live | Cache files and rendered output | Pending |
| Config cache | Old version, invalid schema, theme/assets/logo changes | `config/local.php` and rendered output | Pending |
| OPcache | Loaded and enabled in web SAPI after restart | Web-SAPI diagnostic | Pending |
| Scheduler | Cron heartbeat and due-job success, pseudo trigger absent | Scheduler state files and HTML | Pending |
| Runtime logs | No new unexpected PHP, SQL, or site errors | `storage/logs/error_*.log` | Pending |

## Rollout order

1. Deploy Batch 1 first to remove the public cache-amplification path.
2. Deploy Batch 2 and run exact-counter failure/concurrency tests before relying on analytics.
3. Deploy Batch 3 and repeat all two-visitor cache tests.
4. Enable OPcache and restart OSPanel.
5. Prove cron health, then disable pseudo-cron.
6. Run the complete matrix on MariaDB and MySQL.
7. Update `PERFORMANCE.md` and change this document to `COMPLETE`.

No new database schema or runtime compatibility wrapper is required by Batches 1-4. The existing `_session` migration is verification-only in this follow-up.

## Completion record

Fill this section only with executed results:

- Completion date:
- Commit:
- PHP/web-SAPI version:
- OPcache status:
- MariaDB version/result:
- MySQL version/result:
- PHPUnit result:
- PHPStan result:
- Cache cardinality result:
- Dynamic-region result:
- Statistics concurrency/failure result:
- Scheduler result:
- Remaining accepted risks:

