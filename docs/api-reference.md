# SLAED CMS API Reference

## Table of Contents
1. [Introduction](#introduction)
2. [Authentication](#authentication)
3. [User API](#user-api)
4. [Content API](#content-api)
5. [Module API](#module-api)
6. [Block API](#block-api)
7. [Template API](#template-api)
8. [Database API](#database-api)
9. [Security API](#security-api)
10. [Cache API](#cache-api)
11. [Utility Functions](#utility-functions)
12. [Error Handling](#error-handling)

## Introduction

The SLAED CMS API provides programmatic access to core functionality, allowing developers to integrate with external systems, build custom modules, and extend the platform's capabilities.

### API Access
To access the API, include the necessary core files:

```php
<?php
// Include core API functions
require_once('core/api.php');
require_once('core/functions.php');
?>
```

### Response Format
API functions typically return structured data:

```php
// Success response
[
    'status' => 'success',
    'data' => [...],
    'message' => 'Operation completed successfully'
]

// Error response
[
    'status' => 'error',
    'error_code' => 404,
    'message' => 'Resource not found'
]
```

## Authentication

### User Authentication
Authenticate users programmatically:

```php
// Authenticate user
$result = api_authenticate_user($username, $password);

if ($result['status'] === 'success') {
    $user_data = $result['data'];
    // User authenticated successfully
} else {
    // Authentication failed
    $error = $result['message'];
}
```

### Session Management
Manage user sessions:

```php
// Start session
api_session_start();

// Check if user is logged in
if (api_is_user_logged_in()) {
    $user_id = api_get_current_user_id();
}

// End session
api_session_end();
```

### Token-Based Authentication
For external API access:

```php
// Generate API token
$token = api_generate_token($user_id, $permissions);

// Validate token
if (api_validate_token($token)) {
    $user_data = api_get_user_from_token($token);
}
```

## User API

### User Creation
Create new users:

```php
$user_data = [
    'username' => 'newuser',
    'email' => 'user@example.com',
    'password' => 'securepassword',
    'first_name' => 'John',
    'last_name' => 'Doe'
];

$result = api_create_user($user_data);

if ($result['status'] === 'success') {
    $new_user_id = $result['data']['user_id'];
}
```

### User Retrieval
Get user information:

```php
// Get user by ID
$user = api_get_user_by_id($user_id);

// Get user by username
$user = api_get_user_by_username($username);

// Get user by email
$user = api_get_user_by_email($email);

// Get multiple users
$users = api_get_users([
    'role' => 'administrator',
    'limit' => 10,
    'offset' => 0
]);
```

### User Update
Update user information:

```php
$update_data = [
    'email' => 'newemail@example.com',
    'first_name' => 'Jane',
    'last_name' => 'Smith'
];

$result = api_update_user($user_id, $update_data);
```

### User Deletion
Remove users:

```php
$result = api_delete_user($user_id);

// Soft delete (mark as inactive)
$result = api_soft_delete_user($user_id);
```

## Content API

### Content Creation
Create new content items:

```php
$content_data = [
    'title' => 'Sample Article',
    'content' => 'This is the article content...',
    'excerpt' => 'Brief description',
    'status' => 'published',
    'author_id' => $user_id,
    'category_id' => $category_id,
    'tags' => ['tag1', 'tag2', 'tag3']
];

$result = api_create_content('news', $content_data);
```

### Content Retrieval
Get content items:

```php
// Get content by ID
$content = api_get_content_by_id('news', $content_id);

// Get multiple content items
$contents = api_get_contents('news', [
    'status' => 'published',
    'category_id' => $category_id,
    'limit' => 20,
    'offset' => 0,
    'order_by' => 'created_at',
    'order_dir' => 'DESC'
]);

// Search content
$results = api_search_content('news', $search_term, [
    'limit' => 10
]);
```

### Content Update
Modify existing content:

```php
$update_data = [
    'title' => 'Updated Title',
    'content' => 'Updated content...',
    'status' => 'published'
];

$result = api_update_content('news', $content_id, $update_data);
```

### Content Deletion
Remove content:

```php
$result = api_delete_content('news', $content_id);

// Soft delete
$result = api_soft_delete_content('news', $content_id);
```

## Module API

### Module Management
Manage modules programmatically:

```php
// Get all modules
$modules = api_get_modules();

// Get specific module
$module = api_get_module($module_name);

// Check if module is active
if (api_is_module_active($module_name)) {
    // Module is active
}

// Activate module
$result = api_activate_module($module_name);

// Deactivate module
$result = api_deactivate_module($module_name);
```

### Module Information
Retrieve module details:

```php
$module_info = api_get_module_info($module_name);

// Get module configuration
$config = api_get_module_config($module_name);

// Set module configuration
api_set_module_config($module_name, $config_key, $config_value);
```

### Module Hooks
Work with module hooks:

```php
// Register hook
api_register_hook($module_name, $hook_name, $callback_function);

// Execute hook
$results = api_execute_hook($hook_name, $parameters);

// Remove hook
api_remove_hook($module_name, $hook_name, $callback_function);
```

## Block API

### Block Management
Manage content blocks:

```php
// Get all blocks
$blocks = api_get_blocks();

// Get specific block
$block = api_get_block($block_id);

// Create new block
$block_data = [
    'title' => 'New Block',
    'content' => 'Block content...',
    'position' => 'left',
    'weight' => 10,
    'active' => 1
];

$result = api_create_block($block_data);
```

### Block Configuration
Configure block settings:

```php
// Update block
$update_data = [
    'title' => 'Updated Block Title',
    'content' => 'Updated content...',
    'position' => 'right'
];

$result = api_update_block($block_id, $update_data);

// Set block visibility
api_set_block_visibility($block_id, [
    'roles' => ['administrator', 'editor'],
    'pages' => ['index', 'about']
]);
```

### Block Placement
Control block positioning:

```php
// Get blocks for specific position
$blocks = api_get_blocks_by_position('sidebar');

// Move block to different position
api_move_block($block_id, 'footer', 5);

// Hide block on specific pages
api_hide_block_on_pages($block_id, ['admin', 'login']);
```

## Template API

### Template Management
Work with templates:

```php
// Get template variables
$variables = api_get_template_variables();

// Assign template variable
api_assign_template_variable('PAGE_TITLE', 'My Page Title');

// Parse template
$output = api_parse_template('header.html');

// Display template
api_display_template('index.html');
```

### Template Functions
Template-related functions:

```php
// Include template file
api_include_template('partials/navigation.html');

// Get template path
$template_path = api_get_template_path('default');

// Set active template
api_set_active_template('modern');

// Get active template
$current_template = api_get_active_template();
```

### Theme Functions
Theme-related operations:

```php
// Get available themes
$themes = api_get_themes();

// Activate theme
api_activate_theme('default');

// Get theme information
$theme_info = api_get_theme_info('default');

// Customize theme
api_customize_theme('default', [
    'primary_color' => '#007bff',
    'font_family' => 'Arial, sans-serif'
]);
```

## Database API

### Database Connection
Database operations:

```php
// Get database connection
$db = api_get_database_connection();

// Execute query
$result = api_db_query("SELECT * FROM " . PREFIX . "_users WHERE active = 1");

// Prepared statement
$stmt = api_db_prepare("SELECT * FROM " . PREFIX . "_posts WHERE author_id = ? AND status = ?");
$stmt->bind_param("is", $author_id, $status);
$stmt->execute();
```

### CRUD Operations
Database CRUD operations:

```php
// Insert record
$insert_id = api_db_insert('users', [
    'username' => 'newuser',
    'email' => 'user@example.com',
    'created_at' => date('Y-m-d H:i:s')
]);

// Update record
$affected_rows = api_db_update('users', 
    ['email' => 'updated@example.com'], 
    ['user_id' => $user_id]
);

// Delete record
$affected_rows = api_db_delete('users', ['user_id' => $user_id]);

// Select records
$users = api_db_select('users', [
    'active' => 1
], [
    'limit' => 10,
    'order_by' => 'created_at DESC'
]);
```

### Database Utilities
Database utilities:

```php
// Escape string
$safe_string = api_db_escape($unsafe_string);

// Get table prefix
$prefix = api_db_get_prefix();

// Check if table exists
if (api_db_table_exists('users')) {
    // Table exists
}

// Get table structure
$structure = api_db_get_table_structure('users');
```

## Security API

### Input Validation
Validate and sanitize input:

```php
// Validate email
if (api_validate_email($email)) {
    // Email is valid
}

// Validate URL
if (api_validate_url($url)) {
    // URL is valid
}

// Sanitize string
$safe_string = api_sanitize_string($input_string);

// Sanitize HTML
$safe_html = api_sanitize_html($html_content);
```

### Authentication Functions
Security authentication:

```php
// Hash password
$hashed_password = api_hash_password($password);

// Verify password
if (api_verify_password($password, $hashed_password)) {
    // Password is correct
}

// Generate CSRF token
$csrf_token = api_generate_csrf_token();

// Verify CSRF token
if (api_verify_csrf_token($token)) {
    // Token is valid
}
```

### Permission Functions
Permission management:

```php
// Check permission
if (api_check_permission($user_id, 'edit_posts')) {
    // User has permission
}

// Grant permission
api_grant_permission($user_id, 'edit_posts');

// Revoke permission
api_revoke_permission($user_id, 'edit_posts');

// Get user permissions
$permissions = api_get_user_permissions($user_id);
```

### Security Logging
Security event logging:

```php
// Log security event
api_log_security_event('login_attempt', [
    'user_id' => $user_id,
    'ip_address' => $_SERVER['REMOTE_ADDR'],
    'success' => true
]);

// Get security logs
$logs = api_get_security_logs([
    'limit' => 50,
    'order_by' => 'created_at DESC'
]);

// Check for suspicious activity
if (api_check_suspicious_activity($user_id)) {
    // Take appropriate action
}
```

## Cache API

### Cache Management
Cache operations:

```php
// Set cache
api_cache_set('key', $data, 3600); // Cache for 1 hour

// Get cache
$cached_data = api_cache_get('key');

// Delete cache
api_cache_delete('key');

// Clear all cache
api_cache_clear();
```

### Cache Types
Different cache types:

```php
// File cache
api_file_cache_set('key', $data, 3600);
$data = api_file_cache_get('key');

// Memory cache (if available)
if (api_memory_cache_available()) {
    api_memory_cache_set('key', $data, 3600);
    $data = api_memory_cache_get('key');
}

// Database cache
api_db_cache_set('key', $data, 3600);
$data = api_db_cache_get('key');
```

### Cache Configuration
Cache configuration:

```php
// Get cache configuration
$config = api_get_cache_config();

// Set cache configuration
api_set_cache_config([
    'default_ttl' => 3600,
    'cache_dir' => '/path/to/cache',
    'enabled' => true
]);

// Check if cache is enabled
if (api_is_cache_enabled()) {
    // Cache is enabled
}
```

## Utility Functions

### String Functions
String manipulation utilities:

```php
// Generate slug
$slug = api_generate_slug('My Article Title');

// Truncate text
$excerpt = api_truncate_text($content, 100);

// Format date
$formatted_date = api_format_date($timestamp, 'Y-m-d');

// Generate random string
$random_string = api_generate_random_string(32);
```

### File Functions
File operations:

```php
// Upload file
$result = api_upload_file($_FILES['upload'], [
    'allowed_types' => ['jpg', 'png', 'gif'],
    'max_size' => 5242880 // 5MB
]);

// Resize image
api_resize_image($source_path, $destination_path, 800, 600);

// Get file information
$file_info = api_get_file_info($file_path);

// Delete file
api_delete_file($file_path);
```

### Array Functions
Array utilities:

```php
// Merge arrays recursively
$merged = api_array_merge_recursive($array1, $array2);

// Filter array
$filtered = api_array_filter($array, function($item) {
    return $item['active'] == 1;
});

// Sort array by key
$sorted = api_array_sort_by_key($array, 'name');

// Convert array to object
$object = api_array_to_object($array);
```

### HTTP Functions
HTTP utilities:

```php
// Make HTTP request
$response = api_http_request('https://api.example.com/data', [
    'method' => 'GET',
    'headers' => [
        'Authorization' => 'Bearer ' . $token
    ]
]);

// Get client IP
$client_ip = api_get_client_ip();

// Redirect
api_redirect('/new-page');

// Get current URL
$current_url = api_get_current_url();
```

## Error Handling

### Error Response Format
Standard error response structure:

```php
[
    'status' => 'error',
    'error_code' => 400,
    'message' => 'Bad Request',
    'details' => 'Missing required parameter: username'
]
```

### Common Error Codes
Standard error codes:

```php
// 400 - Bad Request
api_error_bad_request('Missing required parameters');

// 401 - Unauthorized
api_error_unauthorized('Invalid credentials');

// 403 - Forbidden
api_error_forbidden('Insufficient permissions');

// 404 - Not Found
api_error_not_found('Resource not found');

// 500 - Internal Server Error
api_error_internal('An unexpected error occurred');
```

### Exception Handling
Handle exceptions properly:

```php
try {
    $result = api_some_function($parameters);
    if ($result['status'] === 'error') {
        throw new APIException($result['message'], $result['error_code']);
    }
} catch (APIException $e) {
    api_error_response($e->getCode(), $e->getMessage());
} catch (Exception $e) {
    api_error_internal('An unexpected error occurred');
}
```

### Logging Errors
Log errors for debugging:

```php
// Log error
api_log_error('Database connection failed', [
    'error_code' => $error_code,
    'error_message' => $error_message,
    'stack_trace' => debug_backtrace()
]);

// Get error logs
$error_logs = api_get_error_logs([
    'limit' => 100,
    'order_by' => 'created_at DESC'
]);
```

---

*© 2005-2026 Eduard Laas. All rights reserved.*