# Security Policy

> **SLAED CMS Security Information**
> *Last updated: March 2026*

---

## Supported Versions

| Version | Supported | PHP Version | Status |
| ------- | --------- | ----------- | ------ |
| 6.3.x   | Yes       | 8.1+        | Active development |
| 6.2.x   | `TODO:`   | Legacy      | `TODO:` Confirm from maintained branches |
| 6.1.x   | `TODO:`   | Legacy      | `TODO:` Confirm from maintained branches |
| < 6.0   | `TODO:`   | Legacy      | `TODO:` Confirm from maintained branches |

Only version 6.3.x should be considered for security fixes.

> [!IMPORTANT]
> The current repository documents the `6.3` code line. It does not provide enough evidence to publish a reliable support matrix for older branches without additional branch-level verification.

---

## Reporting a Vulnerability

Please report vulnerabilities privately:

- **Email:** info@slaed.net
- **Suggested subject:** `[SECURITY] Brief description`

Include:

1. Clear description of the issue
2. Steps to reproduce
3. Affected versions or branch information
4. Security impact
5. Proof of concept, if safe to share

> [!IMPORTANT]
> Do not disclose unpatched vulnerabilities publicly before a fix is available.

### Response Expectations

Reports are reviewed on a best-effort basis. This document does not guarantee a fixed response time.

---

## Security Measures in the Current Codebase

### SQL Injection Prevention

Database access goes through the `Database` class and project SQL helpers such as:

```php
$db->getSqlQuery(
    'SELECT id, name FROM '.PREFIX_DB.'_users WHERE id = :id',
    ['id' => $id]
);
```

### Input Validation

User input should be filtered through `getVar()`:

```php
$id = getVar('post', 'id', 'num');
$name = getVar('post', 'name', 'name');
$url = getVar('post', 'url', 'url');
```

### CSRF Protection

The codebase includes token helpers:

- `getSiteToken()`
- `checkSiteToken()`

These should be used for state-changing forms and handlers.

### Authentication

Password handling helpers present in the current runtime:

- `getPassHash()`
- `checkPassHash()`

### Logging

Runtime logs are stored under `storage/logs/`.

The repository also contains runtime-generated data under:

- `storage/cache/`
- `storage/sitemap/`
- `storage/backup/`

### Access Control

The codebase contains dedicated admin and module access checks, including patterns such as:

- `isAdmin()`
- `isAdmin(true)`
- `is_admin_modul()`
- `is_moder()`

Module availability and visibility are also influenced by runtime config and database-driven module state.

---

## Security Best Practices for Administrators

### Installation

1. Delete `setup.php` after installation.
2. Review access to `config/` and `storage/` on the web server.
3. Use strong database and administrator credentials.

### Configuration

Review the active configuration files under `config/` and keep secrets out of public access.

Relevant runtime files present in the repository include:

- `config/db.php`
- `config/global.php`
- `config/security.php`
- `config/modules.php`
- `config/scheduler.php`

### Server Hardening

Protect sensitive directories such as:

- `config/`
- `storage/`
- log files
- backup files

The exact web server configuration depends on Apache, Nginx, IIS, or your hosting platform.

### Maintenance

1. Keep PHP updated within the supported range.
2. Apply project updates.
3. Monitor `storage/logs/`.
4. Review administrator accounts and permissions.
5. Keep regular database and file backups.

---

## What We Consider Security Issues

Examples:

- SQL injection
- XSS
- CSRF bypass
- Remote code execution
- Authentication bypass
- Privilege escalation
- Sensitive data disclosure
- Path traversal

Examples usually not treated as security issues in this policy:

- social engineering
- spam
- issues in external third-party software not maintained in this repository

---

## Security Notes for Contributors

When changing code:

- use prepared statements
- validate request input
- protect state-changing actions with CSRF tokens where applicable
- avoid adding raw HTML or SQL paths without a clear reason
- prefer existing security helpers over ad hoc checks
- review `storage/logs/` after changing state-changing admin flows when practical

See also [CONTRIBUTING.md](CONTRIBUTING.md).

---

## Security Resources

- **OWASP Top 10:** https://owasp.org/Top10/
- **PHP Security:** https://www.php.net/manual/en/security.php
- **MySQL Security:** https://dev.mysql.com/doc/refman/8.0/en/security.html

---

## Contact

- **Project Lead:** Eduard Laas
- **Email:** info@slaed.net
- **Website:** [slaed.net](https://slaed.net)

---

*SLAED CMS © 2005 - 2026 Eduard Laas. Licensed under GNU GPL 3.*
