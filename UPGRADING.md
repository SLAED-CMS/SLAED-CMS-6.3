# Upgrading SLAED CMS

> **Migration Guide for SLAED CMS**
> *Last updated: April 2026*

This document describes the upgrade process using currently confirmed files and repository structure. Where the exact historical path cannot be verified from the current codebase alone, it is marked as `TODO:`.

## Table of Contents

- [Before You Upgrade](#before-you-upgrade)
- [Upgrade Files Present in the Repository](#upgrade-files-present-in-the-repository)
- [Recommended Upgrade Flow](#recommended-upgrade-flow)
- [Breaking and Important Changes](#breaking-and-important-changes)
- [Migration Checklist for Custom Code](#migration-checklist-for-custom-code)
- [Troubleshooting](#troubleshooting)

---

## Before You Upgrade

> [!CAUTION]
> Always create a database and file backup before upgrading.

### Database Backup

```bash
mysqldump -u root -p your_database > backup_$(date +%Y%m%d).sql
```

### File Backup

```bash
tar -czf slaed_backup_$(date +%Y%m%d).tar.gz /path/to/slaed/
```

### Minimum Runtime

Confirmed current baseline:

- **PHP:** 8.1+
- **Database:** PDO MySQL-compatible server (MySQL 8.0+ or MariaDB 10+)

### Review Writable Directories

Typical writable paths:

- `config/`
- `storage/`
- `uploads/`

---

## Upgrade Files Present in the Repository

The repository currently contains these SQL files under `setup/sql/`:

- `table.sql`
- `insert.sql`
- `table_update4_1.sql`
- `table_update4_2.sql`
- `table_update4_3.sql`
- `table_update5_0.sql`
- `table_update5_1.sql`
- `table_update6_0.sql`
- `table_update6_2.sql`
- `table_update6_3.sql`

> [!NOTE]
> The repository confirms these files exist. It does not, by itself, fully define every safe version-to-version upgrade path.

> [!IMPORTANT]
> `TODO:` Confirm the exact supported source-version matrix for production upgrades and whether intermediate SQL steps are required for specific legacy versions.

---

## Recommended Upgrade Flow

### 1. Prepare a Clean Copy of the Target Version

```bash
git clone https://github.com/SLAED-CMS/SLAED-CMS-6.3.git slaed-new
```

### 2. Preserve Site-Specific Data

Review and carry over environment-specific data as needed:

- `config/`
- `uploads/`
- any locally maintained templates or theme customizations
- any site-specific generated files that are not part of the repository

### 3. Import the Required SQL

Use the base schema for fresh installations:

```bash
mysql -u root -p your_database < setup/sql/table.sql
```

For upgrades, review the available `table_update*.sql` files and apply the correct path for your current version.

> [!IMPORTANT]
> `TODO:` Confirm the exact upgrade order for each legacy source version before documenting a mandatory sequence.

### 4. Review Configuration

Check the active files in `config/` and verify:

- database connection
- prefixes
- language and site settings
- module-related configuration
- local overrides in `config/local.php`, if used

### 5. Clear Runtime Cache

```bash
rm -rf storage/cache/*
```

Additional runtime-generated locations present in the repository:

- `storage/logs/`
- `storage/sitemap/`
- `storage/backup/`

### 6. Verify Entry Points

Check at minimum:

- `index.php`
- `admin.php`
- login flow
- key modules used by your installation
- `storage/logs/` for runtime errors

---

## Breaking and Important Changes

The current codebase confirms these project-level changes in 6.3:

### Database Layer

The active database class is `Database` in `core/classes/pdo.php`.

Current method family:

- `getSqlQuery()`
- `getSqlRow()`
- `getSqlRows()`
- `getSqlField()`
- `getSqlRowCount()`

Custom code using older `sql_*` methods should be reviewed.

### Input Handling

The current project pattern is to use `getVar()` instead of direct request access:

```php
$id = getVar('post', 'id', 'num');
```

### Password Hashing

Current helpers in the runtime:

- `getPassHash()`
- `checkPassHash()`

### CSRF Tokens

Current helpers:

- `getSiteToken()`
- `checkSiteToken()`

### Content Editors

The new pluggable editor layer is now active via the `Editor` class (`core/classes/editor.php`). When migrating old forms and textareas, update them to output via `Editor::getContent()` or `Editor::getCode()` rather than rendering raw textareas with hardcoded editor initializers. The available editor drivers are bundled under `plugins/editors/`.

### Content Parsing

Legacy text manipulation functions such as `filterMarkdown()` and `filterReplaceText()` have been removed. All user and administrative content formatting should be passed through the unified `Parser` class (`core/classes/parser.php`), typically accessed via its `filterContent()` method.

### Template Layer

The active file-backed template runtime is `core/classes/template.php`.

New template work should target the modern runtime and theme HTML files under `templates/`.
The modern engine supports automatic CSS and JS injection for components placed in `partials/` (e.g., `{% component 'modal' %}` auto-loads `modal.css` and `modal.js` at compile time).

When upgrading custom modules:
- Remove subdirectories from your module's `fragments/` logic (e.g. `new/`). The fragment namespace has been strictly flattened. Update `$tpl->getHtmlFrag(...)` calls accordingly.

> [!NOTE]
> The current repository snapshot does not contain an active `core/template.php` runtime file.

### Themes

Themes currently present in the repository:

- `admin`
- `default`
- `lite`
- `simple`

If your installation contains older theme directories not present in the current repository, review them manually before upgrade.

---

## Migration Checklist for Custom Code

Use this checklist when reviewing custom modules, custom admin code, or local patches.

### Security

- [ ] Replace direct request access with `getVar()` where possible
- [ ] Replace string-built SQL with prepared statements
- [ ] Review state-changing actions for CSRF protection

### Database

- [ ] Update custom DB calls to current `Database` method names
- [ ] Re-test custom queries against the current schema

### Templates

- [ ] Review custom templates against the current theme structure
- [ ] For new template work, prefer the modern `Template` runtime
- [ ] Keep HTML in theme files instead of PHP where practical

### Configuration

- [ ] Review local config overrides
- [ ] Re-check file permissions after deployment

### Runtime Validation

- [ ] Check `storage/logs/`
- [ ] Test admin login
- [ ] Test frontend entry page
- [ ] Test the modules critical to your site

---

## Troubleshooting

### White Screen / HTTP 500

Check:

- PHP error log
- `storage/logs/`
- web server logs

### Database Connection Errors

Verify:

- database credentials
- database server status
- charset and prefix settings

### Cache Issues

Clear runtime cache:

```bash
rm -rf storage/cache/*
```

### Missing Constants or Language Errors

Review:

- `lang/*.php`
- `setup/lang/*.php`
- module-specific language files

---

## Rollback

If the upgrade fails:

1. Restore the database backup.
2. Restore the file backup.
3. Clear `storage/cache/`.
4. Re-check logs before retrying.

---

## Support

- **Documentation EN:** [slaed.info](https://slaed.info)
- **Documentation DE:** [slaed.de](https://slaed.de)
- **Forum:** [slaed.net/forum](https://slaed.net/index.php?name=forum)
- **Email:** info@slaed.net

---

*SLAED CMS © 2005 - 2026 Eduard Laas. Released under MIT License.*
