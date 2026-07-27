# Executing the 2026 plans

Cookbook for handing `docs/MAIL-2026.md` and `docs/COMMENTS-REDESIGN-2026.md`
to an assistant. Three steps, then the prompts.

## Step 1 — open a new chat

**One batch per chat.** Stay in that chat until the batch is finished and its
checks pass — corrections, re-runs and follow-up questions all belong there.
Open the next chat only after the finished batch is committed.

Never reopen an old chat for a *new* batch. Earlier revisions of these plans
contain decisions that were later reversed, and a chat that remembers them will
build the wrong version.

## Step 2 — paste the prompt

They are below, ready to copy. Nothing else needs to be said.

## Step 3 — read the report, then commit yourself

Every prompt ends with "do not commit", so grouping stays your decision.

**Commit before opening the next chat.** The next chat starts from the working
tree, so an uncommitted batch means it cannot tell your changes from its own, and
you lose the ability to revert one batch without losing the others.

Move on only if the report says the checks ran **and passed**. If something is
wrong, say so in the same chat rather than opening a new one — that chat still
holds the context needed to fix it.

## Before anything: commit the parser fix

`core/classes/parser.php` and `tests/Unit/ParserFixturesTest.php` carry the
stored-XSS fix, and **prompt 1 states it as already done**. Commit them first, on
their own, or the first chat starts from a premise its own working tree does not
support.

Two constraints apply at once, and both come from `.rules/git.md`.

**By path.** The working tree also holds the plan rewrite, and
`docs/MAIL-2026.md` is already staged from the `git mv` that renamed it. A bare
`git commit` would sweep that into the security commit. Naming the paths commits
exactly those two files and leaves everything else, staged or not, untouched.

**From a prepared message file**, not inline `-m` — `.rules/git.md:33-35`. Build
it from `.gitmessage`; `commit.template` is not configured, so nothing attaches
it automatically.

```bash
cp .gitmessage .git/COMMIT_PREPARED     # then fill it in, removing the comments
git commit -F .git/COMMIT_PREPARED -- core/classes/parser.php tests/Unit/ParserFixturesTest.php
```

Any path outside the repository works equally well for the message file;
`.git/` is convenient because it is never part of a diff.

`.rules/git.md:37` also says never to leave uncommitted changes behind, and
`:38` says to split by topic. Both hold: the parser fix is one topic and goes
first, and the remaining work — plan rewrite, `docs/EXECUTION-2026.md`,
`tools/comment-baseline.php`, the PHP-version bump, the module defaults — is
committed in its own topical commits rather than left dangling.

## Once per stage, before its first chat

Comments stages only:

```bash
php tools/comment-baseline.php capture
```

**Once per stage, not per batch.** `capture` overwrites the reference; running it
again mid-stage would re-baseline against already-changed code and the parity
check would silently start comparing the work to itself. The prompts only ever
run `verify`.

---

## Order of work

Do them top to bottom. Only one row depends on the other plan.

| # | Chat | Prompt |
|---|---|---|
| 1 | Comments stage 0 | ready below |
| 2 | Mail stage 1, batches 1-6 | template, one chat per batch |
| 3 | Comments stage 1, batches 1-6 | template, one chat per batch |
| 4 | Mail stage 2 | template |
| 5 | Comments stage 2 | template |
| 6 | Comments stage 3 | template — **needs mail stage 2 done** |
| 7 | Mail stages 3, 4 | template |
| 8 | Comments stages 4, 5 | template |

---

## Prompt 1 — comments stage 0, ready to paste

