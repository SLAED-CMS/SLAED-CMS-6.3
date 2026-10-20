# Roadmap 2026

The one entry point for the work before release 8.0. The owner starts every session with the same command:

```
Работай по плану: docs/ROADMAP-2026.md
```

This file holds the order and the progress; the plans hold the content. No plan file decides its own order any
more: where a plan and this file disagree about the order, this file wins.

| Plan | Subject |
| --- | --- |
| `docs/0-PRIVATE-DATA-2026.md` | no secret in a journal, the `public/` tree, the self-check, the nginx file, journal names |
| `docs/1-FILES-2026.md` | one controlled delivery of uploaded files, `uploads/node/<type>/`, the archive retired |
| `docs/2-PROD-FINDINGS-2026.md` | retired addresses answer 410 or 301, `addFile()` writes data only |
| `docs/3-ASSET-CACHE-2026.md` | versioned asset addresses, browser lifetimes, `defer` |
| `docs/4-EDITOR-2026.md` | one editor shell; CodeMirror also a text editor to choose, Plain Plus stays light |
| `docs/5-NODE-HEAD-2026.md` | the header of a Node list from the stand `nh-13`, views by type, the RSS channel fixed in five points, a live list, search hints |
| `docs/6-PAGER-2026.md` | the segmented switch `sl-seg` with its travelling knob; the pager from the stand `pg-09`: one size, steps by its own column, the key tip |
| `docs/7-SCHEMA-2026.md` | one set of column domains, the time rule, the connection contract, `utf8mb4_unicode_520_ci`, unique email, keys that serve queries |
| `docs/8-RANKS-2026.md` | the rank of a group as a row of Bootstrap Icons, "N of M" in a colour of its own at 3:1 in both themes; the group list as a ladder and a rank composer from the stand `grp-10` |
| `docs/9-ADMIN-LOG-2026.md` | every journal of the site: the actions of the panel in `storage/logs/admin.log` in the one JSON format of `Logger`, written in `checkAdminPost()` with the logins; `user.log` and `oauth.log` on the same format; the login table `_login_log` for the profile and the panel; read on "Безопасность", linked from each module |
| `docs/10-URL-2026.md` | readable addresses without `id` — `/files/adaptivnyi-shablon-pure` — in one registry of routes; one address per page, 301 for every other form, an address never reused |
| `docs/11-UPDATE-2026.md` | updates from the panel: signed releases, the module `master` on slaed.net, the page "Обновления" in the core, the API as the branch `/api/` of `public/index.php` (written in Russian by the owner's wish) |
| `docs/12-ADMIN-UI-2026.md` | the etalons `demo/admin/` and `demo/site/` moved into the themes `admin` and `lite`: the shell, one canon of the list and the form, the home cockpit, the merged block "Администрация", the floating menu in the panel and on the site, sounds in each theme's `sound/`, the screensaver; DIAL folded in (written in Russian, like the etalons) |

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
- [x] 2. FILES batch 0 — *decision*: the open decisions, now recorded under "Decisions", then the inventory.
  (2026-10-05)
- [x] 3. FILES batch 1 — Node under one root, `uploads/node/<type>/`. (2026-10-05)
- [x] 4. PRIVATE-DATA batch 2 — the project out of web reach, the `public/` tree, the whole `uploads/` outside it
  with the light path. Its decisions were taken 2026-10-05 and stand in the plan; the step is the code. (2026-10-05)
- [x] 5. PRIVATE-DATA batch 3 — the self-check, `checkTypeGuard()` on it. (2026-10-06)
- [x] 6. PRIVATE-DATA batch 4 — `setup_old/` leaves the tree; `nginx.conf.example` landed with step 4 (2026-10-05).
  (2026-10-06)
- [x] 7. PROD-FINDINGS item 1 — a path that is no address answers 404 (the owner, 2026-10-06, in place of 410 for
  `.html`), missing uploads answer 410, `/index.php/…` answers 301. (2026-10-06)
- [x] 8. FILES batch 2 — inline attachment. (2026-10-06)
- [x] 9. FILES batch 3 — Node texts off the archive. (2026-10-06)
- [x] 10. FILES batch 4 — the protocol: `FileAccess`, `go=file`. (2026-10-06)
- [x] 11. FILES batch 5 — forum. (2026-10-06)
- [x] 12. FILES batch 6 — private messages and signatures. (2026-10-06)
- [x] 13. FILES batch 7 — comments of other targets. (2026-10-06)
- [x] 14. FILES batch 8 — retire the archive. (2026-10-06)
- [x] 15. FILES rehearsal — `update.php` on the fresh 6.2 dump the owner provides, as batch 3 of the plan names it:
  the crawl, the `src` values before and after, the files against a listing of the production folders. (2026-10-07)
- [x] 16. FILES batch 9 — unused files and the tree. (2026-10-07)
- [x] 17. FILES batch 10 — reference; deletes `docs/1-FILES-2026.md`. (2026-10-07)
- [x] 18. PRIVATE-DATA batch 5 — names that say what a journal holds. (2026-10-07)
- [x] 19. PROD-FINDINGS item 2 — `addFile()` writes data only; the plan's status line closes it and the file is deleted
  with its lasting part in `docs/VERSIONS.md`. (2026-10-07)
- [x] 20. ASSET-CACHE batch 0 — inventory and baseline. (2026-10-07)
- [x] 21. ASSET-CACHE batch 1 — versioned addresses. (2026-10-07)
- [x] 22. ASSET-CACHE batch 2 — lifetimes. (2026-10-07)
- [x] 23. ASSET-CACHE batch 3 — defer. (2026-10-07)
- [x] 24. ASSET-CACHE batch 4 — reference; deletes `docs/3-ASSET-CACHE-2026.md`. (2026-10-07)
- [x] 25. PRIVATE-DATA batch 6 — reference; deletes `docs/0-PRIVATE-DATA-2026.md`. (2026-10-07)
- [x] 26. EDITOR batch 0 — *decision*: the open decisions, the `<br>` check, the baseline. (2026-10-07)
- [x] 27. EDITORS defect — `<br>` dropped on mount: the repair at the source, `getTplTextarea()`, and a round-trip test
  of a stored value through the `plain` and the `markdown` save; the mechanism stands in `docs/EDITORS.md`, "Open
  Defect". Batch 0 pinned it by a probe of the server path only, so the step also saves `_users.sig` with a known
  value once through Toast UI in the browser, before and after the repair, and compares the bytes: what Toast UI
  itself does to the text on the client is not measured yet. Added by the owner on 2026-10-07, before batch 1, so
  CodeMirror as text does not inherit the loss. Done 2026-10-07: a stored `<br>` mounts as a line end for `plain` and
  as a Markdown hard break for `markdown` (owner, 2026-10-07); the reference is `docs/EDITORS.md`, "Line Breaks on
  Mount". (2026-10-07)
- [x] 28. EDITOR batch 1 — CodeMirror in the theme. (2026-10-07)
- [x] 29. EDITOR batch 2 — the manifest and the split build. (2026-10-07)
- [x] 30. EDITOR batch 3 — the shell and the runtime. (2026-10-08)
- [x] 31. EDITOR batch 4 — CodeMirror as a text editor. (2026-10-08)
- [x] 32. EDITOR batch 5 — file manager for Plain Plus and CodeMirror as text. (2026-10-08)
- [x] 33. EDITOR batch 6 — capsule and palette. (2026-10-09)
- [x] 34. EDITOR batch 7 — variables and the hint. (2026-10-09)
- [x] 35. EDITOR batch 8 — lint and format. (2026-10-09)
- [x] 36. EDITOR batch 9 — comparison. (2026-10-09)
- [x] 37. EDITOR batch 10 — preview. (2026-10-09)
- [x] 38. EDITOR batch 11 — reference; deletes `docs/4-EDITOR-2026.md`. (2026-10-09)
- [x] 39. PAGER batch 0 — segmented switch and knob: `sl-seg`, the knob in `slaed.js`, the header mode switch on it;
  one "Наверх" on `#top` and smooth scrolling in CSS, the dead code it clears; before NODE-HEAD, whose view switch
  stands on it. (2026-10-09)
- [x] 40. NODE-HEAD batch 0 — *decision*: the open decisions, the callers of `getModuleNavi()`, the baseline.
  (2026-10-09)
- [x] 41. NODE-HEAD batch 1 — the feed. (2026-10-10)
- [x] 42. NODE-HEAD batch 2 — the script. (2026-10-10)
- [x] 43. NODE-HEAD batch 3 — the data of the header. (2026-10-10)
- [x] 44. NODE-HEAD batch 4 — template, theme, strings. (2026-10-10)
- [x] 45. NODE-HEAD batch 5 — views. (2026-10-10)
- [x] 46. NODE-HEAD batch 6 — live list and "Показать ещё". (2026-10-11)
- [x] 47. NODE-HEAD batch 7 — search hints. (2026-10-11)
- [x] 48. NODE-HEAD batch 8 — reference; deletes `docs/5-NODE-HEAD-2026.md`. (2026-10-11)
- [x] 49. PAGER batch 1 — the pager in both themes. (2026-10-11)
- [x] 50. PAGER batch 2 — keys, tip and reference; deletes `docs/6-PAGER-2026.md`. (2026-10-11)
- [x] 51. RANKS batch 1 — the rank as icons: `getRankView()`, the fragment in both themes, the site readers, the
  migration of `_groups.rank` in the darkened tones. (2026-10-11)
- [x] 52. RANKS batch 2 — the panel: the ladder and the special groups, the composer of the group form from the stand
  `grp-10`, the pictures deleted. (2026-10-12)
- [x] 53. RANKS batch 3 — reference; deletes `docs/8-RANKS-2026.md`. (2026-10-12)
- [x] 54. SCHEMA batch 0 — inventory: time columns, indexes against queries, `GROUP BY`, uncut writes, `modul`
  values, `lang` width. (2026-10-12)
- [x] 55. SCHEMA batch 1 — connection: collation, time zone and `sql_mode` in `Database::__construct()`. (2026-10-12)
- [x] 56. SCHEMA batch 2 — text columns: `utf8mb4_unicode_520_ci` from one place, the text domains, cut writes.
  (2026-10-12)
- [x] 57. SCHEMA batch 3 — time: no `VARCHAR` time, moments and timers on their forms. (2026-10-12)
- [x] 58. ADMIN-LOG batch 0 — *decision*: the proposals of the plan, the names of the functions; added by the owner on
  2026-10-12, with the login journal of `docs/2027-CORE.md`, before the remaining SCHEMA batches so their final pass
  covers `_login_log`. (2026-10-12)
- [x] 59. ADMIN-LOG batch 1 — login table: `_login_log`, the profile screen, the tab of "Безопасность", `cachegc`.
  (2026-10-12)
- [x] 60. ADMIN-LOG batch 2 — file journals: the channels of `Logger`, the login written once, the record in
  `checkAdminPost()`. (2026-10-12)
- [x] 61. ADMIN-LOG batch 3 — reading the files: the table view of `logview` and the link of a module. (2026-10-12)
- [x] 62. ADMIN-LOG batch 4 — reference; deletes `docs/9-ADMIN-LOG-2026.md`. The reference is `docs/ARCHITECTURE.md`,
  "Journal Boundary"; the help of "Безопасность" took the journal view and the tab "Журнал входов". (2026-10-12)
- [x] 63. SCHEMA batch 4 — NULL, defaults, types; `_users.lang` codes. The stand was put on the release schema and
  collation on the way (owner, 2026-10-12). (2026-10-12)
- [x] 64. SCHEMA batch 5 — keys: unique email, covered indexes and prefixes out, missing ones in. The stand's three
  shared addresses stay on the active account (owner, 2026-10-12). (2026-10-12)
- [x] 65. SCHEMA batch 6 — rehearsal of `update.php` on a fresh 6.2 dump the owner provides; fresh installs. The owner
  chose a clean 6.2 from `slaed-old.loc/public/setup/sql` and that any 6.2 site migrates, `update.php` staying internal
  (2026-10-12); production is updated later on a fresh dump. (2026-10-13)
- [x] 66. SCHEMA batch 7 — reference; deletes `docs/7-SCHEMA-2026.md`. The reference is `docs/NODE.md`, "Schema
  conventions". (2026-10-13)
- [x] 67. URL batch 0 — the switch out and the inventory: `rewrite`, `sep`, `tsep`, `title`, `ctitle` leave the panel
  and the config; literals classified, module operations listed, list query counts as the baseline. The plan was taken
  out of `docs/2027-CORE.md`, moved before the release and turned to addresses without `id` by the owner on 2026-10-13.
  No 6.2 path form is parsed: production links only `index.php?…`, and its `.html` scheme answers 404 since
  2026-10-06. (2026-10-13)
- [x] 68. URL batch 1 — registry and name: `_routes`, the class `Route`, `filterSlug()` for six locales, suffixes,
  reserved words. `cur` is a column generated from `state`; the stand got the empty table. (2026-10-13)
- [x] 69. URL batch 2 — the writers: materials, categories, topics, users, polls inside their transactions; the field
  "Адрес"; the category cache carries the path. (2026-10-13)
- [x] 70. URL batch 3 — migration: `update.php` fills the registry, `setup.php` the seed; rehearsal on the clean 6.2.
  (2026-10-13)
- [x] 71. URL batch 4 — parsing: the cut before the database, the resolution after it, `getVar()` on the arrays; a
  path opens its page while the links stay `index.php?…`. (2026-10-14)
- [x] 72. URL batch 5 — building: `getSeoUrl()` on passed paths, the join in the lists, `page` for `num`. Every list
  page of the baseline kept its query count; the stand of the second machine got its registry the way batch 4 filled
  it. (2026-10-14)
- [x] 73. URL batch 7 — the links: literals of PHP, templates, scripts, mails and language constants, every one from
  the root of the site, the endpoints included. Put before the redirect by the owner on 2026-10-14: once batch 6 sends
  the query addresses to paths, a page opened at a path must carry no relative address. Templates take `{{ root }}`,
  theme pictures `getThemeImageUrl()` (owner, 2026-10-14). (2026-10-14)
- [x] 74. URL batch 6 — one redirect: after the access check, every reason in one 301, host and scheme included; 410,
  the legacy map on registry paths, sitemap, feed, `robots.txt`. The sitemap task writes the absolute `Sitemap:` line
  into `robots.txt` (owner, 2026-10-14). (2026-10-14)
- [x] 75. URL batch 8 — reference; deletes `docs/10-URL-2026.md`. The reference is `docs/ARCHITECTURE.md`, "Address
  Boundary"; `docs/NODE.md`, "Addresses", `docs/TEMPLATES.md`, "Links In Templates", `docs/VERSIONS.md`,
  `UPGRADING.md` and `README.md` follow it. (2026-10-14)
- [x] 76. UPDATE batch 0 — *decision*: the open questions of the plan, the probes, the baseline. Added by the owner on
  2026-10-15: a site learns of an update only by code it already has, so the updater goes into 8.0. The core checks
  the signature without `sodium` by its own class `Signature` (replaced `sodium_compat`, owner, 2026-10-16) and reads a zip through its own central directory with `PharData` beside
  `ZipArchive`; `public/sitemap.xml` leaves git and `public/robots.txt` is a site file; the whole tree ships (owner,
  2026-10-15). (2026-10-15)
- [x] 77. UPDATE batch 1 — one entry: the branch `/api/` of `public/index.php`, `API_FILE` and the light core.
  (2026-10-15)
- [x] 78. UPDATE batch 2 — the core of the connector without clients, `checkApiFlood`, the reply mode `file`. An
  action names its reply mode by the key `reply` (owner, 2026-10-15). (2026-10-15)
- [x] 79. UPDATE batch 3 — signature, keys, `files.json`, `tools/release.php` and `tools/sign.php`. The version
  constants are `SL_VERSION` and `SL_CODENAME`, within the 12 characters of `.rules/global.md` (owner, 2026-10-16).
  (2026-10-16)
- [x] 80. UPDATE batch 4 — the module `master`. Its scheduler jobs are known to the core and written into the scheduler
  of slaed.net by the settings of the master, never shipped in `config/scheduler.php` (owner, 2026-10-16). (2026-10-16)
- [x] 81. UPDATE batch 5 — the client: the check, the page "Обновления", the notice, the light boot that runs the
  database steps. (2026-10-16)
- [x] 82. UPDATE batch 6 — the client: download, manual upload, verification, unpacking. (2026-10-16)
- [x] 83. UPDATE batch 7 — settings, languages and themes: the installer adds missing options at any depth to the
  site's config files without a second layer (lists whole), the language files and the themes `admin` and `lite`
  replaced whole, other themes untouched. One batch by the owner's wish, 2026-10-15. The sections a site creates and
  deletes itself — Node types and field sets — are taken whole (owner, 2026-10-16). (2026-10-16)
- [x] 84. UPDATE batch 8 — the database: a resumable dump in portions and the restore from it, both callable without
  the core, limited to the site's table prefix. (2026-10-16)
- [x] 85. UPDATE batch 9 — the client: installation in steps, the guard of all three entry points, and rollback of
  files, `config/` and the database with the caches cleared. (2026-10-17)
- [x] 86. UPDATE batch 10 — the rehearsal script `tools/rehearse.php` that gates every publication, then the full
  rehearsal on a copy of the stand, a key change included. The rig is two OSPanel projects, `master.loc` and
  `rehearse.loc`; the light mode takes the portion dump of the site and the shipped `config/` lost the values of the
  stand (owner, 2026-10-17). (2026-10-17)
- [x] 87. UPDATE batch 11 — reference and the real keys; deletes `docs/11-UPDATE-2026.md`. The reference is
  `docs/UPDATE.md`; a test run trusts the test keys through `UPDATE_KEYS` (owner, 2026-10-18); the owner held the key
  ceremony and the factory keys with their fingerprints in `README.md` are real. (2026-10-18)
- [x] 88. ADMIN-UI batch 0 — *decision*: the nineteen open questions of the plan, the inventory of the panel lists for
  the canon, the baseline. Added by the owner on 2026-10-19 with the etalons `demo/admin/` and `demo/site/`; the former
  step 88 DIAL (the closed options dial `sl-dial` is three Tab stops a row, found 2026-10-09) is folded into batch 3.
  The answers stand in the plan, "Ответы шага 0"; the window "Наблюдение" shows only the present, the session limit is
  done now on the PHP session, every site sound with an event ships, the file manager stays outside the list canon
  (owner, 2026-10-19); `axe-core` joined `tools/browser-audit.mjs` as `--axe`. The label crawl reads readable paths
  again through `tests/Support/address_probe.php` and its baseline was rebuilt green, 442 routes and no violation
  (owner, 2026-10-19). (2026-10-19)
- [x] 89. ADMIN-UI batch 1 — the base in JS: one notice with undo, one motion switch, the axis of `setSlideMotion()`,
  the toggle storage helper, the shared palette controller. The motion switch is `--sl-time-scale`, every duration of
  the API block multiplied by it; the notice is `fragments/toast.html` of each theme; `_EDITOR_UNDO` became `_UNDO`
  (owner, 2026-10-19). (2026-10-19)
- [x] 90. ADMIN-UI batch 2 — the header: logo without `h1`, tab title, skip link, icons, the phone drawer. The tab
  title joins its parts by `$conf['defis']` and keeps `_ADMIN`; the classes come from the etalon, the hidden text is
  `sl-visually-hidden` as in lite (owner, 2026-10-19). (2026-10-19)
- [x] 91. ADMIN-UI batch 3 — the menu and DIAL: filter, current section, "…" on hover and focus; a closed dial is one
  Tab stop. The classes of the filter come from the etalon, `sl-menu-top`, `sl-menu-find`, `sl-menu-empty`, and the
  filter stands on module screens only until batch 6 brings it to the home (owner, 2026-10-19). Tab stops fell from
  233 to 104 on the home and from 470 to 242 on "Комментарии". (2026-10-19)
- [x] 92. ADMIN-UI batch 4 — one source of the queues: the bell, "Ждут внимания", the chips, the count in the tab title.
  The source is `getAdminQueues()`, the bell `fragments/bell.html`, the chip `sl-queue` on every section with a queue
  ("Материалы" sums its types), the block tone `sl-block-menu` / `sl-block-queue`; the poll `getAdminQueueData` runs once
  a minute for every administrator (owner, 2026-10-19). (2026-10-19)
- [x] 93. ADMIN-UI batch 5 — the option op and its cookie, "Опции" without reload, the row "Движение". The op is
  `option` (`setAdminOption()`, 204), the motion cookie `still` read by `isThemeStill()` into `data-sl-still` of all
  five documents; `mode` and `changeeditor` of the panel are gone, the language keeps `newlang` and its reload; `_ON`,
  `_OFF`, `_ADM_MOTION` (owner, 2026-10-19). The stand's `config/local.php` was rebuilt for the version map and issued
  a new master (owner, 2026-10-20). (2026-10-20)
- [x] 94. ADMIN-UI batch 6 — Ctrl+K and the rail. The palette is `fragments/palette.html` of the theme admin on
  `setPaletteOpen()`, its sections gathered from the menu or the cards; the rail cookie `rail` goes through op
  `option` and one reader `isThemeFlag()` replaced `isThemeStill()`; the blocks that are no menu step out of the rail
  until batches 16 and 19 (owner, 2026-10-20). `_EDITOR_NONE` became the global `_NONE_FOUND`, which both palettes
  read. (2026-10-20)
- [x] 95. ADMIN-UI batch 7 — the end of the session. The limit is the key `idle` of `config/security.php` read by
  `getAdminIdle()` (the lesser of it and `session.gc_maxlifetime`), the time of the last action
  `$_SESSION[admin_c.'-time']`, which `core/security.php` checks on every request and renews on every request of the
  panel that carries no `X-SL-Poll`; op `keep` is `updateAdminSession()`, the window `#sl-session` stands in
  `layouts/admin.html`, the poll of the bell answers `{left, rows}` (owner, 2026-10-20). The texts are `_ADM_END`,
  `_ADM_LEFT`, `_ADM_LOST`, `_ADM_KEEP`, since a template reads no constant longer than 12 characters. (2026-10-20)
- [ ] 96. ADMIN-UI batch 8 — the list canon, frame, on "Комментарии": fragment, filter, address, tabs, pagers.
- [ ] 97. ADMIN-UI batch 9 — the list canon, row and view, on "Комментарии".
- [ ] 98. ADMIN-UI batch 10 — the list canon, actions with undo, on "Комментарии".
- [ ] 99. ADMIN-UI batch 11 — the form canon on "Конфигурации".
- [ ] 100. ADMIN-UI batch 12 — the panel lists onto the canon, part 1.
- [ ] 101. ADMIN-UI batch 13 — the panel lists onto the canon, part 2.
- [ ] 102. ADMIN-UI batch 14 — the data of the cockpit: the monitor functions in `core/monitor.php`, the signals, the
  cache, the poll.
- [ ] 103. ADMIN-UI batch 15 — the cockpit of the home.
- [ ] 104. ADMIN-UI batch 16 — the merged block "Администрация" in both themes, `dock.css` of each theme.
- [ ] 105. ADMIN-UI batch 17 — the window "Наблюдение" in both themes.
- [ ] 106. ADMIN-UI batch 18 — the floating menu in the panel: the layer.
- [ ] 107. ADMIN-UI batch 19 — the floating menu in the panel: pins, palette entry, watch ring, admin window.
- [ ] 108. ADMIN-UI batch 20 — the floating menu on the site.
- [ ] 109. ADMIN-UI batch 21 — sounds in `sound/` of each theme, `getThemeFileUrl()`, `public/sound/` retired.
- [ ] 110. ADMIN-UI batch 22 — the screensaver "Возрождение".
- [ ] 111. ADMIN-UI batch 23 — reference; deletes `docs/12-ADMIN-UI-2026.md`.
- [ ] 112. Close — no `docs/*-2026.md` plan is left; delete this file and report that the work before 8.0 is done.
