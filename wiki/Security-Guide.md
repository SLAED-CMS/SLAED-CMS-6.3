# Security Guide

SLAED CMS is built with security as a core principle. This guide covers essential security practices and configurations to protect your website.

## 🛡️ Security Architecture

SLAED CMS implements multiple layers of security:

```
┌─────────────────────────────────────────┐
│        Web Server Security             │ ← Firewall, SSL, Headers
├─────────────────────────────────────────┤
│        Application Security            │ ← Input validation, CSRF, XSS
├─────────────────────────────────────────┤
│        Authentication Security         │ ← Strong passwords, sessions
├─────────────────────────────────────────┤
│        Database Security               │ ← Prepared statements, encryption
├─────────────────────────────────────────┤
│        File System Security            │ ← Permissions, upload filtering
└─────────────────────────────────────────┘
```

## 🔒 Essential Security Configuration

### Post-Installation Security

**Immediately after installation:**

1. **Remove setup files:**
```bash
rm setup.php
rm -rf setup/
rm -rf install/
```

2. **Secure configuration files:**
```bash
chmod 600 config/*.php
chown www-data:www-data config/*.php
```

3. **Change default admin password:**
   - Use strong password (12+ characters)
   - Include uppercase, lowercase, numbers, symbols
   - Avoid common words or patterns

### File Permissions

**Recommended permissions:**

```bash
# General files and directories
find /path/to/slaed/ -type f -exec chmod 644 {} \;
find /path/to/slaed/ -type d -exec chmod 755 {} \;

# Writable directories
chmod 777 uploads/
chmod 777 storage/
chmod 777 config/cache/
chmod 777 config/logs/

# Secure sensitive files
chmod 600 config/config_db.php
chmod 600 config/000config_global.php

# No execute permissions on uploads
find uploads/ -name "*.php" -exec chmod 644 {} \;
```

### Web Server Security

**Apache (.htaccess):**

```apache
# Hide sensitive files
<FilesMatch "\.(conf|log|ini|sql|php~|bak|old)$">
    Require all denied
</FilesMatch>

# Protect config directory
<Directory "config">
    <Files "*.php">
        <RequireAll>
            Require all denied
            Require local
        </RequireAll>
    </Files>
</Directory>

# Prevent PHP execution in uploads
<Directory "uploads">
    <Files "*.php">
        Require all denied
    </Files>
    <Files "*.phtml">
        Require all denied
    </Files>
    php_flag engine off
</Directory>

# Security headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options SAMEORIGIN
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
</IfModule>

# Hide server information
ServerTokens Prod
ServerSignature Off
```

**Nginx:**

```nginx
# Hide sensitive files
location ~* \.(conf|log|ini|sql|php~|bak|old)$ {
    deny all;
}

# Protect config directory
location /config/ {
    deny all;
}

# Prevent PHP execution in uploads
location /uploads/ {
    location ~ \.php$ {
        deny all;
    }
}

# Security headers
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "no-referrer-when-downgrade" always;
add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

# Hide server version
server_tokens off;
```

## 🔐 Authentication & Authorization

### Password Security

**Strong password policy configuration:**

```php
// config/config_security.php
$confs = array(
    'password_min_length' => '12',          // Minimum 12 characters
    'password_require_uppercase' => '1',     // Require uppercase letter
    'password_require_lowercase' => '1',     // Require lowercase letter
    'password_require_numbers' => '1',       // Require numbers
    'password_require_special' => '1',       // Require special characters
    'password_no_common' => '1',            // Block common passwords
    'password_no_personal' => '1',          // Block personal info
);
```

**Password hashing:**

```php
// Use strong password hashing (automatic in SLAED CMS)
function hash_password($password) {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,  // 64MB
        'time_cost' => 4,        // 4 iterations
        'threads' => 3,          // 3 threads
    ]);
}

function verify_password($password, $hash) {
    return password_verify($password, $hash);
}
```

### Session Security

**Secure session configuration:**

