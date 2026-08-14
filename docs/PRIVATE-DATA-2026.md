# Private Data 2026

Work plan for the journals of an installation and the data around them: no
secret in a journal, a self-check that proves the private directories are not
served, a server configuration in the delivery, the runtime tree out of the
document root, and one prefix per meaning in the journal names.

Status: planned, nothing implemented. Run the batches in order 1 → 2 → 3 → 4.
They are independent in code and ordered by value; batch 4 is a migration and
belongs to a major release. Batch 5 depends on none of them and can land at any
point. Update this line as batches land.

No line numbers anywhere in this document on purpose: every reference names the
function, the file or the constant it points at, and that name is what to search
for.

## Problem

Two independent halves. Either one alone is survivable; together they turn one
server misconfiguration into a credential leak.

**Secrets reach the journals.** `Logger` masks them already: `MASKKEYS` replaces
the value of every key matching `pass`, `pwd`, `secret`, `token`, `key` or
`code` before a line is written, in the body as well as in the query string.
Two writers predate that class and bypass it entirely, writing with `fopen()`
and `fwrite()`:

- `login_report()` in `core/system.php` writes the first 25 characters of the
  submitted password into `log_admin.log` or `log_user.log` whenever a login
  fails. Three call sites pass it: the administrative login in
  `admin/index.php` and two visitor logins in `modules/account/index.php`.
- `addLog()` in `core/security.php` writes the whole request through
  `getVariablesInfo()` into `log.log`: `POST`, `GET`, `COOKIE`, `FILES` and
  `SESSION` verbatim, on every request while the `log` setting of the security
  configuration is on.

A journal holding a password is a credential store nobody audits. It travels
into backups, into the rotation archives `addCompress()` writes beside it, and
into any archive handed to support. Web access is only one of its exits.

**The delivery declares its private areas in a dialect one server reads.** The
tree carries 179 `.htaccess` files with `deny from all` — 116 under `modules`,
27 under `admin`, 18 under `storage`, and the rest across `uploads`, `setup`,
`templates`, `core`, `lang`, `config` and `blocks`. Apache honours them. nginx
never reads them, and nothing in the delivery states the same intent in a form
nginx understands. On such a server every one of those directories is handed out
as static content: the journals of `storage/logs`, the stored pages of
`storage/cache`, the module sources, and the administrative help of
`admin/info`, which describes the closed paths and the critical files of the
system to a reader who never logged in.

The declaration is not wrong. It is simply invisible to the server most
installations now run, and the installation has no way to notice.

## What already holds

Written down so the next reader does not re-derive it.

- **Masking works** for the six structured channels of `Logger`: `php`, `sql`,
  `file`, `site`, `warn` and `hack`. Only the two legacy writers escape it.
- **The runtime paths are already constants.** `LOGS_DIR`, `CACHE_DIR`,
  `COUNTER_DIR`, `UPLOADS_DIR` and `CONFIG_DIR` are resolved once at boot, so
  moving a tree is a change of the bootstrap and of the installer, not a sweep
  through the codebase.
- **The administration already warns about its environment.** `checkPerms()`
  prints a warning alert above a settings screen when the permissions of a path
  are wrong. A verdict about public readability belongs beside it and needs no
  new interface concept.
- **`uploads/` is public by design.** Stored files are served by direct URL, so
  it stays inside the document root whatever happens to the rest.
- **The address policy of the upload service must not be reused for a
  self-check.** `Upload::addRemoteFile()` refuses every address that is not
  globally reachable, which is exactly the address a site has when it asks
  itself a question. The self-check needs a transport of its own.

## Batch 1 — no secret reaches a journal

The smallest change and the only one that removes a cause instead of a
consequence. Nothing here depends on the other batches.

- **Stop carrying the password into the login report.** `login_report()` takes
  it as its fourth argument and prints it. Drop the argument, and drop it at the
  three call sites that pass a value. What a failed login has to record is who
  tried, from where and when; the string that was tried is not evidence, it is a
  liability.
- **Mask the request dump.** `getVariablesInfo()` collects `POST`, `GET`,
  `COOKIE`, `FILES` and `SESSION` with `print_r()`. It must pass every key
  through the same rule `Logger` uses, so one definition of "this is a secret"
  serves the whole system. Expose that rule from `Logger` instead of copying the
  pattern.
