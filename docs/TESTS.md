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
- `DatabaseTest.php`
- `EditorFormatTest.php`
- `ExampleTest.php`
- `GeoipReaderTest.php`
- `InputFilterTest.php`
- `InputVarContractTest.php`
- `OauthLinkTest.php`
- `OauthTest.php`
- `PageCacheContractTest.php`
- `ParserFixturesTest.php`
- `PasswordHashTest.php`
- `StatsContractTest.php`
- `StructureTest.php`
- `ViewBridgeSmokeTest.php`

Contract tests (`GeoipReaderTest`, `InputVarContractTest`, `PageCacheContractTest`,
`StatsContractTest`) drive production code through `tests/Support/contract_probe.php`,
which boots the real core in an isolated CLI process per scenario.

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
