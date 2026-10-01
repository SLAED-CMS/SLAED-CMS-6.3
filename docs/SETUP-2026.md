# Setup 2026

Work plan for the new installer of SLAED CMS 8.0: one file `setup.php` in the site root, a clean installation only,
the look of the stand face `demo/setup-09-motion.html`, and the file deletes itself when the installation is done.
Updating an existing site is not the installer's business: it moves into `update.php` now and into an admin module
later, which is designed separately.

Status: planned on 2026-10-01; batches 0, 1 and 2 landed on 2026-10-01, batch 3 is next. Batches run in order; update this line as they land. The last batch
moves what lasts into the permanent reference and deletes this file.

No line numbers for this tree anywhere in this document on purpose: every reference names the function, the file or
the constant it points at.

## How to run this plan

The owner starts a session with «Делай по плану docs/SETUP-2026.md». That command means exactly this:

1. Read `CLAUDE.md`, then the whole of every `.rules/*.md` the next batch touches, and take the skills whose
   description matches the batch (`/execute-test-suite` always; `/manage-theme-tokens` and `/manage-slaed-templates`
   for batch 3; `/modernize-php-code` for batch 2; `/secure-inputs-and-forms` for batch 4).
2. Find the first batch in the table below whose status is not `landed`. That batch is the whole job of the run.
3. If the batch lists an open question that is still open, ask it through `AskUserQuestion` before any edit.
4. Do the batch completely, run every check under its **Verify**, fix what fails. A check that cannot run is named in
   the report, never passed over.
5. Set the batch to `landed` with the date in the table, update the status line at the top, and add to the batch
   anything learnt that a later batch needs.
6. Report in the format of `.rules/report.md` and stop. The next run takes the next batch.

What the run never does on its own:

- Commit or push. A commit follows only the owner's explicit command, staged by exact paths, without
  `Co-Authored-By`, through `.gitmessage`, and `'secret'` in `config/security.php` blanked before it.
