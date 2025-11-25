# Installation Guide

This guide will walk you through the complete installation process of SLAED CMS.

## 📋 Pre-Installation Checklist

Before starting, ensure your server meets the system requirements:

### ✅ System Requirements

**Minimum Requirements:**
- PHP 8.0+ with extensions: `mysqli`, `gd`, `zip`, `mbstring`, `json`, `curl`
- MySQL 5.7+ or MariaDB 10.3+
- Apache 2.4+ with mod_rewrite OR Nginx 1.14+
- 128MB RAM for PHP
- 50MB disk space

**Recommended Setup:**
- PHP 8.1+ with OPcache enabled
- MySQL 8.0+ or MariaDB 10.6+
- 256MB+ RAM for PHP
- SSD storage
- SSL certificate for HTTPS

### 🔍 PHP Extensions Check

Run this command to verify required extensions:

```bash
php -m | grep -E "(mysqli|gd|zip|mbstring|json|curl)"
```

Expected output:
```
curl
gd
json
mbstring
mysqli
zip
```

## 📥 Download and Extract

### Option 1: Direct Download
1. Download the latest release from GitHub
2. Extract to your web server directory:

```bash
# Download
wget https://github.com/your-repo/slaed-cms/archive/main.zip

# Extract
unzip main.zip -d /var/www/html/
cd /var/www/html/slaed-cms-main/
```

### Option 2: Git Clone
```bash
git clone https://github.com/your-repo/slaed-cms.git /var/www/html/slaed
cd /var/www/html/slaed/
```

## 🗄️ Database Setup

### Create Database and User

**MySQL/MariaDB:**
```sql
-- Create database
CREATE DATABASE slaed_cms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user
CREATE USER 'slaed_user'@'localhost' IDENTIFIED BY 'your_secure_password';

-- Grant privileges
GRANT ALL PRIVILEGES ON slaed_cms.* TO 'slaed_user'@'localhost';
FLUSH PRIVILEGES;
```

### Database Configuration

Edit the configuration file:

```bash
cp config/config_db.php.example config/config_db.php
```

Configure database settings in `config/config_db.php`:

```php
<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

$confdb = array();
$confdb['host'] = "localhost";              // Database host
$confdb['uname'] = "slaed_user";            // Database username
$confdb['pass'] = "your_secure_password";   // Database password
$confdb['name'] = "slaed_cms";              // Database name
$confdb['type'] = "mysqli";                 // Database type
$confdb['engine'] = "InnoDB";               // Storage engine
$confdb['charset'] = "utf8mb4";             // Character set
$confdb['collate'] = "utf8mb4_unicode_ci";  // Collation
$confdb['prefix'] = "slaed";                // Table prefix
$confdb['sync'] = "0";                      // Time sync
$confdb['mode'] = "0";                      // Strict mode

$prefix = "slaed";                          // Table prefix (duplicate)
$admin_file = "admin";                      // Admin file name
?>
```

## 📁 File Permissions

Set correct file permissions:

```bash
# Basic permissions
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# Writable directories
chmod -R 777 config/
chmod -R 777 uploads/
chmod -R 777 storage/

# Secure configuration
chmod 600 config/config_db.php
```

## 🚀 Run Installation Wizard

### Access Installation

1. Open your web browser
2. Navigate to: `http://yoursite.com/setup.php`
3. Follow the installation wizard

### Installation Steps

**Step 1: System Check**
- Verify PHP version and extensions
- Check file permissions
- Test database connection

**Step 2: Database Setup**
- Confirm database settings
- Create tables and indexes
- Insert default data

**Step 3: Administrator Account**
- Create admin username and password
- Set admin email address
- Configure basic site settings

**Step 4: Basic Configuration**
- Site name and description
- Default language
- Initial modules activation

**Step 5: Completion**
- Remove installation files
- Security recommendations
- Access admin panel

## ⚙️ Web Server Configuration

### Apache Configuration

Create or update `.htaccess` file:

```apache
# Security settings
Options -Indexes -ExecCGI
ServerSignature Off

# Protect sensitive files
<FilesMatch "\.(conf|log|ini|sql|php~|bak)$">
    Require all denied
</FilesMatch>

# Protect config directory
<Files "*.php">
    <RequireAll>
        Require all denied
        Require local
    </RequireAll>
</Files>

# URL Rewriting for SEO
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?go=$1 [QSA,L]

# Security headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options SAMEORIGIN
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# Caching for static files
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
    ExpiresByType image/svg+xml "access plus 1 month"
</IfModule>

# Compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>
```

### Nginx Configuration

