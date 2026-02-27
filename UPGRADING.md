# Upgrading SLAED CMS

> **Migration Guide for SLAED CMS**
> *Last updated: February 2026*

This document provides instructions for upgrading SLAED CMS between versions.

## Table of Contents

- [Upgrade Paths](#upgrade-paths)
- [Before You Upgrade](#before-you-upgrade)
- [Upgrading to 6.3](#upgrading-to-63)
- [Breaking Changes](#breaking-changes)
- [Migration Checklist](#migration-checklist)
- [Troubleshooting](#troubleshooting)
- [Version History](#version-history)

---

## Upgrade Paths

| From Version | To Version | Supported | Notes |
|--------------|------------|-----------|-------|
| 6.2.x | 6.3.x | :white_check_mark: | Direct upgrade |
| 6.1.x | 6.3.x | :white_check_mark: | Via 6.2.x recommended |
| 6.0.x | 6.3.x | :warning: | Manual migration required |
| < 6.0 | 6.3.x | :x: | Fresh installation recommended |

---

## Before You Upgrade

> [!CAUTION]
> **Always backup before upgrading!**

### 1. Create Backups

```bash
# Database backup
mysqldump -u root -p your_database > backup_$(date +%Y%m%d).sql

# Files backup
tar -czf slaed_backup_$(date +%Y%m%d).tar.gz /path/to/slaed/
```

### 2. Check System Requirements

**SLAED CMS 6.3 requires:**

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| PHP | 8.1 | 8.4 |
| MySQL | 8.0 | 8.0+ |
| MariaDB | 10.5 | 10.5+ |

**Required PHP Extensions:**
- PDO + PDO_MySQL
- MySQLi
- GD
- mbstring
- JSON
- zip (for backups)

### 3. Check PHP Version

```bash
php -v
# Should show PHP 8.1 or higher
```

### 4. Enable Maintenance Mode

Before starting the upgrade, put your site in maintenance mode:

1. Login to admin panel
2. Go to Settings → General
3. Enable "Site Closed" option

---

## Upgrading to 6.3

### From Version 6.2.x

#### Step 1: Download SLAED 6.3

```bash
# Option 1: Git
git clone https://github.com/SLAED-CMS/SLAED-CMS-6.3.git slaed-new

# Option 2: Download ZIP
wget https://github.com/SLAED-CMS/SLAED-CMS-6.3/archive/master.zip
unzip master.zip
```

#### Step 2: Preserve Your Configuration

```bash
# Copy your existing configuration
cp your-old-slaed/config/db.php slaed-new/config/
cp your-old-slaed/config/global.php slaed-new/config/

# Copy module-specific configs
cp your-old-slaed/config/*.php slaed-new/config/
```

#### Step 3: Preserve User Data

```bash
# Copy uploads
cp -r your-old-slaed/uploads/* slaed-new/uploads/

# Copy custom templates (if any)
cp -r your-old-slaed/templates/custom/* slaed-new/templates/custom/
```

#### Step 4: Run Database Migrations

```bash
# Import upgrade SQL
mysql -u root -p your_database < slaed-new/setup/sql/table_update6_3.sql
```

#### Step 5: Update File Permissions

```bash
chmod -R 755 config/ storage/ uploads/
chmod 666 config/*.php storage/logs/*.log
```

#### Step 6: Clear Cache

```bash
rm -rf storage/cache/*
```

#### Step 7: Test the Upgrade

1. Navigate to `http://yoursite.com/admin.php`
2. Login with your admin credentials
3. Check all modules are working
4. Review error logs: `storage/logs/`

#### Step 8: Disable Maintenance Mode

1. Go to Settings → General
2. Disable "Site Closed" option

---

## Breaking Changes

### Version 6.3.0

#### Configuration Changes

##### Removed: `config/config_db.php`

The old `config_db.php` is removed. Use `config/db.php` instead:

```php
// Old (6.2.x) - config/config_db.php
$dbhost = 'localhost';
$dbuname = 'root';
// ...

// New (6.3.x) - config/db.php
$confdb = [
    'type' => 'mysqli',
    'host' => 'localhost',
    'name' => 'slaed',
    'uname' => 'root',
    'pass' => '',
    'prefix' => 'slaed',
    'charset' => 'utf8mb4',
    'collate' => 'utf8mb4_unicode_ci',
    'engine' => 'InnoDB',
];
```

##### Module Configuration

Modules now use `config/modules.php` instead of database storage:

```php
// config/modules.php
return [
    'news' => [
        'active' => true,
        'view' => true,
        'menu' => true,
        'group' => 1,
    ],
    // ...
];
```

#### Function Changes

##### Template Functions

`tpl_eval()`, `tpl_func()`, and `tpl_warn()` have been **fully removed**. Any existing calls will cause a fatal error:

| Removed (6.2.x) | Replacement (6.3.x) |
|-----------------|---------------------|
| `tpl_eval('open')` | `setTemplateBasic('open')` |
| `tpl_eval('close')` | `setTemplateBasic('close')` |
| `tpl_func('name')` | `setTemplateBasic('name')` |
| `tpl_warn('warn', $text)` | `setTemplateWarning('warn', ['text' => $text])` |

##### Admin Variables

| Old (6.2.x) | New (6.3.x) |
|-------------|-------------|
| `$admin_file` | `$afile` |

##### Admin Redirects

Inline `header() + exit;` patterns have been replaced by `setRedirect()`:

```php
// Old (6.2.x)
global $admin_file;
header('Location: '.$admin_file.'.php?name=modules');
exit;

// New (6.3.x)
global $afile;
setRedirect($afile.'.php?name=modules');
```

`setRedirect(string $url, bool $refer = false, int $code = 302): never` — automatically sanitizes the URL, selects the correct HTTP status code (upgrades 302 → 303 on POST), and terminates the script.

##### Admin Help Files

Per-module info files have been moved to per-module subdirectories:

| Old path | New path |
|----------|----------|
| `modules/news/admin/info/en.html` | `modules/news/admin/info/english.html` |
| `modules/news/admin/info/de.html` | `modules/news/admin/info/german.html` |
| `modules/news/admin/info/fr.html` | `modules/news/admin/info/french.html` |
| `modules/news/admin/info/pl.html` | `modules/news/admin/info/polish.html` |
| `modules/news/admin/info/ru.html` | `modules/news/admin/info/russian.html` |
| `modules/news/admin/info/uk.html` | `modules/news/admin/info/ukrainian.html` |

The same rename pattern applies to all modules under `modules/*/admin/info/`.

#### SQL Query Changes

All SQL queries now require prepared statements:

```php
// Old (6.2.x) - INSECURE
$db->sql_query("SELECT * FROM users WHERE id = '".$id."'");

// New (6.3.x) - SECURE
$db->sql_query('SELECT * FROM '.PREFIX_DB.'_users WHERE id = :id', ['id' => $id]);
```

#### Input Validation

Direct `$_GET`/`$_POST` access is deprecated. Use `getVar()`:

```php
// Old (6.2.x)
$id = $_POST['id'];

// New (6.3.x)
$id = getVar('post', 'id', 'num');
```

##### Removed: `config/rewrite.php`

The `config/rewrite.php` file and the `rewrite()` function have been removed.
URL rewriting behavior is controlled exclusively by `$conf['rewrite']` in `config/global.php`.

##### Removed: `$confu['anonym']`

The configurable anonymous user name has been replaced with a language constant:

| Old (6.2.x) | New (6.3.x) |
|-------------|-------------|
| `$confu['anonym']` | `_ANONYM` |

Define `_ANONYM` in all 6 `language/*.php` files. Do **not** add it to `admin/language/*.php`.

##### Protected: `setConfigFile()` reserved files

`setConfigFile()` now refuses to write to: `system.php`, `header.php`, `chmod.php`, `local.php`.
Calls with these names are silently ignored.

##### Changed: `getConfig()` skip list

`getConfig()` explicitly skips: `system.php`, `header.php`, `chmod.php`, `local.php`.
These files are loaded separately by the system and must not return config arrays.

---

## Migration Checklist

Use this checklist when upgrading custom modules or themes to SLAED CMS 6.3:

### Code Style

- [ ] Update copyright year: `© 2005 - 2026 SLAED`
- [ ] Change all `"..."` to `'...'` (single quotes)
- [ ] Change all `array(...)` to `[...]`
- [ ] Prefer short, single-purpose variable names (`$filter`, `$color`) over compound names (`$filter_color`) unless disambiguation is required
- [ ] Check indentation: 4 spaces (no tabs)
- [ ] Check line length: max 120 characters
- [ ] Remove closing PHP tag `?>`
- [ ] Remove error suppression operators `@`

### Functions

- [ ] Add type hints to all function parameters
- [ ] Add return types to all functions (`: void`, `: string`, etc.)
- [ ] Remove all `func_get_args()` usage
- [ ] Rename functions to verb+noun pattern if needed

### Security

- [ ] Replace all `isset($_GET/$_POST)` with `getVar()`
- [ ] Convert all SQL queries to prepared statements
- [ ] Test for SQL injection vulnerabilities
- [ ] Validate all user inputs

### Modernization

- [ ] Replace `tpl_eval()` / `tpl_func()` calls with `setTemplateBasic()`
- [ ] Replace `tpl_warn()` calls with `setTemplateWarning()`
- [ ] Change `http://` defaults to `https://`
- [ ] Update config includes: `include('config/config_X.php')` → `require_once CONFIG_DIR.'/X.php'`
- [ ] Use `checkPerms()` instead of `end_chmod()` for config permissions
- [ ] Rename config files: remove `config_` prefix where applicable

### Admin Modules

- [ ] Rename navigation function to `navi()`
- [ ] Replace `$admin_file` with `$afile`
- [ ] Replace `header('Location: ...') + exit;` with `setRedirect(...)`
- [ ] Remove `&op=show` from navigation URLs
- [ ] Extract inline switch-cases into separate functions
- [ ] Rename admin info files: `en.html` → `english.html`, `de.html` → `german.html`, etc.

> [!TIP]
> Refer to `.rules/refactoring-rules.md` for detailed migration patterns and examples.

---

## Troubleshooting

### Common Issues

#### Error: "Class 'sql_db' not found"

**Cause:** PDO driver not loaded.

**Solution:**
```php
// config/db.php
$confdb['type'] = 'mysqli'; // or 'pdo'
```

#### Error: "Undefined constant _CONSTANT_NAME"

**Cause:** Language constant not defined in all languages.

**Solution:** Add the constant to all 6 language files:
- `language/en.php`
- `language/de.php`
- `language/fr.php`
- `language/pl.php`
- `language/ru.php`
- `language/uk.php`

#### White Screen / 500 Error

**Cause:** PHP error with display_errors off.

**Solution:**
```php
// Temporarily add to index.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

Check `storage/logs/error_php.log` and `storage/logs/error_site.log` for details.

#### Database Connection Failed

**Cause:** Database credentials incorrect or MySQL version incompatible.

**Solution:**
1. Verify `config/db.php` credentials
2. Check MySQL is running
3. Verify charset support: `SHOW VARIABLES LIKE 'character_set%';`

#### Cache Issues After Upgrade

**Cause:** Old cache files incompatible with new code.

**Solution:**
```bash
rm -rf storage/cache/*
rm -rf config/cache/*
```

### Getting Help

If you encounter issues during upgrade:

1. Check the [Forum](https://slaed.net/index.php?name=forum)
2. Review error logs in `storage/logs/`
3. Contact support: info@slaed.net

---

## Rollback Procedure

If the upgrade fails, restore from backup:

### 1. Restore Database

```bash
mysql -u root -p your_database < backup_YYYYMMDD.sql
```

### 2. Restore Files

```bash
rm -rf /path/to/slaed/*
tar -xzf slaed_backup_YYYYMMDD.tar.gz -C /
```

### 3. Clear Cache

```bash
rm -rf storage/cache/*
```

---

## Version History

### 6.3.0 (In Development - 2025/2026)

**Status:** Active Development (~80% Complete as of February 2026)

**Major Changes:**
- PHP 8.4 compatibility (8.1+ minimum)
- All SQL queries converted to prepared statements
- Input validation with `getVar()` — raw `$_GET`/`$_POST` eliminated from `core/`
- `func_get_args()` removed from all functions — typed parameters enforced throughout
- Type declarations for all functions (parameters + return types)
- Module configuration moved to `config/modules.php`
- Template functions modernized (`setTemplateBasic()`, `setTemplateWarning()`)
- `tpl_eval()`, `tpl_func()`, `tpl_warn()` **removed** (used `eval()` internally)
- `setRedirect()` introduced — replaces inline `header() + exit;` in admin modules
- `filterMarkdown()` added — safe Markdown→HTML parser with user/admin modes
- `setHead()` enhanced — new `[headline]` and `[author]` SEO placeholders
- Admin help files renamed: 2-letter locale codes → full language names (`en.html` → `english.html`)
- Removed `core/classes/module.php` (centralized in core)
- Config file naming: removed `config_` prefix
- Language constant `_ANONYM` replaces configurable `$confu['anonym']`
- `config/rewrite.php` removed (URL rewrite controlled by `$conf['rewrite']`)
- Error log (`error_php.log`) uses NDJSON format (one JSON object per line)

**Security Improvements:**
- 2106+ SQL injection vulnerabilities fixed
- 269+ input validation points added
- 99 deprecated insecure functions removed
- 1282 legacy code constructs updated

**Modernized Admin Modules (23/23 - 100%):**
- All admin modules have been modernized with `navi()` function
- Key modules: `admins.php`, `blocks.php`, `categories.php`, `comments.php`
- `config.php`, `database.php`, `editor.php`, `fields.php`, `groups.php`
- `lang.php`, `messages.php`, `modules.php`, `security.php`, `uploads.php`

**Removed Files:**
- `config/config_db.php` → use `config/db.php`
- `config/counter/dump.txt`, `config/counter/sess.txt`
- `core/classes/module.php`

**Renamed Files:**
- `modules/news/admin/info/en.html` → `english.html`
- `modules/news/admin/info/de.html` → `german.html`
- `modules/news/admin/info/fr.html` → `french.html`
- `modules/news/admin/info/pl.html` → `polish.html`
- `modules/news/admin/info/ru.html` → `russian.html`
- `modules/news/admin/info/uk.html` → `ukrainian.html`
- `storage/logs/log.txt` → `log.log`
- `storage/logs/error_site.txt` → `error_site.log`
- `storage/logs/error_sql.txt` → `error_sql.log`
- `storage/logs/hack.txt` → `hack.log`
- `storage/logs/warn.txt` → `warn.log`

### 6.2.0 (2017)

**Status:** End of Life - No security updates

- Last stable release with PHP 7.x support
- Legacy SQL queries (string concatenation)
- Direct `$_GET`/`$_POST` access
- `eval()` in template system

> [!WARNING]
> Version 6.2.x contains known security vulnerabilities that will not be patched.
> Upgrade to 6.3.x is strongly recommended.

### 6.1.0 and Earlier

**Status:** Not Supported

- PHP 5.x/7.0 only
- Fresh installation of 6.3.x recommended
- No upgrade path available

---

## Support

- **Documentation EN:** [slaed.info](https://slaed.info)
- **Documentation DE:** [slaed.de](https://slaed.de)
- **Forum:** [slaed.net/forum](https://slaed.net/index.php?name=forum)
- **Email:** info@slaed.net

---

*SLAED CMS © 2005 - 2026 Eduard Laas. Licensed under GNU GPL 3.*
