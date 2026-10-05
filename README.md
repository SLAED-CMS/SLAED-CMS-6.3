# SLAED CMS 6.3

[![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-slateblue.svg)](https://www.php.net/)
[![MariaDB](https://img.shields.io/badge/MariaDB-10.5.2%2B-1F305F.svg)](https://mariadb.org/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0.16%2B-00758F.svg)](https://www.mysql.com/)
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

# 2. Create an empty database

# 3. Point the document root at public/ and open the installer in the browser
http://localhost/setup.php
```

> [!WARNING]
> Run the installer right after the upload: until it has finished, whoever opens `setup.php` first installs the site.
> At the end it deletes itself; a site that is already installed is refused.

---

## System Requirements

- **PHP:** 8.4+
- **Database:** PDO MySQL-compatible server (MySQL 8.0.16+ or MariaDB 10.5.2+), InnoDB
- **Web Server:** Apache, Nginx, IIS, or another PHP-capable web server
- **Extensions:** `composer.json` requires PDO, JSON, mbstring, GD and cURL. Fileinfo, Zip and Zlib are declared under `suggest`: the upload service falls back to its own structural validators without them. SMTP over TLS uses OpenSSL and the Sendmail transport uses `proc_open`. The installer does not go on while PDO MySQL, JSON or mbstring is missing; a missing Zip or Zlib only gets a warning row, and the archives of that kind stay off
- **Encoding:** UTF-8 / utf8mb4

> [!NOTE]
> SLAED CMS has no runtime Composer dependency. `composer.json` declares only the PHP version; PHPStan, PHPUnit and PHP-CS-Fixer live in `require-dev` and are not part of a release.

---

## Installation

### Manual Installation

1. Download or clone the repository.
2. Upload the project and point the document root of the site at its folder `public/`, see
   [Document Root and Web Server](#document-root-and-web-server).
3. Create an empty database on MariaDB 10.5.2+ or MySQL 8.0.16+.
4. Open `http://yoursite.com/setup.php` and walk its stops: language, server checks, database, site, administrator.
   The server checks show the mode of the document root the installer found.
5. Press **Install**. The installer writes the configuration, creates the tables, the first administrator and the
   content types, names the address of the panel and deletes itself.

The installer creates the tables from `storage/update/sql/table.sql` and `storage/update/sql/insert.sql`. Both carry
the placeholders `{prefix}`, `{engine}`, `{charset}` and `{collate}`, so neither can be imported by hand. A site of
an older release is not updated by this release, see [UPGRADING.md](UPGRADING.md).

### Document Root and Web Server

The browser may reach the folder `public/` and nothing else. It holds the entries (`index.php`, `admin.php`,
`setup.php`, `update.php`), `.htaccess`, `robots.txt`, `error.html`, the sitemap files, `templates/`, `plugins/` and
`sound/`. Everything else stays outside: the code in `core/`, `modules/`, `admin/`, the settings in `config/`, the
logs and database backups in `storage/`, and every uploaded file in `uploads/`. The public files of `uploads/`
(avatars, the forum, `all/` and the other open folders) are still served at `/uploads/...` by `index.php`; the files
of a content type only through their checked route.

The installer records the mode it found as `webroot` in `config/global.php`: `public` when the document root is
`public/`, `project` when it is the whole project.

**Root on `public/` (recommended, any server).** Point the document root of the domain at `<project>/public`.

- Apache and LiteSpeed need nothing more than `AllowOverride All` and `mod_rewrite`: `public/.htaccess` carries the rules.
- nginx does not read `.htaccess`. The project ships `nginx.conf.example`: copy its `server` block and adjust
  `server_name`, `root` and `fastcgi_pass`. It sets the root on `public/`, sends `/uploads/` and every missing path to
  `index.php`, refuses the PHP and the markup of the themes and routes the error pages; `tests/Unit/NginxConfigTest.php`
  keeps it in step with `public/.htaccess`.

**Shared hosting.** Pick the first variant the hosting allows:

1. *The panel lets you set the document root* (Beget, Timeweb, ISPmanager, Plesk, cPanel for addon domains and
   subdomains): upload the project, for example to `slaed/`, and set the document root of the domain to `slaed/public`.
2. *The document root is fixed* (`public_html`, `www`) *and the server is Apache or LiteSpeed*: upload the whole project
   into it. The `.htaccess` of the project sends every request into `public/`, so `/config/db.php` or `/storage/...`
   answer 404. Without `mod_rewrite` that file refuses every request: the site does not open, and nothing private does
   either. The installer reports this mode as a warning row.
3. *The document root is fixed, and its parent folder is yours* (the classic cPanel layout `/home/<user>/public_html`):
   upload the project into the parent folder and use the content of `public/` as `public_html` itself, so `core/`,
   `config/`, `storage/` and `uploads/` lie beside `public_html` and never under it. The entries find the project as the
   parent of their own folder, so the folder may carry any name.

> [!WARNING]
> A server that reads no `.htaccess` (nginx, or IIS) with the document root on the whole project serves the logs, the
> backups and the closed upload folders to anyone. On such a host only the root on `public/` is safe.

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

- Typed content through Node, forum, account, search, and other modules
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
├── public/                # The document root: the only part a browser reaches
│   ├── plugins/           # Bundled JS/editor/plugin assets
│   ├── sound/             # Bundled sound assets
│   ├── templates/         # Themes and template trees
│   ├── .htaccess          # Rewrite rules of the document root
│   ├── admin.php          # Admin entry point, runs admin/index.php
│   ├── index.php          # Frontend entry point, the light path of uploads included
│   ├── setup.php          # Installation entry point
│   └── update.php         # Data update of a 6.2 site
├── storage/               # Runtime-generated cache, logs, counters, GeoIP, sitemap, backups
├── tests/                 # PHPUnit and validation tests
├── tools/                 # Audit, gate and capture scripts run from the project root
├── uploads/               # Uploaded files, outside the document root; public folders through the light path
├── .htaccess              # Rewrites every request into public/ when the document root is the project
└── nginx.conf.example     # Server block for nginx, kept in step with public/.htaccess by a test
```

---

## Development Notes

- Current runtime code and actively modernized components coexist in the repository.
- New template work targets `core/classes/template.php`, the shared `$tpl` runtime object, and HTML files under `templates/*`.
- Current theme directories are `admin` and `lite`.
- Current module directories are `account`, `changelog`, `contact`, `forum`, `node`, `presentation`, `recommend`, `rss`, `search`, `sitemap`, `users`, and `voting`.
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
