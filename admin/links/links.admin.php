<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE')) die('Illegal file access');

adminmenu($admin_file.'.php?name=changelog&op=show', 'Changelog', 'editor.png');
adminmenu($admin_file.'.php?name=admins&op=show', _EDITADMINS, 'admins.png');
adminmenu($admin_file.'.php?name=blocks&op=show', _BLOCKS, 'blocks.png');
adminmenu($admin_file.'.php?name=categories&op=show', _CATEGORIES, 'categories.png');
adminmenu($admin_file.'.php?name=comments&op=show', _COMMENTS, 'comments.png');
adminmenu($admin_file.'.php?name=config&op=show', _PREFERENCES, 'preferences.png');
adminmenu($admin_file.'.php?name=database&op=show', _DATABASE, 'database.png');
adminmenu($admin_file.'.php?name=editor&op=function', _EDITOR_IN, 'editor.png');
adminmenu($admin_file.'.php?name=favorites&op=show', _FAVORITES, 'favorites.png');
adminmenu($admin_file.'.php?name=fields&op=show', _FIELDS, 'fields.png');
adminmenu($admin_file.'.php?name=groups&op=show', _UGROUPS, 'groups.png');
adminmenu($admin_file.'.php?name=lang&op=main', _LANG_EDIT, 'lang.png');
adminmenu($admin_file.'.php?name=modules&op=show', _MODULES, 'modules.png');
adminmenu($admin_file.'.php?name=messages&op=show', _MESSAGES, 'messages.png');
adminmenu($admin_file.'.php?name=newsletter&op=show', _NEWSLETTER, 'newsletter.png');
adminmenu($admin_file.'.php?name=privat&op=show', _PRIVAT, 'privat.png');
adminmenu($admin_file.'.php?name=ratings&op=show', _RATINGS, 'ratings.png');
adminmenu($admin_file.'.php?name=referers&op=show', _REFERERS, 'referers.png');
adminmenu($admin_file.'.php?name=replace&op=show', _REPLACE, 'replace.png');
adminmenu($admin_file.'.php?name=rss&op=conf', _RSS, 'rss.png');
adminmenu($admin_file.'.php?name=security&op=show', _SECURITY, 'security.png');
adminmenu($admin_file.'.php?name=sitemap&op=show', _SITEMAP, 'sitemap.png');
adminmenu($admin_file.'.php?name=stat&op=show', _STAT, 'stat.png');
adminmenu($admin_file.'.php?name=template&op=show', _THEME, 'template.png');
adminmenu($admin_file.'.php?name=uploads&op=show', _UPLOADSEDIT, 'uploads.png');
adminmenu($admin_file.'.php?name=users&op=show', _USERS, 'users.png');