Example virtual host configuration:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name yoursite.com www.yoursite.com;
    
    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name yoursite.com www.yoursite.com;
    
    root /var/www/html/slaed;
    index index.php index.html;
    
    # SSL configuration
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
    
    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~* \.(conf|log|ini|sql|php~|bak)$ {
        deny all;
    }
    
    location /config/ {
        deny all;
    }
    
    # PHP processing
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Security
        fastcgi_param HTTP_PROXY "";
        fastcgi_param SERVER_NAME $http_host;
        fastcgi_param HTTPS on;
    }
    
    # Static files caching
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1M;
        add_header Cache-Control "public, immutable";
        add_header Vary "Accept-Encoding";
    }
    
    # SEO-friendly URLs
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # Deny access to uploads directory for PHP files
    location /uploads/ {
        location ~ \.php$ {
            deny all;
        }
    }
}
```

## 🔧 Post-Installation Configuration

### Secure the Installation

1. **Remove setup files:**
```bash
rm setup.php
rm -rf setup/
```

2. **Secure configuration files:**
```bash
chmod 600 config/*.php
chown www-data:www-data config/*.php
```

3. **Set up log rotation:**
```bash
# Add to /etc/logrotate.d/slaed-cms
/var/www/html/slaed/storage/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
}
```

### Configure PHP (Optional)

Add to `php.ini` for optimal performance:

```ini
; Memory and execution
memory_limit = 256M
max_execution_time = 300
max_input_time = 300

; File uploads
upload_max_filesize = 32M
post_max_size = 32M
max_file_uploads = 20

; Sessions
session.gc_maxlifetime = 86400
session.cookie_lifetime = 0
session.cookie_secure = 1
session.cookie_httponly = 1
session.cookie_samesite = "Strict"

; OPcache (recommended)
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 4000
opcache.revalidate_freq = 60
opcache.save_comments = 0
opcache.fast_shutdown = 1
```

### MySQL Optimization

Add to `my.cnf` for better performance:

```ini
[mysqld]
# InnoDB settings
innodb_buffer_pool_size = 256M
innodb_log_file_size = 64M
innodb_log_buffer_size = 16M
innodb_flush_log_at_trx_commit = 2

# Query cache
query_cache_type = 1
query_cache_size = 32M
query_cache_limit = 2M

# General settings
max_connections = 100
max_connect_errors = 10
```

## ✅ Verification

### Test Installation

1. **Frontend:** Visit `http://yoursite.com`
2. **Admin Panel:** Visit `http://yoursite.com/admin.php`
3. **Test Features:**
   - Create a test page
   - Upload an image
   - Check user registration

### System Information

Use built-in tools to verify installation:
- `/systeminfo.php` - System information
- `/check_compat.php` - Compatibility check
- `/test_write.php` - File permissions test

### Performance Testing

```bash
# Test page load time
curl -o /dev/null -s -w "%{time_total}\n" http://yoursite.com

# Check memory usage
grep "memory_get_peak_usage" /var/log/php_errors.log
```

## 🚨 Troubleshooting

### Common Issues

**Database Connection Error:**
- Verify database credentials
- Check MySQL/MariaDB service status
- Ensure database exists and user has permissions

**File Permission Errors:**
```bash
# Fix permissions
sudo chown -R www-data:www-data /var/www/html/slaed/
sudo chmod -R 755 /var/www/html/slaed/
sudo chmod -R 777 /var/www/html/slaed/config/
sudo chmod -R 777 /var/www/html/slaed/uploads/
sudo chmod -R 777 /var/www/html/slaed/storage/
```

**500 Internal Server Error:**
- Check error logs: `/var/log/apache2/error.log` or `/var/log/nginx/error.log`
- Verify PHP error log: `/var/log/php_errors.log`
- Check `.htaccess` syntax

**Module Not Working:**
- Verify module is activated in admin panel
- Check module configuration
- Review module-specific logs

### Getting Help

If you encounter issues:
1. Check the **[[Troubleshooting|Troubleshooting]]** wiki page
2. Search existing issues on GitHub
3. Create a new issue with detailed information
4. Ask in the community forum

## 🎉 Next Steps

After successful installation:

1. **[[Quick Start|Quick-Start]]** - Basic configuration
2. **[[Admin Panel|Admin-Panel]]** - Administrative interface
3. **[[Security Guide|Security-Guide]]** - Secure your installation
4. **[[Performance Optimization|Performance-Optimization]]** - Speed improvements

---

**Congratulations!** SLAED CMS is now installed and ready to use. Proceed to the **[[Quick Start|Quick-Start]]** guide to begin configuring your site.