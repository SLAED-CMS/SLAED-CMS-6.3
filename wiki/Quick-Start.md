# Quick Start Guide

Get your SLAED CMS site up and running in minutes! This guide assumes you've already completed the **[[Installation|Installation]]**.

## 🚀 Initial Setup (5 minutes)

### Step 1: Access Admin Panel

1. Open your browser and navigate to: `http://yoursite.com/admin.php`
2. Login with the administrator credentials you created during installation

### Step 2: Basic Site Configuration

**Site Settings** → **General Configuration**

```
Site Name: Your Website Name
Site Description: Brief description of your site
Admin Email: admin@yoursite.com
Default Language: Choose your preferred language
Time Zone: Select your time zone
```

### Step 3: Activate Essential Modules

**Modules** → **Module Management**

✅ **Recommended modules to activate:**
- **News** - For articles and announcements
- **Pages** - For static content
- **Users** - For user management
- **Contact** - For contact forms

### Step 4: Configure Theme

**Appearance** → **Theme Settings**
- Select your preferred theme
- Customize colors and layout
- Configure logo and favicon

## 📝 Create Your First Content (10 minutes)

### Create a Welcome Page

1. **Navigate to:** Admin Panel → **Pages** → **Add New Page**

2. **Fill in the details:**
```
Title: Welcome to Our Website
Content: Welcome to our new website! We're excited to share our content with you.
Category: Main (create if needed)
Status: Published
```

3. **Click "Save"**

### Add Your First News Article

1. **Navigate to:** Admin Panel → **News** → **Add News**

2. **Create your article:**
```
Title: Welcome to SLAED CMS
Summary: Discover the power of SLAED CMS for your website
Content: SLAED CMS is a powerful, secure, and efficient content management system...
Category: Announcements
Author: Administrator
Status: Published
```

3. **Optional:** Upload a featured image
4. **Click "Publish"**

### Set Up Main Navigation

1. **Navigate to:** Admin Panel → **Appearance** → **Menu Settings**

2. **Add menu items:**
```
Home → index.php
News → index.php?name=news
Pages → index.php?name=pages
Contact → index.php?name=contact
```

## 👥 User Management (5 minutes)

### Configure User Registration

1. **Navigate to:** Admin Panel → **Users** → **User Settings**

2. **Configure registration options:**
```
Allow Registration: Yes
Email Activation: Yes (recommended)
Manual Approval: No (for automatic approval)
Default User Group: Registered Users
```

### Create User Groups

1. **Navigate to:** Admin Panel → **Users** → **Groups**

2. **Create groups as needed:**
```
Editors - Can create and edit content
Moderators - Can moderate comments and users
Contributors - Can submit content for review
```

## 🎨 Customize Appearance (10 minutes)

### Logo and Branding

1. **Upload your logo:**
   - Go to **Appearance** → **Theme Settings**
   - Upload logo image (recommended: 250x60px, PNG format)
   - Set site favicon

### Color Scheme

1. **Customize colors:**
   - Primary color (headers, links)
   - Secondary color (buttons, accents)
   - Background colors
   - Text colors

### Layout Options

Configure sidebar blocks:
- **Login Block** - User authentication
- **Menu Block** - Site navigation
- **News Block** - Latest articles
- **Search Block** - Site search

## 🔧 Essential Settings (15 minutes)

### Security Configuration

1. **Navigate to:** Admin Panel → **System** → **Security Settings**

2. **Configure security:**
```
Enable CSRF Protection: Yes
XSS Filtering: Yes
Login Attempts Limit: 5
Ban Duration: 60 minutes
Enable Captcha: Yes (for forms)
```

### Performance Optimization

1. **Navigate to:** Admin Panel → **System** → **Performance**

2. **Enable optimization:**
```
Enable Caching: Yes
Cache Duration: 1 hour
Compress CSS: Yes
Compress JavaScript: Yes
Optimize Images: Yes
```

### SEO Settings

1. **Navigate to:** Admin Panel → **SEO** → **Settings**

2. **Configure SEO:**
```
Enable SEO URLs: Yes
Meta Keywords: your, site, keywords
Meta Description: Your site description
Generate Sitemap: Yes
```

## 📊 Analytics Setup (5 minutes)

### Google Analytics (Optional)

1. **Get Google Analytics ID** from Google Analytics dashboard
2. **Navigate to:** Admin Panel → **System** → **Analytics**
3. **Enter your Google Analytics ID:** `G-XXXXXXXXXX`

### Built-in Statistics

Enable SLAED CMS built-in analytics:
```
Track Page Views: Yes
Track User Activity: Yes
Track Search Queries: Yes
Generate Reports: Daily
```

## 🔍 Testing Your Site (10 minutes)

### Frontend Testing

1. **Visit your homepage:** `http://yoursite.com`
2. **Check navigation** - All menu items work
3. **Test responsive design** - Works on mobile devices
4. **Verify content** - Pages and news display correctly

### User Experience Testing

1. **Test user registration** (if enabled)
2. **Submit a contact form**
3. **Search functionality**
4. **Comment system** (if enabled)

### Admin Panel Testing

1. **Create test content** in each module
2. **Upload test images**
3. **Modify user permissions**
4. **Check system logs**

## 🛡️ Security Checklist (5 minutes)

Before going live, ensure:

- [ ] **Change default admin password** to strong password
- [ ] **Remove setup files** (`setup.php`, `setup/` directory)
- [ ] **Set secure file permissions** (644 for files, 755 for directories)
- [ ] **Enable HTTPS/SSL** for production sites
- [ ] **Configure backups** (database and files)
- [ ] **Test contact forms** work correctly
- [ ] **Review user permissions** and groups

## 📋 Essential Pages to Create

### Required Pages

1. **About Us** - Company/site information
2. **Privacy Policy** - Privacy and data handling
3. **Terms of Service** - Usage terms and conditions
4. **Contact** - Contact information and form

### Optional Pages

1. **FAQ** - Frequently asked questions
2. **Sitemap** - Site structure overview
3. **RSS Feeds** - Syndication information

## 🎯 Next Steps

Now that your basic site is set up:

### Content Strategy
- **Plan your content calendar**
- **Create content categories**
- **Set up editorial workflow**

### Advanced Features
- **[[Module Development|Module-Development]]** - Add custom functionality
- **[[Theme Development|Theme-Development]]** - Customize appearance
- **[[Performance Optimization|Performance-Optimization]]** - Speed improvements

### Maintenance
- **[[Security Guide|Security-Guide]]** - Secure your installation
- **[[Backup Strategy|Backup-Strategy]]** - Protect your data
- **[[Monitoring|Monitoring]]** - Track performance

## 🆘 Common Issues

### Can't Access Admin Panel
- Check file permissions
- Verify database connection
- Clear browser cache

### Content Not Displaying
- Check module activation
- Verify content status (published)
- Review category permissions

### Performance Issues
- Enable caching
- Optimize images
- Check database performance

### User Registration Problems
- Verify email settings
- Check user permissions
- Review registration configuration

## 📞 Getting Help

If you need assistance:

1. **Check the Wiki** - Comprehensive documentation
2. **Search Issues** - GitHub issue tracker
3. **Community Forum** - Ask questions and get help
4. **Professional Support** - Commercial support available

---

**Congratulations!** 🎉 Your SLAED CMS site is now ready for content creation and user engagement.

**Ready for more?** Explore the **[[User Manual|User-Manual]]** for detailed feature guides or the **[[Admin Panel|Admin-Panel]]** documentation for advanced configuration options.