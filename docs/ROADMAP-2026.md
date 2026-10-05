# Roadmap 2026

The one entry point for the work before release 8.0. The owner starts every session with the same command:

```
Работай по плану: docs/ROADMAP-2026.md
```

This file holds the order and the progress; the four plans hold the content. No plan file decides its own order any
more: where a plan and this file disagree about the order, this file wins.

| Plan | Subject |
| --- | --- |
| `docs/0-PRIVATE-DATA-2026.md` | no secret in a journal, the `public/` tree, the self-check, the nginx file, journal names |
| `docs/1-FILES-2026.md` | one controlled delivery of uploaded files, `uploads/node/<type>/`, the archive retired |
| `docs/2-PROD-FINDINGS-2026.md` | retired addresses answer 410 or 301, `addFile()` writes data only |
| `docs/3-ASSET-CACHE-2026.md` | versioned asset addresses, browser lifetimes, `defer` |

## How a session runs

1. **Check the tree.** `git status`. Uncommitted changes other than `config/security.php` belong to an earlier step
   or to the owner: stop and ask through `AskUserQuestion` whether they are committed first. A step never starts on
   top of another step's diff.
2. **Take the first open step** below — the first line with `[ ]`. Exactly one step per session; the next one starts
   in a new window.
3. **Read what governs it:** the section of its plan the step names, the plan's "Design" parts it relies on, and the
   `.rules/*.md` of the area. Decisions already recorded in a plan are settled; do not reopen them.
4. **Do the step completely,** with the tests the plan names for it. After edits run `--filter` on the covering tests;
   one full phpunit run at the end of the step, not more. Theme or markup changes take the `npm run ui:before` /
   `ui:after` pair and `npm run ui:gates`.
5. **Close it.** Tick the step here (`[x]` and the date), update the `Status:` line of its plan, report in the format
   of `.rules/report.md`, and stop. Nothing is committed without the owner's command.
6. **When a step cannot finish** — a decision is missing, the root cause cannot be pinned, the fix leaves the scope —
   ask through `AskUserQuestion`; if the answer changes a plan, write it into that plan before going on.

A plan that a later step still reads may name a plan an earlier step has deleted; that plan's lasting part is in
`docs/` by then, and the reference is history, not a task.

The stand database is the migrated production dump `storage/backup/prod_migrated_2026-10-01.sql`; no unmigrated 6.2
dump and no production uploads are kept. A step that needs them asks the owner.

A step marked *decision* is a conversation first: put the open questions of the plan to the owner, write the answers
into the plan, then do whatever work the step names.

## Steps

- [x] 1. PRIVATE-DATA batch 1 — no secret reaches a journal. (2026-10-05)
- [ ] 2. FILES batch 0 — *decision*: the open decisions of "Decisions before work starts", then the inventory.
- [ ] 3. FILES batch 1 — Node under one root, `uploads/node/<type>/`.
- [ ] 4. PRIVATE-DATA batch 2 — the project out of web reach, the `public/` tree.
- [ ] 5. PRIVATE-DATA batch 3 — the self-check, `checkTypeGuard()` on it.
- [ ] 6. PRIVATE-DATA batch 4 — `nginx.conf.example`, `setup_old/` leaves the tree.
- [ ] 7. PROD-FINDINGS item 1 — `.html` and missing uploads answer 410, `/index.php/…` answers 301.
- [ ] 8. FILES batch 2 — inline attachment.
- [ ] 9. FILES batch 3 — Node texts off the archive.
- [ ] 10. FILES batch 4 — the protocol: `FileAccess`, `go=file`.
- [ ] 11. FILES batch 5 — forum.
- [ ] 12. FILES batch 6 — private messages and signatures.
- [ ] 13. FILES batch 7 — comments of other targets.
- [ ] 14. FILES batch 8 — retire the archive.
- [ ] 15. FILES batch 9 — unused files and the tree.
- [ ] 16. FILES batch 10 — reference; deletes `docs/1-FILES-2026.md`.
- [ ] 17. PRIVATE-DATA batch 5 — names that say what a journal holds.
- [ ] 18. PROD-FINDINGS item 2 — `addFile()` writes data only; the plan's status line closes it and the file is deleted
  with its lasting part in `docs/VERSIONS.md`.
- [ ] 19. ASSET-CACHE batch 0 — inventory and baseline.
- [ ] 20. ASSET-CACHE batch 1 — versioned addresses.
- [ ] 21. ASSET-CACHE batch 2 — lifetimes.
- [ ] 22. ASSET-CACHE batch 3 — defer.
- [ ] 23. ASSET-CACHE batch 4 — reference; deletes `docs/3-ASSET-CACHE-2026.md`.
- [ ] 24. PRIVATE-DATA batch 6 — reference; deletes `docs/0-PRIVATE-DATA-2026.md`.
- [ ] 25. Close — no `docs/*-2026.md` plan is left; delete this file and report that the work before 8.0 is done.
