# SLAED CMS Blocks and Banners Guide

## Table of Contents
1. [Introduction](#introduction)
2. [Block Types](#block-types)
3. [Standard Blocks](#standard-blocks)
4. [Free (fly) Blocks](#free-fly-blocks)
5. [Adding a File Block](#adding-a-file-block)
6. [Adding an HTML Block](#adding-an-html-block)
7. [Adding an RSS Block](#adding-an-rss-block)
8. [Using Blocks in Templates](#using-blocks-in-templates)
9. [Personal Block Design](#personal-block-design)

## Introduction

The "Blocks and Banners" section provides the ability to build a site from "bricks" called "blocks".

There are the following types of blocks:
- **Standard** – placed in specific places on the site
- **Free (fly)** – these blocks can be used in any places, templates or modules of the system

Most site functionality needs are covered by standard blocks, but in some cases you can use free blocks.

## Block Types

### By Type:
1. **File** – blocks where information is displayed through html and php functions specified in the corresponding file
2. **HTML** – blocks where information is displayed through html functions specified in the block content
3. **RSS** – blocks where information taken from an RSS link is displayed
4. **System** – system blocks include: Admin, UserBox

### By Accessibility (who sees):
- All users
- Only users
- Only administrators
- Only anonymous users

### By Position:
- Left
- Center bottom
- Center top
- Right
- Top banner
- Bottom banner

## Standard Blocks

### Standard Block Design

Standard block design is handled by theme templates: block-center.html, block-all.html, block-right.html and other templates with the block prefix in the name. (See the "Theme" section).

You can create unique design for any block located in the blocks/ directory (block-name.php; name is the block name). To do this:

1. In the templates/theme_design/ directory, you need to create a template file with the name block-name.html
2. In the created template, you need to make unique design for the block-name.php block

**Example:**
You need to make unique design for the block-voting.php block. In this case, a template file is created in the templates/theme_design/ directory with the name block-voting.html. The system will find this template file automatically, and then use it only for designing the block-voting.php block.

## Free (fly) Blocks

A free (fly) block can be placed anywhere on the site page, for which you need to insert the code for generating this free block into the corresponding php file (in config/config_header.php, for example). Template themes ( *.html files) cannot insert free block generation code, since in this case this php code will not be processed. More complete information about inserting blocks into template themes is given in the "Theme" section.

A free block is described in a php script by the function: blocks("why", "who"), where blocks is the function for creating a free block with parameters: why and who.

Depending on the why and who parameters, the free block creation function can:
- Print the generated free block to standard output (display the block on the page)
- Return a string with the generated free block (output the block to a variable for subsequent insertion of this block into an html template). In this case, this free block can be with or without design – this depends on the why parameters.

### Why Parameter Values:
- **none** (displays the block body on the page without design)
  ```php
  $fly_block_1_1 = blocks("none", 15);
  $fly_block_1_2 = blocks("none", "block-menu2.php");
  ```
- **standart** (displays the block body on the page with design)
  ```php
  $fly_block_2_1 = blocks("standart", 15);
  $fly_block_2_2 = blocks("standart", "block-menu2.php");
  ```
- **plzreturn** (outputs the block body to a variable without displaying on the page and without design)
  ```php
  $fly_block_3_1 = blocks("plzreturn", 15);
  $fly_block_3_2 = blocks("plzreturn", "block-menu2.php");
  ```
- **oreturnform** (block body to variable without displaying on the page, but with design)
  ```php
  $fly_block_4_1 = blocks("oreturnform", 15);
  $fly_block_4_1 = blocks("oreturnform", "block-menu2.php");
  ```

* 15 is the bid, the block number in the database (The block number can be viewed in the administrator panel in the № line)

### Who Parameter Values:
- **bid** of the block (block number in the database; table pref_blocks (pref is the prefix of database tables), field bid)
  ```php
  $fly_block_5_1 = blocks("none", 15);
  $fly_block_5_2 = blocks("standart", 15);
  $fly_block_5_3 = blocks("plzreturn", 15);
  $fly_block_5_4 = blocks("oreturnform", 15);
  ```
- **block-name.php** (php file name of the block; name is the block name)
  ```php
  $fly_block_6_1 = blocks("none", "block-menu2.php");
  $fly_block_6_2 = blocks("standart", "block-menu2.php");
  $fly_block_6_3 = blocks("plzreturn", "block-menu2.php");
  $fly_block_6_4 = blocks("oreturnform", "block-menu2.php");
  ```

To use a created free block, it must be active. There should be only one mark on this block - "Free Block", otherwise this block will be standard.

## Adding a File Block

To add a file block, you need to perform the following steps:

### 1. Create a block file

You can create a file for a file block in two ways: through the administrator section interface and through the site's file structure.

#### Creating a file through the administrator section interface

Go to the "Add File Block" tab, which displays the form for adding a new file block.

Specify the name of the file to be created (for example, mysite), taking into account that SLAED CMS will automatically add the "block-" prefix to the file name.

Specify the type of file to be created.

Click the "Create Block" button, after which a form will load into which you need to insert the block code.

**Attention!**
In the block's program code text, to display information you need to use the $content variable instead of standard echo or print methods, this is the only distinctive feature that needs to be taken into account. Everything else is implemented using standard PHP and HTML methods and functions. When implementing blocks, remember that any PHP code must start with <?php and end with ?>.

After inserting, click the "Save" button and the corresponding file will be created in the site's file structure (for example, block-mysite.php, as in our example).

**Attention!**
When creating a file block file, you need to set CHMOD 777 permissions on the blocks/ folder, and subsequently CHMOD 666 on the created file.

#### Creating a file through the site's file structure

Php files for file blocks are stored in the "blocks" directory, which is located in the site's root directory. In this directory, you can create your own php file, necessarily naming it in the format "block-block_name.php" (in case the name format is different, the file will not be visible in the list when adding a block).

### 2. Establish a connection between the created file and the block

To establish a connection between the file and the file block, you need to add a new block by going to the "Add Block" tab.

The addition form has the following fields:
- **Title** – the block title is entered (whatever title is entered - that's what is displayed)
- **RSS Channel Link** – if necessary, so that a certain RSS feed is displayed in the block – specify a link to it or select from the standard list. In case of entering an RSS link, you do not need to select a file in the "File Name" field and fill in "Content". When creating a file block, this field remains unchanged
- **Update Time** – how often the RSS feed should be updated. When creating a file block, this field remains unchanged
- **File Name** – when creating a file block, a previously created file is selected
- **Content** – filled in only when creating an HTML block (when filling in the content, you do not need to select a file). When creating a file block, this field remains unchanged
- **Position** – the position where the block should be displayed is selected
- **Display Block in Modules** – a list of modules in which the block should be displayed is selected
- **Language** – selection of the language version in which the block should be displayed
- **Activate** – "yes" - the block will be shown, "no" - the block will not be shown
- **Work Time in Days** – you can specify the number of days during which the block should be active. 0 – the block is active without restrictions
- **After Expiration** – you can select what the system should automatically do with the block after the work time expires
- **Who Will See This?** – you can specify for which user categories the block viewing is available

## Adding an HTML Block

Creating HTML blocks is very convenient for displaying banners, links and other elements for which HTML code is sufficient.

Adding an HTML block is just as simple as a file block, for this you need to go to the "Add Block" tab and fill in the fields that are the same as when creating a file block, but instead of selecting a file, fill in the "Content" field. The content must be filled with html code.

## Adding an RSS Block

Go to the "Add Block" tab and fill in the fields that are the same as when creating a file block, but instead of selecting a file, specify the RSS channel link and update time.

## Using Blocks in Templates

1. Create a block in the system administrator panel, it can be a standard or free block
2. Activate the block and in the block list determine its № - number
3. To insert the block, for example, in the main file: index.html of the theme design, it is sufficient to add the code section: {%BLOCKS n,XXX%}

XXX - your block number
n - without design
or
XXX - the full name of the block file in the directory: blocks/ for example: block-forum.php
s - standard design

Other options are possible, at your discretion.

### Variable Description
- {%BLOCKS none,XXX%} or {%BLOCKS n,XXX%} - Arbitrary system block or free block without design, where XXX is either the block ID or the block file name
- {%BLOCKS standart,XXX%} or {%BLOCKS s,XXX%} - Arbitrary system block or free block with free block design, where XXX is either the block ID or the block file name

## Personal Block Design

To form exclusive design for a block, you need to create a template file for this block with the name.

- **block-15.html** - 15 is the block number in the block list. In the database (prefix_blocks table, bid field)
- **block-name.html** - name is the block name, the full name of the block file in the directory: blocks/

The block design file should be placed in the main theme directory along with other standard system templates.

### Examples of Using Free Blocks

**Example 1:**
You need to create a free block with parameters:
- Display the free block body on the page
- Without design
- By block number in the database
  ```php
  blocks("none", 15);
  ```
- Using the block name
  ```php
  blocks("none", "block-menu2.php");
  ```

**Example 2:**
You need to create a free block with parameters:
- Display the free block body on the page
- With design
- By block number in the database
  ```php
  blocks("standart", 15);
  ```
- Using the block name:
  ```php
  blocks("standart", "block-menu2.php");
  ```

**Example 3:**
You need to create a free block with parameters:
- Output the free block body to a variable
- Without design
- By block number in the database
  ```php
  global $blockg;
  ob_start();
  blocks("plzreturn", 15);
  $blockg["15"] = ob_get_clean();
  ```
- Using the block name:
  ```php
  global $blockg;
  $blockg["menu2"] = blocks("plzreturn", "block-menu2.php");
  ```

**Example 4:**
You need to create a free block with parameters:
- Output the free block body to a variable
- With design
- By block number in the database
  ```php
  global $blockg;
  ob_start();
  blocks("oreturnform", 15);
  $blockg["15"] = ob_get_clean();
  ```
- Using the block name
  ```php
  global $blockg;
  $blockg["menu2"] = blocks("oreturnform", "block-menu2.php");
  ```

To display on the page a variable containing the free block body, you need to do the following:
Open the config/config_header.php file and insert the code into it:
```php
global $blockg;
$blockg["menu2"] = blocks("plzreturn", "block-menu2.php");
```

Insert the $blockg[menu2] array into any place of the template (in templates/your_theme/index.html, for example) (note the absence of quotes inside the square brackets!) On the page, instead of $blockg[menu2], the menu2 block will be displayed, the code of which is contained in the blocks/block-menu2.php file.

For correct output to the page of a free block that is requested from the database by bid from the pref_blocks table, with the rss_info module enabled (output of news in RSS format), you need to write to the php file config/config_header.php:
```php
global $blockg;
$blockg["menu2"] = blocks("plzreturn", "block-menu2.php");
ob_start();
blocks("none", 15);
$blockg["15"] = ob_get_clean();
```

A free block can be displayed on site pages throughout the site if its code is embedded in the theme design, or it can be displayed in a specific module if its code is embedded in that module's code.

A free block cannot be processed by the system as a standard block, but a standard block can be processed by the system as a free block. Example: the modules block, having bid 1 (slaed_blocks table), can be displayed on the page again as a free block, and then all the restrictions that are imposed on the modules block will be applied to this block (show only on the main page of the site or only in one module or in selected modules or in all modules).

## Changing the Block File

To edit a file block file, go to the "Edit Block" tab and select the file to be edited.

## Changing the Block

In the "Main" tab in the "Functions" column, go to the value (Full Editing) – the block editing form will be displayed.

## Changing the Block Position

In the "Main" tab in the "Position" column, change the block position using the arrows. The selected block position is displayed in the "Position" column.

---

© 2005-2026 Eduard Laas. All rights reserved.