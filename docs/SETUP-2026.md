# Setup 2026

Work plan for the new installer of SLAED CMS 8.0: one file `setup.php` in the site root, a clean installation only,
the look of the stand face `demo/setup-09-motion.html`, and the file deletes itself when the installation is done.
Updating an existing site is not the installer's business: it moves into `update.php` now and into an admin module
later, which is designed separately.

Status: planned on 2026-10-01; batches 0, 1, 2, 3 and 4 landed on 2026-10-01, batch 5 was dropped the same day, batch 6 is next. Batches run in order; update this line as they land. The last batch
moves what lasts into the permanent reference and deletes this file.

No line numbers for this tree anywhere in this document on purpose: every reference names the function, the file or
the constant it points at.

## How to run this plan

The owner starts a session with «Делай по плану docs/SETUP-2026.md». That command means exactly this:

1. Read `CLAUDE.md`, then the whole of every `.rules/*.md` the next batch touches, and take the skills whose
   description matches the batch (`/execute-test-suite` always; `/manage-theme-tokens` and `/manage-slaed-templates`
   for batch 3; `/modernize-php-code` for batch 2; `/secure-inputs-and-forms` for batch 4).
2. Find the first batch in the table below whose status is neither `landed` nor `dropped`. That batch is the whole job of the run.
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
| 3 | the admin theme carries the installer | landed 2026-10-01 |
| 4 | `setup.php` | landed 2026-10-01 |
| 5 | the first administrator leaves `admin.php` | dropped 2026-10-01 |
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
| First administrator | created by the installer itself; `addAdminAccount()` and the empty-table form stay in `admin/index.php` as the way back when the admins table has lost its last row (owner, 2026-10-01) |

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
    its last carrier, `NodeProfileTest::onlyANewInstallationLeavesTheMark`, was removed on 2026-10-01 (see batch 6).
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
- Learnt on landing:
  - One page `templates/admin/pages/setup.html` and no partial: the stop is data. Beside the layout keys of
    `layouts/bare.html` (`lang`, `mode`, `meta`, `links`, `scripts`, `adlogo`, `adalt`, `adtitle`, `license`) it reads
    `action`, `hidden` (a list for `fragments/hidden.html`), `road` (`title`, `is_done`, `is_current` per stop),
    `road_text` (`_SETUP_PATH`), `title`, `step_text`, `lead`, `is_back`, `alert_html` (alert fragments), `langs`
    (`name_attr`, `value_attr`, `label`, `flag`, `is_checked`), `rows` (`label`, `input_id`, `field_html`, `hint`,
    `hint_id`), `checks` (`turn`, `text`, `note`, `is_fail`), `is_run` with `percent` and `task`, `is_done` with
    `site_url`, `site_text`, `panel_url`, `panel_text`, and the buttons `next_text`, `is_install`, `probe_text`,
    `back_text`. A stop shows only the blocks whose key it passes; without `next_text` and `is_done` it has no button
    row, which is the run.
  - The buttons are submits `name="go"` with the values `back`, `probe`, `next`, in that order in the markup, so focus
    walks them as they stand; `back` carries `formnovalidate`. Enter in a field submits the first button of a form,
    so an unseen `.sl-setup-default` with `go=next` opens the form (out of the tab order and the accessibility tree);
    checked in Chromium and Firefox, WebKit not. `fragments/button.html` cannot print an icon, so the page writes its
    buttons itself.
  - The theme buttons were `inline-flex` without a gap, so an icon touched its label everywhere in the panel; by the
    owner's decision of 2026-10-01 every `sl-but*` carries `--sl-space-2` between icon and label. The same day the
    alerts of the admin theme left `text-align: justify` for `start`, and the striped foot of the login card grows
    under 560px instead of cutting the second line of the licence off. The lite alerts (`.sl-alert-text`) followed.
  - Every stop is its own request, so the card arrives out of the blur only on the first stop
    (`.sl-setup-road li:first-child.sl-is-current`), and the side a stop slides in from is the flag `is_back`
    (`data-sl-dir="prev"`), set by `setup.php` from the button pressed; no script for the direction.
  - The page light is the existing `body[data-sl-pointer]` of `admin-ui.js`; the rim of the card reads
    `--sl-d-rim-x` / `--sl-d-rim-y`, which `admin-ui.js` writes on the card holding `.sl-setup`. `setup.php` must put
    `templates/admin/assets/js/admin-ui.js` into `scripts`, or the card has no pointer light.
  - Hooks of the run for batch 4: `[data-sl-setup-run]` wraps it, `[data-sl-setup-bar]` is the fill (inline `width`,
    as `fragments/debug-stats.html` does), `[data-sl-setup-task]` the task, `[data-sl-setup-num]` the per cent, and the
    `progressbar` carries `aria-valuenow`. The driver posting the parts is batch 4's and lives in `admin-ui.js`, by the
    owner's decision of 2026-10-01, active only where `[data-sl-setup-run]` stands; `setup.php` prints no script.
  - Tokens: `--sl-setup-width` (480px), `--sl-setup-dur` (one beat, every motion of the card is a multiple of it),
    `--sl-setup-shadow` (the head of the run), `--sl-setup-seal-width`; components `setup` and `setup-seal` in
    `tools/ui-contract.php`, and `--sl-d-turn` staggers the check rows.
  - Reused instead of declared, by the owner's wish of 2026-10-01 for no zoo: a language tile is an `sl-icon-cell`
    (the cell gained `:has(:checked)` as its active state, a hidden radio, focus and a picture in place of a glyph);
    the spring is `--sl-alert-ease`; a ticked row lands with `sl-alert-land`, the seal draws with
    `sl-alert-flash-ring` run in reverse, its title rises with `sl-modal-in`; the sheen over the logo is the beam
    rule; the hint under a field is `.sl-admin-login-list small` for every login card. Own keyframes stay only for
    what the theme has none of: `sl-setup-arrive`, `-flow`, `-in`, `-back`, `-bob`, `-stripes`, `-spark`.
  - Form rows carry no colon (the field standard) and a `<label for>` when `input_id` is given; a language tile
    carries `lang` of its code and its name as bare text, so it keeps the body size the micro caption of a cell
    would shrink. The flag of `en` is `gb.svg`, of `uk` `ua.svg` — data of `setup.php`.
  - The full suite after the batch: 11 failures, ten in `NodeProfileTest` (installer, batches 4-6) and
    `PhpFileFormatTest::testPhpLineLength` on the new `opcache.revalidate_freq` line of `tests/Support/route_probe.php`
    from the work done ahead of batch 6. `ui:after` showed differences only on the lite pages `front` and
    `presentation`, under 0.27 % and on other viewports in each of two runs over one tree: stand noise, not the batch.

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
- Learnt on landing:
  - Two answers of the owner on 2026-10-01. An installed site is one whose `config/db.php` names a database that holds an
    `_admins` row **or does not answer at all**, so a site whose server is down is never handed to a visitor; only the
    browser that started the run (`part` ≥ 0 in its session) passes. The answers of the stops live in the PHP session
    under `{user_c}-setup` (not in hidden fields, not in cookies); the token alone lives in `storage/install.php`, a
    PHP file returning it, so a server that does not deny `storage/` shows nothing. `.gitignore` names that file, and
    `.rules/global.md` records the exception to `getSiteToken()`.
  - The protocol: every form posts `token`, `stop` and `go` (`back`, `probe`, `next`, `part`). `reach` in the session
    is the furthest stop the browser may post from; a run under way forces every request to the run stop. Install
    (`go=next` on stop 4) runs `checkSetupRun()`: grammar of prefix and panel, `db.php`, `global.php`, `security.php`
    and `update.php` writable, no `storage/backup/config/marker.json`, both SQL files split, then `getSetupProbe()`.
  - The parts: configuration (probe again, `global.php` with `language`, `homeurl`, `sitename`, the panel rename and
    `security.php` as the old `save()` did, `db.php`, `deleteSetupTypes()`), `table.sql` in groups of seven,
    every statement of `insert.sql` alone (the last one writes the four marks of `config/update.php`), and the
    administrator — ten parts on today's schema. A part is marked `busy` in the session before it runs; a request
    that meets a busy part fails the run instead of running it twice. A failed part sends the browser back to stop 4
    with the reason; Install again meets the taken prefix, so the owner drops the tables or picks another prefix.
  - The driver in `admin-ui.js` posts `go=part` until `more` is false or a reply breaks, then submits the form with
    `go=next`; the server answers the closing stop, the refusal or the run where it stands. The closing stop, not the
    administrator part, deletes `setup.php` and the token: that request is the last one `setup.php` serves.
  - Only the administrator part boots the core (`MODULE_FILE`, then `getLang('admin')`); every other request defines
    `FUNC_FILE` and loads `filemanager`, `logger`, `pdo`, `template`, `lang/<code>.php` and `admin/lang/<code>.php`.
    The password hash leaves the session before the boot, because `addLog()` of the core writes `$_SESSION` out; the
    database password leaves it after the configuration part. The admin row takes the hash from the admin stop
    (`password_hash(..., PASSWORD_BCRYPT)`, as `getPassHash()`), `lang` of the first stop, `homeurl` as `url`, and
    the core cookie `language` is set by `setCookies()` in that request (trap 3).
  - `addNodeProfiles()` now ends `core/admin.php`; `admin/index.php` still calls it from `addAdminAccount()`, which
    stays. After an installation the mark `node` is gone, so that call creates no type on a recovered site.
  - Validation of the admin stop: the nickname refuses `"'.:;/*<>&` (`analyze_name()` plus what `filterText()` would
    change), more than 25 bytes, an address `FILTER_VALIDATE_EMAIL` refuses (`_MAIL_BADMAIL`), and the passwords as the
    old form (`_NOPASS`, `_ERROR_PASS`). Every password of an administrator is hashed as typed since 2026-10-01: the
    installer, the recovery form and the admins module alike. `checkAdminLogin()` checks it as typed and falls back to
    the old form of the login (cut to 25 bytes, trimmed, `htmlspecialchars()`), so a hash made from that form still
    opens the panel. The recovery form carries a token of scope `add_admin` through `partials/auth-form.html`.
  - The language tile reuses `.sl-icon-cell`; the click handler of the icon picker in `admin-ui.js` caught it and
    called `setWindowClose()`, which only `plugins/system/slaed.js` declares. The handler now takes only cells with
    `data-sl-icon-name`.
  - Verifying on built-in servers needs what `install_probe.php` already does: the address of the site on a second
    server, since activating a Node type asks the site for `uploads/<type>/index.html`, and a router that answers 403
    for a type directory with its `.htaccess`. On one server the administrator part waits 15 s per profile and every
    profile fails with `Invalid node input: directory`.
  - The task line of the run shows the row just done. Of the 57 constants only `_SETUP_MODS_ON` and `_SETUP_SELFGONE`
    stay unused: the closing stop says both in its lead and its alert. Batch 7 decides whether they leave the six files.

