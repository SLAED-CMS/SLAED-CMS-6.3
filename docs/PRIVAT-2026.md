# Privat

Status: proposed, not started. Last review: 2026-07-28.

Replace the procedural private-message subsystem with one `Privat` class.
Migrate every frontend, block, profile, and admin caller in the same change,
then remove the old functions and duplicate routes. No compatibility wrappers,
runtime schema detection, dual-schema fallback, or auxiliary state table.

The public module name, config namespace, and admin route remain `privat`.
Source line numbers below describe the current working tree; resolve the named
symbols again before editing because adjacent work may move them.

## Scope

Included:

- inbox, outbox, saved messages, detail view, and compose form;
- read/unread and bulk mailbox actions;
- unread and mailbox counters;
- account dashboard preview;
- email notification queueing and points side effects;
- account deletion integration;
- admin list, content access, and deletion;
- fresh-install schema and the 6.3 upgrade migration.

Out of scope: conversations, attachments, group messages, realtime delivery,
encryption, search, and new moderation features.

## Architecture

Create `core/classes/privat.php` with final class `Privat`. Instantiate it in
the core bootstrap beside `Comment`.

The class owns:

- every read and write of `_privat`;
- mailbox visibility and ownership rules;
- recipient resolution, limits, and flood control;
- message creation, read/unread, save, bulk actions, account cleanup, user
  delete, and admin delete;
- list counts and pagination data.

HTTP adapters keep:

- `getVar()`, authentication, POST/CSRF checks, and route responses;
- templates, parser output, links, redirects, and localized messages;
- points and queued email as post-write side effects.

No template, request, session, or mail dependency belongs in `Privat`.
Points and email queueing run only after a successful insert; their failure is
logged and never removes an accepted private message.

Keep the public API small: list/count/detail/recent/admin reads plus send,
read/unread, save, bulk action, account cleanup, user delete, and admin delete.
Methods return typed data arrays or booleans; `addMessage()` returns the
inserted ID and error code.

## Storage model

Keep one `_privat` table:

- existing message fields: `id`, `uidin`, `uidout`, `title`, `body`, `time`,
  `ip`;
- `format`: `plain` or `markdown`;
- `viewed`: recipient has read the message;
- `saved`: recipient moved the message to saved;
- `delin`: recipient removed the message from their mailbox;
- `delout`: sender removed the message from their outbox.

Replace the current single-column indexes with the minimum composite indexes
proven by the inbox, outbox, saved, unread, quota, and flood queries.

Required behavior:

- sending inserts one row;
- reading changes only `viewed`;
- marking unread returns `viewed` to zero;
- saving changes only `saved`;
- recipient deletion changes only `delin`;
- sender deletion changes only `delout`, regardless of read state;
- the row is physically deleted when both `delin` and `delout` are set;
- admin deletion physically deletes the row;
- sender and recipient actions never remove or move the other user's copy.
- the admin status is derived from the four state columns and distinguishes
  unread, read, saved, and recipient-deleted messages.
- a batch action locks and validates the complete bounded ID set before changing
  it; one foreign or invalid ID rejects the whole batch.
- account deletion marks that user's incoming and outgoing sides deleted,
  removes rows deleted by both sides, and leaves the counterpart's copy visible
  with the missing account rendered as `_ANONYM`.

## Content and admin policy

Private messages store source, never trusted HTML. Add `format` with only
`plain` and `markdown`; an HTML editor is not a trust grant and its submitted
markup is treated as Markdown source and escaped. Frontend and admin rendering
both use `Parser::filterContent()` with `safe = true` and the stored format.

Administrators are explicitly allowed to read private-message contents in the
existing `privat` admin module. This is a system policy, not an accidental
popover side effect. The admin view uses the same safe renderer as the
recipient and exposes no raw stored body to a template.

## Migration

Update `setup/sql/table.sql` and `setup/sql/table_update6_3.sql`.

Migration of every existing `_privat` row:

- add `saved`, `delin`, and `delout` with zero defaults;
- add `format` and convert existing bodies to `plain` or `markdown` using the
  verified comment-content migration rules;
- map `status = 2` to `saved = 1`;
- rename `status` to `viewed` and normalize every non-zero value to `1`;
- replace indexes only after migrated inbox, outbox, and saved counts match the
  legacy data.

The upgrade must be restartable and produce the same final schema as a fresh
install. Runtime code supports only the final schema. Previously deleted shared
rows cannot be reconstructed; document that limitation instead of inventing
placeholder messages. Classify content in dry-run mode and require a database
backup before conversion. Runtime has no `legacy` format or unsafe fallback.

## Security and consistency

- Keep all SQL parameterized and authorization inside class queries.
- All mutations are POST-only and require `checkSiteToken()`. Tokens are sent
  in the POST body, not in action URLs.
- Authenticated list and counter GET routes verify `is_user()` without a CSRF
  token. Opening a message marks it read, so that HTMX action is POST/CSRF.
- Recheck recipient existence, inbox quota, saved quota, and sender flood window
  inside the write transaction.
- Serialize competing sends sufficiently to prevent concurrent requests from
  bypassing quota or flood limits.