```php
// Secure session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);        // HTTPS only
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.regenerate_id', 1);

// Custom session security
function init_secure_session() {
    session_start();
    
    // Regenerate session ID regularly
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }
    
    // IP address validation
    if (isset($_SESSION['user_ip']) && $_SESSION['user_ip'] !== getIp()) {
        session_destroy();
        return false;
    }
    $_SESSION['user_ip'] = getIp();
    
    // User agent validation
    if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== getUserAgent()) {
        session_destroy();
        return false;
    }
    $_SESSION['user_agent'] = getUserAgent();
    
    return true;
}
```

### Two-Factor Authentication (2FA)

**Enable 2FA for administrators:**

```php
// config/config_security.php
$confs = array(
    'two_factor_enable' => '1',             // Enable 2FA
    'two_factor_required_admin' => '1',     // Required for admins
    'two_factor_backup_codes' => '10',      // Number of backup codes
    'two_factor_issuer' => 'Your Site',    // TOTP issuer name
);
```

## 🚫 Attack Prevention

### SQL Injection Protection

**Always use prepared statements:**

```php
// SECURE - Prepared statement
$stmt = $db->prepare("SELECT * FROM {$prefix}_users WHERE email = ? AND active = ?");
$stmt->bind_param("si", $email, $active);
$stmt->execute();
$result = $stmt->get_result();

// INSECURE - Direct concatenation (NEVER USE!)
$query = "SELECT * FROM {$prefix}_users WHERE email = '" . $email . "'";
```

**Input validation with getVar():**

```php
// Automatic protection through input filtering
$id = getVar('get', 'id', 'num');           // Only numbers
$email = getVar('post', 'email', 'email');  // Valid email only
$text = getVar('post', 'text', 'text');     // Safe text
```

### XSS Prevention

**Output encoding:**

```php
// Always encode output
echo htmlspecialchars($user_input, ENT_QUOTES, 'UTF-8');

// Use template system (automatic encoding)
echo setTemplateBasic('content', array(
    '{%title%}' => $title,      // Automatically encoded
    '{%content%}' => $content   // Filtered HTML
));
```

**Content Security Policy:**

```php
// Set CSP header
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://www.google.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:;");
```

### CSRF Protection

**Automatic CSRF protection:**

```php
// Generate token for forms
function csrf_token_field() {
    return '<input type="hidden" name="csrf_token" value="'.generate_csrf_token().'">';
}

// Form usage
echo '<form method="post">';
echo csrf_token_field();
echo '<input type="text" name="title">';
echo '<input type="submit" value="Save">';
echo '</form>';

// Verification
if (!verify_csrf_token(getVar('post', 'csrf_token', 'text'))) {
    die('CSRF token verification failed');
}
```

### File Upload Security

**Secure file upload configuration:**

```php
// config/config_uploads.php
$confup = array(
    'allowed_types' => 'jpg,jpeg,png,gif,pdf,doc,docx',
    'max_size' => '5120',                   // 5MB max
    'scan_viruses' => '1',                  // Virus scanning
    'check_dimensions' => '1',              // Image dimension check
    'rename_files' => '1',                  // Rename uploaded files
    'quarantine_suspicious' => '1',         // Quarantine suspicious files
);
```

**File type validation:**

```php
function secure_file_upload($file) {
    // 1. Check file extension
    $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif', 'pdf');
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed_extensions)) {
        return false;
    }
    
    // 2. Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = array(
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'pdf' => 'application/pdf'
    );
    
    if (!isset($allowed_mimes[$ext]) || $allowed_mimes[$ext] !== $mime) {
        return false;
    }
    
    // 3. Check file size
    if ($file['size'] > 5242880) { // 5MB
        return false;
    }
    
    // 4. Scan for malicious content
    $content = file_get_contents($file['tmp_name']);
    if (preg_match('/<\?php|<script|javascript:/i', $content)) {
        return false;
    }
    
    // 5. Generate safe filename
    $safe_name = uniqid() . '.' . $ext;
    
    return $safe_name;
}
```

## 🔍 Security Monitoring

### Logging Security Events

**Security event logging:**