```
Read .agents/SYSTEM-PREAMBLE.md and .rules/*, then
docs/COMMENTS-REDESIGN-2026.md.

Implement Stage 0 — close the trust boundary — only, and only the
"Client-chosen moderation and module" half. The stored-XSS half is already
fixed in core/classes/parser.php:228; do not redo it. Do not touch
filterHtml(), do not change any render site to safe=true, and do not start
Stage 1.

Before you begin: record the size and modification time of storage/logs/
error_php.log, error_sql.log and error_site.log. Confirm vendor/bin/phpunit is
green and php tools/comment-baseline.php verify passes, so a later failure is
attributable. Do not run capture — the baseline is already taken.

When you are done run: php -l on touched files, vendor/bin/phpunit,
vendor/bin/php-cs-fixer check --config=.php-cs-fixer.dist.php <touched paths>,
php tools/comment-baseline.php verify, and inspect only the log entries added
since the sizes you recorded.

Verify the stage's own criteria: a crafted cid cannot publish past
premoderation, a crafted mod cannot attach a comment to another module or move
another target's counter, and a moderator of one module cannot change a comment
in another.

If the code materially contradicts the plan, do not improvise and do not modify
the disputed area. Report it with file:line and continue only with work that
cannot touch it. If a measured number in the plan no longer matches reality,
re-measure and update the plan within this batch.

Before your report, update the plan's Progress section: the batch completed,
decisions made, deviations, checks run, remaining blockers.

Report per .rules/report.md, stating which checks you actually ran. Do not
commit.
```

## Template for every other stage

Replace the three CAPS words. Delete the square-bracket line unless it is
stage 1. Delete the two `comment-baseline` lines for mail stages — that tool only
covers comments.

```
Read .agents/SYSTEM-PREAMBLE.md and .rules/*, then docs/PLANFILE.

Implement STAGE only. [Batch NUMBER of the batch table in that stage — do not
start the next batch.] Do not touch anything outside that scope, and do not
fix things you notice on the way; report them instead.

Before you begin: record the size and modification time of storage/logs/
error_php.log, error_sql.log and error_site.log. Confirm vendor/bin/phpunit is
green and php tools/comment-baseline.php verify passes. Do not run capture —
the baseline is already taken for this stage.

When you are done run: php -l on touched files, vendor/bin/phpunit,
vendor/bin/php-cs-fixer check --config=.php-cs-fixer.dist.php <touched paths>,
php tools/comment-baseline.php verify, and inspect only the log entries added
since the sizes you recorded.

Run the verification the plan lists for this stage, and say which checks you
ran and which you skipped.

If the code materially contradicts the plan, do not improvise and do not modify
the disputed area. Report it with file:line and continue only with work that
cannot touch it. If a measured number in the plan no longer matches reality,
re-measure and update the plan within this batch.

Before your report, update the plan's Progress section: the batch completed,
decisions made, deviations, checks run, remaining blockers.

Report per .rules/report.md. Do not commit.
```

`PLANFILE` is `MAIL-2026.md` or `COMMENTS-REDESIGN-2026.md`.
`STAGE` is the `###` heading copied exactly, including the part after the comma —
`Stage 2 — queue and drain, one release`, not `Stage 2 — queue and drain`.
`NUMBER` is the batch row, stage 1 only. Other stages have no batches.

For mail stages, delete both sentences mentioning `comment-baseline` — the whole
"Do not run capture" sentence with them. That tool covers comments only.

**Comments stage 2 is the exception to `verify`.** The body migration changes
rendered output on purpose, so `verify` reporting CHANGED there is the expected
result, not a failure. Replace the "when you are done" `verify` with:

```
Do not run comment-baseline verify as a pass/fail gate — this stage changes
rendered output by design. Instead diff a sample of each migrated class against
the pre-migration output for meaning, then re-capture the baseline at the end.
```

---

## If something goes wrong

| Symptom | What it means |
|---|---|
| `comment-baseline verify` reports CHANGED | markup moved. A failure in stage 0 and stage 1, **expected** in stage 2. The diff is in `storage/baseline/comments/<module>.actual.html` |
| phpunit fails and was green before | that batch broke it; do not start the next |
| the assistant says the plan is wrong | it may be right. Ask it to show file:line and measure |
| the assistant offers a compatibility wrapper | refuse. Both plans replace contracts outright |
| a log file grew during the batch | read it before accepting the work |

## Two rules worth enforcing by hand

**Security fixes need a test that fails without them.** Ask: "stash the fix, run
the test, show me it fails, restore." A test written after a fix usually passes
for the wrong reason.

**Numbers in the plans are measured, not estimated.** If the assistant quotes a
different one, it must re-measure and update the plan within that batch — you
still make the commit.
