# Mail Delivery Queue

Status date: 2026-07-27. Approved, not started. Every outgoing message in the
installation is sent synchronously inside the request that triggers it. This
plan replaces that with one queue, one drain task and one admin view, without
touching the 26 existing call sites.

## Facts (measured 2026-07-27)

- There is exactly **one** real send point: `mail()` inside `addMail()`
  (`core/security.php:1047`). Everything else routes through it, so the queue
  can be introduced behind that single function.
- `addMail()` **ignores the result** of `mail()` and suppresses its warnings
  with a local error handler. A refused or failed delivery is invisible.
- 26 call sites in 16 files: account (registration, password, profile), order,
  shop, money, contact, recommend, forum subscriptions, help admin, admins,
  security notices, comment notifications, newsletter.
- Comment notification measured on this installation: `addAdminMail()` takes
  **26.6 s** of a 26.7 s request — 13 recipients, ~2.05 s per `mail()` call.
  51 admins carry `smail = 1`; 13 of them match the `news` module.
- The newsletter already has a private queue: `{prefix}_newsletter.mails` holds
  recipients as a comma separated `MEDIUMTEXT`. `updateNewsletter()`
  (`core/system.php:3747`) slices a batch off that string, sends it
  synchronously and writes the remainder back.
- That queue has no per-recipient state, no retry and no attempt counter. A
  failure mid-batch drops those addresses silently.
- Throughput of the newsletter path: `newsletter.count = 4` per run, scheduler
  job `newsletter` runs `1 * * * *` — **4 messages per hour**. With 164 current
  subscribers (`users.newslet = 1`) a single mailing takes about 41 hours.
- History in `_newsletter`: 64 mailings, the largest delivered to 12775
  recipients. All rows currently have an empty `mails` column.
- No SMTP configuration exists in the project: delivery relies on PHP `mail()`
  with `SMTP` and `smtp_port` from php.ini.

## Problems this causes

1. **Requests wait for SMTP.** A comment costs 26.6 s here; on production the
   per-message cost is lower but still multiplied by the recipient count.
2. **Failures are silent.** The return value is discarded, warnings suppressed;
   nobody learns that a password reset never left the server.
3. **Two mechanisms.** The newsletter has its own ad-hoc queue, everything else
   has none.
4. **No retry.** A temporary SMTP outage loses every message sent during it.
5. **Unusable throughput.** Four messages per hour means a mailing to the
   current subscriber list runs for nearly two days.
6. **No visibility.** There is no way to see what is pending, what failed and
   why.

## Target design

### Storage

```
{prefix}_mailqueue
  id      INT UNSIGNED  pk
  kind    VARCHAR(20)      -- originating feature: comment, newsletter, order...
  sender  VARCHAR(100)     -- addMail() second argument, varies per context
  email   VARCHAR(100)
  subject VARCHAR(255)
  body    MEDIUMTEXT
  prio    TINYINT UNSIGNED -- addMail() priority argument
  created DATETIME         -- queued at
  next    DATETIME NULL    -- earliest retry after a temporary failure
  tries   TINYINT UNSIGNED
  state   TINYINT          -- pending / sent / failed
  locked  DATETIME NULL    -- claim timestamp
  lockid  BINARY(16) NULL  -- claiming run, so a stale claim is identifiable
  error   VARCHAR(255)     -- last failure reason
INDEX (state, prio, next, created, id)
INDEX (kind, created)
```

Column names stay single-word, matching the rest of the schema. Everything
`addMail()` receives today is representable: `sender` and `prio` are its second
and sixth arguments (`core/security.php:1014`), otherwise a queued message could
not be reconstructed.

`prio` keeps a bulk mailing from delaying a password reset: the drain orders by
priority first, then by `next`, then by arrival. `kind` drives the admin filter
and cleanup.

The message body is built **when the job is queued**, including the IP and
browser of the originating request that `addMail()` appends today. The worker
must never append its own request data — otherwise every notification would
carry the scheduler's IP.

`addMail()` currently returns `void` and discards the result of `mail()`. It has
to return `bool`, otherwise neither the queue nor the caller can tell whether
anything was delivered.

### Code

One class, `MailQueue` in `core/classes/mailqueue.php`:

- `addJob()` — store one message; called inside the caller's transaction
- `getBatch()` — claim a batch under a lock
- `setResult()` — record success or failure, increment `tries`
- `updateQueue()` — full drain run, called by the scheduler task
- `deleteSent()` — prune delivered rows

`addMail()` becomes a thin wrapper over `addJob()` and keeps its current
signature, so **all 26 call sites keep working untouched**. The actual `mail()`
call moves into the drain path, where its return value is finally checked.

Scope of the class, decided deliberately:

- **queue mechanics belong in it** — batching, locking, attempt counting and
  backoff are real invariants, the same reason `Cache` is a class;