- **Do not dump the session and the cookies whole.** Both carry the identity of
  the visitor rather than the content of the request. Record their key names,
  the way the structured channels already do with `cookie_keys` and
  `session_keys`, and nothing else.
- **Decide the fate of the archives.** The rotation of `log.log`,
  `log_admin.log` and `log_user.log` keeps the old bodies as compressed files
  beside the live ones. A note in the release documentation is enough: an
  installation upgrading into this batch should delete the existing archives,
  because the code cannot know which of them predate the fix.

Tests: a probe that drives both writers with a request carrying a password, a
session and cookies, then asserts the resulting file contains none of the three
values and does contain the key names. A second case asserts a failed login
writes an entry without the attempted string.

## Batch 2 — the installation proves its private directories are private

The check that would have caught the second half of the problem from inside the
administration instead of from a browser.

- **Ship a marker.** One file per protected root, with a plain name — a leading
  dot invites a server rule that hides the marker while serving everything
  beside it, which is a false all-clear. Its body is a random string written at
  installation, so a copy of the delivery cannot be recognised by content alone.
- **Ask over HTTP and judge by the body.** Request the marker through the
  configured site address and compare the answer with the file. A status code
  decides nothing: an installation that maps its errors onto a CMS page can
  answer 200 with the error page or 404 with a body. The marker string is the
  only reliable signal.
- **Answer three states, never two.** `closed`, `open` and `unknown`. The third
  is for a transport that could not run at all: no cURL, a loopback the firewall
  drops, a timeout, a name that does not resolve. Reporting an unchecked
  directory as safe is the failure mode this batch exists to remove. The monitor
  made that mistake once already, in the field listing the extensions a build
  does not load: it answered the same string whether it had found nothing or had
  never been allowed to look.
- **Say which directory and which address.** An `open` verdict is only useful
  with the URL that proved it, ready to be pasted into a server configuration.
- **Run it on a schedule as well.** A verdict nobody opens is a verdict nobody
  has. The scheduler owns the periodic run; the administration shows the last
  one.
- **Keep the transport seam.** The class that performs the request must expose
  it the way `Upload` exposes `getRemoteReply()`, so the three states are
  testable without a network.

Tests: three unit cases over a stubbed transport, one per state, asserting the
verdict and the reported address. One case proves a body-matching answer with a
404 status is still reported `open`, and one proves a CMS error page with a 200
status is reported `closed`.

## Batch 3 — a server configuration in the delivery

The tree states its intent 179 times in a dialect nginx does not read. State it
once in a dialect it does.

- **Ship the file.** A configuration fragment covering every directory that
  carries a deny-all `.htaccess`, plus the markup of the template tree, which is
  assembled by PHP and never fetched. The asset directories of the templates
  stay public, as do `uploads/` and the front controllers.
- **Keep it honest with a test.** Enumerate the directories carrying a deny-all
  `.htaccess`, then assert the shipped fragment covers each of them. Without
  that test the fragment drifts the moment a directory is added, and a stale
  fragment is worse than none because it looks like protection.
- **Point at it from the administration.** The help of the security section
  should name the file and say plainly that `.htaccess` protects nothing on
  nginx.

Tests: the enumeration test above, plus a syntax check of the fragment where a
server binary is available; the enumeration alone is the one that must always
run.

## Batch 4 — the runtime tree out of the document root

The only measure no server misconfiguration can undo, and the only one that
breaks existing installations. It belongs to a major release and to a considered
migration, not to a patch.

- **Move what the system writes, keep what it serves.** `storage/` in all its
  parts — journals, stored pages, sessions, locks, counters — has no reason to
  sit where a request can reach it. `uploads/` stays, because its whole purpose
  is to be reachable.
- **One place decides.** The bootstrap resolves `LOGS_DIR`, `CACHE_DIR` and
  `COUNTER_DIR`; the installer writes the chosen location; everything else keeps
  reading the constants it already reads.
- **Migrate, do not assume.** An upgrade has to move the existing tree and fail
  loudly when it cannot, rather than silently starting a second one beside the
  first.
