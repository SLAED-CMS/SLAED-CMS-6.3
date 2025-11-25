# Author: Eduard Laas
# Copyright © 2005 - 2025 SLAED
# License: GNU GPL 3
# Website: slaed.net

ALTER TABLE `{prefix}_users` CHANGE `user_block` `user_block` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;
ALTER TABLE `{prefix}_users` DROP `user_icq`, DROP `user_aim`, DROP `user_yim`, DROP `user_msnm`;
ALTER TABLE `{prefix}_users` ADD `user_network` VARCHAR(255) NOT NULL AFTER `user_agent`;