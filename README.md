# SLAED CMS 6.3

[![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-slateblue.svg)](https://www.php.net/)
[![MariaDB](https://img.shields.io/badge/MariaDB-10%2B-1F305F.svg)](https://mariadb.org/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-00758F.svg)](https://www.mysql.com/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Status](https://img.shields.io/badge/Status-Active_Development-orange.svg)](#)
[![Migration](https://img.shields.io/badge/Migration-90%25_Complete-purple.svg)](#)
[![Security](https://img.shields.io/badge/Security-90%2F100-brightgreen.svg)](SECURITY.md)

**Modular PHP Content Management System**

SLAED CMS is a modular content management system with a current PHP runtime, a PDO-backed database layer, multi-language support, and an actively evolving template stack.

The repository entrypoints and active runtime files currently include:

- `index.php` for frontend routing
- `admin.php` for the admin entry runtime
- `setup.php` for installation
- `core/system.php` as the main frontend bootstrap
- `core/admin.php` as the shared admin runtime helper layer
- `core/classes/pdo.php` as the database wrapper
- `core/classes/template.php` as the active template runtime

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

- **PHP:** 8.4+
- **Database:** PDO MySQL-compatible server (MySQL 8.0+ or MariaDB 10+)
- **Web Server:** Apache, Nginx, IIS, or another PHP-capable web server
- **Extensions:** `composer.json` requires PDO, JSON, mbstring, GD and cURL. Fileinfo, Zip and Zlib are declared under `suggest`: the upload service falls back to its own structural validators without them. SMTP over TLS uses OpenSSL and the Sendmail transport uses `proc_open`
- **Encoding:** UTF-8 / utf8mb4

> [!NOTE]
> SLAED CMS has no runtime Composer dependency. `composer.json` declares only the PHP version; PHPStan, PHPUnit and PHP-CS-Fixer live in `require-dev` and are not part of a release.

---

## Installation

### Manual Installation

1. Download or clone the repository.
2. Extract files into the web root.
3. Create a database for the installation.
4. Import the base schema from `setup/sql/table.sql`.
5. Review the files in `config/` and adjust local settings as needed.
6. Open `http://yoursite.com/setup.php` and complete the setup flow.
7. Delete `setup.php`.

Current SQL files present in `setup/sql/`:

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
composer test
composer analyse
composer quality
```

---

## Tech Stack

- **Backend:** PHP 8.4+
- **Database:** `Database` class in `core/classes/pdo.php` with prepared statements and `getSql*` methods
- **Template Runtime:** `core/classes/template.php`
- **Editors / JS Plugins:** `Editor` class in `core/classes/editor.php`, pluggable editor system under `plugins/editors/` (bundled drivers: ckeditor, codemirror, plain, tinymce, toastui); additional plugins: altcha, highlightjs, htmx, tablesort, system
- **Content Parsing:** `Parser` class in `core/classes/parser.php`
- **Security Helpers:** `getVar()`, `getSiteToken()`, `checkSiteToken()`, `getPassHash()`, `checkPassHash()`
- **Languages:** 6 bundled locale files in `lang/`

---

## Features

### Core

- Modular frontend and admin architecture
- Multi-language support
- User groups, roles, and permissions
- Prepared-statement database layer
- Runtime storage directories under `storage/`, including cache, logs, captcha,
  counter, GeoIP, sitemap, and backup data
- Central frontend head assembly through `setHead()` and final page rendering through `setFoot()`
- Runtime-generated sitemap data under `storage/sitemap/`

### Content and Modules

- News, forum, shop, media, files, account, search, and other modules
- WYSIWYG editor integrations
- File uploads and media handling
- RSS, SEO metadata, referer and statistics modules

### Themes

Bundled themes currently present in the repository:

- `templates/admin`
- `templates/lite`

`templates/lite` is the bundled frontend theme in the current repository. Both themes carry a local copy of Bootstrap Icons under `assets/vendor/bootstrap-icons/` — the icon stylesheet and its WOFF2 font, nothing else of Bootstrap. Icons are rendered through the theme's `fragments/bootstrap-icon.html`.

### Routing and Entry Flow

Frontend requests are routed from `index.php` by `go`, `name`, `op`, and optional `file` parameters. Standard module requests resolve to `modules/<name>/<file>.php`, while special direct flows include RSS, OpenSearch, XSL, generated CSS, generated JavaScript, and numeric helper endpoints.

Admin requests enter through `admin.php`, which loads `admin/index.php` and then resolves admin handlers from `admin/modules/*.php` and `modules/*/admin/`.

### SEO and Head Assembly

Frontend SEO data is assembled centrally in `core/system.php` through `setHead()`.

Confirmed current behavior:

- canonical URLs are built centrally from normalized route parameters
- `setHead(['canon' => '...'])` overrides the automatic canonical URL
- `setHead(['robots' => 'noindex, follow'])` overrides the default robots meta value
- Open Graph and schema URL fields are built from the same central URL logic

---

## Project Structure

```text
slaed-cms/
├── admin/                 # Admin panel entry logic and admin modules
├── blocks/                # Block rendering
├── config/                # Runtime configuration files
├── core/                  # Core runtime
│   ├── system.php         # Main bootstrap/runtime layer
│   ├── security.php       # Security helpers
│   └── classes/
│       ├── editor.php     # Pluggable editor system
│       ├── parser.php     # Markdown and BBCode parser
│       ├── pdo.php        # Database class
│       └── template.php   # Modern template runtime
├── docs/                  # Architectural documentation and guidelines
├── lang/                  # Main language files
├── modules/               # Frontend modules
├── plugins/               # Bundled JS/editor/plugin assets
├── setup/                 # Installation and SQL files
├── sound/                 # Bundled sound assets
├── storage/               # Runtime-generated cache, logs, counters, GeoIP, sitemap, backups
├── templates/             # Themes and template trees
├── tests/                 # PHPUnit and validation tests
├── tools/                 # Audit, gate and capture scripts run from the project root
├── uploads/               # Uploaded files
├── admin.php              # Admin entry point
├── index.php              # Frontend entry point
└── setup.php              # Installation entry point
```

---

## Development Notes

- Current runtime code and actively modernized components coexist in the repository.
- New template work targets `core/classes/template.php`, the shared `$tpl` runtime object, and HTML files under `templates/*`.
- Current theme directories are `admin` and `lite`.
- Current module directories are `account`, `auto_links`, `changelog`, `clients`, `contact`, `content`, `faq`, `files`, `forum`, `help`, `jokes`, `links`, `main`, `media`, `money`, `news`, `order`, `pages`, `recommend`, `rss`, `search`, `shop`, `sitemap`, `users`, `voting`, and `whois`.
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
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Current runtime architecture and request flow map |
| [docs/TEMPLATES.md](docs/TEMPLATES.md) | Template system, theme structure, theme contract and gates |
| [docs/WINDOW.md](docs/WINDOW.md) | Window canon: the one structure every dialog is built from |
| [docs/TESTS.md](docs/TESTS.md) | Testing and validation commands |
| [docs/PRINCIPLES.md](docs/PRINCIPLES.md) | Engineering principles |
| [docs/PERFORMANCE.md](docs/PERFORMANCE.md) | Performance architecture and optimization priorities |
| [docs/PLUGINS.md](docs/PLUGINS.md) | Plugin architecture design note |
| [docs/EDITORS.md](docs/EDITORS.md) | Pluggable Editor and Plugin system architecture |
| [docs/PARSER.md](docs/PARSER.md) | Content parsing and Markdown/BBCode architecture |
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

MIT License

See [LICENSE](LICENSE) for details.

---

## Author

**Eduard Laas**

- Website: [slaed.net](https://slaed.net)
- E-Mail: info@slaed.net
- Copyright © 2005 - 2026 SLAED

---

*SLAED CMS © 2005 - 2026 Eduard Laas. Released under MIT License.*
