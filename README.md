# SLAED CMS 6.3

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-slateblue.svg)](https://www.php.net/)
[![MariaDB](https://img.shields.io/badge/MariaDB-10%2B-1F305F.svg)](https://mariadb.org/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-00758F.svg)](https://www.mysql.com/)
[![License](https://img.shields.io/badge/License-GPL--3.0-green.svg)](LICENSE)
![Status](https://img.shields.io/badge/Status-Active%20Development-orange.svg)
![Migration](https://img.shields.io/badge/Migration-85%25%20Complete-purple.svg)

**Modular PHP Content Management System**

> *Last updated: March 2026*

SLAED CMS is a modular content management system with a legacy runtime, a modernized database layer, multi-language support, and an actively evolving template stack. The current codebase contains both stable legacy subsystems and newer runtime components used for ongoing modernization work.

---

## Quick Start

```bash
# 1. Clone or download the repository
git clone https://github.com/SLAED-CMS/SLAED-CMS-6.3.git

# 2. Create a database and import the base schema
mysql -u root -p your_database < setup/sql/table.sql

# 3. Review config/*.php and local overrides

# 4. Open in browser
http://localhost/slaed-cms/
```

> [!WARNING]
> Delete `setup.php` after installation.

---

## System Requirements

- **PHP:** 8.1+
- **Database:** MySQL 8.0+ or MariaDB 10+
- **Web Server:** Apache, Nginx, IIS, or another PHP-capable web server
- **Extensions:** PDO, GD, mbstring, JSON
- **Encoding:** UTF-8 / utf8mb4

> [!NOTE]
> Some development documentation and modernization work target newer PHP releases, but the current Composer requirement is `>=8.1`.

---

## Installation

### Manual Installation

1. Download or clone the repository.
2. Extract files into the web root.
3. Create a MySQL or MariaDB database.
4. Import the base schema from `setup/sql/table.sql`.
5. Review the files in `config/` and adjust local settings as needed.
6. Open `http://yoursite.com/setup.php` and complete the setup flow.
7. Delete `setup.php`.

### Permissions

Typical writable directories:

```bash
chmod -R 755 config/ storage/ uploads/
chmod 666 config/*.php
```

Actual server permissions depend on your OS, web server user, and deployment model.

---

## Testing

See [docs/TESTS.md](docs/TESTS.md) for the full guide.

Quick commands:

```bash
./vendor/bin/phpunit
./vendor/bin/phpstan analyse
./vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no --config=.php-cs-fixer.dist.php <paths>
php -l path/to/file.php
```

---

## Tech Stack

- **Backend:** PHP 8.1+
- **Database:** `Database` class in `core/classes/pdo.php` with prepared statements and `getSql*` methods
- **Legacy Template Layer:** `core/template.php`
- **Modern Template Runtime:** `core/classes/template.php`
- **Editors / JS Plugins:** CKEditor, TinyMCE, CodeMirror, jQuery, htmx, Bootstrap and other bundled plugin directories under `plugins/`
- **Content Parsing:** `filterMarkdown()` in `core/system.php`
- **Security Helpers:** `getVar()`, `getSiteToken()`, `checkSiteToken()`, `getPassHash()`, `checkPassHash()`
- **Languages:** 6 bundled locale files in `lang/`

---

## Features

### Core

- Modular frontend and admin architecture
- Multi-language support
- User groups, roles, and permissions
- Prepared-statement database layer
- Caching and logging directories under `storage/`
- Legacy and modern template runtimes available side by side during migration

### Content and Modules

- News, forum, shop, media, files, account, search, and other modules
- WYSIWYG editor integrations
- File uploads and media handling
- RSS, SEO metadata, referer and statistics modules

### Themes

Bundled themes currently present in the repository:

- `templates/admin`
- `templates/default`
- `templates/default_old`
- `templates/lite`
- `templates/simple`

`templates/simple` is the minimal modern theme structure used for current template runtime work.

---

## Project Structure

```text
slaed-cms/
├── admin/                 # Admin panel entry logic and admin modules
├── blocks/                # Block rendering
├── config/                # Runtime configuration files
├── core/                  # Core runtime
│   ├── system.php         # Main bootstrap/runtime layer
│   ├── template.php       # Legacy template layer
│   ├── security.php       # Security helpers
│   └── classes/
│       ├── pdo.php        # Database class
│       └── template.php   # Modern template runtime
├── lang/                  # Main language files
├── modules/               # Frontend modules
├── plugins/               # Bundled JS/editor/plugin assets
├── setup/                 # Installation and SQL files
├── storage/               # Cache, logs, backups, sitemap data
├── templates/             # Themes and template trees
├── tests/                 # PHPUnit and validation tests
├── uploads/               # Uploaded files
├── admin.php              # Admin entry point
├── index.php              # Frontend entry point
└── setup.php              # Installation entry point
```

---

## Development Notes

- Legacy code and modernized code coexist in the current repository.
- `core/template.php` remains the legacy template layer.
- New template work targets `core/classes/template.php`, the shared `$tpl` runtime object, and HTML files under `templates/*`.
- Public documentation aims to describe the current repository state, not a future fully completed migration.

For contribution rules and coding conventions, see [CONTRIBUTING.md](CONTRIBUTING.md).

---

## Contributing

Contributions are welcome. Start here:

- [CONTRIBUTING.md](CONTRIBUTING.md)
- [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md)
- [SECURITY.md](SECURITY.md)

---

## Upgrading

For upgrade guidance and currently confirmed migration notes, see [UPGRADING.md](UPGRADING.md).

---

## Documentation

| Document | Description |
|----------|-------------|
| [docs/TEMPLATES.md](docs/TEMPLATES.md) | Template system and theme structure |
| [docs/TEMPLATE_STATUS.md](docs/TEMPLATE_STATUS.md) | Current template runtime status |
| [docs/TESTS.md](docs/TESTS.md) | Testing and validation commands |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Contribution and coding rules |
| [SECURITY.md](SECURITY.md) | Security policy |
| [UPGRADING.md](UPGRADING.md) | Upgrade notes |
| [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md) | Community standards |

---

## Support

- **Documentation EN:** [slaed.info](https://slaed.info)
- **Documentation DE:** [slaed.de](https://slaed.de)
- **Forum:** [slaed.net/forum](https://slaed.net/index.php?name=forum)

---

## License

GNU General Public License v3.0

See [LICENSE](LICENSE) for details.

---

## Author

**Eduard Laas**

- Website: [slaed.net](https://slaed.net)
- E-Mail: info@slaed.net
- Copyright © 2005 - 2026 SLAED

---

*SLAED CMS © 2005 - 2026 Eduard Laas. Licensed under GNU GPL 3.*
