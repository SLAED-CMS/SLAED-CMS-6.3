# Template System Documentation

> **SLAED CMS Template Guide**
> *Last updated: January 2026*

This document describes the SLAED CMS template system, including conditional rendering, variable substitution, and best practices.

---

## Table of Contents

- [Overview](#overview)
- [Main Layout Template](#main-layout-template)
- [Layout Variables](#layout-variables)
- [Variable Substitution](#variable-substitution)
- [Conditional Rendering](#conditional-rendering)
- [Template Functions](#template-functions)
- [Practical Examples](#practical-examples)
- [Security & Performance](#security--performance)

---

## Overview

The SLAED CMS template system provides a simple, secure way to separate presentation from logic. Templates are HTML files with placeholders and conditional blocks that are processed by PHP.

**Key Principles:**
- All logic is handled in PHP
- Templates control presentation only
- No PHP code execution inside templates
- Pure string-based parsing (no `eval()`)

> [!NOTE]
> Unlike many PHP template engines, SLAED templates do not execute any PHP code. This design choice prioritizes security over flexibility.

---

## Main Layout Template

The main layout template (`index.html`) controls the overall structure and arrangement of modules, blocks, and other system components. Designers can use any HTML layout techniques within this file.

### Template Location

```
templates/<theme>/index.html
```

> [!IMPORTANT]
> The `index.html` file is required for every theme. Without it, the system cannot render pages.

### Custom Templates

SLAED CMS supports automatic template detection for specific pages:

#### Home Page Template

Create a unique layout specifically for the home page, regardless of which module is set as default.

**File:** `templates/<theme>/index-home.html`

```
templates/
└── mytheme/
    ├── index.html        # Default layout
    └── index-home.html   # Home page layout (auto-detected)
```

#### Module-Specific Templates

Create a unique layout for a specific module.

**Format:** `index-{module}.html`

**Example:** Custom layout for the `news` module:

```
templates/
└── mytheme/
    ├── index.html        # Default layout
    └── index-news.html   # News module layout (auto-detected)
```

> [!TIP]
> Module names correspond to folder names in the `modules/` directory.

#### Category-Specific Templates

Create a unique layout for a specific category within a module.

**Format:** `index-{module}-cat-{id}.html`

**Example:** Custom layout for category ID 5 in the `news` module:

```
templates/
└── mytheme/
    ├── index.html              # Default layout
    ├── index-news.html         # News module layout
    └── index-news-cat-5.html   # Category 5 layout (auto-detected)
```

> [!NOTE]
> Category IDs can be found in the admin panel under **Categories**.

### Template Priority

The system automatically selects templates in this order (most specific first):

1. `index-{module}-cat-{id}.html` - Category-specific
2. `index-{module}.html` - Module-specific
3. `index-home.html` - Home page only
4. `index.html` - Default fallback

> [!TIP]
> Use category-specific templates for landing pages, promotional sections, or unique content areas that need special styling.

---

## Layout Variables

These variables are used in the main layout template (`index.html`) to position system components.

### Standard Variables

| Variable | Short Form | Description |
|----------|------------|-------------|
| `{%HEAD%}` | - | HTML head section (meta tags, title, default includes) |
| `{%MODULE%}` | - | Module content area (main content placeholder) |
| `{%LICENSE%}` | - | System copyright/license text |
| `{%BLOCKS banner%}` | `{%BLOCKS b%}` | Top banner blocks |
| `{%BLOCKS left%}` | `{%BLOCKS l%}` | Left sidebar blocks |
| `{%BLOCKS message%}` | `{%BLOCKS m%}` | Home page message block |
| `{%BLOCKS center%}` | `{%BLOCKS c%}` | Top center blocks (above module) |
| `{%BLOCKS down%}` | `{%BLOCKS d%}` | Bottom center blocks (below module) |
| `{%BLOCKS right%}` | `{%BLOCKS r%}` | Right sidebar blocks |
| `{%BLOCKS foot%}` | `{%BLOCKS f%}` | Footer blocks |
| `{%BLOCKS time%}` | `{%BLOCKS t%}` | Page generation time |
| `{%BLOCKS variables%}` | - | Variable analyzer (debug) |
| `{%BLOCKS query%}` | - | Database query analyzer (debug) |

> [!WARNING]
> Debug variables (`{%BLOCKS variables%}` and `{%BLOCKS query%}`) should only be used during development. Remove them before deploying to production!

### Additional Block Variables

For displaying specific blocks by ID or filename:

| Variable | Short Form | Description |
|----------|------------|-------------|
| `{%BLOCKS none,XXX%}` | `{%BLOCKS n,XXX%}` | Block without wrapper styling |
| `{%BLOCKS standart,XXX%}` | `{%BLOCKS s,XXX%}` | Block with standard styling |

Where `XXX` is either:
- Block ID (numeric)
- Block filename (e.g., `myblock.php`)

### Basic Layout Example

```html
<!DOCTYPE html>
<html lang="{%lang%}">
<head>
    {%HEAD%}
</head>
<body>
    <header>
        {%BLOCKS banner%}
    </header>

    <div class="container">
        <aside class="sidebar-left">
            {%BLOCKS left%}
        </aside>

        <main>
            {%BLOCKS center%}
            {%MODULE%}
            {%BLOCKS down%}
        </main>

        <aside class="sidebar-right">
            {%BLOCKS right%}
        </aside>
    </div>

    <footer>
        {%BLOCKS foot%}
        {%LICENSE%}
        {%BLOCKS time%}
    </footer>
</body>
</html>
```

### Custom Block Placement

Display a specific block (ID 15) without wrapper:

```html
<div class="featured">
    {%BLOCKS none,15%}
</div>
```

Display a block file with standard styling:

```html
<div class="widget">
    {%BLOCKS standart,weather.php%}
</div>
```

---

## Variable Substitution

Variables use the `{%name%}` syntax and are replaced with values passed from PHP.

### Syntax

```html
<div class="{%class%}">{%content%}</div>
<a href="{%homeurl%}">{%sitename%}</a>
```

### PHP Usage

```php
echo setTemplateBasic('template-name', [
    '{%class%}'   => 'container',
    '{%content%}' => $html_content,
]);
```

### Global Variables

The following variables are automatically available via `getTemplateVars()`:

> [!NOTE]
> Global variables are cached per theme for optimal performance. They are automatically populated from system configuration and language files.

| Variable | Description |
|----------|-------------|
| `{%theme%}` | Current theme name |
| `{%lang%}` | Language code (2 chars) |
| `{%sitename%}` | Site name from config |
| `{%logo%}` | Site logo path |
| `{%homeurl%}` | Home URL |
| `{%slogan%}` | Site slogan |
| `{%home%}` | "Home" translation |
| `{%account%}` | "Account" translation |
| `{%search%}` | "Search" translation |
| ... | And many more module names |

---

## Conditional Rendering

SLAED CMS supports minimal and safe conditional rendering based on boolean flags.

### Syntax

```html
{%if FLAG%}
  Content shown when FLAG is true
{%else%}
  Content shown when FLAG is false
{%endif%}
```

### Rules

| Feature | Supported |
|---------|-----------|
| `{%if FLAG%}` | Yes |
| `{%else%}` | Yes |
| `{%endif%}` | Yes |
| Nested IF blocks | Yes |
| `{%elseif%}` | No |
| Logical operators (`&&`, `\|\|`, `!`) | No |
| Comparisons (`==`, `>`, `<`) | No |
| Function calls | No |
| Dot notation (`user.is_admin`) | No |

> [!IMPORTANT]
> FLAG must match the pattern `[a-zA-Z0-9_]+` (alphanumeric and underscore only).

### Passing Flags from PHP

Flags are passed via the `flag` key (array):

```php
echo setTemplateBasic('template-name', [
    '{%variable%}' => $value,
    'flag' => [
        'logged_in' => is_user(),
        'is_admin'  => is_admin(),
    ],
]);
```

### Flag Value Handling

- Values are treated as boolean
- Strings `"true"` / `"false"` are normalized automatically
- Undefined flags default to `false`

> [!TIP]
> Use descriptive flag names that clearly indicate their purpose: `show_sidebar`, `has_comments`, `is_premium_user` etc.

---

## Template Functions

### setTemplateBasic()

Load and render a basic template.

```php
setTemplateBasic(string $tpl, array $val = []): string
```

**Parameters:**
- `$tpl` - Template name (without `.html` extension)
- `$val` - Array of variables and flags

**Example:**

```php
$html = setTemplateBasic('header', [
    '{%title%}' => 'Welcome',
    'flag' => [
        'logged_in' => is_user(),
    ],
]);
```

### setTemplateBlock()

Load and render a block template with automatic fallback.

```php
setTemplateBlock(string $tpl, array $val = []): string
```

**Fallback Order:**
1. `templates/<theme>/block-<name>.html` (direct match)
2. `templates/<theme>/block-<position>.html` (position-based)
3. `templates/<theme>/block-all.html` (universal fallback)

> [!NOTE]
> Position values are: `l` (left), `r` (right), `c` (center), `d` (down), `b` (banner), `f` (footer).

### setTemplateWarning()

Display a warning/info message.

```php
setTemplateWarning(string $tpl, array $set = [], array $val = []): string
```

**Parameters:**
- `$set['text']` - Message text
- `$set['url']` - Redirect URL (optional)
- `$set['time']` - Redirect delay in seconds (optional)
- `$set['id']` - CSS ID (default: 'warn')

**Example:**

```php
$html = setTemplateWarning('warn', [
    'text' => 'Settings saved successfully!',
    'id'   => 'info',
]);
```

---

## Practical Examples

> [!TIP]
> Copy these examples as starting points for your own templates. Modify the HTML structure and CSS classes to match your design.

### Example 1: Login/Logout in Header

**Template:** `templates/<theme>/header.html`

```html
<nav>
  {%if logged_in%}
    <a href="index.php?name=account">{%account%}</a>
    <a href="index.php?op=logout">{%logout_text%}</a>
  {%else%}
    <a href="index.php?name=users&op=login">{%login_text%}</a>
  {%endif%}
</nav>
```

**PHP:**

```php
echo setTemplateBasic('header', [
    '{%login_text%}'  => _LOGIN,
    '{%logout_text%}' => _LOGOUT,
    'flag' => [
        'logged_in' => is_user(),
    ],
]);
```

---

### Example 2: Admin Link (Admin Only)

**Template:** `templates/<theme>/nav.html`

```html
<ul class="nav">
  <li><a href="index.php">{%home%}</a></li>

  {%if is_admin%}
    <li><a href="admin.php">{%admin_text%}</a></li>
  {%endif%}
</ul>
```

**PHP:**

```php
echo setTemplateBasic('nav', [
    '{%admin_text%}' => _ADMIN,
    'flag' => [
        'is_admin' => is_admin(),
    ],
]);
```

---

### Example 3: Nested Conditions (User + Admin Badge)

**Template:** `templates/<theme>/profilebox.html`

```html
<section>
  {%if logged_in%}
    <div>Hello, {%user_name%}</div>

    {%if is_admin%}
      <div class="badge">Admin</div>
      <a href="admin.php">Admin Panel</a>
    {%endif%}

  {%else%}
    <a href="index.php?name=users&op=login">Login</a>
  {%endif%}
</section>
```

**PHP:**

```php
echo setTemplateBasic('profilebox', [
    '{%user_name%}' => $uname ?? '',
    'flag' => [
        'logged_in' => is_user(),
        'is_admin'  => is_admin(),
    ],
]);
```

---

### Example 4: Feature Toggle (Banner)

**Template:** `templates/<theme>/home.html`

```html
{%if show_banner%}
  <div class="alert alert-info">{%banner_text%}</div>
{%endif%}
```

**PHP:**

```php
echo setTemplateBasic('home', [
    '{%banner_text%}' => 'Welcome!',
    'flag' => [
        'show_banner' => (bool)($conf['show_banner'] ?? false),
    ],
]);
```

---

### Example 5: Block Template with Content Check

**Template:** `templates/<theme>/block-left.html`

```html
<div class="block">
  <h3>{%title%}</h3>

  {%if has_content%}
    <div class="body">{%content%}</div>
  {%else%}
    <div class="body empty">No content available</div>
  {%endif%}
</div>
```

**PHP:**

```php
echo setTemplateBlock('ignored', [
    '{%title%}'   => $title,
    '{%content%}' => $content,
    'flag' => [
        'has_content' => trim((string)$content) !== '',
    ],
]);
```

---

### Example 6: Undefined Flag (Default Behavior)

When a flag is not defined, it defaults to `false`.

> [!CAUTION]
> Undefined flags silently default to `false`. Always ensure all required flags are passed from PHP to avoid unexpected behavior.

**Template:**

```html
{%if something%}
  YES
{%else%}
  NO
{%endif%}
```

**PHP:**

```php
echo setTemplateBasic('test', []);
// Output: NO
```

---

## Security & Performance

### Security

| Feature | Implementation |
|---------|----------------|
| No `eval()` | Templates are parsed as strings only |
| No PHP execution | No `<?php ?>` tags processed |
| XSS prevention | Use `htmlspecialchars()` for user data |
| Safe parsing | Pure regex-based string replacement |

> [!CAUTION]
> Always escape user-provided data before passing to templates:
> ```php
> '{%username%}' => htmlspecialchars($user_input, ENT_QUOTES, 'UTF-8')
> ```

### Performance

| Optimization | Description |
|--------------|-------------|
| Early return | Templates without `{%` are returned immediately |
| Static caching | Template variables cached per theme |
| Minimal parsing | Only IF blocks trigger regex processing |
| No file I/O overhead | Templates cached after first load |

### Best Practices

1. **Keep logic in PHP** - Templates should only handle presentation
2. **Use meaningful flag names** - `logged_in` instead of `flag1`
3. **Escape user data** - Always use `htmlspecialchars()` for untrusted input
4. **Avoid deep nesting** - Maximum 2-3 levels of nested conditions
5. **Use global variables** - Leverage `getTemplateVars()` for common values

> [!IMPORTANT]
> Templates are designed for presentation logic only. Complex business logic, database queries, and calculations must always be handled in PHP code.

---

## Migration from Legacy Templates

If upgrading from SLAED CMS 6.2.x, note these changes:

| Old (6.2.x) | New (6.3.x) |
|-------------|-------------|
| `tpl_eval('name')` | `setTemplateBasic('name')` |
| `tpl_warn('warn', $text, ...)` | `setTemplateWarning('warn', ['text' => $text])` |
| `tpl_func('name')` | `setTemplateBasic('name')` |
| Direct variable substitution | Use `{%var%}` placeholders |

> [!WARNING]
> The old `tpl_eval()` and `tpl_func()` functions used `eval()` and are deprecated for security reasons. They will be removed in a future version.

---

## Related Documentation

- [CONTRIBUTING.md](../CONTRIBUTING.md) - Coding standards and contribution guidelines
- [README.md](../README.md) - Project overview
- [UPGRADING.md](../UPGRADING.md) - Migration guide

---

*SLAED CMS © 2005 - 2026 Eduard Laas. Licensed under GNU GPL 3.*
