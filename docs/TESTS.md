# Testing Guide

All commands in this document must be run from the project root.

## Tooling
The repository currently ships with:
- PHPUnit
- PHPStan
- PHP-CS-Fixer
- PHP syntax checks via `php -l`
- browser audit scripts backed by Playwright and Chrome Remote Interface

Configuration files:
- `phpunit.xml`
- `phpstan.neon`
- `.php-cs-fixer.dist.php`
- `package.json`

Composer scripts currently present in `composer.json`:

- `composer test`
- `composer analyse`
- `composer quality`

NPM scripts currently present in `package.json`:

- `npm run browser:audit`
- `npm run browser:inspect`
- `npm run browser:attach`
- `npm run ui:gates`
- `npm run ui:before`
- `npm run ui:after`
- `npm run ui:hooks`

The `ui:*` scripts are the theme gates; [TEMPLATES.md](TEMPLATES.md) carries the
full table of what each one checks. `tools/hooks/pre-commit` runs the fast set
on every commit that carries a file able to move a count.

It is enabled per machine, not per repository: `core.hooksPath` lives in
`.git/config`, which a clone and a pull both leave behind. `npm install` sets it
through `postinstall`, and `npm run ui:hooks` sets it by hand. Skip a single
commit with `SLAED_SKIP_GATES=1`. The hook stops with a named reason when `php`
is not on the PATH of the shell git runs hooks in or when `vendor/` is absent -
a gate that steps aside when its tools are missing is the failure it exists to
catch.

## Installation
Install dev dependencies first:

```bash
composer install
```

If Composer is not in PATH:

```bash
php composer.phar install
```

If you use the Composer scripts instead of direct binaries, they resolve to the current repository toolchain defined in `composer.json`.

## Core Commands

### PHPUnit
```bash
vendor/bin/phpunit
```

### PHPUnit By Suite
```bash
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --testsuite Validation
```

### PHPUnit By File
```bash
vendor/bin/phpunit tests/Unit/PasswordHashTest.php
```

### PHPStan
```bash
vendor/bin/phpstan analyse
```

### PHP-CS-Fixer Dry Run
```bash
vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no --config=.php-cs-fixer.dist.php <paths>
```

### PHP-CS-Fixer Apply
```bash
vendor/bin/php-cs-fixer fix --using-cache=no --config=.php-cs-fixer.dist.php <paths>
```

### Syntax Check
```bash
php -l path/to/file.php
```

### Browser Audit
```bash
npm run browser:audit
npm run browser:inspect
npm run browser:attach
```

### Theme Independence Audits

`TemplateValidationTest` discovers installed frontend themes dynamically, validates each theme's local runtime directories and CSS files, rejects cross-theme includes/assets, and requires non-zero PHP and template reference counters. Its mixed Windows/POSIX path cases protect the reference scan from silently becoming vacuous.

`AdminCssClassUsageTest` is a theme-aware informational audit despite its legacy filename. It reports `admin` and `lite` independently and scans theme templates, theme hooks, PHP emitters, and shared JavaScript state classes. An item in its unused list is only a static signal; selector removal still requires a complete emitter audit and browser coverage.

### CSS Normalization Verification

CSS formatting must be verified per file with a parser-normalized, comment-independent token stream. Record line and comment counts, before/after SHA-256 hashes, property value changes, and removed selectors. Formatting and adjacent-rule consolidation are separate diffs: consolidation additionally compares the flattened at-rule, selector, and declaration stream and checks computed styles against captured DOM at desktop and mobile viewports.

The `admin` and `lite` reports are independent. Never compare token values, selector inventories, or computed styles across themes.

### Theme Browser Route Matrices

Admin coverage includes login, dashboard/menu grid, module list/searchbox, config forms, monitor charts, voting tables, message/error UI, and desktop/mobile responsive states. Authenticate through the real admin form before checking protected routes.

Frontend coverage includes home/main slider, news list and detail, voting page/widget, search, account forms, forum list/topic, category list, message/error UI, and desktop/mobile responsive states. Record HTTP status, console/page errors, failed resources, screenshots, and relevant DOM or computed-style assertions. Static fixture rendering alone is not a browser check.

### SEO HTTP Audit
```bash
php tools/seo-audit.php https://slaed.loc
php tools/seo-audit.php https://slaed.loc '/index.php?name=main'
```