### Batch 5 — the first administrator leaves admin.php

- Dropped by the owner on 2026-10-01: `addAdminAccount()`, the empty-table branch of `getAdminLoginForm()` and the
  `add_admin` op stay, because they are the way back into the panel when the admins table has lost its last row.
  The installer creates the first administrator itself all the same (batch 4); nothing of this batch was changed.

### Batch 6 — tests

- `install_probe.php` drives the new stops; `NodeProfileTest` and `UpdateSetupTest` assert the new contract;
  `PointOwnersTest`, `CommentIsolationTest`, `UnusedCodeAuditTest` stop naming `setup/`; `StructureTest` keeps
  `setup.php` until the release decides otherwise.
- Verify: full phpunit, phpstan, php-cs-fixer check.
- The recovery form of `admin.php` (`op=add_admin`) refuses a POST without the token of scope `add_admin` since
  2026-10-01 (`_TOKENMISS`); a probe that still walks it reads `name="token"` from the form first. The panel login
  checks the password as typed, so a probe password longer than 25 bytes or with `&` now logs in as it was set.
- Done ahead of the batch on 2026-10-01:
  - `PointOwnersTest` no longer walks `setup/`; `CommentIsolationTest` and `UnusedCodeAuditTest` still name it.
  - `NodeProfileTest` lost what only the old installer had: `onlyANewInstallationLeavesTheMark` (a grep of the
    update branches of `setup/index.php`), the `unlock` row of `aCleanInstallationCreatesTheTenTypes`, and the `step`
    and `code` rows of `theUnlockedInstallerRefusesBeforeItWrites`, now `theInstallerRefusesBeforeItWrites`. Its
    other rows stay the contract `setup.php` must meet: no stored password in the form, a taken prefix, a prefix or
    panel name outside their grammar, a panel named after another root entry, a wrong password, a pending journal, an
    unwritable `config/security.php`, untouched permissions of `db.php` and `global.php`, no mark after a failed data
    file, and only the refused connections in the logs. The rows `same` and `ddl` still name the key in their
    messages; their tuples come from `install_probe.php` and change with it.
  - `route_probe.php` starts its server with `opcache.revalidate_freq=0`: the probe rewrites the scratch
    configuration between requests, and a config file cached for two more seconds failed `NodeGuardTest` (language
    of the forum breadcrumb) and `NodeIntegrityTest` (limit of favorites). `install_probe.php` rewrites config files
    too; its server needs the same flag when the batch rebuilds it.

### Batch 7 — references and the end of this plan

- `.htaccess` (`^setup/`), `robots.txt` and `admin/modules/editor.php` (`/setup/`), `core/classes/filemanager.php`
  (`CRIT`), `README.md`, `UPGRADING.md`, `SECURITY.md`, `CONTRIBUTING.md`, `docs/ARCHITECTURE.md`, `docs/NODE.md`,
  the comments of `core/helpers.php` and `core/admin.php`.
- The permanent reference gets a section on the installer; this file is deleted with the last commit.
