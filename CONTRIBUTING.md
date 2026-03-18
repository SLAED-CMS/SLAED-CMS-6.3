# Contributing to SLAED CMS

> **Contribution Guidelines for SLAED CMS 6.3**
> *Last updated: March 2026*

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
chmod 666 config/*.php storage/logs/*.log
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
- Prefer short, single-purpose names like `$filter` or `$color`
- Avoid compound names like `$filter_color` unless disambiguation is required
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
$db->getSqlQuery(
    'SELECT * FROM '.PREFIX_DB.'_users WHERE id = :id AND status = :status',
    ['id' => $id, 'status' => $active]
);

// Wrong - SQL Injection vulnerability!
$db->getSqlQuery("SELECT * FROM users WHERE id = '".$id."'");
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
- `'num'` - Integer only (`filterNum`)
- `'let'` - First letter only (1 char, UTF-8)
- `'word'` - Word/slug characters only (`filterWord`)
- `'name'` - Username (max 25 chars, safe characters)
- `'title'` - Title with `filterHtml` (linkify disabled)
- `'text'` - Text with full `filterHtml` processing (HTML filtering)
- `'field'` - Custom field data (`filterFields`)
- `'url'` - Valid URL (`filterUrl`)
- `'var'` - Alphanumeric/underscore/dash only (`filterVar`, `[a-zA-Z0-9_\-]`)
- `'bool'` - Boolean value
- `'raw'` - No filtering — returns raw value (use carefully)

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
> Every constant **must** be defined in all 6 languages: EN, FR, DE, PL, RU, UA.
>
> **Placement:**
> - Constants used in public-facing modules → `lang/*.php`
> - Constants used only in the admin panel → `admin/lang/*.php`
>
> Example: `_ANONYM` is used by front-end modules, so it belongs in `lang/*.php`.

### Config Files

#### Reserved Config Files

The following `config/` files are **reserved** and must not be used as module config files:

| File | Purpose |
|------|---------|
| `config/system.php` | System-level settings (loaded separately) |
| `config/header.php` | HTML head injection (loaded separately) |
| `config/chmod.php` | Permission settings (loaded separately) |
| `config/local.php` | Local overrides (loaded last, not merged) |

- `getConfig()` skips these files during glob merge.
- `setConfigFile()` refuses to write to them (silently ignored).

Do **not** create module config files with these names.

### Global Configuration: `$conf`

The `$conf` array is the merged global configuration loaded from all `config/*.php` files. It is available in every module via `global $conf;`.

**Common top-level keys:**

| Key | Type | Description |
|-----|------|-------------|
| `$conf['sitename']` | `string` | Site title |
| `$conf['homeurl']` | `string` | Base URL (no trailing slash) |
| `$conf['slogan']` | `string` | Site slogan / default meta description |
| `$conf['language']` | `string` | Default locale code (e.g. `'en'`) |
| `$conf['name']` | `string` | Active module name (value of `$_GET['name']`) |
| `$conf['multilingual']` | `int` | `1` if multilingual mode is active |
| `$conf['rewrite']` | `int` | `1` if clean URLs (mod_rewrite) are enabled |

**Module config** is nested under the module name:

```php
// Module-specific settings (loaded from config/{module}.php)
$conf['news']['num']        // items per page
$conf['news']['add']        // registered users may add news (1 = yes)
$conf['news']['rate']       // ratings enabled
```

**Usage in a module:**

```php
function list(): void {
    global $conf;
    $num = (int)($conf['mymodule']['num'] ?? 10);
    // ...
}
```

> [!NOTE]
> Never write directly to `$conf`. Use `setConfigFile()` to persist config changes.
> `$conf['name']` always matches the filtered `$_GET['name']` value set by the routing layer.

### Admin Module Conventions

When working on admin modules, follow these specific conventions:

#### Navigation

Admin modules use `setAdminNavi(array $p): string` — callable directly from any handler function; no per-module `navi()` wrapper is required.

```php
// In any handler function, call setAdminNavi() directly:
$cont = setAdminNavi([
    'ops'  => ['name=modules', 'name=modules&amp;op=info'],
    'tabs' => [_HOME, _INFO],
]);

// With sub-tabs and searchbox:
$cont = setAdminNavi([
    'ops'    => ['name=security', 'name=security&amp;op=block', 'name=security&amp;op=info'],
    'tabs'   => [_HOME, _BANNED, _INFO],
    'sops'   => ['', ''],
    'stabs'  => [_BANNED_IP, _BANNED_USERS],
    'sub'    => $searchboxHtml,
    'tab'    => $tab,
    'subtab' => $subtab,
    'legacy' => $legacy,
    'id'     => 'security',
]);
```

#### Global Variables

```php
// ✅ Correct - use $afile
global $afile;
setRedirect($afile.'.php?name=modules');

// ❌ Wrong - deprecated
global $admin_file;
header('Location: '.$admin_file.'.php?name=modules');
exit;
```

#### Header Redirects

Use `setRedirect()` for all redirects. It handles the correct HTTP status code automatically (302 → 303 on POST) and sanitizes the URL:

```php
// ✅ Correct — use setRedirect()
setRedirect($afile.'.php?name=modules');

// ✅ With referer fallback (for "Back" buttons)
setRedirect($afile.'.php?name=modules', true);

// ❌ Wrong — manual header/exit pattern (legacy, do not use)
header('Location: '.$afile.'.php?name=modules');
exit;

// ❌ Wrong — unnecessary &op=show
setRedirect($afile.'.php?name=modules&op=show');
```

**`setRedirect()` signature:**
```php
setRedirect(string $url, bool $refer = false, int $code = 302): never
```
- `$url` — redirect target URL
- `$refer` — when `true` and a `refer` GET/POST parameter is present, redirect to the HTTP Referer instead (same-origin only)
- `$code` — HTTP status code (301, 302, 303, 307, 308); defaults to 302; auto-upgrades to 303 on POST

#### Switch-Case Structure

Extract inline code into separate functions:

```php
// ✅ Correct
function status(): void {
    global $db, $afile, $act, $id;
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_categories SET active = :act WHERE mid = :id', ['act' => $act, 'id' => $id]);
    setRedirect($afile.'.php?name=categories');
}

switch ($op) {
    default: modules(); break;
    case 'status': status(); break;
    case 'edit': edit(); break;
}
```

#### Help Info Files

Admin modules display a contextual help tab via `getAdminInfo()`. It is called automatically by the admin core — no parameters are needed in module code.

**Signature:**

```php
getAdminInfo(): string
```

- Reads `$_GET['name']` to determine which module's info to display.
- Checks `modules/{name}/admin/info/{locale}.html` and `modules/{name}/admin/info/{locale}.md` first.
- Falls back to `admin/info/{name}/{locale}.html` and `admin/info/{name}/{locale}.md`.
- When `adminfo` is enabled in config, also renders an in-page editor form.

**Info file locations:**

| Path | Purpose |
|------|---------|
| `modules/{name}/admin/info/{locale}.html` or `.md` | Module-specific help (e.g. `modules/news/admin/info/en.html`) |
| `admin/info/{name}/{locale}.html` or `.md` | Core admin module help (e.g. `admin/info/categories/en.html`) |

> [!NOTE]
> Info files use 2-letter locale codes (`en`, `de`, `fr`, `pl`, `ru`, `uk`).
> Content is parsed with `bb_decode()`.

### Template Functions

Use the modernized template functions. `tpl_eval()`, `tpl_func()`, and `tpl_warn()` have been **removed** from 6.3.x:

```php
// ✅ Correct (6.3.x)
$cont .= setTemplateBasic('open');
$cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => $text]);
$cont .= setTemplateBasic('close');

// ❌ Removed — will cause a fatal error
$cont .= tpl_eval('open');
$cont .= tpl_warn('warn', $text, '', '', 'info');
$cont .= tpl_func('open');
```

### Content Parsing

Use `filterMarkdown()` to render Markdown content as HTML. It is self-contained in `core/system.php`.

**Signature:**

```php
filterMarkdown(string $src, string $mod = '', bool $safe = true): string
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$src` | `string` | — | Markdown source text |
| `$mod` | `string` | `''` | Reserved for mode switching (`'bb'`/`'md'`/`'mixed'`) — currently unused |
| `$safe` | `bool` | `true` | `true` = user mode (HTML escaped, URL allowlist); `false` = admin mode (raw HTML allowed) |

**Usage:**

```php
// User content — safe mode (default): HTML escaped, no javascript: URLs
echo filterMarkdown($comment['text']);

// Admin content — raw HTML blocks allowed
echo filterMarkdown($article['text'], '', false);

// Format-based switch alongside bb_decode()
echo $article['format'] === 'md'
    ? filterMarkdown($article['text'], '', false)
    : bb_decode($article['text'], $conf['name']);
```

**Supported Markdown elements** (both modes):
`# H1–H6`, `**bold**`, `*italic*`, `~~strike~~`, `==highlight==`, `` `code` ``, ` ``` `, `> blockquote`,
`- list`, `1. list`, `- [x] task`, `| table |`, `[link](url)`, `![img](src)`, `<https://auto>`

**Raw HTML blocks** (`<div>`, `<section>`, `<article>`, etc.) — only when `$safe = false`.

> [!IMPORTANT]
> Always use `safe=true` (default) for user-submitted content.
> `safe=false` is intended for admin-authored content only.
> `filterMarkdown()` contains no `eval()` and executes no PHP.

---

### Language Loading

Use `getLang()` to load a module's language file and get the active locale. `setLang()` is a bootstrap function — never call it from within a module.

**`getLang()` signature:**

```php
getLang(string $module = '', bool $admin = false): string
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$module` | `string` | `''` | Module name, `'admin'` for the admin panel, or `''` to return the active locale without loading any file |
| `$admin` | `bool` | `false` | `true` loads the admin language variant (`modules/{module}/admin/lang/`) |

**Returns:** The active locale string (e.g. `'en'`, `'de'`).

**Usage:**

```php
// Load front-end module language file — call at the top of every module
$locale = getLang('news');           // loads modules/news/lang/{locale}.php

// Load admin language variant
$locale = getLang('news', true);     // loads modules/news/admin/lang/{locale}.php

// Load admin panel base language
$locale = getLang('admin');          // loads admin/lang/{locale}.php

// Return active locale without loading any file
$locale = getLang();
```

> [!NOTE]
> `getLang()` uses a static cache — loading the same module/context/locale pair twice has no overhead.
> If the locale file is not readable, it falls back to the default language from config.

**`setLang()` signature:**

```php
setLang(): void
```

Called once per request from bootstrap (`index.php` / `admin.php`). Sets the global `$locale` from, in order: the `newlang` request parameter → the language cookie → the config default. Also loads the main `lang/{locale}.php` file.

> [!CAUTION]
> Never call `setLang()` from within a module or admin module. It is a bootstrap function and must run exactly once per request.

---

### Page Lifecycle — setHead() and setFoot()

Every front-end module follows this request lifecycle:

```
getLang() → build $cont → setHead($seo) → echo $cont → setFoot()
```

**`setHead()` signature:**

```php
setHead(array $seo = []): void
```

Outputs the HTML `<head>` section, handles session tracking, referer logging, and statistics. Must be called exactly once per request, after the content is ready.

**`$seo` array keys:**

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `'title'` | `string` | `$conf['sitename']` | Page `<title>` and OG title |
| `'ctitle'` | `string` | `''` | Sub-title appended to `<title>` when `$conf['ltitle']` is enabled |
| `'desc'` | `string` | `$conf['slogan']` | `<meta name="description">` and OG description |
| `'img'` | `string` | site logo URL | OG image URL |
| `'time'` | `string` | current time | Article publish time (ISO 8601 or MySQL datetime) |
| `'author'` | `string` | `$conf['sitename']` | OG article author |

**`setFoot()` signature:**

```php
setFoot(): void
```

Outputs the page footer template, inserts sidebar blocks, writes the page cache if enabled, flushes output buffers, and terminates the request with `exit;`. Must be called exactly once, after all content has been echoed.

**Full module page flow:**

```php
function list(): void {
    global $db, $conf;

    $locale = getLang('news');

    // ... build content ...
    $cont  = setTemplateBasic('open');
    $cont .= '<p>Content here</p>';
    $cont .= setTemplateBasic('close');

    setHead(['title' => _NEWS, 'desc' => _NEWSDESC]);
    echo $cont;
    setFoot();
}
```

> [!CAUTION]
> `setFoot()` calls `exit;` internally — do not place any code after it.
> `setHead()` and `setFoot()` are for front-end modules only. Admin modules do not call them; the admin panel manages its own page lifecycle.

---

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
   ./vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no --config=.php-cs-fixer.dist.php
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
