# Testing Guide

This document explains how to run tests and checks for SLAED CMS. It is written for beginners and lists every tool,
its purpose, configuration files, and the exact commands to use.

**All commands must be executed from the project root.**

## When To Run
1. After about 100 changed lines (roughly 100 diff lines).
2. After refactoring.
3. After changes in critical areas: security, authentication, SQL, templates, file uploads.
4. Before merging into `master`.

## Prerequisites
1. PHP 8.4 (project supports 8.1+).
2. Composer dependencies installed.

Windows (PowerShell):
`composer install`

If Composer is not in PATH:
`php composer.phar install`

Linux or macOS:
`composer install`

## Tools, Components, And Configuration Files
1. PHPUnit
Used for unit and integration tests.
Config file: `phpunit.xml`
Tests live in: `tests/`
Binary: `vendor/bin/phpunit`

2. PHPStan
Used for static analysis.
Config file: `phpstan.neon`
Bootstrap: `phpstan-bootstrap.php`
Stubs: `phpstan-stubs.php`
Binary: `vendor/bin/phpstan`

3. PHP-CS-Fixer
Used for code style checks and formatting.
Config file: `.php-cs-fixer.dist.php`
This config follows SLAED rules (single quotes, short arrays, no spaces around `.`).
Use the same naming convention in patches: prefer short, single-purpose variables (`$filter`, `$color`) over compound names (`$filter_color`) unless disambiguation is required.
Legacy file excluded by config: `plugins/filemanager/uploader/jupload.php`
Binary: `vendor/bin/php-cs-fixer`

4. PHP Syntax Check
Built-in PHP linter for single files.
Binary: `php`

## Commands

### Windows (PowerShell)
1. Run all PHPUnit tests:
`.\vendor\bin\phpunit`

2. Run PHPStan:
`.\vendor\bin\phpstan analyse`

3. Check a single file syntax:
`php -l path\to\file.php`

4. PHP-CS-Fixer dry-run (no changes, shows diff):
`.\vendor\bin\php-cs-fixer fix --dry-run --diff --using-cache=no --config=.php-cs-fixer.dist.php <paths>`

5. PHP-CS-Fixer apply changes:
`.\vendor\bin\php-cs-fixer fix --using-cache=no --config=.php-cs-fixer.dist.php <paths>`

### Linux or macOS
1. Run all PHPUnit tests:
`./vendor/bin/phpunit`

2. Run PHPStan:
`./vendor/bin/phpstan analyse`

3. Check a single file syntax:
`php -l path/to/file.php`

4. PHP-CS-Fixer dry-run (no changes, shows diff):
`./vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no --config=.php-cs-fixer.dist.php <paths>`

5. PHP-CS-Fixer apply changes:
`./vendor/bin/php-cs-fixer fix --using-cache=no --config=.php-cs-fixer.dist.php <paths>`

## Test Suites

PHPUnit is configured with two test suites in `phpunit.xml`:

### Unit (`tests/Unit/`)

Isolated tests for core functions. No database or HTTP required.

| File | Tests | Purpose |
|------|-------|---------|
| `ExampleTest.php` | 1 | Smoke test — verifies PHPUnit works |
| `StructureTest.php` | 3 | Project directory structure validation |
| `TemplateTest.php` | 3 | `setTemplateBasic()`, `setTemplateWarning()`, `getTemplateVars()` |
| `TemplateIfTest.php` | 12 | `setTemplateIf()` conditional logic: true/false, else, nesting, coercion |
| `PasswordHashTest.php` | 11 | `md5_salt()` algorithm + bcrypt readiness for password migration |
| `InputFilterTest.php` | 19 | `filterNum`, `filterWord`, `filterVar`, `filterText`, `filterUrl`, `filterHtml` |

### Validation (`tests/` excluding `Unit/`)

Static analysis tests that scan all project PHP files for patterns.

| File | Tests | Purpose |
|------|-------|---------|
| `SecurityValidationTest.php` | 8 | Detects raw superglobals in SQL, eval with vars, shell_exec with user input |
| `InsertValidationTest.php` | — | Validates INSERT/UPDATE query structure |
| `ConfigValidationTest.php` | — | Checks config file format consistency |
| `BlockValidationTest.php` | — | Validates block file structure |
| `TemplateValidationTest.php` | — | Checks template HTML files |
| `SetupFileWarningTest.php` | — | Verifies setup file access guards |
| `LanguageValidationTest.php` | — | Checks language file completeness |
| `LanguageConstantsUsageTest.php` | — | Verifies language constant usage across codebase |
| `PhpFileFormatTest.php` | — | PHP file format (BOM, tags, encoding) |
| `ModuleStructureTest.php` | — | Module directory structure validation |
| `SchemaUpdateValidationTest.php` | — | DB schema migration file checks |
| `UnusedCodeAuditTest.php` | — | Detects unused functions and dead code |

### Running a Single Suite

```
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --testsuite Validation
```

### Running a Single Test File

```
vendor/bin/phpunit tests/Unit/PasswordHashTest.php
```

## Recommended Order
1. Run `php -l` on changed files to catch syntax errors fast.
2. Run `phpstan` to catch type and static issues.
3. Run `phpunit` for tests.
4. Run `php-cs-fixer` in dry-run mode on changed files.

## Notes
1. The PHP-CS-Fixer config is intentionally minimal to match SLAED rules.
2. Avoid running PHP-CS-Fixer on legacy third-party code unless explicitly required.
3. If you see a PHP version warning, ensure the runtime matches your project requirements.
