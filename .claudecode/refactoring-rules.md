# SLAED CMS - Refactoring Rules for PHP 8.4+ & MySQL 8.0+

**Author:** Eduard Laas
**Project:** SLAED CMS 6.3
**Target:** PHP 8.4+ & MySQL 8.0+ / MariaDB 10+
**Purpose:** Systematic code modernization without hallucinations
**Based on:** Real code migration patterns and verified refactoring examples

---

## 1. General Refactoring Principles

### 1.1 Safety First
- ✅ **Always read the file before editing**
- ✅ **Test after each change** (if possible)
- ✅ **One logical change per commit**
- ✅ **Preserve backward compatibility** unless explicitly breaking
- ✅ **Document breaking changes** in commit messages

### 1.2 What NOT to Do (Anti-Hallucination Rules)
- ❌ **NEVER** invent functions that don't exist
- ❌ **NEVER** assume database table structure without checking
- ❌ **NEVER** add features not requested
- ❌ **NEVER** rename variables/functions without verification
- ❌ **NEVER** remove code without understanding its purpose
- ❌ **NEVER** guess configuration values

### 1.3 Verification Steps
1. **Read existing code first** - understand what it does
2. **Check dependencies** - what calls this function?
3. **Search for usage** - grep for function/variable names
4. **Verify constants** - check language files for constants
5. **Test logic** - ensure refactored code does the same thing

---

## 2. PHP 8.4+ Migration Rules

### 2.1 Type Declarations (Strict Types)

**Add to new files:**
```php
<?php
declare(strict_types=1);
```

**Function signatures - BEFORE:**
```php
function getUserById($id) {
    // ...
}
```

**Function signatures - AFTER:**
```php
function getUserById(int $id): array {
    // ...
}
```

**Type hierarchy:**
- `int` - integers only
- `string` - strings only
- `bool` - boolean only
- `array` - arrays only
- `mixed` - any type (use sparingly)
- `void` - no return value
- `?type` - nullable (e.g., `?int`)
- `type1|type2` - union types (PHP 8.0+)

### 2.2 Remove func_get_args() Pattern

**❌ NEVER use func_get_args() - it's outdated!**

Since PHP 5.6+, use the **spread operator (...)** for variadic functions.

**BEFORE (bad - func_get_args):**
```php
function doSomething() {
    $args = func_get_args();
    $param1 = $args[0] ?? '';
    $param2 = $args[1] ?? 0;
    // ...
}
```

**OPTION 1 - Explicit parameters (preferred):**
```php
function doSomething(string $param1 = '', int $param2 = 0): void {
    // ...
}
```

**OPTION 2 - Variadic parameters (when truly needed):**
```php
function doSomething(string ...$params): void {
    // $params is a typed array of strings
    foreach ($params as $param) {
        // ...
    }
}

// Or with mixed types
function doSomethingMixed(mixed ...$args): void {
    // $args contains all arguments
}
```

**When to use spread operator:**
- Unknown number of arguments
- Same type for all arguments
- Need to pass arguments to another function

**Example with spread:**
```php
function sumNumbers(int ...$numbers): int {
    return array_sum($numbers);
}

// Usage:
sumNumbers(1, 2, 3, 4, 5); // Returns 15
```

### 2.3 Quote Style Consistency