- **Update what names the old paths.** The administrative help, the security
  section that lists the journals, and the documentation of this repository all
  print `storage/...` today.

Acceptance for this batch: no path the system writes to at runtime resolves
inside the document root except `uploads/`, and an installation whose server
serves every file it can reach still exposes nothing but the files it was meant
to serve.

## Batch 5 — one prefix per meaning in the journal names

Independent of every other batch. It costs little, and it repairs two dashboard
fields that are wrong today for the same reason.

**The rule.** `error_*` is what the system could not do. `log_*` is what
happened. Nothing else changes: `error_php.log`, `error_sql.log`,
`error_site.log`, `log.log`, `log_admin.log` and `log_user.log` keep their names.

| Now | Becomes | Carries |
|---|---|---|
| `error_file.log` | `error_file.log` | only `error` and above |
| — | `log_file.log` | every file operation |
| `hack.log` | `log_hack.log` | recognised attacks |
| `warn.log` | `log_warn.log` | refused requests |

**Route by level, not by outcome.** A refusal is not an error: a file rejected
for its format, a quota that is full, an extension that is not allowed — the
system worked exactly as designed and the record belongs in the journal. What
belongs in `error_file.log` is what the system could not do: a missing decoder,
a partial that would not delete, a stored file that survived a failed database
write. The levels already say which is which, so the routing is `notice` and
`warning` to the journal, `error` and `critical` to both.

The duplication is deliberate: those lines are few, the journal stays a complete
history of one operation, and `error_*` becomes a signal that needs no reading —
a line appeared, something needs fixing. If duplication is unwanted at
implementation time, send the error line to the error file alone and accept a
journal that skips them.

**The labels already exist.** `_SEC_STAT_HACK` reads "Журнал атак" and
`_SEC_STAT_WARN` reads "Журнал запрещённых действий" — both stay as they are in
all six locales, only the keys of the label map in the security section move.
Exactly one new constant is needed, for `log_file.log`. Keep the retired keys
`hack`, `warn` and `error_file` in that map: the old files stay on disk with
their history, nothing migrates them, and without their keys they lose their
names in the list of journals.

**Repair the two consumers while renaming.** `getErrorLogCountHours()` counts
the lines of a bracketed timestamp format that `Logger` has never written, which
is why the dashboard reports zero errors whatever the journals hold; it has to
read the structured line and count the problem levels only.
`getDbIssueEventHours()` scans the file journal for database keywords, so a
stored file named after a database dump is reported as a database incident; it
must drop the operations journal from its sources.

The three mentions of the path in the upload help of the administration and the
comments naming the file in `Upload` and in `core/system.php` follow the rename.

Tests: a probe asserting a successful operation appears in the journal and not
in the error file, and a capability failure appears in both. A test asserting
every channel of `Logger` maps to a file and that the label map still covers the
retired names. A test over mixed structured lines asserting the counter reports
the problem levels only.

## Out of scope

- The content security policy of an installation. It is a property of the
  deployment, it breaks the editor when tightened blindly, and it has nothing to
  do with the data this plan protects.
- Rotation, retention and the format of the journals. Batch 1 changes what goes
  into a line, batch 5 changes which file the line lands in, and neither changes
  how long it is kept.
- The name of `log.log`. It says nothing about its content, but it is written by
  the legacy path rather than by `Logger`, so renaming it belongs with batch 1
  rather than with batch 5.

## Open

- **Where the marker of batch 2 lives** for a directory the server refuses to
  serve for an unrelated reason. A directory answering `unknown` forever is
  noise; a directory answering `closed` because a dotfile rule hid the marker is
  a lie. The plain name is the current answer, and it needs one round of testing
  against a stock panel configuration before it is trusted.
- **Whether batch 2 reaches beyond `storage/`.** Checking every one of the 179
  directories is neither useful nor fast. The current proposal checks the roots
  that hold data rather than code: the journals, the stored pages and the
  sessions. Whether the administrative help joins them is a judgement about how
  much a reader learns from it.
- **Whether the default of the security `log` setting should change.** A switch
  that dumps every request of every visitor is a debugging instrument, and batch
  1 makes it safe rather than useful. Turning it off by default is a separate
  decision with its own compatibility question.