- Measure the longest word in characters, not the last word in bytes.
- Read the private-message notification preference from `psmail`, not `fsmail`.
- Use the ID returned by `getSqlLastId()` for the notification link.
- Return no message data when the relevant `delin` or `delout` flag is set.
- Bound, deduplicate, and validate every bulk ID through `getVar()` before the
  class rechecks ownership under its transaction.
- Account deletion wraps user cleanup and `Privat` cleanup in one transaction.
- The class returns data, never HTML; only safely rendered output crosses an
  `*_html` template boundary.

## Current findings

| Priority | Finding | Evidence |
|---|---|---|
| High | Shared `status` lets recipient actions alter the sender's outbox. | `setup/sql/table.sql:560`, `core/user.php:554`, `core/user.php:835` |
| High | Quota and flood checks race with the insert. | `core/user.php:771`, `core/user.php:790`, `core/user.php:796` |
| High | Save and delete mutate through GET routes. | `core/user.php:535`, `core/user.php:539`, `core/user.php:604` |
| High | The inserted ID is rediscovered with a racy latest-row query. | `core/user.php:796`, `core/user.php:802` |
| High | User content is rendered with `safe = false` in frontend and admin views. | `core/user.php:697`, `core/admin.php:820` |
| Medium | Notification uses the forum preference `fsmail` instead of `psmail`. | `core/user.php:800`, `modules/account/index.php:864` |
| Medium | Word validation retains only the final word length. | `core/user.php:773` |
| Medium | Outbox detail reloads the sender profile instead of the recipient. | `core/user.php:638`, `core/user.php:650` |
| Medium | Direct mailbox SQL spans frontend, blocks, and admin. | `core/user.php:260`, `blocks/user_info.php:23`, `core/admin.php:806` |
| Medium | Admin deletion exists in both the admin module and AJAX core. | `admin/modules/privat.php:83`, `core/admin.php:875` |
| Medium | Account deletion does not update either side of private messages. | `modules/account/admin/index.php:912` |

## Implementation plan

1. Add the final one-table schema and an idempotent state/content migration with
   fixture-based parity checks.
2. Add `Privat` and focused unit tests for reads, permissions, limits,
   transactions, content formats, independent states, account cleanup, and
   bounded bulk actions.
3. Migrate the account page, dashboard preview, navigation badge, and user block
   so no direct private-message SQL remains outside `Privat`. Load only the
   active mailbox initially; fetch other tabs on demand. Add read, unread, save,
   and delete bulk actions to the relevant mailbox views.
4. Replace the old AJAX operations with explicit read routes and POST mutation
   routes. Keep the handlers thin and move their markup to the existing template
   boundary where necessary.
5. Integrate `Privat` into transactional account deletion. Migrate the admin
   list, preserve authorized safe content reading, and keep one POST deletion
   path with result/count/pager parity.
6. Queue email using `psmail` and the returned message ID, update points only
   after a successful insert, remove the stale session-only mailbox counter
   cache, then delete the procedural functions and duplicate admin handler.
7. Run the full verification matrix and use `rg` to prove that only `Privat`
   accesses `_privat`.

## Verification

Required automated checks:

- `php -l` for every changed PHP file;
- project PHPStan, PHPUnit, and PHP-CS-Fixer checks;
- migration tests for fresh install, upgrade, second-run safety, row/count
  parity, body conversion, and byte-identical final definitions;
- unit tests for inbox/outbox/saved lists, counts, ownership, self-message,
  read/unread/save/delete independence, account cleanup, admin delete, bounded
  bulk actions, mixed owned/foreign IDs, quota boundaries, flood control, SQL
  failures, and concurrent sends;
- parser tests proving stored HTML and malicious payloads render safely in both
  frontend and admin views;
- source checks proving the old functions, old routes, direct table SQL, shared
  `status`, `fsmail` notification read, and latest-row ID query are gone;
- `EXPLAIN` for mailbox list and counter queries against representative data.

Required route checks:

- real authenticated inbox, outbox, saved, detail, compose, and dashboard reads;
- send, read, unread, save, bulk actions, user delete, account delete, and admin
  delete through valid POST/CSRF;
- GET, missing token, wrong token, foreign message ID, quota overflow, flood,
  and concurrent attempts make no unauthorized persistent change;
- deletion and saving by one participant leave the other participant's state
  intact;
- account deletion removes only that account's mailbox sides and preserves the
  counterpart's safely rendered anonymous copy;
- authorized administrators can read safely rendered message content;
- queued notification references the exact new message and respects `psmail`;
- review `storage/logs/error_php.log`, `error_sql.log`, and `error_site.log`.

## Acceptance

- `Privat` is the only runtime owner of `_privat`.
- Sender and recipient mailbox states are independent.
- Content has one safe `plain`/`markdown` storage and rendering contract.
- Every write is authorized, POST/CSRF protected, checked, and transactional.
- Concurrent sends cannot bypass flood or quota rules.
- Read/unread and bulk actions cannot mutate foreign messages.
- Account deletion cleans its mailbox sides without deleting the counterpart's
  copy.
- Administrator access to safely rendered message content is intentional and
  documented.
- Frontend, block, profile, and admin views use one data contract without direct
  SQL.
- The old functions, duplicate routes, shared status model, and compatibility
  paths are removed.
