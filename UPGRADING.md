# Upgrading SLAED CMS

This document provides instructions for upgrading SLAED CMS between versions.

## Table of Contents

- [Upgrade Paths](#upgrade-paths)
- [Before You Upgrade](#before-you-upgrade)
- [Upgrading to 6.3](#upgrading-to-63)
- [Breaking Changes](#breaking-changes)
- [Troubleshooting](#troubleshooting)

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
chmod 666 config/*.php storage/logs/*.txt
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

| Old (6.2.x) | New (6.3.x) |
|-------------|-------------|
| `tpl_eval('open')` | `setTemplateBasic('open')` |
| `tpl_eval('close')` | `setTemplateBasic('close')` |
| `tpl_warn('warn', $text)` | `setTemplateWarning('warn', ['text' => $text])` |

##### Admin Variables

| Old (6.2.x) | New (6.3.x) |
|-------------|-------------|
| `$admin_file` | `$aroute` |

```php
// Old
global $admin_file;
header('Location: '.$admin_file.'.php?name=modules');

// New
global $aroute;
header('Location: '.$aroute.'.php?name=modules');
```

#### SQL Query Changes

All SQL queries now require prepared statements:

```php
// Old (6.2.x) - INSECURE
$db->sql_query("SELECT * FROM users WHERE id = '".$id."'");

// New (6.3.x) - SECURE
$db->sql_query('SELECT * FROM '.$prefix.'_users WHERE id = :id', ['id' => $id]);
```

#### Input Validation

Direct `$_GET`/`$_POST` access is deprecated. Use `getVar()`:

```php
// Old (6.2.x)
$id = $_POST['id'];

// New (6.3.x)
$id = getVar('post', 'id', 'num');
```

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

Check `storage/logs/error.log` for details.

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

### 6.3.0 (In Development)

**Major Changes:**
- PHP 8.4 compatibility
- All SQL queries converted to prepared statements
- Input validation with `getVar()`
- Type declarations for all functions
- Module configuration moved to `config/modules.php`
- Template functions modernized

**Security:**
- 2106+ SQL injection vulnerabilities fixed
- 269+ input validation points added
- Deprecated insecure functions removed

### 6.2.0 (2017)

- Last stable release with PHP 7.x support
- Legacy SQL queries
- End of Life: No security updates

---

## Support

- **Documentation EN:** [slaed.info](https://slaed.info)
- **Documentation DE:** [slaed.de](https://slaed.de)
- **Forum:** [slaed.net/forum](https://slaed.net/index.php?name=forum)
- **Email:** info@slaed.net

---
