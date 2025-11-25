# SLAED CMS Migration Guide

## Table of Contents
1. [Introduction](#introduction)
2. [Upgrading SLAED CMS](#upgrading-slaed-cms)
3. [Migrating from Other CMS](#migrating-from-other-cms)
4. [Data Migration Tools](#data-migration-tools)
5. [Common Migration Issues](#common-migration-issues)
6. [Post-Migration Checklist](#post-migration-checklist)
7. [Performance Optimization](#performance-optimization)

## Introduction

This guide provides comprehensive instructions for migrating to SLAED CMS, whether you're upgrading from an older version or migrating from another content management system. Following these guidelines will help ensure a smooth transition with minimal downtime.

### Before You Begin
Before starting any migration process:

1. **Backup Everything** - Create complete backups of your current system
2. **Test Environment** - Set up a staging environment for testing
3. **Review Requirements** - Check system requirements for the target version
4. **Plan Downtime** - Schedule migration during low-traffic periods
5. **Document Current State** - Record current configurations and settings

### Migration Types
This guide covers:
- **Version Upgrades** - Moving from older SLAED CMS versions
- **Platform Migrations** - Moving from other CMS platforms
- **Server Migrations** - Moving to new hosting environments

## Upgrading SLAED CMS

### Version Compatibility
SLAED CMS maintains backward compatibility within major versions but may require manual intervention for major version upgrades.

#### Minor Version Upgrades (1.x to 1.y)
- Generally straightforward
- Database structure remains compatible
- Configuration files typically don't change
- Module compatibility maintained

#### Major Version Upgrades (1.x to 2.x)
- May require database schema changes
- Configuration file format changes
- Module compatibility review needed
- Template updates may be required

### Upgrade Process

#### 1. Preparation
```bash
# Backup current installation
tar -czf slaed_backup_$(date +%Y%m%d).tar.gz /path/to/slaed

# Backup database
mysqldump -u username -p database_name > slaed_db_backup_$(date +%Y%m%d).sql

# Check current version
cat config/version.php
```

#### 2. Download and Extract
```bash
# Download latest version
wget https://slaed.info/downloads/slaed-latest.tar.gz

# Extract files
tar -xzf slaed-latest.tar.gz

# Compare file structures
diff -r old_version/ new_version/
```

#### 3. File Migration
1. Preserve your [config.php](file:///C:/Users/eduar/AppData/Roaming/JetBrains/IntelliJIdea2023.1/scratches/config.php) file
2. Preserve custom themes and templates
3. Preserve uploaded media files
4. Update core files while preserving customizations

#### 4. Database Upgrade
Run the upgrade script:
```php
<?php
// Run database upgrade
include('upgrade.php');
?>
```

Or manually apply SQL changes:
```sql
-- Apply database schema changes
ALTER TABLE `prefix_users` ADD COLUMN `last_login` DATETIME;
UPDATE `prefix_config` SET `value` = '2.0.0' WHERE `key` = 'version';
```

#### 5. Module Updates
1. Check module compatibility with new version
2. Update incompatible modules
3. Reconfigure module settings
4. Test module functionality

### Upgrade Checklist
- [ ] Backup completed
- [ ] Staging environment tested
- [ ] Configuration files preserved
- [ ] Custom themes/templates backed up
- [ ] Database backup created
- [ ] Upgrade script executed
- [ ] Modules updated and tested
- [ ] Functionality verified
- [ ] Performance tested

## Migrating from Other CMS

### WordPress Migration

#### Data Export
Export WordPress data:
1. Use WordPress export tool (Tools > Export)
2. Export content, users, and settings
3. Download exported XML file

#### Data Import
Import to SLAED CMS:
```php
// WordPress to SLAED CMS importer
include('importers/wordpress_importer.php');

$result = import_wordpress_data('/path/to/wordpress-export.xml', [
    'import_users' => true,
    'import_posts' => true,
    'import_pages' => true,
    'import_media' => true,
    'import_categories' => true,
    'import_tags' => true
]);
```

#### Mapping Considerations
WordPress to SLAED CMS mapping:
- **Posts** → News module
- **Pages** → Pages module
- **Users** → User system
- **Categories/Tags** → Category system
- **Media** → Media gallery
- **Comments** → Comment system

### Drupal Migration

#### Data Export
Export Drupal data:
1. Use Drupal's migrate module
2. Export content types, users, taxonomy
3. Export files and media

#### Data Import
Import to SLAED CMS:
```php
// Drupal to SLAED CMS importer
include('importers/drupal_importer.php');

$result = import_drupal_data('/path/to/drupal-export/', [
    'import_nodes' => true,
    'import_users' => true,
    'import_taxonomy' => true,
    'import_files' => true
]);
```

### Joomla Migration

#### Data Export
Export Joomla data:
1. Use Joomla's export feature
2. Export articles, users, categories
3. Export media files

#### Data Import
Import to SLAED CMS:
```php
// Joomla to SLAED CMS importer
include('importers/joomla_importer.php');

$result = import_joomla_data('/path/to/joomla-export/', [
    'import_articles' => true,
    'import_users' => true,
    'import_categories' => true,
    'import_media' => true
]);
```

### Generic Migration Process

#### 1. Content Analysis
Analyze source content:
- Content types and structures
- User roles and permissions
- Media and file organization
- Custom functionality

#### 2. Mapping Strategy
Create mapping between source and target:
- Content type mappings
- User role mappings
- Category/tag mappings
- URL structure planning

#### 3. Data Extraction
Extract data from source system:
- Database exports
- File system backups
- Configuration files
- Media assets

#### 4. Data Transformation
Transform data to match SLAED CMS structure:
- Convert data formats
- Map fields and properties
- Handle relationships
- Validate data integrity

#### 5. Data Import
Import data into SLAED CMS:
- Use import tools or custom scripts
- Validate imported data
- Handle import errors
- Test functionality

## Data Migration Tools

### Built-in Importers
SLAED CMS includes several built-in importers:

#### WordPress Importer
```php
// Import WordPress data
$importer = new WordPressImporter();
$result = $importer->import([
    'source_file' => '/path/to/wordpress-export.xml',
    'options' => [
        'import_posts' => true,
        'import_pages' => true,
        'import_users' => true,
        'import_media' => true,
        'preserve_ids' => false
    ]
]);
```

#### Database Importer
```php
// Direct database import
$importer = new DatabaseImporter();
$result = $importer->import([
    'source_db' => [
        'host' => 'source_host',
        'username' => 'source_user',
        'password' => 'source_pass',
        'database' => 'source_db'
    ],
    'table_mappings' => [
        'wp_posts' => 'slaed_news',
        'wp_users' => 'slaed_users',
        'wp_categories' => 'slaed_categories'
    ]
]);
```

### Custom Import Scripts
Create custom import scripts for specific needs:

```php
<?php
// Custom migration script
class CustomMigration {
    private $source_db;
    private $target_db;
    
    public function __construct($source_config, $target_config) {
        $this->source_db = new Database($source_config);
        $this->target_db = new Database($target_config);
    }
    
    public function migrateUsers() {
        $users = $this->source_db->query("SELECT * FROM users");
        foreach ($users as $user) {
            $this->target_db->insert('slaed_users', [
                'username' => $user['username'],
                'email' => $user['email'],
                'password' => $user['password'],
                'created_at' => $user['created_at']
            ]);
        }
    }
    
    public function migrateContent() {
        // Custom content migration logic
    }
}

$migration = new CustomMigration($source_config, $target_config);
$migration->migrateUsers();
$migration->migrateContent();
?>
```

### Migration Validation
Validate migration results:

```php
// Validate migration
$validator = new MigrationValidator();
$report = $validator->validate([
    'source_counts' => [
        'users' => 1500,
        'posts' => 5000,
        'media' => 2000
    ],
    'target_counts' => [
        'users' => $target_db->count('slaed_users'),
        'posts' => $target_db->count('slaed_news'),
        'media' => $target_db->count('slaed_media')
    ]
]);

if ($report['status'] === 'success') {
    echo "Migration validation passed";
} else {
    echo "Migration issues found: " . implode(', ', $report['errors']);
}
```

## Common Migration Issues

### Database Issues

#### Character Encoding
```php
// Fix character encoding issues
function fix_encoding($text) {
    // Convert from source encoding to UTF-8
    $text = mb_convert_encoding($text, 'UTF-8', 'auto');
    
    // Fix common encoding issues
    $text = str_replace(
        ['â€™', 'â€œ', 'â€', 'â€“', 'â€”'],
        ["'", '"', '"', '-', '--'],
        $text
    );
    
    return $text;
}
```

#### Data Type Mismatches
```sql
-- Handle data type mismatches
ALTER TABLE `slaed_users` MODIFY `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP;
UPDATE `slaed_content` SET `status` = 'published' WHERE `status` = '1';
```

### URL Structure Issues

#### Redirect Setup
Create redirects for old URLs:
```php
// URL redirect mapping
$url_redirects = [
    '/old-page.html' => '/new-page',
    '/category/old-category/' => '/news/category/new-category',
    '/user/profile.php?id=([0-9]+)' => '/user/$1'
];

// Implement redirects
foreach ($url_redirects as $old_url => $new_url) {
    if ($_SERVER['REQUEST_URI'] === $old_url) {
        header("Location: $new_url", true, 301);
        exit;
    }
}
```

### User Account Issues

#### Password Migration
```php
// Migrate passwords from other systems
function migrate_password($old_password_hash, $algorithm) {
    switch ($algorithm) {
        case 'wordpress':
            // WordPress uses PHPASS
            return wp_hash_password($old_password);
        case 'drupal':
            // Drupal uses custom hashing
            return user_hash_password($old_password);
        default:
            // Default to SLAED CMS hashing
            return slaed_hash_password($old_password);
    }
}
```

### Media and File Issues

#### File Path Corrections
```php
// Update file paths in content
function update_file_paths($content) {
    // Replace old file paths with new ones
    $content = str_replace(
        '/wp-content/uploads/',
        '/uploads/',
        $content
    );
    
    // Update image URLs
    $content = preg_replace(
        '#http://oldsite.com/wp-content/uploads/([^"]+)#',
        'https://newsite.com/uploads/$1',
        $content
    );
    
    return $content;
}
```

## Post-Migration Checklist

### Content Verification
- [ ] All pages accessible
- [ ] All images loading correctly
- [ ] All links working
- [ ] Content formatting preserved
- [ ] SEO metadata intact

### User Verification
- [ ] All user accounts migrated
- [ ] User roles correctly assigned
- [ ] Passwords working
- [ ] User profiles complete

### Functionality Testing
- [ ] Admin panel accessible
- [ ] All modules functioning
- [ ] Forms submitting correctly
- [ ] Search working properly
- [ ] Comments system operational

### Performance Testing
- [ ] Page load times acceptable
- [ ] Database queries optimized
- [ ] Caching working correctly
- [ ] Server resource usage normal

### Security Verification
- [ ] File permissions correct
- [ ] SSL certificate installed
- [ ] Security plugins configured
- [ ] Backup systems operational
- [ ] Monitoring tools active

## Performance Optimization

### Post-Migration Optimization

#### Database Optimization
```sql
-- Optimize database after migration
OPTIMIZE TABLE slaed_users;
OPTIMIZE TABLE slaed_news;
OPTIMIZE TABLE slaed_categories;

-- Add indexes for better performance
ALTER TABLE `slaed_news` ADD INDEX `idx_status_created` (`status`, `created_at`);
ALTER TABLE `slaed_users` ADD INDEX `idx_username` (`username`);
```

#### Caching Configuration
```php
// Configure caching for migrated site
$config = [
    'cache_enabled' => true,
    'cache_ttl' => 3600,
    'cache_driver' => 'file', // or 'apc', 'memcached'
    'cache_path' => '/tmp/slaed_cache'
];

// Enable page caching
enable_page_cache($config);
```

#### CDN Integration
```php
// Configure CDN for media files
$cdn_config = [
    'enabled' => true,
    'url' => 'https://cdn.yoursite.com',
    'directories' => ['/uploads/', '/media/']
];

// Apply CDN configuration
apply_cdn_config($cdn_config);
```

### Monitoring and Maintenance

#### Performance Monitoring
Set up monitoring:
- Page load time tracking
- Database query performance
- Server resource usage
- Error rate monitoring

#### Regular Maintenance
Schedule regular maintenance tasks:
- Database optimization
- Cache clearing
- Log file rotation
- Security updates

---

*© 2005-2026 Eduard Laas. All rights reserved.*