# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net
# Compatible: MySQL 8.0+ & MariaDB 10+

ALTER TABLE `{prefix}_users` CHANGE `user_warnings` `user_warnings` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;
ALTER TABLE `{prefix}_clients` CHANGE `info` `info` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '';
