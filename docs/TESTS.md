# Testing Guide

All commands in this document must be run from the project root.

## Tooling
The repository currently ships with:
- PHPUnit
- PHPStan
- PHP-CS-Fixer
- PHP syntax checks via `php -l`

Configuration files:
- `phpunit.xml`
- `phpstan.neon`
- `.php-cs-fixer.dist.php`

## Installation
Install dev dependencies first:

```bash
composer install
```

If Composer is not in PATH:

```bash
php composer.phar install
```

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

## PHPUnit Layout
`phpunit.xml` defines two suites:

### Unit
Directory:
- `tests/Unit`

Current unit test files include:
- `AdminLoginBridgeFlowTest.php`
- `AdminPageRenderFlowTest.php`
- `AdminPreviewBridgeFlowTest.php`
- `AdminSearchboxBridgeFlowTest.php`
- `ExampleTest.php`
- `InputFilterTest.php`
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

## Notes
- historical test names such as `ViewBridgeSmokeTest` still exist
- the current runtime target is `Template`, not `View`
- run only relevant checks, but never claim a check that was not run