The default audit discovers one current detail URL for each active content module from its landing page. Additional installation-specific routes may be passed as further arguments. The audit fails on HTTP errors, a missing or duplicate `H1` inside `main`, a heading level that deepens by more than one step, invalid JSON-LD, a missing schema type, an invalid canonical, an Open Graph URL mismatch, or an indexability mismatch. Each route report includes the full `main` heading sequence.

### Comment Markup Baseline
```bash
php tools/comment-baseline.php capture
php tools/comment-baseline.php verify
```

The comment contract required the rendered comment list to survive the move into the
`Comment` class unchanged. That claim is only checkable
against markup captured **before** the refactor, so `capture` must be run first
and `verify` after every step of it.

The tool picks the most commented target per module from the database, fetches
the guest view, extracts the `repcsave` region and stores it under
`storage/baseline/comments/` with a `manifest.json` of sizes and SHA-256 hashes.
Request-scoped values are normalised first — URL `token=`, the `X-CSRF-TOKEN`
inside `hx-headers`, `[[sldyn:...]]` markers and the captcha field — otherwise
the list never compares equal even to itself. On a difference the fresh capture
is written next to the baseline as `<module>.actual.html` for diffing.

All eight modules are meant to be covered: `faq`, `files`, `links`, `media`,
`news`, `pages`, `shop`, `voting`. That baseline was captured on 2026-07-28 with all
eight present, after re-preparing the two fixtures below. A `capture` that reports
fewer than eight has lost one of them again — re-prepare it and capture once
more, or the parity claim silently excludes a module.

Coverage assumes **every module is enabled**, and `config/modules.php` now has
all 50 active. An inactive module is a gap in the test stand, not a reason to
skip it — the comment engine has to keep working for all eight regardless of what
any single site switches on.

Two needed the stand prepared, and both causes are worth knowing because they
look identical from the outside:

- `shop` — product 24 carried two comments while its `acomm` mode was `0`, so the
  region was never rendered. Prepare with
  `UPDATE {prefix}_products SET acomm = 1 WHERE id = 24;`, revert with
  `UPDATE {prefix}_products SET acomm = 0 WHERE id = 24;`
- `media` — the module was **inactive**, so the view page answered 404 before
  comments were ever reached, *and* the table was empty. It is enabled in
  `config/modules.php` and stays enabled; the content is a fixture. Prepare it with
  one category, one item and two comments:

```sql
INSERT INTO {prefix}_categories (id, modul, title, intro, img, parent, status, ordern, pview, pread, ppost, preply, pedit, pdelete, pmod)
  VALUES (110, 'media', 'Демонстрация', 'Категория для проверки комментариев', 'image', 0, 1, 1, '0|0', '0|0', '0|0', '0|0', '3|0', '3|0', '3|0');
INSERT INTO {prefix}_media (id, cid, uid, name, title, intro, note, links, time, acomm, comments, ip, status)
  VALUES (1, 110, 7885, 'SLAED CMS', 'Демонстрационный материал', 'Материал существует ради проверки разметки комментариев.', '', '', '2026-01-01 12:00:00', 1, 2, '127.0.0.1', 1);
INSERT INTO {prefix}_comment (cid, modul, time, uid, name, ip, body, status) VALUES
  (1, 'media', '2026-01-02 10:00:00', 7885, 'SLAED CMS', '127.0.0.1', 'Первый комментарий к демонстрационному материалу.', 1),
  (1, 'media', '2026-01-02 11:00:00', 0, 'Гость', '127.0.0.1', 'Второй комментарий, оставленный гостем.', 1);
```

  Every other column takes its schema default. `note` and `links` are named even
  though they are empty, because both are `TEXT NOT NULL` **without** a default
  (`setup/sql/table.sql`) and this server runs `STRICT_TRANS_TABLES`, which
  refuses the row rather than inventing one. The permission values are the ones
  the existing categories of the other modules carry, because `catmids()` filters
  the view by them and the schema default is an empty string, not an open state.
  Revert the fixture rows with
  `DELETE FROM {prefix}_comment WHERE cid = 1 AND modul = 'media';`,
  `DELETE FROM {prefix}_media WHERE id = 1;`,
  `DELETE FROM {prefix}_categories WHERE id = 110;`. The module stays enabled.

After any change to `config/modules.php`, delete `config/local.php` so the
generated config cache rebuilds — a direct edit to a source config file is not
re-fingerprinted while the cache remains valid.

An empty comment region and a disabled module produce the same "no rendered
comment region" line, so check module activity first.