- Touch `setup_old/` (the old installer, kept in git by the owner's decision), the owner's change in
  `config/security.php`, or the stand config files.
- Change the decisions in the table below. A decision that turns out wrong in the code stops the batch and goes to
  the owner as a question.

| Batch | Subject | Status |
| --- | --- | --- |
| 0 | the schema moves to `storage/update/sql/` | landed 2026-10-01 |
| 1 | the 6.3 update moves into `update.php` | landed 2026-10-01 |
| 2 | texts `_SETUP_*` in `admin/lang/*.php` | landed 2026-10-01 |
| 3 | the admin theme carries the installer | planned |
| 4 | `setup.php` | planned |
| 5 | the first administrator leaves `admin.php` | planned |
| 6 | tests | planned |
| 7 | references and the end of this plan | planned |

## Decisions taken

| Question | Decision |
| --- | --- |
| Shape | one file `setup.php` in the root, no `setup/` directory, deletes itself after a clean run |
| Scope | a new installation only; the update branches, `config/setup.unlock` and its code gate leave the installer |
| Look | stand face 09 «Порог · Движение»: the login card of the admin theme, seven segments, motion layer |
| Markup | templates of the admin theme, nothing printed from PHP |
| Styles | the admin theme; missing pieces are added to the theme, no style of the installer's own |
| Buttons | standard classes only: back `sl-but-back`, probe `sl-but`, next `sl-but-blue`, install `sl-but-green`, every one with an icon |
| Colours | existing tokens only, no `color-mix()`, hex or `rgba()` outside the API block |
| Texts | the main admin language files `admin/lang/*.php`, all six locales, reusable by the future update module |
| Schema | `storage/update/sql/` — `table.sql`, `insert.sql`, `table_update6_3.sql`; `storage/.htaccess` already denies the web |
| 6.3 update | the units of the old installer move into `update.php`, an internal tool of the owner that is not shipped; the release carries a new installation only; the update files 4.1…6.2 are dropped |
| CSRF | a one-time installation token: written to `storage/` on the first form, carried in a hidden field to the end, deleted with the file; recorded as an exception in `.rules/global.md` |
| Run | in parts: configuration, tables in groups, data, administrator — separate requests, the card shows the true progress |
| First administrator | created by the installer itself; `addAdminAccount()` and the empty-table form leave `admin/index.php` |

## What exists today

- `setup/` left the repository with `b2973ad4`; the old code is tracked again as `setup_old/` by the owner's
  decision, byte for byte the `setup/` of `e28d3639`, except its `.sql` files, which `.gitignore` keeps out. The root
  `setup.php` still requires `setup/index.php` and is broken until batch 4. Never restore `setup/` into the working
  tree.
- The old installer boots without `core/system.php`: it reads `config/global.php` and `config/security.php`, borrows
  `getSqlbatch()` and `getSqlinfo()` from `core/admin.php`, `FileManager` for the config lock, `Logger`, and loads
  `Database` from `core/classes/pdo.php` late. `Database` calls the installer's `setExit()` under `SETUP_FILE`.
- A new installation in `save()` of `setup_old/index.php`: validate the prefix and the panel file, refuse an unwritable
  `config/`, refuse a pending config journal, connect, `checkUpdateBase(..., true)` (server version, no table of the
  prefix), write `global.php` (`language`, `homeurl`), rename `admin.php`, write `security.php` (`afile`) and `db.php`,
  drop the Node types of an earlier installation with `deleteSetupTypes()`, run `table.sql` then `insert.sql`, and
  write the marks of `config/update.php` (`node` = `new`). The owner then creates the administrator in `admin.php`.
- The first administrator: `getAdminLoginForm()` shows `partials/auth-form.html` while `_admins` is empty;
  `addAdminAccount()` inserts the row, optionally the user row, and calls `addNodeProfiles()`, which imports every
  `modules/node/profiles/*.json` through `NodeService` and needs the full core. That form carries no CSRF token.
- The template layer runs without the database and without `core/system.php`: `new Template('admin')` needs only
  `FUNC_FILE`, `BASE_DIR` and a writable `storage/cache/templates/admin`. `layouts/bare.html` with `is_bare` off is
  exactly the login card of face 09. The caller builds `meta`, `links`, `scripts`, `adlogo`, `license` and `mode`.

### Traps found before the plan

1. **`.gitignore` drops the schema.** `*.sql` is ignored and only `setup/sql/*.sql` is let back in;
   `storage/update/sql/*.sql` would vanish from the repository and from the probe copy without a word.
2. **Function names collide with the core.** The old installer declares `setConfigFile`, `getProtocol`,
   `getRandomString`, `setHead`, `setFoot`, `getIp`, `getLang`, `setExit`, `filterVar`, which the core declares too.
   A file that ever loads `core/system.php` cannot carry them; every installer function needs its own name.
3. **The language cookie differs.** The old installer wrote `{user_c}-lang`, the core reads `{user_c}-language`; the
   language chosen in the installer was lost for the administrator. The new one writes the core cookie.
4. **Three language constants clash.** `_LANG`, `_PHPSETUP` and `_TABLE` of `setup_old/lang` exist in
   `admin/lang` with another meaning. The new texts take the scope prefix `_SETUP_`.
5. **A 6.2 site cannot boot the core.** Its `config/config_*.php` and `db.php` do not return arrays, so
   `getConfig()` finds no database and `update.php` dies before its `isAdmin(true)` gate. The 6.3 units need a stage
   that runs before the core (see open question 1).
6. **`PointOwnersTest` walks `setup/`** and throws while the directory is missing; `CommentIsolationTest` and
   `UnusedCodeAuditTest` skip only the top-level `setup` directory.
7. **`Database` calls `setExit()` itself.** Its constructor in `core/classes/pdo.php` calls `setExit()` on a refused
   connection, and under `SETUP_FILE` it expects the installer to have declared one. An installer that declares
   `setExit()` cannot boot the core later (trap 2). Batch 4 replaces that call under `SETUP_FILE` by a thrown
   exception the installer catches, so `setup.php` declares no core name at all.
8. **`addNodeProfiles()` lives in `admin/index.php`,** a file that routes the panel when it is included, so
   `setup.php` cannot load it. Batch 4 moves it to `core/admin.php`, whose guard already admits `SETUP_FILE`.

## Open questions

None open. Question 1 (how `update.php` reaches a 6.2 site) was answered by the owner on 2026-10-01: `update.php`
is never shipped, it is an extra tool of the owner, and it runs at the owner's risk without a key and without a login.

## Batches

### Batch 0 — the schema moves to storage/update/sql

- Move `table.sql`, `insert.sql` and `table_update6_3.sql` to `storage/update/sql/`; drop `table_update4_1.sql` …
  `table_update6_2.sql`.
- `.gitignore`: `!storage/update/sql/*.sql` instead of `!setup/sql/*.sql`.
- Repoint the schema readers: `InsertValidationTest`, `PhpFileFormatTest`, `SchemaUpdateValidationTest`,
  `CommentThreadTest`, `EditorRoomTest`, `NodeIntegTest`, `NodeModelTest`, `RatingTest`, `DatabaseBatchTest`, the
  probes `node`, `point`, `privat_class`, `privat`, `rating`, `route`, `install`, and `tools/node-profile.php`.
- `.claude/commands/schema.md` reads the new path in the same batch, or `/schema` breaks at once. The mention of
  `setup/sql` in `.claude/skills/execute-test-suite/SKILL.md` is changed only after the owner allows editing a skill.
- Verify: `git check-ignore` answers nothing for the three files; every repointed test is green. Expected red until
  later batches, and named as such in the report: the tests that lift or grep `setup/index.php` (batch 1:
  `Update*Test`, `update_probe.php`, the greps of `BackupContractTest`, `FeedTest`, `NodeServiceTest`, `NodeSyncTest`,
  `UploadIntegrationTest`, `DatabaseBatchTest`), the installer contract (batch 6: `NodeProfileTest`, `UpdateSiteTest`,
  `install_probe.php`) and `PointOwnersTest` (batch 6). Any other red test is this batch's to fix.
- Learnt on landing:
  - `storage/update/` and `storage/update/sql/` carry the `index.html` guard of every storage directory; the web is
    already shut by `storage/.htaccess`, so no `.htaccess` of their own.
  - The full suite after the batch: 67 failures and 4 errors, every one a read of the missing `setup/index.php`,
    `setup/lang/` or `setup/` as a directory — `Update*Test` (Config, Fields, Mails, Points, Ratings, Setup, Site),
    `NodeProfileTest`, `PointOwnersTest`, and the greps of `BackupContractTest`, `DatabaseBatchTest`, `FeedTest`,
    `NodeServiceTest`, `NodeSyncTest`, `UploadIntegrationTest`. Batch 1 starts from that list.
  - `install_probe.php` copies the tracked and the untracked files git does not ignore, so the uncommitted
    `storage/update/sql/` reaches its copy; only `config/` comes from `HEAD`.
  - The path in the comment of `getEditorRoomData()` in `core/helpers.php` is already repointed; batch 7
    keeps only its other setup mentions. `docs/*`, `CONTRIBUTING.md` and the skill `execute-test-suite` still name
    `setup/sql` — batch 7, the skill only with the owner's leave.

### Batch 1 — the 6.3 update moves into update.php

- `update.php` is internal and stays out of the release. After open question 1: the pre-core stage, the units `setUpdatePoints()`, `setUpdateRatings()`,
  `setUpdateFields()`, `setUpdateMails()`, `setUpdateConfig()`, `setUpdateModules()`, `checkUpdateBase()`,
  `setUpdateBackup()` under names that do not collide with the core.
- `update_probe.php` lifts them from `update.php`; `UpdatePoints/Ratings/Fields/Config/Mails/SetupTest` and the code
  greps of `BackupContractTest`, `FeedTest`, `NodeServiceTest`, `NodeSyncTest`, `UploadIntegrationTest`,
  `UpdateSiteTest` follow the code.
- Verify: full phpunit, the update mode of `install_probe.php` against the new entry.
- Learnt on landing:
  - `update.php` has two stages in one file. The first runs before the core under `SETUP_FILE`, without a key and
    without a login, while the units points, ratings and fields have not all left their marks in `config/update.php`,
    and always for `op=update`: a page on the login card of the admin theme (`pages/login.html` with the fragments
    `alert`, `table`, `table-row`, `table-cells`, `inline-badge`, `post-button`) offers the run, and a POST
    `op=update` executes `setUpdateRun()`. With the three marks the core boots and `isAdmin(true)` guards the Node
    migration as before. The credentials come from `config/db.php`, 6.2 or 6.3 shape, so there is no form at all.
  - The names in `update.php`: `setUpdateFile`, `getUpdateSource`, `getUpdateCred`, `getUpdateRow`, `checkUpdateFail`,
    `checkUpdateWrite`, `setUpdateSql`, `deleteUpdateTypes`, `setUpdateRun`, `getUpdateLinks`, `setUpdatePage` beside
    the moved units; none is declared by the core, `core/admin.php` or a module.
  - A unit answers report rows as data, `[['text' => ..., 'done' => bool]]`; `checkUpdateFail()` replaced the search
    for `sl_red`. The texts of the first stage are English literals, so batch 2 takes no update text.
  - Trap 7 is closed here already: `core/classes/pdo.php` throws a `RuntimeException` under `SETUP_FILE`.
  - The preflight of a clean installation (free prefix, server version) left `checkUpdateBase()`. Batch 4 carries it
    in `setup.php`; batch 6 asserts the cases `UpdateSetupTest` dropped: a taken prefix, a free one, one that only
    starts like a taken one, one holding `_`, and a server older than 10.5.2.
  - `UpdateSetupTest::theInstallerChecksBeforeItWrites` became `theUpdateChecksBeforeItWrites`; the installer half
    (no stored password in the form, prefix and panel file refused before the connection, pending journal, marks only
    after both SQL files) is batch 6's to assert against `setup.php`. The fresh-mark grep left `UpdateRatingsTest`;
    `NodeProfileTest::onlyANewInstallationLeavesTheMark` still carries it.
  - `.gitignore` no longer names `config/setup.unlock`; no key file exists any more.
  - The full suite after the batch: 11 failures and 3 errors, all `NodeProfileTest` (installer, batches 4-6) and
    `PointOwnersTest` (batch 6).

### Batch 2 — texts

- `_SETUP_*` constants in `admin/lang/en.php` first, then the other five; reuse globals (`_OK`, `_ERROR`, `_BACK`,
  `_NEXT`…) wherever one exists; `_DELSETUP` is rewritten for a file that should have deleted itself.
- The Russian wording is already written: every title, lead, label, hint, check row and the final alerts of
  `demo/setup-09-motion.html` and the run rows of `DEMO_SETUP_RUN` in `demo/assets/demo.js`. Take them from there,
  then translate. Refusal texts come from `setup_old/lang/*.php` (`_SETUPPREFIX`, `_SETUPAFILE`, `_SETUPTAKEN`,
  `_SETUPVER`, `_SETUPJOUR`, `_EXTSETUP`, `_SERRORPERM`) under the new prefix; the lock and update texts are not taken.
- Verify: `LanguageValidationTest`, `php -l` on the six files, a grep that every constant is in all six.
- Learnt on landing:
  - 57 constants `_SETUP_*` stand after `_SESS_T` in all six files, the files stay line for line parallel.
    `setup.php` loads `lang/<code>.php` and `admin/lang/<code>.php`, because the reused globals live in both:
    `_LANGUAGE`, `_SITE`, `_SITENAME`, `_ADMIN`, `_USER`, `_PASSWORD`, `_RETYPEPASSWORD`, `_NICKNAME`, `_EMAIL`,
    `_YES`, `_NO`, `_NEXT`, `_BACK`, `_OK`, `_ERROR`, `_CATEGORIES`, `_ERROR_PASS` in `lang/`, `_DATABASE`,
    `_ADDRESS`, `_MODULES` in `admin/lang/`.
  - The stops: titles `_LANGUAGE`, `_SETUP_SERVER`, `_DATABASE`, `_SITE`, `_ADMIN`, `_SETUP_INSTALL`,
    `_SETUP_DONE`, the counter `_SETUP_STEP` (`%1$s of %2$s`), the leads `_SETUP_*_LEAD` and `_SETUP_SRV_FAIL` for a
    server that misses something. The database lead names MySQL 8.0.16 beside MariaDB 10.5.2, as the preflight does.
  - Check rows: the note `_SETUP_NEED` (`%s or newer`) serves PHP and the database server, `_SETUP_NOEXT` and
    `_SETUP_NOWRITE` are the failed notes; run rows: `_SETUP_WRITTEN`, `_SETUP_TBL_MADE`, `_SETUP_SEEDBLOCK`,
    `_CATEGORIES`, `_SETUP_SEEDFORUM`, `_SETUP_MODS_ON`, `_SETUP_ADM_MADE` or `_SETUP_ADM_USER`, `_SETUP_RENAMED`,
    `_SETUP_SELFGONE`. Table and administrator have notes of their own, the gender differs in five locales.
  - Refusals: `_SETUP_TAKEN`, `_SETUP_OLDDB`, `_SETUP_BADPREFIX`, `_SETUP_BADPANEL` (index, setup and update are
    refused), `_SETUP_JOURNAL`, `_SETUP_INSTALLED` for an installed site; `_DELSETUP` names a file that should have
    deleted itself.
  - The names of the six languages on the tiles are their own names, data of `setup.php`, not constants.
  - Until batch 4 uses them the advisory unused count of `LanguageConstantsUsageTest` carries the 57.

### Batch 3 — the admin theme carries the installer

- Page `templates/admin/pages/setup.html` on `layouts/bare.html`; one partial per stop or one partial with the stop
  as data; fragments reused: `input`, `radio`, `hidden`, `button`, `alert`, `select`, `label`.
- The visual reference is `demo/setup-09-motion.html`: its markup over the login card and its `v-*` rules are the
  source; they become `sl-setup-*` classes in `templates/admin/assets/css/theme.css`. What the face carries:
  - the login card of `layouts/bare.html` at 480px, seven segments of the road under the header, the current one
    flowing, the passed ones filled;
  - a stop: title, «шаг N из 7», lead, content, a row of buttons — back left, next right, the probe of the database
    in the middle (on a phone the probe takes its own full row above);
  - language tiles 3×2 with the flags of `images/flags/`; check rows icon · name · note that tick in one by one;
  - the run: `sl-progress-line` with running stripes of `--sl-beam-bg` and a glowing head, the task under way and
    the percent beneath it;
  - done: a seal whose ring and tick draw themselves and ten sparks of the theme tones, the panel address as an
    info alert, the self-deletion as a success alert, «Открыть сайт» and «Войти в панель»;
  - motion: the card arrives out of a blur, a light follows the pointer over the page and along the rim of the card,
    a sheen of `--sl-beam-*` crosses the logo, every stop slides in from the side it lies on, a chosen flag bobs.
- Every animation runs from the hidden state to the finished one under `prefers-reduced-motion: no-preference`, so
  without motion the card stands finished. Existing tokens only. The pointer light and the step direction need a few
  lines of script; they belong to the theme script `templates/admin/assets/js/admin-ui.js`, not to `setup.php`.
- A new component token `--sl-setup-<prop>` is declared under `components` in `tools/ui-contract.php` before it is
  used; every new class is referenced by a template, or the `classes` count of `tools/ui-audit.php` grows.
- Verify: `npm run ui:before` / `ui:after`, `npm run ui:gates`, `php tools/ui-audit.php --theme=admin`, baseline
  re-stored.

### Batch 4 — setup.php

- Bootstrap without the core; refuse an installed site (a database name in `config/db.php` and a reachable
  `_admins` row) and try to delete itself there too.
- Every function of `setup.php` has a name the core does not declare (trap 2), follows the naming rules of
  `.rules/global.md` and carries a comment block.
- Stops: language (the core cookie), server checks (PHP 8.4, mbstring, PDO MySQL, JSON, Zip, Zlib, writable
  `config/`, `storage/`, `uploads/`, protocol), database (connect, version, free prefix), site (name, address,
  panel file), administrator, run, done.
- Inputs read through one filter of its own (the core `getVar()` is not loaded), every value validated as the old
  `save()` did; the panel rename and the config writes keep the behaviour of the old installer.
- `core/classes/pdo.php` already throws under `SETUP_FILE` (trap 7, batch 1): `setup.php` catches it. `addNodeProfiles()`
  moves from `admin/index.php` to `core/admin.php` and `admin/index.php` keeps calling it from there (trap 8).
- The last request boots `core/system.php` on the new configuration, creates the administrator with the core
  helpers and `addNodeProfiles()`, and unlinks `setup.php`; a failed unlink leaves the `_DELSETUP` alert of the panel.
- Verify: an installation over real HTTP on a scratch site, the panel opens, `setup.php` is gone, the logs are clean.

### Batch 5 — the first administrator leaves admin.php

- Remove `addAdminAccount()`, the empty-table branch of `getAdminLoginForm()`, the `add_admin` op and
  `_ADMINLOGIN_NEW` with its partial rows; `addNodeProfiles()` already lives in `core/admin.php` since batch 4.
- Verify: `NodeProfileTest`, phpunit, a login on the stand.

### Batch 6 — tests

- `install_probe.php` drives the new stops; `NodeProfileTest` and `UpdateSetupTest` assert the new contract;
  `PointOwnersTest`, `CommentIsolationTest`, `UnusedCodeAuditTest` stop naming `setup/`; `StructureTest` keeps
  `setup.php` until the release decides otherwise.
- Verify: full phpunit, phpstan, php-cs-fixer check.

### Batch 7 — references and the end of this plan

- `.htaccess` (`^setup/`), `robots.txt` and `admin/modules/editor.php` (`/setup/`), `core/classes/filemanager.php`
  (`CRIT`), `README.md`, `UPGRADING.md`, `SECURITY.md`, `CONTRIBUTING.md`, `docs/ARCHITECTURE.md`, `docs/NODE.md`,
  the comments of `core/helpers.php` and `core/admin.php`.
- The permanent reference gets a section on the installer; this file is deleted with the last commit.