```php
function log_security_event($event_type, $details = array()) {
    global $user;
    
    $log_entry = array(
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event_type,
        'ip' => getIp(),
        'user_agent' => getUserAgent(),
        'user_id' => is_user() ? intval($user[0]) : 0,
        'details' => $details
    );
    
    $log_line = json_encode($log_entry) . "\n";
    file_put_contents(LOGS_DIR.'/security.log', $log_line, FILE_APPEND | LOCK_EX);
    
    // Alert on critical events
    $critical_events = array('ADMIN_LOGIN_FAILED', 'SQL_INJECTION_ATTEMPT', 'XSS_ATTEMPT');
    if (in_array($event_type, $critical_events)) {
        send_security_alert($event_type, $log_entry);
    }
}

// Usage examples
log_security_event('LOGIN_FAILED', array('username' => $username));
log_security_event('FILE_UPLOAD_BLOCKED', array('filename' => $filename, 'reason' => 'Invalid type'));
log_security_event('ADMIN_ACCESS', array('module' => $module, 'action' => $action));
```

### Intrusion Detection

**Automated threat detection:**

```php
function analyze_request_for_threats() {
    $suspicious_patterns = array(
        // SQL Injection patterns
        '/(\%27)|(\')|(\-\-)|(\%23)|(#)/i',
        '/((\%3D)|(=))[^\n]*((\%27)|(\')|(\-\-)|(\%3B)|(;))/i',
        '/\w*((\%27)|(\'))((\%6F)|o|(\%4F))((\%72)|r|(\%52))/i',
        
        // XSS patterns
        '/((\%3C)|<)((\%2F)|\/)*[a-z0-9\%]+((\%3E)|>)/i',
        '/((\%3C)|<)((\%69)|i|(\%49))((\%6D)|m|(\%4D))((\%67)|g|(\%47))/i',
        '/((\%3C)|<)[^\n]+((\%3E)|>)/i',
        
        // File inclusion patterns
        '/((\.\.\/)|(\.\.\\\))/i',
        '/((\%2E)(\%2E)(\%2F))|((\.\.\/))/',
        '/((\%2E)(\%2E)(\%5C))|((\.\.\\\\))/',
    );
    
    $request_data = $_GET + $_POST + $_COOKIE;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $query_string = $_SERVER['QUERY_STRING'] ?? '';
    
    foreach ($suspicious_patterns as $pattern) {
        // Check request parameters
        foreach ($request_data as $key => $value) {
            if (preg_match($pattern, $value) || preg_match($pattern, $key)) {
                log_security_event('SUSPICIOUS_REQUEST', array(
                    'pattern' => $pattern,
                    'parameter' => $key,
                    'value' => substr($value, 0, 100)
                ));
                return true;
            }
        }
        
        // Check user agent
        if (preg_match($pattern, $user_agent)) {
            log_security_event('SUSPICIOUS_USER_AGENT', array(
                'user_agent' => $user_agent,
                'pattern' => $pattern
            ));
            return true;
        }
        
        // Check query string
        if (preg_match($pattern, $query_string)) {
            log_security_event('SUSPICIOUS_QUERY', array(
                'query_string' => $query_string,
                'pattern' => $pattern
            ));
            return true;
        }
    }
    
    return false;
}
```

### Rate Limiting

**Implement rate limiting:**

```php
function check_rate_limit($action, $limit = 10, $window = 3600) {
    global $db, $prefix;
    
    $ip = getIp();
    $user_id = is_user() ? intval($user[0]) : 0;
    $window_start = time() - $window;
    
    // Clean old entries
    $db->prepare("DELETE FROM {$prefix}_rate_limit WHERE timestamp < ?")->execute([$window_start]);
    
    // Count recent attempts
    $stmt = $db->prepare("SELECT COUNT(*) FROM {$prefix}_rate_limit WHERE action = ? AND (ip = ? OR user_id = ?) AND timestamp > ?");
    $stmt->bind_param("ssii", $action, $ip, $user_id, $window_start);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_row()[0];
    
    if ($count >= $limit) {
        log_security_event('RATE_LIMIT_EXCEEDED', array(
            'action' => $action,
            'count' => $count,
            'limit' => $limit
        ));
        return false;
    }
    
    // Record this attempt
    $stmt = $db->prepare("INSERT INTO {$prefix}_rate_limit (action, ip, user_id, timestamp) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssii", $action, $ip, $user_id, time());
    $stmt->execute();
    
    return true;
}

// Usage
if (!check_rate_limit('login', 5, 900)) { // 5 attempts per 15 minutes
    die('Rate limit exceeded. Please try again later.');
}
```

