# SLAED CMS - Refactoring Rules for PHP 8.4+ & MySQL 8.0+

**Author:** Eduard Laas
**Project:** SLAED CMS 6.3
**Target:** PHP 8.4+ & MySQL 8.0+ / MariaDB 10+
**Purpose:** Systematic code modernization without hallucinations

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

### 2.3 Array Access with Proper Checks

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

### 2.4 String Concatenation Rules

**Follow SLAED standards:**
```php
// ✅ Correct
$html = '<div class="'.$cls.'">'.$text.'</div>';
$sql = 'SELECT * FROM '.$prefix.'_table WHERE id='.$id;

// ❌ Wrong
$html = "<div class=\"{$cls}\">{$text}</div>";
$sql = "SELECT * FROM {$prefix}_table WHERE id={$id}";
```

### 2.5 Modern PHP Features to Use

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

**BEFORE (vulnerable):**
```php
$sql = "SELECT * FROM users WHERE id=".$id;
$result = $db->sql_query($sql);
```

**AFTER (secure):**
```php
$sql = 'SELECT * FROM users WHERE id=?';
$result = $db->sql_query($sql, [$id]);
```

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

### 7.2 Comments

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

## 10. Testing & Validation

### 10.1 Syntax Check
```bash
php -l path/to/file.php
```

### 10.2 Look for Common Issues
- Undefined variables
- Undefined constants (check language files)
- Missing semicolons
- Incorrect quotes (' vs ")
- SQL syntax errors

### 10.3 Functional Testing
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

## 13. Checklist for Each Refactoring

- [ ] File read completely before editing
- [ ] Function usage searched in codebase
- [ ] Type hints added to all parameters
- [ ] Return type declared
- [ ] func_get_args() removed if present
- [ ] Prepared statements used for SQL
- [ ] Input validation with getVar() or filters
- [ ] Constants verified in language files
- [ ] String concatenation follows SLAED rules
- [ ] Variable names follow conventions (no camelCase)
- [ ] Code formatted: 4 spaces, max 120 chars
- [ ] Comments in English
- [ ] Syntax check passed (php -l)
- [ ] Functional testing done (if applicable)
- [ ] Detailed commit message written
- [ ] No hallucinations (everything verified!)

---

## 14. When in Doubt

**ASK the developer instead of guessing!**

Questions to ask:
- "Should this function return array or false on failure?"
- "Is this constant defined in all 6 language files?"
- "Can I safely rename this variable, or is it used elsewhere?"
- "Should I preserve this legacy behavior or modernize it?"

**Better to ask than to hallucinate!**

---

## End Notes

These rules ensure:
- ✅ Consistent refactoring approach
- ✅ No breaking changes unless intended
- ✅ Full PHP 8.4 compatibility
- ✅ Enhanced security and type safety
- ✅ Maintainable, clean code
- ✅ **Zero hallucinations**

Last updated: 2025-01-28
