# API Documentation

The SLAED CMS API provides comprehensive functionality for developers to create modules, themes, and integrations.

## 🏗️ Core API Architecture

SLAED CMS follows a layered architecture with well-defined APIs for each layer:

```
┌─────────────────────────────────────┐
│           Template API              │ ← Theme/UI Layer
├─────────────────────────────────────┤
│           Module API                │ ← Business Logic
├─────────────────────────────────────┤
│           Core API                  │ ← System Functions
├─────────────────────────────────────┤
│           Database API              │ ← Data Layer
└─────────────────────────────────────┘
```

## 🔧 Core Functions API

### Input Handling

**`getVar($method, $name, $type, $default = '')`**

Safely retrieve and filter input variables.

```php
// Get numeric value from GET
$id = getVar('get', 'id', 'num');

// Get filtered text from POST
$title = getVar('post', 'title', 'text');

// Get HTML content (filtered)
$content = getVar('post', 'content', 'html');

// Get email with validation
$email = getVar('post', 'email', 'email');

// Get URL with validation
$website = getVar('post', 'website', 'url');

// Get variable name (alphanumeric + underscore)
$module = getVar('get', 'name', 'var');
```

**Input Types:**
- `num` - Numbers only
- `text` - Safe text (HTML escaped)
- `html` - Filtered HTML content
- `email` - Valid email addresses
- `url` - Valid URLs
- `var` - Variable names (a-z, A-Z, 0-9, -, _)

### Constants and Globals

```php
// Core constants
define('MODULE_FILE', true);    // Module context
define('ADMIN_FILE', true);     // Admin context
define('BLOCK_FILE', true);     // Block context
define('FUNC_FILE', true);      // Function context

// Directory constants
define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__)));
define('CONFIG_DIR', BASE_DIR.'/config');
define('UPLOADS_DIR', BASE_DIR.'/uploads');
define('CACHE_DIR', BASE_DIR.'/storage/cache');
define('LOGS_DIR', BASE_DIR.'/storage/logs');

// Global variables
global $db;           // Database connection
global $prefix;       // Table prefix
global $user;         // Current user info
global $conf;         // Configuration array
global $currentlang;  // Current language
```

## 🗄️ Database API

### Connection and Queries

```php
global $db, $prefix;

// Basic query execution
$result = $db->sql_query("SELECT * FROM {$prefix}_table");

// Fetch single row as array
$row = $db->sql_fetchrow($result);

// Fetch single row as associative array
$assoc_row = $db->sql_fetchassoc($result);

// Get number of rows
$num_rows = $db->sql_numrows($result);

// Get last insert ID
$insert_id = $db->sql_insertid();

// Escape string (deprecated - use prepared statements)
$safe_string = $db->sql_escape_string($string);
```

### Prepared Statements (Recommended)

```php
// SELECT with prepared statement
$stmt = $db->prepare("SELECT id, title, content FROM {$prefix}_news WHERE category = ? AND active = ?");
$stmt->bind_param("ii", $category_id, $active);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    echo $row['title'];
}

// INSERT with prepared statement
$stmt = $db->prepare("INSERT INTO {$prefix}_news (title, content, author_id, created) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("ssi", $title, $content, $author_id);
$stmt->execute();
$new_id = $stmt->insert_id;

// UPDATE with prepared statement
$stmt = $db->prepare("UPDATE {$prefix}_news SET title = ?, content = ? WHERE id = ?");
$stmt->bind_param("ssi", $title, $content, $id);
$stmt->execute();
$affected_rows = $stmt->affected_rows;

// DELETE with prepared statement
$stmt = $db->prepare("DELETE FROM {$prefix}_news WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
```

### Transaction Support

```php
// Begin transaction
$db->begin_transaction();

try {
    // Multiple database operations
    $stmt1 = $db->prepare("INSERT INTO {$prefix}_table1 (data) VALUES (?)");
    $stmt1->bind_param("s", $data1);
    $stmt1->execute();
    
    $stmt2 = $db->prepare("UPDATE {$prefix}_table2 SET status = ? WHERE id = ?");
    $stmt2->bind_param("si", $status, $id);
    $stmt2->execute();
    
    // Commit transaction
    $db->commit();
    echo "Transaction completed successfully";
    
} catch (Exception $e) {
    // Rollback on error
    $db->rollback();
    echo "Transaction failed: " . $e->getMessage();
}
```