On an installation that already carries content in these modules, no preparation
is needed — the tool tries up to eight commented targets per module and takes the
first that renders.

Baselines are data- and host-specific, so `storage/baseline/` is ignored by git —
capture them locally against the database you are refactoring against. Logged-in
and moderator views carry session state and belong to the browser path, not to
this tool.

## PHPUnit Layout
`phpunit.xml` defines two suites:

### Unit
Directory:
- `tests/Unit`

Current unit test files include:
- `AdminCssClassUsageTest.php`
- `AdminLoginBridgeFlowTest.php`
- `AdminPageRenderFlowTest.php`
- `AdminPreviewBridgeFlowTest.php`
- `AdminSearchboxBridgeFlowTest.php`
- `BackupContractTest.php`
- `BackupIntegrationTest.php`
- `CommentIsolationTest.php`
- `CommentNotifyTest.php`
- `CommentReadTest.php`
- `CommentStateTest.php`
- `CommentTargetTest.php`
- `CommentThreadTest.php`
- `CommentTransportTest.php`
- `CommentTrustBoundaryTest.php`
- `CommentWriteTest.php`
- `DatabaseBatchTest.php`
- `DatabaseTest.php`
- `DeskKeysTest.php`
- `EditorFormatTest.php`
- `EditorRoomTest.php`
- `EditorWindowTest.php`
- `ExampleTest.php`
- `FileManagerCatalogTest.php`
- `FileManagerEditTest.php`
- `FileManagerLockTest.php`
- `FileManagerOpsTest.php`
- `FileManagerPathTest.php`
- `GeoipReaderTest.php`
- `ImageThumbTest.php`
- `InputFilterTest.php`
- `InputVarContractTest.php`
- `MailCampaignTest.php`
- `MailConfigTest.php`
- `MailDrainTest.php`
- `MailHeaderTest.php`
- `MailQueueTest.php`
- `MailSmtpTest.php`
- `MailTransportTest.php`
- `OauthLinkTest.php`
- `OauthTest.php`
- `PageCacheContractTest.php`
- `ParserFixturesTest.php`
- `PasswordHashTest.php`
- `PrivatClassTest.php`
- `PrivatMigrationTest.php`
- `SchedulerLockTest.php`
- `StatsContractTest.php`
- `StructureTest.php`
- `ThemeContractTest.php`
- `ThemeCreationTest.php`
- `UiAuditTest.php`
- `UploadContractTest.php`
- `UploadFallbackTest.php`
- `UploadFormatTest.php`
- `UploadIntegrationTest.php`
- `ViewBridgeSmokeTest.php`

Contract tests (`CommentNotifyTest`, `CommentReadTest`, `CommentStateTest`, `CommentTargetTest`,
`CommentThreadTest`, `CommentTrustBoundaryTest`, `CommentWriteTest`, `GeoipReaderTest`, `InputFilterTest`,
`InputVarContractTest`, `PageCacheContractTest`, `StatsContractTest`) drive
production code through `tests/Support/contract_probe.php`, which boots the real
core in an isolated CLI process per scenario. Prefer that route over copying an algorithm into a test:
the previous replica-based `InputFilterTest` silently drifted away from the
functions it claimed to cover.

### Validation
Directory:
- `tests/`

Excludes:
- `tests/Unit`
- `tests/bootstrap.php`

Current validation test files include:
- `BlockValidationTest.php`
- `ConfigValidationTest.php`
- `ErrorPageContractTest.php`
- `InsertValidationTest.php`
- `LanguageConstantsUsageTest.php`
- `LanguageValidationTest.php`
- `ModuleStructureTest.php`
- `PhpFileFormatTest.php`
- `TextFileEncodingTest.php`
- `SchemaUpdateValidationTest.php`
- `SecurityValidationTest.php`
- `SeoSemanticsValidationTest.php`
- `SetupFileWarningTest.php`
- `TemplateValidationTest.php`
- `UnusedCodeAuditTest.php`

## Recommended Order
For focused code work:
1. run `php -l` on changed PHP files
2. run relevant PHPUnit
3. run `phpstan analyse` when the change touches shared logic
4. run PHP-CS-Fixer dry-run on changed paths when style is relevant
5. run browser audit scripts when the change touches browser behavior, frontend
   rendering, or live UI flows

## Notes
- test names such as `ViewBridgeSmokeTest` are current repository filenames
- the current runtime target is `Template`, not `View`
- run only relevant checks, but never claim a check that was not run
