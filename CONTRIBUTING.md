# Contributing to SLAED CMS

> **Contribution Guidelines for SLAED CMS 6.3**
> *Last updated: April 2026*

Thank you for your interest in contributing to SLAED CMS. This document describes the current contribution workflow, coding conventions, and project-specific rules that contributors should follow.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [Coding Standards](#coding-standards)
- [Template Runtime Guidance](#template-runtime-guidance)
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

- **PHP:** 8.1+
- **Database:** PDO MySQL-compatible server (MySQL 8.0+ or MariaDB 10+)
- **Web Server:** Apache, Nginx, or IIS
- **Extensions:** PDO and JSON are required by the current runtime; image-related flows use GD functions
- **Tools:** Git, Composer

### Fork and Clone

```bash
git clone https://github.com/YOUR-USERNAME/SLAED-CMS-6.3.git
cd SLAED-CMS-6.3

git remote add upstream https://github.com/SLAED-CMS/SLAED-CMS-6.3.git
```

---

## Development Setup

### 1. Database

Create a database and import the base schema:

```bash
mysql -u root -p your_database < setup/sql/table.sql
```

### 2. Configuration

Review the files in `config/` and adjust local settings as needed for your environment.

> [!NOTE]
> The repository does not ship a `config/db.php.example` file. Configure the real files used by your local installation.

### 3. Writable Directories

Typical writable directories:

```bash
chmod -R 755 config/ storage/ uploads/
chmod 666 config/*.php
```

### 4. Setup

Run `setup.php` in the browser for a local installation if needed, then delete it after installation.

---

## Coding Standards

### Source of Truth

Project rules live primarily in `.rules/`. When this document and `.rules/` overlap, follow `.rules/`.

Primary rule files currently used by the repository:

- `.rules/global.md`
- `.rules/constants.md`
- `.rules/architecture.md`
- `.rules/git.md`
- `.rules/report.md`

### Core Principles

1. **Fast** - Avoid wasteful SQL, I/O, and repeated rendering work.
2. **Stable** - Prefer predictable behavior and small, safe changes.
3. **Effective** - Reuse real project patterns instead of inventing new ones.
4. **Productive** - Keep modules and helpers understandable.
5. **Secure** - Validate input, use prepared statements, and protect state-changing flows.

### Function Naming

Project functions use `verbNoun` naming with the approved verb set:

| Verb | Purpose |
|------|---------|
| `get` | Retrieve data |
| `set` | Save or assign |
| `add` | Create |
| `update` | Modify |
| `delete` | Remove |
| `is` | Boolean check |
| `check` | Validation |
| `filter` | Sanitization or normalization |

```php
function getUser(int $id): array {}
function setConfigFile(string $file, array $data): bool {}
function addMail(string $mail, string $name, string $subj, string $text): void {}
function isAdmin(): bool {}
function checkSiteToken(string $name = 'token'): bool {}
function filterText(string|array $text, int $save = 0): string|array {}
```

Additional enforced constraints from `.rules/global.md`:

- function names must use `camelCase`
- function names must use letters only
- function names must not contain `_`
- function names must not contain digits
- function names must be 6-24 characters long
- function names must follow verb + noun

Template helper functions in `core/helpers.php` follow the current project naming split:

- shared template helpers: `getTpl...()`
- admin-scoped template helpers: `getTplAdmin...()`

### Variable Naming

- Prefer short variable names.
- Use lowercase variable names in the existing project style.
- Avoid unnecessary compound names.

Current repository rules are stricter than the examples in this section:

- variable names must be lowercase only
- variable names must use letters only
- variable names must not use digits
- variable names must be 2-8 characters long

```php
$id = 0;
$cfg = [];
$list = [];
$text = '';
```

### Strings and Arrays

- Prefer single quotes for plain strings.
- Use short array syntax `[]`.
- Keep string concatenation consistent with the surrounding file style.

```php
$text = 'Hello';
$list = ['a', 'b'];
$html = '<div class="'.$cls.'">'.$text.'</div>';
```

### SQL

Use prepared statements with named placeholders through `Database` methods:

```php
$db->getSqlQuery(
    'SELECT id, name FROM '.PREFIX_DB.'_users WHERE id = :id',
    ['id' => $id]
);
```

Never concatenate user-controlled input into SQL strings.

### Input Validation

Use `getVar()` instead of raw `$_GET`, `$_POST`, or `$_REQUEST` access:

```php
$id = getVar('post', 'id', 'num');
$name = getVar('post', 'name', 'name', '');
$url = getVar('post', 'url', 'url', 'https://');
$text = getVar('post', 'text', 'text', '');
```

Available project types include: `num`, `let`, `word`, `name`, `title`, `text`, `field`, `url`, `var`, `bool`, `raw`.

### Constants

Use `_UPPER_CASE` constants:

```php
define('_ERR_FILE', 'File not found: %1$s');
define('_USR_ACTIVE', 'User is active');
```

If a new user-facing constant is required, update all bundled locales where that constant is expected.

### Config Files

Reserved files in `config/`:

- `system.php`
- `header.php`
- `chmod.php`
- `local.php`

Do not use these names for module configuration files.

### Admin Module Conventions

- Use `$afile` for admin entry routing.
- Use `setRedirect()` for redirects.
- Use `setAdminNavi()` for admin navigation blocks where appropriate.
- Keep admin handlers in named functions instead of large inline switch bodies when touching a file substantially.

```php
global $afile;
setRedirect($afile.'.php?name=modules');
```

### Language Loading

Use `getLang()` to load module language files. Do not call `setLang()` from modules.

```php
getLang('news');
getLang('news', true);
getLang('admin');
```

### Page Lifecycle

Frontend modules typically follow this flow:

```php
getLang('news');
setHead(['title' => _NEWS]);
echo $cont;
setFoot();
```

Admin pages are handled by the admin runtime and do not use the frontend lifecycle in the same way.

### Content Parsers and Editors

- **Parsing:** Use `Parser::filterContent()` from `core/classes/parser.php` to render Markdown/BBCode to HTML. Do not use legacy functions like `filterMarkdown()`.
- **Editors:** When building forms, use `Editor::getContent()` for WYSIWYG textareas and `Editor::getCode()` for syntax highlighting fields. Do not manually output generic `<textarea>` tags with custom JS initializers.

---

## Template Runtime Guidance

The active file-backed template runtime in the current repository is:

- `core/classes/template.php`

Historical legacy rendering still exists in PHP-side output assembly, but the repository snapshot does not contain an active `core/template.php` runtime file.

For new template work:

- Prefer the modern `Template` runtime.
- Use the shared `$tpl` runtime object when available.
- Keep HTML in template files under `templates/*`.
- Place reusable components in `partials/` (e.g., `partials/modal.html`) to take advantage of shortname syntax (`{% component 'modal' %}`).
- Keep component CSS and JS alongside the HTML file (`partials/modal.css` and `partials/modal.js`). The runtime will inject them automatically with zero runtime I/O overhead.
- Do not add new legacy `setTemplateBasic()`-only rendering paths for new slices unless the task explicitly requires legacy work.

Current modern runtime methods:

```php
$tpl->getHtmlPage('error', $data);
$tpl->getHtmlPart('login', $data);
$tpl->getHtmlFrag('message', $data);
```

### SEO Head Overrides

The current frontend runtime also supports central SEO overrides through `setHead()`:

```php
setHead([
    'title' => $title,
    'canon' => 'index.php?name=news&op=view&id='.$id,
    'robots' => 'noindex, follow',
]);
```

Rules:

- `canon` overrides the centrally generated canonical URL
- `robots` overrides the default robots meta value
- if `canon` is omitted, the runtime builds the canonical URL from normalized route parameters

---

## Commit Guidelines

### Use the Commit Template

```bash
git config commit.template .gitmessage
```

### Commit Message Format

```
Type: Short description

Extended context explaining what changed and why.
```

Common types:

- `Feature`
- `Fix`
- `Refactor`
- `Docs`
- `Style`
- `Test`
- `Chore`
- `Perf`

Keep commits focused and atomic where possible.

---

## Pull Request Process

### Before Submitting

1. Sync with upstream.
2. Run relevant tests and syntax checks.
3. Update documentation when behavior or developer workflow changes.

### Checklist

- [ ] Code follows project rules in `.rules/`
- [ ] SQL uses prepared statements
- [ ] User input is validated
- [ ] New comments are written in English
- [ ] Relevant tests were run
- [ ] Documentation was updated when needed

In the PR description, include:

- Summary
- Testing performed
- Breaking changes, if any
- Related issues or context

---

## Testing

Run the checks that match your change scope:

```bash
./vendor/bin/phpunit
./vendor/bin/phpstan analyse
./vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no --config=.php-cs-fixer.dist.php <paths>
php -l path/to/file.php
```

Project configuration:

- PHPUnit config: `phpunit.xml`
- PHPStan config: `phpstan.neon`
- Composer dev tools: `composer.json`
- Composer scripts: `composer test`, `composer analyse`, `composer quality`

---

## Documentation

### Code Comments

- Write comments in English.
- Explain intent where needed.
- Avoid comments that restate obvious code.

### File Headers

For new PHP files:

```php
<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net
```

### Public Docs

When updating public documentation:

- prefer code-backed claims
- avoid unsupported metrics and guarantees
- keep useful detail when it reflects the current repository

---

## Questions

- **Forum:** [slaed.net/forum](https://slaed.net/index.php?name=forum)
- **Documentation EN:** [slaed.info](https://slaed.info)
- **Documentation DE:** [slaed.de](https://slaed.de)
- **Email:** info@slaed.net

---

*SLAED CMS © 2005 - 2026 Eduard Laas. Licensed under GNU GPL 3.*
