# Security Policy

> **SLAED CMS Security Information**
> *Last updated: January 2026*

---

## Supported Versions

| Version | Supported          | PHP Version | Status |
| ------- | ------------------ | ----------- | ------ |
| 6.3.x   | :white_check_mark: | 8.1 - 8.4   | Active Development |
| 6.2.x   | :x:                | 7.4 - 8.0   | End of Life (2017) |
| 6.1.x   | :x:                | 7.0 - 7.4   | Not Supported |
| < 6.0   | :x:                | < 7.0       | Not Supported |

> [!IMPORTANT]
> Only version 6.3.x receives security updates. We strongly recommend upgrading to the latest version.

---

## Reporting a Vulnerability

### Private Disclosure

If you discover a security vulnerability, please report it privately:

**Email:** info@slaed.net

**Subject:** `[SECURITY] Brief description of the issue`

### What to Include

1. **Description:** Clear description of the vulnerability
2. **Steps to Reproduce:** Detailed steps to reproduce the issue
3. **Impact:** Potential impact of the vulnerability
4. **Affected Versions:** Which versions are affected
5. **Proof of Concept:** If possible, include a PoC (do not exploit live systems)

### Response Timeline

| Stage | Timeline |
|-------|----------|
| Initial Response | 48 hours |
| Vulnerability Assessment | 7 days |
| Fix Development | 14-30 days |
| Security Advisory | After fix is released |

### What to Expect

- Acknowledgment of your report within 48 hours
- Regular updates on the progress
- Credit in the security advisory (if desired)
- Notification when the fix is released

---

## Security Measures in SLAED CMS 6.3

### SQL Injection Prevention

All database queries use **prepared statements** with named placeholders:

```php
// Safe - Using prepared statements
$db->sql_query(
    'SELECT * FROM '.$prefix.'_users WHERE id = :id',
    ['id' => $id]
);
```

**Statistics:**
- 2106+ SQL queries converted to prepared statements
- Named placeholders (`:id`, `:name`) for all parameters

### Input Validation

All user input is validated using the `getVar()` helper:

```php
$id = getVar('post', 'id', 'num');        // Integer only
$name = getVar('post', 'name', 'name');   // Safe characters, max 25
$url = getVar('post', 'url', 'url');      // Valid URL format
```

**Statistics:**
- 269+ user input points secured with validation

### XSS Prevention

All output is properly escaped:

```php
echo htmlspecialchars($user_input, ENT_QUOTES, 'UTF-8');
```

### CSRF Protection

- Session-based CSRF tokens for state-changing forms
- Token validation on all POST requests

### File Upload Security

- MIME type verification
- Extension whitelist
- Size limits
- Secure storage outside web root

### Authentication

- Secure password hashing
- Session management
- Login attempt limiting
- IP-based blocking

### Access Control

- Role-based permissions
- Module-level access control
- Admin panel protection

---

## Security Best Practices for Administrators

### Installation

1. **Delete `setup.php`** after installation
2. **Rename admin entry point** from default `admin.php`
3. **Restrict config directory** access via `.htaccess`

### Configuration

```php
// config/db.php - Use strong credentials
$confdb = [
    'pass' => 'strong_random_password_here',
    // ...
];
```

### Server Configuration

#### Apache (.htaccess)

```apache
# Deny access to sensitive files
<FilesMatch "\.(php|txt|md|json)$">
    Order Deny,Allow
    Deny from all
</FilesMatch>

# Allow only index.php, admin.php, setup.php
<FilesMatch "^(index|admin|setup)\.php$">
    Order Allow,Deny
    Allow from all
</FilesMatch>
```

#### Nginx

```nginx
# Deny access to config directory
location /config {
    deny all;
    return 404;
}

# Deny access to storage directory
location /storage {
    deny all;
    return 404;
}
```

### Regular Maintenance

1. **Update PHP** to the latest supported version
2. **Apply SLAED updates** promptly
3. **Monitor logs** for suspicious activity
4. **Backup regularly** (database and files)
5. **Review admin accounts** periodically

---

## Security Changelog

### Version 6.3.0 (In Development - January 2026)

**Major Security Improvements:**

- [x] All SQL queries converted to prepared statements (2106+ queries)
- [x] Input validation with `getVar()` for all user inputs (269+ points)
- [x] Type declarations for all functions
- [x] Updated to PHP 8.4 security features
- [x] Deprecated insecure functions removed (99 functions)
- [x] 1282 legacy code constructs updated

**Modules Secured:**

| Module | Status | Notes |
|--------|--------|-------|
| Admin Panel | ✅ Secured | All 27 modules protected |
| User Authentication | ✅ Secured | Session management improved |
| Forum | ✅ Secured | High-priority public module |
| Search | ✅ Secured | Previously main attack target |
| Private Messages | ✅ Secured | Privacy protection |
| File Uploads | ✅ Secured | MIME validation, size limits |
| Categories | ✅ Secured | Access control improved |

**Deprecated and Removed:**

| Old (Insecure) | New (Secure) |
|----------------|--------------|
| `tpl_eval()` with `eval()` | `setTemplateBasic()` |
| `tpl_warn()` with `eval()` | `setTemplateWarning()` |
| Direct `$_GET`/`$_POST` | `getVar()` validation |
| String concatenation in SQL | Prepared statements |

### Version 6.2.x (End of Life - 2017)

> [!WARNING]
> Version 6.2.x is no longer supported. Known vulnerabilities will not be patched.

**Known Issues (Unpatched):**
- SQL injection vulnerabilities in multiple modules
- Direct `$_GET`/`$_POST` access without validation
- Deprecated PHP functions
- `eval()` usage in template system

---

## Vulnerability Disclosure Policy

### Responsible Disclosure

We follow responsible disclosure practices:

1. **Private reporting** of vulnerabilities
2. **Coordinated disclosure** after fix is available
3. **Credit to researchers** who follow this policy

### What We Consider

- SQL Injection
- Cross-Site Scripting (XSS)
- Cross-Site Request Forgery (CSRF)
- Remote Code Execution
- Authentication Bypass
- Privilege Escalation
- Information Disclosure
- Path Traversal

### What We Don't Consider

- Denial of Service (DoS) attacks
- Spam/Social Engineering
- Issues in third-party plugins not maintained by SLAED
- Issues requiring physical access to the server

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