## 👤 User Authentication API

### User State Functions

```php
// Check if user is logged in
if (is_user()) {
    // User is authenticated
}

// Check if user is administrator
if (is_admin()) {
    // User has admin privileges
}

// Check if user is super admin
if (is_admin_god()) {
    // User has highest privileges
}

// Check if user is module moderator
if (is_moder($module_name)) {
    // User can moderate specific module
}

// Check if request is from search bot
if (is_bot()) {
    // Request from search engine crawler
}
```

### User Information

```php
global $user;

// User data array structure:
// $user[0] = User ID
// $user[1] = Username
// $user[2] = Email
// $user[3] = User group/role
// $user[4] = Avatar filename
// $user[5] = Signature

// Get current user ID
$user_id = is_user() ? intval($user[0]) : 0;

// Get user information by ID
function get_user_data($user_id) {
    global $db, $prefix;
    $stmt = $db->prepare("SELECT user_id, user_name, user_email, user_group, user_avatar FROM {$prefix}_users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
```

### Session Management

```php
// Set secure cookie
setCookies($name, $value, $expire_time);

// Get cookie value
$value = getCookies($name);

// Get client IP address
$ip = getIp();

// Get user agent
$user_agent = getUserAgent();

// Get hostname
$hostname = get_host();
```

### Access Control

```php
// Check access level
function is_acess($access_string) {
    // Access string format: "1,2,3" (comma-separated group IDs)
    // Returns true if user belongs to any of the specified groups
}

// Check module-specific permissions
function check_module_access($module, $operation = 'view') {
    global $user;
    
    // Get module configuration
    $module_config = get_module_config($module);
    $required_level = $module_config[$operation.'_access'];
    
    switch($required_level) {
        case 0: return true;                    // Public access
        case 1: return is_user();               // Registered users
        case 2: return is_moder($module);       // Module moderators
        case 3: return is_admin();              // Administrators
        default: return false;
    }
}
```

## 🎨 Template API

### Basic Template Functions

```php
// Include theme files
function setThemeInclude() {
    global $theme;
    $theme = get_theme();
    include_once('templates/'.$theme.'/index.php');
    include_once('core/template.php');
}

// Get current theme
$current_theme = get_theme();

// Basic template rendering
function setTemplateBasic($type, $values = array()) {
    // $type: 'title', 'content', 'pagination', etc.
    // $values: array of placeholder => value pairs
}

// Warning/message templates
function setTemplateWarning($type, $values = array()) {
    // $type: 'info', 'warn', 'error', 'success'
    // $values: message parameters
}
```

### Template Usage Examples

```php
// Page title
echo setTemplateBasic('title', array('{%title%}' => 'Page Title'));

// Content block
$content = setTemplateBasic('content', array(
    '{%title%}' => $title,
    '{%content%}' => $content,
    '{%author%}' => $author,
    '{%date%}' => format_time($timestamp)
));

// Success message
echo setTemplateWarning('success', array(
    'text' => 'Operation completed successfully',
    'url' => 'index.php?name=module',
    'time' => '3'
));

// Error message
echo setTemplateWarning('error', array(
    'text' => 'An error occurred',
    'url' => 'javascript:history.back()',
    'time' => '5'
));
```

### Page Structure

```php
// Start HTML output (header, navigation)
head();

// Your content here
echo '<h1>Module Content</h1>';
echo '<p>Your module content...</p>';

// End HTML output (footer)
foot();
```

### Navigation and Pagination