- **the transport does not become a hierarchy yet.** A `MailTransport`
  interface with `PhpMailTransport` and `SmtpTransport` would copy the existing
  `CaptchaProvider` and `ContentDriver` pattern, but SMTP is out of scope here
  and an interface with a single implementation is dead code. Sending stays one
  private method, so the extension point is already isolated when a second
  transport is actually needed;
- **facades stay functions.** `addMail()` keeps the 26 call sites untouched,
  `addAdminMail()` answers "who receives" rather than "how to deliver", and
  `updateNewsletter()` remains the scheduler entry point. Only their bodies
  change.

One class, not a hierarchy.

### Delivery

- Claiming a batch is **atomic**: a single conditional `UPDATE` stamps `locked`
  and `lockid` on the rows it takes, and only then are they read back. Two
  concurrent runs therefore cannot claim the same row.
- A stale claim — a run that died before recording a result — is released by the
  lock timeout and picked up again.
- A temporary failure increments `tries`, stores `error` and sets `next` to the
  backoff deadline; the row is skipped until then.
- After the attempt cap the row becomes `failed` and is left for the admin view.
- Delivery is **at-least-once**: if a run dies after `mail()` succeeded but
  before the row is marked `sent`, that message is sent again. Exactly-once is
  not achievable against `mail()` and is not attempted.
- A delivery failure never rolls back the business transaction that queued it —
  the comment, order or registration is already committed.
- Batch size and retry limit live in configuration next to the existing
  `newsletter.count`.

### Scheduler

The drain runs as a system job beside the existing `cachegc`, `dbbackup`,
`filescan`, `newsletter` and `sitemap` jobs, reusing the same lock, heartbeat
and manual-trigger mechanics. Pruning of sent rows runs in the same task.

### Admin

The queue view extends `admin/modules/newsletter.php` rather than adding a new
module: list with filters by `kind` and `state`, the failure reason, retry and
delete actions. New user-visible text lands in all six locales at once
(`de`, `en`, `fr`, `pl`, `ru`, `uk`).

## Implementation steps

### Stage 1 — queue behind addMail()

- Add the `mailqueue` table, InnoDB, so a job can join the caller's transaction.
- Add `MailQueue` with `addJob()`.
- Change the `addMail()` return type from `void` to `bool`.
- Turn `addMail()` into a wrapper that queues instead of sending, building the
  full body — including the originating IP and browser — at queue time.
- Verify every one of the 26 call sites still returns immediately and produces a
  row; nothing is delivered yet in this stage.

### Stage 2 — drain task

- Implement `getBatch()`, `setResult()`, `updateQueue()`, `deleteSent()`.
- Check the `mail()` return value and record failures.
- Add locking, batch limit, retry with backoff, attempt cap and `failed` state.
- Register the scheduler job; keep it manually triggerable.
- Verify concurrent runs cannot double-send.

### Stage 3 — newsletter migration

- Convert `_newsletter.mails` into queue rows, preserving anything still
  pending.
- Point `updateNewsletter()` at the queue and drop the CSV slicing.
- Keep the `send` counter accurate.
- Verify a mailing to the current 164 subscribers drains at the configured rate
  instead of 4 per hour.

### Stage 4 — admin visibility

- Extend the newsletter module with the queue list, filters, retry and delete.
- Show pending, sent and failed counts.

Stages are independently shippable and must be delivered in this order.

## Verification

### Functional

- each of the 26 call sites produces exactly one row with the right `kind`,
  `sender`, `prio`;
- a request that sends mail returns without waiting for SMTP;
- a queued message is delivered by the drain task;
- a failing address increments `tries`, stores `error`, retries, then lands in
  `failed`;
- two concurrent drain runs never send one row twice;
- a delivery failure leaves the originating comment, order or registration
  intact;
- pruning removes only delivered rows.

### Performance

- comment submit latency before and after stage 1 (baseline: 26.7 s);
- drain throughput against the configured batch size;
- `EXPLAIN` on the claim query using `(state, prio, next, created, id)`;
- newsletter drain time for 164 recipients.

### Regression

- password reset, registration and contact mails still arrive;
- forum subscription notifications still arrive;
- order and shop notifications keep their sender and priority;
- admin and security notices unaffected.

### Environment

`php -l`, `phpstan`, full `phpunit`, `php-cs-fixer --dry-run` per stage. After
every state-changing run check `storage/logs/error_php.log`,
`storage/logs/error_sql.log` and `storage/logs/error_site.log`.

## Risks

- A queued message never drained because the scheduler is inactive on an
  installation — the admin view must make that visible.
- Rows claimed by a run that dies before recording a result; the lock timeout
  must release them.
- Retry storms against a permanently broken address; the attempt cap prevents
  them.
- The newsletter conversion losing addresses still pending in the CSV column.
- Queue growth on installations that never prune.

## Out of scope

- An SMTP transport with authentication, replacing PHP `mail()`. The queue makes
  it possible later; the transport itself is a separate decision.
- Bounce handling and unsubscribe links.
- Templating of message bodies beyond the existing `$conf['mtemp']`.
- Reworking who receives admin notifications (`admins.smail`).