## 🚨 Incident Response

### Security Incident Handling

**Automated response to threats:**

```php
function handle_security_incident($incident_type, $severity = 'medium') {
    global $conf;
    
    switch ($severity) {
        case 'critical':
            // Immediate lockdown
            block_ip(getIp(), 'Critical security incident');
            notify_admin_immediately($incident_type);
            break;
            
        case 'high':
            // Temporary restriction
            increase_security_level();
            notify_admin($incident_type);
            break;
            
        case 'medium':
            // Log and monitor
            log_security_event($incident_type);
            break;
    }
}

function block_ip($ip, $reason, $duration = 86400) {
    global $db, $prefix;
    
    $expires = time() + $duration;
    $stmt = $db->prepare("INSERT INTO {$prefix}_blocked_ips (ip, reason, expires, created) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("ssi", $ip, $reason, $expires);
    $stmt->execute();
    
    log_security_event('IP_BLOCKED', array(
        'ip' => $ip,
        'reason' => $reason,
        'duration' => $duration
    ));
}
```

### Backup and Recovery

**Security-focused backup strategy:**

```bash
#!/bin/bash
# Secure backup script

BACKUP_DIR="/secure/backups"
DATE=$(date +%Y%m%d_%H%M%S)
SITE_DIR="/var/www/html/slaed"

# Create encrypted database backup
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | \
    gzip | \
    gpg --cipher-algo AES256 --compress-algo 1 --symmetric --output \
    "$BACKUP_DIR/db_$DATE.sql.gz.gpg"

# Create encrypted file backup (excluding sensitive files)
tar --exclude='config/config_db.php' \
    --exclude='storage/logs/*' \
    --exclude='config/cache/*' \
    -czf - "$SITE_DIR" | \
    gpg --cipher-algo AES256 --compress-algo 1 --symmetric --output \
    "$BACKUP_DIR/files_$DATE.tar.gz.gpg"

# Set secure permissions
chmod 600 "$BACKUP_DIR"/*
```

## 📋 Security Checklist

### Pre-Production Checklist

- [ ] **Remove development files** (setup.php, test files)
- [ ] **Set production file permissions**
- [ ] **Configure HTTPS/SSL** with strong ciphers
- [ ] **Enable security headers** (CSP, HSTS, etc.)
- [ ] **Configure secure session settings**
- [ ] **Enable rate limiting** on forms and login
- [ ] **Set up intrusion detection**
- [ ] **Configure automated backups**
- [ ] **Test incident response procedures**
- [ ] **Update all software** (PHP, MySQL, OS)

### Regular Maintenance

- [ ] **Weekly log review** for suspicious activity
- [ ] **Monthly security updates** for all software
- [ ] **Quarterly password policy review**
- [ ] **Semi-annual security audit**
- [ ] **Annual penetration testing**

### Monitoring Alerts

Configure alerts for:
- Multiple failed login attempts
- Suspicious file uploads
- SQL injection attempts
- XSS attempts
- Unusual admin activity
- High traffic from single IP
- File permission changes

## 🔗 Security Tools Integration

### External Security Services

**CloudFlare Integration:**
- DDoS protection
- Web Application Firewall (WAF)
- Rate limiting
- Bot protection

**Fail2ban Configuration:**
```bash
# /etc/fail2ban/jail.local
[slaed-login]
enabled = true
port = http,https
filter = slaed-login
logpath = /var/www/html/slaed/storage/logs/security.log
maxretry = 5
bantime = 3600
findtime = 600
```

**Security Scanner Integration:**
- OWASP ZAP for vulnerability scanning
- Nmap for network security assessment
- SSL Labs for SSL/TLS configuration testing

---

**Remember:** Security is an ongoing process, not a one-time setup. Regular monitoring, updates, and security assessments are essential for maintaining a secure SLAED CMS installation.

For more security resources, see **[[Performance Optimization|Performance-Optimization]]** and **[[Troubleshooting|Troubleshooting]]**.