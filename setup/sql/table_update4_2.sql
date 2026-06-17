# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net
# Compatible: MySQL 8.0+ & MariaDB 10+

RENAME TABLE `{prefix}_auto_links_ip` TO `{prefix}_referer`;
ALTER TABLE `{prefix}_referer` CHANGE `referer_id` `lid` INT(11) NOT NULL DEFAULT '0';
ALTER TABLE `{prefix}_referer` ADD `uid` INT(11) NOT NULL AFTER `id`;
ALTER TABLE `{prefix}_referer` ADD `name` VARCHAR(25) NOT NULL AFTER `uid`;
ALTER TABLE `{prefix}_referer` ADD `link` VARCHAR(255) NOT NULL AFTER `referer`;
ALTER TABLE `{prefix}_admins` ADD `editor` TINYINT(1) NULL AFTER `super`;
ALTER TABLE `{prefix}_admins` ADD `smail` TINYINT(1) NULL AFTER `editor`;