```php
// Generate page numbers
function setPageNumbers($current, $module, $total_items, $total_pages, $per_page, $param = '', $max_links = 8, $anchor = '') {
    // Returns pagination HTML
}

// Example usage
$page = getVar('get', 'page', 'num', 1);
$per_page = 10;
$total_items = 150;
$total_pages = ceil($total_items / $per_page);

$pagination = setPageNumbers($page, 'news', $total_items, $total_pages, $per_page);
echo $pagination;

// Lower navigation (back, home, top)
echo setNaviLower('module_name');
```

## 📁 File and Upload API

### File Upload

```php
// Upload function
function upload($type, $directory, $max_size, $allowed_types, $module, $create_thumb = 0, $user_folders = 0, $user_id = 0) {
    // $type: 1 = image, 2 = file
    // $directory: upload directory
    // $max_size: max size in KB
    // $allowed_types: "jpg,png,gif" etc.
    // $module: module name
    // $create_thumb: create thumbnail (0/1)
    // $user_folders: use user-specific folders (0/1)
    // $user_id: user ID for folders
}

// Example usage
$upload_result = upload(
    1,                          // Image upload
    'uploads/news/',            // Directory
    2048,                       // 2MB max
    'jpg,png,gif',             // Allowed types
    'news',                     // Module
    1,                          // Create thumbnail
    0,                          // No user folders
    0                           // No specific user
);
```

### File Operations

```php
// Get file size in human readable format
function files_size($bytes) {
    if ($bytes >= 1073741824) {
        return round($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

// Find image in theme directories
function img_find($image_name, $default = '') {
    $paths = array(
        'uploads/'.$image_name,
        'templates/'.get_theme().'/images/'.$image_name,
        'templates/default/images/'.$image_name
    );
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    return $default;
}

// Secure file writing
function addFile($file_path, $content, $compression = 'none', $delete_source = false, $mode = 'w', $max_size = 10485760) {
    // Write file with optional compression
    // Returns 0 on success, error code on failure
}
```

### Image Processing

```php
// Image resize and thumbnail creation
function create_thumbnail($source, $destination, $max_width, $max_height, $quality = 85) {
    // Implementation depends on GD extension
    $info = getimagesize($source);
    
    switch ($info['mime']) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($source);
            break;
        default:
            return false;
    }
    
    // Calculate new dimensions
    $width = $info[0];
    $height = $info[1];
    
    if ($width > $max_width || $height > $max_height) {
        $ratio = min($max_width / $width, $max_height / $height);
        $new_width = floor($width * $ratio);
        $new_height = floor($height * $ratio);
    } else {
        $new_width = $width;
        $new_height = $height;
    }
    
    // Create thumbnail
    $thumb = imagecreatetruecolor($new_width, $new_height);
    imagecopyresampled($thumb, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    
    // Save thumbnail
    switch ($info['mime']) {
        case 'image/jpeg':
            return imagejpeg($thumb, $destination, $quality);
        case 'image/png':
            return imagepng($thumb, $destination);
        case 'image/gif':
            return imagegif($thumb, $destination);
    }
    
    return false;
}
```

## 🔒 Security API

### Input Validation

```php
// Analyze string for security
function analyze($string) {
    return preg_replace('#[^a-zA-Z0-9_-]#', '', $string);
}

// XSS protection
function xss_clean($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Filter HTML content
function filter_html($html, $allowed_tags = '<p><br><strong><em><u><a><img><ul><ol><li>') {
    return strip_tags($html, $allowed_tags);
}
```

### CSRF Protection

```php
// Generate CSRF token
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// CSRF token form field
function csrf_token_field() {
    return '<input type="hidden" name="csrf_token" value="'.generate_csrf_token().'">';
}

// Usage in forms
echo '<form method="post">';
echo csrf_token_field();
echo '<input type="text" name="title">';
echo '<input type="submit" value="Submit">';
echo '</form>';

// Verification in processing
if (!verify_csrf_token(getVar('post', 'csrf_token', 'text'))) {
    die('CSRF token verification failed');
}
```

### Captcha Integration

