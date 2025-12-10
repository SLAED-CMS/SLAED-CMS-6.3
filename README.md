# SLAED CMS 6.3

![PHP Version](https://img.shields.io/badge/PHP-8.4+-blue.svg)
![License](https://img.shields.io/badge/License-GPL--3.0-green.svg)
![Status](https://img.shields.io/badge/Status-Active%20Development-orange.svg)
![Migration](https://img.shields.io/badge/Migration-50%25%20Complete-yellow.svg)

**Modern, Secure, High-Performance Content Management System**

SLAED CMS is a powerful, modular content management system built with modern PHP 8.4 standards, featuring comprehensive security, multi-language support, and extensive customization options.

---

## 🚀 Quick Start

```bash
# 1. Clone or download the repository
git clone https://github.com/yourusername/slaed-cms.git

# 2. Configure database
cp config/config_db.php.example config/config_db.php
# Edit config/config_db.php with your database credentials

# 3. Import database schema
mysql -u root -p your_database < database/schema.sql

# 4. Set permissions
chmod -R 755 config/ storage/ uploads/
chmod 666 config/*.php

# 5. Open in browser
http://localhost/slaed-cms/
```

**Default admin credentials:**
- Username: `admin`
- Password: `admin` (change immediately!)

---

## 📋 System Requirements

- **PHP:** 8.4+ (8.1+ supported)
- **Database:** MySQL 8.0+ or MariaDB 10+
- **Web Server:** Apache, Nginx, IIS
- **Extensions:** PDO, MySQLi, GD, mbstring, JSON
- **Encoding:** UTF-8 (utf8mb4)

---

## 🔧 Installation

### Method 1: Manual Installation

1. **Download** the latest release or clone the repository
2. **Extract** files to your web server directory
3. **Create** a MySQL/MariaDB database
4. **Configure** database connection in `config/config_db.php`
5. **Import** database schema from `database/schema.sql`
6. **Set permissions** on writable directories:
   ```bash
   chmod -R 755 config/ storage/ uploads/
   chmod 666 config/*.php storage/logs/*.txt
   ```
7. **Run setup** by accessing `http://yoursite.com/setup.php`
8. **Delete** setup.php after installation

### Method 2: Using setup.php

1. Upload all files to your web server
2. Navigate to `http://yoursite.com/setup.php`
3. Follow the installation wizard
4. Delete `setup.php` after successful installation

---

## 🎯 Tech Stack

- **Backend:** PHP 8.4 with strict types and type declarations
- **Database:** PDO with prepared statements (SQL injection prevention)
- **Frontend:** jQuery 3.x + jQuery UI
- **Editors:** CKEditor 4, TinyMCE, CodeMirror
- **Security:** XSS filtering, CSRF tokens, prepared statements
- **Caching:** Multi-level (pages, blocks, CSS, JS)
- **Languages:** 6 languages out-of-the-box (EN, FR, DE, PL, RU, UA)

---

## ✨ Features

### Core Functionality
- 🎨 **27+ Modules** - News, forum, shop, gallery, files, and more
- 🌍 **Multi-language** - Full support for 6 languages
- 👥 **User Management** - Groups, permissions, roles
- 🔒 **Security** - XSS, CSRF, SQL injection protection
- ⚡ **Performance** - Multi-level caching system
- 📱 **Responsive** - Mobile-friendly admin panel

### Content Management
- 📝 WYSIWYG editors (CKEditor, TinyMCE)
- 📂 File manager with drag & drop
- 🖼️ Media gallery with image processing
- 📰 News and articles system
- 💬 Comment system with moderation

### E-Commerce
- 🛒 Shopping cart and checkout
- 💳 Order management
- 📦 Product catalog with categories
- 💰 Payment integration ready

### SEO & Marketing
- 🔍 SEO optimization (meta tags, keywords)
- 🗺️ XML Sitemap generation
- 📡 RSS feeds
- 🔗 Clean URLs (mod_rewrite)
- 📊 Statistics and analytics

---

## 🏗️ Project Structure

```
slaed-cms/
├── admin/                 # Admin panel interface
│   ├── modules/          # Admin modules
│   └── language/         # Admin translations
├── blocks/               # Reusable UI components
├── config/               # Configuration files
│   ├── config_db.php    # Database configuration
│   ├── config_global.php # Global settings (187+ parameters)
│   └── *.php            # Module-specific configs
├── core/                 # System core
│   ├── core.php         # Main core file (271 KB)
│   ├── security.php     # Security functions
│   ├── user.php         # User management
│   └── classes/         # Database drivers (MySQLi, PDO)
├── language/             # Multi-language files
│   ├── lang-english.php
│   ├── lang-german.php
│   └── ...
├── modules/              # Frontend modules (27+)
│   ├── news/            # News module
│   ├── forum/           # Forum module
│   ├── shop/            # E-commerce module
│   └── ...
├── plugins/              # JavaScript libraries
│   ├── jquery/
│   ├── ckeditor/
│   └── ...
├── storage/              # System data
│   ├── cache/           # Cache files
│   ├── logs/            # System logs
│   └── backup/          # Backups
├── templates/            # Themes
│   ├── admin/           # Admin theme
│   ├── default/         # Default frontend theme
│   └── lite/            # Lite theme
├── uploads/              # User uploads
├── index.php             # Frontend entry point
├── admin.php             # Admin entry point
└── setup.php             # Installation wizard
```

---

## 🔄 Modernization Status (v6.3)

SLAED CMS 6.3 is undergoing a major modernization to PHP 8.4 standards:

**Progress: 50% Complete**

### ✅ Completed
- 2106+ SQL queries converted to prepared statements
- 269 user input points secured with validation
- 12 admin modules fully modernized
- Type declarations added (parameters & return types)
- Modern array syntax (`[]` instead of `array()`)
- Input validation with `getVar()` helper
- Quote consistency (single quotes throughout)

### 🚧 In Progress
- Remaining admin modules (15 modules)
- Frontend modules optimization
- Performance improvements
- Documentation updates

### 🎯 Goals
- ✅ Full PHP 8.4 compatibility
- ✅ Enhanced security (SQL injection prevention)
- ✅ Better performance (2-3x faster with PHP 8.4)
- ✅ Type safety (strict types, type hints)
- ✅ Modern coding standards (PSR-12 compatible)

---

## 💻 Development

### Coding Standards

**Core Principles:**
1. **Fast** - Optimized queries, efficient caching
2. **Stable** - Error prevention, consistent API
3. **Effective** - Reusable code, no redundancy
4. **Productive** - Easy extensibility, clear guidelines
5. **Secure** - Protection against XSS, CSRF, SQL injection

**Function Naming (Mandatory):**
```php
// Format: verb + Noun (camelCase)
function getUserById(int $id): array {}
function setConfig(string $file, array $data): bool {}
function isUserActive(int $id): bool {}
function checkPermission(string $perm): bool {}
function filterInput(string $data): string {}
```

**8 Required Verbs:**
- `get` - retrieve data
- `set` - save/set data
- `add` - create new entity
- `update` - modify existing
- `delete` - remove entity
- `is` - boolean check
- `check` - validation
- `filter` - sanitization

**Variable Naming:**
```php
// ✅ Correct
$id = 123;
$cfg = [];
$list = [];
$user = '';

// ❌ Wrong
$userId = 123;        // No camelCase
$configuration = [];  // Too long
```

**Constants:**
```php
// Format: _UPPER_CASE with _ prefix
define('_ERR_FILE', 'File not found: %1$s');
define('_USR_ACTIVE', 'User is active');

// MUST be defined in ALL 6 languages:
// EN, FR, DE, PL, RU, UA
```

**Security Best Practices:**
```php
// ✅ Input validation
$id = getVar('post', 'id', 'num');
$name = getVar('post', 'name', 'name', '');
$url = getVar('post', 'url', 'url', 'https://');

// ✅ SQL prepared statements
$db->sql_query('SELECT * FROM '.$prefix.'_users WHERE id = :id', ['id' => $id]);

// ✅ Output escaping
echo htmlspecialchars($user_input, ENT_QUOTES, 'UTF-8');

// ❌ NEVER do this
$db->sql_query("SELECT * FROM users WHERE id = '".$id."'");
```

**Code Style:**
```php
// Always use single quotes
$text = 'Hello World';

// Modern array syntax
$arr = ['item1', 'item2', 'item3'];

// String concatenation (no spaces around .)
$html = '<div class="'.$cls.'">'.$text.'</div>';

// Type declarations
function processData(int $id, string $name = ''): array {
    return ['id' => $id, 'name' => $name];
}
```

### File Naming Conventions
- **Files:** snake_case.php
- **Classes:** PascalCase
- **Constants:** _UPPER_CASE

### Code Formatting
- **Indentation:** 4 spaces (no tabs)
- **Line length:** Max 120 characters
- **Encoding:** UTF-8
- **Line endings:** LF (\n)

---

## 🤝 Contributing

We welcome contributions! Please follow these guidelines:

1. **Fork** the repository
2. **Create** a feature branch (`git checkout -b feature/amazing-feature`)
3. **Follow** SLAED coding standards
4. **Test** your changes thoroughly
5. **Commit** with clear messages
6. **Push** to your branch
7. **Open** a Pull Request

**Code Requirements:**
- Follow SLAED naming conventions
- Add type hints to all functions
- Use prepared statements for SQL
- Validate all user input with `getVar()`
- Write comments in English
- Test on PHP 8.4+

---

## 📝 License

GNU General Public License v3.0

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

See [LICENSE](LICENSE) for more details.

---

## 👤 Author

**Eduard Laas**

- Website: [https://slaed.net](https://slaed.net)
- Email: info@slaed.net
- Copyright © 2005 - 2026 SLAED

---

## 📮 Support

- **Documentation:** See project wiki (coming soon)
- **Issues:** Report bugs via GitHub Issues
- **Forum:** Community support forum (coming soon)

---

**SLAED CMS** - Powerful, Secure, Flexible Content Management for Your Projects

*Last updated: January 2025*
