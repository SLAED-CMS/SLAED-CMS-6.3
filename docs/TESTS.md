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
- `ExampleTest.php`
- `InputFilterTest.php`
- `ParserFixturesTest.php`
- `PasswordHashTest.php`
- `StructureTest.php`
- `ViewBridgeSmokeTest.php`

### Validation
Directory:
- `tests/`

Excludes:
- `tests/Unit`
- `tests/bootstrap.php`

Current validation test files include:
- `BlockValidationTest.php`
- `ConfigValidationTest.php`
- `InsertValidationTest.php`
- `LanguageConstantsUsageTest.php`
- `LanguageValidationTest.php`
- `ModuleStructureTest.php`
- `PhpFileFormatTest.php`
- `TextFileEncodingTest.php`
- `SchemaUpdateValidationTest.php`
- `SecurityValidationTest.php`
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
