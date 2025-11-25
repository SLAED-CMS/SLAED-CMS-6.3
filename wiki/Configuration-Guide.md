# SLAED CMS Configuration Guide

## Table of Contents
1. [Introduction](#introduction)
2. [Main Settings](#main-settings)
3. [SEO Settings](#seo-settings)
4. [Language Settings](#language-settings)
5. [Censorship Settings](#censorship-settings)
6. [Search Settings](#search-settings)
7. [Search Bots](#search-bots)
8. [Caching](#caching)
9. [Email Settings](#email-settings)

## Introduction

The "Configuration" section is designed to manage the main settings (configurations) of the site and consists of the following tabs:

- **Main** – basic site settings are set
- **SEO** – settings for search engine promotion of the site
- **Language** – management of multilingual site settings
- **Censorship** – settings for excluding unwanted words and phrases
- **Search** – site search settings
- **Search Bots** – definition of the format for displaying information about search bots on the site
- **Caching** – setting caching parameters for site pages
- **Email** – setting the main email template

## Main Settings

### System Version
Displays the name and version number of the system on which the site is built.

### Site Name
You can set the site name that will be displayed, for example, in the page headers of the site.

### Site Address
The full URL of the site is specified.

### Site Logo Installation
The logo is selected from the directory: images/logos/. The logo is used in the design depending on the site theme.

### Description
This description is used in the "description" html pages of the site (this field is analyzed, including by search engines).

### Administrator Notes
Information that will be available to all authors (users of the management section). The display of this information is shown in the upper part of the system administrator panel.

### Site Launch Date
The date when the site was launched.

### Administrator E-Mail
Used when sending informational messages to the site administrator (including when restoring a password). When sending messages to users, this e-mail is indicated in the "From" field.

### User Cookies Name
Cookies for users are necessary for the system to identify the user. For example, if he is registered and has logged in before, the system will identify him automatically (no need to log in again).

You should change the standard Cookies name, a size of 3 to 5 characters is sufficient.

### Administrator Session Name
The use of sessions is similar to the use of user Cookies: determining administrator activity. But unlike Cookies, the session works on the server side, its operation time is limited, set on the server. This method is more secure. The session is considered completed if there have been no requests from the user to the site within a certain period of time (usually 15-30 minutes). The only disadvantage is that after closing the browser, or going offline, the session ends, so you will need to log in again.

You should change the standard name, a size of 3 to 5 characters is sufficient.

### User Cookies Storage Time, in Days
The time to store Cookies on the user's computer. All the specified time, the system will identify the user automatically, if the time expires, ask to log in with their login/password. As a rule, a value of about 30 days is set.

### Session Work Time, in Minutes
Means: how many minutes an authorized site user can be inactive and still be considered online (on the site) while his name will be displayed in the User Info block.

### Amount of Material in Administrator Panel Modules
The number of records that will be displayed by default on one page (for example, in the "News" section).

### Forum Profile Link, Forum Private Messages Link
If a third-party forum is installed on the site, you can specify links that should lead to the user's profile and to the "Private Messages" section of the forum. These links will appear in the personal profiles of site users.

### IP Address Check Site Link
On which site the IP address check (whois) will be performed. IP addresses, for example, are visible to the administrator next to the author who left a comment on the site, and if you go to the IP address, it will be checked by the service specified in the field.

### General User Registration With
Specifies which forum automatic registration will take place with (selected depending on the version of the forum installed on the site).

### Theme Design
The site theme that should be used at the moment is selected. Theme management takes place in the "Themes" section of the administrator panel.

### Module Loaded on the Main Page
Modules that should be loaded on the main page of the site are specified (if provided by the site theme).

### Module Loaded on the Administrator Main Page
The module that should be loaded on the main page of the administration section is specified (if provided by the site theme).

### Secret Code Type
The type of secret code (Captcha) that will be used on the site is selected. You can choose between S-Captcha and K-Captcha. Below, opposite the "Preview" field, the current secret code is displayed.

### Parameter Responsible for the Graphic Code
Selects in which cases the graphic code (captcha) should be displayed.

### Secret Code Image Quality, in Percent
The lower the percentage, the harder it is to read the inscription.

### Font Type in the Secret Code Image
Selects the type of content writing in the code.

### Editor Usage
Selects the editor that will be used when filling in the content of site modules. These can be editors: BB-editor; HTML editor TinyMCE 4; HTML editor TinyMCE 3.5; HTML editor CKEditor 4.2.

You can quickly change the editor in the administration section in the "Editor Usage" block (displayed on the left).

### Global System Time
Specify the time zone whose time will be used by the site. The time is displayed, for example, next to each comment or message on the forum.

### Activation of Debug Extensions
The functionality allows displaying the following information for viewing by administrators and moderators of the site (at your choice): system information (processor load level); analyzer of GET, POST, COOKIE variables; FILES, SESSION, SERVER; database query analyzer. In case of activation, the information is displayed at the bottom of the site page (below the footer). This functionality will allow you to track the complete technical picture of the operation of modules and additions (both standard and third-party), as well as notice problems in the operation of the hosting in a timely manner.

### Debug Function Visibility
Allows you to select the display variant of debug functions. In case of selecting the "All Visitors" mode, the information will be visible to all visitors of the project, in the "Only Administrators" variant, respectively, only to project administrators.

### Activate Visitor Sessions?
When enabled, the system will track all actions of site visitors and record them in the session. If during the time specified in the "Session Work Time, in Minutes" field, the user did nothing, the session is deleted. If you set the "No" mark, the load on the site will decrease slightly.

### Display Messages on the Main Page?
Whether messages from the "Messages" section of the administrator panel will be displayed on the main page.

### Display Page Generation Speed?
Information about the speed of page generation is displayed in the site footer in the format: Generation: 0.2 sec. and 7 database queries in 0.001 sec. (in the example, the data is conditional).

### Activate Administrator "System Information" Block?
The setting is responsible for displaying the "System Information" block.

### Activate Main Administrator Panel?
Responsible for the administrator panel. In case of selecting the "No" option, all the content of the administrator panel will be displayed in a reduced mode in the left blocks, this method is designed to save space and better overview of the material.

### Activate Information Department Editor?
If the setting is active, you can edit the reference system of the administration section (data in the "Information" tab). For example, this option may be needed if the reference system needs to be adjusted for the specific features of users of the management section.

### Close Site for Maintenance?
The option is used in cases when maintenance work is being carried out on the site. When the option is enabled, site visitors see a message about the temporary unavailability of the site. The style and text of the message depends on the site theme.

## SEO Settings

### Symbol Separating Titles and Headers
The specified symbol is used to separate the components of the page title (browser title).

### Site Keywords
Keywords are entered separated by commas with a space. Keywords are used in the "keywords" html pages of the site (this field is analyzed, including by search engines). The keywords specified in this field are used by default or in case of disabling automatic keyword generation.

### Maximum Number of Keywords
The specified number is used when automatically generating keywords (so many words will be formed maximally). If automatic keyword generation is not enabled, this parameter will not be used. It is not recommended to specify too many keywords (15 is the maximum).

### Minimum Number of Characters in a Word
The specified number is used when automatically generating keywords. Words whose length is less than the specified one will not be included in the list of keywords. If automatic keyword generation is not enabled, this parameter will not be used.

### Activate Automatic Keyword Generation?
The system itself will generate keywords for each page of the site, based on the content of the page. Thus, each page contains different sets of keywords.

### Mix Keywords?
In the "keywords" tag, keywords will be written randomly each time the page is updated. The parameter is active both for automatic and manual keyword generation.

### Activate URL Conversion to SEO URLs?
SEO URLs are web addresses that are convenient for human perception, as well as systems and methods for building such addresses. When this option is activated, site URLs are converted in accordance with the SEO URL concept. Note that activating this option increases page generation time. This function is supported only if Mod Rewrite is installed on the server, you can check this in the "System Information" block, if Mod Rewrite is installed, the following will be displayed: "Mod Rewrite: On", otherwise "Mod Rewrite: Off".

## Language Settings

### Attention!
The value of language variables is set in the "Language Editor" section.

### Default Language
The site language that will be loaded by default when opened.

### Activate Multilingual Properties
When selecting the "Yes" parameter, the site will contain links to other language versions.

### Show Flags Instead of Menu
When selecting the "Yes" parameter, links to switch the site language version will be presented in the form of flags of the corresponding countries.

### Activate Visitor Country Detector
The country will be determined depending on the value of the user's IP address.

### Automatically Switch Site Language to Visitor Country?
When selecting the "Yes" parameter, the site language will switch to the corresponding value depending on the visitor's country.

## Censorship Settings

Using censorship settings, you can significantly limit the output of unwanted content on site pages.

### Censorship Method
At the moment, there are essentially two ways available: to conduct censorship or not.

### Replacement of Forbidden Words With
A set of characters that the forbidden word will be replaced with is specified.

### Forbidden Words
Listed separated by commas without spaces.

### Convert Addresses to Hyperlinks?
If the text entered through the BB-editor contains a link text, it will automatically be displayed as a hyperlink. You can view which editor is used on the "Main" tab of the "Configuration" section.

## Search Settings

### Minimum Allowable Number of Characters in Search Query
If the search query contains fewer characters than specified in this field, the system will issue a corresponding warning.

### Number of Search Results per Page
Search results are displayed page by page and each page will contain the specified number of results. Note that if nothing is specified, the search results will always be empty.

### Maximum Number of User Page Numbers
The list of pages will include the specified number of pages, and then a separate ellipsis will be displayed with the number of the last page.

### Search with Expanded Description?
If "Yes" is selected, then each search result will display a fragment of the material containing the search text. If "No" is selected, then each result will contain only the page title, as well as information about the module and category to which the material belongs.

## Search Bots

This tab sets the rules for displaying information about search bots on the site, as well as a list of search engine sites from which transitions to your project are recorded.

## Caching

Caching leads to faster loading of site pages and reduces the load on the server.

### Caching
Only the main page or all sections of the site. For large portals with high traffic, more than 5,000 unique visitors per day and content of more than 25,000 pages, it is recommended to use caching only for the main page. Otherwise, there may be an overflow of the hosting allocated space on the hard disk, as well as increased load on it.

### Statistical Cache Storage Time, in Seconds
Cached information is used for the specified time, after which the files will be rewritten.

### Forced Deletion of Old Cache, in Days
Automatic cleaning of cache files when the time limit is exceeded.

### Compress Cached Files?
Compression of cached files reduces their size by removing tabs, spaces in HTML tags, comments in CSS styles, writing all the text in one line. Size savings, depending on the content, in the range of 5-6%. This speeds up the transfer of the page to the visitor's browser. It becomes significantly more difficult to copy articles and other site content by automatic programs, grabbers.

### Activate Browser Caching?
When viewing site pages, their content will be stored in the computer's memory, and when updating or reopening the page by the browser, the data loading will essentially occur from the visitor's computer.

### Activate GZip Compression?
Used to transfer data in compressed form (most browsers support viewing GZip compressed files). As a rule, this feature is used to speed up the receipt of requested information by site visitors, as well as to reduce the server's output traffic.

The file caching system allows you to significantly reduce the load on the server by disabling the PHP interpreter and database queries. When first accessing the URL - the generated file is saved under a unique md5 signature, and when subsequently accessing this URL, the file is issued from the cache memory, which reduces the page loading time. Database queries and launching the PHP interpreter on the server side are not performed when issuing from the cache.

### Please Note!
If there is a lot of updated material on the site, caching may lead to the fact that users will not see updates promptly. Set an adequate cache storage time, depending on the project's traffic, we recommend from 300 to 600 seconds.

## Email Settings

The tab sets the main template of the email sent by the system in certain cases (for example, when restoring a password). Note that [text] is the content that will be substituted by the system, so you should not delete it. Everything else can be changed at your discretion.

---

© 2005-2026 Eduard Laas. All rights reserved.