```php
// Get captcha HTML
function getCaptcha($level) {
    global $conf;
    if ($conf['gfx_chk'] >= '1' && ($level == 2 || ($level == 1 && !is_user()))) {
        // Google reCAPTCHA v3
        $html = '<script src="https://www.google.com/recaptcha/api.js?render='.$conf['capkey'].'"></script>';
        $html .= '<script>
            grecaptcha.ready(function() {
                grecaptcha.execute("'.$conf['capkey'].'", {action: "submit"}).then(function(token) {
                    document.getElementById("recaptcha").value = token;
                });
            });
        </script>';
        $html .= '<input type="hidden" id="recaptcha" name="recaptcha">';
        return $html;
    }
    return '';
}

// Verify captcha
function checkCaptcha($level) {
    global $conf;
    if ($conf['gfx_chk'] >= '1' && ($level == 2 || ($level == 1 && !is_user()))) {
        $response = getVar('post', 'recaptcha', 'text');
        // Verify with Google API
        return verify_recaptcha($response);
    }
    return true;
}
```

## ⚡ Caching API

### Browser Caching

```php
// Set cache headers
function setCache($cache_level = '') {
    header('Content-Type: text/html; charset='._CHARSET);
    
    if ($cache_level === "1") {
        global $conf;
        $cache_days = (int) ($conf['cache_d'] ?? 7);
        $max_age = $cache_days * 86400;
        $expires = time() + $max_age;
        
        header('Cache-Control: public, max-age='.$max_age);
        header('Expires: '.gmdate('D, d M Y H:i:s', $expires).' GMT');
        header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
    } else {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: '.gmdate('D, d M Y H:i:s', time() - 3600).' GMT');
    }
    
    header('X-Powered-By: SLAED CMS');
}
```

### File Caching

```php
// Cache management functions
function cache_get($key, $default = null) {
    $cache_file = CACHE_DIR . '/' . md5($key) . '.cache';
    
    if (file_exists($cache_file)) {
        $cache_data = unserialize(file_get_contents($cache_file));
        
        if ($cache_data['expires'] > time()) {
            return $cache_data['data'];
        } else {
            unlink($cache_file);
        }
    }
    
    return $default;
}

function cache_set($key, $data, $ttl = 3600) {
    $cache_file = CACHE_DIR . '/' . md5($key) . '.cache';
    $cache_data = array(
        'data' => $data,
        'expires' => time() + $ttl
    );
    
    return file_put_contents($cache_file, serialize($cache_data), LOCK_EX) !== false;
}

function cache_delete($key) {
    $cache_file = CACHE_DIR . '/' . md5($key) . '.cache';
    return file_exists($cache_file) ? unlink($cache_file) : true;
}

// Usage example
$cache_key = 'user_data_' . $user_id;
$user_data = cache_get($cache_key);

if ($user_data === null) {
    // Data not in cache, fetch from database
    $user_data = get_user_data($user_id);
    cache_set($cache_key, $user_data, 1800); // Cache for 30 minutes
}
```

## 🔧 Utility Functions

### String Operations

```php
// Cut string to specified length
function cutstr($string, $length, $suffix = '...') {
    if (mb_strlen($string) > $length) {
        return mb_substr($string, 0, $length) . $suffix;
    }
    return $string;
}

// Transliteration
function getTranslit($string, $lowercase = true) {
    $translit_table = array(
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
        'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
        'ш' => 'sh', 'щ' => 'shch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya'
    );
    
    $string = strtr($string, $translit_table);
    if ($lowercase) {
        $string = mb_strtolower($string);
    }
    
    return preg_replace('#[^a-zA-Z0-9]#', '', $string);
}

// Generate random string
function generate_random_string($length = 32) {
    return bin2hex(random_bytes($length / 2));
}
```

### Date and Time

```php
// Format timestamp
function format_time($timestamp, $format = 'Y-m-d H:i:s') {
    return date($format, $timestamp);
}

// Check if date is valid
function isDate($date_string) {
    return is_numeric(strtotime($date_string));
}

// Time ago function
function time_ago($timestamp, $language = 'en') {
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes != 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours != 1 ? 's' : '') . ' ago';
    } else {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days != 1 ? 's' : '') . ' ago';
    }
}
```

### Validation

