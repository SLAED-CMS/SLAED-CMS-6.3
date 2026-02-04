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

## Recommended Order
1. Run `php -l` on changed files to catch syntax errors fast.
2. Run `phpstan` to catch type and static issues.
3. Run `phpunit` for tests.
4. Run `php-cs-fixer` in dry-run mode on changed files.

## Notes
1. The PHP-CS-Fixer config is intentionally minimal to match SLAED rules.
2. Avoid running PHP-CS-Fixer on legacy third-party code unless explicitly required.
3. If you see a PHP version warning, ensure the runtime matches your project requirements.
