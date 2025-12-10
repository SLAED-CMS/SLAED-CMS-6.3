# SLAED CMS 6.3

**Modern Content Management System**

## System Requirements

- **PHP:** 8.4+
- **Database:** MySQL 8.0+ or MariaDB 10+
- **Web Server:** Apache, Ngnix, IIS
- **Encoding:** UTF-8

## Tech Stack

- PHP 8.4 with strict types
- PDO with prepared statements
- jQuery + jQuery UI
- CKEditor, TinyMCE, CodeMirror
- Multi-language support (6 languages)

## Features

- 27+ functional modules
- Multi-language (EN, FR, DE, PL, RU, UA)
- Security (XSS, CSRF, SQL injection protection)
- Caching system (pages, blocks, CSS, JS)
- SEO optimization (sitemap, RSS, clean URLs)
- User management with groups and permissions
- Forum, shop, media gallery, file manager
- Responsive admin panel

## Development

### Coding Standards

**Key principles:**
- **Fast** - optimized queries, caching
- **Stable** - error prevention, consistent API
- **Effective** - reusable code, no redundancy
- **Productive** - easy extensibility
- **Secure** - XSS, CSRF, SQL injection protection

**Naming conventions:**
- Functions: `verbNoun()` - camelCase
- Variables: lowercase, 4-8 chars (no camelCase)
- Constants: `_UPPER_CASE` with `_` prefix
- Files: snake_case.php
- Classes: PascalCase

### Project Structure

```
.
├── core/              # System core
├── modules/           # Functional modules
├── templates/         # Themes
├── plugins/           # JavaScript libraries
├── config/            # Configuration files
├── blocks/            # UI blocks
├── admin/             # Admin panel
├── language/          # Language files
├── storage/           # Cache, logs, backups
└── uploads/           # User uploads
```

## License

GNU GPL 3

## Author

Eduard Laas
Website: https://slaed.net
Copyright © 2005 - 2026 SLAED
