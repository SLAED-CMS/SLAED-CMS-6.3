# Data URI Security Hardening Plan

Status date: 2026-07-16. Not executed — awaiting the placement decision and an
explicit go. Companion of the editor embed mode (`embedmax` 32 KB on the
client, commit pending): the client limit is UX only, the server is the sole
enforcement point.

## Facts (verified 2026-07-16)

- `Parser::checkImageSource()` (`core/classes/parser.php`, the
  `str_starts_with($raw, 'data:')` early return) passes ANY `data:` URI
  through untouched: no MIME whitelist, no size limit.
- The editor popup is irrelevant to the attack surface: an anonymous user can
  type `![x](data:...)` into any textarea or POST the markdown directly.
- `filterUrl()` policy for `data:` in link contexts (`[url]`, auto-links) is
  unverified and must be audited as part of this work.

## Risks

1. Upload-permission bypass: a guest without upload rights still has an
   unlimited byte channel straight into the database.
2. Resource abuse: multi-megabyte base64 strings in comments — oversized rows
   (possible column truncation = broken content), heavy pages for every
   reader, bloated backups, and no cleanup path unlike real files.
3. XSS — limited: browsers do not execute scripts inside `<img src="data:...">`
   (including svg+xml in the img context), but `data:text/html` reaching an
   `<a href>` would rely only on browser top-level-navigation blocking.

## Decision required: enforcement point

| Option | Pros | Cons |
|---|---|---|
| Render time — inside `checkImageSource()` (recommended) | last line of defense; covers existing rows and any write path (imports, SQL, old content) | rule runs on every render (memoized per request already); legitimate oversized legacy embeds stop rendering |
| Input time — inside `filterContent()` on save | existing content untouched; zero render cost | old rows stay dirty; any non-standard write path bypasses it |

Recommendation: render time. The 32 KB allowance keeps legitimate icons
working; `checkImageSource()` results are already memoized per source string.

## Implementation steps

1. `checkImageSource()`: replace the blanket `data:` pass-through with a
   whitelist — only `data:image/(png|jpe?g|gif|webp);base64,<payload>` where
   the decoded payload length is 32768 bytes or less; anything else returns
   `null` (the parser's existing image-placeholder path takes over).
   Keep the limit as one shared constant/config value with the driver's
   client-side `embedmax` so the two never drift.
2. Audit `filterUrl()`: `data:` must be rejected in every link context;
   add the guard if it is missing.
3. Repo-wide grep for other raw `data:` acceptors in output paths
   (`getSafeUrl` callers, RSS, OpenGraph image tags).

## Verification

- Unit-style harness over the parser with hostile payloads: oversized
  `data:image/png` (> 32 KB), `data:image/svg+xml` with a script,
  `data:text/html`, `data:application/octet-stream`, uppercase/whitespace
  MIME tricks (`DATA:IMAGE/PNG`, `data: image/png`), missing `;base64`,
  and a legitimate 1 KB png (must survive).
- Live HTTP: guest submits content with a hostile and a legitimate embed via
  the real form; published render shows the placeholder for hostile, the
  image for legitimate.
- `[url=data:text/html,...]` renders as escaped text or a dead link, never a
  clickable `data:` href.
- Existing pages regression: homepage, an article with images, `php -l`,
  `phpstan` on the parser, the parser unit suite (`ParserFixturesTest`).

## Out of scope

- Client editor behavior (embed mode UX is done and committed separately).
- Retroactive cleanup of existing DB rows (possible follow-up: one-off scan
  reporting oversized embeds for manual review).
