# Contributing to SLAED CMS

> **Contribution Guidelines for SLAED CMS 6.3**
> *Last updated: February 2026*

Thank you for your interest in contributing to SLAED CMS! This document provides guidelines and standards for contributing to the project.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [Coding Standards](#coding-standards)
- [Commit Guidelines](#commit-guidelines)
- [Pull Request Process](#pull-request-process)
- [Testing](#testing)
- [Documentation](#documentation)

---

## Code of Conduct

Please read and follow our [Code of Conduct](CODE_OF_CONDUCT.md) before contributing.

---

## Getting Started

### Prerequisites

- **PHP:** 8.4+ (8.1+ minimum supported)
- **Database:** MySQL 8.0+ or MariaDB 10+
- **Web Server:** Apache, Nginx, or IIS
- **Extensions:** PDO, MySQLi, GD, mbstring, JSON
- **Tools:** Git, Composer (optional)

### Fork and Clone

```bash
# Fork the repository on GitHub, then clone your fork
git clone https://github.com/YOUR-USERNAME/SLAED-CMS-6.3.git
cd SLAED-CMS-6.3

# Add upstream remote
git remote add upstream https://github.com/SLAED-CMS/SLAED-CMS-6.3.git
```

---

## Development Setup

### 1. Database Configuration

```bash
cp config/db.php.example config/db.php
# Edit config/db.php with your database credentials
```

### 2. Import Database Schema

```bash
mysql -u root -p your_database < setup/sql/table.sql
```

### 3. Set Permissions

```bash
chmod -R 755 config/ storage/ uploads/
chmod 666 config/*.php storage/logs/*.txt
```

### 4. Run Setup

Navigate to `http://localhost/slaed-cms/setup.php` and follow the wizard.

---

## Coding Standards

### Core Principles

1. **Fast** - Optimized queries, efficient caching
2. **Stable** - Error prevention, consistent API
3. **Effective** - Reusable code, no redundancy
4. **Productive** - Easy extensibility, clear guidelines
5. **Secure** - Protection against XSS, CSRF, SQL injection

### Function Naming

All functions **must** use one of the 8 required verbs:

| Verb | Purpose | Return Type |
|------|---------|-------------|
| `get` | Retrieve data | Array/Object/String |
| `set` | Save/set data | bool/ID |
| `add` | Create new entity | bool/ID |
| `update` | Modify existing | bool |
| `delete` | Remove entity | bool |
| `is` | Boolean check | bool |
| `check` | Validation | bool/Array |
| `filter` | Sanitization | cleaned data |

**Format:** `verbNoun` (camelCase, max 2-3 words)

```php
// Correct
function getUserById(int $id): array {}
function setConfig(string $file, array $data): bool {}
function isUserActive(int $id): bool {}
function checkPermission(string $perm): bool {}
function filterInput(string $data): string {}

// Wrong
function sanitizePath() {}  // Use filterPath()
function fetchUser() {}     // Use getUser()
```

### Variable Naming

- Short names: 4-8 characters preferred
- No camelCase for variables
- Common abbreviations: `$id`, `$db`, `$cfg`, `$tmp`, `$arr`, `$list`, `$rows`

```php
// Correct
$id = 123;
$cfg = [];
$list = [];

// Wrong
$userId = 123;         // No camelCase
$configuration = [];   // Too long
```

### Type Declarations

**Required** for all functions:

```php
function processData(int $id, string $name = ''): array {
    return ['id' => $id, 'name' => $name];
}

function saveUser(array $data): bool {
    // ...
    return true;
}

function deleteItem(int $id): void {
    // ...
}
```

### String Quotes

**Always use single quotes** for simple strings:

```php
// Correct
$text = 'Hello World';
$sql = 'SELECT * FROM users WHERE id = :id';
echo '<span class="'.$cls.'">'.$text.'</span>';

// Wrong
$text = "Hello World";  // Unnecessary double quotes
```

### String Concatenation

**No spaces around the `.` operator:**

```php
// Correct
$html = '<div class="'.$cls.'">'.$text.'</div>';
$ttl = _TITLE.$info;

// Wrong
$html = '<div class="' . $cls . '">' . $text . '</div>';
```

### SQL Queries

**Always use prepared statements with named placeholders:**

```php
// Correct - Safe
$db->sql_query(
    'SELECT * FROM '.REFIX_DB.'_users WHERE id = :id AND status = :status',
    ['id' => $id, 'status' => $active]
);

// Wrong - SQL Injection vulnerability!
$db->sql_query("SELECT * FROM users WHERE id = '".$id."'");
```

### Input Validation

**Always use `getVar()` for user input:**

```php
$id = getVar('post', 'id', 'num');
$name = getVar('post', 'name', 'name', '');
$url = getVar('post', 'url', 'url', 'https://');
$text = getVar('post', 'content', 'text', '');
```

**Available types:**
- `'num'` - Integer only
- `'name'` - Username (max 25 chars, safe characters)
- `'url'` - Valid URL
- `'text'` - Text with HTML filtering
- `'bool'` - Boolean value
- `'var'` - Raw variable (use carefully)

### Constants

**Format:** `_UPPER_CASE` with underscore prefix

```php
define('_ERR_FILE', 'File not found: %1$s');
define('_USR_ACTIVE', 'User is active');
```

**Categories:**
- `_ERR_*` - Errors
- `_SYS_*` - System
- `_USR_*` - User
- `_MOD_*` - Modules

> [!IMPORTANT]
> Every constant **must** be defined in all 6 languages: EN, FR, DE, PL, RU, UA

### Admin Module Conventions

When working on admin modules, follow these specific conventions:

#### Navigation Function

```php
// Always use navi() - not moduleNavi() or similar
function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0, string $extra = ''): string {
    $ops = ['name=modules', 'name=modules&amp;op=info'];
    $lang = [_HOME, _INFO];
    return getAdminTabs(_MODULES, 'modules.png', '', $ops, $lang, [], [], $tab, (bool)$subtab);
}
```

#### Global Variables

```php
// ✅ Correct - use $afile
global $afile;
header('Location: '.$afile.'.php?name=modules');
exit;

// ❌ Wrong - deprecated
global $admin_file;
header('Location: '.$admin_file.'.php?name=modules');
```

#### Header Redirects

Always add `exit;` after header redirects and use simplified URLs:

```php
// ✅ Correct
header('Location: '.$afile.'.php?name=modules');
exit;

// ❌ Wrong - unnecessary &op=show
header('Location: '.$afile.'.php?name=modules&op=show');
```

#### Switch-Case Structure

Extract inline code into separate functions:

```php
// ✅ Correct
function status(): void {
    global $db, $afile, $act, $id;
    $db->sql_query('UPDATE '.REFIX_DB.'_categories SET active = :act WHERE mid = :id', ['act' => $act, 'id' => $id]);
    header('Location: '.$afile.'.php?name=categories');
    exit;
}

switch ($op) {
    default: modules(); break;
    case 'status': status(); break;
    case 'edit': edit(); break;
}
```

### Template Functions

Use the modernized template functions:

```php
// ✅ New (6.3.x)
$cont .= setTemplateBasic('open');
$cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => $text]);
$cont .= setTemplateBasic('close');

// ❌ Old (deprecated)
$cont .= tpl_eval('open');
$cont .= tpl_warn('warn', $text, '', '', 'info');
$cont .= tpl_eval('close');
```

### File Structure

- **Files:** `snake_case.php`
- **Classes:** `PascalCase`
- **Constants:** `_UPPER_CASE`

### Code Formatting

- **Indentation:** 4 spaces (no tabs)
- **Line length:** Max 120 characters
- **Encoding:** UTF-8
- **Line endings:** LF (`\n`)

---

## Commit Guidelines

### Use the Commit Template

Configure git to use the project template:

```bash
git config commit.template .gitmessage
```

### Commit Message Format

```
<Type>: <Short description of changes>

<Extended description explaining what and why>

Core changes:

1. <Component description> (<filename.php>):
- <Change description>
  * <Detail>

Benefits:
- <Benefit 1>
- <Benefit 2>

Technical notes:
- <Note 1>
- <Note 2>
```

### Commit Types

| Type | Description |
|------|-------------|
| `Feature` | New functionality |
| `Fix` | Bug fix |
| `Refactor` | Code refactoring |
| `Docs` | Documentation changes |
| `Style` | Formatting, no logic change |
| `Test` | Adding/updating tests |
| `Chore` | Maintenance tasks |
| `Perf` | Performance improvements |

### Author Information

All commits should use:
- **Name:** Eduard Laas
- **Email:** info@slaed.net

---

## Pull Request Process

### Before Submitting

1. **Sync with upstream:**
   ```bash
   git fetch upstream
   git rebase upstream/master
   ```

2. **Run tests:**
   ```bash
   ./vendor/bin/phpunit
   ./vendor/bin/phpstan analyse
   ```

3. **Check coding standards:**
   ```bash
   ./vendor/bin/phpcs --standard=PSR12 src/
   ```

### PR Requirements

- [ ] Code follows SLAED coding standards
- [ ] All functions have type hints and return types
- [ ] SQL uses prepared statements with named placeholders
- [ ] User input validated with `getVar()`
- [ ] Comments written in English
- [ ] Tested on PHP 8.4+
- [ ] No breaking changes (or documented in PR)
- [ ] Updated documentation if needed

### PR Description

Include:
- **Summary:** What changes and why
- **Testing:** How you tested the changes
- **Breaking changes:** If any
- **Related issues:** Link to related issues

---

## Testing

### Running Tests

```bash
# Unit tests
./vendor/bin/phpunit

# With coverage
./vendor/bin/phpunit --coverage-html coverage/

# Static analysis
./vendor/bin/phpstan analyse --level=5
```

### Test Requirements

- Minimum 60% coverage for core modules
- All new functions should have tests
- Tests must pass on PHP 8.4+

---

## Documentation

### Code Comments

- Write comments in **English**
- Use PHPDoc for functions
- Explain "why", not "what"

```php
/**
 * Get user by ID with optional role filtering.
 *
 * @param int $id User ID
 * @param string|null $role Optional role filter
 * @return array User data or empty array if not found
 */
function getUserById(int $id, ?string $role = null): array {
    // ...
}
```

### File Headers

For new files:

```php
<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net
```

### Language Constants

When adding new text:

1. Add constant to all 6 language files
2. Use `_PREFIX_NAME` format
3. Use `%1$s`, `%2$d` for placeholders

---

## Questions?

- **Forum:** [slaed.net/forum](https://slaed.net/index.php?name=forum)
- **Documentation EN:** [slaed.info](https://slaed.info)
- **Documentation DE:** [slaed.de](https://slaed.de)
- **Email:** info@slaed.net

---

**Thank you for contributing to SLAED CMS!**

---

*SLAED CMS © 2005 - 2026 Eduard Laas. Licensed under GNU GPL 3.*