```php
// Check if positive integer
function isInt($value) {
    $int_value = (int)$value;
    return ($int_value == $value && is_int($int_value) && $value > 0);
}

// Email validation
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// URL validation
function is_valid_url($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

// Strong password check
function check_password_strength($password) {
    $strength = 0;
    
    if (strlen($password) >= 8) $strength++;           // Length
    if (preg_match('/[a-z]/', $password)) $strength++; // Lowercase
    if (preg_match('/[A-Z]/', $password)) $strength++; // Uppercase
    if (preg_match('/[0-9]/', $password)) $strength++; // Numbers
    if (preg_match('/[^a-zA-Z0-9]/', $password)) $strength++; // Special chars
    
    return $strength; // 0-5 scale
}
```

## 🔗 URL and SEO API

### URL Generation

```php
// Generate SEO-friendly URLs
function getHref($parameters) {
    global $conf;
    
    if ($conf['rewrite']) {
        // Apply URL rewriting rules
        include('config/config_rewrite.php');
        return apply_rewrite_rules($parameters);
    } else {
        // Standard query string
        return 'index.php?' . http_build_query($parameters);
    }
}

// Usage examples
$news_url = getHref(array('name' => 'news', 'op' => 'view', 'id' => 123));
$category_url = getHref(array('name' => 'news', 'cat' => 5));
```

### Meta Tags

```php
// Generate meta tags
function generate_meta_tags($title, $description = '', $keywords = '', $image = '') {
    global $conf;
    
    $meta = '';
    
    // Basic meta tags
    $meta .= '<title>' . htmlspecialchars($title) . '</title>' . "\n";
    
    if ($description) {
        $meta .= '<meta name="description" content="' . htmlspecialchars($description) . '">' . "\n";
    }
    
    if ($keywords) {
        $meta .= '<meta name="keywords" content="' . htmlspecialchars($keywords) . '">' . "\n";
    }
    
    // Open Graph tags
    $meta .= '<meta property="og:title" content="' . htmlspecialchars($title) . '">' . "\n";
    $meta .= '<meta property="og:url" content="' . $conf['homeurl'] . $_SERVER['REQUEST_URI'] . '">' . "\n";
    
    if ($description) {
        $meta .= '<meta property="og:description" content="' . htmlspecialchars($description) . '">' . "\n";
    }
    
    if ($image) {
        $meta .= '<meta property="og:image" content="' . $conf['homeurl'] . '/' . $image . '">' . "\n";
    }
    
    return $meta;
}
```

## 📊 Events and Hooks API

### Hook System

```php
// Global hooks storage
global $hooks;

// Register hook
function add_hook($event, $callback, $priority = 10) {
    global $hooks;
    
    if (!isset($hooks[$event])) {
        $hooks[$event] = array();
    }
    
    if (!isset($hooks[$event][$priority])) {
        $hooks[$event][$priority] = array();
    }
    
    $hooks[$event][$priority][] = $callback;
}

// Trigger event
function trigger_event($event, $data = null) {
    global $hooks;
    
    if (isset($hooks[$event])) {
        ksort($hooks[$event]); // Sort by priority
        
        foreach ($hooks[$event] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (is_callable($callback)) {
                    $data = call_user_func($callback, $data);
                }
            }
        }
    }
    
    return $data;
}

// Usage examples
add_hook('user_login', 'my_login_handler', 10);
add_hook('content_save', 'my_save_handler', 5);

// Trigger events
$user_data = trigger_event('user_login', $user_data);
$content = trigger_event('content_save', $content);
```

### Standard Events

```php
// Available system events:
'system_init'           // System initialization
'user_login'            // User login
'user_logout'           // User logout
'user_register'         // User registration
'content_save'          // Content save
'content_delete'        // Content deletion
'module_init'           // Module initialization
'theme_load'            // Theme loading
'page_render'           // Page rendering
'admin_action'          // Admin panel action
```

---

This API documentation provides a comprehensive reference for developing with SLAED CMS. For specific module examples and advanced usage, see the **[[Module Development|Module-Development]]** guide.