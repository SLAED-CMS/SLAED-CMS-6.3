# SLAED CMS Developer Guide

## Table of Contents
1. [Introduction](#introduction)
2. [System Architecture](#system-architecture)
3. [Core Components](#core-components)
4. [Module Development](#module-development)
5. [Block System](#block-system)
6. [Template System](#template-system)
7. [Database Layer](#database-layer)
8. [Security Framework](#security-framework)
9. [API Reference](#api-reference)
10. [Performance Optimization](#performance-optimization)
11. [Testing](#testing)
12. [Deployment](#deployment)

## Introduction

SLAED CMS is a professional content management system developed since 2005 with a focus on performance, stability, efficiency, and security. This guide provides comprehensive information for developers who want to extend or customize SLAED CMS.

### Key Features
- High performance with intelligent caching
- Modular architecture
- Multi-language support
- Advanced security features
- Flexible template system

### System Requirements
- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.3+
- Apache 2.4+ or Nginx 1.14+
- Required PHP extensions: mysqli, gd, zip, mbstring, json, curl

## System Architecture

SLAED CMS follows a layered architecture approach:

```
┌─────────────────────────────────────────────────────────────┐
│                    SLAED CMS Architecture                   │
├─────────────────────────────────────────────────────────────┤
│  Frontend Layer                                             │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐          │
│  │ Templates   │  │   Themes    │  │   Blocks    │          │
│  │   System    │  │   Engine    │  │   System    │          │
│  └─────────────┘  └─────────────┘  └─────────────┘          │
├─────────────────────────────────────────────────────────────┤
│  Application Layer                                          │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐          │
│  │   Modules   │  │   Routing   │  │   Security  │          │
│  │   System    │  │   Engine    │  │   Layer     │          │
│  └─────────────┘  └─────────────┘  └─────────────┘          │
├─────────────────────────────────────────────────────────────┤
│  Core Layer                                                 │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐          │
│  │   Database  │  │   Cache     │  │   Session   │          │
│  │   Layer     │  │   System    │  │   Manager   │          │
│  └─────────────┘  └─────────────┘  └─────────────┘          │
└─────────────────────────────────────────────────────────────┘
```

## Core Components

### Core Functions
The core functions provide the foundation for all SLAED CMS operations:

```php
// Example of core function usage
include("config.php");
include("core/core.php");

// Initialize system
system_init();

// Database connection
$db = db_connect();

// User authentication
$user = user_authenticate($username, $password);
```

### Configuration System
SLAED CMS uses a hierarchical configuration system:

```php
// Access global configuration
$config = get_config();

// Module-specific configuration
$module_config = get_module_config('news');

// Setting configuration values
set_config('site_name', 'My Website');
```

## Module Development

### Module Structure
Each module follows a standard directory structure:

```
modules/
└── modulename/
    ├── admin/
    │   ├── index.php
    │   └── functions.php
    ├── core/
    │   ├── functions.php
    │   └── hooks.php
    ├── language/
    │   ├── lang-english.php
    │   ├── lang-german.php
    │   └── lang-russian.php
    ├── templates/
    │   ├── frontend/
    │   └── admin/
    ├── index.php
    └── module.php
```

### Creating a New Module
1. Create the module directory structure
2. Implement the main module file
3. Add core functions
4. Create language files
5. Design templates

Example module.php:
```php
<?php
/**
 * Module Name: Example Module
 * Description: A sample module for SLAED CMS
 * Version: 1.0
 * Author: Your Name
 */

if (!defined("MODULE_FILE")) {
    header("Location: ../index.php");
    exit;
}

// Module initialization
function example_init() {
    // Initialization code here
}

// Module main function
function example_main() {
    // Main functionality
    return "Hello from Example Module";
}

// Admin function
function example_admin() {
    // Admin panel functionality
    return "Admin panel for Example Module";
}
?>
```

### Module Hooks
SLAED CMS provides hooks for extending functionality:

```php
// Hook registration
register_hook('user_login', 'example_user_login_hook');

// Hook implementation
function example_user_login_hook($user_data) {
    // Custom logic when user logs in
    log_activity("User {$user_data['username']} logged in");
}
```

## Block System

### Block Types
1. **System Blocks** - Core functionality blocks
2. **Custom Blocks** - User-created content blocks
3. **Module Blocks** - Blocks provided by modules

### Creating Custom Blocks
```php
<?php
// Custom block example
if (!defined("BLOCK_FILE")) {
    header("Location: ../index.php");
    exit;
}

function custom_block_content() {
    $content = "
    <div class='custom-block'>
        <h3>Custom Block Title</h3>
        <p>This is a custom block content.</p>
    </div>
    ";
    return $content;
}
?>
```

### Block Configuration
Blocks can be configured through the admin panel with options for:
- Title and content
- Visibility settings
- Position and weight
- Access permissions

## Template System

### Template Structure
SLAED CMS uses a flexible template system:

```
templates/
└── default/
    ├── css/
    ├── images/
    ├── js/
    ├── blocks/
    ├── modules/
    ├── header.html
    ├── footer.html
    ├── index.html
    └── style.css
```

### Template Variables
Templates use a simple variable system:

```html
<!-- Example template -->
<!DOCTYPE html>
<html lang="{LANG}">
<head>
    <title>{SITENAME} - {PAGETITLE}</title>
    <meta name="description" content="{PAGEDESCRIPTION}">
</head>
<body>
    <header>
        {HEADER}
    </header>
    
    <main>
        {CONTENT}
    </main>
    
    <footer>
        {FOOTER}
    </footer>
</body>
</html>
```

### Template Functions
```php
// Assign template variables
$template->assign('SITENAME', $config['site_name']);
$template->assign('PAGETITLE', $page_title);

// Parse template
$template->parse('header.html');
```

## Database Layer

### Database Connection
SLAED CMS provides a secure database abstraction layer:

```php
// Database connection
$db = db_connect();

// Safe query execution
$result = $db->query("SELECT * FROM " . PREFIX . "_users WHERE user_id = " . intval($user_id));

// Prepared statements
$stmt = $db->prepare("SELECT * FROM " . PREFIX . "_posts WHERE category_id = ? AND status = ?");
$stmt->bind_param("is", $category_id, $status);
$stmt->execute();
```

### Database Schema
Key tables in SLAED CMS:
- `{prefix}_users` - User accounts
- `{prefix}_modules` - Module information
- `{prefix}_blocks` - Block configuration
- `{prefix}_config` - System configuration
- `{prefix}_sessions` - User sessions

## Security Framework

### Authentication
SLAED CMS provides robust authentication:

```php
// User authentication
$user = authenticate_user($username, $password);

// Session management
session_start();
$_SESSION['user_id'] = $user['user_id'];

// Permission checking
if (check_permission('admin_access')) {
    // Allow admin access
}
```

### Input Validation
All user input should be validated:

```php
// Sanitize input
$username = sanitize_input($_POST['username']);
$email = validate_email($_POST['email']);

// CSRF protection
if (!verify_csrf_token($_POST['csrf_token'])) {
    die("CSRF token validation failed");
}
```

### XSS Prevention
Output should be escaped:

```php
// Prevent XSS
echo htmlspecialchars($user_input, ENT_QUOTES, 'UTF-8');
```

## API Reference

### Core Functions

#### User Functions
- `user_authenticate($username, $password)` - Authenticate user
- `user_create($data)` - Create new user
- `user_update($user_id, $data)` - Update user information
- `user_delete($user_id)` - Delete user

#### Module Functions
- `module_load($module_name)` - Load module
- `module_exists($module_name)` - Check if module exists
- `module_get_info($module_name)` - Get module information

#### Template Functions
- `template_assign($key, $value)` - Assign template variable
- `template_parse($template_file)` - Parse template file
- `template_display($template_file)` - Display template

### Hook System
- `register_hook($hook_name, $function)` - Register hook
- `execute_hook($hook_name, $params)` - Execute hook
- `remove_hook($hook_name, $function)` - Remove hook

## Performance Optimization

### Caching Strategies
SLAED CMS implements multiple caching layers:

```php
// File caching
cache_set('key', $data, 3600); // Cache for 1 hour
$cached_data = cache_get('key');

// Memory caching (if available)
if (extension_loaded('apcu')) {
    apcu_store('key', $data, 3600);
}
```

### Database Optimization
- Use prepared statements
- Implement proper indexing
- Limit query results
- Use database caching

### Image Optimization
- Compress images before upload
- Use appropriate image formats
- Implement lazy loading
- Use CDN for images

## Testing

### Unit Testing
Create test cases for your modules:

```php
// Example test case
class ModuleTest extends PHPUnit_TestCase {
    function test_module_function() {
        $result = module_function('test');
        $this->assertEquals('expected', $result);
    }
}
```

### Integration Testing
Test module interactions:

```php
// Test database integration
function test_database_integration() {
    $db = db_connect();
    $this->assertTrue($db->ping());
}
```

## Deployment

### Server Configuration
Recommended server settings:
- Enable OPcache for PHP
- Configure proper file permissions
- Set up SSL certificate
- Configure CDN if needed

### Backup Procedures
Regular backup strategy:
- Database backups
- File backups
- Configuration backups
- Automated backup scripts

### Monitoring
Monitor system performance:
- Error logging
- Performance metrics
- Security monitoring
- User activity tracking

---

*© 2005-2026 Eduard Laas. All rights reserved.*