**ALWAYS use single quotes (') for strings**

**BEFORE (double quotes):**
```php
if (!defined("ADMIN_FILE")) die("Illegal file access");
$ops = array("admins_show", "admins_add");
```

**AFTER (single quotes):**
```php
if (!defined('ADMIN_FILE')) die('Illegal file access');
$ops = ['admins_show', 'admins_add'];
```

**Rule:** Replace ALL `"..."` with `'...'` except when necessary (e.g., escaped quotes inside)

### 2.4 Array Syntax Modernization

**Use short array syntax [] instead of array()**

**BEFORE:**
```php
$ops = array("admins_show", "admins_add", "admins_info");
$lang = array(_HOME, _ADD, _INFO);
$stop = array();
```

**AFTER:**
```php
$ops = ['admins_show', 'admins_add', 'admins_info'];
$lang = [_HOME, _ADD, _INFO];
$stop = [];
```

**Rule:** Replace `array(...)` with `[...]` everywhere

### 2.5 Array Access with Proper Checks

**BEFORE (risky):**
```php
$value = $_GET['key'];
```

**AFTER (safe):**
```php
$value = getVar('get', 'key', 'var', '');
// or
$value = $_GET['key'] ?? '';
```

### 2.6 Input Validation with getVar()

**Replace direct $_GET/$_POST/$_REQUEST access with getVar() helper**

**BEFORE (unsafe):**
```php
if (isset($_REQUEST['id'])) {
    $id = intval($_REQUEST['id']);
}
$aid = isset($_POST['aid']) ? $_POST['aid'] : "";
$name = isset($_POST['name']) ? $_POST['name'] : "";
$url = isset($_POST['url']) ? $_POST['url'] : "http://";
```

**AFTER (safe):**
```php
$id = getVar('req', 'id', 'num');
$aid = getVar('post', 'aid', 'num', '');
$name = getVar('post', 'name', 'name', '');
$url = getVar('post', 'url', 'url', 'https://');
```

**Rules:**
- Use `getVar('get', 'key', 'type', 'default')` for $_GET
- Use `getVar('post', 'key', 'type', 'default')` for $_POST
- Use `getVar('req', 'key', 'type', 'default')` for $_REQUEST
- Always specify type: 'num', 'name', 'url', 'text', 'var', 'bool'

**Type mapping:**
- `'num'` - integers
- `'name'` - usernames (25 chars max)
- `'title'` - titles (filtered)
- `'url'` - URLs
- `'text'` - long text
- `'var'` - variables
- `'bool'` - boolean

**Array inputs (checkboxes, multi-select):**

**BEFORE:**
```php
$modules = isset($_POST['amodules']) ? implode(",", $_POST['amodules']) : "";
```

**AFTER:**
```php
$amodules = getVar('post', 'amodules[]', 'num') ?: [];
$modules = $amodules ? implode(',', $amodules) : '';
```

**Boolean values:**

**BEFORE:**
```php
$super = empty($_POST['super']) ? 0 : 1;
$smail = empty($_POST['smail']) ? 0 : 1;
```

**AFTER:**
```php
$super = getVar('post', 'super', 'bool') ? 1 : 0;
$smail = getVar('post', 'smail', 'bool') ? 1 : 0;
```

**Text processing:**

**BEFORE:**
```php
$msg = nl2br(bb_decode(str_replace("[pass]", $pwd, str_replace("[login]", $name, $_POST['mailtext'])), "account"), false);
```

**AFTER:**
```php
$mailtext = getVar('post', 'mailtext', 'text');
$msg = nl2br(bb_decode(str_replace('[pass]', $pwd, str_replace('[login]', $name, $mailtext)), 'account'), false);
```

**Rule:** Extract input with `getVar()` first, then process/transform the validated input. Keeps security and processing separate.

### 2.7 String Concatenation Rules

**Follow SLAED standards:**
```php
// ✅ Correct
$html = '<div class="'.$cls.'">'.$text.'</div>';
$sql = 'SELECT * FROM '.$prefix.'_table WHERE id='.$id;

// ❌ Wrong
$html = "<div class=\"{$cls}\">{$text}</div>";
$sql = "SELECT * FROM {$prefix}_table WHERE id={$id}";
```

**Rule:** No spaces around concatenation operator (`.`)

### 2.8 Remove Error Suppression Operator (@)

**NEVER use @ operator - handle errors properly**

**BEFORE (bad practice):**
```php
$dir = @opendir($path);
$content = @file_get_contents($file);
```

**AFTER (proper error handling):**
```php
$dir = opendir($path);
if ($dir === false) {
    // Handle error properly
    return;
}

$content = file_get_contents($file);
if ($content === false) {
    // Handle error
}
```

**Rule:** Remove @ and check return values explicitly

### 2.9 Compact Code Style (One-liners)

**For simple conditions, use compact syntax**

**BEFORE (verbose):**
```php
foreach ($files as $file) {
    if ($file !== '.' && $file !== '..' && is_file($file)) {
        $mod[] = $file;
    }
}

if (is_dir($path.'/language')) {
    $eadmin = '<a href="...">'._ADMIN.'</a>';
}
```

**AFTER (compact):**
```php
foreach ($files as $file) {
    if ($file !== '.' && $file !== '..' && is_file($file)) $mod[] = $file;
}

if (is_dir($path.'/language')) $eadmin = '<a href="...">'._ADMIN.'</a>';
```

**Rule:** Simple single-statement conditions can be one-liners (max 120 chars)

### 2.10 Config File Modernization

**Use new config helper functions and naming**

**BEFORE:**
```php
include('config/config_lang.php');
$permtest = end_chmod('config/config_lang.php', 666);
if ($permtest) $cont .= setTemplateWarning(...);
```

**AFTER:**
```php
require_once CONFIG_DIR.'/lang.php';
checkConfigFile('lang.php');
```

**Config file naming:**
- `config_lang.php` → `lang.php`
- `config_users.php` → `users.php`
- `config_fields.php` → `fields.php`

**Rule:**
- Use `require_once CONFIG_DIR.'/filename.php'` for includes
- Use `checkConfigFile('filename.php')` to verify permissions
- Remove `config_` prefix from new config files

### 2.11 Modern PHP Features to Use

**Null coalescing:**
```php
$value = $data['key'] ?? 'default';
```

**Null coalescing assignment:**
```php
$config['setting'] ??= 'default_value';
```

**Spaceship operator:**
```php
usort($array, fn($a, $b) => $a <=> $b);
```

**Arrow functions (simple cases):**
```php
$filtered = array_filter($arr, fn($x) => $x > 0);
```

---

## 3. Database (MySQL 8.0+ / MariaDB 10+) Rules

### 3.1 Always Use Prepared Statements

**ALWAYS use prepared statements with named placeholders**

**BEFORE (VULNERABLE):**
```php
$result = $db->sql_query("SELECT id, name FROM ".$prefix."_admins WHERE id = '".$id."'");
$db->sql_query("DELETE FROM ".$prefix."_admins WHERE id = '".$id."'");
$db->sql_query("UPDATE ".$prefix."_admins SET name = '".$name."', email = '".$email."' WHERE id = '".$aid."'");
```

**AFTER (SECURE):**
```php
$result = $db->sql_query('SELECT id, name FROM '.$prefix.'_admins WHERE id = :id', ['id' => $id]);
$db->sql_query('DELETE FROM '.$prefix.'_admins WHERE id = :id', ['id' => $id]);
$db->sql_query('UPDATE '.$prefix.'_admins SET name = :name, email = :email WHERE id = :id', [
    'name' => $name, 'email' => $email, 'id' => $aid
]);
```

**Rules:**
1. Use `:placeholder` syntax in SQL
2. Pass values as second parameter array
3. Never concatenate variables into SQL strings
4. Use single quotes for SQL strings

**INSERT statements:**

**BEFORE:**
```php
INSERT INTO table (id, name, email) VALUES (NULL, '".$name."', '".$email."')
```

**AFTER:**
```php
INSERT INTO table (name, email) VALUES (:name, :email)
// Pass: ['name' => $name, 'email' => $email]
```

**Note:** Remove `id` column from INSERT if it's auto-increment, remove `NULL` value

### 3.2 UTF8MB4 Collation

**All tables must use:**
```sql
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci
```

**Check in queries:**
```php
// Verify charset in connection (already in classes/mysqli.php)
$this->sqlconnid->set_charset('utf8mb4');
```

### 3.3 InnoDB Engine Required

**All new tables:**
```sql
ENGINE=InnoDB
```

**Benefits:**
- ACID compliance
- Foreign keys support
- Better crash recovery
- Row-level locking

---

## 4. Security Hardening

### 4.1 XSS Prevention

**Output escaping:**
```php
// ✅ Always escape user data in HTML
echo htmlspecialchars($user_input, ENT_QUOTES, 'UTF-8');

// ✅ Use existing SLAED functions
echo check_html($text); // Already filters
```

### 4.2 SQL Injection Prevention

**Use prepared statements (see 3.1)**

**Additional validation:**
```php
// ✅ Type validation
$id = (int)$_GET['id']; // Force integer
$page = max(1, (int)$_GET['page']); // Positive integer

// ✅ Use getVar() helper
$id = getVar('get', 'id', 'num', 0);
```

### 4.3 CSRF Protection

**Check for CSRF tokens in forms:**
```php
// Token generation (existing in SLAED)
$token = generateToken();

// Token validation
if (!validateToken($_POST['token'])) {
    die('CSRF token invalid');
}
```

---

## 5. Function Naming Rules (SLAED Standard)

### 5.1 Mandatory Verbs

**All functions MUST start with:**
- `get` - retrieve data
- `set` - set/save data
- `add` - create new entity
- `update` - modify existing entity
- `delete` - remove entity
- `is` - boolean check
- `check` - validation/verification
- `filter` - data filtering/sanitization

### 5.2 Naming Pattern

**Format:** `verbNoun()`

**Examples:**
```php
function getUserById(int $id): array {}
function setConfig(string $file, array $data): bool {}
function isUserActive(int $id): bool {}
function checkPermission(string $perm): bool {}
function filterInput(string $data): string {}
```

### 5.3 Return Types by Verb

| Verb | Expected Return | Required? |
|------|----------------|-----------|
| get | array/string/object | YES |
| is | bool | YES |
| check | bool/array | YES |
| filter | filtered data | YES |
| set/add/update/delete | bool/int/void | NO |

---

## 6. Configuration Files Refactoring

### 6.1 Naming Convention

**OLD naming:**
```
config/config_fields.php
config/config_users.php
```

**NEW naming (shorter):**
```
config/fields.php
config/users.php
```

**Update all includes:**
```php
// ✅ Use constants
require_once CONFIG_DIR.'/fields.php';

// ❌ Avoid hardcoded paths
require_once 'config/config_fields.php';
```

### 6.2 Configuration Arrays

**Consistent naming:**
```php
// ✅ Prefix with 'conf'
$conffi = []; // fields config
$confu = [];  // users config
$confla = []; // language config

// ❌ Avoid generic names
$config = [];
$settings = [];
```

---

## 7. Code Quality Standards

### 7.1 Line Length & Formatting

- Max 120 characters per line
- 4 spaces indentation (NO tabs)
- UTF-8 encoding
- LF line endings (\n)

### 7.2 Copyright Headers

**ALWAYS update copyright year to current range**

**BEFORE:**
```php
# Copyright © 2005 - 2017 SLAED
```

**AFTER:**
```php
# Copyright © 2005 - 2026 SLAED
```

**Rule:** Change end year to 2026 or current year + 1

### 7.3 Switch Statement Formatting

**Consistent switch formatting**

**BEFORE (mixed style with tabs):**
```php
switch ($op) {
	case "admins_show":
	admins_show();
	break;

	case "admins_add":
	admins_add();
	break;
}
```

**AFTER (consistent with 4 spaces):**
```php
switch ($op) {
    case 'admins_show':
    admins_show();
    break;

    case 'admins_add':
    admins_add();
    break;
}
```

**Rules:**
- Single quotes for case values
- 4 spaces indentation (not tabs)
- Empty line between cases (optional but cleaner)

### 7.4 Closing PHP Tag

**Remove closing ?> tag**

**BEFORE:**
```php
	case "admins_info":
	admins_info();
	break;
}
?>
```

**AFTER:**
```php
    case 'admins_info':
    admins_info();
    break;
}
```

**Rule:** Files containing only PHP code should NOT have closing `?>` tag (prevents accidental whitespace output)

### 7.5 Comments

**File headers:**
```php
<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net
```

**Function comments (when needed):**
```php
# Retrieve user data by ID
# @param int $id User ID
# @return array User data or empty array
function getUserById(int $id): array {
    // ...
}
```

**Inline comments:**
```php
// Use English for code comments
// Explain WHY, not WHAT
```

---

## 8. Refactoring Workflow (Step-by-Step)

### 8.1 Before Starting

1. **Read the target file completely**
2. **Search for function usage across codebase**
   ```bash
   grep -r "functionName" .
   ```
3. **Check for dependencies**
4. **Note any global variables used**
5. **Identify database queries**

### 8.2 During Refactoring

1. **Add type hints** to function parameters
2. **Add return types**
3. **Replace func_get_args()** with explicit parameters
4. **Update SQL queries** to use prepared statements
5. **Add input validation** with getVar()
6. **Update variable names** if needed (follow rules)
7. **Keep string concatenation** without spaces
8. **Verify all constants exist** in language files

### 8.3 After Refactoring

1. **Test the functionality** (if possible)
2. **Check for syntax errors** with PHP
   ```bash
   php -l filename.php
   ```
3. **Verify no breaking changes**
4. **Create detailed commit message**

---

## 9. Common Patterns to Refactor

### 9.1 Old Pagination Pattern

**BEFORE:**
```php
function setArticleNumbers() {
    $arg = func_get_args();
    $name = $arg[0];
    $mod = $arg[1];
    // ...
}
```

**AFTER:**
```php
function setArticleNumbers(
    string $name,
    string $mod,
    int $limit,
    string $url,
    string $cntfld,
    string $tbl,
    string $catfld = '',
    string $where = '',
    int $maxpg = 10,
    array $params = []
): string {
    // ...
}
```

### 9.2 Old Navigation Pattern

**BEFORE:**
```php
function module_navi() {
    $narg = func_get_args();
    return navi_gen('Title', 'icon.png', '', $ops, $lang, '', '',
                    $narg[0], $narg[1], $narg[2], $narg[3]);
}
```

**AFTER:**
```php
function module_navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    return navi_gen('Title', 'icon.png', '', $ops, $lang, '', '',
                    $opt, $tab, $subtab, $legacy);
}
```

### 9.3 Old Array Access Pattern

**BEFORE:**
```php
$mod_dir = isset($_GET['mod_dir']) ? $_GET['mod_dir'] : '';
$lng_wh = isset($_GET['lng_wh']) ? $_GET['lng_wh'] : '';
```

**AFTER:**
```php
$mod_dir = getVar('get', 'mod_dir', 'var', '');
$lng_wh = getVar('get', 'lng_wh', 'var', '');
```

---

## 10. Real-World Migration Examples

### 10.1 Complete Function Transformation

**Navigation Function Evolution:**

**BEFORE:**
```php
function admins_navi() {
    panel();
    $narg = func_get_args();
    $ops = array("admins_show", "admins_add", "admins_info");
    $lang = array(_HOME, _ADD, _INFO);
    return navi_gen(_EDITADMINS, "admins.png", "", $ops, $lang, "", "", $narg[0], $narg[1], $narg[2], $narg[3]);
}
```

**AFTER:**
```php
function admins_navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    panel();
    $ops = ['admins_show', 'admins_add', 'admins_info'];
    $lang = [_HOME, _ADD, _INFO];
    return getAdminTabs(_EDITADMINS, 'admins.png', '', $ops, $lang, [], [], $tab, $subtab);
}
```

**Changes applied:**
1. ✅ Removed `func_get_args()` → explicit typed parameters
2. ✅ Added return type `: string`
3. ✅ Double quotes → single quotes
4. ✅ `array()` → `[]` syntax
5. ✅ Function rename: `navi_gen()` → `getAdminTabs()` (verb+noun pattern)
6. ✅ Direct parameter usage instead of `$narg[0]`, `$narg[1]`, etc.

### 10.2 Template Function Modernization

**BEFORE:**
```php
$cont .= tpl_eval("open");
$cont .= tpl_warn("warn", _MAIL_SEND, "", "", "info");
$cont .= tpl_eval("close", "");
```

**AFTER:**
```php
$cont .= setTemplateBasic('open');
$cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _MAIL_SEND]);
$cont .= setTemplateBasic('close');
```

**Function name changes:**
- `tpl_eval()` → `setTemplateBasic()`
- `tpl_warn()` → `setTemplateWarning()`

**Parameter changes:**
- String parameters → array with named keys
- More explicit parameter naming

### 10.3 Conditional Checks Modernization

**BEFORE:**
```php
if (isset($_GET['send'])) $cont .= tpl_warn("warn", _MAIL_SEND, "", "", "info");
```

**AFTER:**
```php
if (getVar('get', 'send', 'num')) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _MAIL_SEND]);
```

**Changes:**
- `isset($_GET['key'])` → `getVar('get', 'key', 'type')`
- Old template function → new template function
- Consistent quote style

### 10.4 Complete Transformation Summary

**Input Validation Evolution:**
```php
// OLD
$name = isset($_POST['name']) ? $_POST['name'] : "";

// NEW
$name = getVar('post', 'name', 'name', '');
```

**SQL Security Evolution:**
```php
// OLD (VULNERABLE)
$db->sql_query("SELECT * FROM ".$prefix."_admins WHERE id = '".$id."'");

// NEW (SECURE)
$db->sql_query('SELECT * FROM '.$prefix.'_admins WHERE id = :id', ['id' => $id]);
```

---

## 11. Testing & Validation

### 11.1 Syntax Check
```bash
php -l path/to/file.php
```

### 11.2 Look for Common Issues
- Undefined variables
- Undefined constants (check language files)
- Missing semicolons
- Incorrect quotes (' vs ")
- SQL syntax errors

### 11.3 Functional Testing
- Test in browser if web module
- Check admin panel functionality
- Verify database operations
- Test with different user roles

---

## 11. Git Commit Strategy

### 11.1 Commit Size
- One module per commit (e.g., admin/modules/lang.php)
- Or one logical feature per commit
- NEVER mix unrelated changes

### 11.2 Commit Message Template
```
Brief summary (50-72 chars)

Detailed description of refactoring:
- What was changed (specific functions/patterns)
- Why it was changed (PHP 8.4 compatibility, security, etc.)
- How it improves the code (type safety, performance, etc.)

Technical details:
- Functions refactored: list function names
- Type hints added: parameter and return types
- Security improvements: prepared statements, validation, etc.
- Breaking changes: none / list if any

Testing:
- Syntax check passed
- Functional testing: describe what was tested

Impact:
- Performance: neutral / improved by X%
- Security: enhanced (list improvements)
- Compatibility: full backward compatibility maintained

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>
```

---

## 12. Special Cases & Edge Cases

### 12.1 Global Variables
```php
// ✅ Document global usage
function getConfig(): array {
    global $conf, $prefix, $db;
    // ...
}
```

### 12.2 Include/Require Paths
```php
// ✅ Use constants (defined in core.php)
require_once CONFIG_DIR.'/fields.php';
require_once BASE_DIR.'/core/security.php';

// ❌ Avoid relative paths
require_once '../config/fields.php';
```

### 12.3 Mixed Return Types
```php
// If function can return different types, use union types
function getData(int $id): array|false {
    if (!$id) return false;
    return ['id' => $id, 'data' => '...'];
}
```

---

## 13. Common Mistakes to Avoid

❌ **Don't mix old and new styles:**
```php
// BAD - mixed quotes
$arr = array('item1', "item2");

// GOOD - consistent
$arr = ['item1', 'item2'];
```

❌ **Don't forget parameter types:**
```php
// BAD
function doSomething($id) { }

// GOOD
function doSomething(int $id): void { }
```

❌ **Don't leave SQL vulnerable:**
```php
// BAD
"WHERE id = '".$id."'"

// GOOD
'WHERE id = :id', ['id' => $id]
```

❌ **Don't forget array type for getVar():**
```php
// BAD - might fail if not array
$modules = getVar('post', 'amodules[]', 'num');

// GOOD - ensures array type
$amodules = getVar('post', 'amodules[]', 'num') ?: [];
```

---

## 14. Complete Migration Checklist

When migrating any old file to new standards:

**Code Style:**
- [ ] Update copyright year (2005 - 2026)
- [ ] Change all `"..."` to `'...'`
- [ ] Change all `array(...)` to `[...]`
- [ ] Verify 4-space indentation (no tabs)
- [ ] Check max 120 chars per line
- [ ] Remove closing `?>` tag
- [ ] Remove all `@` error suppression operators
- [ ] Use compact one-liners for simple if/for/while (max 120 chars)

**Functions:**
- [ ] Add type hints to all function parameters
- [ ] Add return types to all functions (`: void`, `: string`, etc.)
- [ ] Remove all `func_get_args()` usage
- [ ] Rename functions to verb+noun pattern if needed

**Security:**
- [ ] Replace all `isset($_GET/$_POST)` with `getVar()`
- [ ] Convert all SQL queries to prepared statements
- [ ] Test for SQL injection vulnerabilities
- [ ] Validate all user inputs

**Modernization:**
- [ ] Rename template functions (tpl_eval → setTemplateBasic)
- [ ] Change http:// defaults to https://
- [ ] Update function calls to modern equivalents
- [ ] Update config includes: `include('config/config_X.php')` → `require_once CONFIG_DIR.'/X.php'`
- [ ] Use `checkConfigFile()` instead of `end_chmod()` for config permissions
- [ ] Rename config files: remove `config_` prefix where applicable

**Quality:**
- [ ] File read completely before editing
- [ ] Function usage searched in codebase
- [ ] Constants verified in language files
- [ ] String concatenation follows SLAED rules
- [ ] Variable names follow conventions (no camelCase)
- [ ] Comments in English
- [ ] Syntax check passed (php -l)
- [ ] Functional testing done (if applicable)
- [ ] Detailed commit message written
- [ ] No hallucinations (everything verified!)

---

## 15. Performance Considerations

**No performance degradation from these changes:**

- ✅ Type hints: Zero runtime cost (compile-time checks)
- ✅ Prepared statements: Actually FASTER (query plan caching)
- ✅ getVar(): Minimal overhead, huge security benefit
- ✅ Short array syntax: Identical performance to array()
- ✅ Single quotes: Slightly faster (no variable interpolation parsing)

**Benefits:**
- Much more secure (SQL injection prevention)
- Better IDE autocomplete and error detection
- Easier maintenance and debugging
- Modern PHP 8.4 compatibility

---

## 16. When in Doubt

**ASK the developer instead of guessing!**

Questions to ask:
- "Should this function return array or false on failure?"
- "Is this constant defined in all 6 language files?"
- "Can I safely rename this variable, or is it used elsewhere?"
- "Should I preserve this legacy behavior or modernize it?"

**Better to ask than to hallucinate!**

---

## End Notes

**This document combines:**
- Refactoring methodology and best practices
- Real migration patterns from actual code modernization
- Anti-hallucination rules for AI-assisted refactoring
- Complete checklists for systematic modernization

**These rules ensure:**
- ✅ Consistent refactoring approach across all modules
- ✅ No breaking changes unless intended
- ✅ Full PHP 8.4+ and MySQL 8.0+ compatibility
- ✅ Enhanced security and type safety
- ✅ Maintainable, clean, production-ready code
- ✅ **Zero hallucinations** - everything verified before modification

**Every pattern is extracted from REAL migration work.**
**Every change has been verified and tested.**
**Follow these rules consistently across ALL SLAED CMS modules.**

Last updated: 2025-01-28
