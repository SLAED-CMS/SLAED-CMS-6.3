# SLAED CMS Editor Guide

## Table of Contents
1. [Introduction](#introduction)
2. [Editor Sections](#editor-sections)
3. [System Core](#system-core)
4. [System Header](#system-header)
5. [System SEO URLs](#system-seo-urls)
6. [Server SEO URLs](#server-seo-urls)
7. [Robot Rules](#robot-rules)
8. [Usage Examples](#usage-examples)

## Introduction

**Attention!**
This section is intended only for administrators who are familiar with server software and the PHP language.

The "Editor" section allows you to directly manage the main system files through the administration panel. File management is performed in the tabs:

To make changes to a file, go to the corresponding tab and make changes in the file content editor that opens, to save click "Save".

**Attention!**
If the configuration files do not have the appropriate access rights (CHMOD - 666), the system will issue a corresponding warning.

## Editor Sections

### System Core
System core injection file: config_core.php. In the tab, you can add code that will be placed in the system core.

### System Header
System header injection file: config_header.php. In the tab, you can insert code that will be injected into the system head. As a rule, this file is used to inject java scripts and meta tags.

### System SEO URLs
System-level SEO URL transformation rules configuration file: config_rewrite.php. In the tab, you can modify or supplement the standard rules for transforming browser address strings. This feature is supported only if Mod Rewrite is installed on the server.

### Server SEO URLs
Server-level SEO URL transformation rules configuration file: .htaccess. In the tab, you can modify or supplement the standard rules for transforming browser address strings at the server level. This feature is supported only if Mod Rewrite is installed on the server.

### Robot Rules
Configuration file for indexing instructions and parameters for search robots: robots.txt. In the tab, you can modify or supplement the text file located in the site's root directory, in which special instructions for search robots are written. These instructions can prohibit indexing of certain sections or pages on the site, indicate proper domain "mirroring", recommend the search robot to observe a certain time interval between downloading documents from the server, etc. In this file, you can specify indexing parameters for the site both for all robots at once and for each search engine separately.

## Examples of Using Editor Injections in the "System Header" Section

### Redirecting a Visitor Who Came from a Specific Site

Go to the "System Header" tab and add the following entry:

```php
$reflink = "#slaed.net#i";
$metlink = "news.html";
$referer = text_filter(getenv("HTTP_REFERER"));
if (preg_match($reflink, $referer)) { 
echo '<meta http-equiv="refresh" content="0; url='.$metlink.'">'; 
}
```

$reflink – responsible for the site address from which the visitor came.
Instead of: slaed.net, specify your domain.
$metlink – responsible for the page where the visitor will be redirected
Instead of: news.html, specify the required page or site.

### Prohibiting Copying Information from the Site

Everything that can be read and seen can accordingly be copied. There is no effective protection against copying. But this does not mean that it cannot be prevented, you can disable the use of the right or other mouse buttons that are used for copying, this will become insignificant, but still an obstacle to copying for beginning users.

Go to the "System Header" tab and add the following entry:

```php
echo '<script>
<!--
function click() {
// To disable the left button, put the number 1 
// To disable the third button, put the number 3 
if (event.button == 2) { 
// Enter your inscription here, which will appear in the warning window 
alert("Copyright Scriptic!"); 
}
}
document.onmousedown=click;
//-->
</script>';
```

Note that this code only works on Internet Explorer browsers. It's not impossible that there is something similar for other browsers. If you want to use another code, just replace this one with your code by inserting it between echo ' and ';

### Redirecting a Visitor from a Specific Country

Go to the "System Header" tab and add the following entry:

```php
$userlang = "Russia"; 
$metlink = "news.html"; 
$userip = user_geo_ip(getip(), 2); 
if ($userip == $userlang) { 
echo '<meta http-equiv="refresh" content="0; url='.$metlink.'">'; 
}
```

$userlang – responsible for the name of the country from which the visitor came.
The country name should not be arbitrary and must correspond to the class usage standards. The correctness of writing a particular country can be checked in the $COUNTRY_NAMES variable of the file: core/geo_ip.php
$metlink – responsible for the page where the visitor will be redirected
Instead of: news.html, specify the required page or site.

## Examples of Using Editor Injections in the "Server SEO URLs" Section

### Setting a Prohibition on Other Sites Using Any Images from the Site

It's no secret that some sites save their traffic and server space by using remote images from other sites, thereby consuming someone else's traffic and increasing the load on the server. To prevent this, we offer the following methods for use.

Go to the "Server SEO URLs" tab and add after:

```apache
# Mod rewrite on
RewriteEngine On
RewriteBase /
```

the following entry:

```apache
RewriteOptions MaxRedirects=100
RewriteCond %{HTTP_REFERER} !^http://(www\.)?slaed\.net/ [NC]
RewriteRule \.(jpe?g|gif|bmp|png)$ http://www.slaed.net/templates/default/images/logos/slaed_logo_60x60.png [L]
```

Instead of: slaed, specify your site's domain name.
Instead of: net, specify your site's domain zone.
Instead of: http://www.slaed.net/templates/default/images/logos/slaed_logo_60x60.png, specify a link to the logo or image that will be displayed on the site that uses images from the site.

### Combining the Domain with www and Without

At first glance, a domain with the www prefix and without it is the same, but in fact it is not. They are initiated by the server as two different domains and, as a rule, two completely different sites can be installed on them. Some hosts provide the merging of two domains initially and it is performed automatically, some have this setting in the hosting control panel, for hosts where merging is not provided, you can use the following method.

Go to the "Server SEO URLs" tab and add after:

```apache
# Mod rewrite on
RewriteEngine On
RewriteBase /
```

the following entry:

```apache
# Redirect
RewriteCond %{HTTP_HOST} !^www\.slaed\.net [NC]
RewriteRule ^(.*)$ http://www.slaed.net/$1 [R=301,L]
```

Instead of: slaed, specify your site's domain name.
Instead of: net, specify your site's domain zone.
For this method to work, the host's server must support working with .htaccess, Mod Rewrite must be installed and activated.

### Possible Rules and Settings

In the description below, possible rules with descriptions are offered for your attention, which are acceptable for use in our case on the "Server SEO URLs", namely in the main .htaccess file, which is located in the main system directory.

```apache
# Prohibit directory listing and viewing
Options All -ExecCGI -Indexes -Includes +FollowSymLinks
```

```apache
# Site index page
DirectoryIndex index.php
```

```apache
# Default site encoding
AddDefaultCharset utf-8
```

```apache
# Redirect in case of corresponding server error
ErrorDocument 400    /index.php?error=400
ErrorDocument 401    /index.php?error=401
ErrorDocument 403    /index.php?error=403
ErrorDocument 404    /index.php?error=404
ErrorDocument 500    /index.php?error=500
ErrorDocument 503    /index.php?error=503
```

```apache
# Security settings, relevant for versions below PHP 5.3.0, for PHP 5.4.0 and above not relevant
php_flag register_globals off
php_flag safe_mode on
php_flag magic_quotes_gpc on
```

```apache
# Prohibit showing server software versions
ServerSignature Off
```

The above rule, unfortunately, is not supported by all hosts. The settings described below are available only if mod_rewrite is installed on the server.

```apache
# Enable mod_rewrite
RewriteEngine On
```

The mod_rewrite module is a software module of the Apache web server (note that it will not run under other web servers!). Its primary function is URL action manipulation. The module is very versatile and diverse, some real examples are shown here.

```apache
# Root directory
RewriteBase /
```

```apache
# Redirect from www to without www
RewriteCond %{HTTP_HOST}    ^www\.(.*) [NC]
RewriteRule ^(.*)$    http://%1/$1 [R=301,L]
```

```apache
# Redirect from without www to www
RewriteCond %{HTTP_HOST}    ^[^.]+\.[^.]+$
RewriteCond %{HTTPS}s    ^on(s)|
RewriteRule ^    http%1://www.%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
```

```apache
# Redirect from http protocol to https
RewriteCond %{HTTPS} off
RewriteRule (.*) https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
```

```apache
# Redirect addresses from index.php to without index.php
RewriteCond %{THE_REQUEST}    ^GET.*index\.php [NC]
RewriteRule (.*?)index\.php/*(.*)    /$1$2 [R=301,L]
```

```apache
# Redirect to a new category
RewriteRule ^old/(.*)$    /new/$1 [R=301,L]
```

```apache
# Request processing rules for blocking common exploits
RewriteCond %{QUERY_STRING}    proc/self/environ [OR]
RewriteCond %{QUERY_STRING}    mosConfig_[a-zA-Z_]{1,21}(=|\%3D) [OR]
RewriteCond %{QUERY_STRING}    base64_(en|de)code\(.*\) [OR]
RewriteCond %{QUERY_STRING}    base64_encode[^(]*\([^)]*\) [OR]
RewriteCond %{QUERY_STRING}    (<|\%3C)([^s]*s)+cript.*(>|\%3E) [NC,OR]
RewriteCond %{QUERY_STRING}    GLOBALS(=|\[|\%[0-9A-Z]{0,2}) [OR]
RewriteCond %{QUERY_STRING}    _REQUEST(=|\[|\%[0-9A-Z]{0,2})
RewriteRule .*    index.php [F]
```

```apache
# Blocking MySQL injections
RewriteCond %{QUERY_STRING}    CONCAT.*\( [NC,OR]
RewriteCond %{QUERY_STRING}    UNION.*SELECT.*\( [NC,OR]
RewriteCond %{QUERY_STRING}    UNION.*ALL.*SELECT [NC]
RewriteRule ^(.*)$    index.php [F,L]
```

```apache
# Blocking file injections 
RewriteCond %{REQUEST_METHOD}    GET
RewriteCond %{QUERY_STRING}    [a-zA-Z0-9_]=http:// [OR]
RewriteCond %{QUERY_STRING}    [a-zA-Z0-9_]=(\.\./?)+ [OR]
RewriteCond %{QUERY_STRING}    [a-zA-Z0-9_]=/([a-z0-9_.]/?)+ [NC]
RewriteRule .*    - [F]
```

```apache
# Redirect for other domains
RewriteCond %{HTTP_HOST}    !^slaed\.net$
RewriteRule ^(.*)$    http://slaed.net/$1 [R=301,L]
```

---

© 2005-2026 Eduard Laas. All rights reserved.