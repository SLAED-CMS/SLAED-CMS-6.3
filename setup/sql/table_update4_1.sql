# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net
# Compatible: MySQL 8.0+ & MariaDB 10+

ALTER TABLE `{prefix}_rating` CHANGE `name` `uid` INT(11) NOT NULL DEFAULT '0';
