<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

define('_ADMIN_SE','Installation of the manager');
define('_CONFIG','Web Site Configuration');
define('_CONF_1','Database Hostname');
define('_CONF_2','Database Username');
define('_CONF_3','Database Password');
define('_CONF_4','Database Name');
define('_CONF_9','Database table\'s prefix');
define('_CONF_10','Administration panel filename');
define('_CONF_10_INFO','With a view of safety change the standard name at a file admin.php. Rename it that you have thought up, for example: %1$s.php also specify its new name, but without the termination «.php» in our case: %1$s');
define('_CONF_11','Code from config/setup.unlock');
define('_CONF_3_INFO','Leave the field empty to keep the password the site already uses.');
define('_LANG','Select Interface Language');
define('_NEXT_SE','Next');
define('_EXTSETUP','A required PHP extension is not installed on your server');
define('_PHPSETUP','Version PHP established on your server mismatches minimal requirements of system, it should be not below PHP 8.4!');
define('_SAVE_NEW','Installation and configuration');
define('_SAVE_UPDATE','Updating and configuration');
define('_SERRORPERM','has no necessary sanctions for record on a server.<br>Establish the necessary attributes');
define('_SETUPLOCK','The site is already installed, so the installer is locked. To run an update, upload the file config/setup.unlock holding a code of your own of at least 8 characters, open setup.php again and enter that code; a successful run removes the file.');
define('_SETUPCODE','The code does not match the one in config/setup.unlock. Nothing was changed.');
define('_SETUPJOUR','An unfinished configuration operation of the site waits in storage/backup/config. Finish it on the restore screen of the configuration in the administration panel, then start the installer again. Nothing was changed.');
define('_SETUPAFILE','The administration panel filename may hold only Latin letters, digits, _ and -, and may name no file of the site root other than admin.php or the current panel; delete the panel file of the old version first. Nothing was changed.');
define('_SETUPPREFIX','The table prefix may hold only Latin letters, digits and _, up to 32 characters. Nothing was changed.');
define('_SETUPINNODB','These tables are not InnoDB, convert them and start the update again:');
define('_SETUPTABLES','The tables %1$s_users and %1$s_admins are not both in the database, check the table prefix of the site.');
define('_SETUPTAKEN','The database already holds tables of the prefix %s_, a new installation needs an empty database or another prefix.');
define('_SETUPTYPE','Choose a new installation or one of the offered updates. Nothing was changed.');
define('_SETUPVER','The database server %1$s is older than %2$s.');
define('_SETUP_NEW','New installation of system');
define('_SETUP_SLAED','Installation SLAED CMS');
define('_SUPDATE','Updating of system');
define('_TABLE','The table');
